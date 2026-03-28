<?php
/**
 * Publication Frontend Manager - Enhanced Dashboard
 * Interaktives Dashboard mit AJAX-Filterung und visueller Darstellung
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Dashboard {

    /**
     * Render Enhanced Dashboard
     */
    public static function render_enhanced_dashboard() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Bitte einloggen.', PFM_TD) . '</p>';
        }

        $user_id = get_current_user_id();
        $is_eic = pfm_user_is_editor_in_chief();
        $is_ed = pfm_user_is_redaktion();
        $is_rev = pfm_user_is_reviewer();

        // Hole Statistiken
        $stats = self::get_dashboard_stats($user_id, $is_eic, $is_ed, $is_rev);

        ob_start();
        ?>
        <div class="pfm-enhanced-dashboard">
            <div class="dashboard-header">
                <h2><?php _e('Publikations-Dashboard', PFM_TD); ?></h2>
                <p class="dashboard-subtitle">
                    <?php
                    if ($is_eic) {
                        _e('Editor in Chief Ansicht', PFM_TD);
                    } elseif ($is_ed) {
                        _e('Redaktions-Ansicht', PFM_TD);
                    } elseif ($is_rev) {
                        _e('Reviewer-Ansicht', PFM_TD);
                    } else {
                        _e('Autoren-Ansicht', PFM_TD);
                    }
                    ?>
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html($stats['total']); ?></h3>
                        <p><?php _e('Publikationen', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="stat-card submitted">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html($stats['submitted']); ?></h3>
                        <p><?php _e('Eingereicht', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="stat-card review">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-visibility"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html($stats['under_review']); ?></h3>
                        <p><?php _e('Im Review', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="stat-card published">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-megaphone"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html($stats['published']); ?></h3>
                        <p><?php _e('Veröffentlicht', PFM_TD); ?></p>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="dashboard-filters">
                <button class="filter-tab active" data-filter="all">
                    <span class="dashicons dashicons-portfolio"></span>
                    <?php _e('Alle', PFM_TD); ?> (<?php echo $stats['total']; ?>)
                </button>
                <button class="filter-tab" data-filter="submitted">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Eingereicht', PFM_TD); ?> (<?php echo $stats['submitted']; ?>)
                </button>
                <button class="filter-tab" data-filter="under_review">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Im Review', PFM_TD); ?> (<?php echo $stats['under_review']; ?>)
                </button>
                <button class="filter-tab" data-filter="revision_needed">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Revision', PFM_TD); ?> (<?php echo $stats['revision_needed']; ?>)
                </button>
                <button class="filter-tab" data-filter="accepted">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Akzeptiert', PFM_TD); ?> (<?php echo $stats['accepted']; ?>)
                </button>
                <button class="filter-tab" data-filter="rejected">
                    <span class="dashicons dashicons-no"></span>
                    <?php _e('Abgelehnt', PFM_TD); ?> (<?php echo $stats['rejected']; ?>)
                </button>
                <button class="filter-tab" data-filter="published">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('Veröffentlicht', PFM_TD); ?> (<?php echo $stats['published']; ?>)
                </button>
            </div>

            <!-- Search & Sort -->
            <div class="dashboard-controls">
                <div class="search-box">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="dashboard-search" placeholder="<?php esc_attr_e('Suche nach Titel, Autor, DOI...', PFM_TD); ?>">
                </div>

                <div class="sort-controls">
                    <label><?php _e('Sortieren:', PFM_TD); ?></label>
                    <select id="dashboard-sort">
                        <option value="date_desc"><?php _e('Neueste zuerst', PFM_TD); ?></option>
                        <option value="date_asc"><?php _e('Älteste zuerst', PFM_TD); ?></option>
                        <option value="title_asc"><?php _e('Titel A-Z', PFM_TD); ?></option>
                        <option value="title_desc"><?php _e('Titel Z-A', PFM_TD); ?></option>
                        <option value="author_asc"><?php _e('Autor A-Z', PFM_TD); ?></option>
                    </select>
                </div>

                <?php if ($is_eic || $is_ed): ?>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button class="view-btn" data-view="list">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                    <button class="view-btn" data-view="table">
                        <span class="dashicons dashicons-table-col-after"></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Publications Container -->
            <div id="publications-container" class="publications-grid" data-view="grid">
                <div class="loading-spinner">
                    <span class="dashicons dashicons-update spin"></span>
                    <p><?php _e('Lade Publikationen...', PFM_TD); ?></p>
                </div>
            </div>

            <!-- Pagination -->
            <div id="dashboard-pagination" class="dashboard-pagination"></div>

            <!-- Source Verification Panel (für Redaktion) -->
            <?php if ($is_eic || $is_ed): ?>
            <div id="source-verification-panel" class="source-verification-panel" style="display:none;">
                <div class="panel-header">
                    <h3><?php _e('Quellenüberprüfung', PFM_TD); ?></h3>
                    <button class="close-panel">&times;</button>
                </div>
                <div class="panel-content">
                    <div id="verification-results"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const Dashboard = {
                currentFilter: 'all',
                currentSort: 'date_desc',
                currentSearch: '',
                currentView: 'grid',
                currentPage: 1,
                perPage: 12,

                init: function() {
                    this.bindEvents();
                    this.loadPublications();
                },

                bindEvents: function() {
                    // Filter tabs
                    $('.filter-tab').on('click', function() {
                        $('.filter-tab').removeClass('active');
                        $(this).addClass('active');
                        Dashboard.currentFilter = $(this).data('filter');
                        Dashboard.currentPage = 1;
                        Dashboard.loadPublications();
                    });

                    // Search
                    let searchTimeout;
                    $('#dashboard-search').on('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            Dashboard.currentSearch = $('#dashboard-search').val();
                            Dashboard.currentPage = 1;
                            Dashboard.loadPublications();
                        }, 500);
                    });

                    // Sort
                    $('#dashboard-sort').on('change', function() {
                        Dashboard.currentSort = $(this).val();
                        Dashboard.loadPublications();
                    });

                    // View toggle
                    $('.view-btn').on('click', function() {
                        $('.view-btn').removeClass('active');
                        $(this).addClass('active');
                        Dashboard.currentView = $(this).data('view');
                        $('#publications-container').attr('data-view', Dashboard.currentView);
                        Dashboard.renderPublications();
                    });

                    // Close verification panel
                    $(document).on('click', '.close-panel', function() {
                        $('#source-verification-panel').fadeOut();
                    });
                },

                loadPublications: function() {
                    $('#publications-container').html('<div class="loading-spinner"><span class="dashicons dashicons-update spin"></span><p><?php _e('Lade...', PFM_TD); ?></p></div>');

                    $.ajax({
                        url: pfm_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'pfm_load_dashboard_publications',
                            nonce: pfm_ajax.dashboard_nonce,
                            filter: Dashboard.currentFilter,
                            sort: Dashboard.currentSort,
                            search: Dashboard.currentSearch,
                            page: Dashboard.currentPage,
                            per_page: Dashboard.perPage
                        },
                        success: function(response) {
                            if (response.success) {
                                Dashboard.renderPublications(response.data.publications);
                                Dashboard.renderPagination(response.data.total, response.data.pages);
                            } else {
                                $('#publications-container').html('<p class="no-results">' + response.data.message + '</p>');
                            }
                        },
                        error: function() {
                            $('#publications-container').html('<p class="error"><?php _e('Fehler beim Laden.', PFM_TD); ?></p>');
                        }
                    });
                },

                renderPublications: function(publications) {
                    if (!publications || publications.length === 0) {
                        $('#publications-container').html('<div class="no-results"><span class="dashicons dashicons-info"></span><p><?php _e('Keine Publikationen gefunden.', PFM_TD); ?></p></div>');
                        return;
                    }

                    let html = '';

                    if (Dashboard.currentView === 'grid') {
                        publications.forEach(pub => {
                            html += Dashboard.renderGridCard(pub);
                        });
                    } else if (Dashboard.currentView === 'list') {
                        publications.forEach(pub => {
                            html += Dashboard.renderListItem(pub);
                        });
                    } else if (Dashboard.currentView === 'table') {
                        html = Dashboard.renderTable(publications);
                    }

                    $('#publications-container').html(html);
                },

                renderGridCard: function(pub) {
                    let statusClass = 'status-' + pub.status;
                    return `
                        <div class="publication-card ${statusClass}">
                            <div class="pub-header">
                                <div>
                                    <h3 class="pub-title"><a href="${pub.permalink}">${pub.title}</a></h3>
                                    ${pub.pub_type ? '<span class="publikationsart-badge" data-type="' + pub.pub_type + '">' + pub.pub_type + '</span>' : ''}
                                </div>
                                <span class="pub-status-badge ${pub.status}">${pub.status_label}</span>
                            </div>
                            <div class="pub-meta">
                                <span class="pub-meta-item">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    ${pub.autoren || pub.author_name}
                                </span>
                                ${pub.ausgabe ? '<span class="pub-meta-item"><span class="dashicons dashicons-book"></span>' + pub.ausgabe + '</span>' : ''}
                                <span class="pub-meta-item">
                                    <span class="dashicons dashicons-calendar"></span>
                                    ${pub.date}
                                </span>
                                ${pub.reviews_count ? '<span class="pub-meta-item"><span class="dashicons dashicons-welcome-comments"></span>' + pub.reviews_count + ' Reviews</span>' : ''}
                            </div>
                            ${pub.excerpt ? '<div class="pub-excerpt">' + pub.excerpt + '</div>' : ''}
                            <div class="pub-actions">
                                <a href="${pub.permalink}" class="pub-action-btn primary"><?php _e('Öffnen', PFM_TD); ?></a>
                                <?php if(pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()): ?>
                                <a href="${pub.edit_link}" class="pub-action-btn secondary"><?php _e('Bearbeiten', PFM_TD); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    `;
                },

                renderListItem: function(pub) {
                    let statusClass = 'status-' + pub.status;
                    return `
                        <div class="publication-card ${statusClass}" style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="flex: 1;">
                                <h3 class="pub-title" style="margin: 0 0 8px 0;"><a href="${pub.permalink}">${pub.title}</a></h3>
                                <div class="pub-meta">
                                    <span class="pub-status-badge ${pub.status}">${pub.status_label}</span>
                                    ${pub.pub_type ? '<span class="publikationsart-badge" data-type="' + pub.pub_type + '">' + pub.pub_type + '</span>' : ''}
                                    <span class="pub-meta-item">${pub.autoren || pub.author_name}</span>
                                    ${pub.ausgabe ? '<span class="pub-meta-item">' + pub.ausgabe + '</span>' : ''}
                                    <span class="pub-meta-item">${pub.date}</span>
                                    ${pub.reviews_count ? '<span class="pub-meta-item">' + pub.reviews_count + ' Reviews</span>' : ''}
                                </div>
                            </div>
                            <div class="pub-actions" style="border: none; padding: 0;">
                                <a href="${pub.permalink}" class="pub-action-btn primary"><?php _e('Öffnen', PFM_TD); ?></a>
                                <?php if(pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()): ?>
                                <a href="${pub.edit_link}" class="pub-action-btn secondary"><?php _e('Bearbeiten', PFM_TD); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    `;
                },

                renderTable: function(publications) {
                    let html = `
                        <table class="widefat striped publications-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Titel', PFM_TD); ?></th>
                                    <th><?php _e('Typ', PFM_TD); ?></th>
                                    <th><?php _e('Autor', PFM_TD); ?></th>
                                    <th><?php _e('Ausgabe', PFM_TD); ?></th>
                                    <th><?php _e('Status', PFM_TD); ?></th>
                                    <th><?php _e('Datum', PFM_TD); ?></th>
                                    <th><?php _e('Reviews', PFM_TD); ?></th>
                                    <th><?php _e('Aktionen', PFM_TD); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    publications.forEach(pub => {
                        html += `
                            <tr data-status="${pub.status}">
                                <td><strong><a href="${pub.permalink}">${pub.title}</a></strong></td>
                                <td>${pub.pub_type ? '<span class="publikationsart-badge" data-type="' + pub.pub_type + '">' + pub.pub_type + '</span>' : '—'}</td>
                                <td>${pub.autoren || pub.author_name}</td>
                                <td>${pub.ausgabe || '—'}</td>
                                <td><span class="pub-status-badge ${pub.status}">${pub.status_label}</span></td>
                                <td>${pub.date}</td>
                                <td>${pub.reviews_count || 0} / ${pub.reviewers_count || 0}</td>
                                <td class="table-actions">
                                    <a href="${pub.permalink}" class="button button-small"><?php _e('Öffnen', PFM_TD); ?></a>
                                    <?php if(pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()): ?>
                                    <a href="${pub.edit_link}" class="button button-small"><?php _e('Bearbeiten', PFM_TD); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    return html;
                },

                renderPagination: function(total, pages) {
                    if (pages <= 1) {
                        $('#dashboard-pagination').html('');
                        return;
                    }

                    let html = '<div class="pagination-info"><?php _e('Gesamt:', PFM_TD); ?> ' + total + '</div>';
                    html += '<div class="pagination-buttons">';

                    if (Dashboard.currentPage > 1) {
                        html += '<button class="page-btn" data-page="' + (Dashboard.currentPage - 1) + '">&laquo; <?php _e('Zurück', PFM_TD); ?></button>';
                    }

                    for (let i = 1; i <= pages; i++) {
                        if (i === Dashboard.currentPage) {
                            html += '<button class="page-btn active">' + i + '</button>';
                        } else if (Math.abs(i - Dashboard.currentPage) <= 2 || i === 1 || i === pages) {
                            html += '<button class="page-btn" data-page="' + i + '">' + i + '</button>';
                        } else if (Math.abs(i - Dashboard.currentPage) === 3) {
                            html += '<span class="pagination-dots">...</span>';
                        }
                    }

                    if (Dashboard.currentPage < pages) {
                        html += '<button class="page-btn" data-page="' + (Dashboard.currentPage + 1) + '"><?php _e('Weiter', PFM_TD); ?> &raquo;</button>';
                    }

                    html += '</div>';
                    $('#dashboard-pagination').html(html);

                    $('.page-btn[data-page]').on('click', function() {
                        Dashboard.currentPage = parseInt($(this).data('page'));
                        Dashboard.loadPublications();
                        $('html, body').animate({scrollTop: 0}, 300);
                    });
                }
            };

            // Quellenüberprüfung
            $(document).on('click', '.verify-sources-btn', function() {
                const postId = $(this).data('post-id');
                Dashboard.verifyLiterature(postId);
            });

            Dashboard.verifyLiterature = function(postId) {
                $('#source-verification-panel').fadeIn();
                $('#verification-results').html('<div class="loading-spinner"><span class="dashicons dashicons-update spin"></span><p><?php _e('Überprüfe Quellen...', PFM_TD); ?></p></div>');

                $.ajax({
                    url: pfm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pfm_verify_literature',
                        nonce: pfm_ajax.dashboard_nonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#verification-results').html(response.data.html);
                        } else {
                            $('#verification-results').html('<p class="error">' + response.data.message + '</p>');
                        }
                    }
                });
            };

            Dashboard.init();
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Get Dashboard Statistics
     */
    private static function get_dashboard_stats($user_id, $is_eic, $is_ed, $is_rev) {
        $stats = array(
            'total' => 0,
            'submitted' => 0,
            'under_review' => 0,
            'revision_needed' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'published' => 0,
        );

        $query_args = array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        // Filter basierend auf Rolle
        if (!$is_eic && !$is_ed) {
            if ($is_rev) {
                // Reviewer sieht nur zugewiesene Publikationen
                $assigned_ids = self::get_assigned_publications($user_id);
                if (empty($assigned_ids)) {
                    return $stats;
                }
                $query_args['post__in'] = $assigned_ids;
            } else {
                // Autor sieht nur eigene
                $query_args['author'] = $user_id;
            }
        }

        $query = new WP_Query($query_args);
        $post_ids = $query->posts;

        $stats['total'] = count($post_ids);

        foreach ($post_ids as $post_id) {
            $status = get_post_meta($post_id, 'pfm_status', true) ?: 'submitted';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * Get Assigned Publications for Reviewer
     */
    private static function get_assigned_publications($user_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'pfm_assigned_reviewers'
            AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like((string)$user_id) . '%');

        $ids = $wpdb->get_col($query);
        return array_map('intval', $ids);
    }
}
