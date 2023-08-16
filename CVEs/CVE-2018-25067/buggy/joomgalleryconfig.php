<?php
// $HeadURL: https://joomgallery.org/svn/joomgallery/JG-3/JG/trunk/administrator/components/com_joomgallery/tables/joomgalleryconfig.php $
// $Id: joomgalleryconfig.php 4267 2013-05-10 11:41:59Z erftralle $
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
 * JoomGallery Configuration Table Class
 *
 * @package JoomGallery
 * @since   1.5.5
 */
class TableJoomgalleryConfig extends JTable
{
  var $id;
  var $group_id = 0;
  var $ordering = 0;
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
   * Constructor
   *
   * @return  void
   * @since   1.5.5
   */
  public function __construct($db)
  {
    parent::__construct(_JOOM_TABLE_CONFIG, 'id', $db);
  }

  /**
   * Checks specific entries in the configuration
   * and makes them ready to save.
   *
   * @return  boolean True, if the configuration may be saved, false otherwise
   * @since   1.5.5
   */
  public function check()
  {
    // Fix the pathes
    $this->jg_pathimages          = $this->fixPath($this->jg_pathimages);
    $this->jg_pathoriginalimages  = $this->fixPath($this->jg_pathoriginalimages);
    $this->jg_paththumbs          = $this->fixPath($this->jg_paththumbs);
    $this->jg_pathftpupload       = $this->fixPath($this->jg_pathftpupload);
    $this->jg_pathtemp            = $this->fixPath($this->jg_pathtemp);
    $this->jg_wmpath              = $this->fixPath($this->jg_wmpath);

    // Arrays, remove the first or the first two entries if more than
    // the first or the second entry are selected because there can't
    // be users chosen as well as 'No User' and 'Default Recipients'.
    if(is_array($this->jg_msg_upload_recipients))
    {
      if(count($this->jg_msg_upload_recipients) > 1 && $this->jg_msg_upload_recipients[0] == -1)
      {
        unset($this->jg_msg_upload_recipients[0]);
      }
      $this->jg_msg_upload_recipients  = implode(',', $this->jg_msg_upload_recipients);
    }

    if(is_array($this->jg_msg_download_recipients))
    {
      if(count($this->jg_msg_download_recipients) > 1 && $this->jg_msg_download_recipients[0] == -1)
      {
        unset($this->jg_msg_download_recipients[0]);
      }
      $this->jg_msg_download_recipients  = implode(',', $this->jg_msg_download_recipients);
    }

    if(is_array($this->jg_msg_comment_recipients))
    {
      if(count($this->jg_msg_comment_recipients) > 1 && ($this->jg_msg_comment_recipients[0] == 0 || $this->jg_msg_comment_recipients[0] == -1))
      {
        unset($this->jg_msg_comment_recipients[0]);

        if(count($this->jg_msg_comment_recipients) > 1 && $this->jg_msg_comment_recipients[1] == -1)
        {
          unset($this->jg_msg_comment_recipients[1]);
        }
      }
      $this->jg_msg_comment_recipients  = implode(',', $this->jg_msg_comment_recipients);
    }

    if(is_array($this->jg_msg_nametag_recipients))
    {
      if(count($this->jg_msg_nametag_recipients) > 1 && ($this->jg_msg_nametag_recipients[0] == 0 || $this->jg_msg_nametag_recipients[0] == -1))
      {
        unset($this->jg_msg_nametag_recipients[0]);

        if(count($this->jg_msg_nametag_recipients) > 1 && $this->jg_msg_nametag_recipients[1] == -1)
        {
          unset($this->jg_msg_nametag_recipients[1]);
        }
      }
      $this->jg_msg_nametag_recipients  = implode(',', $this->jg_msg_nametag_recipients);
    }

    if(is_array($this->jg_msg_report_recipients))
    {
      if(count($this->jg_msg_report_recipients) > 1 && $this->jg_msg_report_recipients[0] == -1)
      {
        unset($this->jg_msg_report_recipients[0]);
      }
      $this->jg_msg_report_recipients  = implode(',', $this->jg_msg_report_recipients);
    }

    if(is_array($this->jg_usercatorderlist))
    {
      $this->jg_usercatorderlist  = implode(',', $this->jg_usercatorderlist);
    }

    // When no array there are no ticked checkboxes submitted per $_POST
    if(is_array($this->jg_subifdtags))
    {
      $subifdtags     = $this->jg_subifdtags;
      $subifdtags_new = array();
      if($subifdtags)
      {
        foreach($subifdtags as $subifdtag)
        {
          $subifdtag = intval($subifdtag);
          if($subifdtag > 0)
          {
            array_push($subifdtags_new, $subifdtag);
          }
        }
      }
      $this->jg_subifdtags = implode(',', $subifdtags_new);
    }
      else
    {
      $this->jg_subifdtags = '';
    }

    // When no array there are no ticked checkboxes submitted per $_POST
    if(is_array($this->jg_ifdotags))
    {
      $ifdotags     = $this->jg_ifdotags;
      $ifdotags_new = array();
      if($ifdotags)
      {
        foreach($ifdotags as $ifdotag)
        {
          $ifdotag = intval($ifdotag);
          if($ifdotag > 0)
          {
            array_push($ifdotags_new, $ifdotag);
          }
        }
      }
      $this->jg_ifdotags = implode(',', $ifdotags_new);
    }
    else
    {
      $this->jg_ifdotags = '';
    }

    // When no array there are no ticked checkboxes submitted per $_POST
    if(is_array($this->jg_gpstags))
    {
      $gpstags      = $this->jg_gpstags;
      $gpstags_new  = array();
      if($gpstags)
      {
        foreach($gpstags as $gpstag)
        {
          $gpstag = intval($gpstag);
          if($gpstag >= 0)
          {
            array_push($gpstags_new, $gpstag);
          }
        }
      }
      $this->jg_gpstags = implode(',', $gpstags_new);
    }
    else
    {
      $this->jg_gpstags = '';
    }

    // When no array there are no ticked checkboxes submitted per $_POST
    if(is_array($this->jg_iptctags))
    {
      $iptctags     = $this->jg_iptctags;
      $iptctags_new = array();
      if($iptctags)
      {
        foreach($iptctags as $iptctag)
        {
          $iptctag = intval($iptctag);
          if($iptctag >= 0)
          {
            array_push($iptctags_new, $iptctag);
          }
        }
      }
      $this->jg_iptctags = implode(',', $iptctags_new);
    }
    else
    {
      $this->jg_iptctags = '';
    }

    // Check ordering
    if($this->id == 1)
    {
      $this->ordering = 1;
    }
    else
    {
      if($this->ordering <= 1)
      {
        $this->ordering = 2;
      }
    }

    $corrected  = false;
    $items = explode(',', $this->jg_filenamereplace);
    $this->jg_filenamereplace = '';
    if($items != FALSE)
    {
      // Contains pairs of <specialchar>|<replaced char(s)>
      foreach ($items as $item)
      {
        if (!empty($item))
        {
          $workarray = explode('|', trim($item));
          if(    $workarray != FALSE
              && isset($workarray[0]) && !empty($workarray[0])
              && isset($workarray[1]) && !empty($workarray[1])
            )
          {
            $this->jg_filenamereplace .= $workarray[0]
                                       . '|'
                                       . $workarray[1]
                                       . ',';
          }
          else
          {
            $corrected = true;
          }
        }
      }
      $this->jg_filenamereplace = trim($this->jg_filenamereplace, ',');
    }
    else
    {
      $this->jg_filenamereplace = '';
    }

    if($corrected)
    {
      JError::raiseNotice(500, JText::_('COM_JOOMGALLERY_CONFIG_MSG_SETTINGS_FOR_SPECIALCHARS_CORRECTED'));
    }

    return true;
  }

  /**
   * Modify path
   *
   * 1. trim '/' from path and append one
   *
   * @param   string  $path The path to fix
   * @return  string  Modified path or root warning
   * @since   1.5.0
   */
  public function fixPath($path)
  {
    if(!JFolder::exists(JPATH_ROOT.'/'.$path))
    {
      // We assume that it's an absolute path,
      // so trim '/' and '\' only on the right side and append one '/'
      $path = JPath::clean(rtrim($path, '\/').'/');
    }
    else
    {
      // Trim '/' and '\' on both sides and append slash
      // Additionally there should also be only '/' in the path
      $path = trim($path, '\/').'/';
      $path = str_replace('\\', '/', $path);
    }

    if($path == '/' || $path == '\\')
    {
      return 'PLEASE_DO_NOT_USE_JOOMLA_ROOT';
    }

    return $path;
  }
}