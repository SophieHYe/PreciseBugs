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
final class Data {
	/**
	 * @var		string The form ID
	 */
	private $form_id = '';
	
	/**
	 * @var		\epiphyt\Form_Block\form_data\Data
	 */
	public static $instance;
	
	/**
	 * Initialize the class.
	 */
	public function init(): void {
		add_action( 'wp_ajax_form-block-submit', [ $this, 'get_request' ] );
		add_action( 'wp_ajax_nopriv_form-block-submit', [ $this, 'get_request' ] );
	}
	
	/**
	 * Get form data.
	 * 
	 * @param	string	$form_id Current form ID
	 * @return	array Form data
	 */
	public function get( string $form_id = '' ): array {
		if ( ! $form_id ) {
			$form_id = $this->form_id;
		}
		
		if ( ! $form_id ) {
			return [];
		}
		
		return (array) get_option( 'form_block_data_' . $form_id, [] );
	}
	
	/**
	 * Get the field title of a list of fields by its name.
	 * 
	 * @param	string	$name The name to search for
	 * @param	array	$fields The fields to search in
	 * @return	string The field title or the field name, if title cannot be found
	 */
	public function get_field_title_by_name( string $name, array $fields ): string {
		Form_Block::get_instance()->reset_block_name_attributes();
		
		foreach ( $fields as $field ) {
			$field_name = Form_Block::get_instance()->get_block_name_attribute( $field );
			
			if ( $field_name === $name || preg_match( '/' . preg_quote( $field_name, '/' ) . '-\d+/', $name ) ) {
				return $field['label'] ?? $name;
			}
		}
		
		return $name;
	}
	
	/**
	 * Get the form ID.
	 * 
	 * @return	string The form ID
	 */
	public function get_form_id(): string {
		return $this->form_id;
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Form_Block\form_data\Data The single instance of this class
	 */
	public static function get_instance(): Data {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get the request data.
	 */
	public function get_request(): void {
		if ( ! isset( $_POST['_form_id'] ) || ! isset( $_POST['_town'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			/**
			 * Fires after a request is considered invalid.
			 */
			do_action( 'form_block_invalid_data' );
			
			// explicitly return success so that bots cannot learn
			wp_send_json_success();
		}
		
		if ( $this->is_honeypot_filled() ) {
			/**
			 * Fires after a request is considered invalid due to a filled honeypot.
			 */
			do_action( 'form_block_is_honeypot_filled' );
			
			// explicitly return success so that bots cannot learn
			wp_send_json_success();
		}
		
		$this->form_id = sanitize_text_field( wp_unslash( $_POST['_form_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		
		/**
		 * Fires before data has been validated.
		 * 
		 * @param	string	$form_id The form ID
		 */
		do_action( 'form_block_pre_validated_data', $this->form_id );
		
		$fields = Validation::get_instance()->fields();
		$files = Validation::get_instance()->files();
		
		/**
		 * Fires after data has been validated.
		 * 
		 * @param	string	$form_id The form ID
		 * @param	array	$fields Validated fields
		 * @param	array	$files Validated files
		 */
		do_action( 'form_block_validated_data', $this->form_id, $fields, $files );
		
		$this->send( $fields, $files );
	}
	
	/**
	 * Get all required fields of a form.
	 * 
	 * @param	string	$form_id Current form ID
	 * @return	array List of required field names
	 */
	public function get_required_fields( string $form_id = '' ): array {
		if ( ! $form_id ) {
			$form_id = $this->form_id;
		}
		
		if ( ! $form_id ) {
			return [];
		}
		
		Form_Block::get_instance()->reset_block_name_attributes();
		
		$data = $this->get( $form_id );
		$required = [];
		
		foreach ( $data['fields'] as $field ) {
			if ( ! isset( $field['required'] ) ) {
				continue;
			}
			
			$required[] = Form_Block::get_instance()->get_block_name_attribute( $field );
		}
		
		return $required;
	}
	
	/**
	 * Check whether the honeypot is filled.
	 * 
	 * @return	boolean Wether the honeypot is filled
	 */
	private function is_honeypot_filled(): bool {
		$honeypot_key = '_town';
		$is_filled = false;
		
		/**
		 * Filter the honeypot key.
		 * 
		 * @param	string	$honeypot_key The default key '_town'
		 */
		$honeypot_key = apply_filters( 'form_block_honeypot_key', $honeypot_key );
		
		$is_filled = ! empty( $_POST[ $honeypot_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		
		/**
		 * Filter whether the honeypot is filled.
		 * 
		 * @param	bool	$is_filled Whethter the honeypot is filled.
		 */
		$is_filled = apply_filters( 'form_block_is_honeypot_filled', $is_filled );
		
		return $is_filled;
	}
	
	/**
	 * Send form submission to the recipients.
	 * 
	 * @param	array	$fields The validated fields
	 * @param	array	$files The validated files
	 */
	public function send( array $fields, array $files ): void {
		$recipients = [
			get_option( 'admin_email' ),
		];
		
		/**
		 * Filter the form recipients.
		 * 
		 * @param	array	$recipients The recipients
		 * @param	int		$form_id The form ID
		 * @param	array	$fields The validated fields
		 * @param	array	$files The validated files
		 */
		$recipients = apply_filters( 'form_block_recipients', $recipients, $this->form_id, $fields, $files );
		
		$field_data = $this->get( $this->form_id );
		$field_output = [];
		
		foreach ( $fields as $name => $value ) {
			$output = $this->get_field_title_by_name( $name, $field_data['fields'] ) . ': ';
			
			if ( strpos( $value, PHP_EOL ) !== false ) {
				$output .= PHP_EOL;
			}
			
			if ( ! is_array( $value ) ) {
				$output .= $value;
			}
			else {
				$output .= implode( PHP_EOL, array_map( function( $item ) {
					/* translators: list element value */
					return sprintf( _x( '- %s', 'list element in plaintext email', 'form-block' ), $item );
				}, $value ) );
			}
			
			$field_output[] = $output;
		}
		
		$email_text = sprintf(
			/* translators: 1: blog title, 2: form fields */
			__( 'Hello,

you have just received a new form submission with the following data from "%1$s":

%2$s

Your "%1$s" WordPress', 'form-block' ),
			get_bloginfo( 'name' ),
			implode( PHP_EOL, $field_output )
		);
		
		/**
		 * Filter the email text.
		 * 
		 * @param	string	$email_text The email text
		 * @param	string	$field_output The field text output
		 * @param	string	$form_id The form ID
		 * @param	array	$fields The validated fields
		 */
		$email_text = apply_filters( 'form_block_email_text', $email_text, $field_output, $this->form_id, $fields );
		
		$attachments = [];
		
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$new_path = sys_get_temp_dir() . '/' . $file['name'];
				$attachments[] = $new_path;
				
				move_uploaded_file( $file['path'], $new_path );
			}
		}
		
		/* translators: blog name */
		$subject = sprintf( __( 'New form submission via "%s"', 'form-block' ), get_bloginfo( 'name' ) );
		$success = [];
		
		/**
		 * Filter the email subject.
		 * 
		 * @param	string	$subject The email subject
		 */
		$subject = apply_filters( 'form_block_mail_subject', $subject );
		
		foreach ( $recipients as $recipient ) {
			if ( ! filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
				continue;
			}
			
			$sent = wp_mail( $recipient, $subject, $email_text, [], $attachments );
			
			$success[ $recipient ] = $sent;
		}
		
		/**
		 * Runs after sending emails with a status per recipient.
		 * If status is true, the email was sent.
		 * 
		 * @param	array	$success List of emails and whether they were sent
		 * @param	string	$email_text The sent email text
		 * @param	array	$attachments The  sentattachments
		 */
		do_action( 'form_block_sent_emails', $success, $email_text, $attachments );
		
		if ( in_array( false, array_values( $success ), true ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Form submission failed for at least one recipient.', 'form-block' ),
			] );
		}
		
		wp_send_json_success();
	}
	
	/**
	 * Unify the $_FILES-formatted array.
	 * 
	 * @param	array	$file_post The $_FILES-formatted array
	 * @return	array The new formatted array
	 */
	public function unify_files_array( array $file_post ): array {
		$file_ary = [];
		$file_count = count( $file_post['name'] );
		$file_keys = array_keys( $file_post );
		
		for ( $i = 0; $i < $file_count; $i++ ) {
			foreach ( $file_keys as $key ) {
				$file_ary[ $i ][ $key ] = $file_post[ $key ][ $i ];
			}
		}
		
		return $file_ary;
	}
}
