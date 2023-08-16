<?php

/**
 * Adds a meta box to the post editing screen
 */
function hrm_custom_meta() {
    add_meta_box(
      'hrm_meta',
      __( 'Job Listing', 'hrm-jobs' ),
      'hrm_meta_callback',
      'job'
    );
}
add_action( 'add_meta_boxes', 'hrm_custom_meta' );

/**
 * Outputs the content of the meta box
 */
function hrm_meta_callback( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'hrm_nonce' );
    $hrm_stored_meta = get_post_meta( $post->ID );
    ?>
    <div>
      <div class="meta-row">
          <div class="meta-th">
            <label for="job-id" class="hrm-row-title"><?php _e( 'Job ID', 'hrm-textdomain' )?></label>
          </div>
          <div class="meta-td">
            <input type="text" name="job-id" id="job-id" value="<?php if ( isset ( $hrm_stored_meta['job-id'] ) ) echo esc_attr( $hrm_stored_meta['job-id'][0] ); ?>" />
          </div>
      </div>
      <div class="meta-row">
          <div class="meta-th">
            <label for="date_listed" class="wpdt-row-title"><?php _e( 'Date Listed', 'hrm-textdomain' )?></label>
          </div>
          <div class="meta-td">
            <input type="text" size="10" class="wpdt-row-content datepicker" name="date_listed" id="date_listed" value="<?php if ( isset ( $hrm_stored_meta['date_listed'] ) ) echo esc_attr( $hrm_stored_meta['date_listed'][0] ); ?>" />
          </div>
      </div>
      <div class="meta-row">
          <div class="meta-th">
            <label for="application_deadline" class="wpdt-row-title"><?php _e( 'Application Deadline', 'hrm-textdomain' )?></label>
          </div>
          <div class="meta-td">
            <input type="text" size="10" class="wpdt-row-content datepicker" name="application_deadline" id="application_deadline" value="<?php if ( isset ( $hrm_stored_meta['application_deadline'] ) ) echo esc_attr( $hrm_stored_meta['application_deadline'][0] ); ?>" />
          </div>
      </div>
      <div class="meta-row">
          <div class="meta-th">
            <span>Principle Duties</span>
          </div>
          <div  class="meta-editor">
            <?php

              $content = get_post_meta( $post->ID, 'principle_duties', true );
              $editor_id = 'principle_duties';
              $settings = array(
                'textarea_rows' => 5,
              );

              wp_editor( $content, $editor_id, $settings );

            ?>
          </div>
        </div>
      <div class="meta-row">
        <div class="meta-th">
          <label for="minimum-requirements" class="wpdt-row-title"><?php _e( 'Minimum Requirements', 'hrm-textdomain' )?></label>
        </div>
        <div class="meta-td">
          <textarea name="minimum-requirements" class ="hrm-textarea" id="minimum-requirements"><?php if ( isset ( $hrm_stored_meta['minimum-requirements'] ) ) echo esc_attr( $hrm_stored_meta['minimum-requirements'][0] ); ?></textarea>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-th">
          <label for="preferred-requirements" class="wpdt-row-title"><?php _e( 'Preferred Requirements', 'hrm-textdomain' )?></label>
        </div>
        <div class="meta-td">
          <textarea name="preferred-requirements" class ="hrm-textarea" id="preferred-requirements"><?php if ( isset ( $hrm_stored_meta['preferred-requirements'] ) ) echo esc_attr( $hrm_stored_meta['preferred-requirements'][0] ); ?></textarea>
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-th">
          <label for="relocation-assistance" class="prfx-row-title"><?php _e( 'Relocation Assistance', 'hrm-textdomain' )?></label>
        </div>
        <div class="meta-td">
          <select name="relocation-assistance" id="relocation-assistance">
              <option value="select-yes" <?php if ( isset ( $hrm_stored_meta['relocation-assistance'] ) ) selected( $hrm_stored_meta['relocation-assistance'][0], 'select-yes' ); ?>><?php _e( 'Yes', 'prfx-textdomain' )?></option>';
              <option value="select-no" <?php if ( isset ( $hrm_stored_meta['relocation-assistance'] ) ) selected( $hrm_stored_meta['relocation-assistance'][0], 'select-no' ); ?>><?php _e( 'No', 'prfx-textdomain' )?></option>';
          </select>
        </div>
      </div>
    </div>

    <?php
}

/**
 * Saves the custom meta input
 */
function hrm_meta_save( $post_id ) {

    // Checks save status
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST[ 'hrm_nonce' ] ) && wp_verify_nonce( $_POST[ 'hrm_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }

    // Checks for input and sanitizes/saves if needed
    if( isset( $_POST[ 'job-id' ] ) ) {
        update_post_meta( $post_id, 'job-id', sanitize_text_field( $_POST[ 'job-id' ] ) );
    }

    if( isset( $_POST[ 'principle_duties' ] ) ) {
        update_post_meta( $post_id, 'principle_duties', sanitize_text_field( $_POST[ 'principle_duties' ] ) );
    }

    if( isset( $_POST[ 'minimum-requirements' ] ) ) {
        update_post_meta( $post_id, 'minimum-requirements', sanitize_text_field( $_POST[ 'minimum-requirements' ] ) );
    }

    if( isset( $_POST[ 'date_listed' ] ) ) {
        update_post_meta( $post_id, 'date_listed', sanitize_text_field( $_POST[ 'date_listed' ] ) );
    }

    if( isset( $_POST[ 'application_deadline' ] ) ) {
        update_post_meta( $post_id, 'application_deadline', sanitize_text_field( $_POST[ 'application_deadline' ] ) );
    }

    if( isset( $_POST[ 'preferred-requirements' ] ) ) {
        update_post_meta( $post_id, 'preferred-requirements', sanitize_text_field( $_POST[ 'preferred-requirements' ] ) );
    }

    if( isset( $_POST[ 'relocation-assistance' ] ) ) {
        update_post_meta( $post_id, 'relocation-assistance', sanitize_text_field( $_POST[ 'relocation-assistance' ] ) );
    }
}
add_action( 'save_post', 'hrm_meta_save' );

/**
 * Change Placeholder text in Default title field.
 */
function change_default_title( $title ){

    $screen = get_current_screen();

    if ( 'job' == $screen->post_type ){
        $title = "Enter Job Title Here";
    }

    return $title;
}

add_filter( 'enter_title_here', 'change_default_title' );

