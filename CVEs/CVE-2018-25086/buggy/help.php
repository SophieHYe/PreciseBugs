<?php
    /**
     * Help view controller
     * @author Stefan Seehafer <sea75300@yahoo.de>
     * @copyright (c) 2011-2017, Stefan Seehafer
     * @license http://www.gnu.org/licenses/gpl.txt GPLv3
     */
    namespace fpcm\controller\action\system;
    
    class help extends \fpcm\controller\abstracts\controller {
        
        /**
         * Controller-View
         * @var \fpcm\model\view\acp
         */
        protected $view;
        
        /**
         * ID des automatisch offenen Kapitels
         * @var int
         */
        protected $chapterHeadline = '';

        /**
         * Konstruktor
         */
        public function __construct() {
            parent::__construct();

            $this->checkPermission = [];
            $this->view            = new \fpcm\model\view\acp('help', 'system');
            $this->cache           = new \fpcm\classes\cache('helpcache_'.$this->config->system_lang);
        }

        public function request() {
            $this->chapterHeadline = $this->getRequestVar('ref');
            return parent::request();
        }
        
        /**
         * Controller-Processing
         * @return boolean
         */
        public function process() {
            if (!parent::process()) return false;

            $contents = $this->cache->read();
            if ($this->cache->isExpired() || !is_array($contents)) {
                $xml = simplexml_load_string($this->lang->getHelp());
                foreach ($xml->chapter as $chapter) {
                    $headline = trim($chapter->headline);
                    $contents[$headline] = trim($chapter->text);
                }                
            }            
            
            $contents = $this->events->runEvent('extendHelp', $contents);
            $this->view->assign('chapters', $contents);

            $pos = $this->chapterHeadline ? (int) array_search(strtoupper(base64_decode($this->chapterHeadline)), array_keys($contents)) : 0;
            $this->view->addJsVars(['fpcmDefaultCapter' => $pos]);
            $this->view->setViewJsFiles(['help.js']);
            
            $this->view->render();
        }
        
    }
?>