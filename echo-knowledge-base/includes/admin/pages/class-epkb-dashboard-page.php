<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Display Dashboard admin page
 *
 */
class EPKB_Dashboard_Page {

	private $kb_config;

	public function __construct() {
		$this->kb_config = epkb_get_instance()->kb_config_obj->get_current_kb_configuration();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_scripts' ) );
	}

	/**
	 * Display Dashboard page
	 */
	public function display_dashboard_page() {

		$kb_id = EPKB_KB_Handler::get_current_kb_id();
		$kb_id = empty( $kb_id ) ? EPKB_KB_Config_DB::DEFAULT_KB_ID : $kb_id;
				
		$post_type = EPKB_KB_Handler::get_post_type( $kb_id );
		
		// Get statistics
		$article_count_obj = wp_count_posts( $post_type );
		$published_articles = isset( $article_count_obj->publish ) ? $article_count_obj->publish : 0;
		$draft_articles = isset( $article_count_obj->draft ) ? $article_count_obj->draft : 0;
		
		$faq_count_obj = wp_count_posts( EPKB_FAQs_CPT_Setup::FAQS_POST_TYPE );
		$published_faqs = isset( $faq_count_obj->publish ) ? $faq_count_obj->publish : 0;
		
		// Get category count
		$categories = get_terms( array(
			'taxonomy' => EPKB_KB_Handler::get_category_taxonomy_name( $kb_id ),
			'hide_empty' => false,
		) );
		if ( is_wp_error( $categories ) ) {
			$category_count = 0;
		} else {
			$category_count = is_array( $categories ) ? count( $categories ) : 0;
		}
		
		// Get views this month
		$views_this_month = 0;
		if ( $this->kb_config['article_views_counter_enable'] == 'on' ) {
			$year = date( 'Y' );
			$month_weeks = $this->get_month_weeks();
			
			$args = array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$articles = get_posts( $args );
			
			foreach ( $articles as $article_id ) {
				$year_meta = EPKB_Utilities::get_postmeta( $article_id, 'epkb-article-views-' . $year, [] );
				if ( is_wp_error( $year_meta ) ) {
					EPKB_Logging::add_log( 'Failed to get article views meta', $year_meta );
					continue;
				}
				if ( is_array( $year_meta ) ) {
					foreach ( $month_weeks as $week ) {
						if ( isset( $year_meta[$week] ) && is_numeric( $year_meta[$week] ) ) {
							$views_this_month += (int) $year_meta[$week];
						}
					}
				}
			}
		}
		
		// Get searches this month
		$searches_this_month = 0;
		$searches_found = EPKB_Utilities::get_kb_option( $kb_id, 'epkb_hit_search_counter', 0 );
		if ( is_wp_error( $searches_found ) ) {
			EPKB_Logging::add_log( 'Failed to get hit search counter', $searches_found );
			$searches_found = 0;
		}
		$searches_not_found = EPKB_Utilities::get_kb_option( $kb_id, 'epkb_miss_search_counter', 0 );
		if ( is_wp_error( $searches_not_found ) ) {
			EPKB_Logging::add_log( 'Failed to get miss search counter', $searches_not_found );
			$searches_not_found = 0;
		}
		$searches_this_month = $searches_found + $searches_not_found;

		// Ensure WordPress admin environment is properly loaded
		if ( ! function_exists( 'wp_admin_bar_render' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/admin.php' );
		}
		
		EPKB_HTML_Admin::admin_page_header();
		EPKB_HTML_Admin::admin_header( $this->kb_config, ['admin_eckb_access_need_help_read'] );   ?>

		<div id="ekb-admin-page-wrap">
			<div id="epkb-dashboard-page-container">

				<!-- ================= KPI Actions ================= -->
				<div class="epkb-kpi-actions-container">
					<div class="epkb-kpi-actions-buttons">

						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $post_type ) ); ?>" class="epkb-btn epkb-btn-add-article">
							<?php esc_html_e( '+ Add New Article', 'echo-knowledge-base' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-faqs#faqs-overview' ) ); ?>" class="epkb-btn epkb-btn-add-faq">
							<?php esc_html_e( '+ Add New FAQs', 'echo-knowledge-base' ); ?>
						</a>
						<a href="<?php echo esc_url( EPKB_KB_Handler::get_first_kb_main_page_url( $this->kb_config ) ) . '?action=epkb_load_editor'; ?>" class="epkb-btn epkb-btn-frontend-editor" target="_blank">
							<?php esc_html_e( 'Frontend Editor', 'echo-knowledge-base' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-configuration&ekb-main-page-loc=tools&ekb-secondary-page-loc=import#tools__import' ) ); ?>" class="epkb-btn epkb-btn-import-data">
							<?php esc_html_e( 'Import Data', 'echo-knowledge-base' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-configuration&ekb-main-page-loc=tools&ekb-secondary-page-loc=convert#tools__convert' ) ); ?>" class="epkb-btn epkb-btn-convert-posts">
							<?php esc_html_e( 'Convert Posts', 'echo-knowledge-base' ); ?>
						</a>
						
					</div>
				</div>
				
				<!-- ================= Top KPI tiles ================ -->
				<section class="epkb-kpi-grid">
					

					<a href="#" class="epkb-kpi-card">
						<div class="epkb-kpi-icon-container epkb-kpi-articles">
							<span class="epkb-kpi-icon epkbfa epkbfa-file-text"></span>
						</div>
						<div>
							<h3 class="epkb-kpi-value"><?php echo esc_html( $published_articles ); ?></h3>
							<p class="epkb-kpi-label"><?php esc_html_e( 'Total Articles', 'echo-knowledge-base' ); ?></p>
						</div>
					</a>

					<a href="#" class="epkb-kpi-card">
						<div class="epkb-kpi-icon-container epkb-kpi-categories">
							<span class="epkb-kpi-icon epkbfa epkbfa-folder-open"></span>
						</div>
						<div>
							<h3 class="epkb-kpi-value"><?php echo esc_html( $category_count ); ?></h3>
							<p class="epkb-kpi-label"><?php esc_html_e( 'Total Categories', 'echo-knowledge-base' ); ?></p>
						</div>
					</a>

					<a href="#" class="epkb-kpi-card">
						<div class="epkb-kpi-icon-container epkb-kpi-faqs">
							<span class="epkb-kpi-icon epkbfa epkbfa-question-circle"></span>
						</div>
						<div>
							<h3 class="epkb-kpi-value"><?php echo esc_html( $published_faqs ); ?></h3>
							<p class="epkb-kpi-label"><?php esc_html_e( 'Total FAQs', 'echo-knowledge-base' ); ?></p>
						</div>
					</a>

					<a href="#" class="epkb-kpi-card">
						<div class="epkb-kpi-icon-container epkb-kpi-views">
							<span class="epkb-kpi-icon epkbfa epkbfa-eye"></span>
						</div>
						<div>
							<h3 class="epkb-kpi-value"><?php echo esc_html( $views_this_month ); ?></h3>
							<p class="epkb-kpi-label"><?php esc_html_e( 'Views this Month', 'echo-knowledge-base' ); ?></p>
						</div>
					</a>

					<a href="#" class="epkb-kpi-card">
						<div class="epkb-kpi-icon-container epkb-kpi-search">
							<span class="epkb-kpi-icon epkbfa epkbfa-search"></span>
						</div>
						<div>
							<h3 class="epkb-kpi-value"><?php echo esc_html( $searches_this_month ); ?></h3>
							<p class="epkb-kpi-label"><?php esc_html_e( 'Searches this Month', 'echo-knowledge-base' ); ?></p>
						</div>
					</a>
				</section>

				<!-- ================= Marketing row ================= -->
				<section class="epkb-marketing-row">
					
					<!-- Main Content (70%) -->
					<div class="epkb-main-content">

						<!-- Welcome -->
						<div class="epkb-card epkb-card--welcome">
							<div class="epkb-welcome-content">
								<div class="epkb-welcome-text">
									<header>
										<h2><?php esc_html_e( 'Welcome To Echo Knowledge Base', 'echo-knowledge-base' ); ?></h2>
										<p><?php esc_html_e( 'Join', 'echo-knowledge-base' ); ?> <span class="epkb-highlight-text"><?php esc_html_e( '15,000+ professionals', 'echo-knowledge-base' ); ?></span> <?php esc_html_e( 'who use Echo Knowledge Base to build documentation for their businesses.', 'echo-knowledge-base' ); ?></p>
									</header>
								</div>
							</div>
							
							<div class="epkb-why-us-container">
								<div class="epkb-why-us-item">
									<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/why_us_icon1.png' ); ?>" alt="<?php esc_attr_e( 'Happy customers', 'echo-knowledge-base' ); ?>" class="epkb-why-us-icon">
									<div class="epkb-why-us-text">
										<span class="epkb-why-us-number">15,000+</span>
										<span class="epkb-why-us-description"><?php esc_html_e( 'Happy customers & counting', 'echo-knowledge-base' ); ?></span>
									</div>
								</div>
								
								<div class="epkb-why-us-item">
									<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/why_us_icon2.png' ); ?>" alt="<?php esc_attr_e( 'User reviews', 'echo-knowledge-base' ); ?>" class="epkb-why-us-icon">
									<div class="epkb-why-us-text">
										<span class="epkb-why-us-number">97</span>
										<span class="epkb-why-us-description"><?php esc_html_e( 'User reviews 5-stars rating', 'echo-knowledge-base' ); ?></span>
									</div>
								</div>
								
								<div class="epkb-why-us-item">
									<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/why_us_icon3.png' ); ?>" alt="<?php esc_attr_e( 'Free support', 'echo-knowledge-base' ); ?>" class="epkb-why-us-icon">
									<div class="epkb-why-us-text">
										<span class="epkb-why-us-number"><?php esc_html_e( 'Free Support', 'echo-knowledge-base' ); ?></span>
										<span class="epkb-why-us-description"><?php esc_html_e( '7 days/week', 'echo-knowledge-base' ); ?></span>
									</div>
								</div>
							</div>
						</div>

					<!-- Article Lists Container -->
					<div class="epkb-card-article-list-container">
						
						<!-- Most Viewed Articles -->
						<div class="epkb-card epkb-card--most-viewed">
							<div class="epkb-most-viewed-header">
								<h3><?php esc_html_e( 'Most Viewed Articles', 'echo-knowledge-base' ); ?></h3>
							</div>
							<div class="epkb-most-viewed-list">								<?php

								// Get most viewed articles
								$most_viewed_articles = array();
								if ( $this->kb_config['article_views_counter_enable'] == 'on' ) {
									$args = array(
										'post_type'      => $post_type,
										'post_status'    => 'publish',
										'posts_per_page' => 5,
										'orderby'        => 'meta_value_num',
										'meta_key'       => 'epkb-article-views',
										'order'          => 'DESC',
									);
									$most_viewed_articles = get_posts( $args );
								}
								
								if ( ! empty( $most_viewed_articles ) ) {
									$rank = 1;
									foreach ( $most_viewed_articles as $article ) {
										$views = EPKB_Utilities::get_postmeta( $article->ID, 'epkb-article-views', 0 );
										if ( is_wp_error( $views ) ) {
											EPKB_Logging::add_log( 'Failed to get article views', $views );
											$views = 0;
										}
										$article_url = get_permalink( $article->ID );										?>

										<div class="epkb-article-item">
											<div class="epkb-article-info">
												<span class="epkb-article-rank"><?php echo esc_html( $rank ); ?>.</span>
												<a href="<?php echo esc_url( $article_url ); ?>" class="epkb-article-title" target="_blank"><?php echo esc_html( $article->post_title ); ?></a>
											</div>
											<div class="epkb-article-views">
												<?php echo esc_html( number_format( $views ) ); ?> <?php esc_html_e( 'views', 'echo-knowledge-base' ); ?>
											</div>
										</div>
										<?php
										$rank++;
									}
								} else {
									?>
									<div class="epkb-article-item">
										<div class="epkb-article-info">
											<span class="epkb-article-title"><?php esc_html_e( 'Coming Soon', 'echo-knowledge-base' ); ?></span>
										</div>
									</div>
									<?php
								}								?>

							</div>
						</div>

						<!-- Recently Edited Articles -->
						<div class="epkb-card epkb-card--recently-edited">
							<div class="epkb-most-viewed-header">
								<h3><?php esc_html_e( 'Recently Edited Articles', 'echo-knowledge-base' ); ?></h3>
							</div>
							<div class="epkb-most-viewed-list">								<?php

								// Get recently edited articles
								$args = array(
									'post_type'      => $post_type,
									'post_status'    => 'publish',
									'posts_per_page' => 5,
									'orderby'        => 'modified',
									'order'          => 'DESC',
								);
								$recent_articles = get_posts( $args );
								
								if ( ! empty( $recent_articles ) ) {
									$rank = 1;
									foreach ( $recent_articles as $article ) {
										$article_url = get_permalink( $article->ID );
										$modified_date = get_the_modified_date( 'M j, Y', $article->ID );										?>
										<div class="epkb-article-item epkb-article-item--no-views">
											<div class="epkb-article-info">
												<span class="epkb-article-rank"><?php echo esc_html( $rank ); ?>.</span>
												<a href="<?php echo esc_url( $article_url ); ?>" class="epkb-article-title" target="_blank"><?php echo esc_html( $article->post_title ); ?></a>
											</div>
											<div class="epkb-article-date">
												<?php echo esc_html( $modified_date ); ?>
											</div>
										</div>										<?php
										$rank++;
									}
								} else {
									?>
									<div class="epkb-article-item epkb-article-item--no-views">
										<div class="epkb-article-info">
											<span class="epkb-article-title"><?php esc_html_e( 'Coming Soon', 'echo-knowledge-base' ); ?></span>
										</div>
									</div>
									<?php
								}								?>
							</div>
						</div>
						
					</div> <!-- End of Article Lists Container -->

					<!-- AI Chatbot -->
					<article class="epkb-card epkb-card--chatbot">
						<div class="epkb-chatbot-content">
							<div class="epkb-chatbot-text">
								<div class="epkb-chatbot-heading">
									<h2><?php esc_html_e( 'Echo Knowledge Base', 'echo-knowledge-base' ); ?> <span class="epkb-magic-icon"><i class="epkbfa epkbfa-magic"></i></span> <span class="epkb-ai-addon-text" style="white-space:nowrap;"><?php esc_html_e( 'New Chat AI', 'echo-knowledge-base' ); ?></span></h2>
								</div>
								<div class="epkb-chatbot-description">
									<p><?php esc_html_e( 'Transform your knowledge base with AI-powered chat that instantly answers visitor questions. Our intelligent chatbot learns from your documentation to provide accurate, context-aware responses 24/7. Reduce support tickets, improve user satisfaction, and let AI handle repetitive queries while your team focuses on complex issues.', 'echo-knowledge-base' ); ?></p>
								</div>
								<div class="epkb-chatbot-button">
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-ai-chat' ) ); ?>" class="epkb-btn epkb-btn-primary-outline">
										<?php esc_html_e( 'Get AI Chatbot', 'echo-knowledge-base' ); ?>
									</a>
								</div>
							</div>
							<div class="epkb-chatbot-image">
								<figure>
									<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/ai-chatbot-image-1.png' ); ?>" alt="<?php esc_attr_e( 'AI Chatbot screenshot', 'echo-knowledge-base' ); ?>">
								</figure>
							</div>
						</div>
					</article>
					
					</div> <!-- End of Main Content -->
					
					<!-- Sidebar (30%) -->
					<div class="epkb-sidebar">

					<!-- Upsell -->
					<aside class="epkb-card epkb-card--upsell">
						<div class="epkb-upsell-bg">
							<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/line-bg.jpg' ); ?>" alt="<?php esc_attr_e( 'Background pattern', 'echo-knowledge-base' ); ?>">
						</div>
						<div class="epkb-upsell-content">
							<div class="epkb-upsell-header">
								<span class="epkb-upsell-icon epkbfa epkbfa-trophy"></span>
								<h3><?php esc_html_e( 'Premium Add-Ons', 'echo-knowledge-base' ); ?></h3>
							</div>
							<ul class="epkb-checklist">
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'Unlimited Knowledge Bases', 'echo-knowledge-base' ); ?></span></li>
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'Access Manager, KB Groups and Roles', 'echo-knowledge-base' ); ?></span></li>
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'Elegant Layouts', 'echo-knowledge-base' ); ?></span></li>
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'Advanced Search', 'echo-knowledge-base' ); ?></span></li>
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'Articles Import and Export', 'echo-knowledge-base' ); ?></span></li>
								<li><span class="epkb-checklist-icon epkbfa epkbfa-check"></span><span class="epkb-checklist-text"><?php esc_html_e( 'AI PRO Features', 'echo-knowledge-base' ); ?></span></li>
							</ul>
							<a href="https://www.echoknowledgebase.com/wordpress-plugin/pricing/" target="_blank" class="epkb-btn epkb-btn-upgrade-pro">
								<span class="epkbfa epkbfa-trophy"></span>
								<?php esc_html_e( 'Upgrade to PRO', 'echo-knowledge-base' ); ?>
							</a>
						</div>
					</aside>

					<!-- What's New -->
					<aside class="epkb-card epkb-card--whatsnew">
						<div class="epkb-whatsnew-header">
							<span class="epkb-whatsnew-icon epkbfa epkbfa-star"></span>
							<h3><?php esc_html_e( 'What\'s New?', 'echo-knowledge-base' ); ?></h3>
						</div>
						<ul class="epkb-checklist epkb-checklist--whatsnew">
							<li>
								<div>
									<span class="epkb-checklist-icon epkbfa epkbfa-comments"></span>
									<span class="epkb-checklist-text"><?php esc_html_e( 'AI Chat (Beta) - Currently in testing with select users', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<li>
								<div>
									<span class="epkb-checklist-icon epkbfa epkbfa-search"></span>
									<span class="epkb-checklist-text"><?php esc_html_e( 'AI Search (Beta) - Being tested by beta users', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<li>
								<div>
									<span class="epkb-checklist-icon epkbfa epkbfa-edit"></span>
									<span class="epkb-checklist-text"><?php esc_html_e( 'Frontend Editor for visual customization', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<li>
								<div>
									<span class="epkb-checklist-icon epkbfa epkbfa-bars"></span>
									<span class="epkb-checklist-text"><?php esc_html_e( 'Sticky sidebar for better navigation', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
						</ul>
					</aside>
					
					</div> <!-- End of Sidebar -->

				</section>

				<!-- ================= Quick‑Links ================= -->
				<section class="epkb-quicklinks-row">

					<a href="https://www.echoknowledgebase.com/documentation/" target="_blank" class="epkb-ql-card epkb-ql-card--documentation">
						<div class="epkb-ql-icon-container">
							<span class="epkb-ql-icon epkbfa epkbfa-book"></span>
						</div>
						<h3><?php esc_html_e( 'Documentation', 'echo-knowledge-base' ); ?></h3>
						<p><?php esc_html_e( 'Get started by spending some time with the documentation and build an awesome Knowledge Base for your customers.', 'echo-knowledge-base' ); ?></p>
						<span class="epkb-action-text"><?php esc_html_e( 'Read Me', 'echo-knowledge-base' ); ?></span>
					</a>

					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-ai-chat' ) ); ?>" target="_blank" class="epkb-ql-card epkb-ql-card--beta">
						<div class="epkb-ql-icon-container">
							<span class="epkb-ql-icon epkbfa epkbfa-rocket"></span>
						</div>
						<h3><?php esc_html_e( 'Join the AI Beta', 'echo-knowledge-base' ); ?></h3>
						<p><?php esc_html_e( 'Join the AI Beta program, connect with fellow developers and get early access to new features.', 'echo-knowledge-base' ); ?></p>
						<span class="epkb-action-text"><?php esc_html_e( 'Join Beta', 'echo-knowledge-base' ); ?></span>
					</a>

					<a href="https://www.echoknowledgebase.com/contact-us/" target="_blank" class="epkb-ql-card epkb-ql-card--help">
						<div class="epkb-ql-icon-container">
							<span class="epkb-ql-icon epkbfa epkbfa-life-ring"></span>
						</div>
						<h3><?php esc_html_e( 'Need Help?', 'echo-knowledge-base' ); ?></h3>
						<p><?php esc_html_e( 'Stuck with something? Get help from live chat or a support ticket.', 'echo-knowledge-base' ); ?></p>
						<span class="epkb-action-text"><?php esc_html_e( 'Get Support', 'echo-knowledge-base' ); ?></span>
					</a>

					<a href="https://wordpress.org/support/plugin/echo-knowledge-base/reviews/" target="_blank" class="epkb-ql-card epkb-ql-card--love">
						<div class="epkb-ql-icon-container">
							<span class="epkb-ql-icon epkbfa epkbfa-heart"></span>
						</div>
						<h3><?php esc_html_e( 'Show Your Love', 'echo-knowledge-base' ); ?></h3>
						<p><?php esc_html_e( 'We love to have you in Echo Knowledge Base family. Take your 2 minutes to review the plugin and spread the love!', 'echo-knowledge-base' ); ?></p>
						<span class="epkb-action-text"><?php esc_html_e( 'Review Now', 'echo-knowledge-base' ); ?></span>
					</a>

				</section>

			</div>
		</div>	    <?php
	}


	/**
	 * Enqueue scripts for dashboard page
	 */
	public function enqueue_dashboard_scripts() {
		$screen = get_current_screen();
		if ( !$screen || $screen->id !== 'toplevel_page_epkb-dashboard' ) {
			return;
		}

		// Ensure WordPress admin scripts are loaded
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-effects-core' );
		wp_enqueue_script( 'jquery-effects-bounce' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		// Load plugin admin scripts
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_style( 'epkb-admin-plugin-pages-styles', Echo_Knowledge_Base::$plugin_url . 'css/admin-plugin-pages' . $suffix . '.css', array(), Echo_Knowledge_Base::$version );
		wp_enqueue_script( 'epkb-admin-plugin-pages-ui', Echo_Knowledge_Base::$plugin_url . 'js/admin-ui' . $suffix . '.js', array('jquery'), Echo_Knowledge_Base::$version );

		// Localize script with epkb_vars to prevent JavaScript errors
		wp_localize_script( 'epkb-admin-plugin-pages-ui', 'epkb_vars', array(
			'msg_try_again' => esc_html__( 'Please try again later.', 'echo-knowledge-base' ),
			'error_occurred' => esc_html__( 'Error occurred', 'echo-knowledge-base' ) . ' (151)',
			'not_saved' => esc_html__( 'Error occurred', 'echo-knowledge-base' ) . ' (152)',
			'unknown_error' => esc_html__( 'Unknown error', 'echo-knowledge-base' ) . ' (1783)',
			'reload_try_again' => esc_html__( 'Please reload the page and try again.', 'echo-knowledge-base' ),
			'save_config' => esc_html__( 'Saving configuration', 'echo-knowledge-base' ),
			'input_required' => esc_html__( 'Input is required', 'echo-knowledge-base' ),
			'sending_feedback' => esc_html__( 'Sending feedback', 'echo-knowledge-base' ) . '...',
			'changing_debug' => esc_html__( 'Changing debug', 'echo-knowledge-base' ) . '...',
			'help_text_coming' => esc_html__( 'Help text is coming soon.', 'echo-knowledge-base' ),
			'nonce' => wp_create_nonce( '_wpnonce_epkb_ajax_action' ),
			'msg_reading_posts' => esc_html__( 'Reading items', 'echo-knowledge-base' ) . '...',
			'msg_confirm_kb' => esc_html__( 'Please confirm Knowledge Base to import into.', 'echo-knowledge-base' ),
			'msg_confirm_backup' => esc_html__( 'Please confirm you backed up your database or understand that import can potentially make undesirable changes.', 'echo-knowledge-base' ),
			'msg_empty_post_type' => esc_html__( 'Please select post type.', 'echo-knowledge-base' ),
			'msg_nothing_to_convert' => esc_html__( 'No posts to convert.', 'echo-knowledge-base' ),
			'msg_select_article' => esc_html__( 'Please select posts to convert.', 'echo-knowledge-base' ),
			'msg_articles_converted' => esc_html__( 'Items converted', 'echo-knowledge-base' ),
			'msg_converting' => esc_html__( 'Converting, please wait...', 'echo-knowledge-base' ),
			'on_kb_main_page_layout' => esc_html__( 'First, the selected layout will be saved. Then, the page will reload and you can see the layout change on the KB frontend.', 'echo-knowledge-base' ),
			'on_kb_templates' => esc_html__( 'First, the KB Base Template will be enabled. Then the page will reload after which you can see the layout change on the KB frontend.', 'echo-knowledge-base' ),
			'on_current_theme_templates' => esc_html__( 'First, the Current Theme Template will be enabled. Then the page will reload after which you can see the layout change on the KB frontend. If you have issues using the Current Theme Template, switch back to the KB Template or contact us for help.', 'echo-knowledge-base' ),
			'on_modular_main_page_toggle' => esc_html__( 'First, the Modular Main Page settings will be saved. Then, the page will reload and you can see the page structure change on the KB frontend.', 'echo-knowledge-base' ),
			'on_article_search_sync_toggle' => esc_html__( 'First, the current settings will be saved. Then, the page will reload.', 'echo-knowledge-base' ),
			'on_article_search_toggle' => esc_html__( 'First, the current settings will be saved. Then, the page will reload.', 'echo-knowledge-base' ),
			'on_asea_presets_selection' => esc_html__( 'First, the current settings will be saved. Then, the page will reload.', 'echo-knowledge-base' ),
			'on_faqs_presets_selection' => esc_html__( 'First, the current settings will be saved. Then, the page will reload.', 'echo-knowledge-base' ),
			'on_archive_page_v3_toggle' => esc_html__( 'First, the current settings will be saved. Then, the page will reload.', 'echo-knowledge-base' ),
			'preview_not_available' => esc_html__( 'Preview functionality will be implemented soon.', 'echo-knowledge-base' ),
			'msg_empty_input' => esc_html__( 'Missing input', 'echo-knowledge-base' ),
			'msg_no_key_admin' => esc_html__( 'You have no API key. Please add it here', 'echo-knowledge-base' ),
			'msg_no_key' => esc_html__( 'You have no API key.', 'echo-knowledge-base' ),
			'ai_help_button_title' => esc_html__( 'AI Help', 'echo-knowledge-base' ),
			'msg_ai_help_loading' => esc_html__( 'Processing...', 'echo-knowledge-base' ),
			'msg_ai_copied_to_clipboard' => esc_html__( 'Copied to clipboard', 'echo-knowledge-base' ),
			'copied_text' => esc_html__( 'Copied!', 'echo-knowledge-base' ),
			'group_selected_singular' => esc_html__( 'group selected', 'echo-knowledge-base' ),
			'group_selected_plural' => esc_html__( 'groups selected', 'echo-knowledge-base' ),
		) );
	}

	/**
	 * Get week numbers for the current month
	 * @return array
	 */
	private function get_month_weeks() {
		$current_month = date( 'n' );
		$current_year = date( 'Y' );
		$weeks = array();

		// Get first and last day of month
		$first_day = mktime( 0, 0, 0, $current_month, 1, $current_year );
		$last_day = mktime( 0, 0, 0, (int)$current_month + 1, 0, $current_year );

		// Get week numbers
		$first_week = date( 'W', $first_day );
		$last_week = date( 'W', $last_day );

		// Handle year transition
		if ( $last_week < $first_week ) {
			// December to January transition
			for ( $w = $first_week; $w <= 53; $w++ ) {
				$weeks[] = $w;
			}
			for ( $w = 1; $w <= $last_week; $w++ ) {
				$weeks[] = $w;
			}
		} else {
			for ( $w = $first_week; $w <= $last_week; $w++ ) {
				$weeks[] = $w;
			}
		}

		return $weeks;
	}
}