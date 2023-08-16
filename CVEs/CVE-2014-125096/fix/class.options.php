<?php
Namespace WordPress\Plugin\Fancy_Gallery;

class Options {
  private
    $arr_option_box, # Meta boxes for the option page
    $options_page_slug, # Slug for the options page
    $core; # Pointer to the core object

  public function __construct($core){
    $this->core = $core;
    $this->options_page_slug = Sanitize_Title(Str_Replace(Array('\\', '/', '_'), '-', __CLASS__));

    # Option boxes
    $this->arr_option_box = Array(
      'main' => Array(),
      'side' => Array()
    );

    Add_Action('admin_menu', Array($this, 'Add_Options_Page'));
  }

  private function t($text, $context = False){
    return $this->core->t($text, $context);
  }

  public function Add_Options_Page(){
    $handle = Add_Options_Page (
      $this->t('Fancy Gallery Options'),
      $this->t('Fancy Gallery'),
      'manage_options',
      $this->options_page_slug,
      Array($this, 'Print_Options_Page')
    );

    # Add JavaScript to this handle
    Add_Action ('load-' . $handle, Array($this, 'Load_Options_Page'));

    # Add option boxes
    $this->Add_Option_Box($this->t('Lightbox'), DirName(__FILE__).'/options-page/lightbox.php');
    $this->Add_Option_Box($this->t('Templates'), DirName(__FILE__).'/options-page/templates.php', 'main', 'closed');
    $this->Add_Option_Box($this->t('User rights'), DirName(__FILE__).'/options-page/user-rights.php', 'main', 'closed');

    $this->Add_Option_Box($this->t('Taxonomies'), DirName(__FILE__).'/options-page/taxonomies.php', 'side');
    $this->Add_Option_Box($this->t('Gallery "Excerpts"'), DirName(__FILE__).'/options-page/excerpt.php', 'side');
    $this->Add_Option_Box($this->t('Archive Url'), DirName(__FILE__).'/options-page/archive-link.php', 'side');
  }

  private function Get_Options_Page_Url($parameters = Array()){
    $url = Add_Query_Arg(Array('page' => $this->options_page_slug), Admin_Url('options-general.php'));
    If (Is_Array($parameters) && !Empty($parameters)) $url = Add_Query_Arg($parameters, $url);
    return $url;
  }

  public function Load_Options_Page(){
    # Check if the user trys to delete a template
    If (IsSet($_GET['delete']) && $this->core->Get_Template_Properties ($_GET['delete'])){ # You can only delete Fancy Gallery Templates!
      Unlink($_GET['delete']);
      WP_Redirect( $this->Get_Options_Page_Url(Array('template_deleted' => 'true')) );
    }
    ElseIf (IsSet($_GET['delete'])){
      WP_Die($this->t('Error while deleting: ' . HTMLSpecialChars($_GET['delete'])));
    }

    # If the Request was redirected from a "Save Options"-Post
    If (IsSet($_REQUEST['options_saved'])) Flush_Rewrite_Rules();

    # If this is a Post request to save the options
    $options_saved = $this->Save_Options();
    If ($options_saved)
      WP_Redirect( $this->Get_Options_Page_Url(Array('options_saved' => 'true')) );

    WP_Enqueue_Script('dashboard');
    WP_Enqueue_Style('dashboard');

    WP_Enqueue_Script('fancy-gallery-options-page', $this->core->base_url . '/options-page/options-page.js', Array('jquery'), $this->core->version, True);
    WP_Enqueue_Style('fancy-gallery-options-page', $this->core->base_url . '/options-page/options-page.css' );

    # Remove incompatible JS Libs
    WP_Dequeue_Script('post');
  }

  public function Print_Options_Page(){
    ?>
    <div class="wrap">
      <h2><?php Echo $this->t('Fancy Gallery Settings') ?></h2>

      <?php If (IsSet($_GET['options_saved'])) : ?>
      <div id="message" class="updated fade">
        <p><strong><?php _e('Settings saved.') ?></strong></p>
      </div>
      <?php EndIf; ?>

      <?php If (IsSet($_GET['template_installed'])) : ?>
      <div id="message" class="updated fade">
        <p><strong><?php echo $this->t('Template installed.') ?></strong></p>
      </div>
      <?php EndIf; ?>

      <?php If (IsSet($_GET['template_deleted'])) : ?>
      <div id="message" class="updated fade">
        <p><strong><?php echo $this->t('Template deleted.') ?></strong></p>
      </div>
      <?php EndIf; ?>

      <form method="post" action="" enctype="multipart/form-data">
      <div class="metabox-holder">

        <div class="postbox-container" style="width:69%;">
          <?php ForEach ($this->arr_option_box['main'] AS $box) : ?>
            <div class="postbox should-be-<?php Echo $box['state'] ?>">
              <div class="handlediv" title="<?php _e('Click to toggle') ?>"><br /></div>
              <h3 class="hndle"><span><?php Echo $box['title'] ?></span></h3>
              <div class="inside"><?php Include $box['file'] ?></div>
            </div>
          <?php EndForEach ?>
        </div>

        <div class="postbox-container" style="width:29%;float:right">
          <?php ForEach ($this->arr_option_box['side'] AS $box) : ?>
            <div class="postbox should-be-<?php Echo $box['state'] ?>">
              <div class="handlediv" title="<?php _e('Click to toggle') ?>"><br /></div>
              <h3 class="hndle"><span><?php Echo $box['title'] ?></span></h3>
              <div class="inside"><?php Include $box['file'] ?></div>
            </div>
          <?php EndForEach ?>
        </div>

        <div class="clear"></div>
      </div>

      <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>">
      </p>

      </form>
    </div>
    <?php
  }

  public function Add_Option_Box($title, $include_file, $column = 'main', $state = 'opened'){
    # Check the input
    If (!Is_File($include_file)) return False;
    If ( $title == '' ) $title = '&nbsp;';

    # Column (can be 'side' or 'main')
    If ($column != '' && $column != Null && $column != 'main')
      $column = 'side';
    Else
      $column = 'main';

    # State (can be 'opened' or 'closed')
    If ($state != '' && $state != Null && $state != 'opened')
      $state = 'closed';
    Else
      $state = 'opened';

    # Add a new box
    $this->arr_option_box[$column][] = Array('title' => $title, 'file' => $include_file, 'state' => $state);
  }

  private function Save_Options(){
    # Check if this is a post request
    If (Empty($_POST)) return False;

    # Add Capabilities
    If (IsSet($_POST['capabilities']) && Is_Array($_POST['capabilities'])){
      ForEach ($_POST['capabilities'] AS $role_name => $arr_role){
        If (!$role = get_role($role_name)) Continue;
        ForEach ((Array) $arr_role AS $capability => $yes_no){
          If ($yes_no == 'yes')
            $role->add_cap($capability);
          Else
            $role->remove_cap($capability);
        }
      }
      Unset ($_POST['capabilities']);
    }

    # Clean the Post array
    $_POST = StripSlashes_Deep($_POST);
    ForEach ($_POST AS $option => $value)
      If (!$value) Unset ($_POST[$option]);

    # Save Options
    Update_Option (__CLASS__, $_POST);
    Delete_Option ('wp_plugin_fancy_gallery_pro');
    Delete_Option ('wp_plugin_fancy_gallery');

    # We delete the update cache
    $this->core->Clear_Plugin_Update_Cache();

    return True;
  }

  private function Default_Options(){
    return Array(
      'lightbox' => 'on',
      'continuous' => 'off',
      'title_description' => 'on',
      'close_button' => 'on',
      'indicator_thumbnails' => 'on',
      'slideshow_speed' => 4000, # Slideshow speed in milliseconds
      'preload_images' => 2,
      'animation_speed' => 400,
      'stretch_images' => False,

      'gallery_taxonomy' => Array(),

      'disable_excerpts' => False,
      'excerpt_thumb_width' => Get_Option('thumbnail_size_w'),
      'excerpt_thumb_height' => Get_Option('thumbnail_size_h'),
      'excerpt_image_number' => 3,

      'deactivate_archive' => False
    );
  }

  public function Get($key = Null, $default = False){
    # Read Options
    $arr_option = Array_Merge (
      (Array) $this->Default_Options(),
      (Array) Get_Option('wp_plugin_fancy_gallery_pro'),
      (Array) Get_Option('wp_plugin_fancy_gallery'),
      (Array) Get_Option(__CLASS__)
    );

    # Locate the option
    If ($key == Null)
      return $arr_option;
    ElseIf (IsSet($arr_option[$key]))
      return $arr_option[$key];
    Else
      return $default;
  }

}