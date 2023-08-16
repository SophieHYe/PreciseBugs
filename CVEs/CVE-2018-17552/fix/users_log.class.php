<?php
class users_log
{
	public $id;
	public $date;
	public $user;
	public $website;
	public $function;
	public $item;
	public $action;
	public $item_title;
	public $data; // binary blob

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_users_log WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->date				= $main->date;
		$this->user				= $main->user;
		$this->website			= $main->website;
		$this->function			= $main->function;
		$this->item				= $main->item;
		$this->action			= $main->action;
		$this->item_title		= $main->item_title;
		$this->data				= $main->data;	

		// uncompress with gzdecode (function on PHP SVN, yet to be released)
		if(!empty($this->data) && function_exists('gzdecode'))
			$this->data = gzdecode($this->data);
		
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
				DELETE FROM nv_users_log 
				 WHERE id = '.intval($this->id)
			);
		}
		
		return $DB->get_affected_rows();		
	}

		
	public static function action($function, $item='', $action='', $item_title='', $data='')
	{
		global $DB;
		global $website;
		global $user;
		
		$encoded_data = '';
		if($action=='save' && function_exists('gzencode'))
			$encoded_data = gzencode($data); 
			
		// a blank (new) form requested
		if($action=='load' && empty($item))
			return true;

        $wid = $website->id;
        if(empty($wid))
            $wid = 0;

        $uid = $user->id;
        if(empty($uid))
            $uid = 0;

        if(!is_numeric($function))
        {
            $func = core_load_function($function);
            $function = $func->id;
        }
		
		// prepared statement			
		$ok = $DB->execute(' 
 			INSERT INTO nv_users_log
				(id, `date`, user, website, `function`, item, action, item_title, data)
			VALUES 
				( ?, ?, ?, ?, ?, ?, ?, ?, ?	)',
			array(
				0,
				core_time(),
				$uid,
				$wid,
				$function,
				value_or_default($item, ""),
				value_or_default($action, ""),
				value_or_default((string)$item_title, ""),
				value_or_default($encoded_data, "")
			)
		);
			
		if(!$ok)
			throw new Exception($DB->get_last_error());
				
		return true;
	}
	
	public static function recent_items($limit=5)
	{
		global $DB;
		global $user;
		global $website;

		// last month only!
		$DB->query('
			SELECT DISTINCT nvul.website, nvul.function, nvul.item, nvul.item_title,
							nvf.lid as function_title, nvf.icon as function_icon, nvul.date
			FROM nv_users_log nvul, 
				 nv_functions nvf
			WHERE nvul.user = '.intval($user->id).'
			  AND nvul.function = nvf.id
			  AND nvul.item > 0
			  AND nvul.action = "load"
			  AND nvul.website = '.intval($website->id).'
			  AND nvul.item_title <> ""
			  AND nvul.date > '.( core_time() - 30 * 86400).'
			  AND nvul.date = (	SELECT MAX(nvulm.date) 
			  					  FROM nv_users_log nvulm 
			  					 WHERE nvulm.function = nvul.function 
			  					   AND nvulm.item = nvul.item
			  					   AND nvulm.item_title = nvul.item_title
			  					   AND nvulm.website = '.intval($website->id).'
			  					   AND nvulm.user = '.intval($user->id).'
							   )
			ORDER BY nvul.date DESC
			LIMIT '.$limit
		);

		$rows = $DB->result();

		return $rows;
	}

	public static function recent_actions($function, $action, $limit=8)
	{
		global $DB;
		global $user;
		global $website;

		// last month only!
		$DB->query('
			SELECT DISTINCT nvul.website, nvul.function, nvul.item, nvul.date
			FROM nv_users_log nvul
			WHERE nvul.user = :user_id
			  AND nvul.function = :function
			  AND nvul.item > 0
			  AND nvul.action = :action
			  AND nvul.website = :wid
			  AND nvul.date > '.( core_time() - 30 * 86400).'
			  AND nvul.date = (	SELECT MAX(nvulm.date) 
			  					  FROM nv_users_log nvulm 
			  					 WHERE nvulm.function = nvul.function 
			  					   AND nvulm.item = nvul.item
			  					   AND nvulm.item_title = nvul.item_title
			  					   AND nvulm.website = :wid
			  					   AND nvulm.user = :user_id
							   )
			ORDER BY nvul.date DESC
			LIMIT '.$limit,
        'object',
            array(
                ':wid' => $website->id,
                ':action' => $action,
                ':function' => $function,
                ':user_id' => $user->id
            )
		);

		$rows = $DB->result();

		return $rows;
	}
}

?>