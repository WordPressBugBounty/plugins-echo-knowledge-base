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

			if ( $editor_type == 'main-page' && EPKB_Block_Utilities::current_post_has_kb_blocks() ) {
				$editor_type = 'block-main-page';
			}

		} else if ( ! empty( $post ) && EPKB_KB_Handler::is_kb_post_type( $post->post_type ) ) {
			
			$editor_type = 'article-page';
		}

		return $editor_type;
	}

	/**
	 * Check if the current page is actively rendering the current page on the frontend.
	 *
	 * Supported builders:
	 *  - Elementor
	 *  - Divi Builder
	 *  - WPBakery Page Builder / Visual Composer
	 *  - Visual Composer Website Builder (vcv)
	 *  - Beaver Builder
	 *  - SiteOrigin Page Builder
	 *
	 * @return bool True when a supported builder is active for current page.
	 */
	public static function is_page_builder_enabled() {

		global $post;
		if ( empty( $post ) ) {
			return false;
		}

		$post_id = $post->ID;

		/* ---------- Elementor ---------- */
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			/*
			 * Elementor loads the current page inside an iframe when the visual builder
			 * is opened.  The iframe URL contains the "elementor-preview" query
			 * parameter.  We only want to hide our own Front-end Editor while the
			 * user is working with Elementor – not on normal page views.  Therefore
			 * we simply check for the presence of that parameter.
			 *
			 * Example editor URL:
			 *   https://example.com/my-page/?p=123&elementor-preview=1&ver=3.20.0
			 */
			if ( ! empty( $_GET['elementor-preview'] ) ) {
				return true;
			}

		}

		/* ---------- Divi ---------- */
		if ( class_exists( 'ET_Theme_Builder_Request' ) ) {
			if ( isset($_GET['et_fb']) && $_GET['et_fb'] !== '' ) {
				return true;
			}
		}

		/* ---------- WPBakery Page Builder ---------- */
		if ( defined( 'WPB_VC_VERSION' ) ) {
			/*
			 * WPBakery (formerly Visual Composer) adds a few query parameters to the
			 * front-end page when the live editor is active.  The most reliable one
			 * across versions is "vc_editable" which is set to "true".  We also look
			 * for the legacy "vc_edit" and "vc_action" parameters just in case the
			 * site is running an old version.
			 */
			if ( ! empty( $_GET['vc_editable'] ) || ! empty( $_GET['vc_edit'] ) || ! empty( $_GET['vc_action'] ) ) {
				return true;
			}

		}

		/* ---------- Visual Composer Website Builder ---------- */
		if ( defined( 'VCV_VERSION' ) ) {
			/*
			 * Visual Composer Website Builder (vcv) uses the query parameter
			 * "vcv-action" when the live editor loads the front-end preview.
			 * It can have values like "vcvFrontend" or "vcvPreview".
			 */
			if ( ! empty( $_GET['vcv-action'] ) ) {
				return true;
			}

		}

		/* ---------- Beaver Builder ---------- */
		if ( defined( 'FL_BUILDER_LITE' ) || class_exists( 'FLBuilder' ) ) {
			if ( ! empty($_GET['fl_builder']) ) {
				return true;
			}
		}

		/* ---------- SiteOrigin Page Builder ---------- */
		if ( defined( 'SITEORIGIN_PANELS_VERSION' ) ) {
			/*
			 * SiteOrigin Live Editor appends the "so_live_editor" query parameter
			 * (true/1) to the preview URL.  Another parameter sometimes used is
			 * "siteorigin_panels_live_editor".  Detect either.
			 */
			if ( ! empty( $_GET['so_live_editor'] ) || ! empty( $_GET['siteorigin_panels_live_editor'] ) ) {
				return true;
			}

		}

		return false;
	}

	public static function initialize_advanced_search_box( $use_main_page_settings = true ) {
		if ( EPKB_Utilities::is_advanced_search_enabled() && class_exists( 'ASEA_Search_Box_View' ) ) {
			global $asea_use_main_page_settings;
			$asea_use_main_page_settings = $use_main_page_settings;	// for AJAX request we need to hard-code the value here
			/**@disregard P1009 */
			new ASEA_Search_Box_View();		// TODO: move to KB Utilities Constants
		}
	}
}