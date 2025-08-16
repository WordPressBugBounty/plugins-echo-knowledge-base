<?php

/**
 * AI Sync Hooks
 * 
 * Handles WordPress hooks for automatic sync operations.
 * Monitors content changes and triggers appropriate sync actions.
 */
class EPKB_AI_Sync_Hooks {

	public function __construct() {

		// Only enable hooks if auto-sync is enabled
		if ( ! $this->is_auto_sync_enabled() ) {
			return;
		}

		// Post update hooks
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_post_delete' ) );
		add_action( 'transition_post_status', array( $this, 'handle_post_status_change' ), 10, 3 );
		
		// Attachment hooks - disabled for now
		//add_action( 'add_attachment', array( $this, 'handle_attachment_add' ) );
		//add_action( 'edit_attachment', array( $this, 'handle_attachment_edit' ) );
		//add_action( 'delete_attachment', array( $this, 'handle_attachment_delete' ) );
	}
	
	/**
	 * Handle post save
	 *
	 * @param int $post_id Post ID
	 * @param WP_Post $post Post object
	 * @param bool $update Whether this is an update
	 * @return void
	 */
	public function handle_post_save( $post_id, $post, $update ) {
		
		// Skip auto saves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		
		// Validate post object
		if ( ! $post || ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}
		
		// Check if this is a KB post type
		if ( ! $this->is_kb_post_type( $post->post_type ) ) {   // TODO
			return;
		}
		
		// Skip if not published
		if ( $post->post_status !== 'publish' ) {
			return;
		}
		
		// For updates, mark existing training data as outdated
		if ( $update ) {
			$this->mark_post_outdated( $post_id, $post->post_type );
		}
		
		// Queue sync if auto-sync is enabled
		if ( $this->is_auto_sync_enabled() ) {
			$this->sync_one_post( $post );
		}
	}
	
	/**
	 * Handle post deletion
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function handle_post_delete( $post_id ) {
		
		// Get the post object
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		
		// Check if this is a KB post type
		if ( ! $this->is_kb_post_type( $post->post_type ) ) {
			return;
		}
		
		// Get all collections to find which ones contain this post
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		if ( is_wp_error( $collections ) || empty( $collections ) ) {
			return;
		}
		
		$sync_manager = new EPKB_AI_Sync_Manager();
		$training_data_db = new EPKB_AI_Training_Data_DB();

		// Remove from each collection that contains this post
		foreach ( $collections as $collection_id => $collection_config ) {
			$training_data = $training_data_db->get_training_data_by_item( $collection_id, $post_id );
			if ( $training_data ) {
				// Remove directly instead of scheduling
				$sync_manager->remove_post( $post_id, $collection_id );
			}
		}
	}
	
	/**
	 * Handle post status change
	 *
	 * @param string $new_status New post status
	 * @param string $old_status Old post status
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function handle_post_status_change( $new_status, $old_status, $post ) {
		
		// Validate post object
		if ( ! $post || ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}
		
		// Check if this is a KB post type
		if ( ! $this->is_kb_post_type( $post->post_type ) ) {
			return;
		}
		
		// Post unpublished - remove from sync
		if ( $old_status === 'publish' && $new_status !== 'publish' ) {
			$this->handle_post_delete( $post->ID );
		}
		
		// Post published - add to sync
		elseif ( $old_status !== 'publish' && $new_status === 'publish' ) {
			if ( $this->is_auto_sync_enabled() ) {
				$this->sync_one_post( $post );
			}
		}
	}
	
	/**
	 * Handle attachment addition
	 *
	 * @param int $attachment_id Attachment ID
	 * @return void
	 */
	public function handle_attachment_add( $attachment_id ) {
		
		// Check if attachment sync is enabled
		if ( ! $this->is_attachment_sync_enabled() ) {
			return;
		}
		
		// Check if attachment type is supported
		$mime_type = get_post_mime_type( $attachment_id );
		$content_processor = new EPKB_AI_Content_Processor();
		
		if ( ! $content_processor->is_supported_attachment_type( $mime_type ) ) {
			return;
		}
		
		// Queue sync
		if ( $this->is_auto_sync_enabled() ) {
			$this->sync_one_attachment( $attachment_id );
		}
	}
	
	/**
	 * Handle attachment edit
	 *
	 * @param int $attachment_id Attachment ID
	 * @return void
	 */
	public function handle_attachment_edit( $attachment_id ) {
		
		// Mark as outdated
		$this->mark_attachment_outdated( $attachment_id );
		
		// Queue sync if enabled
		if ( $this->is_auto_sync_enabled() && $this->is_attachment_sync_enabled() ) {
			$this->sync_one_attachment( $attachment_id );
		}
	}
	
	/**
	 * Handle attachment deletion
	 *
	 * @param int $attachment_id Attachment ID
	 * @return void
	 */
	public function handle_attachment_delete( $attachment_id ) {
		
		// Get collections that include attachments
		$collection_ids = $this->get_collections_for_post_type( 'attachment' );
		
		$sync_manager = new EPKB_AI_Sync_Manager();

		// Schedule cleanup for each collection
		foreach ( $collection_ids as $collection_id ) {
			/* $sync_manager->schedule_cron_sync( array(
				'type' => 'cleanup',
				'action' => 'remove_attachment',
				'attachment_id' => $attachment_id,
				'collection_id' => $collection_id
			) ); */
		}
	}
	
	/**
	 * Check if post type is a KB post type or configured for AI sync
	 *
	 * @param string $post_type Post type
	 * @return bool
	 */
	private function is_kb_post_type( $post_type ) {
		// Get configured post types for AI sync
		$configured_post_types = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_training_data_store_post_types', array( 'epkb_post_type_1' ) ); // TODO
		
		// Check if this post type is in the configured list
		if ( in_array( $post_type, $configured_post_types, true ) ) {
			return true;
		}
		
		// Also check all KB post types for backward compatibility
		for ( $kb_id = 1; $kb_id <= 10; $kb_id++ ) {
			if ( $post_type === EPKB_KB_Handler::get_post_type( $kb_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Mark post as outdated
	 *
	 * @param int $post_id Post ID
	 * @param string $post_type Post type
	 * @return void
	 */
	private function mark_post_outdated( $post_id, $post_type ) {
		$training_data_db = new EPKB_AI_Training_Data_DB();
		$training_data_db->mark_source_as_outdated( $post_type, (string) $post_id );
	}
	
	/**
	 * Mark attachment as outdated
	 *
	 * @param int $attachment_id Attachment ID
	 * @return void
	 */
	private function mark_attachment_outdated( $attachment_id ) {
		$training_data_db = new EPKB_AI_Training_Data_DB();
		$training_data_db->mark_source_as_outdated( 'attachment', $attachment_id );
	}
	
	/**
	 * Queue post for sync
	 *
	 * @param WP_Post $post
	 * @return void
	 */
	private function sync_one_post( $post ) {
		
		// Validate post object
		if ( ! $post || ! is_object( $post ) || ! isset( $post->post_type ) || ! isset( $post->ID ) ) {
			return;
		}
		
		// Check if sync is already running
		if ( EPKB_AI_Sync_Job_Manager::is_job_active() ) {
			// Don't interfere with running sync, mark post as outdated instead
			$this->mark_post_outdated( $post->ID, $post->post_type );
			return;
		}
		
		// Get collections that include this post type
		$collection_ids = $this->get_collections_for_post_type( $post->post_type );
		
		if ( empty( $collection_ids ) ) {
			return;
		}

		// Use the first collection that contains this post type
		$collection_id = $collection_ids[0];
		
		// Start a direct sync for just this post
		$result = EPKB_AI_Sync_Job_Manager::initialize_sync_job( array( $post->ID ), 'direct', $collection_id );
		
		if ( is_wp_error( $result ) ) {
			// If sync can't start, mark as outdated for later sync
			$this->mark_post_outdated( $post->ID, $post->post_type );
			EPKB_AI_Log::add_log( $result, array( 'post_id' => $post->ID, 'message' => 'Failed to start auto-sync for post' ) );
			return;
		}
		
		// Process the sync immediately since it's just one post
		EPKB_AI_Sync_Job_Manager::process_next_sync_item();
	}
	
	/**
	 * Queue attachment for sync
	 *
	 * @param int $attachment_id Attachment ID
	 * @return void
	 */
	private function sync_one_attachment( $attachment_id ) {
		
		// Check if sync is already running
		if ( EPKB_AI_Sync_Job_Manager::is_job_active() ) {
			// Don't interfere with running sync, mark attachment as outdated instead
			$this->mark_attachment_outdated( $attachment_id );
			return;
		}
		
		// Get collections that include attachments
		$collection_ids = $this->get_collections_for_post_type( 'attachment' );
		
		if ( empty( $collection_ids ) ) {
			return;
		}
		
		// Use the first collection that contains attachments
		$collection_id = $collection_ids[0];
		
		// Start a direct sync for just this attachment
		$result = EPKB_AI_Sync_Job_Manager::initialize_sync_job( array( $attachment_id ), 'direct', $collection_id );
		
		if ( is_wp_error( $result ) ) {
			// If sync can't start, mark as outdated for later sync
			$this->mark_attachment_outdated( $attachment_id );
			EPKB_AI_Log::add_log( $result, array( 'attachment_id' => $attachment_id, 'message' => 'Failed to start auto-sync for attachment' ) );
			return;
		}
		
		// Process the sync immediately since it's just one attachment
		EPKB_AI_Sync_Job_Manager::process_next_sync_item();
	}
	
	/**
	 * Get collection IDs that include a specific post type
	 *
	 * @param string $post_type Post type
	 * @return array Collection IDs
	 */
	private function get_collections_for_post_type( $post_type ) {
		$collection_ids = array();
		
		// Get all collections
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		if ( is_wp_error( $collections ) || empty( $collections ) ) {
			return $collection_ids;
		}
		
		// Check each collection to see if it includes this post type
		foreach ( $collections as $collection_id => $collection_config ) {
			if ( ! empty( $collection_config['ai_training_data_store_post_types'] ) && 
			     is_array( $collection_config['ai_training_data_store_post_types'] ) &&
			     in_array( $post_type, $collection_config['ai_training_data_store_post_types'], true ) ) {
				$collection_ids[] = $collection_id;
			}
		}
		
		return $collection_ids;
	}

	/**
	 * Check if auto-sync is enabled
	 *
	 * @return bool
	 */
	private function is_auto_sync_enabled() {
		$auto_sync = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_auto_sync_enabled', 'off' );
		return $auto_sync === 'on';
	}

	/**
	 * Check if attachment sync is enabled
	 *
	 * @return bool
	 */
	private function is_attachment_sync_enabled() {
		$attachment_sync = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_sync_attachments', 'off' );
		return $attachment_sync === 'on';
	}
}