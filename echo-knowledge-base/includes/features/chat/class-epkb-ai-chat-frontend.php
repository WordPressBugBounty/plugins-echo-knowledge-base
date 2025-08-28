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

		return true;
	}
}