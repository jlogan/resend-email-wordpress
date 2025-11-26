/**
 * Admin JavaScript for Resend Email Integration plugin.
 *
 * @package ResendEmailIntegration
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle Import Emails button click (on logs page).
		$('#resend-import-emails-btn').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var $status = $('#resend-import-status');

			// Disable button and show loading state.
			$button.prop('disabled', true).text(resendEmailIntegration.strings.importing || 'Importing...');
			$status.html('<span style="color: #666;">' + (resendEmailIntegration.strings.importing || 'Importing emails...') + '</span>');

			// Make AJAX request.
			$.ajax({
				url: resendEmailIntegration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'resend_import_emails',
					nonce: resendEmailIntegration.importNonce
				},
				success: function(response) {
					$button.prop('disabled', false).text(resendEmailIntegration.strings.importBtn || 'Import Emails');
					
					if (response.success) {
						$status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
						// Reload page after 1 second to show imported emails.
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						$status.html('<span style="color: red;">✗ ' + (response.data.message || 'Import failed') + '</span>');
					}
				},
				error: function() {
					$button.prop('disabled', false).text(resendEmailIntegration.strings.importBtn || 'Import Emails');
					$status.html('<span style="color: red;">✗ ' + (resendEmailIntegration.strings.error || 'An error occurred') + '</span>');
				}
			});
		});

		// Handle API key test button click (on settings page).
		$('#resend_test_api_key').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var $result = $('#resend_api_key_test_result');
			var apiKey = $('#resend_api_key').val();

			if (!apiKey) {
				$result.html('<span style="color: red;">' + (resendEmailIntegration.strings.apiKeyRequired || 'Please enter an API key first.') + '</span>');
				return;
			}

			// Disable button and show loading state.
			$button.prop('disabled', true).text(resendEmailIntegration.strings.testing || 'Testing...');
			$result.html('<span style="color: #666;">' + (resendEmailIntegration.strings.testing || 'Testing...') + '</span>');

			// Make AJAX request.
			$.ajax({
				url: resendEmailIntegration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'resend_test_api_key',
					nonce: resendEmailIntegration.nonce,
					api_key: apiKey
				},
				success: function(response) {
					$button.prop('disabled', false).text(resendEmailIntegration.strings.testApiKey || 'Test API Key');
					
					if (response.success) {
						$result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
					} else {
						$result.html('<span style="color: red;">✗ ' + (response.data.message || 'Test failed') + '</span>');
					}
				},
				error: function() {
					$button.prop('disabled', false).text(resendEmailIntegration.strings.testApiKey || 'Test API Key');
					$result.html('<span style="color: red;">✗ ' + (resendEmailIntegration.strings.error || 'An error occurred') + '</span>');
				}
			});
		});

		// Handle email detail link clicks (on logs page).
		$(document).on('click', '.resend-email-detail-link', function(e) {
			e.preventDefault();

			var emailId = $(this).data('email-id');
			var $modal = $('#resend-email-detail-modal');
			var $content = $('#resend-email-detail-content');

			if (!emailId) {
				return;
			}

			// Show modal and display loading message.
			$modal.show();
			$content.html('<p>' + (resendEmailIntegration.strings.loading || 'Loading...') + '</p>');

			// Fetch email details via AJAX.
			$.ajax({
				url: resendEmailIntegration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'resend_get_email_details',
					nonce: resendEmailIntegration.nonce,
					email_id: emailId
				},
				success: function(response) {
					if (response.success && response.data.email) {
						var email = response.data.email;
						
						// Debug: log email data to console (remove in production if needed).
						if (console && console.log) {
							console.log('Email data:', email);
							console.log('HTML content:', email.html);
							console.log('HTML type:', typeof email.html);
							console.log('HTML length:', email.html ? email.html.length : 'N/A');
						}
						
						// Parse FROM field to extract name (remove quotes and email).
						var fromDisplay = email.from || '';
						if (fromDisplay) {
							// Check if FROM is in format "Name <email@domain.com>" or just email.
							var fromMatch = fromDisplay.match(/^(.+?)\s*<(.+?)>$/);
							if (fromMatch) {
								// Extract name and remove quotes.
								fromDisplay = fromMatch[1].replace(/^["']|["']$/g, '').trim();
							} else if (fromDisplay.match(/^".+"$/)) {
								// Just quoted name.
								fromDisplay = fromDisplay.replace(/^["']|["']$/g, '');
							}
						}
						
						var html = '<table class="widefat">';
						
						html += '<tr><th style="width: 150px;">' + 'ID' + '</th><td><code>' + (email.id || '') + '</code></td></tr>';
						html += '<tr><th>' + 'To' + '</th><td>' + (Array.isArray(email.to) ? email.to.join(', ') : email.to) + '</td></tr>';
						html += '<tr><th>' + 'From' + '</th><td>' + fromDisplay + '</td></tr>';
						html += '<tr><th>' + 'Subject' + '</th><td>' + (email.subject || '') + '</td></tr>';
						
						if (email.cc && email.cc.length > 0) {
							html += '<tr><th>' + 'CC' + '</th><td>' + (Array.isArray(email.cc) ? email.cc.join(', ') : email.cc) + '</td></tr>';
						}
						if (email.bcc && email.bcc.length > 0) {
							html += '<tr><th>' + 'BCC' + '</th><td>' + (Array.isArray(email.bcc) ? email.bcc.join(', ') : email.bcc) + '</td></tr>';
						}
						if (email.reply_to && email.reply_to.length > 0) {
							html += '<tr><th>' + 'Reply-To' + '</th><td>' + (Array.isArray(email.reply_to) ? email.reply_to.join(', ') : email.reply_to) + '</td></tr>';
						}
						
						html += '<tr><th>' + 'Created At' + '</th><td>' + (email.created_at || '') + '</td></tr>';
						html += '<tr><th>' + 'Status' + '</th><td><span class="resend-status resend-status-' + (email.last_event || '').toLowerCase() + '">' + (email.last_event ? email.last_event.charAt(0).toUpperCase() + email.last_event.slice(1) : '') + '</span></td></tr>';
						
						if (email.scheduled_at) {
							html += '<tr><th>' + 'Scheduled At' + '</th><td>' + email.scheduled_at + '</td></tr>';
						}
						
						html += '</table>';
						
						// Set the table content first.
						$content.html(html);
						
						// HTML Content - append after table is rendered, display in iframe to prevent breaking WordPress design.
						// Check if HTML exists (not null, not undefined, and not empty string).
						if (email.html !== null && email.html !== undefined && email.html !== '') {
							var $htmlRow = $('<tr>');
							$htmlRow.append('<th style="vertical-align: top; padding-top: 15px;">HTML Content</th>');
							var $htmlCell = $('<td>');
							
							// Create iframe container.
							var $iframeContainer = $('<div>').css({
								'border': '1px solid #ddd',
								'background': '#f9f9f9',
								'padding': '10px',
								'position': 'relative'
							});
							
							// Use srcdoc attribute for HTML rendering in iframe (like Resend does).
							// Get raw HTML content - it should already be unescaped from JSON.
							var htmlContent = String(email.html);
							
							// Debug: log first 200 characters to see what we're working with.
							console.log('HTML content preview:', htmlContent.substring(0, 200));
							
							// Check if HTML already has DOCTYPE/html tags, if not wrap it.
							var isFullDocument = /^\s*<!DOCTYPE\s+html/i.test(htmlContent) || /^\s*<html/i.test(htmlContent);
							
							if (!isFullDocument) {
								// Wrap in a complete HTML document structure.
								// Email HTML typically has inline styles, so we preserve them in the body.
								htmlContent = '<!DOCTYPE html>\n<html>\n<head>\n<meta charset="UTF-8">\n<meta name="viewport" content="width=device-width, initial-scale=1.0">\n<style>body { margin: 0; padding: 0; }</style>\n</head>\n<body style="margin: 0; padding: 0;">\n' + htmlContent + '\n</body>\n</html>';
							}
							
							// Create iframe element - use native DOM to set srcdoc directly (like Resend does).
							// jQuery's .attr() might escape HTML entities, so we'll create the iframe and set srcdoc natively.
							var iframe = document.createElement('iframe');
							iframe.setAttribute('srcdoc', htmlContent);  // Set directly - no escaping
							iframe.setAttribute('scrolling', 'yes');
							iframe.setAttribute('frameborder', '0');
							iframe.style.width = '100%';
							iframe.style.height = '600px';
							iframe.style.border = '1px solid #ccc';
							iframe.style.background = '#fff';
							iframe.style.display = 'block';
							
							// Convert to jQuery object for consistency with rest of code.
							var $iframe = $(iframe);
							
							// Add error handling for iframe load.
							$iframe.on('load', function() {
								console.log('Email HTML iframe loaded successfully');
							}).on('error', function() {
								console.error('Error loading email HTML in iframe');
								$iframeContainer.html('<p style="color: red; padding: 20px;">Error loading email content. Please try again.</p>');
							});
							
							$iframeContainer.append($iframe);
							$htmlCell.append($iframeContainer);
							$htmlRow.append($htmlCell);
							$content.find('table').append($htmlRow);
						} else {
							// Show message if HTML is not available.
							var $htmlRow = $('<tr>');
							$htmlRow.append('<th style="vertical-align: top; padding-top: 15px;">HTML Content</th>');
							var $htmlCell = $('<td>');
							$htmlCell.html('<em style="color: #666;">No HTML content available for this email.</em>');
							$htmlRow.append($htmlCell);
							$content.find('table').append($htmlRow);
						}
						
						// Text Content - append after table is rendered.
						if (email.text) {
							var $textRow = $('<tr>');
							$textRow.append('<th>Text Content</th>');
							var $textCell = $('<td>');
							var $textPre = $('<pre>').css({
								'max-height': '300px',
								'overflow': 'auto',
								'white-space': 'pre-wrap',
								'border': '1px solid #ddd',
								'padding': '10px',
								'background': '#f9f9f9'
							}).text(email.text);
							$textCell.append($textPre);
							$textRow.append($textCell);
							$content.find('table').append($textRow);
						}
					} else {
						$content.html('<p style="color: red;">' + (response.data.message || 'Failed to load email details') + '</p>');
					}
				},
				error: function() {
					$content.html('<p style="color: red;">' + (resendEmailIntegration.strings.error || 'An error occurred') + '</p>');
				}
			});
		});

		// Close modal when clicking the X button.
		$(document).on('click', '.resend-modal-close', function() {
			$('#resend-email-detail-modal').hide();
		});

		// Close modal when clicking outside the modal content.
		$(document).on('click', '.resend-modal', function(e) {
			if ($(e.target).hasClass('resend-modal')) {
				$(this).hide();
			}
		});
	});

})(jQuery);
