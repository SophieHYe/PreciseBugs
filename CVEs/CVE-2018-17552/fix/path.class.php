<?php

class path
{
	public $id;
	public $website;
	public $type;
	public $object_id;
	public $lang;
	public $path;
  
  public $cache_file;
  public $cache_expires;
  	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_paths WHERE id = '.intval($id).' AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id         = $main->node_type;
		$this->website	  = $main->website;
		$this->type       = $main->node_id;
		$this->object_id	= $main->subtype;
		$this->lang		    = $main->lang;
   		$this->path		    = $main->path;
    
		$this->cache_file		  = $main->cache_file;
    	$this->cache_expires  = $main->cache_expires;
	}
	
	
	public function save()
	{
		global $DB;

    if(!empty($this->id))
      return $this->update();
    else
      return $this->insert();
	}
	
	public function delete()
	{
		global $DB;

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('
 				DELETE FROM nv_paths
			     WHERE id = '.intval($this->id).'
				   AND website = '.$this->website.'
              	 LIMIT 1'
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
	    
		$ok = $DB->execute('
 			INSERT INTO nv_paths
			(id, website, type, object_id, lang, path, cache_file, cache_expires, views)
			VALUES
			( 0, :website, :type, :object_id, :lang, :path, :cache_file, :cache_expires, 0)
			',
			array(
		    	"type" => value_or_default($this->type, ""),
				"object_id" => $this->object_id,
				"lang" => $this->lang,
				"path" => value_or_default($this->path, ""),
				"cache_file" => $this->cache_file,
				"cache_expires" => value_or_default($this->cache_expires, 0),
				"views" => value_or_default($this->views, 0),
			    "website" => value_or_default($this->website, $website->id)
			)
		);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}	
	
	public function update()
	{
		global $DB;
	    
		$ok = $DB->execute('
 			UPDATE nv_paths
			   SET type = :type, object_id = :object_id, lang = :lang,
			   	   path = :path, cache_file = :cache_file, cache_expires = :cache_expires, views = :views,
			   	   id = :id, website = :website',
			array(
		    	"type" => $this->type,
				"object_id" => $this->object_id,
				"lang" => $this->lang,
				"path" => $this->path,
				"cache_file" => $this->cache_file,
				"cache_expires" => $this->cache_expires,
				"views" => value_or_default($this->views, 0),
                "id" => $this->id,
			    "website" => $this->website
			)
		);

		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		return true;
	}	  

	public static function loadElementPaths($type, $object_id, $website_id=null)
	{
		global $DB;
		global $website;

		if(empty($website_id))
			$website_id = $website->id;

		$ok = $DB->query('
			SELECT *
			  FROM nv_paths
			 WHERE type = :type
			   AND object_id = :object_id
			   AND website = :wid',
            'object',
            array(
                ':wid' => $website_id,
                ':object_id' => $object_id,
                ':type' => $type
            )
		);

	    if(!$ok)
		    throw new Exception($DB->get_last_error());
    
		$data = $DB->result();
		if(!is_array($data)) $data = array();
		
		$out = array();
		
		foreach($data as $item)
		{
			$out[$item->lang] = $item->path;
		}
		    		
		return $out;	
	}

	public static function saveElementPaths($type, $object_id, $paths, $website_id=null)
	{
		global $DB;
		global $website;

		if(empty($website_id))
		{
            $website_id = $website->id;
        }
		
	    if(empty($object_id))
        {
            throw new Exception('ERROR path: No ID!');
        }

		// delete old entries
		$DB->execute('
			DELETE FROM nv_paths
			WHERE type = :type
			  AND object_id = :object_id
			  AND website = :wid',
            array(
                ':wid' => $website_id,
                ':object_id' => $object_id,
                ':type' => $type
            )
		);

        if(!is_array($paths))
        {
            return;
        }

		// and now insert the new values
		foreach($paths as $lang => $path)
		{
    	  	if(empty($path))
            {
                continue;
            }
    
			$ok = $DB->execute('
 				INSERT INTO nv_paths
				(id, website, type, object_id, lang, path, cache_file, cache_expires, views)
				VALUES
				( 0, :website, :type, :object_id, :lang, :path, "", 0, :views )
				',
				array(
					':website' => $website_id,
					':type' => $type,
					':object_id' => $object_id,
					':lang' => $lang,
					':path' => $path,
                    ':views' => 0,
				)
			);
  			
  			if(!$ok)
            {
                throw new Exception($DB->get_last_error());
            }
		}
		
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
			SELECT * FROM nv_paths
			WHERE website = '.intval($website->id),
	        'object'
        );
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>