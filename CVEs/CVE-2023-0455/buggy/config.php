<?php

/**
 * *************************
 * Errors and Solutions
 * ************************
 * 
 * If submit a large of data and return the following error in console: 
 *   net::ERR_CONTENT_DECODING_FAILED
 * 
 *      It happens when your HTTP request's headers claim that the content is gzip encoded, 
 *      but it isn't. Turn off gzip encoding setting or make sure the content is in fact encoded.
 * 
 * Please comment ob_start("ob_gzhandler"); in loader.php file in check it is return.
 * 
 * if it max_input_vars error, then increase it in php.ini file
 * 
 * Continue updating....
 * 
 */



// Site Configuration
define('ACTIVE_THEME', 'rui');
define('TABLE_PREFIX', 'bms_');
define('AUTO_LOGOUT_TIME', 300); // in Second. Default is five minutes

// Directory Configuration
define('DIR_CORE', DIR_BASE . 'core/');
define('DIR_THEME', DIR_BASE . 'theme/' . ACTIVE_THEME . '/');
define('DIR_MODULE', DIR_BASE . 'module/'); 
define('DIR_ASSETS', DIR_BASE . 'assets/'); 
define('DIR_INCLUDE', DIR_BASE . 'include/'); 
define('DIR_UPLOAD', DIR_ASSETS . 'upload/'); 
define('DIR_LOCAL', DIR_INCLUDE . 'local/'); 
define('DIR_LANG', DIR_LOCAL . 'lang/'); 
define('ERROR_PAGE', DIR_INCLUDE . 'static/errorpages/');
define('SYSTEM_DOOR', DIR_INCLUDE . 'system/door/');
define('SYSTEM_API', DIR_INCLUDE . 'system/api/');
define('APPS', DIR_INCLUDE . 'apps/');
define('LOAD_LIB', DIR_INCLUDE . 'lib/');
define('DB_CONN', DIR_INCLUDE . 'db/db.php');


/**
 * Max file upload size in MB
 * Note: Must be less then or euqal to PHP MAX_FILE_UPLOAD_SIZE and post_max_size
 */
$_SETTINGS["MAX_UPLOAD_SIZE"] = 8;

/**
 * Define allowed mime type.
 * 
 * Can be seen common mime type here: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
 * or
 *  https://www.iana.org/assignments/media-types/media-types.xhtml
 * 
 */
// Vaild image type for upload. must be in lower case
$_SETTINGS["VALID_IMAGE_TYPE_FOR_UPLOAD"] = array("jpeg", "jpg", "png");

// Valid document type for upload. must be in lower case
$_SETTINGS["VALID_DOCUMENT_TYPE_FOR_UPLOAD"] = array("pdf", "msword", "vnd.openxmlformats-officedocument.wordprocessingml.document", "vnd.ms-excel", "vnd.openxmlformats-officedocument.spreadsheetml.sheet");

// Valid audio type for upload
$_SETTINGS["VALID_AUDIO_TYPE_FOR_UPLOAD"] = array("mpeg", "ogg", "opus", "aac", "wav", "webm");

// Page title variable. All page title will be included here
$_SETTINGS["PAGE_TITLE"] = array();

// Dynamic Menu variable.
$_SETTINGS["NAV_MENU"] = array();


?>