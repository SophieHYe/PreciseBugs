<?php
class webuser_vote
{
	public $id;
	public $website;
	public $webuser;
	public $object;	// item, structure, poll [?]
	public $object_id;
	public $value;
	public $date;

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_webuser_votes
						WHERE id = '.intval($id).'
						  AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->website			= $main->website;
		$this->webuser			= $main->webuser;
		$this->object			= $main->object;
		$this->object_id		= $main->object_id;
		$this->value			= $main->value;		
		$this->date				= $main->date;
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
		global $website;

		if(!empty($this->id))
		{			
			$DB->execute('
				DELETE FROM nv_webuser_votes
				 WHERE id = '.intval($this->id).' AND
				       website = '.$website->id
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		$this->date = core_time();		
		
		$ok = $DB->execute(' 
 			INSERT INTO nv_webuser_votes
				(id, website, webuser, object, object_id, value, date)
			VALUES 
				( 0, :website, :webuser, :object, :object_id, :value, :date )',
			array(
				'website' => value_or_default($this->website, $website->id),
				'webuser' => value_or_default($this->webuser, 0),
				'object' => value_or_default($this->object, "item"),
				'object_id' => $this->object_id,
				'value' => $this->value,
				'date' => value_or_default($this->date, 0)
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
		global $website;
			
		$this->date = core_time();		
			
		$ok = $DB->execute(' 
 			UPDATE nv_webuser_votes
				SET webuser	= :webuser, object = :object, object_id = :object_id, value = :value, date = :date
			WHERE id = :id AND website = :website',
			array(
				'id' => $this->id,
				'website' => value_or_default($this->website, $website->id),
				'webuser' => value_or_default($this->webuser, 0),
				'object' => $this->object,
				'object_id' => $this->object_id,
				'value' => $this->value,
				'date' => value_or_default($this->date, 0)
			)
		);
		
		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		return true;
	}			
	
	public static function object_votes_by_score($object, $object_id)
	{
		global $DB;
		global $website;
		
		$DB->query('
			SELECT value, COUNT(*) as votes
			  FROM nv_webuser_votes
             WHERE website = :wid
			   AND object  = :object
               AND object_id = :object_id
			 GROUP BY value
			 ORDER BY value ASC
		 ',
            'object',
            array(
                ':wid' => $website->id,
                ':object' => $object,
                ':object_id' => $object_id
            )
        );
					 
		$data = $DB->result();
		
		return $data;
	}
	
	public static function object_votes_by_webuser($object, $object_id, $orderby='date desc', $offset=0, $limit=PHP_INT_MAX)
	{
		global $DB;
		global $website;
		
		$DB->queryLimit(
		    'wuv.id AS id, wuv.date AS date, wuv.webuser AS webuser, wu.username AS username',
			'nv_webuser_votes wuv, nv_webusers wu',
			'	 
			    wuv.website = :wid 
			    AND wuv.object  = :object 
 				AND wuv.object_id = :object_id
				AND wu.id = wuv.webuser',
            $orderby,
            $offset,
            $limit,
            array(
                ':wid' => $website->id,
                ':object' => $object,
                ':object_id' => $object_id
            )
        );
					 				 
		return array($DB->result(), $DB->foundRows());
	}	
	
	public static function object_votes_by_date($object, $object_id, $since=0)
	{
		global $DB;
		global $website;
		
		$from_date = 0;
		if($since > 0)	// last x days
			$from_date = time() - $since * 24*60*60;
		
		$DB->query('
			SELECT date, value 
			  FROM nv_webuser_votes
             WHERE website = :wid
			   AND object  = :object
               AND object_id = :object_id 
			   AND date > :from_date
			 ORDER BY date ASC',
            'object',
            array(
                ':wid' => $website->id,
                ':object' => $object,
                ':object_id' => $object_id,
                ':from_date' => $from_date
            )
        );
					 
		$data = $DB->result();
		
		$votes = array();
		
		foreach($data as $row)
		{
			$votes[] = array($row->date * 1000, $row->value);	
		}
		
		return $votes;
	}	
	
	public static function update_object_votes($webuser, $object, $object_id, $value, $replace=false)
	{
		global $DB;
		global $website;
		global $events;

		$status = false;
		$voted = false;
		$webuser_vote_id = null;
		
		// user has voted in the past?
		if($DB->query('
				SELECT *
				  FROM nv_webuser_votes
				 WHERE webuser = :webuser 
				   AND object  = :object
				   AND object_id = :object_id',
        'object',
                array(
                    ':webuser' => $webuser,
                    ':object' => $object,
                    ':object_id' => $object_id
                )
			)
		)
		{
			$data = $DB->result();
			$data = $data[0];
			$voted = ($data->webuser == $webuser);
			$webuser_vote_id = $data->id;
		}	

		if($voted && $replace) // update
		{
			$ok = $DB->execute('
 				UPDATE nv_webuser_votes
				   SET `value`	=  :value,
						date 	=  :time
				 WHERE id = :wu_vote_id',
                array(
                    ':wu_vote_id' => $webuser_vote_id,
                    ':time' => core_time(),
                    ':value' => $value
                )
			);
			
			if(!$ok)
				throw new Exception($DB->get_last_error());
			else
				$status = true;
		}
		else if($voted)
		{
			$status = 'already_voted';
		}
		else	// insert
		{
			$wv = new webuser_vote();
			$wv->website = $website->id;
			$wv->webuser = $webuser;			
			$wv->object  = $object;
			$wv->object_id = $object_id;
			$wv->value 	 = $value;
			$wv->insert();
			$webuser_vote_id = $wv->id;
			$status = true;
		}
		
		// now update the object score
		if($status === true)
			webuser_vote::update_object_score($object, $object_id);

		$events->trigger(
			'webuser',
			'vote',
			array(
				'status' => $status,
				'webuser_vote_id' => $webuser_vote_id,
				'webuser' => $webuser,
				'object' => $object,
				'object_id' => $object_id,
				'value' => $value,
				'replace' => $replace
			)
		);
		
		return $status;
	}
	
	public static function update_object_score($object, $object_id)
	{
		global $DB;
		
		list($votes, $score) = webuser_vote::calculate_object_score($object, $object_id);		
		$table = array(
			'item'	=>	'nv_items',
			'structure'	=>	'nv_structure',
			'product' => 'nv_products'
		);

		if(empty($table[$object]))
			return false;
		
		$DB->execute('
			UPDATE '.$table[$object].' 
			   SET  votes = :votes, 
			        score = :score
			 WHERE id = :object_id',
            array(
                ':votes' => $votes,
                ':score' => $score,
                ':object_id' => $object_id
            )
		);
		
		return true;		
	}
	
	public static function calculate_object_score($object, $object_id)
	{
		global $DB;
		global $website;
				
		$DB->query(
		    'SELECT COUNT(*) as votes, SUM(value) as score
                  FROM nv_webuser_votes
                 WHERE object_id = :object_id
                   AND object = :object
                   AND website = :wid',
            'object',
            array(
                ':wid' => $website->id,
                ':object' => $object,
                ':object_id' => $object_id
            ));
					   
		$data = $DB->first();
		
		return array($data->votes, $data->score);
	}


	public static function remove_object_votes($object, $object_id)
	{
		global $DB;

		if(empty($object) || empty($object_id))
			return;
		
		$DB->execute('
			DELETE FROM nv_webuser_votes
			      WHERE object = :object
				    AND object_id = :object_id
            ',
            array(
                ':object' => $object,
                ':object_id' => $object_id
            )
		);
							  
		$table = array(
			'item'	=>	'nv_items',
			'structure'	=>	'nv_structure',
			'product' => 'nv_products'
		);

		if(empty($table[$object]))
			return;

		$DB->execute('
			UPDATE '.$table[$object].' 
			   SET votes = 0,
			   	   score = 0
			 WHERE id = :id
			 LIMIT 1',
            array(
                ':id' => $object_id
            )
		);							  
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webuser_votes WHERE website = '.intval($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>