<?php
/** Initialization operations for the backend
  * Ensures a logged-in user and initializes some globals.
  * @package Aquarius.backend
*/

Log::backtrace('backend');

/* Use sessions in backend */
    $aquarius->session_start();


/* Process logins & load logged in user */
    require_once "db/Users.php";
    $login_status = db_Users::authenticate();
                
    // Redirect to frontend if user wants to
    if ($login_status instanceof db_User && isset($_REQUEST['login_frontend'])) {
        header('Location:'.PROJECT_URL);
        exit;
    }

    $user = db_Users::authenticated();

    $request_params = array(
        'request' => clean_magic($_REQUEST),
        'server' => $_SERVER,
        'require_active' => false,
        'user' => $user
    );
    
    // Determine the preset language for working with content
    // Actions often override this and have their own lg specifiers
    $lg_detection = new Language_Detection;
    $lg_detection->add_detector('request_parameter');
    $lg_detection->add_detector('backend_lg_user_default_lang', function() use ($user) {
        // Right after login, we have no language set, but the user has
        if ($user) return $user->defaultLanguage;
    });
    $lg_detection->add_detector('accepted_languages'); // grasping at straws
    $lg_detection->add_detector('primary'); // Better than nothing
    
    $lg = $lg_detection->detect($request_params);
    Log::debug("Using content language ".$lg);
    
    // Determine user interface language
    $admin_lg_detection = new Language_Detection_Admin;
    $admin_lg_detection->add_detector('user_admin_lang');
    $admin_lg_detection->add_detector('accepted_languages');
    $admin_lg_detection->add_detector('use_default');

    $admin_lg = $admin_lg_detection->detect($request_params);
    Log::debug("Using admin language ".$admin_lg);
    

    $aquarius->execute_hooks('backend_init');
    
/* Create base url */

    $url = new Url(false, false);

    // Add language parameter, this one is used everywhere
    $url->add_param('lg', str($lg));


/* Divert to login if user isn't logged in */

    if (!$user) {
        $smarty = $aquarius->get_smarty_backend_container();
        $request_uri = Url::of_request();
        $correct_uri = clone $request_uri; // 'Hopefully correct' would be more to the point

        $admin_domain = $aquarius->conf('admin/domain');
        if ($admin_domain) $correct_uri->host = $admin_domain;

        // Check we're the frameset root and not an inner frame page
        if (preg_match('%/$%', $request_uri->path) === 0) {
            $config_path = $aquarius->conf('admin/path');
            if (!empty($config_path)) {
                $correct_uri->path = $config_path;
            } else {
                $correct_uri->path = dirname($correct_uri->path).'/';
            }
        }
        
        // Login status of -1 means failed attempt
        $login_failed = $login_status === -1;
        
        // primitive check that we had a session cookie submitted
        $cookie_missing = (isset($_GET['returning']) || (bool)$login_status) && empty($_COOKIE);
        
        if ($request_uri == $correct_uri) {
            $smarty->assign(compact('login_failed', 'cookie_missing'));
            $smarty->assign('session_id', session_id());
            $smarty->assign('revision', first(explode('-', $aquarius->revision())));
            $smarty->display('login.tpl');
        } else {
            $correct_uri->params = array();
            $correct_uri->add_param('returning');
            $smarty->assign('correct_uri', $correct_uri);
            $smarty->display('login-redirect.tpl');
        }
        flush_exit();
    }
    

/* Load libraries used in backend */
    require_once "lib/db/Users2languages.php";
    require_once "lib/adminaction.php";
    require_once "lib/moduleaction.php";
    require_once "lib/Translation.php";
