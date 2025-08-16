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
		
		// Check if Setup Wizard should be shown (first 2 weeks)
		$show_setup_wizard = $this->should_show_setup_wizard();
		
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
						</a>						<?php 
						
						$kb_main_page_url = EPKB_KB_Handler::get_first_kb_main_page_url( $this->kb_config );
						if ( empty( $kb_main_page_url ) ) { ?>
							<a href="#" class="epkb-btn epkb-btn-frontend-editor epkb-btn-no-kb-main-page" data-setup-wizard-url="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-configuration&setup-wizard-on=true' ) ); ?>">
								<?php esc_html_e( 'Frontend Editor', 'echo-knowledge-base' ); ?>
							</a>						<?php 
						} else { ?>
							<a href="<?php echo esc_url( $kb_main_page_url ) . '?action=epkb_load_editor'; ?>" class="epkb-btn epkb-btn-frontend-editor" target="_blank">
								<?php esc_html_e( 'Frontend Editor', 'echo-knowledge-base' ); ?>
							</a>						<?php 
						} ?>						<?php 
						if ( $show_setup_wizard ) { ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-configuration&setup-wizard-on=true' ) ); ?>" class="epkb-btn epkb-btn-setup-wizard">							<?php 
								esc_html_e( 'Setup Wizard', 'echo-knowledge-base' ); ?>
							</a>						<?php 
						} ?>
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
										<span class="epkb-why-us-number">112</span>
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
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type . '&page=epkb-kb-ai-chat&active_tab=chat' ) ); ?>" class="epkb-btn epkb-btn-primary-outline">
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

					<!-- Quick Actions - Hidden for now -->
					<?php /* Temporarily hidden
					<aside class="epkb-card epkb-card--quick-actions">
						<div class="epkb-quick-actions-header">
							<h3><?php esc_html_e( 'Quick Actions', 'echo-knowledge-base' ); ?></h3>
						</div>
						<div class="epkb-quick-actions-list">
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=training-data' ) ); ?>" class="epkb-quick-action-item">
								<span class="epkb-quick-action-icon epkbfa epkbfa-sync"></span>
								<div class="epkb-quick-action-content">
									<h4><?php esc_html_e( 'Sync Training Data', 'echo-knowledge-base' ); ?></h4>
									<p><?php esc_html_e( 'Update your AI knowledge base with latest content', 'echo-knowledge-base' ); ?></p>
								</div>
							</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=epkb_post_type_1&page=epkb-kb-ai-chat&active_tab=general-settings' ) ); ?>" class="epkb-quick-action-item">
								<span class="epkb-quick-action-icon epkbfa epkbfa-cog"></span>
								<div class="epkb-quick-action-content">
									<h4><?php esc_html_e( 'Configure AI Settings', 'echo-knowledge-base' ); ?></h4>
									<p><?php esc_html_e( 'Adjust AI behavior and response settings', 'echo-knowledge-base' ); ?></p>
								</div>
							</a>
							<a href="https://www.echoknowledgebase.com/docs/ai-getting-started" target="_blank" class="epkb-quick-action-item">
								<span class="epkb-quick-action-icon epkbfa epkbfa-play-circle"></span>
								<div class="epkb-quick-action-content">
									<h4><?php esc_html_e( 'Getting Started Guide', 'echo-knowledge-base' ); ?></h4>
									<p><?php esc_html_e( 'Learn how to set up and configure AI features', 'echo-knowledge-base' ); ?></p>
								</div>
							</a>
							<a href="#" onclick="if(document.querySelector('.epkb-help-chat-button')) { document.querySelector('.epkb-help-chat-button').click(); } else { alert('AI Help is loading...'); } return false;" class="epkb-quick-action-item epkb-quick-action--ai">
								<span class="epkb-quick-action-icon epkbfa epkbfa-comments"></span>
								<div class="epkb-quick-action-content">
									<h4><?php esc_html_e( 'Get Instant AI Help', 'echo-knowledge-base' ); ?> <span style="color: #ff3333; font-size: 10px; font-weight: bold; margin-left: 5px;"><?php esc_html_e( 'NEW', 'echo-knowledge-base' ); ?></span></h4>
									<p><?php esc_html_e( 'Ask questions and get instant answers', 'echo-knowledge-base' ); ?></p>
								</div>
							</a>
						</div>
					</aside>
					*/ ?>

					<!-- What's New -->
					<aside class="epkb-card epkb-card--whatsnew">
						<div class="epkb-whatsnew-header">
							<h3><?php esc_html_e( 'What\'s New', 'echo-knowledge-base' ); ?></h3>
						</div>
						<ul class="epkb-whatsnew-list">
							<li class="epkb-whatsnew-item epkb-whatsnew-item--new">
								<span class="epkb-whatsnew-badge"><?php esc_html_e( 'NEW', 'echo-knowledge-base' ); ?></span>
								<div class="epkb-whatsnew-content">
									<strong><?php esc_html_e( 'AI Chat Beta', 'echo-knowledge-base' ); ?></strong>
									<span><?php esc_html_e( 'Intelligent conversational assistant', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<li class="epkb-whatsnew-item epkb-whatsnew-item--new">
								<span class="epkb-whatsnew-badge"><?php esc_html_e( 'NEW', 'echo-knowledge-base' ); ?></span>
								<div class="epkb-whatsnew-content">
									<strong><?php esc_html_e( 'AI Search Beta', 'echo-knowledge-base' ); ?></strong>
									<span><?php esc_html_e( 'Smart semantic search capabilities', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<li class="epkb-whatsnew-item">
								<div class="epkb-whatsnew-content">
									<strong><?php esc_html_e( 'AI Dashboard', 'echo-knowledge-base' ); ?></strong>
									<span><?php esc_html_e( 'Centralized AI management', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							<?php /* Temporarily hidden - backend help chat
							<li class="epkb-whatsnew-item">
								<div class="epkb-whatsnew-content">
									<strong><?php esc_html_e( 'Backend Help Chat', 'echo-knowledge-base' ); ?></strong>
									<span><?php esc_html_e( 'Instant AI-powered assistance', 'echo-knowledge-base' ); ?></span>
								</div>
							</li>
							*/ ?>
						</ul>
					</aside>

					<!-- Upsell -->
					<aside class="epkb-card epkb-card--upsell">
						<div class="epkb-upsell-bg">
							<img src="<?php echo esc_url( Echo_Knowledge_Base::$plugin_url . 'img/line-bg.jpg' ); ?>" alt="<?php esc_attr_e( 'Background pattern', 'echo-knowledge-base' ); ?>">
						</div>
						<div class="epkb-upsell-content">
							<div class="epkb-upsell-header">
								<span class="epkb-upsell-icon epkbfa epkbfa-trophy"></span>
								<h3><?php esc_html_e( 'Premium Add-Ons', 'echo-knowledge-base' ); ?></h3>
								<a href="https://www.echoknowledgebase.com/bundle-pricing/" target="_blank" class="epkb-btn epkb-btn-upgrade-pro">
									<span class="epkbfa epkbfa-trophy"></span>
									<?php esc_html_e( 'Upgrade to PRO', 'echo-knowledge-base' ); ?>
								</a>
							</div>
							
							<!-- Add-ons Carousel -->
							<div class="epkb-addons-carousel-wrapper">
								<div class="epkb-addons-carousel">
									<div class="epkb-addons-carousel-track">
										<?php echo $this->get_addons_carousel_items(); ?>
									</div>
								</div>
								<button class="epkb-carousel-nav epkb-carousel-prev" aria-label="Previous">
									<span class="epkbfa epkbfa-chevron-left"></span>
								</button>
								<button class="epkb-carousel-nav epkb-carousel-next" aria-label="Next">
									<span class="epkbfa epkbfa-chevron-right"></span>
								</button>
							</div>
						</div>
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

					<div class="epkb-ql-card epkb-ql-card--help epkb-ql-card--split">
						<div class="epkb-ql-icon-container">
							<span class="epkb-ql-icon epkbfa epkbfa-comments"></span>
						</div>
						<h3><?php esc_html_e( 'Need Help?', 'echo-knowledge-base' ); ?></h3>
						<p><?php esc_html_e( 'Get instant answers or contact our support team.', 'echo-knowledge-base' ); ?></p>
						<div class="epkb-help-options">
							<?php /* Temporarily hidden - backend help chat
							<button onclick="if(document.querySelector('.epkb-help-chat-button')) { document.querySelector('.epkb-help-chat-button').click(); } else { alert('AI Help is loading...'); } return false;" class="epkb-btn epkb-btn-ai-help">
								<span class="dashicons dashicons-editor-help"></span>
								<?php esc_html_e( 'AI Help (Instant)', 'echo-knowledge-base' ); ?>
								<span class="epkb-beta-tag"><?php esc_html_e( 'BETA', 'echo-knowledge-base' ); ?></span>
							</button>
							*/ ?>
							<a href="https://www.echoknowledgebase.com/contact-us/" target="_blank" class="epkb-btn epkb-btn-human-support">
								<span class="dashicons dashicons-admin-users"></span>
								<?php esc_html_e( 'Human Support', 'echo-knowledge-base' ); ?>
							</a>
						</div>
					</div>

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
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Add-ons Carousel functionality
			let currentIndex = 0;
			const $track = $('.epkb-addons-carousel-track');
			const $items = $('.epkb-carousel-item');
			const itemCount = $items.length;
			
			function updateCarousel() {
				const translateX = -currentIndex * 100;
				$track.css('transform', 'translateX(' + translateX + '%)');
				
				// Update button states
				$('.epkb-carousel-prev').prop('disabled', currentIndex === 0);
				$('.epkb-carousel-next').prop('disabled', currentIndex === itemCount - 1);
			}
			
			$('.epkb-carousel-prev').on('click', function() {
				if (currentIndex > 0) {
					currentIndex--;
					updateCarousel();
				}
			});
			
			$('.epkb-carousel-next').on('click', function() {
				if (currentIndex < itemCount - 1) {
					currentIndex++;
					updateCarousel();
				}
			});
			
			// Initialize carousel
			updateCarousel();
			
			// Add click handler for carousel items
			$('.epkb-carousel-item img, .epkb-carousel-item h4').on('click', function() {
				const $item = $(this).closest('.epkb-carousel-item');
				const addonData = $item.data('addon');
				
				if (addonData) {
					// Create and show modal dialog
					const dialogContent = '<div class="epkb-addon-dialog-content">' +
						'<div class="epkb-addon-dialog-image">' +
							'<img src="' + addonData.img + '" alt="' + addonData.title + '">' +
						'</div>' +
						'<div class="epkb-addon-dialog-text">' +
							'<h3>' + addonData.title + '</h3>' +
							(addonData.special_note ? '<p class="epkb-addon-note"><i>' + addonData.special_note + '</i></p>' : '') +
							'<p class="epkb-addon-desc">' + addonData.desc + '</p>' +
							'<a href="' + addonData.learn_more_url + '" target="_blank" class="epkb-primary-btn">Learn More</a>' +
						'</div>' +
					'</div>';
					
					const $dialog = $('<div>').html(dialogContent).dialog({
						title: addonData.title,
						modal: true,
						width: 750,
						maxWidth: '90%',
						height: 'auto',
						maxHeight: '80vh',
						resizable: false,
						dialogClass: 'epkb-addon-dialog',
						close: function() {
							$(this).dialog('destroy').remove();
						}
					});
					
					// Close dialog when clicking outside (on overlay)
					$('.ui-widget-overlay').on('click', function() {
						$dialog.dialog('close');
					});
				}
			});
		});
		</script>
		
		<style>
		.epkb-carousel-item img,
		.epkb-carousel-item h4 {
			cursor: pointer;
			transition: opacity 0.2s ease;
		}
		
		.epkb-carousel-item img:hover {
			opacity: 0.9;
		}
		
		.epkb-carousel-item h4:hover {
			color: #0073aa;
		}
		
		.epkb-addon-dialog {
			z-index: 10000;
		}
		
		.epkb-addon-dialog .ui-dialog-content {
			padding: 30px !important;
			max-height: 70vh;
			overflow-y: auto;
		}
		
		.epkb-addon-dialog-content {
			display: flex;
			flex-direction: column;
			gap: 25px;
		}
		
		.epkb-addon-dialog-image {
			text-align: center;
		}
		
		.epkb-addon-dialog-image img {
			width: 100%;
			max-width: 600px;
			height: auto;
			border-radius: 10px;
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		}
		
		.epkb-addon-dialog-text h3 {
			margin: 0 0 15px 0;
			font-size: 24px;
			font-weight: 600;
			color: #2c3e50;
		}
		
		.epkb-addon-note {
			color: #666;
			font-style: italic;
			margin: 0 0 15px 0;
			font-size: 15px;
		}
		
		.epkb-addon-desc {
			margin: 0 0 25px 0;
			line-height: 1.7;
			font-size: 15px;
			color: #4a5568;
		}
		
		.epkb-addon-dialog .epkb-primary-btn {
			display: inline-block;
			padding: 12px 30px;
			background: #0073aa;
			color: #fff;
			text-decoration: none;
			border-radius: 5px;
			transition: background 0.2s ease;
			font-size: 15px;
			font-weight: 500;
		}
		
		.epkb-addon-dialog .epkb-primary-btn:hover {
			background: #005a87;
			box-shadow: 0 2px 8px rgba(0, 115, 170, 0.3);
		}
		</style>	    <?php
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
	 * Check if Setup Wizard button should be shown (first 2 weeks after installation)
	 * @return bool
	 */
	private function should_show_setup_wizard() {
		
		// Get the installation date from KB config
		$install_date = empty( $this->kb_config['plugin_install_date'] ) ? '' : $this->kb_config['plugin_install_date'];
		
		// If no install date set, this is a new installation
		if ( empty( $install_date ) ) {
			return true;
		}
		
		// Calculate if we're within 2 weeks (14 days) of installation
		$install_timestamp = strtotime( $install_date );
		if ( $install_timestamp === false ) {
			return false; // Invalid date
		}
		
		$two_weeks_in_seconds = 14 * 24 * 60 * 60;
		$time_since_install = current_time( 'timestamp' ) - $install_timestamp;
		
		return $time_since_install <= $two_weeks_in_seconds;
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
	
	/**
	 * Get add-ons carousel items HTML
	 *
	 * @return string
	 */
	private function get_addons_carousel_items() {
		
		$addons = array(
			array(
				'title'             => esc_html__( 'AI Features', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'Smart AI-powered support', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/2025/08/ai-features-banner.jpg',
				'desc'              => sprintf( esc_html__( '%sAI Chat%s with instant answers, %sSmart Search%s with AI-generated responses, and %sAdvanced Training%s on posts, pages & custom content.', 'echo-knowledge-base' ), '<strong>', '</strong>', '<strong>', '</strong>', '<strong>', '</strong>' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/ai-features/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=ai-features',
			),
			array(
				'title'             => esc_html__( 'Unlimited Knowledge Bases', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'Expand your documentation', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/2020/07/featured-image-MKB-1.jpg',
				'desc'              => sprintf( esc_html__( 'Create a separate Knowledge Base for each %sproduct, service or team%s.', 'echo-knowledge-base' ), '<strong>', '</strong>' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/multiple-knowledge-bases/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=multiple-kbs'
			),
			array(
				'title'             => esc_html__( 'Advanced Search', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'Enhance and analyze user searches', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/2020/07/featured-image-ASEA-1.jpg',
				'desc'              => esc_html__( "Enhance users' search experience and view search analytics, including popular searches and no results searches.", 'echo-knowledge-base' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/advanced-search/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=advanced-search'
			),
			array(
				'title'             => esc_html__( 'Elegant Layouts', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'More ways to design your KB', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/2020/07/featured-image-ELAY-1.1.jpg',
				'desc'              => sprintf( esc_html__( 'Use %sGrid Layout%s or %sSidebar Layout%s for KB Main page or combine Basic, Tabs, Grid and Sidebar layouts in many cool ways.', 'echo-knowledge-base' ), '<strong>', '</strong>', '<strong>', '</strong>' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/elegant-layouts/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=elegant-layouts',
			),
			array(
				'title'             => esc_html__( 'Access Manager', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'Protect your KB content', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/2020/07/featured-image-AMGR-1.jpg',
				'desc'              => esc_html__( 'Restrict your Articles to certain Groups using KB Categories. Assign users to specific KB Roles within Groups.', 'echo-knowledge-base' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/access-manager/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=access-manager'
			),
			array(
				'title'             => esc_html__( 'Migrate, Copy, Import and Export', 'echo-knowledge-base' ),
				'special_note'      => esc_html__( 'Import, export and copy Articles', 'echo-knowledge-base' ),
				'img'               => 'https://www.echoknowledgebase.com/wp-content/uploads/edd/2022/01/KB-Import-Export-Banner-v2.jpg',
				'desc'              => esc_html__( "Powerful import and export plugin to migrate, create and copy articles and images from your Knowledge Base.", 'echo-knowledge-base' ),
				'learn_more_url'    => 'https://www.echoknowledgebase.com/wordpress-plugin/kb-import-export/?utm_source=plugin&utm_medium=dashboard&utm_content=carousel&utm_campaign=kb-import-export',
			),
		);
		
		$html = '';
		foreach ( $addons as $addon ) {
			$addon_json = htmlspecialchars( json_encode( $addon ), ENT_QUOTES, 'UTF-8' );
			$html .= '<div class="epkb-carousel-item" data-addon=\'' . $addon_json . '\'>';
			$html .= '<img src="' . esc_url( $addon['img'] ) . '" alt="' . esc_attr( $addon['title'] ) . '">';
			$html .= '<h4>' . esc_html( $addon['title'] ) . '</h4>';
			$html .= '</div>';
		}
		
		return $html;
	}
}