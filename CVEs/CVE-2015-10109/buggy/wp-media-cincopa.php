<?php
/*
Plugin Name: Post video players, slideshow albums, photo galleries and music / podcast playlist
Plugin URI: http://www.cincopa.com/media-platform/wordpress-plugin.aspx
Description: Post rich videos and photos galleries from your cincopa account
Author: Cincopa 
Version: 1.136
*/

function _cpmp_plugin_ver()
{
	return 'wp1.136';
}

function _cpmp_afc()
{
	$cincopa_afc = get_site_option('CincopaAFC');
	
	if ($cincopa_afc == '')
		return '';
	
	return '&afc='.$cincopa_afc;
}

function _cpmp_url()
{
	return '//www.cincopa.com';
}

if (strpos($_SERVER['REQUEST_URI'], 'media-upload.php') && strpos($_SERVER['REQUEST_URI'], '&type=cincopa') && !strpos($_SERVER['REQUEST_URI'], '&wrt='))
{
	header('Location: '._cpmp_url().'/media-platform/start.aspx?ver='._cpmp_plugin_ver()._cpmp_afc().'&rdt='.urlencode(_cpmp_selfURL()));
	exit;
}

function _cpmp_selfURL()
{
	$s = empty ( $_SERVER ["HTTPS"] ) ? '' : ($_SERVER ["HTTPS"] == "on") ? "s" : "";

	$protocol =  strtolower ( $_SERVER ["SERVER_PROTOCOL"] );
	$protocol =  substr($protocol, 0, strpos($protocol, "/"));
	$protocol .= $s;

	$port = ($_SERVER ["SERVER_PORT"] == "80") ? "" : (":" . $_SERVER ["SERVER_PORT"]);
	$ret = $protocol . "://" . $_SERVER ['SERVER_NAME'] . $port . $_SERVER ['REQUEST_URI'];

	return $ret;
}

function _cpmp_pluginURI()
{
	$s = empty ( $_SERVER ["HTTPS"] ) ? '' : ($_SERVER ["HTTPS"] == "on") ? "s" : "";

	$protocol =  strtolower ( $_SERVER ["SERVER_PROTOCOL"] );
	$protocol =  substr($protocol, 0, strpos($protocol, "/"));
	$protocol .= $s;

	$url = site_url( '', $protocol );
	//echo 'url = ' . $url;
	return $url . '/wp-content/plugins/'.dirname(plugin_basename(__FILE__));
	//return get_option('siteurl').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__));
}

function _cpmp_WpMediaCincopa_init() // constructor
{
	add_action('media_buttons', '_cpmp_addMediaButton', 20);

	add_action('media_upload_cincopa', '_cpmp_media_upload');
	// No longer needed in WP 2.6
	if ( !function_exists('wp_enqueue_style') )
	{
		add_action('admin_head_media_upload_type_cincopa', 'media_admin_css');
	}
      
	// check auth enabled
	//if(!function_exists('curl_init') && !ini_get('allow_url_fopen')) {}
}

function _cpmp_media_menu($tabs) {
    $newtab = array('cincopabox' => __('Insert Media from Cincopa', 'cincopa'));
    return array_merge($tabs, $newtab);
}
add_filter('media_upload_tabs', '_cpmp_media_menu');


function media_cincopabox_process() {
    if(isset($_REQUEST['wrt'])){
        _cpmp_media_upload_type_cincopa();
        return;
    }
    if (sizeof($_POST)>0){
        var_dump($_POST);
        
    }
    media_upload_header();
    ?>
    <iframe src="<?php echo _cpmp_url().'/media-platform/start.aspx?ver='._cpmp_plugin_ver()._cpmp_afc().'&rdt='.urlencode(_cpmp_selfURL()."&")?>&" width="100%" height="98%"></iframe>
    <?php
}
function cincopabox_menu_handle() {
    
    return wp_iframe( 'media_cincopabox_process');
}

add_action('media_upload_cincopabox', 'cincopabox_menu_handle');


function _cpmp_addMediaButton($admin = true)
{
	global $post_ID, $temp_ID;
	$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);

	$media_upload_iframe_src = get_option('siteurl')."/wp-admin/media-upload.php?post_id=$uploading_iframe_ID";

	$media_cincopa_iframe_src = apply_filters('media_cincopa_iframe_src', "$media_upload_iframe_src&amp;type=cincopa&amp;tab=cincopa");
	$media_cincopa_title = __('Add Cincopa Media', 'wp-media-cincopa');
	?>
	<div id="cincopa_button">
		<a onClick="javascript:cincopa_launch_popup();" class="insert-media " data-editor="content" title="Add Media">
			<div class="first" onClick="javascript:cincopa_launch_popup();">
				<img src="<?php echo _cpmp_pluginURI(); ?>/media-cincopa.gif" alt="<?php echo $media_cincopa_title; ?>" />
			</div>
		</a>
		<div class="last cincopa-show-gallery" id="cincopa_show_button"<?php if (is_rtl()) echo ' style="border-right:1px solid #bbb;"'; ?>></div>
	</div>
	<div class="cincopa-gallery-block"><img src="<?php echo _cpmp_pluginURI(); ?>/loading.gif"></div>
	<?php
}

function _cpmp_modifyMediaTab($tabs)
{
	return array(
		'cincopa' =>  __('Cincopa photo', 'wp-media-cincopa'),
	);
}

function _cpmp_media_upload()
{
	wp_iframe('_cpmp_media_upload_type_cincopa');
}


function _cpmp_media_upload_type_cincopa()
{
	global $wpdb, $wp_query, $wp_locale, $type, $tab, $post_mime_types;
	add_filter('media_upload_tabs', '_cpmp_modifyMediaTab');
?>

<br />
<br />
<h2>&nbsp;&nbsp;Please Wait...</h2>

<script>

	function _cpmp_cincopa_stub()
	{
		var i = location.href.indexOf("&wrt=");

		if (i > -1)
		{
			top.send_to_editor(unescape(location.href.substring(i+5)));
		}

		top.tb_remove();
	}

	window.onload = _cpmp_cincopa_stub;

</script>

<?php
}

_cpmp_WpMediaCincopa_init();


// this new regex should resolve the problem of having unicode chars in the tag
define("CINCOPA_REGEXP", "/\[cincopa([^\]]*)\]/");


function _cpmp_cincopa_tag($fid)
{
	return _cpmp_plugin_callback(array($fid));
}

function _cpmp_plugin_callback($match)
{
	$uni = uniqid('');
	
	// don't remove it. used for open graph generation
	$ret = sprintf( '<!-- %s -->', $match[0] );
	
	$ret .= '
<!-- Cincopa WordPress plugin '._cpmp_plugin_ver().': //www.cincopa.com/media-platform/wordpress-plugin.aspx -->
<div id="cp_widget_'.$uni.'"><img src="//www.cincopa.com/media-platform/runtime/loading.gif" style="border:0;" alt="Cincopa WordPress plugin" /></div>
<script src="//www.cincopa.com/media-platform/runtime/libasync.js" type="text/javascript"></script>
<script type="text/javascript">
/* PLEASE CHANGE DEFAULT EXCERPT HANDLING TO CLEAN OR FULL (go to your Wordpress Dashboard/Settings/Cincopa Options ... */
cp_load_widget("'.urlencode($match[0]).'", "cp_widget_'.$uni.'");
</script>
';

	$ret .= '<noscript>Powered by Cincopa Video Hosting.<br>';
	$ret .= '</noscript>';

	return $ret;
}

function _cpmp_async_plugin_callback($match)
{
	$uni = uniqid('');

	$ret = '
<!-- Cincopa WordPress plugin '._cpmp_plugin_ver().' (async engine): //www.cincopa.com/media-platform/wordpress-plugin.aspx -->

<div id="cp_widget_'.$uni.'"><img src="//www.cincopa.com/media-platform/runtime/loading.gif" style="border:0;" alt="Cincopa WordPress plugin" /></div>

<script type="text/javascript">
/* PLEASE CHANGE DEFAULT EXCERPT HANDLING TO CLEAN OR FULL (go to your Wordpress Dashboard/Settings/Cincopa Options ... */

var cpo = [];
cpo["_object"] ="cp_widget_'.$uni.'";
cpo["_fid"] = "'.urlencode($match[0]).'";

var _cpmp = _cpmp || [];
_cpmp.push(cpo);

(function() {
	var cp = document.createElement("script"); cp.type = "text/javascript";
	cp.async = true; cp.src = "//www.cincopa.com/media-platform/runtime/libasync.js";
	var c = document.getElementsByTagName("script")[0];
	c.parentNode.insertBefore(cp, c);
})();

</script>

';

	$ret .= '<noscript>Powered by Cincopa Video Hosting.<br>';
	$ret .= '</noscript>';

	return $ret;
}

function _cpmp_feed_plugin_callback($match)
{
	$ret = '<img style="border:0;" src="//www.cincopa.com/media-platform/api/thumb.aspx?fid='.urlencode($match[1]).'&size=large" />';

	return $ret;
}

$opengraph_meta = array();

function _cpmp_plugin($content) {
	global $opengraph_meta;
	
	$cincopa_rss = get_site_option('CincopaRss');

	$cincopa_async = get_site_option('CincopaAsync');
	if (strpos($_SERVER['REQUEST_URI'], 'tcapc=true'))
		$cincopa_async = 'async';
	else if (strpos($_SERVER['REQUEST_URI'], 'tcapc=false'))
		$cincopa_async = 'plain';

	if (strpos($_SERVER['REQUEST_URI'], 'cpdisable=true'))
		return $content;

	$cincopa_excerpt_rt = get_site_option('CincopaExcerpt');
	if ($cincopa_excerpt_rt == 'remove' && (is_search() || is_category() || is_archive() || is_home()))
		return preg_replace(CINCOPA_REGEXP, '', $content);
	else if ( is_feed() ) {
		if ($cincopa_rss == 'full') {
			if ($cincopa_async == 'async') {
				return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_async_plugin_callback', $content));
			} else {
				preg_replace_callback(CINCOPA_REGEXP, '_cpmp_opengraph_meta_tags_callback', $content);
				return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_plugin_callback', $content));
			}
		} else {
			return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_feed_plugin_callback', $content));
		}
	}
	else if ($cincopa_async == 'async')
		return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_async_plugin_callback', $content));
	else {
		// add cincopa did into array for open graph generation
		preg_replace_callback(CINCOPA_REGEXP, '_cpmp_opengraph_meta_tags_callback', $content);
		return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_plugin_callback', $content));
	}
}

function _cpmp_plugin_rss($content)
{
	$cincopa_rss = get_site_option('CincopaRss');

	$cincopa_async = get_site_option('CincopaAsync');
	if (strpos($_SERVER['REQUEST_URI'], 'tcapc=true'))
		$cincopa_async = 'async';
	else if (strpos($_SERVER['REQUEST_URI'], 'tcapc=false'))
		$cincopa_async = 'plain';
	
	if ($cincopa_rss == 'full') {
		if ($cincopa_async == 'async') {
			return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_async_plugin_callback', $content));
		} else {
			preg_replace_callback(CINCOPA_REGEXP, '_cpmp_opengraph_meta_tags_callback', $content);
			return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_plugin_callback', $content));
		}
	} else {
		return (preg_replace_callback(CINCOPA_REGEXP, '_cpmp_feed_plugin_callback', $content));
	}
}

//add_shortcode('cincopa', 'cincopa_plugin_shortcode');
add_filter('the_content', '_cpmp_plugin');
add_filter('the_content_rss', '_cpmp_plugin_rss');
add_filter('the_excerpt_rss', '_cpmp_plugin_rss');
add_filter('comment_text', '_cpmp_plugin'); 

add_action ( 'bp_get_activity_content_body', '_cpmp_plugin' );
add_action ( 'bp_get_the_topic_post_content', '_cpmp_plugin' );

add_action('wp_dashboard_setup', '_cpmp_dashboard'); 

// Hook for adding admin menus
// http://codex.wordpress.org/Adding_Administration_Menus
add_action('admin_menu', '_cpmp_mt_add_pages');

// register CincopaWidget widget
add_action('widgets_init', create_function('', 'return register_widget("CincopaWidget");'));

wp_oembed_add_provider( '//www.cincopa.com/*', '//www.cincopa.com/media-platform/oembed.aspx' );

if (get_site_option('cincopa_welcome_notice') != _cpmp_plugin_ver())
	add_action( 'admin_notices', '_cpmp_activation_notice' );

$open_graph_mode = get_site_option('CincopaOpenGraph');
if ( $open_graph_mode == 1 ) {
	add_action('wp_head', '_cpmp_buffer_start');
	add_action('wp_footer', '_cpmp_buffer_end');
		
	add_action('wp_head', '_cpmp_opengraph_meta_tags');
	add_filter('language_attributes', '_cpmp_opengraph_add_prefix');
}

function _cpmp_buffer_start() { ob_start("_cpmp_content_callback"); }
function _cpmp_buffer_end() { ob_end_flush(); }
function _cpmp_content_callback($buffer) {
	
	$meta_list = _cpmp_get_opengraph_meta_list();
	
	if ( ! empty($meta_list) ) {
		// modify buffer here, and then return the updated code
		$buffer = str_replace(_cpmp_opengraph_placeholder(), $meta_list, $buffer );
	}
	
	return $buffer;
}

function _cpmp_get_opengraph_meta_list() {
	global $opengraph_meta;

	$opengraph_list = '';
	foreach ($opengraph_meta as $key => $item)
		$opengraph_list .= $item;
		
	return $opengraph_list; 	
}
/**
 * Add Open Graph XML prefix to <html> element.
 *
 * @uses apply_filters calls 'opengraph_prefixes' filter on RDFa prefix array
 */
function _cpmp_opengraph_add_prefix( $output ) {
  $prefixes = array(
    'og' => '//ogp.me/ns#'
  );
  $prefixes = apply_filters('_cpmp_opengraph_prefixes', $prefixes);

  $prefix_str = '';
  foreach( $prefixes as $k => $v ) {
    $prefix_str .= $k . ': ' . $v . ' ';
  }
  $prefix_str = trim($prefix_str);
 	
  if (preg_match('/(prefix\s*=\s*[\"|\'])/i', $output)) {
    $output = preg_replace('/(prefix\s*=\s*[\"|\'])/i', '${1}' . $prefix_str, $output);
  } else {
    $output .= ' prefix="' . $prefix_str . '"';
  }
  return $output;
}

function _cpmp_opengraph_meta_tags_callback( $match ) {
	global $opengraph_meta;

	if ( empty($match[1]) ) 
		return '';
	
	$opengraph_meta_item = 
		'<meta property="og:image" content="//www.cincopa.com/media-platform/api/thumb_open.aspx?fid=' . urlencode(trim($match[1])) . '&size=large">' . "\n" .
		'<meta name="twitter:image" content="//www.cincopa.com/media-platform/api/thumb_open.aspx?fid=' . urlencode(trim($match[1])) . '&size=large">' . "\n";

	$opengraph_meta[trim($match[1])] = $opengraph_meta_item;
	
	return $opengraph_meta_item;
}
function _cpmp_opengraph_placeholder() {
	return '<!-- [CincopaOpenGraph] -->';
}
function _cpmp_opengraph_meta_tags() {

	if ( ! is_singular() ) {
		echo _cpmp_opengraph_placeholder();
		return;
	}
		
	$post = get_queried_object();
	$content = $post->post_content;
	$meta = preg_replace_callback(CINCOPA_REGEXP, '_cpmp_opengraph_meta_tags_callback', $content);
	
	echo _cpmp_get_opengraph_meta_list();
}
	
/////////////////////////////////
// #AB# dropdown menu functions
/////////////////////////////////

// register js and style on initialization
add_action('init', '_cpmp_register_script');
function _cpmp_register_script() {
	
	if ( is_admin() ) {
		// add styles for dropdown 
		wp_register_style( 'cincopa-style', plugins_url('css.cincopa.css', __FILE__), false, '20130425.4', 'all' );
		// add js for dropdown 
		wp_register_script( 'cincopa-script', plugins_url('js.cincopa.js', __FILE__), array(), '20130425.4' );
		
		wp_enqueue_script( 'cincopa-script' );
		wp_enqueue_style( 'cincopa-style' );
		
	}
}

/////////////////////////////////
// dashboard widget
//////////////////////////////////
function _cpmp_dashboard()
{
	if(function_exists('wp_add_dashboard_widget'))
		wp_add_dashboard_widget('cincopa', 'Cincopa', '_cpmp_dashboard_content');
}

function _cpmp_dashboard_content()
{

        
	echo "<iframe src='//www.cincopa.com/media-platform/wordpress-dashboard-content.aspx?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='100%' height='370px' scrolling='no'></iframe>";

}



/*function changeUrl()
{
    $pageURL = $_SERVER['QUERY_STRING'];
    
    if( $pageURL == 'page=sub-page4' )
    {
        //header ('location: http://help.cincopa.com/home'); //in same tab
    }
}
add_action( 'admin_menu', 'changeUrl' );*/

// action function for above hook
function _cpmp_mt_add_pages()
{

	// Add a new submenu under Options:
	
	add_options_page('Cincopa Options', 'Cincopa Options', 'edit_pages', 'cincopaoptions', '_cpmp_mt_options_page');


	if(function_exists('add_menu_page'))
	{
		// Add a new top-level menu (ill-advised):
		add_menu_page('Cincopa', 'Cincopa', 'edit_pages', __FILE__, '_cpmp_mt_toplevel_page');

		// kill the first menu item that is usually the the identical to the menu itself
		add_submenu_page(__FILE__, '', '', 'edit_pages', __FILE__);

		add_submenu_page(__FILE__, 'Manage Galleries', 'Manage Galleries', 'edit_pages', 'sub-page', '_cpmp_mt_sublevel_monitor');

		add_submenu_page(__FILE__, 'Media Library', 'Media Library', 'edit_pages', 'sub-page1', '_cpmp_mt_sublevel_library');

		add_submenu_page(__FILE__, 'Create Gallery', 'Create Gallery', 'edit_pages', 'sub-page2', '_cpmp_mt_sublevel_create');

		add_submenu_page(__FILE__, 'My Account', 'My Account', 'edit_pages', 'sub-page3', '_cpmp_mt_sublevel_myaccount');

		add_submenu_page(__FILE__, 'Support Forum', 'Support Forum', 'edit_pages', 'sub-page4', '_cpmp_mt_sublevel_forum');
	}
}

function _cpmp_isAdmin()
{
	return !function_exists('is_site_admin') || is_site_admin() == true;
}

function _cpmp_mt_options_page()
{


	if (strpos($_SERVER['QUERY_STRING'], 'hide_note=welcome_notice'))
	{
		update_site_option('cincopa_welcome_notice', _cpmp_plugin_ver());
		echo "<script type=\"text/javascript\">	document.location.href = '".$_SERVER['HTTP_REFERER']."'; </script>";
		exit;
	}

	$cincopa_afc = get_site_option('CincopaAFC');
	$cincopa_excerpt = get_site_option('CincopaExcerpt');
	$cincopa_async = get_site_option('CincopaAsync');
	$cincopa_rss = get_site_option('CincopaRss');
	$cincopa_opengraph = get_site_option('CincopaOpenGraph');
	if ( $cincopa_opengraph === false ) {
		// enabled by default
		update_site_option('CincopaOpenGraph', 1);
		$cincopa_opengraph = 1;	
	}
	
	if ( isset($_POST['submit']) )
	{
		if (_cpmp_isAdmin())
		{
			if (isset($_POST['cincopaafc']))
			{
				$cincopa_afc = $_POST['cincopaafc'];
				update_site_option('CincopaAFC', $cincopa_afc);
			}

			if (isset($_POST['asyncRel']))
			{
				$cincopa_async = $_POST['asyncRel'];
				update_site_option('CincopaAsync', $cincopa_async);			
			}

			if (isset($_POST['rssRel']))
			{
				$cincopa_rss = $_POST['rssRel'];
				update_site_option('CincopaRss', $cincopa_rss);
			}
		}

		if (isset($_POST['embedRel']))
		{
			$cincopa_excerpt = $_POST['embedRel'];
			update_site_option('cincopaexcerpt', $cincopa_excerpt);			
		}
		if (isset($_POST['open_graph']) && $_POST['open_graph'] == 1)
		{
			$cincopa_opengraph = 1;
			update_site_option('CincopaOpenGraph', 1);			
		}
		else {
			update_site_option('CincopaOpenGraph', 0);
			$cincopa_opengraph = 0;
		}
		
		echo "<div id=\"updatemessage\" class=\"updated fade\"><p>Cincopa settings updated.</p></div>\n";
		echo "<script type=\"text/javascript\">setTimeout(function(){jQuery('#updatemessage').hide('slow');}, 3000);</script>";	
	}

	$tpl_checked = 'checked="checked"';
	$disp_excerpt2 = $cincopa_excerpt == 'clean' ? $tpl_checked : '';
	$disp_excerpt3 = $cincopa_excerpt == 'full' ? $tpl_checked : '';
	$disp_excerpt4 = $cincopa_excerpt == 'remove' ? $tpl_checked : '';
	$disp_excerpt1 = $cincopa_excerpt == '' || $cincopa_excerpt == 'nothing' ? $tpl_checked : '';

	$disp_async2 = $cincopa_async == 'async' ? $tpl_checked : '';
	$disp_async1 = $cincopa_async == '' || $cincopa_async == 'plain' ? $tpl_checked : '';

	$disp_rss2 = $cincopa_rss == 'full' ? $tpl_checked : '';
	$disp_rss1 = $cincopa_rss == '' || $cincopa_rss == 'thumb' ? $tpl_checked : '';

	$disp_opengraph = ( $cincopa_opengraph == 1) ? $tpl_checked : '';

?>
	<div class="wrap">
		<h2>Cincopa Configuration</h2>
		<div class="postbox-container">
			<div class="metabox-holder">
				<div class="meta-box-sortables">
					<form action="" method="post" id="cincopa-conf">
						<div id="cincopa_settings" class="postbox">
							<div class="handlediv" title="Click to toggle">
								<br />
							</div>
							<h3 class="hndle">
								<span>Cincopa Settings</span>
							</h3>
							<div class="inside" style="width:600px;">
								<table class="form-table">

									<tr style="width:100%;">
										<th valign="top" scrope="row">
											<label for="cincopaafc">
												Excerpt Handling (<a target="_blank" href="//help.cincopa.com/entries/448859-wordpress-plugin-settings-page#excerpt">what?</a>):
											</label>
										</th>
										<td valign="top">

											<input type="radio" <?php echo $disp_excerpt1; ?> id="embedCustomization0" name="embedRel" value="nothing"/>
											<label for="embedCustomization0">Do nothing (default Wordpress behavior)</label>
											<br/>
											<input type="radio" <?php echo $disp_excerpt2; ?> id="embedCustomization1" name="embedRel" value="clean"/>
											<label for="embedCustomization1">Clean excerpt (do not show gallery)</label>
											<br/>
											<input type="radio" <?php echo $disp_excerpt4; ?> id="embedCustomization3" name="embedRel" value="remove"/>
											<label for="embedCustomization3">Remove gallery (do not show gallery in all non post pages)</label>
											<br/>
											<input type="radio" <?php echo $disp_excerpt3; ?> id="embedCustomization2" name="embedRel" value="full"/>
											<label for="embedCustomization2">Full excerpt (show gallery)</label>
											<br/>

										</td>
									</tr>
									
									<tr>
										<th valign="top" scrope="row">
											<label for="open_graph">
												Use Open Graph Tags? (<a target="_blank" href="//help.cincopa.com/entries/448859-wordpress-plugin-settings-page#opengraph">what?</a>):
											</label>
										</th>
										<td valign="top">
											<input type="checkbox" <?php echo $disp_opengraph; ?> id="open_graph" name="open_graph" value="1" />
											<br/>
										</td>
									</tr>


									<?php

if (_cpmp_isAdmin())
{
?>


									<tr style="width:100%;">
										<th valign="top" scrope="row">
											<label for="cincopaafc">
												Cincopa AFC (<a target="_blank" href="//help.cincopa.com/entries/448859-wordpress-plugin-settings-page#afc">what?</a>):
											</label>
										</th>
										<td valign="top">
											<input id="cincopaafc" name="cincopaafc" type="text" size="20" value="<?php echo $cincopa_afc; ?>"/>
										</td>
									</tr>





									<tr style="width:100%;">
										<th valign="top" scrope="row">
											<label for="cincopaasync">
												Async Engine (<a target="_blank" href="//help.cincopa.com/entries/448859-wordpress-plugin-settings-page#async">what?</a>):
											</label>
										</th>
										<td valign="top">

											<input type="radio" <?php echo $disp_async1; ?> id="asyncCustomization0" name="asyncRel" value="plain"/>
											<label for="asyncCustomization0">Plain Sync</label>
											<br/>
											<input type="radio" <?php echo $disp_async2; ?> id="asyncCustomization1" name="asyncRel" value="async"/>
											<label for="asyncCustomization1">Advanced Async </label>
											<br/>


										</td>
									</tr>

									<tr style="width:100%;">
										<th valign="top" scrope="row">
											<label for="cincoparss">
												RSS handling (<a target="_blank" href="//help.cincopa.com/entries/448859-wordpress-plugin-settings-page#rsshandling">what?</a>):
											</label>
										</th>
										<td valign="top">

											<input type="radio" <?php echo $disp_rss1; ?> id="rss0" name="rssRel" value="thumb"/>
											<label for="rss0">Show thumbnail</label>
											<br/>
											<input type="radio" <?php echo $disp_rss2; ?> id="rss1" name="rssRel" value="full"/>
											<label for="rss1">Full gallery</label>
											<br/>


										</td>
									</tr>




									<?php
}

?>


									<tr style="width:100%;">
										<th valign="top" scrope="row" colspan=2>
											Note:
<ol>
<li>Use this PHP code to add a gallery directly to your template : <br>&nbsp;&nbsp;&nbsp; <i>&lt;?php echo _cpmp_cincopa_tag("GALLERY ID"); ?&gt;</i></li>
</ol>
										</th>
									</tr>


								</table>
							</div>
						</div>
						<div class="submit">
							<input type="submit" class="button-primary" name="submit" value="Update &raquo;" />
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
<?php
    
    
}
/*
// _cpmp_mt_manage_page() displays the page content for the Test Manage submenu
function _cpmp_mt_manage_page() {
    echo "<h2>Test Manage</h2>";
}
*/

function _cpmp_mt_toplevel_page() {
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/media-platform/start.aspx?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='88%' height='100%'></iframe>";
}

function _cpmp_mt_sublevel_create() {
    //echo "<iframe src='//www.cincopa.com/media-platform/wizard_name.aspx?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='98%' height='2000px'></iframe>";
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/media-platform/start.aspx?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='88%' height='100%'></iframe>";
}

function _cpmp_mt_sublevel_monitor() {
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/media-platform/wizard_edit15?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='88%' height='100%'></iframe>";
}

function _cpmp_mt_sublevel_library() {
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/media-platform/wizard2/library15?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='88%' height='100%'></iframe>";
}

function _cpmp_mt_sublevel_myaccount() {
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/cincopaManager/manageaccount.aspx?ver="._cpmp_plugin_ver()._cpmp_afc()."&src=".urlencode(_cpmp_selfURL())."' width='88%' height='100%'></iframe>";
}

function _cpmp_mt_sublevel_forum() {
    //echo "<iframe src='//www.cincopa.com/redirect_to_help.aspx' width='98%' height='2000px'></iframe>";
    echo "<div style='clear: both;'></div><iframe style='position: fixed; left: 165px;' src='//www.cincopa.com/learning-center' width='88%' height='100%'></iframe>";
}



if (!class_exists('CincopaWidget')) {

	/**
	 * CincopaWidget Class
	 */
	class CincopaWidget extends WP_Widget {
			/** constructor */
			function CincopaWidget() {
					parent::WP_Widget(false, $name = 'Cincopa Gallery Widget');	
			}

			/** @see WP_Widget::widget */
			function widget($args, $instance) {		
					extract( $args );

					if (strpos($instance['galleryid'], 'cincopa'))
						$gallery = _cpmp_plugin($instance['galleryid']);
					else
						$gallery = _cpmp_plugin('[cincopa '.$instance['galleryid'].']');

					echo $gallery;
			}

			/** @see WP_Widget::update */
			function update($new_instance, $old_instance) {				
					return $new_instance;
			}

			/** @see WP_Widget::form */
			function form($instance) {				
					$galleryid = esc_attr($instance['galleryid']);
					?>
	<p>
		<label for=""
			<?php echo $this->get_field_id('galleryid'); ?>"><?php _e('Gallery ID:'); ?> <a target="_blank" href="//help.cincopa.com/entries/405593-how-do-i-add-a-gallery-to-my-wordpress-sidebar">what?</a> <input class="widefat" id=""<?php echo $this->get_field_id('galleryid'); ?>" name="<?php echo $this->get_field_name('galleryid'); ?>" type="text" value="<?php echo $galleryid; ?>" />
		</label>
	</p>
	<?php 
			}

	} // class CincopaWidget

}

// http://www.aaronrussell.co.uk/blog/improving-wordpress-the_excerpt/

function _cpmp_improved_trim_excerpt($text)
{
	global $post;
	if ( '' == $text ) {
		$text = get_the_content('');

		$cincopa_excerpt_rt = get_site_option('CincopaExcerpt');

		if ($cincopa_excerpt_rt == 'clean')
			$text = preg_replace(CINCOPA_REGEXP, '', $text);

		$text = apply_filters('the_content', $text);

		if ($cincopa_excerpt_rt == 'full')
			return $text;

		$text = str_replace(']]>', ']]&gt;', $text);
		$text = preg_replace('@<script[^>]*?>.*?</script>@si', '', $text);

		$text = strip_tags($text, '<'.'p'.'>');
		$excerpt_length = 80;
		$words = explode(' ', $text, $excerpt_length + 1);
		if (count($words)> $excerpt_length) 
		{
			array_pop($words);
			array_push($words, '[...]');
			$text = implode(' ', $words);
		}
	}
			
	return $text;
}

$cincopa_excerpt_rt = get_site_option('CincopaExcerpt');
if ($cincopa_excerpt_rt == 'full' || $cincopa_excerpt_rt == 'clean')
{
	remove_filter('get_the_excerpt', 'wp_trim_excerpt');
	//	remove_all_filters('get_the_excerpt');
	add_filter('get_the_excerpt', '_cpmp_improved_trim_excerpt');
}

function _cpmp_activation_notice()
			{ ?>
			<div id="message" class="updated fade">
				<p style="line-height: 150%">
					<strong>Welcome to Cincopa Rich Media Plugin</strong> - the most popular way to add videos, photo galleries, slideshows, Cooliris gallery, podcast and music to your site.
				</p>
				<p>
					On every post page (above the text box) you'll find this <img src="<?php echo _cpmp_pluginURI() ?>/media-cincopa.gif"  /> icon, click on it to start or use sidebar Widgets (Appearance menu).
				</p>
				<p>
		
<input type="button" class="button" value="Cincopa Options Page" onclick="document.location.href = 'options-general.php?page=cincopaoptions';" />

<input type="button" class="button" value="Hide this message" onclick="document.location.href = 'options-general.php?page=cincopaoptions&amp;hide_note=welcome_notice';" />

<input type="button" class="button" value="Video Tutorial" onclick="window.open('//help.cincopa.com/entries/488838-how-to-use-the-wordpress-plugin');" />

				</p>

			</div>


			<?php

	if (get_site_option('cincopa_installed') != 'true')
	{
		update_site_option('cincopa_installed', 'true');
		echo "<img src='//goo.gl/HqSz' width=0 height=0 />";
	}

}

add_action( 'admin_footer-post-new.php', 'cincopa_mediaDefault_script' );
add_action( 'admin_footer-post.php', 'cincopa_mediaDefault_script' );
add_action( 'admin_footer-index.php', 'cincopa_mediaDefault_script' );

function cincopa_mediaDefault_script()
{
	?>
		<script type="text/javascript">
			var cincopa_popup_timer = null;
			function cincopa_launch_popup()
			{
				cincopa_popup_timer = setInterval(cincopa_check_popup, 200);
			}

			function cincopa_check_popup()
			{
				if (jQuery(".media-menu-item:contains('Cincopa')").closest('.supports-drag-drop').css('display') == 'none') 
					return;
				
				if (jQuery(".media-menu-item:contains('Cincopa')").length > 0)
				{
					jQuery(".media-menu-item:contains('Cincopa')")[0].click();
					clearInterval(cincopa_popup_timer);
				}
			}

		</script>
	<?php
}