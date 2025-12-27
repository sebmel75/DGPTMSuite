/**
 * Zeitschrift Kardiotechnik - Admin JavaScript
 */

(function($) {
    'use strict';

    var ZKAdmin = {
        /**
         * Initialisierung
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Event-Handler binden
         */
        bindEvents: function() {
            // Datum speichern
            $(document).on('click', '.zk-btn-save-date', this.handleSaveDate);

            // Jetzt veröffentlichen
            $(document).on('click', '.zk-btn-publish-now', this.handlePublishNow);

            // Akzeptierte Artikel laden
            $(document).on('click', '#zk-load-accepted', this.handleLoadAccepted);

            // Datum-Änderung mit Enter speichern
            $(document).on('keypress', '.zk-date-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).closest('.zk-date-controls').find('.zk-btn-save-date').click();
                }
            });
        },

        /**
         * Zeigt eine Benachrichtigung an
         */
        showNotice: function($row, type, message) {
            var $notice = $('<div class="zk-notice zk-notice-' + type + '">' + message + '</div>');

            // Vorherige Notices entfernen
            $row.find('.zk-notice').remove();

            // Neue Notice einfügen
            $row.find('.zk-col-date').prepend($notice);

            // Nach 3 Sekunden ausblenden
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Datum speichern
         */
        handleSaveDate: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $row = $btn.closest('.zk-issue-row');
            var issueId = $btn.data('issue-id');
            var $input = $row.find('.zk-date-input');
            var dateValue = $input.val();

            // Validierung
            if (!dateValue) {
                ZKAdmin.showNotice($row, 'error', 'Bitte ein Datum auswählen');
                return;
            }

            // Datum konvertieren (YYYY-MM-DD zu DD/MM/YYYY)
            var parts = dateValue.split('-');
            var formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0];

            $btn.prop('disabled', true).text('Speichern...');

            $.ajax({
                url: zkAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_update_publish_date',
                    nonce: zkAdmin.nonce,
                    post_id: issueId,
                    date: formattedDate
                },
                success: function(response) {
                    if (response.success) {
                        ZKAdmin.showNotice($row, 'success', 'Datum gespeichert');
                        ZKAdmin.updateRowStatus($row, response.data);
                    } else {
                        ZKAdmin.showNotice($row, 'error', response.data.message || 'Fehler beim Speichern');
                    }
                },
                error: function() {
                    ZKAdmin.showNotice($row, 'error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Speichern');
                }
            });
        },

        /**
         * Jetzt veröffentlichen
         */
        handlePublishNow: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $row = $btn.closest('.zk-issue-row');
            var issueId = $btn.data('issue-id');

            if (!confirm('Ausgabe jetzt veröffentlichen?')) {
                return;
            }

            $btn.prop('disabled', true).text('Veröffentlichen...');

            $.ajax({
                url: zkAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_publish_now',
                    nonce: zkAdmin.nonce,
                    post_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        ZKAdmin.showNotice($row, 'success', 'Ausgabe veröffentlicht');

                        // Datum-Input aktualisieren
                        var today = new Date();
                        var formattedDate = today.toISOString().split('T')[0];
                        $row.find('.zk-date-input').val(formattedDate);

                        // Status-Badge aktualisieren
                        $row.find('.zk-status-badge')
                            .removeClass('zk-status-scheduled zk-status-soon')
                            .addClass('zk-status-online')
                            .text('Online');

                        // Publish-Button entfernen
                        $btn.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        ZKAdmin.showNotice($row, 'error', response.data.message || 'Fehler');
                    }
                },
                error: function() {
                    ZKAdmin.showNotice($row, 'error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Jetzt veröffentlichen');
                }
            });
        },

        /**
         * Akzeptierte Artikel laden
         */
        handleLoadAccepted: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $container = $('#zk-accepted-articles');
            var $list = $container.find('.zk-accepted-list');

            $btn.prop('disabled', true);
            $list.html('<div class="zk-loading">Lade Artikel...</div>');

            $.ajax({
                url: zkAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_accepted_articles',
                    nonce: zkAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var articles = response.data.articles;

                        if (articles.length === 0) {
                            $list.html('<p class="zk-no-accepted">Keine akzeptierten Artikel vorhanden.</p>');
                            return;
                        }

                        var html = '';
                        articles.forEach(function(article) {
                            html += '<div class="zk-accepted-article" data-id="' + article.id + '">';
                            html += '<div class="zk-accepted-info">';
                            html += '<div class="zk-accepted-title">' + ZKAdmin.escapeHtml(article.title) + '</div>';
                            html += '<div class="zk-accepted-meta">';
                            if (article.author) {
                                html += 'Autor: ' + ZKAdmin.escapeHtml(article.author) + ' | ';
                            }
                            if (article.publikationsart) {
                                html += 'Art: ' + ZKAdmin.escapeHtml(article.publikationsart) + ' | ';
                            }
                            html += 'ID: ' + article.submission_id;
                            html += '</div>';
                            html += '</div>';
                            html += '<div class="zk-accepted-actions">';
                            html += '<a href="' + zkAdmin.adminUrl + 'post.php?post=' + article.id + '&action=edit" ';
                            html += 'class="zk-btn zk-btn-small zk-btn-secondary" target="_blank">Ansehen</a>';
                            html += '</div>';
                            html += '</div>';
                        });

                        $list.html(html);
                    } else {
                        $list.html('<p class="zk-notice zk-notice-error">' +
                            (response.data.message || 'Fehler beim Laden') + '</p>');
                    }
                },
                error: function() {
                    $list.html('<p class="zk-notice zk-notice-error">Verbindungsfehler</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Zeilen-Status aktualisieren
         */
        updateRowStatus: function($row, data) {
            var $badge = $row.find('.zk-status-badge');
            var $publishBtn = $row.find('.zk-btn-publish-now');

            if (data.is_visible) {
                $badge.removeClass('zk-status-scheduled zk-status-soon')
                      .addClass('zk-status-online')
                      .text('Online');

                $publishBtn.fadeOut(300);
            } else {
                $badge.removeClass('zk-status-online')
                      .addClass('zk-status-scheduled')
                      .text('Geplant: ' + data.date);

                if ($publishBtn.length === 0) {
                    var html = '<button type="button" class="zk-btn zk-btn-small zk-btn-publish-now" ' +
                               'data-issue-id="' + $row.data('issue-id') + '">Jetzt veröffentlichen</button>';
                    $row.find('.zk-action-buttons').prepend(html);
                } else {
                    $publishBtn.fadeIn(300);
                }
            }
        },

        /**
         * HTML escapen
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialisierung
    $(document).ready(function() {
        if (typeof zkAdmin !== 'undefined') {
            // Admin URL hinzufügen falls nicht vorhanden
            if (!zkAdmin.adminUrl) {
                zkAdmin.adminUrl = '/wp-admin/';
            }
            ZKAdmin.init();
        }
    });

})(jQuery);
