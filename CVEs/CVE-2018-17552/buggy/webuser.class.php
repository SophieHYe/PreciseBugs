<?php

class webuser
{
	public $id;
	public $website;
	public $username;
	public $password;
	public $email;
	public $email_verification_date;
    public $groups;
	public $fullname;
	public $gender; // male / female / company / (empty)
	public $avatar;
	public $birthdate;
	public $language; // ISO 639-1 (2 chars) (en => English, es => Español)
	public $country; // ISO-3166-1993 (US => United States of America, ES => Spain)
	public $timezone; // PHP5 Timezone Code (, "Europe/Madrid")
	public $address;
	public $zipcode;
	public $location;
	public $phone;
	public $social_website;
	public $joindate;
    public $lastseen;
	public $newsletter;
    public $private_comment;
	public $activation_key;
	public $cookie_hash;
	public $access; // 0: allowed, 1 => blocked, 2 => allowed within a date range
	public $access_begin;   // timestamp, 0 => infinite
	public $access_end; // timestamp, 0 => infinite

    public $properties;
  	
	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_webusers WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_by_hash($hash)
	{
		global $DB;
		global $session;
        global $events;

        $ok = $DB->query('SELECT * FROM nv_webusers WHERE cookie_hash = '.protect($hash));
        if($ok)
            $data = $DB->result();

        if(!empty($data))
		{
			$this->load_from_resultset($data);

			// check if the user is still allowed to sign in
			$blocked = 1;
			if( $this->access == 0 ||
                ( $this->access == 2 &&
                    ($this->access_begin==0 || $this->access_begin < time()) &&
                    ($this->access_end==0 || $this->access_end > time())
                )
            )
			{
				$blocked = 0;
			}

			if($blocked==1)
				return false;

			$session['webuser'] = $this->id;

            // maybe this function is called without initializing $events
            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'webuser',
                    'sign_in',
                    array(
                        'webuser' => $this,
                        'by' => 'cookie'
                    )
                );
            }
		}

	}

    public function load_by_profile($network, $network_user_id)
    {
        global $DB;
        global $session;

        // the profile exists (connected to a social network)?
        $swuser = $DB->query_single(
            'webuser',
            'nv_webuser_profiles',
            ' network = '.protect($network).' AND '.
            ' network_user_id = '.protect($network_user_id)
        );

        if(!empty($swuser))
            $this->load($swuser);
    }

    public function load_from_resultset($rs)
	{
		$main = $rs[0];	
		
		$this->id      		= $main->id;		
		$this->website      = $main->website;
		$this->username		= $main->username;
		$this->password		= $main->password;
   		$this->email	    = $main->email;    
   		$this->email_verification_date  = $main->email_verification_date;
		$this->fullname		= $main->fullname;
		$this->gender		= $main->gender;		
		$this->avatar		= $main->avatar;		
		$this->birthdate	= $main->birthdate;
		$this->language		= $main->language;	
		$this->country		= $main->country;
		$this->timezone		= $main->timezone;
		$this->address		= $main->address;
		$this->zipcode		= $main->zipcode;
		$this->location		= $main->location;
		$this->phone		= $main->phone;	
		$this->social_website   = $main->social_website;
        $this->joindate		= $main->joindate;
        $this->lastseen		= $main->lastseen;
		$this->newsletter	= $main->newsletter;
		$this->private_comment	= $main->private_comment;
		$this->activation_key	= $main->activation_key;
		$this->cookie_hash	= $main->cookie_hash;
		$this->access		= $main->access;
		$this->access_begin	= $main->access_begin;
		$this->access_end	= $main->access_end;

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups = explode(',', $groups);
        if(!is_array($this->groups))  $this->groups = array($groups);
	}
	
	public function load_from_post()
	{
		//$this->website      = $_REQUEST['webuser-website'];
		$this->username		= trim($_REQUEST['webuser-username']);
		if(!empty($_REQUEST['webuser-password']))
			$this->set_password($_REQUEST['webuser-password']);			
   		$this->email	    = trim($_REQUEST['webuser-email']);
   		$this->groups	    = $_REQUEST['webuser-groups'];
		$this->fullname		= $_REQUEST['webuser-fullname'];
		$this->gender		= $_REQUEST['webuser-gender'][0];		
		$this->avatar		= $_REQUEST['webuser-avatar'];		
		if(!empty($_REQUEST['webuser-birthdate']))
			$this->birthdate	= core_date2ts($_REQUEST['webuser-birthdate']);
		else
			$this->birthdate	= '';
		$this->language		= $_REQUEST['webuser-language'];			
		$this->newsletter	= ($_REQUEST['webuser-newsletter']=='1'? '1' : '0');
		$this->access		= $_REQUEST['webuser-access'];
		$this->access_begin	= (empty($_REQUEST['webuser-access-begin'])? '' : core_date2ts($_REQUEST['webuser-access-begin']));
		$this->access_end	= (empty($_REQUEST['webuser-access-end'])? '' : core_date2ts($_REQUEST['webuser-access-end']));


		$this->country		= $_REQUEST['webuser-country'];
		$this->timezone		= $_REQUEST['webuser-timezone'];
		$this->address		= $_REQUEST['webuser-address'];
		$this->zipcode		= $_REQUEST['webuser-zipcode'];
		$this->location		= $_REQUEST['webuser-location'];
		$this->phone		= $_REQUEST['webuser-phone'];			
		$this->social_website = $_REQUEST['webuser-social_website'];
		$this->private_comment = $_REQUEST['webuser-private_comment'];

        // social profiles is a navigate cms private field
	}
	
	
	public function save($trigger_webuser_modified=true)
	{
		if(!empty($this->id))
		  return $this->update($trigger_webuser_modified);
		else
		  return $this->insert();
	}
	
	public function delete()
	{
		global $DB;
        global $events;

		if(!empty($this->id))
		{
            // remove all social profiles
            $DB->execute('
 				DELETE FROM nv_webuser_profiles
				 WHERE webuser = '.intval($this->id)
            );

            // remove properties
            property::remove_properties('webuser', $this->id);

            // remove grid notes
            grid_notes::remove_all('webuser', $this->id);

            // finally remove webuser account
            $DB->execute('
 				DELETE FROM nv_webusers
				 WHERE id = '.intval($this->id).'
              	 LIMIT 1 '
			);

            $events->trigger(
                'webuser',
                'delete',
                array(
                    'webuser' => $this
                )
            );
		}

		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;	
		global $website;
        global $events;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		$ok = $DB->execute(' 
		    INSERT INTO nv_webusers
                (	id, website, username, password, email, groups, fullname, gender, avatar, birthdate,
                    language, country, timezone, address, zipcode, location, phone, social_website,
                    joindate, lastseen, newsletter, private_comment, activation_key, cookie_hash, 
                    access, access_begin, access_end, email_verification_date
                )
                VALUES 
                (
                    :id, :website, :username, :password, :email, :groups, :fullname, :gender, :avatar, :birthdate,
                    :language, :country, :timezone, :address, :zipcode, :location, :phone, :social_website,
                    :joindate, :lastseen, :newsletter, :private_comment, :activation_key, :cookie_hash, 
                    :access, :access_begin, :access_end, :email_verification_date
                )',
            array(
                ":id" => 0,
                ":website" => $website->id,
                ":username" => is_null($this->username)? '' : $this->username,
                ":password" => is_null($this->password)? '' : $this->password,
                ":email" => is_null($this->email)? '' : strtolower($this->email),
                ":groups" => $groups,
                ":fullname" => is_null($this->fullname)? '' : $this->fullname,
                ":gender" => is_null($this->gender)? '' : $this->gender,
                ":avatar" => is_null($this->avatar)? '' : $this->avatar,
                ":birthdate" => value_or_default($this->birthdate, 0),
                ":language" => is_null($this->language)? '' : $this->language,
                ":country" => is_null($this->country)? '' : $this->country,
                ":timezone" => is_null($this->timezone)? '' : $this->timezone,
                ":address" => is_null($this->address)? '' : $this->address,
                ":zipcode" => is_null($this->zipcode)? '' : $this->zipcode,
                ":location" => is_null($this->location)? '' : $this->location,
                ":phone" => is_null($this->phone)? '' : $this->phone,
                ":social_website" => is_null($this->social_website)? '' : $this->social_website,
                ":joindate" => core_time(),
                ":lastseen" => 0,
                ":newsletter" => is_null($this->newsletter)? '0' : $this->newsletter,
                ":private_comment" => is_null($this->private_comment)? '' : $this->private_comment,
                ":activation_key" => is_null($this->activation_key)? '' : $this->activation_key,
                ":cookie_hash" => is_null($this->cookie_hash)? '' : $this->cookie_hash,
				":access" => value_or_default($this->access, 0),
                ":access_begin" => value_or_default($this->access_begin, 0),
                ":access_end" => value_or_default($this->access_end, 0),
	            ":email_verification_date" => value_or_default($this->email_verification_date, 0)
            )
        );							
				
		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

        $events->trigger(
            'webuser',
            'save',
            array(
                'webuser' => $this
            )
        );

        $this->new_webuser_notification();
		
		return true;
	}	
	
	public function update($trigger_webuser_modified=true)
	{
		global $DB;
        global $events;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		$ok = $DB->execute('
		    UPDATE nv_webusers
                SET
                  website = :website,
                  username = :username,
                  password = :password,
                  email = :email,
                  groups = :groups,
                  fullname = :fullname,
                  gender = :gender,
                  avatar = :avatar,
                  birthdate = :birthdate,
                  language = :language,
                  lastseen = :lastseen,
                  country = :country,
                  timezone = :timezone,
                  address = :address,
                  zipcode = :zipcode,
                  location = :location,
                  phone	= :phone,
                  social_website = :social_website,
                  newsletter = :newsletter,
                  private_comment = :private_comment,
                  activation_key = :activation_key,
                  cookie_hash = :cookie_hash,
                  access = :access,
                  access_begin = :access_begin,
                  access_end = :access_end,
                  email_verification_date = :email_verification_date
                WHERE id = :id
            ',
            array(
                ':website' => $this->website,
                ':username' => $this->username,
                ':password' => $this->password,
                ':email' => $this->email,
                ':groups' => $groups,
                ':fullname' => $this->fullname,
                ':gender' => value_or_default($this->gender, ""),
                ':avatar' => $this->avatar,
                ':birthdate' => value_or_default($this->birthdate, 0),
                ':language' => value_or_default($this->language, ""),
                ':lastseen' => $this->lastseen,
                ':country' => $this->country,
                ':timezone' => $this->timezone,
                ':address' => $this->address,
                ':zipcode' => $this->zipcode,
                ':location' => $this->location,
                ':phone'	=> $this->phone,
                ':social_website' => value_or_default($this->social_website, ""),
                ':newsletter' => value_or_default($this->newsletter, 0),
                ':private_comment' => value_or_default($this->private_comment, ""),
                ':activation_key' => value_or_default($this->activation_key, ""),
                ':cookie_hash' => value_or_default($this->cookie_hash, ""),
                ":access" => value_or_default($this->access, 0),
                ":access_begin" => value_or_default($this->access_begin, 0),
                ":access_end" => value_or_default($this->access_end, 0),
                ':id' => $this->id,
	            ':email_verification_date' => value_or_default($this->email_verification_date, 0)
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());

        if($trigger_webuser_modified)
        {
            $events->trigger(
                'webuser',
                'save',
                array(
                    'webuser' => $this
                )
            );
        }

		return true;
	}

	public function access_allowed()
	{
		// check if the user is still allowed to sign in
		if( $this->access == 0 ||
            ( $this->access == 2 &&
                ($this->access_begin==0 || $this->access_begin < time()) &&
                ($this->access_end==0 || $this->access_end > time())
            )
        )
		{
			return true;
		}

		return false;
	}

	
	public function authenticate($website, $username, $password)
	{
		global $DB;
        global $events;
		
		$username = trim($username);
		$username = mb_strtolower($username);
				
		$A1 = md5($username.':'.APP_REALM.':'.$password);

        $website_check = '';
		if($website > 0)
			$website_check = 'AND website  = '.protect($website);

		if($DB->query('SELECT * 
						 FROM nv_webusers 
						WHERE ( access = 0 OR
						 		(access = 2 AND 
						 			(access_begin = 0 OR access_begin < '.time().') AND 
						 			(access_end = 0 OR access_end > '.time().') 
					            )
					           )
						  '.$website_check.'
						  AND LOWER(username) = '.protect($username))
		)
		{		
			$data = $DB->result();

			if(!empty($data))
			{
				if($data[0]->password==$A1)
				{
					$this->load_from_resultset($data);

	                // maybe this function is called without initializing $events
	                if(method_exists($events, 'trigger'))
	                {
	                    $events->trigger(
	                        'webuser',
	                        'sign_in',
	                        array(
	                            'webuser' => $this,
	                            'by' => 'authenticate'
	                        )
	                    );
	                }

					return true;
				}
			}
		}
		
		return false;		
	}

	public function authenticate_by_email($website, $email, $password)
    {
        global $DB;

        // find the webuser username assigned to an email address
        // because it may exist more than one account with the same email,
        // only the first _created_ will be used

        $username = $DB->query_single(
            'username',
            'nv_webusers',
            'website = '.intval($website).' AND email = '.protect($email)
        );

        if(empty($username))
            return false;

        return $this->authenticate($website, $username, $password);
    }

	public function check_password($password)
    {
        $match = ($this->password ==  md5(mb_strtolower($this->username).':'.APP_REALM.':'.$password));
        return $match;
    }
	
	public function set_password($newpass)
	{
		$this->password = md5(mb_strtolower($this->username).':'.APP_REALM.':'.$newpass);
	}
	
	public function set_cookie()
	{
		global $session;
		global $website;

		$session['webuser'] = $this->id;
		$this->cookie_hash = sha1(rand(1, 9999999));
		$this->update();

        $cookie_domain = $website->domain;
        if(!empty($website->subdomain))
            $cookie_domain = $website->subdomain.'.'.$cookie_domain;

		setcookie('webuser', $this->cookie_hash, time()+60*60*24*365, '/', $cookie_domain); // 365 days
	}
	
	public static function unset_cookie()
	{
		global $session;
		global $website;
        global $events;

        $webuser_sign_out_id = $session['webuser'];
        $session['webuser'] = '';

        $cookie_domain = $website->domain;
        if(!empty($website->subdomain))
            $cookie_domain = $website->subdomain.'.'.$cookie_domain;

        setcookie('webuser', NULL, -1, '/', $cookie_domain);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'webuser',
                'sign_out',
                array(
                    'webuser_id' => $webuser_sign_out_id
                )
            );
        }
    }

	public static function email_verification($email, $hash)
	{
		global $DB;

		$status = false;

		if(strpos($hash, "-") > 0)
        {
            list($foo, $expiry) = explode("-", $hash);
            if(time() > $expiry)
            {
                // expired unconfirmed account!
                return $status;
            }
        }

		$DB->query('
			SELECT id, activation_key
			  FROM nv_webusers
			 WHERE email = '.protect($email).'
			   AND activation_key = '.protect($hash).'
		');
		$rs = $DB->first();

		if(!empty($rs->id))
		{
			$wu = new webuser();
			$wu->load($rs->id);

			// access is only enabled for blocked users (access==1) which don't have a password nor an email verification date
			if($wu->access==1 && empty($wu->password) && empty($wu->email_verification_date))
			{
				// email is confirmed through a newsletter subscribe request
				$wu->email_verification_date = time();
				$wu->access = 0;
				$wu->activation_key = "";
				$status = $wu->save();
			}
		}

		return $status;
	}

	public static function account_verification($email, $hash)
	{
		global $DB;

		$status = false;

        if(strpos($hash, "-") > 0)
        {
            list($foo, $expiry) = explode("-", $hash);
            if(time() > $expiry)
            {
                // expired unconfirmed account!
                return $status;
            }
        }

		$DB->query('
			SELECT id, activation_key
			  FROM nv_webusers
			 WHERE email = '.protect($email).'
			   AND activation_key = '.protect($hash).'
		');
		$rs = $DB->first();

		if(!empty($rs->id))
		{
			$wu = new webuser();
			$wu->load($rs->id);

			// access is only enabled for blocked users (access==1) which already HAVE a password assigned
			if($wu->access==1 && !empty($wu->password))
			{
				// account is confirmed!
                if(empty($wu->email_verification_date)) // maybe the email was already verified by a previous newsletter subscription ;)
				    $wu->email_verification_date = time();
				$wu->access = 0;
				$wu->activation_key = "";
				$status = $wu->save();
			}
		}

		if(!$status)
		    return $status;
		else
		    return $wu->id;
	}

	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'LOWER(username)' . mb_strtolower($like);
		$cols[] = 'email' . $like;
		$cols[] = 'fullname' . $like;		
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	

    public static function social_network_profile_update($network, $network_user_id, $extra='', $data=array())
    {
        global $DB;
        global $webuser;
        global $website;

        $already_updated = false;

        if(is_array($extra))
            $extra = serialize($extra);

        // the profile exists?
        $swuser = $DB->query_single(
            'webuser',
            'nv_webuser_profiles',
            ' network = '.protect($network).' AND '.
            ' network_user_id = '.protect($network_user_id)
        );

        // the webuser already exists or is logged in?
        $wuser = new webuser();

        if(!empty($webuser->id))
        {
            // an existing webuser is already signed in, but we don't have his/her social profile
            if(empty($swuser))
            {
                $DB->execute('
                    INSERT nv_webuser_profiles
                        (id, network, network_user_id, webuser, extra)
                    VALUES
                       (    0, :network, :network_user_id, :webuser, :extra     )',
	                array(
		                'network' => $network,
		                'network_user_id' => $network_user_id,
		                'webuser' => $webuser->id,
		                'extra' => $extra
	                )
                );
            }

            $wuser->load($webuser->id);
        }
        else
        {
            // there is no webuser logged in, it's a new user!
            if(empty($swuser))
            {
                // and we don't have any social profile that matches the one used to sign in
                // Example: signed in with Facebook without having a previous webuser account in the current website
                $wuser->website = $website->id;
                $wuser->joindate = core_time();
                $wuser->lastseen = core_time();
                $wuser->access = 0;
                foreach ($data as $field => $value)
                {   $wuser->$field = $value;    }
                $already_updated = true;

                $wuser->insert();

	            $DB->execute('
                    INSERT nv_webuser_profiles
                        (id, network, network_user_id, webuser, extra)
                    VALUES
                       (    0, :network, :network_user_id, :webuser, :extra     )',
	                array(
		                'network' => $network,
		                'network_user_id' => $network_user_id,
		                'webuser' => $wuser->id,
		                'extra' => $extra
	                )
                );
            }
            else
            {
                // BUT we have a social profile matching a previous webuser in database
                // Ex. Signed in with Facebook having a webuser account previously
                $wuser->load($swuser);
            }
        }

        if(!$already_updated)
        {
            // either way, now we have a webuser account that we need to update
            foreach ($data as $field => $value)
                $wuser->$field = $value;

            $wuser->update();
        }

        return $wuser->id;
    }

	public static function available($username, $website_id)
	{
		global $DB;
		
		// remove spaces and make username lowercase (only to compare case insensitive)
		$username = trim($username);
		$username = mb_strtolower($username);
	
		$data = NULL;
		if($DB->query('SELECT COUNT(*) as total
					   FROM nv_webusers 
					   WHERE LOWER(username) = '.protect($username).'
					   	 AND website = '.$website_id))
		{
			$data = $DB->first();
		}
		
		return ($data->total <= 0);
	}

    public function property($property_name, $raw=false)
    {
        global $theme;

        // load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('webuser', $theme->name, 'webuser', $this->id);

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
            {
                if($raw)
                    $out = $this->properties[$p]->value;
                else
                    $out = $this->properties[$p]->value;

                break;
            }
        }

        return $out;
    }

    public function property_definition($property_name)
    {
        global $theme;

        // load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('webuser', $theme->name, 'webuser', $this->id);

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
            {
                $out = $this->properties[$p];
                break;
            }
        }

        return $out;
    }

    public function property_exists($property_name)
    {
        global $theme;

        // load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('webuser', $theme->name, 'webuser', $this->id);

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
                return true;
        }
        return false;
    }

    public function new_webuser_notification()
    {
        global $website;

        // notify about the new webuser account,
        // only if the current user is not logged in Navigate CMS
        if (empty($_SESSION['APP_USER#' . APP_UNIQUE]))
        {
            $subject = $website->name . ' | ' . t(661, 'New web user signed up') . ' [' . $this->username . ']';

            $body = navigate_compose_email(
                array(
                    array(
                        'title'   => t(177, "Website"),
                        'content' => '<a href="' . $website->absolute_path() . $website->homepage() . '">' . $website->name . '</a>'
                    ),
                    array(
                        'title'   => "ID (".t(647,"Webuser").")",
                        'content' => $this->id
                    ),
                    array(
                        'title'   => t(1, "User"),
                        'content' => value_or_default($this->username, "&nbsp;")
                    ),
                    array(
                        'title'   => t(44, "E-Mail"),
                        'content' => value_or_default($this->email, "&nbsp;")
                    ),
                    array(
                        'title'   => t(159, "Name"),
                        'content' => value_or_default($this->fullname, "&nbsp;")
                    ),
                    array(
                        'title'   => t(249, "Newsletter"),
                        'content' => $this->newsletter ? "&#x2714;" : "&mdash;"
                    ),
                    array(
                        'footer' => '<a href="' . NAVIGATE_URL . '?fid=webusers&act=edit&id=' . $this->id . '">' .
                            t(170, 'Edit') .
                            '</a>'
                    )
                )
            );

            navigate_send_email($subject, $body);
        }
    }

    public static function remove_old_unconfirmed_accounts()
    {
        global $DB;
        global $website;

        $ok = false;

        $DB->query('
            SELECT ex.id 
            FROM (  
                    SELECT id, activation_key, SUBSTRING_INDEX(activation_key, "-", -1) AS expiration_time
                    FROM nv_webusers
                    WHERE website = ' . protect($website->id) . '
                      AND access = 1
                      AND activation_key != ""
                  ) ex
            WHERE ex.activation_key <> ex.expiration_time
              AND '.time().' > ex.expiration_time
        ');

        $rs = $DB->result('id');
        if(!empty($rs))
        {
            $ok = $DB->execute('
                DELETE FROM nv_webusers wu 
                WHERE wu.id IN ('.implode(",", $rs).')        
            ');
        }

        if($ok)
            return count($rs);
        else
            return 0;
    }

    public static function export($type='csv')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
            SELECT id, website, username, email, groups, fullname, gender,
                '/*avatar,*/.'
                birthdate, language, country, timezone,
                address, zipcode, location, phone, social_website,
                joindate, lastseen, newsletter, private_comment, 
                access, access_begin, access_end
            FROM nv_webusers
            WHERE website = '.protect($website->id), 'array');

        $fields = array(
            "id",
            t(177, 'Website').' [NV]',
            t(1, 'User'),
            t(44, 'E-Mail'),
            t(506, 'Groups'),
            t(159, 'Name'),
            t(304, 'Gender'),
            //(246, 'Avatar'),
            t(248, 'Birthdate'),
            t(46, 'Language'),
            t(224, 'Country'),
            t(97, 'Timezone'),
            t(233, 'Address'),
            t(318, 'Zip code'),
            t(319, 'Location'),
            t(320, 'Phone'),
            t(177, 'Website'),
            t(247, 'Date joined'),
            t(563, 'Last seen'),
            t(249, 'Newsletter'),
            t(538, 'Private comment'),
            t(364, 'Access'),
            t(364, 'Access').' / '.t(623, 'Begin'),
            t(364, 'Access').' / '.t(624, 'End')
        );

        $out = $DB->result();

        $temp_file = tempnam("", 'nv_');
        $fp = fopen($temp_file, 'w');

        fputcsv($fp, $fields);

        foreach ($out as $fields)
            fputcsv($fp, $fields);

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.basename('webusers.csv'));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($temp_file));
        ob_clean();
        flush();
        fclose($fp);
        readfile($temp_file);

        @unlink($temp_file);
		
        core_terminate();
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webusers WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out['nv_webusers'] = json_encode($DB->result());

        $DB->query('SELECT nwp.* FROM nv_webuser_profiles nwp, nv_webusers nw
                    WHERE nwp.webuser = nw.id
                      AND nw.website = '.protect($website->id),
            'object');

        if($type='json')
            $out['nv_webuser_profiles'] = json_encode($DB->result());

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>