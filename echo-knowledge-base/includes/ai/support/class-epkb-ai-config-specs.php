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
class EPKB_AI_Config_Specs {

	const OPTION_NAME = 'epkb_ai_configuration';
	const DEFAULT_VERSION = '1.0.0';

	/**
	 * Get all AI configuration specifications
	 *
	 * @return array
	 */
	public static function get_ai_config_fields_specifications( $no_labels=false ) {

		$ai_specs = array(
			/* 'ai_api_model' => array(
				'label'        => esc_html__( 'AI Model', 'echo-knowledge-base' ),
				'name'         => 'ai_api_model',
				'type'         => EPKB_Input_Filter::SELECTION,
				'options'      => array(
					'gpt-4o-mini'   => esc_html__( 'GPT-4o-mini', 'echo-knowledge-base' ),
					'gpt-4o'        => esc_html__( 'GPT-4o', 'echo-knowledge-base' ),
					'gpt-4-turbo'   => esc_html__( 'GPT-4-turbo', 'echo-knowledge-base' ),
				),
				'default'      => EPKB_AI_Config::DEFAULT_MODEL
			), */
			/*'ai_conversation_retention_days' => array(
				'label'     => esc_html__( 'Conversation Retention Period', 'echo-knowledge-base' ),
				'name'      => 'ai_conversation_retention_days',
				'max'       => 365,
				'min'       => 0,
				'type'      => EPKB_Input_Filter::NUMBER,
				'default'   => 15
			),*/
			'ai_disclaimer_accepted' => array(
				'label'     => $no_labels ? '' : esc_html__( 'AI Disclaimer Accepted', 'echo-knowledge-base' ),
				'name'      => 'ai_disclaimer_accepted',
				'type'      => EPKB_Input_Filter::CHECKBOX,
				'default'   => 'off'
			),

			/***  AI Search Settings ***/

			'ai_search_enabled' => array(
				'label'       => $no_labels ? '' : esc_html__( 'AI Search Feature', 'echo-knowledge-base' ),
				'name'        => 'ai_search_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			/* 'ai_search_location' => array(
				'label'       => esc_html__( 'AI Search Location', 'echo-knowledge-base' ),
				'name'        => 'ai_search_location',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'above_results' => esc_html__( 'Above Search Results', 'echo-knowledge-base' ),
					'below_results' => esc_html__( 'Below Search Results', 'echo-knowledge-base' ),
					'both' => esc_html__( 'Both Above and Below Results', 'echo-knowledge-base' )
				),
				'default'     => 'below_results'
			),
			'ai_search_display_mode' => array(
				'label'       => esc_html__( 'AI Search Display Mode', 'echo-knowledge-base' ),
				'name'        => 'ai_search_display_mode',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'button' => esc_html__( 'Show "Ask AI?" Button', 'echo-knowledge-base' ),
					'auto' => esc_html__( 'Show AI Answer Automatically', 'echo-knowledge-base' )
				),
				'default'     => 'button'
			), */

			/***  AI Chat Settings ***/

			'ai_chat_enabled' => array(
				'label'       => $no_labels ? '' : esc_html__( 'AI Chat Feature', 'echo-knowledge-base' ),
				'name'        => 'ai_chat_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			
			// Beta access code - temporary setting for beta testing
			'ai_beta_access_code' => array(
				'label'       => $no_labels ? '' : esc_html__( 'Beta Access Code', 'echo-knowledge-base' ),
				'name'        => 'ai_beta_access_code',
				'type'        => EPKB_Input_Filter::TEXT,
				'min'         => '0',
				'max'         => '100',
				'default'     => ''
			),

			/***  Version and Meta ***/
			/* 'ai_config_version' => array(
				'label'       => esc_html__( 'AI Configuration Version', 'echo-knowledge-base' ),
				'name'        => 'ai_config_version',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => self::DEFAULT_VERSION,
				'internal'    => true
			), */
		);

		return $ai_specs;
	}

	/**
	 * Get default AI configuration
	 *
	 * @return array
	 */
	public static function get_default_ai_config( $no_labels=false ) {
		$default_config = array();
		$specs = self::get_ai_config_fields_specifications( $no_labels );
		
		foreach ( $specs as $field_name => $field_spec ) {
			$default_config[ $field_name ] = isset( $field_spec['default'] ) ? $field_spec['default'] : '';
		}
		
		return $default_config;
	}

	/**
	 * Get AI configuration from database
	 *
	 * @param bool $return_error Whether to return WP_Error on failure
	 * @return array
	 */
	public static function get_ai_config( $return_error = false, $no_labels=false ) { // TODO
		
		// Get the configuration from WordPress options
		$ai_config = get_option( self::OPTION_NAME, null );
		$default_config = self::get_default_ai_config( $no_labels );

		// If not found, return default configuration
		if ( $ai_config === null ) {
			// Save default configuration to database
			update_option( self::OPTION_NAME, $default_config, true ); // true for autoload
		}
		
		// Ensure all fields exist with proper defaults
		$ai_config = wp_parse_args( $ai_config, $default_config );
		
		return $ai_config;
	}

	/**
	 * Update AI configuration in database
	 *
	 * @param array $new_config New configuration values
	 * @return array|WP_Error Updated configuration or error
	 */
	public static function update_ai_config( $new_config ) {

		// Get current configuration
		$current_config = self::get_ai_config();
		
		// Get specifications
		$specs = self::get_ai_config_fields_specifications();
		
		// Validate and filter new configuration
		$validated_config = array();
		$input_filter = new EPKB_Input_Filter();    // TODO

		foreach ( $specs as $field_name => $field_spec ) {
			
			// Skip internal fields unless explicitly provided
			if ( isset( $field_spec['internal'] ) && $field_spec['internal'] && ! isset( $new_config[ $field_name ] ) ) {
				$validated_config[ $field_name ] = isset( $current_config[ $field_name ] ) ? $current_config[ $field_name ] : $field_spec['default'];
				continue;
			}
			
			// Use new value if provided, otherwise keep current value
			if ( isset( $new_config[ $field_name ] ) ) {
				$value = $new_config[ $field_name ];
				
				// Validate based on field type
				$validated_value = $input_filter->filter_input_field( $value, $field_spec );
				if ( is_wp_error( $validated_value ) ) {
					return new WP_Error( 'validation_failed', sprintf( __( 'Validation failed for field: %s', 'echo-knowledge-base' ), $field_name ) );
				}
				
				$validated_config[ $field_name ] = $validated_value;
			} else {
				$validated_config[ $field_name ] = isset( $current_config[ $field_name ] ) ? $current_config[ $field_name ] : $field_spec['default'];
			}
		}
		
		// Update version
		//$validated_config['ai_config_version'] = self::DEFAULT_VERSION;
		
		// Save to database with autoload enabled
		$result = update_option( self::OPTION_NAME, $validated_config, true );
		if ( ! $result ) {
			return new WP_Error( 'save_failed', __( 'Failed to save AI configuration', 'echo-knowledge-base' ) );
		}
		
		// Clear any caches
		wp_cache_delete( self::OPTION_NAME, 'options' );
		
		return $validated_config;
	}

	/**
	 * Get a specific AI configuration value
	 *
	 * @param string $field_name Configuration field name
	 * @param mixed $default Default value if not found
	 * @return mixed
	 */
	public static function get_ai_config_value( $field_name, $default = null, $no_labels=false ) {
		$ai_config = self::get_ai_config( false, $no_labels );
		return isset( $ai_config[ $field_name ] ) ? $ai_config[ $field_name ] : $default;
	}

	/**
	 * Update a specific AI configuration value
	 *
	 * @param string $field_name Configuration field name
	 * @param mixed $value New value
	 * @return bool|WP_Error
	 */
	public static function update_ai_config_value( $field_name, $value ) {
		$ai_config = self::get_ai_config();
		$ai_config[ $field_name ] = $value;
		
		$result = self::update_ai_config( $ai_config );

		return is_wp_error( $result ) ? $result : true;
	}
}