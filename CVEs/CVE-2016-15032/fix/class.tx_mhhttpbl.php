<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Marc Hoersken <info@marc-hoersken.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * class 'tx_mhhttpbl' for the 'mh_httpbl' extension.
 *
 */

/**
 * http:BL blocking extension
 *
 * @author	Marc Hoersken <info@marc-hoersken.de>
 * @author  Jigal van Hemert <jigal@xs4all.nl>
 * @package TYPO3
 * @subpackage mh_httpbl
 */
class tx_mhhttpbl {
	var $extKey = 'mh_httpbl';
	var $debug = false;
	var $params = false;
	var $pObj = false;
	var $config = false;
	var $domain	= 'dnsbl.httpbl.org';
	var $content = '';
	var $request = '';
	var $result = '';
	var $first = 0;
	var $days = 0;
	var $score = 0;
	var $type = -1;
	var $codes = array(
		0 => 'Search Engine',
		1 => 'Suspicious',
		2 => 'Harvester',
		3 => 'Suspicious &amp; Harvester',
		4 => 'Comment Spammer',
		5 => 'Suspicious &amp; Comment Spammer',
		6 => 'Harvester &amp; Comment Spammer',
		7 => 'Suspicious &amp; Harvester &amp; Comment Spammer'
	);

	/**
	 * Hook page id lookup before rendering the content.
	 *
	 * @param	object		$_params: parameter array
	 * @param	object		$pObj: partent object
	 * @return	void
	 */
	function checkBlacklist(&$params, &$pObj) {
		$this->params = &$params;
		$this->pObj = &$pObj;
		$this->config = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->debug = $this->config['debug'];
		$this->runQuery();

		if (($this->type >= $this->config['type']) && (($this->score >= $this->config['score']) || (empty($this->config['score'])))) {
			$this->stopOutput();
		} else {
			$this->pObj->fe_user->setKey('ses','tx_mhhttpbl_user', true);
			$this->pObj->fe_user->storeSessionData();
		}
	}
	
	/**
	 * Sends a DNS request to dnsbl.httpbl.org and checks for bad users.
	 *
	 * @return	integer		IP type
	 */
	function runQuery() {
		if ($this->debug) {
			t3lib_div::devlog('accesskey: ' . $this->config['accesskey'], $this->extKey, 1);
		}

		if (empty($this->config['accesskey'])) {
			return $this->type = -1;
		}

		if (empty($_SERVER['REMOTE_ADDR'])) {
			return $this->type = -2;
		}

		$remote_addr = $_SERVER['REMOTE_ADDR'];
		$remote_addr_quoted = $GLOBALS['TYPO3_DB']->fullQuoteStr($remote_addr, 'tx_mhhttpbl_whitelist');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_mhhttpbl_whitelist', 'whitelist_ip = '.$remote_addr_quoted);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			return $this->type = -3;
		}

		if ($this->pObj->fe_user->getKey('ses','tx_mhhttpbl_user') == true || (($this->pObj->fe_user->getKey('ses','tx_mhhttpbl_hash') == t3lib_div::_GET('continue')) && (strlen(t3lib_div::_GET('continue')) > 0))) {
			return $this->type = -4;
		}

		$this->request = $this->config['accesskey'].'.'.implode('.', array_reverse(explode('.', $_SERVER['REMOTE_ADDR']))).'.'.$this->domain;
		$this->result = gethostbyname($this->request);

		if ($this->result != $this->request) {
			list($this->first, $this->days, $this->score, $this->type) = explode('.', $this->result);
		}

		if ($this->debug) {
			t3lib_div::devlog('dnsbl.httpbl.org result: ' . $result, $this->extKey, 1);
		}

		if($this->first != 127 || !array_key_exists($this->type, $this->codes)) {
			return $this->type = -5;
		}

		return $this->type;
	}

	/**
	 * Stops TYPO3 output and shows an error page.
	 *
	 * @return	void
	 */
	function stopOutput() {
		if ($this->debug)
			t3lib_div::devlog('blocking user: ' . $_SERVER['REMOTE_ADDR'], $this->extKey, 1);

		$remote_addr = $_SERVER['REMOTE_ADDR'];
		$remote_host = gethostbyaddr($remote_addr);
		$remote_addr_html = htmlspecialchars($remote_addr);
		$remote_host_html = htmlspecialchars($remote_host);

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_mhhttpbl_blocklog', array('crdate'=>time(), 'tstamp'=>time(), 'block_ip'=>$remote_addr, 'block_type'=>$this->type, 'block_score'=>$this->score));

		$usrHash = t3lib_div::shortMD5(serialize($_SERVER));
		$stdMsg = "<strong>You have been blocked.</strong><br />Your IP appears to be on the httpbl.org/projecthoneypot.org blacklist.<br /><br />###REQUEST_IP###<br /><br />###USER_TYPE###";
		$message = (!empty($this->config['message']) ? $stdMsg : $this->pObj->csConvObj->utf8_encode($message, $this->pObj->renderCharset));
		$message = str_replace('###REQUEST_IP###', '<strong>' . $remote_addr_html . '</strong> (' . $remote_host_html . ')', $message);
		$message = str_replace('###USER_TYPE###', $this->codes[$this->type], $message);

		$this->pObj->fe_user->setKey('ses','tx_mhhttpbl_hash', $usrHash);
		$this->pObj->fe_user->setKey('ses','tx_mhhttpbl_user', false);
		$this->pObj->fe_user->storeSessionData();

		$this->content = '
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>TYPO3 - http:BL</title>
	</head>
	<body style="background: #fff; color: #ccc; font-family: \'Verdana\', \'Arial\', sans-serif; text-align: center;">
		'.$message.'
	</body>
	<script type="text/javascript">
		window.location.replace(window.location.href.split(\'?\')[0] + \'?continue='.$usrHash.'\');
	</script>
</html>
		';
	}

	/**
	 * Hook content before caching.
	 *
	 * @param	object		$_params: parameter array
	 * @param	object		$pObj: partent object
	 * @return	void
	 */
	function addHoneyPot(&$params, &$pObj) {
		$this->params = &$params;
		$this->pObj = &$pObj;
		$this->config = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		if (!empty($this->config['quicklink'])) {
			$imgconf = array(
				'file' => 'EXT:mh_httpbl/mod1/clear.gif',
				'file.' => array(
					'maxH' => 1,
					'maxW' => 1,
				),
				'stdWrap.' => array(
					'typolink.' => array(
						'parameter' => $this->config['quicklink'],
						'title' => '',
						'wrap' => '<div style="display: none;">|</div>',
					),
				),
			);
			/*
				Create file with clear.gif
				Wrap it with a link to the Quicklink
				Wrap it in a div to comply with XHTML 1.0 Strict
			*/
			$cObj = t3lib_div::makeInstance('tslib_cObj');
			$cObj->start('', 'tt_content');
			/*
				Create a cObj to do the work of generating (X)HTML
			*/
			if (file_exists(t3lib_extMgm::extPath('mh_httpbl').'mod1/clear.gif')) {
				$content = $cObj->IMAGE($imgconf);
			} else {
				$content = $cObj->typoLink('<!--&nbsp;-->', $imgconf['stdWrap']['typolink']);
				/*
					If no clear.gif is found use a part of the configuration to wrap a link and div around
					a comment...
				*/
			}
		}

		if (!empty($this->content)) {
			$pObj->content = trim($this->content);
			$pObj->no_cache = true;
		} else if (!empty($this->config['quicklink'])) {		
			$pObj->content = str_replace('<body>', '<body>'.$content, $this->pObj->content);
			$pObj->content = str_replace('</body>', $content.'</body>', $this->pObj->content);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mh_httpbl/class.tx_mhhttpbl.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mh_httpbl/class.tx_mhhttpbl.php']);
}

?>
