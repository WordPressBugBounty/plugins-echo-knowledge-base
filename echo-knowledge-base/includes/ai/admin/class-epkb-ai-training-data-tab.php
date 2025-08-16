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
		
		// Sync collections from database first to ensure we have all collections
		EPKB_AI_Training_Data_Config_Specs::sync_collections_from_database();
		
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
		
		 /** @disregard P1011 */
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
            'is_wp_cron_disabled' => ( defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' ) ),
			'is_ai_features_pro_enabled' => EPKB_Utilities::is_ai_features_pro_enabled()
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

			// Get available post types with their content status
			$available_post_types = EPKB_AI_Utilities::get_available_post_types_for_ai();
			$post_types_with_status = array();
			
			foreach ( $available_post_types as $post_type => $label ) {
				// Check if post type exists
				if ( ! post_type_exists( $post_type ) ) {
					$post_types_with_status[$post_type] = array(
						'label' => $label,
						'available' => false,
						'count' => 0,
						'already_added' => 0,
						'new_items' => 0
					);
					continue;
				}
				
				// Count published posts
				$count_query = new WP_Query( array(
					'post_type' => $post_type,
					'post_status' => 'publish',
					'posts_per_page' => 1,
					'fields' => 'ids'
				) );
				$total_count = $count_query->found_posts;
				
				// Count already added to this collection
				$already_added = $training_data_db->get_training_data_count( array(
					'collection_id' => $collection_id,
					'type' => $post_type
				) );
				
				$post_types_with_status[$post_type] = array(
					'label' => $label,
					'available' => $total_count > 0,
					'count' => $total_count,
					'already_added' => $already_added,
					'new_items' => max( 0, $total_count - $already_added )
				);
			}
			
			// Add the post types with status to the collection config
			$collection_config['ai_training_data_store_post_types_options'] = $post_types_with_status;

			// Pre-load first page of training data for this collection
			$preloaded_data = self::get_preloaded_training_data( $collection_id );

			$formatted_collections[] = array(
				'id' => $collection_id,
				'name' => empty( $collection_config['ai_training_data_store_name'] ) ? EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id ) : $collection_config['ai_training_data_store_name'],
				'status' => 'active',
				'item_count' => isset( $db_stats['total'] ) ? $db_stats['total'] : 0,
				'last_synced' => $last_sync_date,
				'post_types' => $collection_config['ai_training_data_store_post_types'],
				'config' => $collection_config,
				'stats' => $db_stats, // Include full stats for immediate display
				'preloaded_data' => $preloaded_data // Include pre-loaded first page data
			);
		}
		
		return array(
			'collections' => $formatted_collections,
			'error' => $has_error
		);
	}
	
	/**
	 * Get pre-loaded training data for initial page load
	 * @param int $collection_id
	 * @return array
	 */
	private static function get_preloaded_training_data( $collection_id ) {
		
		$training_data_db = new EPKB_AI_Training_Data_DB();
		$preloaded = array();
		
		// Status tabs to pre-load (all, pending, added, updated, outdated, error)
		$statuses = array( 'all', 'pending', 'added', 'updated', 'outdated', 'error' );
		
		foreach ( $statuses as $status ) {
			// Pre-load first page with default settings for each status
			$args = array(
				'collection_id' => $collection_id,
				'page' => 1,
				'per_page' => 20, // Match the React component's itemsPerPage
				'orderby' => 'updated',
				'order' => 'DESC'
			);
			
			// Add status filter if not 'all'
			if ( $status !== 'all' ) {
				$args['status'] = $status;
			}
			
			// Get training data from database
			$data = $training_data_db->get_training_data_list( $args );
			if ( is_wp_error( $data ) ) {
				$preloaded[$status] = array(
					'data' => array(),
					'pagination' => array(
						'page' => 1,
						'per_page' => 20,
						'total' => 0,
						'total_pages' => 1
					)
				);
				continue;
			}
			
			// Add post type names to each item (same logic as REST endpoint)
			foreach ( $data as &$item ) {
				// Get the post type object to get the label
				$post_type_obj = get_post_type_object( $item->type );
				if ( $post_type_obj && isset( $post_type_obj->labels->name ) ) {
					
					if ( strpos( $item->type, 'epkb_post_type' ) === 0 && isset( $post_type_obj->labels->name ) ) {
						$type_name = $post_type_obj->labels->name;
					} else {
						// For standard post types, use singular_name
						$type_name = $post_type_obj->labels->singular_name;
					}
				} else {
					// Fallback to the type if post type object not found
					$type_name = ucfirst( $item->type );
				}
				
				// Limit to 20 characters with ellipsis if longer
				if ( strlen( $type_name ) > 20 ) {
					$item->type_name = substr( $type_name, 0, 18 ) . '..';
				} else {
					$item->type_name = $type_name;
				}
				// Keep the original type as item_type for filtering
				$item->item_type = $item->type;
			}
			
			// Get total count for pagination
			$total = $training_data_db->get_training_data_count( $args );
			$total_pages = ceil( $total / 20 );
			
			$preloaded[$status] = array(
				'data' => $data,
				'pagination' => array(
					'page' => 1,
					'per_page' => 20,
					'total' => $total,
					'total_pages' => $total_pages
				)
			);
		}
		
		return $preloaded;
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