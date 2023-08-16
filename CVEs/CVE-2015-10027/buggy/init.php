<?php
/** 
 * Tiny Tiny RSS plugin for LDAP authentication 
 * @author hydrian (ben.tyger@tygerclan.net)
 * @copyright GPL2
 *  Requires php-ldap and PEAR Net::LDAP2
 * @version 0.05
 */

/**
 *  Configuration
 *  Put the following options in config.php and customize them for your environment
 *
 * 	define('LDAP_AUTH_SERVER_URI', 'ldaps://LDAPServerHostname:port/');
 *	define('LDAP_AUTH_USETLS', FALSE); // Enable TLS Support for ldaps://
 *	define('LDAP_AUTH_ALLOW_UNTRUSTED_CERT', TRUE); // Allows untrusted certificate
 *	define('LDAP_AUTH_BASEDN', 'dc=example,dc=com');
 * 	define('LDAP_AUTH_ANONYMOUSBEFOREBIND', FALSE);
 *	// ??? will be replaced with the entered username(escaped) at login 
 *	define('LDAP_AUTH_SEARCHFILTER', '(&(objectClass=person)(uid=???))');
 *	// Optional configuration
 *      define('LDAP_AUTH_BINDDN', 'cn=serviceaccount,dc=example,dc=com');
 *      define('LDAP_AUTH_BINDPW', 'ServiceAccountsPassword');
 *      define('LDAP_AUTH_LOGIN_ATTRIB', 'uid');
 *	  
 *  define('LDAP_AUTH_SCHEMA_CACHE_ENABLE', TRUE);
 *    Enables Schema Caching (Recommended) 
 *  define('LDAP_AUTH_SCHEMA_CACHE_TIMEOUT', 86400);
 *    Max time a schema cache is kept (seconds) 
 *  define('LDAP_AUTH_LOG_ATTEMPTS', FALSE);
 *    Enable Debug Logging
 *  define('LDAP_AUTH_DEBUG', FALSE);
 *    
 *    
 *    
 */

/**
 *	Notes -
 *	LDAP search does not support follow ldap referals. Referals are disabled to 
 *	allow proper login.  This is particular to Active Directory.  
 * 
 *	Also group membership can be supported if the user object contains the
 *	the group membership via attributes.  The following LDAP servers can 
 *	support this.   
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
	
	private function _log($msg, $level = E_USER_NOTICE,$file='',$line='',$context='') {
		$loggerFunction = Logger::get();
		if (is_object($loggerFunction)) {
			$loggerFunction->log_error($level, $msg,$file,$line,$context);
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
		
		
		return trigger_error('TT-RSS Login Attempt: user '.(string)$username.
			' attempted to login ('.(string)$result.') from '.(string)$ip,
			E_USER_NOTICE
		);	
	}
	
	/**
	 * Finds client's IP address
	 * @return string
	 */
	private function _getClientIP () {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			//check ip from share internet
		
			$ip=$_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			//to check ip is pass from proxy
			$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else {
			$ip=$_SERVER['REMOTE_ADDR'];
		}
		
		return $ip;
	}

  private function _getBindDNWord () {
    return (strlen($this->_serviceBindDN) > 0 ) ?  $this->_serviceBindDN : 'anonymous DN';
  }

  private function _getTempDir () {
    if (!sys_get_temp_dir()) {
      $tmpFile=tempnam();
      $tmpDir=dirname($tmpFile);
      unlink($tmpFile);
      unset($tmpFile);
      return $tmpDir;
    } else {
      return sys_get_temp_dir();
    }
  }

  private function _getSchemaCache () {
        $cacheFileLoc=$this->_getTempDir().'/ttrss-ldapCache-'.$this->_host.':'.$this->_port.'.cache';
        if ($this->_debugMode) $this->_log('Schema Cache File: '.$cacheFileLoc);
        $schemaCacheConf=array(
            'path'=>$cacheFileLoc,
            'max_age'=>$this->_schemaCacheTimeout
        );
        $schemaCacheObj= new Net_LDAP2_SimpleFileSchemaCache($schemaCacheConf);
        $this->ldapObj->registerSchemaCache($schemaCacheObj);
        $schemaCacheObj->storeSchema($this->ldapObj->schema());
        return TRUE;
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
			if (!require_once('Net/LDAP2.php')) { 
				trigger_error('auth_ldap requires the PEAR package Net::LDAP2');
				return FALSE;
			}
			
      /**
      Loading configuration 
      **/

			$this->_debugMode = defined('LDAP_AUTH_DEBUG') ?
				LDAP_AUTH_DEBUG : FALSE;
			
			$this->_anonBeforeBind =defined('LDAP_AUTH_ANONYMOUSBEFOREBIND') ?
				LDAP_AUTH_ANONYMOUSBEFOREBIND : FALSE;
			
			$this->_serviceBindDN = defined('LDAP_AUTH_BINDDN') ? LDAP_AUTH_BINDDN : null;
			$this->_serviceBindPass = defined('LDAP_AUTH_BINDPW') ? LDAP_AUTH_BINDPW : null;
      $this->_baseDN = defined('LDAP_AUTH_BASEDN') ? LDAP_AUTH_BASEDN : null;
      if (!defined('LDAP_AUTH_BASEDN')) {
        $this->_log('LDAP_AUTH_BASEDN is required and not defined.',E_USER_ERROR);
  		  return FALSE;
      } else {
        $this->_baseDN = LDAP_AUTH_BASEDN;
      }

			$parsedURI=parse_url(LDAP_AUTH_SERVER_URI);
			if ($parsedURI === FALSE) {
				$this->_log('Could not parse LDAP_AUTH_SERVER_URI in config.php',E_USER_ERROR);
				return FALSE;
			}
      $this->_host = $parsedURI['host'];
      $this->_scheme = $parsedURI['scheme'];

      if (is_int($parsedURI['port'])) {
        $this->_port = $parsedURI['port'];
      } else {
        $this->_port = ($this->_scheme === 'ldaps') ? 636 : 389 ;
      }   

      $this->_useTLS = defined('LDAP_AUTH_USETLS') ? LDAP_AUTH_USETLS : FALSE;

      $this->_allowUntrustedCerts = defined('LDAP_AUTH_ALLOW_UNTRUSTED_CERT') ?
        LDAP_AUTH_ALLOW_UNTRUSTED_CERT : FALSE;

      $this->_schemaCacheEnable= defined('LDAP_AUTH_SCHEMA_CACHE_ENABLE') ?
        LDAP_AUTH_SCHEMA_CACHE_ENABLE : TRUE;

      $this->_schemaCacheTimeout= defined('LDAP_AUTH_SCHEMA_CACHE_TIMEOUT') ?
        LDAP_AUTH_SCHEMA_CACHE_TIMEOUT : 86400;

      $this->_logAttempts= defined('LDAP_AUTH_LOG_ATTEMPTS') ?
        LDAP_AUTH_LOG_ATTEMPTS : FALSE;

      $this->_ldapLoginAttrib = defined('LDAP_AUTH_LOGIN_ATTRIB') ?
        LDAP_AUTH_LOGIN_ATTRIB : null;

			
      /**
      Building LDAP connection
      **/

      $ldapConnParams=array(
		'host'=> $this->_scheme.'://'.$this->_host,
		'options' => array('LDAP_OPT_REFERRALS' => 0),
      	'basedn'=> $this->_baseDN,
        'port' => $this->_port,
        'starttls' => $this->_useTLS
			);

			if (!$this->_anonBeforeBind) {
				$ldapConnParams['binddn']= $this->_serviceBindDN;
				$ldapConnParams['bindpw']= $this->_serviceBindPass;
			}
			
			if ($this->_allowUntrustedCerts) {
				putenv('LDAPTLS_REQCERT=never');
			}

      if ($this->_debugMode) $this->_log(print_r($ldapConnParams,TRUE), E_USER_NOTICE);
			$ldapConn=Net_LDAP2::connect($ldapConnParams);
 			
      if (get_class($ldapConn) !== 'Net_LDAP2') {
				$this->_log(
					'Could not connect to LDAP Server: '.$ldapConn->getMessage().' with '.$this->_getBindDNWord(), 
					E_USER_ERROR
				);
				return FALSE;
			} else {
        $this->ldapObj = $ldapConn;
        $this->_log(
          'Connected to LDAP Server: '.LDAP_AUTH_SERVER_URI. ' with '.$this->_getBindDNWord());
      }
    
			// Bind with service account if orignal connexion was anonymous
			if (($this->_anonBeforeBind) && (strlen($this->_bindDN > 0))) {
				$binding=$this->ldapObj->bind($this->_serviceBindDN, $this->_serviceBindPass);
				if (get_class($binding) !== 'Net_LDAP2') {
					$this->_log(
						'Cound not bind service account: '.$binding->getMessage(),E_USER_ERROR);
					return FALSE;
				} else {
          $this->_log('Bind with '.$this->_serviceBindDN.' successful.',E_USER_NOTICE);
        }
			} 
			
			//Cache LDAP Schema
			if ($ldapSchemaCacheEnable) {
        $this->_getSchemaCache();
			}

      //Validate BaseDN 
      $baseDNObj=$this->ldapObj->getEntry($this->_baseDN);
      if (get_class($baseDNObj) !== 'Net_LDAP2_Entry') {
        $this->_log('Cound not get LDAP_AUTH_BASEDN.  Please check config.php',E_USER_ERROR);
        //return FALSE;
      }
			
			//Searching for user
			$escapedUserName=Net_LDAP2_Util::escape_filter_value(array($login));
			$completedSearchFilter=str_replace('???',$escapedUserName[0],LDAP_AUTH_SEARCHFILTER);
			$filterObj=Net_LDAP2_Filter::parse($completedSearchFilter);
			if (get_class($filterObj) !== 'Net_LDAP2_Filter') {
				$this->_log( 'Could not parse LDAP Search filter', E_USER_ERROR);
				return FALSE;
			}
      if ($this->_debugMode) $this->_log(
        "Seaching for user $login with this query ".$filterObj->asString().' within '.$this->_baseDN);
			$searchResults=$this->ldapObj->search($this->_baseDN, $filterObj);
			if (get_class($searchResults) !== 'Net_LDAP2_Search') {
				$this->_log('LDAP Search Failed: '.$searchResults->getMessage(),E_USER_ERROR);
				return FALSE;
			} elseif ($searchResults->count() === 0) {
				$this->_log((string)$login, 'Unknown User',	E_USER_NOTICE);
				return FALSE;
			} elseif ($searchResults->count() > 1 ) {
				$this->_log('Multiple DNs found for username '.(string)$login, E_USER_WARNING);
				return FALSE;
			}
			//Getting user's DN from search
			$userEntry=$searchResults->shiftEntry();
			$userDN=$userEntry->dn();
			//Binding with user's DN. 
			if ($this->_debugMode) $this->_log('Try to bind with user\'s DN: '.$userDN);
			$loginAttempt=$this->ldapObj->bind($userDN, $password);
			if ($loginAttempt === TRUE) {
				$this->_log('User: '.(string)$login.' authentication successful');
				if (strlen($this->_ldapLoginAttrib) > 0) {
          if ($this->_debugMode) $this->_log('Looking up TT-RSS username attribute in '.$this->_ldapLoginAttrib);
          $ttrssUsername=$userEntry->getValue($this->_ldapLoginAttrib,'single');
          $this->ldapObj->disconnect();
          if (!is_string($ttrssUsername)) {
            $this->_log('Could not find user name attribute '.$this->_ldapLoginAttrib.' in LDAP entry', E_USER_WARNING);
            return FALSE;
          } 
          return $this->base->auto_create_user($ttrssUsername);
        } else {
          $this->ldapObj->disconnect();
          return $this->base->auto_create_user($login);
        }
			} elseif ($loginAttempt->getCode() == 49) {
        $this->ldapObj->disconnect();
				$this->_log('User: '.(string)$login.' authentication failed');
				return FALSE;
			} else {
        $this->ldapObj->disconnect();
				$this->_log('Unknown Error: Code: '.$loginAttempt->getCode().
					' Message: '.$loginAttempt->getMessage().' user('.(string)$login.')',E_USER_WARNING);
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
