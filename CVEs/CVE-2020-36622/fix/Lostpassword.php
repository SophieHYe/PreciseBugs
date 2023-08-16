<?php
/**
 * Cinnebar.
 *
 * @package Cinnebar
 * @subpackage Controller
 * @author $Author$
 * @version $Id$
 */

/**
 * Lostpassword controller.
 *
 * @package Cinnebar
 * @subpackage Controller
 * @version $Id$
 */
class Controller_Lostpassword extends Controller
{
    /**
     * Holds the template to render.
     *
     * @var string
     */
    public $template = 'account/lostpassword';

    /**
     * Holds the username entered by the password seeking party.
     */
    public $uname = '';

    /**
     * May hold a message (textile) to the user that want to re-enter a password.
     */
    public $message;

    /**
     * Displays a page to request a new password.
     *
     * A GET request will simply display the page and a POST request will change
     * the user account.
     */
    public function index()
    {
        if (Flight::request()->method == 'POST') {
            if (! Model::validateCSRFToken(Flight::request()->data->token)) {
                $this->redirect("/logout");
            }
            $this->uname = Flight::request()->data->dialog['uname'];
            if (! $user = R::findOne('user', ' email = ? LIMIT 1 ', array($this->uname))) {
                $this->message = I18n::__('lostpassword_user_unknown');
            } elseif (! $user->requestPassword()) {
                $this->message = I18n::__('lostpassword_email_failed');
            } else {
                $this->redirect('login');
            }
        }

        $this->render();
    }

    /**
     * Renders the lostpassword page.
     */
    protected function render()
    {
        Flight::render($this->template, array(
            'uname' => $this->uname,
            'message' => $this->message
        ), 'content');
        Flight::render('html5', array(
            'title' => I18n::__("lostpassword_head_title"),
            'language' => Flight::get('language'),
            'stylesheets' => array('custom', 'default')
        ));
    }
}
