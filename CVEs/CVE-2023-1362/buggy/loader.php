<?php
/**
 * App loader
 * @since 0.1
 */
 
// Set the php coockie id only visible over http
ini_set('session.cookie_httponly', true);

// Check if the PHP version is at leat 7.0
if( version_compare(PHP_VERSION, '7.0.0') <= 0 ) {
    header('HTTP/1.0 403 Forbidden');
    die("BumSys require at least PHP 7.0.0. You are running PHP " . PHP_VERSION);
}

// generate a sha1 string for session coockie
$sha1 = sha1($_SERVER["HTTP_USER_AGENT"].$_SERVER["REMOTE_ADDR"]);

// Set the session name
ini_set('session.name', "__$sha1");

// Start session
session_start();


// Include the configuration file
require "config.php"; 

// include the db connection
require DB_CONN;

// include the functions file
require "functions.php";

// Check the request address
if($_SERVER["HTTP_HOST"] !== explode("/", root_domain())[0]) {
    header('HTTP/1.0 403 Forbidden');
    die("<strong>Error:</strong> You have no permission to access this server.");
}

// Set default time zone FOR PHP
date_default_timezone_set( get_options("timeZone") );

// set options variable for mysql
runQuery("SELECT  
        CASE WHEN option_name = 'decimalPlaces' THEN @decimalPlace:= option_value END, 
        CASE WHEN option_name = 'mysqlTimeFormat' THEN @mysqlTimeFormat:= option_value END,
        CASE WHEN option_name = 'mysqlDateFormat' THEN @mysqlDateFormat:= option_value END
    FROM {$table_prefix}options WHERE option_name in('decimalPlaces', 'mysqlDateFormat', 'mysqlTimeFormat');
");

// Get the page slug
$pageSlug = pageSlug();


// Disable the compression and sanitization of out
// While exporting database and files
if( !isset($_GET["export"]) ) {

    // Enable zg compression
    //ob_start("ob_gzhandler");

    // Santize the output if the page is not for dynamic images load
    // will be not sanities for "js", "css" if it required
    if( !in_array($pageSlug, array("images", "barcode") ) ) {
        //ob_start("sanitize_output");
    }

}


// Check if the access permitted
if(!access_is_permitted()) {
    header('HTTP/1.0 403 Forbidden');
    require ERROR_PAGE . "403.s.php";
    exit();
}


// check if login or not. If not login then show the login page
if(is_login() !== true and !in_array($pageSlug, array("css", "js", "api/v1")) ) {
    require SYSTEM_DOOR . "login.php";
    exit();
}


// Include the default menu
require "menu.php";

// Inclue the default permissions List
require "permissions.php";


/**
 * All External module come with this scope.
 */

// Get all Active module
$activeModule = empty(get_options("activeModule")) ? array() : unserialize( html_entity_decode(get_options("activeModule")) );

foreach($activeModule as $module) {
    
    // Check if the module exists
    if( file_exists(DIR_BASE . $module) ) {
        
        // Load the active module
        require_once(DIR_BASE . $module);

    }

}


// If the request is only content then
// We do not generate the menu again
if( !isset( $_GET['contentOnly'] ) ) {
    
    /* Generate The Menu, title and permissions */
    $generatedMenu = generateMenu( $default_menu );

}

// include the route generator
require "route.php";


?>