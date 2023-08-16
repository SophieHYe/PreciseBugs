<?php
/**
 * dynWid class
 *
 * @version $Id$
 * @copyright 2011 Jacco Drabbe
 */

	defined('ABSPATH') or die("No script kiddies please!");

	class dynWid {
		private $dbtable;
		public  $device;
		public  $dwoptions = array();
		public  $dynwid_list;
		public  $enabled;
		private $firstmessage = TRUE;
		public	$ip_address;
		public  $listmade = FALSE;
		public  $overrule_maintype = array();
		private $registered_sidebars;
		public  $registered_widget_controls;
		public  $registered_widgets;
		public  $removelist = array();
		public  $sidebars;
		public  $template;
		public  $url;
		public  $plugin_url;
		public  $useragent;
		public  $userrole;
		public  $whereami;
		private $wpdb;

		/**
		 * dynWid::__construct() Master class
		 *
		 */
		public function __construct() {
			global $wpdb;

			if ( is_user_logged_in() ) {
				$this->userrole = $GLOBALS['current_user']->roles;
			} else {
				$this->userrole = array('anonymous');
			}

			$this->registered_sidebars = $GLOBALS['wp_registered_sidebars'];
			$this->registered_widget_controls = &$GLOBALS['wp_registered_widget_controls'];
			$this->registered_widgets = &$GLOBALS['wp_registered_widgets'];
			$this->sidebars = wp_get_sidebars_widgets();
			$this->useragent = $this->getBrowser();
			$this->ip_address = $this->getIP();

			// DB init
			$this->wpdb = $wpdb;
			$this->dbtable = $this->wpdb->prefix . DW_DB_TABLE;
			$query = "SHOW TABLES LIKE '" . $this->dbtable . "'";
			$result = $this->wpdb->get_var($query);

			$this->enabled = ( is_null($result) ) ? FALSE : TRUE;
		}

		/**
		 * dynWid::__get() Overload get
		 *
		 * @param string $name
		 * @return mixed
		 */
		public function __get($name) {
			return $this->$name;
		}

		/**
		 * dynWid::__isset() Overload isset
		 *
		 * @param mixed $name
		 * @return boolean
		 */
		public function __isset($name) {
			if ( isset($this->$name) ) {
				return TRUE;
			}
			return FALSE;
		}

		/**
		 * dynWid::__set() Overload set
		 *
		 * @param string $name
		 * @param mixed $value
		 */
		public function __set($name, $value) {
			$this->$name = $value;
		}

		/**
		 * dynWid::addChilds() Save child options
		 *
		 * @param string $widget_id ID of the widget
		 * @param string $maintype Name of module
		 * @param string $default Default module setting
		 * @param array $act Parent options
		 * @param array $childs Options
		 */
		public function addChilds($widget_id, $maintype, $default, $act, $childs) {
			$child_act = array();
			foreach ( $childs as $opt ) {
				if ( in_array($opt, $act) ) {
					$childs_act[ ] = $opt;
				}
			}
			$this->addMultiOption($widget_id, $maintype, $default, $childs_act);
		}

		/**
		 * dynWid::addDate() Saves date options
		 *
		 * @param string $widget_id ID of the widget
		 * @param array $dates Dates
		 */
		public function addDate($widget_id, $dates) {
			$fields = array(
				'widget_id'		=> $widget_id,
				'maintype'		=> 'date',
				'name'			=> 'default',
				'value'			=> '0'
			);
			$this->wpdb->insert($this->dbtable, $fields);

			/*
			$query = "INSERT INTO " . $this->dbtable . "
                    (widget_id, maintype, name, value)
                  VALUES
                    ('" . $widget_id . "', 'date', 'default', '0')";
			$this->wpdb->query($query);
			*/

			foreach ( $dates as $name => $date ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> 'date',
					'name'			=> $name,
					'value'			=> $date
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
                    (widget_id, maintype, name, value)
                  VALUES
                    ('" . esc_sql($widget_id) . "', 'date', '" . esc_sql($name) . "', '" . esc_sql($date) . "')";
				$this->wpdb->query($query);
				*/
			}
		}

		/**
         * dynWid::addIPs() Saves IP options
		 *
		 * @param string $widget_id ID of the widget
		 * @param array $default Default setting
		 * @param string $ips IPs
		 */
		public function addIPs($widget_id, $default, $ips) {
			$value = serialize($ips);

			if ( $default == 'no' ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> 'ip',
					'name'			=> 'default',
					'value'			=> '0'
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'ip', 'default', '0')";
				$this->wpdb->query($query);
				*/
			}

			$fields = array(
				'widget_id'		=> $widget_id,
				'maintype'		=> 'ip',
				'name'			=> 'ip',
				'value'			=> $value
			);
			$this->wpdb->insert($this->dbtable, $fields);

			/*
			$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'ip', 'ip', '" . $value . "')";
			$this->wpdb->query($query);
			*/
		}

		public function addShortcode($widget_id, $default, $value, $match, $operator) {
			$value = array( 'value' => $value, 'match' => $match, 'operator' => $operator );
			$value = serialize($value);

			if ( $default == 'no' ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> 'shortcode',
					'name'			=> 'default',
					'value'			=> '0'
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'shortcode', 'default', '0')";
				$this->wpdb->query($query);
				*/
			}

			$fields = array(
				'widget_id'		=> $widget_id,
				'maintype'		=> 'shortcode',
				'name'			=> 'shortcode',
				'value'			=> $value
			);
			$this->wpdb->insert($this->dbtable, $fields);

			/*
			$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'shortcode', 'shortcode', '" . $value . "')";
			$this->wpdb->query($query);
			*/
		}

		/**
		 * dynWid::addUrls() Saves url options
		 *
		 * @param string $widget_id ID of the widget
		 * @param array $default Default setting
		 * @param string $urls URLs
		 */
		public function addUrls($widget_id, $default, $urls) {
			$value = serialize($urls);
			if ( $default == 'no' ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> 'url',
					'name'			=> 'default',
					'value'			=> '0'
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'url', 'default', '0')";
				$this->wpdb->query($query);
				*/
			}

			$fields = array(
				'widget_id'		=> $widget_id,
				'maintype'		=> 'url',
				'name'			=> 'url',
				'value'			=> $value
			);
			$this->wpdb->insert($this->dbtable, $fields);

			/*
			$query = "INSERT INTO " . $this->dbtable . "
										(widget_id, maintype, name, value)
									VALUES
										('" . esc_sql($widget_id) . "', 'url', 'url', '" . $value . "')";
			$this->wpdb->query($query);
			*/
		}

		/**
		 * dynWid::addMultiOption() Save multi (complex) options
		 *
		 * @param string $widget_id ID of the widget
		 * @param string $maintype Name of the module
		 * @param string $default Default setting
		 * @param array $act Options
		 */
		public function addMultiOption($widget_id, $maintype, $default, $act = array()) {
			$insert = TRUE;

			if ( $default == 'no' ) {
				$opt_default = '0';
				$opt_act = '1';
			} else {
				$opt_default = '1';
				$opt_act = '0';
			}

			// Check single-post or single-option coming from posts or tags screen to prevent database polution
			$types = array();
			$args = array(
								'public'   => TRUE,
								'_builtin' => FALSE
							);
			$post_types = get_post_types($args, 'objects', 'and');
			foreach ( array_keys($post_types) as $t ){
				$types[ ] = $t . '-post';
			}
			$post_types = array_merge( $types, array('single-post', 'single-tag') );

			if ( in_array($maintype, $post_types) ) {
				$query = "SELECT COUNT(1) AS total FROM " . $this->dbtable . " WHERE widget_id = %s AND maintype = %s AND name = %s";
				$query = $this->wpdb->prepare($query, $widget_id, $maintype, 'default');
				$count = $this->wpdb->get_var($query);
				if ( $count > 0 ) {
					$insert = FALSE;
				}
			}

			if ( $insert ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> $maintype,
					'name'			=> 'default',
					'value'			=> $opt_default
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
                      (widget_id, maintype, name, value)
                    VALUES
                      ('" . esc_sql($widget_id) . "', '" . esc_sql($maintype) . "', 'default', '" . esc_sql($opt_default) . "')";
				$this->wpdb->query($query);
				*/
			}
			foreach ( $act as $option ) {
				$fields = array(
					'widget_id'		=> $widget_id,
					'maintype'		=> $maintype,
					'name'			=> $option,
					'value'			=> $opt_act
				);
				$this->wpdb->insert($this->dbtable, $fields);

				/*
				$query = "INSERT INTO " . $this->dbtable . "
                      (widget_id, maintype, name, value)
                    VALUES
                      ('" . esc_sql($widget_id) . "', '" . esc_sql($maintype) . "', '" . esc_sql($option) . "', '" . esc_sql($opt_act) . "')";
				$this->wpdb->query($query);
				*/
			}
		}

		/**
		 * dynWid::addSingleOption() Save single (simple) options
		 *
		 * @param string $widget_id ID of the widget
		 * @param string $maintype Name of the module
		 * @param integer $value Default setting
		 */
		public function addSingleOption($widget_id, $maintype, $value = '0') {
			$fields = array(
				'widget_id'		=> $widget_id,
				'maintype'		=> $maintype,
				'value'			=> $value
			);
			$this->wpdb->insert($this->dbtable, $fields);

			/*
			$query = "INSERT INTO " . $this->dbtable . "
                    (widget_id, maintype, value)
                  VALUES
                    ('" . esc_sql($widget_id) . "', '" . esc_sql($maintype) . "', '" . esc_sql($value) . "')";
			$this->wpdb->query($query);
			*/
		}

		/**
		 * dynWid::checkWPhead() Checks for wp_head()
		 *
		 * @return integer
		 */
		public function checkWPhead() {
			$ct = current_theme_info();
			$headerfile = $ct->template_dir . '/header.php';
			if ( file_exists($headerfile) ) {
				$buffer = file_get_contents($headerfile);
				if ( strpos($buffer, 'wp_head()') ) {
					// wp_head() found
					return 1;
				} else {
					// wp_head() not found
					return 0;
				}
			} else {
				// wp_head() unable to determine
				return 2;
			}
		}

		/**
		 * dynWid::createList() Creates full list of options
		 *
		 */
		private function createList() {
			$this->dynwid_list = array();

			foreach ( $this->sidebars as $sidebar_id => $widgets ) {
				if ( count($widgets) > 0 ) {
					foreach ( $widgets as $widget_id ) {
						if ( $this->hasOptions($widget_id) ) {
							$this->dynwid_list[ ] = $widget_id;
						}
					} // END foreach widgets
				}
			} // END foreach sidebars
		}

		/**
		 * dynWid::deleteOption() Removes option
		 *
		 * @param string $widget_id ID of widget
		 * @param string $maintype Name of module
		 * @param string $name Name of option
		 */
		public function deleteOption($widget_id, $maintype, $name = '') {
			$query = "DELETE FROM " . $this->dbtable . " WHERE widget_id = %s AND maintype = %s";
			if (! empty($name) ) {
				$query .= " AND name = %s";
				$query = $this->wpdb->prepare($query, $widget_id, $maintype, $name);
			} else {
				$query = $this->wpdb->prepare($query, $widget_id, $maintype);
			}

			$this->wpdb->query($query);
		}

		/**
		 * dynWid::detectPage() Page detection
		 *
		 * @return string
		 */
		public function detectPage() {
			// First we register the Path URL
			$this->url = $_SERVER['REQUEST_URI'];

			if ( is_front_page() && get_option('show_on_front') == 'posts' ) {
				return 'front-page';
			} else if ( is_home() && get_option('show_on_front') == 'page' ) {
				return 'front-page';
			} else if ( is_attachment() ) {
				return 'attachment';					// must be before is_single(), otherwise detects as 'single'
			} else if ( is_single() ) {
				return 'single';
			} else if ( is_page() ) {
				return 'page';
			} else if ( is_author() ) {
				return 'author';
			} else if ( is_category() ) {
				return 'category';
			} else if ( is_tag() ) {
				return 'tag';
			} else if ( function_exists('is_post_type_archive') && is_post_type_archive() ) {
				return 'cp_archive';				// must be before is_archive(), otherwise detects as 'archive' in WP 3.1.0
			} else if ( function_exists('is_tax') && is_tax() ) {
				return 'tax_archive';
			} else if ( is_archive() && ! is_category() && ! is_author() && ! is_tag() ) {
				return 'archive';
			} else if ( function_exists('bbp_is_single_user') && (bbp_is_single_user() || bbp_is_single_user_edit()) ) {	// must be before is_404(), otherwise bbPress profile page is detected as 'e404'.
				return 'bbp_profile';
			} else if ( is_404() ) {
				return 'e404';
			} else if ( is_search() ) {
				return 'search';
			} else if ( function_exists('is_pod_page') && is_pod_page() ) {
				return 'pods';
			} else {
				return 'undef';
			}
		}

		/**
		 * dynWid::dump() Dump file creation
		 *
		 */
		public function dump() {
			echo "wp version: " . $GLOBALS['wp_version'] . "\n";
			echo "wp_head: " . $this->checkWPhead() . "\n";
			echo "dw version: " . DW_VERSION . "\n";
			echo "php version: " . PHP_VERSION . "\n";
			echo "\n";
			echo "front: " . get_option('show_on_front') . "\n";
			if ( get_option('show_on_front') == 'page' ) {
				echo "front page: " . get_option('page_on_front') . "\n";
				echo "posts page: " . get_option('page_for_posts') . "\n";
			}

			echo "\n";
			echo "list: \n";
			$list = array();
			$this->createList();
			foreach ( $this->dynwid_list as $widget_id ) {
				$list[$widget_id] = strip_tags($this->getName($widget_id));
			}
			print_r($list);

			echo "wp_registered_widgets: \n";
			print_r($this->registered_widgets);

			echo "options: \n";
			print_r( $this->getOpt('%', NULL) );

			echo "\n";
			echo serialize($this->getOpt('%', NULL));
		}

		/**
		 * dynWid::dumpOpt() Debug dump option
		 *
		 * @param object $opt
		 */
		public function dumpOpt($opt) {
			if ( DW_DEBUG && count($opt) > 0 ) {
				var_dump($opt);
			}
		}

		// replacement for createList() to make the worker faster
		/**
		 * dynWid::dwList() Option list creation
		 *
		 * @param string $whereami Page
		 */
		public function dwList($whereami) {
			$this->dynwid_list = array();
			if ( $whereami == 'home' ) {
				$whereami = 'page';
			}

			$query = "SELECT DISTINCT widget_id FROM " . $this->dbtable . "
                     WHERE  maintype LIKE '" . esc_sql($whereami) . "%'";

			if ( count($this->overrule_maintype) > 0 ) {
				$query .= " OR maintype IN ";
				$q = array();
				foreach ( $this->overrule_maintype as $omt ) {
					$q[ ] = "'" . $omt . "'";
				}
				$query .= "(" . implode(', ', $q) . ")";
			}

			$results = $this->wpdb->get_results($query);
			foreach ( $results as $myrow ) {
				$this->dynwid_list[ ] = $myrow->widget_id;
			}
		}

		/**
		 * dynWid::getBrowser() Browser detection
		 *
		 * @return string
		 */
		private function getBrowser() {
			global $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome;

			if ( $is_gecko ) {
				return 'gecko';
			} else if ( $is_IE ) {
				if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6.') !== FALSE ) {
					return 'msie6';
				} else {
					return 'msie';
				}
			} else if ( $is_opera ) {
				return 'opera';
			} else if ( $is_NS4 ) {
				return 'ns';
			} else if ( $is_safari ) {
				return 'safari';
			} else if ( $is_chrome ) {
				return 'chrome';
			} else {
				return 'undef';
			}
		}

		/**
		 * dynWid::getDWOpt() Gets SQL object used in DWOpts
		 *
		 * @param string $widget_id ID of widget
		 * @param string $maintype Name of module
		 * @return object
		 */
		public function getDWOpt($widget_id, $maintype) {
			if ( $maintype == 'home' ) {
				$maintype = 'page';
			}

			$query = "SELECT widget_id, maintype, name, value FROM " . $this->dbtable . "
                 WHERE widget_id LIKE '" . esc_sql($widget_id) . "'
                   AND maintype LIKE '" . esc_sql($maintype) . "%'
                 ORDER BY maintype, name";
			$results = new DWOpts($this->wpdb->get_results($query), $maintype);
			return $results;
		}

		private function getIP() {
			$ip = $_SERVER['REMOTE_ADDR'];
			$this->message( 'Raw IP: ' . $ip );

			return ( strstr($ip, '.') !== FALSE ) ? $ip : NULL;
		}

		/**
		 * dynWid::getModuleName() Full registration of the modules
		 *
		 */
		public function getModuleName() {
			$dwoptions = array();
			// I NEED PHP > 5.3!!

			DWModule::registerOption(DW_Archive::$option);
			DWModule::registerOption(DW_Attachment::$option);
			DWModule::registerOption(DW_Author::$option);
			DWModule::registerOption(DW_bbPress::$option);
			DWModule::registerOption(DW_BP::$option);
			DWModule::registerOption(DW_Browser::$option);
			DWModule::registerOption(DW_IP::$option);
			DWModule::registerOption(DW_Category::$option);
			DW_CustomPost::registerOption(NULL);
			DWModule::registerOption(DW_Date::$option);
			DWModule::registerOption(DW_Day::$option);
			DWModule::registerOption(DW_E404::$option);
			DWModule::registerOption(DW_Front_page::$option);
			DWModule::registerOption(DW_Device::$option);
			DWModule::registerOption(DW_Page::$option);
			DWModule::registerOption(DW_Pods::$option);
			DWModule::registerOption(DW_QT::$option);
			DWModule::registerOption(DW_Role::$option);
			DWModule::registerOption(DW_Search::$option);
			DWModule::registerOption(DW_Shortcode::$option);
			DWModule::registerOption(DW_Single::$option);
			DWModule::registerOption(DW_Tag::$option);
			DWModule::registerOption(DW_Tpl::$option);
			DWModule::registerOption(DW_URL::$option);
			DWModule::registerOption(DW_Week::$option);
			DWModule::registerOption(DW_WPSC::$option);
			DWModule::registerOption(DW_WPML::$option);
		}

		/**
		 * dynWid::getName() Gets the lookup name
		 *
		 * @return string
		 */
		public function getName($id, $type = 'W') {
			switch ( $type ) {
				case 'S':
					$lookup = $this->registered_sidebars;
					break;

				default:
					$lookup = $this->registered_widgets;
					// end default
			}

			if ( isset($lookup[$id]['name']) ) {
				$name = $lookup[$id]['name'];

				if ( $type == 'W' && isset($lookup[$id]['params'][0]['number']) ) {
					// Retrieve optional set title
					$number = $lookup[$id]['params'][0]['number'];
					$option_name = $lookup[$id]['callback'][0]->option_name;
					$option = get_option($option_name);
					if (! empty($option[$number]['title']) ) {
						$name .= ': <span class="in-widget-title">' . $option[$number]['title'] . '</span>';
					}
				}
			} else {
				$name = NULL;
			}

			return $name;
		}

		/**
		 * dynWid::getOpt() Get SQL object of Opt
		 *
		 * @param string $widget_id ID of the widget
		 * @param string $maintype Name of the module
		 * @param boolean $admin Admin page
		 * @return object
		 */
		public function getOpt($widget_id, $maintype, $admin = TRUE) {
			$opt = array();

			if ( $admin ) {
				$query = "SELECT widget_id, maintype, name, value FROM " . $this->dbtable . "
                  WHERE widget_id LIKE '" . esc_sql($widget_id) . "'
                    AND maintype LIKE '" . esc_sql($maintype) . "%'
                  ORDER BY maintype, name";

			} else {
				if ( $maintype == 'home' ) {
					$maintype = 'page';
				}
				$query = "SELECT widget_id, maintype, name, value FROM " . $this->dbtable . "
                  WHERE widget_id LIKE '" . esc_sql($widget_id) . "'
                    AND (maintype LIKE '" . esc_sql($maintype) . "%'";

				if ( count($this->overrule_maintype) > 0 ) {
					$query .= " OR maintype IN (";
					$q = array();
					foreach ( $this->overrule_maintype as $omt ) {
						$q[ ] = "'" . esc_sql($omt) . "'";
					}
					$query .= implode(', ', $q);
					$query .= ")";
				}

				$query .= ") ORDER BY maintype, name";
			}
			$this->message('Q: ' . $query);

			$results = $this->wpdb->get_results($query);
			return $results;
		}

		/**
		 * dynWid::getPostCatParents() Gets parents from post category
		 *
		 * @param array $post_category Categories
		 * @return array
		 */
		public function getPostCatParents($post_category) {
			// Getting all parents from the categories this post is in
			$parents = array();
			foreach ( $post_category as $id ) {
				$tp = $this->getTaxParents('category', array(), $id);
				// Now checking if the parent is already known
				foreach ( $tp as $p ) {
					if (! in_array($p, $parents) ) {
						$parents[ ] = $p;
					}
				}
			}

			return $parents;
		}

		/**
		 * dynWid::getParents() Gets parents from posts or pages
		 *
		 * @param string $type Type
		 * @param array $arr
		 * @param integer $id Child ID
		 * @return array
		 */
		public function getParents($type, $arr, $id) {
			if ( $type == 'page' ) {
				$obj = get_page($id);
			} else {
				$obj = get_post($id);
			}

			if ( $obj->post_parent > 0 ) {
				$arr[ ] = $obj->post_parent;
				$a = &$arr;
				$a = $this->getParents($type, $a, $obj->post_parent);
			}

			return $arr;
		}

		/**
		 * dynWid::getTaxParents() Get parents for Taxonomy
		 *
		 * @param string $tax_name Taxonomy name
		 * @param array $arr
		 * @param integer $id Child ID
		 * @return array
		 */
		public function getTaxParents($tax_name, $arr, $id) {
			$obj = get_term_by('id', $id, $tax_name);

			if ( $obj->parent > 0 ) {
				$arr[ ] = $obj->parent;
				$a = &$arr;
				$a = $this->getTaxParents($tax_name, $a, $obj->parent);
			}

			return $arr;
		}

		/**
		 * dynWid::getURLPrefix() Gets the optional prefix this blog is under
		 *
		 * @return string
		 */
		public function getURLPrefix() {
			$proto = ( is_ssl() ) ? 'https' : 'http';
			$name = ( isset($_SERVER['HTTP_HOST']) ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
			$server = $proto . '://' . $name;
			$prefix = substr( home_url('/'), strlen($server) );

			// Apply filters
			$prefix = apply_filters('dynwid_urlprefix', $prefix);

			if ( $prefix != '/' ) {
				$prefix = substr($prefix, 0, strlen($prefix) - 1 );
				return $prefix;
			}

			return '';
		}

		/**
		 * dynWid::hasOptions() Checks if a widget has options set
		 *
		 * @param string $widget_id ID of the widget
		 * @return boolean
		 */
		public function hasOptions($widget_id) {
			$query = "SELECT COUNT(1) AS total FROM " . $this->dbtable . "
                  WHERE widget_id = %s AND
                        maintype != %s";
			$query = $this->wpdb->prepare($query, $widget_id, 'individual');
			$count = $this->wpdb->get_var($query);

			if ( $count > 0 ) {
				return TRUE;
			}

			return FALSE;
		}

		/**
		 * dynWid::housekeeping() Housekeeping
		 *
		 */
		public function housekeeping() {
			$widgets = array_keys($this->registered_widgets);

			$query = "SELECT DISTINCT widget_id FROM " . $this->dbtable;
			$results = $this->wpdb->get_results($query);
			foreach ( $results as $myrow ) {
				if (! in_array($myrow->widget_id, $widgets) ) {
					$this->resetOptions($myrow->widget_id);
				}
			}
		}

		/**
		 * dynWid::IPinRange() IP address in range
		 *
		 * @param $ip string IP address
		 * @param $range string IP range
		 * @return boolean
		 */
		public function IPinRange($ip, $range) {
		 /* Copyright 2008: Paul Gregg <pgregg@pgregg.com>
		  * 10 January 2008
		  * Version: 1.2
		  *
		  * Source website: http://www.pgregg.com/projects/php/ip_in_range/
		  * Version 1.2
		  */

		  if ( strpos($range, '/') !== FALSE ) {
				// $range is in IP/NETMASK format
				list($range, $netmask) = explode('/', $range, 2);

				if ( strpos($netmask, '.') !== FALSE ) {
				  // $netmask is a 255.255.0.0 format
				  $netmask = str_replace('*', '0', $netmask);
				  $netmask_dec = ip2long($netmask);

				  return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
				} else {
				  // $netmask is a CIDR size block
				  // fix the range argument
				  $x = explode('.', $range);
				  while ( count($x) < 4 ) {
						$x[ ] = '0';
				  }

				  list( $a, $b, $c, $d ) = $x;
				  $range = sprintf( "%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d );
				  $range_dec = ip2long($range);
				  $ip_dec = ip2long($ip);

				  // Use math to create it
				  $wildcard_dec = pow( 2, (32-$netmask) ) - 1;
				  $netmask_dec = ~ $wildcard_dec;

				  return ( ($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec) );
				}
		  } else {
				// range might be 255.255.*.* or 1.2.3.0-1.2.3.255
				if ( strpos($range, '*') !== FALSE ) { // a.b.*.* format
				  // Just convert to A-B format by setting * to 0 for A and 255 for B
				  $lower = str_replace('*', '0', $range);
				  $upper = str_replace('*', '255', $range);
				  $range = "$lower-$upper";
				}

				if ( strpos($range, '-') !== FALSE ) { // A-B format
				  list( $lower, $upper ) = explode('-', $range, 2);
				  $lower_dec = (float) sprintf( "%u", ip2long($lower) );
				  $upper_dec = (float) sprintf( "%u", ip2long($upper) );
				  $ip_dec = (float) sprintf( "%u",ip2long($ip) );
				  return ( ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec) );
				}

				// last resort
				if ( substr($range, -3) != '/32' ) {
					$range .= '/32';
					return $this->IPinRange($ip, $range);
				}

				$this->message('Range argument is not in 1.2.3.4/24 or 1.2.3.4/255.255.255.0 format');
				return FALSE;
		  }

		}
		/**
		 * dynWid::loadModules() Full load of all modules
		 *
		 */
		public function loadModules() {
			$dh = opendir(DW_MODULES);
			while ( ($file = readdir($dh)) !== FALSE) {
				if ( $file != '.' && $file != '..' && substr(strrchr($file, '_'), 1) == 'module.php' ) {
					include_once(DW_MODULES . $file);
				}
			}
		}

		/**
		 * dynWid::log() Write text to debug log
		 *
		 */
		public function log($text) {
			if ( WP_DEBUG && DW_DEBUG ) {
				error_log($text);
			}
		}

		/**
		 * dynWid::message() Debug message
		 *
		 * @param string $text
		 */
		public function message($text) {
			if ( DW_DEBUG ) {
				if ( $this->firstmessage ) {
					echo "\n";
					$this->firstmessage = FALSE;
				}
				echo '<!-- ' . $text . ' //-->';
				echo "\n";
			}
		}

		/**
		 * dynWid::registerOverrulers() Overrule module regsitering
		 *
		 */
		public function registerOverrulers() {
			include_once(DW_MODULES . 'browser_module.php');
			include_once(DW_MODULES . 'date_module.php');
			include_once(DW_MODULES . 'day_module.php');
			include_once(DW_MODULES . 'week_module.php');
			include_once(DW_MODULES . 'role_module.php');
			include_once(DW_MODULES . 'shortcode_module.php');
			include_once(DW_MODULES . 'tpl_module.php');
			include_once(DW_MODULES . 'url_module.php');
			include_once(DW_MODULES . 'device_module.php');
			include_once(DW_MODULES . 'ip_module.php');

			DW_Browser::checkOverrule('DW_Browser');
			DW_Date::checkOverrule('DW_Date');
			DW_Day::checkOverrule('DW_Day');
			DW_Week::checkOverrule('DW_Week');
			DW_Role::checkOverrule('DW_Role');
			DW_Shortcode::checkOverrule('DW_Shortcode');
			DW_Tpl::checkOverrule('DW_Tpl');
			DW_URL::checkOverrule('DW_URL');
			DW_URL::checkOverrule('DW_Device');
			DW_URL::checkOverrule('DW_IP');

			// WPML Plugin Support
			include_once(DW_MODULES . 'wpml_module.php');
			DW_WPML::detectLanguage();

			// QT Plugin Support
			include_once(DW_MODULES . 'qt_module.php');
			DW_QT::detectLanguage();
		}

		/**
		 * dynWid::resetOptions() Full reset (remove) of options
		 *
		 * @param string $widget_id ID of the widget
		 */
		public function resetOptions($widget_id) {
			$query = "DELETE FROM " . $this->dbtable . " WHERE widget_id = %s";
			$query = $this->wpdb->prepare($query, $widget_id);
			$this->wpdb->query($query);
		}
	}
?>