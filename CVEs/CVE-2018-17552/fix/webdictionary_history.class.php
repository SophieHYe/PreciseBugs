<?php

class webdictionary_history
{
	public static function save_element_strings($node_type, $node_id, $dictionary, $autosave=false, $website_id=null)
	{
		global $DB;
		global $website;

		if(empty($website_id))
			$website_id = $website->id;

        $changed = false;

	    if(empty($node_id)) throw new Exception('ERROR webdictionary: No ID!');

        if(!is_array($dictionary))
            $dictionary = array();

		foreach($dictionary as $lang => $item)
		{
			foreach($item as $subtype => $litem)
			{	
				if(strpos($subtype, 'section-')===0)
				{			
					if($litem=='<p><br _mce_bogus="1"></p>')
                    {
                        // equals to tinymce empty content, so no need to save it
                        continue;
                    }
				
					// has the text been changed since the last save?
					$last_litem = $DB->query_single(
						'`text`',
						'nv_webdictionary_history',
						'   
						    node_id = :node_id AND
							website = :wid AND
							lang = :lang AND
							subtype = :subtype AND
							node_type = :node_type AND
							autosave = '.(($autosave)? '1' : '0').'
						 ORDER BY date_created DESC',
                        null,
                        array(
                            ':wid' => $website_id,
                            ':node_id' => $node_id,
                            ':lang' => $lang,
                            ':subtype' => $subtype,
                            ':node_type' => $node_type
                        )
					);
																							
					if($last_litem != $litem)
					{
                        $changed = true;

						// autocleaning
						if($autosave) 
						{
							// remove previous autosaved elements
							$DB->execute('
								DELETE FROM nv_webdictionary_history
								WHERE node_id = :node_id AND
									  website = :wid AND
										 lang = :lang AND
									  subtype = :subtype AND
									node_type = :node_type AND
									 autosave = 1 AND
								 date_created < '.(core_time() - 86400 * 7),
                                array(
                                    ':wid' => $website_id,
                                    ':lang' => $lang,
                                    ':node_id' => $node_id,
                                    ':node_type' => $node_type,
                                    ':subtype' => $subtype
                                )
							);
						}

						$DB->execute('
			                INSERT INTO nv_webdictionary_history
								(id, website, node_type, node_id, subtype, lang, `text`, date_created, autosave)
							VALUES
								( 0, :website, :node_type, :node_id, :subtype, :lang, :text, :date_created, :autosave)
							',
							array(
								":website" => $website_id,
								":node_type" => $node_type,
								":node_id" => $node_id,
								":subtype" => $subtype,
								":lang" => $lang,
								":text" => $litem,
								":date_created" => core_time(),
								":autosave" => ($autosave)? '1' : '0'
							)
						);
					}
				}
			}
		}

        return $changed;
	}

	public static function load_element_strings($node_type, $node_id, $savedOn="latest")
	{
		global $DB;
		
		// load all webdictionary history elements and keep only the latest
		$DB->query('
			SELECT subtype, lang, text
			  FROM nv_webdictionary_history
			 WHERE node_type = :node_type 
			   AND node_id = :node_id
			 ORDER BY date_created ASC',
            'object',
            array(
                ':node_type' => $node_type,
                ':node_id' => $node_id
            )
		);
				
		$data = $DB->result();
		
		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
		{
			$dictionary[$item->lang][$item->subtype] = $item->text;
		}
		
		return $dictionary;			
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
			SELECT * FROM nv_webdictionary_history
			WHERE website = '.intval($website->id),
	        'object'
        );

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>