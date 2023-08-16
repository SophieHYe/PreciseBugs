<?php

class PiwikHooks {
	
	/**
	 * Initialize the Piwik Hook
	 * 
	 * @param string $skin
	 * @param string $text
	 * @return bool
	 */
	public static function PiwikSetup ($skin, &$text = '')
	{
		$text .= PiwikHooks::AddPiwik( $skin->getTitle() );
		return true;
	}
	
	/**
	 * Add piwik script
	 * @param string $title
	 * @return string
	 */
	public static function AddPiwik ($title) {
		
		global $wgPiwikIDSite, $wgPiwikURL, $wgPiwikIgnoreSysops, 
			   $wgPiwikIgnoreBots, $wgUser, $wgScriptPath, 
			   $wgPiwikCustomJS, $wgPiwikActionName, $wgPiwikUsePageTitle,
			   $wgPiwikDisableCookies, $wgPiwikProtocol,
			   $wgPiwikTrackUsernames, $wgPiwikJSFileURL;
		
		// Is piwik disabled for bots?
		if ( $wgUser->isAllowed( 'bot' ) && $wgPiwikIgnoreBots ) {
			return "<!-- Piwik extension is disabled for bots -->";
		}
		
		// Ignore Wiki System Operators
		if ( $wgUser->isAllowed( 'protect' ) && $wgPiwikIgnoreSysops ) {
			return "<!-- Piwik tracking is disabled for users with 'protect' rights (i.e., sysops) -->";
		}
		
		// Missing configuration parameters 
		if ( empty( $wgPiwikIDSite ) || empty( $wgPiwikURL ) ) {
			return "<!-- You need to set the settings for Piwik -->";
		}
		
		if ( $wgPiwikUsePageTitle ) {
			$wgPiwikPageTitle = $title->getPrefixedText();
		
			$wgPiwikFinalActionName = $wgPiwikActionName;
			$wgPiwikFinalActionName .= $wgPiwikPageTitle;
		} else {
			$wgPiwikFinalActionName = $wgPiwikActionName;
		}
		
		// Check if disablecookies flag
		if ($wgPiwikDisableCookies) {
			$disableCookiesStr = PHP_EOL . '  _paq.push(["disableCookies"]);';
		} else $disableCookiesStr = null;
		
		// Check if we have custom JS
		if (!empty($wgPiwikCustomJS)) {
			
			// Check if array is given
			// If yes we have multiple lines/variables to declare
			if (is_array($wgPiwikCustomJS)) {
				
				// Make empty string with a new line
				$customJs = PHP_EOL;
				
				// Store the lines in the $customJs line
				foreach ($wgPiwikCustomJS as $customJsLine) { 
					$customJs .= $customJsLine;
				}
			
			// CustomJs is string
			} else $customJs = PHP_EOL . $wgPiwikCustomJS;
			
		// Contents are empty
		} else $customJs = null;

        // Track username based on https://piwik.org/docs/user-id/ The user
        // name for anonymous visitors is their IP address which Piwik already
        // records.
        if ($wgPiwikTrackUsernames && $wgUser->isLoggedIn()) {
            $username = $wgUser->getName();
            $customJs .= PHP_EOL . "  _paq.push(['setUserId','{$username}']);";
        }

		// Check if server uses https
		if ($wgPiwikProtocol == 'auto') {
			
			if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
				$wgPiwikProtocol = 'https';
			} else {
				$wgPiwikProtocol = 'http';
			}
			
		}
		
		// Prevent XSS
		$wgPiwikFinalActionName = Xml::encodeJsVar( $wgPiwikFinalActionName );
		
		// If $wgPiwikJSFileURL is null the locations are $wgPiwikURL/piwik.php and $wgPiwikURL/piwik.js
		// Else they are $wgPiwikURL/piwik.php and $wgPiwikJSFileURL
		$jsPiwikURL = '';
		$jsPiwikURLCommon = '';
		if( is_null( $wgPiwikJSFileURL ) ) {
			$wgPiwikJSFileURL = 'piwik.js';
			$jsPiwikURLCommon = '+' . Xml::encodeJsVar( $wgPiwikURL . '/' );
		} else {
			$jsPiwikURL = '+' . Xml::encodeJsVar( $wgPiwikURL . '/' );
		}
		$jsPiwikJSFileURL = Xml::encodeJsVar( $wgPiwikJSFileURL );

		// Piwik script
		$script = <<<PIWIK
<!-- Piwik -->
<script type="text/javascript">
  var _paq = _paq || [];{$disableCookiesStr}{$customJs}
  _paq.push(["trackPageView"]);
  _paq.push(["enableLinkTracking"]);

  (function() {
    var u = (("https:" == document.location.protocol) ? "https" : "http") + "://"{$jsPiwikURLCommon};
    _paq.push(["setTrackerUrl", u{$jsPiwikURL}+"piwik.php"]);
    _paq.push(["setSiteId", "{$wgPiwikIDSite}"]);
    var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0]; g.type="text/javascript";
    g.defer=true; g.async=true; g.src=u+{$jsPiwikJSFileURL}; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Piwik Code -->

<!-- Piwik Image Tracker -->
<noscript><img src="{$wgPiwikProtocol}://{$wgPiwikURL}/piwik.php?idsite={$wgPiwikIDSite}&amp;rec=1" style="border:0" alt="" /></noscript>
<!-- End Piwik -->
PIWIK;
		
		return $script;
		
	}
	
}



