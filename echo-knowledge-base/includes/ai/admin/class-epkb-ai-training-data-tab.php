<?php defined( 'ABSPATH' ) || exit();

/**
 * Display AI Training Data tab with React implementation
 *
 * @copyright   Copyright (C) 2025, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_AI_Training_Data_Tab {

	/**
	 * Get the configuration for the Training Data tab
	 * @return array
	 */
	public static function get_tab_config() {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		
		// Get training data collections
		$data_collections_result = self::get_data_collections_with_error_handling();
		$data_collections = $data_collections_result['collections'];
		$collections_error = $data_collections_result['error'];
		
		// Get next available collection ID for new collections
		$next_collection_id = EPKB_AI_Training_Data_Config_Specs::get_next_collection_id();
		if ( is_wp_error( $next_collection_id ) ) {
			// If error, let the UI handle it appropriately
			$next_collection_id = null;
		}
		
		// Build sub_tabs array from collections
		$sub_tabs = array();
		
		// Add each collection as a sub-tab
		foreach ( $data_collections as $collection ) {
			$sub_tabs['collection-' . $collection['id']] = array(
				'id' => 'collection-' . $collection['id'],
				'title' => $collection['name'],
				'icon' => 'epkbfa epkbfa-database',
				'collection_id' => $collection['id']
			);
		}
		
		// Add "Add New" button as the last sub-tab
		$sub_tabs['add-new'] = array(
			'id' => 'add-new',
			'title' => __( 'Add New', 'echo-knowledge-base' ),
			'icon' => 'epkbfa epkbfa-plus',
			'is_add_new' => true
		);
		
		$config = array(
			'tab_id' => 'training-data',
			'title' => __( 'Training Data', 'echo-knowledge-base' ),
			'sub_tabs' => $sub_tabs,
			'ai_config' => $ai_config,
			'data_collections' => $data_collections,
			'next_collection_id' => $next_collection_id,
			'vector_store_files' => self::get_vector_store_files(),
			'available_post_types' => EPKB_AI_Utilities::get_available_post_types_for_ai(),
			'collection_defaults' => EPKB_AI_Training_Data_Config_Specs::get_default_collection_config(),
			'is_wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
		);
		
		// Add error information if collections couldn't be retrieved
		if ( $collections_error ) {
			$config['collections_error'] = __( 'Unable to retrieve training data collections. Please try again later.', 'echo-knowledge-base' );
		}
		
		return $config;
	}

	/**
	 * Get data collections with error handling
	 * @return array
	 */
	private static function get_data_collections_with_error_handling() {
		// Get all training data collections from specs
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		$has_error = false;
		if ( is_wp_error( $collections ) ) {
			// Return empty array if there's an error retrieving collections
			$collections = array();
			$has_error = true;
		}
		
		$formatted_collections = array();
		$training_data_db = new EPKB_AI_Training_Data_DB();
		
		foreach ( $collections as $collection_id => $collection_config ) {

			$db_stats = $training_data_db->get_status_statistics( $collection_id );
			$last_sync_date = $training_data_db->get_last_sync_date( $collection_id );

			$formatted_collections[] = array(
				'id' => $collection_id,
				'name' => empty( $collection_config['ai_training_data_store_name'] ) ? EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id ) : $collection_config['ai_training_data_store_name'],
				'status' => 'active',
				'item_count' => isset( $db_stats['total'] ) ? $db_stats['total'] : 0,
				'last_synced' => $last_sync_date,
				'post_types' => $collection_config['ai_training_data_store_post_types'],
				'config' => $collection_config,
				'stats' => $db_stats // Include full stats for immediate display
			);
		}
		
		return array(
			'collections' => $formatted_collections,
			'error' => $has_error
		);
	}
	
	/**
	 * Get vector store files
	 * @return array
	 */
	private static function get_vector_store_files() {
		// This will be loaded dynamically via REST API
		// Return empty array as placeholder
		return array();
	}
}