<?php
/**
 * Template: Editor-in-Chief Dashboard (Frontend)
 * Shortcode: [artikel_editor_dashboard]
 * Nur für Benutzer mit editor_in_chief Berechtigung
 *
 * Enthält alle Backend-Funktionen:
 * - Artikelverwaltung (Liste, Details, Entscheidungen)
 * - Reviewer-Verwaltung (Hinzufügen/Entfernen)
 * - Einstellungen (E-Mail, Texte, etc.)
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$user_id = get_current_user_id();

// Check permission
if (!$plugin->is_editor_in_chief($user_id)) {
    echo '<div class="dgptm-artikel-notice notice-error"><p>Sie haben keine Berechtigung für diesen Bereich.</p></div>';
    return;
}

// Current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'articles';
$valid_tabs = ['articles', 'reviewers', 'settings'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'articles';
}

// Get available reviewers
$reviewers = $plugin->get_reviewers();

// Build base URL for tab navigation
$base_url = remove_query_arg(['tab', 'status', 'editor_artikel_id']);
?>

<div class="dgptm-artikel-container">

    <!-- Tab Navigation -->
    <div class="editor-tabs" style="display: flex; gap: 0; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0;">
        <a href="<?php echo esc_url(add_query_arg('tab', 'articles', $base_url)); ?>"
           class="editor-tab <?php echo $current_tab === 'articles' ? 'active' : ''; ?>">
            Artikel
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'reviewers', $base_url)); ?>"
           class="editor-tab <?php echo $current_tab === 'reviewers' ? 'active' : ''; ?>">
            Reviewer verwalten
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'settings', $base_url)); ?>"
           class="editor-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
            Einstellungen
        </a>
    </div>

    <?php if ($current_tab === 'articles'): ?>
        <!-- ============================================
             ARTICLES TAB
             ============================================ -->
        <?php
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

        // Statistics
        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_review' => 0,
            'revision' => 0,
            'accepted' => 0
        ];

        $all_articles = get_posts([
            'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
            'posts_per_page' => -1
        ]);

        foreach ($all_articles as $art) {
            $stats['total']++;
            $st = get_field('artikel_status', $art->ID);
            if ($st === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED) $stats['pending']++;
            elseif ($st === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW) $stats['in_review']++;
            elseif (in_array($st, [DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED, DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED])) $stats['revision']++;
            elseif ($st === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED) $stats['accepted']++;
        }

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

            <a href="<?php echo esc_url(add_query_arg('tab', 'articles', $base_url)); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
                &larr; Zurück zur Übersicht
            </a>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Main Content -->
                <div>
                    <div class="article-card">
                        <div class="article-card-header">
                            <span class="submission-id"><?php echo esc_html($submission_id); ?></span>
                            <h3><?php echo esc_html($view_article->post_title); ?></h3>
                            <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                <?php echo esc_html($plugin->get_status_label($status)); ?>
                            </span>
                        </div>

                        <div class="article-card-body">
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
                                    <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px; font-size: 14px;">
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
                                        <p>Kein Manuskript vorhanden.</p>
                                    <?php endif; ?>

                                    <?php if ($revision): ?>
                                    <h4 style="margin-top: 20px;">Revidiertes Manuskript</h4>
                                    <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-success">
                                        Revision herunterladen (<?php echo esc_html($revision['filename']); ?>)
                                    </a>

                                    <?php if ($response = get_field('revision_response', $view_id)): ?>
                                    <h4 style="margin-top: 20px;">Response to Reviewers</h4>
                                    <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                                        <?php echo esc_html($response); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($literatur = get_field('literatur', $view_id)): ?>
                                    <h4 style="margin-top: 20px;">Literaturverzeichnis</h4>
                                    <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px; font-size: 13px;">
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
                                    <div class="review-section">
                                        <h4>Reviewer 1</h4>
                                        <?php if ($r1_status === 'completed'): ?>
                                            <p>
                                                <strong>Empfehlung:</strong>
                                                <span class="status-badge <?php echo esc_attr($rec_class[$r1_rec] ?? 'status-gray'); ?>">
                                                    <?php echo esc_html($rec_labels[$r1_rec] ?? '-'); ?>
                                                </span>
                                            </p>
                                            <div style="white-space: pre-wrap; background: #fff; padding: 15px; border-radius: 6px; margin-top: 10px; border: 1px solid #e2e8f0;">
                                                <?php echo esc_html($r1_comment); ?>
                                            </div>
                                        <?php elseif ($r1_status === 'pending'): ?>
                                            <p class="status-badge status-orange">Gutachten ausstehend</p>
                                        <?php else: ?>
                                            <p>Noch nicht zugewiesen</p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Reviewer 2 -->
                                    <div class="review-section" style="margin-top: 20px;">
                                        <h4>Reviewer 2</h4>
                                        <?php if ($r2_status === 'completed'): ?>
                                            <p>
                                                <strong>Empfehlung:</strong>
                                                <span class="status-badge <?php echo esc_attr($rec_class[$r2_rec] ?? 'status-gray'); ?>">
                                                    <?php echo esc_html($rec_labels[$r2_rec] ?? '-'); ?>
                                                </span>
                                            </p>
                                            <div style="white-space: pre-wrap; background: #fff; padding: 15px; border-radius: 6px; margin-top: 10px; border: 1px solid #e2e8f0;">
                                                <?php echo esc_html($r2_comment); ?>
                                            </div>
                                        <?php elseif ($r2_status === 'pending'): ?>
                                            <p class="status-badge status-orange">Gutachten ausstehend</p>
                                        <?php else: ?>
                                            <p>Noch nicht zugewiesen</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Reviewer Assignment -->
                    <div class="article-card">
                        <div class="article-card-header">
                            <h3 style="margin: 0; font-size: 16px;">Reviewer zuweisen</h3>
                        </div>
                        <div class="article-card-body">
                            <!-- Reviewer 1 -->
                            <div style="margin-bottom: 20px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Reviewer 1</label>
                                <?php if ($reviewer_1): ?>
                                    <p>
                                        <?php
                                        $r1_user = is_object($reviewer_1) ? $reviewer_1 : get_user_by('ID', $reviewer_1);
                                        echo esc_html($r1_user ? $r1_user->display_name : 'Unbekannt');
                                        ?>
                                        <span class="status-badge <?php echo $r1_status === 'completed' ? 'status-green' : 'status-orange'; ?>" style="margin-left: 10px;">
                                            <?php echo $r1_status === 'completed' ? 'Fertig' : 'Ausstehend'; ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <select id="reviewer-1-select" style="width: 100%; padding: 8px;">
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
                            <div>
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Reviewer 2</label>
                                <?php if ($reviewer_2): ?>
                                    <p>
                                        <?php
                                        $r2_user = is_object($reviewer_2) ? $reviewer_2 : get_user_by('ID', $reviewer_2);
                                        echo esc_html($r2_user ? $r2_user->display_name : 'Unbekannt');
                                        ?>
                                        <span class="status-badge <?php echo $r2_status === 'completed' ? 'status-green' : 'status-orange'; ?>" style="margin-left: 10px;">
                                            <?php echo $r2_status === 'completed' ? 'Fertig' : 'Ausstehend'; ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <select id="reviewer-2-select" style="width: 100%; padding: 8px;">
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
                    </div>

                    <!-- Decision Panel -->
                    <?php if (($r1_status === 'completed' || $r2_status === 'completed') &&
                              !in_array($status, [DGPTM_Artikel_Einreichung::STATUS_ACCEPTED, DGPTM_Artikel_Einreichung::STATUS_REJECTED, DGPTM_Artikel_Einreichung::STATUS_PUBLISHED])): ?>
                    <div class="article-card" style="margin-top: 20px; background: #1a365d; color: #fff;">
                        <div class="article-card-header" style="background: transparent; border-color: rgba(255,255,255,0.2);">
                            <h3 style="margin: 0; font-size: 16px; color: #fff;">Entscheidung treffen</h3>
                        </div>
                        <div class="article-card-body">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <button class="btn btn-success decision-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="accept">
                                    Annehmen
                                </button>
                                <button class="btn decision-btn" style="background: #d69e2e !important; color: #fff !important;" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="revision">
                                    Revision anfordern
                                </button>
                                <button class="btn btn-danger decision-btn" data-article-id="<?php echo esc_attr($view_id); ?>" data-decision="reject">
                                    Ablehnen
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Editor Notes -->
                    <div class="article-card" style="margin-top: 20px;">
                        <div class="article-card-header">
                            <h3 style="margin: 0; font-size: 16px;">Interne Notizen</h3>
                        </div>
                        <div class="article-card-body">
                            <textarea id="editor-notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;"
                                      placeholder="Notizen für die Redaktion..."><?php echo esc_textarea(get_field('editor_notes', $view_id)); ?></textarea>
                            <button class="btn btn-secondary" style="margin-top: 10px;" onclick="saveEditorNotes(<?php echo esc_attr($view_id); ?>)">
                                Speichern
                            </button>
                        </div>
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

        <?php else: ?>
            <!-- Dashboard Overview -->
            <h2>Editor-in-Chief Dashboard</h2>

            <!-- Stats -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="article-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #1a365d;"><?php echo $stats['total']; ?></div>
                    <div style="color: #718096; font-size: 14px;">Gesamt</div>
                </div>
                <div class="article-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #3182ce;"><?php echo $stats['pending']; ?></div>
                    <div style="color: #718096; font-size: 14px;">Neu eingereicht</div>
                </div>
                <div class="article-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #d69e2e;"><?php echo $stats['in_review']; ?></div>
                    <div style="color: #718096; font-size: 14px;">Im Review</div>
                </div>
                <div class="article-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #38a169;"><?php echo $stats['accepted']; ?></div>
                    <div style="color: #718096; font-size: 14px;">Angenommen</div>
                </div>
            </div>

            <!-- Filters -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="<?php echo esc_url(add_query_arg('tab', 'articles', remove_query_arg('status', $base_url))); ?>" class="btn <?php echo !$filter_status ? 'btn-primary' : 'btn-secondary'; ?>">Alle</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'articles', 'status' => DGPTM_Artikel_Einreichung::STATUS_SUBMITTED], $base_url)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? 'btn-primary' : 'btn-secondary'; ?>">Neu</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'articles', 'status' => DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW], $base_url)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? 'btn-primary' : 'btn-secondary'; ?>">Im Review</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'articles', 'status' => DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED], $base_url)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? 'btn-primary' : 'btn-secondary'; ?>">Revision</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'articles', 'status' => DGPTM_Artikel_Einreichung::STATUS_ACCEPTED], $base_url)); ?>"
                   class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? 'btn-primary' : 'btn-secondary'; ?>">Angenommen</a>
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
                                <a href="<?php echo esc_url(add_query_arg(['tab' => 'articles', 'editor_artikel_id' => $article->ID], $base_url)); ?>" class="btn btn-primary">
                                    Bearbeiten
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($current_tab === 'reviewers'): ?>
        <!-- ============================================
             REVIEWERS TAB
             ============================================ -->
        <?php
        $current_reviewer_ids = get_option(DGPTM_Artikel_Einreichung::OPT_REVIEWERS, []);
        $current_reviewers = [];
        if (!empty($current_reviewer_ids)) {
            $current_reviewers = get_users([
                'include' => $current_reviewer_ids,
                'orderby' => 'display_name'
            ]);
        }

        // Get all users for selection
        $all_users = get_users([
            'orderby' => 'display_name',
            'number' => 200
        ]);
        ?>

        <h2>Reviewer-Verwaltung</h2>
        <p style="color: #718096; margin-bottom: 30px;">
            Verwalten Sie hier die Liste der verfügbaren Reviewer für Artikel-Einreichungen.
            Nur Benutzer in dieser Liste können als Reviewer für Artikel zugewiesen werden.
        </p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Current Reviewers -->
            <div class="article-card">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Aktuelle Reviewer (<?php echo count($current_reviewers); ?>)</h3>
                </div>
                <div class="article-card-body">
                    <?php if (empty($current_reviewers)): ?>
                        <p style="color: #718096; font-style: italic;">Noch keine Reviewer hinzugefügt.</p>
                    <?php else: ?>
                        <table class="dgptm-artikel-table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>E-Mail</th>
                                    <th style="width: 100px;">Aktion</th>
                                </tr>
                            </thead>
                            <tbody id="reviewer-list">
                                <?php foreach ($current_reviewers as $reviewer): ?>
                                <tr data-user-id="<?php echo $reviewer->ID; ?>">
                                    <td>
                                        <strong><?php echo esc_html($reviewer->display_name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($reviewer->user_email); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger remove-reviewer-btn" data-user-id="<?php echo $reviewer->ID; ?>" style="padding: 6px 12px; font-size: 12px;">
                                            Entfernen
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Reviewer -->
            <div class="article-card">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Reviewer hinzufügen</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row">
                        <label for="add-reviewer-select">Benutzer auswählen:</label>
                        <select id="add-reviewer-select" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                            <option value="">-- Benutzer wählen --</option>
                            <?php foreach ($all_users as $user):
                                if (in_array($user->ID, $current_reviewer_ids)) continue;
                            ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" id="add-reviewer-btn" class="btn btn-primary" style="margin-top: 15px;">
                        Hinzufügen
                    </button>
                </div>
            </div>
        </div>

    <?php elseif ($current_tab === 'settings'): ?>
        <!-- ============================================
             SETTINGS TAB
             ============================================ -->
        <?php
        // Get current settings
        $settings = get_option(DGPTM_Artikel_Einreichung::OPT_SETTINGS, [
            'email_notifications' => 1,
            'notification_email' => get_option('admin_email'),
            'submission_confirmation_text' => '',
            'review_instructions' => '',
            'max_file_size' => 20,
            'auto_assign_reviewers' => 0
        ]);
        ?>

        <h2>Einstellungen</h2>

        <form id="settings-form">
            <!-- Email Notifications -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">E-Mail-Benachrichtigungen</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row" style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="email_notifications" value="1" <?php checked($settings['email_notifications'] ?? 0, 1); ?>>
                            <span>E-Mail-Benachrichtigungen bei Statusänderungen senden</span>
                        </label>
                    </div>

                    <div class="form-row">
                        <label for="notification_email">Zusätzliche Benachrichtigungs-E-Mail:</label>
                        <input type="email" id="notification_email" name="notification_email"
                               value="<?php echo esc_attr($settings['notification_email'] ?? ''); ?>"
                               style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <p style="color: #718096; font-size: 13px; margin-top: 5px;">
                            Optional: Zusätzliche E-Mail-Adresse für Benachrichtigungen (zusätzlich zum Editor in Chief).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Texts -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Texte</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row" style="margin-bottom: 20px;">
                        <label for="submission_confirmation_text">Bestätigungstext nach Einreichung:</label>
                        <textarea id="submission_confirmation_text" name="submission_confirmation_text" rows="4"
                                  style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;"><?php echo esc_textarea($settings['submission_confirmation_text'] ?? ''); ?></textarea>
                        <p style="color: #718096; font-size: 13px; margin-top: 5px;">
                            Optionaler zusätzlicher Text, der dem Autor nach erfolgreicher Einreichung angezeigt wird.
                        </p>
                    </div>

                    <div class="form-row">
                        <label for="review_instructions">Review-Anweisungen:</label>
                        <textarea id="review_instructions" name="review_instructions" rows="6"
                                  style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;"><?php echo esc_textarea($settings['review_instructions'] ?? ''); ?></textarea>
                        <p style="color: #718096; font-size: 13px; margin-top: 5px;">
                            Anweisungen für Reviewer. Wird auf der Review-Seite angezeigt.
                        </p>
                    </div>
                </div>
            </div>

            <!-- File Upload -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Datei-Upload</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row">
                        <label for="max_file_size">Maximale Dateigröße (MB):</label>
                        <input type="number" id="max_file_size" name="max_file_size"
                               value="<?php echo esc_attr($settings['max_file_size'] ?? 20); ?>" min="1" max="100"
                               style="width: 100px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <p style="color: #718096; font-size: 13px; margin-top: 5px;">
                            Server-Limit: <?php echo esc_html(ini_get('upload_max_filesize')); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Shortcodes Reference -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Shortcodes</h3>
                </div>
                <div class="article-card-body">
                    <table class="dgptm-artikel-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Shortcode</th>
                                <th>Beschreibung</th>
                                <th>Berechtigung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[artikel_einreichung]</code></td>
                                <td>Einreichungsformular für neue Artikel</td>
                                <td>Alle (auch ohne Login)</td>
                            </tr>
                            <tr>
                                <td><code>[artikel_dashboard]</code></td>
                                <td>Autoren-Dashboard - Übersicht der eigenen Einreichungen</td>
                                <td>Eingeloggte Benutzer oder Token-Zugang</td>
                            </tr>
                            <tr>
                                <td><code>[artikel_review]</code></td>
                                <td>Reviewer-Dashboard - Zugewiesene Artikel begutachten</td>
                                <td>Zugewiesene Reviewer</td>
                            </tr>
                            <tr>
                                <td><code>[artikel_redaktion]</code></td>
                                <td>Redaktions-Übersicht - Alle Artikel (anonymisiert)</td>
                                <td>ACF-Feld: redaktion_perfusiologie</td>
                            </tr>
                            <tr>
                                <td><code>[artikel_editor_dashboard]</code></td>
                                <td>Editor-in-Chief Dashboard - Vollzugriff</td>
                                <td>ACF-Feld: editor_in_chief</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
        </form>

    <?php endif; ?>

</div>

<style>
/* Editor Tab Navigation */
.editor-tab {
    padding: 12px 24px;
    text-decoration: none;
    color: #4a5568;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.editor-tab:hover {
    color: #1a365d;
    background: #f7fafc;
}

.editor-tab.active {
    color: #1a365d;
    border-bottom-color: #1a365d;
    background: transparent;
}
</style>

<script>
jQuery(document).ready(function($) {

    // ===== ARTICLE FUNCTIONS =====

    // Save editor notes
    window.saveEditorNotes = function(articleId) {
        const notes = document.getElementById('editor-notes').value;
        $.ajax({
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
    };

    // Assign reviewer
    $(document).on('click', '.assign-reviewer-btn', function() {
        const articleId = $(this).data('article-id');
        const slot = $(this).data('slot');
        const reviewerId = $('#reviewer-' + slot + '-select').val();

        if (!reviewerId) {
            alert('Bitte wählen Sie einen Reviewer aus.');
            return;
        }

        $.ajax({
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

    // ===== REVIEWER MANAGEMENT =====

    // Add reviewer
    $('#add-reviewer-btn').on('click', function() {
        var userId = parseInt($('#add-reviewer-select').val());
        if (!userId) {
            alert('Bitte wählen Sie einen Benutzer aus.');
            return;
        }

        $.ajax({
            url: dgptmArtikel.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_add_reviewer',
                nonce: dgptmArtikel.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Hinzufügen.');
                }
            }
        });
    });

    // Remove reviewer
    $(document).on('click', '.remove-reviewer-btn', function() {
        var userId = parseInt($(this).data('user-id'));

        if (!confirm('Reviewer wirklich entfernen?')) {
            return;
        }

        $.ajax({
            url: dgptmArtikel.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_remove_reviewer',
                nonce: dgptmArtikel.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Entfernen.');
                }
            }
        });
    });

    // ===== SETTINGS =====

    $('#settings-form').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            action: 'dgptm_save_artikel_settings',
            nonce: dgptmArtikel.nonce,
            email_notifications: $('input[name="email_notifications"]').is(':checked') ? 1 : 0,
            notification_email: $('input[name="notification_email"]').val(),
            submission_confirmation_text: $('textarea[name="submission_confirmation_text"]').val(),
            review_instructions: $('textarea[name="review_instructions"]').val(),
            max_file_size: $('input[name="max_file_size"]').val()
        };

        $.ajax({
            url: dgptmArtikel.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Einstellungen gespeichert.');
                } else {
                    alert(response.data.message || 'Fehler beim Speichern.');
                }
            }
        });
    });
});
</script>
