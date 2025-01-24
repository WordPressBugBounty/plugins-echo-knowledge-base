<?php if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

final class EPKB_Sidebar_Layout_Block extends EPKB_Abstract_Block {
	const EPKB_BLOCK_NAME = 'sidebar-layout';

	protected $block_name = 'sidebar-layout';
	protected $block_var_name = 'sidebar_layout';
	protected $block_title = 'KB Sidebar Layout';
	protected $icon = 'editor-table';
	protected $keywords = ['knowledge base', 'layout', 'articles', 'categories'];	// is internally wrapped into _x() - see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#internationalization

	public function __construct( $init_hooks = true ) {
		parent::__construct( $init_hooks );

		// when insert blocks programmatically we need to utilize non-static methods of the block classes, but we do not need hooks for this
		if ( ! $init_hooks ) {
			return;
		}

		// must be assigned to hook inside child class to enqueue unique assets for each block type
		add_action( 'enqueue_block_assets', array( $this, 'register_block_assets' ) ); // Frontend / Backend

		// must be assigned only in layout block
		add_action( 'save_post', array( $this, 'update_templates_for_kb_setting_on_save_post' ), 10, 3 );
	}

	/**
	 * Check if the block is available
	 * @return bool
	 */
	protected static function is_block_available() {
		return class_exists( 'EL'.'AY_Blocks' );
	}

	/**
	 * Return handle for block public styles
	 * @return string
	 */
	protected function get_block_public_styles_handle() {
		return 'elay-' . $this->block_name . '-block';
	}

	/**
	 * Register add-on's block styles
	 * @param $suffix
	 * @param $block_styles_dependencies
	 * @return void
	 */
	protected function register_block_public_styles( $suffix, $block_styles_dependencies ) {
		if ( ! self::is_block_available() ) {
			return;
		}
		EPKB_Core_Utilities::register_elay_block_public_styles( $this->block_name, $suffix, $block_styles_dependencies );
	}

	protected function register_block_public_scripts( $suffix ) {
		if ( ! self::is_block_available() ) {
			return;
		}
		EPKB_Core_Utilities::register_elay_block_public_scripts( $suffix );
	}

	/**
	 * Return the actual specific block content
	 * @param $block_attributes
	 */
	public function render_block_inner( $block_attributes ) {

		$handler = new EPKB_Modular_Main_Page();
		$handler->setup_layout_data_for_blocks( $block_attributes );

		$intro_text = apply_filters( 'eckb_main_page_sidebar_intro_text', '', $block_attributes['id'] );
		$temp_article = new stdClass();
		$temp_article->ID = 0;
		$temp_article->post_title = esc_html__( 'Demo Article', 'echo-knowledge-base' );
		// Use 'post' for the filter as it is the same content as in the usual page/post
		$temp_article->post_content = wp_kses( $intro_text, EPKB_Utilities::get_extended_html_tags( true ) );
		$temp_article = new WP_Post( $temp_article );
		$block_attributes['sidebar_welcome'] = 'on';
		$block_attributes['article_content_enable_back_navigation'] = 'off';
		$block_attributes['prev_next_navigation_enable'] = 'off';
		$block_attributes['article_content_enable_rows'] = 'off';

		// TODO: temporary hardcoded
		$block_attributes['article_sidebar_component_priority'] = array(
			'kb_sidebar_left' => '0',
			'kb_sidebar_right' => '0',
			'toc_left' => '0',
			'toc_content' => '0',
			'toc_right' => '1',
			'nav_sidebar_left' => '1',
			'nav_sidebar_right' => '0',
		);
		$block_attributes['article_nav_sidebar_type_left'] = 'eckb-nav-sidebar-v1';
		$block_attributes['article_nav_sidebar_type_right'] = 'eckb-nav-sidebar-none';
		$block_attributes['article-left-sidebar-match'] = 'off';
		$block_attributes['article-right-sidebar-match'] = 'off';
		$block_attributes['article-mobile-break-point-v2'] = '768';
		$block_attributes['article-tablet-break-point-v2'] = '1025';
		$block_attributes['article-left-sidebar-toggle'] = 'on';
		$block_attributes['article-right-sidebar-toggle'] = 'on';

		$layout_output = EPKB_Articles_Setup::get_article_content_and_features( $temp_article, $temp_article->post_content, $block_attributes );
		$handler->set_sidebar_layout_content( $layout_output );

		// show message that articles are coming soon if the current KB does not have any Category
		if ( $handler->has_kb_categories() ) {

			// render content
			$handler->categories_articles_module( $block_attributes );

		} else {
			// render no content message
			$handler->show_categories_missing_message();
		}
	}

	/**
	 * Add required specific attributes to work correctly with KB core functionality
	 * @param $block_attributes
	 * @return array
	 */
	protected function add_this_block_required_kb_attributes( $block_attributes ) {
		$block_attributes['kb_main_page_layout'] = EPKB_Layout::SIDEBAR_LAYOUT;
		return $block_attributes;
	}

	/**
	 * Block dedicated inline styles
	 * @param $block_attributes
	 * @return string
	 */
	protected function get_this_block_inline_styles( $block_attributes ) {

		$block_ui_specs = $this->get_block_ui_specs();

		$output = EPKB_Modular_Main_Page::get_layout_sidebar_inline_styles( $block_attributes );

		$output .= apply_filters( 'epkb_sidebar_layout_block_inline_styles', '', $block_attributes, $block_ui_specs );

		return $output;
	}

	/**
	 * Return list of all typography settings for the current block
	 * @return array
	 */
	protected function get_this_block_typography_settings() {
		return array(
			'sidebar_section_category_typography_controls',
			'sidebar_section_category_typography_desc_controls',
			'sidebar_section_subcategory_typography_controls',
			'sidebar_section_body_typography_controls',
			'article_typography_controls',
		);
	}

	/**
	 * Return list attributes with custom specs - they are not allowed in attributes when registering block, thus need to keep them separately
	 * @return array[]
	 */
	protected function get_this_block_ui_config() {
		return array(

			// TAB: Settings
			'settings' => array(
				'title' => esc_html__( 'Settings', 'echo-knowledge-base' ),
				'icon' => ' ' . 'epkbfa epkbfa-cog',
				'groups' => array(

					// GROUP: General
					'general' => array(
						'title' => esc_html__( 'General', 'echo-knowledge-base' ),
						'fields' => array(
							'kb_id' => EPKB_Blocks_Settings::get_kb_id_setting(),
							'kb_block_template_toggle' => EPKB_Blocks_Settings::get_kb_block_template_toggle(),
							'templates_for_kb' => EPKB_Blocks_Settings::get_kb_legacy_template_toggle(),
							'mention_kb_block_template' => EPKB_Blocks_Settings::get_kb_block_template_mention(),
							'sidebar_main_page_intro_text' => array(
								'setting_type' => 'text',      // TODO
							),
						),
					),

					// GROUP: Categories
					'category-box' => array(
						'title' => esc_html__( 'Categories', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_top_categories_collapsed' => array(
								'setting_type' => 'toggle'
							),
							'sidebar_section_desc_text_on' => array(
								'setting_type' => 'toggle'
							),
							'sidebar_category_empty_msg' => array(
								'setting_type' => 'text',
							),
						),
					),

					// GROUP: Articles
					'articles-list' => array(
						'title' => esc_html__( 'Articles', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_nof_articles_displayed' => array(
								'setting_type' => 'range',
							),
							'sidebar_article_underline' => array(
								'setting_type' => 'toggle'
							),
							'sidebar_collapse_articles_msg' => array(
								'setting_type' => 'text',
							),
							'sidebar_show_all_articles_msg' => array(
								'setting_type' => 'text',
							),
						),
					),

					// GROUP: Modular Sidebar
					'modular-sidebar' => array(
						'title' => esc_html__( 'Modular Sidebar', 'echo-knowledge-base' ),
						'fields' => array(
							'ml_categories_articles_sidebar_toggle' => array(
								'setting_type' => 'toggle',
							),
							'ml_categories_articles_sidebar_location' => array(
								'setting_type' => 'select_buttons_string',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_categories_articles_sidebar_desktop_width' => array(
								'setting_type' => 'select_buttons',
								'options' => array(
									25 => esc_html__( 'Small', 'echo-knowledge-base' ),
									28 => esc_html__( 'Medium', 'echo-knowledge-base' ),
									30 => esc_html__( 'Large', 'echo-knowledge-base' ),
								),
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_categories_articles_sidebar_position_1' => array(
								'setting_type' => 'dropdown',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_categories_articles_sidebar_position_2' => array(
								'setting_type' => 'dropdown',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_articles_list_nof_articles_displayed' => array(
								'setting_type' => 'number',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_articles_list_popular_articles_msg' => array(
								'setting_type' => 'text',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_articles_list_newest_articles_msg' => array(
								'setting_type' => 'text',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'ml_articles_list_recent_articles_msg' => array(
								'setting_type' => 'text',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
						),
					),

					// GROUP: Advanced
					'advanced' => array(
						'title' => esc_html__( 'Advanced', 'echo-knowledge-base' ),
						'fields' => array(
							'custom_css_class' => EPKB_Blocks_Settings::get_custom_css_class_setting(),
						)
					),
				),
			),

			// TAB: Style
			'style' => array(
				'title' => esc_html__( 'Style', 'echo-knowledge-base' ),
				'icon' => ' ' . 'epkbfa epkbfa-adjust',
				'groups' => array(

					// GROUP: General
					'general' => array(
						'title' => esc_html__( 'General', 'echo-knowledge-base' ),
						'fields' => array(
							'block_full_width_toggle' => EPKB_Blocks_Settings::get_block_full_width_setting(),
							'block_max_width' => EPKB_Blocks_Settings::get_block_max_width_setting(),
							'block_presets' => array(
								'setting_type' => 'presets_dropdown',
								'label' => esc_html__( 'Apply Preset', 'echo-knowledge-base' ),
								'presets' => EPKB_Blocks_Settings::get_all_preset_settings( self::EPKB_BLOCK_NAME, EPKB_Layout::SIDEBAR_LAYOUT ),
								'default' => 'current',
							),
						),
					),

					// GROUP: Category Box
					'category-box' => array(
						'title' => esc_html__( 'Category Box', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_side_bar_width' => array(
								'setting_type' => 'range',
							),
							'sidebar_side_bar_height_mode' => array(
								'setting_type' => 'select_buttons_string',
								'options'     => array(
									'side_bar_no_height' => esc_html__( 'Variable', 'echo-knowledge-base' ),
									'side_bar_fixed_height' => esc_html__( 'Fixed (Scrollbar)', 'echo-knowledge-base' )
								),
							),
							'sidebar_side_bar_height' => array(
								'setting_type' => 'range',
							),
							'sidebar_scroll_bar' => array(
								'setting_type' => 'select_buttons_string',
								'options'     => array(
									'slim_scrollbar'    => esc_html__( 'Slim', 'echo-knowledge-base' ),
									'default_scrollbar' => esc_html__( 'Default', 'echo-knowledge-base' )
								),
							),
							'sidebar_background_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_body_height' => array(
								'setting_type' => 'range',
							),
							'sidebar_section_body_padding' => array(
								'setting_type' => 'box_control_combined',
								'label' => esc_html__( 'Category Body Padding', 'echo-knowledge-base' ),
								'min' => 0,
								'max' => 50,
								'combined_settings' => array(
									'sidebar_section_body_padding_top' => array(
										'side' => 'top',
									),
									'sidebar_section_body_padding_bottom' => array(
										'side' => 'bottom',
									),
									'sidebar_section_body_padding_left' => array(
										'side' => 'left',
									),
									'sidebar_section_body_padding_right' => array(
										'side' => 'right',
									),
								),
							),
							'sidebar_section_box_height_mode' => array(
								'setting_type' => 'select_buttons_string',
								'options'     => array(
									'section_no_height' => esc_html__( 'Variable', 'echo-knowledge-base' ),
									'section_min_height' => esc_html__( 'Minimum', 'echo-knowledge-base' ),
									'section_fixed_height' => esc_html__( 'Maximum', 'echo-knowledge-base' ) ),
							),
							'sidebar_section_border_radius' => array(
								'setting_type' => 'range',
							),
							'sidebar_section_border_width' => array(
								'setting_type' => 'range',
							),
							'sidebar_section_border_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_box_shadow' => array(
								'setting_type' => 'select_buttons_string',
								'options'     => array(
									'no_shadow' => esc_html__( 'No Shadow', 'echo-knowledge-base' ),
									'section_light_shadow' => esc_html__( 'Light Shadow', 'echo-knowledge-base' ),
									'section_medium_shadow' => esc_html__( 'Medium Shadow', 'echo-knowledge-base' ),
									'section_bottom_shadow' => esc_html__( 'Bottom Shadow', 'echo-knowledge-base' )
								),
							),
							'sidebar_section_divider' => array(
								'setting_type' => 'toggle',
								'label' => esc_html__( 'Show Section Divider', 'echo-knowledge-base' ),
							),
							'sidebar_section_divider_thickness' => array(
								'setting_type' => 'range',
								'hide_on_dependencies' => array(
									'sidebar_section_divider' => 'off',
								),
							),
							'sidebar_section_divider_color' => array(
								'setting_type' => 'color',
							),
						),
					),

					// GROUP: Category Box Header
					'category-box-header' => array(
						'title' => esc_html__( 'Category Box Header', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_section_head_font_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_head_background_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_head_description_font_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_head_alignment' => array(
								'setting_type' => 'select_buttons_string',
								'options'     => array(
									'left' => esc_html__( 'Left', 'echo-knowledge-base' ),
									'center' => esc_html__( 'Centered', 'echo-knowledge-base' ),
									'right' => esc_html__( 'Right', 'echo-knowledge-base' )
								),
							),
							'sidebar_section_head_padding' => array(
								'setting_type' => 'box_control_combined',
								'label' => esc_html__( 'Category Name Padding', 'echo-knowledge-base' ),
								'min' => 0,
								'max' => 20,
								'combined_settings' => array(
									'sidebar_section_head_padding_top' => array(
										'side' => 'top',
									),
									'sidebar_section_head_padding_bottom' => array(
										'side' => 'bottom',
									),
									'sidebar_section_head_padding_left' => array(
										'side' => 'left',
									),
									'sidebar_section_head_padding_right' => array(
										'side' => 'right',
									),
								),
							),
						),
					),

					// GROUP: Categories
					'category-box-body' => array(
						'title' => esc_html__( 'Categories', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_section_category_icon_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_category_font_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_category_typography_controls' => array(
								'setting_type' => 'typography_controls',
								'label' => esc_html__( 'Category Typography', 'echo-knowledge-base' ),
								'controls' => array(
									'font_family' => EPKB_Blocks_Settings::get_typography_control_font_family(),
									'font_appearance' => EPKB_Blocks_Settings::get_typography_control_font_appearance(),
									'font_size' => EPKB_Blocks_Settings::get_typography_control_font_size( array(
										'small' => 12,
										'normal' => 14,
										'big' => 16,
									), 14 ),
								),
							),
							'sidebar_section_category_typography_desc_controls' => array(
								'setting_type' => 'typography_controls',
								'label' => esc_html__( 'Category Description Typography', 'echo-knowledge-base' ),
								'controls' => array(
									'font_family' => EPKB_Blocks_Settings::get_typography_control_font_family(),
									'font_appearance' => EPKB_Blocks_Settings::get_typography_control_font_appearance(),
									'font_size' => EPKB_Blocks_Settings::get_typography_control_font_size( array(
										'small' => 12,
										'normal' => 14,
										'big' => 16,
									), 14 ),
								),
							),
							'sidebar_section_subcategory_typography_controls' => array(
								'setting_type' => 'typography_controls',
								'label' => esc_html__( 'Subcategory Typography', 'echo-knowledge-base' ),
								'controls' => array(
									'font_family' => EPKB_Blocks_Settings::get_typography_control_font_family(),
									'font_appearance' => EPKB_Blocks_Settings::get_typography_control_font_appearance(),
									'font_size' => EPKB_Blocks_Settings::get_typography_control_font_size( array(
										'small' => 12,
										'normal' => 14,
										'big' => 16,
									), 14 ),
								),
							),
						),
					),

					// GROUP: Articles
					'articles-list' => array(
						'title' => esc_html__( 'Articles', 'echo-knowledge-base' ),
						'fields' => array(
							'sidebar_expand_articles_icon' => array(
								'setting_type' => 'dropdown',
								'options'     => array( 'ep_font_icon_plus_box' => _x( 'Plus Box', 'icon type', 'echo-knowledge-base' ),
									'ep_font_icon_plus' => _x( 'Plus Sign', 'icon type', 'echo-knowledge-base' ),
									'ep_font_icon_right_arrow' => _x( 'Arrow Triangle', 'icon type', 'echo-knowledge-base' ),
									'ep_font_icon_arrow_carrot_right' => _x( 'Arrow Caret', 'icon type', 'echo-knowledge-base' ),
									'ep_font_icon_arrow_carrot_right_circle' => _x( 'Arrow Caret 2', 'icon type', 'echo-knowledge-base' ),
									'ep_font_icon_folder_add' => _x( 'Folder', 'icon type', 'echo-knowledge-base' )
								),
							),
							'sidebar_article_active_bold' => array(
								'setting_type' => 'toggle'
							),
							'sidebar_article_list_margin' => array(
								'setting_type' => 'box_control',
								'side' => 'left',
							),
							'sidebar_article_active_font_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_article_active_background_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_article_font_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_article_icon_color' => array(
								'setting_type' => 'color',
							),
							'sidebar_section_body_typography_controls' => array(
								'setting_type' => 'typography_controls',
								'label' => esc_html__( 'Articles Typography', 'echo-knowledge-base' ),
								'controls' => array(
									'font_family' => EPKB_Blocks_Settings::get_typography_control_font_family(),
									'font_appearance' => EPKB_Blocks_Settings::get_typography_control_font_appearance(),
									'font_size' => EPKB_Blocks_Settings::get_typography_control_font_size( array(
										'small' => 12,
										'normal' => 14,
										'big' => 16,
									), 14 ),
								),
							),
							'article-content-background-color-v2' => array(
								'setting_type' => 'color',
							),
						),
					),

					// GROUP: Modular Sidebar
					'modular-sidebar' => array(
						'title' => esc_html__( 'Modular Sidebar', 'echo-knowledge-base' ),
						'fields' => array(
							'article_icon_toggle' => array(
								'setting_type' => 'toggle',
								'label' => esc_html__( 'Show Article Icon', 'echo-knowledge-base' ),
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'article_icon_color' => array(
								'setting_type' => 'color',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
									'article_icon_toggle' => 'off',
								),
							),
							'article_font_color' => array(
								'setting_type' => 'color',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'article_list_margin' => array(
								'setting_type' => 'box_control',
								'side' => 'left',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'article_list_spacing' => array(
								'setting_type' => 'range',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
							'article_typography_controls' => array(
								'setting_type' => 'typography_controls',
								'label' => esc_html__( 'Article Typography', 'echo-knowledge-base' ),
								'controls' => array(
									'font_family' => EPKB_Blocks_Settings::get_typography_control_font_family(),
									'font_appearance' => EPKB_Blocks_Settings::get_typography_control_font_appearance(),
									'font_size' => EPKB_Blocks_Settings::get_typography_control_font_size( array(
										'small' => 12,
										'normal' => 14,
										'big' => 16,
									), 14 ),
								),
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
//							'section_category_font_color' => array(		// TODO: is it used for modular sidebar for this layout?
//								'setting_type' => 'color',
//							),
//							'section_head_font_color' => array(		// TODO: is it used for modular sidebar for this layout?
//								'setting_type' => 'color',
//							),
							'section_body_background_color' => array(
								'setting_type' => 'color',
								'hide_on_dependencies' => array(
									'ml_categories_articles_sidebar_toggle' => 'off',
								),
							),
						),
					),
				),
			)
		);
	}
}