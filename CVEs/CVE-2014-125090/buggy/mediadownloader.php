<?php
/*
Plugin Name: Media Downloader
Plugin URI: http://ederson.peka.nom.br
Description: Media Downloader plugin lists MP3 files from a folder by replacing the [media] smarttag.
Version: 0.1.992
Author: Ederson Peka
Author URI: http://ederson.peka.nom.br
*/

// Possible encodings
$mdencodings = array( 'UTF-8', 'ISO-8859-1', 'ISO-8859-15', 'cp866', 'cp1251', 'cp1252', 'KOI8-R', 'BIG5', 'GB2312', 'BIG5-HKSCS', 'Shift_JIS', 'EUC-JP' );
$md_comp_encs = array();
foreach ( $mdencodings as $mdenc ) if ( 'ISO-8859-1'!=$mdenc ) $md_comp_encs[] = 'ISO-8859-1 + '.$mdenc;
$mdencodings = array_merge( $mdencodings, $md_comp_encs );
// Possible fields by which file list should be sorted,
// and respective sorting functions
$mdsortingfields = array(
    'none' => null,
    'title' => 'orderByTitle',
    'file date' => 'orderByFileDate',
    'recording dates' => 'orderByRecordingDates',
    'year' => 'orderByYear',
    'track number' => 'orderByTrackNumber',
    'album' => 'orderByAlbum',
    'artist' => 'orderByArtist',
    'file size' => 'orderByFileSize',
    'sample rate' => 'orderBySampleRate',
);
// Settings and respective sanitize functions
$mdsettings = array(
    'mp3folder' => 'sanitizeRDir',
    'mediaextensions' => 'sanitizeMediaExtensions',
    'sortfiles' => 'sanitizeSortingField',
    'reversefiles' => 'sanitizeBoolean',
    'showtags' => null,
    'customcss' => null,
    'removeextension' => 'sanitizeBoolean',
    'showcover' => 'sanitizeBoolean',
    'packageextensions' => null,
    'embedplayer' => 'sanitizeBoolean',
    'embedwhere' => 'sanitizeBeforeAfter',
    'tagencoding' => 'sanitizeTagEncoding',
    'filenameencoding' => 'sanitizeTagEncoding',
    'cachedir' => 'sanitizeWDir',
    'scriptinfooter' => 'sanitizeBoolean',
    'handlefeed' => 'sanitizeBoolean',
    'overwritefeedlink' => 'sanitizeURL',
    'calculateprefix' => 'sanitizeBoolean',
);
// Possible ID3 tags
$mdtags = array( 'title', 'artist', 'album', 'year', 'recording_dates', 'genre', 'comment', 'track_number', 'bitrate', 'filesize', 'filedate', 'directory', 'file', 'sample_rate' );

// Markup settings and respective sanitize functions
$mdmarkupsettings = array(
    'covermarkup' => null,
    'packagetitle' => null,
    'packagetexts' => null,
    'downloadtext' => null,
    'playtext' => null,
    'stoptext' => null,
    'replaceheaders' => null,
    'markuptemplate' => 'sanitizeMarkupTemplate',
);
// Possible markup templates
$mdmarkuptemplates = array(
    'definition-list' => '<strong>"DL" mode:</strong> One table cell containing a definition list (one definition term for each tag)',
    'table-cells' => '<strong>"TR" mode:</strong> One table cell for each tag'
);

// Default player colors
$mdembedplayerdefaultcolors = array(
    'bg' => 'E7E7E7',
    'text' => '333333',
    'leftbg' => 'CCCCCC',
    'lefticon' => '333333',
    'volslider' => '666666',
    'voltrack' => 'FFFFFF',
    'rightbg' => 'B4B4B4',
    'rightbghover' => '999999',
    'righticon' => '333333',
    'righticonhover' => 'FFFFFF',
    'track' => 'FFFFFF',
    'loader' => 'A2CC39',
    'border' => 'CCCCCC',
    'tracker' => 'DDDDDD',
    'skip' => '666666',
);

// Pre-2.6 compatibility ( From: http://codex.wordpress.org/Determining_Plugin_and_Content_Directories )
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

// MarkDown, used for text formatting
if( !function_exists( 'Markdown' ) ) include_once( "markdown/markdown.php" );

// Friendly file size
if( !function_exists( 'byte_convert' ) ){
    function byte_convert( $bytes ){
        $symbol = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );

        $exp = 0;
        $converted_value = 0;
        if( $bytes > 0 )
        {
          $exp = floor( log($bytes)/log(1024) );
          $converted_value = ( $bytes/pow(1024,floor($exp)) );
        }

        return sprintf( '%.2f '.$symbol[$exp], $converted_value );
    }
}

// Friendly frequency size
if( !function_exists( 'hertz_convert' ) ){
    function hertz_convert( $hertz ){
        $symbol = array( 'Hz', 'kHz', 'MHz', 'GHz', 'THz', 'PHz', 'EHz', 'ZHz', 'YHz' );

        $exp = 0;
        $converted_value = 0;
        if( $hertz > 0 ) {
          $exp = floor( log( $hertz, 10 ) / 3 );
          $converted_value = ( $hertz / pow( 1000 , floor( $exp ) ) );
        }

        return sprintf( '%.2f '.$symbol[$exp], $converted_value );
    }
}

// Scans an array of strings searching for a common prefix in all items
function calculatePrefix($arr){
    $prefix = '';
    if ( get_option( 'calculateprefix' ) && count( $arr ) > 1 ) {
        $prefix = strip_tags( array_pop( $arr ) );
        foreach ( $arr as $i ) {
            for ( $c=1; $c<strlen($i); $c++ ) {
                if ( strncasecmp( $prefix, $i, $c ) != 0 ) break;
            }
            $prefix = substr( $prefix, 0, $c-1 );
        }
    }
    return $prefix;
}

function replaceUnderscores( $t ) {
    if ( $t && false === strpos(' ', $t) ) {
        //if ( false === strpos('_', $t) ) $t = str_replace( '-', '_', $t );
        $t = preg_replace( '/_(_+)/i', ' - ', $t );
        $t = str_replace( '_', ' ', $t );
    }
    return $t ;
}

function get_replaceheaders() {
    $replaceheaders = array();
    $arrreplaceheaders = explode( "\n", trim( get_option( 'replaceheaders' ) ) );
    foreach ( $arrreplaceheaders as $line ) {
        $arrline = explode( ':', trim( $line ) );
        if ( count( $arrline ) >= 2 ) $replaceheaders[ strtolower( trim( array_shift( $arrline ) ) ) ] = implode( ':', $arrline );
    }
    return $replaceheaders;
}

function md_mediaAllExtensions() {
    return array( 'mp3', 'mp2', 'mp1', 'ogg', 'wma', 'm4a', 'aac', 'ac3', 'flac', 'ra', 'rm', 'wav', 'aiff', 'cda', 'mid', 'avi', 'webm', 'asf', 'wmv', 'mpg', 'avi', 'qt', 'mov', 'ogv', 'mp4', '3gp' );
}
function md_mediaExtensions() {
    $ret = get_option( 'mediaextensions' );
    if ( ! ( is_array( $ret ) && count( $ret ) ) ) $ret = array( 'mp3' );
    return $ret;
}

function md_packageExtensions() {
    $ret = explode( ',', get_option( 'packageextensions' ) );
    foreach ( $ret as &$r ) $r = str_replace( '.', '', $r );
    return array_filter( $ret );
}

// Searches post content for our smarttag and do all the magic
function listMedia( $t ){
    global $mdtags, $tagvalues, $mdsortingfields, $mdmarkuptemplates;
    $errors = array();

    // MP3 folder
    $mdir = '/' . get_option( 'mp3folder' );
    // MP3 folder URL
    if ( function_exists( 'switch_to_blog' ) ) switch_to_blog(1);
    $murl = get_option( 'siteurl' ) . $mdir;
    if ( function_exists( 'restore_current_blog' ) ) restore_current_blog();
    // MP3 folder relative URL
    $mrelative = str_replace('http'.(isset($_SERVER['HTTPS'])?'s':'').'://','',$murl); $mrelative = explode( '/', $mrelative ); array_shift($mrelative); $mrelative = '/'.implode('/', $mrelative);
    $mpath = ABSPATH . substr($mdir, 1);
    
    // Should we show the 'cover' file ('folder.jpg')?
    $mshowcover = get_option( 'showcover' );

    // Player position (before or after download link)
    $membedwhere = get_option( 'embedwhere' );

    // Should we re-encode the tags?
    $mdoencode = get_option( 'tagencoding' );
    if ( !$mdoencode ) $mdoencode = 'UTF-8';
    $mdoencode = array_pop( explode( ' + ', $mdoencode ) );

    // Should we re-encode the file names?
    $mdofnencode = get_option( 'filenameencoding' );
    if ( !$mdofnencode ) $mdofnencode = 'UTF-8';
    $mdofnencode = array_pop( explode( ' + ', $mdofnencode ) );
    
    // How should we sort the files?
    $msort = get_option( 'sortfiles' );
    // "Backward compatibilaziness": it used to be a boolean value
    if ( isset( $msort ) && !array_key_exists( $msort . '', $mdsortingfields ) ) $msort = 'title';

    // Should the sorting be reversed?
    $mreverse = ( get_option( 'reversefiles' ) == true );

    // Which tags to show?
    $option_showtags = str_replace( 'comments', 'comment', get_option( 'showtags' ) );
    $mshowtags = array_intersect( explode( ',', $option_showtags ), $mdtags );
    // If none, shows the first tag (title)
    if ( !count($mshowtags) ) $mshowtags = array( $mdtags[0] );
    
    // Markup options
    $covermarkup = get_option( 'covermarkup' );
    $downloadtext = get_option( 'downloadtext' );
    $playtext = get_option( 'playtext' );
    $stoptext = get_option( 'stoptext' );
    $replaceheaders = get_replaceheaders();
    $markuptemplate = get_option( 'markuptemplate' );
    if ( !sanitizeMarkupTemplate( $markuptemplate ) ) $markuptemplate = array_shift( array_keys( $mdmarkuptemplates ) ); // Default: first option

    // Searching for our smarttags
    $t = preg_replace( '/<p>\[media:([^\]]*)\]<\/p>/i', '[media:$1]', $t );
    preg_match_all( '/\[media:([^\]]*)\]/i', $t, $matches );
    // Any?
    if ( count( $matches ) ) {
        // Each...
        foreach ( $matches[1] as $folder ) {
            $cover = '';
            // Removing paragraph
            $t = str_replace('<p>[media:'.$folder.']</p>', '[media:'.$folder.']', $t);
            // Initializing variables
            $ihtml = '';
            $iall = array();
            $ifiles = array();
            $ititles = array();
            $ipath = $mpath . '/' . $folder;
            // Populating arrays with respective files
            if ( is_dir( $ipath ) ) {
                $folderalone = $folder;
                if ( is_readable( $ipath ) ) {
                    $idir = dir( $ipath );
                    while ( false !== ( $ifile = $idir->read() ) ) if ( !is_dir( $ifile ) ) {
                        $arrfile = explode( '.', $ifile );
                        if ( count( $arrfile ) > 1 ) {
                            $fext = array_pop( $arrfile );
                        } else {
                            $fext = '.none';
                        }
                        if ( in_array( $fext, md_mediaExtensions() ) ) {
                            $ifiles[] = $ifile;
                        } else {
                            if ( !array_key_exists( $fext, $iall ) ) $iall[$fext] = array();
                            $iall[$fext][] = $ifile;
                        }
                        if ( strtolower( str_ireplace( '.jpeg', '.jpg', $ifile ) ) == 'folder.jpg' ) $cover = $ifile;
                    }
                } else {
                    $errors[] = sprintf( _md( 'Could not read: %1$s' ), $ipath );
                }
            } elseif ( file_exists( $ipath ) && is_readable( $ipath ) ) {
                $folderalone = implode( '/', array_slice( explode( '/', $folder ), 0, -1 ) );
                $apath = explode( '/', $ipath );
                $ifile = array_pop( $apath );
                $arrfile = explode( '.', $ifile );
                if ( count( $arrfile ) > 1 ) {
                    $fext = array_pop( $arrfile );
                } else {
                    $fext = '.none';
                }
                if ( in_array( $fext, md_mediaExtensions() ) ) {
                    $ifiles[] = $ifile;
                } else {
                    if ( !array_key_exists( $fext, $iall ) ) $iall[$fext] = array();
                    $iall[$fext][] = $ifile;
                }
                $ipath = implode( '/', $apath );
            }
            // Encoding folder name
            $pfolder = array_filter( explode( '/', $folderalone ) );
            foreach( $pfolder as $p ) $p = rawurlencode( $p );
            $ufolder = implode( '/', $pfolder );
            if ( $ufolder ) {
                $afolder = explode( '/', $ufolder );
                foreach ( $afolder as &$alevel ) $alevel = rawurlencode( $alevel );
                unset( $alevel );
                $ufolder = implode( '/', $afolder );
            }
            
            $countextra = 0;
            foreach ( md_packageExtensions() as $pext ) $countextra += count( $iall[$pext] );
            if ( ( $mshowcover && $cover ) || $countextra ) {
                $ihtml .= '<div class="md_albumInfo">';

                if ( $mshowcover && $cover ) {
                    $coversrc = network_home_url($mdir) . '/' . ( $ufolder ? $ufolder . '/' : '' ) . $cover;
                    $icovermarkup = $covermarkup ? $covermarkup : '<img class="md_coverImage" src="[coverimage]" alt="' . _md( 'Album Cover' ) . '" />';
                    $ihtml .= str_replace( '[coverimage]', $coversrc, $icovermarkup );
                }

                // If any "extra" files, inserting an extra table
                // (this was very case specific and remained here)
                if ( $countextra ) {
                    $packagetitle = get_option( 'packagetitle' );
                    $packagetexts = get_option( 'packagetexts' );
                    if ( !$packagetexts ) $packagetexts = array();
                    $ihtml .= '<div class="md_wholebook">';
                    if ( $packagetitle ) $ihtml .= '<h3 class="md_wholebook_title">' . $packagetitle . '</h3>';
                    $afolder = explode( '/', $folderalone );
                    for ( $a=0; $a<count($afolder); $a++ ) $afolder[$a] = rawurlencode( $afolder[$a] );
                    $cfolder = implode( '/', $afolder );
                    $ihtml .= '<ul class="md_wholebook_list">';
                    foreach ( md_packageExtensions() as $pext ) {
                        $cpf = 0; if ( count( $iall[$pext] ) ) foreach( $iall[$pext] as $pf ) {
                            $cpf++;
                            $ptext = _md( 'Download ' . strtoupper( $pext ) );
                            if ( array_key_exists( $pext, $packagetexts ) && $packagetexts[$pext] ) {
                                $ptext = str_replace( '[filename]', $pf, $packagetexts[$pext] );
                            }
                            $ihtml .= '<li class="d' . strtoupper(substr($pext,0,1)) . substr($pext,1) . '"><a href="'.$mrelative.($mrelative!='/'?'/':'').($cfolder).'/'.rawurlencode( $pf ).'" title="' . esc_attr( $pf ) . '">'.$ptext.(count($iall[$pext])>1?' ('.$cpf.')':'').'</a></li>' ;
                        }
                    }
                    $ihtml .= '</ul>';
                    $ihtml .= '</div>';
                }

                $ihtml .= '</div>';
            }

            // Any MP3 file?
            if ( count( $ifiles ) ) {
                // Calculating file "prefixes"
                $prefix = calculatePrefix( $ifiles );
                $hlevel = explode( '/', $folder ); $hlevel = array_pop( $hlevel );

                // Initializing array of tag values
                $tagvalues = array();
                foreach ( $mshowtags as $mshowtag ) $tagvalues[$mshowtag] = array();
                $alltags = array();
                foreach ( $ifiles as $ifile ) {
                    $ifile = explode( '.', $ifile );
                    $iext = array_pop( $ifile );
                    $ifile = implode( '.', $ifile );
                    // Getting ID3 info
                    $finfo = mediadownloaderFileInfo( $mrelative.'/'.$folderalone.'/'.$ifile, $iext );
                    // Loading all possible tags
                    $ftags = array();
                    foreach ( array( 'id3v2', 'quicktime', 'ogg', 'asf', 'flac', 'real', 'riff', 'ape', 'id3v1', 'comments' ) as $poss ) {
                        if ( is_array( $finfo['tags'] ) && array_key_exists( $poss, $finfo['tags'] ) ) {
                            $ftags = array_merge( $finfo['tags'][$poss], $ftags );
                            if ( array_key_exists( 'comments', $finfo['tags'][$poss] ) ) {
                                $ftags = array_merge( $finfo['tags'][$poss]['comments'], $ftags );
                            }
                        }
                    }
                    $ftags['bitrate'] = array( floatval( $finfo['audio']['bitrate'] ) / 1000 . 'kbps' );
                    $ftags['filesize'] = array( byte_convert( $finfo['filesize'] ) );
                    $ftags['filedate'] = array( date_i18n( get_option('date_format'), filemtime( $finfo['filepath'] . '/' . $finfo['filename'] ) ) );
                    $ftags['directory'] = array( $hlevel );
                    $ftags['file'] = array( $ifile );
                    $ftags['sample_rate'] = array( hertz_convert( intval( '0' . $finfo['audio']['sample_rate'] ) ) );
                    unset( $finfo );
                    $alltags[$ifile] = $ftags;
                    // Populating array of tag values with all tags
                    foreach ( $mdtags as $mshowtag )
                        if ( 'comment' == $mshowtag ) {
                            if ( array_key_exists( 'text', $ftags ) && is_array( $ftags['text'] ) && trim( strip_tags( $ftags['text'][0] ) ) ) {
                                $tagvalues[$mshowtag][$ifile.'.'.$iext] = $ftags['text'][0];
                            } else {
                                $tagvalues[$mshowtag][$ifile.'.'.$iext] = Markdown( $ftags[$mshowtag][0] );
                            }
                        } else {
                            $tagvalues[$mshowtag][$ifile.'.'.$iext] = $ftags[$mshowtag][0];
                        }
                    unset( $ftags );
                }
                // Calculating tag "prefixes"
                $tagprefixes = array();
                foreach ( $mshowtags as $mshowtag )
                    if ( 'file' == $mshowtag || 'title' == $mshowtag )
                        $tagprefixes[$mshowtag] = calculatePrefix( $tagvalues[$mshowtag] );
                // If set, sorting array
                if ( $msort != 'none' ) {
                    sort( $ifiles );
                    uasort( $ifiles, $mdsortingfields[$msort] );
                }
                // If set, reversing array
                if ( $mreverse ) $ifiles = array_reverse( $ifiles );

                $tablecellsmode_header = '';
                $tablecellsmode_firstfile = true;
                // Building markup for each file...
                foreach ( $ifiles as $ifile ) {
                    $ifile = explode( '.', $ifile );
                    $iext = array_pop( $ifile );
                    $ifile = implode( '.', $ifile );
                    $ititle = '';
                    // Each tag list item
                    foreach ( $mshowtags as $mshowtag ) {
                        $tagvalue = $tagvalues[$mshowtag][$ifile.'.'.$iext];
                        if ( '' == $tagvalue ) {
                            $tagvalue = '&nbsp;';
                        } else {
                            // Removing "prefix" of this tag
                            if ( '' != $tagprefixes[$mshowtag] )
                                $tagvalue = str_replace( $tagprefixes[$mshowtag], '', $tagvalue );
                            // $tagvalue = str_replace( $prefix, '', $tagvalue ); // Causing weird behavior in some cases
                            // Cleaning...
                            $tagvalue = replaceUnderscores( $tagvalue );
                            // Encoding...
                            if ( 'file' == $mshowtag || 'directory' == $mshowtag ) {
                                if ( $mdofnencode != 'UTF-8' ) $tagvalue = iconv( $mdofnencode, 'UTF-8', $tagvalue );
                            } elseif ( 'recording_dates' == $mshowtag ) {
                                if ( $tagtime = strtotime( $tagvalue ) ) {
                                    $tagvalue = date_i18n( get_option('date_format'), $tagtime );
                                } else {
                                    $tagvalue = '';
                                }
                            } elseif ( $mdoencode != 'UTF-8' ) {
                                $tagvalue = iconv( $mdoencode, 'UTF-8', $tagvalue );
                            }
                        }
                        // Item markup
                        $columnheader = ucwords( _md( $mshowtag ) );
                        if ( array_key_exists( $mshowtag, $replaceheaders ) ) $columnheader = $replaceheaders[$mshowtag];
                        if ( 'table-cells' == $markuptemplate ) {
                            // For "table cells" markup template,
                            // we store a "row with headers", so it
                            // just needs to run once
                            if ( $tablecellsmode_firstfile ) {
                                $tablecellsmode_header .= '<th class="mdTag'.$mshowtag.'">'.$columnheader.'</th>' ;
                            }
                            $ititle .= '<td class="mdTag'.$mshowtag.'">'.$tagvalue.'</td>' ;
                        } elseif ( 'definition-list' == $markuptemplate )  {
                            $ititle .= '<dt class="mdTag'.$mshowtag.'">'.$columnheader.':</dt>' ;
                            $ititle .= '<dd class="mdTag'.$mshowtag.'">'.$tagvalue.'</dd>' ;
                        }
                    }
                    // List markup (if any item)
                    if ( '' != $ititle ) {
                        if ( 'definition-list' == $markuptemplate ) {
                            $ititle = '<dl class="mdTags">' . $ititle . '</dl>' ;
                        }
                    }
                    $ititles[$ifile] = $ititle ;
                    // "Row with headers" is stored already,
                    // so skip the task next iteration
                    $tablecellsmode_firstfile = false;
                }

                // Building general markup
                $tableClass = array( 'mediaTable' );
                if ( TRUE == get_option( 'embedplayer' ) ) $tableClass[] = 'embedPlayer';
                $tableClass[] = 'embedpos' . $membedwhere ;
                $ihtml .= '<table class="' . implode( ' ', $tableClass ) . '">' . "\n";
                $ihtml .= "<thead>\n<tr>\n";
                if ( 'table-cells' == $markuptemplate ) {
                    $ihtml .= $tablecellsmode_header;
                } elseif ( 'definition-list' == $markuptemplate ) {
                    $ihtml .= "\n" . '<th class="mediaTitle">&nbsp;</th>' . "\n";
                }
                $downloadheader = _md( 'Download' );
                if ( array_key_exists( 'download', $replaceheaders ) ) $downloadheader = $replaceheaders['download'];
                $ihtml .= '<th class="mediaDownload">'.$downloadheader.'</th>
</tr>
</thead>
<tbody>';


                // Each file...
                foreach ( $ifiles as $ifile ) {
                    $ifile = explode( '.', $ifile );
                    $iext = array_pop( $ifile );
                    $ifile = implode( '.', $ifile );
                    // File name
                    $showifile = $ifile ;
                    // Removing prefix
                    if ( array_key_exists( 'file', $tagprefixes ) )
                        $showifile = str_replace( $tagprefixes['file'], '', $showifile );
                    // Cleaning
                    $showifile = replaceUnderscores( $showifile );
                    $alltags[$ifile]['file'][0] = $showifile;
                    // Download text
                    $idownloadtext = $downloadtext ? $downloadtext : 'Download: [file]';
                    // Play, Stop, Title and Artist texts (for embed player)
                    $iplaytext = $playtext ? $playtext : 'Play: [file]';
                    $istoptext = $stoptext ? $stoptext : 'Stop: [file]';
                    $ititletext = $showifile;
                    $iartisttext = '';
                    foreach ( $mdtags as $mdtag ) {
                        if ( !array_key_exists( $mdtag, $alltags[$ifile] ) ) $alltags[$ifile][$mdtag] = array( '' );
                        $tagvalue = $alltags[$ifile][$mdtag][0];
                        if ( 'file' == $mdtag || 'directory' == $mdtag ) {
                            if ( $mdofnencode != 'UTF-8' ) $tagvalue = iconv( $mdofnencode, 'UTF-8', $tagvalue );
                        } elseif ( $mdoencode != 'UTF-8' ) {
                            $tagvalue = iconv( $mdoencode, 'UTF-8', $tagvalue );
                        }
                        // Replacing wildcards
                        $idownloadtext = str_replace( '[' . $mdtag . ']', $tagvalue, $idownloadtext );
                        $iplaytext = str_replace( '[' . $mdtag . ']', $tagvalue, $iplaytext );
                        $istoptext = str_replace( '[' . $mdtag . ']', $tagvalue, $istoptext );
                        // If "title", populate "Title text"
                        if ( 'title' == $mdtag ) $ititletext = $tagvalue;
                        // If "artist", populate "Artist text"
                        if ( 'artist' == $mdtag && $tagvalue ) $iartisttext = str_replace( '-', '[_]', $tagvalue ) . ' - ';
                    }
                    
                    // Getting stored markup
                    $ititle = $ititles[$ifile];

                    // $ititle = str_replace( $prefix, '', $ititle ); // Causing weird behavior in some cases

                    // Markup
                    // 20100107 - I took it away: strtoupper( $hlevel )
                    $ihtml .= '<tr class="mdTags">'."\n" ;
                    if ( 'table-cells' == $markuptemplate ) {
                        // a group of "td's"
                        $ihtml .= $ititle . "\n";
                    } elseif ( 'definition-list' == $markuptemplate ) {
                        // one "td" with a "dl" inside
                        $ihtml .= '<td class="mediaTitle">'.$ititle.'</td>'."\n" ;
                    }
                    // Play, Stop and Title (concatenated with Artist) texts
                    // all packed in rel attribute, for embed player to read
                    // and do its black magic
                    $irel = array();
                    if ( $iplaytext ) $irel[] = 'mediaDownloaderPlayText:' . htmlentities( $iplaytext, ENT_COMPAT, 'UTF-8' );
                    if ( $istoptext ) $irel[] = 'mediaDownloaderStopText:' . htmlentities( $istoptext, ENT_COMPAT, 'UTF-8' );
                    $ititletext = $iartisttext . $ititletext;
                    if ( $ititletext ) $irel[] = 'mediaDownloaderTitleText:' . htmlentities( $ititletext, ENT_COMPAT, 'UTF-8' );
                    $irel = implode( ';', $irel );
                    $ihtml .= '<td class="mediaDownload"><a href="'.network_home_url($mdir).'/'.($ufolder?$ufolder.'/':'').rawurlencode( $ifile ).'.'.$iext.'" title="' . htmlentities( $showifile, ENT_COMPAT, 'UTF-8' ) . '" ' . ( $irel ? 'rel="' . $irel . '"' : '' ) . ' id="mdfile_' . sanitize_title( $ifile ) . '">'.$idownloadtext.'</a></td>'."\n" ;
                    $ihtml .= '</tr>'."\n" ;
                }
                $ihtml .= '</tbody></table>'."\n" ;

            }
            
            if ( count( $errors ) ) {
                $errorHtml = '<div class="mediaDownloaderErrors">';
                foreach ( $errors as $error ) $errorHtml .= '<p><strong>' . _md( 'Error:' ) . '</strong> ' . $error . '</p>';
                $errorHtml .= '</div>';
                $ihtml .= $errorHtml;
            }
            // Finally, replacing our smart tag
            $t = str_replace( '[media:'.$folder.']', $ihtml, $t );
        }
    }
    return $t ;
}
// To sort file array by some tag
function orderByTag( $a, $b, $tag ) {
    if ( !is_array( $tag ) ) $tag = array( $tag );
    global $tagvalues;
    $ret = 0;
    foreach ( $tag as $t ) {
        $ret = strnatcmp( $tagvalues[$t][$a], $tagvalues[$t][$b] );
        if ( 0 != $ret ) break;
    }
    if ( 0 == $ret ) $ret = strnatcmp( $a, $b );
    return $ret;
}
function orderByTitle( $a, $b ) {
    return orderByTag( $a, $b, array( 'title', 'filedate' ) );
}
function orderByFileDate( $a, $b ) {
    return orderByTag( $a, $b, 'filedate' );
}
function orderByRecordingDates( $a, $b ) {
    return orderByTag( $a, $b, 'recording_dates', 'year', 'filedate' );
}
function orderByYear( $a, $b ) {
    return orderByTag( $a, $b, array( 'year', 'track_number', 'filedate' ) );
}
function orderByTrackNumber( $a, $b ) {
    return orderByTag( $a, $b, 'track_number' );
}
function orderByAlbum( $a, $b ) {
    return orderByTag( $a, $b, array( 'album', 'track_number' ) );
}
function orderByArtist( $a, $b ) {
    return orderByTag( $a, $b, array( 'artist', 'album', 'track_number' ) );
}
function orderByFileSize( $a, $b ) {
    return orderByTag( $a, $b, 'filesize' );
}
function orderBySampleRate( $a, $b ) {
    return orderByTag( $a, $b, 'sample_rate' );
}

function md_plugin_dir() {
    $vdir = __DIR__;
    if ( '__DIR__' == $vdir ) $vdir = dirname( __FILE__ );
    return array_shift( explode( DIRECTORY_SEPARATOR, plugin_basename( array_pop( explode( DIRECTORY_SEPARATOR, $vdir ) ) ) ) );
}
function md_plugin_url() {
    return WP_PLUGIN_URL . '/' . md_plugin_dir();
}

function mediadownloader( $t ) {
    if ( !is_feed() || !get_option( 'handlefeed' ) ) :
        $t = listMedia( $t );
        if ( TRUE == get_option( 'removeextension' ) ) {
            $t = preg_replace(
                '/href\=[\\\'\"](.*)'.preg_quote('.mp3').'[\\\'\"]/im',
                "href=\"".WP_PLUGIN_URL."/".md_plugin_dir()."/getfile.php?f=$1\"",
                $t
            );
        };
    elseif ( is_feed() ) :
        $t = preg_replace( '/<p>\[media:([^\]]*)\]<\/p>/i', '<p><small>' . _md( '(See attached files...)' ) . '</small></p>', $t );
    endif;
        
    /* -- CASE SPECIFIC: -- */
    $t = listarCategorias( $t );
    $t = listarCategoriasEx( $t );
    $t = listarIdiomas( $t );
    /* -- END CASE SPECIFIC; -- */
    return $t;
}


function mediadownloaderFileLength( $filename ) {
    // Initialize getID3 engine
    $getID3 = new getID3;
    // Analyze file and store returned data in $ThisFileInfo
    $ThisFileInfo = $getID3->analyze( $filename );
    // Optional: copies data from all subarrays of [tags] into [comment] so
    // metadata is all available in one location for all tag formats
    // metainformation is always available under [tags] even if this is not called
    getid3_lib::CopyTagsToComments( $ThisFileInfo );
}

// Get ID3 tags from file
function mediadownloaderFileInfo( $f, $ext ) {
    // File path
    if ( function_exists( 'switch_to_blog' ) ) switch_to_blog(1);
    $relURL = str_replace( 'http'.(isset($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['SERVER_NAME'], '', get_option( 'siteurl' ) );
    if ( function_exists( 'restore_current_blog' ) ) restore_current_blog();
    if ( stripos( $f, $relURL ) === 0 ) $f = substr( $f, strlen( $relURL ) );
    $f = ABSPATH . $f . '.' . $ext;
    $f = preg_replace( '|/+|ims', '/', $f );

    // Checking cache
    $return = false;
    $hash = md5( $f );
    $cachedir = trim( get_option( 'cachedir' ) );
    $cachefile = ABSPATH . '/' . $cachedir . '/md-' . $hash . '.cache' ;
    if ( $cachedir && is_readable( $cachefile )  && file_exists( $f ) && ( filemtime( $cachefile ) >= filemtime( $f ) ) ) {

        $return = unserialize( file_get_contents( $cachefile ) );
        if ( $return ) return $return;

    }
    if ( !$return ) {

        // include getID3() library (can be in a different directory if full path is specified)
        require_once('getid3/getid3.php');
        // Initialize getID3 engine
        $getID3 = new getID3;
        $mdoencode = get_option( 'tagencoding' );
        $mdoencode = array_shift( explode( ' + ', $mdoencode ) );
        if ( 'UTF-8' != $mdoencode ) $getID3->setOption( array( 'encoding' => $mdoencode ) );
        // Analyze file and store returned data in $ThisFileInfo
        if ( $ThisFileInfo = $getID3->analyze( $f ) ) {
            // Saving cache
            if ( $cachedir && is_writeable( ABSPATH . '/' . $cachedir ) ) file_put_contents( $cachefile, serialize( $ThisFileInfo ) );
        }
        return $ThisFileInfo;
    }
}
// File size
function mediadownloaderFileSize( $f, $ext ){
    if ( 0 === stripos( $f, get_option( 'siteurl' ) ) ) $f = str_replace( get_option( 'siteurl' ), '', $f );
    $f = ABSPATH . substr( $f, 1 ) . '.' . $ext;
    if ( !file_exists( $f ) ) $f = urldecode( $f );
    return filesize( $f );
}
// Extract MP3 links form post content
function mediadownloaderEnclosures( $adjacentmarkup = false ){
    $allmatches = array();
    global $post;
    $cont = listMedia( get_the_content( $post->ID ) );
    foreach ( md_mediaExtensions() as $mext ) {
        $ret = array();
        preg_match_all( '/href=[\\\'"](.*)'.preg_quote('.'.$mext).'[\\\'"]/im', $cont, $matches );
        preg_match_all( '/href=[\\\'"].*getfile\.php\?\=(.*)[\\\'"]/im', $cont, $newmatches );
        // It makes no sense, "there can be only one", but just in case...
        if ( count( $matches ) && count( $matches[1] ) ) $ret = array_unique( array_merge( $matches[1], $newmatches[1] ) );
    
        // Should we get only the MP3 URL's?
        if ( !$adjacentmarkup ) {
            foreach ( $ret as $r ) if ( '/' == substr( $r, 0, 1 ) ) $r = 'http'.(isset($_SERVER['HTTPS'])?'s':'').'://' . $_SERVER['SERVER_NAME'] . $r;
            $allmatches[$mext] = $ret;
        
        // Or get all the markup around them?
        } else {
            $markuptemplate = get_option( 'markuptemplate' );
            $adj = array();
            $tablehead = '';
            // For each MP3 URL...
            foreach ( $ret as $r ) {
                $adj[$r] = $r;
                // Dirty magic to get the markup around it...
                $rarr = explode( $r . '.' . $mext, $cont );
                if ( count( $rarr ) > 1 ) {
                    $line = substr( $rarr[0], strripos( $rarr[0], '<tr class="mdTags">' ) );
                    $line .= substr( $rarr[1], 0, stripos( $rarr[1], '</tr>' ) ) .'</tr>';
                    if ( 'definition-list' == $markuptemplate ) {
                        $line = substr( $line, strripos( $line, '<dl class="mdTags">' ) );
                        $line = substr( $line, 0, stripos( $line, '</dl>' ) ) . '</dl>';
                        $adj[$r] = $line;
                    } elseif ( 'table-cells' == $markuptemplate ) {

                        if ( '' == $tablehead ) {
                            $safe_r = str_replace( array('/', '.', ':', '%', '-'), array('\\/', '\\.', '\\:', '\\%', '\\-'), $r );
                            preg_match_all( '/\<table([^\>]*)\>(.*?)'.$safe_r.'(.*?)\<\/table\>/ims', $cont, $adjtable );
                            if ( count( $adjtable ) && count( $adjtable[0] ) ) {
                                $ftable = $adjtable[0][0];
                                $ftable = substr( $ftable, strripos( $ftable, '<table' ) );
                                $tablehead = substr( $ftable, 0, stripos( $ftable, '</thead>' ) ) . '</thead>';
                            }
                        }

                        $adj[$r] = ($tablehead?$tablehead:'<table>') . '<tbody>' . $line . '</tbody></table>';
                    }
                }
            }
            $allmatches[$mext] = $adj;
        }
    }
    return $allmatches;
} 
// Generate ATOM tags
function mediadownloaderAtom(){
    $t = '';
    $allmatches = mediadownloaderEnclosures();
    foreach ( $allmatches as $mext => $matches ) {
        foreach ( $matches as $m ) {
            //$t.='<link rel="enclosure" title="'.basename($m).'" length="'.mediadownloaderFileSize($m, $mext).'" href="'.WP_PLUGIN_URL.'/media-downloader/getfile.php?f='.urlencode($m).'" type="audio/mpeg" />';
            $t .= '<link rel="enclosure" title="' . basename( $m ) . '" length="' . mediadownloaderFileSize( $m, $mext ) . '" href="' . ( $m . '.' . $mext ) . '" type="audio/mpeg" />';
	    }
	}
    echo $t;
    //return $t;
}
// Generate RSS tags
function mediadownloaderRss(){
    global $post;
    $postdate = strtotime( $post->post_date_gmt );
    $t = '';
    $allmatches = mediadownloaderEnclosures( true );
    foreach ( $allmatches as $mext => $matches ) {
        foreach ( $matches as $m => $adjacentmarkup ) {
            $postdate -= 2;
            //$t.='<enclosure title="'.basename($m).'" url="'.WP_PLUGIN_URL.'/media-downloader/getfile.php?f='.urlencode($m).'" length="'.mediadownloaderFileSize($m, $mext).'" type="audio/mpeg" />';
            //$t .= '<enclosure title="' . basename( $m ) . '" url="' . ( $m . '.' . $mext ) . '" length="' . mediadownloaderFileSize( $m, $mext ) . '" type="audio/mpeg" />';
            $t .= '</item>';
            $t .= '<item>';
            $t .= '<title>' . sprintf( _md( 'Attached file: %1$s - %2$s' ), urldecode( basename( $m ) ), get_the_title($post->ID) ) . '</title>';
            $t .= '<link>' . get_permalink($post->ID) . '#mdfile_' . sanitize_title( basename( urldecode( $m ) ) ) . '</link>';
            $t .= '<description><![CDATA[' . $adjacentmarkup . ']]></description>';
            $t .= '<pubDate>' . date( DATE_RSS, $postdate ) . '</pubDate>';
            $t .= '<guid>' . get_permalink($post->ID) . '#mdfile_' . sanitize_title( basename( urldecode( $m ) ) ) . '</guid>';
            $t .= '<enclosure url="' . ( $m . '.' . $mext ) . '" length="' . mediadownloaderFileSize( $m, $mext ) . '" type="audio/mpeg" />';
	    }
	}
    echo $t;
    //return $t; 
}
  
add_filter( 'the_content', 'mediadownloader' );

if ( get_option( 'handlefeed' ) ) :
    add_action( 'atom_entry', 'mediadownloaderAtom' );
    //add_action( 'rss_item', 'mediadownloaderRss' );
    add_action( 'rss2_item', 'mediadownloaderRss' );
    // Lowering cache lifetime to 4 hours
    add_filter( 'wp_feed_cache_transient_lifetime', create_function('$a','$newvalue = 4*3600; if ( $a < $newvalue ) $a = $newvalue; return $a;') );
endif;

function mediaDownloaderEnqueueScripts() {
    // If any custom css, we enqueue our php that throws this css
    $customcss = trim( get_option( 'customcss' ) );
    if ( '' != $customcss ) {
        wp_register_style( 'mediadownloaderCss', md_plugin_url() . '/css/mediadownloader-css.php' );
        wp_enqueue_style( 'mediadownloaderCss' );
    }

    // Enqueuing JQPlugin (browser plugins detection)
    wp_enqueue_script( 'jqplugin', md_plugin_url() . '/js/jquery.jqplugin.1.0.2.min.js', array('jquery'), date( 'YmdHis', filemtime( dirname(__FILE__) . '/js/jquery.jqplugin.1.0.2.min.js' ) ), get_option( 'scriptinfooter' ) );
    // Enqueuing our javascript
    wp_enqueue_script( 'mediadownloaderJs', md_plugin_url() . '/js/mediadownloader.js', array('jquery'), date( 'YmdHis', filemtime( dirname(__FILE__) . '/js/mediadownloader.js' ) ), get_option( 'scriptinfooter' ) );
    
    // Passing options to our javascript
    add_action( 'get_header', 'mediaDownloaderLocalizeScript' );
}
    
// Passing options to our javascript
function mediaDownloaderLocalizeScript() {
    global $mdembedplayerdefaultcolors;
    $mdembedcolors = array();
    foreach( $mdembedplayerdefaultcolors as $mdcolor => $mddefault ) {
        $mdembedcolors[$mdcolor] = str_replace( '#', '', get_option( $mdcolor . '_embed_color' ) );
        if ( !trim($mdembedcolors[$mdcolor]) ) $mdembedcolors[$mdcolor] = $mddefault;
    }
    $replaceheaders = get_replaceheaders();
    $playheader = _md( 'Play' );
    if ( array_key_exists( 'play', $replaceheaders ) ) $playheader = $replaceheaders['play'];
    wp_localize_script( 'mediadownloaderJs', 'mdEmbedColors', $mdembedcolors );
    wp_localize_script( 'mediadownloaderJs', 'mdStringTable', array(
        'pluginURL' => md_plugin_url() . '/',
        'playColumnText' => $playheader,
        'downloadTitleText' => _md( 'Download:' ),
        'playTitleText' => _md( 'Play:' ),
        'stopTitleText' => _md( 'Stop:' ),
    ) );
}

function mediaDownloaderInit() {
    load_textdomain( 'media-downloader', WP_LANG_DIR . '/mediadownloader/mediadownloader-' . apply_filters( 'plugin_locale', get_locale(), 'media-downloader' ) . '.mo' );
    load_plugin_textdomain( 'media-downloader', false, basename( dirname( __FILE__ ) ) . '/languages' );
    /*
    // I'm testing the lines below to avoid problems with symlinks,
    // but it's not over yet...
    $pdir = array_key_exists( 'SCRIPT_FILENAME', $_SERVER ) ? array_shift( explode( '/wp-', $_SERVER["SCRIPT_FILENAME"] ) ) . '/wp-content/plugins/media-downloader' : dirname( plugin_basename( __FILE__ ) );
    load_plugin_textdomain( 'media-downloader', false, $pdir . '/languages/' );
    */
    mediaDownloaderEnqueueScripts();
    add_filter( 'set-screen-option', 'mediadownloader_adm_save_options', 10, 3 );
}
add_action( 'init', 'mediaDownloaderInit' );


add_action( 'admin_init', 'md_admin_init' );

function md_admin_init() {
    wp_register_style( 'md-admin-css', md_plugin_url() . '/css/admin.css' );
    wp_register_script( 'md-admin-script', md_plugin_url() . '/js/admin.js' );
}
function md_admin_styles() {
    wp_enqueue_style( 'md-admin-css' );
}
function md_admin_scripts() {
    wp_enqueue_script( 'md-admin-script', false, array( 'jquery' ) );
}

// Our options screens...
add_action( 'admin_menu', 'mediadownloader_menu' );

function mediadownloader_menu() {
    $oppage = add_options_page( 'Media Downloader Options', 'Media Downloader', 'manage_options', 'mediadownloader-options', 'mediadownloader_options' );
    add_action( 'admin_print_styles-' . $oppage, 'md_admin_styles' );
    add_action( 'admin_print_scripts-' . $oppage, 'md_admin_scripts');
    if ( array_key_exists( 'tag-editor', $_GET ) ) add_action( "load-$oppage", 'mediadownloader_adm_add_options' );
}


function mediadownloader_adm_add_options() {
    $option = 'per_page'; 
    $args = array(
        'label' => sprintf( __( 'items (min: %d - max: %d)' ), 10, 100 ),
        'default' => 50,
        'option' => 'mediadownloader_adm_items_per_page'
    );
    add_screen_option( $option, $args );
}
function mediadownloader_adm_save_options( $status, $option, $value ) {
    if ( 'mediadownloader_adm_items_per_page' == $option ) return ( $value >= 10 && $value <= 100 ) ? $value : false;
}

function mediadownloader_options() {
    // Basically, user input forms...
    if ( isset( $_GET['markup-options'] ) ) {
        require_once("mediadownloader-markup-options.php");
    } elseif ( isset( $_GET['more-options'] ) ) {
        require_once("mediadownloader-more-options.php");
    } elseif ( isset( $_GET['tag-editor'] ) ) {
        require_once("mediadownloader-tag-editor.php");
    } else {
        require_once("mediadownloader-options.php");
    }
}

// Add Settings link to plugins - code from GD Star Ratings
// (as seen in http://www.whypad.com/posts/wordpress-add-settings-link-to-plugins-page/785/ )
function mediadownloader_settings_link( $links, $file ) {
    $this_plugin = plugin_basename( array_pop( explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) ) ) );
    if ( $file == $this_plugin ) {
        $settings_link = '<a href="options-general.php?page=mediadownloader-options">' . _md( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'mediadownloader_settings_link', 10, 2 );

// Registering our settings...
add_action( 'admin_init', 'mediadownloader_settings' );

function mediadownloader_settings() {
    global $mdsettings;
    foreach ( $mdsettings as $mdsetting => $mdsanitizefunction ) register_setting( 'md_options', $mdsetting, $mdsanitizefunction );

    global $mdmarkupsettings;
    foreach ( $mdmarkupsettings as $mdmarkupsetting => $mdsanitizefunction ) register_setting( 'md_markup_options', $mdmarkupsetting, $mdsanitizefunction );

    global $mdembedplayerdefaultcolors;
    foreach ( $mdembedplayerdefaultcolors as $mdcolor => $mddefault ) register_setting( 'md_more_options', $mdcolor . '_embed_color', 'sanitizeHEXColor' );
}

function md_self_link() {
	$host = @parse_url( home_url() );
	return esc_url( apply_filters( 'md_self_link', set_url_scheme( 'http://' . $host['host'] . stripslashes($_SERVER['REQUEST_URI']) ) ) );
}
function md_filter_feed_link( $link, $type = 'rss2' ) {
    $overwritefeedlink = ( 'rss2' == $type ) ? trim( get_option( 'overwritefeedlink' ) ) : false;
    return $overwritefeedlink ? $overwritefeedlink : $link;
}
add_filter( 'md_self_link', 'md_filter_feed_link' );
add_filter( 'feed_link', 'md_filter_feed_link' );

// Functions to sanitize user input
function sanitizeRDir( $d ){
    return is_readable( ABSPATH . $d ) ? $d : '' ;
}
function sanitizeWDir( $d ){
    return is_writeable( ABSPATH . $d ) ? $d : '' ;
}
function sanitizeArray( $i, $a ){
    if ( is_array( $i ) ) {
        return array_intersect( $i, $a );
    } else {
        return in_array( $i, $a ) ? $i : '' ;
    }
}
function sanitizeMediaExtensions( $t ) {
    return sanitizeArray( $t, md_mediaAllExtensions() );
}
function sanitizeSortingField( $t ){
    global $mdsortingfields;
    return sanitizeArray( $t, array_keys( $mdsortingfields ) );
}
function sanitizeBeforeAfter( $t ){
    return sanitizeArray( $t, array( 'before', 'after' ) );
}
function sanitizeTagEncoding( $t ){
    global $mdencodings;
    return sanitizeArray( $t, $mdencodings );
}
function sanitizeBoolean( $b ){
    return $b == 1 ;
}
function sanitizeHEXColor( $c ){
    return preg_match( '/^\s*#?[0-9A-F]{3,6}\s*$/i', $c, $m ) ? trim( str_replace( '#', '', $c ) ) : '';
}
function sanitizeMarkupTemplate( $t ){
    global $mdmarkuptemplates;
    return sanitizeArray( $t, array_keys( $mdmarkuptemplates ) );
}
function sanitizeURL( $t ) {
    return filter_var( $t, FILTER_VALIDATE_URL );
}


// I used these functions below to "internationalize" (localize) some strings,
// left them here for "backward compatibilaziness"

function _md( $t ) {
//    if ( function_exists( 'icl_register_string' ) ) {
//        icl_register_string( 'Media Downloader', $t, $t );
//        return icl_t( 'Media Downloader', $t, $t );
//    } else {
        return __( $t, 'media-downloader' );
//    }
}
function _mde( $t ) {
//    if ( function_exists( 'icl_register_string' ) ) {
//        icl_register_string( 'Media Downloader', $t, $t );
//        echo icl_t( 'Media Downloader', $t, $t );
//    } else {
        return _e( $t, 'media-downloader' );
//    }
}
function _mdn( $ts, $tp, $n ) {
//    if ( function_exists( 'icl_register_string' ) ) {
//        icl_register_string( 'Media Downloader', $ts, $ts );
//        icl_register_string( 'Media Downloader', $tp, $tp );
//        if ( 1 != $n ) {
//            return icl_t( 'Media Downloader', $tp, $tp );
//        } else {
//            return icl_t( 'Media Downloader', $ts, $ts );
//        }
//    } else {
        return _n( $ts, $tp, $n, 'media-downloader' );
//    }
}


/* -- CASE SPECIFIC: -- */

add_filter( 'get_previous_post_where', 'corrige_qtrans_excludeUntransPosts' );
add_filter( 'get_next_post_where', 'corrige_qtrans_excludeUntransPosts' );
add_filter( 'posts_where_request', 'corrige_qtrans_excludeUntransPosts' );

function corrige_qtrans_excludeUntransPosts( $where ) {
    if ( function_exists( 'qtrans_getLanguage' ) ) {
        $l = qtrans_getLanguage();
        if ( trim( $l ) ) {
	        global $q_config, $wpdb;
	        if ( $q_config['hide_untranslated'] ) {
		        $where .= " AND post_content LIKE '%<!--:".$l."-->%'";
	        }
	    }
	}
	return $where;
}

function listarCategorias($t){
    preg_match_all('/\[cat:([^\]]*)\]/i',$t,$matches);
    if(count($matches)){
        foreach($matches[1] as $catname){
            $myposts = get_posts(array('numberposts'=>-1,'post_type'=>'post','category_name'=>$catname,'suppress_filters'=>0));
            $listposts='';

            if(count($myposts)){
                global $post;
                $prepost=$post;
                $listposts.='<ul class="inner-cat">';
                foreach($myposts as $post) $listposts.='<li><a href="'.get_permalink().'">'.get_the_title().'</a></li>';
                $listposts.='</ul>';
                $post=$prepost;
            }
            $t = tiraDoParagrafo('[cat:'.$catname.']', $t);
            $t = str_replace('[cat:'.$catname.']', $listposts, $t);
        }
    }
    return $t;
}

function listarCategoriasEx($t){
    preg_match_all('/\[catex:([^\]]*)\]/i',$t,$matches);
    if(count($matches)){
        foreach($matches[1] as $catname){
            $myposts = get_posts(array('post_type'=>'post','category_name'=>$catname,'suppress_filters'=>0));
            $listposts='';
            if(count($myposts)){
                global $post;
                $prepost=$post;
                $listposts.='<dl class="inner-cat">';
                foreach($myposts as $post) $listposts.='<dt><a href="'.get_permalink().'">'.get_the_title().'</a></dt>'.(trim($post->post_excerpt)?'<dd>'.$post->post_excerpt.'</dd>':'');
                $listposts.='</dl>';
                $post=$prepost;
            }
            $t = tiraDoParagrafo('[catex:'.$catname.']', $t);
            $t = str_replace('[catex:'.$catname.']', $listposts, $t);
        }
    }
    return $t;
}

function listarIdiomas($t){
    if ( stripos($t, '[languages]')!==false && function_exists('qtrans_generateLanguageSelectCode') ){
        ob_start();
        qtrans_generateLanguageSelectCode();
        $i=ob_get_contents();
        ob_end_clean();
        ob_end_flush();
        $t = tiraDoParagrafo('[languages]', $t);
        $t = str_replace('[languages]', $i, $t);
    }
    return $t;
}

function tiraDoParagrafo($tag, $t){
    return str_replace('<p>'.$tag.'</p>', $tag, $t);
}

/* -- END CASE SPECIFIC; -- */

?>
