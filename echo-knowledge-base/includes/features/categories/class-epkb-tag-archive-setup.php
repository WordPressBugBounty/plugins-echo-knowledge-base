<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template for KB Tag Archive Page front-end setup
 */
class EPKB_Tag_Archive_Setup {

    /**
     * Generate Tag Archive Page based on selected preset.
     */
	public static function get_tag_archive_page( $kb_config ) {

		// setup archive structure
		self::setup_archive_hooks( $kb_config );

		// define container classes
		$archive_page_container_classes = apply_filters( 'eckb-archive-page-container-classes', array(), $kb_config['id'], $kb_config );  // used for old Widgets KB Sidebar
		$archive_page_container_classes = isset( $archive_page_container_classes ) && is_array( $archive_page_container_classes ) ? $archive_page_container_classes : array();
		if ( ! empty( $kb_config['theme_name'] ) ) {
			$archive_page_container_classes[] = 'eckb-theme-' . $kb_config['theme_name'];
		}

		// add theme name to Div for specific targeting
		$activeWPTheme = EPKB_Utilities::get_active_theme_classes( 'cp' );

		$mobile_breakpoint = '768';
		if ( is_numeric( $mobile_breakpoint ) && ! empty( $_REQUEST['epkb-editor-page-loaded'] ) ) {
			$mobile_breakpoint -= 400;
		} ?>

		<div id="eckb-archive-page-container" class="<?php echo esc_attr( implode(" ", $archive_page_container_classes) . ' ' . $activeWPTheme ); ?> eckb-archive-page-design-1" data-mobile_breakpoint="<?php echo esc_attr( $mobile_breakpoint ); ?>">    <?php

		   self::archive_section( 'eckb-archive-header', array( 'id' => $kb_config['id'], 'config' => $kb_config ) ); ?>

			<div id="eckb-archive-body">  <?php

		        self::archive_section( 'eckb-archive-left-sidebar', array( 'id' => $kb_config['id'], 'config' => $kb_config ) ); ?>

		        <div id="eckb-archive-content">                        <?php

					self::archive_section( 'eckb-archive-content-header', array( 'id' => $kb_config['id'], 'config' => $kb_config ) );
					self::archive_section( 'eckb-archive-content-body', array( 'id' => $kb_config['id'], 'config' => $kb_config ) );
					self::archive_section( 'eckb-archive-content-footer', array( 'id' => $kb_config['id'], 'config' => $kb_config ) );                        ?>

		        </div><!-- /#eckb-archive-content -->     <?php

		        self::archive_section( 'eckb-archive-right-sidebar', array( 'id' => $kb_config['id'], 'config' => $kb_config ) ); ?>

			</div><!-- /#eckb-archive-body -->              <?php

			self::archive_section( 'eckb-archive-footer', array( 'id' => $kb_config['id'], 'config' => $kb_config ) ); ?>

		</div><!-- /#eckb-archive-page-container -->    <?php
	}

	/**
	 * REGISTER all archive hooks we need
	 *
	 * @param $kb_config
	 */
	private static function setup_archive_hooks( $kb_config ) {

		// A. ARCHIVE PAGE HEADER
		add_action( 'eckb-archive-header', array( 'EPKB_Tag_Archive_Setup', 'search_box' ) );

		// B. ARCHIVE CONTENT HEADER
		add_action( 'eckb-archive-content-header', array( 'EPKB_Tag_Archive_Setup', 'breadcrumb' ), 9 );
		add_action( 'eckb-archive-content-header', array( 'EPKB_Tag_Archive_Setup', 'tag_header' ), 9 );

		// C. SIDEBARS + ARCHIVE CONTENT BODY
		add_action( 'eckb-archive-content-body', array( 'EPKB_Tag_Archive_Setup', 'archive_content_body' ), 10 );

		// Sidebar
		add_action( 'eckb-archive-left-sidebar', array( 'EPKB_Tag_Archive_Setup', 'display_nav_sidebar_left' ), 10 );
		add_action( 'eckb-archive-right-sidebar', array( 'EPKB_Tag_Archive_Setup', 'display_nav_sidebar_right' ), 10 );

		// D. ARCHIVE CONTENT FOOTER
		//add_action( 'eckb-archive-content-footer', array('EPKB_Tag_Archive_Setup', 'prev_next_navigation'), 99 );
	}


	/***********************   A. ARCHIVE PAGE HEADER   *********************/

	/**
	 * Search Box
	 *
	 * @param $args
	 */
	public static function search_box( $args ) {

		// SEARCH BOX OFF: no search box if Archive Page search is off
		if ( $args['config']['archive_search_toggle'] == 'off' ) {
			return;
		}

		// Use Article Page search if the main page has KB blocks or if the search source is set to article page
		if ( EPKB_Block_Utilities::kb_main_page_has_kb_blocks( $args['config'] ) || $args['config']['archive_search_source'] == 'article_page' ) {
			// Use article page search settings
			EPKB_Articles_Setup::search_box( $args );
		} else {
			// Default behavior - use main page search settings
			EPKB_Modular_Main_Page::search_module( $args['config'] );
		}
	}


	/***********************   B. ARCHIVE CONTENT HEADER  *********************/

	public static function tag_header( $args ) {

		$term = EPKB_Utilities::get_current_term();
		if ( empty( $term ) ) {
			return;
		}

		$tag_title = single_cat_title( '', false );
		$tag_title = empty( $tag_title ) ? '' : $tag_title;  ?>

		<header class="eckb-tag-archive-header">

			<div class="eckb-tag-archive-title-container">
				<h1 class="eckb-tag-archive-title">
					<span class="eckb-tag-archive-title-icon">
						<span class="eckb-tag-archive-title-icon--font epkbfa epkbfa-tag"></span>
					</span>
					<span class="eckb-tag-archive-title-name"><?php echo esc_html__( 'Tag', 'echo-knowledge-base' ) . ' - ' . esc_html( $tag_title ); ?></span>
				</h1>
			</div>            <?php

			if ( $args['config']['archive_category_desc_toggle'] == 'on' ) {
				$term_description = get_term_field( 'description', $term );
				if ( ! is_wp_error( $term_description ) && ! empty( $term_description ) ) {
					echo '<div class="eckb-tag-archive-description">' . wp_kses_post( $term_description ) . '</div>';
				}
			}   ?>

		</header>   <?php
	}

	public static function breadcrumb( $args ) {

		if ( $args['config']['breadcrumb_enable']  != 'on' ) {
			return;
		}

		$term = EPKB_Utilities::get_current_term();
		if ( empty( $term ) ) {
			return;
		}

		echo '<div id="eckb-archive-content-breadcrumb-container">';
		EPKB_Templates::get_template_part( 'feature', 'breadcrumb', $args['config'], $term );
		echo '</div>';
	}

	/***********************   C. ARCHIVE CONTENT BODY   *********************/

	public static function archive_content_body( $args ) {

		$kb_config = $args['config'];

		$term = EPKB_Utilities::get_current_term();
		if ( empty( $term ) ) {
			return;
		}

        $articles_list          = self::get_tag_articles( $term );
        $total_articles         = count( $articles_list );
		$nof_articles_displayed = $kb_config['archive_content_articles_nof_articles_displayed'];        ?>

		<main class="eckb-tag-archive-main">   <?php
			// show list of articles if any
			if ( $total_articles > 0 ) {
				// Show title for the list of articles
				$archive_content_articles_list_title = $kb_config['archive_content_articles_list_title'];
				if ( ! empty( $archive_content_articles_list_title ) ) {
					echo '<h2 class="eckb-tag-archive-articles-list-title">' . esc_html( $archive_content_articles_list_title ) . '</h2>';
				}

				$nof_columns = $kb_config['archive_content_articles_nof_columns'];

				// Remove the borders of the last row of articles
				$article_list_separator_classes = '';
				if (  $kb_config['archive_content_articles_separator_toggle'] === 'on' ) {
					$article_list_separator_classes .= 'eckb-article-list--separator';
				}   ?>

				<div class="eckb-article-list-container eckb-article-list-container-columns-<?php echo esc_attr( $nof_columns . ' ' . $article_list_separator_classes ); ?>"> <?php

					self::display_tag_articles( $kb_config, $articles_list, $kb_config['archive_content_articles_nof_articles_displayed'] );

					if ( $total_articles > $nof_articles_displayed ) { ?>
						<div class="eckb-article-list-show-more-container">
							<div class="eckb-article-list-article-count">+ <?php echo esc_html($total_articles - $nof_articles_displayed) . ' ' . esc_html__( 'Articles', 'echo-knowledge-base' ); ?> </div>
							<div class="eckb-article-list-show-all-link"><?php echo esc_html( $kb_config['show_all_articles_msg'] ); ?></div>
						</div>  <?php
					} ?>

				</div> <?php
			} else {
				echo '<div class="epkb-articles-coming-soon">' . esc_html( $kb_config['category_empty_msg'] ) . '</div>';
			}

			wp_reset_postdata();			?>

		</main> <?php
	}

	/**
	 * Display tag articles
	 */
	private static function display_tag_articles( $kb_config, $articles_list, $nof_articles_displayed ) {

		$nof_articles = 0;
		foreach ( $articles_list as $article_id ) {

			if ( ! EPKB_Utilities::is_article_allowed_for_current_user( $article_id ) ) {
				continue;
			}

			$nof_articles++;
			$hide_class = $nof_articles > $nof_articles_displayed ? ' epkb-hide-elem' : '';

			self::display_article( $kb_config, $article_id, $hide_class );
		}
	}

	private static function display_article( $kb_config, $article_id, $article_class ) {

		$article = get_post( $article_id );
		$inline_style_escaped = EPKB_Utilities::get_inline_style( 'padding-bottom:: article_list_spacing,padding-top::article_list_spacing', $kb_config ); ?>

		<div class="eckb-article-container<?php echo esc_attr( $article_class ); ?>" id="post-<?php echo esc_attr( $article_id ); ?>">

			<div class="eckb-article-header" <?php echo $inline_style_escaped; ?> >   <?php
				//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				EPKB_Utilities::get_single_article_link( $kb_config, $article->post_title, $article_id, 'Category_Archive_Page' ) ?>
			</div> <?php

			$articles_display_mode = empty( $kb_config['archive_content_articles_display_mode'] ) ? 'title' : $kb_config['archive_content_articles_display_mode'];
			$articles_display_mode = $articles_display_mode == 'title_excerpt' && ! has_excerpt( $article_id ) ? 'title_content' : $articles_display_mode;

			if ( $articles_display_mode !== 'title' ) {     ?>
				<div class="eckb-article-body"> <?php
					if ( $articles_display_mode == 'title_excerpt' ) {
						echo esc_html( wp_strip_all_tags( get_the_excerpt( $article ) ) );
					} else if ( $articles_display_mode == 'title_content' ) {
						echo esc_html( wp_strip_all_tags( wp_trim_excerpt( '', $article ) ) );
					}   ?>
				</div>
				<div class="eckb-article-footer"></div>	<?php
			}   ?>

		</div>	<?php
	}

	/**
	 * Get tag Articles list
     * @param $tag_object
     * @return array
	 */
    private static function get_tag_articles( $tag_object ) {

        $articles = new WP_Query( array(
            'posts_per_page' => -1,
	        'tax_query' => array(
		        array(
			        'taxonomy' => $tag_object->taxonomy,
			        'field'    => 'slug',
			        'terms'    => $tag_object->slug,
		        )
	        ),
            'post_status' => 'publish',
        ));

        if ( ! $articles->have_posts() ) {
            return array();
        }

        return wp_list_pluck( $articles->posts, 'ID' );
    }

	/******************************************************************************
	 *
	 *  SIDEBARS
	 *
	 ******************************************************************************/

	private static function is_left_sidebar_on( $kb_config ) {
		return $kb_config['archive_left_sidebar_toggle'] != 'off';
	}

	private static function is_right_sidebar_on( $kb_config ) {
		return $kb_config['archive_right_sidebar_toggle'] != 'off';
	}

	/**
	 * Display LEFT navigation Sidebar
	 * @param $args
	 */
	public static function display_nav_sidebar_left( $args ) {

		$kb_config = $args['config'];

		// TODO: new feature - 3 positions where user can choose Navigation, Recent or Popular Articles

		// Position 1
		if ( $kb_config['archive-left-sidebar-position-1'] != 'none' ) {
			if ( $kb_config['archive-left-sidebar-position-1'] == 'navigation' ) {
				self::get_navigation( $args );
			}
		}

		// Position 2
		/* if ( $kb_config['archive-left-sidebar-position-2'] != 'none' ) {
		} */

		// Position 3
		/* if ( $kb_config['archive-left-sidebar-position-3'] != 'none' ) {
		} */
	}

	/**
	 * Display RIGHT navigation Sidebar
	 * @param $args
	 */
	public static function display_nav_sidebar_right( $args ) {
		$kb_config = $args['config'];

		// TODO: new feature - 3 positions where user can choose Navigation, Recent or Popular Articles

		// Position 1
		if ( $kb_config['archive-right-sidebar-position-1'] != 'none' ) {
			if ( $kb_config['archive-right-sidebar-position-1'] == 'navigation' ) {
				self::get_navigation( $args );
			}
		}

		// Position 2
		/* if ( $kb_config['archive-right-sidebar-position-2'] != 'none' ) {
		} */

		// Position 3
		/* if ( $kb_config['archive-right-sidebar-position-3'] != 'none' ) {
		} */
	}

	private static function get_navigation( $args ) {
        do_action( 'eckb-article-v2-elay_sidebar', $args );
	}


	/******************************************************************************
	 *
	 *  OTHER UTILITIES
	 *
	 ******************************************************************************/

	/**
	 * Output section container + trigger hook to output the section content.
	 *
	 * @param $hook - both hook name and div id
	 * @param $args
	 */
	public static function archive_section( $hook, $args ) {

	   echo '<div id="' . esc_attr( $hook ) . '">';

		if ( self::is_hook_enabled( $args['config'], $hook ) ) {
			do_action( $hook, $args );
		}

		echo '</div>';
	}

	/**
	 * Hooks in Sidebar belong to either left or right sidebar. If sidebar is disabled then it is not invoked.
	 *
	 * @param $kb_config
	 * @param $hook
	 * @return bool
	 */
	private static function is_hook_enabled( $kb_config, $hook ) {

		// do not output left and/or right sidebar if not configured
		if ( $hook == 'eckb-archive-left-sidebar' && ! self::is_left_sidebar_on( $kb_config ) ) {
			return false;
		}
		if ( $hook == 'eckb-archive-right-sidebar' && ! self::is_right_sidebar_on( $kb_config ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate archive styles from configuration
	 *
	 * @param $kb_config
	 * @return string
	 */
	private static function generate_archive_structure_css( $kb_config ) {

		// Left Sidebar Settings
		$archive_sidebar_bg_color                       = $kb_config['archive_sidebar_background_color'];
		$archive_left_sidebar_padding_top               = $kb_config['article-left-sidebar-padding-v2_top'];
		$archive_left_sidebar_padding_right             = $kb_config['article-left-sidebar-padding-v2_right'];
		$archive_left_sidebar_padding_bottom            = $kb_config['article-left-sidebar-padding-v2_bottom'];
		$archive_left_sidebar_padding_left              = $kb_config['article-left-sidebar-padding-v2_left'];

		// Content Settings
		$archive_content_bg_color                       = $kb_config['archive_content_background_color'];

		// Right Sidebar Settings
		$archive_right_sidebar_padding_top              = $kb_config['article-right-sidebar-padding-v2_top'];
		$archive_right_sidebar_padding_right            = $kb_config['article-right-sidebar-padding-v2_right'];
		$archive_right_sidebar_padding_bottom           = $kb_config['article-right-sidebar-padding-v2_bottom'];
		$archive_right_sidebar_padding_left             = $kb_config['article-right-sidebar-padding-v2_left'];

		// WIDTH
		$archive_header_desktop_width           = $kb_config['archive_header_desktop_width'];
		$archive_header_desktop_width_units     = $kb_config['archive_header_desktop_width_units'];
		$archive_content_desktop_width          = $kb_config['archive_content_desktop_width'];
		$archive_content_desktop_width_units    = $kb_config['archive_content_desktop_width_units'];
		$archive_left_sidebar_desktop_width     = $kb_config['archive_left_sidebar_desktop_width'];
		$archive_right_sidebar_desktop_width    = $kb_config['archive_right_sidebar_desktop_width'];

		// auto-determine whether we need sidebar or let user override it to be displayed
		$is_left_sidebar_on = self::is_left_sidebar_on( $kb_config );
		$is_right_sidebar_on = self::is_right_sidebar_on( $kb_config );

		/**
		 *  Grid Columns start at lines.
		 *
		 *  Left Sidebar Grid Start:    1 - 2;
		 *  Content Grid Start:         2 - 3;
		 *  Right Sidebar Grid Start:    3 - 4;
		 *
		 *  LEFT   Content  Right
		 *  1 - 2   2 - 3   3 - 4
		 */

		$output = self::archive_media_structure( array(
				'is_left_sidebar_on'                    => $is_left_sidebar_on,
				'is_right_sidebar_on'                   => $is_right_sidebar_on,
				'archive_header_desktop_width'          => $archive_header_desktop_width,
				'archive_header_desktop_width_units'    => $archive_header_desktop_width_units,
				'archive_content_desktop_width'         => $archive_content_desktop_width,
				'archive_content_desktop_width_units'   => $archive_content_desktop_width_units,
				'archive_left_sidebar_desktop_width'    => $archive_left_sidebar_desktop_width,
				'archive_right_sidebar_desktop_width'   => $archive_right_sidebar_desktop_width,
		) );

		/* SHARED */
		$output .= '
			#eckb-archive-page-container #eckb-archive-content {
				background-color: ' . $archive_content_bg_color . ';
			}
			#eckb-archive-page-container #eckb-archive-left-sidebar {
				background-color: ' . $archive_sidebar_bg_color .';
				padding: ' . $archive_left_sidebar_padding_top . 'px ' . $archive_left_sidebar_padding_right . 'px ' . $archive_left_sidebar_padding_bottom . 'px ' . $archive_left_sidebar_padding_left . 'px;
			}
			#eckb-archive-page-container #eckb-archive-right-sidebar {
				padding: ' . $archive_right_sidebar_padding_top . 'px ' . $archive_right_sidebar_padding_right . 'px ' . $archive_right_sidebar_padding_bottom . 'px ' . $archive_right_sidebar_padding_left . 'px;
				background-color: ' . $archive_sidebar_bg_color . ';
			}';

		return $output;
	}

	/**
	 * Output style for either desktop or tablet
	 * @param array $settings
	 */
	public static function archive_media_structure( $settings = array() ) {

		$defaults = array(
			'is_left_sidebar_on'                    => '',
			'is_right_sidebar_on'                   => '',
			'archive_header_desktop_width'          => '',
			'archive_header_desktop_width_units'    => '',
			'archive_content_desktop_width'         => '',
			'archive_content_desktop_width_units'   => '',
			'archive_left_sidebar_desktop_width'    => '',
			'archive_right_sidebar_desktop_width'   => '',
		);
		$args = array_merge( $defaults, $settings );

		// Header ( Currently contains search )
		$output =
			'#eckb-archive-page-container #eckb-archive-header  {
				width: ' . $args[ 'archive_header_desktop_width' ] . $args[ 'archive_header_desktop_width_units'] . ';
			}';

		// Content ( Sidebars , Article Content )
		$output .=
			'#eckb-archive-page-container #eckb-archive-body,
			#eckb-archive-page-container #eckb-archive-footer {
				width: ' . $args[ 'archive_content_desktop_width' ] . $args[ 'archive_content_desktop_width_units'] . ';
			}';

		/**
		 * If No Left Sidebar AND Right Sidebar active
		 *  - Expend the Archive Content 1 - 3
		 *  - Make Layout 2 Columns only and use the Two remaining values
		 */
		if ( ! $args[ 'is_left_sidebar_on' ] && $args[ 'is_right_sidebar_on' ]  ) {

			$archive_content_width = 100 - $args[ 'archive_right_sidebar_desktop_width' ];

			$output .= '
		        /* NO LEFT SIDEBAR */
				#eckb-archive-page-container #eckb-archive-body {
					grid-template-columns:  0 ' . $archive_content_width . '% ' . $args[ 'archive_right_sidebar_desktop_width' ] . '%;
				}
				#eckb-archive-page-container #eckb-archive-left-sidebar {
					display:none;
				}
				#eckb-archive-page-container #eckb-archive-content {
					grid-column-start: 1;
					grid-column-end: 3;
				}';
		}

		/**
		 * If No Right Sidebar AND Left Sidebar active
		 *  - Expend the Archive Content 2 - 4
		 *  - Make Layout 2 Columns only and use the Two remaining values
		 */
		if ( ! $args[ 'is_right_sidebar_on' ] && $args[ 'is_left_sidebar_on' ] ) {

			$archive_content_width = 100 - $args[ 'archive_left_sidebar_desktop_width' ];

			$output .= '
				/* NO RIGHT SIDEBAR */
				#eckb-archive-page-container #eckb-archive-body {
					grid-template-columns: ' . $args[ 'archive_left_sidebar_desktop_width' ] . '% ' . $archive_content_width . '% 0;
				}
				#eckb-archive-page-container #eckb-archive-right-sidebar {
					display:none;
				}
				#eckb-archive-page-container #eckb-archive-content {
					grid-column-start: 2;
					grid-column-end: 4;
				}';
		}

		// If No Sidebars Expand the Archive Content 1 - 4
		if ( ! $args[ 'is_left_sidebar_on']  && ! $args[ 'is_right_sidebar_on' ] ) {
			$output .= '
				#eckb-archive-page-container #eckb-archive-body {
					grid-template-columns: 0 100% 0;
				}
				#eckb-archive-page-container #eckb-archive-left-sidebar,
				#eckb-archive-page-container #eckb-archive-right-sidebar {
					display:none;
				}
				#eckb-archive-page-container #eckb-archive-content {
					grid-column-start: 1;
					grid-column-end: 4;
				}';
		}

		/**
		 * If Both Sidebars are active
		 *  - Make Layout 3 Columns and divide their sizes according to the user settings
		 */
		if ( $args[ 'is_left_sidebar_on' ]  && $args[ 'is_right_sidebar_on' ] ) {
			$archive_content_width = 100 - $args[ 'archive_left_sidebar_desktop_width' ] - $args[ 'archive_right_sidebar_desktop_width' ];
			$output .= '
				#eckb-archive-page-container #eckb-archive-body {
					grid-template-columns: ' . $args[ 'archive_left_sidebar_desktop_width' ] . '% ' . $archive_content_width . '% ' . $args[ 'archive_right_sidebar_desktop_width' ] . '%;
				}';
		}

		return $output;
	}

	/**
	 * Returns inline styles for Archive Page
	 *
	 * @param $kb_config
	 *
	 * @return string
	 */
	public static function get_all_inline_styles( $kb_config ) {

		$output = '
		/* CSS for Archive Page V3
		-----------------------------------------------------------------------*/';

		// General Typography ----------------------------------------------/
		if ( ! empty( $kb_config['general_typography']['font-family'] ) ) {
			$output .= '
			#eckb-archive-page-container,
			#eckb-archive-page-container .eckb-tag-archive-title-name,
			#eckb-archive-page-container .eckb-tag-archive-articles-list-title,
			#eckb-archive-page-container #epkb-sidebar-container-v2 .epkb-sidebar__heading__inner__cat-name,
			#eckb-archive-page-container #epkb-sidebar-container-v2 .eckb-article-title__text,
			#eckb-archive-page-container #elay-sidebar-container-v2 .elay-sidebar__heading__inner__cat-name,
			#eckb-archive-page-container #elay-sidebar-container-v2 .elay-article-title__text,
			#eckb-archive-page-container .eckb-acll__title,
			#eckb-archive-page-container .eckb-acll__cat-item__name,
			#eckb-archive-page-container .eckb-breadcrumb-nav
			{
			    ' . EPKB_Utilities::get_font_css( $kb_config, 'general_typography', 'font-family' ) . '
			}';
		}
		// Tag Name
		$output .= '
		    #eckb-archive-page-container .eckb-tag-archive-title-container {
		        ' . ( empty( $kb_config['article_title_typography']['font-size'] ) ? 'font-size: 30px' : EPKB_Utilities::get_font_css( $kb_config, 'article_title_typography', 'font-size' ) ) . ';
		        color: ' .  ( empty( $kb_config['section_head_font_color'] ) ? '#000000;' : $kb_config['section_head_font_color'] ). ';
		    }
		    .eckb-tag-archive-title-icon--image {
		        width: ' . ( empty( $kb_config['article_title_typography']['font-size'] ) ? '30px' : ( intval( $kb_config['article_title_typography']['font-size']) + 10 ) . 'px' ) . ' !important;
		    }';
		// Tag Desc
		$output .= '
		    #eckb-archive-page-container .eckb-tag-archive-description {
		        color: ' . $kb_config['section_head_description_font_color'] . ';
		    }';

		// Tag Icon
		$output .= '
		    #eckb-archive-page-container .eckb-tag-archive-title-icon {
		        color: ' . $kb_config['section_head_category_icon_color'] . ';
		    }';

		// Sub Titles
		$output .= '
		    #eckb-archive-page-container .eckb-tag-archive-articles-list-title {
		        color: ' . $kb_config['section_category_font_color'] . ';
		        font-size: ' . ( intval( $kb_config['article_typography']['font-size'] ) + 6 ) . 'px;
		    }';

		// Main Tag Articles
		$output .= '
		    #eckb-archive-page-container .eckb-article-list-container .eckb-article-container .epkb-article-inner { 
				font-size: ' . ( intval( $kb_config['article_typography']['font-size'] ) + 2 ) . 'px;
		    }';
		$output .= '
		    #eckb-archive-page-container .eckb-article-list-container .eckb-article-container .epkb-article__icon { 
		        font-size: ' . ( intval( $kb_config['article_typography']['font-size'] ) + 6 ) . 'px;
		    }';

		$output .= '
	    #eckb-archive-page-container .eckb-sub-category-description {
	        color: ' . $kb_config['section_head_description_font_color'] . ';
	    }';
		// Sub Category Articles
		$output .= '
		    #eckb-archive-page-container .eckb-sub-category-list-container .eckb-article-container .epkb-article-inner { 
				font-size: ' .  $kb_config['article_typography']['font-size'] . 'px;
		    }';
		$output .= '
		    #eckb-archive-page-container .eckb-sub-category-list-container .eckb-article-container .epkb-article__icon { 
		        font-size: ' . ( intval( $kb_config['article_typography']['font-size'] ) + 4 ) . 'px;
		    }';

		// Arrows
		$output .= '
		    #eckb-archive-page-container .eckb-category-archive-arrow {
		        color: ' . $kb_config['article_icon_color'] . ';
		    }';

		$output .= self::generate_archive_structure_css( $kb_config );

		// Search ----------------------------------------------------------/
		$output .= EPKB_ML_Search::get_inline_styles( $kb_config );

		return $output;
	}
}
