<?php
// $HeadURL: https://joomgallery.org/svn/joomgallery/JG-3/JG/trunk/administrator/components/com_joomgallery/helpers/config.php $
// $Id: config.php 4267 2013-05-10 11:41:59Z erftralle $
/****************************************************************************************\
**   JoomGallery 3                                                                      **
**   By: JoomGallery::ProjectTeam                                                       **
**   Copyright (C) 2008 - 2013  JoomGallery::ProjectTeam                                **
**   Based on: JoomGallery 1.0.0 by JoomGallery::ProjectTeam                            **
**   Released under GNU GPL Public License                                              **
**   License: http://www.gnu.org/copyleft/gpl.html or have a look                       **
**   at administrator/components/com_joomgallery/LICENSE.TXT                            **
\****************************************************************************************/

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

/**
 * JoomGallery Configuration Helper
 *
 * Provides handling with all configuration settings of the gallery
 *
 * @package JoomGallery
 * @since   1.5.5
 */
class JoomConfig extends JObject
{
  /**
   * Configuration variables
   *
   * @var     string, int
   */
  var $jg_pathimages;
  var $jg_pathoriginalimages;
  var $jg_paththumbs;
  var $jg_pathftpupload;
  var $jg_pathtemp;
  var $jg_wmpath;
  var $jg_wmfile;
  var $jg_use_real_paths;
  var $jg_checkupdate;
  var $jg_filenamewithjs;
  var $jg_filenamereplace;
  var $jg_thumbcreation;
  var $jg_fastgd2thumbcreation;
  var $jg_impath;
  var $jg_resizetomaxwidth;
  var $jg_maxwidth;
  var $jg_picturequality;
  var $jg_useforresizedirection;
  var $jg_cropposition;
  var $jg_thumbwidth;
  var $jg_thumbheight;
  var $jg_thumbquality;
  var $jg_uploadorder;
  var $jg_useorigfilename;
  var $jg_filenamenumber;
  var $jg_delete_original;
  var $jg_msg_upload_type;
  var $jg_msg_upload_recipients;
  var $jg_msg_download_type;
  var $jg_msg_download_recipients;
  var $jg_msg_zipdownload;
  var $jg_msg_comment_type;
  var $jg_msg_comment_recipients;
  var $jg_msg_comment_toowner;
  var $jg_msg_nametag_type;
  var $jg_msg_nametag_recipients;
  var $jg_msg_nametag_totaggeduser;
  var $jg_msg_nametag_toowner;
  var $jg_msg_report_type;
  var $jg_msg_report_recipients;
  var $jg_msg_report_toowner;
  var $jg_msg_rejectimg_type;
  var $jg_msg_global_from;
  var $jg_realname;
  var $jg_contentpluginsenabled;
  var $jg_itemid;
  var $jg_ajaxcategoryselection;
  var $jg_disableunrequiredchecks;
  var $jg_use_listbox_max_user_count;
  var $jg_userspace;
  var $jg_useruploaddefaultcat;
  var $jg_approve;
  var $jg_unregistered_permissions;
  var $jg_maxusercat;
  var $jg_maxuserimage;
  var $jg_maxuserimage_timespan;
  var $jg_maxfilesize;
  var $jg_usercatacc;
  var $jg_usercatthumbalign;
  var $jg_maxuploadfields;
  var $jg_useruploadsingle;
  var $jg_useruploadajax;
  var $jg_useruploadbatch;
  var $jg_useruploadjava;
  var $jg_useruseorigfilename;
  var $jg_useruploadnumber;
  var $jg_special_gif_upload;
  var $jg_delete_original_user;
  var $jg_newpiccopyright;
  var $jg_newpicnote;
  var $jg_redirect_after_upload;
  var $jg_edit_metadata;
  var $jg_download;
  var $jg_download_unreg;
  var $jg_download_hint;
  var $jg_showrating;
  var $jg_maxvoting;
  var $jg_ratingcalctype;
  var $jg_ratingdisplaytype;
  var $jg_ajaxrating;
  var $jg_votingonlyonce;
  var $jg_votingonlyreg;
  var $jg_showcomment;
  var $jg_anoncomment;
  var $jg_namedanoncomment;
  var $jg_anonapprovecom;
  var $jg_approvecom;
  var $jg_bbcodesupport;
  var $jg_smiliesupport;
  var $jg_anismilie;
  var $jg_smiliescolor;
  var $jg_report_images;
  var $jg_report_unreg;
  var $jg_report_hint;
  var $jg_alternative_layout;
  var $jg_anchors;
  var $jg_tooltips;
  var $jg_dyncrop;
  var $jg_dyncropposition;
  var $jg_dyncropwidth;
  var $jg_dyncropheight;
  var $jg_dyncropbgcol;
  var $jg_hideemptycats;
  var $jg_skipcatview;
  var $jg_imgalign;
  var $jg_firstorder;
  var $jg_secondorder;
  var $jg_thirdorder;
  var $jg_pagetitle_cat;
  var $jg_pagetitle_detail;
  var $jg_showgalleryhead;
  var $jg_showpathway;
  var $jg_completebreadcrumbs;
  var $jg_showallpics;
  var $jg_showallhits;
  var $jg_showbacklink;
  var $jg_suppresscredits;
  var $jg_showuserpanel;
  var $jg_showuserpanel_hint;
  var $jg_showuserpanel_unreg;
  var $jg_showallpicstoadmin;
  var $jg_showminithumbs;
  var $jg_openjs_padding;
  var $jg_openjs_background;
  var $jg_dhtml_border;
  var $jg_show_title_in_popup;
  var $jg_show_description_in_popup;
  var $jg_lightbox_speed;
  var $jg_lightbox_slide_all;
  var $jg_resize_js_image;
  var $jg_disable_rightclick_original;
  var $jg_showgallerysubhead;
  var $jg_showallcathead;
  var $jg_colcat;
  var $jg_catperpage;
  var $jg_ordercatbyalpha;
  var $jg_showgallerypagenav;
  var $jg_showcatcount;
  var $jg_showcatthumb;
  var $jg_showrandomcatthumb;
  var $jg_ctalign;
  var $jg_showtotalcatimages;
  var $jg_showtotalcathits;
  var $jg_showcatasnew;
  var $jg_catdaysnew;
  var $jg_showdescriptioningalleryview;
  var $jg_uploadicongallery;
  var $jg_showrestrictedcats;
  var $jg_showrestrictedhint;
  var $jg_showsubsingalleryview;
  var $jg_category_rss;
  var $jg_category_rss_icon;
  var $jg_uploadiconcategory;
  var $jg_showcathead;
  var $jg_usercatorder;
  var $jg_usercatorderlist;
  var $jg_showcatdescriptionincat;
  var $jg_showpagenav;
  var $jg_showpiccount;
  var $jg_perpage;
  var $jg_catthumbalign;
  var $jg_colnumb;
  var $jg_detailpic_open;
  var $jg_lightboxbigpic;
  var $jg_showtitle;
  var $jg_showpicasnew;
  var $jg_daysnew;
  var $jg_showhits;
  var $jg_showdownloads;
  var $jg_showauthor;
  var $jg_showowner;
  var $jg_showcatcom;
  var $jg_showcatrate;
  var $jg_showcatdescription;
  var $jg_showcategorydownload;
  var $jg_showcategoryfavourite;
  var $jg_category_report_images;
  var $jg_showcategoryeditorlinks;
  var $jg_showsubcathead;
  var $jg_showsubcatcount;
  var $jg_colsubcat;
  var $jg_subperpage;
  var $jg_showpagenavsubs;
  var $jg_subcatthumbalign;
  var $jg_showsubthumbs;
  var $jg_showrandomsubthumb;
  var $jg_showdescriptionincategoryview;
  var $jg_ordersubcatbyalpha;
  var $jg_showtotalsubcatimages;
  var $jg_showtotalsubcathits;
  var $jg_uploadiconsubcat;
  var $jg_showdetailpage;
  var $jg_disabledetailpage;
  var $jg_showdetailnumberofpics;
  var $jg_cursor_navigation;
  var $jg_disable_rightclick_detail;
  var $jg_detail_report_images;
  var $jg_showdetaileditorlinks;
  var $jg_showdetailtitle;
  var $jg_showdetail;
  var $jg_showdetailaccordion;
  var $jg_accordionduration;
  var $jg_accordiondisplay;
  var $jg_accordionopacity;
  var $jg_accordionalwayshide;
  var $jg_accordioninitialeffect;
  var $jg_showdetaildescription;
  var $jg_showdetaildatum;
  var $jg_showdetailhits;
  var $jg_showdetaildownloads;
  var $jg_showdetailrating;
  var $jg_showdetailfilesize;
  var $jg_showdetailauthor;
  var $jg_showoriginalfilesize;
  var $jg_showdetaildownload;
  var $jg_downloadfile;
  var $jg_downloadwithwatermark;
  var $jg_watermark;
  var $jg_watermarkpos;
  var $jg_watermarkzoom;
  var $jg_watermarksize;
  var $jg_bigpic;
  var $jg_bigpic_unreg;
  var $jg_bigpic_open;
  var $jg_bbcodelink;
  var $jg_showcommentsunreg;
  var $jg_showcommentsarea;
  var $jg_send2friend;
  var $jg_minis;
  var $jg_motionminis;
  var $jg_motionminiWidth;
  var $jg_motionminiHeight;
  var $jg_motionminiLimit;
  var $jg_miniWidth;
  var $jg_miniHeight;
  var $jg_minisprop;
  var $jg_nameshields;
  var $jg_nameshields_others;
  var $jg_nameshields_unreg;
  var $jg_show_nameshields_unreg;
  var $jg_nameshields_height;
  var $jg_nameshields_width;
  var $jg_slideshow;
  var $jg_slideshow_timer;
  var $jg_slideshow_transition;
  var $jg_slideshow_transtime;
  var $jg_slideshow_maxdimauto;
  var $jg_slideshow_width;
  var $jg_slideshow_heigth;
  var $jg_slideshow_infopane;
  var $jg_slideshow_carousel;
  var $jg_slideshow_arrows;
  var $jg_slideshow_repeat;
  var $jg_showexifdata;
  var $jg_showgeotagging;
  var $jg_geotaggingkey;
  var $jg_subifdtags;
  var $jg_ifdotags;
  var $jg_gpstags;
  var $jg_showiptcdata;
  var $jg_iptctags;
  var $jg_showtoplist;
  var $jg_toplist;
  var $jg_topthumbalign;
  var $jg_toptextalign;
  var $jg_toplistcols;
  var $jg_whereshowtoplist;
  var $jg_showrate;
  var $jg_showlatest;
  var $jg_showcom;
  var $jg_showthiscomment;
  var $jg_showmostviewed;
  var $jg_showtoplistdownload;
  var $jg_showtoplistfavourite;
  var $jg_toplist_report_images;
  var $jg_showtoplisteditorlinks;
  var $jg_favourites;
  var $jg_favouritesshownotauth;
  var $jg_maxfavourites;
  var $jg_zipdownload;
  var $jg_usefavouritesforpubliczip;
  var $jg_usefavouritesforzip;
  var $jg_allimagesofcategory;
  var $jg_showfavouritesdownload;
  var $jg_showfavouriteseditorlinks;
  var $jg_search;
  var $jg_searchcols;
  var $jg_searchthumbalign;
  var $jg_searchtextalign;
  var $jg_showsearchdownload;
  var $jg_showsearchfavourite;
  var $jg_search_report_images;
  var $jg_showsearcheditorlinks;

  /**
   * The ID of the current config row
   *
   * @var int
   */
  protected $_id = 0;

  /**
   * Determines whether extended configuration
   * manager is enabled
   *
   * @var boolean
   */
  protected $_extended = false;

  /**
   * Constructor
   *
   * @return  void
   * @since   1.5.5
   */
  public function __construct($id = null)
  {
    if(JComponentHelper::getParams(_JOOM_OPTION)->get('extended_config'))
    {
      $this->_extended = true;
    }

    if(!is_null($id) || !$this->_extended)
    {
      if(!$this->_extended)
      {
        // If extended configuration manager is
        // disabled we use the default config row
        $id = 1;
      }

      $id = intval($id);

      $this->_id = $id;

      $config = JTable::getInstance('joomgalleryconfig', 'Table');
      $config->load($this->_id);

      // Get config values
      $properties = $config->getProperties();
    }
    else
    {
      $db = JFactory::getDbo();
      $query = $db->getQuery(true)
            ->select('*')
            ->from(_JOOM_TABLE_CONFIG)
            ->where('group_id IN ('.implode(',', JFactory::getUser()->getAuthorisedGroups()).')')
            ->order('ordering DESC');
      $db->setQuery($query, 0, 1);
      if(!$properties = $db->loadAssoc())
      {
        JError::raiseError(500, JText::_('Error loading config data'));
      }

      $this->_id = $properties['id'];
    }

    // Populate configuration values
    unset($properties['id']);
    unset($properties['group_id']);
    unset($properties['ordering']);
    foreach($properties as $key => $value)
    {
      $this->$key = $value;
    }
  }

  /**
   * Returns a reference to the global Config object, only creating it if it
   * doesn't already exist.
   *
   * This method must be invoked as:
   *    <pre>  $config = JoomAmbit::getInstance();</pre>
   *
   * @param   mixed       The ID of the requested configuration row or string 'admin'
                          if additional handling for storing the config is necessary
   * @return  JoomConfig  The Config object.
   * @since   1.5.5
   */
  public static function getInstance($id = null)
  {
    static $instances;

    if(!isset($instances))
    {
      $instances = array();
    }

    if(empty($instances[$id]))
    {
      if($id == 'admin')
      {
        require_once JPATH_ADMINISTRATOR.'/components/'._JOOM_OPTION.'/helpers/adminconfig.php';
        $config = new JoomAdminConfig();
      }
      else
      {
        $config = new JoomConfig($id);
      }

      $instances[$id] = $config;
    }

    return $instances[$id];
  }

  /**
   * Returns true if the extended configuration manager is enabled
   *
   * @return  boolean True if extended configuration manager is enabled, false otherwise
   * @since   2.0
   */
  public function isExtended()
  {
    return $this->_extended;
  }

  /**
   * Returns the CSS file name for the current config row or a specific one
   *
   * @param   int     ID of a specific row for which the CSS file name should be returned, 0 if the current one should be used
   * @return  string  The CSS file name of the current config row
   * @since   2.0
   */
  public function getStyleSheetName($id = 0)
  {
    if($id)
    {
      if($id == 1)
      {
        $id = '';
      }

      return 'joom_settings'.$id.'.css';
    }

    if($this->_id == 1)
    {
      return 'joom_settings.css';
    }

    return 'joom_settings'.$this->_id.'.css';
  }
}