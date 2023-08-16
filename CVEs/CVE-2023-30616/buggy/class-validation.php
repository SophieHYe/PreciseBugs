<?php
namespace epiphyt\Form_Block\form_data;

use epiphyt\Form_Block\Form_Block;

/**
 * Form data class.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Form_Block
 */
final class Validation {
	/**
	 * @var		\epiphyt\Form_Block\form_data\Validation
	 */
	public static $instance;
	
	/**
	 * Validate form fields by allowed names.
	 *
	 * @param	string	$name The field name
	 * @param	array	$form_data The form data
	 */
	private function by_allowed_names( string $name, array $form_data ): void {
		$allowed_names = $this->get_allowed_names( $form_data );
		$name = preg_replace( '/-\d+$/', '', $name );
		
		if ( in_array( $name, $allowed_names, true ) ) {
			return;
		}
		
		wp_send_json_error( [
			'message' => sprintf(
				/* translators: field title */
				esc_html__( 'The following field is not allowed: %s', 'form-block' ),
				esc_html( Data::get_instance()->get_field_title_by_name( $name, $form_data['fields'] ) )
			),
		] );
	}
	
	/**
	 * Validate form fields by field data.
	 * 
	 * @param	string	$form_id The form ID
	 * @param	array	$fields Field data from request
	 * @return	array List of validation errors
	 */
	private function by_field_data( string $form_id, array $fields ): array {
		$form_data = Data::get_instance()->get( $form_id );
		$errors = $this->get_errors( $fields, $form_data );
		
		/**
		 * Filter the field data errors.
		 * 
		 * @param	array	$errors Current detected errors
		 * @param	array	$form_data Current form data to validate
		 * @param	array	$fields Field data from request
		 * @param	string	$form_id Current form ID
		 */
		$errors = apply_filters( 'form_block_field_data_errors', $errors, $form_data, $fields, $form_id );
		
		return $errors;
	}
	
	/**
	 * Validate a field value by its field attributes.
	 * 
	 * @param	mixed	$value The field value
	 * @param	array	$attributes Form field attributes
	 * @return	array List of validation errors
	 */
	private function by_attributes( $value, array $attributes ): array {
		$errors = [];
		
		foreach ( $attributes as $attribute => $attribute_value ) {
			switch ( $attribute ) {
				case 'block_type':
					switch ( $attribute_value ) {
						case 'textarea':
							$validated = sanitize_textarea_field( $value );
							break;
						default:
							$validated = sanitize_text_field( $value );
							break;
					}
					
					if ( $value !== $validated ) {
						$errors[] = [
							'message' => __( 'The entered value is invalid.', 'form-block' ),
							'type' => $attribute,
						];
					}
					break;
				case 'disabled':
				case 'readonly':
					if (
						! empty( $attributes['value'] ) && $attributes['value'] !== $value
						|| empty( $attributes['value'] ) && ! empty( $value )
					) {
						$errors[] = [
							'message' => __( 'The value must not change.', 'form-block' ),
							'type' => $attribute,
						];
					}
					break;
			}
		}
		
		/**
		 * Filter the validation by field attributes.
		 * 
		 * @param	array	$errors Current error list
		 * @param	mixed	$value The field value
		 * @param	array	$attributes Form field attributes
		 */
		$errors = apply_filters( 'form_block_field_attributes_validation', $errors, $value, $attributes );
		
		return $errors;
	}
	
	/**
	 * Get all allowed name attributes without their unique -\d+ part.
	 *
	 * @param	array	$form_data Current form data to validate
	 * @return	array List of allowed name attributes
	 */
	private function get_allowed_names( array $form_data ): array {
		Form_Block::get_instance()->reset_block_name_attributes();
		
		$allowed_names = [
			'_form_id',
			'_town',
			'action',
		];
		
		foreach ( $form_data['fields'] as $field ) {
			$field_name = Form_Block::get_instance()->get_block_name_attribute( $field );
			$allowed_names[] = preg_replace( '/-\d+$/', '', $field_name );
		}
		
		return $allowed_names;
	}
	
	/**
	 * Validate all POST fields.
	 * 
	 * @return	array The validated fields
	 */
	public function fields(): array {
		$form_data = get_option( 'form_block_data_' . Data::get_instance()->get_form_id(), [] );
		$validated = [];
		
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		foreach ( $_POST as $key => $value ) {
			// sanitize_key() but with support for uppercase
			$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $key ) );
			
			$this->by_allowed_names( $key, $form_data );
			
			// iterate through an array to sanitize its fields
			if ( is_array( $value ) ) {
				foreach ( $value as $item_key => &$item ) {
					// if it's not a string, die with an error message
					if ( ! is_string( $item ) ) {
						wp_send_json_error( [
							'message' => sprintf(
								/* translators: 1: the value name, 2: the field name */
								esc_html__( 'Wrong item format of value %1$s in field %2$s.', 'form-block' ),
								esc_html( $item_key ),
								esc_html( Data::get_instance()->get_field_title_by_name( $key, $form_data['fields'] ) )
							),
						] );
					}
					
					$item = sanitize_textarea_field( wp_unslash( $item ) );
				}
			}
			else {
				// if it's not a string, die with an error message
				if ( ! is_string( $value ) ) {
					wp_send_json_error( [
						'message' => sprintf(
							/* translators: the field name */
							esc_html__( 'Wrong item format in field %s.', 'form-block' ),
							esc_html( Data::get_instance()->get_field_title_by_name( $key, $form_data['fields'] ) )
						),
					] );
				}
				
				$value = sanitize_textarea_field( wp_unslash( $value ) );
			}
			
			$validated[ $key ] = $value;
		}
		// phpcs:enable
		
		unset( $validated['_form_id'], $validated['action'], $validated['_town'] );
		
		// remove empty fields
		foreach ( $validated as $key => $value ) {
			if ( ! empty( $value ) ) {
				continue;
			}
			
			unset( $validated[ $key ] );
		}
		
		/**
		 * Filter the validated fields.
		 * 
		 * @param	array	$validated The validated fields
		 * @param	string	$form_id The form ID
		 * @param	array	$form_data The form data
		 */
		$validated = apply_filters( 'form_block_validated_fields', $validated, Data::get_instance()->get_form_id(), $form_data );
		
		$required_fields = Data::get_instance()->get_required_fields( Data::get_instance()->get_form_id() );
		
		// check all required fields
		$missing_fields = [];
		
		// iterate through all required
		foreach ( $required_fields as $field_name ) {
			// check if a field with this identifier is empty
			// and if it's not a file upload
			if (
				(
					empty( $_FILES[ $field_name ]['tmp_name'] )
					|| is_array( $_FILES[ $field_name ]['tmp_name'] ) && empty( array_filter( $_FILES[ $field_name ]['tmp_name'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				)
				&& empty( $validated[ $field_name ] )
			) {
				$missing_fields[] = Data::get_instance()->get_field_title_by_name( $field_name, $form_data['fields'] );
			}
		}
		
		// output error if there are missing fields
		if ( ! empty( $missing_fields ) ) {
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: missing fields */
					esc_html( _n( 'The following field is missing: %s', 'The following fields are missing: %s', count( $missing_fields ), 'form-block' ) ),
					esc_html( implode( ', ', $missing_fields ) )
				),
			] );
		}
		
		$field_data_errors = $this->by_field_data( Data::get_instance()->get_form_id(), $validated );
		
		if ( ! empty( $field_data_errors ) ) {
			$message = '';
			
			foreach ( $field_data_errors as $field_errors ) {
				$message .= esc_html( $field_errors['field_title'] ) . ': ';
				
				foreach ( $field_errors['errors'] as $error ) {
					$message .= esc_html( $error['message'] );
				}
				
				$message .= PHP_EOL;
			}
			
			wp_send_json_error( [
				'message' => $message,
			] );
		}
		
		return $validated;
	}
	
	/**
	 * Validate all files.
	 * 
	 * @return	array The validated files
	 */
	public function files(): array {
		$form_data = get_option( 'form_block_data_' . Data::get_instance()->get_form_id(), [] );
		$validated = [];
		
		if ( empty( $_FILES ) ) {
			return $validated;
		}
		
		$filesize = 0;
		$maximum_file_size = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$maximum_post_size = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		$maximum_upload_size = max( $maximum_file_size, $maximum_post_size );
		
		if ( isset( $_SERVER['CONTENT_LENGTH'] ) || isset( $_SERVER['HTTP_CONTENT_LENGTH'] ) ) {
			$content_length = (int) sanitize_text_field( wp_unslash( $_SERVER['CONTENT_LENGTH'] ?? $_SERVER['HTTP_CONTENT_LENGTH'] ?? $maximum_upload_size ) );
			
			if ( $content_length >= $maximum_upload_size ) {
				wp_send_json_error( [
					'message' => esc_html__( 'The uploaded file(s) are too big.', 'form-block' ),
				] );
			}
		}
		
		foreach ( $_FILES as $field_name => $files ) {
			$this->by_allowed_names( $field_name, $form_data );
			
			if ( is_array( $files['name'] ) ) {
				// if multiple files, resort
				$files = Data::get_instance()->unify_files_array( $files );
				
				foreach ( $files as $file ) {
					if ( empty( $file['tmp_name'] ) ) {
						continue;
					}
					
					if ( $file['size'] > wp_max_upload_size() ) {
						wp_send_json_error( [
							'message' => esc_html__( 'The uploaded file is too big.', 'form-block' ),
						] );
					}
					
					$filesize += $file['size'];
					$validated[] = [
						'name' => $file['name'],
						'path' => $file['tmp_name'],
					];
				}
			}
			else if ( ! empty( $files['tmp_name'] ) ) {
				if ( $files['size'] > wp_max_upload_size() ) {
					wp_send_json_error( [
						'message' => esc_html__( 'The uploaded file is too big.', 'form-block' ),
					] );
				}
				
				$filesize += $files['size'];
				$validated[] = [
					'name' => $files['name'],
					'path' => $files['tmp_name'],
				];
			}
		}
		
		if ( $filesize > wp_max_upload_size() ) {
			wp_send_json_error( [
				'message' => esc_html__( 'The uploaded file(s) are too big.', 'form-block' ),
			] );
		}
		
		return $validated;
	}
	
	/**
	 * Get validation errors by field data attributes.
	 * 
	 * @param	array	$fields Given fields from request
	 * @param	array	$form_data Form data
	 * @return	array A list of errors
	 */
	public function get_errors( array $fields, array $form_data ): array {
		$errors = [];
		
		if ( empty( $form_data['fields'] ) ) {
			return $errors;
		}
		
		foreach ( $form_data['fields'] as $field ) {
			foreach ( $fields as $name => $value ) {
				$field_title = '';
				
				if ( empty( $field['name'] ) ) {
					$field_title = Data::get_instance()->get_field_title_by_name( $name, $form_data['fields'] );
					
					if ( empty( $field['label'] ) || $field_title !== $field['label'] ) {
						continue;
					}
				}
				else if ( $field['name'] === $name ) {
					$field_title = $field['label'];
				}
				
				if ( empty( $field_title ) ) {
					continue;
				}
				
				$field_errors = $this->by_attributes( $value, $field );
				
				if ( ! empty( $field_errors ) ) {
					$errors[ $name ] = [
						'errors' => $field_errors,
						'field_title' => $field_title,
					];
				}
			}
		}
		
		return $errors;
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Form_Block\form_data\Validation The single instance of this class
	 */
	public static function get_instance(): Validation {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
}
