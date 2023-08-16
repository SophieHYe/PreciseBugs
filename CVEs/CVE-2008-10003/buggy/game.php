<?php


// ------------------------------------------------------------------------- //
//                      flashgames                                           //
//                     <http://www.tipsmitgrips.de>                          //
// ------------------------------------------------------------------------- //
// based on                                                                  //
// Xoops module "myalbum"          - http://bluetopia.homeip.net/            //
// Postnuke module "pnflashgames"  - http://www.pnflashgames.com             //
// Mainly based on:                                                          //
// XOOPS PHP Content Management System - http://www.xoops.org/               //
// and:                                                                      //
// myPHPNUKE Web Portal System - http://myphpnuke.com/                       //
// PHP-NUKE Web Portal System - http://phpnuke.org/                          //
// Thatware - http://thatware.org/                                           //
// ------------------------------------------------------------------------- //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
// ------------------------------------------------------------------------- //
include("header.php");
global $full, $item_id, $url, $email, $logourl ;
$myts =& MyTextSanitizer::getInstance();// MyTextSanitizer object
include_once(XOOPS_ROOT_PATH."/class/xoopstree.php");
//include_once(XOOPS_ROOT_PATH."/class/xoopscomments.php");
$mytree = new XoopsTree($xoopsDB->prefix("flashgames_cat"),"cid","pid");
$xoopsOption['template_main'] = 'flashgames_game.html';

$lid = $HTTP_GET_VARS['lid'];
if(!$full) {
    include(XOOPS_ROOT_PATH."/header.php");
//    OpenTable();
}
//mainheader();

if ($lid == "") {
	$lid = $item_id;
}
if ($item_id == "") {
	$item_id = $lid;
}


$q = "select l.lid, l.cid, l.title, l.ext, l.res_x, l.res_y, l.bgcolor, l.status, l.members, l.date, l.hits, l.rating, l.votes, l.comments, l.submitter, t.description, l.gametype, l.license, l.classfile from ".$xoopsDB->prefix("flashgames_games")." l, ".$xoopsDB->prefix("flashgames_text")." t where l.lid=$lid and l.lid=t.lid and status>0";
$result=$xoopsDB->query($q);

list($lid, $cid, $ltitle, $ext, $res_x, $res_y, $bgcolor, $status, $members, $time, $hits, $rating, $votes, $comments, $submitter, $description, $gametype, $license, $classfile)=$xoopsDB->fetchRow($result);

if($full) {
    print "<html><body><center><br>";
    print "<img src=".XOOPS_URL."/modules/flashgames/games/$lid.$ext><br><br>";  
    print "<input type=button value="._ALBM_BACK." onclick=\"javascript:history.go(-1)\">";
    print "<center></body></html>";
    exit;
}

//echo "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"3\" border=\"0\"><tr><td align=\"center\">\n";
//echo "<table width=\"100%\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\" bgcolor=\"cccccc\"><tr><td>\n";

//$pathstring = "<a href=index.php>"._ALBM_MAIN."</a>&nbsp;:&nbsp;";
$pathstring = "<a href=index.php>"._ALBM_HOME."</a>&nbsp;:&nbsp;";  // Oka

$nicepath = $mytree->getNicePathFromId($cid, "title", "viewcat.php?op=");
$pathstring .= $nicepath;
//echo "<b>".$pathstring."</b>";
//echo "</td><td align='right'><a href='submit.php?cid=$cid'><b>Add game</b></a></td></tr></table><br><div align=\"center\">";
$xoopsTpl->assign('category_path', $pathstring);

// category navigation
$fullcountresult=$xoopsDB->query("select lid from ".$xoopsDB->prefix("flashgames_games")." where cid=$cid and status>0");
while (list($id) = $xoopsDB->fetchRow($fullcountresult)) {
	$ids[] = $id;
}

if ($ids[0] != $lid) {
	$prev = array_search($lid,$ids)-1;
//	print "<a href='game.php?lid=".$ids[0]."'><b>[&lt; </b></a>&nbsp;&nbsp;";	
//	print "<a href='game.php?lid=".$ids[$prev]."'><b>"._ALBM_PREVIOUS."</b></a>&nbsp;&nbsp;";
	$game_nav = "<a href='game.php?lid=".$ids[0]."'><b>[&lt; </b></a>&nbsp;&nbsp;";	
	$game_nav .= "<a href='game.php?lid=".$ids[$prev]."'><b>"._ALBM_PREVIOUS."</b></a>&nbsp;&nbsp;";
    
}

$nwin = 7;
if (count($ids) > $nwin) { // window
	$pos = array_search($lid,$ids);
	if ($pos > $nwin/2) {
		if ($pos > round(count($ids) - ($nwin/2)-1) ) {
			$start = count($ids) - $nwin + 1;
		} else {
			$start = round($pos - ($nwin/2))+1;
		}
	} else {
		$start = 1;
	}
}



// OKa  Navigation deaktiviert
for($i = $start; ($i < count($ids)+1) and ($i < $start + $nwin); $i++) {
	if ($ids[$i-1] == $lid) {
		//print "$i&nbsp;&nbsp;";
        $game_nav .= "$i&nbsp;&nbsp;";
	} else {
		//print "<a href='game.php?lid=".$ids[$i-1]."'>$i</a>&nbsp;&nbsp;";
        $game_nav .= "<a href='game.php?lid=".$ids[$i-1]."'>$i</a>&nbsp;&nbsp;";
	}
}

if ($ids[count($ids)-1] != $lid) {
	$next = array_search($lid,$ids)+1;
//	print "<a href='game.php?lid=".$ids[$next]."'><b>"._ALBM_NEXT."</b></a>&nbsp;&nbsp;";
//	print "<a href='game.php?lid=".$ids[count($ids)-1]."'><b> &gt;]</b></a>";
   	$game_nav .= "<a href='game.php?lid=".$ids[$next]."'><b>"._ALBM_NEXT."</b></a>&nbsp;&nbsp;";
	$game_nav .= "<a href='game.php?lid=".$ids[count($ids)-1]."'><b> &gt;]</b></a>";
}



$xoopsTpl->assign('game_nav', $game_nav);

// OKa
// User logged in ? if not then show text;
if ( !$xoopsUser ) {
$xoopsTpl->assign('flashgames_notloggedin' ,  _ALBM_NOTLOGGEDIN); 

if ( $members == 1 ){ 
   $members = 2;}
  
}


if ($members <> 2){
$xoopsDB->queryF("update ".$xoopsDB->prefix("flashgames_games")." set hits=hits+1 where lid=$lid and status>0");
}


//echo "</div><table width=\"100%\" cellspacing=0 cellpadding=10 border=0>";
$rating = number_format($rating, 2);
$title = $myts->makeTboxData4Show($ltitle);
$url = $myts->makeTboxData4Show($url);
$url = urldecode($url);
$email = $myts->makeTboxData4Show($email);
$logourl = $myts->makeTboxData4Show($logourl);
#	$logourl = urldecode($logourl);
$datetime = formatTimestamp($time,"m");
$description = $myts->makeTareaData4Show($description,0);

//PNFG UPDATE: get the game's checksum and domain
$domain = pnFlashGames_getDomain();
$gamefile = "games/$lid.$ext";
$checksum = pnFlashGames_getChecksum($gamefile);

global $xoopsUser;

if ( $xoopsUser ) {
//echo $xoopsUser->uid();
	if ( $xoopsUser->uid() == $submitter or $xoopsUser->isAdmin($xoopsModule->mid()) ) {
		$admin = "<a href=\"".XOOPS_URL."/modules/flashgames/editgame.php?lid=$lid\"><img src=\"".XOOPS_URL."/modules/flashgames/images/editicon.gif\" border=\"0\" alt=\""._ALBM_EDITTHISLINK."\" /></a>  ";
	}
}

$big = 1;
//include("include/linkformat.php");


$xoopsTpl->assign('game', array('id' => $lid, 'ext' => $ext, 'rating' => $rating,
'title' => $title, 'url' => $url, 'email' => $email, 'logourl' => $logourl,
'datetime' => $datetime, 'description' => $description, 'hits' => $hits,
'votes' => $votes, 'license' => $license, 'checksum' => $checksum, 'domain' => $domain, 'res_x' => $res_x,
'res_y' => $res_y, 'bgcolor' => $bgcolor, 'classfile' => $classfile, 'members' => $members));
$xoopsTpl->assign('admin', $admin);
$xoopsTpl->assign('game_title', $title);  // Oka
 $xoopsTpl->assign('lang_description', _ALBM_DESCRIPTIONC);
$xoopsTpl->assign('lang_lastupdate', _ALBM_LASTUPDATEC);
$xoopsTpl->assign('lang_hits', _ALBM_HITSC);
$xoopsTpl->assign('lang_rating', _ALBM_RATINGC);
$xoopsTpl->assign('lang_email', _ALBM_EMAILC);
$xoopsTpl->assign('lang_ratethissite', _ALBM_RATETHISGAME);
$xoopsTpl->assign('lang_reportbroken', _ALBM_REPORTBROKEN);
$xoopsTpl->assign('lang_tellafriend', _ALBM_TELLAFRIEND);
$xoopsTpl->assign('lang_modify', _ALBM_MODIFY);
$xoopsTpl->assign('lang_category' , _ALBM_CATEGORYC);
$xoopsTpl->assign('lang_visit' , _ALBM_VISIT);
$xoopsTpl->assign('lang_comments' , _COMMENTS);
$xoopsTpl->assign('lang_notamember' , _ALBM_NOTAMEMBER);





//PNFG UPDATE: Moved footer output to common funtion for ease of maintenence.
$xoopsTpl->assign('flashgames_footer' , GetFooter());

//echo "</table>";
//echo "</td></tr></table>\n";

// comments

// OKa begin
// get player name from DB

if ( $xoopsUser ){
 $uid = $xoopsUser->uid();
 $result=$xoopsDB->query("SELECT uname FROM ".$xoopsDB->prefix("users")." WHERE uid = $uid") or die("Error");
 $myrow = $xoopsDB->fetchArray($result);
 $playername = $myrow['uname'];
}else{
 $playername = "Guest";
}
$xoopsTpl->assign('username' , $playername);

// Highscoretabelle laden
//$myts =& MyTextSanitizer::getInstance();

$xoopsTpl->assign('gametype', $gametype);

$xoopsTpl->assign(array(
            "lang_halloffame"   => _ALBM_HIGHSCORE ,
			"lang_rang" 		=> _ALBM_RANG , 
			"lang_name" 	=> _ALBM_NAME, 
			"lang_score"    => _ALBM_SCORE, 
			"lang_date"		=> _ALBM_DATE));


if ($gametype == 1)
{
$sql=sprintf("SELECT   g.score, g.name , g.date from %s g where g.lid = $lid order
BY g.score DESC, g.name, g.date",$xoopsDB->prefix("flashgames_score"));
}

if ($gametype == 2)
{
$sql=sprintf("SELECT   g.score, g.name , g.date from %s g where g.lid = $lid order
BY g.score ASC, g.name, g.date",$xoopsDB->prefix("flashgames_score"));
} 

if ($gametype == 3) 
{ 
$sql=sprintf("SELECT   g.score, g.name , g.date from %s g where g.lid = $lid order
BY g.score DESC, g.name, g.date",$xoopsDB->prefix("flashgames_score")); 
$xoopsTpl->assign('lang_score' , "Zeit");
}

if ($gametype == 4)
{ 
$sql=sprintf("SELECT   g.score, g.name , g.date from %s g where g.lid = $lid order
BY g.score ASC, g.name, g.date",$xoopsDB->prefix("flashgames_score")); 
$xoopsTpl->assign('lang_score' , "Zeit");
}




$counter = 1;
$rank = 0;
$lastscore = -999999999999999;

// $result=$xoopsDB->queryF($sql,10);
$result=$xoopsDB->queryF($sql,$flashgames_scoreshow); //oka


while($row1 = $xoopsDB->fetchArray($result))
{
//	$time = floor($row1['time']/1000);
//	$minutes = (string)(floor($time/60));
//	$seconds = (string)($time % 60);	
//	$hall_time =sprintf("%d'%d\"",$minutes,$seconds);
//	$hall_name = $myts->makeTboxData4Show($row1['uname']);
	$name = $myts->makeTboxData4Show($row1['name']);
        
        $date1 = $row1['date'];
        $date =  get_DateTime($date1);

//        $date = formatTimestamp($sqlfetch["record_date"],"m");

$score = $row1['score'] ;	
if ( $gametype == 3  or  $gametype == 4 ) {
 //This is a time based score, so format it accordingly.  All time based scores are stored in seconds
 $timestamp = mktime(0, 0, $score);
 $score = strftime("%H:%M:%S", $timestamp);
}

// $xoopsTpl->append('halloffamescores',array('rang'=>$counter, 'name'=>$name, 'score'=>$row1['score'], 'date' =>  // $date ));

if ($name == $playername)
{
  $name = "<strong>".$name."</strong>"; 

}




           if ($lastscore != $score) 
           {
        	$rank++;
        
		}
                         

            $lastscore = $score;



$xoopsTpl->append('halloffamescores',array('rang'=>$rank, 'name'=>$name, 'score'=>$score, 'date' =>  $date));

 $counter++;
}
// OKa end



include XOOPS_ROOT_PATH.'/include/comment_view.php';

//CloseTable();
//include("footer.php");

include_once("../../footer.php");
?>
