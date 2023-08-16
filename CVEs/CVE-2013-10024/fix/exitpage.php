<?php

/**
 * @package Wordpress Exit Strategy
 * @author Bouzid Nazim Zitouni
 * @version 1.59
 */
/*
Plugin Name: Wordpress Exit Strategy
Plugin URI: http://angrybyte.com/wordpress-plugins/wordpress-exit-strategy/
Description: Exit Strategy will pass all outgoing links from your site through a nofollow link to an exit page before finally being redirected to the external link. You may place anything in your exit page: Ads, Subscribtion buttons, etc. Using Wordpress Exit Strategy you improve your SEO score by not linking directly to external pages, you get more subscribers & more revenues if you use Ads.
Author: Bouzid Nazim Zitouni
Version: 1.59
Author URI: http://angrybyte.com
*/

if(!function_exists('add_action')){
    echo ""; // someone is trying to run the plugin directly, added to avoid full path disclosure.
    die;
}
add_option("exitpagecontents",
    'Thank you for your visit, You`ll be redirected in %n% seconds <br> <a href="%link%">Click here if you are not redirected automatically</a>',
    'Contents of the Exit page', 'yes');
add_option("exitpagedelay", '10', 'yes');
add_option("autoredirect", '1', 'yes');
add_option("countmessage", '', 'yes');
add_option("redirecttoparent", '1', 'yes');
//
add_filter('the_content', 'replacelinks');
add_action('wp_head', 'autoredirect');
add_action('admin_menu', 'exitpageadmin');
function exitpageadmin()
{

    add_options_page('Exit page admin', 'Exit Strategy', 8, __file__,
        'exit_page_admin');
}
function exit_page_admin()
{

    if (($_POST["xx"])&& (is_admin())&& check_admin_referer( 'exit_strategy_save', 'exit_strategy_nonce' ))
    {
        update_option('exitpagecontents', $_POST['xx']);
        update_option('redirecttoparent', $_POST['redirectpar']);

    }
    $oldtemp = stripcslashes(get_option("exitpagecontents"));
    $chkd = 1;
    $chkd2 = get_option("redirecttoparent");
    if ($chkd)
    {
        $chkd = "checked='checked'";

    } else
    {
        $chkd = "";
    }
    if ($chkd2)
    {
        $chkd2 = "checked='checked'";

    } else
    {
        $chkd2 = "";
    }
    echo <<< EOFT
    <h1> Wordpress Exit Strategy</h1>
    <table><tr><td width="70%">
    <div class="metabox-holder" />
    <div  class="postbox gdrgrid frontleft">
<h3 class="hndle">
<span>Exit Page Options</span>
</h3>
<div class="inside">
<div class="table">
<table>
<tbody>
<tr class="first">
<td class="first b">Exit Page Contents</td>
<td class="t options"><Form method ='post' action='$serv' ><input type='hidden' value='fit' name='fit' id='fit' />
EOFT;
    if (function_exists(wp_editor))
    {
        wp_editor($oldtemp, "xx");
    } else
    {
        echo "<textarea name='xx' cols='150' rows='20'>$oldtemp</textarea>";
    }
     wp_nonce_field( 'exit_strategy_save','exit_strategy_nonce' ); 
    echo <<< EOFT

  <br/>
  <ul>
  <li>%n% will be replaced by the redirect delay<br /></li>
 <li>%link% will be replaced by the redirection URL<br /></li>
<li><p style="color: gray;">%count% will be replaced by a redirection count down (Pro! Feature)</p></li>
<li><p style="color: gray;">%site+1% will be replaced by a Google +1 button for your site. (Pro! Feature)</p></li>
<li><p style="color: gray;">%post+1% will be replaced by a Google +1 button for your post. (Pro! Feature)</p></li>
<li><p style="color: gray;">%sitelike% will be replaced by a Facebook Like button for your site. (Pro! Feature)</p></li>
<li><p style="color: gray;">%postlike% will be replaced by a Facebook Like button for your post. (Pro! Feature)</p></li>
<li><p style="color: gray;"><b>%sitetweet%</b> will be replaced by a Twitter button for your site. (Pro! Feature)</p></li>
<li><p style="color: gray;"><b>%posttweet%</b> will be replaced by a Twitter button for your post. (Pro! Feature)</p></li>
<li>HTML and javascript are allowed<br /></li>
 
</tr>
<tr>
<td class="first b">Redirect delay in seconds</td><td class="t options"><input disabled="disabled" type="text" name='delay' value='10 (Editable in Pro!)' /></td></tr><tr>
<td>  Auto redirection.</td><td class="t options"><input disabled="disabled" type='checkbox' value='1' $chkd name='autoredirect' /> Enable auto redirection after the end of the delay (Can be disabled in Pro!)</td></tr><tr><td>
 redirect external</td><td class="t options"><input type='checkbox' value='1' $chkd2 name='redirectpar' /> Redirect external links to exit pages to their parent post</td></tr>
 <tr><td>
 Process entire pages</td><td class="t options"><input type='checkbox' disabled="disabled" value='0'   />  redirect links in the entire page including widgets and footer, or just the post contents (Pro! Feature)</td></tr>
 
 <tr><td>
End of count Message.</td><td class="t options"><input type="text" size='150' name='eoc' disabled="disabled" value='(Pro! Feature)' /><br /> Display this message when the %count% counter runs out (HTML allowed , can include %link%)</td></tr>
<tr><td>Excluded pages.</td><td class="t options"><input type="text" size='150' name='excl' disabled="disabled" value='(Pro! Feature)' /><br /> enter to post IDs to be excluded from your exit page, useful for your sales or affiliate pages. Separate by commas ex: 3,55,153
</td></tr>
<tr><td>
Excluded Links.</td><td class="t options"><textarea disabled="disabled" cols='100' rows='20'>(Pro! Feature)</textarea><br /> Enter the links you wish to exclude from your exit strategy. each link is a separate line. Excluding a website will exclude all pages within it, and excluding a page will not exclude the rest of the website.</td></tr>
<tr><td><input type='submit' value='save' /></form></td></tr>
</tbody>
</table>
</div></div>
</div></td><td VALIGN="TOP">
<div class="metabox-holder" />
    <div  class="postbox gdrgrid frontleft">
<h3 class="hndle">
<span>Wordpress Exit Strategy Pro!</span>
</h3>
<div class="inside">
<div class="table">
<table >
<tbody>
<tr class="first" VALIGN="TOP">
<td class="first b">Get <a href="http://angrybyte.com/wordpress-plugins/wordpress-exit-strategy/">Wordpress Exit Strategy Pro!</a> and unlock the full potential of your exit pages.
<ul>
<li ><b style="color: red; ">Optional auto-redirection:</b> Auto redirect, or just make the link appear after the count down.</li>
<li><b style="color: red;">Count down timer:</b> Show a java count down timer. Redirect or show the link when the timer runs out.</li>
<li><b style="color: red;">Customizable redirection delay:</b> Plan your exit strategy! easily change how long your visitors will wait before they leave your site.</li>
<li><b style="color: red;">Make your exceptions:</b> Select the posts to exclude from your Exit Strategy. Make it easier for your visitors to exit from selected posts, like product sale pages</li>
</ul>
<h2><a href="http://codecanyon.net/item/wordpress-exit-strategy-pro/1573775?ref=AngryByte">Get Exit Strategy Pro! Now</a></h2>
<a href="http://codecanyon.net/item/wordpress-exit-strategy-pro/1573775?ref=AngryByte"><img src="http://0.s3.envato.com/files/19325524/screenshot2.png" style="width:100%" alt="An exit page"/></a>
</td></tr></tbody></table></div></div></div>

<div class="metabox-holder" />
    <div  class="postbox gdrgrid frontleft">
<h3 class="hndle">
<span>Feeling Lucky? Get Pro! for Free!</span>
</h3>
<div class="inside">
<div class="table">
<table>
<tbody>
<tr class="first">
<td class="first b"><div id="fb-root"></div>
<p><script type="text/javascript">// <![CDATA[
    (function(d, s, id) {   var js, fjs = d.getElementsByTagName(s)[0];   if (d.getElementById(id)) return;   js = d.createElement(s); js.id = id;   js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&#038;appId=173688705979189";   fjs.parentNode.insertBefore(js, fjs); }(document, 'script', 'facebook-jssdk'));
// ]]></script>Now And for a limited time, You can get Wordpress Exit Strategy Pro for free! all you need to do is:<br />
                           <ol >
                           <li> <table style="display:inline";><tr><td>Give us a   <!-- Place this tag where you want the +1 button to render --><div class="g-plusone" data-size="medium" data-href="http://angrybyte.com"></div><!-- Place this render call where appropriate --><br /><script type="text/javascript">// <![CDATA[
      (function() {     var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;     po.src = 'https://apis.google.com/js/plusone.js';     var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);   })();// ]]></script></td><td> And a </td><td><div class="fb-like" data-href="http://angrybyte.com" data-send="false" data-layout="button_count" data-width="10" data-show-faces="false"></div></li></td></tr></table>
	                       <li><a href='http://www.facebook.com/pages/AngryBytecom/262969757073611?sk=wall'>Join our facebook page</a>, and comment on the Exit Strategy update!</li>
	                       <li><a href="http://wordpress.org/extend/plugins/exit-strategy/"> Rate Wordpress Exit Strategy on wordpress.org </a>, 5 starts would be great!</li>
                        	
                            </ol>
                          <br />
                           <br /> 
                           For each 100 ratings on wordpress.org, we`ll randomly pick 3 lucky winners from Facebook!</td></tr></tbody></table>



</td></tr></table>
EOFT;
}
function autoredirect()
{
    global $post;
    if ($_GET['xurl'])
    {
        $referer = $_SERVER['HTTP_REFERER'];
        $myurl = get_option('siteurl');
        $redirecting = get_option('autoredirect');
        //Just a security measure
        $m1 = strpos(strtolower(" " . $referer), strtolower($myurl));
        if ($referer == '')
        {
            $m1 = 1;
        }
        $url = $_GET['xurl'];
        $url = urldecode($url);
        $m2 = strpos(" " . $url, "<");
        $m3 = strpos(" " . $url, "$");
        $m4 = strpos(" " . $url, "'");
        $m5 = strpos(" " . $url, '"');
        if (!(($m2 || $m3 || $m4 || $m5)) && $m1 == 1 && $redirecting)
        {

            echo "<META HTTP-EQUIV='refresh' 
CONTENT='10;URL=$url'>";
        } elseif ($m1 != 1)
        {
            $chkd2 = get_option("redirecttoparent");
            //redirect external links to exit pages to their parent posts
            if ($chkd2)
            {
                $pageurl = get_permalink($post->ID);
                echo "<META HTTP-EQUIV='refresh' 
CONTENT='0;URL=$pageurl'>";
            }
        }


    }

}

function replacelinks($content)
{
    if ($_GET['xurl'])
    {
        $referer = $_SERVER['HTTP_REFERER'];
        $myurl = get_option('siteurl');
        //Just a security measure
        $m1 = strpos(strtolower(" " . $referer), strtolower($myurl));
        if ($referer == '')
        {
            $m1 = 1;
        }
        $url = $_GET['xurl'];
        $url = urldecode($url);
        //block possible code injection attenpts
        $m2 = strpos(" " . $url, "<");
        $m3 = strpos(" " . $url, "$");
        $m4 = strpos(" " . $url, "'");
        $m5 = strpos(" " . $url, '"');
        global $post;
        $pageurl = get_permalink($post->ID);
        if ($m2 || $m3 || $m4 || $m5)
        {
            return $content;
            //someone is trying to inject a code, return contents without modifications
        }
        if ($m1 == 1)
        {
            $d = get_option("exitpagecontents");
            $c = 10;

            $mypath = get_option('siteurl') . '/wp-content/plugins/' . plugin_basename(dirname
                (__file__));


            $d = str_ireplace("%n%", $c, $d);
            $d = str_ireplace("%link%", $url, $d);

            $content = stripcslashes($d);
            $content .= "<br / ><br / ><p style ='color: red;text-align: center;'><b>Exit page powered by <a href='http://codecanyon.net/item/wordpress-exit-strategy-pro/1573775?ref=AngryByte'> Wordpress Exit Strategy. </a></b></p>";
            return $content;
        }


    }


    preg_match_all('/<a\s[^>]*href\s*=\s*([\"\']??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU',
        $content, $matches);
    $matches = $matches[0];
    global $post;
    $pageurl = get_permalink($post->ID);
    $myurl = get_option('siteurl');
    $serv = $_SERVER['PHP_SELF'];
    foreach ($matches as $match)
    {
        $qref = strpos($match, $myurl);
        $qref2 = strpos($match, "http");

        if (($qref == '') && ($qref2 != ''))
        {
        
            preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $match,
                $xurl);
            $xurl = $xurl[0][0];
            if (strpos($pageurl, "?"))
            {
                $xurl2 = $pageurl . "&xurl=" . urlencode($xurl);

            } else
            {
                $xurl2 = $pageurl . "?xurl=" . urlencode($xurl);

            }
            $newlink = str_replace($xurl, $xurl2, $match);
            if (!strpos($newlink, "nofollow"))
            {
                $newlink = str_replace("<a ", '<a rel="nofollow" ', $newlink);
            }
            $content = str_ireplace($match, $newlink, $content);
        }
    }

    return $content;
}

?>