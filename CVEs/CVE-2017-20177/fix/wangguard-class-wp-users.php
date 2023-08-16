<?php
/**
 * WangGuard Users Table class.
 *
 */
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
class WangGuard_Users_Table extends WP_List_Table {
	function WangGuard_Users_Table() {
		global $wp_version;
		$cur_wp_version = preg_replace('/-.*$/', '', $wp_version);
		$callConstructor = version_compare($cur_wp_version , '3.2.1' , ">=");
		if (!$callConstructor) {
			parent::WP_List_Table( array(
				'singular' => 'user',
				'plural'   => 'users'
			) );
		}
		else {
			parent::__construct( array(
				'singular' => 'user',
				'plural'   => 'users'
			) );
		}
	}
	function prepare_items() {
		global $role, $usersearch;
		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
		$usertype = isset( $_REQUEST['type'] ) ? $_REQUEST['type'] : '';
		$per_page = ( is_multisite() ) ? 'wangguard_page_wangguard_users_network_per_page' : 'wangguard_page_wangguard_users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );
		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'search' => $usersearch,
			'type' => $usertype,
			'fields' => 'all_with_meta'
		);
		$args['search'] = $args['search'];
		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];
		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];
		// Query the user IDs for this page
		$wp_user_search = new WangGuard_Users_Query( $args );
		$this->items = $wp_user_search->get_results();
		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $users_per_page,
		) );
	}
	function no_items() {
		_e( 'No users were found.' , 'wangguard' );
	}
	function get_views() {
		global $wpdb , $wangguard_g_splog_users_count;
		$url = 'admin.php?page=wangguard_users';
		$requestType = "";
		if (isset($_REQUEST['type']))
			$requestType = $_REQUEST['type'];
		$total = array();
		//Total users
		$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where 1=1");
		$total_users = $Count[0];
		$class = empty($requestType) ? ' class="current"' : '';
		$total['all'] = "<a href='$url'$class>" . sprintf( __( 'All Members <span class="count">(%s)</span>' , $total_users, 'wangguard' ), number_format_i18n( $total_users ) ) . '</a>';
		//Unchecked users
		$table_name = $wpdb->base_prefix . "wangguarduserstatus";
		$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where  (not EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID) OR EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( '', 'not-checked' )))");
		$uncheked_users = $wangguard_g_unchecked_users_count = $Count[0];
		$class = ($requestType == "uncheked") ? ' class="current"' : '';
		$total['uncheked'] = "<a href='" . add_query_arg( 'type', "uncheked", $url ) . "'$class>".sprintf( __( 'Unchecked Users <span class="count">(%s)</span>' , 'wangguard'), number_format_i18n( $uncheked_users ) )."</a>";
		//Legitimate users
		$table_name = $wpdb->base_prefix . "wangguarduserstatus";
		$wgLegitimateSQL = " AND EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( 'checked', 'force-checked', 'buyer' ))";
		if (wangguard_is_multisite())
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1 AND $wpdb->users.spam = 0" . $wgLegitimateSQL);
		elseif (defined( 'BP_VERSION' ))
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1" . $wgLegitimateSQL);
		else
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1" . $wgLegitimateSQL);
		$legitimate_users = $Count[0];
		$class = ($requestType == "l") ? ' class="current"' : '';
		$total['legitimate'] = "<a href='" . add_query_arg( 'type', "l", $url ) . "'$class>".sprintf( __( 'Verified Members <span class="count">(%s)</span>' , 'wangguard'), number_format_i18n( $legitimate_users ) )."</a>";
		//Whitelisted users
		$table_name = $wpdb->base_prefix . "wangguarduserstatus";

		$wgLegitimateSQL = " AND EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( 'whitelisted' ))";
		if (wangguard_is_multisite())
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1 AND $wpdb->users.spam = 0" . $wgLegitimateSQL);
		elseif (defined( 'BP_VERSION' ))
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1" . $wgLegitimateSQL);
		else
			$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status <> 1" . $wgLegitimateSQL);
		$legitimate_users = $Count[0];
		$class = ($requestType == "whitelisted") ? ' class="current"' : '';
		$total['whitelisted'] = "<a href='" . add_query_arg( 'type', "whitelisted", $url ) . "'$class>".sprintf( __( 'Whitelisted Users <span class="count">(%s)</span>' , 'wangguard'), number_format_i18n( $legitimate_users ) )."</a>";
		//Spam users, only BP or MS
		if (wangguard_is_multisite() || defined( 'BP_VERSION' )) {
			if (wangguard_is_multisite())
				$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status = 1 OR $wpdb->users.spam = 1");
			else
				$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where $wpdb->users.user_status = 1");
			$spam_users = $Count[0];
			$class = ($requestType == "spam") ? ' class="current"' : '';
			$total['spam'] = "<a href='" . add_query_arg( 'type', "spam", $url ) . "'$class>".sprintf( __( 'Spammers <span class="count">(%s)</span>' , 'wangguard'), number_format_i18n( $spam_users ) )."</a>";
		}
		//Sploggers users
		$table_name = $wpdb->base_prefix . "wangguarduserstatus";
		$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( 'reported', 'autorep' ))");
		$splog_users = $wangguard_g_splog_users_count = $Count[0];
		$class = ($requestType == "spl") ? ' class="current"' : '';
		$total['sploggers'] = "<a href='" . add_query_arg( 'type', "spl", $url ) . "'$class>".sprintf( __( 'Sploggers <span class="count">(%s)</span>' , 'wangguard'), number_format_i18n( $splog_users ) )."</a>";
		return $total;
	}
	function get_bulk_actions() {
		$actions = array();
		$actions['reportassplog'] = __( 'Report as Splogger', 'wangguard' );
		if (wangguard_is_multisite() || defined( 'BP_VERSION' )) {
			$actions['spam'] = _x( 'Mark as Spam', 'user' );
			$actions['notspam'] = _x( 'Not Spam', 'user' );
		}
		$actions['whitelist'] = __( 'Whitelist', 'wangguard' );
		$actions['delete'] = __( 'Delete Users', 'wangguard' );
		return $actions;
	}
	function extra_tablenav( $which ) {
		return;
	}
	function get_columns() {
		$c = array(
			'cb'				=> '<input type="checkbox" />',
			'info'				=> __( 'Info' , 'wangguard' ),
			'username'			=> __( 'Username' , 'wangguard' ),
			'email'				=> __( 'E-mail' , 'wangguard' ),
			'user_registered'	=> __( 'Signed up on' , 'wangguard' ),
			'from_ip'			=> __( 'User IP' , 'wangguard' ),
			'posts'				=> __( 'Posts' , 'wangguard' ),
			'wggcomments'			=> __( 'Comments' , 'wangguard' ),
			'blogs'				=> __( 'Blogs' , 'wangguard' ),
			'groups'			=> __( 'Admin Group' , 'wangguard' ),
		);
		return $c;
	}
	function get_sortable_columns() {
		$c = array(
			'username' => 'login',
			'email' => 'email',
			'from_ip' => 'from_ip',
			'user_registered' => 'user_registered',
		);
		return $c;
	}
	function display_rows() {
		// Query the post counts for this page
		$style = '';
		$post_counts = count_many_users_posts( array_keys( $this->items ) );
		foreach ( $this->items as $userid => $row_data ) {
			$style = ( 'alternate' == $style ) ? '' : 'alternate';
			echo "\n\t", $this->single_row( $row_data, $style , $post_counts[$userid] );
		}
	}
	/**
	 * Generate HTML for a single row on the users.php admin panel.
	 */
	function single_row( $row_data, $style = '' , $numposts) {
		global $wpdb , $wp_roles;
		$url = admin_url('admin.php?page=wangguard_users&order='.(isset($_REQUEST['order']) ? $_REQUEST['order'] : '').'&orderby='.(isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : ''));
		//USER
		$row_data->filter = 'display';
		$email = $row_data->user_email;
		$user_id = $row_data->ID;

		$checkbox = '';
		$actions = false;
		if (defined('BP_VERSION')) {
			add_thickbox();
			$user_editobj_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( stripslashes( $_SERVER['REQUEST_URI'] ) ), "user-edit.php?user_id=" . $user_id) );
			$editobj_link = esc_url(  bp_core_get_user_domain($user_id));
			// Set up the hover actions for this user
			$actions['edituser'] = "<a href='{$user_editobj_link}' target='_blank'>" . __( 'Edit user', 'wangguard' ) . "</a>";
			$actions['bpprofile'] = "<a href='{$editobj_link}?TB_iframe=true&width=900&height=550' class='thickbox'>" . __( 'BP Profile', 'wangguard' ) . "</a>";
			$report = "<strong><a target=\"_blank\" href=\"$editobj_link\">{$row_data->user_login}</a></strong><br />";
		} else {
			$editobj_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( stripslashes( $_SERVER['REQUEST_URI'] ) ), "user-edit.php?user_id=" . $user_id ) );
			$report = "<strong><a target=\"_blank\" href=\"$editobj_link\">{$row_data->user_login}</a></strong><br />";
		}
		// @TODO: some sort of check whether the user is editable or not?
		// Set up the checkbox ( because the user is editable, otherwise its empty )
		$checkbox = "<input type='checkbox' name='users[]' id='user_{$user_id}' value='{$user_id}' />";
		$role = reset( $row_data->roles );
		if (!empty($role))
			$role = $wp_roles->role_names[$role];

		// Prepare row classes
		$row_classes = array( $style );
		if ( @$row_data->spam || @$row_data->user_status ) {
			$row_classes[] = 'site-spammed';
		}
		/**
		 * Fires before generating HTML class statement for each row.
		 *
		 * @since WangGuard (1.6.3)
		 *
		 * @param array     $row_classes 	Classes to be applied to the row.
		 * @param object    $row_data 		WP_User object.
		 */
		$row_classes = apply_filters( 'wg_users_table_row_classes', $row_classes, $row_data );
		$row_classes_html = 'class="' . implode( ' ', $row_classes ) . '"';

		// Begin row output
		$r = '<tr id="user-' . $user_id . '" ' . $row_classes_html . '>';

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			switch ( $column_name ) {
				case 'cb':
					$cell_contents = $checkbox;
					break;
				case 'info':
					add_thickbox();
					if ( !is_multisite() ) {
						$url = esc_url( admin_url( add_query_arg( array( 'page' => 'wangguard_users_info' ), 'admin.php' ) ) );
					} else {
						$url = esc_url( network_admin_url( add_query_arg( array( 'page' => 'wangguard_users_info' ), 'admin.php' ) ) );
					}
					$arrayUrl = array ('userID' => $user_id, '?TB_iframe' => 'true', 'width' => '900', 'height' => '550' );
					$final_user_info_url = esc_url( add_query_arg(  $arrayUrl , $url ));
					$cell_contents = "<a class='thickbox' title='" . __( 'Info about','wangguard') . " {$row_data->first_name} {$row_data->last_name}' href='" . $final_user_info_url . "'><img class='alignnone size-full wp-image-2055' alt='Info about {$row_data->first_name} {$row_data->last_name}' src='" . plugins_url( 'img/info-wgg.png' , __FILE__ ) . "' width='15' height='15' /> " . __('User Info', 'wangguard' ) . "</a>";
					break;
				case 'username':
					$avatar = get_avatar( $user_id, 32 );
					$cell_contents = "$avatar $report <span style='font-size:11px'>{$role}" . ($actions ? $this->row_actions( $actions ) : "") . "</span>";
					break;
				case 'name':
					$cell_contents = "{$row_data->first_name} {$row_data->last_name}";
					break;
				case 'email':
					$cell_contents = "<a href='mailto:{$email}' title='" . esc_attr( sprintf( __( 'E-mail: %s' ), $email ) ) . "'>{$email}</a>";
					break;
				case 'user_registered':
					$cell_contents = "<span style='font-size:11px'>".date(get_option('date_format'), strtotime($row_data->user_registered)) . " " . date(get_option('time_format'), strtotime($row_data->user_registered))."</span>";
					break;
				case 'from_ip':
					$cell_contents = "<div class='wangguard-user-ip' data='{$row_data->user_ip}'><span class='wangguard-user-ip-bb'>";
					$cell_contents .= $row_data->user_ip;
					$cell_contents .= '</span>';
					if ($row_data->user_ip_is_proxy) {
						$cell_contents .= " <span class='wangguard_proxy'>" . __( 'proxy' , 'wangguard') .  "</span>";
					}
					$cell_contents .= "</div>";
					if (!empty($row_data->user_reported_proxy_ip)) {
						$cell_contents .= "<div class='wangguard-user-ip' data='{$row_data->user_reported_proxy_ip}'><span class='wangguard-user-ip-bb'>";
						$cell_contents .= $row_data->user_reported_proxy_ip;
						$cell_contents .= '</span>';
						$cell_contents .= " <span class='wangguard_proxy'>" . __( 'reported proxy' , 'wangguard') .  "</span>";
						$cell_contents .= "</div>";
					}
					break;
				case 'posts':
					if ( $numposts > 0 ) {
						$cell_contents = "<a target='_blank' href='edit.php?author={$user_id}' title='" . esc_attr__( 'View posts by this author' ) . "' class='edit'>";
						$cell_contents .= $numposts;
						$cell_contents .= '</a>';
					} else {
						$cell_contents = 0;
					}
					break;
				case 'blogs':
					add_thickbox();
					$cell_contents = '';
					if (function_exists("get_blogs_of_user")) {
						$blogs = @get_blogs_of_user( $user_id, true );
						if (is_array($blogs)) {
							foreach ( (array) $blogs as $key => $details ) {
								$cell_contents .= '- <a href="'. $details->siteurl .'?TB_iframe=true&width=900&height=550" class="thickbox" title="'. htmlentities($details->siteurl, 0, 'UTF-8') .'">'.$details->blogname.'</a><br/>';
							}
						}
					}
					break;
				case 'wggcomments':
					//add_thickbox();
					$args = array(
						'user_id' => $user_id, // use user_id
						'count' => true //return only the count
						);
					$comments = get_comments($args);
					$cell_contents = '<p>' . $comments . '</p>';

					/*$args = array(
						'user_id' => $user_id, // use user_id
						);
					$cell_contents = '';
					$comments = get_comments($args);
					foreach($comments as $comment) :
					$cell_contents .= '<p>- ' . $comment->comment_content . '</p>';
					endforeach;*/
					break;
				case 'groups':
					add_thickbox();
					$cell_contents = '';
					if ( ( defined( 'BP_VERSION' ) ) && ( class_exists('BP_Groups_Member') ) ) {
						$groups = BP_Groups_Member::get_is_admin_of( $user_id );
						if ( ! empty( $groups['groups'] ) ) {
							foreach ( $groups['groups'] as $group ) {
								$group_permalink = bp_get_group_permalink( $group );
								$cell_contents .= '- <a href="' . $group_permalink . '?TB_iframe=true&width=900&height=550" class="thickbox" title="'. htmlentities( $group_permalink, 0, 'UTF-8') .'">'. bp_get_group_name( $group ) .'</a><br/>';
							}
						}
					}
					break;
				case 'wgstatus':
					$cell_contents = wangguard_user_custom_columns( "" , "wangguardstatus" , $user_id );
					break;
			}
			/**
			 * Fires before outputting the generated HTML for each cell.
			 *
			 * This is a variable hook, depending on which cell is being generated.
			 * The potential hooks are:
			 * wg_users_table_cb_cell, wg_users_table_info_cell, wg_users_table_username_cell
			 * wg_users_table_name_cell, wg_users_table_email_cell
			 * wg_users_table_user_registered_cell, wg_users_table_from_ip_cell,
			 * wg_users_table_posts_cell, wg_users_table_blogs_cell,
			 * wg_users_table_groups_cell, wg_users_table_wgstatus_cell
			 *
			 * @since WangGuard (1.6.3)
			 *
			 * @param string    $cell_contents 	Generated HTML for the cell.
			 * @param object    $row_data 		WP_User object.
  			 * @param string    $column_name 	Name of column being generated.
			 */
			$cell_contents = apply_filters( 'wg_users_table_' . $column_name . '_cell', $cell_contents, $row_data, $column_name );

			if ( $column_name == 'cb' ) {
				$r .= '<th scope="row" class="check-column">' . $cell_contents . '</th>';
			} else if ( $column_name == 'info' ) {
				$r .= '<td  width="25">' . $cell_contents . '</td>';
			} else {
				// Prepare cell classes
				$classes = array( $column_name, 'column-' . $column_name );
				if ( ( $column_name == 'posts' ) || ( $column_name == 'wggcomments' ) ) {
					$classes[] = 'num';
				}
				/**
				 * Fires before generating HTML class statement for each cell.
				 *
				 * @since WangGuard (1.6.3)
				 *
				 * @param array     $classes 				Classes to be applied to the cell.
				 * @param string    $column_name 			Name of column being generated.
				 * @param object    $row_data 				WP_User object.
				 */
				$classes = apply_filters( 'wg_users_table_cell_classes', $classes, $column_name, $row_data );
				$classes_html = 'class="' . implode( ' ', $classes ) . '"';
				$r .= "<td {$classes_html}>" . $cell_contents . '</td>';
			}
		}
		$r .= '</tr>';
		return $r;
	}
}
class WangGuard_Users_Query {
	/**
	 * List of found user ids
	 */
	var $results;
	/**
	 * Total number of found users for the current query
	 */
	var $total_users = 0;
	// SQL clauses
	var $query_fields_u;
	var $query_from_u;
	var $query_where_u;
	var $query_orderby;
	var $query_limit;
	/**
	 * PHP4 constructor
	 */
	function WangGuard_Users_Query( $query = null ) {
		$this->__construct( $query );
	}
	/**
	 * PHP5 constructor
	 * @return WangGuard_Users_Query
	 */
	function __construct( $query = null ) {
		if ( !empty( $query ) ) {
			$this->query_vars = wp_parse_args( $query, array(
				'orderby' => 'user_registered',
				'order' => 'DESC',
				'search' => '',
				'offset' => '',
				'number' => '',
				'type' => '',
				'count_total' => true
			) );
			$this->prepare_query();
			$this->query();
		}
	}
	/**
	 * Prepare the query variables
	 */
	function prepare_query() {
		global $wpdb;
		$qv = &$this->query_vars;
		$tableUserStatus = $wpdb->base_prefix . "wangguarduserstatus";
		$this->query_fields_u = "$wpdb->users.ID , $wpdb->users.user_login , $tableUserStatus.user_status, $tableUserStatus.user_ip as status_user_ip, $tableUserStatus.user_proxy_ip as status_user_proxy_ip";
		$this->query_from_u = "FROM $wpdb->users LEFT JOIN $tableUserStatus ON $wpdb->users.ID = $tableUserStatus.ID";
		//search
		$this->query_where_u = '';
		if (!empty($qv['search'])) {
			if (empty($this->query_where_u))
				$this->query_where_u = " WHERE ";
			else
				$this->query_where_u .= " AND ";
			$this->query_where_u .= "($wpdb->users.user_login LIKE '%".like_escape($qv['search'])."%' OR $wpdb->users.user_nicename LIKE '%".like_escape($qv['search'])."%' OR $wpdb->users.user_email LIKE '%".like_escape($qv['search'])."%')";
		}
		switch ($qv['type']) {
			case 'l':
				//Legitimate users filter
				if (empty($this->query_where_u))
					$this->query_where_u = " WHERE ";
				else
					$this->query_where_u .= " AND ";
				//Legitimate users
				$wgLegitimateSQL = " $tableUserStatus.user_status IN ( 'checked', 'force-checked' )";
				if (wangguard_is_multisite())
					$wgLegitimateSQL = " $wpdb->users.user_status <> 1 AND $wpdb->users.spam = 0 AND " . $wgLegitimateSQL;
				elseif (defined( 'BP_VERSION' ))
					$wgLegitimateSQL = " $wpdb->users.user_status <> 1 AND " . $wgLegitimateSQL;
				else
					$wgLegitimateSQL = " $wpdb->users.user_status <> 1 AND " . $wgLegitimateSQL;
				$this->query_where_u .= $wgLegitimateSQL;
				break;
			case 'spam':
				//Spam users filter
				if (!wangguard_is_multisite() && !defined('BP_VERSION'))
					break;
				if (empty($this->query_where_u))
					$this->query_where_u = " WHERE ";
				else
					$this->query_where_u .= " AND ";
				if (wangguard_is_multisite())
					$wgLegitimateSQL = " $wpdb->users.user_status = 1 OR $wpdb->users.spam = 1";
				else
					$wgLegitimateSQL = " $wpdb->users.user_status = 1";
				$this->query_where_u .= $wgLegitimateSQL;
				break;
			case 'spl':
				//Spoggers users filter
				if (empty($this->query_where_u))
					$this->query_where_u = " WHERE ";
				else
					$this->query_where_u .= " AND ";
				$wgLegitimateSQL = " $tableUserStatus.user_status IN ( 'reported', 'autorep' )";
				$this->query_where_u .= $wgLegitimateSQL;
				break;
			case 'uncheked':
				//Unchecked users filter
				if (empty($this->query_where_u))
					$this->query_where_u = " WHERE ";
				else
					$this->query_where_u .= " AND ";
				$wgLegitimateSQL = "(not EXISTS (select user_status from $tableUserStatus where $tableUserStatus.ID = {$wpdb->users}.ID) OR EXISTS (select user_status from $tableUserStatus where $tableUserStatus.ID = {$wpdb->users}.ID and $tableUserStatus.user_status IN ( '', 'not-checked' )))";
				$this->query_where_u .= $wgLegitimateSQL;
				break;
			case 'whitelisted':
				//Spoggers users filter
				if (empty($this->query_where_u))
					$this->query_where_u = " WHERE ";
				else
					$this->query_where_u .= " AND ";
				$wgLegitimateSQL = " $tableUserStatus.user_status IN ( 'whitelisted' )";
				$this->query_where_u .= $wgLegitimateSQL;
				break;
		}
		// sorting
		switch ($qv['orderby']) {
			case "email":
				$orderby = "$wpdb->users.user_email";
				break;
			case "user_registered":
				$orderby = "$wpdb->users.user_registered";
				break;
			case "from_ip":
				$orderby = "case when $tableUserStatus.user_proxy_ip = '' then $tableUserStatus.user_ip else $tableUserStatus.user_proxy_ip end";
				break;
			case "login":
			default:
				$orderby = "$wpdb->users.user_login";
				break;
		}
		$qv['order'] = strtoupper( $qv['order'] );
		if ( 'ASC' == $qv['order'] )
			$order = 'ASC';
		else
			$order = 'DESC';
		$this->query_orderby = "ORDER BY $orderby $order";
		// limit
		if ( $qv['number'] ) {
			if ( $qv['offset'] )
				$this->query_limit = $wpdb->prepare("LIMIT %d, %d", $qv['offset'], $qv['number']);
			else
				$this->query_limit = $wpdb->prepare("LIMIT %d", $qv['number']);
		}
		//_parse_meta_query( $qv );
	}
	/**
	 * Execute the query, with the current variables
	 */
	function query() {
		global $wpdb;
		$this->results = $wpdb->get_results("SELECT {$this->query_fields_u} {$this->query_from_u} {$this->query_where_u} {$this->query_orderby} {$this->query_limit}");
		//echo("SELECT {$this->query_fields_u} {$this->query_from_u} {$this->query_where_u} {$this->query_orderby} {$this->query_limit}");
		if ( $this->query_vars['count_total'] ) {
			$this->total_users = $wpdb->get_var("SELECT COUNT(*) {$this->query_from_u} {$this->query_where_u}");
		}
		if ( !$this->results )
			return;
		$r = array();
		foreach ( $this->results as $userrow ) {
			$userid = $userrow->ID;
			$r[ $userid ] = new WP_User( $userid );
			if ($_SERVER['SERVER_ADDR'] == $userrow->status_user_ip) {
				//server is behind an nginx/other proxy, grab the proxy address
				$r[ $userid ]->user_ip = !empty($userrow->status_user_proxy_ip) ? $userrow->status_user_proxy_ip : $userrow->status_user_ip;
				$r[ $userid ]->user_reported_proxy_ip = '';
				$r[ $userid ]->user_ip_is_proxy = !empty($userrow->status_user_proxy_ip);
			}
			else {
				//disply stored client IP addr, report proxy addr detected, just for admin info
				$r[ $userid ]->user_ip = $userrow->status_user_ip;
				$r[ $userid ]->user_reported_proxy_ip = $userrow->status_user_proxy_ip;
				$r[ $userid ]->user_ip_is_proxy = false;
			}
			$r[ $userid ]->user_row = $userrow;
		}
		$this->results = $r;
	}
	function get_results() {
		return $this->results;
	}
	function get_total() {
		return $this->total_users;
	}
}
?>
