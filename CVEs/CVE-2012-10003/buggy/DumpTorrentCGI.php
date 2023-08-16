<?php
require ("config.php");
require ("funcsv2.php");
//Check session
session_start();

if (!$_SESSION['admin_logged_in'])
{
	//check fails
	header("Location: authenticate.php?status=session");
	exit();
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html><head><title>Torrent Information</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" href="./css/style.css" type="text/css" />
</head><body>
<?php
require_once("torrent_functions.php");
?>
<table width="50%" border=0><tr><td>
This script parses a torrent file and displays detailed information about it.
</td></tr>
</table><br>
<form enctype="multipart/form-data" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
Torrent file: <input type="file" name="torrent" size="40"><br>
<br>
OR
<br><br>
Torrent URL: <input type=text name="url" size="50"><br><br>
Output type: <select name="output">
<option value="-1">Auto-detect
<option value="0">Classic (raw)
<option value="1">.torrent file
<option value="2">/scrape
<option value="3">/announce
</select><br><br>
<input type="submit" value="Decode">
</form>

<a href="admin.php"><img src="images/admin.png" border="0" class="icon" alt="Admin Page" title="Admin Page" /></a><a href="admin.php">Return to Admin Page</a>
</body></html>
