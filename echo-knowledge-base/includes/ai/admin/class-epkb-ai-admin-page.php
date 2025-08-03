<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Chat admin page
 */
class EPKB_AI_Admin_Page {

	public function __construct() {
		add_action( 'wp_ajax_epkb_ai_beta_signup', array( $this, 'ajax_epkb_ai_beta_signup' ) );    // TODO
		
		// Initialize tabs to register AJAX handlers
		new EPKB_AI_Tools_Tab();
		new EPKB_AI_Dashboard_Tab();
	}

	// top tabs - base configuration
	private $base_tabs = array(
		'dashboard' => array(
			'title' => 'Dashboard',
			'icon' => 'epkbfa epkbfa-tachometer',
			'class' => 'EPKB_AI_Dashboard_Tab'
		),
		'general-settings' => array(
			'title' => 'General Settings',
			'icon' => 'epkbfa epkbfa-cogs',
			'class' => 'EPKB_AI_General_Settings_Tab'
		),
		'chat' => array(
			'title' => 'Chat',
			'icon' => 'epkbfa epkbfa-comments',
			'class' => 'EPKB_AI_Chat_Tab'
		),
		'search' => array(
			'title' => 'Search',
			'icon' => 'epkbfa epkbfa-search',
			'class' => 'EPKB_AI_Search_Tab'
		),
		'training-data' => array(
			'title' => 'Training Data',
			'icon' => 'epkbfa epkbfa-database',
			'class' => 'EPKB_AI_Training_Data_Tab'
		),
		'tools' => array(
			'title' => 'Tools',
			'icon' => 'epkbfa epkbfa-wrench',
			'class' => 'EPKB_AI_Tools_Tab'
		)
	);

	/**
	 * Get tabs array with dynamic ordering based on settings
	 *
	 * @return array
	 */
	private function get_ordered_tabs() {
		$tabs = $this->base_tabs;
		
		// Check if general settings are configured
		if ( EPKB_AI_General_Settings_Tab::are_settings_configured() ) {
			// Move general-settings to the end if settings are configured
			$general_settings = $tabs['general-settings'];
			unset( $tabs['general-settings'] );
			$tabs['general-settings'] = $general_settings;
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

		$react_data = array(
			'active_tab' => $active_tab,
			'tabs' => array_values( array_map( function( $key, $tab ) {
				$tab_data = array(
					'key' => $key,
					'title' => __( $tab['title'], 'echo-knowledge-base' ),
					'icon' => $tab['icon']
				);
				
				// Mark dashboard tab to check for issues in background
				if ( $key === 'dashboard' ) {
					$tab_data['check_status'] = true;
				}
				
				return $tab_data;
			}, array_keys( $tabs ), $tabs ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'rest_url' => rest_url( 'epkb-admin/v1/' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( '_wpnonce_epkb_ajax_action' ),
			'i18n' => $this->get_i18n_strings(),
			'tabs_data' => $this->get_all_tabs_data(),  // Pre-load all tab settings
			'has_valid_beta_code' => EPKB_AI_General_Settings_Tab::has_valid_beta_code(),
			'are_settings_configured' => EPKB_AI_General_Settings_Tab::are_settings_configured()
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
			'beta_signup_success' => __( 'Thank you for signing up for the beta!', 'echo-knowledge-base' ),
			'beta_signup_error' => __( 'Failed to sign up for beta. Please try again.', 'echo-knowledge-base' ),
			'beta_access_required' => __( 'Beta Access Required', 'echo-knowledge-base' ),
			'beta_access_message' => __( 'This feature requires beta access. Please sign up for the beta program to use AI features.', 'echo-knowledge-base' ),
			'beta_signup_button' => __( 'Sign Up for Beta', 'echo-knowledge-base' ),
			'beta_learn_more' => __( 'Learn More', 'echo-knowledge-base' ),
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
			$tabs_data[$tab_key] = call_user_func( array( $tab['class'], 'get_tab_config' ) );
		}
		
		return $tabs_data;
	}

	/**
	 * AJAX handler for beta signup
	 */
	public function ajax_epkb_ai_beta_signup() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( '_wpnonce_ai_admin_page' );

		// Get email from form
		$user_email = EPKB_Utilities::post( 'user_email' );
		if ( empty( $user_email ) || ! is_email( $user_email ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
		}

		// retrieve current user info or use email from form
		$user = EPKB_Utilities::get_current_user();
		$first_name = empty( $user ) ? 'AI Beta User' : ( empty( $user->user_firstname ) ? $user->display_name : $user->user_firstname );

		// send feedback to same endpoint as deactivation form
		$api_params = array(
			'epkb_action'       => 'epkb_process_user_feedback',
			'feedback_type'     => 'Beta Tester Sign Up',
			'feedback_input'    => 'User signed up for AI beta testing features - Email: ' . $user_email,
			'plugin_name'       => 'KB',
			'plugin_version'    => class_exists('Echo_Knowledge_Base') ? Echo_Knowledge_Base::$version : 'N/A',
			'first_version'     => '',
			'wp_version'        => '',
			'theme_info'        => '',
			'contact_user'      => $user_email . ' - ' . $first_name,
			'first_name'        => $first_name,
			'email_subject'     => 'Beta Tester Sign Up',
		);

		// Call the API
		$response = wp_remote_post(
			esc_url_raw( add_query_arg( $api_params, 'https://www.echoknowledgebase.com' ) ),
			array(
				'timeout'   => 15,
				'body'      => $api_params,
				'sslverify' => false
			)
		);

		// Check if the request was successful
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Failed to submit signup. Please try again.' ) );
		}

		wp_send_json_success( array( 'message' => 'Thank you for signing up for AI beta!' ) );
	}

}