<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Chat admin page
 */
class EPKB_AI_Admin_Page {

	public function __construct() {
		// Initialize tabs to register AJAX handlers
		new EPKB_AI_Tools_Tab();
		new EPKB_AI_Dashboard_Tab();
		new EPKB_AI_Tools_Tuning_Tab();
	}

	// top tabs - base configuration
	private $top_tabs = array(
		'dashboard' => array(
			'title' => 'Dashboard',
			'icon' => 'epkbfa epkbfa-tachometer',
			'class' => 'EPKB_AI_Dashboard_Tab',
			'requires_ai' => false
		),
		'chat' => array(
			'title' => 'Chat',
			'icon' => 'epkbfa epkbfa-comments',
			'class' => 'EPKB_AI_Chat_Tab',
			'requires_ai' => false
		),
		'search' => array(
			'title' => 'Search',
			'icon' => 'epkbfa epkbfa-search',
			'class' => 'EPKB_AI_Search_Tab',
			'requires_ai' => false
		),
		'training-data' => array(
			'title' => 'Training Data',
			'icon' => 'epkbfa epkbfa-database',
			'class' => 'EPKB_AI_Training_Data_Tab',
			'requires_ai' => true
		),
		'tools' => array(
			'title' => 'Tools',
			'icon' => 'epkbfa epkbfa-wrench',
			'class' => 'EPKB_AI_Tools_Tab',
			'requires_ai' => true,
			'has_sub_tabs' => true
		),
		'general-settings' => array(
			'title' => 'General Settings',
			'icon' => 'epkbfa epkbfa-cogs',
			'class' => 'EPKB_AI_General_Settings_Tab',
			'requires_ai' => false
		),
		'pro-features' => array(
			'title' => 'PRO Features',
			'icon' => 'epkbfa epkbfa-star',
			'class' => 'EPKB_AI_PRO_Features_Tab',
			'requires_ai' => false
		),
	);

	// Sub-tabs configuration
	private $sub_tabs = array();

	/**
	 * Get sub-tabs configuration with translations
	 *
	 * @return array
	 */
	private function get_sub_tabs() {
		if ( empty( $this->sub_tabs ) ) {
			$this->sub_tabs = array(
				'tools' => array(
					'debug' => array(
						'id' => 'debug',
						'title' => __( 'Debug Information', 'echo-knowledge-base' ),
						'icon' => 'epkbfa epkbfa-bug'
					),
					'tuning' => array(
						'id' => 'tuning',
						'title' => __( 'Advanced AI Tuning', 'echo-knowledge-base' ),
						'icon' => 'epkbfa epkbfa-sliders',
						'description' => __( 'Optional - For advanced users only', 'echo-knowledge-base' )
					)
				)
			);
		}
		
		return $this->sub_tabs;
	}

	/**
	 * Get tabs array with dynamic ordering based on settings
	 *
	 * @return array
	 */
	private function get_ordered_tabs() {
		$tabs = $this->top_tabs;
		
		// Always keep dashboard first, but prioritize general-settings as second if not configured
		if ( ! EPKB_AI_General_Settings_Tab::are_settings_configured() ) {
			// Extract dashboard and general-settings
			$dashboard = $tabs['dashboard'];
			$general_settings = $tabs['general-settings'];
			unset( $tabs['dashboard'] );
			unset( $tabs['general-settings'] );
			
			// Reconstruct with dashboard first, general-settings second, then the rest
			$tabs = array( 'dashboard' => $dashboard, 'general-settings' => $general_settings ) + $tabs;
		}
		
		return $tabs;
	}

	/**
	 * Display the admin page
	 */
	public function display_page() {

		EPKB_Core_Utilities::display_missing_css_message();

		// Get ordered tabs
		$tabs = $this->get_ordered_tabs();
		
		// Get current tab
		$active_tab = EPKB_Utilities::get( 'active_tab', 'dashboard' );
		$active_tab = isset( $tabs[$active_tab] ) ? $active_tab : 'dashboard';

		// Pre-calculate show_get_started flag for immediate display
		// Only check if AI is enabled to avoid DB errors
		$show_get_started = !EPKB_AI_Utilities::is_ai_enabled() || !EPKB_AI_Messages_DB::has_user_used_ai();

		$react_data = array(
			'active_tab' => $active_tab,
			'tabs' => array_values( array_map( function( $key, $tab ) {
				$tab_data = array(
					'key' => $key,
					'title' => __( $tab['title'], 'echo-knowledge-base' ),
					'icon' => $tab['icon'],
					'requires_ai' => $this->tab_requires_ai( (array)$tab )
				);
				
				// Mark dashboard tab to check for issues in background
				if ( $key === 'dashboard' ) {
					$tab_data['check_status'] = true;
				}
				
				// Add sub-tabs indicator if present
				if ( ! empty( $tab['has_sub_tabs'] ) ) {
					$tab_data['has_sub_tabs'] = true;
				}
				
				return $tab_data;
			}, array_keys( $tabs ), $tabs ) ),
			'sub_tabs' => $this->get_sub_tabs(),  // Sub-tabs configuration
			'ai_enabled' => EPKB_AI_Utilities::is_ai_enabled(),  // Current AI status
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'rest_url' => rest_url( 'epkb-admin/v1/' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( '_wpnonce_epkb_ajax_action' ),
			'i18n' => $this->get_i18n_strings(),
			'tabs_data' => $this->get_all_tabs_data(),  // Pre-load all tab settings
			'are_settings_configured' => EPKB_AI_General_Settings_Tab::are_settings_configured(),
			'show_get_started' => $show_get_started  // Pre-calculated for immediate display
		);

		// Start the page output
		echo '<div class="wrap" id="epkb-admin-ai-page-wrap">'; ?>

		<h1></h1> <!-- This is here for WP admin consistency -->

		<div class="epkb-wrap">
			<div id="epkb-ai-admin-react-root" 
				 class="epkb-ai-config-page-react" 
				 data-epkb-ai-settings='<?php echo esc_attr( wp_json_encode( $react_data ) ); ?>'>
				<!-- Initial loading spinner - will be replaced when React mounts -->
				<div class="epkb-ai-loading-container" id="epkb-ai-initial-loader">
					<div class="epkb-loading-spinner"></div>
					<div class="epkb-ai-loading"><?php echo esc_html__( 'Loading AI Configuration...', 'echo-knowledge-base' ); ?></div>
				</div>
			</div>
		</div>		<?php

		echo '</div>';
	}

	/**
	 * Get internationalization strings for React
	 *
	 * @return array
	 */
	private function get_i18n_strings() {
		return array(
			'save' => __( 'Save', 'echo-knowledge-base' ),
			'saving' => __( 'Saving...', 'echo-knowledge-base' ),
			'saved' => __( 'Saved!', 'echo-knowledge-base' ),
			'error' => __( 'Error', 'echo-knowledge-base' ),
			'success' => __( 'Success', 'echo-knowledge-base' ),
			'loading' => __( 'Loading...', 'echo-knowledge-base' ),
			'confirm' => __( 'Are you sure?', 'echo-knowledge-base' ),
			'yes' => __( 'Yes', 'echo-knowledge-base' ),
			'no' => __( 'No', 'echo-knowledge-base' ),
			'cancel' => __( 'Cancel', 'echo-knowledge-base' ),
			'ok' => __( 'OK', 'echo-knowledge-base' ),
			'reset_logs' => __( 'Reset Logs', 'echo-knowledge-base' ),
			'reset_logs_confirm' => __( 'Are you sure you want to reset all AI logs?', 'echo-knowledge-base' ),
			'logs_reset_success' => __( 'AI logs have been reset successfully.', 'echo-knowledge-base' ),
			'settings_saved' => __( 'Settings saved successfully.', 'echo-knowledge-base' ),
			'settings_save_error' => __( 'Failed to save settings. Please try again.', 'echo-knowledge-base' ),
			'disclaimer_required' => __( 'Data Privacy Agreement Required', 'echo-knowledge-base' ),
			'disclaimer_message' => __( 'To use AI features, you must accept our data privacy agreement. This ensures you understand how your data will be processed by AI services.', 'echo-knowledge-base' ),
			'go_to_settings' => __( 'Go to General Settings', 'echo-knowledge-base' ),
			'privacy_policy' => __( 'View Privacy Policy', 'echo-knowledge-base' )
		);
	}

	/**
	 * Get all tabs data at once for initial page load
	 *
	 * @return array
	 */
	private function get_all_tabs_data() {
		$tabs_data = array();
		$tabs = $this->get_ordered_tabs();
		
		foreach ( $tabs as $tab_key => $tab ) {
			$tabs_data[$tab_key] = $this->get_tab_config( $tab_key, $tab );
		}
		
		return $tabs_data;
	}

	/**
	 * Get the tab configuration with AI enabled check
	 *
	 * @param string $tab_key
	 * @param array $tab
	 * @return array
	 */
	private function get_tab_config( $tab_key, $tab ) {
		// Check if this tab requires AI to be enabled
		if ( $this->tab_requires_ai( $tab ) && ! EPKB_AI_Utilities::is_ai_enabled() ) {
			return $this->get_ai_disabled_config( $tab_key, $tab['title'] );
		}
		
		// Get the tab configuration from the tab class
		$config = call_user_func( array( $tab['class'], 'get_tab_config' ) );
		
		// Add sub-tabs information if this tab has sub-tabs
		$sub_tabs = $this->get_sub_tabs();
		if ( ! empty( $tab['has_sub_tabs'] ) && isset( $sub_tabs[$tab_key] ) ) {
			$config['sub_tabs'] = $sub_tabs[$tab_key];
		}
		
		return $config;
	}

	/**
	 * Check if a tab requires AI to be enabled
	 *
	 * @param array $tab
	 * @return bool
	 */
	private function tab_requires_ai( $tab ) {
		return isset( $tab['requires_ai'] ) && $tab['requires_ai'];
	}

	/**
	 * Get configuration when AI is disabled
	 *
	 * @param string $tab_key
	 * @param string $tab_title
	 * @return array
	 */
	private function get_ai_disabled_config( $tab_key, $tab_title ) {
		return array(
			'tab_id' => $tab_key,
			'title' => __( $tab_title, 'echo-knowledge-base' ),
			'ai_disabled' => true,
			'message' => __( 'AI Features Required', 'echo-knowledge-base' ),
			'instructions' => __( 'To use AI features, please configure your API key and accept the data privacy agreement in General Settings, then enable AI Search or AI Chat.', 'echo-knowledge-base' )
		);
	}
}