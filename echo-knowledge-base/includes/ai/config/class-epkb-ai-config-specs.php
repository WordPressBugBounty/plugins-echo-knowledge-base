<?php defined( 'ABSPATH' ) || exit();

/**
 * AI Configuration Specifications
 * 
 * Defines all AI-related configuration settings with their specifications,
 * validation rules, and default values. This separates AI settings from 
 * the main KB configuration for better organization and performance.
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_AI_Config_Specs extends EPKB_AI_Config_Base {

	const OPTION_NAME = 'epkb_ai_configuration';

	/**
	 * Get all AI configuration specifications
	 *
	 * @return array
	 */
	public static function get_config_fields_specifications() {

		// Get available models from OpenAI client
		$models_data = EPKB_OpenAI_Client::get_models_and_default_params();
		$ai_models = array();
		foreach ( $models_data as $model_key => $model_info ) {
			$ai_models[$model_key] = $model_info['name'];
		}
		
		// Get default model specs for default values
		$default_model_spec = EPKB_OpenAI_Client::get_models_and_default_params( EPKB_OpenAI_Client::DEFAULT_MODEL );
		$default_params = $default_model_spec['default_params'];

		$default_instructions = 'You may ONLY answer using information from the vector store. Do not mention references, documents, files, or sources. ' .
								'Do not reveal retrieval, guess, speculate, or use outside knowledge. If no relevant information is found, reply exactly: "That is not something I can help with. Please try a different question". ' .
								'If relevant information is found, you may give structured explanations, including comparisons, pros and cons, or decision factors, but only if they are in the data. ' .
								'Answer only what the data supports; when unsure, leave it out.';

		$ai_specs = array(

			/***  AI General Settings ***/
			'ai_disclaimer_accepted' => array(
				'name'      => 'ai_disclaimer_accepted',
				'type'      => EPKB_Input_Filter::CHECKBOX,
				'default'   => 'off'
			),
			'ai_key' => array(
				'name'        => 'ai_key',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => '',
				'min'         => 20,
				'max'         => 2500
			),
			'ai_organization_id' => array(
				'name'        => 'ai_organization_id',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => '',
				'min'		  => 3,
				'max'  => 256
			),

			/***  AI Search Settings ***/
			'ai_search_enabled' => array(
				'name'        => 'ai_search_enabled',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'off'     => 'Off', // do not translate - avoid early loading errors
					'preview' => 'Preview (Admins only)',
					'on'      => 'On (Public)'
				),
				'default'     => 'off'
			),
			'ai_search_model' => array(
				'name'        => 'ai_search_model',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => $ai_models,
				'default'     => EPKB_OpenAI_Client::DEFAULT_MODEL
			),
			'ai_search_instructions' => array(
				'name'        => 'ai_search_instructions',
				'type'        => EPKB_Input_Filter::WP_EDITOR,
				'default'     => $default_instructions,
				'min'         => 0,
				'max'         => 1000
			),
			// Search-specific tuning parameters
			'ai_search_temperature' => array(
				'name'        => 'ai_search_temperature',
				'type'        => EPKB_Input_Filter::FLOAT_NUMBER,
				'default'     => isset( $default_params['temperature'] ) ? $default_params['temperature'] : 0.2,
				'min'         => 0.0,
				'max'         => 2.0
			),
			'ai_search_top_p' => array(
				'name'        => 'ai_search_top_p',
				'type'        => EPKB_Input_Filter::FLOAT_NUMBER,
				'default'     => isset( $default_params['top_p'] ) ? $default_params['top_p'] : 1.0,
				'min'         => 0.0,
				'max'         => 1.0
			),
			'ai_search_max_output_tokens' => array(
				'name'        => 'ai_search_max_output_tokens',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => isset( $default_params['max_output_tokens'] ) ? $default_params['max_output_tokens'] : EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS,
				'min'         => 500,
				'max'         => 16384
			),
			'ai_search_verbosity' => array(
				'name'        => 'ai_search_verbosity',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'low'    => 'Low',
					'medium' => 'Medium',
					'high'   => 'High',
				),
				'default'     => 'low'
			),
			'ai_search_reasoning' => array(
				'name'        => 'ai_search_reasoning',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'low'    => 'Low',
					'medium' => 'Medium',
					'high'   => 'High',
				),
				'default'     => 'low'
			),
			/* 'ai_search_location' => array(
				'name'        => 'ai_search_location',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'above_results' => esc_html'Above Search Results', 'echo-knowledge-base', TODO early loading...
					'below_results' => esc_html'Below Search Results', 'echo-knowledge-base',
					'both' => esc_html'Both Above and Below Results', 'echo-knowledge-base' )
				),
				'default'     => 'below_results'
			),
			'ai_search_display_mode' => array(
				'name'        => 'ai_search_display_mode',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'button' => esc_html'Show "Ask AI?" Button', 'echo-knowledge-base',
					'auto' => esc_html'Show AI Answer Automatically', 'echo-knowledge-base' )
				),
				'default'     => 'button'
			), */

			/***  AI Chat Settings ***/
			'ai_chat_enabled' => array(
				'name'        => 'ai_chat_enabled',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'off'     => 'Off', // do not translate - avoid early loading errors
					'preview' => 'Preview (Admins only)',
					'on'      => 'On (Public)'
				),
				'default'     => 'off'
			),
			'ai_chat_widgets' => array(
				'name'        => 'ai_chat_widgets',
				'type'        => EPKB_Input_Filter::INTERNAL_ARRAY,
				'default'     => array( 1 ),
			),
			'ai_chat_model' => array(
				'name'         => 'ai_chat_model',
				'type'         => EPKB_Input_Filter::SELECTION,
				'options'      => $ai_models,
				'default'      => EPKB_OpenAI_Client::DEFAULT_MODEL
			),
			'ai_chat_instructions' => array(
				'name'        => 'ai_chat_instructions',
				'type'        => EPKB_Input_Filter::WP_EDITOR,
				'default'     => $default_instructions,
				'min'         => 0,
				'max'         => 1000
			),
			// Chat-specific tuning parameters
			'ai_chat_temperature' => array(
				'name'        => 'ai_chat_temperature',
				'type'        => EPKB_Input_Filter::FLOAT_NUMBER,
				'default'     => isset( $default_params['temperature'] ) ? $default_params['temperature'] : 0.2,
				'min'         => 0.0,
				'max'         => 2.0
			),
			'ai_chat_top_p' => array(
				'name'        => 'ai_chat_top_p',
				'type'        => EPKB_Input_Filter::FLOAT_NUMBER,
				'default'     => isset( $default_params['top_p'] ) ? $default_params['top_p'] : 1.0,
				'min'         => 0.0,
				'max'         => 1.0
			),
			'ai_chat_max_output_tokens' => array(
				'name'        => 'ai_chat_max_output_tokens',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => isset( $default_params['max_output_tokens'] ) ? $default_params['max_output_tokens'] : EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS,
				'min'         => 500,
				'max'         => 16384
			),
			'ai_chat_verbosity' => array(
				'name'        => 'ai_chat_verbosity',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'low'    => 'Low',
					'medium' => 'Medium',
					'high'   => 'High',
				),
				'default'     => 'low'
			),
			'ai_chat_reasoning' => array(
				'name'        => 'ai_chat_reasoning',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'low'    => 'Low',
					'medium' => 'Medium',
					'high'   => 'High',
				),
				'default'     => 'low'
			),

			/***  AI Sync Custom Settings ***/
			'ai_auto_sync_enabled' => array(
				'name'        => 'ai_auto_sync_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),

			/***  AI Email Notification Settings ***/
			'ai_email_notifications_enabled' => array(
				'name'        => 'ai_email_notifications_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'ai_email_notifications_send_time' => array(
				'name'        => 'ai_email_notifications_send_time',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => '09:00',
				'min'         => 5,
				'max'         => 5
			),
			'ai_email_notifications_recipient' => array(
				'name'        => 'ai_email_notifications_recipient',
				'type'        => EPKB_Input_Filter::EMAIL,
				'default'     => '',
				'min'         => 0,
				'max'         => 100
			),
			'ai_email_notification_subject' => array(
				'name'        => 'ai_email_notification_subject',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'Daily AI Activity Summary - {site_name}',
				'min'         => 5,
				'max'         => 200
			),

			/***  AI Debug Settings ***/
			'ai_tools_debug_enabled' => array(
				'name'        => 'ai_tools_debug_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			)
		);

		return $ai_specs;
	}

	/**
	 * Get a specific AI configuration value
	 * Wrapper method for backward compatibility
	 *
	 * @param string $field_name Configuration field name
	 * @param mixed $default Default value if not found
	 * @return mixed
	 */
	public static function get_ai_config_value( $field_name, $default = null ) {
		$value = parent::get_config_value( $field_name, $default );
		
		// Mask the API key for security - only internal methods should access the real value
		if ( $field_name === 'ai_key' && ! empty( $value ) ) {
			return '********';
		}
		
		return $value;
	}

	/**
	 * Get AI configuration from database
	 * Wrapper method for backward compatibility
	 *
	 * @return array
	 */
	public static function get_ai_config() {
		$config = parent::get_config();
		
		// Mask the API key for security - only the OpenAI client should access the real value
		if ( ! empty( $config['ai_key'] ) ) {
			$config['ai_key'] = '********';
		}
		
		return $config;
	}

	/**
	 * Get field options dynamically (for fields that need late loading)
	 * Overrides parent method to provide AI-specific options
	 *
	 * @param string $field_name
	 * @return array
	 */
	public static function get_field_options( $field_name ) {
		switch ( $field_name ) {
			case 'ai_training_data_store_post_types':
				return EPKB_AI_Utilities::get_available_post_types_for_ai();
			default:
				return parent::get_field_options( $field_name );
		}
	}

	/**
	 * Update a specific AI configuration value
	 * Wrapper method for backward compatibility
	 *
	 * @param string $field_name Configuration field name
	 * @param mixed $value New value
	 * @return bool|WP_Error
	 */
	public static function update_ai_config_value( $field_name, $value ) {
		$result = parent::update_config_value( $field_name, $value );
		
		// Clear the dashboard status cache when AI config is updated
		if ( ! is_wp_error( $result ) ) {
			delete_transient( 'epkb_ai_dashboard_status' );
		}
		
		return $result;
	}

	/**
	 * Update AI configuration in database
	 * Wrapper method for backward compatibility
	 *
	 * @param array $new_config New configuration values
	 * @return array|WP_Error Updated configuration or error
	 */
	public static function update_ai_config( $original_config, $new_config ) {

		// Check if AI features are being enabled (from off to preview/on) and ensure DB tables exist
		$search_was_off = $original_config['ai_search_enabled'] == 'off';
		$search_enabled = empty( $new_config['ai_search_enabled'] ) ? $original_config['ai_search_enabled'] : $new_config['ai_search_enabled'];
		$chat_was_off = $original_config['ai_chat_enabled'] == 'off';
		$chat_enabled = empty( $new_config['ai_chat_enabled'] ) ? $original_config['ai_chat_enabled'] : $new_config['ai_chat_enabled'];

		// If either feature is being enabled from off state, ensure DB tables exist
		if ( ( $search_was_off && $search_enabled ) || ( $chat_was_off && $chat_enabled ) ) {
			// Force DB table creation by instantiating the DB classes
			new EPKB_AI_Training_Data_DB();
			new EPKB_AI_Messages_DB();
		}

		$result = parent::update_config( $new_config );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		do_action( 'eckb_ai_config_updated', $original_config, $new_config );
		
		// Clear the dashboard status cache when AI config is updated
		delete_transient( 'epkb_ai_dashboard_status' );

		return $result;
	}
	
	/**
	 * Get all AI configuration specifications
	 * Wrapper method for backward compatibility
	 *
	 * @return array
	 */
	public static function get_ai_config_fields_specifications() {
		return self::get_config_fields_specifications();
	}
	
	/**
	 * Get the unmasked API key - for internal use only
	 * This method should only be used by the OpenAI client class
	 *
	 * @return string Encrypted API key value
	 */
	public static function get_unmasked_api_key() {
		// Get directly from parent to bypass masking
		return parent::get_config_value( 'ai_key', '' );
	}
	
	/**
	 * Get the default value for a specific field
	 *
	 * @param string $field_name The field name to get default value for
	 * @return mixed The default value or null if not found
	 */
	public static function get_default_value( $field_name ) {
		$specs = self::get_config_fields_specifications();
		return isset( $specs[$field_name]['default'] ) ? $specs[$field_name]['default'] : null;
	}
}