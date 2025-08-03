<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Chat tab with React implementation
 */
class EPKB_AI_Chat_Tab {

	/**
	 * Get the configuration for the Chat tab
	 *
	 * @return array
	 */
	public static function get_tab_config() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		return array(
			'tab_id' => 'chat',
			'title' => __( 'Chat', 'echo-knowledge-base' ),
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
		return array(
			'chat_settings' => array(
				'id' => 'chat_settings',
				'title' => __( 'AI Chat Settings', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-comments',
				'fields' => array(
					'ai_chat_enabled' => array(
						'type' => 'toggle',
						'label' => __( 'Enable AI Chat', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_chat_enabled'],
						'description' => __( 'Enable AI-powered chat functionality on your knowledge base', 'echo-knowledge-base' )
					),
					'ai_chat_model' => array(
						'type' => 'select',
						'label' => __( 'AI Chat Model', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_chat_model'],
						'options' => EPKB_AI_Config_Specs::get_field_options( 'ai_chat_model' ),
						'description' => __( 'Select the AI model to use for chat conversations', 'echo-knowledge-base' )
					),
					'ai_chat_instructions' => array(
						'type' => 'textarea',
						'label' => __( 'AI Chat Instructions', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_chat_instructions'],
						'description' => __( 'Custom instructions for the AI chat assistant. These instructions guide how the AI responds to user questions.', 'echo-knowledge-base' ),
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