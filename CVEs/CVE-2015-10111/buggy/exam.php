<?php
function watu_exams() {
	global $wpdb;
	
	if( isset($_REQUEST['message']) && $_REQUEST['message'] == 'updated') print '<div id="message" class="updated fade"><p>' . __('Test updated', 'watu') . '</p></div>';
	if(isset($_REQUEST['message']) && $_REQUEST['message'] == 'fail') print '<div id="message" class="updated error"><p>' . __('Error occured', 'watu') . '</p></div>';
	if( isset($_REQUEST['grade']) )  print '<div id="message" class="updated fade"><p>' . $_REQUEST['grade']. '</p></div>';
	
	if(!empty($_GET['action']) and $_GET['action'] == 'delete') {
		$wpdb->get_results("DELETE FROM ".WATU_EXAMS." WHERE ID='$_REQUEST[quiz]'");
		$wpdb->get_results("DELETE FROM ".WATU_ANSWERS." WHERE question_id IN (SELECT ID FROM ".WATU_QUESTIONS." WHERE exam_id='$_REQUEST[quiz]')");
		$wpdb->get_results("DELETE FROM ".WATU_QUESTIONS." WHERE exam_id='$_REQUEST[quiz]'");
		print '<div id="message" class="updated fade"><p>' . __('Test deleted', 'watu') . '</p></div>';
	}
	
	// Retrieve the quizzes
		$exams = $wpdb->get_results("SELECT Q.ID,Q.name,Q.added_on,
			(SELECT COUNT(ID) FROM ".WATU_QUESTIONS." WHERE exam_id=Q.ID) AS question_count,
			(SELECT COUNT(ID) FROM ".WATU_TAKINGS." WHERE exam_id=Q.ID) AS taken
			FROM `".WATU_EXAMS."` AS Q ");
		
		// now select all posts that have watu shortcode in them
		$posts=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts 
		WHERE post_content LIKE '%[WATU %]%' 
		AND post_status='publish' AND post_title!=''
		ORDER BY post_date DESC");	
		
		// match posts to exams
		foreach($exams as $cnt=>$exam) {
			foreach($posts as $post) {
				if(strstr($post->post_content,"[WATU ".$exam->ID."]")) {
					$exams[$cnt]->post=$post;			
					break;
				}
			}
		}
	if(@file_exists(get_stylesheet_directory().'/watu/exams.html.php')) include get_stylesheet_directory().'/watu/exams.html.php';
	else include(WATU_PATH . '/views/exams.html.php');
} 

function watu_exam() {
	global $wpdb, $user_ID;
	$answer_display = get_option('watu_show_answers');
	
	if(isset($_POST['submit'])) {
		// use email output?
		$_POST['email_output'] = empty($_POST['different_email_output']) ? '' : $_POST['email_output'];		
		
		if($_REQUEST['action'] == 'edit') { //Update goes here
			$exam_id = $_REQUEST['quiz'];
			$wpdb->query($wpdb->prepare("UPDATE ".WATU_EXAMS."
				SET name=%s, description=%s,final_screen=%s, randomize=%d, single_page=%d, 
				show_answers=%d, require_login=%d, notify_admin=%d, randomize_answers=%d,
				pull_random=%d, dont_store_data=%d, show_prev_button=%d, 
				dont_display_question_numbers=%d, require_text_captcha=%d, email_output=%s,
				notify_user=%d, notify_email=%s, take_again=%d, times_to_take=%d   
				WHERE ID=%d", $_POST['name'], $_POST['description'], $_POST['content'], 
				@$_POST['randomize'], @$_POST['single_page'], @$_POST['show_answers'], 
				@$_POST['require_login'], @$_POST['notify_admin'], @$_POST['randomize_answers'],
				$_POST['pull_random'], @$_POST['dont_store_data'], @$_POST['show_prev_button'], 
				@$_POST['dont_display_question_numbers'], @$_POST['require_text_captcha'], 
				$_POST['email_output'], @$_POST['notify_user'], $_POST['notify_email'], 
				@$_POST['take_again'], $_POST['times_to_take'], $_POST['quiz']));
			
			if(!empty($_POST['auto_publish'])) watu_auto_publish($exam_id);
			$wp_redirect = 'tools.php?page=watu_exams&message=updated';
		
		} else {
			$wpdb->query($wpdb->prepare("INSERT INTO ".WATU_EXAMS." 
				(name, description, final_screen,  added_on, randomize, single_page, show_answers, require_login, 
				notify_admin, randomize_answers, pull_random, dont_store_data, show_prev_button, 
				dont_display_question_numbers, require_text_captcha, email_output, notify_user, 
				notify_email, take_again, times_to_take) 
				VALUES(%s, %s, %s, NOW(), %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %s, %d, %s, %d, %d)", 
				$_POST['name'], $_POST['description'], $_POST['content'], @$_POST['randomize'], @$_POST['single_page'], 
				@$_POST['show_answers'], @$_POST['require_login'], @$_POST['notify_admin'], 
				@$_POST['randomize_answers'], $_POST['pull_random'], @$_POST['dont_store_data'], 
				@$_POST['show_prev_button'], @$_POST['dont_display_question_numbers'], 
				@$_POST['require_text_captcha'], $_POST['email_output'], @$_POST['notify_user'], 
				$_POST['notify_email'], @$_POST['take_again'], $_POST['times_to_take']));
			$exam_id = $wpdb->insert_id;
			if(!empty($_POST['auto_publish'])) watu_auto_publish($exam_id);
			if($exam_id == 0 ) $wp_redirect = 'tools.php?page=watu_exams&message=fail';
			$wp_redirect = 'admin.php?page=watu_questions&message=new_quiz&quiz='.$exam_id;
		}
				
		$wp_redirect = admin_url($wp_redirect);
		
		do_action('watu_exam_saved', $exam_id);
		
		echo "<meta http-equiv='refresh' content='0;url=$wp_redirect' />"; 
		exit;
	}

		
	$action = 'new';
	if($_REQUEST['action'] == 'edit') $action = 'edit';
	
	$dquiz = array();
	$grades = array();
	if($action == 'edit') {
		$dquiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".WATU_EXAMS." WHERE ID=%d", $_REQUEST['quiz']));
		$grades = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".WATU_GRADES." WHERE  exam_id=%d order by ID ", $_REQUEST['quiz']) );
		$final_screen = stripslashes($dquiz->final_screen);
	} else {
		$final_screen = __("<p>Congratulations - you have completed %%QUIZ_NAME%%.</p>\n\n<p>You scored %%POINTS%% points out of %%MAX-POINTS%% points total.</p>\n\n<p>Your obtained grade is <b>%%GRADE-TITLE%%</b></p><p>%%GRADE-DESCRIPTION%%</p>\n\n<p>Your answers are shown below:<p>%%ANSWERS%%", 'watu');
	}
	
	// see what is the show_answers to this exam
	if(!isset($dquiz->show_answers) or $dquiz->show_answers == 100) $answer_display = $answer_display; // assign the default
	else $answer_display = $dquiz->show_answers;
	
	if(!empty($_GET['quiz'])) {
		$quiz_id = intval($_GET['quiz']);
		$is_published = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[watu ".$quiz_id."]%' 
				AND post_status='publish' AND post_title!=''");
	} 
	else $is_published = false;
	
	if(@file_exists(get_stylesheet_directory().'/watu/exam_form.php')) include get_stylesheet_directory().'/watu/exam_form.php';
	else include(WATU_PATH . '/views/exam_form.php');
}

// auto publish quiz in post
// some data comes directly from the $_POST to save unnecessary DB query
function watu_auto_publish($quiz_id) {	
	$post = array('post_content' => '[WATU '.$quiz_id.']', 'post_name'=> $_POST['name'], 
		'post_title'=>$_POST['name'], 'post_status'=>'publish');
	wp_insert_post($post);
}