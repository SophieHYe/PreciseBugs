<?php
function nvweb_gallery($vars=array())
{
	global $website;
	global $DB;
	global $current;
    global $webgets;

	$out = '';
    $webget = 'gallery';
	
	// the request can come from a free item or from a category, so we have to load the first element available
	$item = NULL;

    $border = '';
    if(!empty($vars['border']))
        $border = '&border='.$vars['border'];

    if(!empty($vars['opacity']))
        $border .= '&opacity='.$vars['opacity'];

    $items = PHP_INT_MAX; // number of images shown, 0 => all gallery photos
    if(!empty($vars['items']) && $vars['items']!='0')
        $items = intval($vars['items']);

    $order = 'priority'; // display images using the assigned priority
    if(!empty($vars['order']))
        $order = $vars['order'];

	if(!empty($vars['item']))
	{
        if(is_object($vars['item']))
        {
            $item = $vars['item'];
        }
        else if(is_numeric($vars['item']))
        {
            $item = new item();
            $item->load($vars['item']);
        }
	}
	else if(!empty($vars['product']))
	{
        if(is_object($vars['product']))
        {
            $item = $vars['product'];
        }
        else if(is_numeric($vars['product']))
        {
            $item = new product();
            $item->load($vars['product']);
        }
	}
	else if($current['type']=='item')
	{
		// check publishing is enabled
		$enabled = nvweb_object_enabled($current['object']);				
		if($enabled || (($_REQUEST['preview']=='true' && $current['navigate_session']==1)))
			$item = $current['object'];
	}
	else if($current['type']=='product')
	{
		// check publishing is enabled
		$enabled = nvweb_object_enabled($current['object']);
		if($enabled || (($_REQUEST['preview']=='true' && $current['navigate_session']==1)))
			$item = $current['object'];
	}
	else if($current['type']=='structure')
	{
		$DB->query('
		    SELECT id, permission, date_published, date_unpublish
              FROM nv_items
             WHERE category = '.protect($current['object']->id).'
               AND website = '.$website->id.'
        ');
		$rs = $DB->first();
		$enabled = nvweb_object_enabled($rs);

		if($enabled || (($_REQUEST['preview']=='true' && $current['navigate_session']==1)))
		{	
			$item = new item();
			$item->load($rs->id);
		}
	}

	if($item==NULL) return '';
	
	if(empty($vars['width']) && empty($vars['height']))
	{
		$vars['width'] = 120;
		$vars['height'] = 90;
	}
	else if(empty($vars['height']))
		$vars['height'] = '';	
	else if(empty($vars['width']))
		$vars['width'] = '';			
	
	// which gallery model?
	$out = array();

	switch(@$vars['mode'])
	{
        case 'image':

            if(is_array($item->galleries))
                $gallery = $item->galleries[0];

            if(is_string($item->galleries))
            {
                $gallery = mb_unserialize($item->galleries);
                $gallery = $gallery[0];
            }

            // no images in the gallery?
            if(!is_array($gallery))
                return '';

            $gallery = nvweb_gallery_reorder($gallery, $order);

            $image_ids = array_keys($gallery);
            $position = intval($vars['position']);
            $image_selected = $image_ids[$position];

            // no image found at the requested position
            if(empty($image_selected))
                return '';

			list($image_title, $image_description) = nvweb_gallery_image_caption($image_selected, $gallery);

            if(!empty($vars['return']) && $vars['return']=='url')
            {
                $out[] = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline';
            }
            else if(!empty($vars['return']) && $vars['return']=='thumbnail')
            {
                $out[] = '<img src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].
	                            $border.'" alt="'.$image_description.'" title="'.$image_title.'" />';
            }
            else if(!empty($vars['return']) && $vars['return']=='thumbnail_url')
            {
                $out[] = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border;
            }
            else
                $out[] = '<div class="nv_gallery_item">
                            <a class="nv_gallery_a" href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline" rel="gallery[item-'.$item->id.']">
                                <img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].
	                                $border.'" alt="'.$image_description.'" title="'.$image_title.'" />
                            </a>
                        </div>';
            break;

		case 'greybox':
			/*
			var image_set = [{'caption': 'Flower', 'url': 'http://static.flickr.com/119/294309231_a3d2a339b9.jpg'},
				{'caption': 'Nice waterfall', 'url': 'http://www.widerange.org/images/large/plitvicka.jpg'}];
			*/			
			$out[] = '<div class="nv_gallery">';		
		
			$gallery = mb_unserialize($item->galleries);
			$gallery = $gallery[0];
            $gallery = nvweb_gallery_reorder($gallery, $order);

			$first = true;
			
			$jsout = "var image_set_".$item->id." = [";
			$preload = array();			
			
			foreach($gallery as $image => $dictionary)
			{
				list($image_title, $image_description) = nvweb_gallery_image_caption($image, $gallery);

				if($first)
				{
					$out[] = '<a href="#" onclick="return GB_showImageSet(image_set_'.$item->id.', 1);">
								<img class="nv_gallery_image" 
									 src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'"
									 alt="'.$image_description.'" title="'.$image_title.'" />
							 </a>';
				}
						
				if(!$first) $jsout .= ','."\n";
				
				$jsout .= '{"caption": "'.$image_title.'", "url": "'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline"}';
				$preload[] = "'".NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline';
				$first = false;
                $items--;
                if($items <= 0) break;
			}
			
			$jsout .= "];";
			nvweb_after_body('js', $jsout);
			nvweb_after_body('js', 'AJS.preloadImages('.implode(',', $preload).')');	
			
			$out[] = '<div style=" clear: both; "></div>';
			$out[] = '</div>';						
			break;

        case 'piecemaker':
            $gallery = mb_unserialize($item->galleries);
            $gallery = nvweb_gallery_reorder($gallery[0], $order);

            foreach($gallery as $image => $dictionary)
            {
	            list($image_title, $image_description) = nvweb_gallery_image_caption($image, $gallery);

                $out[] = '<Image Source="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'" Title="'.$image_title.'"></Image>';
                $items--;
                if($items <= 0) break;
            }
            break;

        case 'images':
            // plain IMG without links or divs
            // TO DO: add alt and title to the image
            if(is_array($item->galleries))
                $gallery = $item->galleries[0];

            if(is_string($item->galleries))
            {
                $gallery = mb_unserialize($item->galleries);
                $gallery = $gallery[0];
            }

            $gallery = nvweb_gallery_reorder($gallery, $order);
            $images = array_keys($gallery);

            if(empty($images))
                return '';

            foreach($images as $img)
            {
	            list($image_title, $image_description) = nvweb_gallery_image_caption($img, $gallery);

                $out[] = '<img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$img.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].
	                        $border.'" alt="'.$image_description.'" title="'.$image_title.'" />';
                $items--;
                if($items <= 0) break;
            }
            break;

        case 'image_links':
            // IMG wrapped by a link
            // TO DO: add alt and title to the image
            if(is_array($item->galleries))
                $gallery = $item->galleries[0];

            if(is_string($item->galleries))
            {
                $gallery = mb_unserialize($item->galleries);
                $gallery = $gallery[0];
            }

            $gallery = nvweb_gallery_reorder($gallery, $order);
            $images = array_keys($gallery);

            if(empty($images))
                return '';

            foreach($images as $img)
            {
	            list($image_title, $image_description) = nvweb_gallery_image_caption($img, $gallery);

                $out[] = '
                    <a class="nv_gallery_a" href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$img.'&amp;disposition=inline">
                        <img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$img.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].
	                        $border.'" alt="'.$image_description.'" title="'.$image_title.'" />
                    </a>';
                $items--;
                if($items <= 0) break;
            }
            break;
			
		case 'prettyphoto':
		case 'prettyPhoto':
		default:
			$out[] = '<div class="nv_gallery">';		
		
			if(is_array($item->galleries))
				$gallery = $item->galleries[0];

			if(is_string($item->galleries))		
			{
				$gallery = mb_unserialize($item->galleries);
				$gallery = $gallery[0];
			}

            $gallery = nvweb_gallery_reorder($gallery, $order);

			$first = true;
			
			foreach($gallery as $image => $dictionary)
			{
				if($vars['only_first']=='true')
				{
					$style = ' style="display: none;" ';
					if($first)
						$style = ' style="display: block;" ';
					$first = false;
				}

				list($image_title, $image_description) = nvweb_gallery_image_caption($img, $gallery);
				
				$out[] = '<div class="nv_gallery_item" '.$style.'>
							<a class="nv_gallery_a" href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline" rel="gallery[item-'.$item->id.']">
								<img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'"
									 alt="'.$image_description.'" title="'.$image_title.'" />									 
							</a>
						</div>';

                $items--;
                if($items <= 0) break;
			}	
			
			$out[] = '<div style=" clear: both; "></div>';
			$out[] = '</div>';			
			break;

	}
	
	$out = implode("\n", $out);		
	
	return $out;
}

function nvweb_gallery_reorder($gallery=array(), $order='priority')
{
    if(!is_array($gallery))
        $gallery = array();

    switch($order)
    {
        case 'random':
            uasort($gallery, function ($a, $b) {
                return rand(-1, 1);
            });
            break;

        case 'priority':
        default:
            // do nothing, the gallery is already ordered by priority
    }

    return $gallery;
}

function nvweb_gallery_image_caption($image, $gallery)
{
	global $current;

	$image_title = "";
	$image_description = "";

	if(is_array($gallery))
	{
		$image_title = $gallery[$image][$current['lang']];
		$image_description = $gallery[$image][$current['lang']];
	}

	if(empty($image_title))
	{
		// retrieve title and description from file
		$image_selected_obj = new file();
		$image_selected_obj->load($image);
        if(isset($image_selected_obj->description[$current['lang']]))
		    $image_description = $image_selected_obj->description[$current['lang']];
        if(isset($image_selected_obj->title[$current['lang']]))
		    $image_title = $image_selected_obj->title[$current['lang']];
	}

	return array($image_title, $image_description);
}

?>