<?php

/**
 * Visual Helper - add a Visual Helper
 *
 */
class EPKB_Visual_Helper {

	/**
	 * Constructor - add actions for Visual Helper functionality
	 */
	public function __construct() {
		add_action( 'wp_ajax_epkb_visual_helper_update_switch_settings',  array( $this, 'update_switch_settings_handler' ) );
		add_action( 'wp_ajax_nopriv_epkb_visual_helper_update_switch_settings', array( 'EPKB_Utilities', 'user_not_logged_in' ) );
		add_action( 'wp_ajax_epkb_visual_helper_switch_template',  array( $this, 'switch_template_handler' ) );
		add_action( 'wp_ajax_nopriv_epkb_visual_helper_switch_template', array( 'EPKB_Utilities', 'user_not_logged_in' ) );
	}

	/**
	 * Add Visual Helper Elements to KB Main Page
	 */
	public function epkb_generate_page_content( $settings_info_icons, $kb_config ) {
		wp_enqueue_style( 'epkb-frontend-visual-helper' );
		wp_enqueue_script( 'epkb-frontend-visual-helper' );

		ob_start(); ?>

		<div class="epkb-vshelp__wrapper">  <?php
			$this->epkb_visual_helper_toggle();
			$this->epkb_visual_helper_side_menu( $kb_config ); ?>
		</div>  <?php

		$this->generate_info_components( $settings_info_icons );

		echo ob_get_clean();
	}

	/**
	 * Generate Visual Helper Toggle HTML element
	 */
	private function epkb_visual_helper_toggle() {
		$kb_id = EPKB_Utilities::get_eckb_kb_id();
		ob_start(); ?>

		<div class="epkb-vshelp-toggle-wrapper">
			<div class="epkb-vshelp-icon-wrapper">
				<span class="ep_font_icon_info"></span>
			</div>
			<div class="epkb-vshelp-title">
				<span class="epkb-vshelp-title__text"><?php esc_html_e( 'Toggle Visual Helper', 'echo-knowledge-base' ); ?></span>
			</div>
			<div class="epkb-settings-control">
				<label class="epkb-settings-control-toggle js-epkb-side-menu-toggle">
					<input type="checkbox" class="epkb-settings-control__input__toggle" value="on" name="article_content_enable_article_title" checked="checked">
					<span class="epkb-settings-control__input__label" data-on="<?php esc_html_e( 'On', 'echo-knowledge-base' ); ?>" data-off="<?php esc_html_e( 'Off', 'echo-knowledge-base' ); ?>"></span>
					<span class="epkb-settings-control__input__handle"></span>
				</label>
			</div>
			<div class="epkb-vshelp-hide-switcher epkb-vshelp-hide-switcher--hidden" data-kbid="<?php echo $kb_id; ?>">
				<span class="epkb-vshelp-hide-switcher__icon epkbfa epkbfa-times-circle"></span>
			</div>
		</div>		<?php

		echo ob_get_clean();
	}

	/**
	 * Generate Visual Helper Side Menu HTML element
	 */
	private function epkb_visual_helper_side_menu( $kb_config ) {


		$keys_to_check                                  = ['ml_row_1_module', 'ml_row_2_module', 'ml_row_3_module', 'ml_row_4_module', 'ml_row_5_module'];

		$search_row_desktop_width_location              = 'ml_row_1_desktop_width_units';
		$search_row_desktop_width_units_location        = 'ml_row_1_desktop_width_units_units';
		$category_row_desktop_width_location            = 'ml_row_2_desktop_width_units';
		$category_row_desktop_width_units_location      = 'ml_row_2_desktop_width_units_units';
		$category_row_num = 1;
		$search_row_num = 1;
		$search_active = false;
		$category_article_active = false;

		foreach ( $keys_to_check as $key ) {
			if ( isset( $kb_config[$key] ) ) {
				if ( $kb_config[$key] === 'categories_articles' ) {

					$category_row                                = substr($key, 0,-7 );
					$category_row_desktop_width_location         = $category_row.'_desktop_width';
					$category_row_desktop_width_units_location   = $category_row.'_desktop_width_units';

					preg_match('/\d+/', $category_row, $matches);
					$category_row_num = $matches[0];
					$category_article_active = true;

				} elseif ( $kb_config[$key] === 'search' ) {
					$search_row                                = substr($key, 0,-7 );
					$search_row_desktop_width_location         = $search_row.'_desktop_width';
					$search_row_desktop_width_units_location   = $search_row.'_desktop_width_units';

					preg_match('/\d+/', $search_row, $matches);
					$search_row_num = $matches[0];
					$search_active = true;
				}
			}
		}


		$current_page_template  = $kb_config[ 'templates_for_kb' ];
		$search_row_width = $kb_config[ $search_row_desktop_width_location ];
		$search_row_width_units = $kb_config[ $search_row_desktop_width_units_location ];

		$category_row_width = $kb_config[ $category_row_desktop_width_location ];
		$category_row_width_units = $kb_config[ $category_row_desktop_width_units_location ];
        $kb_id = $kb_config['id'];

		ob_start(); ?>
		<div class="epkb-vshelp-side-menu-wrapper">
			<div class="epkb-vshelp-side-menu-header">
				<div class="epkb-vshelp-icon-wrapper">
					<span class="ep_font_icon_info"></span>
				</div>
				<div class="epkb-vshelp-title">
					<span><?php esc_html_e( 'Page Information', 'echo-knowledge-base' ); ?></span>
				</div>
			</div>
			<div class="epkb-vshelp-side-menu-body">
				<div class="epkb-vshelp-accordion-wrapper">
					<div class="epkb-vshelp-accordion-header">
						<span><?php esc_html_e( 'Issues with the page layout, header, or menu?', 'echo-knowledge-base' ); ?></span>
						<button class="epkb-vshelp-accordion-header__button js-epkb-accordion-toggle" id="epkb-vshelp-switch-template"><?php esc_html_e( 'Details', 'echo-knowledge-base' ); ?></button>
					</div>
					<div class="epkb-vshelp-accordion-body" style="display: none">
						<div class="epkb-vshelp-accordion-body-content">
							<p> <?php
								echo sprintf( esc_html__( 'Knowledge Base provides two template choices: %sKB Template%s and %sCurrent Theme Template%s.', 'echo-knowledge-base' ), '<strong>', '</strong>', '<strong>', '</strong>' ) . ' ' .
									 esc_html__( 'Select the most suitable option according to their requirements or their theme’s behavior.', 'echo-knowledge-base' ) . ' ' .
									'<a href="https://www.echoknowledgebase.com/documentation/current-theme-template-vs-kb-template/" target="_blank" rel="nofollow">' . esc_html__(  'Learn More', 'echo-knowledge-base' ) . '</a> <span class="epkbfa epkbfa-external-link"></span>';  ?>
							</p>
							<hr>
							<p><?php echo esc_html__( 'Try to switch the template if having layout issues', 'echo-knowledge-base' ) . ':'; ?></p>
                            <div class="epkb-vshelp-accordion-body__template-toggle epkb-settings-control">
                                <label class="epkb-settings-control-circle-radio">
                                    <?php esc_html_e( 'KB Template', 'echo-knowledge-base' ); ?>
                                    <input type="radio" name="ekb_current_template" value="kb_templates" data-kbid="<?php echo esc_attr( $kb_id ); ?>" class="epkb-settings-control-circle-radio__radio" <?php echo esc_attr( $current_page_template === 'kb_templates' ? 'checked="checked"' : '' ); ?>>
                                    <span class="epkb-settings-control-circle-radio__checkmark"></span>
                                </label>
                                <label class="epkb-settings-control-circle-radio">
		                            <?php esc_html_e( 'Current Theme Template', 'echo-knowledge-base' ); ?>
                                    <input type="radio" name="ekb_current_template" value="current_theme_templates" data-kbid="<?php echo esc_attr( $kb_id ); ?>" class="epkb-settings-control-circle-radio__radio" <?php echo esc_attr( $current_page_template === 'current_theme_templates' ? 'checked="checked"' : '' ); ?>>
                                    <span class="epkb-settings-control-circle-radio__checkmark"></span>
                                </label>
                            </div>
						</div>
					</div>
				</div>
				<div class="epkb-vshelp-accordion-wrapper">
					<div class="epkb-vshelp-accordion-header">
						<span><?php esc_html_e( 'Is this page or search box too narrow?', 'echo-knowledge-base' ); ?></span>
						<button class="epkb-vshelp-accordion-header__button js-epkb-accordion-toggle"><?php esc_html_e( 'Details', 'echo-knowledge-base' ); ?></button>
					</div>
					<div class="epkb-vshelp-accordion-body" style="display: none">
						<div class="epkb-vshelp-accordion-body-content">							<?php

							if ( $search_active ) { ?>
								<h5 class="epkb-vshelp-accordion-body-content__title"><strong><?php echo esc_html__( 'Search Box Width', 'echo-knowledge-base' ); ?></strong></h5>
								<table>
									<tr>
										<td><?php echo sprintf( esc_html__( 'Total Page width is', 'echo-knowledge-base' ), $search_row_num ) . ' '; ?><span class='js-epkb-mp-width'>-</span></td>
									</tr>
									<tr>
										<td><?php echo sprintf( esc_html__( 'The KB setting is set to ', 'echo-knowledge-base' ), $search_row_num ); echo ' ' . $search_row_width . $search_row_width_units .
												( $search_row_width_units == '%' ? ' ' . esc_html__( 'of the total page width.', 'echo-knowledge-base' ) : '' ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__( 'The actual search box width is', 'echo-knowledge-base' ) . ' '; ?><span class="js-epkb-mp-search-width">-</span></td>
									</tr>
								</table><?php
								/* if ( $search_row_width_units == '%' ) { ?>
									<div class="epkb-vshelp-accordion-body-content__note"><?php
										esc_html_e( 'Note: The px value for the width should be the configured percentage.', 'echo-knowledge-base' ); ?>
									</div>								<?php
								} */ ?>
								<div class="epkb-vshelp-accordion-body-content__spacer"></div><?php
							}
							if ( $category_article_active ) { ?>
								<h5 class="epkb-vshelp-accordion-body-content__title"><strong><?php echo esc_html__( 'Categories and Articles Width', 'echo-knowledge-base' ); ?></strong></h5>
								<table>
									<tr>
										<td><?php echo sprintf( esc_html__( 'The KB setting is set to ', 'echo-knowledge-base' ), $category_row_num ); echo ' ' . $category_row_width . $category_row_width_units .
												( $category_row_width_units == '%' ? ' ' . esc_html__( 'of the total page width.', 'echo-knowledge-base' ) : '' ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__( 'The actual Categories and Articles width is', 'echo-knowledge-base' ) . ' '; ?><span class="js-epkb-mp-width-container">-</span></td>
									</tr>
								</table>    <?php
								/* if ( $category_row_width_units == '%' ) { ?>
									<div class="epkb-vshelp-accordion-body-content__note"><?php
										esc_html_e( 'Note: The detected px value should be a percentage of your page width.', 'echo-knowledge-base' ); ?>
									</div>								<?php
								} */ ?>
								<div class="epkb-vshelp-accordion-body-content__spacer"></div>  <?php
							} ?>

							<h5><strong><?php esc_html_e( 'Troubleshooting', 'echo-knowledge-base' ); ?></strong></h5>
							<p><?php echo esc_html__( 'If the value you set in the KB settings does not match the actual value, it may be because your theme or page
								builder is limiting the overall width. In such cases, the KB settings cannot exceed the maximum width allowed
								by your theme or page builder. Try the following', 'echo-knowledge-base' ) . ':'; ?></p>
							<ul>
								<li><?php echo esc_html__( 'Check your theme settings.', 'echo-knowledge-base' ); ?></li>
								<li><?php echo sprintf( esc_html__( 'If the KB Shortcode is inserted inside your page builder then you will need to check the section width of that page builder. %s', 'echo-knowledge-base' ),
										'<a href="https://www.echoknowledgebase.com/documentation/main-page-width-and-page-builders/" target="_blank" rel="nofollow">' . esc_html__( 'Learn more', 'echo-knowledge-base' ) . '</a> <span class="epkbfa epkbfa-external-link"> </span>' ); ?></li>  <?php

								if ( $current_page_template == 'current_theme_templates' ) { ?>
									<li><?php echo sprintf( esc_html__( 'You are currently using the Current theme template, which may be restricting the widths. Consider switching to the KB template. %s', 'echo-knowledge-base' ),
											'<a href="https://www.echoknowledgebase.com/documentation/current-theme-template-vs-kb-template/" target="_blank" rel="nofollow">' . esc_html__( 'Learn more', 'echo-knowledge-base' ) . '</a> <span class="epkbfa epkbfa-external-link"></span>' ); ?></li>								<?php
								}								?>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>        <?php

		echo ob_get_clean();
	}

	/**
	 * Generate info components for the visual helper
	 */
	private function generate_info_components( $settings_info_icons ) {
		ob_start();
		foreach ( $settings_info_icons as $key => $info_icon ) {
			echo $this->display_info_modal( $key, $info_icon );
		}
		echo ob_get_clean();
	}

	/**
	 * Render info modal windows
	 * @param $section_id
	 * @param $info_icon
	 * @return string
	 */
	private function display_info_modal( $section_id, $info_icon ) {
		ob_start(); ?>

		<div class="epkb-vshelp-info-modal epkb-vshelp-info-modal--<?php echo esc_attr( $section_id ); ?>" data-section-id="<?php echo esc_attr( $section_id ); ?>" data-selectors="<?php echo esc_attr( $info_icon['connected_selectors'] ?? '' ); ?>">
			<div class="epkb-vshelp-info-modal__content">   <?php
				if ( $modalTitle = ( $info_icon['modalTitle'] ?? false ) ) { ?>
					<h3 class="epkb-vshelp-info-modal__title">  <?php
						echo esc_html( $modalTitle ); ?>
					</h3>                <?php
				}

				if ( $modalSections = ( $info_icon['modalSections'] ?? false ) ) { ?>
					<div class="epkb-vshelp-info-modal__sections">  <?php

						foreach ( $modalSections as $section ) {
							$section_title = $section['title'] ?? false;
							$section_location = $section['location'] ?? false;
							$section_content = $section['content'] ?? false;
							$section_link = $section['link'] ?? array(); ?>

							<div class="epkb-vshelp-info-modal__section">   <?php

								if ( $section_title ) { ?>
									<h4 class="epkb-vshelp-info-modal__section-title">										<?php
										echo esc_html( $section_title ); ?>
									</h4>   <?php
								}
								if ( $section_location ) { ?>
									<div class="epkb-vshelp-info-modal__section-location">										<?php
										echo esc_html( $section_location ); ?>
									</div>   <?php
								}

								if ( $section_content ) { ?>

									<p class="epkb-vshelp-info-modal__section-content">     <?php
										echo $section_content; ?>
									</p>    <?php

									if ( ! empty( $section_link ) ) { ?>
										<div class="epkb-vshelp-info-modal__section-link-wrapper">
											<a class="epkb-vshelp-info-modal__section-link "
											   href="<?php echo esc_url( $section_link ); ?>"
											   target="_blank"> <?php
												echo esc_html__( 'Configure Here', 'echo-knowledge-base' ); ?>
											</a>
											<span class="ep_font_icon_external_link epkb-vshelp-info-modal__section-link-icon"></span>
										</div>			<?php
									}
								} ?>
							</div>  <?php
						} ?>
					</div>  <?php
				} ?>
			</div>
		</div><?php

		return ob_get_clean();
	}

	/**
	 * Change switcher state for visual helper - AJAX handler
	 */
	public function update_switch_settings_handler() {

		$kb_id = EPKB_Utilities::post( 'kb_id', EPKB_KB_Config_DB::DEFAULT_KB_ID );
		epkb_get_instance()->kb_config_obj->set_value( $kb_id, 'visual_helper_switch_visibility_toggle', 'off' );

		wp_send_json_success( esc_html__( 'Settings saved', 'echo-knowledge-base' ) );
	}

    /**
     * Switch KB page template
     */
    public function switch_template_handler() {

        $kb_id = EPKB_Utilities::post( 'kb_id', EPKB_KB_Config_DB::DEFAULT_KB_ID );
        $template = EPKB_Utilities::post( 'current_template', 'kb_templates' );

        epkb_get_instance()->kb_config_obj->set_value( $kb_id, 'templates_for_kb', $template );

        wp_send_json_success( esc_html__( 'Settings saved', 'echo-knowledge-base' ) );
    }
}