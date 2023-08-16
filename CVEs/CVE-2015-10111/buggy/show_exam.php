<?php
if(isset($_REQUEST['do']) and $_REQUEST['do']=='show_exam_result' ) $exam_id = $_REQUEST['quiz_id'];

if(!is_singular() and isset($GLOBALS['watu_client_includes_loaded'])) { #If this is in the listing page - and a quiz is already shown, don't show another.
	printf(__("Please go to <a href='%s'>%s</a> to view the test", 'watu'), get_permalink(), get_the_title());
	return false;
} 

global $wpdb, $user_ID, $post;

// select exam
$exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".WATU_EXAMS." WHERE ID=%d", $exam_id));
if(empty($exam->ID)) return __('Quiz not found.', 'watu');

// requires login?
if(!empty($exam->require_login) and !is_user_logged_in()) {
	 echo "<p><b>".sprintf(__('You need to be registered and logged in to take this %s.', 'watu'), __('quiz', 'watu')). 
		      	" <a href='".site_url("/wp-login.php?redirect_to=".urlencode(get_permalink( $post->ID )))."'>".__('Log in', 'watu')."</a>";
		      if(get_option("users_can_register")) {
						echo " ".__('or', 'watu')." <a href='".site_url("/wp-login.php?watu_register=1&action=register&redirect_to=".urlencode(get_permalink( $post->ID )))."'>".__('Register', 'watu')."</a></b>";        
					}
					echo "</p>";
	return false;
}

// can re-take?
if(!empty($exam->require_login) and (empty($exam->take_again) or !empty($exam->times_to_take))) {
	$cnt_takings=$wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".WATU_TAKINGS."
				WHERE exam_id=%d AND user_id=%d", $exam->ID, $user_ID)); 
				
	if(empty($exam->take_again) and $cnt_takings > 0) {
		printf(__("Sorry, you can take this %s only once!", 'watu'), __('quiz', 'watu'));
		return false;
	}
	
	// multiple times allowed, but number is specified	
	if($exam->times_to_take and $cnt_takings >= $exam->times_to_take) {
		echo "<p><b>";
		printf(__("Sorry, you can take this quiz only %d times.", 'watu'), $exam->times_to_take);
		echo "</b></p>";
		return false;
	}			
}

$answer_display = get_option('watu_show_answers');
if(!isset($exam->show_answers) or $exam->show_answers == 100) $answer_display = $answer_display; // assign the default
else $answer_display = $exam->show_answers;

$order_sql = ($exam->randomize or $exam->pull_random) ? "ORDER BY RAND()" : "ORDER BY sort_order, ID";
$limit_sql = $exam->pull_random ? $wpdb->prepare("LIMIT %d", $exam->pull_random) : "";

$questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".WATU_QUESTIONS." 
		WHERE exam_id=%d $order_sql $limit_sql", $exam_id));
$num_questions = sizeof($questions);		

$all_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".WATU_QUESTIONS." WHERE exam_id=%d ", $exam_id));
		
if($questions) {
	if(!isset($GLOBALS['watu_client_includes_loaded']) and !isset($_REQUEST['do']) ) {
		$GLOBALS['watu_client_includes_loaded'] = true; // Make sure that this code is not loaded more than once.
   }

// text captcha?
if(!empty($exam->require_text_captcha)) {	
	$text_captcha_html = WatuTextCaptcha :: generate();
	$textcaptca_style = $exam->single_page==1?"":"style='display:none;'";
	$text_captcha_html = "<div id='WatuTextCaptcha' $textcaptca_style>".$text_captcha_html."</div>";	
	// verify captcha
	if(!empty($_POST['do'])) {
		if(!WatuTextCaptcha :: verify($_POST['watu_text_captcha_question'], $_POST['watu_text_captcha_answer'])) die('WATU_CAPTCHA:::'.__('Wrong answer to the verification question.', 'watu'));	
	}
}

if(isset($_REQUEST['do']) and $_REQUEST['do']) { // Quiz Reuslts.
	$achieved = $max_points = $num_correct = 0;
	$result = '';
	
	// we should reorder the questions in the same way they came from POST because exam might be randomized	
	$_exam = new WatuExam();
	$questions = $_exam->reorder_questions($all_questions, $_POST['question_id']);

	foreach ($questions as $qct => $ques) {
		$qnum = $qct+1;
		$question_number = empty($exam->dont_display_question_numbers) ? "<span class='watu_num'>$qnum. </span>"  : '';
		
		$result .= "<div class='show-question'>";
		$result .= "<div class='show-question-content'>". wpautop($question_number . stripslashes($ques->question), false) . "</div>";
		$all_answers = $ques->answers;
		$correct = false;
		$class = $textarea_class = 'answer';
		$result .= "<ul>";
		$ansArr = is_array( @$_REQUEST["answer-" . $ques->ID] )? $_POST["answer-" . $ques->ID] : array();
		foreach ($all_answers as $ans) {
			$class = 'answer';
			
			list($points, $correct, $class) = WatuQuestion :: calculate($ques, $ans, $ansArr, $correct, $class);		
			if(strstr($class, 'correct-answer')) $textarea_class = $class;	
			
			$achieved += $points;
			if($ques->answer_type != 'textarea') $result .= wpautop("<li class='$class'><span class='answer'><!--WATUEMAIL".$class."WATUEMAIL-->" . stripslashes($ans->answer) . "</span></li>");
		}

		// textareas
		if($ques->answer_type=='textarea' and !empty($_POST["answer-" . $ques->ID][0])) {
			if(!sizeof($all_answers)) $textarea_class = 'correct-answer';
			$result .= wpautop("<li class='user-answer $textarea_class'><span class='answer'><!--WATUEMAIL".$class."WATUEMAIL-->".esc_html(stripslashes($_POST["answer-" . $ques->ID][0]))."</span></li>");
		}		
		
		$result .= "</ul>";
		if(($ques->answer_type == 'textarea' and empty($_POST["answer-" . $ques->ID][0])) 
			or ($ques->answer_type != 'textarea' and empty($_POST["answer-" . $ques->ID])) ) 
			{ $result .= "<p class='unanswered'>" . __('Question was not answered', 'watu') . "</p>";}
			
		// answer explanation?
		if(!empty($ques->feedback)) {
			$result .= "<div class='show-question-feedback'>".wpautop(stripslashes($ques->feedback))."</div>";
		}	

		$result .= "</div>";
	
		if($correct) $num_correct++;
		$max_points += WatuQuestion :: max_points($ques, $all_answers);
	}
	
	// Find scoring details
	if($max_points == 0) $percent = 0;
	else $percent = number_format($achieved / $max_points * 100, 2);
						//0-9			10-19%,	 	20-29%, 	30-39%			40-49%
	$all_rating = array(__('Failed', 'watu'), __('Failed', 'watu'), __('Failed', 'watu'), __('Failed', 'watu'), __('Just Passed', 'watu'),
						//																			100%			More than 100%?!
					__('Satisfactory', 'watu'), __('Competent', 'watu'), __('Good', 'watu'), __('Very Good', 'watu'), __('Excellent', 'watu'), __('Unbeatable', 'watu'), __('Cheater', 'watu'));
	$rate = intval($percent / 10);
	if($percent == 100) $rate = 9;
	if($achieved == $max_points) $rate = 10;
	if($percent>100) $rate = 11;
	$rating = @$all_rating[$rate];
	
	$grade = __('None', 'watu');
	$gtitle = $gdescription="";
	$g_id = 0;
	$allGrades = $wpdb->get_results(" SELECT * FROM `".WATU_GRADES."` WHERE exam_id=$exam_id ");
	if( count($allGrades) ){
		foreach($allGrades as $grow ) {

			if( $grow->gfrom <= $achieved and $achieved <= $grow->gto ) {
				$grade = $gtitle = $grow->gtitle;
				$gdescription = wpautop(stripslashes($grow->gdescription));
				$g_id = $grow->ID;
				if(!empty($grow->gdescription)) $grade .= wpautop(stripslashes($grow->gdescription));
				break;
			}
		}
	}
	
	####################### VARIOUS AVERAGE CALCULATIONS (think about placing them in function / method #######################
	// calculate averages
	$avg_points = $avg_percent = '';
	if(strstr($exam->final_screen, '%%AVG-POINTS%%')) {
		$all_point_rows = $wpdb->get_results($wpdb->prepare("SELECT points FROM ".WATU_TAKINGS." 
			WHERE exam_id=%d", $exam->ID));
		$all_points = 0;
		foreach($all_point_rows as $r) $all_points += $r->points;	
		$all_points += $achieved;			
		$avg_points = round($all_points / ($wpdb->num_rows + 1), 1);
	}
	
	// better than what %?
	$better_than = '';
	if(strstr($exam->final_screen, '%%BETTER-THAN%%')) {
		// select total completed quizzes
		$total_takings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".WATU_TAKINGS."
			WHERE exam_id=%d", $exam->ID));	
		
		$num_lower = $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".WATU_TAKINGS."
				WHERE exam_id=%d AND points < %f", $exam->ID, $achieved));
		
		$better_than = $total_takings ? round($num_lower * 100 / $total_takings) : 0;
	}
	####################### END VARIOUS AVERAGE CALCULATIONS #######################
	
	
	$quiz_details = $wpdb->get_row($wpdb->prepare("SELECT name,final_screen, description FROM {$wpdb->prefix}watu_master WHERE ID=%d", $exam_id));

	$quiz_details->final_screen = str_replace('%%TOTAL%%', '%%MAX-POINTS%%', $quiz_details->final_screen);
	$replace_these	= array('%%SCORE%%', '%%MAX-POINTS%%', '%%PERCENTAGE%%', '%%GRADE%%', '%%RATING%%', '%%CORRECT%%', '%%WRONG_ANSWERS%%', '%%QUIZ_NAME%%',	'%%DESCRIPTION%%', '%%GRADE-TITLE%%', '%%GRADE-DESCRIPTION%%', '%%POINTS%%', '%%AVG-POINTS%%', '%%BETTER-THAN%%');
	$with_these		= array($achieved,		 $max_points,	  $percent,			$grade,		 $rating,		$num_correct,					$num_questions-$num_correct,	   stripslashes($quiz_details->name), wpautop(stripslashes($quiz_details->description)), $gtitle, $gdescription, $achieved, $avg_points, $better_than);
	
	// insert taking
	$uid = $user_ID ? $user_ID : 0;
	if(empty($exam->dont_store_data)) {
		if($exam->no_ajax) {
			$taking_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM ".WATU_TAKINGS."
				WHERE ip=%s AND user_id=%d AND exam_id=%d AND points=%d AND grade_id=%d AND start_time=%s",
				$_SERVER['REMOTE_ADDR'], $user_ID, $exam->ID, $achieved, $g_id, $_POST['start_time']));				
		}		
		if(empty($taking_id)) {
			$wpdb->query($wpdb->prepare("INSERT INTO ".WATU_TAKINGS." SET exam_id=%d, user_id=%d, ip=%s, date=CURDATE(), 
				points=%d, grade_id=%d, result=%s, snapshot='', start_time=%s", 
				$exam_id, $uid, $_SERVER['REMOTE_ADDR'], $achieved, $g_id, $grade, $_POST['start_time']));
			$taking_id = $wpdb->insert_id;
		}
	}
	else $taking_id = 0;	
	$GLOBALS['watu_taking_id'] = $taking_id;

	// Show the results
	$output = str_replace($replace_these, $with_these, wpautop(stripslashes($quiz_details->final_screen)));
	if(strstr($output, '%%ANSWERS%%')) {		
		$output = str_replace('%%ANSWERS%%', $result, $output);
	}
	$final_output = apply_filters(WATU_CONTENT_FILTER, $output);
	
	echo $final_output;
		
	// update snapshot
	$wpdb->query($wpdb->prepare("UPDATE ".WATU_TAKINGS." SET snapshot=%s WHERE ID=%d", $final_output, $taking_id)); 
	
	// notify admin	
	if(!empty($exam->email_output)) {
		$email_output = wpautop(stripslashes($exam->email_output));
		$email_output = str_replace($replace_these, $with_these, $email_output);
		if(strstr($email_output, '%%ANSWERS%%')) {		
			$email_output = str_replace('%%ANSWERS%%', $result, $email_output);
		}
		$email_output = apply_filters(WATU_CONTENT_FILTER, $email_output);
	} 
	else $email_output = $final_output;
	if(!empty($exam->notify_admin)) watu_notify($exam, $uid, $email_output);
	if(!empty($exam->notify_user)) watu_notify($exam, $uid, $email_output, 'user');
	
	do_action('watu_exam_submitted', $taking_id);
	if(empty($exam->no_ajax)) exit;// Exit due to ajax call

} else { // Show The Test
	$single_page = $exam->single_page;
	if(@file_exists(get_stylesheet_directory().'/watu/show_exam.html.php')) include get_stylesheet_directory().'/watu/show_exam.html.php';
	else include(WATU_PATH . '/views/show_exam.html.php');
 }
} // end if $questions