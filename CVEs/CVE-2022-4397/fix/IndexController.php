<?php

class IndexController extends Zend_Controller_Action
{

    protected $_config = null;

    public function init()
    {
        $this->_config = $this->getInvokeArg('bootstrap')
            ->getOptions();
    }

    public function indexAction()
    {
        $page = $this->_getParam('page');

        $postsTable = new Application_Model_Posts();

        $paginator = $postsTable->getAllPosts();
        $paginator->setItemCountPerPage($this->_config['paginator']['topic']);
        $paginator->SetCurrentPageNumber($page);

        if (count($paginator) < $page || $page < 1) {
            $this->_redirect404();
        }

        $sysParams = new Application_Model_SysParameters();
        $this->view->metaDescription = $sysParams->getOption('meta_description');

        if ($page > 1) {
            $this->view->browsertitle = 'Страница ' . $page;
            $this->view->metaDescription .= ' Страница ' . $page;
        }

        $this->view->posts = $paginator;
    }

    public function categoryAction()
    {
        $page = $this->_getParam('page');
        $url  = $this->_getParam('url');

        $this->view->headMeta()->appendName('robots', 'noindex, follow');

        $postsTable = new Application_Model_Posts();

        $paginator = $postsTable->getPostsByCategory($url);
        $paginator->setItemCountPerPage($this->_config['paginator']['topic']);
        $paginator->SetCurrentPageNumber($page);

        if (count($paginator) < $page || $page < 1) {
            $this->_redirect404();
        }

        $category = new Application_Model_Category();
        $currentCategory = $category->getCategoryByUrl($url);

        $this->view->browsertitle    = 'Категория "' . $currentCategory->name . '"';
        $this->view->metaDescription = 'Записи из категории "' . $currentCategory->name . '"';

        if ($page > 1) {
            $this->view->browsertitle .= '. Страница ' . $page;
            $this->view->metaDescription .= '. Страница ' . $page;
        }

        $this->view->posts = $paginator;

        $this->render('index');
    }

    public function tagAction()
    {
        $page = $this->_getParam('page');
        $url  = $this->_getParam('url');

        $this->view->headMeta()->appendName('robots', 'noindex, follow');

        $postsTable = new Application_Model_Posts();

        $paginator = $postsTable->getPostsByTag($url);
        $paginator->setItemCountPerPage($this->_config['paginator']['topic']);
        $paginator->SetCurrentPageNumber($page);

        if (count($paginator) < $page || $page < 1) {
            $this->_redirect404();
        }

        $tags = new Application_Model_Tags();
        $currentTag = $tags->getTagByUrl($url);

        $this->view->browsertitle    = 'Тег "' . $currentTag->name . '"';
        $this->view->metaDescription = 'Записи по тегу "' . $currentTag->name . '"';

        if ($page > 1) {
            $this->view->browsertitle .= '. Страница ' . $page;
            $this->view->metaDescription .= '. Страница ' . $page;
        }

        $this->view->posts = $paginator;

        $this->render('index');
    }

    public function topicAction()
    {
        $id   = $this->_getParam('id');
        $url  = $this->_getParam('url');
        $hash = $this->_getParam('hash');

        $postsTable = new Application_Model_Posts();

        if (isset($hash)) {
            $path = str_replace('qwerty', '', $this->view->url(array('url' => 'qwerty'), 'topic_url'));
            setcookie('commentator_hash', $hash, time() + 31104000, $path);
        }

        if (isset($id)) {
            $post = $postsTable->getPostById($id);
            if ($post) {
                $postUrl = $this->view->url(array('url' => $post->url), 'topic_url', true);
                $this->redirect($postUrl, array('code' => 301));
            } else {
                $this->_redirect404();
            }
        }

        $post = null;
        if (isset($url)) {
            $post = $postsTable->getPostByUrl($url);
            if (!$post) {
                $this->_redirect404();
            } else {
                if (preg_match('/\/$/', $this->_request->getRequestUri())) {
                    $postUrl = $this->view->url(array('url' => $url), 'topic_url', true);
                    $this->redirect($postUrl, array('code' => 301));
                }
            }
        }

        $commentsTable = new Application_Model_Comments();
        $this->view->commentsCacheKey = $commentsTable->getPostCommentKey(
            $post->id,
            Zend_Auth::getInstance()->hasIdentity()
        );

        $this->view->post = $post;

        if ($post->description) {
            $this->view->metaDescription = $post->description;
        }

        $this->view->browsertitle = $post->title;

        $form = new Application_Form_Comment();
        $form->topicId->setValue($post->id);
        $form->getElement('csrfToken')->setValue($this->generateCommentToken());

        $this->view->form = $form;

        $tracking = new Application_Model_Tracking();
        $tracking->checkTrackingEvent($post->id, $this->_request->getClientIp());
    }

    public function feedAction()
    {
        $feedType = $this->_getParam('feed');

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $nameCacheFeed = 'feed_' . $feedType;
        $cache = Zend_Registry::get('cache');

        $feedArray = $cache->load($nameCacheFeed);
        if (!$feedArray) {
            $topics = new Application_Model_Posts();
            $feedArray = $topics->getFeedData($feedType);
            $cache->save($feedArray, $nameCacheFeed);
        }

        $tracking = new Application_Model_Tracking();
        $tracking->checkTrackingEvent(null, $this->_request->getClientIp());

        $feed = Zend_Feed::importArray($feedArray, $feedType);
        $feed->send();
    }

    public function sitemapAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->isCDN()) {
            $sitemap = <<<RAW
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
</urlset>
RAW;
        } else {
            $sitemap = Application_Model_Sitemap::generateSitemap();
        }

        $this->getResponse()->setHeader('Content-Type', 'application/xml');
        $this->getResponse()->setHeader('Cache-Control', 'public, max-age=7200');
        $this->getResponse()->appendBody($sitemap);
    }

    public function addcommentAction()
    {
        $this->redirect('/');
    }

    public function ajaxaddcommentAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $topicId = (int) $this->_getParam('topicId');

        $topicTable = new Application_Model_Posts();

        $topic = $topicTable->getPostById($topicId);
        if ($topic) {
            $url = $topic->url;
        } else {
            $url = false;
        }

        $result = array('valid' => false);

        if ($this->getRequest()->isPost() && $url) {
            $form = new Application_Form_Comment();

            if ($form->isValid($this->getRequest()->getPost())) {
                $formData = $form->getValues();

                if ($this->validCommentToken($formData['csrfToken'])) {
                    $this->saveComment($topicId, $url, $formData);
                }

                $result['valid'] = true;
            } else {
                $formView = new Zend_View;
                $formView->setScriptPath(APPLICATION_PATH . "/views/scripts/index");
                $formView->setHelperPath(APPLICATION_PATH . "/views/helpers");

                $formView->form = $form;

                $result['form_html'] = $formView->render('formcomment.phtml');
            }
        }

        $this->_helper->json($result);
    }

    public function robotsAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $robots = <<<RAW
User-agent: *

Host: morontt.info
Sitemap: https://morontt.info/sitemap.xml
RAW;
        if ($this->isCDN()) {
            $robots = <<<RAW
User-agent: *

Disallow: /
RAW;
        }

        $this->getResponse()->setHeader('Content-Type', 'text/plain');
        $this->getResponse()->setHeader('Cache-Control', 'public, max-age=2592000');
        $this->getResponse()->appendBody($robots);
    }

    protected function _redirect404()
    {
        throw new Zend_Controller_Action_Exception('Page not found', 404);
    }

    protected function saveComment($topicId, $url, $formData)
    {
        $topic         = new Application_Model_Posts();
        $commentators  = new Application_Model_Commentators();
        $commentsTable = new Application_Model_Comments();

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $commentatorId = $commentators->getCommentatorId($formData['name'], $formData['mail'], $formData['website']);
            if ($formData['cookie']) {
                $hash = md5(Application_Model_Commentators::HASH_SALT . $commentatorId);
                $path = str_replace('qwerty', '', $this->view->url(array('url' => 'qwerty'), 'topic_url'));
                setcookie('commentator_hash', $hash, time() + 31104000, $path);
            }
        } else {
            $commentatorId = 0;
        }
        $commentId = $commentsTable->saveComment($formData, $commentatorId, $this->_request->getClientIp());
        $topic->updateCommentsCount($topicId);

        $this->sendCommentMails($url, $formData);
        $this->sendTelegramMessage($url, $commentId, $formData);

        $cacheOutput = Zend_Registry::get('cacheOutput');
        $cacheOutput->remove('commentators_stats');
    }

    protected function sendCommentMails($url, $formData)
    {
        $mailer = new Application_Model_Mailswift();

        $notice = true;

        $options = Zend_Registry::get('options');

        if (Zend_Auth::getInstance()->hasIdentity()) {
            if (Zend_Auth::getInstance()->getIdentity()->id == '1') {
                $notice = false;
            }
        }

        if ($notice) {
            $mailView = new Zend_View;
            $mailView->setScriptPath(APPLICATION_PATH . "/views/mails");
            $mailView->setHelperPath(APPLICATION_PATH . "/views/helpers");

            $mailView->topicUrl    = $url;
            $mailView->formData    = $formData;
            $mailView->headerTitle = $options['blog']['title'];

            $mailBody = $mailView->render('commenttopic.phtml');

            $mailer->sendEmailToSpool($options['sys_parameters']['mail'], $options['sys_parameters']['mailto'],
                'Новый комментарий', $mailBody
            );
            $mailer->sendEmailToSpool($options['sys_parameters']['copymail'], $options['sys_parameters']['mailto'],
                'Новый комментарий', $mailBody
            );
        }

        if ($formData['parentId']) {

            $commentsTable = new Application_Model_Comments();
            $commentInfo = $commentsTable->getCommentInfo($formData['parentId']);

            if ($commentInfo) {
                $mailTwo = new Zend_View;
                $mailTwo->setScriptPath(APPLICATION_PATH . "/views/mails");
                $mailTwo->setHelperPath(APPLICATION_PATH . "/views/helpers");

                $mailTwo->topicUrl    = $url;
                $mailTwo->formData    = $formData;
                $mailTwo->topicTitle  = $commentInfo['title'];
                $mailTwo->hash        = $commentInfo['hash'];
                $mailTwo->headerTitle = $options['blog']['title'];

                $mailBodyTwo = $mailTwo->render('responsecomment.phtml');

                $mailer->sendEmailToSpool($commentInfo['mail'], $commentInfo['name'], 'Ответ на комментарий', $mailBodyTwo);
            }
        }
    }

    /**
     * @param string $slug
     * @param int $commentId
     * @param array $formData
     *
     * @throws Zend_Exception
     */
    protected function sendTelegramMessage(string $slug, int $commentId, array $formData)
    {
        $url = BASE_URL . $this->view->url(array('url' => $slug), 'topic_url');

        $text = "Кто-то оставил [комментарий]({$url})\n\n*ID*: {$commentId}\n";
        if (!empty($formData['name'])) {
            $text .= "*Name*: {$formData['name']}\n";
        }
        if (!empty($formData['mail'])) {
            $text .= "*Email*: {$formData['mail']}\n";
        }
        if (!empty($formData['website'])) {
            $text .= "*Website*: {$formData['website']}\n";
        }

        $text .= "\n" . strip_tags($formData['comment_text']);

        $options = Zend_Registry::get('options');
        $message = [
            'chat_id' => $options['telegram']['admin_id'],
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        exec(sprintf(
            'nohup php %s \'%s\' > /dev/null 2>&1 &',
            realpath(__DIR__ . '/../../bin/telegramMessage.php'),
            json_encode($message)
        ));

        exec(sprintf(
            'nohup php %s > /dev/null 2>&1 &',
            realpath(__DIR__ . '/../../bin/commentGeoLocation.php')
        ));
    }

    /**
     * @return bool
     */
    protected function isCDN()
    {
        return !empty($_SERVER['HTTP_VIA'])
            && (stripos($_SERVER['HTTP_VIA'], 'BunnyCDN') !== false
                || strpos($_SERVER['HTTP_VIA'], 'cdn77') !== false);
    }

    private function generateCommentToken($time = null): string
    {
        $time = $time ?? time();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return base64_encode($time . ':' . hash('md5', 'MD5_' . $userAgent . $time, true));
    }

    private function validCommentToken($token): bool
    {
        $raw = base64_decode($token, true);
        if ($raw === false) {
            return false;
        }

        $position = strpos($raw, ':');
        if ($position === false) {
            return false;
        }

        $time = substr($raw, 0, $position);

        return hash_equals($this->generateCommentToken($time), $token);
    }
}
