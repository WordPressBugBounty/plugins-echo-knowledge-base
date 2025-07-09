<?php

/**
 * OpenAI API Client
 * 
 * Handles all HTTP communication with OpenAI API endpoints.
 * Implements retry logic, rate limiting, and error handling.
 */
class EPKB_OpenAI_Client {
	
	/**
	 * API configuration
	 * @var EPKB_AI_Config
	 */
	private $config;
	
	/**
	 * Constructor
	 *
	 * @param EPKB_AI_Config $config
	 */
	public function __construct( EPKB_AI_Config $config ) {
		$this->config = $config;
	}
	
	/**
	 * Make a request to the OpenAI API
	 *
	 * @param string $endpoint
	 * @param array $data
	 * @param string $method
	 * @param array $additional_headers
	 * @return array|WP_Error
	 */
	public function request( $endpoint, $data = array(), $method = 'POST', $additional_headers = array() ) {
		
		$url = $this->config->get_api_url() . $endpoint;
		$headers = $this->build_headers( $additional_headers );
		
		$max_retries = $this->config->get_max_retries();
		$retry_delay = $this->config->get_retry_delay();
		
		for ( $attempt = 0; $attempt <= $max_retries; $attempt++ ) {
			
			if ( $attempt > 0 ) {
				$this->wait_with_backoff( $retry_delay, $attempt );
			}
			
			$response = $this->execute_request( $url, $method, $headers, $data );
			if ( is_wp_error( $response ) ) {
				// Retry on rate limit errors
				if ( $response->get_error_code() === 'rate_limit_exceeded' && $attempt < $max_retries ) {
					EPKB_AI_Utilities::add_log( 'OpenAI API rate limit retry', array(
						'endpoint' => $endpoint,
						'attempt' => $attempt + 1,
						'max_retries' => $max_retries,
						'error_code' => $response->get_error_code(),
						'error_message' => $response->get_error_message()
					) );
					continue;
				}
				// Don't retry on other errors
				return $response;
			}
			
			$parsed = $this->parse_response( $response );
			if ( is_wp_error( $parsed ) ) {
				// Retry on rate limit errors
				if ( $parsed->get_error_code() === 'rate_limit_exceeded' && $attempt < $max_retries ) {
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
			'timeout' => $this->config->get_timeout(),
			'sslverify' => $this->config->get_ssl_verify()
		);
		
		if ( ! empty( $data ) ) {
			if ( $method === 'GET' ) {
				$url = add_query_arg( $data, $url );
			} else {
				$args['body'] = json_encode( $data );
			}
		}
		
		$response = wp_remote_request( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			EPKB_AI_Utilities::add_log( 'OpenAI API network error', array(
				'url' => $url,
				'method' => $method,
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message()
			) );
			return new WP_Error( 
				'network_error',
				'Network error: ' . $response->get_error_message()
			);
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
				return new WP_Error( 'authentication_failed', $error_message );
				
			case 429:
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$error = new WP_Error( 'rate_limit_exceeded', $error_message );
				$error->add_data( array( 'retry_after' => $retry_after ) );
				return $error;
				
			case 404:
				return new WP_Error( 'not_found', $error_message );
				
			case 400:
				return new WP_Error( 'bad_request', $error_message );
				
			case 500:
			case 502:
			case 503:
				return new WP_Error( 'server_error', $error_message );
				
			default:
				// Log error details internally without exposing sensitive information
				EPKB_AI_Utilities::add_log( 'OpenAI API error response', array(
					'status_code' => $status_code,
					'error_code' => $error_code,
					'error_message' => $this->sanitize_error_message( $error_message ),
					'response_body' => substr( $this->sanitize_response_body( $body ), 0, 200 )
				) );
				
				// Return generic error message to user
				$user_message = __( 'An error occurred while communicating with the AI service. Please try again later.', 'echo-knowledge-base' );

				return new WP_Error( 'api_error', $user_message );
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
			'Authorization' => 'Bearer ' . $this->config->get_api_key(),
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'Echo-Knowledge-Base/' . \Echo_Knowledge_Base::$version
		);
		
		// Add organization ID if configured
		$org_id = $this->config->get_organization_id();
		if ( ! empty( $org_id ) ) {
			$headers['OpenAI-Organization'] = $org_id;
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
	 * Wait with exponential backoff
	 *
	 * @param int $base_delay Base delay in milliseconds
	 * @param int $attempt Current attempt number
	 */
	private function wait_with_backoff( $base_delay, $attempt ) {
		$delay = min( $base_delay * pow( 2, $attempt - 1 ), 10000 ); // Max 10 seconds
		// Using usleep for rate limiting/backoff is appropriate for API retry logic
		// This prevents overwhelming the API with rapid retry attempts
		usleep( $delay * 1000 );
	}
	
	/**
	 * Upload file using multipart form data
	 *
	 * @param string $endpoint
	 * @param string $file_content
	 * @param string $filename
	 * @param array $fields Additional form fields
	 * @param array $additional_headers
	 * @return array|WP_Error
	 */
	public function upload_file( $endpoint, $file_content, $filename, $fields = array(), $additional_headers = array() ) {
		
		$boundary = wp_generate_password( 24 );
		$url = $this->config->get_api_url() . $endpoint;
		
		// Build headers
		$headers = $this->build_headers( $additional_headers );
		$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
		
		// Build multipart body
		$body = $this->build_multipart_body( $boundary, $fields, $file_content, $filename );
		
		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => $body,
			'timeout' => $this->config->get_upload_timeout()
		);
		
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			EPKB_AI_Utilities::add_log( 'OpenAI API file upload failed', array(
				'endpoint' => $endpoint,
				'filename' => $filename,
				'file_size' => strlen( $file_content ),
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message()
			) );
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
}