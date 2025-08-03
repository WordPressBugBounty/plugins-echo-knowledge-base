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
				$validated_settings['ai_key'] = EPKB_Utilities::encrypt_data( $validated_settings['ai_key'] );
			}
		}

		// Update only the provided fields (partial update)
		$result = EPKB_AI_Config_Specs::update_ai_config( $validated_settings );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response([], 500, $result );
		}

		return $this->create_rest_response( array( 'success' => true, 'message' => __( 'Settings saved successfully.', 'echo-knowledge-base' ), 'settings' => $validated_settings ) );
	}
}