<?php

/**
 * Content Processor Service
 * 
 * Processes and cleans content for AI consumption.
 */
class EPKB_AI_Content_Processor {
	
	/**
	 * Prepare a post for AI
	 *
	 * @param int $post_id
	 * @return array Array with 'content', 'metadata', 'size'
	 */
	public function prepare_post( $post_id ) {
		
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return array(
				'content'  => '',
				'metadata' => array(),
				'size'     => 0
			);
		}
		
		// Clean content
		$content = $this->clean_content( $post->post_content, $post_id );
		
		// Build metadata
		$metadata = $this->build_post_metadata( $post );
		
		return array(
			'content'  => $content,
			'metadata' => $metadata,
			'size'     => strlen( $content )
		);
	}

	/**
	 * Clean content for vector storage
	 *
	 * @param string $content
	 * @param int $post_id
	 * @return string
	 */
	public function clean_content( $content, $post_id = 0 ) {
		
		// Allow customization before processing
		if ( $post_id ) {
			$content = apply_filters( 'epkb_pre_clean_post_content', $content, $post_id );
		}
		
		// Apply initial length limit to prevent regex timeouts on very large content
		$content = $this->apply_length_limit( $content, $post_id );
		
		// Remove WordPress block comments
		$content = preg_replace( '/<!--\s*\/?wp:[^\>]+-->/s', '', $content );
		
		// Remove HTML comments
		$content = preg_replace( '/<!--.*?-->/s', '', $content );
		
		// Remove Echo KB specific shortcodes
		$content = preg_replace( '/\[epkb[-_].*?\]/s', '', $content );
		
		// Process shortcodes based on filter
		$process_shortcodes = apply_filters( 'epkb_process_shortcodes_in_ai', false );
		if ( $process_shortcodes ) {
			$content = do_shortcode( $content );
		} else {
			// Remove shortcodes but keep content
			$content = $this->strip_shortcodes_keep_content( $content );
		}
		
		// Decode HTML entities
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Convert to plain text
		$content = wp_strip_all_tags( $content, true );
		
		// Normalize whitespace
		$content = $this->normalize_whitespace( $content );
		
		// Remove control characters
		$content = preg_replace( '/[\x00-\x1F\x7F]/u', '', $content );
		
		// Apply final length limit in case content expanded during processing
		$content = $this->apply_length_limit( $content, $post_id );
		
		// Allow customization after processing
		if ( $post_id ) {
			$content = apply_filters( 'epkb_post_clean_content', $content, $post_id );
		}
		
		return $content;
	}
	
	/**
	 * Strip shortcodes but keep their content
	 *
	 * @param string $content
	 * @return string
	 */
	private function strip_shortcodes_keep_content( $content ) {
		// Remove self-closing shortcodes
		$content = preg_replace( '/\[[^\]]+\/\]/s', '', $content );
		
		// Remove paired shortcodes but keep content
		// Capture only the tag name (first word), not attributes
		$content = preg_replace( '/\[(\w+)(?:\s[^\]]+)?\](.*?)\[\/\1\]/s', '$2', $content );
		
		// Remove any remaining shortcodes
		$content = preg_replace( '/\[[^\]]+\]/s', '', $content );
		
		return $content;
	}
	
	/**
	 * Normalize whitespace
	 *
	 * @param string $content
	 * @return string
	 */
	private function normalize_whitespace( $content ) {
		// Replace tabs with spaces
		$content = str_replace( "\t", ' ', $content );
		
		// Replace multiple spaces with single space
		$content = preg_replace( '/[ ]+/', ' ', $content );
		
		// Normalize line breaks
		$content = preg_replace( '/\r\n|\r/', "\n", $content );
		
		// Convert multiple line breaks to double line break
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );
		
		// Trim each line
		$lines = explode( "\n", $content );
		$lines = array_map( 'trim', $lines );
		$content = implode( "\n", $lines );
		
		// Final trim
		return trim( $content );
	}
	
	/**
	 * Apply length limit to content
	 *
	 * @param string $content
	 * @param int $post_id
	 * @return string
	 */
	private function apply_length_limit( $content, $post_id ) {
		$max_length = apply_filters( 'epkb_ai_content_max_length', 50000, $post_id );
		
		if ( mb_strlen( $content, 'UTF-8' ) <= $max_length ) {
			return $content;
		}
		
		// Truncate to max length
		$content = mb_substr( $content, 0, $max_length, 'UTF-8' );
		
		// Try to end at a sentence boundary
		$sentences = array( '. ', '! ', '? ' );
		$last_sentence_pos = 0;
		
		foreach ( $sentences as $sentence_end ) {
			$pos = mb_strrpos( $content, $sentence_end, 0, 'UTF-8' );
			if ( $pos !== false && $pos > $last_sentence_pos ) {
				$last_sentence_pos = $pos;
			}
		}
		
		// If we found a sentence boundary in the last 20% of content, use it
		if ( $last_sentence_pos > ( $max_length * 0.8 ) ) {
			$content = mb_substr( $content, 0, $last_sentence_pos + 1, 'UTF-8' );
		}
		
		return $content;
	}
	
	/**
	 * Build post metadata
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	private function build_post_metadata( $post ) {
		
		// Core metadata fields for proper citations
		$metadata = array(
			'post_id'   => strval( $post->ID ),  // Stable handle for the post
			'title'     => $this->clean_post_title( $post->post_title ),  // For nice citations
			'language'  => $this->get_post_language_code( $post->ID )  // Language code (en, es, etc.)
		);
		
		// Additional metadata
		$metadata['url'] = get_permalink( $post->ID );
		
		// Add author
		/* if ( ! empty( $post->post_author ) ) {  // privacy
			$author = get_userdata( $post->post_author );
			if ( $author ) {
				$metadata['author'] = $author->display_name;
			}
		} */

		// Add custom metadata via filter
		// $metadata = apply_filters( 'epkb_ai_post_metadata', $metadata, $post );
		
		return $metadata;
	}
	
	/**
	 * Get post language code (e.g., "en", "es")
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function get_post_language_code( $post_id ) {
		$locale = EPKB_Language_Utilities::get_post_language( $post_id );
		// Extract language code from locale (e.g., "en_US" -> "en")
		$parts = explode( '_', $locale );
		return $parts[0];
	}
	
	/**
	 * Clean title for metadata
	 *
	 * @param string $title
	 * @return string
	 */
	private function clean_post_title( $title ) {
		// Decode HTML entities
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Strip tags
		$title = wp_strip_all_tags( $title );
		
		// Normalize whitespace
		$title = preg_replace( '/\s+/', ' ', $title );
		
		// Trim to reasonable length
		return mb_substr( trim( $title ), 0, 100 );
	}
	
	/**
	 * Generate a title from content by extracting the first sentence or truncating.
	 *
	 * @param string $content The content to generate title from
	 * @param int $max_length Maximum length for the title (default 100)
	 * @return string Generated title
	 */
	public static function generate_title_from_content( $content, $max_length = 100 ) {
		// Remove HTML tags
		$title = wp_strip_all_tags( $content );
		
		// Decode HTML entities
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Remove special characters and normalize whitespace
		$title = preg_replace( '/[^\p{L}\p{N}\s\-.,!?]/u', '', $title );
		$title = preg_replace( '/\s+/', ' ', trim( $title ) );
		
		// Try to extract first sentence
		$sentences = preg_split( '/(?<=[.!?])\s+/', $title, 2 );
		if ( ! empty( $sentences[0] ) && mb_strlen( $sentences[0] ) <= $max_length ) {
			$title = $sentences[0];
		} else {
			// Otherwise truncate at word boundary
			if ( mb_strlen( $title ) > $max_length ) {
				$title = mb_substr( $title, 0, $max_length );
				$last_space = mb_strrpos( $title, ' ' );
				if ( $last_space !== false && $last_space > ( $max_length * 0.7 ) ) {
					$title = mb_substr( $title, 0, $last_space );
				} else {
					// If no good word boundary, just truncate and add ellipsis
					$title = mb_substr( $title, 0, $max_length - 3 ) . '...';
				}
			}
		}
		
		// Final cleanup
		$title = trim( $title );
		
		// Ensure title is not empty
		if ( empty( $title ) ) {
			$title = __( 'Untitled', 'echo-knowledge-base' );
		}
		
		return $title;
	}
	
	/**
	 * Process an attachment for AI
	 *
	 * @param int $attachment_id
	 * @return array Array with 'content', 'metadata', 'size'
	 */
	public function process_attachment( $attachment_id ) {
		
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return array(
				'content'  => '',
				'metadata' => array(),
				'size'     => 0
			);
		}
		
		// Get file path and mime type
		$file_path = get_attached_file( $attachment_id );
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			EPKB_AI_Log::add_log( 'Attachment file not found', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path
			) );
			return array(
				'content'  => '',
				'metadata' => array(),
				'size'     => 0
			);
		}
		
		// Check file size limit before processing
		$file_size = filesize( $file_path );
		$max_size = $this->get_max_attachment_size( $mime_type );
		if ( $file_size > $max_size ) {
			EPKB_AI_Log::add_log( 'Attachment exceeds size limit', array(
				'attachment_id' => $attachment_id,
				'mime_type' => $mime_type,
				'file_size' => $file_size,
				'max_size' => $max_size
			) );
			return array(
				'content'  => '',
				'metadata' => array(),
				'size'     => 0
			);
		}
		
		// Extract content based on file type
		$content = '';
		switch ( $mime_type ) {
			case 'text/plain':
			case 'text/csv':
			case 'text/xml':
			case 'application/xml':
				$content = $this->extract_text_file_content( $file_path );
				break;
				
			case 'application/pdf':
				$content = $this->extract_pdf_content( $file_path );
				break;
				
			case 'application/msword':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$content = $this->extract_doc_content( $file_path, $mime_type );
				break;
				
			case 'image/jpeg':
			case 'image/png':
			case 'image/gif':
			case 'image/webp':
				$content = $this->extract_image_content( $attachment );
				break;
				
			default:
				// Try to extract text content for other file types
				if ( strpos( $mime_type, 'text/' ) === 0 ) {
					$content = $this->extract_text_file_content( $file_path );
				} else {
					// Log unsupported MIME type
					EPKB_AI_Log::add_log( 'Unsupported attachment MIME type for extraction', array(
						'attachment_id' => $attachment_id,
						'mime_type' => $mime_type
					) );
				}
				break;
		}
		
		// Log if extraction resulted in empty content
		if ( empty( $content ) ) {
			EPKB_AI_Log::add_log( 'Empty content extracted from attachment', array(
				'attachment_id' => $attachment_id,
				'mime_type' => $mime_type,
				'file_path' => $file_path
			) );
		}
		
		// Clean the extracted content
		if ( ! empty( $content ) ) {
			$content = $this->clean_content( $content );
		}
		
		// Build metadata
		$metadata = $this->build_attachment_metadata( $attachment );

		return array(
			'content'  => $content,
			'metadata' => $metadata,
			'size'     => strlen( $content )
		);
	}
	
	/**
	 * Extract content from text-based files
	 *
	 * @param string $file_path
	 * @return string
	 */
	private function extract_text_file_content( $file_path ) {

		// Limit file size to prevent memory issues
		$max_size = apply_filters( 'epkb_ai_max_text_file_size', 5 * MB_IN_BYTES );
		if ( filesize( $file_path ) > $max_size ) {
			return '';
		}
		
		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			return '';
		}
		
		// Detect and convert encoding to UTF-8
		$encoding = mb_detect_encoding( $content, 'UTF-8, ISO-8859-1, Windows-1252', true );
		if ( $encoding && $encoding !== 'UTF-8' ) {
			$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
		}
		
		return $content;
	}
	
	/**
	 * Extract content from PDF files
	 *
	 * @param string $file_path
	 * @return string
	 */
	private function extract_pdf_content( $file_path ) {

		// This is a placeholder - actual PDF extraction would require a library like pdfparser
		// For now, return empty content or attachment description
		$content = '';
		
		// Allow third-party PDF extraction via filter
		$content = apply_filters( 'epkb_ai_extract_pdf_content', $content, $file_path );
		
		// Log that PDF extraction is not available
		if ( empty( $content ) ) {
			EPKB_AI_Log::add_log( 'No extractor for application/pdf – skipped', array('file_path' => $file_path, 'mime_type' => 'application/pdf', 'context' => 'extract_pdf_content') );
		}
		
		return $content;
	}
	
	/**
	 * Extract content from Word documents
	 *
	 * @param string $file_path
	 * @param string $mime_type
	 * @return string
	 */
	private function extract_doc_content( $file_path, $mime_type ) {

		// This is a placeholder - actual DOC/DOCX extraction would require a library
		$content = '';
		
		// Allow third-party document extraction via filter
		$content = apply_filters( 'epkb_ai_extract_doc_content', $content, $file_path, $mime_type );
		
		// Log that document extraction is not available
		if ( empty( $content ) ) {
			error_log( "AI-Sync: No extractor for $mime_type – skipped {$file_path}" );
		}
		
		return $content;
	}
	
	/**
	 * Extract content from images (alt text, caption, description)
	 *
	 * @param WP_Post $attachment
	 * @return string
	 */
	private function extract_image_content( $attachment ) {
		$content_parts = array();
		
		// Get alt text
		$alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		if ( ! empty( $alt_text ) ) {
			$content_parts[] = sprintf( __( 'Alt text: %s', 'echo-knowledge-base' ), $alt_text );
		}
		
		// Get caption
		if ( ! empty( $attachment->post_excerpt ) ) {
			$content_parts[] = sprintf( __( 'Caption: %s', 'echo-knowledge-base' ), $attachment->post_excerpt );
		}
		
		// Get description
		if ( ! empty( $attachment->post_content ) ) {
			$content_parts[] = sprintf( __( 'Description: %s', 'echo-knowledge-base' ), $attachment->post_content );
		}
		
		// Get title
		if ( ! empty( $attachment->post_title ) ) {
			$content_parts[] = sprintf( __( 'Title: %s', 'echo-knowledge-base' ), $attachment->post_title );
		}
		
		return implode( "\n\n", $content_parts );
	}
	
	/**
	 * Build attachment metadata
	 *
	 * @param WP_Post $attachment
	 * @return array
	 */
	private function build_attachment_metadata( $attachment ) {
		
		$metadata = array(
			'attachment_id' => strval( $attachment->ID ),
			'title'         => $this->clean_post_title( $attachment->post_title ),
			'type'          => 'attachment',
			'mime_type'     => get_post_mime_type( $attachment->ID )
		);
		
		// Add file info
		$file_path = get_attached_file( $attachment->ID );
		if ( file_exists( $file_path ) ) {
			$metadata['file_name'] = basename( $file_path );
			$metadata['file_size'] = filesize( $file_path );
		}
		
		// Add URL
		$metadata['url'] = wp_get_attachment_url( $attachment->ID );
		
		// Add parent post if exists
		if ( $attachment->post_parent ) {
			$metadata['parent_post_id'] = strval( $attachment->post_parent );
			$parent = get_post( $attachment->post_parent );
			if ( $parent ) {
				$metadata['parent_post_title'] = $this->clean_post_title( $parent->post_title );
			}
		}
		
		// Add upload date
		$metadata['upload_date'] = $attachment->post_date;
		
		return $metadata;
	}
	
	/**
	 * Get supported MIME types for AI processing
	 *
	 * @return array
	 */
	private function get_supported_mime_types() {
		$supported_types = array(
			'text/plain',
			'text/csv',
			'text/xml',
			'application/xml',
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp'
		);
		
		// Allow customization of supported types
		return apply_filters( 'epkb_ai_supported_attachment_types', $supported_types );
	}

	/**
	 * Check if attachment type is supported for AI processing
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public function is_supported_attachment_type( $mime_type ) {
		$supported_types = $this->get_supported_mime_types();
		
		// Check exact match
		if ( in_array( $mime_type, $supported_types, true ) ) {
			return true;
		}
		
		// Check if it's a text-based mime type
		if ( strpos( $mime_type, 'text/' ) === 0 ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get maximum file size for attachment processing
	 *
	 * @param string $mime_type
	 * @return int File size in bytes
	 */
	private function get_max_attachment_size( $mime_type ) {
		// Define size limits for supported MIME types
		$max_sizes = array(
			'text/plain'     => 5 * MB_IN_BYTES,
			'text/csv'       => 10 * MB_IN_BYTES,
			'text/xml'       => 10 * MB_IN_BYTES,
			'application/xml' => 10 * MB_IN_BYTES,
			'application/pdf' => 20 * MB_IN_BYTES,
			'application/msword' => 15 * MB_IN_BYTES,
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 15 * MB_IN_BYTES,
			'image/jpeg'     => 10 * MB_IN_BYTES,
			'image/png'      => 10 * MB_IN_BYTES,
			'image/gif'      => 5 * MB_IN_BYTES,
			'image/webp'     => 10 * MB_IN_BYTES,
			'default'        => 5 * MB_IN_BYTES
		);
		
		$max_size = isset( $max_sizes[ $mime_type ] ) ? $max_sizes[ $mime_type ] : $max_sizes['default'];
		
		// Allow customization
		return apply_filters( 'epkb_ai_max_attachment_size', $max_size, $mime_type );
	}
}