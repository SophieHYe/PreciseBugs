<?php

class extension
{
	public $id;
	public $website;
    public $title;
    public $code;
    public $definition;

    public $enabled;
    public $settings;

    public $dictionary;
	public $dictionaries;

	public function load($code)
	{
		global $DB;
        global $website;

        // retrieve extension definition from filesystem
        if(file_exists(NAVIGATE_PATH.'/plugins/'.$code.'/'.$code.'.plugin'))
            $this->definition = @json_decode(file_get_contents(NAVIGATE_PATH.'/plugins/'.$code.'/'.$code.'.plugin'));

        debug_json_error('extension: '.$code);

        $this->id = null;
        $this->website = $website->id;
        $this->title = $this->definition->title;
        $this->code = $code;
        $this->enabled = 1; // default
        $this->settings = array(); // default

        // now retrieve extension configuration for the active website
        $DB->query('
          SELECT * FROM nv_extensions
          WHERE website = '.protect($this->website).'
            AND extension = '.protect($this->code)
        );

        $row = $DB->first();

        if(!empty($row))
        {
            $this->id = $row->id;
            $this->enabled = $row->enabled;
            $this->settings = json_decode($row->settings, true);
        }
        else
        {
            // get from definition the default values for settings, if any
            if(isset($this->definition->options))
            {
                foreach ($this->definition->options as $option)
                {
                    if (isset($option->dvalue))
                    {
                        $this->settings[$option->id] = $option->dvalue;
                    }
                }
            }
        }
	}

    public function load_from_post()
    {
        global $website;

        // it can only be extension options!
        if(!empty($this->definition->options))
        {
            foreach($this->definition->options as $extension_option)
            {
                // get property info
                $property = new property();
                $property->load_from_object($extension_option, $this->settings->{$extension_option->id}, $this);

                $value = '';

                switch($property->type)
                {
                    case 'text':
                    case 'textarea':
                        // multilang
                        $value = array();
                        foreach($website->languages_list as $lang)
                            $value[$lang] = $_REQUEST['property-'.$extension_option->id.'-'.$lang];
                        break;

                    case 'link':
                        // multilang and title+link
                        $value = array();
                        foreach($website->languages_list as $lang)
                            $value[$lang] = $_REQUEST['property-'.$extension_option->id.'-'.$lang.'-link'].'##'.$_REQUEST['property-'.$extension_option->id.'-'.$lang.'-title'];
                        break;

                    case 'date':
                    case 'datetime':
                        $value = core_date2ts($_REQUEST['property-'.$extension_option->id]);
                        break;

                    case 'moption':
                        $value = implode(',', $_REQUEST['property-'.$extension_option->id]);
                        break;

                    case 'coordinates':
                        $value = $_REQUEST['property-'.$extension_option->id.'-latitude'].'#'.$_REQUEST['property-'.$extension_option->id.'-longitude'];
                        break;

                    case 'boolean':
                        $value = 0;
                        if($_REQUEST['property-'.$extension_option->id]=='1')
                            $value = 1;
                        break;

                    default:
                        // direct value
                        $value = $_REQUEST['property-'.$extension_option->id];
                }

                $this->settings[$extension_option->id] = $value;
            }
        }
    }
		
	public function save()
	{
		global $DB;
		global $events;

        $ok = false;

        $settings = $this->settings;
        $settings = json_encode($settings);

        if(empty($this->id))
        {
            $ok = $DB->execute('
                INSERT INTO nv_extensions (id, website, extension, enabled, settings)
                    VALUES(0, :website, :code, :enabled, :settings)',
                array(
                    ':website' => $this->website,
                    ':code' => $this->code,
                    ':enabled' => value_or_default($this->enabled, 0),
                    ':settings' => $settings
                )
            );
        }
        else
        {
            $ok = $DB->execute('
                UPDATE nv_extensions
                   SET enabled = :enabled, settings = :settings
                 WHERE id = :id',
                array(
                    ':enabled' => value_or_default($this->enabled, 0),
                    ':settings' => $settings,
                    ':id' => $this->id
                )
            );
        }

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'extension',
                'save',
                array(
                    'extension' => $this
                )
            );
        }

        return $ok;
	}
	
	public function delete()
	{
		global $DB;
        global $user;
        global $events;

        $ok = false;

        if($user->permission("themes.delete")=="false")
            throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));

        if(file_exists(NAVIGATE_PATH.'/plugins/'.$this->code.'/'.$this->code.'.plugin'))
        {
            core_remove_folder(NAVIGATE_PATH.'/plugins/'.$this->code);

            $ok = $DB->execute('
                DELETE FROM nv_extensions
                 WHERE id = '.protect($this->id)
            );

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'extension',
                    'delete',
                    array(
                        'extension' => $this
                    )
                );
            }
        }

        return $ok;
	
	}
	
	public function quicksearch($text)
	{

	}

    public function t($code)
    {
        global $user;
        global $session;
        global $website;
        global $DB;

        if(empty($this->dictionary))
        {
            $extension_languages = (array)$this->definition->languages;
            $file = '';

            if(!is_array($extension_languages))
				$extension_languages = array();

            // if we are in Navigate CMS, user has the default language
            // if we call this function from the website, the session has the default language
            $current_language = $session['lang'];
            if(empty($current_language) && !empty($webuser))
                $current_language = $webuser->language;

            if(empty($current_language) && !empty($user))
                $current_language = $user->language;

            foreach($extension_languages as $lcode => $lfile)
            {
                if( $lcode==@$user->language ||
                    $lcode==@$session['lang'] ||
                    empty($file)    )
                {
                    $file = $lfile;
                }
            }

            $json = '';
            if(file_exists(NAVIGATE_PATH.'/plugins/'.$this->code.'/'.$file))
                $json = @file_get_contents(NAVIGATE_PATH.'/plugins/'.$this->code.'/'.$file);

            if(!empty($json))
                $this->dictionary = (array)json_decode($json);

            // maybe we have a custom translation added in navigate / webdictionary ?
            if(!empty($website->id))
            {
                $DB->query('
                  SELECT subtype, lang, text
                    FROM nv_webdictionary
                   WHERE website = '.$website->id.'
                     AND node_type = "extension"
                     AND lang = '.protect($current_language).'
                     AND extension = '.protect($this->code)
                );
                $rs = $DB->result();

                for($r=0; $r < count($rs); $r++)
                    $this->dictionary[$rs[$r]->subtype] = $rs[$r]->text;
            }
        }

        if(is_string($code))
		{
            $out = $code;
            if(substr($out, 0, 1)=='@')  // get translation from theme dictionary
                $out = substr($out, 1);

            if(!empty($this->dictionary[$out]))
                $out = $this->dictionary[$out];
        }

        return $out;
    }


    public function get_translations()
	{
		if(empty($this->dictionaries))
		{
			$dict = array();
            if(isset($this->definition->languages))
            {
                foreach($this->definition->languages as $lcode => $lfile)
                {
                    $jarray = NULL;
                    $json = @file_get_contents(NAVIGATE_PATH.'/plugins/'.$this->code.'/'.$lfile);

                    if(!empty($json))
                        $jarray = (array)json_decode($json);

                    if(!empty($jarray))
                    {
                        foreach($jarray as $code => $text)
                        {
                            $id = count($dict) + 1;
                            $id = -$id;
                            $dict[] = array(
                                'id'		=>	$id, //.' | '.$this->name . ' | '.$code,
                                'extension'	=>	$this->code,
                                'source'    =>  'extension.'.$this->code.'.'.$code,
                                'node_id'	=>	$code,
                                'lang'		=>	$lcode,
                                'text'		=>	$text
                            );
                        }
                    }
                }
			}

			$this->dictionaries = $dict;
		}

		return $this->dictionaries;
	}


    public static function list_installed($type='', $ignore_permissions=true)
    {
        global $website;
        global $DB;
        global $user;

        $extensions = glob(NAVIGATE_PATH.'/plugins/*/*.plugin');

        $updates = @$_SESSION['extensions_updates'];
        $enabled = array();

        $DB->query('
            SELECT extension, enabled
              FROM nv_extensions
             WHERE website = '.protect($website->id),
            'array'
        );

        $rs = $DB->result();

        foreach($rs as $row)
        {
            $properties[$row['extension']] = array(
                'enabled' => intval($row['enabled'])
            );
        }

        $allowed_extensions = array();  // empty => all of them
        if(!$ignore_permissions)
        {
            if(method_exists($user, "permission"))
                $allowed_extensions = $user->permission("extensions.allowed");
        }

        for($t=0; $t < count($extensions); $t++)
        {
            $extension_json = @json_decode(@file_get_contents($extensions[$t]));
            debug_json_error($extensions[$t]); // if debug is enabled, show last json error

            $code = substr($extensions[$t], strrpos($extensions[$t], '/')+1);
            $code = substr($code, 0, strpos($code, '.plugin'));

            if(!empty($allowed_extensions) && !in_array($code, $allowed_extensions))
            {
                $extensions[$t] = null;
                continue;
            }

            if(!empty($extension_json))
            {
                $extensions[$t] = (array)$extension_json;

                if(!empty($type) && $extensions[$t]['type']!=$type)
                {
                    $extensions[$t] = '';
                    continue;
                }

                if(substr($extensions[$t]['description'], 0, 1)=='@')
                {
                    $tmp = new extension();
                    $tmp->load($code);
                    $extensions[$t]['description'] = $tmp->t($extensions[$t]['description']);
                }

                $extensions[$t]['code'] = $code;
                $extensions[$t]['update'] = ((version_compare($updates[$code], $extensions[$t]['version']) > 0)? $updates[$code] : '');

                if(isset($properties) && isset($properties[$code]))
                    $extensions[$t]['enabled'] = ($properties[$code]['enabled']===0)? '0' : '1';
                else
                    $extensions[$t]['enabled'] = '1';
            }
        }

        if(!is_array($extensions))
            $extensions = array();

        $extensions = array_filter($extensions);
        sort($extensions);

        return $extensions;
    }

    public static function latest_available()
    {
        $list = extension::list_installed();
        $post = array();

        if(!is_array($list))
            return false;

        foreach($list as $extension)
            $post[$extension['code']] = $extension['version'];

        $latest_update = core_curl_post(
            'http://update.navigatecms.com/extensions',
            array(
                'extensions' => json_encode($post)
            )
        );

        if(empty($latest_update))
            return false;

        $latest_update = json_decode($latest_update, true);

        return $latest_update;
    }

    public static function include_php($extension_code)
    {
        if(file_exists(NAVIGATE_PATH.'/plugins/'.$extension_code.'/'.$extension_code.'.php'))
            include_once(NAVIGATE_PATH.'/plugins/'.$extension_code.'/'.$extension_code.'.php');
    }

    public static function blocks()
    {
        $out = array();

        $list = extension::list_installed();

        for($e=0; $e < count($list); $e++)
        {
            if(isset($list[$e]['blocks']))
            {
                for($eb=0; $eb < count($list[$e]['blocks']); $eb++)
                {
                    $list[$e]['blocks'][$eb]->_extension = $list[$e]['code'];
                    $out[] = $list[$e]['blocks'][$eb];
                }
            }
        }

        return $out;
    }
}

?>