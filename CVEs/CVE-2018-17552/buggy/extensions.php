<?php
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new extension();

	switch($_REQUEST['act'])
	{
        case 'extension_info':
            echo '<iframe src="'.NAVIGATE_URL.'/plugins/'.$_REQUEST['extension'].'/'.$_REQUEST['extension'].'.info.html'.'" scrolling="auto" frameborder="0"  width="100%" height="100%"></iframe>';
            core_terminate();
            break;

        case 'disable':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->enabled = 0;
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;

        case 'enable':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->enabled = 1;
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;

        // TODO: rework favorite extensions as user's favorite (not global)
        /*
        case 'favorite':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->favorite = intval($_REQUEST['value']);
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;
        */

        case 'remove':
            try
            {
                $extension = new extension();
                $extension->load($_REQUEST['extension']);
                $status = $extension->delete();
                echo json_encode($status);
            }
            catch(Exception $e)
            {
                echo $e->getMessage();
            }
            core_terminate();
            break;

        case 'options':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);

            $status = null;
            if(isset($_REQUEST['form-sent']))
            {
                $extension->load_from_post();
                $status = $extension->save();
            }

            $out = extensions_options($extension, $status);
            echo $out;

            core_terminate();
            break;

        case 'dialog':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $out = extensions_dialog($extension, $_REQUEST['function'], $_REQUEST);
            echo $out;

            core_terminate();
            break;

        case 'process':
            $extension = trim($_REQUEST['extension']);
            call_user_func("nvweb_".$extension."_plugin", $_REQUEST);
            core_terminate();
            break;

        case 'run':
            $extension = trim($_REQUEST['extension']);

            $extensions_allowed = $user->permission("extensions.allowed");
            if(!empty($extensions_allowed) && !in_array($extension, $extensions_allowed))
                $out = t(610, "Sorry, you are not allowed to execute this function.");
            else
            {
                if(file_exists(NAVIGATE_PATH.'/plugins/'.$extension.'/run.php'))
                {
                    include_once(NAVIGATE_PATH.'/plugins/'.$extension.'/run.php');
                    if(function_exists($extension.'_run'))
                    {
                        eval('$out = '.$extension.'_run();');
                    }
                }
            }
            break;

        case 'install_from_hash':
            $url = base64_decode($_GET['hash']);

            if(!empty($url) && $user->permission("extensions.install")=="true")
            {
                $error = false;
                parse_str(parse_url($url, PHP_URL_QUERY), $query);

                $tmp_file = sys_get_temp_dir().DIRECTORY_SEPARATOR.$query['code'].'.zip';
                @core_file_curl($url, $tmp_file);
                if(@filesize($tmp_file) == 0)
                {
                    @unlink($tmp_file);
                    // core file curl failed, try using file_get_contents...
                    $tmp = @file_get_contents($url);
                    if(!empty($tmp))
                        @file_put_contents($tmp_file, $tmp);
                    unset($tmp);
                }

                if(@filesize($tmp_file) > 0)
                {
                    // uncompress ZIP and copy it to the extensions dir
                    @mkdir(NAVIGATE_PATH.'/plugins/'.$query['code']);

                    $zip = new ZipArchive();
                    $zip_open_status = $zip->open($tmp_file);
                    if($zip_open_status === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/plugins/'.$query['code']);
                        $zip->close();

                        $layout->navigate_notification(t(374, "Item installed successfully."), false);
                    }
                    else // zip extraction failed
                    {
                        $layout->navigate_notification('ERROR '.$zip_open_status, true, true);
                        $error = true;
                    }
                }
                else
                {
                    $layout->navigate_notification(t(56, 'Unexpected error'), true, true);
                    $error = true;
                }

                if($error)
                {
                    $layout->add_content('
                        <div id="navigate_marketplace_install_from_hash_error">
                            <p>'.t(529, "It has not been possible to download from the marketplace.").'</p>
                            <p>'.t(530, "You have to visit your Marketplace Dashboard and download the file, then use the <strong>Install from file</strong> button you'll find in the actions bar on the right.").'</p>
                            <p>'.t(531, "Sorry for the inconvenience.").'</p>
                            <a class="uibutton" href="http://www.navigatecms.com/en/marketplace/dashboard" target="_blank"><span class="ui-icon ui-icon-extlink" style="float: left;"></span> '.t(532, "Navigate CMS Marketplace").'</a>
                        </div>
                    ');
                    $layout->add_script('
                        $("#navigate_marketplace_install_from_hash_error").dialog({
                            modal: true,
                            title: "'.t(56, "Unexpected error").'"
                        });
                    ');
                }

            }
        // don't break, we want to show the themes grid right now (theme_upload by browser upload won't trigger)

        case 'extension_upload':
            if(isset($_FILES['extension-upload']) && $_FILES['extension-upload']['error']==0  && $user->permission("extensions.install")=="true")
            {
                // uncompress ZIP and copy it to the extensions dir
                $tmp = trim(substr($_FILES['extension-upload']['name'], 0, strpos($_FILES['extension-upload']['name'], '.')));
                $extension_name = filter_var($tmp, FILTER_SANITIZE_EMAIL);

                if($tmp!=$extension_name) // INVALID file name
                {
                    $layout->navigate_notification(t(344, 'Security error'), true, true);
                }
                else
                {
                    @mkdir(NAVIGATE_PATH.'/plugins/'.$extension_name);

                    $zip = new ZipArchive;
                    if($zip->open($_FILES['extension-upload']['tmp_name']) === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/plugins/'.$extension_name);
                        $zip->close();

                        $layout->navigate_notification(t(374, "Item installed successfully."), false);
                    }
                    else // zip extraction failed
                    {
                        $layout->navigate_notification(t(262, 'Error uploading file'), true, true);
                    }
                }
            }

		default:
            $list = extension::list_installed(null, false);
			$out = extensions_grid($list);
			break;
	}

	return $out;
}

function extensions_grid($list)
{
    global $layout;
    global $user;
    global $current_version;

    $navibars = new navibars();
    $navibars->title(t(327, 'Extensions'));

    $marketplace = isset($_REQUEST['marketplace']);

    if($user->permission("extensions.install")=="true")
    {
        $navibars->add_actions(
            array(
                '<a href="#" id="extension-upload-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/package_add.png"> '.t(461, 'Install from file').'</a>'
            )
        );
    }

    if(!$marketplace)
        $navibars->add_actions(	array ( 'search_form' ));

    $grid = new navigrid('extensions');

    $grid->set_header('
        <div class="navibrowse-path ui-corner-all">
            <input type="checkbox" id="extension-available-button" /><label for="extension-available-button"><img src="img/icons/silk/plugin.png" width="16px" height="16px" align="absbottom" /> '.t(528, 'Available').'</label>
            '.($user->permission("extensions.marketplace")=="true"? '<input type="checkbox" id="extension-marketplace-button" /><label for="extension-marketplace-button"><img src="img/icons/silk/basket.png" width="16px" height="16px" align="absbottom" /> '.t(527, 'Marketplace').'</label>' : '').'
        </div>
	');

    $layout->add_script('
        $("#extension-available-button").button().on("click", function()
        {
            window.location.replace("?fid=extensions");
        });
        $("#extension-marketplace-button").button();
        $("#extension-marketplace-button").button().on("click", function()
        {
            window.location.replace("?fid=extensions&marketplace");
        });

        $(".navibrowse-path input").removeAttr("checked");
        $("#extension-'.($marketplace? 'marketplace' : 'available').'-button").attr("checked", "checked");
        $("#extension-marketplace-button,#extension-available-button").button("refresh");
    ');

    if(!$marketplace)
    {
        $grid->item_size(220, 220);
        $grid->thumbnail_size(205, 145);

        $extensions = array();

        for($i=0; $i < count($list); $i++)
        {
            $extension_has_options = empty($list[$i]['options']);
            // ignore options for extensions of type payment_method
            if($list[$i]['type']=='payment_method')
                $extension_has_options = false;

            $extensions[] = array(
                'id'	=>  $list[$i]['code'],
                'name'	=>	'<div class="navigrid-item-title">'.$list[$i]['title'].'<br />v'.$list[$i]['version'].'</div>',
                'thumbnail' => NAVIGATE_URL.'/plugins/'.$list[$i]['code'].'/thumbnail.png',
                'description' => $list[$i]['description'],
                'header' => '',
                'footer' => '
                    <div class="buttonset navigrid-item-buttonset" style=" font-size: 0.6em; margin-top: 5px; visibility: hidden; "
                         extension="'.$list[$i]['code'].'" extension-title="'.$list[$i]['title'].'"
                         run="'.$list[$i]['run'].'" enabled="'.$list[$i]['enabled'].'"  favorite="'.$list[$i]['favorite'].'">
                        <button class="navigrid-extensions-info" title="'.t(457, 'Information').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></button>'.
                        //(empty($list[$i]['run'])?       '' : '<button class="navigrid-extensions-favorite" title="'.t(464, 'Favorite').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/heart_'.($list[$i]['favorite']=='1'? 'delete' : 'add').'.png"></button>').
                        (!$extension_has_options?   '' : '<button class="navigrid-extensions-settings" title="'.t(459, 'Settings').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cog.png"></button>').
                        (empty($list[$i]['update']) || ($user->permission("extensions.update")=="false")?    '' : '<button class="navigrid-extensions-update" title="'.t(463, 'Update available').': '.$list[$i]['update'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"></button>').
                        '<button '.(($list[$i]['enabled']==='0')? 'style="display: none;"' : '').' class="navigrid-extensions-disable" title="'.t(460, 'Disable').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/delete.png"></button>'.
                        '<button '.(($list[$i]['enabled']==='1')? 'style="display: none;"' : '').' class="navigrid-extensions-enable" title="'.t(462, 'Enable').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"></button>'.
                        ($user->permission("extensions.delete")=="true"? '<button '.(($list[$i]['enabled']==='1')? 'style="display: none;"' : '').' class="navigrid-extensions-remove" title="'.t(35, 'Delete').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cross.png"></button>' : '').'
                    </div>
                '
            );
        }

        $grid->items($extensions);

        $navibars->add_content($grid->generate());

        $navibars->add_content('<div id="navigrid-extension-information" title="" style=" display: none; "></div>');
        $navibars->add_content('<div id="navigrid-extension-options" title="" style=" display: none; "></div>');

        $navibars->add_content('
            <div id="navigrid-extensions-remove-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
                '.t(57, 'Do you really want to delete the item?').'
            </div>'
        );

        $navibars->add_content('
            <div id="navigrid-extensions-update" title="'.t(285, 'Update').'" style=" display: none; ">
                <iframe src="about:blank"
                    class="ui-corner-all"
                    border="0" frameborder="0" allowtransparency="true">
                </iframe>
            </div>'
        );

        $out = $navibars->generate();

        $layout->add_script('
            $.ajax({
                type: "GET",
                dataType: "script",
                cache: true,
                url: "lib/packages/extensions/extensions.js?r='.$current_version->revision.'",
                complete: function()
                {                   
                    navigate_extensions_refresh();
                }
            });

            $(window).on("load", function()
            {
                $(".navigrid-item-buttonset").each(function(i, el)
                {
                    $(el).hide().css("visibility", "visible");
                    $(el).fadeIn();
                    $(".navigrid-extensions-disable").addClass("ui-corner-right");
                });
            });

            function navitable_quicksearch(value)
            {
                $(".navigrid-item").hide();

                if(value=="")
                    $(".navigrid-item").show();
                else
                {
                    $(".navigrid-item").each(function(i, el)
                    {
                        var item_text = $(el).text().toLowerCase();
                        if( item_text.indexOf(value.toLowerCase()) >= 0 )
                            $(el).fadeIn();
                    });
                }
            }
            $("#extension-upload-button").bind("click", function()
            {
                $("#extension-upload-button").parent().find("form").remove();
                $("#extension-upload-button").after(\'<form action="?fid=extensions&act=extension_upload" enctype="multipart/form-data" method="post"><input type="file" name="extension-upload" style=" display: none;" /></form>\');
                $("#extension-upload-button").next().find("input").bind("change", function()
                {
                    if($(this).val()!="")
                        $(this).parent().submit();
                });
                $("#extension-upload-button").next().find("input").trigger("click");

                return false;
            });

        ');
    }
    else
    {
        $html = '
            <div class="navibrowse-path ui-corner-all">
                <input type="checkbox" id="extension-available-button" /><label for="extension-available-button"><img src="img/icons/silk/rainbow.png" width="16px" height="16px" align="absbottom" /> '.t(528, 'Available').'</label>
                <input type="checkbox" id="extension-marketplace-button" /><label for="extension-marketplace-button"><img src="img/icons/silk/basket.png" width="16px" height="16px" align="absbottom" /> '.t(527, 'Marketplace').'</label>
            </div>
        ';
        $html .= '
            <iframe src="http://www.navigatecms.com/en/marketplace/extensions"
                    style="visibility: hidden; width: 1px; height: 1px;"
                    class="ui-corner-all"
                    border="0" frameborder="0" allowtransparency="true">
            </iframe>
        ';

        $navibars->add_content('<div id="navigate-content-safe" class="ui-corner-all">'.$html.'</div>');

        $layout->add_script('
            $(window).on("resize focus blur", function()
            {
                $("#navigate-content-safe iframe").css({"width": 1, "height": 1});

                $("#navigate-content-safe iframe").css({
                    padding: "0px 4px",
                    width: $(".navibrowse-path").width() + parseInt($(".navibrowse-path").css("padding-right")) * 2,
                    height: $("#navigate-content-safe").height() - $("#navigate-content-safe div:first").height() - 24,
                    visibility: "visible"
                });
            });

            $("#navigate-content-safe iframe").on("focus blur load", function(){ $(window).trigger("resize");});
        ');

        $out = $navibars->generate();
    }

    $layout->add_script('
        function navigatecms_marketplace_install_from_hash(hash)
        {
            window.location.replace("?fid=extensions&act=install_from_hash&hash="+hash);
        }

        if(typeof(window.postMessage) != "undefined")
        {
           if(typeof(window.addEventListener) != "undefined")
            {
                window.addEventListener("message", function(event) {
                    navigatecms_marketplace_install_from_hash(event.data);
                }, false);
            }
            else
            {
                window.attachEvent("onmessage", function(e) {
                    navigatecms_marketplace_install_from_hash(e.data);
                });
            }
        }
    ');

    if(isset($_REQUEST['edit_settings']))
    {
        $layout->add_script('
            $(window).on("load", function()
            {
                $("div.buttonset[extension='.$_REQUEST['edit_settings'].']").find(".navigrid-extensions-settings").trigger("click");
            });
        ');
    }

    return $out;
}

function extensions_options($extension, $saved=null)
{
    global $layout;
    global $website;
    global $events;

    $layout = null;
    $layout = new layout('navigate');

    if($saved!==null)
    {
        if($saved)
            $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
        else
            $layout->navigate_notification(t(56, "Unexpected error"), true, true);
    }

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(327, 'Extensions'));

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

    $navibars->add_actions(	array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)    );

    $navibars->form();

    $navibars->add_tab(t(7, 'Configuration'));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));

    // show a language selector (only if it's a multi language website and has properties)
    if(!empty($extension->definition->options) && count($website->languages) > 1)
    {
        $website_languages_selector = $website->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(63, 'Languages').'</label>',
                $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
            ),
	        "navigate-form-language_selector"
        );

	    // hide languages selector if there isn't a multilanguage property
	    $layout->add_script('
			$(document).ready(function()
		    {
				if($("#navigate-content-tabs-1 .navigate-form-row[lang]").length < 1)
				{
					$("#navigate-form-language_selector").css("display", "none");
				}
		    });
	    ');
    }

    foreach($extension->definition->options as $option)
    {
        $property = new property();
        $property->load_from_object($option, $extension->settings[$option->id], $extension);

        if($property->type == 'tab')
        {
            $navibars->add_tab($property->name);
            if(count($website->languages) > 1)
            {
                $website_languages_selector = $website->languages();
                $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(63, 'Languages').'</label>',
                        $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
                    )
                );
            }
        }

        if($property->type == 'function')
        {
            $fname = $option->dvalue;
            if(empty($fname))
                $fname = $option->function;

            // load the extension source code, if not already done
            extension::include_php($extension->code);

            if(!function_exists($fname))
                continue;

            call_user_func(
                $fname,
                array(
                    'extension' => $extension,
                    'navibars' => $navibars,
                    'naviforms' => $naviforms
                )
            );
        }
        else
        {
            $navibars->add_tab_content(navigate_property_layout_field($property, $extension));
        }
    }

    $layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$navibars->generate().'</div>');
    $layout->navigate_additional_scripts();
    navigate_property_layout_scripts(); // add javascript to enable special buttons and functions (Copy from, etc.)
    $layout->add_script('
        $("html").css("background", "transparent");
    ');
    
    $out = $layout->generate();

    return $out;
}

function extensions_dialog($extension, $function, $params)
{
    global $layout;

    $layout = null;
    $layout = new layout('navigate');

	// load the extension source code, if not already done
    extension::include_php($extension->code);

    if(function_exists($function))
    {
        call_user_func($function, $params);
        $out = $layout->generate();
    }
    else
        $out = 'ERROR: "'.$function.'" function does not exist!';

    return $out;
}

?>