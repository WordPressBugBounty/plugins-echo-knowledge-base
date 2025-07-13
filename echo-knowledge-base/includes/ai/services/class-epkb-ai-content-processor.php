<?php

/**
 * Content Processor Service
 * 
 * Processes and cleans content for AI consumption.
 */
class EPKB_AI_Content_Processor {
	
	/**
	 * Process a post for AI
	 *
	 * @param int $post_id
	 * @return array Array with 'content', 'metadata', 'size'
	 */
	public function process_post( $post_id ) {
		
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
	 * Process multiple posts
	 *
	 * @param array $post_ids
	 * @return array Array of processed posts
	 */
	public function process_posts( $post_ids ) {
		$results = array();

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = $this->process_post( $post_id );
		}

		return $results;
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
		
		// Apply length limit
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
		$content = preg_replace( '/\[([^\]]+)\](.*?)\[\/\1\]/s', '$2', $content );
		
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
		
		if ( strlen( $content ) <= $max_length ) {
			return $content;
		}
		
		// Truncate to max length
		$content = substr( $content, 0, $max_length );
		
		// Try to end at a sentence boundary
		$sentences = array( '. ', '! ', '? ' );
		$last_sentence_pos = 0;
		
		foreach ( $sentences as $sentence_end ) {
			$pos = strrpos( $content, $sentence_end );
			if ( $pos !== false && $pos > $last_sentence_pos ) {
				$last_sentence_pos = $pos;
			}
		}
		
		// If we found a sentence boundary in the last 20% of content, use it
		if ( $last_sentence_pos > ( $max_length * 0.8 ) ) {
			$content = substr( $content, 0, $last_sentence_pos + 1 );
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
	 * Generate a clean title from content
	 *
	 * @param string $content
	 * @param int $max_length
	 * @return string
	 */
	public function generate_title_from_content( $content, $max_length = 255 ) {
		// Remove HTML tags
		$title = wp_strip_all_tags( $content );
		
		// Decode HTML entities
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Remove special characters and normalize whitespace
		$title = preg_replace( '/[^\p{L}\p{N}\s\-.,!?]/u', '', $title );
		$title = preg_replace( '/\s+/', ' ', $title );
		
		// Trim to first sentence or question if possible
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
				}
			}
		}
		
		// Final cleanup
		$title = trim( $title );
		
		// Add ellipsis if truncated
		if ( mb_strlen( $content ) > mb_strlen( $title ) && ! preg_match( '/[.!?]$/', $title ) ) {
			$title .= '...';
		}
		
		return $title;
	}
}