<?php
/*
Plugin Name: Watu
Plugin URI: http://calendarscripts.info/watu-wordpress.html
Description: Create exams and quizzes and display the result immediately after the user takes the exam. Watu for Wordpress is a light version of <a href="http://calendarscripts.info/watupro/" target="_blank">WatuPRO</a>. Check it if you want to run fully featured exams with data exports, student logins, timers, random questions and more. Free support and upgrades are available. Go to <a href="options-general.php?page=watu.php">Watu Settings</a> or <a href="tools.php?page=watu_exams">Manage Your Exams</a> 

Version: 2.6.7
Author: Kiboko Labs
License: GPLv2 or later

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define( 'WATU_PATH', dirname( __FILE__ ) );
define( 'WATU_URL', plugin_dir_url( __FILE__ ));
include( WATU_PATH.'/controllers/exam.php');
include( WATU_PATH.'/controllers/questions.php');
include( WATU_PATH.'/controllers/takings.php');
include( WATU_PATH.'/controllers/ajax.php');
include( WATU_PATH.'/controllers/grades.php');
include( WATU_PATH.'/controllers/social-sharing.php');
include( WATU_PATH.'/models/question.php');
include( WATU_PATH.'/lib/functions.php');
include( WATU_PATH.'/lib/text-captcha.php');
include( WATU_PATH."/models/exam.php");

function watu_init() {
	global $wpdb;
	load_plugin_textdomain('watu', false, dirname( plugin_basename( __FILE__ )).'/langs/' );
	
	$version = get_bloginfo('version');
	if($version <= 3.3) add_action('wp_enqueue_scripts', 'watu_vc_scripts');
	add_action('admin_enqueue_scripts', 'watu_vc_scripts');
	add_action('wp_enqueue_scripts', 'watu_vc_jquery');	
	
	add_shortcode( 'WATU', 'watu_shortcode' );
	add_shortcode( 'watu', 'watu_shortcode' );
	add_shortcode( 'watushare-buttons', array('WatuSharing', 'display') );
	
	// table names as constants
	define('WATU_EXAMS', $wpdb->prefix.'watu_master');	
	define('WATU_QUESTIONS', $wpdb->prefix.'watu_question');
	define('WATU_ANSWERS', $wpdb->prefix.'watu_answer');
	define('WATU_GRADES', $wpdb->prefix.'watu_grading');
	define('WATU_TAKINGS', $wpdb->prefix.'watu_takings');
	
	// which filter to use
	$content_filter = get_option('watu_use_the_content') ? 'the_content' : 'watu_content';
	define('WATU_CONTENT_FILTER', $content_filter);
	
	// add_filter( 'watu_content', 'watu_autop' );	
	add_filter( 'watu_content', 'wptexturize' );
	add_filter( 'watu_content', 'convert_smilies' );
	add_filter( 'watu_content', 'convert_chars' );
	add_filter( 'watu_content', 'shortcode_unautop' );
	add_filter( 'watu_content', 'do_shortcode' );	
	
	// Compatibility with specific plugins
	// qTranslate
	if(function_exists('qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage')) add_filter('watu_content', 'qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage');	
	// WP QuickLaTeX
	if(function_exists('quicklatex_parser')) add_filter( 'watu_content',  'quicklatex_parser', 7);
	
	$version = get_option('watu_version');
	if($version != '2.58') watu_activate(true);
	
	add_action('admin_notices', 'watu_admin_notice');
}

function watu_autop($content) {
	//return wpautop($content, false);
	return nl2br($content);
}

/**
 * Add a new menu under Manage, visible for all users with template viewing level.
 */
add_action( 'admin_menu', 'watu_add_menu_links' );
add_action ( 'watu_exam', 'watu_exam' );
function watu_add_menu_links() {
	global $wp_version, $_registered_pages;
	$view_level= 'manage_options';
	$page = 'tools.php';
	//add_menu_page('Watu Settings Page', 'Watu Settings', $view_level, 'watu', 'watu_options');	$page = 'watu';
	
	add_submenu_page($page, __('Manage Quizzes', 'watu'), __('Watu Quizzes', 'watu'), $view_level , 'watu_exams', 'watu_exams');
	
	// hidden pages
	add_submenu_page(NULL, __('Manage Quizzes', 'watu'), __('Watu Quizzes', 'watu'), $view_level , 'watu_exam', 'watu_exam');
	add_submenu_page(NULL, __('Manage Questions', 'watu'), __('Manage Questions', 'watu'), $view_level , 'watu_questions', 'watu_questions');
	add_submenu_page(NULL, __('Add/Edit Question', 'watu'), __('Add/Edit Question', 'watu'), $view_level , 'watu_question', 'watu_question');
	add_submenu_page(NULL, __('Manage Grades', 'watu'), __('Manage Grades', 'watu'), $view_level , 'watu_grades', 'watu_grades');
	add_submenu_page(NULL, __('Social Sharing', 'watu'), __('Social Sharing', 'watu'), $view_level , 'watu_social_sharing', array('WatuSharing', 'options'));
	
	$code_pages = array('question_form.php');
	foreach($code_pages as $code_page) {
		$hookname = get_plugin_page_hookname("watu/$code_page", '' );
		$_registered_pages[$hookname] = true;
	}
}

/// Add an option page for watu
add_action('admin_menu', 'watu_option_page');
function watu_option_page() {
	add_options_page(__('Watu Settings', 'watu'), __('Watu Settings', 'watu'), 'administrator', basename(__FILE__), 'watu_options');
	
	add_submenu_page(NULL, __('Exam submissions', 'watu'), __('Exam submissions', 'watu'), 'manage_options', 'watu_takings', 'watu_takings'); 
}

function watu_options() {
	if ( function_exists('current_user_can') && !current_user_can('manage_options') ) die(__("Your are not allowed to to perform this operation", 'watu'));
	if (! user_can_access_admin_page()) wp_die( __('You do not have sufficient permissions to access this page', 'watu') );

	require(ABSPATH. '/wp-content/plugins/watu/options.php');
}

/**
 * This will scan all the content pages that wordpress outputs for our special code. If the code is found, it will replace the requested quiz.
 */
function watu_shortcode( $attr ) {
	$exam_id = $attr[0];

	$contents = '';
	if(!is_numeric($exam_id)) return $contents;
	
	watu_vc_scripts();
	
	// submitting without ajax?	
	if(!empty($_POST['no_ajax']) and !empty($exam->no_ajax)) {		
		require(WATU_PATH."/show_exam.php");
		$contents = ob_get_clean();
		$contents = apply_filters('watu_content', $contents);
		return $contents;
	}	
	
	ob_start();
	include(WATU_PATH . '/controllers/show_exam.php');
	$contents = ob_get_contents();
	ob_end_clean();
	
	return $contents;
}

add_action('activate_watu/watu.php','watu_activate');
function watu_activate($update = false) {
	global $wpdb;
	
	$wpdb-> show_errors();
	$version = get_option('watu_version');
	if(!$update) watu_init();
	
	// Initial options.
	update_option('watu_show_answers', 1);
	update_option('watu_single_page', 0);
	update_option('watu_answer_type', 'radio');
	
	if($wpdb->get_var("SHOW TABLES LIKE '".WATU_EXAMS."'") != WATU_EXAMS) {
		$sql = "CREATE TABLE `".WATU_EXAMS."`(
					ID int(11) unsigned NOT NULL auto_increment,
					name varchar(50) NOT NULL DEFAULT '',
					description mediumtext NOT NULL,
					final_screen mediumtext NOT NULL,
					added_on datetime NOT NULL DEFAULT '1900-01-01',
					PRIMARY KEY  (ID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 ";
		$wpdb->query($sql);
	}		
	
	if($wpdb->get_var("SHOW TABLES LIKE '".WATU_QUESTIONS."'") != WATU_QUESTIONS) {
		$sql = "CREATE TABLE ".WATU_QUESTIONS." (
					ID int(11) unsigned NOT NULL auto_increment,
					exam_id int(11) unsigned NOT NULL DEFAULT 0,
					question mediumtext NOT NULL,
					answer_type char(15)  NOT NULL DEFAULT '',
					sort_order int(3) NOT NULL default 0,
					PRIMARY KEY  (ID),
					KEY quiz_id (exam_id)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$wpdb->query($sql);
	}		
	
	if($wpdb->get_var("SHOW TABLES LIKE '".WATU_ANSWERS."'") != WATU_ANSWERS) {
		$sql = "CREATE TABLE ".WATU_ANSWERS." (
					ID int(11) unsigned NOT NULL auto_increment,
					question_id int(11) unsigned NOT NULL,
					answer TEXT,
					correct enum('0','1') NOT NULL default '0',
					point int(11) NOT NULL,
					sort_order int(3) NOT NULL default 0,
					PRIMARY KEY  (ID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$wpdb->query($sql);
	}					
			
	if($wpdb->get_var("SHOW TABLES LIKE '".WATU_GRADES."'") != WATU_GRADES) {
		$sql = "CREATE TABLE `".WATU_GRADES."` (
				 `ID` int(11) NOT NULL AUTO_INCREMENT,
				 `exam_id` int(11) NOT NULL DEFAULT 0,
				 `gtitle` varchar (255) NOT NULL DEFAULT '',
				 `gdescription` mediumtext NOT NULL,
				 `gfrom` int(11) NOT NULL DEFAULT 0,
				 `gto` int(11) NOT NULL DEFAULT 0,
				 PRIMARY KEY (`ID`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$wpdb->query($sql);
	}					
	
	if($wpdb->get_var("SHOW TABLES LIKE '".WATU_TAKINGS."'") != WATU_TAKINGS) {
		$sql = "CREATE TABLE `".WATU_TAKINGS."` (
				 `ID` int(11) NOT NULL AUTO_INCREMENT,
				 `exam_id` int(11) NOT NULL DEFAULT 0,
				 `user_id` int(11) NOT NULL DEFAULT 0,
				 `ip` varchar(20) NOT NULL DEFAULT '',
				 `date` DATE NOT NULL DEFAULT '1900-01-01',
				 `points` INT NOT NULL DEFAULT 0,
				 `grade_id` INT UNSIGNED NOT NULL DEFAULT 0,
				 PRIMARY KEY (`ID`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$wpdb->query($sql);
	}	
	
	watu_add_db_fields(array(
		array("name"=>"randomize", "type"=>"TINYINT NOT NULL DEFAULT 0"),		
		array("name"=>"single_page", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"show_answers", "type"=>"TINYINT NOT NULL DEFAULT 100"),
		array("name"=>"require_login", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"notify_admin", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"randomize_answers", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"pull_random", "type"=>"INT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"dont_store_data", "type"=>"TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"show_prev_button", "type"=>"TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"dont_display_question_numbers", "type"=>"TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"require_text_captcha", "type"=>"TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"email_output", "type"=>"TEXT"),
		array("name"=>"notify_user", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"notify_email", "type"=>"VARCHAR(255) NOT NULL DEFAULT ''"),
		array("name"=>"take_again", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"times_to_take", "type"=>"TINYINT NOT NULL DEFAULT 0"),
		array("name"=>"no_ajax", "type"=>"TINYINT NOT NULL DEFAULT 0"), /* don't use Ajax when submitting this quiz */
	), WATU_EXAMS);	
	
	
	// db updates in 1.8
	if(empty($version) or $version < 1.8) {
		 // let all existing exams follow the default option
		 $sql = "UPDATE ".WATU_EXAMS." SET single_page = '".get_option('watu_single_page')."'";
		 $wpdb->query($sql);
	}
	
	watu_add_db_fields(array(
		array("name"=>"is_required", "type"=>"TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		array("name"=>"feedback", "type"=>"TEXT")	
	), WATU_QUESTIONS);	
	
	watu_add_db_fields(array(
		array("name"=>"result", "type"=>"TEXT"),
		array("name"=>"snapshot", "type"=>"MEDIUMTEXT"),
		array("name"=>"start_time", "type"=>"DATETIME")
	), WATU_TAKINGS);			
	
	// let's change choice and answer fields to TEXT instead of VARCHAR - 2.1.3	
	if(empty($version) or $version < 2.1) {
		 // let all existing exams follow the default option
		 $sql = "ALTER TABLE ".WATU_ANSWERS." CHANGE answer answer TEXT";
		 $wpdb->query($sql);
	}	
	
	$demo_quiz_created = get_option('watu_demo_quiz_created');
	if($demo_quiz_created != '1') WatuExam :: create_demo();
	
	// versions before 2.44 had the following text hard-coded. We need to make sure thta the quizzes which were showing results
	// now have it in the "final screen"
	if(!empty($version) and $version < 2.45) {
		$answers_text = "<p>" . __('All the questions in the quiz along with their answers are shown below. Your answers are bolded. The correct answers have a green background while the incorrect ones have a red background.', 'watu') . "</p> %%ANSWERS%%";
		$wpdb->query("UPDATE ".WATU_EXAMS." SET final_screen = CONCAT(final_screen, '$answers_text') WHERE show_answers=1");
	}
	
	// let all quizzes prior to DB version 2.49 have dont_display_question_numbers = 1 because this is how it was
	if(!empty($version) and $version < 2.49) {
		$wpdb->query("UPDATE ".WATU_EXAMS." SET dont_display_question_numbers=1");
	}
	
	// let's make all "require_login" quizzes created previously to have take_again=1 to avoid sudden change
	if(!empty($version) and $version  < 2.56)  {
		$wpdb->query("UPDATE ".WATU_MASTER." SET take_again=1 WHERE require_login=1");
	}
						
	update_option( "watu_delete_db", '' );	
	update_option( "watu_version", '2.58' );
	
	update_option('watu_admin_notice', __('<h2>Thank you for activating Watu!</h2> <p>Please go to your <a href="tools.php?page=watu_exams">Quizzes page</a> to get started! If this is the first time you have activated the plugin there will be a small demo quiz automatically created for you. Feel free to explore it to get better idea how things work.</p>', 'watu'));
}

function watu_admin_notice() {
		$notice = get_option('watu_admin_notice');
		if(!empty($notice)) {
			echo "<div class='updated'>".stripslashes($notice)."</div>";
		}
		// once shown, cleanup
		update_option('watu_admin_notice', '');
}

function watu_vc_scripts() {
     wp_enqueue_script('jquery');	
		  
      wp_enqueue_style(
			'watu-style',
			WATU_URL.'style.css',
			array(),
			'2.2.0'
		);
		
		wp_enqueue_script(
			'watu-script',
			WATU_URL.'script.js',
			array(),
			'2.3.2'
		);
		
		$translation_array = array(
			'missed_required_question' => __('You have missed to answer a required question', 'watu'),
			'nothing_selected' => __('You did not select any answer. Are you sure you want to continue?', 'watu'),
			'show_answer' => __('Show Answer', 'watu'),
			'complete_text_captcha' => __('You need to answer the verification question', 'watu'),
			'try_again' => __('Try again', 'watu'),
			'email_required' => __('Valid email address is required.', 'watu'),
			);	
		wp_localize_script( 'watu-script', 'watu_i18n', $translation_array );	
}

function watu_vc_jquery() {
	wp_enqueue_script('jquery');
}

// function to conditionally add DB fields
function watu_add_db_fields($fields, $table) {
		global $wpdb;
		
		// check fields
		$table_fields = $wpdb->get_results("SHOW COLUMNS FROM `$table`");
		$table_field_names = array();
		foreach($table_fields as $f) $table_field_names[] = $f->Field;		
		$fields_to_add=array();
		
		foreach($fields as $field) {
			 if(!in_array($field['name'], $table_field_names)) {
			 	  $fields_to_add[] = $field;
			 } 
		}
		
		// now if there are fields to add, run the query
		if(!empty($fields_to_add)) {
			 $sql = "ALTER TABLE `$table` ";
			 
			 foreach($fields_to_add as $cnt => $field) {
			 	 if($cnt > 0) $sql .= ", ";
			 	 $sql .= "ADD $field[name] $field[type]";
			 } 
			 
			 $wpdb->query($sql);
		}
}

add_action('init', 'watu_init');
add_action('wp_ajax_watu_submit', 'watu_submit');
add_action('wp_ajax_nopriv_watu_submit', 'watu_submit');
add_action('wp_ajax_watu_taking_details', 'watu_taking_details');