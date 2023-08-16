<?php

class comment
{
	public $id;
	public $website;
	public $object_type;
	public $object_id;
	public $user;
	public $name;
	public $email;
	public $url;
	public $ip;
	public $date_created;
	public $date_modified;
    public $last_modified_by;
	public $status; //  -1 => To review  0 => Published  1 => Private    2 => Hidden   3 => Spam
    public $reply_to;
    public $subscribed; // 0 => no, 1 => yes
	public $message;

    public $properties;

    private $pending_revision; // keep pending revision flag until next reload
    public $avatar; // auto loaded from user avatar

    public function __construct()
    {
        $this->pending_revision = true;
    }

    public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_comments WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];	
		
		$this->id      		= $main->id;		
		$this->website      = $main->website;
		$this->object_type	= $main->object_type;
		$this->object_id	= $main->object_id;
		$this->user			= $main->user;
   		$this->name		    = $main->name;    
   		$this->email	    = $main->email;    
   		$this->url  	    = $main->url;
   		$this->ip		    = $main->ip;
		$this->date_created	= $main->date_created;
		$this->date_modified= $main->date_modified;		
		$this->last_modified_by  = $main->last_modified_by;
		$this->status		= $main->status;
		$this->reply_to		= $main->reply_to;
		$this->subscribed	= $main->subscribed;
		$this->message		= html_entity_decode($main->message, ENT_COMPAT, "UTF-8");

        $this->pending_revision = ($main->status == -1);
        $this->avatar       = $main->avatar;
	}
	
	public function load_from_post()
	{
		$this->object_type	= $_REQUEST['comment-object_type'];
		$this->object_id	= $_REQUEST['comment-object_id'];
		$this->user			= $_REQUEST['comment-user'];
		$this->name			= $_REQUEST['comment-name'];
		$this->email		= $_REQUEST['comment-email'];
		$this->url		    = $_REQUEST['comment-url'];
		$this->status		= intval($_REQUEST['comment-status']);
		$this->message		= $_REQUEST['comment-message'];
		$this->reply_to		= $_REQUEST['comment-reply_to'];
		$this->subscribed	= $_REQUEST['comment-subscribed'];
		$this->date_created	= core_date2ts($_REQUEST['comment-date_created']);
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
		global $events;

        $affected_rows = 0;

		// remove all old entries
		if(!empty($this->id))
		{
            // remove all properties of the comment
            property::remove_properties('comment', $this->id, $this->website);

			$DB->execute('
 				DELETE FROM nv_comments
				 WHERE id = '.intval($this->id).'
               LIMIT 1 '
			);

            $affected_rows = $DB->get_affected_rows();

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'comment',
                    'delete',
                    array(
                        'comment' => $this
                    )
                );
            }
		}
		
		return $affected_rows;
	}
	
	public function insert()
	{
		global $DB;	
		global $website;
		global $events;

		$message = htmlentities($this->message, ENT_COMPAT, 'UTF-8', true);

        if(empty($this->date_created))
            $this->date_created = core_time();

        if(empty($this->ip))
            $this->ip = core_ip();

        if(empty($this->website))
            $this->website = $website->id;

        $ok = $DB->execute('
 			INSERT INTO nv_comments
				(	id, website, object_type, object_id, user, name, email, url, ip,
					date_created, date_modified, last_modified_by,
					reply_to, subscribed, status, message
				)
				VALUES
				( 	0, :website, :object_type, :object_id, :user, :name, :email, :url, :ip,
					:date_created, :date_modified, :last_modified_by,
					:reply_to, :subscribed, :status, :message)
			',
			array(
				":website" => value_or_default($this->website, $website->id),
				":object_type" => value_or_default($this->object_type, "item"),
				":object_id" => value_or_default($this->object_id, 0),
				":user" => value_or_default($this->user, 0),
				":name" => empty($this->name)? "" : $this->name,
				":email" => empty($this->email)? "" : $this->email,
				":url" => empty($this->url)? "" : $this->url,
				":ip" => $this->ip,
				":date_created" => $this->date_created,
				":date_modified" => 0,
				":last_modified_by" => 0,
				":reply_to" => value_or_default($this->reply_to, 0),
				":subscribed" => value_or_default($this->subscribed, 0),
				":status" => value_or_default($this->status, 0),
				":message" => $message
			)
		);

		if(!$ok)
            throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'comment',
                'save',
                array(
                    'comment' => $this
                )
            );
        }

        $this->notify_subscribed();

		return true;
	}	
	
	public function update()
	{
		global $DB;
        global $user;
        global $events;

		$message = htmlentities($this->message, ENT_COMPAT, 'UTF-8', true);

		$ok = $DB->execute('
 			UPDATE nv_comments
 			SET
 			  object_type = :object_type,
 			  object_id = :object_id,
              user = :user,
              name = :name,
              email = :email,
              url = :url,
              date_created = :date_created,
              date_modified = :date_modified,
              last_modified_by = :last_modified_by,
              reply_to = :reply_to,
              subscribed = :subscribed,
              status = :status,
              message = :message
            WHERE id = :id
			',
			array(
                ":object_type" => value_or_default($this->object_type, "item"),
                ":object_id" => value_or_default($this->object_id, 0),
				":user" => value_or_default($this->user, 0),
				":name" => empty($this->name)? "" : $this->name,
				":email" => empty($this->email)? "" : $this->email,
				":url" => empty($this->url)? "" : $this->url,
				":date_created" => $this->date_created,
				":date_modified" => core_time(),
				":last_modified_by" => value_or_default($user->id, 0),
				":reply_to" => value_or_default($this->reply_to, 0),
				":subscribed" => value_or_default($this->subscribed, 0),
				":status" => value_or_default($this->status, 0),
				":message" => $message,
				":id" => $this->id
			)
		);
		
		if(!$ok)
		    throw new Exception($DB->get_last_error());

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'comment',
                'save',
                array(
                    'comment' => $this
                )
            );
        }

        $this->notify_subscribed();
		
		return true;
	}
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'message' . $like;
		$cols[] = 'name' . $like;
		$cols[] = 'email' . $like;

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
			SELECT * FROM nv_comments
			 WHERE website = '.protect($website->id),
	        'object'
        );
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

    public static function pending_count()
    {
        global $DB;
        global $website;

        $pending_comments = $DB->query_single(
            'COUNT(*)',
            'nv_comments',
            ' website = '.protect($website->id).' AND
              status = -1'
        );

        return $pending_comments;
    }

    public static function remove_spam()
    {
        global $DB;
        global $website;

        $count = $DB->query_single(
	        'count(*) as total',
	        'nv_comments',
	        'website = '.protect($website->id).' AND status = 3'
        );

        $ok = $DB->execute('
			DELETE FROM nv_comments
             WHERE website = '.protect($website->id).'
               AND status = 3
        ');

        if($ok)
            return $count;
    }

    public function author_name()
    {
        if(!empty($this->user))
        {
            $w = new webuser();
            $w->load($this->user);
            return $w->username;
        }
        else
            return $this->name;
    }

    public function author_avatar()
    {
        if(!empty($this->avatar))
        {
            return $this->avatar;
        }
        else if(!empty($this->user))
        {
            $w = new webuser();
            $w->load($this->user);
            return $w->avatar;
        }
        else
        {
            return null;
        }
    }

    public function depth()
    {
        global $DB;
        $out = 0;

        if(!empty($this->depth)) // already calculated!
            return $this->depth;

        if(!empty($this->reply_to))
        {
            $parent = $this->reply_to;
            while(!empty($parent))
            {
                $out++;
                $parent = $DB->query_single('reply_to', 'nv_comments', 'id = ' . $parent);
            }
        }

        $this->depth = $out;

        return $out;
    }

    public function get_replies($recursive=true)
    {
        global $DB;

        $out = array();

        // replies are always ordered by ascending date creation
        $DB->query('
            SELECT nvc.*, nvwu.username, nvwu.avatar 
             FROM nv_comments nvc
             LEFT OUTER JOIN nv_webusers nvwu
                          ON nvwu.id = nvc.user
            WHERE nvc.reply_to = '.$this->id.' AND
                  nvc.website = '.$this->website.' AND
                  nvc.status = 0
            ORDER BY nvc.date_created ASC'
        );

        $rs = $DB->result();

        if($recursive)
        {
            for($r=0; $r < count($rs); $r++)
            {
                $c = new comment();
                $c->load_from_resultset(array($rs[$r]));
                $replies = $c->get_replies();

                $out[] = $rs[$r];
                if(!empty($replies))
                {
                    foreach($replies as $reply)
                        $out[] = $reply;
                }
            }
        }
        else
        {
            $out = $rs;
        }

        return $out;
    }

    public function element_template()
    {
        global $DB;

        $out = "";

        if(!empty($this->object_id))
        {
            if($this->object_type == "item")
                $out = $DB->query_single('template', 'nv_items', 'id=' . $this->object_id);
            else if($this->object_type == "product")
                $out = $DB->query_single('template', 'nv_products', 'id=' . $this->object_id);
        }


        return $out;
    }

    public function property($property_name, $raw=false)
    {
        // load properties if not already done
        if(empty($this->properties))
        {
            $template = $this->element_template();
            $this->properties = property::load_properties('comment', $template, 'comment', $this->id);
        }

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
            {
                $out = $this->properties[$p]->value;
                break;
            }
        }

        return $out;
    }

    public function property_definition($property_name)
    {
        // load properties if not already done
        if(empty($this->properties))
        {
            $template = $this->element_template();
            $this->properties = property::load_properties('comment', $template, 'comment', $this->id);
        }

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
        // load properties if not already done
        if(empty($this->properties))
        {
            $template = $this->element_template();
            $this->properties = property::load_properties('comment', $template, 'comment', $this->id);
        }

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
                return true;
        }
        return false;
    }

    public function notify_subscribed()
    {
        global $DB;

        if($this->pending_revision && $this->status == 0)
        {
            $website = new website();
            $website->load($this->website);
            $lang = $website->languages_published[0];

            if($this->object_type == "item")
                $item = new item();
            else
                $item = new product();

            $item->load($this->object_id);

            // find users subscribed to the same content (except the author of the current comment)
            $DB->query('
                SELECT id, user, email 
                 FROM nv_comments
                WHERE website = ' . $this->website . '
                  AND object_type = ' . protect($this->object_type) . '
                  AND object_id = ' . protect($this->object_id) . '
                  AND subscribed = 1
            ');

            $subscribers = $DB->result();

            $emailed_to = array();
            $users_emailed_to = array();

            if (!empty($subscribers))
            {
                // send an email notification telling there is a new comment
                foreach ($subscribers as $subscriber)
                {
                    if (!empty($subscriber->user) && !in_array($subscriber->user, $users_emailed_to))
                    {
                        // the subscriber is a web user
                        $users_emailed_to[] = $subscriber->user;
                        $swu = new webuser();
                        $swu->load($subscriber->user);
                        $emailed_to[] = $swu->email;

                        if($swu->language == $lang->code)
                        {
                            $ulang = $lang;
                        }
                        else
                        {
                            $ulang = new language();
                            $ulang->load($swu->language);
                        }

                        // compose and send email
                        $content_title = $item->dictionary[$swu->language]['title'];
                        if (empty($content_title))
                            $content_title = $item->dictionary[$lang]['title'];

                        $subject = $website->name . ' | ' . $ulang->t(387, 'New comment') . ' [' . $content_title . ']';
                        if($this->email == $swu->email)
                            $subject = $website->name . ' | ' . $ulang->t(780, 'You comment has been published') . ' [' . $item->dictionary[$ulang->code]['title'] . ']';

                        $content = '<a href="' . nvweb_source_url('item', $item->id, $swu->language) . '" target="_blank">' . $content_title . '</a>';
                        $comment_link = '<a href="' . nvweb_source_url('item', $item->id, $swu->language) . '#comment-'.$this->id.'" target="_blank">' . $ulang->t(779, "Read more") . '</a>';
                        $unsubscribe_link = $website->absolute_path() . '/nv.comments/unsubscribe?cid='.$subscriber->id.'&hash=' . md5(APP_UNIQUE . $subscriber->id . '#' . $this->object_type . $this->object_id);

                        $body = navigate_compose_email(
                            array(
                                array(
                                    'title'   => $ulang->t(177, "Website"),
                                    'content' => '<a href="' . $website->absolute_path() . $website->homepage() . '">' . $website->name . '</a>'
                                ),
                                array(
                                    'title'   => $ulang->t(9, "Content"),
                                    'content' => $content
                                ),
                                array(
                                    'title'   => $ulang->t(266, "Author").', '.$ulang->t(86, "Date"),
                                    'content' => $this->author_name() .
                                                 '<br />' .
                                                 core_ts2date($this->date_created, true)
                                ),
                                array(
                                    'title'   => $ulang->t(387, "New comment"),
                                    'content' => core_string_cut($this->message, 100).'
                                                 <br /><br />
                                                 ('.$comment_link.')'
                                ),
                                array(
                                    'footer' => '<a href="' . $unsubscribe_link . '">' .
                                        $ulang->t(653, 'Unsubscribe from comments notifications related to this content') .
                                        '</a>'
                                )
                            )
                        );

                        navigate_send_email($subject, $body, array($swu->email), array(), true);
                    }
                    else if (!empty($subscriber->email) && !in_array($subscriber->email, $emailed_to))
                    {
                        // the subscriber is not a web user (a public comment)
                        $emailed_to[] = $subscriber->email;

                        $ulang = new language();
                        $ulang->load($website->languages_published[0]);

                        // send email
                        $subject = $website->name . ' | ' . $ulang->t(387, 'New comment') . ' [' . $item->dictionary[$ulang->code]['title'] . ']';
                        if($this->email == $subscriber->email)
                            $subject = $website->name . ' | ' . $ulang->t(780, 'You comment has been published') . ' [' . $item->dictionary[$ulang->code]['title'] . ']';

                        $content = '<a href="' . nvweb_source_url('item', $item->id, $ulang->code) . '" target="_blank">' . $item->dictionary[$ulang->code]['title'] . '</a>';
                        $comment_link = '<a href="' . nvweb_source_url('item', $item->id, $website->languages_published[0]) . '#comment-'.$this->id.'" target="_blank">' . $ulang->t(779, "Read more") . '</a>';
                        $unsubscribe_link = $website->absolute_path() . '/nv.comments/unsubscribe?cid='.$subscriber->id.'&hash=' . md5(APP_UNIQUE . $subscriber->id . '#' . $this->object_type . $this->object_id);

                        $body = navigate_compose_email(
                            array(
                                array(
                                    'title'   => $ulang->t(177, "Website"),
                                    'content' => '<a href="' . $website->absolute_path() . $website->homepage() . '">' . $website->name . '</a>'
                                ),
                                array(
                                    'title'   => $ulang->t(9, "Content"),
                                    'content' => $content
                                ),
                                array(
                                    'title'   => $ulang->t(266, "Author").', '.$ulang->t(86, "Date"),
                                    'content' => $this->author_name() .
                                                 '<br />' .
                                                 core_ts2date($this->date_created, true)
                                ),
                                array(
                                    'title'   => $ulang->t(387, "New comment"),
                                    'content' => core_string_cut($this->message, 100).'
                                                 <br /><br />
                                                 ('.$comment_link.')'
                                ),
                                array(
                                    'footer' => '<a href="' . $unsubscribe_link . '">' .
                                        $ulang->t(653, 'Unsubscribe from comments notifications related to this content') .
                                        '</a>'
                                )
                            )
                        );

                        navigate_send_email($subject, $body, array($subscriber->email), array(), true);
                    }
                }
            }
        }
    }

    public static function notifications_unsubscribe($cid, $hash)
    {
        global $DB;

        $c = new comment();
        $c->load(intval($cid));

        $hash_check = md5(APP_UNIQUE . $c->id . '#' . $c->object_type . $c->object_id);
        if($hash == $hash_check)
        {
            if(!empty($c->user))
            {
                $DB->execute(
                    'UPDATE nv_comments SET subscribed = 0 
                      WHERE object_type = :object_type
                        AND object_id = :object_id
                        AND user = :user',
                    array(
                        ':object_type' => $c->object_type,
                        ':object_id' => $c->object_id,
                        ':user' => $c->user
                    )
                );
            }
            else
            {
                $DB->execute(
                    'UPDATE nv_comments SET subscribed = 0 
                      WHERE object_type = :object_type AND 
                            object_id = :object_id AND
                            email = :email',
                    array(
                        ':object_type' => $c->object_type,
                        ':object_id' => $c->object_id,
                        ':email' => $c->email
                    )
                );
            }
        }

        $error = $DB->error();
        return empty($error);
    }

    public static function webuser_comments_count($webuser_id)
    {
        global $DB;
        global $website;

        $DB->query('  
            SELECT COUNT(*) AS total 
            FROM nv_comments 
            WHERE website = '.protect($website->id).' 
            AND user = '.protect($webuser_id).'
            AND status = 0'
        );

        $out = $DB->result('total');
        if(is_array($out))
            $out = $out[0];

        return $out;
    }

    public static function __set_state(array $obj)
	{
		$tmp = new comment();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}

}

?>