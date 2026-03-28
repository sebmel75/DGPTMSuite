/**
 * DGPTM Mitgliederversammlung - Beamer/Projektion JS
 */
(function($) {
	'use strict';

	var questionActive = false;
	var questionSeconds = 0;
	var refreshTimer = null;
	var lastMode = '';
	var chartInstance = null;
	var qrDrawn = false;

	function drawQR(url) {
		if (qrDrawn) return;
		try {
			// qrcode-generator API
			if (typeof qrcode !== 'undefined') {
				var qr = qrcode(0, 'M');
				qr.addData(url);
				qr.make();
				var canvas = document.getElementById('dgptm-mv-qr-canvas');
				if (!canvas) return;
				var ctx = canvas.getContext('2d');
				var size = 200;
				canvas.width = size;
				canvas.height = size;
				var count = qr.getModuleCount();
				var cellSize = size / count;
				ctx.fillStyle = '#fff';
				ctx.fillRect(0, 0, size, size);
				ctx.fillStyle = '#000';
				for (var r = 0; r < count; r++) {
					for (var c = 0; c < count; c++) {
						if (qr.isDark(r, c)) {
							ctx.fillRect(c * cellSize, r * cellSize, cellSize + 0.5, cellSize + 0.5);
						}
					}
				}
				qrDrawn = true;
			}
		} catch(e) {}
	}

	$(document).ready(function() {
		if (!$('#dgptm-mv-beamer').length) return;
		updateClock();
		loadBeamerState();
	});

	function pad(n) { return String(n).padStart(2, '0'); }

	function updateClock() {
		var now = new Date();
		$('#dgptm-mv-clock-time').text(pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()));
		if (questionActive) {
			questionSeconds++;
			$('#dgptm-mv-question-timer').text(questionSeconds + 's');
		} else {
			$('#dgptm-mv-question-timer').text('0s');
		}
		setTimeout(updateClock, 1000);
	}

	function schedule(ms) {
		clearTimeout(refreshTimer);
		refreshTimer = setTimeout(loadBeamerState, ms);
	}

	function show(sel) { $(sel).show(); }
	function hide(sel) { $(sel).hide(); }

	function loadBeamerState() {
		$.post(dgptm_mv.ajax_url, {
			action: 'dgptm_mv_get_beamer_payload'
		}, function(resp) {
			if (!resp || !resp.success) { schedule(5000); return; }

			var d = resp.data;
			var state = d.beamer_state || {};
			var mode = state.mode || 'idle';

			// Kein Assembly
			if (!d.active_assembly) {
				hide('#dgptm-mv-beamer-title');
				hide('#dgptm-mv-beamer-live');
				hide('#dgptm-mv-beamer-results-all');
				hide('#dgptm-mv-beamer-qr');
				$('#dgptm-mv-beamer-idle').show();
				questionActive = false;
				schedule(10000);
				return;
			}

			// Title + Stats
			$('#dgptm-mv-beamer-title').text(d.active_assembly.name).show();
			if (d.stats) {
				$('#dgptm-mv-beamer-stat-present').text(d.stats.total + ' Anwesend');
				$('#dgptm-mv-beamer-stat-eligible').text(d.stats.eligible + ' Stimmberechtigt');
			}

			// QR
			if (state.qr_visible) {
				show('#dgptm-mv-beamer-qr');
				drawQR(window.location.origin + '/?mv_vote=1');
			} else {
				hide('#dgptm-mv-beamer-qr');
			}

			// MODE: results_one
			if (mode === 'results_one' && d.results) {
				hide('#dgptm-mv-beamer-idle');
				hide('#dgptm-mv-beamer-results-all');
				show('#dgptm-mv-beamer-live');

				$('#dgptm-mv-beamer-cta').text('Ergebnis');
				$('#dgptm-mv-beamer-question').text(d.results.question);
				$('#dgptm-mv-beamer-majority-info').text(d.results.majority_info || '');

				var total = d.results.total_votes || 0;
				var eligible = d.stats ? d.stats.eligible : 0;
				$('#dgptm-mv-beamer-live-bar').text('Abgegebene Stimmen: ' + total + ' / ' + eligible + ' Stimmberechtigte');

				renderChart(d.results.choices, d.results.votes, d.results.chart_type || 'bar');

				lastMode = 'results_one';
				questionActive = false;
				questionSeconds = 0;
				schedule(2000);
				return;
			}

			// MODE: results_all
			if (mode === 'results_all' && d.all_results && d.all_results.length) {
				hide('#dgptm-mv-beamer-idle');
				hide('#dgptm-mv-beamer-live');
				show('#dgptm-mv-beamer-results-all');

				if (lastMode !== 'results_all') {
					var grid = $('#dgptm-mv-beamer-results-grid').empty();
					d.all_results.forEach(function(r) {
						var card = $('<div class="result-card"><h3></h3><p></p><canvas></canvas></div>');
						card.find('h3').text(r.question);
						card.find('p').text(r.majority_info || '');
						var canvas = card.find('canvas')[0];
						canvas.id = 'beamer-rc-' + r.poll_id;
						grid.append(card);
						setTimeout(function() {
							renderChartOnCanvas(canvas, r.choices, r.votes, r.chart_type || 'bar');
						}, 50);
					});
				}

				lastMode = 'results_all';
				questionActive = false;
				schedule(3000);
				return;
			}

			// MODE: idle (keine aktive Abstimmung)
			if (!d.active_poll || mode === 'idle') {
				hide('#dgptm-mv-beamer-live');
				hide('#dgptm-mv-beamer-results-all');
				show('#dgptm-mv-beamer-idle');

				lastMode = 'idle';
				questionActive = false;
				questionSeconds = 0;
				schedule(3000);
				return;
			}

			// MODE: live (aktive Abstimmung)
			hide('#dgptm-mv-beamer-idle');
			hide('#dgptm-mv-beamer-results-all');
			show('#dgptm-mv-beamer-live');

			$('#dgptm-mv-beamer-cta').text('Bitte abstimmen');
			$('#dgptm-mv-beamer-question').text(d.active_poll.question);

			var majorityLabels = {
				'simple': 'Einfache Mehrheit',
				'three_quarters': 'Dreiviertelmehrheit',
				'absolute': 'Absolute Mehrheit (>50%)'
			};
			$('#dgptm-mv-beamer-majority-info').text(majorityLabels[d.active_poll.required_majority] || '');

			var tv = d.active_poll.total_votes || 0;
			var el = d.stats ? d.stats.eligible : 0;
			var pct = el > 0 ? Math.round((tv / el) * 100) : 0;
			$('#dgptm-mv-beamer-live-bar').text('Abgestimmt: ' + tv + ' / ' + el + ' (' + pct + '%)');

			// Im Live-Modus kein Chart zeigen (Anonymitaet!)
			$('#dgptm-mv-beamer-chart').hide();

			lastMode = 'live';
			questionActive = true;
			if (lastMode !== 'live') questionSeconds = 0;
			schedule(1500);

		}, 'json').fail(function() {
			schedule(5000);
		});
	}

	function renderChart(labels, values, type) {
		var canvas = document.getElementById('dgptm-mv-beamer-chart');
		if (!canvas) return;
		$(canvas).show();
		renderChartOnCanvas(canvas, labels, values, type);
	}

	function renderChartOnCanvas(canvas, labels, values, type) {
		if (typeof Chart === 'undefined') return;
		if (canvas._chartInstance) canvas._chartInstance.destroy();

		var colors = [
			'rgba(34,197,94,0.7)',   // green
			'rgba(239,68,68,0.7)',   // red
			'rgba(234,179,8,0.7)',   // yellow
			'rgba(59,130,246,0.7)',  // blue
			'rgba(168,85,247,0.7)',  // purple
			'rgba(249,115,22,0.7)', // orange
			'rgba(156,163,175,0.7)', // gray
			'rgba(20,184,166,0.7)'  // teal
		];

		var chartType = (type === 'pie') ? 'pie' : 'bar';
		var data, options;

		if (chartType === 'pie') {
			data = { labels: labels, datasets: [{ data: values, backgroundColor: colors }] };
			options = { responsive: true, animation: false, plugins: { legend: { display: true, position: 'bottom' } } };
		} else {
			data = { labels: labels, datasets: [{ label: 'Stimmen', data: values, backgroundColor: colors, borderWidth: 1 }] };
			options = {
				responsive: true,
				animation: false,
				scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
				plugins: { legend: { display: false } }
			};
		}

		canvas._chartInstance = new Chart(canvas, { type: chartType, data: data, options: options });
	}

})(jQuery);
