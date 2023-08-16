<?php
use XoopsModules\Tadtools\FooTable;
use XoopsModules\Tadtools\TadUpFiles;
use XoopsModules\Tadtools\Utility;
/*-----------引入檔案區--------------*/
require __DIR__ . '/header.php';
$xoopsOption['template_main'] = 'tad_discuss_index.tpl';
require_once XOOPS_ROOT_PATH . '/header.php';
$TadUpFiles = new TadUpFiles('tad_discuss');
/*-----------function區--------------*/

//列出所有tad_discuss_board資料
function list_tad_discuss_board($ofBoardID = 0, $mode = 'tpl')
{
    global $xoopsDB, $xoopsModule, $isAdmin, $xoopsUser, $xoopsTpl, $TadUpFiles, $xoopsModuleConfig;

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

    $sql = 'select * from `' . $xoopsDB->prefix('tad_discuss_board') . "` where BoardEnable='1' and `ofBoardID`='$ofBoardID' order by BoardSort";
    $result = $xoopsDB->query($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    $all_content = [];
    $i = 0;
    while (false !== ($all = $xoopsDB->fetchArray($result))) {
        //以下會產生這些變數： $BoardID , $BoardTitle , $BoardDesc , $BoardManager , $BoardEnable
        foreach ($all as $k => $v) {
            $$k = $v;
        }

        if (!$gpermHandler->checkRight('forum_read', $BoardID, $groups, $module_id)) {
            continue;
        }
        $post = $gpermHandler->checkRight('forum_post', $BoardID, $groups, $module_id);

        //$pic=get_pic_file('BoardID' , $BoardID , 1 , 'thumb');
        $TadUpFiles->set_col('BoardID', $BoardID);
        $pic = $TadUpFiles->get_pic_file('thumb'); //thumb 小圖, images 大圖（default）, file 檔案
        $pic = empty($pic) ? 'images/board.png' : $pic;

        $display_number = isset($xoopsModuleConfig['display_number']) ? (int) $xoopsModuleConfig['display_number'] : 7;
        $list_tad_discuss = list_tad_discuss_short($BoardID, $display_number);

        $fun = ($isAdmin) ? "<a href='admin/main.php?op=tad_discuss_board_form&BoardID=$BoardID'><img src='images/edit.png' alt='" . _TAD_EDIT . "'></a>" : '';
        $BoardManager = implode(' , ', getBoardManager($BoardID, 'uname'));

        $BoardNum = get_board_num($BoardID);
        $DiscussNum = get_board_num($BoardID, false);

        $all_content[$i]['post'] = $post;
        $all_content[$i]['pic'] = $pic;
        $all_content[$i]['BoardTitle'] = $BoardTitle;
        $all_content[$i]['BoardID'] = $BoardID;
        $all_content[$i]['ofBoardID'] = $ofBoardID;
        $all_content[$i]['fun'] = $fun;
        $all_content[$i]['BoardNum'] = sprintf(_MD_TADDISCUS_BOARD_DISCUSS, number_format($BoardNum));
        $all_content[$i]['DiscussNum'] = sprintf(_MD_TADDISCUS_ALL_DISCUSS, number_format($DiscussNum));
        $all_content[$i]['list_tad_discuss'] = $list_tad_discuss;
        $all_content[$i]['BoardManager'] = $BoardManager;
        $all_content[$i]['subBoard'] = list_tad_discuss_board($BoardID, 'return');

        $i++;
    }

    if ('return' === $mode) {
        return $all_content;
    }

    $FooTable = new FooTable();
    $FooTableJS = $FooTable->render();

    $xoopsTpl->assign('FooTableJS', $FooTableJS);
    $xoopsTpl->assign('all_content', $all_content);

    if ($xoopsUser) {
        $xoopsTpl->assign('login', true);
    } else {
        $xoopsTpl->assign('login', false);
    }
}

//列出所有tad_discuss資料
function list_tad_discuss_short($BoardID = null, $limit = null)
{
    global $xoopsDB, $xoopsModule, $xoopsUser, $xoopsTpl;

    $andBoardID = (empty($BoardID)) ? '' : "and a.BoardID='$BoardID'";
    $andLimit = null !== $limit ? "limit 0,$limit" : '';
    $sql = 'select a.*,b.* from ' . $xoopsDB->prefix('tad_discuss') . ' as a left join ' . $xoopsDB->prefix('tad_discuss_board') . " as b on a.BoardID = b.BoardID where a.ReDiscussID='0' $andBoardID  order by a.LastTime desc $andLimit";

    $result = $xoopsDB->query($sql) or Utility::web_error($sql, __FILE__, __LINE__);

    $main_data = [];
    $i = 0;
    while (false !== ($all = $xoopsDB->fetchArray($result))) {
        //以下會產生這些變數： $DiscussID , $ReDiscussID , $uid , $DiscussTitle , $DiscussContent , $DiscussDate , $BoardID , $LastTime , $Counter
        foreach ($all as $k => $v) {
            $$k = $v;
        }

        $renum = get_re_num($DiscussID);
        //$show_re_num=empty($renum)?"":sprintf(_MD_TADDISCUS_RE_DISCUSS,$renum);

        $uid_name = \XoopsUser::getUnameFromId($uid, 1);
        $LastTime = mb_substr($LastTime, 0, 10);

        $isPublic = isPublic($onlyTo, $uid, $BoardID);
        $onlyToName = getOnlyToName($onlyTo);
        $DiscussTitle = $isPublic ? $DiscussTitle : sprintf(_MD_TADDISCUS_ONLYTO, $onlyToName);

        $DiscussTitle = str_replace('[s', "<img src='" . XOOPS_URL . '/modules/tad_discuss/images/smiles/s', $DiscussTitle);
        $DiscussTitle = str_replace('.gif]', ".gif' hspace=2 align='absmiddle'>", $DiscussTitle);

        $main_data[$i]['LastTime'] = $LastTime;
        $main_data[$i]['DiscussID'] = $DiscussID;
        $main_data[$i]['BoardID'] = $BoardID;
        $main_data[$i]['DiscussTitle'] = $DiscussTitle;
        $main_data[$i]['uid_name'] = $uid_name;
        $main_data[$i]['renum'] = $renum;

        $i++;
    }

    return $main_data;
}

/*-----------執行動作判斷區----------*/
require_once $GLOBALS['xoops']->path('/modules/system/include/functions.php');
$op = system_CleanVars($_REQUEST, 'op', '', 'string');
$BoardID = system_CleanVars($_REQUEST, 'BoardID', 0, 'int');
$DiscussID = system_CleanVars($_REQUEST, 'DiscussID', 0, 'int');
$files_sn = system_CleanVars($_REQUEST, 'files_sn', 0, 'int');

$xoopsTpl->assign('toolbar', Utility::toolbar_bootstrap($interface_menu));
$xoopsTpl->assign('jquery', Utility::get_jquery(true));

switch ($op) {
    default:
        list_tad_discuss_board(0);
        break;
}

/*-----------秀出結果區--------------*/
require_once XOOPS_ROOT_PATH . '/footer.php';
