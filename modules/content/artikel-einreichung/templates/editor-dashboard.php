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

// Current tab — GET-Parameter oder JS-gesteuert (Dashboard-kompatibel)
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'articles';
$valid_tabs = ['articles', 'reviewers', 'settings'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'articles';
}

// Get available reviewers
$reviewers = $plugin->get_reviewers();

// Dashboard-Kontext erkennen
$in_dashboard = (defined('DOING_AJAX') && DOING_AJAX) || !empty($GLOBALS['dgptm_dash_context']);
$base_url = remove_query_arg(['tab', 'status', 'editor_artikel_id']);
?>

<div class="dgptm-artikel-container">

    <!-- Tab Navigation (JS-basiert fuer Dashboard-Kompatibilitaet) -->
    <div class="editor-tabs" style="display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0;">
        <a href="#" class="editor-tab <?php echo $current_tab === 'articles' ? 'active' : ''; ?>" data-editor-tab="articles">
            Artikel
        </a>
        <a href="#" class="editor-tab <?php echo $current_tab === 'reviewers' ? 'active' : ''; ?>" data-editor-tab="reviewers">
            Reviewer verwalten
        </a>
        <a href="#" class="editor-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>" data-editor-tab="settings">
            Einstellungen
        </a>
    </div>
    <script>
    jQuery(function($){
        $(document).on('click', '.editor-tab[data-editor-tab]', function(e){
            e.preventDefault();
            var tab = $(this).data('editor-tab');
            $('.editor-tab').removeClass('active');
            $(this).addClass('active');
            $('.editor-tab-content').hide();
            $('.editor-tab-content[data-editor-content="' + tab + '"]').show();
        });
    });
    </script>

    <div class="editor-tab-content" data-editor-content="articles" <?php echo $current_tab !== 'articles' ? 'style="display:none"' : ''; ?>>
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
                                        <?php if ($orcid = get_field('hauptautor_orcid', $view_id)): ?>
                                        <li>
                                            <span class="label">ORCID:</span>
                                            <span class="value">
                                                <a href="https://orcid.org/<?php echo esc_attr($orcid); ?>" target="_blank">
                                                    <?php echo esc_html($orcid); ?>
                                                </a>
                                            </span>
                                        </li>
                                        <?php endif; ?>
                                    </ul>

                                    <!-- Email Button -->
                                    <div style="margin-top: 15px;">
                                        <button type="button" class="email-action-btn preview-email-btn"
                                                data-article-id="<?php echo esc_attr($view_id); ?>"
                                                data-email-type="status_update"
                                                data-recipient-type="author">
                                            &#9993; E-Mail an Autor senden
                                        </button>
                                    </div>

                                    <?php if ($highlights = get_field('highlights', $view_id)): ?>
                                    <h4 style="margin-top: 20px;">Highlights</h4>
                                    <div style="background: #f0f9ff; padding: 15px; border-radius: 6px; border-left: 4px solid #3182ce;">
                                        <pre style="white-space: pre-wrap; margin: 0; font-family: inherit; font-size: 14px;"><?php echo esc_html($highlights); ?></pre>
                                    </div>
                                    <?php endif; ?>

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

                                    <hr style="margin: 20px 0;">

                                    <h4>Export</h4>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <a href="<?php echo esc_url(add_query_arg(['dgptm_artikel_pdf' => 1, 'artikel_id' => $view_id], home_url())); ?>" target="_blank" class="btn btn-secondary">
                                            PDF herunterladen
                                        </a>
                                        <button type="button" class="btn btn-secondary export-xml-btn" data-article-id="<?php echo esc_attr($view_id); ?>">
                                            XML exportieren (JATS)
                                        </button>
                                    </div>
                                    <p style="margin-top: 10px; font-size: 12px; color: #718096;">
                                        XML-Format: JATS 1.2 (Journal Article Tag Suite) - Standard für medizinische Publikationen
                                    </p>
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

                    <!-- Author Token & Dashboard Link -->
                    <div class="article-card" style="margin-top: 20px; background: #f0f9ff;">
                        <div class="article-card-header" style="background: #e0f2fe; border-color: #bae6fd;">
                            <h3 style="margin: 0; font-size: 16px; color: #0369a1;">Autoren-Zugang</h3>
                        </div>
                        <div class="article-card-body">
                            <?php
                            $author_token = get_field('author_token', $view_id);
                            $author_dashboard_url = $plugin->get_author_dashboard_url($view_id);
                            ?>
                            <?php if ($author_token): ?>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Token</label>
                                <code style="font-size: 11px; word-break: break-all; display: block; background: #fff; padding: 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                    <?php echo esc_html($author_token); ?>
                                </code>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Dashboard-URL</label>
                                <input type="text" readonly value="<?php echo esc_url($author_dashboard_url); ?>"
                                       style="width: 100%; font-size: 11px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;"
                                       onclick="this.select();">
                            </div>
                            <button type="button" class="btn btn-secondary" style="width: 100%;"
                                    onclick="navigator.clipboard.writeText('<?php echo esc_js($author_dashboard_url); ?>').then(function() { alert('Link kopiert!'); });">
                                Link kopieren
                            </button>
                            <?php else: ?>
                            <p style="margin: 0; color: #64748b; font-style: italic;">Kein Token vorhanden.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Communication Log -->
                    <div class="article-card" style="margin-top: 20px;">
                        <div class="article-card-header">
                            <h3 style="margin: 0; font-size: 16px;">Kommunikations-Verlauf</h3>
                        </div>
                        <div class="article-card-body">
                            <?php
                            $comm_log = $plugin->get_communication_log($view_id);
                            if (empty($comm_log)):
                            ?>
                                <p style="color: #718096; font-style: italic; margin: 0;">Noch keine Kommunikation.</p>
                            <?php else: ?>
                                <div class="communication-log" style="max-height: 300px; overflow-y: auto;">
                                    <?php
                                    // Sort by timestamp descending (newest first)
                                    usort($comm_log, function($a, $b) {
                                        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
                                    });
                                    foreach ($comm_log as $entry):
                                        $timestamp = $entry['timestamp'] ?? 0;
                                        $date = $timestamp ? date_i18n('d.m.Y H:i', $timestamp) : '-';
                                        $type_labels = [
                                            'status_update' => 'Status-Update',
                                            'reviewer_request' => 'Reviewer-Anfrage',
                                            'revision_request' => 'Revision',
                                            'accepted' => 'Annahme',
                                            'rejected' => 'Ablehnung',
                                            'custom' => 'Individuelle E-Mail'
                                        ];
                                        $type_label = $type_labels[$entry['type'] ?? 'custom'] ?? ($entry['type'] ?? 'E-Mail');
                                    ?>
                                    <div class="comm-entry" style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                            <span style="font-weight: 600; color: #1a365d;">
                                                <?php echo esc_html($type_label); ?>
                                            </span>
                                            <span style="color: #718096; font-size: 12px;">
                                                <?php echo esc_html($date); ?>
                                            </span>
                                        </div>
                                        <div style="color: #4a5568; margin-bottom: 4px;">
                                            <strong>Von:</strong> <?php echo esc_html($entry['user_name'] ?? 'System'); ?>
                                            &bull;
                                            <strong>An:</strong> <?php echo esc_html($entry['recipient'] ?? '-'); ?>
                                        </div>
                                        <div style="color: #64748b;">
                                            <strong>Betreff:</strong> <?php echo esc_html($entry['subject'] ?? '-'); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

            <!-- Email Preview Modal -->
            <div id="email-preview-modal" class="dgptm-modal-overlay">
                <div class="dgptm-modal" style="max-width: 800px;">
                    <div class="dgptm-modal-header">
                        <h3>E-Mail Vorschau</h3>
                        <button class="dgptm-modal-close">&times;</button>
                    </div>
                    <div class="dgptm-modal-body">
                        <div class="email-preview-info" style="background: #f0f9ff; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <p style="margin: 0;"><strong>An:</strong> <span id="email-recipient"></span></p>
                        </div>
                        <div class="form-row">
                            <label>Betreff</label>
                            <input type="text" id="email-subject" style="width: 100%; padding: 10px;">
                        </div>

                        <!-- Text Snippets -->
                        <div class="form-row" style="background: #f0fdf4; padding: 15px; border-radius: 6px; border: 1px solid #86efac;">
                            <label style="color: #166534;">Textbaustein einfügen</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <select id="snippet-category" style="padding: 8px; flex: 0 0 150px;">
                                    <option value="">Alle Kategorien</option>
                                    <option value="formal">Formale Prüfung</option>
                                    <option value="review">Review-Feedback</option>
                                    <option value="decision">Entscheidungen</option>
                                    <option value="general">Allgemein</option>
                                </select>
                                <select id="snippet-select" style="padding: 8px; flex: 1;">
                                    <option value="">-- Textbaustein wählen --</option>
                                </select>
                                <button type="button" class="btn btn-secondary" id="insert-snippet-btn" style="flex: 0 0 auto;">
                                    Einfügen
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <label>Nachricht <span style="font-weight: normal; color: #718096;">(Sie können den Text vor dem Senden anpassen)</span></label>
                            <textarea id="email-body" rows="15" style="width: 100%; padding: 10px; font-family: inherit;"></textarea>
                        </div>
                        <input type="hidden" id="email-article-id">
                        <input type="hidden" id="email-type">
                    </div>
                    <div class="dgptm-modal-footer">
                        <button class="btn btn-secondary modal-cancel">Abbrechen</button>
                        <button class="btn btn-primary" id="send-email-btn">E-Mail senden</button>
                    </div>
                </div>
            </div>

            <!-- Send Email Button (for article detail view) -->
            <style>
            .email-action-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                background: #e0f2fe;
                color: #0369a1;
                border: 1px solid #7dd3fc;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s;
            }
            .email-action-btn:hover {
                background: #bae6fd;
            }
            </style>

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

    </div><!-- /articles -->

    <div class="editor-tab-content" data-editor-content="reviewers" <?php echo $current_tab !== 'reviewers' ? 'style="display:none"' : ''; ?>>
        <!-- ============================================
             REVIEWERS TAB
             ============================================ -->
        <?php
        $pool = $plugin->get_reviewer_pool();
        $active_ids = [];
        $inactive_ids = [];
        foreach ($pool as $r) {
            if (is_array($r)) {
                if (!empty($r['active'])) $active_ids[] = intval($r['user_id']);
                else $inactive_ids[] = intval($r['user_id']);
            }
        }
        $active_reviewers = !empty($active_ids) ? get_users(['include' => $active_ids, 'orderby' => 'display_name']) : [];
        $inactive_reviewers = !empty($inactive_ids) ? get_users(['include' => $inactive_ids, 'orderby' => 'display_name']) : [];
        ?>

        <h3>Reviewer-Verwaltung</h3>

        <!-- Reviewer hinzufuegen: Suchfeld statt Dropdown -->
        <div style="margin-bottom: 16px;">
            <label style="font-weight: 500; font-size: 13px;">Reviewer suchen und hinzufuegen:</label>
            <div style="display: flex; gap: 8px; margin-top: 4px;">
                <input type="text" id="reviewer-search-input" placeholder="Name oder E-Mail eingeben..." style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
            </div>
            <div id="reviewer-search-results" style="border: 1px solid #eee; border-top: none; border-radius: 0 0 4px 4px; display: none; max-height: 200px; overflow-y: auto;"></div>
        </div>

        <!-- Aktive Reviewer -->
        <h4 style="font-size: 13px; color: #1d2327; margin: 16px 0 8px;">Aktive Reviewer (<?php echo count($active_reviewers); ?>)</h4>
        <?php if (empty($active_reviewers)): ?>
            <p style="color: #888; font-size: 13px;">Noch keine Reviewer hinzugefuegt.</p>
        <?php else: ?>
            <table class="dgptm-artikel-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th style="width: 160px;">Aktion</th>
                    </tr>
                </thead>
                <tbody id="reviewer-list">
                    <?php foreach ($active_reviewers as $reviewer): ?>
                    <tr data-user-id="<?php echo $reviewer->ID; ?>">
                        <td><strong><?php echo esc_html($reviewer->display_name); ?></strong></td>
                        <td><?php echo esc_html($reviewer->user_email); ?></td>
                        <td>
                            <button type="button" class="btn btn-secondary btn-toggle-reviewer" data-user-id="<?php echo $reviewer->ID; ?>" style="padding: 3px 8px; font-size: 11px;">Deaktivieren</button>
                            <button type="button" class="btn btn-danger remove-reviewer-btn" data-user-id="<?php echo $reviewer->ID; ?>" style="padding: 3px 8px; font-size: 11px;">Entfernen</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Inaktive Reviewer -->
        <?php if (!empty($inactive_reviewers)): ?>
            <h4 style="font-size: 13px; color: #888; margin: 16px 0 8px;">Inaktive Reviewer (<?php echo count($inactive_reviewers); ?>)</h4>
            <table class="dgptm-artikel-table" style="margin: 0; opacity: 0.6;">
                <tbody>
                    <?php foreach ($inactive_reviewers as $reviewer): ?>
                    <tr data-user-id="<?php echo $reviewer->ID; ?>" class="reviewer-inactive">
                        <td><strong><?php echo esc_html($reviewer->display_name); ?></strong></td>
                        <td><?php echo esc_html($reviewer->user_email); ?></td>
                        <td style="width: 160px;">
                            <button type="button" class="btn btn-primary btn-toggle-reviewer" data-user-id="<?php echo $reviewer->ID; ?>" style="padding: 3px 8px; font-size: 11px;">Aktivieren</button>
                            <button type="button" class="btn btn-danger remove-reviewer-btn" data-user-id="<?php echo $reviewer->ID; ?>" style="padding: 3px 8px; font-size: 11px;" data-permanent="1">Loeschen</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Externe Person einladen -->
        <div style="margin-top: 16px; padding: 12px; border: 1px dashed #ccc; border-radius: 4px; background: #f8f9fa;">
            <h4 style="font-size: 13px; margin: 0 0 8px; color: #1d2327;">Externe Person als Reviewer einladen</h4>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="ext-reviewer-first" placeholder="Vorname" style="padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; width: 120px;">
                <input type="text" id="ext-reviewer-last" placeholder="Nachname *" style="padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; width: 120px;">
                <input type="email" id="ext-reviewer-email" placeholder="E-Mail *" style="padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; flex: 1; min-width: 200px;">
                <button type="button" id="ext-reviewer-invite" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Einladen</button>
            </div>
            <small style="color: #888; font-size: 11px; display: block; margin-top: 4px;">WP-Benutzer wird angelegt, Einladungs-E-Mail versendet, CRM-Contact erstellt.</small>
        </div>

        <script>
        jQuery(function($){
            // Externe Person einladen
            $('#ext-reviewer-invite').on('click', function(){
                var fn = $('#ext-reviewer-first').val().trim();
                var ln = $('#ext-reviewer-last').val().trim();
                var em = $('#ext-reviewer-email').val().trim();

                if (!ln || !em) {
                    alert('Bitte Nachname und E-Mail angeben.');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('Wird angelegt...');

                $.post(dgptmArtikel.ajaxUrl, {
                    action: 'dgptm_add_reviewer',
                    nonce: dgptmArtikel.nonce,
                    user_id: 0,
                    email: em,
                    first_name: fn,
                    last_name: ln,
                    zoho_id: ''
                }, function(res){
                    $btn.prop('disabled', false).text('Einladen');
                    if (res.success) {
                        alert('Reviewer eingeladen: ' + (res.data.reviewer ? res.data.reviewer.name : em));
                        location.reload();
                    } else {
                        alert(res.data.message || 'Fehler');
                    }
                });
            });

            var searchTimeout;
            $('#reviewer-search-input').on('input', function(){
                clearTimeout(searchTimeout);
                var q = $(this).val().trim();
                if (q.length < 2) { $('#reviewer-search-results').hide(); return; }

                searchTimeout = setTimeout(function(){
                    $.post(dgptmArtikel.ajaxUrl, {
                        action: 'dgptm_search_users',
                        nonce: dgptmArtikel.nonce,
                        search: q
                    }, function(res){
                        if (!res.success || !res.data.users.length) {
                            $('#reviewer-search-results').html('<div style="padding:8px;color:#888;font-size:12px;">Keine Treffer</div>').show();
                            return;
                        }
                        var html = '';
                        res.data.users.forEach(function(u){
                            var badge = u.source === 'crm' ? '<span class="ae-source-badge ae-source-crm">CRM</span>' : '<span class="ae-source-badge ae-source-wp">WP</span>';
                            html += '<div class="reviewer-search-item" style="padding:8px 10px;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:13px;" data-user-id="' + u.id + '" data-email="' + (u.email||'') + '" data-first-name="' + (u.first_name||'') + '" data-last-name="' + (u.last_name||'') + '" data-zoho-id="' + (u.zoho_id||'') + '">';
                            html += '<strong>' + u.name + '</strong> ' + badge + '<br><small>' + (u.email||'') + '</small></div>';
                        });
                        $('#reviewer-search-results').html(html).show();
                    });
                }, 300);
            });

            $(document).on('click', '.reviewer-search-item', function(){
                var $item = $(this);
                $.post(dgptmArtikel.ajaxUrl, {
                    action: 'dgptm_add_reviewer',
                    nonce: dgptmArtikel.nonce,
                    user_id: $item.data('user-id'),
                    email: $item.data('email'),
                    first_name: $item.data('first-name'),
                    last_name: $item.data('last-name'),
                    zoho_id: $item.data('zoho-id')
                }, function(res){
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data.message || 'Fehler');
                    }
                });
            });

            // Hover-Effekt
            $(document).on('mouseenter', '.reviewer-search-item', function(){ $(this).css('background', '#f8f9fa'); });
            $(document).on('mouseleave', '.reviewer-search-item', function(){ $(this).css('background', ''); });
        });
        </script>

    </div><!-- /reviewers -->

    <div class="editor-tab-content" data-editor-content="settings" <?php echo $current_tab !== 'settings' ? 'style="display:none"' : ''; ?>>
        <!-- ============================================
             SETTINGS TAB
             ============================================ -->
        <?php
        // Get current settings
        $settings = get_option(DGPTM_Artikel_Einreichung::OPT_SETTINGS, []);

        // Default email templates
        $default_templates = [
            'master_header' => '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
<div style="background: #1a365d; color: #fff; padding: 20px; text-align: center;">
<h1 style="margin: 0; font-size: 24px;">Die Perfusiologie</h1>
</div>
<div style="padding: 30px; background: #fff;">',
            'master_footer' => '</div>
<div style="background: #f7fafc; padding: 20px; text-align: center; font-size: 12px; color: #718096;">
<p>Deutsche Gesellschaft für Prävention und Telemedizin e.V.</p>
<p>Bei Fragen wenden Sie sich an: redaktion@perfusiologie.de</p>
</div>
</div>',
            'email_submission' => [
                'enabled' => 1,
                'subject' => 'Vielen Dank für Ihre Einreichung - {submission_id}',
                'body' => '<p>Sehr geehrte/r {author_name},</p>
<p>vielen Dank für die Einreichung Ihres Artikels <strong>"{title}"</strong> bei Die Perfusiologie.</p>
<p><strong>Ihre Einreichungs-ID:</strong> {submission_id}</p>
<p>Sie können den Status Ihrer Einreichung jederzeit über folgenden Link einsehen:</p>
<p><a href="{dashboard_url}" style="display: inline-block; background: #3182ce; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Zum Autoren-Dashboard</a></p>
<p>Wir werden Ihren Artikel prüfen und uns in Kürze bei Ihnen melden.</p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ],
            'email_reviewer_request' => [
                'enabled' => 1,
                'subject' => 'Gutachten-Anfrage: {submission_id}',
                'body' => '<p>Sehr geehrte/r {reviewer_name},</p>
<p>wir möchten Sie bitten, ein Gutachten für folgenden Artikel zu erstellen:</p>
<p><strong>Artikel-ID:</strong> {submission_id}<br>
<strong>Titel:</strong> {title}<br>
<strong>Publikationsart:</strong> {publication_type}</p>
<p>Bitte klicken Sie auf folgenden Link, um das Manuskript einzusehen und Ihr Gutachten abzugeben:</p>
<p><a href="{review_url}" style="display: inline-block; background: #3182ce; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Zum Review-Dashboard</a></p>
<p>Wir bitten um Rückmeldung innerhalb von 3 Wochen.</p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ],
            'email_revision_request' => [
                'enabled' => 1,
                'subject' => 'Überarbeitung erforderlich: {submission_id}',
                'body' => '<p>Sehr geehrte/r {author_name},</p>
<p>die Begutachtung Ihres Artikels <strong>"{title}"</strong> ist abgeschlossen.</p>
<p>Die Gutachter haben eine <strong>Überarbeitung</strong> empfohlen. Bitte beachten Sie die folgenden Hinweise:</p>
<div style="background: #fef3c7; padding: 15px; border-radius: 4px; margin: 15px 0;">
{decision_letter}
</div>
<p>Bitte laden Sie Ihre überarbeitete Version über folgenden Link hoch:</p>
<p><a href="{dashboard_url}" style="display: inline-block; background: #d69e2e; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Revision einreichen</a></p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ],
            'email_accepted' => [
                'enabled' => 1,
                'subject' => 'Artikel angenommen: {submission_id}',
                'body' => '<p>Sehr geehrte/r {author_name},</p>
<p>wir freuen uns, Ihnen mitteilen zu können, dass Ihr Artikel <strong>"{title}"</strong> zur Veröffentlichung in Die Perfusiologie <strong>angenommen</strong> wurde.</p>
<div style="background: #c6f6d5; padding: 15px; border-radius: 4px; margin: 15px 0;">
{decision_letter}
</div>
<p>Wir werden Sie über die weiteren Schritte zur Veröffentlichung informieren.</p>
<p>Herzlichen Glückwunsch und vielen Dank für Ihren Beitrag!</p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ],
            'email_rejected' => [
                'enabled' => 1,
                'subject' => 'Artikel nicht angenommen: {submission_id}',
                'body' => '<p>Sehr geehrte/r {author_name},</p>
<p>nach sorgfältiger Prüfung müssen wir Ihnen leider mitteilen, dass Ihr Artikel <strong>"{title}"</strong> nicht zur Veröffentlichung in Die Perfusiologie angenommen werden kann.</p>
<div style="background: #fed7d7; padding: 15px; border-radius: 4px; margin: 15px 0;">
{decision_letter}
</div>
<p>Wir danken Ihnen für Ihr Interesse an unserer Zeitschrift und wünschen Ihnen für Ihre weitere wissenschaftliche Arbeit viel Erfolg.</p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ],
            'email_status_update' => [
                'enabled' => 0,
                'subject' => 'Statusänderung: {submission_id}',
                'body' => '<p>Sehr geehrte/r {author_name},</p>
<p>der Status Ihres Artikels <strong>"{title}"</strong> wurde aktualisiert:</p>
<p><strong>Neuer Status:</strong> {status}</p>
<p>Sie können den aktuellen Status jederzeit über folgenden Link einsehen:</p>
<p><a href="{dashboard_url}" style="display: inline-block; background: #3182ce; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Zum Autoren-Dashboard</a></p>
<p>Mit freundlichen Grüßen,<br>Die Redaktion</p>'
            ]
        ];

        // Merge with saved settings
        foreach ($default_templates as $key => $default) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }
        ?>

        <h2>Einstellungen</h2>

        <form id="settings-form">
            <!-- General Settings -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Allgemeine Einstellungen</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row" style="margin-bottom: 15px;">
                        <label for="notification_email">Benachrichtigungs-E-Mail (Redaktion):</label>
                        <input type="email" id="notification_email" name="notification_email"
                               value="<?php echo esc_attr($settings['notification_email'] ?? ''); ?>"
                               style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    </div>
                    <div class="form-row" style="margin-bottom: 15px;">
                        <label for="max_file_size">Maximale Dateigröße (MB):</label>
                        <input type="number" id="max_file_size" name="max_file_size"
                               value="<?php echo esc_attr($settings['max_file_size'] ?? 20); ?>" min="1" max="100"
                               style="width: 100px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <span style="color: #718096; font-size: 13px; margin-left: 10px;">Server-Limit: <?php echo esc_html(ini_get('upload_max_filesize')); ?></span>
                    </div>
                    <div class="form-row">
                        <label for="review_instructions">Review-Anweisungen:</label>
                        <textarea id="review_instructions" name="review_instructions" rows="4"
                                  style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;"><?php echo esc_textarea($settings['review_instructions'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Placeholder Reference -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">Verfügbare Platzhalter</h3>
                </div>
                <div class="article-card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px;">
                        <div><code>{author_name}</code> - Name des Autors</div>
                        <div><code>{author_email}</code> - E-Mail des Autors</div>
                        <div><code>{title}</code> - Artikeltitel</div>
                        <div><code>{submission_id}</code> - Einreichungs-ID</div>
                        <div><code>{publication_type}</code> - Publikationsart</div>
                        <div><code>{status}</code> - Aktueller Status</div>
                        <div><code>{dashboard_url}</code> - Link zum Autoren-Dashboard</div>
                        <div><code>{review_url}</code> - Link zum Review-Dashboard</div>
                        <div><code>{reviewer_name}</code> - Name des Reviewers</div>
                        <div><code>{decision_letter}</code> - Entscheidungsschreiben</div>
                        <div><code>{date}</code> - Aktuelles Datum</div>
                    </div>
                </div>
            </div>

            <!-- Master Template -->
            <div class="article-card" style="margin-bottom: 20px;">
                <div class="article-card-header">
                    <h3 style="margin: 0;">E-Mail Master-Template</h3>
                </div>
                <div class="article-card-body">
                    <div class="form-row" style="margin-bottom: 15px;">
                        <label>Header (wird vor jeder E-Mail eingefügt):</label>
                        <textarea name="master_header" rows="6" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($settings['master_header'] ?? $default_templates['master_header']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <label>Footer (wird nach jeder E-Mail eingefügt):</label>
                        <textarea name="master_footer" rows="4" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($settings['master_footer'] ?? $default_templates['master_footer']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Email Templates -->
            <?php
            $email_types = [
                'email_submission' => ['title' => 'Einreichungsbestätigung', 'desc' => 'Wird nach erfolgreicher Einreichung an den Autor gesendet'],
                'email_reviewer_request' => ['title' => 'Reviewer-Anfrage', 'desc' => 'Wird an Reviewer gesendet, wenn ihnen ein Artikel zugewiesen wird'],
                'email_revision_request' => ['title' => 'Überarbeitungsanforderung', 'desc' => 'Wird an den Autor gesendet, wenn eine Revision erforderlich ist'],
                'email_accepted' => ['title' => 'Annahme', 'desc' => 'Wird an den Autor gesendet, wenn der Artikel angenommen wird'],
                'email_rejected' => ['title' => 'Ablehnung', 'desc' => 'Wird an den Autor gesendet, wenn der Artikel abgelehnt wird'],
                'email_status_update' => ['title' => 'Statusmeldung', 'desc' => 'Allgemeine Statusbenachrichtigung an den Autor']
            ];

            foreach ($email_types as $key => $info):
                $template = $settings[$key] ?? $default_templates[$key];
            ?>
            <div class="article-card email-template-card" style="margin-bottom: 20px;">
                <div class="article-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0;"><?php echo esc_html($info['title']); ?></h3>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #718096;"><?php echo esc_html($info['desc']); ?></p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="<?php echo esc_attr($key); ?>_enabled" value="1"
                               <?php checked($template['enabled'] ?? 0, 1); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="article-card-body">
                    <div class="form-row" style="margin-bottom: 15px;">
                        <label>Betreff:</label>
                        <input type="text" name="<?php echo esc_attr($key); ?>_subject"
                               value="<?php echo esc_attr($template['subject'] ?? ''); ?>"
                               style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    </div>
                    <div class="form-row">
                        <label>Inhalt (HTML):</label>
                        <textarea name="<?php echo esc_attr($key); ?>_body" rows="8"
                                  style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($template['body'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
        </form>

        <style>
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: 0.3s;
            border-radius: 26px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background-color: #38a169;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        </style>

    </div><!-- /settings -->

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
    cursor: pointer;
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

/* Dashboard-filigran */
.dgptm-dash .editor-tabs { margin-bottom: 12px; border-bottom: 1px solid #eee; }
.dgptm-dash .editor-tab { padding: 6px 12px; font-size: 12px; font-weight: 400; color: #888; }
.dgptm-dash .editor-tab:hover { color: #1d2327; background: #f8f9fa; }
.dgptm-dash .editor-tab.active { color: #0073aa; border-bottom-color: #0073aa; font-weight: 600; }
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

    // Export XML (JATS format)
    $(document).on('click', '.export-xml-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const articleId = $btn.data('article-id');
        const originalText = $btn.text();

        console.log('JATS Export gestartet für Artikel:', articleId);

        if (!articleId) {
            alert('Fehler: Keine Artikel-ID gefunden.');
            return;
        }

        $btn.prop('disabled', true).text('Wird generiert...');

        $.ajax({
            url: dgptmArtikel.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_export_xml',
                nonce: dgptmArtikel.nonce,
                article_id: articleId
            },
            success: function(response) {
                console.log('JATS Export Response:', response);
                $btn.prop('disabled', false).text(originalText);

                if (response.success && response.data && response.data.xml) {
                    // Create blob and download
                    const blob = new Blob([response.data.xml], { type: 'application/xml; charset=utf-8' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = response.data.filename || 'artikel-export.xml';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function() {
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    }, 100);
                    console.log('JATS Export erfolgreich:', response.data.filename);
                } else {
                    console.error('JATS Export Fehler:', response);
                    alert(response.data && response.data.message ? response.data.message : 'Fehler beim Exportieren.');
                }
            },
            error: function(xhr, status, error) {
                console.error('JATS Export AJAX Fehler:', status, error, xhr.responseText);
                $btn.prop('disabled', false).text(originalText);
                alert('Verbindungsfehler: ' + error);
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

    // Remove reviewer (permanent flag from data attribute)
    $(document).on('click', '.remove-reviewer-btn', function() {
        var userId = parseInt($(this).data('user-id'));
        var permanent = $(this).data('permanent') ? true : false;
        var msg = permanent ? 'Reviewer endgueltig loeschen?' : 'Reviewer deaktivieren?';

        if (!confirm(msg)) return;

        $.ajax({
            url: dgptmArtikel.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_remove_reviewer',
                nonce: dgptmArtikel.nonce,
                user_id: userId,
                permanent: permanent ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler.');
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
