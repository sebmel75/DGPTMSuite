/**
 * Zeitschrift Kardiotechnik - Admin JavaScript (Frontend Manager)
 * Vollständig AJAX-basiert für Benutzer ohne Backend-Zugriff
 */

(function($) {
    'use strict';

    var ZKManager = {
        config: {
            ajaxUrl: '',
            nonce: '',
            adminUrl: ''
        },

        /**
         * Initialisierung
         */
        init: function() {
            console.log('ZK Manager: Initialisierung gestartet');

            if (typeof zkAdmin === 'undefined') {
                console.error('ZK Admin: Konfiguration nicht geladen');
                return;
            }

            console.log('ZK Manager: Konfiguration geladen', zkAdmin);

            this.config = zkAdmin;
            this.bindEvents();
            this.loadYearFilter();
            this.loadIssues();

            console.log('ZK Manager: Initialisierung abgeschlossen');
        },

        /**
         * Event-Handler binden
         */
        bindEvents: function() {
            var self = this;

            // Tab-Wechsel
            $(document).on('click', '.zk-tab', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Refresh
            $(document).on('click', '#zk-refresh-btn', function(e) {
                e.preventDefault();
                self.loadIssues();
            });

            // Filter
            $(document).on('change', '#zk-filter-status, #zk-filter-year', function() {
                self.loadIssues();
            });

            // Neue Ausgabe Modal öffnen
            $(document).on('click', '#zk-new-issue-btn', function(e) {
                e.preventDefault();
                self.openNewModal();
            });

            // Modal schließen
            $(document).on('click', '.zk-modal-close, .zk-modal-cancel, .zk-modal-overlay', function(e) {
                e.preventDefault();
                self.closeModals();
            });

            // Neue Ausgabe speichern
            $(document).on('click', '#zk-save-new-issue', function(e) {
                e.preventDefault();
                self.createIssue();
            });

            // Ausgabe bearbeiten
            $(document).on('click', '.zk-issue-edit', function(e) {
                e.preventDefault();
                var issueId = $(this).closest('.zk-issue-item').data('id');
                self.openEditModal(issueId);
            });

            // Bearbeitung speichern
            $(document).on('click', '#zk-save-edit-issue', function(e) {
                e.preventDefault();
                self.updateIssue();
            });

            // Ausgabe löschen
            $(document).on('click', '.zk-issue-delete', function(e) {
                e.preventDefault();
                var issueId = $(this).closest('.zk-issue-item').data('id');
                var title = $(this).closest('.zk-issue-item').find('.zk-issue-title').text();
                self.deleteIssue(issueId, title);
            });

            // Jetzt veröffentlichen
            $(document).on('click', '.zk-issue-publish', function(e) {
                e.preventDefault();
                var issueId = $(this).closest('.zk-issue-item').data('id');
                self.publishNow(issueId);
            });

            // Akzeptierte Artikel Tab
            $(document).on('click', '.zk-tab[data-tab="accepted"]', function() {
                self.loadAcceptedArticles();
            });

            // Artikel Tab
            $(document).on('click', '.zk-tab[data-tab="articles"]', function() {
                self.loadArticles();
            });

            // Neuer Artikel Button
            $(document).on('click', '#zk-new-article-btn', function(e) {
                e.preventDefault();
                self.openNewArticleModal();
            });

            // Neuer Artikel speichern
            $(document).on('click', '#zk-save-new-article', function(e) {
                e.preventDefault();
                self.createArticle();
            });

            // Artikel bearbeiten
            $(document).on('click', '.zk-article-edit', function(e) {
                e.preventDefault();
                var articleId = $(this).closest('.zk-article-item').data('id');
                self.openEditArticleModal(articleId);
            });

            // Artikel speichern
            $(document).on('click', '#zk-save-edit-article', function(e) {
                e.preventDefault();
                self.updateArticle();
            });

            // Artikel löschen
            $(document).on('click', '#zk-delete-article', function(e) {
                e.preventDefault();
                self.deleteArticle();
            });

            // Artikel-Suche
            var articleSearchTimeout;
            $(document).on('input', '#zk-article-search', function() {
                clearTimeout(articleSearchTimeout);
                articleSearchTimeout = setTimeout(function() {
                    self.loadArticles();
                }, 300);
            });

            // Artikel-Sortierung
            $(document).on('change', '#zk-article-sort, #zk-article-order', function() {
                self.loadArticles();
            });

            // Link Artikel Button (im Ausgaben-Edit Modal)
            $(document).on('click', '.zk-link-article-btn', function(e) {
                e.preventDefault();
                var issueId = $('#zk-edit-id').val();
                var slot = $(this).data('slot');
                self.openLinkArticleModal(issueId, slot);
            });

            // Artikel verknüpfen
            $(document).on('click', '.zk-available-article-item', function(e) {
                e.preventDefault();
                var articleId = $(this).data('id');
                self.linkArticle(articleId);
            });

            // Artikel entknüpfen
            $(document).on('click', '.zk-unlink-article-btn', function(e) {
                e.preventDefault();
                var issueId = $('#zk-edit-id').val();
                var slot = $(this).data('slot');
                self.unlinkArticle(issueId, slot);
            });

            // Link Modal Suche
            var linkSearchTimeout;
            $(document).on('input', '#zk-link-article-search', function() {
                clearTimeout(linkSearchTimeout);
                linkSearchTimeout = setTimeout(function() {
                    self.loadAvailableArticles();
                }, 300);
            });

            // Modal nicht schließen bei Klick auf Content
            $(document).on('click', '.zk-modal-content', function(e) {
                e.stopPropagation();
            });

            // ESC zum Schließen
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModals();
                }
            });
        },

        /**
         * Tab wechseln
         */
        switchTab: function(tabId) {
            $('.zk-tab').removeClass('zk-tab-active');
            $('.zk-tab[data-tab="' + tabId + '"]').addClass('zk-tab-active');

            $('.zk-tab-content').removeClass('zk-tab-active');
            $('#zk-tab-' + tabId).addClass('zk-tab-active');
        },

        /**
         * Jahr-Filter laden
         */
        loadYearFilter: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_available_years',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.years) {
                        var $select = $('#zk-filter-year');
                        $select.find('option:not(:first)').remove();

                        response.data.years.forEach(function(year) {
                            $select.append('<option value="' + year + '">' + year + '</option>');
                        });
                    }
                }
            });
        },

        /**
         * Alle Ausgaben laden
         */
        loadIssues: function() {
            var self = this;
            var $list = $('#zk-issues-list');

            console.log('ZK Manager: Lade Ausgaben...');

            $list.html(this.getLoadingHtml('Lade Ausgaben...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_all_issues',
                    nonce: this.config.nonce,
                    status: $('#zk-filter-status').val(),
                    year: $('#zk-filter-year').val()
                },
                success: function(response) {
                    console.log('ZK Manager: AJAX Antwort erhalten', response);
                    if (response.success) {
                        self.renderIssues(response.data.issues);
                    } else {
                        $list.html(self.getErrorHtml(response.data.message || 'Fehler beim Laden'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('ZK Manager: AJAX Fehler', status, error);
                    $list.html(self.getErrorHtml('Verbindungsfehler'));
                }
            });
        },

        /**
         * Ausgaben rendern
         */
        renderIssues: function(issues) {
            var $list = $('#zk-issues-list');

            if (!issues || issues.length === 0) {
                $list.html('<div class="zk-empty">Keine Ausgaben gefunden.</div>');
                return;
            }

            var html = '<div class="zk-issues-grid">';

            issues.forEach(function(issue) {
                html += '<div class="zk-issue-item" data-id="' + issue.id + '">';

                // Thumbnail
                html += '<div class="zk-issue-thumb">';
                if (issue.thumbnail) {
                    html += '<img src="' + issue.thumbnail + '" alt="' + ZKManager.escapeHtml(issue.label) + '">';
                } else {
                    html += '<div class="zk-issue-placeholder">';
                    html += '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>';
                    html += '<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>';
                    html += '</svg>';
                    html += '</div>';
                }
                html += '</div>';

                // Info
                html += '<div class="zk-issue-info">';
                html += '<div class="zk-issue-header">';
                html += '<span class="zk-issue-title">' + ZKManager.escapeHtml(issue.title) + '</span>';
                html += '<span class="zk-issue-label">' + ZKManager.escapeHtml(issue.label) + '</span>';
                html += '</div>';

                // Meta
                html += '<div class="zk-issue-meta">';
                html += '<span class="zk-issue-status zk-status-' + issue.status + '">' + issue.status_label + '</span>';
                html += '<span class="zk-issue-date">Veröffentlichung: ' + issue.verfuegbar_ab_formatted + '</span>';
                html += '<span class="zk-issue-articles">' + issue.article_count + ' Artikel</span>';
                html += '</div>';

                // Actions
                html += '<div class="zk-issue-actions">';
                if (!issue.is_visible) {
                    html += '<button type="button" class="zk-btn zk-btn-success zk-btn-small zk-issue-publish" title="Jetzt veröffentlichen">';
                    html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<polyline points="20 6 9 17 4 12"></polyline>';
                    html += '</svg>';
                    html += '</button>';
                }
                html += '<button type="button" class="zk-btn zk-btn-secondary zk-btn-small zk-issue-edit" title="Bearbeiten">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>';
                html += '<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>';
                html += '</svg>';
                html += '</button>';
                html += '<button type="button" class="zk-btn zk-btn-danger zk-btn-small zk-issue-delete" title="Löschen">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<polyline points="3 6 5 6 21 6"></polyline>';
                html += '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>';
                html += '</svg>';
                html += '</button>';
                html += '</div>';

                html += '</div>'; // .zk-issue-info
                html += '</div>'; // .zk-issue-item
            });

            html += '</div>';
            $list.html(html);
        },

        /**
         * Neue Ausgabe Modal öffnen
         */
        openNewModal: function() {
            // Formular zurücksetzen
            $('#zk-new-issue-form')[0].reset();
            $('#zk-new-jahr').val(new Date().getFullYear());

            // Nächste Ausgabe-Nummer ermitteln
            var currentYear = new Date().getFullYear();
            var maxAusgabe = 0;

            $('.zk-issue-item').each(function() {
                var label = $(this).find('.zk-issue-label').text();
                var match = label.match(/(\d{4})\/(\d+)/);
                if (match && parseInt(match[1]) === currentYear) {
                    maxAusgabe = Math.max(maxAusgabe, parseInt(match[2]));
                }
            });

            $('#zk-new-ausgabe').val(maxAusgabe + 1);

            $('#zk-modal-new').addClass('zk-modal-open');
        },

        /**
         * Edit Modal öffnen
         */
        openEditModal: function(issueId) {
            var self = this;
            var $modal = $('#zk-modal-edit');

            // Lade-Animation im Modal
            $modal.find('.zk-modal-body').addClass('zk-loading-overlay');
            $modal.addClass('zk-modal-open');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_issue_details',
                    nonce: this.config.nonce,
                    post_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        self.populateEditForm(response.data);
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Laden');
                        self.closeModals();
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                    self.closeModals();
                },
                complete: function() {
                    $modal.find('.zk-modal-body').removeClass('zk-loading-overlay');
                }
            });
        },

        /**
         * Edit-Formular befüllen
         */
        populateEditForm: function(data) {
            var self = this;
            var issue = data.issue;
            var articles = data.articles;

            $('#zk-edit-id').val(issue.id);
            $('#zk-edit-jahr').val(issue.jahr);
            $('#zk-edit-ausgabe').val(issue.ausgabe);
            $('#zk-edit-title').val(issue.title);
            $('#zk-edit-doi').val(issue.doi);
            $('#zk-edit-date').val(issue.verfuegbar_ab);

            // Artikel-Slots definieren
            var slots = [
                { key: 'editorial', label: 'Editorial' },
                { key: 'journalclub', label: 'Journal Club' },
                { key: 'tutorial', label: 'Tutorial' },
                { key: 'pub1', label: 'Fachartikel 1' },
                { key: 'pub2', label: 'Fachartikel 2' },
                { key: 'pub3', label: 'Fachartikel 3' },
                { key: 'pub4', label: 'Fachartikel 4' },
                { key: 'pub5', label: 'Fachartikel 5' },
                { key: 'pub6', label: 'Fachartikel 6' }
            ];

            // Artikel-Mapping erstellen
            var articlesMap = {};
            if (articles && articles.length > 0) {
                articles.forEach(function(article) {
                    articlesMap[article.slot] = article;
                });
            }

            // Verknüpfte Artikel mit Slots anzeigen
            var $articlesList = $('#zk-linked-articles');
            var html = '<div class="zk-slots-list">';

            slots.forEach(function(slot) {
                var article = articlesMap[slot.key];
                html += '<div class="zk-slot-item" data-slot="' + slot.key + '">';
                html += '<div class="zk-slot-header">';
                html += '<span class="zk-slot-label">' + slot.label + '</span>';
                html += '</div>';

                if (article) {
                    html += '<div class="zk-slot-content zk-slot-filled">';
                    html += '<span class="zk-slot-title">' + self.escapeHtml(article.title) + '</span>';
                    if (article.authors) {
                        html += '<span class="zk-slot-authors">' + self.escapeHtml(article.authors) + '</span>';
                    }
                    html += '<button type="button" class="zk-btn zk-btn-danger zk-btn-mini zk-unlink-article-btn" data-slot="' + slot.key + '" title="Verknüpfung entfernen">';
                    html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<line x1="18" y1="6" x2="6" y2="18"></line>';
                    html += '<line x1="6" y1="6" x2="18" y2="18"></line>';
                    html += '</svg>';
                    html += '</button>';
                    html += '</div>';
                } else {
                    html += '<div class="zk-slot-content zk-slot-empty">';
                    html += '<button type="button" class="zk-btn zk-btn-secondary zk-btn-small zk-link-article-btn" data-slot="' + slot.key + '">';
                    html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<line x1="12" y1="5" x2="12" y2="19"></line>';
                    html += '<line x1="5" y1="12" x2="19" y2="12"></line>';
                    html += '</svg>';
                    html += 'Artikel verknüpfen';
                    html += '</button>';
                    html += '</div>';
                }

                html += '</div>';
            });

            html += '</div>';
            $articlesList.html(html);
        },

        /**
         * Modals schließen
         */
        closeModals: function() {
            // TinyMCE Editoren entfernen
            if (typeof tinymce !== 'undefined') {
                if (tinymce.get('zk-article-content')) {
                    tinymce.get('zk-article-content').remove();
                }
                if (tinymce.get('zk-edit-article-content')) {
                    tinymce.get('zk-edit-article-content').remove();
                }
            }
            $('.zk-modal').removeClass('zk-modal-open');
        },

        /**
         * Neue Ausgabe erstellen
         */
        createIssue: function() {
            var self = this;
            var $btn = $('#zk-save-new-issue');
            var $form = $('#zk-new-issue-form');

            var data = {
                action: 'zk_create_issue',
                nonce: this.config.nonce,
                jahr: $form.find('[name="jahr"]').val(),
                ausgabe: $form.find('[name="ausgabe"]').val(),
                title: $form.find('[name="title"]').val(),
                doi: $form.find('[name="doi"]').val(),
                verfuegbar_ab: $form.find('[name="verfuegbar_ab"]').val()
            };

            $btn.prop('disabled', true).text('Erstellen...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Ausgabe "' + response.data.title + '" erstellt');
                        self.closeModals();
                        self.loadIssues();
                        self.loadYearFilter();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Erstellen');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Ausgabe erstellen');
                }
            });
        },

        /**
         * Ausgabe aktualisieren
         */
        updateIssue: function() {
            var self = this;
            var $btn = $('#zk-save-edit-issue');
            var $form = $('#zk-edit-issue-form');

            var data = {
                action: 'zk_update_issue',
                nonce: this.config.nonce,
                post_id: $form.find('[name="post_id"]').val(),
                jahr: $form.find('[name="jahr"]').val(),
                ausgabe: $form.find('[name="ausgabe"]').val(),
                title: $form.find('[name="title"]').val(),
                doi: $form.find('[name="doi"]').val(),
                verfuegbar_ab: $form.find('[name="verfuegbar_ab"]').val()
            };

            $btn.prop('disabled', true).text('Speichern...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Ausgabe aktualisiert');
                        self.closeModals();
                        self.loadIssues();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Speichern');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Speichern');
                }
            });
        },

        /**
         * Ausgabe löschen
         */
        deleteIssue: function(issueId, title) {
            var self = this;

            if (!confirm('Ausgabe "' + title + '" wirklich löschen?\n\nDie Ausgabe wird in den Papierkorb verschoben.')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_delete_issue',
                    nonce: this.config.nonce,
                    post_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Ausgabe gelöscht');
                        self.loadIssues();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Löschen');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Jetzt veröffentlichen
         */
        publishNow: function(issueId) {
            var self = this;

            if (!confirm('Ausgabe jetzt veröffentlichen?')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_publish_now',
                    nonce: this.config.nonce,
                    post_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Ausgabe veröffentlicht');
                        self.loadIssues();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Akzeptierte Artikel laden
         */
        loadAcceptedArticles: function() {
            var self = this;
            var $list = $('#zk-accepted-list');

            $list.html(this.getLoadingHtml('Lade Artikel...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_accepted_articles',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderAcceptedArticles(response.data.articles);
                    } else {
                        $list.html(self.getErrorHtml(response.data.message || 'Fehler beim Laden'));
                    }
                },
                error: function() {
                    $list.html(self.getErrorHtml('Verbindungsfehler'));
                }
            });
        },

        /**
         * Akzeptierte Artikel rendern
         */
        renderAcceptedArticles: function(articles) {
            var $list = $('#zk-accepted-list');

            if (!articles || articles.length === 0) {
                $list.html('<div class="zk-empty">Keine akzeptierten Artikel vorhanden.</div>');
                return;
            }

            var html = '<div class="zk-accepted-grid">';

            articles.forEach(function(article) {
                html += '<div class="zk-accepted-item" data-id="' + article.id + '">';
                html += '<div class="zk-accepted-info">';
                html += '<span class="zk-accepted-title">' + ZKManager.escapeHtml(article.title) + '</span>';
                html += '<div class="zk-accepted-meta">';
                if (article.author) {
                    html += '<span>Autor: ' + ZKManager.escapeHtml(article.author) + '</span>';
                }
                if (article.publikationsart) {
                    html += '<span>Art: ' + ZKManager.escapeHtml(article.publikationsart) + '</span>';
                }
                html += '<span>ID: ' + article.submission_id + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
            $list.html(html);
        },

        // ============================================
        // ARTIKEL-VERWALTUNG
        // ============================================

        /**
         * Alle Artikel laden
         */
        loadArticles: function() {
            var self = this;
            var $list = $('#zk-articles-list');
            var search = $('#zk-article-search').val() || '';
            var sortBy = $('#zk-article-sort').val() || 'date';
            var sortOrder = $('#zk-article-order').val() || 'DESC';

            $list.html(this.getLoadingHtml('Lade Artikel...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_all_articles',
                    nonce: this.config.nonce,
                    search: search,
                    sort_by: sortBy,
                    sort_order: sortOrder
                },
                success: function(response) {
                    if (response.success) {
                        self.renderArticles(response.data.articles);
                    } else {
                        $list.html(self.getErrorHtml(response.data.message || 'Fehler beim Laden'));
                    }
                },
                error: function() {
                    $list.html(self.getErrorHtml('Verbindungsfehler'));
                }
            });
        },

        /**
         * Artikel-Liste rendern
         */
        renderArticles: function(articles) {
            var $list = $('#zk-articles-list');

            if (!articles || articles.length === 0) {
                $list.html('<div class="zk-empty">Keine Artikel gefunden.</div>');
                return;
            }

            var html = '<div class="zk-articles-grid">';

            articles.forEach(function(article) {
                html += '<div class="zk-article-item" data-id="' + article.id + '">';
                html += '<div class="zk-article-info">';

                // Titel mit Status-Badge
                html += '<div class="zk-article-header">';
                html += '<span class="zk-article-title">' + ZKManager.escapeHtml(article.title) + '</span>';
                if (article.status && article.status !== 'publish') {
                    html += '<span class="zk-article-status zk-status-' + article.status + '">' + ZKManager.escapeHtml(article.status_label || article.status) + '</span>';
                }
                html += '</div>';

                html += '<div class="zk-article-meta">';
                if (article.authors) {
                    html += '<span class="zk-article-authors">' + ZKManager.escapeHtml(article.authors) + '</span>';
                }

                // Datum anzeigen
                if (article.date) {
                    html += '<span class="zk-article-date">Erstellt: ' + ZKManager.escapeHtml(article.date) + '</span>';
                }
                if (article.modified && article.modified !== article.date) {
                    html += '<span class="zk-article-modified">Geändert: ' + ZKManager.escapeHtml(article.modified) + '</span>';
                }

                if (article.doi) {
                    html += '<span class="zk-article-doi">DOI: ' + ZKManager.escapeHtml(article.doi) + '</span>';
                }
                if (article.linked_issue) {
                    html += '<span class="zk-article-linked">→ ' + ZKManager.escapeHtml(article.linked_issue.label) + ' (' + article.linked_issue.field + ')</span>';
                } else {
                    html += '<span class="zk-article-unlinked">Nicht verknüpft</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '<div class="zk-article-actions">';
                html += '<button type="button" class="zk-btn zk-btn-secondary zk-btn-small zk-article-edit" title="Bearbeiten">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                html += '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>';
                html += '<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>';
                html += '</svg>';
                html += '</button>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
            $list.html(html);
        },

        /**
         * Neuer Artikel Modal öffnen
         */
        openNewArticleModal: function() {
            $('#zk-new-article-form')[0].reset();
            $('#zk-modal-new-article').addClass('zk-modal-open');

            // TinyMCE initialisieren
            this.initTinyMCE('zk-article-content');
        },

        /**
         * TinyMCE initialisieren
         */
        initTinyMCE: function(editorId) {
            var self = this;

            // Vorhandenen Editor entfernen
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).remove();
            }

            // Kurze Verzögerung für DOM-Rendering
            setTimeout(function() {
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.initialize(editorId, {
                        tinymce: {
                            wpautop: true,
                            plugins: 'lists link paste',
                            toolbar1: 'bold italic | bullist numlist | link | removeformat',
                            height: 250,
                            menubar: false,
                            statusbar: false
                        },
                        quicktags: false,
                        mediaButtons: false
                    });
                }
            }, 100);
        },

        /**
         * TinyMCE Inhalt abrufen
         */
        getTinyMCEContent: function(editorId) {
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                return tinymce.get(editorId).getContent();
            }
            return $('#' + editorId).val();
        },

        /**
         * TinyMCE Inhalt setzen
         */
        setTinyMCEContent: function(editorId, content) {
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).setContent(content || '');
            } else {
                $('#' + editorId).val(content || '');
            }
        },

        /**
         * Artikel erstellen
         */
        createArticle: function() {
            var self = this;
            var $btn = $('#zk-save-new-article');
            var $form = $('#zk-new-article-form');

            var title = $form.find('[name="title"]').val();
            if (!title) {
                self.showToast('error', 'Bitte Titel eingeben');
                return;
            }

            var data = {
                action: 'zk_create_article',
                nonce: this.config.nonce,
                title: title,
                unterueberschrift: $form.find('[name="unterueberschrift"]').val(),
                publikationsart: $form.find('[name="publikationsart"]').val(),
                doi: $form.find('[name="doi"]').val(),
                kardiotechnikausgabe: $form.find('[name="kardiotechnikausgabe"]').val(),
                supplement: $form.find('[name="supplement"]').val(),
                autoren: $form.find('[name="autoren"]').val(),
                hauptautorin: $form.find('[name="hauptautorin"]').val(),
                content: this.getTinyMCEContent('zk-article-content'),
                literatur: $form.find('[name="literatur"]').val(),
                abstract_deutsch: $form.find('[name="abstract_deutsch"]').val(),
                abstract: $form.find('[name="abstract"]').val(),
                keywords_deutsch: $form.find('[name="keywords_deutsch"]').val(),
                keywords_englisch: $form.find('[name="keywords_englisch"]').val(),
                volltext_anzeigen: $form.find('[name="volltext_anzeigen"]').is(':checked') ? '1' : ''
            };

            $btn.prop('disabled', true).text('Erstellen...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Artikel erstellt');
                        self.closeModals();
                        self.loadArticles();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Erstellen');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Artikel erstellen');
                }
            });
        },

        /**
         * Edit Artikel Modal öffnen
         */
        openEditArticleModal: function(articleId) {
            var self = this;
            var $modal = $('#zk-modal-edit-article');

            $modal.find('.zk-modal-body').addClass('zk-loading-overlay');
            $modal.addClass('zk-modal-open');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_article_details',
                    nonce: this.config.nonce,
                    article_id: articleId
                },
                success: function(response) {
                    if (response.success) {
                        self.populateEditArticleForm(response.data.article);
                        // TinyMCE initialisieren nach Formular-Befüllung
                        self.initTinyMCE('zk-edit-article-content');
                        // Inhalt nach TinyMCE-Init setzen
                        setTimeout(function() {
                            self.setTinyMCEContent('zk-edit-article-content', response.data.article.content || '');
                        }, 200);
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Laden');
                        self.closeModals();
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                    self.closeModals();
                },
                complete: function() {
                    $modal.find('.zk-modal-body').removeClass('zk-loading-overlay');
                }
            });
        },

        /**
         * Edit Artikel Formular befüllen
         */
        populateEditArticleForm: function(article) {
            var $form = $('#zk-edit-article-form');

            console.log('ZK Populate Form - Full article data:', article);

            // Grunddaten
            $form.find('#zk-edit-article-id').val(article.id);
            $form.find('#zk-edit-article-title').val(article.title);
            $form.find('#zk-edit-article-subtitle').val(article.unterueberschrift || '');
            $form.find('#zk-edit-article-doi').val(article.doi || '');
            $form.find('#zk-edit-article-kardiotechnik').val(article.kardiotechnikausgabe || '');
            $form.find('#zk-edit-article-supplement').val(article.supplement || '');

            // Publikationsart setzen
            var $typeSelect = $form.find('#zk-edit-article-type');
            var pubArt = article.publikationsart || '';
            console.log('ZK Setting publikationsart to:', pubArt);
            $typeSelect.val(pubArt);

            // Autoren
            $form.find('#zk-edit-article-authors').val(article.autoren || '');
            $form.find('#zk-edit-article-main-author').val(article.hauptautorin || '');

            // Inhalt
            $form.find('#zk-edit-article-content').val(article.content || '');
            $form.find('#zk-edit-article-literatur').val(article.literatur || '');

            // Abstracts & Keywords
            $form.find('#zk-edit-article-abstract').val(article.abstract_deutsch || '');
            $form.find('#zk-edit-article-abstract-en').val(article.abstract || '');
            $form.find('#zk-edit-article-keywords').val(article.keywords_deutsch || '');
            $form.find('#zk-edit-article-keywords-en').val(article.keywords_englisch || '');

            // Optionen
            $form.find('#zk-edit-article-fulltext').prop('checked', article.volltext_anzeigen === '1' || article.volltext_anzeigen === 1);

            // Verknüpfte Ausgabe anzeigen
            var $linkedIssue = $('#zk-article-linked-issue');
            if (article.linked_issue) {
                $linkedIssue.html(
                    '<span class="zk-linked-badge">' + this.escapeHtml(article.linked_issue.label) +
                    ' (' + article.linked_issue.field + ')</span>'
                );
            } else {
                $linkedIssue.html('<span class="zk-no-link">Nicht verknüpft</span>');
            }
        },

        /**
         * Artikel aktualisieren
         */
        updateArticle: function() {
            var self = this;
            var $btn = $('#zk-save-edit-article');
            var $form = $('#zk-edit-article-form');

            var articleId = $form.find('#zk-edit-article-id').val();
            var title = $form.find('#zk-edit-article-title').val();

            if (!title) {
                self.showToast('error', 'Bitte Titel eingeben');
                return;
            }

            var data = {
                action: 'zk_update_article',
                nonce: this.config.nonce,
                article_id: articleId,
                title: title,
                unterueberschrift: $form.find('#zk-edit-article-subtitle').val(),
                publikationsart: $form.find('#zk-edit-article-type').val(),
                doi: $form.find('#zk-edit-article-doi').val(),
                kardiotechnikausgabe: $form.find('#zk-edit-article-kardiotechnik').val(),
                supplement: $form.find('#zk-edit-article-supplement').val(),
                autoren: $form.find('#zk-edit-article-authors').val(),
                hauptautorin: $form.find('#zk-edit-article-main-author').val(),
                content: this.getTinyMCEContent('zk-edit-article-content'),
                literatur: $form.find('#zk-edit-article-literatur').val(),
                abstract_deutsch: $form.find('#zk-edit-article-abstract').val(),
                abstract: $form.find('#zk-edit-article-abstract-en').val(),
                keywords_deutsch: $form.find('#zk-edit-article-keywords').val(),
                keywords_englisch: $form.find('#zk-edit-article-keywords-en').val(),
                volltext_anzeigen: $form.find('#zk-edit-article-fulltext').is(':checked') ? '1' : ''
            };

            $btn.prop('disabled', true).text('Speichern...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Artikel aktualisiert');
                        self.closeModals();
                        self.loadArticles();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Speichern');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Speichern');
                }
            });
        },

        /**
         * Artikel löschen
         */
        deleteArticle: function() {
            var self = this;
            var $form = $('#zk-edit-article-form');
            var articleId = $form.find('#zk-edit-article-id').val();
            var title = $form.find('#zk-edit-article-title').val();

            if (!confirm('Artikel "' + title + '" wirklich löschen?\n\nDer Artikel wird in den Papierkorb verschoben.')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_delete_article',
                    nonce: this.config.nonce,
                    article_id: articleId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Artikel gelöscht');
                        self.closeModals();
                        self.loadArticles();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Löschen');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        // ============================================
        // ARTIKEL-VERKNÜPFUNG
        // ============================================

        /**
         * Link Artikel Modal öffnen
         */
        openLinkArticleModal: function(issueId, slot) {
            var slotLabels = {
                'editorial': 'Editorial',
                'journalclub': 'Journal Club',
                'tutorial': 'Tutorial',
                'pub1': 'Fachartikel 1',
                'pub2': 'Fachartikel 2',
                'pub3': 'Fachartikel 3',
                'pub4': 'Fachartikel 4',
                'pub5': 'Fachartikel 5',
                'pub6': 'Fachartikel 6'
            };

            $('#zk-link-issue-id').val(issueId);
            $('#zk-link-slot').val(slot);
            $('#zk-link-article-search').val('');
            $('#zk-modal-link-article').find('.zk-modal-header h3').text('Artikel für "' + (slotLabels[slot] || slot) + '" auswählen');
            $('#zk-modal-link-article').addClass('zk-modal-open');

            this.loadAvailableArticles();
        },

        /**
         * Verfügbare Artikel laden
         */
        loadAvailableArticles: function() {
            var self = this;
            var $list = $('#zk-available-articles');
            var search = $('#zk-link-article-search').val() || '';

            $list.html(this.getLoadingHtml('Lade verfügbare Artikel...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_available_articles',
                    nonce: this.config.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        self.renderAvailableArticles(response.data.articles);
                    } else {
                        $list.html(self.getErrorHtml(response.data.message || 'Fehler beim Laden'));
                    }
                },
                error: function() {
                    $list.html(self.getErrorHtml('Verbindungsfehler'));
                }
            });
        },

        /**
         * Verfügbare Artikel rendern
         */
        renderAvailableArticles: function(articles) {
            var $list = $('#zk-available-articles');

            if (!articles || articles.length === 0) {
                $list.html('<div class="zk-empty">Keine verfügbaren Artikel gefunden.</div>');
                return;
            }

            var html = '<div class="zk-available-articles-list">';

            articles.forEach(function(article) {
                html += '<div class="zk-available-article-item" data-id="' + article.id + '">';
                html += '<span class="zk-available-title">' + ZKManager.escapeHtml(article.title) + '</span>';
                if (article.authors) {
                    html += '<span class="zk-available-authors">' + ZKManager.escapeHtml(article.authors) + '</span>';
                }
                html += '</div>';
            });

            html += '</div>';
            $list.html(html);
        },

        /**
         * Artikel mit Ausgabe verknüpfen
         */
        linkArticle: function(articleId) {
            var self = this;
            var issueId = $('#zk-link-issue-id').val();
            var slot = $('#zk-link-slot').val();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_link_article',
                    nonce: this.config.nonce,
                    issue_id: issueId,
                    article_id: articleId,
                    slot: slot
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Artikel verknüpft');
                        $('#zk-modal-link-article').removeClass('zk-modal-open');
                        // Issue Edit Modal aktualisieren
                        self.openEditModal(issueId);
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Verknüpfen');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Artikel von Ausgabe entknüpfen
         */
        unlinkArticle: function(issueId, slot) {
            var self = this;

            if (!confirm('Artikel-Verknüpfung wirklich entfernen?')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_unlink_article',
                    nonce: this.config.nonce,
                    issue_id: issueId,
                    slot: slot
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', 'Verknüpfung entfernt');
                        // Issue Edit Modal aktualisieren
                        self.openEditModal(issueId);
                    } else {
                        self.showToast('error', response.data.message || 'Fehler');
                    }
                },
                error: function() {
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Toast-Benachrichtigung anzeigen
         */
        showToast: function(type, message) {
            var $container = $('#zk-toast-container');
            var $toast = $('<div class="zk-toast zk-toast-' + type + '">' +
                '<span class="zk-toast-message">' + this.escapeHtml(message) + '</span>' +
                '<button type="button" class="zk-toast-close">&times;</button>' +
                '</div>');

            $container.append($toast);

            // Einblenden
            setTimeout(function() {
                $toast.addClass('zk-toast-visible');
            }, 10);

            // Automatisch ausblenden
            setTimeout(function() {
                $toast.removeClass('zk-toast-visible');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 4000);

            // Manuelles Schließen
            $toast.find('.zk-toast-close').on('click', function() {
                $toast.removeClass('zk-toast-visible');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            });
        },

        /**
         * Lade-HTML
         */
        getLoadingHtml: function(message) {
            return '<div class="zk-loading">' +
                '<div class="zk-spinner"></div>' +
                '<span>' + message + '</span>' +
                '</div>';
        },

        /**
         * Fehler-HTML
         */
        getErrorHtml: function(message) {
            return '<div class="zk-error-message">' +
                '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<circle cx="12" cy="12" r="10"></circle>' +
                '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
                '</svg>' +
                '<span>' + message + '</span>' +
                '</div>';
        },

        /**
         * HTML escapen
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ============================================
        // PDF-IMPORT (Komplette Ausgaben)
        // ============================================

        pdfImport: {
            importId: null,
            filename: null,
            issue: null,
            articles: [],
            coverUrl: null
        },

        /**
         * PDF Import Events binden
         */
        bindPdfImportEvents: function() {
            var self = this;

            // PDF Tab aktivieren
            $(document).on('click', '.zk-tab[data-tab="pdf-import"]', function() {
                self.resetPdfImport();
            });

            // Durchsuchen Button
            $(document).on('click', '#zk-pdf-browse', function(e) {
                e.preventDefault();
                $('#zk-pdf-file').click();
            });

            // Datei-Input
            $(document).on('change', '#zk-pdf-file', function() {
                if (this.files && this.files[0]) {
                    self.uploadPdf(this.files[0]);
                }
            });

            // Drag & Drop
            var $dropzone = $('#zk-pdf-dropzone');

            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('zk-dragover');
            });

            $dropzone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('zk-dragover');
            });

            $dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('zk-dragover');

                var files = e.originalEvent.dataTransfer.files;
                if (files && files[0]) {
                    self.uploadPdf(files[0]);
                }
            });

            // PDF entfernen
            $(document).on('click', '#zk-pdf-remove', function(e) {
                e.preventDefault();
                self.discardImport();
            });

            // KI-Extraktion & Analyse (kombiniert)
            $(document).on('click', '#zk-extract-btn', function(e) {
                e.preventDefault();
                self.extractAndAnalyzeIssue();
            });

            // KI-Einstellungen öffnen
            $(document).on('click', '#zk-ai-settings-btn', function(e) {
                e.preventDefault();
                self.openAiSettings();
            });

            // KI-Einstellungen speichern
            $(document).on('click', '#zk-save-ai-settings', function(e) {
                e.preventDefault();
                self.saveAiSettings();
            });

            // Import speichern
            $(document).on('click', '#zk-import-save', function(e) {
                e.preventDefault();
                self.saveIssueImport();
            });

            // Import verwerfen
            $(document).on('click', '#zk-import-discard', function(e) {
                e.preventDefault();
                self.discardImport();
            });

            // Artikel-Bearbeitung Toggle
            $(document).on('click', '.zk-article-preview-toggle', function(e) {
                e.preventDefault();
                $(this).closest('.zk-article-preview').toggleClass('zk-expanded');
            });
        },

        /**
         * PDF hochladen
         */
        uploadPdf: function(file) {
            var self = this;

            if (file.type !== 'application/pdf') {
                this.showToast('error', 'Nur PDF-Dateien erlaubt');
                return;
            }

            var formData = new FormData();
            formData.append('pdf', file);
            formData.append('action', 'zk_upload_pdf');
            formData.append('nonce', this.config.nonce);

            var $dropzone = $('#zk-pdf-dropzone');
            var $progress = $dropzone.find('.zk-upload-progress');
            var $content = $dropzone.find('.zk-upload-content');

            $content.hide();
            $progress.show();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = (e.loaded / e.total) * 100;
                            $progress.find('.zk-progress-fill').css('width', percent + '%');
                            $progress.find('.zk-progress-text').text('Hochladen... ' + Math.round(percent) + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $progress.hide();
                    $content.show();
                    $progress.find('.zk-progress-fill').css('width', '0');

                    if (response.success) {
                        self.pdfImport.importId = response.data.import_id;
                        self.pdfImport.filename = response.data.filename;
                        $('#zk-import-id').val(response.data.import_id);

                        self.showPdfInfo(response.data);
                        self.activateStep(2);
                        $('#zk-extract-btn').prop('disabled', false);
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Hochladen');
                    }
                },
                error: function() {
                    $progress.hide();
                    $content.show();
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * PDF-Info anzeigen
         */
        showPdfInfo: function(data) {
            var $info = $('#zk-pdf-info');
            $info.find('.zk-file-name').text(data.filename);
            $info.find('.zk-file-size').text(this.formatFileSize(data.size));
            $info.show();
        },

        /**
         * Dateigröße formatieren
         */
        formatFileSize: function(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
            return Math.round(bytes / 1024 / 1024 * 10) / 10 + ' MB';
        },

        /**
         * KI-Extraktion & Analyse (kombiniert)
         * Extrahiert Cover, Text, Bilder UND analysiert mit KI in einem Schritt
         */
        extractAndAnalyzeIssue: function() {
            var self = this;

            if (!this.pdfImport.importId) {
                this.showToast('error', 'Kein PDF hochgeladen');
                return;
            }

            var $status = $('#zk-import-step-2 .zk-extraction-status');
            var $statusText = $('#zk-extraction-status-text');

            $status.show();
            $statusText.text('Extrahiere und analysiere... (kann 2-3 Minuten dauern)');
            $('#zk-extract-btn').prop('disabled', true);

            console.log('=== ZK KI-Extraktion Start ===');
            console.log('Import-ID:', this.pdfImport.importId);
            console.log('AJAX URL:', this.config.ajaxUrl);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                timeout: 300000, // 5 Minuten für Extraktion + KI-Analyse
                data: {
                    action: 'zk_extract_issue',
                    nonce: this.config.nonce,
                    import_id: this.pdfImport.importId
                },
                success: function(response) {
                    console.log('=== ZK KI-Extraktion Antwort ===');
                    console.log('Response:', response);

                    $status.hide();
                    $('#zk-extract-btn').prop('disabled', false);

                    if (response.success) {
                        console.log('Erfolg! Issue:', response.data.issue);
                        console.log('Artikel:', response.data.articles);

                        // Cover-URL speichern
                        self.pdfImport.coverUrl = response.data.cover_url;

                        // Issue und Artikel-Daten speichern
                        self.pdfImport.issue = response.data.issue;
                        self.pdfImport.articles = response.data.articles;

                        // Direkt zur Vorschau (Schritt 3)
                        self.populateIssuePreview(response.data);
                        self.activateStep(3);
                        $('#zk-import-step-3 .zk-import-preview').show();

                        self.showToast('success', 'Extraktion und Analyse abgeschlossen');
                    } else {
                        console.error('Fehler:', response.data);

                        if (response.data.need_config) {
                            self.showToast('error', 'Bitte KI-Einstellungen konfigurieren');
                            self.openAiSettings();
                        } else {
                            self.showToast('error', response.data.message || 'Fehler bei der KI-Extraktion');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== ZK KI-Extraktion AJAX Fehler ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);

                    $status.hide();
                    $('#zk-extract-btn').prop('disabled', false);

                    if (status === 'timeout') {
                        self.showToast('error', 'Timeout - bitte erneut versuchen');
                    } else {
                        self.showToast('error', 'Verbindungsfehler: ' + error);
                    }
                }
            });
        },

        /**
         * Ausgabe-Vorschau befüllen
         */
        populateIssuePreview: function(data) {
            var self = this;

            // Ausgabe-Daten
            if (data.issue) {
                $('#zk-issue-jahr').val(data.issue.jahr || '');
                $('#zk-issue-ausgabe').val(data.issue.ausgabe || '');
                $('#zk-issue-doi').val(data.issue.doi || '');
            }

            // Titelseite
            if (this.pdfImport.coverUrl) {
                $('#zk-issue-cover img').attr('src', this.pdfImport.coverUrl);
            }

            // Artikel-Liste
            var $container = $('#zk-articles-preview');
            $container.empty();

            $('#zk-article-count').text(data.articles ? data.articles.length : 0);

            if (data.articles && data.articles.length > 0) {
                data.articles.forEach(function(article, index) {
                    var pdfLink = article.pdf_url
                        ? '<a href="' + article.pdf_url + '" target="_blank" class="zk-article-pdf-link">PDF</a>'
                        : '';

                    var html = '<div class="zk-article-preview" data-index="' + index + '">';
                    html += '<div class="zk-article-preview-header">';
                    html += '<button type="button" class="zk-article-preview-toggle">';
                    html += '<span class="zk-toggle-icon">▶</span>';
                    html += '</button>';
                    html += '<div class="zk-article-preview-info">';
                    html += '<span class="zk-article-preview-type">' + self.escapeHtml(article.publication_type || 'Artikel') + '</span>';
                    html += '<span class="zk-article-preview-title">' + self.escapeHtml(article.title || 'Ohne Titel') + '</span>';
                    html += '<span class="zk-article-preview-authors">' + self.escapeHtml(article.authors || '') + '</span>';
                    html += '</div>';
                    html += '<div class="zk-article-preview-meta">';
                    html += '<span class="zk-article-pages">S. ' + (article.start_page || '?') + '-' + (article.end_page || '?') + '</span>';
                    html += pdfLink;
                    html += '</div>';
                    html += '</div>';

                    // Bearbeitbarer Bereich
                    html += '<div class="zk-article-preview-body">';
                    html += '<div class="zk-form-row">';
                    html += '<div class="zk-form-group"><label>Titel</label>';
                    html += '<input type="text" class="zk-input zk-article-field" data-field="title" value="' + self.escapeHtml(article.title || '') + '"></div>';
                    html += '<div class="zk-form-group"><label>Typ</label>';
                    html += '<select class="zk-select zk-article-field" data-field="publication_type">';
                    html += '<option value="Fachartikel"' + (article.publication_type === 'Fachartikel' ? ' selected' : '') + '>Fachartikel</option>';
                    html += '<option value="Editorial"' + (article.publication_type === 'Editorial' ? ' selected' : '') + '>Editorial</option>';
                    html += '<option value="Journal Club"' + (article.publication_type === 'Journal Club' ? ' selected' : '') + '>Journal Club</option>';
                    html += '<option value="Tutorial"' + (article.publication_type === 'Tutorial' ? ' selected' : '') + '>Tutorial</option>';
                    html += '<option value="Fallbericht"' + (article.publication_type === 'Fallbericht' ? ' selected' : '') + '>Fallbericht</option>';
                    html += '<option value="Übersichtsarbeit"' + (article.publication_type === 'Übersichtsarbeit' ? ' selected' : '') + '>Übersichtsarbeit</option>';
                    html += '</select></div>';
                    html += '</div>';

                    html += '<div class="zk-form-group"><label>Autoren</label>';
                    html += '<input type="text" class="zk-input zk-article-field" data-field="authors" value="' + self.escapeHtml(article.authors || '') + '"></div>';

                    html += '<div class="zk-form-group"><label>Abstract (DE)</label>';
                    html += '<textarea class="zk-textarea zk-article-field" data-field="abstract_de" rows="3">' + self.escapeHtml(article.abstract_de || '') + '</textarea></div>';

                    html += '<div class="zk-form-group"><label>Keywords</label>';
                    html += '<input type="text" class="zk-input zk-article-field" data-field="keywords_de" value="' + self.escapeHtml(article.keywords_de || '') + '"></div>';

                    html += '</div></div>';

                    $container.append(html);
                });
            }
        },

        /**
         * Ausgabe-Import speichern
         */
        saveIssueImport: function() {
            var self = this;

            // Aktuelle Werte aus Formular sammeln
            var issue = {
                jahr: $('#zk-issue-jahr').val(),
                ausgabe: $('#zk-issue-ausgabe').val(),
                doi: $('#zk-issue-doi').val()
            };

            // Artikel-Daten aktualisieren
            var articles = [];
            $('.zk-article-preview').each(function() {
                var index = $(this).data('index');
                var article = $.extend({}, self.pdfImport.articles[index] || {});

                $(this).find('.zk-article-field').each(function() {
                    var field = $(this).data('field');
                    article[field] = $(this).val();
                });

                articles.push(article);
            });

            if (!issue.jahr || !issue.ausgabe) {
                this.showToast('error', 'Jahr und Ausgabe sind erforderlich');
                return;
            }

            var $btn = $('#zk-import-save');
            $btn.prop('disabled', true).text('Importiere...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_save_issue_import',
                    nonce: this.config.nonce,
                    import_id: this.pdfImport.importId,
                    issue: JSON.stringify(issue),
                    articles: JSON.stringify(articles)
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<polyline points="20 6 9 17 4 12"></polyline>' +
                        '</svg> Ausgabe importieren'
                    );

                    if (response.success) {
                        self.showToast('success', response.data.message);
                        self.resetPdfImport();

                        // Zur Ausgaben-Übersicht wechseln
                        self.switchTab('issues');
                        self.loadIssues();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler beim Import');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<polyline points="20 6 9 17 4 12"></polyline>' +
                        '</svg> Ausgabe importieren'
                    );
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Import verwerfen
         */
        discardImport: function() {
            var self = this;

            if (this.pdfImport.importId) {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'zk_discard_import',
                        nonce: this.config.nonce,
                        import_id: this.pdfImport.importId
                    }
                });
            }

            this.resetPdfImport();
            this.showToast('info', 'Import verworfen');
        },

        /**
         * KI-Einstellungen öffnen
         */
        openAiSettings: function() {
            var self = this;
            var $modal = $('#zk-modal-ai-settings');

            // Formular zurücksetzen
            $('#zk-ai-key').val('');

            console.log('=== ZK KI-Einstellungen laden ===');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_ai_settings',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Settings Response:', response);

                    if (response.success) {
                        $('#zk-ai-provider').val(response.data.provider || 'anthropic');
                        $('#zk-ai-model').val(response.data.model || 'claude-sonnet-4-20250514');

                        if (response.data.has_key) {
                            $('#zk-ai-key').attr('placeholder', response.data.api_key_masked);
                            $('#zk-ai-key-status').text('API-Key konfiguriert ✓').css('color', '#22c55e');
                        } else {
                            $('#zk-ai-key').attr('placeholder', 'API-Key eingeben (sk-ant-...)');
                            $('#zk-ai-key-status').text('⚠ Kein API-Key konfiguriert!').css('color', '#ef4444');
                        }

                        console.log('Provider:', response.data.provider);
                        console.log('Model:', response.data.model);
                        console.log('Has Key:', response.data.has_key);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Settings Load Error:', status, error);
                }
            });

            $modal.addClass('zk-modal-open');
        },

        /**
         * KI-Einstellungen speichern
         */
        saveAiSettings: function() {
            var self = this;
            var apiKey = $('#zk-ai-key').val();

            console.log('=== ZK KI-Einstellungen speichern ===');
            console.log('Provider:', $('#zk-ai-provider').val());
            console.log('Model:', $('#zk-ai-model').val());
            console.log('API-Key eingegeben:', apiKey ? 'ja (Länge: ' + apiKey.length + ')' : 'nein (wird beibehalten)');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_save_ai_settings',
                    nonce: this.config.nonce,
                    provider: $('#zk-ai-provider').val(),
                    model: $('#zk-ai-model').val(),
                    api_key: apiKey
                },
                success: function(response) {
                    console.log('Save Response:', response);
                    if (response.success) {
                        var msg = response.data.has_key
                            ? 'Einstellungen gespeichert (API-Key aktiv)'
                            : 'Einstellungen gespeichert (WARNUNG: Kein API-Key!)';
                        self.showToast(response.data.has_key ? 'success' : 'error', msg);
                        self.closeModals();
                    } else {
                        self.showToast('error', response.data.message || 'Fehler');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save Error:', status, error);
                    self.showToast('error', 'Verbindungsfehler');
                }
            });
        },

        /**
         * Import-Schritt aktivieren (3-Schritte-Workflow)
         */
        activateStep: function(step) {
            for (var i = 1; i <= 3; i++) {
                var $step = $('#zk-import-step-' + i);
                if (i <= step) {
                    $step.addClass('zk-import-step-active');
                } else {
                    $step.removeClass('zk-import-step-active');
                }
            }
        },

        /**
         * PDF-Import zurücksetzen (3-Schritte-Workflow)
         */
        resetPdfImport: function() {
            this.pdfImport = {
                importId: null,
                filename: null,
                issue: null,
                articles: [],
                coverUrl: null
            };

            $('#zk-import-id').val('');
            $('#zk-pdf-info').hide();
            $('#zk-pdf-file').val('');
            $('#zk-extract-btn').prop('disabled', true);

            // Schritt 2: Extraktion + Analyse Status
            $('#zk-import-step-2 .zk-extraction-status').hide();

            // Schritt 3: Import-Vorschau
            $('#zk-import-step-3 .zk-import-preview').hide();

            // Vorschau-Felder zurücksetzen
            $('#zk-issue-cover img').attr('src', '');
            $('#zk-issue-jahr, #zk-issue-ausgabe, #zk-issue-doi').val('');
            $('#zk-articles-preview').empty();

            this.activateStep(1);
        }
    };

    // Initialisierung
    $(document).ready(function() {
        if ($('#zk-manager').length > 0) {
            ZKManager.init();
            ZKManager.bindPdfImportEvents();
        }
    });

})(jQuery);
