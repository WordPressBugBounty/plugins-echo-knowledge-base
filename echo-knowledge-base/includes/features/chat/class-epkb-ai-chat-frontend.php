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

		// Get the KB configuration
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config( EPKB_KB_Config_DB::DEFAULT_KB_ID ); // TODO determine KB ID
		
		// Check if AI chat is enabled
		if ( empty( $kb_config['ai_chat_enabled'] ) || $kb_config['ai_chat_enabled'] !== 'on' ) {
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
		</script>		<?php
		
		$output = ob_get_clean();
		
		// Use wp_footer action to ensure proper placement
		echo wp_kses( $output, array(
			'div' => array(
				'id'    => array(),
				'class' => array(),
				'data-is-admin' => array(),
			),
			'script' => array(),
		) );
	}

		/**
	 * Return current widget
	 *
	 * @return array|null
	 */
	private static function can_display_chat_widget() {

		// is this page or post or main page to display the Help Dialog on?
		$post = get_queried_object();
		if ( empty( $post ) ) {
			return false;
		}

		// woocommerce shop page. Queried object is not WP_Post for woo shop page, so we need special code for edge case
		 /** @disregard P1010 */
		if ( function_exists( 'is_shop' ) && function_exists( 'wc_get_page_id' ) && is_shop() ) {
			/** @disregard P1010 */
			$page_id = wc_get_page_id( 'shop' );
			if ( empty( $page_id ) || $page_id < 1 ) {
				return false;
			}
			$post = get_post( $page_id );
		}

		$is_front_page = is_front_page();

		if ( ! $is_front_page && ( empty( $post ) || ( get_class( $post ) != 'WP_Post' && get_class( $post ) != 'WP_Post_Type' && get_class( $post ) != 'WP_Term' ) ) ) {
			return false;
		}

		/* if ( ! empty( $post ) && get_class( $post ) == 'WP_Term' ) {
			$post_type = 'taxonomy';
			$key       = '';
		} elseif ( $is_front_page ) {
			$post_type = 'page';
			$key       = 0; // 'Home Page' is always signed to '0' ID as it is not dependent to any page ID
		} elseif ( empty( $post->post_type ) ) {
			return false;
		} elseif ( $post->post_type == 'post' || $post->post_type == 'page' ) {
			$post_type = $post->post_type;
			$key       = empty( $post->ID ) ? '' : $post->ID;
		} else {
			$post_type = 'cpt';
			$key       = $post->post_type;
		} */

		return true;
	}
}