<?php
class shipping_method
{
    public $id;
    public $website;
    public $codename;
    public $image;
    public $permission;
    public $rates;  // table of countries, states/provinces (zones), weight range, subtotal range, base_price, tax

    public $dictionary;

    public function load($id)
    {
        global $DB;
        global $website;

        if($DB->query('
            SELECT * FROM nv_shipping_methods 
            WHERE id = '.intval($id).' AND 
                  website = '.$website->id)
        )
        {
            $data = $DB->result();
            $this->load_from_resultset($data);
        }
    }

    public function load_from_resultset($rs)
    {
        $main = $rs[0];

        $this->id			= $main->id;
        $this->website		= $main->website;
        $this->codename		= $main->codename;
        $this->image		= $main->image;
        $this->permission	= $main->permission;
        $this->dictionary	= webdictionary::load_element_strings('shipping_method', $this->id);
        $this->rates        = json_decode($main->rates);
    }

    public function load_from_post()
    {
        $this->codename  	= $_REQUEST['codename'];
        $this->permission	= $_REQUEST['permission'];
        $this->image		= intval($_REQUEST['image']);

        $this->dictionary   = array();
        $fields = array('title', 'description');
        foreach($_REQUEST as $key => $value)
        {
            if(empty($value)) continue;

            foreach($fields as $field)
            {
                if(substr($key, 0, strlen($field.'-'))==$field.'-')
                    $this->dictionary[substr($key, strlen($field.'-'))][$field] = $value;
            }
        }

        $this->rates = array();

        if(is_array($_POST['rate_details']))
        {
            foreach ($_POST['rate_details'] as $rate_detail)
            {
                $rate_detail = base64_decode($rate_detail);
                $rate_detail = json_decode($rate_detail);
                $this->rates[] = $rate_detail;
            }
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
        global $website;

        if(!empty($this->id))
        {
            // remove grid notes
            grid_notes::remove_all('shipping_method', $this->id);

            // remove dictionary strings
            webdictionary::save_element_strings('shipping_method', $this->id, array(), $this->website);

            $DB->execute('
				DELETE FROM nv_shipping_methods
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

        $DB->execute(' 
 			INSERT INTO nv_shipping_methods
				(id, website, codename, image, permission, rates)
			VALUES 
				( 0, :website, :codename, :image, :permission, :rates)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'codename' => value_or_default($this->codename, ""),
                'image' => value_or_default($this->image, 0),
                'permission' => value_or_default($this->permission, 0),
                'rates' => value_or_default(json_encode($this->rates), "")
            )
        );

        $this->id = $DB->get_last_id();

        webdictionary::save_element_strings('shipping_method', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_shipping_methods
			  SET codename = :codename, image = :image, permission = :permission, rates = :rates
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'codename' => value_or_default($this->codename, ""),
                'image' => value_or_default($this->image, 0),
                'permission' => value_or_default($this->permission, 0),
                'rates' => value_or_default(json_encode($this->rates), "")
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        webdictionary::save_element_strings('shipping_method', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public static function get_available($ws=NULL)
    {
        global $website;
        global $DB;

        if(empty($ws))
            $ws = $website->id;

        $DB->query('
            SELECT *
              FROM nv_shipping_methods
             WHERE website = '.$ws.' 
        ');

        $rs = $DB->result();

        $shipping_methods = array();
        foreach($rs as $row)
        {
            $sm = new shipping_method();
            $sm->load_from_resultset(array($row));
            if(nvweb_object_enabled($sm))
            {
                $shipping_methods[] = $sm;
            }
        }

        return $shipping_methods;
    }

    public function calculate($country=NULL, $region=NULL, $weight=NULL, $subtotal=NULL)
    {
        $rate = array();

        // check available rates for the current conditions and choose the first (rates are ordered by priority)
        for($i=0; $i < count($this->rates); $i++)
        {
            // rate disabled
            if(!$this->rates[$i]->enabled)
                continue;

            // specific country, not matching the current one
            if(!empty($this->rates[$i]->country) && $this->rates[$i]->country != $country)
                continue;

            // specific group of regions, not matching the current one
            if(!empty($this->rates[$i]->regions) && !in_array($region, $this->rates[$i]->regions))
                continue;

            // weight conditions not matching the order values
            // TODO: check weight units, now using "grams" everywhere!!
            if(!empty($this->rates[$i]->weight->min) && $this->rates[$i]->weight->min > $weight)
                continue;

            if(!empty($this->rates[$i]->weight->max) && $this->rates[$i]->weight->max < $weight)
                continue;

            // subtotal conditions not matching the order values
            // TODO: check subtotal currency, now using the global cart value
            if(!empty($this->rates[$i]->subtotal->min) && $this->rates[$i]->subtotal->min > $subtotal)
                continue;

            if(!empty($this->rates[$i]->subtotal->max) && $this->rates[$i]->subtotal->max < $subtotal)
                continue;

            // the rate is applicable
            $rate = $this->rates[$i];
            break;
        }

        return $rate;
    }

    public function quicksearch($text)
    {
        global $DB;
        global $website;

        $like = ' LIKE '.protect('%'.$text.'%');

        // we search for the IDs at the dictionary NOW (to avoid inefficient requests)

        $DB->query('
            SELECT DISTINCT (nvw.node_id)
              FROM nv_webdictionary nvw
             WHERE nvw.node_type = "shipping_method" AND
                   nvw.text '.$like.' AND
                   nvw.website = '.$website->id,
            'array'
        );

        $dict_ids = $DB->result("node_id");

        // all columns to look for
        $cols[] = 'sm.id' . $like;

        if(!empty($dict_ids))
            $cols[] = 'sm.id IN ('.implode(',', $dict_ids).')';

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_shipping_methods WHERE website = '.intval($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>