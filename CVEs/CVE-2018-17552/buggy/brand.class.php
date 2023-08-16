<?php
class brand
{
    public $id;
    public $website;
    public $name;
    public $image;
    public $url;

    public function load($id)
    {
        global $DB;
        global $website;

        if($DB->query('
            SELECT * FROM nv_brands 
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
        $this->name 		= $main->name;
        $this->image		= $main->image;
        $this->url          = $main->url;
    }

    public function load_from_post()
    {
        $this->name  		= $_REQUEST['name'];
        $this->image		= intval($_REQUEST['image']);
        $this->url		    = $_REQUEST['url'];
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
            grid_notes::remove_all('brand', $this->id);

            $DB->execute('
				DELETE FROM nv_brands
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
 			INSERT INTO nv_brands
				(id, website, name, image, url)
			VALUES 
				( 0, :website, :name, :image, :url)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'name' => $this->name,
                'image' => value_or_default($this->image, 0),
                'url' => value_or_default($this->url, "")
            )
        );

        $this->id = $DB->get_last_id();

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_brands
			  SET name = :name, image = :image, url = :url
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'name' => $this->name,
                'image' => value_or_default($this->image, 0),
                'url' => value_or_default($this->url, "")
            )
        );

        if(!$ok) throw new Exception($DB->get_last_error());

        return true;
    }

    public function quicksearch($text)
    {
        $like = ' LIKE '.protect('%'.$text.'%');

        $cols[] = 'name '.$like;

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_brands WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>