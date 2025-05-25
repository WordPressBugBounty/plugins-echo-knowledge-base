jQuery( document ).ready( function( $ ) {

	let frontendEditor = $( '#epkb-fe__editor' );

	// show the frontend editor if 'epkb-modular-main-page-container' is found in the page
	if ( $( '#epkb-modular-main-page-container' ).length > 0 || $( '#eckb-article-page-container-v2' ).length > 0 || $( '#eckb-archive-page-container' ).length > 0 ) {
		$( '#epkb-fe__toggle' ).show();
	} else {
		$( '#wp-admin-bar-epkb-edit-mode-button' ).hide();
	}

	let admin_report_error_form = $( '#epkb-fe__error-form-wrap .epkb-admin__error-form__container' );

	const MAX_ROW_NUMBER = 5;

	// Handle FE Open Button and Admin bar FE edit link
	$( '.epkb-fe__toggle, #wp-admin-bar-epkb-edit-mode-button' ).on( 'click', function( e ) {
		e.preventDefault(); // Prevent the default link behavior
		frontendEditor.css( 'right', '0' );
		frontendEditor.show();
		$( '.epkb-fe__toggle' ).hide();
		$( '#epkb-fe__editor .epkb-fe__feature-settings' ).each(function () {
			if ( $( this ).find( '.epkb-row-module-position select' ).val() === 'none' ) {
				$( this ).find( '.epkb-fe__settings-section:not(.epkb-fe__settings-section--module-position)' ).addClass( 'epkb-fe__settings-section--hide' );
			}
		} );
	});

	// Close FE
	$( document ).on( 'click', '.epkb-fe__header-close-button', function() {
		frontendEditor.hide();
		$( '.epkb-fe__toggle' ).show();
		$( '#epkb-fe__action-back' ).trigger( 'click' );
	} );

	// Open Help tab
	$( document ).on( 'click', '#epkb-fe__help-tab', function() {

		// Add Help class and remove Main class
		frontendEditor.addClass( 'epkb-fe__editor--help' ).removeClass( 'epkb-fe__editor--home' );

		// Show action buttons
		$( '#epkb-fe__editor .epkb-fe__actions' ).show();
	} );

	// Show settings for a feature
	$( document ).on( 'click', '.epkb-fe__feature-select-button', function() {
		let $button = $( this );
		let feature_name = $button.data( 'feature' );
		let $tab = $( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"]' );

		$( '.epkb-fe__header-title' ).hide();
		$( '.epkb-fe__header-title[data-title="' + feature_name + '"]' ).addClass( 'epkb-fe__header-title--active' );

		// set epkb-fe__editor--settings and remove rest of classes
		frontendEditor.removeClass(function(index, className) {
			return (className.match(/\bepkb-fe__editor--\S+/g) || []).join(' ');
		});
		frontendEditor.addClass( 'epkb-fe__editor--settings' );

		$( '.epkb-fe__feature-select-button' ).hide();
		$( '.epkb-fe__feature-settings' ).removeClass( 'epkb-fe__feature-settings--active' );
		$tab.addClass( 'epkb-fe__feature-settings--active' );

		// Ensure custom dropdowns in the newly shown tab reflect the current select values
		$tab.find('.epkb-input-custom-dropdown select').each(function() {
			update_custom_dropdown_display($(this));
		});

		$( '#epkb-fe__editor .epkb-fe__actions' ).show();
		$( '.epkb-fe__top-actions' ).hide();
	} );

	// Back button to hide settings for the feature and show features list
	$( document ).on( 'click', '#epkb-fe__action-back', function() {

		// set epkb-fe__editor--home and remove rest of classes
		frontendEditor.removeClass(function(index, className) {
			return (className.match(/\bepkb-fe__editor--\S+/g) || []).join(' ');
		  });
		$( '.epkb-fe__header-title' ).removeClass( 'epkb-fe__header-title--active' );
		frontendEditor.addClass( 'epkb-fe__editor--home' );

		// Show module icons
		$( '.epkb-fe__feature-select-button' ).show();

		// Hide all module settings
		$( '.epkb-fe__feature-settings' ).removeClass( 'epkb-fe__feature-settings--active' );

		// Hide action buttons
		$( '#epkb-fe__editor .epkb-fe__actions' ).hide();

		// Show 'close' button
		$( '.epkb-fe__top-actions' ).show();
	} );

	// Expand / Collapse settings section
	$( document ).on( 'click', '.epkb-fe__settings-section-header', function() {
		let $section = $( this ).parent();
		let $sectionBody = $section.find( '.epkb-fe__settings-section-body' );
		if ( $section.hasClass( 'epkb-fe__is_opened' ) ) {
			$sectionBody.stop().slideUp();
			$section.removeClass( 'epkb-fe__is_opened' );
		} else {
			$sectionBody.stop().slideDown();
			$section.addClass( 'epkb-fe__is_opened' );
		}
	} );

	// Switch Settings boxes which belong to certain feature
	// Add the following CSS classes in PHP config to necessary Settings boxes:
	// - epkb-fe__settings-section--module-box
	// - epkb-fe__settings-section--{module name}-box
	// - epkb-fe__settings-section--hide
	// Add 'data' => [ 'insert-box-after' => {selector} ] in PHP config to insert the box after certain Settings box
	// Adapted from admin-plugin-pages.js - has similar functionality
	function switch_module_boxes( module_selector ) {
		let current_module_name = $( module_selector ).val();

		// Hide other modules Settings boxes in the current tab
		let other_modules_boxes = $( module_selector ).closest( '.epkb-fe__feature-settings' ).find( '.epkb-fe__settings-section--module-box:not(.epkb-fe__settings-section--' + current_module_name + '-box)' );
		other_modules_boxes.addClass( 'epkb-fe__settings-section--hide' );

		// Find all Settings boxes which belong to the currently selected module
		let module_boxes = $( module_selector ).closest( '.epkb-fe__features-list' ).find( '.epkb-fe__settings-section--' + current_module_name + '-box' );
		if ( ! module_boxes.length ) {
			return;
		}

		$( module_boxes.get().reverse() ).each( function () {
			$( this ).removeClass( 'epkb-fe__settings-section--hide' );

			// Show Settings boxes which belong to the currently selected module
			let insert_box_after = $( this ).data( 'insert-box-after' );
			$( module_selector ).closest( '.epkb-fe__feature-settings .epkb-fe__settings-list' ).find( insert_box_after ).after( this );
		} );

		// Insure the selected Layout is shown as active - fix for Grid or Sidebar Layout selection with Elegant Layouts disabled
		if ( current_module_name === 'categories_articles' ) {
			$( '[name="kb_main_page_layout"]:checked' ).trigger( 'click' );
		}
	}

	// Initialize Layout box settings
	// Adapted from admin-plugin-pages.js - has similar functionality
	$( '[data-settings-group="ml-row"].epkb-row-module-setting select' ).each( function() {
		switch_module_boxes( this );
	} );

	// Helper function to update the custom dropdown's display
	function update_custom_dropdown_display($select) {
		const $inputGroup = $select.closest('.epkb-input-custom-dropdown');
		if (!$inputGroup.length) return;

		const $optionsList = $inputGroup.find('.epkb-input-custom-dropdown__options-list');
		const newValue = $select.val();
		const newText = $select.find('option:selected').text();

		$inputGroup.find('.epkb-input-custom-dropdown__input span').text(newText);
		$optionsList.find('.epkb-input-custom-dropdown__option').removeClass('epkb-input-custom-dropdown__option--selected');
		$optionsList.find('.epkb-input-custom-dropdown__option[data-value="' + newValue + '"]').addClass('epkb-input-custom-dropdown__option--selected');
	}


	/*************************************************************************************************
	 *
	 *          FE Settings - Preview Changes / Save Changes
	 *
	 ************************************************************************************************/

	let ignore_setting_update_flag = false;

	// Update page with a new preview - call backend
	function updatePreview( event, ui ) {

		if ( ignore_setting_update_flag ) {
			return;
		}

		const $feature_settings_container = $( event.target ).closest( '.epkb-fe__feature-settings' );
		const feature_name = $feature_settings_container.data( 'feature' );
		const kb_page_type = $feature_settings_container.data( 'kb-page-type' );

		// Row number assigned to the feature settings - it does not mean the feature is assigned to the row, but the row number each feature's settings must have event if the feature is disabled (to host row width settings and row module selector setting)
		const row_width_setting = $feature_settings_container.find( '.epkb-fe__settings-list [id^="ml_row_"][id$="_desktop_width"]' );
		const settings_row_number = row_width_setting.length > 0 ? row_width_setting.attr( 'id' ).replaceAll( 'ml_row_', '' ).replaceAll( '_desktop_width', '' ) : 'none';

		if ( $( event.target ).hasClass( 'wp-color-picker' ) && ui && ui.color ) {
			$( event.target ).closest( '.wp-picker-container' ).find( '.wp-color-result' ).css( 'background-color', ui.color.toString() );
			$( event.target ).val( ui.color.toString() );
		}

		let kb_config = collectConfig();

		kb_config[feature_name + '_module_position'] = settings_row_number;

		epkb_loading_Dialog( 'show', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );

		// Apply changes without saving (for preview purpose)
		$.ajax( {
			url: epkb_vars.ajaxurl,
			method: 'POST',
			data: {
				action: 'eckb_apply_fe_settings',
				_wpnonce_epkb_ajax_action: epkb_vars.nonce,
				kb_id: frontendEditor.data( 'kbid' ),
				new_kb_config: kb_config,
				kb_page_type: kb_page_type,
				feature_name: feature_name,
				setting_name: $(event.target).attr('name'),
				prev_link_css_id: $( '[id^="epkb-mp-frontend-modular-"][id$="-layout-css"]' ).attr( 'id' ),
				settings_row_number: settings_row_number ? settings_row_number : 'none'
			},
			success: function( response ) {

				// Handle generic KB error (caught by KB)
				if ( ! response.success ) {
					try {
						let responseJson = JSON.parse( response );
						if ( responseJson.message ) {
							$( 'body' ).append( $( responseJson.message ) );
						}
					} catch ( e ) {}
					return;
				}

				// Main Page: Handle successful AJAX response

				// Ensure we do not trigger extra updates during control re-initialization
				ignore_setting_update_flag = true;

				if ( kb_page_type === 'main-page' ) {

					update_main_page_css( response );

					// Update layout module settings if required (on layout change)
					if ( response.data.layout_settings_html && response.data.layout_settings_html.length > 0 ) {

						// Create temporary container for optional settings to initialize (required by inherited logic from Settings UI)
						let $temporary_layout_change_settings = $( '<div id="epkb-fe__layout-change-settings" style="display: none !important;">' + response.data.layout_settings_html_temp + '</div>' );
						$( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"]' ).prepend( $temporary_layout_change_settings );

						// Update module settings HTML
						$( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list' ).html( response.data.layout_settings_html );

						// Show Settings boxes which belong to the currently selected module
						switch_module_boxes( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list [data-settings-group="ml-row"].epkb-row-module-setting select' );

						// Re-apply current settings for the dropdown controls of the module
						$( '#epkb-fe__editor .epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list .epkb-input-custom-dropdown select' ).trigger( 'change' );

						// Re-init radio-buttons
						$( '#epkb-fe__editor .epkb-fe__feature-settings .epkb-fe__settings-list input[type="radio"][checked]' ).prop( 'checked', true );

						prepare_color_picker( feature_name );

						// Re-apply current settings for the buttons, radio buttons, and other controls of the module
						$( '#epkb-fe__editor .epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list .eckb-conditional-setting-input' ).trigger( 'click' );
					}

					// Update FAQs module settings (after applying design preset)
					if ( response.data.faqs_design_settings ) {

						// Unselect preset name to prevent continuing preset applying on further settings changes
						$feature_settings_container.find( '[name="faq_preset_name"]' ).prop( 'checked', false );

						// Apply preset settings for UI controls
						for ( const [ key, value ] of Object.entries( response.data.faqs_design_settings ) ) {
							const $target_field = $feature_settings_container.find( '[name="' + key + '"]' );
							apply_preset_setting( $feature_settings_container, $target_field, key, value );
						}

						// Re-apply current settings for the buttons, radio buttons, and other controls of the module
						$( '#epkb-fe__editor .epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list .eckb-conditional-setting-input' ).trigger( 'click' );
					}

					// Update Categories & Articles module settings (after applying design preset)
					if ( response.data.categories_articles_design_settings ) {

						// Unselect preset name to prevent continuing preset applying on further settings changes
						$feature_settings_container.find( '[name="categories_articles_preset"]' ).val( 'current' ).trigger( 'change' );

						// Apply preset settings for UI controls
						for ( const [ key, value ] of Object.entries( response.data.categories_articles_design_settings ) ) {
							const $target_field = frontendEditor.find( '[name="' + key + '"]' );
							apply_preset_setting( frontendEditor, $target_field, key, value );
						}

						// Re-apply current settings for the buttons, radio buttons, and other controls of the module
						$( '#epkb-fe__editor .epkb-fe__settings-list .eckb-conditional-setting-input' ).trigger( 'click' );
					}

					// Inline styles - changed every time a module setting was changed
					if ( response.data.inline_styles ) {
						$( '[id^="epkb-mp-frontend-modular-"][id$="-layout-inline-css"]' ).html( response.data.inline_styles );
					}

					// Update HTML of the target module - changed every time a module setting was changed
					if ( response.data.preview_html ) {
						const row_position = $feature_settings_container.attr( 'data-row-number' );

						// If destination row is missing, then create a new one in corresponding sequence with other rows
						let $destination_row = $( '#epkb-ml__row-' + settings_row_number );
						if ( $destination_row.length === 0 ) {
							$destination_row = $( '<div id="epkb-ml__row-' + settings_row_number + '" class="epkb-ml__row" data-position="' + row_position + '" data-feature="' + feature_name + '"></div>' );

							// Try to find a row to which append the newly creating row
							let $anchor_row = $( '.epkb-ml__row[data-position="0"]' );
							for ( let i = row_position - 1; i > 0; i-- ) {
								$anchor_row = $( '.epkb-ml__row[data-position="' + i + '"]' );
								if ( $anchor_row.length > 0 ) {
									$destination_row.insertAfter( $anchor_row );
									break;
								}
							}

							// If no anchor row found, then insert the newly creating row as the first element of KB content container
							if ( $anchor_row.length === 0 ) {
								$( '#epkb-modular-main-page-container' ).prepend( $destination_row );
							}
						}

						// Update preview HTML - if row was not taken by any module, then its container may be not present in the page HTML yet (insert it in correct order)
						$destination_row.html( response.data.preview_html );
					}

					// Ensure public JS which is dependent on HTML initialization is re-initialized
					setTimeout( function() { 
						$( window ).trigger( 'resize' );
					}, 100 );
				}

				// Article Page
				if ( kb_page_type === 'article-page' ) {

					// TODO: update HTML

					// TODO: Inline styles - changed every time a module setting was changed
					/*if ( response.data.inline_styles ) {
						$( '[id="epkb-ap-frontend-layout-inline-css"]' ).html( response.data.inline_styles );
					}*/
				}

				// Archive Page
				if ( kb_page_type === 'archive-page' ) {

					// TODO: Update HTML of the entire Archive content
					/*if ( response.data.preview_html ) {
						$( '#eckb-archive-page-container' ).replaceWith( response.data.preview_html );
					}*/

					// TODO: Inline styles - changed every time a module setting was changed
					/*if ( response.data.inline_styles ) {
						$( '[id="epkb-cp-frontend-layout-inline-css"]' ).html( response.data.inline_styles );
					}*/
				}

				// Allow to handle user changes for settings
				ignore_setting_update_flag = false;

			},
			complete: function() {
				epkb_loading_Dialog( 'remove', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );
			},
			error: function() {
				// Handle AJAX request errors (e.g., network issues)
				show_report_error_form( epkb_vars.fe_update_preview_error );
				epkb_loading_Dialog( 'remove', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );
			},
		} );
	}

	function apply_preset_setting( $feature_settings_container, $target_setting_field, key, value ) {

		if ( $target_setting_field.length === 0 ) {
			return;
		}

		// Radio buttons
		if ( $target_setting_field.attr( 'type' ) === 'radio' ) {
			$feature_settings_container.find( '[name="' + key + '"][value="' + value + '"]' ).prop( 'checked', true );

		// Color-picker
		} else if ( $target_setting_field.hasClass( 'wp-color-picker' ) ) {
			$target_setting_field.closest( '.wp-picker-container' ).find( '.wp-color-result' ).css( 'background-color', value );
			$target_setting_field.val( value );

		// Other field types
		} else {
			$target_setting_field.val( value );
		}
	}

	// Update page with reload while keep unsaved changes in FE settings
	function update_preview_via_page_reload( event, ui ) {

		if ( ignore_setting_update_flag ) {
			return;
		}

		epkb_loading_Dialog( 'show', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );

		if ( $( event.target ).hasClass( 'wp-color-picker' ) && ui && ui.color ) {
			$( event.target ).closest( '.wp-picker-container' ).find( '.wp-color-result' ).css( 'background-color', ui.color.toString() );
			$( event.target ).val( ui.color.toString() );
		}

		const kb_config = collectConfig();
		const config_json = JSON.stringify( kb_config );
		const feature_name = $( '#epkb-fe__editor .epkb-fe__feature-settings--active' ).attr( 'data-feature' );

		// Set parameter to re-open currently active feature in the editor
		const action_url = new URL( window.location.href );
		action_url.searchParams.set( 'epkb_fe_reopen_feature', feature_name );

		let $preview_form = $( '<form method="post" action="' + action_url + '" style="display: none !important;">' +
			'<input type="hidden" name="epkb_fe_reload_mode" value="on">' +
			'<input type="hidden" name="kb_id" value="' + frontendEditor.data( 'kbid' ) + '">' +
			'<input type="text" name="new_kb_config" value="">' +
			'</form>' );

		$preview_form.find( '[name="new_kb_config"]' ).val( config_json );

		$( 'body' ).append( $preview_form );

		$preview_form.trigger( 'submit' );
	}

	function update_main_page_css( response ) {
		// Main CSS file - changed only on switching layout
		if ( response.data.link_css && response.data.link_css.length > 0 ) {
			let new_link_css_id = $( response.data.link_css ).attr( 'id' );
			let $current_link_css = $( '[id^="epkb-mp-frontend-modular-"][id$="-layout-css"]' );

			// Load the file only once
			if ( new_link_css_id !== $current_link_css.attr( 'id' ) ) {
				$current_link_css.replaceWith( response.data.link_css );
			}
		}

		// RTL Main CSS file - changed only on switching layout
		if ( response.data.link_css_rtl && response.data.link_css_rtl.length > 0 ) {
			let new_link_css_rtl_id = $( response.data.link_css_rtl ).attr( 'id' );
			let $current_link_css_rtl = $( '[id^="epkb-mp-frontend-modular-"][id$="-layout-rtl-css"]' );

			// Load the file only once
			if ( new_link_css_rtl_id !== $current_link_css_rtl.attr( 'id' ) ) {
				$current_link_css_rtl.replaceWith( response.data.link_css_rtl );
			}
		}

		// EL.AY Main CSS file - changed only on switching layout
		if ( response.data.elay_link_css && response.data.elay_link_css.length > 0 ) {
			let new_elay_link_css_id = $( response.data.elay_link_css ).attr( 'id' );
			let $current_elay_link_css = $( '[id^="elay-mp-frontend-modular-"][id$="-layout-css"]' );

			// EL.AY layout-specific CSS file is not present if the layout was not active during the page load
			if ( $current_elay_link_css.length > 0 ) {

				// Load the file only once
				if ( new_elay_link_css_id !== $current_elay_link_css.attr( 'id' ) ) {
					$current_elay_link_css.replaceWith( response.data.elay_link_css );
				}
			} else {
				$current_elay_link_css.insertAfter( '#elay-public-modular-styles-css' );
			}

			if ( $current_elay_link_css.length ) {
				$current_elay_link_css.replaceWith( response.data.elay_link_css );
			} else {
				let $current_link_css = $( '[id^="epkb-mp-frontend-modular-"][id$="-layout-css"]' );
				$( response.data.elay_link_css ).insertAfter( $current_link_css );
			}
		}

		// RTL Main CSS file
		if ( response.data.elay_link_css_rtl && response.data.elay_link_css_rtl.length > 0 ) {
			let new_elay_link_css_rtl_id = $( response.data.elay_link_css ).attr( 'id' );
			let $current_elay_link_css_rtl = $( '[id^="elay-mp-frontend-modular-"][id$="-layout-rtl-css"]' );

			// EL.AY layout-specific CSS file is not present if the layout was not active during the page load
			if ( $current_elay_link_css_rtl.length > 0 ) {

				// Load the file only once
				if ( new_elay_link_css_rtl_id !== $current_elay_link_css_rtl.attr( 'id' ) ) {
					$current_elay_link_css_rtl.replaceWith( response.data.elay_link_css_rtl );
				}
			} else {
				$current_elay_link_css_rtl.insertAfter( '#elay-public-modular-styles-rtl-css' );
			}

			if ( $current_elay_link_css_rtl.length ) {
				$current_elay_link_css_rtl.replaceWith( response.data.elay_link_css_rtl );
			} else {
				let $current_link_css = $( '[id^="epkb-mp-frontend-modular-"][id$="-layout-rtl-css"]' );
				$( response.data.elay_link_css_rtl ).insertAfter( $current_link_css );
			}
		}
	}

	// Preview Update: on single setting change except colors
	$( document ).on( 'change', '#epkb-fe__editor input, #epkb-fe__editor select', function( event ) {

		// do not update preview if we are updating settings based on previous user selection
		if ( ignore_setting_update_flag ) {
			return;
		}

		let $field = $( this );

		// some settings do not need to trigger AJAX update for preview
		const noPreviewUpdateSettings = [ 'search_result_mode', 'search_box_results_style', 'article_search_box_results_style', 'article_search_result_mode', 'advanced_search_mp_show_top_category',
											'advanced_search_ap_show_top_category', 'advanced_search_mp_results_list_size', 'advanced_search_ap_results_list_size', 'advanced_search_text_highlight_enabled',
										'advanced_search_mp_results_page_size', 'advanced_search_ap_results_page_size', 'advanced_search_mp_box_results_style', 'advanced_search_ap_box_results_style'];
		if ( noPreviewUpdateSettings.includes( $field[0].name ) ) {
			return;
		}

		// For radio buttons, only proceed if the changed element is the selected one.
		if ( $field.attr( 'type' ) === 'radio' && ! $field.is( ':checked' ) ) {
			return;
		}

		// Color-picker handles its update through 'iris' library
		if ( $field.hasClass( 'wp-color-picker' ) ) {
			return;
		}

		// Module position handles its change itself
		if ( $field.closest( '.epkb-row-module-position' ).length ) {
			return;
		}

		// Module selector is excluded from the Editor UI
		if ( $field.closest( '.epkb-fe__settings-section--module-selection' ).length > 0 ) {
			return;
		}

		// Unselected module does not need to trigger AJAX update for preview
		if ( $field.closest( '.epkb-fe__feature-settings' ).attr( 'data-row-number' ) === 'none' ) {
			return;
		}

		// For some settings need to reload the entire page
		if ( $field.attr( 'name' ) === 'templates_for_kb' ) {
			update_preview_via_page_reload( event );
			return;
		}

		updatePreview( event );
	});

	function prepare_color_picker( feature_name ) {
		let isColorInputSync = false;
		$( '#epkb-fe__editor .epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list .epkb-admin__color-field input' ).wpColorPicker({
			change: function( colorEvent, ui) {

				// Do nothing for programmatically changed value (for sync purpose)
				if ( isColorInputSync ) {
					return;
				}

				isColorInputSync = true;

				// Get current color value
				let color_value = $( colorEvent.target ).wpColorPicker( 'color' );
				let setting_name = $( colorEvent.target ).attr( 'name' );

				// Sync other color pickers that have the same name
				$( '.epkb-admin__color-field input[name="' + setting_name + '"]' ).not( colorEvent.target ).each( function () {
					$( this ).wpColorPicker( 'color', color_value );
				} );

				isColorInputSync = false;
			},
		});

		// Ensure the WordPress color-picker is ready before 'iris' library options are available
		setTimeout( function() {
			$( '#epkb-fe__editor .epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-list input.wp-color-picker' ).iris( 'option', 'change', colorpicker_update );
		}, 100 );
	}

	// Preview Update: on color change
	setTimeout( function() {
		$( '#epkb-fe__editor input.wp-color-picker' ).iris( 'option', 'change', colorpicker_update );
	}, 100 );

	// Before send AJAX request for the preview update, the color-picker should wait until the user stopped to change the color
	let colorpicker_update_timeout = false;
	function colorpicker_update( event, ui ) {

		// Remove previous timeout handler
		if ( colorpicker_update_timeout ) {
			clearTimeout( colorpicker_update_timeout );
		}

		// Set current timeout handler
		colorpicker_update_timeout = setTimeout( function () {
			updatePreview( event, ui );
			colorpicker_update_timeout
		}, 200 );
	}

	// Save settings
	$( document ).on( 'click', '#epkb-fe__action-save', function( event ) {

		let kb_config = collectConfig();

		epkb_loading_Dialog( 'show', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );

		$.ajax( {
			url: epkb_vars.ajaxurl,
			method: 'POST',
			data: {
				action: 'eckb_save_fe_settings',
				_wpnonce_epkb_ajax_action: epkb_vars.nonce,
				kb_id: frontendEditor.data( 'kbid' ),
				new_kb_config: kb_config
			},
			success: function( response ) {

				if ( response.data && response.data.message ) {
					epkb_show_success_notification( response.data.message );
				}

				// Handle successful AJAX response.
				if ( response.success ) {

				} else {
					try {
						let responseJson = JSON.parse( response );
						if ( responseJson.message ) {
							$( 'body' ).append( $( responseJson.message ) );
						}
					} catch ( e ) {}
				}

				epkb_loading_Dialog( 'remove', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );
			},
			error: function() {
				// Handle AJAX request errors (e.g., network issues)
				show_report_error_form( epkb_vars.fe_save_settings_error );
				epkb_loading_Dialog( 'remove', '', $( '#epkb-modular-main-page-container, #eckb-kb-template, #eckb-archive-page-container' ) );
			}
		} );
	} );

	function collectConfig() {
		// collect settings
		let kb_config = {};

		frontendEditor.find( 'input, select' ).each( function(){

			// ignore inputs with empty name and pro feature fields (an ad field)
			if ( ! $( this ).attr( 'name' ) || ! $( this ).attr( 'name' ).length
				|| $( this ).closest( '.epkb-input-group' ).find( '.epkb__option-pro-tag' ).length
				|| $( this ).closest( '.epkb-input-group' ).find( '.epkb__option-pro-tag-container' ).length ) {
				return true;
			}

			if ( $( this ).attr( 'type' ) === 'checkbox' ) {

				// checkboxes multiselect
				if ( $( this ).closest( '.epkb-admin__checkboxes-multiselect' ).length ) {
					if ( $( this ).prop( 'checked' ) ) {
						if ( ! kb_config[ $(this).attr( 'name' ) ] ) {
							kb_config[ $( this ).attr( 'name' ) ] = [];
						}
						kb_config[ $( this ).attr( 'name' ) ].push( $( this ).val() );
					}

					// single checkbox
				} else {
					kb_config[ $( this ).attr( 'name' ) ] = $( this ).prop( 'checked' ) ? 'on' : 'off';
				}
				return true;
			}

			if ( $( this ).attr('type') === 'radio' ) {
				if ( $( this ).prop( 'checked' ) ) {
					kb_config[ $( this ).attr( 'name' ) ] = $( this ).val();
				}
				return true;
			}

			if ( typeof $( this ).attr( 'name' ) == 'undefined' ) {
				return true;
			}
			kb_config[ $( this ).attr( 'name' ) ] = $( this ).val();
		});

		// Ensure 'faq_group_ids' is set even if no FAQ Groups are selected
		if ( $( '[name="faq_group_ids"]' ).length && typeof kb_config.faq_group_ids == 'undefined' ) {
			kb_config.faq_group_ids = 0;
		}

		return kb_config;
	}
	

	/*************************************************************************************************
	 *
	 *          Module Position Change
	 *
	 ************************************************************************************************/

/* ------------------------------------------------------------------
   Modular-page position helper (v2 – toggle + radio buttons)
   – Keeps every module’s toggle / “Move Up” / “Move Down” controls
     in sync with the real order of .epkb-ml__row elements.
   – Runs once on DOM-ready and after every control interaction.
   ------------------------------------------------------------------ */

   const MAX_POS = 5;                                              // hard limit
   const $container = $('#epkb-modular-main-page-container');
   
   /* ─── utilities ──────────────────────────────────────────────── */
   const rows            = ()       => $('.epkb-ml__row');         // live collection
   const rowByFeature    = slug     => $(`.epkb-ml__row[data-feature="${slug}"]`);
   const enabledCount    = ()       => rows().length;
   
   /* (re)build the on-page rows list according to their data-position      */
   function sortRows() {
	 rows()
	   .sort((a, b) => +$(a).attr('data-position') - +$(b).attr('data-position'))
	   .appendTo($container);
   }
   
   /* renumber 1…N after any removal or swap (keeps gaps out)               */
   function renumberRows() {
	 rows().each((idx, el) => {
	   const pos = idx + 1;
	   $(el)
		 .attr('data-position', pos)
		 .attr('id', `epkb-ml__row-${pos}`);                       // keep id unique
	 });
   }
   
   /* create a minimal placeholder row when a module is (re)enabled         */
   function addRow(toggle, slug) {
	 if (enabledCount() >= MAX_POS || rowByFeature(slug).length) return;
   
	 // pick first free slot
	 let pos = 1;
	 while (rowByFeature(slug).length === 0 &&
			$(`.epkb-ml__row[data-position="${pos}"]`).length) pos++;
   
	 $('<div>', {
		 id:            `epkb-ml__row-${pos}`,
		 class:         'epkb-ml__row',
		 'data-position': pos,
		 'data-feature':  slug
	 }).appendTo($container);
   
	 $settingsSection = $(toggle).closest('.epkb-fe__settings-section-body');
	 $settingsSection.target = $(toggle).closest('.epkb-fe__settings-section-body');
	 $settingsSection.attr('name', slug);
	 updatePreview( $settingsSection );   // ← existing helper in your codebase
   }
   
   /* remove a row when a module is disabled                                */
   function removeRow(slug) {
	 rowByFeature(slug).remove();
	 renumberRows();
	 sortRows();
   }
   
   /* refresh **all** sidebar controls to reflect the current state         */
   function refreshControls() {
	const total = enabledCount();
  
	$('.epkb-settings-control__input__toggle').each(function () {
	  const $toggle = $(this);
	  const slug    = $toggle.attr('name');
	  const $wrap   = $toggle.closest('.epkb-fe__settings-section-body');
	  const $radios = $wrap.find('.epkb-radio-buttons-container');
	  const $up     = $radios.find('input[value="move-up"]');
	  const $down   = $radios.find('input[value="move-down"]');
	  const $row    = rowByFeature(slug);
	  const enabled = $row.length > 0;
  
	  /* toggle state & label */
	  $toggle.prop('checked', enabled);
	  /* $wrap.find('.epkb-settings-control__input__label')
		   .attr('data-off', 'Disabled'); */
  
	  /* show/hide radio buttons */
	  if (!enabled || total <= 1) {
		$radios.hide();
	  } else {
		$radios.show();
		const pos = +$row.attr('data-position');
		$up.prop('disabled',   pos === 1);
		$down.prop('disabled', pos === total);
	  }
  
	  /* clear the momentary radio selection */
	  $radios.find('input[type="radio"]').prop('checked', false);
	});
  }
   
   /* ─── event handlers ────────────────────────────────────────────────── */
   // 1. toggle enable / disable ------------------------------------------
   $(document).on('change', '.epkb-settings-control__input__toggle', function () {
	 const slug = this.name;
   
	 if (this.checked) {
	   addRow(this, slug);
	 } else {
	   removeRow(slug);
	 }
	 refreshControls();
   });
   
   // 2. move-up / move-down ----------------------------------------------
   $(document).on('change', '.epkb-input[value="move-up"], .epkb-input[value="move-down"]', function () {
	 const $btn      = $(this);
	 const dir       = $btn.val() === 'move-up' ? -1 : +1;
	 const slug      = $btn.closest('.epkb-row-module-position').data('module');
	 const $row      = rowByFeature(slug);
	 const oldPos    = +$row.attr('data-position');
	 const newPos    = oldPos + dir;
	 const $otherRow = rows().filter((_, el) => +$(el).attr('data-position') === newPos);
   
	 if (!$row.length || !$otherRow.length) return;    // already at edge
   
	 // swap the two rows’ data-position values
	 $row.attr('data-position', newPos);
	 $otherRow.attr('data-position', oldPos);
   
	 sortRows();
	 refreshControls();
   });
   
   /* ─── initial run ───────────────────────────────────────────────────── */
   renumberRows();        // make sure positions start at 1‥N, no gaps
   sortRows();
   refreshControls();
   


	/*************************************************************************************************
	 *
	 *          Various
	 *
	 ************************************************************************************************/

	//Collect Page Parameters
	const pageConfigs = [
		{
			checkSelector: '#epkb-modular-main-page-container',
			prefix: 'mp',
			containerSelector: '#epkb-ml__module-categories-articles',
			searchSelector: '#epkb-ml__row-1',
		},
		{
			checkSelector: '#eckb-article-page-container-v2',
			prefix: 'ap',
			containerSelector: '#eckb-article-body',
			searchSelector: '#eckb-article-header',
		},
		{
			checkSelector: '#eckb-archive-body',
			prefix: 'cp',
			containerSelector: '#eckb-archive-body',
			searchSelector: '#eckb-archive-header',
		},
	];

	function epkb_get_page_params() {
		const windowWidth = $( 'body' ).width();
		let prefix = '';
		let containerWidth = 0;
		let searchWidth = 0;

		for ( const config of pageConfigs ) {
			if ( $( config.checkSelector ).length ) {
				prefix = config.prefix;
				containerWidth = Math.round( $( config.containerSelector ).width() );
				searchWidth = Math.round( $( config.searchSelector ).width() );
				break;
			}
		}

		if ( prefix ) {
			if ( searchWidth ) {
				$( `.js-epkb-${prefix}-search-width` ).text(`${searchWidth}px` );
		}

		if ( containerWidth ) {
				$( `.js-epkb-${prefix}-width-container`).text(`${containerWidth}px` );
			}

			if ( windowWidth ) {
				$( `.js-epkb-${prefix}-width` ).text(`${windowWidth}px` );
			}
		}
	}

	//Initialize Visual Helper Collect Page Parameters
	epkb_get_page_params();

	//Resize Visual Helper Collect Page Parameters
	$(window).on('resize', function() {
		epkb_get_page_params();
	})

	function epkb_loading_Dialog( displayType, message, parent_container ){

		if ( displayType === 'show' ) {

			let output =
				'<div class="epkb-admin-dialog-box-loading">' +

				//<-- Header -->
				'<div class="epkb-admin-dbl__header">' +
				'<div class="epkb-admin-dbl-icon epkbfa epkbfa-hourglass-half"></div>'+
				(message ? '<div class="epkb-admin-text">' + message + '</div>' : '' ) +
				'</div>'+

				'</div>';

			//Add message output at the end of Body Tag
			parent_container.append( output );

		} else if( displayType === 'remove' ) {

			// Remove loading dialogs.
			parent_container.find( '.epkb-admin-dialog-box-loading' ).remove();
		}
	}

	// Close Button Message if Close Icon clicked
	$( document ).on( 'click', '.epkb-close-notice', function() {
		let bottom_message = $( this ).closest( '.eckb-bottom-notice-message' );
		bottom_message.addClass( 'fadeOutDown' );
		setTimeout( function() {
			bottom_message.html( '' );
		}, 10000 );
	} );

	// SHOW INFO MESSAGES
	function epkb_admin_notification( $title, $message , $type ) {
		return '<div class="eckb-bottom-notice-message">' +
			'<div class="contents">' +
			'<span class="' + $type + '">' +
			($title ? '<h4>' + $title + '</h4>' : '' ) +
			($message ? '<p>' + $message + '</p>': '') +
			'</span>' +
			'</div>' +
			'<div class="epkb-close-notice epkbfa epkbfa-window-close"></div>' +
			'</div>';
	}

	function epkb_show_success_notification( $message, $title = '' ) {
		$('.eckb-bottom-notice-message').remove();
		$('body').append( epkb_admin_notification( $title, $message, 'success' ) );
	}

	// Re-open edit if the page was reloaded programmatically on setting change
	( function() {
		const current_url = new URL( window.location.href );
		const feature_name = current_url.searchParams.get( 'epkb_fe_reopen_feature' )
		if ( feature_name && feature_name.length > 0 ) {

			// Re-open editor feature
			$( '.epkb-fe__toggle' ).trigger( 'click' );
			$( '#epkb-fe__editor .epkb-fe__feature-select-button[data-feature="' + feature_name + '"]' ).trigger( 'click' );

			// Remove temporary parameter to avoid re-opening editor on manual page reloading
			current_url.searchParams.delete( 'epkb_fe_reopen_feature' );

			// Clear history to avoid resending the form on manual page relaoding
			history.replaceState( null, '', current_url.pathname + current_url.search + current_url.hash );
		}
	} )();

	// Link to open certain setting of certain feature
	$( document ).on( 'click', '#epkb-fe__editor .epkb-fe__open-feature-setting-link', function ( event ) {

		// Disable the default <a> tag behavior
		event.preventDefault();

		const feature_name = $( this ).attr( 'data-feature' );
		const setting_name = $( this ).attr( 'data-setting' );
		const settings_section = $( this ).attr( 'data-section' );

		// Remove any previous highlighting
		frontendEditor.find( '.epkb-highlighted_setting' ).removeClass( 'epkb-highlighted_setting' );

		// Open the target feature settings
		frontendEditor.find( '.epkb-fe__feature-select-button[data-feature="' + feature_name + '"]' ).trigger( 'click' );

		let setting_offset = 0;

		let $target_container = false;

		// CASE: Link to single setting
		if ( setting_name ) {
			$target_container = frontendEditor.find( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"] [name="' + setting_name + '"]' ).closest( '.epkb-input-group' );
		}

		// CASE: Link to settings section
		if ( settings_section ) {
			$target_container = frontendEditor.find( '.epkb-fe__feature-settings[data-feature="' + feature_name + '"] .epkb-fe__settings-section--' + settings_section );
		}

		if ( $target_container.length > 0 ) {
			setting_offset = $target_container.offset().top - frontendEditor.offset().top - 100;
			setting_offset = setting_offset > 0 ? setting_offset : 0;

			// Highlight the target setting
			$target_container.addClass( 'epkb-highlighted_setting' );
		}

		// Scroll to the target setting
		frontendEditor.animate( {
			scrollTop: setting_offset
		}, 300 );

		// Disable the default <a> tag behavior
		return false;
	} );

	/**
	 * Report the Report Error Form
	 */
	// Close Error Submit Form if Close Icon or Close Button clicked
	$( admin_report_error_form ).on( 'click', '.epkb-close-notice, .epkb-admin__error-form__btn-cancel', function(){
		$( admin_report_error_form ).css( 'display', 'none' ).parent().css( 'display', 'none' );
	});

	// Submit the Report Error Form
	$( admin_report_error_form ).find( '#epkb-admin__error-form' ).on( 'submit', function ( event ) {
		event.preventDefault();

		let $form = $(this);

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: epkb_vars.ajaxurl,
			data: $form.serialize(),
			beforeSend: function (xhr) {
				// block the form and add loader
				$form.find( '.epkb-admin__error-form__btn-wrap, input, label, textarea' ).slideUp( 'fast' );
				$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).addClass( 'epkb-admin__error-form__response--active' );
				$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).html( epkb_vars.fe_sending_error_report );
			}
		}).done(function (response) {
			// success message
			if ( typeof response.success !== 'undefined' && response.success === false ) {
				$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).html( response.data );
			} else if ( typeof response.success !== 'undefined' && response.success === true ) {
				$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).html( response.data );
			} else {
				// something went wrong
				$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).html( epkb_vars.fe_send_report_error );
			}
		}).fail(function (response, textStatus, error) {
			// something went wrong
			$( admin_report_error_form ).find( '.epkb-admin__error-form__response' ).html( epkb_vars.fe_send_report_error );
		}).always(function () {
			// remove form loader
			$( admin_report_error_form ).find( 'input, textarea' ).prop( 'disabled', false );
		});
	});

	function show_report_error_form( error_message ) {
		let error_message_text = error_message ? error_message : '';
		$( admin_report_error_form ).find( '.epkb-admin__error-form__title' ).text( epkb_vars.fe_report_error_title );
		$( admin_report_error_form ).find( '.epkb-admin__error-form__desc' ).text( epkb_vars.fe_report_error_desc );
		$( admin_report_error_form ).find( '#epkb-admin__error-form__message' ).val( error_message_text );
		$( admin_report_error_form ).css( 'display', 'block' ).parent().css( 'display', 'block' );
	}
});