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
 * Account controller.
 *
 * @package Cinnebar
 * @subpackage Controller
 * @version $Id$
 */
class Controller_Account extends Controller
{
    /**
     * Holds the template to render.
     *
     * @var string
     */
    public $template;

    /**
     * Displays the currently logged user account.
     *
     * A GET request will simply display the page and a POST request will change
     * the user account.
     */
    public function index()
    {
        session_start();
        Auth::check();
        $this->template = 'account/index';

        if (Flight::request()->method == 'POST') {
            if (! Model::validateCSRFToken(Flight::request()->data->token)) {
                $this->redirect("/logout");
            }
            Flight::get('user')->import(Flight::request()->data->dialog);
            try {
                R::store(Flight::get('user'));
                Flight::get('user')->notify(I18n::__('account_edit_success'), 'success');
                $this->redirect('/account/');
            } catch (Exception $e) {
                Flight::get('user')->notify(I18n::__('account_edit_failure'), 'error');
            }
        }

        $this->render();
    }

    /**
     * Displays a page to change the password.
     *
     * A GET request will simply display the page and a POST request will try to
     * change the password.
     */
    public function changepassword()
    {
        session_start();
        Auth::check();
        $this->template = 'account/changepassword';

        if (Flight::request()->method == 'POST') {
            if (! Model::validateCSRFToken(Flight::request()->data->token)) {
                $this->redirect("/logout");
            }
            if (Flight::get('user')->changePassword(
                Flight::request()->data->pw,
                Flight::request()->data->pw_new,
                Flight::request()->data->pw_repeated
            )) {
                try {
                    R::store(Flight::get('user'));
                    Flight::get('user')->notify(I18n::__('account_changepassword_success'), 'success');
                    $this->redirect('/account/');
                } catch (Exception $e) {
                    //Whoops, what nu?
                }
            } else {
                Flight::get('user')->notify(I18n::__('account_changepassword_failure'), 'error');
            }
        }

        $this->render();
    }

    /**
     * Renders the account page.
     */
    protected function render()
    {
        Flight::render('shared/notification', array(), 'notification');
        //
        Flight::render('shared/navigation/account', array(), 'navigation_account');
        Flight::render('shared/navigation/main', array(), 'navigation_main');
        Flight::render('shared/navigation', array(), 'navigation');
        Flight::render('account/toolbar', array(), 'toolbar');
        Flight::render('shared/header', array(), 'header');
        Flight::render('shared/footer', array(), 'footer');
        Flight::render($this->template, array(
            'record' => Flight::get('user')
        ), 'content');
        Flight::render('html5', array(
            'title' => I18n::__("account_head_title"),
            'language' => Flight::get('language')
        ));
    }
}
