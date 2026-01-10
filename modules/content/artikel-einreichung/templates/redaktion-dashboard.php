<?php
/**
 * Template: Redaktions-Dashboard (Frontend)
 * Shortcode: [artikel_redaktion]
 *
 * Für Benutzer mit redaktion_perfusiologie Berechtigung
 * WICHTIG: Redaktion sieht KEINE Reviewer-Namen (Anonymität)
 *          und kann KEINE Reviewer zuweisen
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$user_id = get_current_user_id();

// Check permission
$is_redaktion = $plugin->is_redaktion($user_id);
$is_editor = $plugin->is_editor_in_chief($user_id);

if (!$is_redaktion && !$is_editor) {
    echo '<div class="dgptm-artikel-notice notice-error"><p>Sie haben keine Berechtigung für diesen Bereich.</p></div>';
    return;
}

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
    'submitted' => 0,
    'in_review' => 0,
    'revision' => 0,
    'accepted' => 0,
    'exported' => 0,
    'rejected' => 0,
    'published' => 0
];

foreach ($articles as $art) {
    $stats['total']++;
    $st = get_field('artikel_status', $art->ID);
    switch ($st) {
        case DGPTM_Artikel_Einreichung::STATUS_SUBMITTED:
            $stats['submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW:
            $stats['in_review']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED:
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED:
            $stats['revision']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_ACCEPTED:
            $stats['accepted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_EXPORTED:
            $stats['exported']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REJECTED:
            $stats['rejected']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_PUBLISHED:
            $stats['published']++;
            break;
    }
}

// Check if viewing single article
$view_id = isset($_GET['redaktion_artikel_id']) ? intval($_GET['redaktion_artikel_id']) : 0;
$view_article = null;
if ($view_id) {
    $view_article = get_post($view_id);
    if (!$view_article || $view_article->post_type !== DGPTM_Artikel_Einreichung::POST_TYPE) {
        $view_article = null;
        $view_id = 0;
    }
}
?>

<div class="dgptm-artikel-container">

    <?php if ($view_article): ?>
        <!-- Single Article View (Redaktion - Read Only, Anonymized) -->
        <?php
        $status = get_field('artikel_status', $view_id);
        $submission_id = get_field('submission_id', $view_id);

        // Reviewer status (anonymized - only show if reviews are done, not who did them)
        $r1_status = get_field('reviewer_1_status', $view_id);
        $r2_status = get_field('reviewer_2_status', $view_id);
        $reviews_completed = 0;
        if ($r1_status === 'completed') $reviews_completed++;
        if ($r2_status === 'completed') $reviews_completed++;
        ?>

        <a href="<?php echo esc_url(remove_query_arg('redaktion_artikel_id')); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &larr; Zurück zur Übersicht
        </a>

        <div class="article-card">
            <div class="article-card-header">
                <span class="submission-id"><?php echo esc_html($submission_id); ?></span>
                <h3><?php echo esc_html($view_article->post_title); ?></h3>
                <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                    <?php echo esc_html($plugin->get_status_label($status)); ?>
                </span>
            </div>

            <div class="article-card-body">
                <!-- Basic Article Info -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h4>Artikeldetails</h4>
                        <ul class="info-list">
                            <li>
                                <span class="label">Publikationsart:</span>
                                <span class="value"><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $view_id)] ?? '-'); ?></span>
                            </li>
                            <?php if ($unter = get_field('unterueberschrift', $view_id)): ?>
                            <li>
                                <span class="label">Untertitel:</span>
                                <span class="value"><?php echo esc_html($unter); ?></span>
                            </li>
                            <?php endif; ?>
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
                        </ul>
                    </div>

                    <div>
                        <h4>Prozess-Status</h4>
                        <ul class="info-list">
                            <li>
                                <span class="label">Eingereicht am:</span>
                                <span class="value"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime(get_field('submitted_at', $view_id)))); ?></span>
                            </li>
                            <li>
                                <span class="label">Review-Status:</span>
                                <span class="value">
                                    <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED): ?>
                                        Warten auf Reviewer-Zuweisung
                                    <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW): ?>
                                        <?php echo $reviews_completed; ?> von 2 Reviews abgeschlossen
                                    <?php else: ?>
                                        <?php echo esc_html($plugin->get_status_label($status)); ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                            <?php if ($decision_at = get_field('decision_at', $view_id)): ?>
                            <li>
                                <span class="label">Entscheidung am:</span>
                                <span class="value"><?php echo esc_html(date_i18n('d.m.Y', strtotime($decision_at))); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <!-- Note: Reviewer names are NOT shown to Redaktion -->
                        <div class="dgptm-artikel-notice notice-info" style="margin-top: 15px;">
                            <p><strong>Hinweis:</strong> Die Namen der Reviewer werden aus Gründen der Anonymität nicht angezeigt.</p>
                        </div>
                    </div>
                </div>

                <!-- Ko-Autoren -->
                <?php if ($koautoren = get_field('autoren', $view_id)): ?>
                <h4 style="margin-top: 25px;">Ko-Autoren</h4>
                <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                    <?php echo esc_html($koautoren); ?>
                </div>
                <?php endif; ?>

                <!-- Abstract -->
                <h4 style="margin-top: 25px;">Abstract (Deutsch)</h4>
                <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                    <?php echo esc_html(get_field('abstract-deutsch', $view_id)); ?>
                </div>

                <?php if ($abstract_en = get_field('abstract', $view_id)): ?>
                <h4 style="margin-top: 20px;">Abstract (English)</h4>
                <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                    <?php echo esc_html($abstract_en); ?>
                </div>
                <?php endif; ?>

                <!-- Keywords -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <?php if ($kw_de = get_field('keywords-deutsch', $view_id)): ?>
                    <div>
                        <h4>Schlüsselwörter (Deutsch)</h4>
                        <p><?php echo esc_html($kw_de); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($kw_en = get_field('keywords-englisch', $view_id)): ?>
                    <div>
                        <h4>Keywords (English)</h4>
                        <p><?php echo esc_html($kw_en); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Files (read-only) -->
                <h4 style="margin-top: 25px;">Dateien</h4>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php
                    $manuskript = get_field('manuskript', $view_id);
                    if ($manuskript):
                    ?>
                        <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="btn btn-secondary">
                            Manuskript herunterladen
                        </a>
                    <?php endif; ?>

                    <?php
                    $revision = get_field('revision_manuskript', $view_id);
                    if ($revision):
                    ?>
                        <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-secondary">
                            Revision herunterladen
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Decision Letter (if available and article is decided) -->
                <?php
                $decision_letter = get_field('decision_letter', $view_id);
                if ($decision_letter && in_array($status, [
                    DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                    DGPTM_Artikel_Einreichung::STATUS_REJECTED,
                    DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED
                ])):
                ?>
                <h4 style="margin-top: 25px;">Decision Letter</h4>
                <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px; border-left: 4px solid #3182ce;">
                    <?php echo esc_html($decision_letter); ?>
                </div>
                <?php endif; ?>

                <!-- Export & Aktionen (nur für angenommene/exportierte Artikel) -->
                <?php if (in_array($status, [
                    DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                    DGPTM_Artikel_Einreichung::STATUS_EXPORTED,
                    DGPTM_Artikel_Einreichung::STATUS_PUBLISHED
                ])): ?>
                <hr style="margin: 25px 0;">

                <h4>Export & Veröffentlichung</h4>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                    <!-- XML Export -->
                    <button type="button" class="btn btn-secondary export-xml-btn" data-article-id="<?php echo esc_attr($view_id); ?>">
                        XML exportieren (JATS)
                    </button>

                    <!-- PDF Export -->
                    <a href="<?php echo esc_url(add_query_arg(['dgptm_artikel_pdf' => 1, 'artikel_id' => $view_id], home_url())); ?>" target="_blank" class="btn btn-secondary">
                        PDF herunterladen
                    </a>
                </div>

                <!-- Status-Änderung -->
                <div class="redaktion-status-panel" style="background: #f0f9ff; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd; margin-top: 15px;">
                    <h5 style="margin: 0 0 15px 0; color: #0369a1;">Status ändern</h5>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED): ?>
                            <button type="button" class="btn change-status-btn" style="background: #0d9488; color: #fff;"
                                    data-article-id="<?php echo esc_attr($view_id); ?>"
                                    data-status="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_EXPORTED); ?>">
                                Als "Exportiert" markieren
                            </button>
                            <span style="color: #64748b; font-size: 13px;">→ Artikel wurde für Druckversion exportiert</span>

                        <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED): ?>
                            <button type="button" class="btn publish-artikel-btn" style="background: #7c3aed; color: #fff;"
                                    data-article-id="<?php echo esc_attr($view_id); ?>">
                                Online veröffentlichen
                            </button>
                            <span style="color: #64748b; font-size: 13px;">→ Erstellt Beitrag in "Publikationen" und setzt Status auf "Veröffentlicht"</span>

                        <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED): ?>
                            <?php
                            $publikation_id = get_field('publikation_id', $view_id);
                            $published_at = get_field('published_at', $view_id);
                            ?>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="status-badge status-purple" style="font-size: 14px;">✓ Veröffentlicht</span>
                                <?php if ($published_at): ?>
                                    <span style="color: #64748b; font-size: 13px;">am <?php echo esc_html(date_i18n('d.m.Y', strtotime($published_at))); ?></span>
                                <?php endif; ?>
                                <?php if ($publikation_id): ?>
                                    <a href="<?php echo esc_url(get_permalink($publikation_id)); ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                        Publikation ansehen →
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    <?php else: ?>
        <!-- Dashboard Overview -->
        <h2>Redaktions-Übersicht</h2>
        <p style="color: #718096; margin-bottom: 20px;">
            Übersicht aller eingereichten Artikel. Reviewer-Namen werden aus Anonymitätsgründen nicht angezeigt.
        </p>

        <!-- Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #1a365d;"><?php echo $stats['total']; ?></div>
                <div style="color: #718096; font-size: 12px;">Gesamt</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #3182ce;"><?php echo $stats['submitted']; ?></div>
                <div style="color: #718096; font-size: 12px;">Eingereicht</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #d69e2e;"><?php echo $stats['in_review']; ?></div>
                <div style="color: #718096; font-size: 12px;">Im Review</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #805ad5;"><?php echo $stats['revision']; ?></div>
                <div style="color: #718096; font-size: 12px;">Revision</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #38a169;"><?php echo $stats['accepted']; ?></div>
                <div style="color: #718096; font-size: 12px;">Angenommen</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #14b8a6;"><?php echo $stats['exported']; ?></div>
                <div style="color: #718096; font-size: 12px;">Exportiert</div>
            </div>
            <div class="article-card" style="text-align: center; padding: 15px;">
                <div style="font-size: 28px; font-weight: 700; color: #9f7aea;"><?php echo $stats['published']; ?></div>
                <div style="color: #718096; font-size: 12px;">Publiziert</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="<?php echo esc_url(remove_query_arg('status')); ?>"
               class="btn <?php echo !$filter_status ? 'btn-primary' : 'btn-secondary'; ?>">Alle</a>
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_SUBMITTED)); ?>"
               class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? 'btn-primary' : 'btn-secondary'; ?>">Eingereicht</a>
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW)); ?>"
               class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? 'btn-primary' : 'btn-secondary'; ?>">Im Review</a>
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_ACCEPTED)); ?>"
               class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? 'btn-primary' : 'btn-secondary'; ?>">Angenommen</a>
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_EXPORTED)); ?>"
               class="btn <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? 'btn-primary' : 'btn-secondary'; ?>">Exportiert</a>
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
                        <th>Art</th>
                        <th>Status</th>
                        <th>Eingereicht</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article):
                        $status = get_field('artikel_status', $article->ID);
                        $submission_id = get_field('submission_id', $article->ID);
                        $submitted_at = get_field('submitted_at', $article->ID);
                        $pub_art = get_field('publikationsart', $article->ID);
                    ?>
                    <tr>
                        <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                        <td>
                            <div class="article-title"><?php echo esc_html($article->post_title); ?></div>
                        </td>
                        <td><?php echo esc_html(get_field('hauptautorin', $article->ID)); ?></td>
                        <td style="font-size: 12px;"><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[$pub_art] ?? '-'); ?></td>
                        <td>
                            <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                <?php echo esc_html($plugin->get_status_label($status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('redaktion_artikel_id', $article->ID)); ?>" class="btn btn-secondary">
                                Ansehen
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
jQuery(document).ready(function($) {
    var config = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>'
    };

    // Export XML (JATS format)
    $(document).on('click', '.export-xml-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Wird generiert...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_export_xml',
                nonce: config.nonce,
                article_id: articleId
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);

                if (response.success) {
                    // Create blob and download
                    var blob = new Blob([response.data.xml], { type: 'application/xml' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert(response.data.message || 'Fehler beim Exportieren.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('Verbindungsfehler.');
            }
        });
    });

    // Change status
    $(document).on('click', '.change-status-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var newStatus = $btn.data('status');
        var originalText = $btn.text();

        if (!confirm('Status wirklich ändern?')) {
            return;
        }

        $btn.prop('disabled', true).text('Wird gespeichert...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_change_artikel_status',
                nonce: config.nonce,
                article_id: articleId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated status
                    location.reload();
                } else {
                    $btn.prop('disabled', false).text(originalText);
                    alert(response.data.message || 'Fehler beim Ändern des Status.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('Verbindungsfehler.');
            }
        });
    });

    // Publish article
    $(document).on('click', '.publish-artikel-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var originalText = $btn.text();

        if (!confirm('Artikel jetzt online veröffentlichen?\n\nDies erstellt einen neuen Beitrag im Bereich "Publikationen".')) {
            return;
        }

        $btn.prop('disabled', true).text('Wird veröffentlicht...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_publish_artikel',
                nonce: config.nonce,
                article_id: articleId
            },
            success: function(response) {
                if (response.success) {
                    alert('Artikel erfolgreich veröffentlicht!\n\nPublikation erstellt mit ID: ' + response.data.publikation_id);
                    // Reload page to show updated status
                    location.reload();
                } else {
                    $btn.prop('disabled', false).text(originalText);
                    alert(response.data.message || 'Fehler beim Veröffentlichen.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('Verbindungsfehler.');
            }
        });
    });
});
</script>

<style>
.status-teal {
    background-color: #14b8a6 !important;
    color: #fff !important;
}
</style>
