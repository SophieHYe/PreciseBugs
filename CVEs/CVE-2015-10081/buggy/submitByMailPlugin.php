<?php

/**
 * submitByMail plugin version 1.0b2.9
 * 
 *
 * @category  phplist
 * @package   submitByMail Plugin
 * @author    Arnold V. Lesikar
 * @copyright 2014 Arnold V. Lesikar
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/submitByMail .
 * 
 */
require_once dirname(__FILE__) . "/submitByMailPlugin/sbmGlobals.php";  	// __DIR__ not consistent with PHP 5.x earlier than 5.3
require_once dirname(__FILE__) . "/submitByMailPlugin/PEAR/Mail/mimeDecode.php";
/**
 * Registers the plugin with phplist
 * 
 * @category  phplist
 * @package   conditionalPlaceholderPlugin
 */

class submitByMailPlugin extends phplistPlugin
{
    // Parent properties overridden here
    public $name = 'Submit by Mail Plugin';
    public $version = '1.0b2.9';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows messages to be submitted to mailing lists by email';
    public $coderoot; 	// coderoot relative to the phplist admin directory
    public $DBstruct =array (	//For creation of the required tables by Phplist
    		'escrow' => array(
    			"token" => array("varchar(10) not null primary key", "Token sent to confirm escrowed submission"),
    			"file_name" => array("varchar(255) not null","File name for escrowed submission"),
    			"sender" => array("varchar(255) not null", "From whom?"),
    			"subject" => array("varchar(255) not null default '(no subject)'","subject"),
    			"listid" => array("integer not null","List ID"),
    			"listsadressed" => array("blob not null", "Array of list ids targeted, serialized"),
    			"expires" => array ("integer not null", "Unix time when submission expires without confirmation")
			), 
			'list' => array(
				"id" => array("integer not null primary key", "ID of the list associated with the email address"),
				"pop3server" => array ("varchar(255) not null", "Server collecting list submissions"),
				"submissionadr" => array ("varchar(255) not null", "Email address for list submission"),
				"password" => array ("varchar(255)","Password associated with the user name"),
				"pipe_submission" => array ("tinyint default 0", "Flags messages are submitted by a pipe from the POP3 server"),
				"confirm" => array ("tinyint default 1", "Flags email submissions are escrowed for confirmation by submitter"),
				"queue" => array ("tinyint default 0", "Flags that messages are queued immediately rather than being saved as drafts"),
				"template" => array("integer default 0", "Template to use with messages submitted to this address"),
				"footer" => array("text","Footer for a message submitted to this address")
			),
		);  				// Structure of database tables for this plugin
	
	public $tables = array ();	// Table names are prefixed by Phplist
	public $commandlinePluginPages = array ('pipeInMsg', 'collectMsgs'); 
	public $publicPages = array ('confirmMsg'); 
	
	public $settings = array(
    	"cliPath" => array (
      		'value' => '',
     		'description' => "Complete path to command line PHP binary (leave empty if you don't know it)",
      		'type' => "text",
      		'allowempty' => 1,
      		'category'=> 'general',),
      
    	"publicPageProtocol" => array (
    		'value' => 1,
    		'description' => "Use 'http' for public page links instead of 'https' (Yes or No)",
    		'type' => "boolean",
      		'allowempty' => 1,
      		'category'=> 'general',), 
      	
		"escrowHoldTime" => array (
      		'value' => 1,
     		'description' => 'Days escrowed messages are held before being discarded',
      		'type' => "integer",
      		'allowempty' => 0,
      		"max" => 7,
      		"min" => 1,
      		'category'=> 'general',),
      
		"manualMsgCollection" => array (
    		'value' => 1,
    		'description' => 'Use browser to collect messages submitted by POP (Yes or No)',
    		'type' => "boolean",
      		'allowempty' => 1,
      		'category'=> 'general',), 
      	
      	"popTimeout" => array (
      		'value' => 0,
    		'description' => 'POP3 timeout in seconds; set 0 to use default value',
    		'type' => "integer",
      		'allowempty' => 1,
      		"max" => 120,
      		"min" => 0,
      		'category'=> 'general',), 
    // Note that the content type of the message must be multipart or text
    // The settings below apply to attachments.
    // Note also that we do not allow multipart attachments.
		"allowedTextSubtypes" => array(
			'value' => 'plain, html',
    		'description' => 'MIME text/subtypes allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 0,
      		'category' => 'general',),
      		
		'allowedImageSubtypes' => array(
			'value' => 'gif, jpeg, pjpeg, tiff, png',
    		'description' => 'image/subtypes allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 1,
      		'category' => 'general',),
      		
      	"allowedMimeTypes" => array (
    		'value' => 'application/pdf',
    		'description' => 'Additional MIME content-types allowed for attachments',
    		'type' => 'text',
    		'allowempty' => 1,
      		'category' => 'general',),
      	);
	
	// Arrays for the menu system
	public $pageTitles = array ("configure_a_list" => "Configure a List for Submission by Email",
								"collectMsgs" => "Collect Messages Submitted by Email",
								"generateScripts"=> "Generate Scripts for Mailbox Pipes and Cron");
	public $topMenuLinks = array('configure_a_list' => array ('category' => 'config'),
								  'collectMsgs' => array ('category' => 'campaigns'), 
								  'generateScripts' => array('category' => 'config')
								  );	
	
	// Properties particular to this plugin  	
  	public $escrowdir; 	// Directory for messages escrowed for confirmation
  	
  	private $errMsgs = array(
  							"nopipe" => 'Msg discarded: pipe not allowed for this list',
  							"nodecode" => 'Msg discarded: cannot decode',
  							"badbox" => 'Msg discarded: bad mailbox',
  							'nolists' => 'Msg discarded: no lists addressed',
  							"unauth" => "List '%s': Msg discarded; unauthorized sender",
  							'unauthp' => "List '%s': Msg discarded: sent to list(s) sender does not own",
  							"badmain" => "List '%s': Msg discarded; bad type for main message",
  							"badtyp" => "List '%s': Msg discarded; mime type not allowed",
  							"noattach" => "List '%s': Msg discarded; attachments not permitted",
  							"toodeep" => "List '%s': Msg discarded; mime nesting too deep",
  							"badinlin" => "List '%s': Msg discarded; inline type not allowed"
  							);
    private $days = array ('', 'one day', 'two days', 'three days', 'four days', 'five days', 'six days', 'seven days');
  							
  	public $numberPerList = 20;		// Number of lists tabulated per page in listing
  	
	private $allowedMimes = array(); // Allowed MIME subtypes keyed on types
	private $allowedMain = array(); // MIME subtypes allowed as main message, keyed
									// on types
	public $deleteMsgsOnReceipt = CL_EXPUNGE;	// Applies to POP mailboxes. Normally we
										// set this flag to CL_EXPUNGE. It is set to 0 only
										// for testing and debugging the POP routines.
	// Parameters for the message we are dealing with currently
	// If only PHP had genuine scope rules, so many private class properties would not 
	// be necessary!!
	public $lid = 0;		// ID of the list whose mailbox is handling the message (the first list sent to)
	public $alids = array();	// IDs for the lists receiving current message
	public $sender = '';		// Sender of the current message
	public $displayName = '';	// Name of the sender
	public $subj = '';			// Subject line of the current message
	private $mid;				// Message ID for current message being saved or queued
	private $holdTime;			// Days to hold escrowed message
	private $textmsg;	// Text version of current message	
	private $htmlmsg;	// HTML version of current message
	private $embeddedImage; 	// Flag msg constains embedded image. This is an error
	private $publicScheme;		// phpList does not set public page links from command line pages
	
	const ONE_DAY = 86400; 	// 24 hours in seconds

  	public function __construct()
    {
    	if (!function_exists('imap_open') || !$this->isSecureConnection()) { // Don't have prerequisites
    		$this->uninitialize();
    		parent::__construct();
    		return;
    	}
    	
    	$this->coderoot = dirname(__FILE__) . '/submitByMailPlugin/';
	   	
	   	$this->escrowdir = $this->coderoot . "escrow/";
		if (!is_dir($this->escrowdir))
			mkdir ($this->escrowdir);
		
		$this->holdTime =getConfig("escrowHoldTime");	
		
		// Build array of allowed MIME types and subtypes
		$str = getConfig('allowedTextSubtypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$this->allowedMimes['text'] = explode(',', $str);
    	
    	$str = getConfig('allowedImageSubtypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$this->allowedMimes['image'] = explode(',', $str);
    	
    	$str = getConfig('allowedMimeTypes');
    	$str = strtolower(str_replace(' ','', $str));
    	$addTypes = explode (',', $str);
    	foreach ($addTypes as $val) {
    		$partial = explode('/', $val);
    		$this->allowedMimes[trim($partial[0])][] = trim($partial[1]);    	
    	}
    	
    	// Don't let the admin add to the multipart types
    	$this->allowedMimes['multipart'] = array('mixed', 'alternative', 'related');
    	$this->allowedMain = array('text' => array('plain', 'html'), "multipart" => $this->allowedMimes['multipart']);
    	
    	$this->pop_timeout = (int) getConfig("popTimeout");
    	if ($this->pop_timeout) {
    		imap_timeout (IMAP_OPENTIMEOUT, $this->pop_timeout);
    		imap_timeout (IMAP_READTIMEOUT, $this->pop_timeout);
    		imap_timeout (IMAP_WRITETIMEOUT, $this->pop_timeout);
    	}
    	
    	// Properly set public scheme; phpList always sets this to 'http' if running
    	// from command line
    	$this->publicScheme = getConfig("publicPageProtocol")? 'http' : 'https';

    	parent::__construct();
	
    	/* Delete escrowed messages that have expired
    	   Do this here, because we don't want the user to have to set up a cron script
    	   for this. We don't have the name of the relevant database table until after
    	   the parent is constructed. */
    	
    	// This class is constructed before the plugin is initialized. Make
    	// sure we have the 'escrow' table before attempting to delete expired msgs
    	if (Sql_Table_Exists($this->tables['escrow'])) {
    		$this->deleteExpired();
    	}    
	}
   	
   	// Remove initialization flag into phpList configuration table to prevent
   	// use of plug in after it is found that we do not have the proper prequisites
   	private function uninitialize() {
   		Sql_Query(sprintf("delete from %s where item ='%s'", $GLOBALS['tables']['config'], 
    					md5('plugin-submitByMailPlugin-initialised')));
   	}     
   	
    // Determine if we have a secure https connection.
    // This code was adapted from the comment by temuraru on the Stack Overflow page
    // at http://stackoverflow.com/questions/1175096/how-to-find-out-if-youre-using-https-without-serverhttps
    //
    // Why do we need this? We cannot tie a list to a POP account without sending a 
    // password between the browser and server. Without a secure connection, the list
    // might become open to spammers. The danger is less within mail accounts connected to 
    // a pipe, but still spammers could learn that the account is connected to a mailing list.
    //
    private function isSecureConnection() {
    	if ($GLOBALS['commandline']) return true; 	// Command line is internal and secure (pipe 
    												// and cron and assuming SSH if on terminal)
    	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') return true;
    	// The following line applies for servers behind a load balancer
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') return true;
		return false;
	}
	
    # Startup code, all other objects are constructed 
    # Returns false when we do not have the prereqs, which means we cannot start
    # Also returns false when not running off a secure connection
    public function activate() {
        return true;
  	}

	// Delete expired messages in escrow
    private function deleteExpired() {
    	$query = sprintf("select token, file_name from %s where expires < %d", $this->tables['escrow'], time());
    	$result = Sql_Query ($query);
    	while ($row = Sql_Fetch_Row($result)) {
    		unlink ($this->escrowdir . $row[1]);
    		$query = sprintf ("delete from %s where token = '%s'", $this->tables['escrow'], $row[0]);
    		Sql_Query($query);
    	}
    }

  	// Provide complete server name in a form suitable for a SSL/TLS POP call using iMap function
  	private function completeServerName($server) {
  		return '{' . $server . submitByMailGlobals::SERVER_TAIL . '}';
  	}
  	
  	private function getCliPhp() {
  		if ($cmd = getConfig("cliPath"))
  			return $cmd;
  		exec('which php-cli', $output);
  		if ($output)
  			return trim($output[0]);
  		exec('which php', $output);
  		return trim($output[0]);
  	}
  	
  	public function ckPHPversion() {
  		if ($cmd = $this->getCliPhp()) {
  			exec ("$cmd -v", $output);
  			if (preg_match ('/PHP\s*(\d\d?\.\d\d?)/', $output[0], $match))
  				return $match[1];
  		}
  		return "";
  	}
  	
  	// Generate a command line PHP command to be used by emailajax.php
	public function makeCliCommand($page) {
		if ($cmd = $this->getCliPhp()) {
			$cmd = $cmd . ' -q ' . dirname(dirname($this->coderoot)) . '/index.php ';
			$cmd .= '-c' . realpath($GLOBALS["configfile"]) . " -p$page -msubmitByMailPlugin";
			return $cmd;
		}
		return '';
	}
  	
  	public function adminmenu() {
  		// Adjust what adminMenu returns for different circumstances.
	   	if (!isSuperUser()) { 
    		// Make sure that only super users get to see the adminMenu.
    		$this->topMenuLinks = $this->pageTitles = array(); 
    	} else if (!getConfig("manualMsgCollection")) {
    			// Make sure that we don't show the message collection page if we don't allow
    			// manual collection of messages, remove that page from the menus.
    			unset($this->topMenuLinks["collectMsgs"]);
    			unset($this->pageTitles["collectMsgs"]);
    	}
    	return $this->pageTitles;
	}
	
	public function allowMessageToBeQueued($messagedata = array()) {
		if (($this->lid) && ($this->embeddedImage)) 
			return "Message cannot be sent with unprocessed embedded image.";
    	if (($this->lid) && ($this->subj == '(no subject)')) { 
    			//$this->lid is nonzero only if it is this plugin that is processing the
    			//message rather than Phplist's send_core.php
    		return "Message cannot be sent with missing subject line.";
		}
    	return '';
  	}
		
	private function generateRandomString($length = 10) {
    	return substr(sha1(mt_rand()), 0, $length);
	}
	
  	public function cleanFormString($str) {
		return sql_escape(strip_tags(trim($str)));
	}
	
	// Produce button links to pages outside the plugin
	public function outsideLinkButton($name, $desc, $url = '', $extraclass = '',$title = '' ) {
		$str = PageLinkButton($name, $desc, $url, $extraclass, $title);
		$str = str_replace("&amp;pi=submitByMailPlugin", '', $str);
		return $str;
	}
	
	public function myFormStart($action, $additional) {
		$html = formStart($additional);
		preg_match('/action\s*=\s*".*"/Ui', $html, $match);
		$html = str_replace($match[0], 'action="' . $action .'"', $html);
		return $html;
	}
    
    // Get an array of the mailing lists with submission address and list id
    public function getTheLists($name='') {
    	global $tables;
    	$A = $tables['list']; 	// Phplist table of lists, including name and id
		$B = $this->tables['list'];	// My table holds mail submission data for lists
		$out = array();
		if (strlen($name)) {
			$where = sprintf("WHERE $A.name='%s' ", $name); 
		}
    	$query = "SELECT $A.name,$B.submissionadr,$A.id FROM $A LEFT JOIN $B ON $A.id=$B.id {$where}ORDER BY $A.name";
    	if ($res = Sql_Query($query)) {
    		$ix = 0;
    		while ($row = Sql_Fetch_Row($res)) {
    			$out[$ix] = $row;
    			$ix += 1;
    		}	
    	}
    	return $out; 
    }
          
    // Get the numberical id of a list from its email submission address
    public function getListID ($email) {
    	$out = 0;
    	if (preg_match('/<(.*)>/', $email, $match))
			$email = $match[1];$query = sprintf("select id from %s where submissionadr='%s'", $this->tables['list'], trim($email));
    	$res = Sql_Query($query);
    	$row = Sql_Fetch_Row($res);
    	return $row[0];
    }
    
    public function getCredentials ($email) {
    	$query = sprintf("select pop3server, password from %s where submissionadr='%s'",
    		$this->tables['list'], $email);
    	return Sql_Fetch_Assoc_Query($query);
    }
    
    // Returns array of connection parameters for the lists receiving messages via POP
    public function getPopData() {
    	$out = array();
    	$query = sprintf("select id, pop3server, submissionadr, password from %s where pipe_submission=0",
    		$this->tables['list']);
    	$result = Sql_Query($query);
    	while ($row = Sql_Fetch_Assoc($result))
    		$out[] = $row;
    	return $out;
    }
        
    // What to do with messages for a particular list
    private function getDisposition ($id) {
    	$query = sprintf ("select confirm, queue from %s where id=%d", $this->tables['list'], $id);
    	$row = Sql_Fetch_Array_Query($query);
    	if (is_null($row)) return null;
    	return $row[0]? "escrow" : ($row[1]? "queue" : "save"); 
    }
    
    public function doQueueMsg($lid) {
    	$query = sprintf ("select queue from %s where id=%d", $this->tables['list'], $lid);
    	$row = Sql_Fetch_Array_Query($query);
    	return $row[0];
    }
    
    public function pipeOK($lid) {
    	$query = sprintf ("select pipe_submission from %s where id=%d", $this->tables['list'], $lid);
    	$row = Sql_Fetch_Array_Query($query);
    	return $row[0];
    }
    
    private function getListTmpltFtr($id) {
    	$query = sprintf ("select template, footer from %s where id=%d", $this->tables['list'], $id);
    	$row = Sql_Fetch_Row_Query($query);
    	return $row;
    }
    
    private function getListAdminAdr($listId) {
    	$A = $GLOBALS['tables']['admin'];
    	$B = $GLOBALS['tables']['list'];
    	$query = sprintf ("select email from %s left join %s on %s.id=%s.owner where %s.id=%d", $A, $B, $A, $B, $B, $listId ); 
    	$row = Sql_Fetch_Row_Query($query);	
		return $row[0];
	}
    
    // Get the email addresses of all the admins
    private function getAdminAdrs() {
    	$query = sprintf("select email from %s", $GLOBALS['tables']['admin']);
    	$result = Sql_Query($query);
    	while ($row = Sql_Fetch_Row($result))
    		$out[] = $row[0];
    	return $out;
    }
    
    private function getOwnerLids ($email) {
    	$out = array();
    	$A = $GLOBALS['tables']['list'];
    	$B = $GLOBALS['tables']['admin'];
    	$query = sprintf ("select %s.id from %s left join %s on %s.id=%s.owner where %s.email='%s'", $A, $A, $B, $B, $A, $B, $email);
    	$result = Sql_Query($query);
    	while ($row = Sql_Fetch_Row($result))
    		$out[] = $row[0];
    	return $out;
    }
    
    // Return addresses of all superusers
    private function getSuperAdrs() {
    	$query = sprintf ("select email from %s where superuser=1", $GLOBALS['tables']['admin']);
    	$res = Sql_query($query);
    	while ($row = Sql_Fetch_Row($res))
    		$out[] = trim($row[0]);
    	return $out;
    }
    
    private function getSenderID($sender) {
    	$query = sprintf ("select id from %s where email='%s'", $GLOBALS['tables']['admin'], $sender);
    	$row = Sql_Fetch_Row_Query($query);
    	return $row[0];
	}
   
    private function std($str) {
    	return strtolower(trim($str));
    }
    
    // Get out the email address from a string of the form Name<email_address>
    // Modified to show display name of sender
	public function cleanAdr ($adr, $setDisplayName=false) { 
		if ($setDisplayName)
			$this->displayName = ''; 
		 if (preg_match('/<(.*)>/', $adr, $match)) { 
			if ($setDisplayName)
			 	$this->displayName = trim(str_replace($match[0],'', $adr)); 
			 return trim($match[1]); 
		} 
		return trim($adr); 
	}    
	
    // Get filename associated with a MIME part if there is one
    private function getFn($apart) {
    	if (isset($apart->d_parameters['filename']))
    		return $apart->d_parameters['filename'];
    	if (isset($apart->ctype_parameters['name']))
    		return $apart->ctype_parameters['name'];
    	return false;
    }
    
    // Check the structure of one mime part of a message.
    // Returns an error code or false if there is no problem.
    private function isBadMime($apart, $lvl) {
    	$mimes = $this->allowedMimes;
    	$mains = $this->allowedMain;
    	$c1 = $this->std($apart->ctype_primary); 
    	$c2 = $this->std($apart->ctype_secondary);
    	if (isset($apart->disposition)) $dp = $this->std($apart->disposition);

    	if ($lvl > 2)
    		return "toodeep"; 	// Mime parts too deeply nexted
    	
    	// Is the part an allowed mime type, subtype:
    	if ((!array_key_exists( $c1,$mimes)) || (!in_array($c2, $mimes[$c1])))
    		return "badtyp";		// Message has a forbidden mime type
    	
    	// If multipart, check the parts	
    	if ($c1 == 'multipart') {
    		foreach ($apart->parts as $mypart) {
   			if ($result = $this->isBadMime($mypart, $lvl+1))	// Return if find bad part
    				return $result;
    		}
    		return false;    		
    	} else { // if not multipart, is it OK as attachment or inline?
    	
    		// Do we have a file name? Treat the part as an attachment
    		// But if its an image it could also be inline even with a file name
    		$havefn = $this->getFn($apart);
    		// Don't check for inline images. If we have a file name we can treat the
    		// image as an attachment, ignoring the inline disposition directive, which 
    		// is misused bu user agents anyway.
    		if ($havefn) {
    			if (!ALLOW_ATTACHMENTS) return "noattach";	// Have an attachment when none or allowed.
    			return false; 	// If we got here the file type is an acceptable mime type
    		}
    		
    		// If no file name, but have something other than text or multipart
    		// Treat it as inline and an error. It is at this point that we catch inline images
    		// without file names. We could create a file name and treat the image as simply
    		// an attachment, but it's better to inform the sender of the problem.
    		// Multipart type is excluded by this point; we are only looking for text types
    		if (!$havefn && ((!array_key_exists( $c1, $mains)) || (!in_array($c2, $mains[$c1]))))
    			return "badinlin"; 		// Forbidden inline attachment
    		return false;
    	}
    }  
    
    // Get the lists addressed by the message. Return an array of submission addresses
    // for the lists we're sending to.
    private function getListsAddressed(&$hdrs) { // A bit more efficient here to call by reference.
    	// First find the submission addresses for our lists
		$sbmAdrs = array();	
		$arr = $this->getTheLists();
		foreach ($arr as $val) {
			if (!$val[1]) continue;
			$sbmAdrs[] = $val[1];		
		}		
		// What lists are addressed by the message?
		$listsSentTo = array();
		// Some user agents spread multiple addressees to separate lines:
		$str = preg_replace("#\r?\n#", '', $hdrs['to'] . ($hdrs['cc']? (',' . $hdrs['cc']): ''));
		$tos = explode(',', $str);	
		foreach ($tos as $adr) {
			$adr = $this->cleanAdr($adr);
			if (in_array($adr, $sbmAdrs)) $listsSentTo[] = $this->getListID($adr);	 
		}
		return $listsSentTo;
    }
    
    private function isUnauthorized($from) {
    	$authSenders[] = $this->getListAdminAdr($this->lid); // Admin for this list
		// Authorized senders are the list administrator and superusers
		$isSuperUser = in_array($this->sender, $this->getSuperAdrs());
		if ($isSuperUser) $isAdmin = 1;			// Can send to all lists
		else $isAdmin = in_array($this->sender, $this->getAdminAdrs());
		if (!$isAdmin) return 'unauth';	
		if (!$isSuperUser) {					// If not a super user, can send only to own lists
			$owned = $this->getOwnerLids($this->sender);
			/* We should respond to a list owner, even if the message is not sent
			to lists owned by that person. The code 'unauth' suppresses a response.
			if (!array_intersect ($this->alids, $owned))
				return 'unauth';					*/			
			if (array_diff($this->alids, $owned))
				return 'unauthp';
		}
		return false;
	}
   
 	// Check if the message is acceptable; $mbox is the address at which the email arrived.
    // We need this so that in case of a submission to multiple lists we can tell
    // which list we are sending this instance of the message to. In such a case we do
    // not do anything, unless $mbox represents the first list the message is sent to.
    // If there is a problem with the message, returns a short error string.
    //
    // As a side effect this function sets various class properties for the current message
    // such as $this->subj and $this->sender
    private function isBadMessage ($msg, $mbox) {  // Maybe check message and attachment sizes here?
    	$isSuperUser = $isAdmin = 0;
    	$mbox = $this->cleanAdr($mbox);	// The user might screw up the argument in a pipe
    	$decoder = new Mail_mimeDecode($msg);
		$params['include_bodies'] = false;
		$params['decode_bodies']  = false;
		$params['decode_headers'] = true;
		$out = $decoder->decode($params);
		$hdrs = $out->headers;

		$this->sender = $this->cleanAdr($hdrs['from'], true); // Modified to show display name of sender
		if (!($hdrs['to'] && $this->sender)) return 'nodecode';
		
		$this->subj = trim($hdrs['subject']); 
		if (!$this->subj) $this->subj = '(no subject)'; // Need something here, to show in
														// messages.
		
		// Find the lists the message is addressed to
		// Decide which list is going to handle the message for the others
		$this->lid = $this->getListID($mbox);
		if (!$this->lid) return "badbox";
		// The first list in the address list is the one which will handle the message
		// If the current mailbox does not represent that list, quit
		$this->alids = $this->getListsAddressed($hdrs);	// List IDs of lists receiving the message
		if (!$this->alids) {
			$this->sender = '';	// No lists addressed, so no response needed!
			return 'nolists';
		}
		// Quit if this is not the list that is supposed to handle the message
		if ($this->lid != $this->alids[0]) return 'not_ours'; 
		
		// Check authorizations for the lists addressed	
		if ($errcode = $this->isUnauthorized($hdrs['from'])) {
			if ($errcode == 'unauth') $this->sender = '';	// Don't respond to unauthorized senders!
			return $errcode;
		}
		
		// If we have a message piped in, check if the pipe is allowed.
		if (($GLOBALS['commandline']) && ($_GET['page'] == 'pipeInMsg') && (!$this->pipeOK($this->lid)))
			return 'nopipe';
		
		// Check that we have an acceptable MIME structure
		$mains = $this->allowedMain;
		$c1 = $this->std($out->ctype_primary); 
    	$c2 = $this->std($out->ctype_secondary);
		if ((!array_key_exists( $c1, $mains)) || (!in_array($c2, $mains[$c1]))) 
    		return "badmain";		// The main message is not proper text or multipart
    	if ($c1 == 'text') 	// Must be plain or html here
    		return false;
    	else { 	// Multipart
    		foreach ($out->parts as $mypart) {
    			if ($result = $this->isBadMime($mypart, 1))	// Return if find bad part
    				return $result;
    		}
    	return false;	// All parts OK 
    	}  	
	}
	
	// Hold message for confirmation by the sender
	// Save msg in the 'escrow' subdirectory and save location and message information
	// in the Phplist database. 
	private function escrowMsg($msg) {
		$tfn = tempnam ($this->escrowdir , "msg" );
		file_put_contents ($tfn, $msg);
		$fname = basename($tfn);
		$tokn = $this->generateRandomString();
		$xpir = time() + self::ONE_DAY * $this->holdTime;
		// Modified the lines below so that we can show the display name of the sender
		$sndr = ($this->displayName? $this->displayName . ' <' . $this->sender . '>' : $this->sender);
		$query = sprintf ("insert into %s values ('%s', '%s', '%s','%s', %d, '%s', %d)", $this->tables['escrow'], $tokn, $fname, 
			sql_escape($sndr), sql_escape($this->subj), $this->lid, sql_escape(serialize ($this->alids)), $xpir);
		Sql_Query($query);
		return $tokn;
	}

	// Some email user agents separate sections of html messages showing email attachments
	// inline. Apple Mail is an example of this. The result can be multiple html and body tags in
	// tags in a message. There may even be a DOCTYPE tag. We remove all these tags
	// to produce the kind of HTML that the Phplist editor produces.
	//
	// User agents may produce all sorts of mixtures of plain text and html, for example
	// a long text message with an html part at the end, following an inline attachment.
	// For Phplist we separate the text and html messages, and there is nothing that we 
	// can do if the text and html of the message do not have similar content 
	private function cleanHtml($html) {
		$html = preg_replace('/^\s*<!doctype[^>]*>\s*$/im', "", $html);
		$html = preg_replace('#<head[^>]*>.*</head>#imsU', '', $html);
		$html = preg_replace('#<html.*>|<body.*>#isU', '', $html);
		$html = str_ireplace("</html>", "", $html); 
		$html = str_ireplace("</body>", "", $html);
		$html = preg_replace('/^\s*\r?\n/m', '', $html);
		return $html;
	}
	
	/*	The following methods are not useable independently. They have been pulled out
		out of receiveMsg in order to make the logic clearer and to ease testing.    */
	// Save the $messagedata array in the database. This code if taken almost verbatim
	// from the Phplist file sendcore.php. We save the message data only after setting
	// the message status. Requires the class property $this->mid to be set.
	private function saveMessageData($messagedata) {
		global $tables;
		$imageWarning = 
			'<p style="color:red; font-weight:bold;">Embedded images not allowed in email
			submissions. The image below cannot be displayed.</p>';
		$query = sprintf('update %s  set '
     		. '  subject = ?'
     		. ', fromfield = ?'
     		. ', tofield = ?'
     		. ', replyto = ?'
     		. ', embargo = ?'
     		. ', repeatinterval = ?'
     		. ', repeatuntil = ?'
     		. ', message = ?'
     		. ', textmessage = ?'
     		. ', footer = ?'
     		. ', status = ?'
     		. ', htmlformatted = ?'
     		. ', sendformat  =  ?'
     		. ', template  =  ?'
     		. ' where id = ?', $tables["message"]);
     	$htmlformatted = ($messagedata["sendformat"] == 'HTML'); 
     	
     	if ($this->embeddedImage) {
     		$messagedata["message"] = $imageWarning . $messagedata["message"];
     	}
  		
  		$result = Sql_Query_Params($query, array(
       		$messagedata['subject']
     		, $messagedata['fromfield']
     		, $messagedata['tofield']
     		, $messagedata['replyto']
     		, sprintf('%04d-%02d-%02d %02d:%02d',
        		$messagedata['embargo']['year'],$messagedata['embargo']['month'],$messagedata['embargo']['day'],
        		$messagedata['embargo']['hour'],$messagedata['embargo']['minute'])
     		, $messagedata['repeatinterval']
     			, sprintf('%04d-%02d-%02d %02d:%02d',
        		$messagedata["repeatuntil"]['year'],$messagedata["repeatuntil"]['month'],$messagedata["repeatuntil"]['day'],
        		$messagedata["repeatuntil"]['hour'],$messagedata["repeatuntil"]['minute'])
     		, $messagedata["message"]
     		, $messagedata["textmessage"]
     		, $messagedata["footer"]
     		, $messagedata['status']
     		, $htmlformatted ? '1' : '0'
     		, $messagedata["sendformat"]
     		, $messagedata["template"]
     		, $this->mid));
     		
     	// Have to save the target lists in the 'listmessage' table.
     	foreach ($this->alids as $listid) {
      		$query = "replace into %s (messageid,listid,entered) values(?,?,current_timestamp)";
      		$query = sprintf($query,$GLOBALS['tables']['listmessage']);
      		Sql_Query_Params($query,array($this->mid,$listid));
    	}
     	return $this->mid; 	// Return private message ID so we can use it in other files
	}
	
	// Figure out what is going on with a MIME part and process it accordingly
	private function parseAPart($apart) {
		global $tables;
		$c1 = $this->std($apart->ctype_primary); 
    	$c2 = $this->std($apart->ctype_secondary);

    	// If multipart, check the parts	
   		if ($c1 == 'multipart') {
    		foreach ($apart->parts as $mypart) {
  				$this->parseaPart ($mypart);
    		}  		
    	} else { // if not multipart, is it OK as attachment or inline?
			// Do we have a file name? Treat the part as an attachment
	   		if (($attachname = $this->getFn($apart)) && strlen($apart->body)) {
    			// Handle atttachment
    			list($name,$ext) = explode(".",basename($attachname));
        		# create a temporary file to make sure to use a unique file name to store with
        		$newfile = tempnam($GLOBALS["attachment_repository"],$name);
        		unlink ($newfile); 	// Want the name, not the file that tempnam creates
        		$newfile .= ".".$ext;
        		file_put_contents($newfile, $apart->body);
         		Sql_query(sprintf('insert into %s (filename,remotefile,mimetype,description,size) values("%s","%s","%s","%s",%d)',
          			$tables["attachment"],
          			basename($newfile), 
          			$attachname, 
          			$c1 . '/' . $c2, 
          			'From submitted email', 
          			filesize($newfile))
          		);
          		$attachmentid = Sql_Insert_Id();
      		 	Sql_query(sprintf('insert into %s (messageid,attachmentid) values(%d,%d)',
          			$tables["message_attachment"],$this->mid,$attachmentid));
          	}  else {	// if not multipart and not attachment must be text/plain or text/html
    				if ($c2 == 'plain') {
    					$this->textmsg .= $apart->body;
    				} else
    					$this->htmlmsg .= $apart->body; 
    		} 
    	} 
    }
    
	// Do the actual decoding of bodies of message
	// Before this function is called, we have already determined that all of the 
	// message parts are acceptable
	private function decodeMime ($msg) {
		$decoder = new Mail_mimeDecode($msg);
		$params['include_bodies'] = true;
		$params['decode_bodies']  = true;
		$params['decode_headers'] = true;
		$out = $decoder->decode($params);
		$this->parseApart($out);	
	} 
	
	// Put default message values into the Phplist database and get an ID for the 
	// message. Then load the message data array with values for the message
	// that we get directly by decoding the message. Note that we do not complete
	// fill the messageData array, overwriting relevant defaults, until the message is
	// saved by the saveMessageData() method.
	//
	// This method provides a message ID, that is saved in the plugin property $mid. This
	// corresponds to set of default entries in the database. We do not overwrite these
	// default data until the message is actually saved. The purpose of the database
	// access in this method, is merely to acquire a message ID.
	private function loadMessageData ($msg) {
	 	
	 	// Note that the 'replyto' item appears not to be in use
  		// This item in $messagedata must exist, but it will remain empty
  		// So we do nothing further with it
  		$query
  		= " insert into %s"
  		. "    (subject, status, entered, sendformat, embargo"
  		. "    , repeatuntil, owner, template, tofield, replyto,footer)"
  		. " values"
  		. "    ('(no subject)', 'draft', current_timestamp, 'HTML'"
  		. "    , current_timestamp, current_timestamp, ?, ?, '', '', ? )";
  		$query = sprintf($query, $GLOBALS['tables']['message']);
  		Sql_Query_Params($query, array($this->getSenderID($this->sender), $defaulttemplate,$defaultfooter));
  		// Set the current message ID
  		$this->mid = Sql_Insert_Id();
      	// Now create the messageData array with the default values
      	// We are going to load it with the template and footer set for the current list
      	// and the MIME decoded message
      	$messagedata = loadMessageData($this->mid);
      	$messagedata['subject'] = $this->subj;
      	$messagedata['fromfield'] = ($this->displayName? $this->displayName . ' <' . $this->sender . '>' : $this->sender);
      	$tempftr = $this->getListTmpltFtr($this->lid);
      	$messagedata['template'] = $tempftr[0];
      	$messagedata['footer'] = $tempftr[1];
      	
      	// Now decode the MIME. Load attachments into database. Get text and html msg
      	$this->decodeMime($msg);
      	
      	$messagedata["sendformat"] = 'HTML';      		
      	if ($this->htmlmsg) {
      		$messagedata["message"] = $this->cleanHtml($this->htmlmsg);
      		$this->embeddedImage = preg_match('/<img [^>]*src\s*=\s*"cid:/i', $messagedata["message"]); 
      		if ($this->textmsg)
      			$messagedata["textmessage"] = $this->textmsg;
      	} else {
      		$messagedata["message"] = "<p>" . preg_replace("@<br />\s*<br />@U", "</p><p>", nl2br($this->textmsg)) . "</p>" ;
      		$messagedata["textmessage"] = $this->textmsg;
       	}
		return $messagedata;
	}  
	
	// Update the status for the current message
	private function updateStatus($status) {
		$query = sprintf("update %s set status='%s' where id=%d", $GLOBALS['tables']['message'], $status, $this->mid);
		sql_query($query);
	}
	
	public function saveDraft($msg) {
		$msgData = $this->loadMessageData ($msg); 	// Default messagedata['status'] is 'draft'

		// Allow plugins manipulate data or save it somewhere else
  		foreach ($GLOBALS['plugins'] as $pluginname => $plugin)
  			$plugin->sendMessageTabSave($this->mid,$msgData);
		return $this->saveMessageData($msgData);  	// Return message ID
	}
	
	public function queueMsg($msg) {
		$msgData = $this->loadMessageData ($msg);
		$this->saveMessageData($msgData);

		// Now can we queue this message. Ask if it's OK with the plugins
		$queueErr = '';
		foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
  			$pluginerror = $plugin->allowMessageToBeQueued($msgdata);
  			if ($pluginerror) 
  				$queueErr .= $pluginerror . "\n"; 
  		}
  		if (!$queueErr) {
 			$this->updateStatus('submitted');
			return '';
		} else
			return $queueErr;
	}
	
	// This method downloads and processes the messages in an account
	// $anAcct is an associative array containing the credentials for the account
	// $count is an associative array containing the count of the different 
	// outcomes from the message processing
	public function downloadFromAcct ($anAcct, &$count) {
		// Open the default mailbox, i.e., the inbox
		if ($hndl = imap_open($this->completeServerName($anAcct['pop3server']), 
			$anAcct['submissionadr'], $anAcct['password'] )){
			$nm = imap_num_msg($hndl);
			for ($i = 1; $i <= $nm; $i++) {
				if (($hdr = imap_fetchheader($hndl, $i)) && ($bdy = imap_body ($hndl, $i))) {
					$msg = $hdr . $bdy;
					$this->receiveMsg($msg, $anAcct['submissionadr'], $count);
					if ($this->deleteMsgsOnReceipt) imap_delete($hndl, $i);
				} else {
					logEvent("Lost connection to $anAcct[submissionadr]");
					$count['lost']++;
					break;
				}
			}
			imap_close($hndl, $this->deleteMsgsOnReceipt);
		} else {
			logEvent("Connection to $anAcct[submissionadr] timed out");
			$count['lost']++;
		}
	}
	
	// This function is called for each message as it is received
	// to determine whether the message should be escrowed or processed immediately
	// $count is an optional array with the proper items to count the outcomes.
	public function receiveMsg($msg, $mbox, &$count=null) {
		// If we are processing multiple messages, it's important to reinitialize
		// the parameters for each message.
		$this->lid = 0;		
		$this->alids = array();
		$this->sender = $this->subj = '';
		$this->embeddedImage = false;
		if ($result = $this->isBadMessage($msg, $mbox)) {
			if ($result == 'not_ours') return;	// Quit if the current message was not sent to the address of the current list
			logEvent(sprintf($this->errMsgs[$result], listName($this->lid)));
			if ($this->sender) {	// We have to know who gets the response
				// Edit the log entry for the email to the sender
				$ofs = strpos($this->errMsgs[$result], 'Msg discarded;') + strlen('Msg discarded;');
				sendMail($this->sender, "Message Received and Discarded",
					"A message with the subject '" . $this->subj . "'was received but discarded for the following reason:" . 
						substr($this->errMsgs[$result], $ofs));
			}
			if (is_array($count)) $count['error']++;
		} else { 
			$err = '';
			if (count($this->alids) > 1)
				$disposn = 'escrow';
			else
				$disposn = $this->getDisposition($this->lid); 
			
			switch ($disposn) {
				case 'escrow':
					$tokn = $this->escrowMsg($msg);
					$site = getConfig('website');
					$cfmlink = $this->publicScheme . '://' . $site . $GLOBALS["pageroot"]; 
					$cfmlink .= "/?p=confirmMsg&pi=submitByMailPlugin&mtk=$tokn";
					$escrowMsg = "A message with the subject '" . $this->subj . "' was received and escrowed.\n\n";
					$escrowMsg .= "To confirm this message, please click the following link:\n\n" ;
					$escrowMsg .= "$cfmlink\n\n";
					$escrowMsg .=	"You must confirm this message within " . $this->days[$this->holdTime];
					$escrowMsg .= " or the message will be deleted.";
					sendMail($this->sender, 'Message Received and Escrowed', $escrowMsg); 
					logEvent("A message with the subject '" . $this->subj . "' was escrowed.");
					if (is_array($count)) $count['escrow']++;
					break;
				case 'queue': 						
					if ($err = $this->queueMsg($msg)) {
						sendMail($this->sender, 'Message Received but NOT Queued', 
							"A message with the subject '" . $this->subj . 
								"' was received. It was not queued because of the following error(s): \n\n$err\n" 
								. "The message will be saved as a draft.");
						logEvent("A message with the subject '" . $this->subj ."' received but not queued because of a problem.");
						if (is_array($count)) $count['draft']++;
					} else {
						sendMail($this->sender, 'Message Received and Queued', 
						"A message with the subject '" . $this->subj . "' was received and is queued for distribution.");
						logEvent("A message with the subject '" . $this->subj ."' was received and queued.");
						if (is_array($count)) $count['queue']++;
					}
					break;
				case 'save':	
					$this->saveDraft($msg);
					sendMail($this->sender, 'Message Received and Saved as a Draft', 
						"A message with the subject '" . $this->subj . "' was received and has been saved as a draft.");
					logEvent("A message with the subject '" . $this->subj ."' was received and and saved as a draft.");
					if (is_array($count)) $count['draft']++;
					break;
			}
		}		
	} 
}

?>