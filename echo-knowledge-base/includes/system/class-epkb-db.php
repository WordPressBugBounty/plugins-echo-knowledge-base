<?php defined( 'ABSPATH' ) || exit();

/**
 * Base class for database access.
 * Based on EDD_DB class.
 */
abstract class EPKB_DB {

	/**
	 * The name of Help Dialog database table
	 */
	protected $table_name;

	/**
	 * The name of the primary key column
	 */
	protected $primary_key;

	public function __construct() {}

	/**
	 * Get table columns
	 */
	public function get_column_format() {
		return array();
	}

	/**
	 * Default column values
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Check if database table needs to be created or updated
	 *
	 * @return bool True if database is ready
	 */
	protected function check_db() {

		$stored_version = get_option( $this->get_version_option_name(), '0' );

		// Check if we need to create/update table
		if ( version_compare( $stored_version, $this->get_table_version(), '<' ) ) {
			$this->create_table();
		}
	}

	/**
	 * Get the option name for the table version
	 *
	 * @return string
	 */
	protected function get_version_option_name() {
		return $this->table_name . '_version';
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @param $primary_key_value
	 *
	 * @return Object|WP_Error|null - row as an Object with properties as column names e.g. $result->ID
	 *                              - null if 0 records found
	 *                              - WP_Error on failure
	 */
	public function get_by_primary_key( $primary_key_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		if ( empty($this->primary_key) ) {
			EPKB_Logging::add_log("Primary key is empty", $this->primary_key);
			return null;
		}

		if ( ! EPKB_Utilities::is_positive_int($primary_key_value) ) {
			EPKB_Logging::add_log("Primary key is not valid", $primary_key_value);
			return null;
		}

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $primary_key_value ) );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, 'Primary Key: ' . $primary_key_value );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve a row by a specific value in a column
	 *
	 * @param $column_name
	 * @param $column_value
	 *
	 * @return Object|WP_Error|null - row as an Object with properties as column names e.g. $result->ID
	 *                              - null if 0 records found
	 *                              - WP_Error on failure
	 */
	public function get_a_row_by_column_value( $column_name, $column_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$column_name = esc_sql( $column_name );
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column_name = %s LIMIT 1;", $column_value ) );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, 'Column - value: ' . $column_name . ' - ' . $column_value );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve multiple rows by a specific value in a column
	 *
	 * @param $column_name
	 * @param $column_value
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	public function get_rows_by_column_value( $column_name, $column_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$column_name = esc_sql( $column_name );
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column_name = %s LIMIT 500;", $column_value ) );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: ", $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return $result;
	}

	/**
	 * Retrieve ordered multiple rows
	 *
	 * @param $order_column
	 * @param string $order
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	protected function get_ordered_rows( $order_column, $order='DESC', $offset=0, $limit=500 ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$order_column = esc_sql( $order_column );
		$order = esc_sql( $order );
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name ORDER BY $order_column $order LIMIT %d, %d;", $offset, $limit ) );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: ", $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return $result;
	}

	/**
	 * Retrieve ordered multiple rows by a specific value in a column
	 *
	 * @param $column
	 * @param $column_value
	 * @param $order_column
	 * @param string $order
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	protected function get_ordered_rows_by_column_value( $column, $column_value, $order_column, $order='DESC', $offset=0, $limit=500 ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$column = esc_sql( $column );
		$order_column = esc_sql( $order_column );
		$order = esc_sql( $order );
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column = %s ORDER BY $order_column $order LIMIT %d, %d;", $column_value, $offset, $limit ) );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: ", $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return $result;
	}

	/**
	 * Retrieve a row by a WHERE clause
	 *
	 * @param array $where_data
	 *
	 * @return Object|WP_Error|null - row as an Object with properties as column names e.g. $result->ID
	 *                              - null if 0 records found
	 *                              - WP_Error on failure
	 */
	public function get_a_row_by_where_clause( array $where_data ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_clause = '';
		if ( ! empty( $where_data ) ) {
			$where_clause = $this->get_where_clause( $where_data );
			if ( empty( $where_clause ) ) {
				EPKB_Logging::add_log( 'Could not get records from ' . $this->table_name . ' table. Incorrect where_data' );
				return new WP_Error( 'db-query-error',  'Wrong WHERE condition' );
			}
		}
		$result = $wpdb->get_row( "SELECT * FROM {$this->table_name} WHERE {$where_clause} LIMIT 1;" );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: ", $wpdb_last_error );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve SUM of multiple rows using a where clause
	 *
	 * @param array $where_data
	 * @param $select_column
	 *
	 * @return int|WP_Error|null - column value
	 *                           - null if 0 records found
	 *                           - WP_Error on error
	 */
	public function get_sum_rows_by_where_clause( array $where_data, $select_column ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_clause = '';
		if ( ! empty( $where_data ) ) {
			$where_clause = $this->get_where_clause( $where_data );
			if ( empty( $where_clause ) ) {
				EPKB_Logging::add_log( 'Could not get records from ' . $this->table_name . ' table. Incorrect where_data' );
				return new WP_Error( 'db-query-error',  'Wrong WHERE condition' );
			}
		}

		$result = $wpdb->get_var( "SELECT SUM({$select_column}) AS total FROM {$this->table_name} WHERE {$where_clause}" );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: ", $wpdb_last_error );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve multiple rows using a where clause
	 *
	 * @param array $where_data
	 * @param string $where_clause
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	public function get_rows_by_where_clause( array $where_data, $where_clause='' ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		if ( ! empty( $where_data ) && empty( $where_clause ) ) {
			$where_clause = $this->get_where_clause( $where_data );
			if ( empty( $where_clause ) ) {
				EPKB_Logging::add_log( 'Could not get records from ' . $this->table_name . ' table. Incorrect where_data' );
				return new WP_Error( 'db-query-error',  'Wrong WHERE condition' );
			}
		}

		$result = $wpdb->get_results( "SELECT * FROM {$this->table_name} WHERE {$where_clause} LIMIT 500" );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log("DB failure: ", $wpdb_last_error);
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Get all records from the table. Limit 500.
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	public function get_all_rows() {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$result = $wpdb->get_results( "SELECT * FROM $this->table_name LIMIT 500;" );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log("DB failure: ", $wpdb_last_error);
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve a specific column's value by the primary key
	 *
	 * @param $column
	 * @param $primary_key
	 *
	 * @return string|WP_Error|null - column value
	 *                              - null if 0 records found
	 *                              - WP_Error on failure
	 */
	public function get_column_value_by_primary_key( $column, $primary_key ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		if ( empty($this->primary_key) ) {
			return null;
		}

		if ( ! EPKB_Utilities::is_positive_int($primary_key) ) {
			EPKB_Logging::add_log("Primary key is not valid", $primary_key);
			return null;
		}

		$column = esc_sql( $column );
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT $column FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $primary_key ) );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, 'Column: ' . $column. ', Primary Key: ' . $primary_key );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve a specific column's value by the the specified column / value
	 *
	 * @param $select_column - returned column value
	 * @param $column_name
	 * @param $column_value
	 *
	 * @return string|WP_Error|null - column value
	 *                              - null if 0 records found
	 *                              - WP_Error on failure
	 */
	public function get_column_value_by( $select_column, $column_name, $column_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$select_column = esc_sql( $select_column );
		$column_name   = esc_sql( $column_name );
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT $select_column FROM $this->table_name WHERE $column_name = %s LIMIT 1;", $column_value ) );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, 'Select Column: ' . $select_column. ', Column - value: ' . $column_name . ' - ' . $column_value );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Retrieve a specific column's value by the the specified column / value
	 *
	 * @param $select_column - returned column value
	 * @param $column_name
	 * @param $column_value
	 *
	 * @return array|WP_Error|null - column value
	 *                             - null if 0 records found
	 *                             - WP_Error on error
	 */
	public function get_column_values_by( $select_column, $column_name, $column_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$select_column = esc_sql( $select_column );
		$column_name   = esc_sql( $column_name );
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT $select_column FROM $this->table_name WHERE $column_name = %s LIMIT 500;", $column_value ) );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, 'Select Column: ' . $select_column. ', Column - value: ' . $column_name . ' - ' . $column_value );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Get record count for given range
	 *
	 * @param $date_column_name
	 * @param $date_from
	 * @param $date_to
	 * @param string $where_condition
	 *
	 * @return int|WP_Error
	 */
	public function get_count_rows_range( $date_column_name, $date_from, $date_to, $where_condition='' ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_between = $wpdb->prepare(" {$date_column_name} between %s and %s {$where_condition}", $date_from, $date_to );
		$result = $wpdb->get_var( "SELECT count(*) FROM $this->table_name WHERE {$where_between};" );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( "DB failure: " . $wpdb_last_error, $date_from . ' - ' . $date_to );
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return empty($result) ? 0 : (int) $result;
	}

	/**
	 * Retrieve multiple rows using a where clause
	 *
	 * @param $date_column_name
	 * @param $date_from
	 * @param $date_to
	 * @param $order_by
	 * @param $group_by
	 * @param $limit
	 * @param string $where_condition
	 *
	 * @return array|null|WP_Error - column value
	 *                             - null if 0 records found
	 * - WP_Error on error
	 */
	public function get_rows_by_date_range( $date_column_name, $date_from, $date_to, $order_by, $group_by, $limit, $where_condition='' ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_between = $wpdb->prepare(" {$date_column_name} between %s and %s {$where_condition} ", $date_from, $date_to );
		$result = $wpdb->get_results( "SELECT *, count(*) as times FROM $this->table_name WHERE {$where_between}  GROUP BY {$group_by} ORDER BY {$order_by} LIMIT {$limit};" );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log("DB failure: ", $wpdb_last_error);
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Get Distinct column value by column name
	 * @param $column_name
	 * @return array|object|WP_Error
	 */
	public function get_distinct_column_value_by_name( $column_name) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$result = $wpdb->get_results( "SELECT DISTINCT {$column_name} FROM $this->table_name WHERE {$column_name} != '' " );
		if ( ! empty($wpdb->last_error) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log("DB failure: ", $wpdb_last_error);
			return new WP_Error('DB failure', $wpdb_last_error);
		}

		return $result;
	}

	/**
	 * Insert a new row
	 *
	 * @param $data - Data to insert (in column => value pairs).
	 *                Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 *                Sending a null value will cause the column to be set to NULL - the corresponding format is ignored in this case.
	 * @return int|WP_Error - inserted record ID
	 */
	// KEEP PROTECTED to let implementation to handle security check
	protected function insert_record( $data ) { /** KEEP PROTECTED */
		/** @var $wpdb Wpdb */
		global $wpdb;

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		// Initialise column format array
		$column_formats = $this->get_column_format();

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->insert( $this->table_name, $data, $column_formats ) ) {
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( 'DB failure: ', $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a row
	 *
	 * @param $id - primary key or column value used in WHERE
	 * @param array $data - Data to update (in column => value pairs).
	 *                      Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 *                      Sending a null value will cause the column to be set to NULL - the corresponding
	 *                      format is ignored in this case.
	 * @param string $column_name - used in the WHERE clause. default is the primary key
	 *
	 * @return bool|WP_Error - return false/WP_Error on error
	 */
	// KEEP PROTECTED
	protected function update_record( $id, $data = array(), $column_name = '' ) {  /** KEEP PROTECTED */
		/** @var $wpdb Wpdb */
		global $wpdb;

		// Row ID must be a positive integer
		if ( ! EPKB_Utilities::is_positive_int( $id ) ) {
			EPKB_Logging::add_log("Row ID is not valid", $id);
			return false;
		}

		if ( empty( $column_name ) ) {
			$column_name = $this->primary_key;
		}

		// Initialise column format array
		$column_formats = $this->get_column_format();

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->update( $this->table_name, $data, array( $column_name => $id ), $column_formats ) ) {
			EPKB_Logging::add_log("Could not update record in " . $this->table_name . " table. Row id: " . $id . ". Data to be inserted: ", $data);
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( 'DB failure: ', $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return true;
	}

	/**
	 * If record exist then UPDATE it otherwise INSERT it.
	 *
	 * @param $primary_key_value
	 * @param $data
	 *
	 * @return bool|WP_Error - return false/WP_Error on error
	 */
	// KEEP PROTECTED
	protected function upsert_record( $primary_key_value, $data ) {

		if ( empty( $this->primary_key ) ) {
			return null;
		}

		$record = $this->get_by_primary_key( $primary_key_value );
		if ( is_wp_error( $record) ) {
			EPKB_Logging::add_log("Cannot get by primary key: ", $primary_key_value);
			return false;
		}

		// if no record found (or error occurred), INSERT the record
		if ( empty( $record ) ) {
			$result = $this->insert_record( array($this->primary_key => $primary_key_value) + $data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $result > 0;
		} else {
			return $this->update_record( $primary_key_value, $data );
		}
	}

	/**
	 * Delete a row identified by the primary key
	 *
	 * @param int $primary_key
	 *
	 * @return bool|WP_Error - return false/WP_Error on error
	 */
	// KEEP PROTECTED
	protected function delete_record( $primary_key = 0 ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		if ( empty($this->primary_key) ) {
			return false;
		}

		// Row ID must be positive integer
		if ( ! EPKB_Utilities::is_positive_int( $primary_key ) ) {
			EPKB_Logging::add_log("Row ID is not valid", $primary_key);
			return false;
		}

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM $this->table_name WHERE $this->primary_key = %d", $primary_key ) ) ) {
			EPKB_Logging::add_log("Could not delete record in " . $this->table_name . " table. Row id: " . $primary_key);
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( 'DB failure: ', $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return true;
	}

	/**
	 * Delete a row identified by the primary key
	 *
	 * @param $column_name
	 * @param $column_value
	 * @return bool|WP_Error - return false/WP_Error on error
	 */
	// KEEP PROTECTED
	protected function delete_record_by_column_value( $column_name, $column_value ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$column_name = esc_sql( $column_name );
		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM $this->table_name WHERE $column_name = %d", $column_value ) ) ) {
			EPKB_Logging::add_log("Could not delete records in " . $this->table_name . " table. Column $column_name and value $column_value");
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( 'DB failure: ', $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return true;
	}

	/**
	 * Delete multiple rows using a where clause
	 *
	 * @param array $where_data
	 * @return bool|WP_Error - return false/WP_Error on error
	 */
	// KEEP PROTECTED
	protected function delete_rows_by_where_clause( array $where_data ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_clause = '';
		if ( ! empty( $where_data ) ) {
			$where_clause = $this->get_where_clause( $where_data );
			if ( empty( $where_clause ) ) {
				EPKB_Logging::add_log( 'Could not delete records in ' . $this->table_name . ' table. Incorrect where_data' );
				return false;
			}
		}
		if ( false === $wpdb->query( "DELETE FROM {$this->table_name} WHERE {$where_clause}" ) ) {
			EPKB_Logging::add_log("Could not delete records in " . $this->table_name . " table. Where is {$where_clause}");
			$wpdb_last_error = $wpdb->last_error;   // add_log changes last_error so store it first
			EPKB_Logging::add_log( 'DB failure: ', $wpdb_last_error );
			return new WP_Error( 'DB failure', $wpdb_last_error );
		}

		return true;
	}

	/**
	 * Get number of records
	 *
	 * @return int
	 */
	protected function get_number_of_rows() {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$result = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_name" );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			EPKB_Logging::add_log( "DB failure: " . $wpdb->last_error );
			return 0;
		}

		return (int)$result;
	}

	/**
	 * Get number of records by column(s) value(s)
	 *
	 * @param array $where_data
	 * @return int
	 */
	protected function get_number_of_rows_by_where_clause( array $where_data ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$where_clause = '';
		if ( ! empty( $where_data ) ) {
			$where_clause = $this->get_where_clause( $where_data );
			if ( empty( $where_clause ) ) {
				EPKB_Logging::add_log( 'Could not get records from ' . $this->table_name . ' table. Incorrect where_data' );
				return 0;
			}
		}
		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}" );
		if ( $result === null && ! empty($wpdb->last_error) ) {
			EPKB_Logging::add_log( "DB failure: " . $wpdb->last_error );
			return 0;
		}

		return (int)$result;
	}

	/**
	 * Check if the given table exists
	 *
	 * @param  string $table The table name
	 * @return bool          If the table name exists
	 */
	public function table_exists( $table ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		$table = sanitize_text_field( $table );
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
	}

	/**
	 * Check if the table was ever installed
	 *
	 * @return bool Returns if the customers table was installed and upgrade routine run
	 */
	public function installed() {
		return $this->table_exists( $this->table_name );
	}

	public function getTableName() {
		return $this->table_name;
	}

	/**
	 * Delete all records in the table
	 *
	 * @return bool|int
	 */
	public function clear_table() {
		global $wpdb;
		return $wpdb->query( "DELETE FROM " . $this->table_name );
	}

	/**
	 * Drop the table
	 *
	 * @return bool|int
	 */
	public function delete_table() {
		global $wpdb;
		return $wpdb->query( "DROP TABLE IF EXISTS " . $this->table_name );
	}

	/**************************************************************************************************************************
	 *
	 *                     Support functions
	 *
	 *************************************************************************************************************************/

	/**
	 * Generate where clause from data
	 *
	 * @param array $where_data
	 *
	 * @return false|string
	 */
	public function get_where_clause( array $where_data ) {
		global $wpdb;

		if ( empty( $where_data ) ) {
			return false;
		}

		$where_escaped = ' ';
		$first = true;
		foreach( $where_data as $column => $value ) {
			if ( is_array( $value ) ) {
				if ( ! isset( $value['value'] ) ) {
					EPKB_Logging::add_log( 'Wrong WHERE clause for ' . $this->table_name . ' table. Empty value for the column: ' . $column );
					return false;
				}
				$value = $value['value'];
				$operator_escaped = isset( $value['operator'] ) ? $value['operator'] : '=';
			} else {
				$operator_escaped = '=';
			}

			// sanitize operator
			$allowed_operators = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN'];
			$operator_escaped = in_array( $operator_escaped, $allowed_operators, true ) ? $operator_escaped : '=';

			// sanitize format
			$allowed_formats = ['%s', '%d', '%f'];
			$column_formats = $this->get_column_format();
			$format = empty( $column_formats[$column] ) ? '%s' : $column_formats[$column];
			$format_escaped = in_array( $format, $allowed_formats, true ) ? $format : '%s';

			$where_escaped .= ( $first ? '' : ' AND ' ) . esc_sql( $column ) . ' ' . $operator_escaped . ' ' . $wpdb->prepare( $format_escaped, $value ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$first = false;
		}

		return $where_escaped;
	}

}
