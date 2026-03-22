<?php

/**
 * AI Provider helper
 *
 * Centralizes provider selection and provider-specific utilities
 * so features can switch between ChatGPT and Gemini without duplicating logic.
 */
class EPKB_AI_Provider {

	const PROVIDER_CHATGPT = 'chatgpt';	// do not modify
	const PROVIDER_GEMINI = 'gemini';	// do not modify
	const FASTEST_MODEL = 'fastest';

	private static $cached_active_provider = null;

	/**
	 * List of supported providers.
	 *
	 * @return array
	 */
	public static function get_supported_providers() {
		return array( self::PROVIDER_CHATGPT, self::PROVIDER_GEMINI );
	}

	/**
	 * Normalize provider slug
	 *
	 * @param string $provider
	 * @return string
	 */
	public static function normalize_provider( $provider ) {

		// TODO remove legacy 'openai' mapping in future version
		if ( $provider === 'openai' ) {
			$provider = EPKB_AI_Provider::PROVIDER_CHATGPT;
		}

		$provider = strtolower( trim( (string) $provider ) );

		return in_array( $provider, self::get_supported_providers(), true ) ? $provider : self::PROVIDER_GEMINI;
	}

	/**
	 * Get active provider using current configuration
	 *
	 * @param array|null $config Optional config array to avoid recursive lookups
	 * @return string
	 */
	public static function get_active_provider( $config = null ) {

		if ( $config === null && self::$cached_active_provider !== null ) {
			return self::$cached_active_provider;
		}

		if ( $config === null ) {
			$config = EPKB_AI_Config_Specs::get_ai_config();
		}

		$original_provider = $config['ai_provider'];
		$normalized_provider = self::normalize_provider( $original_provider );

		// Update stored value if it was an old provider name
		if ( $original_provider !== $normalized_provider ) {
			EPKB_AI_Config_Specs::update_ai_config_value( 'ai_provider', $normalized_provider );
		}

		self::$cached_active_provider = $normalized_provider;

		return $normalized_provider;
	}

	/**
	 * Human readable label for provider
	 *
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_provider_label( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		return $provider === self::PROVIDER_GEMINI ? 'Google Gemini' : 'OpenAI ChatGPT';
	}

	/**
	 * Get user-facing provider options for select fields.
	 *
	 * @return array
	 */
	public static function get_provider_options() {
		return array(
			self::PROVIDER_GEMINI => self::get_provider_label( self::PROVIDER_GEMINI ),
			self::PROVIDER_CHATGPT => self::get_provider_label( self::PROVIDER_CHATGPT ),
		);
	}

	/**
	 * Get AI client for provider
	 *
	 * @param string|null $provider
	 * @return EPKB_ChatGPT_Client|EPKB_Gemini_Client
	 */
	public static function get_client( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		return $provider === self::PROVIDER_GEMINI ? new EPKB_Gemini_Client() : new EPKB_ChatGPT_Client();
	}

	/**
	 * Get vector store handler for provider
	 *
	 * @param string|null $provider
	 * @return EPKB_AI_ChatGPT_Vector_Store|EPKB_AI_Gemini_Vector_Store
	 */
	public static function get_vector_store_handler( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		return $provider === self::PROVIDER_GEMINI ? new EPKB_AI_Gemini_Vector_Store() : new EPKB_AI_ChatGPT_Vector_Store();
	}

	/**
	 * Get models and defaults for provider
	 *
	 * @param string|null $model_name
	 * @param string|null $provider
	 * @return array
	 */
	public static function get_models_and_default_params( $model_name = null, $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		if ( $provider === self::PROVIDER_GEMINI ) {
			return EPKB_Gemini_Client::get_models_and_default_params( $model_name );
		}

		return EPKB_ChatGPT_Client::get_models_and_default_params( $model_name );
	}

	/**
	 * Get available models formatted for select fields
	 *
	 * @param string|null $provider
	 * @return array
	 */
	public static function get_model_options( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$models = self::get_models_and_default_params( null, $provider );
		$options = array();

		foreach ( $models as $model_key => $model_spec ) {
			$options[ $model_key ] = isset( $model_spec['name'] ) ? $model_spec['name'] : $model_key;
		}

		return $options;
	}

	/**
	 * Ensure selected model exists for the provider, fallback to default
	 *
	 * @param string $model
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_valid_model_for_provider( $model, $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$model_options = self::get_model_options( $provider );

		if ( isset( $model_options[ $model ] ) ) {
			return $model;
		}

		$default_model = self::get_default_model( $provider );
		if ( isset( $model_options[ $default_model ] ) ) {
			return $default_model;
		}

		if ( empty( $model_options ) ) {
			return '';
		}

		reset( $model_options );

		return key( $model_options );
	}

	/**
	 * Get model presets derived from model definitions
	 *
	 * Builds presets from models that have preset_key defined, plus adds 'custom' preset.
	 *
	 * @param string $use_case Not currently used, kept for API compatibility
	 * @param string|null $provider
	 * @return array
	 */
	public static function get_model_presets( $use_case = 'chat', $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$models = self::get_models_and_default_params( null, $provider );

		$presets = array();
		foreach ( $models as $model_id => $model_spec ) {
			if ( empty( $model_spec['preset_key'] ) ) {
				continue;
			}

			$preset = array(
				'label'       => $model_spec['preset_label'],
				'description' => $model_spec['name'],
				'model'       => $model_id
			);

			// Add all default params to the preset
			if ( ! empty( $model_spec['default_params'] ) ) {
				foreach ( $model_spec['default_params'] as $param => $value ) {
					$preset[$param] = $value;
				}
			}

			$presets[$model_spec['preset_key']] = $preset;
		}

		// Add custom preset at the end
		$presets['custom'] = array(
			'label'       => __( 'Custom', 'echo-knowledge-base' ),
			'description' => __( 'Model parameters can be customized.', 'echo-knowledge-base' ),
			'model'       => null
		);

		return $presets;
	}

	/**
	 * Get preset parameters
	 *
	 * @param string $preset_key
	 * @param string $use_case
	 * @param string|null $provider
	 * @return array
	 */
	public static function get_preset_parameters( $preset_key, $use_case = 'chat', $provider = null ) {
		$presets = self::get_model_presets( $use_case, $provider );
		return isset( $presets[$preset_key] ) ? $presets[$preset_key] : reset( $presets );
	}

	/**
	 * Get the preset key for a given model
	 *
	 * @param string $model Model ID
	 * @param string|null $provider
	 * @return string Preset key or 'custom' if model has no preset
	 */
	public static function get_preset_key_for_model( $model, $provider = null ) {
		$model_spec = self::get_models_and_default_params( $model, $provider );
		return ! empty( $model_spec['preset_key'] ) ? $model_spec['preset_key'] : 'custom';
	}

	/**
	 * Apply model parameters for provider
	 *
	 * @param array $request
	 * @param string $model
	 * @param array $params
	 * @param string|null $provider
	 * @return array
	 */
	public static function apply_model_parameters( $request, $model, $params = array(), $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		if ( $provider === self::PROVIDER_GEMINI ) {
			return EPKB_Gemini_Client::apply_model_parameters( $request, $model, $params );
		}

		return EPKB_ChatGPT_Client::apply_model_parameters( $request, $model, $params );
	}

	/**
	 * Get default model for provider
	 *
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_default_model( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? EPKB_Gemini_Client::DEFAULT_MODEL : EPKB_ChatGPT_Client::DEFAULT_MODEL;
	}

	/**
	 * Get decrypted API key for provider
	 *
	 * @param string|null $provider Provider (defaults to active)
	 * @return string Decrypted API key or empty string
	 */
	public static function get_api_key( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$encrypted = EPKB_AI_Config_Specs::get_unmasked_api_key_for_provider( $provider );
		if ( empty( $encrypted ) ) {
			return '';
		}
		$decrypted = EPKB_Utilities::decrypt_data( $encrypted );
		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Get chat model for provider
	 *
	 * @param string|null $provider
	 * @return string Valid model ID
	 */
	public static function get_chat_model( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$field = $provider === self::PROVIDER_GEMINI ? 'ai_gemini_chat_model' : 'ai_chatgpt_chat_model';
		$model = EPKB_AI_Config_Specs::get_ai_config_value( $field );
		return self::get_valid_model_for_provider( $model, $provider );
	}

	/**
	 * Get search model for provider
	 *
	 * @param string|null $provider
	 * @return string Valid model ID
	 */
	public static function get_search_model( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		$field = $provider === self::PROVIDER_GEMINI ? 'ai_gemini_search_model' : 'ai_chatgpt_search_model';
		$model = EPKB_AI_Config_Specs::get_ai_config_value( $field );
		return self::get_valid_model_for_provider( $model, $provider );
	}

	/**
	 * Get the config field name for API key
	 *
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_api_key_field( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? 'ai_gemini_key' : 'ai_chatgpt_key';
	}

	/**
	 * Get the config field name for chat model
	 *
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_chat_model_field( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? 'ai_gemini_chat_model' : 'ai_chatgpt_chat_model';
	}

	/**
	 * Get the config field name for search model
	 *
	 * @param string|null $provider
	 * @return string
	 */
	public static function get_search_model_field( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? 'ai_gemini_search_model' : 'ai_chatgpt_search_model';
	}

	/**
	 * Get default max output tokens for provider
	 *
	 * @param string|null $provider
	 * @return int
	 */
	public static function get_default_max_output_tokens( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? EPKB_Gemini_Client::DEFAULT_MAX_OUTPUT_TOKENS : EPKB_ChatGPT_Client::DEFAULT_MAX_OUTPUT_TOKENS;
	}

	/**
	 * Get default max results for retrieval tools
	 *
	 * @param string|null $provider
	 * @return int
	 */
	public static function get_default_max_results( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? EPKB_Gemini_Client::DEFAULT_MAX_NUM_RESULTS : EPKB_ChatGPT_Client::DEFAULT_MAX_NUM_RESULTS;
	}

	/**
	 * Get max upload size according to provider
	 *
	 * @param string|null $provider
	 * @return int
	 */
	public static function get_max_file_size( $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		return $provider === self::PROVIDER_GEMINI ? EPKB_Gemini_Client::MAX_FILE_SIZE : EPKB_ChatGPT_Client::MAX_FILE_SIZE;
	}

	/**
	 * Upload a temporary file for direct prompt input.
	 *
	 * @param string $file_content Raw file bytes.
	 * @param string $file_name Original file name.
	 * @param string $mime_type MIME type.
	 * @return array|WP_Error Normalized attachment reference.
	 */
	public static function upload_prompt_attachment( $file_content, $file_name, $mime_type ) {

		if ( ! is_string( $file_content ) || $file_content === '' ) {
			return new WP_Error( 'empty_file', __( 'File content is empty.', 'echo-knowledge-base' ) );
		}

		$provider = self::get_active_provider();
		$file_name = sanitize_file_name( $file_name );
		$file_name = empty( $file_name ) ? 'document' : $file_name;
		$mime_type = empty( $mime_type ) ? 'application/octet-stream' : $mime_type;

		if ( $provider === self::PROVIDER_GEMINI ) {
			return self::upload_gemini_prompt_attachment( $file_content, $file_name, $mime_type );
		}

		return self::upload_chatgpt_prompt_attachment( $file_content, $file_name, $mime_type );
	}

	/**
	 * Build provider-native request content for an uploaded prompt attachment.
	 *
	 * @param array $attachment Normalized attachment reference.
	 * @param string|null $provider
	 * @return array
	 */
	public static function build_prompt_attachment_content( $attachment, $provider = null ) {

		$provider = $provider ?: ( ! empty( $attachment['provider'] ) ? $attachment['provider'] : self::get_active_provider() );

		if ( $provider === self::PROVIDER_GEMINI ) {
			return array(
				'fileData' => array(
					'mimeType' => $attachment['mime_type'],
					'fileUri'  => $attachment['file_uri'],
				)
			);
		}

		return array(
			'type'    => 'input_file',
			'file_id' => $attachment['file_id'],
		);
	}

	/**
	 * Delete a temporary prompt attachment.
	 *
	 * @param array $attachment Normalized attachment reference.
	 * @param string|null $provider
	 * @return bool
	 */
	public static function delete_prompt_attachment( $attachment, $provider = null ) {

		$provider = $provider ?: ( ! empty( $attachment['provider'] ) ? $attachment['provider'] : self::get_active_provider() );

		if ( $provider === self::PROVIDER_GEMINI ) {
			return self::delete_gemini_prompt_attachment( $attachment );
		}

		return self::delete_chatgpt_prompt_attachment( $attachment );
	}

	/**
	 * Upload a temporary prompt attachment to OpenAI.
	 *
	 * @param string $file_content Raw file bytes.
	 * @param string $file_name Original file name.
	 * @param string $mime_type MIME type.
	 * @return array|WP_Error
	 */
	private static function upload_chatgpt_prompt_attachment( $file_content, $file_name, $mime_type ) {

		$client = new EPKB_ChatGPT_Client();
		$response = $client->request( '/files', array(
			'file_name'         => $file_name,
			'file_content'      => $file_content,
			'file_purpose'      => 'user_data',
			'file_content_type' => $mime_type,
		), 'POST', 'file_storage_upload' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['id'] ) ) {
			return new WP_Error( 'file_upload_failed', __( 'Failed to upload file to AI storage.', 'echo-knowledge-base' ) );
		}

		return array(
			'provider'  => self::PROVIDER_CHATGPT,
			'file_id'   => $response['id'],
			'mime_type' => ! empty( $response['mime_type'] ) ? $response['mime_type'] : $mime_type,
			'file_name' => $file_name,
		);
	}

	/**
	 * Upload a temporary prompt attachment to Gemini.
	 *
	 * @param string $file_content Raw file bytes.
	 * @param string $file_name Original file name.
	 * @param string $mime_type MIME type.
	 * @return array|WP_Error
	 */
	private static function upload_gemini_prompt_attachment( $file_content, $file_name, $mime_type ) {

		$client = new EPKB_Gemini_Client();
		$response = $client->upload_prompt_file( $file_content, array( 'displayName' => $file_name ), $mime_type );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$file = isset( $response['file'] ) && is_array( $response['file'] ) ? $response['file'] : $response;
		$file = self::wait_for_gemini_prompt_attachment( $client, $file );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		if ( empty( $file['name'] ) || empty( $file['uri'] ) ) {
			return new WP_Error( 'file_upload_failed', __( 'Failed to upload file to AI storage.', 'echo-knowledge-base' ) );
		}

		return array(
			'provider'      => self::PROVIDER_GEMINI,
			'resource_name' => $file['name'],
			'file_uri'      => $file['uri'],
			'mime_type'     => ! empty( $file['mimeType'] ) ? $file['mimeType'] : $mime_type,
			'file_name'     => $file_name,
		);
	}

	/**
	 * Wait until Gemini marks an uploaded file as ready for prompt usage.
	 *
	 * @param EPKB_Gemini_Client $client
	 * @param array $file Uploaded Gemini File resource.
	 * @return array|WP_Error
	 */
	private static function wait_for_gemini_prompt_attachment( $client, $file ) {

		if ( empty( $file['name'] ) ) {
			return new WP_Error( 'file_upload_failed', __( 'Failed to upload file to AI storage.', 'echo-knowledge-base' ) );
		}

		$state = ! empty( $file['state'] ) ? strtoupper( $file['state'] ) : '';
		if ( $state === '' || $state === 'ACTIVE' ) {
			return $file;
		}

		if ( $state === 'FAILED' ) {
			return self::get_gemini_prompt_attachment_error( $file );
		}

		for ( $attempt = 0; $attempt < 30; $attempt++ ) {
			EPKB_AI_Utilities::safe_sleep( 2 );

			$latest = $client->get_file( $file['name'] );
			if ( is_wp_error( $latest ) ) {
				return $latest;
			}

			$file = isset( $latest['file'] ) && is_array( $latest['file'] ) ? $latest['file'] : $latest;
			$state = ! empty( $file['state'] ) ? strtoupper( $file['state'] ) : '';

			if ( $state === '' || $state === 'ACTIVE' ) {
				return $file;
			}

			if ( $state === 'FAILED' ) {
				return self::get_gemini_prompt_attachment_error( $file );
			}
		}

		return new WP_Error( 'file_processing_timeout', __( 'AI provider is still processing the uploaded file. Please try again.', 'echo-knowledge-base' ) );
	}

	/**
	 * Build a Gemini processing error from a File resource.
	 *
	 * @param array $file Gemini File resource.
	 * @return WP_Error
	 */
	private static function get_gemini_prompt_attachment_error( $file ) {

		$message = __( 'AI provider failed to process the uploaded file.', 'echo-knowledge-base' );
		if ( ! empty( $file['error']['message'] ) ) {
			$message = 'AI ERROR: ' . $file['error']['message'];
		}

		return new WP_Error( 'file_processing_failed', $message );
	}

	/**
	 * Delete a temporary OpenAI prompt attachment.
	 *
	 * @param array $attachment Normalized attachment reference.
	 * @return bool
	 */
	private static function delete_chatgpt_prompt_attachment( $attachment ) {

		if ( empty( $attachment['file_id'] ) ) {
			return true;
		}

		$client = new EPKB_ChatGPT_Client();
		$response = $client->request( '/files/' . rawurlencode( $attachment['file_id'] ), array(), 'DELETE', 'file_storage' );
		if ( is_wp_error( $response ) && $response->get_error_code() !== 'not_found' ) {
			EPKB_AI_Log::add_log( 'Failed to delete temporary AI prompt file', array(
				'provider' => self::PROVIDER_CHATGPT,
				'file_id'  => $attachment['file_id'],
				'error'    => $response->get_error_message(),
			) );
			return false;
		}

		return true;
	}

	/**
	 * Delete a temporary Gemini prompt attachment.
	 *
	 * @param array $attachment Normalized attachment reference.
	 * @return bool
	 */
	private static function delete_gemini_prompt_attachment( $attachment ) {

		if ( empty( $attachment['resource_name'] ) ) {
			return true;
		}

		$client = new EPKB_Gemini_Client();
		$response = $client->delete_file( $attachment['resource_name'] );
		if ( is_wp_error( $response ) && $response->get_error_code() !== 'not_found' ) {
			EPKB_AI_Log::add_log( 'Failed to delete temporary AI prompt file', array(
				'provider'      => self::PROVIDER_GEMINI,
				'resource_name' => $attachment['resource_name'],
				'error'         => $response->get_error_message(),
			) );
			return false;
		}

		return true;
	}

	/**
	 * Extract response content for provider
	 *
	 * @param array $response
	 * @param string|null $provider
	 * @return string
	 */
	public static function extract_response_content( $response, $provider = null ) {
		$provider = $provider ?: self::get_active_provider();
		if ( $provider === self::PROVIDER_GEMINI ) {
			return self::extract_gemini_response_content( $response );
		}

		return self::extract_chatgpt_response_content( $response );
	}

	/**
	 * Extract content from ChatGPT-style Responses API payload
	 *
	 * @param array $response
	 * @return string
	 */
	private static function extract_chatgpt_response_content( $response ) {
		if ( empty( $response['output'] ) || ! is_array( $response['output'] ) ) {
			return '';
		}

		// Primary structure for Responses API - output array with content array
		$last_output = end( $response['output'] );
		if ( empty( $last_output['content'] ) || ! is_array( $last_output['content'] ) ) {
			return '';
		}

		$content = empty( $last_output['content'][0] ) ? '' : $last_output['content'][0];

		// If content is an object with a 'text' property (from newer ChatGPT API), extract it
		if ( is_array( $content ) && isset( $content['text'] ) ) {
			$content = $content['text'];
		}

		// If content is an object/array, convert to string
		if ( is_array( $content ) || is_object( $content ) ) {
			$content = json_encode( $content );
		}

		// Convert kb_article patterns to links with article names
		// This is done in the ChatGPT handler since it created these file names
		return EPKB_AI_Utilities::convert_kb_article_references_to_links( $content );
	}

	/**
	 * Extract content from Gemini generateContent response
	 *
	 * @param array $response
	 * @return string
	 */
	private static function extract_gemini_response_content( $response ) {
		if ( empty( $response['candidates'] ) || ! is_array( $response['candidates'] ) ) {
			return '';
		}

		$candidate = $response['candidates'][0];
		if ( empty( $candidate['content']['parts'] ) || ! is_array( $candidate['content']['parts'] ) ) {
			return '';
		}

		$first_part = $candidate['content']['parts'][0];
		if ( is_array( $first_part ) && isset( $first_part['text'] ) ) {
			$content = $first_part['text'];
		} else {
			$content = is_string( $first_part ) ? $first_part : json_encode( $first_part );
		}

		// Convert kb_article patterns to links with article names
		// This is done in the ChatGPT handler since it created these file names
		return EPKB_AI_Utilities::convert_kb_article_references_to_links( $content );
	}

	/**
	 * Extract usage information from API response
	 *
	 * @param array $response
	 * @param string|null $provider
	 * @return array Normalized usage array with prompt_tokens, completion_tokens, total_tokens
	 */
	public static function extract_response_usage( $response, $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		if ( $provider === self::PROVIDER_GEMINI ) {
			return self::extract_gemini_usage( $response );
		}

		return self::extract_chatgpt_usage( $response );
	}

	/**
	 * Extract usage from ChatGPT response
	 *
	 * @param array $response
	 * @return array
	 */
	private static function extract_chatgpt_usage( $response ) {
		if ( empty( $response['usage'] ) ) {
			return array();
		}

		return array(
			'prompt_tokens'     => isset( $response['usage']['prompt_tokens'] ) ? intval( $response['usage']['prompt_tokens'] ) : 0,
			'completion_tokens' => isset( $response['usage']['completion_tokens'] ) ? intval( $response['usage']['completion_tokens'] ) : 0,
			'total_tokens'      => isset( $response['usage']['total_tokens'] ) ? intval( $response['usage']['total_tokens'] ) : 0
		);
	}

	/**
	 * Extract usage from Gemini response
	 *
	 * @param array $response
	 * @return array
	 */
	private static function extract_gemini_usage( $response ) {
		if ( empty( $response['usageMetadata'] ) ) {
			return array();
		}

		$usage = $response['usageMetadata'];

		return array(
			'prompt_tokens'     => isset( $usage['promptTokenCount'] ) ? intval( $usage['promptTokenCount'] ) : 0,
			'completion_tokens' => isset( $usage['candidatesTokenCount'] ) ? intval( $usage['candidatesTokenCount'] ) : 0,
			'total_tokens'      => isset( $usage['totalTokenCount'] ) ? intval( $usage['totalTokenCount'] ) : 0
		);
	}

	/**
	 * Extract source references from provider response metadata
	 *
	 * @param array $response Raw API response
	 * @param string|null $provider Provider (defaults to active)
	 * @return array Array of source articles with post_id, title, url
	 */
	public static function extract_response_sources( $response, $provider = null ) {
		$provider = $provider ?: self::get_active_provider();

		if ( $provider === self::PROVIDER_GEMINI ) {
			return EPKB_AI_Utilities::extract_sources_from_grounding_metadata( $response );
		}

		return EPKB_AI_Utilities::extract_sources_from_chatgpt_annotations( $response );
	}

	/**
	 * Get preset options formatted for select fields
	 *
	 * @param string $use_case 'chat' or 'search'
	 * @param string|null $provider
	 * @return array
	 */
	public static function get_preset_options( $use_case = 'chat', $provider = null ) {
		$presets = self::get_model_presets( $use_case, $provider );
		$options = array();
		foreach ( $presets as $key => $preset ) {
			if ( $key === 'fastest' ) {
				$options[$key] = $preset['label'] . ': ' . $preset['description'] . ' ' . __( '(default)', 'echo-knowledge-base' );
			} else {
				$options[$key] = $preset['label'] . ': ' . $preset['description'];
			}
		}
		return $options;
	}

	/**
	 * Get model parameter fields for settings UI
	 *
	 * @param string $use_case 'chat' or 'search'
	 * @param array $config Current AI config
	 * @return array Field definitions
	 */
	public static function get_model_parameter_fields( $use_case, $config ) {
		$prefix = "ai_{$use_case}_";
		$model_field = $use_case === 'chat' ? self::get_chat_model_field() : self::get_search_model_field();
		$model = $use_case === 'chat' ? self::get_chat_model() : self::get_search_model();
		$model_spec = self::get_models_and_default_params( $model );
		$max_limit = isset( $model_spec['max_output_tokens_limit'] ) ? $model_spec['max_output_tokens_limit'] : self::get_default_max_output_tokens();
		$label_model = $use_case === 'chat' ? __( 'Chat Model', 'echo-knowledge-base' ) : __( 'Search Model', 'echo-knowledge-base' );

		return array(
			$model_field => array(
				'type' => 'select',
				'label' => $label_model,
				'value' => $model,
				'options' => self::get_model_options()
			),
			$prefix . 'verbosity' => array(
				'type' => 'select',
				'label' => __( 'Verbosity', 'echo-knowledge-base' ),
				'value' => $config[$prefix . 'verbosity'],
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' ),
				),
				'description' => __( 'Controls response verbosity', 'echo-knowledge-base' ),
			),
			$prefix . 'reasoning' => array(
				'type' => 'select',
				'label' => __( 'Reasoning', 'echo-knowledge-base' ),
				'value' => $config[$prefix . 'reasoning'],
				'options' => array(
					'low' => __( 'Low', 'echo-knowledge-base' ),
					'medium' => __( 'Medium', 'echo-knowledge-base' ),
					'high' => __( 'High', 'echo-knowledge-base' ),
				),
				'description' => __( 'Controls reasoning depth', 'echo-knowledge-base' ),
			),
			$prefix . 'temperature' => array(
				'type' => 'number',
				'label' => __( 'Temperature', 'echo-knowledge-base' ),
				'value' => $config[$prefix . 'temperature'],
				'min' => 0,
				'max' => $use_case === 'chat' ? 2 : 1,
				'step' => 0.1,
				'description' => __( 'Controls response creativity', 'echo-knowledge-base' )
			),
			$prefix . 'top_p' => array(
				'type' => 'number',
				'label' => __( 'Top P', 'echo-knowledge-base' ),
				'value' => $config[$prefix . 'top_p'],
				'min' => 0,
				'max' => 1,
				'step' => 0.1,
				'description' => __( 'Controls response diversity', 'echo-knowledge-base' )
			),
			$prefix . 'max_output_tokens' => array(
				'type' => 'number',
				'label' => __( 'Max Tokens', 'echo-knowledge-base' ),
				'value' => $config[$prefix . 'max_output_tokens'],
				'min' => 50,
				'max' => $max_limit,
				'description' => __( 'Maximum response length in tokens', 'echo-knowledge-base' )
			)
		);
	}

	/**
	 * Apply preset settings for a use case
	 *
	 * @param string $preset_key
	 * @param string $use_case 'chat' or 'search'
	 * @return array Applied settings info
	 */
	public static function apply_preset( $preset_key, $use_case = 'chat' ) {
		if ( $preset_key === 'custom' ) {
			return array( 'message' => __( 'Custom preset selected.', 'echo-knowledge-base' ) );
		}

		$preset = self::get_preset_parameters( $preset_key, $use_case );
		$prefix = "ai_{$use_case}_";
		$model_field = $use_case === 'chat' ? self::get_chat_model_field() : self::get_search_model_field();

		$applied = array();
		if ( isset( $preset['model'] ) ) {
			EPKB_AI_Config_Specs::update_ai_config_value( $model_field, $preset['model'] );
			$applied['model'] = $preset['model'];
		}
		foreach ( array( 'verbosity', 'reasoning', 'temperature', 'max_output_tokens', 'top_p' ) as $param ) {
			if ( isset( $preset[$param] ) ) {
				EPKB_AI_Config_Specs::update_ai_config_value( $prefix . $param, $preset[$param] );
				$applied[$param] = $preset[$param];
			}
		}

		// translators: %s is the preset name
		return array(
			'message' => sprintf( __( 'Applied "%s" preset', 'echo-knowledge-base' ), $preset['label'] ),
			'applied_settings' => $applied
		);
	}
}
