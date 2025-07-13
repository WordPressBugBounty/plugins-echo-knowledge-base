<?php
/**
 * AI Validation Utility
 * 
 * Centralized validation for all AI-related inputs
 * Provides consistent validation rules and error messages
 */
class EPKB_AI_Validation {
	
	/**
	 * Validate and sanitize chat message
	 *
	 * @param string $message
	 * @return string|WP_Error Sanitized message or error
	 */
	public static function validate_message( $message ) {
		
		// Check if empty
		if ( empty( $message ) ) {
			return new WP_Error( 'empty_message', __( 'Please enter a message.', 'echo-knowledge-base' ) );
		}
		
		// Check message length
		$max_length = apply_filters( 'epkb_ai_chat_max_message_length', 5000 );
		if ( strlen( $message ) > $max_length ) {
			return new WP_Error( 
				'message_too_long', 
				sprintf( __( 'Message is too long. Please keep it under %d characters.', 'echo-knowledge-base' ), $max_length )
			);
		}
		
		// Basic XSS prevention - strip all HTML
		$message = wp_kses( $message, array() );
		
		// Check for malicious patterns
		$blocked_patterns = apply_filters( 'epkb_ai_chat_blocked_patterns', array(
			'/\<script/i',
			'/javascript:/i',
			'/on\w+\s*=/i', // onclick, onload, etc.
			'/data:text\/html/i',
			'/vbscript:/i'
		) );
		
		foreach ( $blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return new WP_Error( 'invalid_content', __( 'Invalid content detected.', 'echo-knowledge-base' ) );
			}
		}
		
		return sanitize_textarea_field( $message );
	}

	/**
	 * Validate UUID v4 format
	 *
	 * @param string $uuid UUID to validate
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	public static function validate_uuid( $uuid ) {
		if ( empty( $uuid ) ) {
			return new WP_Error( 'empty_uuid', __( 'UUID is empty', 'echo-knowledge-base' ) );
		}

		$uuid = trim( $uuid );

		// Regular expression to validate UUID v4 format
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		if ( preg_match( $pattern, $uuid ) !== 1 ) {
			return new WP_Error( 'invalid_uuid', sprintf( __( 'Invalid UUID format: %s', 'echo-knowledge-base' ), $uuid ) );
		}

		return true;
	}

	/**
	 * Validate idempotency key
	 *
	 * @param string $key
	 * @return string|WP_Error Validated key or error
	 */
	public static function validate_idempotency_key( $key ) {
		
		// Check if empty
		if ( empty( $key ) ) {
			return new WP_Error( 
				'empty_idempotency_key', 
				__( 'Idempotency key is required', 'echo-knowledge-base' ) 
			);
		}
		
		// Sanitize
		$key = sanitize_text_field( $key );
		
		// Check length
		if ( strlen( $key ) > 64 ) {
			return new WP_Error( 
				'invalid_idempotency_key', 
				__( 'Idempotency key is too long', 'echo-knowledge-base' ) 
			);
		}
		
		// Check format - should be UUID or similar
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) ) {
			return new WP_Error( 
				'invalid_idempotency_key', 
				__( 'Invalid idempotency key format', 'echo-knowledge-base' ) 
			);
		}
		
		return $key;
	}
	
	/**
	 * Validate widget ID
	 *
	 * @param int|string $widget_id
	 * @return int|WP_Error Validated widget ID or error
	 */
	public static function validate_widget_id( $widget_id ) {
		
		// Convert to integer
		$widget_id = absint( $widget_id );
		
		// Check range
		$max_widget_id = defined( 'EPKB_AI_Config::MAX_WIDGET_ID' ) ? EPKB_AI_Config::MAX_WIDGET_ID : 10;
		
		if ( $widget_id < 1 || $widget_id > $max_widget_id ) {
			return new WP_Error( 
				'invalid_widget_id', 
				sprintf( __( 'Widget ID must be between 1 and %d', 'echo-knowledge-base' ), $max_widget_id )
			);
		}
		
		return $widget_id;
	}

	/**
	 * Validate language code
	 *
	 * @param string $language
	 * @return string|WP_Error Validated language or error
	 */
	public static function validate_language( $language ) {
		$language = sanitize_text_field( $language );
		// Basic validation for language codes (e.g., en, en_US, en-US)
		if ( ! preg_match( '/^[a-z]{2}([_-][A-Z]{2})?$/', $language ) || strlen( $language ) > 10 ) {
			return '';
		}
		return $language;
	}
	
	/**
	 * Validate conversation title
	 *
	 * @param string $title
	 * @return string Validated and truncated title
	 */
	public static function validate_title( $title ) {
		
		$title = sanitize_text_field( $title );
		
		if ( strlen( $title ) > 255 ) {
			$title = substr( $title, 0, 252 ) . '...';
		}
		
		return $title;
	}

	/**
	 * Batch validate multiple fields
	 *
	 * @param array $fields Array of field_name => value pairs
	 * @param array $rules Array of field_name => validation_method pairs
	 * @return array|WP_Error Array of validated values or first error encountered
	 */
	public static function validate_fields( $fields, $rules ) {
		
		$validated = array();
		
		foreach ( $rules as $field_name => $validation_method ) {
			// Skip if field not provided
			if ( ! isset( $fields[$field_name] ) ) {
				continue;
			}
			
			// Validate using specified method
			if ( method_exists( __CLASS__, $validation_method ) ) {
				$result = self::$validation_method( $fields[$field_name] );
				
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				
				$validated[$field_name] = $result;
			}
		}
		
		return $validated;
	}
}