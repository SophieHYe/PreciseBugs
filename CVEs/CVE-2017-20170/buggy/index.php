<!--Kotisivut Riikka/Hevoset, draft 1; /index.php-->
<?


$rel = "./";

include $rel."db.php";


$haku_fp = mysqli_query($yht, "SELECT * FROM `sivut` LIMIT 1");
$fprow = mysqli_fetch_assoc($haku_fp);


//Go to first page in order (front page) if ?s argument is not set
if(!isset($_GET["s"]))	{$_GET['s'] = $fprow['uid'];}


$haku_s = mysqli_query($yht, "SELECT * FROM `sivut` WHERE uid = '".$_GET['s']."' LIMIT 1");


$pagerow = mysqli_fetch_assoc($haku_s);

$usePhotoSwipe = false;

function image_gallery($images = array()) {
	if(count($images)) {
		$toReturn = "
			<div class='img-gallery img' itemscope itemtype='http://schema.org/ImageGallery'>";
		foreach ($images as $picture) {
			$toReturn .= "<figure itemprop='associatedMedia' itemscope itemtype='http://schema.org/ImageObject'>
				<a href='".$picture['img-large']."' itemprop='contentUrl' data-size='".$picture['img-size']."'>
					<img src='".$picture['img-thumb']."' itemprop='thumbnail' alt='".$picture['text']."' />
				</a>
				<figcaption itemprop='caption description'>".$picture['text'];
				if($picture['author'] != "") $toReturn .= "<br/><small>Kuva: ".$picture['author']."</small>";
				$toReturn .= "</figcaption>
				";
			if($picture['break-row']) $toReturn .= "<br/>";
			$toReturn .= "</figure>";
		}
		$toReturn .= "</div>";
		return $toReturn;
	}
}

function image_exists($id, $yht) {
	return mysqli_fetch_array(mysqli_query($yht, "SELECT `imgur-uid` FROM images WHERE `imgur-uid` = '$id' LIMIT 1")) !== false;
}
function get_image_by_id($id, $yht) {
	return mysqli_fetch_assoc(mysqli_query($yht, "SELECT * FROM images WHERE `imgur-uid` = '$id' LIMIT 1"));
}

function generate_gallery($text, $yht) {
	$idArray = explode('*', $text);
	$images = array();
	foreach ($idArray as $rid) {
		$attributes = explode('&', $rid);
		$id = $attributes[0];
		if (image_exists($id, $yht)) {
			$images[$id] = get_image_by_id($id, $yht);
			$images[$id]['break-row'] = $attributes[1] == "br";
		}
	}
	return image_gallery($images);
}

$kuva = $pagerow["kuva"];
$rawtext = $pagerow["teksti"];
$teksti = preg_replace_callback(
	"/\\<!-+gallery (.+?) gallery-+\\>/",
	function ($matches) use (&$usePhotoSwipe, $yht) {
		$usePhotoSwipe = true;
		return generate_gallery($matches[1], $yht);
	},
	$rawtext
);
$nimi = $pagerow["nimi"];
$html = $pagerow["html"];
$selitys = $pagerow["selitys"];
$uid = $pagerow["uid"];

?>
<html>
<head>

<?php include $rel."skeleton/metas.php" ?>


<?
echo("
	<meta name='description' content='$selitys'>
	<meta name='og:site_name' content='Parontalli.fi'>
	<meta name='og:title' content='$nimi'>
	<meta name='og:description' content='$selitys'>
");

?>
<title>
	<? echo $nimi; ?>
</title>

<?php include $rel."skeleton/styles.php" ?>

</head>

<script>
function execRnav()
{
	if (typeof rnavPosByHeader == 'function') {rnavPosByHeader();}
}
function runHandles()
{
	if (typeof addHandles() == 'function') {addHandles();}
}
</script>

<body onresize="execRnav();" onscroll="execRnav();" onload="runHandles();">

<?php if ($usePhotoSwipe) include "{$rel}skeleton/photoswipe.php" ?>
<?php include $rel."skeleton/header.php" ?>

<div class="nav">
	<?
	$haku = mysqli_query($yht, "SELECT * FROM sivut ORDER BY id");
	while ($row = mysqli_fetch_array($haku)){
		$thispage = "";
		if ($row['uid'] == $uid){
			$thispage = "thispage";
		}
		createLink($row['color'], "?s=".$row['uid'], $thispage, $row['nimi']);
	}
	?>

	<hr class="header" id="">
</div>
<?
if (!$kuva == ""){
	echo '
	<img src="'. $kuva .'" class="header">
	';
}

echo $html;

?>
<div class="content"><br>
	<?
	echo $teksti;
	?>
</div>
<?php include $rel."skeleton/footer.php" ?>
</body>
<html>
