<?php
    /**
     * Template preview controller
     * @author Stefan Seehafer <sea75300@yahoo.de>
     * @copyright (c) 2011-2017, Stefan Seehafer
     * @license http://www.gnu.org/licenses/gpl.txt GPLv3
     */
    namespace fpcm\controller\action\system;
    
    class templatepreview extends \fpcm\controller\abstracts\controller {
        
        use \fpcm\controller\traits\system\templatepreview;
        
        /**
         *
         * @var \fpcm\model\view\pub
         */
        protected $view;

        /**
         *
         * @var \fpcm\model\pubtemplates\template
         */
        protected $template;
        
        /**
         *
         * @var int 
         */
        protected $tid;

        /**
         * Konstruktor
         */
        public function __construct() {
            parent::__construct();
            
            $this->checkPermission = array('system' => 'templates');

        }
        
        /**
         * Request-Handler
         * @return boolean
         */
        public function request() {

            $this->tid = $this->getRequestVar('tid', array(9));

            if (!$this->tid) {
                return false;
            }

            return true;
        }
        
        /**
         * Controller-Processing
         * @return boolean
         */
        public function process() {
            if (!parent::process()) return false;

            $this->template = $this->getTemplateById($this->tid);

            switch ($this->tid) {
                case 1 :
                    $this->getArticlesPreview();
                    break;
                case 2 :
                    $this->getArticlePreview();
                    break;
                case 3 :
                    $this->getCommentPreview();
                    break;
                case 4 :
                    $this->getCommentFormPreview();
                    break;
                case 5 :
                    $this->getLatestNewsPreview();
                    break;
                default :
                    $this->view = new \fpcm\model\view\error();
                    $this->view->setMessage('Invalid template data');
                    $this->view->render();
                    return;
            }

            $this->view->assign('showToolbars', false);
            $this->view->assign('hideDebug', true);
            $this->view->assign('hideDebug', true);
            $this->view->assign('systemMode', 1);
            $this->view->setShowHeader(true);
            $this->view->setShowFooter(true);
            $this->view->setForceCss(true);
            $this->view->prependjQuery();
            $this->view->render();

        }        
        
        private function getArticlesPreview() {

            $this->view         = new \fpcm\model\view\pub('showall', 'public');

            $parsed = [];
            
            $categoryTexts     = array('<span class="fpcm-pub-category-text">Category 1</span>', '<span class="fpcm-pub-category-text">Category 2</span>');
            $shareButtonParser = new \fpcm\model\pubtemplates\sharebuttons($this->config->system_url, 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr!');

            $replacements = array(
                '{{headline}}'                      => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr!',
                '{{text}}'                          => 'Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.',
                '{{author}}'                        => $this->session->getCurrentUser()->getUsername(),
                '{{authorEmail}}'                   => '<a href="mailto:'.$this->session->getCurrentUser()->getEmail().'">'.$this->session->getCurrentUser()->getDisplayname().'</a>',
                '{{authorAvatar}}'                  => \fpcm\model\users\author::getAuthorImageDataOrPath($this->session->getCurrentUser(), 0),
                '{{authorInfoText}}'                => $this->session->getCurrentUser()->getUsrinfo(),
                '{{date}}'                          => date($this->config->system_dtmask, time()),
                '{{changeDate}}'                    => date($this->config->system_dtmask, time()),
                '{{changeUser}}'                    => $this->session->getCurrentUser()->getDisplayname(),
                '{{statusPinned}}'                  => '',
                '{{shareButtons}}'                  => $shareButtonParser->parse(),
                '{{categoryIcons}}'                 => '',
                '{{categoryTexts}}'                 => implode(PHP_EOL, $categoryTexts),
                '{{commentCount}}'                  => 0,
                '{{permaLink}}:{{/permaLink}}'      => $this->config->system_url,
                '{{commentLink}}:{{/commentLink}}'  => $this->config->system_url.'#comments',
                '<readmore>:</readmore>'            => md5(time()),
                '{{articleImage}}'                  => '',
                '{{sources}}'                       => $this->config->system_url
            );
            $this->template->setReplacementTags($replacements);
            $parsed[] = $this->template->parse();
            
            $categoryTexts     = array('<span class="fpcm-pub-category-text">Category 3</span>', '<span class="fpcm-pub-category-text">Category 4</span>');
            $shareButtonParser = new \fpcm\model\pubtemplates\sharebuttons($this->config->system_url, 'Ut wisi enim ad minim veniam?');
            $replacements = array(
                '{{headline}}'                      => 'Ut wisi enim ad minim veniam?',
                '{{text}}'                          => 'Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. ',
                '{{author}}'                        => $this->session->getCurrentUser()->getUsername(),
                '{{authorEmail}}'                   => '<a href="mailto:'.$this->session->getCurrentUser()->getEmail().'">'.$this->session->getCurrentUser()->getDisplayname().'</a>',
                '{{authorAvatar}}'                  => '',
                '{{authorInfoText}}'                => '',
                '{{date}}'                          => date($this->config->system_dtmask, time() - 3600),
                '{{changeDate}}'                    => date($this->config->system_dtmask, time() - 3600),
                '{{changeUser}}'                    => $this->session->getCurrentUser()->getDisplayname(),
                '{{statusPinned}}'                  => '',
                '{{shareButtons}}'                  => $shareButtonParser->parse(),
                '{{categoryIcons}}'                 => '',
                '{{categoryTexts}}'                 => implode(PHP_EOL, $categoryTexts),
                '{{commentCount}}'                  => 0,
                '{{permaLink}}:{{/permaLink}}'      => $this->config->system_url,
                '{{commentLink}}:{{/commentLink}}'  => $this->config->system_url.'#comments',
                '<readmore>:</readmore>'            => md5(time()),
                '{{articleImage}}'                  => '',
                '{{sources}}'                       => ''
            );
            $this->template->setReplacementTags($replacements);
            $parsed[] = $this->template->parse();

            $this->view->assign('content', str_replace(
                ['<script>', '</script>'],
                ['&lt;script_diabled_in_preview&gt;', '&lt;/script_diabled_in_preview&gt;'],
                implode(PHP_EOL, $parsed))
            );
            $this->view->assign('commentform', '');

        }

        private function getArticlePreview() {

            $this->view         = new \fpcm\model\view\pub('showsingle', 'public');
            
            $categoryTexts     = array('<span class="fpcm-pub-category-text">Category 1</span>', '<span class="fpcm-pub-category-text">Category 2</span>');
            $shareButtonParser = new \fpcm\model\pubtemplates\sharebuttons($this->config->system_url, 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr!');

            $replacements = array(
                '{{headline}}'                      => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr!',
                '{{text}}'                          => 'Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.',
                '{{author}}'                        => $this->session->getCurrentUser()->getDisplayname(),
                '{{authorEmail}}'                   => '<a href="mailto:'.$this->session->getCurrentUser()->getEmail().'">'.$this->session->getCurrentUser()->getDisplayname().'</a>',
                '{{authorAvatar}}'                  => \fpcm\model\users\author::getAuthorImageDataOrPath($this->session->getCurrentUser(), 0),
                '{{authorInfoText}}'                => $this->session->getCurrentUser()->getUsrinfo(),
                '{{date}}'                          => date($this->config->system_dtmask, time()),
                '{{changeDate}}'                    => date($this->config->system_dtmask, time()),
                '{{changeUser}}'                    => $this->session->getCurrentUser()->getDisplayname(),
                '{{statusPinned}}'                  => '',
                '{{shareButtons}}'                  => $shareButtonParser->parse(),
                '{{categoryIcons}}'                 => '',
                '{{categoryTexts}}'                 => implode(PHP_EOL, $categoryTexts),
                '{{commentCount}}'                  => 0,
                '{{permaLink}}:{{/permaLink}}'      => $this->config->system_url,
                '{{commentLink}}:{{/commentLink}}'  => $this->config->system_url.'#comments',
                '<readmore>:</readmore>'            => md5(time()),
                '{{articleImage}}'                  => '',
                '{{sources}}'                       => $this->config->system_url
            );

            $this->template->setReplacementTags($replacements);

            $this->view->assign('article', $this->template->parse());
            $this->view->assign('comments', '');
            $this->view->assign('commentform', '');
        }
        
        private function getCommentPreview() {
            $this->view = new \fpcm\model\view\pub('showsingle', 'public');

            $this->view->assign('article', '');
            
            $replacements = array(
                '{{author}}'                => $this->session->getCurrentUser()->getDisplayname(),
                '{{email}}'                 => $this->session->getCurrentUser()->getEmail(),
                '{{website}}'               => $this->config->system_url,
                '{{text}}'                  => 'Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis. ',
                '{{date}}'                  => date($this->config->system_dtmask, time()-600),
                '{{number}}'                => 1,
                '{{id}}'                    => 1,
                '{{mentionid}}'             => 'id="c1"',
                '{{mention}}:{{/mention}}'  => 1
            );

            $this->template->setReplacementTags($replacements);
            $this->view->assign('comments', $this->template->parse());
            $this->view->assign('commentform', '');
        }
        
        private function getCommentFormPreview() {
            
            $this->view = new \fpcm\model\view\pub('showsingle', 'public');
            $this->view->assign('article', '');
            $this->view->assign('comments', '');
            
            $captcha = $this->events->runEvent('publicReplaceSpamCaptcha');
            
            if (!is_a($captcha, '\fpcm\model\abstracts\spamCaptcha')) {
                $captcha = new \fpcm\model\captchas\fpcmDefault();
            }

            $smileyList = new \fpcm\model\files\smileylist();
            $smileys    = $smileyList->getDatabaseList();            

            $smileyHtml = [];
            $smileyHtml[] = "<ul class=\"fpcm-pub-smileys\">";
            foreach ($smileys as $key => $smiley)  {
                $smileyHtml[] = '<li><a class="fpcm-pub-commentsmiley" smileycode="'.$smiley->getSmileyCode().'" href="#"><img src="'.$smiley->getSmileyUrl().'" alt="'.$smiley->getSmileyCode().'()" '.$smiley->getWhstring().'></a></li>';
            }
            $smileyHtml[] = '</ul>';
            
            $replacementTags = array(
                '{{formHeadline}}'                   => $this->lang->translate('COMMENTS_PUBLIC_FORMHEADLINE'),
                '{{submitUrl}}'                      => $this->config->system_url,
                '{{nameDescription}}'                => $this->lang->translate('COMMMENT_AUTHOR'),
                '{{nameField}}'                      => '<input type="text" class="fpcm-pub-textinput" name="newcomment[name]" value="">',
                '{{emailDescription}}'               => $this->lang->translate('GLOBAL_EMAIL'),
                '{{emailField}}'                     => '<input type="text" class="fpcm-pub-textinput" name="newcomment[email]" value="">',
                '{{websiteDescription}}'             => $this->lang->translate('COMMMENT_WEBSITE'),
                '{{websiteField}}'                   => '<input type="text" class="fpcm-pub-textinput" name="newcomment[website]" value="">',
                '{{textfield}}'                      => '<textarea class="fpcm-pub-textarea" id="newcommenttext" name="newcomment[text]"></textarea>',
                '{{smileysDescription}}'             => $this->lang->translate('HL_OPTIONS_SMILEYS'),
                '{{smileys}}'                        => implode(PHP_EOL, $smileyHtml),
                '{{tags}}'                           => htmlentities(\fpcm\model\comments\comment::COMMENT_TEXT_HTMLTAGS_FORM),
                '{{spampluginQuestion}}'             => $captcha->createPluginText(),
                '{{spampluginField}}'                => $captcha->createPluginInput(),
                '{{privateCheckbox}}'                => '<input type="checkbox" class="fpcm-pub-checkboxinput" name="newcomment[private]" value="1">',
                '{{submitButton}}'                   => '<button type="submit" name="btnSendComment">'.$this->lang->translate('GLOBAL_SUBMIT').'</button>',
                '{{resetButton}}'                    => '<button type="reset">'.$this->lang->translate('GLOBAL_RESET').'</button>'
            );            

            $this->template->setReplacementTags($replacementTags);            
            $this->view->assign('commentform', $this->template->parse());
        }
        
        private function getLatestNewsPreview() {

            $this->view = new \fpcm\model\view\pub('showlatest', 'public');
            
            $replacements = array(
                '{{headline}}'                      => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr!',
                '{{author}}'                        => $this->session->getCurrentUser()->getDisplayname(),
                '{{date}}'                          => date($this->config->system_dtmask, time()),
                '{{permaLink}}:{{/permaLink}}'      => $this->config->system_url,
                '{{commentLink}}:{{/commentLink}}'  => $this->config->system_url.'#comments'
            );

            $this->template->setReplacementTags($replacements);
            $parsed[] = $this->template->parse();
            
            $replacements = array(
                '{{headline}}'                      => 'Ut wisi enim ad minim veniam?',
                '{{author}}'                        => $this->session->getCurrentUser()->getDisplayname(),
                '{{date}}'                          => date($this->config->system_dtmask, time() - 3600),
                '{{permaLink}}:{{/permaLink}}'      => $this->config->system_url,
                '{{commentLink}}:{{/commentLink}}'  => $this->config->system_url.'#comments'
            );

            $this->template->setReplacementTags($replacements);
            $parsed[] = $this->template->parse();

            $this->view->assign('content', implode(PHP_EOL, $parsed));
        }
    }
?>