<?php
$form_data = array(
	'field_name' => isset( $_POST['field_name'] ) ? sanitize_text_field($_POST['field_name']) : '',
	'merge_tag'  => isset( $_POST['merge_tag'] ) ? sanitize_text_field($_POST['merge_tag']) : '',
	'field_type' => isset( $_POST['field_type'] ) ? sanitize_text_field($_POST['field_type']) : '',
	'list_id'    => isset( $_POST['list_id'] ) ? sanitize_text_field($_POST['list_id']) : '',
);

// Grab our list handler.
$list_handler = yikes_get_mc_api_manager()->get_list_handler();

$available_merge_variables = $list_handler->get_merge_fields( $form_data['list_id'] );
if ( is_wp_error( $available_merge_variables ) ) {
	$error_logging = new Yikes_Inc_Easy_Mailchimp_Error_Logging();
	$error_logging->maybe_write_to_log(
		$available_merge_variables->get_error_code(),
		__( "Get Merge Variables", 'yikes-inc-easy-mailchimp-extender' ),
		__( "Add Field to Form", 'yikes-inc-easy-mailchimp-extender' )
	);
	?>
	<section class="draggable" id="error-container">
		<p>
			<span class="dashicons dashicons-no-alt"></span> <?php printf( __( 'Error: %s', 'yikes-inc-easy-mailchimp-extender' ), $available_merge_variables->get_error_code() ); ?>
		</p>
	</section>
	<?php
	return;
}

// find and return the location of this merge field in the array
$index = $this->findMCListIndex( $form_data['merge_tag'], $available_merge_variables['merge_fields'], 'tag' );

// store it and use it to pre-populate field data (only on initial add to form)
$merge_field_data = $available_merge_variables['merge_fields'][ $index ];
?>
<section class="draggable" id="<?php echo esc_attr( $form_data['field_name'] ); ?>">
	<!-- top -->
	<a href="#" class="expansion-section-title settings-sidebar">
		<span class="dashicons dashicons-plus yikes-mc-expansion-toggle" title="<?php _e( 'Expand Field' , 'yikes-inc-easy-mailchimp-extender' ); ?>"></span>
		<?php echo wp_kses_post( stripslashes( $form_data['field_name'] ) ); ?>
		<span class="field-type-text"><small><?php echo __( 'type' , 'yikes-inc-easy-mailchimp-extender' ) . ' : ' . esc_html( $form_data['field_type'] ); ?></small></span>
	</a>
	<!-- expansion section -->
	<div class="yikes-mc-settings-expansion-section">

		<!-- Single or Double Opt-in -->
		<p class="type-container form-field-container"><!-- necessary to prevent skipping on slideToggle(); -->
			<!-- store the label -->
			<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][label]" value="<?php echo esc_attr( htmlspecialchars( $form_data['field_name'] ) ); ?>" />
			<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][type]" value="<?php echo esc_attr( $form_data['field_type'] ); ?>" />
			<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][merge]" value="<?php echo $merge_field_data['tag']; ?>" />
			<input type="hidden" class="field-<?php echo $merge_field_data['tag']; ?>-position position-input" name="field[<?php echo $merge_field_data['tag']; ?>][position]" value="" />

			<?php if ( $form_data['field_type'] == 'radio' || $form_data['field_type'] == 'dropdown' ) { ?>
				<?php $choices = ( isset( $merge_field_data['options']['choices'] ) ) ? esc_attr( json_encode( $merge_field_data['options']['choices'] ) ) : ''; ?>
				<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][choices]" value='<?php echo $choices; ?>' />
			<?php } ?>

			<table class="form-table form-field-container">

					<!-- Merge Tag -->
					<tr valign="top">
						<td scope="row">
							<label for="merge-tag">
								<?php _e( 'Merge Tag' , 'yikes-inc-easy-mailchimp-extender' ); ?>
							</label>
						</td>
						<td>
							<input class="widefat merge-tag-text" type="text" readonly value="<?php echo $merge_field_data['tag']; ?>">
						</td>
					</tr>

			<?php switch( $form_data['field_type'] ) {

					default:
						break;

					case 'text':
					case 'number':
					case 'url':
					case 'email':
					case 'phone':
					case 'birthday':
					case 'zip':
			?>
					<!-- Placeholder -->
					<tr valign="top">
						<td scope="row">
							<label for="placeholder">
								<?php _e( 'Placeholder' , 'yikes-inc-easy-mailchimp-extender' ); ?>
							</label>
						</td>
						<td>
						<input type="text" class="widefat" name="field[<?php echo $merge_field_data['tag']; ?>][placeholder]" value="<?php echo isset( $merge_field_data['placeholder'] ) ? stripslashes( wp_strip_all_tags( $merge_field_data['placeholder'] ) ): '' ; ?>" />
							<p class="description"><small><?php _e( "Assign a placeholder value to this field.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
						</td>
					</tr>

			<?php
					/*
					*	Loop over field types and store necessary formats
					*	( date, birthday - dateformat ; phone - phoneformat )
					*/
					switch( $form_data['field_type'] ) {

						/* Store the date format, for properly rendering dates on the front end */
						case 'date':
							$date_format = isset( $merge_field_data['options']['dateformat'] ) ? $merge_field_data['options']['dateformat'] : 'MM/DD/YYYY';
							?>
							<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][date_format]" value="<?php echo strtolower( $date_format ); ?>" />
							<?php
						break;

						case 'birthday':
							$date_format = isset( $merge_field_data['options']['dateformat'] ) ? $merge_field_data['options']['dateformat'] : 'MM/DD';
							?>
							<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][date_format]" value="<?php echo strtolower( $date_format ); ?>" />
							<?php
						break;

						/* Store the phone format, for properly regex pattern */
						case 'phone':
							?>
							<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][phone_format]" value="<?php echo $merge_field_data['options']['phone_format']; ?>" />
							<?php
						break;
					}

					break;

					case 'address':
						?>
							<tr valign="top">
								<td scope="row">
									<label for="placeholder_<?php echo esc_attr( $field['merge'] ); ?>">
										<?php _e( 'Placeholder' , 'yikes-inc-easy-mailchimp-extender' ); ?>
									</label>
								</td>
								<td>
									<input type="checkbox" class="widefat" name="field[<?php echo $merge_field_data['tag']; ?>][placeholder]" value="1" />
									<span class="description"><small><?php _e( "Use placeholders for this field (these will be automatically filled in with field names).", 'yikes-inc-easy-mailchimp-extender' );?></small></span>
								</td>
							</tr>
						<?php
					break;

				}
			?>

				<!-- Default Value -->
				<?php switch( $form_data['field_type'] ) {

					default:
					case 'text':
						?>
						<tr valign="top">
							<td scope="row">
								<label for="placeholder">
									<?php _e( 'Default Value' , 'yikes-inc-easy-mailchimp-extender' ); ?>
								</label>
							</td>
							<td>
								<input type="text" class="widefat" name="field[<?php echo $merge_field_data['tag']; ?>][default]" <?php if( $form_data['field_type'] != 'url' ) { ?> value="<?php echo isset( $merge_field_data['default_value'] ) ? stripslashes( wp_strip_all_tags( $merge_field_data['default_value'] ) ) : ''; ?>" <?php } else { ?> value="<?php echo isset( $merge_field_data['default_value'] ) ? stripslashes( wp_strip_all_tags( esc_url_raw( $merge_field_data['default_value'] ) ) ) : ''; } ?>" />
								<p class="description"><small><?php _e( "Assign a default value to populate this field with on initial page load.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
								<?php
								switch ( $form_data['field_type'] ) {
									case 'text':
										?>
										<p><small class="pre-defined-tag-link"><a href="#TB_inline?width=600&height=550&inlineId=pre-defined-tag-container" class="thickbox" onclick="storeGlobalClicked( jQuery( this ) );"><?php _e( 'View Pre-Defined Tags' , 'yikes-inc-easy-mailchimp-extender' ); ?></a></small></p>
										<?php
									break;
								} ?>
							</td>
						</tr>
						<?php
						break;

						case 'radio':
						?>
							<tr valign="top">
								<td scope="row">
									<label for="placeholder">
										<?php _e( 'Default Selection' , 'yikes-inc-easy-mailchimp-extender' ); ?>
									</label>
								</td>
								<td>
									<?php $pre_selected = ! empty( $merge_field_data['default_choice'] ) ? $merge_field_data['default_choice'] : 'no-default'; ?>
									<label for="<?php echo $merge_field_data['tag'] . '-no-default'; ?>">
										<input id="<?php echo $merge_field_data['tag'] . '-no-default'; ?>"
										       type="radio"
										       name="field[<?php echo $merge_field_data['tag']; ?>][default_choice]"
										       value="no-default" <?php checked( $pre_selected, 'no-default' ); ?>
										>
										No Default&nbsp;
									</label>
									<?php
									$x = 0;
									foreach ( $merge_field_data['options']['choices'] as $choice => $value ) { ?>
										<label>
											<input type="radio" name="field[<?php echo $merge_field_data['tag']; ?>][default_choice]" value="<?php echo $x; ?>" <?php checked( $pre_selected, $choice ); ?>><?php echo $value; ?>
										</label>
										<?php $x++;
									} ?>
									<p class="description"><small><?php _e( "Select the option that should be selected by default.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
								</td>
							</tr>

							<?php
							break;

						case 'dropdown':
							?>
							<!-- Placeholder -->
							<tr valign="top">
								<td scope="row">
									<label for="placeholder">
										<?php _e( 'Default Value' , 'yikes-inc-easy-mailchimp-extender' ); ?>
									</label>
								</td>
								<td>
								<input type="text" id="placeholder_<?php echo esc_attr( $field['merge'] ); ?>" class="widefat" name="field[<?php echo $field['merge']; ?>][placeholder]" value="<?php echo isset( $field['placeholder'] ) ? $field['placeholder'] : '' ; ?>" />
									<p class="description"><small><?php _e( "Assign a default value to populate a placeholder for selection drop-down", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
								</td>
							</tr>

							<tr valign="top">
								<td scope="row">
									<label for="placeholder">
										<?php _e( 'Default Selection' , 'yikes-inc-easy-mailchimp-extender' ); ?>
									</label>
								</td>
								<td>
									<select type="default" name="field[<?php echo $merge_field_data['tag']; ?>][default_choice]">
										<?php $pre_selected = ! empty( $merge_field_data['default_choice'] ) ? $merge_field_data['default_choice'] : 'no-default'; ?>
										<option value="no-default" <?php selected( $pre_selected, $choice ); ?>>No Default</option>
										<?php foreach ( $merge_field_data['options']['choices'] as $choice => $value ) { ?>
											<option value="<?php echo $choice; ?>" <?php selected( $pre_selected, $choice ); ?>><?php echo stripslashes( $value ); ?></option>
										<?php } ?>
									</select>
									<p class="description"><small><?php _e( "Which option should be selected by default?", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
								</td>
							</tr>

						<?php
							break;

					} // end switch field type ?>

				<!-- Field Description -->
				<tr valign="top">
					<td scope="row">
						<label for="placeholder">
							<?php _e( 'Description' , 'yikes-inc-easy-mailchimp-extender' ); ?>
						</label>
					</td>
					<td>
						<textarea class="widefat field-description-input" name="field[<?php echo $merge_field_data['tag']; ?>][description]"></textarea>
						<p class="description"><small><?php _e( "Enter the description for the form field. This will be displayed to the user and provide some direction on how the field should be filled out or selected.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
					</td>
				</tr>
				<!-- Description Above Field -->
				<tr valign="top" class="yikes-checkbox-container">
					<td scope="row">
						<label for="description_above_<?php echo esc_attr( $merge_field_data['tag'] ); ?>">
							<?php _e( 'Description Above Field' , 'yikes-inc-easy-mailchimp-extender' ); ?>
						</label>
					</td>
					<td>
						<input type="checkbox" id="description_above_<?php echo esc_attr( $merge_field_data['tag'] ); ?>" class="widefat field-description-input" name="field[<?php echo $merge_field_data['tag']; ?>][description_above]" value="1" />
						<p class="description"><small><?php _e( "By default the description will appear undearneath the field. Check this box if you'd like the description to appear above the field.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
					</td>
				</tr>
				<!-- Additional Classes -->
				<tr valign="top">
					<td scope="row">
						<label for="placeholder">
							<?php _e( 'Additional Classes' , 'yikes-inc-easy-mailchimp-extender' ); ?>
						</label>
					</td>
					<td>
						<input type="text" class="widefat" name="field[<?php echo $merge_field_data['tag']; ?>][additional-classes]" value="<?php echo isset( $form_data['classes'] ) ? stripslashes( wp_strip_all_tags( $form_data['classes'] ) ) : '' ; ?>" />
						<p class="description"><small><?php printf( __( "Assign additional classes to this field. %s.", 'yikes-inc-easy-mailchimp-extender' ), '<a target="_blank" href="' . esc_url( 'https://yikesplugins.com/support/knowledge-base/bundled-css-classes/' ) . '">' . __( 'View bundled classes', 'yikes-inc-easy-mailchimp-extender' ) . '</a>' );?></small></p>
					</td>
				</tr>
					<!-- Required Toggle -->
					<tr valign="top" class="yikes-checkbox-container">
						<td scope="row">
							<label for="field-required">
								<?php _e( 'Field Required?' , 'yikes-inc-easy-mailchimp-extender' ); ?>
							</label>
						</td>
						<td>
							<input type="checkbox" class="widefat" value="1" name="field[<?php echo $merge_field_data['tag']; ?>][require]" <?php checked( $merge_field_data['required'], 1 ); ?> <?php if( $merge_field_data['tag'] == 'EMAIL' ) {  ?> disabled="disabled" checked="checked" title="<?php echo __( 'Email is a required field.' , 'yikes-inc-easy-mailchimp-extender' ); } ?>">
							<p class="description"><small><?php _e( "Require this field to be filled in before the form can be submitted.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
						</td>
					</tr>
					<!-- Visible Toggle -->
					<tr valign="top" class="yikes-checkbox-container">
						<td scope="row">
							<label for="hide-field">
								<?php _e( 'Hide Field' , 'yikes-inc-easy-mailchimp-extender' ); ?>
							</label>
						</td>
						<td>
							<input type="checkbox" class="widefat" value="1" name="field[<?php echo $merge_field_data['tag']; ?>][hide]" <?php checked( $merge_field_data['public'], '' ); ?> <?php if( $merge_field_data['tag'] == 'EMAIL' ) {  ?> disabled="disabled" title="<?php echo __( 'Cannot toggle email field visibility.' , 'yikes-inc-easy-mailchimp-extender' ); } ?>">
							<p class="description"><small><?php _e( "Hide this field from being displayed on the front end.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
						</td>
					</tr>
					<!-- Toggle Field Label Visibility -->
					<tr valign="top" class="yikes-checkbox-container">
						<td scope="row">
							<label for="placeholder">
								<?php _e( 'Hide Label' , 'yikes-inc-easy-mailchimp-extender' ); ?>
							</label>
						</td>
						<td>
							<input type="checkbox" name="field[<?php echo $merge_field_data['tag']; ?>][hide-label]" value="1" />
							<p class="description"><small><?php _e( "Toggle field label visibility.", 'yikes-inc-easy-mailchimp-extender' );?></small></p>
						</td>
					</tr>
					<!-- Display Phone/Date Formats back to the user -->
					<?php
						switch( $form_data['field_type'] ) {

							/* Store the phone format, for properly regex pattern */
							case 'phone':
							case 'birthday':
							case 'date':
							?>
								<tr valign="top">
									<td scope="row">
										<label for="placeholder">
										<?php
											switch( $form_data['field_type'] ) {
												default:
												case 'birthday':
													$type = __( 'Date Format' , 'yikes-inc-easy-mailchimp-extender' );
													$format = $merge_field_data['options']['date_format'];
													$format_name = 'date_format';
													break;

												case 'date':
													$type = __( 'Date Format' , 'yikes-inc-easy-mailchimp-extender' );
													$format = $merge_field_data['options']['date_format'];
													$format_name = 'date_format';
													break;

												case 'phone':
													$type = __( 'Phone Format' , 'yikes-inc-easy-mailchimp-extender' );
													$format = ( ( $merge_field_data['options']['phone_format'] == 'none' ) ? __( 'International', 'yikes-inc-easy-mailchimp-extender' ) : $merge_field_data['options']['phone_format'] );
													$format_name = 'phone_format';
													break;
											}
											echo $type;
										?>
										</label>
									</td>
									<td>
										<strong><?php echo $format; ?></strong>
										<input type="hidden" name="field[<?php echo $merge_field_data['tag']; ?>][<?php echo $format_name; ?>]" value="<?php echo $format; ?>" />
										<p class="description"><small>
											<?php printf( __( 'To change the %s please head over to <a href="%s" title="Mailchimp" target="_blank">Mailchimp</a>. If you alter the format, you should re-import this field.', 'yikes-inc-easy-mailchimp-extender' ), strtolower( $type ), esc_url( 'http://www.mailchimp.com' ) ); ?>
										</small></p>
									</td>
								</tr>
							<?php
							break;
							// others..
							default:
							break;
						}
					?>
										<!-- End Date/Phone Formats -->
					<!-- Toggle Buttons -->
					<tr valign="top">
						<td scope="row">
							&nbsp;
						</td>
						<td>
							<span class="toggle-container">
								<a href="#" class="hide-field"><?php _e( "Close" , 'yikes-inc-easy-mailchimp-extender' ); ?></a> |
								<a href="#" class="remove-field" alt="<?php echo $merge_field_data['tag']; ?>"><?php _e( "Remove Field" , 'yikes-inc-easy-mailchimp-extender' ); ?></a>
							</span>
						</td>
					</tr>
			</table>
		</p>

	</div>
</section>
