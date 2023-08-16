<?php
require_once 'MDB2/Schema.php';
class pearweb_election_postinstall
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
                if ($answers['yesno'] == 'n') {
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
        //include_once dirname(__FILE__) . 'include/pear-config.php';
        //$a = MDB2_Schema::factory(PEAR_DATABASE_DSN,
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
        if (!file_exists('@www-dir@' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR .
            '.pearweb-upgrade')) {
            if (!mkdir('@www-dir@' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR .
                  '.pearweb-upgrade')) {
                $this->_ui->outputData('error - make sure we can create directories');
                return false;
            }
        }
        PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
        $c = $a->parseDatabaseDefinitionFile(
            realpath('@www-dir@/sql/pearweb_election.xml'));
        PEAR::staticPopErrorHandling();
        if (PEAR::isError($c)) {
            $extra = '';
            if (MDB2_Schema::isError($c) || MDB2::isError($c)) {
                $extra = "\n" . $c->getUserInfo();
            }
            $this->_ui->outputData('ERROR: ' . $c->getMessage() . $extra);
            return false;
        }
        $c['name']      = $answers['database'];
        $c['create']    = 1;
        $c['overwrite'] = 0;
        $dir = opendir('@www-dir@/sql/.pearweb-upgrade');
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
        if (!file_exists('@www-dir@/sql/.pearweb-upgrade/' .
              $answers['database'] . '-@version@.ser')) {
            $fp = fopen('@www-dir@/sql/.pearweb-upgrade/' .
                $answers['database'] . '-@version@.ser', 'w');
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
            $sFile = '@www-dir@/sql/.pearweb-upgrade/' . $answers['database'] . '-' . $oldversion . '.ser';
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
}
