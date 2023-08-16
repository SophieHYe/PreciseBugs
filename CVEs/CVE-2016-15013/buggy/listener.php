<?php
/**
*
* @package Search Results
* @copyright (c) 2014 John Peskens (http://ForumHulp.com)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace forumhulp\searchresults\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	protected $config;
	protected $helper;
	protected $user;
	protected $request;
	protected $db;
	protected $log;
	protected $phpbb_root_path;
	protected $php_ext;
	protected $searchresults_table;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\user $user, \phpbb\request\request $request, \phpbb\db\driver\driver_interface $db, \phpbb\log\log $log, $phpbb_root_path, $php_ext, $searchresults_table)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->user = $user;
		$this->request = $request;
		$this->db = $db;
		$this->log = $log;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->searchresults_table = $searchresults_table;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.search_results_modify_search_title'	=> 'list_keywords',
			'core.acp_board_config_edit_add'			=> 'load_config_on_setup',
		);
	}

	public function list_keywords($event)
	{
		$keywords = $this->request->variable('keywords', '');
		$match		= array('#\sand\s#iu', '#\sor\s#iu', '#\snot\s#iu', '#(^|\s)\+#', '#(^|\s)-#', '#(^|\s)\|#');
		$replace	= array(' +', ' |', ' -', ' +', ' -', ' |');
		$keywords = preg_replace($match, $replace, $keywords);

		// Filter out as above
		$split_keywords = preg_replace("#[\n\r\t]+#", ' ', trim(htmlspecialchars_decode(strtolower($keywords))));

		// Split words
		$split_keywords = preg_replace('#([^\p{L}\p{N}\'*"()])#u', '$1$1', str_replace('\'\'', '\' \'', trim($split_keywords)));
		$matches = array();
		preg_match_all('#(?:[^\p{L}\p{N}*"()]|^)([+\-|]?(?:[\p{L}\p{N}*"()]+\'?)*[\p{L}\p{N}*"()])(?:[^\p{L}\p{N}*"()]|$)#u', $split_keywords, $matches);
		$this->split_words = $matches[1];

		foreach($this->split_words as $word)
		{
			if (strlen($word) <= $this->config['fulltext_native_min_chars'])
			{
				continue;
			}

			$sql = 'SELECT word_id FROM ' . SEARCH_WORDLIST_TABLE . ' WHERE word_text = "' . $word . '"';
			$resulttemp = $this->db->sql_query($sql);
			$found = ($rowtemp = $this->db->sql_fetchrow($resulttemp));

			$sql = 'SELECT search_keywords, last_time FROM ' . $this->searchresults_table . ' WHERE search_keywords = "' . $word . '"';
			$result = $this->db->sql_query($sql);
			$used = ($row = $this->db->sql_fetchrow($result));
			$this->db->sql_freeresult($result);

			$fields = array('last_time' => time(), 'in_post' => ($found) ? 1 : 0);
			if (!$used)
			{
				$fields += array(
					'search_keywords' => $word,
					'hits' => 1,
					'first_time' => time(),
				);
				$sql = 'INSERT INTO ' . $this->searchresults_table . ' ' . $this->db->sql_build_array('INSERT', $fields);
			} else
			{
				$sql = 'UPDATE ' . $this->searchresults_table . ' SET hits = hits + 1, ' . $this->db->sql_build_array('UPDATE', $fields) . '
						WHERE search_keywords = "' . $word . '"';
			}
			$this->db->sql_query($sql);
		}
	}

	public function search_result($event)
	{
		$sql = 'SELECT search_time, search_keywords, search_authors FROM ' . SEARCH_RESULTS_TABLE;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$sql = 'SELECT search_keywords, last_time FROM ' . $this->searchresults_table . ' WHERE search_keywords = \'' . $this->db->sql_escape($row['search_keywords']) . '\'';
			$resulttemp = $this->db->sql_query($sql);
			$found = ($rowtemp = $this->db->sql_fetchrow($resulttemp));
			$this->db->sql_freeresult($resulttemp);

			$fields = array('last_time' => $row['search_time']);
			if (!$found)
			{
				$fields += array(
					'search_keywords' => $row['search_keywords'],
					'hits' => 1,
					'first_time' => $row['search_time'],
				);
				$sql = 'INSERT INTO ' . $this->searchresults_table . ' ' . $this->db->sql_build_array('INSERT', $fields);
			} else if ($row['search_time'] != $rowtemp['last_time'])
			{
				$sql = 'UPDATE ' . $this->searchresults_table . ' SET hits = hits + 1, ' . $this->db->sql_build_array('UPDATE', $fields) . '
						WHERE search_keywords = \'' . $this->db->sql_escape($row['search_keywords']) . '\'';
			}
			$this->db->sql_query($sql);

		}
		if ($this->config['prune_searchresults'])
		{
			$sql = 'SELECT hits, last_time FROM ' . $this->searchresults_table . ' ORDER BY hits DESC LIMIT '. $this->config['prune_searchresults'] .', 1';
			$result = $this->db->sql_query($sql);

			$prune = $this->db->sql_fetchrow($result);
			if ($prune)
			{
				$sql = 'DELETE FROM ' . $this->searchresults_table . ' WHERE hits < ' . $prune['hits'] . ' AND last_time < ' . $prune['last_time'];
				$this->db->sql_query($sql);
			}
		}
	}

	public function load_config_on_setup($event)
	{
		if ($event['mode'] == 'features')
		{
			$display_vars = $event['display_vars'];

			$add_config_var['prune_searchresults'] =
				array(
					'lang' 		=> 'PRUNE_SEARCHRESULTS',
					'validate'	=> 'int',
					'type'		=> 'number:0:99',
					'explain'	=> true
				);
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $add_config_var, array('after' =>'allow_quick_reply'));
			$event['display_vars'] = array('title' => $display_vars['title'], 'vars' => $display_vars['vars']);
		}
	}
}
