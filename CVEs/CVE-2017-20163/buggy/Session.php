<?php
mb_internal_encoding('UTF-8');

/**
 * class 'Session'
 * This uses static functions, but is constructed at a specific time.
 * requires tables session and sessiondata
 * cf. http://php.net/manual/en/class.sessionhandlerinterface.php
 * We are currently ignoring the following php.ini session.* configuration settings.
 * cf. http://php.net/manual/en/session.configuration.php
 */
class Session extends Singleton {
	private static $tom = null;            // timeout minutes
	private static $sqlsess = null;
	private static $apache_cookie = null;    // This is the session cookie or '[new session]'- used for session-only variables.
	private static $registry;
	private static $session;
	private static $cookieLife = null;

	/*
	 * set timeout (in minutes)
	 * this is traditionally set using a php.ini value session.gc_lifetime
	 */
	public static function setto($tom_p = 1440, int $cookieLife = 8640000) {
		static::$tom = Settings::$sql->escape_string($tom_p);
		static::$cookieLife = $cookieLife > 0 ? time() + $cookieLife : 0;
		Settings::$sql->query("delete from sio_session where TIMESTAMPADD(MINUTE," . static::$tom . ",ts) < CURRENT_TIMESTAMP");
	}

	public static function mutate() {
		if (isset($_COOKIE["xsession"])) {
			$session = static::$session;
			$code = @$_SERVER['REMOTE_ADDR'] . @$_SERVER['SSL_SESSION_ID'] . "_wxf9[9]Z(9.2)";
			$vector = $_SERVER['SCRIPT_URI'] . "37b807ea4118db8d";
			$mutated = hash('sha256', openssl_encrypt(gzdeflate($session), "aes-256-cbc", $code, OPENSSL_RAW_DATA, substr($vector, 0, 16)));

			Settings::$sql->query("update sio_session set id='$mutated' where id='$session'");
			Settings::$sql->query("update sio_sessiondata set sid='$mutated' where sid='$session'");
			static::$sqlsess = $mutated;
			static::$session = $mutated;
			setcookie("xsession", static::$session, static::$cookieLife, '/', '', true, true); // 8640000 = 100 days
		}
	}

	/**
	 * has (with no name = check for session) - otherwise, check for session variable
	 */
	public static function has($name = null) {
		$retVal = false;
		if (!empty(static::$session)) {
			if (!empty($name)) {
				$registry = static::getRegistry();
				$retVal = isset($registry[$name]);
			} else {
				$retVal = true;
			}
		}
		return $retVal;
	}

	public static function get($name = null) {
		$retVal = false;
		if (!empty(static::$session)) {
			if (!is_null($name)) {
				$registry = static::getRegistry();
				if (static::has($name)) {
					$retVal = $registry[$name];
				}
			} else {
				$retVal = static::$session;
			}
		}
		return $retVal;
	}

	public static function set($name = null, $val = null, $session_only = false) {
		$retVal = false;
		if (!empty(static::$session) && !is_null($name)) {
			$sqlnam = Settings::$sql->escape_string($name);
			$sonly = $session_only ? "'" . static::$apache_cookie . "'" : "NULL";
			$value = is_null($val) ? "NULL" : "'" . Settings::$sql->escape_string($val) . "'";
			Settings::$sql->query("replace into sio_sessiondata (sid,name,value,session) values ('" . static::$sqlsess . "','{$sqlnam}',{$value},{$sonly})");
			static::$registry[$name] = $val;
		}
		return $retVal;
	}

	private static function getRegistry() {
		if (is_null(static::$registry)) {
			static::$registry = [];
			$statement = Settings::$sql->prepare("select name,value from sio_sessiondata where sid=? and (session is NULL or session=? or session='_NEW')");
			if ($statement !== false) {
				$statement->bind_param("ss", static::$sqlsess, static::$apache_cookie);
				$statement->bind_result($name, $value);
				$statement->execute();
				while ($statement->fetch()) {
					static::$registry[$name] = $value;
				}
			}
		}
		return static::$registry;
	}

	public static function del($nam = null) {
		if (!empty(static::$session)) {
			if (!is_null($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				$query = "delete from sio_sessiondata where sid='" . static::$sqlsess . "' and name='" . $sqlname . "'";
				Settings::$sql->query($query);
				unset(static::$registry[$nam]);
			} else {
				Settings::$sql->query("delete from sio_session where id='" . static::$sqlsess . "'");
				Settings::$sql->query("delete from sio_sessiondata where sid='" . static::$sqlsess . "'");
				static::resetRegistry();
			}
		}
	}

	/**
	 * 'fresh'
	 * Returns a boolean. Tests to see if the current session is a new session, or a recovered session..
	 */
	public static function fresh() {
		$retval = false;
		if ((!isset($_COOKIE["session"])) || (static::$session === $_COOKIE["session"])) {
			$retval = true;
		}
		return $retval;
	}

	/**
	 * '__construct'
	 * Set / manage the session cookie and it's correlating data-record.
	 */
	protected function __construct() {
	}

	public static function start($override = false) {
		static::resetRegistry();
		if (isset($_COOKIE["session"])) {
			static::$apache_cookie = Settings::$sql->escape_string($_COOKIE["session"]);
		} else {
			static::$apache_cookie = "_NEW";
		}
		if (!isset($_COOKIE["xsession"]) || $override) {
			if (!isset($_COOKIE["session"]) || $override) {
				$session_id = getenv("UNIQUE_ID");
				if (!$session_id) {
					$session_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
					  mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
					  mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
					  mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
					);
				}
				static::$session = md5($session_id);
			} else {
				static::$session = $_COOKIE["session"];
			}
			if (empty($_SERVER['HTTPS'])) {
				setcookie("xsession", static::$session, static::$cookieLife, '/', '', false, true);
			} else {
				//possibly don't need this.
				setcookie("xsession", static::$session, static::$cookieLife, '/', '', true, true);
			}
		} else {
			static::$session = $_COOKIE["xsession"];
		}
		if (!empty(static::$session)) {
			static::$sqlsess = Settings::$sql->escape_string(static::$session);
			Settings::$sql->query("delete sio_sessiondata from sio_sessiondata left join sio_session on sid=id where id is null");
			Settings::$sql->query("replace into sio_session set id='" . static::$sqlsess . "'");
		}
		static::tidy_session();
	}

	/**
	 * Returns the time to live in minutes for the current session
	 *
	 * @param int $minutes
	 * @return int
	 */
	public static function ttl(int $minutes = 1440): int {

		$session = @$_COOKIE["xsession"];
		$ttl = 0;
		$seconds = $minutes * 60;

		if (!is_null($session)) {
			$statement = Settings::$sql->prepare("select TIMESTAMPDIFF(SECOND,NOW(),(ts + INTERVAL ? SECOND)) from sio_session where id=?");
			$statement->bind_param("is", $seconds, $session);
			$statement->execute();
			$statement->bind_result($ttl);
			$statement->fetch();
		}

		return $ttl ?? 0;
	}

	/**
	 * 'set cookie as found.'
	 */
	private static function tidy_session() {
		if ((static::$apache_cookie === "_NEW") && isset($_COOKIE["session"])) {
			static::$apache_cookie = Settings::$sql->escape_string($_COOKIE["session"]);
			Settings::$sql->query("update sio_sessiondata set session='" . static::$apache_cookie . "' where sid='" . static::$sqlsess . "' and session='_NEW'");
		}
	}

	private static function resetRegistry() {
		static::$registry = null;
	}

}
