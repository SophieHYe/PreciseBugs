<?php
/*
Plugin Name: Fancy Gallery Lite
Plugin URI: http://dennishoppe.de/en/wordpress-plugins/fancy-gallery
Description: Fancy Gallery enables you to create and manage galleries and converts your galleries in post and pages to valid HTML5 blocks and associates linked images with a nice and responsive lightbox.
Version: 1.5.13
Author: Dennis Hoppe
Author URI: http://DennisHoppe.de
*/

If (Version_Compare(PHP_VERSION, '5.3.0', '<')){

  # Add PHP Version warning to the dashboard
  Add_Action('admin_notices', 'Fancy_Gallery_PHP53_Version_Warning');
  function Fancy_Gallery_PHP53_Version_Warning(){ ?>
    <div class="error">
      <p><?php PrintF('<strong>%1$s:</strong> You need at least <strong>PHP 5.3</strong> or higher to use %1$s. You are using PHP %2$s. Please ask your hoster for an upgrade.', 'Fancy Gallery', PHP_VERSION) ?></p>
    </div><?php
  }

}
Else {

  # Load core classes
  Include DirName(__FILE__) . '/class.core.php';
  Include DirName(__FILE__) . '/class.gallery-post-type.php';
  Include DirName(__FILE__) . '/class.lightbox.php';
  Include DirName(__FILE__) . '/class.i18n.php';
  Include DirName(__FILE__) . '/class.mocking-bird.php';
  Include DirName(__FILE__) . '/class.options.php';
  Include DirName(__FILE__) . '/class.wpml.php';

  # Load widgets
  Include DirName(__FILE__) . '/widget.random-images.php';
  Include DirName(__FILE__) . '/widget.taxonomies.php';
  Include DirName(__FILE__) . '/widget.taxonomy-cloud.php';

  # Inititalize Plugin: Would cause a synthax error in PHP < 5.3
  Eval('New WordPress\Plugin\Fancy_Gallery\Core(__FILE__);');

}