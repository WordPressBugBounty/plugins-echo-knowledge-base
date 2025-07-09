<?php defined( 'ABSPATH' ) || exit();

/**
 * Shared table operations for AI Search and AI Chat admin tables
 */
class EPKB_AI_Table_Operations {

	/**
	 * Get table data for REST API (returns data instead of sending JSON)
	 *
	 * @param string $mode 'search' or 'chat'
	 * @param array $params Request parameters
	 * @return array|WP_Error
	 */
	public static function get_table_data( $mode = 'search', $params = array() ) {
		
		$page        = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page    = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 20;
		$sort_column = isset( $params['orderby'] ) ? sanitize_key( $params['orderby'] ) : EPKB_AI_Messages_DB::PRIMARY_KEY;
		$sort_order  = isset( $params['order'] ) && in_array( strtolower( $params['order'] ), array( 'asc', 'desc' ) ) ? strtolower( $params['order'] ) : 'desc';
		$search      = isset( $params['s'] ) ? sanitize_text_field( $params['s'] ) : '';
		
		// Map display column names to database column names
		$column_map = array(
			'submit_date' => 'created',
			'name' => 'user_id',
			'page_name' => 'title',
			'status' => 'meta'
		);
		
		if ( isset( $column_map[ $sort_column ] ) ) {
			$sort_column = $column_map[ $sort_column ];
		}

		// Build filter
		$filter = array( 'mode' => $mode );
		if ( ! empty( $search ) ) {
			$filter['search'] = $search;
		}

		// Get the conversations
		$ai_messages_db = new EPKB_AI_Messages_DB();
		$conversations = $ai_messages_db->get_conversations(
			array_merge( $filter, array(
				'orderby'    => $sort_column,
				'order'      => strtoupper( $sort_order ),
				'per_page'   => $per_page,
				'page'       => $page
			) )
		);

		if ( is_wp_error( $conversations ) ) {
			return $conversations;
		}

		// Get total count for pagination
		$total_count = $ai_messages_db->get_conversations_count( $filter );

		// Format the data
		$formatted_data = array();
		foreach ( $conversations as $conversation ) {
			$formatted_data[] = self::format_row_data( $conversation, $mode );
		}

		return array(
			'items' => $formatted_data,
			'page' => $page,
			'per_page' => $per_page,
			'total' => $total_count,
			'pages' => ceil( $total_count / $per_page )
		);
	}

	/**
	 * Delete a single row (returns result instead of sending JSON)
	 *
	 * @param string $mode 'search' or 'chat'
	 * @param int $row_id
	 * @return bool|WP_Error
	 */
	public static function delete_row( $mode, $row_id ) {
		
		if ( ! EPKB_Utilities::is_positive_int( $row_id ) ) {
			return new WP_Error( 'invalid_id', __( 'Invalid row ID', 'echo-knowledge-base' ) );
		}

		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Verify the row exists and is of the correct mode
		$conversation_row = $ai_messages_db->get_by_primary_key( $row_id );
		if ( ! $conversation_row || $conversation_row->mode !== $mode ) {
			return new WP_Error( 'not_found', __( 'Conversation not found', 'echo-knowledge-base' ) );
		}

		// Delete the row
		$result = $ai_messages_db->delete_conversation( $row_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Delete selected rows (returns result instead of sending JSON)
	 *
	 * @param string $mode 'search' or 'chat'
	 * @param array $row_ids
	 * @return int|WP_Error Number of deleted rows
	 */
	public static function delete_selected_rows( $mode, $row_ids ) {
		
		if ( ! is_array( $row_ids ) || empty( $row_ids ) ) {
			return new WP_Error( 'no_selection', __( 'No rows selected', 'echo-knowledge-base' ) );
		}

		// Validate all IDs
		$valid_ids = array();
		foreach ( $row_ids as $id ) {
			if ( EPKB_Utilities::is_positive_int( $id ) ) {
				$valid_ids[] = absint( $id );
			}
		}

		if ( empty( $valid_ids ) ) {
			return new WP_Error( 'invalid_ids', __( 'Invalid row IDs', 'echo-knowledge-base' ) );
		}

		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Delete the rows
		$deleted_count = 0;
		foreach ( $valid_ids as $id ) {
			// Verify each row exists and is of the correct mode
			$conversation_row = $ai_messages_db->get_by_primary_key( $id );
			if ( $conversation_row && $conversation_row->mode === $mode ) {
				$result = $ai_messages_db->delete_conversation( $id );
				if ( ! is_wp_error( $result ) && $result ) {
					$deleted_count++;
				}
			}
		}

		return $deleted_count;
	}

	/**
	 * Delete all conversations (returns result instead of sending JSON)
	 *
	 * @param string $mode 'search' or 'chat'
	 * @return int|WP_Error Number of deleted conversations
	 */
	public static function delete_all_conversations( $mode ) {
		
		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Get all conversations for the mode
		$conversations = $ai_messages_db->get_conversations( array(
			'mode' => $mode,
			'per_page' => 1000 // Large batch
		) );

		if ( is_wp_error( $conversations ) ) {
			return $conversations;
		}

		// Delete each conversation
		$deleted_count = 0;
		foreach ( $conversations as $conversation ) {
			$result = $ai_messages_db->delete_conversation( $conversation->id );
			if ( ! is_wp_error( $result ) && $result ) {
				$deleted_count++;
			}
		}

		return $deleted_count;
	}

	/**
	 * Handle AJAX request to fetch table data based on pagination, sorting, and filtering
	 *
	 * @param string $mode 'search' or 'chat'
	 */
	public static function handle_get_table_data( $mode = 'search' ) {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die();

		$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$sort_column = isset( $_POST['sort_column'] ) ? sanitize_key( $_POST['sort_column'] ) : EPKB_AI_Messages_DB::PRIMARY_KEY;
		$sort_order  = isset( $_POST['sort_order'] ) && in_array( $_POST['sort_order'], array( 'asc', 'desc' ) ) ? $_POST['sort_order'] : 'desc';
		$filter      = isset( $_POST['filter'] ) ? EPKB_Utilities::post( 'filter' ) : array();
		$per_page    = 20;

		// Map display column names to database column names
		$column_map = array(
			'submit_date' => 'created',
			'name' => 'user_id',
			'page_name' => 'title',
			'status' => 'meta'
		);
		
		if ( isset( $column_map[ $sort_column ] ) ) {
			$sort_column = $column_map[ $sort_column ];
		}

		// Filter by mode
		if ( ! isset( $filter['mode'] ) ) {
			$filter['mode'] = $mode;
		}

		// KB ID filtering removed - no longer needed

		// Get the conversations
		$ai_messages_db = new EPKB_AI_Messages_DB();
		$conversations = $ai_messages_db->get_conversations(
			array_merge( $filter, array(
				'orderby'    => $sort_column,
				'order'      => strtoupper( $sort_order ),
				'per_page'   => $per_page,
				'page'       => $page
			) )
		);

		// Get total count for pagination
		$total_count = $ai_messages_db->get_conversations_count( $filter );

		// Format the data
		$formatted_data = array();
		foreach ( $conversations as $conversation ) {
			$formatted_data[] = self::format_row_data( $conversation, $mode );
		}

		// Create table instance to generate HTML
		$headings = $mode === 'search' ? array(
			'submit_date' => __( 'Date', 'echo-knowledge-base' ),
			'name' => __( 'Name', 'echo-knowledge-base' ),
			'page_name' => __( 'Page', 'echo-knowledge-base' ),
			'question' => __( 'Question', 'echo-knowledge-base' ),
			'status' => __( 'Status', 'echo-knowledge-base' )
		) : array(
			'submit_date' => __( 'Date', 'echo-knowledge-base' ),
			'name' => __( 'User', 'echo-knowledge-base' ),
			'chat_id' => __( 'Chat ID', 'echo-knowledge-base' ),
			'message_count' => __( 'Messages', 'echo-knowledge-base' ),
			'status' => __( 'Status', 'echo-knowledge-base' )
		);

		$table = new EPKB_UI_Table(
			'epkb-' . $mode . '-conversations-table',
			$per_page,
			$headings,
			array(),
			array( 'submit_date', 'name', 'status' ),
			true,
			'id',
			ceil( $total_count / $per_page ),
			$page,
			$total_count
		);

		// Generate HTML for rows
		$table_html = $table->generate_rows_html( $formatted_data );

		wp_send_json_success( array(
			'html' => $table_html,
			'current_page' => $page,
			'total_pages' => ceil( $total_count / $per_page ),
			'total_rows' => $total_count
		) );
	}

	/**
	 * Handle AJAX request to delete a single row
	 *
	 * @param string $mode 'search' or 'chat'
	 */
	public static function handle_delete_row( $mode = 'search' ) {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die();

		$row_id = EPKB_Utilities::post( 'row_id' );
		if ( ! EPKB_Utilities::is_positive_int( $row_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid row ID', 'echo-knowledge-base' ) ) );
		}

		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Verify the row exists and is of the correct mode
		$conversation = $ai_messages_db->get_by_primary_key( $row_id );
		if ( ! $conversation || $conversation->mode !== $mode ) {
			wp_send_json_error( array( 'message' => __( 'Conversation not found', 'echo-knowledge-base' ) ) );
		}

		// Delete the row
		$result = $ai_messages_db->delete_conversation( $row_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Conversation deleted successfully', 'echo-knowledge-base' ) ) );
	}

	/**
	 * Handle AJAX request to delete selected rows
	 *
	 * @param string $mode 'search' or 'chat'
	 */
	public static function handle_delete_selected_rows( $mode = 'search' ) {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die();

		$row_ids = EPKB_Utilities::post( 'row_ids' );
		if ( ! is_array( $row_ids ) || empty( $row_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No rows selected', 'echo-knowledge-base' ) ) );
		}

		// Validate all IDs
		$valid_ids = array();
		foreach ( $row_ids as $id ) {
			if ( EPKB_Utilities::is_positive_int( $id ) ) {
				$valid_ids[] = absint( $id );
			}
		}

		if ( empty( $valid_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid row IDs', 'echo-knowledge-base' ) ) );
		}

		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Verify all rows exist and are of the correct mode
		$verified_count = 0;
		foreach ( $valid_ids as $id ) {
			$conversation = $ai_messages_db->get_by_primary_key( $id );
			if ( $conversation && $conversation->mode === $mode ) {
				$verified_count++;
			}
		}

		if ( $verified_count !== count( $valid_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Some conversations not found or invalid', 'echo-knowledge-base' ) ) );
		}

		// Delete the rows
		$deleted_count = 0;
		foreach ( $valid_ids as $id ) {
			$result = $ai_messages_db->delete_conversation( $id );
			if ( ! is_wp_error( $result ) && $result ) {
				$deleted_count++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%d conversations deleted successfully', 'echo-knowledge-base' ), $deleted_count )
		) );
	}

	/**
	 * Handle AJAX request to delete all conversations
	 *
	 * @param string $mode 'search' or 'chat'
	 */
	public static function handle_delete_all_conversations( $mode = 'search' ) {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die();

		$ai_messages_db = new EPKB_AI_Messages_DB();
		
		// Get all conversations for the mode and KB
		$conversations = $ai_messages_db->get_conversations( array(
			'mode' => $mode,
			'per_page' => 1000 // Large batch
		) );

		if ( is_wp_error( $conversations ) ) {
			wp_send_json_error( array( 'message' => __( 'Error retrieving conversations', 'echo-knowledge-base' ) ) );
		}

		// Delete each conversation
		$deleted_count = 0;
		foreach ( $conversations as $conversation ) {
			$result = $ai_messages_db->delete_conversation( $conversation->id );
			if ( ! is_wp_error( $result ) && $result ) {
				$deleted_count++;
			}
		}

		wp_send_json_success( array( 'message' => __( 'All conversations deleted successfully', 'echo-knowledge-base' ) ) );
	}

	/**
	 * Format row data for display
	 *
	 * @param object $conversation
	 * @param string $mode
	 * @return array
	 */
	private static function format_row_data( $conversation, $mode ) {
		
		// Get user display name
		$user_name = '';
		if ( ! empty( $conversation->user_id ) ) {
			$user = get_user_by( 'id', $conversation->user_id );
			if ( $user ) {
				$user_name = $user->display_name;
			}
		}
		if ( empty( $user_name ) ) {
			$user_name = __( 'Guest', 'echo-knowledge-base' );
		}

		// Get first message
		$messages = json_decode( $conversation->messages, true );
		$first_message = '';
		if ( is_array( $messages ) && ! empty( $messages ) ) {
			$first_message = isset( $messages[0]['content'] ) ? $messages[0]['content'] : '';
		}

		// Format date
		$created_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conversation->created ) );

		// Get status from meta
		$meta = json_decode( $conversation->meta, true );
		$status = isset( $meta['status'] ) ? $meta['status'] : 'answered';
		$rating = isset( $meta['rating'] ) ? $meta['rating'] : 0;

		// Base data
		$row_data = array(
			'id'          => $conversation->id,
			'submit_date' => $created_date,
			'name'        => esc_html( $user_name ),
			'page_name'   => esc_html( $conversation->title ),
			'question'    => esc_html( wp_trim_words( $first_message, 20 ) ),
			'status'      => $status,
			'rating'      => $rating
		);

		// Add mode-specific data
		if ( $mode === 'chat' ) {
			// Add chat-specific fields
			$row_data['chat_id'] = $conversation->chat_id;
			$row_data['message_count'] = count( $messages );
		}

		return $row_data;
	}
}