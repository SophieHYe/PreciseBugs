<?php
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/external/feedcreator/feedcreator.class.php');

class feed
{
	public $id;
	public $website;
	public $categories;
	public $image;	
	public $format;
	public $entries;
	public $content;
	public $views;
	public $permission;
	public $enabled;
	
	public $dictionary;
	public $paths;
	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_feeds WHERE id = '.intval($id).' AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;		
		$this->title  		= $main->title;
		$this->categories	= explode(',', $main->categories);
		$this->format		= $main->format;
		$this->image		= $main->image;
		$this->entries		= $main->entries;
		$this->content		= $main->content;				
		$this->views		= $main->views;		
		$this->permission	= $main->permission;		
		$this->enabled		= $main->enabled;	
		
		$this->dictionary	= webdictionary::load_element_strings('feed', $this->id);
		$this->paths 		= path::loadElementPaths('feed', $this->id);
	}
	
	public function load_from_post()
	{
		$this->permission	= intval($_REQUEST['permission']);
		$this->enabled		= intval($_REQUEST['enabled']);
		$this->format  		= $_REQUEST['format'];
		$this->image		= intval($_REQUEST['image']);
		$this->content 		= $_REQUEST['content'];
		$this->entries		= intval($_REQUEST['entries']);

	
		// language strings and options
		$this->dictionary = array();
		$this->paths = array();
		$fields = array('title', 'description');
		
		foreach($_REQUEST as $key => $value)
		{
			if(empty($value)) continue;
			
			foreach($fields as $field)
			{
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
					$this->dictionary[substr($key, strlen($field.'-'))][$field] = $value;
			}
		
			if(substr($key, 0, strlen('path-'))=='path-')
				$this->paths[substr($key, strlen('path-'))] = $value;
		}	
		
		$this->categories 	= '';
		if($_REQUEST['categories']!='true')
			$this->categories	= explode(',', $_REQUEST['categories']);				
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
		global $events;

		$affected_rows = 0;

		// remove all old entries
		if(!empty($this->id))
        {
            $DB->execute('
				DELETE FROM nv_feeds
					  WHERE id = ' . intval($this->id) . ' AND 
					        website = ' . $this->website
            );

            $affected_rows = $DB->get_affected_rows();

            // remove dictionary elements
            webdictionary::save_element_strings('feed', $this->id, array());

            // remove path elements
            path::saveElementPaths('feed', $this->id, array());

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'feed',
                    'delete',
                    array(
                        'feed' => $this
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
		
		$ok = $DB->execute(' 
 			INSERT INTO nv_feeds
				(id, website, categories, format, image, entries, content, views, permission, enabled)
			VALUES 
				( 0, :website, :categories, :format, :image, :entries, :content, :views, :permission, :enabled)
			',
			array(
				'website' => value_or_default($this->website, $website->id),
				'categories' => implode(',', $this->categories),
				'format' => $this->format,
				'image' => value_or_default($this->image, 0),
				'entries' => value_or_default($this->entries, 10),
				'content' => $this->content,
				'views' => value_or_default($this->views, 0),
				'permission' => value_or_default($this->permission, 0),
				'enabled' => value_or_default($this->enabled, 0)
			)
		);

        if(!$ok)
            throw new Exception($DB->get_last_error());

		$this->id = $DB->get_last_id();
			
		webdictionary::save_element_strings('feed', $this->id, $this->dictionary);
		path::saveElementPaths('feed', $this->id, $this->paths);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'feed',
                'save',
                array(
                    'feed' => $this
                )
            );
        }
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $events;

		if(!is_array($this->categories))
			$this->categories = array();
			
		$ok = $DB->execute(' 
 			UPDATE nv_feeds
			  SET categories = :categories, format = :format, image = :image, entries = :entries, 
			  	  content = :content, views = :views, permission = :permission, enabled = :enabled
			WHERE id = :id	AND	website = :website',
			array(
				'id' => $this->id,
				'website' => $this->website,
				'categories' => implode(',', $this->categories),
				'format' => $this->format,
				'image' => value_or_default($this->image, 0),
				'entries' => value_or_default($this->entries, 10),
				'content' => $this->content,
				'views' => value_or_default($this->views, 0),
				'permission' => value_or_default($this->permission, 0),
				'enabled' => value_or_default($this->enabled, 0)
			)
		);
							  
		if(!$ok)
		    throw new Exception($DB->get_last_error());
		
		webdictionary::save_element_strings('feed', $this->id, $this->dictionary);
		path::saveElementPaths('feed', $this->id, $this->paths);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'feed',
                'save',
                array(
                    'feed' => $this
                )
            );
        }
		
		return true;
	}
	
	public function quicksearch($text)
	{
		global $DB;
		global $website;
		
		$like = ' LIKE '.protect('%'.$text.'%');
		
		// we search for the IDs at the dictionary NOW (to avoid inefficient requests)
		
		$DB->query('SELECT DISTINCT (nvw.node_id)
					 FROM nv_webdictionary nvw
					 WHERE nvw.node_type = "feed" 
					   AND nvw.website = '.$website->id.' 
					   AND nvw.text '.$like, 'array');
						   
		$dict_ids = $DB->result("node_id");
		
		// all columns to look for	
		$cols[] = 'i.id' . $like;

		if(!empty($dict_ids))
			$cols[] = 'i.id IN ('.implode(',', $dict_ids).')';
			
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	
	
	public static function generate_feed($id = NULL)
	{
		global $current;
		global $website;
		global $DB;
	
		if(empty($id))
			$id = $current['id'];

		$item = new feed();
		$item->load($id);
		
		$permission = nvweb_object_enabled($item);
	
		if(!$permission)
            return;

        $feed = new UniversalFeedCreator();
		
		$feed->encoding = 'UTF-8';
		$feed->title = $item->dictionary[$current['lang']]['title'];
		$feed->description = $item->dictionary[$current['lang']]['description'];
		$feed->link = $website->absolute_path();
		$feed->syndicationURL = $website->absolute_path().$item->paths[$current['lang']];

		if(!empty($item->image))
		{
			$image = new FeedImage(); 
			$image->url = $website->absolute_path().'/object?type=image&amp;id='.$item->image;
			$image->link = $website->absolute_path(); 
			//$image->description = $feed->title;
			$image->title = $feed->title;
			$feed->image = $image;
		}

		// TODO: include products in feed content
		if(!empty($item->categories[0]))
		{			
			$limit = intval($item->entries);		 
			if($limit <= 0)	$limit = 10;

            $DB->query(' SELECT SQL_CALC_FOUND_ROWS i.id, i.permission, i.date_published, i.date_unpublish,
                                i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate, d.text as title, i.position as position,
                                i.galleries as galleries, i.template as template
                          FROM nv_items i, nv_structure s, nv_webdictionary d
                         WHERE i.category IN('.implode(",", $item->categories).')
                           AND i.website = '.$website->id.'
                           AND i.permission = 0
                           AND (i.date_published = 0 OR i.date_published < '.core_time().')
                           AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
                           AND s.id = i.category
                           AND (s.date_published = 0 OR s.date_published < '.core_time().')
                           AND (s.date_unpublish = 0 OR s.date_unpublish > '.core_time().')
                           AND s.permission = 0
                           AND (s.access = 0)
                           AND (i.access = 0)
                           AND d.website = i.website
                           AND d.node_type = "item"
                           AND d.subtype = "title"
                           AND d.node_id = i.id
                           AND d.lang = '.protect($current['lang']).'
                         ORDER BY pdate DESC
                         LIMIT '.$limit.'
                        OFFSET 0');
								
			$rs = $DB->result();
			
			for($x=0; $x < count($rs); $x++)
			{
				if(nvweb_object_enabled($rs[$x]))
				{
					$texts = webdictionary::load_element_strings('item', $rs[$x]->id);
					$paths = path::loadElementPaths('item', $rs[$x]->id);				
		
					$fitem = new FeedItem(); 
					$fitem->title = $texts[$current['lang']]['title'];

                    $encoded_path = implode('/', array_map('urlencode', explode('/', $paths[$current['lang']])));
					$fitem->link = $website->absolute_path().$encoded_path;
					
					switch($item->content)
					{
						case 'title':
							// no description
							break;

						case 'content':
							$html = $texts[$current['lang']]['section-main'];
                            $html = nvweb_template_tweaks($html);
                            $html = nvweb_template_convert_nv_paths($html);
                            $fitem->description = $html;
							break;
													
						case 'summary':
						default:
                            $fitem->description = $texts[$current['lang']]['section-main'];
                            $fitem->description = str_replace(
                                array('</p>', '<br />', '<br/>', '<br>'),
                                array('</p>'."\n", '<br />'."\n", '<br/>'."\n", '<br>'."\n"),
                                $fitem->description
                            );
							$fitem->description = core_string_cut($fitem->description, 500, '&hellip;');
							break;
					}
	
					$fitem->date = $rs[$x]->date_to_display;

                    // find an image to attach to the item
                    // A) first enabled image in item gallery
                    // B) first image on properties

                    $image = '';

                    if(!empty($rs[$x]->galleries))
                    {
                        $galleries = mb_unserialize($rs[$x]->galleries);
                        $photo = @array_shift(array_keys($galleries[0]));
                        if(!empty($photo))
                            $image = $website->absolute_path(false) . '/object?type=image&id='.$photo;
                    }

                    if(empty($image))
                    {
                        // no image found on galleries, look for image properties
                        $properties = property::load_properties("item", $rs[$x]->template, "item", $rs[$x]->id);

                        for($p=0; $p < count($properties); $p++)
                        {
                            if($properties[$p]->type=='image')
                            {
                                if(!empty($properties[$p]->value))
                                    $image = $properties[$p]->value;
                                else if(!empty($properties[$p]->dvalue))
                                    $image = $properties[$p]->dvalue;

                                if(is_array($image))
                                {
                                    $image = array_values($image);
                                    $image = $image[0];
                                }

                                if(!empty($image))
                                    $image = $website->absolute_path(false) . '/object?type=image&id='.$image;
                            }

                            // we only need the first image
                            if(!empty($image))
                                break;
                        }
                    }

                    if(!empty($image))
                    {
                        $fitem->image = $image;
	                    // feedly will only display images of >450px --> http://blog.feedly.com/2015/07/31/10-ways-to-optimize-your-feed-for-feedly/
						if(strpos($item->format, 'RSS')!==false)
							$fitem->description = '<img src="'.$image.'&width=640"><br />'.$fitem->description;
					}

					//$item->author = $contents->rows[$x]->author_name;
					$feed->addItem($fitem); 			
				}
			}
			
			// valid format strings are: RSS0.91, RSS1.0, RSS2.0, PIE0.1 (deprecated),
			// MBOX, OPML, ATOM, ATOM10, ATOM0.3, HTML, JS
			//echo $rss->saveFeed("RSS1.0", "news/feed.xml");
		}
		
		$xml = $feed->createFeed($item->format);

		if($item->format=="RSS2.0")
		{
			// add extra tweaks to improve the feed
			$xml = str_replace('<rss ', '<rss xmlns:atom="http://www.w3.org/2005/Atom" xmlns:webfeeds="http://webfeeds.org/rss/1.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" ', $xml);

			// also available:
			// <webfeeds:cover image="http://yoursite.com/a-large-cover-image.png" />\n
			// <webfeeds:accentColor>00FF00</webfeeds:accentColor>
			$xml = str_replace('<channel>', '<channel>'."\n\t\t".'<webfeeds:related layout="card" target="browser" />', $xml);
			$xml = str_replace('<channel>', '<channel>'."\n\t\t".'<webfeeds:logo>'.file::file_url($item->image).'</webfeeds:logo>', $xml);
			$xml = str_replace('<channel>', '<channel>'."\n\t\t".'<webfeeds:icon>'.file::file_url($website->favicon).'</webfeeds:icon>', $xml);
		}

		return $xml;
	}	

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_feeds WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>