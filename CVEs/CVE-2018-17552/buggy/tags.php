<?php	
nvweb_webget_load('menu');

function nvweb_tags($vars=array())
{
	global $website;
	global $DB;
	global $current;

    $out = '';

	switch($vars['mode'])
	{
		case 'top':
        case 'random':

            $search_url = nvweb_source_url('theme', 'search');
            if($search_url == $website->absolute_path())
                $search_url = NVWEB_ABSOLUTE.'/nvtags';

            $search_parameter = 'q';
            if(!empty($vars['request']))
                $search_parameter = $vars['request'];

            $search_url .= '?'.$search_parameter.'=';

            $categories = array();
            if(!empty($vars['categories']))
            {
                $categories = preg_split('/[,\s]+/', $vars['categories']);
                $categories = array_merge($categories, nvweb_menu_get_children($categories));
                $categories = array_filter($categories);
            }

            $tags = nvweb_tags_retrieve($vars['items'], $categories, $vars['mode']);
            $out = array();

            $extra = '';
            if(!empty($vars['class']))
                $extra = ' class="'.$vars['class'].'" ';

            foreach($tags as $tag => $times)
            {
                if(empty($tag))
                    continue;

                if($vars['tag']=='li')
                    $out[] = '<li><a href="'.$search_url.$tag.'" count="'.$times.'" '.$extra.'>'.$tag.'</a></li>';
                else if($vars['tag']=='span')
                    $out[] = '<span count="'.$times.'" '.$extra.'>'.$tag.'</span>'.$vars['separator'];
                else
                    $out[] = '<a href="'.$search_url.$tag.'" count="'.$times.'" '.$extra.'>'.$tag.'</a>'.$vars['separator'];
            }
            $out = implode("\n", $out);
			break;
	}
	
	return $out;
}

function nvweb_tags_retrieve($maxtags="", $categories=array(), $order='top', $search='', $lang='', $types=array("item", "product"))
{
    // TODO: implement a tags cache system to improve website render time

    global $website;
    global $DB;
    global $current;

    $tags = array();
    $extra = '';

    if(empty($lang))
        $lang = $current['lang'];

    if(!empty($categories))
    {
        $extra = ' AND
            (
                ( node_type = "structure" AND node_id IN('.implode(',', $categories).') ) OR
                ( node_type = "product" AND node_id IN('.implode(',', $categories).') ) OR
                ( node_type = "item" AND node_id IN('.implode(',', $categories).') )
            )
        ';
    }

    if(!empty($search))
        $extra.= ' AND text LIKE '.protect('%'.$search.'%'); // note: we will need to pass another filter after exploding the tags

    $DB->query(
        'SELECT text FROM nv_webdictionary
          WHERE website = '.$website->id.'
            AND node_type IN("'.implode('","', $types).'")
            AND subtype = "tags"
            AND lang = '.protect($lang).'
            '.$extra.'
          ORDER BY RAND()
        ',
        'array'
    );

    $rows = $DB->result();

    if(!empty($rows))
    {
        foreach($rows as $row)
        {
            $row_tags = explode(',', $row['text']);
            foreach($row_tags as $row_tag)
            {
                if(!empty($search))
                {
                    if(strpos(strtolower($row_tag), strtolower($search))===false)
                        continue;
                }

                if(isset($tags[$row_tag]))
                    $tags[$row_tag]++;
                else
                    $tags[$row_tag] = 1;
            }
        }
    }

    switch($order)
    {
        case 'random':
            // don't need to do anything! already randomized by SQL :)
            break;

        case 'top':
        default:
            arsort($tags);
    }

    if(!empty($maxtags))
        $tags = array_slice($tags, 0, $maxtags, true);

    return $tags;
}

?>