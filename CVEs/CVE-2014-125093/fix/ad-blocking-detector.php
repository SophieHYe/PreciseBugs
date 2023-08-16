<?php
/**
 * Plugin Name: Ad Blocking Detector
 * Plugin URI: http://adblockingdetector.jtmorris.net
 * Description: A plugin to detect ad blocking browser extensions and display alternative content to site visitors.
 * Version: 1.2.2
 * Author: John Morris
 * Author URI: http://jtmorris.net
 * License: GPL2
 */

/*  Copyright 2013 - 2014  John Morris  (email : johntylermorris@jtmorris.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


define ( 'ABD_ROOT_PATH', plugin_dir_path( __FILE__ ) );
define ( 'ABD_ROOT_URL', plugin_dir_url( __FILE__ ) );
define ( 'ABD_PLUGIN_FILE', ABD_ROOT_PATH . 'ad-blocking-detector.php' );

include_once ( ABD_ROOT_PATH . 'includes/specify-admin-menus.php' );
include_once ( ABD_ROOT_PATH . 'includes/admin-page.php' );
include_once ( ABD_ROOT_PATH . 'includes/hooks.php' );
include_once ( ABD_ROOT_PATH . 'includes/enqueue.php' );
include_once ( ABD_ROOT_PATH . 'includes/ajax-actions.php' );
include_once ( ABD_ROOT_PATH . 'includes/shortcodes.php' );


//      Start SESSION to facilitate data transfers
//      Error Prevention: http://www.php.net/manual/en/function.session-start.php#82957
function my_session_start()
{
        if (isset($_COOKIE['PHPSESSID'])) {
                $sessid = $_COOKIE['PHPSESSID'];
        }
        else if (isset($_GET['PHPSESSID'])) {
                $sessid = $_GET['PHPSESSID'];
        }
        else {
                session_start();
                return false;
        }

        if (!preg_match('/^[a-z0-9]{32}$/', $sessid)) {
                return false;
        }
        session_start();

        return true;
}
my_session_start();
