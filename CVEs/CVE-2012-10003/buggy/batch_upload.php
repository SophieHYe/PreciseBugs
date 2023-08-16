<?php

require ("config.php");
require ("funcsv2.php"); //required for errorMessage() function
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

<html>
<head>
	<title>Batch Upload Torrents</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" href="./css/style.css" type="text/css" />
</head>
<body>
<center>
<h1>Batch Upload Torrents</h1>
</center>
<br>

<?php

if (isset($_FILES["zipfile"]) && $_FILES["zipfile"]["error"] != 4 && isset($_FILES["zipfile"]["tmp_name"])) //4 corresponds to the error no file uploaded
{
	?>
	<a href="admin.php"><img src="images/admin.png" border="0" class="icon" alt="Admin Page" title="Admin Page" /></a><a href="admin.php">Return to Admin Page</a>
	<br><br>
	<?php
	$zip = zip_open($_FILES["zipfile"]["tmp_name"]);
	
	if ($zip == true)
	{
		$db = mysql_connect($dbhost, $dbuser, $dbpass) or die(errorMessage() . "Couldn't connect to the database, contact the administrator</p>");
		mysql_select_db($database) or die(errorMessage() . "Can't open the database.</p>");
	
	   while ($zip_entry = zip_read($zip))
	   {
	   	echo "Name: " . zip_entry_name($zip_entry) . "<br>\n";
	      if (substr(zip_entry_name($zip_entry), -8) == ".torrent")
			{
				$error_status = true;
				if (zip_entry_open($zip, $zip_entry, "r"))
			   {
			   	//read in file from zip
			  		$buffer = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
			      //go through each torrent file and add it if possible
					require_once ("BDecode.php");
					require_once ("BEncode.php");
					
					$tracker_url = $website_url . substr($_SERVER['REQUEST_URI'], 0, -16) . $announceurl;
					
					$array = BDecode($buffer);
					if (!$array)
					{
						echo errorMessage() . "Error: The parser was unable to load this torrent.</p>\n";
						$error_status = false;
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
							$error_status = false;
							exit;
						}
					} else {
						//a single tracker is listed
						if (strtolower($array["announce"]) != $tracker_url) {
							echo errorMessage() . "Error: The tracker announce URL does not match this:<br>$tracker_url<br>Please re-create and re-upload the torrent.</p>\n";
							$error_status = false;
							exit;
						}
					}
					if (function_exists("sha1"))
						$hash = @sha1(BEncode($array["info"]));
					else
					{
						echo errorMessage() . "Error: It looks like you do not have a hash function available, this will not work.</p>\n";
						$error_status = false;
					}
				
					//figure out total size of all files in torrent, needed for insertion into database
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
					$filename = $array["info"]["name"];
					$filename = mysql_real_escape_string($filename);
					$filename = stripslashes($filename);
					$filename = clean($filename);
				
					if ((strlen($hash) != 40) || !verifyHash($hash))
					{
						echo errorMessage() . "Error: Info hash must be exactly 40 hex bytes.</p>\n";
						$error_status = false;
					}
					
				
					if ($error_status == true)
					{
						$query = "INSERT INTO " . $prefix . "namemap (info_hash, filename, url, size, pubDate) VALUES (\"$hash\", \"$filename\", \"$url\", \"$total_size\", \"" . date('D, j M Y h:i:s') . "\")";
						$status = makeTorrent($hash, true);
						quickQuery($query);
						if ($status == true)
						{
							//create torrent file in folder, at this point we assume it's valid
							if (!$handle = fopen("torrents/" . $filename . ".torrent", 'w'))
							{
	         				echo errorMessage() . "Error: Can't write to file.</p>\n";
	        					break;
	    					}
							//populate file with contents
					   	if (fwrite($handle, $buffer) === FALSE)
					   	{
					       	echo errorMessage() . "Error: Can't write to file.</p>\n";
					      	break;
					   	}
					   	fclose($handle);
							//make torrent file readable by all
							chmod("torrents/" . $filename . ".torrent", 0644);
							echo "<p class=\"success\">Torrent was added successfully.</p>\n";
						}
						else
						{
							echo errorMessage() . "There were some errors. Check if this torrent has been added previously.</p>\n";
						}
					}
			
			      zip_entry_close($zip_entry);
			    }
			} 
			else
				echo errorMessage() . "Unable to add torrent, it doesn't end in .torrent</p>\n";
			
		echo "<br>";
	   }
	   zip_close($zip);
	}

	//finished reading zip file
	
	//run RSS generator because we have new torrents in database
	require_once("rss_generator.php");


}
else
{
	//display upload box
	?>
	<?php require("config.php"); $tracker_url = $website_url . substr($_SERVER['REQUEST_URI'], 0, -16) . $announceurl; ?>
	<p>This page lets you upload a zip file containing multiple torrents and add them into the database.  The
	zip file cannot have any folders in it.  This requires that you are running PHP with compiled zip support.
	If you are unsure, check with your system administrator or phpinfo().  Any torrents that already exist in
	the database will be skipped.  If you want to use HTTP seeding you'll need to add this feature to the torrent
	files before you zip and upload the file.  If you are uploading a very large zip file this may take some time...
	<br>
	<br>
	The torrents you are batch uploading should include the following Tracker URL: <b><?php echo $tracker_url ?></b></p>
	
	<?php
	if (function_exists("zip_open"))
	{
		?>
		<form enctype="multipart/form-data" action="<?php echo $_SERVER["PHP_SELF"];?>" method="post">
		<b>Zip File:</b><input type="file" name="zipfile" size="50"/>
		<input type="submit" value="Upload ZIP File"/>
		</form>
		<?php
	}
	else
		echo errorMessage() . "Error: It looks like you don't have ZIP support compiled into PHP.</p>\n";
}

?>

<br>
<br>
<a href="admin.php"><img src="images/admin.png" border="0" class="icon" alt="Admin Page" title="Admin Page" /></a><a href="admin.php">Return to Admin Page</a>
</body>
</html>
