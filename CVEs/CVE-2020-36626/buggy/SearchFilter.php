<?php


namespace ModularContent;
use WP_Query;


/**
 * Class SearchFilter
 *
 * @package ModularContent
 *
 * Filters search queries to include content entered into panels
 */
class SearchFilter {
	private $query = NULL;

	public function __construct( WP_Query $query ) {
		$this->query = $query;
	}

	public function set_hooks() {
		$this->query->set( 'panel_search_filter', true );
		add_filter( 'posts_search', array( $this, 'add_post_content_filtered_to_search_sql' ), 1000, 2 );
	}

	/**
	 * @param string $sql
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	public function add_post_content_filtered_to_search_sql( $sql, $query ) {
		if ( $query->get( 'panel_search_filter' ) ) {
			global $wpdb;
			remove_filter( 'posts_search', array( $this, 'add_post_content_filtered_to_search_sql' ), 1000, 2 );
			
			$pattern = "#OR \($wpdb->posts.post_content LIKE '(.*?)'\)#";
			$sql = preg_replace_callback( $pattern, array( $this, 'replace_callback' ), $sql );
		}
		return $sql;
	}

	/**
	 * Duplicate the search SQL on the post_content field to also search the post_content_filtered field
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	private function replace_callback( $matches ) {
		global $wpdb;
		$post_content = $matches[0];
		$post_content_filtered = str_replace( $wpdb->posts.'.post_content', $wpdb->posts.'.post_content_filtered', $post_content );
		return $post_content.' '.$post_content_filtered;
	}
}