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
	});

})(jQuery);
