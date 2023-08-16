<?php
/**
 * Little Software Stats
 *
 * An open source program that allows developers to keep track of how their software is being used
 *
 * @package		Little Software Stats
 * @author		Little Apps
 * @copyright   Copyright (c) 2011, Little Apps
 * @license		http://www.gnu.org/licenses/gpl.html GNU General Public License v3
 * @link		http://little-software-stats.com
 * @since		Version 0.1
 * @filesource
 */

// Prevents other pages from being loaded directly
define( 'LSS_LOADED', true );

require_once( dirname( __FILE__ ) . '/inc/main.php' );

$error = "";

if ( ( isset( $_GET['action'] ) ) && $_GET['action'] == 'logout' ) {
    $login->logout_user();
    redirect( $site_url."/login.php" );
}

$valid = false;

$use_captcha = ( get_option( 'recaptcha_enabled' ) == 'true' );

if ( $use_captcha == true ) {
    require_once( ROOTDIR . '/inc/recaptchalib.php' );
    
    $public_key = get_option( 'recaptcha_public_key' );
    $private_key = get_option( 'recaptcha_private_key' );

    if ( isset( $_POST["recaptcha_challenge_field"] ) && isset( $_POST["recaptcha_response_field"] ) ) {
        // Check captcha
        $resp = recaptcha_check_answer( $private_key, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"] );

        $valid = $resp->is_valid;
    }
} else {
    if ( isset( $_POST['captcha'] ) )
        $valid = ( ( md5( $_POST['captcha'] ) == Session::getInstance()->Captcha ) ? ( true ) : ( false ) );
}


if ( isset( $_POST['submitBtn'] ) ) {
    if ( !isset( $_POST['username'] ) || !isset( $_POST['password'] ) )
        return;
		
    $username = ( ( isset( $_POST['username'] ) ) ? ( $_POST['username'] ) : ( '' ) );
    $password = ( ( isset( $_POST['password'] ) ) ? ( $_POST['password'] ) : ( '' ) );

    if ( !$valid ) {
        $error = __( "The CAPTCHA wasn't entered correctly. Go back and try it again." );
    } else {
        $error = SecureLogin::getInstance()->login_user( $username, $password );

        if ( empty( $error ) ) {
            redirect( $site_url );
        }
    }
} else if ( isset( $_POST['resetBtn'] ) ) {
    if ( !$valid )
        $error = __( "The CAPTCHA wasn't entered correctly. Go back and try it again." );
    else
        $error = SecureLogin::getInstance()->forgot_password( $_POST['email'] );
} else if ( isset( $_POST['changeBtn'] ) ) {
    if ( !$valid )
        $error = __( "The CAPTCHA wasn't entered correctly. Go back and try it again." );
    else
        $error = SecureLogin::getInstance()->change_password( $_POST['username'], $_POST['password'], $_POST['password2'], $_POST['key'] );

    if ( empty( $error ) ) {
        redirect( $site_url . "/login.php" );
    }
}
?>
<!DOCTYPE html>
<!--[if IE 6]><html id="ie6" dir="ltr" lang="en"><![endif]-->
<!--[if IE 7]><html id="ie7" dir="ltr" lang="en"><![endif]-->
<!--[if IE 8]><html id="ie8" dir="ltr" lang="en"><![endif]-->
<!--[if !(IE 6) | !(IE 7) | !(IE 8)  ]><!--><html dir="ltr" lang="en"><!--<![endif]-->
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        
        <title><?php _e ( 'Little Software Stats' ); ?> | <?php _e ( 'Login' ); ?></title>
        <link rel="stylesheet" href="css/screen.css" type="text/css" media="screen" title="default" />
        <!-- favicon -->
        <link type="image/x-icon" href="images/shared/favicon.ico" rel="icon" />
        <link type="image/x-icon" href="images/shared/favicon.ico" rel="shortcut icon" />
        <!--  jquery core -->
        <script src="<?php file_url( '/js/jquery/jquery.min.js' ) ?>" type="text/javascript"></script>

        <!-- jquery scripts -->
        <script src="<?php get_min_uri( 'login' ); ?>" type="text/javascript"></script>

        <script type="text/javascript">
            var RecaptchaOptions = {
                theme : 'blackglass'
            };
        </script>

    </head>
    <body id="login-bg"> 
        <!-- Start: login-holder -->
        <div id="login-holder">

            <!-- start logo -->
            <div id="logo-login">
                <a href="index.php"><img src="images/shared/logo.png" width="261" height="40" alt="<?php _e ( 'Little Software Stats' ); ?>" /></a>
            </div>
            <!-- end logo -->
	
            <div class="clear"></div>
	
            <!--  start loginbox ................................................................................. -->
            <div id="loginbox">
                <!--  start message -->
                <?php if ( !empty( $error ) ) : ?>
                    <div id="message-red" align="center">
                        <table border="0" width="90%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="red-left"><?php echo $error; ?></td>
                                <td class="red-right"><a class="close-red"><img src="images/table/icon_close_red.png" alt="" /></a></td>
                            </tr>
                        </table>
                    </div>
                <?php elseif (empty($error) && isset($_POST['resetBtn'])) : ?>
                    <div id="message-green" align="center">
                        <table border="0" width="90%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="green-left"><?php _e ( 'Please check your inbox for further instructions on how to reset your password.' ); ?></td>
                                <td class="green-right"><a class="close-red"><img src="images/table/icon_close_green.png" alt="" /></a></td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
                <!--  end message -->
                
		<!--  start login-inner -->
                <?php if ( ( isset( $_GET['action'] ) ) && $_GET['action'] == "resetPwd" && !empty( $_GET['key'] ) ) : ?>		
		<div id="login-inner">
                    <form action="#" method="post">
                        <input name="key" type="hidden" value="<?php echo $_GET['key']; ?>" />
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <th><?php _e ( 'Username' ); ?></th>
                                <td><input name="username" type="text" class="login-inp" value="<?php echo ( ( isset( $_GET['login'] ) ) ? ( $_GET['login'] ) : ( '' ) ); ?>" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Password' ); ?></th>
                                <td><input name="password" type="password" class="login-inp" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Password (again)' ); ?></th>
                                <td><input name="password2" type="password" class="login-inp" /></td>
                            </tr>
                            <?php if ( $use_captcha ) : ?>
                            <tr>
                                <td colspan="2" align="center" id="login-captcha">
                                    <?php echo recaptcha_get_html( $public_key ); ?>
                                </td>
                            </tr>
                            <?php else : ?>
                            <tr>
                                <td>&nbsp;</td>
                                <td id="login-captcha" align="center"><img src="inc/captcha.php" width="200" height="75" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Enter the code above' ); ?></th>
                                <td><input name="captcha" type="text" class="login-inp" /></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th></th>
                                <td><input name="changeBtn" type="submit" class="submit-login" /></td>
                            </tr>
                        </table>
                    </form>
		</div>
                <?php else : ?>
		<div id="login-inner">
                    <form action="#" method="post">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <th><?php _e ( 'Username' ); ?></th>
                                <td><input name="username" type="text" class="login-inp" value="<?php echo ( ( isset( $_POST['username'] ) ) ? ( $_POST['username'] ) : ( '' ) ); ?>" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Password' ); ?></th>
                                <td><input name="password" type="password" class="login-inp" /></td>
                            </tr>
                            <?php if ( $use_captcha ) : ?>
                            <tr>
                                <td colspan="2" align="center" id="login-captcha">
                                    <?php echo recaptcha_get_html( $public_key ); ?>
                                </td>
                            </tr>
                            <?php else : ?>
                            <tr>
                                <td>&nbsp;</td>
                                <td id="login-captcha" align="center"><img src="inc/captcha.php" width="200" height="75" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Enter the code above' ); ?></th>
                                <td><input name="captcha" type="text" class="login-inp" /></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th></th>
                                <td><input name="submitBtn" type="submit" class="submit-login"  /></td>
                            </tr>
                        </table>
                    </form>
		</div>
		<div class="clear"></div>
		<a href="#" class="forgot-pwd"><?php _e ( 'Forgot Password?' ); ?></a>
                <?php endif; ?>
            <!--  end login-inner -->
            </div>
            <!--  end loginbox -->
 
            <!--  start forgotbox ................................................................................... -->
            <div id="forgotbox">
                <div id="forgotbox-text"><?php _e ( "Please enter your email and we'll reset your password." ); ?></div>
                <!--  start forgot-inner -->
                <div id="forgot-inner">
                    <form action="#" method="post">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <th><?php _e ( 'Email address:' ); ?></th>
                                <td><input name="email" type="text" class="login-inp" /></td>
                            </tr>
                            <?php if ( $use_captcha ) : ?>
                            <tr>
                                <td colspan="2" align="center" id="forgot-captcha"></td>
                            </tr>
                            <?php else : ?>
                            <tr>
                                <td>&nbsp;</td>
                                <td id="forgot-captcha" align="center"><img src="inc/captcha.php" width="200" height="75" /></td>
                            </tr>
                            <tr>
                                <th><?php _e ( 'Enter the code above' ); ?></th>
                                <td><input name="captcha" type="text" class="login-inp" /></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th> </th>
                                <td><input name="resetBtn" type="submit" class="submit-login"  /></td>
                            </tr>
                        </table>
                    </form>
                </div>
                <!--  end forgot-inner -->
                <div class="clear"></div>
                <a href="" class="back-login"><?php _e ( 'Back to login' ); ?></a>
            </div>
            <!--  end forgotbox -->
        </div>
        <!-- End: login-holder -->
        
        <!-- MUST BE THE LAST SCRIPT IN <HEAD></HEAD></HEAD> png fix -->
        <script type="text/javascript">
            $(document).ready(function(){ $(document).pngFix( ); });
        </script>
    </body>
</html>