<?php defined( 'ABSPATH' ) || exit();

/**
 * AI Tuning Tab
 * 
 * Provides configuration options for fine-tuning AI behavior
 */
class EPKB_AI_Tools_Tuning_Tab {

	/**
	 * Constructor - register AJAX handlers
	 */
	public function __construct() {
		add_action( 'wp_ajax_epkb_ai_save_tuning_settings', array( __CLASS__, 'ajax_save_tuning_settings' ) );
		add_action( 'wp_ajax_epkb_ai_reset_tuning_defaults', array( __CLASS__, 'ajax_reset_tuning_defaults' ) );
	}

	/**
	 * Get tab configuration
	 *
	 * @return array
	 */
	public static function get_tab_config() {
		
		// Get chat-specific settings
		$chat_temperature = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_temperature' );
		$chat_max_output_tokens = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_max_output_tokens' );
		$chat_top_p = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_top_p' );
		$chat_model = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_model' );
		
		// Get search-specific settings
		$search_temperature = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_temperature' );
		$search_max_output_tokens = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_max_output_tokens' );
		$search_top_p = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_top_p' );
		$search_model = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_model' );
		
		// Get model specifications
		$chat_model_spec = EPKB_OpenAI_Client::get_models_and_default_params( $chat_model );
		$search_model_spec = EPKB_OpenAI_Client::get_models_and_default_params( $search_model );
		
		// Get all model specifications for UI
		$all_model_specs = EPKB_OpenAI_Client::get_models_and_default_params();
		
		$config = array(
			'tab_id' => 'tuning',
			'title' => __( 'Advanced AI Tuning', 'echo-knowledge-base' ),
			'subtitle' => __( 'Optional - For Advanced Users Only', 'echo-knowledge-base' ),
			'settings_sections' => self::get_settings_sections(),
			'chat_settings' => array(
				'ai_chat_model' => $chat_model,
				'ai_chat_temperature' => $chat_temperature,
				'ai_chat_max_output_tokens' => $chat_max_output_tokens,
				'ai_chat_top_p' => $chat_top_p,
				'ai_chat_verbosity' => EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_verbosity' ),
				'ai_chat_reasoning' => EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_reasoning' )
			),
			'search_settings' => array(
				'ai_search_model' => $search_model,
				'ai_search_temperature' => $search_temperature,
				'ai_search_max_output_tokens' => $search_max_output_tokens,
				'ai_search_top_p' => $search_top_p,
				'ai_search_verbosity' => EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_verbosity' ),
				'ai_search_reasoning' => EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_reasoning' )
			),
			'chat_model_spec' => $chat_model_spec,
			'search_model_spec' => $search_model_spec,
			'all_model_specs' => $all_model_specs
		);
		
		return $config;
	}
	
	/**
	 * Get settings sections configuration
	 *
	 * @return array
	 */
	private static function get_settings_sections() {
		
		$sections = array(
			'advanced_warning' => array(
				'title' => __( 'Advanced Configuration Notice', 'echo-knowledge-base' ),
				'description' => __( 'This section is for advanced users who want to fine-tune AI model parameters. Most users should use the preset configurations available in the Chat and Search tabs.', 'echo-knowledge-base' ),
				'type' => 'notice',
				'notice_type' => 'warning'
			),
			'chat_parameters' => array(
				'title' => __( 'AI Chat Model Parameters', 'echo-knowledge-base' ),
				'description' => __( 'Fine-tune parameters specifically for AI chat conversations', 'echo-knowledge-base' ),
				'fields' => self::get_chat_parameter_fields()
			),
			'search_parameters' => array(
				'title' => __( 'AI Search Model Parameters', 'echo-knowledge-base' ),
				'description' => __( 'Fine-tune parameters specifically for AI-enhanced search', 'echo-knowledge-base' ),
				'fields' => self::get_search_parameter_fields()
			)
		);
		
		return $sections;
	}
	
	/**
	 * Get chat parameter fields
	 * 
	 * @return array
	 */
	private static function get_chat_parameter_fields() {
		$chat_model = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_model' );
		$model_spec = EPKB_OpenAI_Client::get_models_and_default_params( $chat_model );
		
		$fields = array();
		
		// Model selection
		$fields[] = array(
			'id' => 'ai_chat_model',
			'type' => 'select',
			'label' => __( 'Chat Model', 'echo-knowledge-base' ),
			'description' => __( 'Select the AI model for chat conversations', 'echo-knowledge-base' ),
			'options' => EPKB_AI_Config_Specs::get_field_options( 'ai_chat_model' ),
			'default' => EPKB_OpenAI_Client::DEFAULT_MODEL
		);
		
		// Standard parameters for GPT-4 models
		if ( $model_spec['type'] !== 'gpt5' ) {
			$fields[] = array(
				'id' => 'ai_chat_temperature',
				'type' => 'slider',
				'label' => __( 'Temperature', 'echo-knowledge-base' ),
				'description' => __( 'Controls response creativity. Lower = more focused, Higher = more creative', 'echo-knowledge-base' ),
				'min' => 0,
				'max' => 2,
				'step' => 0.1,
				'default' => isset( $model_spec['default_params']['temperature'] ) ? $model_spec['default_params']['temperature'] : 0.2
			);
			
			$fields[] = array(
				'id' => 'ai_chat_top_p',
				'type' => 'slider',
				'label' => __( 'Top P', 'echo-knowledge-base' ),
				'description' => __( 'Controls response diversity via nucleus sampling', 'echo-knowledge-base' ),
				'min' => 0,
				'max' => 1,
				'step' => 0.1,
				'default' => isset( $model_spec['default_params']['top_p'] ) ? $model_spec['default_params']['top_p'] : 1.0
			);
		} else {
			// GPT-5 specific parameters
			$fields[] = array(
				'id' => 'ai_chat_verbosity',
				'type' => 'select',
				'label' => __( 'Verbosity', 'echo-knowledge-base' ),
				'description' => __( 'Controls response verbosity for GPT-5 models', 'echo-knowledge-base' ),
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' )
				),
				'default' => isset( $model_spec['default_params']['verbosity'] ) ? $model_spec['default_params']['verbosity'] : 'medium'
			);
			
			$fields[] = array(
				'id' => 'ai_chat_reasoning',
				'type' => 'select',
				'label' => __( 'Reasoning', 'echo-knowledge-base' ),
				'description' => __( 'Controls reasoning depth for GPT-5 models', 'echo-knowledge-base' ),
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' )
				),
				'default' => isset( $model_spec['default_params']['reasoning'] ) ? $model_spec['default_params']['reasoning'] : 'medium'
			);
		}
		
		// Max tokens - applicable to all models
		$fields[] = array(
			'id' => 'ai_chat_max_output_tokens',
			'type' => 'number',
			'label' => __( 'Max Tokens', 'echo-knowledge-base' ),
			'description' => __( 'Maximum response length in tokens', 'echo-knowledge-base' ),
			'min' => 50,
			'max' => $model_spec['max_output_tokens_limit'],
			'default' => isset( $model_spec['default_params']['max_output_tokens'] ) ? $model_spec['default_params']['max_output_tokens'] : EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS
		);
		
		return $fields;
	}
	
	/**
	 * Get search parameter fields
	 * 
	 * @return array
	 */
	private static function get_search_parameter_fields() {
		$search_model = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_model' );
		$model_spec = EPKB_OpenAI_Client::get_models_and_default_params( $search_model );
		
		$fields = array();
		
		// Model selection
		$fields[] = array(
			'id' => 'ai_search_model',
			'type' => 'select',
			'label' => __( 'Search Model', 'echo-knowledge-base' ),
			'description' => __( 'Select the AI model for search functionality', 'echo-knowledge-base' ),
			'options' => EPKB_AI_Config_Specs::get_field_options( 'ai_search_model' ),
			'default' => EPKB_OpenAI_Client::DEFAULT_MODEL
		);
		
		// Standard parameters for GPT-4 models
		if ( $model_spec['type'] !== 'gpt5' ) {
			$fields[] = array(
				'id' => 'ai_search_temperature',
				'type' => 'slider',
				'label' => __( 'Temperature', 'echo-knowledge-base' ),
				'description' => __( 'Lower values for more accurate search results', 'echo-knowledge-base' ),
				'min' => 0,
				'max' => 1,
				'step' => 0.1,
				'default' => isset( $model_spec['default_params']['temperature'] ) ? $model_spec['default_params']['temperature'] : 0.2
			);
			
			$fields[] = array(
				'id' => 'ai_search_top_p',
				'type' => 'slider',
				'label' => __( 'Top P', 'echo-knowledge-base' ),
				'description' => __( 'Controls result diversity', 'echo-knowledge-base' ),
				'min' => 0,
				'max' => 1,
				'step' => 0.1,
				'default' => isset( $model_spec['default_params']['top_p'] ) ? $model_spec['default_params']['top_p'] : 1.0
			);
		} else {
			// GPT-5 specific parameters
			$fields[] = array(
				'id' => 'ai_search_verbosity',
				'type' => 'select',
				'label' => __( 'Verbosity', 'echo-knowledge-base' ),
				'description' => __( 'Controls search result verbosity', 'echo-knowledge-base' ),
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' )
				),
				'default' => isset( $model_spec['default_params']['verbosity'] ) ? $model_spec['default_params']['verbosity'] : 'medium'
			);
			
			$fields[] = array(
				'id' => 'ai_search_reasoning',
				'type' => 'select',
				'label' => __( 'Reasoning', 'echo-knowledge-base' ),
				'description' => __( 'Controls search reasoning depth', 'echo-knowledge-base' ),
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' )
				),
				'default' => isset( $model_spec['default_params']['reasoning'] ) ? $model_spec['default_params']['reasoning'] : 'medium'
			);
		}
		
		// Max tokens - applicable to all models
		$fields[] = array(
			'id' => 'ai_search_max_output_tokens',
			'type' => 'number',
			'label' => __( 'Max Tokens', 'echo-knowledge-base' ),
			'description' => __( 'Maximum search result length in tokens', 'echo-knowledge-base' ),
			'min' => 50,
			'max' => $model_spec['max_output_tokens_limit'],
			'default' => isset( $model_spec['default_params']['max_output_tokens'] ) ? $model_spec['default_params']['max_output_tokens'] : EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS
		);
		
		return $fields;
	}
	
	/**
	 * AJAX handler to save tuning settings
	 */
	public static function ajax_save_tuning_settings() {
		
		// Verify nonce and permission
		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_ai_feature' );
		
		$settings = EPKB_Utilities::post( 'settings', array(), false, 'db-config' );
		$context = EPKB_Utilities::post( 'context', 'chat', false );  // 'chat' or 'search'
		
		// Validate and save settings based on context
		$errors = array();
		$prefix = $context === 'search' ? 'ai_search_' : 'ai_chat_';
		
		// Model selection
		$model_key = $prefix . 'model';
		if ( isset( $settings[$model_key] ) ) {

			$model = sanitize_text_field( $settings[$model_key] );
			EPKB_AI_Config_Specs::update_ai_config_value( $model_key, $model );
			
			// Get model specifications to validate other parameters
			$model_spec = EPKB_OpenAI_Client::get_models_and_default_params( $model );
			$applicable_settings = isset( $model_spec['parameters'] ) ? $model_spec['parameters'] : array();
			
			// Temperature - only for GPT-4 models
			$temp_key = $prefix . 'temperature';
			if ( isset( $settings[$temp_key] ) && isset( $model_spec['supports_temperature'] ) && $model_spec['supports_temperature'] ) {
				$temperature = floatval( $settings[$temp_key] );
				if ( $temperature < 0 || $temperature > 2 ) {
					$errors[] = __( 'Temperature must be between 0 and 2', 'echo-knowledge-base' );
				} else {
					EPKB_AI_Config_Specs::update_ai_config_value( $temp_key, $temperature );
				}
			}
			
			// Top P - only for GPT-4 models
			$top_p_key = $prefix . 'top_p';
			if ( isset( $settings[$top_p_key] ) && isset( $model_spec['supports_top_p'] ) && $model_spec['supports_top_p'] ) {
				$top_p = floatval( $settings[$top_p_key] );
				if ( $top_p < 0 || $top_p > 1 ) {
					$errors[] = __( 'Top P must be between 0 and 1', 'echo-knowledge-base' );
				} else {
					EPKB_AI_Config_Specs::update_ai_config_value( $top_p_key, $top_p );
				}
			}
			
			// Verbosity - only for GPT-5 models
			$verbosity_key = $prefix . 'verbosity';
			if ( isset( $settings[$verbosity_key] ) && isset( $model_spec['supports_verbosity'] ) && $model_spec['supports_verbosity'] ) {
				$verbosity = sanitize_text_field( $settings[$verbosity_key] );
				$allowed = array( 'low', 'medium', 'high' );
				if ( ! in_array( $verbosity, $allowed, true ) ) {
					$errors[] = __( 'Invalid verbosity value', 'echo-knowledge-base' );
				} else {
					EPKB_AI_Config_Specs::update_ai_config_value( $verbosity_key, $verbosity );
				}
			}
			
			// Reasoning - only for GPT-5 models
			$reasoning_key = $prefix . 'reasoning';
			if ( isset( $settings[$reasoning_key] ) && isset( $model_spec['supports_reasoning'] ) && $model_spec['supports_reasoning'] ) {
				$reasoning = sanitize_text_field( $settings[$reasoning_key] );
				$allowed = array( 'low', 'medium', 'high' );
				if ( ! in_array( $reasoning, $allowed, true ) ) {
					$errors[] = __( 'Invalid reasoning value', 'echo-knowledge-base' );
				} else {
					EPKB_AI_Config_Specs::update_ai_config_value( $reasoning_key, $reasoning );
				}
			}
			
			// Max Tokens - for all models
			$tokens_key = $prefix . 'max_output_tokens';
			if ( isset( $settings[$tokens_key] ) ) {
				$max_output_tokens = intval( $settings[$tokens_key] );
				$max_limit = isset( $model_spec['max_output_tokens_limit'] ) ? $model_spec['max_output_tokens_limit'] : 4000;
				
				if ( $max_output_tokens < 50 || $max_output_tokens > $max_limit ) {
					$errors[] = sprintf( __( 'Max tokens must be between 50 and %d', 'echo-knowledge-base' ), $max_limit );
				} else {
					EPKB_AI_Config_Specs::update_ai_config_value( $tokens_key, $max_output_tokens );
				}
			}
		}
		
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ', ', $errors ) ) );
			return;
		}
		
		wp_send_json_success( array( 
			'message' => sprintf( 
				__( '%s tuning settings saved successfully', 'echo-knowledge-base' ), 
				ucfirst( $context )
			) 
		) );
	}
	
	/**
	 * AJAX handler to reset tuning settings to defaults
	 */
	public static function ajax_reset_tuning_defaults() {
		
		// Verify nonce and permission
		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_ai_feature' );
		
		$context = EPKB_Utilities::post( 'context', 'chat', false );  // 'chat' or 'search'
		
		$defaults = array();
		
		if ( $context === 'search' ) {
			$settings = ['ai_search_model', 'ai_search_temperature', 'ai_search_top_p', 'ai_search_max_output_tokens', 'ai_search_verbosity', 'ai_search_reasoning'];
			foreach ( $settings as $setting ) {
				$defaults[$setting] = EPKB_AI_Config_Specs::get_default_value( $setting );
			}
		} else {
			$settings = ['ai_chat_model', 'ai_chat_temperature', 'ai_chat_top_p', 'ai_chat_max_output_tokens', 'ai_chat_verbosity', 'ai_chat_reasoning'];
			foreach ( $settings as $setting ) {
				$defaults[$setting] = EPKB_AI_Config_Specs::get_default_value( $setting );
			}
		}
		
		foreach ( $defaults as $key => $value ) {
			EPKB_AI_Config_Specs::update_ai_config_value( $key, $value );
		}
		
		wp_send_json_success( array(
			'message' => sprintf( __( '%s settings reset to defaults', 'echo-knowledge-base' ), ucfirst( $context ) )
		) );
	}
}