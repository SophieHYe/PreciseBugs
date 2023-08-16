<?php

function nvweb_plugins_load()
{
	// load all webget plugins and exclude the DISABLED
	global $plugins;
    global $DB;
    global $website;
	
	$plugin_list = glob(NAVIGATE_PATH.'/plugins/*', GLOB_ONLYDIR);
	$plugins = array();

    if(is_array($plugin_list))
	{
        // read the database to find the disabled plugins
        $DB->query('SELECT extension, enabled
                      FROM nv_extensions
                     WHERE website = '.protect($website->id).'
                       AND enabled = 0');

        $plugins_disabled = $DB->result('extension');

        foreach($plugin_list as $plugin_path)
		{
			$plugin_name = basename($plugin_path);

            if(in_array($plugin_name, $plugins_disabled))
                continue;

            if(file_exists($plugin_path.'/'.$plugin_name.'.php'))
				@include_once($plugin_path.'/'.$plugin_name.'.php');

			$plugins[] = $plugin_name;
		}
	}
}

function nvweb_plugins_called_in_template($html)
{
    preg_match_all("/(object=)(\"|\')(nvweb)(\"|\')((\s)+)(name=)(\"|\')(\w+)(\"|\')/", $html, $matches);
    $plugins_called = array_unique($matches[9]);
    sort($plugins_called);
    return $plugins_called;
}

// events: 	before_parse, after_parse	
function nvweb_plugins_event($event, $html)
{
	global $plugins;

    if(!is_array($plugins))
        return $html;
	
	foreach($plugins as $plugin)
	{
		$fname = 'nvweb_'.$plugin.'_event';

		if(function_exists($fname))
			$html = $fname($event, $html);
	}
	return $html;
}

?>