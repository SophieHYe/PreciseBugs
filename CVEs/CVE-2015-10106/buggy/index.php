<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Marc Hoersken <info@marc-hoersken.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

unset($MCONF);

require_once('conf.php');
require_once($BACK_PATH.'init.php');
require_once($BACK_PATH.'template.php');
require_once(PATH_t3lib.'class.t3lib_scbase.php');

$LANG->includeLLFile('EXT:mh_httpbl/mod1/locallang.xml');
$LANG->includeLLFile('EXT:mh_httpbl/locallang_db.xml');
$BE_USER->modAccess($MCONF,1);
$TBE_TEMPLATE->backPath = $BACK_PATH;

/**
 * Module 'Honey Pot (http:BL)' for the 'mh_httpbl' extension.
 *
 * @author	Marc Hoersken <info@marc-hoersken.de>
 * @author  Myroslav Holyak <vbhjckfd@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_mhhttpbl
 */
class tx_mhhttpbl_module1 extends t3lib_SCbase {
	var $pageinfo;
	var $codes = array(
		0 => 'Search Engine',
		1 => 'Suspicious',
		2 => 'Harvester',
		3 => 'Suspicious &amp; Harvester',
		4 => 'Comment Spammer',
		5 => 'Suspicious &amp; Comment Spammer',
		6 => 'Harvester &amp; Comment Spammer',
		7 => 'Suspicious &amp; Harvester &amp; Comment Spammer'
	);

	/**
	 * Initializes the Module
	 * @return	void
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			'function' => Array (
				'1' => $LANG->getLL('function1'),
				'2' => $LANG->getLL('function2'),
				'3' => $LANG->getLL('function3'),
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{

				// Draw the header.
			$this->doc = t3lib_div::makeInstance('bigDoc');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
			$this->content.=$this->doc->divider(5);


			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('bigDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	function moduleContent()	{
		global $LANG, $BACK_PATH, $BE_USER, $TYPO3_DB;

		if (t3lib_div::_GP('block_ip')) {
			$this->MOD_SETTINGS['function'] = 1;
		}

		switch((string)$this->MOD_SETTINGS['function'])	{
			case 1:
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_mhhttpbl_blocklog', 'tstamp < '.(time()-(60*60*24*7)));

				if (t3lib_div::_GP('move_whitelist')) {
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_mhhttpbl_whitelist', array('cruser_id'=>$BE_USER->user['uid'], 'crdate'=>time(), 'tstamp'=>time(), 'whitelist_ip'=>t3lib_div::_GP('move_whitelist')));
					$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_mhhttpbl_blocklog', 'block_ip = '.$GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::_GP('move_whitelist'), 'tx_mhhttpbl_blocklog'));
				} else if (t3lib_div::_GP('delete')) {
					$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_mhhttpbl_blocklog', 'block_ip = '.$GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::_GP('delete'), 'tx_mhhttpbl_blocklog'));
				} else if (t3lib_div::_GP('clear_log')) {
					$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_mhhttpbl_blocklog', '');
				}

				$content = '
					<a href="?clear_log=true" title="'.$LANG->getLL('clear_log').'">'.$LANG->getLL('clear_log').'</a>
					<table border="0" cellspacing="0" cellpadding="0" class="typo3-dblist">
						<tr>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('time').' <a href="?sort=tstamp&amp;order=asc">&uArr;</a><a href="?sort=tstamp&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('tx_mhhttpbl_blocklog.block_ip').' <a href="?sort=block_ip&amp;order=asc">&uArr;</a><a href="?sort=block_ip&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('tx_mhhttpbl_blocklog.block_type').' <a href="?sort=block_type&amp;order=asc">&uArr;</a><a href="?sort=block_type&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('tx_mhhttpbl_blocklog.block_score').' <a href="?sort=block_score&amp;order=asc">&uArr;</a><a href="?sort=block_score&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" align="right" class="c-headLineTable"><b>'.$LANG->getLL('move_whitelist').' &amp; '.$LANG->getLL('delete').'</b></td>
						</tr>
				';

				if (t3lib_div::_GP('block_ip')) {
					$where = "block_ip = '". t3lib_div::_GP('block_ip') ."'";
				} else {
					$where = '';
				}
				if (t3lib_div::_GP('sort') && t3lib_div::_GP('order')) {
					$order = t3lib_div::_GP('sort').' '.strtoupper(t3lib_div::_GP('order'));
				} else {
					$order = 'tstamp DESC';
				}
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_mhhttpbl_blocklog', $where, '', $order);
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$content .= '
						<tr>
							<td valign="top">'.date('Y-m-d H:i:s', $row['tstamp']).'</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top">'.$row['block_ip'].'</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top">'.$this->codes[$row['block_type']].' ('.$row['block_type'].')</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top">'.$this->codes[$row['block_score']].' ('.$row['block_score'].')</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" align="right"><a href="?move_whitelist='.$row['block_ip'].'" title="'.$LANG->getLL('move_whitelist').'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/move_record.gif" alt="'.$LANG->getLL('move_whitelist').'" /></a><a href="?delete='.$row['block_ip'].'" title="'.$LANG->getLL('delete').'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/garbage.gif" alt="'.$LANG->getLL('delete').'" /></a></td>
						</tr>
						';
					}
				}

				$content .= '
					</table>
				';

				$this->content.=$this->doc->section($LANG->getLL('function1'),$content,0,1);
			break;
			case 2:
				if (t3lib_div::_GP('whitelist_add')) {
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_mhhttpbl_whitelist', array('cruser_id'=>$BE_USER->user['uid'], 'crdate'=>time(), 'tstamp'=>time(), 'whitelist_ip'=>implode('.', t3lib_div::_GP('whitelist_ip'))));
				} else if (t3lib_div::_GP('delete')) {
					$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_mhhttpbl_whitelist', 'uid = '.intval(t3lib_div::_GP('delete')));
				}

				$content = '
					<table border="0" cellspacing="2" cellpadding="0">
						<tr>
							<td><b>'.$LANG->getLL('tx_mhhttpbl_whitelist.whitelist_ip').'</b></td>
							<td></td>
							<td><input type="text" name="whitelist_ip[0]" maxlength="3" size="3" /></td>
							<td>.</td>
							<td><input type="text" name="whitelist_ip[1]" maxlength="3" size="3" /></td>
							<td>.</td>
							<td><input type="text" name="whitelist_ip[2]" maxlength="3" size="3" /></td>
							<td>.</td>
							<td><input type="text" name="whitelist_ip[3]" maxlength="3" size="3" /></td>
							<td></td>
							<td><input type="submit" name="whitelist_add" value="+" /></td>
						</tr>
					</table>
				';

				$content .= '
					<table border="0" cellspacing="0" cellpadding="0" class="typo3-dblist">
						<tr>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('time').' <a href="?sort=tstamp&amp;order=asc">&uArr;</a><a href="?sort=tstamp&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('tx_mhhttpbl_whitelist.whitelist_ip').' <a href="?sort=whitelist_ip&amp;order=asc">&uArr;</a><a href="?sort=whitelist_ip&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" align="right" class="c-headLineTable"><b>'.$LANG->getLL('delete').'</b></td>
						</tr>
				';

				if (t3lib_div::_GP('sort') && t3lib_div::_GP('order')) {
					$order = t3lib_div::_GP('sort').' '.strtoupper(t3lib_div::_GP('order'));
				} else {
					$order = 'tstamp DESC';
				}
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_mhhttpbl_whitelist', '', '', $order);
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$content .= '
						<tr>
							<td valign="top">'.date('Y-m-d H:i:s', $row['tstamp']).'</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top">'.$row['whitelist_ip'].'</td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" align="right"><a href="?delete='.$row['uid'].'" title="'.$LANG->getLL('delete').'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/garbage.gif" alt="'.$LANG->getLL('delete').'" /></a></td>
						</tr>
						';
					}
				}

				$content .= '
					</table>
				';

				$this->content.=$this->doc->section($LANG->getLL('function2'),$content,0,1);
			break;

			case 3:
				$content = '
					<table border="0" cellspacing="0" cellpadding="0" class="typo3-dblist">
						<tr>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('tx_mhhttpbl_blocklog.block_ip').' <a href="?sort=block_ip&amp;order=asc">&uArr;</a><a href="?sort=block_ip&amp;order=desc">&dArr;</a></b></td>
							<td class="c-headLineTable"><img src="clear.gif" width="10" height="1"></td>
							<td valign="top" class="c-headLineTable"><b>'.$LANG->getLL('count').' <a href="?sort=count&amp;order=asc">&uArr;</a><a href="?sort=count&amp;order=desc">&dArr;</a></b></td>
						</tr>
				';

				if (t3lib_div::_GP('sort') && t3lib_div::_GP('order')) {
					$order = t3lib_div::_GP('sort').' '.strtoupper(t3lib_div::_GP('order'));
				} else {
					$order = 'count DESC';
				}
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('block_ip, COUNT(block_ip) as count', 'tx_mhhttpbl_blocklog', '', 'block_ip', $order);
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$content .= '
						<tr>
							<td valign="top"><a title="'.$LANG->getLL('lookup').'" href="http://www.projecthoneypot.org/ip_'.$row['block_ip'].'" target="_blank">'.$row['block_ip'].'</a></td>
							<td><img src="clear.gif" width="10" height="1"></td>
							<td valign="top"><a title="'.$LANG->getLL('details').'" href="?block_ip='.$row['block_ip'].'">'.$row['count'].'</a></td>
						</tr>
						';
					}
				}
				$content .= '
					</table>
				';

				$this->content.=$this->doc->section($LANG->getLL('function3'),$content,0,1);
			break;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mh_httpbl/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mh_httpbl/mod1/index.php']);
}

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_mhhttpbl_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
