<?php
/** @package Aquarius */

/** User of the backend interface */
class db_Users extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'users';                           // table name
    public $id;                              // int(11)  not_null primary_key auto_increment group_by
    public $name;                            // varchar(150)  not_null unique_key
    public $password;                        // varchar(765)  not_null multiple_key
    public $password_salt;                   // varchar(765)  
    public $status;                          // int(11)  not_null group_by
    public $adminLanguage;                   // char(6)  not_null
    public $defaultLanguage;                 // char(6)  not_null
    public $active;                          // tinyint(1)  not_null multiple_key group_by
    public $activation_permission;           // tinyint(1)  not_null group_by
    public $delete_permission;               // tinyint(1)  not_null group_by
    public $copy_permission;                 // tinyint(1)  not_null group_by
    public $last_login;                      // datetime(19)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('db_Users',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    const SUPERADMIN = 0;
    const SITEADMIN  = 1;
    const USER       = 2;

    static $status_names = array(
        self::SUPERADMIN => 'superuser',
        self::SITEADMIN  => 'siteadmin',
        self::USER       => 'user'
    );

    /** Verify user credentials and register user in session if successful.
      * Requires fields 'backend_login', 'username' and 'password' to be set in $_REQUEST
      *   backend_login: must be set or this method won't try to authenticate
      *  @return user instance if login is successful, -1 if login failed, false if no login credentials were found.
      */
    static function authenticate() {
        if (isset($_REQUEST['backend_login'])) {
            $user = DB_DataObject::factory('users');
            $user->active = true;
            $user->name = $_REQUEST['username'];
            $user->find(true);

            // Don't look whether that user exists, so we give less timing information
            // Instead, rely only on having a matching password
            $proffered_password = $_REQUEST['password'];
            if (in_array(
                $user->password,
                self::password_hashes($proffered_password, $user->password_salt)
            )) {
                // Regenerate the session ID, unless it's an IE we're talking to which would use the old session ID to load the frame contents
                // Remove this guard once we no longer use frames
                if(!preg_match('/(?i)msie /', $_SERVER['HTTP_USER_AGENT'])) {
                    session_regenerate_id();
                }
                $user->login();
                
                return $user;
            } else {
                Log::warn("Failed login for user name '".$_REQUEST['username']."' from " . $_SERVER['REMOTE_ADDR'].' user-agent '.$_SERVER['HTTP_USER_AGENT']);
                return -1;
            }
        }
        return false;
    }

    /** Update the password and salt
      * @param $pass the password to set
      * This function will replace the current 'password_salt' string and 
      * 'password' hash using the strongest hash from password_hashes().
      */
    function set_password($pass) {
        $this->password_salt = uniqid();
        $this->password = end(self::password_hashes($pass, $this->password_salt));
    }

    
    /** Generate list of possible password hashes, strongest last
      *
      * @param $pass The password to generate hashes for
      * @param $salt Salt to use in hashes
      *
      * This returns an array with three entries:
      *   1. MD5 sum of $pass
      *   2. SHA1 hash of $pass with $salt appended
      *   3. 100 x Iterated SHA256 HMAC with $pass as initial key and $salt as
      *      data, iterations use the previous HMAC as key and $salt as data
      *
      * If the PHP version does not support SHA256 the third entry is omitted.
      */
    static function password_hashes($pass, $salt) {
        $possible_passwords = array();
        $possible_passwords []= md5($pass);        // Legacy
        $possible_passwords []= sha1($pass.$salt); // Fallback
        
        // Iterated sha256 HMAC will take a while to search. We'll add
        // sha512x1000 in two years or so.
        if (in_array('sha256', hash_algos())) {
            $hmac = $pass;
            for ($n=0; $n<100; $n++) $hmac = hash_hmac('sha256', $hmac, $salt);
            $possible_passwords []= $hmac;
        }
        return $possible_passwords;
    }


    
    /** Loads user instance from session if the user authenticated himself already */
    static function authenticated() {
        global $aquarius;
        $user_id = $aquarius->session_get('user');
        if ($user_id) {
            static $cache_user;
            if (!$cache_user || $cache_user->id != $user_id) {
                $user = new self();
                $user->id = $user_id;
                if ($user->find(true)) {
                    $cache_user = $user;
                } else {
                    self::logout();
                    throw new Exception("Invalid user id '$user_id' in session ".session_id());
                }
            }
            return $cache_user;
        }
        return false;
    }

    /** Mark this user as logged-in for this session */
    function login() {
        global $aquarius;
        $aquarius->db->query("UPDATE users SET last_login=UTC_TIMESTAMP() WHERE id=?", array($this->id));
        $aquarius->session_set('user', $this->id);
        Log::info("Login of user '".$this->name."' (".$this->id.") from " . $_SERVER['REMOTE_ADDR'].' user-agent '.$_SERVER['HTTP_USER_AGENT']);
    }

    /** Clear the user id from session */
    static function logout() {
        global $aquarius;
        $user_id = $aquarius->session_get('user') ;
        $aquarius->session_set('user', NULL) ;
        $user = new self();
        $user->id = $user_id;
        if ($user->find(true)) {
            Log::info("Logout of user '".$user->name."' (".$user->id.") from ip: ".$_SERVER['REMOTE_ADDR']);
        } else {
            Log::warn("Logout for invalid user id '$user_id' from ip: ".$_SERVER['REMOTE_ADDR']);
        }
    }


	/** return an array with all existing users */
	static function getUsers() {
		$user_prototype = DB_DataObject::factory('users');
		$user_prototype->find();
		
		$users = array();
					
		while ( $user_prototype->fetch() )
			$users[] = clone($user_prototype);
		
			
		return $users;
		
	}

    /** List of nodes to which this user has access permissions */
	function getNodes() {
        if (!isset($this->cached_nodes)) {
            $proto =& DB_DataObject::factory('users2nodes');
            $proto->userId = $this->id;
            $proto->find();
            $this->cached_nodes = array();
            while ($proto->fetch())
                $this->cached_nodes[$proto->nodeId] = clone $proto;
        }
        return $this->cached_nodes;
	}
	
    /** List of module-ids to which this user has access permissions */
    function getAccessableModuleIds() {
        if (!isset($this->accessible_moduleids)) {
            $u2m =& DB_DataObject::factory('users2modules');
            $u2m->userId = $this->id;
            $u2m->find();
            $result = array();
            while($u2m->fetch()) {
                $result[] = $u2m->moduleId;
            }
            $this->accessible_moduleids = $result;
        }
        return $this->accessible_moduleids;
    }
    
    /** List of modules to which this user has access permissions */
    function getAccessableModules() {
         
        if (!isset($this->accessible_modules)) {
            if($this->isSuperadmin()) {
                $this->accessible_modules=db_Modules::getModules();
            } else {
                $u2m =& DB_DataObject::factory('users2modules');
                $u2m->userId = $this->id;
                $u2m->find();
                $result = array();
                while($u2m->fetch()) {
                    $modproto = DB_DataObject::factory('modules');
                    $modproto->id=$u2m->moduleId;
                    $modproto->find(true);
                    $result[] = clone($modproto);
                }
                $this->accessible_modules = $result;
            }
        }
        return $this->accessible_modules;
    }
    
	function getAccessableLanguages() {
	    if (!isset($this->accessible_languages)) {
            $u2l =& DB_DataObject::factory('users2languages');
            $u2l->userId = $this->id;
            $u2l->find();
            $result = array();
            while ( $u2l->fetch() ) {
                $result[] = $u2l->lg;
            }
            $this->accessible_languages = $result; // Caching
        }
        return  $this->accessible_languages; // Cached
	}
	
	function getPrefsArray() {
		$prefs	=& DB_DataObject::factory('users2modules');
		$prefs->userId = $this->id;
		$prefs->find();
		
		$result	= array();
		while ( $prefs->fetch() )
			$result[$prefs->moduleId] = true;
		
		return $result;
	}
	
	function isSuperadmin() {
		return $this->status == self::SUPERADMIN;
	}
	
	function isSiteadmin() {
		return $this->status <= self::SITEADMIN;
	}
	
	function isUser() {
		return $this->status <= self::USER;
	}

    /** Remove permission settings as well */
    function delete() {
        $users2node = DB_DataObject::factory('users2nodes');
        $users2node->userId = $this->id;
        $users2node->delete();

        parent::delete();
    }

    /** List of status names visible to this user */
    function visible_status_names() {
        return array_kfilter(
            self::$status_names,
            create_function('$key', 'return $key >= '.$this->status.';')
        );
    }

    /** List of users visible from this user */
    function visible_users() {
        $user_prototype = DB_DataObject::factory('users');
        $user_prototype->whereAdd('status >= '.$this->status);
        $user_prototype->find();
        $users = array();
        while ($user_prototype->fetch()) $users[] = clone($user_prototype);
        return $users;
    }

    /** Whether the user has permission to edit the given node */
    function may_edit($node) {
        // Siteadmins may edit everything
        if ($this->isSiteadmin()) return true;

        // Check whether node is in a permitted edit range
        // In case of new nodes, the tree index is not available, so we check against its parent
        $check_against = $node;
        if (!$node->cache_right_index) $check_against = $node->get_parent();

        $user_id = $this->id;
        $edit_ranges = Cache::call("edit_ranges$this->id", function() use ($user_id) {
            // Users may edit a node if they have permission to edit that node or one of its parents
            global $aquarius;
            return $aquarius->db->mapqueryhash('node_id', '
                SELECT node.id AS node_id, node.cache_left_index AS left_index, node.cache_right_index AS right_index
                FROM node
                JOIN users2nodes ON node.id = users2nodes.nodeId
                WHERE users2nodes.userId = ?', 
                $user_id
            );
        });

        foreach($edit_ranges as $permitted_range) {
            if ( $permitted_range['left_index'] <= $check_against->cache_left_index
              && $permitted_range['right_index'] >= $check_against->cache_right_index
            ) return true;
        }
        
        return false;
    }

    /** Whether this user may activate or deactivate the given node.
      * Users must have edit permissions on the node and have activation permission. */
    function may_activate($node) {
        // Siteadmins may activate everything
        if ($this->isSiteadmin()) return true;

        // Ensure user has activation permission
        if (!$this->activation_permission) return false;

        // Let the user activate if he has edit permission
        return $this->may_edit($node);
    }


    function may_delete($node) {
        return ($this->isSiteadmin() || $this->delete_permission)
            && $this->may_edit($node);
    }

    function idstr() {
        return "$this->name ($id)";
    }
    
    /** Get last login as DateTime */
    function last_login() {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->last_login, new DateTimeZone("UTC"));
    }
}
