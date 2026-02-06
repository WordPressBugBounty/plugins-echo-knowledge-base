<?php

/**
 * Base AI Handler
 *
 * Abstract base class providing common functionality for AI handlers.
 * Supports multiple AI providers (ChatGPT, Gemini) via provider factory.
 */
abstract class EPKB_AI_Base_Handler {

	/**
	 * AI client for current provider
	 * @var EPKB_ChatGPT_Client|EPKB_Gemini_Client
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
		$this->ai_client = EPKB_AI_Provider::get_client();
		$this->messages_db = new EPKB_AI_Messages_DB();
	}

	/**
	 * Call AI API with messages
	 *
	 * @param String $message
	 * @param String $model
	 * @param null $previous_response_id
	 * @param int|null $collection_id Training data collection ID to use for search
	 * @param array $conversation_history Optional conversation history for Gemini (which is stateless)
	 * @return array|WP_Error AI response or error
	 */
	protected function get_ai_response( $message, $model, $previous_response_id, $collection_id, $conversation_history = array() ) {

		// Validate model exists for current provider, fallback to default if not
		$model = EPKB_AI_Provider::get_valid_model_for_provider( $model );

		// Get vector store ID - required for both providers
		$vector_store_id = EPKB_AI_Training_Data_Config_Specs::get_vector_store_id_by_collection( $collection_id );
		if ( is_wp_error( $vector_store_id ) ) {
			return $vector_store_id;  // Return the specific error (provider_mismatch, collection_not_found, no_vector_store, etc.)
		}

		// Get context-specific parameters
		$params = $this->get_model_parameters( $model );

		// Use provider-specific API call
		$provider = EPKB_AI_Provider::get_active_provider();
		if ( $provider === EPKB_AI_Provider::PROVIDER_GEMINI ) {
			$response = $this->get_gemini_response( $message, $model, $vector_store_id, $params, $conversation_history );
		} else {
			$response = $this->get_chatgpt_response( $message, $model, $vector_store_id, $params, $previous_response_id );
		}

		if ( is_wp_error( $response ) ) {
			if ( $response->get_error_code() === 'authentication_failed' ) {
				return new WP_Error( 'authentication_failed', __( 'AI service authentication failed. Please check your API key in the AI settings.', 'echo-knowledge-base' ) );
			}
			return $response;
		}

		// Extract content from response
		$content = EPKB_AI_Provider::extract_response_content( $response );
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_response', __( 'Received empty response from AI', 'echo-knowledge-base' ) );
		}

		// Extract source references from grounding metadata
		$sources = EPKB_AI_Provider::extract_response_sources( $response );

		return array(
			'content'     => $content,
			'response_id' => isset( $response['id'] ) ? $response['id'] : '',
			'usage'       => EPKB_AI_Provider::extract_response_usage( $response ),
			'sources'     => $sources
		);
	}

	/**
	 * Get AI response from ChatGPT using Responses API
	 *
	 * @param string $message User message
	 * @param string $model Model name
	 * @param string $vector_store_id Vector store ID
	 * @param array $params Model parameters
	 * @param string|null $previous_response_id Previous response ID for conversation continuity
	 * @return array|WP_Error
	 */
	private function get_chatgpt_response( $message, $model, $vector_store_id, $params, $previous_response_id = null ) {

		$request = array(
			'model'        => $model,
			'instructions' => $this->get_instructions(),
			'input'        => array(
				array(
					'role'    => 'user',
					'content' => $message
				)
			),
		);

		$request = EPKB_AI_Provider::apply_model_parameters( $request, $model, $params );

		if ( ! empty( $previous_response_id ) ) {
			$request['previous_response_id'] = $previous_response_id;
		}

		$request['tools'] = array(
			array(
				'type'             => 'file_search',
				'vector_store_ids' => array( $vector_store_id ),
				'max_num_results'  => EPKB_AI_Provider::get_default_max_results()
			)
		);

		return $this->ai_client->request( '/responses', $request );
	}

	/**
	 * Get AI response from Gemini using generateContent with file_search tool
	 *
	 * @param string $message User message
	 * @param string $model Model name
	 * @param string $vector_store_id File Search Store ID
	 * @param array $params Model parameters
	 * @param array $conversation_history Previous messages in the conversation
	 * @return array|WP_Error
	 */
	private function get_gemini_response( $message, $model, $vector_store_id, $params, $conversation_history = array() ) {

		$options = array(
			'system_instruction' => $this->get_instructions()
		);

		if ( isset( $params['temperature'] ) ) {
			$options['temperature'] = $params['temperature'];
		}
		if ( isset( $params['top_p'] ) ) {
			$options['top_p'] = $params['top_p'];
		}
		if ( isset( $params['max_output_tokens'] ) ) {
			$options['max_output_tokens'] = $params['max_output_tokens'];
		}
		if ( isset( $params['thinking_level'] ) ) {
			$options['thinking_level'] = $params['thinking_level'];
		}
		if ( ! empty( $conversation_history ) ) {
			$options['conversation_history'] = $conversation_history;
		}

		return $this->ai_client->generate_content_with_file_search( $model, $message, $vector_store_id, $options );
	}

	/**
	 * Get model parameters based on context (chat or search)
	 *
	 * @param string $model The model being used
	 * @return array Parameters array with temperature, max_output_tokens, etc.
	 */
	protected function get_model_parameters( $model ) {

		// Determine context - search or chat
		$is_search = $this instanceof EPKB_AI_Search_Handler;
		$prefix = $is_search ? 'ai_search_' : 'ai_chat_';
		
		// Get model specifications to determine which parameters are applicable
		$model_spec = EPKB_AI_Provider::get_models_and_default_params( $model );

		$params = array();

		// Add max_output_tokens
		$max_output_tokens_key = $prefix . 'max_output_tokens';
		$max_output_tokens = EPKB_AI_Config_Specs::get_ai_config_value( $max_output_tokens_key );
		if ( ! empty( $max_output_tokens ) ) {
			$max_output_tokens = intval( $max_output_tokens );
			$max_limit = isset( $model_spec['max_output_tokens_limit'] ) ? $model_spec['max_output_tokens_limit'] : 16384;
			if ( $max_output_tokens > 0 && $max_output_tokens <= $max_limit ) {
				$params['max_output_tokens'] = $max_output_tokens;
			}
		}

		// Add temperature for models that support it
		if ( ! empty( $model_spec['supports_temperature'] ) ) {
			$temperature_key = $prefix . 'temperature';
			$temperature = EPKB_AI_Config_Specs::get_ai_config_value( $temperature_key );
			if ( $temperature !== null ) {
				$temperature = floatval( $temperature );
				if ( $temperature >= 0.0 && $temperature <= 2.0 ) {
					$params['temperature'] = $temperature;
				}
			}
		}

		// Add top_p for models that support it (alternative to temperature)
		if ( ! empty( $model_spec['supports_top_p'] ) && ! isset( $params['temperature'] ) ) {
			$top_p_key = $prefix . 'top_p';
			$top_p = EPKB_AI_Config_Specs::get_ai_config_value( $top_p_key );
			if ( $top_p !== null ) {
				$top_p = floatval( $top_p );
				if ( $top_p >= 0.0 && $top_p <= 1.0 ) {
					$params['top_p'] = $top_p;
				}
			}
		}

		// Add verbosity for models that support it (ChatGPT only)
		if ( ! empty( $model_spec['supports_verbosity'] ) ) {
			$verbosity_key = $prefix . 'verbosity';
			$verbosity = EPKB_AI_Config_Specs::get_ai_config_value( $verbosity_key );
			if ( ! empty( $verbosity ) ) {
				$params['verbosity'] = $verbosity;
			}
		}

		// Add reasoning for models that support it (ChatGPT only)
		if ( ! empty( $model_spec['supports_reasoning'] ) ) {
			$reasoning_key = $prefix . 'reasoning';
			$reasoning = EPKB_AI_Config_Specs::get_ai_config_value( $reasoning_key );
			if ( ! empty( $reasoning ) ) {
				$params['reasoning'] = $reasoning;
			}
		}

		// Add thinking_level for models that support it (Gemini 2.5+/3.0+)
		if ( ! empty( $model_spec['supports_thinking_level'] ) ) {
			$thinking_level_key = $prefix . 'thinking_level';
			$thinking_level = EPKB_AI_Config_Specs::get_ai_config_value( $thinking_level_key );
			if ( ! empty( $thinking_level ) ) {
				$params['thinking_level'] = $thinking_level;
			} elseif ( ! empty( $model_spec['default_params']['thinking_level'] ) ) {
				// Use model default if no config setting
				$params['thinking_level'] = $model_spec['default_params']['thinking_level'];
			}
		}

		return $params;
	}

	/**
	 * Get AI instructions for chat
	 *
	 * @return string
	 */
	private function get_instructions() {
		if ( $this instanceof EPKB_AI_Search_Handler ) {
			$instructions = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_search_instructions' );
			return apply_filters( 'epkb_ai_search_instructions', $instructions );
		} else {
			$instructions = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_instructions' );
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
		return EPKB_AI_Provider::extract_response_content( $response );
	}
} 
