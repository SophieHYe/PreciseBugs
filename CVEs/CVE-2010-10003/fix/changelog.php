<?php
// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die('Direct Access to this location is not allowed.');

?>

2010-08-08:

- [#20653] SQL Injection: $phrase is escaped now, yet most of the plugins have to be updated according to plugin_content_title.php


2009-06-06:

- [#16580] Exact Match as default or plugin option


2009-05-27:

- added plugin_jmovies (thanks to Onimaro)


2008-11-13:

- fixed creation of wrong content article links ... though they worked correctly in Joomla *phew*


2008-11-10:

- [#13627] link to the site-internal search
- code cleanup


2008-10-28:

- corrections for new plugins (using new Joomla! 1.5.x API)
- implemented new sorting of plugins
- other plugins are disabled by default (only content and menuitem plugins are enabled) 


2008-10-26:

- new plugins by external contributions, but there still needs to be a new option to sort plugins.


2008-10-19:

- updated documentation link http://www.gesellix.de/joomla/36-titlelink/46-titlelink-documentation.html


2008-09-17:

- fixes [#12606] TL 3.0.5 cannot find uncategorized articles
- fixes missing links when Joomla table prefix isn't 'jos'


2008-08-18:

- fixed "empty" page issue, when plugin was called from other components


2008-08-18:

- [#10182] SEF don't work correctly
- method="upgrade" to enable users making updates without uninstall


<?php
?>