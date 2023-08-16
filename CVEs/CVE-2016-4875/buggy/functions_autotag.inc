<?php
/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | UserBox プラグイン   function.inc からrequire
// | 自動タグおよび UserBox 専用関数
// +---------------------------------------------------------------------------+
// $Id: plugins/userbox/functions_autotag.inc
//20101223 tsuchitani AT ivywe DOT co DOT jp http://www.ivywe.co.jp/
//20120416 userbox_category {data_edit}

if (strpos ($_SERVER['PHP_SELF'], 'functions_autotag.inc') !== false) {
    die ('This file can not be used on its own.');
}

//=====自動タグ=================================================================
function plugin_autotags_userbox (
    $op
    , $content = ''
    , $autotag = '')
// +---------------------------------------------------------------------------+
// | 自動タグ
// | 書式 plugin_autotags_userbox ($op, $content, $autotag)
// +---------------------------------------------------------------------------+
// 20101229
{
    global $_TABLES;
    global $_CONF;
    global $LANG_USERBOX_autotag_desc;
         
    //Option Display
    if ($op == 'tagname' ) {
        $tagname[]='userbox';
        return $tagname;
    } elseif ($op == 'permission' || $op == 'nopermission') {
        if ($op == 'permission') {
            $flag = true;
        } else {
            $flag = false;
        }

        if (isset($_GROUPS['UserBox Admin'])) {
            $group_id = $_GROUPS['UserBox Admin'];
        } else {
            $group_id = DB_getItem($_TABLES['groups'], 'grp_id',
                                   "grp_name = 'UserBox Admin'");
        }
        $owner_id = SEC_getDefaultRootUser();

        if (COM_getPermTag(
            $owner_id
            , $group_id
            , $_USERBOX_CONF['autotag_permissions_userbox'][0]
            , $_USERBOX_CONF['autotag_permissions_userbox'][1]
            , $_USERBOX_CONF['autotag_permissions_userbox'][2]
            , $_USERBOX_CONF['autotag_permissions_userbox'][3]) == $flag) {
            return $tagname;
        }

    } elseif ($op == 'description') {
        return $LANG_USERBOX_autotag_desc;
    } else if ($op == 'parse') {
        //引数チェック
        $p1 = COM_applyFilter ($autotag['parm1']);
        $p2 = COM_applyFilter ($autotag['parm2']);
        $tag = COM_applyFilter ($autotag['tag']);
        $tagstr = COM_applyFilter ($autotag['tagstr']);
        
        $parm2_ary[]="category_id";
        $parm2_ary[]="category_code";
        $parm2_ary[]="mode";
        $parm2_ary[]="rss_file";
        $parm2_ary[]="title_trim_length";
        $parm2_ary[]="intervalday";
        $parm2_ary[]="limitcnt";
        $parm2_ary[]="newmarkday";
        $parm2_ary[]="templatedir";
        $parm2_ary[]="id";
        $parm2_ary[]="uid";
        $parm2_ary[]="username";
        $parm2_ary[]="code";
        $parm2_ary[]="nohitmsg";
        $parm2_ary[]="perpage";
        $parm2_ary[]="page";
        $parm2_ary[]="order";
        $parm2_ary[]="templatesetvar";
        $parm2_ary[]="value";
        $parm2_ary[]="field_id";
        $parm2_ary[]="expired";
        $parm2_ary[]="group_id";
        $parm2_ary[]="group_code";
        $parm2_ary[]="field_code";
        $parm2_ary[]="option";
        $parm2_ary[]="method";
        $parm2_ary[]="roundprecision";
        $parm2_ary[]="numberformat";
        $parm2_ary[]="permission";
        $p=userbox_parm2($p2,$parm2_ary);

        //各処理
        switch( $tag ) {
            case 'userbox' :
                switch($p1) {
                    case 'count':
                        $rt= userbox_count(
                            $p['category_id']
                            ,$p['category_code']
                            ,$p['mode']
                            ,$p['permission']
                            ,$p['numberformat']
                            );
                        break;
                    case 'newlist':
                            if ($p['category_id']<>""){
                                $m="id";
                                $categories=$p['category_id'];
                            }elseif ($p['category_code']<>"") {
                                $m="code";
                                $categories=$p['category_code'];
                            }else{
                                $m="ALL";
                                $categories="";
                            }
                            $rt= userbox_newlist(
                            $m
                            ,$categories
                            ,$p['rss_file']
                            ,$p['title_trim_length']
                            ,$p['intervalday']
                            ,$p['limitcnt']
                            ,$p['newmarkday']
                            ,$p['templatedir']
                            );
                        break;

                    case 'profile':
                        $w= userbox_profile(
                            $p['uid']
                            ,$p['templatedir']
                            ,$p['nohitmsg']
                            ,""
                            ,$p['username']
                            );
                        $rt=$w['display'];
                        break;
                    case 'category':
                        $rt= userbox_category(
                            "autotag"
                            ,$p['category_id']
                            ,$p['templatedir']
                            ,$p['nohitmsg']
                            ,$p['perpage']
                            ,$p['page']
                            ,$p['order']
                            ,$p['category_code']
                            ,$p['mode']
                            ,$p['expired']
                        );
                        break;
                    case 'attributedatacount':
                        $rt=DATABOX_count_each_afield(
                            "userbox"
                            ,$p['templatesetvar']
       	                    ,$p['value']
                            ,$p['type_id']
                            ,$p['permission']
                            ,$p['numberformat']
                            );
                        break;
                    case 'lastmodified':
                        $rt= userbox_grp(
                            "modified"
                            ,"max"
                            ,"lastmodified"
                            ,$p['category_id']
                            ,$p['category_code']
                            );
                        break;
                    case 'lastcreated':
                        $rt= userbox_grp(
                            "created"
                            ,"max"
                            ,"lastcreated"
                            ,$p['category_id']
                            ,$p['category_code']
                            );
                            break;
                    case 'attribute':
                        $rt= userbox_field(
                            "autotag"
                            ,$p['field_id']
                            ,$p['value']
                            ,$p['templatedir']
                            ,$p['nohitmsg']
                            ,$p['perpage']
                            ,$p['page']
                            ,$p['order']
                            ,$p['field_code']
                        );
                        break;
                    case 'msg':
                        $rt=userbox_msg($p['for']) ;
                        break;
                    case 'categorycount':
                        $rt= DATABOX_categorycount(
                             "userbox"
                            ,$p['group_id']
                            ,$p['group_code']
                            ,$p['permission']
                            ,$p['numberformat']
                        );
                        break;
                    case 'attributevalue':
                        $rt=DATABOX_fieldvalue(
                                "userbox"
                                ,$p['id']
                                ,$p['code']
                                ,$p['field_id']
                                ,$p['field_code']
                                ,$p['option']
                            );
                        break;
                    case 'math':
                        $rt=DATABOX_math(
                                "userbox"
                                ,$p['method']
                                ,$p['templatesetvar']
                                ,$p['type_id']
                                ,$p['category_id']
                                ,$p['category_code']
                                ,$p['mode']
                                ,$p['roundprecision']
                                ,$p['numberformat']
                                ,$p['permission']
                            );
                        break;
                }

                $content = str_replace ($autotag['tagstr'], $rt, $content);
                return $content;
        }
    }
}

 

//第二引数分割
function userbox_parm2 ($p2,$parm2_ary)
{
    $ary=array();
    $px = explode (' ', trim ($p2));
    if (is_array ($px)) {
        foreach ($px as $part) {
            $a = explode (':', $part);
            if (in_array($a[0],$parm2_ary)){
                $ary["{$a[0]}"]=$a[1];
                $skip++;            
            }
        }
        
        if (count ($px) > $skip) {
            for ($i = 0; $i < $skip; $i++) {
                array_shift ($px);
            }
            $ary["lastparm2"] = trim(implode (' ', $px));
        }

    }else{
        $ary["lastparm2"]=trim($p2);
    }
    
    return $ary;

}
//=====ブロック関数============================================================
function phpblock_shownewuserbox()
// +---------------------------------------------------------------------------+
// | 機能  ブロック用 新着data一覧出力                                         |
// | 書式 phpblock_shownewuserbox()                                            |
// +---------------------------------------------------------------------------+
{
    return userbox_newlist();
}
//=====専用関数============================================================
//
function userbox_category(
    $autotag
    ,$category_id=null
    ,$template=null
    ,$nohitmsg=""
    ,$perpage =0
    ,$page=null
    ,$order=null
    ,$category_code=null
    ,$mode=''
    ,$expired=''
)

// +---------------------------------------------------------------------------+
// | 機能  カテゴリ別一覧表示
// | 書式  userbox_category($id,$template,"yes","page",$perpage)
// | 書式  userbox_category($p['category_id'],$p['thtml'],$p['nohitmsg']
// | 書式                    ,"",$p['perpage'],$p['order'],$p['code']
// +---------------------------------------------------------------------------+
// | 引数 $category_id:カテゴリid
// | 引数 $template:使用するテンプレートのフォルダ名
// | 引数 $nohitmsg      :"yes"の時nohitメッセージ表示
// | 引数 $m             :"page"の時ヘッダ表示
// | 引数 $perpage       :1pageあたりの件数、0の時改ページなし
// | 引数 $order         :順序
// | 引数 $code          :
// | 引数 $mode          :ブランク　または　AND
// +---------------------------------------------------------------------------+
// | public_html/category.php および　自動タグで使用
// | ヘッダはコンフィギュレーション設定により表示されない場合があります
// | idもcodeも指定されない場合は、全カテゴリ
// +---------------------------------------------------------------------------+
// | 戻値 nomal:category list
// +---------------------------------------------------------------------------+
{
    $pi_name="userbox";

    global $_CONF;
    global $_TABLES;

    global $_USERBOX_CONF;
    global $LANG_USERBOX;
    global $LANG_USERBOX_ADMIN;
    global $LANG28;
    global $_USER;
    
    global $_IMAGE_TYPE;
    global $LANG_confignames;

    
    //ログイン要否チェック
    if (COM_isAnonUser()){
        if  ($_CONF['loginrequired']
                OR ($_USERBOX_CONF['loginrequired'] >1) ){
            return $LANG_USERBOX['loginrequired'];
        }

    }

    //-----テーブル
    $tbl1=$_TABLES['USERBOX_category'] ;
    $tbl2=$_TABLES['USERBOX_base'] ;
    $tbl3=$_TABLES['USERBOX_addition'] ;
    $tbl4=$_TABLES['users'] ;
    //
    $tbl5=$_TABLES['USERBOX_def_category'] ;
    
    $tbl6=$_TABLES['USERBOX_stats'];
    
    $datefield=$_USERBOX_CONF['datefield'];//使用する日付（編集日付、作成日付）
    $new_img=$_USERBOX_CONF['new_img'];
    if ($new_img==""){
        $new_img="New!";
    }
    $newmarkday=$_USERBOX_CONF['newmarkday'];
    if ($newmarkday==""){
        $newmarkday=3;
    }
    $chkday=strtotime("- $newmarkday days",time());

    //-----引数チェック
    $ids="";
    $idsary=array();
    if (is_null($category_id) OR $category_id==0){
        if (is_null($category_code)){
        }else{
            $w=explode("|",$category_code);
            foreach( $w as $val ){
                if ($ids<>""){
                    $ids.=",";
                }
                $w_id=DATABOX_codetoid($val,'USERBOX_def_category',"category_id");
                $idsary[]=$w_id;
                $ids.=$w_id;
            }
        }
    }else{
        $ids=$category_id;
        $idsary[]=$category_id;
    }
    
    //--CATEGORY
    $category_name="";
    $defaulttemplatesdirectory = "";
    if (count($idsary)<>0){
        
        for ($i = 0; $i < count($idsary); $i++) {
            $sql = "SELECT ";
            $sql .= " name ".LB;
            $sql .= " ,code ".LB;
            $sql .= " ,description ".LB;
            $sql .= " ,defaulttemplatesdirectory".LB;
            $sql .= " FROM ".LB;
            $sql .= " {$tbl5} AS t5 ".LB;
            $sql .= " WHERE ".LB;
            $sql .= " t5.category_id =".$idsary[$i].LB;
        
            $result = DB_query ($sql);
            $numrows = DB_numRows ($result);
            if ($numrows > 0) {
                $A = DB_fetchArray ($result);
                $category_name.=COM_applyFilter($A['name'])." ";
                $category_description.=COM_applyFilter($A['description'])." ";
                if ($i==0){
                    $defaulttemplatesdirectory=COM_applyFilter($A['defaulttemplatesdirectory']);
                }
            }
        }
    }

    if (is_null($template) or ($template==="")){
        if ($defaulttemplatesdirectory===""){
            $template="default";
        }else{
            $template=$defaulttemplatesdirectory;
        }
    }
    
    //
    if (is_null($page) OR !isset($page) OR $page == 0) {
        $page = 1;
    }
    if ($perpage===0 OR is_null($perpage)){
        $perpage=$_USERBOX_CONF['perpage'];
    }
    //
    $w=userbox_orderby($datefield,$order,$orderby,$addfieldorder,$field_id);

    //-----    
    $sql = "SELECT ";

    $sql .= " t2.id ".LB;
    $sql .= " ,t2.description ".LB;
    $sql .= " ,t2.released ".LB;
    $sql .= " ,t2.expired ".LB;
    $sql .= " ,t2.".$datefield." AS datefield ".LB;
    $sql .= " ,t2.fieldset_id ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.".$datefield.") AS datefield_un ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.released ) AS released_un ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.expired ) AS expired_un ".LB;

    $sql .= " ,t2.group_id";
    $sql .= " ,t2.owner_id";

    $sql .= " ,t4.username";
    $sql .= " ,t4.fullname";
    $sql .= " ,t4.photo";
    $sql .= " ,t4.email";
    if ($addfieldorder){
        $sql .= " ,t3.value ".LB;
    }
    
    //--FROM
    $sql .= " FROM ".LB;
    $sql .= " {$tbl2} AS t2 ".LB;
    $sql .= " ,{$tbl4} AS t4 ".LB;
    if ($addfieldorder){
        $sql .= " ,{$tbl3} AS t3 ".LB;
    }
    
    //--WHERE
    $sql .= " WHERE ".LB;
    $sql .= " t2.id=t4.uid ".LB;

    if (count($idsary)<>0){
        if (strtoupper($mode)=="AND"){
            $w="";
            foreach( $idsary as $val ){
                if ($w<>""){
                    $w.=" AND ";
                }
                $w.=$val." IN (SELECT t1.category_id";
                $w.=" FROM {$tbl1} AS t1 ,{$tbl5} AS t5";
                $w.=" WHERE t2.id = t1.id AND t1.category_id = t5.category_id AND t5.allow_display<2)";
            }
        }else{
            $w= "t2.id IN (SELECT id FROM {$tbl1} AS t1 ,{$tbl5} AS t5 ";
            $w.="WHERE t1.category_id = t5.category_id AND t5.allow_display<2 AND t1.category_id IN ({$ids})) ";
        }
        $sql.=" AND ({$w})".LB;
    }
    
    if ($addfieldorder){
        $sql .= " AND t3.field_id=".$field_id.LB;
        $sql .= " AND t3.id=t2.id".LB;
    }
    //下書データはのぞく
    $sql .= " AND t2.draft_flag=0".LB;
    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND',0,2,"t2");
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;
    //公開終了日を過ぎたデータはのぞく
    if  (strtoupper($expired)=="NO"){
        $sql .= " AND (expired=0 OR expired > NOW())";
    }
    
    //--ORDER
    $sql .= " ORDER BY ".LB;
    $sql .= $orderby.LB;
    
    //
    $result = DB_query ($sql);
    $cnt = DB_numRows ($result);

    $pages = 0;
     if ($perpage > 0) {
        $pages = ceil($cnt / $perpage);
    }
    $offset = ($page - 1) * $perpage;
    $sql .= " LIMIT $offset, $perpage";

    //自動タグでない時　ヘッダ、左ブロック
    if ($autotag==="notautotag"){
        if ($page > 1) {
            $page_title = sprintf ('%s (%d)', $category_name, $page);
        } else {
            $page_title = sprintf ('%s ', $category_name);
        }
        // Meta Tags
        $headercode=DATABOX_getheadercode(    
            "category"
            ,$template
            ,$pi_name
            ,$category_id
            ,$category_name
            ,$category_description
            ,$category_name
            ,$category_description);
        $retval .= DATABOX_siteHeader($pi_name,'',$page_title,$headercode) ;
    }


    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    if ($numrows > 0) {
        
        $tmplfld=DATABOX_templatePath('category',$template,$pi_name);
        $templates = new Template($tmplfld);
        $templates->set_file (array (
            'list' => 'list_detail.thtml',
            'nav'   => 'navigation_detail.thtml',
            'row'   => 'row.thtml',
            'col'   => "col_detail.thtml",
            'pagenav'  => 'pagenavigation.thtml'
            ));
        $languageid=COM_getLanguageId();
        $language= COM_getLanguage();
        $templates->set_var ('languageid', $languageid);
        $templates->set_var ('language', $language);
        if ($languageid<>"") {
            $templates->set_var ('_languageid', "_".$languageid);
        }else{
            $templates->set_var ('_languageid', "");
        }
        
        $templates->set_var ('site_url',$_CONF['site_url']);
        $templates->set_var ('this_script',THIS_SCRIPT);

        //bread
        $templates->set_var ('home',$LANG_USERBOX['home']);

        $url=$_CONF['site_url']."/userbox/category.php";
        $category_top="<a href='".$url."'>".$LANG_USERBOX['category_top']."</a>";
        $templates->set_var ('category_top',$category_top);

        $templates->set_var ('lang_category_list_h2',$LANG_USERBOX['category_list_h2']);

        $templates->set_var ('category_name',$category_name);
        $templates->set_var ('category_code',$category_code);
        $templates->set_var ('category_description',$category_description);
    

        //page
        $templates->set_var ('cnt', $cnt);
        $lin1=$offset+1;
        $lin2=$lin1+$perpage - 1;
        if ($lin2>$cnt){
            $lin2=$cnt;
        }

        //summary navigation
        $templates->set_var ('lang_view', $LANG_USERBOX['view']);
        $templates->set_var ('lin', $lin1."-".($lin2));
        $templates->set_var ('cnt', $cnt);

        $templates->set_var ('lang_name', $LANG_USERBOX_ADMIN['name']);

        $templates->set_var ('lang_username', $LANG_USERBOX_ADMIN['username']);
        $templates->set_var ('lang_id', $LANG_USERBOX_ADMIN['id']);
        $templates->set_var ('lang_uid', $LANG28[2]);
        $templates->set_var ('lang_username', $LANG28[3]);
        $templates->set_var ('lang_fullname',$LANG28[4]);

        //
        $templates->set_var ('lang_date', $LANG_USERBOX_ADMIN[$datefield]);
        $templates->set_var ('lang_released', $LANG_USERBOX_ADMIN['released']);
        $templates->set_var ('lang_expired', $LANG_USERBOX_ADMIN['expired']);
        $templates->set_var ('lang_remaingdays', $LANG_USERBOX_ADMIN['remaingdays']);
        $templates->set_var ('lang_description', $LANG_USERBOX_ADMIN['description']);
        $templates->set_var ('lang_addfield', $addfield_name);
        // 追加項目のヘッダ
        $addition_def=DATABOX_getadditiondef($pi_name);
        //

        $templates->set_var('lang_imgfile_frd', $LANG_confignames['userbox']['imgfile_frd']);
        $templates->set_var ('imgfile_frd', $_USERBOX_CONF['imgfile_frd']);
        $templates->set_var ('data_img_url', $_CONF['site_url']."/".$_USERBOX_CONF['imgfile_frd']);
        $templates->set_var('lang_imgfile_thumb_frd', $LANG_confignames['userbox']['imgfile_thumb_frd']);
        $templates->set_var ('imgfile_thumb_frd', $_USERBOX_CONF['imgfile_thumb_frd']);
        $templates->set_var ('data_thumb_img_url', $_CONF['site_url']."/".$_DATABOX_CONF['imgfile_thumb_frd']);
            
        for ($i = 0; $i < $numrows; $i++) {
            $A = DB_fetchArray ($result);
            $A = array_map('stripslashes', $A);

            $description=COM_applyFilter($A['description']);
            $username=COM_applyFilter($A['username']);
            $fullname=COM_applyFilter($A['fullname']);
            $id=COM_applyFilter($A['id']);
            $datefield=COM_applyFilter($A['datefield']);
            $released=COM_applyFilter($A['released']);
            $expired=COM_applyFilter($A['expired']);
            $datefield_ary = COM_getUserDateTimeFormat($A['datefield_un']);
            $released_ary = COM_getUserDateTimeFormat($A['released_un']);
            if ($expired==="0000-00-00 00:00:00"){
                $expired_ary=array();
            }else{    
                $expired_ary = COM_getUserDateTimeFormat($A['expired_un']);
            }
            $curdate_ary =  COM_getUserDateTimeFormat();

            $value=COM_applyFilter($A['value']);
            $group_id = $A['group_id'];
            $owner_id = $A['owner_id'];
            $fieldset_id=COM_applyFilter($A['fieldset_id']);

            $url=$_CONF['site_url'] . "/userbox/profile.php";
            $url.="?";
            //コード使用の時
            if ($_USERBOX_CONF['datacode']){
                $url.="code=".$A['username'];
                $url.="&amp;m=code";
            }else{
                $url.="id=".$A['id'];
                $url.="&amp;m=id";
            }
            $url = COM_buildUrl( $url );
            $link= COM_createLink($username, $url);


            $templates->set_var ('data_link', $link);

            $templates->set_var ('data_fullname', $A['fullname']);
            $templates->set_var ('data_title', $A['fullname']);

            $templates->set_var ('data_username', $username);
            $templates->set_var ('data_code', $username);

            $templates->set_var ('data_description', $description);
            $templates->set_var ('data_id', $id);
            $templates->set_var ('data_url', $url);
            $templates->set_var ('data_datefield', $datefield_ary[0]);
            $templates->set_var ('data_value', $value);
            $templates->set_var ('data_datefield_shortdate', strftime( $_CONF['shortdate'], $A['datefield_un'] ));
            $templates->set_var ('data_released', $released_ary[0]);
            $templates->set_var ('data_released_shortdate', strftime( $_CONF['shortdate'], $A['released_un'] ));
            //公開終了日 Expired to publish
            if ($A['expired'] ==="0000-00-00 00:00:00"){
                $templates->set_var ('data_expired', "");
                $templates->set_var ('data_expired_shortdate', "" );
            }else{
                $wary = COM_getUserDateTimeFormat($A['expired_un']);
                $templates->set_var ('data_expired', $expired_ary[0]);
                $templates->set_var ('data_expired_shortdate', strftime( $_CONF['shortdate'], $A['expired_un'] ));
            }
            $remaingdays="";
            if ($expired<>"0000-00-00 00:00:00") {
                if  ($expired_ary[1]>=$curdate_ary[1]){
                    $remaingdays=COM_dateDiff( "d", $expired_ary[1], $curdate_ary[1] ) + 1;
                }
            }
            $templates->set_var ('data_remaingdays', $remaingdays);//@@@@@@
            if (date("Ymd",strtotime($datefield)) >= date("Ymd",$chkday)){
                $templates->set_var ('new_img', $new_img);
            }else{
                $templates->set_var ('new_img', '');
            }
            
            //
            if (isset($A['photo']) && empty($A['photo'])) {
                $A['photo'] = '(none)'; // user does not have a photo
                $templates->set_var('data_photo', "");
            }else{
                $templates->set_var('data_photo', $A['photo']);
            }
            $photo = USER_getPhoto($uid, $A['photo'], $A['email'], -1);
            $templates->set_var('data_user_photo', $photo);

            $hits=COM_applyFilter(DB_getItem( $tbl6 ,"hits","id={$id}"),true);
            $templates->set_var('lang_hits', $LANG_USERBOX_ADMIN['hits']);
            $templates->set_var('data_hits', $hits);
            
            //カテゴリ@@@@@
            $templates->set_var('lang_category', $LANG_USERBOX_ADMIN['category']);
            DATABOX_getcategoriesDisp($A['id'],$templates,$chk_user,0,$pi_name);

            //追加項目
            $chk_user=DATABOX_chkuser($group_id,$owner_id,"userbox.admin");
            $additionfields = DATABOX_getadditiondatas($id,$pi_name);
            DATABOX_getaddtionfieldsDisp($additionfields,$addition_def,$templates,$chk_user,$pi_name,$fieldset_id);
            
            //管理者の時「編集」
            $templates->set_var ('data_edit', "");
            if ( SEC_hasRights('userbox.admin')) {
                $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                $attr = array('title' => $username." ".$LANG_USERBOX_ADMIN['edit']);
                $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);
                $attr = array('class' => 'editlink', 'title' => $username." ".$LANG_USERBOX_ADMIN['edit']);
                $url = $_CONF['site_admin_url'];
                $url .= '/plugins/userbox/profile.php';
                $url .= '?mode=edit';
                $url .= '&amp;'."id={$id}";
                $icon = '&nbsp;' ;
                $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );
                $templates->set_var ('data_edit', $icon);
            }else{
                if ($id===$_USER['uid']){
                    $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                    $attr = array('title' => $LANG_USERBOX_ADMIN['edit']);
                    $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);
                    $attr = array('class' => 'editlink', 'title' => $username." ".$LANG_USERBOX_ADMIN['edit']);
                    $url = $_CONF['site_url'];
                    $url .= '/userbox/myprofile/profile.php';
                    $icon = '&nbsp;' ;
                    $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );
                    $templates->set_var ('data_edit', $icon);
                }
            }
            
            //=====
            $templates->parse ('col_var', 'col', true);
            $templates->parse ('row_var', 'row', true);

            $templates->set_var ('col_var', '');

        }
        // Call to plugins to set template variables in the databox
        PLG_templateSetVars( 'userbox', $templates );

        //------------
        //-----navigation
        $url = $_CONF['site_url']  . '/';
        if ($autotag==="notautotag"){
            $url.=THIS_SCRIPT;
        }else{
            $url.="userbox/category.php";
        }
        $url .= "?";
        if ($category_code<>""){
            $url .= "code=".$category_code;
            $url .= "&amp;m=code";
        }else{
            $url .= "id=".$category_id;
            $url .= "&amp;m=id";
        }
        $url .= "&amp;template=".$template;
        $url .= "&amp;order=";
        //-----order navigation
        userbox_order ($url, $templates, $order) ;
        //-----page navigation
        $url .= $order;
        $templates->set_var ('page_navigation',
                COM_printPageNavigation ($url, $page, $pages));
        if ($order==="random"){
            $templates->set_var ( 'pagenavinone', 'style="display:none;"' );
        }else{
            $templates->set_var ( 'pagenavinone', '' );
        }
        //------------
        $templates->parse ('nav_var', 'nav', true);

        $templates->set_var ('blockfooter',COM_endBlock());

        $templates->set_var ('msg', "");
        //@@@@@@@@$templates->set_var ('search_link', $LANG_USERBOX['search_link']);
        //@@@@@@@@$templates->set_var ('search_var',"");

        $templates->parse ('output', 'list');

        $content = $templates->finish ($templates->get_var ('output'));
        $retval .=$content;

    }else{
        if ($nohitmsg==="yes"){
            $tmplfld=DATABOX_templatePath('category',$template,$pi_name);
            $templates = new Template($tmplfld);
            $templates->set_file (array (
                'list' => 'nohit.thtml',
            ));
            
            $templates->set_var ('home',$LANG_USERBOX['home']);
            
            $url=$_CONF['site_url']."/userbox/category.php";
            $category_top="<a href='".$url."'>".$LANG_USERBOX['category_top']."</a>";
            $templates->set_var ('category_top',$category_top);

            $templates->set_var ('category_name',$category_name);
            $templates->set_var ('category_code',$category_code);
            $templates->set_var ('category_description',$category_description);
                        
            $templates->set_var('xhtml', XHTML);
            $templates->set_var('site_url', $_CONF['site_url']);
            $templates->set_var('site_admin_url', $_CONF['site_admin_url']);
            $templates->set_var('layout_url', $_CONF['layout_url']);
            
            $templates->set_var ('lang_nohit', $LANG_USERBOX['nohit']);

            $templates->parse ('output', 'list');
            $content = $templates->finish ($templates->get_var ('output'));
            $retval .=$content;
        }
    }

    $retval =PLG_replacetags ($retval);

    //return $dbg.$retval;
    return $retval;
}





function userbox_count(
    $category_id =null
    ,$category_code =null
    ,$mode=''
    ,$permission=""
    ,$numberformat=''
)
// +---------------------------------------------------------------------------+
// | 機能  件数表示
// +---------------------------------------------------------------------------+
// | 書式  userbox_count()
// | 書式  userbox_count(1)
// | 書式  userbox_count("japan")
// +---------------------------------------------------------------------------+
// | 引数 $category_id:カテゴリid　（優先）
// | 引数 $category_code:カテゴリコード
// | 引数 $mode          :ブランク　または　AND
// +---------------------------------------------------------------------------+
// | 戻値 nomal:データ総数、カテゴリ別データ件数
// +---------------------------------------------------------------------------+
{
    global $_TABLES;

    //-----
    $tbl1=$_TABLES['USERBOX_base'] ;
    $tbl2=$_TABLES['USERBOX_category'] ;
    
    //カテゴリの指定がなければ全件、あればカテゴリ毎
    $exp="";
    if (is_null($category_id) AND is_null($category_code) ){
    }else{
        $ids="";
        $idsary=array();
        if (is_null($category_id) OR $category_id==0){
            if (is_null($category_code)){
            }else{
                $w=explode("|",$category_code);
                foreach( $w as $val ){
                    if ($ids<>""){
                        $ids.=",";
                    }
                    $w_id=DATABOX_codetoid($val,'USERBOX_def_category',"category_id");
                    $idsary[]=$w_id;
                    $ids.=$w_id;
                }
            }
        }else{
            $ids=$category_id;
            $idsary[]=$category_id;
        }
        if (strtoupper($mode)=="AND"){
            $w="";
            foreach( $idsary as $val ){
                if ($w<>""){
                    $w.=" AND ";
                }
                $w.=$val." IN (SELECT category_id";
                $w.=" FROM {$tbl2} AS t2 ";
                $w.=" WHERE t2.id = t1.id)";
            }
        }else{
            $w= "id IN (SELECT id FROM {$tbl2} ";
            $w.="WHERE category_id IN ({$ids})) ";
        }
        $exp=$w.LB;
    }

    //-----
    $sql = "SELECT ".LB;

    $sql .= " distinct t1.id ".LB;

    $sql .= " FROM ".LB;

    $sql .= " {$tbl1} AS t1 ".LB;


    $sql .= " WHERE ".LB;
    
    if ($exp<>""){
        $sql .= $exp. " AND ";
    }
    
    //@@@@@@@@@@@------>
    //下書はのぞく
    $sql .= " t1.draft_flag=0".LB;
    //アクセス権のないデータ はのぞく
    if  (strtoupper($permission)=="IGNORE"){
    }else{
        $sql .= COM_getPermSql('AND').LB;
    }
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;
    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())".LB;
    //@@@@@@@@@@@<------

    $result = DB_query ($sql);
    $cnt = DB_numRows ($result);
    if  (strtoupper($numberformat)=="YES"){
        $rt=COM_NumberFormat($cnt);
    }else{
        $rt=$cnt;
    }

    return $rt;

}


function userbox_grp(
    $field=null
    ,$function=null
    ,$format=null
    ,$category_id =null
    ,$category_code =null
)
// +---------------------------------------------------------------------------+
// | 機能  集計表示(max min sum)
// +---------------------------------------------------------------------------+
// | 書式  userbox_grp()
// | 書式  userbox_grp(1)
// | 書式  userbox_grp("japan")
// +---------------------------------------------------------------------------+
// | 引数 $field:項目　デフォルトmodified
// | 引数 $function:関数　デフォルトmax
// | 引数 $format:書式
// | 引数 $category_id:カテゴリid　（優先）
// | 引数 $category_code:カテゴリコード
// +---------------------------------------------------------------------------+
// | 戻値 nomal:データ総数、カテゴリ別データ件数
// +---------------------------------------------------------------------------+
{
    global $_TABLEdS;
    global $LANG_USERBOX;

    $rt="";

    if (is_null($field) ){
        $field="id";
    }
    if (is_null($function) ){
        $function="max";
    }

    if (is_null($category_id) AND is_null($category_code) ){
        $m="ALL";
    }else{
        if (is_null($category_id) ){
            $category_id=DATABOX_codetoid($category_code,'USERBOX_def_category',"category_id");
        }
        $m="CATEGORY";
    }
    //-----
    $tbl1=$_TABLES['USERBOX_base'] ;
    $tbl2=$_TABLES['USERBOX_category'] ;

    //-----
    $sql=LB;
    $sql .= "SELECT ".LB;

    $sql .= " {$function}(t1.{$field}) AS rt".LB;

    $sql .= " FROM ".LB;

    $sql .= " {$tbl1} AS t1 ".LB;

    if ($m==="ALL"){
    }else{
        $sql .= " ,{$tbl2} AS t2 ".LB;
    }

    $sql .= " WHERE ".LB;

    //@@@@@@@@@--------->
    //下書はのぞく
    $sql .= " t1.draft_flag=0".LB;
    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND').LB;
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;
    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())".LB;
    //@@@@@@@@@<---------

    if ($m==="ALL"){
    }else{
        $sql .= " AND t1.id = t2.id ".LB;
        $sql .= " AND t2.category_id= ".$category_id .LB;
    }

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    if ($numrows > 0) {

        $A = DB_fetchArray ($result);

        $rt=$A['rt'];

        if ($rt<>""){
            switch($format){
                case "lastmodified":// '%Y年%m%e日更新';
                case "lastcreated"://'%Y年%m%e日追加';
                    $fm=$LANG_USERBOX[$format];
                    $rt=strftime($fm,strtotime($rt));
                    break;
                default:
                    break;
            }
        }

    }

    return $rt;

}

function userbox_profile(
    $id=null
    ,$template=null
    ,$nohitmsg=""
    ,$m=""
    ,$code=null
)
// +---------------------------------------------------------------------------+
// | 機能  個別データ表示
// | 書式  userbox_profile($id,$template,"yes","page")
// | 書式  userbox_profile($p['id'],$p['thtml'],$p['nohitmsg'],$p['code']
// +---------------------------------------------------------------------------+
// | 引数 $id            :
// | 引数 $template      :テンプレートのdirectory
// | 引数 $nohitmsg      :"yes"の時nohitメッセージ表示
// | 引数 $m             :"page"の時ヘッダ表示
// | 引数 $code          :
// +---------------------------------------------------------------------------+
// | public_html/profile.php および　自動タグで使用
// | ヘッダはコンフィギュレーション設定により表示されない場合があります
// | idもcodeも指定されない場合は、直近に変更または追加されたデータを返します
// +---------------------------------------------------------------------------+
// | 戻値 nomal:profile
// +---------------------------------------------------------------------------+
// 20120125
{
    $pi_name="userbox";

    global $_CONF;
    global $_TABLES;
    global $_IMAGE_TYPE;
    global $_USER;

    global $LANG28;
    global $LANG04;
    
    global $_USERBOX_CONF;
    global $LANG_USERBOX;
    global $LANG_USERBOX_ADMIN;
    
    global $LANG_confignames;
    
    $layout=$_DATABOX_CONF['layout'];
    //-----
    if (is_null($id) AND (is_null($code))){
        $id=$_USER['uid'];
    }

    if (is_null($id)  OR ($id==0)){
        if ($code<>""){
            $id=DATABOX_codetoid($code,'users',"uid","username");
        }
    }

    //-----
    $display = '';


    $tbl=$_TABLES['USERBOX_base'] ;
    $tbl1=$_TABLES['users'] ;
    $tbl4=$_TABLES['USERBOX_def_fieldset'];
    $tbl5=$_TABLES['USERBOX_stats'];

    $addition_def=DATABOX_getadditiondef($pi_name);

    //-----
    $sql = "SELECT t.* ";
    
    $sql .= " ,UNIX_TIMESTAMP(t.modified) AS modified_un";
    $sql .= " ,UNIX_TIMESTAMP(t.released) AS released_un";
    $sql .= " ,UNIX_TIMESTAMP(t.comment_expire) AS comment_expire_un";
    $sql .= " ,UNIX_TIMESTAMP(t.expired) AS expired_un";
    $sql .= " ,UNIX_TIMESTAMP(t.created) AS created_un";
    $sql .= " ,UNIX_TIMESTAMP(t.udatetime) AS udatetime_un";
    
    $sql .= " ,t.fieldset_id";

    $sql .= " ,t1.username ";
    $sql .= " ,t1.fullname ";
    $sql .= " ,t1.photo ";
    $sql .= " ,t1.email";
    $sql .= " ,t3.username AS last_modify_name";
    $sql .= " ,t3.fullname AS last_modify_fullname";
    
    $sql .= " ,t4.name AS set_name";
    $sql .= " ,t4.description AS set_description";
    $sql .= " ,t4.defaulttemplatesdirectory AS set_defaulttemplatesdirectory";
    $sql .= " ,t4.layout AS set_layout";
    
    $sql .= " ,t5.hits";

    $sql .= " FROM ";
    $sql .= " {$tbl} AS t ";
    $sql .= " JOIN {$tbl4} AS t4       ON t.fieldset_id=t4.fieldset_id";
    $sql .= " JOIN {$tbl1} AS t1       ON t.id=t1.uid";//owner
    $sql .= " LEFT JOIN  {$tbl5} AS t5 ON t.id=t5.id";
    $sql .= " LEFT JOIN  {$tbl1} AS t3 ON t.uuid=t3.uid";//last_updateowner

    $sql .= " WHERE ";


    if (is_null($id)){//@@@@@
        $sql .= "1=1";
    }else{
        $sql .= " t.id=".$id;
    }

    //管理者の時,
    if ( SEC_hasRights('userbox.admin') OR $m==="view") {
    }else{
        //下書データはのぞく
       $sql .= " AND t.draft_flag=0".LB;
        //公開日以前のデータはのぞく
        $sql .= " AND (released <= NOW())";

        //公開終了日を過ぎたデータはのぞく
        $sql .= " AND (expired=0 OR expired > NOW())";
    }
    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND');

    if (is_null($id)){
        $sql .= " ORDER BY ".$_USERBOX_CONF['datefield'] . " DESC ";
        $sql .= " LIMIT 0 , 1";
    }

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);
    
    if  ($m==="page"  OR  $m==="view"){
        if ($numrows <= 0) {
            COM_handle404();
            exit;
        }
    }

    if ($numrows > 0) {

        $A = DB_fetchArray ($result);
        $A = array_map('stripslashes', $A);
		
		$cache_time=$A['cache_time'];
        $cache=false;
		if  ($cache_time===0){
		}else{
		    if  ($m==="page"){
                if ($cache_time > -1 ) {
                    // Don't need to cache per theme since not rendered in a block yet 
                    $cacheInstance = 'userbox__' . $id . '__' . CACHE_security_hash();
                    $retval = CACHE_check_instance($cacheInstance);
                    if ($retval AND $cache_time == -1) {
                        return $retval;
                    } elseif ($retval AND $cache_time > 0) {        
                        $lu = CACHE_get_instance_update($cacheInstance);
                        $now = time();
                        if (($now - $lu) < $cache_time) {
                            return $retval;
                        } else {
                            $retval = '';
                        }
                   }    
                }
            }
	    }
        
        if  ($cache){
            $display = CACHE_check_instance($cacheInstance."_display",$display);
            $layout = CACHE_check_instance($cacheInstance."_layout",$layout);
            $headercode = CACHE_check_instance($cacheInstance."_headercode",$headercode);
            $title = CACHE_check_instance($cacheInstance."_title",$title);
        }else{
			
            if (is_null($template) or ($template==="")){
                if ($A['defaulttemplatesdirectory']<>""){
                    $template=$A['defaulttemplatesdirectory'];
                }elseif  ($A['set_defaulttemplatesdirectory']<>""){
                    $template=$A['set_defaulttemplatesdirectory'];
                }else{
                    $template="default";
                }
           }


            $group_id = $A['group_id'];
            $owner_id = $A['owner_id'];
            $fieldset_id= $A['fieldset_id'];
            if ($m==="view"){
                $chk_user=1000;//ログインユーザ
            }else{
                $chk_user=DATABOX_chkuser($group_id,$owner_id,"userbox.admin");
            }
            //
            $id=$A['id'];
            $additionfields = DATABOX_getadditiondatas($id,$pi_name);

            //ヘッダ、左ブロック start.............
            if ($m==="page"){
                if  ($A['set_layout']<>"0") {
                    $layout=$A['set_layout'];
                }
                $title= $A['page_title'];
                if ($title===""){
                    $title =$A['username'];
                }
                // Meta Tags
                $headercode=DATABOX_getheadercode(    
                    "profile"
                    ,$template
                    ,$pi_name
                    ,$A['id']
                    ,$title
                    ,$A['meta_description']
                    ,$A['meta_keywords']
                    ,$A['description']);
            }

            $tmplfld=DATABOX_templatePath('profile',$template,$pi_name);
            $templates = new Template($tmplfld);
            $templates->set_file (array (
                'list' => 'list_detail.thtml',
                'row'   => 'row.thtml',
                'col'   => "col_detail.thtml",
                ));
            $languageid=COM_getLanguageId();
            $language= COM_getLanguage();
            $templates->set_var ('languageid', $languageid);
            $templates->set_var ('language', $language);
            if ($languageid<>"") {
                $templates->set_var ('_languageid', "_".$languageid);
            }else{
                $templates->set_var ('_languageid', "");
            }

            $templates->set_var ('site_admin_url', $_CONF['site_admin_url']);
            $templates->set_var ('site_url',$_CONF['site_url']);
        
            $templates->set_var('xhtml', XHTML);
            $templates->set_var('layout_url', $_CONF['layout_url']);
        
            $templates->set_var ('site_name', $_CONF['site_name']);
            $templates->set_var ('site_mail', $_CONF['site_mail']);
        
            $currenturl= COM_getCurrentURL();
            $templates->set_var ('currenturl', $currenturl);
            //facebook
            $facebook_consumer_key = trim($_CONF['facebook_consumer_key']);
            $templates->set_var ('facebook_consumer_key', $facebook_consumer_key);
            //

            //daft_flag管理者の時
            if ($A['draft_flag']==1){
                $templates->set_var ('draft', "draft!");
            }else{
                $templates->set_var ('draft', "");
            }
            //------------

            //id
            $templates->set_var('lang_id', $LANG_USERBOX_ADMIN['id']);
            $templates->set_var ('id', $A['id']);

            $templates->set_var('lang_fields', $LANG_USERBOX_ADMIN['fields']);
            $templates->set_var('lang_field', $LANG_USERBOX_ADMIN['field']);
            $templates->set_var('lang_templatesetvar', $LANG_USERBOX_ADMIN['templatesetvar']);
            $templates->set_var('lang_content', $LANG_USERBOX_ADMIN['content']);
            $templates->set_var('lang_type', $LANG_USERBOX_ADMIN['type']);
            $templates->set_var('lang_byusingid', $LANG_USERBOX_ADMIN['byusingid']);
            $templates->set_var('lang_byusingcode', $LANG_USERBOX_ADMIN['byusingcode']);
            $templates->set_var('lang_byusingtemplatesetvar', $LANG_USERBOX_ADMIN['byusingtemplatesetvar']);
            $templates->set_var('lang_group', $LANG_USERBOX_ADMIN['group']);
            $templates->set_var('lang_withlink', $LANG_USERBOX_ADMIN['withlink']);
            $templates->set_var('lang_groupbygroup', $LANG_USERBOX_ADMIN['groupbygroup']);

            $templates->set_var('lang_uid', $LANG28['2']);
            $templates->set_var('lang_username', $LANG28['3']);
            $templates->set_var ('username', $A['username']);
            $templates->set_var('lang_fullname', $LANG28['4']);
            $templates->set_var ('fullname', $A['fullname']);
        
            $templates->set_var('lang_photo', $LANG04['77']);
            if (isset($A['photo']) && empty($A['photo'])) {
                $A['photo'] = '(none)'; // user does not have a photo
                $templates->set_var('photo', "");
            }else{
                $templates->set_var('photo', $A['photo']);
            }
            //$photo = USER_getPhoto($A['id'], $A['photo'], $A['email'], -1);
            //$templates->set_var('user_photo', $photo);
        
        
            //$_USERBOX_CONF
            $templates->set_var('lang_imgfile_frd', $LANG_confignames['userbox']['imgfile_frd']);
            $templates->set_var ('imgfile_frd', $_USERBOX_CONF['imgfile_frd']);
            $templates->set_var ('data_img_url', $_CONF['site_url']."/".$_USERBOX_CONF['imgfile_frd']);
            $templates->set_var('lang_imgfile_thumb_frd', $LANG_confignames['userbox']['imgfile_thumb_frd']);
            $templates->set_var ('imgfile_thumb_frd', $_USERBOX_CONF['imgfile_thumb_frd']);
            $templates->set_var ('data_thumb_img_url', $_CONF['site_url']."/".$_USERBOX_CONF['imgfile_thumb_frd']);

            //基本項目
            $templates->set_var('lang_basicfields', $LANG_USERBOX_ADMIN['basicfields']);
            //コード＆タイトル＆説明
            $templates->set_var('lang_code', $LANG_USERBOX_ADMIN['code']);
            $templates->set_var ('code', $A['username']);
            $templates->set_var('lang_title', $LANG_USERBOX_ADMIN['title']);
            $templates->set_var ('title',$A['fullname']);

            $templates->set_var('lang_description', $LANG_USERBOX_ADMIN['description']);
            $templates->set_var ('description',$A['description']);
        
            $templates->set_var('lang_draft', $LANG_USERBOX_ADMIN['draft']);
            if ($A['draft_flag']){
                $templates->set_var ('draft',$LANG_USERBOX_ADMIN['draft']);
            }else{
                $templates->set_var ('draft',"");
            }
            //hits
            $templates->set_var('lang_hits', $LANG_USERBOX_ADMIN['hits']);
            $templates->set_var ('hits', $A['hits']);
            //カテゴリ@@@@@
            $templates->set_var('lang_category', $LANG_USERBOX_ADMIN['category']);
            DATABOX_getcategoriesDisp($A['id'],$templates,$chk_user,0,$pi_name);

            //追加項目
            $templates->set_var('lang_additionfields', $LANG_USERBOX_ADMIN['additionfields']);
            DATABOX_getaddtionfieldsDisp($additionfields,$addition_def,$templates,$chk_user,$pi_name,$fieldset_id);

            //編集日付
            $templates->set_var('lang_modified', $LANG_USERBOX_ADMIN['modified']);
            $wary = COM_getUserDateTimeFormat($A['modified_un']);
            $templates->set_var ('modified',$wary[0]);
            $templates->set_var ('modified_shortdate', strftime( $_CONF['shortdate'], $A['modified_un'] ));
            //作成日付
            $templates->set_var('lang_created', $LANG_USERBOX_ADMIN['created']);
            $wary = COM_getUserDateTimeFormat($A['created_un']);
            $templates->set_var ('created', $wary[0]);
            $templates->set_var ('created_shortdate', strftime( $_CONF['shortdate'], $A['created_un'] ));
            //公開日
            $templates->set_var('lang_released', $LANG_USERBOX_ADMIN['released']);
            $wary = COM_getUserDateTimeFormat($A['released_un']);
            $templates->set_var ('released', $wary[0]);
            $templates->set_var ('released_shortdate', strftime( $_CONF['shortdate'], $A['released_un'] ));
            //公開終了日
            $templates->set_var('lang_expired', $LANG_USERBOX_ADMIN['expired']);
            if ($A['expired'] ==="0000-00-00 00:00:00"){
                $templates->set_var ('expired', "");
                $templates->set_var ('expired_shortdate', "" );
            }else{
                $wary = COM_getUserDateTimeFormat($A['expired_un']);
                $templates->set_var ('expired', $wary[0]);
                $templates->set_var ('expired_shortdate', strftime( $_CONF['shortdate'], $A['expired_un'] ));
            }

            $templates->set_var ('lang_uuid', $LANG_USERBOX_ADMIN['uuid']);
            $templates->set_var ('uuid', $uuid);

            $templates->set_var ('lang_udatetime', $LANG_USERBOX_ADMIN['udatetime']);
            $wary = COM_getUserDateTimeFormat($A['udatetime_un']);
            $templates->set_var ('udatetime', $wary[0]);
            $templates->set_var ('last_modify_uid', $A['uuid']);
            $templates->set_var ('last_modify_name', $A['last_modify_name']);
            $templates->set_var ('last_modify_fullname', $A['last_modify_fullname']);

            //管理者の時「編集」
            $templates->set_var ('edit', "");

            if ($m<>"view"){

                if ( SEC_hasRights('userbox.admin')) {
                    $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                    $attr = array('title' => $A['username']." ".$LANG_USERBOX_ADMIN['edit']);
                    $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);
                    $attr = array('class' => 'editlink', 'title' => $A['username']." ".   $LANG_USERBOX_ADMIN['edit']);
                    $url = $_CONF['site_admin_url'];
                    $url .= '/plugins/userbox/profile.php';
                    $url .= '?mode=edit';
                    $url .= '&amp;'."id={$id}";
                    $icon = '&nbsp;' ;
                    $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );
                    $templates->set_var ('edit', $icon);
                }else{
                    if ($id===$_USER['uid']){
                        $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                        $attr = array('title' => $LANG_USERBOX_ADMIN['edit']);
                        $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);
                        $attr = array('class' => 'editlink', 'title' => $A['username']." ".$LANG_USERBOX_ADMIN['edit']);
                        $url = $_CONF['site_url'];
                        $url .= '/userbox/myprofile/profile.php';
                        $icon = '&nbsp;' ;
                        $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );
                        $templates->set_var ('edit', $icon);
                    }
                }
            }
            // Call to plugins to set template variables in the databox
            PLG_templateSetVars( 'userbox', $templates );

            //------------
            $templates->parse ('output', 'list');

            $userbox_content = $templates->finish ($templates->get_var ('output'));
            $display=$userbox_content;
			
            if ($A['draft_flag']!==1){
                if (!SEC_hasRights('userbox.admin')) {
                    $id=$A['id'];
                    $hits=DB_getItem($_TABLES['USERBOX_stats'],"hits","id={$id}");
                    if (empty($hits)) {
                        $hits=0;
                    }
                    $fields.="id,hits ";
                    $values.="$id,$hits + 1";
                    DB_save($_TABLES['USERBOX_stats'],$fields,$values);
                }
            }
        }
    }else{
        $cache_time=0;
        if ($nohitmsg==="yes"){
            $tmplfld=DATABOX_templatePath('profile',$template,$pi_name);
            $templates = new Template($tmplfld);
            $templates->set_file (array (
                'list' => 'nohit.thtml',
            ));
            $templates->set_var ('lang_nohit', $LANG_USERBOX['nohit']);
            $templates->set_var('xhtml', XHTML);
            $templates->set_var('site_url', $_CONF['site_url']);
            $templates->set_var('site_admin_url', $_CONF['site_admin_url']);
            $templates->set_var('layout_url', $_CONF['layout_url']);

            $templates->parse ('output', 'list');
            $databox_content = $templates->finish ($templates->get_var ('output'));
            $display =$databox_content;
            
        }
    }
    $display =PLG_replacetags ($display);
		
    if  (($cache_time > 0 OR $cache_time == -1)  AND  $m==="page" ){
        CACHE_create_instance($cacheInstance."_display", $display); 
        CACHE_create_instance($cacheInstance."_layout", $layout); 
        CACHE_create_instance($cacheInstance."_headercode", $headercode); 
        CACHE_create_instance($cacheInstance."_title", $title); 
    }

    $retval['display']=$display;
    $retval['layout']=$layout;
    $retval['headercode']=$headercode;
    $retval['title']=$title;


    return $retval;
}



function userbox_field(
    $autotag
    ,$field_id=null
    ,$value=""
    ,$template=null
    ,$nohitmsg=""
    ,$perpage =0
    ,$page=null
    ,$order=null
    ,$field_code=null
)
// +---------------------------------------------------------------------------+
// | 機能  アトリビュート別一覧表示
// | 書式
// +---------------------------------------------------------------------------+
// | 引数 $autotag:notautotagの時ヘッダを表示しない
// | 引数 $field_id:追加属性id
// | 引数 $value:表示する項目の値
// | 引数 $template:使用するテンプレートのフォルダ名
// | 引数 $nohitmsg      :"yes"の時nohitメッセージ表示
// | 引数 $perpage       :1pageあたりの件数、0の時改ページなし
// | 引数 $page           :
// | 引数 $order         :順序
// | 引数 $field_code    :
// +---------------------------------------------------------------------------+
// | public_html/attribute.php および　自動タグで使用
// | ヘッダはコンフィギュレーション設定により表示されない場合があります
// | idもcodeも指定されない場合は、全カテゴリ
// +---------------------------------------------------------------------------+
// | 戻値 nomal:category list
// +---------------------------------------------------------------------------+
{
    $pi_name="userbox";

    global $_CONF;
    global $_TABLES;
    global $_USERBOX_CONF;
    global $LANG_USERBOX;
    global $LANG_USERBOX_ADMIN;
    global $LANG_USERBOX_NOYES;
    global $_IMAGE_TYPE;
    global $LANG_confignames;

    //ログイン要否チェック
    if (COM_isAnonUser()){
        if  ($_CONF['loginrequired']
                OR ($_USERBOX_CONF['loginrequired'] >1) ){
            return $LANG_USERBOX['loginrequired'];
        }

    }
    //-----table assign
    $tbl1=$_TABLES['USERBOX_addition'] ;
    $tbl2=$_TABLES['USERBOX_base'] ;
    $tbl3=$_TABLES['USERBOX_addition'] ;
    $tbl4=$_TABLES['users'] ;
    $tbl5=$_TABLES['USERBOX_stats'];
    
    //日付関連
    $datefield=$_USERBOX_CONF['datefield'];//使用する日付（編集日付、作成日付）
    $new_img=$_USERBOX_CONF['new_img'];
    if ($new_img==""){
        $new_img="New!";
    }
    $newmarkday=$_USERBOX_CONF['newmarkday'];
    if ($newmarkday==""){
        $newmarkday=3;
    }
    $chkday=strtotime("- $newmarkday days",time());

    //-----引数チェック
    if (is_null($value) OR $value==="") {
        return;
    }
    if (is_null($field_id)){
        if ($field_code===""){
            $field_id=0;
        }else{
            $field_id=DATABOX_codetoid($field_code,'USERBOX_def_field',"field_id","templatesetvar");

        }
    }
    if ($field_id===0) {
        return;
    }
    
    if ($field_code===""){
        $field_code=DB_getItem( $_TABLES['USERBOX_def_field']
                 ,"templatesetvar","field_id={$field_id}");
    }
    $field_def=DATABOX_getadditiondef($pi_name);
    $type=$field_def[$field_id]['type'];
    $w = array(0,2,3,4,7,8,9,16);
    if (in_array ($type,$w)){
    }else{
        return;
    }

    if (is_null($page) OR !isset($page) OR $page == 0) {
        $page = 1;
    }

    if ($perpage===0 OR is_null($perpage)){
        $perpage=$_USERBOX_CONF['perpage'];
    }


    $w=userbox_orderby($datefield,$order,$orderby,$addfieldorder,$orderfield_id);
    $field_def[$field_id]['name'];
    $field_name=$field_def[$field_id]['name'];
    $field_templatesetvar=$field_def[$field_id]['templatesetvar'];
    $field_description=$field_def[$field_id]['description'];

    $fieldvalue=DATABOX_getfieldvalue(
          $value
         ,$field_def[$field_id]['type']
         ,$field_def[$field_id]['selectionary']
         ,$LANG_USERBOX_NOYES
         ,$field_def[$field_id]['selectlist']
         ,$pi_name
          );


    if (is_null($template) or ($template==="")){
        $template="default";
    }


    //-----
    $sql = "SELECT ";

    $sql .= " t2.id ".LB;
    $sql .= " ,t2.description ".LB;
    $sql .= " ,t2.released ".LB;
    $sql .= " ,t2.".$datefield." AS datefield ".LB;
    $sql .= " ,t2.expired ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.".$datefield.") AS datefield_un ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.released ) AS released_un ".LB;
    $sql .= " ,UNIX_TIMESTAMP(t2.expired ) AS expired_un ".LB;

    $sql .= " ,t2.group_id";
    $sql .= " ,t2.owner_id";
    $sql .= " ,t2.fieldset_id ".LB;

    $sql .= " ,t4.username";
    $sql .= " ,t4.fullname";
    $sql .= " ,t4.photo";
    $sql .= " ,t4.email";
    
    if ($addfieldorder){
        $sql .= " ,t3.value ".LB;
    }


    $sql .= " FROM ".LB;
    $sql .= " {$tbl2} AS t2 ".LB;
    $sql .= " ,{$tbl1} AS t1 ".LB;
    $sql .= " ,{$tbl4} AS t4 ".LB;
    if ($addfieldorder){
        $sql .= " ,{$tbl3} AS t3 ".LB;
    }

    $sql .= " WHERE ".LB;
    $sql .= " t1.field_id = ".$field_id.LB;
    $sql .= " AND t1.id = t2.id ".LB;
    $sql .= " AND t1.value = \"".$value."\"".LB;
    $sql .= " AND t2.id=t4.uid ".LB;
    if ($addfieldorder){
        $sql .= " AND t3.field_id=".$orderfield_id.LB;
        $sql .= " AND t3.id=t2.id".LB;
    }
    //管理者の時,下書データも含む
    //if ( SEC_hasRights('userbox.admin')) {
    //}else{
       $sql .= " AND t2.draft_flag=0".LB;
    //}

    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND',0,2,"t2");

    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;
    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())";
    
    $sql .= " ORDER BY ".LB;
    $sql .= $orderby.LB;

    $result = DB_query ($sql);
    $cnt = DB_numRows ($result);

    $pages = 0;
    if ($perpage > 0) {
        $pages = ceil($cnt / $perpage);
    }
    $offset = ($page - 1) * $perpage;
    $sql .= " LIMIT $offset, $perpage";
    
    //ヘッダ、左ブロック
    if ($autotag==="notautotag"){
        if ($page > 1) {
            $page_title = sprintf ('%s (%d)', $field_name, $page);
        } else {
            $page_title = sprintf ('%s ', $field_name);
        }
        $headercode=DATABOX_getheadercode(    
            "attribute"
            ,$template
            ,$pi_name
            ,$field_id
            ,$field_name
            ,$field_description
            ,$field_name
            ,$field_description);
        $retval .= DATABOX_siteHeader($pi_name,'',$page_title,$headercode);
    }
    
    $addition_def=DATABOX_getadditiondef($pi_name);
    //
    $url=$_CONF['site_url']."/userbox/attribute.php";
    $attribute_top="<a href='".$url."'>".$LANG_USERBOX['attribute_top']."</a>";
    $url=$_CONF['site_url']."/userbox/attribute.php";
    $url.="?";
    $url.="id=".$field_id;
    $url.="&amp;m=id";
    $field_top="<a href='".$url."'>".$field_name.$LANG_USERBOX['countlist']."</a>";

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    if ($numrows > 0) {
        $tmplfld=DATABOX_templatePath('attribute',$template,$pi_name);
        $templates = new Template($tmplfld);
        $templates->set_file (array (
            'list' => 'list_detail.thtml',
            'nav'   => 'navigation_detail.thtml',
            'row'   => 'row.thtml',
            'col'   => "col_detail.thtml",
            'pagenav'  => 'pagenavigation.thtml'
            ));
        
        $languageid=COM_getLanguageId();
        $language= COM_getLanguage();
        $templates->set_var ('languageid', $languageid);
        $templates->set_var ('language', $language);
        if ($languageid<>"") {
            $templates->set_var ('_languageid', "_".$languageid);
        }else{
            $templates->set_var ('_languageid', "");
        }
        
        //
        $templates->set_var ('site_url',$_CONF['site_url']);
        $templates->set_var ('this_script',THIS_SCRIPT);

        //bread
        $templates->set_var ('home',$LANG_USERBOX['home']);
        $templates->set_var ('attribute_top',$addition_top);
        $templates->set_var ('field_top',$field_top);
        
        $templates->set_var ('field_name',$field_name);
        $templates->set_var ('field_templatesetvar',$field_templatesetvar);
        $templates->set_var ('field_description',$field_description);

        $templates->set_var ('fieldvalue',$fieldvalue);

        //page
        $offset = ($page - 1) * $perpage;
        $templates->set_var ('cnt', $cnt);
        $lin1=$offset+1;
        $lin2=$lin1+$perpage - 1;
        if ($lin2>$cnt){
            $lin2=$cnt;
        }

        //summary navigation
        $templates->set_var ('lang_view', $LANG_USERBOX['view']);
        $templates->set_var ('lin', $lin1."-".($lin2));
        $templates->set_var ('cnt', $cnt);

        $templates->set_var ('lang_name', $LANG_USERBOX_ADMIN['name']);

        $templates->set_var ('lang_username', $LANG_USERBOX_ADMIN['username']);
        $templates->set_var ('lang_id', $LANG_USERBOX_ADMIN['id']);
        $templates->set_var ('lang_uid', $LANG28[2]);
        $templates->set_var ('lang_username', $LANG28[3]);
        $templates->set_var ('lang_fullname',$LANG28[4]);

        //
        $templates->set_var ('lang_date', $LANG_USERBOX_ADMIN[$datefield]);
        $templates->set_var ('lang_released', $LANG_USERBOX_ADMIN['released']);
        $templates->set_var ('lang_description', $LANG_USERBOX_ADMIN['description']);
        $templates->set_var ('lang_expired', $LANG_DATABOX_ADMIN['expired']);
        $templates->set_var ('lang_remaingdays', $LANG_DATABOX_ADMIN['remaingdays']);
        $templates->set_var ('lang_addfield', $addfield_name);
        
        //$_USERBOX_CONF
        $templates->set_var('lang_imgfile_frd', $LANG_confignames['userbox']['imgfile_frd']);
        $templates->set_var ('imgfile_frd', $_USERBOX_CONF['imgfile_frd']);
        $templates->set_var ('data_img_url', $_CONF['site_url']."/".$_USERBOX_CONF['imgfile_frd']);
        $templates->set_var('lang_imgfile_thumb_frd', $LANG_confignames['userbox']['imgfile_thumb_frd']);
        $templates->set_var ('imgfile_thumb_frd', $_USERBOX_CONF['imgfile_thumb_frd']);
        $templates->set_var ('data_thumb_img_url', $_CONF['site_url']."/".$_DATABOX_CONF['imgfile_thumb_frd']);
        
         for ($i = 0; $i < $numrows; $i++) {
            $A = DB_fetchArray ($result);
            $A = array_map('stripslashes', $A);

            $description=COM_applyFilter($A['description']);
            $username=COM_applyFilter($A['username']);
            $fullname=COM_applyFilter($A['fullname']);
            $id=COM_applyFilter($A['id']);
            $datefield=COM_applyFilter($A['datefield']);
            $released=COM_applyFilter($A['released']);
            $expired=COM_applyFilter($A['expired']);
            $datefield_ary = COM_getUserDateTimeFormat($A['datefield_un']);
            $released_ary = COM_getUserDateTimeFormat($A['released_un']);
            if ($expired==="0000-00-00 00:00:00"){
                $expired_ary=array();
            }else{    
                $expired_ary = COM_getUserDateTimeFormat($A['expired_un']);
            }
            $curdate_ary =  COM_getUserDateTimeFormat();
            
            $sortvalue=COM_applyFilter($A['value']);
            $group_id = $A['group_id'];
            $owner_id = $A['owner_id'];
            
            $fieldset_id=COM_applyFilter($A['fieldset_id']);

            $url=$_CONF['site_url'] . "/userbox/profile.php";
            $url.="?";
            //コード使用の時
            if ($_USERBOX_CONF['datacode']){
                $url.="m=code";
                $url.="&amp;code=".$A['username'];
            }else{
                $url.="id=".$A['id'];
                $url.="&amp;m=id";
            }
            $url = COM_buildUrl( $url );
            $link= COM_createLink($username, $url);


            $templates->set_var ('data_link', $link);

            $templates->set_var ('data_fullname', $fullname);
            $templates->set_var ('data_title', $fullname);

            $templates->set_var ('data_username', $username);
            $templates->set_var ('data_code', $username);

            $templates->set_var ('data_description', $description);
            $templates->set_var ('data_id', $id);
            $templates->set_var ('data_url', $url);
            //data_released
            $templates->set_var ('data_datefield', $datefield);
            $templates->set_var ('data_datefield_shortdate', strftime( $_CONF['shortdate'], $A['datefield_un'] ));
            $templates->set_var ('data_value', $sortvalue);
            
            $templates->set_var ('data_released', $released_ary[0]);//@@@@@@
            $templates->set_var ('data_released_shortdate', strftime( $_CONF['shortdate'], $A['released_un'] ));
            //公開終了日 Expired to publish
            if ($A['expired'] ==="0000-00-00 00:00:00"){
                $templates->set_var ('data_expired', "");
                $templates->set_var ('data_expired_shortdate', "" );
            }else{
                $wary = COM_getUserDateTimeFormat($A['expired_un']);
                $templates->set_var ('data_expired', $expired_ary[0]);
                $templates->set_var ('data_expired_shortdate', strftime( $_CONF['shortdate'], $A['expired_un'] ));
            }
            $remaingdays="";
            if ($expired<>"0000-00-00 00:00:00") {
                if  ($expired_ary[1]>=$curdate_ary[1]){
                    $remaingdays=COM_dateDiff( "d", $expired_ary[1], $curdate_ary[1] ) + 1;
                }
            }
            $templates->set_var ('data_remaingdays', $remaingdays);//@@@@@@

            if (date("Ymd",strtotime($datefield)) >= date("Ymd",$chkday)){
                $templates->set_var ('new_img', $new_img);
            }else{
                $templates->set_var ('new_img', '');
            }

            if (isset($A['photo']) && empty($A['photo'])) {
                $A['photo'] = '(none)'; // user does not have a photo
                $templates->set_var('data_photo', "");
            }else{
                $templates->set_var('data_photo', $A['photo']);
            }
            $photo = USER_getPhoto($uid, $A['photo'], $A['email'], -1);
            $templates->set_var('data_user_photo', $photo);

            $hits=COM_applyFilter(DB_getItem( $tbl5 ,"hits","id={$id}"),true);
            $templates->set_var('lang_hits', $LANG_USERBOX_ADMIN['hits']);
            $templates->set_var('data_hits', $hits);
            //カテゴリ categories 
            $templates->set_var('lang_category', $LANG_DATABOX_ADMIN['category']);
            DATABOX_getcategoriesDisp($A['id'],$templates,$chk_user,0,$pi_name);

            //追加項目 fields
            $group_id = stripslashes($A['group_id']);
            $owner_id = stripslashes($A['owner_id']);
            $chk_user=DATABOX_chkuser($group_id,$owner_id,"userbox.admin");
            $additionfields = DATABOX_getadditiondatas($id,$pi_name);
            DATABOX_getaddtionfieldsDisp($additionfields,$addition_def,$templates,$chk_user,$pi_name,$fieldset_id);
            
            //管理者の時「編集」
            if ( SEC_hasRights('databox.admin')) {
                $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                $attr = array('title' => $title." ".$LANG_USERBOX_ADMIN['edit']);
                $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);
                
                $attr = array('class' => 'editlink', 'title' => $title." ".$LANG_USERBOX_ADMIN['edit']);
                $url = $_CONF['site_admin_url'];
                $url .= '/plugins/userbox/profile.php';
                $url .= '?mode=edit';
                $url .= '&amp;'."id={$id}";
                $icon = '&nbsp;' ;
                $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );
                $templates->set_var ('data_edit', $icon);
            }else{
                $rt=databox_chk_loaddata($id);
                //編集権限のあるMyProfile
                if ( $rt==="OK") {
                    $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
                    $attr = array('title' => $LANG_USERBOX_ADMIN['edit']);
                    $editiconhtml = COM_createImage($icon_url, $LANG_USERBOX_ADMIN['edit'], $attr);

                    $attr = array('class' => 'editlink', 'title' => $title." ".$LANG_USERBOX_ADMIN['edit']);

                    $url = $_CONF['site_url'];
                    $url .= '/databox/myprofile/profile.php';
                    $url .= '?mode=edit';
                    $url .= '&amp;'."id={$id}";

                    $icon = '&nbsp;' ;
                    $icon .=  COM_createLink( $editiconhtml,  $url,   $attr   );

                    $templates->set_var ('data_edit', $icon);
                }else{
                    $templates->set_var ('data_edit', "");
                }
                
            }

            //=====
            $templates->parse ('col_var', 'col', true);
            $templates->parse ('row_var', 'row', true);

            $templates->set_var ('col_var', '');

        }

        //------------
        //-----navigation
        $url = $_CONF['site_url']  . '/';
        if ($autotag==="notautotag"){
            $url.=THIS_SCRIPT;
        }else{
            $url.="userbox/field.php";
        }
        $url .="?";
        $url .= "id=".$field_id;
        $url .= "&amp;m=id";
        $url .= "&amp;value=".$value;
        $url .= "&amp;template=".$template;
        $url .= "&amp;perpage=".$perpage;
        $url .= "&amp;order=";
        //-----order navigation
        userbox_order ($url, $templates, $order) ;
        //-----page navigation
        $url .= $order;
        $templates->set_var ('page_navigation',
                COM_printPageNavigation ($url, $page, $pages));
        if ($order==="random"){
            $templates->set_var ( 'pagenavinone', 'style="display:none;"' );
        }else{
            $templates->set_var ( 'pagenavinone', '' );
        }
        // Call to plugins to set template variables in the databox
        PLG_templateSetVars( 'userbox', $templates );

        //------------
        $templates->parse ('nav_var', 'nav', true);

        $templates->set_var ('msg', "");

        $templates->parse ('output', 'list');

        $content = $templates->finish ($templates->get_var ('output'));
        $retval .=$content;

    }else{
        if ($nohitmsg==="yes"){
            $tmplfld=DATABOX_templatePath('attribute',$template,$pi_name);
            $templates = new Template($tmplfld);
            $templates->set_file (array (
                'list' => 'nohit.thtml',
            ));
            
            //bread
            $templates->set_var ('home',$LANG_USERBOX['home']);
            $templates->set_var ('attribute_top',$attribute_top);
            $templates->set_var ('field_top',$field_top);
            
            $templates->set_var ('field_name',$field_name);
            $templates->set_var ('field_templatesetvar',$templatesetvar);
            $templates->set_var ('field_description',$field_description);
            
            $templates->set_var ('fieldvalue',$fieldvalue);
                        
            $templates->set_var('xhtml', XHTML);
            $templates->set_var('site_url', $_CONF['site_url']);
            $templates->set_var('site_admin_url', $_CONF['site_admin_url']);
            $templates->set_var('layout_url', $_CONF['layout_url']);
            
            $templates->set_var ('lang_nohit', $LANG_USERBOX['nohit']);

            $templates->parse ('output', 'list');
            $content = $templates->finish ($templates->get_var ('output'));
            $retval .=$content;
        }
    }

    $retval =PLG_replacetags ($retval);

    return $retval;
}

function userbox_orderby(
    $datefield
    ,&$order
    ,&$orderby
    ,&$addfieldorder
    ,&$field_id

)
// +---------------------------------------------------------------------------+
// | 機能  order orderby addfieldorder $field_id を編集
// | 書式  userbox_orderby($datefield,$order,$orderby,$addfieldorder,$field_id);
// +---------------------------------------------------------------------------+
// | 引数 $datefield
// | 引数戻値 $order
// | 引数戻値 $orderby
// | 引数戻値 $addfieldorder
// | 引数戻値 $field_id
// +---------------------------------------------------------------------------+
{

    global $_TABLES;

    $orderby="";
    $addfieldorder=false;
    switch($order){
        case "random":
            $orderby =" RAND()";
            break;
        case "":
        case "date":
            $order="date";
            $orderby = $datefield ." DESC";
            break;
        case "released":
            $orderby = " released DESC";
            break;
        case "orderno":
        case "code":
        case "title":
        case "description":
        case "id":
            $orderby = " t2.".$order;
            break;
        case "username":
        case "fullname":
            $orderby = " t4.".$order;
            break;
        default:
            $sql="SELECT ";
            $sql.= " field_id ";
            $sql.= ",name ";
            $sql.=" FROM";
            $sql.=" {$_TABLES['USERBOX_def_field']} ";
            $sql.=" WHERE templatesetvar='".$order."'";

            $result = DB_query ($sql);
            $numrows = DB_numRows ($result);

            if ($numrows>0){
                $A = DB_fetchArray ($result);
                $field_id=COM_stripslashes($A['field_id']);
                $addfield_name=COM_stripslashes($A['name']);
                $orderby = " t3.value IS NULL ASC ,cast(t3.value as DECIMAL) ASC ";
                $addfieldorder=true;
            }else{
                $order="date";
                $orderby = $datefield ." DESC";
            }
    }

    return ;

}


function userbox_newlist(
     $m="ALL"
    ,$categories=""
    ,$rss_file=""
    ,$title_trim_length=""
    ,$intervalday=""
    ,$limitcnt=""
    ,$newmarkday=""
    ,$thtml=null
)
// +---------------------------------------------------------------------------+
// | 機能  ブロック用 新着user一覧出力                                         |
// | 書式 userbox_newlist                                                      |
// +---------------------------------------------------------------------------+
// | 引数 $m             :省略時'ALL' id=id指定で選択 code指定で選択           |
// | 引数 $categories    :選択トピック 省略時設定値or'ALL'                     |
// | 引数 $rss_file      :RSS_file     省略時""                                |
// | 引数 $title_trim_length :タイトル長の制限 省略時設定値or20                |
// | 引数 $intervalday   :新着記事の期間 単位日  省略時設定値or90              |
// |                      0の時　全件                                          |
// | 引数 $limitcnt      :表示件数   省略時設定値or10                          |
// | 引数 $newmarkday    :新着マーク 省略時設定値or3                           |
// | 引数 $thtml         :テンプレートファイルのフォルダ  layout/テーマ/XXXX/  |
// |                      省略時は、プラグイン内のテンプレート                 |
// +---------------------------------------------------------------------------+
// | 戻値 nomal:新着user一覧                                                   |
// +---------------------------------------------------------------------------+
// update20101124
{
    $pi_name="userbox";

    global $_USER;
    global $_TABLES;
    global $_CONF;

    global $_USERBOX_CONF;
    global $LANG_USERBOX;

    //
    if ($title_trim_length==""){
        $title_trim_length=$_USERBOX_CONF['title_trim_length'];
        if ($title_trim_length==""){
            $title_trim_length=20;
        }
    }
    //
    if ($intervalday==""){
        $intervalday=$_USERBOX_CONF['intervalday'];
        if ($intervalday==""){
            $intervalday=90;
        }
    }
    //
    if ($limitcnt==""){
        $limitcnt=$_USERBOX_CONF['limitcnt'];
        if ($limitcnt==""){
            $limitcnt=10;
        }
    }
    if ($newmarkday==""){
        $newmarkday=$_USERBOX_CONF['newmarkday'];
        if ($newmarkday==""){
            $newmarkday=3;
        }
    }

    if ($m===""){
        $categories="ALL";
    }else{
        if ($categories==""){
            $categories=$_USERBOX_CONF['categories'];
            if ($categories==""){
                $categories="ALL";
            }
        }elseif (strpos($categories,",")=="") {
            $categories="'$categories'";
        }else{
            $ary=explode(",",$categories);
            $b="";
            for ($i = 1; $i <= count($ary); $i++) {
                if ($m==="code"){
                    $b.="'".current($ary)."',";
                }else{
                    $b.=current($ary).",";
                }
                next($ary);
            }
            $categories=rtrim($b,",");
        }
    }

    //
    $new_img=$_USERBOX_CONF['new_img'];
    if ($new_img==""){
        $new_img="New!";
    }
    $rss_img=$_USERBOX_CONF['rss_img'];
    if ($rss_img=="") {
        $rss_img="[RSS]";
    }

    if (is_null($thtml)){
        $thtml="default";
    }

//-------------------------------------------
    $retval="";

    //テンプレートフォルダの設定
    $tmplfld=DATABOX_templatePath('newlist',$thtml,$pi_name);

    $list = new Template($tmplfld);
    $list->set_file (array (
        'list' => 'list.thtml',
        'col'   => 'list_col.thtml'
    ));

    $datefield=$_USERBOX_CONF['datefield'];//使用する日付（編集日付、作成日付）

    //-----
    $tbl1=$_TABLES['USERBOX_base'] ;
    $tbl2=$_TABLES['USERBOX_category'] ;
    $tbl3=$_TABLES['USERBOX_def_category'] ;
    $tbl4=$_TABLES['users'] ;


    $sql="SELECT ".LB;
    $sql.=" t4.fullname".LB;
    $sql.=" , UNIX_TIMESTAMP(t1.".$datefield .") AS day".LB;
    $sql.=" , t1.id".LB;
    $sql.=" , t4.username".LB;

    $sql .= " FROM ".LB;
    $sql .= " {$tbl1} AS t1 ".LB;
    $sql .= " , {$tbl4} AS t4 ".LB;

    if ($categories!=="ALL" ){
        $sql .= " ,{$tbl2} AS t2 ".LB;
        $sql .= " ,{$tbl3} AS t3 ".LB;
    }

    $sql.=" WHERE";
    if ($categories==="ALL" ){
        $sql.=" t1.id=t4.uid".LB;
    }else{
        $sql.= " t1.id = t4.id ".LB;
        $sql.= " AND t1.id=t2.uid".LB;
        $sql.= " AND t2.category_id = t3.category_id ".LB;
    }

    //管理者の時,下書データも含む
    //if ( SEC_hasRights('userbox.admin')) {
    //}else{
       $sql .= " AND t1.draft_flag=0".LB;
    //}
    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND').LB;
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;

    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())".LB;

    if ($intervalday<>0){
        $sql.=" AND (".$datefield ." >= (date_sub(NOW(), INTERVAL $intervalday day)))".LB;
    }

    if ($categories!=="ALL" ){
        if ($m==="id") {
            $sql.=" AND t2.category_id IN ($categories)";
        }else{
            $sql.=" AND t3.code IN ($categories)";
        }
    }
    $sql .= COM_getLangSQL ('username', 'AND', 't4').LB;

    $sql.=" ORDER BY ".$datefield ." DESC";
    $sql.=" LIMIT $limitcnt";

    $result = DB_query ($sql);

    $i=0;

    while( $A = DB_fetchArray( $result ) )    {

        $title= COM_truncate( $A['username'], $title_trim_length,'...' );

        $url=$_CONF['site_url'] . "/userbox/profile.php";
        $url.="?";
        //コード使用の時
        if ($_USERBOX_CONF['datacode']){
            $url.="m=code";
            $url.="&code=".$A['username'];
        }else{
            $url.="m=id";
            $url.="&id=".$A['id'];
        }
        $url = COM_buildUrl( $url );
        $link= COM_createLink($title, $url);


        $list->set_var ('site_url', $_CONF['site_url']);

        $list->set_var ('day', strftime($LANG_USERBOX['day'],$A['day']));

        $chkday=strtotime("- $newmarkday days",time());
        if (date("Ymd",$A['day']) >= date("Ymd",$chkday)){
            $list->set_var ('new_img', $new_img);
        }else{
            $list->set_var ('new_img', '');
        }
        $list->set_var ('link', $link);//@@@@@

        $list->set_var ('url', $url);
        $list->set_var ('title', $title);
        $n=($i%2)+1;
        $class='class="row'.$n.'"';
        $list->set_var ('class', 'class="row'.$n.'"');
        
        //カテゴリ
        DATABOX_getcategoriesDisp($A['id'],$list,$chk_user,0,$pi_name);

        $list->parse ('list_col', 'col', true);

        $i++;
    }

    $list->set_var ('site_url', $_CONF['site_url']);
    $list->set_var ('day', strftime($LANG_USERBOXBOX['day'],$A['day']));

    //
    if ($rss_file<>""){
        $list->set_var ('rss_url', $_CONF['site_url'] . '/backend/'.$rss_file);
        $list->set_var ('rss_title', $rss_img);
    }
    //
    $more_title=$LANG_USERBOX['more'];
    if ($categories==="ALL" OR strpos($categories,",")<>"") {
        $more_url=COM_buildUrl($_CONF['site_url'] . '/userbox/index.php');//
    }else{
        //コード使用の時
        if ($m="code"){
            $more_url.="m=code";
            $more_url.="&code=".ltrim(rtrim($categories,"'"),"'");
        }else{
            $more_url.="m=id";
            $more_url.="&id=".ltrim(rtrim($categories,"'"),"'");
        }
        $more_url=$_CONF['site_url'] . "/userbox/index.php".$more_url;//@@@@@
    }

    $more_url = COM_buildUrl( $more_url );
    $more_link= COM_createLink($title, $more_url);
    $list->set_var ('more_url', $more_url);
    $list->set_var ('more_title', $more_title);

    $list->parse ('output', 'list');
    $retval .= $list->finish ($list->get_var ('output'));


    return $retval;
}

function userbox_order(
    $url=""
    ,$templates
    ,$cur_order="id"
)
// +---------------------------------------------------------------------------+
// | 機能 ORDER指定LINK出力
// | 書式 userbox_order()
// +---------------------------------------------------------------------------+
// | 引数 $url:
// | 引数 $templates
// | 引数 $cur_order:
// +---------------------------------------------------------------------------+
{
global $LANG_USERBOX_ORDER;
global $_TABLES;

    $templates->set_var('cur_order',$cur_order);

    $order_ary=array();
    $order_ary[]='random';
    $order_ary[]='date';
    $order_ary[]='orderno';
    $order_ary[]='username';
    $order_ary[]='fullname';
    $order_ary[]='description';
    $order_ary[]='id';
    $order_ary[]='released';

    $sql="SELECT ";
    $sql.= " field_id ";
    $sql.= ",name ";
    $sql.= ",templatesetvar";
    $sql.=" FROM";
    $sql.=" {$_TABLES['USERBOX_def_field']} ";
    $sql.=" where allow_display=0";

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    $addfield_ary=array();
    for ($i = 1; $i <= $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $field_id=COM_stripslashes($A['field_id']);
        $addfield_ary[$field_id]['name']=COM_stripslashes($A['name']);
        $addfield_ary[$field_id]['templatesetvar']=COM_stripslashes($A['templatesetvar']);
        $order_ary[]="addfield".$field_id;
    }


    for ($i = 1; $i <= count($order_ary); $i++) {
        $c=current($order_ary);

        if (substr($c,0,8)==="addfield"){
            $field_id=substr($c,8);
            $order=$addfield_ary[$field_id]['templatesetvar'];
            $order_name=$addfield_ary[$field_id]['name'];
            $order_name.=$LANG_USERBOX_ORDER['order'];
        }else{
            $order=$c;
            $order_name=$LANG_USERBOX_ORDER[$c];
        }

        if ($order<>"random" AND $order===$cur_order){
            $rt=$order_name;
        }else{
            $order_url=$url;
            $order_url.=$order;
            //$order_url = COM_buildUrl( $order_url );
            $rt= COM_createLink($order_name, $order_url);
        }

        $templates->set_var('order_'.$order,$rt);

        next($order_ary);
    }

    return ;


}



function userbox_msg(
    $for=""
)
// +---------------------------------------------------------------------------+
// | 機能 メッセージ出力
// | 書式 userbox_msg()
// +---------------------------------------------------------------------------+
// | 引数 $for:誰宛
// +---------------------------------------------------------------------------+
{
global $LANG_USERBOX_MSG;
global $_USER;
global $_TABLES;
    
    $rt="";
    $uid=$_USER['uid'];
    
    switch ($for) {
        case '':
        case 'alluser':
            $rt=$LANG_USERBOX_MSG['alluser'];
            break;
        case 'draftuser':
            $draft_flag=DB_getItem($_TABLES['USERBOX_base'], 'draft_flag',
                        "id = '{$uid}'");
            if ($draft_flag==1){
                $rt=$LANG_USERBOX_MSG['draftuser'];
            }
            break;
        case 'descriptionempty':
            if ($uid<>0){
                if (userbox_descriptionemptycheck($uid)==false){
                    $rt=$LANG_USERBOX_MSG['descriptionempty'];
                }
            }
            break;
        default:
        
    }
    
    return $rt;

}


?>
