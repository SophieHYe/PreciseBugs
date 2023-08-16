<?php
/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | DataBox プラグイン   function.inc からrequire
// | Box シリーズ共通
// +---------------------------------------------------------------------------+
// $Id: plugins/databox/lib/databox_functions.inc
//20100818 tsuchitani AT ivywe DOT co DOT jp http://www.ivywe.co.jp/

if (strpos ($_SERVER['PHP_SELF'], 'databox_functions.inc') !== false) {
    die ('This file can not be used on its own.');
}

//==============================================================================
function DATABOX_nl2br (
    $text
) 
// +---------------------------------------------------------------------------+
// | 機能 改行をBRに変換
// | 書式 DATABOX_nl2br ($text)
// +---------------------------------------------------------------------------+
// | 引数 $text:文字列
// +---------------------------------------------------------------------------+
// | 戻値 nomal:改行をBRに変換後の文字列
// +---------------------------------------------------------------------------+
// copy20100803 from  "hiroron - 改行をBRに変換"
{
    return PLG_replacetags ( str_replace(array("\r\n","\r","\n"), '<br'.XHTML.'>', $text) );

}

function DATABOX_autolink (
    $text
)
// +---------------------------------------------------------------------------+
// | 機能 urlをリンク変換
// | 書式 DATABOX_autolink($text)
// +---------------------------------------------------------------------------+
// | 引数 $text         :文字列                                                |
// +---------------------------------------------------------------------------+
// | 戻値 nomal:urlをリンク変換後の文字列                                       |
// +---------------------------------------------------------------------------+
// copy20100803 from  "hiroron - 自動リンクを
{
    $patterns = array("/(https?|ftp)(:\/\/[[:alnum:]\+\$\;\?\.%,!#~*\/:@&=_-]+)/i");
    $replacements = array("<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>");
    return preg_replace($patterns, $replacements, $text);
}

function DATABOX_codetoid(
    $code
    ,$table
    ,$idname = "id"
    ,$codename = "code"
)
// +---------------------------------------------------------------------------+
// | 機能  コードをidに変換
// | 書式 DATABOX_codetoid($code,'DATABOX_base')
// | 書式 DATABOX_codetoid($code,'users',"uid","username")
// +---------------------------------------------------------------------------+
// | 引数 $code:コード
// | 引数 $table
// | 引数 $idname
// | 引数 $codename
// +---------------------------------------------------------------------------+
// | 戻値 nomal:id
// +---------------------------------------------------------------------------+
//update20101125
{
    global $_TABLES;
    $retval=DB_getItem( $_TABLES[$table]   ,$idname,$codename."='{$code}'");
    if (empty($retval)){
        $retval=0;
    }
    return $retval;
}

function DATABOX_swichlang(
	$code
)
// +---------------------------------------------------------------------------+
// | 機能  コード多言語対応変換
// | 書式 DATABOX_swichlang($code)
// |      xxxx_(言語id) 多言語モードの時に必要があれば言語idを現在の言語idに変換
// +---------------------------------------------------------------------------+
// | 引数 $code:コード
// +---------------------------------------------------------------------------+
// | 戻値 nomal:変換後のコード
// +---------------------------------------------------------------------------+
{
    
    global $_CONF;
    $newlanguageid = COM_getLanguageId();
    if  ($newlanguageid<>""){
        if  ($code<>""){
            $lang_len = strlen($newlanguageid);
            $oldlanguageid=substr($code,-($lang_len));
            if  (array_key_exists($oldlanguageid, $_CONF['language_files'])){
                $code = substr_replace($code, $newlanguageid, -$lang_len);
            }
        }
    }
    return $code;
}


function DATABOX_templatePath (
    $kind = 'data'
    ,$template ='default'
    ,$pi_name= 'databox'
    )
// +---------------------------------------------------------------------------+
// | 機能  テンプレートフォルダパスを取得
// | 書式 DATABOX_templatePath('data','default','databox')
// | 書式 DATABOX_templatePath($kind,$template,$pi_name)
// +---------------------------------------------------------------------------+
// | 引数 $kind:'data' 'category' ''newlist' 'admin'
// | 引数 $template:  'default'
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 :テンプレートフォルダパス
// +---------------------------------------------------------------------------+
// 20101115
{

    global $_CONF;

    if (is_null($template) OR ($template==="")){
        $template="default";
    }

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;


    if ($kind==="admin"){
        $conf_templates=$box_conf["templates_admin"];
    }else{
        $conf_templates=$box_conf["templates"];
    }

    //"standard";//標準テンプレートを使用する
    //"custom";//カスタムテンプレートを使用する
    //"theme";//テーマテンプレートを使用する
    if  ($conf_templates==="theme"){

        $tmplfld=$_CONF['path_layout'] .$box_conf['themespath'].$kind;
        if ($kind<>"admin"){

            $tmplfld.="/".$template;
        }
        if (is_dir($tmplfld)) {

        }else{
            COM_handle404();
            exit;
        }
    }else if  ($conf_templates==="custom"){

        $tmplfld=$_CONF['path'] .'plugins/'.$pi_name.'/custom/templates/'.$kind;

        if ($kind<>"admin"){

            $tmplfld.="/".$template;

        }

        if (is_dir($tmplfld)){

        }else{
            COM_handle404();
            exit;
        }
    }else{

        $tmplfld=$_CONF['path'] .'plugins/'.$pi_name.'/templates/'.$kind;
        if ($kind<>"admin"){

            $tmplfld.="/default";
        }
    }


    return $tmplfld;
}

function DATABOX_siteHeader (
    $pi_name= 'databox'
    ,$kind = ''
    ,$page_title = ''
    ,$headercode = ''
    )
// +---------------------------------------------------------------------------+
// | 機能  ヘッダを編集
// | 書式 DATABOX_siteHeader($pi_name,'_admin',$page_title,$headercode)
// | 書式 DATABOX_siteHeader($pi_name,'_admin',$page_title)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// | 引数 $kind:'' '_admin'
// | 引数 $pagetitle:
// | 引数 $headercode:
// +---------------------------------------------------------------------------+
// | 戻値 :ヘッダ
// +---------------------------------------------------------------------------+
// 20120910
{
    global $_CONF;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;


    $display="";
    
    if  ($kind=="_admin" OR $kind==""){
        $layout=$box_conf['layout'.$kind];
    }else{
        $layout=$kind;
    }
    switch ($layout) {
        // ヘッダ・フッタ・左ブロックあり（右ブロックはテーマ設定による）
        case 'standard':
        //ヘッダ・フッタ・左右ブロックあり
        case 'leftrightblocks':
        //ヘッダ・フッタ・左ブロックあり（右ブロックなし）
        case 'leftblocks':
            $display=COM_siteHeader ('menu', $page_title,$headercode);
            break;
        //ヘッダ・フッタ・右ブロックあり（左ブロックなし）
        case 'rightblocks':
        //ヘッダ・フッタあり（ブロックなし）
        case 'noblocks':
            $display=COM_siteHeader ('none', $page_title,$headercode);
            break;

        //全画面表示（ヘッダ・フッタ・ブロックなし）
        case 'blankpage':
            break;

        default:

    }



    return $display;
}


function DATABOX_siteFooter (
    $pi_name= 'databox'
    ,$kind = ''
    ,$custom=''
    )
// +---------------------------------------------------------------------------+
// | 機能  フッタを編集
// | 書式 DATABOX_siteFooter($pi_name,'_admin',$custom)
// | 書式 DATABOX_siteFooter($pi_name)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// | 引数 $kind:'' '_admin'
// | 引数 $custom:
// +---------------------------------------------------------------------------+
// | 戻値 : フッタ
// +---------------------------------------------------------------------------+
// 20110119
{
    global $_CONF;

    if (is_null($pi_name)){
        $pi_name="databox";
    }

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $display="";

    $layout=$box_conf['layout'.$kind];
    switch ($layout) {
        // ヘッダ・フッタ・左ブロックあり（右ブロックはテーマ設定による）
        case 'standard':
        $display .= COM_siteFooter(false,$custom);
            break;
        //ヘッダ・フッタ・左右ブロックあり
        case 'leftrightblocks':
        //ヘッダ・フッタ・右ブロックあり（左ブロックなし）
        case 'rightblocks':
            $display .= COM_siteFooter(true,$custom);
            break;
        //ヘッダ・フッタ・左ブロックあり（右ブロックなし）
        case 'leftblocks':
        //ヘッダ・フッタあり（ブロックなし）
        // 右ブロックを常時表示する設定であっても右ブロックを表示させない
        case 'noblocks':
            $_CONF['show_right_blocks']=0;
            $_CONF['left_blocks_in_footer']=0;
            $display .= COM_siteFooter(false,$custom);
            break;

        //全画面表示（ヘッダ・フッタ・ブロックなし）
        case 'blankpage':
            break;

        default:

    }

    return $display;
}


//$retval = SP_returnStaticpage($page, $display_mode, $comment_order, $comment_mode, $comment_page, $msg, $query);

function DATABOX_displaypage (
    $pi_name= 'databox'
    ,$kind = ''
    ,$display = ''
    ,$information = ''
)
//$information
// 'what'  'none' no left blocks are returned
//         'menu' (default)  right blocks are returned
// 'pagetitle'     Optional content for the page's <title>
// 'breadcrumbs'   Optional content for the page's <title>
// 'headercode'    Optional code to go into the page's <head>
// 'rightblock'    右ブロックを表示するかどうか　 default is no (-1)
// 'custom'        An array defining custom function to be used to 

{
    global $_CONF;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    if  ($kind=="_admin" OR $kind==""){
        $layout=$box_conf['layout'.$kind];
    }else{
        $layout=$kind;
    }
    
    //全画面表示（ヘッダ・フッタ・ブロックなし）
    if  ($layout=='blankpage'){
        
    }else{
        switch ($layout) {
            // ヘッダ・フッタ・左ブロックあり（右ブロックはテーマ設定による）
            case 'standard':
                //$display=COM_siteHeader ('menu', $page_title,$headercode);
                //$display .= COM_siteFooter(false,$custom);
                $information['what']='menu';
                $information['rightblock']=false;
                break;
            //ヘッダ・フッタ・左右ブロックあり
            case 'leftrightblocks':
                //$display=COM_siteHeader ('menu', $page_title,$headercode);
                //$display .= COM_siteFooter(true,$custom);
                $information['what']='menu';
                $information['rightblock']=true;
                break;
            //ヘッダ・フッタ・左ブロックあり（右ブロックなし）
            case 'leftblocks':
                //$display=COM_siteHeader ('menu', $page_title,$headercode);
                $information['what']='menu';
                $_CONF['show_right_blocks']=0;
                $_CONF['left_blocks_in_footer']=0;
                //$display .= COM_siteFooter(false,$custom);
                $information['rightblock']=false;
                break;
            //ヘッダ・フッタ・右ブロックあり（左ブロックなし）
            case 'rightblocks':
                //$display=COM_siteHeader ('none', $page_title,$headercode);
                $information['what']='none';
                //$display .= COM_siteFooter(true,$custom);
                $information['rightblock']=true;
                break;
            //ヘッダ・フッタあり（ブロックなし）
            case 'noblocks':
                //$display=COM_siteHeader ('none', $page_title,$headercode);
                $information['what']='none';
                $_CONF['show_right_blocks']=0;
                $_CONF['left_blocks_in_footer']=0;
                //$display .= COM_siteFooter(false,$custom);
                $information['rightblock']=false;
                break;
        }
        
        //FOR GL2.0.0 
        if (COM_versionCompare(VERSION, "2.0.0",  '>=')){
            $display = COM_createHTMLDocument($display,$information);
        }else{
            $display = COM_siteHeader ($information['what'], $information['pagetitle']).$display;
            $display .= COM_siteFooter($information['rightblock']);
        }
    }


    return $display;
}


function DATABOX_getcheckList(
    $kind
    ,$selected
    ,$pi_name
    ,$group_id=""
    ,$fname=""
)
// +---------------------------------------------------------------------------+
// | 機能  チェックリスト作成
// | 書式 DATABOX_getcheckList($kind,$selected = "")
// | 書式 $checklist=DATABOX_getcheckList ("fieldset","",$pi_name)
// | 書式 $checklist=DATABOX_getcheckList ("categorygroup","",$pi_name,1,"ch1")
// +---------------------------------------------------------------------------+
// | 引数 $kind:fieldset
// | 引数 $selected:選択値
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox' 
// | 引数 $group_id　マスターの時既定リスト名
// | 引数 $fname フィールド名
// +---------------------------------------------------------------------------+
// | 戻値 nomal:チェックリスト
// +---------------------------------------------------------------------------+
//20101116
// カテゴリ以外で使っているか確認のこと --> category は DATABOX_getcategoriesinp
//20140624 fieldset　追加
{
global $_TABLES;
    
    if ($fname==""){
        $fname=$kind;
    }

    $table_def_category=$_TABLES[strtoupper($pi_name).'_def_category'];
    $table_def_group=$_TABLES[strtoupper($pi_name).'_def_group'];
    $table_mst=$_TABLES[strtoupper($pi_name).'_mst'];
    $table_def_fieldset=$_TABLES[strtoupper($pi_name).'_def_fieldset'];
    $retval = '';
    if ($kind=="categorygroup"){
        $sql = "SELECT ";
        $sql .= " m1.category_id".LB;
        $sql .= ",m1.name".LB;
        $sql .= ",m1.orderno".LB;
        $sql .= ",m2.group_id".LB;
        $sql .= ",m2.name AS group_name".LB;
        $sql .= " FROM {$table_def_category} AS m1".LB;
        $sql .= " , {$table_def_group} AS m2".LB;
        $sql .= " WHERE ".LB;
        $sql .= " m1.allow_display<2 ".LB;
        $sql .= " AND m1.categorygroup_id =".$group_id.LB;
        $sql .= " AND m2.group_id = m1.categorygroup_id".LB;
        $sql .= " ORDER BY m1.orderno".LB;
    }else if ($kind=="fieldset"){
        $sql = "SELECT ";
        $sql .= " fieldset_id".LB;
        $sql .= ",name".LB;
        $sql .= ",'1' AS group_id".LB;
        $sql .= " FROM {$table_def_fieldset} ".LB;
        $sql .= " ORDER BY fieldset_id".LB;
    }else{
        $sql = "SELECT ".LB;
        $sql .= " no".LB;
        $sql .= ",value".LB;
        $sql .= ",orderno".LB;
        $sql .= ",'1' AS group_id".LB;
        $sql .= " FROM {$table_mst}".LB;
        $sql .= " WHERE kind='{$kind}'".LB;
        $sql .= " ORDER BY orderno".LB;
    }
    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );
    if (is_array($selected)){
            $S = $selected;
    }else{
        if( !empty( $selected ))    {
            $S = explode( ' ', $selected );
        }else {
            $S = array();
        }
    }
    $old_group_id="";
    for( $i = 0; $i < $nrows; $i++ )    {

        $A = DB_fetchArray( $result, true );
        $retval .= '<span style="white-space:nowrap">';
        $retval .= '<input type="checkbox" name="' . $fname . '[]" value="' . $A[0] ;
        $retval .= '"';
        for( $x = 0; $x < sizeof( $S ); $x++ )            {
            if( $A[0] == $S[$x] )                {
                $retval .= ' checked="checked"';
            }
        }
        $retval .= '>' . stripslashes( $A[1] ) . '</span>' . LB;
    }

    return $retval;
}

function DATABOX_getdatas(
    $fld
    ,$tbl
    ,$where
)
// +---------------------------------------------------------------------------+
// | 機能  複数データ読込                                                      |
// | 書式 DATABOX_getdatas($fld,$tbl,$where)                                   |
// | $rt=DATABOX_getdatas(                                                     |
// |      "category_id",$_TABLES['DATABOX_category'],"id=$id");                |
// +---------------------------------------------------------------------------+
// | 引数 $fld:項目
// | 引数 $tbl:テーブル
// | 引数 $where:条件式
// +---------------------------------------------------------------------------+
// | 戻値 nomal:複数データリスト
// +---------------------------------------------------------------------------+
//update20100420
{

    $sql="SELECT {$fld} FROM {$tbl} WHERE {$where}";
    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    $selected="";
    for ($i = 0; $i < $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $selected.= $A[$fld]." ";
    }
    $selcted=rtrim($selected," ");
    return $selected;

}


function DATABOX_savedatas(
    $fld
    ,$tbl
    ,$id
    ,$fldary=""
)
// +---------------------------------------------------------------------------+
// | 機能  複数データ書込
// | 書式 DATABOX_savedatas($fld,$tbl,$id,$fldary)
// +---------------------------------------------------------------------------+
// | 引数 $fld:項目
// | 引数 $tbl:テーブル
// | 引数 $id:
// | 引数 $fldary:データ配列
// +---------------------------------------------------------------------------+
// | 戻値 nomal:
// +---------------------------------------------------------------------------+
//update20100420
{

    global $_TABLES;

    $rt=DB_delete($tbl,"id",$id);

    if (is_array($fldary)){
        for ($i = 1; $i <= count($fldary); $i++) {
            $val=current($fldary);
            $sql="INSERT INTO {$tbl} (";
            $sql.="id, {$fld}";
            $sql.=") VALUES (";
            $sql.="{$id}, {$val}";
            $sql.=")";
            DB_query($sql);
            next($fldary);
        }
    }

    return ;

}
function DATABOX_savecategorydatas(
    $id
    ,$fldary
    ,$pi_name=""
    ,$mydata=""
)
// +---------------------------------------------------------------------------+
// | 機能  複数データ書込(カテゴリ)
// | 書式 DATABOX_savecategorydatas($id,$category,'databox','mydata')
// +---------------------------------------------------------------------------+
// | 引数 $id:
// | 引数 $fldary:データ配列
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:                                                               |
// +---------------------------------------------------------------------------+
//update20100824
{

    global $_TABLES;

    if ($pi_name==""){
        $table_def_category=$_TABLES['DATABOX_def_category'];
        $table_category=$_TABLES['DATABOX_category'];
    }else{
        $table_def_category=$_TABLES[strtoupper($pi_name).'_def_category'];
        $table_category=$_TABLES[strtoupper($pi_name).'_category'];
    }
	
	if  ($mydata==""){
        $rt=DB_delete($table_category,"id",$id);
	}else{
		$categories=COM_applyFilter($_POST['categories']);
		if  ($categories=="" ){
		}else{
            $categories=str_replace("|", ',', $categories);
		    $sql="DELETE FROM {$table_category} WHERE ";
	        $sql .="  id = ".$id ;
	        $sql .="  AND category_id IN (".$categories.")";
		    DB_query($sql);
		}
	}
	
    if (is_array($fldary)){
        for ($i = 1; $i <= count($fldary); $i++) {
            $val=current($fldary);
            $sql="INSERT INTO {$table_category} (".LB;
            $sql.="id, category_id".LB;
            $sql.=") VALUES (".LB;
            $sql.="{$id}, {$val}".LB;
            $sql.=")".LB;
            DB_query($sql);
            /////parent_id が0になるまでinsert
            $parent_id=DB_getItem( $table_def_category   ,"parent_id","category_id={$val}");
            while ($parent_id<>0) {
                $category_id=$parent_id;
                $seq=DB_getItem( $table_category   ,"seq","id={$id} AND category_id={$category_id} ");
                if (is_null($seq)) {
                    $sql="INSERT INTO {$table_category} (".LB;
                    $sql.="id, category_id".LB;
                    $sql.=") VALUES (".LB;
                    $sql.="{$id}, {$category_id}".LB;
                    $sql.=")".LB;
                    DB_query($sql);
                }
                $parent_id=DB_getItem( $table_def_category   ,"parent_id","category_id={$category_id}");
            }
            /////
            next($fldary);
        }
    }

    return ;

}
//==============================================================================
function DATABOX_chkuser(
        $group_id
        ,$owner_id
        ,$adminrights="databox.admin"
)
// +---------------------------------------------------------------------------+
// | 機能  ユーザチェック
// | 書式 $chk_user=DATABOX_chkuser($group_id,$owner_id,"databox.admin")
// +---------------------------------------------------------------------------+
// | 引数 $group_id
// | 引数 $owner_id
// | 引数 $adminrights　databox.admin
// +---------------------------------------------------------------------------+
// | 戻値
// |    0 guest
// |   10 guest and allusers
// | 1000 user
// | 1010 group　(owner 含む)
// | 1011 owner
// | 1100 admin
// | 1110 group and admin
// | 1101 owner and admin
// | 1111 group owner and admin
// +---------------------------------------------------------------------------+
//update20101004 group_id
{
    global $_USER;

    $chk_user=0;
    if (isset($_USER['uid']) && $_USER['uid'] > 1) {
        $chk_user=1000;//ログインユーザ
    }
    if ( SEC_hasRights($adminrights)) {
        $chk_user=$chk_user+100;//admin
    }
    //group に属している人
    $usergroups=SEC_getUserGroups();

    if ( in_array($group_id, $usergroups)){
        $chk_user=$chk_user+10;//group
    }

    if ($owner_id===$_USER['uid']){
        $chk_user=$chk_user+1;//owner
    }

    return $chk_user;
}



function DATABOX_getadditiondef(
    $pi_name=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目定義読込
// | 書式 DATABOX_getadditiondef($pi_name)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目定義
// +---------------------------------------------------------------------------+
{
    global $_TABLES;
    global $_CONF;
    
    if ($pi_name==""){
        $table=$_TABLES['DATABOX_def_field'];
        $table2=$_TABLES['DATABOX_mst'];
    }else{
        $table=$_TABLES[strtoupper($pi_name).'_def_field'];
        $table2=$_TABLES[strtoupper($pi_name).'_mst'];
    }

    $rt=array();
	$fieldgroupno_old="";
	
    $sql="SELECT ";
    $sql.= " field_id ";
    $sql.= ",name ";
    $sql.= ",description";
    $sql.= ",type";
    //$sql.= ",fieldgroup_id AS group_id";
    $sql.= ",selection";
    $sql.= ",selectlist";
    $sql.= ",checkrequried";
    $sql.= ",size";
    $sql.= ",maxlength";
    $sql.= ",rows";
    $sql.= ",br";
    $sql.= ",templatesetvar";

    $sql.= ",allow_display";
    $sql.= ",allow_edit";

    $sql.= ",textcheck";
    $sql.= ",textconv";
    $sql.= ",searchtarget";
    
    $sql.= ",initial_value";
    $sql.= ",range_start";
    $sql.= ",range_end";
    $sql.= ",dfid";
    $sql.= ",description2";
    $sql.= ",fieldgroupno";
	
    $sql.=" FROM";
    $sql.=" {$table}  AS m";
    $sql.= " ORDER BY orderno,field_id";

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);
    for ($i = 1; $i <= $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $field_id = COM_stripslashes($A['field_id']);
        $rt[$field_id]['name'] = COM_stripslashes($A['name']);
        $rt[$field_id]['checkrequried'] = COM_stripslashes($A['checkrequried']);
        $rt[$field_id]['description'] = COM_stripslashes($A['description']);
        $rt[$field_id]['allow_display'] = COM_stripslashes($A['allow_display']);
        $rt[$field_id]['allow_edit'] = COM_stripslashes($A['allow_edit']);
        $rt[$field_id]['textcheck'] = COM_stripslashes($A['textcheck']);
        $rt[$field_id]['textconv'] = COM_stripslashes($A['textconv']);
        $rt[$field_id]['searchtarget'] = COM_stripslashes($A['searchtarget']);
        
        $rt[$field_id]['type'] = COM_stripslashes($A['type']);
        //$rt[$field_id]['group_id'] = COM_stripslashes($A['group_id']);
        $rt[$field_id]['selection'] = COM_stripslashes($A['selection']);

        $rt[$field_id]['size'] = COM_stripslashes($A['size']);
        if ($rt[$field_id]['size']==0){
            $rt[$field_id]['size']=60;
        }
        $rt[$field_id]['maxlength'] = COM_stripslashes($A['maxlength']);
        if ($rt[$field_id]['maxlength']==0){
            $rt[$field_id]['maxlength']=160;
        }

        $rt[$field_id]['rows'] = COM_stripslashes($A['rows']);
        if ($rt[$field_id]['rows']==0){
            $rt[$field_id]['rows']=2;
        }
        $rt[$field_id]['br'] = COM_stripslashes($A['br']);

        $rt[$field_id]['selectlist'] = COM_stripslashes($A['selectlist']);
        $rt[$field_id]['templatesetvar'] = COM_stripslashes($A['templatesetvar']);

        $selection= COM_stripslashes($A['selection']);
        $rt[$field_id]['selectionary']=DATABOX_getselectionary($selection);

        $rt[$field_id]['initial_value']= COM_stripslashes($A['initial_value']);
        $rt[$field_id]['range_start']= COM_stripslashes($A['range_start']);
        $rt[$field_id]['range_end']= COM_stripslashes($A['range_end']);
        $dfid= COM_stripslashes($A['dfid']);
        $format = DB_getItem($_TABLES['dateformats'], "format", "dfid = $dfid");
        if( empty( $format )) {
            $rt[$field_id]['format'] = $_CONF['date'];
        }else{
            $rt[$field_id]['format'] = $format;
        }

        $rt[$field_id]['description2']= COM_stripslashes($A['description2']);
        $fieldgroupno=COM_stripslashes($A['fieldgroupno']);
        if  ($fieldgroupno_old==$fieldgroupno){
		}else{
            $fieldgroup_name=DB_getItem($table2,"value","kind='fieldgroup' AND no=".$fieldgroupno);
            $fieldgroupno_old=$fieldgroupno;
        }
        $rt[$field_id]['fieldgroup_name']=$fieldgroup_name;
        $rt[$field_id]['fieldgroupno']= $fieldgroupno;

    }
    return $rt;

}
function DATABOX_getselectionary(
    $selection
)
{
    $selectionary=array();
    $ary=array();
    $ary=split("\r" , $selection);

    $j=0;
    for ($ii = 0; $ii < count($ary); $ii++) {
        if ($ary[$ii]<>""){
            $selectionary[$j]=$ary[$ii];
            $j=$j+1;
        }
    }
    return $selectionary;

}

// +---------------------------------------------------------------------------+
// | 機能  検索用引数作成
// | 書式 DATABOX_getadditionsearchdef($pi_name)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 配列
// |    $rt["sql1"]: ",{$_TABLES['DATABOX_addition']} AS ad1"
// |    $rt["sql2"]: " AND t1.id=ad1.id AND ad1.field_id=1 "
// |    $rt["columns"][]: 'ad1.value '
// +---------------------------------------------------------------------------+
function DATABOX_getadditionsearchdef(
    $pi_name=""
)
{
    global $_TABLES;
    
    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];
    $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];
    
    if ($box_conf['additionsearch']==0){
        return;
    }
    $lmt=$box_conf['additionsearch'];
    
    $retval=array();

    $rt=array();
    $sql3="";
    $columns=array();

    $sql=LB;
    $sql.="SELECT ";
    $sql.= " field_id ";
    
    $sql.=" FROM";
    $sql.=" {$table_def_field}  AS m";

    // 表示する項目のみ
    $sql.=" WHERE";
    $sql.=" allow_display='0'";
    //0: 一行テキストフィールド
    //1: 複数行テキストフィールド
    //20:HTML  10:TinyMCE 19:CKEditor
    //15:数値  21:通貨
    $sql.=" AND type IN (0,1,10,19,20,15,21)";
    
    //検索対象にする=はい
    $sql.=" AND searchtarget='1'";
    
    $sql.=" ORDER  BY  orderno,field_id";
    $sql.=" LIMIT 0 ,".$lmt;

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    for ($i = 1; $i <= $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $field_id = COM_stripslashes($A['field_id']);
        $type = COM_stripslashes($A['type']);
        $selection= COM_stripslashes($A['selection']);
        $selectionary=DATABOX_getselectionary($selection);

        $columns[]= "ad{$field_id}.value ";

        $sql3 .= " JOIN {$table_addition} AS ad{$field_id}";
        $sql3 .= " ON t1.id=ad{$field_id}.id ";
        $sql3 .= " AND ad{$field_id}.field_id={$field_id} ".LB;
        
    }

    $rt["sql3"]=$sql3;
    $rt["columns"]=$columns;
    
    return $rt;

}



function DATABOX_getadditiondatas(
    $id
    ,$pi_name=""
    )
// +---------------------------------------------------------------------------+
// | 機能  追加項目読込
// | 書式 DATABOX_getadditiondatas($id)
// | $array=DATABOX_getaddtuindatas($id);
// +---------------------------------------------------------------------------+
// | 引数 $id:
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目リスト                                                 |
// +---------------------------------------------------------------------------+
{
    global $_TABLES;

    if ($pi_name==""){
        $table_addition=$_TABLES['DATABOX_addition'];
        $table_def_field=$_TABLES['DATABOX_def_field'];
    }else{
        $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];
        $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];
    }

    $rt=array();
    
    $sql="SELECT ";
    $sql.=" m.field_id";
    $sql.=" ,m.fieldgroupno";
    if ($id==0){
        $sql.=",initial_value AS value  ";
    }else{
        $sql.=", ( SELECT VALUE ";
        $sql.=" FROM";
        $sql.=" {$table_addition}  AS t";
        $sql.=" where";
        $sql.=" t.id={$id}";
        $sql.=" AND t.field_id=m.field_id";
        $sql.=" ) AS value ";
    }
    $sql.=" FROM";
    $sql.=" {$table_def_field}  AS m";
    $sql.=" order by m.fieldgroupno,m.orderno ,m.field_id";
    
    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    for ($i = 1; $i <= $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $field_id=$A['field_id'];
        $value=$A['value'];
        $rt[$field_id]=COM_stripslashes($value);
    }
    return $rt;

}


function DATABOX_getaddtionfieldsEdit(
    $fields
    ,$addition_def
    ,$templates
    ,$chk_user
    ,$pi_name=""
    ,$fields_fnm=""
    ,$fields_del=""
    ,$fieldset_id=0
    ,$fields_date=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目入力用HTML作成
// | 書式 DATABOX_getaddtionfieldsEdit
//          ($additionfields,$addition_def,$templates,$chk_user)
// +---------------------------------------------------------------------------+
// | 引数 $fields:
// | 引数 $addition_def:
// | 引数 $templates:
// | 引数 $chk_user:
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $fields_fnm:
// | 引数 $fields_del:
// | 引数 $fieldset_id:
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目入力用HTML　                                           |
// +---------------------------------------------------------------------------+
// 20101206 日付
{
    global $_TABLES;
    global $_SCRIPTS;
    global $_CONF;
    global $MESSAGE;
	
    if ($pi_name==""){
        $pi_name="databox";
    }
    $lang_admin_x="LANG_".strtoupper($pi_name)."_ADMIN";
    $lang_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    $lang_noyes="LANG_".strtoupper($pi_name)."_NOYES";
    $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    global $$lang_admin;
    global $$lang_noyes;
    $lang_admin=$$lang_admin;
    $lang_noyes=$$lang_noyes;
    
    $langCode = COM_getLangIso639Code();
    $toolTip  = $MESSAGE[118];
    $imgUrl   = $_CONF['site_url'] . '/images/calendar.png';
    
    $fieldsetfields=DATABOX_getfields($pi_name, $fieldset_id);
    
    $retval = '';

    if (is_array($fields)){
            $S = $fields;
    }else{
        if( !empty( $selected ))    {
            $S = explode( ' ', $fields );
        }else {
            $S = array();
        }
    }
    $fieldgroup_old="";
    foreach( $S as $fid => $fvalue ){
        $name=$addition_def[$fid]['name'];
        $checkrequried=$addition_def[$fid]['checkrequried'];
        $type=$addition_def[$fid]['type'];
        $selectionary=$addition_def[$fid]['selectionary'];
        $selectlist=$addition_def[$fid]['selectlist'];

        $size=$addition_def[$fid]['size'];
        $maxlength=$addition_def[$fid]['maxlength'];
        $rows=$addition_def[$fid]['rows'];
        $br=$addition_def[$fid]['br'];

        $templatesetvar=$addition_def[$fid]['templatesetvar'];

        $description=$addition_def[$fid]['description'];
        $description2=$addition_def[$fid]['description2'];
        $fieldgroup_name=$addition_def[$fid]['fieldgroup_name'];
        $chk=false;
        $disabled="disabled";
        
        if (in_array($fid,$fieldsetfields )) {
            
            if ($chk_user===9999){
                $chk=true;
                $disabled="";
            }else{
                //編集可能
                if  ($addition_def[$fid]['allow_edit']==='0') {
                    $chk=true;
                    $disabled="";
                //グループとadmin権のある人のみ表示
                }else if ($addition_def[$fid]['allow_edit']==='2'){
                    if  ($chk_user>=1010) {
                        $chk=true;
                        $disabled="";
                    }
                //所有者とadmin権のある人のみ
                }else if ($addition_def[$fid]['allow_edit']==='3'){
                    if  ($chk_user>=1011) {
                        $chk=true;
                        $disabled="";
                    }
                //編集不可表示のみ
                }else if ($addition_def[$fid]['allow_edit']==='4'){
                    $chk=true;
                }
            }
        }
    
        if ($chk) {

            $fieldhtml="";
            switch ($type) {
                case 12:// 図
                        $fvalue_fnm=$fields_fnm[$fid];
                        $fvalue_del=$fields_del[$fid];
                        $fieldhtml .=DATABOX_figurehtml12
                            ($fid,$fvalue,$fvalue_del,$size,$maxlength);
                    break;
                case 11:// 図
                        $fvalue_fnm=$fields_fnm[$fid];
                        $fvalue_del=$fields_del[$fid];
                        $fieldhtml .=DATABOX_figurehtml
                            ($fid,$fvalue,$fvalue_del);
                    break;
                case 13:// 添付ファイル
                        $fvalue_fnm=$fields_fnm[$fid];
                        $fvalue_del=$fields_del[$fid];
                        $fieldhtml .=DATABOX_filehtml
                            ($pi_name,$fid,$fvalue,$fvalue_del);
                    break;


                case 1:// 複数行テキストフィールド(HTML OK)
                case 10:// 複数行テキストフィールド(HTML NG)
                case 20:// 複数行テキストフィールド(HTML OK  TinyMCE)//@@@@@
                case 19:// 複数行テキストフィールド(HTML OK  CKEditor)//@@@@@
                     if ($disabled===""){
                        $cntname="afieldcnt".$fid;
                        $fieldhtml .= "<textarea ";
                        $fieldhtml .= " name=\"afield[".$fid."]\" ";
                        $fieldhtml .= " style=\"width:95%\"";
                        $fieldhtml .= " rows=\"".$rows."\"";
                        $fieldhtml .= " id=\"DATABOX_afield".$fid."\"";
                        $fieldhtml .= " ".$disabled." ";
                        $fieldhtml .= " onKeyup=\"var n=".$maxlength;
                        $fieldhtml .="-this.value.length;var ";
                        $fieldhtml .="s=document.getElementById";
                        $fieldhtml .="('".$cntname."');s.innerHTML='('+n+')';\"";
                        if  ($type==20){
                            $fieldhtml .= " class=\"tinymce_enabled\"";
                        }
                        if  ($type==19){
                            $fieldhtml .= " class=\"wide\"";
                        }
                        $fieldhtml .= ">";
                        $fieldhtml .= $fvalue;
                        $fieldhtml .= "</textarea>";
                        $fieldhtml .="<br ".XHTML."><strong>";
                        $fieldhtml .="<span id='".$cntname."'></span></strong>";
                    }else{
                        $fieldhtml .=$fvalue;
                    }
                    break;
                case 2://いいえ/はい 
                    if ($disabled===""){
                        $fieldhtml=DATABOX_getradiolist
                            ($lang_noyes,"afield[".$fid."]",$fvalue,$br ,$disabled);
                    }else{
                        $fieldhtml= $lang_noyes[$fvalue];
                    }
                    break;
                case 7://オプションリスト（選択肢）
                case 14://マルチセレクトリスト（選択肢）
                    if ($disabled===""){
                        $fieldhtml=DATABOX_getoptionlistary($selectionary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type,$size);
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 24://チェックボックス（選択肢）
                    if ($disabled===""){
                        $fieldhtml=DATABOX_getcheckboxary($selectionary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type,$size);
					
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                 case 25://チェックボックス（マスター）
                    if ($disabled===""){
                        $mstary=DATABOX_getAry("selectlist_mst",0,$selectlist,$pi_name);
                        $fieldhtml=DATABOX_getcheckboxary($mstary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type,$size);
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 9://オプションリスト（マスター）
                    if ($disabled===""){
                       $fieldhtml=DATABOX_getoptionlist("selectlist_mst",$fvalue,0,$pi_name,"",0,$selectlist,"afield[".$fid."]");
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 8://ラジオボタンリスト（選択肢）
                    if ($disabled===""){
                        $fieldhtml=DATABOX_getradiolist ($selectionary,"afield[".$fid."]",$fvalue,$br,$disabled,$type);
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 16://ラジオボタンリスト（マスター）//@@@@@
                    if ($disabled===""){
                        $mstary=DATABOX_getAry("selectlist_mst",0,$selectlist,$pi_name);
                        $fieldhtml=DATABOX_getradiolist ($mstary,"afield[".$fid."]",$fvalue,$br,$disabled,$type);
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 18://マルチセレクトリスト（マスター）//@@@@@
                    if ($disabled===""){
                        $mstary=DATABOX_getAry("selectlist_mst",0,$selectlist,$pi_name);
                        $fieldhtml=DATABOX_getoptionlistary($mstary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type,$size);
                    }else{
                        $selectionary[$fvalue];
                    }
                    break;
                case 3://日付（date picker対応）
                if ($disabled===""){
                        //$filedname="afield[".$fid."]";
                        $filedname="afield".$fid;
                    
                        $_SCRIPTS->setJavaScript(
                        "jQuery(function () {"
                            . "  geeklog.datepicker.set('{$filedname}', '{$langCode}', '{$toolTip}', '{$imgUrl}');"
                            . "});", TRUE, TRUE
                            );
                        if  (is_array($fields_date)){
                            $fieldhtml.=DATABOX_datetimeedit_R($fields_date[$fid],$lang_admin_x,$filedname);
                        }else{
                            $fieldhtml.=DATABOX_datetimeedit($fvalue,$lang_admin_x,$filedname,"date");
                        }
                        $wk =  " <input ";
                        $wk .= " type=\"hidden\"";
                        $wk .= " name=\"afield[".$fid."]\" ";
                        $wk .= " value=\"".$fvalue."\"";
                        $wk .= " ".XHTML.">";
                        $fieldhtml.=$wk;
                    }else{
                        $fieldhtml .=$fvalue;
                    }
                    break;
                case 4://日時（datetime picker対応）
                    if ($disabled===""){
                        //$filedname="afield[".$fid."]";
                        $filedname="afield".$fid;
                    
                        $_SCRIPTS->setJavaScript(
                        "jQuery(function () {"
                            . "  geeklog.hour_mode = {$_CONF['hour_mode']};"
                            . "  geeklog.datetimepicker.set('{$filedname}', '{$langCode}', '{$toolTip}', '{$imgUrl}');"
                            . "});", TRUE, TRUE
                            );
					
                        if  (is_array($fields_date)){
                            $fieldhtml.=DATABOX_datetimeedit_R($fields_date[$fid],$lang_admin_x,$filedname);
                        }else{
                            $fieldhtml.=DATABOX_datetimeedit($fvalue,$lang_admin_x,$filedname,"datetime");
                        }
                        $wk =  " <input ";
                        $wk .= " type=\"hidden\"";
                        $wk .= " name=\"afield[".$fid."]\" ";
                        $wk .= " value=\"".$fvalue."\"";
                        $wk .= " ".XHTML.">";
                        $fieldhtml.=$wk;
                    }else{
                        $fieldhtml .=$fvalue;
                    }
                    break;
                case 26://時刻（UI time picker対応）
                    if ($disabled===""){
                         $fvalue_time=DATABOX_getTimeformat($fvalue);
                         $filedname="afield".$fid;
                         
                         $wk =  " <input ";
                         $wk .= " type=\"hidden\"";
                         $wk .= " name=\"afield[".$fid."]\" ";
                         $wk .= " value=\"".$fvalue."\"";
                         $wk .= " ".XHTML.">";
                         $fieldhtml.=$wk;

                         $fieldhtml .= "        <input ";
                         $fieldhtml .= " data-uk-timepicker=\"{format:'".$_CONF['hour_mode']."h'";
                         if  ($addition_def[$fid]['range_start']==""){
                         }else{
                             $fieldhtml .= ",start:".$addition_def[$fid]['range_start'] ;
                         }
                         if  ($addition_def[$fid]['range_end']==""){
                         }else{
                             $fieldhtml .= ",end:".$addition_def[$fid]['range_end'] ;
                         }
                         $fieldhtml .= " }\"";
                         $fieldhtml .= " class=\"uk-form-width-small\"";
                         $fieldhtml .= " type=\"text\"";
                         $fieldhtml .= "  id=\"afield".$fid."\"";
                         $fieldhtml .= " size=\"".$size."\"";
                         $fieldhtml .= " maxlength=\"".$maxlength."\" ";
                         $fieldhtml .= " name=\"afield[".$fid."]\" ";
                         $fieldhtml .= " style=\"max-width:95%\"";
                         $fieldhtml .= " value=\"". $fvalue_time."\"";
                         $fieldhtml .= " ".XHTML.">";
                     }else{
                        $fieldhtml .=$fvalue;
                     }
                     break;
                case 22://日付
                if ($disabled===""){
                        if  ($fvalue=="")  {
                            $fvalue_date="";
                        }else{
                            $fvalue_date=date( $box_conf['dateformat'], $fvalue );
                        }
                        $filedname="afield".$fid;

                        $_SCRIPTS->setJavaScript(
                            "$(function() {
                            $( \"#afield".$fid."\" ).datepicker();
                            });"
                            , TRUE, TRUE
                            );
                        $wk =  " <input ";
                        $wk .= " type=\"hidden\"";
                        $wk .= " name=\"afield[".$fid."]\" ";
                        $wk .= " value=\"".$fvalue."\"";
                        $wk .= " ".XHTML.">";
                        $fieldhtml.=$wk;
                        
                        $fieldhtml .= "        <input ";
                        $fieldhtml .= " type=\"text\"";
                        $fieldhtml .= "  id=\"afield".$fid."\"";
                        $fieldhtml .= " size=\"".$size."\"";
                        $fieldhtml .= " maxlength=\"".$maxlength."\" ";
                        $fieldhtml .= " name=\"afield[".$fid."]\" ";
                        $fieldhtml .= " style=\"max-width:95%\"";
                        $fieldhtml .= " value=\"".$fvalue_date."\"";
                        $fieldhtml .= " ".XHTML.">";
                    }else{
                        $fieldhtml .=$fvalue;
                    }
                    break;
                case 23://日付
                    if ($disabled===""){
                        if  ($fvalue=="")  {
                            $fvalue_date="";
                        }else{
                            $fvalue_date=date( $box_conf['dateformat'], $fvalue );
                        }
                        $filedname="afield".$fid;
                        
                        $wk =  " <input ";
                        $wk .= " type=\"hidden\"";
                        $wk .= " name=\"afield[".$fid."]\" ";
                        $wk .= " value=\"".$fvalue."\"";
                        $wk .= " ".XHTML.">";
                        $fieldhtml.=$wk;

                        $fieldhtml .= "        <input ";
                        $fieldhtml .= " data-uk-datepicker=\"{format:'YYYY/MM/DD'}\"";
                        $fieldhtml .= " type=\"text\"";
                        $fieldhtml .= "  id=\"afield".$fid."\"";
                        $fieldhtml .= " size=\"".$size."\"";
                        $fieldhtml .= " maxlength=\"".$maxlength."\" ";
                        $fieldhtml .= " name=\"afield[".$fid."]\" ";
                        $fieldhtml .= " style=\"max-width:95%\"";
                        $fieldhtml .= " value=\"". $fvalue_date."\"";
                        $fieldhtml .= " ".XHTML.">";
                    }else{
                        $fieldhtml .=$fvalue;
                    }
                    break;
                case 17://フラグ
                    if ($disabled===""){
                        $wk =" <input ";
                        $wk .= " type=\"checkbox\"";
                        $wk .= " name=\"afield[".$fid."]\" ";
                        $wk .= " value=\"1\"";
                        if  ($fvalue==1) {
                            $wk .= " checked=checked";
                        }
                        $wk .= " ".XHTML.">";
                        $fieldhtml=$wk;
                    }else{
                        $fieldhtml= $fvalue;
                    }
                    break;

                case 5://メールアドレス';
                case 6://url';
                case 0:// 一行テキストフィールド
                default:
                    if ($disabled===""){
                        $fieldhtml .= "        <input ";
                        $fieldhtml .= " type=\"text\"";
                        $fieldhtml .= " size=\"".$size."\"";
                        $fieldhtml .= " maxlength=\"".$maxlength."\" ";
                        $fieldhtml .= " name=\"afield[".$fid."]\" ";
                        $fieldhtml .= " style=\"max-width:95%\"";
                        $fieldhtml .= " value=\"".$fvalue."\"";
                        $fieldhtml .= " ".XHTML.">";
                    }else{
                        $fieldhtml .=$fvalue;
                    }
            }


            if ($checkrequried==1){//必須
                $must= $lang_admin['must'];
            }else{
                $must="";
            }
            $templates->set_var('lang_must_additionfield', $must);

            $templates->set_var('additionfield_name', $name);
            $templates->set_var('additionfield_templatesetvar', "&#123;".$templatesetvar."&#125;");

            $addisionfields .= $fieldhtml;
            $templates->set_var('additionfield_value', $fieldhtml);

            $templates->set_var('additionfield_description', $description);
            $templates->set_var('additionfield_description2', $description2);
            $templates->set_var('fieldgroup_name', $fieldgroup_name);
			if  ($fieldgroup_old==$fieldgroup_name){
                $templates->set_var('fieldgroup', "");
            }else{
                $templates->set_var('fieldgroup', $fieldgroup_name);
                $fieldgroup_old=$fieldgroup_name;
            }
            $templates->set_var('additionfield'.$fid, $fieldhtml);
            $templates->set_var($templatesetvar, $fieldhtml);

            //=====
            $templates->parse ('col_var', 'col', true);
            $templates->parse ('additionfields', 'row', true);

            $templates->set_var ('col_var', '');
        }
    }

    return $retval;
}

//
function DATABOX_figurehtml(
    $fid
    ,$fvalue
    ,$fvalue_del
)
{

    global $LANG04;

    //
    $retval .= "        <input ";
    $retval .= " type=\"hidden\"";
    $retval .= " name=\"afield[".$fid."]\" ";
    $retval .= " value=\"".$fvalue."\"";
    $retval .= " ".XHTML.">".LB;

    $retval .="<input type=\"file\" dir=\"ltr\"";

    $retval .= " id=\"afield_fnm[".$fid."]\" ";
    $retval .= " name=\"afield_fnm[".$fid."]\" ";

//    $retval .= " value=\"".$fvalue_fnm."\"";
    $retval .="size=\"30\"";
    $retval .= " ".XHTML.">".LB;

    if ($fvalue<>""){
        $retval .= '<br' . XHTML . '>' ;
        $retval .= '<br' . XHTML . '>' ;
        $retval .="<img src=\"data:image/jpg;base64,";
        $retval .="{$fvalue}\"";
        $retval .="width=100 height=100";
        $retval .=" ".XHTML.">".LB;
        //
        $retval .= '<br' . XHTML . '>' ;
        $retval .= '<br' . XHTML . '>' ;
        $retval .= $LANG04[79];
        $retval .= "<input type=\"checkbox\"";
        $retval .= " name=\"afield_del[".$fid."]\" ";
        if ($fvalue_del){
            $retval .= " checked ";
        }
        $retval .= " ".XHTML.">".LB;

    }

//  $photo_x = USER_getPhoto ($uid, $photo, $email, -1);

    return $retval;
}
function DATABOX_filehtml(
    $pi_name
    ,$fid
    ,$fvalue
    ,$fvalue_del
)
{

    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    //
    $retval .= "        <input ";
    $retval .= " type=\"hidden\"";
    $retval .= " name=\"afield[".$fid."]\" ";
    $retval .= " value=\"".$fvalue."\"";
    $retval .= " ".XHTML.">".LB;
    
    $retval .="<input type=\"file\" dir=\"ltr\"";
    $retval .= " id=\"afield_fnm[".$fid."]\" ";
    $retval .= " name=\"afield_fnm[".$fid."]\" ";
    $retval .="size=\"30\"";
    $retval .= " ".XHTML.">".LB;

    if ($fvalue<>""){

        $retval .="{$fvalue}";
        $retval .= '<br' . XHTML . '>' ;
        $retval .= $lang_box_admin['deletemsg_check'];
        $retval .= "<input type=\"checkbox\"";
        $retval .= " name=\"afield_del[".$fid."]\" ";
        if ($fvalue_del){
            $retval .= " checked ";
        }
        $retval .= " ".XHTML.">".LB;

    }


    return $retval;
}


function DATABOX_figurehtml12(
    $fid
    ,$fvalue
    ,$fvalue_del
    ,$size
    ,$maxlength
)
{

    global $LANG04;
    
    $a=explode("|",$fvalue);

    //
    $retval .= "        <input ";
    $retval .= " type=\"hidden\"";
    $retval .= " name=\"afield[".$fid."]\" ";
    $retval .= " value=\"".$fvalue."\"";
    $retval .= " ".XHTML.">".LB;

    $retval .="<input type=\"file\" dir=\"ltr\"";
    $retval .= " id=\"afield_fnm[".$fid."]\" ";
    $retval .= " name=\"afield_fnm[".$fid."]\" ";
    $retval .="size=\"30\"";
    $retval .= " ".XHTML.">".LB;
    
    $retval .= "<input ";
    $retval .= " type=\"text\"";
    $retval .= " size=\"".$size."\"";
    $retval .= " maxlength=\"".$maxlength."\" ";
    $retval .= " name=\"afield_alt[".$fid."]\" ";
    $retval .= " style=\"max-width:95%\"";
    $retval .= " value=\"".$a[1]."\"";
    $retval .= " ".XHTML.">";

    if ($fvalue<>""){

        $retval .="{$fvalue}";
        $retval .= '<br' . XHTML . '>' ;
        $retval .= $LANG04[79];
        $retval .= "<input type=\"checkbox\"";
        $retval .= " name=\"afield_del[".$fid."]\" ";
        if ($fvalue_del){
            $retval .= " checked ";
        }
        $retval .= " ".XHTML.">".LB;

    }


    return $retval;
}

function DATABOX_imagehtml(
    $name
    ,$fvalue
    ,$fvalue_del
)
{

    global $LANG04;

    //
    $retval .= "        <input ";
    $retval .= " type=\"hidden\"";
    $retval .= " name=\"".$name."\" ";
    $retval .= " value=\"".$fvalue."\"";
    $retval .= " ".XHTML.">".LB;
    
    if ($fvalue==""){

        $retval .="<input type=\"file\" dir=\"ltr\"";

        $retval .= " id=\"".$name."\" ";
        $retval .= " name=\"".$name."\" ";

        $retval .="size=\"30\"";
        $retval .= " ".XHTML.">".LB;

    }else{

        $retval .="{$fvalue}";
        $retval .= '<br' . XHTML . '>' ;
        $retval .= $LANG04[79];
        $retval .= "<input type=\"checkbox\"";
        $retval .= " name=\"".$name."_del\" ";
        if ($fvalue_del){
            $retval .= " checked ";
        }
        $retval .= " ".XHTML.">".LB;

    }


    return $retval;
}

function DATABOX_getaddtionfieldsText(
    $fields
    ,$addition_def
    ,$chk_user
    ,$pi_name
    ,$fieldset_id
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目TEXT作成
// | 書式 DATABOX_getaddtionfieldsText
// |        ($additionfields,$addition_def,$chk_user)
// +---------------------------------------------------------------------------+
// | 引数 $fields:
// | 引数 $addition_def:
// | 引数 $chk_user:
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $fieldset_id
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目text
// +---------------------------------------------------------------------------+
//
{
    global $_CONF;
    
    $lang_noyes="LANG_".strtoupper($pi_name)."_NOYES";

    global $$lang_noyes;
    $lang_noyes=$$lang_noyes;
    
    $lang_box="LANG_".strtoupper($pi_name);
    global $$lang_box;
    $lang_box=$$lang_box;
    $separater=$lang_box['field_separater'];
    
    $templatesetvar=$addition_def[$fid]['templatesetvar'];

    $retval = '';

    if (is_array($fields)){
            $S = $fields;
    }else{
        if( !empty( $selected ))    {
            $S = explode( ' ', $fields );
        }else {
            $S = array();
        }
    }

    foreach( $S as $fid => $fvalue ){

        $name=$addition_def[$fid]['name'];
        $checkrequried=$addition_def[$fid]['checkrequried'];
        $type=$addition_def[$fid]['type'];
        $selectionary=$addition_def[$fid]['selectionary'];
        $selectlist=$addition_def[$fid]['selectlist'];

        $size=$addition_def[$fid]['size'];
        $maxlength=$addition_def[$fid]['maxlength'];
        $rows=$addition_def[$fid]['rows'];
        $br=$addition_def[$fid]['br'];

        $fieldsetfields=DATABOX_getfields($pi_name, $fieldset_id);

        $chk=false;
        $disabled="disabled";
        if ($chk_user===9999){
            $chk=true;
            $disabled="";
        }else{
            //編集可能
            if  ($addition_def[$fid]['allow_edit']==='0') {
                $chk=true;
                $disabled="";
            //グループとadmin権のある人のみ表示
            }else if ($addition_def[$fid]['allow_edit']==='2'){
                if  ($chk_user>=1010) {
                    $chk=true;
                    $disabled="";
                }
            //所有者とadmin権のある人のみ
            }else if ($addition_def[$fid]['allow_edit']==='3'){
                if  ($chk_user>=1011) {
                    $chk=true;
                    $disabled="";
                }
            //編集不可表示のみ
            }else if ($addition_def[$fid]['allow_edit']==='4'){
                $chk=true;
            }
        }

        if (in_array($fid,$fieldsetfields )) {
            if ($chk) {
                $retval.=$name.":";

                switch ($type) {
                    case 11://図
                        $retval .= "";
                        break;
                    case 2://いいえ/はい
                        $retval .= $lang_noyes[$fvalue];
                        break;
                    case 7://オプションリスト（選択肢）
                    case 8://オプションリスト（選択肢）
                        $retval .= $selectionary[$fvalue];
                        break;
                    case 14://マルチセレクトリスト（選択肢）
                    case 24://チェックボックス（選択肢）
                        $wkary=explode(":",$fvalue);
                        $wk="";
                        for( $x = 0; $x < sizeof( $wkary ); $x++ )            {
                            $wk.=$selectionary[$wkary[$x]].$separater;
                        }
                        $retval .= rtrim($wk,$separater);
                        break;
                    case 9://オプションリスト（マスター）
                    case 16://ラジオボタンリスト（マスター）
                        $mstitems=DATABOX_getMstItems($selectlist,$fvalue,$pi_name);
                        $retval .= $mstitems['value'];
                        break;
                    case 18://マルチセレクトリスト（マスター）
                    case 25://チェックボックス（マスター）
                        $wkary=explode(":",$fvalue);
                        $wk="";
                        for( $x = 0; $x < sizeof( $wkary ); $x++ )            {
                            $mstitems=DATABOX_getMstItems($selectlist,$wkary[$x],$pi_name);
                            $wk.=$mstitems['value'].$separater;
                        }
                        $retval .= rtrim($wk,$separater);
                        break;
                    case 4://日時
                        $w=COM_getUserDateTimeFormat($fvalue);
                        $retval .= $w[0];
                        break;
                    case 22://日付
                    case 23://日付
                    case 3://日付
                        if  ($fvalue<>""){
                            $retval .=strftime( $_CONF['shortdate'], $fvalue );
                        }
                        break;
                    case 5://メールアドレス　（準備中）';
                    case 6://url　（準備中）';
                    case 0:// 一行テキストフィールド
                    case 1:// 複数行テキストフィールド(HTML OK)
                    case 10:// 複数行テキストフィールド(HTML NG)
                    case 20:// 複数行テキストフィールド(HTML OK  TinyMCE)//@@@@@
                    case 19:// 複数行テキストフィールド(HTML OK  CKEditor)//@@@@@
                    default:
                        $retval .= $fvalue;
                }
                $retval.=LB;
            }
        }
    }
    return $retval;
}



function DATABOX_getaddtionfieldsDisp(
    $fields
    ,$addition_def
    ,$templates
    ,$chk_user
    ,$pi_name
    ,$fieldset_id
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目表示用HTML作成                                              |
// | 書式 DATABOX_getaddtionfieldsDisp
// |        ($additionfields,$addition_def,$templates,$chk_user)
// +---------------------------------------------------------------------------+
// | 引数 $fields:
// | 引数 $addition_def:
// | 引数 $templates:
// | 引数 $chk_user:
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $fieldset_id
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目表示用HTML　                                           |
// +---------------------------------------------------------------------------+
//update 20101012 $chk_user
{
    global $_CONF;
    global $_TABLES;

    $lang_noyes="LANG_".strtoupper($pi_name)."_NOYES";
    global $$lang_noyes;
    $lang_noyes=$$lang_noyes;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;
    
    $lang_box="LANG_".strtoupper($pi_name);
    global $$lang_box;
    $lang_box=$$lang_box;
    $separater=$lang_box['field_separater'];

    
    $noimage="lib/noimage.jpg";
    
    $fieldsetfields=DATABOX_getfields($pi_name, $fieldset_id);
    
    $retval = '';

    if (is_array($fields)){
            $S = $fields;
    }else{
        if( !empty( $selected ))    {
            $S = explode( ' ', $fields );
        }else {
            $S = array();
        }
    }

    foreach( $S as $fid => $fvalue ){


        $name=$addition_def[$fid]['name'];
        $description=$addition_def[$fid]['description'];
        $checkrequried=$addition_def[$fid]['checkrequried'];
        $type=$addition_def[$fid]['type'];
        $selectionary=$addition_def[$fid]['selectionary'];
        $selectlist=$addition_def[$fid]['selectlist'];

        $size=$addition_def[$fid]['size'];
        $maxlength=$addition_def[$fid]['maxlength'];
        $rows=$addition_def[$fid]['rows'];
        $br=$addition_def[$fid]['br'];
        $format=$addition_def[$fid]['format'];

        $templatesetvar=$addition_def[$fid]['templatesetvar'];

        $chk=false;
        if (in_array($fid,$fieldsetfields )) {
            //表示する
            if  ($addition_def[$fid]['allow_display']==='0') {
                $chk=true;
                //ログインユーザのみ
            }else if ($addition_def[$fid]['allow_display']==='1'){
                if  ($chk_user>=1000) {
                    $chk=true;
                }
                //グループとadmin権のある人のみ表示
            }else if ($addition_def[$fid]['allow_display']==='2'){
                if  ($chk_user>=1010) {
                    $chk=true;
                }
                //所有者とadmin権のある人のみ
            }else if ($addition_def[$fid]['allow_display']==='3'){
                if  ($chk_user>=1011) {
                    $chk=true;
                }
                //admin権のある人のみ
            }else if ($addition_def[$fid]['allow_display']==='4'){
                if  ($chk_user>=1100) {
                    $chk=true;
                }
            }
        }
        if ($chk) {
            $fieldhtml="";//外画像の場合ファイル名
            $fieldhtml_tn="";//サムネイルファイル名
            $fieldhtmldisp="";//外画像　画像表示　（準備中）
            $fieldhtmldisp_tn="";//外画像　画像表示　（準備中）
            $fieldhtml_noimage="";
            $fieldhtml_tn_noimage="";
			$fieldhtml_tn_image="";//サムネイル
			$fieldhtml_readfile="";//添付ファイルreadfile
            $mstitems=array();
            $fieldhtml_yes="";
            switch ($type) {
                case 12://画像（外記憶）（準備中）
                    $a=explode("|",$fvalue);
                    $fieldhtml=$a[0];//ファイル名
                    $fieldhtml_noimage=$a[0];
                    //
                    if  ($fvalue===""){
                        $fieldhtml_noimage=$noimage;
                        $fieldhtml_tn_noimage=$noimage;
                    }else{

                        $fieldurl= $_CONF['site_url']   ."/".$box_conf['imgfile_frd'] ;
                        $fieldurl.= $a[0] ;

                        $fieldhtmldisp .="<img ";
                        $fieldhtmldisp .=" src=\"{$fieldurl}\"";
                        $fieldhtmldisp .=" border=\"0\"";
                        $fieldhtmldisp .=" alt=\"{$a[1]}\"";
                        $fieldhtmldisp .=" ".HTML.">";
                    
                        //サムネイル
                        $filename = substr($fvalue, 0, strrpos($fvalue,"."));
                        $thum_filename = $filename . "-small.jpg";
                        $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                    
                        if(file_exists($imgfld_thmb.$thum_filename)) {
                            $fieldhtml_tn=$thum_filename;
                            $fieldhtml_tn_noimage=$thum_filename;
                            //
                            $fieldurl_tn= $_CONF['site_url']   ."/".$box_conf['imgfile_thumb_frd'] ;
                            $fieldurl_tn.= $thum_filename ;
                            //
                            $fieldhtml_tn_image .="<a href=\"{$fieldurl}\" target=\"_blank\">";
                            $fieldhtml_tn_image .="<img ";
                            $fieldhtml_tn_image .=" src=\"{$fieldurl_tn}\"";
                            $fieldhtml_tn_image .=" border=\"0\"";
                            $fieldhtml_tn_image .=" alt=\"{$a[1]}\"";
                            $fieldhtml_tn_image .=" ".XHTML.">";
                            $fieldhtml_tn_image .=" </a>";
                        
                        }else{
                            $fieldhtml_tn_noimage=$noimage;
                        }
                        
                    }

                    break;
                case 13://添付ファイル
                    if  ($fvalue==""){
                    }else{
                        $a=explode("|",$fvalue);
                        $fieldhtml .= $a[1];
                        $fieldhtml_readfile = $a[0];
                    }
                    break;
                case 11://画像（DB記憶)
                    if  ($fvalue==""){
                    }else{
                        $fieldhtml .="<img src=\"data:image/jpg;base64,";
                        $fieldhtml .="{$fvalue}\"".XHTML.">";
                    }
                    break;
                case 2://いいえ/はい
                    $fieldhtml .= $lang_noyes[$fvalue];
                    break;
                case 7://オプションリスト（選択肢）//@@@@@@
                case 8://ラジオボタンリスト（選択肢）//@@@@@
                    $fieldhtml .= $selectionary[$fvalue];
                    break;
                case 14://マルチセレクトリスト（選択肢）
                case 24://チェックボックス（選択肢）
                    $wkary=explode(":",$fvalue);
                    $wk="";
                    for( $x = 0; $x < sizeof( $wkary ); $x++ )            {
                        $wk.=$selectionary[$wkary[$x]].$separater;
                    }
                    $fieldhtml .= rtrim($wk,$separater);
                    break;
                case 10:// 複数行テキストフィールド(HTML NG)
                    $w=DATABOX_nl2br($fvalue);
                    $w=DATABOX_autolink($w);
                    $fieldhtml .= $w;
                    break;
                case 9://オプションリスト（マスター）
                case 16: //ラジオボタン（マスター）
                    $mstitems=DATABOX_getMstItems($selectlist,$fvalue,$pi_name);
                    $fieldhtml .= $mstitems['value'];
                    break;
                case 18://マルチセレクトリスト（マスター）
                case 25://チェックボックス（マスター）
                    $wkary=explode(":",$fvalue);
                    $wk="";
                    $wk_value2="";
                    $wk_disp="";
                    for( $x = 0; $x < sizeof( $wkary ); $x++ )            {
                        $mstitems=DATABOX_getMstItems($selectlist,$wkary[$x],$pi_name);
                        $wk.=$mstitems['value'].$separater;
                        $wk_value2.=$mstitems['value2'].$separater;
                        $wk_disp.=$mstitems['disp'].$separater;
                    }
                    $fieldhtml .= rtrim($wk,$separater);
                    
                    $mstitems['value2'] = rtrim($wk_value2,$separater);
                    $mstitems['disp'] = rtrim($wk_disp,$separater);
                    $mstitems['no'] =$fvalue;
                    break;
                case 4://日時
                    if  ($fvalue<>""){
                        $w=DATABOX_getDateTimeFormat($fvalue,$format);
                        $fieldhtml .= $w[0];
                    }
                    break;
                case 26://時刻
                    if  ($fvalue<>""){
                        $fieldhtml .= DATABOX_getTimeformat($fvalue);
                    }
                    break;
                case 22://日付
                case 23://日付
                case 3://日付
                    if  ($fvalue<>""){
                        $fieldhtml .=strftime( $_CONF['shortdate'], $fvalue );
                    }
                    break;
                case 21://money 通貨;
                    $dc = $_CONF['decimal_count'];
                    $ts = $_CONF['thousand_separator'];
                    $ds = $_CONF['decimal_separator'];
                    $fieldhtml .= number_format( (float)$fvalue, $dc, $ds, $ts );
                    break;
                case 5://メールアドレス　（準備中）';

                case 6://url';
                case 0:// 一行テキストフィールド
                case 1:// 複数行テキストフィールド(HTML OK)
                case 20:// 複数行テキストフィールド(HTML OK  TinyMCE)
                case 19:// 複数行テキストフィールド(HTML OK  CKEditor)
                default:
                    $fieldhtml .= $fvalue;
            }
            $templates->set_var('field_id', $fid);
            $templates->set_var('field_type', $type);
            
            //項目名
            $templates->set_var('additionfield_name', $name);
            $templates->set_var('additionfield'.$fid."_name", $name);
            $templates->set_var($templatesetvar."_name", $name);
            
            //説明
            $templates->set_var('additionfield'.$fid."_description", $description);
            $templates->set_var($templatesetvar."_description", $description);
            
            //登録したテーマ変数名
            $templates->set_var('additionfield_templatesetvar', $templatesetvar);
            
            //
            $templates->set_var('additionfield_value', $fieldhtml);
            $templates->set_var('additionfield'.$fid, $fieldhtml);
            $templates->set_var($templatesetvar, $fieldhtml);
            $templates->set_var('additionfield_fvalue', $fvalue);
            $templates->set_var('additionfield'.$fid."_value", $fvalue);
            $templates->set_var($templatesetvar."_value", $fvalue);
            
            if  ($type==12){
                $templates->set_var('display_a', 'display:');
            }else{
                $templates->set_var('display_a', 'display:none');
            }
            //(a_1)_tn サムネイルファイル名
            $templates->set_var('additionfield_value_tn', $fieldhtml_tn);
            $templates->set_var('additionfield'.$fid."_tn", $fieldhtml_tn);
            $templates->set_var($templatesetvar."_tn", $fieldhtml_tn);
            
            //(a_2)_noimage
            $templates->set_var('additionfield_value_noimage', $fieldhtml_noimage);
            $templates->set_var('additionfield'.$fid."_noimage", $fieldhtml_noimage);
            $templates->set_var($templatesetvar."_noimage", $fieldhtml_noimage);
            
            //(a_3)_tn_image サムネイルイメージ
            $templates->set_var('additionfield_value_tn_image', $fieldhtml_tn_image);
            $templates->set_var('additionfield'.$fid."_tn_image", $fieldhtml_tn_image);
            $templates->set_var($templatesetvar."_tn_image", $fieldhtml_tn_image);
            
            //(a_4)_tn_noimage サムネイルイメージ
            $templates->set_var('additionfield_value_tn_noimage', $fieldhtml_tn_noimage);
            $templates->set_var('additionfield'.$fid."_tn_noimage", $fieldhtml_tn_noimage);
            $templates->set_var($templatesetvar."_tn_noimage", $fieldhtml_tn_noimage);
            
            //(a_5)disp 画像（外記憶）表示
            $templates->set_var('additionfield_value_disp', $fieldhtmldisp);
            $templates->set_var('additionfield'.$fid."_disp", $fieldhtmldisp);
            $templates->set_var($templatesetvar."_disp", $fieldhtmldisp);
            
            if  ( $type==9 OR $type==16 OR $type==18 ){
                $templates->set_var('display_b', 'display:');
            }else{
                $templates->set_var('display_b', 'display:none');
            }
            //(b_1)マスターのvalue2
            $templates->set_var('additionfield_value_mst_value2', $mstitems['value2']);
            $templates->set_var('additionfield'.$fid."_mst_value2", $mstitems['value2']);
            $templates->set_var($templatesetvar."_mst_value2", $mstitems['value2']);
            
            //(b_2)マスターのdisp
            $templates->set_var('additionfield_value_mst_disp', $mstitems['disp']);
            $templates->set_var('additionfield'.$fid."_mst_disp", $mstitems['disp']);
            $templates->set_var($templatesetvar."_mst_disp", $mstitems['disp']);
            
            //(b_3)マスターのno
            $templates->set_var('additionfield_value_mst_no', $mstitems['no']);
            $templates->set_var('additionfield'.$fid."_mst_no", $mstitems['no']);
            $templates->set_var($templatesetvar."_mst_no", $mstitems['no']);
            
            //@@@@@ 準備中　$templates->set_var('additionfield'.$fid."_disptn", $fieldhtmldisp_tn);
            //@@@@@ 準備中　$templates->set_var($templatesetvar."_disptn", $fieldhtmldisp_tn);
			
            if  ( $type==13  ){
                $templates->set_var('display_c', 'display:');
            }else{
                $templates->set_var('display_c', 'display:none');
            }
			//(c)添付ファイルの記憶ファイル名(templatesetvar_(id)_(readfile))
            $templates->set_var('additionfield_value_readfile', $fieldhtml_readfile);
            $templates->set_var('additionfield'.$fid."_readfile", $fieldhtml_readfile);
            $templates->set_var($templatesetvar."_readfile", $fieldhtml_readfile);
			
			//必須チェック(templatesetvar_(id)_(requied))
            $templates->set_var('additionfield'.$fid."_requried", $checkrequried);
            $templates->set_var($templatesetvar."_requried", $checkrequried);
			
            //文言
            $templates->set_var('lang_download', $lang_box['download']);
            $templates->set_var('lang_downloadrequired', $lang_box['downloadrequired']);
            $templates->set_var('lang_display', $lang_box['display']);
            $templates->set_var('lang_displayrequired', $lang_box['displayrequired']);
            
            //=====
            $templates->parse ('col_var', 'col', true);
            $templates->parse ('additionfields', 'row', true);

            $templates->set_var ('col_var', '');

        }
    }


    return $retval;
}

//
function DATABOX_getMstItems(
    $selectlist
    ,$id
    ,$pi_name
//DATABOX_getMstItems($id,$pi_name);
){
    if ($id==""){
        return array();
    }
    global $_TABLES;
    
    $table_mst=$_TABLES[strtoupper($pi_name).'_mst'];

    $sql="SELECT * FROM  {$table_mst} WHERE kind='{$selectlist}' AND no={$id}";
    
    $result = DB_query($sql);
    $numrows = DB_numRows ($result);
    if ($numrows > 0) {
        $A = DB_fetchArray($result, true);
        $A = array_map('stripslashes', $A);
    }else{
        $A=array();
    }
    return $A;
}


function DATABOX_getcategoriesText(
    $id
    ,$group_id=0
    ,$pi_name=""
)
// +---------------------------------------------------------------------------+
// | 機能  カテゴリメール送信用テキスト作成
// | 書式 DATABOX_getcategoriesText
// +---------------------------------------------------------------------------+
// | 引数 $id:
// | 引数 $group_id:
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:カテゴリテキスト
// +---------------------------------------------------------------------------+
//20101116
{
    global $_TABLES;
    global $_CONF;



    if ($pi_name==""){
        $lang_box="LANG_DATABOX";
        $lang_box_admin="LANG_DATABOX_ADMIN";

        $table_def_category=$_TABLES['DATABOX_def_category'];
        $table_def_group=$_TABLES['DATABOX_def_group'];
        $table_category=$_TABLES['DATABOX_category'];

    }else{
        $lang_box="LANG_".strtoupper($pi_name);
        $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";

        $table_def_category=$_TABLES[strtoupper($pi_name).'_def_category'];
        $table_def_group=$_TABLES[strtoupper($pi_name).'_def_group'];
        $table_category=$_TABLES[strtoupper($pi_name).'_category'];
    }

    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    global $$lang_box;
    $lang_box=$$lang_box;

    $retval = '';

    $sql = "SELECT ".LB;
    $sql .= " m1.category_id".LB;
    $sql .= ",m1.name AS category_name".LB;
    $sql .= ",m1.code AS category_code".LB;
    //$sql .= ",m1.orderno";
    $sql .= ",m1.categorygroup_id".LB;
    //$sql .= ",m2.name AS group";
    $sql .= ",m2.code AS group_code".LB;
    $sql .= ",m2.group_id".LB;//@@@@@@

    $sql .= " FROM ".LB;
    $sql .= " {$table_category} AS t".LB;
    $sql .= " , {$table_def_category} AS m1".LB;
    $sql .= " , {$table_def_group} AS m2".LB;
    $sql .= " WHERE ".LB;
    $sql .= " t.id = ".$id.LB;

    if ($group_id<>0){
        $sql .= " AND m1.categorygroup_id=".$group_id.LB;
    }
    $sql .= " AND m1.category_id = t.category_id".LB;
    $sql .= " AND m2.group_id = m1.categorygroup_id".LB;

    $sql .= " ORDER by m1.categorygroup_id, m2.orderno, m1.orderno".LB;

    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );

    $separater=$lang_box['category_separater_text'];

    $categpries="";


    $old_group_id="";
    $old_group_code="";
    $categories_group ="";
    $rt="";
    for( $i = 0; $i < $nrows; $i++ )    {
        $A = DB_fetchArray( $result, true );
        if ($old_group_id<>$A['group_id']){
            if ($categories_group<>""){
                $categories_group=rtrim($categories_group,$separater);
                $rt.=$categories_group.LB;
            }
            $categories_group ="";
            $old_group_id=$A['group_id'];
        }
        $categories.=$A['category_name'].$separater;
        $categories_group.=$A['category_name'].$separater;

    }

    $categories=rtrim($categories,$separater);
    $categories_group=rtrim($categories_group,$separater);
    $rt.=$categories_group.LB;

    $retval.=$lang_box_admin['category'].": ".$categories.LB;

    return $retval;
}

function DATABOX_getcategoriesDisp(
    $id
    ,$templates
    ,$chk_user
    ,$group_id=0
    ,$pi_name=""
)
// +---------------------------------------------------------------------------+
// | 機能  カテゴリHRML作成
// | 書式 DATABOX_getcategoriesDisp
// +---------------------------------------------------------------------------+
// | 引数 $id:
// | 引数 $addition_def:
// | 引数 $templates:
// | 引数 $group_id:
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:カテゴリ表示用HTML
// +---------------------------------------------------------------------------+
//20101129
{
    global $_TABLES;
    global $_CONF;

    if ($pi_name==""){
        $lang_box="LANG_DATABOX";
        $box_conf="_DATABOX_CONF";
        $table_def_category=$_TABLES['DATABOX_def_category'];
        $table_def_group=$_TABLES['DATABOX_def_group'];
        $table_category=$_TABLES['DATABOX_category'];

    }else{
        $lang_box="LANG_".strtoupper($pi_name);
        $box_conf="_".strtoupper($pi_name)."_CONF";
        $table_def_category=$_TABLES[strtoupper($pi_name).'_def_category'];
        $table_def_group=$_TABLES[strtoupper($pi_name).'_def_group'];
        $table_category=$_TABLES[strtoupper($pi_name).'_category'];
    }

    global $$box_conf;
    $box_conf=$$box_conf;

    global $$lang_box;
    $lang_box=$$lang_box;
    
    $sql = "SELECT ";
    $sql .= " group_id".LB;
    $sql .= " ,code".LB;
    $sql .= " FROM ";
    $sql .= " {$table_def_group} ".LB;
    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );
    
    for( $i = 0; $i < $nrows; $i++ )    {
        $A = DB_fetchArray( $result, true );
        $A = array_map('stripslashes', $A);
        $gid=$A['group_id'];
        $gcode=$A['code'];
        $templates->set_var('group_'.$gid, "");
        $templates->set_var('group_'.$gcode, "");
        $templates->set_var('group_'.$gid."_code", "");
        $templates->set_var('group_'.$gcode."_code", "");
                
        $templates->set_var('grouplink_'.$gid, "");
        $templates->set_var('grouplink_'.$gid."_code", "");

        $templates->set_var('grouplink_'.$gcode, "");
        $templates->set_var('grouplink_'.$gcode."_code", "");
    }
    
    
    
    $retval = '';

    $sql = "SELECT ".LB;
    $sql .= " m1.category_id".LB;
    $sql .= ",m1.name AS category_name".LB;
    $sql .= ",m1.code AS category_code".LB;
    //$sql .= ",m1.orderno";
    $sql .= ",m1.categorygroup_id".LB;
    $sql .= ",m1.allow_display".LB;
    //$sql .= ",m2.name AS group";
    $sql .= ",m2.code AS group_code".LB;
    $sql .= ",m2.group_id".LB;//@@@@@@

    $sql .= " FROM ".LB;
    $sql .= " {$table_category} AS t".LB;
    $sql .= " , {$table_def_category} AS m1".LB;
    $sql .= " , {$table_def_group} AS m2".LB;
    $sql .= " WHERE ".LB;
    $sql .= " t.id = ".$id.LB;

    if ($group_id<>0){
        $sql .= " AND m1.categorygroup_id=".$group_id.LB;
    }
    $sql .= " AND m1.category_id = t.category_id".LB;
    $sql .= " AND m2.group_id = m1.categorygroup_id".LB;

    $sql .= " ORDER by m1.categorygroup_id, m2.orderno, m1.orderno,m1.category_id".LB;

    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );

    $separater=$lang_box['category_separater'];
    $separater_code=$lang_box['category_separater_code'];
    $separater_pipeline="|";
    
    $categpries="";


    $old_group_id="";
    $old_group_code="";
    
    $categories_group ="";
    $categories_link_id_group ="";
    $categories_link_code_group ="";
    
    $categories_group2 ="";
    $categories_link_id_group2 ="";
    $categories_link_code_group2 ="";
    
    $categories_group_pipeline ="";
    
    //group_id2, group_code2, grouplinkid2, grouplinkcode2,
    //group_id がカテゴリの名称を表示するのに対し、
    //group_id2はカテゴリのコードを表示する。
    //$categories_group $categories_link_id_group $categories_link_code_group
    //group_pipelineはカテゴリのコードパイプライン区切を表示する。
    //$categories_group_pipeline
    for( $i = 0; $i < $nrows; $i++ )    {
        $A = DB_fetchArray( $result, true );
        $A = array_map('stripslashes', $A);
        $chk=false;
        //表示する
        if  ($A['allow_display']==='0') {
            $chk=true;
        //ログインユーザのみ
        }else if ($A['allow_display']==='1'){
            if  ($chk_user>=1000) {
                $chk=true;
            }
        //グループとadmin権のある人のみ表示
        }else if ($A['allow_display']==='2'){
            if  ($chk_user>=1010) {
                $chk=true;
            }
        //所有者とadmin権のある人のみ
        }else if ($A['allow_display']==='3'){
            if  ($chk_user>=1011) {
                $chk=true;
            }
        //admin権のある人のみ
        }else if ($A['allow_display']==='4'){
            if  ($chk_user>=1100) {
                $chk=true;
            }
        }
        
        if  ($chk==false){
            continue;
        }
        if ($old_group_id<>$A['group_id']){
            if ($categories_group<>""){

                $categories_group=rtrim($categories_group,$separater);
                $templates->set_var('group_'.$old_group_id, $categories_group);
                $templates->set_var('group_'.$old_group_code, $categories_group);
                $categories_group2=rtrim($categories_group2,$separater_code);
                $templates->set_var('group_'.$old_group_id."_code", $categories_group2);
                $templates->set_var('group_'.$old_group_code."_code", $categories_group2);
                $categories_group_pipeline=rtrim($categories_group_pipeline,$separater_pipeline);//@@
                $templates->set_var('group_'.$old_group_id."_pipeline", $categories_group_pipeline);//@@
                $templates->set_var('group_'.$old_group_code."_pipeline", $categories_group_pipeline);//@@
                
                $categories_link_id_group=rtrim($categories_link_id_group,$separater);
                $templates->set_var('grouplink_'.$old_group_id, $categories_link_id_group);
                $categories_link_id_group2=rtrim($categories_link_id_group2,$separater_code);
                $templates->set_var('grouplink_'.$old_group_id."_code", $categories_link_id_group2);

                $categories_link_code_group=rtrim($categories_link_code_group,$separater);
                $templates->set_var('grouplink_'.$old_group_code, $categories_link_code_group);
                $categories_link_code_group2=rtrim($categories_link_code_group2,$separater_code);
                $templates->set_var('grouplink_'.$old_group_code."_code", $categories_link_code_group2);

            }
            $categories_group ="";
            $categories_link_id_group ="";
            $categories_link_code_group ="";
            $categories_group2 ="";
            $categories_link_id_group2 ="";
            $categories_link_code_group2 ="";
            $categories_group_pipeline="";//@@
            
            $old_group_id=$A['group_id'];
            $old_group_code=$A['group_code'];
        }
        $categories.=$A['category_name'].$separater;
        $categories_group.=$A['category_name'].$separater;
        $categories_group2.=$A['category_code'].$separater_code;
        $categories_group_pipeline.=$A['category_code'].$separater_pipeline;
        

        $name=$A['category_name'];
        $code=COM_applyFilter($A['category_code']);
        $url=$_CONF['site_url'] ."/".$pi_name."/category.php";
        $url_code=$url;
        $url_code.="?code=".$A['category_code'];
        $url_code.="&m=code";
        $url_code = COM_buildUrl( $url_code );
        $url_id=$url;
        $url_id.="?id=".$A['category_id'];
        $url_id.="&m=id";
        $url_id= COM_buildUrl( $url_id );
        $category_link_id= COM_createLink($name, $url_id);
        $category_link_code= COM_createLink($name, $url_code);
        $category_link_id2= COM_createLink($code, $url_id);
        $category_link_code2= COM_createLink($code, $url_code);

        $categories_link_id.=$category_link_id.$separater;
        $categories_link_id_group.=$category_link_id.$separater;
        $categories_link_id_group2.=$category_link_id2.$separater_code;//@@
        $categories_link_code.=$category_link_code.$separater;
        $categories_link_code_group.=$category_link_code.$separater;
        $categories_link_code_group2.=$category_link_code2.$separater_code;//@@
    }

    $categories=rtrim($categories,$separater);
    $templates->set_var('categories', $categories);


    $categories_link_code=rtrim($categories_link_code,$separater);
    $categories_link_id=rtrim($categories_link_id,$separater);


    if ($box_conf['categoriescode']){
        $categorieslink=$categories_link_code;
    }else{
        $categorieslink=$categories_link_id;
    }
    $templates->set_var('categorieslink', $categorieslink);


    $templates->set_var('categorieslinkcode', $categories_link_code);
    $templates->set_var('categorieslinkid', $categories_link_id);

    $categories_group=rtrim($categories_group,$separater);
    $categories_group2=rtrim($categories_group2,$separater_code);
    $categories_group_pipeline=rtrim($categories_group_pipeline,$separater_pipeline);//@@

    $templates->set_var('group_'.$old_group_id, $categories_group);
    $templates->set_var('group_'.$old_group_code, $categories_group);
    $templates->set_var('group_'.$old_group_id."_code", $categories_group2);
    $templates->set_var('group_'.$old_group_code."_code", $categories_group2);
    $templates->set_var('group_'.$old_group_id."_pipeline", $categories_group_pipeline);//@@
    $templates->set_var('group_'.$old_group_code."_pipeline", $categories_group_pipeline);//@@

    $categories_link_id_group=rtrim($categories_link_id_group,$separater);
    $templates->set_var('grouplink_'.$old_group_id, $categories_link_id_group);
    
    $categories_link_id_group2=rtrim($categories_link_id_group2,$separater_code);//@@
    $templates->set_var('grouplink_'.$old_group_id."_code", $categories_link_id_group2);//@@
    
    $categories_link_code_group=rtrim($categories_link_code_group,$separater);
    $templates->set_var('grouplink_'.$old_group_code, $categories_link_code_group);
    
    $categories_link_code_group2=rtrim($categories_link_code_group2,$separater_code);//@@
    $templates->set_var('grouplink_'.$old_group_code."_code", $categories_link_code_group2);//@@

    return $retval;
}

function DATABOX_getcategoriesinp(
    $selected = ""
    ,$fieldset_id=""
    ,$pi_name=""
    ,$chk_user=9999
)
{
global $_TABLES;
    if ($pi_name==""){
        $table_def_category=$_TABLES['DATABOX_def_category'];
        $table_def_group=$_TABLES['DATABOX_def_group'];
        $table_def_fieldset_group=$_TABLES['DATABOX_def_fieldset_group'];
        $table_mst=$_TABLES['DATABOX_mst'];
    }else{
        $table_def_category=$_TABLES[strtoupper($pi_name).'_def_category'];
        $table_def_group=$_TABLES[strtoupper($pi_name).'_def_group'];
        $table_def_fieldset_group=$_TABLES[strtoupper($pi_name).'_def_fieldset_group'];
        $table_mst=$_TABLES[strtoupper($pi_name).'_mst'];
    }
    
    //
    if (is_array($selected)){
            $S = $selected;
    }else{
        if( !empty( $selected ))    {
            $S = explode( ' ', $selected );
        }else {
            $S = array();
        }
    }
    
    //
    $retval = '';

    $sql = "SELECT ";
    $sql .= "m2.group_id".LB;
    $sql .= ",m2.name AS group_name".LB;
    $sql .= ",m2.input_type".LB;
    $sql .= ",m2.allow_edit".LB;
    $sql .= " FROM ".LB;
    $sql .= " {$table_def_group} AS m2".LB;
    if  ($fieldset_id<>0){
        $sql .= " ,{$table_def_fieldset_group} AS m3".LB;
        $sql .= " WHERE ".LB;
        $sql .= " m2.group_id = m3.group_id".LB;
        $sql .= " AND m3.fieldset_id = ".$fieldset_id .LB;
    }
    
    
    $sql .= " ORDER BY m2.orderno".LB;
        
    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );

    $categoryids="";
    for( $i = 0; $i < $nrows; $i++ )    {

        $A = DB_fetchArray( $result, true );
        
        $chk=false;
        $disabled="disabled";
        
        if ($chk_user===9999){
            $chk=true;
            $disabled="";
        }else{
            //編集可能
            if  ($A['allow_edit']==='0') {
                $chk=true;
                $disabled="";
            //グループとadmin権のある人のみ表示
            }else if ($A['allow_edit']==='2'){
                if  ($chk_user>=1010) {
                    $chk=true;
                    $disabled="";
                }
                //所有者とadmin権のある人のみ
            }else if ($A['allow_edit']==='3'){
                if  ($chk_user>=1011) {
                    $chk=true;
                    $disabled="";
                }
            //編集不可表示のみ
            }else if ($A['allow_edit']==='4'){
                $chk=true;
            }
        }
        if ($chk==false) {
            continue;
        }
		
        $sql2 = "SELECT ";
        $sql2 .= " m1.category_id".LB;
        $sql2 .= ",m1.name".LB;
        $sql2 .= ",m1.orderno".LB;
        $sql2 .= " FROM {$table_def_category} AS m1".LB;
        $sql2 .= " WHERE ".LB;
        $sql2 .= " m1.categorygroup_id=".$A['group_id'].LB;
        
        $sql2 .= " ORDER BY m1.orderno".LB;
        
        $result2 = DB_query( $sql2 );
        $nrows2 = DB_numRows( $result2 );
        
        if ($nrows2>0){
            $retval .="<div class=\"databox_admin_data_title\">";
            $retval .=$A['group_name'];
            $retval .="</div>".LB;
        
            if  ($A['input_type']==1){
                $retval .='<select name="category[]" multiple style="height:10em" '.$disabled.' >'.LB;
            }else if ($A['input_type']==3){
                $retval .='<select name="category[]" '. $disabled .' >'.LB;
            }
            
            for( $i2 = 0; $i2 < $nrows2; $i2++ )    {

                $A2 = DB_fetchArray( $result2, true );
                
				if  ($disabled==""){
					$categoryids.=$A2['category_id']."|";
                }
                
                $checked=" ";
                for( $x = 0; $x < sizeof( $S ); $x++ )            {
                    if( $A2['category_id'] == $S[$x] )                {
                        if  ($A['input_type']==0 OR $A['input_type']==2){
                            $checked= ' checked="checked" ';
                        }else{
                            $checked= ' selected ';
                        }
                    }
                }
            
                if  ($A['input_type']==0){
                    $retval .= '<span style="white-space:nowrap">';
                    $retval .= '<input type="checkbox" name="category[]" value="' . $A2['category_id'] ;
                    $retval .= '"';
                    $retval .= $checked;
                    $retval .= $disabled.' >' . stripslashes( $A2['name'] ) . '</span>' . LB;
                }else  if  ($A['input_type']==1){
                    $retval .= '<option value="' . $A2['category_id'] . '"';
                    $retval .= $checked;
                    $retval .= ' >' . stripslashes( $A2['name'] ) ;
                    $retval .= '</option>' . LB;
                }else  if  ($A['input_type']==2){
                    $retval .= '<input type="radio" name="category[]" value="' . $A2['category_id'];
                    $retval .= '"';
                    $retval .= $checked;
                    $retval .= ' '.$disabled.' >' . stripslashes( $A2['name'] ) . '</span>' . LB;
                }else  if  ($A['input_type']==3){
                    $retval .= '<option value="' . $A2['category_id'] . '"';
                    $retval .= $checked;
                    $retval .= ' >' . stripslashes( $A2['name'] ) ;
                    $retval .= '</option>' . LB;
                }
            }
        
            if  ($A['input_type']==1  OR  $A['input_type']==3  ){
                $retval .='</select>';
            }
        }
        
	}
	if  ($categoryids==""){
	}else{
		$categoryids=substr($categoryids,0,strlen($categoryids)-1);
        $retval .= "  <input type='hidden' name='categories' value='{$categoryids}'>";
    }
    return $retval;
}
function DATABOX_getaddtionfields(
    $id
    ,$addition_def
    ,$pi_name
    ,$field_id=0
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目値を返す　　　　                                            |
// | 書式 DATABOX_getadditionfields($id,$addition_def,$tbl_prefix,$field_id)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'

// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目値の配列
// +---------------------------------------------------------------------------+
//update 20110204
{

    global $_TABLES;

    $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];
    $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];

    $lang_noyes="LANG_".strtoupper($pi_name)."_NOYES";
    global $$lang_noyes;
    $lang_noyes=$$lang_noyes;

    $retval=array();

    $sql = "SELECT ";
    $sql .= " value";
    $sql .= ",t.field_id";

    $sql .= " FROM {$table_addition} AS t";
    $sql .= " , {$table_def_field} AS m";
    $sql .= " WHERE ";
    $sql .= " t.id={$id}";
    $sql .= " AND t.field_id=m.field_id";
    if ($field_id<>0){
        $sql .= " AND t.field_id=".$field_id;
    }

    $sql .= " ORDER BY m.orderno";

    $result = DB_query( $sql );
    $nrows = DB_numRows( $result );

    for( $i = 0; $i < $nrows; $i++ )    {

        $A = DB_fetchArray( $result, true );

        $fid=$A["field_id"];
        $value=$A["value"];

        $fieldvalue=DATABOX_getfieldvalue(
            $value
            ,$addition_def[$fid]['type']
            ,$addition_def[$fid]['selectionary']
            ,$lang_noyes
            ,$addition_def[$fid]['selectlist']
            ,$pi_name
            );
        $retval[$fid]=$fieldvalue;
    }

    return $retval;
}

function DATABOX_getfieldvalue(
    $value
    ,$type
    ,$selectionary
    ,$lang_noyes
    ,$selectlist
    ,$pi_name
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目値を返す　　　　                                            |
// | 書式 DATABOX_getfieldvalue($value,$type,$selectionary,$lang_noyes)
// +---------------------------------------------------------------------------+
// | 引数 $value:
// | 引数 $type:
// | 引数 $selectionary:
// | 引数 $lang_noyes:
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目値
// +---------------------------------------------------------------------------+
//update 20110204
{
    global $_TABLES;
    global $_CONF;
    
    switch ($type) {

        case 2://いいえ/はい
            $fieldvalue = $lang_noyes[$value];
            break;
        case 7://オプションリスト（選択肢）
        case 8://ラジオボタンリスト（選択肢）
            $fieldvalue= $selectionary[$value];
            break;
        case 9://オプションリスト（マスター）';
        case 16://ラジオボタンリスト（マスター）';
            if  ($value==""){
                $fieldvalue="";
            }else{
                $fieldvalue=DB_getItem($_TABLES[strtoupper($pi_name).'_mst'] 
                    ,"value"
                    ,"kind='{$selectlist}' AND no={$value}");
            }
            break;
        case 4://日時
            $w=COM_getUserDateTimeFormat($value);
            $fieldvalue= $w[0];
            break;
        case 3://日付
            $fieldvalue=strftime( $_CONF['shortdate'], $value );
            break;
        case 5://メールアドレス　（準備中）';
        case 6://url　（準備中）';

        case 0:// 一行テキストフィールド
        case 1:// 複数行テキストフィールド(HTML OK)
        case 10:// 複数行テキストフィールド(HTML NG)
        case 20:// 複数行テキストフィールド(HTML OK  TinyMCE)
        default:
            $fieldvalue = $value;
        }

    return $fieldvalue;
}


function DATABOX_saveaddtiondatas(
    $id
    ,$fldary = ""
    ,$addition_def=""
    ,$pi_name=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目書込                                                        |
// | 書式
// +---------------------------------------------------------------------------+
// | 引数 $ide:
// | 引数 $fldary:
// | 引数 $addition_def:
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:
// +---------------------------------------------------------------------------+
//
{
    global $_TABLES;

    if ($pi_name==""){
        $table=$_TABLES['DATABOX_addition'];
    }else{
        $table=$_TABLES[strtoupper($pi_name).'_addition'];
    }
    
    $rt=DB_delete($table,"id",$id);
    if (is_array($fldary)){
        foreach( $fldary as $field_id => $value ){
            
            $sql="INSERT INTO {$table} (";
            $sql.="id, field_id,value ";
            $sql.=") VALUES (";
            $sql.="{$id},{$field_id}";
            if  ($value<>"") {
                $sql.=", '{$value}'";
            }else{
                $sql.=", NULL";
            }
            $sql.=")";
            DB_query($sql);
        }
    }
    return ;

}

function DATABOX_saveaddtiondatas_update(
    $id
    ,$fldary = ""
    ,$addition_def=""
    ,$pi_name=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目書込 user用
// | 書式 $rt=DATABOX_saveaddtiondatas_update
// |               ($id,$additionfields,$addition_def,$pi_name);
// +---------------------------------------------------------------------------+
// | 引数 $ide:
// | 引数 $fldary:
// | 引数 $addition_def:
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:                                                               |
// +---------------------------------------------------------------------------+
//
{
    global $_TABLES;

    if ($pi_name==""){
        $table=$_TABLES['DATABOX_addition'];
    }else{
        $table=$_TABLES[strtoupper($pi_name).'_addition'];
    }

    if (is_array($fldary)){

        foreach( $fldary as $field_id => $value ){
            //if ($addition_def[$field_id]['type']=11){
            $sql="UPDATE ".$table ." SET ";
            if  ($value<>"") {
                $sql.=" value ='".$value."'";
            }else{
                $sql.=" value =NULL";
            }
            $sql.=" WHERE id=".$id." AND field_id=".$field_id;
            DB_query($sql);
        }
    }


    return ;

}

function DATABOX_cleanaddtiondatas(
    &$fldary
    ,$addition_def
    ,&$fnmary
    ,&$delary
    ,&$dateary
    ,&$altary
    ,$addslash = true
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目clean
// | 書式 DATABOX_cleanadditiondatas($fldary,$typeary)
// +---------------------------------------------------------------------------+
// | 引数 $fldary:
// | 引数 $addition_def
// | 引数 $fnmary
// | 引数 $delary
// | 引数 $addslash 
// +---------------------------------------------------------------------------+
// | 戻値 nomal:                                                               |
// +---------------------------------------------------------------------------+
//update 20131022 12
{
    if (is_array($fldary)){
        foreach( $fldary as $field_id => $value ){
            $type=$addition_def[$field_id]['type'];
            $textconv=$addition_def[$field_id]['textconv'];
            $textcheck=$addition_def[$field_id]['textcheck'];
            $wk=$value;
            //入力値変換
            switch ($type) {
                case 0:// 一行テキストフィールド
                case 1:// 複数行テキストフィールド(HTML OK)
                case 10:// 複数行テキストフィールド(HTML NG)
                case 20:// 複数行テキストフィールド(HTML OK TinyMCE)
                if  (($textconv==10)
                        OR ($textcheck>=11 AND $textcheck<=14)){ //半角チェック;
                        $wk = mb_convert_kana($wk,"ak");
                    }else if ($textconv==20){ //全角に変換する';
                        $wk = mb_convert_kana($wk,"AKV");
                }
                break;
            }
            //
            switch ($type) {
                case 11:// 図
                    break;
                case 0:// 一行テキスト
                case 10://複数行テキスト
                    $wk=COM_stripslashes($wk);
                    $wk=COM_checkHTML (COM_checkWords ($wk));
                    break;
                case 1://HTML
                case 20://TinyMCE
                case 19://CKEditor
                    $wk=COM_stripslashes($wk);
                    break;
                case 2://いいえ/はい
                    $wk=COM_applyFilter($wk,true);
                    break;
                case 3://日付
                    $wk_year=$_POST['afield'.$field_id.'_year'];
                    $wk_month=$_POST['afield'.$field_id.'_month'];
                    $wk_day=$_POST['afield'.$field_id.'_day'];
                    $wk=$wk_year."-".$wk_month."-".$wk_day;
                    $dateary[$field_id]['year']=$wk_year;//@@@@@
                    $dateary[$field_id]['month']=$wk_month;//@@@@@
                    $dateary[$field_id]['day']=$wk_day;//@@@@@
                    break;
                case 22://日付
                case 23://日付
                    $ary_wk=date_parse( $wk);
                    if  ($ary_wk[error_count]==0){
                        $dateary[$field_id]['month']= $ary_wk[month];
                        $dateary[$field_id]['day']= $ary_wk[day];
                        $dateary[$field_id]['year']= $ary_wk[year];
                    }else{
                        $wk="";
                        $dateary[$field_id]['month']= "";
                        $dateary[$field_id]['day']= "";
                        $dateary[$field_id]['year']= "";
                    }
                    break;
                case 4://日時
                    $wk_year=$_POST['afield'.$field_id.'_year'];
                    $wk_month=$_POST['afield'.$field_id.'_month'];
                    $wk_day=$_POST['afield'.$field_id.'_day'];
                    $wk_hour=$_POST['afield'.$field_id.'_hour'];
                    $wk_minute=$_POST['afield'.$field_id.'_minute'];
                    $wk_ampm=$_POST['afield'.$field_id.'_ampm'];
                    $dateary[$field_id]['year']=$wk_year;//@@@@@
                    $dateary[$field_id]['month']=$wk_month;//@@@@@
                    $dateary[$field_id]['day']=$wk_day;//@@@@@
                    $dateary[$field_id]['hour']=$wk_hour;//@@@@@
                    $dateary[$field_id]['minute']=$wk_minute;//@@@@@
                    $dateary[$field_id]['ampm']=$wk_ampm;//@@@@@
                    if ($wk_ampm == 'pm') {
                        if ($wk_hour < 12) {
                            $wk_hour = $wk_hour + 12;
                        }
                    }
                    if ($wk_ampm == 'am' AND $wk_hour == 12) {
                        $wk_hour = '00';
                    }
                    $wk=$wk_year."-".$wk_month."-".$wk_day ." ".$wk_hour.":".$wk_minute;
                    break;
                case 26://時刻
                    $wk=COM_applyFilter($wk);
                    $wk=COM_checkHTML (COM_checkWords ($wk));
                    break;
                case 24://チェックボックス（選択肢）
                case 25://チェックボックス（マスター）
                case 14://マルチセレクトリスト（選択肢）
                case 18://マルチセレクトリスト（マスター）
                    $wk=implode ($wk,":");
                    break;
                case 12:// 画像（ファイル保存）
                    $fnmary[$field_id]=COM_applyFilter($fnmary[$field_id]);
                    $delary[$field_id]=COM_applyFilter($delary[$field_id]);
                    $wk=COM_applyFilter($wk);
                    $wk=COM_checkHTML (COM_checkWords ($wk));
                    $wk2=COM_applyFilter($altary[$field_id]);
                    $wk2=COM_checkHTML (COM_checkWords ($wk2));
                    $altary[$field_id]=$wk2;
                    $a=explode("|",$wk);
                    $wk=$a[0]."|".$wk2;
                    break;
                case 13:// 添付ファイル
                    $fnmary[$field_id]=COM_applyFilter($fnmary[$field_id]);
                    $delary[$field_id]=COM_applyFilter($delary[$field_id]);
                    $wk=COM_applyFilter($wk);
                    $wk=COM_checkHTML (COM_checkWords ($wk));
                    $wk2=COM_applyFilter($delary[$field_id]);
                    $delary[$field_id]=COM_checkHTML (COM_checkWords ($wk2));
                    break;
                case 7://オプションリスト（選択肢）
                case 8://ラジオボタンリスト（選択肢）
                case 9://オプションリスト（マスター）';
                case 5://メールアドレス';
                case 6://url';
                default:
                    $wk=COM_applyFilter($wk);
                    $wk=COM_checkHTML (COM_checkWords ($wk));
            }
            if ($addslash){
                $wk=addslashes ($wk);
            }
            $fldary[$field_id] =$wk;
        }
    }

    return $fldary;

}

function DATABOX_checkaddtiondatas(
    &$fldary
    ,$addition_def
    ,$pi_name
    ,$fldary_fnm=""
    ,$fldary_del=""
    ,$fldary_alt=""
)
// +---------------------------------------------------------------------------+
// | 機能 追加項目check
// | 書式 DATABOX_checkdditiondatas($fldary,$typeary,$pi_data)
// | 書式 DATABOX_checkdditiondatas($additionfields,$addition_def,$pi_name
// |                                 ,$additionfields_fnm,$additionfields_del)
// +---------------------------------------------------------------------------+
// | 引数 $fldary:追加項目データ配列
// | 引数 $addition_def:追加項目定義
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +-----
// | 引数 $fldary_fnm:追加項目データ配列　アップロードファイル名
// | 引数 $fldary_del:追加項目データ配列　アップロードファイル削除
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
{
    global $_CONF;
    global $LANG04;
    global $_FILES;
    
    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if (is_array($fldary)){

        foreach( $fldary as $field_id => $value ){
            //必須
            if ($addition_def[$field_id]['checkrequried']==1){
                if ($value ===""){
                    $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_checkrequried'] ;
                    $rt.="<br ".XHTML.">".LB;
                }
                //url email date
            }
            //図 外記憶
            if ($addition_def[$field_id]['type']==12){//図
                 $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
                 if  ( $box_conf['imgfile_subdir']==1){
                    $imgfld.=$addition_def[$field_id]['templatesetvar'];
                 }
                 $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                 if  ( $box_conf['imgfile_subdir']==1){
                    $imgfld_thmb.=$addition_def[$field_id]['templatesetvar'];
                 }
                 if (!is_writable ( $imgfld )){
                    $rt.=$imgfld.": ".$lang_box_admin['err_writable'];
                    $rt.="<br ".XHTML.">".LB;
                 }
                 if  ($imgfld<>$imgfld_thmb){
                    if (!is_writable ( $imgfld_thmb )){
                        $rt.=$imgfld_thmb.": ".$lang_box_admin['err_writable'] ;
                        $rt.="<br ".XHTML.">".LB;
                    }
                 }
                 if (is_array($fldary_del) AND $fldary_del[$field_id]){
                     //$fldary[$field_id]="";
                     //$fldary_fnm[$field_id]="";
                 }else{
                    if (is_array($fldary_fnm)){
                        $imgname=$_FILES['afield_fnm']['name'][$field_id];
                        $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                        $imgtype=$_FILES['afield_fnm']['type'][$field_id];

                        if  ($imgfile<>""){
                            $imgerr="";
                            if ($box_conf['imgfile_size2']<>"" AND $imgsize>$box_conf['imgfile_size2']){
                                $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_size'];
                                $rt.="<br ".XHTML.">".LB;
                                $imgerr=1;
                            }
                            if (count($box_conf['imgfile_type2'])>0){
                                if  (in_array($imgtype,$box_conf['imgfile_type2'])){
                                }else{
                                    $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_type2']
                                        .$_FILES['afield_fnm']['name'][$field_id]."(".$imgtype.")";
                                    $rt.="<br ".XHTML.">".LB;
                                    $imgerr=1;
                                }
                            }
                        
                            //$imgname=sanitize
                            // 問題文字を除く
                            //$filename = str_replace("&","",$filename);
                            //$filename = str_replace("'","",$filename);
                            //$filename = str_replace("/","",$filename);
                            //$filename = str_replace(".JPG",".jpg",$filename);
                            //重複チェック
                            if  ($imgerr===""){
                                $fldary[$field_id]=$imgname."|".fldary_alt;//@@@@@@@!!!!
                            }
                        }
                    }else{
                        
                    }    
                 }
            }



            //図 DB記憶
            if ($addition_def[$field_id]['type']==11){//図

                 if (is_array($fldary_del) AND $fldary_del[$field_id]){
                        $fldary[$field_id]="";
                        $fldary_fnm[$field_id]="";
                 }else{
                    //$figure = $_FILES[$w];
                    // $imgfile=$figure[ 'tmp_name' ];


                     $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                     $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                     $imgtype=$_FILES['afield_fnm']['type'][$field_id];

                     if  ($imgfile<>""){
                          $imgerr="";
                          if ($box_conf['imgfile_size']<>"" AND $imgsize>$box_conf['imgfile_size']){
                              $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_size'];
                              $rt.="<br ".XHTML.">".LB;
                              $imgerr=1;
                          }
                          if (count($box_conf['imgfile_type'])>0){
                              if  (in_array($imgtype,$box_conf['imgfile_type'])){
                              }else{
                                  $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_type']
                                  .$_FILES['afield_fnm']['name'][$field_id]."(".$imgtype.")";
                                  $rt.="<br ".XHTML.">".LB;
                                 $imgerr=1;
                              }
                          }
                          if  ($imgerr===""){
                              $imgbinary = fread ( fopen ( $imgfile , "r" ), filesize ( $imgfile ));
                              $fldary[$field_id]=base64_encode($imgbinary);
                          }
                     }
                 }
            }

            //日付 日時
            if ($addition_def[$field_id]['type']==3 OR $addition_def[$field_id]['type']==4
                     OR $addition_def[$field_id]['type']==22 OR $addition_def[$field_id]['type']==23){//日付
                if ($value ===""){
                }else{
                    $w=strtotime($value);
                    
                    if  ($addition_def[$field_id]['range_start']=="" 
                         AND $addition_def[$field_id]['range_end']=="" ){
                    }else{
                        $w_start=strtotime($addition_def[$field_id]['range_start']);
                        $w_end=strtotime($addition_def[$field_id]['range_end']);
                        if  ($w <$w_start OR $w >$w_end){
                                 $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_range']
                                  ."(".$addition_def[$field_id]['range_start'] 
                                  ."-".$addition_def[$field_id]['range_end'].")" ;
                                 $rt.="<br ".XHTML.">".LB;
                        }
                    }
                    
                    if ($w==""){
                        $w_chk=false;
                    }else{
                        $w_year=date('Y', $w);
                        $w_month=date('m', $w);
                        $w_day=date('d', $w);
                        $w_chk=checkdate($w_month, $w_day, $w_year);
                    }
                    if ($w_chk==false) {
                        $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_date'] ;
                        $rt.="<br ".XHTML.">".LB;
                    }else{
                        $w_date=date("Y-m-d",$w);
                        if ($addition_def[$field_id]['type']==3){
                            $w_time="";
                        }else{
                            $w_time=date("H:i",$w)."::00";
                        }
                        $fldary[$field_id]=COM_convertDate2Timestamp($w_date,$w_time);
                    }
                }
            }
            //時刻
            if ($addition_def[$field_id]['type']==26){
                if ($value ===""){
                }else{
                    $w=strtotime("1970-1-1 ".$value);
                    if  ($addition_def[$field_id]['range_start']=="" 
                         AND $addition_def[$field_id]['range_end']=="" ){
                    }else{
                        $w_start=strtotime("1970-1-1 ".$addition_def[$field_id]['range_start'].":00");
                        $w_end=strtotime("1970-1-1 ".$addition_def[$field_id]['range_end'].":00");
                        if  ($w <$w_start OR $w >$w_end){
                                 $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_range']
                                  ."(".$addition_def[$field_id]['range_start'] 
                                  ."-".$addition_def[$field_id]['range_end'].")" ;
                                 $rt.="<br ".XHTML.">".LB;
                        }
                    }
                    if ($w=="") {
                        $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_time'] ;
                        $rt.="<br ".XHTML.">".LB;
                    }else{
                        $w_time=date("H:i",$w)."::00";
                        $fldary[$field_id]=COM_convertDate2Timestamp("1970-1-1",$w_time);
                    }
                }
            }
            //メールアドレス
            if ($addition_def[$field_id]['type']==5){
                if ($value ===""){
                }else{
                    if (COM_isEmail ($value)==false) {
                        $rt.=$addition_def[$field_id]['name'].$LANG04[18];
                        $rt.="<br ".XHTML.">".LB;
                    }
                }
            }
            //url
            if ($addition_def[$field_id]['type']==6){
                if ($value ===""){
                }else{
                    
                    if (preg_match('/^(https?|ftp)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/', $value)) {
                    } else {
                        $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_url'];
                        $rt.="<br ".XHTML.">".LB;
                    }
                }
            }
            //numeric
            if ($addition_def[$field_id]['type']==15 OR $addition_def[$field_id]['type']==21){
                if ($value ===""){
                }else{
                    
                    if (is_numeric($value)) {
                    } else {
                        $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_numeric'];
                        $rt.="<br ".XHTML.">".LB;
                    }
                }
            }
           //text field
            if (($addition_def[$field_id]['type']==0)
                    OR ($addition_def[$field_id]['type']==1)
                    OR ($addition_def[$field_id]['type']==10) ){
                if ($value ===""){
                }else{
                    if  ($addition_def[$field_id]['textcheck']==11){ //'半角数字のみ';
                        if  (!preg_match("/^[0-9]+$/", $value)){
                            $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_text1'];
                            $rt.="<br ".XHTML.">".LB;
                        }
                    }else if ($addition_def[$field_id]['textcheck']==12){//半角英数字のみ';
                        if  (!preg_match("/^[a-zA-Z0-9]+$/", $value)){
                            $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_text2'];
                            $rt.="<br ".XHTML.">".LB;
                        }
                    }else if ($addition_def[$field_id]['textcheck']==13){//標準IDの範囲内';
                        $value=rtrim(ltrim($value));
                        $newvalue=COM_sanitizeID($value);
                        if  ($value<>$newvalue){
                            $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_text3'];
                            $rt.="<br ".XHTML.">".LB;
                        }
                        $fldary[$field_id]=$value;
                    }else if ($addition_def[$field_id]['textcheck']==14){//半角英数字記号のみ';
                        if  (!preg_match("/^[!-~]+$/", $value)){
                            $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_text4'];
                            $rt.="<br ".XHTML.">".LB;
                        }
                    }
                }
            }
            
            
            //textarea
            if ($addition_def[$field_id]['type']==1 
                    OR $addition_def[$field_id]['type']==10
                    OR $addition_def[$field_id]['type']==20
                    ){
                if ($value ===""){
                }else{
                    if (mb_strlen($value, 'UTF-8')>$addition_def[$field_id]['maxlength']) {
                        $rt.=$addition_def[$field_id]['name']
                            .$addition_def[$field_id]['maxlength']
                            .": ".$lang_box_admin['err_maxlength'];
                            $rt.="<br ".XHTML.">".LB;
                    }
                }
            }
            //添付ファイル
            if ($addition_def[$field_id]['type']==13){
                 $file_path=$box_conf['file_path'];
                 if  ( $box_conf['file_subdir']==1){
                    $file_path.=$addition_def[$field_id]['templatesetvar'];
                 }
                 if (!is_writable ( $file_path )){
                    $rt.=$file_path.": ".$lang_box_admin['err_writable'];
                    $rt.="<br ".XHTML.">".LB;
                 }
                 if (is_array($fldary_del) AND $fldary_del[$field_id]){
                 }else{
                    if (is_array($fldary_fnm)){
                        $filename=$_FILES['afield_fnm']['name'][$field_id];
                        $filetmpname=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $filesize=$_FILES['afield_fnm']['size'][$field_id];
                        $filetype=$_FILES['afield_fnm']['type'][$field_id];

                        if  ($filetmpname<>""){
							$err="";
                            if ($box_conf['file_size']<>"" AND $filesize>$box_conf['file_size']){
                                $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_size'];
                                $rt.="<br ".XHTML.">".LB;
                                $err=1;
                            }
							if (count($box_conf['file_type'])>0){
                                if  (in_array($filetype,$box_conf['file_type'])){
                                }else{
                                    $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_type']
                                        .$_FILES['afield_fnm']['name'][$field_id]."(".$filetype.")";
                                    $rt.="<br ".XHTML.">".LB;
                                    $err=1;
                                }
                            }
                            //チェックOK
                            if  ($err===""){
                                $fldary[$field_id]=$filename;
                            }
                        }
                    }else{
                        
                    }
                 }
            }

            //range check 範囲チェック
            if  ($addition_def[$field_id]['range_start']=="" AND $addition_def[$field_id]['range_end']=="" ){
            }else{
                if (($addition_def[$field_id]['type']==0)
                     OR ($addition_def[$field_id]['type']==1)
                     OR ($addition_def[$field_id]['type']==15) 
                     OR ($addition_def[$field_id]['type']==21) ){
                    if  ($value <$addition_def[$field_id]['range_start'] 
                        OR $value >$addition_def[$field_id]['range_end']){
                                $rt.=$addition_def[$field_id]['name'].": ".$lang_box_admin['err_range']
                                ."(".$addition_def[$field_id]['range_start'] 
                                ."-".$addition_def[$field_id]['range_end'].")";
                                $rt.="<br ".XHTML.">".LB;
                    }
                }
                
            }
        }
    }

    return $rt;

}

function DATABOX_uploadaddtiondatas(
    &$fldary
    ,$addition_def
    ,$pi_name
    ,$id
    ,$fldary_fnm=""
    ,$fldary_del=""
    ,$fldary_old=""
    ,$fldary_alt=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目upload
// | 書式 DATABOX_uploadaddtiondatas($additionfields,$addition_def,$pi_name
// |                                 ,$additionfields_fnm,$additionfields_del)
// +---------------------------------------------------------------------------+
// | 引数 $fldary:追加項目データ配列
// | 引数 $addition_def:追加項目定義
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +-----
// | 引数 $fldary_fnm:追加項目データ配列　アップロードファイル名
// | 引数 $fldary_del:追加項目データ配列　アップロードファイル削除
// | 引数 $fldary_old:追加項目データ配列　OLD
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
{
    global $_CONF;
    global $LANG04;
    global $_FILES;

    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if (is_array($fldary)){

        foreach( $fldary as $field_id => $value ){
            
            //図 外記憶
            if ($addition_def[$field_id]['type']==12){//図
                $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
                $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                $filename=$fldary[$field_id];
                $a=explode("|",$fldary[$field_id]);
                $filename=$a[0];
                $a=explode("|",$fldary_old[$field_id]);
                $filename_old=$a[0];
                if (is_array($fldary_del) AND $fldary_del[$field_id]){
                    $rt=DATABOX_deleteFile($imgfld.$filename);
                    $thumbname=DATABOX_getthumbname($filename);
                    $rt=DATABOX_deleteFile($imgfld_thmb.$thumbname);
                    $fldary[$field_id]="";
                }else{
                    $imgname=$_FILES['afield_fnm']['name'][$field_id];
                    
                    if ($imgname<>""){
                        if  ($filename_old<>""){
                            $rt=DATABOX_deleteFile($imgfld.$filename_old);
                            $thumbname=DATABOX_getthumbname($filename_old);
                            $rt=DATABOX_deleteFile($imgfld_thmb.$thumbname);
                        }

                        $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                        $imgtype=$_FILES['afield_fnm']['type'][$field_id];
                        $a=explode(".",$imgname);
                        $a[1]=strtolower($a[1]);
                        if  ( $box_conf['imgfile_subdir']==1){
                            $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                        }else{
                            $newfilename="";
                        }
                        $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                        //$newfilename=$imgname;
                        if (DATABOX_uploadImageFile
                                ($imgname,$imgfile,$imgsize, $imgtype,$imgfld,$newfilename)){
                            $fldary[$field_id]=$newfilename."|".$fldary_alt[$field_id];//@@@@@@@!!!!
                            //サムネイル作成する場合
                            if($box_conf['imgfile_thumb_ok'] && function_exists('ImageCreate')) {
                                $rt=DATABOX_createthumb(
                                    $newfilename
                                    ,$box_conf['imgfile_thumb_w'] ,$box_conf['imgfile_thumb_h'] 
                                    ,$box_conf['imgfile_thumb_w2'] ,$box_conf['imgfile_thumb_h2'] 
                                    ,$imgfld_thmb,$imgfld);
                            }
                        }else{
                            $fldary[$field_id]="";
                            //$fldary_fnm[$field_id]="";
                        }
                    }
                }
            }
			
            //添付ファイル
            if ($addition_def[$field_id]['type']==13){
                $imgfld=$box_conf['file_path'];
                $filename=$fldary[$field_id];
                $a=explode("|",$fldary_old[$field_id]);
                $filename_old=$a[0];
                if (is_array($fldary_del) AND $fldary_del[$field_id]){
                    $rt=DATABOX_deleteFile($imgfld.$filename_old);
                    $fldary[$field_id]="";
                }else{
                    $imgname=$_FILES['afield_fnm']['name'][$field_id];
                    
                    if ($imgname<>""){
                        if  ($filename_old<>""){
                            $rt=DATABOX_deleteFile($imgfld.$filename_old);
                        }
                        $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                        $imgtype=$_FILES['afield_fnm']['type'][$field_id];
                        $a=explode(".",$imgname);
                        $a[1]=strtolower($a[1]);//拡張子を小文字にする
                        if  ( $box_conf['file_subdir']==1){
                            $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                        }else{
                            $newfilename="";
                        }
                        $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                        if (DATABOX_uploadImageFile
                                ($imgname,$imgfile,$imgsize, $imgtype,$imgfld,$newfilename)){
                            $fldary[$field_id]=$newfilename."|".$imgname;
                        }else{
                            $fldary[$field_id]="";
                        }
                    }
                }
            }

		}
    }

    return $rt;

}
function DATABOX_uploadaddtiondatas_cpy(
    &$fldary
    ,$addition_def
    ,$pi_name
    ,$id
    ,$fldary_fnm=""
    ,$fldary_del=""
    ,$fldary_old=""
    ,$fldary_alt=""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目upload  for copy
// | 書式 DATABOX_uploadaddtiondatas($additionfields,$addition_def,$pi_name
// |                                 ,$additionfields_fnm,$additionfields_del)
// +---------------------------------------------------------------------------+
// | 引数 $fldary:追加項目データ配列
// | 引数 $addition_def:追加項目定義
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// +-----
// | 引数 $fldary_fnm:追加項目データ配列　アップロードファイル名
// | 引数 $fldary_del:追加項目データ配列　アップロードファイル削除
// | 引数 $fldary_old:追加項目データ配列　OLD
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
{
    global $_CONF;
    global $LANG04;
    global $_FILES;

    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if (is_array($fldary)){

        foreach( $fldary as $field_id => $value ){
            
            //図 外記憶
            if ($addition_def[$field_id]['type']==12){//図
                $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
                $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                //$filename=$fldary[$field_id];
                //$filename_old=$fldary_old[$field_id];
                $a=explode("|",$fldary[$field_id]);
                $filename=$a[0];//ファイル名
                $a=explode("|",$fldary_old[$field_id]);
                $filename_old=$a[0];//ファイル名
                
                if (is_array($fldary_del) AND $fldary_del[$field_id]){
                    $fldary[$field_id]="";
                }else{
                    $imgname=$_FILES['afield_fnm']['name'][$field_id];
                    if ($imgname<>""){
                        $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                        $imgtype=$_FILES['afield_fnm']['type'][$field_id];
                        $a=explode(".",$imgname);
                        $a[1]=strtolower($a[1]);
                        if  ( $box_conf['imgfile_subdir']==1){
                            $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                        }else{
                            $newfilename="";
                        }
                        $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                        //$newfilename=$imgname;
                        if (DATABOX_uploadImageFile
                                ($imgname,$imgfile,$imgsize, $imgtype,$imgfld,$newfilename)){
                            $fldary[$field_id]=$newfilename."|".$fldary_alt[$field_id];
                            //サムネイル作成する場合
                            if($box_conf['imgfile_thumb_ok'] && function_exists('ImageCreate')) {
                                $rt=DATABOX_createthumb(
                                    $newfilename
                                    ,$box_conf['imgfile_thumb_w'] ,$box_conf['imgfile_thumb_h'] 
                                    ,$box_conf['imgfile_thumb_w2'] ,$box_conf['imgfile_thumb_h2'] 
                                    ,$imgfld_thmb,$imgfld);
                            }
                        }else{
                            $fldary[$field_id]="";
                        }
                    }else{
                        if  ($filename_old<>""){
                            $cpyfrom=$imgfld.$filename_old;
                            $a=explode(".",$filename_old);
                            $a[1]=strtolower($a[1]);
                            if  ( $box_conf['imgfile_subdir']==1){
                                $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                            }else{
                                $newfilename="";
                            }
                            $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                            $cpyto=$imgfld.$newfilename;
                            $rt=copy( $cpyfrom, $cpyto );
                            //
                            $thumbname=DATABOX_getthumbname($filename_old);
                            $cpyfrom =$imgfld_thmb.$thumbname;
                            $thumbname=DATABOX_getthumbname($newfilename);
                            $cpyto =$imgfld_thmb.$thumbname;
                            $rt=copy( $cpyfrom, $cpyto );
                            //
                            $fldary[$field_id]=$newfilename."|".$fldary_alt[$field_id];
                        }
                    }
                }
			}
            //添付ファイル
            if ($addition_def[$field_id]['type']==13){
                $imgfld=$box_conf['file_path'];
                $filename=$fldary[$field_id];
                $a=explode("|",$fldary_old[$field_id]);
                $filename_old=$a[0];
                $imgname_old=$a[1];
                if (is_array($fldary_del) AND $fldary_del[$field_id]){
                    $fldary[$field_id]="";
                }else{
                    $imgname=$_FILES['afield_fnm']['name'][$field_id];
                    if ($imgname<>""){
                        $imgfile=$_FILES['afield_fnm']['tmp_name'][$field_id];
                        $imgsize=$_FILES['afield_fnm']['size'][$field_id];
                        $imgtype=$_FILES['afield_fnm']['type'][$field_id];
                        $a=explode(".",$imgname);
                        $a[1]=strtolower($a[1]);
                        if  ( $box_conf['imgfile_subdir']==1){
                            $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                        }else{
                            $newfilename="";
                        }
                        $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                        if (DATABOX_uploadImageFile
                                ($imgname,$imgfile,$imgsize, $imgtype,$imgfld,$newfilename)){
                            $fldary[$field_id]=$newfilename;
                        }else{
                            $fldary[$field_id]="";
                        }
                    }else{
                        if  ($filename_old<>""){
                            $cpyfrom=$imgfld.$filename_old;
                            $a=explode(".",$filename_old);
                            $a[1]=strtolower($a[1]);
                            if  ( $box_conf['file_subdir']==1){
                                $newfilename=$addition_def[$field_id]['templatesetvar']."/";
                            }else{
                                $newfilename="";
                            }
                            $newfilename.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".".$a[1];
                            $cpyto=$imgfld.$newfilename;
                            $rt=copy( $cpyfrom, $cpyto );
                            //
                            $fldary[$field_id]=$newfilename."|".$imgname_old;
                        }
                    }
                }
            }

        }
    }

    return $rt;

}

//update
function DATABOX_uploadaddtiondatas_xml(
    &$fldary
    ,$addition_def
    ,$pi_name
    ,$id
)
{
    global $_CONF;
    global $LANG04;
    global $_FILES;

    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if (is_array($fldary)){
        foreach( $fldary as $field_id => $value ){
            if ($fldary[$field_id]<>""){
                
                //図 外記憶
                if ($addition_def[$field_id]['type']==12){//図
                    $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
                    $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                    //
                    if  ($box_conf['imgfile_subdir']==1){
                        $imgname=$addition_def[$field_id]['templatesetvar']."/";
                    }
                    $imgname.=$addition_def[$field_id]['templatesetvar']."_".$id."_".time().".jpg";
                    $imgsrc=$fldary[$field_id];
                    if (DATABOX_uploadImageFile_xml($imgname,$imgsrc,$imgfld)){
                        $fldary[$field_id]=$imgname;
                        //サムネイル作成する場合
                        if($box_conf['imgfile_thumb_ok']==="1" && function_exists('ImageCreate')) {
                            $srcfile=$imgfld.$imgname;
                            $rt=DATABOX_createthumb(
                                $imgname
                                ,$box_conf['imgfile_thumb_w'] ,$box_conf['imgfile_thumb_h'] 
                                ,$box_conf['imgfile_thumb_w2'] ,$box_conf['imgfile_thumb_h2'] 
                                ,$imgfld_thmb,$imgfld);
                        }
                    }else{
                        $fldary[$field_id]="";
                    }
                }
            }
        }
    }

    return $rt;

}
function DATABOX_deleteaddtionfiles(
    $fldary
    ,$addition_def
    ,$pi_name
){
    global $_CONF;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if (is_array($fldary)){
        foreach( $fldary as $field_id => $value ){
            
            //図 外記憶
            if ($addition_def[$field_id]['type']==12){//図
                $a=explode("|",$fldary[$field_id]);
                $filename=$a[0];
                if ($filename<>""){
                    $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
                    $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
                    $rt=DATABOX_deleteFile($imgfld.$filename);
                    $thumbname=DATABOX_getthumbname($filename);
                    $rt=DATABOX_deleteFile($imgfld_thmb.$thumbname);
                }
            }
            //添付ファイル
            if ($addition_def[$field_id]['type']==13){
                $imgfld=$box_conf['file_path'];
                $a=explode("|",$fldary[$field_id]);
                $filename=$a[0];
                if ($filename<>""){
                    $rt=DATABOX_deleteFile($imgfld.$filename);
                }
            }
        }
    }

    return $rt;

}
//
function DATABOX_deleteaddtionfiles_def(
    $field_id
    ,$pi_name
){
    global $_CONF;
    global $_TABLES;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";
    
    $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];
    //
    $sql="SELECT value  ";
    $sql.=" FROM";
    $sql.=" {$table_addition} ";
    $sql.=" where";
    $sql.=" field_id=".$field_id;

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);
    for ($i = 1; $i <= $numrows; $i++) {
        $A = DB_fetchArray ($result);
        $filename = COM_stripslashes($A['value']);
        if ($filename<>""){
            $imgfld=$_CONF['path_html'].$box_conf['imgfile_frd'];
            $imgfld_thmb=$_CONF['path_html'].$box_conf['imgfile_thumb_frd'];
            $rt=DATABOX_deleteFile($imgfld.$filename);
            $thumbname=DATABOX_getthumbname($filename);
            $rt=DATABOX_deleteFile($imgfld_thmb.$thumbname);
        }
    }

    return $rt;

}

function DATABOX_deleteaddtionfiles_all(
    $fld
    ,$pi_name
){
    
    
    
    global $_CONF;
    global $_TABLES;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $rt="";

    if( is_dir( $fld)){
        $fd = opendir( $fld );
        while(( $file = @readdir( $fd )) == TRUE )    {
            if( !is_dir( $fd1 . $file)
                    && $dir <> '.'
                    && $dir <> '..'
                    && $dir <> 'CVS'
                    && substr( $dir, 0 , 1 ) <> '.' ) {
                clearstatcache();
                $rt=DATABOX_deleteFile($fld.$file);
            }
        }
    }

    return $rt;

}

// +---------------------------------------------------------------------------+
// | 機能  追加項目uploadfile delete
// | 書式 DATABOX_checkdditiondatas($filename)
// +---------------------------------------------------------------------------+
// | 引数 $filename:フォルダ/ファイル
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
function DATABOX_deleteFile (
    $filename
)
{
    $rt=false;
    if (file_exists ($filename)) {
        if (!@unlink ($filename)) {
            COM_errorLog ("Unable to remove file $filename");
        }else{
            $rt=true;
            COM_errorLog ("remove file $filename");
        }        
    }else{
            COM_errorLog ("Unable to remove file $filename not exist");
    }
    return $rt;
}

function DATABOX_uploadImageFile (
     $imgname
    ,$imgfile
    ,$imgsize
    ,$imgtype
    ,$imgfld
    ,$newfilename
)
// +---------------------------------------------------------------------------+
// | 機能  upload Image File
// | 書式 DATABOX_uploadImageFile($imgname,$imgfile,$imgsize, $imgtype,$imgfld)
// +---------------------------------------------------------------------------+
// | 引数 $imgname:
// | 引数 $imgfile:
// | 引数 $imgsize:
// | 引数 $imgtype:
// | 引数 $imgfld:
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
{
    $FilePermissions = (int) 0755;

    if (is_uploaded_file ($imgfile)) {
        //$newfile=$imgfld.$imgname;
        $newfile=$imgfld.$newfilename;
        if(!file_exists($newfile)) {
            $returnMove = move_uploaded_file($imgfile, $newfile); 
            if (!$returnMove) {
                $logmsg="DataBox File upload error: ".$tmp." to ".$name;
                COM_errorLOG($logmsg);
                return false;
             } else {
                $chown =@chmod ($newfile,$FilePermissions);
                $logmsg="DataBox File upload sucsess: ".$imgname;
                COM_errorLOG($logmsg);
                return true;
             }
         }else{
            COM_errorLOG("DataBox upload error: Temporary file does not exist: '".$tmp ."'");
            return false;
         }
    }
}
//
function DATABOX_uploadImageFile_xml (
    $imgname
    ,$imgsrc
    ,$imgfld
)
{
    $FilePermissions = (int) 0755;

    $newfile=$imgfld.$imgname;
    //
    $fp = fopen($newfile, "w");
    $imgsrc=base64_decode($imgsrc);
    fputs($fp, $imgsrc);
    fclose($fp);
    
    if(!file_exists($newfile)) {
        $logmsg="DataBox ImageFile upload error: ".$tmp." to ".$name;
        COM_errorLOG($logmsg);
        return false;
    } else {
        $chown =@chmod ($newfile,$FilePermissions);
        $logmsg="DataBox ImageFile upload sucsess: ".$imgname;
        COM_errorLOG($logmsg);
        return true;
    }
}

function DATABOX_createthumb(
    $filename
    , $W
    , $H
    , $W2
    , $H2
    , $imgfld_thmb
    , $imgfld
)
// +---------------------------------------------------------------------------+
// | 機能 uploadfile サムネイル作成
// | 書式 DATABOX_createthumb($srcfile,$W,$H,$W2,$H2, $thumb_dir,$tmp_dir)
// +---------------------------------------------------------------------------+
// | 引数 $imgname:
// | 引数 $imgfile:
// | 引数 $imgsize:
// | 引数 $imgtype:
// | 引数 $imgfld:
// +---------------------------------------------------------------------------+
// | 戻値 nomal:  
// +---------------------------------------------------------------------------+
//update
{
    //$filename = substr($srcfile, strrpos($srcfile,"/")+1);
    $srcfile=$imgfld.$filename;
    // 画像の幅と高さとタイプを取得
    $imagesize = GetImageSize($srcfile);
    $srcfile_w=$imagesize['0'];
    $srcfile_h=$imagesize['1'];
    $srcfile_type=$imagesize['2'];
    //対象タイプの確認
    switch ($srcfile_type) {
        case 1 : //gif
             return false;
             break;
        case 2 : //jpg
             $srcimage = @ImageCreateFromJPEG($srcfile);
             break;
        case 3 : //png
             $srcimage = @ImageCreateFromPNG($srcfile);
             break;
    }
    if (!$srcimage) {
        return false;
    }
    
    $logmsg="size=(w)".$srcfile_w." > ".$w." (h)".$srcfile_h." > ".$h;
    COM_errorLog ($logmsg);

    //対象サイズの確認
    if ($srcfile_w > $w || $srcfile_h > $h) {
    }else{
        return false;
    }
        
    // リサイズ
    if ($srcfile_w > $W || $srcfile_h > $H) {
        $key_w = $W / $srcfile_w;
        $key_h = $H / $srcfile_h;
        ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
        $out_w = $srcfile_w * $keys;
        $out_h = $srcfile_h * $keys;
    } else {
        $out_w = $srcfile_w;
        $out_h = $srcfile_h;
    }

    // 出力画像（サムネイル）のイメージを作成し、元画像をコピーします。(GD2.0用)
    $outimage = ImageCreateTrueColor($out_w, $out_h);
    $resize = ImageCopyResampled(
            $outimage, $srcimage, 0, 0, 0, 0
            , $out_w, $out_h, $srcfile_w, $srcfile_h);

    // サムネイル画像をブラウザに出力、保存
    //$filename = substr($srcfile, strrpos($srcfile,"/")+1);
    $thum_filename = DATABOX_getthumbname($filename);
       $filename = substr($filename, 0, strrpos($filename,"."));
    touch($imgfld_thmb.$thum_filename);
    ImageJPEG($outimage, $imgfld_thmb.$thum_filename);
        
    // 出力画像のイメージを作成し、元画像をコピーします。(GD2.0用)
    // リンク先のサイズが設定値より大きい時リンク先も縮小する<-----
    if ($srcfile_w > $W2 || $srcfile_h > $H2) {
        $rt = rename($imgfld.$filename.".jpg",$imgfld.$filename."-bak.jpg");
        if (!$rt) {
            echo "rename err!";
        }

        $key_w = $W2 / $srcfile_w;
        $key_h = $H2 / $srcfile_h;
        ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
        $out_w = $srcfile_w * $keys;
        $out_h = $srcfile_h * $keys;
        $outimage2 = ImageCreateTrueColor($out_w, $out_h);
        $resize = ImageCopyResampled(
            $outimage2, $srcimage, 0, 0, 0, 0
            , $out_w, $out_h, $srcfile_w, $srcfile_h);

        touch($imgfld.$filename.".jpg");
        ImageJPEG($outimage2, $imgfld.$filename.".jpg"); 

        ImageDestroy($outimage2);
        $rt=unlink($imgfld.$filename."-bak.jpg");
        if (!$rt) {
            echo "delete err!";
        }

   }
    
    // 作成したイメージを破棄
    ImageDestroy($srcimage);
    ImageDestroy($outimage);

    return $thum_filename;

}
//
function DATABOX_getthumbname(
    $filename
)
{
    $rt="";
    if ($filename<>""){
        $filename = substr($filename, 0, strrpos($filename,"."));
        $rt = $filename . "-small.jpg";
    }
    return $rt;
}
//==============================================================================

function DATABOX_getradiolist(
    $ary
    ,$fld
    ,$selected = 0
    ,$br=""
    ,$disabled=""
)
// +---------------------------------------------------------------------------+
// | 機能  ラジヲボタン作成
// | 書式 DATABOX_getradiolist
// |                ($lang_noyes,"afield[".$fid."]",$fvalue,$br ,$disabled);
// +---------------------------------------------------------------------------+
// | 引数 $ary:値配列
// | 引数 $fld:field name
// | 引数 $selected:選択値　省略時
// | 引数 $br:blank 0 改行しない 1〜9 指定数毎に改行
// | 引数 $disabled:
// +---------------------------------------------------------------------------+
// | 戻値 nomal:ラジヲボタン                                                   |
// +---------------------------------------------------------------------------+
//update 20130704
{
    
    $retval = '';
    if  ($br==="" ) {
        $br=0;
    }
    $cnt=0;
    foreach( $ary as $id => $value ){

        //echo "ary".$i."=".$ary[$i]."<br>";
        $retval .= "<input type='radio' NAME='$fld' $disabled VALUE='$id'";
		if  ($selected==""){
		}else{
            if( $id == $selected ){
                $retval .= " CHECKED=CHECKED ";
            }
        }
        $retval .= ">$ary[$id]&nbsp";
        
        if   ($br<>0){
            $cnt++;
            if  ($cnt==$br){
                $retval .="<br".XHTML.">";
                $cnt=0;
            }
        }
    }

    return $retval;
}


function DATABOX_getoptionlistary(
    $ary
    ,$fld
    ,$selected = ""
    ,$pi_name=""
    ,$disabled=""
    ,$type=""
    ,$size=1
)
// +---------------------------------------------------------------------------+
// | 機能  オプションリスト作成（配列より）
// | 書式 DATABOX_getoptionlistary($selectionary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type);
// +---------------------------------------------------------------------------+
// | 引数 $ary:
// | 引数 $fld:
// | 引数 $selected:選択値　省略時
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// | 引数 $disabled:defalt="" 
// | 引数 $type:defalt="" 14=multiselect
// +---------------------------------------------------------------------------+
// | 戻値 nomal:オプションリスト                                               |
// +---------------------------------------------------------------------------+
{

    if ($pi_name==""){
        $lang_box="LANG_DATABOX";
    }else{
        $lang_box="LANG_".strtoupper($pi_name);
    }
    global $$lang_box;
    $lang_box=$$lang_box;

    if  ($type==14 OR $type==18){
        //$selectedary=array[];
        $selectedary=explode(":",$selected);
    }
    if  ($size=="" OR $size==0){
        $size=1;
    }
    $retval = '';

    $retval .= "<select ";
    if ($type==14 OR $type==18){
        $retval .= ' name="'.$fld.'[]" ';
        $retval .= ' multiple="multiple" ';
        $retval .= ' size="'.$size.'"';
    }else{
        $retval .= ' name="'.$fld.'"';
    }
    $retval .= $disabled.">".LB;
    
	if  ($type==7 ){
		if  ($ary[0]==""){
        }else{
            $retval .= "<option value=''>{$lang_box['selectit']}</option>".LB;
        }
	}
    
    foreach($ary as $k => $v) {
        $retval .= "<option value=\"".$k."\"";
		//echo "k=".$k."sa=".$selectedary."s=".$selected."<br>";
		if  ($selected==""){
		}else{
            if  ($type==14 OR $type==18){
                if( in_array($k,$selectedary) ){//@@@@@
                    $retval .= " selected=\"selected\" ";
                }
		    }else{
                if( $k == $selected ){//@@@@@
                    $retval .= " selected=\"selected\" ";
                }
			}
		}
		$retval .= ">";

        $retval .= $v;
        $retval .= "</option>".LB;

    }

    $retval .= '</select>';
    
    return $retval;
}



function DATABOX_getoptionlist(
    $kind
    ,$selected = 0
    ,$relno=0
    ,$pi_name=""
    ,$disabled=""
    ,$selectit=true
    ,$selectlist=""
    ,$fname=""
)
// +---------------------------------------------------------------------------+
// | 機能  オプションリスト作成
// | 書式 DATABOX_getoptionlist($kind)
// |      DATABOX_getoptionlist("fieldset",$fieldset_id,0,$pi_name,"",0 );
// |      DATABOX_getoptionlist("group",$categorygroup_id,0,$pi_name,"",0 );
// +---------------------------------------------------------------------------+
// | 引数 $kind:項目種類
// | 引数 $selected:選択値
// | 引数 $relno
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// | 引数 $disabled
// | 引数 $selectit
// | 引数 $selectlist　既定リストの時既定リスト名
// | 引数 $fname フィールド名
// +---------------------------------------------------------------------------+
// | 戻値 nomal:オプションリスト                                               |
// +---------------------------------------------------------------------------+
//update 20101122,20110613,20120525,20140724
{
global $_CONF;
global $_TABLES;
global $LANG_configselects;
    
    if ($pi_name==""){
        $lang_box="LANG_DATABOX";
    }else{
        $lang_box="LANG_".strtoupper($pi_name);
    }
    global $$lang_box;
    $lang_box=$$lang_box;
    
    if ($fname==""){
        $fname=$kind;
    }
    
    
    $retval = '';

    $retval .= "<select name='$fname' $disabled>".LB;
    if ($selectit==="all"){
        $retval .= "<option value=\"all\">{$lang_box['selectall']}</option>".LB;
    }elseif ($selectit){
        $retval .= "<option value=0>{$lang_box['selectit']}</option>".LB;
    }
    
    
    if ($kind=="layout"){
        $retval .= "<option value=0>{$lang_box['byconfig']}</option>".LB;
        $ary=$LANG_configselects[strtolower($pi_name)][22];
        foreach($ary as $k => $v) {
            $retval .= "<option value=\"".$v."\"";
            if( $v == $selected ){//@@@@@
                $retval .= " selected=\"selected\" ";
            }
            $retval .= ">";

            $retval .= $k;
            $retval .= "</option>".LB;
            next($ary);
        }
    }else if ($kind=="language_id"){
        $retval .= "<option value=0> </option>".LB;
        $ary=$_CONF['languages'];
        foreach($ary as $k => $v) {
            $retval .= "<option value=\"".$k."\"";
            if( $k == $selected ){//@@@@@
                $retval .= " selected=\"selected\" ";
            }
            $retval .= ">";

            $retval .= $v;
            $retval .= "</option>".LB;
            next($ary);
        }
    }else{
        if ($kind=="group"){
            $where="";
            $table=$_TABLES[strtoupper($pi_name).'_def_group'];
            $fields= 'group_id,name,orderno';
        }else if ($kind=="parent"){
            $where="";
            $table=$_TABLES[strtoupper($pi_name).'_def_category'];
            $fields= 'category_id,name,orderno';
        }else if ($kind=="fieldset"){
            $where="";
            $table=$_TABLES[strtoupper($pi_name).'_def_fieldset'];
            $fields= 'fieldset_id,name,fieldset_id';
        }else if ($kind=="selectlist"){
            $where="";
            $table=$_TABLES[strtoupper($pi_name).'_mst'];
            $fields= 'DISTINCT kind,kind,kind';
        }else if ($kind=="selectlist_mst"){
            $table=$_TABLES[strtoupper($pi_name).'_mst'];
            $where="kind='{$selectlist}'";
            if ($relno<>0) $where.=" AND relno='{$relno}'";
            $fields= 'no,value,orderno';
        }else if ($kind=="fieldgroupno"){
            $where="kind='fieldgroup'";
            $table=$_TABLES[strtoupper($pi_name).'_mst'];
            $fields= 'DISTINCT no,value,no';
        }else if ($kind=="csvsel"){
            $where="";
            $table=$_TABLES[strtoupper($pi_name).'_def_csv_sel'];
            $fields= 'csv_sel_id,name,csv_sel_id*-1';
        }else{
            $table=$_TABLES[strtoupper($pi_name).'_mst'];
            $where="kind='{$kind}'";
        if ($relno<>0) $where.=" AND relno='{$relno}'";
            $fields= 'no,value,orderno';
        }
        $retval .= COM_optionList (
            $table
            , $fields
            , $selected,2,$where);
    }
    
    
    $retval .= '</select>';

    if ($disabled==="disabled"){
        $retval .= "  <input type='hidden' name='{$fname}' value='{$selected}'>";
    }

    return $retval;
}


function DATABOX_dltbldt(
    $filenm
    ,$fld
    ,$tbl
    ,$where=""
    ,$order=""
    ,$pi_name=""
    ,$addition=false
)
// +---------------------------------------------------------------------------+
// | 機能  テーブルのデータをCSV形式でダウンロードする
// | 書式 DATABOX_dltbldt($filenm,$fld,$tbl,$where,$order,$type,$pi_name,false);
// +---------------------------------------------------------------------------+
// | 引数 $filenm :
// | 引数 $fld :
// | 引数 $tbl :
// | 引数 $where :
// | 引数 $order :
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $addition:false=追加項目なし、 true=追加項目あり
// +---------------------------------------------------------------------------+
// | 戻値 nomal:
// +---------------------------------------------------------------------------+
//20100910
//@@@@@ 追加予定　カテゴリも
{
    global $_CONF;

    $dm=",";//区切り文字
    $ec="`";//フィールド囲み文字

    if ($addition){
        $addition_def=DATABOX_getadditiondef($pi_name);
    }

    $retval="";

    //file output open
    $curdatetime = date("Y_m_d_H_i_s");
    //$outfile = tempnam($_CONF['path_data'] ."tmp", $filenm);
    $outfile = $_CONF['path_data'] . $filenm."_".$curdatetime;
    $file = @fopen( $outfile, 'w' );
    if ( $file === false ) {
        $retval .= "ERR! ".$outfile ." is not writable!<br ".XHTML.">" . LB;
        return $retval;
    }

    //-----
    $sql = "SELECT DISTINCT ";
    foreach($fld as $k => $v) {
        $sql .=$k.",";
    }
    $sql=rtrim($sql,",");
    $sql.= " FROM ".$tbl;
    if (!empty($where)) {
        $sql.=" WHERE ".$where;
    }
    if (!empty($order)) {
        $sql.=" ORDER BY ".$order;
    }
    //-----
    $result = DB_query ($sql);
    //-----1行目ヘッダ
    $w='';
    foreach($fld as $k => $v) {
        $w .=$ec.$k.$ec.$dm;
    }
    //addition field
    if ($addition){

        if ($addition_def<>""){
            $s=$addition_def;
            foreach($s as $k1 =>$v1){
                $w .=$ec.$s[$k1]['templatesetvar'].$ec.$dm;
            }
        }
    }
    $w=rtrim($w,$dm);

    $w = str_replace( array( '<?', '?>' ), array( '(@', '@)' ),$w );
    fputs( $file, $w.LB);

    //-----2行目ヘッダ
    $w="";
    foreach($fld as $k => $v) {
        $w .=$ec.$v['name'].$ec.$dm;
    }
    //addition field
    if ($addition){
        if ($addition_def<>""){
            $s=$addition_def;
            foreach($s as $k1 =>$v1){
                $w .=$ec.$addition_def[$k1]['name'].$ec.$dm;
            }
        }
    }

    $w=rtrim($w,$dm);

    $w = str_replace( array( '<?', '?>' ), array( '(@', '@)' ),$w );
    fputs( $file, $w.LB);
    //-----3行目以降
    while( $A = DB_fetchArray( $result ) )    {

        $w="";
        foreach($fld as $k => $v) {
            $w .=$ec.$A[$k].$ec.$dm;
        }
//
        if ($addition){
            $wkary=DATABOX_getaddtionfields($A["id"],$addition_def,$pi_name);
            $s=$addition_def;
            foreach($s as $k1 =>$v1){
                //echo "k1=".$k1."<br ".XHTML.">";


                if (isset($wkary[$k1])) {
                //echo "wkary=".$wkary[$k1]."<br>";
                    $w .=$ec.$wkary[$k1].$ec.$dm;
                }else{
                //echo "DD<br>";
                    $w .=$ec.$ec.$dm;
                }
            }
        }
        $w=rtrim($w,$dm);

        $w = str_replace( array( '<?', '?>' ), array( '(@', '@)' ),$w );
        fputs( $file, $w.LB);

    }
    fclose($file);
    
    $filename=basename($outfile).".csv";
    $dir=dirname($outfile);

    header ("Content-Disposition: attachment; filename=$filename");
    header ("Content-type: application/x-csv");
    readfile ($outfile);
    flush();
    
    $rt=unlink($outfile);
    
    return "";
}

function DATABOX_getfeaturedimage(
    $id
    ,$pi_name
    ,$mode="imgsrc"
)
// +---------------------------------------------------------------------------+
// | 機能  featuredimage取得
// | 書式 DATABOX_getfeaturedimage($id,$pi_name)
// | typw14(外画像)表示する先頭（順番が若い）追加項目をfeaturedimageとして取得
// +---------------------------------------------------------------------------+
// | 引数 $id:
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:featuredimage
// +---------------------------------------------------------------------------+
{
    global $_CONF;
    global $_TABLES;

    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;

    $tbl1=$_TABLES[strtoupper($pi_name).'_addition'];
    $tbl2=$_TABLES[strtoupper($pi_name).'_def_field'];

    $rt="";

    $sql = "SELECT t1.value";
    $sql .= " FROM ";
    $sql .= " {$tbl1} AS t1";
    $sql .= "  ,{$tbl2} AS t2";
    $sql .= "  WHERE t1.id=".$id;
    $sql .= "  AND t1.field_id =t2.field_id";
    $sql .= "  AND t2.type=12";
    $sql .= "  AND allow_display=0";
    $sql .= "  ORDER BY orderno";

    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);

    if ($numrows > 0) {
        $A = DB_fetchArray ($result);
        $value=$A['value'];
        if  ($value<>""){
            $url=$_CONF['site_url']   ."/".$box_conf['imgfile_frd'] ;
            $url.= $value ;
            if  ($mode=="imgsrc"){
                $rt='<img src="'.$url.'" />';
            }else{
                $rt=$url;
            }
        }
    }

    return $rt;
}

function DATABOX_getheadercode(
    $kind
    ,$template
    ,$pi_name
    ,$id
    ,$title
    ,$meta_description
    ,$meta_keywords
    ,$description
    ,$additionfields=""
    ,$addition_def=""
    ,$chk_user=""
    ,$fieldset_id=""
    ,$fieldset_name=""
)
// +---------------------------------------------------------------------------+
// | 機能 HEADERCODE 編集
// | 書式 DATABOX_getheadercode("data",$template,$pi_name,$A['id'],$A['title']
// |         ,$A['meta_description'],$A['meta_keyword'],$A['description']);
// +---------------------------------------------------------------------------+
// | 引数  $kind        "data","category"
// | 引数  $template
// | 引数  $pi_name
// | 引数  $id
// | 引数  $title
// | 引数  $meta_description
// | 引数  $meta_keywords
// | 引数  $description
// +---------------------------------------------------------------------------+
// | 戻値 nomal:HEADERCODE
// +---------------------------------------------------------------------------+
{
    global $_CONF;
    global $_TABLES;
    global $_IMAGE_TYPE;
    
    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;
    
    $retval="";
    
    $currenturl= COM_getCurrentURL();
    $site_logo= $_CONF['layout_url'] . '/images/logo.' . $_IMAGE_TYPE ;

    $og_type="article";
    $og_title=$title;
    
    if ($box_conf['meta_tags'] > 0) {
         $retval .= COM_createMetaTags($meta_description, $meta_keywords);
    }

    if ($_CONF['meta_tags']==0 OR $meta_description=="") {
        $og_description = $description;
    }else{
        $og_description = $meta_description;
    }
    $og_description= COM_getTextContent(PLG_replaceTags($og_description));
        
    
    if ($kind==="data"){
        $og_image =DATABOX_getfeaturedimage($id,$pi_name,"url");
        if ($og_image===""){
            $og_image=$site_logo;
        }
    }else{
        $og_image =$box_conf["default_img_url"];
        if ($og_image===""){
            $og_image=$site_logo;
        }
    }
    
    //テンプレートフォルダの設定
    $tmplfld=DATABOX_templatePath($kind,$template,$pi_name);
    if (file_exists ($tmplfld."/headercode.thtml")) {
        $tpl = new Template($tmplfld);
        $tpl->set_file (array (
            'tpl' => 'headercode.thtml',
            ));
    
        $tpl->set_var('xhtml', XHTML);
        $tpl->set_var('site_url', $_CONF['site_url']);
        $tpl->set_var('site_admin_url', $_CONF['site_admin_url']);
        $tpl->set_var('layout_url', $_CONF['layout_url']);
        
        $tpl->set_var ('currenturl', htmlspecialchars($currenturl, ENT_QUOTES, 'UTF-8'));
        
        $tpl->set_var ('site_name', $_CONF['site_name']);
        $tpl->set_var ('site_mail', $_CONF['site_mail']);
        
        $tpl->set_var ('og_title', $og_title);
        $tpl->set_var ('og_image', $og_image);
        $tpl->set_var ('og_description', $og_description);
        $tpl->set_var ('og_type', $og_type);
        $tpl->set_var ('fieldset_name', $fieldset_name);
        
        //facebook
        $facebook_consumer_key = trim($_CONF['facebook_consumer_key']);
        $tpl->set_var ('facebook_consumer_key', $facebook_consumer_key);
        
        if ($kind==="data"){
            DATABOX_getaddtionfieldsDisp($additionfields,$addition_def,$tpl,$chk_user,$pi_name,$fieldset_id);
        }
    
        $tpl->parse ('output', 'tpl');
        $retval .= $tpl->finish ($tpl->get_var ('output'));
    }
    return $retval;
}

function DATABOX_getfields(
    $pi_name
    , $fieldset_id
)
{
    global $_TABLES;
    
    if ($fieldset_id==0){
        $table=$_TABLES[strtoupper($pi_name).'_def_field'];
    }else{
        $table=$_TABLES[strtoupper($pi_name).'_def_fieldset_assignments'];
    }
    $fields = array();

    // Get a list of additionfields in the selected field
    $sql  = "SELECT DISTINCT field_id ";
    $sql  .= " FROM {$table}";
    
    if ($fieldset_id<>0){
        $sql  .= " WHERE  ";
        $sql  .= " fieldset_id = $fieldset_id";
    }
    
    $result = DB_query ($sql);
    $num = DB_numRows ($result);
    
    if ($num<>0){
        while ($A = DB_fetchArray($result)) {
            $fields[] = $A['field_id'];
        }
    }
    
    return $fields;
}


function DATABOX_getAry(
    $kind
    ,$relno=0
    ,$selectlist
    ,$pi_name=""
)
{
    global $_TABLES;

//    if ($kind=="selectlist_mst"){
        $table=$_TABLES[strtoupper($pi_name).'_mst'];
        $where=" WHERE kind='{$selectlist}'";
        if ($relno<>0) $where.=" AND relno='{$relno}'";
        $fields= 'no,value';
        $order=" ORDER BY orderno"; 
//    }

    $ary=array();
    $sql = "SELECT ";
    $sql .= $fields ;
    $sql .= " FROM " ;
    $sql .= $table ;
    $sql .= $where ;
    $sql .= $order ;

    $result = DB_query($sql);
    $nrows = DB_numRows($result);
    for( $i=0; $i < $nrows; $i++) {
         $A = DB_fetchArray($result, true);
         $ary[$A[0]] = $A[1];
    }
    return $ary;
}

function DATABOX_categorycount(
    $pi_name
    ,$categorygroup_id 
    ,$categorygroup_code 
    ,$permission=""
    ,$numberformat=''
)
// +---------------------------------------------------------------------------+
// | 機能  カテゴリ件数表示
// +---------------------------------------------------------------------------+
// | 書式  databox_categorycount()
// | 書式  databox_categorycount(1)
// | 書式  databox_count("","japan")
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin名
// | 引数 $group_id:カテゴリグループid
// | 引数 $group_code:カテゴリグループコード
// | カテゴリグループid もカテゴリグループコードも指定ない場合は
// | 親カテゴリグループなしのカテゴリの件数
// | 引数 $permission    :ブランク　または　ignore
// | 引数 $numberformat  :yes
// +---------------------------------------------------------------------------+
// | 戻値 nomal:カテゴリ件数
// | （アクセス権のあるデータが登録されていないカテゴリの件数は含みません）
// +---------------------------------------------------------------------------+
{
    global $_TABLES;
    //-----
    $tbl3=$_TABLES[strtoupper($pi_name).'_def_category'] ;
    $tbl1=$_TABLES[strtoupper($pi_name).'_category'] ;
    $tbl4=$_TABLES[strtoupper($pi_name).'_def_group'] ;
    $tbl2=$_TABLES[strtoupper($pi_name).'_base'] ;
    if ($categorygroup_id =="" AND $categorygroup_code=="") {
        $categorygroup_id=0;
    }else{
        if  ($categorygroup_id==""){
            $categorygroup_id=DATABOX_codetoid(    $categorygroup_code,strtoupper($pi_name).'_def_group',"group_id");
            if  ($categorygroup_id==0){
                return 0;
            }
        }
    }
    

    //-----
    $sql = "SELECT ".LB;
    $sql .= " t1.category_id".LB;
    //$sql .= " ,Count(t1.id) AS count".LB;

    $sql .= " FROM ".LB;
    $sql .= " {$tbl1} AS t1 ".LB;
    $sql .= " ,{$tbl2} AS t2 ".LB;
    $sql .= " ,{$tbl3} AS t3 ".LB;
    $sql .= " ,{$tbl4} AS t4 ".LB;

    $sql .= " WHERE ".LB;
    $sql .= " t1.id = t2.id ".LB;
    $sql .= " AND t1.category_id = t3.category_id ".LB;
    $sql .= " AND t3.categorygroup_id = ".$categorygroup_id.LB;
    $sql .= " AND t3.categorygroup_id = t4.group_id ".LB;
    //管理者の時,下書データも含む
    //if ( SEC_hasRights('databox.admin')) {
    //}else{
       $sql .= " AND t2.draft_flag=0".LB;
    //}
    //アクセス権のないデータ はのぞく
    if  (strtoupper($permission)=="IGNORE"){
    }else{
        $sql .= COM_getPermSql('AND',0,2,"t2").LB;
    }
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())".LB;
    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())".LB;
    
    $sql .= " GROUP BY ".LB;
    $sql .= " t1.category_id".LB;
    
    $result = DB_query ($sql);
    $cnt = DB_numRows ($result);
    if  (strtoupper($numberformat)=="YES"){
        $rt=COM_NumberFormat($cnt);
    }else{
        $rt=$cnt;
    }

    return $rt;

}

function DATABOX_Confirmation(
    $pi_name
    ,$mode
)
// +---------------------------------------------------------------------------+
// | 機能 確認画面
// | 書式 Con_firmation("databox")
//   fnc_Confirmation proversion
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin名
// | 引数 $mode:カテゴリグループid
// +---------------------------------------------------------------------------+
// | 戻値 nomal:
// +---------------------------------------------------------------------------+
{
    global $_CONF;
    global $LANG_ADMIN;


    $lang_box_admin="LANG_".strtoupper($pi_name)."_ADMIN";
    global $$lang_box_admin;
    $lang_box_admin=$$lang_box_admin;

    $tmpl = new Template ($_CONF['path'] . "plugins/".THIS_PLUGIN."/templates/admin/");
    $tmpl->set_file(array('confirm' => 'confirmation.thtml'));


    $tmpl->set_var('site_admin_url', $_CONF['site_admin_url']);
    $tmpl->set_var('gltoken_name', CSRF_TOKEN);
    $tmpl->set_var('gltoken', SEC_createToken());
    $tmpl->set_var ( 'xhtml', XHTML );

    //
    $actionname=$lang_box_admin[$mode];
    $actionprg="{$_CONF['site_admin_url']}/plugins/".THIS_SCRIPT."?mode={$mode}exec";

    $tmpl->set_var('actionname', $actionname);
    $tmpl->set_var('actionprg', $actionprg);
    
    $tmpl->set_var('help', $lang_box_admin[$mode.'msg']);
    $btn_value=$LANG_ADMIN['submit'];
    $confirm_value=$lang_box_admin['confirm'];
    $btn2="";
    $btn3="";
    if ($mode==="datadelete"){
        $tmpl->set_var('lang_checklist', $lang_box_admin['fieldset'].":");
        $checklist=DATABOX_getcheckList ("fieldset","",$pi_name);
        $tmpl->set_var('checklist', $checklist);
        $btn_value=$lang_box_admin['delete1'];
        $confirm_value=$lang_box_admin['delete1']." ".$lang_box_admin['confirm'];
        $confirm_value2=$lang_box_admin['delete2']." ".$lang_box_admin['confirm'];
        $confirm_value3=$lang_box_admin['delete3']." ".$lang_box_admin['confirm'];
        $btn2="<input type='submit' name='action' value='{$lang_box_admin['delete2']}'" 
             ." onclick=\"return confirm('{$confirm_value2}');\"".xhtml.">".LB;
        $btn3="<input type='submit' name='action' value='{$lang_box_admin['delete3']}'" 
             ." onclick=\"return confirm('{$confirm_value3}');\"".xhtml.">".LB;
    }

    // SAVE、CANCEL ボタン
    $tmpl->set_var('lang_confirm', $confirm_value);
    $tmpl->set_var('lang_submit', $btn_value);
    $tmpl->set_var('btn2', $btn2);
    $tmpl->set_var('btn3', $btn3);
    $tmpl->set_var('lang_cancel', $LANG_ADMIN['cancel']);
    $tmpl->set_var('mode', $mode."exec");

    $tmpl->parse ('output', 'confirm');
    $comfirm = $tmpl->finish ($tmpl->get_var ('output'));

    $retval = $comfirm;


    return $retval;
}

//==============================================================================
//lib_datetimeedit.php →
function DATABOX_datetimeedit(
    $datetime_value
    ,$lang
    ,$datetime_name="datetime"
    ,$mode="datetime"
) {
    global $_CONF;

    $var = $lang;
    global $$var;
    $lang_ary=$$var;
    if ($datetime_value==""){
        $datetime_year = date('Y');
        $datetime_month = date('m');
        $datetime_day = date('d');
        //
        $datetime_hour = date ('H');
        $datetime_minute = date ('i') ;
    }else{
        $datetime_year = date('Y', $datetime_value);
        $datetime_month = date('m', $datetime_value);
        $datetime_day = date('d', $datetime_value);
        //
        $datetime_hour = date ('H', $datetime_value);
        $datetime_minute = date ('i', $datetime_value) ;
    }
    
    $rt="";
    //Year
    $year_options = COM_getYearFormOptions ($datetime_year);
    $rt.="<select name=\"".$datetime_name."_year\">".LB;
    $rt.=$year_options.LB;
    $rt.="</select>".$lang_ary['yy'].LB;

    //month
    $month_options = COM_getMonthFormOptions ($datetime_month);
    $rt.="<select name=\"".$datetime_name."_month\">".LB;
    $rt.=$month_options.LB;
    $rt.="</select>".$lang_ary['mm'].LB;

    //day
    $day_options = COM_getDayFormOptions ($datetime_day);
    $rt.="<select name=\"".$datetime_name."_day\">".LB;
    $rt.=$day_options.LB;
    $rt.="</select>".$lang_ary['dd'].LB;

    //hour
    if ($mode==="datetime"){
        if ($_CONF['hour_mode'] == 24) {
            $hour_options = COM_getHourFormOptions ($datetime_hour, 24);
        }else{
            $datetime_hour_wk=$datetime_hour;
            if ($datetime_hour_wk >= 12) {
                if ($datetime_hour_wk > 12) {
                    $datetime_hour_wk = $datetime_hour_wk - 12;
                }
                $ampm = 'pm';
            } else {
                $ampm = 'am';
            }
            $ampm_select = COM_getAmPmFormSelection ($datetime_name.'_ampm', $ampm);
            if (empty ($ampm_select)) {
                $ampm_select = '<input type="hidden" name="cmt_close_ampm" value=""' . XHTML . '>';
            }
            $rt.=$ampm_select;
            $hour_options = COM_getHourFormOptions ($datetime_hour_wk);
        }

        $rt.="<select name=\"".$datetime_name."_hour\">".LB;
        $rt.=$hour_options.LB;
        $rt.="</select>:".LB;

        //minute
        $minute_options = COM_getMinuteFormOptions ($datetime_minute);

        $rt.="<select name=\"".$datetime_name."_minute\">".LB;
        $rt.=$minute_options.LB;
        $rt.="</select>".LB;

    }

    return $rt;

}

function DATABOX_datetimeedit_R(
    $datetime_ary
    ,$lang
    ,$datetime_name="datetime"
) {
    global $_CONF;

    $var = $lang;
    global $$var;
    $lang_ary=$$var;
    
    if  (!is_array($datetime_ary)){
        return "";
    }
    
    $rt="";
    
    //Year
    $year_options = COM_getYearFormOptions ($datetime_ary['year']);
    $rt.="<select name=\"".$datetime_name."_year\">".LB;
    $rt.=$year_options.LB;
    $rt.="</select>".$lang_ary['yy'].LB;

    //month
    $month_options = COM_getMonthFormOptions ($datetime_ary['month']);
    $rt.="<select name=\"".$datetime_name."_month\">".LB;
    $rt.=$month_options.LB;
    $rt.="</select>".$lang_ary['mm'].LB;

    //day
    $day_options = COM_getDayFormOptions ($datetime_ary['day']);
    $rt.="<select name=\"".$datetime_name."_day\">".LB;
    $rt.=$day_options.LB;
    $rt.="</select>".$lang_ary['dd'].LB;

    //hour
    if ($datetime_ary['hour']<>""){
        if ($_CONF['hour_mode'] == 24) {
            $hour_options = COM_getHourFormOptions ($datetime_ary['hour'], 24);
        }else{
            $ampm_select = COM_getAmPmFormSelection ($datetime_name.'_ampm', $datetime_ary['ampm']);
            if (empty ($ampm_select)) {
                $ampm_select = '<input type="hidden" name="cmt_close_ampm" value=""' . XHTML . '>';
            }
            $rt.=$ampm_select;
            $hour_options = COM_getHourFormOptions ($datetime_ary['hour']);
        }

        $rt.="<select name=\"".$datetime_name."_hour\">".LB;
        $rt.=$hour_options.LB;
        $rt.="</select>:".LB;

        //minute
        $minute_options = COM_getMinuteFormOptions ($datetime_ary['minute']);

        $rt.="<select name=\"".$datetime_name."_minute\">".LB;
        $rt.=$minute_options.LB;
        $rt.="</select>".LB;

    }

    return $rt;

}
//==============================================================================
//lib_outlog.php →
//log 出力モード設定　0:作成しない,1:ファイルに出力 2:画面にも出力
//$logmode =2;
//$logfile = $_CONF['path_log'] . "profile_point.log";

//echo OutLog( "----- photomailcheck.php start",$logmode);


// ログ出力処理
function DATABOX_OutLog( $logentry ,$logfile,$logmode=1)
{
    global $_CONF,$LANG01;

    $retval = '';

    if (!empty($logmode)) {
        if( !empty( $logentry )) {
            $logentry = str_replace( array( '<?', '?>' ), array( '(@', '@)' ),
                                     $logentry );

            $timestamp = strftime( '%c' );

            if (!file_exists($logfile)) {
                echo $logfile ."  doesn't exist. ";
                exit();
            }
            if (!is_writable($logfile)) {
                echo $logfile ." cannot be written. ";
                exit();
            }

            $file = fopen( $logfile, 'a' );

            if( empty($file)) {
                $retval .= $LANG01[33] . ' ' . $logfile . ' (' . $timestamp . ')<br/><br/>' . LB;
            } else {
                fputs( $file, "$timestamp - $logentry\n" );
            }

            if ($logmode ==2){
                $retval .="$timestamp - $logentry<br/>" . LB;
            }
        }
    }

    return $retval;
}
//==============================================================================
//lib_getfilelist.php →
// 指定フォルダのファイルの一覧を取得
function DATABOX_getfilelist(
    $fd
    ,$exte =""
)
{
    $fary= array();
    $dir=opendir($fd);
    while(($ent = readdir()) !==FALSE){
        if  ($exte=="") {
           $fary[$i] = $ent;
           $i++;
        }else{
            $w=explode('.',$ent);
            if (strtolower($w[1])===$exte){
               $fary[$i] = $ent;
               $i++;
            }
        }
    }
    return $fary;

}
function DATABOX_getDateTimeFormat( $date,$format )
{
    if( empty( $date ))    {
        $stamp = time();
    } else if( is_numeric( $date )){
        $stamp = $date;
    }else{
        $stamp = strtotime( $date );
    }
    $date = strftime( $format, $stamp );
    return array( $date, $stamp );
}
function DATABOX_getTimeFormat( $value )
{
    $rt="";
    if  ($value==""){
        $rt="";
    }else{
        if  ($_CONF['hour_mode']=='24'){
            $rt=date('H:i', $value );
        }else{
            $rt=date('h:i', $value );
            if  (date('H',$value)>=12){
                $rt.=" PM";
            }else{
                $rt.=" AM";
            }
        }
    }
    return $rt;
}

function DATABOX_fieldvalue(
    $pi_name
    ,$id
    ,$code
    ,$field_id
    ,$field_code
    ,$option = ""
)
// +---------------------------------------------------------------------------+
// | 機能  追加項目表示用取得
// | 書式 DATABOX_fieldvalue($pi_name,$id,$code,$field_id,$field_code,$option)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $id:
// | 引数 $code:
// | 引数 $field_id:
// | 引数 $field_code:
// | 引数 $option:'','name','description','yes','value2','disp','no'
// +---------------------------------------------------------------------------+
// | 戻値 nomal:追加項目表示用
// +---------------------------------------------------------------------------+
{
    global $_TABLES;
    global $_CONF;
    global $_PLUGINS;
    
    $lang_noyes="LANG_".strtoupper($pi_name)."_NOYES";
    global $$lang_noyes;
    $lang_noyes=$$lang_noyes;
          
    $lang_box="LANG_".strtoupper($pi_name);
    global $$lang_box;
    $lang_box=$$lang_box;
    $separater=$lang_box['field_separater'];

    $table_base=$_TABLES[strtoupper($pi_name).'_base'];
    $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];
    $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];
    $table_def_fieldset_assignments
        =$_TABLES[strtoupper($pi_name).'_def_fieldset_assignments'];
    //-----引数チェック
    if (is_null($id)){
        if (is_null($code) OR $code===""){
            $id=0;
        }else{
            if ($pi_name=="userbox"){
                $id=DATABOX_codetoid($code,'users',"uid","username");
            }else{
                $id=DATABOX_codetoid($code,strtoupper($pi_name).'_base');
            }
        }
	}
    if ($id===0) {
        return "";
    }
    if (is_null($field_id)){
        if (is_null($field_code) OR $field_code===""){
            $field_id=0;
        }else{
            $field_id=DATABOX_codetoid($field_code,strtoupper($pi_name).'_def_field'
                     ,"field_id","templatesetvar");
        }
    }
    if ($field_id===0) {
        return "";
    }
    $fieldset_id=DB_getItem( $table_base,"fieldset_id","id=".$id  );
    if  ($fieldset_id==0){
    }else{
        $w=DB_getItem( $table_def_fieldset_assignments
             ,"fieldset_id","fieldset_id=$fieldset_id AND field_id=".$field_id );
        if  ($w==""){
            return "";
        }
    }
          
    //
    $sql  = "SELECT DISTINCT ".LB;
    $sql  .= " t.value ".LB;
    $sql  .= " ,m.type ".LB;
    $sql  .= " ,m.name ".LB;
    $sql  .= " ,m.description ".LB;
    $sql  .= " ,m.selection ".LB;
    $sql  .= " ,m.selectlist ".LB;
    $sql  .= " ,m.dfid ".LB;
    $sql  .= " FROM ".LB;
    $sql  .= "{$table_addition} AS t".LB;
    $sql  .= " , {$table_def_field} AS m".LB;
    
    $sql  .= " WHERE  ".LB;
    $sql  .= " t.id = {$id} AND t.field_id = {$field_id} ".LB;
    $sql  .= " AND m.field_id = {$field_id} ".LB;
    $sql  .= " AND m.allow_display = 0 ".LB;
    
    $result = DB_query ($sql);
    $num = DB_numRows ($result);
    $rt="";
    if ($num<>0){
        $A = DB_fetchArray ($result);
        $A = array_map('stripslashes', $A);
        if  ($option=="name"){
            $rt=$A['name'];
        }else if ($option=="description"){
            $rt=$A['description'];
        }else if ($option=="datatitle"){
            if (in_array('databox', $_PLUGINS)) {
                $rt=COM_applyFilter(DB_getItem( $_TABLES['DATABOX_base'] ,"title","code='{$A['value']}'"));
            }
        }else if ($option=="datadescription"){
            if (in_array('databox', $_PLUGINS)) {
				$rt=COM_applyFilter(DB_getItem( $_TABLES['DATABOX_base'] ,"description","code='{$A['value']}'"));
            }
        }else{
            $selectionary=DATABOX_getselectionary($A['selection']);
            $selectlist=$A['selectlist'];
            $type=$A['type'];
            $value=$A['value'];
            $dfid= $A['dfid'];
            $format = DB_getItem($_TABLES['dateformats'], "format", "dfid = $dfid");
            if( empty( $format )) {
                $format = $_CONF['date'];
            }
            switch ($type) {
                case 12://画像（外記憶）
                case 13://添付ファイル
                    if  ($value===""){
                    }else{
                        list($readfile,$rt)=explode("|",$value);
                    }
                    break;
                case 11://画像（DB記憶)
                    if  ($value===""){
                    }else{
                        $rt="<img src=\"data:image/jpg;base64,";
                        $rt .="{$value}\"".XHTML.">";
                    }
                    break;
                case 2://いいえ/はい
                    if  ($option=="yes"){
                        if  ($value==1){
                            $rt=1;
                        }
                    }else{
                        $rt= $lang_noyes[$value];
                    }
                    break;
                case 7://オプションリスト（選択肢）
                case 8://ラジオボタンリスト（選択肢）
                    $rt= $selectionary[$value];
                    break;
                case 14://マルチセレクトリスト（選択肢）
                    $wkary=explode(":",$value);
                    $wk="";
                    for( $x = 0; $x < sizeof( $wkary ); $x++ ) {
                        $wk.=$selectionary[$wkary[$x]].$separater;
                    }
                    $rt= rtrim($wk,$separater);
                    break;
                case 10:// 複数行テキストフィールド(HTML NG)
                    $w=DATABOX_nl2br($value);
                    $w=DATABOX_autolink($w);
                    $rt= $w;
                    break;
                case 9://オプションリスト（マスター）
                case 16: //ラジオボタン（マスター）
                    $mstitems=DATABOX_getMstItems($selectlist,$value,$pi_name);
                    $rt= $mstitems['value'];
                    if  ($option=="value2"){
                        $rt= $mstitems['value2'];
                    }else if  ($option=="disp"){
                        $rt= $mstitems['disp'];
                    }else if  ($option=="no"){
                        $rt= $mstitems['no'];
                    }else{
                        $rt= $mstitems['value'];
                    }
                    break;
                case 18://マルチセレクトリスト（マスター）
                    $wkary=explode(":",$value);
                    $wk="";
                    $wk_value2="";
                    $wk_disp="";
                    $wk_no="";
                    for( $x = 0; $x < sizeof( $wkary ); $x++ ) {
                        $mstitems=DATABOX_getMstItems($selectlist,$wkary[$x],$pi_name);
                        $wk.=$mstitems['value'].$separater;
                        $wk_value2.=$mstitems['value2'].$separater;
                        $wk_disp.=$mstitems['disp'].$separater;
                        $wk_no.=$mstitems['no'].$separater;
                    }
                    if  ($option=="value2"){
                        $rt= rtrim($wk_value2,$separater);
                    }else if  ($option=="disp"){
                        $rt= rtrim($wk_disp,$separater);
                    }else if  ($option=="no"){
                        $rt= rtrim($wk_no,$separater);
                    }else{
                        $rt= rtrim($wk,$separater);
                    }
                    break;
                case 4://日時
                    if  ($value<>""){
                        $w=DATABOX_getDateTimeFormat($value,$format);
                        $rt= $w[0];
                    }
                    break;
                case 3://日付
                    if  ($value<>""){
                        $rt=strftime( $_CONF['shortdate'], $value );
                    }
                    break;
                case 21://money 通貨;
                    $dc = $_CONF['decimal_count'];
                    $ts = $_CONF['thousand_separator'];
                    $ds = $_CONF['decimal_separator'];
                    $rt= number_format( (float)$value, $dc, $ds, $ts );
                    break;
                case 5://メールアドレス
                case 6://url';
                case 0:// 一行テキストフィールド
                case 1:// 複数行テキストフィールド(HTML OK)
                case 20:// 複数行テキストフィールド(HTML OK  TinyMCE)
                case 19:// 複数行テキストフィールド(HTML OK  CKEditor)
                default:
                    $rt= $value;
            }
        }
    }

    return $rt;

}
function DATABOX_fieldownload(
    $pi_name
    ,$id
    ,$code
    ,$field_id
    ,$field_code
    ,$mode
)
// +---------------------------------------------------------------------------+
// | 機能  ファイルダウンロード 
// | 書式 DATABOX_fieldownload($pi_name,$id,$code,$field_id,$field_code)
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $id:
// | 引数 $code:
// | 引数 $field_id:
// | 引数 $field_code:
// | 引数 $mode ナシ、display
// +---------------------------------------------------------------------------+
// | 戻値 nomal:
// +---------------------------------------------------------------------------+
{
    global $_TABLES;
    global $_CONF;
     
    $box_conf="_".strtoupper($pi_name)."_CONF";
    global $$box_conf;
    $box_conf=$$box_conf;
          
    $lang_box="LANG_".strtoupper($pi_name);
    global $$lang_box;
    $lang_box=$$lang_box;
    $separater=$lang_box['field_separater'];

    $table_base=$_TABLES[strtoupper($pi_name).'_base'];
    $table_def_field=$_TABLES[strtoupper($pi_name).'_def_field'];
    $table_addition=$_TABLES[strtoupper($pi_name).'_addition'];
    $table_def_fieldset_assignments
        =$_TABLES[strtoupper($pi_name).'_def_fieldset_assignments'];
    //-----引数チェック
    if (is_null($id)  OR $id===""){
        if (is_null($code) OR $code===""){
            $id=0;
        }else{
            if ($pi_name=="userbox"){
                $id=DATABOX_codetoid($code,'users',"uid","username");
            }else{
                $id=DATABOX_codetoid($code,strtoupper($pi_name).'_base');
            }
        }
	}
    if ($id===0) {
        return "download argument error id";
    }
    if (is_null($field_id) OR $field_id===""){
        if (is_null($field_code) OR $field_code===""){
            $field_id=0;
        }else{
            $field_id=DATABOX_codetoid($field_code,strtoupper($pi_name).'_def_field'
                     ,"field_id","templatesetvar");
        }
    }
    if ($field_id===0) {
        return "download Argument error field_id";
    }
    $fieldset_id=DB_getItem( $table_base,"fieldset_id","id=".$id  );
    if  ($fieldset_id==0){
    }else{
        $w=DB_getItem( $table_def_fieldset_assignments
             ,"fieldset_id","fieldset_id=$fieldset_id AND field_id=".$field_id );
        if  ($w==""){
            return "download drgument error id field_id";
        }
	}
    //
    $sql  = "SELECT DISTINCT ".LB;
    $sql  .= " t.value ".LB;
    $sql  .= " ,m.allow_display ".LB;
    $sql  .= " ,d.group_id ".LB;
    $sql  .= " ,d.owner_id ".LB;
    $sql  .= " FROM ".LB;
    $sql  .= "{$table_addition} AS t".LB;
    $sql  .= " , {$table_def_field} AS m".LB;
    $sql  .= " , {$table_base} AS d".LB;
    
    $sql  .= " WHERE  ".LB;
    $sql  .= " t.id = {$id} AND t.field_id = {$field_id} ".LB;
    $sql  .= " AND m.field_id = {$field_id} ".LB;
    $sql  .= " AND m.type = 13 ".LB;
    $sql  .= " AND d.id = {$id} ".LB;
    //下書データはのぞく
    $sql .= " AND d.draft_flag=0".LB;
    //公開日以前のデータはのぞく
    $sql .= " AND (released <= NOW())";
    //公開終了日を過ぎたデータはのぞく
    $sql .= " AND (expired=0 OR expired > NOW())";
    //アクセス権のないデータ はのぞく
    $sql .= COM_getPermSql('AND');
	$result = DB_query ($sql);
    $num = DB_numRows ($result);
    $rt="Target file is not found";
    if ($num<>0){
        $A = DB_fetchArray ($result);
        $A = array_map('stripslashes', $A);
		$value=$A['value'];
        $group_id = $A['group_id'];
		$owner_id = $A['owner_id'];
		$allow_display= $A['allow_display'];
        $chk_user=DATABOX_chkuser($group_id,$owner_id,$pi_name.".admin");
		if  ($value<>""){
			
            $chk=false;
            //表示する
            if  ($allow_display==='0') {
                $chk=true;
                //ログインユーザのみ
            }else if ($allow_display==='1'){
                if  ($chk_user>=1000) {
                    $chk=true;
                }
            //グループとadmin権のある人のみ表示
            }else if ($allow_display==='2'){
                if  ($chk_user>=1010) {
                    $chk=true;
                }
            //所有者とadmin権のある人のみ
            }else if ($allow_display==='3'){
                if  ($chk_user>=1011) {
                    $chk=true;
                }
            //admin権のある人のみ
            }else if ($allow_display==='4'){
                if  ($chk_user>=1100) {
                    $chk=true;
                }
            }
			if ($chk) {
				list($readfile,$filename)=explode("|",$value);
		        list($dummy,$extention)=explode(".",$filename);
		        $filetype=DATABOX_getFileTypeAry($extention );
		        if  ($filetype<>""){
                    $path=$box_conf['file_path'];
                    if  (file_exists($path.$readfile)){
                        if  ($mode=="display"){
                            if  ($extention=="pdf"){
                                header('Content-Type: '.$filetype);
                                header('Content-Disposition: inline; filename="'.$filename.'"');
                                readfile($path.$readfile);
                                //download log
                                //download count
                                exit;
                            }
                        }else{
                            header('Content-Type: '.$filetype);
                            header('Content-Disposition: attachment; filename="'.$filename.'"');
                            readfile($path.$readfile);
                            //download log
                            //download count
                            exit;
                        }
                    }
                }
            }
        }
    }
    
    return $rt;

}
function DATABOX_getFileTypeAry(
    $extension 
)
{
    $ary=array();
    $ary['odt']="application/vnd.oasis.opendocument.text";//open office writer*
    $ary['doc']="application/msword";//Microsoft Word
    $ary['docx']="application/vnd.openxmlformats";// Microsoft Office Word 2007*
    $ary['html']="text/html";
    $ary['odb']="application/octet-stream ";//OpenOffice Base*
    $ary['odf']="application/vnd.oasis.opendocument.formula";//OpenOffice Calc*
    $ary['odg']="application/vnd.oasis.opendocument.graphics";//OpenOffice Draw*
    $ary['odm']="application/vnd.oasis.opendocument.text-master";// OpenOffice Writer*
    $ary['odp']="application/vnd.oasis.opendocument.presentation";//OpenOffice Impress*
    $ary['ods']="application/vnd.oasis.opendocument.spreadsheet";//OpenOffice Calc*
    $ary['otg']="application/vnd.oasis.opendocument.graphics-template";//OpenOffice Draw*
    $ary['oth']="application/octet-stream";// OpenOffice.org Writer*
    $ary['otp']="application/vnd.oasis.opendocument.presentation-template";//OpenOffice Impress*
    $ary['ots']="application/octet-stream";//OtsAV*
    $ary['ott']="application/vnd.oasis.opendocument.formula-template ";//OpenOffice Writer*
    $ary['oxt']="application/vnd.openofficeorg.extension";//OpenOffice *
    $ary['pdf']="application/pdf";
    $ary['ppt']="application/mspowerpoint";//Microsoft PowerPoint*
    $ary['pptx']="application/vnd.openxmlformats";//Microsoft Office PowerPoint 2007*
    $ary['txt']="text/plain";
    $ary['xls']="application/vnd.ms-excel";//Microsoft Excel*
    $ary['xlsx']="application/vnd.openxmlformats ";//Microsoft Office Excel 2007*
    $ary['xml']="text/xml";
	if  ($extention===""){
		$rt= $ary;
	}else{
		$rt= $ary[$extension];
	}
	return $rt;
}

// +---------------------------------------------------------------------------+
// | 機能  指定追加項目の指定データに一致する件数表示
// +---------------------------------------------------------------------------+
// | 書式  DATABOX_count_each_afield("class","1")
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $templatesetvar:追加項目のテーマ変数
// | 引数 $value:指定データ値
// | 引数 $type_id:タイプID
// | 引数 $permission    :ブランク　または　ignore
// | 引数 $numberformat  :ブランク　または　yes
// +---------------------------------------------------------------------------+
// | 戻値 nomal:指定追加項目の指定データに一致する件数
// +---------------------------------------------------------------------------+

function DATABOX_count_each_afield(
    $pi_name="databox"
    ,$templatesetvar=""
    ,$value=""
    ,$type_id=0
    ,$permission=''
    ,$numberformat=''
)
{
    global $_TABLES;

    if ($templatesetvar=="" OR $value=="" ){
        return "";
    }

    if  ($type_id=="" OR  $type_id==NULL){
        $type_id=0;
    }
    
    //-----
    $tbl1=$_TABLES[strtoupper($pi_name).'_base'] ;
    $tbl2=$_TABLES[strtoupper($pi_name).'_addition'] ;
    $tbl3=$_TABLES[strtoupper($pi_name).'_def_field'];

    //-----
    $sql = "SELECT ".LB;

    $sql .= " t1.id".LB;

    $sql .= " FROM ".LB;

    $sql .= " {$tbl1} AS t1 ".LB;
    $sql .= " ,{$tbl2} AS t2 ".LB;
    $sql .= " ,{$tbl3} AS t3 ".LB;

    $sql .= " WHERE ".LB;

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
    $sql .= " AND fieldset_id = ".$type_id.LB;

    $sql .= " AND t1.id = t2.id ".LB;
    $sql .= " AND t2.field_id= t3.field_id" .LB;
    $sql .= " AND t3.templatesetvar='". $templatesetvar ."'".LB;

    $sql .= " AND t2.value='". $value ."'".LB;

    $result = DB_query ($sql);
    $cnt = DB_numRows ($result);
    if  (strtoupper($numberformat)=="YES"){
        $rt=COM_NumberFormat($cnt);
    }else{
        $rt=$cnt;
    }

    return $rt;

}
function DATABOX_math(
    $pi_name= 'databox'
    ,$method= 'count'
    ,$templatesetvar=""
    ,$type_id=""
    ,$category_id=null
    ,$category_code=null
    ,$mode=''
    ,$roundprecision=''
    ,$numberformat=''
    ,$permission=""
)
// +---------------------------------------------------------------------------+
// | 機能  指定アトリビュートの指定値（count average max min sum）を取得する
// |       複数カテゴリ条件選択が可能（OR と AND）
// +---------------------------------------------------------------------------+
// | 書式  DATABOX_math("databox","count","1")
// +---------------------------------------------------------------------------+
// | 引数 $pi_name:plugin name 'databox' 'userbox' 'formbox'
// | 引数 $method:count average avg max min sum
// | 引数 $templatesetvar:追加項目のテーマ変数
// | 引数 $value:指定データ値
// | 引数 $type_id:タイプID
// | 引数 $category_id:カテゴリid
// | 引数 $category_code :
// | 引数 $mode          :ブランク　または　AND
// | 引数 $roundprecision:〜-3,-2,-1,0,1,2,3
// | 引数 $numberformat  :yes
// +---------------------------------------------------------------------------+
// | 戻値 nomal:指定値
// +---------------------------------------------------------------------------+
{
    global $_TABLES;
    global $_CONF;

    if ($templatesetvar=="" ){
        return "";
    }
    if  ($type_id=="" OR  $type_id==NULL){
        $type_id=0;
    }
	
	$method=strtoupper($method);
    if  ($method=="MAX" OR $method=="MIN" OR $method=="SUM" OR $method=="AVG" OR $method=="COUNT" ){
    }else if  ($method=="AVERAGE" ){
	    $method="AVG";
	}else{
	    $method="COUNT";
	}
    //-----category check
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
                $w_id=DATABOX_codetoid($val,strtoupper($pi_name).'_def_category',"category_id");
                $idsary[]=$w_id;
                $ids.=$w_id;
            }
        }
    }else{
        $ids=$category_id;
        $idsary[]=$category_id;
    }
    //-----
    $tbl1=$_TABLES[strtoupper($pi_name).'_base'] ;
    $tbl2=$_TABLES[strtoupper($pi_name).'_addition'] ;
    $tbl3=$_TABLES[strtoupper($pi_name).'_def_field'];
    $tbl4=$_TABLES[strtoupper($pi_name).'_category'] ;
	$tbl5=$_TABLES[strtoupper($pi_name).'_def_category'] ;
	
    $type=DB_getItem( $tbl3   ,"type","templatesetvar='{$templatesetvar}'");
	
    //-----
    $sql = "SELECT ".LB;
    
    if  ($type==15  OR $type==21 OR strtoupper($numberformat)=="YES") {
        $sql .= $method ."(t2.value + 0) AS rt".LB;
    }else{
        $sql .= $method ."(t2.value) AS rt".LB;
    }
    $sql .= " FROM ".LB;

    $sql .= " {$tbl1} AS t1 ".LB;
    $sql .= " ,{$tbl2} AS t2 ".LB;
    $sql .= " ,{$tbl3} AS t3 ".LB;

    $sql .= " WHERE ".LB;

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
    
    $sql .= " AND fieldset_id=".$type_id;
    $sql .= " AND t1.id = t2.id ".LB;
    $sql .= " AND t2.field_id= t3.field_id" .LB;
    $sql .= " AND t3.templatesetvar='". $templatesetvar ."'".LB;
	
    if (count($idsary)<>0){
        if (strtoupper($mode)=="AND"){
            $w="";
            foreach( $idsary as $val ){
                if ($w<>""){
                    $w.=" AND ";
                }
                $w.=$val." IN (SELECT t4.category_id";
                $w.=" FROM {$tbl4} AS t4 ,{$tbl5} AS t5";
                $w.=" WHERE t1.id = t4.id AND t4.category_id = t5.category_id AND t5.allow_display<2)";
            }
        }else{
            $w= "t1.id IN (SELECT id FROM {$tbl4} AS t4 ,{$tbl5} AS t5 ";
            $w.="WHERE t4.category_id = t5.category_id AND t5.allow_display<2 AND t4.category_id IN ({$ids})) ";
        }
        $sql.=" AND ({$w})".LB;
    }
    $result = DB_query ($sql);
    $numrows = DB_numRows ($result);
	
    if ($numrows > 0) {
        $A = DB_fetchArray ($result);
		$A = array_map('stripslashes', $A);
		$rt=$A['rt'];;
	}else{
		if  ($method=="COUNT"){
			$rt=0;
		}else{
			$rt="";
		}
	}
	
    if ( is_numeric($rt) ) {
        if  (!is_numeric($roundprecision)){
            $roundprecision=0;
        }
        $rt=round($rt,$roundprecision);
        if  (strtoupper($numberformat)=="YES"){
            $rt=number_format( $rt, 
                $roundprecision, $_CONF['decimal_separator'], $_CONF['thousand_separator'] );
        }
    }
    return $rt;
	
	
}
function DATABOX_getcheckboxary(
    $ary
    ,$fld
    ,$selected = ""
    ,$pi_name=""
    ,$disabled=""
    ,$type=""
    ,$size=1
)
// +---------------------------------------------------------------------------+
// | 機能  チェックボックス作成（配列より）
// | 書式 DATABOX_getcheckboxary($selectionary,"afield[".$fid."]",$fvalue,$pi_name, $disabled,$type);
// +---------------------------------------------------------------------------+
// | 引数 $ary:
// | 引数 $fld:
// | 引数 $selected:選択値　省略時
// | 引数 $pi_name: 'databox' 'userbox' 'formbox'
// | 引数 $disabled:defalt="" 
// | 引数 $type:defalt="" 14=multiselect
// +---------------------------------------------------------------------------+
// | 戻値 nomal:オプションリスト                                               |
// +---------------------------------------------------------------------------+
{

    if ($pi_name==""){
        $lang_box="LANG_DATABOX";
    }else{
        $lang_box="LANG_".strtoupper($pi_name);
    }
    global $$lang_box;
    $lang_box=$$lang_box;
	
    $selectedary=explode(":",$selected);
	
	$retval = '';
	
    foreach($ary as $k => $v) {
        $retval .= '<span style="white-space:nowrap">';
        $retval .= '<input type="checkbox" name="' . $fld . '[]" value="' . $k ;
        $retval .= '"';
		if  ($selected==""){
		}else{
            if( in_array($k,$selectedary) ){//@@@@@
                $retval .= ' checked="checked"';
			}
		}
        $retval .= '>' . stripslashes( $v ) . '</span>' . LB;

    }
    return $retval;
}

?>