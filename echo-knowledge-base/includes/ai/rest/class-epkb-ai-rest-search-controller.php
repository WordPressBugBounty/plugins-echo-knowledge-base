<?php defined( 'ABSPATH' ) || exit();

/**
 * REST API Controller for AI Search functionality
 * 
 * Provides secure REST endpoints for search operations
 */
class EPKB_AI_REST_Search_Controller extends EPKB_AI_REST_Base_Controller {
	
	/**
	 * Route base
	 */
	protected $rest_base = 'ai-search';

	/**
	 * Register the routes for AI Search
	 */
	public function register_routes() {

		if ( ! EPKB_AI_Utilities::is_ai_search_enabled() ) {
			return;
		}

		// Search endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'search_permissions_check' ),
				'args'                => $this->get_search_params(),
			),
		) );

		// Admin endpoints for search conversation management
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations_table' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => $this->get_collection_params(),
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
	 * Permission check for search
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function search_permissions_check( $request ) {
		
		// Check if AI is enabled
		$ai_check = $this->check_ai_enabled( $request );
		if ( is_wp_error( $ai_check ) ) {
			return $ai_check;
		}

		// Check if AI Search is enabled
		if ( ! EPKB_AI_Utilities::is_ai_search_enabled() ) {
			return new WP_Error(
				'ai_search_disabled',
				__( 'AI Search is currently disabled.', 'echo-knowledge-base' ),
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

		// Get KB config
		$kb_config = $this->get_kb_config();
		if ( is_wp_error( $kb_config ) ) {
			return $kb_config;
		}

		// Check if guest access is allowed for search
		if ( ! is_user_logged_in() && empty( $kb_config['ai_search_guest_access'] ) ) {
			return new WP_Error(
				'login_required',
				__( 'You must be logged in to use the search feature.', 'echo-knowledge-base' ),
				array( 'status' => 401 )
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
	 * Handle search request
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function search( $request ) {
		
		// Get search query
		$query = $request->get_param( 'query' );
		
		// Validate query
		if ( empty( $query ) || strlen( $query ) < 3 ) {
			return new WP_Error(
				'invalid_query',
				__( 'Search query must be at least 3 characters long.', 'echo-knowledge-base' ),
				array( 'status' => 400 )
			);
		}

		// Get optional parameters
		$kb_id = absint( $request->get_param( 'kb_id' ) ?: EPKB_KB_Config_DB::DEFAULT_KB_ID );
		$limit = absint( $request->get_param( 'limit' ) ?: 5 );
		
		// Validate limit
		if ( $limit < 1 || $limit > 20 ) {
			$limit = 5;
		}

		// Initialize conversation service for search
		$conversation_service = new EPKB_AI_Conversation_Service();
		
		// Perform search
		$result = $conversation_service->search( $query, array(
			'kb_id' => $kb_id,
			'limit' => $limit
		) );

		// Handle errors
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, array(
				'query' => $query,
				'kb_id' => $kb_id
			) );
		}

		// Format response
		$response_data = array(
			'query'   => $query,
			'results' => isset( $result['results'] ) ? $result['results'] : array(),
			'count'   => isset( $result['count'] ) ? $result['count'] : 0,
			'kb_id'   => $kb_id
		);

		// Add AI response if available
		if ( isset( $result['ai_response'] ) ) {
			$response_data['ai_response'] = EPKB_AI_Chat_Security::sanitize_output( $result['ai_response'] );
		}

		// Add conversation ID if this search was logged
		if ( isset( $result['conversation_id'] ) ) {
			$response_data['conversation_id'] = $result['conversation_id'];
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get conversations table data for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_conversations_table( $request ) {
		
		// Get parameters
		$params = array(
			'per_page' => $request->get_param( 'per_page' ),
			'page'     => $request->get_param( 'page' ),
			's'        => $request->get_param( 'search' ),
			'orderby'  => $request->get_param( 'orderby' ),
			'order'    => strtoupper( $request->get_param( 'order' ) )
		);

		// Get data using existing table operations
		$result = EPKB_AI_Table_Operations::get_table_data( 'search', $params );

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

		// Check if this is actually a search conversation
		$meta = $conversation->get_meta();
		if ( empty( $meta['type'] ) || $meta['type'] !== 'search' ) {
			return new WP_Error(
				'invalid_type',
				__( 'This is not a search conversation.', 'echo-knowledge-base' ),
				array( 'status' => 400 )
			);
		}

		// Get messages
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
			'query'      => isset( $messages[0] ) ? $messages[0]['content'] : '',
			'results'    => isset( $meta['results'] ) ? $meta['results'] : array(),
			'meta'       => array(
				'page_title' => isset( $meta['page_title'] ) ? $meta['page_title'] : '',
				'kb_id'      => isset( $meta['kb_id'] ) ? $meta['kb_id'] : EPKB_KB_Config_DB::DEFAULT_KB_ID
			)
		);

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

		$result = EPKB_AI_Table_Operations::delete_row( 'search', $conversation_id );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => __( 'Search conversation deleted successfully.', 'echo-knowledge-base' ),
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

		$result = EPKB_AI_Table_Operations::delete_selected_rows( 'search', $ids );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => sprintf( __( '%d search conversations deleted successfully.', 'echo-knowledge-base' ), $result ),
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
		
		$result = EPKB_AI_Table_Operations::delete_all_conversations( 'search' );
		
		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result );
		}

		return rest_ensure_response( array(
			'message' => sprintf( __( '%d search conversations deleted successfully.', 'echo-knowledge-base' ), $result ),
			'deleted' => $result
		) );
	}

	/**
	 * Get schema for search parameters
	 * 
	 * @return array
	 */
	protected function get_search_params() {
		return array(
			'query' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'The search query', 'echo-knowledge-base' ),
				'validate_callback' => function( $param ) {
					return is_string( $param ) && strlen( trim( $param ) ) >= 3;
				},
				'sanitize_callback' => 'sanitize_text_field',
			),
			'kb_id' => array(
				'type'              => 'integer',
				'description'       => __( 'Knowledge Base ID', 'echo-knowledge-base' ),
				'default'           => EPKB_KB_Config_DB::DEFAULT_KB_ID,
				'sanitize_callback' => 'absint',
			),
			'limit' => array(
				'type'              => 'integer',
				'description'       => __( 'Maximum number of results', 'echo-knowledge-base' ),
				'default'           => 5,
				'minimum'           => 1,
				'maximum'           => 20,
				'sanitize_callback' => 'absint',
			),
		);
	}
}