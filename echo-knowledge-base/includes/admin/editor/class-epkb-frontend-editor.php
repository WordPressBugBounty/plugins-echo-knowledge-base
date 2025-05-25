<?php

/**
 * Visual Helper Editor
 * Handles the display and saving of settings for modules on the frontend
 * within the KB Main Page Visual Helper.
 */
class EPKB_Frontend_Editor {

	private static $modules = [ 'search', 'categories_articles', 'articles_list', 'faqs', 'resource_links' ];

    /**
     * Constructor
     * Initializes the class, sets up KB configuration, and adds AJAX handlers.
     */
    public function __construct() {

		add_action( 'wp_ajax_eckb_apply_fe_settings', array( $this, 'update_preview_and_settings') );
		add_action( 'wp_ajax_nopriv_eckb_apply_fe_settings', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

        add_action( 'wp_ajax_eckb_save_fe_settings', array( $this, 'save_settings' ) );
        add_action( 'wp_ajax_nopriv_eckb_save_fe_settings', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		// report uncaught AJAX error
	    add_action( 'wp_ajax_epkb_editor_error', array( 'EPKB_Controller', 'handle_report_admin_error' ) );
	    add_action( 'wp_ajax_nopriv_epkb_editor_error', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

        add_action( 'wp_footer', array( $this, 'generate_page_content' ), 1 );
    }

    /**
     * Display Frontend Editor
     */
    public function generate_page_content() {

		$kb_id = EPKB_Utilities::get_eckb_kb_id( '' );
		if ( empty( $kb_id ) ) {
			return;
		}

		// continue only if we are on one of the following page: KB main page, KB article page, KB archive page
		$kb_page_type = EPKB_Editor_Utilities::epkb_front_end_editor_type();
		if ( empty( $kb_page_type ) || $kb_page_type != 'main-page' ) {
			return;
		}

		if ( ! EPKB_Admin_UI_Access::is_user_access_to_context_allowed( 'admin_eckb_access_frontend_editor_write' ) ) {
			return;
		}

		// Editor is disabled for blocks on KB Main Page
		if ( $kb_page_type == 'main-page' && EPKB_Block_Utilities::current_post_has_kb_blocks() ) {
			return;
		}

	    // get KB configuration	- do nothing on fail
	    $kb_config = epkb_get_instance()->kb_config_obj->get_kb_config( $kb_id );
		if ( is_wp_error( $kb_config ) ) {
			return;
		}

		// if modular is off do not enable editor on KB Main Page
		if ( $kb_page_type == 'main-page' && $kb_config['modular_main_page_toggle'] != 'on' ) {
			return;
		}

		// do not enable for old category archive page v2
		if ( $kb_page_type == 'archive-page' && $kb_config['archive_page_v3_toggle'] != 'on' ) {
			return;
		}

	    // when FE preview is updated via the entire page reload without saving settings (for some of the settings controls need to reload the entire page)
	    $kb_config = self::fe_preview_config( $kb_config );

		// render settings
		self::render_editor( $kb_config );

		// TODO FUTURE: this way of including of WordPress core color-picker is not 100% reliable as 'iris' file may be moved in some future release
		//		alternative is to use our color-picker file as we do for front-end editor, but it may require more custom CSS
		// on public frontend the WordPress color-picker is not registered by default
		if ( ! wp_script_is( 'wp-i18n', 'registered' ) ) {
			wp_register_script( 'wp-i18n', includes_url( 'js/dist/i18n.min.js' ), array(), false, true );
		}
		if ( ! wp_script_is( 'iris', 'registered' ) ) {
			wp_register_script( 'iris', admin_url( 'js/iris.min.js' ), array( 'jquery' ), false, true );
		}
		if ( ! wp_style_is( 'wp-color-picker', 'registered' ) ) {
			wp_register_style( 'wp-color-picker', admin_url( 'css/color-picker.css' ) );
		}
		if ( ! wp_script_is( 'wp-color-picker', 'registered' ) ) {
			wp_register_script( 'wp-color-picker', admin_url( 'js/color-picker.min.js' ), array( 'jquery', 'iris', 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-ui-widget', 'wp-i18n' ), false, true );
		}

        wp_enqueue_style( 'epkb-frontend-editor' );
		wp_enqueue_script( 'epkb-admin-form-controls-scripts' );
		wp_enqueue_script( 'epkb-frontend-editor' );
    }

    /**
     * Renders the HTML content for the settings sidebar.
     * Retrieves configuration settings and generates the form fields.
     */
    private static function render_editor( $kb_config ) {	?>

		<!-- Frontend Editor Toggle -->
		<div id="epkb-fe__toggle" class="epkb-fe__toggle" style="display: none;">
			<div class="epkb-fe__toggle-wrapper">
				<div class="epkb-fe_toggle-icon-wrapper">
					<span class="epkbfa epkbfa-pencil"></span>
				</div>
				<div class="epkb-fe__toggle-title">
					<span class="epkb-fe__toggle-title__text"><?php esc_html_e( 'Open Frontend Editor', 'echo-knowledge-base' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Frontend Editor Sidebar -->
		<div id="epkb-fe__editor" class="epkb-admin__form epkb-fe__editor--home" data-kbid="<?php echo esc_attr( $kb_config['id'] ); ?>" style="display: none;">

			<!-- Frontend Editor Header -->
			<div id="epkb-fe__header-container">
				
				<!-- Main Page Titles -->
				<h1 data-title="home" class="epkb-fe__header-title"><?php esc_html_e( 'Frontend Editor', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="help" class="epkb-fe__header-title"><?php esc_html_e( 'Help', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="search" class="epkb-fe__header-title"><?php esc_html_e( 'Search Box', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="categories_articles" class="epkb-fe__header-title"><?php esc_html_e( 'Categories and Articles', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="articles_list" class="epkb-fe__header-title"><?php esc_html_e( 'Featured Articles', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="faqs" class="epkb-fe__header-title"><?php esc_html_e( 'FAQs', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="resource_links" class="epkb-fe__header-title"><?php esc_html_e( 'Resource Links', 'echo-knowledge-base' ); ?></h1>
			
				<!-- Article Page Titles -->
				<h1 data-title="article-page-settings" class="epkb-fe__header-title"><?php esc_html_e( 'Settings', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="article-page-search-box" class="epkb-fe__header-title"><?php esc_html_e( 'Search Box', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="article-page-sidebar" class="epkb-fe__header-title"><?php esc_html_e( 'Sidebar', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="article-page-toc" class="epkb-fe__header-title"><?php esc_html_e( 'Table of Contents', 'echo-knowledge-base' ); ?></h1>
				<h1 data-title="article-page-ratings" class="epkb-fe__header-title"><?php esc_html_e( 'Rating and Feedback', 'echo-knowledge-base' ); ?></h1>

				<div class="epkb-fe__header-close-button">
					<span class="epkbfa epkbfa-times"></span>
				</div>
			</div>

			<!-- List of features -->
			<div class="epkb-fe__features-list">
			
				<div class="epkb-fe__actions" style="display: none;">
					<span id="epkb-fe__action-back" class="epkb-primary-btn epkb-fe__action-btn">
						<span class="epkb-fe__action-btn-icon epkbfa epkbfa-chevron-left"></span>
						<span class="epkb-fe__action-btn-text"><?php esc_html_e( 'Back', 'echo-knowledge-base' ); ?></span>
					</span>
					<span id="epkb-fe__action-save" class="epkb-success-btn epkb-fe__action-btn"><?php esc_html_e( 'Save', 'echo-knowledge-base' ); ?></span>
				</div>	<?php

				// display settings for each feature
				switch ( EPKB_Editor_Utilities::epkb_front_end_editor_type() ) {

					case 'main-page':
						// we need to retrieve settings for all modules - hardcode all modules assigned to rows in $settings_kb_config to have their settings rendered by EPKB_Config_Settings_Page(),
						// while store actually selected modules in $kb_config
						$settings_kb_config = $kb_config;

						$assigned_modules = array();
						for ( $row_number = 1; $row_number <= EPKB_Modular_Main_Page::MAX_ROWS; $row_number++ ) {
							$module_key = 'ml_row_' . $row_number . '_module';
							if ( isset( $settings_kb_config[ $module_key ] ) && $settings_kb_config[ $module_key ] != 'none' ) {
								$assigned_modules[] = $settings_kb_config[ $module_key ];
							}
						}

						$unassigned_modules = array_diff( self::$modules, $assigned_modules );
						for ( $row_number = 1; $row_number <= EPKB_Modular_Main_Page::MAX_ROWS; $row_number++ ) {
							$module_key = 'ml_row_' . $row_number . '_module';
							if ( isset( $settings_kb_config[ $module_key ] ) && $settings_kb_config[ $module_key ] == 'none' ) {
								if ( ! empty( $unassigned_modules ) ) {
									$settings_kb_config[ $module_key ] = array_shift( $unassigned_modules );
								}
							}
						}

						$config_page = new EPKB_Config_Settings_Page( $settings_kb_config, true );
						$features_config = $config_page->get_vertical_tabs_config( 'main-page' );
						self::display_main_page_feature_selection_buttons( array(
							'search' => __( 'Search', 'echo-knowledge-base' ),
							'categories_articles' => __( 'Categories & Articles', 'echo-knowledge-base' ),
							'articles_list' => __( 'Featured Articles', 'echo-knowledge-base' ),
							'faqs' => __( 'FAQs', 'echo-knowledge-base' ),
							'resource_links' => __( 'Resource Links', 'echo-knowledge-base' ),
						) );
						self::display_main_page_settings( $features_config, $kb_config );
						break;

					case 'article-page':
						$config_page = new EPKB_Config_Settings_Page( $kb_config, true );
						$features_config = $config_page->get_vertical_tabs_config( 'article-page' );
						self::display_article_page_settings( $features_config );
						break;

					case 'archive-page':
						$config_page = new EPKB_Config_Settings_Page( $kb_config, true );
						$features_config = $config_page->get_vertical_tabs_config( 'archive-page' );
						self::display_archive_page_settings( $features_config );
						break;

					default:
						break;
				}	?>
			</div>

			<!-- Help tab -->
			<div class='epkb-fe__help-container'>	<?php
				self::display_help_tab( $kb_config );	?>
			</div> 

			<!-- Frontend Editor Footer -->
			<div id="epkb-fe__footer-container">  
				<!-- text is available to screen readers but not visible on screen -->
				<span id="epkb-tab-instructions" class="epkb-sr-only"><?php esc_html_e( 'Use arrow keys to move between features', 'echo-knowledge-base' ); ?></span>

				<!-- FEATURES CONTAINER -->
				<div id="epkb-fe__tab-container" role="tablist" aria-label="Help Dialog Top Tabs" aria-describedby="epkb-tab-instructions">

					<div id="epkb-fe__help-tab" role="tab" aria-selected="true" tabindex="0" class="epkb-fe__tab epkb-fe__tab__help-btn epkb-fe__tab--active" data-epkb-target-tab="help">
						<span class="epkb-fe__tab__icon epkbfa epkbfa-book"></span>
						<span class="epkb-fe__tab__text"><?php esc_html_e( 'Help', 'echo-knowledge-base' ); ?></span>
					</div>  

					<a id="epkb-fe__contact-tab" href="<?php echo esc_url( 'https://www.echoknowledgebase.com/contact-us/' ); ?>" target="_blank" rel="noopener noreferrer" aria-selected="false" tabindex="-1" class="epkb-fe__tab epkb-fe__tab__contact-btn" data-epkb-target-tab="contact">
						<span class="epkb-fe__tab__icon epkbfa epkbfa-envelope-o"></span>
						<span class="epkb-fe__tab__text"><?php esc_html_e( 'Contact Us', 'echo-knowledge-base' ); ?></span>
					</a>  		

				</div>				
			</div>
		</div>

		<!-- Error Form -->
		<div id="epkb-fe__error-form-wrap" style="display: none !important;">	<?php
			EPKB_HTML_Admin::display_report_admin_error_form();	?>
		</div>	<?php
    }

	private static function display_help_tab( $kb_config ) {

		// Is this page or search box too narrow? ------------------------/
		$search_row_width_key = '';
		$category_row_width_key = '';
		$kb_id = $kb_config['id'];

		for ( $row_index = 1; $row_index <= EPKB_Modular_Main_Page::MAX_ROWS; $row_index++ ) {
			if ( $kb_config['ml_row_' . $row_index . '_module'] === 'categories_articles' ) {
				$category_row_width_key = 'ml_row_' . $row_index . '_desktop_width';
				continue;
			}
			if ( $kb_config['ml_row_' . $row_index . '_module'] === 'search' ) {
				$search_row_width_key = 'ml_row_' . $row_index . '_desktop_width';
			}
		}

		ob_start();	?>

		<h4><?php echo esc_html__( 'Page width', 'echo-knowledge-base' ) . ': '; ?><span class='js-epkb-mp-width'>-</span></h4> <?php

		if ( ! empty( $search_row_width_key ) ) {	?>
			<ul>
				<h5><?php echo esc_html__( 'Search Box', 'echo-knowledge-base' ); ?></h5>

				<li><?php echo esc_html__( 'Actual width', 'echo-knowledge-base' ) . ': '; ?><span class="js-epkb-mp-search-width">-</span></li>

				<li><?php echo esc_html__( 'KB setting for Search Width', 'echo-knowledge-base' ) . ': ' . esc_attr( $kb_config[ $search_row_width_key ] . $kb_config[ $search_row_width_key . '_units' ] ) .

					( $kb_config[ $search_row_width_key . '_units' ] == '%' ? ' ' . esc_html__( 'of the page.', 'echo-knowledge-base' ) : '' ); ?>

					<a href="#" class="epkb-fe__open-feature-setting-link" data-feature="search" data-section="module-settings"><?php echo esc_html__( 'Edit', 'echo-knowledge-base' ); ?></a>
				</li>
			</ul>	<?php
		}

		if ( ! empty( $category_row_width_key ) ) {	?>
			<h5><?php echo esc_html__( 'Categories and Articles', 'echo-knowledge-base' ); ?></h5>

			<ul>
				<li><?php echo esc_html__( 'Actual width', 'echo-knowledge-base' ) . ': '; ?><span class="js-epkb-mp-width-container">-</span></li>

				<li><?php echo esc_html__( 'KB setting for categories list width', 'echo-knowledge-base' ); echo ': ' . esc_attr( $kb_config[ $category_row_width_key ] . $kb_config[ $category_row_width_key . '_units' ] ) .
							( $kb_config[ $category_row_width_key . '_units' ] == '%' ? ' ' . esc_html__( 'of the total page width.', 'echo-knowledge-base' ) : '' ); ?>
						<a href="#" class="epkb-fe__open-feature-setting-link" data-feature="categories_articles" data-section="module-settings"><?php echo esc_html__( 'Edit', 'echo-knowledge-base' ); ?></a>
				</li>
			</ul>	<?php
		}	?>

		<h5><?php echo esc_html__( 'Troubleshooting', 'echo-knowledge-base' ); ?></h5>

		<p><?php echo esc_html__( 'If the value you set in the KB settings does not match the actual value, it may be because your theme or page builder is limiting the overall width. In such cases, the KB settings cannot exceed the maximum width allowed ' .
						'by your theme or page builder. Try the following', 'echo-knowledge-base' ) . ':'; ?>
		</p>

		<ul>
			<li><?php echo sprintf( esc_html__( 'If the KB Shortcode is inserted inside your page builder, then you will need to check the section width of that page builder. %s', 'echo-knowledge-base' ),
				'<a href="https://www.echoknowledgebase.com/documentation/main-page-width-and-page-builders/" target="_blank" rel="nofollow">' . esc_html__( 'Learn more', 'echo-knowledge-base' ) .' '. '<span class="epkbfa epkbfa-external-link"> </span></a> ' ); ?>
			</li><?php

			if ( $kb_config['templates_for_kb'] == 'current_theme_templates' ) { ?>
				<li><?php echo sprintf( esc_html__( 'You are currently using the Current Theme Template. Check your theme settings or switch to the KB template. %s', 'echo-knowledge-base' ),
					'<a href="https://www.echoknowledgebase.com/documentation/current-theme-template-vs-kb-template/" target="_blank" rel="nofollow">' . esc_html__( 'Learn more', 'echo-knowledge-base' ) .' '. '<span class="epkbfa epkbfa-external-link"></span></a> ' ); ?>
				</li>	<?php
			}	?>
		</ul>	<?php

		$content = ob_get_clean();

		self::display_section( __( 'Is this page or search box too narrow?', 'echo-knowledge-base' ), $content );

		ob_start(); ?>

		<p> <?php
		echo sprintf( esc_html__( 'The Knowledge Base offers two template options for both Main and Article Pages: %sKB Template%s and %sCurrent Theme Template%s.', 'echo-knowledge-base' ), '<strong>', '</strong>', '<strong>', '</strong>' ) . ' ' .
			'<a href="https://www.echoknowledgebase.com/documentation/current-theme-template-vs-kb-template/" target="_blank" rel="nofollow">' . esc_html__(  'Learn More', 'echo-knowledge-base' ) .' '. '<span class="epkbfa epkbfa-external-link"></span></a>';  ?>
		</p>

		<p><?php echo esc_html__( 'If you\'re experiencing layout issues or want to see a different look, try switching the template', 'echo-knowledge-base' ) . ':'; ?></p>
		<a href="#" class="epkb-fe__open-feature-setting-link" data-feature="categories_articles" data-setting="templates_for_kb"><?php esc_html_e( 'Click here to switch the template', 'echo-knowledge-base' ); ?></a> <?php

		$content = ob_get_clean();

		self::display_section( __( 'Issues with the page layout, header, or menu?', 'echo-knowledge-base' ), $content );
	}

	/**
	 * Display a collapsible section with a title and content.
	 * 
	 * @param string $title   The title text to display in the section header
	 * @param string $content The HTML content to display in the section body
	 */
	private static function display_section( $title, $content ) { ?>
		<div class="epkb-fe__settings-section">
			<div class="epkb-fe__settings-section-header">				<?php
				echo esc_html( $title ); ?>
				<i class="epkbfa epkbfa-chevron-down"></i>
				<i class="epkbfa epkbfa-chevron-up"></i>
			</div>

			<div class="epkb-fe__settings-section-body">				<?php
				echo wp_kses_post( $content ); ?>
			</div>
		</div>	<?php
	}

	private static function display_main_page_settings( $features_config, $kb_config ) {

		$is_elay_enabled = EPKB_Utilities::is_elegant_layouts_enabled();

		foreach ( $features_config['main-page']['sub_tabs'] as $row_index => $row_config ) {

			$is_resource_links_unavailable = $row_config['data']['selected-module'] == 'resource_links' && ! $is_elay_enabled;

			$module_position = $is_resource_links_unavailable ? 'none' : self::get_module_row_number( $row_config['data']['selected-module'], $kb_config );	?>

			<!-- Module settings -->
			<div class="epkb-fe__feature-settings" data-feature="<?php echo esc_attr( $row_config['data']['selected-module'] ); ?>" data-row-number="<?php echo esc_attr( $module_position ); ?>" data-kb-page-type="main-page">

				<!-- Module settings body -->
				<div class="epkb-fe__settings-list">	<?php
					if ( $is_resource_links_unavailable ) {
						EPKB_HTML_Admin::show_resource_links_ad();
					} else {
						echo self::get_module_position_field( $row_config['data']['selected-module'], $module_position );
						self::display_feature_settings( $row_config['contents'] );
					}	?>
				</div>
			</div>	<?php
		}
	}

	/**
	 * Display buttons HTML to select a feature for Main Page in desired sequence (since the features can change their sequence on the page, it is needed to keep their sequence in UI constant)
	 * @param $features_list
	 * @return void
	 */
	private static function display_main_page_feature_selection_buttons( $features_list ) {
		foreach ( $features_list as $feature_name => $feature_title ) {	?>
			<!-- Module icon -->
			<div class="epkb-fe__feature-select-button" data-feature="<?php echo esc_attr( $feature_name ); ?>">
				<i class="<?php echo self::get_features_icon_escaped( $feature_name ); ?> epkb-fe__feature-icon"></i>
				<span class="epkb-fe__feature-title"><?php echo esc_html( $feature_title ); ?></span>
			</div>	<?php
		}
	}

	private static function display_article_page_settings( $features_config ) {

		foreach ( $features_config['article-page']['sub_tabs'] as $feature_index => $feature_config ) {	?>

			<!-- Feature icon -->
			<div class="epkb-fe__feature-select-button" data-feature="<?php echo esc_attr( $feature_config['key'] ); ?>">
				<i class="<?php echo self::get_features_icon_escaped( $feature_config['key'] ); ?> epkb-fe__feature-icon"></i>
				<span class="epkb-fe__feature-title"><?php echo esc_html( $feature_config['title'] ); ?></span>
			</div>

			<!-- Feature settings -->
			<div class="epkb-fe__feature-settings" data-feature="<?php echo esc_attr( $feature_config['key'] ); ?>" data-kb-page-type="article-page">

				<!-- Sub-feature settings body -->
				<div class="epkb-fe__settings-list">	<?php
					self::display_feature_settings( $feature_config['contents'] );	?>
				</div>
			</div>	<?php
		}
	}

	private static function display_archive_page_settings( $features_config ) {	?>

		<!-- Feature icon -->
		<div class="epkb-fe__feature-select-button" data-feature="archive-page-settings">
			<i class="<?php echo self::get_features_icon_escaped( 'archive-page-settings' ); ?> epkb-fe__feature-icon"></i>
			<span class="epkb-fe__feature-title"><?php esc_html_e( 'Settings', 'echo-knowledge-base' ); ?></span>
		</div>

		<!-- Feature settings -->
		<div class="epkb-fe__feature-settings" data-feature="archive-page-settings" data-kb-page-type="archive-page">

			<!-- Settings body -->
			<div class="epkb-fe__settings-list">	<?php
				self::display_feature_settings( $features_config['archive-page']['contents'] );	?>
			</div>
		</div>	<?php
	}

	/**
	 * Display settings HTML for each feature
	 * @param $feature_config_contents
	 * @param $return_html
	 * @return string
	 */
	private static function display_feature_settings( $feature_config_contents, $return_html = false ) {

		if ( $return_html ) {
			ob_start();
		}

		foreach ( $feature_config_contents as $settings_section ) {

			$css_class = empty( $settings_section['css_class'] ) ? '' : ' ' . str_replace( 'epkb-admin__form-tab-content', 'epkb-fe__settings-section', $settings_section['css_class'] );
			$data_escaped = '';
			if ( isset( $settings_section['data'] ) ) {
				foreach ( $settings_section['data'] as $data_key => $data_value ) {
					$data_escaped .= 'data-' . esc_attr( $data_key ) . '="' . esc_attr( str_replace( 'epkb-admin__form-tab-content', 'epkb-fe__settings-section', $data_value ) ) . '" ';
				}
			}	?>

			<!-- Settings section -->
			<div class="epkb-fe__settings-section epkb-fe__is_opened<?php echo esc_attr( $css_class ); ?>" <?php echo $data_escaped; ?>>
				<div class="epkb-fe__settings-section-header"><?php echo esc_html( $settings_section['title'] ); ?><i class="epkbfa epkbfa-chevron-down"></i><i class="epkbfa epkbfa-chevron-up"></i></div>
				<div class="epkb-fe__settings-section-body">	<?php
					echo wp_kses( $settings_section['body_html'], EPKB_Utilities::get_admin_ui_extended_html_tags() );	?>
				</div>
			</div>	<?php
		}

		if ( $return_html ) {
			return ob_get_clean();
		}
	}

	/**************************************************************************************
	 *
	 *    AJAX PREVIEW HANDLERS
	 *
	 **************************************************************************************/

	/**
	 * AJAX preview changes without saving
	 */
	public function update_preview_and_settings() {
		global $epkb_frontend_editor_preview;

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_frontend_editor_write' );

		$epkb_frontend_editor_preview = true;

		$feature_name = EPKB_Utilities::post( 'feature_name' );
		$kb_page_type = EPKB_Utilities::post( 'kb_page_type' );
		$setting_name = EPKB_Utilities::post( 'setting_name' );
		$layout_name = EPKB_Utilities::post( 'layout_name' );
		$settings_row_number = EPKB_Utilities::post( 'settings_row_number' );

		// do not use the self::update_module_position() here because on preview the rows numbers in HTML remain original
		$config = self::merge_new_and_old_kb_config( false );
		$orig_config = $config['orig_config'];
		$new_config = $config['new_config'];

		ob_start();
		$faqs_design_settings = array();
		$categories_articles_design_settings = array();
		switch ( $feature_name ) {

			// Main Page 'Search' feature
			case 'search':
				global $eckb_is_kb_main_page;
				$eckb_is_kb_main_page = true;

				EPKB_Core_Utilities::initialize_advanced_search_box();

				$new_config = EPKB_Core_Utilities::advanced_search_presets( $new_config, $orig_config, 'mp' );

				EPKB_Modular_Main_Page::search_module( $new_config );
				break;

			// Main Page 'Categories & Articles' feature
			case 'categories_articles':
				global $eckb_is_kb_main_page;
				$eckb_is_kb_main_page = true;

				// adjust settings on layout change

				// temporarily set the layout name to the one selected in the editor if user went from e.g. Basic -> Tabs -> Basic
				// original layout is Basic but for the purpose of layout change we want to capture Tabs -> Basic change
				if ( ! empty( $layout_name) ) {
					$orig_config['kb_main_page_layout'] = $layout_name;
				}

				$new_config_result = EPKB_Core_Utilities::adjust_settings_on_layout_change( $orig_config, $new_config );
				$new_config = $new_config_result['new_config'];
				$seq_meta = $new_config_result['seq_meta'];

				if ( ! empty( $new_config['categories_articles_preset'] ) && $new_config['categories_articles_preset'] != 'current' ) {
					$categories_articles_design_settings = EPKB_KB_Wizard_Themes::get_theme( $new_config['categories_articles_preset'], $new_config );
					$new_config = array_merge( $new_config, $categories_articles_design_settings );
				}

				$handler = new EPKB_Modular_Main_Page();
				$handler->setup_layout_data( $new_config, $seq_meta );
				if ( $new_config['kb_main_page_layout'] == EPKB_Layout::SIDEBAR_LAYOUT ) {
					$intro_text = apply_filters( 'eckb_main_page_sidebar_intro_text', $new_config['sidebar_main_page_intro_text'], $new_config['id'] );
					$temp_article = new stdClass();
					$temp_article->ID = 0;
					$temp_article->post_title = esc_html__( 'Demo Article', 'echo-knowledge-base' );
					$temp_article->post_content = wp_kses( $intro_text, EPKB_Utilities::get_extended_html_tags( true ) );
					$temp_article = new WP_Post( $temp_article );

					$new_config['sidebar_welcome'] = 'on';
					$new_config['article_content_enable_back_navigation'] = 'off';
					$new_config['prev_next_navigation_enable'] = 'off';
					$new_config['article_content_enable_rows'] = 'off';
					$layout_output = EPKB_Articles_Setup::get_article_content_and_features( $temp_article, $temp_article->post_content, $new_config );
					$handler->set_sidebar_layout_content( $layout_output );
				}

				if ( $handler->has_kb_categories() ) {
					$handler->categories_articles_module( $new_config );
				} else {
					$handler->show_categories_missing_message();
				}
				break;

			// Main Page 'Featured Articles' feature
			case 'articles_list':   ?>
				<div id="epkb-ml__module-articles-list" class="epkb-ml__module">   <?php
					$articles_list_handler = new EPKB_ML_Articles_List( $new_config );
					$articles_list_handler->display_articles_list();	?>
				</div>  <?php
				break;

			// Main Page 'FAQs' feature
			case 'faqs':    	?>
				<div id="epkb-ml__module-faqs" class="epkb-ml__module">   <?php

					if ( ! empty( $new_config['faq_preset_name'] ) ) {
						$faqs_design_settings = EPKB_FAQs_Utilities::get_design_settings( $new_config['faq_preset_name'] );
						$new_config = array_merge( $new_config, $faqs_design_settings );
					}

					$faqs_handler = new EPKB_ML_FAQs( $new_config );
					$faqs_handler->display_faqs_module( true, true ); ?>
				</div>	<?php
				break;

			case 'resource_links':
				do_action( 'epkb_ml_resource_links_module', $new_config );
				// echo '<style>' . apply_filters( 'epkb_ml_resource_links_module_styles', '', $new_config ) . '</style>;
				break;

			// Article Page features update entire Article HTML
			case 'article-page-settings':
			case 'article-page-search-box':
			case 'article-page-sidebar':
			case 'article-page-toc':
			case 'article-page-ratings':
				$template_style_escaped = EPKB_Utilities::get_inline_style(
					' padding-top::       template_article_padding_top,
					padding-bottom::    template_article_padding_bottom,
					padding-left::      template_article_padding_left,
					padding-right::     template_article_padding_right,
					margin-top::        template_article_margin_top,
					margin-bottom::     template_article_margin_bottom,
					margin-left::       template_article_margin_left,
					margin-right::      template_article_margin_right,', $new_config );

				// CSS Article Reset / Defaults
				$article_class_escaped = '';
				if ( $new_config[ 'templates_for_kb_article_reset'] === 'on' ) {
					$article_class_escaped .= 'eckb-article-resets ';
				}
				if ( $new_config[ 'templates_for_kb_article_defaults'] === 'on' ) {
					$article_class_escaped .= 'eckb-article-defaults ';
				}	?>

				<div class="eckb-kb-template <?php echo $article_class_escaped; ?>" <?php echo $template_style_escaped; ?>>	      <?php

					// LATER TODO retrieve article from Article Setup class      	?>

				</div>	<?php
				break;

			// Archive Page features update entire Archive HTML
			case 'archive-page-settings':
				if ( EPKB_KB_Handler::is_kb_category_taxonomy( $GLOBALS['taxonomy'] ) ) {
					EPKB_Category_Archive_Setup::get_category_archive_page_v3( $new_config );
				} else if (  EPKB_KB_Handler::is_kb_tag_taxonomy( $GLOBALS['taxonomy'] ) ) {
					EPKB_Tag_Archive_Setup::get_tag_archive_page( $new_config );
				}
				break;

			default:
				break;
		}

		$preview_html = ob_get_clean();

		$updated_settings_html = self::get_updated_settings_html( $new_config, $kb_page_type, $setting_name, $feature_name, $settings_row_number );
		$inline_styles = self::get_inline_styles( $new_config, $orig_config, $kb_page_type );

		wp_send_json_success( $inline_styles + array(
				'preview_html' => $preview_html,
				'layout_settings_html' => $updated_settings_html['layout_settings_html'],
				'layout_settings_html_temp' => $updated_settings_html['layout_settings_html_temp'],
				'faqs_design_settings' => $faqs_design_settings,
				'categories_articles_design_settings' => $categories_articles_design_settings,
			) );
	}

	/**
	 * For some settings need to reload entire page - populate the KB config with the changes which are not saved yet
	 * @param $kb_config
	 * @return array|mixed|null
	 */
	public static function fe_preview_config( $kb_config ) {

		// only for reloading if user changes KB Template option
		if ( EPKB_Utilities::post( 'epkb_fe_reload_mode' ) != 'on' ) {
			return $kb_config;
		}

		// use cache
		static $cached_kb_config = null;
		if ( ! empty( $cached_kb_config ) ) {
			return $cached_kb_config;
		}

		$config = self::merge_new_and_old_kb_config( true, true );
		$new_config = $config['new_config'];

		$cached_kb_config = $new_config;

		return $new_config;
	}

    /**
     * AJAX save all settings
     * Handles nonce verification, user permissions check, data retrieval, and configuration update.
     */
    public function save_settings() {

        EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_frontend_editor_write' );

	    $config = self::merge_new_and_old_kb_config();
	    $orig_config = $config['orig_config'];
	    $new_config = $config['new_config'];
		$kb_id = $config['kb_id'];

		// Check if the user has permission to save settings
		if ( ! EPKB_Utilities::is_positive_int( $kb_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid Knowledge Base ID', 'echo-knowledge-base' ) ) );
		}

		// Update the main page configuration
		self::update_module_position( $orig_config, $new_config );

		self::update_main_page( $kb_id, $orig_config, $new_config );

        // Send success response if update was successful
        wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully', 'echo-knowledge-base' ) ) );
    }

	private static function merge_new_and_old_kb_config( $merge_module_position=true, $page_reload=false ) {

		// use cache
		static $cached_kb_config = null;
		if ( ! empty( $cached_kb_config ) ) {
			return $cached_kb_config;
		}

		$kb_id = EPKB_Utilities::post( 'kb_id', 0 );
		if ( ! EPKB_Utilities::is_positive_int( $kb_id ) ){
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 801 ) );
		}

		$value_type = $page_reload ? 'db-config-json' : 'db-config';
		$new_config = EPKB_Utilities::post( 'new_kb_config', [], $value_type );

		$orig_config = epkb_get_instance()->kb_config_obj->get_kb_config( $kb_id, true );
		if ( is_wp_error( $orig_config ) ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 8 ) );
		}
		$orig_config = EPKB_Core_Utilities::get_add_ons_config( $kb_id, $orig_config );
		if ( $orig_config === false ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 149 ) );
		}

		if ( $merge_module_position ) {
			$new_config = self::update_module_position( $orig_config, $new_config );
		}

		$new_config = array_merge( $orig_config, $new_config );

		$cached_kb_config = [ 'kb_id' => $kb_id, 'orig_config' => $orig_config, 'new_config' => $new_config ];

		return $cached_kb_config;
	}

	/**
	 * Save KB Main Page configuration
	 *
	 * @param $editor_kb_id
	 * @param $orig_config
	 * @param $new_config
	 */
	private static function update_main_page( $editor_kb_id, $orig_config, $new_config ) {

		$chosen_preset = empty( $new_config['theme_presets'] ) || $new_config['theme_presets'] == 'current' ? '' : $new_config['theme_presets'];

		// if user selected a theme presets then Copy search setting from main to article and update icons
		if ( ! empty( $chosen_preset ) ) {
			$new_config['theme_name'] = $chosen_preset;
			$new_config = EPKB_KB_Wizard_Themes::copy_search_mp_to_ap( $new_config );
			EPKB_Core_Utilities::get_or_update_new_category_icons( $new_config, $chosen_preset, true );
		}

		// detect user changed kb template
		if ( $orig_config['templates_for_kb'] != $new_config['templates_for_kb'] ) {
			$new_config['article_content_enable_article_title'] = $new_config['templates_for_kb'] == 'current_theme_templates' ? 'off' : 'on';
		}

		EPKB_Core_Utilities::start_update_kb_configuration( $editor_kb_id, $new_config );
	}

	private static function update_module_position( $orig_config, $new_config ) {

		// ensure at least one module is set
		$module_counter = 0;
		foreach ( self::$modules as $module ) {

			// for unavailable module the module position and the rest of settings are missing
			if ( empty( $new_config[ $module . '_module_position' ] ) ) {
				continue;
			}

			if ( $new_config[ $module . '_module_position' ] == 'none' ) {
				$module_counter++;
			}
		}

		if ( $module_counter == 0 ) {
			return $new_config;
		}

		$initial_new_config = $new_config;

		// reset original module positions
		for ( $row_index = 1; $row_index <= EPKB_Modular_Main_Page::MAX_ROWS; $row_index++ ) {
			$new_config[ 'ml_row_' . $row_index . '_module' ] = 'none';
			$new_config[ 'ml_row_' . $row_index . '_desktop_width' ] = 1400;
			$new_config[ 'ml_row_' . $row_index . '_desktop_width_units' ] = 'px';
		}

		// update new module positions
		foreach ( self::$modules as $module ) {

			// for unavailable module the module position and the rest of settings are missing
			if ( ! isset( $new_config[ $module . '_module_position' ] ) ) {
				continue;
			}

			$new_module_row_number = $new_config[ $module . '_module_position' ];
			if ( $new_module_row_number == 'none' ) {
				continue;
			}

			$new_config[ 'ml_row_' . $new_module_row_number . '_module' ] = $module;

			// original config: contains module initial row number
			// new config: contains the actual current values (row number, width) for that row because we do not move the width controls, so they still have the same as HTML row number.
			$prev_module_row = self::get_module_row_number( $module, $orig_config );
			if ( $prev_module_row != 'none' ) {
				$new_config[ 'ml_row_' . $new_module_row_number . '_desktop_width' ] = $initial_new_config[ 'ml_row_' . $prev_module_row . '_desktop_width' ];
				$new_config[ 'ml_row_' . $new_module_row_number . '_desktop_width_units' ] = $initial_new_config[ 'ml_row_' . $prev_module_row . '_desktop_width_units' ];
			}
		}

		return $new_config;
	}

	private static function get_updated_settings_html( $new_config, $kb_page_type, $setting_name, $feature_name, $settings_row_number ) {

		$prev_link_css_id = EPKB_Utilities::post( 'prev_link_css_id' );

		$layout_settings_html = '';
		$layout_settings_html_temp = '';

		$module_row_number = $settings_row_number == 'none' ? 1 : $settings_row_number;
		$all_main_page_features = self::$modules;
		if ( in_array( $feature_name, $all_main_page_features ) ) {
			$new_config['ml_row_' . $module_row_number . '_module'] = $feature_name;
		}

		$config_page = new EPKB_Config_Settings_Page( $new_config, true );
		$features_config = $config_page->get_vertical_tabs_config();

		switch ( $kb_page_type ) {
			case 'main-page':
				$current_css_file_slug = self::get_current_css_slug( $new_config );

				// FUTURE TODO 'advanced_search_mp_presets', 'faq_preset_name', layout presets

				// only on layout switch
				if ( $setting_name == 'kb_main_page_layout' && 'epkb-' . $current_css_file_slug . '-css' != $prev_link_css_id ) {
					// shared settings for all layouts are assigned to the first feature container (required by inherited logic from Settings UI)
					$layout_settings_html_temp = self::display_feature_settings( $features_config['main-page']['sub_tabs'][0]['contents'], true );

					$layout_settings_html = self::get_module_position_field( $feature_name, $new_config['categories_articles_module_position'] );
					$layout_settings_html .= self::display_feature_settings( $features_config['main-page']['sub_tabs'][ $module_row_number - 1 ]['contents'], true );
				}
				break;
			case 'article-page':
				// LATER TODO 'advanced_search_ap_presets'
				break;
		}

		return array(
			'layout_settings_html' => $layout_settings_html,
			'layout_settings_html_temp' => $layout_settings_html_temp,
		);
	}

	private static function get_inline_styles( $new_config, $orig_config, $kb_page_type ) {

		$prev_link_css_id = EPKB_Utilities::post( 'prev_link_css_id' );

		$link_css = '';
		$link_css_rtl = '';
		$elay_link_css = '';
		$current_css_file_slug = '';

		switch ( $kb_page_type ) {

			case 'main-page':

				$current_css_file_slug = self::get_current_css_slug( $new_config );

				// get CSS file accordingly to the current slug if layout change detected
				if ( 'epkb-' . $current_css_file_slug . '-css' != $prev_link_css_id ) {

					// apply the modules position here to have the inline CSS updated properly (since the inline CSS rendering relying on ml_row_{n}_module settings)
					$new_config = self::update_module_position( $orig_config, $new_config );

					$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

					$elay_link_css = EPKB_Core_Utilities::initialize_elegant_layouts( $new_config, $suffix );

					$link_css = '<link rel="stylesheet" id="epkb-' . $current_css_file_slug . '-css" href="' . Echo_Knowledge_Base::$plugin_url . 'css/' . $current_css_file_slug . $suffix . '.css?ver=' . Echo_Knowledge_Base::$version . '" media="all">';
					if ( is_rtl() ) {
						$link_css_rtl = '<link rel="stylesheet" id="epkb-' . $current_css_file_slug . '-rtl-css" href="' . Echo_Knowledge_Base::$plugin_url . 'css/' . $current_css_file_slug . '-rtl' . $suffix . '.css?ver=' . Echo_Knowledge_Base::$version . '" media="all">';
					}
				}
				break;

			case 'article-page':
				$current_css_file_slug = 'ap-frontend-layout';
				break;

			case 'archive-page':
				$current_css_file_slug = 'cp-frontend-layout';
				break;

			default:
				break;
		}

		// get updated inline styles
		$inline_styles = epkb_frontend_kb_theme_styles_now( $new_config, $current_css_file_slug );

		return array(
			'inline_styles'	=> EPKB_Utilities::minify_css( $inline_styles ),
			'link_css'		=> $link_css,
			'link_css_rtl'	=> $link_css_rtl,
			'elay_link_css'		=> empty( $elay_link_css['elay_link_css'] ) ? '' : $elay_link_css['elay_link_css'],
			'elay_link_css_rtl'	=> empty( $elay_link_css['elay_link_css_rtl'] ) ? '' : $elay_link_css['elay_link_css_rtl']
		);
	}

	/**
	 * Icons for the FE first page
	 * @param $feature_name
	 * @return string
	 */
	private static function get_features_icon_escaped( $feature_name ) {    // LATER TODO: update icons
		switch ( $feature_name ) {

			// main page features
			case 'search':
				return 'epkbfa epkbfa-search';
			case 'categories_articles':
				return 'epkbfa epkbfa-list-alt';
			case 'articles_list':
				return 'epkbfa epkbfa-list-alt';
			case 'faqs':
				return 'epkbfa epkbfa-list-alt';
			case 'resource_links':
				return 'epkbfa epkbfa-list-alt';

			// article page features
			case 'article-page-settings':
				return 'epkbfa epkbfa-list-alt';
			case 'article-page-search-box':
				return 'epkbfa epkbfa-list-alt';
			case 'article-page-sidebar':
				return 'epkbfa epkbfa-list-alt';
			case 'article-page-toc':
				return 'epkbfa epkbfa-list-alt';
			case 'article-page-ratings':
				return 'epkbfa epkbfa-list-alt';

			// archive-page features
			case 'archive-page-settings':
				return 'epkbfa epkbfa-list-alt';

			default:
				return '';
		}
	}

	private static function get_module_row_number( $module_name, $kb_config ) {
		for ( $i = 1; $i <= EPKB_Modular_Main_Page::MAX_ROWS; $i++ ) {
			if ( $kb_config['ml_row_' . $i . '_module'] == $module_name ) {
				return $i;
			}
		}
		return 'none';
	}

	private static function get_module_position_field( $module_name, $module_position ) {

		$output = self::display_feature_settings( array( array(
			'title' => __( 'Enable Feature', 'echo-knowledge-base' ),
			'body_html' => EPKB_HTML_Elements::checkbox_toggle( array(
				'checked' => $module_position == 'none',
				'name' => $module_name,
				'input_group_class' => 'epkb-row-module-position epkb-row-module-position--' . $module_name,
				'return_html' => true,
				'group_data' => array(
					'module' => $module_name,
				),
			) ). EPKB_HTML_Elements::radio_buttons_horizontal( array(
				'name' => $module_name . '_module_position',
				'value' => '',
				'options' => [ 'move-up' => __( 'Move Up', 'echo-knowledge-base' ), 'move-down' => __( 'Move Down', 'echo-knowledge-base' ) ],
				'input_group_class' => 'epkb-row-module-position epkb-row-module-position--' . $module_name,
				'return_html' => true,
				'group_data' => array(
					'module' => $module_name,
				),
			) ),
			'css_class' => 'epkb-fe__settings-section--module-position' ),
		), true );

		return $output;
	}

	private static function get_current_css_slug( $kb_config ) {
		switch ( $kb_config['kb_main_page_layout'] ) {
			case 'Tabs': return 'mp-frontend-modular-tab-layout';
			case 'Categories': return 'mp-frontend-modular-category-layout';
			case 'Grid': return EPKB_Utilities::is_elegant_layouts_enabled() ? 'mp-frontend-modular-grid-layout' : 'mp-frontend-modular-basic-layout';
			case 'Sidebar': return EPKB_Utilities::is_elegant_layouts_enabled() ? 'mp-frontend-modular-sidebar-layout' : 'mp-frontend-modular-basic-layout';
			case 'Classic': return 'mp-frontend-modular-classic-layout';
			case 'Drill-Down': return 'mp-frontend-modular-drill-down-layout';
			case 'Basic':
			default: return 'mp-frontend-modular-basic-layout';
		}
	}
} 