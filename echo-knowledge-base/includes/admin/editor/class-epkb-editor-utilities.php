<?php

/**
 * Various utility functions for editor 
 *
 * @copyright   Copyright (C) 2020, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_Editor_Utilities {

	/**
	 * Get visual Editor URLs
	 *
	 * @param $kb_config
	 * @param string $main_page_zone_name
	 * @param string $article_page_zone_name
	 * @param string $archive_page_zone_name
	 * @param bool $use_backend_mode
	 * @param string $preopen_setting
	 * @return array
	 */
	public static function get_editor_urls( $kb_config, $main_page_zone_name='', $article_page_zone_name='', $archive_page_zone_name='', $use_backend_mode = true, $preopen_setting = '' ) {    // TODO

		$params = array( 'action' => 'epkb_load_editor' );
		if ( ! empty( $preopen_setting ) ) {
			$params['preopen_setting'] = $preopen_setting;
		}

		$main_page_zone_name = EPKB_Core_Utilities::run_setup_wizard_first_time() ? 'templates' : $main_page_zone_name;

		$first_main_page_url = EPKB_KB_Handler::get_first_kb_main_page_url( $kb_config );
		$main_url = empty( $first_main_page_url ) ? '' : add_query_arg( $params + ( empty( $main_page_zone_name ) ? [] : array( 'preopen_zone' => $main_page_zone_name ) ), $first_main_page_url );

		$article_url = EPKB_KB_Handler::get_first_kb_article_url( $kb_config );
		$article_url = empty( $article_url ) ? '' : add_query_arg(  $params + ( empty( $article_page_zone_name ) ? [] : array( 'preopen_zone' => $article_page_zone_name ) ), $article_url );

		$archive_url = EPKB_KB_Handler::get_kb_category_with_most_articles_url( $kb_config );
		$archive_url = empty( $archive_url ) ? '' : add_query_arg(  $params + ( empty( $archive_page_zone_name ) ? [] : array( 'preopen_zone' => $archive_page_zone_name ) ), $archive_url );

		$search_url = '';
		if ( EPKB_Utilities::is_advanced_search_enabled() ) {

			// get search query: first title letter from first article
			$posts = get_posts( array(
				'numberposts' => 1,
				'post_type'   => EPKB_KB_Handler::get_post_type( $kb_config['id'] ),
			) );

			// provide Editor url for Search page only if we can find KB Main Page and articles
			if ( ! empty( $posts ) && ! empty( $first_main_page_url ) ) {
				$search_query = substr( $posts[0]->post_title, 0, 1 );

				$search_query_param = apply_filters( 'eckb_search_query_param', '', $kb_config['id'] );
				if ( empty( $search_query_param ) ) {
					$search_query_param = _x( 'kb-search', 'search query parameter in URL', 'echo-knowledge-base' );
				}
				/** END remove */

				$search_url = add_query_arg( array( 'preopen_zone' => 'search_zone', 'action' => 'epkb_load_editor', $search_query_param => $search_query ), $first_main_page_url );
			}
		}

		return [
			'main_page_url' => $main_url,
			'article_page_url' => $article_url,
			'archive_url' => $archive_url,
			'search_page_url' => $search_url ];
	}

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