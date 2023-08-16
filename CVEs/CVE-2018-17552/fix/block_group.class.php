<?php
class block_group
{
	public $id;
    public $website;
	public $code;
    public $title;
	public $notes;
    public $blocks; // array

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_block_groups
						WHERE id = '.intval($id).'
						  AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}

    public function load_by_code($code)
	{
		global $DB;
		global $website;

        $found = $DB->query(
            'SELECT * FROM nv_block_groups
                  WHERE code = :code
                    AND website = '.$website->id,
            'object',
            array(
                ':code' => $code
            )
        );

		if($found)
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
	    global $theme;

		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->website			= $main->website;
		$this->code  			= $main->code;
		$this->title  			= $main->title;
		$this->notes  			= $main->notes;

        // serialized (pre nv 2.1) or json object? (>= 2.1)
        // this compatibility fix will be removed in a future version!
        if(substr($main->blocks, 0, 2)=='a:')
        {
            $this->blocks		= mb_unserialize($main->blocks);

            for($b = 0; $b < count($this->blocks); $b++)
            {
                if(is_numeric($this->blocks[$b]))
                {
                    $this->blocks[$b] = array(
                        "type" => "block",
                        "id" => $this->blocks[$b]
                    );
                }
                else if(!empty($this->blocks[$b]))
                {
                    // block group block or block type?
                    if(is_array($theme->block_groups))
                    {
                        foreach($theme->block_groups as $key => $bg)
                        {
                            for($i=0; $i < count($bg->blocks); $i++)
                            {
                                if($bg->blocks[$i]->id==$this->blocks[$b])
                                {
                                    $this->blocks[$b] = array(
                                        "type" => "block_group_block",
                                        "id" => $this->blocks[$b]
                                    );
                                }
                            }
                        }
                    }

                    // final case, we assume it's a block type
                    if(!is_array($this->blocks[$b]))
                    {
                        $this->blocks[$b] = array(
                            "type" => "block_type",
                            "id" => $this->blocks[$b]
                        );
                    }
                }
            }
        }
        else
            $this->blocks = json_decode($main->blocks, true);
	}
	
	public function load_from_post()
	{
        $this->code  			= $_REQUEST['code'];
        $this->title  			= $_REQUEST['title'];
        $this->notes 			= $_REQUEST['notes'];
        $this->blocks			= json_decode($_REQUEST['blocks_group_selection'], true); //explode(",", $_REQUEST['blocks_group_selection']);
        if(empty($this->blocks))
            $this->blocks = array();
	}
	
	public function save()
	{
		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}
	
	public function delete()
	{
		global $DB;
		global $website;

		if(!empty($this->id))
		{
			$DB->execute('
				DELETE FROM nv_block_groups
			     WHERE id = '.intval($this->id).'
				   AND website = '.$website->id
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

        $ok = $DB->execute(
            'INSERT INTO nv_block_groups
                (id, website, code, title, notes, blocks)
                VALUES
                ( 0, :website, :code, :title, :notes, :blocks )
            ',
            array(
                ':website'          =>  value_or_default($this->website, $website->id),
                ':code'             =>  value_or_default($this->code, ''),
                ':title'            =>  value_or_default($this->title, ''),
                ':notes'            =>  value_or_default($this->notes, ''),
                ':blocks'           =>  json_encode($this->blocks)
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;

        $ok = $DB->execute(
            'UPDATE nv_block_groups
             SET
                `code`			= :code,
                title           = :title,
                notes	 		= :notes,
                blocks   		= :blocks
             WHERE id = :id
               AND website = :website
            ',
            array(
                ':id'               =>  $this->id,
                ':website'          =>  $this->website,
                ':code'             =>  value_or_default($this->code, ''),
                ':title'            =>  value_or_default($this->title, ''),
                ':notes'            =>  value_or_default($this->notes, ''),
                ':blocks'           =>  json_encode($this->blocks)
            )
        );
		
		if(!$ok) throw new Exception($DB->get_last_error());

		return true;
	}

    public static function paginated_list($offset, $limit, $order_by_field, $order_by_ascdesc)
    {
        global $DB;
	    global $website;

        $DB->queryLimit(
            '*',
            'nv_block_groups',
            'website = '.intval($website->id),
            $order_by_field.' '.$order_by_ascdesc,
            $offset,
            $limit
        );
        $rs = $DB->result();
        $total = $DB->foundRows();

        return array($rs, $total);
    }
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');

		// all columns to look for	
		$cols[] = 'b.id' . $like;
		$cols[] = 'b.title' . $like;
		$cols[] = 'b.notes' . $like;		

		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
            SELECT *
              FROM nv_block_groups
             WHERE website = '.intval($website->id),
            'object'
        );
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

	public static function __set_state(array $obj)
	{
		$tmp = new block_group();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}
		
}

?>