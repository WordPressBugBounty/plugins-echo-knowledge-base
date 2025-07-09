<?php

/**
 * AI Configuration Management
 * 
 * Centralizes all AI-related configuration with validation and defaults.
 */
class EPKB_AI_Config {
	
	/**
	 * Configuration constants
	 */
	const DEFAULT_MODEL = 'gpt-4o';
	const DEFAULT_MAX_TOKENS = 1000;
	const DEFAULT_TEMPERATURE = 0.7;
	const DEFAULT_TIMEOUT = 120;
	const DEFAULT_UPLOAD_TIMEOUT = 300;
	const DEFAULT_MAX_RETRIES = 3;
	const DEFAULT_RETRY_DELAY = 1000; // milliseconds
	
	const MAX_FILE_SIZE = 1048576; // 1MB
	const MAX_METADATA_KEYS = 16;
	const MAX_METADATA_KEY_LENGTH = 64;
	const MAX_METADATA_VALUE_LENGTH = 512;
	
	const MAX_WIDGET_ID = 100; // Maximum widget ID sequence number
	
	const API_BASE_URL = 'https://api.openai.com';
	const API_VERSION = 'v1';
	
	/**
	 * Configuration data
	 * @var array
	 */
	private $config;
	
	/**
	 * Constructor
	 *
	 * @param array $config Configuration overrides
	 */
	public function __construct( $config = array() ) {
		$this->config = $this->validate_config( $config );
	}
	
	/**
	 * Validate and merge configuration with defaults
	 *
	 * @param array $config
	 * @return array
	 */
	private function validate_config( $config ) {
		
		$defaults = array(
			'api_key'         => $this->get_api_key_from_options(),
			'organization_id' => $this->get_organization_id_from_options(),
			'model'           => self::DEFAULT_MODEL,
			'max_tokens'      => self::DEFAULT_MAX_TOKENS,
			'temperature'     => self::DEFAULT_TEMPERATURE,
			'timeout'         => self::DEFAULT_TIMEOUT,
			'upload_timeout'  => self::DEFAULT_UPLOAD_TIMEOUT,
			'max_retries'     => self::DEFAULT_MAX_RETRIES,
			'retry_delay'     => self::DEFAULT_RETRY_DELAY,
			'ssl_verify'      => true,
			'debug_mode'      => false,
			'cache_enabled'   => true,
			'cache_ttl'       => 3600, // 1 hour
		);
		
		$config = wp_parse_args( $config, $defaults );
		
		// Validate specific values
		$config['max_tokens'] = $this->validate_max_tokens( $config['max_tokens'] );
		$config['temperature'] = $this->validate_temperature( $config['temperature'] );
		$config['timeout'] = absint( $config['timeout'] );
		$config['max_retries'] = absint( $config['max_retries'] );
		
		return $config;
	}
	
	/**
	 * Get API key from WordPress options
	 *
	 * @return string
	 */
	private function get_api_key_from_options() {
		return self::get_api_key();
	}
	
	/**
	 * Get organization ID from WordPress options
	 *
	 * @return string
	 */
	private function get_organization_id_from_options() {
		return get_option( 'epkb_openai_organization_id', '' );
	}
	
	/**
	 * Validate max tokens value
	 *
	 * @param int $max_tokens
	 * @return int
	 */
	private function validate_max_tokens( $max_tokens ) {
		$max_tokens = absint( $max_tokens );
		
		// Ensure within reasonable bounds
		if ( $max_tokens < 1 ) {
			return 1;
		}
		
		if ( $max_tokens > 4096 ) {
			return 4096;
		}
		
		return $max_tokens;
	}
	
	/**
	 * Validate temperature value
	 *
	 * @param float $temperature
	 * @return float
	 */
	private function validate_temperature( $temperature ) {
		$temperature = floatval( $temperature );
		
		// Ensure within valid range
		if ( $temperature < 0 ) {
			return 0;
		}
		
		if ( $temperature > 2 ) {
			return 2;
		}
		
		return $temperature;
	}
	
	/**
	 * Get configuration value
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $default;
	}
	
	/**
	 * Set configuration value
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set( $key, $value ) {
		$this->config[ $key ] = $value;
	}
	
	/**
	 * Get organization ID
	 *
	 * @return string
	 */
	public function get_organization_id() {
		return $this->config['organization_id'];
	}
	
	/**
	 * Get model
	 *
	 * @return string
	 */
	public function get_model() {
		return $this->config['model'];
	}
	
	/**
	 * Get max tokens
	 *
	 * @return int
	 */
	public function get_max_tokens() {
		return $this->config['max_tokens'];
	}
	
	/**
	 * Get temperature
	 *
	 * @return float
	 */
	public function get_temperature() {
		return $this->config['temperature'];
	}
	
	/**
	 * Get timeout
	 *
	 * @return int
	 */
	public function get_timeout() {
		return $this->config['timeout'];
	}
	
	/**
	 * Get upload timeout
	 *
	 * @return int
	 */
	public function get_upload_timeout() {
		return $this->config['upload_timeout'];
	}
	
	/**
	 * Get max retries
	 *
	 * @return int
	 */
	public function get_max_retries() {
		return $this->config['max_retries'];
	}
	
	/**
	 * Get retry delay
	 *
	 * @return int
	 */
	public function get_retry_delay() {
		return $this->config['retry_delay'];
	}
	
	/**
	 * Get SSL verify setting
	 *
	 * @return bool
	 */
	public function get_ssl_verify() {
		return $this->config['ssl_verify'];
	}
	
	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	public function is_debug_mode() {
		return $this->config['debug_mode'];
	}
	
	/**
	 * Check if cache is enabled
	 *
	 * @return bool
	 */
	public function is_cache_enabled() {
		return $this->config['cache_enabled'];
	}
	
	/**
	 * Get cache TTL
	 *
	 * @return int
	 */
	public function get_cache_ttl() {
		return $this->config['cache_ttl'];
	}
	
	/**
	 * Get API URL
	 *
	 * @return string
	 */
	public function get_api_url() {
		return self::API_BASE_URL . '/' . self::API_VERSION;
	}
	
	/**
	 * Get all configuration
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->config;
	}
	
	/**
	 * Get API key from WordPress options (static)
	 *
	 * @return string
	 */
	public static function get_api_key() {
		$encrypted_key = get_option( 'epkb_openai_api_key', '' );
		
		if ( empty( $encrypted_key ) ) {
			// Check for old option name (migration)
			$old_key = get_option( 'epkb_openai_key', '' );
			if ( ! empty( $old_key ) ) {
				$decrypted = EPKB_Utilities::decrypt_data( $old_key );
				if ( $decrypted !== false && ! empty( $decrypted ) ) {
					// Migrate to new option name
					self::save_api_key( $decrypted );
					delete_option( 'epkb_openai_key' );
					return $decrypted;
				}
			}
			return '';
		}
		
		// Use existing EPKB_Utilities encryption/decryption
		$decrypted = EPKB_Utilities::decrypt_data( $encrypted_key );
		return $decrypted !== false ? $decrypted : '';
	}
	
	/**
	 * Save API key to WordPress options (encrypted)
	 *
	 * @param string $api_key
	 * @return bool|WP_Error
	 */
	public static function save_api_key( $api_key ) {
		
		// Validate API key format
		if ( ! self::validate_api_key_format( $api_key ) ) {
			return new WP_Error( 'invalid_format', __( 'Invalid API key format', 'echo-knowledge-base' ) );
		}
		
		// Encrypt the API key using existing EPKB_Utilities method
		$encrypted_key = EPKB_Utilities::encrypt_data( $api_key );
		
		if ( $encrypted_key === false ) {
			return new WP_Error( 'encryption_failed', __( 'Failed to encrypt API key', 'echo-knowledge-base' ) );
		}
		
		$result = update_option( 'epkb_openai_api_key', $encrypted_key );
		
		return $result ? true : new WP_Error( 'save_failed', __( 'Failed to save API key', 'echo-knowledge-base' ) );
	}
	
	/**
	 * Validate metadata according to OpenAI limits
	 *
	 * @param array $metadata
	 * @return array
	 */
	public function validate_metadata( $metadata ) {
		
		if ( empty( $metadata) || ! is_array( $metadata ) ) {
			return array();
		}
		
		$validated = array();
		$count = 0;
		
		foreach ( $metadata as $key => $value ) {
			// Limit to max keys
			if ( $count >= self::MAX_METADATA_KEYS ) {
				break;
			}
			
			// Validate key
			$key = sanitize_key( substr( $key, 0, self::MAX_METADATA_KEY_LENGTH ) );
			if ( empty( $key ) ) {
				continue;
			}
			
			// Convert boolean values to string representation
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			
			// Convert other non-string types to string
			if ( ! is_string( $value ) ) {
				$value = strval( $value );
			}
			
			// Validate value
			$value = substr( sanitize_text_field( $value ), 0, self::MAX_METADATA_VALUE_LENGTH );
			if ( empty( $value ) && $value !== '0' ) {
				continue;
			}
			
			$validated[ $key ] = $value;
			$count++;
		}
		
		return $validated;
	}
	
	/**
	 * Validate API key format
	 *
	 * @param string $api_key
	 * @return bool
	 */
	private static function validate_api_key_format( $api_key ) {
		// OpenAI API keys typically start with 'sk-' and are alphanumeric
		if ( empty( $api_key ) || ! is_string( $api_key ) ) {
			return false;
		}
		
		// Basic format validation
		if ( ! preg_match( '/^sk-[\w\-]+$/i', $api_key ) ) {
			return false;
		}
		
		// Check reasonable length (OpenAI keys are typically 40-60 chars)
		$key_length = strlen( $api_key );
		if ( $key_length < 20 || $key_length > 500 ) {
			return false;
		}
		
		return true;
	}
	
}