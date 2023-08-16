<?php // 5.3.3

/*
 * delete.php - deletes an uploaded file from a user's subdirectory
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/socrates.php';

// redirects to log in page if necessary
require 'auth.php';

if (!isset($_GET['type']) || !isset($_GET['num']) || !isset($_GET['file']))
    trigger_error('invalid or not enough parameters');

check_assignment($_GET['num'], $_GET['type']);

$num = $_GET['num'];
$type = $_GET['type'];

delete_file($num, $type, $_SESSION['username'], $_GET['file']);

$vars = array();

$vars['assignment'] = htmlspecialchars(assignment_name($num, $type));
$vars['filename'] = $_GET['file'];
$vars['url'] = "upload.php?type=$type&num=$num";

set_title('Deleted');
use_body_template('delete');
render_page($vars);
