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
			'id'              => '%d',
			'user_id'         => '%d',
			'ip'              => '%s',
			'title'           => '%s',
			'messages'        => '%s',
			'mode'            => '%s',
			'model'           => '%s',
			'chat_id'         => '%s',
			'conversation_id' => '%s',
			'vector_store_id' => '%s',
			'widget_id'       => '%d',
			'language'        => '%s',
			'created'         => '%s',
			'updated'         => '%s'
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
			'chat_id'         => '',
			'conversation_id' => '',
			'vector_store_id' => '',
			'widget_id'       => '1',
			'language'        => '',
			'created'         => current_time( 'mysql' ),
			'updated'         => current_time( 'mysql' )
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
			// Update existing
			$result = $this->update_record( $conversation->get_id(), $data );
			
			// Check if we need to create table and retry
			if ( $this->handle_db_error( $result, 'update_conversation' ) === 'retry_operation' ) {
				$result = $this->update_record( $conversation->get_id(), $data );
			}
			
			if ( ! $result ) {
				return new WP_Error( 'save_failed', 'Failed to update conversation' );
			}
			return $conversation->get_id();
		} else {
			// Insert new
			$id = $this->insert_record( $data );
			
			// Check if we need to create table and retry
			if ( $this->handle_db_error( $id, 'insert_conversation' ) === 'retry_operation' ) {
				$id = $this->insert_record( $data );
			}
			
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			return $id;
		}
	}
	
	/**
	 * Get conversation by ID
	 *
	 * @param int $id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation( $id ) {
		$row = $this->get_by_primary_key( $id );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $row, 'get_conversation' ) === 'retry_operation' ) {
			$row = $this->get_by_primary_key( $id );
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
		if ( empty( $chat_id ) ) {
			return null; // Invalid chat ID
		}

		$row = $this->get_a_row_by_column_value( 'chat_id', $chat_id );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $row, 'get_conversation_by_chat_id' ) === 'retry_operation' ) {
			$row = $this->get_a_row_by_column_value( 'chat_id', $chat_id );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
	}
	
	/**
	 * Get conversation by OpenAI conversation ID
	 *
	 * @param string $conversation_id
	 * @return EPKB_AI_Conversation_Model|null
	 */
	public function get_conversation_by_openai_id( $conversation_id ) {
		$row = $this->get_a_row_by_column_value( 'conversation_id', $conversation_id );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $row, 'get_conversation_by_openai_id' ) === 'retry_operation' ) {
			$row = $this->get_a_row_by_column_value( 'conversation_id', $conversation_id );
		}
		
		if ( empty( $row ) ) {
			return null;
		}
		
		return EPKB_AI_Conversation_Model::from_db_row( $row );
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
	 * Prepare column value for WHERE clause
	 *
	 * @param string $column
	 * @param mixed $value
	 * @return string
	 */
	private function prepare_column_value( $column, $value ) {
		global $wpdb;
		
		$format = $this->get_column_format()[ $column ];
		return $wpdb->prepare( "`$column` = $format", $value );
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
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table_name} 
			WHERE updated < %s",
			$cutoff_date
		) );
		
		// Check if we need to create table and retry
		if ( $this->handle_db_error( $deleted, 'delete_old_conversations' ) === 'retry_operation' ) {
			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$this->table_name} 
				WHERE updated < %s",
				$cutoff_date
			) );
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
		if ( strpos( $error, $this->table_name ) !== false &&
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
	 */
	protected function create_table() {
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		/** IMPORTANT: When modifying this table structure, you MUST update TABLE_VERSION constant at the top of this class! **/
		$sql = "CREATE TABLE {$this->table_name} (
			id              BIGINT(20) NOT NULL AUTO_INCREMENT,
			user_id         BIGINT(20) NULL,
			ip              VARCHAR(64) NULL,
			title           VARCHAR(255) NULL,
			messages        MEDIUMTEXT NULL,
			mode            VARCHAR(16) NOT NULL DEFAULT 'search',
			model           VARCHAR(64) NULL,
			chat_id         VARCHAR(64) NOT NULL,
			conversation_id VARCHAR(64) NULL,
			vector_store_id VARCHAR(64) NULL,
			widget_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
			language        VARCHAR(10) NULL,
			created         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			INDEX idx_chat_id (chat_id),
			INDEX idx_conversation_id (conversation_id),
			INDEX idx_mode (mode),
			INDEX idx_user_id_created (user_id, created),
			INDEX idx_updated (updated),
			INDEX idx_widget_id (widget_id)
		) $collate;";

		dbDelta( $sql );

		// Store version with autoload enabled
		update_option( $this->get_version_option_name(), self::TABLE_VERSION, true );
	}
}