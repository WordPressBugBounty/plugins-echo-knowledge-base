<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Content Analysis Job Manager
 *
 * Manages content analysis jobs with unified approach for both direct and cron modes.
 * Processes articles one at a time for consistent behavior, following the same pattern as sync.
 * Stores analysis state in WordPress option for persistence and single-job enforcement.
 */
class EPKB_AI_Content_Analysis_Job_Manager {

	const ANALYSIS_OPTION_NAME = 'epkb_ai_content_analysis_job_status';
	const CRON_HOOK = 'epkb_do_content_analysis_cron_event';

	/**
	 * Get current analysis job status
	 *
	 * @return array Analysis job data or default values
	 */
	public static function get_analysis_job() {

		$default = self::get_default_job_data();
		$job = get_option( self::ANALYSIS_OPTION_NAME, $default );

		return wp_parse_args( $job, $default );
	}

	/**
	 * Update analysis job status
	 *
	 * @param array $data Data to update
	 * @return bool Success
	 */
	public static function update_analysis_job( $data=array() ) {

		$job = self::get_analysis_job();
		if ( self::is_job_canceled( $job ) ) {
			return false;
		}

		$updated_job = array_merge( $job, $data );
		$updated_job['last_update'] = gmdate( 'Y-m-d H:i:s' );

		return update_option( self::ANALYSIS_OPTION_NAME, $updated_job, false );
	}

	/**
	 * Initialize a new analysis job
	 *
	 * @param array|string $article_ids Article IDs or 'ALL' or 'ALL_STATUS'
	 * @param string $mode 'direct' or 'cron'
	 * @return array|WP_Error
	 */
	public static function initialize_analysis_job( $article_ids, $mode = 'direct' ) {

		// Validate no active job exists
		if ( self::is_job_active() ) {
			return new WP_Error( 'job_active', __( 'An analysis job is already running', 'echo-knowledge-base' ) );
		}

		// Clear any existing analysis job data to ensure we start fresh
		// This prevents old sync records from being processed
		delete_option( self::ANALYSIS_OPTION_NAME );

		// Get articles to analyze
		$articles = array();
		if ( $article_ids === 'ALL' ) {

			// Get all KB articles
			$post_type = EPKB_KB_Handler::get_post_type( EPKB_KB_Config_DB::DEFAULT_KB_ID );
			$args = array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'orderby' => 'modified',
				'order' => 'DESC'
			);
			$all_articles = get_posts( $args );
			foreach ( $all_articles as $article_id ) {
				$articles[] = array( 'id' => $article_id, 'type' => 'article' );
			}

		} elseif ( is_array( $article_ids ) ) {
			foreach ( $article_ids as $article_id ) {
				$articles[] = array( 'id' => $article_id, 'type' => 'article' );
			}

		} else {
			return new WP_Error( 'invalid_article_ids', __( 'Invalid article IDs provided', 'echo-knowledge-base' ) );
		}

		// Check if we have articles to analyze
		if ( empty( $articles ) ) {
			return new WP_Error( 'no_articles', __( 'No articles found to analyze', 'echo-knowledge-base' ) );
		}

		// Create job data
		$job_data = array_merge( self::get_default_job_data(), array(
			'status' => $mode === 'cron' ? 'scheduled' : 'running',
			'type' => $mode,
			'items' => $articles,
			'total' => count( $articles )
		) );

		// Save job via helper to ensure consistent metadata (e.g., last_update)
		if ( ! self::update_analysis_job( $job_data ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save analysis job', 'echo-knowledge-base' ) );
		}

		return $job_data;
	}

	/**
	 * Process next article in the analysis queue
	 *
	 * @return array Result with processed count and status
	 */
	public static function process_next_analysis_item() {

		$job = self::get_analysis_job();

		// Skip if canceled
		if ( self::is_job_canceled( $job ) ) {
			return array( 'status' => 'idle' );
		}

		// Skip if not running
		if ( $job['status'] !== 'running' ) {
			return array( 'status' => $job['status'] );
		}

		// Get next unprocessed item (always process one at a time)
		$remaining_item = array_slice( $job['items'], $job['processed'], 1 );
		$remaining_item = empty( $remaining_item[0] ) ? null : $remaining_item[0];
		$remaining_article_ids = $remaining_item ? array( $remaining_item['id'] ) : array();

		// Check if we need to retry failed articles
		if ( empty( $remaining_article_ids ) && ! empty( $job['retry_article_ids'] ) && empty( $job['retrying'] ) ) {

			// Start retrying failed articles one by one
			$failed_items = array();
			foreach ( $job['retry_article_ids'] as $failed_id ) {
				$failed_items[] = array( 'id' => $failed_id, 'type' => 'article' );
			}

			$result = self::update_analysis_job( array( 'retrying' => true, 'items' => $failed_items, 'processed' => 0, 'total' => count( $failed_items ) ) );
			if ( ! $result ) {
				return array( 'status' => 'failed', 'message' => __( 'Stopping retrying failed articles', 'echo-knowledge-base' ) );
			}

			$job = self::get_analysis_job();
			$remaining_item = array_slice( $job['items'], 0, 1 );
			$remaining_item = empty( $remaining_item[0] ) ? null : $remaining_item[0];
			$remaining_article_ids = $remaining_item ? array( $remaining_item['id'] ) : array();
		}

		// Check if all done including retries
		if ( empty( $remaining_article_ids ) ) {
			self::update_analysis_job( array( 'status' => 'completed', 'percent' => 100 ) );
			return array( 'status' => 'completed' );
		}

		$consecutive_errors = $job['consecutive_errors'];
		$analyzed_articles = array();

		// Process the single article
		$article_id = $remaining_article_ids[0];
		$job['processed']++;

		// Perform the actual analysis
		$result = self::analyze_article( $article_id );
		if ( is_wp_error( $result ) ) {

			$is_retry = $result->get_error_data( 'retry' ) === true;

			$consecutive_errors = $is_retry ? $consecutive_errors + 1 : 0;
			$job['errors']++;

			$updated_articles[] = array(
				'id' => $article_id,
				'status' => 'error', 
				'message' => $result->get_error_message()
			);

			self::update_analysis_job();

			// Check if we've hit 5 consecutive errors
			if ( $consecutive_errors >= 5 ) {
				// Update job status and exit sync
				self::update_analysis_job( array(
					'status' => 'failed',
					'processed' => $job['processed'],
					'errors' => $job['errors'],
					'percent' => round( ( $job['processed'] / $job['total'] ) * 100 ),
					'consecutive_errors' => $consecutive_errors,
					'error_message' => __( 'Sync stopped after 5 consecutive errors', 'echo-knowledge-base' )
				) );

				return array(
					'status' => 'failed',
					'processed' => 0,
					'errors' => 1,
					'message' => __( 'Sync stopped after 5 consecutive errors', 'echo-knowledge-base' ),
					'updated_articles' => $updated_articles
				);
			}

			// Track failed articles for retry (only if not already retrying)
			if ( empty( $job['retrying'] ) && ! in_array( $article_id, $job['retry_article_ids'] ) && $is_retry ) {
				$job['retry_article_ids'][] = $article_id;
				self::update_analysis_job( array( 'retry_article_ids' => $job['retry_article_ids'] ) );
			}

		} else {
			// Reset consecutive errors on success
			$consecutive_errors = 0;

			// Send the analysis results including score and importance
			$post_update_data = array(
				'id' => $article_id,
				'status' => 'analyzed',
				'score' => isset( $result['score'] ) ? $result['score'] : 0,
				'importance' => isset( $result['importance'] ) ? $result['importance'] : 0,
				'scoreComponents' => isset( $result['scoreComponents'] ) ? $result['scoreComponents'] : array(),
				'analyzed_at' => isset( $result['analyzed_at'] ) ? $result['analyzed_at'] : current_time( 'mysql' )
			);

			$updated_articles[] = $post_update_data;
		}

		// Update job progress
		$new_processed = $job['processed'];
		$percent = $job['retrying'] ? 100 : round( ( $new_processed / $job['total'] ) * 100 );
		
		self::update_analysis_job( array( 'processed' => $new_processed, 'errors' => $job['errors'], 'percent' => $percent, 'consecutive_errors' => $consecutive_errors ) );
		
		// Check if complete
		if ( $new_processed >= $job['total'] ) {
			
			$result = self::update_analysis_job( array( 'status' => 'completed', 'percent' => 100, 'processed' => $new_processed ) );

			return array( 'status' => $result ? 'completed' : 'failed', 'updated_articles' => $updated_articles );
		}
		
		return array(
			'status' => self::is_job_canceled() ? 'idle' : 'running',
			'processed' => 1,
			'errors' => $job['errors'],
			'updated_articles' => $updated_articles
		);
	}

	private static function get_default_job_data() {
		return array(
			'status' => 'idle',	// idle, scheduled (cron), running (direct), completed, failed
			'type' => '',
			'items' => array(),
			'retry_article_ids' => array(),
			'retrying' => false,
			'cancel_requested' => false,
			'processed' => 0,
			'total' => 0,
			'percent' => 0,
			'errors' => 0,
			'consecutive_errors' => 0,
			'start_time' => gmdate( 'Y-m-d H:i:s' ),
			'last_update' => ''
		);
	}

	/**
	 * Check if a job is active
	 *
	 * @return bool
	 */
	public static function is_job_active() {
		$job = self::get_analysis_job();
		return in_array( $job['status'], array( 'scheduled', 'running' ) );
	}

	private static function is_job_canceled( $job = null ) {

		if ( empty( $job ) ) {
			$job = self::get_analysis_job();
		}

		return ! empty( $job['cancel_requested'] );
	}

	/**
	 * Cancel all analysis operations
	 *
	 * @return bool Success
	 */
	public static function cancel_all_analysis() {

		// Mark cancel requested and set to idle (align with sync semantics)
		self::update_analysis_job( array(
			'status' => 'idle',
			'cancel_requested' => true,
		) );
		
		// Clear scheduled cron event if exists
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		// Don't delete the cancel flag here - let it persist until a new sync starts
		// This prevents race conditions where a running process might not see the cancel
		
		return true;
	}

	/**
	 * Analyze a single article
	 *
	 * @param int $article_id Article ID
	 * @return array|WP_Error Analysis result
	 */
	private static function analyze_article( $article_id ) {

		$post = get_post( $article_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_article', __( 'Article not found', 'echo-knowledge-base' ), array( 'retry' => false ) );
		}

		// Clear any previous error state
		EPKB_AI_Content_Analysis_Utilities::clear_analysis_error( $article_id );

		try {
			// Run analysis using the analysis classes
			$gap_analysis = EPKB_AI_Gap_Analysis::analyze( $article_id );
			if ( is_wp_error( $gap_analysis ) ) {
				throw new Exception( $gap_analysis->get_error_message() );
			}
			$gap_score = isset( $gap_analysis['score'] ) ? $gap_analysis['score'] : 0;

			$tags_analysis = EPKB_AI_Tags_Usage::analyze( $article_id );
			if ( is_wp_error( $tags_analysis ) ) {
				throw new Exception( $tags_analysis->get_error_message() );
			}
			$tags_score = isset( $tags_analysis['score'] ) ? $tags_analysis['score'] : 0;

			/* $readability_analysis = EPKB_AI_Readability::analyze( $article_id );
			if ( is_wp_error( $readability_analysis ) ) {
				throw new Exception( $readability_analysis->get_error_message() );
			} */
			$readability_score = 0; //isset( $readability_analysis['score'] ) ? $readability_analysis['score'] : 0;

		} catch ( Exception $e ) {
			// Store error details in post meta for later display
			$error_message = $e->getMessage();
			EPKB_AI_Content_Analysis_Utilities::set_analysis_error( $article_id, $error_message, 500 );

			// Return WP_Error with retry flag
			return new WP_Error( 'analysis_failed', $error_message, array( 'retry' => true ) );
		}

		// Calculate overall score (average of the three scores)
		$overall_score = round( ( $gap_score + $tags_score + $readability_score ) / 3 );

		// Calculate importance (based on views, age, etc. - stub for now)
		$importance = rand( 50, 100 );

		// Save scores using utility class
		$scores = array(
			'overall' => $overall_score,
			'components' => array(
				'gap_analysis' => $gap_score,
				'tags_usage' => $tags_score,
				'readability' => $readability_score
			)
		);
		EPKB_AI_Content_Analysis_Utilities::save_article_scores( $article_id, $scores, $importance );
		EPKB_AI_Content_Analysis_Utilities::set_analysis_status( $article_id, 'analyzed' );
		EPKB_AI_Content_Analysis_Utilities::update_article_date( $article_id, 'analyzed' );
		EPKB_AI_Content_Analysis_Utilities::initialize_date_fields( $article_id );

		// Return analysis result
		$current_date = EPKB_AI_Content_Analysis_Utilities::get_article_date( $article_id, 'analyzed' );
		$display_status = EPKB_AI_Content_Analysis_Utilities::get_article_display_status( $article_id );
		return array(
			'id' => $article_id,
			'title' => $post->post_title,
			'score' => $overall_score,
			'importance' => $importance,
			'status' => 'analyzed',
			'display_status' => $display_status,
			'analyzed_at' => $current_date,
			'scoreComponents' => EPKB_AI_Content_Analysis_Utilities::format_score_components( $scores['components'] ),
			'details' => array(
				'gap_analysis' => $gap_analysis,
				'tags_analysis' => $tags_analysis,
				'readability_analysis' => array( 'score' => $readability_score ) // Placeholder for readability analysis
			)
		);
	}
}
