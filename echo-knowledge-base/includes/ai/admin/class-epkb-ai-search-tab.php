<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Search tab with React implementation
 */
class EPKB_AI_Search_Tab {

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
					'ai_search_model' => array(
						'type' => 'select',
						'label' => __( 'AI Search Model', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_search_model'],
						'options' => EPKB_AI_Config_Specs::get_field_options( 'ai_search_model' ),
						'description' => __( 'Select the AI model to use for search', 'echo-knowledge-base' )
					),
					'ai_search_instructions' => array(
						'type' => 'textarea',
						'label' => __( 'AI Search Instructions', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_search_instructions'],
						'description' => __( 'Custom instructions for the AI search functionality. These instructions guide how the AI interprets and responds to search queries.', 'echo-knowledge-base' ),
						//'placeholder' => __( 'You are a helpful assistant. Avoid answering questions unrelated to given articles.', 'echo-knowledge-base' ),
						'rows' => 4
					),
					'ai_temperature' => array(
						'type' => 'number',
						'label' => __( 'AI Temperature', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_temperature'],
						'min' => 0,
						'max' => 2,
						'step' => 0.1,
						'description' => __( 'Controls randomness in AI responses. Lower values (0.0-0.5) make output more focused and deterministic. Higher values (0.5-2.0) make output more creative and varied. Default: 0.7', 'echo-knowledge-base' )
					),
					'ai_max_output_tokens' => array(
						'type' => 'number',
						'label' => __( 'Max Output Tokens', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_max_output_tokens'],
						'min' => 1,
						'max' => 16384,
						'step' => 1,
						'description' => __( 'Maximum number of tokens in the AI response. Higher values allow longer responses but consume more API credits. Default: 4096', 'echo-knowledge-base' )
					)
				)
			)
		);
	}

}