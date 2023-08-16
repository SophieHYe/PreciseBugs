<?php
/**
 * @copyright Copyright (C) 2005 - 2007 Tobias Gesellchen. All rights reserved.
 * @license   GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die('Direct Access to this location is not allowed.');

jimport( 'joomla.plugin.plugin' );

class plgContentTitleLink extends JPlugin
{
  /**
   * caches results of plugins
   * index-key: phrase
   * value: plugin result
   */
  //var $titlelink_cache = array();
  var $plugin_cache;

  var $title1_delim = " '";
  var $title2_delim = " ''";

  var $plugin_call = "plugin_call";
  var $item_id = "itemid-";
  var $limitstart = "limitstart-";
  var $append_url = "append-";
  var $css_class_pattern = "css-";
  var $search_pattern = "search-";
  var $newwindow = "nw";
  var $open_tag = "op";  // don't close the anchor tag, like: '<a href=...>'
  var $search_site = "search";
  var $search_google = "google";
  var $search_yahoo = "yahoo";
  var $search_wikipedia = "wikipedia";
  var $exact_match = "em";
  var $replace_match = "rep";
  var $link_http = "http";
  var $enabled = "enable";
  var $debug_mode = "debug";

  var $dir        = 'plugins/content/titlelink_plugins';
  var $pluginmask = 'plugin_';

  /**
   * see http://www.php.net/manual/en/reference.pcre.pattern.syntax.php
   * for details: this special "character" is used as start/end tag
   * for the preg_match functions.
   */
  var $pattern_start_end = "\x01";

  // configuration

  var $trigger_prefix;
  var $trigger_suffix;
  var $separator;
  var $enablenewcontent;
  var $enablepartialmatch;
  var $disp_link;
  var $disp_tooltip;
  var $linkr_enabled;

  var $finalpattern;


  /**
   * Constructor
   *
   * For php4 compatability we must not use the __constructor as a constructor for plugins
   * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
   * This causes problems with cross-referencing necessary for the observer design pattern.
   *
   * @param object $subject The object to observe
   * @param object $params  The object that holds the plugin parameters
   */
  function plgContentTitleLink( &$subject, $params )
  {
    parent::__construct( $subject, $params );

    $this->trigger_prefix = $this->params->get( 'trigger_prefix', "{ln" );
    $this->trigger_suffix = $this->params->get( 'trigger_suffix', "}" );
    $this->separator = $this->params->get( 'separator', ":" );
    $this->enablenewcontent = $this->params->get( 'enablenewcontent', 0 );
    $this->enablepartialmatch = $this->params->get( 'enablepartialmatch', 1 );
    $this->disp_link = $this->params->get( 'disp_link', 1 );
    $this->disp_tooltip = $this->params->get( 'disp_tooltip', 1 );
    $this->linkr_enabled = $this->params->get( 'linkr_enabled', 1 );

    $this->finalpattern = $this->pattern_start_end.preg_quote($this->trigger_prefix).".+?".preg_quote($this->trigger_suffix).$this->pattern_start_end;

    if ($this->plugin_cache == null || !is_array($this->plugin_cache))
    {
      $this->plugin_cache = $this->getPluginFunctions($this->dir, $this->pluginmask, $this->params);
    }

    //$this->titlelink_cache = array();
  }

  // Send links to Linkr
  function onLinkrGetLinks( $version )
  {
    $result = array();

    // This plugin doesn't actually do anything.
    // It will only display the current version
    // in a popup using the LinkrHelper.test()
    // javascript function

    if ($this->linkr_enabled && $version >= 2.2)
    {
      $msg = 'TitleLink plugin is working on Linkr '. $version;

      // Since this will be added to the "onclick"
      // attribute, use 'single quotes' instead
      // of "double quotes"
      //$js = 'LinkrHelper.test(\''. $msg .'\')';

      // Return link title and javascript statement
      $result = array(
        'TitleLink' => 'LinkrHelper.layout(\'link\')'
      );
    }

    return $result;
  }

  // Linkr Scripts
  function onLinkrLoadJS( $version )
  {
    if ($this->linkr_enabled && $version >= 2.2 && 2==1)
    {
      $doc = & JFactory::getDocument();
      $doc->addScript( 'plugins/content/titlelink_plugins/linkr_script.js' );

      // URL for AJAX requests. Be sure to use full URLs
      $r = JURI::base() .'index.php?option=com_foo&amp;tmpl=component&amp;view=foo&amp;'. JUtility::getToken() .'=1';

      return 'LinkrFoo.init(\''. $r .'\');';
    }

    return '';
  }

  /**
   * Replaces TitleLinks with the correct html hyperlinks
   *
   * Method is called by the view
   *
   * @param   object    The article object.  Note $article->text is also available
   * @param   object    The article params
   * @param   int     The 'page' number
   */
  function onPrepareContent(&$article, &$params, $limitstart)
  {
    global $mainframe, $titlelink_cache;

    $database = & JFactory::getDBO();

    $content = $article->text;
    $matches = array ();

    if (preg_match_all($this->finalpattern, $content, $matches, PREG_PATTERN_ORDER))
    {
      $titlelink_disabled = false;
      $titlelink_was_disabled = false;

      foreach ($matches as $fmatch)
      {
        foreach ($fmatch as $match)
        {
          if ($match == "")
          {
            continue;
          }

          $match = str_replace($this->trigger_prefix, "", $match);
          $match = str_replace($this->trigger_suffix, "", $match);

          $match = html_entity_decode($match, ENT_QUOTES);

          // split phrase and title
          $title1 = null;
          $title2 = null;
          $delimpos = strpos($match, $this->title1_delim);
          if ($delimpos !== false)
          {
            $title1 = substr($match, $delimpos + strlen($this->title1_delim));
            $match = substr($match, 0, $delimpos);

            $delimpos = strpos($title1, $this->title2_delim);
            if ($delimpos !== false)
            {
              $title2 = substr($title1, $delimpos + strlen($this->title2_delim));
              $title1 = substr($title1, 0, $delimpos);
            }
          }

          $pieces = explode($this->separator, $match);
          $phrase = trim($pieces[count($pieces) - 1]);

          // Look for an anchor name in the phrase string (thanks to Federico Filipponi, 2006-11-10)
          $anchor = null;
          $anchorpos = strpos($phrase, "#");
          if ($anchorpos !== false)
          {
            $anchor = substr($phrase, $anchorpos);
            $phrase = substr($phrase, 0, $anchorpos);
          }

          $original_phrase = $phrase;
          $phrase = html_entity_decode($phrase);

          $link = null;
          $css_class = null;
          $plugin_call_name = null;
          $name = null;
          $title = null;
          $search = null;
          $external = null;
          $debug_enabled = null;
          $partial_match = $this->enablepartialmatch == 1;
          $replace_partial_match = true;
          $create_open_tag = false;
          $my_item_id = null;
          $limitstart_ix = null;
          $to_append = null;

          // get options
          $count = count($pieces);
          for ($i = 0; $i < $count - 1; $i++) // ignore the last piece - it's our phrase
          {
            switch($pieces[$i])
            {
              case $this->enabled:
                // disable TitleLink for this text?
                if ($phrase == "false")
                {
                  $titlelink_disabled = true;
                  $titlelink_was_disabled = false;
                }
                else
                {
                  $titlelink_disabled = false;
                  $titlelink_was_disabled = true;
                }
                break;
              case $this->newwindow:     // open link in a new window?
                $external = true;
                break;
              case $this->open_tag:
                $create_open_tag = true;
                break;
              case $this->search_site:       // get a search link for the site
              case $this->search_google:     // get a google search link
              case $this->search_yahoo:      // get a yahoo search link
              case $this->search_wikipedia:  // get a wikipedia search link
                $link = $this->getSearchLink($pieces[$i], $phrase);
                $name = $phrase;
                break;
              case $this->exact_match:   // disable partial matching?
                $partial_match = false;
                break;
              case $this->replace_match: // don't replace phrase by complete match?
                $replace_partial_match = false;
                break;
              case $this->link_http:     // is external link?
              case $this->link_http.'s':     // is external link?
                $link = $this->startswith($phrase, "index.php") ? $phrase : $pieces[$i].":".$phrase;
                $name = $link;
                break;
              case $this->debug_mode:    // give some debug information?
                $debug_enabled = true;
                break;
              default:
                if ($this->startswith($pieces[$i], $this->plugin_call."-") && empty($plugin_call_name))
                {
                  $plugin_call_name = substr($pieces[$i], strlen($this->plugin_call."-"));
                }
                else if ($this->startswith($pieces[$i], $this->css_class_pattern) && empty($css_class))
                {
                  $css_class = " class=\"".substr($pieces[$i], strlen($this->css_class_pattern))."\" ";
                }
                else if ($this->startswith($pieces[$i], $this->item_id) && empty($my_item_id))
                {
                  $my_item_id = substr($pieces[$i], strlen($this->item_id));
                }
                else if ($this->startswith($pieces[$i], $this->limitstart) && empty($limitstart_ix))
                {
                  $limitstart_ix = substr($pieces[$i], strlen($this->limitstart));
                }
                else if ($this->startswith($pieces[$i], $this->search_pattern))
                {
                  $link = $this->getSearchLink(substr($pieces[$i], 0, strlen($this->search_pattern)), $phrase);
                  $name = $phrase;
                }
                else if ($this->startswith($phrase, "index.php"))
                {
                  $link = $phrase;
                  $name = $link;
                }
                else if (empty($to_append) && $this->startswith($pieces[$i], $this->append_url))
                {
                  $to_append = substr($pieces[$i], strlen($this->append_url));
                }
            }
          }

          if ($titlelink_disabled)
          {
            $content = preg_replace($this->finalpattern, $link, $content, 1);
            $article->text = $content;
            continue;
          }
          else if ($titlelink_was_disabled)
          {
            $content = preg_replace($this->finalpattern, $link, $content, 1);
            $article->text = $content;
            $titlelink_was_disabled = false;
            continue;
          }

          // try to find a link by help of the plugins
          // returns
          // $link: the href
          // $name: will be used as title and as the text for the visitor
          if (empty($link))  // did the search functions already set a link?
          {
            // ask cache
            if (!is_array($titlelink_cache))
            {
              $titlelink_cache = array();
            }
            if (!array_key_exists($phrase, $titlelink_cache))
            {
              // try to find an exact match
              $result = $this->getByPlugins($database, $this->plugin_cache, $phrase, false);
              if ((count($result) != 2) && ($partial_match))
              {
                // no exact match found --> try partial match
                $result = $this->getByPlugins($database, $this->plugin_cache, $phrase, true);
              }
            }
            else
            {
              $result = $titlelink_cache[$phrase];
            }

            // found something? --> save it
            if (count($result) == 2)
            {
              // save result in cache
              $titlelink_cache[$phrase] = $result;
              $link = $result[0];
              $name = $result[1];

              if (!$replace_partial_match)
              {
                $name = $phrase;
              }

              if (!empty($my_item_id) && $this->endswith($link, 'Itemid='))
              {
                $link .= $my_item_id;
              }
            }
          }

          // if a working link was found, create the complete hyperlink with the given options
          if (!empty($link))
          {
            if (!empty($to_append))
            {
              $link .= $to_append;
            }

            $options = "";

            // append css-class (can be empty)
            $options .= $css_class;

            // if the user provided this parameter, overwrite the generic name from above
            $link_title = $title2;
            $link_text = $title1;

            if (empty($link_title))
            {
              switch($this->disp_tooltip)
              {
                case 0: // Query String
                  $link_title = $phrase;
                  break;
                case 1: // Query Result
                  $link_title = $name;
                  break;
                case 2: // Explicit Title
                  if (!(empty($title1) || $this->startswith($title1, "<img ")))
                  {
                    $link_title = $title1;
                  }
                  else
                  {
                    $link_title = $name;
                  }
                  break;
                default:
                  $link_title = $name;
              }
            }

            if (empty($link_text))
            {
              switch($this->disp_link)
              {
                case 0: // Query String
                  $link_text = $phrase;
                  break;
                case 1: // Query Result
                  $link_text = $name;
                  break;
                default:
                  $link_title = $name;
              }
            }

            // set title
            if ($debug_enabled && $external)
            {
              $options .= "title=".$link_title."\" (open in new window)\"";
            }
            else
            {
              $options .= "title=\"".$link_title."\"";
            }

            // open the link in a new window?
            if ($external)
            {
              $options .= " target=\"_blank\"";
            }

            // select page
            if (!empty($limitstart_ix))
            {
              if (is_numeric($limitstart_ix))
              {
                //$link .= "&start=".($limitstart_ix-1);
                $link .= "&limitstart=".($limitstart_ix-1);
              }
            }

            // lets TitleLink call other plugins for the target content
            if (!empty($plugin_call_name))
            {
              $link .= "&".$this->plugin_call."=".$plugin_call_name;
            }

            if ($this->isExternal($link))
            {
              // external link
              //$link = htmlentities($link);
              $link = htmlspecialchars($link);
            }
            else
            {
              // internal link, make it sef
              //$link = htmlentities($link);
              $link = JRoute::_($link);
            }

            // add the anchor to the found link
            if (!empty($anchor))
            {
              $link .= $anchor;
            }

            $link = "<a href=\"".$link."\" ".$options.">";

            if (!$create_open_tag)
            {
              $link .= "$link_text</a>";
            }
          }
          else
          {
            if ($this->enablenewcontent)
            {
              $link = "<strong>";
              $link .= "<a href=\"";
              $link .= "index.php?option=com_content&view=article&layout=form";
              //$link .= "index.php?option=com_content&task=new&sectionid=";
              // use current sectionid
              //$link .= $article->sectionid;
              // hinders Joomla from checking for a published menu entry (authorisation check)
              //$link .= "&Itemid=-1";
              $link .= "\" title=\"Submit article\">";
              $link .= $phrase;
              $link .= "!</a>";
              $link .= "</strong>";
            }
            else
            {
              // show $phrase in bold font if no link could be built
              $link = "<strong>";
              $link .= $phrase;
              $link .= "</strong>";
            }

            if ($debug_enabled)
            {
              $link .= "<p>dump:";

              $link .= "<br />new window: ".(($external) ? "yes" : "no");

              $link .= "<br />pieces:<ul>";
              $count = count($pieces);
              for ($i = 0; $i < $count; $i++)
              {
                $link .= "<li>".$pieces[$i]."</li>";
              }
              $link .= "</ul>";

              $link .= "<br />plugin-results:<ul>";
              $count = count($result);
              for ($i = 0; $i < $count; $i++)
              {
                $link .= "<li>".$result[$i]."</li>";
              }
              $link .= "</ul>";

              $link .= "<br />phrase=".$phrase;
              $link .= "<br />title=".$title;

              $link .= "<br />available plugins:<ul>";
              $count = count($this->plugin_cache);
              for ($i = 0; $i < $count; $i++)
              {
                $link .= "<li>".$this->plugin_cache[$i]."</li>";
              }
              $link .= "</ul>";

              $link .= "</p>";
            }
          }

          $content = preg_replace($this->finalpattern, $link, $content, 1);
        }
      }
    }

    $article->text = $content;

    $this->callExternalPlugin($article, $params, $limitstart);

    return true;
  }



/////////////////////////////////////////////
// internal functions

  function callExternalPlugin($article, $params, $limitstart) {
    $plugin_to_call = JRequest::getVar($this->plugin_call);
    //$plugin_to_call = html_entity_decode(mosGetParam($_GET, $this->plugin_call), ENT_QUOTES);
    $plugin_to_call = stripslashes($plugin_to_call);
    if (!empty($plugin_to_call))
    {
      //$article->text .= "<!-- plugincall5 key".$this->plugin_call." value".$plugin_to_call." --> <br /><br /><br />"."{".$plugin_to_call."}";
      $article->text .= "{".$plugin_to_call."}";

// TODO call generic $_MAMBOTS
//MAZ changed this to botMosEbayKit3
      botMosEbayKit3(true, $article, $params, $limitstart);
    }
  }
  
  function isExternal($link)
  {
    $link_pattern='/^(http:\/\/|)(www.|)([^\/]+)/i';

    preg_match($link_pattern, $link, $domain);
    preg_match($link_pattern, $_SERVER['HTTP_HOST'], $http);

    return $this->startswith($link, "http") && ((isset($domain[3])) and (isset($http[3])) and ($domain[3]!==$http[3]));
}

  function getByPlugins($database, $plugins, $phrase, $partial_match)
  {
    $phrase_escaped = $database->getEscaped($phrase, true);

    $count = count($plugins);
    for ($i = 0; $i < $count; $i++)
    {
      $result = call_user_func($plugins[$i], $database, $phrase_escaped, $partial_match);

      // magix number '2' is the amount of strings we need as result
      if (count($result) == 2)
      {
        return $result;
      }
    }

    return null;
  }

  function getPluginFunctions($dir, $pluginmask, &$pluginParams)
  {
    //$dir = $this->dir; $pluginmask = $this->pluginmask; $pluginParams = $this->params;

    // load plugin params info
    //$plugin =& JPluginHelper::getPlugin('content', 'titlelink');
    //$pluginParams   = new JParameter( $plugin->params );

    $files = null;
    $dh  = opendir(JPATH_ROOT.DIRECTORY_SEPARATOR.$dir);
    while (false !== ($filename = readdir($dh)))
    {
      $keyname = substr($filename, 0, strlen($filename) - strlen('.php'));

      if ($this->startswith($filename, $pluginmask)
          && $pluginParams->get($keyname, 0) > 0)
      {
        // found "plugin", include in list
        $files[] = array('file' => $filename, 'order' => $pluginParams->get($keyname, 0));
      }
    }

    if ($files == null)
    {
      // no plugin found
      return null;
    }

    $filenames = array();
    $orders = array();
    
	// Obtain a list of columns
	foreach($files as $key => $row)
	{
	    $filenames[$key] = $row['file'];
	    $orders[$key] = $row['order'];
	}
	
	array_multisort($orders, SORT_ASC, $filenames, SORT_ASC, $files);

    // load plugins
    foreach($files as $key => $row)
    {
      $file = $row['file'];
      if (!empty($file))
      {
        include_once(JPATH_ROOT.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$file);
      }
    }

    // get only functions which are usable in this bot
    $functions = get_defined_functions();
    $functions = $functions["user"];
    $count = count($functions);
    for ($i = 0; $i < $count; $i++)
    {
      if ($this->startswith($functions[$i], $pluginmask))
      {
        $plugin_fkt[] = $functions[$i];
      }
    }

    return $plugin_fkt;
  }

  function startswith($source, $mask)
  {
    $pos = strpos($source, $mask);
    return (($pos !== false) && ($pos == 0));
  }

  function endswith($source, $mask)
  {
    $pos = strpos($source, $mask);
    return (($pos !== false) && ($pos == (strlen($source)-strlen($mask))));
  }

  function stringrpos($haystack,$needle,$offset=NULL)
  {
    return strlen($haystack)
           - strpos( strrev($haystack) , strrev($needle) , $offset)
           - strlen($needle);
  }

  function strrpos_string($haystack, $needle, $offset = 0)
  {
    if(trim($haystack) != "" && trim($needle) != "" && $offset <= strlen($haystack))
    {
      $last_pos = $offset;
      $found = false;
      while(($curr_pos = strpos($haystack, $needle, $last_pos)) !== false)
      {
        $found = true;
        $last_pos = $curr_pos + 1;
      }
      if($found)
      {
        return $last_pos - 1;
      }
      else
      {
        return false;
      }
    }
    else
    {
      return false;
    }
  }

  function getSearchLink($engine, $phrase)
  {
    $space_replacement = "%20";
    $search_link = null;

    switch($engine)
    {
      case "search":
        // local search
        $search_link = "index.php?option=com_search&ordering=&searchphrase=all&searchword=".$phrase;
        $space_replacement = "+";
        break;

      case "abbenormal":
        $search_link = "http://www.ourpla.net/cgi-bin/pikie.cgi?".$phrase;
         break;
      case "acronym":
        $search_link = "http://www.acronymfinder.com/af-query.asp?String=exact&Acronym=".$phrase;
        break;
      case "advogato":
        $search_link = "http://www.advogato.org/".$phrase;
        break;
      case "amazon_com":
        $search_link = "http://www.amazon.com/s?url=search-alias%3Daps&field-keywords=".$phrase;
        $space_replacement = "+";
        break;
      case "bible":
        $search_link = "http://bible.gospelcom.net/bible?".$phrase;
        break;
      case "caffeinatedwiki":
        $search_link = "http://socrates.yi.org/".$phrase;
        break;
      case "cliki":
        $search_link = "http://www.cliki.net/".$phrase;
        break;
      case "cmwiki":
        $search_link = "http://www.cmwiki.com/".$phrase;
        break;
      case "creationmatters":
        $search_link = "http://www.ourpla.net/cgi-bin/wiki.pl?".$phrase;
        break;
      case "cssdiscusswiki":
        $search_link = "http://css-discuss.incutio.com/?page=".$phrase;
        break;
      case "dictionary":
        $search_link = "http://www.dictionary.com/cgi-bin/dict.pl?term=".$phrase;
        break;
      case "doiwalters":
        $search_link = "http://www.doiwalters.com/wiki/".$phrase;
        break;
      case "emacswiki":
        $search_link = "http://www.emacswiki.org/cgi-bin/wiki/".$phrase;
        break;
      case "everything2":
        $search_link = "http://www.everything2.com/".$phrase;
        break;
      case "foldoc":
        $search_link = "http://www.foldoc.org/foldoc/foldoc.cgi?".$phrase;
        break;
      case "foxwiki":
        $search_link = "http://fox.wikis.com/wc.dll?Wiki~".$phrase;
        break;
      case "geekido":
        $search_link = "http://geekido.org/index.php?page=".$phrase;
        break;
      case "google":
        $search_link = "http://www.google.com/search?q=".$phrase;
        $space_replacement = "+";
        break;
      case "googlegroups":
        $search_link = "http://groups.google.com/groups?q=".$phrase;
        break;
      case "h2g2":
        $search_link = "http://www.bbc.co.uk/h2g2/guide/Search?searchstring=".$phrase;
        break;
      case "hootoo":
        $search_link = "http://www.bbc.co.uk/h2g2/guide/Search?searchstring=".$phrase;
        break;
      case "hhgg":
        $search_link = "http://www.bbc.co.uk/dna/h2g2/".$phrase;
        break;
      case "iawiki":
        $search_link = "http://www.IAwiki.net/".$phrase;
        break;
      case "icq":
        $search_link = "http://wwp.icq.com/".$phrase;
        break;
      case "imdb":
        $search_link = "http://us.imdb.com/Title?".$phrase;
        break;
      case "jargonfile":
        $search_link = "http://sunir.org/apps/meta.pl?wiki=JargonFile&redirect=".$phrase;
        break;
      case "jtf":
        $search_link = "http://www.justthefaqs.org/?page=".$phrase;
        break;
      case "knowhow":
        $search_link = "http://www2.iro.umontreal.ca/~paquetse/cgi-bin/wiki.cgi?".$phrase;
        break;
      case "kwiki":
        $search_link = "http://kwiki.org/?".$phrase;
        break;
      case "linuxquestions":
        $search_link = "http://wiki.linuxquestions.org/wiki/".$phrase;
        break;
      case "livejournal":
        $search_link = "http://www.livejournal.com/users/".$phrase;
        break;
      case "meatball":
        $search_link = "http://www.usemod.com/cgi-bin/mb.pl?".$phrase;
        break;
      case "metawiki":
        $search_link = "http://sunir.org/apps/meta.pl?words=".$phrase;
        break;
      case "moinmoin":
        $search_link = "http://moinmoin.wikiwikiweb.de/".$phrase;
        break;
      case "openwiki":
        $search_link = "http://openwiki.com/ow.asp?".$phrase;
        break;
      case "orgpatterns":
        $search_link = "http://www.bell-labs.com/cgi-user/OrgPatterns/OrgPatterns?".$phrase;
        break;
      case "patent":
        $search_link = "http://patft.uspto.gov/netacgi/nph-Parser?patentnumber=".$phrase;
        break;
      case "pgpkey":
        $search_link = "http://keys.pgp.dk:11371/pks/lookup?op=get&search=".$phrase;
        break;
      case "phpwiki":
        $search_link = "http://phpwiki.sourceforge.net/phpwiki/".$phrase;
        break;
      case "pikie":
        $search_link = "http://pikie.webbing.nl/cgi-bin/pikie.cgi?".$phrase;
        break;
      case "ppr":
        $search_link = "http://c2.com/cgi/wiki?".$phrase;
        break;
      case "pythoninfo":
        $search_link = "http://www.python.org/cgi-bin/moinmoin/".$phrase;
        break;
      case "rfc":
        $search_link = "http://www.rfc.org.uk/cgi-bin/lookup.cgi?rfc=".$phrase;
        break;
      case "sourceforge":
        $search_link = "http://sourceforge.net/".$phrase;
        break;
      case "squeak":
        $search_link = "http://minnow.cc.gatech.edu/squeak/".$phrase;
        break;
      case "squirrelmail":
        $search_link = "http://squirrelmail.org/wiki/wiki.php?".$phrase;
        break;
      case "tavi":
        $search_link = "http://tavi.sourceforge.net/".$phrase;
        break;
      case "thesaurus":
        $search_link = "http://www.thesaurus.com/cgi-bin/search?config=roget&words=".$phrase;
        break;
      case "thinki":
        $search_link = "http://www.thinkware.se/cgi-bin/thinki.cgi/".$phrase;
        break;
      case "twiki":
        $search_link = "http://twiki.sourceforge.net/cgi-bin/view/".$phrase;
        break;
      case "usemod":
        $search_link = "http://www.usemod.com/cgi-bin/wiki.pl?".$phrase;
        break;
      case "visualworks":
        $search_link = "http://wiki.cs.uiuc.edu/VisualWorks/".$phrase;
        break;
      case "webster":
        $search_link = "http://m-w.com/cgi-bin/dictionary?va=".$phrase;
        break;
      case "why":
        $search_link = "http://clublet.com/c/c/why?".$phrase;
        break;
      case "wiki":
        $search_link = "http://c2.com/cgi/wiki?".$phrase;
        break;
      case "wikifind":
        $search_link = "http://c2.com/cgi/wiki?FindPage&value=".$phrase;
        break;
      case "wikipedia":
        $search_link = "http://en.wikipedia.org/wiki/".$phrase;
        break;
      case "wiktionary":
        $search_link = "http://en.wiktionary.org/wiki/".$phrase;
        break;
      case "wunderground":
        $search_link = "http://www.wunderground.com/cgi-bin/findweather/getForecast?query=".$phrase;
        break;
      case "yahoo":
        $search_link = "http://search.yahoo.com/search?p=".$phrase;
        $space_replacement = "+";
        break;
      case "zwiki":
        $search_link = "http://www.zwiki.org/".$phrase;
        break;
      default:
        // do nothing
        $search_link = null;
        break;
    }

    if (!empty($search_link))
    {
      $search_link = preg_replace($this->pattern_start_end."\ ".$this->pattern_start_end, $space_replacement, $search_link);
    }

    return $search_link;
  }
}
?>