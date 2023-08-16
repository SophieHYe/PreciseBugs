<?php
/*
 * ScratchSig extension for MediaWiki
 * Renders <scratchsig> tag with profile image from scratch.mit.edu
 *
 * Copyright 2013, Tim Radvan
 * MIT Licensed
 * http://opensource.org/licenses/MIT
 *
 */

if (!defined('MEDIAWIKI')) {
    die();
}


// Don't dump DOM errors onto page

error_reporting(0);


// Hooks

$wgExtensionFunctions[] = 'sigSetup';
$wgHooks['ParserFirstCallInit'][] = 'sigParserInit';


// Temporary cache of avatar image URLs

global $sig_imageUrls;
$sig_imageUrls = array();


// Hook callback function into parser

function sigParserInit (Parser $parser) {
    // Register <scratchsig> tag
    $parser->setHook('scratchsig', 'sigRenderTag');
    return true;
}


// Fetch avatar thumbnail url for user from site api

function sigFetchProfile ($username) {
    // Fetch page
    $data = file_get_contents("http://scratch.mit.edu/site-api/users/all/$username/");
    $json = json_decode($data, $assoc=true);
    $pk = $json['user']['pk'];
    $image_url = "http://cdn.scratch.mit.edu/get_image/user/{$pk}_18x18.png";
    return $image_url;
}


// Return the url of the avatar's profile image
// Fetches it if not cached in database

function sigGetAvatarUrl ($username) {
    global $sig_imageUrls;
    if (!isset($sig_imageUrls[$username])) {
        $sig_imageUrls[$username] = sigFetchProfile($username);
    }
    return $sig_imageUrls[$username];
}


// Called to output HTML for <scratchsig> tag

function sigRenderTag ($input, array $args, Parser $parser, PPFrame $frame) {
    $username = $input;

    $img_url = sigGetAvatarUrl($username);

    $o =  '<br>'
        . '<span class="scratch-sig">'
        . '<a href="/wiki/User:'.$username.'">'
        . '<img src="' . $img_url . '" width="18px" height="18px">'
        . '</a>'
        . ' '
        . '<a href="/wiki/User:'.$username.'">'
        . '<b>'.$username.'</b>'
        . '</a>'
        . ' '
        . '('
        . '<a href="/wiki/User_Talk:'.$username.'">talk</a>'
        . ' | '
        . '<a href="/wiki/Special:Contributions/'.$username.'">contribs</a>'
        . ')'
        . '</span>';

    return $o;
}


// Make wiki load resources

function sigSetup () {
    global $wgOut;
    $wgOut->addModules('ext.scratchSig');
}


// Define resources

$wgResourceModules['ext.scratchSig'] = array(
    'styles' => 'scratchsig.css',

    'localBasePath' => __DIR__,
    'remoteExtPath' => 'mw-ScratchSig2'
);

