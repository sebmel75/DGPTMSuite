/**
 * Event Tracker Frontend JavaScript
 * Version: 2.0.0
 */

(function($) {
	'use strict';

	console.log('Event Tracker: frontend.js loaded');

	$(document).ready(function() {
		console.log('Event Tracker: DOM ready');

		var panelsContainer = $('.et-panels');
		console.log('Event Tracker: Panels container found:', panelsContainer.length);

		if (!panelsContainer.length) {
			console.error('Event Tracker: .et-panels container not found!');
			return;
		}

		var ajaxUrl = eventTrackerData.ajaxUrl;
		var nonce = eventTrackerData.nonce;
		console.log('Event Tracker: AJAX URL:', ajaxUrl);
		console.log('Event Tracker: Nonce:', nonce ? 'Set' : 'Missing');

		var currentPanel = null;

		/**
		 * Show message
		 */
		function showMsg(text, type) {
			var msg = panelsContainer.find('.et-msg');
			msg.removeClass('success error et-hidden').addClass(type).text(text).show();
			setTimeout(function() {
				msg.fadeOut();
			}, 5000);
		}

		/**
		 * Show panel
		 */
		function showPanel(name, data) {
			console.log('Event Tracker: Showing panel:', name);
			panelsContainer.find('.et-panel').addClass('et-hidden').attr('aria-hidden', 'true');
			var panel = panelsContainer.find('.et-panel[data-name="' + name + '"]');
			panel.removeClass('et-hidden').attr('aria-hidden', 'false');
			if (data && data.html) {
				panel.html(data.html);
			}
			currentPanel = name;
		}

		/**
		 * Fetch panel via AJAX
		 */
		function fetchPanel(action, extraData) {
			var data = {
				action: action,
				nonce: nonce
			};
			if (extraData) {
				$.extend(data, extraData);
			}
			console.log('Event Tracker: Sending AJAX request:', data);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: data,
				success: function(res) {
					console.log('Event Tracker: AJAX response:', res);
					if (res.success) {
						showPanel(currentPanel, res.data);
					} else {
						var msg = res.data && res.data.message ? res.data.message : 'Fehler';
						console.error('Event Tracker: AJAX error:', msg);
						showMsg(msg, 'error');
					}
				},
				error: function(xhr, status, error) {
					console.error('Event Tracker: Network error:', status, error);
					console.error('Event Tracker: Response:', xhr.responseText);
					showMsg('Netzwerkfehler: ' + error, 'error');
				}
			});
		}

		/**
		 * Button click handler
		 */
		panelsContainer.on('click', '.et-btn[data-panel]', function(e) {
			console.log('Event Tracker: Button clicked');
			e.preventDefault();

			var btn = $(this);
			var panelName = btn.data('panel');
			console.log('Event Tracker: Panel name:', panelName);
			currentPanel = panelName;

			if (panelName === 'list') {
				console.log('Event Tracker: Fetching event list...');
				fetchPanel('et_fetch_event_list');
			} else if (panelName === 'form') {
				console.log('Event Tracker: Fetching event form...');
				fetchPanel('et_fetch_event_form', { event_id: 0 });
			}
		});

		/**
		 * Edit button in list
		 */
		panelsContainer.on('click', '.et-btn[data-action="edit"]', function(e) {
			e.preventDefault();
			var eventId = $(this).data('event-id');
			console.log('Event Tracker: Edit button clicked, event ID:', eventId);
			currentPanel = 'form';
			fetchPanel('et_fetch_event_form', { event_id: eventId });
		});

		/**
		 * Delete button in list
		 */
		panelsContainer.on('click', '.et-btn[data-action="delete"]', function(e) {
			e.preventDefault();
			var eventId = $(this).data('event-id');
			if (!confirm('Veranstaltung wirklich löschen?')) {
				return;
			}
			console.log('Event Tracker: Delete button clicked, event ID:', eventId);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'et_delete_event',
					nonce: nonce,
					event_id: eventId
				},
				success: function(res) {
					if (res.success) {
						showMsg('Event gelöscht', 'success');
						// Reload list
						currentPanel = 'list';
						fetchPanel('et_fetch_event_list');
					} else {
						showMsg(res.data.message || 'Fehler beim Löschen', 'error');
					}
				},
				error: function() {
					showMsg('Netzwerkfehler', 'error');
				}
			});
		});

		/**
		 * Copy link button
		 */
		panelsContainer.on('click', '.et-btn[data-action="copy"]', function(e) {
			e.preventDefault();
			var link = $(this).data('link');
			console.log('Event Tracker: Copy link:', link);

			// Create temporary input
			var tempInput = $('<input>');
			$('body').append(tempInput);
			tempInput.val(link).select();
			document.execCommand('copy');
			tempInput.remove();

			showMsg('Link kopiert!', 'success');
		});

		console.log('Event Tracker: Event handlers registered');

		/* =================================================================
		 * Zoho Meeting Panel Handlers
		 * ================================================================= */

		/**
		 * Show Zoho Meeting message
		 */
		function zmShowMsg(text, type) {
			var msg = $('#et-zm-msg');
			msg.removeClass('success error').addClass(type).text(text).show();
			if (type === 'success') {
				setTimeout(function() { msg.fadeOut(); }, 5000);
			}
		}

		/**
		 * Get event ID from Zoho Meeting panel
		 */
		function zmEventId() {
			return $('.et-zm-panel').data('event-id');
		}

		/**
		 * Zoho Meeting AJAX helper
		 */
		function zmAjax(action, extraData, callback) {
			var data = {
				action: action,
				nonce: nonce,
				event_id: zmEventId()
			};
			if (extraData) {
				$.extend(data, extraData);
			}
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: data,
				success: function(res) {
					if (callback) callback(res);
				},
				error: function() {
					zmShowMsg('Netzwerkfehler', 'error');
				}
			});
		}

		// Toggle panel open/close
		$(document).on('click', '.et-zm-toggle', function() {
			$(this).closest('.et-zm-panel').toggleClass('open');
		});

		// Create Webinar
		$(document).on('click', '[data-zm-action="create"]', function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Erstelle...');
			zmAjax('et_zm_create_webinar', {}, function(res) {
				if (res.success) {
					zmShowMsg(res.data.message, 'success');
					// Reload the form panel to show full Zoho Meeting panel
					currentPanel = 'form';
					fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
				} else {
					zmShowMsg(res.data.message || 'Fehler', 'error');
					btn.prop('disabled', false).text('Webinar anlegen');
				}
			});
		});

		// Refresh Links
		$(document).on('click', '[data-zm-action="refresh-links"]', function() {
			var btn = $(this);
			btn.prop('disabled', true);
			zmAjax('et_zm_get_links', {}, function(res) {
				btn.prop('disabled', false);
				if (res.success) {
					$('#et-zm-start-url').val(res.data.start_url || '');
					$('#et-zm-join-url').val(res.data.join_url || '');
					zmShowMsg('Links aktualisiert', 'success');
				} else {
					zmShowMsg(res.data.message || 'Fehler', 'error');
				}
			});
		});

		// Copy URL from input field
		$(document).on('click', '[data-zm-action="copy"]', function(e) {
			e.preventDefault();
			var targetId = $(this).data('zm-target');
			var input = document.getElementById(targetId);
			if (input && input.value) {
				if (navigator.clipboard) {
					navigator.clipboard.writeText(input.value);
				} else {
					input.select();
					document.execCommand('copy');
				}
				zmShowMsg('Kopiert!', 'success');
			}
		});

		// Adopt join URL as redirect URL
		$(document).on('click', '[data-zm-action="adopt-redirect"]', function() {
			var joinUrl = $('#et-zm-join-url').val();
			if (joinUrl) {
				$('input[name="et_url"]').val(joinUrl);
				zmShowMsg('Join-URL in Redirect-URL uebernommen', 'success');
			}
		});

		// Adopt recording URL
		$(document).on('click', '[data-zm-action="adopt-recording"]', function() {
			var recUrl = $('#et-zm-recording-url').val();
			if (recUrl) {
				$('input[name="et_recording_url"]').val(recUrl);
				zmShowMsg('Recording-URL uebernommen', 'success');
			}
		});

		// Fetch Recording
		$(document).on('click', '[data-zm-action="fetch-recording"]', function() {
			var btn = $(this);
			btn.prop('disabled', true);
			zmAjax('et_zm_get_recording', {}, function(res) {
				btn.prop('disabled', false);
				if (res.success) {
					$('#et-zm-recording-url').val(res.data.recording_url || '');
					zmShowMsg(res.data.message, res.data.recording_url ? 'success' : 'error');
					// Show copy/adopt buttons if recording found
					if (res.data.recording_url) {
						currentPanel = 'form';
						fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
					}
				} else {
					zmShowMsg(res.data.message || 'Fehler', 'error');
				}
			});
		});

		// Test Connection
		$(document).on('click', '[data-zm-action="test-connection"]', function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Teste...');
			zmAjax('et_zm_test_connection', {}, function(res) {
				btn.prop('disabled', false).text('Verbindung testen');
				if (res.success) {
					zmShowMsg(res.data.message, 'success');
				} else {
					zmShowMsg(res.data.message || 'Verbindung fehlgeschlagen', 'error');
				}
			});
		});

		// Start Webinar
		$(document).on('click', '[data-zm-action="start-webinar"]', function() {
			zmAjax('et_zm_start_webinar', {}, function(res) {
				if (res.success && res.data.start_url) {
					window.open(res.data.start_url, '_blank');
					zmShowMsg('Webinar wird gestartet...', 'success');
				} else {
					zmShowMsg(res.data.message || 'Fehler', 'error');
				}
			});
		});

		// Delete Webinar
		$(document).on('click', '[data-zm-action="delete-webinar"]', function() {
			if (!confirm('Webinar wirklich loeschen? Dies kann nicht rueckgaengig gemacht werden.')) {
				return;
			}
			var btn = $(this);
			btn.prop('disabled', true);
			// We reuse create endpoint logic — but we need a delete endpoint
			// For now: call zm_create_webinar with delete flag won't work;
			// we need to add a delete handler. Let's use the existing API pattern.
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'et_zm_delete_webinar',
					nonce: nonce,
					event_id: zmEventId()
				},
				success: function(res) {
					if (res.success) {
						zmShowMsg('Webinar geloescht', 'success');
						currentPanel = 'form';
						fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
					} else {
						zmShowMsg(res.data.message || 'Fehler', 'error');
						btn.prop('disabled', false);
					}
				},
				error: function() {
					zmShowMsg('Netzwerkfehler', 'error');
					btn.prop('disabled', false);
				}
			});
		});

		// Co-Host: User Search with debounce
		var zmSearchTimer = null;
		$(document).on('input', '#et-zm-user-search', function() {
			var input = $(this);
			var query = input.val().trim();
			var results = $('#et-zm-user-results');

			clearTimeout(zmSearchTimer);

			if (query.length < 2) {
				results.removeClass('visible').empty();
				return;
			}

			zmSearchTimer = setTimeout(function() {
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'et_zm_search_users',
						nonce: nonce,
						search: query
					},
					success: function(res) {
						results.empty();
						if (res.success && res.data.users && res.data.users.length) {
							$.each(res.data.users, function(i, user) {
								results.append(
									'<div class="et-zm-user-item" data-email="' + user.email + '">' +
									user.name + ' &lt;' + user.email + '&gt;</div>'
								);
							});
							results.addClass('visible');
						} else {
							results.removeClass('visible');
						}
					}
				});
			}, 300);
		});

		// Co-Host: Select user from search results
		$(document).on('click', '.et-zm-user-item', function() {
			var email = $(this).data('email');
			var tags = $('#et-zm-cohost-tags');

			// Check if already added
			if (tags.find('[data-email="' + email + '"]').length) {
				return;
			}

			tags.append(
				'<span class="et-zm-tag" data-email="' + email + '">' +
				email + ' <span class="et-zm-tag-remove">&times;</span></span>'
			);

			$('#et-zm-user-search').val('');
			$('#et-zm-user-results').removeClass('visible').empty();
		});

		// Co-Host: Remove tag
		$(document).on('click', '.et-zm-tag-remove', function() {
			$(this).closest('.et-zm-tag').remove();
		});

		// Hide search results on outside click
		$(document).on('click', function(e) {
			if (!$(e.target).closest('.et-zm-user-search').length) {
				$('#et-zm-user-results').removeClass('visible');
			}
		});

		// Co-Host: Save
		$(document).on('click', '[data-zm-action="save-cohosts"]', function() {
			var btn = $(this);
			var emails = [];
			$('#et-zm-cohost-tags .et-zm-tag').each(function() {
				emails.push($(this).data('email'));
			});

			if (!emails.length) {
				zmShowMsg('Keine Co-Hosts ausgewaehlt', 'error');
				return;
			}

			btn.prop('disabled', true).text('Speichere...');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'et_zm_add_cohosts',
					nonce: nonce,
					event_id: zmEventId(),
					'emails[]': emails
				},
				success: function(res) {
					btn.prop('disabled', false).text('Co-Hosts speichern');
					if (res.success) {
						zmShowMsg(res.data.message, 'success');
					} else {
						zmShowMsg(res.data.message || 'Fehler', 'error');
					}
				},
				error: function() {
					btn.prop('disabled', false).text('Co-Hosts speichern');
					zmShowMsg('Netzwerkfehler', 'error');
				}
			});
		});

	});

})(jQuery);
