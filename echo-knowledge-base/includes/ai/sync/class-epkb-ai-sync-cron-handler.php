<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Sync Cron Handler
 * 
 * Handles WP-Cron execution of sync jobs
 */
class EPKB_AI_Sync_Cron_Handler {

	/**
	 * Initialize cron handler
	 */
	public static function init() {
		add_action( EPKB_AI_Sync_Job_Manager::CRON_HOOK, array( __CLASS__, 'process_sync_cron' ) );
	}
	
	/**
	 * Process sync via cron
	 * 
	 * This method is called by WP-Cron to process sync jobs in the background
	 */
	public static function process_sync_cron() {
		
		// Check if AI is enabled
		if ( ! EPKB_AI_Utilities::is_ai_enabled() ) {
			EPKB_AI_Log::add_log( 'Cron sync skipped: AI is not enabled' );
			return;
		}
		
		// Get current job
		$job = EPKB_AI_Sync_Job_Manager::get_sync_job();
		if ( $job['status'] === 'idle' || $job['type'] !== 'cron' ) {
			EPKB_AI_Log::add_log( 'Cron sync skipped: No active cron job found' );
			return;
		}
		
		// Check if canceled
		if ( ! empty( $job['cancel_requested'] ) ) {
			EPKB_AI_Sync_Job_Manager::update_sync_job( array( 'status' => 'canceled' ) );
			EPKB_AI_Log::add_log( 'Cron sync canceled by user request' );
			return;
		}
		
		// Update status to running
		if ( $job['status'] === 'scheduled' ) {
			EPKB_AI_Sync_Job_Manager::update_sync_job( array( 'status' => 'running' ) );
		}
		
		// Process batch
		$result = EPKB_AI_Sync_Job_Manager::process_next_batch();
		
		// Check result
		if ( $result['status'] === 'running' ) {
			// Schedule next batch
			wp_schedule_single_event( time() + 60, EPKB_AI_Sync_Job_Manager::CRON_HOOK );
			
			// Update status back to scheduled
			EPKB_AI_Sync_Job_Manager::update_sync_job( array( 'status' => 'scheduled' ) );
			
			EPKB_AI_Log::add_log( 'Cron sync batch completed, next batch scheduled', array(	'processed' => isset( $result['processed'] ) ? $result['processed'] : 0,
										'errors' => isset( $result['errors'] ) ? $result['errors'] : 0 ) );

		} elseif ( $result['status'] === 'completed' ) {
			EPKB_AI_Log::add_log( 'Cron sync completed successfully', array( 'job' => $job ) );

		} elseif ( $result['status'] === 'canceled' ) {
			EPKB_AI_Log::add_log( 'Cron sync was canceled' );

		} else {
			EPKB_AI_Log::add_log( 'Cron sync ended with status: ' . $result['status'] );
		}
	}
}