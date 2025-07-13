<?php defined( 'ABSPATH' ) || exit();

/**
 * Base REST API Controller for AI functionality
 * Provides common functionality for all AI REST endpoints
 * 
 * Currently provides:
 * - Common REST response creation with token refresh
 * - Error handling utilities
 * - Collection parameter schemas
 * 
 * Future considerations:
 * - If multiple AI services need sessions, consider creating a shared session endpoint here
 * - Common authentication/authorization logic could be added here
 * - Shared rate limiting logic could be centralized here
 */
abstract class EPKB_AI_REST_Base_Controller extends WP_REST_Controller {

	/**
	 * Namespace and version
	 */
	protected $namespace = 'epkb/v1';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes') );
	}

	public function init() {
		if ( ! EPKB_AI_Utilities::is_ai_enabled() ) {
			return;
		}

		// if our REST request, start session
		if ( EPKB_AI_Security::can_start_session() ) {
			session_start();
		}
	}

	public function register_routes() {
		// Base routes can be registered here if needed in the future
	}

	/**
	 * Helper method to create REST responses with automatic token refresh
	 *
	 * @param array $data The response data
	 * @param int $status HTTP status code
	 * @param WP_Error|null $wp_error Optional WP_Error object to process
	 * @return WP_REST_Response
	 */
	protected function create_rest_response( $data, $status=200, $wp_error=null ) {

		// If WP_Error is provided, process it and merge into data
		if ( $wp_error instanceof WP_Error ) {
			$error_result = $this->process_wp_error( $wp_error, $status );
			$data = array_merge( $error_result['data'], $data );
			$status = $error_result['status'];

		} elseif ( isset( $data['error'] ) && ! isset( $data['status'] ) ) {
			// If error is provided in data but status is not, add it
			$data['status'] = 'error';
			
			// Use status mapping if status is still 200
			if ( $status === 200 ) {
				$status = $this->get_error_status_code( $data['error'] );
			}
		}

		// Always check if we need to provide a new nonce
		$current_nonce = epkb_get_instance()->security_obj->get_nonce( true );
		$request_nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : null;

		// Check if nonce is approaching expiration (WordPress nonces last 12-24 hours)
		// We'll refresh if the nonce is older than 10 hours to be safe
		$should_refresh = false;

		if ( $request_nonce ) {
			// Verify if the nonce is still valid but getting old
			$verify = wp_verify_nonce( $request_nonce, 'wp_rest' );
			if ( $verify === 2 ) {
				// Nonce is valid but was generated 12-24 hours ago
				$should_refresh = true;
			}
		}

		// If the nonce has changed or should be refreshed, include the new one
		if ( $should_refresh || ( $request_nonce && $current_nonce !== $request_nonce ) ) {
			$data['new_token'] = $current_nonce;
		}

		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Process a WP_Error object and extract error data
	 * 
	 * @param WP_Error $wp_error WP_Error object to process
	 * @param int $default_status Default status code if none is found
	 * @return array Array containing error data and status code
	 */
	protected function process_wp_error( $wp_error, $default_status = 200 ) {
		if ( ! ( $wp_error instanceof WP_Error ) ) {
			return array(
				'data' => array(),
				'status' => $default_status
			);
		}
		
		$error_code = $wp_error->get_error_code();
		$error_data = $wp_error->get_error_data( $error_code );
		$status = $default_status;
		
		// Extract status from error data if available
		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = (int) $error_data['status'];
		} elseif ( $default_status === 200 ) {
			// If no status provided and default is still 200, use appropriate error status
			$status = $this->get_error_status_code( $error_code );
		}
		
		// Get all error messages and combine them
		$error_messages = implode( '; ', $wp_error->get_error_messages( $error_code ) );
		
		// Build error response data
		$error_response = array(
			'success' => false,
			'status' => 'error',
			'error' => $error_code,
			'message' => $error_messages ?: $wp_error->get_error_message()
		);
		
		return array(
			'data' => $error_response,
			'status' => $status
		);
	}

	/**
	 * Get appropriate HTTP status code for error code
	 * 
	 * @param string $error_code
	 * @return int
	 */
	protected function get_error_status_code( $error_code ) {
		$status_map = array(
			'invalid_input'       => 400,
			'message_too_long'    => 400,
			'invalid_idempotency_key' => 400,
			'empty_message'       => 400,
			'invalid_content'     => 400,
			'conversation_limit_reached' => 400,
			'invalid_session'     => 401,
			'no_session'          => 401,
			'login_required'      => 401,
			'unauthorized'        => 403,
			'access_denied'       => 403,
			'ai_disabled'         => 403,
			'ai_chat_disabled'    => 403,
			'ai_search_disabled'  => 403,
			'not_found'           => 404,
			'conversation_not_found' => 404,
			'expired'             => 410,
			'conversation_expired' => 410,
			'rate_limit_exceeded' => 429,
			'user_rate_limit'     => 429,
			'global_rate_limit'   => 429,
			'version_conflict'    => 409,
			'server_error'        => 500,
			'db_error'           => 500,
			'save_failed'        => 500,
			'insert_failed'      => 500,
			'unexpected_error'   => 500,
			'service_unavailable' => 503,
			'empty_response'     => 503,
		);
		
		return isset( $status_map[ $error_code ] ) ? $status_map[ $error_code ] : 500;
	}

	/**
	 * Get standard collection parameters for list endpoints
	 * 
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page' => array(
				'description'       => __( 'Current page of the collection.', 'echo-knowledge-base' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'echo-knowledge-base' ),
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
				'maximum'           => 100,
			),
			'search' => array(
				'description'       => __( 'Limit results to those matching a string.', 'echo-knowledge-base' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby' => array(
				'description'       => __( 'Sort collection by object attribute.', 'echo-knowledge-base' ),
				'type'              => 'string',
				'default'           => 'created',
				'enum'              => array( 'id', 'created', 'modified' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order' => array(
				'description'       => __( 'Order sort attribute ascending or descending.', 'echo-knowledge-base' ),
				'type'              => 'string',
				'default'           => 'desc',
				'enum'              => array( 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}