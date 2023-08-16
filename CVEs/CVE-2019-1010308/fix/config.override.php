<?php
/** Override settings
  * Place a file with the override name in the aquarius dir to enable these
  * overrides. Example: To enable DEV mode, touch aquarius/DEV.
  *
  * If one of the override settings is unsuitable, you can override it again
  * in config.local.php.
  */
if (DEV) {
    /** Overrides suitable for development */
    $config['admin']['allpass'] = true;
    $config['frontend']['domain'] = null;
    $config['frontend']['domains'] = array();
    $config['frontend']['cache']['templates'] = false;
    $config['initcache'] = false;
    $config['log']['php'] = true;
}

if (STAGING) {
    /** Overrides suitable for testing before deployment */
    $config['frontend']['domain'] = null;
    $config['frontend']['domains'] = array();
}

if (DEBUG) {
    /** Overrides suitable for debugging */
    $config['log']['echolevel'] = 'DEBUG';
    $config['log']['php'] = true;
}
