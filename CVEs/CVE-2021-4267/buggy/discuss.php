<?php
use XoopsModules\Tadtools\CkEditor;
use XoopsModules\Tadtools\FormValidator;
use XoopsModules\Tadtools\TadUpFiles;
use XoopsModules\Tadtools\Utility;
/*-----------引入檔案區--------------*/
require __DIR__ . '/header.php';
$xoopsOption['template_main'] = 'tad_discuss_discuss.tpl';
require_once XOOPS_ROOT_PATH . '/header.php';
$TadUpFiles = new TadUpFiles('tad_discuss');
/*-----------function區--------------*/

//tad_discuss編輯表單
function tad_discuss_form($BoardID = '', $DefDiscussID = '', $DefReDiscussID = '', $dir = 'left', $mode = '')
{
    global $xoopsDB, $xoopsUser, $isAdmin, $xoopsModuleConfig, $xoopsModule, $xoopsTpl, $TadUpFiles;

    if (empty($BoardID)) {
        return;
    }

    //取得本模組編號
    $module_id = $xoopsModule->mid();

    //取得目前使用者的群組編號
    if ($xoopsUser) {
        $uid = $xoopsUser->uid();
        $groups = $xoopsUser->getGroups();
    } else {
        $uid = 0;
        $groups = XOOPS_GROUP_ANONYMOUS;
    }

    $gpermHandler = xoops_getHandler('groupperm');

    if (!$gpermHandler->checkRight('forum_post', $BoardID, $groups, $module_id)) {
        if ('return' === $mode) {
            return;
        }

        header('location:index.php');
    }

    //抓取預設值
    if (!empty($DefDiscussID)) {
        $DBV = get_tad_discuss($DefDiscussID);
    } else {
        $DBV = [];
    }

    //設定「DiscussID」欄位預設值
    $DiscussID = (!isset($DBV['DiscussID'])) ? $DefDiscussID : $DBV['DiscussID'];

    //設定「ReDiscussID」欄位預設值
    $ReDiscussID = (!isset($DBV['ReDiscussID'])) ? $DefReDiscussID : $DBV['ReDiscussID'];

    //設定「uid」欄位預設值
    $uid = (!isset($DBV['uid'])) ? '' : $DBV['uid'];
    $uid = (is_object($xoopsUser) and empty($uid)) ? $xoopsUser->uid() : $uid;

    //設定「DiscussTitle」欄位預設值
    $DiscussTitle = (!isset($DBV['DiscussTitle'])) ? '' : $DBV['DiscussTitle'];

    //設定「DiscussContent」欄位預設值
    $DiscussContent = (!isset($DBV['DiscussContent'])) ? '' : $DBV['DiscussContent'];

    //設定「DiscussDate」欄位預設值
    $DiscussDate = (!isset($DBV['DiscussDate'])) ? date('Y-m-d H:i:s') : $DBV['DiscussDate'];

    //設定「BoardID」欄位預設值
    $BoardID = (!isset($DBV['BoardID'])) ? $BoardID : $DBV['BoardID'];

    //設定「LastTime」欄位預設值
    $LastTime = (!isset($DBV['LastTime'])) ? date('Y-m-d H:i:s') : $DBV['LastTime'];

    //設定「Counter」欄位預設值
    $Counter = (!isset($DBV['Counter'])) ? '' : $DBV['Counter'];

    //設定「onlyTo」欄位預設值
    $onlyTo = (!isset($DBV['onlyTo'])) ? '' : $DBV['onlyTo'];

    $op = (empty($DiscussID)) ? 'insert_tad_discuss' : 'update_tad_discuss';
    //$op="replace_tad_discuss";

    $FormValidator = new FormValidator('#myForm', true);
    $FormValidator->render();

    $RE = !empty($DefReDiscussID) ? get_tad_discuss($DefReDiscussID) : [];

    if (empty($ReDiscussID)) {
        $board_option = "<select name='BoardID' class='form-control'>" . get_tad_discuss_board_option($BoardID) . '</select>';
        $twidth = '76%';
    } else {
        $board_option = "<input type='hidden' name='BoardID' value='{$BoardID}'>";
        $twidth = '99%';
    }

    if (empty($DefReDiscussID)) {
        $DiscussTitle = "
        <div class='row' style='margin: 10px 0px;'>
            <div class='col-sm-3'>{$board_option}</div>
            <div class='col-sm-9'>
                <input type='text' name='DiscussTitle' value='{$DiscussTitle}' id='DiscussTitle' class='form-control validate[required]' placeholder='" . _MD_TADDISCUS_INPUT_TITLE . "' class=''>
            </div>
        </div>";
    } else {
        $DiscussTitle = "
        {$board_option}
        <input type='hidden' name='DiscussTitle' value='RE:{$RE['DiscussTitle']}'>";
    }

    $Board = get_tad_discuss_board($BoardID);
    if ('0' == $Board['BoardEnable']) {
        redirect_header('index.php', 3, _MD_TADDISCUS_BOARD_UNABLE);
    }

    //$BoardTitle=(empty($DefDiscussID) and empty($DefReDiscussID))?"<h1><a href='discuss.php?BoardID=$BoardID'>{$Board['BoardTitle']}</a></h1>":"";
    //die('$BoardID:'.$BoardID.',$DefDiscussID:'.$DefDiscussID.',$DefReDiscussID:'.$DefReDiscussID);
    if (!empty($BoardID) and empty($DefDiscussID) and empty($DefReDiscussID)) {
        $BoardTitle = get_board_title($BoardID);
    }

    $TadUpFiles->set_col('DiscussID', $DefDiscussID); //若 $show_list_del_file ==true 時一定要有
    $upform = $TadUpFiles->upform(true, 'upfile', 100, true);

    $checked = !empty($onlyTo) ? 'checked' : '';
    if ($DefReDiscussID) {
        $RE = get_tad_discuss($DefReDiscussID);
        $checked = !empty($RE['onlyTo']) ? 'checked' : '';
    }

    if ('CKEditor' === $xoopsModuleConfig['def_editor']) {

        $ck = new CkEditor('tad_discuss', 'DiscussContent', $DiscussContent);
        $ck->setToolbarSet('mySimple');
        $ck->setHeight(250);
        $editor = $ck->render();
    } else {
        $editor = "<textarea name='DiscussContent' cols='50' rows=8 id='DiscussContent' class='validate[required,minSize[5]]' style='width:100%; height:150px;font-size: 75%;line-height:150%;border:1px dotted #B0B0B0;'>{$DiscussContent}</textarea>";
    }
    $xoopsTpl->assign('def_editor', $xoopsModuleConfig['def_editor']);

    $captcha_js = '';
    $captcha_div = '';
    if (!is_object($xoopsUser)) {
        $captcha_js = "
        <link rel='stylesheet' type='text/css' href='class/Qaptcha3/jquery/QapTcha.jquery.css' media='screen'>
        <script type='text/javascript' src='class/Qaptcha3/jquery/jquery.ui.touch.js'></script>
        <script type='text/javascript' src='class/Qaptcha3/jquery/QapTcha.jquery.js'></script>
        <script type='text/javascript'>
            $(document).ready(function(){
            $('.QapTcha').QapTcha({disabledSubmit:true , autoRevert:true , PHPfile:'class/Qaptcha3/php/Qaptcha.jquery.php', txtLock:'" . _MD_TADDISCUS_TXTLOCK . "' , txtUnlock:'" . _MD_TADDISCUS_TXTUNLOCK . "'});
            });
        </script>";
        $captcha_div = "<div class='QapTcha'></div>";
        $only_root = '';
    } else {
        $only_root = "
        <label class='checkbox-inline'>
            <input type='checkbox' name='only_root' value='1' $checked>" . _MD_TADDISCUS_ONLY_ROOT . '
        </label>';
    }

    $DiscussContent = "
    $DiscussTitle
    <div style='margin: 10px 0px;'>
        {$editor}
    </div>
    <div class='row'>
        <div class='col-sm-6'>
            {$captcha_div}
        </div>
        <div class='col-sm-6 text-right'>
            {$only_root}
            <input type='hidden' name='OldBoardID' value='{$BoardID}'>
            <input type='hidden' name='DiscussID' value='{$DefDiscussID}'>
            <input type='hidden' name='ReDiscussID' value='{$ReDiscussID}'>
            <input type='hidden' name='uid' value='{$uid}'>
            <input type='hidden' name='op' value='{$op}'>
            <button type='submit' class='btn btn-primary'>" . _TAD_SAVE . "</button>
            {$captcha_js}
        </div>
    </div>
    {$upform}";

    $DiscussDate = date('Y-m-d H:i:s', xoops_getUserTimestamp(strtotime($DiscussDate)));

    if ('left' === $xoopsModuleConfig['display_mode']) {
        $dir = 'left';
        $width = 100;
    } elseif ('top' === $xoopsModuleConfig['display_mode']) {
        $dir = 'top';
        $width = 100;
    } elseif ('bottom' === $xoopsModuleConfig['display_mode']) {
        $dir = 'bottom';
        $width = 100;
    } elseif ('mobile' === $xoopsModuleConfig['display_mode']) {
        $dir = '';
        $width = 120;
    } elseif ('clean' === $xoopsModuleConfig['display_mode']) {
        $dir = '';
        $width = 50;
    } elseif ('default' === $xoopsModuleConfig['display_mode']) {
        $dir = $i % 2 ? 'left' : 'right';
        $width = 100;
    } else {
        $dir = '';
        $width = 100;
    }

    $all[0] = talk_bubble($BoardID, $DiscussID, $DiscussContent, $dir, $uid, $publisher, $DiscussDate, 'return', null, null, $width, $onlyTo);

    if ('return' === $mode) {
        return $all;
    }
    $xoopsTpl->assign('display_mode', $xoopsModuleConfig['display_mode']);
    $xoopsTpl->assign('op', $_REQUEST['op']);
    $xoopsTpl->assign('form_data', $all);
    $xoopsTpl->assign('uid', $uid);
}

//取得tad_discuss_board分類選單的選項（單層選單）
function get_tad_discuss_board_option($default_BoardID = '0')
{
    global $xoopsDB, $xoopsUser, $xoopsModule;

    //取得本模組編號
    $module_id = $xoopsModule->mid();

    //取得目前使用者的群組編號
    if ($xoopsUser) {
        $uid = $xoopsUser->uid();
        $groups = $xoopsUser->getGroups();
    } else {
        $uid = 0;
        $groups = XOOPS_GROUP_ANONYMOUS;
    }
    $gpermHandler = xoops_getHandler('groupperm');

    $sql = 'SELECT `BoardID` , `ofBoardID` , `BoardTitle` FROM `' . $xoopsDB->prefix('tad_discuss_board') . '` ORDER BY `BoardSort`';
    $result = $xoopsDB->query($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    $option = '';
    while (list($BoardID, $ofBoardID, $BoardTitle) = $xoopsDB->fetchRow($result)) {
        if (!$gpermHandler->checkRight('forum_post', $BoardID, $groups, $module_id)) {
            continue;
        }

        $selected = ($BoardID == $default_BoardID) ? 'selected' : '';
        // $option[$i]['selected']=$selected;
        // $option[$i]['BoardID']=$BoardID;
        // $option[$i]['ofBoardID']=$ofBoardID;
        // $option[$i]['BoardTitle']=$BoardTitle;
        $option .= "<option value='{$BoardID}' {$selected}>{$BoardTitle}</option>";
    }

    return $option;
}

//以流水號秀出某筆tad_discuss資料內容
function show_one_tad_discuss($DefDiscussID = '')
{
    global $xoopsDB, $xoopsModule, $xoopsUser, $isAdmin, $xoopsModuleConfig, $xoopsTpl, $xoTheme;

    $myts = \MyTextSanitizer::getInstance();
    if (empty($DefDiscussID)) {
        return;
    }
    $DefDiscussID = (int) $DefDiscussID;
    $discuss = get_tad_discuss($DefDiscussID);

    //取得本模組編號
    $module_id = $xoopsModule->mid();

    //取得目前使用者的群組編號
    if ($xoopsUser) {
        $now_uid = $xoopsUser->uid();
        $groups = $xoopsUser->getGroups();
    } else {
        $now_uid = 0;
        $groups = XOOPS_GROUP_ANONYMOUS;
    }

    $gpermHandler = xoops_getHandler('groupperm');
    if (!$gpermHandler->checkRight('forum_read', $discuss['BoardID'], $groups, $module_id)) {
        header('location:index.php');
    }

    if (0 != $discuss['ReDiscussID']) {
        header("location: {$_SERVER['PHP_SELF']}?DiscussID={$discuss['ReDiscussID']}&BoardID={$discuss['BoardID']}");
    }

    add_tad_discuss_counter($DefDiscussID);

    $js = "
    <script type='text/javascript' src='" . XOOPS_URL . "/modules/tadtools/jqueryCookie/jquery.cookie.js'></script>
    <link rel='stylesheet' type='text/css' media='screen' href='reset.css'>
    <script>
        function like(op,DiscussID){
            if($.cookie('like'+DiscussID)){
                alert('" . _MD_TADDISCUS_HAD_LIKE . "');
            }else{
            $.post('like.php',  {op: op , DiscussID: DiscussID} , function(data) {
                $('#'+op+DiscussID).html(data);
            });

            $.cookie('like'+DiscussID , true , { expires: 7 });
            }
        }


        function delete_tad_discuss_func(DiscussID){
            var sure = window.confirm('" . _TAD_DEL_CONFIRM . "');
            if (!sure)  return;
            location.href=\"{$_SERVER['PHP_SELF']}?op=delete_tad_discuss&ReDiscussID=$DefDiscussID&BoardID={$discuss['BoardID']}&DiscussID=\" + DiscussID;
        }
    </script>";

    $Board = get_tad_discuss_board($discuss['BoardID']);

    $sql = 'select * from ' . $xoopsDB->prefix('tad_discuss') . " where DiscussID='$DefDiscussID' or ReDiscussID='$DefDiscussID' order by ReDiscussID , DiscussDate";

    //Utility::getPageBar($原sql語法, 每頁顯示幾筆資料, 最多顯示幾個頁數選項);
    $PageBar = Utility::getPageBar($sql, $xoopsModuleConfig['show_bubble_amount'], 10);
    $bar = $PageBar['bar'];
    $sql = $PageBar['sql'];
    $total = $PageBar['total'];

    if (empty($total)) {
        redirect_header($_SERVER['PHP_SELF'], 3, _MD_TADDISCUS_THE_DISCUSS_EMPTY);
    }

    $result = $xoopsDB->query($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    $discuss_data = [];
    $i = 1;
    $first = '';

    $memberHandler = xoops_getHandler('member');
    while (false !== ($all = $xoopsDB->fetchArray($result))) {
        //以下會產生這些變數： $DiscussID , $ReDiscussID , $uid , $DiscussTitle , $DiscussContent , $DiscussDate , $BoardID , $LastTime , $Counter
        foreach ($all as $k => $v) {
            $$k = $v;
        }

        if (!isset($onlyTo1)) {
            $onlyTo1 = $onlyTo;
        }

        if ('left' === $xoopsModuleConfig['display_mode']) {
            $dir = 'left';
            $width = 100;
        } elseif ('top' === $xoopsModuleConfig['display_mode']) {
            $dir = 'top';
            $width = 100;
        } elseif ('bottom' === $xoopsModuleConfig['display_mode']) {
            $dir = 'bottom';
            $width = 100;
        } elseif ('mobile' === $xoopsModuleConfig['display_mode']) {
            $dir = '';
            $width = 120;
        } elseif ('clean' === $xoopsModuleConfig['display_mode']) {
            $dir = '';
            $width = 50;
        } elseif ('default' === $xoopsModuleConfig['display_mode']) {
            $dir = $i % 2 ? 'left' : 'right';
            $width = 100;
        } else {
            $dir = '';
            $width = 100;
        }

        if (empty($first)) {
            $first = $DiscussContent;
        }

        $discuss['DiscussTitle'] = str_replace('[s', "<img src='" . XOOPS_URL . '/modules/tad_discuss/images/smiles/s', $discuss['DiscussTitle']);
        $discuss['DiscussTitle'] = str_replace('.gif]', ".gif' hspace=2 align='absmiddle'>", $discuss['DiscussTitle']);

        $DiscussContent = str_replace('[s', "<img src='" . XOOPS_URL . '/modules/tad_discuss/images/smiles/s', $DiscussContent);
        $DiscussContent = str_replace('.gif]', ".gif' hspace=2 align='absmiddle'>", $DiscussContent);

        //若無任何標籤則套用nl2br
        if (false === mb_strpos($DiscussContent, '<')) {
            $DiscussContent = $myts->displayTarea($DiscussContent, 0, 1, 1, 1, 1);
        } else {
            $DiscussContent = $myts->displayTarea($DiscussContent, 1, 0, 0, 1, 0);
        }

        $discuss_data[$i] = talk_bubble($discuss['BoardID'], $DiscussID, $DiscussContent, $dir, $uid, $publisher, $DiscussDate, 'return', $Good, $Bad, $width, $onlyTo);
        $i++;
    }

    //if($xoopsUser){
    $dir = $i % 2 ? 'left' : 'right';
    $form_data = tad_discuss_form($discuss['BoardID'], '', $DefDiscussID, $dir, 'return');
    //}

    $onlyToName = getOnlyToName($onlyTo1);
    $discuss['DiscussTitle'] = isPublic($onlyTo1, $uid, $discuss['BoardID']) ? $discuss['DiscussTitle'] : sprintf(_MD_TADDISCUS_ONLYTO, $onlyToName);
    //die(var_export($discuss_data));
    $xoopsTpl->assign('BoardID', $discuss['BoardID']);
    $xoopsTpl->assign('BoardTitle', $Board['BoardTitle']);
    $xoopsTpl->assign('DiscussTitle', $discuss['DiscussTitle']);
    $xoopsTpl->assign('display_mode', $xoopsModuleConfig['display_mode']);
    $xoopsTpl->assign('op', 'show_one_tad_discuss');
    $xoopsTpl->assign('js', $js);
    $xoopsTpl->assign('discuss_data', $discuss_data);
    $xoopsTpl->assign('form_data', $form_data);
    $xoopsTpl->assign('bar', $bar);
    $xoopsTpl->assign('isPublic', isPublic($onlyTo1, $uid, $discuss['BoardID']));
    $xoopsTpl->assign('onlyTo', $onlyTo);
    $xoopsTpl->assign('ReDiscussID', $DefDiscussID);

    $title = $discuss['DiscussTitle'];
    $description = strip_tags($first);

    $fb_tag = "
      <meta property=\"og:title\" content=\"{$title}\">
      ";
    $xoopsTpl->assign('xoops_module_header', $fb_tag);
    $xoopsTpl->assign('xoops_pagetitle', $title);
    if (is_object($xoTheme)) {
        $xoTheme->addMeta('meta', 'keywords', $title);
    } else {
        $xoopsTpl->assign('xoops_meta_keywords', 'keywords', $title);
    }
}

//更新tad_discuss某一筆資料
function update_tad_discuss($DiscussID = '')
{
    global $xoopsDB, $xoopsUser, $TadUpFiles;

    $myts = \MyTextSanitizer::getInstance();
    $DiscussTitle = $myts->addSlashes($_POST['DiscussTitle']);
    $DiscussContent = $myts->addSlashes($_POST['DiscussContent']);

    if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $myip = $_SERVER['REMOTE_ADDR'];
    } else {
        $myip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $myip = $myip[0];
    }

    $anduid = onlyMine($DiscussID);

    if (chk_spam($DiscussTitle)) {
        redirect_header($_SERVER['PHP_SELF'], 3, _MD_TADDISCUS_FOUND_SPAM);
    }

    if (chk_spam($DiscussContent)) {
        redirect_header($_SERVER['PHP_SELF'], 3, _MD_TADDISCUS_FOUND_SPAM);
    }

    $onlyTo = '';
    $ReDiscussID = isset($_POST['ReDiscussID']) ? (int) $_POST['ReDiscussID'] : 0;
    $BoardID = isset($_POST['BoardID']) ? (int) $_POST['BoardID'] : 0;
    $OldBoardID = isset($_POST['OldBoardID']) ? (int) $_POST['OldBoardID'] : 0;

    $Discuss = get_tad_discuss($ReDiscussID);
    if ('1' == $_POST['only_root'] and !empty($ReDiscussID)) {
        $onlyTo = $Discuss['uid'];
    } elseif ('1' == $_POST['only_root']) {
        $memberHandler = xoops_getHandler('member');
        $adminusers = $memberHandler->getUsersByGroup(1);
        $onlyTo = implode(',', $adminusers);
    }

    //$now=date('Y-m-d H:i:s',xoops_getUserTimestamp(time()));
    $time = date('Y-m-d H:i:s');
    $sql = 'update ' . $xoopsDB->prefix('tad_discuss') . " set
   `BoardID` = '{$BoardID}' ,
   `DiscussTitle` = '{$DiscussTitle}' ,
   `DiscussContent` = '{$DiscussContent}' ,
   `LastTime` = '$time',
   `FromIP` = '$myip',
   `onlyTo` = '$onlyTo'
  where DiscussID='$DiscussID' $anduid";

    //die($sql);
    $xoopsDB->queryF($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    if ($OldBoardID != $BoardID) {
        $sql = 'update ' . $xoopsDB->prefix('tad_discuss') . " set
     `BoardID` = '{$BoardID}'
    where ReDiscussID='$DiscussID'";
        $xoopsDB->queryF($sql) or Utility::web_error($sql, __FILE__, __LINE__);
    }

    $TadUpFiles->set_col('DiscussID', $DiscussID);
    $TadUpFiles->upload_file('upfile', 1024, 120, null, '', true);

    return $DiscussID;
}

function change_lock($lock, $BoardID, $DiscussID)
{
    global $xoopsDB, $xoopsUser;

    $anduid = onlyMine($DiscussID);

    $onlyTo = '';

    if ($lock) {
        $ReDiscussID = isset($_REQUEST['ReDiscussID']) ? (int) $_REQUEST['ReDiscussID'] : 0;
        $Discuss = get_tad_discuss($ReDiscussID);
        if ('1' == $_POST['only_root'] and !empty($ReDiscussID)) {
            $onlyTo = $Discuss['uid'];
        } elseif ('1' == $_POST['only_root']) {
            $adminusers = $memberHandler->getUsersByGroup(1);
            $onlyTo = implode(',', $adminusers);
        }
    }

    $sql = 'update ' . $xoopsDB->prefix('tad_discuss') . " set
   `onlyTo` = '$onlyTo'
  where DiscussID='$DiscussID' $anduid";
    //die($sql);
    $xoopsDB->queryF($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    return $DiscussID;
}

//新增tad_discuss計數器
function add_tad_discuss_counter($DiscussID = '')
{
    global $xoopsDB, $xoopsModule;
    $sql = 'update ' . $xoopsDB->prefix('tad_discuss') . " set `Counter`=`Counter`+1 where `DiscussID`='{$DiscussID}'";
    $xoopsDB->queryF($sql) or Utility::web_error($sql, __FILE__, __LINE__);
}

/*-----------執行動作判斷區----------*/
require_once $GLOBALS['xoops']->path('/modules/system/include/functions.php');
$op = system_CleanVars($_REQUEST, 'op', '', 'string');
$BoardID = system_CleanVars($_REQUEST, 'BoardID', 0, 'int');
$DiscussID = system_CleanVars($_REQUEST, 'DiscussID', 0, 'int');
$ReDiscussID = system_CleanVars($_REQUEST, 'ReDiscussID', 0, 'int');
$files_sn = system_CleanVars($_REQUEST, 'files_sn', 0, 'int');

$xoopsTpl->assign('toolbar', Utility::toolbar_bootstrap($interface_menu));
$xoopsTpl->assign('jquery', Utility::get_jquery(true));
$xoopsTpl->assign('isAdmin', $isAdmin);
if ($xoopsUser) {
    $xoopsTpl->assign('now_uid', $xoopsUser->uid());
} else {
    $xoopsTpl->assign('now_uid', '--');
}

switch ($op) {
    //新增資料
    case 'insert_tad_discuss':
        $DiscussID = insert_tad_discuss();
        redirect_header("discuss.php?DiscussID={$DiscussID}&BoardID={$BoardID}", 0, _MD_TADDISCUS_SAVE_OK);
        break;
    //更新資料
    case 'update_tad_discuss':
        update_tad_discuss($DiscussID);
        $ID = empty($ReDiscussID) ? $DiscussID : $ReDiscussID;
        header("location: {$_SERVER['PHP_SELF']}?DiscussID=$ID&BoardID=$BoardID");
        exit;
        break;
    //刪除資料
    case 'delete_tad_discuss':
        delete_tad_discuss($DiscussID);
        header("location: {$_SERVER['PHP_SELF']}?BoardID=$BoardID");
        exit;
        break;
    //輸入表格
    case 'tad_discuss_form':
        tad_discuss_form($BoardID, $DiscussID, $ReDiscussID);
        break;
    //下載檔案
    case 'tufdl':
        $files_sn = isset($_GET['files_sn']) ? (int) $_GET['files_sn'] : '';
        $TadUpFiles->add_file_counter($files_sn);
        exit;
        break;
    case 'unlock':
        change_lock(false, $BoardID, $DiscussID);
        header("location: {$_SERVER['PHP_SELF']}?DiscussID=$DiscussID&BoardID=$BoardID");
        exit;
        break;
    case 'lock':
        change_lock(true, $BoardID, $DiscussID);
        header("location: {$_SERVER['PHP_SELF']}?DiscussID=$DiscussID&BoardID=$BoardID");
        exit;
        break;
    //預設動作
    default:
        if (empty($DiscussID)) {
            list_tad_discuss($BoardID);
        } else {
            show_one_tad_discuss($DiscussID);
        }
        break;
}

/*-----------秀出結果區--------------*/
require_once XOOPS_ROOT_PATH . '/footer.php';
