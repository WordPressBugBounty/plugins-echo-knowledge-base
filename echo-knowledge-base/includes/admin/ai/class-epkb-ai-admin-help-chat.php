<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Help Chat - Simple AI chat for admin users to get help
 */
class EPKB_AI_Admin_Help_Chat {

	public function __construct() {
		add_action( 'admin_footer', array( $this, 'maybe_display_help_chat' ) );
	}

	/**
	 * Get customized welcome messages for each KB admin page
	 * 
	 * @return array
	 */
	private function get_page_welcome_messages() {
		return array(
			'epkb-dashboard' => array(
				'welcome' => __( 'Welcome to your Knowledge Base Dashboard!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you understand dashboard metrics, create your first article, or show you how to organize categories effectively.', 'echo-knowledge-base' )
			),
			'epkb-faqs' => array(
				'welcome' => __( 'Welcome to the FAQs Manager!', 'echo-knowledge-base' ),
				'helper' => __( 'I can guide you through creating FAQ groups, adding question-answer pairs, or setting up FAQ display on your pages.', 'echo-knowledge-base' )
			),
			'epkb-kb-configuration' => array(
				'welcome' => __( 'Welcome to KB Configuration!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you with Settings (layouts, colors, search), Order Articles, manage KB URLs, set up Blocks/Shortcodes, or use Tools for import/export and access control.', 'echo-knowledge-base' )
			),
			'epkb-kb-ai-features' => array(
				'welcome' => __( 'Welcome to AI-powered features!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you set up AI chat, configure search parameters, manage training data, or explain how AI features work.', 'echo-knowledge-base' )
			),
			'epkb-plugin-analytics' => array(
				'welcome' => __( 'Welcome to KB Analytics!', 'echo-knowledge-base' ),
				'helper' => __( 'I can explain your visitor metrics, show you how to track popular articles, or help you understand search patterns.', 'echo-knowledge-base' )
			),
			'epkb-add-ons' => array(
				'welcome' => __( 'Welcome to Add-ons & Extensions!', 'echo-knowledge-base' ),
				'helper' => __( 'I can explain available add-ons, help you install extensions, or guide you through activating premium features.', 'echo-knowledge-base' )
			),
			// Setup wizards
			'epkb-setup-wizard' => array(
				'welcome' => __( 'Welcome to the KB Setup Wizard!', 'echo-knowledge-base' ),
				'helper' => __( 'I\'ll walk you through choosing a layout, creating your main page, and setting up your first categories and articles.', 'echo-knowledge-base' )
			),
			'epkb-theme-wizard' => array(
				'welcome' => __( 'Welcome to the Theme Customizer!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you preview themes, customize color schemes, or adjust typography to match your brand.', 'echo-knowledge-base' )
			),
			'epkb-ordering-wizard' => array(
				'welcome' => __( 'Welcome to Article & Category Ordering!', 'echo-knowledge-base' ),
				'helper' => __( 'I can show you how to reorder articles, set up alphabetical sorting, or create a custom category hierarchy.', 'echo-knowledge-base' )
			),
			'epkb-global-wizard' => array(
				'welcome' => __( 'Welcome to Global Settings!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you configure settings for multiple KBs, set up user permissions, or manage global display options.', 'echo-knowledge-base' )
			),
			// Article/Category management
			'edit-epkb_post_type' => array(
				'welcome' => __( 'Welcome to Articles Management!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you write effective KB articles, organize content with categories, or perform bulk editing operations.', 'echo-knowledge-base' )
			),
			'edit-epkb_post_type_category' => array(
				'welcome' => __( 'Welcome to Categories Management!', 'echo-knowledge-base' ),
				'helper' => __( 'I can guide you through creating category hierarchies, adding icons to categories, or setting up category descriptions.', 'echo-knowledge-base' )
			),
			'epkb-settings-tools' => array(
				'welcome' => __( 'Welcome to KB Tools & Settings!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you import/export KB data, troubleshoot issues, or configure advanced settings.', 'echo-knowledge-base' )
			),
			// AI specific tabs - these might be shown within the AI page
			'ai-dashboard-tab' => array(
				'welcome' => __( 'Welcome to the AI Dashboard!', 'echo-knowledge-base' ),
				'helper' => __( 'I can explain AI usage statistics, show you response quality metrics, or help you monitor system performance.', 'echo-knowledge-base' )
			),
			'ai-training-data-tab' => array(
				'welcome' => __( 'Welcome to Training Data Management!', 'echo-knowledge-base' ),
				'helper' => __( 'I can guide you through syncing content, selecting training articles, or optimizing your AI knowledge base.', 'echo-knowledge-base' )
			),
			'ai-tools-tab' => array(
				'welcome' => __( 'Welcome to AI Tools!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you run diagnostic tests, validate AI responses, or debug configuration issues.', 'echo-knowledge-base' )
			),
			'ai-tuning-tab' => array(
				'welcome' => __( 'Welcome to AI Fine-tuning!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you adjust response length, set creativity levels, or optimize accuracy settings for better results.', 'echo-knowledge-base' )
			),
			'ai-general-settings-tab' => array(
				'welcome' => __( 'Welcome to AI General Settings!', 'echo-knowledge-base' ),
				'helper' => __( 'I can guide you through API setup, help you choose the right AI model, or configure connection settings.', 'echo-knowledge-base' )
			),
			'ai-pro-features-tab' => array(
				'welcome' => __( 'Welcome to AI Pro Features!', 'echo-knowledge-base' ),
				'helper' => __( 'I can explain advanced AI capabilities, show you enterprise features, or help you upgrade your plan.', 'echo-knowledge-base' )
			),
			// Default fallback
			'default' => array(
				'welcome' => __( 'Welcome to Echo Knowledge Base!', 'echo-knowledge-base' ),
				'helper' => __( 'I can help you set up your KB, manage articles, configure settings, or explain any feature you need assistance with.', 'echo-knowledge-base' )
			)
		);
	}

	/**
	 * Get the current page identifier
	 * Always returns a valid page ID, defaulting to 'default' if no specific page is found
	 * 
	 * @return string
	 */
	private function get_current_page_id() {
		$screen = get_current_screen();
		
		if ( empty( $screen ) ) {
			return 'default';
		}
		
		// Check for specific page parameter first (for plugin pages)
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( ! empty( $page ) ) {
			// Check for AI page tabs
			if ( $page === 'epkb-kb-ai-features' ) {
				$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
				if ( $tab !== 'dashboard' ) {
					$tab_id = 'ai-' . $tab . '-tab';
					$messages = $this->get_page_welcome_messages();
					if ( isset( $messages[$tab_id] ) ) {
						return $tab_id;
					}
				}
			}
			// Check if this page exists in our messages array
			$messages = $this->get_page_welcome_messages();
			if ( isset( $messages[$page] ) ) {
				return $page;
			}
		}
		
		// Check for post type screens (articles, categories)
		if ( $screen->base === 'edit' && ! empty( $screen->post_type ) ) {
			if ( strpos( $screen->post_type, 'epkb_post_type' ) !== false ) {
				return 'edit-epkb_post_type';
			}
		}
		
		if ( $screen->base === 'edit-tags' && ! empty( $screen->taxonomy ) ) {
			if ( strpos( $screen->taxonomy, 'epkb_post_type' ) !== false ) {
				return 'edit-epkb_post_type_category';
			}
		}
		
		// Check for wizard screens
		if ( isset( $_GET['wizard'] ) ) {
			$wizard = sanitize_text_field( $_GET['wizard'] );
			$wizard_id = 'epkb-' . $wizard . '-wizard';
			$messages = $this->get_page_welcome_messages();
			if ( isset( $messages[$wizard_id] ) ) {
				return $wizard_id;
			}
		}
		
		// Always return default for any unrecognized pages
		return 'default';
	}

	/**
	 * Display help chat button in admin pages
	 */
	public function maybe_display_help_chat() {
		
		// Only show for users who can manage KB settings
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show on KB plugin pages
		$screen = get_current_screen();
		if ( empty( $screen ) || ( strpos( $screen->id, 'epkb' ) === false && strpos( $screen->id, 'epkb_post_type' ) === false ) ) {
			return;
		}
		
		// Get customized message for current page
		$page_id = $this->get_current_page_id();
		$messages = $this->get_page_welcome_messages();
		$page_messages = isset( $messages[$page_id] ) ? $messages[$page_id] : $messages['default'];		?>

		<div id="epkb-admin-help-chat-root" 
		     data-welcome-message="<?php echo esc_attr( $page_messages['welcome'] ); ?>"
		     data-helper-question="<?php echo esc_attr( $page_messages['helper'] ); ?>"
		     data-page-context="<?php echo esc_attr( $page_id ); ?>">
		</div>		<?php
	}
}