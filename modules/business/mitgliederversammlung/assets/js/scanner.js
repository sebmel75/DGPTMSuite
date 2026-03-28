/**
 * DGPTM Mitgliederversammlung - Praesenz-Scanner JS
 */
(function($) {
	'use strict';

	var scanHistory = [];
	var audioCtx = null;

	$(document).ready(function() {
		var $wrap = $('.dgptm-mv-scanner-wrap');
		if (!$wrap.length) return;

		var useCrm = $wrap.data('usecrm') === '1' || $wrap.data('usecrm') === 1;
		var saveOn = ($wrap.data('saveon') || 'green,yellow').split(',');
		var assemblyId = $wrap.data('assembly-id');

		// Scanner-Input
		$wrap.find('.dgptm-mv-scan-input').on('keydown', function(e) {
			if (e.key !== 'Enter') return;
			e.preventDefault();
			var code = this.value.trim();
			this.value = '';
			if (!code) return;
			processScan(code, useCrm, saveOn, assemblyId);
		});

		// Manuelle Suche
		$('#dgptm-mv-manual-search-btn').on('click', function() {
			$('#dgptm-mv-search-modal').show();
			$('#dgptm-mv-search-input').val('').focus();
		});

		$('.dgptm-mv-modal-close').on('click', function() {
			$('#dgptm-mv-search-modal').hide();
		});

		var searchTimer = null;
		$('#dgptm-mv-search-input').on('input', function() {
			var q = this.value.trim();
			clearTimeout(searchTimer);
			if (q.length < 2) { $('#dgptm-mv-search-results').empty(); return; }
			searchTimer = setTimeout(function() { searchMember(q, assemblyId); }, 300);
		});

		// Statistik initial laden
		loadScannerStats(assemblyId);
		setInterval(function() { loadScannerStats(assemblyId); }, 15000);
	});

	function processScan(code, useCrm, saveOn, assemblyId) {
		if (useCrm) {
			// CRM-Ticket-Check
			$.ajax({
				url: dgptm_mv.rest_url + 'ticket-check?scan=' + encodeURIComponent(code),
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', dgptm_mv.rest_nonce);
				}
			}).done(function(data) {
				showScanResult(data, code, saveOn, assemblyId);
			}).fail(function() {
				showFlash('red');
				beep(300, 0.3, 200);
				showInfo('Fehler', 'Netzwerkfehler');
			});
		} else {
			// Direkt einchecken ohne CRM
			saveToAttendance({
				name: code,
				scan_code: code,
				member_no: code
			}, assemblyId);
		}
	}

	function showScanResult(data, code, saveOn, assemblyId) {
		var color = data.result || 'red';

		showFlash(color);
		beepForColor(color);
		showInfo(data.name || code, data.status || '');

		if (saveOn.indexOf(color) !== -1 && data.ok) {
			saveToAttendance({
				name: data.name || '',
				email: data.email || '',
				member_no: data.member_no || code,
				member_status: data.member_status || data.status || '',
				scan_code: code
			}, assemblyId);
		}

		addToHistory(data.name || code, color, data.status || '');
	}

	function saveToAttendance(params, assemblyId) {
		params.assembly_id = assemblyId;
		params.attendance_type = 'presence';
		params.source = 'scanner';

		$.ajax({
			url: dgptm_mv.rest_url + 'scanner/checkin',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(params),
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', dgptm_mv.rest_nonce);
			}
		}).done(function(resp) {
			if (resp.status === 'already') {
				showInfo(resp.name || params.name, 'Bereits eingecheckt');
			}
			if (resp.stats) {
				updateStats(resp.stats);
			}
		});
	}

	function searchMember(query, assemblyId) {
		$.ajax({
			url: dgptm_mv.rest_url + 'member-search',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ search: query }),
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', dgptm_mv.rest_nonce);
			}
		}).done(function(data) {
			if (!data.ok) return;
			var html = '';
			data.results.forEach(function(m) {
				html += '<div class="member-result" data-member=\'' + JSON.stringify(m).replace(/'/g, '&#39;') + '\' style="padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin:4px 0;cursor:pointer;">';
				html += '<strong>' + esc(m.name) + '</strong>';
				html += ' <small>' + esc(m.member_no || '') + '</small>';
				html += ' <span style="float:right;">' + (m.eligible ? 'Stimmberechtigt' : '') + '</span>';
				html += '</div>';
			});
			$('#dgptm-mv-search-results').html(html || '<p>Keine Ergebnisse.</p>');
		});

		$(document).off('click', '.member-result').on('click', '.member-result', function() {
			var m = JSON.parse($(this).attr('data-member'));
			$('#dgptm-mv-search-modal').hide();

			saveToAttendance({
				user_id: m.user_id || 0,
				name: m.name || '',
				email: m.email || '',
				member_no: m.member_no || '',
				member_status: m.member_status || '',
				scan_code: ''
			}, assemblyId);

			showFlash('green');
			beepForColor('green');
			showInfo(m.name, 'Manuell eingecheckt');
			addToHistory(m.name, 'green', 'Manuell');
		});
	}

	function loadScannerStats(assemblyId) {
		$.ajax({
			url: dgptm_mv.rest_url + 'attendance',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', dgptm_mv.rest_nonce);
			}
		}).done(function(data) {
			if (data.ok && data.stats) {
				updateStats(data.stats);
			}
		});
	}

	function updateStats(stats) {
		$('#dgptm-mv-scanner-stats').html(
			'Anwesend: <strong>' + stats.total + '</strong> | ' +
			'Stimmberechtigt: <strong>' + stats.eligible + '</strong> | ' +
			'Praesenz: <strong>' + stats.presence + '</strong> | ' +
			'Online: <strong>' + stats.online + '</strong>'
		);
	}

	// --- UI Helpers ---

	function showFlash(color) {
		var $flash = $('.dgptm-mv-scanner-flash');
		$flash.removeClass('green yellow red').addClass(color);
		setTimeout(function() { $flash.removeClass(color); }, 1500);
	}

	function showInfo(name, sub) {
		$('.dgptm-mv-scanner-info').text(name);
		$('.dgptm-mv-scanner-sub').text(sub);
	}

	function addToHistory(name, color, status) {
		var time = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		scanHistory.unshift({ name: name, color: color, status: status, time: time });
		if (scanHistory.length > 30) scanHistory.pop();

		var html = '';
		scanHistory.forEach(function(entry) {
			html += '<div class="scan-entry">';
			html += '<span style="color:' + (entry.color === 'green' ? '#16a34a' : entry.color === 'yellow' ? '#ca8a04' : '#dc2626') + ';">' + esc(entry.name) + '</span>';
			html += '<span>' + esc(entry.status) + '</span>';
			html += '<span class="time">' + entry.time + '</span>';
			html += '</div>';
		});
		$('#dgptm-mv-scanner-list').html(html);
	}

	function beepForColor(color) {
		if (color === 'green') beep(800, 0.2, 150);
		else if (color === 'yellow') beep(600, 0.2, 200);
		else beep(300, 0.3, 300);
	}

	function beep(freq, vol, duration) {
		try {
			if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
			var osc = audioCtx.createOscillator();
			var gain = audioCtx.createGain();
			osc.connect(gain);
			gain.connect(audioCtx.destination);
			osc.frequency.value = freq;
			gain.gain.value = vol;
			osc.start();
			setTimeout(function() { osc.stop(); }, duration);
		} catch(e) {}
	}

	function esc(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

})(jQuery);
