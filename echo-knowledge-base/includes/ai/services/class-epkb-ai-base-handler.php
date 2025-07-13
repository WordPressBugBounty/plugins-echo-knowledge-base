<?php

/**
 * Base AI Handler
 * 
 * Abstract base class providing common functionality for AI handlers
 */
abstract class EPKB_AI_Base_Handler {
	
	/**
	 * OpenAI client
	 * @var EPKB_OpenAI_Client
	 */
	protected $ai_client;
	
	/**
	 * AI configuration
	 * @var EPKB_AI_Config
	 */
	protected $config;
	
	/**
	 * Messages database
	 * @var EPKB_AI_Messages_DB
	 */
	protected $messages_db;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = new EPKB_AI_Config();
		$this->ai_client = new EPKB_OpenAI_Client( $this->config );
		$this->messages_db = new EPKB_AI_Messages_DB();
	}

	/**
	 * Call AI API with messages
	 *
	 * @param array|String $messages
	 * @param null $previous_response_id
	 * @return array|WP_Error AI response or error
	 */
	protected function get_ai_response( $messages, $previous_response_id = null ) {

		// Get only the last user message for Responses API
		$message = is_array( $messages ) ? $this->get_last_user_message( $messages ) : $messages;
		if ( empty( $message ) ) {
			return new WP_Error( 'no_user_message', __( 'No user message found', 'echo-knowledge-base' ) );
		}

		// Build request for Responses API
		$request = array(
			'model'             => $this->config->get_model(),
			'instructions'      => $this->get_instructions(),
			'input'             => $message,
			'max_output_tokens' => $this->config->get_max_tokens()
		);

		// Add previous response ID for continuing conversation
		if ( ! empty( $previous_response_id ) ) {
			$request['previous_response_id'] = $previous_response_id;
		}

		// Make API call to Responses endpoint
		$response = $this->ai_client->request( '/responses', $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract content from response
		$content = $this->extract_response_content( $response ); // TODO
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_response', __( 'Received empty response from AI', 'echo-knowledge-base' ) );
		}

		return array(
			'content' => $content,
			'response_id' => isset( $response['id'] ) ? $response['id'] : '',
			'usage' => isset( $response['usage'] ) ? $response['usage'] : array()
		);
	}

	/**
	 * Get the last user message from messages array
	 *
	 * @param array $messages
	 * @return string
	 */
	protected function get_last_user_message( $messages ) {
		if ( empty( $messages ) ) {
			return '';
		}

		// Iterate backwards to find the last user message
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			if ( $messages[$i]['role'] === 'user' ) {
				return $messages[$i]['content'];
			}
		}

		return '';
	}

	/**
	 * Get AI instructions for chat
	 *
	 * @return string
	 */
	private function get_instructions() {
		$instructions = __( 'You are a helpful assistant. Continue the conversation naturally, providing accurate and helpful responses to the user\'s questions.', 'echo-knowledge-base' );
		return apply_filters( 'epkb_ai_chat_instructions', $instructions );
	}

	/**
	 * Get client IP address (hashed for privacy)
	 *
	 * @return string Hashed IP address or empty string
	 */
	protected function get_hashed_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		$raw_ip = '';
		
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
	 * Record API usage for tracking
	 *
	 * @param array $usage Usage data from API response
	 * @return void
	 */
	protected function record_usage( $usage ) {
		if ( empty( $usage ) ) {
			return;
		}
		
		// Get current month's usage
		$month_key = 'epkb_ai_usage_' . gmdate( 'Y_m' );
		$monthly_usage = get_option( $month_key, array(
			'prompt_tokens' => 0,
			'completion_tokens' => 0,
			'total_tokens' => 0,
			'requests' => 0
		) );
		
		// Update usage
		if ( isset( $usage['prompt_tokens'] ) ) {
			$monthly_usage['prompt_tokens'] += intval( $usage['prompt_tokens'] );
		}
		if ( isset( $usage['completion_tokens'] ) ) {
			$monthly_usage['completion_tokens'] += intval( $usage['completion_tokens'] );
		}
		if ( isset( $usage['total_tokens'] ) ) {
			$monthly_usage['total_tokens'] += intval( $usage['total_tokens'] );
		}
		$monthly_usage['requests']++;
		
		// Save updated usage
		update_option( $month_key, $monthly_usage, false );
	}

	/**
	 * Extract content from API response
	 *
	 * @param array $response
	 * @return string
	 */
	protected function extract_response_content( $response ) {
		// Responses API structure
		if ( isset( $response['output'][0]['content'][0]['text'] ) ) {
			return $response['output'][0]['content'][0]['text'];
		}
		
		// Chat completions API structure
		if ( isset( $response['choices'][0]['message']['content'] ) ) {
			return $response['choices'][0]['message']['content'];
		}
		
		return '';
	}
	
	/**
	 * Generate conversation title from first message
	 * 
	 * @param string $message
	 * @return string
	 */
	protected function generate_title( $message ) {
		// Truncate to first sentence or 100 chars
		$title = wp_strip_all_tags( $message );
		$title = preg_replace( '/\s+/', ' ', trim( $title ) );
		
		$sentences = preg_split( '/[.!?]+/', $title, 2 );
		$title = ! empty( $sentences[0] ) ? $sentences[0] : $title;
		
		if ( strlen( $title ) > 100 ) {
			$title = substr( $title, 0, 97 ) . '...';
		}
		
		return $title;
	}
} 