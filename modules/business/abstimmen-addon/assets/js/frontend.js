/**
 * DGPTM Abstimmen-Addon - Frontend JavaScript
 * Version: 4.0.0
 */

(function($) {
    'use strict';

    /**
     * Voting Interface
     */
    const VotingInterface = {
        init: function() {
            this.bindEvents();
            this.loadMemberView();
        },

        bindEvents: function() {
            // Join poll
            $(document).on('click', '#dgptm_joinBtn', this.handleJoin);

            // Vote submission
            $(document).on('submit', '#dgptm_memberVoteForm', this.handleVote);

            // Name gate form
            $(document).on('submit', '#dgptm_nameGateForm', this.handleNameGate);

            // Toggle button
            $(document).on('click', '.dgptm-toggle-button', this.handleToggleButton);
        },

        loadMemberView: function() {
            const $container = $('#dgptm_memberViewContainer');
            if (!$container.length) return;

            const pollId = $container.data('poll-id');
            const token = $container.data('token');

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_get_member_view',
                    poll_id: pollId,
                    token: token
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                    } else {
                        $container.html(response.data.html || '<p>Fehler beim Laden</p>');
                    }
                },
                error: function() {
                    $container.html('<p>Verbindungsfehler</p>');
                }
            });
        },

        handleJoin: function(e) {
            e.preventDefault();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Beitritt...');

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_join_poll',
                    poll_id: $('#dgptm_memberVoteForm').data('poll-id') || 0
                },
                success: function(response) {
                    if (response.success) {
                        VotingInterface.showMessage('Erfolgreich beigetreten', 'success');
                        $btn.hide();
                    } else {
                        VotingInterface.showMessage(response.data || 'Fehler', 'error');
                        $btn.prop('disabled', false).text('Jetzt teilnehmen');
                    }
                },
                error: function() {
                    VotingInterface.showMessage('Verbindungsfehler', 'error');
                    $btn.prop('disabled', false).text('Jetzt teilnehmen');
                }
            });
        },

        handleVote: function(e) {
            e.preventDefault();
            const $form = $(this);
            const maxVotes = parseInt($form.data('max-votes')) || 1;
            const selectedCount = $form.find('input[name="choices[]"]:checked').length;

            // Validate vote count
            if (selectedCount === 0) {
                VotingInterface.showMessage('Bitte wählen Sie mindestens eine Option', 'error');
                return;
            }

            if (selectedCount > maxVotes) {
                VotingInterface.showMessage('Sie können maximal ' + maxVotes + ' Option(en) wählen', 'error');
                return;
            }

            const formData = $form.serialize();
            $form.find('button[type="submit"]').prop('disabled', true).text('Sende...');

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        VotingInterface.showMessage('Ihre Stimme wurde erfasst!', 'success');
                        $form.find('input').prop('disabled', true);
                        $form.find('button[type="submit"]').text('Abgestimmt ✓');
                    } else {
                        VotingInterface.showMessage(response.data || 'Fehler beim Abstimmen', 'error');
                        $form.find('button[type="submit"]').prop('disabled', false).text('Abstimmen');
                    }
                },
                error: function() {
                    VotingInterface.showMessage('Verbindungsfehler', 'error');
                    $form.find('button[type="submit"]').prop('disabled', false).text('Abstimmen');
                }
            });
        },

        handleNameGate: function(e) {
            e.preventDefault();
            const $form = $(this);
            const name = $('#dgptm_gateName').val().trim();

            if (name.length < 3) {
                VotingInterface.showMessage('Bitte vollständigen Namen eingeben', 'error');
                return;
            }

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_save_participant_name',
                    name: name
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        VotingInterface.showMessage('Fehler beim Speichern', 'error');
                    }
                },
                error: function() {
                    VotingInterface.showMessage('Verbindungsfehler', 'error');
                }
            });
        },

        handleToggleButton: function(e) {
            e.preventDefault();
            const $btn = $(this);
            if ($btn.prop('disabled')) return;

            const currentState = $btn.hasClass('on') ? 'on' : 'off';
            const newState = currentState === 'on' ? 'off' : 'on';

            $btn.prop('disabled', true);

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_vote_toggle_ajax',
                    nonce: $btn.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('on off').addClass(newState);
                        $btn.html(response.data.button_html || '');
                        VotingInterface.showMessage(response.data.message || 'Aktualisiert', 'success');
                    } else {
                        VotingInterface.showMessage(response.data || 'Fehler', 'error');
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    VotingInterface.showMessage('Verbindungsfehler', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        showMessage: function(message, type) {
            const $feedback = $('#dgptm_memberVoteFeedback');
            if ($feedback.length) {
                $feedback
                    .removeClass('success error')
                    .addClass(type)
                    .html('<p>' + message + '</p>')
                    .show();

                setTimeout(function() {
                    $feedback.fadeOut();
                }, 3000);
            } else {
                alert(message);
            }
        }
    };

    /**
     * Presence Scanner
     */
    const PresenceScanner = {
        init: function() {
            if (!$('.dgptm-presence').length) return;

            this.bindEvents();
            this.initScanner();
        },

        bindEvents: function() {
            // Manual search button
            $(document).on('click', '#dgptm-manual-open', this.openSearchModal);

            // Modal controls
            $(document).on('click', '.dgptm-modal-close, #dgptm-search-cancel-btn', this.closeSearchModal);
            $(document).on('click', '#dgptm-search-execute-btn', this.executeSearch);
            $(document).on('click', '#dgptm-search-modal', function(e) {
                if (e.target === this) {
                    PresenceScanner.closeSearchModal();
                }
            });

            // Search input enter key
            $(document).on('keypress', '#dgptm-search-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#dgptm-search-execute-btn').trigger('click');
                }
            });

            // Result item selection
            $(document).on('click', '.dgptm-search-result-item', function() {
                $('.dgptm-search-result-item').removeClass('selected');
                $(this).addClass('selected');
            });

            // Result item double-click
            $(document).on('dblclick', '.dgptm-search-result-item', function() {
                const data = JSON.parse($(this).attr('data-member') || '{}');
                PresenceScanner.addSelectedMember(data);
            });
        },

        initScanner: function() {
            const $input = $('.scan-input');
            if (!$input.length) return;

            $input.focus();

            $input.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const code = $(this).val().trim();
                    if (code) {
                        PresenceScanner.processScan(code);
                        $(this).val('');
                    }
                }
            });
        },

        processScan: function(code) {
            const $container = $('.dgptm-presence');
            const webhook = $container.data('webhook');
            const meeting = $container.data('meeting');
            const kind = $container.data('kind');

            if (!webhook) {
                PresenceScanner.showInfo('Webhook nicht konfiguriert', 'error');
                return;
            }

            $.ajax({
                url: webhook,
                type: 'POST',
                data: {
                    code: code,
                    meeting_id: meeting,
                    kind: kind
                },
                success: function(response) {
                    PresenceScanner.showFlash();
                    if (response.success || response.ok) {
                        PresenceScanner.showInfo('Erfolgreich erfasst', 'success');
                        PresenceScanner.addEntryToList(response.data || {});
                    } else {
                        PresenceScanner.showInfo(response.message || 'Fehler', 'error');
                    }
                },
                error: function() {
                    PresenceScanner.showInfo('Verbindungsfehler', 'error');
                }
            });
        },

        openSearchModal: function(e) {
            e.preventDefault();
            $('#dgptm-search-modal').show();
            $('#dgptm-search-input').focus();
        },

        closeSearchModal: function() {
            $('#dgptm-search-modal').hide();
            $('#dgptm-search-input').val('');
            $('#dgptm-search-results').html('');
        },

        executeSearch: function(e) {
            e.preventDefault();
            const name = $('#dgptm-search-input').val().trim();
            if (!name) {
                alert('Bitte Name eingeben');
                return;
            }

            const $container = $('.dgptm-presence');
            const searchUrl = $container.data('search-webhook');
            const $results = $('#dgptm-search-results');

            $results.html('<em>Suche läuft…</em>');

            $.ajax({
                url: searchUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    name: name,
                    query: name
                }),
                success: function(response) {
                    PresenceScanner.renderResults(response);
                },
                error: function() {
                    $results.html('<span style="color:#c00">Fehler bei der Suche.</span>');
                }
            });
        },

        renderResults: function(data) {
            const $results = $('#dgptm-search-results');
            const results = Array.isArray(data) ? data : (data.results || []);

            if (!results.length) {
                $results.html('<em>Keine Ergebnisse.</em>');
                return;
            }

            let html = '<div role="list" aria-label="Suchergebnisse">';
            results.forEach(function(member) {
                const name = member.fullname || member.name || 'Unbekannt';
                const email = member.email || member.Email || '';
                const status = member.status || member.Status || '–';
                const art = member.Mitgliedsart || member.mitgliedsart || '–';
                const nr = member.mitgliedsnummer || member.Mitgliedsnummer || '–';

                const merged = {
                    fullname: name,
                    email: email,
                    status: status,
                    Mitgliedsart: art,
                    mitgliedsnummer: nr
                };

                html += '<div class="dgptm-search-result-item" data-member=\'' + JSON.stringify(merged).replace(/'/g, '&#039;') + '\' title="Doppelklick übernimmt">';
                html += '<div><strong>' + name + '</strong></div>';
                html += '<div class="dgptm-row"><b>Email</b><span>' + (email || '–') + '</span></div>';
                html += '<div class="dgptm-row"><b>Status</b><span>' + status + '</span></div>';
                html += '<div class="dgptm-row"><b>Mitgliedsart</b><span>' + art + '</span></div>';
                html += '<div class="dgptm-row"><b>Mitgliedsnummer</b><span>' + nr + '</span></div>';
                html += '</div>';
            });
            html += '</div>';

            $results.html(html);
        },

        addSelectedMember: function(data) {
            const $container = $('.dgptm-presence');
            const meeting = $container.data('meeting');
            const kind = $container.data('kind');

            // Save to database via REST API
            if (typeof dgptm_vote !== 'undefined' && dgptm_vote.rest_presence) {
                $.ajax({
                    url: dgptm_vote.rest_presence,
                    type: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': dgptm_vote.rest_nonce
                    },
                    data: JSON.stringify({
                        id: meeting,
                        kind: kind,
                        name: data.fullname || data.name,
                        email: data.email,
                        status: data.Mitgliedsart || data.status,
                        mitgliedsart: data.Mitgliedsart,
                        mitgliedsnummer: data.mitgliedsnummer,
                        ts: Math.floor(Date.now() / 1000),
                        manual: 1
                    }),
                    success: function() {
                        PresenceScanner.showInfo('Erfolgreich erfasst (manuell)', 'success');
                        PresenceScanner.showFlash();
                        PresenceScanner.addManualEntryToList(data);
                        PresenceScanner.closeSearchModal();
                    },
                    error: function() {
                        alert('Speichern fehlgeschlagen');
                    }
                });
            }
        },

        addEntryToList: function(data) {
            const $list = $('#dgptm-last-entries');
            const ts = new Date();
            const div = $('<div>')
                .addClass('dgptm-entry')
                .html(
                    '<div class="dgptm-entry-header">' +
                    (data.name || 'Unbekannt') +
                    ' <span class="dgptm-entry-time">' + ts.toLocaleTimeString() + '</span></div>' +
                    '<div class="dgptm-entry-details">' +
                    '<span>E-Mail: ' + (data.email || '–') + '</span>' +
                    ' · <span>Mitgliedsart: ' + (data.mitgliedsart || '–') + '</span>' +
                    '</div>'
                );

            $list.prepend(div);
        },

        addManualEntryToList: function(data) {
            const $list = $('#dgptm-last-entries');
            const ts = new Date();
            const div = $('<div>')
                .addClass('dgptm-entry dgptm-manual-entry')
                .html(
                    '<div class="dgptm-entry-header">' +
                    (data.fullname || data.name || 'Unbekannt') +
                    ' <span class="dgptm-entry-time">' + ts.toLocaleTimeString() + '</span></div>' +
                    '<div class="dgptm-entry-details">' +
                    '<span>E-Mail: ' + (data.email || '–') + '</span>' +
                    ' · <span>Mitgliedsart: ' + (data.Mitgliedsart || '–') + '</span>' +
                    ' · <span><b>Manuell: X</b></span>' +
                    '</div>'
                );

            $list.prepend(div);
        },

        showInfo: function(message, type) {
            const $info = $('.dgptm-presence .info');
            $info.removeClass('success error').addClass(type).text(message).show();

            setTimeout(function() {
                $info.fadeOut();
            }, 3000);
        },

        showFlash: function() {
            const $flash = $('.dgptm-presence .flash');
            $flash.addClass('show');
            setTimeout(function() {
                $flash.removeClass('show');
            }, 300);
        }
    };

    /**
     * Live Updates (Presence Table)
     */
    const LiveUpdates = {
        init: function() {
            const $table = $('.dgptm-presence-ui');
            if (!$table.length) return;

            const interval = parseInt($table.data('poll-interval')) || 10000;
            this.startPolling(interval);
        },

        startPolling: function(interval) {
            setInterval(function() {
                LiveUpdates.updateTable();
            }, interval);
        },

        updateTable: function() {
            const $table = $('.dgptm-presence-ui');
            const meeting = $table.data('meeting');
            const kind = $table.data('kind');

            if (!meeting) return;

            $.ajax({
                url: dgptm_vote.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_get_presence_data',
                    meeting_id: meeting,
                    kind: kind
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        const $tbody = $table.find('tbody');
                        $tbody.html(response.data.html);
                    }
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        VotingInterface.init();
        PresenceScanner.init();
        LiveUpdates.init();
    });

})(jQuery);
