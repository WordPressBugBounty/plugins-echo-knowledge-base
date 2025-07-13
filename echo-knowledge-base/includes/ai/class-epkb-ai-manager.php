<?php

/**
 * AI Manager
 * 
 * High-level interface for AI functionality.
 * Provides simplified methods for common operations.
 */
class EPKB_AI_Manager {

	/**
	 * Search handler
	 * @var EPKB_AI_Search_Handler
	 */
	private $search_handler;
	
	/**
	 * Vector store service
	 * @var EPKB_AI_Vector_Store_Service
	 */
	private $vector_store_service;

	public function __construct() {
		//$this->search_handler = new EPKB_AI_Search_Handler();
		//$this->vector_store_service = new EPKB_AI_Vector_Store_Service();
	}
	
	/**
	 * Search knowledge base
	 *
	 * @param string $question
	 * @param int $widget_id
	 * @return array|WP_Error
	 */
	public function search( $question, $widget_id = 1 ) {
		return $this->search_handler->search( $question, $widget_id );
	}

	/**
	 * Create a new vector store
	 *
	 * @return array|WP_Error
	 */
	public function create_vector_store() {

		$vector_store_name = sprintf( __( 'KB %d Posts', 'echo-knowledge-base' ), EPKB_KB_Config_DB::KB_CONFIG_PREFIX );
		$metadata = array(
			'kb_id' => EPKB_KB_Config_DB::KB_CONFIG_PREFIX,
			'type'  => 'knowledge_base'
		);
		
		$vector_store = $this->vector_store_service->create_vector_store( $vector_store_name, $metadata );
		if ( is_wp_error( $vector_store ) ) {
			EPKB_AI_Utilities::add_log( 'Unable to create a new vector store', $vector_store->get_error_message() );
			return $vector_store;
		}

		// Save Vector Store ID
		$result = EPKB_AI_Config_Specs::update_ai_config_value( 'ai_vector_store_id', $vector_store['id'] );
		if ( is_wp_error( $result ) ) {
			EPKB_AI_Utilities::add_log( 'Error occurred on saving configuration. (107)' );
			return $result;
		}

		return array(
			'status' => 'success',
			'vector_store_id' => $vector_store['id'],
			'message' => __( 'Vector store initialized', 'echo-knowledge-base' )
		);
	}

	/**
	 * Recreate vector store - check if exists, create if not
	 *
	 * @return array|WP_Error
	 */
	public function recreate_vector_store() {

		$vector_store_id = '';

		$vector_store = $this->vector_store_service->get_vector_store( $vector_store_id );

		// Serious error
		if ( is_wp_error( $vector_store ) ) {
			EPKB_AI_Utilities::add_log( 'Error occurred on fetching Vector Store', $vector_store->get_error_message() );
			return $vector_store;
		}

		// Vector store doesn't exist, create a new one
		if ( empty( $vector_store ) ) {
			EPKB_AI_Utilities::add_log( 'Error occurred on fetching Vector Store but we will create a new one' );
			return $this->create_vector_store();
		}

		return array(
			'status' => 'success',
			'vector_store_id' => $vector_store_id,
			'message' => __( 'Vector Store initialized', 'echo-knowledge-base' )
		);
	}

	/**
	 * Upload posts to vector store
	 *
	 * @return array|WP_Error
	 */
	public function upload_posts() {

		$post_ids = $this->get_kb_posts_for_vector_store();

		if ( empty( $post_ids ) ) {
			EPKB_AI_Utilities::add_log( 'No Knowledge base posts found' );
			return new WP_Error( 'no_posts', __( 'No Knowledge base posts found', 'echo-knowledge-base' ) );
		}

		$vector_store_id = '';
		if ( empty( $vector_store_id ) ) {
			EPKB_AI_Utilities::add_log( 'Empty vector store id' );
			return new WP_Error( 'no_vector_store', __( 'Create vector store before uploading content.', 'echo-knowledge-base' ) );
		}

		$result = $this->vector_store_service->add_posts_to_vector_store( $vector_store_id, $post_ids );

		if ( is_wp_error( $result ) ) {
			EPKB_AI_Utilities::add_log( 'Unable to upload posts to vector store' );
			return $result;
		}

		return array(
			'status' => 'success',
			'message' => __( 'Posts successfully uploaded', 'echo-knowledge-base' )
		);
	}

	/**
	 * Re-upload posts to vector store (delete existing files first)
	 *
	 * @return array|WP_Error
	 */
	public function reupload_posts() {

		$post_ids = $this->get_kb_posts_for_vector_store();

		if ( empty( $post_ids ) ) {
			EPKB_AI_Utilities::add_log( 'No Knowledge base posts found' );
			return new WP_Error( 'no_posts', __( 'No Knowledge base posts found', 'echo-knowledge-base' ) );
		}

		$vector_store_id = '';
		if ( empty( $vector_store_id ) ) {
			EPKB_AI_Utilities::add_log( 'Empty vector store id' );
			return new WP_Error( 'no_vector_store', __( 'Create vector store before uploading content.', 'echo-knowledge-base' ) );
		}

		// Delete existing files
		$remove_existing_posts = $this->vector_store_service->clear_vector_store_files( $vector_store_id );
		if ( is_wp_error( $remove_existing_posts ) ) {
			EPKB_AI_Utilities::add_log( 'Unable to remove existing files from vector store', $remove_existing_posts );
			return $remove_existing_posts;
		}

		// Upload new files
		$result = $this->vector_store_service->add_posts_to_vector_store( $vector_store_id, $post_ids );
		if ( is_wp_error( $result ) ) {
			EPKB_AI_Utilities::add_log( 'Unable to upload posts to vector store' );
			return $result;
		}

		return array(
			'status' => 'success',
			'message' => __( 'Posts successfully uploaded', 'echo-knowledge-base' )
		);
	}

	/**
	 * Save AI settings
	 *
	 * @param array $settings
	 * @return array|WP_Error
	 */
	public function save_ai_settings( $settings ) {

		$ai_config = EPKB_AI_Config_Specs::get_ai_config();
		$new_ai_config = array();

		// Check if disclaimer needs to be accepted
		if ( $ai_config['ai_disclaimer_accepted'] == 'off' && isset( $settings['ai_disclaimer_accepted'] ) ) {
			$disclaimer_accepted = $settings['ai_disclaimer_accepted'];
			/* TODO	if ( ! in_array( 'disclaimer_read', $disclaimer_accepted ) ||
			     ! in_array( 'confidentiality_notice', $disclaimer_accepted ) || 
			     ! in_array( 'no_guarantee_of_accuracy', $disclaimer_accepted ) || 
			     ! in_array( 'disclaimer_risk_limitation', $disclaimer_accepted ) ) {
				return new WP_Error( 'disclaimer_not_accepted', __( 'Please accept all disclaimers before continuing.', 'echo-knowledge-base' ) );
			} */
			$new_ai_config['ai_disclaimer_accepted'] = 'on';
		}

		// Update settings
		/* if ( isset( $settings['model'] ) ) {
			$new_ai_config['ai_api_model'] = $settings['model'];
		} */

		// Handle API key update
		if ( ! empty( $settings['openai_key'] ) && strpos( $settings['openai_key'], '...' ) === false ) {
			$result = EPKB_AI_Config::save_api_key( $settings['openai_key'] );
			if ( is_wp_error( $result ) ) {
				EPKB_AI_Utilities::add_log( 'Error occurred on saving API key: ' . $result->get_error_message() );
				return $result;
			}
		}

		// Handle AI feature toggles
		if ( isset( $settings['ai_search_enabled'] ) ) {
			$new_ai_config['ai_search_enabled'] = $settings['ai_search_enabled'];
		}
		if ( isset( $settings['ai_chat_enabled'] ) ) {
			$new_ai_config['ai_chat_enabled'] = $settings['ai_chat_enabled'];
		}
		
		// Handle beta access code
		if ( isset( $settings['ai_beta_access_code'] ) ) {
			$new_ai_config['ai_beta_access_code'] = $settings['ai_beta_access_code'];
		}

		// Save configuration
		$result = EPKB_AI_Config_Specs::update_ai_config( $new_ai_config );
		if ( is_wp_error( $result ) ) {
			EPKB_AI_Utilities::add_log( 'Error occurred on saving configuration.' );
			return $result;
		}

		return array(
			'status' => 'success',
			'message' => __( 'Configuration Saved', 'echo-knowledge-base' )
		);
	}

	/**
	 * Reset logs
	 *
	 * @return bool
	 */
	public function reset_logs() {
		EPKB_AI_Utilities::reset_logs();
		return true;
	}

	/**
	 * Get KB posts, filtering by default language if WPML or Polylang is active
	 *
	 * @return array Post IDs
	 */
	private function get_kb_posts_for_vector_store() {

		// Check if multilingual plugin is active and get default language
		$is_multilingual = EPKB_Language_Utilities::is_multilingual_active();
		$default_language = null;
		
		if ( $is_multilingual ) {
			$default_lang_data = EPKB_Language_Utilities::get_site_default_language();
			if ( $default_lang_data ) {
				$default_language = $default_lang_data['code'];
			}
		}
		
		// Build query args
		$args = array(
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'post_type'      => EPKB_KB_Handler::KB_POST_TYPE_PREFIX . EPKB_KB_Config_DB::KB_CONFIG_PREFIX,
			'post_status'    => 'publish',
		);
		
		// Add language filter if multilingual is active
		if ( $is_multilingual && $default_language ) {
			$args = EPKB_Language_Utilities::add_language_filter_to_query( $args, $default_language );
		}
		
		$post_ids = get_posts( $args );
		
		// Additional filtering if needed
		if ( $is_multilingual && $default_language ) {
			$post_ids = EPKB_Language_Utilities::filter_posts_by_language( $post_ids, $default_language );
		}

		return $post_ids;
	}
}