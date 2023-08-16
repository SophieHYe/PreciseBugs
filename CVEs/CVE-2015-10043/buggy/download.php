<?php // 5.3.3

/*
 * download.php - downloads an uploaded file in a user's subdirectory
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

$path = submission_path($num, $type, $_SESSION['username'], $_GET['file']);

if (!file_exists($path))
    trigger_error('the specified file does not exist: ' . $_GET['file']);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($path));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

readfile($path);
