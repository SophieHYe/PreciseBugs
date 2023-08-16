<?php

/*
 *   How to use plugin::plInfo()
 *   ===================
 *   
 *   The function returns a descriptive array of the plugin,
 *    which will then be used by GOsa to populate the plugin, its ACLs, 
 *    its properties, its schema requirements aso.
 *
 *
 *   The following values can be set:
 *   ================================
 *
 *   plShortName    |-> The name of the plugin in short (e.g. Posix)
 *                  |   This short-name will be shown for example in the ACL definitions.
 *                  | 
 *                  | 
 *   plDescription  |-> A descriptive text for the plugin (e.g. User posix account extension)
 *                  |   This will be shown in the ACL definitions.   
 *                  | 
 *                  | 
 *   plSelfModify   |-> If set to true this plugin allows to set 'self' ACLs.
 *                  |   For exampe to allow to change the users own password, but not the others.
 *                  | 
 *                  | 
 *   plDepends      |-> The plugins dependencies to other classes (e.g. sambaAccount requires posixAccount)
 *                  |  
 *                  | 
 *   plPriority     |-> The priority of the plugin, this influences the ACL listings only.
 *                  | 
 *                  | 
 *   plSection      |-> The section of this plugin 'administration', 'personal', 'addons'
 *                  | 
 *                  | 
 *   plCategory     |-> The plugin category this plugins belongs to (e.g. users, groups, departments) 
 *                  | 
 *                  | 
 *   plRequirements |-> Plugin requirements. 
 *                  | |
 *                  | |-> [activePlugin]           The schame checks will only be performed if the given plugin is enabled
 *                  | |                             in the gosa.conf definitions.
 *                  | |                            Defaults to the current class name if empty.
 *                  | |
 *                  | |-> [ldapSchema]             An array of objectClass requirements.
 *                  | |                            Syntax [[objectClass => 'version'], ... ]
 *                  | |                            Version can be emtpy which just checks for the existence of the class.
 *                  | |
 *                  | |-> [onFailureDisablePlugin] A list of plugins that which will be disabled if the 
 *                  |                               requirements couldn't be fillfulled.
 *                  |
 *                  |      ---------------------------------------------
 *                  |      EXAMPLE:
 *                  |      ---------------------------------------------
 *                  |      "plRequirements"=> array(
 *                  |         'activePlugin' => 'applicationManagement', 
 *                  |         'ldapSchema' => array(
 *                  |             'gosaObject' => '',
 *                  |             'gosaAccount' => '>=2.7',
 *                  |             'gosaLockEntry' => '>=2.7',
 *                  |             'gosaDepartment' => '>=2.7',
 *                  |             'gosaCacheEntry' => '>=2.7',
 *                  |             'gosaProperties' => '>=2.7',
 *                  |             'gosaConfig' => '>=2.7'
 *                  |             ),
 *                  |         'onFailureDisablePlugin' => array(get_class(), 'someClassName')
 *                  |         ),
 *                  |      ---------------------------------------------
 *                  |
 *                  |
 *                  |         
 *   plProvidedAcls |-> The ACLs provided by this plugin
 *                  |
 *                  |      ---------------------------------------------
 *                  |      EXAMPLE:
 *                  |      ---------------------------------------------
 *                  |      "plProvidedAcls"=> array(
 *                  |          'cn'             => _('Name'),
 *                  |          'uid'            => _('Uid'),
 *                  |          'phoneNumber'    => _('Phone number')
 *                  |          ),
 *                  |      ---------------------------------------------
 *                  |
 *                  |
 *                  | 
 *   plProperties   |-> Properties used by the plugin.
 *                  |   Properties which are defined here will be modifyable using the property editor.
 *                  |   To read properties you can use $config->get_cfg_value(className, propertyName)
 *                  | 
 *                  |      ---------------------------------------------
 *                  |      EXAMPLE:
 *                  |      ---------------------------------------------
 *                  |      "plProperties"=> array(
 *                  |         array(
 *                  |             "name"          => "htaccessAuthentication",
 *                  |             "type"          => "bool",
 *                  |             "default"       => "false",
 *                  |             "description"   => _("A description..."),
 *                  |             "check"         => "gosaProperty::isBool",
 *                  |             "migrate"       => "",
 *                  |             "group"         => "authentification",
 *                  |             "mandatory"     => TRUE
 *                  |             ),
 *                  |         ),
 *                  |   See class_core.inc for a huge amount of examples.
 */


class all extends plugin {
    static function plInfo()
    {
        return (array(
                    "plShortName"   => _("All"),
                    "plDescription" => _("All objects"),
                    "plSelfModify"  => TRUE,
                    "plDepends"     => array(),
                    "plPriority"    => 0,
                    "plSection"     => array("administration"),
                    "plCategory"    => array("all" => array("description" => '*&nbsp;'._("All"))),
                    "plProvidedAcls"    => array())
               );
    }
}

class core extends plugin {

    static function getPropertyValues($class,$name,$value,$type)
    {
        $list = array();
        switch($name){
            case 'idAllocationMethod':
                $list = array('traditional' => _('Traditional'), 'pool' => _('Use samba pool'));
                break;
            case 'passwordDefaultHash':
                $tmp = passwordMethod::get_available_methods();
                foreach($tmp['name'] as $id => $method){
                    $desc = $tmp[$id]['name'];
                    $list[$method] = $desc;
                }
                break;
            case 'theme':
                $cmd = "cd ../ihtml/themes; find . -name 'img.styles' | sed s/'^[^\/]*\/\([^\/]*\).*'/'\\1'/g";
                $res = `$cmd`  ; 
                $list = array();
                foreach(preg_split("/\n/",$res) as $entry){
                    if(!empty($entry)){
                        $list[$entry] = $entry;
                    }
                }
                break;
            case 'accountPrimaryAttribute':
                $list = array('uid' => 'uid', 'cn' => 'cn');
                break;
            case 'loginAttribute':
                $list = array(
                            'uid' => 'uid',
                            'mail' => 'mail',
                            'both' => 'uid & mail');
                break;
            case 'timezone': 
                $tmp = timezone::_get_tz_zones();
                foreach($tmp['TIMEZONES'] as $tzname => $offset){
                    if($offset >= 0){
                        $list[$tzname] = $tzname." ( + ".sprintf("%0.2f",$offset/(60*60))." "._("hours").")";
                    }else{
                        $offset = $offset * -1;
                        $list[$tzname] = $tzname." ( - ".sprintf("%0.2f",($offset/(60*60)))." "._("hours").")";
                    }
                }
                break;
            case 'mailAttribute':
                $list = array('mail' => 'mail','uid' => 'uid');
                break;
            case 'mailMethod': 
                $tmp = array();
                if(class_available('mailMethod')){
                    $tmp = mailMethod::get_methods();
                }
                $list =array();
                foreach($tmp as $vName => $vValue){
                    $vName = preg_replace('/^mailMethod/','', $vName);
                    $list[$vName] = $vValue;
                }
                $list[''] = _("None");
                break;
            case 'language':
                $tmp = get_languages(TRUE);
                $list[""] = _("Automatic");
                foreach($tmp as $key => $desc){
                    $list[$key] = $desc;
                }
                break;
            case 'modificationDetectionAttribute': 
                $list = array('entryCSN' => 'entryCSN (OpenLdap)','textCSN'=>'textCSN (Sun DS)');
                break;
            default: echo $name." ";$list = array();
        }

        if(!isset($list[$value])){
            $list[$value] = $value." ("._("User value").")";
        }

        return($list);
    }

    static function plInfo()
    {
        return (array(
                    "plShortName" => _("Core"),
                    "plDescription" => _("GOsa core plugin"),
                    "plSelfModify"  => FALSE,
                    "plDepends"     => array(),
                    "plPriority"    => 0,
                    "plSection"     => array("administration"),

                    "plRequirements"=> array(
                        'ldapSchema' => array(
                            'gosaObject' => '>=2.7',
                            'gosaAccount' => '>=2.7',
                            'gosaLockEntry' => '>=2.7',
                            'gosaDepartment' => '>=2.7',
                            'gosaCacheEntry' => '>=2.7',
                            'gosaProperties' => '>=2.7',
                            'gosaConfig' => '>=2.7'
                            ),
                        'onFailureDisablePlugin' => array(get_class())
                        ),



                    "plCategory"    => array("all"),
                    "plProperties"  => array(

                        array(
                            "name"          => "htaccessAuthentication",
                            "type"          => "bool",
                            "default"       => "false",
                            "description"   => _("Enables htaccess instead of LDAP authentication. This can be used to enable other authentication mechanisms like Kerberos for the GOsa login."),
                            "check"         => "gosaProperty::isBool",
                            "migrate"       => "",
                            "group"         => "authentification",
                            "mandatory"     => TRUE),

                        array(
                            "name"          => "statsDatabaseEnabled",
                            "type"          => "bool",
                            "default"       => "false",
                            "description"   => _("Enables the usage statistics module."),
                            "check"         => "gosaProperty::isBool",
                            "migrate"       => "",
                            "group"         => "core",
                            "mandatory"     => TRUE),

                        array(
                                "name"          => "statsDatabaseDirectory",
                                "type"          => "path",
                                "default"       => "/var/spool/gosa/stats",
                                "description"   => _("Database file to be used by the usage statistics module."),
                                "check"         => "gosaProperty::isWriteablePath",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => TRUE),

                        array(
                            "name"          => "logging",
                            "type"          => "bool",
                            "default"       => "true",
                            "description"   => _("Enables event logging in GOsa. Setting it to 'On' make GOsa log every action a user performs via syslog. If you use this in combination with rsyslog and configure it to MySQL logging, you can browse all events in GOsa."),

                            "check"         => "gosaProperty::isBool",
                            "migrate"       => "",
                            "group"         => "core",
                            "mandatory"     => TRUE),

                        array(
                                "name"          => "listSummary",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enables a status bar on the bottom of lists displaying a summary of type and number of elements in the list."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "visual",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "passwordMinLength",
                                "type"          => "integer",
                                "default"       => "",
                                "description"   => _("Specify the minimum length for newly entered passwords."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "passwordMinDiffer",
                                "type"          => "integer",
                                "default"       => "",
                                "description"   => _("Specify the minimum number of characters that have to differ between old and newly entered passwords."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "passwordProposalHook",
                                "type"          => "command",
                                "default"       => "",
                                "description"   => _("Command to generate password proposals. If a command has been specified, the user can decide whether to use an automatic password or a manually specified one.")." "._("Example").": /usr/bin/apg -n1",
                                "check"         => "gosaProperty::isCommand",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "displayErrors",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable display of PHP errors on the top of the page. Disable this feature in production environments to avoid the exposure of sensitive data.")." ".sprintf(_("Related option").": developmentMode"),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "developmentMode",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Show messages that may assist plugin development. Be aware that this option may produce some ACL related false error messages!"),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => TRUE),


                        array(
                                "name"          => "schemaCheck",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable LDAP schema verification during login. The recommended setting is 'On' because it enables efficient methods to create missing subtrees in the LDAP."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "copyPaste",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable copy and paste for most objects managed by GOsa."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "copyPaste",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "forceGlobals",
                                "type"          => "noLdap",
                                "default"       => "false",
                                "description"   => _("Enable PHP security checks for disabled register_global settings."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "forceSSL",
                                "type"          => "noLdap",
                                "default"       => "false",
                                "description"   => _("Enable automatic redirection to HTTPS based administration."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "ldapStats",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable logging of detailed information of LDAP operations."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "ldapFollowReferrals",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable LDAP referral chasing."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "ldap",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "ldapFilterNestingLimit",
                                "type"          => "integer",
                                "default"       => 200,
                                "description"   => _("Specify LDAP element filter limit. If the limit is not 0, GOsa speeds up group queries by putting several queries into a single query. This is known to produce problems on some LDAP servers (i.e. Sun DS) and needs to be lowered or disabled."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "ldap",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "ldapSizelimit",
                                "type"          => "integer",
                                "default"       => 200,
                                "description"   => _("Specify the maximum number of entries GOsa will request from an LDAP server. A warning is displayed if this limit is exceeded."), 
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "ldapSizeIgnore",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Disable checks for LDAP size limits."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "warnSSL",
                                "type"          => "noLdap",
                                "default"       => "true",
                                "description"   => _("Enable warnings for non encrypted connections."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "ppdGzip",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable compression for PPD files."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "ppd",
                                "mandatory"     => FALSE),


                        array(
                                "name"          => "ignoreAcl",
                                "type"          => "dn",
                                "default"       => "",
                                "description"   => _("DN of user with ACL checks disabled. This should only be used to restore lost administrative ACLs."),
                                "check"         => "gosaProperty::isDN",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => FALSE),


                        array(
                                "name"          => "ppdPath",
                                "type"          => "path",
                                "default"       => "/var/spool/ppd",
                                "description"   => _("Storage path for PPD files."),
                                "check"         => "gosaProperty::isPath",
                                "migrate"       => "",
                                "group"         => "ppd",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "ldapMaxQueryTime",
                                "type"          => "integer",
                                "default"       => "",
                                "description"   => _("Number of seconds a LDAP query is allowed to take until GOsa aborts the request."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "storeFilterSettings",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enables storing of user filters in browser cookies."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "sendCompressedOutput",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enables sending of compressed web page content."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "allowUidProposalModification",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Allows to modify uid-proposals when creating a new user from a user-template."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "modificationDetectionAttribute",
                                "type"          => "switch",
                                "default"       => "entryCSN",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("LDAP attribute which is used to detect changes."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "language",
                                "type"          => "switch",
                                "default"       => "",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("ISO language code which is used to override the automatic language detection."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "theme",
                                "type"          => "switch",
                                "default"       => "default",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("CSS and template theme to be used."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "visual",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "sessionLifetime",
                                "type"          => "integer",
                                "default"       => 600,
                                "description"   => _("Number of seconds after an inactive session expires. This may be overridden by some systems php.ini/crontab mechanism."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "templateCompileDirectory",
                                "type"          => "path",
                                "default"       => "/var/spool/gosa",
                                "description"   => _("Template engine compile directory."),
                                "check"         => "gosaProperty::isWriteablePath",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "debugLevel",
                                "type"          => "integer",
                                "default"       => 0,
                                "description"   => sprintf(_("Logical AND of the integer values below that controls the debug output on every page load: %s"),
"

DEBUG_TRACE   = 1
DEBUG_LDAP    = 2
DEBUG_MYSQL   = 4
DEBUG_SHELL   = 8
DEBUG_POST    = 16
DEBUG_SESSION = 32
DEBUG_CONFIG  = 64
DEBUG_ACL     = 128
DEBUG_SI      = 256"),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "debug",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "sambaHashHook",
                                "type"          => "command",
                                "default"       => "perl -MCrypt::SmbHash -e \"use MIME::Base64; print join(q[:], ntlmgen decode_base64('%password')), $/;\"",
                                "description"   => _("Command to create Samba NT/LM hashes. Required for password synchronization if you don't use supplementary services."),
                                "check"         => "gosaProperty::isCommand",
                                "migrate"       => "",
                                "group"         => "samba",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "passwordDefaultHash",
                                "type"          => "switch",
                                "default"       => "ssha",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Default hash to be used for newly created user passwords."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),
                        array(
                                "name"          => "strictPasswordRules",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable checking for the presence of problematic unicode characters in passwords."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "accountPrimaryAttribute",
                                "type"          => "switch",
                                "default"       => "cn",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Specify whether 'cn' or 'uid' style user DNs are generated. For more sophisticated control use the 'accountRDN' setting."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "userRDN",
                                "type"          => "rdn",
                                "default"       => "ou=people,",
                                "description"   => _("Location component for user storage inside of departments."),
                                "check"         => "gosaProperty::isRdn",
                                "migrate"       => "migrate_userRDN", 
                                "group"         => "user",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "groupRDN",
                                "type"          => "rdn",
                                "default"       => "ou=groups,",
                                "description"   => _("Location component for group storage inside of departments."),
                                "check"         => "gosaProperty::isRdn",
                                "migrate"       => "migrate_groupRDN",
                                "group"         => "group",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gidNumberBase",
                                "type"          => "integer",
                                "default"       => "1000",
                                "description"   => _("Count base for group IDs. For dynamic ID assignment use the 'nextIdHook' setting."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => TRUE),

                       array(
                                "name"          => "baseIdHook",
                                "type"          => "command",
                                "default"       => "",
                                "description"   => _("Count base for user IDs. For dynamic ID assignment use the 'nextIdHook' setting."),
                                "check"         => "gosaProperty::isCommand",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gidNumberPoolMin",
                                "type"          => "integer",
                                "default"       => 10000,
                                "description"   => _("Lowest assignable group ID for use with the idAllocationMethod set to 'pool'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gidNumberPoolMax",
                                "type"          => "integer",
                                "default"       => 40000,
                                "description"   => _("Highest assignable group ID for use with the idAllocationMethod set to 'pool'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "uidNumberPoolMin",
                                "type"          => "integer",
                                "default"       => 10000,
                                "description"   => _("Lowest assignable user ID for use with the idAllocationMethod set to 'pool'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "uidNumberPoolMax",
                                "type"          => "integer",
                                "default"       => 40000,
                                "description"   => _("Highest assignable user ID for use with the idAllocationMethod set to 'pool'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "uidNumberBase",
                                "type"          => "integer",
                                "default"       => "1000",
                                "description"   => _("Count base for user IDs. For dynamic ID assignment use the 'baseIdHook' setting."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaRpcServer",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("Connection URL for use with the gosa-ng service."),
                                "check"         => "jsonRPC::testConnectionProperties",
                                "migrate"       => "",
                                "group"         => "rpc",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaRpcUser",
                                "type"          => "string",
                                "default"       => "admin",
                                "description"   => _("User name used to connect to the 'gosaRpcServer'."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "rpc",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaRpcPassword",
                                "type"          => "string",
                                "default"       => "tester",
                                "description"   => _("Password used to connect to the 'gosaRpcServer'."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "rpc",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaSupportURI",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("Connection URI for use with the gosa-si service (obsolete)."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "gosa-si",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaSupportTimeout",
                                "type"          => "integer",
                                "default"       => 15,
                                "description"   => _("Number of seconds after a gosa-si connection is considered 'dead'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "gosa-si",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "loginAttribute",
                                "type"          => "switch",
                                "default"       => "uid",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("User attribute which is used for log in."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "timezone",
                                "type"          => "switch",
                                "default"       => "",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Local time zone."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "honourUnitTags",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable tagging of administrative units. This can be used in conjunction with ACLs (obsolete)."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "useSaslForKerberos",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable the use of {sasl} instead of {kerberos} for user realms."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "SASLRealm",
                                "type"          => "string",
                                "default"       => "REALM",
                                "description"   => _("The SASL realm to use for password storage."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "password",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "rfc2307bis",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable RFC 2307bis style groups. This combines the use of 'member' and 'memberUid' attributes."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "personalTitleInDN",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Adjusts the user DN generation to include the users personal title (only in conjunction with accountPrimaryAttribute)."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "storage location",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "nextIdHook",
                                "type"          => "command",
                                "default"       => "",
                                "description"   => _("Script to be called for finding the next free id for groups or users."),
                                "check"         => "gosaProperty::isCommand",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),
                        array(
                                "name"          => "idGenerator",
                                "type"          => "string",
                                "default"       => "{%sn}-{%givenName[2-4]}",
                                "description"   => _("Descriptive string for the automatic ID generator. Please read the FAQ file for more information."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "strictNamingRules",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable strict checking for user IDs and group names."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "minId",
                                "type"          => "integer",
                                "default"       => 40,
                                "description"   => _("Lowest assignable user or group ID. Only active if idAllocationMethod is set to 'traditional'."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "mailAttribute",
                                "type"          => "switch",
                                "default"       => "mail",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Attribute to be used for primary mail addresses."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "gosaSharedPrefix",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("Namespace used for shared folders."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "mailUserCreation",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("Namespace rule to create user folders. Please read the FAQ file for more information."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "mailFolderCreation",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("Namespace rule to create folders. Please read the FAQ file for more information."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "imapTimeout",
                                "type"          => "integer",
                                "default"       => 10,
                                "description"   => _("Seconds after an IMAP connection is considered dead."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "mailMethod",
                                "type"          => "switch",
                                "default"       => "",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Class name of the mail method to be used."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "cyrusUseSlashes",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable slashes instead of dots as a name space separator for Cyrus IMAP."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "vacationTemplateDirectory",
                                "type"          => "path",
                                "default"       => "/etc/gosa/vacation",
                                "description"   => _("Directory to store vacation templates. Please read the FAQ file for more information."),
                                "check"         => "gosaProperty::isWriteablePath",
                                "migrate"       => "",
                                "group"         => "mail",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "ldapTLS",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable TLS for LDAP connections."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "security",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "honourIvbbAttributes",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable IVBB used by german authorities."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "sambaIdMapping",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Maintain sambaIdmapEntry objects to improve performance on some Samba versions."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "samba",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "handleExpiredAccounts",
                                "type"          => "bool",
                                "default"       => "true",
                                "description"   => _("Enable checks to determine whether an account is expired or not."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "sambaSID",
                                "type"          => "string",
                                "default"       => "",
                                "description"   => _("String containing the SID for Samba setups without the Domain object in LDAP."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "samba",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "sambaRidBase",
                                "type"          => "integer",
                                "default"       => "",
                                "description"   => _("String containing the RID base for Samba setups without the Domain object in LDAP."),
                                "check"         => "gosaProperty::isInteger",
                                "migrate"       => "",
                                "group"         => "samba",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "enableSnapshots",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable manual object snapshots."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "snapshot",
                                "mandatory"     => TRUE),

                        array(
                                "name"          => "snapshotBase",
                                "type"          => "dn",
                                "default"       => "ou=snapshots,dc=localhost,dc=de",
                                "description"   => _("Base DN for snapshot storage."),
                                "check"         => "gosaProperty::isDn",
                                "migrate"       => "",
                                "group"         => "snapshot",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "snapshotAdminDn",
                                "type"          => "dn",
                                "default"       => "cn=admin,dc=localhost,dc=de",
                                "description"   => _("DN of the snapshot administrator."),
                                "check"         => "gosaProperty::isDn",
                                "migrate"       => "",
                                "group"         => "snapshot",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "snapshotAdminPassword",
                                "type"          => "string",
                                "default"       => "secret",
                                "description"   => _("Password of the snapshot administrator."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "snapshot",
                                "mandatory"     => FALSE),

                        array(
                                "name"          => "idAllocationMethod",
                                "type"          => "switch",
                                "default"       => "traditional",
                                "defaults"      => "core::getPropertyValues",
                                "description"   => _("Method for user and group ID generation. Note: only the 'traditional' method is safe due to PHP limitations."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "id",
                                "mandatory"     => TRUE),
                        array(
                                "name"          => "snapshotURI",
                                "type"          => "uri",
                                "default"       => "ldap://localhost:389",
                                "description"   => _("URI of server to be used for snapshots."),
                                "check"         => "",
                                "migrate"       => "",
                                "group"         => "snapshot",
                                "mandatory"     => FALSE),
                        array(
                                "name"          => "forceTranslit",
                                "type"          => "bool",
                                "default"       => "false",
                                "description"   => _("Enable transliteration of cyrillic characters for UID generation."),
                                "check"         => "gosaProperty::isBool",
                                "migrate"       => "",
                                "group"         => "core",
                                "mandatory"     => TRUE)
                            )));
    }
}
?>
