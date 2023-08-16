<?php // 5.3.3

/*
 * view_file.php - displays the contents of a submitted file
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
$assignment_name = htmlspecialchars(assignment_name($num, $type));

// protects against attacks where '..' could be used in file path
if (basename($_GET['file']) !== $_GET['file'])
    trigger_error('invalid file path');

$path = submission_path($num, $type, $_SESSION['username'], $_GET['file']);

if (!file_exists($path))
    trigger_error('the specified file does not exist: ' . $_GET['file']);

$vars = array();

$vars['filename'] = $_GET['file'];
$vars['file'] = htmlspecialchars(file_get_contents($path));

$vars['url'] = "upload.php?type=$type&num=$num";
$vars['assignment'] = $assignment_name;

$ct = get_change_time($num, $type, $_SESSION['username'], $_GET['file']);
$vars['ctime'] = $ct;

set_title('Viewing ' . $_GET['file']);
use_body_template('view_file');
render_page($vars);
