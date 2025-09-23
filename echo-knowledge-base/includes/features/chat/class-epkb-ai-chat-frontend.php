<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Display AI Chat widget on the frontend
 */
class EPKB_AI_Chat_Frontend {

	public function __construct() {
		// Hook into wp_footer with high priority to ensure it loads after theme
		add_action( 'wp_footer', array( $this, 'maybe_display_chat_widget' ), 999 );
	}

	/**
	 * Check if chat should be displayed and output the widget
	 */
	public function maybe_display_chat_widget() {

		if ( ! self::can_display_chat_widget() ) {
			return;
		}

		// Allow filtering of where to display the chat
		$display_chat = apply_filters( 'epkb_display_ai_chat', true );
		if ( ! $display_chat ) {
			return;
		}
		
		// Output the chat widget root element
		$this->output_chat_widget_html();
	}

	/**
	 * Output the HTML for the chat widget
	 */
	private function output_chat_widget_html() {
		
		// Use output buffering to ensure clean output
		ob_start();		?>

		<!-- EPKB AI Chat Widget -->
		<div id="epkb-ai-chat-widget-root" class="epkb-ai-chat-widget-root" data-is-admin="<?php echo esc_attr( current_user_can( 'manage_options' ) ? 'true' : 'false' ); ?>"></div>
		<script>
			// Initialize the chat widget root element ID for the script
			window.epkbChatWidgetRoot = 'epkb-ai-chat-widget-root';
		</script>   <?php
		
		$output = ob_get_clean();
		
		// Use wp_footer action to ensure proper placement
		echo $output;
	}

		/**
	 * Return current widget
	 *
	 * @return bool
	 */
	private static function can_display_chat_widget() {

		$ai_chat_enabled = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_enabled', 'off' );
		if ( $ai_chat_enabled == 'off' || ( $ai_chat_enabled == 'preview' && ! current_user_can( 'manage_options' ) ) ) {
			return false;
		}

		$display_mode = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_display_mode', 'all_pages' );

		// Show on all pages - quick return
		if ( $display_mode === 'all_pages' ) {
			return true;
		}

		// Determine if we're looking for matches or exclusions
		$is_selected_only = ( $display_mode === 'selected_only' );

		// Check basic WordPress page types first (cheapest checks)
		$page_rules = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_display_page_rules', array() );
		if ( ! empty( $page_rules ) ) {
			// Check Posts
			if ( in_array( 'posts', $page_rules ) && is_single() && get_post_type() === 'post' ) {
				return $is_selected_only; // Match found - return true for selected_only, false for all_except
			}
			// Check Pages  
			if ( in_array( 'pages', $page_rules ) && is_page() ) {
				return $is_selected_only; // Match found
			}
		}

		// Check Knowledge Base pages (more expensive checks)
		$kb_post_types = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_display_other_post_types', array() );
		if ( ! empty( $kb_post_types ) ) {
			// Check KB Main Page
			if ( EPKB_Utilities::is_kb_main_page() ) {
				// Check if any selected KB matches this main page
				foreach ( $kb_post_types as $kb_post_type ) {
					// Extract KB ID from post type (e.g., 'epkb_post_type_1' -> 1)
					if ( preg_match( '/epkb_post_type_(\d+)/', $kb_post_type, $matches ) ) {
						$kb_id = intval( $matches[1] );
						// Check if this is the main page for this KB
						$kb_main_pages = epkb_get_instance()->kb_config_obj->get_value( $kb_id, 'kb_main_pages', null );
						if ( ! empty( $kb_main_pages ) && is_page( $kb_main_pages ) ) {
							return $is_selected_only; // Match found
						}
					}
				}
			}
			
			// Check KB Article Pages
			$current_post_type = get_post_type();
			if ( $current_post_type && in_array( $current_post_type, $kb_post_types ) ) {
				return $is_selected_only; // Match found
			}
			
			// Check KB Category/Tag Archives
			foreach ( $kb_post_types as $kb_post_type ) {
				// Extract KB ID from post type
				if ( preg_match( '/epkb_post_type_(\d+)/', $kb_post_type, $matches ) ) {
					$kb_id = intval( $matches[1] );
					$kb_category = EPKB_KB_Handler::get_category_taxonomy_name( $kb_id );
					$kb_tag = EPKB_KB_Handler::get_tag_taxonomy_name( $kb_id );
					
					if ( is_tax( $kb_category ) || is_tax( $kb_tag ) ) {
						return $is_selected_only; // Match found
					}
				}
			}
		}

		// Check URL patterns last (most expensive - string operations)
		$url_patterns = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_chat_display_url_patterns', '' );
		$url_match = self::match_current_url( $url_patterns, $is_selected_only );
		if ( $url_match !== null ) {
			return $url_match; // Match found
		}

		// No matches found
		// For selected_only: return false (don't show)
		// For all_except: return true (show)
		return ! $is_selected_only;
	}

	/**
	 * Check if current frontend URL matches any configured patterns.
	 *
	 * @param string $url_patterns Comma-separated list of patterns (supports * wildcards).
	 * @param mixed  $is_selected_only Value to return when a match is found.
	 * @return mixed|null
	 */
	private static function match_current_url( $url_patterns, $is_selected_only ) {

		if ( empty( $url_patterns ) ) {
			return null;
		}

		global $wp;
		// Get current request path (no domain, no query string)
		$path = '/' . ltrim( $wp->request, '/' );
		$path = rawurldecode( $path );
		$path = untrailingslashit( $path ); // normalize trailing slash

		$patterns = array_filter( array_map( 'trim', explode( ',', $url_patterns ) ) );

		foreach ( $patterns as $pattern ) {
			if ( $pattern === '' ) {
				continue;
			}

			// Ensure leading slash & normalize
			$pattern = '/' . ltrim( $pattern, '/' );
			$pattern = untrailingslashit( $pattern );

			// Exact match first
			if ( $path === $pattern ) {
				return $is_selected_only;
			}

			// Wildcard match
			if ( function_exists( 'fnmatch' ) && fnmatch( $pattern, $path, FNM_CASEFOLD ) ) {
				return $is_selected_only;
			}

			// Prefix style: allow pattern to match sub-paths (e.g. /kb matches /kb/foo)
			if ( strpos( $path, $pattern . '/' ) === 0 ) {
				return $is_selected_only;
			}
		}

		return null; // no match
	}
}