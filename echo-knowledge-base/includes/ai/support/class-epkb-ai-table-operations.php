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
	 * @return array
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
	 * @return array|int Number of deleted conversations
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
	 * Format row data for display
	 *
	 * @param EPKB_AI_Conversation_Model $conversation
	 * @param string $mode
	 * @return array
	 */
	private static function format_row_data( $conversation, $mode ) {
		
		// Get user display name
		$user_name = '';
		$user_id = $conversation->get_user_id();
		if ( ! empty( $user_id ) ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$user_name = $user->display_name;
			}
		}
		if ( empty( $user_name ) ) {
			$user_name = __( 'Guest', 'echo-knowledge-base' );
		}

		// Get first message
		$messages = $conversation->get_messages();
		$first_message = '';
		if ( is_array( $messages ) && ! empty( $messages ) ) {
			$first_message = isset( $messages[0]['content'] ) ? $messages[0]['content'] : '';
		}

		// Get raw UTC date for frontend formatting
		$created_date = $conversation->get_created();

		// Get status from metadata
		$metadata = $conversation->get_metadata();
		$status = isset( $metadata['status'] ) ? $metadata['status'] : 'answered';
		$rating = isset( $metadata['rating'] ) ? $metadata['rating'] : 0;

		// Base data
		$row_data = array(
			'id'          => $conversation->get_id(),
			'submit_date' => $created_date,
			'name'        => $user_name,
			'page_name'   => $conversation->get_title(),
			'question'    => wp_trim_words( $first_message, 20 ),
			'status'      => $status,
			'rating'      => $rating
		);

		// Add mode-specific data
		if ( $mode === 'chat' ) {
			// Add chat-specific fields
			$row_data['chat_id'] = $conversation->get_chat_id();
			$row_data['message_count'] = count( $messages );
			$row_data['time'] = $created_date;
			// Mark support conversations differently
			if ( $conversation->get_mode() === 'support' ) {
				$row_data['user'] = '🎧 ' . $user_name;
			} else {
				$row_data['user'] = $user_name;
			}
			$row_data['user_id'] = $user_id;
			$row_data['conversation'] = $first_message;
			$row_data['first_message'] = $first_message;
		} elseif ( $mode === 'search' ) {
			// Add search-specific fields for admin display
			$row_data['created_at'] = $created_date;
			$row_data['time'] = $created_date;
			$row_data['user_name'] = $user_name;
			$row_data['user'] = $user_name;
			$row_data['user_id'] = $user_id;
			$row_data['query'] = $first_message;
			
			// Get AI response (second message) to count results
			$ai_response = isset( $messages[1]['content'] ) ? $messages[1]['content'] : '';
			// For AI search, we don't have traditional result count
			$row_data['results_count'] = $ai_response ? '1' : '0';
			$row_data['results_found'] = $row_data['results_count'];
			
			// TODO: Track clicked articles in metadata
			$row_data['clicked_article'] = '-';
			$row_data['article_clicked'] = '-';
		}

		return $row_data;
	}
}