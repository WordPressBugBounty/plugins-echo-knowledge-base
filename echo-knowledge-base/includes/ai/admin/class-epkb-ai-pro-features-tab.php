<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI PRO Features tab showcasing premium features
 */
class EPKB_AI_PRO_Features_Tab {

	/**
	 * Get the configuration for the PRO Features tab
	 *
	 * @return array
	 */
	public static function get_tab_config() {
		return array(
			'tab_id' => 'pro-features',
			'title' => __( 'PRO Features', 'echo-knowledge-base' ),
			'type' => 'pro_showcase',
			'header' => array(
				'title' => __( 'Unlock Premium AI Features', 'echo-knowledge-base' ),
				'subtitle' => __( 'Take your knowledge base to the next level with advanced AI capabilities', 'echo-knowledge-base' ),
				'status' => self::get_pro_status()
			),
			'discount_coupon' => self::get_discount_coupon(),
			'features' => self::get_pro_features(),
			'cta' => array(
				'title' => __( 'Ready to Unlock All PRO Features?', 'echo-knowledge-base' ),
				'subtitle' => __( 'Join thousands of businesses using AI to provide instant, accurate support', 'echo-knowledge-base' ),
				'button_text' => __( 'Get PRO Now', 'echo-knowledge-base' ),
				'button_url' => 'https://www.echoknowledgebase.com/wordpress-plugin/ai-features/',
				'button_secondary_text' => __( 'View Pricing', 'echo-knowledge-base' ),
				'button_secondary_url' => 'https://www.echoknowledgebase.com/ai-features-pricing/#pricing',
				'discount_text' => __( '🎉 Save 20% with annual billing', 'echo-knowledge-base' ),
				'guarantee' => __( '30-day money-back guarantee', 'echo-knowledge-base' )
			)
		);
	}

	/**
	 * Get the number of installed Echo KB add-ons
	 *
	 * @return int
	 */
	private static function get_addon_count() {
		$addon_count = 0;
		
		// Check for each known Echo KB add-on
		$addons = array(
			'Echo_Advanced_Search',
			'Echo_Elegant_Layouts',
			'Echo_Article_Rating_And_Feedback',
			'Echo_KB_Articles_Setup',
			'Echo_Knowledge_Base_CPT',
			'Echo_Widgets_KB',
			'Echo_Access_Manager',
			'Echo_Links_Editor',
			'Echo_KB_Export_Import',
			'Echo_Advanced_Config',
			'EPKB_AI_FEATURES_VERSION'
		);
		
		foreach ( $addons as $addon_class ) {
			if ( class_exists( $addon_class ) || defined( $addon_class ) ) {
				$addon_count++;
			}
		}
		
		return $addon_count;
	}

	/**
	 * Get discount coupon based on number of add-ons
	 *
	 * @return array
	 */
	private static function get_discount_coupon() {
		$addon_count = self::get_addon_count();
		$current_date = date('Y-m-d');
		$expiry_date = date('Y-m-d', strtotime('September 15'));
		
		if ( $addon_count == 0 ) {
			return array(
				'discount_percentage' => 30,
				'coupon_code' => 'AIPRO30',
				'title' => __( '🎉 Limited Time: 30% OFF for New Users!', 'echo-knowledge-base' ),
				'subtitle' => __( 'Start your AI journey with our special promotional discount', 'echo-knowledge-base' ),
				'expiry_text' => sprintf( __( 'Offer expires on %s', 'echo-knowledge-base' ), date('F j, Y', strtotime($expiry_date)) ),
				'badge_text' => __( 'NEW USER DISCOUNT', 'echo-knowledge-base' ),
				'addon_count' => $addon_count
			);
		} elseif ( $addon_count == 1 ) {
			return array(
				'discount_percentage' => 50,
				'coupon_code' => 'LOYAL50',
				'title' => __( '🎉 Exclusive: 50% OFF for Valued Customers!', 'echo-knowledge-base' ),
				'subtitle' => __( 'Thank you for being our customer! Enjoy this special discount', 'echo-knowledge-base' ),
				'expiry_text' => sprintf( __( 'Offer expires on %s', 'echo-knowledge-base' ), date('F j, Y', strtotime($expiry_date)) ),
				'badge_text' => __( 'CUSTOMER APPRECIATION', 'echo-knowledge-base' ),
				'addon_count' => $addon_count
			);
		} elseif ( $addon_count >= 2 && $addon_count <= 3 ) {
			return array(
				'discount_percentage' => 60,
				'coupon_code' => 'VIP60',
				'title' => __( '🎉 VIP Offer: 60% OFF for Premium Members!', 'echo-knowledge-base' ),
				'subtitle' => __( 'As a premium member, you deserve our best discount', 'echo-knowledge-base' ),
				'expiry_text' => sprintf( __( 'Offer expires on %s', 'echo-knowledge-base' ), date('F j, Y', strtotime($expiry_date)) ),
				'badge_text' => __( 'VIP MEMBER DISCOUNT', 'echo-knowledge-base' ),
				'addon_count' => $addon_count
			);
		} else {
			return array(
				'discount_percentage' => 80,
				'coupon_code' => 'ELITE80',
				'title' => __( '🎉 Elite Special: 80% OFF for Power Users!', 'echo-knowledge-base' ),
				'subtitle' => __( 'Our most exclusive discount for our most valued partners', 'echo-knowledge-base' ),
				'expiry_text' => sprintf( __( 'Offer expires on %s', 'echo-knowledge-base' ), date('F j, Y', strtotime($expiry_date)) ),
				'badge_text' => __( 'ELITE PARTNER DISCOUNT', 'echo-knowledge-base' ),
				'addon_count' => $addon_count
			);
		}
	}

	/**
	 * Get list of PRO features to showcase
	 *
	 * @return array
	 */
	private static function get_pro_features() {
		return array(
			array(
				'id' => 'advanced-training',
				'title' => __( 'Advanced Training Data Sources', 'echo-knowledge-base' ),
				'description' => __( 'Train your AI on posts, pages, custom post types, and private notes. Create a comprehensive knowledge base that understands all your content.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-database',
				'icon_color' => '#4A90E2',
				'benefits' => array(
					__( 'WordPress Posts & Pages', 'echo-knowledge-base' ),
					__( 'Custom Post Types', 'echo-knowledge-base' ),
					__( 'Notes and PDF Documents', 'echo-knowledge-base' ),
				)
			),
			array(
				'id' => 'email-notifications',
				'title' => __( 'Smart Email Notifications', 'echo-knowledge-base' ),
				'description' => __( 'Never miss a customer conversation. Get instant email alerts when users complete chats, with full conversation transcripts and insights.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-envelope',
				'icon_color' => '#E74C3C',
				'benefits' => array(
					__( 'Real-time chat completion alerts', 'echo-knowledge-base' ),
					__( 'Full conversation transcripts', 'echo-knowledge-base' ),
					__( 'Custom notification rules', 'echo-knowledge-base' )
				),
				'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				'badge_type' => 'coming-soon'
			),
			array(
				'id' => 'articles-analysis',
				'title' => __( 'AI-Powered Article Analysis', 'echo-knowledge-base' ),
				'description' => __( 'Get intelligent insights about your content. AI analyzes article quality, readability, and suggests improvements to maximize user engagement.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-chart-line',
				'icon_color' => '#27AE60',
				'benefits' => array(
					__( 'Content quality scoring', 'echo-knowledge-base' ),
					__( 'Readability analysis', 'echo-knowledge-base' ),
					__( 'SEO optimization tips', 'echo-knowledge-base' ),
				),
				'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				'badge_type' => 'coming-soon'
			),
			array(
				'id' => 'glossary-terms',
				'title' => __( 'AI-Generated Glossary Terms', 'echo-knowledge-base' ),
				'description' => __( 'Automatically generate and manage glossary terms with AI. Intelligently prioritize technical terms, acronyms, and industry-specific language in search results and content ordering.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-book',
				'icon_color' => '#9B59B6',
				'benefits' => array(
					__( 'Auto-generate glossary definitions', 'echo-knowledge-base' ),
					__( 'Smart term prioritization', 'echo-knowledge-base' ),
					__( 'Context-aware term ordering', 'echo-knowledge-base' )
				),
				'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				'badge_type' => 'coming-soon'
			),
			array(
				'id' => 'enhanced-search',
				'title' => __( 'Enhanced AI Search Results', 'echo-knowledge-base' ),
				'description' => __( 'Deliver rich, comprehensive search results with visual aids. Include diagrams, related articles, glossary terms, and more to provide complete answers.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-search-plus',
				'icon_color' => '#F39C12',
				'benefits' => array(
					__( 'Visual diagrams and charts', 'echo-knowledge-base' ),
					__( 'Related articles suggestions', 'echo-knowledge-base' ),
					__( 'Integrated glossary terms', 'echo-knowledge-base' )
				),
				'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				'badge_type' => 'coming-soon'
			),
			array(
				'id' => 'advanced-features',
				'title' => __( 'Advanced AI Capabilities', 'echo-knowledge-base' ),
				'description' => __( 'Unlock powerful features including PDF search, human agent handoff, and intelligent auto-suggestions for a complete support experience.', 'echo-knowledge-base' ),
				'icon' => 'epkbfa epkbfa-rocket',
				'icon_color' => '#E67E22',
				'benefits' => array(
					__( 'PDF document search', 'echo-knowledge-base' ),
					__( 'Human agent handoff', 'echo-knowledge-base' ),
					__( 'Smart auto-suggestions', 'echo-knowledge-base' ),
					__( 'Multi-language support', 'echo-knowledge-base' )
				),
				'badge' => __( 'upcoming feature', 'echo-knowledge-base' ),
				'badge_type' => 'coming-soon'
			)
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