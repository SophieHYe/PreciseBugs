<?php
class navibars
{
	public $elements;
	
	public function __construct()	
	{
		$this->elements = array();
	}
	
	function title($text)
	{
		$this->elements['title'] = '<div class="ui-corner-all" id="navigate-content-title">'.$text.'</div>';
	}

	function add_actions($actions)
	{
		if(is_array($actions) && !empty($actions))
		{
			$search_form_pos = array_search('search_form', $actions);
			
			if($search_form_pos !== false)
			{
				$actions[$search_form_pos] = array();

				// we need to suppose something here;
				// if we are showing a list (act=0 || act=list), make an ajax call
				// else redirect browser to the list and make the search after load
				
				if( empty($_REQUEST['act']) || $_REQUEST['act']=='list' ||
                    ($_REQUEST['fid']=='extensions' && $_REQUEST['act']=='run' && (empty($_REQUEST['mode']) || $_REQUEST['mode']=='list'))
                )
				{
                    // we are displaying a list
					$actions[$search_form_pos][] = '<img onclick="$(this).next().triggerHandler(\'submit\');" height="16" align="absmiddle" width="16" src="img/icons/silk/zoom.png"></a>';
					$actions[$search_form_pos][] = '<form method="GET" action="#" onsubmit=" navitable_quicksearch($(\'#navigate-quicksearch\').val()); return false;">';
				}
				else // other screen than a list
				{
					$actions[$search_form_pos][] = '<img onclick="$(this).next().trigger(\'submit\');" height="16" align="absmiddle" width="16" src="img/icons/silk/zoom.png"></a>';					
					$actions[$search_form_pos][] = '<form method="GET" action="?">';
				}
					
				$actions[$search_form_pos][] = '	<input type="hidden" name="fid" value="'.$_REQUEST['fid'].'" />';
				$actions[$search_form_pos][] = '	<input type="hidden" name="act" value="list" />';
				$actions[$search_form_pos][] = '	<input type="hidden" name="quicksearch" value="true" />';
				$actions[$search_form_pos][] = '	<input type="text" id="navigate-quicksearch" name="navigate-quicksearch" size="16" value="" placeholder="'.t(41, 'Search').'…">';
				$actions[$search_form_pos][] = '</form>';
            
				$actions[$search_form_pos] = implode("\n", $actions[$search_form_pos]);
			}

            $actions_html = '';
            foreach($actions as $action)
            {
                if(is_array($action))
                {
                    // action with submenu
                    $actions_html .= $action[0]."\n";
                    $actions_html .= '<ul class="content-actions-submenu">';

                    array_shift($action);

                    foreach($action as $subaction)
                        $actions_html .= '<li>'.$subaction.'</li>';

                    $actions_html .= '</ul>'."\n";
                }
                else if(!empty($action))
                {
                    $actions_html .= $action . "\n";
                }
            }

            $actions = $actions_html;
		}

		if(!empty($actions))
		    $this->elements['actions'][] = '<div class="ui-corner-all">'.$actions.'</div>';
	}

	function search_form_suggest($search_url, $edit_url)
    {
        global $layout;

        $layout->add_script('
            $("#navigate-quicksearch").autocomplete(
            {
                source: "'.$search_url.'",                
                minLength: 2,
                position: { my : "right top", at: "right bottom" },
                classes: {"ui.autocomplete": "navi-ui-widget-shadow"},
                select: function( event, ui ) 
                {
                    window.location.replace("'.$edit_url.'" + ui.item.id);
                }
            }).data("ui-autocomplete")._renderItem = function (ul, item) 
            {
                return $("<li></li>")
                    .data("item.autocomplete", item)
                    .append("<a>" + item.label + " <span class=\"small_item_info\">#" + item.id + "</span></a>")
                    .appendTo(ul);
            };                        
        ');
    }

	function form($tag="", $action="")
	{
		if(empty($action))
			$action = $_SERVER['QUERY_STRING'];
			
		if(empty($tag))
			$tag = '<form name="navigate-content-form" action="?'.$action.'" method="post" enctype="multipart/form-data">';
		
		$this->elements['form'] = $tag;
	}	

	function add_tab($name, $href='', $icon='')
	{
		$this->elements['tabs'][] =
            array(
                'name' => $name,
				'href' => $href,
                'icon' => $icon
            );
		
		return count($this->elements['tabs']) - 1;	
	}
	
	function add_content($html)
	{
		$this->elements['html'][] = $html;	
	}
	
	function add_tab_content($content)
	{
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = $content;
	}
	
	function add_tab_content_row($content, $id="", $extra="")
	{
		if(is_array($content)) $content = implode("\n", $content);
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="navigate-form-row" id="'.$id.'" '.$extra.'>';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = $content;
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '</div>';
	}		
	
	function add_tab_content_panel($title, $content, $id="", $width="90%", $height="200px")
	{
		if(is_array($content)) $content = implode("\n", $content);
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="ui-widget-content ui-corner-all navigate-panel" style=" margin-bottom: 5px; width: '.$width.'; height: '.$height.' " id="'.$id.'">';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="ui-state-default ui-corner-top navigate-panel-header" style=" padding: 5px; ">'.$title.'</div>';	// ui-tabs-selected ui-state-active
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="navigate-panel-body" style=" height: '.(intval($height) - 32).'px; overflow: auto; ">'.$content.'</div>';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '</div>';				
	}
	
	
	function generate_tabs()
	{
		global $layout;
		
		$tabs = $this->elements['tabs'];
		
		$buffer[] =  '<div id="navigate-content-tabs" style=" visibility: hidden; ">';
		
			$buffer[] =  '<ul>';
			
			for($t=0; $t < count($tabs); $t++)
			{
			    $icon = "";
			    if(!empty($tabs[$t]['icon']))
			        $icon = '<i class="'.$tabs[$t]['icon'].'"></i> ';

				if(empty($tabs[$t]['href']))
					$buffer[] = '<li><a href="#navigate-content-tabs-'.($t+1).'">'.$icon.$tabs[$t]['name'].'</a></li>';
				else
					$buffer[] = '<li><a href="'.$tabs[$t]['href'].'">'.$icon.$tabs[$t]['name'].'</a></li>';
			}
			$buffer[] =  '</ul>';			
			
			for($t=0; $t < count($tabs); $t++)
			{
				if(!empty($tabs[$t]['href'])) continue;
				
				$buffer[] = '<div id="navigate-content-tabs-'.($t+1).'">';
				
				if(!empty($this->elements['tabs_content'][$t]))
					$buffer[] = implode("\n", $this->elements['tabs_content'][$t]);
					
				$buffer[] = '</div>';				
			}			
		
		$buffer[] =  '</div>';	
		
        $layout->add_script('
            $(window).on("load", function()
            {
                $("#navigate-content-tabs").tabs({
                    '.(!empty($_REQUEST['tab'])? 'active: '.$_REQUEST['tab'].',' : '').'
                    beforeActivate: function() // NEW WAY from JQUERY UI 1.10
                    {
                        setTimeout(function() {
                            $(navigate_codemirror_instances).each(function() { this.refresh(); } );
                        }, 200);
                    }
                });
                $("#navigate-content-tabs").css({"visibility": "visible"});
            });
        ');

		return implode("\n", $buffer);	
	}
	
	function generate()
	{
		$buffer[] = $this->elements['title'];
		
		if(!empty($this->elements['actions']))
		{
			$buffer[] = '<div id="navigate-content-actions">';
			$buffer[] = implode("\n", $this->elements['actions']);
			$buffer[] = '</div>';
		}
		
		$buffer[] = '<div id="navigate-content-top-spacer"></div>';
		
		if(!empty($this->elements['form']))
			$buffer[] = $this->elements['form'];

		if(!empty($this->elements['tabs']))
			$buffer[] = $this->generate_tabs();
			
		if(!empty($this->elements['form']))
			$buffer[] = '</form>';			
			
		if(!empty($this->elements['html']))
			$buffer[] = implode("\n", $this->elements['html']);
		
		return implode("\n", $buffer);	
	}
}
?>