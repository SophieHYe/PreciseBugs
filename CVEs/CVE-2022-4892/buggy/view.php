<?php

/*
 * Klasa odpowiedzialna za tworzenie szczegółów - Generator Szczegółów
 */

class ViewBuilder
{
	private $title;
	private $image;
	private $width;
	
	private $module;
	
	private $row;
	private $columns;
	private $buttons = array();
	
	function __construct()
	{
	}
	
	public function init($view_title, $view_image, $view_width)
	{
		$this->image = $view_image;
		$this->title = $view_title;
		$this->width = $view_width;
	}
	
	public function set_module($module)
	{
		$this->module = $module;
	}
	
	public function set_row($row)
	{
		$this->row = $row;
	}
	
	public function set_columns($columns)
	{
		$this->columns = $columns;
	}
	
	public function set_buttons($buttons)
	{
		$this->buttons = $buttons;
	}
	
	private function get_split_text($source, $length)
	{
		$result = NULL;
		$idx = 0;
		$broken = FALSE;

		$source = str_replace(chr(13) . chr(10), chr(32), $source);
		$words = explode(chr(32), $source);

		foreach ($words as $k => $v)
		{
			$result .= $v . chr(32);
			if ($idx++ >= $length) 
			{
				$broken = TRUE;
				break;
			}
		}
		$result = $broken ? $result . '...&nbsp;»' : trim($result);
		
		return $result;
	}
	
	public function build_view()
	{
		$main_text = NULL;
		
		$field_names = array();
		
		// kolumny pól:
		foreach ($this->columns as $key => $value)
		{
			foreach ($value as $k => $v)
			{
				if ($k == 'db_name') $db_name = $v;
				if ($k == 'column_name') $column_name = $v;
				if ($k == 'sorting') $sorting = $v;
			}
			if ($column_name)
				$field_names[] = array($db_name, $column_name);
		}
		
		$col_attrib = array(
			array('width' => '25%', 'align' => 'left'),
			array('width' => '75%', 'align' => 'left'),
		);
		
		$main_text .= '<table class="Table" width="'.$this->width.'" cellpadding="2" cellspacing="1" align="center">';

		$main_text .= '<tr>';
		$main_text .= '<th class="FormTitleBar" colspan="2">';
		$main_text .= '<span class="FormIcon">';
		$main_text .= '<img src="'.$this->image.'" alt="'.$this->title.'" />';
		$main_text .= '</span>';
		$main_text .= '<span class="FormTitle">';
		$main_text .= $this->title;
		$main_text .= '</span>';
		$main_text .= '</th>';
		$main_text .= '</tr>';
		
		foreach ($field_names as $k => $v)
		{
			foreach ($v as $key => $value)
			{
				if ($key == 0) $db_name = $value;
				if ($key == 1) $column_name = $value;
			}

			$main_text .= '<tr>';
			$main_text .= '<td class="FormCell" width="' . $col_attrib[0]['width'] .
									'" style="text-align: ' . $col_attrib[0]['align'] .
									';">';
			$main_text .= $column_name .':';
			$main_text .= '</td>';
			$main_text .= '<td class="FormCell" width="' . $col_attrib[1]['width'] .
									'" style="text-align: ' . $col_attrib[1]['align'] .
									';">';
									
			if (is_array($this->row[$db_name])) // dane tablicowe
			{
				if ($db_name == 'archives') // pozycje z linkami
				{
					foreach ($this->row[$db_name] as $ik => $iv)
					{
						foreach ($iv as $iik => $iiv)
						{
							if ($iik == 'id') $item_id = $iiv;
							if ($iik == 'caption') $item_caption = $iiv;
						}
						$main_text .= '<div>';
						$main_text .= '<span style="padding-right: 20px;">' . $item_caption . '</span>';
						$main_text .= '<span><a href="index.php?route=' . $this->module . '&action=preview&id=' . $item_id . '"><img src="img/16x16/page_view.png" class="TopLinkIcon" alt="preview" title="Podgląd" /></a></span>';
						$main_text .= '<span><a href="index.php?route=' . $this->module . '&action=restore&id=' . $item_id . '"><img src="img/16x16/archives.png" class="TopLinkIcon" alt="restore" title="Przywróć" /></a></span>';
						$main_text .= '</div>';
					}
				}
				else // zwykłe pozycje
				{
					if (array_key_exists('original', $this->row[$db_name]) && array_key_exists('converted', $this->row[$db_name])) // linki referer i uri
					{
						foreach ($this->row[$db_name] as $ik => $iv)
						{
							if ($ik == 'original') $original = $iv;
							if ($ik == 'converted') $converted = $iv;
						}
						$main_text .= '<div>';
						$main_text .= '<a href="' . $original . '" target="_blank">' . $converted . '</a>';
						$main_text .= '</div>';
					}
					else // zwykłe dane
					{
						foreach ($this->row[$db_name] as $ik => $iv)
						{
							$main_text .= '<div>';
							$main_text .= $this->get_split_text(strip_tags($iv), 100);
							$main_text .= '</div>';
						}
					}
				}
			}
			else if (substr($this->row[$db_name], 0, 4) == '<img') // obrazek
			{
				$main_text .= $this->row[$db_name];
			}
			else // zwykłe dane
			{
				$main_text .= $this->get_split_text(strip_tags($this->row[$db_name]), 100);
			}
			$main_text .= '</td>';
			$main_text .= '</tr>';
		}
		
		$main_text .= '<tr>';
		$main_text .= '<td colspan="2" class="ButtonBar">';
		$main_text .= '<table cellpadding="0" cellspacing="0" align="right">';
		$main_text .= '<tr>';
        
		if (in_array('edit', $this->buttons))
		{
			$main_text .= '<td>';
			$main_text .= '<form action="index.php?route=' . $this->module . '&action=edit&id=' . $this->row['id'] . '" method="post">';
			$main_text .= '&nbsp;';
			$main_text .= '<input type="submit" name="edit" value="Edytuj" class="Button" style="width: 80px;" />';
			$main_text .= '</form>';
			$main_text .= '</td>';
		}

		if (in_array('cancel', $this->buttons))
		{
			$main_text .= '<td>';
			$main_text .= '<form action="index.php?route=' . $this->module . '" method="post">';
			$main_text .= '&nbsp;';
			$main_text .= '<input type="submit" name="cancel" value="Anuluj" class="Button" style="width: 80px;" />';
			$main_text .= '</form>';
			$main_text .= '</td>';
		}

		$main_text .= '</tr>';
		$main_text .= '</table>';
		$main_text .= '</td>';
		$main_text .= '</tr>';
		
		$main_text .= '</table>';

		return $main_text;
	}
}

?>
