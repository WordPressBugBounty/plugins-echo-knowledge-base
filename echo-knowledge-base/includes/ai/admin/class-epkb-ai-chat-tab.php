<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Chat tab with React implementation
 */
class EPKB_AI_Chat_Tab {

	/**
	 * Constructor - register AJAX handlers
	 */
	public function __construct() {
		add_action( 'wp_ajax_epkb_ai_apply_chat_preset', array( __CLASS__, 'ajax_apply_chat_preset' ) );
	}

	/**
	 * Get the configuration for the Chat tab
	 *
	 * @return array
	 */
	public static function get_tab_config() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		
		// Get default widget configuration
		$default_widget_config = EPKB_AI_Chat_Widget_Config_Specs::get_widget_config( 1 );

		return array(
			'tab_id' => 'chat',
			'title' => __( 'Chat', 'echo-knowledge-base' ),
			'sub_tabs' => self::get_sub_tabs_config(),
			'settings_sections' => self::get_settings_sections( $ai_config ),
			'ai_config' => $ai_config,
			'widget_config' => $default_widget_config,
			'all_widgets' => EPKB_AI_Chat_Widget_Config_Specs::get_all_widget_configs()
		);
	}

	/**
	 * Get sub-tabs configuration
	 *
	 * @return array
	 */
	private static function get_sub_tabs_config() {
		return array(
			'chat-history' => array(
				'id' => 'chat-history',
				'title' => __( 'Chat History', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-comments'
			),
			'chat-settings' => array(
				'id' => 'chat-settings',
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
		
		// Get preset options for chat
		$chat_presets = EPKB_OpenAI_Client::get_model_presets( 'chat' );
		$preset_options = array();
		foreach ( $chat_presets as $key => $preset ) {
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
		foreach ( $chat_presets as $key => $preset ) {
			if ( $key == 'custom' ) {
				continue; // Skip custom preset
			}

			$matches = true;

			// Check model (always present in non-custom presets)
			if ( isset( $preset['model'] ) && $preset['model'] != $ai_config['ai_chat_model'] ) {
				$matches = false;
			}

			// Check verbosity if present in preset
			if ( $matches && isset( $preset['verbosity'] ) && $preset['verbosity'] != $ai_config['ai_chat_verbosity'] ) {
				$matches = false;
			}

			// Check reasoning if present in preset
			if ( $matches && isset( $preset['reasoning'] ) && $preset['reasoning'] != $ai_config['ai_chat_reasoning'] ) {
				$matches = false;
			}

			// Check temperature ONLY if it's defined in the preset (non-GPT-5 models)
			if ( $matches && isset( $preset['temperature'] ) ) {
				if ( abs( floatval( $preset['temperature'] ) - floatval( $ai_config['ai_chat_temperature'] ) ) >= 0.01 ) {
					$matches = false;
				}
			}

			// Check max_output_tokens if present in preset (compare as integers)
			if ( $matches && isset( $preset['max_output_tokens'] ) && intval( $preset['max_output_tokens'] ) != intval( $ai_config['ai_chat_max_output_tokens'] ) ) {
				$matches = false;
			}

			// Check top_p ONLY if it's defined in the preset (non-GPT-5 models)
			if ( $matches && isset( $preset['top_p'] ) ) {
				if ( abs( floatval( $preset['top_p'] ) - floatval( $ai_config['ai_chat_top_p'] ) ) >= 0.01 ) {
					$matches = false;
				}
			}

			if ( $matches ) {
				$current_preset = $key;
				break;
			}
		}
		
		return array(
			'chat_settings' => array(
				'id' => 'chat_settings',
				'title' => __( 'AI Chat Settings', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-comments',
				'fields' => array(
					'ai_chat_enabled' => array(
						'type' => 'radio',
						'label' => __( 'AI Chat Mode', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_chat_enabled'],
						'options' => array(
							'off'     => __( 'Off', 'echo-knowledge-base' ),
							'preview' => __( 'Preview (Admins only)', 'echo-knowledge-base' ),
							'on'      => __( 'On (Public)', 'echo-knowledge-base' )
						),
						'description' => __( 'Control AI Chat visibility: Off (disabled), Preview (admins only for testing), or On (public access)', 'echo-knowledge-base' ),
						'field_class' => 'epkb-ai-chat-mode' // add epkb-ai-radio-vertical if you want vertical radio buttons
						
					),
					'ai_chat_preset' => array(
						'type' => 'select',
						'label' => __( 'Choose AI Behavior', 'echo-knowledge-base' ),
						'value' => $current_preset,
						'options' => $preset_options,
						'description' => $current_preset === 'custom' ? 
							__( 'Custom model parameters are used and can be further configured in AI Advanced Tuning.', 'echo-knowledge-base' ) :
							__( 'Select an AI behavior preset that best fits your needs. All presets use the default model.', 'echo-knowledge-base' ),
						'field_class' => 'epkb-ai-behavior-preset-select'
					),
					'ai_chat_instructions' => array(
						'type' => 'textarea',
						'label' => __( 'AI Chat Instructions', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_chat_instructions'],
						'description' => __( 'Warning: Modifying these instructions is challenging and can significantly impact AI performance. The AI is highly sensitive to instruction changes - even small modifications can cause unexpected behavior.', 'echo-knowledge-base' ),
						'rows' => 8,
						'default' => EPKB_AI_Config_Specs::get_default_value( 'ai_chat_instructions' ),
						'show_reset' => true
					)
				)
			),
			'default_chat_widget' => self::get_widget_settings_section()
		);
	}

	/**
	 * Get widget settings section configuration
	 *
	 * @return array
	 */
	private static function get_widget_settings_section() {
		
		// Get the default widget configuration (always returns valid config for widget 1)
		$widget_config = EPKB_AI_Chat_Widget_Config_Specs::get_widget_config( 1 );
		
		return array(
			'id' => 'default_chat_widget',
			'title' => __( 'Chat Widget Appearance', 'echo-knowledge-base' ),
			'icon' => 'epkbfa epkbfa-paint-brush',
			'fields' => array(
				/* 'widget_enabled' => array(
					'type' => 'toggle',
					'label' => __( 'Enable This Widget', 'echo-knowledge-base' ),
					'value' => isset( $widget_config['widget_enabled'] ) ? $widget_config['widget_enabled'] : 'on',
					'description' => __( 'Enable or disable this chat widget', 'echo-knowledge-base' )
				), 
				'widget_name' => array(
					'type' => 'text',
					'label' => __( 'Widget Name', 'echo-knowledge-base' ),
					'value' => $widget_config['widget_name'],
					'description' => __( 'Internal name for this chat widget configuration', 'echo-knowledge-base' )
				), */
				
				// Text Customization
				'text_section' => array(
					'type' => 'section_header',
					'label' => __( 'Text Customization', 'echo-knowledge-base' ),
					'description' => __( 'Customize widget text and messages', 'echo-knowledge-base' )
				),
				'widget_header_title' => array(
					'type' => 'text',
					'label' => __( 'Widget Header Title', 'echo-knowledge-base' ),
					'value' => $widget_config['widget_header_title'],
					'description' => __( 'Title displayed in the chat widget header', 'echo-knowledge-base' )
				),
				'input_placeholder_text' => array(
					'type' => 'text',
					'label' => __( 'Input Placeholder', 'echo-knowledge-base' ),
					'value' => $widget_config['input_placeholder_text'],
					'description' => __( 'Placeholder text in the message input field', 'echo-knowledge-base' )
				),
				'welcome_message' => array(
					'type' => 'textarea',
					'label' => __( 'Welcome Message', 'echo-knowledge-base' ),
					'value' => $widget_config['welcome_message'],
					'description' => __( 'First message shown when chat opens', 'echo-knowledge-base' ),
					'rows' => 3
				),

				// Colors
				'launcher_background_color' => array(
					'type' => 'color',
					'label' => __( 'Launcher Color', 'echo-knowledge-base' ),
					'value' => $widget_config['launcher_background_color'],
					'description' => __( 'Background color of the floating chat button', 'echo-knowledge-base' )
				),
				'widget_header_background_color' => array(
					'type' => 'color',
					'label' => __( 'Widget Header Color', 'echo-knowledge-base' ),
					'value' => $widget_config['widget_header_background_color'],
					'description' => __( 'Background color of the chat widget header', 'echo-knowledge-base' )
				),
				
				// Error Messages
				/* 'errors_section' => array(
					'type' => 'section_header',
					'label' => __( 'Error Messages', 'echo-knowledge-base' ),
					'description' => __( 'Customize error messages shown to users', 'echo-knowledge-base' )
				),
				'error_generic_message' => array(
					'type' => 'text',
					'label' => __( 'Generic Error', 'echo-knowledge-base' ),
					'value' => $widget_config['error_generic_message']
				),
				'error_network_message' => array(
					'type' => 'text',
					'label' => __( 'Network Error', 'echo-knowledge-base' ),
					'value' => $widget_config['error_network_message']
				),
				'error_timeout_message' => array(
					'type' => 'text',
					'label' => __( 'Timeout Error', 'echo-knowledge-base' ),
					'value' => $widget_config['error_timeout_message']
				),
				'error_rate_limit_message' => array(
					'type' => 'text',
					'label' => __( 'Rate Limit Error', 'echo-knowledge-base' ),
					'value' => $widget_config['error_rate_limit_message']
				), */
				
				// Reset Button
				'reset_widget_settings' => array(
					'type' => 'action_button',
					'label' => __( 'Reset Widget Settings', 'echo-knowledge-base' ),
					'button_text' => __( 'Reset to Defaults', 'echo-knowledge-base' ),
					'button_class' => 'epkb-ai-reset-widget-settings',
					'confirm_message' => __( 'Are you sure you want to reset all widget settings to their default values?', 'echo-knowledge-base' ),
					'description' => __( 'Reset all chat widget appearance and text settings to default values', 'echo-knowledge-base' )
				)
			)
		);
	}

	/**
	 * AJAX handler to apply chat preset
	 */
	public static function ajax_apply_chat_preset() {
		
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
		$preset = EPKB_OpenAI_Client::get_preset_parameters( $preset_key, 'chat' );
		if ( ! $preset ) {
			wp_send_json_error( array( 'message' => __( 'Invalid preset configuration', 'echo-knowledge-base' ) ) );
			return;
		}
		
		// Apply preset parameters
		if ( isset( $preset['model'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_model', $preset['model'] );
		}
		if ( isset( $preset['verbosity'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_verbosity', $preset['verbosity'] );
		}
		if ( isset( $preset['reasoning'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_reasoning', $preset['reasoning'] );
		}
		if ( isset( $preset['temperature'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_temperature', $preset['temperature'] );
		}
		if ( isset( $preset['max_output_tokens'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_max_output_tokens', $preset['max_output_tokens'] );
		}
		if ( isset( $preset['top_p'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_chat_top_p', $preset['top_p'] );
		}
		
		wp_send_json_success( array( 
			'message' => sprintf( 
				__( 'Applied "%s" preset for AI Chat', 'echo-knowledge-base' ), 
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