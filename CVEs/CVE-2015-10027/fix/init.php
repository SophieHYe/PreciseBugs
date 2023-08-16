<?php

/**
 * Tiny Tiny RSS plugin for LDAP authentication 
 * @author tsmgeek (tsmgeek@gmail.com)
 * @originalauthor hydrian (ben.tyger@tygerclan.net)
 * @copyright GPL2
 *  Requires php-ldap 
 * @version 1.00
 */
/**
 *  Configuration
 *  Put the following options in config.php and customize them for your environment
 *
 * 	define('LDAP_AUTH_SERVER_URI', 'ldaps://LDAPServerHostname:port/');
 * 	define('LDAP_AUTH_USETLS', FALSE); // Enable TLS Support for ldaps://
 * 	define('LDAP_AUTH_ALLOW_UNTRUSTED_CERT', TRUE); // Allows untrusted certificate
 * 	define('LDAP_AUTH_BASEDN', 'dc=example,dc=com');
 * 	define('LDAP_AUTH_ANONYMOUSBEFOREBIND', FALSE);
 * 	// ??? will be replaced with the entered username(escaped) at login 
 * 	define('LDAP_AUTH_SEARCHFILTER', '(&(objectClass=person)(uid=???))');
 * 	// Optional configuration
 *      define('LDAP_AUTH_BINDDN', 'cn=serviceaccount,dc=example,dc=com');
 *      define('LDAP_AUTH_BINDPW', 'ServiceAccountsPassword');
 *      define('LDAP_AUTH_LOGIN_ATTRIB', 'uid');
 *  define('LDAP_AUTH_LOG_ATTEMPTS', FALSE);
 *    Enable Debug Logging
 *  define('LDAP_AUTH_DEBUG', FALSE);
 *    
 *    
 *    
 */

/**
 * 	Notes -
 * 	LDAP search does not support follow ldap referals. Referals are disabled to 
 * 	allow proper login.  This is particular to Active Directory.  
 * 
 * 	Also group membership can be supported if the user object contains the
 * 	the group membership via attributes.  The following LDAP servers can 
 * 	support this.   
 * 	 * Active Directory
 *   * OpenLDAP support with MemberOf Overlay
 *
 */
class Auth_Ldap extends Plugin implements IAuthModule {

    private $link;
    private $host;
    private $base;
    private $logClass;
    private $ldapObj = NULL;
    private $_debugMode;
    private $_serviceBindDN;
    private $_serviceBindPass;
    private $_baseDN;
    private $_useTLS;
    private $_host;
    private $_port;
    private $_scheme;
    private $_schemaCacheEnabled;
    private $_anonBeforeBind;
    private $_allowUntrustedCerts;
    private $_ldapLoginAttrib;

    function about() {
        return array(0.05,
            "Authenticates against an LDAP server (configured in config.php)",
            "hydrian",
            true);
    }

    function init($host) {
        $this->link = $host->get_link();
        $this->host = $host;
        $this->base = new Auth_Base($this->link);

        $host->add_hook($host::HOOK_AUTH_USER, $this);
    }

    private function _log($msg, $level = E_USER_NOTICE, $file = '', $line = '', $context = '') {
        $loggerFunction = Logger::get();
        if (is_object($loggerFunction)) {
            $loggerFunction->log_error($level, $msg, $file, $line, $context);
        } else {
            trigger_error($msg, $level);
        }
    }

    /**
     * Logs login attempts
     * @param string $username Given username that attempts to log in to TTRSS
     * @param string $result "Logging message for type of result. (Success / Fail)"
     * @return boolean
     * @deprecated
     * 
     * Now that _log support syslog and log levels and graceful fallback user.  
     */
    private function _logAttempt($username, $result) {


        return trigger_error('TT-RSS Login Attempt: user ' . (string) $username .
                ' attempted to login (' . (string) $result . ') from ' . (string) $ip, E_USER_NOTICE
        );
    }

    /**
     * @param string $subject The subject string
     * @param string $ignore Set of characters to leave untouched
     * @param int $flags Any combination of LDAP_ESCAPE_* flags to indicate the
     *                   set(s) of characters to escape.
     * @return string
     */
    function ldap_escape($subject, $ignore = '', $flags = 0)
    {
        if (!function_exists('ldap_escape')) {
            define('LDAP_ESCAPE_FILTER', 0x01);
            define('LDAP_ESCAPE_DN',     0x02);
            
            static $charMaps = array(
                LDAP_ESCAPE_FILTER => array('\\', '*', '(', ')', "\x00"),
                LDAP_ESCAPE_DN     => array('\\', ',', '=', '+', '<', '>', ';', '"', '#'),
            );

            // Pre-process the char maps on first call
            if (!isset($charMaps[0])) {
                $charMaps[0] = array();
                for ($i = 0; $i < 256; $i++) {
                    $charMaps[0][chr($i)] = sprintf('\\%02x', $i);;
                }

                for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_FILTER]); $i < $l; $i++) {
                    $chr = $charMaps[LDAP_ESCAPE_FILTER][$i];
                    unset($charMaps[LDAP_ESCAPE_FILTER][$i]);
                    $charMaps[LDAP_ESCAPE_FILTER][$chr] = $charMaps[0][$chr];
                }

                for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_DN]); $i < $l; $i++) {
                    $chr = $charMaps[LDAP_ESCAPE_DN][$i];
                    unset($charMaps[LDAP_ESCAPE_DN][$i]);
                    $charMaps[LDAP_ESCAPE_DN][$chr] = $charMaps[0][$chr];
                }
            }

            // Create the base char map to escape
            $flags = (int)$flags;
            $charMap = array();
            if ($flags & LDAP_ESCAPE_FILTER) {
                $charMap += $charMaps[LDAP_ESCAPE_FILTER];
            }
            if ($flags & LDAP_ESCAPE_DN) {
                $charMap += $charMaps[LDAP_ESCAPE_DN];
            }
            if (!$charMap) {
                $charMap = $charMaps[0];
            }

            // Remove any chars to ignore from the list
            $ignore = (string)$ignore;
            for ($i = 0, $l = strlen($ignore); $i < $l; $i++) {
                unset($charMap[$ignore[$i]]);
            }

            // Do the main replacement
            $result = strtr($subject, $charMap);

            // Encode leading/trailing spaces if LDAP_ESCAPE_DN is passed
            if ($flags & LDAP_ESCAPE_DN) {
                if ($result[0] === ' ') {
                    $result = '\\20' . substr($result, 1);
                }
                if ($result[strlen($result) - 1] === ' ') {
                    $result = substr($result, 0, -1) . '\\20';
                }
            }

            return $result;
        }else{
            return ldap_escape($subject, $ignore, $flags);
        }    
    }
        
    /**
     * Finds client's IP address
     * @return string
     */
    private function _getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet

            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    private function _getBindDNWord() {
        return (strlen($this->_serviceBindDN) > 0 ) ? $this->_serviceBindDN : 'anonymous DN';
    }

    private function _getTempDir() {
        if (!sys_get_temp_dir()) {
            $tmpFile = tempnam();
            $tmpDir = dirname($tmpFile);
            unlink($tmpFile);
            unset($tmpFile);
            return $tmpDir;
        } else {
            return sys_get_temp_dir();
        }
    }

    /**
     * Main Authentication method
     * Required for plugin interface 
     * @param unknown $login  User's username
     * @param unknown $password User's password
     * @return boolean
     */
    function authenticate($login, $password) {
        if ($login && $password) {

            if (!function_exists('ldap_connect')) {
                trigger_error('auth_ldap requires PHP\'s PECL LDAP package installed.');
                return FALSE;
            }

            /**
              Loading configuration
             * */
            $this->_debugMode = defined('LDAP_AUTH_DEBUG') ?
                    LDAP_AUTH_DEBUG : FALSE;

            $this->_anonBeforeBind = defined('LDAP_AUTH_ANONYMOUSBEFOREBIND') ?
                    LDAP_AUTH_ANONYMOUSBEFOREBIND : FALSE;

            $this->_serviceBindDN = defined('LDAP_AUTH_BINDDN') ? LDAP_AUTH_BINDDN : null;
            $this->_serviceBindPass = defined('LDAP_AUTH_BINDPW') ? LDAP_AUTH_BINDPW : null;
            $this->_baseDN = defined('LDAP_AUTH_BASEDN') ? LDAP_AUTH_BASEDN : null;
            if (!defined('LDAP_AUTH_BASEDN')) {
                $this->_log('LDAP_AUTH_BASEDN is required and not defined.', E_USER_ERROR);
                return FALSE;
            } else {
                $this->_baseDN = LDAP_AUTH_BASEDN;
            }

            $parsedURI = parse_url(LDAP_AUTH_SERVER_URI);
            if ($parsedURI === FALSE) {
                $this->_log('Could not parse LDAP_AUTH_SERVER_URI in config.php', E_USER_ERROR);
                return FALSE;
            }
            $this->_host = $parsedURI['host'];
            $this->_scheme = $parsedURI['scheme'];

            if (is_int($parsedURI['port'])) {
                $this->_port = $parsedURI['port'];
            } else {
                $this->_port = ($this->_scheme === 'ldaps') ? 636 : 389;
            }

            $this->_useTLS = defined('LDAP_AUTH_USETLS') ? LDAP_AUTH_USETLS : FALSE;

            $this->_logAttempts = defined('LDAP_AUTH_LOG_ATTEMPTS') ?
                    LDAP_AUTH_LOG_ATTEMPTS : FALSE;

            $this->_ldapLoginAttrib = defined('LDAP_AUTH_LOGIN_ATTRIB') ?
                    LDAP_AUTH_LOGIN_ATTRIB : null;


            /**
              Building LDAP connection
             * */
            $ldapConnParams = array(
                'host' => $this->_host,
                'basedn' => $this->_baseDN,
                'port' => $this->_port,
                'starttls' => $this->_useTLS
            );

            if ($this->_debugMode)
                $this->_log(print_r($ldapConnParams, TRUE), E_USER_NOTICE);
            $ldapConn = @ldap_connect($this->_host, $this->_port);
            if ($ldapConn === FALSE) {
                $this->_log('Could not connect to LDAP Server: \'' . $this->_host . '\'', E_USER_ERROR);
                return false;
            }

            /* Enable LDAP protocol version 3. */
            if (!@ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
                $this->_log('Failed to set LDAP Protocol version (LDAP_OPT_PROTOCOL_VERSION) to 3', E_USER_ERROR);
                return false;
            }

            /* Set referral option */
            if (!@ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, FALSE)) {
                $this->_log('Failed to set LDAP Referrals (LDAP_OPT_REFERRALS) to TRUE', E_USER_ERROR);
                return false;
            }

            if (stripos($this->_host, "ldaps:") === FALSE and $this->_useTLS) {
                if (!@ldap_start_tls($ldapConn)) {
                    $this->_log('Unable to force TLS', E_USER_ERROR);
                    return false;
                }
            }
            $error = @ldap_bind($ldapConn, $this->_serviceBindDN, $this->_serviceBindPass);
            if ($error === FALSE) {
                $this->_log(
                        'LDAP bind(): Bind failed (' . $error . ')with DN ' . $this->_serviceBindDN, E_USER_ERROR
                );
                return FALSE;
            } else {
                $this->_log(
                        'Connected to LDAP Server: ' . LDAP_AUTH_SERVER_URI . ' with ' . $this->_getBindDNWord());
            }

            // Bind with service account if orignal connexion was anonymous
            /* if (($this->_anonBeforeBind) && (strlen($this->_bindDN > 0))) {
              $binding=$this->ldapObj->bind($this->_serviceBindDN, $this->_serviceBindPass);
              if (get_class($binding) !== 'Net_LDAP2') {
              $this->_log(
              'Cound not bind service account: '.$binding->getMessage(),E_USER_ERROR);
              return FALSE;
              } else {
              $this->_log('Bind with '.$this->_serviceBindDN.' successful.',E_USER_NOTICE);
              }
              } */

            //Searching for user
            $filterObj = str_replace('???', ldap_escape($login), LDAP_AUTH_SEARCHFILTER);
            $searchResults = @ldap_search($ldapConn, $this->_baseDN, $filterObj, array('displayName', 'title', 'sAMAccountName'), 0, 0, 0);
            if ($searchResults === FALSE) {
                $this->_log('LDAP Search Failed on base \'' . $this->_baseDN . '\' for \'' . $filterObj . '\'', E_USER_ERROR);
                return FALSE;
            }
            $count = @ldap_count_entries($ldapConn, $searchResults);
            if ($count === FALSE) {
                
            } elseif ($count > 1) {
                $this->_log('Multiple DNs found for username ' . (string) $login, E_USER_WARNING);
                return FALSE;
            } elseif ($count === 0) {
                $this->_log((string) $login, 'Unknown User', E_USER_NOTICE);
                return FALSE;
            }

            //Getting user's DN from search
            $userEntry = @ldap_first_entry($ldapConn, $searchResults);
            if ($userEntry === FALSE) {
                $this->_log('LDAP search(): Unable to retrieve result after searching base \'' . $this->_baseDN . '\' for \'' . $filterObj . '\'', E_USER_WARNING);
                return false;
            }
            $userAttributes = @ldap_get_attributes($ldapConn, $userEntry);
            $userDN = @ldap_get_dn($ldapConn, $userEntry);
            if ($userDN == FALSE) {
                $this->_log('LDAP search(): Unable to get DN after searching base \'' . $this->_baseDN . '\' for \'' . $filterObj . '\'', E_USER_WARNING);
                return false;
            }
            //Binding with user's DN. 
            if ($this->_debugMode)
                $this->_log('Try to bind with user\'s DN: ' . $userDN);
            $loginAttempt = @ldap_bind($ldapConn, $userDN, $password);
            if ($loginAttempt === TRUE) {
                $this->_log('User: ' . (string) $login . ' authentication successful');
                if (strlen(userAttributes) > 0) {
                    if ($this->_debugMode)
                        $this->_log('Looking up TT-RSS username attribute in ' . $this->_ldapLoginAttrib);
                    $ttrssUsername = $userAttributes['sAMAccountName'][0];
                    ;
                    @ldap_close($ldapConn);
                    if (!is_string($ttrssUsername)) {
                        $this->_log('Could not find user name attribute ' . $this->_ldapLoginAttrib . ' in LDAP entry', E_USER_WARNING);
                        return FALSE;
                    }
                    return $this->base->auto_create_user($ttrssUsername);
                } else {
                    @ldap_close($ldapConn);
                    return $this->base->auto_create_user($login);
                }
            } else {
                @ldap_close($ldapConn);
                $this->_log('User: ' . (string) $login . ' authentication failed');
                return FALSE;
            }
        }
        return false;
    }

    /**
     * Returns plugin API version
     * Required for plugin interface
     * @return number
     */
    function api_version() {
        return 2;
    }

}

?>
