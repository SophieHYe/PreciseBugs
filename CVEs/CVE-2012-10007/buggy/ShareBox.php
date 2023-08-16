
<div class="buddystream_sharebox_box">
<h3>ShareBox</h3>
<br/>

<blockquote>
    <h4><i>"<?php echo $_GET['content']; ?>"</i></h4>
</blockquote>
<br/>

<?php

$content   = $_GET['content'];
$link      = $_GET['link'];
$arrShares = explode(',', $_GET['shares']);

foreach($arrShares as $share){

    if($share == 'facebook'){
        echo '<a href="http://www.facebook.com/share.php?u=' . urlencode($link) . '&t=' . urlencode($content) . '" class="buddystream_sharebutton facebook">Facebook share</a> ';
        echo '<iframe src="http://www.facebook.com/plugins/like.php?href='.$link.'&amp;&amp;action=like" scrolling="no" frameborder="0" allowTransparency="true" style="border:none; overflow:hidden; margin:0px; padding:0px; height:20px"></iframe> ';
    }

    if($share == 'twitter'){
        echo '<a href="http://twitter.com/home?status=' . urlencode($content) . ' ' . urlencode($link) . '" class="buddystream_sharebutton twitter">Share on Twitter</a> ';
    }

    if($share == 'linkedin'){
        echo '<script src="http://platform.linkedin.com/in.js" type="text/javascript"></script><script type="IN/Share" data-url="'.$link.'"></script> ';
    }

    if($share == 'googleplus'){
        echo '<script type="text/javascript" src="https://apis.google.com/js/plusone.js"></script>
        <g:plusone size="medium" annotation="none" href="'.$url.'"></g:plusone>
        <meta itemprop="description" content="'.$content.'">';
    }
}
    
?>
</div>