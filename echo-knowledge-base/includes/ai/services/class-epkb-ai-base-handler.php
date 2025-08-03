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
	private $ai_client;
		
	/**
	 * Messages database
	 * @var EPKB_AI_Messages_DB
	 */
	protected $messages_db;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->ai_client = new EPKB_OpenAI_Client();
		$this->messages_db = new EPKB_AI_Messages_DB();
	}

	/**
	 * Call AI API with messages
	 *
	 * @param String $message
	 * @param String $model
	 * @param null $previous_response_id
	 * @return array|WP_Error AI response or error
	 */
	protected function get_ai_response( $message, $model, $previous_response_id = null ) {

		// Get and validate max_output_tokens
		$max_output_tokens = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_max_output_tokens', EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS );
		$max_output_tokens = intval( $max_output_tokens );
		// Ensure it's within valid OpenAI bounds and handle edge cases
		if ( $max_output_tokens < 1 || $max_output_tokens > 16384 ) {
			$max_output_tokens = EPKB_OpenAI_Client::DEFAULT_MAX_OUTPUT_TOKENS;
		}

		// Get and validate temperature
		$temperature = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_temperature', EPKB_OpenAI_Client::DEFAULT_TEMPERATURE );
		$temperature = floatval( $temperature );
		// Ensure it's within valid OpenAI bounds and handle edge cases
		if ( $temperature < 0.0 || $temperature > 2.0 ) {
			$temperature = EPKB_OpenAI_Client::DEFAULT_TEMPERATURE;
		}

		// Build request for Responses API
		$request = array(
			'model'             => $model,
			'instructions'      => $this->get_instructions(),
			'input'             => array(
				array(
					'role'    => 'user',
					'content' => $message
				)
			),
			'max_output_tokens' => $max_output_tokens,
			'temperature'       => $temperature,
		);

		// Add previous response ID for continuing conversation
		if ( ! empty( $previous_response_id ) ) {
			$request['previous_response_id'] = $previous_response_id;
		}

		// Add file search tool if vector store is available
		$vector_store_id = $this->get_vector_store_id_for_chat();
		if ( ! empty( $vector_store_id ) ) {
			$request['tools'] = array(
				array(
					'type' => 'file_search',
					'vector_store_ids' => array( $vector_store_id ),
					'max_num_results' => EPKB_OpenAI_Client::DEFAULT_MAX_NUM_RESULTS
				)
			);
		}

		// Make API call to Responses endpoint
		$response = $this->ai_client->request( '/responses', $request );
		if ( is_wp_error( $response ) ) {
			// Check if it's an authentication error
			if ( $response->get_error_code() === 'authentication_failed' ) {
				return new WP_Error( 
					'authentication_failed', 
					__( 'AI service authentication failed. Please check your API key in the AI settings.', 'echo-knowledge-base' )
				);
			}
			return $response;
		}

		// Extract content from response
		$content = $this->extract_response_content( $response );
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
	 * Get AI instructions for chat
	 *
	 * @return string
	 */
	private function get_instructions() {

		$default_instructions = __( "You are a helpful assistant that only answers questions related to the provided content. " .
										"If the answer is not available, respond with: 'That is not something I can help with. Please try a different question.' " .
										"Do not refer to documents, files, content, or sources. Do not guess or answer based on general knowledge.", 'echo-knowledge-base' );

		// Determine which instructions to use based on the handler type
		if ( $this instanceof EPKB_AI_Search_Handler ) {
			$instructions = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_instructions', $default_instructions );
			return apply_filters( 'epkb_ai_search_instructions', $instructions );
		} else {
			$instructions = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_instructions', $default_instructions );
			return apply_filters( 'epkb_ai_chat_instructions', $instructions );
		}
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
	private function extract_response_content( $response ) {

		if ( empty( $response['output'] ) || ! is_array( $response['output'] ) ) {
			return '';
		}

		// Primary structure for Responses API - output array with content array
		$last_output = end( $response['output'] );
		if ( empty( $last_output['content'] ) || ! is_array( $last_output['content'] ) ) {
			return '';
		}

		$content = empty( $last_output['content'][0] ) ? '' : $last_output['content'][0];
		
		// If content is an object with a 'text' property (from newer OpenAI API), extract it
		if ( is_array( $content ) && isset( $content['text'] ) ) {
			return $content['text'];
		}
		
		// If content is an object/array, convert to string
		if ( is_array( $content ) || is_object( $content ) ) {
			return json_encode( $content );
		}
		
		return $content;
	}

	/**
	 * Get vector store ID from the first training data collection
	 *
	 * @return string|null Vector store ID or null if not available
	 */
	private function get_vector_store_id_for_chat() {

		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		if ( is_wp_error( $collections ) || empty( $collections ) ) {
			return null;
		}

		// Get the first collection (typically collection ID 1)
		$first_collection = reset( $collections );
		if ( ! is_array( $first_collection ) ) {
			return null;
		}

		// Return the vector store ID if it exists
		return ! empty( $first_collection['ai_training_data_store_id'] ) ? $first_collection['ai_training_data_store_id'] : null;
	}
} 