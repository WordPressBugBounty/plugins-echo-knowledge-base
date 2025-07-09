<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Chat Security and Rate Limiting
 */
class EPKB_AI_Chat_Security {

	const RATE_LIMIT_TRANSIENT_PREFIX = 'epkb_ai_chat_rate_';
	const GLOBAL_RATE_LIMIT_TRANSIENT = 'epkb_ai_chat_global_rate';
	const SESSION_PREFIX = 'epkb_ai_chat_session_';	
	
	/**
	 * Check if user has exceeded rate limits
	 * 
	 * @param string $user_identifier - Can be user ID or IP hash
	 * @return bool|WP_Error - True if allowed, WP_Error if rate limited
	 */
	public static function check_rate_limit( $user_identifier = '' ) {
		
		// Get user identifier
		if ( empty( $user_identifier ) ) {
			$user_identifier = self::get_user_identifier();
		}
		
		// Check global rate limit first
		$global_limit = apply_filters( 'epkb_ai_chat_global_rate_limit', 1000 ); // 1000 requests per hour globally
		$global_count = get_transient( self::GLOBAL_RATE_LIMIT_TRANSIENT );
		
		if ( $global_count >= $global_limit ) {
			return new WP_Error( 'global_rate_limit', __( 'Chat service is temporarily unavailable due to high demand. Please try again later.', 'echo-knowledge-base' ) );
		}
		
		// Check user rate limit
		$user_limit = apply_filters( 'epkb_ai_chat_user_rate_limit', 50 ); // 50 requests per hour per user
		$user_transient = self::RATE_LIMIT_TRANSIENT_PREFIX . $user_identifier;
		$user_count = get_transient( $user_transient );
		
		if ( $user_count >= $user_limit ) {
			return new WP_Error( 'user_rate_limit', __( 'You have reached the chat limit. Please try again in an hour.', 'echo-knowledge-base' ) );
		}
		
		// Increment counters
		set_transient( self::GLOBAL_RATE_LIMIT_TRANSIENT, $global_count + 1, HOUR_IN_SECONDS );
		set_transient( $user_transient, $user_count + 1, HOUR_IN_SECONDS );
		
		return true;
	}
	
	/**
	 * Get unique user identifier for rate limiting
	 * 
	 * @return string
	 */
	private static function get_user_identifier() {
		
		// For logged-in users, use user ID
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}
		
		// For guests, use hashed IP (GDPR compliant)
		return 'ip_' . hash( 'sha256', self::get_client_ip() . wp_salt() );
	}
	
	/**
	 * Get client IP address
	 * 
	 * @return string
	 */
	private static function get_client_ip() {
		
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[$key] ) as $ip ) {
					$ip = trim( $ip );
					
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}
		
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}
	
	/**
	 * Validate and sanitize chat message
	 * 
	 * @param string $message
	 * @return string|WP_Error
	 */
	public static function validate_message( $message ) {
		
		// Check if empty
		if ( empty( $message ) ) {
			return new WP_Error( 'empty_message', __( 'Please enter a message.', 'echo-knowledge-base' ) );
		}
		
		// Check message length
		$max_length = apply_filters( 'epkb_ai_chat_max_message_length', 5000 ); // 5KB default
		if ( strlen( $message ) > $max_length ) {
			return new WP_Error( 'message_too_long', __( 'Message is too long. Please keep it under 5000 characters.', 'echo-knowledge-base' ) );
		}
		
		// Basic XSS prevention
		$message = wp_kses( $message, array() ); // Strip all HTML
		
		// Check for malicious patterns
		$blocked_patterns = apply_filters( 'epkb_ai_chat_blocked_patterns', array(
			'/\<script/i',
			'/javascript:/i',
			'/on\w+\s*=/i', // onclick, onload, etc.
			'/data:text\/html/i',
			'/vbscript:/i'
		) );
		
		foreach ( $blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return new WP_Error( 'invalid_content', __( 'Invalid content detected.', 'echo-knowledge-base' ) );
			}
		}
		
		return sanitize_textarea_field( $message );
	}

	/**
	 * Validate session ID
	 *
	 * @param string $session_id
	 * @return bool
	 */
	public static function validate_session_id_format( $session_id ) {
		return EPKB_AI_Utilities::validate_uuid( $session_id );
	}

	/**
	 * Generate secure session ID for chat
	 * 
	 * @return string
	 */
	public static function generate_session_id() {
		return EPKB_AI_Utilities::generate_uuid_v4();
	}

	/**
	 * Check if user can access a chat session
	 * 
	 * @param string $session_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function can_access_session( $session_id, $user_id = 0 ) {
		
		if ( empty( $session_id ) || ! self::validate_session_id_format( $session_id ) ) {
			return false;
		}
		
		// Admins can access any session
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		
		// Get session owner from transient
		$session_owner = get_transient( self::SESSION_PREFIX . $session_id );
		
		// If no owner found, it's a new or expired session
		if ( $session_owner === false ) {
			return true;
		}
		
		// Check ownership
		if ( $user_id > 0 ) {
			return (int) $session_owner === (int) $user_id;
		}
		
		// For guests, check IP hash
		$current_ip_hash = self::get_ip_hash();

		return $session_owner === $current_ip_hash;
	}
	
	/**
	 * Store session ownership
	 * 
	 * @param string $session_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function store_session_owner( $session_id, $user_id = 0 ) {
		
		if ( ! self::validate_session_id_format( $session_id ) ) {
			return false;
		}
		
		$owner = $user_id > 0 ? $user_id : self::get_ip_hash();
		$expiration = apply_filters( 'epkb_ai_chat_session_expiration', DAY_IN_SECONDS );
		
		return set_transient( self::SESSION_PREFIX . $session_id, $owner, $expiration );
	}

	/**
	 * Sanitize output for display
	 * 
	 * @param string $text
	 * @return string
	 */
	public static function sanitize_output( $text ) {
		
		// Allow basic formatting tags
		$allowed_tags = apply_filters( 'epkb_ai_chat_allowed_tags', array(
			'p' => array(),
			'br' => array(),
			'strong' => array(),
			'em' => array(),
			'u' => array(),
			'ol' => array(),
			'ul' => array(),
			'li' => array(),
			'code' => array(),
			'pre' => array(),
			'blockquote' => array(),
			'a' => array(
				'href' => array(),
				'title' => array(),
				'target' => array(),
				'rel' => array()
			)
		) );
		
		return wp_kses( $text, $allowed_tags );
	}
	
	/**
	 * Log security events
	 * 
	 * @param string $event_type
	 * @param array $data
	 */
	public static function log_security_event( $event_type, $data = array() ) {
		
		if ( ! apply_filters( 'epkb_ai_chat_log_security_events', true ) ) {
			return;
		}
		
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event' => $event_type,
			'ip_hash' => self::get_ip_hash(),
			'user_id' => get_current_user_id(),
			'data' => $data
		);
		
		// Store in transient with 7-day expiration
		$logs = get_transient( 'epkb_ai_chat_security_logs' );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		
		// Keep only last 100 entries
		if ( count( $logs ) >= 100 ) {
			array_shift( $logs );
		}
		
		$logs[] = $log_entry;
		set_transient( 'epkb_ai_chat_security_logs', $logs, WEEK_IN_SECONDS );
	}

	private static function get_ip_hash() {
		return hash( 'sha256', self::get_client_ip() . wp_salt() );
	}
}