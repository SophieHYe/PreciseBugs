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
	<title>Change CSS File</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" href="./css/style.css" type="text/css" />
	<style type="text/css">
		td.cell{
			width: 2%;
			}
	</style>
	<script type="text/javascript">
	function changeColor(color)
	{
		document.getElementById("color_box").value = color;
		document.getElementById("thecolor").style.backgroundColor = color;
	}
	</script>
</head>
<body>
<center>
<h1>Change CSS File</h1>
</center>
<br>

<?php

if (isset($_POST["set_css"]))
{
	//delete style.css file
	if (copy("./css/" . $_POST["set_css"], "./css/style.css"))
		echo "<p class=\"success\">style.css file has been replaced with " . $_POST["set_css"] . "</p>";
	else
	{
		echo errorMessage() . "Error: Unable to copy over style.css, are the permissions correct?</p>";
		exit();
	}
}
elseif (isset($_POST["delete_css"]))
{
	//delete css file
	if (unlink("./css/" . $_POST["delete_css"]))
		echo "<p class=\"success\">" . $_POST["delete_css"] . " has been deleted</p>";
	else
	{
		echo errorMessage() . "Error: Unable to delete " . $_POST["delete_css"] . ", are you sure the permissions are correct?</p>";
		exit();
	}
}
elseif (isset($_POST["create_css"]))
{
	//create new css file by copying over style.css into new file
	if (substr($_POST["create_css"], -4) == ".css")
	{
		if (!file_exists("./css/" . $_POST["create_css"]))
		{
			if (copy("./css/style.css", "./css/" . $_POST["create_css"]))
				echo "<p class=\"success\">" . $_POST["create_css"] . ", was created successfuly</p>";
			else
			{
				echo errorMessage() . "Error: Unabled to create " . $_POST["create_css"] . ", are you sure the permissions are correct?</p>";
				exit();			
			}
		}
		else
		{
			echo errorMessage() . "Error: " . $_POST["create_css"] . " already exists, please choose a different name</p>";
			exit();
		}
	}
	else
	{
		echo errorMessage() . "Error: Your file doesn't end with .css</p>";
		exit();
	}
}

if (isset($_POST["create_css"]) || isset($_POST["edit_css"]))
{
	//display color picker
	?>
	<h2>Color Picker:</h2>
	<table style="cursor: pointer;" border="0">
	<?php
	function rgbhex($red, $green, $blue)
	{
		return sprintf('#%02X%02X%02X', $red, $green, $blue);
	}
	
	//create table of 216 web safe colors
	for ($red = 0; $red < 256; $red = $red + 51)
	{
		echo "<tr>";
		for ($green = 0; $green < 256; $green = $green + 51)
		{
			for ($blue = 0; $blue < 256; $blue = $blue + 51)
			{
				$hexcolor = rgbhex($red, $green, $blue);
				echo "<td bgcolor='" . $hexcolor . "' title='" . $hexcolor . "' class='cell' onClick=\"changeColor('" . $hexcolor . "')\">&nbsp;</td>\n";
			}
		}
		echo "</tr>";
	}
	
	?>
	</table>
	<br>
	<b>Color:</b>
	<table border="0"><tr>
	<td id="thecolor" align="left" bgcolor="#000000"><input type="text" id="color_box" value="#000000"/>
	</td></tr>
	</table>
	<br>
	<?php
	
	if (isset($_POST["create_css"]))
		$filename = $_POST["create_css"];
	if (isset($_POST["edit_css"]))
		$filename = $_POST["edit_css"];
	//display text box with css in it
	?>
	<h2>Editing File: <?php echo $filename;?></h2>
	<form action="<?php echo $_SERVER["PHP_SELF"];?>" method="post">
	<input type="hidden" name="hidden_filename" value="<?php echo $filename;?>"/>
	<input type="hidden" name="current_css_file" value="<?php echo $_POST['current_css_file'];?>"/>
	<textarea name="file_contents" cols="120" rows="20"><?php
	//open css file
	readfile("./css/" . $filename);
	?></textarea>
	<br><br>
	<input type="submit" value="Save File"/>
	</form>
	<?php
	
}

if (isset($_POST["file_contents"]))
{
	//save previously edited text into file
	if (is_writable("./css/" . $_POST["hidden_filename"]))
	{
		//open file
		$stream = fopen("./css/" . $_POST["hidden_filename"], "w");
		fwrite($stream, $_POST["file_contents"]);
		fclose($stream);
		echo "<p class=\"success\">" . $_POST["hidden_filename"] . ", was saved successfuly</p>";
	}
	else
	{
		echo errorMessage() . "Error: The file cannot be saved, check the permissions</p>";
		exit();
	}
	//if editing the current css file, replace that too
	if ($_POST["current_css_file"] == $_POST["hidden_filename"])
	{
		if (copy("./css/" . $_POST["hidden_filename"], "./css/style.css"))
			echo "<p class=\"success\">style.css file has been replaced with " . $_POST["hidden_filename"] . "</p>";
		else
		{
			echo errorMessage() . "Error: Unable to copy over style.css, are the permissions correct?</p>";
			exit();
		}
	}
}

if (!isset($_POST["create_css"]) && !isset($_POST["edit_css"]) && !isset($_POST["delete_css"]) && 
!isset($_POST["set_css"]) && !isset($_POST["file_contents"]))
{
	//save all files in css directory to array
	$current_css_file = "";
	$css_style_md5 = md5_file("./css/style.css");
	$number_files = 0;
	if ($dh = opendir("./css/"))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (filetype("./css/" . $file) == "file" && $file != "index.php" && $file != "style.css" && substr($file, -4) == ".css")
			{
				if (md5_file("./css/" . $file) == $css_style_md5)
					$current_css_file = $file;
				$files_array[$number_files] = $file;
				$number_files++;
			}
		}
		closedir($dh);
	}
	echo "<b>Currently Used CSS File: " . $current_css_file . "</b><br><br>";
	?>
	
	<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
	<b>Set CSS File:</b><select name="set_css">
	<?php
	for ($i = 0; $i < $number_files; $i++)
	{
		if ($files_array[$i] != $current_css_file) //no point setting it to itself...
			echo "<option value=\"" . $files_array[$i] . "\">" . $files_array[$i] . "</option>\n\t";
	}
	?>
	</select>
	<input type="submit" value="Set CSS File"/>
	</form>
	<br><br>
	
	<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post"> 
	<b>Delete CSS File:</b><select name="delete_css">
	<?php
	for ($i = 0; $i < $number_files; $i++)
	{
		if ($files_array[$i] != $current_css_file) //can't delete the file if it's already being used...
			echo "<option value=\"" . $files_array[$i] . "\">" . $files_array[$i] . "</option>\n\t";
	}
	?>
	</select>
	<input type="submit" value="Delete CSS File"/>
	</form>
	<br><br>
	
	<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
	<input type="hidden" name="current_css_file" value="<?php echo $current_css_file;?>"/>
	<b>Edit Existing CSS File:</b><select name="edit_css">
	<?php
	for ($i = 0; $i < $number_files; $i++)
	{
		echo "<option value=\"" . $files_array[$i] . "\">" . $files_array[$i] . "</option>\n\t";
	}
	?>
	</select>
	<input type="submit" value="Edit CSS File"/>
	</form>
	<br><br>
	
	<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post"> 
	<b>Create New CSS File (e.g. mycssfile.css):</b>
	<input type="text" size="40" name="create_css"/>
	<input type="submit" value="Create New CSS File"/>
	</form>
	<?php
}
?>

<br>
<br>
<a href="admin.php"><img src="images/admin.png" border="0" class="icon" alt="Admin Page" title="Admin Page" /></a><a href="admin.php">Return to Admin Page</a>
</body>
</html>
