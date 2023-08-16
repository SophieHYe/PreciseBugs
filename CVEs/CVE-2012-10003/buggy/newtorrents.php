<?php
require_once("config.php");
require_once("funcsv2.php");
//Check session
session_start();

if (!$_SESSION['admin_logged_in'] && !$_SESSION['upload_logged_in'])
{
	//check fails
	header("Location: authenticate.php?status=error");
	exit();
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Add Torrent to Tracker</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" type="text/css" href="./css/style.css" />
</head>
<body>

<?php
$tracker_url = $website_url . substr($_SERVER['PHP_SELF'], 0, -15) . $announceurl;

if (isset($_FILES["torrent"]))
	addTorrent();


endOutput();

	
function addTorrent()
{
	require ("config.php");
	$tracker_url = $website_url . substr($_SERVER['PHP_SELF'], 0, -15) . $announceurl;
	
	$hash = strtolower($_POST["hash"]);

	$db = mysql_connect($dbhost, $dbuser, $dbpass) or die(errorMessage() . "Couldn't connect to the database, contact the administrator</p>");
	mysql_select_db($database) or die(errorMessage() . "Can't open the database.</p>");
	
	require_once ("funcsv2.php");
	require_once ("BDecode.php");
	require_once ("BEncode.php");
	
	if ($_FILES["torrent"]["error"] != 4)	
	{
		$fd = fopen($_FILES["torrent"]["tmp_name"], "rb") or die(errorMessage() . "File upload error 1</p>\n");
		is_uploaded_file($_FILES["torrent"]["tmp_name"]) or die(errorMessage() . "File upload error 2</p>\n");
		$alltorrent = fread($fd, filesize($_FILES["torrent"]["tmp_name"]));

		$array = BDecode($alltorrent);
		if (!$array)
		{
			echo errorMessage() . "Error: The parser was unable to load your torrent.  Please re-create and re-upload the torrent.</p>\n";
			endOutput();
			exit;
		}

		if (isset($array["announce-list"])) {
			//multiple trackers are listed
			$found_tracker = false;
			for ($i = 0; $i < count($array["announce-list"]); $i++) {
				if (strtolower($array["announce-list"][$i][0]) == $tracker_url) {
					$found_tracker = true;
					break;
				}
			}
			if ($found_tracker == false)
			{
				echo errorMessage() . "Error: Multiple trackers were found but none of them match the
					announce URL:<br>$tracker_url<br>Please re-create and re-upload the torrent.</p>\n";
				endOutput();
				exit;
			}
		} else {
			//a single tracker is listed
			if (strtolower($array["announce"]) != $tracker_url) {
				echo errorMessage() . "Error: The tracker announce URL does not match this:<br>$tracker_url<br>Please re-create and re-upload the torrent.</p>\n";
				endOutput();
				exit;
			}
		}

		if (isset($_POST["httpseed"]) && $_POST["httpseed"] == "enabled" && $_POST["relative_path"] == "")
		{
			echo errorMessage() . "Error: HTTP seeding was checked however no relative path was given.</p>\n";
			endOutput();
			exit;
		}
		if (isset($_POST["httpseed"]) && $_POST["httpseed"] == "enabled" && $_POST["relative_path"] != "")
		{
			if (Substr($_POST["relative_path"], -1) == "/")
			{
				if (!is_dir($_POST["relative_path"]))
				{
					echo errorMessage() . "Error: HTTP seeding relative path ends in / but is not a valid directory.</p>\n";
					endOutput();
					exit;
				}
			}
			else
			{
				if (!is_file($_POST["relative_path"]))
				{
					echo errorMessage() . "Error: HTTP seeding relative path is not a valid file.</p>\n";
					endOutput();
					exit;
				}
			}
		}
		if (isset($_POST["getrightseed"]) && $_POST["getrightseed"] == "enabled" && $_POST["httpftplocation"] == "")
		{
			echo errorMessage() . "Error: GetRight HTTP seeding was checked however no URL was given.</p>\n";
			endOutput();
			exit;
		}
		if (isset($_POST["getrightseed"]) && $_POST["getrightseed"] == "enabled" &&
			(Substr($_POST["httpftplocation"], 0, 7) != "http://" && Substr($_POST["httpftplocation"], 0, 6) != "ftp://")
		)
		{
			echo errorMessage() . "Error: GetRight HTTP seeding URL must start with http:// or ftp://</p>\n";
			endOutput();
			exit;
		}
		$hash = @sha1(BEncode($array["info"]));
		fclose($fd);
		
		$target_path = "torrents/";
		$target_path = $target_path . basename( clean($_FILES['torrent']['name'])); 
		$move_torrent = move_uploaded_file($_FILES["torrent"]["tmp_name"], $target_path);
		if ($move_torrent == false)
		{
			echo errorMessage() . "Unable to move " . $_FILES["torrent"]["tmp_name"] . " to torrents/</p>\n";
		}	
	}
	

	if (isset($_POST["filename"]))
		$filename = clean($_POST["filename"]);
	else
		$filename = "";
	
	if (isset($_POST["url"]))
		$url = clean($_POST["url"]);
	else
		$url = "";

	if (isset($_POST["autoset"]))
	if (strcmp($_POST["autoset"], "enabled") == 0)
	{
		if (strlen($filename) == 0 && isset($array["info"]["name"]))
			$filename = $array["info"]["name"];
	}
	

	//figure out total size of all files in torrent
	$info = $array["info"];
	$total_size = 0;
	if (isset($info["files"]))
	{
		foreach ($info["files"] as $file)
		{
			$total_size = $total_size + $file["length"];
		}
	}
	else
	{
		$total_size = $info["length"];
	}
	
	//Validate torrent file, make sure everything is correct
	
	$filename = mysql_real_escape_string($filename);
	$filename = stripslashes($filename);
	$filename = htmlspecialchars(clean($filename));
	$url = htmlspecialchars(mysql_real_escape_string($url));

	if ((strlen($hash) != 40) || !verifyHash($hash))
	{
		echo errorMessage() . "Error: Info hash must be exactly 40 hex bytes.</p>\n";
		endOutput();
	}

	if (Substr($url, 0, 7) != "http://" && $url != "")
	{
		echo errorMessage() . "Error: The Torrent URL does not start with http:// Make sure you entered a correct URL.</p>\n";
		endOutput();
	}

	$query = "INSERT INTO ".$prefix."namemap (info_hash, filename, url, size, pubDate) VALUES (\"$hash\", \"$filename\", \"$url\", \"$total_size\", \"" . date('D, j M Y h:i:s') . "\")";
	$status = makeTorrent($hash, true);
	quickQuery($query);
	if ($status)
	{
		echo "<p class=\"success\">Torrent was added successfully.</p>\n";
		echo "<a href=\"newtorrents.php\"><img src=\"images/add.png\" border=\"0\" class=\"icon\" alt=\"Add Torrent\" title=\"Add Torrent\" /></a><a href=\"newtorrents.php\">Add Another Torrent</a><br>\n";
		//rename torrent file to match filename
		rename("torrents/" . clean($_FILES['torrent']['name']), "torrents/" . $filename . ".torrent");
		//make torrent file readable by all
		chmod("torrents/" . $filename . ".torrent", 0644);
	
		//run RSS generator
		require_once("rss_generator.php");
		//Display information from DumpTorrentCGI.php
		require_once("torrent_functions.php");
	}
	else
	{
		echo errorMessage() . "There were some errors. Check if this torrent has been added previously.</p>\n";
		//delete torrent file if it doesn't exist in database
		$query = "SELECT COUNT(*) FROM ".$prefix."summary WHERE info_hash = '$hash'";
		$results = mysql_query($query) or die(errorMessage() . "Can't do SQL query - " . mysql_error() . "</p>");
		$data = mysql_fetch_row($results);
		if ($data[0] == 0)
		{
			if (file_exists("torrents/" . $_FILES['torrent']['name']))
				unlink("torrents/" . $_FILES['torrent']['name']);
		}
		//make torrent file readable by all
		chmod("torrents/" . $filename . ".torrent", 0644);
		endOutput();
	}
}

function endOutput() 
{
	require ("config.php");
	$tracker_url = $website_url . substr($_SERVER['PHP_SELF'], 0, -15) . $announceurl;
	?>
	<p align="right"><a href="./docs/help.html"><img src="images/help.png" border="0" class="icon" alt="Help" title="Help" /></a><a href="./docs/help.html">Help</a></p>
	<div class="center">
	<h1>Add Torrent to Tracker Database</h1>
	<h3>Tracker URL: <?php echo $tracker_url;?></h3>
	<form enctype="multipart/form-data" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
	<table>
	<tr>
		<td class="right">Torrent file:</td>
		<td class="left"><?php
		if (function_exists("sha1"))
			echo "<input type=\"file\" name=\"torrent\" size=\"50\"/>";
		else
			echo '<i>File uploading not available - no SHA1 function.</i>';
		?></td>
	</tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr>	
	<td class="center" colspan="2"><input type="checkbox" name="httpseed" value="enabled">Use BitTornado HTTP seeding specification (optional)</td>
	</tr>
	<tr>
	<td class="right">Relative location of file or directory:<br>e.g. ../../files/file.zip</td>
	<td class="left"><input type="text" name="relative_path" size="70"/></td>
	</tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr>
	<td class="center" colspan="2"><input type="checkbox" name="getrightseed" value="enabled">Use GetRight HTTP seeding specification (optional)</td>
	</tr>
	<tr>
	<td class="right">FTP/HTTP URL of file or directory:<br>e.g. http://yourwebsite.com/file.zip</td>
	<td class="left"><input type="text" name="httpftplocation" size="70"/></td>
	</tr>
	<tr><td colspan="2"><hr></td></tr>
	<?php if (function_exists("sha1")) 
		echo "<tr><td class=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"autoset\" value=\"enabled\" checked=\"checked\" /> Fill in fields below automatically using data from the torrent file.</td></tr>\n";
	?>
	<tr>
		<td class="right">Info Hash:</td>
		<td class="left"><input type="text" name="hash" size="40"/></td>
	</tr>
	<tr>
		<td class="right">File name (optional): </td>
		<td class="left"><input type="text" name="filename" size="60" maxlength="200"/></td>
	</tr>
	<tr>
		<td class="right">Torrent's URL (optional): </td>
		<td class="left"><input type="text" name="url" size="60" maxlength="200"/></td>
	</tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr>
		<td class="center" colspan="2"><input type="submit" value="Add Torrent to Database"/> - <input type="reset" value="Clear Settings"/></td>
	</tr>
	</table>
	<br>
	<input type="hidden" name="username" value="<?php echo $_POST['username']; ?>"/>
	<input type="hidden" name="password" value="<?php echo $_POST['password']; ?>"/>
	</form>
	<a href="index.php"><img src="images/stats.png" border="0" class="icon" alt="Tracker Statistics" title="Tracker Statistics" /></a><a href="index.php">Return to Statistics Page</a><br>
	</div>
	</body></html>
	<?php 	
	// Still in function endOutput()
	exit;
}
?>
