<?php

function nvweb_blocks($vars=array())
{
	global $website;
	global $DB;
	global $current;
    global $webgets;
    global $webuser;
    global $theme;

    $webget = 'blocks';
    nvweb_blocks_init();

	$out = '';

    $access = array();
    $access[] = 0;
    if(empty($current['webuser'])) // 1: only signed in users, 2: only NON signed in users, 3: selected web user groups only
        $access[] = 2;
    else
    {
        $access[] = 1;
        $access[] = 3;
    }

    // blocks type cache
    if(empty($webgets[$webget]['block_types']))
        $webgets[$webget]['block_types'] = block::types();

	$block_types = $webgets[$webget]['block_types'];

    if($vars['mode']=='single')
    {
        $order_mode = 'single';
    }
    else
    {
        // get the index for the block type requested
        $bti = array_multidimensional_search($block_types, array('code' => $vars['type']));

        // how must we process this block type
        $order_mode = @$block_types[$bti]['order'];
        if(empty($order_mode) || $order_mode=='theme')
            $order_mode = @$vars['mode'];
    }

    // how many blocks of this type we have to show
    $howmany = @intval($block_types[$bti]['maximum']);
    if(empty($howmany) || ($howmany > intval(@$vars['number']) && !empty($vars['number'])))
		$howmany = intval(@$vars['number']) + 0;

    // which zone of the block we have to return
    if(empty($vars['zone']))
        $vars['zone'] = 'block';

    $categories = array();
    if(!empty($vars['categories']))
    {
        $categories = explode(',', $vars['categories']);
        $categories = array_filter($categories);
    }

    if(isset($current['object']->category))
        $categories[] = $current['object']->category;
    else
        $categories[] = $current['category'];

    $blocks = array();

    $categories_query = '';
    $exclusions_query = '';

    if(is_array($categories) && !empty($categories[0]))
    {
        foreach($categories as $cq)
        {
            $categories_query .= " OR INSTR(CONCAT(',', categories, ','), ',".intval($cq).",') > 0 ";
            $exclusions_query .= " AND INSTR(CONCAT(',', exclusions, ','), ',".intval($cq).",') = 0 ";
        }

        $categories_query = ' AND (categories = "" '.$categories_query.') ';
    }

	switch($order_mode)
	{
        case 'single':
            $query_type = '';
            if(!empty($vars['type']))
                $query_type = ' AND type = '.protect($vars['type']);

            $DB->query('
                SELECT id, type
                  FROM nv_blocks
                 WHERE enabled = 1
                   '.$query_type.'
                   AND website = '.$website->id.'
                   AND (date_published = 0 OR date_published < '.core_time().')
                   AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                   AND access IN('.implode(',', $access).')
                   '.$categories_query.'
                   '.$exclusions_query.'
                   AND id = '.protect($vars['id'])
            );
            $row = $DB->first();

            if(!empty($row))
            {
                $blocks[] = $row->id;
                $vars['type'] = $row->type;
            }
            break;

        case 'priority':
		case 'ordered':
            $DB->query('
                SELECT *
                  FROM nv_blocks
                 WHERE type = '.protect($vars['type']).'
                   AND enabled = 1
                   AND website = '.$website->id.'
                   AND (date_published = 0 OR date_published < '.core_time().')
                   AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                   AND access IN('.implode(',', $access).')
                   '.$categories_query.'
                   '.$exclusions_query.'
                ORDER BY position ASC
            ');
			$rows = $DB->result();

			foreach($rows as $row)
			{				
				if(!nvweb_block_enabled($row)) continue;
                $row = (array)$row;
                $blocks[] = $row['id'];
			}
			break;
		
		default:			
		case 'random':
            // "random" gets priority blocks first
            // retrieve fixed blocks

            $DB->query('
                SELECT *
                  FROM nv_blocks
                 WHERE type = '.protect($vars['type']).'
                   AND enabled = 1
                   AND website = '.$website->id.'
                   AND (date_published = 0 OR date_published < '.core_time().')
                   AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                   AND access IN('.implode(',', $access).')
                   AND fixed = 1
                   '.$categories_query.'
                   '.$exclusions_query.'
              ORDER BY position ASC
            ');

			$fixed_rows = $DB->result();
			$fixed_rows_ids = $DB->result('id');

        	if(empty($fixed_rows_ids))
        		$fixed_rows_ids = array(0);

            // now retrieve the other blocks in random order
			$DB->query('
			    SELECT *
                  FROM nv_blocks
                 WHERE type = '.protect($vars['type']).'
                   AND enabled = 1
                   AND website = '.$website->id.'
                   AND (date_published = 0 OR date_published < '.core_time().')
                   AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                   AND access IN('.implode(',', $access).')
                   AND id NOT IN('.implode(",", $fixed_rows_ids).')
                   '.$categories_query.'
                   '.$exclusions_query.'
                 ORDER BY RAND()
            ');
		
			$random_rows = $DB->result();

			// mix rows (fixed and random)

			// fixed position rows
    		foreach($fixed_rows as $fr)
			{
				if(!nvweb_block_enabled($fr)) continue;
                $blocks[$fr->position] = $fr->id;
			}

			// random position rows
			$pos = 0;
			foreach($random_rows as $rr)
			{
				if(!nvweb_block_enabled($rr)) continue;
				
				// find next free position
				$free = false;
				while(!$free)
				{
					$free = empty($blocks[$pos]);
					if(!$free)
						$pos++;
				}
                $blocks[$pos] = $rr->id;
			}
			
            // sort array by key
            ksort($blocks);
			break;			
	}

    // render the blocks found
    $shown = 0;
    $position = 1;
    $block_objects = array();

    foreach($blocks as $id)
    {
        if($howmany > 0 && $shown >= $howmany) break;

        $block = new block();
        $block->load($id);

        $bt = 'block';
        for($bti=0; $bti < count($block_types); $bti++)
        {
            if($block->type == $block_types[$bti]['code'])
            {
                $bt = $block_types[$bti]['type'];
                break;
            }
        }

        // SKIP all blocks until a certain position
        if(isset($vars['position']) && $vars['position']!=$position)
        {
            $position++;
            continue;
        }

        // RENDER block zone
        switch($vars['zone'])
        {
            case 'object':
                $block_objects[] = clone $block;
                break;

            case 'title':
                $out.= '<span class="block-'.$vars['type'].'-title" zone="title" ng-block-id="'.$block->id.'">'.$block->dictionary[$current['lang']]['title'].'</span>';
                break;

            case 'content':
            case 'block':
            default:
                if($bt=='theme')
                {
                    $fn = 'nvweb_'.$website->theme.'_blocks_render';

                    $out.= '<div class="block-'.$vars['type'].'" ng-block-id="'.$block->id.'">';
                    $out.= $fn($block, $vars);
                    $out.= '</div>'."\n";
                }
                else
                {
                    $out.= '<div class="block-'.$vars['type'].'" ng-block-id="'.$block->id.'">';
                    $out.= nvweb_blocks_render(
                        $vars['type'],
                        $block->trigger,
                        $block->action,
                        $vars['zone'],
                        $block,
                        $vars
                    );
                    $out.= '</div>'."\n";
                }
                break;
        }

        // the block requested at a CERTAIN POSITION was this, we've finished
        if(isset($vars['position']) && $vars['position']==$position)
            break;

        $shown++;
        $position++;
    }

    if($vars['zone']=='object')
    {
        $out = array($block_objects, count($block_objects));
    }

	return $out;
}

function nvweb_blocks_init()
{
    global $webgets;
    global $website;
    global $theme;
    global $current;

    $webget = 'blocks';

    if(!isset($webgets[$webget]))
    {
        $webgets[$webget] = array();

        global $lang;
        if(empty($lang))
        {
            $lang = new language();
            $lang->load($current['lang']);
        }

        // default translations
        $webgets[$webget]['translations'] = array(
            'vote' => t(560, 'Vote'),
            'votes' => t(352, 'Votes'),
            'results' => t(562, 'Results')
        );

        // theme translations
        // if the web theme has custom translations for this string subtypes, use it (for the user selected language)
        /* just add the following translations to your json theme dictionary:

            "vote": "Vote",
            "results": "Results",
        */

        if(!empty($website->theme) && method_exists($theme, 't'))
        {
            foreach($webgets[$webget]['translations'] as $code => $text)
            {
                $theme_translation = $theme->t($code);

                if(!empty($theme_translation) && $theme_translation!=$code)
                    $webgets[$webget]['translations'][$code] = $theme_translation;
            }
        }
    }
}

function nvweb_blocks_render($type, $trigger, $action, $zone="", $block=NULL, $vars=array())
{
	global $current;
	global $website;
    global $theme;

    $block_types = array_merge((array)$website->block_types, (array)$theme->blocks);

	foreach($block_types as $btype)
	{
        $btype = (array)$btype;

		if($btype['title']==$type || $btype['code']==$type)
            $type  = $btype;
	}
	
	$lang = $current['lang'];
	
	$sizes = '';
    $width = '';
    $height = '';
    $border = true;
    $opacity = '';

	if(!empty($type['width']))      $width = $type['width'];
	if(!empty($type['height']))     $height = $type['height'];

    // $vars sizes have preference over block type  ($vars is used in nv lists)
    if(!empty($vars['width']))      $width = $vars['width'];
    if(!empty($vars['height']))     $height = $vars['height'];
    if(!empty($vars['border']))     $border = $vars['border'];
    if(!empty($vars['opacity']))    $opacity = '&opacity='.$vars['opacity'];

    if(!empty($width))  $sizes.= ' width="'.$width.'" ';
	if(!empty($height)) $sizes.= ' height="'.$height.'" ';
	
	switch($trigger['trigger-type'][$lang])
	{
		case 'image':
		    if($vars['return'] == 'url')
		        return NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-image'][$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity;
            else
			    $trigger_html = '<img src="'.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-image'][$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity.'" '.$sizes.' />';
			break;
			
		case 'rollover':
            if($vars['return'] == 'url')
                return NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover'][$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity;
            else
			    $trigger_html = '
                    <img src="'.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover'][$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity.'"
                         '.$sizes.'
                         onmouseover="this.src=\''.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover']['active-'.$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity.'\';"
                         onmouseout="this.src=\''.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover'][$lang].'&width='.$width.'&height='.$height.'&border='.$border.$opacity.'\';" />';
			break;
			
		case 'flash':
			$clickTAG = '&clickTAG='.urlencode($action['action-web'][$lang]);
		
			$trigger_html = '
				<!--[if !IE]> -->
					<object type="application/x-shockwave-flash"
				            data="'.NVWEB_ABSOLUTE.'/object?type=file&id='.$trigger['trigger-flash'][$lang].'&disposition=inline'.$clickTAG.'" '.$sizes.'>
				<!-- <![endif]-->
				<!--[if IE]>
					<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
						    codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0"  '.$sizes.'>
						        <param name="movie" value="'.NVWEB_ABSOLUTE.'/object?type=file&id='.$trigger['trigger-flash'][$lang].'&disposition=inline'.$clickTAG.'" />
				<!-->
				<!--dgx-->
								<param name="menu" value="false" />
								<p>Flash is not installed.</p>
					</object>
				<!-- <![endif]-->
			';
					
			// disable any action, flash do not allow being wrapped by <a>
			$action = array();
			break;

        case 'video':
            if(strpos($trigger['trigger-video'][$lang], '#')!==false)
            {
                list($provider, $reference) = explode('#', $trigger['trigger-video'][$lang]);
            }
            else
            {
                $provider = 'file';
                $reference = $trigger['trigger-video'][$lang];
            }
            $trigger_html = file::embed($provider, $reference);
            break;

        case 'links':
            $tl = $trigger['trigger-links'][$lang];
            $trigger_html = array();
            $prefix = '';
            $suffix = '';

            if($vars['wrapper']=='ul')
            {
                $prefix = '<li>';
                $suffix = '</li>';
            }

            foreach($tl['link'] as $key => $link)
            {
                $new_window = '';

                if(@$tl['access'][$key] > 0) // link is hidden!
                    continue;

                $title = $tl['title'][$key];

                $link = nvweb_prepare_link($link);

                if($tl['new_window'][$key]=='1')
                    $new_window = ' target="_blank" ';

                // icons, by the moment only compatible with Font Awesome
                $icon = '';
                if(!empty($tl['icon'][$key]))
                    $icon = '<i class="fa '.$tl['icon'][$key].'"></i>';

                if(!empty($title) && !empty($icon))
                    $icon.= " ";

                $trigger_html[] = $prefix.'<a href="'.$link.'"'.$new_window.'>'.$icon.$title.'</a>'.$suffix;
            }
            $glue = '';
            if(!empty($vars['separator']))
                $glue = $vars['separator'];
            $trigger_html = implode($glue, $trigger_html);

            if(!empty($vars['wrapper']))    // "ul", for example
            {
                $trigger_html = '<'.$vars['wrapper'].' class="'.$vars['wrapper_class'].'">'.$trigger_html.'</'.$vars['wrapper'].'>';
            }
            break;
			
		case 'html':
			$trigger_html = htmlspecialchars_decode($trigger['trigger-html'][$lang]);
			break;

        case 'content':
            $trigger_html = htmlspecialchars_decode($trigger['trigger-content'][$lang]);
            $trigger_html = str_replace('\"', '', $trigger_html);
            break;

        case 'title':
            $trigger_html = $block->dictionary[$current['lang']]['title'];
            break;

		default:
		case '':	// hidden
			break;
	}

    if($zone=='content' || $zone=='trigger')
        return $trigger_html;

    $action = nvweb_blocks_render_action($action, $trigger_html, $lang);

	return $action;
	
}

function nvweb_blocks_render_action($action, $trigger_html, $lang, $return_url=false)
{
	switch(@$action['action-type'][$lang])
    {
        case 'web':
            $url = nvweb_prepare_link($action['action-web'][$lang]);
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        case 'web-n':
            $url = nvweb_prepare_link($action['action-web'][$lang]);
            $action = '<a href="'.$url.'" target="_blank">'.$trigger_html.'</a>';
            break;

        case 'javascript':
            $action = '<a href="javascript:void(0);" onclick="'.$action['action-javascript'][$lang].'">'.$trigger_html.'</a>';
            break;

        case 'file':
            $url = NVWEB_ABSOLUTE.'/object?type=file&id='.$action['action-file'][$lang];
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        case 'image':
            $url = NVWEB_ABSOLUTE.'/object?type=image&id='.$action['action-image'][$lang];
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        default:
        case '':	// do nothing
            $action = $trigger_html;
            break;
    }

    if($return_url)
        return $url;

    return $action;
}

function nvweb_blocks_render_poll($object)
{
    global $current;
    global $webgets;
    global $session;

    $webget = 'blocks';
    nvweb_blocks_init();

    if($object->class != 'poll')
        return;

    if(!isset($session['polls'][$object->id]))
        $session['polls'][$object->id] = false;

    if(($_GET['poll_vote']==$object->id && !empty($_POST['vote'])))
    {
        // submit vote and show results
        if(empty($session['polls'][$object->id]))
        {
            foreach($object->trigger[$current['lang']] as $i => $answer)
            {
                if($answer['code'] == $_POST['vote'])
                    $object->trigger[$current['lang']][$i]['votes'] = $object->trigger[$current['lang']][$i]['votes'] + 1;
            }
            $object->save();
            $session['polls'][$object->id] = true;
        }

        $out = nvweb_blocks_render_poll_results($object);

        echo $out;
        nvweb_clean_exit();
    }
    else if($_GET['poll_result']==$object->id)
    {
        echo nvweb_blocks_render_poll_results($object);
        nvweb_clean_exit();
    }
    else if(!empty($session['polls'][$object->id]))
    {
        $out = '<div class="block-poll '.$object->type.'-'.$object->id.'" data-id="'.$object->id.'">';
        $out .= nvweb_blocks_render_poll_results($object);
        $out.= '</div>';
    }
    else
    {
        $out = '<div class="block-poll '.$object->type.'-'.$object->id.'" data-id="'.$object->id.'">';
        $out.= '<form action="?" method="post" id="block_poll_'.$object->type.'-'.$object->id.'_form">';

        foreach($object->trigger[$current['lang']] as $answer)
        {
            $out .= '<div class="block-poll-answer">';
            $out .= '   <input type="radio" id="'.$object->type.'-'.$object->id.'-answer-'.$answer['code'].'" name="'.$object->type.'-'.$object->id.'-answer" value="'.$answer['code'].'" />';
            $out .= '   <label for="'.$object->type.'-'.$object->id.'-answer-'.$answer['code'].'">'.$answer['title'].'</label>';
            $out .= '</div>';
        }

        $out .= '<div class="block-poll-actions">';
        $out .= '   <input type="submit" id="'.$object->type.'-'.$object->id.'-submit" value="'.$webgets[$webget]['translations']["vote"].'" />';
        $out .= '   <input type="button" value="'.$webgets[$webget]['translations']["results"].'" />';
        $out .= '</div>';

        $out.= '</form>';
        $out.= '</div>';

        nvweb_after_body("js", '
            function block_poll_'.$object->id.'_vote()
            {
                if(document.querySelector("input[name=\"'.$object->type.'-'.$object->id.'-answer\"]:checked"))
                {
                    var vote_value = document.querySelector("input[name=\"'.$object->type.'-'.$object->id.'-answer\"]:checked").value;

                    var request = new XMLHttpRequest();
                    request.open("POST", "?poll_vote='.$object->id.'", true);
                    request.setRequestHeader("Content-type","application/x-www-form-urlencoded");

                    request.onreadystatechange = function() {
                      if (this.readyState === 4) {
                        if (this.status >= 200 && this.status < 400)
                        {
                            var resp = this.responseText;
                            document.querySelector(".'.$object->type.'-'.$object->id.'").innerHTML = resp;
                        }
                        else
                        {
                          // Error
                        }
                      }
                    };

                    request.send("poll='.$object->id.'&vote=" + vote_value);
                    request = null;

                    return false;
                }
                else
                    return false;
            }

            function block_poll_'.$object->id.'_result()
            {
                var request = new XMLHttpRequest();
                request.open("GET", "?poll_result='.$object->id.'", true);

                request.onreadystatechange = function() {
                  if (this.readyState === 4) {
                    if (this.status >= 200 && this.status < 400)
                    {
                        var resp = this.responseText;
                        document.querySelector(".'.$object->type.'-'.$object->id.'").innerHTML = resp;
                    }
                    else
                    {
                      // Error
                    }
                  }
                };

                request.send();
                request = null;

                return false;
            }

            document.querySelector("form[id=block_poll_'.$object->type.'-'.$object->id.'_form]").onsubmit = block_poll_'.$object->id.'_vote;
            document.querySelector(".block-poll-actions input[type=button]").onclick = block_poll_'.$object->id.'_result;
        ');
    }

    return $out;
}

function nvweb_blocks_render_poll_results($object)
{
    global $webgets;
    global $current;

    $out = '';
    $webget = 'blocks';

    // calculate totals
    $votes_array = array_map(function($k) { return $k['votes']; }, array_values($object->trigger[$current['lang']]));
    $votes_count = array_sum($votes_array);

    foreach($object->trigger[$current['lang']] as $i => $answer)
    {
        $percentage = $answer['votes'] / $votes_count * 100;
        $out .= '<div class="block-poll-result">';
        $out .= '   <span id="'.$object->type.'-'.$object->id.'-answer-'.$answer['code'].'" data-percentage="'.round($percentage, 1).'" data-votes="'.$answer['votes'].'">'.round($percentage, 1).' %</span>';
        $out .= '   <label for="'.$object->type.'-'.$object->id.'-answer-'.$answer['code'].'">'.$answer['title'].'</label>';
        $out .= '</div>';
    }

    $out .= '<div class="block-poll-results-count">'.$votes_count.' '.$webgets[$webget]['translations']["votes"].'</div>';

    return $out;
}


function nvweb_block_enabled($object)
{
    global $current;

	$enabled = ($object->enabled=='1');
	$enabled = $enabled && (empty($object->date_published) || ($object->date_published < core_time()));
	$enabled = $enabled && (empty($object->date_unpublish) || ($object->date_unpublish > core_time()));

	// check access
	if(isset($object->access))
	{
		$access = true;
		
		switch($object->access)
		{
			case 2:	// accessible to NOT SIGNED IN visitors
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$enabled = $enabled && $access;
	}

    // selection/exclusion of free association items (category filter is already integrated in the query)
    if($current['type']=='item' && $current['object']->association == "free")
    {
        $elements = @json_decode($object->elements, true);
        if(!empty($elements))
        {
            if(!empty($elements['selection']))
            {
                if(!in_array($current['id'], $elements['selection']))
                    $enabled = false;
            }

            if(!empty($elements['exclusions']))
            {
                if(in_array($current['id'], $elements['exclusions']))
                    $enabled = false;
            }
        }
    }
	
	return $enabled;
}

?>