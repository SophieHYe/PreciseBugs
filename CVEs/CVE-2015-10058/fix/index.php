<?php
require 'config.php';

$lang = (isset($_GET['lang'])) ? htmlspecialchars($_GET['lang']) : 'en';
if ( !array_key_exists($lang, $dbs)) {
    $err = "The language '$lang' has not yet been included. Please lodge an issue.";
}
$suffix = $lang=='en' ? '' : '_'.$lang;

?><!doctype html>
<html class="no-js" lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Wikisource (All Validated Works)</title>
        <link rel="stylesheet" href="//tools-static.wmflabs.org/cdnjs/ajax/libs/foundation/5.4.7/css/foundation.min.css" />
        <link rel="stylesheet" href="css/style.css" />
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    </head>
    <body>

        <div class="row">
            <div class="large-12 columns">

                <ul class="inline-list">
                    <li>Languages:</li>
                    <?php foreach ($dbs as $l => $info): ?>
                    <li>
                    <?php if ($lang == $l): ?>
                        <strong><?php echo $l ?></strong>
                    <?php else: ?>
                        <a href="?lang=<?php echo $l ?>"><?php echo $l ?></a>
                    <?php endif ?>
                    </li>
                    <?php endforeach ?>
                </ul>

                <h1>Wikisource <small>(All Validated Works)</small></h1>

                <?php if (isset($err)): ?>
                <p class="alert-box alert"><?php echo $err ?></p>
                <?php endif ?>

                <p>
                    This page presents the categorisation of the <span id="total-works">x</span> works
                    on the <a href="https://<?php echo $lang ?>.wikisource.org/"><?php echo strtoupper($lang) ?> Wikisource</a>
                    that are categorised, backed by scans,
                    and have been validated (i.e. proofread by at least two contributors).
                </p>
                <p class="loading"><img src="img/loading.gif" /> Categories loading, please wait...</p>
                <ol class="c hide" id="catlist"></ol>
                <p>
                    This list was last updated at:
                    <span id="last-mod">y</span> <a href="http://time.is/UTC">UTC</a>.
                </p>
                <p>
                    For more information please see
                    <a href="https://github.com/samwilson/wikisource-cat-browser">the code</a> on Github
                    or contact <a href="https://en.wikisource.org/wiki/User:Samwilson">User:Samwilson</a>.
                    The above data is available in
                    <a href="works<?php echo $suffix ?>.json"><tt>works<?php echo $suffix ?>.json</tt></a>
                    and <a href="categories<?php echo $suffix ?>.json"><tt>categories<?php echo $suffix ?>.json</tt></a>.
                </p>
            </div>
        </div>

        <div class="hide">
            <img src='https://upload.wikimedia.org/wikipedia/commons/thumb/d/d5/EPUB_silk_icon.svg/15px-EPUB_silk_icon.svg.png' />
        </div>
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/foundation/5.4.7/js/foundation.min.js"></script>
        <script src="js/app.js"></script>
        <script>
            var lang = '<?php echo $lang ?>';
        </script>
    </body>
</html>
