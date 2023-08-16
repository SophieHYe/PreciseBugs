<?php
require_once 'MDB2/Schema.php';
class pearweb_postinstall
{
    var $lastversion;
    var $dsn;
    /**
     * Frontend object
     * @var PEAR_Frontend
     * @access private
     */
    var $_ui;

    function init(&$config, &$pkg, $lastversion)
    {
        $this->_ui = &PEAR_Frontend::singleton();
        $this->lastversion = $lastversion;
        return true;
    }

    function run($answers, $phase)
    {
        switch ($phase) {
            case 'askdb' :
                if ($answers['yesno'] != 'y') {
                    $this->_ui->skipParamgroup('init');
                }
                return true;
                break;
            case 'init' :
                PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
                if (PEAR::isError($err = MDB2::loadFile('Driver' . DIRECTORY_SEPARATOR .
                      $answers['driver']))) {
                    PEAR::popErrorHandling();
                    $this->_ui->outputData('ERROR: Unknown MDB2 driver "' .
                        $answers['driver'] . '": ' .
                        $err->getUserInfo() . '. Be sure you have installed ' .
                        'MDB2_Driver_' . $answers['driver']);
                    return false;
                }
                PEAR::popErrorHandling();
                if ($answers['driver'] !== 'mysqli') {
                    $this->_ui->outputData('pearweb only supports mysqli, ' .
                        'not ' . $answers['driver']);
                    return false;
                }
                return $this->initializeDatabase($answers);
                break;
            case 'askhttpd' :
                if ($answers['yesno'] != 'y') {
                    $this->_ui->skipParamgroup('httpdconf');
                }
                return true;
                break;
            case 'httpdconf' :
                return $this->setupHttpdconf($answers);
                break;
        }
        return true;
    }

    /**
     * Create or upgrade the database needed for pearweb
     *
     * This helper function scans for previous database versions,
     * and upgrades the database based on differences between the
     * previous version's schema and the one distributed with this
     * version.
     *
     * If the database has never been created, then it is created.
     *
     * @param array $answers
     * @return boolean
     */
    function initializeDatabase($answers)
    {
        $this->dsn = array(
            'phptype' => $answers['driver'],
            'username' => $answers['user'],
            'password' => $answers['password'],
            'hostspec' => $answers['host'],
            'database' => $answers['database']);
        $a = MDB2_Schema::factory($this->dsn,
            array('idxname_format' => '%s',
                  'seqname_format' => 'id',
                  'quote_identifier' => true));
                  
        // for upgrade purposes
        $updir = '@www-dir@/pear.php.net/sql/.pearweb-upgrade';
        if (!file_exists($updir)) {
            if (!mkdir($updir)) {
                $this->_ui->outputData('error - make sure we can create directories');
                return false;
            }
        }
        PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
        $c = $a->parseDatabaseDefinitionFile(
            realpath('@www-dir@/pear.php.net/sql/pearweb_mdb2schema.xml'));
        PEAR::staticPopErrorHandling();
        if (PEAR::isError($c)) {
            $extra = '';
            if (MDB2_Schema::isError($c) || MDB2::isError($c)) {
                $extra = "\n" . $c->getUserInfo();
            }
            $this->_ui->outputData('ERROR: ' . $c->getMessage() . $extra);
            return false;
        }
        $c['name'] = $answers['database'];
        $c['create'] = 1;
        $c['overwrite'] = 0;
        $dir = opendir('@www-dir@/pear.php.net/sql/.pearweb-upgrade');
        $oldversion = false;
        while (false !== ($entry = readdir($dir))) {
            if ($entry[0] === '.') {
                continue;
            }
            if (strpos($entry, $answers['database']) === 0) {
                // this is one of ours
                // strip databasename-
                $entry = substr($entry, strlen($answers['database']) + 1);
                // strip ".ser"
                $entry = substr($entry, 0, strlen($entry) - 4);
                // ... and we're left with just the version
                if (!$oldversion) {
                    $oldversion = $entry;
                    continue;
                }
                if (version_compare($entry, $oldversion, '>')) {
                    $oldversion = $entry;
                }
            }
        }
        
        $serfile = $updir . $answers['database'] . '-@version@.ser';
        if (!file_exists($serfile)) {
            $fp = fopen($serfile, 'w');
            fwrite($fp, serialize($c));
            fclose($fp);
        }
        if ($oldversion == '@version@') {
            // this is where to change if we need to add a "force upgrade of
            // structure" option
            // we would uncomment the following line:
            //$c['overwrite'] = true;
            $oldversion = false;
        }
        if ($oldversion) {
            $sFile = $updir . $answers['database'] . '-' . $oldversion . '.ser';
            try {
                $curdef = unserialize(file_get_contents($sFile), ['allowed_classes' => false]);
            } catch (Exception $ex) {
                $curdef = false;
            }

            if (!is_array($curdef)) {
                $this->_ui->outputData('invalid data returned from previous version');
            }
            // get a database diff (MDB2_Schema is very useful here)
            PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
            $c = $a->compareDefinitions($c, $curdef);
            if (PEAR::isError($c)) {
                $this->_ui->outputData($err->getMessage());
                $this->_ui->outputData($err->getUserInfo());
                $this->_ui->outputData('Unable to automatically update database');
                return false;
            }
            $err = $a->updateDatabase($curdef, $c);
            PEAR::staticPopErrorHandling();
        } else {
            PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
            $err = $a->createDatabase($c);
            PEAR::staticPopErrorHandling();
        }
        if (PEAR::isError($err)) {
            $this->_ui->outputData($err->getUserInfo());
            $this->_ui->outputData($err->getMessage());
            return false;
        }
        return true;
    }

    /**
     * This helper function parses httpd.conf and adds needed information
     * for pearweb to run.
     *
     * In essence, this takes the contents of docs/apache_setup.txt and
     * adds them to httpd.conf with paths replaced that are needed to
     * make the thing work.
     *
     * @param array $answers
     * @return boolean
     */
    function setupHttpdconf($answers)
    {
        // TODO handle ports properly
        $eol = PHP_EOL;
        if (!realpath($answers['path']) || !file_exists($answers['path'])) {
            $this->_ui->outputData('No such file: "' . $answers['path'] . '"');
            return false;
        }
        $httpdconf = file(realpath($answers['path']));
        $found = array();
        foreach ($httpdconf as $num => $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }
            if (strpos($line, 'NameVirtualHost') === 0) {
                $found['NameVirtualHost'] = $num;
                continue;
            }
            if (strpos($line, '# inserted by pearweb #### (do not remove) start') === 0) {
                $found['start'] = $num;
                continue;
            }
            if (strpos($line, '# inserted by pearweb #### (do not remove) end') === 0) {
                $found['end'] = $num;
            }
        }
        if (strtolower($answers['addnamev']) == 'yes') {
            if (array_key_exists('NameVirtualHost', $found)) {
                $httpdconf[$found['NameVirtualHost']] =
                    'NameVirtualHost ' . $answers['namehost'] . $eol;
            } else {
                if (array_key_exists('start', $found) && array_key_exists('end', $found)) {
                    $one = array_slice($httpdconf, 0, $found['start'] - 1);
                    $found['start']++;
                    $one[] = $eol . 'NameVirtualHost ' . $answers['namehost'] . $eol;
                    $two = array_slice($httpdconf, $found['start']);
                    $httpdconf = array_merge($one, $two);
                } else {
                    $httpdconf[] = $eol . 'NameVirtualHost ' . $answers['namehost'] . $eol;
                }
            }
        }
        if (array_key_exists('start', $found) && array_key_exists('end', $found)) {
            $one = array_slice($httpdconf, 0, $found['start'] - 1);
            $two = array_slice($httpdconf, $found['end'] + 1);
        } else {
            $one = $httpdconf;
            $two = array();
        }

        // here we go...
        $middle = array();
        $middle[] = $eol;
        $middle[] = '# inserted by pearweb #### (do not remove) start' . $eol;
        $middle[] = '<VirtualHost ' . $answers['namehost'] . '>' . $eol;
        $middle[] = ' ServerName ' . $answers['pear'] . $eol;
        $middle[] = $eol;
        // apache requires all path separators to be "/" even on windows
        $middle[] = ' DocumentRoot ' . str_replace('\\', '/', '@www-dir@') . '/pear.php.net' . $eol;
        $middle[] = ' DirectoryIndex index.php index.html' . $eol;
        $middle[] = $eol;
        $middle[] = ' php_value include_path .' . PATH_SEPARATOR .
            str_replace('\\', '/', '@www-dir@')
            . '/pear.php.net/include' . PATH_SEPARATOR . str_replace('\\', '/', '@php-dir@') . $eol;
        $middle[] = ' php_value auto_prepend_file pear-prepend.php' . $eol;
        $middle[] = ' php_flag magic_quotes_gpc off' . $eol;
        $middle[] = ' php_flag magic_quotes_runtime off' . $eol;
        $middle[] = ' php_flag register_globals Off' . $eol;
        $middle[] = $eol;
        $middle[] = ' ErrorDocument 404 /error/404.php' . $eol;
        $middle[] = $eol;
        $middle[] = ' Alias /package ' . str_replace('\\', '/', '@www-dir@')
            . '/public_html/package-info.php' . $eol;
        $middle[] = ' Alias /user    ' . str_replace('\\', '/', '@www-dir@')
            . '/public_html/account-info.php' . $eol;
        $middle[] = ' Alias /sidebar/pear.gif ' . str_replace('\\', '/', '@www-dir@')
            . '/public_html/gifs/pear_item.gif' . $eol;
        $middle[] = ' Alias /distributions/manual/chm /var/lib/pear/chm' . $eol;
        $middle[] = ' Alias /reference /var/lib/pear/apidoc' . $eol;
        $middle[] = $eol;
        $middle[] = ' RedirectPermanent /download-docs.php          http://'
            . $answers['pear'] . '/manual/' . $eol;
        $middle[] = ' RedirectPermanent /rss.php                    http://'
            . $answers['pear'] . '/feeds/latest.rss' . $eol;
        $middle[] = ' RedirectPermanent /weeklynews.php             http://'
            . $answers['pear'] . '/' . $eol;
        $middle[] = ' RedirectPermanent /support.php                http://'
            . $answers['pear'] . '/support/' . $eol;
        $middle[] = ' RedirectPermanent /credits.php                http://'
            . $answers['pear'] . '/about/credits.php' . $eol;
        $middle[] = ' RedirectPermanent /pepr/pepr-overview.php     http://'
            . $answers['pear'] . '/pepr/' . $eol;
        $middle[] = ' RedirectPermanent /faq.php                    http://'
            . $answers['pear'] . '/manual/en/faq.php' . $eol;
        $middle[] = ' RedirectPermanent /doc/index.php              http://'
            . $answers['pear'] . '/manual/en/' . $eol;
        $middle[] = $eol;
        $middle[] = ' #' . $eol;
        $middle[] = ' # xmlrpc.php was removed 1 Jan 2008 and won\'t come back' . $eol;
        $middle[] = ' #' . $eol;
        $middle[] = $eol;
        $middle[] = ' Redirect gone /xmlrpc.php' . $eol;
        $middle[] = $eol;
        $middle[] = ' RewriteEngine On' . $eol;
        $middle[] = $eol;
        $middle[] = ' #' . $eol;
        $middle[] = ' # Rewriting rules for the RSS feeds' . $eol;
        $middle[] = ' #' . $eol;
        $middle[] = $eol;
        $middle[] = ' RewriteRule   /feeds/(.+)\.rss$ /feeds/feeds.php?type=$1' . $eol;
        $middle[] = $eol;
        $middle[] = ' #' . $eol;
        $middle[] = ' # Rewriting rule for the API documentation' . $eol;
        $middle[] = ' #' . $eol;
        $middle[] = $eol;
        $middle[] = ' RewriteRule   /package/([a-zA-Z0-9_]+)/docs/(.+)($/|$) /reference/$1-$2 [PT]' . $eol;
        $middle[] = $eol;
        $middle[] = ' #' . $eol;
        $middle[] = ' # Rewriting rule for the Bug system' . $eol;
        $middle[] = ' #' . $eol;
        $middle[] = $eol;
        $middle[] = ' RewriteRule   /bugs/([0-9]+)/*$ /bugs/bug.php?id=$1 [R]
' . $eol;
        $middle[] = $eol;
        $middle[] = ' #' . $eol;
        $middle[] = ' # Rewriting rule for the manual' . $eol;
        $middle[] = ' # throw pecl doc people to the php manual' . $eol;
        $middle[] = ' #' . $eol;
        $middle[] = $eol;
        $middle[] = ' RewriteRule   /manual/[a-z]{2}/pecl.([a-zA-Z0-9_-]+)\.php$ http://www.php.net/$1 [R=301]' . $eol;
        $middle[] = $eol;
        $middle[] = ' SetEnvIf User-Agent "MS Search 4\.0 Robot\)$" badrobot' . $eol;
        $middle[] = $eol;
        $middle[] = ' <Directory />' . $eol;
        $middle[] = '  order deny,allow' . $eol;
        $middle[] = '  deny from env=badrobot' . $eol;
        $middle[] = ' </Directory>' . $eol;
        $middle[] = $eol;
        $middle[] = ' <Location /get>' . $eol;
        $middle[] = '  ForceType application/x-httpd-php' . $eol;
        $middle[] = $eol;
        $middle[] = '  Deny From 194.51.105.35' . $eol;
        $middle[] = ' </Location>' . $eol;
        $middle[] = $eol;
        $middle[] = ' <Location /manual>' . $eol;
        $middle[] = '  ErrorDocument 404 /error/404-manual.php' . $eol;
        $middle[] = ' </Location>' . $eol;
        $middle[] = $eol;
        $middle[] = ' <Location /bugs/include>' . $eol;
        $middle[] = '  deny from all' . $eol;
        $middle[] = ' </Location>' . $eol;
        $middle[] = $eol;
        $middle[] = ' <Location /trackback>' . $eol;
        $middle[] = '  DirectoryIndex trackback.php' . $eol;
        $middle[] = ' </Location>' . $eol;
        $middle[] = $eol;
        $middle[] = '</VirtualHost>' . $eol;
        $middle[] = '# inserted by pearweb #### (do not remove) end' . $eol;

        $one = array_merge($one, $middle);
        $httpdconf = array_merge($one, $two);
        $this->_ui->outputData('opening ' . $answers['path'] . ' for writing');
        $fp = fopen(realpath($answers['path']), 'w');
        $this->_ui->outputData('writing data...');
        fwrite($fp, implode('', $httpdconf));
        fclose($fp);
        $this->_ui->outputData('...done');
        return true;
    }
}
