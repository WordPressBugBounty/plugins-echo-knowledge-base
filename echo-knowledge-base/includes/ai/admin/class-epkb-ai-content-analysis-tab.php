<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Content Analysis tab with sub-tabs implementation
 *
 * @copyright   Copyright (C) 2025, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_AI_Content_Analysis_Tab {


	/**
	 * Get the configuration for the Content Analysis tab
	 * @return array
	 */
	public static function get_tab_config() {

		if ( ! EPKB_AI_Utilities::is_ai_enabled() ) {
			return array(
				'error' => __( 'AI features are not enabled. Please enable any AI feature to access the Content Analysis tab.', 'echo-knowledge-base' )
			);
		}

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();

		// Build sub_tabs array
		$sub_tabs = array();

		// Add Analyze as the first sub-tab
		$sub_tabs['overview'] = array(
			'id' => 'overview',
			'title' => __( 'Analyze', 'echo-knowledge-base' ),
			'icon' => 'epkbfa epkbfa-bar-chart'
		);

		// Add Improve as the second sub-tab
		$sub_tabs['improve'] = array(
			'id' => 'improve',
			'title' => __( 'Improve', 'echo-knowledge-base' ),
			'icon' => 'epkbfa epkbfa-magic'
		);

		// Get preloaded content analysis data for initial display
		$preloaded_data = self::get_preloaded_content_analysis_data();

		// Default to KB #1 for now - later user will be able to choose
		$kb_id = 1;

		 /** @disregard P1011 */
		$config = array(
			'tab_id' => 'content-analysis',
			'title' => __( 'Content Analysis', 'echo-knowledge-base' ),
			'sub_tabs' => $sub_tabs,
			'ai_config' => $ai_config,
			'kb_id' => $kb_id,  // Pass KB ID to frontend
			'is_ai_features_pro_enabled' => EPKB_Utilities::is_ai_features_pro_enabled(),
			'is_access_manager_active' => EPKB_Utilities::is_amag_on(),
			'preloaded_data' => $preloaded_data
		);

		return $config;
	}

	/**
	 * Get pre-loaded content analysis data for initial page load
	 * @return array
	 */
	private static function get_preloaded_content_analysis_data() {

		$preloaded = array();

		// Default to KB #1 for now - later user will be able to choose
		$kb_id = 1;

		// Get KB configuration to check if article views counter is enabled
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( $kb_id );
		$views_counter_enabled = ! empty( $kb_config['article_views_counter_enable'] ) && $kb_config['article_views_counter_enable'] === 'on';

		// Status tabs to pre-load (all, to_analyse, to_improve, recent)
		$statuses = array( 'all', 'to_analyse', 'to_improve', 'recent' );

		// Get the post type for this KB
		$post_type = EPKB_KB_Handler::get_post_type( $kb_id );

		foreach ( $statuses as $status ) {
			// Query KB articles directly
			$args = array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => 20,
				'paged' => 1,
				'orderby' => 'modified',
				'order' => 'DESC'
			);

			// Execute the query
			$query = new WP_Query( $args );

			// Transform data for content analysis display
			$transformed_data = array();
			foreach ( $query->posts as $post ) {
				// Get analysis data if available
				$analysis_data = EPKB_AI_Content_Analysis_Utilities::get_article_analysis_data( $post->ID );
				$scores = $analysis_data['scores'];
				$dates = $analysis_data['dates'];

				// Get the post type object to get the label
				$post_type_obj = get_post_type_object( $post->post_type );
				$type_name = 'Article';
				if ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) ) {
					$type_name = $post_type_obj->labels->singular_name;
				}

				// Add content analysis specific fields
				$transformed_item = new stdClass();
				$transformed_item->id = $post->ID;
				$transformed_item->item_id = $post->ID;
				$transformed_item->title = $post->post_title;

				// Get score from analysis data or default
				$transformed_item->score = $scores && isset( $scores['overall'] ) ? $scores['overall'] : '-';

				// Get score components from analysis data
				if ( $scores && isset( $scores['components'] ) ) {
					$transformed_item->scoreComponents = EPKB_AI_Content_Analysis_Utilities::format_score_components( $scores['components'] );
				} else {
					$transformed_item->scoreComponents = array(
						array( 'name' => 'Tags Usage', 'value' => '-' ),
						array( 'name' => 'Score 2', 'value' => '-' ),
						array( 'name' => 'Score 3', 'value' => '-' )
					);
				}

				// Importance from analysis data
				$transformed_item->importance = $scores && isset( $scores['importance'] ) ? $scores['importance'] : 'N/A';

				$transformed_item->last_analyzed = $dates['analyzed'] ? $dates['analyzed'] : 'Not analyzed';
				$transformed_item->updated = $post->post_modified;
				$transformed_item->type = $post->post_type;
				$transformed_item->type_name = $type_name;
				$transformed_item->status = $analysis_data['status'];
				$transformed_item->display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $post->ID );

				$transformed_data[] = $transformed_item;
			}

			// Get total count for pagination
			$total = $query->found_posts;
			$total_pages = $query->max_num_pages;

			$preloaded[$status] = array(
				'data' => $transformed_data,
				'pagination' => array(
					'page' => 1,
					'per_page' => 20,
					'total' => $total,
					'total_pages' => $total_pages
				)
			);
		}

		// Calculate status statistics from actual KB articles
		// Get all articles to count properly
		$count_args = array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids'
		);
		$all_posts = get_posts( $count_args );

		$to_analyze_count = 0;
		$to_improve_count = 0;

		foreach ( $all_posts as $post_id ) {
			$display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $post_id );
			if ( $display_status === 'To Analyze' ) {
				$to_analyze_count++;
			} elseif ( $display_status === 'To Improve' ) {
				$to_improve_count++;
			}
		}

		$stats = array(
			'total' => count( $all_posts ),
			'to_analyse' => $to_analyze_count,
			'to_improve' => $to_improve_count,
			'recent' => count( $all_posts ),
		);

		return array(
			'data' => $preloaded,
			'stats' => $stats
		);
	}
}