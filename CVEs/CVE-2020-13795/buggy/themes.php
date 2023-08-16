<?php
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function run()
{
	global $user;	
	global $layout;
	global $website;
    global $theme;
    global $DB;
	
	$out = '';

	switch($_REQUEST['act'])
	{
        case 'theme_info':
            echo '<iframe src="'.NAVIGATE_URL.'/themes/'.$_REQUEST['theme'].'/'.$_REQUEST['theme'].'.info.html'.'" scrolling="auto" frameborder="0"  width="100%" height="100%"></iframe>';
            core_terminate();
            break;

        case 'remove':
            // check the theme is not actually used in any website
            $usages = $DB->query_single(
                'COUNT(*)',
                'nv_websites',
                ' theme = :theme',
                null,
                array(
                    ':theme' => $_REQUEST['theme']
                )
            );
            if($usages == 0)
            {
                try
                {
                    $theme = new theme();
                    $theme->load($_REQUEST['theme']);
                    $status = $theme->delete();
                    echo json_encode($status);
                }
                catch(Exception $e)
                {
                    echo $e->getMessage();
                }
            }
            else
            {
                $status = t(537, "Can't remove the theme because it is currently being used by another website.");
                echo $status;
            }
            core_terminate();
            break;

        /*
        case 'export':
            $out = themes_export_form();
            break;
        */

        case 'theme_sample_content_import':
            try
            {
                $theme->import_sample();
                $layout->navigate_notification(t(374, "Item installed successfully."), false);
            }
            catch(Exception $e)
            {
                $layout->navigate_notification($e->getMessage(), true, true);
            }

            $themes = theme::list_available();
            $out = themes_grid($themes);
            break;

        case 'theme_sample_content_export':
            if(empty($_POST))
                $out = themes_sample_content_export_form();
            else
            {
                $categories = explode(',', $_POST['categories']);
                $folder = $_POST['folder'];
                $items = explode(',', $_POST['elements']);
                $block_groups = explode(',', $_POST['block_groups']);
                $blocks = explode(',', $_POST['blocks']);
                $comments = explode(',', $_POST['comments']);

                theme::export_sample($categories, $items, $block_groups, $blocks, $comments, $folder);

                core_terminate();
            }
            break;

        case 'install_from_hash':
            $url = base64_decode($_GET['hash']);

            if(!empty($url) && $user->permission("themes.install")=="true")
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
                    // uncompress ZIP and copy it to the themes dir
                    @mkdir(NAVIGATE_PATH.'/themes/'.$query['code']);

                    $zip = new ZipArchive();
                    $zip_open_status = $zip->open($tmp_file);
                    if($zip_open_status === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/themes/'.$query['code']);
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
                            <p>'.t(529, "It has not been possible to download the item you have just bought from the marketplace.").'</p>
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

        case 'theme_upload':
            if(isset($_FILES['theme-upload']) && $_FILES['theme-upload']['error']==0 &&  $user->permission("themes.install")=="true")
            {
                // uncompress ZIP and copy it to the themes dir
                $tmp = trim(substr($_FILES['theme-upload']['name'], 0, strpos($_FILES['theme-upload']['name'], '.')));
                $theme_name = filter_var($tmp, FILTER_SANITIZE_EMAIL);

                if($tmp!=$theme_name) // INVALID file name
                {
                    $layout->navigate_notification(t(344, 'Security error'), true, true);
                }
                else
                {
                    @mkdir(NAVIGATE_PATH.'/themes/'.$theme_name);

                    $zip = new ZipArchive;
                    if($zip->open($_FILES['theme-upload']['tmp_name']) === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/themes/'.$theme_name);
                        $zip->close();

                        $layout->navigate_notification(t(374, "Item installed successfully."), false);
                    }
                    else // zip extraction failed
                    {
                        $layout->navigate_notification(t(262, 'Error uploading file'), true, true);
                    }
                }
            }
            // don't break, we want to show the themes grid right now

        case 'themes':
        default:
            if(@$_REQUEST['opt']=='install')
            {
                $ntheme = new theme();
                $ntheme->load($_REQUEST['theme']);

                $website->theme = $ntheme->name;

                if(!empty($ntheme->styles))
                {
                    $nst = get_object_vars($ntheme->styles);
                    $nst = array_keys($nst);

                    if(!isset($website->theme_options) || empty($website->theme_options))
                        $website->theme_options = json_decode('{"style": ""}');
                    $website->theme_options->style = array_shift($nst);
                }
                else
                {
                    if(!isset($website->theme_options) || empty($website->theme_options))
                        $website->theme_options = json_decode('{"style": ""}');
                    else
                        $website->theme_options->style = "";
                }

                try
                {
                    $website->update();
                    $layout->navigate_notification(t(374, "Item installed successfully."), false);
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
            }

            $themes = theme::list_available();

            $out = themes_grid($themes);
            break;

    }
	
	return $out;
}

function themes_grid($list)
{
	global $layout;
	global $website;
    global $user;
    global $current_version;
	
	$navibars = new navibars();	
	$navibars->title(t(367, 'Themes'));

    $marketplace = isset($_REQUEST['marketplace']);

    if($user->permission("themes.install")=="true")
    {
        $navibars->add_actions(
            array(
                '<a href="#" id="theme-upload-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/package_add.png"> '.t(461, 'Install from file').'</a>'
            )
        );
    }

    $navibars->add_actions(
        array(
            '<a href="?fid=themes&act=theme_sample_content_export" id="theme-sample-content-export-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/server_compressed.png"> '.t(480, 'Export sample content').'</a>'
        )
    );

	$grid = new navigrid('themes');

	$grid->set_header('
        <div class="navibrowse-path ui-corner-all">
            <input type="checkbox" id="theme-available-button" /><label for="theme-available-button"><img src="img/icons/silk/rainbow.png" width="16px" height="16px" align="absbottom" /> '.t(528, 'Available').'</label>
            '.($user->permission("themes.marketplace")=="true"? '<input type="checkbox" id="theme-marketplace-button" /><label for="theme-marketplace-button"><img src="img/icons/silk/basket.png" width="16px" height="16px" align="absbottom" /> '.t(527, 'Marketplace').'</label>' : '').'
        </div>
	');

    $layout->add_script('
        $("#theme-available-button").button().on("click", function()
        {
            window.location.replace("?fid=themes");
        });
        $("#theme-marketplace-button").button();
        $("#theme-marketplace-button").button().on("click", function()
        {
            window.location.replace("?fid=themes&marketplace");
        });

        $(".navibrowse-path input").removeAttr("checked");
        $("#theme-'.($marketplace? 'marketplace' : 'available').'-button").attr("checked", "checked");
        $("#theme-marketplace-button,#theme-available-button").button("refresh");
    ');

    if(!$marketplace)
    {
        $grid->item_size(220, 220);
        //$grid->thumbnail_size(138, 150); NV 1.x thumbnail size
        $grid->thumbnail_size(205, 145);
        $grid->highlight_on_click = false;

        $themes = array();

        // current website theme
        if(!empty($website->theme))
        {
            $theme = new theme();
            $theme->load($website->theme, true);
            $update_ver = $_SESSION['themes_updates'][$theme->name];

            if(version_compare($update_ver, $theme->version, '<='))
                $update_ver = '';
            else
                $update_ver = $theme->version.' &raquo; '.$update_ver;

            $themes[] = array(
                'id'	=>  $website->theme,
                'name'	=>	'<div class="navigrid-themes-title navigrid-themes-installed">'.$theme->title.'</div>',
                'thumbnail' => NAVIGATE_URL.'/themes/'.$website->theme.'/thumbnail.png',
                'header' => '
                '.(file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'.info.html')? '<a href="#" class="navigrid-themes-info" theme="'.$website->theme.'" theme-title="'.$theme->title.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></a>' : '').'
                '.(empty($update_ver)? '' : '
                    <a href="#" class="navigrid-themes-update" theme="'.$website->theme.'" title="'.t(285, "Update").' '.$update_ver.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"></a>
                '),
                'footer' => '
                    <a href="?fid=websites&act=edit&id='.$website->id.'&tab=7" class="uibutton navigrid-themes-button navigrid-theme-configure"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wrench_orange.png"> '.t(200, 'Options').'</a>
                '.(
                    !file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'_sample.zip')?
                        '' : '<a href="#" class="uibutton navigrid-themes-button navigrid-theme-install-demo"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wand.png"> '.t(484, 'Install demo').'</a>'
                )
            );
        }

        for($t=0; $t < count($list); $t++)
        {
            if($website->theme==$list[$t]['code']) continue;

            $update_ver = $_SESSION['themes_updates'][$list[$t]['code']];
            if(version_compare($update_ver, $list[$t]['version'], '<='))
                $update_ver = '';
            else
                $update_ver = $list[$t]['version'].' &raquo; '.$update_ver;

            $themes[] = array(
                'id'	=>  $list[$t]['code'],
                'name'	=>	'<div class="navigrid-themes-title">'.$list[$t]['title'].'</div>',
                'thumbnail' => NAVIGATE_URL.'/themes/'.$list[$t]['code'].'/thumbnail.png',
                'header' => '
                    '.($user->permission("themes.delete")=="true"? '<a href="#" class="navigrid-themes-remove" theme="'.$list[$t]['code'].'" theme-title="'.$list[$t]['title'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"></a>' : '').'
                    '.(file_exists(NAVIGATE_PATH.'/themes/'.$list[$t]['code'].'/'.$list[$t]['code'].'.info.html')? '<a href="#" class="navigrid-themes-info" theme="'.$list[$t]['code'].'" theme-title="'.$list[$t]['title'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></a>' : '').'
                    '.(empty($update_ver)? '' : '
                    '.($user->permission("themes.update")=="true"? '<a href="#" class="navigrid-themes-update" theme="'.$list[$t]['code'].'" title="'.t(285, "Update").' '.$update_ver.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"></a>' : '').'
                '),
                'footer' => '
                    '.(file_exists(NAVIGATE_PATH.'/themes/'.$list[$t]['code'].'/demo.html')? '<a href="'.NAVIGATE_URL.'/themes/'.$list[$t]['code'].'/demo.html'.'" class="uibutton navigrid-themes-button" target="_blank"><img height="16" align="absmiddle" width="16" src="img/icons/silk/monitor.png"> '.t(274, 'Preview').'</a>' : '').'
                    <a href="#" class="uibutton navigrid-themes-button navigrid-themes-install" theme="'.$list[$t]['code'].'" target="_blank" style=" margin-left: 5px; "><img height="16" align="absmiddle" width="16" src="img/icons/silk/world_go.png"> '.t(365, 'Install').'</a>
                '
            );
        }

        $grid->items($themes);

        $navibars->add_content($grid->generate());

        $navibars->add_content('
            <div id="navigrid-themes-install-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
                '.t(371, 'Installing a new theme removes the settings of the old one.').'<br />
                '.t(372, 'The list of available block types may also change.').'<br /><br />
                '.t(373, 'Are you sure you want to continue?').'
            </div>

            <div id="navigrid-themes-information" title="" style=" display: none; "></div>
        ');

        $navibars->add_content('
            <div id="navigrid-themes-install-demo-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
                '.t(483, 'Do you really want to import the default website for the theme selected?').'
            </div>'
        );

        $navibars->add_content('
            <div id="navigrid-themes-remove-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
                '.t(57, 'Do you really want to delete the item?').'
            </div>'
        );

        $navibars->add_content('
            <div id="navigrid-themes-update" title="'.t(285, 'Update').'" style=" display: none; ">
                <iframe src="about:blank"
                    class="ui-corner-all"
                    border="0" frameborder="0" allowtransparency="true">
                </iframe>
            </div>'
        );
    }
    else
    {
        $html = '
            <div class="navibrowse-path ui-corner-all">
                <input type="checkbox" id="theme-available-button" /><label for="theme-available-button"><img src="img/icons/silk/rainbow.png" width="16px" height="16px" align="absbottom" /> '.t(528, 'Available').'</label>
                <input type="checkbox" id="theme-marketplace-button" /><label for="theme-marketplace-button"><img src="img/icons/silk/basket.png" width="16px" height="16px" align="absbottom" /> '.t(527, 'Marketplace').'</label>
            </div>
        ';
        $html .= '
            <iframe src="http://www.navigatecms.com/en/marketplace/themes"
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
    }

    $layout->add_script('
        function navigatecms_marketplace_install_from_hash(hash)
        {
            window.location.replace("?fid=themes&act=install_from_hash&hash="+hash);
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

    $out = $navibars->generate();

    $layout->add_script('
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/themes/themes.js?r='.$current_version->revision.'",
	        complete: function()
	        {
                navigate_themes_init();
	        }
	    });
	');

	return $out;
}

function themes_sample_content_export_form()
{
    // templates, blocks, files, properties
    global $user;
    global $DB;
    global $website;
    global $layout;
    global $theme;

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(367, 'Themes').' / '.t(480, 'Export sample content'));

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
    );

    $navibars->form();

    /*
    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('theme-title', $website->name)
    ));
    */

    $navibars->add_tab(t(16, "Structure"));
    // select structure points to export
    $hierarchy = structure::hierarchy(0);
    $categories_list = structure::hierarchyList($hierarchy);

    $navibars->add_tab_content_row(array(
        '<label>'.t(330, 'Categories').'<br /></label>',
        '<div class="category_tree" id="category-tree-parent">
            <img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
            '<div class="tree_ul">'.$categories_list.'</div>'.
        '</div>',
        '<label>&nbsp;</label>',
        '<button id="theme_export_sample_content_select_all_categories">'.t(481, 'Select all').'</button>'
    ));

    $navibars->add_tab_content($naviforms->hidden('categories', ''));

    $layout->add_script('        
        $("#category-tree-parent .tree_ul").jstree({
            plugins: ["changed", "types", "checkbox"],
            "types" :
            {
                "default":  {   "icon": "img/icons/silk/folder.png"    },
                "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
            },
            "checkbox":
            {
                three_state: false,
                cascade: "undetermined"
            },
            "core":
            {
                dblclick_toggle: false
            }
        })
        .on("dblclick.jstree", function(e)
        {
            e.preventDefault();
            e.stopPropagation();
        
            var li = $(e.target).closest("li");
            $("#category-tree-parent .tree_ul").jstree("open_node", "#" + li[0].id);
        
            var children_nodes = new Array();
            children_nodes.push(li);
            $(li).find("li").each(function() {
                children_nodes.push("#" + $(this)[0].id);
            });
        
            $("#category-tree-parent .tree_ul").jstree("select_node", children_nodes);
        
            return false;
        })
        .on("changed.jstree", function(e, data)
        {        
            var i, j, r = [];
            var categories = new Array();
            $("#categories").val("");       
        
            for(i = 0, j = data.selected.length; i < j; i++)
            {
                var id = data.instance.get_node(data.selected[i]).data.nodeId;
                categories.push(id);
            }
            
            if(categories.length > 0)
                $("#categories").val(categories);                                                                
        });

        $("#theme_export_sample_content_select_all_categories").on("click", function(e)
        {
            e.stopPropagation();
            e.preventDefault();
            $("#category-tree-parent .tree_ul").jstree("select_all");
            return false;
        });
	');

    $navibars->add_tab(t(22, "Elements"));
    // select elements to export
    $navitable_items = new navitable("items_list");
    $navitable_items->setURL('?fid=items&act=1');
    $navitable_items->sortBy('date_modified', 'DESC');
	$navitable_items->setDataIndex('id');
	$navitable_items->max_rows = 9999999;
    $navitable_items->addCol("ID", 'id', "40", "true", "left");
    $navitable_items->addCol(t(67, 'Title'), 'title', "350", "true", "left");
    $navitable_items->addCol(t(309, 'Social'), 'comments', "80", "true", "center");
    $navitable_items->addCol(t(78, 'Category'), 'category', "150", "true", "center");
    $navitable_items->addCol(t(266, 'Author'), 'author_username', "100", "true", "left");
    $navitable_items->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
    $navitable_items->addCol(t(80, 'Permission'), 'permission', "80", "true", "center");
    $navitable_items->after_select_callback = ' $("#elements").val(navitable_items_list_selected_rows); ';
    $navitable_items->setLoadCallback('
        if(!themes_export_first_select_elements) 
        {
            $("#cb_items_list").trigger("click");
            themes_export_first_select_elements = true; 
        }
    ');
    $navibars->add_tab_content($naviforms->hidden('elements', ''));
    $navibars->add_tab_content($navitable_items->generate());


    $navibars->add_tab(t(544, "Block groups"));
    // select blocks to export
    $navitable_block_groups = new navitable("block_groups_list");
    $navitable_block_groups->setURL('?fid=blocks&act=block_groups_json');
    $navitable_block_groups->sortBy('id', 'DESC');
    $navitable_block_groups->setDataIndex('id');
    $navitable_items->max_rows = 9999999;
    $navitable_block_groups->addCol("ID", 'id', "80", "true", "left");
    $navitable_block_groups->addCol(t(237, 'Code'), 'code', "120", "true", "left");
    $navitable_block_groups->addCol(t(67, 'Title'), 'title', "200", "true", "left");
    $navitable_block_groups->addCol(t(23, 'Blocks'), 'blocks', "80", "true", "left");
    $navitable_block_groups->after_select_callback = ' $("#block_groups").val(navitable_block_groups_list_selected_rows); ';
    $navitable_block_groups->setLoadCallback('
        if(!themes_export_first_select_blockgrp) 
        {
            $("#cb_block_groups_list").trigger("click");
            themes_export_first_select_blockgrp = true; 
        }
    ');
    $navibars->add_tab_content($naviforms->hidden('block_groups', ''));
    $navibars->add_tab_content($navitable_block_groups->generate());


    $navibars->add_tab(t(23, "Blocks"));
    // select blocks to export
    $navitable_blocks = new navitable("blocks_list");
    $navitable_blocks->setURL('?fid=blocks&act=1');
    $navitable_blocks->sortBy('id', 'DESC');
    $navitable_blocks->setDataIndex('id');
    $navitable_items->max_rows = 9999999;
    $navitable_blocks->addCol("ID", 'id', "40", "true", "left");
    $navitable_blocks->addCol(t(160, 'Type'), 'type', "120", "true", "center");
    $navitable_blocks->addCol(t(67, 'Title'), 'title', "400", "true", "left");
    $navitable_blocks->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
    $navitable_blocks->addCol(t(364, 'Access'), 'access', "40", "true", "center");
    $navitable_blocks->addCol(t(65, 'Enabled'), 'enabled', "40", "true", "center");
    $navitable_blocks->after_select_callback = ' $("#blocks").val(navitable_blocks_list_selected_rows); ';
    $navitable_blocks->setLoadCallback('
        if(!themes_export_first_select_blocks) 
        {
            $("#cb_blocks_list").trigger("click"); 
            themes_export_first_select_blocks = true; 
        }
    ');
    $navibars->add_tab_content($naviforms->hidden('blocks', ''));
    $navibars->add_tab_content($navitable_blocks->generate());


    $navibars->add_tab(t(250, "Comments"));
    // select comments to export
    $navitable_comments = new navitable("comments_list");
    $navitable_comments->setURL('?fid=comments&act=1');
    $navitable_comments->sortBy('date_created', 'desc');
    $navitable_comments->setDataIndex('id');
    $navitable_items->max_rows = 9999999;
    $navitable_comments->addCol("ID", 'id', "80", "true", "left");
    $navitable_comments->addCol(t(180, 'Item'), 'item', "200", "true", "left");
    $navitable_comments->addCol(t(226, 'Date created'), 'date_created', "100", "true", "left");
    $navitable_comments->addCol(t(1, 'User'), 'user', "100", "true", "left");
    $navitable_comments->addCol(t(54, 'Text'), 'message', "200", "true", "left");
    $navitable_comments->addCol(t(68, 'Status'), 'status', "80", "true", "center");
    $navitable_comments->after_select_callback = ' $("#comments").val(navitable_comments_list_selected_rows); ';
    $navitable_comments->setLoadCallback('
        if(!themes_export_first_select_comments) 
        {
            $("#cb_comments_list").trigger("click"); 
            themes_export_first_select_comments = true; 
        }
    ');
    $navibars->add_tab_content($naviforms->hidden('comments', ''));
    $navibars->add_tab_content($navitable_comments->generate());
    
    $navibars->add_tab(t(89, "Files"));
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(141, 'Folder').'</label>',
            $naviforms->dropbox('folder', 0, 'folder')
        )
    );

    $navibars->add_tab_content_row(
        '<div class="subcomment"><span class="ui-icon ui-icon-info" style="float: left;"></span> '.
            t(482, 'All sample files should be placed in a folder. Navigate CMS will also add files used in contents.').
        '</div>'
    );

    // auto-select everything on load
    $layout->add_script('
        themes_export_first_select_elements = false;
        themes_export_first_select_blockgrp = false;
        themes_export_first_select_blocks   = false;
        themes_export_first_select_comments = false;

        $("#theme_export_sample_content_select_all_categories").trigger("click");               
    ');

    return $navibars->generate();
}

/* TODO: generate a theme from custom templates and blocks... maybe in NVCMS3.0?
function themes_export_form()
{
    // templates, blocks, files, properties
    global $user;
    global $DB;
    global $website;
    global $layout;
    global $theme;

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(367, 'Themes').' / '.t(475, 'Export'));

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
    );

    $navibars->form();

    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content(
        '<div class="subcomment"><span class="ui-icon ui-icon-info" style="float: left;"></span></div>'
    );

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('theme-title', $website->name)
    ));

    $navibars->add_tab_content_row(array(
        '<label>'.t(237, 'Code').'</label>',
        $naviforms->textfield('theme-name', $website->name)
    ));

    $layout->add_script('
        $("#theme-name").on("keyup", function()
        {
            var title = $(this).val();
			title = title.replace(/([\'"?:\+\&!¿#\\\\])/g, "");
			title = title.replace(/[.\s]+/g, "_");
            $(this).val(title.toLowerCase());
        });
        $("#theme-name").trigger("keyup");
    ');

    $navibars->add_tab_content_row(array(
        '<label>'.t(220, 'Version').'</label>',
        $naviforms->textfield('theme-version', '1.0')
    ));


    $navibars->add_tab_content_row(array(
        '<label>'.t(266, 'Author').'</label>',
        $naviforms->textfield('theme-author', $user->username)
    ));

    $navibars->add_tab_content_row(array(
        '<label>'.t(177, 'Website').'</label>',
        $naviforms->textfield('theme-website', $website->absolute_path())
    ));

    // languages (+auto create dictionary)
    // styles

    $navibars->add_tab(t(200, "Properties"));
    // similar to template properties

    $navibars->add_tab(t(20, "Templates"));
    // select templates to export

    $navibars->add_tab(t(23, "Blocks"));
    // select block types to export

    $navibars->add_tab(t(89, "Files"));
    // upload JS files
    // upload CSS files
    // upload IMG files
    // select files from database to be included

    // + demo structure, content & blocks?

    return $navibars->generate();
}
*/

?>