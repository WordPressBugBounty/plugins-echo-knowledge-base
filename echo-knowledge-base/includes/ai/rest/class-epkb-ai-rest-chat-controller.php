<?php defined( 'ABSPATH' ) || exit();

/**
 * REST API Controller for AI Chat functionality
 * 
 * Provides secure REST endpoints for chat operations following WordPress best practices
 */
class EPKB_AI_REST_Chat_Controller extends EPKB_AI_REST_Base_Controller {
	
	/**
	 * Route base
	 */
	protected $rest_base = 'ai-chat';

	/**
	 * Register the routes for the AI Chat
	 */
	public function register_routes() {

		if ( ! EPKB_AI_Utilities::is_ai_chat_enabled() ) {
			return;
		}

		// Start session endpoint - creates httpOnly session cookie
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/start-session', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start_session' ),
				'permission_callback' => '__return_true' // Public endpoint for guest users
			),
		) );

		// Chat message endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/message', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => [ $this, 'check_rest_nonce' ]
			),
		) );
		
		// Get active conversation endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/active', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_active_conversation' ),
				'permission_callback' => [ $this, 'check_rest_nonce' ]
			),
		) );
		
		// Clear conversation endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/clear', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_conversation' ),
				'permission_callback' => [ $this, 'check_rest_nonce' ]
			),
		) );

		// TODO Get conversation endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/conversation/(?P<chat_id>[a-zA-Z0-9_-]+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_conversation' ),
				'permission_callback' => [ $this, 'check_rest_nonce' ]
			),
		) );

		// TODO Admin endpoints for conversation management
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations_table' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
				'args'                => $this->get_table_params(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_all_conversations' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
			),
		) );

		// TODO Single conversation admin operations
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation_details' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_conversation' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// TODO Bulk delete conversations
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations/bulk', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_selected_conversations' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
				'args'                => array(
					'ids' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
						'sanitize_callback' => function( $param ) {
							return array_map( 'absint', $param );
						},
					),
				),
			),
		) );
	}

	/**
	 * Start a new session (creates httpOnly session cookie)
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function start_session( $request ) {
		try {
			// Get or create session (this sets the httpOnly cookie)
			$session_id = EPKB_AI_Security::get_or_create_session();
			if ( is_wp_error( $session_id ) ) {
				return $this->create_rest_response( array(), 500, $session_id );
			}
			
			// Generate fresh nonce for the session
			$security = new EPKB_AI_Security();
			$rest_nonce = $security->get_nonce( true );
			
			return $this->create_rest_response( array(
				'success' => true,
				'rest_nonce' => $rest_nonce
			) );
			
		} catch ( Exception $e ) {
			return $this->create_rest_response( array( 'error' => 'session_error', 'message' => $e->getMessage() ), 500 );
		}
	}

	// access by WP_REST_Server
	public function check_rest_nonce( $request ) {

		if ( ! EPKB_AI_Utilities::is_ai_chat_enabled() ) {
			return false;
		}

		return EPKB_AI_Security::check_rest_nonce( $request );
	}

	/**
	 * Handle sending a chat message with two-phase approach
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function send_message( $request ) {

		try {
			// Extract and validate request data
			$request_data = $this->extract_request_data( $request );
			if ( is_wp_error( $request_data ) ) {
				return $this->create_rest_response( array(), 400, $request_data );
			}
			
			// Get or create session
			$session_id = EPKB_AI_Security::get_or_create_session();
			if ( is_wp_error( $session_id ) ) {
				return $this->create_rest_response( array(), 400, $session_id );
			}
			
			// Process the message using the handler
			$this->message_handler = new EPKB_AI_Message_Handler();
			$result = $this->message_handler->process_message(
				$request_data['message'],
				$request_data['chat_id'],
				$session_id,
				$request_data['idempotency_key'],
				$request_data['widget_id']
			);
			
			if ( is_wp_error( $result ) ) {
				return $this->create_rest_response( array(), 400, $result );
			}
			
			// Return successful response
			return $this->create_rest_response( $result );
			
		} catch ( Exception $e ) {  // Catch any unexpected exceptions during frontend request processing
			EPKB_AI_Utilities::add_log( 'Unexpected error in send_message', array( 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ) );
			return $this->create_rest_response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Get active conversation for current session
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_active_conversation( $request ) {
		
		// Get session ID
		$session_id = EPKB_AI_Security::get_or_create_session();
		if ( empty( $session_id ) ) {
			return $this->create_rest_response( array( 'success' => true, 'has_active_conversation' => false ) );
		}
		
		// Get active conversation
		$messages_db = new EPKB_AI_Messages_DB();
		$conversation = $messages_db->get_latest_active_conversation_for_session( $session_id );
		if ( ! $conversation ) {
			return $this->create_rest_response( array( 'success' => true, 'has_active_conversation' => false ) );
		}
		
		// Validate user matching for the conversation
		$validation = EPKB_AI_Utilities::validate_ai_user_matching( $conversation->get_chat_id() );
		if ( is_wp_error( $validation ) ) {
			return $this->create_rest_response( array(), 403, $validation );
		}
		
		// Format messages for response
		$messages = $this->format_messages_for_response( $conversation->get_messages() );
		
		return $this->create_rest_response( array( 'success'   => true, 'has_active_conversation' => true, 'chat_id'   => $conversation->get_chat_id(), 'messages'  => $messages ) );
	}
	
	/**
	 * Clear current conversation
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function clear_conversation( $request ) {
		
		// Get session ID
		$session_id = EPKB_AI_Security::get_or_create_session();
		if ( empty( $session_id ) ) {
			return $this->create_rest_response( array( 'success' => false ), 400, new WP_Error( 'no_session', __( 'No active session.', 'echo-knowledge-base' ) ) );
		}
		
		// Generate new chat ID for the same session
		$new_chat_id = EPKB_AI_Security::generate_chat_id();
		
		// Set a transient flag to force new conversation on next message. This flag will be checked when processing the next message
		$transient_key = 'epkb_ai_clear_conv_' . md5( $session_id );
		set_transient( $transient_key, $new_chat_id, 15 * MINUTE_IN_SECONDS );
		
		return $this->create_rest_response( array(
			'success' => true,
			'chat_id' => $new_chat_id,
			'message' => __( 'New conversation started.', 'echo-knowledge-base' )
		) );
	}
	
	/**
	 * Get conversation history by chat ID
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_conversation( $request ) {
		
		$chat_id = $request->get_param( 'chat_id' );
		$session_id = EPKB_AI_Security::get_or_create_session();
		
		if ( empty( $session_id ) ) {
			return $this->create_rest_response( array(), 401, new WP_Error( 'no_session', __( 'No active session found.', 'echo-knowledge-base' ) )			);
		}
		
		// Validate chat belongs to session
		if ( ! EPKB_AI_Security::validate_chat_session( $chat_id, $session_id ) ) {
			return $this->create_rest_response( array(), 403, new WP_Error( 'unauthorized', __( 'Unauthorized access to conversation.', 'echo-knowledge-base' ) ) );
		}
		
		// Additional user matching validation
		$user_validation = EPKB_AI_Utilities::validate_ai_user_matching( $chat_id );
		if ( is_wp_error( $user_validation ) ) {
			return $this->create_rest_response( array(), 403, $user_validation );
		}
		
		// Get conversation from database
		$messages_db = new EPKB_AI_Messages_DB();
		$conversation = $messages_db->get_conversation_by_chat_and_session( $chat_id, $session_id );
		if ( ! $conversation ) {
			return $this->create_rest_response( array(), 404, new WP_Error( 'not_found', __( 'Conversation not found.', 'echo-knowledge-base' ) ) );
		}

		// Check if conversation is expired
		if ( $conversation->is_conversation_expired() ) {
			return $this->create_rest_response( array(), 410, new WP_Error( 'conversation_expired', __( 'Conversation has expired.', 'echo-knowledge-base' ) ) );
		}

		// Format messages for response
		$messages = $this->format_messages_for_response( $conversation->get_messages() );

		return $this->create_rest_response( array( 'success' => true, 'messages' => $messages, 'chat_id' => $chat_id ) );
	}

	/**
	 * Get conversations table data for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_conversations_table( $request ) {
		
		// Get parameters
		$per_page = absint( $request->get_param( 'per_page' ) ?: 10 );
		$page = absint( $request->get_param( 'page' ) ?: 1 );
		$search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$orderby = sanitize_text_field( $request->get_param( 'orderby' ) ?: 'created' );
		$order = strtoupper( sanitize_text_field( $request->get_param( 'order' ) ?: 'DESC' ) );

		// Validate order
		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
			$order = 'DESC';
		}

		// Get data using existing table operations
		$result = EPKB_AI_Table_Operations::get_table_data( 'chat', array(
			'per_page' => $per_page,
			'page'     => $page,
			's'        => $search,
			'orderby'  => $orderby,
			'order'    => $order
		) );

		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( $result );
	}

	/**
	 * Get single conversation details for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_conversation_details( $request ) {
		
		$conversation_id = $request->get_param( 'id' );

		// Get conversation from database
		$messages_db = new EPKB_AI_Messages_DB();
		$conversation = $messages_db->get_conversation( $conversation_id );
		
		if ( ! $conversation ) {
			return $this->create_rest_response( array( 'error' => 'not_found', 'message' => __( 'Conversation not found.', 'echo-knowledge-base' ) ), 404 );
		}

		// Get messages and metadata
		$messages = $conversation->get_messages();

		// Build user display name
		$user_display = __( 'Guest', 'echo-knowledge-base' );
		if ( $conversation->get_user_id() > 0 ) {
			$user = get_user_by( 'id', $conversation->get_user_id() );
			if ( $user ) {
				$user_display = $user->display_name;
			}
		} 

		// Format response
		$response = array(
			'id'         => $conversation->get_id(),
			'user'       => $user_display,
			'created'    => $conversation->get_created(),
			'messages'   => array()
		);

		// Format messages
		foreach ( $messages as $message ) {
			$response['messages'][] = array(
				'role'      => $message['role'],
				'content'   => $message['content'],
				'timestamp' => isset( $message['timestamp'] ) ? $message['timestamp'] : ''
			);
		}

		return $this->create_rest_response( $response );
	}

	/**
	 * Delete a single conversation
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_conversation( $request ) {
		
		$conversation_id = $request->get_param( 'id' );

		$result = EPKB_AI_Table_Operations::delete_row( 'chat', $conversation_id );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => __( 'Conversation deleted successfully.', 'echo-knowledge-base' ), 'deleted' => true ) );
	}

	/**
	 * Delete selected conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_selected_conversations( $request ) {
		
		$ids = $request->get_param( 'ids' );

		$result = EPKB_AI_Table_Operations::delete_selected_rows( 'chat', $ids );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => sprintf( __( '%d conversations deleted successfully.', 'echo-knowledge-base' ), $result ), 'deleted' => $result ) );
	}

	/**
	 * Delete all conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_all_conversations( $request ) {
		
		$result = EPKB_AI_Table_Operations::delete_all_conversations( 'chat' );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => sprintf( __( '%d conversations deleted successfully.', 'echo-knowledge-base' ), $result ), 'deleted' => $result ) );
	}

	/**
	 * Extract and validate request data
	 * 
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	private function extract_request_data( $request ) {
		$params = $request->get_json_params();
		
		// Get and validate message
		$message = isset( $params['message'] ) ? $params['message'] : '';
		$message = $this->optimize_message( $message );
		$message = EPKB_AI_Validation::validate_message( $message );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		
		// Get optional parameters; keep empty if not provided (handled in Message_Handler)
		$chat_id = isset( $params['chat_id'] ) ? sanitize_text_field( $params['chat_id'] ) : '';
		if ( !empty( $chat_id ) && ! EPKB_AI_Validation::validate_uuid( str_replace( EPKB_AI_Security::CHAT_ID_PREFIX, '', $chat_id ) ) ) {
			$chat_id = EPKB_AI_Security::generate_chat_id();
		}

		// Get idempotency key
		$idempotency_key = isset( $params['idempotency_key'] ) ? sanitize_text_field( $params['idempotency_key'] ) : '';
		$idempotency_key = EPKB_AI_Validation::validate_idempotency_key( $idempotency_key );
		if ( is_wp_error( $idempotency_key ) ) {
			$idempotency_key = '';
		}

		// Get widget ID
		$widget_id = isset( $params['widget_id'] ) ? absint( $params['widget_id'] ) : 1;
		$widget_id = EPKB_AI_Validation::validate_widget_id( $widget_id );
		if ( is_wp_error( $widget_id ) ) {
			$widget_id = 1;
		}
		
		return array(
			'message'         => $message,
			'chat_id'         => $chat_id,
			'idempotency_key' => $idempotency_key,
			'widget_id'       => $widget_id,
			'user_id'         => get_current_user_id()
		);
	}
	
	/**
	 * Format messages for API response
	 * 
	 * @param array $messages
	 * @return array
	 */
	private function format_messages_for_response( $messages ) {
		$formatted = array();
		
		foreach ( $messages as $message ) {
			$formatted[] = array(
				'role'      => $message['role'],
				'content'   => EPKB_AI_Security::sanitize_output( $message['content'] ),
				'timestamp' => isset( $message['timestamp'] ) ? $message['timestamp'] : ''
			);
		}
		
		return $formatted;
	}

	/**
	 * Get schema for table parameters
	 * 
	 * @return array
	 */
	protected function get_table_params() {
		return array(
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'page' => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'search' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby' => array(
				'type'              => 'string',
				'default'           => 'created',
				'enum'              => array( 'id', 'created', 'user', 'messages' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order' => array(
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC' ),
				'sanitize_callback' => function( $param ) {
					return strtoupper( sanitize_text_field( $param ) );
				},
			),
		);
	}

	/**
	 * Optimize message for transmission (same as AJAX handler)
	 *
	 * @param string $message
	 * @return string
	 */
	private function optimize_message( $message ) {
		// Remove excessive whitespace
		$message = preg_replace( '/\s+/', ' ', $message );

		// Trim message
		$message = trim( $message );

		// Remove zero-width characters
		$message = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $message );

		return $message;
	}
}