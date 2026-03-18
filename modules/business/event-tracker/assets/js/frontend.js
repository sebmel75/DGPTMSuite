/**
 * Event Tracker Frontend JavaScript
 * Version: 2.2.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		var panelsContainer = $('.et-panels');

		if (!panelsContainer.length) {
			return;
		}

		var ajaxUrl = eventTrackerData.ajaxUrl;
		var nonce = eventTrackerData.nonce;

		var currentPanel = null;

		/**
		 * Extract error message safely from AJAX response
		 */
		function getMsg(res, fallback) {
			if (res && res.data && res.data.message) {
				return res.data.message;
			}
			return fallback || 'Ein Fehler ist aufgetreten.';
		}

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

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: data,
				success: function(res) {
					if (res && res.success) {
						showPanel(currentPanel, res.data);
					} else {
						showMsg(getMsg(res, 'Fehler beim Laden'), 'error');
					}
				},
				error: function(xhr, status, error) {
					showMsg('Netzwerkfehler: ' + (error || status || 'Verbindung fehlgeschlagen'), 'error');
				}
			});
		}

		/**
		 * Button click handler
		 */
		panelsContainer.on('click', '.et-btn[data-panel]', function(e) {
			e.preventDefault();

			var btn = $(this);
			var panelName = btn.data('panel');
			currentPanel = panelName;

			if (panelName === 'list') {
				fetchPanel('et_fetch_event_list');
			} else if (panelName === 'form') {
				fetchPanel('et_fetch_event_form', { event_id: 0 });
			}
		});

		/**
		 * Edit button in list
		 */
		panelsContainer.on('click', '.et-btn[data-action="edit"]', function(e) {
			e.preventDefault();
			var eventId = $(this).data('event-id');
			currentPanel = 'form';
			fetchPanel('et_fetch_event_form', { event_id: eventId });
		});

		/**
		 * Delete button in list
		 */
		panelsContainer.on('click', '.et-btn[data-action="delete"]', function(e) {
			e.preventDefault();
			var eventId = $(this).data('event-id');
			if (!confirm('Veranstaltung wirklich loeschen?')) {
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'et_delete_event',
					nonce: nonce,
					event_id: eventId
				},
				success: function(res) {
					if (res && res.success) {
						showMsg('Event geloescht', 'success');
						currentPanel = 'list';
						fetchPanel('et_fetch_event_list');
					} else {
						showMsg(getMsg(res, 'Fehler beim Loeschen'), 'error');
					}
				},
				error: function() {
					showMsg('Netzwerkfehler', 'error');
				}
			});
		});

		/**
		 * Copy link button (event list)
		 */
		panelsContainer.on('click', '.et-btn[data-action="copy"]', function(e) {
			e.preventDefault();
			var link = $(this).data('link');
			if (navigator.clipboard) {
				navigator.clipboard.writeText(link);
			} else {
				var tempInput = $('<input>');
				$('body').append(tempInput);
				tempInput.val(link).select();
				document.execCommand('copy');
				tempInput.remove();
			}
			showMsg('Link kopiert!', 'success');
		});

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
		 * Zoho Meeting AJAX helper with robust error handling
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
				dataType: 'json',
				data: data,
				success: function(res) {
					if (callback) callback(res);
				},
				error: function(xhr, status, error) {
					zmShowMsg('Netzwerkfehler: ' + (error || status || 'Verbindung fehlgeschlagen'), 'error');
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
				if (res && res.success) {
					zmShowMsg(getMsg(res, 'Webinar erstellt'), 'success');
					currentPanel = 'form';
					fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
				} else {
					zmShowMsg(getMsg(res, 'Webinar konnte nicht erstellt werden'), 'error');
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
				if (res && res.success) {
					$('#et-zm-start-url').val((res.data && res.data.start_url) || '');
					$('#et-zm-join-url').val((res.data && res.data.join_url) || '');
					zmShowMsg('Links aktualisiert', 'success');
				} else {
					zmShowMsg(getMsg(res, 'Links konnten nicht abgerufen werden'), 'error');
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
				if (res && res.success && res.data) {
					$('#et-zm-recording-url').val(res.data.recording_url || '');
					zmShowMsg(getMsg(res, 'Recording abgefragt'), res.data.recording_url ? 'success' : 'error');
					if (res.data.recording_url) {
						currentPanel = 'form';
						fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
					}
				} else {
					zmShowMsg(getMsg(res, 'Recording konnte nicht abgerufen werden'), 'error');
				}
			});
		});

		// Test Connection
		$(document).on('click', '[data-zm-action="test-connection"]', function() {
			var btn = $(this);
			btn.prop('disabled', true).text('Teste...');
			zmAjax('et_zm_test_connection', {}, function(res) {
				btn.prop('disabled', false).text('Verbindung testen');
				if (res && res.success) {
					zmShowMsg(getMsg(res, 'Verbindung erfolgreich'), 'success');
				} else {
					zmShowMsg(getMsg(res, 'Verbindung fehlgeschlagen'), 'error');
				}
			});
		});

		// Start Webinar
		$(document).on('click', '[data-zm-action="start-webinar"]', function() {
			zmAjax('et_zm_start_webinar', {}, function(res) {
				if (res && res.success && res.data && res.data.start_url) {
					window.open(res.data.start_url, '_blank');
					zmShowMsg('Webinar wird gestartet...', 'success');
				} else {
					zmShowMsg(getMsg(res, 'Webinar konnte nicht gestartet werden'), 'error');
				}
			});
		});

		// Delete Webinar
		$(document).on('click', '[data-zm-action="delete-webinar"]', function() {
			if (!confirm('Webinar wirklich loeschen?')) {
				return;
			}
			var btn = $(this);
			btn.prop('disabled', true);
			zmAjax('et_zm_delete_webinar', {}, function(res) {
				if (res && res.success) {
					zmShowMsg('Webinar geloescht', 'success');
					currentPanel = 'form';
					fetchPanel('et_fetch_event_form', { event_id: zmEventId() });
				} else {
					zmShowMsg(getMsg(res, 'Webinar konnte nicht geloescht werden'), 'error');
					btn.prop('disabled', false);
				}
			});
		});

		// Co-Host: User Search with debounce
		var zmSearchTimer = null;
		$(document).on('input', '#et-zm-user-search', function() {
			var query = $(this).val().trim();
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
					dataType: 'json',
					data: {
						action: 'et_zm_search_users',
						nonce: nonce,
						search: query
					},
					success: function(res) {
						results.empty();
						if (res && res.success && res.data && res.data.users && res.data.users.length) {
							$.each(res.data.users, function(i, user) {
								results.append(
									'<div class="et-zm-user-item" data-email="' + $('<span>').text(user.email).html() + '">' +
									$('<span>').text(user.name).html() + ' &lt;' + $('<span>').text(user.email).html() + '&gt;</div>'
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

			// Duplicate check using .filter() to avoid selector injection
			if (tags.find('.et-zm-tag').filter(function() {
				return $(this).data('email') === email;
			}).length) {
				return;
			}

			var escapedEmail = $('<span>').text(email).html();
			tags.append(
				'<span class="et-zm-tag" data-email="' + escapedEmail + '">' +
				escapedEmail + ' <span class="et-zm-tag-remove">&times;</span></span>'
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

		// Co-Host: Save — use 'emails' key so PHP receives $_POST['emails'] as array
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
				dataType: 'json',
				data: {
					action: 'et_zm_add_cohosts',
					nonce: nonce,
					event_id: zmEventId(),
					emails: emails
				},
				success: function(res) {
					btn.prop('disabled', false).text('Co-Hosts speichern');
					if (res && res.success) {
						zmShowMsg(getMsg(res, 'Co-Hosts gespeichert'), 'success');
					} else {
						zmShowMsg(getMsg(res, 'Co-Hosts konnten nicht gespeichert werden'), 'error');
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
