<?php
/**
 * QSF Portal
 * Copyright (c) 2006-2019 The QSF Portal Development Team
 * https://github.com/Arthmoor/QSF-Portal
 *
 * Based on:
 *
 * Quicksilver Forums
 * Copyright (c) 2005-2011 The Quicksilver Forums Development Team
 * https://github.com/Arthmoor/Quicksilver-Forums
 *
 * MercuryBoard
 * Copyright (c) 2001-2006 The Mercury Development Team
 * https://github.com/markelliot/MercuryBoard
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 **/

if( version_compare( PHP_VERSION, "7.0.0", "<" ) ) {
	die( 'PHP version does not meet minimum requirements. Contact your system administrator.' );
}

define( 'QUICKSILVERFORUMS', true );

date_default_timezone_set( 'UTC' );

$time_now   = explode( ' ', microtime() );
$time_start = $time_now[1] + $time_now[0];

srand( (double)microtime() * 1234567 );

$_REQUEST = array();

require './settings.php';
$set['include_path'] = '.';
require_once $set['include_path'] . '/lib/' . $set['dbtype'] . '.php';
$database = 'db_' . $set['dbtype'];
require_once $set['include_path'] . '/lib/globalfunctions.php';
require_once $set['include_path'] . '/lib/perms.php';
require_once $set['include_path'] . '/lib/file_perms.php';
require_once $set['include_path'] . '/lib/user.php';
require_once $set['include_path'] . '/lib/mailer.php';
require_once $set['include_path'] . '/lib/attachutil.php';
require_once $set['include_path'] . '/lib/htmlwidgets.php';
require_once $set['include_path'] . '/lib/bbcode.php';
require_once $set['include_path'] . '/lib/tool.php';
require_once $set['include_path'] . '/lib/readmarker.php';
require_once $set['include_path'] . '/lib/activeutil.php';
require_once $set['include_path'] . '/lib/modlet.php';
require_once $set['include_path'] . '/lib/zTemplate.php';

if( !$set['installed'] ) {
	header( 'Location: ./install/index.php' );
}

set_error_handler( 'error' );

error_reporting( E_ALL );

// Open connection to database
$db = new $database( $set['db_host'], $set['db_user'], $set['db_pass'], $set['db_name'], $set['db_port'], $set['db_socket'], $set['prefix'] );
if( !$db->connection ) {
	error( QUICKSILVER_ERROR, 'A connection to the database could not be established and/or the specified database could not be found.', __FILE__, __LINE__ );
}

/*
 * Logic here:
 * If 'a' is not set, but some other query is, it's a bogus request for this software.
 * If 'a' is set, but the module doesn't exist, it's either a malformed URL or a bogus request.
 * Otherwise $missing remains false and no error is generated later.
 */
$module = null;
$qstring = null;
$missing = false;
$terms_module = '';

if( !isset( $_GET['a'] ) ) {
	$module = 'main';

	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}
} elseif( !file_exists( 'func/' . $_GET['a'] . '.php' ) ) {
	$module = 'main';

	if( $_GET['a'] != 'forum_rules' && $_GET['a'] != 'upload_rules' ) {
		$qstring = $_SERVER['REQUEST_URI'];

		$missing = true;
	} else {
		$terms_module = $_GET['a'];
	}
} else {
	$module = $_GET['a'];
}

if( strstr( $module, '/' ) || strstr( $module, '\\' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit( 'You have been banned from this site.' );
}

// I know this looks corny and all but it mimics the output from a real 404 page.
if( $missing ) {
	header( 'HTTP/1.0 404 Not Found' );

	echo( "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
	<html><head>
	<title>404 Not Found</title>
	</head><body>
	<h1>Not Found</h1>
	<p>The requested URL $qstring was not found on this server.</p>
	<hr>
	{$_SERVER['SERVER_SIGNATURE']}	</body></html>" );

	exit( );
}

require './func/' . $module . '.php';

$qsf = new $module( $db );
$qsf->pre = $set['prefix'];

$qsf->get['a'] = $module;
$qsf->sets     = $qsf->get_settings( $set );
$qsf->site     = $qsf->sets['loc_of_board']; // Will eventually replace $qsf->self once the SEO URL changes are done.

session_start();

$qsf->user_cl = new user( $qsf );
$qsf->user    = $qsf->user_cl->login();
$qsf->lang    = $qsf->get_lang( $qsf->user['user_language'], $qsf->get['a'] );

if( !isset( $qsf->get['skin'] ) ) {
	$skin = $qsf->db->fetch( 'SELECT skin_dir FROM %pskins WHERE skin_id=%d', $qsf->user['user_skin'] );

	$qsf->skin = $skin['skin_dir'];
} elseif( $qsf->perms->auth( 'is_admin' ) ) {
	// Allow admins to specify a skin manually for development purposes.
	$skin = intval( $qsf->get['skin'] );

	$dev_skin = $qsf->db->fetch( 'SELECT skin_dir FROM %pskins WHERE skin_id=%d', $skin );

	$qsf->skin = $dev_skin['skin_dir'];
}

$qsf->init();

// Security header options
if( $qsf->sets['htts_enabled'] && $qsf->sets['htts_max_age'] > -1 ) {
	header( "Strict-Transport-Security: max-age={$qsf->sets['htts_max_age']}" );
}

if( $qsf->sets['xss_enabled'] ) {
	if( $qsf->sets['xss_policy'] == 0 ) {
		header( 'X-XSS-Protection: 0' );
	}

	if( $qsf->sets['xss_policy'] == 1 ) {
		header( 'X-XSS-Protection: 1' );
	}

	if( $qsf->sets['xss_policy'] == 2 ) {
		header( 'X-XSS-Protection: 1; mode=block' );
	}
}

if( $qsf->sets['xfo_enabled'] ) {
	if( $qsf->sets['xfo_policy'] == 0 ) {
		header( 'X-Frame-Options: deny' );
	}

	if( $qsf->sets['xfo_policy'] == 1 ) {
		header( 'X-Frame-Options: sameorigin' );
	}

	if( $qsf->sets['xfo_policy'] == 2 ) {
		header( "X-Frame-Options: allow-from {$qsf->sets['xfo_allowed_origin']}" );
	}
}

if( $qsf->sets['xcto_enabled'] ) {
	header( 'X-Content-Type-Options: nosniff' );
}

if( $qsf->sets['ect_enabled'] ) {
	header( "Expect-CT: max-age={$qsf->sets['ect_max_age']}" );
}

if( $qsf->sets['csp_enabled'] ) {
	header( "Content-Security-Policy: {$qsf->sets['csp_details']}" );
}

if( $qsf->is_banned() ) {
	error( QUICKSILVER_NOTICE, $qsf->lang->main_banned );
}

$qsf->tree( $qsf->sets['forum_name'], "{$qsf->site}/board/" );

$qsf->add_feed( $qsf->site . '/index.php?a=rssfeed' );

if( ( $qsf->get['a'] == 'forum' ) && isset( $qsf->get['f'] ) ) {
	$searchlink = '&amp;f=' . intval( $qsf->get['f'] );
} else {
	$searchlink = null;
}

$spam_style = null;
if( $qsf->sets['spam_pending'] > 0 )
	$spam_style = ' class="attention"';
$can_spam = false;
if( $qsf->perms->auth( 'is_admin' ) || $qsf->user['user_group'] == USER_MODS )
	$can_spam = true;

$new_pm = null;
if( $qsf->get_messages() > 0 )
	$new_pm = ' class="attention"';

$new_files = null;
if( $qsf->get_files() > 0 )
	$new_files = ' class="attention"';

$title = isset( $qsf->title ) ? $qsf->title : $qsf->sets['forum_name'];

$time_now  = explode( ' ', microtime() );
$qsf->time_exec = round( $time_now[1] + $time_now[0] - $time_start, 4 );

$xtpl = new XTemplate( './skins/' . $qsf->skin . '/index.xtpl' );
$qsf->xtpl = $xtpl;

if( $terms_module == 'forum_rules' ) {
	$tos = $qsf->db->fetch( 'SELECT settings_tos FROM %psettings' );

	$message = $qsf->format( $tos['settings_tos'], FORMAT_HTMLCHARS | FORMAT_BREAKS | FORMAT_BBCODE );
	$output = $qsf->message( $qsf->lang->main_tos_forums, $message );
} elseif ( $terms_module == 'upload_rules' ) {
	$tos = $qsf->db->fetch( 'SELECT settings_tos_files FROM %psettings' );

	$message = $qsf->format( $tos['settings_tos_files'], FORMAT_HTMLCHARS | FORMAT_BREAKS | FORMAT_BBCODE );
	$output = $qsf->message( $qsf->lang->main_tos_files, $message );
} else {
	$output = $qsf->execute();
}

if( $qsf->nohtml ) {
	ob_start( 'ob_gzhandler' );

	echo $output;

	@ob_end_flush();
	@flush();
} else {
	// Page headers and container wrapper.
	$xtpl->assign( 'user_language', $qsf->user['user_language'] );
	$xtpl->assign( 'charset', $qsf->lang->charset );
	$xtpl->assign( 'meta_keywords', $qsf->sets['meta_keywords'] );
	$xtpl->assign( 'meta_desc', $qsf->sets['meta_description'] );
	$xtpl->assign( 'mobile_icons', $qsf->sets['mobile_icons'] );
	$xtpl->assign( 'title', $title );
	$xtpl->assign( 'loc_of_board', $qsf->sets['loc_of_board'] );
	$xtpl->assign( 'skin', $qsf->skin );
	$xtpl->assign( 'feed_links', $qsf->feed_links );

	$left_links = null;
	foreach( $qsf->sets['left_sidebar_links'] as $link ) {
		$link = trim( $link );

		$left_links .= $link;
	}

	$xtpl->assign( 'index_left_links', $left_links );

	$right_links = null;
	foreach( $qsf->sets['right_sidebar_links'] as $link ) {
		$link = trim( $link );

		$right_links .= $link;
	}

	$xtpl->assign( 'index_right_links', $right_links );

	// Blocks on left side of front page.
	if( $qsf->perms->is_guest ) {
		$qsf->lang->login(); // For login words
		$qsf->lang->register(); // For registration word

		$xtpl->assign( 'qsf_site', $qsf->site );
		$xtpl->assign( 'forum_name', $qsf->sets['forum_name'] );
		$xtpl->assign( 'main_files', $qsf->lang->main_files );
		$xtpl->assign( 'main_forum', $qsf->lang->main_forum );
		$xtpl->assign( 'main_members', $qsf->lang->main_members );
		$xtpl->assign( 'searchlink', $searchlink );
		$xtpl->assign( 'main_search', $qsf->lang->main_search );

		$xtpl->assign( 'login', $qsf->lang->login );

		$request_uri = $qsf->get_uri();
		if( substr( $request_uri, -8 ) == 'register') {
			$request_uri = $qsf->self;
		}
		$xtpl->assign( 'request_uri', $request_uri );

		$xtpl->assign( 'login_user', $qsf->lang->login_user );
		$xtpl->assign( 'login_pass', $qsf->lang->login_pass );
		$xtpl->assign( 'submit', $qsf->lang->submit );
		$xtpl->assign( 'register_reg', $qsf->lang->register_reg );
		$xtpl->assign( 'login_forgot_pass', $qsf->lang->login_forgot_pass );

		$xtpl->parse( 'Index.GuestHeader' );
	} else {
		$xtpl->assign( 'qsf_site', $qsf->site );
		$xtpl->assign( 'forum_name', $qsf->sets['forum_name'] );
		$xtpl->assign( 'main_welcome', $qsf->lang->main_welcome );
		$xtpl->assign( 'user_id', $qsf->user['user_id'] );
		$xtpl->assign( 'user_name', $qsf->user['user_name'] );
		$xtpl->assign( 'link_name', $qsf->htmlwidgets->clean_url( $qsf->user['user_name'] ) );
		$xtpl->assign( 'new_files', $new_files );
		$xtpl->assign( 'main_files', $qsf->lang->main_files );
		$xtpl->assign( 'main_forum', $qsf->lang->main_forum );

		if( $qsf->perms->auth( 'page_create' ) || $qsf->perms->auth( 'page_edit' ) || $qsf->perms->auth( 'page_delete' ) ) {
			$xtpl->assign( 'main_pages', $qsf->lang->main_pages );

			$xtpl->parse( 'Index.MemberHeader.PagesLink' );
		}

		$xtpl->assign( 'main_members', $qsf->lang->main_members );
		$xtpl->assign( 'searchlink', $searchlink );
		$xtpl->assign( 'main_search', $qsf->lang->main_search );

		if( $can_spam ) {
			$xtpl->assign( 'spam_style', $spam_style );
			$xtpl->assign( 'main_spam_controls', $qsf->lang->main_spam_controls );

			$xtpl->parse( 'Index.MemberHeader.SpamLink' );
		}

		$xtpl->assign( 'new_pm', $new_pm );
		$xtpl->assign( 'main_messenger', $qsf->lang->main_messenger );
		$xtpl->assign( 'main_cp', $qsf->lang->main_cp );

		if( $qsf->perms->auth( 'is_admin' ) ) {
			$xtpl->assign( 'main_admincp', $qsf->lang->main_admincp );

			$xtpl->parse( 'Index.MemberHeader.AdminCPLink' );
		}

		$xtpl->assign( 'main_logout', $qsf->lang->main_logout );

		$xtpl->parse( 'Index.MemberHeader' );
	}

	// FIXME: All this 0 == 0 stuff should be settable as front page features.
	// Recent Posts
	$recent_posts = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/recent_posts.php';

		$modlet = new recent_posts( $qsf );

		$recent_posts = $modlet->execute( false );

		$xtpl->assign( 'recent_posts', $recent_posts );
	}

	// Recent Uploads
	$recent_uploads = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/recent_uploads.php';

		$modlet = new recent_uploads( $qsf );

		$recent_uploads = $modlet->execute( false );

		$xtpl->assign( 'recent_uploads', $recent_uploads );
	}

	// Top Posters
	$top_posters = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/top_posters.php';

		$modlet = new top_posters( $qsf );

		$top_posters = $modlet->execute( false );

		$xtpl->assign( 'top_posters', $top_posters );
	}

	// Top Uploaders
	$top_uploaders = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/top_uploaders.php';

		$modlet = new top_uploaders( $qsf );

		$top_uploaders = $modlet->execute( false );

		$xtpl->assign( 'top_uploaders', $top_uploaders );
	}

	// Users Online
	$users_online = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/users_online.php';

		$modlet = new users_online( $qsf );

		$users_online = $modlet->execute( false );

		$xtpl->assign( 'users_online_block', $users_online );
	}

	// Board Stats
	$board_stats = null;
	if( 0 == 0 ) {
		require_once $set['include_path'] . '/modlets/board_stats.php';

		$modlet = new board_stats( $qsf );

		$board_stats = $modlet->execute( true ); // FIXME: Birthdays should be optional display.

		$xtpl->assign( 'board_stats', $board_stats );
	}

	// Main section on right side of front page.
	$reminder = null;
	$reminder_text = null;

	if( $qsf->sets['closed'] ) {
		if( !$qsf->perms->auth( 'board_view_closed' ) ) {
			if( $qsf->get['a'] != 'login' ) {
				error( QUICKSILVER_NOTICE, $qsf->sets['closedtext'] . "<br /><hr />If you are an administrator, <a href='{$qsf->site}/index.php?a=login&amp;s=on'>click here</a> to login." );
			}
		} else {
			$reminder_text = $qsf->lang->main_reminder_closed . '<br />&quot;' . $qsf->sets['closedtext'] . '&quot;';
		}
	}

	if( $qsf->user['user_group'] == USER_AWAIT ) {
		$reminder_text = "{$qsf->lang->main_activate}<br /><a href='{$qsf->site}/register/&amp;s=resend'>{$qsf->lang->main_activate_resend}</a>";
	}
	if( $reminder_text ) {
		$xtpl->assign( 'main_reminder', $qsf->lang->main_reminder );
		$xtpl->assign( 'reminder_text', $reminder_text );

		$xtpl->parse( 'Index.Reminder' );
	}

	// Generated by the specific module being accessed.
	$xtpl->assign( 'module_output', $output );

	// Footer section.
	$xtpl->assign( 'powered_by', $qsf->lang->powered );
	$xtpl->assign( 'qsf_name', $qsf->name );
	$xtpl->assign( 'based_on', $qsf->lang->based_on );
	$xtpl->assign( 'servertime', $qsf->mbdate( DATE_LONG, $qsf->time, false ) );

	$google = null;
	if( isset( $qsf->sets['analytics_code'] ) && !empty( $qsf->sets['analytics_code'] ) ) {
		$google = $qsf->sets['analytics_code'];
	}
	$xtpl->assign( 'google', $google );

	$xtpl->parse( 'Index' );

	ob_start( 'ob_gzhandler' );

	$xtpl->out( 'Index' );

	@ob_end_flush();
	@flush();
}

// Do post output stuff
$qsf->cleanup();

// Close the DB connection.
$qsf->db->close();
?>