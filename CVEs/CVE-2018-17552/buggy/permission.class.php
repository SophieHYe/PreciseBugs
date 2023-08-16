<?php
class permission
{
    public $id;
    public $website;
    public $name;
    public $scope;
    public $function;
	public $type;
	//public $description;
    //public $dvalue;
    public $profile;
    public $user;
    public $value;

    /**
     * Load the properties of a certain permission
     *
     * @param integer $id ID of the user in the database
     */
    public function load($name, $profile_id=0, $user_id=0, $website=null)
    {
        global $DB;

        $ws_query = '';
        if(!empty($website))
            $ws_query = ' AND website = '.protect($website);

        $status = $DB->query('
            SELECT * FROM nv_permissions
             WHERE name = '.protect($name).'
               AND profile = '.intval($profile_id).'
               AND user = '.intval($user_id).
            $ws_query
        );

        if($status)
        {
            $data = $DB->result();
            $this->load_from_resultset($data[0]);
        }

        if(empty($this->name))
        {
            $definition = permission::get_definition($name);

            // NO permission set on database, create a new one using the definition
            $this->name     = $definition['name'];
            $this->website  = $website;
            $this->scope    = $definition['scope'];
            $this->function = $definition['function'];
            $this->type     = $definition['type'];
            $this->profile  = intval($profile_id);
            $this->user     = intval($user_id);
	        $this->value    = json_decode($definition['dvalue'], true);
        }

    }

    /**
     * Load the database resultset properties to this permission instance.
     *
     * @param object $rs Resultset returned by the Database class
     */
    public function load_from_resultset($rs)
    {
        $this->id           = $rs->id;
        $this->name         = $rs->name;
        $this->scope        = $rs->scope;
        $this->function     = $rs->function;
	    $this->type         = $rs->type;
        $this->profile      = $rs->profile;
        $this->user         = $rs->user;
        $this->website      = $rs->website;
        $this->value        = json_decode($rs->value, true);
    }

    /**
     * Insert or update a permission
     *
     * @return bool True if success, False otherwise
     */
    public function save()
    {
        if(empty($this->id))
            return $this->insert();
        else
            return $this->update();
    }

    /**
     * Insert the properties of a new permission into the Database
     *
     * @return boolean True if success, Exception otherwise
     */
    public function insert()
    {
        global $DB;

	    $value = "";
	    if(isset($this->value))
		    $value = json_encode($this->value);

        $ok = $DB->execute(
            'INSERT INTO nv_permissions
                (id, website, name, scope, type, function, profile, user, value)
                VALUES
                ( 0,
                  :website,
                  :name,
                  :scope,
                  :type,
                  :function,
                  :profile,
                  :user,
                  :value
                )',
            array(
                ':website'   =>  $this->website,
                ':name'      =>  value_or_default($this->name, ""),
                ':scope'     =>  value_or_default($this->scope, ""),
	            ':type'      =>  value_or_default($this->type, ""),
	            ':function'  =>  value_or_default($this->function, 0),
                ':profile'   =>  value_or_default($this->profile, 0),
                ':user'      =>  value_or_default($this->user, 0),
                ':value'     =>  $value
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        $this->id = $DB->get_last_id();

        return true;
    }

    /**
     * Update the properties of an existing permission in Database
     *
     *
     * @return boolean True if success, Exception otherwise
     */
    public function update()
    {
        global $DB;

        if(empty($this->id))
	        return false;

        $value = @$this->value;
	    if(!isset($this->value))
		    $value = "";
	    else
		    $value = json_encode($this->value);

        $ok =  $DB->execute('
            UPDATE nv_permissions
			   SET website    = :website,
			       name       = :name,
			       scope      = :scope,
			       type		  = :type,
			       function   = :function,
			       profile    = :profile,
			       user       = :user,
			       value      = :value
			 WHERE id = :id',
            array(
                ':id'        =>  $this->id,
                ':website'   =>  $this->website,
                ':name'      =>  value_or_default($this->name, ""),
                ':scope'     =>  value_or_default($this->scope, ""),
	            ':type'      =>  value_or_default($this->type, ""),
	            ':function'  =>  value_or_default($this->function, 0),
                ':profile'   =>  value_or_default($this->profile, 0),
                ':user'      =>  value_or_default($this->user, 0),
                ':value'     =>  $value
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        return true;
    }


    public static function get_definitions()
    {
        global $user;

        $definitions = array();
        $definitions['system'] = json_decode(file_get_contents(NAVIGATE_PATH.'/lib/permissions/navigatecms.json'), true);
        $definitions['functions'] = json_decode(file_get_contents(NAVIGATE_PATH.'/lib/permissions/functions.json'), true);
        $definitions['settings'] = json_decode(file_get_contents(NAVIGATE_PATH.'/lib/permissions/settings.json'), true);

        $definitions['extensions'] = array();
        $extensions = extension::list_installed();
        for($e=0; $e < count($extensions); $e++)
        {
            if(!empty($extensions[$e]['permissions']))
            {
                foreach($extensions[$e]['permissions'] as $permission)
                {
                    $definitions['extensions'][] = (array)$permission;
                }
            }
        }

        // get translations
        $translations = array();

        // if we are in Navigate CMS, user has the default language
        if(file_exists(NAVIGATE_PATH.'/lib/permissions/i18n/'.$user->language.'.json'))
        {
            $translations = @file_get_contents(NAVIGATE_PATH.'/lib/permissions/i18n/'.$user->language.'.json');
            if(!empty($translations))
                $translations = json_decode($translations, true);
        }

        foreach($definitions as $type => $list)
        {
            for($i=0; $i < count($list); $i++)
            {
                if(!empty($translations[$list[$i]['name']]))
                    $definitions[$type][$i]['description'] = $translations[$list[$i]['name']];
            }
        }

        return $definitions;
    }

    public static function get_definition($name)
    {
        global $user;

        $scopes = array('system', 'functions', 'settings', 'extensions');
        $definition = '';
        // force loading all permissions definitions (if not already done)
        $foo = $user->permission('');
        $definitions = $user->permissions['definitions'];

        foreach($scopes as $scope)
        {
            for($i=0; $i < count($definitions[$scope]); $i++)
            {
                $def = $definitions[$scope][$i];
                if($def['name']==$name)
                {
                    $definition = $def;
                    break;
                }
            }
        }

        return $definition;
    }

    public static function get_values($who='user', $object=NULL, $definitions=NULL, $ws=null)
    {
        global $DB;
        global $website;

        if(empty($ws))
            $ws = $website->id;

        // load all permission definitions: system, functions, extensions
        $scopes = array('system', 'functions', 'settings', 'extensions');

        if(empty($definitions))
            $definitions = permission::get_definitions();

        // load permissions with values set on database
        if($who=='user')
        {
            $DB->query('
                SELECT *
                FROM nv_permissions
                WHERE profile = '.protect($object->profile).'
                  AND (website = 0 OR website = '.protect($ws).')'
            );
            $permissions_profile = $DB->result();

            $DB->query('
                SELECT *
                  FROM nv_permissions
                 WHERE user = '.protect($object->id).'
                   AND (website = 0 OR website = '.protect($ws).')'
            );
            $permissions_user = $DB->result();
        }
        else if($who=='profile')
        {
            $DB->query('
                SELECT * FROM nv_permissions
                 WHERE profile = '.protect($object->id).'
                 AND (website = 0 OR website = '.protect($ws).')'
            );

            $permissions_profile = $DB->result();
            $permissions_user = array();
        }

        // now combine definitions with custom values
        $permissions = array();

        foreach($scopes as $scope)
        {
            for($i=0; $i < count($definitions[$scope]); $i++)
            {
                $def = $definitions[$scope][$i];
                $permissions[$def['name']] = (isset($def['dvalue'])? $def['dvalue'] : "");

                // search for a custom value on PROFILE permissions
                for($pp=0; $pp < count($permissions_profile); $pp++)
                {
                    if($permissions_profile[$pp]->name == $def['name'])
                    {
	                    $permissions[$def['name']] = json_decode($permissions_profile[$pp]->value, true);
                        break; // no need to look further
                    }
                }

                // search for a custom value on USER permissions
                for($pu=0; $pu < count($permissions_user); $pu++)
                {
                    if($permissions_user[$pu]->name == $def['name'])
                    {
                        $permissions[$def['name']] = json_decode($permissions_user[$pu]->value, true);
                        break; // no need to look further
                    }
                }
            }
        }

        return $permissions;
    }

    public static function update_permissions($changes=array(), $profile_id=0, $user_id=0)
    {
        if(!is_array($changes))
            return;

        foreach($changes as $key => $value)
        {
            $key = str_replace(array('[', ']'), '', $key);
            $ws = null;
            if(strpos($key, "wid") === 0)
            {
                list($ws, $key) = explode('.', $key, 2);
                $ws = str_replace("wid", "", $ws);
            }

            $permission = new permission();
            $permission->load($key, intval($profile_id), intval($user_id), $ws);
            $permission->value = $value;
            $permission->save();
        }
    }

    /**
     * Retrieve all permissions information and encode it in JSON format to do a Backup.
     *
     *
     * @param string $type Encode format for the rows, right now only "json" available
     * @return string All permissions rows of the database encoded
     */
    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_permissions', 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>