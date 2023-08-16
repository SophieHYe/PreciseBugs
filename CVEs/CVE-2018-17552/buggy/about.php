<?php
function run()
{
	switch(@$_REQUEST['act'])
	{
		default:
			$out = about_layout();
	}
	
	return $out;
}

function about_layout()
{
	$navibars = new navibars();
	$current_version = update::latest_installed();
	
	$navibars->title(t(215, 'About'));
	
	$navibars->form();		
	$navibars->add_tab('Navigate CMS');
	
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(216, 'Created by').'</label>',
			'<a href="http://www.naviwebs.com" target="_blank">Naviwebs</a>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(220, 'Version').'</label>',
			'<span>'.$current_version->version.' r'.$current_version->revision.'</span>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(378, 'License').'</label>',
			'<a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL v2</a>'
		)
	);
											
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(219, 'Copyright').'</label>',
			'<a href="http://www.naviwebs.com" target="_blank">&copy; 2010 - '.date('Y').', Naviwebs.com</a>'
		)
	);


	$navibars->add_tab(t(218, 'Third party libraries'));	
	
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(218, 'Third party libraries').'</label>',
			'<a href="http://www.tinymce.com" target="_blank">TinyMCE 4.8.0c</a><br />'
		)
	);

	// note: the tinymce-codemirror plugin has Apache 2 License, but the author Arjan (from Webgear.nl) has given permission to use and include the code in this application
    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="https://github.com/christiaan/tinymce-codemirror" target="_blank">TinyMCE CodeMirror plugin v1.4+ (commit #1d31634)</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="https://github.com/Matmusia/magicline" target="_blank">TinyMCE magic line plugin v1.2.3_nv</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="https://github.com/josh18/TinyMCE-FontAwesome-Plugin" target="_blank">TinyMCE Font Awesome plugin v2.0.10_nv</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/maschek/imgmap" target="_blank">TinyMCE imgmap plugin v1.09</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
   											'<a href="http://www.assembla.com/spaces/lorem-ipsum" target="_blank">TinyMCE LoremIpsum plugin v0.13</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://www.jquery.com" target="_blank">jQuery v2.2.3 + jQuery Migrate v1.3</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://www.jqueryui.com" target="_blank">jQuery UI v1.11.2</a><br />' ));

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://fortawesome.github.io/Font-Awesome/" target="_blank">Font Awesome v4.7</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="https://github.com/free-jqgrid/jqGrid" target="_blank">free-jqGrid v4.13.3</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://stanlemon.net/pages/jgrowl" target="_blank">jGrowl v1.2.12</a><br />'
		)
	);

    $navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
            '<a href="https://select2.github.io" target="_blank">Select2 4.0.6-rc.1</a><br />'
		)
	);

    $navibars->add_tab_content_row(
    	array(
    	'<label>&nbsp;</label>',
        '<a href="https://tracy.nette.org/" target="_blank">Tracy – PHP debugger v2.4.6 (NV modified version)</a><br />'
		)
	);

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
            '<a href="http://mind2soft.com/labs/jquery/multiselect/" target="_blank">jQuery UIx Multiselect v2.0RC</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://www.jstree.com" target="_blank">jsTree v3.3.1</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="https://github.com/RobinHerbots/jquery.inputmask" target="_blank">jQuery Inputmask v4.0</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://www.plupload.com/" target="_blank">Plupload v2.3.1</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://player.bitgravity.com" target="_blank">Bitgravity free video player v6</a><br />'
		)
	);
											
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://mediaelementjs.com/" target="_blank">MediaElement.js v2.11.2</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/pisi/Longclick" target="_blank">jQuery Long Click v0.3.2 (22-Jun-2010)</a><br />' ));

	$navibars->add_tab_content_row(
	    array(
	        '<label>&nbsp;</label>',
			'<a href="https://github.com/broofa/node-uuid" target="_blank">node-uuid v1.4.7</a><br />'
        )
    );

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://plugins.jquery.com/project/query-object" target="_blank">jQuery.query v2.1.8 (22-Jun-2010)</a><br />'
		)
	);

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://iflyingangel.com/jautochecklist" target="_blank">jAutochecklist v1.3.1</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://pupunzi.open-lab.com/mb-jquery-components/jquery-mb-extruder/" target="_blank">jQuery mb.extruder v2.5</a><br />' ));
											
	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://www.flotcharts.org" target="_blank">Flot (Attractive Javascript plotting for jQuery) v0.8.3</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/ludo/jquery-treetable" target="_blank">jQuery treeTable plugin v2.3.0</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/isocra/TableDnD" target="_blank">jQuery Table DnD plugin v0.7+ (2015/03/23)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/mathiasbynens/jquery-noselect" target="_blank">jQuery noSelect plugin v51bac1d397 (2012-01-11)</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/ROMB/jquery-dialogextend" target="_blank">jQuery Dialog Extend plugin v2.0.4</a><br />' ));

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://codemirror.net" target="_blank">CodeMirror source code editor v5.32.0</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/PHPMailer/PHPMailer" target="_blank">PHP Mailer v5.2.22</a><br />'));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://qtip2.com" target="_blank">qTip2 v2.2.1</a><br />'));

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://idnaconv.net" target="_blank">IDNA Convert v1.1.0</a><br />'
		)
	);

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://leafletjs.com" target="_blank">Leaflet 1.0.3</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://aehlke.github.com/tag-it/" target="_blank">jQuery Tag It! v2.0</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://code.google.com/p/cssmin/" target="_blank">CssMin v3.0.1</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.verot.net/php_class_upload.htm" target="_blank">class.upload v0.33dev</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://www.framework2.com.ar/dzone/forceUTF8-es/" target="_blank">Encoding UTF8 Class (by Sebastián Grignoli)</a><br />' ));

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://www.dropzonejs.com" target="_blank">DropzoneJS v4.3.0</a><br />'
		)
	);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://trentrichardson.com/examples/timepicker/" target="_blank">jQuery Timepicker Addon v1.6.1</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/tzuryby/jquery.hotkeys" target="_blank">jQuery HotKeys v0.8+</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/DrPheltRight/jquery-caret" target="_blank">jQuery Caret v20803a7a16 (Sep 23 2011)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/tbasse/jquery-truncate" target="_blank">jQuery Truncate Text Plugin v18fdc9195c (Apr 03 2013)</a><br />' ));

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="http://jacob87.github.io/raty-fa" target="_blank">jQuery Raty FA v0.1.2</a><br />'
		)
	);

    $navibars->add_tab_content_row(
    	array(
    		'<label>&nbsp;</label>',
			'<a href="https://github.com/yatt/jquery.base64/" target="_blank">jQuery.base64 v2013.03.26</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://vanderlee.github.io/colorpicker/" target="_blank">jQuery.colorpicker v1.2.9</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://code.google.com/p/ezcookie/" target="_blank">jQuery ezCookie v0.7.01</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://verlok.github.io/lazyload" target="_blank">LazyLoad v5.1.1</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="https://github.com/digitalnature/php-ref" target="_blank">PHP REF v1.2dev</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://scripts.incutio.com/xmlrpc/" target="_blank">Incutio XML-RPC Library for PHP v1.7.4</a><br />'
		)
	);

	$navibars->add_tab(t(29, 'Images'));
	
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(29, 'Images').'</label>',
			'<a href="http://www.famfamfam.com/lab/icons/silk/" target="_blank">famfamfam Silk Icons 1.3 (Mark James)</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://damieng.com/creative/icons/silk-companion-1-icons" target="_blank">Silk Companion I (Damien Guard)</a><br />'
		)
	);
	
	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://www.cagintranet.com/archive/download-famfamfam-silk-companion-2-icon-pack/" target="_blank">Silk Companion II (Chris Cagle)</a><br />'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>&nbsp;</label>',
			'<a href="http://fontawesome.io" target="_blank">Font Awesome 4 by Dave Gandy - http://fontawesome.io</a><br />'
		)
	);

    $navibars->add_tab(t(526, 'Translations'));

    $navibars->add_tab_content_row(
	    array(
	    '<label>English</label>',
        '<a href="http://www.navigatecms.com">Navigate CMS</a>'
	    )
    );

    $navibars->add_tab_content_row(
	    array(
	    '<label>Català</label>',
        '<a href="mailto:info@naviwebs.com">Marc Lobato (naviwebs.com)</a><br />'
	    )
    );

    $navibars->add_tab_content_row(
	    array(
	    '<label>Español</label>',
        '<a href="mailto:info@naviwebs.com">Marc Lobato (naviwebs.com)</a><br />'
	    )
    );

    $navibars->add_tab_content_row(
	    array(
	    '<label>Deutsch</label>',
        '<a href="http://www.lingudora.com" target="_blank">Dominik Hlusiak (lingudora.com)</a><br />'
	    )
    );

    $navibars->add_tab_content_row(
	    array(
	    '<label>Polish</label>',
        '<a href="https://github.com/stpk007" target="_blank">Stanisław Krukowski (https://github.com/stpk007)</a><br />'
	    )
    );
    $navibars->add_tab_content_row(
	    array(
	    '<label>Norwegian Bokmål</label>',
        '<a href="https://hosted.weblate.org/user/kingu/" target="_blank">Allan Nordhøy</a><br />'
	    )
    );

    return $navibars->generate();
}

?>
