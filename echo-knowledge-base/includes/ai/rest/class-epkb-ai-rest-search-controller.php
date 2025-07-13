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

		// TODO Search endpoint
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/search', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => [ $this, 'check_rest_nonce' ],
				'args'                => $this->get_search_params(),
			),
		) );

		// TODO Admin endpoints for search conversation management
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/admin/conversations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations_table' ),
				'permission_callback' => array( 'EPKB_AI_Security', 'can_access_settings' ),
				'args'                => $this->get_collection_params(),
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

	private function check_rest_nonce( $request ) {

		if ( ! EPKB_AI_Utilities::is_ai_search_enabled() ) {
			return false;
		}

		return EPKB_AI_Security::check_rest_nonce( $request );
	}

	/**
	 * Handle search request
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function search( $request ) {
		
		// Get search query
		$query = $request->get_param( 'query' );
		
		// Validate query
		if ( empty( $query ) || strlen( $query ) < 3 ) {
			return $this->create_rest_response( array( 'error' => 'invalid_query', 'message' => __( 'Search query must be at least 3 characters long.', 'echo-knowledge-base' ) ), 400 );
		}

		// Get optional parameters
		$kb_id = absint( $request->get_param( 'kb_id' ) ?: EPKB_KB_Config_DB::DEFAULT_KB_ID );
		$limit = absint( $request->get_param( 'limit' ) ?: 5 );
		
		// Validate limit
		if ( $limit < 1 || $limit > 20 ) {
			$limit = 5;
		}

		// Initialize search handler for search
		$search_handler = new EPKB_AI_Search_Handler();
		
		// Perform search
		$result = $search_handler->search( $query, array(
			'kb_id' => $kb_id,
			'limit' => $limit
		) );

		// Handle errors
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array( 'query' => $query, 'kb_id' => $kb_id ), 500, $result );
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
			$response_data['ai_response'] = EPKB_AI_Security::sanitize_output( $result['ai_response'] );
		}

		// Add conversation ID if this search was logged
		if ( isset( $result['conversation_id'] ) ) {
			$response_data['conversation_id'] = $result['conversation_id'];
		}

		return $this->create_rest_response( $response_data );
	}

	/**
	 * Get conversations table data for admin
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
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

		// Check if this is actually a search conversation
		if ( ! $conversation->is_search() ) {
			return $this->create_rest_response( array( 'error' => 'invalid_type', 'message' => __( 'This is not a search conversation.', 'echo-knowledge-base' ) ), 400 );
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
			'answer'     => isset( $messages[1] ) ? $messages[1]['content'] : '',
			'widget_id'  => $conversation->get_widget_id()
		);

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

		$result = EPKB_AI_Table_Operations::delete_row( 'search', $conversation_id );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => __( 'Search conversation deleted successfully.', 'echo-knowledge-base' ), 'deleted' => true ) );
	}

	/**
	 * Delete selected conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_selected_conversations( $request ) {
		
		$ids = $request->get_param( 'ids' );

		$result = EPKB_AI_Table_Operations::delete_selected_rows( 'search', $ids );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => sprintf( __( '%d search conversations deleted successfully.', 'echo-knowledge-base' ), $result ), 'deleted' => $result ) );
	}

	/**
	 * Delete all conversations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_all_conversations( $request ) {
		
		$result = EPKB_AI_Table_Operations::delete_all_conversations( 'search' );
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array(), 500, $result );
		}

		return $this->create_rest_response( array( 'message' => sprintf( __( '%d search conversations deleted successfully.', 'echo-knowledge-base' ), $result ), 'deleted' => $result ) );
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