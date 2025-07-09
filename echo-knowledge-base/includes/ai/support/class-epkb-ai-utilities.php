<?php defined( 'ABSPATH' ) || exit();

class EPKB_AI_Utilities {

	public static function add_log( $message, $context = array() ) {
		// Security: Only log if user has appropriate permissions or it's a system event
		if ( ! self::should_log() ) {
			return;
		}
		
		$option_name = 'epkb_ai_logs';
		$max_logs = 50;
		$max_logs_per_day = 30; // Maximum logs per day
		$max_message_length = 800;
		$max_context_size = 200;
		
		// Sanitize message to prevent any potential security issues
		$message = sanitize_text_field( $message );
		
		// Truncate message if too long
		if ( strlen( $message ) > $max_message_length ) {
			$message = substr( $message, 0, $max_message_length - 3 ) . '...';
		}
		
		// Sanitize and limit context
		$context = self::sanitize_log_context( $context, $max_context_size );
		
		// Get existing logs
		$logs = get_option( $option_name, array() );
		$current_date = current_time( 'Y-m-d' );
		
		// Count today's logs
		$daily_count = 0;
		foreach ( $logs as $log ) {
			if ( isset( $log['date'] ) && $log['date'] === $current_date ) {
				$daily_count++;
			}
		}
		
		if ( $daily_count >= $max_logs_per_day ) {
			return; // Daily limit reached
		}
		
		// Create log entry with minimal information
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'date' => $current_date,
			'message' => $message,
			'context' => $context,
			'hash' => substr( md5( $message . serialize( $context ) ), 0, 8 ) // For deduplication
		);
		
		// Check for duplicate entries (same hash within last hour)
		$one_hour_ago = strtotime( '-1 hour' );
		foreach ( array_reverse( $logs ) as $log ) {
			if ( isset( $log['hash'] ) && $log['hash'] === $log_entry['hash'] && 
			     strtotime( $log['timestamp'] ) > $one_hour_ago ) {
				return; // Skip duplicate
			}
		}
		
		// Add new log entry
		$logs[] = $log_entry;
		
		// Remove old logs
		$cutoff_date = date( 'Y-m-d', strtotime( '-20 days' ) );
		$logs = array_filter( $logs, function( $log ) use ( $cutoff_date ) {
			return isset( $log['date'] ) && $log['date'] >= $cutoff_date;
		} );
		
		// Keep only the most recent logs
		if ( count( $logs ) > $max_logs ) {
			$logs = array_slice( $logs, -$max_logs );
		}
		
		// Update option with autoload disabled for performance
		update_option( $option_name, $logs, false );
	}
	
	/**
	 * Check if logging should be enabled
	 *
	 * @return bool
	 */
	private static function should_log() {
		// Only log if debugging is enabled or user is admin
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}
		
		// Check if user has admin capabilities
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		
		// Check if it's a cron job or system event
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Sanitize log context to remove sensitive information
	 *
	 * @param array $context
	 * @param int $max_size
	 * @return array
	 */
	private static function sanitize_log_context( $context, $max_size ) {
		if ( ! is_array( $context ) ) {
			return array();
		}
		
		// Remove sensitive keys
		$sensitive_keys = array( 
			'password', 'pass', 'pwd', 'secret', 'token', 'key', 'api_key', 
			'auth', 'authorization', 'cookie', 'session', 'nonce', 'salt'
		);
		
		$sanitized = array();
		foreach ( $context as $key => $value ) {
			// Skip sensitive keys
			$lower_key = strtolower( $key );
			$skip = false;
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $lower_key, $sensitive ) !== false ) {
					$sanitized[ $key ] = '[REDACTED]';
					$skip = true;
					break;
				}
			}
			
			if ( $skip ) {
				continue;
			}
			
			// Sanitize values
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( substr( $value, 0, 100 ) );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = '[Array with ' . count( $value ) . ' items]';
			} else {
				$sanitized[ $key ] = '[' . gettype( $value ) . ']';
			}
		}
		
		// Check serialized size
		$serialized = serialize( $sanitized );
		if ( strlen( $serialized ) > $max_size ) {
			// Truncate to most important keys
			$important_keys = array( 'error_code', 'status', 'kb_id', 'user_id', 'action' );
			$truncated = array();
			foreach ( $important_keys as $key ) {
				if ( isset( $sanitized[ $key ] ) ) {
					$truncated[ $key ] = $sanitized[ $key ];
				}
			}
			$truncated['truncated'] = true;
			return $truncated;
		}
		
		return $sanitized;
	}

	public static function get_logs() {
		// Check permissions before returning logs
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		
		$logs = get_option( 'epkb_ai_logs', array() );
		
		// Additional filtering for display
		return array_map( function( $log ) {
			// Ensure no sensitive data is exposed
			if ( isset( $log['context'] ) && is_array( $log['context'] ) ) {
				$log['context'] = self::sanitize_log_context( $log['context'], 200 );
			}
			return $log;
		}, $logs );
	}

	public static function reset_logs() {
		// Check permissions before resetting logs
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		
		// Delete log option
		delete_option( 'epkb_ai_logs' );
		
		return true;
	}

	/**
	 * Remove potentially dangerous characters from the given string
	 *
	 * @param $text
	 * @return string
	 */
	public static function extra_sanitize_text_field( $text ) {
		return str_replace( array( '<', '>', '[', ']', '{', '}', '(', ')', '/', '|', '+', '=', '^' ), '', $text );
	}

	/**
	 * Generate uuid v4 output.
	 *
	 * @return string
	 */
	public static function generate_uuid_v4() {
		// Generate 16 random bytes.
		try {
			$data = random_bytes( 16 );
		} catch ( Exception $e ) {
			$data = openssl_random_pseudo_bytes( 16 );
		}

		// Set the version to 0100 (UUID version 4).
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );

		// Set the variant to 10 (variant 1).
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		// Convert binary data to hexadecimal string.
		$hex = bin2hex( $data );

		// Format as UUID: 8-4-4-4-12.
		$formatted_uuid = vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( $hex, 4 ) );

		return $formatted_uuid;
	}

	/**
	 * Validate UUID v4 format
	 *
	 * @param string $uuid UUID to validate
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	public static function validate_uuid( $uuid ) {
		if ( empty( $uuid ) ) {
			return new WP_Error( 'empty_uuid', __( 'UUID is empty', 'echo-knowledge-base' ) );
		}

		$uuid = trim( $uuid );

		// Regular expression to validate UUID v4 format
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		if ( preg_match( $pattern, $uuid ) !== 1 ) {
			return new WP_Error( 'invalid_uuid', sprintf( __( 'Invalid UUID format: %s', 'echo-knowledge-base' ), $uuid ) );
		}
		
		return true;
	}

	/**
	 * Check if AI Search feature is enabled
	 *
	 * @param int $kb_id KB ID to check (default: DEFAULT_KB_ID)
	 * @return bool True if AI Search is enabled
	 */
	public static function is_ai_search_enabled( $kb_id = EPKB_KB_Config_DB::DEFAULT_KB_ID ) {
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( $kb_id );
		
		if ( is_wp_error( $kb_config ) ) {
			return false;
		}
		
		return isset( $kb_config['ai_search_enabled'] ) && $kb_config['ai_search_enabled'] === 'on';
	}

	/**
	 * Check if AI Chat feature is enabled
	 *
	 * @param int $kb_id KB ID to check (default: DEFAULT_KB_ID)
	 * @return bool True if AI Chat is enabled
	 */
	public static function is_ai_chat_enabled( $kb_id = EPKB_KB_Config_DB::DEFAULT_KB_ID ) {

		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config_or_default( $kb_id );
		if ( is_wp_error( $kb_config ) ) {
			return false;
		}
		
		return isset( $kb_config['ai_chat_enabled'] ) && $kb_config['ai_chat_enabled'] === 'on';
	}

	/**
	 * Check if any AI features are enabled
	 *
	 * @param int $kb_id KB ID to check (default: DEFAULT_KB_ID)
	 * @return bool True if either AI Search or AI Chat is enabled
	 */
	public static function is_ai_enabled( $kb_id = EPKB_KB_Config_DB::DEFAULT_KB_ID ) {
		return self::is_ai_search_enabled( $kb_id ) || self::is_ai_chat_enabled( $kb_id );
	}

	/**
	 * Send chat error notification emails
	 *
	 * @param string $subject Subject for email.
	 * @param string $message Message text for email.
	 * @return void
	 */
	public static function send_ai_notification_email( $subject, $message ) {

		$current_date = gmdate( 'Y_m_d' );
		$maximum_notification_count = 10;

		// limit number of emails sent each day
		$error_notification_count   = get_transient( 'epkb_ai_error_notification_count_' . $current_date );
		if ( $error_notification_count === false ) {
			$error_notification_count = 0;
		} elseif ( $error_notification_count === $maximum_notification_count - 1 ) { // Last notification for today.
			$message .= esc_html__( 'No additional emails will be sent today. Check the Chat AI dashboard for more details.', 'echo-knowledge-base' );

		} elseif ( $error_notification_count >= $maximum_notification_count ) {
			return;
		}

		// send notification if email defined
		$email_ids = ''; // TODO epkb_get_instance()->kb_config_obj->get_value( EPKB_KB_Config_DB::DEFAULT_KB_ID, 'ai_chat_notification_email' );
		$email_ids = explode( ',', $email_ids );
		if ( empty( $email_ids ) ) {
			return;
		}

		// update the transient only if emails were sent
		$result = set_transient( 'epkb_ai_error_notification_count_' . $current_date, ++$error_notification_count, DAY_IN_SECONDS );
		if ( $result === false ) {
			EPKB_AI_Utilities::add_log( 'Error setting up error notification count transient', $current_date );

			// prevent sending too many notifications if failed to set the transient
			return;
		}

		foreach ( $email_ids as $email_id ) {
			$email_error = EPKB_Utilities::send_email( self::prepare_email_message_body(  $subject, $message, $email_id ), true, $email_id, '', '', $subject );
			if ( ! empty( $email_error ) ) {
				EPKB_AI_Utilities::add_log( $email_error, $email_id );
			}
		}
	}

	/**
	 * Prepare email message body for sending
	 * @param string $subject
	 * @param string $message
	 * @param string $email
	 * @return string
	 */
	public static function prepare_email_message_body( $subject, $message, $email ) {
		$email_message = '
				<html>
					<body>
						<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
							<tbody>
								<tr style="background-color:#EAF2FA;">
									<td colspan="2" style="font-family: sans-serif; font-size:12px;padding:3px;"><strong>' . esc_html__( 'Email', 'echo-knowledge-base' ) . '</strong></td>
			                    </tr>
			                    <tr style="background-color:#FFFFFF;">
									<td width="20" style="padding:3px;">&nbsp;</td>
									<td style="font-family: sans-serif; font-size:12px;padding:3px;"><a href="mailto:' . esc_html( $email ) . '">' . esc_html( $email ) . '</a></td>
			                    </tr>
			                    <tr style="background-color:#EAF2FA;">
									<td colspan="2" style="font-family: sans-serif; font-size:12px;padding:3px;"><strong>' . esc_html__( 'Subject', 'echo-knowledge-base' ) . '</strong></td>
			                    </tr>
			                    <tr style="background-color:#FFFFFF;">
									<td width="20" style="padding:3px;">&nbsp;</td>
									<td style="font-family: sans-serif; font-size:12px;padding:3px;">' . esc_html( $subject ) . '</td>
			                    </tr>
								<tr style="background-color:#EAF2FA">
									<td colspan="2" style="font-family: sans-serif; font-size:12px;padding:3px;"><strong>' . esc_html__( 'Message', 'echo-knowledge-base' ) . '</strong></td>
			                    </tr>
			                    <tr style="background-color:#FFFFFF;">
									<td width="20" style="padding:3px;">&nbsp;</td>
									<td style="font-family: sans-serif; font-size:12px;padding:3px;">' . wp_kses_post( $message ) . '<br /></td>
			                    </tr> 
							</tbody>
						</table>
					</body>
				</html>';

		return $email_message;
	}

	/**
	 * Ensure that user state is the same as the one who started the chat
	 *
	 * @param string $uuid Chat ID (UUID)
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	public static function validate_ai_user_matching( $uuid ) {
		$current_user_id = get_current_user_id();
		$handler = new EPKB_AI_Messages_DB();
		
		// Get the conversation by chat ID (UUID)
		$conversation = $handler->get_conversation_by_chat_id( $uuid );
		
		if ( $conversation === null ) {
			return new WP_Error( 'invalid_uuid_record', __( 'Chat record does not exist for this session', 'echo-knowledge-base' ) );
		}
		
		// Check if the user ID matches (logged off user is 0) or if user is admin
		if ( $conversation->get_user_id() !== $current_user_id ) {
			// Allow admins to access any conversation for support purposes
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'user_mismatch', __( 'You are not authorized to continue this chat session', 'echo-knowledge-base' ) );
			}
		}

		return true;
	}
	
	/**
	 * Run AI cleanup tasks immediately
	 * This can be called manually or via AJAX for immediate cleanup
	 *
	 * @return array Results of cleanup operations
	 */
	public static function run_cleanup_now() {
		$results = array(
			'expired_conversations' => 0,
			'deleted_conversations' => 0,
			'errors' => array()
		);
		
		// Only run if AI is enabled
		if ( ! self::is_ai_enabled() ) {
			$results['errors'][] = __( 'AI features are not enabled', 'echo-knowledge-base' );
			return $results;
		}
		
		try {
			$messages_db = new EPKB_AI_Messages_DB();
			
			// Note: mark_expired_conversations() method doesn't exist in EPKB_AI_Messages_DB
			// The delete_old_conversations() method below handles both marking and deletion
			
			// Delete old conversations
			$retention_days = apply_filters( 'epkb_ai_conversation_retention_days', 90 );
			$deleted_count = $messages_db->delete_old_conversations( $retention_days );
			if ( is_wp_error( $deleted_count ) ) {
				$results['errors'][] = sprintf( __( 'Failed to delete old conversations: %s', 'echo-knowledge-base' ), $deleted_count->get_error_message() );
			} else {
				$results['deleted_conversations'] = $deleted_count;
			}
			
			// Update last cleanup time
			set_transient( 'epkb_ai_last_cleanup', time(), DAY_IN_SECONDS );
			
		} catch ( Exception $e ) {
			$results['errors'][] = sprintf( __( 'Cleanup exception: %s', 'echo-knowledge-base' ), $e->getMessage() );
		}
		
		return $results;
	}

	/**
	 * Handle AI errors - log detailed error and send appropriate message for frontend
	 *
	 * @param WP_Error|string $error The error object or error message
	 * @param array $context Additional context for logging
	 * @param int $source Unique number identifying where the error occurred (for logging)
	 */
	public static function handle_ai_error_and_die( $error, $context = array(), $source = 0 ) {
		
		// Extract error details
		if ( is_wp_error( $error ) ) {
			$error_code = $error->get_error_code();
			$error_message = $error->get_error_message();
			$error_data = $error->get_error_data();
		} else {
			$error_code = is_string( $error ) ? $error : 'unknown_error';
			$error_message = is_string( $error ) ? $error : 'Unknown error occurred';
			$error_data = null;
		}
		
		// Map error codes to user-friendly messages
		$error_messages = array(
			'access_denied'        => __( 'Access denied. Please refresh the page and try again.', 'echo-knowledge-base' ),
			'empty_question'       => __( 'Please enter a question.', 'echo-knowledge-base' ),
			'empty_message'        => __( 'Please enter a message.', 'echo-knowledge-base' ),
			'ai_search_disabled'   => __( 'AI Search is currently unavailable.', 'echo-knowledge-base' ),
			'ai_chat_disabled'     => __( 'AI Chat is currently unavailable.', 'echo-knowledge-base' ),
			'ai_disabled'          => __( 'AI features are not enabled.', 'echo-knowledge-base' ),
			'rate_limit'           => __( 'Too many requests. Please wait a moment and try again.', 'echo-knowledge-base' ),
			'rate_limit_exceeded'  => __( 'Too many requests. Please wait a moment and try again.', 'echo-knowledge-base' ),
			'invalid_message'      => __( 'Please enter a valid message.', 'echo-knowledge-base' ),
			'invalid_session'      => __( 'Your session has expired. Please refresh the page.', 'echo-knowledge-base' ),
			'invalid_email'        => __( 'Invalid email address provided.', 'echo-knowledge-base' ),
			'invalid_url'          => __( 'Invalid URL format.', 'echo-knowledge-base' ),
			'invalid_url_scheme'   => __( 'Invalid URL scheme. Only HTTP and HTTPS are allowed.', 'echo-knowledge-base' ),
			'unsafe_url'           => __( 'Invalid or potentially unsafe URL.', 'echo-knowledge-base' ),
			'conversation_expired' => __( 'This conversation has expired. Please start a new conversation.', 'echo-knowledge-base' ),
			'api_error'            => __( 'Service temporarily unavailable. Please try again later.', 'echo-knowledge-base' ),
			'network_error'        => __( 'The AI service is temporarily unavailable. Please try again later.', 'echo-knowledge-base' ),
			'server_error'         => __( 'The AI service is temporarily unavailable. Please try again later.', 'echo-knowledge-base' ),
			'authentication_failed'=> __( 'Authentication failed. Please contact support.', 'echo-knowledge-base' ),
			'configuration_error'  => __( 'Configuration error. Please contact support.', 'echo-knowledge-base' ),
			'no_results'           => __( 'No relevant articles found. Please try a different question.', 'echo-knowledge-base' ),
			'no_posts'             => __( 'No posts found to upload. Please create some knowledge base articles first.', 'echo-knowledge-base' ),
			'insufficient_credits' => __( 'Daily limit reached. Please try again tomorrow.', 'echo-knowledge-base' ),
			'service_unavailable'  => __( 'Service temporarily unavailable. Please try again.', 'echo-knowledge-base' ),
		);
		
		// Get user-friendly message based on error code
		$user_message = isset( $error_messages[ $error_code ] ) 
			? $error_messages[ $error_code ] 
			: __( 'An error occurred. Please try again.', 'echo-knowledge-base' );
		
		// Prepare log context
		$log_context = array_merge( array(
			'error_code' => $error_code,
			'error_message' => $error_message,
			'source' => $source,
			'user_id' => get_current_user_id()
		), $context );
		
		// Add error data if available
		if ( ! empty( $error_data ) ) {
			$log_context['error_data'] = $error_data;
		}
		
		// Log the error
		self::add_log( 'AI Error: ' . $error_code, $log_context );
		
		// Prepare error response
		$error_response = array( 
			'error_code' => $error_code
		);
		
		// For admins, include the actual error message
		if ( current_user_can( 'manage_options' ) ) {
			$error_response['message'] = sprintf( 
				__( '[Admin Debug] %s', 'echo-knowledge-base' ), 
				$error_message 
			);
		} else {
			// For regular users, use the user-friendly message
			$error_response['message'] = $user_message;
		}
		
		// Send JSON error response and exit
		wp_send_json_error( $error_response );
	}
}
