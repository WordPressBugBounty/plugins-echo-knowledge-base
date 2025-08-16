<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Search tab
 */
class EPKB_AI_Search_Tab {

	/**
	 * Constructor - register AJAX handlers
	 */
	public function __construct() {
		add_action( 'wp_ajax_epkb_ai_apply_search_preset', array( __CLASS__, 'ajax_apply_search_preset' ) );
	}

	/**
	 * Get the configuration for the Search tab
	 *
	 * @return array
	 */
	public static function get_tab_config() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		return array(
			'tab_id' => 'search',
			'title' => __( 'Search', 'echo-knowledge-base' ),
			'sub_tabs' => self::get_sub_tabs_config(),
			'settings_sections' => self::get_settings_sections( $ai_config ),
			'ai_config' => $ai_config
		);
	}

	/**
	 * Get sub-tabs configuration
	 *
	 * @return array
	 */
	private static function get_sub_tabs_config() {
		return array(
			'search-history' => array(
				'id' => 'search-history',
				'title' => __( 'Search History', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-search'
			),
			'search-settings' => array(
				'id' => 'search-settings',
				'title' => __( 'Settings', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-cogs'
			)
		);
	}

	/**
	 * Get settings sections configuration
	 *
	 * @param array $ai_config
	 * @return array
	 */
	private static function get_settings_sections( $ai_config ) {
		
		// Get preset options for search
		$search_presets = EPKB_OpenAI_Client::get_model_presets( 'search' );
		$preset_options = array();
		foreach ( $search_presets as $key => $preset ) {
			// Add (recommended) to the balanced preset
			if ( $key === 'balanced' ) {
				$preset_options[$key] = $preset['label'] . ' ' . __( '(recommended)', 'echo-knowledge-base' ) . ' - ' . $preset['description'];
			} else {
				$preset_options[$key] = $preset['label'] . ' - ' . $preset['description'];
			}
		}
		
		// Determine current preset based on settings
		$current_preset = 'custom';
		
		// Check if current settings match any preset
		foreach ( $search_presets as $key => $preset ) {

			if ( $key == 'custom' ) {
				continue; // Skip custom preset
			}

			$matches = true;

			// Check model (always present in non-custom presets)
			if ( isset( $preset['model'] ) && $preset['model'] != $ai_config['ai_search_model'] ) {
				$matches = false;
			}

			// Check verbosity if present in preset
			if ( $matches && isset( $preset['verbosity'] ) && $preset['verbosity'] != $ai_config['ai_search_verbosity'] ) {
				$matches = false;
			}

			// Check reasoning if present in preset
			if ( $matches && isset( $preset['reasoning'] ) && $preset['reasoning'] != $ai_config['ai_search_reasoning'] ) {
				$matches = false;
			}

			// Check temperature ONLY if it's defined in the preset (non-GPT-5 models)
			if ( $matches && isset( $preset['temperature'] ) ) {
				if ( abs( floatval( $preset['temperature'] ) - floatval( $ai_config['ai_search_temperature'] ) ) >= 0.01 ) {
					$matches = false;
				}
			}

			// Check max_output_tokens if present in preset (compare as integers)
			if ( $matches && isset( $preset['max_output_tokens'] ) && intval( $preset['max_output_tokens'] ) != intval( $ai_config['ai_search_max_output_tokens'] ) ) {
				$matches = false;
			}

			// Check top_p ONLY if it's defined in the preset (non-GPT-5 models)
			if ( $matches && isset( $preset['top_p'] ) ) {
				if ( abs( floatval( $preset['top_p'] ) - floatval( $ai_config['ai_search_top_p'] ) ) >= 0.01 ) {
					$matches = false;
				}
			}

			if ( $matches ) {
				$current_preset = $key;
				break;
			}
		}
		
		return array(
			'search_settings' => array(
				'id' => 'search_settings',
				'title' => __( 'AI Search Settings', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-search',
				'fields' => array(
					'ai_search_enabled' => array(
						'type' => 'toggle',
						'label' => __( 'Enable AI Search', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_search_enabled'],
						'description' => __( 'Enable AI-enhanced search functionality', 'echo-knowledge-base' )
					),
					'ai_search_preset' => array(
						'type' => 'select',
						'label' => __( 'Choose AI Behavior', 'echo-knowledge-base' ),
						'value' => $current_preset,
						'options' => $preset_options,
						'description' => $current_preset === 'custom' ? 
							__( 'Custom model parameters are used and can be further configured in AI Advanced Tuning.', 'echo-knowledge-base' ) :
							__( 'Select an AI behavior preset that best fits your needs. All presets use the default model.', 'echo-knowledge-base' ),
						'field_class' => 'epkb-ai-behavior-preset-select'
					),
					'ai_search_instructions' => array(
						'type' => 'textarea',
						'label' => __( 'AI Search Instructions', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_search_instructions'],
						'description' => __( 'Warning: Modifying these instructions is challenging and can significantly impact AI performance. The AI is highly sensitive to instruction changes - even small modifications can cause unexpected behavior.', 'echo-knowledge-base' ),
						'rows' => 8,
						'default' => EPKB_AI_Config_Specs::get_default_value( 'ai_search_instructions' ),
						'show_reset' => true
					)
				)
			),
		);
	}

	/**
	 * AJAX handler to apply search preset
	 */
	public static function ajax_apply_search_preset() {
		
		// Verify nonce and permission
		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_ai_feature' );
		
		$preset_key = EPKB_Utilities::post( 'preset', '', false );
		
		if ( empty( $preset_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid preset selected', 'echo-knowledge-base' ) ) );
			return;
		}
		
		// Handle custom preset - no changes needed
		if ( $preset_key === 'custom' ) {
			wp_send_json_success( array( 
				'message' => __( 'Custom preset selected. Configure parameters in Advanced AI Tuning.', 'echo-knowledge-base' ),
				'redirect_to_tuning' => true
			) );
			return;
		}
		
		// Get preset parameters
		$preset = EPKB_OpenAI_Client::get_preset_parameters( $preset_key, 'search' );
		if ( ! $preset ) {
			wp_send_json_error( array( 'message' => __( 'Invalid preset configuration', 'echo-knowledge-base' ) ) );
			return;
		}
		
		// Apply preset parameters
		if ( isset( $preset['model'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_model', $preset['model'] );
		}
		if ( isset( $preset['verbosity'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_verbosity', $preset['verbosity'] );
		}
		if ( isset( $preset['reasoning'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_reasoning', $preset['reasoning'] );
		}
		if ( isset( $preset['temperature'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_temperature', $preset['temperature'] );
		}
		if ( isset( $preset['max_output_tokens'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_max_output_tokens', $preset['max_output_tokens'] );
		}
		if ( isset( $preset['top_p'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_top_p', $preset['top_p'] );
		}
		
		wp_send_json_success( array( 
			'message' => sprintf( 
				__( 'Applied "%s" preset for AI Search', 'echo-knowledge-base' ), 
				$preset['label'] 
			),
			'applied_settings' => array(
				'model' => isset( $preset['model'] ) ? $preset['model'] : null,
				'verbosity' => isset( $preset['verbosity'] ) ? $preset['verbosity'] : null,
				'reasoning' => isset( $preset['reasoning'] ) ? $preset['reasoning'] : null,
				'temperature' => isset( $preset['temperature'] ) ? $preset['temperature'] : null,
				'max_output_tokens' => isset( $preset['max_output_tokens'] ) ? $preset['max_output_tokens'] : null,
				'top_p' => isset( $preset['top_p'] ) ? $preset['top_p'] : null
			)
		) );
	}

}