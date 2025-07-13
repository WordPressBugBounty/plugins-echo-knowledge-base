<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Admin page
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_AI_Admin_Page {

	private $vector_store_status        = 'inactive';
	private $files_status               = 'inactive';
	private $vector_store_error_message = '';
	private $files_error_message        = '';
	private $total_search_rows_number = 0;
	private $current_search_data       = [];
	private $total_chat_rows_number = 0;
	private $current_chat_data       = [];
	
	public function __construct() {
		add_action( 'wp_ajax_epkb_ai_beta_signup', [ $this, 'ajax_epkb_ai_beta_signup' ] );
		add_action( 'wp_ajax_nopriv_epkb_ai_beta_signup', [ $this, 'ajax_epkb_ai_beta_signup' ] );
		add_action( 'wp_ajax_epkb_save_ai_settings', [ $this, 'ajax_save_ai_settings' ] );
	}

	/**
	 * Displays the AI Conversations page with top panel
	 */
	public function display_page() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		// Trigger cleanup check when admin page is loaded
		do_action( 'epkb_ai_admin_page_loaded' );

		//$this->retrieve_vector_store_status();
		
		// Only retrieve chat data if beta code is valid
		$entered_code = $ai_config['ai_beta_access_code'];
		$valid_beta_code = 'EPKB-BETA-2024-AI-CHAT';
		if ( $entered_code === $valid_beta_code ) {
			$this->retrieve_messages_data( 'chat' );
		}

		//$this->retrieve_messages_data( 'search' );


		$admin_page_views = $this->get_regular_views_config();   ?>

		<!-- Admin Page Wrap -->
		<div id="ekb-admin-page-wrap">
			<div class="epkb-ai-page-container epkb-ai-admin-tabs-left">
				<div class="epkb-admin__form">			<?php
					/**
					 * ADMIN HEADER
					 */
					EPKB_HTML_Admin::admin_header( '', ['admin_eckb_access_order_articles_write', 'admin_eckb_access_frontend_editor_write'] );

					/**
					 * ADMIN TOP PANEL
					 */
					EPKB_HTML_Admin::admin_primary_tabs( $admin_page_views );

					/**
					 * Show top notification
					 */
					$this->error_notification_top();

					/**
					 * LIST OF SETTINGS IN TABS
					 */
					EPKB_HTML_Admin::admin_primary_tabs_content( $admin_page_views );             ?>
				</div>
			</div>
		</div>

		<div class="epkb-bottom-notice-message fadeOutDown"></div>  		<?php
		
		// Add JavaScript to handle navigation
		$this->output_page_scripts();
	}

	/**
	 * Get configuration array for regular views of AI Chat admin page
	 *
	 * @return array[]
	 */
	private function get_regular_views_config() {

		/**
		 * VIEW: AI Dashboard
		 */
		$views_config[] = array(

			// Shared.
			'active'     => true,
			'list_key'   => 'dashboard',

			// Top Panel Item.
			'label_text' => esc_html__( 'Dashboard', 'echo-knowledge-base' ),
			'icon_class' => 'epkbfa epkbfa-th-large',

			// Boxes List.
			'boxes_list' => $this->get_ai_dashboard_boxes(),
		);

		/**
		 * VIEW: Chat History
		 */
		$views_config[] = array(

			// Shared.
			'active'     => true,
			'list_key'   => 'chat-history',

			// Top Panel Item.
			'label_text' => esc_html__( 'Chat History', 'echo-knowledge-base' ),
			'icon_class' => 'epkbfa epkbfa-envelope-o epkb-icon--black',

			// Boxes List.
			'boxes_list' => array(

				// Split view container
				array(
					'class' => 'epkb-ai-split-view-container',
					'title' => '',
					'html' => self::get_table_page( 'chat' ),
				),
			),
		);

		/**
		 * VIEW: Searches
		 */
		/* $views_config[] = array(

			// Shared.
			'active'                => true,
			'list_key'              => 'ai-search',

			// Top Panel Item.
			'label_text'            => esc_html__( 'Searches', 'echo-knowledge-base' ),
			'icon_class'            => 'epkbfa epkbfa-search',

			//'list_top_actions_html' => self::settings_tab_actions_row(),

			'boxes_list' => array(

				// Split view container
				array(
					'class' => 'epkb-ai-split-view-container',
					'title' => '',
					'html' => self::get_table_page( 'search' ),
				),
			),
		); */

		/**
		 * VIEW: Conversations
		 */
		/* $ai_chat_view_config = array(

			// Shared.
			'active'                => true,
			'list_key'              => 'ai-chat',
			'minimum_required_capability' => EPKB_Admin_UI_Access::get_context_required_capability( 'admin_eckb_access_frontend_editor_write' ),

			// Top Panel Item.
			'label_text'            => esc_html__( 'Chat History', 'echo-knowledge-base' ),
			'icon_class'            => 'epkbfa epkbfa-comments',

			// Boxes List
			'boxes_list' => array(

				// Split view container
				array(
					'title' => esc_html__( 'AI Features Coming Soon!', 'echo-knowledge-base' ),
					'class' => 'epkb-ai__announcement-box',
					'html'  => '',
				)
			),
		); 


		$views_config[] = $ai_chat_view_config;

		/**
		 * VIEW: AI Settings
		 */
		$views_config[] = array(

			// Shared.
			'active'                => true,
			'list_key'              => 'settings',

			// Top Panel Item.
			'label_text'            => esc_html__( 'Settings', 'echo-knowledge-base' ),
			'icon_class'            => 'epkbfa epkbfa-cogs',

			'list_top_actions_html' => self::settings_tab_actions_row(),

			// Boxes List.
			'boxes_list'            => $this->get_ai_settings_boxes(),
		);

		/**
		 * VIEW: Error Log
		 */
		/* $views_config[] = array(  TODO

			// Shared.
			'active'     => true,
			'list_key'   => 'chat-error-log',

			// Top Panel Item.
			'label_text' => esc_html__( 'Error Log', 'echo-knowledge-base' ),
			'icon_class' => 'epkbfa epkbfa-info-circle',

			// Boxes List.
			'boxes_list' => $this->get_ai_error_log_boxes(),
		); */

		return $views_config;
	}

	/**
	 * Return configuration for AI Chat dashboard tab.
	 *
	 * @return array
	 */
	private function get_ai_dashboard_boxes() {

		$dashboard_form_boxes = array();

		// AI Features Announcement box
		$dashboard_form_boxes[] = array(
			'title' => esc_html__( 'AI Features Coming Soon!', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__announcement-box',
			'html'  => $this->get_ai_announcement_content(),
		);

		// Welcome message box
		/* $dashboard_form_boxes[] = array(
			'title' => esc_html__( 'Welcome to KB AI Features', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__welcome-box',
			'html'  => $this->get_dashboard_welcome_content(),
		);

		// Quick Stats box (removed Quick Actions and Content Status)
		$dashboard_form_boxes[] = array(
			'title' => esc_html__( 'AI Features Overview', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__stats-box',
			'html'  => $this->get_dashboard_stats_content(),
		);

		// AI Features box (moved to end and renamed)
		$dashboard_form_boxes[] = array(
			'title' => esc_html__( 'AI Features', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__features-box',
			'html'  => $this->get_ai_features_content(),
		); */

		return $dashboard_form_boxes;
	}

	/**
	 * Initialize AI buttons
	 *
	 * @return string
	 */
	private function get_initialize_ai_box_html() {

		$box_config = array(
			'vector-store' => array(
				'label' => __( 'Vector Store', 'echo-knowledge-base' ),
				'status' => $this->vector_store_status,
				'error_message' => $this->vector_store_error_message,
			),
			'files' => array(
				'label' => __( 'Content', 'echo-knowledge-base' ),
				'status' => $this->files_status,
				'error_message' => $this->files_error_message,
			),
		);

		ob_start();     ?>

		<div class="epkb-ai-setup-container">	<?php
			foreach ( $box_config as $setting_key => $setting_config ) {	?>
				<div class="epkb-ai-setup epkb-ai-<?php echo esc_attr( $setting_key ); ?>-setup" data-setup-status="<?php echo esc_attr( $setting_config['status'] ); ?>">
					<div class="epkb-ai-label epkb-ai-<?php echo esc_attr( $setting_key ); ?>-label"><?php echo esc_html( $setting_config['label'] ); ?></div>
					<div class="epkb-ai-status-container epkb-ai-<?php echo esc_attr( $setting_key ); ?>-status-container">
						<div class="epkb-ai-status epkb-ai-<?php echo esc_attr( $setting_key ); ?>-status">	<?php
							echo self::get_setting_status_label( $setting_config );	?>
						</div>
						<div class="epkb-ai-action epkb-ai-<?php echo esc_attr( $setting_key ); ?>-action">	<?php
							echo self::get_setting_action_button( $setting_config, $setting_key );	?>
						</div>
					</div>
				</div>	<?php
			}	?>
		</div>    <?php

		return ob_get_clean();
	}

	/**
	 * Return configuration for AI Chat settings tab.
	 *
	 * @return array
	 */
	private function get_ai_settings_boxes() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		$settings_form_boxes = array();

		// Beta Access Settings
		$settings_form_boxes[] = array(
			'title' => esc_html__( 'Beta Access', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__beta-settings',
			'html'  => $this->get_beta_access_settings_box_html( $ai_config ),
		);

		/*if ( $ai_config['ai_disclaimer_accepted'] == 'off' ) {
	        $settings_form_boxes[] = array(
		        'title' => esc_html__( 'Disclaimer', 'echo-knowledge-base' ),
		        'class' => 'epkb-ai__disclaimer-settings',
		        'html'  => $this->get_disclaimer_settings_box_html( $ai_config ),
	        );
        } */

		// Chat API settings.
		$settings_form_boxes[] = array(
			'title' => esc_html__( 'API Settings', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__api-settings',
			'html'  => $this->get_api_settings_box_html( $ai_config ),
		);

		// Set Up Chat AI - moved from Dashboard
		/* $settings_form_boxes[] = array(
			'title' => esc_html__( 'Set Up Chat AI', 'echo-knowledge-base' ),
			'html'  => $this->get_initialize_ai_box_html(),
		); */

		// AI Search Settings - moved from AI Search tab
		/* $settings_form_boxes[] = array(
			'title' => esc_html__( 'AI Search Settings', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__search-settings',
			'html'  => $this->get_ai_search_settings_box_html( $ai_config ),
		); */

		// AI Chat Settings - moved from AI Chat tab
		$settings_form_boxes[] = array(
			'title' => esc_html__( 'AI Chat Settings', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__chat-settings',
			'html'  => $this->get_ai_chat_settings_box_html( $ai_config ),
		);

		return $settings_form_boxes;
	}

	/**
	 * API settings options
	 *
	 * @return string
	 */
	private function get_api_settings_box_html( $ai_config ) {
		ob_start();

		$openai_key = EPKB_AI_Config::get_api_key();
		EPKB_HTML_Elements::text(
			array(
				'value'    		=> empty( $openai_key ) ? '' : substr( $openai_key, 0, 2 ) . '...' . substr( $openai_key, -4 ),
				'label'			=> esc_html__( 'OpenAI API Key', 'echo-knowledge-base' ),
				'name'			=> 'openai_key',
				'input_size' 	=> 'large',
				'tooltip_body'	=> esc_html__( 'Enter your OpenAI API key.', 'echo-knowledge-base' ) . ' <a href="https://beta.openai.com/account/api-keys" target="_blank" rel="noopener">' . esc_html__( 'Get OpenAI API Key', 'echo-knowledge-base' ) . '</a>',
			)
		);
		$ai_specs = EPKB_AI_Config_Specs::get_ai_config_fields_specifications();
		/* EPKB_HTML_Elements::dropdown(
			array(
				'value'         => $ai_config['ai_api_model'],
				'label'         => $ai_specs['ai_api_model']['label'],
				'name'          => 'ai_api_model',
				'options'       => $ai_specs['ai_api_model']['options'],
			)
		); */

		return ob_get_clean();
	}

	/**
	 * Disclaimer settings options
	 *
	 * @return string
	 */
	private function get_disclaimer_settings_box_html( $ai_config ) {
		ob_start();

		EPKB_HTML_Elements::checkboxes_multi_select(
			array(
				'value' => $ai_config['ai_disclaimer_accepted'] == 'on',
				'name' => 'ai_disclaimer_accepted',
				'input_group_class' => 'epkb-admin__text-field',
				'main_tag' => 'div',
				'options' => array(
					'disclaimer_read' => sprintf('<strong>%s</strong> <a href="%s" target="_blank" rel="noopener">%s</a> %s', esc_html__('I have read Disclaimer', 'echo-knowledge-base'), esc_url('https://www.echoknowledgebase.com/disclaimer/'), esc_html__('[here]', 'echo-knowledge-base'), esc_html__('in regards to usage of the AI Chat feature', 'echo-knowledge-base')),
					'confidentiality_notice' => sprintf('<strong>%s</strong> %s', esc_html__('Confidentiality Notice:', 'echo-knowledge-base'), esc_html__('Do not input or share sensitive, confidential, or legally protected information through the AI chat, as the service does not guarantee confidentiality or security; any information you provide may not remain private.', 'echo-knowledge-base')),
					'no_guarantee_of_accuracy' => sprintf('<strong>%s</strong> %s', esc_html__('No Guarantee of Accuracy:', 'echo-knowledge-base'), esc_html__('The AI chat provides information on an "as is" basis without warranties of accuracy or reliability; do not rely solely on its responses for critical decisions and always verify information independently.', 'echo-knowledge-base')),
					'disclaimer_risk_limitation' => sprintf('<strong>%s</strong> %s', esc_html__('Assumption of Risk and Limitation of Liability:', 'echo-knowledge-base'), esc_html__('By using the AI chat, you agree to use it at your own risk; we are not liable for any damages, losses, or consequences resulting from your use of the service, and you must comply with all applicable laws and regulations.', 'echo-knowledge-base'))
				)
			),
		);

		return ob_get_clean();
	}

	/**
	 * AI Search settings options
	 *
	 * @param $ai_config
	 * @return string
	 */
	private function get_ai_search_settings_box_html( $ai_config ) {
		ob_start();

		// AI Search enabled toggle
		EPKB_HTML_Elements::checkbox_toggle( array(
			'name'              => 'ai_search_enabled',
			'text'             => esc_html__( 'AI Search Feature', 'echo-knowledge-base' ),
			'checked'           => ! empty( $ai_config['ai_search_enabled'] ) && $ai_config['ai_search_enabled'] === 'on',
			'input_group_class' => 'epkb-admin__input-field',
			'label_class'       => 'epkb-admin__input-label',
			//'input_class'       => '',
		) );

		return ob_get_clean();
	}

	private function get_ai_chat_settings_box_html( $ai_config ) {
		ob_start();
	
		// AI Chat enabled toggle
		EPKB_HTML_Elements::checkbox_toggle( array(
			'name'              => 'ai_chat_enabled',
			'text'             => esc_html__( 'AI Chat Feature', 'echo-knowledge-base' ),
			'checked'           => ! empty( $ai_config['ai_chat_enabled'] ) && $ai_config['ai_chat_enabled'] === 'on',
			'input_group_class' => 'epkb-admin__input-field',
			'label_class'       => 'epkb-admin__input-label',
		) );

		return ob_get_clean();
	}
	
	/**
	 * Beta Access settings options
	 *
	 * @param $ai_config
	 * @return string
	 */
	private function get_beta_access_settings_box_html( $ai_config ) {
		ob_start();
		
		// Beta access code input
		EPKB_HTML_Elements::text( array(
			'value'             => isset( $ai_config['ai_beta_access_code'] ) ? $ai_config['ai_beta_access_code'] : '',
			'label'             => esc_html__( 'Beta Access Code', 'echo-knowledge-base' ),
			'name'              => 'ai_beta_access_code',
			'input_size'        => 'medium',
			'tooltip_body'      => esc_html__( 'Enter the beta access code to unlock AI Chat features for testing.', 'echo-knowledge-base' ),
			'input_group_class' => 'epkb-admin__input-field',
			'label_class'       => 'epkb-admin__input-label',
		) );
		
		return ob_get_clean();
	}

	/**
	 * Retrieve Vector Store status; leave it as inactive if not set up.
	 */
	private function retrieve_vector_store_status() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		$vector_store_service = new EPKB_AI_Vector_Store_Service();

		// get Vector Store status.
		$vector_store_id = $ai_config['ai_vector_store_id'];
		if ( ! empty( $vector_store_id ) ) {
			$vector_store = $vector_store_service->get_vector_store( $vector_store_id );

			if ( is_wp_error( $vector_store ) ) {
				$error_data = $vector_store->get_error_data();

				// Access specific data, like response code and error code.
				$response_code = isset( $error_data['response_code'] ) ? $error_data['response_code'] : null;
				// It was 'not found' on openai server error.
				if ( $response_code === 404 ) {
					$this->vector_store_status = 'missing';
				} else {
					$this->vector_store_status        = 'error';
					// Log error internally but don't expose details to users
					EPKB_AI_Utilities::add_log( 'Vector store error: ' . $vector_store->get_error_message() );
					$this->vector_store_error_message = esc_html__( 'Unable to connect to vector store. Please check your settings.', 'echo-knowledge-base' );
				}
			} else if ( is_null( $vector_store ) ) {
				// Vector store not found
				$this->vector_store_status = 'missing';
			} else {
				$this->vector_store_status = $vector_store['status'] === 'completed' ? 'active' : $vector_store['status'];
			}
		}

		// check if any files are attached to the vector store.
		if ( ! empty( $vector_store_id ) ) {
			$vector_store_files = $vector_store_service->get_vector_store_files( $vector_store_id );

			if ( is_wp_error( $vector_store_files ) ) {
				$this->files_status        = 'error';
				// Log error internally but don't expose details to users
				EPKB_AI_Utilities::add_log( 'Vector store files error: ' . $vector_store_files->get_error_message() );
				$this->files_error_message = esc_html__( 'Unable to retrieve file information. Please check your settings.', 'echo-knowledge-base' );
			} elseif ( ! empty( $vector_store_files['data'] ) ) {
				// only mark active if it has some files.
				$this->files_status = 'active';
			} else {
				$this->files_status = 'inactive';
			}
		}
	}

	/**
	 * Retrieve chat conversations data for the table
	 */
	private function retrieve_messages_data( $mode ) {

		$messages_db = new EPKB_AI_Messages_DB();

		// Get conversations for search mode
		$args = array(
			'page' => 1,
			'per_page' => EPKB_AI_Messages_DB::PER_PAGE,
			'mode' => $mode,
			'orderby' => 'created',
			'order' => 'DESC'
		);

		$conversations = $messages_db->get_conversations( $args );
		$total_rows_number = $messages_db->get_conversations_count( array( 'mode' => $mode ) );
		if ( $mode == 'chat' ) {
			$this->total_chat_rows_number = $total_rows_number;
			$this->current_chat_data = array();
		} else {
			$this->total_search_rows_number = $total_rows_number;
			$this->current_search_data = array();
		}

		// Format conversations for table display
		$current_messages_data = array();
		foreach ( $conversations as $conversation ) {
			$messages = $conversation->get_messages();
			$first_message = ! empty( $messages ) ? $messages[0] : null;
			$meta = $conversation->get_meta();

			// Determine user display
			$user_display = __( 'Visitor', 'echo-knowledge-base' );
			if ( $conversation->get_user_id() > 0 ) {
				$user = get_user_by( 'id', $conversation->get_user_id() );
				if ( $user ) {
					$user_display = '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
				}
			}

			$conversation_data = array(
				'conversation_id' => $conversation->get_id(),
				'submit_date' => $conversation->get_created(),
				'name' => $user_display,
				'first_message' => $first_message && isset( $first_message['content'] ) ? wp_trim_words( $first_message['content'], 10 ) : '',
			);

			// Add mode-specific fields
			if ( $mode === 'chat' ) {
				$conversation_data['chat_id'] = $conversation->get_chat_id();
			} else {
				$conversation_data['page_name'] = ! empty( $meta['page_title'] ) ? $meta['page_title'] : '';
				$conversation_data['status'] = ! empty( $meta['status'] ) ? $meta['status'] : __( 'Completed', 'echo-knowledge-base' );
			}

			$current_messages_data[] = $conversation_data;
		}

		if ( $mode == 'chat' ) {
			$this->current_chat_data = $current_messages_data;
		} else {
			$this->current_search_data = $current_messages_data;
		}
	}
	
	/**
	 * Get conversations table
	 *
	 * @return false|string
	 */
	private function get_messages_table( $mode ) {

		ob_start();

		// Define table headings based on mode
		if ( $mode === 'chat' ) {
			$headings = [
				'submit_date'  => esc_html__( 'Time', 'echo-knowledge-base' ),
				'name' => esc_html__( 'User', 'echo-knowledge-base' ),
				'chat_id' => esc_html__( 'Chat ID', 'echo-knowledge-base' ),
				'first_message' => esc_html__( 'Question', 'echo-knowledge-base' ),
			];
		} else {
			$headings = [
				'submit_date'  => esc_html__( 'Time', 'echo-knowledge-base' ),
				'name' => esc_html__( 'User', 'echo-knowledge-base' ),
				'first_message' => esc_html__( 'First Message', 'echo-knowledge-base' ),
				'page_name' => esc_html__( 'Page', 'echo-knowledge-base' ),
				'status' => esc_html__( 'Status', 'echo-knowledge-base' ),
			];
		}

		// Define sortable columns
		$sortable_columns = ['submit_date', 'name', 'page_name', 'status'];

		// Calculate total pages
		$rows_per_page = EPKB_AI_Messages_DB::PER_PAGE;
		$total_pages = ceil( ( $mode == 'search' ) ? $this->total_search_rows_number : $this->total_chat_rows_number / $rows_per_page );

		// Get current page
		$page = isset( $_GET['page_number'] ) ? (int)$_GET['page_number'] : 1;

		// Instantiate the table class
		$table = new EPKB_UI_Table(
			'epkb-' . $mode . '-conversations-table',     // Unique table ID
			$rows_per_page,               // Rows per page
			$headings,                    // Table headings
			( $mode == 'search' ) ? $this->current_search_data : $this->current_chat_data,   // Data for the table
			$sortable_columns,            // Sortable columns
			true,                         // Enable checkboxes
			'conversation_id',              // Specify the key for the row ID
			$total_pages,                 // Total pages
			$page,                        // Current page
			( $mode == 'search' ) ? $this->total_search_rows_number : $this->total_chat_rows_number, // Total rows
			[],                           // Filter fields
			false                         // Disable actions column
		);

		// Render the table
		$table->render();

		return ob_get_clean();
	}

	/**
	 * Get dashboard welcome content
	 *
	 * @return string
	 */
	private function get_dashboard_welcome_content() {
		ob_start(); ?>

		<div class="epkb-admin__welcome-content">
			<div style="text-align: center; padding: 20px;">
				<h2 style="margin-bottom: 20px; color: #2c3338; font-size: 24px;">
					<?php esc_html_e( 'Enhance Your Knowledge Base with AI', 'echo-knowledge-base' ); ?>
				</h2>
				<p style="font-size: 16px; color: #50575e; max-width: 800px; margin: 0 auto 30px;">
					<?php esc_html_e( 'Welcome to the AI Features section! Here you can manage AI-powered search and chat functionality to provide your users with instant, intelligent assistance.', 'echo-knowledge-base' ); ?>
				</p>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
				<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<span class="epkbfa epkbfa-search" style="font-size: 24px; color: #2271b1; margin-right: 10px;"></span>
						<h3 style="margin: 0; color: #2c3338;"><?php esc_html_e( 'AI Search', 'echo-knowledge-base' ); ?></h3>
					</div>
					<p style="margin: 0 0 15px 0; color: #50575e;">
						<?php esc_html_e( 'Provide intelligent search results powered by AI that understands user intent and delivers the most relevant articles.', 'echo-knowledge-base' ); ?>
					</p>
					<a href="#" class="epkb-primary-btn" style="text-decoration: none;" data-target="ai-search">
						<?php esc_html_e( 'View AI Search', 'echo-knowledge-base' ); ?>
					</a>
				</div>

				<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<span class="epkbfa epkbfa-comments" style="font-size: 24px; color: #2271b1; margin-right: 10px;"></span>
						<h3 style="margin: 0; color: #2c3338;"><?php esc_html_e( 'AI Chat', 'echo-knowledge-base' ); ?></h3>
					</div>
					<p style="margin: 0 0 15px 0; color: #50575e;">
						<?php esc_html_e( 'Enable an AI-powered chat assistant that can answer questions based on your knowledge base content.', 'echo-knowledge-base' ); ?>
					</p>
					<a href="#" class="epkb-primary-btn" style="text-decoration: none;" data-target="ai-chat">
						<?php esc_html_e( 'View AI Chat', 'echo-knowledge-base' ); ?>
					</a>
				</div>

				<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<span class="epkbfa epkbfa-cogs" style="font-size: 24px; color: #2271b1; margin-right: 10px;"></span>
						<h3 style="margin: 0; color: #2c3338;"><?php esc_html_e( 'Settings', 'echo-knowledge-base' ); ?></h3>
					</div>
					<p style="margin: 0 0 15px 0; color: #50575e;">
						<?php esc_html_e( 'Configure API keys, models, and other settings to customize AI features for your needs.', 'echo-knowledge-base' ); ?>
					</p>
					<a href="#" class="epkb-primary-btn" style="text-decoration: none;" data-target="settings">
						<?php esc_html_e( 'Configure Settings', 'echo-knowledge-base' ); ?>
					</a>
				</div>
			</div>
		</div>		<?php

		return ob_get_clean();
	}

	/**
	 * Get dashboard stats content
	 *
	 * @return string
	 */
	private function get_dashboard_stats_content() {
		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		
		ob_start(); ?>

		<div class="epkb-admin__stats-content">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">

				<div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #50575e; font-weight: normal;"><?php esc_html_e( 'AI Chat', 'echo-knowledge-base' ); ?></h4>
					<p style="font-size: 24px; margin: 0; color: <?php echo ( ! empty( $ai_config['ai_chat_enabled'] ) && $ai_config['ai_chat_enabled'] === 'on' ) ? '#46b450' : '#dc3232'; ?>;">
						<?php if ( ! empty( $ai_config['ai_chat_enabled'] ) && $ai_config['ai_chat_enabled'] === 'on' ) : ?>
							<span class="epkbfa epkbfa-check-circle"></span> <?php esc_html_e( 'Enabled', 'echo-knowledge-base' ); ?>
						<?php else : ?>
							<span class="epkbfa epkbfa-times-circle"></span> <?php esc_html_e( 'Disabled', 'echo-knowledge-base' ); ?>
						<?php endif; ?>
					</p>
				</div>

				<div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7; text-align: center;">
					<h4 style="margin: 0 0 10px 0; color: #50575e; font-weight: normal;"><?php esc_html_e( 'Total AI Conversations', 'echo-knowledge-base' ); ?></h4>
					<p style="font-size: 24px; margin: 0; color: #2c3338;">
						<?php echo esc_html( number_format( $this->total_chat_rows_number ) ); ?>
					</p>
				</div>
			</div>
		</div>		<?php

		return ob_get_clean();
	}

	/**
	 * Get AI announcement content for beta testing signup
	 *
	 * @return string
	 */
	private function get_ai_announcement_content() {
		ob_start(); ?>

		<div class="epkb-ai-announcement-content" style="text-align: center; padding: 30px;">
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 12px; margin-bottom: 30px;">
				<h2 style="margin: 0 0 20px 0; font-size: 32px; color: white;">
					<span class="epkbfa epkbfa-rocket" style="margin-right: 10px;"></span>
					<?php esc_html_e( 'Exciting AI Features Are Coming!', 'echo-knowledge-base' ); ?>
				</h2>
				<p style="font-size: 18px; margin: 0 0 30px 0; color: white; opacity: 0.95;">
					<?php esc_html_e( 'Be among the first to experience our revolutionary AI-powered search and chat capabilities. Sign up now for early access, exclusive beta testing opportunities, and special promotions!', 'echo-knowledge-base' ); ?>
				</p>
				
				<!-- AI Beta Signup Form -->
				<form id="epkb-ai-beta-signup-form" action="javascript:void(0);" style="display: inline-block; max-width: 400px; margin: 0 auto;">
					<div class="epkb-ai-signup-form-wrap" style="display: flex; align-items: center; background: white; border-radius: 30px; padding: 5px; margin-bottom: 15px;">
						<input 
							type="email" 
							name="user_email" 
							placeholder="<?php esc_attr_e( 'Enter your email address', 'echo-knowledge-base' ); ?>" 
							required 
							style="flex: 1; border: none; outline: none; padding: 10px 20px; background: transparent; color: #333; font-size: 16px;"
						/>
						<button 
							type="button" 
							class="epkb-ai-signup-submit-btn" 
							style="background: #667eea; color: white; border: none; padding: 10px 25px; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s ease;"
						>
							<span class="epkbfa epkbfa-envelope" style="margin-right: 8px;"></span>
							<?php esc_html_e( 'Join Beta', 'echo-knowledge-base' ); ?>
						</button>
					</div>
					<div class="epkb-ai-signup-message" style="color: white; font-size: 14px; min-height: 20px;"></div>
					<input type="hidden" name="action" value="epkb_ai_beta_signup" />
					<?php wp_nonce_field( '_epkb_ai_beta_signup_nonce' ); ?>
				</form>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
				<div style="background: #f0f6ff; padding: 20px; border-radius: 8px; border: 1px solid #d0e2ff;">
					<span class="epkbfa epkbfa-bolt" style="font-size: 32px; color: #667eea; display: block; margin-bottom: 10px;"></span>
					<h4 style="margin: 0 0 10px 0; color: #2c3338;"><?php esc_html_e( 'Early Access', 'echo-knowledge-base' ); ?></h4>
					<p style="margin: 0; color: #50575e;"><?php esc_html_e( 'Get access to new AI features before general release', 'echo-knowledge-base' ); ?></p>
				</div>
				<div style="background: #fff4e5; padding: 20px; border-radius: 8px; border: 1px solid #ffe0b2;">
					<span class="epkbfa epkbfa-gift" style="font-size: 32px; color: #ff9800; display: block; margin-bottom: 10px;"></span>
					<h4 style="margin: 0 0 10px 0; color: #2c3338;"><?php esc_html_e( 'Special Promotions', 'echo-knowledge-base' ); ?></h4>
					<p style="margin: 0; color: #50575e;"><?php esc_html_e( 'Exclusive discounts and offers for beta testers', 'echo-knowledge-base' ); ?></p>
				</div>
				<div style="background: #e8f5e9; padding: 20px; border-radius: 8px; border: 1px solid #c8e6c9;">
					<span class="epkbfa epkbfa-comments" style="font-size: 32px; color: #4caf50; display: block; margin-bottom: 10px;"></span>
					<h4 style="margin: 0 0 10px 0; color: #2c3338;"><?php esc_html_e( 'Shape the Future', 'echo-knowledge-base' ); ?></h4>
					<p style="margin: 0; color: #50575e;"><?php esc_html_e( 'Your feedback will help us build better AI features', 'echo-knowledge-base' ); ?></p>
				</div>
			</div>
		</div>		<?php

		return ob_get_clean();
	}

	/**
	 * Get AI features content (formerly AI Search)
	 *
	 * @return string
	 */
	private function get_ai_features_content() {
		ob_start(); ?>

		<div class="epkb-ai-features-content" style="padding: 20px;">
			<div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border: 1px solid #e2e4e7; text-align: center;">
				<span class="epkbfa epkbfa-magic" style="font-size: 48px; color: #667eea; display: block; margin-bottom: 20px;"></span>
				<h3 style="margin: 0 0 15px 0; color: #2c3338; font-size: 24px;">
					<?php esc_html_e( 'AI Features Coming Soon', 'echo-knowledge-base' ); ?>
				</h3>
				<p style="font-size: 16px; color: #50575e; max-width: 600px; margin: 0 auto 20px;">
					<?php esc_html_e( 'We are working on exciting AI-powered features including intelligent search and conversational chat that will transform how your users interact with your knowledge base. These features will help users find answers faster and more accurately than ever before.', 'echo-knowledge-base' ); ?>
				</p>
				<div style="margin-top: 30px;">
					<a href="https://www.echoknowledgebase.com/ai-beta-signup/" target="_blank" class="epkb-primary-btn" style="text-decoration: none; margin-right: 10px;">
						<span class="epkbfa epkbfa-bell"></span> <?php esc_html_e( 'Get Notified', 'echo-knowledge-base' ); ?>
					</a>
					<a href="https://www.echoknowledgebase.com/documentation/ai-features/" target="_blank" class="epkb-default-btn" style="text-decoration: none;">
						<span class="epkbfa epkbfa-book"></span> <?php esc_html_e( 'Learn More', 'echo-knowledge-base' ); ?>
					</a>
				</div>
			</div>
		</div>		<?php

		return ob_get_clean();
	}

	/**
	 * Get split view layout for conversations
	 *
	 * @param string $mode
	 * @return string
	 */
	private function get_table_page( $mode ) {
		ob_start();
		
		// Check if beta access code is valid
		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		$entered_code = isset( $ai_config['ai_beta_access_code'] ) ? $ai_config['ai_beta_access_code'] : '';
		$valid_beta_code = 'EPKB-BETA-2024-AI-CHAT'; // Hard-coded beta access code
		
		if ( $mode === 'chat' && $entered_code !== $valid_beta_code ) { ?>
			<div class="epkb-ai-beta-access-required" style="padding: 40px; text-align: center;">
				<h3><?php esc_html_e( 'Beta Access Required', 'echo-knowledge-base' ); ?></h3>
				<p style="margin: 20px 0; font-size: 16px;">
					<?php esc_html_e( 'The AI Chat History feature is currently in beta testing. Please enter a valid beta access code in the Settings tab to unlock this feature.', 'echo-knowledge-base' ); ?>
				</p>
				<a href="#" class="epkb-primary-btn" onclick="jQuery('.epkb-primary-tabs__item[data-target=\'settings\']').trigger('click'); return false;">
					<?php esc_html_e( 'Go to Settings', 'echo-knowledge-base' ); ?>
				</a>
			</div>		<?php
		} else { ?>
			<div class="epkb-ai-discussions-layout">
				<div class="epkb-ai-discussions-table">
					<div class="epkb-ai-discussions-header">
						<h3><?php echo $mode === 'search' ? esc_html__( 'User Searches Answered by AI', 'echo-knowledge-base' ) : esc_html__( 'User Conversations with AI', 'echo-knowledge-base' ); ?></h3>
					</div>
					<div class="epkb-ai-discussions-content">
						<?php echo self::get_messages_table( $mode ); ?>
					</div>
				</div>
				<div class="epkb-ai-discussion-details">
					<div class="epkb-ai-discussion-details-header">
						<h3><?php esc_html_e( 'Selected Discussion', 'echo-knowledge-base' ); ?></h3>
					</div>
					<div class="epkb-ai-discussion-details-content">
						<div class="epkb-ai-no-selection">
							<p><?php esc_html_e( 'Select a conversation from the list to view details.', 'echo-knowledge-base' ); ?></p>
						</div>
						<div class="epkb-ai-conversation-messages" style="display: none;">
							<!-- Conversation details will be loaded here via AJAX -->
						</div>
					</div>
				</div>
			</div>		<?php
		}

		return ob_get_clean();
	}

	/**
	 * Show error notifications below tabs.
	 */
	private function error_notification_top() {

		if ( $this->vector_store_status !== 'in_progress' ) {
			return;
		}

		ob_start();     ?>

		<div id="epkb-admin-page-wrap" class="epkb-admin-page-wrap--config-error"> <?php
			EPKB_HTML_Forms::notification_box_top(
				array(
					'type'  => 'warning',
					'title' => esc_html__( 'Vector store is not ready', 'echo-knowledge-base' ),
					'desc'  => esc_html__( 'Vector store setup is in progress. Please refresh the page to check its status.', 'echo-knowledge-base' ),
				)
			);  ?>
		</div>  <?php

		echo ob_get_clean();
	}

	/**
	 * Return configuration for AI Chat error log tab.
	 *
	 * @return array
	 */
	private function get_ai_error_log_boxes() {

		$error_log_form_boxes = array();

		// Chat error log.
		$error_log_form_boxes[] = array(
			'title' => esc_html__( 'Error Log', 'echo-knowledge-base' ),
			'class' => 'epkb-ai__error-log-settings',
			'html'  => $this->get_api_error_log_box_html(),
			'description' => esc_html__( 'Enable debugging when instructed by the Echo team.', 'echo-knowledge-base' ),
		);

		return $error_log_form_boxes;
	}

	/**
	 * Error log output
	 *
	 * @return string
	 */
	private function get_api_error_log_box_html() {

		$is_debug_on = EPKB_Utilities::get_wp_option( 'epkb_debug', false );
		$output = '';

		// button for HD core action TODO: should we refactor it to have its own logs process?
		if ( class_exists( 'EPKB_HTML_Elements' ) && method_exists( 'EPKB_HTML_Elements', 'submit_button_v2' ) ) {
			$output = EPKB_HTML_Elements::submit_button_v2(
				$is_debug_on ? esc_html__( 'Disable Debug', 'echo-knowledge-base' ) : esc_html__( 'Enable Debug', 'echo-knowledge-base' ),
				'epkb_toggle_debug',
				'epkb-debug__toggle',
				'',
				true,
				true,
				'epkb-primary-btn'
			);
		}

		if ( ! $is_debug_on ) {
			return $output;
		}

		$logs = EPKB_AI_Utilities::get_logs();
		if ( empty( $logs ) ) {
			return $output . '<section class="epkb-debug-info-empty-logs"><h3>' . esc_html__( 'Logs are empty', 'echo-knowledge-base' ) . '</h3></section>';
		}

		$output .= '<h3 class="epkb-debug__title">' . esc_html__( 'Debug Information', 'echo-knowledge-base' ) . ':</h3>';

		// General simple log for all errors
		$output .= '<textarea rows="30" cols="150" style="overflow:scroll;">';
		foreach ( $logs as $log ) {
			$output .= empty( $log['date'] ) ? '' : '[' . esc_html( $log['date'] ) . ']: ';
			$output .= empty( $log['message'] ) ? '' : esc_html( $log['message'] ) . "\n";
		}
		$output .= '</textarea>';

		$output .= EPKB_HTML_Elements::submit_button_v2( esc_html__( 'Reset Logs', 'echo-knowledge-base' ), 'epkb_ai_reset_logs', 'epkb_ai_reset_logs', '', true, true, 'epkb-primary-btn' );

		return $output;
	}

	/**
	 *  Return label text for AI setting UI
	 *
	 * @param $setting_config
	 * @return string
	 */
	private static function get_setting_status_label( $setting_config ) {

		switch ( $setting_config['status'] ) {

			case 'inactive':
				return esc_html__( 'Not initialized', 'echo-knowledge-base' );

			case 'missing':
				return esc_html__( 'Missing', 'echo-knowledge-base' );

			case 'active':
				return esc_html__( 'Active', 'echo-knowledge-base' ) . '<span class="epkb-ai-status__active-checkmark epkbfa epkbfa-check-circle"></span>';

			case 'error':
				return esc_html__( 'Error', 'echo-knowledge-base' );

			case 'expired':
				return esc_html__( 'Expired', 'echo-knowledge-base' );

			case 'in_progress':
				return esc_html__( 'In Progress', 'echo-knowledge-base' );

			case 'cancelled':
				return esc_html__( 'Cancelled', 'echo-knowledge-base' );

			case 'failed':
			default:
				return esc_html__( 'Failed', 'echo-knowledge-base' );
		}
	}

	/**
	 * Return action button for AI setting UI
	 *
	 * @param $setting_config
	 * @param $setting_key
	 * @return string
	 */
	private static function get_setting_action_button( $setting_config, $setting_key ) {

		switch ( $setting_config['status'] ) {

			case 'inactive':
				return $setting_key == 'files'
					? EPKB_HTML_Elements::submit_button_v2( __( 'Upload', 'echo-knowledge-base' ), 'epkb-ai-upload-' . $setting_key, '', '', false, true, 'epkb-primary-btn epkb-ai-upload-' . $setting_key )
					: EPKB_HTML_Elements::submit_button_v2( __( 'Create', 'echo-knowledge-base' ), 'epkb-ai-create-' . $setting_key, '', '', false, true, 'epkb-primary-btn epkb-ai-create-' . $setting_key );

			case 'missing':
				return $setting_key == 'files'
					? ''
					: EPKB_HTML_Elements::submit_button_v2( __( 'Re-create', 'echo-knowledge-base' ), 'epkb-ai-recreate-' . $setting_key, '', '', false, true, 'epkb-primary-btn epkb-ai-recreate-' . $setting_key );

			case 'active':
			case 'failed':
			case 'cancelled':
				return $setting_key == 'files'
					? EPKB_HTML_Elements::submit_button_v2( __( 'Re-upload', 'echo-knowledge-base' ), 'epkb-ai-reupload-' . $setting_key, '', '', false, true, 'epkb-primary-btn epkb-ai-reupload-' . $setting_key )
					: '';

			case 'error':
				return esc_html( $setting_config['error_message'] );

			case 'expired':
				return $setting_key == 'vector-store'
					? EPKB_HTML_Elements::submit_button_v2( __( 'Re-create', 'echo-knowledge-base' ), 'epkb-ai-recreate-' . $setting_key, '', '', false, true, 'epkb-primary-btn epkb-ai-recreate-' . $setting_key )
					: '';

			case 'in_progress':
				return '<span class="epkb-ai-progress-indicator"><span class="epkb-ai-spinner"></span> ' . __( 'Processing...', 'echo-knowledge-base' ) . '</span>';

			default:
				return __( 'Contact Us', 'echo-knowledge-base' );
		}
	}

	/**
	 * Show actions row for Settings tab
	 *
	 * @return false|string
	 */
	private static function settings_tab_actions_row() {

		ob_start();     ?>

		<div class="epkb-admin__list-actions-row">		<?php
			EPKB_HTML_Elements::submit_button_v2( esc_html__( 'Save Settings', 'echo-knowledge-base' ), 'epkb_save_ai_settings', '', '', false, '', 'epkb-success-btn' );     ?>
		</div>      		<?php

		return ob_get_clean();
	}

	/**
	 * Output JavaScript for the admin page
	 */
	private function output_page_scripts() { ?>
		<script>
		jQuery(document).ready(function($) {
			// Handle navigation button clicks in welcome content
			$('.epkb-admin__welcome-content .epkb-primary-btn').on('click', function(e) {
				e.preventDefault();
				var target = $(this).data('target');
				$('.epkb-admin__top-panel__item[data-target="' + target + '"]').trigger('click');
			});

			// Handle navigation button clicks in stats content
			$('.epkb-admin__stats-content a[data-target]').on('click', function(e) {
				e.preventDefault();
				var target = $(this).data('target');
				$('.epkb-primary-tabs__item[data-target="' + target + '"]').trigger('click');
			});

			// Handle AI Beta signup form submission
			$('#epkb-ai-beta-signup-form .epkb-ai-signup-submit-btn').on('click', function(e) {
				e.preventDefault();

				var $form        = $(this).closest('form');
				var $btn         = $(this);
				var userEmail    = $form.find('input[name="user_email"]').val();
				var nonce        = $form.find('input[name="_wpnonce"]').val();
				var $msgBox      = $form.find('.epkb-ai-signup-message');

				// Basic email validation
				if ( ! userEmail || ! /.+@.+\..+/.test(userEmail) ) {
					$msgBox.text('<?php echo esc_js( __( 'Please enter a valid email address.', 'echo-knowledge-base' ) ); ?>');
					return;
				}

				// Disable button to prevent multiple submissions
				$btn.prop('disabled', true);
				$msgBox.text('<?php echo esc_js( __( 'Submitting…', 'echo-knowledge-base' ) ); ?>');

				$.post( ajaxurl, {
					action: 'epkb_ai_beta_signup',
					user_email: userEmail,
					_wpnonce: nonce
				}, function(response) {
					if ( response && response.success ) {
						$msgBox.text( response.data.message );
						$form.find('input[name="user_email"]').val('');
					} else {
						var errMsg = ( response && response.data && response.data.message ) ? response.data.message : '<?php echo esc_js( __( 'An error occurred. Please try again later.', 'echo-knowledge-base' ) ); ?>';
						$msgBox.text( errMsg );
					}
					$btn.prop('disabled', false);
				});
			});
		});
		</script>  		<?php
	}

	/**
	 * Handle AI Beta Signup form submission
	 */
	public function ajax_epkb_ai_beta_signup() {

		// Simple nonce check like deactivation form
		$wpnonce_value = EPKB_Utilities::post( '_wpnonce' );
		if ( empty( $wpnonce_value ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $wpnonce_value ) ), '_epkb_ai_beta_signup_nonce' ) ) {
			wp_send_json_error();
		}

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

	/**
	 * Handle AJAX request to save AI settings
	 */
	public function ajax_save_ai_settings() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die();

		$settings = array();

		$openai_key = EPKB_Utilities::post( 'openai_key' );
		if ( ! empty( $openai_key ) && strpos( $openai_key, '...' ) === false ) {
			$settings['openai_key'] = $openai_key;
		}

		// Handle model selection
		/* $model = EPKB_Utilities::post( 'ai_api_model' );
		if ( ! empty( $model ) ) {
			$settings['model'] = $model;
		} */

		$disclaimer_accepted = EPKB_Utilities::post( 'ai_disclaimer_accepted' );
		if ( is_array( $disclaimer_accepted ) ) {
			$settings['ai_disclaimer_accepted'] = $disclaimer_accepted;
		}

		/* $ai_search_enabled = EPKB_Utilities::post( 'ai_search_enabled' );
		if ( ! is_null( $ai_search_enabled ) ) {
			$settings['ai_search_enabled'] = $ai_search_enabled === 'on' ? 'on' : 'off';
		} */

		$ai_chat_enabled = EPKB_Utilities::post( 'ai_chat_enabled' );
		if ( ! is_null( $ai_chat_enabled ) ) {
			$settings['ai_chat_enabled'] = $ai_chat_enabled === 'on' ? 'on' : 'off';
		}
		
		$ai_beta_access_code = EPKB_Utilities::post( 'ai_beta_access_code' );
		if ( ! is_null( $ai_beta_access_code ) ) {
			$settings['ai_beta_access_code'] = sanitize_text_field( $ai_beta_access_code );
		}

		$ai_manager = new EPKB_AI_Manager();
		$result = $ai_manager->save_ai_settings( $settings );
		if ( is_wp_error( $result ) ) {
			EPKB_Utilities::ajax_show_error_die( $result->get_error_message() );
		}

		// Return success
		wp_send_json_success( array(
			'message' => __( 'AI settings saved successfully', 'echo-knowledge-base' ),
			'reload' => false
		) );
	}
}
