<?php

/**
 * Conversation Model
 * 
 * Represents a complete conversation with messages, metadata, and state.
 */
class EPKB_AI_Conversation_Model {
	
	/**
	 * Conversation ID
	 * @var int
	 */
	protected $id;

	/**
	 * User ID
	 * @var int
	 */
	protected $user_id;
	
	/**
	 * Session ID
	 * @var string
	 */
	protected $session_id;
	
	/**
	 * Chat ID (unique identifier)
	 * @var string
	 */
	protected $chat_id;
	
	/**
	 * OpenAI conversation ID
	 * @var string
	 */
	protected $conversation_id;
	
	/**
	 * Row version for optimistic concurrency
	 * @var int
	 */
	public $row_version;

	/**
	 * Mode (search or chat)
	 * @var string
	 */
	protected $mode;
	
	/**
	 * Title
	 * @var string
	 */
	protected $title;
	
	/**
	 * Messages
	 * @var array
	 */
	public $messages;
	
	/**
	 * Model used
	 * @var string
	 */
	protected $model;
	
	/**
	 * Widget ID
	 * @var string
	 */
	protected $widget_id;

	/**
	 * Language
	 * @var string
	 */
	protected $language;
	
	/**
	 * Metadata
	 * @var array
	 */
	protected $metadata;
	
	/**
	 * Created timestamp
	 * @var string
	 */
	protected $created;
	
	/**
	 * Updated timestamp
	 * @var string
	 */
	protected $updated;
	
	/**
	 * Constructor
	 *
	 * @param array $data
	 */
	public function __construct( $data = array() ) {
		$this->id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$this->user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : get_current_user_id();
		$this->session_id = isset( $data['session_id'] ) ? sanitize_text_field( $data['session_id'] ) : '';
		$this->chat_id = isset( $data['chat_id'] ) ? $this->validate_id( $data['chat_id'], 'chat' ) : '';
		$this->conversation_id = isset( $data['conversation_id'] ) ? $this->validate_id( $data['conversation_id'], 'conversation' ) : '';
		$this->row_version = isset( $data['row_version'] ) ? absint( $data['row_version'] ) : 1;
		$this->mode = isset( $data['mode'] ) ? $this->validate_mode( $data['mode'] ) : 'search';
		$this->title = isset( $data['title'] ) ? EPKB_AI_Validation::validate_title( $data['title'] ) : '';
		$this->messages = isset( $data['messages'] ) ? $this->parse_messages( $data['messages'] ) : array();
		$this->model = isset( $data['model'] ) ? $this->validate_model( $data['model'] ) : '';
		$this->widget_id = isset( $data['widget_id'] ) ? $this->validate_widget_id( $data['widget_id'] ) : '1';
		$this->language = isset( $data['language'] ) ? EPKB_AI_Validation::validate_language( $data['language'] ) : '';
		$this->metadata = isset( $data['metadata'] ) ? $this->parse_metadata( $data['metadata'] ) : array();
		$this->created = isset( $data['created'] ) ? $data['created'] : current_time( 'mysql' );
		$this->updated = isset( $data['updated'] ) ? $data['updated'] : current_time( 'mysql' );
	}

	public function set_chat_id( $chat_id ) {
		$this->chat_id = $this->validate_id( $chat_id, 'chat' );
	}	

	public function set_session_id( $session_id ) {
		$this->session_id = $this->validate_id( $session_id, 'session' );
	}

	/**
	 * Parse messages
	 *
	 * @param mixed $messages
	 * @return array
	 */
	protected function parse_messages( $messages ) {
		if ( is_string( $messages ) ) {
			$decoded = json_decode( $messages, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				EPKB_AI_Utilities::add_log( 'Failed to decode messages JSON: ' . json_last_error_msg() );
				return array();
			}
			$messages = is_array( $decoded ) ? $decoded : array();
		}
		
		return is_array( $messages ) ? $messages : array();
	}
	
	/**
	 * Parse metadata
	 *
	 * @param mixed $metadata
	 * @return array
	 */
	protected function parse_metadata( $metadata ) {
		if ( is_string( $metadata ) ) {
			$decoded = json_decode( $metadata, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				EPKB_AI_Utilities::add_log( 'Failed to decode metadata JSON: ' . json_last_error_msg() );
				return array();
			}
			$metadata = is_array( $decoded ) ? $decoded : array();
		}
		
		return is_array( $metadata ) ? $metadata : array();
	}
	
	/**
	 * Add message to the conversation; ensures unique message ID (generated if not provided) to avoid duplicates
	 *
	 * @param string $role
	 * @param string $content
	 * @param array $metadata
	 * @param string $message_id Optional message ID used to avoid duplicates; not in DB right now
	 */
	public function add_message( $role, $content, $metadata = array(), $message_id = '' ) {
		// Generate message ID if not provided
		if ( empty( $message_id ) ) {
			$message_id = 'msg_' . uniqid( $role . '_', true );
		}
		
		// Check for duplicate message ID
		if ( $this->has_message_id( $message_id ) ) {
			EPKB_AI_Utilities::add_log( 'Duplicate message ID detected: ' . $message_id );
			return false;
		}
		
		$message = array(
			'id' => $message_id,
			'role' => $role,
			'content' => $content,
			'timestamp' => current_time( 'mysql' ),
			'metadata' => $metadata
		);
		
		$this->messages[] = $message;
		$this->updated = current_time( 'mysql' );
		
		// Update title from first user message if empty
		if ( empty( $this->title ) && $role === 'user' ) {
			// Generate title from content without creating circular dependency
			$this->title = $this->generate_title_from_content( $content );
		}
		
		return true;
	}

	/**
	 * Validate mode
	 *
	 * @param string $mode
	 * @return string
	 */
	protected function validate_mode( $mode ) {
		$valid_modes = array( 'search', 'chat' );
		return in_array( $mode, $valid_modes ) ? $mode : 'search';
	}

	/**
	 * Validate ID format
	 *
	 * @param string $id
	 * @param string $type
	 * @return string
	 */
	protected function validate_id( $id, $type = 'generic' ) {
		$id = sanitize_text_field( $id );

		// Validate based on type
		switch ( $type ) {
			case 'chat':
				// Chat IDs should be UUID format or similar
				if ( strlen( $id ) > 64 ) {
					return substr( $id, 0, 64 );
				}
				break;

			case 'conversation':
				// OpenAI IDs have specific format
				if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $id ) || strlen( $id ) > 64 ) {
					return '';
				}
				break;
		}

		return $id;
	}

	/**
	 * Validate model name
	 *
	 * @param string $model
	 * @return string
	 */
	protected function validate_model( $model ) {
		$model = sanitize_text_field( $model );
		// Limit model length and ensure it matches expected pattern
		if ( ! preg_match( '/^[a-zA-Z0-9.-]+$/', $model ) || strlen( $model ) > 64 ) {
			return EPKB_AI_Config::DEFAULT_MODEL;
		}
		return $model;
	}

	/**
	 * Validate widget ID
	 *
	 * @param string|int $widget_id
	 * @return string
	 */
	protected function validate_widget_id( $widget_id ) {
		// Convert to integer and validate range
		$widget_id = absint( $widget_id );
		if ( $widget_id < 1 || $widget_id > EPKB_AI_Config::MAX_WIDGET_ID ) {
			return '1';
		}
		return (string) $widget_id;
	}

	public function has_message_id( $message_id ) {
		foreach ( $this->messages as $message ) {
			if ( isset( $message['id'] ) && $message['id'] === $message_id ) {
				return true;
			}
		}
		return false;
	}
	
	public function get_messages() {
		return $this->messages;
	}
	
	public function get_messages_array() {
		return $this->messages;
	}
	
	public function get_id() {
		return $this->id;
	}
	
	public function get_chat_id() {
		return $this->chat_id;
	}
	
	public function get_conversation_id() {
		return $this->conversation_id;
	}
	
	public function set_conversation_id( $conversation_id ) {
		$this->conversation_id = $conversation_id;
		// Update the timestamp when setting new conversation ID
		$this->updated = current_time( 'mysql' );
	}
	
	/**
	 * Check if conversation expired
	 *
	 * @return bool
	 */
	public function is_conversation_expired() {
		if ( empty( $this->conversation_id ) ) {
			return true;
		}
		// Check if conversation is older than x days based on last update
		return ( time() - strtotime( $this->updated ) ) > ( EPKB_AI_Config::DEFAULT_CONVERSATION_EXPIRY_DAYS * DAY_IN_SECONDS );
	}

	public function get_mode() {
		return $this->mode;
	}
	
	public function is_search() {
		return $this->mode === 'search';
	}
	
	public function is_chat() {
		return $this->mode === 'chat';
	}

	/**
	 * Convert to array for database
	 *
	 * @return array
	 */
	public function to_db_array() {
		return array(
			'user_id'         => $this->user_id,
			'session_id'      => $this->session_id,
			'chat_id'         => $this->chat_id,
			'conversation_id' => $this->conversation_id,
			'mode'            => $this->mode,
			'title'           => $this->title,
			'messages'        => wp_json_encode( $this->get_messages_array() ),
			'model'           => $this->model,
			'widget_id'       => $this->widget_id,
			'language'        => $this->language,
			'metadata'        => wp_json_encode( $this->metadata ),
			'updated'         => $this->updated
		);
	}
	
	/**
	 * Create from database row
	 *
	 * @param object $row
	 * @return self
	 */
	public static function from_db_row( $row ) {
		$data = (array) $row;
		return new self( $data );
	}
	
	/**
	 * Generate title from content
	 * This is a simple version to avoid circular dependency with EPKB_Content_Processor
	 *
	 * @param string $content
	 * @return string
	 */
	private function generate_title_from_content( $content ) {
		// Remove extra whitespace
		$title = preg_replace( '/\s+/', ' ', trim( $content ) );
		
		// Truncate to first sentence or 100 characters
		$sentences = preg_split( '/[.!?]+/', $title, 2 );
		$title = ! empty( $sentences[0] ) ? $sentences[0] : $title;
		
		// Limit length
		if ( strlen( $title ) > 100 ) {
			$title = substr( $title, 0, 97 ) . '...';
		}
		
		return $title;
	}

	public function get_created() {
		return $this->created;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_title() {
		return $this->title;
	}
	
	public function get_widget_id() {
		return $this->widget_id;
	}
	
	public function set_widget_id( $widget_id ) {
		$this->widget_id = $this->validate_widget_id( $widget_id );
	}
	
	public function get_session_id() {
		return $this->session_id;
	}
	
	public function get_row_version() {
		return $this->row_version;
	}

	public function get_updated() {
		return $this->updated;
	}

	public function get_metadata() {
		return $this->metadata;
	}

	public function set_metadata( $metadata ) {
		$this->metadata = $metadata;
	}
	
	/**
	 * Alias for get_metadata() for backwards compatibility
	 *
	 * @return array
	 */
	public function get_meta() {
		return $this->get_metadata();
	}
}