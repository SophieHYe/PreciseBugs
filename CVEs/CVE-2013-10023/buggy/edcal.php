<?php
/*******************************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 ******************************************************************************/

/*
Plugin Name: WordPress Editorial Calendar
Description: The Editorial Calendar makes it possible to see all your posts and drag and drop them to manage your blog.
Version: 2.7
Author: Colin Vernon, Justin Evans, Joachim Kudish, Mary Vogt, and Zack Grossbart
Author URI: http://www.zackgrossbart.com
Plugin URI: http://stresslimitdesign.com/editorial-calendar-plugin
*/


if ( is_admin() ) {
    global $edcal;
    if ( empty($edcal) )
        $edcal = new EdCal();
}


/*
 * This error code matches CONCURRENCY_ERROR from edcal.js
 */
define( 'EDCAL_CONCURRENCY_ERROR', 4 );

/*
 * This error code matches PERMISSION_ERROR from edcal.js
 */
define( 'EDCAL_PERMISSION_ERROR', 5 );

/*
 * This error code matches NONCE_ERROR from edcal.js
 */
define( 'EDCAL_NONCE_ERROR', 6 );

class EdCal {
    
    protected $supports_custom_types;
    protected $default_time;

    function __construct() {
        add_action('wp_ajax_edcal_saveoptions', array(&$this, 'edcal_saveoptions'));
        add_action('wp_ajax_edcal_changedate', array(&$this, 'edcal_changedate'));
        add_action('wp_ajax_edcal_savepost', array(&$this, 'edcal_savepost'));
        add_action('wp_ajax_edcal_changetitle', array(&$this, 'edcal_changetitle'));
        add_action('admin_menu', array(&$this, 'edcal_list_add_management_page'));
        add_action('wp_ajax_edcal_posts', array(&$this, 'edcal_posts'));
        add_action('wp_ajax_edcal_getpost', array(&$this, 'edcal_getpost'));
        add_action('wp_ajax_edcal_deletepost', array(&$this, 'edcal_deletepost'));
        add_action("init", array(&$this, 'edcal_load_language'));
        
        /*
         * This boolean variable will be used to check whether this 
         * installation of WordPress supports custom post types.
         */
        $this->supports_custom_types = function_exists('get_post_types') && function_exists('get_post_type_object');

        /*
         * This is the default time that posts get created at, for now 
         * we are using 10am, but this could become an option later.
         */
        $this->default_time = get_option("edcal_default_time") != "" ? get_option("edcal_default_time") : '10:00';        
        
        /*
         * We use these variables to hold the post dates for the filter when 
         * we do our post query.
         */
        //$edcal_startDate;
        //$edcal_endDate;
    }
    
    function edcal_load_language() {
        $plugin_dir = basename(dirname(__FILE__));
        load_plugin_textdomain( 'editorial-calendar', 'wp-content/plugins/' . $plugin_dir . '/languages/', $plugin_dir . '/languages/' );
    }
    
    /*
     * This function adds our calendar page to the admin UI
     */
    function edcal_list_add_management_page() {
        if (function_exists('add_management_page') ) {
            $page = add_posts_page( __('Calendar', 'editorial-calendar'), __('Calendar', 'editorial-calendar'), 'edit_posts', 'cal', array(&$this, 'edcal_list_admin'));
            add_action( "admin_print_scripts-$page", array(&$this, 'edcal_scripts'));

            if( $this->supports_custom_types ) {

                /* 
                 * We add one calendar for Posts and then we add a separate calendar for each
                 * custom post type.  This calendar will have an URL like this:
                 * /wp-admin/edit.php?post_type=podcasts&page=cal_podcasts
                 *
                 * We can then use the post_type parameter to show the posts of just that custom
                 * type and update the labels for each post type.
                 */
                $args = array(
                    'public'   => true,
                    '_builtin' => false
                ); 
                $output = 'names'; // names or objects
                $operator = 'and'; // 'and' or 'or'
                $post_types = get_post_types($args,$output,$operator); 

                foreach ($post_types as $post_type) {
                    $show_this_post_type = apply_filters("edcal_show_calendar_$post_type", true);
                    if ($show_this_post_type) {
                        $page = add_submenu_page('edit.php?post_type=' . $post_type, __('Calendar', 'editorial-calendar'), __('Calendar', 'editorial-calendar'), 'edit_posts', 'cal_' . $post_type, array(&$this, 'edcal_list_admin'));
                        add_action( "admin_print_scripts-$page", array(&$this, 'edcal_scripts'));
                    }
                }    
            }
        }
    }
    
    /*
     * This is a utility function to open a file add it to our
     * output stream.  We use this to embed JavaScript and CSS
     * files and cut down on the number of HTTP requests.
     */
    function edcal_echoFile($myFile) {
        $fh = fopen($myFile, 'r');
        $theData = fread($fh, filesize($myFile));
        fclose($fh);
        echo $theData;
    }
     
    /*
     * This is the function that generates our admin page.  It adds the CSS files and 
     * generates the divs that we need for the JavaScript to work.
     */
    function edcal_list_admin() {
        
        /*
         * We want to count the number of times they load the calendar
         * so we only show the feedback after they have been using it 
         * for a little while.
         */
        $edcal_count = get_option("edcal_count");
        if ($edcal_count == '') {
            $edcal_count = 0;
            add_option("edcal_count", $edcal_count, "", "yes");
        }
            
        if (get_option("edcal_do_feedback") != "done") {
            $edcal_count++;
            update_option("edcal_count", $edcal_count);
        }
        
        /*
         * This section of code embeds certain CSS and
         * JavaScript files into the HTML.  This has the 
         * advantage of fewer HTTP requests, but the 
         * disadvantage that the browser can't cache the
         * results.  We only do this for files that will
         * be used on this page and nowhere else.
         */
         
        echo '<!-- This is the styles from time picker.css -->';
        echo '<style type="text/css">';
        $this->edcal_echoFile(dirname( __FILE__ ) . "/lib/timePicker.css");
        echo '</style>';
        
        echo '<!-- This is the styles from humanmsg.css -->';
        echo '<style type="text/css">';
        $this->edcal_echoFile(dirname( __FILE__ ) . "/lib/humanmsg.css");
        echo '</style>';
        
        echo '<!-- This is the styles from edcal.css -->';
        echo '<style type="text/css">';
        $this->edcal_echoFile(dirname( __FILE__ ) . "/edcal.css");
        echo '</style>';
        
        /*
         * We want to add the right to left stylesheet if 
         * we're in a right to left language.
         */
        if (__('ltr', 'editorial-calendar') == 'rtl') {
            echo '<!-- This is the styles from edcal_rtl.css -->';
            echo '<style type="text/css">';
            $this->edcal_echoFile(dirname( __FILE__ ) . "/edcal_rtl.css");
            echo '</style>';
        }
        
        
        ?>
        
        <!-- This is just a little script so we can pass the AJAX URL and some localized strings -->
        <script type="text/javascript">
            jQuery(document).ready(function(){
                edcal.plugin_url = '<?php echo(plugins_url("/", __FILE__ )); ?>';
                edcal.wp_nonce = '<?php echo wp_create_nonce("edit-calendar"); ?>';
                <?php 
                    if (get_option("edcal_weeks_pref") != "") {
                ?>
                    edcal.weeksPref = <?php echo(get_option("edcal_weeks_pref")); ?>;
                <?php
                    }
                ?>
                
                <?php 
                    if (get_option("edcal_author_pref") != "") {
                ?>
                    edcal.authorPref = <?php echo(get_option("edcal_author_pref")); ?>;
                <?php
                    }
                ?>
                
                <?php 
                    if (get_option("edcal_time_pref") != "") {
                ?>
                    edcal.timePref = <?php echo(get_option("edcal_time_pref")); ?>;
                <?php
                    }
                ?>
                
                <?php 
                    if (get_option("edcal_status_pref") != "") {
                ?>
                    edcal.statusPref = <?php echo(get_option("edcal_status_pref")); ?>;
                <?php
                    }
                ?>
                
                <?php 
                    if (get_option("edcal_do_feedback") != "done") {
                ?>
                    edcal.doFeedbackPref = true;
                    edcal.visitCount = <?php echo(get_option("edcal_count")); ?>;
                <?php
                    }
                ?>
    
                <?php $this->edcal_getLastPost(); ?>
                
                edcal.startOfWeek = <?php echo(get_option("start_of_week")); ?>;
                edcal.timeFormat = "<?php echo(get_option("time_format")); ?>";
                edcal.previewDateFormat = "MMMM d";
                edcal.defaultTime = "<?php echo $this->default_time; ?>";
    
                /*
                 * We want to show the day of the first day of the week to match the user's 
                 * country code.  The problem is that we can't just use the WordPress locale.
                 * If the locale was fr-FR so we started the week on Monday it would still 
                 * say Sunday was the first day if we didn't have a proper language bundle
                 * for French.  Therefore we must depend on the language bundle writers to
                 * specify the locale for the language they are adding.
                 * 
                 */
                edcal.locale = '<?php echo(__('en-US', 'editorial-calendar')) ?>';
                
                /*
                 * These strings are all localized values.  The WordPress localization mechanism 
                 * doesn't really extend to JavaScript so we localize the strings in PHP and then
                 * pass the values to JavaScript.
                 */
                
                edcal.str_by = <?php echo($this->edcal_json_encode(__('%1$s by %2$s', 'editorial-calendar'))) ?>;
                
                edcal.str_addPostLink = <?php echo($this->edcal_json_encode(__('New Post', 'editorial-calendar'))) ?>;
                edcal.str_addDraftLink = <?php echo($this->edcal_json_encode(__('New Draft', 'editorial-calendar'))) ?>;
                edcal.ltr = <?php echo($this->edcal_json_encode(__('ltr', 'editorial-calendar'))) ?>;
                
                edcal.str_draft = <?php echo($this->edcal_json_encode(__(' [DRAFT]', 'editorial-calendar'))) ?>;
                edcal.str_pending = <?php echo($this->edcal_json_encode(__(' [PENDING]', 'editorial-calendar'))) ?>;
                edcal.str_sticky = <?php echo($this->edcal_json_encode(__(' [STICKY]', 'editorial-calendar'))) ?>;
                edcal.str_draft_sticky = <?php echo($this->edcal_json_encode(__(' [DRAFT, STICKY]', 'editorial-calendar'))) ?>;
                edcal.str_pending_sticky = <?php echo($this->edcal_json_encode(__(' [PENDING, STICKY]', 'editorial-calendar'))) ?>;
                edcal.str_edit = <?php echo($this->edcal_json_encode(__('Edit', 'editorial-calendar'))) ?>;
                edcal.str_quick_edit = <?php echo($this->edcal_json_encode(__('Quick Edit', 'editorial-calendar'))) ?>;
                edcal.str_del = <?php echo($this->edcal_json_encode(__('Delete', 'editorial-calendar'))) ?>;
                edcal.str_view = <?php echo($this->edcal_json_encode(__('View', 'editorial-calendar'))) ?>;
                edcal.str_republish = <?php echo($this->edcal_json_encode(__('Edit', 'editorial-calendar'))) ?>;
                edcal.str_status = <?php echo($this->edcal_json_encode(__('Status:', 'editorial-calendar'))) ?>;
                edcal.str_cancel = <?php echo($this->edcal_json_encode(__('Cancel', 'editorial-calendar'))) ?>;
                edcal.str_posttitle = <?php echo($this->edcal_json_encode(__('Title', 'editorial-calendar'))) ?>;
                edcal.str_postcontent = <?php echo($this->edcal_json_encode(__('Content', 'editorial-calendar'))) ?>;
                edcal.str_newpost = <?php echo($this->edcal_json_encode(__('Add a new post on %s', 'editorial-calendar'))) ?>;
                edcal.str_newdraft = <?php echo($this->edcal_json_encode(__('Add a new draft', 'editorial-calendar'))) ?>;
                edcal.str_newpost_title = <?php echo($this->edcal_json_encode(sprintf(__('New %s - ', 'editorial-calendar'), $this->edcal_get_posttype_singlename()))) ?> ;
                edcal.str_newdraft_title = <?php echo($this->edcal_json_encode(__('New Draft', 'editorial-calendar'))) ?>;
                edcal.str_update = <?php echo($this->edcal_json_encode(__('Update', 'editorial-calendar'))) ?>;
                edcal.str_publish = <?php echo($this->edcal_json_encode(__('Schedule', 'editorial-calendar'))) ?>;
                edcal.str_review = <?php echo($this->edcal_json_encode(__('Submit for Review', 'editorial-calendar'))) ?>;
                edcal.str_save = <?php echo($this->edcal_json_encode(__('Save', 'editorial-calendar'))) ?>;
                edcal.str_edit_post_title = <?php echo($this->edcal_json_encode(__('Edit %1$s - %2$s', 'editorial-calendar'))) ?>;
                edcal.str_scheduled = <?php echo($this->edcal_json_encode(__('Scheduled', 'editorial-calendar'))) ?>;
                
                edcal.str_del_msg1 = <?php echo($this->edcal_json_encode(__('You are about to delete the post "', 'editorial-calendar'))) ?>;
                edcal.str_del_msg2 = <?php echo($this->edcal_json_encode(__('". Press Cancel to stop, OK to delete.', 'editorial-calendar'))) ?>;
                
                edcal.concurrency_error = <?php echo($this->edcal_json_encode(__('Looks like someone else already moved this post.', 'editorial-calendar'))) ?>;
                edcal.permission_error = <?php echo($this->edcal_json_encode(__('You do not have permission to edit posts.', 'editorial-calendar'))) ?>;
                edcal.checksum_error = <?php echo($this->edcal_json_encode(__('Invalid checksum for post. This is commonly a cross-site scripting error.', 'editorial-calendar'))) ?>;
                edcal.general_error = <?php echo($this->edcal_json_encode(__('There was an error contacting your blog.', 'editorial-calendar'))) ?>;
                
                edcal.str_screenoptions = <?php echo($this->edcal_json_encode(__('Screen Options', 'editorial-calendar'))) ?>;
                edcal.str_optionscolors = <?php echo($this->edcal_json_encode(__('Colors', 'editorial-calendar'))) ?>;
                edcal.str_optionsdraftcolor = <?php echo($this->edcal_json_encode(__('Drafts: ', 'editorial-calendar'))) ?>;
                edcal.str_apply = <?php echo($this->edcal_json_encode(__('Apply', 'editorial-calendar'))) ?>;
                edcal.str_show_title = <?php echo($this->edcal_json_encode(__('Show on screen', 'editorial-calendar'))) ?>;
                edcal.str_opt_weeks = <?php echo($this->edcal_json_encode(__(' weeks at a time', 'editorial-calendar'))) ?>;
                edcal.str_show_opts = <?php echo($this->edcal_json_encode(__('Show in Calendar Cell', 'editorial-calendar'))) ?>;
                edcal.str_opt_author = <?php echo($this->edcal_json_encode(__('Author', 'editorial-calendar'))) ?>;
                edcal.str_opt_status = <?php echo($this->edcal_json_encode(__('Status', 'editorial-calendar'))) ?>;
                edcal.str_opt_time = <?php echo($this->edcal_json_encode(__('Time of day', 'editorial-calendar'))) ?>;
                edcal.str_fatal_error = <?php echo($this->edcal_json_encode(__('An error occurred while loading the calendar: ', 'editorial-calendar'))) ?>;
                
                edcal.str_weekserror = <?php echo($this->edcal_json_encode(__('The calendar can only show between 1 and 8 weeks at a time.', 'editorial-calendar'))) ?>;
                edcal.str_weekstt = <?php echo($this->edcal_json_encode(__('Select the number of weeks for the calendar to show.', 'editorial-calendar'))) ?>;

                edcal.str_showdrafts = <?php echo($this->edcal_json_encode(__('Show Unscheduled Drafts'))) ?>;
                edcal.str_hidedrafts = <?php echo($this->edcal_json_encode(__('Hide Unscheduled Drafts'))) ?>;
    
                edcal.str_feedbackmsg = <?php echo($this->edcal_json_encode(__('<div id="feedbacksection">' . 
                 '<h2>Help us Make the Editorial Calendar Better</h2>' .
                 'We are always trying to improve the Editorial Calendar and you can help. May we collect some data about your blog and browser settings to help us improve this plugin?  We\'ll only do it once and your blog will show up on our <a target="_blank" href="http://www.zackgrossbart.com/edcal/mint/">Editorial Calendar Statistics page</a>.<br /><br />' . 
                 '<button class="button-secondary" onclick="edcal.doFeedback();">Collect Data</button> ' . 
                 '<a href="#" id="nofeedbacklink" onclick="edcal.noFeedback(); return false;">No thank you</a></div>', 'editorial-calendar'))) ?>;
    
                edcal.str_feedbackdone = <?php echo($this->edcal_json_encode(__('<h2>We\'re done</h2>We\'ve finished collecting data.  Thank you for helping us make the calendar better.', 'editorial-calendar'))) ?>;
            });
        </script>
        
        <?php
        /*
         * There are a few images we want to reference where we need the full path to the image
         * since we don't want to make assumptions about the plugin file structure.  We need to 
         * set those here since we need PHP to get the full path.  
         */
        ?>
    
        <style type="text/css">
            .loadingclass > .postlink, .loadingclass:hover > .postlink, .tiploading {
                background-image: url('<?php echo(admin_url("images/loading.gif", __FILE__ )); ?>');
            }
    
            #loading {
                background-image: url('<?php echo(admin_url("images/loading.gif", __FILE__ )); ?>');
            }
    
            #tipclose {
                background-image: url('<?php echo(plugins_url("images/tip_close.png", __FILE__ )); ?>');
            }
    
        </style>
        
        <?php
        echo '<!-- This is the code from edcal.js -->';
        echo '<script type="text/javascript">';
        if (isset($_GET['debug'])) {
            $this->edcal_echoFile(dirname( __FILE__ ) . "/edcal.js");
        } else {
            $this->edcal_echoFile(dirname( __FILE__ ) . "/edcal.min.js");
        }
        echo '</script>';
        
        ?>
        
        <div class="wrap">
            <div class="icon32" id="icon-edit"><br/></div>
            <h2 id="edcal_main_title"><?php echo sprintf( __('%1$s Calendar', 'editorial-calendar'), $this->edcal_get_posttype_multiplename() ) ?></h2>
            
            <div id="loadingcont">
                <div id="loading"> </div>
            </div>
            
            <div id="topbar" class="tablenav clearfix">
                <div id="topleft" class="tablenav-pages alignleft">
                    <h3>
                        <a href="#" title="<?php echo(__('Jump back', 'editorial-calendar')) ?>" class="prev page-numbers" id="prevmonth">&lsaquo;</a>
                        <span id="currentRange"></span>
                        <a href="#" title="<?php echo(__('Skip ahead', 'editorial-calendar')) ?>" class="next page-numbers" id="nextmonth">&rsaquo;</a>
                        <a class="next page-numbers" title="<?php echo(__('Scroll the calendar and make the last post visible', 'editorial-calendar')) ?>" id="moveToLast">&raquo;</a>

                        <a class="next page-numbers" title="<?php echo(__('Scroll the calendar and make the today visible', 'editorial-calendar')) ?>" id="moveToToday"><?php echo(__('Show Today', 'editorial-calendar')) ?></a>
                        
                        
                    </h3>
                </div>

                <div id="topright" class="tablenav-pages alignright">
                    <a class="next page-numbers" title="<?php echo(__('Show unscheduled posts', 'editorial-calendar')) ?>" id="showdraftsdrawer"><?php echo(__('Show Unscheduled Drafts', 'editorial-calendar')) ?></a>
                </div>
            </div>
            
            <div id="draftsdrawer_cont">
                <div id="draftsdrawer">
                    <div class="draftsdrawerheadcont" title="<?php echo(__('Unscheduled draft posts', 'editorial-calendar')) ?>"><div class="dayhead"><?php echo(__('Unscheduled', 'editorial-calendar')) ?></div></div>
                    <div class="day" id="00000000">
                        <div id="draftsdrawer_loading"></div>
                        <div id="unscheduled" class="dayobj"></div>
                    </div>
                </div>
            </div>
            
            <div id="cal_cont">
                <div id="edcal_scrollable" class="edcal_scrollable vertical">
                    <div id="cal"></div>
                </div>
            </div>

            <?php $this->edcal_edit_popup(); ?>
            
        </div><?php // end .wrap ?>
    
        <?php
    }
    
    /*
     * Generate the DOM elements for the quick edit popup from
     * within the calendar.
     */
    function edcal_edit_popup() {
    
    ?>
          <div id="edcal_quickedit" style="display:none;">
            <div id="tooltiphead">
              <h3 id="tooltiptitle"><?php _e('Edit Post', 'editorial-calendar') ?></h3>
              <a href="#" id="tipclose" onclick="edcal.hideForm(); return false;" title="close"> </a>
            </div>
    
                <div class="edcal_quickedit inline-edit-row">
    
                    <fieldset>
    
                    <label>
                        <span class="title"><?php _e('Title', 'editorial-calendar') ?></span>
                        <span class="input-text-wrap"><input type="text" class="ptitle" id="edcal-title-new-field" name="title" /></span>
                    </label>
    
                    <label>
                        <span class="title"><?php _e('Content', 'editorial-calendar') ?></span>
                        <span class="input-text-wrap"><textarea cols="15" rows="7" id="content" name="content"></textarea></span>
                    </label>
    
                    <div id="timeEditControls">
                        <label>
                            <span class="title"><?php _e('Time', 'editorial-calendar') ?></span>
                            <span class="input-text-wrap"><input type="text" class="ptitle" id="edcal-time" name="time" value="" size="8" maxlength="8" autocomplete="off" /></span>
                        </label>
                            
                        <label>
                            <span class="title"><?php _e('Status', 'editorial-calendar') ?></span>
                            <span class="input-text-wrap">
                                <select name="status" id="edcal-status">
                                    <option value="draft"><?php _e('Draft', 'editorial-calendar') ?></option>
                                    <option value="pending"><?php _e('Pending Review', 'editorial-calendar') ?></option>
                                    <?php if ( current_user_can('publish_posts') ) {?>
                                        <option id="futureoption" value="future"><?php _e('Scheduled', 'editorial-calendar') ?></option>
                                    <?php } ?>
                                </select>
                            </span>
                        </label>
                    </div>
    
    <?php /*                <label>
                        <span class="title"><?php _e('Author', 'editorial-calendar') ?></span>
                        <span id="edcal-author-p"><!-- Placeholder for the author's name, added dynamically --></span>
                    </label>
    */ ?>
                    </fieldset>
    
                    <p class="submit inline-edit-save" id="edit-slug-buttons">
                        <a class="button-primary disabled" id="newPostScheduleButton" href="#"><?php _e('Schedule', 'editorial-calendar') ?></a>
                        <a href="#" onclick="edcal.hideForm(); return false;" class="button-secondary cancel"><?php _e('Cancel', 'editorial-calendar') ?></a>
                    </p>
    
                    <input type="hidden" id="edcal-date" name="date" value="" />
                    <input type="hidden" id="edcal-id" name="id" value="" />
    
                </div><?php // end .tooltip ?>
            </div><?php // end #tooltip 
    }
    
    /*
     * When we get a set of posts to populate the calendar we don't want
     * to get all of the posts.  This filter allows us to specify the dates
     * we want. We also exclude posts that have not been set to a specific date.
     */
    function edcal_filter_where($where = '') {
        global $edcal_startDate, $edcal_endDate;
        if ($edcal_startDate == '00000000') {
            $where .= " AND post_date_gmt LIKE '0000%'";
        } else {
            $where .= " AND post_date >= '" . $edcal_startDate . "' AND post_date < '" . $edcal_endDate . "' AND post_date_gmt NOT LIKE '0000%'";
        }
        return $where;
    }
    
    /*
     * This function adds all of the JavaScript files we need.
     *
     */
    function edcal_scripts() {
        /*
         * To get proper localization for dates we need to include the correct JavaScript file for the current
         * locale.  We can do this based on the locale in the localized bundle to make sure the date locale matches
         * the locale for the other strings.
         */
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
    
        //wp_enqueue_script("date-extras", plugins_url("lib/date.extras.js", __FILE__ ), array( 'jquery' ));
    
        wp_enqueue_script("edcal-date", plugins_url("lib/languages/date-".__('en-US', 'editorial-calendar').".js", __FILE__ ));
        wp_enqueue_script("edcal-lib", plugins_url("lib/edcallib.min.js", __FILE__ ), array( 'jquery' ));
    
        if (isset($_GET['qunit'])) {
            wp_enqueue_script("qunit", plugins_url("lib/qunit.js", __FILE__ ), array( 'jquery' ));
            wp_enqueue_script("edcal-test", plugins_url("edcal_test.js", __FILE__ ), array( 'jquery' ));
        }
        
        return;
        
        /*
         * If you're using one of the specific libraries you should comment out the two lines
         * above this comment.
         */
        wp_enqueue_script("bgiframe", plugins_url("lib/jquery.bgiframe.js", __FILE__ ), array( 'jquery' ));
        wp_enqueue_script("humanMsg", plugins_url("lib/humanmsg.js", __FILE__ ), array( 'jquery' ));
        wp_enqueue_script("jquery-timepicker", plugins_url("lib/jquery.timepicker.js", __FILE__ ), array( 'jquery' ));
        
        wp_enqueue_script("scrollable", plugins_url("lib/tools.scrollable-1.1.2.js", __FILE__ ), array( 'jquery' ));
        wp_enqueue_script("mouse-wheel", plugins_url("lib/lib/tools.scrollable.mousewheel-1.0.1.js", __FILE__ ), array( 'jquery' ));
    
        wp_enqueue_script("json-parse2", plugins_url("lib/json2.js", __FILE__ ), array( 'jquery' ));
    }
    
    /*
     * This is an AJAX call that gets the posts between the from date 
     * and the to date.  
     */
    function edcal_posts() {
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        if (!$this->edcal_checknonce()) {
            die();
        }
        
        global $edcal_startDate, $edcal_endDate;
        
        $edcal_startDate = isset($_GET['from']) ? $_GET['from'] : null;
        $edcal_endDate = isset($_GET['to']) ? $_GET['to'] : null;
        global $post;
        $args = array(
            'posts_per_page' => -1,
            'post_status' => "publish&future&draft",
            'post_parent' => null // any parent
        );

        /* 
         * If we're in the specific post type case we need to add
         * the post type to our query.
         */
        $post_type = isset($_GET['post_type'])?$_GET['post_type']:null;
        if ($post_type) {
            $args['post_type'] = $post_type;
        }

        /* 
         * If we're getting the list of posts for the drafts drawer we
         * want to sort them by the post title.
         */
        if ($edcal_startDate == '00000000') {
            $args['orderby'] = 'title';
        }

        /* 
         * We add a WHERE clause to filter by calendar date and/or by whether
         * or not the posts have been scheduled to a specific date:
         * WHERE `post_date_gmt` = '0000-00-00 00:00:00'
         */
        add_filter( 'posts_where', array(&$this, 'edcal_filter_where' ));
        $myposts = query_posts($args);
        remove_filter( 'posts_where', array(&$this, 'edcal_filter_where' ));

        ?>[
        <?php
        $size = sizeof($myposts);
        
        for($i = 0; $i < $size; $i++) {    
            $post = $myposts[$i];
            $this->edcal_postJSON($post, $i < $size - 1);
        }
        
        ?> ]
        <?php
        
        die();
    }
    
    /*
     * This filter specifies a special WHERE clause so we just get the posts we're 
     * interested in for the last post.
     */
    function edcal_lastpost_filter_where($where = '') {
        $where .= " AND (`post_status` = 'draft' OR `post_status` = 'publish' OR `post_status` = 'future')";
        return $where;
    }
    
    /*
     * Get information about the last post (the one furthest in the future) and make
     * that information available to the JavaScript code so it can make the last post
     * button work.
     */
    function edcal_getLastPost() {
        $args = array(
            'posts_per_page' => 1,
            'post_parent' => null,
            'order' => 'DESC'
        );
        
        add_filter( 'posts_where', array(&$this, 'edcal_lastpost_filter_where' ));
        $myposts = query_posts($args);
        remove_filter( 'posts_where', array(&$this, 'edcal_lastpost_filter_where' ));
        
        if (sizeof($myposts) > 0) {
            $post = $myposts[0];
            setup_postdata($post);
            ?>
            edcal.lastPostDate = '<?php echo(date('dmY',strtotime($post->post_date))); ?>';
            edcal.lastPostId = '<?php echo($post->ID); ?>';
            <?php
        } else {
            ?>
            edcal.lastPostDate = '-1';
            edcal.lastPostId = '-1';
            <?php
        }
    }
    
    /*
     * This is for an AJAX call that returns a post with the specified ID
     */
    function edcal_getpost() {
        
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        // If nonce fails, return
        if (!$this->edcal_checknonce()) {
            die();
        }
        
        $post_id = isset($_GET['postid'])?intval($_GET['postid']):-1;
        
        // If a proper post_id wasn't passed, return
        if(!$post_id) die();
        
        $args = array(
            'post__in' => array($post_id)
        );
        
        /* 
         * If we're in the specific post type case we need to add
         * the post type to our query.
         */
        $post_type = isset($_GET['post_type'])?$_GET['post_type']:null;
        if ($post_type) {
            $args['post_type'] = $post_type;
        }
        
        $post = query_posts($args);
        
        // get_post and setup_postdata don't get along, so we're doing a mini-loop
        if(have_posts()) :
            while(have_posts()) : the_post();
                ?>
                {
                "post" :
                    <?php
                    $this->edcal_postJSON($post[0], false, true);
                    ?>
                }
                <?php
            endwhile;
        endif;
        die();
    }
    
    /*
     * Wrap php's json_encode() for a WP-specific apostrophe bug
     */
    function edcal_json_encode($string) {
        /*
         * WordPress escapes apostrophe's when they show up in post titles as &#039;
         * This is the HTML ASCII code for a straight apostrophe.  This works well
         * with Firefox, but IE complains with a very unhelpful error message.  We
         * can replace them with a right curly apostrophe since that works in IE
         * and Firefox. It is also a little nicer typographically.  
         */
        return json_encode(str_replace("&#039;", "&#146;", $string));
    }
    
    /* 
     * This helper functions gets the plural name of the post
     * type specified by the post_type parameter.
     */
    function edcal_get_posttype_multiplename() {
    
        $post_type = isset($_GET['post_type'])?$_GET['post_type']:null;
        if (!$post_type) {
            return __('Posts ', 'editorial-calendar');
        }
    
        $postTypeObj = get_post_type_object($post_type);
        return $postTypeObj->labels->name;
    }
    
    /* 
     * This helper functions gets the singular name of the post
     * type specified by the post_type parameter.
     */
    
    function edcal_get_posttype_singlename() {
    
        $post_type = isset($_GET['post_type'])?$_GET['post_type']:null;
        if (!$post_type) {
            return __('Post ', 'editorial-calendar');
        }
    
        $postTypeObj = get_post_type_object($post_type);
        return $postTypeObj->labels->singular_name;
    }
    
    /*
     * This function sets up the post data and prints out the values we
     * care about in a JSON data structure.  This prints out just the
     * value part. If $fullPost is set to true, post_content is also returned.
     */
    function edcal_postJSON($post, $addComma = true, $fullPost = false) {
        $timeFormat = get_option("time_format");
        if ($timeFormat == "g:i a") {
            $timeFormat = "ga";
        } else if ($timeFormat == "g:i A") {
            $timeFormat = "gA";
        } else if ($timeFormat == "H:i") {
            $timeFormat = "H";
        }
        
        setup_postdata($post);
        
        if (get_post_status() == 'auto-draft' || get_post_status() == 'inherit' || get_post_status() == 'trash' ) {
            /*
             * WordPress 3 added a new post status of auto-draft so
             * we want to hide them from the calendar. 
             * We also want to hide posts with type 'inherit'
             */
            return;
        }
        
        /* 
         * We want to return the type of each post as part of the
         * JSON data about that post.  Right now this will always
         * match the post_type parameter for the calendar, but in
         * the future we might support a mixed post type calendar
         * and this extra data will become useful.  Right now we
         * are using this data for the title on the quick edit form.
         */
        if( $this->supports_custom_types ) {
            $postTypeObj = get_post_type_object(get_post_type( $post ));
            $postTypeTitle = $postTypeObj->labels->singular_name;
        } else {
            $postTypeTitle = 'post';
        }

        $post_date_gmt = date('dmY',strtotime($post->post_date_gmt));
        if ($post_date_gmt == '01011970') {
            $post_date_gmt = '00000000';
        }
        
        /*
         * The date function in PHP isn't consistent in the way it handles
         * formatting dates that are all zeros.  In that case we can manually
         * format the all zeros date so it shows up properly.
         */
        if ($post->post_date_gmt == '0000-00-00 00:00:00') {
            $post_date_gmt = '00000000';
        }
        
        
        ?>
            {
                "date" : "<?php the_time('d') ?><?php the_time('m') ?><?php the_time('Y') ?>", 
                "date_gmt" : "<?php echo $post_date_gmt; ?>",
                "time" : "<?php echo trim(get_the_time()) ?>", 
                "formattedtime" : "<?php $this->edcal_json_encode(the_time($timeFormat)) ?>", 
                "sticky" : "<?php echo is_sticky($post->ID) ?>",
                "url" : "<?php $this->edcal_json_encode(the_permalink()) ?>", 
                "status" : "<?php echo get_post_status() ?>",
                "orig_status" : "<?php echo get_post_status() ?>",
                "title" : <?php echo $this->edcal_json_encode(get_the_title()) ?>,
                "author" : <?php echo $this->edcal_json_encode(get_the_author()) ?>,
                "type" : "<?php echo get_post_type( $post ) ?>",
                "typeTitle" : "<?php echo $postTypeTitle ?>",
    
                <?php if ( current_user_can('edit_post', $post->ID) ) {?>
                "editlink" : "<?php echo get_edit_post_link($post->ID) ?>",
                <?php } ?>
    
                <?php if ( current_user_can('delete_post', $post->ID) ) {?>
                "dellink" : "javascript:edcal.deletePost(<?php echo $post->ID ?>)",
                <?php } ?>
    
                "permalink" : "<?php echo get_permalink($post->ID) ?>",
                "id" : "<?php the_ID(); ?>"
                
                <?php if($fullPost) : ?>
                , "content" : <?php echo $this->edcal_json_encode($post->post_content) ?>
                
                <?php endif; ?>
            }
        <?php
        if ($addComma) {
            ?>,<?php
        }
    }
    
    /*
     * This is a helper AJAX function to delete a post. It gets called
     * when a user clicks the delete button, and allows the user to 
     * retain their position within the calendar without a page refresh.
     * It is not called unless the user has permission to delete the post.
     */
    function edcal_deletepost() {
        if (!$this->edcal_checknonce()) {
            die();
        }
    
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
        $post = get_post($edcal_postid, ARRAY_A);
        $title = $post['post_title'];
        $date = date('dmY', strtotime($post['post_date'])); // [TODO] : is there a better way to generate the date string ... ??
        $date_gmt = date('dmY',strtotime($post['post_date_gmt']));
        if ($date_gmt == '01011970') {
            $date_gmt = '00000000';
        }
        
        $force = !EMPTY_TRASH_DAYS;                    // wordpress 2.9 thing. deleted post hangs around (ie in a recycle bin) after deleted for this # of days
        if ( isset($post->post_type) && ($post->post_type == 'attachment' )) {
            $force = ( $force || !MEDIA_TRASH );
            if ( ! wp_delete_attachment($edcal_postid, $force) )
                wp_die( __('Error in deleting...') );
        } else {
            if ( !wp_delete_post($edcal_postid, $force) )
                wp_die( __('Error in deleting...') );
        }
    
    //    return the following info so that jQuery can then remove post from edcal display :
    ?>
    {
        "post" :
        {
            "date" : "<?php echo $date ?>", 
            "title" : "<?php echo $title ?>",
            "id" : "<?php echo $edcal_postid ?>",
            "date_gmt" : "<?php echo $date_gmt; ?>"
        }
    }
    <?php
    
        die();    
    }
    
    /*
     * This is a helper AJAX function to change the title of a post.  It
     * gets called from the save button in the tooltip when you change a
     * post title in a calendar.
     */
    function edcal_changetitle() {
        if (!$this->edcal_checknonce()) {
            die();
        }
    
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
        $edcal_newTitle = isset($_GET['title'])?$_GET['title']:null;
        
        $post = get_post($edcal_postid, ARRAY_A);
        setup_postdata($post);
        
        $post['post_title'] = wp_strip_all_tags($edcal_newTitle);
        
        /*
         * Now we finally update the post into the database
         */
        wp_update_post( $post );
        
        /*
         * We finish by returning the latest data for the post in the JSON
         */
        global $post;
        $args = array(
            'posts_id' => $edcal_postid,
        );
        
        $post = get_post($edcal_postid);
        
        ?>{
            "post" :
        <?php
        
            $this->edcal_postJSON($post);
        
        ?>
        }
        <?php
        
        
        die();
    }
    
    /*
     * This is a helper function to create a new blank draft
     * post on a specified date.
     */
    function edcal_newdraft() {
        if (!$this->edcal_checknonce()) {
            die();
        }
    
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        $edcal_date = isset($_POST["date"])?$_POST["date"]:null;
        
        $my_post = array();
        $my_post['post_title'] = isset($_POST["title"])?wp_strip_all_tags($_POST["title"]):null;
        $my_post['post_content'] = isset($_POST["content"])?$_POST["content"]:null;
        $my_post['post_status'] = 'draft';
        
        $my_post['post_date'] = $edcal_date;
        $my_post['post_date_gmt'] = get_gmt_from_date($edcal_date);
        $my_post['post_modified'] = $edcal_date;
        $my_post['post_modified_gmt'] = get_gmt_from_date($edcal_date);
        
        // Insert the post into the database
        $my_post_id = wp_insert_post( $my_post );
        
        /*
         * We finish by returning the latest data for the post in the JSON
         */
        global $post;
        $post = get_post($my_post_id);
    
        ?>{
            "post" :
        <?php
        
            $this->edcal_postJSON($post, false);
        
        ?>
        }
        <?php
        
        die();
    }
    
    /*
     * This is a helper function to create a new draft post on a specified date
     * or update an existing post.
     */
    function edcal_savepost() {
        
        if (!$this->edcal_checknonce()) {
            die();
        }
        
        // Most blogs have warnings turned off by default, but if they're
        // turned on the warnings can cause errors in the JSON data when
        // we change the post status so we set the warning level to hide
        // warnings and then reset it at the end of this function.
        $my_error_level = error_reporting();
        error_reporting(E_ERROR);
    
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        $edcal_date = isset($_POST["date"])?$_POST["date"]:null;
        $edcal_date_gmt = isset($_POST["date_gmt"])?$_POST["date_gmt"]:get_gmt_from_date($edcal_date);
        
        $my_post = array();
        
        // If the post id is not specified, we're creating a new post
        if($_POST['id'] && intval($_POST['id']) > 0) {
            $my_post['ID'] = intval($_POST['id']);
        } else {
            // We have a new post
            //$my_post['ID'] = 0; // and the post ID to 0
            
            // Set the status to draft unless the user otherwise specifies
            if ($_POST['status']) {
                $my_post['post_status'] = $_POST['status'];
            } else {
                $my_post['post_status'] = 'draft';
            }
        }
        
        $my_post['post_title'] = isset($_POST["title"])?wp_strip_all_tags($_POST["title"]):null;
        $my_post['post_content'] = isset($_POST["content"])?$_POST["content"]:null;
        
        if ($edcal_date_gmt != '0000-00-00 00:00:00' || $my_post['ID'] > 0) {
            /*
             * We don't want to set a date if this a new post in the drafts
             * drawer since WordPress 3.5 will reject new posts with a 0000 
             * GMT date.
             */
            $my_post['post_date'] = $edcal_date;
            $my_post['post_date_gmt'] = $edcal_date_gmt;
            $my_post['post_modified'] = $edcal_date;
            $my_post['post_modified_gmt'] = $edcal_date_gmt;
        }
        
        $my_post['post_status'] = $_POST['status'];
        
        /* 
         * When we create a new post we need to specify the post type
         * passed in from the JavaScript.
         */
        $post_type = isset($_POST["post_type"])?$_POST["post_type"]:null;
        if ($post_type) {
            $my_post['post_type'] = $post_type;
        }

        // If we are updating a post
        if($_POST['id']) {
            if ($_POST['status'] != $_POST['orig_status']) {
                wp_transition_post_status($_POST['status'], $_POST['orig_status'], $my_post);
                $my_post['post_status'] = $_POST['status'];
            }
            $my_post_id = wp_update_post($my_post);
        } else {
            // We have a new post, insert the post into the database
            $my_post_id = wp_insert_post($my_post, true);
        }
        
        // TODO: throw error if update/insert or getsinglepost fails
        /*
         * We finish by returning the latest data for the post in the JSON
         */
        $args = array(
            'post__in' => array($my_post_id)
        );
        
        if ($post_type) {
            $args['post_type'] = $post_type;
        }
        $post = query_posts($args);
        
        // get_post and setup_postdata don't get along, so we're doing a mini-loop
        if(have_posts()) :
            while(have_posts()) : the_post();
                ?>
                {
                "post" :
                    <?php
                    $this->edcal_postJSON($post[0], false);
                    ?>
                }
                <?php
            endwhile;
        endif;
        
        error_reporting($my_error_level);
        
        die();
    }
    
    /*
     * This function checks the nonce for the URL.  It returns
     * true if the nonce checks out and outputs a JSON error
     * and returns false otherwise.
     */
    function edcal_checknonce() {
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'edit-calendar')) {
           /*
             * This is just a sanity check to make sure
             * this isn't a CSRF attack.  Most of the time this
             * will never be run because you can't see the calendar unless
             * you are at least an editor
             */
            ?>
            {
                "error": <?php echo(EDCAL_NONCE_ERROR); ?>
            }
            <?php
            return false;
        }
        return true;
    }
    
    /*
     * This function changes the date on a post.  It does optimistic 
     * concurrency checking by comparing the original post date from
     * the browser with the one from the database.  If they don't match
     * then it returns an error code and the updated post data.
     *
     * If the call is successful then it returns the updated post data.
     */
    function edcal_changedate() {
        if (!$this->edcal_checknonce()) {
            die();
        }
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
        $edcal_newDate = isset($_GET['newdate'])?$_GET['newdate']:null;
        $edcal_oldDate = isset($_GET['olddate'])?$_GET['olddate']:null;
        $edcal_postStatus = isset($_GET['postStatus'])?$_GET['postStatus']:null;
        $move_to_drawer = $edcal_newDate == '0000-00-00';
        $move_from_drawer = $edcal_oldDate == '00000000';

        global $post;
        $args = array(
            'posts_id' => $edcal_postid,
        );
        $post = get_post($edcal_postid);
        setup_postdata($post);

        /*
         * Posts in WordPress have more than one date.  There is the GMT date,
         * the date in the local time zone, the modified date in GMT and the
         * modified date in the local time zone.  We update all of them.
         */
        if ( $move_from_drawer ) {
            /* 
             * Set the date to 'unscheduled' [ie. 0]. We use this date 
             * further down in the concurrency check, and this will make the dates
             * technically off by 10 hours, but it's still the same day. We only do 
             * this for posts that were created as drafts.  Works for now, but
             * we would have to revamp this if we use an actual timestamp check.
             */
            $post->post_date = '0000-00-00 ' . date('H:i:s', strtotime($post->post_date));
        } else if ( $move_to_drawer ) {
            // echo ( "\r\npost->post_date_gmt=".$post->post_date_gmt);
            $post->post_date_gmt = $post->post_date;
        } else {
            // set the scheduled time as our original time
            $post->post_date_gmt = $post->post_date;
        }
// echo ( "\r\npost->post_date_gmt = $post->post_date_gmt \r\npost->post_date = $post->post_date");

        /*
         * Error-checking:
         */
        $error = false;
        if (!current_user_can('edit_post', $edcal_postid)) {
            /*
             * This is just a sanity check to make sure that the current
             * user has permission to edit posts.  Most of the time this
             * will never be run because you can't see the calendar unless
             * you are at least an editor.
             */
            $error = EDCAL_PERMISSION_ERROR;
        } else if ( date('Y-m-d', strtotime($post->post_date)) != date('Y-m-d', strtotime($edcal_oldDate)) ) {
            /*
             * We are doing optimistic concurrency checking on the dates.  If
             * the user tries to move a post we want to make sure nobody else
             * has moved that post since the page was last updated.  If the 
             * old date in the database doesn't match the old date from the
             * browser then we return an error to the browser along with the
             * updated post data.
             */
            $error = EDCAL_CONCURRENCY_ERROR;
        }

        if ( $error ) {
            // die('error= '.$error);
            ?>
            {
                "error": <?php echo $error; ?>,
                "post" :
            <?php
                $this->edcal_postJSON($post, false, true);
            ?> }
            
            <?php
            die();
        }


        /*
         * No errors, so let's go create our new post parameters to update
         */
        
        $updated_post = array();
        $updated_post['ID'] = $edcal_postid;

        if ( !$move_to_drawer ) {
            $updated_post['post_date'] = $edcal_newDate . substr($post->post_date, strlen($edcal_newDate));
        }

        /*
         * When a user creates a draft and never sets a date or publishes it 
         * then the GMT date will have a timestamp of 00:00:00 to indicate 
         * that the date hasn't been set.  In that case we need to specify
         * an edit date or the wp_update_post function will strip our new
         * date out and leave the post as publish immediately.
         */
        $needsEditDate = preg_match( '/^0000/', $post->post_date_gmt );

        if ( $needsEditDate ) {
            // echo "\r\nneeds edit date\r\n";
            $updated_post['edit_date'] = $edcal_newDate . substr($post->post_date, strlen($edcal_newDate));
        }

        if ( $move_to_drawer ) {
            $updated_post['post_date_gmt'] = "0000-00-00 00:00:00";
            $updated_post['edit_date'] = $post->post_date;
        } else if ( $move_from_drawer ) {
            $updated_post['post_date_gmt'] = get_gmt_from_date($post->post_date);
            $updated_post['post_modified_gmt'] = get_gmt_from_date($post->post_date);
        }

        /*
         * We need to make sure to use the GMT formatting for the date.
         */
        if ( !$move_to_drawer ) {
            $updated_post['post_date_gmt'] = get_gmt_from_date($updated_post['post_date']);
            $updated_post['post_modified'] = $edcal_newDate . substr($post->post_modified, strlen($edcal_newDate));
            $updated_post['post_modified_gmt'] = get_gmt_from_date($updated_post['post_date']);
        }
        
        if ($edcal_postStatus != $post->post_status) {
            /*
             * We only want to update the post status if it has changed.
             * If the post status has changed that takes a few more steps
             */
            wp_transition_post_status($edcal_postStatus, $post->post_status, $post);
            $updated_post['post_status'] = $edcal_postStatus;
            
            // Update counts for the post's terms.
            foreach ( (array) get_object_taxonomies('post') as $taxonomy ) {
                $tt_ids = wp_get_object_terms($post_id, $taxonomy, 'fields=tt_ids');
                wp_update_term_count($tt_ids, $taxonomy);
            }
            
            do_action('edit_post', $edcal_postid, $post);
            do_action('save_post', $edcal_postid, $post);
            do_action('wp_insert_post', $edcal_postid, $post);
        }
        
// die(var_dump($updated_post).'success!');
        /*
         * Now we finally update the post into the database
         */
        wp_update_post( $updated_post );
        
        /*
         * We finish by returning the latest data for the post in the JSON
         */
        global $post;
        $args = array(
            'posts_id' => $edcal_postid,
        );
        
        $post = get_post($edcal_postid);
        ?>{
            "post" :
            
        <?php
            $this->edcal_postJSON($post, false, true);
        ?>}
        <?php
        
        die();
    }
    
    /*
     * This function saves the preferences
     */
    function edcal_saveoptions() {
        if (!$this->edcal_checknonce()) {
            die();
        }
    
        header("Content-Type: application/json");
        $this->edcal_addNoCacheHeaders();
        
        /*
         * The number of weeks preference
         */
        $edcal_weeks = isset($_GET['weeks'])?$_GET['weeks']:null;
        if ($edcal_weeks != null) {
            add_option("edcal_weeks_pref", $edcal_weeks, "", "yes");
            update_option("edcal_weeks_pref", $edcal_weeks);
        }
        
        /*
         * The show author preference
         */
        $edcal_author = isset($_GET['author-hide'])?$_GET['author-hide']:null;
        if ($edcal_author != null) {
            add_option("edcal_author_pref", $edcal_author, "", "yes");
            update_option("edcal_author_pref", $edcal_author);
        }
        
        /*
         * The show status preference
         */
        $edcal_status = isset($_GET['status-hide'])?$_GET['status-hide']:null;
        if ($edcal_status != null) {
            add_option("edcal_status_pref", $edcal_status, "", "yes");
            update_option("edcal_status_pref", $edcal_status);
        }
        
        /*
         * The show time preference
         */
        $edcal_time = isset($_GET['time-hide'])?$_GET['time-hide']:null;
        if ($edcal_time != null) {
            add_option("edcal_time_pref", $edcal_time, "", "yes");
            update_option("edcal_time_pref", $edcal_time);
        }
    
        /*
         * The edcal feedback preference
         */
        $edcal_feedback = isset($_GET['dofeedback'])?$_GET['dofeedback']:null;
        if ($edcal_feedback != null) {
            add_option("edcal_do_feedback", $edcal_feedback, "", "yes");
            update_option("edcal_do_feedback", $edcal_feedback);
        }
        
        /*
         * We finish by returning the latest data for the post in the JSON
         */
        ?>{
            "update" : "success"
        }
        <?php
        
        die();
    }
    
    /*
     * Add the no cache headers to make sure that our responses aren't
     * cached by the browser.
     */
    function edcal_addNoCacheHeaders() {
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    }

}

?>