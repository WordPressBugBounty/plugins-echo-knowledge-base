<?php defined( 'ABSPATH' ) || exit();

class EPKB_AI_Utilities {

	const AI_PRO_NOTES_POST_TYPE = 'aipro_ai_note';
	const DEFAULT_TIMEOUT = 300;
	const DEFAULT_MINIMUM_EXECUTION_TIME_LIMIT = 60; // seconds

	/**
	 * Generate uuid v4 output.
	 *
	 * @return string
	 */
	public static function generate_uuid_v4() {
		// Try multiple methods for generating secure random data
		$bytes = false;
		
		// Method 1: random_bytes (most secure, PHP 7+)
		if ( function_exists( 'random_bytes' ) ) {
			try {
				$bytes = random_bytes( 16 );
			} catch ( Exception $e ) {
				$bytes = false;
			}
		}
		
		// Method 2: openssl_random_pseudo_bytes (widely compatible, PHP 5.3+)
		if ( $bytes === false && function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$strong = false;
			$bytes = openssl_random_pseudo_bytes( 16, $strong );
			// Only use if cryptographically strong
			if ( ! $strong ) {
				$bytes = false;
			}
		}
		
		// Method 3: Fallback using multiple entropy sources
		if ( $bytes === false ) {
			// Combine multiple sources of entropy
			$entropy = uniqid( '', true );                    // Microsecond precision
			$entropy .= wp_rand();                            // WordPress random
			$entropy .= microtime( true );                    // Current time with microseconds
			$entropy .= serialize( $_SERVER );                // Server variables
			if ( function_exists( 'wp_salt' ) ) {
				$entropy .= wp_salt( 'auth' );                // WordPress salt if available
			}
			
			// Hash the combined entropy and take first 16 bytes
			$bytes = substr( hash( 'sha256', $entropy, true ), 0, 16 );
		}
		
		// Set UUID v4 version and variant bits
		$bytes[6] = chr( ord( $bytes[6] ) & 0x0f | 0x40 ); // version 4
		$bytes[8] = chr( ord( $bytes[8] ) & 0x3f | 0x80 ); // variant 10
		
		// Format as UUID string
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}

	/**
	 * Check if AI Search feature is enabled
	 *
	 * @return bool True if AI Search is enabled (on or preview mode for admins)
	 */
	public static function is_ai_search_enabled() {
		$enabled = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_enabled', 'off' );
		return $enabled != 'off';
	}

	public static function is_ai_search_simple_enabled() {
		return self::is_ai_search_enabled_for_frontend() && EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_mode' ) === 'simple_search';
	}

	public static function is_ai_search_smart_enabled( $skip_preview_check = false ) {
		if ( ! self::is_ai_search_enabled_for_frontend( $skip_preview_check ) ) {
			return false;
		}
		$mode = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_mode' );

		// Handle legacy 'advanced_search' value - try to update it; TODO: remove after v16
		if ( $mode === 'advanced_search' ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_search_mode', 'smart_search' );
			return true;
		}

		return $mode === 'smart_search';
	}

	// TODO: remove this method after v16 - kept for backward compatibility
	public static function is_ai_search_advanced_enabled( $skip_preview_check = false ) {
		return self::is_ai_search_smart_enabled( $skip_preview_check );
	}

	/**
	 * Check if AI Search feature is enabled for frontend; used by ASEA
	 *
	 * @return bool True if AI Search is enabled for frontend
	 */
	public static function is_ai_search_enabled_for_frontend( $skip_preview_check = false ) {
		if ( ! self::is_ai_search_enabled() || ( EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_enabled' ) == 'preview' && ! $skip_preview_check && ( ! function_exists('wp_get_current_user') || ! current_user_can( 'manage_options' ) ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if AI Chat feature is enabled
	 *
	 * @return bool True if AI Chat is enabled (on or preview mode for admins)
	 */
	public static function is_ai_chat_enabled() {
		$ai_chat_enabled = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_enabled', 'off' );
		
		// Check if chat is enabled
		return $ai_chat_enabled != 'off';
	}

	/**
	 * Check if any AI features are enabled (including preview mode)
	 *
	 * @return bool True if either AI Search or AI Chat is not 'off'
	 */
	public static function is_ai_chat_or_search_enabled() {
		return self::is_ai_search_enabled() || self::is_ai_chat_enabled();
	}

	/**
	 * Check if AI is configured with API credentials (has API key and terms accepted)
	 * This is the base requirement for any AI feature, regardless of whether AI Chat or AI Search are enabled.
	 *
	 * @return bool True if API key is set and terms are accepted
	 */
	public static function is_ai_configured() {
		$ai_key = EPKB_AI_Config_Specs::get_unmasked_api_key_for_provider( EPKB_AI_Provider::get_active_provider() );
		$disclaimer_accepted = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_disclaimer_accepted', 'off' );

		return ! empty( $ai_key ) && $disclaimer_accepted === 'on';
	}

	/**
	 * Check if AI Features Pro is enabled
	 *
	 * @return bool True if AI Features Pro is enabled
	 */
	public static function is_ai_features_pro_enabled() {
		return defined( 'AI_FEATURES_PRO_PLUGIN_NAME' );
	}

	/**
	 * Send chat error notification emails
	 *
	 * @param string $subject Subject for email.
	 * @param string $message Message text for email.
	 * @return bool|WP_Error True on success, WP_Error on failure
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
			return new WP_Error( 'daily_limit_reached', __( 'Daily email notification limit reached', 'echo-knowledge-base' ) );
		}

		// send notification if email defined
		$email_ids = ''; // TODO
		$email_ids = explode( ',', $email_ids );
		if ( empty( $email_ids ) ) {
			return new WP_Error( 'no_recipients', __( 'No email recipients configured', 'echo-knowledge-base' ) );
		}

		// update the transient only if emails were sent
		$result = set_transient( 'epkb_ai_error_notification_count_' . $current_date, ++$error_notification_count, DAY_IN_SECONDS );
		if ( $result === false ) {
			// prevent sending too many notifications if failed to set the transient
			return new WP_Error( 'transient_error', __( 'Failed to update notification count', 'echo-knowledge-base' ) );
		}

		$errors = array();
		foreach ( $email_ids as $email_id ) {
			$email_error = EPKB_Utilities::send_email( self::prepare_email_message_body(  $subject, $message, $email_id ), true, $email_id, '', '', $subject );
			if ( ! empty( $email_error ) ) {
				// translators: %1$s is the email address, %2$s is the error message
				$errors[] = sprintf( __( 'Failed to send email to %1$s: %2$s', 'echo-knowledge-base' ), $email_id, $email_error );
			}
		}

		// Return error if all emails failed
		if ( count( $errors ) == count( $email_ids ) ) {
			return new WP_Error( 'all_emails_failed', implode( '; ', $errors ) );
		}

		// Return true if at least one email was sent
		return true;
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
	 * Get available post types for vector store sync
	 *
	 * @return array
	 */
	public static function get_available_post_types_for_ai() {
		$post_types = array();

		// Add KB post types (list all when Multiple KBs is active; otherwise stop after the first)
		$all_kb_configs = epkb_get_instance()->kb_config_obj->get_kb_configs();
		$show_all_kbs = EPKB_Utilities::is_multiple_kbs_enabled();
		foreach ( $all_kb_configs as $kb_config ) {
			// Skip archived KBs
			if ( isset( $kb_config['status'] ) && $kb_config['status'] === EPKB_KB_Config_Specs::ARCHIVED ) {
				continue;
			}

			$kb_id = $kb_config['id'];
			$kb_post_type = EPKB_KB_Handler::get_post_type( $kb_id );
			// translators: %d is the Knowledge Base ID
			$kb_name = isset( $kb_config['kb_name'] ) ? $kb_config['kb_name'] : sprintf( __( 'Knowledge Base %d', 'echo-knowledge-base' ), $kb_id );
			$post_types[ $kb_post_type ] = $kb_name;
			if ( ! $show_all_kbs ) { break; }
		}

		// Always show Posts, Pages, and Notes in the list (require AI Pro to use)
		$post_types['post'] = __( 'Posts', 'echo-knowledge-base' );
		$post_types['page'] = __( 'Pages', 'echo-knowledge-base' );
		$post_types[ self::AI_PRO_NOTES_POST_TYPE ] = __( 'Additional Notes', 'echo-knowledge-base' );

		// Add other public CPTs (require AI Pro to use)
		$public_cpts = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $public_cpts as $slug => $obj ) {
			// Skip post types we already handle
			if ( $slug === 'post' || $slug === 'page' || $slug === 'attachment' || $slug === self::AI_PRO_NOTES_POST_TYPE ) {
				continue;
			}
			// Skip KB post types (already added above)
			if ( EPKB_KB_Handler::is_kb_post_type( $slug ) ) {
				continue;
			}
			$label = isset( $obj->labels->name ) ? $obj->labels->name : ucfirst( $slug );
			$post_types[ $slug ] = $label;
		}

		return $post_types;
	}

	/**
	 * Get AI Training Data Collection options for dropdowns/selects
	 * Returns an array of collection_id => collection_name pairs
	 *
	 * @param string $format Optional format: 'simple' (default) returns key => value, 'block' returns array with key/name/style
	 * @return array Collection options in the requested format
	 */
	public static function get_collection_options( $format = 'simple' ) {
		// Start with "Select Collection" option for value 0
		$select_collection_label = __( 'Select Collection', 'echo-knowledge-base' );
		if ( $format === 'block' ) {
			$options = array(
				array(
					'key' => 0,
					'name' => $select_collection_label,
					'style' => array(),
				)
			);
		} else {
			$options = array( 0 => $select_collection_label );
		}

		// Get all training data collections
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		if ( ! is_wp_error( $collections ) && ! empty( $collections ) ) {
			foreach ( $collections as $collection_id => $collection_config ) {
				$collection_name = isset( $collection_config['ai_training_data_store_name'] )
					? $collection_config['ai_training_data_store_name']
					: EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id );

				if ( $format === 'block' ) {
					$options[] = array(
						'key' => (int)$collection_id,
						'name' => $collection_name,
						'style' => array(),
					);
				} else {
					$options[ $collection_id ] = $collection_name;
				}
			}
		}

		return $options;
	}

	/**
	 * Sleep safely but never longer than MAX_SLEEP seconds.
	 *
	 * @param float $seconds  The requested delay.
	 * @param float $max      The absolute cap (default 30 s).
	 */
	public static function safe_sleep( $seconds, $max = 30.0 ) {
		// Return early if seconds is negative or zero
		if ( $seconds <= 0 ) {
			return;
		}
		
		// Cap seconds at max if too large
		if ( $seconds > $max ) {
			$seconds = $max;
		}
		
		if ( $seconds < 1 ) {
			usleep( (int) ( $seconds * 1000000 ) );
		} else {
			sleep( (int) round( $seconds ) );
		}

	}

	/**
	 * Get client IP address (hashed for privacy)
	 *
	 * @return string Hashed IP address or empty string
	 */
	public static function get_hashed_ip() {
		$ip_keys = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'HTTP_X_REAL_IP',
			'HTTP_CF_CONNECTING_IP',
			'REMOTE_ADDR'
		);

		$raw_ip = '';

		// First check for public IP addresses (ignore private/reserved ranges)
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[$key] ) ) {
				$ip = sanitize_text_field( $_SERVER[$key] );

				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}

				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$raw_ip = $ip;
					break;
				}
			}
		}

		// If no valid public IP found, check for any valid IP (including private)
		if ( empty( $raw_ip ) ) {
			foreach ( $ip_keys as $key ) {
				if ( ! empty( $_SERVER[$key] ) ) {
					$ip = sanitize_text_field( $_SERVER[$key] );

					// Handle comma-separated IPs (from proxies)
					if ( strpos( $ip, ',' ) !== false ) {
						$ips = explode( ',', $ip );
						$ip = trim( $ips[0] );
					}

					// Validate any IP address
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						$raw_ip = $ip;
						break;
					}
				}
			}
		}

		// Hash the IP address for privacy (GDPR compliance)
		if ( ! empty( $raw_ip ) ) {
			// Use a consistent salt for the same IP to produce the same hash
			// This allows rate limiting while preserving privacy
			return wp_hash( $raw_ip . wp_salt() );
		}

		return '';
	}

	/**
	 * Enqueue AI search scripts when a search component is rendered.
	 * Call this from any component that renders a search box with AI capabilities.
	 * Note: Inline script data is pre-attached during script registration in scripts-registration-public.php
	 */
	public static function enqueue_ai_search_scripts() {
		static $scripts_enqueued = false;

		if ( ! self::is_ai_search_enabled() || $scripts_enqueued ) {
			return;
		}

		wp_enqueue_script( 'epkb-ai-chat-util' );
		wp_enqueue_script( 'epkb-marked' );
		wp_enqueue_script( 'epkb-ai-search' );

		$scripts_enqueued = true;

		// Also enqueue search results if smart search enabled
		if ( self::is_ai_search_smart_enabled() ) {
			self::enqueue_ai_search_results_scripts();
		}
	}

	/**
	 * Enqueue Advanced AI Search Results scripts and styles.
	 * Note: Inline script data is pre-attached during script registration in scripts-registration-public.php
	 */
	public static function enqueue_ai_search_results_scripts() {
		static $results_enqueued = false;

		if ( $results_enqueued ) {
			return;
		}

		wp_enqueue_style( 'epkb-ai-search-results' );
		wp_enqueue_script( 'epkb-ai-search-results' );

		$results_enqueued = true;
	}

	/**
	 * Determine if an AI search answer is the configured refusal message.
	 *
	 * @param string $answer
	 * @return bool
	 */
	public static function is_search_refusal_answer( $answer ) {
		if ( empty( $answer ) ) {
			return false;
		}

		$normalized = wp_strip_all_tags( $answer );
		return stripos( $normalized, EPKB_AI_Config_Specs::get_ai_refusal_message() ) !== false;
	}

	/**
	 * Calculate exponential backoff delay
	 *
	 * @param int $retry_count Current retry attempt (0-based)
	 * @param int $base_delay Base delay in seconds
	 * @param int $max_delay Maximum delay in seconds
	 * @param WP_Error|null $error Optional error object that may contain retry-after header
	 * @return int Delay in seconds
	 */
	public static function calculate_backoff_delay( $retry_count, $base_delay = 1, $max_delay = 60, $error = null ) {
		$server_hint_delay = 0;

		// Calculate exponential backoff with jitter
		$exponential_delay = $base_delay * pow( 2, $retry_count );
		// Add jitter (0-25% of delay)
		$jitter = $exponential_delay * ( wp_rand( 0, 25 ) / 100 );
		$calculated_delay = $exponential_delay + $jitter;

		// Check for Retry-After header in error data (highest priority)
		if ( is_wp_error( $error ) ) {
			$error_data = $error->get_error_data();
			if ( ! empty( $error_data['retry_after'] ) ) {
				// Retry-After can be seconds or HTTP date
				$retry_after = $error_data['retry_after'];
				if ( is_numeric( $retry_after ) ) {
					// It's seconds
					$server_hint_delay = intval( $retry_after );
				} else {
					// It's an HTTP date, parse it
					$retry_time = strtotime( $retry_after );
					if ( $retry_time !== false ) {
						$server_hint_delay = max( 0, $retry_time - time() );
					}
				}
			}
		}

		// Check for X-RateLimit-Reset from transient (second priority)
		if ( $server_hint_delay === 0 ) {
			$rate_limit_info = get_transient( 'epkb_openai_rate_limit' );
			if ( ! empty( $rate_limit_info['reset_in'] ) && $rate_limit_info['remaining'] === 0 ) {
				// Use actual reset time from OpenAI headers
				$server_hint_delay = $rate_limit_info['reset_in'] + 1;
			}
		}

		// Use the maximum of calculated backoff and server hint
		$delay = max( $calculated_delay, $server_hint_delay );

		// Cap at max delay
		return min( $delay, $max_delay );
	}

	/**
	 * Check if API error is retryable
	 *
	 * This determines whether the server should retry a failed API request.
	 * Note: This is different from EPKB_AI_Log::is_retryable_error() which determines
	 * if the client should retry a request to our REST API.
	 *
	 * @param WP_Error $error
	 * @return bool
	 */
	public static function is_retryable_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$error_code = $error->get_error_code();
		$error_data = $error->get_error_data();
		$http_code = null;

		// Extract HTTP status code if available
		if ( isset( $error_data['response']['code'] ) ) {
			$http_code = $error_data['response']['code'];
		}

		// Use centralized logic from EPKB_AI_Log for consistency
		$is_retryable = EPKB_AI_Log::is_retryable_error( $error_code, $http_code );

		// - Don't retry 401/403 auth errors (API key issues)
		if ( $http_code === 401 || $http_code === 403 ) {
			return false;
		}

		if ( $error_code === 'json_encode_error' ) {
			return false;
		}

		// - Don't retry if execution_time_too_low (won't be fixed by retrying)
		if ( $error_code === 'execution_time_too_low' ) {
			return false;
		}

		// - Don't retry insufficient_quota errors (billing issues) or invalid API key
		if ( $error_code === 'insufficient_quota' || $error_code === 'invalid_api_key' ) {
			return false;
		}

		// - Don't retry incomplete responses (won't be fixed by retrying)
		if ( $error_code === 'response_incomplete' ) {
			return false;
		}

		return $is_retryable;
	}

	/**
	 * Convert kb_article file references to markdown links with article titles
	 * This converts the file names we generate when uploading to AI storage back to readable links
	 *
	 * @param string $content Content that may contain kb_article references
	 * @return string Content with references converted to links
	 */
	public static function convert_kb_article_references_to_links( $content ) {

		// Remove the †turn0file2 pattern and similar artifacts only when kb_article_ is found
		// Pattern matches optional † followed by turn, number, file, number
		$content = preg_replace('/†?turn\d+file\d+/u', '', $content);

		// Quick check - if content doesn't contain kb_article_, no need to process
		if ( strpos( $content, 'kb_article_' ) === false ) {
			return $content;
		}

		// Pattern: kb_article_[postId]_[timestamp].txt
		// This matches the format we use in upload_file() method
		$pattern = '/kb_article_(\d+)_\d+\.txt/';

		// Find all matches
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$file_reference = $match[0];
				$post_id = $match[1];

				// Get the post to retrieve its title
				$post = get_post( $post_id );
				if ( $post ) {
					// Get the article URL
					$article_url = get_permalink( $post_id );
					// Get the article title
					$article_title = get_the_title( $post );

					// Create HTML link that opens in new tab
					// Using HTML directly since marked.js passes through HTML
					$link = '<a href="' . esc_url( $article_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $article_title ) . '</a>';

					// Replace the file reference with the link
					$content = str_replace( $file_reference, $link, $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Extract source article references from Gemini grounding metadata
	 *
	 * Gemini File Search returns groundingMetadata with groundingChunks that contain
	 * references to the documents used to generate the response. This extracts those
	 * references and converts them to article links.
	 *
	 * @param array $response Raw API response from Gemini
	 * @return array Array of source articles with post_id, title, url
	 */
	public static function extract_sources_from_grounding_metadata( $response ) {
		$sources = array();
		$seen_ids = array();

		// Check for Gemini grounding metadata
		if ( empty( $response['candidates'][0]['groundingMetadata']['groundingChunks'] ) ) {
			return $sources;
		}

		foreach ( $response['candidates'][0]['groundingMetadata']['groundingChunks'] as $chunk ) {
			// File Search uses 'retrievedContext', Google Search uses 'web'
			$context = isset( $chunk['retrievedContext'] ) ? $chunk['retrievedContext'] : null;
			if ( ! $context || empty( $context['title'] ) ) {
				continue;
			}

			// Parse post_id from file title: kb_article_{postId}_{timestamp}.txt or kb_page_{postId}_{timestamp}.txt
			if ( ! preg_match( '/kb_(?:article|page|post)_(\d+)_\d+/', $context['title'], $matches ) ) {
				continue;
			}

			$post_id = intval( $matches[1] );

			// Skip duplicates
			if ( isset( $seen_ids[ $post_id ] ) ) {
				continue;
			}
			$seen_ids[ $post_id ] = true;

			// Get CURRENT article info from WordPress (not stale DB data)
			$post = get_post( $post_id );
			if ( ! $post || $post->post_status !== 'publish' ) {
				continue; // Skip deleted or unpublished articles
			}

			$sources[] = array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post_id )
			);
		}

		return $sources;
	}

	/**
	 * Get timeout for a specific purpose with execution time safety check
	 *
	 * @param string $purpose Purpose of the request (e.g., 'content_analysis', 'chat', 'search', 'general')
	 * @return int Timeout in seconds
	 */
	public static function get_timeout_for_purpose( $purpose ) {

		// Determine ideal timeout based on purpose
		$ideal_timeout = self::DEFAULT_TIMEOUT;
		switch ( $purpose ) {
			case 'content_analysis_gap_analysis':
			case 'content_analysis_tag_suggestions':
			case 'content_analysis':
				// Content analysis needs longer timeout
				$ideal_timeout = 120;
				break;
			case 'chat':
			case 'search':
			case 'general':
			default:
				break;
		}

		// Ensure execution time is sufficient and get safe timeout
		$safe_timeout = self::ensure_execution_time( $ideal_timeout, array( 'purpose' => $purpose ) );

		return $safe_timeout;
	}

	/**
	 * Ensure sufficient PHP execution time for long-running API calls
	 * Attempts to set execution time to desired limit and validates it meets minimum requirements
	 *
	 * @param int $desired_limit Desired execution time limit in seconds (default: 120)
	 * @param array $context Optional context for logging (e.g., article_id, analysis_type)
	 * @return int Safe timeout in seconds, WP_Error if too low
	 */
	private static function ensure_execution_time( $desired_limit = 120, $context = array() ) {

		$minimum_limit = self::DEFAULT_MINIMUM_EXECUTION_TIME_LIMIT;  // seconds
		$current_limit = ini_get( 'max_execution_time' );
		if ( $current_limit == 0 ) {
			return $desired_limit - 10;
		}

		// Check if current limit is too low
		if ( $current_limit < $minimum_limit ) {

			// Try to increase it
			@set_time_limit( $desired_limit );
			$new_limit = ini_get( 'max_execution_time' );

			// Check if we succeeded
			/* if ( $new_limit < $minimum_limit && $new_limit != 0 ) {
				EPKB_AI_Log::add_log( 'PHP execution time limit is too low for AI operations', array_merge( array(
					'current_limit' => $new_limit,
					'minimum_required' => $minimum_limit,
					'desired_limit' => $desired_limit,
					'set_time_limit_failed' => true
				), $context ) );

				return new WP_Error( 'execution_time_too_low',
					sprintf( __( 'PHP execution time limit is too low (%d seconds). Minimum required: %d seconds. Please increase max_execution_time in php.ini or wp-config.php.', 'echo-knowledge-base' ),
						$new_limit, $minimum_limit
					),
					array(
						'current_limit' => $new_limit,
						'minimum_required' => $minimum_limit,
						'desired_limit' => $desired_limit
					)
				);
			} */

			$final_limit = $new_limit;

		} else if ( $current_limit < $desired_limit ) {

			// Current limit is sufficient - try to increase to desired if possible
			@set_time_limit( $desired_limit );
			$new_limit = ini_get( 'max_execution_time' );
			$final_limit = $new_limit;

		} else {
			// Current limit is already >= desired
			$final_limit = $current_limit;
		}

		// Calculate safe HTTP timeout (10 seconds less than execution limit)
		$safe_timeout = $final_limit == 0 ? ( $desired_limit - 10 ) : max( 10, $final_limit - 10 );

		return $safe_timeout;
	}

	/**
	 * Redact common PII from text (email, phone, IP, payment card, SSN, SIN).
	 *
	 * @param string $text
	 * @return string
	 */
	public static function redact_pii_from_text( $text ) {
		if ( empty( $text ) || ! is_string( $text ) ) {
			return '';
		}

		$redacted = $text;
		$card_placeholder = __( '[redacted card]', 'echo-knowledge-base' );
		$ssn_placeholder = __( '[redacted ssn]', 'echo-knowledge-base' );
		$sin_placeholder = __( '[redacted sin]', 'echo-knowledge-base' );

		$redacted = preg_replace_callback(
			'/\b(?:\d[ -]*?){13,19}\b/',
			static function( $matches ) use ( $card_placeholder ) {
				$candidate = preg_replace( '/\D+/', '', $matches[0] );
				$length = strlen( $candidate );
				if ( $length < 13 || $length > 19 ) {
					return $matches[0];
				}

				if ( ! EPKB_AI_Utilities::is_luhn_valid( $candidate ) ) {
					return $matches[0];
				}

				return $card_placeholder;
			},
			$redacted
		);

		$redacted = preg_replace(
			'/\b(?!000|666|9\d\d)\d{3}[- ]?(?!00)\d{2}[- ]?(?!0000)\d{4}\b/',
			$ssn_placeholder,
			$redacted
		);

		$redacted = preg_replace_callback(
			'/\b\d{3}[- ]?\d{3}[- ]?\d{3}\b/',
			static function( $matches ) use ( $sin_placeholder ) {
				$candidate = preg_replace( '/\D+/', '', $matches[0] );
				if ( strlen( $candidate ) !== 9 ) {
					return $matches[0];
				}

				if ( ! EPKB_AI_Utilities::is_luhn_valid( $candidate ) ) {
					return $matches[0];
				}

				return $sin_placeholder;
			},
			$redacted
		);

		$patterns = array(
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => __( '[redacted email]', 'echo-knowledge-base' ),
			'/\b\d{1,3}(?:\.\d{1,3}){3}\b/' => __( '[redacted ip]', 'echo-knowledge-base' ),
			'/\+?\d[\d\s().-]{7,}\d/' => __( '[redacted phone]', 'echo-knowledge-base' ),
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$redacted = preg_replace( $pattern, $replacement, $redacted );
		}

		return $redacted;
	}

	/**
	 * Validate a numeric string with the Luhn checksum.
	 *
	 * @param string $number
	 * @return bool
	 */
	private static function is_luhn_valid( $number ) {
		if ( empty( $number ) || ! is_string( $number ) ) {
			return false;
		}

		if ( preg_match( '/\D/', $number ) ) {
			return false;
		}

		$sum = 0;
		$alternate = false;
		for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {
			$digit = intval( $number[ $i ] );
			if ( $alternate ) {
				$digit *= 2;
				if ( $digit > 9 ) {
					$digit -= 9;
				}
			}
			$sum += $digit;
			$alternate = ! $alternate;
		}

		return ( $sum % 10 ) === 0;
	}

	/**
	 * Build a plain-text chat transcript for handoff requests.
	 *
	 * @param array $messages
	 * @return string
	 */
	public static function format_chat_transcript_for_handoff( $messages ) {
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return '';
		}

		$blocks = array();
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = isset( $message['role'] ) ? sanitize_key( $message['role'] ) : '';
			if ( empty( $role ) && isset( $message['isUser'] ) ) {
				$role = $message['isUser'] ? 'user' : 'assistant';
			}

			$content = '';
			if ( isset( $message['content'] ) ) {
				$content = $message['content'];
			} elseif ( isset( $message['text'] ) ) {
				$content = $message['text'];
			}

			if ( ! is_string( $content ) ) {
				continue;
			}

			$content = trim( wp_strip_all_tags( $content ) );
			if ( $content === '' ) {
				continue;
			}

			if ( $role === 'user' ) {
				$blocks[] = ">> " . __( 'USER', 'echo-knowledge-base' ) . ":\n" . $content;
			} elseif ( $role === 'assistant' ) {
				$blocks[] = __( 'AI ASSISTANT', 'echo-knowledge-base' ) . ":\n" . $content;
			} else {
				$blocks[] = __( 'MESSAGE', 'echo-knowledge-base' ) . ":\n" . $content;
			}
		}

		$transcript = implode( "\n\n" . str_repeat( '-', 40 ) . "\n\n", $blocks );
		return self::redact_pii_from_text( $transcript );
	}
}
