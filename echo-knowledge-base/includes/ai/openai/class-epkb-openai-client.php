<?php

/**
 * OpenAI API Client
 * 
 * Handles all HTTP communication with OpenAI API endpoints.
 * Implements retry logic, rate limiting, and error handling.
 */
class EPKB_OpenAI_Client {
	
	/**
	 * OpenAI API Constants
	 */
	const API_BASE_URL = 'https://api.openai.com';
	const API_VERSION = 'v1';
	const DEFAULT_MODEL = 'gpt-4o';
	const DEFAULT_TIMEOUT = 120;
	const DEFAULT_UPLOAD_TIMEOUT = 300;
	const DEFAULT_MAX_RETRIES = 3;
	const DEFAULT_CONVERSATION_EXPIRY_DAYS = 29; // 29 days
	const MAX_FILE_SIZE = 1048576; // 1MB
	const DEFAULT_MAX_OUTPUT_TOKENS = 4096;
	const DEFAULT_TEMPERATURE = 0.7;
	const DEFAULT_MAX_NUM_RESULTS = 3;

	/**
	 * Make a request to the OpenAI API with automatic retry logic
	 * 
	 * Retry behavior:
	 * - Rate limit errors (429): No retry (return immediately to client)
	 * - Other client errors (4xx): No retry
	 * - Server errors (5xx): Retry up to 3 times with exponential backoff
	 * - Network/timeout errors: Retry up to 3 times with exponential backoff
	 *
	 * @param string $endpoint
	 * @param array $data
	 * @param string $method
	 * @param array $additional_headers
	 * @return array|WP_Error
	 */
	public function request( $endpoint, $data = array(), $method = 'POST', $additional_headers = array() ) {
		
		// Check if API key is configured
		$api_key_check = $this->check_api_key();
		if ( is_wp_error( $api_key_check ) ) {
			return $api_key_check;
		}
		
		$url = $this->get_api_url() . $endpoint;
		$headers = $this->build_headers( $additional_headers );
		
		$max_retries = self::DEFAULT_MAX_RETRIES;
		$last_error = null;
		
		for ( $attempt = 0; $attempt <= $max_retries; $attempt++ ) {
			
			if ( $attempt > 0 && $last_error ) {
				// Use intelligent backoff delay calculation with error information
				$delay_seconds = EPKB_AI_OpenAI_Handler::calculate_backoff_delay( $attempt - 1, 1, 60, $last_error );
				EPKB_AI_Utilities::safe_sleep( $delay_seconds );
			}
			
			$response = $this->execute_request( $url, $method, $headers, $data );
			if ( is_wp_error( $response ) ) {
				// Check if error is retryable
				if ( EPKB_AI_OpenAI_Handler::is_retryable_error( $response ) && $attempt < $max_retries ) {
					$last_error = $response;
					continue;
				}
				// Don't retry on other errors
				return $response;
			}
			
			$parsed = $this->parse_response( $response );
			if ( is_wp_error( $parsed ) ) {
				// Check if error is retryable
				if ( EPKB_AI_OpenAI_Handler::is_retryable_error( $parsed ) && $attempt < $max_retries ) {
					$last_error = $parsed;
					continue;
				}
				// Don't retry on other errors
				return $parsed;
			}
			
			return $parsed;
		}
		
		return new WP_Error( 'max_retries_exceeded', 'Maximum retries exceeded' );
	}

	/**
	 * Execute the HTTP request
	 *
	 * @param string $url
	 * @param string $method
	 * @param array $headers
	 * @param array $data
	 * @return array|WP_Error
	 */
	private function execute_request( $url, $method, $headers, $data ) {
		
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => self::DEFAULT_TIMEOUT,
			'sslverify' => true
		);
		
		if ( ! empty( $data ) ) {
			if ( $method === 'GET' ) {
				$url = add_query_arg( $data, $url );
			} else {
				$json_body = json_encode( $data );
				if ( $json_body === false ) {
					return new WP_Error( 'json_encode_error', 'Failed to encode request data: ' . json_last_error_msg() );
				}
				$args['body'] = $json_body;
			}
		}
		
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error = new WP_Error( 'network_error', 'Network error: ' . $response->get_error_message() );
			// Add response data to make it compatible with is_retryable_error check
			$error->add_data( array( 'response' => array( 'code' => 0 ) ) );

			return $error;
		}
		
		return $response;
	}
	
	/**
	 * Parse API response
	 *
	 * @param array $response
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {
		
		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );
		
		// Extract rate limit headers for intelligent retry timing; see Rate limiting information documentation
		$rate_limit_info = array();
		
		// Check for request-based rate limits
		if ( isset( $headers['x-ratelimit-limit-requests'] ) ) {
			$rate_limit_info['limit_requests'] = intval( $headers['x-ratelimit-limit-requests'] );
		}
		if ( isset( $headers['x-ratelimit-remaining-requests'] ) ) {
			$rate_limit_info['remaining_requests'] = intval( $headers['x-ratelimit-remaining-requests'] );
		}
		if ( isset( $headers['x-ratelimit-reset-requests'] ) ) {
			$reset_timestamp = $headers['x-ratelimit-reset-requests'];
			// Handle both timestamp and duration formats
			if ( strpos( $reset_timestamp, 's' ) !== false || strpos( $reset_timestamp, 'm' ) !== false ) {
				// Parse duration format (e.g., "5s", "2m30s")
				$seconds = $this->parse_duration_to_seconds( $reset_timestamp );
				$rate_limit_info['reset_requests'] = time() + $seconds;
				$rate_limit_info['reset_requests_in'] = $seconds;
			} else {
				$rate_limit_info['reset_requests'] = intval( $reset_timestamp );
				$rate_limit_info['reset_requests_in'] = max( 0, $rate_limit_info['reset_requests'] - time() );
			}
		}
		
		// Check for token-based rate limits
		if ( isset( $headers['x-ratelimit-limit-tokens'] ) ) {
			$rate_limit_info['limit_tokens'] = intval( $headers['x-ratelimit-limit-tokens'] );
		}
		if ( isset( $headers['x-ratelimit-remaining-tokens'] ) ) {
			$rate_limit_info['remaining_tokens'] = intval( $headers['x-ratelimit-remaining-tokens'] );
		}
		if ( isset( $headers['x-ratelimit-reset-tokens'] ) ) {
			$reset_timestamp = $headers['x-ratelimit-reset-tokens'];
			// Handle both timestamp and duration formats
			if ( strpos( $reset_timestamp, 's' ) !== false || strpos( $reset_timestamp, 'm' ) !== false ) {
				// Parse duration format (e.g., "5s", "2m30s")
				$seconds = $this->parse_duration_to_seconds( $reset_timestamp );
				$rate_limit_info['reset_tokens'] = time() + $seconds;
				$rate_limit_info['reset_tokens_in'] = $seconds;
			} else {
				$rate_limit_info['reset_tokens'] = intval( $reset_timestamp );
				$rate_limit_info['reset_tokens_in'] = max( 0, $rate_limit_info['reset_tokens'] - time() );
			}
		}

		// Store rate limit info for next request timing
		if ( ! empty( $rate_limit_info ) ) {
			set_transient( 'epkb_openai_rate_limit', $rate_limit_info, 300 );
		}
		
		// Try to decode JSON response
		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 
				'invalid_response',
				'Invalid JSON response: ' . json_last_error_msg()
			);
		}
		
		// Handle different status codes
		if ( $status_code >= 200 && $status_code < 300 ) {
			return $data;
		}
		
		// Extract error message
		$error_message = $this->extract_error_message( $data );
		$error_code = isset( $data['error']['code'] ) ? $data['error']['code'] : 'unknown_error';
		
		// Handle specific error types
		switch ( $status_code ) {
			case 401:
			case 403:
				$wp_error = new WP_Error( 'authentication_failed', $error_message );
				$wp_error->add_data( array(
					'status_code' => $status_code,
					'response' => array( 'code' => $status_code )
				) );
				return $wp_error;
				
			case 429:
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$error = new WP_Error( 'rate_limit_exceeded', $error_message );
				$error_data = array( 
					'retry_after' => $retry_after,
					'response' => array( 'code' => 429 )
				);
				// Include rate limit headers if available
				if ( ! empty( $rate_limit_info ) ) {
					$error_data['rate_limit'] = $rate_limit_info;
				}
				$error->add_data( $error_data );
				return $error;
				
			case 404:
				return new WP_Error( 'not_found', $error_message );
				
			case 400:
				return new WP_Error( 'bad_request', $error_message );
				
			case 500:
			case 502:
			case 503:
				$error = new WP_Error( 'server_error', $error_message );
				$error_data = array( 
					'response' => array( 'code' => $status_code )
				);
				// Include rate limit headers if available
				if ( ! empty( $rate_limit_info ) ) {
					$error_data['rate_limit'] = $rate_limit_info;
				}
				$error->add_data( $error_data );
				return $error;
				
			default:
				// Create WP_Error with original message for proper handling
				$wp_error = new WP_Error( 'api_error', $error_message );
				$wp_error->add_data( array(
					'status_code' => $status_code,
					'error_code' => $error_code,
					'response' => array( 'code' => $status_code )
				) );
				return $wp_error;
		}
	}
	
	/**
	 * Build request headers
	 *
	 * @param array $additional_headers
	 * @return array
	 */
	private function build_headers( $additional_headers = array() ) {
		
		$headers = array(
			'Authorization' => 'Bearer ' . self::get_api_key(),
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'Echo-Knowledge-Base/' . \Echo_Knowledge_Base::$version
		);
		
		// Add organization ID if configured
		if ( ! empty( $this->organization_id ) ) {
			$headers['OpenAI-Organization'] = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_organization_id' );
		}
		
		// Merge additional headers
		if ( ! empty( $additional_headers ) ) {
			$headers = array_merge( $headers, $additional_headers );
		}
		
		return $headers;
	}
	
	/**
	 * Extract error message from response data
	 *
	 * @param array $data
	 * @return string
	 */
	private function extract_error_message( $data ) {
		
		if ( isset( $data['error']['message'] ) ) {
			return $data['error']['message'];
		}
		
		if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			return $data['error'];
		}
		
		return 'Unknown API error';
	}
	
	/**
	 * Upload file using multipart form data
	 *
	 * @param string $endpoint
	 * @param string $file_content
	 * @param string $filename
	 * @param array $fields Additional form fields e.g. array( 'purpose' => 'assistants' )
	 * @param array $additional_headers
	 * @return array|WP_Error
	 */
	public function upload_file( $endpoint, $file_content, $filename, $fields = array(), $additional_headers = array() ) {
		
		// Check if API key is configured
		$api_key_check = $this->check_api_key();
		if ( is_wp_error( $api_key_check ) ) {
			return $api_key_check;
		}
		
		$boundary = wp_generate_password( 24 );
		$url = $this->get_api_url() . $endpoint;
		
		// Build headers
		$headers = $this->build_headers( $additional_headers );
		$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
		
		// Build multipart body
		$body = $this->build_multipart_body( $boundary, $fields, $file_content, $filename );
		
		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => $body,
			'timeout' => self::DEFAULT_UPLOAD_TIMEOUT
		);
		
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 
				'upload_error',
				'File upload failed: ' . $response->get_error_message()
			);
		}
		
		return $this->parse_response( $response );
	}
	
	/**
	 * Build multipart form data body
	 *
	 * @param string $boundary
	 * @param array $fields
	 * @param string $file_content
	 * @param string $filename
	 * @return string
	 */
	private function build_multipart_body( $boundary, $fields, $file_content, $filename ) {
		
		$body = '';
		
		// Add form fields
		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
			$body .= $value . "\r\n";
		}
		
		// Add file
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
		$body .= "\r\n";
		$body .= $file_content . "\r\n";
		$body .= '--' . $boundary . '--';
		
		return $body;
	}
	
	/**
	 * Sanitize error message to remove sensitive information
	 *
	 * @param string $message
	 * @return string
	 */
	private function sanitize_error_message( $message ) {
		// Remove API keys, tokens, or other sensitive patterns
		$patterns = array(
			'/Bearer\s+[\w\-\.]+/i' => 'Bearer [REDACTED]',
			'/sk-[\w\-]+/i' => '[API_KEY_REDACTED]',
			'/[\w\-]{20,}/i' => '[TOKEN_REDACTED]',
			'/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/' => '[IP_REDACTED]',
			'/\b[\w\.-]+@[\w\.-]+\.\w+\b/' => '[EMAIL_REDACTED]'
		);
		
		foreach ( $patterns as $pattern => $replacement ) {
			$message = preg_replace( $pattern, $replacement, $message );
		}
		
		return substr( sanitize_text_field( $message ), 0, 255 );
	}
	
	/**
	 * Sanitize response body to remove sensitive information
	 *
	 * @param string $body
	 * @return string
	 */
	private function sanitize_response_body( $body ) {
		// Decode JSON if possible
		$decoded = json_decode( $body, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			// Remove sensitive fields
			$sensitive_fields = array( 'api_key', 'token', 'secret', 'password', 'authorization' );
			foreach ( $sensitive_fields as $field ) {
				if ( isset( $decoded[$field] ) ) {
					$decoded[$field] = '[REDACTED]';
				}
			}
			$body = wp_json_encode( $decoded );
		}
		
		// Apply same pattern sanitization as error messages
		return $this->sanitize_error_message( $body );
	}

	/**
	 * Get API URL
	 *
	 * @return string
	 */
	private function get_api_url() {
		return self::API_BASE_URL . '/' . self::API_VERSION;
	}

	/**
	 * Check if API key is configured
	 *
	 * @return true|WP_Error
	 */
	private function check_api_key() {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'OpenAI API key is not configured. Please configure your API key in the AI settings.', 'echo-knowledge-base' )
			);
		}
		return true;
	}

	/**
	 * Get API key from WordPress options (static)
	 *
	 * @return string
	 */
	public static function get_api_key() {

		$encrypted_key = EPKB_AI_Config_Specs::get_unmasked_api_key();
		if ( empty( $encrypted_key ) ) {
			return '';
		}

		$decrypted = EPKB_Utilities::decrypt_data( $encrypted_key );

		return $decrypted !== false ? $decrypted : '';
	}
	
	/**
	 * Parse duration string to seconds
	 * Handles formats like "5s", "2m30s", "1h30m", etc.
	 *
	 * @param string $duration
	 * @return int Seconds
	 */
	private function parse_duration_to_seconds( $duration ) {
		$seconds = 0;
		
		// Match hours
		if ( preg_match( '/(\d+)h/i', $duration, $matches ) ) {
			$seconds += intval( $matches[1] ) * 3600;
		}
		
		// Match minutes
		if ( preg_match( '/(\d+)m/i', $duration, $matches ) ) {
			$seconds += intval( $matches[1] ) * 60;
		}
		
		// Match seconds
		if ( preg_match( '/(\d+)s/i', $duration, $matches ) ) {
			$seconds += intval( $matches[1] );
		}
		
		// If no units found, assume it's seconds
		if ( $seconds === 0 && is_numeric( $duration ) ) {
			$seconds = intval( $duration );
		}
		
		return $seconds;
	}

	/**
	 * Test connection to OpenAI API
	 *
	 * @return true|WP_Error True if connection is successful, WP_Error on failure
	 */
	public function test_connection() {
		// Try to list models as a simple test
		$response = $this->request( '/models', array(), 'GET' );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Check if we got a valid response structure
		if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from OpenAI API', 'echo-knowledge-base' ) );
		}
		
		return true;
	}
}