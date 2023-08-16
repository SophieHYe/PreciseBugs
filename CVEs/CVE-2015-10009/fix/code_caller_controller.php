<?php
require_once 'app/controllers/asset_controller.php';
/**
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Code Caller Asset
 * @author     Tim Glen <tim@nonfiction.ca>
 * @copyright  2005-2007 nonfiction studios inc.
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    SVN: $Id$
 * @link       http://www.nterchange.com/
 */
class CodeCallerController extends AssetController {
	function __construct() {
		$this->name = 'code_caller';
		$this->versioning = true;
		$this->base_view_dir = BASE_DIR;
		parent::__construct();
	}

	function render($options) {
		$in_nterchange = defined('IN_NTERCHANGE')?constant('IN_NTERCHANGE'):false;
		$in_surftoedit = defined('IN_SURFTOEDIT')?constant('IN_SURFTOEDIT'):false;
		if (!$in_nterchange || $in_surftoedit) {
			$model = &$this->getDefaultModel();
			$content = $model->content;
			require_once 'vendor/JSON.php';
			$json = new Services_JSON();
			if ($code = $json->decode($content) && !empty($code)) {
				if (isset($code->controller) && isset($code->action)) {
					$this->getContent((array) $code, $model->dynamic);
				}
			} else {
				while (false !== ($pos = strpos($content, '{call'))) {
					$pos2 = strpos($content, '}', $pos)+1;
 					$str = substr($content, $pos, $pos2-$pos);
 					// clean up the string
 					$str = trim(str_replace(array('{call ', '}'), '', $str));
 					// replace value
					$value = '';
					// find matches
					preg_match_all('/\s?([^=]+)=[\"\']?([^\"\'\s$]+)[\"\']?/', $str, $matches);
					// push the matches into an array if they exists
					if (isset($matches[0])) {
						$params = array();
						for ($i=0;$i<count($matches[0]);$i++) {
							$params[$matches[1][$i]] = $matches[2][$i];
						}
						$value = $this->getContent($params, $model->dynamic);
					}
					$content = substr($content, 0, $pos) . $value . substr($content, $pos2 + 1);
				}
			}
			$model->content = $content;
			$this->set($model->toArray());
			unset($json);
		}
		return parent::render($options);
	}

	function getContent($params, $dynamic) {
		$content = '';
		$controller = $params['controller'];
		$action = $params['action'];
		unset($params['controller'], $params['action']);
		include_once 'controller/inflector.php';
		$method = Inflector::camelize($action);
		if ($ctrl = &NController::factory($controller)) {
			if ($dynamic) {
				$content = $this->dynamicPHP($ctrl, $method, NController::getIncludePath($controller), $params);
			} else {
				$content = $ctrl->$method($params);
			}
			unset($ctrl);
		}
		return $content;
	}

	function dynamicPHP(&$obj, $method, $include_file, $params=array()) {
		$ret = '';
		if (is_object($obj) && method_exists($obj, $method)) {
			$ret .= '<?php include_once \'' . $include_file . '\';';
			$ret .= '$obj = '    . $this->wrapSanitizedSerializer($obj)   . ';';
			$ret .= '$params = ' . $this->wrapSanitizedSerializer($param) . ';';
			$ret .= 'print $obj->' . $method . '($params);';
			$ret .= '?>';
		}
		return $ret;
	}

	private function wrapSanitizedSerializer($obj) {
		$encoded_serialized_string = base64_encode(serialize($obj));
		return "unserialize(base64_decode('$encoded_serialized_string'))";
	}
}
?>
