<?php defined( 'ABSPATH' ) || exit();

/**
 * Gemini API Client
 *
 * Handles HTTP communication with Google Gemini API endpoints.
 * Implements retry logic, rate limiting, and error handling.
 */
class EPKB_Gemini_Client {

	const API_BASE_URL = 'https://generativelanguage.googleapis.com';
	const API_VERSION = 'v1beta';
	const DEFAULT_MAX_RETRIES = 3;
	const MAX_FILE_SIZE = 1048576; // 1MB
	const DEFAULT_MODEL = 'gemini-2.5-flash-lite';
	const DEFAULT_MAX_OUTPUT_TOKENS = 8192;
	const DEFAULT_MAX_NUM_RESULTS = 10;

	/**
	 * Make a request to the Gemini API with automatic retry logic
	 *
	 * Retry behavior:
	 * - Insufficient quota errors (429 with insufficient_quota): No retry (billing issue)
	 * - Rate limit errors (429 with rate_limit_exceeded): Retry with exponential backoff
	 * - Other client errors (4xx): No retry
	 * - Server errors (5xx): Retry up to 3 times with exponential backoff
	 * - Network/timeout errors: Retry up to 3 times with exponential backoff
	 *
	 * @param string $endpoint
	 * @param array $data
	 * @param string $method
	 * @param string $purpose Purpose of the request (e.g., 'content_analysis', 'chat', 'search', 'general') - used for logging and timeout determination
	 * @return array|WP_Error
	 */
	public function request( $endpoint, $data = array(), $method = 'POST', $purpose = 'general' ) {

		$api_key_check = $this->check_api_key();
		if ( is_wp_error( $api_key_check ) ) {
			return $api_key_check;
		}

		$last_error = null;
		for ( $attempt = 0; $attempt <= self::DEFAULT_MAX_RETRIES; $attempt++ ) {

			if ( $attempt > 0 && $last_error ) {
				$delay_seconds = EPKB_AI_Utilities::calculate_backoff_delay( $attempt - 1, 1, 60, $last_error );
				EPKB_AI_Utilities::safe_sleep( $delay_seconds );
			}

			// 1. Execute request with short retry mechanism
			$request_start_time = microtime( true );
			$response = $this->execute_request( $endpoint, $method, $data, $purpose );
			$request_duration = microtime( true ) - $request_start_time;

			// 2. Parse response and check for errors (handles all HTTP status codes)
			$parsed = $this->parse_response( $response );

			// 3. Request succeeded, parse final response
			if ( ! is_wp_error( $parsed ) ) {
				$parsed['_timing'] = array( 'elapsed_seconds' => round( $request_duration, 2 ) );
				EPKB_AI_Log::add_log( 'API request completed', array(
					'purpose'          => $purpose,
					'request_endpoint' => $endpoint,
					'model'            => isset( $data['model'] ) ? $data['model'] : '',
					'elapsed_seconds'  => round( $request_duration, 2 ),
					'attempt'          => $attempt + 1
				) );
				return $parsed;
			}

			// 4. Handle error response

			// log error details
			$parsed->add_data( $data );
			$log_context = $parsed->get_error_data();
			$log_context['purpose'] = $purpose;
			$log_context['request_endpoint'] = $endpoint;
			$log_context['model'] = isset( $data['model'] ) ? $data['model'] : '';
			$log_context['request_method'] = $method;
			$log_context['elapsed_seconds'] = round( $request_duration, 2 );
			EPKB_AI_Log::add_log( 'API request error: ' . $parsed->get_error_message(), $log_context );

			// Warn if execution time limit is too low
			$current_limit = ini_get( 'max_execution_time' );
			if ( $current_limit < EPKB_AI_Utilities::DEFAULT_TIMEOUT ) {
				EPKB_AI_Log::add_log( 'PHP execution time limit is too low for AI operations', array( 'current_limit' => $current_limit, 'minimum_required' => EPKB_AI_Utilities::DEFAULT_TIMEOUT) );
			}

			// 5. Determine if we should retry based on error type
			if ( ! EPKB_AI_Utilities::is_retryable_error( $parsed ) ) {
				return $parsed;
			}

			// 6. Check if we should do a short retry e.g., for transient network errors
			$last_error = $request_duration < 5 && $attempt < self::DEFAULT_MAX_RETRIES ? null : $parsed;

		} // end for()

		return new WP_Error( 'max_retries_exceeded', __( 'Maximum retries exceeded', 'echo-knowledge-base' ), ( is_wp_error( $last_error ) ? $last_error->get_error_data() : $data ) );
	}


	/********************************************************************
	 *          Request Functions
	 ********************************************************************/

	/**
	 * Execute the HTTP request
	 *
	 * @param string $endpoint
	 * @param string $method
	 * @param array $data
	 * @param string $purpose Purpose of the request (e.g., 'content_analysis', 'chat', 'search', 'general')
	 * @return array|WP_Error
	 */
	private function execute_request( $endpoint, $method, $data, $purpose ) {

		$headers = $this->build_headers();

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => EPKB_AI_Utilities::get_timeout_for_purpose( $purpose ),
			'sslverify' => true
		);

		if ( ! empty( $data ) ) {
			if ( $method === 'GET' ) {
				$endpoint = add_query_arg( $data, $endpoint );
			} else {
				$json_body = json_encode( $data );
				if ( $json_body === false ) {
					EPKB_AI_Log::add_log( 'JSON ENCODE ERROR: Failed to encode request data: ' . json_last_error_msg() );
					return new WP_Error( 'json_encode_error', 'JSON ENCODE ERROR: Failed to encode request data: ' . json_last_error_msg(), $data );
				}
				$args['body'] = $json_body;
			}
		}

		$response = wp_remote_request( self::API_BASE_URL . '/' . self::API_VERSION . $endpoint, $args );

		return $response;
	}

	/**
	 * Upload file using Google's resumable upload protocol
	 *
	 * @param string $endpoint Upload endpoint (e.g., /fileSearchStores/{id}:uploadToFileSearchStore)
	 * @param string $file_content File content to upload
	 * @param array $metadata Metadata including displayName
	 * @param string $mime_type MIME type (default: text/plain)
	 * @return array|WP_Error Response data or error
	 */
	public function upload_file_resumable( $endpoint, $file_content, $metadata = array(), $mime_type = 'text/plain' ) {

		$api_key_check = $this->check_api_key();
		if ( is_wp_error( $api_key_check ) ) {
			return $api_key_check;
		}

		$content_length = strlen( $file_content );

		// Step 1: Initiate resumable upload
		$init_headers = array(
			'Content-Type'                     => 'application/json',
			'x-goog-api-key'                   => self::get_api_key(),
			'X-Goog-Upload-Protocol'           => 'resumable',
			'X-Goog-Upload-Command'            => 'start',
			'X-Goog-Upload-Header-Content-Length' => $content_length,
			'X-Goog-Upload-Header-Content-Type'   => $mime_type,
			'User-Agent'                       => 'Echo-Knowledge-Base/' . \Echo_Knowledge_Base::$version
		);

		$init_body = array();
		if ( ! empty( $metadata['displayName'] ) ) {
			$init_body['displayName'] = $metadata['displayName'];
		}
		if ( ! empty( $metadata['customMetadata'] ) ) {
			$init_body['customMetadata'] = $metadata['customMetadata'];
		}

		// Use upload URI (note: /upload/ prefix required for file uploads)
		$upload_base_url = 'https://generativelanguage.googleapis.com/upload/' . self::API_VERSION;

		$init_response = wp_remote_post( $upload_base_url . $endpoint, array(
			'headers'   => $init_headers,
			'body'      => ! empty( $init_body ) ? wp_json_encode( $init_body ) : '',
			'timeout'   => 60,
			'sslverify' => true
		) );

		if ( is_wp_error( $init_response ) ) {
			EPKB_AI_Log::add_log( 'Resumable upload initiation failed', array( 'error' => $init_response->get_error_message() ) );
			return $init_response;
		}

		$init_status = wp_remote_retrieve_response_code( $init_response );
		if ( $init_status < 200 || $init_status >= 300 ) {
			$body = wp_remote_retrieve_body( $init_response );
			EPKB_AI_Log::add_log( 'Resumable upload initiation failed', array( 'status' => $init_status, 'body' => $body ) );
			return new WP_Error( 'upload_init_failed', __( 'Failed to initiate file upload', 'echo-knowledge-base' ), array( 'status_code' => $init_status, 'raw_body' => $body ) );
		}

		// Extract upload URL from response headers
		$headers = wp_remote_retrieve_headers( $init_response );
		$upload_url = isset( $headers['x-goog-upload-url'] ) ? $headers['x-goog-upload-url'] : '';
		if ( empty( $upload_url ) ) {
			EPKB_AI_Log::add_log( 'Resumable upload URL not found in response headers' );
			return new WP_Error( 'upload_url_missing', __( 'Upload URL not found in response', 'echo-knowledge-base' ) );
		}

		// Step 2: Upload the actual file content
		$upload_headers = array(
			'Content-Length'         => $content_length,
			'X-Goog-Upload-Offset'   => '0',
			'X-Goog-Upload-Command'  => 'upload, finalize'
		);

		$upload_response = wp_remote_post( $upload_url, array(
			'headers'   => $upload_headers,
			'body'      => $file_content,
			'timeout'   => EPKB_AI_Utilities::get_timeout_for_purpose( 'file_upload' ),
			'sslverify' => true
		) );

		if ( is_wp_error( $upload_response ) ) {
			EPKB_AI_Log::add_log( 'File upload failed', array( 'error' => $upload_response->get_error_message() ) );
			return $upload_response;
		}

		return $this->parse_response( $upload_response );
	}

	/**
	 * Build request headers
	 * @return array
	 */
	private function build_headers() {
		
		return array(
			'Content-Type'  => 'application/json',
			'x-goog-api-key' => self::get_api_key(),
			'User-Agent'    => 'Echo-Knowledge-Base/' . \Echo_Knowledge_Base::$version
		);
	}


	/**********************************************************************
	 *          Response Functions
	 ********************************************************************/

	/**
	 * Parse API response
	 *
	 * @param array|WP_Error $response
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );

		// Handle WP_Error response
		if ( is_wp_error( $response ) ) {
			$error_data = $this->build_error_data( $status_code, $response_message, $body, '', array(), array( 'retry_after' => $retry_after ) );
			$response->add_data( $error_data );
			return $response;
		}

		// Try to decode JSON response
		$data = json_decode( $body, true );
		$is_json = json_last_error() === JSON_ERROR_NONE;

		// Handle success responses
		if ( $status_code >= 200 && $status_code < 300 ) {
			if ( ! $is_json ) {
				$error_data = $this->build_error_data( $status_code, $response_message, $body );
				return new WP_Error( 'invalid_json', 'AI ERROR: Invalid JSON in success response', $error_data );
			}
			return $data;
		}
		
		// Extract error message and code from JSON if available
		$error_message = '';
		$error_status = '';
		$error_code = '';
		if ( $is_json && isset( $data['error'] ) ) {
			if ( isset( $data['error']['message'] ) ) {
				$error_message = 'AI ERROR: ' . $data['error']['message'];
			} elseif ( is_string( $data['error'] ) ) {
				$error_message = 'AI ERROR: ' . $data['error'];
			}

			if ( isset( $data['error']['status'] ) ) {
				$error_status = strtolower( $data['error']['status'] );
			}

			if ( isset( $data['error']['code'] ) ) {
				$error_code = $data['error']['code'];
			}
		}
		
		// Fallback to plain text error body or HTTP message
		if ( empty( $error_message ) ) {
			if ( ! empty( $body ) ) {
				// Use the raw body as error message (e.g., "upstream connect error...")
				$error_message = 'AI ERROR: ' . ( strlen( $body ) > 200 ? substr( $body, 0, 200 ) . '...' : $body );
			} else {
				$error_message = 'AI ERROR: HTTP ' . $status_code . ' ' . $response_message;
			}
		}

		$normalized_error_code = ! empty( $error_status ) ? $error_status : ( $error_code !== '' ? strtolower( (string) $error_code ) : '' );
		$additional_data = array();
		if ( ! empty( $retry_after ) ) {
			$additional_data['retry_after'] = $retry_after;
		}
		if ( ! empty( $error_status ) ) {
			$additional_data['error_status'] = $error_status;
		}

		$error_data = $this->build_error_data( $status_code, $response_message, $body, $normalized_error_code, array(), $additional_data );
		$wp_error_code = $this->map_gemini_error_code( $status_code, $normalized_error_code, $error_message );

		return new WP_Error( $wp_error_code, $error_message, $error_data );
	}

	/**
	 * Map Gemini error status/HTTP code to internal error codes based on API return codes
	 *
	 * @param int $status_code
	 * @param string $error_status
	 * @param string $error_message
	 * @return string
	 */
	private function map_gemini_error_code( $status_code, $error_status, $error_message ) {

		switch ( $error_status ) {
			case 'invalid_argument':
			case 'failed_precondition':
			case 'out_of_range':
				return 'bad_request';

			case 'unauthenticated':
				return 'invalid_api_key';

			case 'permission_denied':
				return 'authentication_failed';

			case 'not_found':
				return 'not_found';

			case 'already_exists':
			case 'aborted':
				return 'version_conflict';

			case 'resource_exhausted':
				return stripos( $error_message, 'quota' ) !== false ? 'insufficient_quota' : 'rate_limit_exceeded';

			case 'deadline_exceeded':
				return 'timeout';

			case 'unavailable':
				return 'service_unavailable';

			case 'internal':
			case 'data_loss':
			case 'unknown':
				return 'server_error';

			case 'unimplemented':
			case 'cancelled':
				return 'api_error';
		}

		switch ( $status_code ) {
			case 400:
				return 'bad_request';

			case 401:
			case 403:
				return 'authentication_failed';

			case 404:
				return 'not_found';

			case 408:
			case 504:
				return 'timeout';

			case 409:
				return 'version_conflict';

			case 429:
				return 'rate_limit_exceeded';

			case 500:
			case 502:
				return 'server_error';

			case 503:
				return 'service_unavailable';
		}

		return 'api_error';
	}

	/**
	 * Build consistent error data structure
	 *
	 * @param int $status_code HTTP status code
	 * @param string $response_message HTTP response message
	 * @param string $body Raw response body
	 * @param string $error_code ChatGPT error code
	 * @param array $rate_limit_info Rate limit information
	 * @param array $additional_data Additional error-specific data
	 * @return array Error data array
	 */
	private function build_error_data( $status_code, $response_message, $body, $error_code = '', $rate_limit_info = array(), $additional_data = array() ) {

		$error_data = array(
			'status_code' => $status_code,
			'response'    => array( 'code' => $status_code, 'message' => $response_message ),
		);

		// Add error code if available
		if ( ! empty( $error_code ) ) {
			$error_data['error_code'] = $error_code;
		}

		// Include raw body for debugging (truncated if too long)
		if ( ! empty( $body ) ) {
			$error_data['raw_body'] = strlen( $body ) > 500 ? substr( $body, 0, 500 ) . '...' : $body;
		}

		// Include rate limit headers if available
		if ( ! empty( $rate_limit_info ) ) {
			$error_data['rate_limit'] = $rate_limit_info;
		}

		// Merge additional error-specific data
		if ( ! empty( $additional_data ) ) {
			$error_data = array_merge( $error_data, $additional_data );
		}

		return $error_data;
	}


	/********************************************************************
	 *          Utility Functions
	 ********************************************************************/

	/**
	 * Get API key from configuration
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return EPKB_AI_Provider::get_api_key( EPKB_AI_Provider::PROVIDER_GEMINI );
	}

	/**
	 * Validate presence of API key
	 *
	 * @return true|WP_Error
	 */
	private function check_api_key() {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'ERROR: API key is not configured. Please configure your API key in the AI settings.', 'echo-knowledge-base' ) );
		}

		return true;
	}

	/**
	 * Test connection to Gemini API by listing models
	 *
	 * @return true|WP_Error True if connection is successful, WP_Error on failure
	 */
	public function test_connection() {
		// Try to list models as a simple test
		$response = $this->request( '/models', array(), 'GET' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['models'] ) || ! is_array( $response['models'] ) ) {
			return new WP_Error( 'invalid_response', __( 'GEMINI ERROR: Invalid response from Gemini API', 'echo-knowledge-base' ) );
		}

		return true;
	}

	/**
	 * Generate content with file search grounding (RAG)
	 *
	 * Uses the Gemini generateContent endpoint with file_search tool for
	 * retrieval-augmented generation from documents in a File Search Store.
	 *
	 * @param string $model Model name (e.g., 'gemini-2.5-flash')
	 * @param string $prompt User prompt/question
	 * @param string $store_id File Search Store ID
	 * @param array $options Additional options (system_instruction, temperature, etc.)
	 * @return array|WP_Error Response data or error
	 */
	public function generate_content_with_file_search( $model, $prompt, $store_id, $options = array() ) {

		if ( empty( $model ) || empty( $prompt ) || empty( $store_id ) ) {
			return new WP_Error( 'missing_params', __( 'Model, prompt, and store ID are required', 'echo-knowledge-base' ) );
		}

		// Ensure store_id has the full resource name format
		$store_resource_name = strpos( $store_id, 'fileSearchStores/' ) === 0 ? $store_id : 'fileSearchStores/' . $store_id;

		// Build contents array - include conversation history if provided
		$contents = $this->build_contents_with_history( $prompt, $options );

		// Build the request body for generateContent with file_search tool
		$request_body = array(
			'contents' => $contents,
			'tools' => array(
				array(
					'file_search' => array(
						'file_search_store_names' => array( $store_resource_name )
					)
				)
			)
		);

		// Add system instruction if provided
		if ( ! empty( $options['system_instruction'] ) ) {
			$request_body['system_instruction'] = array(
				'parts' => array(
					array( 'text' => $options['system_instruction'] )
				)
			);
		}

		// Add generation config for model parameters
		$generation_config = array();
		if ( isset( $options['temperature'] ) ) {
			$generation_config['temperature'] = floatval( $options['temperature'] );
		}
		if ( isset( $options['top_p'] ) ) {
			$generation_config['topP'] = floatval( $options['top_p'] );
		}
		if ( isset( $options['max_output_tokens'] ) ) {
			$generation_config['maxOutputTokens'] = intval( $options['max_output_tokens'] );
		}
		if ( ! empty( $generation_config ) ) {
			$request_body['generationConfig'] = $generation_config;
		}

		// Add thinking config for Gemini 2.5+/3.0+ models (must be inside generationConfig)
		if ( ! empty( $options['thinking_level'] ) ) {
			$thinking_config = self::get_thinking_config( $model, $options['thinking_level'] );
			if ( ! empty( $thinking_config ) ) {
				if ( ! isset( $request_body['generationConfig'] ) ) {
					$request_body['generationConfig'] = array();
				}
				$request_body['generationConfig']['thinkingConfig'] = $thinking_config;
			}
		}

		// Make request to generateContent endpoint
		$endpoint = '/models/' . $model . ':generateContent';
		return $this->request( $endpoint, $request_body, 'POST', 'search' );
	}

	/**
	 * Get models and their default parameters
	 *
	 * Models are ordered by capability (fastest first, smartest last).
	 * Preset metadata is included for models that should appear in presets.
	 *
	 * @param string|null $model_name Optional specific model name to retrieve
	 * @return array Model(s) with default parameters
	 */
	public static function get_models_and_default_params( $model_name = null ) {

		$models = array(
			'gemini-2.5-flash-lite' => array(
				'name'                       => 'Gemini 2.5 Flash-Lite',
				'type'                       => 'gemini',
				'preset_key'                 => EPKB_AI_Provider::FASTEST_MODEL,
				'preset_label'               => __( 'Fastest', 'echo-knowledge-base' ),
				'default_params'             => array(
					'temperature'       => 1,
					'top_p'             => 0.95,
					'max_output_tokens' => self::DEFAULT_MAX_OUTPUT_TOKENS
				),
				'supports_temperature'       => true,
				'supports_top_p'             => true,
				'supports_thinking_level'    => false,
				'supports_max_output_tokens' => true,
				'max_output_tokens_limit'    => 65536,
				'parameters'                 => array( 'temperature', 'top_p', 'max_output_tokens' )
			),
			'gemini-2.5-flash' => array(
				'name'                       => 'Gemini 2.5 Flash',
				'type'                       => 'gemini',
				'default_params'             => array(
					'temperature'       => 1,
					'top_p'             => 0.95,
					'thinking_level'    => 'low',
					'max_output_tokens' => self::DEFAULT_MAX_OUTPUT_TOKENS
				),
				'supports_temperature'       => true,
				'supports_top_p'             => true,
				'supports_thinking_level'    => true,
				'supports_max_output_tokens' => true,
				'max_output_tokens_limit'    => 65536,
				'parameters'                 => array( 'temperature', 'top_p', 'thinking_level', 'max_output_tokens' )
			),
			'gemini-3-flash-preview' => array(
				'name'                       => 'Gemini 3 Flash',
				'type'                       => 'gemini',
				'preset_key'                 => 'balanced',
				'preset_label'               => __( 'Balanced', 'echo-knowledge-base' ),
				'default_params'             => array(
					'temperature'       => 0.4,
					'top_p'             => 0.95,
					'thinking_level'    => 'low',
					'max_output_tokens' => self::DEFAULT_MAX_OUTPUT_TOKENS
				),
				'supports_temperature'       => true,
				'supports_top_p'             => true,
				'supports_thinking_level'    => true,
				'supports_max_output_tokens' => true,
				'max_output_tokens_limit'    => 65536,
				'parameters'                 => array( 'temperature', 'top_p', 'thinking_level', 'max_output_tokens' )
			),
			'gemini-2.5-pro' => array(
				'name'                       => 'Gemini 2.5 Pro',
				'type'                       => 'gemini',
				'default_params'             => array(
					'temperature'       => 1.0,
					'top_p'             => 0.95,
					'thinking_level'    => 'low',
					'max_output_tokens' => self::DEFAULT_MAX_OUTPUT_TOKENS
				),
				'supports_temperature'       => true,
				'supports_top_p'             => true,
				'supports_thinking_level'    => true,
				'supports_max_output_tokens' => true,
				'max_output_tokens_limit'    => 65536,
				'parameters'                 => array( 'temperature', 'top_p', 'thinking_level', 'max_output_tokens' )
			),
			'gemini-3-pro-preview' => array(
				'name'                       => 'Gemini 3 Pro',
				'type'                       => 'gemini',
				'preset_key'                 => 'smartest',
				'preset_label'               => __( 'Smartest', 'echo-knowledge-base' ),
				'default_params'             => array(
					'temperature'       => 1.0,
					'top_p'             => 0.95,
					'thinking_level'    => 'low',
					'max_output_tokens' => self::DEFAULT_MAX_OUTPUT_TOKENS
				),
				'supports_temperature'       => true,
				'supports_top_p'             => true,
				'supports_thinking_level'    => true,
				'supports_max_output_tokens' => true,
				'max_output_tokens_limit'    => 65536,
				'parameters'                 => array( 'temperature', 'top_p', 'thinking_level', 'max_output_tokens' )
			)
		);

		// Return specific model if requested
		if ( ! empty( $model_name ) ) {
			return isset( $models[$model_name] ) ? $models[$model_name] : $models[self::DEFAULT_MODEL];
		}

		return $models;
	}

	/**
	 * Apply model-specific parameters to a request
	 *
	 * @param array $request
	 * @param string $model
	 * @param array $params
	 * @return array
	 */
	public static function apply_model_parameters( $request, $model, $params = array() ) {

		$model_spec = self::get_models_and_default_params( $model );

		if ( empty( $params ) ) {
			$params = $model_spec['default_params'];
		}

		// Build generationConfig for Gemini API (parameters must be nested inside generationConfig)
		$generation_config = isset( $request['generationConfig'] ) ? $request['generationConfig'] : array();

		if ( $model_spec['supports_temperature'] && isset( $params['temperature'] ) ) {
			$generation_config['temperature'] = floatval( $params['temperature'] );
		}

		if ( $model_spec['supports_top_p'] && isset( $params['top_p'] ) ) {
			$generation_config['topP'] = floatval( $params['top_p'] );
		}

		if ( $model_spec['supports_max_output_tokens'] && isset( $params['max_output_tokens'] ) ) {
			$max_output = intval( $params['max_output_tokens'] );
			$limit      = isset( $model_spec['max_output_tokens_limit'] ) ? $model_spec['max_output_tokens_limit'] : self::DEFAULT_MAX_OUTPUT_TOKENS;
			if ( $max_output > 0 && $max_output <= $limit ) {
				$generation_config['maxOutputTokens'] = $max_output;
			}
		}

		// Apply thinking config for models that support it (Gemini 2.5+/3.0+)
		if ( ! empty( $model_spec['supports_thinking_level'] ) && ! empty( $params['thinking_level'] ) ) {
			$thinking_config = self::get_thinking_config( $model, $params['thinking_level'] );
			if ( ! empty( $thinking_config ) ) {
				$generation_config['thinkingConfig'] = $thinking_config;
			}
		}

		if ( ! empty( $generation_config ) ) {
			$request['generationConfig'] = $generation_config;
		}

		return $request;
	}

	/**
	 * Get thinking config based on model and level
	 * - Gemini 2.5 models use thinkingBudget (integer tokens)
	 * - Gemini 3+ models use thinkingLevel (LOW, MEDIUM, HIGH)
	 *
	 * @param string $model
	 * @param string $level 'low' or 'high'
	 * @return array
	 */
	private static function get_thinking_config( $model, $level ) {

		$valid_levels = array( 'low', 'high' );
		if ( ! in_array( $level, $valid_levels, true ) ) {
			return array();
		}

		// Gemini 3+ models use thinkingLevel
		if ( strpos( $model, 'gemini-3' ) === 0 ) {
			return array( 'thinkingLevel' => strtoupper( $level ) );
		}

		// Gemini 2.5 models use thinkingBudget (token count)
		$budget_map = array(
			'low'  => 2048,
			'high' => 8192
		);

		return array( 'thinkingBudget' => $budget_map[ $level ] );
	}

	/**
	 * Build contents array with conversation history for multi-turn conversations
	 *
	 * Gemini API requires all previous messages to be included in the contents array
	 * for multi-turn conversations (unlike ChatGPT which uses previous_response_id).
	 *
	 * @param string $current_prompt Current user message
	 * @param array $options Options array containing 'conversation_history' key
	 * @return array Contents array for Gemini API
	 */
	private function build_contents_with_history( $current_prompt, $options = array() ) {

		$contents = array();
		$conversation_history = isset( $options['conversation_history'] ) ? $options['conversation_history'] : array();

		// Add previous messages from conversation history
		if ( ! empty( $conversation_history ) ) {
			foreach ( $conversation_history as $message ) {
				if ( empty( $message['content'] ) ) {
					continue;
				}

				// Map 'assistant' role to 'model' for Gemini API
				$role = isset( $message['role'] ) && $message['role'] === 'assistant' ? 'model' : 'user';

				$contents[] = array(
					'role' => $role,
					'parts' => array(
						array( 'text' => $message['content'] )
					)
				);
			}
		}

		// Add current user message
		$contents[] = array(
			'role' => 'user',
			'parts' => array(
				array( 'text' => $current_prompt )
			)
		);

		return $contents;
	}
}
