<?php
/**
 * Captions/timed-text controller.
 *
 * Very important - Flowplayer only accepts,
 *   xmlns="http://www.w3.org/2006/10/ttaf1"
 * NOT
 *   xmlns="http://www.w3.org/2006/04/ttaf1"
 *
 * @author N.D.Freear, 27 April 2011/6 February 2012.
 * @copyright Copyright 2012 The Open University.
 */
#require_once APPPATH.'libraries/ouplayer_lib.php';

define('TTML_NS', 'http://www.w3.org/2006/10/ttaf1');


class Timedtext extends MY_Controller { #CI_Controller {


  // Captions for Mediaelement-based players.

  /**
   * TTML+XML to WebVTT (Web Video Text Tracks) parser.
   * Copyright 2012-02-06 N.D.Freear/ The Open University.
   *
   * See, http://dev.w3.org/html5/webvtt/#the-webvtt-file-format
   * See, http://www.delphiki.com/webvtt/
   *
   * FROM: oup-mep/webvtt.php
   */
  public function webvtt() {

    $ttml_url = $this->input->get('url');
    #'http://podcast.open.ac.uk/feeds/student-experiences/closed-captions/openings-being-an-ou-student.xml';
    $debug = $this->input->get('debug');

    if (! $ttml_url) {
      $this->_error("Error, 'url' is a required parameter.", 400);
    }

    $p = parse_url($ttml_url);


    // A naive check for SRT captions, from VLE etc.
    $is_srt = 'srt' == pathinfo($p['path'], PATHINFO_EXTENSION);


	// Bug #1334, Proxy mode to fix VLE caption redirects.
	// Example https://learn2acct.open.ac.uk/pluginfile.php/985394/mod_oucontent/oucontent/155114/k315-0-video1.srt
    $options = array();
    if ($is_srt && preg_match('/^(learn|[\w\.]*vledev)\w*.open.ac.uk\/(moodle\w*\/)?pluginfile/', $p['host'].$p['path'], $matches)) {
      $options['proxy_cookies'] = true;
      $options['max_redirects'] = 0;
      $options['ua'] = $this->agent->agent_string();
      $options['ssl_verify'] = false;
      $options['debug'] = true;
    }

	$this->load->library('http');

    $result = $this->http->request($ttml_url, $spoof=FALSE, $options);

    if (! $result->success) {
      if (404 == $result->info['http_code']) {
        $this->_error('Caption file not found.', 404);
      }
      #var_dump($result->info);
      $this->_error('Caption request problem.', $result->http_code);
    }


    if (! $is_srt) {
      $xmlo = @simplexml_load_string($result->data);
      #$xmlo = new SimpleXMLElement($result->data);
      if (! $xmlo) {
        $this->_error('XML caption parsing problem.', 503);
      }
    }

    if ($debug) {
      header('Content-Type: text/plain; charset=UTF-8');
    } else {
      header('Content-Type: text/vtt; charset=UTF-8');
    }
    @header('X-Input-Text-Track: '.$ttml_url); #Was: X-Input-TTML
    @header('Content-Disposition: inline; filename='.basename($ttml_url).'.vtt');
    echo 'WEBVTT'.PHP_EOL.PHP_EOL;


    // Assume the SRT captions are well formed.
    if ($is_srt) {
      echo $result->data;
      exit;
    }


    // Get declared namespaces.
    $ns = $xmlo->getDocNamespaces();

    $ns_string = '';
    foreach ($ns as $pre => $url) {
      $fix = ''==$pre ? 'xmlns' : 'xmlns:'.$pre;
      $ns_string .= " $fix='$url'";
    }

    $count=0;
foreach ($xmlo->body->div->p as $el => $para) {
  $count++;

  $text ='';
  $line = $para;
  /*if (isset($para->span)) {
    $line = $para->span;
  }*/

  if ($line->br) {
    $para_n = str_replace('<br/>', ' ', $line->asXML());
    $xml_n = "<x $ns_string>$para_n</x>";
    $xmlo_n = new SimpleXMLElement($xml_n);
    if (isset($xmlo_n->p->span)) {
      foreach ($xmlo_n->p->children() as $span) {
        $text .= (string) $span.' ';
      }
    } else {
      $text = (string) $xmlo_n->p;
    }
  } elseif (isset($para->span)) {
    $text = (string) $para->span;
  } else {
    $text = (string) $line;
  }
  echo
    $count.PHP_EOL
    .$para['begin'].' --> '.$para['end'].PHP_EOL
    .$text .PHP_EOL.PHP_EOL;
}

  }



  // Captions for Flowplayer-based players.

  /**
  *  OU-podcast player captions - TTML format.
  */
  public function pod_captions($custom_id, $shortcode, $captions_file=null) {

    $captions = config_item('captions'); #$this->CI->config->item
    if (isset($captions[$custom_id][$shortcode])) {
	    $cc_file = $captions[$custom_id][$shortcode];

		$cc_path = config_item('data_dir')."oupodcast/captions/$cc_file";

		if (file_exists($cc_path)) {
		    header('Content-Type: application/xml; charset=utf-8');
			header("Content-Disposition: inline; filename=$cc_file");
			#header('Content-Type: text/xml');
			#header('Accept-Ranges: bytes');
			echo file_get_contents($cc_path);
		} else {
		  //Error 404.
		  die('404.1');
		}
	} else {
		//Error 404.
		die('404.2');
	}
  }
}
