<?php

class menu
{
	public $id;
	public $codename;
	public $icon;
	public $lid;
	public $notes;
	public $enabled;
	
	public $functions;
  	
	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_menus WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		global $DB;
		
		$main = $rs[0];
		
		$this->id      		= $main->id;
		$this->codename		= $main->codename;
		$this->icon		    = $main->icon;
   		$this->lid		    = $main->lid;    
   		$this->notes	    = $main->notes;    
		$this->enabled		= $main->enabled;
		
		/*
		$DB->query('SELECT function_id 
					  FROM nv_menu_items 
					 WHERE menu_id = '.$this->id.' ORDER BY position ASC');
					 
		$this->functions = $DB->result('function_id');
		*/
		$this->functions	= json_decode($main->functions);
		if(empty($this->functions))	$this->functions = array();		
	}
	
	public function load_from_post()
	{
		$this->codename		= $_REQUEST['codename'];
		$this->icon		    = $_REQUEST['icon'];
   		$this->lid		    = $_REQUEST['lid']; 
   		$this->notes	    = $_REQUEST['notes']; 		   
		$this->enabled		= ($_REQUEST['enabled']=='1'? '1' : '0');
		
		// load associated functions
		$functions = explode('#', $_REQUEST['menu-functions']);
		$this->functions = array();
		foreach($functions as $function)
		{
			if(!empty($function))
				$this->functions[] = $function;
		}
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
 				DELETE FROM nv_menus
					WHERE id = '.intval($this->id).'
              		LIMIT 1 '
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
    
		$ok = $DB->execute(' 
 			INSERT INTO nv_menus
				(id, codename, icon, lid, notes, functions, enabled)
			VALUES 
				( 0, :codename, :icon, :lid, :notes, :functions, :enabled)',
			array(
				'codename' => value_or_default($this->codename, ""),
				'icon' => value_or_default($this->icon, ""),
				'lid' => value_or_default($this->lid, 0),
				'notes' => value_or_default($this->notes, ""),
				'functions' => json_encode($this->functions),
				'enabled' => value_or_default($this->enabled, 0)
			)
		);
				
		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}	
	
	public function update()
	{
		global $DB;
	    
		$ok = $DB->execute(' 
 			UPDATE nv_menus
			   SET codename = :codename, icon = :icon, lid = :lid, notes = :notes, 
			   	   functions = :functions, enabled = :enabled
            WHERE id = :id',
			array(
				'id' => $this->id,
				'codename' => value_or_default($this->codename, ""),
				'icon' => value_or_default($this->icon, ""),
				'lid' => value_or_default($this->lid, 0),
				'notes' => value_or_default($this->notes, ""),
				'functions' => json_encode($this->functions),
				'enabled' => value_or_default($this->enabled, 0)
			)
        );
		
		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		return true;
	}
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'codename' . $like;
		$cols[] = 'icon' . $like;		
		$cols[] = 'notes' . $like;
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	
	
	public static function load_all_menus()
	{
		global $DB;
		
		$DB->query('SELECT * FROM nv_menus');
		
		return $DB->result();
	}

    public function backup($type='json')
    {
        global $DB;

        $out = array();

        $DB->query('SELECT * FROM nv_menus', 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>