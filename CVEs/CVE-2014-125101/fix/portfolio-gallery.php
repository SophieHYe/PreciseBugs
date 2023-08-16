<?php

/*
Plugin Name: Huge IT Portfolio Gallery
Plugin URI: http://huge-it.com/portfolio-gallery
Description: Portfolio Gallery is a great plugin for adding specialized portfolios or gallery to your site. There are various view options for the images to choose from.
Version: 1.1.9
Author: http://huge-it.com/
License: GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/




add_action('media_buttons_context', 'add_portfolio_my_custom_button');


add_action('admin_footer', 'add_portfolio_inline_popup_content');


function add_portfolio_my_custom_button($context) {
  

  $img = plugins_url( '/images/post.button.png' , __FILE__ );
  

  $container_id = 'huge_it_portfolio';
  

  $title = 'Select Huge IT Portfolio Gallery to insert into post';

  $context .= '<a class="button thickbox" title="Select portfolio gallery to insert into post"    href="#TB_inline?width=400&inlineId='.$container_id.'">
		<span class="wp-media-buttons-icon" style="background: url('.$img.'); background-repeat: no-repeat; background-position: left bottom;"></span>
	Add Portfolio Gallery
	</a>';
  
  return $context;
}

function add_portfolio_inline_popup_content() {
?>
<script type="text/javascript">
				jQuery(document).ready(function() {
				  jQuery('#hugeitportfolioinsert').on('click', function() {
				  	var id = jQuery('#huge_it_portfolio-select option:selected').val();
			
				  	window.send_to_editor('[huge_it_portfolio id="' + id + '"]');
					tb_remove();
				  })
				});
</script>

<div id="huge_it_portfolio" style="display:none;">
  <h3>Select Huge IT Portfolio Gallery to insert into post</h3>
  <?php 
  	  global $wpdb;
	  $query="SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios order by id ASC";
			   $shortcodeportfolios=$wpdb->get_results($query);
			   ?>

 <?php 	if (count($shortcodeportfolios)) {
							echo "<select id='huge_it_portfolio-select'>";
							foreach ($shortcodeportfolios as $shortcodeportfolio) {
								echo "<option value='".$shortcodeportfolio->id."'>".$shortcodeportfolio->name."</option>";
							}
							echo "</select>";
							echo "<button class='button primary' id='hugeitportfolioinsert'>Insert portfolio gallery</button>";
						} else {
							echo "No slideshows found", "huge_it_portfolio";
						}
						?>
	
</div>
<?php
}
///////////////////////////////////shortcode update/////////////////////////////////////////////


add_action('init', 'hugesl_portfolio_do_output_buffer');
function hugesl_portfolio_do_output_buffer() {
        ob_start();
}
add_action('init', 'portfolio_lang_load');

function portfolio_lang_load()
{
    load_plugin_textdomain('sp_portfolio', false, basename(dirname(__FILE__)) . '/Languages');

}


function huge_it_portfolio_images_list_shotrcode($atts)
{
    extract(shortcode_atts(array(
        'id' => 'no huge_it portfolio',
    
    ), $atts));




    return huge_it_portfolio_images_list($atts['id']);

}


/////////////// Filter portfolio gallery


function portfolio_after_search_results($query)
{
    global $wpdb;
    if (isset($_REQUEST['s']) && $_REQUEST['s']) {
        $serch_word = htmlspecialchars(($_REQUEST['s']));
        $query = str_replace($wpdb->prefix . "posts.post_content", gen_string_portfolio_search($serch_word, $wpdb->prefix . 'posts.post_content') . " " . $wpdb->prefix . "posts.post_content", $query);
    }
    return $query;

}

add_filter('posts_request', 'portfolio_after_search_results');


function gen_string_portfolio_search($serch_word, $wordpress_query_post)
{
    $string_search = '';

    global $wpdb;
    if ($serch_word) {
        $rows_portfolio = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "huge_itportfolio_portfolios WHERE (description LIKE %s) OR (name LIKE %s)", '%' . $serch_word . '%', "%" . $serch_word . "%"));

        $count_cat_rows = count($rows_portfolio);

        for ($i = 0; $i < $count_cat_rows; $i++) {
            $string_search .= $wordpress_query_post . ' LIKE \'%[huge_it_portfolio id="' . $rows_portfolio[$i]->id . '" details="1" %\' OR ' . $wordpress_query_post . ' LIKE \'%[huge_it_portfolio id="' . $rows_portfolio[$i]->id . '" details="1"%\' OR ';
        }
		
        $rows_portfolio = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "huge_itportfolio_portfolios WHERE (name LIKE %s)","'%" . $serch_word . "%'"));
        $count_cat_rows = count($rows_portfolio);
        for ($i = 0; $i < $count_cat_rows; $i++) {
            $string_search .= $wordpress_query_post . ' LIKE \'%[huge_it_portfolio id="' . $rows_portfolio[$i]->id . '" details="0"%\' OR ' . $wordpress_query_post . ' LIKE \'%[huge_it_portfolio id="' . $rows_portfolio[$i]->id . '" details="0"%\' OR ';
        }

        $rows_single = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "huge_itportfolio_images WHERE name LIKE %s","'%" . $serch_word . "%'"));

        $count_sing_rows = count($rows_single);
        if ($count_sing_rows) {
            for ($i = 0; $i < $count_sing_rows; $i++) {
                $string_search .= $wordpress_query_post . ' LIKE \'%[huge_it_portfolio_Product id="' . $rows_single[$i]->id . '"]%\' OR ';
            }

        }
    }
    return $string_search;
}


///////////////////// end filter


add_shortcode('huge_it_portfolio', 'huge_it_portfolio_images_list_shotrcode');




function   huge_it_portfolio_images_list($id)
{
    require_once("Front_end/portfolio_front_end_view.php");
    require_once("Front_end/portfolio_front_end_func.php");
    if (isset($_GET['product_id'])) {
        if (isset($_GET['view'])) {
            if ($_GET['view'] == 'huge_itportfolio') {
                return showPublishedportfolios_1($id);
            } else {
                return front_end_single_product($_GET['product_id']);
            }
        } else {
            return front_end_single_product($_GET['product_id']);
        }
    } else {
        return showPublishedportfolios_1($id);
    }
}




add_filter('admin_head', 'huge_it_portfolio_ShowTinyMCE');
function huge_it_portfolio_ShowTinyMCE()
{
    // conditions here
    wp_enqueue_script('common');
    wp_enqueue_script('jquery-color');
    wp_print_scripts('editor');
    if (function_exists('add_thickbox')) add_thickbox();
    wp_print_scripts('media-upload');
    if (version_compare(get_bloginfo('version'), 3.3) < 0) {
        if (function_exists('wp_tiny_mce')) wp_tiny_mce();
    }
    wp_admin_css();
    wp_enqueue_script('utils');
    do_action("admin_print_styles-post-php");
    do_action('admin_print_styles');
}


add_action('admin_menu', 'huge_it_portfolio_options_panel');
function huge_it_portfolio_options_panel()
{
    $page_cat = add_menu_page('Theme page title', 'Huge IT Portfolio', 'manage_options', 'portfolios_huge_it_portfolio', 'portfolios_huge_it_portfolio', plugins_url('images/huge_it_portfolioLogoHover -for_menu.png', __FILE__));
    add_submenu_page('portfolios_huge_it_portfolio', 'Portfolios', 'Portfolios', 'manage_options', 'portfolios_huge_it_portfolio', 'portfolios_huge_it_portfolio');
    $page_option = add_submenu_page('portfolios_huge_it_portfolio', 'General Options', 'General Options', 'manage_options', 'Options_portfolio_styles', 'Options_portfolio_styles');
	$lightbox_options = add_submenu_page('portfolios_huge_it_portfolio', 'Lightbox Options', 'Lightbox Options', 'manage_options', 'Options_portfolio_lightbox_styles', 'Options_portfolio_lightbox_styles');
	
	add_submenu_page( 'portfolios_huge_it_portfolio', 'Licensing', 'Licensing', 'manage_options', 'huge_it_portfolio_Licensing', 'huge_it_portfolio_Licensing');

	add_submenu_page('portfolios_huge_it_portfolio', 'Featured Plugins', 'Featured Plugins', 'manage_options', 'huge_it__portfolio_featured_plugins', 'huge_it__portfolio_featured_plugins');

	add_action('admin_print_styles-' . $page_cat, 'huge_it_portfolio_admin_script');
    add_action('admin_print_styles-' . $page_option, 'huge_it_portfolio_option_admin_script');
	add_action('admin_print_styles-' . $lightbox_options, 'huge_it_portfolio_option_admin_script');
}

function huge_it__portfolio_featured_plugins()
{
	include_once("admin/huge_it_featured_plugins.php");
}

function huge_it_portfolio_Licensing(){

	?>
    <div style="width:95%">
    <p>
	This plugin is the non-commercial version of the Huge IT Portfolio / Gallery. If you want to customize to the styles and colors of your website,than you need to buy a license.
Purchasing a license will add possibility to customize the general options and lightbox of the Huge IT Portfolio / Gallery. 

 </p>
<br /><br />
<a href="http://huge-it.com/portfolio-gallery/" class="button-primary" target="_blank">Purchase a License</a>
<br /><br /><br />
<p>After the purchasing the commercial version follow this steps:</p>
<ol>
	<li>Deactivate Huge IT Portfolio / Gallery Plugin</li>
	<li>Delete Huge IT Portfolio / Gallery Plugin</li>
	<li>Install the downloaded commercial version of the plugin</li>
</ol>
</div>
<?php
	}
	
	
function huge_it_portfolio_admin_script()
{
	wp_enqueue_media();
	wp_enqueue_style("jquery_ui", "http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css", FALSE);
	wp_enqueue_style("admin_css", plugins_url("style/admin.style.css", __FILE__), FALSE);
	wp_enqueue_script("admin_js", plugins_url("js/admin.js", __FILE__), FALSE);
}


function huge_it_portfolio_option_admin_script()
{
	wp_enqueue_script("jquery_old", "http://ajax.googleapis.com/ajax/libs/jquery/1.8.1/jquery.min.js", FALSE);
	wp_enqueue_script("simple_slider_js",  plugins_url("js/simple-slider.js", __FILE__), FALSE);
	wp_enqueue_style("simple_slider_css", plugins_url("style/simple-slider.css", __FILE__), FALSE);
	wp_enqueue_style("admin_css", plugins_url("style/admin.style.css", __FILE__), FALSE);
	wp_enqueue_script("admin_js", plugins_url("js/admin.js", __FILE__), FALSE);
	wp_enqueue_script('param_block2', plugins_url("elements/jscolor/jscolor.js", __FILE__));
}


function portfolios_huge_it_portfolio()
{

    require_once("admin/portfolios_func.php");
    require_once("admin/portfolios_view.php");
    if (!function_exists('print_html_nav'))
        require_once("portfolio_function/html_portfolio_func.php");


    if (isset($_GET["task"]))
        $task = $_GET["task"]; 
    else
        $task = '';
    if (isset($_GET["id"]))
        $id = $_GET["id"];
    else
        $id = 0;
    global $wpdb;
    switch ($task) {

        case 'add_cat':
            add_portfolio();
            break;
        case 'edit_cat':
            if ($id)
                editportfolio($id);
            else {
                $id = $wpdb->get_var("SELECT MAX( id ) FROM " . $wpdb->prefix . "huge_itportfolio_portfolios");
                editportfolio($id);
            }
            break;

        case 'save':
            if ($id)
                apply_cat($id);
        case 'apply':
            if ($id) {
                apply_cat($id);
                editportfolio($id);
            } 
            break;
        case 'remove_cat':
            removeportfolio($id);
            showportfolio();
            break;
        default:
            showportfolio();
            break;
    }


}


function Options_portfolio_styles()
{
    require_once("admin/portfolio_Options_func.php");
    require_once("admin/portfolio_Options_view.php");
    if (isset($_GET['task']))
        if ($_GET['task'] == 'save')
            save_styles_options();
    showStyles();
}
function Options_portfolio_lightbox_styles()
{
    require_once("admin/portfolio_lightbox_func.php");
    require_once("admin/portfolio_lightbox_view.php");
    if (isset($_GET['task']))
        if ($_GET['task'] == 'save')
            save_styles_options();
    showStyles();
}


/**
 * Huge IT Widget
 */
class Huge_it_portfolio_Widget extends WP_Widget {


	public function __construct() {
		parent::__construct(
	 		'Huge_it_portfolio_Widget', 
			'Huge IT Portfolio', 
			array( 'description' => __( 'Huge IT Portfolio', 'huge_it_portfolio' ), ) 
		);
	}

	
	public function widget( $args, $instance ) {
		extract($args);

		if (isset($instance['portfolio_id'])) {
			$portfolio_id = $instance['portfolio_id'];

			$title = apply_filters( 'widget_title', $instance['title'] );

			echo $before_widget;
			if ( ! empty( $title ) )
				echo $before_title . $title . $after_title;

			echo do_shortcode("[huge_it_portfolio id={$portfolio_id}]");
			echo $after_widget;
		}
	}


	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['portfolio_id'] = strip_tags( $new_instance['portfolio_id'] );
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}


	public function form( $instance ) {
		$selected_portfolio = 0;
		$title = "";
		$portfolios = false;

		if (isset($instance['portfolio_id'])) {
			$selected_portfolio = $instance['portfolio_id'];
		}

		if (isset($instance['title'])) {
			$title = $instance['title'];
		}

        

        
		?>
		<p>
			
				<p>
					<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
					<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
				</p>
				<label for="<?php echo $this->get_field_id('portfolio_id'); ?>"><?php _e('Select portfolio:', 'huge_it_portfolio'); ?></label> 
				<select id="<?php echo $this->get_field_id('portfolio_id'); ?>" name="<?php echo $this->get_field_name('portfolio_id'); ?>">
				
				<?php
				 global $wpdb;
				$query="SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios ";
				$rowwidget=$wpdb->get_results($query);
				foreach($rowwidget as $rowwidgetecho){
				?>
					<option <?php if($rowwidgetecho->id == $instance['portfolio_id']){ echo 'selected'; } ?> value="<?php echo $rowwidgetecho->id; ?>"><?php echo $rowwidgetecho->name; ?></option>

					<?php } ?>
				</select>

		</p>
		<?php 
	}
}

add_action('widgets_init', 'register_Huge_it_portfolio_Widget');  

function register_Huge_it_portfolio_Widget() {  
    register_widget('Huge_it_portfolio_Widget'); 
}



//////////////////////////////////////////////////////                                             ///////////////////////////////////////////////////////
//////////////////////////////////////////////////////               Activate portfolio gallery                    ///////////////////////////////////////////////////////
//////////////////////////////////////////////////////                                             ///////////////////////////////////////////////////////
//////////////////////////////////////////////////////                                             ///////////////////////////////////////////////////////


function huge_it_portfolio_activate()
{
    global $wpdb;

/// creat database tables



    $sql_huge_itportfolio_params = "
CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "huge_itportfolio_params`(
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `title` varchar(200) CHARACTER SET utf8 NOT NULL,
  `description` text CHARACTER SET utf8 NOT NULL,
  `value` varchar(200) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=200 ";


    $sql_huge_itportfolio_images = "
CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "huge_itportfolio_images` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `portfolio_id` varchar(200) DEFAULT NULL,
  `description` text,
  `image_url` text,
  `sl_url` varchar(128) DEFAULT NULL,
  `sl_type` text NOT NULL,
  `link_target` text NOT NULL,
  `ordering` int(11) NOT NULL,
  `published` tinyint(4) unsigned DEFAULT NULL,
  `published_in_sl_width` tinyint(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5";

    $sql_huge_itportfolio_portfolios = "
CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "huge_itportfolio_portfolios` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `sl_height` int(11) unsigned DEFAULT NULL,
  `sl_width` int(11) unsigned DEFAULT NULL,
  `pause_on_hover` text,
  `portfolio_list_effects_s` text,
  `description` text,
  `param` text,
  `sl_position` text NOT NULL,
  `ordering` int(11) NOT NULL,
  `published` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ";




    $table_name = $wpdb->prefix . "huge_itportfolio_params";
    $sql_1 = <<<query1
INSERT INTO `$table_name` (`name`, `title`,`description`, `value`) VALUES

/*############################## VIEW 0 #####################################*/

('ht_view0_togglebutton_style', 'Toggle Button Style', 'Toggle Button Style','dark'),
('ht_view0_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view0_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view0_show_linkbutton', 'Show Link Button', 'Show Link Button', 'on'),
('ht_view0_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', 'df2e1b'),
('ht_view0_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', 'e74c3c'),
('ht_view0_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view0_linkbutton_color', 'Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view0_linkbutton_font_size', 'Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view0_description_color', 'Description Font Color', 'Description Font Color', '5b5b5b'),
('ht_view0_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view0_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view0_thumbs_width', 'Thumbnails Width', 'Thumbnails Width', '75'),
('ht_view0_thumbs_position', 'Thumbnails Position', 'Thumbnails Position', 'before'),
('ht_view0_show_thumbs', 'Show Thumbnails', 'Show Thumbnails', 'on'),
('ht_view0_title_font_size', 'Title Font Size', 'Title Font Size', '15'),
('ht_view0_title_font_color', 'Title Font Color', 'Title Font Color', '555555'),
('ht_view0_element_border_width', 'Element Border Width', 'Element Border Width', '1'),
('ht_view0_element_border_color', 'Element Border Color', 'Element Border Color', 'D0D0D0'),
('ht_view0_element_background_color', 'Element Background Color', 'Element Background Color', 'f7f7f7'),
('ht_view0_block_width', 'Block Width', 'Block Width', '275'),
('ht_view0_block_height', 'Block Height', 'Block Height', '160'),


/*############################## VIEW 1 #####################################*/

('ht_view1_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view1_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view1_show_linkbutton', 'Show Link Button', 'Show Link Button', 'on'),
('ht_view1_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', 'df2e1b'),
('ht_view1_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', 'e74c3c'),
('ht_view1_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view1_linkbutton_color', 'Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view1_linkbutton_font_size', 'Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view1_description_color', 'Description Font Color', 'Description Font Color', '5b5b5b'),
('ht_view1_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view1_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view1_thumbs_width', 'Thumbnails Width', 'Thumbnails Width', '75'),
('ht_view1_thumbs_position', 'Thumbnails Position', 'Thumbnails Position', 'before'),
('ht_view1_show_thumbs', 'Show Thumbnails', 'Show Thumbnails', 'on'),
('ht_view1_title_font_size', 'Title Font Size', 'Title Font Size', '15'),
('ht_view1_title_font_color', 'Title Font Color', 'Title Font Color', '555555'),
('ht_view1_element_border_width', 'Element Border Width', 'Element Border Width', '1'),
('ht_view1_element_border_color', 'Element Border Color', 'Element Border Color', 'D0D0D0'),
('ht_view1_element_background_color', 'Element Background Color', 'Element Background Color', 'f7f7f7'),
('ht_view1_block_width', 'Block Width', 'Block Width', '275'),



/*############################## VIEW 2 Popup #####################################*/

('ht_view2_element_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view2_element_show_linkbutton', 'Show Link Button On Element', 'Show Link Button On Element', 'on'),
('ht_view2_element_linkbutton_color', 'Element Link Button Font Color', 'Element Link Button Font Color', 'ffffff'),
('ht_view2_element_linkbutton_font_size', 'Element Link Button Font Size', 'Element Link Button Font Size', '14'),
('ht_view2_element_linkbutton_background_color', 'Element Link Button Background Color', 'Element Link Button Background Color', '2ea2cd'),
('ht_view2_show_popup_linkbutton', 'Show Link Button On Popup', 'Show Link Button On Popup', 'on'),
('ht_view2_popup_linkbutton_text', 'Popup Link Button Text', 'Link Button Text', 'View More'),
('ht_view2_popup_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', '0074a2'),
('ht_view2_popup_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', '2ea2cd'),
('ht_view2_popup_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view2_popup_linkbutton_color', 'Element Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view2_popup_linkbutton_font_size', 'Element Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view2_description_color', 'Description Font Color', 'Description Font Color', '222222'),
('ht_view2_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view2_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view2_thumbs_width', 'Thumbnails Width', 'Thumbnails Width', '75'),
('ht_view2_thumbs_height', 'Thumbnails Height', 'Thumbnails Height', '75'),
('ht_view2_thumbs_position', 'Thumbnails Position', 'Thumbnails Position', 'before'),
('ht_view2_show_thumbs', 'Show Thumbnails', 'Show Thumbnails', 'on'),
('ht_view2_popup_background_color', 'Popup Background Color', 'Popup Background Color', 'FFFFFF'),
('ht_view2_popup_overlay_color', 'Popup Overlay Color', 'Popup Overlay Color', '000000'),
('ht_view2_popup_overlay_transparency_color', 'Popup Overlay Transparency', 'Popup Overlay Transparency ', '70'),
('ht_view2_popup_closebutton_style', 'Popup Close Button Style', 'Popup Close Button Style', 'dark'),
('ht_view2_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view2_show_popup_title', 'Show Popup Title', 'Show Popup Title','on'),
('ht_view2_element_title_font_size', 'Element Title Font Size', 'Element Title Font Size', '18'),
('ht_view2_element_title_font_color', 'Element Title Font Color', 'Element Title Font Color', '222222'),
('ht_view2_popup_title_font_size', 'Popup Title Font Size', 'Popup Title Font Size', '18'),
('ht_view2_popup_title_font_color', 'Popup Title Font Color', 'Popup Title Font Color', '222222'),
('ht_view2_element_overlay_color', 'Element Overlay Color', 'Element Overlay Color', 'FFFFFF'),
('ht_view2_element_overlay_transparency', 'Element Overlay Transparency', 'Element Overlay Transparency ', '70'),
('ht_view2_zoombutton_style', 'Zoom Button Style', 'Zoom Button Style','light'),
('ht_view2_element_border_width', 'Element Border Width', 'Element Border Width', '1'),
('ht_view2_element_border_color', 'Element Border Color', 'Element Border Color', 'dedede'),
('ht_view2_element_background_color', 'Element Background Color', 'Element Background Color', 'f9f9f9'),
('ht_view2_element_width', 'Block Width', 'Block Width', '275'),
('ht_view2_element_height', 'Block Height', 'Block Height', '160'),


/*############################## VIEW 3 Fullwidth #####################################*/

('ht_view3_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view3_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view3_show_linkbutton', 'Show Link Button', 'Show Link Button', 'on'),
('ht_view3_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', '0074a2'),
('ht_view3_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', '2ea2cd'),
('ht_view3_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view3_linkbutton_color', 'Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view3_linkbutton_font_size', 'Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view3_description_color', 'Description Font Color', 'Description Font Color', '555555'),
('ht_view3_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view3_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view3_thumbs_width', 'Thumbnails Width', 'Thumbnails Width', '75'),
('ht_view3_thumbs_height', 'Thumbnails Height', 'Thumbnails Hight', '75'),
('ht_view3_show_thumbs', 'Show Thumbnails', 'Show Thumbnails', 'on'),
('ht_view3_title_font_size', 'Title Font Size', 'Title Font Size', '18'),
('ht_view3_title_font_color', 'Title Font Color', 'Title Font Color', '0074a2'),
('ht_view3_mainimage_width', 'Main Image Width', 'Main Image Width', '240'),
('ht_view3_element_border_width', 'Element Border Width', 'Element Border Width', '1'),
('ht_view3_element_border_color', 'Element Border Color', 'Element Border Color', 'dedede'),
('ht_view3_element_background_color', 'Element Background Color', 'Element Background Color', 'f9f9f9'),




/*############################## VIEW 4 FAQ #####################################*/

('ht_view4_togglebutton_style', 'Toggle Button Style', 'Toggle Button Style','dark'),
('ht_view4_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view4_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view4_show_linkbutton', 'Show Link Button', 'Show Link Button', 'on'),
('ht_view4_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', 'df2e1b'),
('ht_view4_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', 'e74c3c'),
('ht_view4_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view4_linkbutton_color', 'Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view4_linkbutton_font_size', 'Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view4_description_color', 'Description Font Color', 'Description Font Color', '555555'),
('ht_view4_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view4_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view4_title_font_size', 'Title Font Size', 'Title Font Size', '18'),
('ht_view4_title_font_color', 'Title Font Color', 'Title Font Color', 'E74C3C'),
('ht_view4_element_border_width', 'Element Border Width', 'Element Border Width', '1'),
('ht_view4_element_border_color', 'Element Border Color', 'Element Border Color', 'dedede'),
('ht_view4_element_background_color', 'Element Background Color', 'Element Background Color', 'f9f9f9'),
('ht_view4_block_width', 'Block Width', 'Block Width', '275'),

/*############################## VIEW 5 SLIDER #####################################*/

('ht_view5_icons_style', 'Icons Style', 'Icons Style','dark'),
('ht_view5_show_separator_lines', 'Show Separator Lines', 'Show Separator Lines','on'),
('ht_view5_linkbutton_text', 'Link Button Text', 'Link Button Text', 'View More'),
('ht_view5_show_linkbutton', 'Show Link Button', 'Show Link Button', 'on'),
('ht_view5_linkbutton_background_hover_color', 'Link Button Background Hover Color', 'Link Button Background Hover Color', '0074a2'),
('ht_view5_linkbutton_background_color', 'Link Button Background Color', 'Link Button Background Color', '2ea2cd'),
('ht_view5_linkbutton_font_hover_color', 'Link Button Font Hover Color', 'Link Button Font Hover Color', 'ffffff'),
('ht_view5_linkbutton_color', 'Link Button Font Color', 'Link Button Font Color', 'ffffff'),
('ht_view5_linkbutton_font_size', 'Link Button Font Size', 'Link Button Font Size', '14'),
('ht_view5_description_color', 'Description Font Color', 'Description Font Color', '555555'),
('ht_view5_description_font_size', 'Description Font Size', 'Description Font Size', '14'),
('ht_view5_show_description', 'Show Description', 'Show Description', 'on'),
('ht_view5_thumbs_width', 'Thumbnails Width', 'Thumbnails Width', '75'),
('ht_view5_thumbs_height', 'Thumbnails Height', 'Thumbnails Hight', '75'),
('ht_view5_show_thumbs', 'Show Thumbnails', 'Show Thumbnails', 'on'),
('ht_view5_title_font_size', 'Title Font Size', 'Title Font Size', '16'),
('ht_view5_title_font_color', 'Title Font Color', 'Title Font Color', '0074a2'),
('ht_view5_main_image_width', 'Main Image Width', 'Main Image Width', '275'),
('ht_view5_slider_tabs_font_color', 'Slider Tabs Font Color', 'Slider Tabs Font Color', 'd9d99'),
('ht_view5_slider_tabs_background_color', 'Slider Tabs Background Color', 'Slider Tabs Background Color', '555555'),
('ht_view5_slider_background_color', 'Slider Background Color', 'Slider Background Color', 'f9f9f9'),

/*############################## VIEW 6 Lightbox-gallery #####################################*/

('ht_view6_title_font_size', 'Title Font Size', 'Title Font Size', '16'),
('ht_view6_title_font_color', 'Title Font Color', 'Title Font Color', '0074A2'),
('ht_view6_title_font_hover_color', 'Title Font Hover Color', 'Title Font Hover Color', '2EA2CD'),
('ht_view6_title_background_color', 'Title Background Color', 'Title Background Color', '000000'),
('ht_view6_title_background_transparency', 'Title Background Transparency', 'Title Background Transparency', '80'),
('ht_view6_border_radius', 'Image Border Radius', 'Image Border Radius', '3'),
('ht_view6_border_width', 'Image Border Width', 'Image Border Width', '0'),
('ht_view6_border_color', 'Image Border Color', 'Image Border Color', 'eeeeee'),
('ht_view6_width', 'Image Width', 'Image Width', '275');


query1;

    $table_name = $wpdb->prefix . "huge_itportfolio_images";
    $sql_2 = "
INSERT INTO 

`" . $table_name . "` (`id`, `name`, `portfolio_id`, `description`, `image_url`, `sl_url`, `sl_type`, `link_target`, `ordering`, `published`, `published_in_sl_width`) VALUES
(1, 'Cutthroat & Cavalier', '1', '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/1.jpg", __FILE__).";".plugins_url("Front_images/projects/1.1.jpg", __FILE__).";".plugins_url("Front_images/projects/1.2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 0, 1, NULL),
(2, 'Cone Music', '1', '<ul><li>lorem ipsumdolor sit amet</li><li>lorem ipsum dolor sit amet</li></ul><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/5.jpg", __FILE__).";".plugins_url("Front_images/projects/5.1.jpg", __FILE__).";".plugins_url("Front_images/projects/5.2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 1, 1, NULL),
(3, 'Nexus', '1', '<h6>Lorem Ipsum </h6><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><ul><li>lorem ipsum</li><li>dolor sit amet</li><li>lorem ipsum</li><li>dolor sit amet</li></ul>', '".plugins_url("Front_images/projects/3.jpg", __FILE__).";".plugins_url("Front_images/projects/3.1.jpg", __FILE__).";".plugins_url("Front_images/projects/3.2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 2, 1, NULL),
(4, 'De7igner', '1', '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><h7>Dolor sit amet</h7><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/4.jpg", __FILE__).";".plugins_url("Front_images/projects/4.1.jpg", __FILE__).";".plugins_url("Front_images/projects/4.2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 3, 1, NULL),
(5, 'Autumn / Winter Collection', '1', '<h6>Lorem Ipsum</h6><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 4, 1, NULL),
(6, 'Retro Headphones', '1', '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/6.jpg", __FILE__).";".plugins_url("Front_images/projects/6.1.jpg", __FILE__).";".plugins_url("Front_images/projects/6.2.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 5, 1, NULL),
(7, 'Take Fight', '1', '<h6>Lorem Ipsum</h6><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>', '".plugins_url("Front_images/projects/7.jpg", __FILE__).";".plugins_url("Front_images/projects/7.2.jpg", __FILE__).";".plugins_url("Front_images/projects/7.3.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 6, 1, NULL),
(8, 'The Optic', '1', '<h6>Lorem Ipsum </h6><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p><p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><ul><li>lorem ipsum</li><li>dolor sit amet</li><li>lorem ipsum</li><li>dolor sit amet</li></ul>', '".plugins_url("Front_images/projects/8.jpg", __FILE__).";".plugins_url("Front_images/projects/8.1.jpg", __FILE__).";".plugins_url("Front_images/projects/8.3.jpg", __FILE__).";', 'http://huge-it.com/fields/order-website-maintenance/', 'image', 'on', 7, 1, NULL)";



    $table_name = $wpdb->prefix . "huge_itportfolio_portfolios";


    $sql_3 = "

INSERT INTO `$table_name` (`id`, `name`, `sl_height`, `sl_width`, `pause_on_hover`, `portfolio_list_effects_s`, `description`, `param`, `sl_position`, `ordering`, `published`) VALUES
(1, 'My First Portfolio', 375, 600, 'on', '2', '4000', '1000', 'center', 1, '300')";


    $wpdb->query($sql_huge_itportfolio_params);
    $wpdb->query($sql_huge_itportfolio_images);
    $wpdb->query($sql_huge_itportfolio_portfolios);


    if (!$wpdb->get_var("select count(*) from " . $wpdb->prefix . "huge_itportfolio_params")) {
        $wpdb->query($sql_1);
    }
    if (!$wpdb->get_var("select count(*) from " . $wpdb->prefix . "huge_itportfolio_images")) {
      $wpdb->query($sql_2);
    }
    if (!$wpdb->get_var("select count(*) from " . $wpdb->prefix . "huge_itportfolio_portfolios")) {
      $wpdb->query($sql_3);
    }

			///////////////////////////update////////////////////////////////////
    $table_name = $wpdb->prefix . "huge_itportfolio_params";
	    $sql_update_p1 = <<<query1
INSERT INTO `$table_name` (`name`, `title`,`description`, `value`) VALUES
('light_box_size', 'Light box size', 'Light box size', '17'),
('light_box_width', 'Light Box width', 'Light Box width', '500'),
('light_box_transition', 'Light Box Transition', 'Light Box Transition', 'elastic'),
('light_box_speed', 'Light box speed', 'Light box speed', '800'),
('light_box_href', 'Light box href', 'Light box href', 'False'),
('light_box_title', 'Light box Title', 'Light box Title', 'false'),
('light_box_scalephotos', 'Light box scalePhotos', 'Light box scalePhotos', 'true'),
('light_box_rel', 'Light Box rel', 'Light Box rel', 'false'),
('light_box_scrolling', 'Light box Scrollin', 'Light box Scrollin', 'false'),
('light_box_opacity', 'Light box Opacity', 'Light box Opacity', '20'),
('light_box_open', 'Light box Open', 'Light box Open', 'false'),
('light_box_overlayclose', 'Light box overlayClose', 'Light box overlayClose', 'true'),
('light_box_esckey', 'Light box escKey', 'Light box escKey', 'false'),
('light_box_arrowkey', 'Light box arrowKey', 'Light box arrowKey', 'false'),
('light_box_loop', 'Light box loop', 'Light box loop', 'true'),
('light_box_data', 'Light box data', 'Light box data', 'false'),
('light_box_classname', 'Light box className', 'Light box className', 'false'),
('light_box_fadeout', 'Light box fadeOut', 'Light box fadeOut', '300'),
('light_box_closebutton', 'Light box closeButton', 'Light box closeButton', 'false'),
('light_box_current', 'Light box current', 'Light box current', 'image'),
('light_box_previous', 'Light box previous', 'Light box previous', 'previous'),
('light_box_next', 'Light box next', 'Light box next', 'next'),
('light_box_close', 'Light box close', 'Light box close', 'close'),
('light_box_iframe', 'Light box iframe', 'Light box iframe', 'false'),
('light_box_inline', 'Light box inline', 'Light box inline', 'false'),
('light_box_html', 'Light box html', 'Light box html', 'false'),
('light_box_photo', 'Light box photo', 'Light box photo', 'false'),
('light_box_height', 'Light box height', 'Light box height', '500'),
('light_box_innerwidth', 'Light box innerWidth', 'Light box innerWidth', 'false'),
('light_box_innerheight', 'Light box innerHeight', 'Light box innerHeight', 'false'),
('light_box_initialwidth', 'Light box initialWidth', 'Light box initialWidth', '300'),
('light_box_initialheight', 'Light box initialHeight', 'Light box initialHeight', '100'),
('light_box_maxwidth', 'Light box maxWidth', 'Light box maxWidth', '768'),
('light_box_maxheight', 'Light box maxHeight', 'Light box maxHeight', '500'),
('light_box_slideshow', 'Light box slideshow', 'Light box slideshow', 'false'),
('light_box_slideshowspeed', 'Light box slideshowSpeed', 'Light box slideshowSpeed', '2500'),
('light_box_slideshowauto', 'Light box slideshowAuto', 'Light box slideshowAuto', 'true'),
('light_box_slideshowstart', 'Light box slideshowStart', 'Light box slideshowStart', 'start slideshow'),
('light_box_slideshowstop', 'Light box slideshowStop', 'Light box slideshowStop', 'stop slideshow'),
('light_box_fixed', 'Light box fixed', 'Light box fixed', 'true'),
('light_box_top', 'Light box top', 'Light box top', 'false'),
('light_box_bottom', 'Light box bottom', 'Light box bottom', 'false'),
('light_box_left', 'Light box left', 'Light box left', 'false'),
('light_box_right', 'Light box right', 'Light box right', 'false'),
('light_box_reposition', 'Light box reposition', 'Light box reposition', 'false'),
('light_box_retinaimage', 'Light box retinaImage', 'Light box retinaImage', 'true'),
('light_box_retinaurl', 'Light box retinaUrl', 'Light box retinaUrl', 'false'),
('light_box_retinasuffix', 'Light box retinaSuffix', 'Light box retinaSuffix', '@2x.$1'),
('light_box_returnfocus', 'Light box returnFocus', 'Light box returnFocus', 'true'),
('light_box_trapfocus', 'Light box trapFocus', 'Light box trapFocus', 'true'),
('light_box_fastiframe', 'Light box fastIframe', 'Light box fastIframe', 'true'),
('light_box_preloading', 'Light box preloading', 'Light box preloading', 'true'),
('slider_title_position', 'Slider title position', 'Slider title position', '5'),
('light_box_style', 'Light Box style', 'Light Box style', '1'),
('light_box_size_fix', 'Light Box size fix style', 'Light Box size fix style', 'false');

query1;


	global $wpdb;
	$query="SELECT name FROM ".$wpdb->prefix."huge_itportfolio_params";
	$update_p1=$wpdb->get_results($query);
	if(end($update_p1)->name=='ht_view6_width'){
		$wpdb->query($sql_update_p1);
	}

}


register_activation_hook(__FILE__, 'huge_it_portfolio_activate');





