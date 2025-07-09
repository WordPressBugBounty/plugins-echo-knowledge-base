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
	protected $messages;
	
	/**
	 * Model used
	 * @var string
	 */
	protected $model;
	
	/**
	 * Vector store ID
	 * @var string
	 */
	protected $vector_store_id;
	
	/**
	 * Widget ID
	 * @var string
	 */
	protected $widget_id;
	
	/**
	 * Rating (1-5)
	 * @var int
	 */
	protected $rating;
	
	/**
	 * Language
	 * @var string
	 */
	protected $language;
	
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
	 * Metadata
	 * @var array
	 */
	protected $metadata;
	
	/**
	 * Constructor
	 *
	 * @param array $data
	 */
	public function __construct( $data = array() ) {
		$this->id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$this->user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		$this->chat_id = isset( $data['chat_id'] ) ? $this->validate_id( $data['chat_id'], 'chat' ) : '';
		$this->conversation_id = isset( $data['conversation_id'] ) ? $this->validate_id( $data['conversation_id'], 'conversation' ) : '';
		$this->mode = isset( $data['mode'] ) ? $this->validate_mode( $data['mode'] ) : 'search';
		$this->title = isset( $data['title'] ) ? $this->validate_title( $data['title'] ) : '';
		$this->messages = isset( $data['messages'] ) ? $this->parse_messages( $data['messages'] ) : array();
		$this->model = isset( $data['model'] ) ? $this->validate_model( $data['model'] ) : '';
		$this->vector_store_id = isset( $data['vector_store_id'] ) ? $this->validate_id( $data['vector_store_id'], 'vector_store' ) : '';
		$this->widget_id = isset( $data['widget_id'] ) ? $this->validate_widget_id( $data['widget_id'] ) : '1';
		$this->rating = isset( $data['rating'] ) ? $this->validate_rating( $data['rating'] ) : 0;
		$this->language = isset( $data['language'] ) ? $this->validate_language( $data['language'] ) : '';
		$this->created = isset( $data['created'] ) ? $data['created'] : current_time( 'mysql' );
		$this->updated = isset( $data['updated'] ) ? $data['updated'] : current_time( 'mysql' );
		$this->metadata = isset( $data['meta'] ) ? $this->parse_metadata( $data['meta'] ) : array();
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
			case 'vector_store':
				// OpenAI IDs have specific format
				if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $id ) || strlen( $id ) > 64 ) {
					return '';
				}
				break;
		}
		
		return $id;
	}
	
	/**
	 * Validate title
	 *
	 * @param string $title
	 * @return string
	 */
	protected function validate_title( $title ) {
		$title = sanitize_text_field( $title );
		// Limit title length
		if ( strlen( $title ) > 255 ) {
			$title = substr( $title, 0, 255 );
		}
		return $title;
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
	 * Validate rating
	 *
	 * @param int $rating
	 * @return int
	 */
	protected function validate_rating( $rating ) {
		$rating = absint( $rating );
		// Rating should be 0-5
		if ( $rating > 5 ) {
			return 0;
		}
		return $rating;
	}
	
	/**
	 * Validate language code
	 *
	 * @param string $language
	 * @return string
	 */
	protected function validate_language( $language ) {
		$language = sanitize_text_field( $language );
		// Basic validation for language codes (e.g., en, en_US, en-US)
		if ( ! preg_match( '/^[a-z]{2}([_-][A-Z]{2})?$/', $language ) || strlen( $language ) > 10 ) {
			return '';
		}
		return $language;
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
			return is_array( $decoded ) ? $decoded : array();
		}
		
		return is_array( $metadata ) ? $metadata : array();
	}
	
	/**
	 * Add message
	 *
	 * @param string $role
	 * @param string $content
	 * @param array $metadata
	 * @param string $message_id Optional message ID for OpenAI responses
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
	 * Check if message ID already exists
	 *
	 * @param string $message_id
	 * @return bool
	 */
	public function has_message_id( $message_id ) {
		foreach ( $this->messages as $message ) {
			if ( isset( $message['id'] ) && $message['id'] === $message_id ) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Get messages
	 *
	 * @return array
	 */
	public function get_messages() {
		return $this->messages;
	}
	
	/**
	 * Get messages as array
	 *
	 * @return array
	 */
	public function get_messages_array() {
		return $this->messages;
	}
	
	/**
	 * Get ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}
	
	/**
	 * Get chat ID
	 *
	 * @return string
	 */
	public function get_chat_id() {
		return $this->chat_id;
	}
	
	/**
	 * Get conversation ID
	 *
	 * @return string
	 */
	public function get_conversation_id() {
		return $this->conversation_id;
	}
	
	/**
	 * Set conversation ID
	 *
	 * @param string $conversation_id
	 */
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
		// Check if conversation is older than 25 days based on last update
		return ( time() - strtotime( $this->updated ) ) > ( 25 * DAY_IN_SECONDS );
	}
	
	/**
	 * Get response ID if not expired
	 *
	 * @return string|null
	 */
	public function get_valid_conversation_id() {
		if ( $this->is_conversation_expired() ) {
			return null;
		}
		return $this->conversation_id;
	}
	
	
	/**
	 * Set rating
	 *
	 * @param int $rating
	 */
	public function set_rating( $rating ) {
		$this->rating = absint( $rating );
		$this->updated = current_time( 'mysql' );
	}
	
	/**
	 * Get mode
	 *
	 * @return string
	 */
	public function get_mode() {
		return $this->mode;
	}
	
	/**
	 * Is search mode
	 *
	 * @return bool
	 */
	public function is_search() {
		return $this->mode === 'search';
	}
	
	/**
	 * Is chat mode
	 *
	 * @return bool
	 */
	public function is_chat() {
		return $this->mode === 'chat';
	}
	
	/**
	 * Get the last assistant message
	 *
	 * @return array|null Message array with id, role, content, timestamp, metadata or null if none found
	 */
	public function get_last_assistant_message() {
		// Iterate through messages in reverse to find the most recent assistant message
		for ( $i = count( $this->messages ) - 1; $i >= 0; $i-- ) {
			if ( isset( $this->messages[$i]['role'] ) && $this->messages[$i]['role'] === 'assistant' ) {
				return $this->messages[$i];
			}
		}
		return null;
	}
	
	/**
	 * Convert to array for database
	 *
	 * @return array
	 */
	public function to_db_array() {
		return array(
			'user_id'         => $this->user_id,
			'chat_id'         => $this->chat_id,
			'conversation_id' => $this->conversation_id,
			'mode'            => $this->mode,
			'title'           => $this->title,
			'messages'        => wp_json_encode( $this->get_messages_array() ),
			'model'           => $this->model,
			'vector_store_id' => $this->vector_store_id,
			'widget_id'       => $this->widget_id,
			'rating'          => $this->rating,
			'language'        => $this->language,
			'updated'         => $this->updated,
			'meta'            => wp_json_encode( $this->metadata )
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

	/**
	 * Get created timestamp
	 *
	 * @return string
	 */
	public function get_created() {
		return $this->created;
	}

	/**
	 * Get user ID
	 *
	 * @return int
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Get metadata
	 *
	 * @return array
	 */
	public function get_meta() {
		return $this->metadata;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}
	
	/**
	 * Get widget ID
	 *
	 * @return string
	 */
	public function get_widget_id() {
		return $this->widget_id;
	}
	
	/**
	 * Set widget ID
	 *
	 * @param string $widget_id
	 */
	public function set_widget_id( $widget_id ) {
		$this->widget_id = $this->validate_widget_id( $widget_id );
	}
}