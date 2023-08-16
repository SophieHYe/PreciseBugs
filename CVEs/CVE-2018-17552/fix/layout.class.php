<?php
class layout
{
	public $type;
	public $scripts;
	public $before_includes;
	public $styles;
	public $styles_unmerged;
	public $js_code;
	public $buffer;

	public $parts_added;
	
	public function __construct($layout)	
	{
		$this->type = $layout;
		$this->before_includes = array();
		$this->parts_added = array();
	}
	
	public function doctype()
	{
        $out = array();

        if($this->type == 'navigate')
        {
		    $out[] = '<!DOCTYPE html>';
            $out[] = '<!--
              _   _             _             _          _____ __  __  _____
             | \ | |           (_)           | |        / ____|  \/  |/ ____|
             |  \| | __ ___   ___  __ _  __ _| |_ ___  | |    | \  / | (___
             | . ` |/ _` \ \ / / |/ _` |/ _` | __/ _ \ | |    | |\/| |\___ \
             | |\  | (_| |\ V /| | (_| | (_| | ||  __/ | |____| |  | |____) |
             |_| \_|\__,_| \_/ |_|\__, |\__,_|\__\___|  \_____|_|  |_|_____/
                                   __/ |
                                  |___/
            -->';
		    $out[] = '<html>';
        }
		return implode("\n", $out);
	}
	
	public function metatags()
	{
		global $website;
        global $user;
        $out = array();

        if($this->type == 'navigate')
        {
            if(!empty($website->name))
                $wtitle = $website->name.' | ';

            $out[] = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            $out[] = '<meta name="viewport" content="width=device-width, user-scalable=no">';
            $out[] = '<meta http-equiv="Expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />';
            $out[] = '<meta http-equiv="Pragma" content="no-cache" />';

            if(@$_REQUEST['navigate_privacy']=='true' || $user->permission('navigatecms.privacy_mode')=='true')
                $_SESSION['navigate_privacy'] = true;
	        else
		        $_SESSION['navigate_privacy'] = false;

            if(@$_SESSION['navigate_privacy'])
            {
                $out[] = '<link href="data:image/x-icon;base64,AAABAAEAEBACAAAAAACwAAAAFgAAACgAAAAQAAAAIAAAAAEAAQAAAAAAQAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA" rel="icon" type="image/x-icon" />';
                $out[] = '<title>&nbsp;</title>';
            }
            else
            {
                //$out[] = '<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">';
                $out[] = '<link rel="shortcut icon" type="image/png" href="img/navigate-isotype-16x16.png">';
                $out[] = '<title>'.$wtitle.APP_NAME.'</title>';
            }
        }
        
		return implode("\n", $out);
	}
	
	public function add_script_tag($src)
	{
		$this->scripts[] = $src;
	}
	
	public function add_style_tag($src, $merged_and_compressed=true)
	{
		if($merged_and_compressed)
			$this->styles[] = $src;
		else
			$this->styles_unmerged[] = $src;
	}
	
	public function includes()
	{
        global $current_version;

		$this->add_script_tag('js/navigate.js');
		
		$this->add_script_tag('js/jquery-ui.js');
		$this->add_script_tag('js/jquery-ui-i18n.min.js');

		$this->add_script_tag('js/plugins/browserdetect.js');

		$this->add_script_tag('lib/external/jquery-noselect/jquery.noselect.js');

        $this->add_script_tag('js/plugins/jquery.dialogextend.js');
		$this->add_script_tag('js/plugins/lazyload.js');
		
		$this->add_script_tag('lib/external/jquery-tag-it/js/tag-it.navigate-cms.js');
		$this->add_style_tag('lib/external/jquery-tag-it/css/jquery.tagit.css');

        $this->add_style_tag('js/jstree/themes/default/style.css');
		$this->add_script_tag('js/jstree/jstree.js');
        $this->add_script_tag('lib/external/raty-fa/lib/jquery.raty-fa.js');

		$this->add_script_tag('js/plugins/dropzone.js');

		$this->add_script_tag('js/plugins/jquery.ezCookie.js');
        $this->add_script_tag('js/plugins/jquery.longclick.js');
        $this->add_script_tag('js/plugins/uuid.js');
        $this->add_script_tag('js/plugins/jquery.inputmask.bundle.js');

		$this->add_style_tag('js/treetable/src/stylesheets/jquery.treeTable.css');
		$this->add_script_tag('js/treetable/src/javascripts/jquery.treeTable.min.js');

        $this->add_script_tag('js/plugins/jquery-ui-timepicker-addon.js');

		$this->add_script_tag('lib/external/jgrowl/jquery.jgrowl.js');
		$this->add_style_tag('lib/external/jgrowl/jquery.jgrowl.css');

        $this->add_script_tag('lib/external/select2/js/select2.full.js');
        $this->add_style_tag('lib/external/select2/css/select2.css');

        $this->add_script_tag('js/plugins/jquery.tablednd.js');

        $this->add_script_tag('js/plugins/jquery.query.js');

		$this->add_style_tag('lib/external/jqgrid/css/ui.jqgrid.css');	
		//$this->add_script_tag('lib/external/jqgrid/js/i18n/grid.locale-'.$user->language.'.js', true);	// must be loaded after jquery, see before_includes
		$this->add_script_tag('lib/external/jqgrid/jquery.jqgrid.src.js');

        $this->add_style_tag('lib/external/qtip2/jquery.qtip.css');
		$this->add_script_tag('lib/external/qtip2/jquery.qtip.js');

        $this->add_style_tag('lib/external/jautochecklist/css/jAutochecklist.css');
		$this->add_script_tag('lib/external/jautochecklist/js/jAutochecklist.js');
		
		$this->add_script_tag('lib/external/flot/jquery.flot.js');
		$this->add_script_tag('lib/external/flot/jquery.flot.pie.js');		
		$this->add_script_tag('lib/external/flot/jquery.flot.time.js');
		$this->add_script_tag('lib/external/flot/jquery.flot.navigate.js');

		$this->add_script_tag('lib/external/plupload/js/plupload.full.min.js');
		$this->add_script_tag('lib/external/plupload/js/jquery.ui.plupload/jquery.ui.plupload.min.js');
		$this->add_style_tag('lib/external/plupload/js/jquery.ui.plupload/css/jquery.ui.plupload.css');

		$this->add_script_tag('js/plugins/pixlr.js');

        $this->add_script_tag('js/plugins/jquery.hotkeys.js');
        $this->add_script_tag('lib/external/jquery-caret/jquery.caret.js');
        $this->add_script_tag('lib/external/jquery.base64/jquery.base64.js');
        $this->add_script_tag('lib/external/jquery-truncate/jquery.truncate.js');

		// tinymce 4
		$this->add_script_tag('lib/external/tinymce4/tinymce.min.js');
		$this->add_script_tag('lib/external/tinymce4/jquery.tinymce.min.js');
        $this->add_script_tag('lib/external/tinymce4/themes/modern/theme.min.js');

		// tinymce plugins compression (speeds up loading editor)
        $this->add_script_tag('lib/external/tinymce4/plugins/compat3x/plugin.min.js'); // optimization disabled because produces a js error
        $this->add_script_tag('lib/external/tinymce4/plugins/noneditable/plugin.min.js');
        $this->add_script_tag('lib/external/tinymce4/plugins/advlist/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/autolink/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/nv_link/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/image/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/lists/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/charmap/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/print/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/preview/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/hr/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/anchor/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/pagebreak/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/searchreplace/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/wordcount/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/visualblocks/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/visualchars/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/fullscreen/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/media/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/nonbreaking/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/table/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/directionality/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/template/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/table/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/textcolor/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/paste/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/colorpicker/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/textpattern/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/codesample/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/codemirror/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/imagetools/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/importcss/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/magicline/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/fontawesome/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/nv_rollups/plugin.min.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/loremipsum/editor_plugin.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/imgmap/editor_plugin.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/style/editor_plugin.js');
		$this->add_script_tag('lib/external/tinymce4/plugins/xhtmlxtras/editor_plugin.js');


		// mb extruder
		$this->add_style_tag('js/mbextruder/css/mbExtruder.css');
		$this->add_script_tag('js/mbextruder/inc/jquery.mb.flipText.js');
		$this->add_script_tag('js/mbextruder/inc/jquery.hoverIntent.min.js');
		$this->add_script_tag('js/mbextruder/inc/mbExtruder.js');		
		
		$this->add_script_tag('lib/external/codemirror/lib/codemirror.js');
        $this->add_script_tag('lib/external/codemirror/addon/hint/html-hint.js');
        $this->add_script_tag('lib/external/codemirror/addon/hint/javascript-hint.js');
        $this->add_script_tag('lib/external/codemirror/addon/hint/show-hint.js');
        $this->add_script_tag('lib/external/codemirror/addon/edit/closebrackets.js');
        $this->add_script_tag('lib/external/codemirror/addon/edit/closetag.js');
        $this->add_script_tag('lib/external/codemirror/addon/selection/active-line.js');
		$this->add_script_tag('lib/external/codemirror/mode/xml/xml.js');
		$this->add_script_tag('lib/external/codemirror/mode/javascript/javascript.js');
		$this->add_script_tag('lib/external/codemirror/mode/css/css.js');
        $this->add_script_tag('lib/external/codemirror/mode/htmlmixed/htmlmixed.js');

		$this->add_style_tag('lib/external/codemirror/lib/codemirror.css');
		$this->add_style_tag('lib/external/codemirror/addon/hint/show-hint.css');

		$this->add_script_tag('lib/external/mediaelement/mediaelement-and-player.js');
		$this->add_style_tag('lib/external/mediaelement/mediaelementplayer.css');

        $this->add_style_tag('lib/external/leaflet/leaflet.css');
        $this->add_script_tag('lib/external/leaflet/leaflet.js');

		$this->add_script_tag('lib/external/colorpicker/jquery.colorpicker.js');
		$this->add_script_tag('lib/external/colorpicker/parts/jquery.ui.colorpicker-memory.narrow.js');
		$this->add_script_tag('lib/external/colorpicker/i18n/jquery.ui.colorpicker-en.js');
		$this->add_script_tag('lib/external/colorpicker/i18n/jquery.ui.colorpicker-es.js');
		$this->add_script_tag('lib/external/colorpicker/i18n/jquery.ui.colorpicker-ca.js');
		$this->add_script_tag('lib/external/colorpicker/i18n/jquery.ui.colorpicker-de.js');
		$this->add_script_tag('lib/external/colorpicker/i18n/jquery.ui.colorpicker-pl.js');
		$this->add_style_tag('lib/external/colorpicker/jquery.colorpicker.css');

        $this->add_script_tag('lib/external/jquery.uix.multiselect/js/jquery-multiselect-2.0.js');
        $this->add_style_tag('lib/external/jquery.uix.multiselect/css/jquery-multiselect-2.0.css');

		//$this->add_style_tag('css/skins/blue.css');	
		$this->add_style_tag('css/silk-sprite/silk-sprite.css');

        $this->add_script_tag('js/navigate_backcompat.js');

		if(APP_DEBUG)
		{
			foreach($this->styles as $cssfile)
				$out[] = '<link rel="stylesheet" type="text/css" href="'.$cssfile.'?r='.$current_version->revision.'" />';
		}
		else
		{
            $stylesheets = glob('cache/*.css');

			if(empty($stylesheets))
			{
				$tmp = '';
				foreach($this->styles as $cssfile)
				{
					$cssfile_content = file_get_contents($cssfile);	

					if(strpos($cssfile_content, "url('")!==false)
					{
						$cssfile_content = str_replace("url('", 'url(', $cssfile_content);
						$cssfile_content = str_replace("')", ')', $cssfile_content);	
					}
					else if(strpos($cssfile_content, 'url("')!==false)
					{					
						$cssfile_content = str_replace('url("', 'url(', $cssfile_content);
						$cssfile_content = str_replace('")', ')', $cssfile_content);
					}
					
					$cssfile_content = str_replace('url(', 'url(../'.dirname($cssfile).'/', $cssfile_content);
					
					$tmp .= mb_convert_encoding($cssfile_content, 'UTF-8', mb_detect_encoding($cssfile_content, 'UTF-8, ISO-8859-1', true));
					$tmp .= "\n\n";		
				}
				
				// remove all charset declarations (for webkit and standards compliance)
				//$tmp = preg_replace('/^@charset\s+[\'"](\S*)\b[\'"];/i', '', $tmp);
				$tmp = str_replace('@charset "utf-8";', '', $tmp);
				// and set it only at the beggining
				$tmp = '@charset "utf-8"; '.$tmp; 				

				file_put_contents('cache/styles.css', $tmp);

                $tmp = CssMin::minify($tmp, array("RemoveLastDelarationSemiColon" => false));
                file_put_contents('cache/styles.min.css', $tmp);

                if(file_exists('cache/styles.min.css') && filesize('cache/styles.min.css') > 0)
                {
                    // cleaning
                    @unlink('cache/styles.css');
                    @rename('cache/styles.min.css', 'cache/styles.'.time().'.min.css');
                }
			}

            clearstatcache();

            // locate the latest CSS stylesheet
            $stylesheets = glob('cache/styles.*.min.css');
            $stylesheet = array_pop($stylesheets);

            for($ss=0; $ss < count($stylesheets); $ss++)
                @unlink($stylesheets[$ss]);

			if(!empty($stylesheet))
				$out[] = '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'?r='.$current_version->revision.'&_='.filemtime($stylesheet).'" />';
			else			
				$out[] = '<link rel="stylesheet" type="text/css" href="cache/styles.css?r='.$current_version->revision.'&_='.filemtime('cache/styles.css').'" />';
		}
		
		if(APP_DEBUG)
		{
			foreach($this->scripts as $jsfile)
			{
				$out[] = '<script language="javascript" src="'.$jsfile.'?r='.$current_version->revision.'" type="text/javascript"></script>';
			}
		}
		else
		{
            $javascripts = glob('cache/scripts.*.min.*');

			if(empty($javascripts))
			{
			    if(!file_exists('cache/scripts.js') || filesize('cache/scripts.js')==0)
                {
                    $tmp = '';
                    foreach ($this->scripts as $jsfile)
                    {
                        $jsfile_content = file_get_contents($jsfile);
                        $tmp .= mb_convert_encoding($jsfile_content, 'UTF-8', mb_detect_encoding($jsfile_content, 'UTF-8, ISO-8859-1', true));
                        $tmp .= "\n";
                    }
                    file_put_contents('cache/scripts.js', $tmp);
                }

				/*
				if(JAVA_RUNTIME!='')
				{
					// YUI COMPRESSOR (can't redistribute)
                    exec(JAVA_RUNTIME." -jar cache/yuicompressor.jar cache/scripts.js -o cache/scripts.min.js --charset utf-8");
				}
				*/
                // does not work as expected
                //$packer = new JavaScriptPacker($tmp, 'None', true, true);
                //$tmp = $packer->pack();
                //file_put_contents('cache/scripts.min.js', $tmp);

                // gzip compression
                if(file_exists('cache/scripts.min.js'))
                {
                    $scripts_min = file_get_contents('cache/scripts.min.js');
                    file_put_contents('cache/scripts.min.jgz', gzencode($scripts_min, 8)); // level 9 causes parsing errors !?
                }
                else
                    file_put_contents('cache/scripts.min.jgz', gzencode($tmp, 8)); // level 9 causes parsing errors !?

				if(file_exists('cache/scripts.min.jgz'))
				{
					// cleaning
					// @unlink('cache/scripts.js'); don't remove this file, it is used as a fallback when the server cannot serve jgz files
					// @unlink('cache/scripts.min.js');
                    @rename('cache/scripts.min.jgz', 'cache/scripts.'.time().'.min.jgz');
				}
			}

            clearstatcache();

            // locate the latest CSS stylesheet
            $javascripts = glob('cache/scripts.*.min.jgz');
            $javascript = array_pop($javascripts);

            for($js=0; $js < count($javascripts); $js++)
                @unlink($javascripts[$js]);

            if(!empty($javascript) && !APP_FAILSAFE)
            {
                $out[] = '<script language="javascript" src="'.$javascript.'?r='.$current_version->revision.'&_='.filemtime($javascript).'" type="text/javascript" ></script>';
                // fallback when the compressed scripts version could not be loaded by the browser (incorrect .jgz client/server gzip encoding)
                $out[] = '<script language="javascript" type="text/javascript">if(typeof(navigate_window_resize)=="undefined"){$.ajax({async: false, url: "'.NAVIGATE_URL.'/cache/scripts.js?r='.$current_version->revision.'&_='.filemtime('cache/scripts.js').'", dataType: "script", cache: true});}</script>';
            }
            else
            {
                $out[] = '<script language="javascript" src="cache/scripts.js?r='.$current_version->revision.'&_='.filemtime('cache/scripts.js').'" type="text/javascript"></script>';
            }
		}
		
		return implode("\n", $out);
	}
	
	public function before_includes()
	{
		global $user;
        global $current_version;

		if(empty($user->skin)) $user->skin = 'cupertino';
		if(empty($user->language)) $user->language = 'en';

		$out[] = '<script language="javascript" src="'.NAVIGATE_URL.'/js/jquery.min.js?r='.$current_version->revision.'"></script>';
		$out[] = '<script language="javascript" src="'.NAVIGATE_URL.'/js/jquery-migrate.js?r='.$current_version->revision.'"></script>';

        //$out[] = '<script language="javascript" type="text/javascript">$.uiBackCompat = false;</script>';
        if(APP_DEBUG)
            $out[] = '<script language="javascript" type="text/javascript">jQuery.migrateMute = true;</script>';
        else
            $out[] = '<script language="javascript" type="text/javascript">jQuery.migrateTrace = false;</script>';

        // jqgrid translation
		$out[] = '<script language="javascript" src="'.NAVIGATE_URL.'/lib/external/jqgrid/i18n/grid.locale-'.$user->language.'.js?r='.$current_version->revision.'"></script>';

        // jquery ui custom css
        $out[] = '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/'.$user->skin.'/jquery-ui.css?r='.$current_version->revision.'" />';

		return implode("\n", $out);
	}
	
	public function after_includes()
	{
		global $user;
        global $current_version;

        $out = array();

		if(empty($user->skin)) $user->skin = 'cupertino';
		$out[] = '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/skins/'.$user->skin.'.css?r='.$current_version->revision.'" />';

        $out[] = '<script type="text/javascript">$.datepicker.setDefaults( $.datepicker.regional[ "'.$user->language.'" ] );</script>';

        // select2 translation (if not english)
        if($user->language != 'en')
        {
            $out[] = '<script language="javascript" src="'.NAVIGATE_URL.'/lib/external/select2/js/i18n/'.$user->language.'.js?r='.$current_version->revision.'"></script>';
            $out[] = '<script language="javascript">$.fn.select2.defaults.set("language", "'.$user->language.'");</script>';
        }

        $out[] = '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/font-awesome/css/font-awesome.min.css?r='.$current_version->revision.'" />';

		if(!empty($this->styles_unmerged))
		{
			foreach($this->styles_unmerged as $css_file)
			{
				$out[] = '<link rel="stylesheet" type="text/css" href="'.$css_file.'?r='.$current_version->revision.'" />';
			}
		}

        return implode("\n", $out);
	}	
	
	public function head()
	{
        $out = array();

        if($this->type=='navigate')
        {
            $out[] = '<head>';
            $out[] = $this->metatags();
            $out[] = $this->before_includes();
            $out[] = $this->includes();
            $out[] = $this->after_includes();
            $out[] = '</head>';
        }
		
		return implode("\n", $out);
	}
	
	public function body()
	{
        $out = array();
        
		if(!empty($this->buffer))
        {
            $out[] = '<body>';
            $out[] = implode("\n", $this->buffer);
            $out[] = '</body>';
        }

		return implode("\n", $out);
	}
	
	public function add_content($data)
	{
		$this->buffer[] = $data;	
	}
	
	public function add_script($js)
	{
		$this->js_code[] = $js;
	}
	
	public function javascript()
	{
		$out = array();

        // TODO: translate or try to fix this issue
        $out[] = '<script language="javascript" type="text/javascript">';
        $out[] = '
            if(!$.tableDnD)
            {
                console.log("Navigate CMS: javascript problem");
                /*
                if(confirm("There is a problem with your browser and the server that could make Navigate CMS unusable.\nNavigate CMS will try to force a refresh to try to overcome the problem."))
                {
                    window.location.replace(window.location.href);
                }
                */
            }
        ';
        $out[] = '</script>';


        if(!empty($this->js_code))
		{
			$out[] = '<script language="javascript" type="text/javascript">';
			$out[] = implode("\n", $this->js_code);
			$out[] = '</script>';
		}
		
		return implode("\n", $out);
	}
	
	public function close_tag()
	{
        $out = '';
        
        if($this->type=='navigate')
        {
		    $out = '</html>';
        }

        return $out;
	}
	
	public function navigate_logo()
	{
        global $user;
		$style = '';

		if(@$_SESSION['navigate_privacy']==true || $user->permission('navigatecms.privacy_mode')=='true')
			$style = ' style="opacity: 0.1;" ';
		
		$this->add_content(	' <div class="navigate-logo"'.$style.'>'.
							'	<a href="?"><img src="img/navigate-logo-150x70.png" /></a>'.
							' </div>');	
	}
	
	public function navigate_session()
	{
		global $website;
		global $user;
		global $DB;

		$fid = $_REQUEST['fid'];
		if(empty($fid))
			$fid = 'dashboard';

		$user_profile_name = $DB->query_single(
		    'name',
            'nv_profiles',
            'id = :user_profile',
            'NULL',
            array(':user_profile' => $user->profile)
        );

		$this->add_content(
			'<div class="navigate-help">'.
                (empty($website->id)? '' : '<a class="navigate-plus-link" href="#" title="'.t(38, 'Create').'"><i class="fa fa-fw fa-plus"></i></span></a>').
				//'<a class="navigate-favorites-link" href="#" title="'.t(465, 'Favorites').'"><i class="fa fa-fw fa-heart"></i></span></a>'.
				'<a class="navigate-help-link" title="'.t(302, 'Help').'" href="http://www.navigatecms.com/help?lang='.$user->language.'&fid='.$fid.'" target="_blank"><i class="fa fa-fw fa-question"></i></a>'.
				'<a class="navigate-logout-link" href="?logout" title="'.t(5, 'Logout').'"><i class="fa fa-fw fa-power-off"></i></span></a>'.
			'</div>'.
			'<div class="navigate-session">'.
                (empty($website->id)? '' : '<a href="#" id="navigate-recent-items-link"><div><span class="ui-icon ui-icon-triangle-1-s" style=" float: right; "></span><img src="img/icons/silk/briefcase.png" width="16px" height="16px" align="absmiddle" /> '.t(275, 'Recent items').'</div></a>').
				'<a class="bold" href="?fid=2" title="'.$user_profile_name.'"><img src="img/icons/silk/user.png" width="16px" height="16px" align="absmiddle" /> '.$user->username.'</a>'.
			'</div>'
		);

        if(!empty($website->id))
        {
			// recent items panel (loaded by an ajax request)
			$this->add_content('<ul id="navigate-recent-items" class="hidden"></ul>');

			// favorite extensions panel
			// TODO: retrieve user's favorites
			// $this->add_content('<div id="navigate-favorite-extensions" class="ui-dialog ui-widget ui-corner-all hidden"></div>');

            $this->add_content(
                '<ul id="navigate-create-helper" style=" display: none; ">'.
                    '<li id="navigate-create-helper-item"><a href="?fid=items&act=edit"><img class="silk-sprite silk-page" src="img/transparent.gif" width="16" height="16" align="absmiddle" /> '.t(630, 'Element').'</a></li>'.
                    '<li id="navigate-create-helper-block"><a href="?fid=blocks&act=edit"><img class="silk-sprite silk-brick" src="img/transparent.gif" width="16" height="16" align="absmiddle" /> '.t(437, 'Block').'</a></li>'.
                    '<li id="navigate-create-helper-structure"><a href="?fid=structure&act=edit"><img class="silk-sprite silk-sitemap_color" src="img/transparent.gif" width="16" height="16" align="absmiddle" /> '.t(479, 'Structure entry').'</a></li>'.
                '</ul>'
            );

            $this->add_script('
                $(".navigate-plus-link").on("click", function()
                {
                    $("#navigate-create-helper").menu();
                    $("#navigate-create-helper").css({
                        "position": "absolute",
                        "top": $(".navigate-plus-link").offset().top,
                        "left": $(".navigate-plus-link").offset().left - $("#navigate-create-helper").width() + 10,
                        "z-index": 1000
                    });
                    $("#navigate-create-helper").addClass("navi-ui-widget-shadow");
                    $("#navigate-create-helper").show();
                });
            ');
        }
	}
	
	public function navigate_title()
	{
		global $website;
		global $DB;

		$DB->query('SELECT * FROM nv_websites WHERE 1 = 1 ORDER BY name ASC');
		$websites = $DB->result();

		$extruder = '';
		
		$main_title = '';
		$main_url = '';

		foreach($websites as $web)
		{
			$style = ' display: none; ';

            $ws = new website();
            $ws->load_from_resultset(array($web));
			
			if($ws->id == $website->id)
				$style = ' display: block; ';

            $url = $ws->absolute_path(true);
					
			if($ws->id == $website->id)
			{
				$main_title = $ws->name;
				$main_url = $url;
                $main_config_url = '?fid=websites&act=2&id='.$ws->id;
			}
			else			
				$extruder .= '<div class="voice {}" style=" display: none; ">
								<a href="'.$url.'" target="_blank"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/house_link.png" width="16px" height="16px" /></a>
								<a class="label" href="?act=0&wid='.$ws->id.'">'.$ws->name.'</a>								
							  </div>';
		}

        $extruder .= '<div style="clear: both;"></div>';
		
		if(!empty($main_title)) // at least we have ONE website
		{
			// mb extruder
			$this->add_content('
			  <div id="navigate-website-selector-top" class="{title:\''.str_replace("'", "\\'", @htmlspecialchars($main_title)).'\'}">
				'.$extruder.'
			  </div>
			  <a id="navigate-website-main-link" href="'.$main_url.'" target="_blank" style=" margin-right: 5px; display: none; "><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/house_link.png" width="16px" height="16px" /></a>
			');
			
			$this->add_script('
				$("#navigate-website-selector-top").buildMbExtruder(
				{
					positionFixed:true,
					width:400,
					sensibility:800,
					position:"top", // left, right, bottom
					extruderOpacity: 1, // was 0.9 for better integration (FF 8.0 problems)
					flapDim:100,
					textOrientation:"bt", // or "tb" (top-bottom or bottom-top)
					onExtOpen:function() {},
					onExtContentLoad:function(){},
					onExtClose:function(){},
					hidePanelsOnClose:true,
					autoCloseTime:3000, // 0=never
					slideTimer:300
				});
			');
			
			$this->add_script('
			    $("#navigate-website-selector-top").find(".flapLabel").css("padding-left", "21px");
			    $("#navigate-website-selector-top div.flap").addClass("ui-corner-bottom");
			    $("#navigate-website-selector-top div.flap").css("opacity", 1);
			    $("#navigate-website-selector-top").on("click", ".silk-sprite", function(e)
			    {
			        setTimeout(
			            function()
			            {
			                $("#navigate-website-selector-top").closeMbExtruder();
		                }, 
		                1000
	                );
			    });
			');
		}		
	}
	
	public function navigate_footer()
	{
        global $user;
        global $current_version;

        $version = ' v'.$current_version->version; //.' r'.$current_version->revision;
        if($user->permission('navigatecms.display_version')=='false')
            $version = '';

		$this->add_content('
		    <div id="navigate-status" class="ui-corner-all">
                <div>
                    <div style="float: left;" id="navigate-status-info">
                        <img src="'.NAVIGATE_URL.'/img/loader.gif" width="16px" height="16px" align="absmiddle"> '.t(6, 'Loading').'...
                    </div>
                    <div style="float: right; font-weight: normal;">
                        <a href="?fid=about">'.APP_NAME.$version.'</a>, <a href="http://www.naviwebs.com" target="_blank"><i class="fa fa-copyright"></i> '.date('Y').'</a>
                        <a class="navigate-hidemenu-link" href="#">&#9650;</a>
                    </div>
                    <div style=" clear: both; "></div>
                </div>
            </div>'
        );
	}

    public function navigate_additional_scripts()
    {
        global $website;
        global $user;

        $this->add_script(' var NAVIGATE_DOWNLOAD = "'.NAVIGATE_DOWNLOAD.'"; ');
        $this->add_script(' var NAVIGATE_URL = "'.NAVIGATE_URL.'"; ');
        $this->add_script(' var NAVIGATE_APP = "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'"; ');
        $this->add_script(' var NAVIGATE_MAX_UPLOAD_SIZE = '.NAVIGATE_UPLOAD_MAX_SIZE.'; ');
        $this->add_script('
            var navigate_lang_dictionary = {
                6: "'.t(6, 'Loading').'",
                11: "'.t(11, 'Multimedia').'",
                17: "'.t(17, 'Permissions').'",
                35: "'.t(35, 'Delete').'",
                40: "'.t(40, 'History').'",
                41: "'.t(41, 'Search').'",
                42: "'.t(42, 'Ready').'",
                53: "'.t(53, 'Data successfully saved').'",                
                56: "'.t(56, 'Unexpected error').'",
                57: "'.t(57, 'Do you really want to delete this item?').'",
                58: "'.t(58, 'Cancel').'",
                59: "'.t(59, 'Confirmation').'",
                67: "'.t(67, 'Title').'",
                77: "'.t(77, 'Properties').'",
                82: "'.t(82, 'File').'",
                92: "'.t(92, 'Close').'",
                141: "'.t(141, 'Folder').'",
                152: "'.t(152, 'Continue').'",
                159: "'.t(159, 'Name').'",
                170: "'.t(170, 'Edit').'",
                171: "'.t(171, 'Order').'",
                185: "'.t(185, 'Searching elements').'",
                189: "'.t(189, 'Copy from').'",
                190: "'.t(190, 'Ok').'",
                258: "'.t(258, 'Publish').'",
                260: "'.t(260, 'Drag & Drop files now to upload them').'",
                261: "'.t(261, 'Uploading').'",
                262: "'.t(262, 'Error uploading file').'",
                270: "'.t(270, 'Auto-save in progress').'",
                271: "'.t(271, 'Auto-save completed').'",
                274: "'.t(274, 'Preview').'",
                286: "'.t(286, 'Drag to reorder. Double click a item to set a caption.').'",
                334: "'.t(334, 'Description').'",
                368: "'.t(368, 'Theme').'",
                389: "'.t(389, 'Backspace key protection').'",
                401: "'.t(401, 'Your browser does not support HTML5').'",
                402: "'.t(402, 'Please select a fewer number of files').'",
                403: "'.t(403, 'File too large').'",
                440: "'.t(440, 'Error saving the data, please do an external backup of your changes to prevent data loss').'",
                472: "'.t(472, 'Add').'",
                476: "'.t(476, 'Copy to clipboard').'",
                477: "'.t(477, 'Duplicate').'",
                492: "'.t(492, 'No matches found').'",
                493: "'.t(493, 'Loading more results...').'",
                494: "'.t(494, 'Searching...').'",
                495: "'.t(495, 'Please enter at least {number} characters').'",
                496: "'.t(496, 'You can only select {number} items').'",
                497: "'.t(497, "Do you really want to erase this data?").'",
                499: "'.t(499, 'Open').'",
                500: "'.t(500, 'Rename').'",
                501: "'.t(501, 'Previous').'",
                502: "'.t(502, 'Next').'",
                540: "'.t(540, 'Focal point').'",
                580: "'.t(580, 'Used in this page').'",
                581: "'.t(581, 'None').'",
                582: "'.t(582, "default").'",
                583: "'.t(583, "Same width").'",
                584: "'.t(584, "Same height").'",
                585: "'.t(585, "Same width & height (scaled)").'",
                586: "'.t(586, "Same width & height (cropped)").'",
                587: "'.t(587, "Full available width").'",
                588: "'.t(588, "Replace image").'",
                601: "'.t(601, "Import").'",
                620: "'.t(620, "Insert").'",
                621: "'.t(621, "Append").'",
                627: "'.t(627, "Remove").'",                                
                739: "'.t(739, "Download").'"                                
            };
        ');

        $this->add_script(' var navigate = Array(); ');
        $this->add_script(' navigate["website_id"] = "'.$website->id.'";');
        $this->add_script(' navigate["session_id"] = "'.session_id().'";');
        $this->add_script(' navigate["word_separator"] = "'.$website->word_separator.'";');
        $this->add_script(' navigate["decimal_separator"] = "'.value_or_default($user->decimal_separator, ".").'";');
    }
	
	public function navigate_notification($text, $isError=false, $sticky=false, $css_icon="")
	{
		$text = str_replace("\n", '', $text);
		$text = str_replace("\r", '', $text);
		$text = str_replace('"', '&quot;', $text);		
		
        if($sticky==1 || $sticky=='true')
            $sticky = 'true';
        else
            $sticky = 'false';

        $icon = '';
        if(!empty($css_icon))
            $icon = '<i class=\''.$css_icon.'\'></i> ';

		$this->add_script('
		    $.jGrowl.defaults.position = "center";
		    $.jGrowl("'.$icon.$text.'", { 
		        life: 4000,
                sticky: '.$sticky.',
                open: function() { 
                    setTimeout(function() { 
                        $(".jGrowl-notification").css({"background-repeat": "repeat"}); 
                    }, 50);
                } 
            });');

		//$this->add_script('$("#jGrowl").css({"top": "36px"});');
		//$this->add_script('$(".jGrowl-notification").css({"background-color": "#fda700", "background-image": "none", "border-color": "#6c1108"});');
	}
	
	public function navigate_message($type="info", $title, $text)
	{
		$navibars = new navibars();
		$navibars->title($title);
		$navibars->add_content($text);	
	
		return $navibars->generate();
	}

    public function navigate_notes_dialog($element_type, $element_id)
    {
        global $user;

        $grid_control_url = "?fid=grid_notes&object=".$element_type."&act=";

        $this->add_script("
            function navigate_display_notes_dialog(callback)
            {
                var row_id = ".$element_id.";
                // open item notes dialog
                $('<div><img src=\"".NAVIGATE_URL."/img/loader.gif\" style=\" top: 162px; left: 292px; position: absolute; \" /></div>').dialog({
                    modal: true,
                    width: 600,
                    height: 400,
                    title: '".t(168, "Notes")."',
                    open: function(event, ui)
                    {
                        var container = this;
                        $.getJSON('".$grid_control_url."grid_notes_comments&id=' + row_id, function(data)
                        {
                            $(container).html('".
                                '<div><form action="#" onsubmit="return false;" method="post"><span class=\"grid_note_username\">'.$user->username.'</span><button class="grid_note_save">'.t(34, 'Save').'</button><br /><textarea id="grid_note_comment" class="grid_note_comment"></textarea></form></div>'
                                ."');

                            for(d in data)
                            {
                                var note = '<div class=\"grid_note ui-corner-all\" grid-note-id=\"'+data[d].id+'\" style=\" background: '+data[d].background+'; \">';
                                note += '<span class=\"grid_note_username\">'+data[d].username+'</span>';
                                note += '<span class=\"grid_note_remove\"><img src=\"".NAVIGATE_URL."/img/icons/silk/decline.png\" /></span>';
                                note += '<span class=\"grid_note_date\">'+data[d].date+'</span>';
                                note += '<span class=\"grid_note_text\">'+data[d].note+'</span>';
                                note += '</div>';

                                $(container).append(note);
                            }

                            $(container).find('.grid_note_remove').on('click', function()
                            {
                                var grid_note = $(this).parent();

                                $.get('".$grid_control_url."grid_note_remove&id=' + $(this).parent().attr('grid-note-id'), function(result)
                                {
                                    if(result=='true')
                                    {
                                        var number_of_notes = parseInt($('.navigate_grid_notes_span').text()) - 1;
                                        if(number_of_notes < 0) number_of_notes = 0;
                                        $(grid_note).fadeOut();
                                        $('.navigate_grid_notes_span').html(number_of_notes);
                                        if(callback) callback('".$element_type."', '".$element_id."');
                                    }
                                });
                            });

                            $(container).find('.grid_note_save').button(
                            {
                                icons: { primary: 'ui-icon-disk' }
                            }).on('click', function()
                            {
                                var background_color;
                                if($('#' + row_id).find('.grid_color_swatch').length > 0)
                                    background_color = $('#' + row_id).find('.grid_color_swatch').attr('ng-background');
                                else
                                    background_color = $('.grid_note:visible:first').css('background-color');

                                $.post('".$grid_control_url."grid_notes_add_comment',
                                {
                                    comment: $(container).find('.grid_note_comment').val(),
                                    id: row_id,
                                    background: background_color
                                },
                                function(result)
                                {
                                    if(result=='true') // reload dialog and table
                                    {
                                        $(container).parent().remove();
                                        $('.navigate_grid_notes_span').html(parseInt($('.navigate_grid_notes_span').text()) + 1);
                                        if(callback) callback('".$element_type."', '".$element_id."');
                                    }
                                });
                            });
                        });
                    }
                });
            };
        ");
    }
	
	public function navigate_media_browser()
	{	
		global $DB;
        global $website;
		global $events;
		global $user;
        global $current_version;

		if(in_array('media_browser', $this->parts_added))
			return;

		array_push($this->parts_added, 'media_browser');

        $naviforms = new naviforms();
		
		$DB->query('
			SELECT *
			  FROM nv_websites
			 WHERE id = '.$website->id.'
			    OR (share_files_media_browser = 1 AND id <> '.$website->id.')
			 ORDER BY name ASC
		');
		$websites = $DB->result();	
	
		$html = array();
		
		$html[] = '<div id="navigate-media-browser">';
		$html[] = '	<form action="#"> ';
		
		// website selector
		$html[] = '		<input id="media_browser_website" type="hidden" />';
		$html[] = '		<a href="#" id="navigate_media_browser_website" class="uibutton"><img src="img/icons/silk/world.png" sprite="true" align="left" /> <span class="ui-icon ui-icon-triangle-1-s"></span></a>';
		$html[] = '		<div id="navigate_media_browser_website_list" class="ui-dialog ui-widget ui-corner-all">
		                    <div id="navigate_media_browser_website_list_wrapper">';
		foreach($websites as $ws)
		{
			$html[]	= '		    <div website_id="'.$ws->id.'" class="uibutton" title="'.htmlspecialchars($ws->name).'">'.$ws->name.'</div>';
		}
		$html[] = '		    </div>
		                </div>';

		if($user->permission("files.upload")=="true")
            $html[] = ' 	<div id="navigate_media_browser_upload_button" class="uibutton"><img src="img/icons/silk/page_white_get.png" width="16" height="16" /></div>';

		// resource type selector
		$html[] = ' 	<div id="navigate_media_browser_buttons" >';

        $html[] = '         <select id="media_browser_type" name="media_browser_type">';
        $html[] = '             <option value="image" selected="selected" data-class="ui-icon-image" id="nvmb-image">'.t(29, 'Images').'</option>';
        $html[] = '             <option value="audio" data-class="ui-icon-volume-off" id="nvmb-audio">'.t(31, 'Audio').'</option>';
        $html[] = '             <option value="video" data-class="ui-icon-video" id="nvmb-film">'.t(30, 'Video').'</option>';
        $html[] = '             <option value="flash" data-class="ui-icon-script" id="nvmb-flash">'.t(186, 'Adobe Flash').'</option>';
        $html[] = '             <option value="document" data-class="ui-icon-document" id="nvmb-doc">'.t(32, 'Documents').'</option>';
        $html[] = '             <option value="folder" data-class="ui-icon-folder-collapsed" id="nvmb-folder" prefix="'.t(75, 'Path').'">'.t(75, 'Path').'</option>';
//        $html[] = '             <option value="youtube" data-class="fa fa-youtube fa-align-center" id="nvmb-youtube">Youtube</option>';
        $html[] = '         </select>';

        $html[] = '         <select id="media_browser_order" name="media_browser_order">';
        $html[] = '             <option value="date_added_DESC" selected="selected" data-class="silk-sprite silk-time_go" id="nvmb-date_added_DESC">'.t(86, 'Date').'</option>';
        $html[] = '             <option value="date_added_ASC" selected="selected" data-class="silk-sprite silk-time_go_inv" id="nvmb-date_added_ASC">'.t(86, 'Date').'</option>';
        $html[] = '             <option value="name_ASC" data-class="silk-sprite silk-sort_ascending" id="nvmb-name_ASC">'.t(159, 'Name').'</option>';
        $html[] = '             <option value="name_DESC" data-class="silk-sprite silk-sort_descending" id="nvmb-name_DESC">'.t(159, 'Name').'</option>';
        $html[] = '         </select>';

		// search box
		$html[] = '		    <div id="media_browser_search"><input type="text" value="" placeholder="'.t(41, 'Search').'…" name="media_browser_search" id="media_browser_search" style="width: 100px;"><img src="img/icons/silk/zoom.png" align="right" sprite="false" class="ui-corner-tr ui-corner-br" /></div>';
		$html[] = 		'</div>';
		
		$html[] = '		<div id="navigate_media_browser_items"></div>';
		
		$html[] = '		<input type="hidden" id="navigate_media_browser_folder_id" value="0" />';		
		
		$html[] = '	</form>';
		$html[] = '</div>';
	
		$this->add_content(implode("", $html));

		$this->add_script('
			$.getScript("js/navigate_media_browser.js?r='.$current_version->revision.'");
			$("#navigate_media_browser_upload_button").on("click", navigate_media_browser_files_uploader);
		');

		// extensions: add new contextmenu functions
		$extra_contextmenu_actions = array();
		$events->trigger(
			"media_browser",
			"contextmenu",
			array(
				'actions' => &$extra_contextmenu_actions
			)
		);
		if(!empty($extra_contextmenu_actions))
			array_unshift($extra_contextmenu_actions, '<hr />');

        $this->add_content('
            <ul id="contextmenu-files" style="display: none" class="ui-corner-all">
                <li id="contextmenu-files-download_link"><a href="#"><span class="ui-icon ui-icon-clipboard"></span>'.t(154, "Download link").'</a></li>
                <li id="contextmenu-files-permissions"><a href="#"><span class="ui-icon ui-icon-key"></span>'.t(17, "Permissions").'</a></li>
                <li id="contextmenu-files-duplicate"><a href="#"><span class="ui-icon ui-icon-copy"></span>'.t(477, "Duplicate").'</a></li>
                <li id="contextmenu-files-focalpoint"><a href="#"><span class="ui-icon ui-icon-image"></span>'.t(540, "Focal point").'</a></li>
                <li id="contextmenu-files-rename"><a href="#"><span class="ui-icon ui-icon-pencil"></span>'.t(500, 'Rename').'</a></li>
                <li id="contextmenu-files-description"><a href="#"><span class="ui-icon ui-icon-comment"></span>'.t(334, 'Description').'</a></li>
                '.($user->permission("files.delete")=="true"? '<li id="contextmenu-files-delete"><a href="#"><span class="ui-icon ui-icon-trash"></span>'.t(35, 'Delete').'</a></li>' : '').'
                '.implode("\n", $extra_contextmenu_actions).'                
            </ul>
            <ul id="contextmenu-mediabrowser-folders" style="display: none;">
	            <li id="contextmenu-mediabrowser-folders-open" action="open"><a href="#"><span class="ui-icon ui-icon-arrowreturnthick-1-e"></span>'.t(499, "Open").'</a></li>
	            <li id="contextmenu-mediabrowser-folders-rename" action="rename"><a href="#"><span class="ui-icon ui-icon-pencil"></span>'.t(500, "Rename").'</a></li>
	            '.($user->permission("files.delete")=="true"? '<li id="contextmenu-mediabrowser-folders-delete" action="delete"><a href="#"><span class="ui-icon ui-icon-trash"></span>'.t(35, 'Delete').'</a></li>' : '').'
            </ul>
            <ul id="contextmenu-mediabrowser"  style="display: none" class="ui-corner-all">
                <li id="contextmenu-mediabrowser-create_folder"><a href="#"><span class="ui-icon ui-icon-folder-collapsed"></span>'.t(561, "Create a new folder").'</a></li>
            </ul>
            <div id="navigate-edit-folder" style=" display: none; ">
                <form action="#" onsubmit="return false;">
                    <input type="submit" value="" style=" display: none; " />
                    <div class="navigate-form-row">
                        <label>'.t(159, 'Name').'</label>
                        '.$naviforms->textfield('folder-name', '').'
                    </div>
                    <div class="navigate-form-row">
                        <label>'.t(160, 'Type').'</label>
                        '.$naviforms->selectfield(
                            'folder-mime',
                            array(
                                0 => 'folder/generic',
                                1 => 'folder/images',
                                2 => 'folder/audio',
                                3 => 'folder/video',
                                4 => 'folder/flash',
                                5 => 'folder/documents'
                            ),
                            array(
                                0 => t(161, 'Generic'),
                                1 => t(29, 'Images'),
                                2 => t(31, 'Audio'),
                                3 => t(30, 'Video'),
                                4 => t(186, 'Adobe Flash'),
                                5 => t(32, 'Documents')
                            ),
                            'folder/generic'
                        ).'
                    </div>
                </form>
            </div>
			<div id="navigate-edit-file" style=" display: none; ">
	            <form action="#" onsubmit="return false;">
	                <input type="submit" value="" style=" display: none; " />
	                <div class="navigate-form-row">
	                    <label>'.t(159, 'Name').'</label>
	                    '.$naviforms->textfield('file-name', '').'
	                </div>
	            </form>
			</div>
        ');

        // permissions dialog
        $permissions_dialog = array();
        $permissions_dialog[] = '<div class="navigate-form-row">';
        $permissions_dialog[] = '<label>'.t(364, 'Access').'</label>';
        $permissions_dialog[] = $naviforms->selectfield(
            'contextmenu-permissions-access',
            array(
                0 => 0,
                1 => 2,
                2 => 1,
                3 => 3
            ),
            array(
                0 => t(254, 'Everybody'),
                1 => t(362, 'Not signed in'),
                2 => t(361, 'Web users only'),
                3 => t(512, 'Selected web user groups')
            ),
            0,
            'navigate_permissions_dialog_webuser_groups_visibility($(this).val());',
            false,
            array(
                1 => t(363, 'Users who have not yet signed in')
            )
        );
        $permissions_dialog[] = '</div>';

        $webuser_groups = webuser_group::all_in_array();

        $permissions_dialog[] = '<div class="navigate-form-row" id="permissions-dialog-webuser-groups-field">';
        $permissions_dialog[] = '<label>'.t(506, "Groups").'</label>';
        $permissions_dialog[] = $naviforms->multiselect(
            'contextmenu-permissions-groups',
            array_keys($webuser_groups),
            array_values($webuser_groups),
            0
        );
        $permissions_dialog[] = '<div style="clear: both; padding-bottom: 16px;"></div>';
        $permissions_dialog[] = '</div>';

        $this->add_script('
            function navigate_permissions_dialog_webuser_groups_visibility(access_value)
            {
                if(access_value==3)
                {
                    $("#permissions-dialog-webuser-groups-field").show();
                    if($("#contextmenu-permissions-dialog").is(":visible"))
                    {
                        $("#contextmenu-permissions-dialog").dialog("option", "width", "970");
                        $("#contextmenu-permissions-dialog").dialog("option", "height", "424");
                    }
                }
                else
                {
                    $("#permissions-dialog-webuser-groups-field").hide();
                    if($("#contextmenu-permissions-dialog").is(":visible"))
                    {
                        $("#contextmenu-permissions-dialog").dialog("option", "width", "620");
                        $("#contextmenu-permissions-dialog").dialog("option", "height", "200");
                    }
                }
            }

            navigate_permissions_dialog_webuser_groups_visibility(0);
        ');

        $permissions_dialog[] = '<div class="navigate-form-row">';
        $permissions_dialog[] = '<label>'.t(80, 'Permission').'</label>';
        $permissions_dialog[] = $naviforms->selectfield(
            'contextmenu-permissions-permission',
            array(
                0 => 0,
                1 => 1,
                2 => 2
            ),
            array(
                0 => t(69, 'Published'),
                1 => t(70, 'Private'),
                2 => t(81, 'Hidden')
            ),
            0
        );
        $permissions_dialog[] = '</div>';

        $permissions_dialog[] = '<div class="navigate-form-row">';
        $permissions_dialog[] = '<label>'.t(65, 'Enabled').'</label>';
        $permissions_dialog[] = $naviforms->checkbox('contextmenu-permissions-enabled', false);
        $permissions_dialog[] = '</div>';

        $this->add_content('
            <div id="contextmenu-permissions-dialog" style="display: none;">
                '.implode("\n", $permissions_dialog).'
            </div>
        ');


        // title/description(alt) dialog
        $description_dialog = array();

        $website_languages_selector = $website->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $description_dialog[] = '<div class="navigate-form-row">';
        $description_dialog[] = '<label>'.t(63, 'Languages').'</label>';
        $description_dialog[] = $naviforms->buttonset(
                'files_texts_language_selector',
                $website_languages_selector,
                '',
                "navigate_tabform_language_selector(this);"
            );
        $description_dialog[] = '</div>';

        foreach($website->languages_list as $lang)
        {
            $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

            $description_dialog[] = '<div class="navigate-form-row" lang="'.$lang.'">';
            $description_dialog[] = '<label>'.t(67, 'Title').' '.$language_info.'</label>';
            $description_dialog[] = $naviforms->textfield('contextmenu-description-dialog-title-'.$lang, "");
            $description_dialog[] = '</div>';

            $description_dialog[] = '<div class="navigate-form-row" lang="'.$lang.'">';
            $description_dialog[] = '<label>'.t(334, 'Description').' '.$language_info.'</label>';
            $description_dialog[] = $naviforms->textfield('contextmenu-description-dialog-description-'.$lang, "");
            $description_dialog[] = '</div>';
        }

        $this->add_content('
            <div id="contextmenu-description-dialog" style="display: none;">
                '.implode("\n", $description_dialog).'
            </div>
        ');


        // plupload
        $this->add_content('<div id="navigate-media-browser-files-uploader"></div>');

        $this->add_script('
            plupload.addI18n(
            {
                "Select files" : "'.t(142, 'Select files').'",
                "Add files to the upload queue and click the start button." : "'.t(143, 'Add files to the upload queue and click the start button.').'",
                "Filename" : "'.t(144, 'Filename').'",
                "Status" : "'.t(68, 'Status').'",
                "Size" : "'.t(145, 'Size').'",
                "Add files" : "'.t(146, 'Select files').'",
                "Start upload":"'.t(147, 'Start upload').'",
                "Stop current upload" : "'.t(148, 'Stop current upload').'",
                "Start uploading queue" : "'.t(149, 'Start uploading queue').'",
                "Drag files here." : "'.t(150, 'Drag files here.').'",
                "Uploaded %d/%d files": "'.t(338, 'Uploaded %d/%d files').'",
                "N/A": "'.t(339, 'N/A').'",
                "File extension error.": "'.t(340, 'File extension error').'",
                "File size error.": "'.t(341, 'File size error').'",
                "Init error.": "'.t(342, 'Init error').'",
                "HTTP Error.": "'.t(343, 'HTTP Error').'",
                "Security error.": "'.t(344, 'Security error').'",
                "Generic error.": "'.t(345, 'Generic error').'",
                "IO error.": "'.t(346, 'IO error').'",
                "Stop Upload": "'.t(347, 'Stop upload').'",
                "Add Files": "'.t(348, 'Add files').'",
                "Start Upload": "'.t(349, 'Start upload').'",
                "%d files queued": "'.t(350, '%d files queued').'"
            });
        ');

        $this->add_script('
            function navigate_media_browser_files_uploader()
            {
                var plupload_instance = $("#navigate-media-browser-files-uploader").plupload(
                {
                    // General settings
                    runtimes : "html5,flash,silverlight",
                    url : "'.NAVIGATE_URL.'/navigate_upload.php?session_id='.session_id().'",
                    max_file_size : "'.NAVIGATE_UPLOAD_MAX_SIZE.'mb",
                    chunk_size : "384kb",
                    unique_names: false,
                    sortable: false,
                    rename: true,
                    preinit: attachCallbacks,
                    flash_swf_url: "'.NAVIGATE_URL.'/lib/external/plupload/js/Moxie.swf",
                    silverlight_xap_url: "'.NAVIGATE_URL.'/lib/external/plupload/js/Moxie.xap"
                });

                function attachCallbacks(Uploader)
                {
                    Uploader.bind("FileUploaded", function(Up, File, Response)
                    {
                        var media = $("select[name=media_browser_type]").val();
                        var parent = 0;
                        if(media=="folder")
                            parent = navigate_media_browser_parent;

                        $.ajax(
                        {
                            async: true,
                            url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=files&act=json&op=upload",
                            success: function(data)
                            {

                            },
                            type: "post",
                            dataType: "json",
                            data: {
                                tmp_name: "{{BASE64}}",
                                name: File.name,
                                parent: parent
                            }
                        });
                    });
                }

                $("#navigate-media-browser-files-uploader").dialog(
                {
                    title: "'.t(142, 'Select files').'",
                    height: 400,
                    width: 600,
                    modal: true,
                    close: function()
                    {
                       navigate_media_browser_reload();
                    }
                });

                $(".plupload_wrapper").removeClass("plupload_scroll");

                $("#navigate-media-browser-files-uploader").on("mouseenter", function()
                {
                    $("div.plupload input").css("z-index","99999");
                });
			}
        ');
	}

	public function navigate_editorfield_link_dialog()
	{
		global $website;

		if(in_array('link_dialog', $this->parts_added))
			return;
		array_push($this->parts_added, 'link_dialog');

		$naviforms = new naviforms();

		$html = array();
		$html[] = '<div class="navigate-form-row">';
		$html[] = '<label>'.t(191, 'Source').'</label>';
		$html[] = $naviforms->buttonset(
					'nv_link_dialog_source',
					array(
						'structure'	  => t(16, 'Structure'),
						'element'	  => t(180, 'Item')
					),
					'structure',
					'nv_link_dialog_source_change()'
				);
		$html[] = '</div>';

		$hierarchy = structure::hierarchy(0);
		$categories_list = structure::hierarchyList($hierarchy);


		$html[] = '<div class="navigate-form-row hidden">';
		$html[] = '<label>'.t(78, 'Category').'</label>';
		$html[] = '<div class="category_tree" id="nv_link_dialog_category">
						<img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
                        '<div class="tree_ul">'.$categories_list.'</div>
					</div>';
		$html[] = '</div>';

		$html[] = '<div class="navigate-form-row hidden">';
		$html[] = '<label>'.t(630, 'Element').'</label>';
		$html[] = $naviforms->selectfield("nv_link_dialog_element", null, null, null, null, false, null, null, false);
		$html[] = '</div>';

		$html[] = '<div class="navigate-form-row hidden">';
		$html[] = '<label>'.t(75, 'Path').'</label>';
		$html[] = '<div id="nv_link_dialog_real_path">'.$website->absolute_path(true).'<span>?</span></div>';
		$html[] = '</div>';

		$html[] = '<div class="navigate-form-row hidden">';
		$html[] = '<label>'.t(631, "Dynamic URL").'</label>';
		$html[] = '<div id="nv_link_dialog_dynamic_path"><strong>?</strong></div>';
		$html[] = '</div>';

		$html[] = '<div class="navigate-form-row hidden">';
		$html[] = '<label>'.t(632, 'Replace text').'</label>';
		$html[] = $naviforms->checkbox("nv_link_dialog_replace_text", true);
		$html[] = '<input type="hidden" id="nv_link_dialog_title" value="" />';
		$html[] = '</div>';
		

		$this->add_content('
			<div id="nv_link_dialog" title="'.t(631, "Dynamic URL").'" class="hidden">
				'.implode("\n", $html).'
			</div>
		');

		$this->add_script('		
			function nv_link_dialog_source_change()
			{
				setTimeout(function()
				{			
					var source = $(\'#nv_link_dialog_source_structure:checked,#nv_link_dialog_source_element:checked\').val();
													
					$("#nv_link_dialog_real_path").parent().addClass("hidden");
					$("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
					$("#nv_link_dialog_replace_text").parent().addClass("hidden");
					
					$("#nv_link_dialog_element").parent().addClass("hidden");
					$("#nv_link_dialog_category").parent().addClass("hidden");
					
					if(source == "element")
					{
						$("#nv_link_dialog_element").parent().removeClass("hidden");
					}
					else if(source == "structure")
					{
						$("#nv_link_dialog_category").parent().removeClass("hidden");
					}
				}, 100);
			}
		
		    $("#nv_link_dialog_category .tree_ul").jstree(
		    {
                plugins: ["changed", "types"],
                "types" : 
                {
                    "default":  {   "icon": "img/icons/silk/folder.png"         },
                    "leaf":     {   "icon": "img/icons/silk/page_white.png"     }
                },
                "core" : 
                {
                    "multiple" : false
                }
            }).on("changed.jstree", function(e, data) 
            {                               
                if(data.selected.length > 0)
                {				
                    var node = data.instance.get_node(data.selected[0]);
                    var id = node.data.nodeId;
                    var text = $(node.text).text();
                                        
                    $("#nv_link_dialog_dynamic_path strong").html("nv://structure/" + id);
                            
                    $("#nv_link_dialog_dynamic_path").parent().removeClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().removeClass("hidden");
                    $("#nv_link_dialog_title").val(text);
                }
                else
                {
                    $("#nv_link_dialog_real_path").parent().addClass("hidden");
                    $("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().addClass("hidden");
                    $("#nv_link_dialog_title").val("");
                }		
            });				   

			$("#nv_link_dialog_element").select2(
                {
                    placeholder: "'.t(533, "Find element by title").'",
                    minimumInputLength: 1,
                    ajax: {
                        url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=json_find_item",
                        dataType: "json",
                        delay: 100,
                        data: function(params)
                        {
	                        return {
				                title: params.term,
				                embedding: 0,
				                nd: new Date().getTime(),
				                page_limit: 30, // page size
				                page: params.page // page number
				            };
                        },
                        processResults: function (data, params)
				        {
				            params.page = params.page || 1;
				            return {
								results: data.items,
								pagination: { more: (params.page * 30) < data.total_count }
							};
				        }
                    },
                    templateSelection: function(row)
					{
						if(row.id)
						{
							$("#nv_link_dialog_real_path span").html(row.path);
							$("#nv_link_dialog_dynamic_path strong").html("nv://element/" + row.id);
							
							$("#nv_link_dialog_real_path").parent().removeClass("hidden");
							$("#nv_link_dialog_dynamic_path").parent().removeClass("hidden");
							$("#nv_link_dialog_replace_text").parent().removeClass("hidden");
							$("#nv_link_dialog_title").val(row.text);
							
							return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
						}
						else
						{
							$("#nv_link_dialog_real_path").parent().addClass("hidden");
							$("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
							$("#nv_link_dialog_replace_text").parent().addClass("hidden");
							$("#nv_link_dialog_title").val("");
							return row.text;
						}
					},
					escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
                    triggerChange: true,
                    allowClear: true
                });
                
			nv_link_dialog_source_change(); // auto-select structure on load			
		');
	}
	
	public function silk_sprite($html)
	{
		// parse generated html and subsitute all static silk icons for a sprite
		$tags = nvweb_tags_extract($html, 'img', NULL, true, 'UTF-8');
		
		foreach($tags as $tag)
		{
			if(strpos($tag['attributes']['src'], '/icons/silk/')!==false)
			{
				if(@$tag['attributes']['sprite']=='false') continue;
				$base = basename($tag['attributes']['src'], '.png');
				
				$tag['new'] = '<img class="silk-sprite silk-'.$base.'" ';
				foreach($tag['attributes'] as $name => $value)
				{
					if($name=='src') $tag['new'].= 'src="'.NAVIGATE_URL.'/img/transparent.gif" ';
					else			 $tag['new'].= $name.'="'.$value.'" ';
				}
				$tag['new'] .= '/>';
				
				$html = str_replace($tag['full_tag'], $tag['new'], $html);
			}
		}
		return $html;
			
	}
	
	public function generate()
	{
		$layout[] = $this->doctype();
		$layout[] = $this->head();
		$layout[] = $this->body();
		$layout[] = $this->javascript();
		$layout[] = $this->close_tag();
		
		$html = implode("\n", $layout);
		
		$html = $this->silk_sprite($html);
		
		return $html;
    }
}

?>