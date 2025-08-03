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

	public function __construct() {
		parent::__construct();

		$this->repository = new EPKB_AI_Messages_DB();
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
		if ( ! EPKB_AI_Utilities::is_ai_search_enabled() ) {
			return new WP_Error( 'ai_search_disabled', __( 'AI Search feature is not enabled', 'echo-knowledge-base' ) );
		}
		
		// Check rate limit
		$rate_limit_check = EPKB_AI_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}
		
		// Get or create session
		$session_id = EPKB_AI_Security::get_or_create_session();
		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}
		
		// Make API request
		$model = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_model' );
		$ai_response = $this->get_ai_response( $question, $model );
		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}
		
		// Get language info
		$language = EPKB_Language_Utilities::detect_current_language();
		
		// Add messages to conversation
		$conversation = new EPKB_AI_Conversation_Model( array(
			'user_id'         => get_current_user_id(),
			'mode'            => 'search',
			'chat_id'         => 'search_' . EPKB_AI_Utilities::generate_uuid_v4(),
			'session_id'      => $session_id,
			'widget_id'       => $widget_id,
			'language'        => $language['locale'],
			'ip'              => EPKB_AI_Utilities::get_hashed_ip()
		) );

		$conversation->add_message( 'user', $question );
		$conversation->add_message( 'assistant', $ai_response['content'], array( 'usage' => $ai_response['usage'] ) );
		
		// Update conversation with response ID
		$conversation->set_conversation_id( $ai_response['response_id'] );
		
		// Save to database
		$message_id = $this->repository->save_conversation( $conversation );
		if ( is_wp_error( $message_id ) ) {
			return new WP_Error( 'save_failed', 'Failed to save conversation: ' . $message_id->get_error_message()
			);
		}
		
		// Record usage for tracking
		// TODO $this->record_usage( $response['usage'] );

		// Return format matching what JavaScript expects (same as AI Chat)
		return array(
			'success'     => true,
			'response'    => $ai_response['content'],  // Using 'response' to match AI Chat
			'query'       => $question,
			'conversation_id' => $conversation->get_chat_id(),
			'results'     => array(), // AI search doesn't return traditional results
			'count'       => 0        // AI search doesn't return count
		);
	}
}