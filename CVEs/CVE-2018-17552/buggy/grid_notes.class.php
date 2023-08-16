<?php

class grid_notes
{
    public $id;
    public $website;
    public $user;
    public $item_type;
    public $item_id;
    public $background;
    public $note;

    public static function background($item_type, $item_id, $color)
    {
        global $DB;
        global $website;
        global $user;

        $DB->execute('
            INSERT INTO nv_notes
                (id, website, user, item_type, item_id, background, note, date_created)
                VALUES
                (   0, :website, :user, :item_type, :item_id, :background, :note, :date_created   )',
            array(
                ':website' => $website->id,
                ':user' => value_or_default($user->id, 0),
                ':item_type' => value_or_default($item_type, ''),
                ':item_id' => value_or_default($item_id, 0),
                ':background' => value_or_default($color, ""),
                ':note' => "",
                ':date_created' => time()
            )
        );

        $background = $DB->query_single(
            'background',
            'nv_notes',
            'website = '.$website->id.'
             AND item_type = '.protect($item_type).'
             AND item_id = '.protect($item_id).'
             ORDER BY date_created DESC'
        );


        // TODO: purge old grid notes when current background is empty or transparent
        //          =>  remove all empty notes
        // NOT REALLY NEEDED, just save the item grid notes history, let the user remove at will

        if(empty($background) || $background=='transparent')
        {
            $DB->execute('
                DELETE FROM nv_notes
                WHERE website = '.$website->id.'
                  AND item_type = '.protect($item_type).' 
                  AND item_id = '.protect($item_id).'
                  AND note = ""
            ');
        }

    }

    public static function summary($dataset, $type, $field_id)
    {
        global $DB;
        global $website;

        // find IDs to search
        $ids = array();

        for($i=0; $i < count($dataset); $i++)
            $ids[] = intval($dataset[$i][$field_id]);
        
        $ids = array_filter($ids);

        if(!empty($ids))
        {
            $DB->query(
                'SELECT gn.id, gn.item_id, gn.background, gn.note, gn.date_created, u.username as creator
                   FROM nv_notes gn, nv_users u
                  WHERE gn.website = '.protect($website->id).'
                    AND gn.item_type = '.protect($type).'
                    AND gn.item_id IN ('.implode(",", $ids).')
                    AND u.id = gn.user
                  ORDER BY gn.item_id ASC, gn.date_created DESC'
            );

            $grid_notes = $DB->result();
        }

        if(!is_array($grid_notes))
            $grid_notes = array();

        for($i=0; $i < count($dataset); $i++)
        {
            // search for the grid notes of this dataset row
            $background = '';
            $notes = array();
            foreach($grid_notes as $gnote)
            {
                if($gnote->item_id == $dataset[$i][$field_id])
                {
                    if(empty($background))
                        $background = $gnote->background; // the latest background saved is the one shown
                    
                    if(!empty($gnote->note))
                        $notes[] = $gnote;
                }
            }

            if(empty($notes))
                $dataset[$i]['_grid_notes_html'] = '<img src="img/icons/silk/note_edit.png" ng-notes="'.count($notes).'" class="grid_note_edit" align="absmiddle" />';
            else
                $dataset[$i]['_grid_notes_html'] = '<span class="navigate_grid_notes_span">'.count($notes).'</span><img src="img/skins/badge.png" ng-notes="'.count($notes).'" width="18px" height="18px" class="grid_note_edit" align="absmiddle" />';

            $dataset[$i]['_grid_notes_html'] .= ' ';
            $dataset[$i]['_grid_notes_html'] .= '<img src="img/icons/silk/color_swatch.png" title="" ng-background="'.$background.'" class="grid_color_swatch" align="absmiddle" />';
        }

        return $dataset;
    }

    public static function comments($item_type, $id, $notes_only=true)
    {
        global $DB;
        global $website;

        if(empty($id) || !is_numeric($id))
            return array();

        $extra = '';
        if($notes_only)
            $extra = ' AND gn.note != "" ';

        $DB->query("    
            SELECT gn.*, u.username as username
            FROM nv_notes gn, nv_users u
            WHERE gn.website = ".protect($website->id)."
              AND gn.item_type = ".protect($item_type)."
              AND gn.item_id = ".protect($id)."
              AND gn.user = u.id ".
              $extra."
            ORDER BY gn.date_created DESC"
        );

        $result = $DB->result();

        // transform result fields to human readable form
        $out = array();
        foreach($result as $row)
        {
            $out[] = array(
                'id' => $row->id,
                'background' => $row->background,
                'username'  => $row->username,
                'note' => nl2br($row->note),
                'date' => core_ts2date($row->date_created, true)
            );
        }

        return $out;
    }

    public static function add_comment($type, $id, $comment, $background='')
    {
        global $DB;
        global $website;
        global $user;

        if(empty($comment))
            return 'comment_empty';

        $comment = htmlspecialchars($comment);

        $DB->execute('
            INSERT INTO nv_notes
                (id, website, user, item_type, item_id, background, note, date_created)
                VALUES
                (   0, :website, :user, :item_type, :item_id, :background, :note, :date_created )',
            array(
                ':website' => $website->id,
                ':user' => value_or_default($user->id, 0),
                ':item_type' => value_or_default($type, ''),
                ':item_id' => value_or_default($id, 0),
                ':background' => value_or_default($background, ""),
                ':note' => value_or_default($comment, ""),
                ':date_created' => time()
            )
        );

        return 'true';
    }

    public static function remove($id)
    {
        global $DB;
        global $website;

        if(empty($id))
            return 'invalid_id';

        $DB->execute('
            DELETE FROM nv_notes
                WHERE website = '.protect($website->id).'
                  AND id = '.protect($id).'
                LIMIT 1'
        );

        return 'true';
    }

    public static function remove_all($object_type, $object_id)
    {
        global $DB;
        global $website;

        $DB->execute('
            DELETE FROM nv_notes
                WHERE website = '.protect($website->id).'
                  AND item_type = '.protect($object_type).'
                  AND item_id = '.protect($object_id).'
                LIMIT 1'
        );

        return 'true';
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
            SELECT * FROM nv_notes 
            WHERE website = '.protect($website->id),
            'object'
        );
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>