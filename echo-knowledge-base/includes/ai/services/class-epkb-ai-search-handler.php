<?php

/**
 * Search Handler
 * 
 * Handles AI search interactions (single Q&A).
 * Orchestrates between API, repository, and models.
 */
class EPKB_AI_Search_Handler extends EPKB_AI_Base_Handler {

	/**
	 * Message repository
	 * @var EPKB_AI_Messages_DB
	 */
	private $repository;

	/**
	 * Vector store ID
	 * @var string
	 */
	private $vector_store_id;

	public function __construct() {
		parent::__construct();

		$this->repository = new EPKB_AI_Messages_DB();
		$this->vector_store_id = $this->get_kb_vector_store_id();
	}
	
	/**
	 * Search knowledge base - Single Q&A
	 *
	 * @param string $question
	 * @param int $widget_id
	 * @return array|WP_Error Array with 'answer', 'conversation_id', 'message_id' or WP_Error on failure
	 */
	public function search( $question, $widget_id ) {
		
		if ( empty( $question ) ) {
			return new WP_Error( 'empty_question', 'Question cannot be empty' );
		}
		
		// Check if AI Search is enabled
		if ( ! EPKB_AI_Utilities::is_ai_search_enabled( EPKB_KB_Config_DB::DEFAULT_KB_ID ) ) {
			return new WP_Error( 'ai_search_disabled', __( 'AI Search feature is not enabled', 'echo-knowledge-base' ) );
		}
		
		// Check rate limit
		$rate_limit_check = EPKB_AI_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}
		
		// Create conversation model with proper chat_id
		$conversation = new EPKB_AI_Conversation_Model( array(
			'user_id'         => get_current_user_id(),
			'mode'            => 'search',
			'model'           => $this->config->get_model(),
			'vector_store_id' => $this->vector_store_id,
			'chat_id'         => 'search_' . EPKB_AI_Utilities::generate_uuid_v4(),
			'widget_id'       => $widget_id,
			'language'        => EPKB_Language_Utilities::detect_current_language(),
			'ip'              => $this->get_hashed_ip()
		) );
		
		// Make API request
		$response = $this->get_ai_response( $question );
		
		if ( is_wp_error( $response ) ) {
			// Save conversation even on error for debugging
			$this->repository->save_conversation( $conversation );
			return $response;
		}
		
		// Add messages to conversation
		$conversation->add_message( 'user', $question );
		$conversation->add_message( 'assistant', $response['content'], array( 'usage' => $response['usage'] ) );
		
		// Update conversation with response ID
		$conversation->set_conversation_id( $response['response_id'] );
		
		// Save to database
		$message_id = $this->repository->save_conversation( $conversation );
		if ( is_wp_error( $message_id ) ) {
			return new WP_Error( 'save_failed', 'Failed to save conversation: ' . $message_id->get_error_message()
			);
		}
		
		// Record usage for tracking
		// TODO $this->record_usage( $response['usage'] );

		return array(
			'answer'          => $response['content'],
			'conversation_id' => $conversation->get_chat_id(),
			'message_id'      => $message_id,
			'usage'           => $response['usage']
		);
	}

	/**
	 * Get KB vector store ID
	 *
	 * @return string
	 */
	private function get_kb_vector_store_id() {
		return 1; // TODO
	}
}