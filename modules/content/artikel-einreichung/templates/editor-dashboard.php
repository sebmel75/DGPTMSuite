<?php
/**
 * Template: Editor-in-Chief Dashboard (Frontend)
 * Shortcode: [artikel_editor_dashboard]
 * Nur für Benutzer mit editor_in_chief Berechtigung
 *
 * Design angelehnt an das Admin-Dashboard für einheitliche Benutzerführung
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$user_id = get_current_user_id();

// Check permission
if (!$plugin->is_editor_in_chief($user_id)) {
    echo '<div class="dgptm-artikel-notice notice-error"><p>Sie haben keine Berechtigung für diesen Bereich.</p></div>';
    return;
}

// Get available reviewers
$reviewers = $plugin->get_reviewers();

// Get filter parameters
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build query
$query_args = [
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
];

if ($filter_status) {
    $query_args['meta_query'] = [
        [
            'key' => 'artikel_status',
            'value' => $filter_status,
            'compare' => '='
        ]
    ];
}

$articles = get_posts($query_args);

// Get ALL articles for statistics (unfiltered)
$all_articles = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'post_status' => 'publish'
]);

// Calculate statistics
$stats = [
    'total' => count($all_articles),
    'submitted' => 0,
    'in_review' => 0,
    'revision_required' => 0,
    'revision_submitted' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'published' => 0
];

foreach ($all_articles as $article) {
    $status = get_field('artikel_status', $article->ID);
    switch ($status) {
        case DGPTM_Artikel_Einreichung::STATUS_SUBMITTED:
            $stats['submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW:
            $stats['in_review']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED:
            $stats['revision_required']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED:
            $stats['revision_submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_ACCEPTED:
            $stats['accepted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REJECTED:
            $stats['rejected']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_PUBLISHED:
            $stats['published']++;
            break;
    }
}

// Pending action articles
$pending_action = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => 'artikel_status',
            'value' => DGPTM_Artikel_Einreichung::STATUS_SUBMITTED,
            'compare' => '='
        ],
        [
            'key' => 'artikel_status',
            'value' => DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED,
            'compare' => '='
        ]
    ]
]);

// Recent submissions
$recent = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Check if viewing single article
$view_id = isset($_GET['editor_artikel_id']) ? intval($_GET['editor_artikel_id']) : 0;
$view_article = null;
if ($view_id) {
    $view_article = get_post($view_id);
    if (!$view_article || $view_article->post_type !== DGPTM_Artikel_Einreichung::POST_TYPE) {
        $view_article = null;
        $view_id = 0;
    }
}
?>

<div class="dgptm-artikel-container dgptm-editor-dashboard">

    <?php if ($view_article): ?>
        <!-- Single Article Editor View -->
        <?php
        $status = get_field('artikel_status', $view_id);
        $submission_id = get_field('submission_id', $view_id);
        $reviewer_1 = get_field('reviewer_1', $view_id);
        $reviewer_2 = get_field('reviewer_2', $view_id);
        $r1_status = get_field('reviewer_1_status', $view_id);
        $r2_status = get_field('reviewer_2_status', $view_id);
        $r1_comment = get_field('reviewer_1_comment', $view_id);
        $r2_comment = get_field('reviewer_2_comment', $view_id);
        $r1_rec = get_field('reviewer_1_recommendation', $view_id);
        $r2_rec = get_field('reviewer_2_recommendation', $view_id);
        ?>

        <a href="<?php echo esc_url(remove_query_arg('editor_artikel_id')); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &larr; Zurück zur Übersicht
        </a>

        <div class="editor-detail-grid">
            <!-- Main Content -->
            <div class="editor-main">
                <div class="editor-box">
                    <div class="editor-box-header">
                        <div>
                            <span class="submission-id"><?php echo esc_html($submission_id); ?></span>
                            <h2 style="margin: 5px 0 0 0;"><?php echo esc_html($view_article->post_title); ?></h2>
                        </div>
                        <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                            <?php echo esc_html($plugin->get_status_label($status)); ?>
                        </span>
                    </div>

                    <div class="editor-box-body">
                        <!-- Tabs -->
                        <div class="tabs-container">
                            <div class="tabs">
                                <div class="tab active" data-tab="info">Übersicht</div>
                                <div class="tab" data-tab="manuscript">Manuskript</div>
                                <div class="tab" data-tab="reviews">Reviews</div>
                            </div>

                            <!-- Info Tab -->
                            <div class="tab-content active" data-tab-content="info">
                                <h4>Artikeldetails</h4>
                                <ul class="info-list">
                                    <li>
                                        <span class="label">Publikationsart:</span>
                                        <span class="value"><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $view_id)] ?? '-'); ?></span>
                                    </li>
                                    <li>
                                        <span class="label">Korrespondenzautor:</span>
                                        <span class="value"><?php echo esc_html(get_field('hauptautorin', $view_id)); ?></span>
                                    </li>
                                    <li>
                                        <span class="label">E-Mail:</span>
                                        <span class="value"><?php echo esc_html(get_field('hauptautor_email', $view_id)); ?></span>
                                    </li>
                                    <li>
                                        <span class="label">Institution:</span>
                                        <span class="value"><?php echo esc_html(get_field('hauptautor_institution', $view_id) ?: '-'); ?></span>
                                    </li>
                                    <li>
                                        <span class="label">Ko-Autoren:</span>
                                        <span class="value"><?php echo nl2br(esc_html(get_field('autoren', $view_id) ?: '-')); ?></span>
                                    </li>
                                    <li>
                                        <span class="label">Eingereicht am:</span>
                                        <span class="value"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime(get_field('submitted_at', $view_id)))); ?></span>
                                    </li>
                                </ul>

                                <h4 style="margin-top: 20px;">Abstract (Deutsch)</h4>
                                <div class="abstract-box">
                                    <?php echo esc_html(get_field('abstract-deutsch', $view_id)); ?>
                                </div>

                                <?php if ($keywords = get_field('keywords-deutsch', $view_id)): ?>
                                <h4 style="margin-top: 15px;">Schlüsselwörter</h4>
                                <p><?php echo esc_html($keywords); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Manuscript Tab -->
                            <div class="tab-content" data-tab-content="manuscript">
                                <?php
                                $manuskript = get_field('manuskript', $view_id);
                                $revision = get_field('revision_manuskript', $view_id);
                                ?>

                                <h4>Original-Manuskript</h4>
                                <?php if ($manuskript): ?>
                                    <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="btn btn-primary">
                                        Herunterladen (<?php echo esc_html($manuskript['filename']); ?>)
                                    </a>
                                <?php else: ?>
                                    <p class="no-items">Kein Manuskript vorhanden.</p>
                                <?php endif; ?>

                                <?php if ($revision): ?>
                                <h4 style="margin-top: 20px;">Revidiertes Manuskript</h4>
                                <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-success">
                                    Revision herunterladen (<?php echo esc_html($revision['filename']); ?>)
                                </a>

                                <?php if ($response = get_field('revision_response', $view_id)): ?>
                                <h4 style="margin-top: 20px;">Response to Reviewers</h4>
                                <div class="abstract-box">
                                    <?php echo esc_html($response); ?>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($literatur = get_field('literatur', $view_id)): ?>
                                <h4 style="margin-top: 20px;">Literaturverzeichnis</h4>
                                <div class="abstract-box" style="font-size: 13px;">
                                    <?php echo esc_html($literatur); ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($coi = get_field('interessenkonflikte', $view_id)): ?>
                                <h4 style="margin-top: 20px;">Interessenkonflikte</h4>
                                <p><?php echo esc_html($coi); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Reviews Tab -->
                            <div class="tab-content" data-tab-content="reviews">
                                <?php
                                $rec_labels = [
                                    'accept' => 'Annehmen',
                                    'minor_revision' => 'Kleinere Überarbeitung',
                                    'major_revision' => 'Größere Überarbeitung',
                                    'reject' => 'Ablehnen'
                                ];
                                $rec_class = [
                                    'accept' => 'status-green',
                                    'minor_revision' => 'status-blue',
                                    'major_revision' => 'status-orange',
                                    'reject' => 'status-red'
                                ];
                                ?>

                                <!-- Reviewer 1 -->
                                <div class="review-display">
                                    <div class="review-header">
                                        <strong>Reviewer 1</strong>
                                        <?php if ($r1_status === 'completed'): ?>
                                            <span class="status-badge <?php echo esc_attr($rec_class[$r1_rec] ?? 'status-gray'); ?>">
                                                <?php echo esc_html($rec_labels[$r1_rec] ?? '-'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($r1_status === 'completed'): ?>
                                        <div class="review-text">
                                            <?php echo esc_html($r1_comment); ?>
                                        </div>
                                    <?php elseif ($r1_status === 'pending'): ?>
                                        <p><span class="status-badge status-orange">Gutachten ausstehend</span></p>
                                    <?php else: ?>
                                        <p class="no-items">Noch nicht zugewiesen</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Reviewer 2 -->
                                <div class="review-display" style="margin-top: 20px;">
                                    <div class="review-header">
                                        <strong>Reviewer 2</strong>
                                        <?php if ($r2_status === 'completed'): ?>
                                            <span class="status-badge <?php echo esc_attr($rec_class[$r2_rec] ?? 'status-gray'); ?>">
                                                <?php echo esc_html($rec_labels[$r2_rec] ?? '-'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($r2_status === 'completed'): ?>
                                        <div class="review-text">
                                            <?php echo esc_html($r2_comment); ?>
                                        </div>
                                    <?php elseif ($r2_status === 'pending'): ?>
                                        <p><span class="status-badge status-orange">Gutachten ausstehend</span></p>
                                    <?php else: ?>
                                        <p class="no-items">Noch nicht zugewiesen</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="editor-sidebar">
                <!-- Reviewer Assignment -->
                <div class="sidebar-card">
                    <h3>Reviewer zuweisen</h3>

                    <!-- Reviewer 1 -->
                    <div class="reviewer-slot">
                        <div class="slot-header">
                            <span class="slot-label">Reviewer 1</span>
                            <?php if ($r1_status): ?>
                                <span class="reviewer-status <?php echo $r1_status === 'completed' ? 'completed' : 'pending'; ?>">
                                    <?php echo $r1_status === 'completed' ? 'Fertig' : 'Ausstehend'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($reviewer_1): ?>
                            <p style="margin: 0;">
                                <?php
                                $r1_user = is_object($reviewer_1) ? $reviewer_1 : get_user_by('ID', $reviewer_1);
                                echo esc_html($r1_user ? $r1_user->display_name : 'Unbekannt');
                                ?>
                            </p>
                        <?php else: ?>
                            <select id="reviewer-1-select" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($reviewers as $rev): ?>
                                    <option value="<?php echo esc_attr($rev->ID); ?>">
                                        <?php echo esc_html($rev->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary assign-reviewer-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-slot="1" style="margin-top: 10px; width: 100%;">
                                Zuweisen
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Reviewer 2 -->
                    <div class="reviewer-slot">
                        <div class="slot-header">
                            <span class="slot-label">Reviewer 2</span>
                            <?php if ($r2_status): ?>
                                <span class="reviewer-status <?php echo $r2_status === 'completed' ? 'completed' : 'pending'; ?>">
                                    <?php echo $r2_status === 'completed' ? 'Fertig' : 'Ausstehend'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($reviewer_2): ?>
                            <p style="margin: 0;">
                                <?php
                                $r2_user = is_object($reviewer_2) ? $reviewer_2 : get_user_by('ID', $reviewer_2);
                                echo esc_html($r2_user ? $r2_user->display_name : 'Unbekannt');
                                ?>
                            </p>
                        <?php else: ?>
                            <select id="reviewer-2-select" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($reviewers as $rev): ?>
                                    <option value="<?php echo esc_attr($rev->ID); ?>">
                                        <?php echo esc_html($rev->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary assign-reviewer-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-slot="2" style="margin-top: 10px; width: 100%;">
                                Zuweisen
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Decision Panel -->
                <?php if (($r1_status === 'completed' || $r2_status === 'completed') &&
                          !in_array($status, [DGPTM_Artikel_Einreichung::STATUS_ACCEPTED, DGPTM_Artikel_Einreichung::STATUS_REJECTED, DGPTM_Artikel_Einreichung::STATUS_PUBLISHED])): ?>
                <div class="decision-panel">
                    <h3>Entscheidung treffen</h3>
                    <div class="decision-buttons">
                        <button class="btn btn-success decision-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="accept">
                            Annehmen
                        </button>
                        <button class="btn decision-btn" style="background: #d69e2e !important; color: #fff !important;" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="revision">
                            Revision
                        </button>
                        <button class="btn btn-danger decision-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="reject">
                            Ablehnen
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Editor Notes -->
                <div class="sidebar-card">
                    <h3>Interne Notizen</h3>
                    <textarea id="editor-notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; resize: vertical;"
                              placeholder="Notizen für die Redaktion..."><?php echo esc_textarea(get_field('editor_notes', $view_id)); ?></textarea>
                    <button class="btn btn-secondary" style="margin-top: 10px; width: 100%;" onclick="saveEditorNotes(<?php echo esc_attr($view_id); ?>)">
                        Speichern
                    </button>
                </div>
            </div>
        </div>

        <!-- Decision Modal -->
        <div id="decision-modal" class="dgptm-modal-overlay">
            <div class="dgptm-modal">
                <div class="dgptm-modal-header">
                    <h3>Entscheidung</h3>
                    <button class="dgptm-modal-close">&times;</button>
                </div>
                <div class="dgptm-modal-body">
                    <div class="form-row">
                        <label>Decision Letter an den Autor</label>
                        <textarea name="decision_letter" rows="10" style="width: 100%;"
                                  placeholder="Begründung und ggf. zusammengefasste Reviewer-Kommentare..."></textarea>
                    </div>
                </div>
                <div class="dgptm-modal-footer">
                    <button class="btn btn-secondary modal-cancel">Abbrechen</button>
                    <button class="btn btn-primary" id="confirm-decision">Entscheidung senden</button>
                </div>
            </div>
        </div>

        <script>
        function saveEditorNotes(articleId) {
            const notes = document.getElementById('editor-notes').value;
            jQuery.ajax({
                url: dgptmArtikel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_save_editor_notes',
                    nonce: dgptmArtikel.nonce,
                    article_id: articleId,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        alert('Notizen gespeichert.');
                    }
                }
            });
        }

        // Custom assign reviewer handler for editor dashboard
        jQuery(document).on('click', '.assign-reviewer-btn', function() {
            const articleId = jQuery(this).data('article-id');
            const slot = jQuery(this).data('slot');
            const reviewerId = jQuery('#reviewer-' + slot + '-select').val();

            if (!reviewerId) {
                alert('Bitte wählen Sie einen Reviewer aus.');
                return;
            }

            jQuery.ajax({
                url: dgptmArtikel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_assign_reviewer',
                    nonce: dgptmArtikel.nonce,
                    article_id: articleId,
                    reviewer_id: reviewerId,
                    slot: slot
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Fehler beim Zuweisen.');
                    }
                }
            });
        });
        </script>

    <?php else: ?>
        <!-- Dashboard Overview -->
        <div class="dashboard-header">
            <h1>Die Perfusiologie - Editor Dashboard</h1>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Gesamt</div>
            </div>
            <div class="stat-card stat-new">
                <div class="stat-number"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label">Neu eingereicht</div>
            </div>
            <div class="stat-card stat-review">
                <div class="stat-number"><?php echo $stats['in_review']; ?></div>
                <div class="stat-label">Im Review</div>
            </div>
            <div class="stat-card stat-revision">
                <div class="stat-number"><?php echo $stats['revision_submitted']; ?></div>
                <div class="stat-label">Revision eingereicht</div>
            </div>
            <div class="stat-card stat-accepted">
                <div class="stat-number"><?php echo $stats['accepted']; ?></div>
                <div class="stat-label">Angenommen</div>
            </div>
            <div class="stat-card stat-published">
                <div class="stat-number"><?php echo $stats['published']; ?></div>
                <div class="stat-label">Veröffentlicht</div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="dashboard-columns">
            <!-- Pending Action -->
            <div class="dashboard-column">
                <div class="dashboard-box">
                    <h2>Aktion erforderlich (<?php echo count($pending_action); ?>)</h2>
                    <?php if (empty($pending_action)): ?>
                        <p class="no-items">Keine Einreichungen erfordern aktuell eine Aktion.</p>
                    <?php else: ?>
                        <table class="dgptm-artikel-table compact">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titel</th>
                                    <th>Status</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_action as $article):
                                    $status = get_field('artikel_status', $article->ID);
                                    $submission_id = get_field('submission_id', $article->ID);
                                ?>
                                <tr>
                                    <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                                    <td>
                                        <strong><?php echo esc_html(wp_trim_words($article->post_title, 6)); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                            <?php echo esc_html($plugin->get_status_label($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg('editor_artikel_id', $article->ID)); ?>" class="btn btn-primary btn-small">
                                            Bearbeiten
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="dashboard-column">
                <div class="dashboard-box">
                    <h2>Letzte Einreichungen</h2>
                    <?php if (empty($recent)): ?>
                        <p class="no-items">Keine Einreichungen vorhanden.</p>
                    <?php else: ?>
                        <table class="dgptm-artikel-table compact">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titel</th>
                                    <th>Datum</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $article):
                                    $status = get_field('artikel_status', $article->ID);
                                    $submission_id = get_field('submission_id', $article->ID);
                                    $submitted_at = get_field('submitted_at', $article->ID);
                                ?>
                                <tr>
                                    <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg('editor_artikel_id', $article->ID)); ?>">
                                            <?php echo esc_html(wp_trim_words($article->post_title, 6)); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                            <?php echo esc_html($plugin->get_status_label($status)); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- All Articles Section -->
        <div class="dashboard-box" style="margin-top: 20px;">
            <h2>Alle Einreichungen</h2>

            <!-- Filters -->
            <div class="filter-bar">
                <a href="<?php echo esc_url(remove_query_arg('status')); ?>" class="btn <?php echo !$filter_status ? 'btn-primary' : 'btn-secondary'; ?>">Alle</a>
                <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_SUBMITTED)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? 'btn-primary' : 'btn-secondary'; ?>">Neu</a>
                <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? 'btn-primary' : 'btn-secondary'; ?>">Im Review</a>
                <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? 'btn-primary' : 'btn-secondary'; ?>">Revision</a>
                <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_ACCEPTED)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? 'btn-primary' : 'btn-secondary'; ?>">Angenommen</a>
                <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_PUBLISHED)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? 'btn-primary' : 'btn-secondary'; ?>">Publiziert</a>
            </div>

            <!-- Article List -->
            <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">&#128196;</div>
                    <h3>Keine Artikel gefunden</h3>
                </div>
            <?php else: ?>
                <table class="dgptm-artikel-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titel</th>
                            <th>Autor</th>
                            <th>Status</th>
                            <th>Reviewer</th>
                            <th>Eingereicht</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article):
                            $status = get_field('artikel_status', $article->ID);
                            $submission_id = get_field('submission_id', $article->ID);
                            $submitted_at = get_field('submitted_at', $article->ID);
                            $r1 = get_field('reviewer_1', $article->ID);
                            $r2 = get_field('reviewer_2', $article->ID);
                            $r1_st = get_field('reviewer_1_status', $article->ID);
                            $r2_st = get_field('reviewer_2_status', $article->ID);
                        ?>
                        <tr>
                            <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                            <td>
                                <div class="article-title"><?php echo esc_html($article->post_title); ?></div>
                                <div class="article-meta">
                                    <?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $article->ID)] ?? ''); ?>
                                </div>
                            </td>
                            <td><?php echo esc_html(get_field('hauptautorin', $article->ID)); ?></td>
                            <td>
                                <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                    <?php echo esc_html($plugin->get_status_label($status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $reviewer_info = [];
                                if ($r1) $reviewer_info[] = 'R1: ' . ($r1_st === 'completed' ? '&#10003;' : '...');
                                if ($r2) $reviewer_info[] = 'R2: ' . ($r2_st === 'completed' ? '&#10003;' : '...');
                                echo $reviewer_info ? implode(' ', $reviewer_info) : '-';
                                ?>
                            </td>
                            <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('editor_artikel_id', $article->ID)); ?>" class="btn btn-primary btn-small">
                                    Bearbeiten
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
