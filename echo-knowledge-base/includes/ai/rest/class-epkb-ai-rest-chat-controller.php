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

		// Chat message endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/message', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => array( $this, 'send_message_permissions_check' ),
				'args'                => $this->get_message_params(),
			),
		) );

		// Get conversation endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/conversation/(?P<chat_id>[a-zA-Z0-9_-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation' ),
				'permission_callback' => array( $this, 'get_conversation_permissions_check' ),
				'args'                => array(
					'chat_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_chat_id' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		) );

		// Admin endpoints for conversation management
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations_table' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => $this->get_table_params(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_all_conversations' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
			),
		) );

		// Single conversation admin operations
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation_details' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
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
				'permission_callback' => array( $this, 'admin_permissions_check' ),
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

		// Bulk delete conversations
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations/bulk', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_selected_conversations' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
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
	 * Permission check for sending messages
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function send_message_permissions_check( $request ) {
		
		// Check if AI Chat is enabled
		if ( ! EPKB_AI_Utilities::is_ai_chat_enabled() ) {
			return new WP_Error(
				'ai_chat_disabled',
				__( 'AI Chat is currently disabled.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		// Validate nonce
		$nonce_check = $this->validate_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Check rate limiting
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		return true;
	}

	/**
	 * Permission check for getting conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function get_conversation_permissions_check( $request ) {
		
		// Validate nonce
		$nonce_check = $this->validate_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Get session ID from cookie
		$session_id = isset( $_COOKIE['epkb_session_id'] ) ? sanitize_text_field( $_COOKIE['epkb_session_id'] ) : '';
		if ( empty( $session_id ) ) {
			return new WP_Error(
				'no_session',
				__( 'No session found.', 'echo-knowledge-base' ),
				array( 'status' => 401 )
			);
		}

		// Validate session format
		if ( ! EPKB_AI_Chat_Security::validate_session_id_format( $session_id ) ) {
			return new WP_Error(
				'invalid_session',
				__( 'Invalid session format.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		// Check session access
		if ( ! EPKB_AI_Chat_Security::can_access_session( $session_id, get_current_user_id() ) ) {
			return new WP_Error(
				'access_denied',
				__( 'Access denied to this conversation.', 'echo-knowledge-base' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check for admin operations
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check( $request ) {
		return $this->check_admin_permission( 'admin_eckb_access_ai_features_write' );
	}

	/**
	 * Handle sending a chat message
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_message( $request ) {
		
		// Get and validate message
		$message = $request->get_param( 'message' );
		$message = EPKB_AI_Chat_Security::validate_message( $message );
		if ( is_wp_error( $message ) ) {
			return new WP_Error(
				'invalid_message',
				$message->get_error_message(),
				array( 'status' => 400 )
			);
		}

		// Get optional parameters
		$message_id = sanitize_text_field( $request->get_param( 'message_id' ) ?: '' );
		$widget_id = absint( $request->get_param( 'widget_id' ) ?: 1 );
		$chat_id = sanitize_text_field( $request->get_param( 'chat_id' ) ?: '' );

		// Validate widget ID range
		if ( $widget_id < 1 || $widget_id > EPKB_AI_Config::MAX_WIDGET_ID ) {
			$widget_id = 1;
		}

		// Get or create session ID
		$session_id = isset( $_COOKIE['epkb_session_id'] ) ? sanitize_text_field( $_COOKIE['epkb_session_id'] ) : '';
		if ( empty( $session_id ) ) {
			$session_id = EPKB_AI_Chat_Security::generate_session_id();
			$this->set_session_cookie( $session_id );
		} else if ( ! EPKB_AI_Chat_Security::validate_session_id_format( $session_id ) ) {
			return new WP_Error(
				'invalid_session',
				__( 'Invalid session format.', 'echo-knowledge-base' ),
				array( 'status' => 400 )
			);
		}

		// Store session owner
		EPKB_AI_Chat_Security::store_session_owner( $session_id, get_current_user_id() );

		// Optimize message
		$message = $this->optimize_message( $message );

		// Initialize conversation service
		$conversation_service = new EPKB_AI_Conversation_Service();

		// Prepare options
		$options = array(
			'message_id' => $message_id,
			'widget_id'  => $widget_id,
			'chat_id'    => $chat_id
		);

		// Handle conversation
		if ( $conversation_service->get_conversation( $chat_id ) ) {
			$result = $conversation_service->continue_chat( $chat_id, $message, $options );
		} else {
			$result = $conversation_service->start_chat( $message, $options );
		}

		// Handle errors
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, array(
				'session_id' => $session_id,
				'chat_id' => $chat_id,
				'is_new_chat' => empty( $chat_id )
			) );
		}

		// Sanitize response
		$response = EPKB_AI_Chat_Security::sanitize_output( $result['response'] );

		// Get chat_id from result
		$response_chat_id = isset( $result['conversation_id'] ) ? $result['conversation_id'] : $chat_id;

		// Build response
		$response_data = array(
			'response'   => $response,
			'session_id' => $session_id,
			'chat_id'    => $response_chat_id,
			'message_id' => isset( $result['message_id'] ) ? $result['message_id'] : ''
		);

		// Set session cookie in response headers if new
		if ( empty( $_COOKIE['epkb_session_id'] ) ) {
			$response = new WP_REST_Response( $response_data );
			$response->header( 'X-Session-Id', $session_id );
			return $response;
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get conversation history
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_conversation( $request ) {
		
		$chat_id = $request->get_param( 'chat_id' );
		
		// Get session ID from cookie
		$session_id = isset( $_COOKIE['epkb_session_id'] ) ? sanitize_text_field( $_COOKIE['epkb_session_id'] ) : '';
		if ( empty( $session_id ) ) {
			return new WP_Error(
				'no_session',
				__( 'No session found.', 'echo-knowledge-base' ),
				array( 'status' => 401 )
			);
		}

		// Get conversation from database
		$conversation_service = new EPKB_AI_Conversation_Service();
		$conversation = $conversation_service->get_conversation( $chat_id );

		if ( ! $conversation ) {
			return new WP_Error(
				'not_found',
				__( 'Conversation not found.', 'echo-knowledge-base' ),
				array( 'status' => 404 )
			);
		}

		// Check if conversation is expired
		if ( $conversation->is_conversation_expired() ) {
			return new WP_Error(
				'expired',
				__( 'Conversation has expired.', 'echo-knowledge-base' ),
				array( 'status' => 410 )
			);
		}

		// Get messages
		$messages = $conversation->get_messages();
		$formatted_messages = array();

		foreach ( $messages as $message ) {
			$formatted_messages[] = array(
				'role'      => $message['role'],
				'content'   => EPKB_AI_Chat_Security::sanitize_output( $message['content'] ),
				'timestamp' => isset( $message['timestamp'] ) ? $message['timestamp'] : '',
				'id'        => isset( $message['id'] ) ? $message['id'] : ''
			);
		}

		return rest_ensure_response( array(
			'messages'   => $formatted_messages,
			'session_id' => $session_id,
			'chat_id'    => $chat_id
		) );
	}

	/**
	 * Get conversations table data for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
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
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get single conversation details for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_conversation_details( $request ) {
		
		$conversation_id = $request->get_param( 'id' );

		// Get conversation from database
		$messages_db = new EPKB_AI_Messages_DB();
		$conversation = $messages_db->get_conversation( $conversation_id );
		
		if ( ! $conversation ) {
			return new WP_Error(
				'not_found',
				__( 'Conversation not found.', 'echo-knowledge-base' ),
				array( 'status' => 404 )
			);
		}

		// Get messages and metadata
		$messages = $conversation->get_messages();
		$meta = $conversation->get_meta();

		// Build user display name
		$user_display = __( 'Guest', 'echo-knowledge-base' );
		if ( $conversation->get_user_id() > 0 ) {
			$user = get_user_by( 'id', $conversation->get_user_id() );
			if ( $user ) {
				$user_display = $user->display_name;
			}
		} else if ( ! empty( $meta['user_name'] ) ) {
			$user_display = $meta['user_name'];
		}

		// Format response
		$response = array(
			'id'         => $conversation->get_id(),
			'user'       => $user_display,
			'created'    => $conversation->get_created(),
			'messages'   => array(),
			'meta'       => array(
				'page_title' => isset( $meta['page_title'] ) ? $meta['page_title'] : '',
				'status'     => isset( $meta['status'] ) ? $meta['status'] : ''
			)
		);

		// Format messages
		foreach ( $messages as $message ) {
			$response['messages'][] = array(
				'role'      => $message['role'],
				'content'   => $message['content'],
				'timestamp' => isset( $message['timestamp'] ) ? $message['timestamp'] : ''
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a single conversation
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_conversation( $request ) {
		
		$conversation_id = $request->get_param( 'id' );

		$result = EPKB_AI_Table_Operations::delete_row( 'chat', $conversation_id );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => __( 'Conversation deleted successfully.', 'echo-knowledge-base' ),
			'deleted' => true
		) );
	}

	/**
	 * Delete selected conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_selected_conversations( $request ) {
		
		$ids = $request->get_param( 'ids' );

		$result = EPKB_AI_Table_Operations::delete_selected_rows( 'chat', $ids );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => sprintf( __( '%d conversations deleted successfully.', 'echo-knowledge-base' ), $result ),
			'deleted' => $result
		) );
	}

	/**
	 * Delete all conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_all_conversations( $request ) {
		
		$result = EPKB_AI_Table_Operations::delete_all_conversations( 'chat' );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => sprintf( __( '%d conversations deleted successfully.', 'echo-knowledge-base' ), $result ),
			'deleted' => $result
		) );
	}

	/**
	 * Get schema for message parameters
	 * 
	 * @return array
	 */
	protected function get_message_params() {
		return array(
			'message' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'The chat message to send', 'echo-knowledge-base' ),
				'validate_callback' => function( $param ) {
					return is_string( $param ) && ! empty( trim( $param ) );
				},
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'message_id' => array(
				'type'              => 'string',
				'description'       => __( 'Optional message ID', 'echo-knowledge-base' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'widget_id' => array(
				'type'              => 'integer',
				'description'       => __( 'Widget ID (1-10)', 'echo-knowledge-base' ),
				'default'           => 1,
				'minimum'           => 1,
				'maximum'           => EPKB_AI_Config::MAX_WIDGET_ID,
				'sanitize_callback' => 'absint',
			),
			'chat_id' => array(
				'type'              => 'string',
				'description'       => __( 'Chat conversation ID', 'echo-knowledge-base' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
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
	 * Validate chat ID parameter
	 * 
	 * @param string $param
	 * @param WP_REST_Request $request
	 * @param string $key
	 * @return bool
	 */
	public function validate_chat_id( $param, $request, $key ) {
		// Allow alphanumeric, hyphens and underscores
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $param );
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