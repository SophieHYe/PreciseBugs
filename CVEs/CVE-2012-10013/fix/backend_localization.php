<?php
/*
Plugin Name: Backend Localization
Plugin URI: http://kau-boys.com/230/wordpress/kau-boys-backend-localization-plugin
Description: This plugin enables you to run your blog in a different language than the backend of your blog. So you can serve your blog using e.g. German as the default language for the users, but keep English as the language for the administration.
Version: 2.0
Requires at least: 3.2
Author: Bernhard Kau
Author URI: http://kau-boys.com
*/

define( 'BACKEND_LOCALIZATION_URL', WP_PLUGIN_URL . '/' . str_replace( basename(  __FILE__ ), "", plugin_basename( __FILE__ ) ) );

$wp_locale_all = array();

function init_backend_localization(){
	global $wp_locale_all;
	
	load_plugin_textdomain( 'backend-localization', false, dirname( plugin_basename( __FILE__ ) ) );
	
	$wp_locale_all = array(
		'af' => __( 'Afrikaans', 'backend-localization' ),
		'an' => __( 'Aragonese', 'backend-localization' ),
		'ar' => __( 'Arabic', 'backend-localization' ),
		'az' => __( 'Azerbaijani', 'backend-localization' ),
		'az_TR' => __( 'Azerbaijani (Turkey)', 'backend-localization' ),
		'bg_BG' => __( 'Bulgarian', 'backend-localization' ),
		'bn_BD' => __( 'Bengali', 'backend-localization' ),
		'bs_BA' => __( 'Bosnian', 'backend-localization' ),
		'ca' => __( 'Catalan', 'backend-localization' ),
		'ckb' => __( 'Kurdish', 'backend-localization' ),
		'co' => __( 'Corsican', 'backend-localization' ),
		'cpp' => __( 'Cape Verdean Creole', 'backend-localization' ),
		'cs_CZ' => __( 'Czech', 'backend-localization' ),
		'cy' => __( 'Cymraeg (Welsh)', 'backend-localization' ),
		'da_DK' => __( 'Danish', 'backend-localization' ),
		'de_DE' => __( 'German', 'backend-localization' ),
		'dv' => __( 'Divehi/Maldivian', 'backend-localization' ),
		'el' => __( 'Greek', 'backend-localization' ),
		'en_US' => __( 'English', 'backend-localization' ),
		'en_CA' => __( 'English (Canada)', 'backend-localization' ),
		'en_GB' => __( 'English (United Kingdom)', 'backend-localization' ),
		'eo' => __( 'Esperanto', 'backend-localization' ),
		'es_CL' => __( 'Spanish (Chile)', 'backend-localization' ),
		'es_ES' => __( 'Spanish', 'backend-localization' ),
		'es_PE' => __( 'Spanish (Peru)', 'backend-localization' ),
		'es_VE' => __( 'Spanish (Venezuela)', 'backend-localization' ),
		'et' => __( 'Estonian', 'backend-localization' ),
		'eu' => __( 'Basque', 'backend-localization' ),
		'fa_AF' => __( 'Persian (Afghanistan)', 'backend-localization' ),
		'fa_IR' => __( 'Persian', 'backend-localization' ),
		'fi' => __( 'Finnish', 'backend-localization' ),
		'fo' => __( 'Faroese', 'backend-localization' ),
		'fr_BE' => __( 'French (Belgium)', 'backend-localization' ),
		'fr_FR' => __( 'French', 'backend-localization' ),
		'fy' => __( 'Western Frisian', 'backend-localization' ),
		'ga' => __( 'Gaeilge/Irish', 'backend-localization' ),
		'gd' => __( 'Scottish Gaelic', 'backend-localization' ),
		'gl_ES' => __( 'Galician', 'backend-localization' ),
		'gu' => __( 'Gujarati', 'backend-localization' ),
		'haw_US' => __( 'Hawaiian', 'backend-localization' ),
		'he_IL' => __( 'Hebrew', 'backend-localization' ),
		'hi_IN' => __( 'Hindi', 'backend-localization' ),
		'hr' => __( 'Croatian', 'backend-localization' ),
		'hu_HU' => __( 'Hungarian', 'backend-localization' ),
		'hy' => __( 'Armenian', 'backend-localization' ),
		'id_ID' => __( 'Indonesian', 'backend-localization' ),
		'is_IS' => __( 'Icelandic', 'backend-localization' ),
		'it_IT' => __( 'Italian', 'backend-localization' ),
		'ja' => __( 'Japanese', 'backend-localization' ),
		'jv_ID' => __( 'Javanese', 'backend-localization' ),
		'ka_GE' => __( 'Georgian', 'backend-localization' ),
		'kea' => __( 'Kabuverdianu', 'backend-localization' ),
		'kk' => __( 'Kazakh', 'backend-localization' ),
		'kn' => __( 'Kannada', 'backend-localization' ),
		'ko_KR' => __( 'Korean', 'backend-localization' ),
		'ku' => __( 'Kurdish', 'backend-localization' ),
		'ky_KY' => __( 'Kyrgyz', 'backend-localization' ),
		'li' => __( 'Limburgish', 'backend-localization' ),
		'lo' => __( 'Lao', 'backend-localization' ),
		'lv' => __( 'Latvian', 'backend-localization' ),
		// 'me_ME' => __( '', 'backend-localization' ),
		'mg_MG' => __( 'Malagasy', 'backend-localization' ),
		'mk_MK' => __( 'Macedonian', 'backend-localization' ),
		'ml_IN' => __( 'Malayalam', 'backend-localization' ),
		'mn' => __( 'Mongolian', 'backend-localization' ),
		'ms_MY' => __( 'Malay', 'backend-localization' ),
		'my_MM' => __( 'Burmese (Myanmar)', 'backend-localization' ),
		'nb_NO' => __( 'Norwegian (Bokm&aring;l)', 'backend-localization' ),
		'ne_NP' => __( 'Nepali', 'backend-localization' ),
		'nl' => __( 'Dutch', 'backend-localization' ),
		'nl_BE' => __( 'Dutch (Belgium)', 'backend-localization' ),
		'nl_NL' => __( 'Dutch (Netherlands)', 'backend-localization' ),
		'nn_NO' => __( 'Norwegian (Nynorsk)', 'backend-localization' ),
		'os' => __( 'Ossetic/Ossetian', 'backend-localization' ),
		'pa_IN' => __( 'Punjabi', 'backend-localization' ),
		'pl_PL' => __( 'Polish', 'backend-localization' ),
		'pt_BR' => __( 'Portuguese (Brazil)', 'backend-localization' ),
		'pt_PT' => __( 'Portuguese', 'backend-localization' ),
		'ro_RO' => __( 'Romanian', 'backend-localization' ),
		'ru_RU' => __( 'Russian', 'backend-localization' ),
		'ru_UA' => __( 'Russian (Ukraine)', 'backend-localization' ),
		'sa_IN' => __( 'Sanskrit', 'backend-localization' ),
		'sd_PK' => __( 'Sindhi', 'backend-localization' ),
		'si_LK' => __( 'Sinhalese', 'backend-localization' ),
		'sk_SK' => __( 'Slovak', 'backend-localization' ),
		'sl_SI' => __( 'Slovenian', 'backend-localization' ),
		'so_SO' => __( 'Somali', 'backend-localization' ),
		'sq' => __( 'Albanian', 'backend-localization' ),
		'sr_RS' => __( 'Serbian', 'backend-localization' ),
		'srd' => __( 'Sardinian', 'backend-localization' ),
		'su_ID' => __( 'Sundanese', 'backend-localization' ),
		'sv_SE' => __( 'Swedish', 'backend-localization' ),
		'sw' => __( 'Swahili', 'backend-localization' ),
		'ta_IN' => __( 'Tamil', 'backend-localization' ),
		'ta_LK' => __( 'Tamil (Sri Lanka)', 'backend-localization' ),
		'te' => __( 'Telugu', 'backend-localization' ),
		'th' => __( 'Thai', 'backend-localization' ),
		'tr_TR' => __( 'Turkish', 'backend-localization' ),
		'ug_CN' => __( 'Uighur', 'backend-localization' ),
		'uk' => __( 'Ukrainian', 'backend-localization' ),
		'ur' => __( 'Urdu', 'backend-localization' ),
		'uz_UZ' => __( 'Uzbek', 'backend-localization' ),
		'vi' => __( 'Vietnamese', 'backend-localization' ),
		'zh_CN' => __( 'Chinese', 'backend-localization' ),
		'zh_HK' => __( 'Chinese (Hong Kong)', 'backend-localization' ),
		'zh_TW' => __( 'Chinese (Taiwan)', 'backend-localization' )
	);
}

function backend_localization_admin_menu(){
	global $wp_locale_all;
	
	add_options_page( "Kau-Boy's Backend Localization settings", __( 'Backend Language', 'backend-localization' ), 'manage_options', 'backend_localization', 'backend_localization_admin_settings' );
		
	$backend_locale_array = backend_localization_get_languages();
	$backend_locale = backend_localization_get_locale();

	foreach( $backend_locale_array as $locale_value ){
		$link = add_query_arg('kau-boys_backend_localization_language', $locale_value);
		$link = ( strpos( $link, "wp-admin/" ) === false ) ? preg_replace( '#[^?&]*/#i', '', $link ) : preg_replace( '#[^?&]*wp-admin/#i', '', $link );
		if( strpos($link, "?" ) === 0|| strpos( $link, "index.php?" ) ===0 ){
			if( current_user_can( 'manage_options' ) ){
				$link = 'options-general.php?page=backend_localization&godashboard=1&kau-boys_backend_localization_language=' . $locale_value; 
			} else {
				$link = 'edit.php?lang='.$language;
			}
		}
		add_menu_page( __( $wp_locale_all[$locale_value], 'qtranslate' ), $wp_locale_all[$locale_value], 'read', $link, NULL, BACKEND_LOCALIZATION_URL . 'flag_icons/' . strtolower( substr( $locale_value, ( strpos($locale_value, '_' ) * -1 ) ) ) . '.png' );
		/*
		$link = admin_url( add_query_arg( 'kau-boys_backend_localization_language', $locale_value, $link ) );
		add_menu_page( $wp_locale_all[$locale_value], $wp_locale_all[$locale_value], 'read', $link, NULL, BACKEND_LOCALIZATION_URL . 'flag_icons/' . strtolower( substr( $locale_value, ( strpos($locale_value, '_' ) * -1 ) ) ) . '.png' );
		*/
	}
}

function backend_localization_filter_plugin_actions( $links, $file ){
	static $this_plugin;
	if ( !$this_plugin ) $this_plugin = plugin_basename( __FILE__ );
	
	if ( $file == $this_plugin ){
		$settings_link = '<a href="options-general.php?page=backend_localization">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}

function backend_localization_admin_settings(){
	global $wp_locale_all, $wp_version;
	
	if( isset($_POST['save'] ) ) {
		update_option( 'kau-boys_backend_localization_loginselect', $_POST['kau-boys_backend_localization_loginselect'] );
	}
	$loginselect = get_option( 'kau-boys_backend_localization_loginselect' );
	
	$backend_locale = backend_localization_get_locale();
	
	// do redirection for dashboard from the qTranslate Plugin (www.qianqin.de/qtranslate)
	if(isset($_GET['godashboard'])) {
		echo '<h2>' . __( 'Switching Language', 'backend-localization' ) . '</h2>'
			. sprintf( __( 'Switching language to %1$s... If the Dashboard isn\'t loading, use this <a href="%2$s" title="Dashboard">link</a>.', 'backend-localization' ), $wp_locale_all[$locale_value], admin_url() )
			. '<script type="text/javascript">document.location="' . admin_url() . '";</script>';
		exit();
	}
	
	// set default if values haven't been recieved from the database
	if( empty( $backend_locale ) ) $backend_locale = 'en_US';
?>

<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Kau-Boy's Backend Localization</h2>
	<?php if( $settings_saved ) : ?>
	<div id="message" class="updated fade"><p><strong><?php _e( 'Options saved.' ) ?></strong></p></div>
	<?php endif ?>
	<p>
		<?php _e( 'Here you can customize the plugin for your needs.', 'backend-localization' ) ?>
	</p>
	<form method="post" action="">
		<p>
			<input type="checkbox" name="kau-boys_backend_localization_loginselect" id="kau-boys_backend_localization_loginselect"<?php echo ( $loginselect == 'on' )? ' checked="checked"' : '' ?>/>
			<label for="kau-boys_backend_localization_loginselect"><?php _e( 'Hide language selection on login form', 'backend-localization' ) ?></label>
		</p>
		<p>
			<h2><?php _e('Available languages', 'backend-localization') ?></h2>
			<?php $backend_locale_array = backend_localization_get_languages() ?>
			<?php foreach($backend_locale_array as $locale_value) : ?>
			<input type="radio" value="<?php echo $locale_value ?>" id="kau-boys_backend_localization_language_<?php echo $locale_value ?>" name="kau-boys_backend_localization_language"<?php echo ( $backend_locale == $locale_value )? ' checked="checked"' : '' ?> />
			<label for="kau-boys_backend_localization_language_<?php echo $locale_value ?>" style="width: 200px; display: inline-block;">
				<img src="<?php echo BACKEND_LOCALIZATION_URL . 'flag_icons/' . strtolower( substr( $locale_value, ( strpos( $locale_value, '_' ) * -1 ) ) ) . '.png' ?>" alt="<?php echo $locale_value ?>" />
				<?php echo $wp_locale_all[$locale_value] . ' (' . $locale_value . ')' ?>
			</label>
			<br />
			<?php endforeach ?>
			<div class="description">
				<?php echo sprintf( __( 'If your language isn\'t listed, you have to download the right version from the WordPress repository: <a href="http://svn.automattic.com/wordpress-i18n">http://svn.automattic.com/wordpress-i18n</a>. Browser to the language folder of your choice and get the <b>all</b> .mo files for your WordPress Version from <i><b>tags/%1s/messages/</b></i> or from the <i><b>trunk/messages/</b></i> folder. Upload them to the langauge folder <i>%2s</i>. You should than be able to choose the new language (or after a refresh of this page).', 'backend-localization' ), $wp_version, WP_LANG_DIR ) ?>
			</div>
		</p>
		<p class="submit">
			<input class="button-primary" name="save" type="submit" value="<?php _e( 'Save Changes' ) ?>" />
		</p>
	</form>
</div>

<?php
}

function backend_localization_get_languages(){
	$backend_locale_array = array();
	
	if( is_dir( WP_LANG_DIR ) ){
		/* php 4 fix */
		$dir = WP_LANG_DIR;
		$dh = opendir( $dir );
		while ( false !== ( $filename = readdir( $dh ) ) ){
			$files[] = $filename;
		}
		/* read the array */
		foreach( $files as $file ){
			$fileParts = pathinfo( $file );
			if($fileParts['extension'] == 'mo' && ( strlen($fileParts['filename'] ) <= 5 ) ){
				$fileParts['filename'] = substr( $fileParts['basename'], 0, strpos($fileParts['basename'], '.') );
				$backend_locale_array[] = $fileParts['filename'];
			}
		}
	}
	
	if( !in_array( 'en_US', $backend_locale_array ) ){
		$backend_locale_array[] = 'en_US';
	}
	sort($backend_locale_array);
	
	return $backend_locale_array;
}

function backend_localization_save_setting(){
	if( isset( $_REQUEST['kau-boys_backend_localization_language'] ) ){
		setcookie( 'kau-boys_backend_localization_language', backend_localization_filter_var( $_REQUEST['kau-boys_backend_localization_language'] ), time()+60*60*24*30, '/' );
	}
	
	return true;
}

function backend_localization_login_form(){
	global $wp_locale_all;
	
	// return if language selection on login screen should be hidden
	if( get_option( 'kau-boys_backend_localization_loginselect' ) ) return;
	
	$backend_locale_array = backend_localization_get_languages();
	$backend_locale = backend_localization_get_locale();
?>
<p>
	<label>
		<?php _e( 'Language', 'backend-localization' ) ?><br />
		<select name="kau-boys_backend_localization_language" id="user_email" class="input" style="width: 100%; color: #555;">
		<?php foreach( $backend_locale_array as $locale_value ) : ?>
			<option value="<?php echo $locale_value ?>"<?php echo ($backend_locale == $locale_value )? ' selected="selected"' : '' ?>>
				<?php echo $wp_locale_all[$locale_value] . ' (' . $locale_value . ')' ?>
			</option>
		<?php endforeach ?>
		</select>
	</label>
</p>
<?php
}

function backend_localization_get_locale(){
	return 	isset( $_REQUEST['kau-boys_backend_localization_language'] )
			? backend_localization_filter_var( $_REQUEST['kau-boys_backend_localization_language'] )
			: (	isset( $_COOKIE['kau-boys_backend_localization_language'] )
				? $_COOKIE['kau-boys_backend_localization_language']
				: get_option( 'kau-boys_backend_localization_language' ) );
}

function localize_backend($locale){
	// set langauge if user is in admin area
	if( defined( 'WP_ADMIN' ) || ( isset( $_REQUEST['pwd'] ) && isset( $_REQUEST['kau-boys_backend_localization_language'] ) ) ){
		$locale = backend_localization_get_locale();
	}
	return $locale;
}

function backend_localization_set_login_language(){
	setcookie( 'kau-boys_backend_localization_language', "", time() - 3600, '/' );
	setcookie( 'kau-boys_backend_localization_language', backend_localization_filter_var( $_REQUEST['kau-boys_backend_localization_language'] ), time()+60*60*24*30, '/' );
}

function backend_localization_filter_var($lang){
	return preg_replace('/\W/', '', $lang);
}

add_action( 'init', 'init_backend_localization' );
add_action( 'admin_menu', 'backend_localization_admin_menu' );
add_action( 'admin_menu', 'backend_localization_save_setting' );
add_action( 'wp_login', 'backend_localization_set_login_language' );
add_action( 'login_form_locale', 'localize_backend' );
add_action( 'login_head', 'localize_backend' );
add_action( 'login_form', 'backend_localization_login_form' );
add_filter( 'plugin_action_links', 'backend_localization_filter_plugin_actions', 10, 2 );
add_filter( 'locale', 'localize_backend' );

?>