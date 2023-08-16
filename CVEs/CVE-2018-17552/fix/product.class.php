<?php
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/brands/brand.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary_history.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');

class product
{
	public $id;
	public $website;
	public $category;
	public $template;
    public $date_to_display;
	public $date_published;
	public $date_unpublish;
	public $galleries;
	public $date_created;
	public $date_modified;
	public $comments_enabled_to; // 0 => nobody, 1=>registered, 2=>everyone
	public $comments_moderator; // user_id
	public $access; // 0 => everyone, 1 => logged in, 2 => not logged in, 3 => selected webuser groups
    public $groups;
	public $permission; // 0 => public, 1 => private (only navigate cms users), 2 => hidden
	public $views;
	public $author;
	public $votes;
	public $score;
	public $position;

	public $brand;

    public $sku; // stock-keeping unit (must be unique!)
    public $barcode;
    public $type;   // defaults: 1 => shippable (standard), 2 => downloadable

    public $width;
    public $height;
    public $depth;
    public $size_unit;

    public $weight;
    public $weight_unit;

    public $inventory;
    public $stock_available;   // including all variants

    public $cost;
    public $cost_currency;
    public $base_price;
    public $base_price_currency;
    public $tax_class;
    public $tax_value;

    public $offer_begin_date;
    public $offer_end_date;
    public $offer_price;

    // reserved for future use
    /*
        public $allow_preorder; // can be purchased even when no stock is available
        public $hide_if_no_stock;   // hide the product when no stock available
        public $low_stock_threshold; // when the product has that number of units, it is considered "low stock"
        public $product_available_date;
    	public $variants;
    */

    public $options;

    public $dictionary;
    public $paths;
	public $properties;

    private $_comments_count;

    static $top_products;
		
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_products 
						WHERE id = '.intval($id).'
						  AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];

		$this->id				= $main->id;
		$this->website   		= $main->website;
		$this->category			= $main->category;
		$this->template			= $main->template;
		$this->date_to_display	= (empty($main->date_to_display)? '' : $main->date_to_display);
		$this->date_published	= (empty($main->date_published)? '' : $main->date_published);
		$this->date_unpublish	= (empty($main->date_unpublish)? '' : $main->date_unpublish);
		$this->date_created		= $main->date_created;
		$this->date_modified	= $main->date_modified;		
		$this->galleries		= mb_unserialize($main->galleries);
		$this->comments_enabled_to = $main->comments_enabled_to;
		$this->comments_moderator = $main->comments_moderator;
		$this->access			= $main->access;	
		$this->permission		= $main->permission;		
		$this->author			= $main->author;
		$this->views			= $main->views;
		$this->votes			= $main->votes;
		$this->score			= $main->score;
		$this->position			= $main->position;

		$this->dictionary		= webdictionary::load_element_strings('product', $this->id);
		$this->paths			= path::loadElementPaths('product', $this->id);

        // to get the array of groups first we remove the "g" character
        $this->groups           = $main->groups;
        if(!is_array($this->groups))
        {
            // to get the array of groups first we remove the "g" character
            $groups = str_replace('g', '', $this->groups);
            $this->groups = explode(',', $groups);
        }

        if(!is_array($this->groups))
            $this->groups = array($this->groups);

        $this->brand            =  $main->brand;

        $this->sku              =  $main->sku;  // must be unique!
        $this->barcode          =  $main->barcode;

        $this->width            =  $main->width + 0;
        $this->height           =  $main->height + 0;
        $this->depth            =  $main->depth + 0;
        $this->size_unit        =  $main->size_unit;

        $this->weight           =  $main->weight + 0;
        $this->weight_unit      =  $main->weight_unit;

        $this->type             =  $main->type;
        $this->inventory        =  $main->inventory;
        $this->stock_available  =  $main->stock_available + 0; // including all variants

        $this->base_price           =  $main->base_price + 0;
        $this->base_price_currency  =  $main->base_price_currency;
        $this->tax_class            =  $main->tax_class;
        $this->tax_value            =  $main->tax_value + 0;
        $this->cost                 =  $main->cost + 0;
        $this->cost_currency        =  $main->cost_currency;

        $this->offer_price           =  $main->offer_price + 0;
        $this->offer_begin_date      =  $main->offer_begin_date;
        $this->offer_end_date        =  $main->offer_end_date;

        /*
         * future use
        public $options;
         */
    }
	
	public function load_from_post()
	{
		global $website;
		global $user;

		$this->category			= intval($_REQUEST['category']);
		$this->template			= $_REQUEST['template'];
		$this->author			= intval($_REQUEST['product-author']);
		$this->brand			= $_REQUEST['product-brand-id'];

		if((!empty($this->brand) && !is_numeric($this->brand)) || $this->brand === 0)
        {
            // new brand! insert new object into database
            $brand = new brand();
            $brand->name = trim($_REQUEST['product-brand']);
            $brand->save();
            $this->brand = $brand->id;
        }

		$this->date_to_display	= (empty($_REQUEST['date_to_display'])? '' : core_date2ts($_REQUEST['date_to_display']));
		$this->date_published	= (empty($_REQUEST['date_published'])? '' : core_date2ts($_REQUEST['date_published']));
		$this->date_unpublish	= (empty($_REQUEST['date_unpublish'])? '' : core_date2ts($_REQUEST['date_unpublish']));
		$this->access			= intval($_REQUEST['access']);

        $this->groups	        = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		if($user->permission("products.publish") != 'false')
			$this->permission = intval($_REQUEST['permission']);

        // if comment settings were not visible, keep the original values
        if(isset($_REQUEST['product-comments_enabled_to']))
        {
            $this->comments_enabled_to 	= intval($_REQUEST['product-comments_enabled_to']);
            $this->comments_moderator 	= intval($_REQUEST['product-comments_moderator']);
        }

		// language strings and options
		$this->dictionary = array();
		$this->paths = array();

		$template = $this->load_template();

		$fields = array('title', 'tags');
		
		if(!is_array($template->sections))
            $template->sections = array('id' => 'main');
		
		foreach($template->sections as $section)
		{			
			if(is_object($section)) 
				$section = (array) $section;

			if(is_array($section))
				$section = $section['id'];
			
			$fields[] = 'section-'.$section;	
		}
		
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

		// image galleries
		$this->galleries = array();
		
		$items = explode("#", $_REQUEST['products-gallery-elements-order']);
		if(!is_array($items)) $items = array();
		$gallery_items = array();
		$gallery_items[0] = array();
		if(!is_array($website->languages)) $website->languages = array();
		foreach($items as $item)
		{
			if(empty($item)) continue;
			
			// capture image captions
			$gallery_items[0][$item] = array();
			
			foreach($website->languages_list as $lang)
			{
				$gallery_items[0][$item][$lang] = $_REQUEST['products-gallery-item-'.$item.'-dictionary-'.$lang];
			}
		}

		$this->galleries = $gallery_items;
		// galleries[0] = array( [id-file] => array(es => '', ca => '',.. ), [id-file-2] => array... )

        $this->sku              =  $_REQUEST['product-sku'];
        $this->barcode          =  $_REQUEST['product-barcode'];

        $this->width            =  core_string2decimal($_REQUEST['product-width']);
        $this->height           =  core_string2decimal($_REQUEST['product-height']);
        $this->depth            =  core_string2decimal($_REQUEST['product-depth']);
        $this->size_unit        =  $_REQUEST['product-size_unit'];

        $this->weight           =  core_string2decimal($_REQUEST['product-weight']);
        $this->weight_unit      =  $_REQUEST['product-weight_unit'];

        $this->inventory        =  $_REQUEST['product-track_inventory'];
        $this->stock_available  =  $_REQUEST['product-stock_available'];

        $this->type             =  $_REQUEST['product-type'];

        $this->base_price           =  core_string2decimal($_REQUEST['product-base_price']);
        $this->base_price_currency  =  $_REQUEST['product-base_price_currency'];
        $this->tax_class            =  $_REQUEST['product-tax_class'];
        $this->tax_value            =  core_string2decimal($_REQUEST['product-tax_value']);
        $this->cost                 =  core_string2decimal($_REQUEST['product-cost']);
        $this->cost_currency        =  $_REQUEST['product-cost_currency'];

        if($_REQUEST['product-offer']=='1')
        {
            $this->offer_price = core_string2decimal($_REQUEST['product-offer_price']);
            $this->offer_begin_date = (empty($_REQUEST['product-offer_begin_date']) ? '' : core_date2ts($_REQUEST['product-offer_begin_date']));
            $this->offer_end_date = (empty($_REQUEST['product-offer_end_date']) ? '' : core_date2ts($_REQUEST['product-offer_end_date']));
        }
        else
        {
            // reset any offer settings
            $this->offer_price = NULL;
            $this->offer_begin_date = NULL;
            $this->offer_end_date = NULL;
        }
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
		global $user;
		global $events;

        $affected_rows = 0;

		if($user->permission("products.delete") == 'false')
			throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));

		if(!empty($this->id) && !empty($this->website))
		{
		    // TODO: remove variants, price-listings, etc.

			// remove dictionary elements
			webdictionary::save_element_strings('product', $this->id, array(), $this->website);
			
			// remove path elements
			path::saveElementPaths('product', $this->id, array(), $this->website);
			
			// remove all votes assigned to element
			webuser_vote::remove_object_votes('product', $this->id);

            // remove all element properties
            property::remove_properties('product', $this->id, $this->website);

            // remove grid notes
            grid_notes::remove_all('product', $this->id);

            // finally remove the product
			$DB->execute('
			    DELETE FROM nv_products
				 WHERE id = '.intval($this->id).'
				   AND website = '.$this->website
            );

			$affected_rows = $DB->get_affected_rows();

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'product',
                    'delete',
                    array(
                        'product' => $this
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
		global $user;

		if(!empty($user->id))
		{
			if( $user->permission("products.create") == 'false'    ||
				!structure::category_allowed($this->category)
			)
				throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
		}

		$this->date_created  = core_time();		
		$this->date_modified = core_time();

        if(empty($this->comments_enabled_to))
        {
            // apply default comment settings from website properties
            $this->comments_enabled_to = $website->comments_enabled_for;
            $this->comments_moderator = $website->comments_default_moderator;
            if($this->comments_moderator == 'c_author')
                $this->comments_moderator = $this->author;
        }

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		if(empty($this->website))
			$this->website = $website->id;

		if(!empty($user->id) && $user->permission("products.publish") == 'false')
		{
			if($this->permission == 0)
				$this->permission = 1;
		}

		if(empty($this->position))
		{
			$last_position_in_category = $DB->query_single('MAX(position)', 'nv_products', ' category = '.value_or_default($this->category, 0));
			$default_position = $last_position_in_category + 1;
		}

        $ok = $DB->execute('
            INSERT INTO nv_products
                (id, website, category, type, template, author, brand,
                 date_to_display, date_published, date_unpublish, date_created, date_modified,
                 sku, barcode, base_price, base_price_currency, tax_class, tax_value, cost, cost_currency,
                 offer_price, offer_begin_date, offer_end_date,
                 width, height, depth, size_unit, weight, weight_unit,
                 inventory, stock_available, 
                 options,
                 galleries, comments_enabled_to, comments_moderator,
                 access, groups, permission, views, votes, score, position)
            VALUES
                (:id, :website, :category, :type, :template, :author, :brand,
                 :date_to_display, :date_published, :date_unpublish, :date_created, :date_modified,
                 :sku, :barcode, :base_price, :base_price_currency, :tax_class, :tax_value, :cost, :cost_currency,
                 :offer_price, :offer_begin_date, :offer_end_date,
                 :width, :height, :depth, :size_unit, :weight, :weight_unit,
                 :inventory, :stock_available,
                 :options,
                 :galleries, :comments_enabled_to, :comments_moderator,
                 :access, :groups, :permission, :views, :votes, :score, :position)
             ',
            array(
                ":id" => 0,
                ":website" => $this->website,
                ":category" => value_or_default($this->category, 0),
                ":template" => value_or_default($this->template, ''),
                ":type" => value_or_default($this->type, 0),
                ":brand" => value_or_default($this->brand, 0),
                ":date_to_display" => intval($this->date_to_display),
                ":date_published" => intval($this->date_published),
                ":date_unpublish" => intval($this->date_unpublish),
                ":date_created" => $this->date_created,
                ":date_modified" => $this->date_modified,
                ":sku" =>  value_or_default($this->sku, ''),
                ":barcode" =>  value_or_default($this->barcode, ''),
                ":base_price" =>  value_or_default($this->base_price, 0),
                ":base_price_currency" =>  value_or_default($this->base_price_currency, ""),
                ":tax_class" =>  value_or_default($this->tax_class, "included"),
                ":tax_value" =>  value_or_default($this->tax_value, 0),
                ":cost" =>  value_or_default($this->cost, 0),
                ":cost_currency" =>  value_or_default($this->cost_currency, ""),
                ":offer_price" => value_or_default($this->offer_price, 0),
                ":offer_begin_date" => intval($this->offer_begin_date),
                ":offer_end_date" => intval($this->offer_end_date),
                ":width" => value_or_default($this->width, 0),
                ":height" => value_or_default($this->height, 0),
                ":depth" => value_or_default($this->depth, 0),
                ":size_unit" => value_or_default($this->size_unit, 'cm'),
                ":weight" => value_or_default($this->weight, 0),
                ":weight_unit" => value_or_default($this->weight_unit, 'kg'),
                ":inventory" => value_or_default($this->inventory, 0),
                ":stock_available" => value_or_default($this->stock_available, 0),
                ":options" => json_encode($this->options),
                ":author" => value_or_default($this->author, 0),
                ":galleries" => serialize($this->galleries),
                ":comments_enabled_to" => value_or_default($this->comments_enabled_to, 0),
                ":comments_moderator" => value_or_default($this->comments_moderator, 0),
                ":access" => value_or_default($this->access, 0),
                ":groups" => $groups,
                ":permission" => value_or_default($this->permission, 0),
                ":views" => 0,
                ":votes" => 0,
                ":score" => 0,
	            ":position" => value_or_default($this->position, $default_position)
            )
        );
			
		if(!$ok)
			throw new Exception($DB->get_last_error());

		$this->id = $DB->get_last_id();

		webdictionary::save_element_strings('product', $this->id, $this->dictionary, $this->website);
		webdictionary_history::save_element_strings('product', $this->id, $this->dictionary, false, $this->website);
   		path::saveElementPaths('product', $this->id, $this->paths, $this->website);

		if(method_exists($events, 'trigger'))
		{
			$events->trigger(
				'product',
				'save',
				array(
					'product' => $this
				)
			);
		}

		return true;
	}
	
	public function update()
	{
		global $DB;
		global $events;
		global $user;

        if(!is_null($user))
        {
            if($user->permission("products.edit") == 'false' && $this->author != $user->id)
                throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));

            if( !structure::category_allowed($this->category) )
                throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
        }

		$this->date_modified = core_time();

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute(' 
            UPDATE nv_products
            SET 
                  category = :category,
                  type = :type,
                  template = :template,
                  author = :author,
                  brand = :brand,
                  date_to_display = :date_to_display,
                  date_published = :date_published,
                  date_unpublish = :date_unpublish,
                  date_modified = :date_modified,
                  sku = :sku,
                  barcode = :barcode,
                  base_price = :base_price,
                  base_price_currency = :base_price_currency,
                  tax_class = :tax_class,
                  tax_value = :tax_value,
                  cost = :cost,
                  cost_currency = :cost_currency,
                  offer_price = :offer_price,
                  offer_begin_date = :offer_begin_date,
                  offer_end_date = :offer_end_date,
                  width = :width,
                  height = :height,
                  depth = :depth,
                  size_unit = :size_unit,
                  weight = :weight,
                  weight_unit = :weight_unit,
                  inventory = :inventory,
                  stock_available = :stock_available,
                  options = :options,
                  galleries = :galleries,
                  comments_enabled_to = :comments_enabled_to,
                  comments_moderator = :comments_moderator,
                  access = :access,
                  groups = :groups,
                  permission = :permission,
                  views = :views,
                  votes = :votes,
                  score = :score,
                  position = :position
            WHERE id = :id
              AND website = :website',
            array(
                ":id" => $this->id,
                ":website" => $this->website,
                ":category" => value_or_default($this->category, 0),
                ":template" => value_or_default($this->template, ''),
                ":type" => value_or_default($this->type, 0),
                ":brand" => value_or_default($this->brand, 0),
                ":date_to_display" => intval($this->date_to_display),
                ":date_published" => intval($this->date_published),
                ":date_unpublish" => intval($this->date_unpublish),
                ":date_modified" => $this->date_modified,
                ":sku" =>  value_or_default($this->sku, ''),
                ":barcode" =>  value_or_default($this->barcode, ''),
                ":base_price" =>  value_or_default($this->base_price, 0),
                ":base_price_currency" =>  value_or_default($this->base_price_currency, ""),
                ":tax_class" =>  value_or_default($this->tax_class, "included"),
                ":tax_value" =>  value_or_default($this->tax_value, 0),
                ":cost" =>  value_or_default($this->cost, 0),
                ":cost_currency" =>  value_or_default($this->cost_currency, ""),
                ":offer_price" => value_or_default($this->offer_price, 0),
                ":offer_begin_date" => intval($this->offer_begin_date),
                ":offer_end_date" => intval($this->offer_end_date),
                ":width" => value_or_default($this->width, 0),
                ":height" => value_or_default($this->height, 0),
                ":depth" => value_or_default($this->depth, 0),
                ":size_unit" => value_or_default($this->size_unit, 'cm'),
                ":weight" => value_or_default($this->weight, 0),
                ":weight_unit" => value_or_default($this->weight_unit, 'kg'),
                ":inventory" => value_or_default($this->inventory, 0),
                ":stock_available" => value_or_default($this->stock_available, 0),
                ":options" => json_encode($this->options),
                ":author" => value_or_default($this->author, 0),
                ":galleries" => serialize($this->galleries),
                ":comments_enabled_to" => value_or_default($this->comments_enabled_to, 0),
                ":comments_moderator" => value_or_default($this->comments_moderator, 0),
                ":access" => value_or_default($this->access, 0),
                ":groups" => $groups,
                ":permission" => value_or_default($this->permission, 0),
                ":views" => $this->views,
                ":votes" => $this->votes,
                ":score" => $this->score,
                ":position" => value_or_default($this->position, 0)
            )
        );
		
		if(!$ok)
			throw new Exception($DB->get_last_error());

		webdictionary::save_element_strings('product', $this->id, $this->dictionary, $this->website);
		webdictionary_history::save_element_strings('product', $this->id, $this->dictionary, false, $this->website);
   		path::saveElementPaths('product', $this->id, $this->paths, $this->website);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'product',
                'save',
                array(
                    'product' => $this
                )
            );
        }

		return true;
	}

	public function load_template()
	{
		$template = new template();
        $template->load($this->template);
		return $template;
	}
	
	public function property($property_name, $raw=false)
	{
		// load properties if not already done
		if(empty($this->properties))
            $this->properties = property::load_properties('product', $this->template, 'product', $this->id);

		for($p=0; $p < count($this->properties); $p++)
		{
			if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
			{
				if($raw)
					$out = $this->properties[$p]->value;
				else
					$out = $this->properties[$p]->value;
					
				break;
			}
		}
		
		return $out;
	}

	public function property_exists($property_name)
	{
		// load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('product', $this->template, 'product', $this->id);

		for($p=0; $p < count($this->properties); $p++)
		{
			if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
				return true;
		}
		return false;
	}

    public function property_definition($property_name)
	{
	    $out = "";

		// load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('product', $this->template, 'product', $this->id);

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

    public function link($lang)
    {
        $url = $this->paths[$lang];
	    if(empty($url))
		    $url = '/product/'.$this->id;
        $url = nvweb_prepare_link($url);
        return $url;
    }

    public function comments_count()
    {
        global $DB;

        if(empty($this->_comments_count))
        {
            $DB->query('
                SELECT COUNT(*) as total
                      FROM nv_comments
                     WHERE website = ' . intval($this->website) . '
                       AND object_type = "product"
                       AND object_id = ' . intval($this->id) . '
                       AND status = 0'
            );

            $out = $DB->result('total');
            $this->_comments_count = $out[0];
        }

        return $this->_comments_count;
    }

    public function weight_in_grams()
    {
        $out = 0;

        switch($this->weight_unit)
        {
            case 'kg':
                $out = $this->weight * 1000;    // 1 kg = 1000 grams
                break;

            case 'lb':
                $out = $this->weight * 453.59237; // 1 lb = 453.59237 grams
                break;

            case 'g':
                $out = $this->weight;
                break;
        }

        return $out;
    }

    public static function reorder($order)
    {
        global $DB;
		global $website;

		$items = explode("#", $order);

		for($i=0; $i < count($items); $i++)
		{
			if(empty($items[$i])) continue;

			$ok = $DB->execute('
              UPDATE nv_products
				 SET position = '.($i+1).'
			   WHERE id = '.$items[$i].' AND 
			         website = '.$website->id
            );

			if(!$ok)
			    return array("error" => $DB->get_last_error());
		}

		return true;
    }
	
	public function quicksearch($text)
	{
		global $DB;
		global $website;

		$where = '';
		$search = explode(" ", $text);
		$search = array_filter($search);
		sort($search);
		foreach($search as $text)
		{
			// we search for the IDs at the dictionary NOW (to avoid inefficient requests)
			$DB->query(
			    'SELECT DISTINCT (nvw.node_id)
                     FROM nv_webdictionary nvw
                     WHERE nvw.node_type = "product"
                       AND nvw.website = '.$website->id.'
                       AND nvw.text LIKE :text',
                'array',
                array(
                    ':text' => '%'.$text.'%'
                )
            );

			$dict_ids = $DB->result("node_id");

			// all columns to look for
			$cols[] = 'p.id LIKE ' .  protect('%'.$text.'%').' ';
			if(!empty($dict_ids))
				$cols[] = 'p.id IN ('.implode(',', $dict_ids).')';

			$where .= ' AND ( ';
			$where .= implode( ' OR ', $cols);
			$where .= ')';
		}
		
		return $where;
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();
        $DB->query('SELECT * FROM nv_products WHERE website = '.intval($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }

    public function get_price()
    {
        // TODO: calculate price based on price lists, current web user, etc.

        // price is base_price + taxes
        // except if the product is on sale, then is offer_price + taxes
        $current = $this->base_price;
        $base_price = $this->base_price;
        $old_price = null;
        $old_price_without_taxes = null;

        if($this->on_offer())
        {
            $base_price = $this->offer_price; // no taxes added
            $current = $this->offer_price; // taxes added later
            $old_price = $this->base_price; // taxes added later
            $old_price_without_taxes = $this->offer_price;
        }

        // add taxes to $current and $old_price, if needed
        $tax_value = 0;
        $tax_amount = 0;

        if($this->tax_class == "custom")
        {
            $tax_value = $this->tax_value;
            $tax_amount = round(($current / 100 * $this->tax_value), 2);

            $current = round($current + $tax_amount, 2);

            if(!empty($old_price))
                $old_price = round($old_price + ($old_price / 100 * $tax_value), 2);
        }

        return array(
            'current' => $current, // product price for this customer (with taxes, if custom)
            'old' => $old_price, // old product price (with taxes, if custom)
            'base_price' => $base_price, // current product price without taxes
            'old_without_taxes' => $old_price_without_taxes, // old product price (only if offer is active) without taxes
            'tax_value' => $tax_value, // for the current price
            'tax_amount' => $tax_amount // for the current price
        );
    }

    public function on_offer()
    {
        $on_offer = false;

        if(!empty($this->offer_price))
        {
            // check if the date is in the valid period of the offer
            if(
                (empty($this->offer_begin_date) || core_time() >= $this->offer_begin_date) &&
                (empty($this->offer_end_date) || core_time() <= $this->offer_end_date)
            )
                $on_offer = true;
        }

        return $on_offer;
    }

    public function is_top($limit="10")
    {
        if(empty($limit))
        {
            $limit = 10;
        }

        $rs = product::top_products($limit);

        if(empty($rs))
            return false;

        foreach($rs as $top_product)
        {
            if($top_product->product == $this->id)
                return true;
        }
        return false;
    }

    public static function top_products($limit=10)
    {
        global $DB;
        global $website;

        // if possible, use the internal cache instead of querying the database
        if(isset(product::$top_products) && count(product::$top_products) >= $limit)
        {
            return product::$top_products;
        }

        $DB->query('
            SELECT product, COUNT(*) AS orders 
              FROM nv_orders_lines 
             WHERE website = '.$website->id.'
          GROUP BY product 
          ORDER BY orders DESC
          LIMIT '.$limit
        );

        product::$top_products = $DB->result();

        return product::$top_products;
    }

    public function is_new($since="30") // days
    {
        $is_new = false;

        if(empty($since))
        {
            $since = 30;
        }

        if(!empty($this->date_to_display))
        {
            if( floor((time() - $this->date_to_display) / 86400) < $since )
                $is_new = true;
        }
        else if(!empty($this->date_published))
        {
            if( floor((time() - $this->date_published) / 86400) < $since )
                $is_new = true;
        }
        else
        {
            if( floor((time() - $this->date_created) / 86400) < $since )
                $is_new = true;
        }

        return $is_new;
    }

    public static function size_units()
    {
        $size_units = array(
            'cm', 'm', 'mm', 'in'
        );
        return $size_units;
    }

    public static function weight_units()
    {
        $weight_units = array(
            'g', 'kg', 'lb'
        );
        return $weight_units;
    }

    // get list of enabled currencies or "all"
    public static function currencies($value=NULL, $simple=true)
    {
        global $website;

        $out = array();

        // future todo: currencies should be in database with their (daily?) updated exchange rates
        // use ISO 4217 codes: https://en.wikipedia.org/wiki/ISO_4217
        $currencies = array(
            'eur' => array('symbol' => '€', 'placement' => 'after', 'decimals' => 2),
            'usd' => array('symbol' => '$', 'placement' => 'before', 'decimals' => 2)
        );

        $out = $currencies;

        if($simple)
        {
            $out = array();
            foreach($currencies as $key => $val)
                $out[$key] = $val['symbol'];
        }

        if(!empty($value) && $value != 'all')
        {
            $out = $out[$value];
        }
        else if(empty($value))
        {
            // just return the website default currency
            $out = array($website->currency => $out[$website->currency]);
        }
        else
        {
            // return all currencies
        }

        return $out;
    }

    public static function tax_classes()
    {
        $tax_classes = array(
            'included' => t(679, "Included"),
            'free' => t(100, "Free"),
            'custom' => "(". t(680, "Custom") . ")"
        );

        return $tax_classes;
    }

	public static function __set_state(array $obj)
	{
		$tmp = new product();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}
	
}

?>