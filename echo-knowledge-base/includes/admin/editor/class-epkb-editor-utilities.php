<?php

/**
 * Various utility functions for editor 
 *
 * @copyright   Copyright (C) 2020, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_Editor_Utilities {

	/**
	 * Determine what page are we editing in the visual Editor
	 * @return string
	 */
	public static function epkb_front_end_editor_type() {
		global $post;

		$editor_type = '';

		if ( is_archive() ) {

			$editor_type = 'archive-page';

		} else if ( ! empty( $post ) && $post->post_type == 'page' && EPKB_Utilities::is_kb_main_page() ) {

			$editor_type =  EPKB_Utilities::is_advanced_search_enabled() && EPKB_Utilities::get( 'kbsearch' ) ? 'search-page' : 'main-page';

		} else if ( ! empty( $post ) && EPKB_KB_Handler::is_kb_post_type( $post->post_type ) ) {
			
			$editor_type = 'article-page';
		}

		return $editor_type;
	}
}