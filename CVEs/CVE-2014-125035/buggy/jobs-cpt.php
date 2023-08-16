<?php
function hrm_jobs_posttype() {
    
    $labels = array(
        'name'               => 'Jobs',
        'singular_name'      => 'Job',
        'menu_name'          => 'Jobs',
        'name_admin_bar'     => 'Jobs',
        'add_new'            => 'Add New Job',
        'add_new_item'       => 'Add New Job',
        'new_item'           => 'New Job',
        'edit_item'          => 'Edit Job',
        'view_item'          => 'View Job',
        'all_items'          => 'All Jobs',
        'search_items'       => 'Search Jobs',
        'parent_item_colon'  => 'Parent Jobs:',
        'not_found'          => 'No jobs found.',
        'not_found_in_trash' => 'No jobs found in Trash.',
    );
    
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-businessman',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'jobs' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title' )
    );
    register_post_type( 'job', $args );
}
add_action( 'init', 'hrm_jobs_posttype' );

// Flush rewrite rules to add "jobs" as a permalink slug
function hrm_jobs_my_rewrite_flush() {
    hrm_jobs_posttype();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'hrm_jobs_my_rewrite_flush' );

function hrm_location_tax() {
    
    $labels = array(
            'name'                       => _x( 'Locations', 'taxonomy general name' ),
            'singular_name'              => _x( 'Location', 'taxonomy singular name' ),
            'search_items'               => __( 'Search Locations' ),
            'popular_items'              => __( 'Popular Locations' ),
            'all_items'                  => __( 'All Locations' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Location' ),
            'update_item'                => __( 'Update Location' ),
            'add_new_item'               => __( 'Add New Location' ),
            'new_item_name'              => __( 'New Location Name' ),
            'separate_items_with_commas' => __( 'Separate locations with commas' ),
            'add_or_remove_items'        => __( 'Add or remove locations' ),
            'choose_from_most_used'      => __( 'Choose from the most used locations' ),
            'not_found'                  => __( 'No locations found.' ),
            'menu_name'                  => __( 'Locations' ),
        );

        $args = array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'location' ),
        );

        register_taxonomy( 'location', 'job', $args );
}
add_action( 'init', 'hrm_location_tax');