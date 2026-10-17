<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Removes trashed and deleted posts from the AI provider so AI Chat and AI Search stop answering from their content.
 *
 * Provider calls take seconds, so the trash/delete request only marks the post's synced training rows as 'removing'
 * and schedules a WP-Cron event; the cron worker deletes the provider files and marks the rows skipped.
 * Other eligibility changes (draft, private, password) are handled by the Training Data reconcile and Sync.
 */
class EPKB_AI_Sync_Hooks {

	const CRON_HOOK = 'epkb_do_remove_training_files_cron_event';
	const BATCH_SIZE = 20;
	const MAX_RETRIES = 3;

	public static function init() {
		// both hooks fire after the change, so the cron worker never sees the post as still published
		add_action( 'trashed_post', array( __CLASS__, 'queue_provider_file_removal' ) );
		add_action( 'deleted_post', array( __CLASS__, 'queue_provider_file_removal' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'remove_provider_files' ) );
	}

	/**
	 * Mark the synced training rows of a trashed or deleted post for provider file removal and schedule the cron worker
	 *
	 * @param int $post_id
	 */
	public static function queue_provider_file_removal( $post_id ) {

		// one indexed UPDATE; revisions, menu items and never-synced posts match no rows and schedule nothing
		$marked_rows = ( new EPKB_AI_Training_Data_DB() )->mark_post_as_removing( $post_id );
		if ( $marked_rows > 0 ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Cron worker: delete provider files of rows marked 'removing' and mark the rows skipped
	 */
	public static function remove_provider_files() {

		$training_data_db = new EPKB_AI_Training_Data_DB();
		$rows = $training_data_db->get_rows_pending_removal( self::MAX_RETRIES, self::BATCH_SIZE + 1 );
		if ( empty( $rows ) ) {
			return;
		}

		$had_error = false;
		foreach ( array_slice( $rows, 0, self::BATCH_SIZE ) as $row ) {

			// post gone or trashed: provider file deleted, row marked skipped with file_id cleared
			$result = EPKB_AI_Utilities::skip_training_row_if_source_unavailable( $row, $training_data_db );
			if ( is_wp_error( $result ) ) {
				// row stays 'removing'; after MAX_RETRIES the Training Data reconcile or Sync removes the file
				$had_error = true;
				$training_data_db->update_training_data( $row->id, array( 'retry_count' => $row->retry_count + 1 ) );
				EPKB_AI_Log::add_log( $result, array( 'training_data_id' => $row->id, 'item_id' => $row->item_id, 'context' => 'remove_provider_files' ) );
				continue;
			}

			// post was restored before the worker ran: keep the provider file and let the user re-sync
			if ( $result === '' ) {
				$training_data_db->update_training_data( $row->id, array( 'status' => 'outdated', 'error_code' => null, 'error_message' => null ) );
			}
		}

		if ( $had_error || count( $rows ) > self::BATCH_SIZE ) {
			wp_schedule_single_event( time() + ( $had_error ? 5 * MINUTE_IN_SECONDS : 5 ), self::CRON_HOOK );
		}
	}
}
