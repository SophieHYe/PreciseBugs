<?php
require_once('cfg/globals.php');
require_once('cfg/common.php');

$navigate_url = !empty($_ENV['SCRIPT_URI'])? dirname($_ENV['SCRIPT_URI']) : dirname(nvweb_self_url());
if(substr($navigate_url, -1)=='/')  $navigate_url = substr($navigate_url, 0, -1);
define('NAVIGATE_URL', $navigate_url);

// create database connection
$DB = new database();
if(!$DB->connect())
{
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());	
}

if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
{
	session_write_close();
	header('location: '.NAVIGATE_MAIN);
	exit;
}

$user = new user();
$website = new website(); // only needed for the users log

if(!empty($_COOKIE['navigate-user']))
{
    $nuid = $DB->query_single(
        'id',
        'nv_users',
        'cookie_hash = :cookie_hash',
        NULL,
        array(':cookie_hash' => $_COOKIE['navigate-user'])
    );

    if(!empty($nuid))
    {
        $user->load($nuid);
        $_SESSION['APP_USER#'.APP_UNIQUE] = $nuid;
	    session_write_close();
	    header('location: '.NAVIGATE_MAIN);
	    exit;
    }
}

if(!empty($_POST['login-username']) && !empty($_POST['login-password']))
{
	$error = !$user->authenticate($_POST['login-username'], $_POST['login-password']);

	if(empty($error) && $user->blocked == '1')
		$error = true;

	if(!$error)
	{
		$_SESSION['APP_USER#'.APP_UNIQUE] = $user->id;

        if($_REQUEST['login-remember']=='1')
            $user->set_cookie();
        else
            $user->remove_cookie();

		$login_request_uri = $_SESSION["login_request_uri"];

        $website->load(); // load first website available (needed in the users log)
		users_log::action(0, $user->id, 'login', $user->username);

		$_SESSION["login_request_uri"] = '';

        setcookie('navigate-session-id', session_id(), time() + 60, '/'); // 60 seconds

		session_write_close();

		header('location: '.NAVIGATE_MAIN.'?'.$login_request_uri);
		exit;		
	}
}

/* CHECK USER BROWSER LANGUAGE PREFERENCES */
$language_default = 'en';

$DB->query('SELECT code 
			FROM nv_languages
			WHERE nv_dictionary != ""', 'array');
			
$languages_available = $DB->result('code');

$langs = array();

if(!empty($_COOKIE['navigate-language']))
{
	$language_default = $_COOKIE['navigate-language'];
}
else if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) 
{
    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

    if (count($lang_parse[1])) 
	{
        $langs = array_combine($lang_parse[1], $lang_parse[4]);
        foreach ($langs as $lang => $val) 
		{
            if ($val === '') $langs[$lang] = 1;
        }
        arsort($langs, SORT_NUMERIC);
    }

	$found = false;
	
	foreach($langs as $language_browser => $val) 
	{	
		foreach($languages_available as $foo => $language_available)
		{
			if($language_available == $language_browser)
			{
				$language_default = $language_browser;	
				$found = true;
				break;
			}
		}
		if($found) break;
	}
}

/* LOAD LANGUAGE */

$lang = new language();
$lang->load($language_default);

// is it a recover password request?
if($_REQUEST['action']=='forgot-password')
{
    $value = mb_strtolower(trim($_REQUEST['value']));
    // look for an existing username or e-mail in Navigate CMS users table
    $found_id = $DB->query_single(
        'id',
        'nv_users',
        ' username = :value OR email = :value',
        NULL,
        array(':value' => $value)
    );

    if(!$found_id)
        echo 'not_found';
    else
    {
        $user->load($found_id);
        $sent = $user->forgot_password();

        if(!$sent)
            echo 'not_sent';
        else
            echo 'sent';
    }

    core_terminate();
}

$layout = new layout('navigate');

echo $layout->doctype();
echo $layout->head();
$current_version = update::latest_installed();

?>
<body>

<div class="navigate-top"></div>

<div id="navigate-status" class="ui-corner-all">
	<div>
        <div style="float: left;">

        </div>
        <div style="float: right;">
            <?php echo APP_NAME;?> v<?php echo $current_version->version;?>, &copy; <?php echo date('Y');?>
        </div>    
        <div style=" clear: both; "></div>
     </div>
</div>

<div id="navigate-login" class="ui-corner-all" style=" border: solid 1px #ddd; top: 50%; margin-top: -150px; position: absolute; margin-left: -325px; left: 50%; padding: 4px; width: 700px; height: 350px; ">
    <form
    	name="navigate-content-form"
        action="<?php echo $_SERVER['PHP_SELF'];?>"
    	method="post" 
        enctype="multipart/form-data"
        style=" margin-left: auto; margin-right: auto; margin-top: 50px; height: 350px; ">

        <div style=" float: left; margin-right: 55px; margin-left: 20px; ">
            <img src="img/navigate-logo-430x200.png" width="300" height="140"  />
            <a href="http://www.navigatecms.com" style=" display: block; text-decoration: none; color: #2E476E; text-align:  center; " target="_blank">www.navigatecms.com</a>
        </div>
        <div style=" float: left; width: 288px; ">
            <div class="navigate-form-row">
                <label style=" padding-top: 6px; margin-bottom: 6px; font-size: 15px; "><?php echo t(1, 'User');?></label>
                <br />
                <input type="text" value="" size="32" name="login-username" id="login-username" style=" width: 278px; font-size: 20px; " />
            </div>
            <div class="navigate-form-row">
                <label style=" padding-top: 6px; margin-bottom: 6px; font-size: 15px; "><?php echo t(2, 'Password');?></label>
                <br />
                <input type="password" value="" size="32" name="login-password" id="login-password"  style=" width: 278px; font-size: 20px; " />
            </div>

            <div class="navigate-form-row">
                <input type="checkbox" name="login-remember" id="login-remember" value="1" />
                <label onclick="$('#login-remember').trigger('click');" style=" margin-left: 3px; margin-top: 2px; position: absolute; "><?php echo t(406, 'Remember me');?></label>
            </div>

            <div class="navigate-form-row" id="login-button" style=" margin-top: 48px; font-size: 15px; ">
                <button style=" background: none; border: none; color: transparent; display: block; float: left; "><?php echo t(3, 'Enter');?></button>
                <a href="#" style=" color: #2E476E; font-size: 10px; line-height: 30px; float: right; text-decoration: none;"><?php echo t(407, 'Forgot password?');?></a>
            </div>
        </div>

        <?php
            if(isset($error))
            {
                ?>
                <div class="navigate-form-row" style=" padding-top: 20px; text-align: center; display: none; ">
                    <span class="error"><img src="img/icons/silk/decline.png" width="16" height="16" align="absmiddle" /> <?php echo t(4, 'Login incorrect.');?></span>
                </div>
                <?php
            }
        ?>

    </form>
</div>

<div id="navigate-lost-password-dialog" style=" display: none; ">
    <form action="?" method="post">
        <div class="navigate-form-row">
            <label style=" padding-top: 6px; margin-bottom: 6px; font-size: 11px; width: auto; ">
                <?php echo t(449, "Enter your Navigate CMS username or e-mail address");?>
            </label>
            <br />
            <input type="text" value="" size="32" name="forgot-password" id="forgot-password"  style=" width: 96%; font-size: 17px; " />
            <br />
            <div id="forgot-password-problem" class="subcomment" style=" margin-left: 0px; color: #f33; ">&nbsp;</div>
        </div>
        <div class="navigate-form-row" style=" margin-top: 20px; ">
            <button style=" background: none; border: none; color: transparent; display: block; float: left; font-size: 12px; ">
                <?php echo t(190, 'Ok');?>
            </button>
        </div>
    </div>
</div>

</body>

<script language="javascript" type="text/javascript">

var NAVIGATE_APP = "<?php echo NAVIGATE_URL.'/'.NAVIGATE_MAIN;?>";

$(window).on('load,resize', function()
{
    $('#navigate-status').css({ 'width': $(document).width() - 18 });
});

$(document).ready(function()
{
    $('button').removeAttr('style').css({'font-size': '14px', 'padding-bottom': '4px'}).hide().fadeIn('slow', function()
    {
        $('.error').parent().fadeIn('slow');
    });

    $('input[name="login-username"]').focus();

    $('#navigate-lost-password-dialog form').on('submit', function(e)
    {
        $('#forgot-password-problem').html('&nbsp;');
        e.stopPropagation();
        e.preventDefault();

        $.post(
            'login.php',
            {
                'action': 'forgot-password',
                'value': $('#forgot-password').val()
            },
            function(data)
            {
                if(data=='sent')
                {
                    //$('#navigate-lost-password-dialog').dialog('close');
                    $('#navigate-lost-password-dialog').html('');
                    $('#navigate-lost-password-dialog').append('<div style="text-align: center; margin: 16px; "><i class="fa fa-5x fa-envelope" style="color: #BBD6F5"></i><i style="position: absolute; margin-top: 28px; margin-left: -12px; color: #2E476E;" class="fa fa-2x fa-check"></i></div>');
                    $('#navigate-lost-password-dialog').append('<div style="text-align: center; font-weight: bold; padding: 10px; "><?php echo t(454, 'An e-mail with a confirmation link has been sent to your e-mail account.', false, true); ?></div>');
                }
                else if(data=='not_found')
                {
                    $('#forgot-password-problem').html("<?php echo t(453, "Couldn't find this username or e-mail address", false, true);?>");
                }
                else// if(data=='not_sent')
                {
                    $('#forgot-password-problem').html("<?php echo t(452, "E-mail could not be sent; please contact the administrator", false, true);?>");
                }
            }
        );
    });

    // forgot password button
    $('#login-button a').on('click', function()
    {
        $('#navigate-lost-password-dialog').dialog({
            title: "<?php echo t(407, 'Forgot password?', false, true);?>",
            modal: true,
            width: 350,
            height: 220
        });
    });

    $.setCookie("navigate-tinymce-scroll", '{}');
});
    
</script>

<?php
    // are we on a password change process?
    if($_REQUEST['action']=='password-reset')
    {
        $value = trim($_REQUEST['value']);

        // look for an existing username or e-mail in Navigate CMS users table
        $found_id = $DB->query_single(
            'id',
            'nv_users',
            'activation_key = :activation_key',
            NULL,
            array(':activation_key' => $value)
        );

        if(!empty($found_id))
        {
            $user->load($found_id);

            if(!empty($_REQUEST['login-password']))
            {
                $user->activation_key = '';
                $user->set_password(trim($_REQUEST['login-password']));
                $user->save();
                ?>
                <script language="javascript">
                    $(document).ready(function()
                    {
                        $('form:first').append('<div class="navigate-form-row" style=" padding-top: 20px; text-align: center; display: none; "></div>');
                        $('form:first').find('div:last').html('<span class="ok" style="color: #579A4D; font-weight: bold; "><img src="img/icons/silk/accept.png" width="16" height="16" align="absmiddle" /> <?php echo t(455, 'Your new password has been activated.');?></span>');
                        $('form:first').find('div:last').fadeIn('slow');
                    });
                </script>
                <?php
            }
            else
            {
                ?>
                <script language="javascript">
                    $(document).ready(function()
                    {
                        $('#login-username').parent().remove();
                        $('#login-button a').remove();
                        $('#login-remember').parent().remove();
                        $('#login-button').remove();
                        $('#navigate-lost-password-dialog').remove();
                        $('form').attr('action', $('form').attr('action') + '?action=password-reset&value=<?php echo $value;?>');
                        $('form').append('<button id="login-button" style="margin-top: 20px; font-size: 14px; "><?php echo t(34, "Save");?></button>');
                    });
                </script>
                <?php
            }
        }
    }
?>
</html>
<?php
	$DB->disconnect();
?>