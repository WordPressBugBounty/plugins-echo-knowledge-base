<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Dashboard tab with React implementation
 */
class EPKB_AI_Dashboard_Tab {
	
	public function __construct() {
		add_action( 'wp_ajax_epkb_get_ai_status', array( $this, 'ajax_get_ai_status' ) );
	}

	/**
	 * Get the configuration for the Dashboard tab
	 * This will be used by React to render the tab content
	 *
	 * @return array
	 */
	public static function get_tab_config() {
		return array(
			'tab_id' => 'dashboard',
			'title' => __( 'Dashboard', 'echo-knowledge-base' ),
			'beta_signup' => self::get_beta_signup_config(),
			'load_status_async' => true
		);
	}

	/**
	 * AJAX handler to get AI status
	 */
	public function ajax_get_ai_status() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( '_wpnonce_ai_admin_page' );

		$status = self::get_ai_status();

		wp_send_json_success( $status );
	}

	/**
	 * Get comprehensive AI status
	 *
	 * @return array Status information with issues and warnings
	 */
	private static function get_ai_status() {

		$status = array(
			'issues' => array(),
			'warnings' => array(),
			'info' => array(),
			'checks' => array()
		);
		
		// 1. Check API Key
		$api_key_status = self::check_api_key();
		$status['checks']['api_key'] = $api_key_status;
		if ( $api_key_status['status'] === 'error' ) {
			$status['issues'][] = $api_key_status;
		} elseif ( $api_key_status['status'] === 'warning' ) {
			$status['warnings'][] = $api_key_status;
		}
		
		// 2. Check Vector Store
		$vector_store_status = self::check_vector_store();
		$status['checks']['vector_store'] = $vector_store_status;
		if ( $vector_store_status['status'] === 'error' ) {
			$status['issues'][] = $vector_store_status;
		} elseif ( $vector_store_status['status'] === 'warning' ) {
			$status['warnings'][] = $vector_store_status;
		}
		
		// 3. Check Disclaimer Agreement
		$disclaimer_status = self::check_disclaimer();
		$status['checks']['disclaimer'] = $disclaimer_status;
		if ( $disclaimer_status['status'] === 'error' ) {
			$status['issues'][] = $disclaimer_status;
		}
		
		// 4. Check AI Tables
		$tables_status = self::check_ai_tables();
		$status['checks']['tables'] = $tables_status;
		if ( $tables_status['status'] === 'error' ) {
			$status['issues'][] = $tables_status;
		}
		
		// 5. Check AI Configuration
		$config_status = self::check_ai_configuration();
		$status['checks']['configuration'] = $config_status;
		if ( $config_status['status'] === 'error' ) {
			$status['issues'][] = $config_status;
		} elseif ( $config_status['status'] === 'warning' ) {
			$status['warnings'][] = $config_status;
		}
		
		// 6. Check REST API
		$rest_status = self::check_rest_api();
		$status['checks']['rest_api'] = $rest_status;
		if ( $rest_status['status'] === 'error' ) {
			$status['issues'][] = $rest_status;
		}
		
		// 7. Check Beta Code
		$beta_status = self::check_beta_code();
		$status['checks']['beta_code'] = $beta_status;
		if ( $beta_status['status'] === 'warning' ) {
			$status['warnings'][] = $beta_status;
		}
		
		// 8. Additional System Checks
		$system_checks = self::check_system_requirements();
		foreach ( $system_checks as $check ) {
			$status['checks'][$check['id']] = $check;
			if ( $check['status'] === 'error' ) {
				$status['issues'][] = $check;
			} elseif ( $check['status'] === 'warning' ) {
				$status['warnings'][] = $check;
			} elseif ( $check['status'] === 'info' ) {
				$status['info'][] = $check;
			}
		}
		
		// Calculate overall status
		if ( ! empty( $status['issues'] ) ) {
			$status['overall'] = 'error';
		} elseif ( ! empty( $status['warnings'] ) ) {
			$status['overall'] = 'warning';
		} else {
			$status['overall'] = 'success';
		}
		
		return $status;
	}
	
	/**
	 * Check API Key validity
	 *
	 * @return array Status information
	 */
	private static function check_api_key() {
		
		$encrypted_key = EPKB_AI_Config_Specs::get_unmasked_api_key();
		
		// Check if API key exists
		if ( empty( $encrypted_key ) ) {
			return array(
				'id' => 'api_key_missing',
				'status' => 'error',
				'message' => __( 'OpenAI API key is not configured', 'echo-knowledge-base' ),
				'action' => __( 'Add your API key in General Settings', 'echo-knowledge-base' ),
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' )
			);
		}
		
		// Decrypt the API key for validation
		$api_key = EPKB_Utilities::decrypt_data( $encrypted_key );
		if ( $api_key === false ) {
			return array(
				'id' => 'api_key_decrypt_failed',
				'status' => 'error',
				'message' => __( 'Failed to decrypt API key', 'echo-knowledge-base' ),
				'action' => __( 'Re-enter your API key in General Settings', 'echo-knowledge-base' ),
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' )
			);
		}
		
		// Validate API key format
		if ( ! EPKB_AI_Validation::validate_api_key_format( $api_key ) ) {
			return array(
				'id' => 'api_key_invalid_format',
				'status' => 'error',
				'message' => __( 'API key format is invalid', 'echo-knowledge-base' ),
				'action' => __( 'Check your API key format (should start with sk-)', 'echo-knowledge-base' ),
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' )
			);
		}
		
		// Check with OpenAI
		$client = new EPKB_OpenAI_Client();
		$test_result = $client->test_connection();
		if ( is_wp_error( $test_result ) ) {
			return array(
				'id' => 'api_key_invalid_openai',
				'status' => 'error',
				'message' => __( 'OpenAI does not recognize the API key', 'echo-knowledge-base' ),
				'action' => __( 'Verify your API key on OpenAI dashboard', 'echo-knowledge-base' ),
				'details' => $test_result->get_error_message(),
				'link' => 'https://platform.openai.com/api-keys'
			);
		}

		return array(
			'id' => 'api_key_valid',
			'status' => 'success',
			'message' => __( 'API key is valid', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check Vector Store existence
	 *
	 * @return array Status information
	 */
	private static function check_vector_store() {
		
		// Get all training data collections
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		$missing_stores = array();
		
		foreach ( $collections as $collection_id => $collection ) {
			if ( ! empty( $collection['ai_training_data_store_id'] ) ) {
				// Verify the store exists in OpenAI
				$handler = new EPKB_AI_OpenAI_Handler();
				$store_info = $handler->get_vector_store_info( $collection_id );
				if ( is_wp_error( $store_info ) ) {
					$missing_stores[] = array(
						'collection_id' => $collection_id,
						'store_id' => $collection['ai_training_data_store_id'],
						'collection_name' => $collection['ai_training_data_store_name']
					);
				}
			}
		}
		
		if ( ! empty( $missing_stores ) ) {
			return array(
				'id' => 'vector_store_missing',
				'status' => 'warning',
				'message' => sprintf( 
					__( '%d vector store(s) are missing in OpenAI', 'echo-knowledge-base' ), 
					count( $missing_stores ) 
				),
				'action' => __( 'Re-sync your training data to create new vector stores', 'echo-knowledge-base' ),
				'details' => $missing_stores,
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=training-data' )
			);
		}
		
		return array(
			'id' => 'vector_store_valid',
			'status' => 'success',
			'message' => __( 'All vector stores are valid', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check disclaimer agreement
	 *
	 * @return array Status information
	 */
	private static function check_disclaimer() {
		
		$disclaimer_accepted = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_disclaimer_accepted' );
		if ( $disclaimer_accepted !== 'on' ) {
			return array(
				'id' => 'disclaimer_not_accepted',
				'status' => 'error',
				'message' => __( 'AI disclaimer has not been accepted', 'echo-knowledge-base' ),
				'action' => __( 'Accept the disclaimer in General Settings', 'echo-knowledge-base' ),
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' )
			);
		}
		
		return array(
			'id' => 'disclaimer_accepted',
			'status' => 'success',
			'message' => __( 'Disclaimer has been accepted', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check AI database tables
	 *
	 * @return array Status information
	 */
	private static function check_ai_tables() {
		
		global $wpdb;
		$missing_tables = array();
		
		// List of required AI tables
		$required_tables = array(
			$wpdb->prefix . 'epkb_ai_training_data' => __( 'Training Data', 'echo-knowledge-base' ),
			$wpdb->prefix . 'epkb_ai_messages' => __( 'Chat Messages', 'echo-knowledge-base' )
		);
		
		foreach ( $required_tables as $table_name => $table_label ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 
				"SHOW TABLES LIKE %s", 
				$table_name 
			) );
			
			if ( $table_exists !== $table_name ) {
				$missing_tables[] = $table_label;
			}
		}
		
		if ( ! empty( $missing_tables ) ) {
			return array(
				'id' => 'ai_tables_missing',
				'status' => 'error',
				'message' => sprintf( 
					__( 'Missing AI database tables: %s', 'echo-knowledge-base' ), 
					implode( ', ', $missing_tables ) 
				),
				'action' => __( 'Run database upgrade to create missing tables', 'echo-knowledge-base' ),
				'details' => $missing_tables
			);
		}
		
		return array(
			'id' => 'ai_tables_valid',
			'status' => 'success',
			'message' => __( 'All AI database tables exist', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check AI configuration
	 *
	 * @return array Status information
	 */
	private static function check_ai_configuration() {
		
		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		
		if ( empty( $ai_config ) || ! is_array( $ai_config ) ) {
			return array(
				'id' => 'ai_config_missing',
				'status' => 'error',
				'message' => __( 'AI configuration is missing', 'echo-knowledge-base' ),
				'action' => __( 'Contact support - configuration needs to be initialized', 'echo-knowledge-base' )
			);
		}
		
		// Check if any AI features are enabled
		$chat_enabled = isset( $ai_config['ai_chat_enabled'] ) && $ai_config['ai_chat_enabled'] === 'on';
		$search_enabled = isset( $ai_config['ai_search_enabled'] ) && $ai_config['ai_search_enabled'] === 'on';
		$auto_sync_enabled = isset( $ai_config['ai_auto_sync_enabled'] ) && $ai_config['ai_auto_sync_enabled'] === 'on';
		
		if ( ! $chat_enabled && ! $search_enabled ) {
			return array(
				'id' => 'ai_features_disabled',
				'status' => 'warning',
				'message' => __( 'No AI features are enabled', 'echo-knowledge-base' ),
				'action' => __( 'Enable AI Chat or AI Search to use AI features', 'echo-knowledge-base' ),
				'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' )
			);
		}
		
		return array(
			'id' => 'ai_config_valid',
			'status' => 'success',
			'message' => __( 'AI configuration is valid', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check REST API availability
	 *
	 * @return array Status information
	 */
	private static function check_rest_api() {
		
		// Check if REST API is disabled via filter
		if ( apply_filters( 'rest_enabled', true ) === false ) {
			return array(
				'id' => 'rest_api_disabled_filter',
				'status' => 'error',
				'message' => __( 'REST API is disabled by a filter', 'echo-knowledge-base' ),
				'action' => __( 'Remove any filters disabling the REST API', 'echo-knowledge-base' ),
				'details' => __( 'AI features require REST API to be enabled', 'echo-knowledge-base' )
			);
		}
		
		// Check if REST API routes are available
		$rest_url = get_rest_url();
		if ( empty( $rest_url ) ) {
			return array(
				'id' => 'rest_api_url_missing',
				'status' => 'error',
				'message' => __( 'REST API URL is not available', 'echo-knowledge-base' ),
				'action' => __( 'Check permalink settings and server configuration', 'echo-knowledge-base' )
			);
		}
		
		// Check if our custom REST endpoints are registered
		$routes = rest_get_server()->get_routes();
		$our_namespace = '/epkb-ai/v1';
		$has_our_routes = false;
		
		foreach ( $routes as $route => $data ) {
			if ( strpos( $route, $our_namespace ) === 0 ) {
				$has_our_routes = true;
				break;
			}
		}
		
		if ( ! $has_our_routes ) {
			return array(
				'id' => 'rest_api_routes_missing',
				'status' => 'warning',
				'message' => __( 'AI REST API routes are not registered', 'echo-knowledge-base' ),
				'action' => __( 'Deactivate and reactivate the plugin', 'echo-knowledge-base' )
			);
		}
		
		return array(
			'id' => 'rest_api_valid',
			'status' => 'success',
			'message' => __( 'REST API is enabled and working', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check beta code validity
	 *
	 * @return array Status information
	 */
	private static function check_beta_code() {
		
		$beta_code = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_beta_code' );
		
		if ( empty( $beta_code ) ) {
			return array(
				'id' => 'beta_code_missing',
				'status' => 'info',
				'message' => __( 'No beta code configured', 'echo-knowledge-base' ),
				'action' => __( 'Add beta code if you have one', 'echo-knowledge-base' )
			);
		}
		
		// Validate beta code format/validity
		if ( ! EPKB_AI_General_Settings_Tab::has_valid_beta_code() ) {
			return array(
				'id' => 'beta_code_invalid',
				'status' => 'warning',
				'message' => __( 'Beta code is invalid', 'echo-knowledge-base' ),
				'action' => __( 'Check your beta code', 'echo-knowledge-base' )
			);
		}
		
		return array(
			'id' => 'beta_code_valid',
			'status' => 'success',
			'message' => __( 'Beta code is valid', 'echo-knowledge-base' )
		);
	}
	
	/**
	 * Check system requirements and additional issues
	 *
	 * @return array Array of status checks
	 */
	private static function check_system_requirements() {
		
		$checks = array();
		
		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
			$checks[] = array(
				'id' => 'php_version',
				'status' => 'error',
				'message' => sprintf( __( 'PHP version %s is too old', 'echo-knowledge-base' ), PHP_VERSION ),
				'action' => __( 'Upgrade to PHP 7.2 or higher', 'echo-knowledge-base' )
			);
		}
		
		// Check WordPress version
		global $wp_version;
		if ( version_compare( $wp_version, '5.3', '<' ) ) {
			$checks[] = array(
				'id' => 'wp_version',
				'status' => 'warning',
				'message' => sprintf( __( 'WordPress %s may have compatibility issues', 'echo-knowledge-base' ), $wp_version ),
				'action' => __( 'Update WordPress to 5.3 or higher', 'echo-knowledge-base' )
			);
		}
		
		// Check SSL
		if ( ! is_ssl() && ! defined( 'WP_DEBUG' ) ) {
			$checks[] = array(
				'id' => 'ssl_missing',
				'status' => 'warning',
				'message' => __( 'Site is not using SSL/HTTPS', 'echo-knowledge-base' ),
				'action' => __( 'Enable SSL for secure API communication', 'echo-knowledge-base' ),
				'details' => __( 'AI features work best with SSL enabled', 'echo-knowledge-base' )
			);
		}
		
		// Check memory limit
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory_limit < 128 * MB_IN_BYTES ) {
			$checks[] = array(
				'id' => 'memory_limit',
				'status' => 'warning',
				'message' => __( 'PHP memory limit is low', 'echo-knowledge-base' ),
				'action' => __( 'Increase memory_limit to at least 128M', 'echo-knowledge-base' ),
				'details' => sprintf( __( 'Current limit: %s', 'echo-knowledge-base' ), size_format( $memory_limit ) )
			);
		}
		
		// Check cron status
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$auto_sync = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_auto_sync_enabled' );
			if ( $auto_sync === 'on' ) {
				$checks[] = array(
					'id' => 'cron_disabled',
					'status' => 'warning',
					'message' => __( 'WP Cron is disabled', 'echo-knowledge-base' ),
					'action' => __( 'Auto-sync requires WP Cron or external cron', 'echo-knowledge-base' ),
					'details' => __( 'Set up external cron or enable WP Cron', 'echo-knowledge-base' )
				);
			}
		}
		
		// Check for conflicting plugins
		$conflicting = self::check_conflicting_plugins();
		if ( ! empty( $conflicting ) ) {
			$checks[] = array(
				'id' => 'conflicting_plugins',
				'status' => 'warning',
				'message' => __( 'Potentially conflicting plugins detected', 'echo-knowledge-base' ),
				'action' => __( 'Test AI features with these plugins disabled', 'echo-knowledge-base' ),
				'details' => implode( ', ', $conflicting )
			);
		}
		
		// Check sync status
		$sync_status = self::check_sync_status();
		if ( $sync_status !== null ) {
			$checks[] = $sync_status;
		}
		
		// Check rate limiting
		$rate_limit_status = self::check_rate_limiting();
		if ( $rate_limit_status !== null ) {
			$checks[] = $rate_limit_status;
		}
		
		return $checks;
	}
	
	/**
	 * Check for conflicting plugins
	 *
	 * @return array List of potentially conflicting plugins
	 */
	private static function check_conflicting_plugins() {
		
		$conflicting = array();
		$active_plugins = get_option( 'active_plugins', array() );
		
		// Known plugins that might conflict
		$potential_conflicts = array(
			'disable-json-api/disable-json-api.php' => 'Disable JSON API',
			'disable-rest-api/disable-rest-api.php' => 'Disable REST API',
			'wp-rest-api-controller/wp-rest-api-controller.php' => 'WP REST API Controller',
			'jwt-authentication-for-wp-rest-api/jwt-auth.php' => 'JWT Authentication'
		);
		
		foreach ( $potential_conflicts as $plugin => $name ) {
			if ( in_array( $plugin, $active_plugins ) ) {
				$conflicting[] = $name;
			}
		}
		
		return $conflicting;
	}
	
	/**
	 * Check sync status and identify issues
	 *
	 * @return array|null Status information or null if no issues
	 */
	private static function check_sync_status() {
		
		// Check if there's a stuck sync
		$sync_lock = get_transient( 'epkb_ai_sync_lock' );
		if ( $sync_lock !== false ) {
			$lock_time = get_option( 'epkb_ai_sync_lock_time', 0 );
			if ( $lock_time && ( time() - $lock_time ) > 3600 ) {
				return array(
					'id' => 'sync_stuck',
					'status' => 'warning',
					'message' => __( 'AI sync appears to be stuck', 'echo-knowledge-base' ),
					'action' => __( 'Clear sync lock in Tools tab', 'echo-knowledge-base' ),
					'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=tools' )
				);
			}
		}
		
		// Check last sync time
		$last_sync = get_option( 'epkb_ai_last_sync_completed', 0 );
		if ( $last_sync > 0 ) {
			$days_since_sync = ( time() - $last_sync ) / DAY_IN_SECONDS;
			if ( $days_since_sync > 30 ) {
				return array(
					'id' => 'sync_outdated',
					'status' => 'info',
					'message' => sprintf( 
						__( 'Last sync was %d days ago', 'echo-knowledge-base' ), 
						round( $days_since_sync ) 
					),
					'action' => __( 'Consider syncing your training data', 'echo-knowledge-base' ),
					'link' => admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=training-data' )
				);
			}
		}
		
		return null;
	}
	
	/**
	 * Check rate limiting status
	 *
	 * @return array|null Status information or null if no issues
	 */
	private static function check_rate_limiting() {
		
		// Check if rate limited
		$rate_limit_until = get_transient( 'epkb_ai_rate_limit_until' );
		if ( $rate_limit_until !== false && $rate_limit_until > time() ) {
			$minutes_left = ceil( ( $rate_limit_until - time() ) / 60 );
			return array(
				'id' => 'rate_limited',
				'status' => 'warning',
				'message' => sprintf( 
					__( 'OpenAI rate limit active for %d more minutes', 'echo-knowledge-base' ), 
					$minutes_left 
				),
				'action' => __( 'Wait for rate limit to expire', 'echo-knowledge-base' ),
				'details' => __( 'Too many requests were sent to OpenAI', 'echo-knowledge-base' )
			);
		}
		
		return null;
	}

	/**
	 * Get beta signup configuration
	 *
	 * @return array
	 */
	private static function get_beta_signup_config() {
		$current_user = wp_get_current_user();

		return array(
			'enabled' => true,
			'title' => __( 'Exciting AI Features Are Coming!', 'echo-knowledge-base' ),
			'description' => __( 'Be among the first to experience our revolutionary AI-powered search and chat capabilities. Sign up now for early access, exclusive beta testing opportunities, and special promotions!', 'echo-knowledge-base' ),
			'benefits' => array(
				array(
					'icon' => 'epkbfa epkbfa-bolt',
					'title' => __( 'Early Access', 'echo-knowledge-base' ),
					'description' => __( 'Get access to new AI features before general release', 'echo-knowledge-base' )
				),
				array(
					'icon' => 'epkbfa epkbfa-gift',
					'title' => __( 'Special Promotions', 'echo-knowledge-base' ),
					'description' => __( 'Exclusive discounts and offers for beta testers', 'echo-knowledge-base' )
				),
				array(
					'icon' => 'epkbfa epkbfa-comments',
					'title' => __( 'Shape the Future', 'echo-knowledge-base' ),
					'description' => __( 'Your feedback will help us build better AI features', 'echo-knowledge-base' )
				)
			),
			'form_fields' => array(
				'email' => array(
					'type' => 'email',
					'placeholder' => __( 'Enter your email address', 'echo-knowledge-base' ),
					'required' => true,
					'default' => $current_user->user_email
				)
			),
		);
	}
}