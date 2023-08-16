<?php

class webdictionary
{
	public $id; // unused but needed
	public $website;
	public $node_type;
	public $theme;
	public $extension;
	public $node_id;
	public $node_uid;
	public $subtype;
	public $lang;
	public $text;
	
	public $extension_name;
	
	// load a certain word from the dictionary with its translations
	public function load($id)
	{
		global $DB;
		global $website;
		global $theme;

		if(is_numeric($id))
		{
			if($DB->query('SELECT * FROM nv_webdictionary
							WHERE node_id = '.intval($id).'
							  AND node_type = "global"
							  AND website = '.intval($website->id))
            )
			{
				$data = $DB->result();
				$this->load_from_resultset($data); // there will be as many entries as languages enabled
			}
		}
		else
		{
			// id can be a theme string or a translation path (example: extension.seotab.check_url_on_facebook)
			$path = explode(".", $id, 3);
			if($path[0]=='extension')
			{
				$extension = new extension();
				$extension->load($path[1]);
				$id = $path[2];

				// $id is a theme string that may be in the database or/and the theme json dictionary
				$extension_dictionary = $extension->get_translations();

				$this->id       = $id;
				$this->node_type= 'extension';
				$this->extension= $extension->code;
				$this->extension_name = $extension->title;
				$this->node_id  = $id;
				$this->subtype	= $id;
				$this->website  = $website->id;

				$this->text = array();
				foreach($extension_dictionary as $word)
				{
					if($word['node_id']==$id)
						$this->text[$word['lang']] = $word['text'];
				}

				// we need to load the database versions of the theme strings
				// node_id is not used in database with theme strings
				$DB->query('
					SELECT lang, text
					  FROM nv_webdictionary 
					 WHERE node_type = "extension"
					   AND extension = :extension
					   AND subtype = :subtype
					   AND website = :wid',
                    'object',
                    array(
                        ':wid' => $website->id,
                        ':subtype' => $this->subtype,
                        ':extension' => $this->extension
                    )
				);

				$data = $DB->result();

				if(!is_array($data))
				{
				    $data = array();
                }
				foreach($data as $item)
                {
                    $this->text[$item->lang] = $item->text;
                }
			}
			else    // theme translation (only for the current active theme)
			{
				$id = $path[2];

				// $id is a theme string that may be in the database or/and the theme json dictionary
				$theme_dictionary = $theme->get_translations();

				$this->id       = $id;
				$this->node_type= 'theme';
				$this->node_id  = $id;
				$this->theme	= $theme->name;
				$this->subtype	= $id;
				$this->website  = $website->id;

				$this->text = array();
				foreach($theme_dictionary as $word)
				{
					if($word['node_id']==$id)
						$this->text[$word['lang']] = $word['text'];
				}

				// we need to load the database versions of the theme strings
				// node_id is not used in database with theme strings
				$DB->query('
					SELECT lang, text
					  FROM nv_webdictionary 
					 WHERE node_type = "theme"
					   AND theme = :theme 
					   AND subtype = :subtype 
					   AND website = :wid',
                    'object',
                    array(
                        ':wid' => $website->id,
                        ':subtype' => $this->subtype,
                        ':theme' => $theme->name
                    )
				);

				$data = $DB->result();

				if(!is_array($data)) $data = array();
				foreach($data as $item)
					$this->text[$item->lang] = $item->text;
			}
		}
	}
		
	public function load_from_resultset($rs)
	{
		$main = $rs[0];

		$this->website  = $main->website;
		$this->node_type= $main->node_type;
		$this->node_id  = $main->node_id;
		$this->theme	= $main->theme;
		$this->extension= $main->extension;
		$this->subtype	= $main->subtype;

		$this->text		= array();
		
		for($r=0; $r < count($rs); $r++)
		{
			$this->text[$rs[$r]->lang] = $rs[$r]->text;
		}
	}
	
	public function load_from_post()
	{
		// if node_id is empty, then is an insert
		$this->node_type 	= $_REQUEST['node_type'];
		$this->subtype 		= $_REQUEST['subtype'];
		$this->theme 		= $_REQUEST['theme']; //(is_numeric($this->node_id)? '' : $theme->name);
		$this->node_id		= $_REQUEST['node_id'];
		
		$this->text = array();
		foreach($_REQUEST as $key => $value)
		{
			if(substr($key, 0, strlen("webdictionary-text-"))=="webdictionary-text-")
				$this->text[substr($key, strlen("webdictionary-text-"))] = $value;
		}
	}
	
	public function save()
	{
		global $DB;

		// remove all old entries
        $node_id_filter = '';

		if(!empty($this->node_id))
		{
		    $query_params = array(
                ':wid' => $this->website,
                ':subtype' => $this->subtype,
                ':theme' => $this->theme,
                ':extension' => $this->extension,
                ':node_type' => $this->node_type
            );

			if(is_numeric($this->node_id))
            {
				$node_id_filter .= ' AND node_id = :node_id ';
				$query_params[':node_id'] = $this->node_id;
            }

			if(is_numeric($this->node_uid))
            {
				$node_id_filter .= ' AND node_uid = :node_uid ';
                $query_params[':node_uid'] = $this->node_uid;
            }

			$DB->execute('
				DELETE FROM nv_webdictionary 
					WHERE website = :wid 
					  AND subtype = :subtype
					  AND theme = :theme  
					  AND extension = :extension  
					  AND node_type = :node_type
					  '.$node_id_filter,
                $query_params
			);
		}
		
		// insert the new ones
		return $this->insert();			
	}
	
	public function delete()
	{
		global $DB;

        $node_filter = "";

		// remove all old entries
		if(!empty($this->node_id))
		{
		    $query_params = array(
                ':wid' => $this->website,
                ':extension' => $this->extension,
                ':theme' => $this->theme,
                ':node_type' => $this->node_type,
                ':subtype' => $this->subtype
            );

            if(is_numeric($this->node_id))
            {
                $node_filter .= ' AND node_id = :node_id ';
                $query_params[':node_id'] = $this->node_id;
            }

            if(is_numeric($this->node_uid))
            {
                $node_filter .= ' AND node_uid = :node_uid ';
                $query_params[':node_uid'] = $this->node_uid;
            }
			
			$DB->execute('
 				DELETE FROM nv_webdictionary
				WHERE subtype = :subtype
				  AND node_type = :node_type
				  AND theme = :theme 
				  AND extension = :extension 
				  AND website = :wid
				  '.$node_filter,
                $query_params
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

		if(empty($this->website))
			$this->website = $website->id;
		
		if(empty($this->node_id)) 
		{
			// we need to find what is the next node_id available for this subtype
			$tmp = $DB->query_single(
				'MAX(node_id)',
				'nv_webdictionary',
				' subtype = :subtype 
				       AND node_type = :node_type 
					   AND website = :wid',
                null,
                array(
                    ':wid' => $this->website,
                    ':subtype' => $this->subtype,
                    ':node_type' => $this->node_type
                )
			);

			$this->node_id = intval($tmp) + 1;
		}

		// one entry per language
		foreach($this->text as $lang => $text)
		{
			if(empty($text)) continue;

			$ok = $DB->execute('
 				INSERT INTO nv_webdictionary
					(id, website, node_type, node_id, node_uid, theme, extension, subtype, lang, `text`)
				VALUES
					( 0, :website, :node_type, :node_id, :node_uid, :theme, :extension, :subtype, :lang, :text)
				',
				array(
					":website" => $this->website,
					":node_type" => $this->node_type,
					":node_id" => (!empty($this->theme) || !empty($this->extension))? 0 : $this->node_id,
					":node_uid" => value_or_default($this->node_uid, 0),
					":theme" => value_or_default($this->theme, ""),
					":extension" => value_or_default($this->extension, ""),
					":subtype" => $this->subtype,
					":lang" => $lang,
					":text" => value_or_default($text, "")
				)
			);
			
			if(!$ok) throw new Exception($DB->get_last_error());
		}

		return true;
	}	
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'node_id' . $like;
		$cols[] = 'lang' . $like;
		$cols[] = 'subtype' . $like;
		$cols[] = 'text' . $like;
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}

    public static function load_element_strings($node_type, $node_id, $node_uid=null)
    {
        return webdictionary::load_object_strings($node_type, $node_id, $node_uid);
    }

	// only for strings NOT from theme dictionary 
	public static function load_object_strings($node_type, $node_id, $node_uid=null)
	{
		global $DB;

		$query_params = array(
            ':node_type' => $node_type,
            ':node_id' => $node_id
        );

		$uid_filter = '';
		if(!empty($node_uid))
        {
            $uid_filter = ' AND ( node_uid = :node_uid OR node_uid = "" OR node_uid IS NULL )';
            $query_params[':node_uid'] = $node_uid;
        }

		
		$DB->query('
			SELECT subtype, lang, text
			  FROM nv_webdictionary
			 WHERE node_type = :node_type
			   AND node_id = :node_id 
            '.$uid_filter,
            'object',
            $query_params
		);

		$data = $DB->result();

		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
			$dictionary[$item->lang][$item->subtype] = $item->text;

		return $dictionary;	
	}

	public static function save_element_strings($node_type, $node_id, $dictionary, $website_id=null, $node_uid=null)
	{
		global $DB;
		global $website;

		if(empty($website_id))
			$website_id = $website->id;

	    if(empty($node_id))
		    throw new Exception('ERROR webdictionary: No ID! ['.$node_type.']');

        if(!is_array($dictionary))
            $dictionary = array();

        if($node_type=='property-block_group_block')
        {
            // special case, remove only the rows we will insert
            foreach($dictionary as $lang => $texts)
            {
                $subtypes = array_keys($texts);

                $DB->execute('
                    DELETE FROM nv_webdictionary
                     WHERE node_type = :node_type
                       AND node_id = :node_id 
                       AND website = :wid 
                       AND lang = :lang
                       AND subtype IN (:subtype)',
                    array(
                        ':wid' => $website_id,
                        ':lang' => $lang,
                        ':node_id' => $node_id,
                        ':node_type' => $node_type,
                        ':subtype' => implode(",", array_map(function($k){ return protect($k);}, $subtypes))
                    )
                );
            }
        }
        else if($node_type=='property-block_group-extension-block')
        {
            // special case, remove only the rows we will insert
            foreach($dictionary as $lang => $texts)
            {
                $subtypes = array_keys($texts);

                $DB->execute(
                    'DELETE FROM nv_webdictionary
                         WHERE node_type = :node_type
                           AND node_id = :node_id 
                           AND website = :wid 
                           AND lang = :lang
                           AND subtype IN (:subtype)
                           AND node_uid = :node_uid',
                    array(
                        ':wid' => $website_id,
                        ':lang' => $lang,
                        ':node_id' => $node_id,
                        ':node_type' => $node_type,
                        ':subtype' => implode(",", array_map(function($k){ return protect($k);}, $subtypes)),
                        ':node_uid' => $node_uid
                    )
                );
            }
        }
        else
        {
            // first, delete old entries (deletes too much rows when updating block group block properties)
            $DB->execute('
                DELETE FROM nv_webdictionary
                 WHERE node_type = :node_type 
                   AND node_id = :node_id 
                   AND website = :wid',
                array(
                    ':wid' => $website_id,
                    ':node_type' => $node_type,
                    ':node_id' => $node_id
                )
            );
            // and then insert the new values
        }

		foreach($dictionary as $lang => $item)
		{
			foreach($item as $subtype => $litem)
			{
				// NO error checking
				$DB->execute('
	                INSERT INTO nv_webdictionary
						(id, website, node_type, theme, extension, node_id, node_uid, subtype, lang, `text`)
					VALUES
						( :id, :website, :node_type, :theme, :extension, :node_id, :node_uid, :subtype, :lang, :text)
					',
					array(
					    ":id" => 0,
						":website" => $website_id,
						":node_type" => $node_type,
						":node_id" => $node_id,
						":node_uid" => value_or_default($node_uid, ""),
						":theme" => "",
						":extension" => "",
						":subtype" => $subtype,
						":lang" => $lang,
						":text" => value_or_default($litem, "")
					)
				);
			}
		}
	}

	public static function save_translations_post($language)
	{
		global $DB;
		global $website;
		global $theme;

		$errors = array();

		foreach($_POST['data'] as $key => $text)
		{
			$object = "";
			list($language, $type, $id) = explode(".", $key, 3);
			// 0 => language
			// 1 => type (theme, extension, internal)
			// 2 => ID or name.ID   (name of the theme or extension)

			if(!is_numeric($id))
				list($object, $id) = explode(".", $id, 2);

			switch($type)
			{
				case "global":
					// remove old entry, if exists
					$DB->execute(
                    'DELETE FROM nv_webdictionary
						WHERE node_id = :id 
						  AND node_type = "global"
						  AND lang = :lang 
						  AND website = :wid
						LIMIT 1',
                        array(
                            ':wid' => $website->id,
                            ':lang' => $language,
                            ':id' => $id
                        )
                    );
					break;

				case "theme":
					// remove old entry, if exists
					$DB->execute('
		                DELETE FROM nv_webdictionary
						WHERE subtype = :id 
						  AND node_type = "theme"
						  AND theme = :object
						  AND lang = :lang 
						  AND website = :wid
						LIMIT 1',
                        array(
                            ':wid' => $website->id,
                            ':lang' => $language,
                            ':object' => $object,
                            ':id' => $id
                        )
                    );
					break;

				case "extension":
					// remove old entry, if exists
					$DB->execute(
                    'DELETE FROM nv_webdictionary
						WHERE subtype = :id
						  AND node_type = "extension"
						  AND extension = :object 
						  AND lang = :lang
						  AND website = :wid
						LIMIT 1',
                        array(
                            ':id' => $id,
                            ':object' => $object,
                            ':lang' => $language,
                            ':wid' => $website->id
                        )
                    );
					break;
			}

			// insert new value (if not empty)
			if(!empty($text))
			{
				$ok = $DB->execute('
				    INSERT INTO nv_webdictionary
	                (	id,	website, node_type, theme, extension, node_id, node_uid, subtype, lang, `text`)
	                VALUES
	                (	0, :website, :node_type, :theme, :extension, :node_id, :subtype, :lang, :text )',
					array(
						':website' => $website->id,
						':node_type' => $type,
						':theme' => ($type=='theme'? $object : ""),
						':extension' => ($type=='extension'? $object : ""),
						':node_id' => (is_numeric($id)? $id : 0),
						':node_uid' => "",
						':subtype' => (is_numeric($id)? '' : $id),
						':lang' => $language,
						':text' => value_or_default($text, "")
					)
				);

				if(!$ok)
					$errors[] = $DB->get_last_error();
			}
		}

		return (empty($errors)? true : $errors);
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webdictionary WHERE website = '.intval($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>