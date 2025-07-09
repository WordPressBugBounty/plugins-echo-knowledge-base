<?php

/**
 * Vector Store Service
 * 
 * Manages OpenAI vector stores and file operations.
 */
class EPKB_AI_Vector_Store_Service {
	
	/**
	 * API endpoints
	 */
	const VECTOR_STORES_ENDPOINT = '/vector_stores';
	const FILES_ENDPOINT = '/files';
	
	/**
	 * File name prefix
	 */
	const FILENAME_PREFIX = 'kb-articles-';
	
	/**
	 * Maximum file size (1MB)
	 */
	const MAX_FILE_SIZE = 1048576; // 1MB in bytes
	
	/**
	 * API client
	 * @var EPKB_OpenAI_Client
	 */
	private $api_client;
	
	/**
	 * Configuration
	 * @var EPKB_AI_Config
	 */
	private $config;
	
	/**
	 * Content processor
	 * @var EPKB_AI_Content_Processor
	 */
	private $content_processor;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		$this->config = new EPKB_AI_Config();
		$this->api_client = new EPKB_OpenAI_Client( $this->config );
		$this->content_processor = new EPKB_AI_Content_Processor();
	}
	
	/**
	 * Create a new vector store
	 *
	 * @param string $name
	 * @param array $metadata
	 * @return array|WP_Error
	 */
	public function create_vector_store( $name = '', $metadata = array() ) {
		
		$data = array(
			'name' => ! empty( $name ) ? $name : __( 'KB Posts', 'echo-knowledge-base' ),
			'metadata' => $this->config->validate_metadata( $metadata )
		);
		
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT, 
			$data,
			'POST',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		if ( is_wp_error( $response ) ) {
			EPKB_AI_Utilities::add_log( 'Vector store creation failed', array(
				'name' => $name,
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message()
			) );
		}
		
		return $response;
	}
	
	/**
	 * Get vector store by ID
	 *
	 * @param string $vector_store_id
	 * @return array|null|WP_Error
	 */
	public function get_vector_store( $vector_store_id ) {
		
		if ( empty( $vector_store_id ) ) {
			return new WP_Error( 'missing_id', 'Vector store ID is required' );
		}
		
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id,
			array(),
			'GET',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		if ( is_wp_error( $response ) ) {
			// Return null for 404 errors
			if ( $response->get_error_code() === 'not_found' ) {
				return null;
			}
			return $response;
		}
		
		if ( ! empty( $response['status'] ) && $response['status'] !== 'completed' ) {
			return new WP_Error( 
				'not_ready',
				sprintf( 'Vector store is not ready: %s', $response['status'] )
			);
		}
		
		return $response;
	}
	
	/**
	 * Delete vector store
	 *
	 * @param string $vector_store_id
	 * @return bool|WP_Error
	 */
	public function delete_vector_store( $vector_store_id ) {
		
		if ( empty( $vector_store_id ) ) {
			return new WP_Error( 'missing_id', 'Vector store ID is required' );
		}
		
		// First delete all files
		$delete_files_result = $this->delete_all_vector_store_files( $vector_store_id );
		if ( is_wp_error( $delete_files_result ) ) {
			return $delete_files_result;
		}
		
		// Then delete the vector store
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id,
			array(),
			'DELETE',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		return true;
	}
	
	/**
	 * Add posts to vector store
	 *
	 * @param string $vector_store_id
	 * @param array $post_ids
	 * @return array|WP_Error Array of results with successes and failures or WP_Error
	 */
	public function add_posts_to_vector_store( $vector_store_id, $post_ids ) {
		
		if ( empty( $vector_store_id ) || empty( $post_ids ) ) {
			return new WP_Error( 'missing_params', 'Vector store ID and post IDs are required' );
		}
		
		$results = array(
			'success' => array(),
			'failed'  => array(),
			'skipped' => array()
		);
		
		foreach ( $post_ids as $post_id ) {
			// Process post content
			$processed_content = $this->content_processor->process_post( $post_id );
			
			if ( empty( $processed_content['content'] ) ) {
				$results['skipped'][] = array(
					'post_id' => $post_id,
					'reason'  => 'Empty content'
				);
				continue;
			}
			
			// Check file size
			if ( $processed_content['size'] > EPKB_AI_Config::MAX_FILE_SIZE ) {
				$results['failed'][] = array(
					'post_id' => $post_id,
					'error'   => 'Content exceeds maximum file size'
				);
				continue;
			}
			
			// Upload file directly to vector store
			$file_result = $this->upload_file_to_vector_store( 
				$vector_store_id,
				$processed_content['content'],
				self::FILENAME_PREFIX . $post_id . '.txt',
				$processed_content['metadata']
			);
			
			if ( is_wp_error( $file_result ) ) {
				EPKB_AI_Utilities::add_log( 'File upload to vector store failed for post', array(
					'post_id' => $post_id,
					'vector_store_id' => $vector_store_id,
					'file_size' => $processed_content['size'],
					'error_code' => $file_result->get_error_code(),
					'error_message' => $file_result->get_error_message()
				) );
				$results['failed'][] = array(
					'post_id' => $post_id,
					'error'   => $file_result->get_error_message()
				);
				continue;
			}
			
			$results['success'][] = array(
				'post_id' => $post_id,
				'file_id' => $file_result['id']
			);
		}
		
		return $results;
	}
	
	/**
	 * Upload file directly to vector store
	 *
	 * @param string $vector_store_id
	 * @param string $content
	 * @param string $filename
	 * @param array $metadata
	 * @return array|WP_Error File object or WP_Error
	 */
	private function upload_file_to_vector_store( $vector_store_id, $content, $filename, $metadata = array() ) {
		
		// Validate content is not empty
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_content', 'File content cannot be empty' );
		}
		
		// Validate content size (limit to 1MB for KB articles)
		$content_size = strlen( $content );
		if ( $content_size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'content_too_large', sprintf( __( 'File content exceeds limit. Size %d', 'echo-knowledge-base' ), $content_size ) );
		}
		
		// Comprehensive security validation for file content
		// Note: This is for knowledge base content, not executable files
		
		// Check for script tags (including obfuscated versions)
		$script_patterns = array(
			'/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is',
			'/<\s*script[^>]*\/>/is',
			'/on\w+\s*=\s*["\'].*?["\']/is', // Event handlers like onclick, onload, etc.
			'/javascript\s*:/is',
			'/vbscript\s*:/is',
			'/data\s*:\s*text\/html/is',
		);
		
		foreach ( $script_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return new WP_Error( 'invalid_content', 'File contains potentially malicious script content' );
			}
		}
		
		// Check for PHP and other server-side code
		$server_code_patterns = array(
			'/<\?php/i',
			'/<\?=/i',
			'/<\?/i',
			'/<%/i', // ASP tags
			'/<jsp:/i', // JSP tags
		);
		
		foreach ( $server_code_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return new WP_Error( 'invalid_content', 'File contains server-side code which is not allowed' );
			}
		}
		
		// Check for iframe tags
		if ( preg_match( '/<\s*iframe[^>]*>/is', $content ) ) {
			return new WP_Error( 'invalid_content', 'File contains iframe tags which are not allowed' );
		}
		
		// Check for object and embed tags
		if ( preg_match( '/<\s*(object|embed|applet)[^>]*>/is', $content ) ) {
			return new WP_Error( 'invalid_content', 'File contains potentially dangerous embed content' );
		}
		
		// Sanitize content using WordPress functions
		$allowed_html = wp_kses_allowed_html( 'post' );
		// Remove script related tags from allowed HTML
		unset( $allowed_html['script'] );
		unset( $allowed_html['style'] );
		unset( $allowed_html['link'] );
		unset( $allowed_html['meta'] );
		
		// Apply WordPress content sanitization
		$sanitized_content = wp_kses( $content, $allowed_html );
		
		// If content was significantly modified by sanitization, it likely contained malicious content
		if ( strlen( $sanitized_content ) < strlen( $content ) * 0.8 ) {
			return new WP_Error( 'invalid_content', 'File contains too much potentially dangerous content' );
		}
		
		// Prepare the request data for JSON API
		$data = array(
			'file' => array(
				'data' => base64_encode( $content ),
				'name' => $filename
			)
		);
		
		// Add metadata if provided
		if ( ! empty( $metadata ) ) {
			$validated_metadata = $this->config->validate_metadata( $metadata );
			if ( ! empty( $validated_metadata ) ) {
				$data['metadata'] = $validated_metadata;
			}
		}
		
		// Upload file directly to vector store using JSON request
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files',
			$data,
			'POST',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		if ( empty( $response['id'] ) ) {
			return new WP_Error( 'upload_failed', 'Failed to upload file to vector store: No file ID returned' );
		}
		
		// Wait for file to be processed
		$wait_result = $this->wait_for_file_processing( $vector_store_id, $response['id'] );
		if ( is_wp_error( $wait_result ) ) {
			// File was uploaded but processing failed
			// Try to delete the file to clean up
			$this->delete_vector_store_file( $vector_store_id, $response['id'] );
			return $wait_result;
		}
		
		return $response;
	}
	
	/**
	 * Check vector store file status
	 *
	 * @param string $vector_store_id
	 * @param string $file_id
	 * @return array|WP_Error
	 */
	private function check_vector_store_file_status( $vector_store_id, $file_id ) {
		
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files/' . $file_id,
			array(),
			'GET',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		return $response;
	}
	
	/**
	 * Wait for vector store file to be processed
	 *
	 * @param string $vector_store_id
	 * @param string $file_id
	 * @param int $timeout Maximum wait time in seconds
	 * @return bool|WP_Error
	 */
	private function wait_for_file_processing( $vector_store_id, $file_id, $timeout = 60 ) {
		
		$start_time = time();
		$wait_interval = 1;
		
		while ( ( time() - $start_time ) < $timeout ) {
			
			$response = $this->check_vector_store_file_status( $vector_store_id, $file_id );
			
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			
			if ( ! empty( $response['status'] ) ) {
				if ( $response['status'] === 'completed' ) {
					return true;
				} elseif ( in_array( $response['status'], array( 'failed', 'cancelled' ) ) ) {
					$error_message = ! empty( $response['last_error']['message'] ) ? $response['last_error']['message'] : $response['status'];
					return new WP_Error( 
						'file_processing_failed',
						sprintf( 'File processing failed: %s', $error_message )
					);
				}
			}
			
			// Wait before checking again - this is necessary for polling external API status
			// Using sleep() is appropriate here as we're waiting for an async operation to complete
			// This runs in AJAX context so it won't block the main page load
			sleep( min( $wait_interval, 5 ) );
			$wait_interval = min( $wait_interval * 1.5, 5 );
		}
		
		EPKB_AI_Utilities::add_log( 'Vector store file processing timeout', array(
			'vector_store_id' => $vector_store_id,
			'file_id' => $file_id,
			'elapsed_time' => time() - $start_time,
			'timeout' => $timeout
		) );
		
		return new WP_Error( 'timeout', 'File processing timed out' );
	}
	
	/**
	 * Clear all files from vector store
	 *
	 * @param string $vector_store_id
	 * @return bool|WP_Error
	 */
	public function clear_vector_store_files( $vector_store_id ) {
		return $this->delete_all_vector_store_files( $vector_store_id );
	}
	
	/**
	 * Delete all files from vector store
	 *
	 * @param string $vector_store_id
	 * @return bool|WP_Error
	 */
	private function delete_all_vector_store_files( $vector_store_id ) {
		
		$file_ids = array();
		$last_id = '';
		$limit = 100;
		
		// Paginate through all files
		for ( $i = 0; $i < 50; $i++ ) { // Safety limit
			
			$params = array( 'limit' => $limit );
			if ( ! empty( $last_id ) ) {
				$params['after'] = $last_id;
			}
			
			$response = $this->api_client->request( 
				self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files',
				$params,
				'GET',
				array( 'OpenAI-Beta' => 'assistants=v2' )
			);
			
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			
			if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
				break;
			}
			
			foreach ( $response['data'] as $file ) {
				if ( ! empty( $file['id'] ) ) {
					$file_ids[] = $file['id'];
				}
			}
			
			if ( empty( $response['has_more'] ) || empty( $response['last_id'] ) ) {
				break;
			}
			
			$last_id = $response['last_id'];
		}
		
		// Delete all collected files from vector store
		if ( ! empty( $file_ids ) ) {
			$this->process_vector_store_file_deletions( $file_ids, $vector_store_id );
		}
		
		return true;
	}
	
	/**
	 * Process vector store file deletions in batch
	 *
	 * @param array $file_ids
	 * @param string $vector_store_id
	 * @return void
	 */
	private function process_vector_store_file_deletions( $file_ids, $vector_store_id ) {
		foreach ( $file_ids as $file_id ) {
			$delete_result = $this->delete_vector_store_file( $vector_store_id, $file_id );
			if ( is_wp_error( $delete_result ) ) {
				// Log but continue with other files
				EPKB_AI_Utilities::add_log( 'Failed to delete file from vector store', array(
					'file_id' => $file_id,
					'vector_store_id' => $vector_store_id,
					'error_code' => $delete_result->get_error_code(),
					'error_message' => $delete_result->get_error_message()
				) );
			}
		}
	}
	
	/**
	 * Delete a file from vector store
	 *
	 * @param string $vector_store_id
	 * @param string $file_id
	 * @return bool|WP_Error
	 */
	private function delete_vector_store_file( $vector_store_id, $file_id ) {
		
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files/' . $file_id,
			array(),
			'DELETE',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		return true;
	}
	
	/**
	 * Get vector store files
	 *
	 * @param string $vector_store_id
	 * @param array $params
	 * @return array|WP_Error
	 */
	public function get_vector_store_files( $vector_store_id, $params = array() ) {
		
		$response = $this->api_client->request( 
			self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files',
			$params,
			'GET',
			array( 'OpenAI-Beta' => 'assistants=v2' )
		);
		
		return $response;
	}
}