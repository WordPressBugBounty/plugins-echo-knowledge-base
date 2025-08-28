<?php defined( 'ABSPATH' ) || exit();

/**
 * REST API Controller for AI Admin functions
 */
class EPKB_AI_REST_Admin_Controller extends EPKB_AI_REST_Base_Controller {

	/**
	 * Register routes
	 */
	public function register_routes() {

		// Save AI settings
		register_rest_route( $this->admin_namespace, '/ai/settings', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );
	}

	/**
	 * Check admin permission
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {    // TODO
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'echo-knowledge-base' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Save AI settings
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function save_settings( $request ) {

		$settings = $request->get_param( 'settings' );
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return $this->create_rest_response( array( 'error' => 'invalid_input', 'message' => __( 'Invalid settings data provided.', 'echo-knowledge-base' ) ), 400 );
		}
		
		// Extract widget settings if present
		$widget_settings = $request->get_param( 'widget_settings' );
		$widget_id = $request->get_param( 'widget_id' );

		// Validate and sanitize settings
		$validated_settings = array();
		$specs = EPKB_AI_Config_Specs::get_ai_config_fields_specifications();
		
		foreach ( $settings as $field_name => $field_value ) {
			// Check if field exists in specs
			if ( ! isset( $specs[$field_name] ) ) {
				continue;
			}

			// Get field spec and populate dynamic options if needed
			$field_spec = $specs[$field_name];
			if ( $field_spec['type'] === EPKB_Input_Filter::CHECKBOXES_MULTI_SELECT && empty( $field_spec['options'] ) ) {
				$field_spec['options'] = EPKB_AI_Config_Specs::get_field_options( $field_name );
			}

			// Validate and sanitize based on field type
			$validated_settings[$field_name] = EPKB_AI_Config_Base::sanitize_field_value( $field_value, $field_spec );
		}

		// 'ai_key' needs to be encrypted before saving, but skip if it's the placeholder
		if ( ! empty( $validated_settings['ai_key'] ) ) {
			// If the value is our placeholder, remove it from the update (keep existing value)
			if ( $validated_settings['ai_key'] == '********' ) {
				unset( $validated_settings['ai_key'] );
            } else {
				// First validate the format
				if ( ! EPKB_AI_Validation::validate_api_key_format( $validated_settings['ai_key'] ) ) {
					return $this->create_rest_response( array( 
						'error' => 'invalid_api_key_format', 
						'message' => __( 'Invalid API key format. OpenAI API keys should start with "sk-" and contain alphanumeric characters.', 'echo-knowledge-base' ) 
					), 400 );
				}
				
				// Test the API key with OpenAI before saving
				$encrypted_test_key = EPKB_Utilities::encrypt_data( $validated_settings['ai_key'] );
				$old_key = EPKB_AI_Config_Specs::get_unmasked_api_key();
				
				// Temporarily set the new key to test it
				EPKB_AI_Config_Specs::update_ai_config_value( 'ai_key', $encrypted_test_key );
				
				$client = new EPKB_OpenAI_Client();
				$test_result = $client->test_connection();
				
				// Restore old key if test fails
				if ( is_wp_error( $test_result ) ) {
					EPKB_AI_Config_Specs::update_ai_config_value( 'ai_key', $old_key );
					return $this->create_rest_response( array( 'error' => $test_result->get_error_code(),  'message' => $test_result->get_error_message() ), 400 );
				}
				
				// Test passed, keep the encrypted key for saving
				EPKB_AI_Config_Specs::update_ai_config_value( 'ai_key', $old_key ); // Restore for now, will be saved properly below
				$validated_settings['ai_key'] = $encrypted_test_key;
			}
		}

		// Get current config before update to check if we're enabling features
		$orig_config = EPKB_AI_Config_Specs::get_ai_config();
		
		// Update only the provided fields (partial update)
		$result = EPKB_AI_Config_Specs::update_ai_config( $orig_config, $validated_settings );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response([], 500, $result );
		}

		// Check if user switched AI features from off to on
		$old_search_off = $orig_config['ai_search_enabled'] == 'off';
		$old_chat_off = $orig_config['ai_chat_enabled'] == 'off';
		$both_were_off = $old_search_off && $old_chat_off;
		
		// If both were off and now at least one is on, create tables
		if ( $both_were_off && EPKB_AI_Utilities::is_ai_enabled() ) {
			new EPKB_AI_Messages_DB();
			new EPKB_AI_Training_Data_DB();
		}
		
		// Handle widget settings if provided
		$widget_config = null;
		if ( ! empty( $widget_settings ) && is_array( $widget_settings ) ) {
			$widget_id = empty( $widget_id ) ? EPKB_AI_Chat_Widget_Config_Specs::DEFAULT_WIDGET_ID : absint( $widget_id );
			
			// Update widget configuration
			$widget_result = EPKB_AI_Chat_Widget_Config_Specs::update_widget_config( $widget_id, $widget_settings );
			if ( is_wp_error( $widget_result ) ) {
				return $this->create_rest_response( array( 
					'error' => $widget_result->get_error_code(), 
					'message' => $widget_result->get_error_message() 
				), 400 );
			}
			
			$widget_config = EPKB_AI_Chat_Widget_Config_Specs::get_widget_config( $widget_id );
		}
		
		$response_data = array( 
			'success' => true, 
			'message' => __( 'Settings saved successfully.', 'echo-knowledge-base' ), 
			'settings' => $validated_settings 
		);
		
		if ( $widget_config !== null ) {
			$response_data['widget_config'] = $widget_config;
		}

		return $this->create_rest_response( $response_data );
	}
}