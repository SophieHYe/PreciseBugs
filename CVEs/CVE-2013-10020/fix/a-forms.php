<?php
/*
Plugin Name: A Forms
Plugin URI: http://wordpress.org/extend/plugins/a-forms/
Description: Adds a contact form to your wordpress site.

Installation:

1) Install WordPress 3.6 or higher

2) Download the latest from:

http://wordpress.org/extend/plugins/tom-m8te 

http://wordpress.org/extend/plugins/jquery-ui-theme 

http://wordpress.org/extend/plugins/a-forms

3) Login to WordPress admin, click on Plugins / Add New / Upload, then upload the zip file you just downloaded.

4) Activate the plugin.

Version: 1.4.3
Author: TheOnlineHero - Tom Skroza
License: GPL2
*/

require_once("a-form.php");
require_once("a-form-section.php");
require_once("a-form-fields.php");
require_once("a-forms-path.php");
include_once (dirname (__FILE__) . '/tinymce/tinymce.php'); 

define(__AFORMS_DEFAULT_LIMIT__, "10");

function a_forms_activate() {
  global $wpdb;

  $a_form_forms_table = $wpdb->prefix . "a_form_forms";
  $checktable = $wpdb->query("SHOW TABLES LIKE '$a_form_forms_table'");
  if ($checktable == 0) {

    $sql = "CREATE TABLE $a_form_forms_table (
      ID mediumint(9) NOT NULL AUTO_INCREMENT, 
      form_name VARCHAR(255) DEFAULT '',
      to_email VARCHAR(255) DEFAULT '',
      to_cc_email VARCHAR(255) DEFAULT '',
      to_bcc_email VARCHAR(255) DEFAULT '',
      subject VARCHAR(255) DEFAULT '',
      show_section_names tinyint(4) NOT NULL DEFAULT 1,
      field_name_id mediumint(9), 
      field_email_id mediumint(9), 
      field_subject_id mediumint(9), 
      send_confirmation_email tinyint(4) NOT NULL DEFAULT 0,
      confirmation_from_email VARCHAR(255) DEFAULT '',
      success_message longtext DEFAULT '',
      success_redirect_url VARCHAR(255) DEFAULT '',
      include_captcha tinyint(4) NOT NULL DEFAULT 0,
      tracking_enabled tinyint(4) NOT NULL DEFAULT 1,
      created_at DATETIME,
      updated_at DATETIME,
      PRIMARY KEY  (ID),
      UNIQUE (form_name)
    )";
    $wpdb->query($sql); 

    $a_form_sections_table = $wpdb->prefix . "a_form_sections";
    $sql = "CREATE TABLE $a_form_sections_table (
      ID mediumint(9) NOT NULL AUTO_INCREMENT, 
      section_name VARCHAR(255) DEFAULT '',
      section_order mediumint(9) NOT NULL DEFAULT 0, 
      form_id mediumint(9) NOT NULL, 
      created_at DATETIME,
      updated_at DATETIME,
      PRIMARY KEY  (ID)
    )";
    $wpdb->query($sql); 

    $a_form_fields_table = $wpdb->prefix . "a_form_fields";
    $sql = "CREATE TABLE $a_form_fields_table (
      FID mediumint(9) NOT NULL AUTO_INCREMENT, 
      field_type VARCHAR(255) DEFAULT '',
      field_label VARCHAR(255) DEFAULT '', 
      value_options VARCHAR(255) DEFAULT '',
      field_order mediumint(9) NOT NULL DEFAULT 0, 
      validation VARCHAR(255) DEFAULT '',
      file_ext_allowed VARCHAR(255) DEFAULT '',
      form_id mediumint(9) NOT NULL,
      section_id mediumint(9) NOT NULL,
      created_at DATETIME,
      updated_at DATETIME,
      PRIMARY KEY  (FID)
    )";
    $wpdb->query($sql);

    $a_form_tracks_table = $wpdb->prefix . "a_form_tracks";
    $sql = "CREATE TABLE $a_form_tracks_table (
      ID mediumint(9) NOT NULL AUTO_INCREMENT, 
      content longtext NOT NULL,
      track_type VARCHAR(255) DEFAULT '',
      form_id mediumint(9) NOT NULL,
      referrer_url VARCHAR(255) DEFAULT '',
      fields_array mediumtext DEFAULT '',
      created_at DATETIME,
      updated_at DATETIME,
      PRIMARY KEY  (ID)
    )";
    $wpdb->query($sql);

  }

  $checkcol = $wpdb->query("SHOW COLUMNS FROM '$a_form_forms_table' LIKE 'include_admin_in_emails'");
  if ($checkcol == 0) {
    $sql = "ALTER TABLE $a_form_forms_table ADD include_admin_in_emails VARCHAR(1)";
    $wpdb->query($sql); 
  }

  $checkcol = $wpdb->query("SHOW COLUMNS FROM '$a_form_forms_table' LIKE 'captcha_type'");
  if ($checkcol == 0) {
    $sql = "ALTER TABLE $a_form_forms_table ADD captcha_type VARCHAR(1) DEFAULT '0'";
    $wpdb->query($sql); 
  }

  if (!is_dir(get_template_directory()."/aforms_css")) {
    aform_copy_directory(AFormsPath::normalize(dirname(__FILE__)."/css"), get_template_directory());  
  } else {
    add_option("aform_current_css_file", "default.css");
  }

}
register_activation_hook( __FILE__, 'a_forms_activate' );

//call register settings function
add_action( 'admin_init', 'register_a_forms_settings' );
function register_a_forms_settings() {
  register_setting( 'a-forms-settings-group', 'a_forms_admin_email' );
  register_setting( 'a-forms-settings-group', 'a_forms_mail_host' );
  register_setting( 'a-forms-settings-group', 'a_forms_smtp_auth' );
  register_setting( 'a-forms-settings-group', 'a_forms_smtp_port' );
  register_setting( 'a-forms-settings-group', 'a_forms_enable_tls' );
  register_setting( 'a-forms-settings-group', 'a_forms_enable_ssl' );
  register_setting( 'a-forms-settings-group', 'a_forms_smtp_username' );
  register_setting( 'a-forms-settings-group', 'a_forms_smtp_password' );
}

function are_a_forms_dependencies_installed() {
  return is_plugin_active("tom-m8te/tom-m8te.php") && is_plugin_active("jquery-ui-theme/jquery-ui-theme.php");
}

add_action( 'admin_notices', 'a_forms_notice_notice' );
function a_forms_notice_notice(){
  $activate_nonce = wp_create_nonce( "activate-a-forms-dependencies" );
  $tom_active = is_plugin_active("tom-m8te/tom-m8te.php");
  $jquery_ui_theme_active = is_plugin_active("jquery-ui-theme/jquery-ui-theme.php");
  if (!($tom_active && $jquery_ui_theme_active)) { ?>
    <div class='updated below-h2'><p>Before you can use A Forms, please install/activate the following plugin(s):</p>
    <ul>
      <?php if (!$tom_active) { ?>
        <li>
          <a target="_blank" href="http://wordpress.org/extend/plugins/tom-m8te/">Tom M8te</a> 
           &#8211; 
          <?php if (file_exists(ABSPATH."/wp-content/plugins/tom-m8te/tom-m8te.php")) { ?>
            <a href="<?php echo(get_option("siteurl")); ?>/wp-admin/?a_forms_install_dependency=tom-m8te&_wpnonce=<?php echo($activate_nonce); ?>">Activate</a>
          <?php } else { ?>
            <a href="<?php echo(get_option("siteurl")); ?>/wp-admin/plugin-install.php?tab=plugin-information&plugin=tom-m8te&_wpnonce=<?php echo($activate_nonce); ?>&TB_iframe=true&width=640&height=876">Install</a> 
          <?php } ?>
        </li>
      <?php }
      if (!$jquery_ui_theme_active) { ?>
        <li>
          <a target="_blank" href="http://wordpress.org/extend/plugins/jquery-ui-theme/">JQuery UI Theme</a>
           &#8211; 
          <?php if (file_exists(ABSPATH."/wp-content/plugins/jquery-ui-theme/jquery-ui-theme.php")) { ?>
            <a href="<?php echo(get_option("siteurl")); ?>/wp-admin/?a_forms_install_dependency=jquery-ui-theme&_wpnonce=<?php echo($activate_nonce); ?>">Activate</a>
          <?php } else { ?>
            <a href="<?php echo(get_option("siteurl")); ?>/wp-admin/plugin-install.php?tab=plugin-information&plugin=jquery-ui-theme&_wpnonce=<?php echo($activate_nonce); ?>&TB_iframe=true&width=640&height=876">Install</a> 
          <?php } ?>
        </li>
      <?php } ?>
    </ul>
    </div>
    <?php
  }

}

add_action( 'admin_init', 'register_a_forms_install_dependency_settings' );
function register_a_forms_install_dependency_settings() {
  if (isset($_GET["a_forms_install_dependency"])) {
    if (wp_verify_nonce($_REQUEST['_wpnonce'], "activate-a-forms-dependencies")) {
      switch ($_GET["a_forms_install_dependency"]) {
        case 'jquery-ui-theme':
          activate_plugin('jquery-ui-theme/jquery-ui-theme.php', 'plugins.php?error=false&plugin=jquery-ui-theme.php');
          wp_redirect(get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms.php");
          exit();
          break; 
        case 'tom-m8te':  
          activate_plugin('tom-m8te/tom-m8te.php', 'plugins.php?error=false&plugin=tom-m8te.php');
          wp_redirect(get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms.php");
          exit();
          break;   
        default:
          throw new Exception("Sorry unable to install plugin.");
          break;
      }
    } else {
      die("Security Check Failed.");
    }
  }
}

add_action('admin_menu', 'register_a_forms_page');
function register_a_forms_page() {
  if (are_a_forms_dependencies_installed()) {
    add_menu_page('A Forms', 'A Forms', 'manage_options', 'a-forms/a-forms.php', 'a_form_initial_page');
    add_submenu_page('a-forms/a-forms.php', 'Settings', 'Settings', 'manage_options', 'a-forms/a-forms-settings.php', 'a_form_settings_page');
    add_submenu_page('a-forms/a-forms.php', 'Tracking', 'Tracking', 'manage_options', 'a-forms/a-forms-tracking.php', 'a_form_tracking_page');
    add_submenu_page('a-forms/a-forms.php', 'Styling', 'Styling', 'update_themes', 'a-forms/a-forms-styling.php');
  }
}

add_action('wp_ajax_aform_css_file_selector', 'aform_css_file_selector');
function aform_css_file_selector() {
  if (are_a_forms_dependencies_installed()) {
    update_option("aform_current_css_file", ($_POST["css_file_selection"]));
    echo(@file_get_contents(get_template_directory()."/aforms_css/".($_POST["css_file_selection"])));
  }
  die();  
}

add_action('wp_ajax_add_field_to_section', 'add_field_to_section');
function add_field_to_section() {
  global $wpdb;
  $section = tom_get_row_by_id("a_form_sections", "*", "ID", ($_POST["section_id"]));
  tom_insert_record("a_form_fields", array("field_order" => ($_POST["field_order"]), "section_id" => ($_POST["section_id"]), "form_id" => $section->form_id));
  echo $section->ID."::".$wpdb->insert_id;
  die();  
}


add_action('wp_ajax_aforms_tinymce', 'aforms_tinymce');
/**
 * Call TinyMCE window content via admin-ajax
 * 
 * @since 1.7.0 
 * @return html content
 */
function aforms_tinymce() {
  if (are_a_forms_dependencies_installed()) {
    // check for rights
    if ( !current_user_can('edit_pages') && !current_user_can('edit_posts') ) 
      die(__("You are not allowed to be here"));
          
    include_once( dirname( dirname(__FILE__) ) . '/a-forms/tinymce/window.php');
    
    die(); 
  } 
}

function a_form_initial_page() {
  wp_enqueue_script('jquery');
  wp_enqueue_script('jquery-ui-sortable');

  wp_register_script("a-forms", plugins_url("/js/application.js", __FILE__));
  wp_enqueue_script("a-forms");

  wp_localize_script( 'a-forms', 'AFormsAjax', array(
    "ajax_url" => admin_url('admin-ajax.php'),
    "base_url" => get_option('siteurl')."/wp-admin/admin.php?page=a-forms/a-forms.php",
    "sort_section_url" => get_option('siteurl')."/wp-admin/admin.php?page=a-forms/a-forms.php&a_form_page=section_section_sort",
    "sort_field_url" => get_option('siteurl')."/wp-admin/admin.php?page=a-forms/a-forms.php&a_form_page=section_field_sort"
  ));

  wp_register_style("a-forms", plugins_url("/admin_css/style.css", __FILE__));
  wp_enqueue_style("a-forms");

  // If you don't use Securimage and Tom M8te is not setup to use Securimage, then ...
  if (get_option("include_securimage") != "1" && !class_exists("Securimage")) {
    // Make Tom M8te use Securimage.
    update_option("include_securimage", "1");
  } 

  if ((tom_get_query_string_value("a_form_page")) == "fields") {
    if (($_GET["action"]) == "delete") {
      AFormFields::delete();
    }
  }

  if (tom_get_query_string_value("a_form_page") == "section") {
    a_form_section_page();
  } else if (tom_get_query_string_value("a_form_page") == "section_section_sort") {
    tom_update_record_by_id("a_form_sections", array("section_order" => ($_POST["section_order"])), "ID", ($_POST["ID"]));
    exit;
  } else if (tom_get_query_string_value("a_form_page") == "section_field_sort") {
    tom_update_record_by_id("a_form_fields", array("field_order" => ($_POST["field_order"]), "section_id" => ($_POST["section_id"])), "FID", ($_POST["FID"]));
    exit;
  } else if (tom_get_query_string_value("a_form_page") == "create_field") {

    exit;
  } else {
    a_form_page();
  }
  ?>
  <div class="clear"></div>
  <?php 
    tom_add_social_share_links("http://wordpress.org/extend/plugins/a-forms/");
}

function a_form_page() {
  if (tom_get_query_string_value("a_form_page") != "section") {
    if (isset($_POST["action"])) {
      $action = ($_POST["action"]);
      if ($action == "Update") {
        AForm::update();
      }
      if ($action == "Create") {
        AForm::create();
      }
    }
    if ($_GET["action"] == "delete") {
      AForm::delete();
    }    
  }
  ?>
  
  <div class="wrap a-form">
  <h2>A Forms <a class="add-new-h2" href="<?php echo(get_option('siteurl')); ?>/wp-admin/admin.php?page=a-forms/a-forms.php&action=new">Add New Form</a></h2>
  <?php

  if (isset($_GET["message"]) && $_GET["message"] != "") {
    echo("<div class='updated below-h2'><p>".($_GET["message"])."</p></div>");
  }

  if (isset($_GET["action"]) && $_GET["action"] != "delete") {
    if ($_GET["action"] == "edit") {
      // Display Edit Page
      $a_form = tom_get_row_by_id("a_form_forms", "*", "ID", ($_GET["id"])); ?>

        <div class="postbox " style="display: block; ">
        <div class="inside">
          <form action="" method="post">
            <?php AForm::render_admin_a_form_forms_form($a_form, "Update"); ?>
          </form>
        </div>
        </div>

    
    <?php }

    if (($_GET["action"]) == "new") {
      // Display New Page
      ?>

        <div class="postbox " style="display: block; ">
        <div class="inside">
          <form action="" method="post">
            <?php 

            if (!isset($_POST["to_email"])) {
              $_POST["to_email"] = get_option("admin_email");
            }
            if (!isset($_POST["show_section_names"])) {
              $_POST["show_section_names"] = "1";
            }
            if (!isset($_POST["tracking_enabled"])) {
              $_POST["tracking_enabled"] = "1";
            }

            AForm::render_admin_a_form_forms_form(null, "Create"); ?>
          </form>
        </div>
        </div>
    <?php }

  } else { ?>


      <div class="postbox " style="display: block; ">
      <div class="inside">
        <?php

        $forms = tom_get_results("a_form_forms", "*", "");
        if (count($forms) == 0) {
          $url = get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms.php&action=new";
          tom_javascript_redirect_to($url, "<p>Start by creating a form.</p>");
        } else {
          tom_generate_datatable("a_form_forms", array("ID", "form_name", "include_admin_in_emails", "to_email", "tracking_enabled"), "ID", "", array("form_name ASC"), __AFORMS_DEFAULT_LIMIT__, get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms.php", false, true, true, true, true);   
        }
        ?>
      </div>
      </div>
    <?php
  }
  ?>
  </div>
  <?php
}

function a_form_section_page() {
  if (isset($_POST["action"])) {
    if ($_POST["action"] == "Update") {
      AFormSection::update();
    }
    if ($_POST["action"] == "Create") {
      AFormSection::create();
    }
  }
  if ($_GET["action"] == "delete") {
    AFormSection::delete();
  }

  ?>
  
  <div class="wrap a-form">
  <h2>A Forms <a class="add-new-h2" href="<?php echo(get_option('siteurl')); ?>/wp-admin/admin.php?page=a-forms/a-forms.php&action=new">Add New Form</a></h2>
  
  <?php

  if (isset($_GET["message"]) && $_GET["message"] != "") {
    echo("<div class='updated below-h2'><p>".($_GET["message"])."</p></div>");
  }

  if (isset($_GET["action"]) && $_GET["action"] != "delete") {
    if ($_GET["action"] == "edit") {
      // Display Edit Page
      $a_form = tom_get_row_by_id("a_form_sections", "*", "ID", ($_GET["id"])); ?>
      <div class="postbox " style="display: block; ">
      <div class="inside">
        <form action="" method="post">
          <?php AFormSection::render_admin_a_form_sections_form($a_form, "Update"); ?>
        </form>
      </div>
      </div>
      </div>
    <?php }

    if (($_GET["action"]) == "new") {
      // Display New Page
      ?>
      <div class="postbox " style="display: block; ">
      <div class="inside">
        <form action="" method="post">
          <?php AFormSection::render_admin_a_form_sections_form(null, "Create"); ?>
        </form>
      </div>
      </div>
      </div>
    <?php }
  }
}

function a_form_settings_page() { ?>
  <div class="wrap">
  <h2>Settings</h2>
  <div class="postbox " style="display: block; ">
  <div class="inside">
  <form method="post" action="options.php">
    <?php settings_fields( 'a-forms-settings-group' ); ?>
    <h3>Admin Settings</h3>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="a_forms_admin_email">Admin Email:</label>
          </th>
          <td>
            <input type="text" id="a_forms_admin_email" name="a_forms_admin_email" value="<?php echo get_option('a_forms_admin_email'); ?>" />
            <span class="example">e.g: admin@yourcompany.com.au</span>
          </td>
        </tr>
      </tbody>
    </table>

    <h3>SMTP Settings</h3>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="a_forms_mail_host">Mail Host:</label>
          </th>
          <td>
            <input type="text" id="a_forms_mail_host" name="a_forms_mail_host" value="<?php echo get_option('a_forms_mail_host'); ?>" />
            <span class="example">e.g: mail.yourdomain.com</span>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_smtp_auth">Enable SMTP Authentication:</label>
          </th>
          <td>
            <input type="hidden" name="a_forms_smtp_auth" value="0">
            <input type="checkbox" id="a_forms_smtp_auth" name="a_forms_smtp_auth" value="1" <?php if (get_option('a_forms_smtp_auth')) {echo "checked";} ?> />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_enable_tls">Enable TLS:</label>
          </th>
          <td>
            <input type="hidden" name="a_forms_enable_tls" value="0">
            <input type="checkbox" id="a_forms_enable_tls" name="a_forms_enable_tls" value="1" <?php if (get_option('a_forms_enable_tls')) {echo "checked";} ?> />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_enable_ssl">Enable SSL:</label>
          </th>
          <td>
            <input type="hidden" name="a_forms_enable_ssl" value="0">
            <input type="checkbox" id="a_forms_enable_ssl" name="a_forms_enable_ssl" value="1" <?php if (get_option('a_forms_enable_ssl')) {echo "checked";} ?> />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_smtp_port">SMTP Port:</label>
          </th>
          <td>
            <input type="text" id="a_forms_smtp_port" name="a_forms_smtp_port" value="<?php echo get_option('a_forms_smtp_port'); ?>" />
            <span class="example">e.g: 26</span>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_smtp_username">SMTP Username:</label>
          </th>
          <td>
            <input type="text" id="a_forms_smtp_username" name="a_forms_smtp_username" value="<?php echo get_option('a_forms_smtp_username'); ?>" />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <label for="a_forms_smtp_password">SMTP Password:</label>
          </th>
          <td>
            <input type="password" id="a_forms_smtp_password" name="a_forms_smtp_password" value="<?php echo get_option('a_forms_smtp_password'); ?>" />
          </td>
        </tr>
    
      </tbody>
    </table>

    <p class="submit">
      <input type="submit" name="Submit" value="Update Settings">
    </p>

  </form>
  </div>
  </div>
  </div>
<?php
  tom_add_social_share_links("http://wordpress.org/extend/plugins/a-forms/");
}

function a_form_tracking_page() { 
  wp_enqueue_script('jquery');
  wp_enqueue_script('jquery-ui-datepicker');
  wp_register_script("a-forms", plugins_url("/js/application.js", __FILE__));
  wp_enqueue_script('jquery-ui-sortable');
  wp_enqueue_script("a-forms");
  wp_register_style("a-forms", plugins_url("/admin_css/style.css", __FILE__));
  wp_enqueue_style("a-forms");

  wp_enqueue_style("jquery-ui-core");
  wp_enqueue_style("jquery-ui");
  wp_enqueue_style("jquery-ui-datepicker");
  ?>

  <script language="javascript">
  jQuery(function() {
    jQuery('.datepicker').datepicker({
      dateFormat : 'yy-m-d',
      showOn: "button",
      buttonImage: "<?php echo(plugins_url( '/images/calendar.gif', __FILE__ )); ?>",
      buttonImageOnly: true
    });
  });
  </script>

  <div class="wrap">
  <h2>Tracking</h2>
  <?php if ((tom_get_query_string_value("id")) != "") { 
    if ((tom_get_query_string_value("action")) != "view") { ?>
      <form action="" method="post">
        <?php tom_add_form_field(null, "text", "Search Text", "search_text", "search_text", array(), "p", array()); ?>
        <?php tom_add_form_field(null, "text", "Date From", "search_date_from", "search_date_from", array("class" => "datepicker"), "p", array()); ?>
        <?php tom_add_form_field(null, "text", "Date To", "search_date_to", "search_date_to", array("class" => "datepicker"), "p", array()); ?>
        <p><input type="submit" name="action" value="Search" /></p>
      </form>
    <?php } ?>
  <?php } ?>
  <?php 
    if (!isset($_GET["action"])) {
      tom_generate_datatable("a_form_forms", array("ID", "form_name"), "ID", "", array(), "30", "?page=a-forms/a-forms-tracking.php", true, false, false, false, true, "Y-m-d", array()); 
    } else if ($_GET["action"] == "show") {
      $limit_clause = "10";
      
      $page_no = 0;
      if (isset($_GET["a_form_tracks_page"])) {
        $page_no = ($_GET["a_form_tracks_page"]);
      }
      $offset = $page_no * $limit_clause;
      $where_sql = "form_id=".($_GET["id"]);
      if ((tom_get_query_string_value("search_text")) != "") {
        $where_sql .= " AND content LIKE '%".($_POST["search_text"])."%'";
      }

      if (((tom_get_query_string_value("search_date_from")) != null) && ((tom_get_query_string_value("search_date_to")) != null)) {
        $where_sql .= " AND (created_at BETWEEN '".(tom_get_query_string_value("search_date_from"))." 00:00:00' AND '".(tom_get_query_string_value("search_date_to"))." 23:59:59')";
      } else if ((tom_get_query_string_value("search_date_from")) != null) {
        $where_sql .= " AND created_at > '".(tom_get_query_string_value("search_date_from"))." 00:00:00'";
      } else if ((tom_get_query_string_value("search_date_to")) != null) {
        $where_sql .= " AND created_at < '".(tom_get_query_string_value("search_date_to"))." 23:59:59'";
      }

      $tracks = tom_get_results("a_form_tracks", "*", $where_sql, array("created_at DESC"), "$limit_clause OFFSET $offset");
      $fields = tom_get_results("a_form_fields", "*", "form_id=".($_GET["id"]), array());
      
      $total_tracks = count(tom_get_results("a_form_tracks", "*", $where_sql, array("created_at DESC")));

      if ($total_tracks > 0) {
        tom_generate_datatable_pagination("a_form_tracks", $total_tracks, $limit_clause, ($_GET["a_form_tracks_page"]), "?page=a-forms/a-forms-tracking.php&action=show&id=".($_GET["id"])."&search_text=".(tom_get_query_string_value("search_text"))."&search_date_from=".(tom_get_query_string_value("search_date_from"))."&search_date_to=".(tom_get_query_string_value("search_date_to")), "ASC", "top");
      ?>
        <table id="tracking">
          <thead>
            <tr>
              <td>ID</td>
              <?php           
                foreach ($fields as $field) {
                  echo("<th>".$field->field_label."</th>");
                }
              ?>
              <th>Referrer URL</th>
              <th>Date Sent</th>
              <th>View Form</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tracks as $track) {
              $fields_array = unserialize($track->fields_array); 
              echo("<tr><td>".$track->ID."</td>");
              foreach ($fields as $field) {
                $content = $fields_array[str_replace(" ", "_", strtolower($field->field_label))];
                echo("<td>");
                if ($content != "" && $field->field_type == "file") {
                  echo("<a href='".get_option("siteurl")."/wp-content/plugins/tom-m8te/tom-download-file.php?file=".$content."'>download</a>");
                } else {
                  echo(preg_replace("/, $/", "", esc_html($content)));
                }
                echo("</td>");
              }

              echo("<td>".$track->referrer_url."</td>");
              echo("<td>".gmdate("Y-m-d H:i:s", strtotime($track->created_at ))." GMT</td>");
              echo("<td><a href='".
get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms-tracking.php&action=view&id=".$track->ID."'>View</a></td>");
              echo("</tr>");
            }?>
          </tbody>
        </table>
        <?php
        tom_generate_datatable_pagination("a_form_tracks", $total_tracks, $limit_clause, $_GET["a_form_tracks_page"], "?page=a-forms/a-forms-tracking.php&action=show&id=".$_GET["id"], "ASC", "bottom");
      } else {
        echo("<p>No records found!</p>");
      }
    } else if ($_GET["action"] == "view") {
      $view = tom_get_row_by_id("a_form_tracks", "*", "ID", $_GET["id"]);
      echo "<p><textarea rows='40' cols='160'>".esc_html(stripcslashes($view->content))."</textarea></p>";
      echo("<p><a href='".get_option("siteurl")."/wp-admin/admin.php?page=a-forms/a-forms-tracking.php&action=show&id=".$view->form_id."'>Back</a></p>");
    }
    
  ?>
  </div>
  <?php
  tom_add_social_share_links("http://wordpress.org/extend/plugins/a-forms/");
}

add_shortcode( 'a-form', 'a_form_shortcode' );

function a_form_shortcode($atts) {
  if (is_plugin_active("tom-m8te/tom-m8te.php") && is_plugin_active("jquery-ui-theme/jquery-ui-theme.php")) {

    $nonce_passed = true;
    $return_content = "";
    $email_content = "";
    $validation_array = array();
    $from_email = get_option("admin_email");
    $current_datetime = gmdate( 'Y-m-d H:i:s');

    $form = tom_get_row_by_id("a_form_forms", "*", "ID", $atts["id"]);
    $sections = tom_get_results("a_form_sections", "*", "form_id='".$atts["id"]."'", array("section_order ASC"));

    $form_name = "a_form_".str_replace(" ", "_", strtolower($form->form_name))."_";

    $form_valid = true;
    $section_index = 0;

    if (isset($_POST["send_a_form_section"])) {
      $section_index = ($_POST["send_a_form_section"]);
    } else {
      $section_index = 0;
    }

    // Get this section.
    $section = $sections[$section_index];

    // Add validation for this section only.
    $fields = tom_get_results("a_form_fields", "*", "section_id='".$section->ID."'");
    foreach ($fields as $field) {
      $field_name = str_replace(" ", "_", strtolower($field->field_label));
      $validation_array[$form_name.$field_name] = $field->validation;
    }

    // Check to see if User submits a form action.
    if (isset($_POST["send_a_form"]) && ($atts["id"] == $_POST["send_a_form"])) {

      // User has submitted an aform.
      $captcha_valid = true;
      $form_valid = tom_validate_form($validation_array);

      $field_values = array();
      $attachment_urls = array();

      if (isset($_POST["a_form_attachment_urls"]) && $_POST["a_form_attachment_urls"] != "") {
        $attachment_urls = explode("::", ($_POST["a_form_attachment_urls"]));
      }

      // Construct email content.
      $all_fields = tom_get_results("a_form_fields", "*", "form_id='".$atts["id"]."'");
      foreach ($all_fields as $field) {
        $field_name = str_replace(" ", "_", strtolower($field->field_label));

        if ($field->field_type == "checkbox") {
          $i = 0;
          $email_content .= $field->field_label.": ";
          $answers = "";
          foreach (explode(",", $field->value_options) as $key) {
            if (($_POST[$form_name.$field_name."_".$i]) != "") {
              $content = str_replace('\"', "\"", ($_POST[$form_name.$field_name."_".$i]));
              $content = str_replace("\'", '\'', $content);
              $answers .= $content.", ";
            }
            $i++;
          }
          $email_content .= preg_replace("/, $/", "", $answers);
          $email_content .= "\n\n";
          $field_values[$field_name] = $answers;
        } else if ($field->field_type == "file") {
          // Upload file.

          try {
            $filedst = AForm::upload_file($form_name.$field_name, $field->file_ext_allowed);
            array_push($attachment_urls, $form_name.$field_name."=>".$filedst);
          } catch(Exception $ex) {
            $form_valid = false;
            $_SESSION[$form_name.$field_name."_error"] = $ex->getMessage();
          }
          
          if ($filedst != "") {
            $field_values[$field_name] = $filedst;
          } else {
            if (($_POST["a_form_attachment_urls"]) != "") {
              $records = explode("::", ($_POST["a_form_attachment_urls"]));
              foreach ($records as $record) {
                $key_value = explode("=>", $record);
                if ($key_value[0] == $form_name.$field_name && $key_value[1] != "") {
                  $field_values[$field_name] = $key_value[1];
                }
              }
            }
          }
          
        } else {
          $content = str_replace('\"', "\"", ($_POST[$form_name.$field_name]));
          $content = str_replace("\'", '\'', $content);
          $email_content .= $field->field_label.": ".$content."\n\n";
          $field_values[$field_name] = $content;
        }
        
      }

      // Check to see if the user has clicked the Send button and check to see if the form is using a captcha.
      if (isset($_POST["action"]) && $_POST["action"] == "Send" && isset($_POST[$form_name."captcha"]) && $form->include_captcha) {

        // User clicked on Send button and the form has a captcha.
        // Check the type of captcha.
        if ($form->captcha_type == "0") {
          // Form is using the Securimage Captcha.
          $captcha_valid = tom_check_captcha($form_name."captcha");
        } else {
          // Form is using the Math Captcha.

          // Check that the answer is first number plus second number.
          $captcha_valid = 
          (
            (
              ($_POST[aform_field_name($form, "captcha_first_number")]) 
              + 
              ($_POST[aform_field_name($form, "captcha_second_number")])
            ) 
            == ($_POST[aform_field_name($form, "captcha")])
          );

          // Check to see if captcha is valid.
          if ($captcha_valid == false) {
            // Captcha is invalid, so display error message.
            $_SESSION["a_form_".str_replace(" ", "_", strtolower($form->form_name))."_captcha_error"] = "invalid captcha code, try again!";
          }
        }
      }
      
      // Check to see if form is valid.
      $nonce_passed = wp_verify_nonce($_REQUEST["_wpnonce"], "a-forms-contact-a-form");
      if ($nonce_passed && $form_valid && $captcha_valid) {
        // Form is valid.
        if (($_POST["action"]) == "Send") {
          // User clicked Send, so since form is valid and they click Send, send the email.

          $subject = $form->subject;
          $from_name = "";
          $user_email = "";
          if ($form->field_name_id != "") {
            $row = tom_get_row_by_id("a_form_fields", "*", "FID", $form->field_name_id);
            $from_name = ($_POST[$form_name.str_replace(" ", "_", strtolower($row->field_label))]);
          }
          if ($form->field_email_id != "") {
            $row = tom_get_row_by_id("a_form_fields", "*", "FID", $form->field_email_id);
            $user_email = ($_POST[$form_name.str_replace(" ", "_", strtolower($row->field_label))]);
          }
          if ($form->field_subject_id != "") {
            $row = tom_get_row_by_id("a_form_fields", "*", "FID", $form->field_subject_id);
            if (isset($_POST[$form_name.str_replace(" ", "_", strtolower($row->field_label))])) {
              $subject .= " - ".($_POST[$form_name.str_replace(" ", "_", strtolower($row->field_label))]);
            }
          }

          if ($form->confirmation_from_email != "") {
            $from_email = $form->confirmation_from_email;
          }

          // Send Email.
          $cc_emails = $form->to_cc_email;
          if ($user_email != "" && $form->send_confirmation_email) {
            if ($cc_emails == "") {
              $cc_emails .= $user_email;
            } else {
              $cc_emails .= ", ".$user_email;
            }
          }
          if ($cc_emails == "") {
            $cc_emails .= $from_email;
          } else {
            $cc_emails .= ", ".$from_email;
          }

          // Rip up $attachment_urls so we're left with only the paths to the files uploaded.
          $smtp_attachment_urls = array();
          foreach ($attachment_urls as $attach_url) {
            $temp = explode("=>", $attach_url);
            array_push($smtp_attachment_urls, $temp[1]);
          }

          $secure_algorithms = array();
          if (get_option("a_forms_enable_tls")) {
            $secure_algorithms["tls"] = "tls";
          }
          if (get_option("a_forms_enable_ssl")) {
            $secure_algorithms["ssl"] = "ssl";
          }

          $mail_message = tom_send_email(false, get_option("a_forms_admin_email").", ".$form->to_email, $cc_emails, $form->to_bcc_email, $from_email, $from_name, $subject, $email_content, "", $smtp_attachment_urls, get_option("a_forms_smtp_auth"), get_option("a_forms_mail_host"), get_option("a_forms_smtp_port"), get_option("a_forms_smtp_username"), get_option("a_forms_smtp_password"), $secure_algorithms);        
          
          if ($mail_message == "<div class='success'>Message sent!</div>") {

            if ($form->success_message != "") {
              $mail_message = "<div class='success'>".$form->success_message."</div>";
            }

            if ($form->tracking_enabled) {
              tom_insert_record("a_form_tracks", array("created_at" => $current_datetime, "form_id" => ($_POST["send_a_form"]), "content" => $email_content, "track_type" => "Successful Email", "referrer_url" => $_SERVER["HTTP_REFERER"], "fields_array" => serialize($field_values)));  
            }        

            if ($form->success_redirect_url != "") {
              tom_javascript_redirect_to($form->success_redirect_url, "<p>Please <a href='$url'>Click Next</a> to continue.</p>");
            }

          } else {
            if ($form->tracking_enabled) {
              tom_insert_record("a_form_tracks", array("created_at" => $current_datetime, "form_id" => ($_POST["send_a_form"]), "content" => "Error Message: ".$mail_message.".\n\nContent: ".$email_content, "track_type" => "Failed Email", "referrer_url" => $_SERVER["HTTP_REFERER"], "fields_array" => serialize($field_values)));
            }
          }          

          $return_content .= $mail_message;
        }
      } else {

        // Check to see if the input field values are valid, but not the wpnonce value.
        if ($form_valid && $captcha_valid && $nonce_passed == false) {
          // The input field values are valid except the wpnonce value. Therefore there must have been a cross site spam attack. So display fail send email message.
          $return_content .= "<div class='a-form error'>Failed to send your message. Please try again later.</div>";
        }
        $form_valid = false;

      }

    }
    
    $aform_form_nonce = wp_create_nonce( "a-forms-contact-a-form" );
    $return_content .= "<form action='' id='".str_replace(" ", "_", strtolower($form->form_name))."' method='post' class='a-form' enctype='multipart/form-data'>";

    $return_content .= "<input type='hidden' name='_wpnonce' value='".$aform_form_nonce."'/>";
    $return_content .= "<fieldset>";
    // Get next section
    if (($_POST["action"]) == "Next") {
      if ($form_valid) {
        $section_index++;
      } 
    }

    // Get previous section.
    if (($_POST["action"]) == "Back") {
      $section_index--;
    }

    $section = $sections[$section_index];

    // Navigate through all the other sections and make all fields hidden.
    $hidden_fields = tom_get_results("a_form_fields", "*", "form_id = '".$atts["id"]."' AND section_id <> '".$section->ID."'");
    foreach ($hidden_fields as $field) {
      $field_name = str_replace(" ", "_", strtolower($field->field_label));
      ob_start();
      
      if ($field->field_type == "checkbox") {
        $i = 0;
        foreach (explode(",", $field->value_options) as $key) {
          tom_add_form_field(null, "hidden", $field->field_label, $form_name.$field_name."_".$i, $form_name.$field_name."_".$i, array(), "p", array(), array());  
          $i++;
        }
      } else {

        tom_add_form_field(null, "hidden", $field->field_label, $form_name.$field_name, $form_name.$field_name, array(), "p", array(), array());
        
      }
      
      $return_content .= ob_get_contents();
      ob_end_clean();
    }

    $input_attachment_urls = "";

    if (count($attachment_urls) > 0) {
      $attachment_urls = array_filter( $attachment_urls, 'strlen' );
      $input_attachment_urls = implode("::", str_replace("\\\\", '\\', $attachment_urls));
    }

    $return_content .= "<input type='hidden' name='a_form_attachment_urls' value='".$input_attachment_urls."' />";

    $fields = tom_get_results("a_form_fields", "*", "section_id='".$section->ID."'", array("field_order ASC"));

    // Render form fields.
    if ($form->show_section_names) {
      $return_content .= "<legend>".$section->section_name."</legend>";
    }

    foreach ($fields as $field) {
      $field_name = str_replace(" ", "_", strtolower($field->field_label));
      $value_options = array();
      if ($field->value_options != "") {
        $options = explode(",", $field->value_options);
        foreach($options as $option_with_label) {
          $temp_array = explode(":", $option_with_label);
          $option = $temp_array[1];
          $value = $temp_array[0];
          if ($option == "") {
            $option = $value;
          }
          $value_options[$option] = $value;
        }
      }
      $field_label = $field->field_label;
      if (preg_match("/required/",$validation_array[$form_name.$field_name])) {
        $field_label .= "<abbr title='required'>*</abbr>";
      }

      ob_start();
      if ($field->field_type == "file" && $field->file_ext_allowed != "") {
        echo("<div>");
      } 
      $error_class = "";
      if (isset($_SESSION[$form_name.$field_name."_error"])) {
        $error_class = "error";
      }
      tom_add_form_field(null, $field->field_type, $field_label, $form_name.$field_name, $form_name.$field_name, array("class" => $field->field_type), "div", array("class" => $error_class), $value_options);
      if ($field->field_type == "file" && $field->file_ext_allowed != "") {
        $extensions_allowed = $field->file_ext_allowed;
        $extensions_allowed = preg_replace('/(\s)+/',' ', $extensions_allowed);
        $extensions_allowed = preg_replace('/(\s)+$/', '', $extensions_allowed);
        $extensions_allowed = preg_replace('/(\s)/', ', ', $extensions_allowed);
        $extensions_allowed = preg_replace('/ \.([a-z|A-Z])*$/', ' and $0', $extensions_allowed);
        $extensions_allowed = preg_replace('/,(\s)+and/', ' and', $extensions_allowed);
        echo("<span class='file-ext-allowed'>Can only accept: ".$extensions_allowed."</span>");
        echo("</div>");
      }

      $return_content .= ob_get_contents();
      ob_end_clean();
    }
    
    $return_content .= "</fieldset><fieldset class='submit'><div><input type='hidden' name='send_a_form_section' value='".$section_index."' /><input type='hidden' name='send_a_form' value='".$atts["id"]."' />";

    // Add action buttons
    // Check if more then one section
    if (count($sections) > 1) {
      // There is more then one section.

      if (($section_index+1) == count($sections)) {
        // Looking at the last section.
        $return_content .= render_a_form_submit_html($form);
      } else {
        // Not looking at the last section.
        $return_content .= "<input type='submit' name='action' value='Next' class='next'/>";
      }

    } else {
      // Only one section.
      $return_content .= render_a_form_submit_html($form);
    }

    // Check which section your currently looking at.
    if ($section_index > 0) {
      // Not looking at the first section.
      $return_content .= "<input type='submit' name='action' value='Back' class='prev'/>";
    }

    return $return_content."</div></fieldset></form>";

  }
}

function render_a_form_submit_html($form) {
  $return_content = "";
  if ($form->include_captcha) {
    ob_start();
    if ($form->captcha_type == "0") {

      tom_add_form_field(null, "captcha", "Captcha", aform_field_name($form, "captcha"), aform_field_name($form, "captcha"), array(), "div", array("class" => "captcha"));

    } else {

      $first_number = $_POST[aform_field_name($form, "captcha_first_number")] = rand(1, 20);
      $second_number = $_POST[aform_field_name($form, "captcha_second_number")] = rand(1, 20);

      tom_add_form_field(null, "hidden", "First number", aform_field_name($form, "captcha_first_number"), 
        aform_field_name($form, "captcha_first_number")
        , array(), "div", array());
      tom_add_form_field(null, "hidden", "Second number", aform_field_name($form, "captcha_second_number"), aform_field_name($form, "captcha_second_number"), array(), "div", array());

      tom_add_form_field(null, "text", "What is ".$first_number." + ".$second_number, aform_field_name($form, "captcha"), aform_field_name($form, "captcha"), array(), "div", array("class" => "captcha"));
    }
    $return_content .= ob_get_contents();
    ob_end_clean();
  }
  $return_content .= "<input type='submit' name='action' value='Send' class='send'/>";
  return $return_content;
}

add_action('wp_head', 'add_a_forms_js_and_css');
function add_a_forms_js_and_css() { 
  wp_enqueue_script('jquery');

  wp_register_script("a-forms", plugins_url("/js/application.js", __FILE__));
  wp_enqueue_script("a-forms");

  wp_register_style("a-forms", get_template_directory_uri().'/aforms_css/'.get_option("aform_current_css_file"));
  wp_enqueue_style("a-forms");
} 

function aform_field_name($form, $field_name) {
  return "a_form_".str_replace(" ", "_", strtolower($form->form_name))."_".$field_name;
}

// Copy directory to another location.
function aform_copy_directory($src,$dst) { 
    $dir = opendir($src); 
    try{
        @mkdir($dst); 
        while(false !== ( $file = readdir($dir)) ) { 
            if (( $file != '.' ) && ( $file != '..' )) { 
                if ( is_dir($src . '/' . $file) ) { 
                    aform_copy_directory($src . '/' . $file,$dst . '/' . $file); 
                } else { 
                    copy($src . '/' . $file,$dst . '/' . $file);
                } 
            }   
        }
        closedir($dir); 
    } catch(Exception $ex) {
        return false;
    }
    return true;
}

?>