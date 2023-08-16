<?php
/*"******************************************************************************************************
*   (c) 2007-2014 by Kajona, www.kajona.de                                                              *
*       Published under the GNU LGPL v2.1, see /system/licence_lgpl.txt                                 *
*-------------------------------------------------------------------------------------------------------*
*	$Id$                                             *
********************************************************************************************************/

/**
 * Class to handle all link-generations, backend and portal.
 * Moved from functions.php to a central class in order to avoid duplicated code.
 * As a side-effect, the class may be overridden using the /project schema in order
 * to modify the link generation (if required).
 *
 * @package module_system
 * @author sidler@mulchprod.de
 * @since 4.3
 */
class class_link {

    private static $intNrOfPortalLanguages = null;
    private static $strPortalLanguage = null;


    /**
     * Generates a link using the content passed.
     * The param $strLinkContent should contain all contents of the a-tag.
     * The system renders <a $strLinkContent title... class...>($strText|$strImage)</a>
     *
     * @param string $strLinkContent
     * @param string $strText
     * @param string $strAlt
     * @param string $strImage
     * @param string $strImageId
     * @param string $strLinkId
     * @param bool $bitTooltip
     * @param string $strCss
     * @return string
     */
    public static function getLinkAdminManual($strLinkContent, $strText , $strAlt="", $strImage="", $strImageId = "", $strLinkId = "", $bitTooltip = true, $strCss = "") {
        $strLink = "";

        if($strImage != "") {
            if(!$bitTooltip)
                $strLink = "<a ".$strLinkContent."  title=\"".$strAlt."\" ".($strLinkId != "" ? "id=\"".$strLinkId."\"" : "")." >".class_adminskin_helper::getAdminImage($strImage, $strAlt, true, $strImageId)."</a>";
            else
                $strLink = "<a ".$strLinkContent."  title=\"".$strAlt."\" rel=\"tooltip\" ".($strLinkId != "" ? "id=\"".$strLinkId."\"" : "")." >".class_adminskin_helper::getAdminImage($strImage, $strAlt, true, $strImageId)."</a>";
        }
        else if($strText != "") {
            if($bitTooltip && (trim($strAlt) == "" || $strAlt == $strText)) {
                $bitTooltip = false;
                $strAlt = $strText;
            }

            $strLink = "<a ".$strLinkContent." title=\"".$strAlt."\" ".($strCss!= "" ? " class=\"".$strCss."\"" : "")." ".($bitTooltip!= "" ? " rel=\"tooltip\"" : "")." ".($strLinkId != "" ? "id=\"".$strLinkId."\"" : "")." >".$strText."</a>";
        }

        return $strLink;
    }

    /**
     * Generates a link for the admin-area
     *
     * @param string $strModule
     * @param string $strAction
     * @param string $strParams
     * @param string $strText
     * @param string $strAlt
     * @param string $strImage
     * @param bool $bitTooltip
     * @param string $strCss
     * @return string
     */
    public static function getLinkAdmin($strModule, $strAction, $strParams = "", $strText ="", $strAlt="", $strImage="", $bitTooltip = true, $strCss = "") {
        $strHref = "href=\"".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."\"";
        return self::getLinkAdminManual($strHref, $strText, $strAlt, $strImage, "", "", $bitTooltip, $strCss);
    }





    /**
     * Generates a link for the admin-area
     *
     * @param string $strModule
     * @param string $strAction
     * @param string $strParams
     * @param bool $bitEncodedAmpersand
     * @return string
     */
    public static function getLinkAdminHref($strModule, $strAction = "", $strParams = "", $bitEncodedAmpersand = true) {

        //systemid in params?
        $strSystemid = "";
        $arrParams = self::parseParamsString($strParams, $strSystemid);

        //urlencoding
        $strModule = urlencode($strModule);
        $strAction = urlencode($strAction);

        //rewriting enabled?
        if(_system_mod_rewrite_ == "true") {

            //scheme: /admin/module.action.systemid
            if($strModule != "" && $strAction == "" && $strSystemid == "")
                $strLink = _webpath_."/admin/".$strModule.".html";
            else if($strModule != "" && $strAction != "" && $strSystemid == "")
                $strLink = _webpath_."/admin/".$strModule."/".$strAction.".html";
            else
                $strLink = _webpath_."/admin/".$strModule."/".$strAction."/".$strSystemid.".html";

            if(count($arrParams) > 0)
                $strLink .= "?".implode("&amp;", $arrParams);

        }
        else {
            $strLink = ""._indexpath_."?admin=1&amp;module=".$strModule.
                ($strAction != "" ? "&amp;action=".$strAction : "").
                ($strSystemid != "" ? "&amp;systemid=".$strSystemid : "");

            if(count($arrParams) > 0)
                $strLink .= "&amp;".(implode("&amp;", $arrParams));
        }

        if(!$bitEncodedAmpersand)
            $strLink = uniStrReplace("&amp;", "&", $strLink);

        return $strLink;
    }

    /**
     * Generates an admin-url to trigger xml-requests. Takes care of url-rewriting
     *
     * @param string $strModule
     * @param string $strAction
     * @param string $strParams
     * @param bool $bitEncodedAmpersand
     *
     * @return mixed|string
     */
    public static function getLinkAdminXml($strModule, $strAction = "", $strParams = "", $bitEncodedAmpersand = false) {

        //systemid in params?
        $strSystemid = "";
        $arrParams = self::parseParamsString($strParams, $strSystemid);

        //urlencoding
        $strModule = urlencode($strModule);
        $strAction = urlencode($strAction);

        //rewriting enabled?
        if(_system_mod_rewrite_ == "true") {

            //scheme: /admin/module.action.systemid
            if($strModule != "" && $strAction == "" && $strSystemid == "")
                $strLink = _webpath_."/xml/admin/".$strModule;
            else if($strModule != "" && $strAction != "" && $strSystemid == "")
                $strLink = _webpath_."/xml/admin/".$strModule."/".$strAction;
            else
                $strLink = _webpath_."/xml/admin/".$strModule."/".$strAction."/".$strSystemid;

            if(count($arrParams) > 0)
                $strLink .= "?".implode("&amp;", $arrParams);

        }
        else {
            $strLink = ""._webpath_."/xml.php?admin=1&amp;module=".$strModule.
                ($strAction != "" ? "&amp;action=".$strAction : "" ).
                ($strSystemid != "" ?  "&amp;systemid=".$strSystemid : "");

            if(count($arrParams) > 0)
                $strLink .= "&amp;".(implode("&amp;", $arrParams));
        }

        if(!$bitEncodedAmpersand)
            $strLink = uniStrReplace("&amp;", "&", $strLink);

        return $strLink;
    }

    /**
     * Generates a link opening in a popup in admin-area
     *
     * @param string $strModule
     * @param string $strAction
     * @param string $strParams
     * @param string $strText
     * @param string $strAlt
     * @param string $strImage
     * @param int|string $intWidth
     * @param int|string $intHeight
     * @param string $strTitle
     * @param bool $bitTooltip
     * @param bool $bitPortalEditor
     *
     * @return string
     */
    public static function getLinkAdminPopup($strModule, $strAction, $strParams = "", $strText = "", $strAlt = "", $strImage = "", $intWidth = "500", $intHeight = "500", $strTitle = "", $bitTooltip = true, $bitPortalEditor = false) {
        $strLink = "";
        //if($strParams != "")
        //    $strParams = str_replace("&", "&amp;", $strParams);
        $strTitle = addslashes(uniStrReplace(array("\n", "\r"), array(), strip_tags(nl2br($strTitle))));

        if($bitPortalEditor && $intHeight == "500")
            $intHeight = 690;

        //urlencoding
        $strModule = urlencode($strModule);
        $strAction = urlencode($strAction);

        if($bitPortalEditor)
            $strParams .= "&pe=1";

        if($strImage != "") {
            if($strAlt == "")
                $strAlt = $strAction;

            if(!$bitTooltip)
                $strLink = "<a href=\"#\" onclick=\"window.open('".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."','".$strTitle."','scrollbars=yes,resizable=yes,width=".$intWidth.",height=".$intHeight."'); return false;\" ".
                    "title=\"".$strAlt."\">".class_adminskin_helper::getAdminImage($strImage, $strAlt, true)."</a>";
            else
                $strLink = "<a href=\"#\" onclick=\"window.open('".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."','".$strTitle."','scrollbars=yes,resizable=yes,width=".$intWidth.",height=".$intHeight."'); return false;\" ".
                    "title=\"".$strAlt."\" rel=\"tooltip\">".class_adminskin_helper::getAdminImage($strImage, $strAlt, true)."</a>";
        }

        if($strImage == "" && $strText != "") {
            $bitTooltip = $bitTooltip && $strAlt != "";

            $strLink = "<a href=\"#\" ".($bitPortalEditor ? "class=\"pe_link\"" : "")." ".($bitTooltip ? "title=\"".$strAlt."\" rel=\"tooltip\" " : "")." ".
                "onclick=\"window.open('".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."','".$strTitle."','scrollbars=yes,resizable=yes,width=".$intWidth.",height=".$intHeight."'); return false;\">".$strText."</a>";
        }
        return $strLink;
    }

    /**
     * Generates a link opening in a dialog in admin-area
     *
     * @param string $strModule
     * @param string $strAction
     * @param string $strParams
     * @param string $strText
     * @param string $strAlt
     * @param string $strImage
     * @param string $strTitle
     * @param bool $bitTooltip
     * @param bool $bitPortalEditor
     * @param bool|string $strOnClick
     * @param null|int $intWidth
     * @param null|int $intHeight
     * @return string
     */
    public static function getLinkAdminDialog($strModule, $strAction, $strParams = "", $strText = "", $strAlt="", $strImage="", $strTitle = "", $bitTooltip = true, $bitPortalEditor = false, $strOnClick = "", $intWidth = null, $intHeight = null) {
        $strLink = "";
        $strTitle = addslashes(uniStrReplace(array("\n", "\r"), array(), strip_tags(nl2br($strTitle))));

        if($bitPortalEditor)
            $strParams .= "&pe=1";

        //urlencoding
        $strModule = urlencode($strModule);
        $strAction = urlencode($strAction);

        if($strOnClick == "") {
            if($intWidth !== null && $intHeight !== null)
                $strOnClick = "KAJONA.admin.folderview.dialog.setContentIFrame('".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."'); KAJONA.admin.folderview.dialog.setTitle('".$strTitle."'); ".
                    "KAJONA.admin.folderview.dialog.init('".$intWidth."', '".$intHeight."'); return false;";
            else
                $strOnClick = "KAJONA.admin.folderview.dialog.setContentIFrame('".class_link::getLinkAdminHref($strModule, $strAction, $strParams)."'); KAJONA.admin.folderview.dialog.setTitle('".$strTitle."'); ".
                    "KAJONA.admin.folderview.dialog.init(); return false;";
        }



        if($strImage != "") {
            if($strAlt == "")
                $strAlt = $strAction;

            if(!$bitTooltip)
                $strLink = "<a href=\"#\" onclick=\"".$strOnClick."\" title=\"".$strAlt."\">".class_adminskin_helper::getAdminImage($strImage, $strAlt, true)."</a>";
            else
                $strLink = "<a href=\"#\" onclick=\"".$strOnClick."\" title=\"".$strAlt."\" rel=\"tooltip\">".class_adminskin_helper::getAdminImage($strImage, $strAlt, true)."</a>";
        }

        if($strImage == "" && $strText != "") {
            if($strAlt == "")
                $strAlt = $strText;
            $strLink = "<a href=\"#\" ".($bitPortalEditor ? "class=\"pe_link\"" : "")." ".($bitTooltip ? "title=\"".$strAlt."\" rel=\"tooltip\" " : "" )." onclick=\"".$strOnClick."\">".$strText."</a>";
        }
        return $strLink;
    }




    /**
     * Creates a Link for the portal
     *
     * @param string $strPageI
     * @param string $strPageE
     * @param string $strTarget
     * @param string $strText
     * @param string $strAction
     * @param string $strParams
     * @param string $strSystemid
     * @param string $strCssClass
     * @param string $strLanguage
     * @param string $strSeoAddon
     * @return string
     */
    public static function getLinkPortal($strPageI, $strPageE, $strTarget = "_self", $strText = "", $strAction = "", $strParams = "", $strSystemid = "", $strCssClass = "", $strLanguage = "", $strSeoAddon = "") {
        $strReturn = "";

        $strHref = class_link::getLinkPortalHref($strPageI, $strPageE, $strAction, $strParams, $strSystemid, $strLanguage, $strSeoAddon);

        if($strTarget == "")
            $strTarget = "_self";

        $strReturn .="<a href=\"".$strHref."\" target=\"".$strTarget."\" ".($strCssClass != "" ? " class=\"".$strCssClass."\" ": "").">".$strText."</a>";

        return $strReturn;
    }

    /**
     * Creates a raw Link for the portal (just the href)
     *
     * @param string $strPageI
     * @param string $strPageE
     * @param string $strAction
     * @param string $strParams
     * @param string $strSystemid
     * @param string $strLanguage
     * @param string $strSeoAddon Only used if using mod_rewrite
     * @return string
     */
    public static function getLinkPortalHref($strPageI, $strPageE = "", $strAction = "", $strParams = "", $strSystemid = "", $strLanguage = "", $strSeoAddon = "") {
        $strReturn = "";
        $bitInternal = true;

        //return "#" if neither an internal nor an external page is set
        if($strPageI == "" && $strPageE == "")
            return "#";

        //Internal links are more important than external links!
        if($strPageI == "" && $strPageE != "")
            $bitInternal = false;


        //create an array out of the params
        $strParsedSystemid = "";
        $arrParams = self::parseParamsString($strParams, $strParsedSystemid);
        if($strSystemid == "" && validateSystemid($strParsedSystemid))
            $strSystemid = $strParsedSystemid;

        // any anchors set to the page?
        $strAnchor = "";
        if(uniStrpos($strPageI, "#") !== false) {
            //get anchor, remove anchor from link
            $strAnchor = urlencode(uniSubstr($strPageI, uniStrpos($strPageI, "#")+1));
            $strPageI = uniSubstr($strPageI, 0, uniStrpos($strPageI, "#"));
        }

        //urlencoding
        $strPageI = urlencode($strPageI);
        $strAction = urlencode($strAction);

        //more than one language installed?
        if($strLanguage == "" && self::getIntNumberOfPortalLanguages() > 1)
            $strLanguage = self::getStrPortalLanguage();
        else if($strLanguage != "" && self::getIntNumberOfPortalLanguages() <=1)
            $strLanguage = "";

        $strHref = "";
        if($bitInternal) {
            //check, if we could use mod_rewrite
            $bitRegularLink = true;
            if(_system_mod_rewrite_ == "true") {

                $strAddKeys = "";

                //used later to add seo-relevant keywords
                $objPage = class_module_pages_page::getPageByName($strPageI);
                if($objPage !== null) {
                    if($strLanguage != "") {
                        $objPage->setStrLanguage($strLanguage);
                        $objPage->initObject();
                    }

                    $strAddKeys = $objPage->getStrSeostring().($strSeoAddon != "" && $objPage->getStrSeostring() != "" ? "-" : "").urlSafeString($strSeoAddon);
                    if(uniStrlen($strAddKeys) > 0 && uniStrlen($strAddKeys) <=2 )
                        $strAddKeys .= "__";

                    //trim string
                    $strAddKeys = uniStrTrim($strAddKeys, 100, "");

                    if($strLanguage != "")
                        $strHref .= $strLanguage."/";

                    $strPath = $objPage->getStrPath();
                    if($strPath == "") {
                        $objPage->updatePath();
                        $strPath = $objPage->getStrPath();
                        $objPage->updateObjectToDb();
                    }
                    if($strPath != "") {
                        $strHref .= $strPath."/";
                    }

                }

                //ok, here we go. schema for rewrite_links: pagename.addKeywords.action.systemid.language.html
                //but: special case: just pagename & language
                if($strAction == "" && $strSystemid == "" && $strAddKeys == "")
                    $strHref .= $strPageI.".html";
                elseif($strAction == "" && $strSystemid == "")
                    $strHref .= $strPageI.($strAddKeys == "" ? "" : ".".$strAddKeys).".html";
                elseif($strAction != "" && $strSystemid == "")
                    $strHref .= $strPageI.".".$strAddKeys.".".$strAction .".html";
                else
                    $strHref .= $strPageI.".".$strAddKeys.".".$strAction .".".$strSystemid.".html";

                //params?
                if(count($arrParams) > 0)
                    $strHref .= "?".implode("&amp;", $arrParams);

                // add anchor if given
                if($strAnchor != "")
                    $strHref .= "#".$strAnchor;

                //plus the domain as a prefix
                $strHref = "_webpath_"."/".$strHref;


                $bitRegularLink = false;

            }

            if($bitRegularLink)
                $strHref = "_indexpath_"."?".
                    ($strPageI != "" ? "page=".$strPageI : "" )."".
                    ($strSystemid != "" ? "&amp;systemid=".$strSystemid : "" ).
                    ($strAction != "" ? "&amp;action=".$strAction : "").
                    ($strLanguage != "" ? "&amp;language=".$strLanguage : "").
                    (count($arrParams) > 0 ? "&amp;".implode("&amp;", $arrParams) : "" ).
                    ($strAnchor != "" ? "#".$strAnchor : "")."";
        }
        else {
            $strHref = $strPageE;
        }

        $strReturn .= $strHref;

        return $strReturn;
    }

    /**
     * Generates a link opening in a popup in portal-area
     *
     * @param string $strPageI
     * @param string $strPageE
     * @param string $strAction
     * @param string $strParams
     * @param string $strSystemid
     * @param string $strTitle
     * @param int|string $intWidth
     * @param int|string $intHeight
     *
     * @return string
     */
    public static function getLinkPortalPopup($strPageI, $strPageE, $strAction = "", $strParams = "", $strSystemid = "", $strTitle = "", $intWidth = "500", $intHeight = "500") {
        $strLink = class_link::getLinkPortalHref($strPageI, $strPageE, $strAction, $strParams, $strSystemid);
        $strLink = "<a href=\"$strLink\" onclick=\"return !window.open('".$strLink."','".$strTitle."','scrollbars=yes,resizable=yes,width=".$intWidth.",height=".$intHeight."')\" title=\"".$strTitle."\">".$strTitle."</a>";
        return $strLink;
    }



    /**
     * Internal helper to transform the passed params string into an array.
     * Extracts the systemid out of the string and updates the passed reference with the
     * systemid.
     *
     * @param string $strParams
     * @param string &$strSystemid
     * @return array
     */
    private static function parseParamsString($strParams, &$strSystemid = "") {
        $strParams = uniStrReplace("&amp;", "&", $strParams);

        //if given, remove first ampersand from params
        if(substr($strParams, 0, 1) == "&")
            $strParams = substr($strParams, 1);

        $arrParams = explode("&", $strParams);
        foreach($arrParams as $strKey => &$strValue) {
            $arrEntry = explode("=", $strValue);

            if(count($arrEntry) == 2 && $arrEntry[0] == "systemid") {
                $strSystemid = $arrEntry[1];
                unset($arrParams[$strKey]);
            }
            else if($strValue == "")
                unset($arrParams[$strKey]);

            if(count($arrEntry) == 2)
                $arrEntry[1] = urlencode($arrEntry[1]);

            $strValue = implode("=", $arrEntry);
        }

        return $arrParams;
    }

    /**
     * Helper to determin the number of portal languages only once.
     * @return int
     */
    private static function getIntNumberOfPortalLanguages() {
        if(self::$intNrOfPortalLanguages == null)
            self::$intNrOfPortalLanguages = class_module_languages_language::getNumberOfLanguagesAvailable(true);

        return self::$intNrOfPortalLanguages;
    }


    /**
     * Helper to fetch the portal language
     * @return null|string
     */
    private static function getStrPortalLanguage() {
        if(self::$strPortalLanguage == null) {
            $objLang = new class_module_languages_language();
            self::$strPortalLanguage = $objLang->getStrPortalLanguage();
        }

        return self::$strPortalLanguage;
    }


}