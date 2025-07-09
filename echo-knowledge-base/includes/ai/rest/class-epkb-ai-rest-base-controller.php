<?php defined( 'ABSPATH' ) || exit();

/**
 * Base REST API Controller for AI functionality
 * Provides common functionality for all AI REST endpoints
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
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		if ( EPKB_AI_Utilities::is_ai_enabled() ) {
			add_action( 'rest_api_init', array( $this, 'register_routes') );
		}
	}

	/**
	 * Common permission check for AI features
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	protected function check_ai_enabled( $request ) {
		
		// Check if AI features are enabled
		if ( ! EPKB_AI_Utilities::is_ai_enabled() ) {
			return new WP_Error(
				'ai_disabled',
				__( 'AI features are currently disabled.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate REST nonce
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	protected function validate_nonce( $request ) {
		
		// Validate nonce from header or request parameter
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid security token. Please refresh the page and try again.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Common rate limit check
	 * 
	 * @return bool|WP_Error
	 */
	protected function check_rate_limit() {
		
		$rate_check = EPKB_AI_Chat_Security::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				$rate_check->get_error_message(),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Check admin permissions
	 * 
	 * @param string $context
	 * @return bool|WP_Error
	 */
	protected function check_admin_permission( $context = 'admin_eckb_access_ai_features_write' ) {
		
		if ( ! EPKB_Admin_UI_Access::is_user_access_to_context_allowed( $context ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to perform this action.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get KB configuration
	 *
	 * @param int $kb_id
	 * @return array|WP_Error
	 */
	protected function get_kb_config( $kb_id = null ) {

		$kb_id = $kb_id ?: EPKB_KB_Config_DB::DEFAULT_KB_ID;
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( $kb_id );

		if ( ! $kb_config || is_wp_error( $kb_config ) ) {
			return new WP_Error(
				'config_error',
				__( 'Unable to load configuration.', 'echo-knowledge-base' ),
				array( 'status' => 500 )
			);
		}

		return $kb_config;
	}

	/**
	 * Handle WP_Error responses consistently
	 * 
	 * @param WP_Error $error
	 * @param array $context
	 * @return WP_Error
	 */
	protected function format_error_response( $error, $context = array() ) {
		
		$error_code = $error->get_error_code();
		$error_data = $error->get_error_data( $error_code );
		
		// Default status code
		$status = isset( $error_data['status'] ) ? $error_data['status'] : 500;
		
		// Map specific error codes to HTTP status codes
		$status_map = array(
			'invalid_input'       => 400,
			'message_too_long'    => 400,
			'invalid_session'     => 401,
			'no_session'          => 401,
			'login_required'      => 401,
			'access_denied'       => 403,
			'ai_disabled'         => 403,
			'ai_chat_disabled'    => 403,
			'not_found'           => 404,
			'expired'             => 410,
			'rate_limit_exceeded' => 429,
		);
		
		if ( isset( $status_map[ $error_code ] ) ) {
			$status = $status_map[ $error_code ];
		}
		
		// Add context to error data
		if ( ! empty( $context ) ) {
			$error_data = array_merge( (array) $error_data, array( 'context' => $context ) );
		}
		
		return new WP_Error(
			$error_code,
			$error->get_error_message(),
			array( 'status' => $status )
		);
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

	/**
	 * Set session cookie with proper security flags
	 *
	 * @param string $session_id
	 */
	protected function set_session_cookie( $session_id ) {
		$cookie_args = array(
			'expires'  => 0,  // Session cookie
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax'
		);

		setcookie( 'epkb_session_id', $session_id, $cookie_args );
	}
}