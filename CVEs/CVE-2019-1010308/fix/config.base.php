<?php

/**************** LOGGING */

// Log messages are written to a log-file and included as HTML comments
// Available log levels: NEVER | FAIL |  WARN | INFO | MESSAGES | DEBUG | BACKTRACE | ALL

/** What messages to log to file, recommended is INFO */
$config['log']['level']     = 'INFO';

/** Write log messages to this file, path relative to aquarius dir, must be
  * writable by webserver. */
$config['log']['file'] = 'cache/log.txt';

/** Echo log messages to output as HTML comments, recommended NEVER */
$config['log']['echolevel'] = 'NEVER';

/** Send log messages in HTTP headers, recommended NEVER
  * This requires Firefox/FirePHP */
$config['log']['firelevel'] = 'NEVER';

/** Enable/disable PHP messages
  * Set to true to enable PHP warnings, false to disable them explicitly.
  * The preset null means that the PHP settings are not changed. */
$config['log']['php'] = null;




/**************** DB */

/** What DB driver to use
  *
  * Preset is 'mysqli' and this should be left alone.
  * 'mysql' is somewhat tested, others won't work unless you do some serious porting work.
  */
$config['db']['driver'] = 'mysqli'; // Change this at your own peril.

/** The DB host to connect to, preset is 'localhost' */
$config['db']['host'] = 'localhost';

/** The database name, this is best configured in config.local.php */
$config['db']['name'] = null;

/** The database user to connect with, this is best configured in config.local.php */
$config['db']['user'] = null;

/** The database password to connect with, this is best configured in config.local.php
  * Note that this will not be available during run-time to avoid leakage.
  * Use the constant DB_PASSWORD to retrieve it.
  */
$config['db']['pass'] = null; 



/**************** SESSION */

/** Where to save session files
  *
  * Specify a filesystem path where session files are stored. If this path is
  * relative, it will be taken relative to the aquarius cache path. The
  * preset value is 'session'. If this is set to false, the value preset
  * by the webserver is not changed, the standard location is '/tmp'.
  *
  * On servers with shared hosting, it is insecure to store session data in a
  * common directory with other sites. Unfortunately, webservers are frequently
  * configured that way. For this reason Aquarius does not by default use the
  * session.save_path preset by the webserver.
  *
  * See also: PHP's session.save_path directive
  *
  */
$config['session']['save_path'] = 'session';

/** How long session data is preserved before it's cleared (minimum) in seconds
  *
  * The preset is 30 minutes. If this value is set to false, the value
  * configured by the webserver is not changed.
  *
  * See also: PHP's session.gc_maxlifetime directive
  *
  */
$config['session']['lifetime'] = '1800';

/** Name of the session cookie, preset is 'aquarius3_session' */
$config['session']['name'] = 'aquarius3_session';


/**************** FRONTEND */

/* Set the standard domain name 
 * This will be used in generated URL.
 * 
 * Example:
 * $config['frontend']['domain'] = 'www.aquaverde.ch'
 */
$config['frontend']['domain'] = null;


/* Always use session in frontend
 * If this is false (the default, sessions will only be enabled for restricted nodes) */
$config['frontend']['use_session'] = false;

/* Use different language or base node, based on domain name.
   Default language, node and redirects may be specified based on domain-name. These parameters can be set:
    node: A node id or name to use instead of the root node
    lg:   A language to use instead of using browser detection or the default
    moved_permanently: an URL to redirect to.

  The 'node' and 'lg' parameters are considered only if they are not specified in the URL. 'moved_permanently' on the other hand is always active.

   Example: Assume we have the two domains 'coolthing.example.com' and 'trucfroid.example.com', the two languages 'en' and 'fr', also the default language is 'en'. Now we configure the following:

    $config['frontend']['domains'] = array(
            'search.coolthing.example.com' => array('node' => 'search'),
                   'trucfroid.example.com' => array('lg'   => 'fr'),
         'recherche.trucfroid.example.com' => array('node' => 'search'),
                     'oldcool.example.com' => array('moved_permanently' => 'http://coolthing.example.com')
        );

   What this means is that for 'search.coolthing.example.com' and all subdomains we use the node named 'search' as base node. For all domains ending in 'trucfroid.example.com' we select 'fr' as language, and finally, for 'recherche.trucfroid.example.com' we also use the node named 'search'.

   Note that it was not necessary to specify 'lg'=>'fr' again for 'recherche.trucfroid.example.com', because that was already covered by 'trucfroid.example.com'.

   All requests on domain oldcool.example.com will be answered with a HTTP 301 redirection to location 'http://coolthing.example.com'. */
$config['frontend']['domains'] = array();

/* Frontend redirects to proper URI if this is enabled. May lead to redirect loops, surprising behaviour and overall confusion. Required to please the holy GOOG. */
$config['frontend']['uri_correction'] = true;

/* Automagically assign content fields for each item in the smarty {list} block. */
$config['frontend']['loadcontent'] = false;

/* Automagically escape every variable in templates */
$config['frontend']['smarty']['auto_escape'] = false;

/* Merge includes into one compiled template
 * In smarty 3.1, you can either have dynamic templates like
 * {include file=$file} or use {block} inheritance. The default is {block}
 * inheritance. If you want dynamic includes set this to false, then use
 * the inline parameter ({include file=include_with_block.tpl inline}) if there
 * are blocks in your includes.
 * */
$config['frontend']['smarty']['merge_includes'] = true;

/* Enable caching of template output.
 * This is generally a very good idea. */
$config['frontend']['cache']['templates'] = true;

/* How long to keep cache output, in seconds */
$config['frontend']['cache']['lifetime'] = 86400; // One day

/* Enable caching of compiled templates.
 * There is no reason to turn this off, really. */
$config['frontend']['cache']['compiles'] = true;


/**************** ADMIN */

/** Optional: Domain to use for backend, clients using another domain will be redirected to this */
//$config['admin']['domain'] = 'admin.site.example';

/** Path to backend. Optional, standard value is '/admin/'  */
//$config['admin']['path'] = '/aquarius/';

$config['admin']['user']['edit_permission_tree_depth'] = 2; // How many levels of tree to allow adding edit permission for users (default 2)

// light config
$config['admin']['menu_links'] = array(
    array( 'parent' => 'menu_super_links', 'title' => 'Statistics', 'url' => '/stats/'),
    array( 'parent' => 'menu_super_links', 'title' => 'Aquarius manual', 'url' => 'http://wiki.aquarius3.ch/', 'target' => '_new'),
    array( 'parent' => 'menu_super_links', 'title' => 'Database admin', 'url' => '/aquarius/dbadmin', 'target' => '_new', 'allow' => 0)
);

// RTE configuration
$config['admin']['rte'] = array(
    'browse_path_img' => 'pictures/richtext',
    'browse_path_file' => 'download',
    'plugins' => array(
        'iLink' => '/aquarius/core/backend/ckeditor/plugins/iLink/plugin.js',
        'wordcount' => '/aquarius/core/vendor/w8tcha/CKEditor-WordCount-Plugin/wordcount/plugin.js'
    )
);

// Add RTE plugin examples
#$config['admin']['rte']['plugins']['youtube'] = '/js/ckeditor_youtube/plugin.js' // External plugin
#$config['admin']['rte']['plugins']['iframe'] = true // core plugin


/** Allow administrators to manage languages
  *
  * Preset is false.
  */
$config['admin']['allow_languageadmin'] = false;

/** The preset for the target selection in link fields
  *
  * Set this to '_blank' if you prefer the links to open a new window.
  * Changing this value will only affect newly added links. */
$config['admin']['link_target'] = '';


/** Omit password checking for backend logins.
  * This is useful during development, use with care! Do not ever enable this
  * on a publicly accessible system. Best enable DEV mode to get this, change it
  * in config.local.php if at all.
  *
  * Preset is false.
  */
$config['admin']['allpass'] = false;


/** Standard email address to use as sender address
  * This is used in the "Sender:" header when the system generates mails. The
  * "From:" header will also be set to this address should it not be set
  * explicitly.
  * 
  * The "@host" part may be omitted, and only the local part (before the "@")
  * specified. In this case the request-hostname will be used, with the
  * "www." stripped off.
  */
$config['email'] = array(
    'sender' => 'info',
    'smtp' => false
);

/*
// Example SMTP config
$config['email']['smtp'] = array(
    'host' => 'smtp.bulkspam.example',
    'port' => 25,
    'user' => 'egg@bulkspam.example',
    'pass' => 'ham',
    'sender' => 'egg@bulkspam.example' // Force the mail sender to be this address
);

// Testing config for perl fakesmtpd.pl
$config['email']['smtp'] = array(
    'host'     => 'localhost',
    'port'     => '2525'
);
*/


/** PDF generator settings.
  * WARNING: The PDF generator currently allows generating PDF from any node
  * with any template. This is probably not what you want, so it is disabled as
  * a precaution. 
  */
$config['pdfgen']['enabled'] = false;
$config['pdfgen']['standard_template'] = 'basic.tpl';
$config['pdfgen']['prefix'] = 'pdf';


/** Cache Aquarius loading
  * The pristine Aquarius stage is cached to a file and Aquarius is initizlized
  * from this cache. On fast systems the difference is negligible but disk-bound
  * webservers can profit from this.
  */
$config['initcache'] = true;

