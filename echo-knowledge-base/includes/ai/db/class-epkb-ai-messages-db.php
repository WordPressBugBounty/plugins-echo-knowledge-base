<?php

/**
 * Message Repository DB
 * 
 * Handles all database operations for AI messages.
 */
class EPKB_AI_Messages_DB extends EPKB_DB {
	
	/**
	 * Version History:
	 * 1.0 - Initial table structure
	 */
	const TABLE_VERSION = '1.0';    /** update when table schema changes **/
	const PER_PAGE = 20;
	const PRIMARY_KEY = 'id';

	/**
	 * Get things started
	 */
	public function __construct() {
		parent::__construct();
		
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'epkb_ai_messages';
		$this->primary_key = self::PRIMARY_KEY;
		
		// Ensure latest table exists
		$this->check_db();
	}
	
	/**
	 * Get columns and formats
	 *
	 * @return array
	 */
	public function get_column_format() {
		return array(
			'user_id'         => '%d',
			'ip'              => '%s',
			'title'           => '%s',
			'messages'        => '%s',
			'mode'            => '%s',
			'model'           => '%s',
			'session_id'      => '%s',
			'chat_id'         => '%s',
			'conversation_id' => '%s',
			'row_version'     => '%d',
			'last_idemp_key'  => '%s',
			'widget_id'       => '%d',
			'language'        => '%s',
			'metadata'        => '%s',
			'created'      => '%s',
			'updated'      => '%s'
		);
	}
	
	/**
	 * Get default column values
	 *
	 * @return array
	 */
	public function get_column_defaults() {
		return array(
			'id'              => 0,
			'user_id'         => 0,
			'ip'              => '',
			'title'           => '',
			'messages'        => '[]',
			'mode'            => 'search',
			'model'           => EPKB_AI_Config::DEFAULT_MODEL,
			'session_id'      => '',
			'chat_id'         => '',
			'conversation_id' => '',
			'row_version'     => 1,
			'last_idemp_key'  => '',
			'widget_id'       => 1,
			'language'        => '',
			'metadata'        => null,
			'created'      => current_time( 'mysql' ),
			'updated'      => current_time( 'mysql' )
		);
	}
	
	/**
	 * Save conversation
	 *
	 * @param EPKB_AI_Conversation_Model $conversation
	 * @return int|WP_Error Conversation ID or error
	 */
	public function save_conversation( EPKB_AI_Conversation_Model $conversation ) {
		$data = $conversation->to_db_array();
		
		if ( $conversation->get_id() > 0 ) {
			$result = $this->update_record( $conversation->get_id(), $data );
			if ( $this->handle_db_error( $result, 'update_conversation' ) === 'retry_operation' ) {
				$result = $this->update_record( $conversation->get_id(), $data );
			}
			
			if ( ! $result ) {
				return new WP_Error( 'save_failed', 'Failed to update conversation' );
			}

			return $conversation->get_id();

		} else {
			// Insert new
			$record_id = $this->insert_record( $data );
			if ( $this->handle_db_error( $record_id, 'insert_conversation' ) === 'retry_operation' ) {
				$record_id = $this->insert_record( $data );
			}

			return $record_id;
		}
	}
	
	/**
	 * Get conversation by ID
	 *
	 * @param int $row_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation( $row_id ) {

		$row = $this->get_by_primary_key( $row_id );
		if ( $this->handle_db_error( $row, 'get_conversation' ) === 'retry_operation' ) {
			$row = $this->get_by_primary_key( $row_id );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
	}
	
	/**
	 * Get conversation by chat ID
	 *
	 * @param string $chat_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation_by_chat_id( $chat_id ) {

		// new conversation if true
		if ( empty( $chat_id ) ) {
			return null; // Invalid chat ID
		}

		$row = $this->get_a_row_by_column_value( 'chat_id', $chat_id );
		if ( $this->handle_db_error( $row, 'get_conversation_by_chat_id' ) === 'retry_operation' ) {
			$row = $this->get_a_row_by_column_value( 'chat_id', $chat_id );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
	}

	/**
	 * Get the latest active conversation for a session
	 * Active = updated within last 24 hours
	 *
	 * @param string $session_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_latest_active_conversation_for_session( $session_id ) {
		global $wpdb;
		
		// Define active conversation criteria - only time-based
		$max_age_hours = apply_filters( 'epkb_ai_chat_max_age_hours', 24 );
		$cutoff_time = date( 'Y-m-d H:i:s', strtotime( "-{$max_age_hours} hours" ) );
		
		// Get the latest conversation for this session within the time window
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table_name} 
								WHERE session_id = %s AND updated > %s
								ORDER BY updated DESC LIMIT 1",
								$session_id, $cutoff_time );
		
		$row = $wpdb->get_row( $sql );
		if ( $this->handle_db_error( $row, 'get_latest_active_conversation' ) === 'retry_operation' ) {
			$row = $wpdb->get_row( $sql );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
	}
	
	/**
	 * Get conversation by chat ID and session ID for security
	 *
	 * @param string $chat_id
	 * @param string $session_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation_by_chat_and_session( $chat_id, $session_id ) {
		global $wpdb;
		
		if ( empty( $chat_id ) || empty( $session_id ) ) {
			return null;
		}
		
		$sql = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE chat_id = %s AND session_id = %s LIMIT 1", $chat_id, $session_id );
		
		$row = $wpdb->get_row( $sql );
		if ( $this->handle_db_error( $row, 'get_conversation_by_chat_and_session' ) === 'retry_operation' ) {
			$row = $wpdb->get_row( $sql );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
	}
	
	/**
	 * Check if idempotency key already exists for a conversation
	 *
	 * @param string $chat_id
	 * @param string $idempotency_key
	 * @return array|null Returns conversation data if idempotent request found
	 */
	public function check_idempotent_request( $chat_id, $idempotency_key ) {
		global $wpdb;
		
		if ( empty( $chat_id ) || empty( $idempotency_key ) ) {
			return null;
		}
		
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE chat_id = %s AND last_idemp_key = %s", $chat_id, $idempotency_key ) );
		if ( empty( $row ) ) {
			return null;
		}
		
		return $row;
	}
	
	/**
	 * Update conversation with optimistic concurrency control
	 *
	 * @param EPKB_AI_Conversation_Model $conversation
	 * @param string $idempotency_key
	 * @param int $expected_version
	 * @return bool|WP_Error
	 */
	public function update_conversation_with_version_check( $conversation, $idempotency_key, $expected_version ) {
		global $wpdb;
		
		$data = $conversation->to_db_array();
		$data['row_version'] = $expected_version + 1;
		$data['last_idemp_key'] = $idempotency_key;
		$data['updated'] = current_time( 'mysql' );
		
		// Get column formats
		$column_formats = $this->get_column_format();
		
		// Filter data to only include columns that have formats
		$data = array_intersect_key( $data, $column_formats );
		
		// Reorder column formats to match the order of data keys
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );
		
		// Build update query with version check
		$result = $wpdb->update( $this->table_name, $data, array( 'id' => $conversation->get_id(), 'row_version' => $expected_version ),
								$column_formats, array( '%d', '%d' )	);
		if ( $result === false ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}
		
		if ( $result === 0 ) {
			// No rows updated - version conflict
			return new WP_Error( 'version_conflict', 'Conversation was modified by another request' );
		}
		
		return true;
	}
	
	/**
	 * Insert new conversation with initial messages
	 *
	 * @param array $data Conversation data including messages
	 * @param string $idempotency_key
	 * @return int|WP_Error Insert ID or error
	 */
	public function insert_conversation_with_messages( $data, $idempotency_key ) {
		// Add metadata
		$data['last_idemp_key'] = $idempotency_key;
		$data['row_version'] = 1;
		$data['created'] = current_time( 'mysql' );
		$data['updated'] = current_time( 'mysql' );
		
		// Ensure messages is JSON encoded
		if ( isset( $data['messages'] ) && is_array( $data['messages'] ) ) {
			$data['messages'] = wp_json_encode( $data['messages'] );
		}
		
		$result = $this->insert_record( $data );
		if ( $this->handle_db_error( $result, 'delete_old_conversations' ) === 'retry_operation' ) {
			$result = $this->insert_record( $data );
		}
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'insert_failed', $result->get_error_message() );
		}
		
		return $result;
	}
	
	/**
	 * Get conversations with pagination
	 *
	 * @param array $args Query arguments
	 * @return array Array of EPKB_Conversation_Model objects
	 */
	public function get_conversations( $args = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => self::PER_PAGE,
			'mode'     => '',
			'user_id'  => 0,
			'widget_id' => '',
			'language' => '',
			'orderby'  => 'created',
			'order'    => 'DESC'
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		// Build WHERE clauses
		$where = array();
		
		if ( ! empty( $args['mode'] ) ) {
			$where[] = $this->prepare_column_value( 'mode', $args['mode'] );
		}
		
		
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $this->prepare_column_value( 'user_id', $args['user_id'] );
		}
		
		if ( ! empty( $args['language'] ) ) {
			$where[] = $this->prepare_column_value( 'language', $args['language'] );
		}
		
		if ( ! empty( $args['widget_id'] ) ) {
			$where[] = $this->prepare_column_value( 'widget_id', $args['widget_id'] );
		}
		
		// Calculate offset with overflow protection
		$page = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) ); // Limit to 100 per page
		$offset = ( $page - 1 ) * $per_page;
		
		// Get rows
		$rows = $this->get_rows_with_conditions( 
			$where, 
			$args['orderby'], 
			$args['order'], 
			$per_page, 
			$offset 
		);
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $rows, 'get_conversations' ) === 'retry_operation' ) {
			$rows = $this->get_rows_with_conditions( 
				$where, 
				$args['orderby'], 
				$args['order'], 
				$per_page, 
				$offset 
			);
		}
		
		if ( is_wp_error( $rows ) ) {
			return array();
		}
		
		// Convert to models
		$conversations = array();
		foreach ( $rows as $row ) {
			$conversations[] = EPKB_AI_Conversation_Model::from_db_row( $row );
		}
		
		return $conversations;
	}
	
	/**
	 * Get total count of conversations
	 *
	 * @param array $args Query arguments
	 * @return int
	 */
	public function get_conversations_count( $args = array() ) {
		$where = array();
		
		if ( ! empty( $args['mode'] ) ) {
			$where[] = $this->prepare_column_value( 'mode', $args['mode'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $this->prepare_column_value( 'user_id', $args['user_id'] );
		}
		
		if ( ! empty( $args['widget_id'] ) ) {
			$where[] = $this->prepare_column_value( 'widget_id', $args['widget_id'] );
		}
		
		$count = $this->get_count_with_conditions( $where );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $count, 'get_conversations_count' ) === 'retry_operation' ) {
			$count = $this->get_count_with_conditions( $where );
		}
		
		return $count;
	}
	
	/**
	 * Delete conversation
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete_conversation( $id ) {
		$result = $this->delete_record( $id );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $result, 'delete_conversation' ) === 'retry_operation' ) {
			$result = $this->delete_record( $id );
		}
		
		return $result;
	}

	/**
	 * Get rows with WHERE conditions
	 *
	 * @param array $where WHERE clauses
	 * @param string $orderby
	 * @param string $order
	 * @param int $limit
	 * @param int $offset
	 * @return array|WP_Error
	 */
	private function get_rows_with_conditions( $where, $orderby, $order, $limit, $offset ) {
		global $wpdb;
		
		$sql = "SELECT * FROM $this->table_name";
		
		if ( ! empty( $where ) ) {
			$sql .= " WHERE " . implode( ' AND ', $where );
		}
		
		// Validate orderby against allowed columns to prevent SQL injection
		$allowed_columns = array( 'id', 'created', 'updated', 'user_id', 'ip' );
		if ( ! in_array( $orderby, $allowed_columns, true ) ) {
			$orderby = 'created';
		}
		
		// Validate order direction
		$order = strtoupper( $order );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		
		// Use backticks for column name and validate limit/offset
		$orderby = '`' . $orderby . '`';
		$limit = absint( $limit );
		$offset = absint( $offset );
		
		$sql .= " ORDER BY $orderby $order";
		$sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $limit, $offset );
		
		$results = $wpdb->get_results( $sql );
		if ( $results === null && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}
		
		return $results ?: array();
	}
	
	/**
	 * Get count with WHERE conditions
	 *
	 * @param array $where WHERE clauses
	 * @return int
	 */
	private function get_count_with_conditions( $where ) {
		global $wpdb;
		
		$sql = "SELECT COUNT(*) FROM $this->table_name";
		
		if ( ! empty( $where ) ) {
			$sql .= " WHERE " . implode( ' AND ', $where );
		}
		
		$count = $wpdb->get_var( $sql );
		
		// If there's an error, return 0 to let the caller handle it
		if ( $count === null && ! empty( $wpdb->last_error ) ) {
			return 0;
		}
		
		return absint( $count );
	}

	/**
	 * Delete old conversations based on retention period
	 * 
	 * @param int $retention_days Number of days to keep conversations
	 * @return int|WP_Error Number of conversations deleted or error
	 */
	public function delete_old_conversations( $retention_days = 30 ) {
		global $wpdb;
		
		// Validate retention days
		$retention_days = absint( $retention_days );
		if ( $retention_days < 1 || $retention_days > 90 ) { // Max 90 days
			$retention_days = 10; // Default to 10 days
		}
		
		// Calculate cutoff date
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
		
		// Delete conversations older than retention period based on last update time
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE updated < %s", $cutoff_date ) );
		if ( $this->handle_db_error( $deleted, 'delete_old_conversations' ) === 'retry_operation' ) {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE updated < %s", $cutoff_date ) );
		}
		
		if ( $deleted === false ) {
			return new WP_Error( 'db_error', 'Failed to delete old conversations' );
		}
		
		if ( $deleted > 0 ) {
			EPKB_AI_Utilities::add_log( "Deleted {$deleted} old conversations older than {$retention_days} days" );
		}
		
		return $deleted;
	}
	
	/**
	 * Get metadata for a conversation
	 *
	 * @param int $conversation_id
	 * @return array Decoded metadata array or empty array
	 */
	public function get_metadata( $conversation_id ) {
		global $wpdb;
		
		$metadata = $wpdb->get_var( $wpdb->prepare( "SELECT metadata FROM {$this->table_name} WHERE id = %d", $conversation_id ) );
		
		if ( empty( $metadata ) ) {
			return array();
		}
		
		$decoded = json_decode( $metadata, true );
		return is_array( $decoded ) ? $decoded : array();
	}
	
	/**
	 * Update metadata for a conversation
	 *
	 * @param int $conversation_id
	 * @param array $metadata_array
	 * @return bool|WP_Error
	 */
	public function update_metadata( $conversation_id, $metadata_array ) {
		global $wpdb;
		
		if ( empty( $metadata_array ) ) {
			$json_metadata = null;
		} else {
			$json_metadata = wp_json_encode( $metadata_array );
			if ( $json_metadata === false ) {
				return new WP_Error( 'invalid_metadata', __( 'Invalid metadata format', 'echo-knowledge-base' ) );
			}
		}
		
		$result = $wpdb->update( 
			$this->table_name, 
			array( 'metadata' => $json_metadata ), 
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( $result === false ) {
			return new WP_Error( 'update_failed', $wpdb->last_error );
		}
		
		return true;
	}

	/**
	 * Handle database errors and create table if it doesn't exist
	 * 
	 * @param mixed $result The result of the database operation
	 * @param string $operation The operation being performed (for logging)
	 * @return mixed The original result if no error, or retry result if table was created
	 */
	private function handle_db_error( $result, $operation = '' ) {
		global $wpdb;

		static $table_updated = false;

		// If no error or table already updated, return original result
		$last_db_error = $result instanceof WP_Error ? $result->get_error_message() : $wpdb->last_error;
		if ( $table_updated || empty( $last_db_error ) ) {
			return $result;
		}
		
		// Check if error is related to missing table or column
		$error = strtolower( $last_db_error );
		$last_query = empty( $wpdb->last_query ) ? '' : strtolower( $wpdb->last_query );
		if ( strpos( $last_query, $this->table_name ) !== false &&
			( strpos( $error, "doesn't exist" ) !== false || strpos( $error, "does not exist" ) !== false ||
		     strpos( $error, "table" ) !== false && strpos( $error, "exist" ) !== false ||
		     strpos( $error, "unknown column" ) !== false || strpos( $error, "field list" ) !== false ) ) {
			
			// Try to create/update the table
			$this->create_table();
			$table_updated = true;
			
			// Log the table creation/update
			EPKB_AI_Utilities::add_log( "Created/updated table {$this->table_name} after error in operation: {$operation}" );
			
			// Return indication that retry is needed
			return 'retry_operation';
		}

		return $result;
	}

	protected function get_table_version() {
		return self::TABLE_VERSION;
	}

	/**
	 * Create the table
	 * 
	 * Table columns:
	 * - id: Primary key, auto-incrementing
	 * - user_id: WordPress user ID (null for guests)
	 * - session_id: Browser session (cookie); ties with one or more chat_ids
	 * - chat_id: Hex-UUID for the conversation (unique internal identifier)
	 * - ip: Store SHA-256 hash, not plaintext
	 * - title: Conversation title
	 * - conversation_id: Last OpenAI response.id (may not be unique)
	 * - messages: Ordered history in JSON format. Each message contains attributes: 'role', 'content'
	 * - row_version: Optimistic Concurrency Control (OCC) version counter
	 * - last_idemp_key: Last Idempotency-Key; prevent duplicates on retries
	 * - mode: Operation mode (default: 'search')
	 * - model: Determines which model to use and which provider
	 * - widget_id: Used to differentiate between different chat widgets
	 * - language: Language code
	 * - metadata: JSON field for storing additional data
	 * - created: Timestamp when conversation started
	 * - updated: Timestamp of last update
	 * 
	 * Indexes:
	 * - uniq_chat: 1-row-per-chat guard on chat_id
	 * - Various performance indexes on session_id, chat_id, conversation_id, mode, user_id, updated, widget_id
	 */
	protected function create_table() {
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		/** IMPORTANT: When modifying this table structure, you MUST update TABLE_VERSION constant at the top of this class! **/
		$sql = "CREATE TABLE {$this->table_name} (
				    id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				    user_id          BIGINT(20) UNSIGNED NULL,
				    session_id       VARCHAR(64)        NOT NULL,
				    chat_id          CHAR(64)           NOT NULL,
				    ip               VARBINARY(64)      NULL,
				    title            VARCHAR(255)       NULL,
				    conversation_id  VARCHAR(64)        NULL,
				    messages         TEXT               NOT NULL,
				    row_version      INT UNSIGNED       NOT NULL DEFAULT 1,
				    last_idemp_key   CHAR(64)           NULL,
				    mode             VARCHAR(20)        NOT NULL DEFAULT 'search',
				    model            VARCHAR(64)        NULL,
				    widget_id        TINYINT UNSIGNED   NOT NULL DEFAULT 1,
				    language         VARCHAR(20)        NULL,
				    metadata         LONGTEXT           NULL,
				    created	         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
					updated          DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    UNIQUE KEY  uniq_chat                    (chat_id),
				    KEY         idx_session_created          (session_id, created),
				    KEY         idx_chat_id                  (chat_id),
				    KEY         idx_conversation_id          (conversation_id),
				    KEY         idx_mode                     (mode),
				    KEY         idx_user_id_created          (user_id, created),
				    KEY         idx_updated                  (updated),
				    KEY         idx_widget_id                (widget_id)
			) $collate;";

		dbDelta( $sql );

		// Store version with autoload enabled
		update_option( $this->get_version_option_name(), self::TABLE_VERSION, true );
	}
}