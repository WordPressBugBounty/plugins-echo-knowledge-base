<?php

/**
 * AI Cleanup Service
 * 
 * Handles cleanup tasks for AI functionality using lazy cleanup approach
 */
class EPKB_AI_Cron {
	
	const CLEANUP_TRANSIENT = 'epkb_ai_last_cleanup';
	const CLEANUP_INTERVAL = DAY_IN_SECONDS; // Run once per day
	
	/**
	 * Constructor - Initialize cleanup hooks
	 */
	public function __construct() {
		// Hook into AI operations to trigger lazy cleanup
		add_action( 'epkb_ai_conversation_started', array( $this, 'maybe_run_cleanup' ) );
		add_action( 'epkb_ai_search_performed', array( $this, 'maybe_run_cleanup' ) );
		add_action( 'epkb_ai_admin_page_loaded', array( $this, 'maybe_run_cleanup' ) );
	}
	
	/**
	 * Check if cleanup should run and execute if needed
	 * This is called lazily when AI features are actually used
	 */
	public function maybe_run_cleanup() {
		// Only run if AI is enabled
		if ( ! EPKB_AI_Utilities::is_ai_enabled() ) {
			return;
		}
		
		// Check if we've run cleanup recently
		$last_cleanup = get_transient( self::CLEANUP_TRANSIENT );
		if ( $last_cleanup !== false ) {
			return; // Already ran within the interval
		}
		
		// Set transient to prevent multiple runs
		set_transient( self::CLEANUP_TRANSIENT, time(), self::CLEANUP_INTERVAL );
		
		// Run cleanup in the background if possible
		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( 'epkb_ai_run_cleanup_now' ) ) {
			wp_schedule_single_event( time() + 10, 'epkb_ai_run_cleanup_now' );
			add_action( 'epkb_ai_run_cleanup_now', array( __CLASS__, 'cleanup_expired_conversations') );
		} else {
			// Fallback: run directly (but this might slow down the request)
			self::cleanup_expired_conversations();
		}
	}
	
	/**
	 * Run cleanup for expired response IDs
	 */
	public static function cleanup_expired_conversations() {
		try {
			$messages_db = new EPKB_AI_Messages_DB();
			
			// Delete old conversations based on retention period
			$retention_days = self::get_retention_days();
			$deleted_count = $messages_db->delete_old_conversations( $retention_days );
			if ( is_wp_error( $deleted_count ) ) {
				EPKB_AI_Utilities::add_log( 'AI Cron Error: Failed to delete old conversations - ' . $deleted_count->get_error_message() );
			} elseif ( $deleted_count > 0 ) {
				EPKB_AI_Utilities::add_log( 'AI Cron: Deleted ' . $deleted_count . ' old conversations' );
			}
		} catch ( Exception $e ) {
			EPKB_AI_Utilities::add_log( 'AI Cron Exception: ' . $e->getMessage() );
		}
	}
	
	/**
	 * Get conversation retention period in days
	 *
	 * @return int Number of days to keep conversations (default 90)
	 */
	private static function get_retention_days() {
		// Get retention days from AI config
		$retention_days = 10;   //TODO
		
		// Apply filter for customization
		return apply_filters( 'epkb_ai_conversation_retention_days', $retention_days );
	}
}