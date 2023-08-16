<?php
/**
 *                    Jojo CMS
 *                ================
 *
 * Copyright 2007-2008 Harvey Kane <code@ragepank.com>
 * Copyright 2007-2008 Michael Holt <code@gardyneholt.co.nz>
 * Copyright 2007 Melanie Schulz <mel@gardyneholt.co.nz>
 *
 * See the enclosed file license.txt for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Harvey Kane <code@ragepank.com>
 * @author  Michael Cochrane <mikec@jojocms.org>
 * @author  Melanie Schulz <mel@gardyneholt.co.nz>
 * @license http://www.fsf.org/copyleft/lgpl.html GNU Lesser General Public License
 * @link    http://www.jojocms.org JojoCMS
 * @package jojo_core
 */

 /* Pass Auto loading of classes to Jojo:: */
spl_autoload_register(array('Jojo', 'autoload'));

/* Register the jojo php error hanlder */
if (!defined('_DEBUG') || !_DEBUG) {
    set_error_handler(array('Jojo', 'errorHandler'), E_ALL);
}

class Jojo {

    /**
     * Associate a URI pattern with a plugin
     *
     */
    static function registerURI($pattern, $class, $customFunction = null)
    {
        global $_uriPatterns;
        if (!isset($_uriPatterns)) {
            $_uriPatterns = array();
        }
        $_uriPatterns[] = array(
                            'pattern' => $pattern,
                            'class' => $class,
                            'custom' => $customFunction
                            );
    }

    /**
     * Handle any submitted authentication data
     * eg username/password or 'remember me' cookie
     * set the global USERID and USERGROUPS varaibles
     *
     * TODO: log failures from each IP address - if too many, block the IP from accessing the site
     *
     */
    public static function authenticate()
    {
        global $smarty, $_USERID, $_USERTIMEZONE, $_USERGROUPS;

        /* Look up user from database */
        $newlogin = false;
        $referer = Jojo::getFormData('referer', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

        $logindata = false;
        if (isset($_SESSION['userid']) && !empty($_SESSION['userid'])) {
            /* User has previously authenticated, lookup by username and code */
            $logindata = Jojo::selectRow("SELECT * FROM {user} WHERE userid = ? AND us_locked = 0 LIMIT 1", array($_SESSION['userid']));
        } elseif (isset($_COOKIE['jojoR'])) {
            /* Get user details from 'jojoR' (password remember) cookie */
            $values = explode(':', base64_decode($_COOKIE['jojoR']));
            if (count($values) == 2) {
                $validtoken = Jojo::selectQuery("SELECT * FROM {auth_token} WHERE userid = ? AND token = ? LIMIT 1", $values);
                if (count($validtoken)) {
                    array_unshift($values, time());
                    $res = Jojo::updateQuery("UPDATE {auth_token} SET lastused = ? WHERE userid = ? AND token = ? ", $values);
                    $logindata = Jojo::selectRow("SELECT * FROM {user} WHERE userid = ? AND us_locked = 0 LIMIT 1", array($validtoken[0]['userid']));
                }
                if ($logindata) {
                    /* Set loggingIn global */
                    $_SESSION['loggingin'] = true;
                }
            }
        }

        if (!$logindata) {
            /* User is not logged in */
            $_USERID = false;
            $_USERGROUPS[] = 'notloggedin';
            return;
        }

        /* Store User Info */
        $_USERID = $logindata['userid'];
        $_USERTIMEZONE = $logindata['us_timezone'];
        $_SESSION['userid'] = $_USERID;

        /* User is logged in */
        $smarty->assign('loggedIn', true);
        $smarty->assign('userrecord', $logindata);

        /* Should we remember this login? */

        /* Get User Group Membership */
        $_USERGROUPS = array('everyone');
        $groups = Jojo::selectQuery("SELECT * FROM {usergroup_membership} WHERE userid = ?", array($_USERID));

        /* if admin, set as admin ie to not show analytics when admin viewing site */
        foreach ($groups as $group) {
            if($group['groupid'] != 'notloggedin') { // can't be both logged in, and in the usergroup 'notloggedin'
               $_USERGROUPS[] = $group['groupid'];
               if ($group['groupid'] == 'admin') {
                   $smarty->assign('adminloggedin', true);
                   // allow admins to see hidden pages
                   $_SESSION['showhidden'] = true;
               }
            }
        }
    }

    /**
     * Makes a random value between min and max that is based on the URL of the
     * current page. Useful for randomising link text etc, while ensuring it
     * doesn't change too often
     */
    static function semiRand($min = 0, $max = 1000000, $seedling = '')
    {
        $hash = md5($_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI'] . $seedling . 'sss');
        $seed = intval(substr($hash, 0, 5), 16);
        mt_srand($seed);
        $random = mt_rand($min, $max);
        mt_srand(); //reset the seed to something random again
        return $random;
    }
    /*
     * This function returns a semi-random block of text, useful for keeping content looking more unique
     * $variations = array of text variations to use. Delimit variables with [square brackets]
     * $variables = keyed array of variables / values to insert into the variations.
     * eg $variations = array('Browse our selection of [category] in [city]', 'We have a large selection of [category] available in [city]')
     *    $variables = array('category' => 'houses', 'city' => 'Auckland')
     *    Funtion will return something like 'Browse our selection of houses in Auckland'
     */
    static function semiRandomText($variations, $variables=false, $metadesc=false)
    {
        if (!$variations) return false;
        if (!is_array($variations)) $variations = array($variations);
        if (!$variables) $variables = array();
        $goodvariations = array();

        /* make a list of good variations. A variation is considered good if all the variables it contains can be fulfilled */
        foreach ($variations as $variation) {
            preg_match_all('/\\[(.*?)\\]/', $variation, $result, PREG_PATTERN_ORDER);
            $required = $result[1];
            $ok = true;
            $relevance = 0;
            foreach ($required as $requiredvariable) {
                if (empty($variables[$requiredvariable])) {
                    $ok = false;
                } else {
                    $relevance++;
                }
            }
            if ($ok) $goodvariations[$relevance][] = $variation;
        }

        /* ensure there is at least one good variation */
        if (!count($goodvariations)) return false;
        $goodvariations = end($goodvariations);

        /* choose a semi-random variation from the most relevant set */
        $text = $goodvariations[Jojo::semiRand(0, count($goodvariations)-1)];

        /* swap out the variables in the chosen variation */
        foreach ($variables as $variable => $value) {
            $text = str_replace('['.$variable.']', $value, $text);
        }

        return $text;
    }

    /* reverses the plugin list so most important is first in the array */
    static function listPluginsReverse($file, $whichplugin = 'all', $onlyplugins = false, $forceclean = false)
    {
        $plugins = self::listPlugins($file, $whichplugin = 'all', $onlyplugins = false, $forceclean = false);
        return array_reverse($plugins);
    }

    /* most important is last in the array */
    static function listPlugins($file, $whichplugin = 'all', $onlyplugins = false, $forceclean = false)
    {
        global $_db;
        static $_plugins;
        static $_files;

        /* Try to build from file cache (faster) */
        $cachefile = _CACHEDIR . '/listPlugins.txt';
        if (!is_array($_plugins) && !($forceclean || Jojo::ctrlF5()) && file_exists($cachefile)) {
            list($_plugins, $_files) = @unserialize(file_get_contents($cachefile));
        }

        /* Fetch list of all the active plugins */
        if (!is_array($_plugins) || $forceclean) {
            $_plugins = array();
            $_files = array();
            $_plugins['jojo_core'] = _BASEPLUGINDIR . '/jojo_core'; //manually add jojo_core first
            $data = Jojo::selectQuery("SELECT name FROM {plugin} WHERE active='yes' AND name != 'jojo_core' ORDER BY priority DESC");
            foreach ($data as $plugin) {
                /* Work out what folder the plugin lives in */
                if (strpos($plugin['name'], '.phar')) {
                    if (file_exists('phar://' . _PLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = 'phar://' . _PLUGINDIR . '/' . $plugin['name'];
                    } elseif (defined('_ALTPLUGINDIR') && file_exists(_ALTPLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = 'phar://' . _ALTPLUGINDIR . '/' . $plugin['name'];
                    } elseif (file_exists(_BASEPLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = 'phar://' . _BASEPLUGINDIR . '/' . $plugin['name'];
                    }
                } else {
                    if (file_exists(_PLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = _PLUGINDIR . '/' . $plugin['name'];
                    } elseif (defined('_ALTPLUGINDIR') && file_exists(_ALTPLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = _ALTPLUGINDIR . '/' . $plugin['name'];
                    } elseif (file_exists(_BASEPLUGINDIR . '/' . $plugin['name'])) {
                        $_plugins[$plugin['name']] = _BASEPLUGINDIR . '/' . $plugin['name'];
                    }
                }
            }
        }

        $found = array();
        if ($whichplugin == 'all') {
            if (isset($_files[$file])) {
                /* Return cached answer if we have it */
                $found = $_files[$file];
            } else {
                /* Search for the file */
                foreach ($_plugins as $pluginname => $plugindir) {
                    if (file_exists($plugindir . '/' . $file)) {
                        $found[] = $plugindir . '/' . $file;
                    }
                }

                /* Cache the result for next time */
                $_files[$file] = $found;
                file_put_contents($cachefile, serialize(array($_plugins, $_files)));
            }

            if (!$onlyplugins) {
                foreach(Jojo::listThemes($file, $whichplugin, $forceclean) as $themeFile) {
                    $found[] = $themeFile;
                }
            }
            return $found;
        }

        /* Search a specific theme for a file */
        if (file_exists($_plugins[$whichplugin] . '/' . $file)) {
            $found[] = $_plugins[$whichplugin] . '/' . $file;
            return $found;
        }

        if (!$onlyplugins) {
            return Jojo::listThemes($file, $whichplugin, $forceclean);
        }
        return $found;
    }

    static function listThemes($file, $whichtheme = 'all', $forceclean = false)
    {
        global $_db;
        static $_themes;
        static $_files;

        /* Try to build from file cache (faster) */
        $cachefile = _CACHEDIR . '/listThemes.txt';
        if (!is_array($_themes) && !($forceclean || Jojo::ctrlF5()) && file_exists($cachefile)) {
            list($_themes, $_files) = @unserialize(file_get_contents($cachefile));
        }

        /* Fetch and cache list of all the active themes */
        if (!is_array($_themes)) {
            $_themes = array();
            $_files = array();
            $data = Jojo::selectQuery("SELECT name FROM {theme} WHERE active='yes'");
            foreach ($data as $theme) {
                /* Work out what folder the theme lives in */
                if (file_exists(_THEMEDIR . '/' . $theme['name'])) {
                    $_themes[$theme['name']] = _THEMEDIR . '/' . $theme['name'];
                } elseif (file_exists(_BASETHEMEDIR . '/' . $theme['name'])) {
                    $_themes[$theme['name']] = _BASETHEMEDIR . '/' . $theme['name'];
                }
            }

            /* Cache a copy to file */
            file_put_contents($cachefile, serialize(array($_themes, $_files)));
        }

        /* Check all the themes for the file */
        $found = array();
        if ($whichtheme == 'all') {
            if (isset($_files[$file])) {
                /* Return cached answer if we have it */
                $found = $_files[$file];
            } else {
                /* Search for the file */
                foreach ($_themes as $themename => $themedir) {
                    if (file_exists($themedir . '/' . $file)) {
                        $found[] = $themedir . '/' . $file;
                    }
                }

                /* Cache the result for next time */
                $_files[$file] = $found;
                file_put_contents($cachefile, serialize(array($_themes, $_files)));
            }
            return $found;
        }

        /* Search a specific theme for a file */
        if (file_exists($_themes[$whichtheme] . '/' . $file)) {
            $found[] = $_themes[$whichtheme] . '/' . $file;
            return $found;
        }
        return $found;
    }

    /**
     * Return a reference to the ADODB object for code that wants to access it
     * directly.
     */
    public static function adodb()
    {
        return Jojo::_connectToDB();
    }

    /* _connectToDB()
     *
     * Ensure we have a connection to the database
     */
    static function _connectToDB()
    {
        global $_db;

        if (!isset($_db)) {
            /* Include database config */
            if (!defined('_DBUSER')) {
                echo '/config.php does not exist - please add this file with your database connection details.';
            }

            /* Include Database Abstraction Layer and initialize database connection */
            global $ADODB_COUNTRECS, $ADODB_FETCH_MODE;
            include(_BASEPLUGINDIR . '/jojo_core/external/adodb/adodb.inc.php');
            $ADODB_COUNTRECS = false;
            $ADODB_FETCH_MODE = ADODB_FETCH_NUM;
            $_db = ADONewConnection('mysql');
            $_db->Connect(_DBHOST, _DBUSER, _DBPASS, _DBNAME);
            $_db->query("SET CHARACTER SET 'utf8'");
            $_db->query("SET NAMES 'utf8'");
            if (_DEBUG) {
                $_db->LogSQL(true);
            }
        }
        return $_db;
    }

    /* Query functions */

    /**
     * prefixes table names with the global table prefix by replacing {table} with prefix_table.
     * Note there is currently no support for prefixes, so this function simply removes the braces.
     *
     * $query        string The SQL query to prefix.
     */
    static function prefixTables($query)
    {
        return strtr($query, array('{' => '`' . _TBLPREFIX, '}' => '`'));
    }

    /**
     * Runs query and returns an associative array containing results
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     */
    static function selectQuery($query, $values = array())
    {
        Jojo::_connectToDB();

        if (strpos($query, '{') === false) {
            $log = new Jojo_Eventlog();
            $log->code = 'sql';
            $log->importance = 'very low';
            $backtrace = debug_backtrace();
            $log->shortdesc = 'SQL Query does not have marked table from ' . $backtrace[0]['file'] . ' line ' . $backtrace[0]['line'];
            $log->desc = "SQL Query does not have marked tables: \n\n" . $query . "\n\n table names should have curly brackets around them.";
            $log->savetodb();
            unset($log);
        }

        /* Ensure the values are in an array */
        $values = is_array($values) ? $values : array($values);

        /* Prefix the tables */
        $query = Jojo::prefixTables($query);

        /* Include database abstration object */
        global $_db;
        $_db->SetFetchMode(ADODB_FETCH_ASSOC);

        /* Execute Query */
        $rs = $_db->Execute($query, $values);

        if (!$rs) {
            echo $query."     \n";
            echo $_db->ErrorMsg();
            var_dump(debug_backtrace());
            exit();
        }

        /* Fetch rows */
        $rows = !$rs->EOF ? $rs->GetArray() : array();

        /* Return data */
        return $rows;
    }

    /**
     * Runs query and returns an associative array containing results
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     */
    static function selectAssoc($query, $values = array())
    {
        Jojo::_connectToDB();

        /* Execute Query */
        return Jojo::adodb()->getAssoc(Jojo::prefixTables($query), $values);
    }

    /**
     * Runs query and returns the first result from a query as an associative array
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     */
    static function selectRow($query, $values = array())
    {
        /* Ensure the values are in an array */
        $values = is_array($values) ? $values : array($values);

        /* Include database abstration object */
        return Jojo::adodb()->getRow(Jojo::prefixTables($query), $values);
    }

    /**
     * Use for update queries. Runs query, returns number of rows affected if query is successful.
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     */
    static function updateQuery($query, $values = array())
    {
        Jojo::_connectToDB();

        if (strpos($query, '{') === false) {
            $log = new Jojo_Eventlog();
            $log->code = 'sql';
            $log->importance = 'very low';
            $backtrace = debug_backtrace();
            $log->shortdesc = 'SQL Query does not have marked table from '. $backtrace[0]['file'] .' line '.$backtrace[0]['line'];
            $log->desc = "SQL Query does not have marked tables: \n\n" . $query . "\n\n table names should have curly brackets around them.";
            $log->savetodb();
            unset($log);
        }

        /* Ensure the values are in an array */
        $values = is_array($values) ? $values : array($values);

        /* Prefix the tables */
        $query = Jojo::prefixTables($query);

        if (_DEBUG) {
            if (strtoupper(substr($query, 0, 6)) != 'UPDATE' &&
                strtoupper(substr($query, 0, 7)) != 'REPLACE') {
                echo "<hr>\nNon Update query: $query\n";
                if (function_exists('xdebug_get_function_stack')) {
                    $stack = xdebug_get_function_stack();
                    $last = array_pop($stack);
                    echo sprintf("<br/>Called from %s:%s\n", $last['file'], $last['line']);
                }
                echo "<hr>\n";
            }
        }

        /* Include database abstration object */
        global $_db;

        /* Execute Query */
        $rs = $_db->Execute($query, $values);

        if (!$rs) {
            echo $query."\n" . print_r($values, true) . "\n";
            echo $_db->ErrorMsg();
            var_dump(debug_backtrace());
            exit();
        }

        /* Return number of affected rows */
        return $_db->Affected_Rows();
    }

    /**
     * Use for delete queries.
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     * @return  integer The number of rows deleted by this query.
     */
    static function deleteQuery($query, $values = array())
    {
        Jojo::_connectToDB();

        if (strpos($query, '{') === false) {
            $log = new Jojo_Eventlog();
            $log->code = 'sql';
            $log->importance = 'very low';
            $backtrace = debug_backtrace();
            $log->shortdesc = 'SQL Query does not have marked table from '. $backtrace[0]['file'] .' line '.$backtrace[0]['line'];
            $log->desc = "SQL Query does not have marked tables: \n\n" . $query . "\n\n table names should have curly brackets around them.";
            $log->savetodb();
            unset($log);
        }

        /* Ensure the values are in an array */
        $values = is_array($values) ? $values : array($values);

        /* Prefix the tables */
        $query = Jojo::prefixTables($query);

        if (_DEBUG) {
            if (strtoupper(substr($query, 0, 6)) != 'DELETE') {
                echo "<hr>Non Delete query: $query<hr>";
            }
        }

        /* Include database abstration object */
        global $_db;

        /* Execute Query */
        $rs = $_db->Execute($query, $values);

        if (!$rs) {
            echo $query."     \n";
            echo $_db->ErrorMsg();
            var_dump(debug_backtrace());
            exit();
        }

        /* Return number of affected rows */
        return $_db->Affected_Rows();
    }

    /**
     * Use for insert queries. Runs query, returns the new ID of the inserted record
     *
     * $query        string The SQL query to run.
     * $values       array An optional array of values to replace ? characters in query
     */
    static function insertQuery($query, $values = array())
    {
        Jojo::_connectToDB();

        if (strpos($query, '{') === false) {
            $log = new Jojo_Eventlog();
            $log->code = 'sql';
            $log->importance = 'very low';
            $backtrace = debug_backtrace();
            $log->shortdesc = 'SQL Query does not have marked table from '. $backtrace[0]['file'] .' line '.$backtrace[0]['line'];
            $log->desc = "SQL Query does not have marked tables: \n\n" . $query . "\n\n table names should have curly brackets around them.";
            $log->savetodb();
            unset($log);
        }

        /* Ensure the values are in an array */
        $values = is_array($values) ? $values : array($values);

        /* Prefix the tables */
        $query = Jojo::prefixTables($query);

        /* Include database abstration object */
        global $_db;

        /* Execute Query */
        $rs = $_db->Execute($query, $values);

        if (!$rs) {
            echo $query."\n" . print_r($values, true) . "\n";
            echo $_db->ErrorMsg();
            exit();
        }

        /* Return new id */
        return $_db->Insert_ID();
    }

    /**
     * Use for queries that effect the database structure such as DROP or ALTER.
     *
     * $query        string The SQL query to run.
     */
    static function structureQuery($query)
    {
        Jojo::_connectToDB();

        if (strpos($query, '{') === false) {
            $log = new Jojo_Eventlog();
            $log->code = 'sql';
            $log->importance = 'very low';
            $backtrace = debug_backtrace();
            $log->shortdesc = 'SQL Query does not have marked table from '. $backtrace[0]['file'] .' line '.$backtrace[0]['line'];
            $log->desc = "SQL Query does not have marked tables: \n\n" . $query . "\n\n table names should have curly brackets around them.";
            $log->savetodb();
            unset($log);
        }

        /* Prefix the tables */
        $query = Jojo::prefixTables($query);

        if (_DEBUG) {
            /*if (strtoupper(substr($query, 0, 5)) != 'ALTER' &&
                strtoupper(substr($query, 0, 4)) != 'DROP') {
                echo "<hr>Non Update query: $query<hr>";
            }
            */
        }
        /* Include database abstration object */
        global $_db;

        /* Execute Query */
        $rs = $_db->Execute($query);

        if (!$rs) {
            echo $query."     \n";
            echo $_db->ErrorMsg();
            exit();
        }

        /* Return number of affected rows */
        return ($_db->Affected_Rows()) ? $_db->Affected_Rows() : true;
    }

    /**
     * Check the structure of a database table. Will create the table if it
     * does not exist already. Will add missing columns.
     *
     * TODO: Check indexes exist and are correct
     *
     * $tablename   string  Name of the table
     * $createQuery string  SQL needed to create the table.
     */
    static function checkTable($tablename, $createQuery)
    {
        Jojo::_connectToDB();
        global $_db;

        /* "TYPE=InnoDB" syntax is deprecated and breaks some versions of MySQL. Use "ENGINE=InnoDB" instead. */
        $createQuery = preg_replace('/TYPE\\s*=\\s*(innodb|myisam)/i', 'ENGINE=$1', $createQuery);

        $result = array();
        if (!Jojo::tableexists($tablename)) {
            /* Create missing table */
            Jojo::structureQuery($createQuery);
            $result['created'] = true;
        } else {
            /* Check existing table */

            /* Seperate details of the new table spec */
            $new = explode("\n", $createQuery);
            $newCols = array();
            foreach ($new as $k => $v) {
                $v = trim($v);
                if ($v && $v[0] == '`') {
                    $newCols[substr($v, 1, strpos($v, '`', 2) - 1)] = rtrim($v, ',');
                }
            }

            /* Get and separate details of existing table */
            Jojo::adodb()->execute("SET SQL_QUOTE_SHOW_CREATE = 1;");
            $res = Jojo::selectQuery(sprintf("SHOW CREATE TABLE {%s};", $tablename));

            $current = explode("\n", $res[0]['Create Table']);;
            $currentCols = array();
            foreach ($current as $k => $v) {
                $v = trim($v);
                if ($v && $v[0] == '`') {
                    $currentCols[substr($v, 1, strpos($v, '`', 2) - 1)] = rtrim($v, ',');
                }
            }

            /* See if there is anything missing or different from the existing table */
            $different = array();
            $after = '';
            foreach ($newCols as $f => $sql) {
                if (isset($currentCols[$f]) && $currentCols[$f] != $sql) {
                    /* Check all parts of the expected sql are in the found sql */

                    /* convert VARCHAR ( 40 ) to VARCHAR(40) for consistency in the matching process */
                    $regex = '/(varchar|char|int) *\\( *([0-9]+) *\\)/i';
                    $replace = '\\1(\\2)';
                    $currentCols[$f]  = preg_replace($regex, $replace, $currentCols[$f] );
                    $sql              = preg_replace($regex, $replace, $sql );
                    /* uppercase keywords so they match */
                    $regex = '/\b(varchar|char|int|bigint|default|not|null|enum|auto_increment|on|update)\b/i';
                    $currentCols[$f]  = preg_replace_callback($regex, create_function('$matches', 'return strtoupper($matches[0]);'), $currentCols[$f]);
                    $sql              = preg_replace_callback($regex, create_function('$matches', 'return strtoupper($matches[0]);'), $sql);
                    /* remove unwanted whitespace */
                    $regex = '/\s{2,}/i';
                    $replace = '\\1(\\2)';
                    $currentCols[$f]  = trim(preg_replace($regex, $replace, $currentCols[$f]));
                    $sql              = trim(preg_replace($regex, $replace, $sql));
                    /* INT becomes INT(11) */
                    $regex = '/\b(int)( ?[^(\\s])/i';
                    $replace = '\\1(11)\\2';
                    $currentCols[$f]  = preg_replace($regex, $replace, $currentCols[$f]);
                    $sql              = preg_replace($regex, $replace, $sql);
                    /* BIGINT becomes BIGINT(20) */
                    $regex = '/\b(bigint)( ?[^(\\s])/i';
                    $replace = '\\1(20)\\2';
                    $currentCols[$f]  = preg_replace($regex, $replace, $currentCols[$f]);
                    $sql              = preg_replace($regex, $replace, $sql);

                    $eArray = explode(' ', $currentCols[$f]);
                    $fArray = explode(' ', $sql);

                    /* add a DEFAULT '' clause to varchars with NOT NULL */
                    if ((strpos(strtolower($currentCols[$f]),'varchar')!==false) && (strpos(strtolower($currentCols[$f]),'not null')!==false) && (strpos(strtolower($currentCols[$f]),'default')===false)) {
                        $eArray[] = 'DEFAULT';
                        $eArray[] = '\'\'';
                    }

                    /* add a NULL clause to text columns with NOT NULL */
                    if ((strpos(strtolower($currentCols[$f]),'text') !== false) && (strpos(strtolower($currentCols[$f]),'null') === false)) {
                        $eArray[] = 'NULL';
                    }

                    if (count(array_intersect($eArray, $fArray)) != count($fArray)) {
                        /* Record different column */
                        $changesql = preg_replace('/`(.*?)`(.*)/', '`$1` `$1` $2', $sql);
                        if ($changesql == '') $changesql = $sql;
                        $result['different'][$f] = array(
                                                    'found' => $currentCols[$f],
                                                    'expected' => $sql,
                                                    'alter' => 'ALTER TABLE {'.$tablename.'} CHANGE '.$changesql.';'
                                                    );
                    }
                } elseif (!isset($currentCols[$f])) {
                    /* Add missing column */
                    $query = sprintf('ALTER TABLE {%s} ADD COLUMN %s', $tablename, $sql);
                    if ($after) {
                        $query .= " AFTER `$after`";
                    }
                    Jojo::structureQuery($query);
                    $result['added'][$f] = 'added';
                }
                $after = $f;
            }
        }
        return $result;
    }

    static function printTableDifference($table,$difference) {
        if (isset($difference) && is_array($difference)) {
            foreach ($difference as $col => $v) {
                echo sprintf("<div class=\"box\"><div class='error'><font color='red'>Table <b>%s</b> column <b>%s</b> exists but is different to expected - resolve this manually.</font></div>", $table, $col);
                echo sprintf("&nbsp;&nbsp;&nbsp;&nbsp;Found: %s<br/>&nbsp;&nbsp;&nbsp;&nbsp;Expected: %s<br/>", $v['found'], $v['expected']);
                echo sprintf("&nbsp;&nbsp;&nbsp;&nbsp;SQL: %s<br />",$v['alter']);
                echo sprintf("&nbsp;&nbsp;&nbsp;&nbsp;<form method=\"post\"><input type=\"hidden\" name=\"sql\" value=\"%s\" /><input type=\"submit\" name=\"submit\" value=\"Fix\" /></form></div>",$v['alter']);
            }
        }
    }

    /**
     * Returns true if the table exists in the database
     */
    static function tableExists($tablename, $type = 'TABLES')
    {
        /* Get list of tables */
        $tables = Jojo::adodb()->getAssoc("SHOW FULL TABLES");

        /* the above code does not work on some servers - get the table list the old-fashioned way */
        if (!is_array($tables)) {
            Jojo::_connectToDB();
            $tablename = trim(Jojo::prefixTables('{' . $tablename . '}'), '`');

            /* Include database abstration object */
            global $_db;

            /* Get list of tables */
            $tables = $_db->MetaTables('TABLES');

            /* Return result */
            return (boolean)(is_array($tables) && in_array($tablename, $tables));
        }

        /* Return result */
        $type = ($type == 'VIEWS') ? 'VIEW' : 'BASE TABLE';
        $tablename = trim(Jojo::prefixTables('{' . $tablename . '}'), '`');
        return isset($tables[$tablename]) && $tables[$tablename] == $type;
    }

    /**
     * Returns true if the field exists in the table. Be sure to check table exists first
     */
    static function fieldExists($tablename, $fieldname)
    {
        /* Get list of columns in table */
        $tablename = trim(Jojo::prefixTables('{' . $tablename . '}'), '`');
        $columns = Jojo::adodb()->MetaColumnNames($tablename, true);

        /* Return result */
        return in_array($fieldname, $columns);
    }

    /* Prepares a string for use in database queries */
    static function clean($unclean)
    {
        Jojo::_connectToDB();

        /* Get the quoted string */
        $quoted = Jojo::adodb()->qstr($unclean, get_magic_quotes_runtime());

        /* Remove the quotes off the ends */
        $clean = ($quoted[0] == "'") ? substr($quoted, 1, -1) : $quoted;
        /* Return the clean string */
        return $clean;
    }

    /* Prepares an integer for use in database queries - defaults to zero for any non-numeric input */
    static function cleanInt($unclean_string)
    {
        $clean_string = Jojo::clean($unclean_string);
        if ($clean_string == '') $clean_string = 0;
        return $clean_string;
    }

    /**
     * Basic Script timer - to start timing, don't give any arguments. To stop timing, give start time as argument. Returns start time or total seconds elapsed
     * Todo, allow function to return time in 1:20.56 as opposed to 80.56
     */
    static function timer($starttime='')
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = ((float)$usec + (float)$sec);
        if ($starttime == '') {
            return $time;
        } else {
            return $time - $starttime;
        }
    }

    /**
     * Reads request headers to determine if the user pressed CTRL-F5 for a full refresh
     */
    static function ctrlF5() {
        if (!isset($_SERVER['HTTP_PRAGMA'])) return false;
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) return false;
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) return false;
        return true;
    }

    /**
     * returns the file extension of the supplied string
     */
    static function getFileExtension($file)
    {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    /**
     * caches a file in the public cache area (ie for files the public are allowed to see)
     */
    static function publicCache($filename, $data=false, $modified=false)
    {
        $extensions = array('jpg', 'jpeg', 'gif', 'png', 'js', 'css');
        $extension = Jojo::getFileExtension($filename);
        if (!in_array($extension, $extensions)) {
            return false;
        }
        $publiccachefile = _CACHEDIR.'/public/'.md5($filename).'.'.$extension;
        Jojo::RecursiveMkdir(_CACHEDIR.'/public/'); //in case this folder does not exist
        if (!$data) {
            return $publiccachefile; //if no data is supplied, return the name of the cache location
        }
        file_put_contents($publiccachefile, $data);
        if (is_int($modified)) {
            touch($publiccachefile, $modified);
        }
        /* todo: periodic cache cleanup */
    }

    /**
     * clears all files in the public cache area. Use an array of file extensions as an argument to restrict what gets cleared (todo)
     */
    static function clearPublicCache($extensions=false)
    {
        $cache = scandir(_CACHEDIR.'/public/');
        if (is_array($cache)) {
            foreach ($cache as $filename) {
                if (preg_match('/^[0-9a-f]{32}\\.[0-9a-z]{2,4}$/im', $filename)) {
                    unlink(_CACHEDIR.'/public/'.$filename);
                }
            }
        }
    }

    /**
     * converts a file extension to a mime type from the list of known types
     */
    static function getMimeType($filename) {
        $extension = Jojo::getFileExtension($filename);

        $mime = array(
            '' => 'application/octet-stream',
            '323' => 'text/h323',
            'acx' => 'application/internet-property-stream',
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'asf' => 'video/x-ms-asf',
            'asr' => 'video/x-ms-asf',
            'asx' => 'video/x-ms-asf',
            'au' => 'audio/basic',
            'avi' => 'video/x-msvideo',
            'axs' => 'application/olescript',
            'bas' => 'text/plain',
            'bcpio' => 'application/x-bcpio',
            'bin' => 'application/octet-stream',
            'bmp' => 'image/bmp',
            'c' => 'text/plain',
            'cat' => 'application/vnd.ms-pkiseccat',
            'cdf' => 'application/x-cdf',
            'cer' => 'application/x-x509-ca-cert',
            'class' => 'application/octet-stream',
            'clp' => 'application/x-msclip',
            'cmx' => 'image/x-cmx',
            'cod' => 'image/cis-cod',
            'cpio' => 'application/x-cpio',
            'crd' => 'application/x-mscardfile',
            'crl' => 'application/pkix-crl',
            'crt' => 'application/x-x509-ca-cert',
            'csh' => 'application/x-csh',
            'css' => 'text/css',
            'dcr' => 'application/x-director',
            'der' => 'application/x-x509-ca-cert',
            'dir' => 'application/x-director',
            'dll' => 'application/x-msdownload',
            'dms' => 'application/octet-stream',
            'doc' => 'application/msword',
            'dot' => 'application/msword',
            'dvi' => 'application/x-dvi',
            'dxr' => 'application/x-director',
            'eps' => 'application/postscript',
            'etx' => 'text/x-setext',
            'evy' => 'application/envoy',
            'exe' => 'application/octet-stream',
            'fif' => 'application/fractals',
            'flv' => 'video/x-flv',
            'flr' => 'x-world/x-vrml',
            'gif' => 'image/gif',
            'gtar' => 'application/x-gtar',
            'gz' => 'application/x-gzip',
            'h' => 'text/plain',
            'hdf' => 'application/x-hdf',
            'hlp' => 'application/winhlp',
            'hqx' => 'application/mac-binhex40',
            'hta' => 'application/hta',
            'htc' => 'text/x-component',
            'htm' => 'text/html',
            'html' => 'text/html',
            'htt' => 'text/webviewhtml',
            'ico' => 'image/x-icon',
            'ief' => 'image/ief',
            'iii' => 'application/x-iphone',
            'ins' => 'application/x-internet-signup',
            'isp' => 'application/x-internet-signup',
            'jfif' => 'image/pipeg',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'latex' => 'application/x-latex',
            'lha' => 'application/octet-stream',
            'lsf' => 'video/x-la-asf',
            'lsx' => 'video/x-la-asf',
            'lzh' => 'application/octet-stream',
            'm13' => 'application/x-msmediaview',
            'm14' => 'application/x-msmediaview',
            'm3u' => 'audio/x-mpegurl',
            'man' => 'application/x-troff-man',
            'mdb' => 'application/x-msaccess',
            'me' => 'application/x-troff-me',
            'mht' => 'message/rfc822',
            'mhtml' => 'message/rfc822',
            'mid' => 'audio/mid',
            'mny' => 'application/x-msmoney',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2' => 'video/mpeg',
            'mp3' => 'audio/mpeg',
            'mpa' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpp' => 'application/vnd.ms-project',
            'mpv2' => 'video/mpeg',
            'ms' => 'application/x-troff-ms',
            'mvb' => 'application/x-msmediaview',
            'nws' => 'message/rfc822',
            'oda' => 'application/oda',
            'p10' => 'application/pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7b' => 'application/x-pkcs7-certificates',
            'p7c' => 'application/x-pkcs7-mime',
            'p7m' => 'application/x-pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/x-pkcs7-signature',
            'pbm' => 'image/x-portable-bitmap',
            'pdf' => 'application/pdf',
            'pfx' => 'application/x-pkcs12',
            'pgm' => 'image/x-portable-graymap',
            'pko' => 'application/ynd.ms-pkipko',
            'pma' => 'application/x-perfmon',
            'pmc' => 'application/x-perfmon',
            'pml' => 'application/x-perfmon',
            'pmr' => 'application/x-perfmon',
            'pmw' => 'application/x-perfmon',
            'png' => 'image/png',
            'pnm' => 'image/x-portable-anymap',
            'pot' => 'application/vnd.ms-powerpoint',
            'ppm' => 'image/x-portable-pixmap',
            'pps' => 'application/vnd.ms-powerpoint',
            'ppt' => 'application/vnd.ms-powerpoint',
            'prf' => 'application/pics-rules',
            'ps' => 'application/postscript',
            'pub' => 'application/x-mspublisher',
            'qt' => 'video/quicktime',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ras' => 'image/x-cmu-raster',
            'rgb' => 'image/x-rgb',
            'rmi' => 'audio/mid',
            'roff' => 'application/x-troff',
            'rtf' => 'application/rtf',
            'rtx' => 'text/richtext',
            'scd' => 'application/x-msschedule',
            'sct' => 'text/scriptlet',
            'setpay' => 'application/set-payment-initiation',
            'setreg' => 'application/set-registration-initiation',
            'sh' => 'application/x-sh',
            'shar' => 'application/x-shar',
            'sit' => 'application/x-stuffit',
            'snd' => 'audio/basic',
            'spc' => 'application/x-pkcs7-certificates',
            'spl' => 'application/futuresplash',
            'src' => 'application/x-wais-source',
            'sst' => 'application/vnd.ms-pkicertstore',
            'stl' => 'application/vnd.ms-pkistl',
            'stm' => 'text/html',
            'svg' => 'image/svg+xml',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc' => 'application/x-sv4crc',
            'swf' => 'application/x-shockwave-flash',
            't' => 'application/x-troff',
            'tar' => 'application/x-tar',
            'tcl' => 'application/x-tcl',
            'tex' => 'application/x-tex',
            'texi' => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'tgz' => 'application/x-compressed',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'tr' => 'application/x-troff',
            'trm' => 'application/x-msterminal',
            'tsv' => 'text/tab-separated-values',
            'ttf' => 'application/x-font-ttf',
            'txt' => 'text/plain',
            'uls' => 'text/iuls',
            'ustar' => 'application/x-ustar',
            'vcf' => 'text/x-vcard',
            'vrml' => 'x-world/x-vrml',
            'wav' => 'audio/x-wav',
            'wcm' => 'application/vnd.ms-works',
            'wdb' => 'application/vnd.ms-works',
            'wks' => 'application/vnd.ms-works',
            'wmf' => 'application/x-msmetafile',
            'woff' => 'application/x-font-woff',
            'wps' => 'application/vnd.ms-works',
            'wri' => 'application/x-mswrite',
            'wrl' => 'x-world/x-vrml',
            'wrz' => 'x-world/x-vrml',
            'wsdl'=> 'text/xml',
            'xaf' => 'x-world/x-vrml',
            'xbm' => 'image/x-xbitmap',
            'xla' => 'application/vnd.ms-excel',
            'xlc' => 'application/vnd.ms-excel',
            'xlm' => 'application/vnd.ms-excel',
            'xls' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',
            'xlw' => 'application/vnd.ms-excel',
            'xml' => 'text/xml',
            'xof' => 'x-world/x-vrml',
            'xpm' => 'image/x-xpixmap',
            'xwd' => 'image/x-xwindowdump',
            'z' => 'application/x-compress',
            'zip' => 'application/zip'
        );
        if (isset($mime[$extension])) {
            return $mime[$extension];
        }
        // If we can't find it using the array above, see if the magic file can tell us
        if (file_exists($filename)) {
            $finfo = new finfo(FILEINFO_MIME);
            $type = $finfo->file($filename);
            if ($type) {
                return $type;
            }
        }
        return '';
    }

    static function getMySQLType($table, $field) {
        $data = Jojo::selectQuery('SHOW COLUMNS FROM {' . $table . '}');
        foreach ($data as $row) {
            if ($row["Field"] == $field) return $row["Type"];
        }
        return false;
    }

    /**
     * returns a block of text that is useful for adding to admin emails
     */
    static function emailFooter() {
        $referer                            = isset($_SESSION['referer']) ? $_SESSION['referer'] : 'direct visitor';
        $searchphrase                       = isset($_SESSION['referer_searchphrase']) ? $_SESSION['referer_searchphrase'] : '';
        $footer                             = "\r\n\r\n______________________________________\r\n";
        $footer                            .= "This message was sent from the ".Jojo::getOption('sitetitle')." website.\r\n";
        $footer                            .= "Referer: ".$referer."\r\n";
        if (!empty($searchphrase)) $footer .= "Search Phrase: ".$searchphrase."\r\n";
        $footer                            .= "Browser: ".Jojo::getbrowser()."\r\n";
        //$footer                          .= "IP Address: ".Jojo::getIP()."\r\n";

        /* allow plugins to modify the standard email footer */
        $footer = Jojo::applyFilter('email_footer', $footer);

        return $footer;
    }

    /**
     * Recursively create a directory
     *
     * $path string Name of the folder to directory
     *
     * return mixed (boolean)true if the folder was created.
     *              (boolean)false if the folder could not be created.
     *              (int)-1 if the folder already exists.
     */
    static function recursiveMkdir($path = false) {
        if (!$path) {
            return false;
        }
        $res = -1;
        if (!file_exists($path)) {
            $res = Jojo::RecursiveMkdir(dirname($path));
            if (!file_exists($path)) {
                $res = mkdir($path, 0777);
            }
        }
        return $res;
    }

    /* Will return the prefix for a relative URL (eg http://www.foo.com/).
     * The output of this function depends on whether the current page is secure or not.
     * For search engine reasons, relative URLs are preferred, but you can't use relative URLs when transferring between secure and insecure.
     * So, If insecure -> insecure or secure -> secure use relative. If anything else, use absolute
     */
    static function urlPrefix($link2secure=false)
    {
        global $issecure;
        if (!isset($issecure)) {$issecure = false;}

        if ($link2secure && $issecure) {
            return '';
        } else if ($link2secure && !$issecure) {
            return _SECUREURL."/";
        } else if (!$link2secure && $issecure) {
            return _SITEURL."/";
        } else if (!$link2secure && !$issecure) {
            return '';
        }
    }

    /* Converts "yes" to true and "no" to false */
    static function yes2true($text)
    {
        return (strtolower($text) == 'yes');
    }

    /* Convert multi-line textarea text to an array while removing empty values, stripping whitespace by default */
    static function ta2array($text, $trimwhitespace=true)
    {
        $text = preg_split("/[\r\n]+/", $text);
        if ($trimwhitespace) {
            $text = array_map('trim', $text);
        }
        // Remove empty entries
        $text = array_filter($text, 'strlen');
        return $text;
    }

    /* Turns text from a text area into a key/value array */
    /* Sample text:
            foo=bar
            one=two
       becomes
            array(2) {
                foo => "bar",
                one => "two"
            }
    */
    static function ta2kv($text, $delim="=")
    {
        $delim = preg_quote($delim, '/');
        $text = preg_replace("/([\r\n]+)\\s+([\r\n]+)/", "$1", trim($text));
        preg_match_all("/([^\r\n".$delim."]+)".$delim."?([^\r\n$]+)?/", $text, $matches);
        return array_combine(
            array_map('trim', $matches[1]),
            array_map('trim', $matches[2])
        );
    }

    /* Convert comma separated values (single line) to an array, stripping empty values and whitespace by default */
    static function csv2array($text, $trimwhitespace=true)
    {
        $text = explode(",", $text);
        if ($trimwhitespace) {
            $text = array_map('trim', $text);
        }
        // Remove empty entries
        $text = array_filter($text, 'strlen');
        return $text;
    }

    /* Rewrites standard Jojo URLs */
    static function rewrite($table, $id, $name='index', $suffix='s', $allowurlprefix='', $pagenumber=1)
    {
        global $thelanguage;
        /* Make name lower case */
        $name = strtolower($name);

        /* Convert non-UTF8 Characters */
        if (extension_loaded('mbstring') && mb_detect_encoding($name)!='UTF-8') $name = utf8_encode($name);

        /* Remove some characters */
        $matches = array( '"', '!', '#', '$', '%', '^', '*', '<', '>',
                          '=',  '\'', ',', '(', ')', '?', '.', '!',
                          ',','[',']','{','}',':',';','`','~','|');
        $name = str_replace($matches, '', $name);

        /* Replace some characters */
        $matches = array( '+', '/', ' - ', ' ', ', ',   '&',  '@', ':', '--');
        $replace = array( '-', '-',   '-', '-', '-',  'and', 'at', '-', '-' );
        $name = str_replace($matches, $replace, $name);

        /* Remove remainging dashes */
        $name = str_replace('--','-',$name);
        $name = trim($name, '-'); //remove trailing + leading dashes
         $name = urlencode($name);
       $pagecode = $pagenumber <= 1 ? '' : 'p'.$pagenumber;
        if ($table.$suffix == 'pages') {
            return $id . $pagecode . '/' . $name . '/'; // 23/name-of-page/
        } else {
            return $table . $suffix . '/' . $id . $pagecode . '/' . $name . '/'; // table-name/23p2/name-of-item/
        }
    }

    /* Cleans up a URL by making lowercase, removing special chars etc */
    static function cleanURL($url)
    {
        /* Make url lower case */
        $url = strtolower($url);

        /* Convert non-UTF Characters */
        if (extension_loaded('mbstring') && mb_detect_encoding($url)!='UTF-8') $url = utf8_encode($url);

        /* Remove some characters */
        $matches = array( '"', '!', '#', '$', '%', '^', '*', '<', '>', '=', '\'', ',', '(', ')', '?', '.', '!', ',','[',']','{','}',':',';','`','~','|');
        $url = str_replace($matches, '', $url);

        /* Replace some characters */
        $matches = array( '/', ' - ', ' ', ', ',   '&',  '@', ':', '--' );
        $replace = array( '-',   '-', '-',  '-', 'and', 'at', '-', '-'  );
        $url = str_replace($matches, $replace, $url);

        /* Remove remaining dashes */
        $url = str_replace('--','-',$url);
        $url = trim($url,'-');

       /* Encode to catch any remaining non-english characters  */
        $url = urlencode($url);

        return $url;
    }

    /* The standard file_exists() function will return true if a directory exists of the same name, this won't */
    static function fileExists($file)
    {
        if (!is_file($file))     return false;
        if (!file_exists($file)) return false;
        return true;
    }

    /* Turns a number in bytes to either XXb, XXkb, XXmb, XXgb depending what is appropriate. Decimal Cutoff will reduce the decimal places by one for larger numbers (for example when you want the decimal place in 1.3Mb but not in 345.4kb) */
    static function roundBytes($bytes, $decimals = 1, $decimalcutoff=false)
    {
        $sizes = array('TB' => 1099511627776, 'GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024, 'B' => 1);
        foreach ($sizes as $unit => $b) {
            if ($bytes >= $b) {
                $num = number_format($bytes/$b, $decimals);
                if ($decimalcutoff && ($num > $decimalcutoff)) $num = number_format($bytes/$b, max($decimals-1, 0));
                return $num.$unit;
            }
        }
        return $bytes . 'B';
    }

    /**
     * Return the first non-empty, non-zero variable in the list
     *
     * $var1 mixed First variable that is checked
     * $var2 mixed Second variable that is checked
     * $varn nixed nth vatiable that is checked
     */
    static function either($var1, $var2 = '', $var3 = '', $var4 = '', $var5 = '', $var6 = '', $var7 = '', $var8 = '')
    {
        if (($var1 != '') && ($var1 != '0')) return $var1;
        if (($var2 != '') && ($var2 != '0')) return $var2;
        if (($var3 != '') && ($var3 != '0')) return $var3;
        if (($var4 != '') && ($var4 != '0')) return $var4;
        if (($var5 != '') && ($var5 != '0')) return $var5;
        if (($var6 != '') && ($var6 != '0')) return $var6;
        if (($var7 != '') && ($var7 != '0')) return $var7;
        if (($var8 != '') && ($var8 != '0')) return $var8;
        return '';
    }

    /**
     * same as ECHOIF but returns the value rather than echoing it
     */
    static function onlyIf($var, $string)
    {
        if (($var != '') and ($var != '0')) return $string;
    }

    static function cssAddAssets($css) {
        $css = preg_replace_callback('%url\\([\'"]?\\.\\./(.*?)[\'"]?\\)%', array('Jojo', '_Callback_CssAddAssets'), $css);
        return $css;
    }

    static function _Callback_CssAddAssets($matches) {
        global $nextasset;

        static $ASSETS;
        static $n;

        /* Get asset domain names from the database, once */
        if (is_null($ASSETS)) {
            $ASSETS = array();
            $rows = Jojo::selectQuery("SELECT * FROM {option} WHERE op_name = 'assetdomains'");
            if (empty($rows[0]['op_value'])) return 'url(../'.$matches[1].')';

            $lines = explode("\n", $rows[0]['op_value']);

            foreach($lines as $line) {
                if (trim($line)) {
                    $ASSETS[] = trim($line);
                }
            }
            $n = count($ASSETS) - 1;
        }

        /* No asset domains, don't change */
        if (!$n > 0) {
            return 'url(../'.$matches[1].')';
        }

        /* Add asset domain */
        //$nextasset = ($nextasset >= $n) ? 0 : $nextasset + 1;  //This code will alternate evenly between asset domains
        $nextasset = Jojo::semiRand(0, $n, $matches[1]);
        return 'url(' . $ASSETS[$nextasset] . '/' . $matches[1] . ')';
    }

    /**
     * Adds one or more CSS files to be merged with styles.css
     *
     * $files  string,array  The name of the CSS file to add, or an array of filenames
     */
    static function mergeCSS($target, $files=false)
    {
        static $list;
        if (!isset($list)) $list = array();
        if (!isset($list[$target])) $list[$target] = array();
        if ($files === false) return $list[$target]; //leave the arguments blank to return a list of CSS files already added
        /* add single file */
        if (!is_array($files)) {
            $list[$target][] = $files;
        } else {
            $list[$target] = array_merge($list[$target], $files);
        }
        return true;
    }

    /**
     * Get the value of an option. Returning the cached value if available.
     *
     * $name      string  Name of the option
     * $default   string  Value to return if the option is not found
     * $foreclean boolean True will force all options to be
     *                    refreshed from the database
     */
    static function getOption($name, $default = null, $forceclean = false)
    {
        /* Fetch options */
        $_options = Jojo::getOptions($forceclean);

        /* Return option value if we have it */
        if (isset($_options[$name])) {
            $value = $_options[$name];
        } else {
            /* Return the default value */
            $value = $default;
        }
        /* allow plugins to override the value of an option */
        $value = Jojo::applyFilter('get_option', $value, array($name, $default));

        return $value;
    }

    /**
     * Set the value of an option.
     *
     * $name      string  Name of the option
     * $value     string  Value to set if the option is not found
     * $foreclean boolean True will force all options to be
     *                    refreshed from the database
     */
    static function setOption($name, $value)
    {
        $data = Jojo::selectQuery("SELECT * FROM {option} WHERE op_name = ?", $name);
        if (count($data)) {
            Jojo::updateQuery("UPDATE {option} SET op_value=? WHERE op_name=?", array($value, $name));
            return true;
        }
        return false;
    }

    /**
     * Get an array of all options and their values from the database. Will
     * return a cached array if available.
     *
     * $foreclean boolean True will force all options to be
     *                    refreshed from the database
     */
    static function getOptions($forceclean = false)
    {
        static $_options;

        /* Fetch options from database if we don't have them */
        if (!is_array($_options) || $forceclean) {
            $_options = array();
            $rows = Jojo::selectQuery("SELECT `op_name`, `op_value` FROM {option}");
            foreach ($rows as $row) {
                $_options[$row['op_name']] = $row['op_value'];
            }
        }

        return $_options;
    }

    /**
     * Remove an option from the database
     * $name      string  Name of the option
     */
    static function removeOption($name)
    {
        $data = Jojo::selectQuery("SELECT * FROM {option} WHERE op_name = ?", $name);
        if (count($data)) {
            Jojo::deleteQuery("DELETE FROM {option} WHERE op_name = ?", $name);
            return true;
        }
        return false;
    }

    /**
    Format should be as follows...
    [[gallery: {name:'Gallery',instructions:''}]]
    -Variables in parenthesis
    -Colon in variable separates the variable name and it's description
    -Add as many variables as needed
    -At present, there is no escaping of control characters
    */
    static function addContentVar($options)
    {
        global $_contentvars;
        if (!is_array($_contentvars)) {
            $_contentvars = array();
        }
        /* $options is a keyed array of options */
        if (!is_array($options)) return '';

        if (!isset($options['name'])) return '';
        if (!isset($options['format'])) return '';
        if (!is_array($options['vars'])) $options['vars'] = array();
        if (!isset($options['description'])) $options['description'] = '';
        if (!isset($options['icon'])) $options['icon'] = 'images/cms/icons/brick.png';

        $options['jtagformat'] = $options['format'];

        /* ensure there is a value for each element of each var */
        foreach ($options['vars'] as $k => $v) {
            if (!isset($options['vars'][$k]['name'])) $options['vars'][$k]['name'] = '';
            if (!isset($options['vars'][$k]['description'])) $options['vars'][$k]['description'] = '';
            $display = !empty($options['vars'][$k]['description']) ? $options['vars'][$k]['description'] : $options['vars'][$k]['name'];
            $options['jtagformat'] = str_replace('['.$k.']', '@'.$display.'@', $options['jtagformat']);
        }

        if (!isset($_contentvars[$options['name']])) {
            $_contentvars[$options['name']] = array();
        }
        $_contentvars[$options['name']] = array(
                               'name'=>$options['name'],
                               'format'=>$options['format'],
                               'jtagformat'=>$options['jtagformat'],
                               'vars'=>$options['vars'],
                               'description'=>$options['description'],
                               'icon'=>$options['icon']
                               );
    }

    static function getContentVars()
    {
        global $_contentvars;
        if (!is_array($_contentvars)) {
            $_contentvars = array();
        }
        return $_contentvars;
    }

    /**
     * Add a filter call back. Tell Jojo that a filter is to be run on a filter
     * at a certain point.
     *
     * $tag          string Name of the filter to hook.
     * $functionname string Name of function to add
     * $classname    string Name of the class providing the function
     * $priority     int    Prority of this filter, default is 10
     */
    static function addFilter($tag, $functionname, $classname, $priority = 10)
    {
        global $_filters;

        if (!is_array($_filters)) {
            $_filters = array();
        }

        if (!isset($_filters[$tag])) {
            $_filters[$tag] = array();
        }

        if (!isset($_filters[$tag][$priority])) {
            $_filters[$tag][$priority] = array();
        }

        $_filters[$tag][$priority][serialize(array($classname, $functionname))] = array($classname, $functionname);
    }

    /**
     * Remove a filter added prviously. Called with the same arguments as addfilter
     *
     * $tag          string Name of the filter to remove.
     * $functionname string Name of function to remove
     * $classname    string Name of the class providing the function
     * $priority     int    Prority of this filter, default is 10
     */
    static function removeFilter($tag, $functionname, $classname, $priority = 10)
    {
        global $_filters;

        if (isset($_filters[$tag][$priority][serialize(array($classname, $functionname))])) {
            unset($_filters[$tag][$priority][serialize(array($classname, $functionname))]);
            return true;
        }
        return false;
    }

    /**
     * Apply filters to a tag.
     *
     * $tag  string Name of the filter
     * $data string The data the filter has to work on
     */
    static function applyFilter($tag, $data, $optionalArgs = null)
    {
        global $_filters;

        if (!isset($_filters[$tag])) {
            return $data;
        }

        $args = func_get_args();
        $tag = array_shift($args);
        ksort($_filters[$tag]);

        foreach($_filters[$tag] as $priority => $phooks) {
            foreach($phooks as $hook) {
                $classname = $hook[0];
                $functionname = $hook[1];
                if (!class_exists($classname)) {
                    $classname = 'Jojo_Plugin_' . $hook[0];

                    /* Is the class already available */
                    if (!class_exists($classname)) {
                        /* Class not found, try including from plugin */
                        $pluginfile = $hook[0] . '.php';
                        foreach (Jojo::listPlugins($pluginfile) as $pluginfile) include($pluginfile);
                    }
                }

                /* Is function available */
                if (!is_callable(array($classname, $functionname))) {
                      /* Skip filter if function doesn't exist
                         TODO: log error here */
                        continue 1;
                }

                $args[0] = call_user_func_array(array($classname, $functionname), $args);
            }
        }
        return $args[0];
    }

    /**
     * Add a filter call back. Tell Jojo that a filter is to be run on a filter
     * at a certain point.
     *
     * $tag          string Name of the hook.
     * $functionname string Name of function to add
     * $classname    string Name of the class providing the function
     * $priority     int    Priority of this hook, default is 10
     */
    static function addHook($tag, $functionname, $classname = null, $priority = 10)
    {
        global $_hooks;

        if (!is_array($_hooks)) {
            $_hooks = array();
        }

        if (!isset($_hooks[$tag])) {
            $_hooks[$tag] = array();
        }

        if (!isset($_hooks[$tag][$priority])) {
            $_hooks[$tag][$priority] = array();
        }

        if ($classname) {
            $_hooks[$tag][$priority][serialize(array($classname, $functionname))] = array($classname, $functionname);
        } else {
            $_hooks[$tag][$priority][] = $functionname;
        }
    }

    /**
     * Remove a hook call back. Called with same arguments as above.
     *
     * $tag          string Name of the hook.
     * $functionname string Name of function to add
     * $classname    string Name of the class providing the function
     * $priority     int    Priority of this hook, default is 10
     */
    static function removeHook($tag, $functionname, $classname, $priority = 10)
    {
        global $_hooks;

        if (isset($_hooks[$tag][$priority][serialize(array($classname, $functionname))])) {
            unset($_hooks[$tag][$priority][serialize(array($classname, $functionname))]);
            return true;
        }
        return false;
    }

    /**
     * Runs a hook in php
     */
    static function runHook($tag, $optionalArgs = array())
    {
        global $_hooks;

        if (!isset($_hooks[$tag])) {
            return;
        }

        $result = '';
        ksort($_hooks[$tag]);
        foreach($_hooks[$tag] as $priority => $phooks) {
            foreach($phooks as $hook) {
                $classname = $hook[0];
                $functionname = $hook[1];

                if (!class_exists($classname)) {
                    $classname = 'Jojo_Plugin_' . $hook[0];

                    /* Is the class already available */
                    if (!class_exists($classname)) {
                        /* Class not found, try including from plugin */
                        $pluginfile = $hook[0] . '.php';
                        foreach (Jojo::listPlugins($pluginfile) as $pluginfile) include($pluginfile);
                    }
                }

                /* Is function available */
                if (!is_callable(array($classname, $functionname))) {
                      /* Skip hook if function doesn't exist
                         TODO: log error here */
                        continue 1;
                }

                $result = call_user_func_array(array($classname, $functionname), $optionalArgs);
                $optionalArgs = $result ? $result : $optionalArgs;
            }
        }

        return $optionalArgs;
    }

    /**
     * Runs a hook in smarty
     *
     * $tag  string Name of the hook
     */
    static function runSmartyHook($params, $smarty)
    {
        global $_hooks;

        if (empty($params['hook'])) {
            $smarty->trigger_error("assign: missing 'hook' parameter");
            return;
        }
        $tag = $params['hook'];

        if (!isset($_hooks[$tag])) {
            return '';
        }

        $result = '';
        ksort($_hooks[$tag]);
        foreach($_hooks[$tag] as $priority => $phooks) {
            foreach($phooks as $hook) {
                if (!is_array($hook)) {
                    $result .= $hook();
                    continue;
                }
                $classname = $hook[0];
                $functionname = $hook[1];

                if (!class_exists($classname)) {
                    $classname = 'Jojo_Plugin_' . $hook[0];

                    /* Is the class already available */
                    if (!class_exists($classname)) {
                        /* Class not found, try including from plugin */
                        $pluginfile = $hook[0] . '.php';
                        foreach (Jojo::listPlugins($pluginfile) as $pluginfile) include($pluginfile);
                    }
                }

                /* Is function available */
                if (!is_callable(array($classname, $functionname))) {
                      /* Skip hook if function doesn't exist
                         TODO: log error here */
                        continue 1;
                }

                $result .= call_user_func(array($classname, $functionname));
            }
        }

        return $result;
    }

    /**
     * Runs the Asset hook in smarty
     */
    static function runSmartyAssetHook($params, $smarty)
    {

		$filepath = false;

        if (empty($params['file'])) {
        	if (is_array($params) && count($params)) {
        		$filepath = current($params);
        	}
        } else {
        	$filepath = $params['file'];
        }

        $result = $filepath;

        if (_DEBUG) {
        	return $result.'?r='.rand(1000,10000);
        }


        $cachepath = false;
        if (file_exists(_CACHEDIR.'/'.$filepath)) {
        	$cachepath = _CACHEDIR.'/'.$filepath;
        } else {
        	/* This bit is crude but if this feature takes off we'll clean up the caching of JS and CSS */
        	$ext = pathinfo($filepath, PATHINFO_EXTENSION);
        	if ($ext == 'css') {
        		$filepath = preg_replace('%^css/%', '', $filepath);
        	}
        	$publiccachepath = _CACHEDIR.'/public/'.md5($filepath).'.'.$ext;

		    if (file_exists($publiccachepath)) {
		    	$cachepath = $publiccachepath;
		    }
        }

        if ($cachepath) {
		    $mtime = filemtime($cachepath);
		    $maxcachelength = max(_CONTENTCACHETIME, 28800); // 28800 is a hardcoded "max-age" time in core.php, image.php, js.php and more.
		    if (time() < $mtime + $maxcachelength) {
		    	$result .= "?v=$mtime";
		    }
        }

        return $result;
    }

    /**
     * Function to replace Smarty's build in file resource
     */
    static function smarty_getSecure($tpl_name, &$smarty)
    {
        return true;
    }

    /**
     * Function to replace Smarty's build in file resource
     */
    static function smarty_getTrusted($tpl_name, &$smarty)
    {
    }

    /**
     * Function to replace Smarty's build in file resource
     */
    static function smarty_getTemplate($tpl_name, &$tpl_source, &$smarty)
    {
        static $_cache;

        if (isset($_cache[$tpl_name])) {
            $tpl_source = $_cache[$tpl_name];
            return true;
        }

        $res = Jojo::listThemes('templates/' . $tpl_name);
        if (count($res)) {
            $file = array_pop($res);
            $tpl_source = file_get_contents($file);
            $_cache[$tpl_name] = $tpl_source;
            return true;
        }

        $res = Jojo::listPlugins('templates/' . $tpl_name, 'all', false);
        if (count($res)) {
            $file = array_pop($res);
            $tpl_source = file_get_contents($file);
            $_cache[$tpl_name] = $tpl_source;
            return true;
        }

        if (file_exists($tpl_name)) {
            $tpl_source = file_get_contents($tpl_name);
            $_cache[$tpl_name] = $tpl_source;
            return true;
        }

        return false;
    }

    /**
     * Function to replace Smarty's build in file resource
     */
    static function smarty_getTimestamp($tpl_name, &$tpl_timestamp, &$smarty)
    {
        static $_cache;
        if (isset($_cache[$tpl_name])) {
            $tpl_timestamp = $_cache[$tpl_name];
            return true;
        }

        $res = Jojo::listThemes('templates/' . $tpl_name);
        if (count($res)) {
            $file = array_pop($res);
            $tpl_timestamp = filemtime($file);
            $_cache[$tpl_name] = $tpl_timestamp;
            return true;
        }

        $res = Jojo::listPlugins('templates/' . $tpl_name);
        if (count($res)) {
            $file = array_pop($res);
            $tpl_timestamp = filemtime($file);
            $_cache[$tpl_name] = $tpl_timestamp;
            return true;
        }

        if (file_exists($tpl_name)) {
            $tpl_timestamp = filemtime($tpl_name);
            $_cache[$tpl_name] = $tpl_timestamp;
            return true;
        }

        return false;
    }

    /**
     * Parse the URI to a page id to be displayed
     */
    static function parsepage($uri, $getall=false) {
        global $_uriPatterns;

        if ($getall) $allmatches = array();

        /* Strip the query string off the url */
        $uriParts = explode('?', $uri);
        if(isset($uriParts[1])) {
            $uri = trim($uriParts[0], '/');
            parse_str($uriParts[1], $vars);
            $_GET = array_merge($_GET, $vars);
            $_REQUEST = array_merge($_REQUEST, $vars);
        }
        $uri = trim($uri, '/');

        /* handle subfolder installs */
        $f = ltrim(_SITEFOLDER, 'https://');
        $f = ltrim(_SITEFOLDER, 'http://');
        $temp = explode('/', $f);
        if (count($temp) > 1) {
            array_shift($temp);
            $basefolder = implode('/', $temp);
        } else {
            $basefolder = '';
        }
        $uri = preg_replace('%^'.$basefolder.'/(.*)$%', '$1', $uri);

        /* Strip the section prefix off the URI if there is one*/
        $mldata = Jojo::getMultiLanguageData();
        $section = $mldata['default'];

        /* Find the first part of the URI */
        $uriParts = explode('/', $uri);
        $uriPrefix = $uriParts[0];

        if (isset($mldata['roots'][$uriPrefix])) {
            /* Check if the prefix is a section short code */
            $uri = (string)substr($uri, strlen($uriPrefix));
            $uri = trim($uri, '/');
            $section = $uriPrefix;
        } elseif ($l = array_search($uriPrefix, $mldata['longcodes'])) {
            /* Check if the prefix is a section long code */
            $uri = (string)substr($uri, strlen($uriPrefix));
            $uri = trim($uri, '/');
            $section = $l;
        }

        if (trim($uri) == '') {
            /* We are on a homepage */
            if ($getall) {
                $allmatches[] = $mldata['homes'][$section];
            } else {
                return $mldata['homes'][$section];
            }
        }

        /* Check see if any plugins registered this page */
        foreach ($_uriPatterns as $uriPattern) {
            /* Handle custom handler */
            if ($uriPattern['custom']) {
                if (!class_exists($uriPattern['class']) || !method_exists($uriPattern['class'], $uriPattern['custom'])) {
                    continue;
                }
                $res = call_user_func(array($uriPattern['class'], $uriPattern['custom']), $uri);
                if ($res === false) {
                    /* Didn't match */
                    continue;
                } elseif ($res === true) {
                    /* Did match, find the page in the database */
                    $values = array($uriPattern['class']);
                    /* Search for the page with the url that best matches the current uri */
                    $uri_pieces = explode('/', $uri);
                    $uri_heirachy_list = array();
                    foreach ($uri_pieces as $slug) {
                        $values[] = implode('/', $uri_pieces);
                        $placeholders[] = '?';
                        array_pop($uri_pieces);
                    }
                    $placeholders = implode(', ', $placeholders);

                    $res = Jojo::selectQuery('SELECT pageid, pg_url FROM {page} WHERE pg_link = ? AND pg_url IN ('.$placeholders.') ORDER BY LENGTH(pg_url) DESC', $values);

                    if (isset($res[0]['pageid']) && count($res) == 1 ) {
                        if ($getall) {
                            $allmatches[] =  $res[0]['pageid'];
                        } else {
                            return  $res[0]['pageid'];
                        }
                    } elseif (isset($res[0]['pageid'])) {
                       $root = $mldata['roots'][$section];
                        foreach ($res as $k=>$r){
                            /* push any pages not from this section to the end of the array */
                            if ($root != Jojo::getSectionRoot($r['pageid']) ) {
                                unset($res[$k]);
                                array_push($res, $r);
                            }
                        }
                        preg_match('#([a-z0-9-_/]*)\/#', $uri, $matches);
                        $pageid ='';
                        foreach ($res as $r){
                            if ($r['pg_url'] == $matches[1]) {
                                $pageid = $r['pageid'];
                                break;
                            }
                        }
                        $page1 = array_shift($res);
                        $pageid = !$pageid ? $page1['pageid'] : $pageid;
                        if ($getall) {
                            $allmatches[] = $pageid;
                        } else {
                            return $pageid;
                        }
                    }
                } else {
                    /* Returned explicit page number */
                    if ($getall) {
                        $allmatches[] = $res;
                    } else {
                        return $res;
                    }

                }
                continue;
            }

            /* Convert the pattern to a regex */
            $pattern = $uriPattern['pattern'];
            $parts = array();
            $iMax = strlen($pattern);
            $open = 0; $current = '';
            for ($i = 0; $i < $iMax; $i++) {
                if ($pattern[$i] == '[') {
                    if ($open == 0 && strlen($current)) {
                        $parts[] = $current;
                        $current = '';
                    }
                    $open++;
                    $current .= $pattern[$i];
                } elseif ($pattern[$i] == ']') {
                    $open--;
                    $current .= $pattern[$i];
                    if ($open == 0) {
                        $parts[] = $current;
                        $current = '';
                    }
                } else {
                    $current .= $pattern[$i];
                }
            }
            if ($current) {
                $parts[] = $current;
            }

            $regex = '';
            $names = array();
            foreach($parts as $part) {
                if (preg_match('#\[([a-z0-9]*):(.*)\]#', $part, $matches)) {
                    $names[] = $matches[1];
                    /* Replace friendly names with regex patterns */
                    $part = '(' . str_replace(
                            array('integer', 'string',      'phrase'),
                            array('[0-9]+',  '[a-z0-9-_]+', '[0-9a-z-_\s]+' ),
                             $matches[2]) . ')';
                } elseif (preg_match('#\[(.*)\]#', $part, $matches)) {
                    $names[] = '';
                    $part = '(' . str_replace(
                            array('integer', 'string',      'phrase'),
                            array('[0-9]+',  '[a-z0-9-_]+', '[0-9a-z-_\s]+' ),
                             $matches[1]) . ')';
                }

                $regex .= $part ;
            }
            $regex = '#^' . trim($regex, '/') . '#';

            if (!preg_match($regex, $uri, $matches)) {
                /* Didn't match, try next one */
                continue;
            }

            /* Merge all the named sections into the $_GET array */
            array_shift($matches);
            foreach ($names as $id => $name) {
                if (!$name) {
                    continue;
                }
                $_GET[$name] = isset($matches[$id]) ? $matches[$id] : '';
            }

            /* Find the page in the database */
            $query = 'SELECT pageid, pg_url FROM {page} WHERE pg_link = ?';
            $values = array($uriPattern['class']);
            $res = Jojo::selectQuery($query, $values);
            if (isset($res[0]['pageid']) && count($res) == 1 ) {
                if ($getall) {
                    $allmatches[] =  $res[0]['pageid'];
                } else {
                    return $res[0]['pageid'];
                }
            } else {
               $root = $mldata['roots'][$section];
                foreach ($res as $k=>$r){
                    /* push any pages not from this section to the end of the array */
                    if ($root != Jojo::getSectionRoot($r['pageid']) ) {
                        unset($res[$k]);
                        array_push($res, $r);
                    }
                }
                preg_match('#([a-z0-9-_/]*)\/#', $uri, $matches);
                $pageid ='';
                foreach ($res as $r){
                    if ($r['pg_url'] == $matches[1]) {
                        $pageid = $r['pageid'];
                        break;
                    }
                }
                $page1 = array_shift($res);
                $pageid = !$pageid ? $page1['pageid'] : $pageid;
                if ($getall) {
                    $allmatches[] = $pageid;
                } else {
                    return $pageid;
                }
            }
        }

        /* Match "34/name-of-page/" for page id 34 */
        preg_match_all('%^([0-9]+)/([^/]+)/?$%', $uri, $matches);
        if (isset($matches[1][0])) {
            if ($getall) {
                $allmatches[] = $matches[1][0];
            } else {
                return $matches[1][0];
            }
        }

        /* Lookup  by uri */
        $query = 'SELECT pageid FROM {page} WHERE pg_url = ? ORDER BY pageid DESC';
        /* convert admin URIs (if the Admin section is not set to the default of /admin/) */
        $uri = Jojo::getAdminUriReverse($uri);
        $values = array($uri);
        $res = Jojo::selectQuery($query, $values);
        $root = isset($mldata['roots'][$section]) ? $mldata['roots'][$section] : 0;
        foreach ($res as $k=>$r){
            /* push any pages not from this section to the end of the array */
            if ($root != Jojo::getSectionRoot($r['pageid']) ) {
                unset($res[$k]);
                array_push($res, $r);
            }
        }
        $page1 = array_shift($res);
        if (isset($page1['pageid'])) {
            if ($getall) {
                $allmatches[] = $page1['pageid'];
            } else {
                return $page1['pageid'];
            }
        }

        /**
         * If last section is only digits, remove it.
         * eg: "admin/edit/tablename/123"
         * becomes: "admin/edit/tablename"
         * then lookup by uri again
         */
        $uriParts = explode('/', $uri);
        $lastPart = $uriParts[count($uriParts) - 1];
        if (preg_match('%^([0-9]+)$%', $lastPart)) {
            $uri = substr($uri, 0, strlen($uri) - strlen($lastPart) - 1);
            $values[0] = $uri;
            $res = Jojo::selectQuery($query, $values);
            if (isset($res[0]['pageid'])) {
                if ($getall) {
                    $allmatches[] = $res[0]['pageid'];
                } else {
                    return $res[0]['pageid'];
                }
            }
        }
        if ($getall) {
            if (!count($allmatches)) $allmatches[] = false;
            return $allmatches;
        } else {
            return false;
        }

    }

    /* convert admin URIs (if the Admin section is not set to the default of /admin/) */
    static function getAdminUri($uri)
    {
        if (_ADMIN == 'admin')
            return $uri;

        if ($uri == 'admin') {
            $uri = _ADMIN;
        } else {
            $uri = preg_replace('%(admin/)(.*)%', _ADMIN.'/$2', $uri);
        }

        return $uri;
    }

    static function getAdminUriReverse($uri)
    {
        if (_ADMIN == 'admin')
            return $uri;

        if ($uri == _ADMIN) {
            $uri = 'admin';
        } else {
            $uri = preg_replace('%('._ADMIN.'/)(.*)%', 'admin/$2', $uri);
        }

        return $uri;
    }

    /**
     * Return a random string of $length charcters made up of $characters
     */
    static function randomString($length = 16, $characters = null)
    {
        if (is_null($characters)) {
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        $res = '';
        $cMax = strlen($characters) - 1;
        while (strlen($res) < $length) {
            $res .= $characters[mt_rand(0, $cMax)];
        }
        return $res;
    }

    /**
     * Convert to date sting into a unix timestamp.
     * Assumes Kiwi date format first, dd/mm/yyyy
     * Then reverts to build in strtotime()
     */
    static function strToTimeUK($normaldate)
    {
        if (preg_match('/[0-9]+\/[0-9]+\/[0-9]+/', $normaldate)) {
            $split = explode('/', $normaldate);
            $usdate = $split[1] . "/" . $split[0] . "/" . $split[2];
        } else {
            $usdate = $normaldate;
        }
        if (empty($usdate)) {return false;}
        if (($timestamp = strtotime($usdate)) === -1) {
            return false; //timestamp is not a valid format
        } else {
            return $timestamp;
        }
    }

    /**
     * Convert a mysql date into a particular format
     */
    static function mysql2date($mysqldate, $format='short')
    {
        if (($mysqldate == 'NULL') || ($mysqldate == NULL) || ($mysqldate == '0000-00-00') || empty($mysqldate) || ($mysqldate == '0') || ($mysqldate == '')) {
            return '';
        }
        $timestamp = Jojo::strToTimeUK($mysqldate);

        if (!$timestamp) {
            return '';
        } else {
            return Jojo::formatTimestamp($timestamp, $format);
        }
    }

    /**
     * Convert a timestamp into a particular format
     */
    static function formatTimestamp($timestamp, $format='short')
    {
        if (!$timestamp) {
            return "";
        } else {
            if ($format == 'rss') {
                //Wed, 02 Oct 2007 15:00:00 +0200 - useful for RSS feeds
                return date('D, d M Y H:i:s O', $timestamp);
            } elseif ($format == 'short') {
                // 2/10/2007
                return date('d/m/Y', $timestamp);
            } elseif ($format == 'medium') {
                return date('j M y', $timestamp);
            } elseif ($format == 'long') {
                return date('j F Y', $timestamp);
            } elseif ($format == 'vlong') {
                return date('D, j F Y', $timestamp);
            } elseif ($format == 'array') {
                $d = strtotime($mysql);
                return getdate($d);
            } elseif ($format == 'friendly') { //Friendly will use names such as today, yesterday etc
                if (date('d/m/Y', strtotime('+0 day')) == date('d/m/Y', $timestamp)) {
                    $d = 'today, ' . date("j M y", $timestamp);
                } else if (date("d/m/Y", strtotime('+1 day')) == date('d/m/Y', $timestamp)) {
                    $d = 'tomorrow, ' . date('j M y', $timestamp);
                } else if (date('d/m/Y', strtotime('-1 day')) == date('d/m/Y', $timestamp)) {
                    $d = "yesterday, ". date('j M y', $timestamp);
                } else {
                    $d = date('j M y', $timestamp);
                }
                return $d;
            }
        }
    }

    /* Adds http:// to a url if it's not already there */
    static function addhttp($url)
    {
        $txt = substr(strtolower($url), 0, 7);
        if ( ($txt != "http://") and ($txt != "https:/") ) {
            $url = "http://" . $url;
        }
        return $url;
    }

    static function relative2Absolute($text, $base)
    {
        if (empty($base)) {
            return $text;
        }

        // base url needs trailing /
        if (substr($base, -1, 1) != "/") {
            $base .= "/";
        }

        // Replace links
        $pattern = "/<a([^>]*) href=\"(?!http|ftp|https|mailto)([^\"]*)\"/";
        $replace = "<a\${1} href=\"" . $base . "\${2}\"";
        $text = preg_replace($pattern, $replace, $text);

        // Replace images
        $pattern = "/<img([^>]*) src=\"(?!http|ftp|https)([^\"]*)\"/";
        $replace = "<img\${1} src=\"" . $base . "\${2}\"";
        $text = preg_replace($pattern, $replace, $text);

        // Done
        return $text;
    }

    /* a case insensitive version of EXPLODE */
    static function iExplode($Delimiter, $String, $Limit = '')
    {
        $Explode = array();
        $LastIni = 0;
        $Count   = 1;

        if (is_numeric($Limit) == false)
            $Limit = '';

        while ( false !== ( $Ini = stripos($String, $Delimiter, $LastIni) ) && ($Count < $Limit || $Limit == ''))
            {
            $Explode[] = substr($String, $LastIni, $Ini-$LastIni);
            $LastIni = $Ini+strlen($Delimiter);
            $Count++;
            }

        $Explode[] = substr($String, $LastIni);
        return $Explode;
    }

    /* Performs a redirect, 301 by default */
    static function redirect($url, $type=301)
    {
        /* TODO: make relative URLs absolute */
        //$url = preg_replace('/(?!http|ftp|https)([^\"]*)/i', _SITEURL.'/$1', $url);
        if ($type == 301) header("HTTP/1.1 301 Moved Permanently");
        header("Location: $url");
        echo 'This page has moved to <a href="'.$url.'">'.$url.'</a>';
        exit();
    }

    /* 302 Redirects the user back to where they came from, or the homepage if they came via an external link */
    public static function redirectBack($location=false)
    {
        if (!empty($location)) Jojo::redirect($location);

        $redirect = Jojo::getFormData('redirect', false);

        /* redirect them to 1. The location specified in the function argument (if set) 2. The location specified in GET. 3. The HTTP_REFERER if an internal URL. 4. The homepage. */
        if (!$redirect) {
            /* is the referer the login page? don't want an infinite loop */
            if (trim($_SERVER['HTTP_REFERER'], '/') == trim(_SITEURL.'/'._SITEURI, '/')) {
                $redirect = '';
            /* is the referer an internal URL? */
            } elseif (preg_match('%^'.str_replace('.', '\\.', _SITEURL).'/(.*)$%im', $_SERVER['HTTP_REFERER'])) {
                $redirect = preg_replace('%^'.str_replace('.', '\\.', _SITEURL).'/(.*)$%im', '$1', $_SERVER['HTTP_REFERER']);
            /* external or malformed referers */
            } else {
                $redirect = '';
            }
        }

        Jojo::redirect(_SITEURL.'/'.$redirect);
    }

    /* regex to check URL format
     * TODO: test this bad boy, and hack it to work on HTTPS / FTP / IP addresses
     */
    static function checkUrlFormat($url)
    {
        return preg_match('#^http\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i', $url);
    }

    /* Checks that an email address looks valid
     * TODO: Get the function to check that the email domain is valid, and online
     */
    static function checkEmailFormat($email)
    {
      //  return eregi("^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$", $email);
      //  return preg_match("#^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]+$#i", $email);
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /* Gets the IP address of the visitor, bypassing proxies */
    static function getIp()
    {
        $ip = false;
        if ( (getenv('HTTP_X_FORWARDED_FOR') != '') && (strtolower(getenv('HTTP_X_FORWARDED_FOR')) != 'unknown')) {
            $iparray = explode(',', getenv('HTTP_X_FORWARDED_FOR'));
            $ip = $iparray[0];
        } elseif (getenv('REMOTE_ADDR') != '') {
            $ip = getenv('REMOTE_ADDR');
        }
        /* check IP is valid format */
        if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/', $ip)) {
        	return $ip;
        }
        return false;
    }

    /* reads the user agent string and gives the browser type - quick and simple detection */
    static function getBrowser()
    {
        static $_browser;

        if (isset($_browser)) return $_browser;

        $version = '';
        $nav = '';
        $browsers = 'mozilla msie gecko firefox konqueror safari netscape navigator opera mosaic lynx amaya omniweb snoopy chrome';
        $browsers = explode(' ', $browsers);

        $nua = isset($_SERVER['HTTP_USER_AGENT']) ? strToLower( $_SERVER['HTTP_USER_AGENT']) : '';

        $l = strlen($nua);
        $x = count($browsers);
        for ($i=0; $i<$x; $i++) {
            $browser = $browsers[$i];
            $n = stristr($nua, $browser);
            if (strlen($n) > 0) {
                $version = '';
                $nav = $browser;
                $j = strpos($nua, $nav) + $n + strlen($nav) + 1;
                for (; $j<=$l; $j++){
                    $s = substr($nua, $j, 1);
                    if (is_numeric($version.$s)) {
                        $version .= $s;
                    } else {
                        break;
                    }
                }
            }
        }
        if ($nav == 'msie') $nav = 'internet explorer';
        $_browser = ucwords($nav . ' ' . $version);
        return $_browser;
    }

    /* Given a string of page content, this script will return a short list of words to use in the
     * META KEYWORDS tag on your page. It does this by removing known "noisewords" from your content,
     * and returns a list of the first "content" words from your page, ensuring there are no duplicates.
     * Many SEO's believe that Google will penalise you for including words in your meta tags that can't
     * be found on the page. This function ensures that all meta keywords are available in the page content,
     * thus reducing the likelihood of the page being viewed as spam.
     * Note that words are space seperated, not comma seperated. This will increase the chance of the Search
     * Engines picking up keyword phrases, not just individual keywords.
     * This version assumes the first words in the content are the most relevant, it does not count the
     * number of times a word occurs on a page (the next version might do this).
     * This function is not going to produce results that are as good as hand crafted meta tags, but it is
     * certainly better than using NO meta keywords, or using the same keywords site-wide.
     * Also, don't expect that adding META Keywords to a page will make a huge difference to your SEO efforts.
     * This script should be used in conjunction with the many other optimization techniques available, such as
     * optimised title tags, keyword-rich URLs and content, validated markup and a decent backlink campaign.
     *
     *
     * Usage: Assuming your content is in a variable called $content...
     * require_once('keywords.function.php');
     * echo '<meta name="keywords" content="'.getMetaKeywords($content,20).'" />';
     *
     * If your content has already been outputted to the screen, you might want to look at using Output Buffering.
     * http://nz.php.net/manual/en/function.ob-start.php
     *
     *
     **/
    static function getMetaKeywords($content,$maxLength=30)
    {
        //Noisewords - an array of words we don't want to appear in our output.
        $noise = array(
                      'to',
                      'a',
                      'about',
                      'after',
                      'all',
                      'am',
                      'an',
                      'and',
                      'any',
                      'are',
                      'as',
                      'at',
                      'be',
                      'but',
                      'by',
                      'can',
                      'do',
                      'does',
                      'for',
                      'from',
                      'has',
                      'have',
                      'he',
                      'her',
                      'here',
                      'him',
                      'his',
                      'how',
                      'if',
                      'in',
                      'is',
                      'it',
                      'me',
                      'my',
                      'no',
                      'not',
                      'of',
                      'on',
                      'or',
                      'she',
                      'so',
                      'that',
                      'the',
                      'their',
                      'then',
                      'there',
                      'this',
                      'was',
                      'we',
                      'what',
                      'whats',
                      'when',
                      'which',
                      'will',
                      'with',
                      'would',
                      'you',
                      'your',
                      'well',
                      'into',
                      'also',
                      'now',
                      'its',
                      'get',
                      'need',
                      'worth',
                      'up',
                      'down',
                      'see',
                      'over'
                      );

        /* Make string lowercase and shorten */
        $content = strtolower(substr($content, 0, 500));

        //Remove any HTML tags (this code copied from www.php.net)
        $search = array ('@<script[^>]*?'.'>.*?</script>@si', // Strip out javascript
                     '@<[\/\!]*?[^<>]*?'.'>@si',              // Strip out HTML tags
                     '@([\r\n])[\s]+@',                       // Strip out white space
                     '@&(quot|#34);@i',                       // Replace HTML entities
                     '@&(amp|#38);@i',
                     '@&(lt|#60);@i',
                     '@&(gt|#62);@i',
                     '@&(nbsp|#160);@i',
                     '@&(iexcl|#161);@i',
                     '@&(cent|#162);@i',
                     '@&(pound|#163);@i',
                     '@&(copy|#169);@i',
                     '@&#(\d+);@e');                          // evaluate as php

        $replace = array ('',
                     '',
                     '\1',
                     '"',
                     '&',
                     '<',
                     '>',
                     ' ',
                     chr(161),
                     chr(162),
                     chr(163),
                     chr(169),
                     'chr(\1)');

        $content = preg_replace($search, $replace, $content);

        /* Remove newline characters */
        $remove = array("\r", "\n", ' ');
        $content = str_replace($remove, ' ', $content);

        /* Remove any special characters */
        $content = preg_replace ('/[^[:space:]a-zA-Z0-9*_-]/', '', $content);

        /* $keywords is an array of our approved keywords */
        $keywords = array();

        $contentArray = explode(' ',$content,($maxLength*5)); //Assume 4 out of 5 words are valid

        for ($i=0;$i<count($contentArray);$i++) {
            if (preg_match('/^\\d+$/si', $contentArray[$i])) unset($contentArray[$i]);
        }

        $i = 0;
        $n = count($contentArray);
        while ((count($keywords) < $maxLength) && ($i < $n)) {
            /* Do not allow duplicates in our keywords array (may be considered spam) */
            if (isset($contentArray[$i]) && !in_array($contentArray[$i], $noise) && !in_array($contentArray[$i], $keywords)) {
                $keywords[]=$contentArray[$i];
            }
            $i++;
        }

        /* Returns space seperated string. Use in the following format... <meta name="keywords" content="$keywords" /> */
        return implode(' ',$keywords);
    }
    /* clearing the full argument also wipes Smarty templates_c and the cached api / listplugins / listthemes files */
    static function clearCache($full = false) {
        Jojo::deleteQuery("DELETE FROM {contentcache}");
        if ($full) {
            if (Jojo::fileExists(_CACHEDIR . '/api.txt')) {
                unlink(_CACHEDIR.'/api.txt');
            }
            if (Jojo::fileExists(_CACHEDIR . '/listPlugins.txt')) {
                unlink(_CACHEDIR.'/listPlugins.txt');
            }
            if (Jojo::fileExists(_CACHEDIR . '/listThemes.txt')) {
                unlink(_CACHEDIR.'/listThemes.txt');
            }
            $files = scandir(_CACHEDIR . '/smarty/templates_c');
            foreach ($files as $file) {
                if (preg_match('/^%%.*%%.*\\.tpl\\.php$/i', $file)) {
                    unlink(_CACHEDIR . '/smarty/templates_c/' . $file);
                }
            }

        }
        return true;
    }

    static function html2text($html)
    {
        return Text_Filter::filter($html, 'html2text');
    }

    /**
     * Compares the current host with the list of localhosts as defined in dev_domains list. Returns true if a match is found.
     */
    static function isLocalServer($domain=false)
    {
        if (!$domain) $domain = _PROTOCOL.$_SERVER['HTTP_HOST'];
        $localservers = Jojo::getOption('dev_domains');
        if (empty($localservers)) return false;

        $localservers = explode("\n", $localservers);
        foreach ($localservers as $localserver) {
            $localserver = trim($localserver);
            if ($domain == $localserver) return true;
        }
        return false;
    }

    static function simpleMail($toname, $toaddress, $subject, $message, $fromname=_FROMNAME, $fromaddress=_FROMADDRESS, $htmlmessage=false, $senderaddress=false)
    {
        //Protect against email injection
        $badStrings = array("Content-Type:",
                         "MIME-Version:",
                         "Content-Transfer-Encoding:",
                         "bcc:",
                         "cc:",
                         "%0A");

        foreach($badStrings as $v){
            if ( (strpos($fromname, $v) !== false) || (strpos($fromname, $v) !== false) ){
                header('location: http://en.wikipedia.org/wiki/Email_injection');
                exit;
            }
            if ( (strpos($fromaddress, $v) !== false) || (strpos($fromaddress, $v) !== false) ){
                header('location: http://en.wikipedia.org/wiki/Email_injection');
                exit;
            }
        }

        /* Log the message */
        if (!class_exists('Jojo_Eventlog')) require_once(_BASEDIR.'/plugins/jojo_core/classes/Jojo/Eventlog.php');
        $log             = new Jojo_Eventlog();
        $log->code       = 'Sendmail';
        $log->importance = 'normal';
        $log->shortdesc  = 'To:'.$toaddress.' - '.$subject;
        $log->desc       = "To: $toname <$toaddress>\nFrom: $fromname <$fromaddress>\nSubject: $subject\nMessage:\n$message";
        $log->savetodb();
        unset($log);

        $smtp = Jojo::getOption('smtp_mail_enabled', 'no');
        if ($smtp == 'yes') {
            $host = Jojo::getOption('smtp_mail_host', 'http://localhost');
            $port = Jojo::getOption('smtp_mail_port', 25);
            $user = Jojo::getOption('smtp_mail_user', '');
            $pass = Jojo::getOption('smtp_mail_pass', '');

            foreach (Jojo::listPlugins('external/mimemail/htmlMimeMail.php') as $pluginfile) {
                require_once($pluginfile);
                break;
            }
            $mail = new htmlMimeMail();
            if (!$htmlmessage) {
            	$mail->setText($message);
            } else {
            	$mail->setHtml($htmlmessage, $message);
            }
            if (!empty($user)) {
                $mail->setSMTPParams($host, $port, _SITEURL, true, $user, $pass);
            } else {
                $mail->setSMTPParams($host, $port, _SITEURL);
            }
            $mail->setFrom('"'.'=?UTF-8?B?'.base64_encode($fromname).'?='.'" <'.$fromaddress.'>');
            $mail->setSubject('=?UTF-8?B?'.base64_encode($subject).'?=');
            $mail->setTextCharset('UTF-8');

            $result = $mail->send(array($toaddress), 'smtp');
            return $result;
        } else {
            # Setup mime boundary
            $mime_boundary = 'Multipart_Boundary_x'.md5(time()).'x';

            $headers  = "MIME-Version: 1.0\n";
            $headers .= $htmlmessage ? "Content-Type: multipart/alternative; boundary=\"$mime_boundary\"\r\n" : "Content-Type: text/plain;charset=\"UTF-8\"\n";
            $headers .= "Content-Transfer-Encoding: 7bit\r\n";
            $headers .= "X-Priority: 3\n";
            $headers .= "X-MSMail-Priority: Normal\n";
            $headers .= "X-Mailer: php\n";
            $headers .= $senderaddress ? "From: " . $senderaddress . "\n" : "From: \"" . "=?UTF-8?B?".base64_encode($fromname)."?=" . "\" <" . $fromaddress . ">\n";
            $headers .= $senderaddress ? "Reply-To: \"" . "=?UTF-8?B?".base64_encode($fromname)."?=" . "\" <" . $fromaddress . ">\n" : '';
            $additional="-f$fromaddress";
            $to = (strpos($toname, '@') || empty($toname)) ? $toaddress : $toname . ' <' . $toaddress. '>';
            if ($htmlmessage) {
                $body = '';
                # Add in plain text version
                $body.= "--$mime_boundary\n";
                $body.= "Content-Type: text/plain; charset=\"charset=us-ascii\"\n";
                $body.= "Content-Transfer-Encoding: 7bit\n\n";
                $body.= $message;
                $body.= "\n\n";

                # Add in HTML version
                $body.= "--$mime_boundary\n";
                $body.= "Content-Type: text/html; charset=\"UTF-8\"\n";
                $body.= "Content-Transfer-Encoding: 7bit\n\n";
                $body.= $htmlmessage;
                $body.= "\n\n";

                # End email
                $body.= "--$mime_boundary--\n"; # <-- Notice trailing --, required to close email body for mime's
                $message = $body;
            }
            return mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $message, $headers, $additional);
        }
    }

    /////////////////////////USINGSSLCONNECTION////////////////////////////////////////////
    //Determine if we are using a secure (SSL) connection.
    //@return boolean  True if using SSL, false if not.
    //Taken from Horde Browser class
    static function usingSSLConnection()
    {
        return ((isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on')) ||
        getenv('SSL_PROTOCOL_VERSION'));
    }

    /* PHP4 version of scandir */
    static public function scanDirectory($dir = './', $sort = 0)
    {
        $files = array();
        if (!file_exists($dir)) return false;
        $dir_open = @ opendir($dir);
        if (!$dir_open) return false;

        while (($dir_content = readdir($dir_open)) !== false) {
            if ( ($dir_content != '.') && ($dir_content != '..') && ($dir_content != '.svn') ) {$files[] = $dir_content;}
        }
        if ($sort == 1) rsort($files, SORT_STRING);
        else sort($files, SORT_STRING);

        return $files;
    }

    /////////////////////////Jojo::relativeDate////////////////////////////////////////////
    /* Date formatting routine to give an easy to understand date to the user, giving only as much info as is needed
    Coming Week = This Wednesday, 8:30am
    Tomorrow = Tomorrow, 8:30am
    Today (afternoon) = Today, 8:30am
    Today (morning) = Today, 8:30 (no need to specify am or pm)
    Yesterday = Yesterday, 8:30am
    The last week = Last Monday, 8:30am
    In the last month = Monday, Dec 12
    In the same calendar year = Feb 23
    Last year or prior = May 16, 2004
    The full date / time should be given on the rollover, outside the scope of this function
    */
    public static function relativeDate($timestamp, $showtime=true) {
        if ( is_null($timestamp) || ($timestamp == 0) ) {return '';}
        if (date('d/m/Y',strtotime('+0 day')) == date('d/m/Y',$timestamp)) {
            $d = $showtime ? 'Today, '.date("h:ia",$timestamp) : 'Today';
        } else if (date("d/m/Y",strtotime("+1 day")) == date("d/m/Y",$timestamp)) {
            $d = $showtime ? "Tomorrow, ".date("h:ia",$timestamp) : 'Tomorrow';
        } else if (date("d/m/Y",strtotime("-1 day")) == date("d/m/Y",$timestamp)) {
            $d = $showtime ? "Yesterday, ".date("h:ia",$timestamp) : 'Yesterday';
        } else if (date("Y",strtotime("+0 day")) == date("Y",$timestamp)) { //same year
            $d = "".date("jS F",$timestamp); //no need to include year
        } else {
            $d = date("j M Y",$timestamp);
        }
        return $d;
        //return $timestamp;
    }

    /**
     * Auto load a class file if we know where to find it
     *
     */
    public static function autoload($classname)
    {
        if (strpos(strtolower($classname), 'jojo_') === 0) {
            $parts = explode(' ', ucwords(str_replace('_', ' ', $classname)));
            array_shift($parts);

            /* Search for the file in a theme first and include it if we find it */
            $filename =  'classes/Jojo/' . implode('/', $parts) . '.php';
            $pluginFiles = Jojo::listThemes($filename);
            foreach($pluginFiles as $file) {
                require_once($file);
                if (class_exists($classname)) {
                    return;
                }
            }

            /* Search for the file in plugins and include it if we find it */
            $pluginFiles = Jojo::listPlugins($filename);
            foreach($pluginFiles as $file) {
                require_once($file);
                if (class_exists($classname)) {
                    return;
                }
            }

            /* See if it's a core class */
            $filename = _BASEPLUGINDIR . '/jojo_core/' . $filename;
            if (file_exists($filename)) {
                require_once($filename);
                if (class_exists($classname)) {
                    return;
                }
            }
        }

        if (strpos(strtolower($classname), 'jojo_plugin_') === 0) {
            /* Is a plugin class, get filename */
            $filename = str_replace('jojo_plugin_', '',  strtolower($classname)) . '.php';

            /* Search for the file in a theme first and include it if we find it */
            $pluginFiles = Jojo::listThemes($filename);
            foreach($pluginFiles as $file) {
                require_once($file);
                if (class_exists($classname)) {
                    return;
                }
            }

            /* Search for the file in plugins and include it if we find it */
            $pluginFiles = Jojo::listPlugins($filename);
            foreach($pluginFiles as $file) {
                require_once($file);
                if (class_exists($classname)) {
                    return;
                }
            }
            return;
        }

        /* Support for plugins to immitate "classes/Path/Classname.php" structure that the core uses */
        $filename = 'classes/'.str_replace('_', '/', $classname).'.php';
        $pluginFiles = Jojo::listThemes($filename);
        foreach($pluginFiles as $file) {
            require_once($file);
            if (class_exists($classname)) {
                return;
            }
        }
        /* Search for the file in plugins and include it if we find it */
        $pluginFiles = Jojo::listPlugins($filename);
        foreach($pluginFiles as $file) {
            require_once($file);
            if (class_exists($classname)) {
                return;
            }
        }

        $custom = array(
            'text_filter' => _BASEPLUGINDIR . '/jojo_core/external/Horde/Filter.php',
            'browser'     => _BASEPLUGINDIR . '/jojo_core/external/Horde/Browser.php',
            'string'      => _BASEPLUGINDIR . '/jojo_core/external/Horde/String.php',
            'util'        => _BASEPLUGINDIR . '/jojo_core/external/Horde/Util.php',
            'phpcaptcha'  => _BASEPLUGINDIR . '/jojo_core/external/php-captcha/php-captcha.inc.php',
            'htmlmimemail'=> _BASEPLUGINDIR . '/jojo_core/external/mimemail/htmlMimeMail.php',
            'hktree'      => _BASEPLUGINDIR . '/jojo_core/external/hktree/hktree.class.php',
            'bbconverter' => _BASEPLUGINDIR . '/jojo_core/external/bbconverter/bbconverter.class.php',
            );

        if (isset($custom[strtolower($classname)])) {
            require_once($custom[strtolower($classname)]);
            if (class_exists($classname)) {
                return;
            }
        }
    }

    /**
     * Handle/Log php errors appropriately
     */
    public static function errorHandler($errno, $errstr, $errfile = null, $errline = null)
    {
        /* Ignore errors from smarty */
        if (strpos($errfile, 'smarty')) {
            return;
        }

        if (!defined('_DEBUG') || !_DEBUG) {
            set_error_handler(array('Jojo', 'errorHandler'), E_ALL);
        }

        if (!_DEBUG) {
            /* Create message to log */
            $message = "Error No: $errno\nDescription: $errstr\nFile: $errfile\nLine: $errline\n";

            /* Log the message */
            if (!class_exists('Jojo_Eventlog')) require_once(_BASEDIR.'/plugins/jojo_core/classes/Jojo/Eventlog.php');
            $log = new Jojo_Eventlog();
            $log->code = 'PHP Error';
            $log->importance = 'high';
            $log->shortdesc = !empty($errfile) ? $errfile.' "'.$errstr.'" line '.$errline : $errstr;
            $log->desc = $message;
            $log->savetodb();
            unset($log);
        }

        /* Don't execute PHP internal error handler if not debug mode */
        return !_DEBUG;
    }

    /**
     * Protects against POST form injection by...
     * - ensuring the form is POSTed and the request is from the current website
     * - Stripping evil strings such as "Content-Type:" and "MIME-Version:"
     * - ensuring the useragent is set - very basic protection against non-browser apps
     *
     * If attempts are found, redirect out of here.
     */
    public static function noFormInjection()
    {
        /* First, make sure the form was posted from a browser.
           For basic web-forms, we don't care about anything other than requests from a browser:    */
        if(!isset($_SERVER['HTTP_USER_AGENT'])){
             die("Forbidden - You are not authorized to view this page");
             exit;
        }

        /* Make sure the form was indeed POST'ed: (requires your html form to use: action="post") */
        if(!$_SERVER['REQUEST_METHOD'] == "POST"){
             die("Forbidden - You are not authorized to view this page");
             exit;
        }

        /*
        //This section checks to ensure the visitor came from our site, either http or https. This code is
        commented because the parse_url returns the URL without the http:// whereas the _SITEURL includes this.
        Not a big deal to fix really.

        // Host names from where the form is authorized
        // to be posted from:
        $authHosts = array(_SITEURL, _SECUREURL);

        // Where have we been posted from?
        $fromArray = parse_url(strtolower($_SERVER['HTTP_REFERER']));

        // Test to see if the $fromArray used www to get here.
        $wwwUsed = strpos($fromArray['host'], "www.");

        // Make sure the form was posted from an approved host name.
        if(!in_array(($wwwUsed === false ? $fromArray['host'] : substr(stristr($fromArray['host'], '.'), 1)), $authHosts)){
            //header("HTTP/1.0 403 Forbidden");
            die("Forbidden - You are not authorized to view this page");
            exit;
        }
        */

        /* Attempt to defend against header injections: */
        $badStrings = array("Content-Type:",
                             "MIME-Version:",
                             "Content-Transfer-Encoding:",
                             "X-Mailer",
                             "bcc:",
                             "cc:",
                             "%0A");

        /* Loop through each POST'ed value and test if it contains one of the $badStrings:*/
        foreach($_POST as $k => $v3){
        /* cast all post variables as arrays (to catch those that actually are)*/
            foreach((array)$v3 as $v) {
                foreach($badStrings as $v2){
                    if(strpos($v, $v2) !== false){
                        header('location: http://en.wikipedia.org/wiki/Email_injection');
                        exit;
                    }
                }
            }
        }

        /* Made it past spammer test, free up some memory and continue rest of script: */
        unset($k, $v, $v2, $v3, $badStrings, $authHosts, $fromArray, $wwwUsed);
    }

    public static function getFormData($var, $default = null)
    {
        return Util::getFormData($var, $default);
    }

    public static function getGet($var, $default = null)
    {
        return Util::getGet($var, $default);
    }

    public static function getPost($var, $default = null)
    {
        return Util::getPost($var, $default);
    }

    public static function bb2Html($bbcode, $options=array())
    {
        $bb = new bbconverter;
        $bb->setBbCode($bbcode);
        if (isset($options['nofollow']) && $options['nofollow']) $bb->nofollow = true;
        $html = $bb->convert('bbcode2html');
        return $html;
    }

    public static function markdown2Html($markdown)
    {
        require_once(_BASEPLUGINDIR.'/jojo_core/external/php-markdown/markdown.php');
        return Markdown($markdown);
    }

    /**
    Takes an email address user@domain.co.nz
    Splits into 3 parts
    $addressname = user (just the first part of the address)
    $addressdomain1 = niamod (the first part of the domain name reversed)
    $addressdomain2 = co.nz (the remaining part of the domain name, without the first dot)
    Final output looks like this...
    <a href="/contact" onmouseover="this.href=xyz('co.nz','user','niamod');">
    */
    public static function obfuscateEmail($address, $includemailto = true) {
        return bbConverter::obfuscateEmail($address, $includemailto);
    }

    public static function gzip()
    {
        static $_gzipped;

        /* Are we allready gzipping? */
        if (isset($_gzipped) || in_array('ob_gzhandler', ob_list_handlers())) {
            /* Yes, don't do it twice */
            return false;
        }

        $PREFER_DEFLATE = false; // prefer deflate over gzip when both are supported
        $AE = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : (isset($_SERVER['HTTP_TE']) ? $_SERVER['HTTP_TE'] : '');
        $support_gzip    = !(strpos($AE, 'gzip')    === false) && function_exists('gzencode');
        $support_deflate = !(strpos($AE, 'deflate') === false) && function_exists('gzdeflate');
        $support_deflate = ($support_gzip && $support_deflate) ? $PREFER_DEFLATE : $support_deflate;

        if ($support_deflate) {
            function compress_output_deflate($output) {
                $output = gzdeflate($output, 9);
                header('Content-Encoding: deflate');
                header('Content-Length: ' . strlen($output));
                return $output;
            }
            ob_start('compress_output_deflate');
            $_gzipped = true;
            return true;
        } elseif ($support_gzip) {
            function compress_output_gzip($output) {
                $output = gzencode($output);
                header('Content-Encoding: gzip');
                header('Content-Length: ' . strlen($output));
                return $output;
            }
            ob_start('compress_output_gzip');
            $_gzipped = true;
            return true;
        } else {
            ob_start();
        }
        return false;
    }

    static function makePassword($length=8)
    {
        $consts='bcdgklmnprstvwz';
        $vowels='aeiou';
        $digits = '1234567890';

        /* Select characters */
        $character = array();
        $i = 0;
        while ($i < $length) {
            mt_srand((double)microtime() * 1000000);
            $character[$i++] = substr($consts, mt_rand(0, strlen($consts)-1), 1);
            $character[$i++] = substr($vowels, mt_rand(0, strlen($vowels)-1), 1);
            if (mt_rand(0, 10) > 6) {
                $character[$i++] = substr($digits, mt_rand(0, strlen($digits)-1), 1);
            }
        }

        /* Put together into a 'word' */
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $character[$i];
        }

        return $password;
    }

    function makeLogin($first, $last)
    {
        $first = strtolower($first);
        $last = strtolower($last);
        /* try these usernames in order */
        $logins = array();
        $logins[] = $first; //john
        $logins[] = $first.$last; //johnsmith
        $logins[] = $first.$last[0]; //johns
        $logins[] = $first[0].$last; //jsmith
        $logins[] = $first.'.'.$last[0]; //john.s
        $logins[] = $first[0].'.'.$last; //j.smith
        $logins[] = $first.'.'.$last; //john.smith

        foreach ($logins as $login) {
            $user = Jojo::selectRow("SELECT userid FROM {user} WHERE us_login=? AND us_login !=''", $login);
            if (empty($user['userid'])) {
                return $login;
            }
        }
        /* all the above combinations are taken - try appending a number instead */
        for ($i=1; $i<100; $i++) { //if there are 100+ other users with the same name we may need to improve this function
            foreach ($logins as $login) {
                $user = Jojo::selectRow("SELECT userid FROM {user} WHERE us_login=?", $login.$i);
                if (empty($user['userid'])) {
                    return $login.$i;
                }
            }
        }
        return false;
    }

    /**
     * Retrieve the multilanguage data from the database
     */
    static function getMultiLanguageData()
    {
        // Modified 3 April 2009 by James Pluck, SearchMasters
        // Added check for new LanguageCountry functionlity to split country codes for pages
        // off from language codes for the page.
        static $mldata;

        if (!is_array($mldata)) {
            $mldata = array(
                            'roots' => array(),
                            'homes' => array(),
                            'longcodes' => array(),
                            'languagelist' => array()
                            );
            // get language codes from new table
            $res = Jojo::selectAssoc("SELECT lc_root as pageid, lc_code as languageid, lc_root as root, lc_home as home, lc_longcode as longcode, lc_name as name, lc_defaultlang, lc.* FROM {lang_country} lc WHERE active=1 ORDER BY displayorder");
            $default = !isset($res[0]['default']) ? Jojo::getOption('multilanguage-default') : '';
            foreach ($res as $k=>$r) {
                $mldata['roots'][$r['languageid']] = $r['root'];
                $mldata['homes'][$r['languageid']] = $r['home'];
                $mldata['longcodes'][$r['languageid']] = (!empty($r['longcode'])) ? $r['longcode'] : $r['languageid'];
                $mldata['names'][$r['languageid']] = !empty($r['name']) ? $r['name'] : $r['longcode'];
                $mldata['languages'][$r['languageid']] = !empty($r['lc_defaultlang']) ? $r['lc_defaultlang'] : 'en';
                if (isset($r['default']) && $r['default']) $default = $r['languageid'];
                if ($r['active']){
                    $res[$k]['url'] = $r['languageid']!=$default ? $r['languageid'] . '/' : '';
                } else {
                    unset($res[$k]);
                }
            }
            $mldata['default'] = $default;
            $mldata['sectiondata'] = $res;
        }
        return $mldata;
    }

    static function getPageUrlPrefix($pageid) {
        $thisprefix = '';
        $default=true;
        $thisroot = Jojo::getSectionRoot($pageid);
        $mldata = self::getMultiLanguageData();
        if (!isset($mldata['sectiondata'][$thisroot])) return '';
        $thisprefix = !$mldata['sectiondata'][$thisroot]['default'] ? $mldata['sectiondata'][$thisroot]['lc_code'] . '/' : '';
        return $thisprefix;
    }

    static function getSectionRoot($pageid) {
        $thispagetree = Jojo::getSelectedPages($pageid);
        $thisroot = $thispagetree ? $thispagetree[0] : 0;
        return $thisroot;
    }

    static function isSectionPage($pageid, $sectionroot=0) {
        $thisroot = Jojo::getSectionRoot($pageid);
        if ($thisroot==$sectionroot) return true;
        return false;
    }

    static function getMultiLanguageString ($language, $long = false, $defReturnStr = '') {
        $defaultLanguage = Jojo::getOption('multilanguage-default');
        if (!$long) {
            $languageHeader = ($defaultLanguage==$language) ? $defReturnStr : $language . '/';
        } else {
            // We want the long language codes
            $mldata = Jojo::getMultiLanguageData();
            $languageHeader = ($defaultLanguage==$language) ? $defReturnStr : $mldata['longcodes'][$language] . '/';
        }
        return $languageHeader;
    }

    static function getPageHtmlLanguage() {
        global $page, $sectiondata;
        $languagedata = array();
        // if the page had an html language set, use that
        if ($page->page['pg_htmllang']) {
            $pagehtmllang = $page->page['pg_htmllang'];
        // otherwise, find the default language for this section from its root
        } else if ($sectiondata) {
            $pagehtmllang = $sectiondata['lc_defaultlang'];
        } else {
            $pagehtmllang = 'en';
        }
        $languagedata = Jojo::selectRow("SELECT * FROM {language} WHERE languageid = ?", array($pagehtmllang));
        $languagedata['longlanguage']  = ($languagedata && $languagedata['name']) ? $languagedata['name'] : $pagehtmllang;
        $languagedata['charset'] = ($languagedata['charset']) ? $languagedata['charset'] : 'utf-8';
        $languagedata['ISOcode'] = isset($languagedata['ISOcode']) ? $languagedata['ISOcode'] : 'en_GB';
        $languagedata['direction'] = ($languagedata['direction']) ? $languagedata['direction'] : 'ltr';
        return $languagedata;
    }

    static function getNav($root, $subnavLevels, $field = 'mainnav')
    {
        global $_USERGROUPS, $selectedPages;

        /* Get multilanguage data */
            global $page;
            $mldata = Jojo::getMultiLanguageData();
            $home = isset($mldata['sectiondata'][$root]) ? $mldata['sectiondata'][$root]['home'] : 1;
            $roots = array_keys($mldata['sectiondata']);
            $multisection = (boolean)(count($roots)>1);

        /* Get pages from database */
        static $_cached;
        if (!isset($_cached[$field])) {
            $now    = time();
            // If pg_mainnavalways exists - and requested menu is mainnav - adjust query to include
            // those pages that are configured to appear in all main nav menus.
            if ($field == 'mainnav' && $multisection && Jojo::fieldExists ( 'page', 'pg_mainnavalways' )) {
                $query = sprintf("SELECT * FROM {page}
                             WHERE (pg_%s = 'yes' or pg_mainnavalways = 'yes')
                             ORDER BY pg_order", $field);
            } else {
                $query = sprintf("SELECT * FROM {page}
                             WHERE pg_%s = 'yes'
                             ORDER BY pg_order", $field);
            }
            $_cached[$field] = array();
            $result = Jojo::selectQuery($query);
            // strip out expired / forbidden pages
            $result = Jojo_Plugin_Core::cleanItems($result, 'nav');
            foreach ($result as $k => $row) {
                $r = $row['pg_parent'];
                if (!isset($_cached[$field][$r])) {
                    $_cached[$field][$r] = array();
                }
                $_cached[$field][$r][] = $row;
                if ($field=='mainnav' && $multisection && isset($row['pg_mainnavalways']) && $row['pg_mainnavalways']=='yes' && $r!=$root && (in_array($r, $mldata['roots']) || $r==1)) {
                    $_cached[$field][$root][] = $row;
                }
            }
        }
        $nav = isset($_cached[$field][$root]) ? $_cached[$field][$root] : array();

        foreach ($nav as $id => &$n) {
            /* Create the url for this page */
            $n['url'] = ($n['pg_ssl'] == 'yes' ? _SECUREURL : _SITEURL ) . '/' . Jojo::getPageUrlPrefix($n['pageid']);
            if ($n['pageid'] != $home && !in_array($n['pageid'], $roots)) {
                /* Use page url is we have it, else generate something */
                $n['url'] .= ($n['pg_url'] ? $n['pg_url'] : $n['pageid'] . '/' . Jojo::cleanURL($n['pg_title'])) . '/';
            }
            /* Create title and label for display */
            $n['title'] = htmlspecialchars(($n['pg_desc'] ? $n['pg_desc'] : ($n['pg_seotitle'] ? $n['pg_seotitle'] : $n['pg_title'])), ENT_COMPAT, 'UTF-8', false);
            $n['label'] = htmlspecialchars(($n['pg_menutitle'] ? $n['pg_menutitle'] : $n['pg_title']), ENT_COMPAT, 'UTF-8', false);
            /* Add field for selectedPages tree */
            $n['selected'] = (boolean)($selectedPages && in_array($n['pageid'], $selectedPages));
            if ($subnavLevels) {
                /* Add sub pages to this page */
                $n['subnav'] = Jojo::getNav($n['pageid'], $subnavLevels - 1, $field);
                $plugin = $n['pg_link'];
                 if ($plugin && class_exists($plugin) && method_exists($plugin, 'getNavItems')) {
                    $pluginsubnav = call_user_func($plugin . '::getNavItems', $n['pageid'], $n['selected']);
                    $n['subnav'] = array_merge($pluginsubnav, $n['subnav']);
                }
            }
        }
        return $nav;
    }

    /* Get currently selected page and step back up through parents to build a current section/sub pages array */
    static function getSelectedPages($pageid, $root=0) {
        if (!$pageid) {
            return array();
        }
        $mldata = Jojo::getMultiLanguageData();

        /* Cache the page parents */
        static $_pageParent;
        if (!is_array($_pageParent)) {
           $query = "SELECT pageid, pg_parent
                       FROM {page}";
           $_pageParent = Jojo::selectAssoc($query);
        }

        /* Start with the current page */
        $selectedPages = array($pageid);
        $depth = 0;

        while ( !in_array($selectedPages[0], $mldata['roots']) && ($selectedPages[0] != 0) && ($depth < 10)) {
           /* Find the parent of this iteration's top page */
           if (!isset($_pageParent[$selectedPages[0]])) {
               return $selectedPages;
           }
           $pg_parent = $_pageParent[$selectedPages[0]];

           /* Add new parent to top of array and move others down */
           array_unshift($selectedPages, $pg_parent);
           $depth ++;
        }
        return $selectedPages;
    }

    /*
     * This function prevents content from being cached, regardless of other settings within Jojo.
     * Whenever code is executed that outputs content that should not be cached, run Jojo::noCache(true);
     * to test, run Jojo::noCache() which will return true if $nocache has previously been set on this request
     */
    static function noCache($set=false)
    {
        static $nocache;
        if ($set) {
            $nocache = true;
        } elseif (!isset($nocache)) {
            $nocache = false;
        }
        return $nocache;
    }

    static function getFeed($items, $fields)
    {
        $pagetitle = $fields['pagetitle'];
        $pageurl = $fields['pageurl'];
        $titlefield = $fields['title'];
        $authorfield = isset($fields['author']) ?  $fields['author'] : '';
        $bodyfield = $fields['body'];
        $urlfield = $fields['url'];
        $datefield = $fields['date'];
        $datetype = $fields['datetype']; // mysql or unix timestamp
        $categoryfield = isset($fields['category']) ?  $fields['category'] : '';
        $options = isset($fields['options']) ? $fields['options'] : array();
        $snip =  $options ? $options['snip'] : (Jojo::getOption('rss_full_description') == 'yes' ? 'full' : Jojo::getOption('rss_truncate', 800)); // full or truncate length
        $image =  isset($fields['image']) && $options['imagesize'] ? $fields['image'] : '';
        $sourcelink = (boolean)(Jojo::getOption('rss_source_link') == 'yes'); // add source link to each item
        $site = mb_convert_encoding(_SITETITLE, 'HTML-ENTITIES', 'UTF-8');
        $pagetitle = mb_convert_encoding($pagetitle, 'HTML-ENTITIES', 'UTF-8');
        $description = mb_convert_encoding(Jojo::getOption('rss_sitedesc', Jojo::getOption('sitetitle')), 'HTML-ENTITIES', 'UTF-8');
        $rss  = '<?xml version="1.0" ?>' . "\n";
        $rss .= '<rss version="2.0">' . "\n";
        $rss .= "<channel>\n";
        $rss .= '<title>' . $site . ': ' . $pagetitle . "</title>\n";
        $rss .= '<description>' . $description . "</description>\n";
        $rss .= "<link>" . $pageurl . "</link>\n";
        $rss .= "<copyright>" . htmlentities(_SITETITLE) . " " . date('Y', strtotime('now')) . "</copyright>\n";

        foreach ($items as &$i) {
            $i['body'] = Jojo::relative2absolute($i[$bodyfield], _SITEURL);
            /* chop up to the first [[snip]] */
            if ($snip =='full') {
                $i['body'] = str_ireplace('[[snip]]','',$i['body']);
            } else {
                $irr = Jojo::iExplode('[[snip]]', $i['body']);
                if (count($irr) === 1 && is_numeric($snip)) {
                    $i['body'] = substr($mbody=wordwrap($i['body'], $snip, '$$'), 0, strpos($mbody,'$$')) . ' ...';
                } else {
                    $i['body'] = $irr[0];
                }
                $i['body'] = preg_replace('/\[\[.*?\]\]/', '',  $i['body']);
            }
            $source = _SITEURL . "/" . $i['url'];
            $i['body'] = mb_convert_encoding($i['body'], 'HTML-ENTITIES', 'UTF-8');
            $i['title'] = mb_convert_encoding($i[$titlefield], 'HTML-ENTITIES', 'UTF-8');
            $i['author'] = $authorfield ? mb_convert_encoding($i[$authorfield], 'HTML-ENTITIES', 'UTF-8') : '';
            $i['date'] = $datetype=='mysql' ? Jojo::mysql2date($i[$datefield], 'rss') :  Jojo::formatTimestamp($i[$datefield], 'rss');
            $i['category'] = $categoryfield ? mb_convert_encoding($i[$categoryfield], 'HTML-ENTITIES', 'UTF-8') : '';
            if ($image) {
                $i['imageurl'] = isset($i['image']) && $i['image'] ?  _SITEURL . "/images/" . ( isset($options['imagesize']) ? $options['imagesize'] : 'default') . "/" . $i['image'] : '';
                $i['imagedata'] = $i['imageurl'] ?  getimagesize(_DOWNLOADDIR . '/' . urldecode($i['image'])) : '';
                $i['imagedata']['size'] = $i['imagedata'] ?  filesize(_DOWNLOADDIR . '/' . urldecode($i['image'])) : '';
            }
            if ($sourcelink) $i['body'] .= '<p>Source: <a href="' . $source . '">' . $i['title'] . '</a></p>';
            $rss .= "<item>\n";
            $rss .= "<title>" . Jojo::xmlEscape($i['title']) . "</title>\n";
            $rss .= "<description>" . Jojo::xmlEscape($i['body']) . "</description>\n";
            $rss .= $i['author'] ? "<author>" . Jojo::xmlEscape($i['author']) . "</author>\n" : '';
            $rss .= $i['category'] ? "<category>" . Jojo::xmlEscape($i['category']) . "</category>\n" : '';
            if ($image) {
                $rss .= $i['imageurl'] && $i['imagedata'] ? '<enclosure url="' . $i['imageurl'] . '" length="' . $i['imagedata']['size'] . '" type="' . ( isset($i['imagedata']['mime']) ? $i['imagedata']['mime'] : 'image/jpeg' ) . '" />' . "\n" : '';
            }
            $rss .= "<link>". $source . "</link>\n";
            $rss .= '<source url="'. $pageurl . '">' . $pagetitle . "</source>\n";
            $rss .= "<pubDate>" . $i['date'] . "</pubDate>\n";
            $rss .= "</item>\n";
        }
        $rss .= "</channel>\n";
        $rss .= "</rss>\n";

        header('Content-type: application/rss+xml');
        echo $rss;
        exit;
    }

    static function xmlEscape($data, $specialchars=false) {
        $xmldata  = preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/', 'Jojo::convertEntity4XML', $data);
        $xmldata = !$specialchars ?  str_replace('<', '&lt;', str_replace('>', '&gt;', str_replace('"', '&quot;', str_replace('&', '&amp;', $xmldata)))) : str_replace('&amp;#', '&#', str_replace('&', '&amp;', $xmldata));
        return $xmldata;

    }

    /* Swap HTML named entity with its numeric equivalent. If the entity
     * isn't in the lookup table, this function returns a blank, which
     * destroys the character in the output - this is probably the
     * desired behaviour when producing XML. */
    static function convertEntity4XML($matches) {
      static $table = array('quot'    => '&#34;',
                            'amp'      => '&#38;',
                            'lt'       => '&#60;',
                            'gt'       => '&#62;',
                            'OElig'    => '&#338;',
                            'oelig'    => '&#339;',
                            'Scaron'   => '&#352;',
                            'scaron'   => '&#353;',
                            'Yuml'     => '&#376;',
                            'circ'     => '&#710;',
                            'tilde'    => '&#732;',
                            'ensp'     => '&#8194;',
                            'emsp'     => '&#8195;',
                            'thinsp'   => '&#8201;',
                            'zwnj'     => '&#8204;',
                            'zwj'      => '&#8205;',
                            'lrm'      => '&#8206;',
                            'rlm'      => '&#8207;',
                            'ndash'    => '&#8211;',
                            'mdash'    => '&#8212;',
                            'lsquo'    => '&#8216;',
                            'rsquo'    => '&#8217;',
                            'sbquo'    => '&#8218;',
                            'ldquo'    => '&#8220;',
                            'rdquo'    => '&#8221;',
                            'bdquo'    => '&#8222;',
                            'dagger'   => '&#8224;',
                            'Dagger'   => '&#8225;',
                            'permil'   => '&#8240;',
                            'lsaquo'   => '&#8249;',
                            'rsaquo'   => '&#8250;',
                            'euro'     => '&#8364;',
                            'fnof'     => '&#402;',
                            'Alpha'    => '&#913;',
                            'Beta'     => '&#914;',
                            'Gamma'    => '&#915;',
                            'Delta'    => '&#916;',
                            'Epsilon'  => '&#917;',
                            'Zeta'     => '&#918;',
                            'Eta'      => '&#919;',
                            'Theta'    => '&#920;',
                            'Iota'     => '&#921;',
                            'Kappa'    => '&#922;',
                            'Lambda'   => '&#923;',
                            'Mu'       => '&#924;',
                            'Nu'       => '&#925;',
                            'Xi'       => '&#926;',
                            'Omicron'  => '&#927;',
                            'Pi'       => '&#928;',
                            'Rho'      => '&#929;',
                            'Sigma'    => '&#931;',
                            'Tau'      => '&#932;',
                            'Upsilon'  => '&#933;',
                            'Phi'      => '&#934;',
                            'Chi'      => '&#935;',
                            'Psi'      => '&#936;',
                            'Omega'    => '&#937;',
                            'alpha'    => '&#945;',
                            'beta'     => '&#946;',
                            'gamma'    => '&#947;',
                            'delta'    => '&#948;',
                            'epsilon'  => '&#949;',
                            'zeta'     => '&#950;',
                            'eta'      => '&#951;',
                            'theta'    => '&#952;',
                            'iota'     => '&#953;',
                            'kappa'    => '&#954;',
                            'lambda'   => '&#955;',
                            'mu'       => '&#956;',
                            'nu'       => '&#957;',
                            'xi'       => '&#958;',
                            'omicron'  => '&#959;',
                            'pi'       => '&#960;',
                            'rho'      => '&#961;',
                            'sigmaf'   => '&#962;',
                            'sigma'    => '&#963;',
                            'tau'      => '&#964;',
                            'upsilon'  => '&#965;',
                            'phi'      => '&#966;',
                            'chi'      => '&#967;',
                            'psi'      => '&#968;',
                            'omega'    => '&#969;',
                            'thetasym' => '&#977;',
                            'upsih'    => '&#978;',
                            'piv'      => '&#982;',
                            'bull'     => '&#8226;',
                            'hellip'   => '&#8230;',
                            'prime'    => '&#8242;',
                            'Prime'    => '&#8243;',
                            'oline'    => '&#8254;',
                            'frasl'    => '&#8260;',
                            'weierp'   => '&#8472;',
                            'image'    => '&#8465;',
                            'real'     => '&#8476;',
                            'trade'    => '&#8482;',
                            'alefsym'  => '&#8501;',
                            'larr'     => '&#8592;',
                            'uarr'     => '&#8593;',
                            'rarr'     => '&#8594;',
                            'darr'     => '&#8595;',
                            'harr'     => '&#8596;',
                            'crarr'    => '&#8629;',
                            'lArr'     => '&#8656;',
                            'uArr'     => '&#8657;',
                            'rArr'     => '&#8658;',
                            'dArr'     => '&#8659;',
                            'hArr'     => '&#8660;',
                            'forall'   => '&#8704;',
                            'part'     => '&#8706;',
                            'exist'    => '&#8707;',
                            'empty'    => '&#8709;',
                            'nabla'    => '&#8711;',
                            'isin'     => '&#8712;',
                            'notin'    => '&#8713;',
                            'ni'       => '&#8715;',
                            'prod'     => '&#8719;',
                            'sum'      => '&#8721;',
                            'minus'    => '&#8722;',
                            'lowast'   => '&#8727;',
                            'radic'    => '&#8730;',
                            'prop'     => '&#8733;',
                            'infin'    => '&#8734;',
                            'ang'      => '&#8736;',
                            'and'      => '&#8743;',
                            'or'       => '&#8744;',
                            'cap'      => '&#8745;',
                            'cup'      => '&#8746;',
                            'int'      => '&#8747;',
                            'there4'   => '&#8756;',
                            'sim'      => '&#8764;',
                            'cong'     => '&#8773;',
                            'asymp'    => '&#8776;',
                            'ne'       => '&#8800;',
                            'equiv'    => '&#8801;',
                            'le'       => '&#8804;',
                            'ge'       => '&#8805;',
                            'sub'      => '&#8834;',
                            'sup'      => '&#8835;',
                            'nsub'     => '&#8836;',
                            'sube'     => '&#8838;',
                            'supe'     => '&#8839;',
                            'oplus'    => '&#8853;',
                            'otimes'   => '&#8855;',
                            'perp'     => '&#8869;',
                            'sdot'     => '&#8901;',
                            'lceil'    => '&#8968;',
                            'rceil'    => '&#8969;',
                            'lfloor'   => '&#8970;',
                            'rfloor'   => '&#8971;',
                            'lang'     => '&#9001;',
                            'rang'     => '&#9002;',
                            'loz'      => '&#9674;',
                            'spades'   => '&#9824;',
                            'clubs'    => '&#9827;',
                            'hearts'   => '&#9829;',
                            'diams'    => '&#9830;',
                            'nbsp'     => '&#160;',
                            'iexcl'    => '&#161;',
                            'cent'     => '&#162;',
                            'pound'    => '&#163;',
                            'curren'   => '&#164;',
                            'yen'      => '&#165;',
                            'brvbar'   => '&#166;',
                            'sect'     => '&#167;',
                            'uml'      => '&#168;',
                            'copy'     => '&#169;',
                            'ordf'     => '&#170;',
                            'laquo'    => '&#171;',
                            'not'      => '&#172;',
                            'shy'      => '&#173;',
                            'reg'      => '&#174;',
                            'macr'     => '&#175;',
                            'deg'      => '&#176;',
                            'plusmn'   => '&#177;',
                            'sup2'     => '&#178;',
                            'sup3'     => '&#179;',
                            'acute'    => '&#180;',
                            'micro'    => '&#181;',
                            'para'     => '&#182;',
                            'middot'   => '&#183;',
                            'cedil'    => '&#184;',
                            'sup1'     => '&#185;',
                            'ordm'     => '&#186;',
                            'raquo'    => '&#187;',
                            'frac14'   => '&#188;',
                            'frac12'   => '&#189;',
                            'frac34'   => '&#190;',
                            'iquest'   => '&#191;',
                            'Agrave'   => '&#192;',
                            'Aacute'   => '&#193;',
                            'Acirc'    => '&#194;',
                            'Atilde'   => '&#195;',
                            'Auml'     => '&#196;',
                            'Aring'    => '&#197;',
                            'AElig'    => '&#198;',
                            'Ccedil'   => '&#199;',
                            'Egrave'   => '&#200;',
                            'Eacute'   => '&#201;',
                            'Ecirc'    => '&#202;',
                            'Euml'     => '&#203;',
                            'Igrave'   => '&#204;',
                            'Iacute'   => '&#205;',
                            'Icirc'    => '&#206;',
                            'Iuml'     => '&#207;',
                            'ETH'      => '&#208;',
                            'Ntilde'   => '&#209;',
                            'Ograve'   => '&#210;',
                            'Oacute'   => '&#211;',
                            'Ocirc'    => '&#212;',
                            'Otilde'   => '&#213;',
                            'Ouml'     => '&#214;',
                            'times'    => '&#215;',
                            'Oslash'   => '&#216;',
                            'Ugrave'   => '&#217;',
                            'Uacute'   => '&#218;',
                            'Ucirc'    => '&#219;',
                            'Uuml'     => '&#220;',
                            'Yacute'   => '&#221;',
                            'THORN'    => '&#222;',
                            'szlig'    => '&#223;',
                            'agrave'   => '&#224;',
                            'aacute'   => '&#225;',
                            'acirc'    => '&#226;',
                            'atilde'   => '&#227;',
                            'auml'     => '&#228;',
                            'aring'    => '&#229;',
                            'aelig'    => '&#230;',
                            'ccedil'   => '&#231;',
                            'egrave'   => '&#232;',
                            'eacute'   => '&#233;',
                            'ecirc'    => '&#234;',
                            'euml'     => '&#235;',
                            'igrave'   => '&#236;',
                            'iacute'   => '&#237;',
                            'icirc'    => '&#238;',
                            'iuml'     => '&#239;',
                            'eth'      => '&#240;',
                            'ntilde'   => '&#241;',
                            'ograve'   => '&#242;',
                            'oacute'   => '&#243;',
                            'ocirc'    => '&#244;',
                            'otilde'   => '&#245;',
                            'ouml'     => '&#246;',
                            'divide'   => '&#247;',
                            'oslash'   => '&#248;',
                            'ugrave'   => '&#249;',
                            'uacute'   => '&#250;',
                            'ucirc'    => '&#251;',
                            'uuml'     => '&#252;',
                            'yacute'   => '&#253;',
                            'thorn'    => '&#254;',
                            'yuml'     => '&#255;'

                            );
      // Entity not found? Destroy it.
      return isset($table[$matches[1]]) ? $table[$matches[1]] : '';
    }

    static function inlineStyle($html, $css=array()) {
        foreach ($css as $style) {
            $html = str_replace('<' . $style['tag'], '<' . $style['tag'] . ' style="' . $style['style'] . '"', $html);
        }
        return $html;
    }

    /**
     * Sets a cookie used to override mobile browser detection
     *
     * $is_mobile  boolean Enable or disable mobile browsing
     */
    static function setMobile($is_mobile)
    {
        $is_mobile_str = ($is_mobile) ? '1' : '0';
        setcookie("jojo_mobile", $is_mobile_str, time() + (60 * 60 * 24 * 365), '/' . _SITEFOLDER);
        $_SESSION['jojo_mobile'] = $is_mobile; //set session var also as cookies can't be read until page reload
        self::isMobile(true); //flush cache
        return true;
    }

    /**
     * Returns true/false depending if visitor is using a mobile browser
     *
     * $flush  boolean Flush the cache and perform detection again
     */
    static function isMobile($flush = false)
    {
        /* value already cached? */
        static $is_mobile;
        if (isset($is_mobile) && !$flush) return $is_mobile;

        /* check mobile_site is enabled in Jojo options */
        $mobile_site = Jojo::getOption('mobile_site', 'no');
        if (($mobile_site == 'no')) {
            $is_mobile = false;
            return $is_mobile;
        }

        /* look for session */
        if (isset($_SESSION['jojo_mobile'])) {
            $is_mobile = $_SESSION['jojo_mobile'];
            return $is_mobile;
        }

        /* look for cookie */
        if (isset($_COOKIE['jojo_mobile'])) {
            $is_mobile = ($_COOKIE['jojo_mobile'] == '1') ? true : false;
            $_SESSION['jojo_mobile'] = $is_mobile;
            return $is_mobile;
        }

        /* sniff user agent */
        $useragent = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';

        /* ipad is not considered mobile */
        if(strstr($useragent,'ipad') !== false) {
            $is_mobile = false;
            return $is_mobile;
        }

        /* known mobile user agents */
        /* TODO: http://code.google.com/p/php-mobile-detect/ may be a better alternative */
        $is_mobile = (preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)));
        return $is_mobile;
    }
}
