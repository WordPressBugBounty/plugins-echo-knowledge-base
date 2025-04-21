<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Methods used for block related features
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_Block_Utilities {

	/**
	 * Determine if the current page has KB layout blocks
	 * @param bool $clear_cache - TODO - implement cache clearing if needed
	 * @return bool
	 */
	public static function current_post_has_kb_layout_blocks( $clear_cache = true ) {
		static $cache = [];

		// NOTE: any updates/fixes to be applied to Elegant Layouts and Advanced Search

		if ( $clear_cache ) {
			$cache = [];
		}

		// Get the current post
		$found_post = EPKB_Core_Utilities::get_current_post();
		if ( ! $found_post ) {
			return false;
		}
		$post_id = $found_post->ID;

		// Check if the result is already cached
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		// Perform the computation to determine if the post has KB layout blocks
		//$has_layout_blocks = self::parse_block_attributes_from_post( $found_post, '-layout' ) !== false;
		$has_kb_blocks = self::content_has_kb_block( $found_post->post_content );

		// Store the result in the cache for future use
		$cache[ $post_id ] = $has_kb_blocks;

		return $has_kb_blocks;
	}

	/**
	 * Detect whether the given content contains any KB block
	 * @param $content
	 * @return false|int
	 */
	public static function content_has_kb_block( $content ) {
		return preg_match( '/wp:echo-knowledge-base\//i', $content );
	}

	/**
	 * Detect whether the given content contains KB block with the given name
	 * @param $content
	 * @param $block_name
	 * @return false|int
	 */
	public static function content_has_the_kb_block( $content, $block_name ) {
		return preg_match( '/wp:echo-knowledge-base\/' . preg_quote( $block_name, '/' ) . '/i', $content );
	}

	public static function get_kb_block_layout( $post, $default_layout = false ) {
		$blocks = self::parse_blocks( $post );
		if ( is_array( $blocks ) && count( $blocks ) ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && strpos( $block['blockName'], '-layout' ) !== false ) {
					return ucfirst( str_replace( '-layout', '', preg_replace( '/echo-knowledge-base\//', '', $block['blockName'] ) ) );
				}
			}
		}
		return $default_layout;
	}

	/**
	 * Retrieve block attributes from post content; return false if the block was not found in the post content
	 * @param $post
	 * @param $block_name
	 * @return false|array
	 */
	public static function parse_block_attributes_from_post( $post, $block_name ) {

		if ( empty( $post ) ) {
			return false;
		}

		$blocks = self::parse_blocks( $post );
		if ( is_array( $blocks ) && count( $blocks ) ) {
			return self::parse_block_attributes_recursive( $blocks, $block_name );
		}

		return false;
	}

	/**
	 * Return attributes of the first found layout block in the given post or false
	 * @param $post
	 * @return array|false
	 */
	public static function parse_first_layout_block_attributes_from_post( $post ) {

		if ( empty( $post ) ) {
			return false;
		}

		$quoted_layout_block_names = array(
			preg_quote( EPKB_Basic_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Tabs_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Categories_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Classic_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Drill_Down_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Grid_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Sidebar_Layout_Block::EPKB_BLOCK_NAME, '/' ),
			preg_quote( EPKB_Advanced_Search_Block::EPKB_BLOCK_NAME, '/' ),
		);

		if ( ! preg_match( '/wp:echo-knowledge-base\/(' . implode( '|', $quoted_layout_block_names ) . ')/i', $post->post_content, $matches ) ) {
			return false;
		}

		$blocks = self::parse_blocks( $post );
		if ( is_array( $blocks ) && count( $blocks ) ) {
			return self::parse_block_attributes_recursive( $blocks, $matches[1] );
		}

		return false;
	}

	/**
	 * Find blocks in given post
	 * @param $post
	 * @return array|array[]
	 */
	private static function parse_blocks( $post ) {
		$blocks = [];

		if ( empty( $post->post_content ) ) {
			return $blocks;
		}

		if ( function_exists( 'parse_blocks' ) ) { // added  in wp 5.0
			$blocks = parse_blocks( $post->post_content );
		}

		return $blocks;
	}

	/**
	 * Retrieve block attributes from blocks; return false if the block was not found in the blocks
	 * @param $blocks
	 * @param $block_name
	 * @return false|array
	 */
	private static function parse_block_attributes_recursive( $blocks, $block_name ) {
		foreach ( $blocks as $block ) {

			// parse top level blocks
			if ( isset( $block['blockName'] ) ) {

				// match KB blocks by name
				if ( $block['blockName'] == EPKB_Abstract_Block::EPKB_BLOCK_NAMESPACE . '/' . $block_name ) {
					return isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
				}

				// optionally detect any layout blocks - '-layout' indicates that we try to find any layout block
				if ( $block_name == '-layout' && strpos( $block['blockName'], EPKB_Abstract_Block::EPKB_BLOCK_NAMESPACE . '/' ) !== false && substr( $block['blockName'], -7 ) == $block_name ) {
					return isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
				}
			}

			// parse inner blocks
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$result = self::parse_block_attributes_recursive( $block['innerBlocks'], $block_name );
				if ( $result !== false ) {
					return $result;
				}
			}
		}
		return false;
	}

	/**
	 * Return class handler of corresponding KB block depending on KB module name and KB Main Page layout; null if no corresponding block class found
	 * @param $module_name
	 * @param $layout
	 * @return EPKB_FAQs_Block|EPKB_Featured_Articles_Block|EPKB_Basic_Layout_Block|EPKB_Categories_Layout_Block|EPKB_Classic_Layout_Block|EPKB_Drill_Down_Layout_Block|EPKB_Search_Block|EPKB_Tabs_Layout_Block|EPKB_Grid_Layout_Block|EPKB_Sidebar_Layout_Block|EPKB_Advanced_Search_Block|null
	 *
	 */
	private static function get_block_class_by_module_name( $module_name, $layout ) {

		switch ( $module_name ) {

			case 'search':
				return new EPKB_Search_Block( false );

			case 'categories_articles':
				switch ( $layout ) {

					case EPKB_Layout::BASIC_LAYOUT:
						return new EPKB_Basic_Layout_Block( false );

					case EPKB_Layout::TABS_LAYOUT:
						return new EPKB_Tabs_Layout_Block( false );

					case EPKB_Layout::CLASSIC_LAYOUT:
						return new EPKB_Classic_Layout_Block( false );

					case EPKB_Layout::DRILL_DOWN_LAYOUT:
						return new EPKB_Drill_Down_Layout_Block( false );

					case EPKB_Layout::CATEGORIES_LAYOUT:
						return new EPKB_Categories_Layout_Block( false );

					case EPKB_Layout::GRID_LAYOUT:
						return new EPKB_Grid_Layout_Block( false );

					case EPKB_Layout::SIDEBAR_LAYOUT:
						return new EPKB_Sidebar_Layout_Block( false );

					default:
						return null;
				}

			case 'faqs':
				return new EPKB_FAQs_Block();

			case 'articles_list':
				return new EPKB_Featured_Articles_Block();

			case 'advanced-search':
				return new EPKB_Advanced_Search_Block( false );

			default:
				return null;
		}
	}

	/**
	 * Check whether given template is the KB block page template
	 * @param $template
	 * @return bool
	 */
	public static function is_kb_block_page_template( $template ) {
		return ! empty( $template->slug ) && $template->slug == EPKB_Abstract_Block::EPKB_KB_BLOCK_PAGE_TEMPLATE;
	}

	/**
	 * We allow KB block template only if WP version is 6.7 or higher and the current theme is a block theme;
	 * classic theme with block template can have issues.
	 * @return bool
	 */
	public static function is_kb_block_page_template_available() {
		static $epkb_is_kb_block_page_template_available = null;
		global $wp_version;

		if ( $epkb_is_kb_block_page_template_available === null ) {
			$epkb_is_kb_block_page_template_available = version_compare( $wp_version, '6.7', '>=' ) && EPKB_Utilities::is_block_theme();
		}

		return $epkb_is_kb_block_page_template_available;
	}

	/**
	 * Return array of block configurations depending on the given KB configuration (e.g. enabled modules and their settings)
	 * @param $kb_id
	 * @param $kb_config
	 * @return array
	 */
	public static function get_blocks_config_from_kb_config( $kb_id, $kb_config ) {

		$kb_blocks = array();
		for ( $i = 1; $i <= 5; $i++ ) {

			$block_class_handler = self::get_block_class_by_module_name( $kb_config['ml_row_' . $i . '_module'], $kb_config['kb_main_page_layout'] );

			// do not continue if corresponding block class was not found for the current module
			if ( empty( $block_class_handler ) ) {
				continue;
			}

			$default_attributes = $block_class_handler->get_block_attributes_defaults();

			// block needs to store only attributes which have non-default value
			$non_default_attributes = array();
			foreach ( $default_attributes as $attribute_name => $attribute_value ) {

				// skip missing attribute names in KB configuration
				if ( ! isset( $kb_config[ $attribute_name ] ) ) {
					continue;
				}

				// skip the same values
				if ( $attribute_value == $kb_config[ $attribute_name ] ) {
					continue;
				}

				$non_default_attributes[ $attribute_name ] = $kb_config[ $attribute_name ];
			}

			// set required block-only attributes
			if ( $default_attributes['kb_id'] != $kb_id ) {
				$non_default_attributes['kb_id'] = $kb_id;
			}

			// if there are no non-default attributes, then the encoded string must be empty, otherwise it will lead to not rendered block
			$attributes_encoded = empty( $non_default_attributes ) ? '' : json_encode( $non_default_attributes );

			$kb_blocks[] = array(
				'name' => $block_class_handler::EPKB_BLOCK_NAME,
				'attributes' => $attributes_encoded,
			);
		}
		
		return $kb_blocks;
	}

	/**
	 * Check if the current theme supports blocks whether it is classic theme or block theme
	 * @return bool
	 */
	public static function current_theme_has_block_support() {
		return use_block_editor_for_post_type( 'page' );
	}

	public static function is_frontend() {
		global $pagenow;
		
		return EPKB_Utilities::post( 'action' ) != 'edit' && $pagenow != 'post-new.php';
	}
}
