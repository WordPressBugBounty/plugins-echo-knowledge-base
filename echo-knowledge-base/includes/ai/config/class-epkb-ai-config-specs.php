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
			'ai_beta_code' => array(
				'name'        => 'ai_beta_code',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => '',
				'min'         => 0,
				'max'         => 50
			),

			/***  AI Search Settings ***/
			'ai_search_enabled' => array(
				'name'        => 'ai_search_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'ai_search_model' => array(
				'name'        => 'ai_search_model',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'o4-mini'       => 'o4-mini' . ' (' . 'Faster reasoning' . ')',
					'gpt-4.1-mini'  => 'GPT-4.1 mini' . ' (' . 'Balanced' . ')',
					'gpt-4.1-nano'  => 'GPT-4.1 nano' . ' (' . 'Fastest' . ')',
					'o3-mini'       => 'o3-mini' . ' (' . 'Small model' . ')',
					'gpt-4o-mini'   => 'GPT-4o mini' . ' (' . 'Fast, affordable' . ')',
					'gpt-4o'        => 'GPT-4o',
					'gpt-4-turbo'   => 'GPT-4-turbo',
				),
				'default'      => EPKB_OpenAI_Client::DEFAULT_MODEL
			),
			'ai_search_instructions' => array(
				'name'        => 'ai_search_instructions',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'You are a helpful assistant that only answers questions related to the provided content. If the answer is not available, respond with:' .
									"That is not something I can help with. Please try a different question.' Do not refer to documents, files, content, or sources. " .
									'Do not guess or answer based on general knowledge.',   // TODO translation
				'min'         => 0,
				'max'         => 1000
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
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'ai_chat_model' => array(
				'name'         => 'ai_chat_model',
				'type'         => EPKB_Input_Filter::SELECTION,
				'options'      => array(
					'o4-mini'       => 'o4-mini' . ' (' . 'Faster reasoning' . ')',
					'gpt-4.1-mini'  => 'GPT-4.1 mini' . ' (' . 'Balanced' . ')',
					'gpt-4.1-nano'  => 'GPT-4.1 nano' . ' (' . 'Fastest' . ')',
					'o3-mini'       => 'o3-mini' . ' (' . 'Small model' . ')',
					'gpt-4o-mini'   => 'GPT-4o mini' . ' (' . 'Fast, affordable' . ')',
					'gpt-4o'        => 'GPT-4o',
					'gpt-4-turbo'   => 'GPT-4-turbo',
				),
				'default'      => EPKB_OpenAI_Client::DEFAULT_MODEL
			),
			'ai_chat_instructions' => array(
				'name'        => 'ai_chat_instructions',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'You are a helpful assistant that only answers questions related to the provided content. If the answer is not available, respond with:' .
				                "That is not something I can help with. Please try a different question.' Do not refer to documents, files, content, or sources. " .
								'Do not guess or answer based on general knowledge.',
				'min'         => 0,
				'max'         => 1000
			),
			'ai_temperature' => array(
				'name'        => 'ai_temperature',
				'type'        => EPKB_Input_Filter::FLOAT_NUMBER,
				'default'     => 0.7,
				'min'         => 0.0,
				'max'         => 2.0
			),
			'ai_max_output_tokens' => array(
				'name'        => 'ai_max_output_tokens',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => 4096,
				'min'         => 1,
				'max'         => 16384
			),

			/***  AI Sync Custom Settings ***/
			'ai_auto_sync_enabled' => array(
				'name'        => 'ai_auto_sync_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),

			/***  AI Debug Settings ***/
			'ai_tools_debug_enabled' => array(
				'name'        => 'ai_tools_debug_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			)
			/* 'ai_sync_attachments' => array( TODO FUTURE
				'name'        => 'ai_sync_attachments',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			), */
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
		return parent::update_config_value( $field_name, $value );
	}

	/**
	 * Update AI configuration in database
	 * Wrapper method for backward compatibility
	 *
	 * @param array $new_config New configuration values
	 * @return array|WP_Error Updated configuration or error
	 */
	public static function update_ai_config( $new_config ) {
		return parent::update_config( $new_config );
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
}