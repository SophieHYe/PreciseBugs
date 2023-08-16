<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');

nvweb_webget_load("menu");
nvweb_webget_load("list");

function nvweb_product($vars=array())
{
	global $website;
	global $theme;
	global $current;
	global $template;
	global $structure;
	
	$out = '';

	$product = new product();

	$product_id = $current['object']->id;
	if(!empty($vars['pid']))
        $product_id = $vars['pid'];

    $product->load($product_id);

    if($product->website != $website->id)
        return;

    switch(@$vars['mode'])
	{
        case 'id':
            $out = $product->id;
            break;

        case 'title':
            $texts = webdictionary::load_object_strings("product", $product->id);
            $out = $texts[$current['lang']]['title'];
			if(!empty($vars['function']))
			    eval('$out = '.$vars['function'].'("'.$out.'");');
			break;

        case 'date':
            $ts = $product->date_to_display;
            // if no date, return nothing
            if(!empty($ts))
    			$out = nvweb_content_date_format(@$vars['format'], $ts);
			break;
			
		case 'date_created':
			$ts = $product->date_created;
			$out = $vars['format'];
			$out = nvweb_content_date_format($out, $ts);			
			break;
			
		case 'comments':
			// display number of published comments for the current product
			$out = nvweb_product_comments_count();
			break;

        case 'views':
            $out = $product->views;
            break;
			
		case 'summary':
            $length = 300;
            $allowed_tags = array();
            if(!empty($vars['length']))
                $length = intval($vars['length']);
			$texts = webdictionary::load_element_strings('product', $product->id);
			$text = $texts[$current['lang']]['main'];
            if(!empty($vars['allowed_tags']))
                $allowed_tags = explode(',', $vars['allowed_tags']);
			$out = core_string_cut($text, $length, '&hellip;', $allowed_tags);
			break;

        case 'author':
            if(!empty($product->author))
            {
                $nu = new user();
                $nu->load($product->author);
                $out = $nu->username;
                unset($nu);
            }

            if(empty($out))
                $out = $website->name;
            break;
			
		case 'structure':
			// force loading structure data
			nvweb_menu();
            $structure_id = $product->category;

			switch($vars['return'])
			{
				case 'path':
					$out = $structure['routes'][$structure_id];
					break;
					
				case 'title':
					$out = $structure['dictionary'][$structure_id];
					break;
					
				case 'action':
					$out = nvweb_menu_action($structure_id);
					break;
					
				default:
			}
			break;

        case 'tags':
            $tags = array();

            $search_url = nvweb_source_url('theme', 'search');
            if(!empty($search_url))
                $search_url .= '?q=';
            else
                $search_url = NVWEB_ABSOLUTE.'/nvtags?q=';

            if(empty($vars['separator']))
                $vars['separator'] = ' ';

            $class = 'product-tag';
            if(!empty($vars['class']))
                $class = $vars['class'];

            // check publishing is enabled
            $enabled = nvweb_object_enabled($product);

            if($enabled)
            {
                $texts = webdictionary::load_element_strings('product', $product->id);
                $itags = explode(',', $texts[$current['lang']]['tags']);
                if(!empty($itags))
                {
                    for($i=0; $i < count($itags); $i++)
                    {
                        if(empty($itags[$i])) continue;
                        $tags[$i] = '<a class="'.$class.'" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                    }
                }
            }

            $out = implode($vars['separator'], $tags);
            break;

        case 'sku':
            $out = $product->sku;
            break;

        case 'barcode':
            $out = $product->barcode;
            break;

        case 'size':
            $separator = value_or_default($vars['separator'], ' x ');

            switch($vars['return'])
            {
                case 'width':
                    $out = core_decimal2string($product->width);
                    break;

                case 'height':
                    $out = core_decimal2string($product->height);
                    break;

                case 'depth':
                    $out = core_decimal2string($product->depth);
                    break;

                case 'unit':
                    $out = $product->size_unit;
                    break;

                default:
                    $out =  core_decimal2string($product->width) . $separator .
                            core_decimal2string($product->height) . $separator .
                            core_decimal2string($product->depth) . ' ' .
                            $product->size_unit;
            }
            break;

        case 'weight':
            switch($vars['return'])
            {
                case 'value':
                    $out = core_decimal2string($product->weight);
                    break;

                case 'unit':
                    $out = $product->weight_unit;
                    break;

                default:
                    $out = core_decimal2string($product->weight) . ' ' . $product->weight_unit;
            }

            break;

        case 'stock':
            $out = $product->stock_available;

            if($product->inventory)
            {
                if ($out > 0 && isset($vars['in_stock']))
                    $out = $theme->t($vars['in_stock']);
                else if ($out == 0 && isset($vars['out_of_stock']))
                    $out = $theme->t($vars['out_of_stock']);
            }
            else
                $out = $theme->t($vars['in_stock']);

            break;

        case 'price':
            $price = $product->get_price();
            $out = core_price2string($price['current'], $product->base_price_currency, @$vars['return']);
            break;

        case 'old_price':
            // price is base_price + taxes
            // by default, return empty if old_price = current_price
            // current price may be different if the product is on sale

            $price = $product->get_price();

            if(empty($price['old']) || $price['current'] == $price['old'])
                $out = "";
            else
                $out = core_price2string($price['old'], $product->base_price_currency, @$vars['return']);

            break;


        case 'tax':
            $price = $product->get_price();

            switch($vars['return'])
            {
                case 'amount':
                    if($product->tax_class == 'custom')
                    {
                        $tax_amount = $price['tax_amount'];
                        $currency = product::currencies($product->base_price_currency, false);
                        if ($currency['placement'] == 'after')
                            $out = core_decimal2string($tax_amount) . ' ' . $currency['symbol'];
                        else
                            $out = $currency['symbol'] . ' ' . core_decimal2string($tax_amount);
                    }
                    else
                        $out = '';
                    break;

                case 'value':
                default:
                    // percentage / free / included
                    switch($product->tax_class)
                    {
                        case 'free':
                            $out = @value_or_default($theme->t($vars['tax_free']), '0 %');
                            break;

                        case 'included':
                            $out = @value_or_default($theme->t($vars['tax_included']), '');
                            break;

                        case 'custom':
                        default:
                            $out = core_decimal2string($product->tax_value) . ' %';
                            break;
                    }
            }
            break;

        case 'brand':
            $brand = new brand();
            $brand->load($product->brand);

            switch($vars['return'])
            {
                case 'id':
                    $out = $brand->id;
                    break;

                case 'image':
                    if(!empty($brand->image))
                        $out = file::file_url($brand->image);
                    break;

                case 'url':
                    if(!empty($brand->url))
                        $out = nvweb_prepare_link($brand->url);
                    break;

                case 'name':
                default:
                    // brand name
                    $out = $brand->name;
            }
            break;

        case 'add_to_cart':
            // TODO: 2 modes: AJAX callback or Redirect to cart (default)
            // TODO: decide how to capture and include the parameters quantity and option
            $link = nvweb_source_url('theme', 'cart');
            $link = $link . '?action=add_product&product='.$product->id.'&quantity=1';
            $out = $link;
            break;

		case 'section':
		case 'body':
			if(empty($vars['section'])) $vars['section'] = 'main';
			$section = "section-".$vars['section'];

            // check publishing is enabled
            $enabled = nvweb_object_enabled($product);
            $texts = NULL;

            // retrieve last saved text (is a preview request from navigate)
            if($_REQUEST['preview']=='true' && $current['navigate_session']==1)
                $texts = webdictionary_history::load_element_strings('product', $product->id, 'latest');
            // or last approved/saved text
            else if($enabled)
                $texts = webdictionary::load_element_strings('product', $product->id);

            // have we found any content?
            if(!empty($texts))
            {
                foreach($template->sections as $tsection)
                {
                    if($tsection['id'] == $vars['section'] || $tsection['code'] == $vars['section'])
                    {
                        switch($tsection['editor'])
                        {
                            case 'raw':
                                $out = nl2br($texts[$current['lang']][$section]);
                                break;

                            case 'html':
                            case 'tinymce':
                            default:
                                $out = $texts[$current['lang']][$section];
                                break;
                        }
                        break;
                    }
                }
            }
			break;

        default:
            break;
	}

	return $out;
}

function nvweb_product_comments_count($object_id = NULL)
{
	global $DB;
	global $website;
	global $current;

	if(empty($object_id))
        $object_id = $current['object']->id;

	$DB->query('SELECT COUNT(*) as total
				  FROM nv_comments
				 WHERE website = '.intval($website->id).'
				   AND object_type = "product"
				   AND object_id = '.intval($object_id).'
				   AND status = 0'
				);
													
	$out = $DB->result('total');
	
	return $out[0];
}

function nvweb_categories_products($categories=array(), $only_published=false, $max=NULL, $order='date')
{
    global $website;
    global $DB;
    global $current;
    global $webuser;

    if(!is_array($categories))
        $categories = array(intval($categories));

    if($categories[0] == NULL)
        $categories = array(0);

    $where = ' i.website = '.$website->id.'
               AND i.category IN ('.implode(",", $categories).')';

    if($only_published)
        $where .= ' AND (i.date_published = 0 OR i.date_published < '.core_time().')
                    AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')';

    // status (0 public, 1 private (navigate cms users), 2 hidden)
    $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
    $where .= ' AND i.permission <= '.$permission;

    // access permission (0 public, 1 web users only, 2 unidentified users, 3 selected web user groups)
    $access = 2;
    $access_extra = '';
    if(!empty($current['webuser']))
    {
        $access = 1;
        if(!empty($webuser->groups))
        {
            $access_groups = array();
            foreach($webuser->groups as $wg)
            {
                if(empty($wg))
                    continue;
                $access_groups[] = 'i.groups LIKE "%g'.$wg.'%"';
            }
            if(!empty($access_groups))
                $access_extra = ' OR (i.access = 3 AND ('.implode(' OR ', $access_groups).'))';
        }
    }

    $where .= ' AND (i.access = 0 OR i.access = '.$access.$access_extra.')';

    if(!empty($max))
        $limit = 'LIMIT '.$max;

    $orderby = nvweb_list_get_orderby($order);
	$orderby = str_replace(", IFNULL(s.position,0) ASC", "", $orderby); // remove s. order used exclusively at nvweb_list

    $DB->query('
        SELECT i.*, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate, d.text as title
        FROM nv_products i
         LEFT JOIN nv_webdictionary d ON
         	   d.website = i.website
			   AND d.node_type = "product"
			   AND d.subtype = "title"
			   AND d.node_id = i.id
			   AND d.lang = :lang
        WHERE '.$where.'
        '.$orderby.'
        '.$limit,
        'object',
        array(
            ':lang' => $current['lang']
        )
    );

    $rs = $DB->result();

    return $rs;
}

?>