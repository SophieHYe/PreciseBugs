<?php
Namespace WordPress\Plugin\Fancy_Gallery\Widget;

class Taxonomies Extends \WP_Widget {
  public
    $arr_options,
    $core; #  Pointer to the core class

  function __construct(){
    $this->core = $GLOBALS['WordPress\Plugin\Fancy_Gallery\Core'];

    # Setup the Widget data
    parent::__construct (
      'fancy-gallery-taxonomies',
      $this->t('Gallery taxonomies'),
      Array('description' => $this->t('Displays your gallery taxonomies like categories, tags, events, photographers, etc.'))
    );
  }

  function t ($text, $context = False){
    return $this->core->t($text, $context);
  }

  function Default_Options(){
    # Default settings
    return Array(
      'show_count' => False,
      'number'     => Null,
      'orderby'    => 'name',
      'order'      => 'ASC',
      'exclude'    => False
    );
  }

  function Load_Options($options){
    $options = (Array) $options;

    # Delete empty values
    ForEach ($options AS $key => $value)
      If (!$value) Unset($options[$key]);

    # Load options
    $this->arr_option = Array_Merge ($this->Default_Options(), $options);
  }

  function Get_Option($key, $default = False){
    If (IsSet($this->arr_option[$key]) && $this->arr_option[$key])
      return $this->arr_option[$key];
    Else
      return $default;
  }

  function Set_Option($key, $value){
    $this->arr_option[$key] = $value;
  }

  function Form ($settings){
    # Load options
    $this->load_options ($settings); Unset ($settings);
    ?>
    <p>
      <label for="<?php Echo $this->Get_Field_Id('title') ?>"><?php _e('Title:') ?></label>
      <input type="text" id="<?php Echo $this->Get_Field_Id('title') ?>" name="<?php Echo $this->Get_Field_Name('title')?>" value="<?php Echo Esc_Attr($this->Get_Option('title')) ?>" class="widefat">
      <small><?php Echo $this->t('Leave blank to use the widget default title.') ?></small>
    </p>

    <p>
      <label for="<?php Echo $this->Get_Field_Id('taxonomy') ?>"><?php Echo $this->t('Taxonomy:') ?></label>
      <select id="<?php Echo $this->Get_Field_Id('taxonomy') ?>" name="<?php Echo $this->Get_Field_Name('taxonomy') ?>" class="widefat">
      <?php ForEach(Get_Object_Taxonomies($this->core->gallery_post_type->name) AS $taxonomy) : $taxonomy = Get_Taxonomy($taxonomy) ?>
      <option value="<?php Echo $taxonomy->name ?>" <?php Selected($this->get_option('taxonomy'), $taxonomy->name) ?>><?php Echo HTMLSpecialChars($taxonomy->labels->name) ?></option>
      <?php EndForEach ?>
      </select><br>
      <small><?php Echo $this->t('Please choose the Taxonomy the widget should display.') ?></small>
    </p>

    <p>
      <label for="<?php Echo $this->Get_Field_Id('number') ?>"><?php Echo $this->t('Number of terms:') ?></label>
      <input type="number" id="<?php Echo $this->Get_Field_Id('number') ?>" name="<?php Echo $this->Get_Field_Name('number')?>" value="<?php Echo Esc_Attr($this->Get_Option('number')) ?>"><br>
      <small><?php Echo $this->t('Leave blank to show all.') ?></small>
    </p>

    <p>
      <label for="<?php echo $this->Get_Field_Id('exclude') ?>"><?php _e('Exclude:') ?></label>
      <input type="text" value="<?php Echo Esc_Attr($this->Get_Option('exclude')) ?>" name="<?php Echo $this->Get_Field_Name('exclude') ?>" id="<?php Echo $this->Get_Field_Id('exclude') ?>" class="widefat">
      <small><?php Echo $this->t('Term IDs, separated by commas.') ?></small>
    </p>

    <p>
      <input type="checkbox" id="<?php Echo $this->Get_Field_Id('count') ?>" name="<?php Echo $this->Get_Field_Name('count') ?>" <?php Checked($this->Get_Option('count')) ?> >
      <label for="<?php echo $this->Get_Field_Id('count') ?>"><?php Echo $this->t( 'Show Gallery counts.' ) ?></label>
    </p>

    <p>
      <label for="<?php Echo $this->Get_Field_Id('orderby') ?>"><?php Echo $this->t('Order by:') ?></label>
      <select id="<?php Echo $this->Get_Field_Id('orderby') ?>" name="<?php Echo $this->Get_Field_Name('orderby') ?>" class="widefat">
      <option value="name" <?php Selected($this->Get_Option('orderby'), 'name') ?>><?php _e('Name') ?></option>
      <option value="count" <?php Selected($this->Get_Option('orderby'), 'count') ?>><?php Echo $this->t('Gallery Count') ?></option>
      <option value="ID" <?php Selected($this->Get_Option('orderby'), 'ID') ?>>ID</option>
      <option value="slug" <?php Selected($this->Get_Option('orderby'), 'slug') ?>><?php Echo $this->t('Slug') ?></option>
      </select>
    </p>

    <p>
      <label for="<?php Echo $this->Get_Field_Id('order') ?>"><?php Echo $this->t('Order:') ?></label>
      <select id="<?php Echo $this->Get_Field_Id('order') ?>" name="<?php Echo $this->Get_Field_Name('order') ?>" class="widefat">
      <option value="ASC" <?php Selected($this->Get_Option('order'), 'ASC') ?>><?php _e('Ascending') ?></option>
      <option value="DESC" <?php Selected($this->Get_Option('order'), 'DESC') ?>><?php _e('Descending') ?></option>
      </select>
    </p>

    <?php
  }

  function Widget ($args, $settings){
    # Load options
    $this->Load_Options ($settings); Unset ($settings);

    # Check if the Taxonomy is alive
    If (!Taxonomy_Exists($this->Get_Option('taxonomy'))) return False;

    # Display Widget
    Echo $args['before_widget'];

    Echo $args['before_title'] . Apply_Filters('widget_title', $this->Get_Option('title'), $this->arr_option, $this->id_base) . $args['after_title'];

    Echo '<ul>';
    WP_List_Categories(Array(
      'taxonomy'   => $this->Get_Option('taxonomy'),
      'show_count' => $this->Get_Option('count'),
      'number'     => $this->Get_Option('number'),
      'order'      => $this->Get_Option('order'),
      'orderby'    => $this->Get_Option('orderby'),
      'exclude'    => $this->Get_Option('exclude'),
      'title_li'   => ''
    ));
    Echo '</ul>';

    Echo $args['after_widget'];
  }

  function Update ($new_settings, $old_settings){
    return $new_settings;
  }

}