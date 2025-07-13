<?php
/**
 * AI Message Handler
 * 
 * Handles the two-phase message processing:
 * 1. Call AI API first
 * 2. Save both user and assistant messages together after successful AI response
 * 
 * Includes idempotency, error handling, and recovery mechanisms
 */
class EPKB_AI_Message_Handler extends EPKB_AI_Base_Handler {
	
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Process a chat message with two-phase approach
	 * 
	 * @param string $user_message User's message
	 * @param string $chat_id Existing chat ID or empty for new chat
	 * @param string $session_id Current session ID
	 * @param string $idempotency_key Unique request identifier
	 * @param int $widget_id Widget identifier
	 * @return array|WP_Error Result array or error
	 */
	public function process_message( $user_message, $chat_id, $session_id, $idempotency_key, $widget_id = 1 ) {
		
		// Check rate limit before processing
		$rate_limit_check = EPKB_AI_Security::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		$conversation = new EPKB_AI_Conversation_Model( [ 'chat_id' => $chat_id, 'session_id' => $session_id, 'idempotency_key' => $idempotency_key, 'widget_id' => $widget_id ]	 );

		// Step 1: Determine if this is a new or existing conversation
		$conversation_context = $this->resolve_conversation_context( $conversation );
		if ( is_wp_error( $conversation_context ) ) {
			return $conversation_context;
		}
		$conversation = $conversation_context['conversation_obj'];	

		// Step 2: Check for idempotent request (duplicate requests) and return last assistant response
		$idempotent_check = $this->check_idempotency( $conversation->get_chat_id(),	$idempotency_key );
		if ( $idempotent_check !== false ) {
			return $idempotent_check;
		}
		
		// Step 3: Check for stale messages and handle them
		if ( $conversation_context['has_stale_message'] ) {
			$stale_result = $this->get_response_for_stale_message( $conversation );		
			if ( is_wp_error( $stale_result ) ) {
				return $stale_result;
			}

			// Update conversation context after handling stale message
			$conversation_context['conversation_obj'] = $stale_result['conversation_obj'];
		}
		
		// Step 4: Call AI API FIRST (before any database writes)
		$ai_response = $this->get_ai_response( $user_message, $conversation->get_conversation_id() );
		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}
		
		// Step 6: Save both messages together after successful AI response
		$save_result = $this->save_messages( $conversation_context, $user_message, $ai_response, $idempotency_key, $session_id, $widget_id );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}
		
		// Step 7: Return successful response (API expects 'response' field, not 'assistant_message')
		return array(
			'success' => true,
			'response' => $ai_response['content'],
			'message_id' => 'msg_assistant_' . time() . '_' . uniqid(),
			'chat_id' => $conversation->get_chat_id(),
			'is_duplicate' => false
		);
	}

	/**
	 * Resolve conversation context - determine if new or existing
	 *
	 * @param $conversation
	 * @return array|WP_Error Context array with conversation details
	 */
	private function resolve_conversation_context( $conversation ) {

		$chat_id = $conversation->get_chat_id();
		$session_id = $conversation->get_session_id();

		// Check if conversation was cleared - force new if flag is set
		$clear_flag_key = 'epkb_ai_clear_conv_' . md5( $session_id );
		$pre_generated_chat_id = get_transient( $clear_flag_key );
		if ( $pre_generated_chat_id ) {
			// Clear the flag
			delete_transient( $clear_flag_key );
			
			// Force new conversation with pre-generated chat_id
			$conversation->set_chat_id( $pre_generated_chat_id );
			return array(
				'is_new' => true,
				'has_stale_message' => false,
				'conversation_obj' => $conversation
			);
		}
		
		// If no chat_id, check for active conversation in session
		if ( empty( $chat_id ) ) {
			$active_conversation = $this->messages_db->get_latest_active_conversation_for_session( $session_id );
			if ( $active_conversation ) {
				// Validate user matching for the active conversation
				$user_validation = EPKB_AI_Utilities::validate_ai_user_matching( $active_conversation->get_chat_id() );
				if ( is_wp_error( $user_validation ) ) {
					// User mismatch - treat as no active conversation and create new
					$conversation->set_chat_id( EPKB_AI_Security::generate_chat_id() );
					return array(
						'is_new' => true,
						'has_stale_message' => false,
						'conversation_obj' => $conversation
					);
				}
				
				return array(
					'is_new' => false,
					'conversation_obj' => $active_conversation,
					'has_stale_message' => $this->has_stale_user_message( $active_conversation )
				);
			}
			
			// No active conversation - create new
			$conversation->set_chat_id( EPKB_AI_Security::generate_chat_id() );
			return array(
				'is_new' => true,
				'has_stale_message' => false,
				'conversation_obj' => $conversation
			);
		}
		
		// Validate provided chat_id belongs to session
		if ( ! EPKB_AI_Security::validate_chat_session( $chat_id, $session_id ) ) {
			EPKB_AI_Security::log_security_event( 'invalid_chat_session', array( 'chat_id' => $chat_id, 'session_id' => $session_id ) ); //TODO
			return new WP_Error( 'invalid_chat_id', __( 'Invalid conversation ID for this session', 'echo-knowledge-base' ) );
			// create new conversation
			/* $conversation->set_chat_id( EPKB_AI_Security::generate_chat_id() );
			return array(
				'is_new' => true,
				'has_stale_message' => false,
				'conversation_obj' => $conversation
			); */
		}
		
		// Additional validation to ensure user state matches
		$user_validation = EPKB_AI_Utilities::validate_ai_user_matching( $chat_id );
		if ( is_wp_error( $user_validation ) ) {
			EPKB_AI_Security::log_security_event( 'user_mismatch', array( 'chat_id' => $chat_id, 'error' => $user_validation->get_error_code() ) );
			return $user_validation;
		}
		
		// Get existing conversation
		$conversation = $this->messages_db->get_conversation_by_chat_and_session( $chat_id, $session_id );
		if ( ! $conversation ) {
			return new WP_Error( 'conversation_not_found', __( 'Conversation not found', 'echo-knowledge-base' ) );
		}
		
		// Check if conversation is expired
		if ( $conversation->is_conversation_expired() ) {
			// Return error to let client know conversation expired
			return new WP_Error( 'conversation_expired', __( 'This conversation has expired. Please start a new conversation.', 'echo-knowledge-base' ) );
		}
		
		return array(
			'is_new' => false,
			'conversation_obj' => $conversation,
			'has_stale_message' => $this->has_stale_user_message( $conversation )
		);
	}
	
	/**
	 * Check if conversation has a stale user message i.e. last message is from user
	 * 
	 * @param EPKB_AI_Conversation_Model $conversation
	 * @return bool
	 */
	private function has_stale_user_message( $conversation ) {

		$messages = $conversation->get_messages();
		if ( empty( $messages ) ) {
			return false;
		}
		
		$last_message = end( $messages );
		if ( $last_message['role'] !== 'user' ) {
			return false;
		}
		
		// Check if message is older than 5 minutes
		$message_time = isset( $last_message['timestamp'] ) 
			? strtotime( $last_message['timestamp'] ) 
			: strtotime( $conversation->get_updated() );
			
		return ( time() - $message_time ) > 300; // 5 minutes
	}
	
	/**
	 * Handle stale user message by getting AI response
	 * 
	 * @param EPKB_AI_Conversation_Model $conversation
	 * @return array|WP_Error Updated conversation or error
	 */
	private function get_response_for_stale_message( EPKB_AI_Conversation_Model $conversation ) {

		$messages = $conversation->get_messages();
		
		// Call AI for the stale message
		$ai_response = $this->get_ai_response( $messages, $conversation->get_conversation_id() );
		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}
		
		// Add assistant response to messages
		$messages[] = array(
			'role' => 'assistant',
			'content' => $ai_response['content'],
			'timestamp' => current_time( 'mysql' )
		);
		
		// Update conversation
		$conversation->messages = $messages;
		$conversation->set_conversation_id( $ai_response['response_id'] );
		
		// Save updated conversation
		$result = $this->messages_db->save_conversation( $conversation );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return array( 'conversation_obj' => $conversation );
	}
	
	/**
	 * Check for idempotent request
	 * 
	 * @param string $chat_id
	 * @param string $idempotency_key
	 * @return array|false Returns last assistant message if duplicate, false otherwise
	 */
	private function check_idempotency( $chat_id, $idempotency_key ) {

		$existing = $this->messages_db->check_idempotent_request( $chat_id, $idempotency_key );
		if ( ! $existing ) {
			return false;
		}
		
		// Parse messages and get last assistant message
		if ( empty( $existing->messages ) ) {
			return false;
		}
		
		/** @disregard P1006 */
		$messages = json_decode( $existing->messages, true );
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return false;
		}
		
		$last_message = end( $messages );
		if ( $last_message['role'] !== 'assistant' ) {
			// Last message should be assistant for idempotent request
			return false;
		}
		
		return array(
			'success' => true,
			'response' => $last_message['content'],
			'message_id' => isset($last_message['id']) ? $last_message['id'] : 'msg_assistant_' . time(),
			'chat_id' => $chat_id,
			'is_duplicate' => true
		);
	}

	/**
	 * Save messages to database
	 * 
	 * @param array $conversation_context
	 * @param string $user_message
	 * @param array $ai_response
	 * @param string $idempotency_key
	 * @param string $session_id
	 * @param int $widget_id
	 * @return true|WP_Error
	 */
	private function save_messages( $conversation_context, $user_message, $ai_response, $idempotency_key, $session_id, $widget_id ) {
		
		// Prepare messages array
		$messages = array();
		/** @var EPKB_AI_Conversation_Model $conversation */
		$conversation = isset( $conversation_context['conversation_obj'] ) ? $conversation_context['conversation_obj'] : new EPKB_AI_Conversation_Model();

		// Add existing messages if updating
		if ( ! $conversation_context['is_new'] && $conversation ) {
			$messages = $conversation->get_messages();
		}
		
		// Add user message
		$timestamp = current_time( 'mysql' );
		$messages[] = array(
			'role' => 'user',
			'content' => $user_message,
			'timestamp' => $timestamp
		);

		// Add AI response message
		$messages[] = array(
			'role' => 'assistant',
			'content' => $ai_response['content'],
			'timestamp' => $timestamp,
			//'usage' => $ai_response['usage']
		);
		
		// Prepare data for save
		$language = EPKB_Language_Utilities::detect_current_language();
		$data = array(
			'session_id' => $session_id,
			'chat_id' => $conversation->get_chat_id(),
			'messages' => $messages,
			'conversation_id' => $ai_response['response_id'],
			'widget_id' => $widget_id,
			'mode' => 'chat',
			'model' => $this->config->get_model(),
			'user_id' => get_current_user_id(),
			'ip' => $this->get_hashed_ip(),
			'language' => $language['locale']
		);
		
		// Only set title for new conversations - preserve existing title for updates
		if ( $conversation_context['is_new'] ) {
			$data['title'] = $this->generate_title( $user_message );
		}
		
		// a) Save new conversation
		if ( $conversation_context['is_new'] ) {
			$result = $this->messages_db->insert_conversation_with_messages( $data, $idempotency_key );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return true;
		}

		// b) Update existing conversation with version check
		$conversation->messages = $messages;
		$conversation->set_conversation_id( $ai_response['response_id'] );
		$current_version = $conversation->get_row_version();

		$result = $this->messages_db->update_conversation_with_version_check( $conversation, $idempotency_key, $current_version );
		if ( is_wp_error( $result ) && $result->get_error_code() === 'version_conflict' ) {

			// Reload and retry once
			$fresh_conversation = $this->messages_db->get_conversation_by_chat_id( $conversation->get_chat_id() );
			if ( $fresh_conversation ) {
				// Check if our message was already added
				$array = $fresh_conversation->get_messages();
				$last_message = end( $array );
				if ( $last_message && $last_message['content'] === $ai_response['content'] ) {
					return true; // Already saved by another request
				}

				// Try again with fresh version
				$result = $this->messages_db->update_conversation_with_version_check( $conversation, $idempotency_key, $fresh_conversation->get_row_version() );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		
		return true;
	}
}