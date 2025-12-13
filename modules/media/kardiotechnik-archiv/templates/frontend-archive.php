<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'kardiotechnik_articles';

// Jahresbereich ermitteln
$year_range = $wpdb->get_row("SELECT MIN(year) as min_year, MAX(year) as max_year FROM $table_name");
$min_year = $year_range ? $year_range->min_year : 1975;
$max_year = $year_range ? $year_range->max_year : date('Y');
?>

<div class="kta-archive-container">
    <div class="kta-search-box">
        <h2>Kardiotechnik Archiv durchsuchen</h2>

        <form id="kta-search-form" class="kta-search-form">
            <div class="kta-search-row">
                <div class="kta-search-field kta-search-full">
                    <label for="kta-search-input">Suchbegriff</label>
                    <input type="text" id="kta-search-input" name="search"
                           placeholder="Titel, Autor, Schlagwörter oder Text durchsuchen..." />
                </div>
            </div>

            <div class="kta-search-row">
                <div class="kta-search-field">
                    <label for="kta-year-from">Von Jahr</label>
                    <select id="kta-year-from" name="year_from">
                        <?php for ($year = $min_year; $year <= $max_year; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php selected($year, $min_year); ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="kta-search-field">
                    <label for="kta-year-to">Bis Jahr</label>
                    <select id="kta-year-to" name="year_to">
                        <?php for ($year = $min_year; $year <= $max_year; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php selected($year, $max_year); ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="kta-search-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="kta-search-button">
                        <span class="dashicons dashicons-search"></span> Suchen
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div id="kta-loading" class="kta-loading" style="display:none;">
        <div class="kta-spinner"></div>
        <p>Suche läuft...</p>
    </div>

    <div id="kta-results" class="kta-results">
        <div class="kta-results-header">
            <h3 id="kta-results-count">Alle Artikel anzeigen</h3>
            <div class="kta-results-sort">
                <label for="kta-sort">Sortierung:</label>
                <select id="kta-sort">
                    <option value="year_desc">Jahr (neueste zuerst)</option>
                    <option value="year_asc">Jahr (älteste zuerst)</option>
                    <option value="title_asc">Titel (A-Z)</option>
                    <option value="author_asc">Autor (A-Z)</option>
                </select>
            </div>
        </div>

        <div id="kta-results-list" class="kta-results-list">
            <!-- Ergebnisse werden hier über AJAX geladen -->
        </div>

        <div id="kta-no-results" class="kta-no-results" style="display:none;">
            <p>Keine Artikel gefunden. Bitte versuchen Sie eine andere Suche.</p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var currentResults = [];

    // Initiale Suche (alle Artikel)
    performSearch();

    // Suchformular absenden
    $('#kta-search-form').on('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    // Sortierung ändern
    $('#kta-sort').on('change', function() {
        sortResults();
    });

    function performSearch() {
        var searchTerm = $('#kta-search-input').val();
        var yearFrom = $('#kta-year-from').val();
        var yearTo = $('#kta-year-to').val();

        $('#kta-loading').show();
        $('#kta-results-list').hide();
        $('#kta-no-results').hide();

        $.post(ktaFrontend.ajax_url, {
            action: 'kta_search',
            search: searchTerm,
            year_from: yearFrom,
            year_to: yearTo,
            nonce: ktaFrontend.nonce
        }, function(response) {
            $('#kta-loading').hide();

            if (response.success && response.data.results.length > 0) {
                currentResults = response.data.results;
                displayResults(currentResults);

                var countText = response.data.results.length + ' Artikel gefunden';
                if (searchTerm) {
                    countText += ' für "' + searchTerm + '"';
                }
                $('#kta-results-count').text(countText);
            } else {
                $('#kta-no-results').show();
                $('#kta-results-list').html('');
            }
        });
    }

    function displayResults(results) {
        var html = '';

        results.forEach(function(article) {
            html += '<div class="kta-article-card">';
            html += '  <div class="kta-article-header">';
            html += '    <h4 class="kta-article-title">' + escapeHtml(article.title) + '</h4>';
            html += '    <span class="kta-article-year">' + article.year + '-' + article.issue + '</span>';
            html += '  </div>';

            if (article.author) {
                html += '  <p class="kta-article-author"><strong>Autor(en):</strong> ' + escapeHtml(article.author) + '</p>';
            }

            if (article.abstract) {
                html += '  <p class="kta-article-abstract">' + escapeHtml(article.abstract) + '</p>';
            }

            if (article.keywords) {
                var keywords = article.keywords.split(',').map(function(kw) {
                    return '<span class="kta-keyword">' + escapeHtml(kw.trim()) + '</span>';
                }).join('');
                html += '  <div class="kta-article-keywords">' + keywords + '</div>';
            }

            html += '  <div class="kta-article-meta">';
            if (article.page_start && article.page_end) {
                html += '    <span class="kta-article-pages">Seiten ' + article.page_start + '-' + article.page_end + '</span>';
            }
            html += '  </div>';

            html += '  <div class="kta-article-actions">';
            html += '    <a href="' + article.pdf_url + '" target="_blank" class="kta-button kta-button-primary">';
            html += '      <span class="dashicons dashicons-pdf"></span> PDF öffnen';
            html += '    </a>';
            html += '  </div>';
            html += '</div>';
        });

        $('#kta-results-list').html(html).show();
    }

    function sortResults() {
        var sortBy = $('#kta-sort').val();

        currentResults.sort(function(a, b) {
            switch(sortBy) {
                case 'year_desc':
                    return b.year - a.year || b.issue.localeCompare(a.issue);
                case 'year_asc':
                    return a.year - b.year || a.issue.localeCompare(b.issue);
                case 'title_asc':
                    return a.title.localeCompare(b.title);
                case 'author_asc':
                    return (a.author || '').localeCompare(b.author || '');
                default:
                    return 0;
            }
        });

        displayResults(currentResults);
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
});
</script>
