<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI General Settings tab with React implementation
 */
class EPKB_AI_General_Settings_Tab {

	/**
	 * Get the configuration for the General Settings tab
	 *
	 * @return array
	 */
	public static function get_tab_config() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		return array(
			'tab_id' => 'general-settings',
			'title' => __( 'General Settings', 'echo-knowledge-base' ),
			'settings_sections' => self::get_settings_sections( $ai_config ),
			'ai_config' => $ai_config
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
			'api_settings' => array(
				'id' => 'api_settings',
				'title' => __( 'API Configuration', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-key',
				'fields' => array(
					'ai_key' => array(
						'type' => 'password',
						'label' => __( 'OpenAI API Key', 'echo-knowledge-base' ),
						'value' => empty( $ai_config['ai_key'] ) ? '': '********',
						'description' => __( 'Enter your OpenAI API key. You can find it at https://platform.openai.com/api-keys', 'echo-knowledge-base' ),
						'placeholder' => 'sk-...',
						'required' => true
					),
					'ai_organization_id' => array(
						'type' => 'text',
						'label' => __( 'Organization ID', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_organization_id'],
						'description' => __( 'Optional: Enter your OpenAI Organization ID if you belong to multiple organizations', 'echo-knowledge-base' ),
						'placeholder' => 'org-...'
					)
				)
			),
			'data_privacy' => array(
				'id' => 'data_privacy',
				'title' => __( 'Data Privacy & Disclaimer', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-shield',
				'fields' => array(
					'ai_disclaimer_accepted' => array(
						'type' => 'checkbox',
						'label' => __( 'Data Privacy Acknowledgment', 'echo-knowledge-base' ),
						'value' => $ai_config['ai_disclaimer_accepted'],
						'description' => self::get_disclaimer_text()
					)
				)
			)
		);
	}

	/**
	 * Check if general settings are configured so that we move the tab to the front if not
	 *
	 * @return bool
	 */
	public static function are_settings_configured() {
		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		
		// Check if API key is set and disclaimer accepted
		$api_key_set = ! empty( $ai_config['ai_key'] );
		$disclaimer_set = ! empty( $ai_config['ai_disclaimer_accepted'] ) && $ai_config['ai_disclaimer_accepted'] === 'on';
		
		return $api_key_set && $disclaimer_set;
	}

	/**
	 * Get the disclaimer text
	 *
	 * @return string
	 */
	private static function get_disclaimer_text() {
		return sprintf(
			'<div class="epkb-ai-disclaimer-text">%s</div>',
			sprintf(
				__( '<p style="margin-bottom: 15px;">Please read our AI features privacy and security disclaimer before enabling AI features.</p>
				<p style="margin-bottom: 20px;">
					<a href="https://www.echoknowledgebase.com/privacy-security-disclaimer" target="_blank" style="color: #2271b1; text-decoration: underline; font-weight: bold;">
						View Privacy & Security Disclaimer →
					</a>
				</p>
				<label style="font-weight: bold; display: flex; align-items: center;">
					<input type="checkbox" name="ai_disclaimer_accepted" value="on" %s style="margin-right: 8px;">
					I have read and accept the privacy & security disclaimer
				</label>', 'echo-knowledge-base' ),
				checked( 'on', EPKB_AI_Config_Specs::get_ai_config_value( 'ai_disclaimer_accepted' ), false )
			)
		);
	}
}