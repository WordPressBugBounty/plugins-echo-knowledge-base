<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI PRO Features tab showcasing premium features
 */
class EPKB_AI_PRO_Features_Tab {

	/**
	 * Get the configuration for the PRO Features tab
	 *
	 * @return array
	 * @noinspection PhpUnused
	 */
	public static function get_tab_config() {
		return array(
			'tab_id' => 'pro-features',
			'title' => __( 'PRO Features', 'echo-knowledge-base' ),
			'type' => 'pro_showcase',
			'header' => array(
				'title' => __( 'Welcome to Echo Knowledge Base Pro', 'echo-knowledge-base' ),
				'subtitle' => array(
					__( 'We\'re grateful you\'re using Echo Knowledge Base and hope it\'s been valuable for your team.', 'echo-knowledge-base' ),
					__( 'Upgrading to Pro unlocks powerful features that enhance your knowledge base experience.', 'echo-knowledge-base' ),
					__( 'Your support also helps us continue developing and improving the plugin for our amazing community.', 'echo-knowledge-base' )
				),
				'status' => self::get_pro_status()
			),
			'features' => self::get_pro_features(),
			'cta' => array(
				//'title' => __( 'Ready to Unlock All PRO Features?', 'echo-knowledge-base' ),
				'subtitle' => __( 'Join thousands of businesses using AI to provide instant, accurate support', 'echo-knowledge-base' ),
				'button_text' => __( 'Get PRO Now', 'echo-knowledge-base' ),
				'button_url' => 'https://www.echoknowledgebase.com/wordpress-plugin/ai-features/',
				'button_secondary_text' => __( 'View Pricing', 'echo-knowledge-base' ),
				'button_secondary_url' => 'https://www.echoknowledgebase.com/ai-features-pricing/#pricing',
				'guarantee' => __( '30-day money-back guarantee', 'echo-knowledge-base' )
			)
		);
	}

	/**
	 * Get list of PRO features to showcase
	 *
	 * @return array
	 */
	private static function get_pro_features() {
		return array(
			array(
				'id'          => 'advanced-training',
			'title'       => __( 'Advanced Training Data Sources', 'echo-knowledge-base' ),
			'description' => __( 'Train your AI using posts, pages, custom post types, and private notes. Build a comprehensive knowledge base that understands all your content.', 'echo-knowledge-base' ),
			'icon'        => 'epkbfa epkbfa-database',
			'icon_color'  => '#4A90E2',
			'benefits'    => array(
				__( 'WordPress posts and pages (Home page, product pages etc.).', 'echo-knowledge-base' ),
				__( 'Custom post types (e.g. Products, Events, Courses).', 'echo-knowledge-base' ),
				__( 'Internal notes created for reference, available to AI without being published publicly.', 'echo-knowledge-base' ),
               ),

				'image' => esc_url( Echo_Knowledge_Base::$plugin_url . 'img/ai-pro-features-training-data.png' )
			),
			array(
				'id' => 'email-notifications',
				'title' => __( 'Smart Email Notifications', 'echo-knowledge-base' ),
				'description' => __( 'Stay informed with automated daily summaries of AI Chat and Search activity, delivered straight to your inbox.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-envelope',
				'icon_color' => '#E74C3C',
				'benefits' => array(
					__( 'Daily email reports at your chosen time', 'echo-knowledge-base' ),
					__( 'Customizable recipient and subject line', 'echo-knowledge-base' ),
					__( 'Includes AI Chat and Search query titles', 'echo-knowledge-base' )
				),
				'image' => esc_url( Echo_Knowledge_Base::$plugin_url . 'img/ai-pro-features-email-notifications.png' )
				//'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				//'badge_type' => 'coming-soon'
			),
			// array(
			// 	'id' => 'articles-analysis',
			// 	'title' => __( 'AI-Powered Article Analysis', 'echo-knowledge-base' ),
			// 	'description' => __( 'Get intelligent insights about your content. AI analyzes article quality, readability, and suggests improvements to maximize user engagement.', 'echo-knowledge-base' ),
			// 	'icon' => 'epkbfa epkbfa-chart-line',
			// 	'icon_color' => '#27AE60',
			// 	'benefits' => array(
			// 		__( 'Content quality scoring', 'echo-knowledge-base' ),
			// 		__( 'Readability analysis', 'echo-knowledge-base' ),
			// 		__( 'SEO optimization tips', 'echo-knowledge-base' ),
			// 	),
			// 	'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
			// 	'badge_type' => 'coming-soon'
			// ),
			// array(
			// 	'id' => 'glossary-terms',
			// 	'title' => __( 'AI-Generated Glossary Terms', 'echo-knowledge-base' ),
			// 	'description' => __( 'Automatically generate and manage glossary terms with AI. Intelligently prioritize technical terms, acronyms, and industry-specific language in search results and content ordering.', 'echo-knowledge-base' ),
			// 	'icon' => 'epkbfa epkbfa-book',
			// 	'icon_color' => '#9B59B6',
			// 	'benefits' => array(
			// 		__( 'Auto-generate glossary definitions', 'echo-knowledge-base' ),
			// 		__( 'Smart term prioritization', 'echo-knowledge-base' ),
			// 		__( 'Context-aware term ordering', 'echo-knowledge-base' )
			// 	),
			// 	'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
			// 	'badge_type' => 'coming-soon'
			// ),
			// array(
			// 	'id' => 'enhanced-search',
			// 	'title' => __( 'Enhanced AI Search Results', 'echo-knowledge-base' ),
			// 	'description' => __( 'Deliver rich, comprehensive search results with visual aids. Include diagrams, related articles, glossary terms, and more to provide complete answers.', 'echo-knowledge-base' ),
			// 	'icon' => 'epkbfa epkbfa-search-plus',
			// 	'icon_color' => '#F39C12',
			// 	'benefits' => array(
			// 		__( 'Visual diagrams and charts', 'echo-knowledge-base' ),
			// 		__( 'Related articles suggestions', 'echo-knowledge-base' ),
			// 		__( 'Integrated glossary terms', 'echo-knowledge-base' )
			// 	),
			// 	'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
			// 	'badge_type' => 'coming-soon'
			// ),
			// array(
			// 	'id' => 'advanced-features',
			// 	'title' => __( 'Advanced AI Capabilities', 'echo-knowledge-base' ),
			// 	'description' => __( 'Unlock powerful features including PDF search, human agent handoff, and intelligent auto-suggestions for a complete support experience.', 'echo-knowledge-base' ),
			// 	'icon' => 'epkbfa epkbfa-rocket',
			// 	'icon_color' => '#E67E22',
			// 	'benefits' => array(
			// 		__( 'PDF document search', 'echo-knowledge-base' ),
			// 		__( 'Human agent handoff', 'echo-knowledge-base' ),
			// 		__( 'Smart auto-suggestions', 'echo-knowledge-base' ),
			// 		__( 'Multi-language support', 'echo-knowledge-base' )
			// 	),
			// 	'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
			// 	'badge_type' => 'coming-soon'
			// )
		);
	}

	/**
	 * Get PRO status information
	 *
	 * @return array
	 */
	private static function get_pro_status() {
		$has_pro = defined( 'EPKB_AI_FEATURES_VERSION' );
		
		if ( ! $has_pro ) {
			return array(
				'has_pro' => false,
				'status_text' => '',
				'status_class' => '',
				'features_available' => 0,
				'features_total' => 0
			);
		}
		
		return array(
			'has_pro' => true,
			'status_text' => __( 'PRO Active', 'echo-knowledge-base' ),
			'status_class' => 'status-active',
			'features_available' => 6,
			'features_total' => 6
		);
	}

}