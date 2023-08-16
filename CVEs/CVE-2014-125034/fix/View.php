<?php

class View {
    
    public $title, $data = array();

    public function __construct() {

        if(!isset($this->title)) {
            $title = explode('/', $_SERVER['REQUEST_URI']);
            $title = $title[2];

            if( empty($title) ) {
                $title = DEFAULT_CONTROLLER;
            }
            $this->title = ucfirst( $title );
        }
    }
    
    /**
     * Desc: include template file
     * @param string $template
     * @param [, true if is no requirement for templates header and footer ]
     * @return void
     */
    public function render($template, $noInclude = false) {
        if( isset($this->data) && !empty($this->data) ) {
            extract($this->data);
            
            if($noInclude) {
                require_once VIEWS.$template.".php";
            } else {
                require_once HEADER;
                require_once VIEWS.$template.".php";
                require_once FOOTER;
            }
        }
    }

    /**
     * Display variable if set
     * @param $var
     * @return string
     */
    public function var_check(&$var) {
        return isset($var) ? htmlspecialchars($var,ENT_QUOTES,'UTF-8') : '';
        //I put htmlspecialchars into the var_check to avoid the XSS injection.
    }
    
}
