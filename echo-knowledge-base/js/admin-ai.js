/* global jQuery */
(function ($) {
	$(function () {
		
		// Priority handlers for chat conversations table - must run before generic handlers
		function setupChatTableDeleteHandlers() {
			// Delete selected chat conversations - only for chat table
			$(document).on('click', '#delete-selected', function(e) {
				let $button = $(this);
				let $container = $button.closest('.epkb-submissions-table-container');
				let $table = $container.find('table');
				
				// Only handle if this is the chat conversations table
				if ($table.attr('id') !== 'epkb-chat-conversations-table') {
					return;
				}
				
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation(); // Prevent other handlers
				
				let selectedIds = [];
				
				$table.find('.select-row:checked').each(function() {
					let conversationId = $(this).closest('tr').data('id');
					if (conversationId) {
						selectedIds.push(conversationId);
					}
				});
				
				if (selectedIds.length === 0) {
					epkb_show_error_notification(epkb_vars.no_conversations_selected || 'No conversations selected.');
					return;
				}
				
				if (!confirm((epkb_vars.confirm_delete_selected || 'Are you sure you want to delete {count} selected conversation(s)?').replace('{count}', selectedIds.length))) {
					return;
				}
				
				// Show loading
				epkb_loading_Dialog('show', epkb_vars.deleting_conversations || 'Deleting conversations...');
				
				// Make REST API call to delete selected conversations
				$.ajax({
					url: (epkb_vars.wpApiSettings ? epkb_vars.wpApiSettings.root : '/wp-json/') + 'epkb/v1/ai-chat/admin/conversations/bulk',
					type: 'DELETE',
					data: JSON.stringify({ ids: selectedIds }),
					contentType: 'application/json',
					beforeSend: function(xhr) {
						if (epkb_vars.wpApiSettings && epkb_vars.wpApiSettings.nonce) {
							xhr.setRequestHeader('X-WP-Nonce', epkb_vars.wpApiSettings.nonce);
						} else if (epkb_vars.nonce) {
							xhr.setRequestHeader('X-WP-Nonce', epkb_vars.nonce);
						}
					},
					success: function(response) {
						epkb_loading_Dialog('remove');
						
						if (response && response.deleted) {
							epkb_show_success_notification(response.message || (epkb_vars.conversations_deleted || '{count} conversation(s) deleted successfully.').replace('{count}', response.deleted));
							
							// Remove deleted rows from table
							selectedIds.forEach(function(id) {
								$table.find('tr[data-id="' + id + '"]').fadeOut(400, function() {
									$(this).remove();
									
									// Update checkbox states
									$container.find('#delete-selected').prop('disabled', true);
									$('#select-all-epkb-chat-conversations-table').prop('checked', false);
									
									// Clear conversation details if deleted conversation was selected
									let $detailsContent = $('.epkb-ai-conversation-messages');
									if ($detailsContent.find('[data-conversation-id="' + id + '"]').length > 0) {
										$detailsContent.empty();
										$('.epkb-ai-no-selection').show();
									}
								});
							});
						} else {
							epkb_show_error_notification(epkb_vars.failed_delete_conversations || 'Failed to delete conversations.');
						}
					},
					error: function(xhr, status, error) {
						epkb_loading_Dialog('remove');
						console.error('Failed to delete conversations:', error);
						epkb_show_error_notification(epkb_vars.failed_delete_conversations || 'Failed to delete conversations.');
					}
				});
				
				return false; // Ensure no other handlers run
			});

			// Delete all chat conversations - only for chat table
			$(document).on('click', '#delete-all', function(e) {
				let $button = $(this);
				let $container = $button.closest('.epkb-submissions-table-container');
				let $table = $container.find('table');
				
				// Only handle if this is the chat conversations table
				if ($table.attr('id') !== 'epkb-chat-conversations-table') {
					return;
				}
				
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation(); // Prevent other handlers
				
				if (!confirm(epkb_vars.confirm_delete_all || 'Are you sure you want to delete ALL chat conversations? This action cannot be undone.')) {
					return;
				}
				
				// Show loading
				epkb_loading_Dialog('show', epkb_vars.deleting_all_conversations || 'Deleting all conversations...');
				
				// Make REST API call to delete all conversations
				$.ajax({
					url: (epkb_vars.wpApiSettings ? epkb_vars.wpApiSettings.root : '/wp-json/') + 'epkb/v1/ai-chat/admin/conversations',
					type: 'DELETE',
					beforeSend: function(xhr) {
						if (epkb_vars.wpApiSettings && epkb_vars.wpApiSettings.nonce) {
							xhr.setRequestHeader('X-WP-Nonce', epkb_vars.wpApiSettings.nonce);
						} else if (epkb_vars.nonce) {
							xhr.setRequestHeader('X-WP-Nonce', epkb_vars.nonce);
						}
					},
					success: function(response) {
						epkb_loading_Dialog('remove');
						
						if (response && response.deleted !== undefined) {
							epkb_show_success_notification(response.message || (epkb_vars.all_conversations_deleted || 'All conversations deleted successfully.'));
							
							// Clear the table
							$table.find('tbody').empty().append(
								'<tr class="epkb-no-results"><td colspan="100%">' + (epkb_vars.no_conversations_found || 'No conversations found.') + '</td></tr>'
							);
							
							// Clear conversation details
							$('.epkb-ai-conversation-messages').empty();
							$('.epkb-ai-no-selection').show();
							
							// Disable buttons
							$container.find('#delete-selected, #delete-all').prop('disabled', true);
							$('#select-all-epkb-chat-conversations-table').prop('checked', false);
						} else {
							epkb_show_error_notification(epkb_vars.failed_delete_all || 'Failed to delete all conversations.');
						}
					},
					error: function(xhr, status, error) {
						epkb_loading_Dialog('remove');
						console.error('Failed to delete all conversations:', error);
						epkb_show_error_notification(epkb_vars.failed_delete_all || 'Failed to delete all conversations.');
					}
				});
				
				return false; // Ensure no other handlers run
			});
		}
		
		setupChatTableDeleteHandlers();

		// vector store initialization.
		$(document).on(
			"click",
			".epkb-ai-page-container .epkb-ai-create-vector-store",
			function (e) {
				e.preventDefault();
				
				// Show loading state
				let $button = $(this);
				let $statusContainer = $(".epkb-ai-vector-store-status");
				let $actionContainer = $(".epkb-ai-vector-store-action");
				let originalButtonHtml = $button.html();
				
				// Disable button and show loading
				$button.prop('disabled', true).html('<span class="epkb-ai-spinner"></span> ' + epkb_vars.creating_text || 'Creating...');
				$statusContainer.html('<span class="epkb-ai-progress-indicator"><span class="epkb-ai-spinner"></span> ' + (epkb_vars.processing_text || 'Processing...') + '</span>');

				let postData = {
					action: "epkb_ai_create_vector_store",
					_wpnonce_epkb_ajax_action: epkb_vars.ai_create_vector_store_nonce || epkb_vars.nonce,
					epkb_kb_id: $('#epkb-list-of-kbs').val(),
				};

				epkb_send_ajax(postData, function (response) {
					if (!response.error && typeof response.message != "undefined") {
						epkb_show_success_notification(response.message);
						$statusContainer.html(response.status_html);
						$actionContainer.html('');
						$('.epkb-ai-vector-store-setup').attr('data-setup-status', 'active');
					} else {
						// Restore button on error
						$button.prop('disabled', false).html(originalButtonHtml);
						$statusContainer.html('<span class="epkb-ai-status--error">' + (epkb_vars.error_text || 'Error') + '</span>');
					}
				}, null, false, function() {
					// Always callback - restore button if still in loading state
					if ($button.prop('disabled')) {
						$button.prop('disabled', false).html(originalButtonHtml);
					}
				}, false, false);
			}
		);

		// recreate vector store.
		$(document).on(
			"click",
			".epkb-ai-page-container .epkb-ai-recreate-vector-store",
			function (e) {
				e.preventDefault();
				
				// Show loading state
				let $button = $(this);
				let $statusContainer = $(".epkb-ai-vector-store-status");
				let $actionContainer = $(".epkb-ai-vector-store-action");
				let originalButtonHtml = $button.html();
				
				// Disable button and show loading
				$button.prop('disabled', true).html('<span class="epkb-ai-spinner"></span> ' + epkb_vars.recreating_text || 'Recreating...');
				$statusContainer.html('<span class="epkb-ai-progress-indicator"><span class="epkb-ai-spinner"></span> ' + (epkb_vars.processing_text || 'Processing...') + '</span>');

				let postData = {
					action: "epkb_ai_recreate_vector_store",
					_wpnonce_epkb_ajax_action: epkb_vars.ai_recreate_vector_store_nonce || epkb_vars.nonce,
					epkb_kb_id: $('#epkb-list-of-kbs').val(),
				};

				epkb_send_ajax(postData, function (response) {
					if (!response.error && typeof response.message != "undefined") {
						epkb_show_success_notification(response.message);
						$statusContainer.html(response.status_html);
						$actionContainer.html('');
						$('.epkb-ai-vector-store-setup').attr('data-setup-status', 'active');
					} else {
						// Restore button on error
						$button.prop('disabled', false).html(originalButtonHtml);
						$statusContainer.html('<span class="epkb-ai-status--error">' + (epkb_vars.error_text || 'Error') + '</span>');
					}
				}, null, false, function() {
					// Always callback - restore button if still in loading state
					if ($button.prop('disabled')) {
						$button.prop('disabled', false).html(originalButtonHtml);
					}
				}, false, false);
			}
		);

		// Content - upload files
		$(document).on(
			"click",
			".epkb-ai-page-container .epkb-ai-upload-files",
			function (e) {
				e.preventDefault();
				
				// Show loading state
				let $button = $(this);
				let $statusContainer = $(".epkb-ai-files-status");
				let $actionContainer = $(".epkb-ai-files-action");
				let originalButtonHtml = $button.html();
				
				// Disable button and show loading
				$button.prop('disabled', true).html('<span class="epkb-ai-spinner"></span> ' + epkb_vars.uploading_text || 'Uploading...');
				$statusContainer.html('<span class="epkb-ai-progress-indicator"><span class="epkb-ai-spinner"></span> ' + (epkb_vars.uploading_files_text || 'Uploading files...') + '</span>');

				let postData = {
					action: "epkb_ai_upload_files",
					_wpnonce_epkb_ajax_action: epkb_vars.ai_upload_files_nonce || epkb_vars.nonce,
					epkb_kb_id: $('#epkb-list-of-kbs').val(),
				};

				epkb_send_ajax(postData, function (response) {
					if (!response.error && typeof response.message != "undefined") {
						epkb_show_success_notification(response.message);
						$statusContainer.html(response.status_html);
						$actionContainer.html(response["action_button"]);
						$('.epkb-ai-files-setup').attr('data-setup-status', 'active');
					} else {
						// Restore button on error
						$button.prop('disabled', false).html(originalButtonHtml);
						$statusContainer.html('<span class="epkb-ai-status--error">' + (epkb_vars.error_text || 'Error') + '</span>');
					}
				}, null, false, function() {
					// Always callback - restore button if still in loading state
					if ($button.prop('disabled')) {
						$button.prop('disabled', false).html(originalButtonHtml);
					}
				}, false);
			}
		);

		// Content - reupload files
		$(document).on(
			"click",
			".epkb-ai-page-container .epkb-ai-reupload-files",
			function (e) {
				e.preventDefault();
				
				// Show loading state
				let $button = $(this);
				let $statusContainer = $(".epkb-ai-files-status");
				let $actionContainer = $(".epkb-ai-files-action");
				let originalButtonHtml = $button.html();
				
				// Disable button and show loading
				$button.prop('disabled', true).html('<span class="epkb-ai-spinner"></span> ' + epkb_vars.reuploading_text || 'Re-uploading...');
				$statusContainer.html('<span class="epkb-ai-progress-indicator"><span class="epkb-ai-spinner"></span> ' + (epkb_vars.reuploading_files_text || 'Re-uploading files...') + '</span>');

				let postData = {
					action: "epkb_ai_reupload_files",
					_wpnonce_epkb_ajax_action: epkb_vars.ai_reupload_files_nonce || epkb_vars.nonce,
					epkb_kb_id: $('#epkb-list-of-kbs').val(),
				};

				epkb_send_ajax(postData, function (response) {
					if (!response.error && typeof response.message != "undefined") {
						epkb_show_success_notification(response.message);
						$statusContainer.html(response.status_html);
						// Keep the same button for re-upload
						$button.prop('disabled', false).html(originalButtonHtml);
					} else {
						// Restore button on error
						$button.prop('disabled', false).html(originalButtonHtml);
						$statusContainer.html('<span class="epkb-ai-status--error">' + (epkb_vars.error_text || 'Error') + '</span>');
					}
				}, null, false, function() {
					// Always callback - restore button if still in loading state
					if ($button.prop('disabled')) {
						$button.prop('disabled', false).html(originalButtonHtml);
					}
				}, false);
			}
		);

		// Save AI settings
		$(document).on(
			"click",
			".epkb-admin__list-actions-row .epkb_save_ai_settings",
			function (e) {
				e.preventDefault();

				let api_settings_box 		  = $(".epkb-ai__api-settings"),
					labels_settings_box 	  = $(".epkb-ai__labels-settings"),
					disclaimer_settings_box   = $(".epkb-ai__disclaimer-settings"),
					search_settings_box       = $(".epkb-ai__search-settings"),
					chat_settings_box         = $(".epkb-ai__chat-settings"),
					beta_settings_box         = $(".epkb-ai__beta-settings");

				let disclaimer_accepted = [];
				disclaimer_settings_box.find('[name="disclaimer_accepted"]:checked').each(function() {
					disclaimer_accepted.push($(this).val());
				})

				let postData = {
					action: "epkb_save_ai_settings",
					_wpnonce_epkb_ajax_action: epkb_vars.save_ai_settings_nonce || epkb_vars.nonce,
					epkb_kb_id: $('#epkb-list-of-kbs').val(),
					model: api_settings_box.find('[name="ai_api_model"]').val(),
					openai_key: api_settings_box.find('[name="openai_key"]').val(),
					assistant_setup_status: $('.epkb-ai-assistant-setup').data('setup-status'),
					disclaimer_accepted: disclaimer_accepted,
					ai_search_enabled: search_settings_box.find('[name="ai_search_enabled"]').is(':checked') ? 'on' : 'off',
					ai_chat_enabled: chat_settings_box.find('[name="ai_chat_enabled"]').is(':checked') ? 'on' : 'off',
					ai_beta_access_code: beta_settings_box.find('[name="ai_beta_access_code"]').val(),
				};

				// Show loading dialog with custom message
				epkb_loading_Dialog("show", epkb_vars.saving_settings || "Saving settings...");
				
				epkb_send_ajax(postData, function (response) {
					if (!response.error && typeof response.message != "undefined") {
						epkb_show_success_notification(response.message);
					}
				}, null, false, null, false);

				return false;
			}
		);

		// Handle conversation row click for split view
		$(document).on('click', '#epkb-search-conversations-table tbody tr:not(.epkb-row-info), #epkb-chat-conversations-table tbody tr:not(.epkb-row-info)', function(e) {
			// Prevent click on checkbox or delete button from triggering row selection
			if ($(e.target).is('input[type="checkbox"], button, a')) {
				return;
			}

			let $row = $(this);
			let conversationId = $row.data('id');
			
			// Determine mode based on table ID
			let tableId = $row.closest('table').attr('id');
			let mode = tableId === 'epkb-chat-conversations-table' ? 'chat' : 'search';
			
			// Check if conversation ID exists
			if (!conversationId) {
				console.error('No conversation ID found for this row');
				return;
			}

			// Add selected class
			$row.siblings().removeClass('selected');
			$row.addClass('selected');

			// Show loading state
			let $layout = $row.closest('.epkb-ai-discussions-layout');
			let $detailsContent = $layout.find('.epkb-ai-conversation-messages');
			let $noSelection = $layout.find('.epkb-ai-no-selection');
			
			$noSelection.hide();
			$detailsContent.html('<div class="epkb-ai-loading"><div class="epkb-ai-spinner"></div></div>').show();

			// Load conversation details using REST API
			let restUrl = (epkb_vars.wpApiSettings ? epkb_vars.wpApiSettings.root : '/wp-json/') + 'epkb/v1/ai-chat/admin/conversations/' + conversationId;
			
			$.ajax({
				url: restUrl,
				type: 'GET',
				beforeSend: function(xhr) {
					// Add nonce header for authentication
					if (epkb_vars.wpApiSettings && epkb_vars.wpApiSettings.nonce) {
						xhr.setRequestHeader('X-WP-Nonce', epkb_vars.wpApiSettings.nonce);
					} else if (epkb_vars.nonce) {
						xhr.setRequestHeader('X-WP-Nonce', epkb_vars.nonce);
					}
				},
				success: function(response) {
					if (response && response.messages) {
						// Format the conversation HTML
						let html = '<div class="epkb-ai-conversation-details">';
						html += '<div class="epkb-ai-conversation-header">';
						html += '<strong>' + (epkb_vars.user_label || 'User') + ':</strong> ' + response.user + '<br>';
						html += '<strong>' + (epkb_vars.created_label || 'Created') + ':</strong> ' + response.created;
						html += '</div>';
						html += '<div class="epkb-ai-messages">';
						
						response.messages.forEach(function(message) {
							let roleClass = message.role === 'user' ? 'epkb-ai-user-message' : 'epkb-ai-assistant-message';
							let roleLabel = message.role === 'user' ? (epkb_vars.user_label || 'User') : (epkb_vars.assistant_label || 'Assistant');
							html += '<div class="epkb-ai-message ' + roleClass + '">';
							html += '<div class="epkb-ai-message-role">' + roleLabel + '</div>';
							html += '<div class="epkb-ai-message-content">' + message.content + '</div>';
							if (message.timestamp) {
								html += '<div class="epkb-ai-message-timestamp">' + message.timestamp + '</div>';
							}
							html += '</div>';
						});
						
						html += '</div></div>';
						$detailsContent.html(html);
					} else {
						$detailsContent.html('<p class="epkb-ai-error">' + (epkb_vars.failed_load_conversation || 'Failed to load conversation details.') + '</p>');
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to load conversation details:', error);
					$detailsContent.html('<p class="epkb-ai-error">' + (epkb_vars.failed_load_conversation || 'Failed to load conversation details.') + '</p>');
				}
			});
		});

		// Handle checkbox selection for chat conversations
		$(document).on('change', '#epkb-chat-conversations-table .select-row', function() {
			let $table = $(this).closest('table');
			let $container = $table.closest('.epkb-submissions-table-container');
			let checkedCount = $table.find('.select-row:checked').length;
			
			// Update select all checkbox state
			let totalCheckboxes = $table.find('.select-row').length;
			$('#select-all-epkb-chat-conversations-table').prop('checked', checkedCount === totalCheckboxes && totalCheckboxes > 0);
			
			// Enable/disable delete selected button
			$container.find('#delete-selected').prop('disabled', checkedCount === 0);
		});

		// Handle select all checkbox for chat conversations
		$(document).on('change', '#select-all-epkb-chat-conversations-table', function() {
			let isChecked = $(this).prop('checked');
			let $table = $('#epkb-chat-conversations-table');
			let $container = $table.closest('.epkb-submissions-table-container');
			$table.find('.select-row').prop('checked', isChecked);
			
			// Enable/disable delete selected button
			$container.find('#delete-selected').prop('disabled', !isChecked || $table.find('.select-row').length === 0);
		});


		// Add data-mode attribute to tables for identification
		$(document).ready(function() {
			$('.epkb-ai-discussions-layout').each(function() {
				let $layout = $(this);
				let mode = $layout.find('.epkb-ai-discussions-header h3').text().includes('Search') ? 'search' : 'chat';
				$layout.find('#epkb-search-conversations-table, #epkb-chat-conversations-table').attr('data-mode', mode);
			});
		});

		// RESET LOGS
		$(document).on("click", "#epkb_ai_reset_logs", function () {
			// Remove old messages
			// $('.eckb-top-notice-message').html('');

			let postData = {
				action: "epkb_ai_reset_logs",
				_wpnonce_epkb_ajax_action: epkb_vars.ai_reset_logs_nonce || epkb_vars.nonce,
				epkb_kb_id: $('#epkb-list-of-kbs').val(),
			};

			epkb_send_ajax(postData, function () {
				location.reload();
			});
		});

		// Load more items
		$('.epkb-admin__items-list__more-items-message form').on('submit', function (e) {
			e.preventDefault();

			let container = $(this).closest('.epkb-admin__items-list__more-items-message').parent();
			let form = $(this);
			let insert_before = container.find('.epkb-admin__items-list .epkb-admin__items-list__no-results');

			let page_number = parseInt(form.find('[name="page_number"]').val());

			let postData = {
				action: form.find('[name="action"]').val(),
				page_number: page_number + 1,
				_wpnonce_epkb_ajax_action: epkb_vars.nonce,
				epkb_kb_id: $('#epkb-list-of-kbs').val()
			};

			epkb_send_ajax(postData, function (response) {

				if (!response.error && typeof response.message != 'undefined') {

					let new_items = $(response.items);
					new_items.css('display', 'none');

					page_number = page_number + 1;

					// Initialize submit handlers for each new items
					// new_items.find( 'form.epkb-admin__items-list__field-actions__form' ).on( 'submit', epkb_items_list_delete_item );

					// Delete 'Load more items' button if there is no more items exist
					if (response.total_number <= response.per_page * page_number) {
						container.find('.epkb-admin__items-list__more-items-message').remove();
					}

					// Insert new items
					$(insert_before).before(new_items);
					new_items.fadeIn(1000);

					// Increase page number
					form.find('[name="page_number"]').val(page_number);
				}
			});

			return false;
		});

		/*************************************************************************************************
		 *
		 *          AJAX calls
		 *
		 ************************************************************************************************/

		// generic AJAX call handler
		function epkb_send_ajax(
			postData,
			refreshCallback,
			callbackParam,
			reload,
			alwaysCallback,
			$loader
		) {
			let errorMsg;
			let theResponse;
			refreshCallback =
				typeof refreshCallback === "undefined"
					? "epkb_callback_noop"
					: refreshCallback;

			$.ajax({
				type: "POST",
				dataType: "json",
				data: postData,
				url: ajaxurl,
				beforeSend: function (xhr) {
					// Show loading dialog by default unless explicitly disabled
					if ($loader !== false) {
						if (typeof $loader == "object") {
							epkb_loading_Dialog("show", "", $loader);
						} else {
							epkb_loading_Dialog("show", "");
						}
					}
				},
			})
				.done(function (response) {
					theResponse = response ? response : "";
					if (theResponse.error || typeof theResponse.message === "undefined") {
						// If we have an error response with a message, it's already formatted HTML from PHP
						if (theResponse.error && theResponse.message) {
							errorMsg = theResponse.message;
						} else {
							// Otherwise create our own error notification
							errorMsg = epkb_admin_notification(
								"",
								epkb_vars.reload_try_again,
								"error"
							);
						}
					}
				})
				.fail(function (response, textStatus, error) {
					//noinspection JSUnresolvedVariable
					errorMsg = error ? " [" + error + "]" : epkb_vars.unknown_error;
					//noinspection JSUnresolvedVariable
					errorMsg = epkb_admin_notification(
						epkb_vars.error_occurred + ". " + epkb_vars.msg_try_again,
						errorMsg,
						"error"
					);
				})
				.always(function () {
					theResponse = typeof theResponse === "undefined" ? "" : theResponse;

					if (typeof alwaysCallback == "function") {
						alwaysCallback(theResponse);
					}

					epkb_loading_Dialog("remove", "");

					if (errorMsg) {
						$(".epkb-bottom-notice-message").remove();
						$("body #epkb-admin-page-wrap")
							.append(errorMsg)
							.removeClass("fadeOutDown");

						setTimeout(function () {
							$(".epkb-bottom-notice-message").addClass("fadeOutDown");
						}, 10000);
						return;
					}

					if (typeof refreshCallback === "function") {
						if (callbackParam === "undefined") {
							refreshCallback(theResponse);
						} else {
							refreshCallback(theResponse, callbackParam);
						}
					} else {
						if (reload) {
							location.reload();
						}
					}
				});
		}

		/**
		 * Displays a Center Dialog box with a loading icon and text.
		 *
		 * This should only be used for indicating users that loading or saving or processing is in progress, nothing else.
		 * This code is used in these files, any changes here must be done to the following files.
		 *   - admin-plugin-pages.js
		 *   - admin-kb-config-scripts.js
		 *
		 * @param  {string}    displayType     Show or hide Dialog initially. ( show, remove )
		 * @param  {string}    message         Optional    Message output from database or settings.
		 *
		 * @return {html}                      Removes old dialogs and adds the HTML to the end body tag with optional message.
		 *
		 */
		function epkb_loading_Dialog(displayType, message, $el) {
			if (displayType === "show") {
				let loadingClass =
					typeof $el == "undefined"
						? ""
						: "epkb-admin-dialog-box-loading--relative";

				let output =
					'<div class="epkb-admin-dialog-box-loading ' +
					loadingClass +
					'">' +
					//<-- Header -->
					'<div class="epkb-admin-dbl__header">' +
					'<div class="epkb-admin-dbl-icon epkbfa epkbfa-hourglass-half"></div>' +
					(message
						? '<div class="epkb-admin-text">' + message + "</div>"
						: "") +
					"</div>" +
					"</div>" +
					'<div class="epkb-admin-dialog-box-overlay ' +
					loadingClass +
					'"></div>';

				//Add message output at the end of Body Tag
				if (typeof $el == "undefined") {
					$("body").append(output);
				} else {
					$el.append(output);
				}
			} else if (displayType === "remove") {
				// Remove loading dialogs.
				$(".epkb-admin-dialog-box-loading").remove();
				$(".epkb-admin-dialog-box-overlay").remove();
			}
		}

		/* Dialogs --------------------------------------------------------------------*/

		// SHOW INFO MESSAGES
		function epkb_admin_notification($title, $message, $type) {
			return (
				'<div class="epkb-bottom-notice-message">' +
				'<div class="contents">' +
				'<span class="' +
				$type +
				'">' +
				($title ? "<h4>" + $title + "</h4>" : "") +
				($message ? "<p>" + $message + "</p>" : "") +
				"</span>" +
				"</div>" +
				'<div class="epkb-close-notice epkbfa epkbfa-window-close"></div>' +
				"</div>"
			);
		}

		let epkb_notification_timeout;

		function epkb_show_error_notification($message, $title = "") {
			$(".epkb-bottom-notice-message").remove();
			$("body #epkb-admin-page-wrap").append(
				epkb_admin_notification($title, $message, "error")
			);

			clearTimeout(epkb_notification_timeout);
			epkb_notification_timeout = setTimeout(function () {
				$(".epkb-bottom-notice-message").addClass("fadeOutDown");
			}, 10000);
		}

		function epkb_show_success_notification($message, $title = "") {
			$(".epkb-bottom-notice-message").remove();
			$("body #epkb-admin-page-wrap").append(
				epkb_admin_notification($title, $message, "success")
			);

			clearTimeout(epkb_notification_timeout);
			epkb_notification_timeout = setTimeout(function () {
				$(".epkb-bottom-notice-message").addClass("fadeOutDown");
			}, 10000);
		}

		/*************************************************************************************************
		 *
		 *          AJAX Search Table
		 *
		 ************************************************************************************************/

		// Initialize table variables from data attributes
		var table = $('#epkb-conversations-table'); // The actual table ID

		// Exit if table doesn't exist
		if (!table.length) {
			return;
		}

		var rowsPerPage = table.data('rows-per-page');
		var totalRows = table.data('total-rows');
		var totalPages = table.data('total-pages');
		var currentPage = table.data('current-page');
		var sortColumn = table.data('sort-column');
		var sortOrder = table.data('sort-order');
		var filter = table.data('filter');
		var hasCheckboxes = table.data('has-checkboxes');

		/**
		 * Fetch table data via AJAX based on page, sort, and filter parameters.
		 *
		 * @param {number} page       Page number to fetch.
		 * @param {string} sortCol    Column to sort by.
		 * @param {string} sortOrd    Sort order ('asc' or 'desc').
		 * @param {string} filt       Filter string.
		 */
		function fetchData(page, sortCol, sortOrd, filt) {
			// Show loading spinner
			epkb_loading_Dialog('show', 'Loading searches...');

			$.ajax({
				url: ajaxurl, // WordPress AJAX endpoint
				type: 'POST',
				data: {
					action: 'epkb_get_search_table_conversations_data',
					_wpnonce_epkb_ajax_action: epkb_vars.nonce,
					table_id: 'epkb-conversations-table',
					page: page,
					sort_column: sortCol,
					sort_order: sortOrd,
					filter: filt,
					fetch_html: true // New parameter to tell server to return HTML
				},
				success: function(response) {
					if (response.success) {
						updateTable(response.data);
						currentPage = response.data.current_page;
						totalPages = response.data.total_pages;
					}
				},
				error: function() {
					console.log('AJAX error fetching table data');
				},
				complete: function() {
					// Hide loading spinner
					epkb_loading_Dialog('remove', '');
				}
			});
		}

		/**
		 * Update the table body with new data.
		 *
		 * @param {object} data Response data containing rows and metadata.
		 */
		function updateTable(data) {
			var tableBody = table.find('tbody');

			// Update table body with the HTML returned from server
			if (data.html) {
				tableBody.html(data.html);

				// Expand any row infos if needed
				// (this is optional, depending on if you want row details visible by default)
				// tableBody.find('.epkb-row-info').show();
			}

			// Update pagination info
			$('#current-page').text(data.current_page);
			$('#total-pages').text(data.total_pages);

			// Update pagination button states
			$('#first-page, #prev-page').prop('disabled', data.current_page <= 1);
			$('#next-page, #last-page').prop('disabled', data.current_page >= data.total_pages || data.total_pages === 0);
		}

		// Create a debounce function to avoid too many filter requests
		function debounce(func, wait) {
			var timeout;
			return function() {
				var context = this, args = arguments;
				clearTimeout(timeout);
				timeout = setTimeout(function() {
					func.apply(context, args);
				}, wait);
			};
		}

		// Filter input event with debounce
		$('#filter-' + table.attr('id')).on('input', debounce(function() {
			var filterValue = $(this).val();
			filter = filterValue; // Update the filter variable
			fetchData(1, sortColumn, sortOrder, filterValue);
		}, 1000)); // Wait 1000ms after user stops typing

		// Sorting on clickable headers
		table.find('thead th.sortable').on('click', function() {
		var column = $(this).data('column');
		var newSortOrder = (sortColumn === column && sortOrder === 'asc') ? 'desc' : 'asc';

		// Update visual indicators
		table.find('thead th.sortable').removeAttr('data-sort-order');
		$(this).attr('data-sort-order', newSortOrder);

		// Update sort icons
		table.find('thead th.sortable').attr('data-sort', '');
		$(this).attr('data-sort', newSortOrder);

		// Update sort variables
		sortColumn = column;
		sortOrder = newSortOrder;

		// Fetch data with new sort
		fetchData(1, column, newSortOrder, filter);
		});

		// Pagination controls
		$('#first-page').on('click', function() {
		if (currentPage > 1) fetchData(1, sortColumn, sortOrder, filter);
	});
	$('#prev-page').on('click', function() {
		if (currentPage > 1) fetchData(currentPage - 1, sortColumn, sortOrder, filter);
	});
	$('#next-page').on('click', function() {
		if (currentPage < totalPages) fetchData(currentPage + 1, sortColumn, sortOrder, filter);
	});
	$('#last-page').on('click', function() {
		if (currentPage < totalPages) fetchData(totalPages, sortColumn, sortOrder, filter);
	});

	// Delete single row - removed since Actions column is no longer displayed

	// Select all checkbox
	if (hasCheckboxes) {
		$('#select-all-' + table.attr('id')).on('change', function() {
			table.find('.select-row').prop('checked', $(this).prop('checked'));
		});
	}

	// Delete selected rows
	if (hasCheckboxes) {
		$('#delete-selected').on('click', function() {
			var selectedIds = table.find('.select-row:checked').map(function() {
				return $(this).data('id');
			}).get();
			if (selectedIds.length > 0 && confirm('Are you sure you want to delete the selected rows?')) {
				// Show loading spinner
				epkb_loading_Dialog('show', 'Deleting selected searches...');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'epkb_delete_search_table_selected_conversation_rows',
						_wpnonce_epkb_ajax_action: epkb_vars.nonce,
						table_id: 'epkb-conversations-table',
						ids: selectedIds,
						current_page: currentPage
					},
					success: function(response) {
						if (response.success) {
							// Update pagination info
							currentPage = response.data.current_page;
							totalPages = response.data.total_pages;

							// Fetch updated table data for the current page
							fetchData(currentPage, sortColumn, sortOrder, filter);
						}
					},
					error: function() {
						// Hide loading spinner on error
						epkb_loading_Dialog('remove', '');
					}
				});
			}
		});
	}

	// Delete all rows
	$('#delete-all').on('click', function() {
		if (confirm('Are you sure you want to delete all rows?')) {
			// Show loading spinner
			epkb_loading_Dialog('show', 'Deleting all searches...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'epkb_delete_all_search_table_conversations',
					_wpnonce_epkb_ajax_action: epkb_vars.nonce,
					table_id: 'epkb-conversations-table'
				},
				success: function(response) {
					if (response.success) {
						// Update pagination info
						currentPage = response.data.current_page;
						totalPages = response.data.total_pages;

						// Fetch updated table data for the current page
						fetchData(currentPage, sortColumn, sortOrder, filter);
					}
				},
				error: function() {
					// Hide loading spinner on error
					epkb_loading_Dialog('remove', '');
				}
			});
		}
	});

	// Initial pagination button states
	$('#first-page, #prev-page').prop('disabled', currentPage <= 1);
	$('#next-page, #last-page').prop('disabled', currentPage >= totalPages || totalPages === 0);

	// If we have a sortColumn defined, update the visual indicator
	if (sortColumn) {
		var $sortHeader = table.find('thead th[data-column="' + sortColumn + '"]');
		$sortHeader.attr('data-sort-order', sortOrder);
		$sortHeader.attr('data-sort', sortOrder);
	}

		// Set initial filter value if present
		var initialFilter = table.data('filter');
		if (initialFilter) {
			$('#filter-' + table.attr('id')).val(initialFilter);
		}

	});

})(jQuery);
