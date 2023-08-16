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
 * Login controller.
 *
 * @package Cinnebar
 * @subpackage Controller
 * @version $Id$
 */
class Controller_Login extends Controller
{
    /**
     * May hold a message (textile) to the user that want to re-enter a password.
     */
    public $message;

    /**
     * Renders the login page.
     *
     * Renders the login page and handles a login attempt on POST if no valid
     * session already exists. If credentials given are correct a session is started
     * and the client is redirected to the originally requested URL or the /cms page if
     * no URL was provided.
     */
    public function index()
    {
        session_start();
        if (! isset($_SESSION['login_id'])) {
            $_SESSION['login_id'] = 0;
        }
        $login = R::load('login', $_SESSION['login_id']);
        if (Flight::request()->method == 'POST') {
            if (! Model::validateCSRFToken(Flight::request()->data->token)) {
                $this->redirect("/logout");
            }
            try {
                $login = R::graph(Flight::request()->data->dialog, true);
                if ($login->trial()) {
                    //you must trial before store because of pw reset in update
                    $_SESSION['user']['id'] = $login->user->getId();
                    $_SESSION['backend']['language'] = Flight::get('language');
                    $login->user->sid = session_id();
                    R::store($login);
                    $this->redirect(Flight::request()->data->goto, $raw = true);
                }
                $this->message = I18n::__('login_failed');
                R::store($login);
            } catch (Exception $e) {
                error_log($e);
                //uups, login could not be saved
            }
        }
        // either no yet submitted or the credentials given failed
        if (Flight::request()->query->goto == '' || Flight::request()->query->goto == '/login') {
            $goto = '/cms';
        } else {
            $goto = Flight::request()->query->goto;
        }
        Flight::render('account/login', array(
            'goto' => htmlspecialchars($goto),
            'record' => $login,
            'message' => $this->message
        ), 'content');
        Flight::render('html5', array(
            'title' => I18n::__('login_head_title'),
            'language' => Flight::get('language'),
            'stylesheets' => array('custom', 'default')
        ));
    }
}
