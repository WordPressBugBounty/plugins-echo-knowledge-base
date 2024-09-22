<?php

/**
 * Main Page Visual Helper - add a Visual Helper to the KB Main Page
 *
 */
class EPKB_Main_Page_Visual_Helper extends EPKB_Visual_Helper {

	/**
	 * Constructor - add actions for Visual Helper functionality
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'epkb_main_page_generate_page_content' ) );
	}

	/**
	 * Display Visual Helper on KB Main Page
	 */
	public function epkb_main_page_generate_page_content() {

		if ( ! EPKB_Utilities::is_kb_main_page() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! EPKB_Admin_UI_Access::is_user_admin_editor() ) {
			return;
		}
		$kb_id = EPKB_Utilities::get_eckb_kb_id();
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( $kb_id );


		$visual_helper_state = epkb_get_instance()->kb_config_obj->get_value( $kb_id, 'visual_helper_switch_visibility_toggle' );
		if ( $visual_helper_state === 'off' ) {
			return;
		}

		$settings_info_icons = array(
			// search
			'kb-main-page-search' => array(
				'connected_selectors' => '#epkb-ml-search-form',
				'modalTitle' => esc_html__( 'Search Settings', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => 'Search Box Width',
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Search Box', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Adjust the whole search box width or width of the input box.', 'echo-knowledge-base' ) .
							' <a href="https://www.echoknowledgebase.com/documentation/main-page-width/" target="_blank">' . esc_html__( 'Learn More', 'echo-knowledge-base' ) . '</a>',
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--search__module-settings' ) )
					),
					array(
						'title' => esc_html__( 'Labels for Search Title, Search Button Text, and Other', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Labels', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize text for the search box title, search button, and other elements.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__labels____search-labels-mp' ) )
					),
					array(
						'title' => esc_html__( 'Colors, Padding, Title, and More', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Search Box', 'echo-knowledge-base' ),
						'content' => esc_html__( 'The search box also has settings for colors, padding, title, and more.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--search__search-options-mp' ) )
					),
				)
			),
			// advanced search
			'kb-main-page-advanced-search' => array(
				'connected_selectors' => '#asea-doc-search-box-container',
				'modalTitle' => esc_html__( 'Search Settings', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => 'Search Box Width',
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Search Box', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Adjust the whole search box width or width of the input box.', 'echo-knowledge-base' ) .
							' <a href="https://www.echoknowledgebase.com/documentation/main-page-width/" target="_blank">' . esc_html__( 'Learn More', 'echo-knowledge-base' ) . '</a>',
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--search__module-settings--search-options-mp' ) )
					),
					array(
						'title' => esc_html__( 'Labels for Search Title, Search Button Text, and Other', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Labels', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize text for the search box title, search button, and other elements.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__labels____search-labels-mp' ) )
					),
					array(
						'title' => esc_html__( 'Colors, Padding, Title, and More', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Search Box', 'echo-knowledge-base' ),
						'content' => esc_html__( 'The search box also has settings for colors, padding, title, and more.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--search__search-options-mp--search-style-mp' ) )
					),
				)
			),
			'kb-main-page-category-box' => array(
				'connected_selectors' => '
				.epkb-top-category-box, 
				.epkb-category-section, 
				.epkb-ml-top-categories-button-container,
				#epkb-ml-grid-layout .eckb-categories-list a
				',
				'modalTitle' => esc_html__( 'Category Box Settings', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => 'Category and Articles Width',
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Search Box', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Adjust the width of the categories and articles section on the webpage.', 'echo-knowledge-base' ) .
							' <a href="https://www.echoknowledgebase.com/documentation/main-page-width/" target="_blank">' . esc_html__( 'Learn More', 'echo-knowledge-base' ) . '</a>',
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--categories_articles__module-settings' ) )
					),
					array(
						'title' => esc_html__( 'Category Icons', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'KB Main Page', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Categories & Articles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Choose category icons from our font icon library or upload your own.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit-tags.php?taxonomy=epkb_post_type_' . $kb_id  . '_category&post_type=epkb_post_type_' . $kb_id ) )
					),
					array(
						'title' => esc_html__( 'Colors, Box Height, Alignment, and More', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'KB Main Page', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Categories & Articles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize the appearance and behavior of category boxes with additional settings for colors, box height, alignment, and more.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--categories_articles' ) )
					),
					array(
						'title' => esc_html__( 'Labels for Category', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize text for the Empty Category Notice.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__labels____labels-category-body' ) )
					),
				)
			),
			'kb-main-page-featured-articles' => array(
				'connected_selectors' => '#epkb-ml-popular-articles',
				'modalTitle' => esc_html__( 'Featured Articles', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => esc_html__( 'Configuration', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'KB Main Page', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Categories & Articles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize the number of articles listed and which articles to list.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--articles_list__module-settings' ) )
					),
					array(
						'title' => esc_html__( 'Labels for Featured Articles Titles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize text for featured articles section and box titles.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__labels____labels_articles_list_feature' ) )
					),
				)
			),
			'kb-main-page-faq' => array(
				'connected_selectors' => '#epkb-ml__module-faqs',
				'modalTitle' => esc_html__( 'FAQs', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => esc_html__( 'FAQs Management', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Add and update questions and answers for your FAQs.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-faqs/' ) )
					),
					array(
						'title' => esc_html__( 'Choose style, colors, format, and More', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'KB Main Page', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Categories & Articles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Personalize the look and feel of your FAQs with additional settings for colors, predefined styles, animations, and more.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--faqs__module-settings' ) )
					),
					array(
						'title' => esc_html__( 'Labels for FAQs', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Customize text for the FAQs title, and Empty FAQs Notice.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__labels____labels_faqs_feature' ) )
					),
				)
			),
			'kb-main-page-resource-links' => array(
				'connected_selectors' => '.elay-ml__module-resource-links__title span',
				'modalTitle' => esc_html__( 'Resource Links', 'echo-knowledge-base' ),
				'modalSections' => array(
					array(
						'title' => esc_html__( 'Definition of Links and Buttons For Site Resources', 'echo-knowledge-base' ),
						//'location' => esc_html__( 'KB Config', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Settings', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'KB Main Page', 'echo-knowledge-base' ) . ' ⮞ ' . esc_html__( 'Categories & Articles', 'echo-knowledge-base' ),
						'content' => esc_html__( 'Define the destination and behavior of each link and button. Also, specify their colors and styles.', 'echo-knowledge-base' ),
						'link' => esc_url( admin_url( 'edit.php?post_type=epkb_post_type_' . $kb_id . '&page=epkb-kb-configuration#settings__main-page__module--resource_links__resource-link-individual-settings' ) )
					),
				)
			)
		);

		$this->epkb_generate_page_content( $settings_info_icons , $kb_config );
	}
}