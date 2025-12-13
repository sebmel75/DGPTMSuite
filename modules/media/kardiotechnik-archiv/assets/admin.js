/**
 * Kardiotechnik Archiv - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // AJAX-Handler für Artikel abrufen
        window.ktaGetArticle = function(articleId, callback) {
            $.post(ajaxurl, {
                action: 'kta_get_article',
                article_id: articleId,
                nonce: ktaAdmin.nonce
            }, function(response) {
                if (response.success && callback) {
                    callback(response.data);
                }
            });
        };

        // Artikel-Suche im Admin
        $('#kta-search-btn').on('click', function(e) {
            e.preventDefault();
            var searchTerm = $('#kta-search-articles').val();

            if (!searchTerm) {
                location.reload();
                return;
            }

            $.post(ajaxurl, {
                action: 'kta_search',
                search: searchTerm,
                year_from: 1975,
                year_to: new Date().getFullYear(),
                nonce: ktaAdmin.nonce
            }, function(response) {
                if (response.success) {
                    updateArticlesList(response.data.results);
                }
            });
        });

        function updateArticlesList(articles) {
            var $tbody = $('#kta-articles-list');
            $tbody.empty();

            if (articles.length === 0) {
                $tbody.append('<tr><td colspan="7">Keine Artikel gefunden.</td></tr>');
                return;
            }

            articles.forEach(function(article) {
                var pages = '';
                if (article.page_start && article.page_end) {
                    pages = article.page_start + '-' + article.page_end;
                }

                var row = '<tr>' +
                    '<td>' + article.id + '</td>' +
                    '<td>' + article.year + '</td>' +
                    '<td>' + article.issue + '</td>' +
                    '<td>' + escapeHtml(article.title) + '</td>' +
                    '<td>' + escapeHtml(article.author || '') + '</td>' +
                    '<td>' + pages + '</td>' +
                    '<td>' +
                    '<a href="' + article.pdf_url + '" target="_blank" class="button button-small">PDF anzeigen</a> ' +
                    '<button class="button button-small kta-edit-article" data-id="' + article.id + '">Bearbeiten</button> ' +
                    '<button class="button button-small button-link-delete kta-delete-article" data-id="' + article.id + '">Löschen</button>' +
                    '</td>' +
                    '</tr>';

                $tbody.append(row);
            });

            // Event-Handler neu binden
            bindArticleActions();
        }

        function bindArticleActions() {
            $('.kta-edit-article').off('click').on('click', function() {
                var articleId = $(this).data('id');
                ktaGetArticle(articleId, function(article) {
                    $('#edit-article-id').val(article.id);
                    $('#edit-title').val(article.title);
                    $('#edit-author').val(article.author);
                    $('#edit-keywords').val(article.keywords);
                    $('#edit-abstract').val(article.abstract);
                    $('#edit-page-start').val(article.page_start);
                    $('#edit-page-end').val(article.page_end);
                    $('#kta-edit-modal').show();
                });
            });

            $('.kta-delete-article').off('click').on('click', function() {
                if (!confirm('Möchten Sie diesen Artikel wirklich löschen?')) {
                    return;
                }

                var articleId = $(this).data('id');
                var $row = $(this).closest('tr');

                $.post(ajaxurl, {
                    action: 'kta_delete_article',
                    article_id: articleId,
                    nonce: ktaAdmin.nonce
                }, function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    }
                });
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Enter-Taste im Suchfeld
        $('#kta-search-articles').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#kta-search-btn').click();
            }
        });

    });

})(jQuery);
