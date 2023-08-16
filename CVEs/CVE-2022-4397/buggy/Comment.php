<?php

class Application_Form_Comment extends Zend_Form
{

    public $elementDecorators = array(
        'ViewHelper',
        'Errors',
        array(array('data' => 'HtmlTag'), array('tag' => 'div', 'class' => 'element')),
        array('label', array()),
        array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'row')),
    );

    public function init()
    {
        $this->setAction('/index/addcomment');
        $this->setMethod('post');

        $this->setAttrib('id', 'comment_form');

        $name = new Zend_Form_Element_Text('name');
        $name->setLabel('Имя:')
            ->setRequired(true)
            ->setAttribs(array(
                'placeholder' => 'nickname',
                'required'    => 'required',
            ))
            ->addFilters(array('StripTags', 'StringTrim'))
            ->addValidator('NotEmpty');

        $email = new Zend_Form_Element_Text('mail');
        $email->helper = 'formEmail';
        $email->setLabel('E-mail:')
            ->setAttribs(array(
                'placeholder' => 'mail@example.org',
            ))
            ->addFilters(array('StripTags', 'StringTrim'))
            ->addValidator('EmailAddress');

        $website = new Zend_Form_Element_Text('website');
        $website->helper = 'formUrl';
        $website->setLabel('Website:')
                ->setRequired(false)
                ->setAttrib('placeholder', 'http://example.org')
                ->addFilters(array('StripTags', 'StringTrim'))
                ->addValidator(new Application_Form_Validate_Uri());

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->addElement($name);
            $this->addElement($email);
            $this->addElement($website);
        }

        $textarea = new Zend_Form_Element_Textarea('comment_text');
        $textarea->setLabel('Текст комментария:')
                ->setRequired(true)
                ->setAttribs(array(
                    'cols'     => 66,
                    'rows'     => 10,
                    'required' => 'required',
                ))
                ->addFilter('StringTrim');
        $tagFilter = new Zend_Filter_StripTags(array(
            'allowTags'    => array('a', 's', 'b', 'i', 'em', 'strong', 'img'),
            'allowAttribs' => array('src', 'href', 'class', 'id'),
        ));
        $textarea->addFilter($tagFilter);
        $this->addElement($textarea);

        $topicId = new Zend_Form_Element_Hidden('topicId');
        $topicId->addValidator('Int');
        $this->addElement($topicId);

        $parentId = new Zend_Form_Element_Hidden('parentId');
        $parentId->setValue(0);
        $parentId->addValidator('Int');
        $this->addElement($parentId);

        $cookie = new Zend_Form_Element_Hidden('cookie');
        $cookie->setValue(1);
        $cookie->addValidator('Int');
        $this->addElement($cookie);

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->checkCookie();
        }

        $this->setElementDecorators($this->elementDecorators);
    }

    protected function checkCookie()
    {
        $hash = false;

        if (isset($_COOKIE['commentator_hash'])) {
            $hash = $_COOKIE['commentator_hash'];
        }

        if ($hash) {
            $commentatorsTable = new Application_Model_Commentators();

            $commentator = $commentatorsTable->getByHash($hash);
            if ($commentator) {
                $this->name->setValue($commentator->name);
                if ($commentator->mail) {
                    $this->mail->setValue($commentator->mail);
                }
                if ($commentator->website) {
                    $this->website->setValue($commentator->website);
                }
            }
        }
    }
}

