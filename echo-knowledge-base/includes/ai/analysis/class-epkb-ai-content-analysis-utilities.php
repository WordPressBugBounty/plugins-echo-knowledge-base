<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Content Analysis Utilities
 *
 * Utility class for managing content analysis metadata and common operations.
 * Provides centralized methods for score management, date tracking, and metadata operations.
 */
class EPKB_AI_Content_Analysis_Utilities {

	// Metadata keys
	const META_SCORES_DATA = '_epkb_content_scores_data';
	const META_ANALYSIS_STATUS = '_epkb_content_analysis_status';
	const META_ANALYSIS_ERROR = '_epkb_content_analysis_error';
	const META_ANALYSIS_ERROR_CODE = '_epkb_content_analysis_error_code';

	// Date metadata keys
	const META_DATE_ANALYZED = '_epkb_content_date_analyzed';
	const META_DATE_IMPROVED = '_epkb_content_date_improved';
	const META_DATE_IGNORED = '_epkb_content_date_ignored';
	const META_DATE_DONE = '_epkb_content_date_done';

	/**
	 * Save article analysis scores
	 *
	 * @param int $article_id Article ID
	 * @param array $scores Array with 'overall' and 'components' keys
	 * @param int $importance Importance score
	 * @return bool Success
	 */
	public static function save_article_scores( $article_id, $scores, $importance = 0 ) {

		if ( ! is_array( $scores ) || ! isset( $scores['overall'] ) || ! isset( $scores['components'] ) ) {
			return false;
		}

		$scores_data = array(
			'overall' => (int) $scores['overall'],
			'components' => $scores['components'],
			'importance' => (int) $importance
		);

		return update_post_meta( $article_id, self::META_SCORES_DATA, $scores_data );
	}

	/**
	 * Get article scores
	 *
	 * @param int $article_id Article ID
	 * @return array|null Array of scores or null if not analyzed
	 */
	public static function get_article_scores( $article_id ) {

		$scores_data = get_post_meta( $article_id, self::META_SCORES_DATA, true );

		if ( empty( $scores_data ) || ! is_array( $scores_data ) ) {
			return null;
		}

		// Ensure all expected keys exist
		return wp_parse_args( $scores_data, array(
			'overall' => 0,
			'components' => array(),
			'importance' => 0
		) );
	}

	/**
	 * Add or update a score component
	 *
	 * @param int $article_id Article ID
	 * @param string $component_name Name of the score component
	 * @param int $score Score value (0-100)
	 * @param bool $recalculate_overall Whether to recalculate the overall score
	 * @return bool Success
	 */
	public static function add_score_component( $article_id, $component_name, $score, $recalculate_overall = true ) {

		$scores_data = self::get_article_scores( $article_id );

		if ( ! $scores_data ) {
			return false;
		}

		// Add or update the component
		$scores_data['components'][$component_name] = (int) $score;

		// Recalculate overall score if requested
		if ( $recalculate_overall && ! empty( $scores_data['components'] ) ) {
			$total = array_sum( $scores_data['components'] );
			$count = count( $scores_data['components'] );
			$scores_data['overall'] = round( $total / $count );
		}

		return update_post_meta( $article_id, self::META_SCORES_DATA, $scores_data );
	}

	/**
	 * Remove a score component
	 *
	 * @param int $article_id Article ID
	 * @param string $component_name Name of the score component to remove
	 * @param bool $recalculate_overall Whether to recalculate the overall score
	 * @return bool Success
	 */
	public static function remove_score_component( $article_id, $component_name, $recalculate_overall = true ) {

		$scores_data = self::get_article_scores( $article_id );

		if ( ! $scores_data || ! isset( $scores_data['components'][$component_name] ) ) {
			return false;
		}

		unset( $scores_data['components'][$component_name] );

		// Recalculate overall score if requested and components remain
		if ( $recalculate_overall && ! empty( $scores_data['components'] ) ) {
			$total = array_sum( $scores_data['components'] );
			$count = count( $scores_data['components'] );
			$scores_data['overall'] = round( $total / $count );
		} elseif ( empty( $scores_data['components'] ) ) {
			$scores_data['overall'] = 0;
		}

		return update_post_meta( $article_id, self::META_SCORES_DATA, $scores_data );
	}

	/**
	 * Set article analysis status
	 *
	 * @param int $article_id Article ID
	 * @param string $status Status: 'analyzed', 'error', 'pending'
	 * @return bool Success
	 */
	public static function set_analysis_status( $article_id, $status ) {
		return update_post_meta( $article_id, self::META_ANALYSIS_STATUS, $status );
	}

	/**
	 * Get article analysis status
	 *
	 * @param int $article_id Article ID
	 * @return string Status or empty string if not set
	 */
	public static function get_analysis_status( $article_id ) {
		return get_post_meta( $article_id, self::META_ANALYSIS_STATUS, true ) ?: '';
	}

	/**
	 * Set analysis error
	 *
	 * @param int $article_id Article ID
	 * @param string $error_message Error message (max 200 characters)
	 * @param int $error_code Error code
	 * @return bool Success
	 */
	public static function set_analysis_error( $article_id, $error_message, $error_code = 500 ) {

		// Limit error message length
		if ( strlen( $error_message ) > 200 ) {
			$error_message = substr( $error_message, 0, 197 ) . '...';
		}

		update_post_meta( $article_id, self::META_ANALYSIS_ERROR, $error_message );
		update_post_meta( $article_id, self::META_ANALYSIS_ERROR_CODE, $error_code );

		return self::set_analysis_status( $article_id, 'error' );
	}

	/**
	 * Clear analysis error
	 *
	 * @param int $article_id Article ID
	 * @return bool Success
	 */
	public static function clear_analysis_error( $article_id ) {
		delete_post_meta( $article_id, self::META_ANALYSIS_ERROR );
		delete_post_meta( $article_id, self::META_ANALYSIS_ERROR_CODE );
		return true;
	}

	/**
	 * Get analysis error
	 *
	 * @param int $article_id Article ID
	 * @return array Array with 'message' and 'code' keys
	 */
	public static function get_analysis_error( $article_id ) {
		return array(
			'message' => get_post_meta( $article_id, self::META_ANALYSIS_ERROR, true ) ?: '',
			'code' => get_post_meta( $article_id, self::META_ANALYSIS_ERROR_CODE, true ) ?: 0
		);
	}

	/**
	 * Update article date for specific action
	 *
	 * @param int $article_id Article ID
	 * @param string $action Action type: 'analyzed', 'improved', 'ignored', 'done'
	 * @param string|null $date Date string or null for current time
	 * @return bool Success
	 */
	public static function update_article_date( $article_id, $action, $date = null ) {

		$valid_actions = array( 'analyzed', 'improved', 'ignored', 'done' );

		if ( ! in_array( $action, $valid_actions ) ) {
			return false;
		}

		$meta_key = constant( 'self::META_DATE_' . strtoupper( $action ) );
		$date_value = $date ?: current_time( 'mysql' );

		return update_post_meta( $article_id, $meta_key, $date_value );
	}

	/**
	 * Clear article date for specific action
	 *
	 * @param int $article_id Article ID
	 * @param string $action Action type: 'analyzed', 'improved', 'ignored', 'done'
	 * @return bool Success
	 */
	public static function clear_article_date( $article_id, $action ) {

		$valid_actions = array( 'analyzed', 'improved', 'ignored', 'done' );

		if ( ! in_array( $action, $valid_actions ) ) {
			return false;
		}

		$meta_key = constant( 'self::META_DATE_' . strtoupper( $action ) );

		return delete_post_meta( $article_id, $meta_key );
	}

	/**
	 * Get all article dates
	 *
	 * @param int $article_id Article ID
	 * @return array Array of dates
	 */
	public static function get_article_dates( $article_id ) {
		return array(
			'analyzed' => get_post_meta( $article_id, self::META_DATE_ANALYZED, true ) ?: '',
			'improved' => get_post_meta( $article_id, self::META_DATE_IMPROVED, true ) ?: '',
			'ignored' => get_post_meta( $article_id, self::META_DATE_IGNORED, true ) ?: '',
			'done' => get_post_meta( $article_id, self::META_DATE_DONE, true ) ?: ''
		);
	}

	/**
	 * Get a specific article date
	 *
	 * @param int $article_id Article ID
	 * @param string $action Action type: 'analyzed', 'improved', 'ignored', 'done'
	 * @return string Date string or empty string
	 */
	public static function get_article_date( $article_id, $action ) {

		$valid_actions = array( 'analyzed', 'improved', 'ignored', 'done' );

		if ( ! in_array( $action, $valid_actions ) ) {
			return '';
		}

		$meta_key = constant( 'self::META_DATE_' . strtoupper( $action ) );

		return get_post_meta( $article_id, $meta_key, true ) ?: '';
	}

	/**
	 * Initialize all date fields for an article
	 *
	 * @param int $article_id Article ID
	 * @return bool Success
	 */
	public static function initialize_date_fields( $article_id ) {

		$date_fields = array(
			self::META_DATE_IMPROVED,
			self::META_DATE_IGNORED,
			self::META_DATE_DONE
		);

		foreach ( $date_fields as $meta_key ) {
			if ( ! metadata_exists( 'post', $article_id, $meta_key ) ) {
				update_post_meta( $article_id, $meta_key, '' );
			}
		}

		return true;
	}

	/**
	 * Clear all analysis metadata for an article
	 *
	 * @param int $article_id Article ID
	 * @return bool Success
	 */
	public static function clear_all_analysis_metadata( $article_id ) {

		$meta_keys = array(
			self::META_SCORES_DATA,
			self::META_ANALYSIS_STATUS,
			self::META_ANALYSIS_ERROR,
			self::META_ANALYSIS_ERROR_CODE,
			self::META_DATE_ANALYZED,
			self::META_DATE_IMPROVED,
			self::META_DATE_IGNORED,
			self::META_DATE_DONE
		);

		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta( $article_id, $meta_key );
		}

		return true;
	}

	/**
	 * Get complete analysis data for an article
	 *
	 * @param int $article_id Article ID
	 * @return array Complete analysis data
	 */
	public static function get_article_analysis_data( $article_id ) {

		$scores = self::get_article_scores( $article_id );
		$dates = self::get_article_dates( $article_id );
		$status = self::get_analysis_status( $article_id );
		$error = self::get_analysis_error( $article_id );

		return array(
			'status' => $status ?: 'not_analyzed',
			'scores' => $scores,
			'dates' => $dates,
			'error' => $error,
			'is_analyzed' => ! empty( $dates['analyzed'] ) && $status === 'analyzed',
			'is_improved' => ! empty( $dates['improved'] ),
			'is_ignored' => ! empty( $dates['ignored'] ),
			'is_done' => ! empty( $dates['done'] )
		);
	}

	/**
	 * Set article as ignored
	 *
	 * @param int $article_id Article ID
	 * @return bool Success
	 */
	public static function set_article_ignored( $article_id, $ignored = true ) {
		if ( $ignored ) {
			return self::update_article_date( $article_id, 'ignored' );
		} else {
			return self::clear_article_date( $article_id, 'ignored' );
		}
	}

	/**
	 * Set article as done
	 *
	 * @param int $article_id Article ID
	 * @return bool Success
	 */
	public static function set_article_done( $article_id, $done = true ) {
		if ( $done ) {
			return self::update_article_date( $article_id, 'done' );
		} else {
			return self::clear_article_date( $article_id, 'done' );
		}
	}

	/**
	 * Get article display status for the Content Analysis table
	 *
	 * @param int $article_id Article ID
	 * @return string Status: 'To Analyze', 'Ignored', 'Done', 'To Improve'
	 */
	public static function get_article_display_status( $article_id ) {

		$data = self::get_article_analysis_data( $article_id );
		$post = get_post( $article_id );

		// Check if ignored
		if ( $data['is_ignored'] ) {
			return 'Ignored';
		}

		// Check if done
		if ( $data['is_done'] ) {
			return 'Done';
		}

		// Check if needs analysis (never analyzed or analyzed date is older than last update)
		if ( ! $data['is_analyzed'] || empty( $data['scores'] ) ) {
			return 'To Analyze';
		}

		// Check if analyzed date is older than article update date
		if ( ! empty( $data['dates']['analyzed'] ) && $post ) {
			$analyzed_time = strtotime( $data['dates']['analyzed'] );
			$updated_time = strtotime( $post->post_modified );

			if ( $updated_time > $analyzed_time ) {
				return 'To Analyze';
			}
		}

		// Default to 'To Improve' if none of the above conditions are met
		return 'To Improve';
	}

	/**
	 * Check if article needs improvement
	 *
	 * @param int $article_id Article ID
	 * @param int $score_threshold Minimum acceptable score
	 * @return bool
	 */
	public static function needs_improvement( $article_id, $score_threshold = 70 ) {

		$data = self::get_article_analysis_data( $article_id );

		// Not analyzed or has error
		if ( ! $data['is_analyzed'] ) {
			return false;
		}

		// Already improved, ignored, or done
		if ( $data['is_improved'] || $data['is_ignored'] || $data['is_done'] ) {
			return false;
		}

		// Check score threshold
		if ( $data['scores'] && $data['scores']['overall'] < $score_threshold ) {
			return true;
		}

		return false;
	}

	/**
	 * Format score components for display
	 *
	 * @param array $components Score components array
	 * @return array Formatted components
	 */
	public static function format_score_components( $components ) {

		$formatted = array();

		// Always show Tags Usage first, then placeholder scores for future features
		// Tags Usage
		if ( isset( $components['tags_usage'] ) ) {
			$formatted[] = array(
				'name' => __( 'Tags Usage', 'echo-knowledge-base' ),
				'value' => $components['tags_usage']
			);
		} else {
			$formatted[] = array(
				'name' => __( 'Tags Usage', 'echo-knowledge-base' ),
				'value' => '-'
			);
		}

		// Future Score 2 (will be Gap Analysis but hidden for now)
		$formatted[] = array(
			'name' => 'Score 2',
			'value' => '-'
		);

		// Future Score 3 (will be Readability but hidden for now)
		$formatted[] = array(
			'name' => 'Score 3',
			'value' => '-'
		);

		return $formatted;
	}
}