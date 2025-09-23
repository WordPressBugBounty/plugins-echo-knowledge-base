<?php defined( 'ABSPATH' ) || exit();

/**
 * REST API Controller for Content Analysis operations
 *
 * Uses the Content Analysis Job Manager for consistent processing pattern with Training Data sync.
 */
class EPKB_AI_REST_Content_Analysis_Controller extends EPKB_AI_REST_Base_Controller {

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {

		// Start content analysis (direct mode)
		register_rest_route( $this->admin_namespace, '/start-direct-analysis', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_direct_analysis' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_ids' => array(
						'required' => true,
						'description' => 'Article IDs to analyze or "ALL" or "ALL_STATUS"',
					),
				),
			)
		) );

		// Start cron-based analysis
		register_rest_route( $this->admin_namespace, '/start-cron-analysis', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_cron_analysis' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_ids' => array(
						'required' => true,
						'description' => 'Article IDs to analyze or "ALL"',
					),
				),
			)
		) );

		// Get analysis progress
		register_rest_route( $this->admin_namespace, '/content-analysis-progress', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analysis_progress' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );

		// Process next batch (for direct analysis)
		register_rest_route( $this->admin_namespace, '/content-analysis-process-next', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_next_article' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );

		// Cancel analysis
		register_rest_route( $this->admin_namespace, '/content-analysis-cancel', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_analysis' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );

        // Get articles with analysis data
        register_rest_route( $this->admin_namespace, '/content-analysis-articles', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_analysis_articles' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'page' => array(
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ),
                    'status' => array(
                        'default' => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'search' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'kb_id' => array(
                        'default' => 1,  // Default to KB #1 for now
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        ) );

		// Toggle article ignored status
		register_rest_route( $this->admin_namespace, '/content-analysis-toggle-ignored', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_article_ignored' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'ignored' => array(
						'required' => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		) );

		// Toggle article done status
		register_rest_route( $this->admin_namespace, '/content-analysis-toggle-done', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_article_done' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'done' => array(
						'required' => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		) );

		// Get detailed analysis for a specific article
		register_rest_route( $this->admin_namespace, '/content-analysis-details', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_article_analysis_details' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'kb_id' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		) );

		// Add tag to article
		register_rest_route( $this->admin_namespace, '/content-analysis-tag-add', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_article_tag' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'tag_name' => array(
						'required' => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'kb_id' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		) );

		// Remove tag from article
		register_rest_route( $this->admin_namespace, '/content-analysis-tag-remove', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'remove_article_tag' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'tag_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'kb_id' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		) );

		// Edit article tag
		register_rest_route( $this->admin_namespace, '/content-analysis-tag-edit', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'edit_article_tag' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'article_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'tag_id' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
					),
					'new_name' => array(
						'required' => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'kb_id' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		) );
	}
	
	/**
	 * Check if user has permission to access AI admin endpoints
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'echo-knowledge-base' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Start direct content analysis
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function start_direct_analysis( $request ) {

		$article_ids = $request->get_param( 'article_ids' );

		// Initialize analysis job using Job Manager
		$result = EPKB_AI_Content_Analysis_Job_Manager::initialize_analysis_job( $article_ids, 'direct' );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array( 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}

		return $this->create_rest_response( array( 'success' => true, 'job' => $result, 'total' => $result['total'] ) );
	}

	/**
	 * Start cron-based content analysis
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function start_cron_analysis( $request ) {

		$article_ids = $request->get_param( 'article_ids' );

		// Initialize analysis job using Job Manager
		$result = EPKB_AI_Content_Analysis_Job_Manager::initialize_analysis_job( $article_ids, 'cron' );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array( 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}

		// Schedule the first cron event to start the chain
// TODO		wp_schedule_single_event( time() + 1, EPKB_AI_Content_Analysis_Job_Manager::CRON_HOOK );

		return $this->create_rest_response( array( 'success' => true, 'job' => $result, 'total' => $result['total'] ) );
	}

	/**
	 * Get current analysis progress
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_analysis_progress( $request ) {

		$job = EPKB_AI_Content_Analysis_Job_Manager::get_analysis_job();

		return $this->create_rest_response( array(
			'success' => true,
			'progress' => array(
				'status' => $job['status'],
				'total' => $job['total'],
				'processed' => $job['processed'],
				'percent' => $job['percent'],
				'errors' => isset( $job['errors'] ) ? $job['errors'] : 0,
									'type' => $job['type'],
				'retrying' => isset( $job['retrying'] ) ? $job['retrying'] : false,
				'cancel_requested' => isset( $job['cancel_requested'] ) ? $job['cancel_requested'] : false
			)
		) );
	}

	/**
	 * Process next article for direct analysis
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function process_next_article( $request ) {

		$job = EPKB_AI_Content_Analysis_Job_Manager::get_analysis_job();

		// Only process if direct analysis is running
		if ( $job['type'] !== 'direct' || $job['status'] !== 'running' ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'No active direct analysis job', 'echo-knowledge-base' )
			) );
		}

		// Process one article using Job Manager
		$batch_result = EPKB_AI_Content_Analysis_Job_Manager::process_next_analysis_item();

		// Get updated job status
		$job = EPKB_AI_Content_Analysis_Job_Manager::get_analysis_job();

		// Process the analyzed articles to ensure proper format
		$analyzed_articles = array();
		if ( isset( $batch_result['updated_articles'] ) && is_array( $batch_result['updated_articles'] ) ) {
			$analyzed_articles = $batch_result['updated_articles'];
		}

		return $this->create_rest_response( array(
			'success' => true,
			'status' => $batch_result['status'],
			'analyzed_articles' => $analyzed_articles,
			'progress' => array(
				'status' => $job['status'],
				'total' => $job['total'],
				'processed' => $job['processed'],
				'percent' => $job['percent'],
				'errors' => $job['errors'],
				'retrying' => ! empty( $job['retrying'] ),
				'cancel_requested' => ! empty( $job['cancel_requested'] )
			)
		) );
	}

	/**
	 * Cancel the current analysis job
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function cancel_analysis( $request ) {

		$result = EPKB_AI_Content_Analysis_Job_Manager::cancel_all_analysis();

		return $this->create_rest_response( array( 'success' => $result, 'message' => __( 'Content analysis canceled successfully', 'echo-knowledge-base' ) ) );
	}


	/**
	 * Get articles with their analysis data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
    public function get_analysis_articles( $request ) {

        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $status = $request->get_param( 'status' );
        $search = $request->get_param( 'search' );
        // Default to KB #1 for now - later user will be able to choose
        $kb_id = (int) $request->get_param( 'kb_id' );
        if ( empty( $kb_id ) ) {
            $kb_id = 1;
        }

        // Get KB articles for the requested KB
        $post_type = EPKB_KB_Handler::get_post_type( $kb_id );

		// For 'to_analyse' and 'to_improve', get all articles and filter by display_status
		$get_all_for_filtering = ( $status === 'to_improve' || $status === 'to_analyse' );

		$args = array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => $get_all_for_filtering ? -1 : $per_page,
			'paged' => $get_all_for_filtering ? 1 : $page,
			'orderby' => 'modified',
			'order' => 'DESC'
		);

		// Add search if provided
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Don't use meta_query for to_analyse and to_improve - we'll filter by display_status instead
		// Keep meta_query only for other status types like 'analyzed', 'error', 'not_analyzed'
		if ( $status !== 'all' && $status !== 'recent' && $status !== 'to_analyse' && $status !== 'to_improve' ) {
			$meta_query = array();

			switch ( $status ) {
				case 'analyzed':
					$meta_query[] = array(
						'key' => EPKB_AI_Content_Analysis_Utilities::META_ANALYSIS_STATUS,
						'value' => 'analyzed',
						'compare' => '='
					);
					break;
				case 'error':
					$meta_query[] = array(
						'key' => EPKB_AI_Content_Analysis_Utilities::META_ANALYSIS_STATUS,
						'value' => 'error',
						'compare' => '='
					);
					break;
				case 'not_analyzed':
					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key' => EPKB_AI_Content_Analysis_Utilities::META_ANALYSIS_STATUS,
							'compare' => 'NOT EXISTS'
						),
						array(
							'key' => EPKB_AI_Content_Analysis_Utilities::META_ANALYSIS_STATUS,
							'value' => array( 'analyzed', 'error' ),
							'compare' => 'NOT IN'
						)
					);
					break;
			}

			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}
		}

		$query = new WP_Query( $args );
		$articles = array();
		foreach ( $query->posts as $post ) {
			// Get analysis data using utility class
			$analysis_data = EPKB_AI_Content_Analysis_Utilities::get_article_analysis_data( $post->ID );
			$scores = $analysis_data['scores'];
			$dates = $analysis_data['dates'];
			$error = $analysis_data['error'];

			// Get display status
			$display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $post->ID );

			$article_data = array(
				'id' => $post->ID,
				'item_id' => $post->ID,
				'title' => $post->post_title,
				'score' => $scores && isset( $scores['overall'] ) ? $scores['overall'] : '-',
				'importance' => $scores && isset( $scores['importance'] ) ? $scores['importance'] : 'N/A',
				'last_analyzed' => $dates['analyzed'] ? $dates['analyzed'] : 'Not analyzed',
				'updated' => $post->post_modified,
				'status' => $analysis_data['status'],
				'display_status' => $display_status,  // New status for display
				// Include all dates for future use
				'dates' => $dates,
				// Include ignored and done flags
				'is_ignored' => $analysis_data['is_ignored'],
				'is_done' => $analysis_data['is_done']
			);

			// Add score components
			if ( $analysis_data['is_analyzed'] && $scores && isset( $scores['components'] ) ) {
				$article_data['scoreComponents'] = EPKB_AI_Content_Analysis_Utilities::format_score_components( $scores['components'] );
			} else {
				$article_data['scoreComponents'] = array(
					array( 'name' => 'Tags Usage', 'value' => '-' ),
					array( 'name' => 'Score 2', 'value' => '-' ),
					array( 'name' => 'Score 3', 'value' => '-' )
				);
			}

			// Add error details if status is error
			if ( $analysis_data['status'] === 'error' && ! empty( $error['message'] ) ) {
				$article_data['error_message'] = $error['message'];
				$article_data['error_code'] = $error['code'] ?: 500;
			}

			$articles[] = $article_data;
		}

		// Filter for 'to_analyse' status - only show articles with display_status 'To Analyze'
		if ( $status === 'to_analyse' ) {
			$articles = array_filter( $articles, function( $article ) {
				// Only include articles with display_status of 'To Analyze'
				return isset( $article['display_status'] ) && $article['display_status'] === 'To Analyze';
			} );

			// Reindex array after filtering
			$articles = array_values( $articles );

			// Calculate proper pagination for filtered results
			$total_filtered = count( $articles );

			// Apply pagination to the filtered results
			$offset = ( $page - 1 ) * $per_page;
			$articles = array_slice( $articles, $offset, $per_page );
		}

		// Filter for 'to_improve' status - only show articles with display_status 'To Improve'
		if ( $status === 'to_improve' ) {
			$articles = array_filter( $articles, function( $article ) {
				// Only include articles with display_status of 'To Improve'
				return isset( $article['display_status'] ) && $article['display_status'] === 'To Improve';
			} );

			// Reindex array after filtering
			$articles = array_values( $articles );

			// Calculate proper pagination for filtered results
			$total_filtered = count( $articles );

			// Apply pagination to the filtered results
			$offset = ( $page - 1 ) * $per_page;
			$articles = array_slice( $articles, $offset, $per_page );
		}

		// Prepare pagination info
		if ( ( $status === 'to_improve' || $status === 'to_analyse' ) && isset( $total_filtered ) ) {
			// For filtered statuses, use the filtered counts
			$pagination = array(
				'total' => $total_filtered,
				'total_pages' => ceil( $total_filtered / $per_page ),
				'page' => $page,
				'per_page' => $per_page
			);
		} else {
			// For other statuses, use query counts
			$pagination = array(
				'total' => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page' => $page,
				'per_page' => $per_page
			);
		}

		return $this->create_rest_response( array(
			'success' => true,
			'data' => $articles,
			'pagination' => $pagination
		) );
	}

	/**
	 * Toggle article ignored status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function toggle_article_ignored( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$ignored = $request->get_param( 'ignored' );

		// Set the ignored status
		$result = EPKB_AI_Content_Analysis_Utilities::set_article_ignored( $article_id, $ignored );

		if ( ! $result ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Failed to update article ignored status', 'echo-knowledge-base' )
			), 500 );
		}

		// Get updated display status
		$display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $article_id );

		return $this->create_rest_response( array(
			'success' => true,
			'display_status' => $display_status,
			'is_ignored' => $ignored
		) );
	}

	/**
	 * Toggle article done status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function toggle_article_done( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$done = $request->get_param( 'done' );

		// Set the done status
		$result = EPKB_AI_Content_Analysis_Utilities::set_article_done( $article_id, $done );

		if ( ! $result ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Failed to update article done status', 'echo-knowledge-base' )
			), 500 );
		}

		// Get updated display status
		$display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $article_id );

		return $this->create_rest_response( array(
			'success' => true,
			'display_status' => $display_status,
			'is_done' => $done
		) );
	}

	/**
	 * Get detailed analysis for a specific article
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_article_analysis_details( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$kb_id = $request->get_param( 'kb_id' );

		// Get article post
		$post = get_post( $article_id );
		if ( ! $post ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Article not found', 'echo-knowledge-base' )
			), 404 );
		}

		// Get analysis data from database
		$analysis_data = EPKB_AI_Content_Analysis_Utilities::get_article_analysis_data( $article_id );

		// Get tags analysis details with structured data
		$tags_report = EPKB_AI_Tags_Usage::get_detailed_report( $article_id );
		if ( is_wp_error( $tags_report ) ) {
			$tags_report = array(
				'score' => 0,
				'current_tags' => array(),
				'suggested_tags' => array(),
				'score_components' => array(),
				'recommendations' => array()
			);
		}

		// Build detailed response
		$details = array(
			'article_id' => $article_id,
			'title' => $post->post_title,
			'status' => $post->post_status,
			'score' => ( $analysis_data && isset( $analysis_data['scores']['overall'] ) ) ? $analysis_data['scores']['overall'] : 0,
			'importance' => ( $analysis_data && isset( $analysis_data['scores']['importance'] ) ) ? $analysis_data['scores']['importance'] : 0,
			'last_analyzed' => ( $analysis_data && isset( $analysis_data['dates']['analyzed'] ) ) ? $analysis_data['dates']['analyzed'] : null,
			'display_status' => EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $article_id ),
			'tags_score' => $tags_report['score'],
			'tags_interpretation' => isset( $tags_report['score_interpretation'] ) ? $tags_report['score_interpretation'] : '',
			'current_tags' => $tags_report['current_tags'],
			'suggested_tags' => $tags_report['suggested_tags'],
			'tags_score_components' => $tags_report['score_components'],
			'tags_recommendations' => isset( $tags_report['recommendations'] ) ? $tags_report['recommendations'] : array(),
			'scoreComponents' => array()
		);

		// Add overall score components - check if they exist in scores array
		if ( $analysis_data && isset( $analysis_data['scores']['components'] ) && is_array( $analysis_data['scores']['components'] ) ) {
			// Build score components from the components array
			$score_mapping = array(
				'tags_usage' => 'Tags Usage',
				'gap_analysis' => 'Score 2',
				'readability' => 'Score 3'
			);

			foreach ( $score_mapping as $key => $name ) {
				if ( isset( $analysis_data['scores']['components'][$key] ) ) {
					$details['scoreComponents'][] = array(
						'name' => $name,
						'value' => $analysis_data['scores']['components'][$key]
					);
				}
			}
		}

		// If no score components found, use defaults
		if ( empty( $details['scoreComponents'] ) ) {
			$details['scoreComponents'] = array(
				array( 'name' => 'Tags Usage', 'value' => $tags_report['score'] ),
				array( 'name' => 'Score 2', 'value' => 0 ),
			);
		}

		return $this->create_rest_response( array(
			'success' => true,
			'data' => $details
		) );
	}

	/**
	 * Add tag to article
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function add_article_tag( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$tag_name = $request->get_param( 'tag_name' );
		$kb_id = $request->get_param( 'kb_id' );

		// Get article post
		$post = get_post( $article_id );
		if ( ! $post ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Article not found', 'echo-knowledge-base' )
			), 404 );
		}

		// Get KB tag taxonomy name
		$kb_tag_taxonomy = EPKB_KB_Handler::get_tag_taxonomy_name( $kb_id );
		if ( empty( $kb_tag_taxonomy ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Could not determine KB tag taxonomy', 'echo-knowledge-base' )
			), 500 );
		}

		// Check if tag already exists, if not create it
		$term = get_term_by( 'name', $tag_name, $kb_tag_taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $tag_name, $kb_tag_taxonomy );
			if ( is_wp_error( $term ) ) {
				return $this->create_rest_response( array(
					'success' => false,
					'message' => $term->get_error_message()
				), 500 );
			}
			$term_id = $term['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		// Add tag to article
		$result = wp_set_object_terms( $article_id, intval( $term_id ), $kb_tag_taxonomy, true );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			), 500 );
		}

		return $this->create_rest_response( array(
			'success' => true,
			'message' => __( 'Tag added successfully', 'echo-knowledge-base' ),
			'tag_id' => $term_id
		) );
	}

	/**
	 * Remove tag from article
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function remove_article_tag( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$tag_id = $request->get_param( 'tag_id' );
		$kb_id = $request->get_param( 'kb_id' );

		// Get article post
		$post = get_post( $article_id );
		if ( ! $post ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Article not found', 'echo-knowledge-base' )
			), 404 );
		}

		// Get KB tag taxonomy name
		$kb_tag_taxonomy = EPKB_KB_Handler::get_tag_taxonomy_name( $kb_id );
		if ( empty( $kb_tag_taxonomy ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Could not determine KB tag taxonomy', 'echo-knowledge-base' )
			), 500 );
		}

		// Get current tags
		$current_tags = wp_get_object_terms( $article_id, $kb_tag_taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_tags ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => $current_tags->get_error_message()
			), 500 );
		}

		// Remove the specified tag
		$updated_tags = array_diff( $current_tags, array( $tag_id ) );

		// Update article tags
		$result = wp_set_object_terms( $article_id, $updated_tags, $kb_tag_taxonomy );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			), 500 );
		}

		return $this->create_rest_response( array(
			'success' => true,
			'message' => __( 'Tag removed successfully', 'echo-knowledge-base' )
		) );
	}

	/**
	 * Edit article tag
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function edit_article_tag( $request ) {

		$article_id = $request->get_param( 'article_id' );
		$tag_id = $request->get_param( 'tag_id' );
		$new_name = $request->get_param( 'new_name' );
		$kb_id = $request->get_param( 'kb_id' );

		// Get article post
		$post = get_post( $article_id );
		if ( ! $post ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Article not found', 'echo-knowledge-base' )
			), 404 );
		}

		// Get KB tag taxonomy name
		$kb_tag_taxonomy = EPKB_KB_Handler::get_tag_taxonomy_name( $kb_id );
		if ( empty( $kb_tag_taxonomy ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Could not determine KB tag taxonomy', 'echo-knowledge-base' )
			), 500 );
		}

		// Get the tag term
		$term = get_term( $tag_id, $kb_tag_taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => __( 'Tag not found', 'echo-knowledge-base' )
			), 404 );
		}

		// Update the tag name
		$result = wp_update_term( $tag_id, $kb_tag_taxonomy, array(
			'name' => $new_name,
			'slug' => sanitize_title( $new_name )
		) );

		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			), 500 );
		}

		return $this->create_rest_response( array(
			'success' => true,
			'message' => __( 'Tag updated successfully', 'echo-knowledge-base' )
		) );
	}
}
