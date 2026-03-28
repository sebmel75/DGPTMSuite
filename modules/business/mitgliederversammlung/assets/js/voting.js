/**
 * DGPTM Mitgliederversammlung - Voting & Manager Frontend JS
 */
(function($) {
	'use strict';

	window.dgptmMV = window.dgptmMV || {};

	var refreshTimer = null;
	var currentPollId = 0;

	// --- Initialisierung ---

	$(document).ready(function() {
		// Voting-Interface: Auto-Load
		if ($('#dgptm-mv-vote-content').length) {
			loadVoteView();
			refreshTimer = setInterval(loadVoteView, 5000);
		}

		// Manager: Abstimmung erstellen
		$(document).on('submit', '#dgptm-mv-create-form', handleCreateAssembly);
		$(document).on('submit', '#dgptm-mv-create-poll', handleCreatePoll);
		$(document).on('submit', '#dgptm-mv-add-agenda', handleAddAgenda);
		$(document).on('submit', '#dgptm-mv-vote-form', handleCastVote);

		// Manuelle Stimmabgabe
		if ($('#dgptm-mv-manual-vote').length) {
			initManualVote();
		}

		// Anwesenheitsliste Auto-Refresh
		if ($('#dgptm-mv-attendance').length) {
			loadAttendanceList();
			var interval = parseInt($('#dgptm-mv-attendance').data('refresh') || 10, 10);
			setInterval(loadAttendanceList, interval * 1000);
		}
	});

	// --- Voting ---

	function loadVoteView() {
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_get_vote_view',
			nonce: dgptm_mv.nonce
		}, function(resp) {
			if (resp.success) {
				$('#dgptm-mv-vote-content').html(resp.data.html);
				if (resp.data.poll_id !== currentPollId) {
					currentPollId = resp.data.poll_id;
				}
			} else if (resp.data && resp.data.html) {
				$('#dgptm-mv-vote-content').html(resp.data.html);
			}
		}, 'json');
	}

	function handleCastVote(e) {
		e.preventDefault();
		var $form = $(this);
		var pollId = $form.data('poll-id');
		var choices = [];

		$form.find('input[name="choices[]"]:checked').each(function() {
			choices.push(parseInt(this.value, 10));
		});

		if (choices.length === 0) {
			alert('Bitte waehlen Sie mindestens eine Option.');
			return;
		}

		var $btn = $form.find('#dgptm-mv-submit-vote');
		$btn.prop('disabled', true).text('Wird gesendet...');

		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_cast_vote',
			nonce: dgptm_mv.nonce,
			poll_id: pollId,
			choices: choices
		}, function(resp) {
			if (resp.success) {
				loadVoteView();
			} else {
				alert(resp.data || 'Fehler bei der Stimmabgabe.');
				$btn.prop('disabled', false).text('Stimme abgeben');
			}
		}, 'json').fail(function() {
			alert('Netzwerkfehler. Bitte versuchen Sie es erneut.');
			$btn.prop('disabled', false).text('Stimme abgeben');
		});
	}

	// --- Assembly Management ---

	function handleCreateAssembly(e) {
		e.preventDefault();
		var data = {
			action: 'dgptm_mv_create_assembly',
			nonce: dgptm_mv.nonce,
			name: $('[name="name"]', this).val(),
			assembly_date: $('[name="assembly_date"]', this).val(),
			location: $('[name="location"]', this).val(),
			is_hybrid: $('[name="is_hybrid"]', this).is(':checked') ? 1 : 0
		};
		$.post(dgptm_mv.ajax_url, data, function(resp) {
			if (resp.success) {
				location.reload();
			} else {
				alert(resp.data || 'Fehler.');
			}
		}, 'json');
	}

	function handleCreatePoll(e) {
		e.preventDefault();
		var $f = $(this);
		var data = {
			action: 'dgptm_mv_create_poll',
			nonce: dgptm_mv.nonce,
			assembly_id: $f.find('[name="assembly_id"]').val(),
			question: $f.find('[name="question"]').val(),
			description: $f.find('[name="description"]').val(),
			poll_type: $f.find('[name="poll_type"]').val(),
			choices: $f.find('[name="choices"]').val(),
			max_choices: $f.find('[name="max_choices"]').val(),
			is_secret: $f.find('[name="is_secret"]').is(':checked') ? 1 : 0
		};
		$.post(dgptm_mv.ajax_url, data, function(resp) {
			if (resp.success) { location.reload(); }
			else { alert(resp.data || 'Fehler.'); }
		}, 'json');
	}

	function handleAddAgenda(e) {
		e.preventDefault();
		var $f = $(this);
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_add_agenda_item',
			nonce: dgptm_mv.nonce,
			assembly_id: $f.find('[name="assembly_id"]').val(),
			title: $f.find('[name="title"]').val(),
			item_type: $f.find('[name="item_type"]').val()
		}, function(resp) {
			if (resp.success) { location.reload(); }
			else { alert(resp.data || 'Fehler.'); }
		}, 'json');
	}

	// --- Manager Actions ---

	dgptmMV.toggleAssemblyStatus = function(id, status) {
		if (!confirm('Status auf "' + status + '" setzen?')) return;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_toggle_assembly_status',
			nonce: dgptm_mv.nonce,
			id: id,
			status: status
		}, function() { location.reload(); }, 'json');
	};

	dgptmMV.activatePoll = function(id) {
		if (!confirm('Abstimmung starten? Andere aktive Abstimmungen werden beendet.')) return;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_activate_poll',
			nonce: dgptm_mv.nonce,
			poll_id: id
		}, function() { location.reload(); }, 'json');
	};

	dgptmMV.stopPoll = function(id) {
		if (!confirm('Abstimmung beenden?')) return;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_stop_poll',
			nonce: dgptm_mv.nonce,
			poll_id: id
		}, function() { location.reload(); }, 'json');
	};

	dgptmMV.releaseResults = function(id) {
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_release_results',
			nonce: dgptm_mv.nonce,
			poll_id: id
		}, function() { location.reload(); }, 'json');
	};

	dgptmMV.deletePoll = function(id) {
		if (!confirm('Abstimmung loeschen?')) return;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_delete_poll',
			nonce: dgptm_mv.nonce,
			poll_id: id
		}, function() { location.reload(); }, 'json');
	};

	dgptmMV.showOnBeamer = function(pollId) {
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_set_beamer_state',
			nonce: dgptm_mv.nonce,
			mode: 'results_one',
			poll_id: pollId
		});
	};

	dgptmMV.setBeamerState = function(mode) {
		var assembly = $('#dgptm-mv-manager .dgptm-mv-active-assembly').length ? true : false;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_set_beamer_state',
			nonce: dgptm_mv.nonce,
			mode: mode,
			qr_visible: $('#dgptm-mv-beamer-qr').is(':checked') ? 1 : 0
		});
	};

	dgptmMV.toggleBeamerQR = function(visible) {
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_set_beamer_state',
			nonce: dgptm_mv.nonce,
			mode: 'current',
			qr_visible: visible ? 1 : 0
		});
	};

	dgptmMV.onPollTypeChange = function(sel) {
		var type = sel.value;
		var $choices = $(sel).closest('form').find('[name="choices"]');
		if (type === 'simple' || type === 'satzung') {
			$choices.val("Ja\nNein\nEnthaltung");
		} else if (type === 'election') {
			$choices.val("Kandidat 1\nKandidat 2\nEnthaltung");
		}
	};

	// --- Anwesenheitsliste ---

	function loadAttendanceList() {
		var assemblyId = $('#dgptm-mv-attendance').data('assembly-id');
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_get_attendance_list',
			nonce: dgptm_mv.nonce,
			assembly_id: assemblyId
		}, function(resp) {
			if (!resp.success) return;
			var d = resp.data;

			// Statistik
			$('#att-total').text(d.stats.total);
			$('#att-eligible').text(d.stats.eligible);
			$('#att-presence').text(d.stats.presence);
			$('#att-online').text(d.stats.online);

			// Tabelle
			var html = '';
			(d.attendees || []).forEach(function(a) {
				html += '<tr>';
				html += '<td>' + esc(a.name) + '</td>';
				html += '<td>' + esc(a.email) + '</td>';
				html += '<td>' + esc(a.member_no) + '</td>';
				html += '<td>' + esc(a.member_status) + '</td>';
				html += '<td>' + (a.type === 'presence' ? 'Praesenz' : 'Online') + '</td>';
				html += '<td>' + (a.eligible ? '<strong>Ja</strong>' : 'Nein') + '</td>';
				html += '<td>' + formatDate(a.checked_in) + '</td>';
				html += '<td><button class="dgptm-mv-btn small danger" onclick="dgptmMV.deleteAttendee(' + a.id + ')">X</button></td>';
				html += '</tr>';
			});
			$('#dgptm-mv-att-body').html(html || '<tr><td colspan="8">Keine Eintraege.</td></tr>');
		}, 'json');
	}

	dgptmMV.deleteAttendee = function(id) {
		if (!confirm('Eintrag entfernen?')) return;
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_delete_attendee',
			nonce: dgptm_mv.nonce,
			id: id
		}, function() { loadAttendanceList(); }, 'json');
	};

	dgptmMV.exportAttendance = function(format) {
		var assemblyId = $('#dgptm-mv-attendance').data('assembly-id');
		var action = format === 'pdf' ? 'dgptm_mv_export_attendance_pdf' : 'dgptm_mv_export_attendance_csv';
		window.location.href = dgptm_mv.ajax_url + '?action=' + action + '&nonce=' + dgptm_mv.nonce + '&assembly_id=' + assemblyId;
	};

	// --- Manuelle Stimmabgabe ---

	function initManualVote() {
		var selectedUserId = null;
		var debounceTimer = null;

		$('#dgptm-mv-member-search').on('input', function() {
			var q = this.value.trim();
			clearTimeout(debounceTimer);
			if (q.length < 2) { $('#dgptm-mv-member-results').empty(); return; }
			debounceTimer = setTimeout(function() { searchMembers(q); }, 300);
		});

		function searchMembers(q) {
			// Aktive Abstimmung ermitteln
			var assemblyId = $('#dgptm-mv-manual-vote').data('assembly-id');
			$.post(dgptm_mv.ajax_url, {
				action: 'dgptm_mv_get_eligible_voters',
				nonce: dgptm_mv.nonce,
				search: q,
				poll_id: currentPollId
			}, function(resp) {
				if (!resp.success) return;
				var html = '';
				resp.data.forEach(function(m) {
					var cls = m.has_voted ? ' voted' : '';
					var badge = m.has_voted ? '<span class="dgptm-mv-badge stopped">Bereits gestimmt</span>' : '';
					html += '<div class="member-item' + cls + '" data-user-id="' + m.user_id + '">';
					html += '<span>' + esc(m.name) + ' <small>(' + esc(m.member_no || '') + ')</small></span>';
					html += badge;
					html += '</div>';
				});
				$('#dgptm-mv-member-results').html(html || '<p>Keine Ergebnisse.</p>');
			}, 'json');
		}

		$(document).on('click', '#dgptm-mv-member-results .member-item:not(.voted)', function() {
			selectedUserId = $(this).data('user-id');
			var name = $(this).find('span').first().text();
			$('#dgptm-mv-selected-member').show();
			$('#dgptm-mv-selected-name').text(name);
			$('#dgptm-mv-selected-user-id').val(selectedUserId);
			$('#dgptm-mv-member-results').empty();

			// Poll-View laden
			loadManualPollView();
		});

		function loadManualPollView() {
			$.post(dgptm_mv.ajax_url, {
				action: 'dgptm_mv_get_vote_view',
				nonce: dgptm_mv.nonce
			}, function(resp) {
				if (resp.success) {
					currentPollId = resp.data.poll_id;
					// Modifiziertes HTML fuer manuelle Stimmabgabe
					var html = resp.data.html;
					// Form-Action auf manuelle Stimmabgabe aendern
					html = html.replace('id="dgptm-mv-vote-form"', 'id="dgptm-mv-manual-vote-form"');
					html = html.replace('Stimme abgeben', 'Stimme fuer Mitglied abgeben');
					$('#dgptm-mv-manual-poll-content').html(html);

					// Manuelle Stimmabgabe Handler
					$('#dgptm-mv-manual-vote-form').off('submit').on('submit', function(e) {
						e.preventDefault();
						var choices = [];
						$(this).find('input[name="choices[]"]:checked').each(function() {
							choices.push(parseInt(this.value, 10));
						});
						if (!choices.length) { alert('Bitte waehlen.'); return; }
						if (!selectedUserId) { alert('Kein Mitglied ausgewaehlt.'); return; }

						$.post(dgptm_mv.ajax_url, {
							action: 'dgptm_mv_cast_manual_vote',
							nonce: dgptm_mv.nonce,
							voter_user_id: selectedUserId,
							poll_id: currentPollId,
							choices: choices
						}, function(resp) {
							if (resp.success) {
								alert(resp.data.message);
								selectedUserId = null;
								$('#dgptm-mv-selected-member').hide();
								$('#dgptm-mv-member-search').val('').focus();
								loadManualPollView();
							} else {
								alert(resp.data || 'Fehler.');
							}
						}, 'json');
					});
				} else if (resp.data && resp.data.html) {
					$('#dgptm-mv-manual-poll-content').html(resp.data.html);
				}
			}, 'json');
		}

		// Initial laden
		loadManualPollView();
		setInterval(loadManualPollView, 8000);
	}

	// --- Helpers ---

	function esc(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function formatDate(str) {
		if (!str) return '';
		var d = new Date(str.replace(' ', 'T'));
		return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
	}

})(jQuery);
