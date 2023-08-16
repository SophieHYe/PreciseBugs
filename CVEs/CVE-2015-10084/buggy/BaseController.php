<?php
/**
 * @author Mikel Madariaga Madariaga <mikel@irontec.com>
 * @author Daniel Rendon <dani@irontec.com>
 */
class Iron_Controller_Rest_BaseController extends \Zend_Rest_Controller
{

    public $status;
    public $loggers = array();

    protected $_sendEtag = true;
    protected $_logActive;
    protected $_viewData;
    protected $_contexts = array(
        'index',
        'error',
        'rest-error',
        'get',
        'post',
        'head',
        'put',
        'delete',
        'options'
    );

    protected $_acceptedAdvancedSearchConditions = array(
//         'between',
//         'notin',
//         'in',
//         'like',
//         'notlike',
    );

    public function init()
    {
        $bootstrap = $this->_invokeArgs['bootstrap'];
        $plugins = $bootstrap->getContainer()->frontcontroller->getPlugins();

        $this->_checkPluginInit($plugins);

        $optionsApp = $bootstrap->getOptions();

		$restConfigIsDeclared = $optionsApp['restConfig'];
		$cacheResponseIsDeclared = $restConfigIsDeclared && isset($optionsApp['restConfig']['cacheResponses']);
		if ($cacheResponseIsDeclared) {
			$this->_sendEtag = $optionsApp['restConfig']['cacheResponses'];
		}

        $fallbackLogger = $bootstrap->getResource('log');

        $noDeclaredLogger = !(isset($optionsApp['restLog']) || $fallbackLogger);
        if ($noDeclaredLogger) {
            $msg = '"restLog" no esta configurado en el application.ini';
            throw new \Exception($msg, 500);
        }

        if (isset($optionsApp['restLog'])) {
            $this->_logSystemConfig(
                $optionsApp['restLog']
            );
        } else {
            $this->_setFallbackLogger($fallbackLogger);
        }

        $this->status = new \Iron_Model_Rest_StatusResponse;
        $this->startErrorHandler();
        $this->_helper->viewRenderer->setNoRender(true);
    }

	protected function _sendEtag($currentEtag)
	{
		if ($this->_sendEtag) {
			$this->getResponse()->setHeader('Etag', $currentEtag);
		}
	}

    public function startErrorHandler()
    {

        $front = \Zend_Controller_Front::getInstance();
        $request = $front->getRequest();
        if ($request->getActionName() != 'rest-error' && $this->_logActive) {
            $this->_logRequest();
        }

        $errorHandler = $front->getPlugin(
            'Zend_Controller_Plugin_ErrorHandler'
        );
        $errorHandler
            ->setErrorHandlerAction('rest-error')
            ->setErrorHandlerController($request->getControllerName())
            ->setErrorHandlerModule($request->getModuleName());
    }

    public function location()
    {
        $location = $this->view->serverUrl(
            $this->view->url()
        );
        return $location;
    }

    public function restErrorAction()
    {
        $errors = $this->_getParam('error_handler');
        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = 'You have reached the error page';
            return;
        }
        $this->status->setApplicationError($errors->exception);
        $this->view->error = $errors->exception->getMessage();
    }

    protected function _setFallbackLogger(\Zend_Log $fallbackLogger)
    {
        $_logActive = true;
        $this->loggers["access"] = $fallbackLogger;
        $this->loggers["error"] = $fallbackLogger;
    }

    protected function _logSystemConfig($config)
    {
        if (!isset($config['log'])) {
            $this->_logActive = false;
            return;
        }
        $declarableEventLoggers = array("access", "error");
        foreach ($declarableEventLoggers as $eventLogger) {
            if (!isset($config['log'][$eventLogger])) {
                continue;
            }
            $this->_logActive = true;
            $logConfig = $config['log'][$eventLogger];
            $timesFormat = "Y-m-d H:s:i";
            $zendLogConfig = array(
                'timestampFormat' => $timesFormat
            );
            foreach ($logConfig as $key => $value) {
                $zendLogConfig[] = $value;
            }
            $this->loggers[$eventLogger] = Zend_Log::factory($zendLogConfig);
        }
    }

    protected function _checkPluginInit($plugins)
    {
        $init = false;
        foreach ($plugins as $plugin) {
            if (get_class($plugin) === 'Iron_Plugin_RestParamsParser') {
                $init = true;
            }
        }
        if (!$init) {
            throw new Exception(
                'No esta inicializado el plugin "Iron_Plugin_RestParamsParser"'
            );
        }
    }

    private function _logRequest()
    {
        if (!$this->loggers['access'] instanceof \Zend_Log) {
            return;
        }
        $module = $this->_request->getParam("module");
        $controller = $this->_request->getParam("controller");
        $action = $this->_request->getParam("action");
        $requestLog = $module . "/" . $controller . "::". $action;
        $params = $this->_request->getParams();
        foreach (array('module', 'controller', 'action') as $key) {
            unset($params[$key]);
        }
        $requestParamString = var_export($params, true);
        if (isset($_SERVER["REMOTE_ADDR"])) {
            $requestLog .= " from " . $_SERVER["REMOTE_ADDR"];
        }
        $this->loggers['access']->debug(
            "Requesting " . $requestLog
        );
        $resquestParams = str_replace("\n", "", $requestParamString);
        $this->loggers['access']->debug(
            "Request params: " . $resquestParams
        );
    }

    private function _logResponse()
    {
        $statusResume = $this->status->getException();
        if (array_key_exists('exception', $statusResume)) {
            if (!$this->loggers['error'] instanceof \Zend_Log) {
                return;
            }
            $msg = "Exception thrown: " . $statusResume['exception'];
            $this->loggers['error']->debug($msg);
            $msg = "Exception Ref: " . $statusResume['developerRef'];
            $this->loggers['error']->debug($msg);
        }
        if (!$this->loggers['access'] instanceof \Zend_Log) {
            return;
        }
        $msg = "Request finished with status code " . $this->status->getCode();
        $msg .= " [" . $this->status->getMessage() . "]";
        $this->loggers['access']->debug($msg);
    }

    /**
     * Context json to methods rest $this->_contexts
     * @see Zend_Controller_Action::preDispatch()
     */
    public function preDispatch()
    {

        $contextSwitch = $this->_helper->getHelper('contextSwitch');

        foreach ($this->_contexts as $context) {
            $contextSwitch->addActionContext($context, 'json');
        }

        $contextSwitch->initContext('json');

    }

    public function postDispatch()
    {

        $this->_responseContent();

        if (
            $this->_request->getUserParam("controller") !=
            $this->_request->getControllername() &&
            (get_class($this) != "Api_ErrorController")
        ) {
            return;
        }

        if ($this->_logActive) {
            $this->_logResponse();
        }

    }

    protected function _responseContent()
    {
        $this->getResponse()->setHttpResponseCode(
            $this->status->getCode()
        );

        $this->getResponse()->setHeader(
            'Content-type',
            'application/json; charset=UTF-8;'
        );

        $view = $this->view;
        $exceptionData = $this->status->getException();

        if (!empty($exceptionData)) {
            $exceptionEncode = json_encode($exceptionData);
            $this->getResponse()->setHeader('exception', $exceptionEncode);
        }

        $dataView = $this->_viewData;
        if (!empty($dataView)) {
            foreach ($dataView as $key => $val) {
                $view->$key = $val;
            }
        }

    }

    public function addViewData($data)
    {
        $this->_viewData = $data;
    }

    /**
     * The index action handles index/list requests; it should respond with a
     * list of the requested resources.
     */
    public function indexAction()
    {
        $this->_methodNotAllowed();
    }

    /**
     * The get action handles GET requests and receives an 'id' parameter; it
     * should respond with the server resource state of the resource identified
     * by the 'id' value.
     */
    public function getAction()
    {
        $this->_methodNotAllowed();
    }

    /**
     * The head action handles HEAD requests and receives an 'id' parameter; it
     * should respond with the server resource state of the resource identified
     * by the 'id' value.
     */
    public function headAction()
    {
        $this->_methodNotAllowed();
    }

    /**
     * The post action handles POST requests; it should accept and digest a
     * POSTed resource representation and persist the resource state.
     */
    public function postAction()
    {
        $this->_methodNotAllowed();
    }

    /**
     * The put action handles PUT requests and receives an 'id' parameter; it
     * should update the server resource state of the resource identified by
     * the 'id' value.
     */
    public function putAction()
    {
        $this->_methodNotAllowed();
    }

    /**
     * The delete action handles DELETE requests and receives an 'id'
     * parameter; it should update the server resource state of the resource
     * identified by the 'id' value.
     */
    public function deleteAction()
    {
        $this->_methodNotAllowed();
    }

    public function optionsAction()
    {
        $this->_methodNotAllowed();
    }

    private function _methodNotAllowed()
    {
        $this->status->setCode(405);
    }

    /**
     * Offset to pagination
     */
    protected function _prepareOffset($params = array())
    {
        if (isset($params["page"]) && $params["page"] > 0) {

            if (!$params["limit"]) {
                Throw new \Exception("Page parameter requires limit to be set");
            }

            return ($params["page"] - 1) * $params["limit"];
        }
        return 0;
    }

    /**
     * Order to list
     */
    protected function _prepareOrder($orderParam)
    {
        if ($orderParam === false || trim($orderParam) === '') {
            return 'id DESC';
        }

        return $orderParam;
    }

    /**
     * Where para busquedas, la variable $search espera un json_encode con los parámetros de búsqueda.
     */
    protected function _prepareWhere($search)
    {
        if ($search === false || trim($search) === '') {
            return NULL;
        }

        $search = json_decode($search);

        $itemsSearch = array();
        foreach ($search as $key => $val) {

            if (is_scalar($val)) {
                $itemsSearch[] = $this->_prepareScalarCondition($key, $val);
            } else if (is_object($val)){
                $itemsSearch[] = $this->_prepareAdvancedCondition($key, $val);
            }
        }

        if (empty($itemsSearch)) {
            return '';
        }
        return implode(" AND ", $itemsSearch);
    }

    protected function _prepareScalarCondition($key, $val) {

        $dbAdapter = Zend_Db_Table::getDefaultAdapter();
        if ($val != '') {

            $key = $dbAdapter->quoteIdentifier($key) . " = ?";
            return $dbAdapter->quoteInto($key, $val);
        }
        return array();
    }

    protected function _prepareAdvancedCondition($key, $val) {

        $dbAdapter = Zend_Db_Table::getDefaultAdapter();
        switch (strtolower(key($val))) {

            case 'between':
                $values = $this->_cleanArray($val);
                return $dbAdapter->quoteIdentifier($key) . ' between '. $values[0] . ' AND ' . $values[1];

            case 'notin':

                $values = $this->_cleanArray($val);
                return $dbAdapter->quoteIdentifier($key) . ' not in ('. implode(",", $values) . ') ';

            case 'in':

                $values = $this->_cleanArray($val);
                return $dbAdapter->quoteIdentifier($key) . ' in ('. implode(",", $values) . ') ';

            case 'like':

                $key = $dbAdapter->quoteIdentifier($key) . " like '%?%'";
                return $dbAdapter->quoteInto($key, $val);

            case 'notlike':

                $key = $dbAdapter->quoteIdentifier($key) . " not like '%?%'";
                return $dbAdapter->quoteInto($key, $val);
                break;
        }

        return '';
    }

    protected function _cleanArray($obj) {

        $dbAdapter = Zend_Db_Table::getDefaultAdapter();
        $values = array();
        foreach ($obj as $k => $v) {
            $values[] = $dbAdapter->quote($v);
        }
        return $values;
    }
}
