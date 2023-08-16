<?php
/*
Plugin Name: BuddyStream
Plugin URI: http://www.buddystream.net
Description: BuddyStream
Version: 2.6.2
Author: Peter Hofman
Author URI: http://www.buddystream.net
*/

// Copyright (c) 2010/2011/2012 Buddystream.net All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for Buddypress
// http://buddypress.org/
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

/*
 * Only load code that needs BuddyPress
 * to run once BP is loaded and initialized.
 */
function buddystream_init()
{
    global $bp;

    //define plugin version and installed value
    define('BP_BUDDYSTREAM_VERSION', '2.6.2');
    define('BP_BUDDYSTREAM_IS_INSTALLED', 1);
    define('BP_BUDDYSTREAM_DIR', dirname(__FILE__));
    define('BP_BUDDYSTREAM_URL', $bp->root_domain."/".str_replace(ABSPATH,"",dirname(__FILE__)));

    //first load translations
    buddyStreamLoadTranslations();

    //initialize the database if needed
    buddyStreamInitDatabase();
    
    //initialize settings if needed
    buddyStreamInitSettings();
    
    //now initialize the core
    include_once('lib/BuddyStreamCurl.php');
    include_once('lib/BuddyStreamOAuth.php');
    include_once('lib/BuddyStreamLog.php');
    include_once('lib/BuddyStreamExtensions.php');
    include_once('lib/BuddyStreamFilters.php');
    include_once('lib/BuddyStreamSupport.php');
    include_once('lib/BuddyStreamPageLoader.php');
    include_once('lib/BuddyStreamCore.php');
}

/**
 * Initialise default settings
 */
function buddyStreamInitSettings(){

    if( ! get_site_option('buddystream_init_settings') != 'BP_BUDDYSTREAM_VERSION'){
        
        if(!get_site_option('buddystream_sharebox')){
            update_site_option('buddystream_sharebox', 'on');
        }
        
        if(!get_site_option('buddystream_social_albums')){
            update_site_option('buddystream_social_albums', 'on');
        }

        if(!get_site_option('buddystream_group_sharing')){
            update_site_option('buddystream_group_sharing', 'on');
        }
        
        update_site_option('buddystream_init_settings', BP_BUDDYSTREAM_VERSION);
    }

    if( ! get_site_option('buddystream_2512')) {
        update_site_option('buddystream_facebook_privacy_setting', 'on');
        update_site_option('buddystream_2512', '1');
    }
}

/**
 * Initialise database tables needed for plugin.
 */
function buddyStreamInitDatabase(){
    
   if( ! get_site_option("buddystream_installed_version")){

        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $buddystreamSql = "CREATE TABLE IF NOT EXISTS " . $wpdb->base_prefix . "buddystream_log (
          `id` int(11) NOT NULL auto_increment,
          `date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
          `type` text NOT NULL,
          `message` text NOT NULL,
          PRIMARY KEY  (`id`)
        );";

        dbDelta($buddystreamSql);
        unset($buddystreamSql);

        update_site_option("buddystream_installed_version", "1");
    }

   if( ! get_site_option('buddystream_26')) {

        global $wpdb,$bp;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $buddystreamSql = "CREATE TABLE IF NOT EXISTS " . $wpdb->base_prefix . "buddystream_imports (
          `id` int(11) NOT NULL auto_increment,
          `item_id` varchar(255) NOT NULL,
          PRIMARY KEY  (`id`)
        );";

        dbDelta($buddystreamSql);
        unset($buddystreamSql);

       //now get all activity items with a secondary id adn add it to them buddystream imports table
       $items = $wpdb->get_results("SELECT * FROM ".$bp->activity->table_name." WHERE secondary_item_id != ''");

       foreach($items as $item){

           $item_id = str_replace($item->user_id."_", "", $item->secondary_item_id);
           $item_id = $item->user_id."-".$item_id."-".$item->component;

           $wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->base_prefix."buddystream_imports set item_id='".$item_id."'"));
       }

       //enable the new Facebook extensions by default when Facebook extension is enabled.
       if(get_site_option('buddystream_facebook_power') == "on"){
           update_site_option('buddystream_facebookWall_power','on');
           update_site_option('buddystream_facebookWall_setup','1');
           update_site_option('buddystream_facebookPages_power','on');
           update_site_option('buddystream_facebookPages_setup','1');
           update_site_option('buddystream_facebookAlbums_power','on');
           update_site_option('buddystream_facebookAlbums_setup','1');
       }

        update_site_option('buddystream_26', '1');
   }
}

/*
 * Load the translation files for the plugin and extensions
 */
function buddyStreamLoadTranslations() {
    
    if (file_exists( BP_BUDDYSTREAM_DIR."/languages/buddystream-" . get_locale() . ".mo")) {
        load_textdomain('buddystream_lang', BP_BUDDYSTREAM_DIR."/languages/buddystream-" . get_locale().".mo");
    }else{
        load_textdomain('buddystream_lang', BP_BUDDYSTREAM_DIR."/languages/buddystream-en_US.mo");
    }

    $handle = opendir(dirname(__FILE__)."/extensions");
    if ($handle) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != ".." && $file != ".DS_Store") {
                if (file_exists(BP_BUDDYSTREAM_DIR."/extensions/".$file."/languages/buddystream_".$file."-".get_locale().".mo")) {
                    load_textdomain('buddystream_' . $file, BP_BUDDYSTREAM_DIR."/extensions/".$file."/languages/buddystream_".$file."-".get_locale().".mo");
                }else{
                    load_textdomain('buddystream_' . $file, BP_BUDDYSTREAM_DIR."/extensions/".$file."/languages/buddystream_".$file."-en_US.mo");
                }
            }
        }
    }
}

add_action('bp_init', 'buddystream_init', 4);

/**
 * Add the BuddyStream Connect Widget
 */
add_action('widgets_init', 'buddystream_connect_widget');

function buddystream_connect_widget() {
    include_once('lib/BuddyStreamWidgets.php');
    register_widget('BuddyStream_Connect_Widget');
}