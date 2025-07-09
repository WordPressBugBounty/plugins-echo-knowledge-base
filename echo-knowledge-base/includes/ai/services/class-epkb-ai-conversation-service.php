<?php

/**
 * Conversation Service
 * 
 * Handles AI conversations and search interactions.
 * Orchestrates between API, repository, and models.
 */
class EPKB_AI_Conversation_Service {
	
	/**
	 * API client
	 * @var EPKB_OpenAI_Client
	 */
	private $api_client;
	
	/**
	 * Configuration
	 * @var EPKB_AI_Config
	 */
	private $config;
	
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

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = new EPKB_AI_Config();
		$this->api_client = new EPKB_OpenAI_Client( $this->config );
		$this->repository = new EPKB_AI_Messages_DB();
		
		// Get vector store ID for KB
		$this->vector_store_id = $this->get_kb_vector_store_id();
	}
	
	/**
	 * Search knowledge base - Single Q&A
	 *
	 * @param string $question
	 * @param array $options
	 * @return array|WP_Error Array with 'answer', 'conversation_id', 'message_id' or WP_Error on failure
	 */
	public function search( $question, $options = array() ) {
		
		if ( empty( $question ) ) {
			return new WP_Error( 'empty_question', 'Question cannot be empty' );
		}
		
		// Check if AI Search is enabled
		if ( ! EPKB_AI_Utilities::is_ai_search_enabled( EPKB_KB_Config_DB::DEFAULT_KB_ID ) ) {
			return new WP_Error( 'ai_search_disabled', __( 'AI Search feature is not enabled', 'echo-knowledge-base' ) );
		}
		
		// Check rate limit
		$rate_limit_check = $this->check_rate_limit();
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
			'widget_id'       => isset( $options['widget_id'] ) ? $options['widget_id'] : '1',
			'language'        => $this->detect_language(),
			'ip'              => $this->get_client_ip()
		) );
		
		// Make API request
		$response = $this->create_response( $question );
		
		if ( is_wp_error( $response ) ) {
			// Save conversation even on error for debugging
			$this->repository->save_conversation( $conversation );
			
			EPKB_AI_Utilities::add_log( 'AI Search failed', array(
				'user_id' => get_current_user_id(),
				'question_length' => strlen( $question ),
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
				'vector_store_id' => $this->vector_store_id
			) );
			
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
			return new WP_Error( 
				'save_failed',
				'Failed to save conversation: ' . $message_id->get_error_message()
			);
		}
		
		// Record usage for tracking
		$this->record_usage( $response['usage'] );
		
		// Trigger cleanup check
		do_action( 'epkb_ai_search_performed' );
		
		return array(
			'answer'          => $response['content'],
			'conversation_id' => $conversation->get_chat_id(),
			'message_id'      => $message_id,
			'usage'           => $response['usage']
		);
	}
	
	/**
	 * Start a new chat conversation
	 *
	 * @param string $message
	 * @param array $options
	 * @return array|WP_Error Array with 'response', 'conversation_id', 'message_id' or WP_Error on failure
	 */
	public function start_chat( $message, $options = array() ) {
		
		if ( empty( $message ) ) {
			return new WP_Error( 'empty_message', 'Message cannot be empty' );
		}
		
		// Use provided chat_id or generate new one
		$chat_id = isset( $options['chat_id'] ) && EPKB_AI_Utilities::validate_uuid( str_replace( 'chat_', '', $options['chat_id'] ) ) 
			? $options['chat_id'] 
			: 'chat_' . EPKB_AI_Utilities::generate_uuid_v4();
		
		// Check if conversation already exists (deduplication)
		$existing_conversation = $this->repository->get_conversation_by_chat_id( $chat_id );
		if ( $existing_conversation ) {
			// If we already have this chat_id, it's a duplicate request
			// Return the last response from the existing conversation
			$messages = $existing_conversation->get_messages();
			if ( ! empty( $messages ) ) {
				$last_message = end( $messages );
				if ( $last_message['role'] === 'assistant' ) {
					return array(
						'response'        => $last_message['content'],
						'conversation_id' => $existing_conversation->get_chat_id(),
						'message_id'      => isset( $last_message['id'] ) ? $last_message['id'] : '',
						'usage'           => isset( $last_message['metadata']['usage'] ) ? $last_message['metadata']['usage'] : array()
					);
				}
			}
		}
		
		// Create conversation model
		$conversation = new EPKB_AI_Conversation_Model( array(
			'user_id'         => get_current_user_id(),
			'mode'            => 'chat',
			'model'           => $this->config->get_model(),
			'vector_store_id' => $this->vector_store_id,
			'chat_id'         => $chat_id,
			'widget_id'       => isset( $options['widget_id'] ) ? $options['widget_id'] : '1',
			'language'        => $this->detect_language(),
			'ip'              => $this->get_client_ip()
		) );
		
		// Make API request
		$response = $this->create_response( $message );
		
		if ( is_wp_error( $response ) ) {
			// Save conversation even on error for debugging
			$this->repository->save_conversation( $conversation );
			
			EPKB_AI_Utilities::add_log( 'AI Chat start failed', array(
				'user_id' => get_current_user_id(),
				'chat_id' => $conversation->get_chat_id(),
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
				'vector_store_id' => $this->vector_store_id
			) );
			
			return $response;
		}
		
		// Add messages to conversation with message IDs
		$user_message_id = isset( $options['message_id'] ) ? $options['message_id'] : '';
		if ( ! $conversation->add_message( 'user', $message, array(), $user_message_id ) ) {

			// Message is duplicate - return the last assistant response instead of error
			$last_assistant_message = $conversation->get_last_assistant_message();
			if ( $last_assistant_message ) {
				EPKB_AI_Utilities::add_log( 'Duplicate message detected in start_chat, returning existing response', array(
					'chat_id' => $conversation->get_chat_id(),
					'message_id' => $user_message_id
				) );
				
				return array(
					'response'        => $last_assistant_message['content'],
					'conversation_id' => $conversation->get_chat_id(),
					'message_id'      => $conversation->get_id(),
					'usage'           => isset( $last_assistant_message['metadata']['usage'] ) ? $last_assistant_message['metadata']['usage'] : array()
				);
			}
			
			// No assistant message found - this shouldn't happen in normal flow
			return new WP_Error( 'duplicate_message', 'This message has already been sent' );
		}
		
		// Extract assistant message ID from response if available
		$assistant_message_id = '';
		if ( isset( $response['message_id'] ) ) {
			$assistant_message_id = $response['message_id'];
		} elseif ( isset( $response['output'][0]['id'] ) ) {
			$assistant_message_id = $response['output'][0]['id'];
		}
		$conversation->add_message( 'assistant', $response['content'], array( 'usage' => $response['usage'] ), $assistant_message_id );
		
		// Update conversation with response ID
		$conversation->set_conversation_id( $response['response_id'] );
		
		// Save to database
		$message_id = $this->repository->save_conversation( $conversation );
		
		if ( is_wp_error( $message_id ) ) {
			return new WP_Error( 
				'save_failed',
				'Failed to save conversation: ' . $message_id->get_error_message()
			);
		}
		
		// Trigger cleanup check
		do_action( 'epkb_ai_conversation_started' );
		
		return array(
			'response'        => $response['content'],
			'conversation_id' => $conversation->get_chat_id(),
			'message_id'      => $message_id,
			'usage'           => $response['usage']
		);
	}
	
	/**
	 * Continue an existing chat conversation
	 *
	 * @param string $chat_id
	 * @param string $message
	 * @param array $options Optional parameters including message_id
	 * @return array|WP_Error Array with 'response', 'usage' or WP_Error on failure
	 */
	public function continue_chat( $chat_id, $message, $options = array() ) {
		
		if ( empty( $chat_id ) || empty( $message ) ) {
			return new WP_Error( 'missing_params', 'Chat ID and message are required' );
		}
		
		// Check rate limit
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}
		
		// Get conversation from database
		$conversation = $this->repository->get_conversation_by_chat_id( $chat_id );
		
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found' );
		}
		
		
		// Check if response ID is expired
		if ( $conversation->is_conversation_expired() ) {
			return new WP_Error( 'conversation_expired', __( 'This conversation has expired. Please start a new conversation.', 'echo-knowledge-base' ) );
		}
		
		// Make API request with previous response ID
		$response = $this->create_response( $message, $conversation->get_conversation_id() );
		if ( is_wp_error( $response ) ) {
			// Save conversation even on error for debugging
			$this->repository->save_conversation( $conversation );
			
			EPKB_AI_Utilities::add_log( 'AI Chat continuation failed', array(
				'user_id' => get_current_user_id(),
				'chat_id' => $chat_id,
				'conversation_id' => $conversation->get_conversation_id(),
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message()
			) );
			
			return $response;
		}
		
		// Add messages to conversation with message IDs
		$user_message_id = isset( $options['message_id'] ) ? $options['message_id'] : '';
		if ( ! $conversation->add_message( 'user', $message, array(), $user_message_id ) ) {

			// Message is duplicate - return the last assistant response instead of error
			$last_assistant_message = $conversation->get_last_assistant_message();
			if ( $last_assistant_message ) {
				EPKB_AI_Utilities::add_log( 'Duplicate message detected, returning existing response', array(
					'chat_id' => $chat_id,
					'message_id' => $user_message_id
				) );
				
				return array(
					'response'   => $last_assistant_message['content'],
					'usage'      => isset( $last_assistant_message['metadata']['usage'] ) ? $last_assistant_message['metadata']['usage'] : array(),
					'message_id' => isset( $last_assistant_message['id'] ) ? $last_assistant_message['id'] : ''
				);
			}
			
			// No assistant message found - this shouldn't happen in normal flow
			return new WP_Error( 'duplicate_message', 'This message has already been sent' );
		}
		
		// Extract assistant message ID from response if available
		$assistant_message_id = '';
		if ( isset( $response['message_id'] ) ) {
			$assistant_message_id = $response['message_id'];
		} elseif ( isset( $response['output'][0]['id'] ) ) {
			$assistant_message_id = $response['output'][0]['id'];
		}

		$conversation->add_message( 'assistant', $response['content'], array( 'usage' => $response['usage'] ), $assistant_message_id );
		
		// Update conversation with new response ID
		$conversation->set_conversation_id( $response['response_id'] );
		
		// Save to database
		$result = $this->repository->save_conversation( $conversation );
		
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 
				'save_failed',
				'Failed to update conversation: ' . $result->get_error_message()
			);
		}
		
		return array(
			'response'   => $response['content'],
			'usage'      => $response['usage'],
			'message_id' => $assistant_message_id
		);
	}
	
	/**
	 * Get conversation by chat ID
	 *
	 * @param string $chat_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation( $chat_id ) {
		return $this->repository->get_conversation_by_chat_id( $chat_id );
	}
	
	/**
	 * Get conversations list
	 *
	 * @param array $args
	 * @return array
	 */
	public function get_conversations( $args = array() ) {
		// Widget ID can be passed in args to filter conversations
		
		return $this->repository->get_conversations( $args );
	}
	
	/**
	 * Delete conversation
	 *
	 * @param string $chat_id
	 * @return bool
	 */
	public function delete_conversation( $chat_id ) {
		$conversation = $this->repository->get_conversation_by_chat_id( $chat_id );
		
		if ( ! $conversation ) {
			return false;
		}
		
		// Soft delete
		return $this->repository->delete_conversation( $conversation->get_id() );
	}

	/**
	 * Create response using OpenAI API
	 *
	 * @param string $message
	 * @param string $previous_response_id
	 * @return array|WP_Error Array with 'content', 'response_id', 'usage' or WP_Error on failure
	 */
	private function create_response( $message, $previous_response_id = null ) {
		
		// Build request
		$request = array(
			'model'             => $this->config->get_model(),
			'instructions'      => $this->get_instructions(),
			'input'             => $message,
			'max_output_tokens' => $this->config->get_max_tokens()
		);
		
		// Add previous response ID for continuing conversation
		if ( ! empty( $previous_response_id ) ) {
			$request['previous_response_id'] = $previous_response_id;
		}
		
		// Add tools if vector store is available
		/* TODO if ( ! empty( $this->vector_store_id ) ) {
			$request['tools'] = array(
				array(
					'type' => 'file_search',
					'file_search' => array(
						'vector_store_ids' => array( $this->vector_store_id )
					)
				)
			);
		} */
		
		// Make API request
		$response = $this->api_client->request( '/responses', $request );
		
		if ( is_wp_error( $response ) ) {
			EPKB_AI_Utilities::add_log( 'AI API request failed', array(
				'model' => $this->config->get_model(),
				'has_previous_response' => ! empty( $previous_response_id ),
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message()
			) );
			return $response;
		}
		
		// Extract response data
		$content = $this->extract_response_content( $response );
		$response_id = isset( $response['id'] ) ? $response['id'] : '';
		$usage = isset( $response['usage'] ) ? $response['usage'] : array();
		
		// Extract message ID from OpenAI response
		$message_id = '';
		if ( isset( $response['output'][0]['id'] ) ) {
			$message_id = $response['output'][0]['id'];
		}
		
		return array(
			'content'     => $content,
			'response_id' => $response_id,
			'message_id'  => $message_id,
			'usage'       => $usage
		);
	}
	
	/**
	 * Extract content from API response
	 *
	 * @param array $response
	 * @return string
	 */
	private function extract_response_content( $response ) {
		// Responses API structure
		if ( isset( $response['output'][0]['content'][0]['text'] ) ) {
			return $response['output'][0]['content'][0]['text'];
		}
		
		// Fallback for different response structures
		if ( isset( $response['choices'][0]['message']['content'] ) ) {
			return $response['choices'][0]['message']['content'];
		}
		
		return '';
	}
	
	/**
	 * Get KB vector store ID
	 *
	 * @return string
	 */
	private function get_kb_vector_store_id() {
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( EPKB_KB_Config_DB::DEFAULT_KB_ID );
		return ''; // TODO isset( $kb_config['ai_vector_store_id'] ) ? $kb_config['ai_vector_store_id'] : '';
	}
	
	/**
	 * Get AI instructions
	 *
	 * @return string
	 */
	private function get_instructions() {
		$instructions = __( 'You are a helpful knowledge base assistant. Answer questions based on the provided documentation. Be concise and accurate. If you cannot find the answer in the documentation, say so.', 'echo-knowledge-base' );
		
		return apply_filters( 'epkb_ai_instructions', $instructions );
	}
	
	/**
	 * Detect current language
	 *
	 * @return string
	 */
	private function detect_language() {
		if ( class_exists( 'EPKB_Language_Utilities' ) && method_exists( 'EPKB_Language_Utilities', 'detect_current_language' ) ) {
			$language_data = EPKB_Language_Utilities::detect_current_language();
			return isset( $language_data['locale'] ) ? $language_data['locale'] : get_locale();
		}
		
		return get_locale();
	}
	
	/**
	 * Get client IP address (hashed for privacy)
	 *
	 * @return string Hashed IP address or empty string
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		$raw_ip = '';
		
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[$key] ) ) {
				$ip = sanitize_text_field( $_SERVER[$key] );
				
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$raw_ip = $ip;
					break;
				}
			}
		}
		
		// If no valid public IP found, check for any valid IP (including private)
		if ( empty( $raw_ip ) ) {
			foreach ( $ip_keys as $key ) {
				if ( ! empty( $_SERVER[$key] ) ) {
					$ip = sanitize_text_field( $_SERVER[$key] );
					
					// Handle comma-separated IPs (from proxies)
					if ( strpos( $ip, ',' ) !== false ) {
						$ips = explode( ',', $ip );
						$ip = trim( $ips[0] );
					}
					
					// Validate any IP address
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						$raw_ip = $ip;
						break;
					}
				}
			}
		}
		
		// Hash the IP address for privacy (GDPR compliance)
		if ( ! empty( $raw_ip ) ) {
			// Use a consistent salt for the same IP to produce the same hash
			// This allows rate limiting while preserving privacy
			return wp_hash( $raw_ip . AUTH_SALT );
		}
		
		return '';
	}
	
	/**
	 * Check rate limit for current user/IP
	 *
	 * @return bool|WP_Error True if within limit, WP_Error if exceeded
	 */
	private function check_rate_limit() {
		// Simple global rate limiting
		$transient_key = 'epkb_ai_rate_limit_global';
		$rate_limit = apply_filters( 'epkb_ai_rate_limit', 50 ); // 50 requests per hour globally
		
		// Get current request count
		$request_count = get_transient( $transient_key );
		if ( $request_count === false ) {
			$request_count = 0;	// start at zero
		}
		
		// Check if limit exceeded
		if ( $request_count >= $rate_limit ) {
			EPKB_AI_Utilities::add_log( 'AI rate limit exceeded', array(
				'request_count' => $request_count,
				'rate_limit' => $rate_limit,
				'user_id' => get_current_user_id()
			) );
			
			$rate_limit_message = apply_filters( 'epkb_ai_rate_limit_message', 
				__( 'System rate limit exceeded. Please try again later.', 'echo-knowledge-base' ) );
			
			return new WP_Error( 'rate_limit_exceeded', $rate_limit_message );
		}
		
		// Increment count and save for 1 hour
		set_transient( $transient_key, $request_count + 1, HOUR_IN_SECONDS );
		
		return true;
	}
	
	/**
	 * Record API usage for tracking
	 *
	 * @param array $usage Usage data from API response
	 * @return void
	 */
	private function record_usage( $usage ) {
		if ( empty( $usage ) ) {
			return;
		}
		
		// Get current month's usage
		$month_key = 'epkb_ai_usage_' . gmdate( 'Y_m' );
		$monthly_usage = get_option( $month_key, array(
			'prompt_tokens' => 0,
			'completion_tokens' => 0,
			'total_tokens' => 0,
			'requests' => 0
		) );
		
		// Update usage
		if ( isset( $usage['prompt_tokens'] ) ) {
			$monthly_usage['prompt_tokens'] += intval( $usage['prompt_tokens'] );
		}
		if ( isset( $usage['completion_tokens'] ) ) {
			$monthly_usage['completion_tokens'] += intval( $usage['completion_tokens'] );
		}
		if ( isset( $usage['total_tokens'] ) ) {
			$monthly_usage['total_tokens'] += intval( $usage['total_tokens'] );
		}
		$monthly_usage['requests']++;
		
		// Save updated usage
		update_option( $month_key, $monthly_usage, false );
	}
}