<?php defined('_JEXEC') or die('Direct Access to this location is not allowed.'); ?>
<form action="index.php" method="post" id="adminForm" name="adminForm" class="form-inline">
<?php if(!empty($this->sidebar)): ?>
  <div id="j-sidebar-container" class="span2">
    <?php echo $this->sidebar; ?>
  </div>
  <div id="j-main-container" class="span10">
<?php else : ?>
  <div id="j-main-container">
<?php endif;
      if($this->_config->isExtended()):
        JHtml::_('bootstrap.tooltip'); ?>
    <div class="hasTooltip pull-right" title="<?php echo JHtml::tooltipText('COM_JOOMGALLERY_CONFIG_PROPAGATE_CHANGES', 'COM_JOOMGALLERY_CONFIG_PROPAGATE_CHANGES_TIPTEXT'); ?>">
      <label for="propagate_changes"><?php echo JText::_('COM_JOOMGALLERY_CONFIG_PROPAGATE_CHANGES'); ?></label>
      <input type="checkbox" id="propagate_changes" name="propagate_changes" value="1" />
    </div>
<?php   endif;

// start nested MainPane
echo JHtml::_('tabs.start', 'NestedmainPane', array('useCookie' => 1));
// start first nested MainTab "Grundlegende Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'NestedMainPane1');
// start first nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneOne', array('useCookie' => 1));

// start Tab "Grundlegende Einstellungen->Pfade und Verzeichnisse"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_PATH_DIRECTORIES'), 'nested-one');

JHtml::_('joomconfig.start', 'page1');
    JHtml::_('joomconfig.intro', JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_PD_CSS_CONFIGURATION_INTRO', $this->_config->getStyleSheetName(), $this->cssfilemsg));
    JHtml::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_DIRECTORIES_INTRO'));
if($this->display):
    $field = '<input size="60" type="text" name="jg_pathimages" value="'.$this->_config->jg_pathimages.'" /><br />['.$this->write_pathimages.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_COMMON_IMAGE_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_IMAGES_STORED'));
    $field = '<input size="60" type="text" name="jg_pathoriginalimages" value="'.$this->_config->jg_pathoriginalimages.'" /><br />['.$this->write_pathoriginalimages.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_ORIGINALS_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_ORIGINALS_STORED'));
    $field = '<input size="60" type="text" name="jg_paththumbs" value="'.$this->_config->jg_paththumbs.'" /><br />['.$this->write_paththumbs.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_THUMBNAILS_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_THUMBNAILS_STORED'));
endif;
    $field = '<input size="60" type="text" name="jg_pathftpupload" value="'.$this->_config->jg_pathftpupload.'" /><br />['.$this->write_pathftpupload.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_FTPUPLOAD_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_FOR_FTPUPLOAD'));
if($this->display):
    $field = '<input size="60" type="text" name="jg_pathtemp" value="'.$this->_config->jg_pathtemp.'" /><br />['.$this->write_pathtemp.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_TEMP_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_FOR_TEMP'));
endif;
    $field = '<input size="60" type="text" name="jg_wmpath" value="'.$this->_config->jg_wmpath.'" /><br />['.$this->write_pathwm.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_WATERMARK_PATH', $field, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_PD_PATH_WATERMARK_STORED'));
    $field = '<input size="60" type="text" name="jg_wmfile" value="'.$this->_config->jg_wmfile.'" /><br />['.$this->wmfilemsg.']';
    JHtml::_('joomconfig.row', 'jg_pathimages', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_PD_WATERMARK_FILE', $field);
    JHtml::_('joomconfig.row', 'jg_use_real_paths', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_PD_USE_REAL_PATHS', $this->_config->jg_use_real_paths);
    JHtml::_('joomconfig.row', 'jg_checkupdate', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_PD_CHECKUPDATE', $this->_config->jg_checkupdate);
JHtml::_('joomconfig.end');

if($this->display):
// start Tab "Grundlegende Einstellungen->Ersetzungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_BACKEND_REPLACEMENTS'), 'nested-two');

JHTML::_('joomconfig.start', 'page2');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_GS_RP_BACKEND_REPLACEMENTS_INTRO'));
    JHTML::_('joomconfig.row', 'jg_filenamewithjs', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_RP_FILENAME_WITHJS', $this->_config->jg_filenamewithjs);
    $tl_jg_filenamereplace = '<textarea name="jg_filenamereplace" cols="60" rows="10" >'.$this->_config->jg_filenamereplace.'</textarea>';
    JHTML::_('joomconfig.row', 'jg_filenamereplace', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_RP_FILENAME_REPLACE', $tl_jg_filenamereplace);
JHTML::_('joomconfig.end');

// start Tab "Grundlegende Einstellungen->Bildmanipulation"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_IMAGE_PROCESSING'), 'nested-three');

JHTML::_('joomconfig.start', 'page3');
    JHTML::_('joomconfig.intro', '<div align="center"><strong>'.$this->gdmsg.'</strong></div>');
    $thumbcreator[] = JHTML::_('select.option','gd1', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_GDLIB'));
    $thumbcreator[] = JHTML::_('select.option','gd2', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_GD2LIB'));
    $thumbcreator[] = JHTML::_('select.option','im', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_IMAGEMAGICK'));
    $mc_jg_thumbcreation = JHTML::_('select.genericlist',$thumbcreator, 'jg_thumbcreation', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_thumbcreation);
    JHTML::_('joomconfig.row', 'jg_thumbcreation', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_IP_IMAGE_CREATOR', $mc_jg_thumbcreation);
    JHTML::_('joomconfig.row', 'jg_fastgd2thumbcreation', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_IP_FAST_GD2_THUMBCREATION', $this->_config->jg_fastgd2thumbcreation);
    $tl_jg_impath = '<input type="text" name="jg_impath" value="'.$this->_config->jg_impath.'" size="50" />';
    JHTML::_('joomconfig.row', 'jg_impath', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_IP_PATH_TO_IMAGEMAGICK', $tl_jg_impath, true, '', $this->immsg);
    JHTML::_('joomconfig.row', 'jg_resizetomaxwidth', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_IP_RESIZING', $this->_config->jg_resizetomaxwidth);
    JHTML::_('joomconfig.row', 'jg_maxwidth', 'text', 'COM_JOOMGALLERY_CONFIG_GS_IP_MAX_WIDTH', $this->_config->jg_maxwidth);
    JHTML::_('joomconfig.row', 'jg_picturequality', 'text', 'COM_JOOMGALLERY_CONFIG_GS_IP_IMAGE_QUALITY', $this->_config->jg_picturequality);
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_THUMBNAILS_INTRO'));
    $directionresize[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_SAMEHIGHT'));
    $directionresize[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_SAMEWIDTH'));
    $directionresize[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FREEHEIGHTWIDTH'));
    $mc_jg_useforresizedirection = JHTML::_('select.genericlist',$directionresize, 'jg_useforresizedirection', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_useforresizedirection);
    JHTML::_('joomconfig.row', 'jg_useforresizedirection', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_IP_DIRECTION_RESIZE', $mc_jg_useforresizedirection);
    $cropposition[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONLU'));
    $cropposition[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONRU'));
    $cropposition[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONC'));
    $cropposition[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONLL'));
    $cropposition[] = JHTML::_('select.option','4', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONRL'));
    $mc_jg_cropposition = JHTML::_('select.genericlist',$cropposition, 'jg_cropposition', 'class="inputbox" size="5"', 'value', 'text', $this->_config->jg_cropposition);
    JHTML::_('joomconfig.row', 'jg_cropposition', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITION', $mc_jg_cropposition);
    JHTML::_('joomconfig.row', 'jg_thumbwidth', 'text', 'COM_JOOMGALLERY_CONFIG_GS_IP_THUMBNAIL_WIDTH', $this->_config->jg_thumbwidth);
    JHTML::_('joomconfig.row', 'jg_thumbheight', 'text', 'COM_JOOMGALLERY_CONFIG_GS_IP_THUMBNAIL_HEIGHT', $this->_config->jg_thumbheight);
    JHTML::_('joomconfig.row', 'jg_thumbquality', 'text', 'COM_JOOMGALLERY_CONFIG_GS_IP_THUMBNAIL_QUALITY', $this->_config->jg_thumbquality);
JHTML::_('joomconfig.end');
endif;

// start Tab "Grundlegende Einstellungen->Backend-Upload"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_BACKEND_UPLOAD'), 'nested-four');

JHTML::_('joomconfig.start', 'page4');
    $uploadordering[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_NO_ORDER'));
    $uploadordering[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_DESCENDING'));
    $uploadordering[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_ASCENDING'));
    $mc_jg_uploadorder = JHTML::_('select.genericlist',$uploadordering, 'jg_uploadorder', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_uploadorder);
    JHTML::_('joomconfig.row', 'jg_uploadorder', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_BU_UPLOAD_ORDER', $mc_jg_uploadorder);
    JHTML::_('joomconfig.row', 'jg_useorigfilename', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_ORIGINAL_FILENAME', $this->_config->jg_useorigfilename);
    JHTML::_('joomconfig.row', 'jg_filenamenumber', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_BU_FILENAMENUMBER', $this->_config->jg_filenamenumber);
    $delete_original[] = JHTML::_('select.option','0', JText::_('JNO'));
    $delete_original[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ALL_ORIGINALS'));
    $delete_original[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ORIGINAL_CHECKBOX'));
    $mc_jg_delete_original = JHTML::_('select.genericlist',$delete_original, 'jg_delete_original', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_delete_original);
    JHTML::_('joomconfig.row', 'jg_delete_original', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ORIGINAL', $mc_jg_delete_original);
JHTML::_('joomconfig.end');

// start Tab "Grundlegende Einstellungen->Benachrichtigungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_MESSAGES_SETTINGS'), 'nested-five');

JHTML::_('joomconfig.start', 'page5');
    $message_type[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_OPTION_NONE'));
    $message_type[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_OPTION_MAIL'));
    $message_type[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_OPTION_PM'));
    $message_type[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_OPTION_BOTH'));
    $mc_jg_msg_upload_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_upload_type', 'class="inputbox" size="4"', 'value', 'text', $this->_config->get('jg_msg_upload_type'));
    JHTML::_('joomconfig.row', 'jg_msg_upload_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_UPLOAD_TYPE', $mc_jg_msg_upload_type);
    $arr_jg_msg_upload_recipients   = $this->_config->get('jg_msg_upload_recipients') ? explode(',', $this->_config->get('jg_msg_upload_recipients')) : array(-1);
    $list = JHTML::_('joomselect.users', 'jg_msg_upload_recipients[]', $arr_jg_msg_upload_recipients, false, array(-1 => JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DEFAULT_RECIPIENTS')));
    JHTML::_('joomconfig.row', 'jg_msg_upload_recipients', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_UPLOAD_RECIPIENTS', $list, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_UPLOAD_RECIPIENTS_LONG').(JHtmlJoomSelect::$count > $this->_config->get('jg_use_listbox_max_user_count') ? '<br /><br />'.JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS', $this->_config->get('jg_use_listbox_max_user_count')) : ''));
    $mc_jg_msg_download_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_download_type', 'class="inputbox" size="4"', 'value', 'text', $this->_config->get('jg_msg_download_type'));
    JHTML::_('joomconfig.row', 'jg_msg_download_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_DOWNLOAD_TYPE', $mc_jg_msg_download_type);
    $arr_jg_msg_download_recipients   = $this->_config->get('jg_msg_download_recipients') ? explode(',', $this->_config->get('jg_msg_download_recipients')) : array(-1);
    $list = JHTML::_('joomselect.users', 'jg_msg_download_recipients[]', $arr_jg_msg_download_recipients, false, array(-1 => JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DEFAULT_RECIPIENTS')));
    JHTML::_('joomconfig.row', 'jg_msg_download_recipients', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_DOWNLOAD_RECIPIENTS', $list, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DOWNLOAD_RECIPIENTS_LONG').(JHtmlJoomSelect::$count > $this->_config->get('jg_use_listbox_max_user_count') ? '<br /><br />'.JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS', $this->_config->get('jg_use_listbox_max_user_count')) : ''));
    JHTML::_('joomconfig.row', 'jg_msg_zipdownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_ZIPDOWNLOAD', $this->_config->jg_msg_zipdownload);
    $mc_jg_msg_comment_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_comment_type', 'class="inputbox" size="4"', 'value', 'text', $this->_config->get('jg_msg_comment_type'));
    JHTML::_('joomconfig.row', 'jg_msg_comment_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_COMMENT_TYPE', $mc_jg_msg_comment_type);
    $arr_jg_msg_comment_recipients  = strlen($this->_config->get('jg_msg_comment_recipients')) ? explode(',', $this->_config->get('jg_msg_comment_recipients')) : array(-1);
    $list = JHTML::_('joomselect.users', 'jg_msg_comment_recipients[]', $arr_jg_msg_comment_recipients, true, array(-1 => JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DEFAULT_RECIPIENTS')));
    JHTML::_('joomconfig.row', 'jg_msg_comment_recipients', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_COMMENT_RECIPIENTS', $list, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_COMMENT_RECIPIENTS_LONG').(JHtmlJoomSelect::$count > $this->_config->get('jg_use_listbox_max_user_count') ? '<br /><br />'.JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS', $this->_config->get('jg_use_listbox_max_user_count')).' '.JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS_ADDITION') : ''));
    JHTML::_('joomconfig.row', 'jg_msg_comment_toowner', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_COMMENT_TOOWNER', $this->_config->get('jg_msg_comment_toowner'));
    $mc_jg_msg_nametag_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_nametag_type', 'class="inputbox" size="4"', 'value', 'text', $this->_config->get('jg_msg_nametag_type'));
    JHTML::_('joomconfig.row', 'jg_msg_nametag_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_NAMETAG_TYPE', $mc_jg_msg_nametag_type);
    $arr_jg_msg_nametag_recipients  = strlen($this->_config->get('jg_msg_nametag_recipients')) ? explode(',', $this->_config->get('jg_msg_nametag_recipients')) : array(-1);
    $list = JHTML::_('joomselect.users', 'jg_msg_nametag_recipients[]', $arr_jg_msg_nametag_recipients, true, array(-1 => JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DEFAULT_RECIPIENTS')));
    JHTML::_('joomconfig.row', 'jg_msg_nametag_recipients', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_NAMETAG_RECIPIENTS', $list, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_NAMETAG_RECIPIENTS_LONG').(JHtmlJoomSelect::$count > $this->_config->get('jg_use_listbox_max_user_count') ? '<br /><br />'.JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS', $this->_config->get('jg_use_listbox_max_user_count')).' '.JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS_ADDITION') : ''));
    JHTML::_('joomconfig.row', 'jg_msg_nametag_totaggeduser', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_NAMETAG_TOTAGGEDUSER', $this->_config->get('jg_msg_nametag_totaggeduser'));
    JHTML::_('joomconfig.row', 'jg_msg_nametag_toowner', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_NAMETAG_TOOWNER', $this->_config->get('jg_msg_nametag_toowner'));
    $mc_jg_msg_report_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_report_type', 'class="inputbox" size="4"', 'value', 'text', $this->_config->get('jg_msg_report_type'));
    JHTML::_('joomconfig.row', 'jg_msg_report_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_REPORT_TYPE', $mc_jg_msg_report_type);
    $arr_jg_msg_report_recipients  = $this->_config->get('jg_msg_report_recipients') ? explode(',', $this->_config->get('jg_msg_report_recipients')) : array(-1);
    $list = JHTML::_('joomselect.users', 'jg_msg_report_recipients[]', $arr_jg_msg_report_recipients, false, array(-1 => JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_DEFAULT_RECIPIENTS')));
    JHTML::_('joomconfig.row', 'jg_msg_report_recipients', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_REPORT_RECIPIENTS', $list, true, '', JText::_('COM_JOOMGALLERY_CONFIG_GS_MS_REPORT_RECIPIENTS_LONG').(JHtmlJoomSelect::$count > $this->_config->get('jg_use_listbox_max_user_count') ? '<br /><br />'.JText::sprintf('COM_JOOMGALLERY_CONFIG_GS_MS_RECIPIENTS_MANY_USERS', $this->_config->get('jg_use_listbox_max_user_count')) : ''));
    JHTML::_('joomconfig.row', 'jg_msg_report_toowner', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_REPORT_TOOWNER', $this->_config->get('jg_msg_report_toowner'));
    unset($message_type[0]);
    $mc_jg_msg_rejectimg_type = JHTML::_('select.genericlist', $message_type, 'jg_msg_rejectimg_type', 'class="inputbox" size="3"', 'value', 'text', $this->_config->get('jg_msg_rejectimg_type'));
    JHTML::_('joomconfig.row', 'jg_msg_rejectimg_type', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_MS_REJECTIMG_TYPE', $mc_jg_msg_rejectimg_type);
    JHTML::_('joomconfig.row', 'jg_msg_global_from', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_MS_GLOBAL_FROM', $this->_config->get('jg_msg_global_from'));
JHTML::_('joomconfig.end');

// start Tab "Grundlegende Einstellungen->Performance Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_GS_TAB_PERFORMANCE_SETTINGS'), 'nested-six');

JHTML::_('joomconfig.start', 'page6');
    JHTML::_('joomconfig.row', 'jg_ajaxcategoryselection', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_PS_AJAXCATEGORYSELECTION', $this->_config->get('jg_ajaxcategoryselection'));
    JHTML::_('joomconfig.row', 'jg_disableunrequiredchecks', 'yesno', 'COM_JOOMGALLERY_CONFIG_GS_PS_DISABLEUNREQUIREDCHECKS', $this->_config->get('jg_disableunrequiredchecks'));
    JHTML::_('joomconfig.row', 'jg_use_listbox_max_user_count', 'text', 'COM_JOOMGALLERY_CONFIG_GS_PS_USELISTBOXMAXUSERCOUNT', $this->_config->get('jg_use_listbox_max_user_count'));
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start second nested MainTab "Benutzer-Rechte"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_USER_ACCESS_RIGHTS'), 'NestedMainPane2');
// start second nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneTwo', array('useCookie' => 1));
// start Tab "Benutzer-Rechte->Benutzer-Upload ueber 'Meine Galerie'"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_UR_UU_USER_UPLOAD_SETTINGS'), 'nested-seven');

JHTML::_('joomconfig.start', 'page7');
    JHTML::_('joomconfig.row', 'jg_userspace', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_USERSPACE', $this->_config->jg_userspace);
    $useruploaddefaultcat[] = JHTML::_('select.option','0', JText::_('JNONE'));
    $useruploaddefaultcat[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_UR_UU_DEFAULT_CAT_OLDEST_ALL'));
    $useruploaddefaultcat[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_UR_UU_DEFAULT_CAT_NEWEST_ALL'));
    $useruploaddefaultcat[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_UR_UU_DEFAULT_CAT_OLDEST_OWN'));
    $useruploaddefaultcat[] = JHTML::_('select.option','4', JText::_('COM_JOOMGALLERY_CONFIG_UR_UU_DEFAULT_CAT_NEWEST_OWN'));
    $mc_jg_useruploaddefaultcat = JHTML::_('select.genericlist', $useruploaddefaultcat, 'jg_useruploaddefaultcat', 'class="inputbox" size="5"', 'value', 'text', $this->_config->jg_useruploaddefaultcat);
    JHTML::_('joomconfig.row', 'jg_useruploaddefaultcat', 'custom', 'COM_JOOMGALLERY_CONFIG_UR_UU_DEFAULT_CAT', $mc_jg_useruploaddefaultcat);
    JHTML::_('joomconfig.row', 'jg_approve', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_APPROVAL_NEEDED', $this->_config->jg_approve);
    JHTML::_('joomconfig.row', 'jg_unregistered_permissions', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UNREGISTERED_PERMISSIONS', $this->_config->jg_unregistered_permissions);
    JHTML::_('joomconfig.row', 'jg_usercatacc', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_USERCATACCESS', $this->_config->jg_usercatacc);
    JHTML::_('joomconfig.row', 'jg_usercatthumbalign', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_USERCATTHUMBALIGN', $this->_config->jg_usercatthumbalign);
    JHTML::_('joomconfig.row', 'jg_maxusercat', 'text', 'COM_JOOMGALLERY_CONFIG_UR_UU_MAX_USERCATS', $this->_config->jg_maxusercat);
    JHTML::_('joomconfig.row', 'jg_maxuserimage', 'text', 'COM_JOOMGALLERY_CONFIG_UR_UU_MAX_IMAGES', $this->_config->jg_maxuserimage);
    JHTML::_('joomconfig.row', 'jg_maxuserimage_timespan', 'text', 'COM_JOOMGALLERY_CONFIG_UR_UU_MAX_IMAGES_TIMESPAN', $this->_config->jg_maxuserimage_timespan);
    JHTML::_('joomconfig.row', 'jg_maxfilesize', 'text', 'COM_JOOMGALLERY_CONFIG_UR_UU_MAX_FILESIZE', $this->_config->jg_maxfilesize);
    JHTML::_('joomconfig.row', 'jg_useruploadsingle', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UPLOAD_SINGLE', $this->_config->jg_useruploadsingle);
    JHTML::_('joomconfig.row', 'jg_maxuploadfields', 'text', 'COM_JOOMGALLERY_CONFIG_UR_UU_MAX_UPLOADFIELDS', $this->_config->jg_maxuploadfields);
    JHTML::_('joomconfig.row', 'jg_useruploadajax', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UPLOAD_AJAX', $this->_config->jg_useruploadajax);
    JHTML::_('joomconfig.row', 'jg_useruploadbatch', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UPLOAD_BATCH', $this->_config->jg_useruploadbatch);
    JHTML::_('joomconfig.row', 'jg_useruploadjava', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UPLOAD_JAVA', $this->_config->jg_useruploadjava);
    JHTML::_('joomconfig.row', 'jg_useruseorigfilename', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_ORIGINAL_FILENAME', $this->_config->jg_useruseorigfilename);
    JHTML::_('joomconfig.row', 'jg_useruploadnumber', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_NUMBERING', $this->_config->jg_useruploadnumber);
    JHTML::_('joomconfig.row', 'jg_special_gif_upload', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_SPECIAL_GIF_UPLOAD', $this->_config->jg_special_gif_upload);
    $delete_original_user[] = JHTML::_('select.option','0', JText::_('JNO'));
    $delete_original_user[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ALL_ORIGINALS'));
    $delete_original_user[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ORIGINAL_CHECKBOX'));
    $mc_jg_delete_original_user = JHTML::_('select.genericlist', $delete_original_user, 'jg_delete_original_user', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_delete_original_user);
    JHTML::_('joomconfig.row', 'jg_delete_original_user', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_BU_DELETE_ORIGINAL', $mc_jg_delete_original_user);
    JHTML::_('joomconfig.row', 'jg_newpiccopyright', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_COPYRIGHT', $this->_config->jg_newpiccopyright);
    JHTML::_('joomconfig.row', 'jg_newpicnote', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_UPLOADNOTE', $this->_config->jg_newpicnote);
    $redirect_after_upload[] = JHTML::_('select.option','0', JText::_('JNO'));
    $redirect_after_upload[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_REDIRECT_AFTER_UPLOAD_TO_UPLOAD_VIEW'));
    $redirect_after_upload[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_REDIRECT_AFTER_UPLOAD_TO_USERPANEL'));
    $redirect_after_upload[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_GS_BU_REDIRECT_AFTER_UPLOAD_TO_GALLERY_VIEW'));
    $mc_jg_redirect_after_upload = JHTML::_('select.genericlist', $redirect_after_upload, 'jg_redirect_after_upload', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_redirect_after_upload);
    JHTML::_('joomconfig.row', 'jg_redirect_after_upload', 'custom', 'COM_JOOMGALLERY_CONFIG_GS_BU_REDIRECT_AFTER_UPLOAD', $mc_jg_redirect_after_upload);
    JHtml::_('joomconfig.row', 'jg_edit_metadata', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UU_EDIT_METADATA', $this->_config->jg_edit_metadata);
JHTML::_('joomconfig.end');

// start Tab "Benutzer-Rechte->Download"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_UR_UD_USER_DOWNLOAD_SETTINGS'), 'nested-seven1');

JHTML::_('joomconfig.start', 'page71');
    JHTML::_('joomconfig.row', 'jg_download', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UD_DOWNLOAD', $this->_config->jg_download);
    JHTML::_('joomconfig.row', 'jg_download_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UD_DOWNLOAD_UNREG', $this->_config->jg_download_unreg, $this->display);
    JHTML::_('joomconfig.row', 'jg_download_hint', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UD_DOWNLOAD_HINT', $this->_config->jg_download_hint);
    $downloadfile[] = JHTML::_('select.option', 0, JText::_('COM_JOOMGALLERY_CONFIG_UR_UD_DETAIL_ONLY'));
    $downloadfile[] = JHTML::_('select.option', 1, JText::_('COM_JOOMGALLERY_CONFIG_UR_UD_ORIGINAL_ONLY'));
    $downloadfile[] = JHTML::_('select.option', 2, JText::_('COM_JOOMGALLERY_CONFIG_UR_UD_DETAIL_IFNO_ORIGINAL'));
    $mc_jg_downloadfile = JHTML::_('select.genericlist', $downloadfile, 'jg_downloadfile', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_downloadfile);
    JHTML::_('joomconfig.row', 'jg_downloadfile', 'custom', 'COM_JOOMGALLERY_CONFIG_UR_UD_DOWNLOADFILE', $mc_jg_downloadfile);
    JHTML::_('joomconfig.row', 'jg_downloadwithwatermark', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_UD_DOWNLOADWITHWATERMARK', $this->_config->jg_downloadwithwatermark);
JHTML::_('joomconfig.end');

// start Tab "Benutzer-Rechte->Bewertungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_UR_TAB_RATING_SETTINGS'), 'nested-eight');

JHTML::_('joomconfig.start', 'page8');
    JHTML::_('joomconfig.row', 'jg_showrating', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RT_RATING', $this->_config->jg_showrating);
    JHTML::_('joomconfig.row', 'jg_maxvoting', 'text', 'COM_JOOMGALLERY_CONFIG_UR_RT_HIGHEST_RATING', $this->_config->jg_maxvoting);

    $ratingcalctype[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_UR_RT_CALC_TYPE_STANDARD'));
    $ratingcalctype[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_UR_RT_CALC_TYPE_BAYES1'));
    $mc_jg_ratingcalctype = JHTML::_('select.genericlist', $ratingcalctype, 'jg_ratingcalctype', 'class="inputbox" size="2"', 'value', 'text', $this->_config->jg_ratingcalctype);
    JHTML::_('joomconfig.row', 'jg_ratingcalctype', 'custom', 'COM_JOOMGALLERY_CONFIG_UR_RT_CALC_TYPE', $mc_jg_ratingcalctype);
    $ratingdisplaytype[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_UR_RT_DISPLAY_TYPE_TEXT'));
    $ratingdisplaytype[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_UR_RT_DISPLAY_TYPE_GRAPHIC'));
    $mc_jg_ratingdisplaytype = JHTML::_('select.genericlist', $ratingdisplaytype, 'jg_ratingdisplaytype', 'class="inputbox" size="2"', 'value', 'text', $this->_config->jg_ratingdisplaytype);
    JHTML::_('joomconfig.row', 'jg_ratingdisplaytype', 'custom', 'COM_JOOMGALLERY_CONFIG_UR_RT_DISPLAY_TYPE', $mc_jg_ratingdisplaytype);
    JHTML::_('joomconfig.row', 'jg_ajaxrating', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RT_AJAX', $this->_config->jg_ajaxrating);
    JHTML::_('joomconfig.row', 'jg_votingonlyonce', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RT_VOTING_ONLY_ONCE', $this->_config->jg_votingonlyonce);
    JHTML::_('joomconfig.row', 'jg_votingonlyreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RT_VOTING_ONLY_REG', $this->_config->jg_votingonlyreg);
    JHTML::_('joomconfig.end');

// start Tab "Benutzer-Rechte->Kommentare"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_UR_TAB_COMMENT_SETTINGS'), 'nested-nine');

JHTML::_('joomconfig.start', 'page9');
    JHTML::_('joomconfig.row', 'jg_showcomment', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_COMMENTS', $this->_config->jg_showcomment);
    JHTML::_('joomconfig.row', 'jg_anoncomment', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_ANONYM', $this->_config->jg_anoncomment, $this->display);
    JHTML::_('joomconfig.row', 'jg_namedanoncomment', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_NAMED_ANONYM', $this->_config->jg_namedanoncomment, $this->display);
    JHTML::_('joomconfig.row', 'jg_anonapprovecom', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_APPROVE_NEEDED_ANONYM', $this->_config->get('jg_anonapprovecom'), $this->display);
    JHTML::_('joomconfig.row', 'jg_approvecom', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_APPROVE_NEEDED', $this->_config->jg_approvecom);
    JHTML::_('joomconfig.row', 'jg_bbcodesupport', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_BBCODE', $this->_config->jg_bbcodesupport);
    JHTML::_('joomconfig.row', 'jg_smiliesupport', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_SMILIES', $this->_config->jg_smiliesupport);
    JHTML::_('joomconfig.row', 'jg_anismilie', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_CM_ANISMILIES', $this->_config->jg_anismilie);
    $smiliescolor[] = JHTML::_('select.option','grey', JText::_('COM_JOOMGALLERY_CONFIG_UR_CM_COLOR_GREY'));
    $smiliescolor[] = JHTML::_('select.option','orange', JText::_('COM_JOOMGALLERY_CONFIG_UR_CM_COLOR_ORANGE'));
    $smiliescolor[] = JHTML::_('select.option','yellow', JText::_('COM_JOOMGALLERY_CONFIG_UR_CM_COLOR_YELLOW'));
    $smiliescolor[] = JHTML::_('select.option','blue', JText::_('COM_JOOMGALLERY_CONFIG_UR_CM_COLOR_BLUE'));
    $mc_jg_smiliescolor = JHTML::_('select.genericlist',$smiliescolor, 'jg_smiliescolor', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_smiliescolor);
    JHTML::_('joomconfig.row', 'jg_smiliescolor', 'custom', 'COM_JOOMGALLERY_CONFIG_UR_CM_COLORSMILIES', $mc_jg_smiliescolor);
JHTML::_('joomconfig.end');

// start Tab "Benutzer-Rechte->Beanstandungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_UR_RP_REPORTS_SETTINGS'), 'nested-nine1');

JHTML::_('joomconfig.start', 'page91');
    JHTML::_('joomconfig.row', 'jg_report_images', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RP_REPORT_IMAGES', $this->_config->jg_report_images);
    JHTML::_('joomconfig.row', 'jg_report_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RP_REPORT_UNREG', $this->_config->jg_report_unreg);
    JHTML::_('joomconfig.row', 'jg_report_hint', 'yesno', 'COM_JOOMGALLERY_CONFIG_UR_RP_REPORT_HINT', $this->_config->jg_report_hint);
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start third nested MainTab "Frontend Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_FRONTEND_SETTINGS'), 'NestedMainPane3');
// start third nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneThree', array('useCookie' => 1));
// start Tab "Frontend Einstellungen->Generelle Einstellungen
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-ten');

JHTML::_('joomconfig.start', 'page10');
    JHTML::_('joomconfig.row', 'jg_itemid', 'text', 'COM_JOOMGALLERY_CONFIG_FS_GS_ITEMID', $this->_config->get('jg_itemid'));
    JHTML::_('joomconfig.row', 'jg_alternative_layout', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_GS_ALTERNATIVE_LAYOUT', $this->getComponentLayouts($this->_config->get('jg_alternative_layout')));
    JHTML::_('joomconfig.row', 'jg_anchors', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_ANCHORS', $this->_config->jg_anchors);
    $tooltips[] = JHTML::_('select.option', 0, JText::_('COM_JOOMGALLERY_CONFIG_FS_GS_TOOLTIPS_OPTION_NO_DISPLAY'));
    $tooltips[] = JHTML::_('select.option', 1, JText::_('COM_JOOMGALLERY_CONFIG_FS_GS_TOOLTIPS_OPTION_DEFAULT_STYLE'));
    $tooltips[] = JHTML::_('select.option', 2, JText::_('COM_JOOMGALLERY_CONFIG_FS_GS_TOOLTIPS_OPTION_OWN_STYLE'));
    $mc_jg_tooltips = JHTML::_('select.genericlist', $tooltips, 'jg_tooltips', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_tooltips);
    JHTML::_('joomconfig.row', 'jg_tooltips', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_GS_TOOLTIPS', $mc_jg_tooltips);
    JHTML::_('joomconfig.row', 'jg_contentpluginsenabled', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_CONTENTPLUGINS', $this->_config->get('jg_contentpluginsenabled'));
    JHTML::_('joomconfig.row', 'jg_imgalign', 'text', 'COM_JOOMGALLERY_CONFIG_FS_GS_IMG_ALIGN', $this->_config->jg_imgalign);
    JHTML::_('joomconfig.row', 'jg_dyncrop', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_DYNCROP', $this->_config->jg_dyncrop);
    $dyncropposition[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONLU'));
    $dyncropposition[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONRU'));
    $dyncropposition[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONC'));
    $dyncropposition[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONLL'));
    $dyncropposition[] = JHTML::_('select.option','4', JText::_('COM_JOOMGALLERY_CONFIG_GS_IP_CROP_POSITIONRL'));
    $mc_jg_dyncropposition = JHTML::_('select.genericlist', $dyncropposition, 'jg_dyncropposition', 'class="inputbox" size="5"', 'value', 'text', $this->_config->jg_dyncropposition);
    JHTML::_('joomconfig.row', 'jg_dyncropposition', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_GS_DYNCROP_POSITION', $mc_jg_dyncropposition);
    JHTML::_('joomconfig.row', 'jg_dyncropwidth', 'text', 'COM_JOOMGALLERY_CONFIG_FS_GS_DYNCROP_WIDTH', $this->_config->jg_dyncropwidth);
    JHTML::_('joomconfig.row', 'jg_dyncropheight', 'text', 'COM_JOOMGALLERY_CONFIG_FS_GS_DYNCROP_HEIGHT', $this->_config->jg_dyncropheight);
    $mc_color_value = JHTML::_('joomgallery.colorpicker', 'jg_dyncropbgcol', $this->_config->jg_dyncropbgcol);
    JHTML::_('joomconfig.row', 'jg_dyncropbgcol', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_GS_DYNCROP_BGCOL', $mc_color_value);
    $mc_color_value = '';
    $hideemptycats[] = JHTML::_('select.option', 0, JText::_('JNO'));
    $hideemptycats[] = JHTML::_('select.option', 1, JText::_('COM_JOOMGALLERY_CONFIG_FS_GS_HIDE_ONLY_COMPLETELY_EMPTY_CATEGORIES'));
    $hideemptycats[] = JHTML::_('select.option', 2, JText::_('COM_JOOMGALLERY_CONFIG_FS_GS_HIDE_ALL_EMPTY_CATEGORIES'));
    $mc_jg_hideemptycats = JHTML::_('select.genericlist', $hideemptycats, 'jg_hideemptycats', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_hideemptycats);
    JHTML::_('joomconfig.row', 'jg_hideemptycats', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_GS_HIDE_EMPTY_CATEGORIES', $mc_jg_hideemptycats);
    JHTML::_('joomconfig.row', 'jg_skipcatview', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_SKIPCATVIEW', $this->_config->jg_skipcatview);
    JHTML::_('joomconfig.row', 'jg_showrestrictedcats', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_SHOW_RESTRICTED_CATEGORIES', $this->_config->jg_showrestrictedcats);
    JHTML::_('joomconfig.row', 'jg_showrestrictedhint', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_SHOW_RESTRICTED_CATEGORIES_HINT', $this->_config->jg_showrestrictedhint);
    JHTML::_('joomconfig.row', 'jg_realname', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GS_USERNAME_REALNAME', $this->_config->jg_realname);
    JHTML::_('joomconfig.row', 'jg_showowner', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_GF_OWNER', $this->_config->jg_showowner);
JHTML::_('joomconfig.end');

// start Tab "Frontend Einstellungen->Anordnung der Bilder"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_FS_TAB_IMAGE_ORDERING'), 'nested-eleven');

JHTML::_('joomconfig.start', 'page10');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_FS_IO_INTRO'));
    $picorder[] = JHTML::_('select.option','ordering ASC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_ORDERING_ASC'));
    $picorder[] = JHTML::_('select.option','ordering DESC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_ORDERING_DESC'));
    $picorder[] = JHTML::_('select.option','imgdate ASC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_UPLOADTIME_ASC'));
    $picorder[] = JHTML::_('select.option','imgdate DESC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_UPLOADTIME_DESC'));
    $picorder[] = JHTML::_('select.option','imgtitle ASC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_IMGTITLE_ASC'));
    $picorder[] = JHTML::_('select.option','imgtitle DESC', JText::_('COM_JOOMGALLERY_COMMON_OPTION_ORDERBY_IMGTITLE_DESC'));
    $mc_jg_firstorder = JHTML::_('select.genericlist',$picorder, 'jg_firstorder', 'class="inputbox" size="1"', 'value', 'text', $this->_config->jg_firstorder);
    JHTML::_('joomconfig.row', 'jg_firstorder', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_IO_FIRST', $mc_jg_firstorder);
    array_unshift($picorder, JHTML::_('select.option','', JText::_('COM_JOOMGALLERY_CONFIG_FS_IO_EMPTY')));
    $mc_jg_secondorder = JHTML::_('select.genericlist', $picorder, 'jg_secondorder', 'class="inputbox" size="1"', 'value', 'text', $this->_config->jg_secondorder);
    JHTML::_('joomconfig.row', 'jg_secondorder', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_IO_SECOND', $mc_jg_secondorder);
    $mc_jg_thirdorder = JHTML::_('select.genericlist', $picorder, 'jg_thirdorder', 'class="inputbox" size="1"', 'value', 'text', $this->_config->jg_thirdorder);
    JHTML::_('joomconfig.row', 'jg_thirdorder', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_IO_THIRD', $mc_jg_thirdorder);
JHTML::_('joomconfig.end');

// start Tab "Frontend Einstellungen->Seitentitel"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_FS_TAB_PAGETITLE_SETTINGS'), 'nested-twelve');

JHTML::_('joomconfig.start', 'page11');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_FS_PT_PAGETITLE_SETTINGS_INTRO'));
    JHTML::_('joomconfig.row', 'jg_pagetitle_cat', 'text', 'COM_JOOMGALLERY_CONFIG_FS_PT_CATVIEW', $this->_config->jg_pagetitle_cat, true, 'size = "60"');
    JHTML::_('joomconfig.row', 'jg_pagetitle_detail', 'text', 'COM_JOOMGALLERY_CONFIG_FS_PT_DETAILVIEW', $this->_config->jg_pagetitle_detail, true, 'size = "60"');
JHTML::_('joomconfig.end');

// start Tab "Frontend Einstellungen->Kopf- und Fussbereich"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_FS_TAB_HEADER_AND_FOOTER'), 'nested-thirteen');

JHTML::_('joomconfig.start', 'page12');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_FS_HF_INTRO'));
    JHTML::_('joomconfig.row', 'jg_showgalleryhead', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_HF_GALLERYHEAD', $this->_config->jg_showgalleryhead);
    $pathway[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $pathway[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $pathway[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $pathway[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $mc_jg_showpathway = JHTML::_('select.genericlist', $pathway, 'jg_showpathway', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showpathway);
    JHTML::_('joomconfig.row', 'jg_showpathway', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_HF_PATHWAY', $mc_jg_showpathway);
    JHTML::_('joomconfig.row', 'jg_completebreadcrumbs', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_HF_BREADCRUMBS', $this->_config->jg_completebreadcrumbs);
    $shownumbpics[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $shownumbpics[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $shownumbpics[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $shownumbpics[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $mc_jg_showallpics = JHTML::_('select.genericlist', $shownumbpics, 'jg_showallpics', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showallpics);
    JHTML::_('joomconfig.row', 'jg_showallpics', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_HF_ALLIMAGES', $mc_jg_showallpics);
    $shownumbhits[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $shownumbhits[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $shownumbhits[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $shownumbhits[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $mc_jg_showallhits = JHTML::_('select.genericlist', $shownumbhits, 'jg_showallhits', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showallhits);
    JHTML::_('joomconfig.row', 'jg_showallhits', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_HF_ALLHITS', $mc_jg_showallhits);
    $showbacklink[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $showbacklink[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $showbacklink[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $showbacklink[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $mc_jg_showbacklink = JHTML::_('select.genericlist', $showbacklink, 'jg_showbacklink', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showbacklink);
    JHTML::_('joomconfig.row', 'jg_showbacklink', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_HF_BACKLINK', $mc_jg_showbacklink);
    JHTML::_('joomconfig.row', 'jg_suppresscredits', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_HF_CREDITS', $this->_config->jg_suppresscredits);
JHTML::_('joomconfig.end');

// start Tab "Frontend Einstellungen->Meine Galerie"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_FS_TAB_USER_PANEL'), 'nested-fourteen');

JHTML::_('joomconfig.start', 'page13');
    JHTML::_('joomconfig.row', 'jg_showuserpanel', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_UP_USER_PANEL', $this->_config->jg_showuserpanel);
    JHTML::_('joomconfig.row', 'jg_showuserpanel_hint', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_UP_USER_PANEL_HINT', $this->_config->jg_showuserpanel_hint);
    JHTML::_('joomconfig.row', 'jg_showuserpanel_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_UP_USER_PANEL_UNREG', $this->_config->jg_showuserpanel_unreg, $this->display);
    JHTML::_('joomconfig.row', 'jg_showallpicstoadmin', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_UP_ALLIMAGESTOADMIN', $this->_config->jg_showallpicstoadmin);
    JHTML::_('joomconfig.row', 'jg_showminithumbs', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_UP_MINITHUMBS', $this->_config->jg_showminithumbs);
JHTML::_('joomconfig.end');

// start Tab "Frontend Einstellungen->PopUp-Funktionen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_FS_TAB_POPUP_FUNCTIONS'), 'nested-fifteen');

JHTML::_('joomconfig.start', 'page14');
    JHTML::_('joomconfig.row', 'jg_openjs_padding', 'text', 'COM_JOOMGALLERY_CONFIG_FS_PF_OPENJS_BORDERPX', $this->_config->jg_openjs_padding);
    $mc_color_value = JHTML::_('joomgallery.colorpicker', 'jg_openjs_background', $this->_config->jg_openjs_background);
    JHTML::_('joomconfig.row', 'jg_openjs_background', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_PF_OPENJS_BACKGROUND', $mc_color_value);
    $mc_color_value = JHTML::_('joomgallery.colorpicker', 'jg_dhtml_border', $this->_config->jg_dhtml_border);
    JHTML::_('joomconfig.row', 'jg_dhtml_border', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_PF_DHTML_BORDER', $mc_color_value);
    $mc_color_value = '';
    JHTML::_('joomconfig.row', 'jg_show_title_in_popup', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_PF_POPUP_TITLE', $this->_config->jg_show_title_in_popup);
    $showdescinpopup[] = JHTML::_('select.option','0', JText::_('JNO'));
    $showdescinpopup[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_FS_PF_POPUP_DESCRIPTION_YHTML'));
    $showdescinpopup[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_FS_PF_POPUP_DESCRIPTION_YNOHTML'));
    $mc_jg_show_description_in_popup = JHTML::_('select.genericlist', $showdescinpopup, 'jg_show_description_in_popup', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_show_description_in_popup);
    JHTML::_('joomconfig.row', 'jg_show_description_in_popup', 'custom', 'COM_JOOMGALLERY_CONFIG_FS_PF_POPUP_DESCRIPTION', $mc_jg_show_description_in_popup);
    JHTML::_('joomconfig.row', 'jg_lightbox_speed', 'text', 'COM_JOOMGALLERY_CONFIG_FS_PF_SLIMBOX_SPEED', $this->_config->jg_lightbox_speed);
    JHTML::_('joomconfig.row', 'jg_lightbox_slide_all', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_PF_SLIDEALL', $this->_config->jg_lightbox_slide_all);
    JHTML::_('joomconfig.row', 'jg_resize_js_image', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_PF_JSIMAGERESIZE', $this->_config->jg_resize_js_image);
    JHTML::_('joomconfig.row', 'jg_disable_rightclick_original', 'yesno', 'COM_JOOMGALLERY_CONFIG_FS_PF_DISABLE_RIGHTCLICK', $this->_config->jg_disable_rightclick_original);
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start fourth nested MainTab "Galerie-Ansicht"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_GALLERY_VIEW'), 'NestedMainPane4');
// start fourth nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneFour', array('useCookie' => 1));
// start Tab "Galerie-Ansicht->Generelle Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-sixteen');

JHTML::_('joomconfig.start', 'page15');
    JHTML::_('joomconfig.row', 'jg_showgallerysubhead', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_PATHWAY', $this->_config->jg_showgallerysubhead);
    JHTML::_('joomconfig.row', 'jg_showallcathead', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_CATEGORYHEADER', $this->_config->jg_showallcathead);
    JHTML::_('joomconfig.row', 'jg_colcat', 'text', 'COM_JOOMGALLERY_CONFIG_GV_GS_NUMB_COLUMN', $this->_config->jg_colcat);
    JHTML::_('joomconfig.row', 'jg_catperpage', 'text', 'COM_JOOMGALLERY_CONFIG_GV_GS_CATS_PER_PAGE', $this->_config->jg_catperpage);
    JHTML::_('joomconfig.row', 'jg_ordercatbyalpha', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_CATS_BY_ALPHA', $this->_config->jg_ordercatbyalpha);
    $showpagecatnavi[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_ONLY'));
    $showpagecatnavi[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_AND_BOTTOM'));
    $showpagecatnavi[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_BOTTOM_ONLY'));
    $mc_jg_showgallerypagenav = JHTML::_('select.genericlist', $showpagecatnavi, 'jg_showgallerypagenav', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showgallerypagenav);
    JHTML::_('joomconfig.row', 'jg_showgallerypagenav', 'custom', 'COM_JOOMGALLERY_CONFIG_GV_GS_PAGENAVIGATION', $mc_jg_showgallerypagenav);
    JHTML::_('joomconfig.row', 'jg_showcatcount', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_NUMB_CATS', $this->_config->jg_showcatcount);
    $catthumbs[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NONE'));
    $catthumbs[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_RANDOM'));
    $catthumbs[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_MYCHOISE'));
    $catthumbs[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_OVERRIDE'));
    $mc_jg_showcatthumb = JHTML::_('select.genericlist', $catthumbs, 'jg_showcatthumb', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showcatthumb);
    JHTML::_('joomconfig.row', 'jg_showcatthumb', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORYTHUMBNAIL', $mc_jg_showcatthumb);
    $randomcatthumbs[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_PARENT_CAT_ONLY'));
    $randomcatthumbs[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_CHILD_CAT_ONLY'));
    $randomcatthumbs[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_BOTH'));
    $mc_jg_showrandomcatthumb = JHTML::_('select.genericlist', $randomcatthumbs, 'jg_showrandomcatthumb', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showrandomcatthumb);
    JHTML::_('joomconfig.row', 'jg_showrandomcatthumb', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_RANDOMCATTHUMB', $mc_jg_showrandomcatthumb);
    $cthumbalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $cthumbalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $cthumbalign[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CHANGING'));
    $cthumbalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $mc_jg_ctalign = JHTML::_('select.genericlist', $cthumbalign, 'jg_ctalign', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_ctalign);
    JHTML::_('joomconfig.row', 'jg_ctalign', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_THUMBALIGN', $mc_jg_ctalign);
    JHTML::_('joomconfig.row', 'jg_showtotalcatimages', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_IMAGES', $this->_config->jg_showtotalcatimages);
    JHTML::_('joomconfig.row', 'jg_showtotalcathits', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_HITS', $this->_config->jg_showtotalcathits);
    JHTML::_('joomconfig.row', 'jg_showcatasnew', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_CATASNEW', $this->_config->jg_showcatasnew);
    JHTML::_('joomconfig.row', 'jg_catdaysnew', 'text', 'COM_JOOMGALLERY_CONFIG_GV_GS_CATDAYSNEW', $this->_config->jg_catdaysnew);
    JHTML::_('joomconfig.row', 'jg_showdescriptioningalleryview', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_DESCRIPTION', $this->_config->get('jg_showdescriptioningalleryview'));
    JHTML::_('joomconfig.row', 'jg_uploadicongallery', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_UPLOAD_ICON', $this->_config->get('jg_uploadicongallery'));
    JHTML::_('joomconfig.row', 'jg_showsubsingalleryview', 'yesno', 'COM_JOOMGALLERY_CONFIG_GV_GS_SUBCATEGORIES', $this->_config->jg_showsubsingalleryview);
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start fifth nested MainTab "Kategorie-Ansicht"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_CATEGORY_VIEW'), 'NestedMainPane5');
// start fifth nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneFive', array('useCookie' => 1));
// start Tab "Kategorie-Ansicht->Generelle Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-seventeen');

JHTML::_('joomconfig.start', 'page16');
    JHTML::_('joomconfig.row', 'jg_category_rss', 'text', 'COM_JOOMGALLERY_CONFIG_CV_CS_RSS', $this->_config->jg_category_rss);
    $rssicon[] = JHTML::_('select.option', '0', JText::_('JNO'));
    $rssicon[] = JHTML::_('select.option', 'atom', JText::_('COM_JOOMGALLERY_CONFIG_CV_CS_RSS_ICON_ATOM'));
    $rssicon[] = JHTML::_('select.option', 'rss', JText::_('COM_JOOMGALLERY_CONFIG_CV_CS_RSS_ICON_RSS'));
    $mc_jg_category_rss_icon = JHTML::_('select.genericlist', $rssicon, 'jg_category_rss_icon', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_category_rss_icon);
    JHTML::_('joomconfig.row', 'jg_category_rss_icon', 'custom', 'COM_JOOMGALLERY_CONFIG_CV_CS_RSS_ICON', $mc_jg_category_rss_icon);
    JHTML::_('joomconfig.row', 'jg_uploadiconcategory', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_UPLOAD_ICON', $this->_config->get('jg_uploadiconcategory'));
    JHTML::_('joomconfig.row', 'jg_showcathead', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_CATEGORYTITLE', $this->_config->jg_showcathead);
    JHTML::_('joomconfig.row', 'jg_usercatorder', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_ORDERBY_USER', $this->_config->jg_usercatorder);
    $arr_jg_usercatorderlist = explode(',', $this->_config->get('jg_usercatorderlist'));
    $catorderlist[] = JHTML::_('select.option', 'date', JText::_('COM_JOOMGALLERY_CONFIG_CV_GS_USER_ORDERBY_DATE'));
    $catorderlist[] = JHTML::_('select.option', 'user', JText::_('COM_JOOMGALLERY_CONFIG_CV_GS_USER_ORDERBY_AUTHOR'));
    $catorderlist[] = JHTML::_('select.option', 'title', JText::_('COM_JOOMGALLERY_CONFIG_CV_GS_USER_ORDERBY_TITLE'));
    $catorderlist[] = JHTML::_('select.option', 'hits', JText::_('COM_JOOMGALLERY_CONFIG_CV_GS_USER_ORDERBY_HITS'));
    $catorderlist[] = JHTML::_('select.option', 'rating', JText::_('COM_JOOMGALLERY_CONFIG_CV_GS_USER_ORDERBY_RATING'));
    $mc_jg_usercatorderlist = JHTML::_('select.genericlist', $catorderlist, 'jg_usercatorderlist[]', 'class="inputbox" size="5" multiple="multiple"', 'value', 'text', $arr_jg_usercatorderlist);
    JHTML::_('joomconfig.row', 'jg_usercatorderlist', 'custom', 'COM_JOOMGALLERY_CONFIG_CV_GS_ORDERBY_LIST', $mc_jg_usercatorderlist);
    JHTML::_('joomconfig.row', 'jg_showcatdescriptionincat', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_CATDESCRIPTIONINCAT', $this->_config->jg_showcatdescriptionincat);
    JHTML::_('joomconfig.row', 'jg_colnumb', 'text', 'COM_JOOMGALLERY_CONFIG_CV_GS_NUMB_COLUMN', $this->_config->jg_colnumb);
    JHTML::_('joomconfig.row', 'jg_perpage', 'text', 'COM_JOOMGALLERY_CONFIG_CV_GS_IMGS_PER_PAGE', $this->_config->jg_perpage);
    $catthumbalign[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $catthumbalign[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $catthumbalign[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_catthumbalign = JHTML::_('select.genericlist', $catthumbalign, 'jg_catthumbalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_catthumbalign);
    JHTML::_('joomconfig.row', 'jg_catthumbalign', 'custom', 'COM_JOOMGALLERY_CONFIG_CV_GS_CATEGORY_THUMBALIGN', $mc_jg_catthumbalign);
    $showpagenavi[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_ONLY'));
    $showpagenavi[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_AND_BOTTOM'));
    $showpagenavi[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_BOTTOM_ONLY'));
    $mc_jg_showpagenav = JHTML::_('select.genericlist', $showpagenavi, 'jg_showpagenav', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showpagenav);
    JHTML::_('joomconfig.row', 'jg_showpagenav', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_PAGENAVIGATION', $mc_jg_showpagenav);
    JHTML::_('joomconfig.row', 'jg_showpiccount', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_NUMB_CATIMAGES', $this->_config->jg_showpiccount);
    $mc_jg_detailpic_open = JHtml::_('joomselect.openimage', 'jg_detailpic_open', $this->_config->get('jg_detailpic_open'));
    JHTML::_('joomconfig.row', 'jg_detailpic_open', 'custom', 'COM_JOOMGALLERY_CONFIG_CV_GS_OPEN_DETAIL_VIEW', $mc_jg_detailpic_open);
    JHTML::_('joomconfig.row', 'jg_lightboxbigpic', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_POPUP_ORIGINAL', $this->_config->jg_lightboxbigpic);
    JHTML::_('joomconfig.row', 'jg_showtitle', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_TITLE', $this->_config->jg_showtitle);
    JHTML::_('joomconfig.row', 'jg_showpicasnew', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_ASNEW', $this->_config->jg_showpicasnew);
    JHTML::_('joomconfig.row', 'jg_daysnew', 'text', 'COM_JOOMGALLERY_CONFIG_CV_GS_DAYSNEW', $this->_config->jg_daysnew);
    JHTML::_('joomconfig.row', 'jg_showhits', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_HITS', $this->_config->jg_showhits);
    JHTML::_('joomconfig.row', 'jg_showdownloads', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_DOWNLOADS', $this->_config->jg_showdownloads);
    JHTML::_('joomconfig.row', 'jg_showauthor', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_AUTHOR', $this->_config->jg_showauthor);
    JHTML::_('joomconfig.row', 'jg_showcatcom', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_COMMENTS', $this->_config->jg_showcatcom);
    JHTML::_('joomconfig.row', 'jg_showcatrate', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_RATINGS', $this->_config->jg_showcatrate);
    JHTML::_('joomconfig.row', 'jg_showcatdescription', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_GS_DESCRIPTION', $this->_config->jg_showcatdescription);
    JHTML::_('joomconfig.row', 'jg_showcategorydownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_DOWNLOAD', $this->_config->jg_showcategorydownload);
    JHTML::_('joomconfig.row', 'jg_showcategoryfavourite', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_FAVOURITES_LINK', $this->_config->jg_showcategoryfavourite);
    JHTML::_('joomconfig.row', 'jg_category_report_images', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_REPORT_IMAGES', $this->_config->jg_category_report_images);
    JHTML::_('joomconfig.row', 'jg_showcategoryeditorlinks', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_EDITOR_LINKS', $this->_config->jg_showcategoryeditorlinks);
JHTML::_('joomconfig.end');

// start Tab "Kategorie-Ansicht->Unterkategorien"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_CV_TAB_SUBCAT_SETTINGS'), 'nested-eighteen');

JHTML::_('joomconfig.start', 'page17');
    JHTML::_('joomconfig.row', 'jg_showsubcathead', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_SC_SUBCATEGORYHEADER', $this->_config->jg_showsubcathead);
    JHTML::_('joomconfig.row', 'jg_showsubcatcount', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_SC_NUMB_SUBCATEGORIES', $this->_config->jg_showsubcatcount);
    JHTML::_('joomconfig.row', 'jg_colsubcat', 'text', 'COM_JOOMGALLERY_CONFIG_CV_SC_NUMB_COLUMN', $this->_config->jg_colsubcat);
    JHTML::_('joomconfig.row', 'jg_subperpage', 'text', 'COM_JOOMGALLERY_CONFIG_CV_SC_SUBCATS_PER_PAGE', $this->_config->jg_subperpage);
    $showpagenavisubs[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_ONLY'));
    $showpagenavisubs[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_TOP_AND_BOTTOM'));
    $showpagenavisubs[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_BOTTOM_ONLY'));
    $mc_jg_showpagenavsubs = JHTML::_('select.genericlist', $showpagenavisubs, 'jg_showpagenavsubs', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showpagenavsubs);
    JHTML::_('joomconfig.row', 'jg_showpagenavsubs', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_PAGENAVIGATION', $mc_jg_showpagenavsubs);
    $subthumbs[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NONE'));
    $subthumbs[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_MYCHOISE'));
    $subthumbs[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_RANDOM'));
    $subthumbs[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_OVERRIDE'));
    $mc_jg_showsubthumbs = JHTML::_('select.genericlist', $subthumbs, 'jg_showsubthumbs', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showsubthumbs);
    JHTML::_('joomconfig.row', 'jg_showsubthumbs', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORYTHUMBNAIL', $mc_jg_showsubthumbs);
    $randomsubthumbs[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_PARENT_CAT_ONLY'));
    $randomsubthumbs[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_CHILD_CAT_ONLY'));
    $randomsubthumbs[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_FROM_BOTH'));
    $mc_jg_showrandomsubthumb = JHTML::_('select.genericlist', $randomsubthumbs, 'jg_showrandomsubthumb', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showrandomsubthumb);
    JHTML::_('joomconfig.row', 'jg_showrandomsubthumb', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_RANDOMCATTHUMB', $mc_jg_showrandomsubthumb);
    $subcatthumbalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $subcatthumbalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $subcatthumbalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_subcatthumbalign = JHTML::_('select.genericlist', $subcatthumbalign, 'jg_subcatthumbalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_subcatthumbalign);
    JHTML::_('joomconfig.row', 'jg_subcatthumbalign', 'custom', 'COM_JOOMGALLERY_CONFIG_COMMON_THUMBALIGN', $mc_jg_subcatthumbalign);
    JHTML::_('joomconfig.row', 'jg_showdescriptionincategoryview', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_SC_DESCRIPTION', $this->_config->get('jg_showdescriptionincategoryview'));
    JHTML::_('joomconfig.row', 'jg_ordersubcatbyalpha', 'yesno', 'COM_JOOMGALLERY_CONFIG_CV_SC_ORDER_BY_ALPHA', $this->_config->jg_ordersubcatbyalpha);
    JHTML::_('joomconfig.row', 'jg_showtotalsubcatimages', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_IMAGES', $this->_config->jg_showtotalsubcatimages);
    JHTML::_('joomconfig.row', 'jg_showtotalsubcathits', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_CATEGORY_HITS', $this->_config->jg_showtotalsubcathits);
    JHTML::_('joomconfig.row', 'jg_uploadiconsubcat', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_UPLOAD_ICON', $this->_config->get('jg_uploadiconsubcat'));
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start sixth nested MainTab "Detail-Ansicht"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_DETAIL_VIEW'), 'NestedMainPane6');
// start sixth nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneSix', array('useCookie' => 1));
// start Tab "Detail-Ansicht->Generelle Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-nineteen');

JHTML::_('joomconfig.start', 'page18');
    JHTML::_('joomconfig.row', 'jg_showdetailpage', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_ALLOW_DETAILPAGE', $this->_config->jg_showdetailpage);
    JHTML::_('joomconfig.row', 'jg_disabledetailpage', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_DISABLE_DEFAULT_DETAIL_VIEW', $this->_config->jg_disabledetailpage);
    JHTML::_('joomconfig.row', 'jg_showdetailnumberofpics', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_COUNTER', $this->_config->jg_showdetailnumberofpics);
    JHTML::_('joomconfig.row', 'jg_cursor_navigation', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_CURSOR_NAVIGATION', $this->_config->jg_cursor_navigation);
    JHTML::_('joomconfig.row', 'jg_disable_rightclick_detail', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_DISABLE_RIGHTCLICK', $this->_config->jg_disable_rightclick_detail);
    JHTML::_('joomconfig.row', 'jg_detail_report_images', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_REPORT_IMAGES', $this->_config->jg_detail_report_images);
    JHTML::_('joomconfig.row', 'jg_showdetaileditorlinks', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_EDITOR_LINKS', $this->_config->jg_showdetaileditorlinks);
    $showdetailtitle[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $showdetailtitle[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_UPSIDE'));
    $showdetailtitle[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_BELOW'));
    $mc_jg_showdetailtitle = JHTML::_('select.genericlist', $showdetailtitle, 'jg_showdetailtitle', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_showdetailtitle);
    JHTML::_('joomconfig.row', 'jg_showdetailtitle', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_TITLE', $mc_jg_showdetailtitle);
    JHTML::_('joomconfig.row', 'jg_showdetail', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_INFORMATION', $this->_config->jg_showdetail);
    JHTML::_('joomconfig.row', 'jg_showdetaildescription', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_DESCRIPTION', $this->_config->jg_showdetaildescription);
    JHTML::_('joomconfig.row', 'jg_showdetaildatum', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_DATE', $this->_config->jg_showdetaildatum);
    JHTML::_('joomconfig.row', 'jg_showdetailhits', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_HITS', $this->_config->jg_showdetailhits);
    JHTML::_('joomconfig.row', 'jg_showdetaildownloads', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_DOWNLOADS', $this->_config->jg_showdetaildownloads);
    JHTML::_('joomconfig.row', 'jg_showdetailrating', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_RATING', $this->_config->jg_showdetailrating);
    JHTML::_('joomconfig.row', 'jg_showdetailfilesize', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_FILESIZE', $this->_config->jg_showdetailfilesize);
    JHTML::_('joomconfig.row', 'jg_showdetailauthor', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_AUTHOR', $this->_config->jg_showdetailauthor);
    JHTML::_('joomconfig.row', 'jg_showoriginalfilesize', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_ORIGFILESIZE', $this->_config->jg_showoriginalfilesize);
    JHTML::_('joomconfig.row', 'jg_showdetaildownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_DOWNLOAD', $this->_config->jg_showdetaildownload);
    JHTML::_('joomconfig.row', 'jg_watermark', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_ADD_WATERMARK', $this->_config->jg_watermark);
    $watermarkpos[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_TOP_LEFT'));
    $watermarkpos[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_TOP_CENTER'));
    $watermarkpos[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_TOP_RIGHT'));
    $watermarkpos[] = JHTML::_('select.option', '4', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_MIDDLE_LEFT'));
    $watermarkpos[] = JHTML::_('select.option', '5', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_MIDDLE_CENTER'));
    $watermarkpos[] = JHTML::_('select.option', '6', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_MIDDLE_RIGHT'));
    $watermarkpos[] = JHTML::_('select.option', '7', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_BOTTOM_LEFT'));
    $watermarkpos[] = JHTML::_('select.option', '8', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_BOTTOM_CENTER'));
    $watermarkpos[] = JHTML::_('select.option', '9', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_BOTTOM_RIGHT'));
    $mc_jg_watermarkpos = JHTML::_('select.genericlist', $watermarkpos, 'jg_watermarkpos', 'class="inputbox" size="1"', 'value', 'text', $this->_config->jg_watermarkpos);
    JHTML::_('joomconfig.row', 'jg_watermarkpos', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARK_POSITION', $mc_jg_watermarkpos);
    $watermarkzoom[] = JHTML::_('select.option', '0', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARKNORESIZE'));
    $watermarkzoom[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARKHEIGHT'));
    $watermarkzoom[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARKWIDTH'));
    $mc_jg_watermarkzoom = JHTML::_('select.genericlist', $watermarkzoom, 'jg_watermarkzoom', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_watermarkzoom);
    JHTML::_('joomconfig.row', 'jg_watermarkzoom', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARKZOOM', $mc_jg_watermarkzoom);
    JHTML::_('joomconfig.row', 'jg_watermarksize', 'text', 'COM_JOOMGALLERY_CONFIG_DV_GS_WATERMARKSIZE', $this->_config->jg_watermarksize);
    JHTML::_('joomconfig.row', 'jg_bigpic', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_LINKTOORIGINAL', $this->_config->jg_bigpic);
    JHTML::_('joomconfig.row', 'jg_bigpic_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_LINKTOORIGINAL_UNREG', $this->_config->jg_bigpic_unreg);
    $mc_jg_bigpic_open = JHtml::_('joomselect.openimage', 'jg_bigpic_open', $this->_config->get('jg_bigpic_open'), false);
    JHTML::_('joomconfig.row', 'jg_bigpic_open', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_OPEN_ORIGINAL', $mc_jg_bigpic_open);
    $bbcodelinks[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $bbcodelinks[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_BBCODE_IMG_ONLY'));
    $bbcodelinks[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_BBCODE_URL_ONLY'));
    $bbcodelinks[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_BBCODE_BOTH'));
    $mc_jg_bbcodelinks = JHTML::_('select.genericlist', $bbcodelinks, 'jg_bbcodelink', 'class="inputbox" size="4"', 'value', 'text',$this->_config->jg_bbcodelink);
    JHTML::_('joomconfig.row', 'jg_bbcodelink', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_BBCODELINK', $mc_jg_bbcodelinks);
    JHTML::_('joomconfig.row', 'jg_showcommentsunreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_COMMENTS_REG', $this->_config->jg_showcommentsunreg, $this->display);
    $showcommentsarea[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_ABOVE_COMMENTS'));
    $showcommentsarea[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_DV_GS_OPTION_UNDERNEATH_COMMENTS'));
    $mc_jg_showcommentsarea = JHTML::_('select.genericlist', $showcommentsarea, 'jg_showcommentsarea', 'class="inputbox" size="2"', 'value', 'text', $this->_config->jg_showcommentsarea);
    JHTML::_('joomconfig.row', 'jg_showcommentsarea', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_GS_COMMENTSAREA', $mc_jg_showcommentsarea);
    JHTML::_('joomconfig.row', 'jg_send2friend', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_GS_SEND2FRIEND', $this->_config->jg_send2friend);
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->Accordion"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_ACCORDION_SETTINGS'), 'nested-twenty');
JHTML::_('joomconfig.start', 'dtlaccordion');
    JHTML::_('joomconfig.row', 'jg_showdetailaccordion', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_AC_ACCORDION', $this->_config->jg_showdetailaccordion);
    JHTML::_('joomconfig.row', 'jg_accordionduration', 'text', 'COM_JOOMGALLERY_CONFIG_DV_AC_DURATION', $this->_config->jg_accordionduration);
    JHTML::_('joomconfig.row', 'jg_accordiondisplay', 'text', 'COM_JOOMGALLERY_CONFIG_DV_AC_DISPLAY', $this->_config->jg_accordiondisplay);
    JHTML::_('joomconfig.row', 'jg_accordioninitialeffect', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_AC_INITIALEFFECT', $this->_config->jg_accordioninitialeffect);
    JHTML::_('joomconfig.row', 'jg_accordionopacity', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_AC_OPACITY', $this->_config->jg_accordionopacity);
    JHTML::_('joomconfig.row', 'jg_accordionalwayshide', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_AC_ALWAYSHIDE', $this->_config->jg_accordionalwayshide);
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->Motiongallery"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_MOTIONGALLERY_SETTINGS'), 'nested-twentyone');

JHTML::_('joomconfig.start', 'page19');
    JHTML::_('joomconfig.row', 'jg_minis', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_MG_MOTIONGALLERY', $this->_config->jg_minis);
    $joom_ShowMotionMinis[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_CONFIG_DV_MG_OPTION_STATIC'));
    $joom_ShowMotionMinis[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_CONFIG_DV_MG_OPTION_MOVEABLE'));
    $mc_jg_motionminis = JHTML::_('select.genericlist', $joom_ShowMotionMinis, 'jg_motionminis', 'class="inputbox" size="2"', 'value', 'text', $this->_config->jg_motionminis);
    JHTML::_('joomconfig.row', 'jg_motionminis', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_MG_DISPLAYMODE', $mc_jg_motionminis);
    JHTML::_('joomconfig.row', 'jg_motionminiWidth', 'text', 'COM_JOOMGALLERY_CONFIG_DV_MG_WIDTH', $this->_config->jg_motionminiWidth);
    JHTML::_('joomconfig.row', 'jg_motionminiLimit', 'text', 'COM_JOOMGALLERY_CONFIG_DV_MG_LIMIT', $this->_config->jg_motionminiLimit);
    JHTML::_('joomconfig.row', 'jg_motionminiHeight', 'text', 'COM_JOOMGALLERY_CONFIG_DV_MG_HEIGHT', $this->_config->jg_motionminiHeight);
    JHTML::_('joomconfig.row', 'jg_miniWidth', 'text', 'COM_JOOMGALLERY_CONFIG_DV_MG_MINIS_MAXWIDTH', $this->_config->jg_miniWidth);
    JHTML::_('joomconfig.row', 'jg_miniHeight', 'text', 'COM_JOOMGALLERY_CONFIG_DV_MG_MINIS_MAXHEIGHT', $this->_config->jg_miniHeight);
    $joom_minisprop[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_SAMEWIDTHANDHEIGHT'));
    $joom_minisprop[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_SAMEWIDTH'));
    $joom_minisprop[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_SAMEHIGHT'));
    $mc_jg_minisprop = JHTML::_('select.genericlist', $joom_minisprop, 'jg_minisprop', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_minisprop);
    JHTML::_('joomconfig.row', 'jg_minisprop', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_MG_MINIS_PROPORTIONS', $mc_jg_minisprop);
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->Namensschilder"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_NAMETAGS_SETTINGS'), 'nested-twentytwo');

JHTML::_('joomconfig.start', 'page20');
    JHTML::_('joomconfig.row', 'jg_nameshields', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_NT_NAMETAGS', $this->_config->jg_nameshields);
    JHTML::_('joomconfig.row', 'jg_nameshields_others', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_NT_OTHERS', $this->_config->jg_nameshields_others);
    JHTML::_('joomconfig.row', 'jg_nameshields_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_NT_GUEST_VISIBLE', $this->_config->jg_nameshields_unreg, $this->display);
    JHTML::_('joomconfig.row', 'jg_show_nameshields_unreg', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_NT_GUEST_INFORMATION', $this->_config->jg_show_nameshields_unreg, $this->display);
    JHTML::_('joomconfig.row', 'jg_nameshields_height', 'text', 'COM_JOOMGALLERY_CONFIG_DV_NT_HEIGHT', $this->_config->jg_nameshields_height);
    JHTML::_('joomconfig.row', 'jg_nameshields_width', 'text', 'COM_JOOMGALLERY_CONFIG_DV_NT_WIDTH', $this->_config->jg_nameshields_width);
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->Slideshow"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_SLIDESHOW_SETTINGS'), 'nested-twentythree');

JHTML::_('joomconfig.start', 'page21');
    JHTML::_('joomconfig.row', 'jg_slideshow', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_SL_SLIDESHOW', $this->_config->jg_slideshow);
    JHTML::_('joomconfig.row', 'jg_slideshow_timer', 'text', 'COM_JOOMGALLERY_CONFIG_DV_SL_TIMEFRAME', $this->_config->jg_slideshow_timer);
    $joom_transitions[] = JHTML::_('select.option','0', 'fade');
    $joom_transitions[] = JHTML::_('select.option','1', 'fadeslideleft');
    $joom_transitions[] = JHTML::_('select.option','2', 'crossfade');
    $joom_transitions[] = JHTML::_('select.option','3', 'continuoushorizontal');
    $joom_transitions[] = JHTML::_('select.option','4', 'continuousvertical');
    $joom_transitions[] = JHTML::_('select.option','5', 'fadebg');
    $mc_jg_transitions = JHTML::_('select.genericlist', $joom_transitions, 'jg_slideshow_transition', 'class="inputbox" size="6"', 'value', 'text', $this->_config->jg_slideshow_transition);
    JHTML::_('joomconfig.row', 'jg_slideshow_transition', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_SL_TRANSITION', $mc_jg_transitions);
    JHTML::_('joomconfig.row', 'jg_slideshow_transtime', 'text', 'COM_JOOMGALLERY_CONFIG_DV_SL_TRANSITION_TIME', $this->_config->jg_slideshow_transtime);
    JHTML::_('joomconfig.row', 'jg_slideshow_maxdimauto', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_SL_MAXDIMAUTO', $this->_config->jg_slideshow_maxdimauto);
    JHTML::_('joomconfig.row', 'jg_slideshow_width', 'text', 'COM_JOOMGALLERY_CONFIG_DV_SL_WIDTH', $this->_config->jg_slideshow_width);
    JHTML::_('joomconfig.row', 'jg_slideshow_heigth', 'text', 'COM_JOOMGALLERY_CONFIG_DV_SL_HEIGHT', $this->_config->jg_slideshow_heigth);
    JHTML::_('joomconfig.row', 'jg_slideshow_infopane', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_SL_INFOPANE', $this->_config->jg_slideshow_infopane);
    JHTML::_('joomconfig.row', 'jg_slideshow_carousel', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_SL_CAROUSEL', $this->_config->jg_slideshow_carousel);
    JHTML::_('joomconfig.row', 'jg_slideshow_arrows', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_SL_ARROWS', $this->_config->jg_slideshow_arrows);
    $joom_repetitions[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_DV_SL_REPEAT_ENDLESS'));
    $joom_repetitions[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_DV_SL_REPEAT_ASK'));
    $joom_repetitions[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_DV_SL_REPEAT_STOP'));
    $mc_jg_repetitions = JHTML::_('select.genericlist', $joom_repetitions, 'jg_slideshow_repeat', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_slideshow_repeat);
    JHTML::_('joomconfig.row', 'jg_slideshow_repeat', 'custom', 'COM_JOOMGALLERY_CONFIG_DV_SL_REPETITION', $mc_jg_repetitions);
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->Exif-Daten"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_EXIF_SETTINGS'), 'nested-twentyfour');

JHTML::_('joomconfig.start', 'page22');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_DV_ED_EXIF_INTRO_ONE').'<br />'.JText::_('COM_JOOMGALLERY_CONFIG_DV_ED_EXIF_INTRO_TWO').'<br />'.$this->exifmsg);
    JHTML::_('joomconfig.row', 'jg_showexifdata', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_ED_EXIFDATA', $this->_config->jg_showexifdata);
    JHTML::_('joomconfig.row', 'jg_showgeotagging', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_ED_SHOWGEOTAGGING', $this->_config->jg_showgeotagging);
    JHTML::_('joomconfig.row', 'jg_geotaggingkey', 'text', 'COM_JOOMGALLERY_CONFIG_DV_ED_GEOTAGGINGKEY', $this->_config->jg_geotaggingkey);
?>
  </table>
  <table class="adminlist table table-bordered">
<?php for($ii = 1; $ii <= count($this->exif_definitions); $ii++):
        $tags     = count($this->exif_config_array[$this->exif_definitions[$ii]['TAG']]);
        $jgtags   = $this->exif_definitions[$ii]['JG'];
        $tagname  = $this->exif_definitions[$ii]['NAME'];
        $header   = $this->exif_definitions[$ii]['HEAD']; ?>
      <tr>
        <!--<th>
          <input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo $tags; ?>)" />
        </th>-->
        <th colspan="5" width="100%" align="center" class="title">
          <?php echo $header; ?>
        </th>
      </tr>
      <tr>
        <th>
          <input type="hidden" name="jg_<?php echo $tagname; ?>" value="<?php echo implode(',', $jgtags); ?>" />&nbsp;
        </th>
        <th nowrap="nowrap">
          <?php echo JText::_('COM_JOOMGALLERY_TAGNR'); ?>
        </th>
        <th>
          <?php echo JText::_('COM_JOOMGALLERY_TAGNAME'); ?>
        </th>
        <th nowrap="nowrap">
          <?php echo JText::_('COM_JOOMGALLERY_TAG'); ?>
        </th>
        <th>
          <?php echo JText::_('COM_JOOMGALLERY_TAGDESCRIPTION'); ?>
        </th>
      </tr>
<?php   $i = 1;
        foreach($this->exif_config_array[$this->exif_definitions[$ii]['TAG']] as $key => $value):
          $checked = '';
          if((in_array($key, $jgtags)) && $jgtags[0]):
            $checked = ' checked="checked"';
          endif; ?>
      <tr>
        <td>
          <input type="checkbox" id="cb<?php echo $i; ?>" name="<?php echo $tagname; ?>" value="<?php echo $key; ?>" onclick="isChecked(this.checked);"<?php echo $checked; ?> />
        </td>
        <td nowrap="nowrap">
          <?php echo $key; ?>
        </td>
        <td width="30%">
          <?php echo $value['Name']; ?>
        </td>
        <td width="20%">
          <?php echo $value['Attribute']; ?>
        </td>
        <td width="50%">
          <?php echo $value['Description']; ?>
        </td>
      </tr>
<?php     $i++;
        endforeach;
      endfor; ?>
      <tr>
        <th colspan="5">
          &nbsp;
        </th>
      </tr>
<?php
JHTML::_('joomconfig.end');

// start Tab "Detail-Ansicht->IPTC-Daten"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_DV_TAB_IPTC_SETTINGS'), 'nested-twentyfive');

JHTML::_('joomconfig.start', 'page23');
    JHTML::_('joomconfig.intro', JText::_('COM_JOOMGALLERY_CONFIG_DV_ID_IPTC_INTRO_ONE').'<br />'.JText::_('COM_JOOMGALLERY_CONFIG_DV_ID_IPTC_INTRO_TWO'));
    JHTML::_('joomconfig.row', 'jg_showiptcdata', 'yesno', 'COM_JOOMGALLERY_CONFIG_DV_ID_IPTCDATA', $this->_config->jg_showiptcdata);
?>
  </table>
  <table class="adminlist table table-bordered">
<?php for($ii = 1; $ii <= count($this->iptc_definitions); $ii++):
        $tags     = count($this->iptc_config_array[$this->iptc_definitions[$ii]['TAG']]);
        $jgtags   = $this->iptc_definitions[$ii]['JG'];
        $tagname  = $this->iptc_definitions[$ii]['NAME'];
        $header   = $this->iptc_definitions[$ii]['HEAD']; ?>
    <tr>
      <!--<th>
        <input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo $tags; ?>)" />
      </th>-->
      <th colspan="5" width="100%" align="center" class="title">
        <?php echo $header; ?>
      </th>
    </tr>
    <tr>
      <th>
        <input type="hidden" name="jg_<?php echo $tagname; ?>" value="<?php echo implode(',', $jgtags); ?>" />&nbsp;
      </th>
      <th nowrap="nowrap">
        <?php echo JText::_('COM_JOOMGALLERY_TAGNR'); ?>
      </th>
      <th>
        <?php echo JText::_('COM_JOOMGALLERY_TAGNAME'); ?>
      </th>
      <th nowrap="nowrap">
        <?php echo JText::_('COM_JOOMGALLERY_TAG'); ?>
      </th>
      <th>
        <?php echo JText::_('COM_JOOMGALLERY_TAGDESCRIPTION'); ?>
      </th>
    </tr>
<?php   $i = 1;
        foreach($this->iptc_config_array[$this->iptc_definitions[$ii]['TAG']] as $key => $value):
          $checked = '';
          if((in_array($key, $jgtags)) && $jgtags[0]):
            $checked = ' checked="checked"';
          endif; ?>
    <tr>
      <td>
        <input type="checkbox" id="cb<?php echo $i; ?>" name="<?php echo $tagname; ?>" value="<?php echo $key; ?>" onclick="isChecked(this.checked);"<?php echo $checked; ?> />
      </td>
      <td nowrap="nowrap">
        <?php echo $value['IMM']; ?>
      </td>
      <td width="20%">
        <?php echo $value['Name']; ?>
      </td>
      <td width="20%">
        <?php echo $value['Attribute']; ?>
      </td>
      <td width="60%">
        <?php echo $value['Description']; ?>
      </td>
    </tr>
<?php     $i++;
        endforeach; ?>
    <tr>
      <th colspan="5">
        &nbsp;
      </th>
    </tr>
<?php endfor; ?>
  </table>
  <table class="adminlist table table-bordered">
<?php
    JHTML::_('joomconfig.intro', '&sup1;&nbsp;'.JText::_('COM_JOOMGALLERY_CONFIG_DV_ID_COPYRIGHT').'<br />'.JText::_('COM_JOOMGALLERY_CONFIG_DV_ID_COPYRIGHT_LANGUAGE'));
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start seventh nested MainTab "Toplisten"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_TOPLIST_SETTINGS'), 'NestedMainPane7');
// start seventh nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneSeven', array('useCookie' => 1));
// start Tab "Toplisten->Generelle Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-twentysix');

JHTML::_('joomconfig.start', 'page24');
    $toplist[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $toplist[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $toplist[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $toplist[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $mc_jg_showtoplist = JHTML::_('select.genericlist',$toplist, 'jg_showtoplist', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_showtoplist);
    JHTML::_('joomconfig.row', 'jg_showtoplist', 'custom', 'COM_JOOMGALLERY_CONFIG_TL_GS_TOPLIST', $mc_jg_showtoplist);
    $wheretoplist[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_TL_GS_OPTION_ALL_VIEWS'));
    $wheretoplist[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_TL_GS_OPTION_ONLY_GALLERYVIEW'));
    $wheretoplist[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_TL_GS_OPTION_GALLERY_AND_CATEGORYVIEW'));
    $mc_jg_whereshowtoplist = JHTML::_('select.genericlist', $wheretoplist, 'jg_whereshowtoplist', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_whereshowtoplist);
    JHTML::_('joomconfig.row', 'jg_whereshowtoplist', 'custom', 'COM_JOOMGALLERY_CONFIG_TL_GS_ON_VIEWS', $mc_jg_whereshowtoplist);
    JHTML::_('joomconfig.row', 'jg_toplistcols', 'text', 'COM_JOOMGALLERY_CONFIG_TL_GS_NUMB_COLUMN', $this->_config->jg_toplistcols);
    JHTML::_('joomconfig.row', 'jg_toplist', 'text', 'COM_JOOMGALLERY_CONFIG_TL_GS_NUMB_ENTRIES', $this->_config->jg_toplist);
    $topthumbalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $topthumbalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $topthumbalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_topthumbalign = JHTML::_('select.genericlist', $topthumbalign, 'jg_topthumbalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_topthumbalign );
    JHTML::_('joomconfig.row', 'jg_topthumbalign', 'custom', 'COM_JOOMGALLERY_CONFIG_TL_GS_THUMBALIGN', $mc_jg_topthumbalign);
    $toptextalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $toptextalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $toptextalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_toptextalign = JHTML::_('select.genericlist', $toptextalign, 'jg_toptextalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_toptextalign );
    JHTML::_('joomconfig.row', 'jg_toptextalign', 'custom', 'COM_JOOMGALLERY_CONFIG_TL_GS_TEXTALIGN', $mc_jg_toptextalign);
    JHTML::_('joomconfig.row', 'jg_showrate', 'yesno', 'COM_JOOMGALLERY_CONFIG_TL_GS_RATING', $this->_config->jg_showrate);
    JHTML::_('joomconfig.row', 'jg_showlatest', 'yesno', 'COM_JOOMGALLERY_CONFIG_TL_GS_LATEST', $this->_config->jg_showlatest);
    JHTML::_('joomconfig.row', 'jg_showcom', 'yesno', 'COM_JOOMGALLERY_CONFIG_TL_GS_COMMENTS', $this->_config->jg_showcom);
    JHTML::_('joomconfig.row', 'jg_showthiscomment', 'yesno', 'COM_JOOMGALLERY_CONFIG_TL_GS_THISCOMMENT', $this->_config->jg_showthiscomment);
    JHTML::_('joomconfig.row', 'jg_showmostviewed', 'yesno', 'COM_JOOMGALLERY_CONFIG_TL_GS_MOSTVIEWED', $this->_config->jg_showmostviewed);
    JHTML::_('joomconfig.row', 'jg_showtoplistdownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_DOWNLOAD', $this->_config->jg_showtoplistdownload);
    JHTML::_('joomconfig.row', 'jg_showtoplistfavourite', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_FAVOURITES_LINK', $this->_config->jg_showtoplistfavourite);
    JHTML::_('joomconfig.row', 'jg_toplist_report_images', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_REPORT_IMAGES', $this->_config->jg_toplist_report_images);
    JHTML::_('joomconfig.row', 'jg_showtoplisteditorlinks', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_EDITOR_LINKS', $this->_config->jg_showtoplisteditorlinks);
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start eighth nested MainTab "Favoriten"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_FAVOURITES_SETTINGS'), 'NestedMainPane8');
// start eighth nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneEight', array('useCookie' => 1));
// start Tab "Favoriten->Generelle Einstellungen"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-twentyseven');

JHTML::_('joomconfig.start', 'page25');
    JHTML::_('joomconfig.row', 'jg_favourites', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_FAVOURITES', $this->_config->jg_favourites);
    JHTML::_('joomconfig.row', 'jg_favouritesshownotauth', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_GUEST_INFORMATION', $this->_config->jg_favouritesshownotauth);
    JHTML::_('joomconfig.row', 'jg_maxfavourites', 'text', 'COM_JOOMGALLERY_CONFIG_FV_GS_MAX_IMAGES', $this->_config->jg_maxfavourites);
    JHTML::_('joomconfig.row', 'jg_zipdownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_ZIPDOWNLOAD', $this->_config->jg_zipdownload);
    JHTML::_('joomconfig.row', 'jg_usefavouritesforpubliczip', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_FOR_PUBLIC_ZIP', $this->_config->jg_usefavouritesforpubliczip);
    JHTML::_('joomconfig.row', 'jg_usefavouritesforzip', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_FOR_ZIP', $this->_config->jg_usefavouritesforzip);
    JHTML::_('joomconfig.row', 'jg_allimagesofcategory', 'yesno', 'COM_JOOMGALLERY_CONFIG_FV_GS_ALL_IMAGES_OF_CATEGORY', $this->_config->jg_allimagesofcategory);
    JHTML::_('joomconfig.row', 'jg_showfavouritesdownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_DOWNLOAD', $this->_config->jg_showfavouritesdownload);
    JHTML::_('joomconfig.row', 'jg_showfavouriteseditorlinks', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_EDITOR_LINKS', $this->_config->jg_showfavouriteseditorlinks);
JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

// start nineth nested MainTab "Search"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_TAB_SEARCH_SETTINGS'), 'NestedMainPane9');
// start nineth nested tabs pane
echo JHtml::_('tabs.start', 'NestedPaneNine', array('useCookie' => 1));
// start Tab "Search->General settings"
echo JHtml::_('tabs.panel', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_TAB_GENERAL_SETTINGS'), 'nested-twentyeight');

JHTML::_('joomconfig.start', 'page28');
    $search[] = JHTML::_('select.option','0', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_NO_DISPLAY'));
    $search[] = JHTML::_('select.option','1', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADER'));
    $search[] = JHTML::_('select.option','2', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_FOOTER'));
    $search[] = JHTML::_('select.option','3', JText::_('COM_JOOMGALLERY_CONFIG_COMMON_OPTION_IN_HEADERFOOTER'));
    $mc_jg_search = JHTML::_('select.genericlist', $search, 'jg_search', 'class="inputbox" size="4"', 'value', 'text', $this->_config->jg_search);
    JHTML::_('joomconfig.row', 'jg_search', 'custom', 'COM_JOOMGALLERY_CONFIG_SS_GS_SEARCHFIELD', $mc_jg_search);
    JHTML::_('joomconfig.row', 'jg_searchcols', 'text', 'COM_JOOMGALLERY_CONFIG_SS_GS_NUMB_COLUMN', $this->_config->jg_searchcols);
    $searchthumbalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $searchthumbalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $searchthumbalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_searchthumbalign = JHTML::_('select.genericlist', $searchthumbalign, 'jg_searchthumbalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_searchthumbalign );
    JHTML::_('joomconfig.row', 'jg_searchthumbalign', 'custom', 'COM_JOOMGALLERY_CONFIG_SS_GS_THUMBALIGN', $mc_jg_searchthumbalign);
    $searchtextalign[] = JHTML::_('select.option', '1', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_LEFT'));
    $searchtextalign[] = JHTML::_('select.option', '3', JText::_('COM_JOOMGALLERY_COMMON_OPTION_CENTERED'));
    $searchtextalign[] = JHTML::_('select.option', '2', JText::_('COM_JOOMGALLERY_COMMON_OPTION_FLUSH_RIGHT'));
    $mc_jg_searchtextalign = JHTML::_('select.genericlist', $searchtextalign, 'jg_searchtextalign', 'class="inputbox" size="3"', 'value', 'text', $this->_config->jg_searchtextalign );
    JHTML::_('joomconfig.row', 'jg_searchtextalign', 'custom', 'COM_JOOMGALLERY_CONFIG_SS_GS_TEXTALIGN', $mc_jg_searchtextalign);
    JHTML::_('joomconfig.row', 'jg_showsearchdownload', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_DOWNLOAD', $this->_config->jg_showsearchdownload);
    JHTML::_('joomconfig.row', 'jg_showsearchfavourite', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_FAVOURITES_LINK', $this->_config->jg_showsearchfavourite);
    JHTML::_('joomconfig.row', 'jg_search_report_images', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_REPORT_IMAGES', $this->_config->jg_search_report_images);
    JHTML::_('joomconfig.row', 'jg_showsearcheditorlinks', 'yesno', 'COM_JOOMGALLERY_CONFIG_COMMON_EDITOR_LINKS', $this->_config->jg_showsearcheditorlinks);
    JHTML::_('joomconfig.end');

echo JHtml::_('tabs.end');

echo JHtml::_('tabs.end');

?>
    <input type="hidden" name="option" value="<?php echo _JOOM_OPTION; ?>" />
    <input type="hidden" name="controller" value="config" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
<?php if($this->_config->isExtended()): ?>
    <input type="hidden" name="id" value="<?php echo !JRequest::getInt('group_id') ? JRequest::getInt('id') : 0; ?>" />
    <input type="hidden" name="based_on" value="<?php echo JRequest::getInt('id'); ?>" />
    <input type="hidden" name="group_id" value="<?php echo JRequest::getInt('group_id'); ?>" />
<?php endif; ?>
    <?php JHtml::_('joomgallery.credits'); ?>
  </div>
</form>
