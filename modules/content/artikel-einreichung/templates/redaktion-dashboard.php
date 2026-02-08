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

// Query ALL articles for statistics (without filter)
$all_articles = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Statistics - always count ALL articles
$stats = [
    'total' => 0,
    'submitted' => 0,
    'formal_check' => 0,
    'in_review' => 0,
    'revision_required' => 0,
    'revision_submitted' => 0,
    'accepted' => 0,
    'exported' => 0,
    'lektorat' => 0,
    'gesetzt' => 0,
    'rejected' => 0,
    'published' => 0
];

foreach ($all_articles as $art) {
    $stats['total']++;
    $st = get_field('artikel_status', $art->ID);
    switch ($st) {
        case DGPTM_Artikel_Einreichung::STATUS_SUBMITTED:
            $stats['submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK:
            $stats['formal_check']++;
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
        case DGPTM_Artikel_Einreichung::STATUS_EXPORTED:
            $stats['exported']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_LEKTORAT:
            $stats['lektorat']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_GESETZT:
            $stats['gesetzt']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REJECTED:
            $stats['rejected']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_PUBLISHED:
            $stats['published']++;
            break;
    }
}

// Get filtered articles for display (apply filter if set)
if ($filter_status) {
    $articles = array_filter($all_articles, function($art) use ($filter_status) {
        return get_field('artikel_status', $art->ID) === $filter_status;
    });
    $articles = array_values($articles); // Re-index array
} else {
    $articles = $all_articles;
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
                            <?php
                            $ausgabe_value = get_field('ausgabe', $view_id);
                            ?>
                            <li>
                                <span class="label">Ausgabe:</span>
                                <span class="value" style="<?php echo $ausgabe_value ? 'color: #6366f1; font-weight: 600;' : ''; ?>">
                                    <?php echo $ausgabe_value ? esc_html($ausgabe_value) : '<em style="color: #94a3b8;">Nicht zugewiesen</em>'; ?>
                                </span>
                            </li>
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
                <!--
                    DATEISPEICHERORT:
                    Alle hochgeladenen Dateien werden als WordPress Media Library Attachments gespeichert.
                    Physischer Pfad: wp-content/uploads/YYYY/MM/filename.ext
                    Die Attachments werden mit dem Artikel-Post verknüpft.
                -->
                <h4 style="margin-top: 25px;">Dateien</h4>
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php
                    $manuskript = get_field('manuskript', $view_id);
                    $revision = get_field('revision_manuskript', $view_id);
                    $abbildungen = get_field('abbildungen', $view_id);
                    $tabellen = get_field('tabellen', $view_id);
                    $supplementary = get_field('supplementary_material', $view_id);

                    // Helper function to get upload date
                    $get_upload_date = function($attachment) {
                        if (!$attachment || !isset($attachment['ID'])) return '';
                        $post = get_post($attachment['ID']);
                        return $post ? date_i18n('d.m.Y H:i', strtotime($post->post_date)) : '';
                    };

                    // Helper function to format file size
                    $format_size = function($attachment) {
                        if (!$attachment || !isset($attachment['filesize'])) return '';
                        $bytes = $attachment['filesize'];
                        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
                        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
                        return $bytes . ' Bytes';
                    };

                    // Determine current version
                    $current_version = $revision ? 'Revision' : ($manuskript ? 'Original' : 'Keine');
                    ?>

                    <!-- Version Info -->
                    <div style="margin-bottom: 15px; padding: 10px; background: #e0f2fe; border-radius: 6px; border-left: 4px solid #0284c7;">
                        <strong style="color: #0369a1;">Aktuelle Version:</strong>
                        <span style="color: #0c4a6e; font-weight: 600; margin-left: 8px;">
                            <?php echo esc_html($current_version); ?>
                            <?php if ($revision): ?>
                                (hochgeladen: <?php echo esc_html($get_upload_date($revision)); ?>)
                            <?php elseif ($manuskript): ?>
                                (hochgeladen: <?php echo esc_html($get_upload_date($manuskript)); ?>)
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Manuskript (Original) -->
                    <?php if ($manuskript): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: <?php echo $revision ? '#f1f5f9' : '#dbeafe'; ?>; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <strong style="color: #1e40af;">
                                    Manuskript <?php echo $revision ? '(Original)' : '(Aktuell)'; ?>:
                                </strong>
                                <span style="color: #64748b; font-size: 12px; margin-left: 8px;">
                                    <?php echo esc_html($format_size($manuskript)); ?> | <?php echo esc_html($get_upload_date($manuskript)); ?>
                                </span>
                            </div>
                            <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                <?php echo esc_html($manuskript['filename']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Revision (wenn vorhanden = aktuelle Version) -->
                    <?php if ($revision): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #f3e8ff; border-radius: 6px; border-left: 4px solid #7c3aed;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <strong style="color: #7c3aed;">
                                    Revision (Aktuell):
                                </strong>
                                <span style="color: #64748b; font-size: 12px; margin-left: 8px;">
                                    <?php echo esc_html($format_size($revision)); ?> | <?php echo esc_html($get_upload_date($revision)); ?>
                                </span>
                            </div>
                            <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-sm" style="background: #7c3aed; color: #fff;">
                                <?php echo esc_html($revision['filename']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Abbildungen (Gallery) -->
                    <?php if ($abbildungen && is_array($abbildungen)): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #ecfdf5; border-radius: 6px;">
                        <strong style="color: #059669;">Abbildungen (<?php echo count($abbildungen); ?>):</strong>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                            <?php foreach ($abbildungen as $index => $img):
                                $img_date = $get_upload_date($img);
                            ?>
                                <a href="<?php echo esc_url($img['url']); ?>" target="_blank" class="btn btn-sm btn-secondary" title="Hochgeladen: <?php echo esc_attr($img_date); ?>">
                                    Abb. <?php echo ($index + 1); ?> (<?php echo esc_html(strtoupper(pathinfo($img['filename'], PATHINFO_EXTENSION))); ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Tabellen -->
                    <?php if ($tabellen): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #fef3c7; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <strong style="color: #d97706;">Tabellen:</strong>
                                <span style="color: #64748b; font-size: 12px; margin-left: 8px;">
                                    <?php echo esc_html($format_size($tabellen)); ?> | <?php echo esc_html($get_upload_date($tabellen)); ?>
                                </span>
                            </div>
                            <a href="<?php echo esc_url($tabellen['url']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                <?php echo esc_html($tabellen['filename']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Supplementary Material -->
                    <?php if ($supplementary): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #e0e7ff; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <strong style="color: #6366f1;">Zusatzmaterial:</strong>
                                <span style="color: #64748b; font-size: 12px; margin-left: 8px;">
                                    <?php echo esc_html($format_size($supplementary)); ?> | <?php echo esc_html($get_upload_date($supplementary)); ?>
                                </span>
                            </div>
                            <a href="<?php echo esc_url($supplementary['url']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                <?php echo esc_html($supplementary['filename']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$manuskript && !$revision && !$abbildungen && !$tabellen && !$supplementary): ?>
                    <p style="color: #64748b; margin: 0;">Keine Dateien vorhanden.</p>
                    <?php else: ?>
                    <!-- Download All as ZIP -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="btn download-all-files-btn" style="background: #0891b2; color: #fff;" data-article-id="<?php echo esc_attr($view_id); ?>">
                            📦 Alle Dateien als ZIP herunterladen
                        </button>
                    </div>
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

                <!-- Status-Workflow & Export Aktionen -->
                <hr style="margin: 25px 0;">

                <h4>Status & Workflow</h4>

                <!-- Status-Änderung Panel -->
                <div class="redaktion-status-panel" style="background: #f0f9ff; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd; margin-bottom: 20px;">
                    <h5 style="margin: 0 0 15px 0; color: #0369a1;">Status ändern</h5>

                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-start;">
                        <!-- Status Dropdown -->
                        <div style="flex: 1; min-width: 250px;">
                            <label style="display: block; font-size: 12px; color: #64748b; margin-bottom: 5px;">Neuer Status:</label>
                            <select id="status-select" class="status-dropdown" style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px;">
                                <option value="">-- Status wählen --</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_SUBMITTED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_SUBMITTED); ?>>Eingereicht</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK); ?>>Formale Prüfung</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW); ?>>Im Review</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED); ?>>Revision erforderlich</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED); ?>>Revision eingereicht</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_ACCEPTED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_ACCEPTED); ?>>Angenommen</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_EXPORTED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_EXPORTED); ?>>Exportiert</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_LEKTORAT); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_LEKTORAT); ?>>Lektorat</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_GESETZT); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_GESETZT); ?>>Gesetzt</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_REJECTED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_REJECTED); ?>>Abgelehnt</option>
                                <option value="<?php echo esc_attr(DGPTM_Artikel_Einreichung::STATUS_PUBLISHED); ?>" <?php selected($status, DGPTM_Artikel_Einreichung::STATUS_PUBLISHED); ?>>Veröffentlicht</option>
                            </select>
                        </div>

                        <!-- Change Button -->
                        <div style="padding-top: 22px;">
                            <button type="button" class="btn change-status-dropdown-btn" style="background: #0369a1; color: #fff; padding: 10px 20px;"
                                    data-article-id="<?php echo esc_attr($view_id); ?>">
                                Status ändern
                            </button>
                        </div>
                    </div>

                    <!-- Ausgabe zuweisen -->
                    <div style="margin-top: 15px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 12px; color: #64748b; margin-bottom: 5px;">Ausgabe zuweisen:</label>
                            <input type="text" id="ausgabe-input" value="<?php echo esc_attr(get_field('ausgabe', $view_id) ?: ''); ?>"
                                   placeholder="z.B. 2025-1, 2025-2"
                                   style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px;">
                        </div>
                        <div>
                            <button type="button" class="btn save-ausgabe-btn" style="background: #6366f1; color: #fff; padding: 8px 16px;"
                                    data-article-id="<?php echo esc_attr($view_id); ?>">
                                Ausgabe speichern
                            </button>
                        </div>
                    </div>

                    <!-- Workflow Info -->
                    <div style="margin-top: 15px; padding: 12px; background: #fff; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; margin-bottom: 8px;">Typischer Workflow:</div>
                        <div style="font-size: 12px; color: #475569; line-height: 1.8;">
                            Eingereicht → Form. Prüfung → Im Review → Angenommen → Exportiert → Lektorat → Gesetzt → Veröffentlicht
                        </div>
                    </div>
                </div>

                <!-- Export Buttons (für angenommene+ Artikel) -->
                <?php if (in_array($status, [
                    DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                    DGPTM_Artikel_Einreichung::STATUS_EXPORTED,
                    DGPTM_Artikel_Einreichung::STATUS_LEKTORAT,
                    DGPTM_Artikel_Einreichung::STATUS_GESETZT,
                    DGPTM_Artikel_Einreichung::STATUS_PUBLISHED
                ])): ?>
                <div style="background: #ecfdf5; padding: 15px; border-radius: 8px; border: 1px solid #a7f3d0; margin-bottom: 20px;">
                    <h5 style="margin: 0 0 12px 0; color: #047857;">Export-Funktionen</h5>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <!-- XML Export -->
                        <button type="button" class="btn btn-secondary export-xml-btn" data-article-id="<?php echo esc_attr($view_id); ?>">
                            XML exportieren (JATS)
                        </button>

                        <!-- PDF Export -->
                        <a href="<?php echo esc_url(add_query_arg(['dgptm_artikel_pdf' => 1, 'artikel_id' => $view_id], home_url())); ?>" target="_blank" class="btn btn-secondary">
                            PDF herunterladen
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Publish Button (nur für Gesetzt-Status) -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_GESETZT): ?>
                <div style="background: #faf5ff; padding: 15px; border-radius: 8px; border: 1px solid #d8b4fe; margin-bottom: 20px;">
                    <h5 style="margin: 0 0 12px 0; color: #7c3aed;">Online-Veröffentlichung</h5>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="btn publish-artikel-btn" style="background: #7c3aed; color: #fff;"
                                data-article-id="<?php echo esc_attr($view_id); ?>">
                            Jetzt online veröffentlichen
                        </button>
                        <span style="color: #6b7280; font-size: 13px;">→ Erstellt Beitrag in "Publikationen" und setzt Status auf "Veröffentlicht"</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Published Info -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED): ?>
                <?php
                $publikation_id = get_field('publikation_id', $view_id);
                $published_at = get_field('published_at', $view_id);
                ?>
                <div style="background: #f5f3ff; padding: 15px; border-radius: 8px; border: 1px solid #c4b5fd;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <span class="status-badge status-purple" style="font-size: 14px;">✓ Veröffentlicht</span>
                        <?php if ($published_at): ?>
                            <span style="color: #64748b; font-size: 13px;">am <?php echo esc_html(date_i18n('d.m.Y', strtotime($published_at))); ?></span>
                        <?php endif; ?>
                        <?php if ($publikation_id): ?>
                            <a href="<?php echo esc_url(get_permalink($publikation_id)); ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                Publikation ansehen →
                            </a>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $publikation_id . '&action=edit')); ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                Im Backend bearbeiten
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Danger Zone (nur für Chefredakteure/Admins) -->
                <?php if ($plugin->is_editor_in_chief() || current_user_can('manage_options')): ?>
                <hr style="margin: 30px 0; border-color: #fecaca;">
                <div style="background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca;">
                    <h5 style="margin: 0 0 10px 0; color: #b91c1c;">⚠️ Gefahrenzone</h5>
                    <p style="color: #7f1d1d; font-size: 13px; margin-bottom: 15px;">
                        Das Löschen eines Artikels entfernt alle zugehörigen Dateien und kann nicht rückgängig gemacht werden.
                    </p>
                    <button type="button" class="btn delete-artikel-btn" style="background: #dc2626; color: #fff; border: none;"
                            data-article-id="<?php echo esc_attr($view_id); ?>"
                            data-article-title="<?php echo esc_attr($view_article->post_title); ?>"
                            data-submission-id="<?php echo esc_attr(get_field('submission_id', $view_id)); ?>">
                        🗑️ Artikel endgültig löschen
                    </button>
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

        <!-- Ausgabe Filter -->
        <?php
        // Get all unique Ausgaben
        $all_ausgaben = [];
        foreach ($articles as $art) {
            $ausgabe = get_field('ausgabe', $art->ID);
            if ($ausgabe && !in_array($ausgabe, $all_ausgaben)) {
                $all_ausgaben[] = $ausgabe;
            }
        }
        rsort($all_ausgaben); // Newest first
        $filter_ausgabe = isset($_GET['ausgabe']) ? sanitize_text_field($_GET['ausgabe']) : '';
        ?>

        <?php if (!empty($all_ausgaben)): ?>
        <div style="margin-bottom: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
            <label style="font-weight: 600; color: #0369a1; margin-right: 10px;">Ausgabe:</label>
            <select id="ausgabe-filter" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e1;">
                <option value="">Alle Ausgaben</option>
                <?php foreach ($all_ausgaben as $ausgabe): ?>
                    <option value="<?php echo esc_attr($ausgabe); ?>" <?php selected($filter_ausgabe, $ausgabe); ?>>
                        <?php echo esc_html($ausgabe); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Stats als klickbare Filter -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 10px; margin-bottom: 25px;">
            <!-- Gesamt -->
            <a href="<?php echo esc_url(remove_query_arg(['status', 'ausgabe'])); ?>" class="stat-filter-card <?php echo !$filter_status ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: #f8fafc; border-radius: 8px; border: 2px solid <?php echo !$filter_status ? '#1a365d' : '#e2e8f0'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: #1a365d;"><?php echo $stats['total']; ?></div>
                <div style="color: #718096; font-size: 11px;">Gesamt</div>
            </a>
            <!-- Eingereicht -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_SUBMITTED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? '#3182ce' : '#eff6ff'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? '#3182ce' : '#bfdbfe'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? '#fff' : '#3182ce'; ?>;"><?php echo $stats['submitted']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_SUBMITTED ? '#e0e7ff' : '#718096'; ?>; font-size: 11px;">Eingereicht</div>
            </a>
            <!-- Formale Prüfung -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK ? '#0891b2' : '#ecfeff'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK ? '#0891b2' : '#a5f3fc'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK ? '#fff' : '#0891b2'; ?>;"><?php echo $stats['formal_check']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_FORMAL_CHECK ? '#e0f2fe' : '#718096'; ?>; font-size: 11px;">Form. Prüf.</div>
            </a>
            <!-- Im Review -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? '#ca8a04' : '#fefce8'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? '#ca8a04' : '#fde047'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? '#fff' : '#ca8a04'; ?>;"><?php echo $stats['in_review']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW ? '#fef9c3' : '#718096'; ?>; font-size: 11px;">Im Review</div>
            </a>
            <!-- Revision erforderlich -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED ? '#d97706' : '#fef3c7'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED ? '#d97706' : '#fcd34d'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED ? '#fff' : '#d97706'; ?>;"><?php echo $stats['revision_required']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED ? '#fef3c7' : '#718096'; ?>; font-size: 11px;">Rev. erf.</div>
            </a>
            <!-- Revision eingereicht -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? '#2563eb' : '#dbeafe'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? '#2563eb' : '#93c5fd'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? '#fff' : '#2563eb'; ?>;"><?php echo $stats['revision_submitted']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED ? '#dbeafe' : '#718096'; ?>; font-size: 11px;">Rev. eing.</div>
            </a>
            <!-- Angenommen -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_ACCEPTED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? '#16a34a' : '#dcfce7'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? '#16a34a' : '#86efac'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? '#fff' : '#16a34a'; ?>;"><?php echo $stats['accepted']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED ? '#dcfce7' : '#718096'; ?>; font-size: 11px;">Angenommen</div>
            </a>
            <!-- Exportiert -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_EXPORTED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? '#0d9488' : '#ccfbf1'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? '#0d9488' : '#5eead4'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? '#fff' : '#0d9488'; ?>;"><?php echo $stats['exported']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_EXPORTED ? '#ccfbf1' : '#718096'; ?>; font-size: 11px;">Exportiert</div>
            </a>
            <!-- Lektorat -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_LEKTORAT)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_LEKTORAT ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_LEKTORAT ? '#4f46e5' : '#e0e7ff'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_LEKTORAT ? '#4f46e5' : '#a5b4fc'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_LEKTORAT ? '#fff' : '#4f46e5'; ?>;"><?php echo $stats['lektorat']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_LEKTORAT ? '#e0e7ff' : '#718096'; ?>; font-size: 11px;">Lektorat</div>
            </a>
            <!-- Gesetzt -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_GESETZT)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_GESETZT ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_GESETZT ? '#db2777' : '#fce7f3'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_GESETZT ? '#db2777' : '#f9a8d4'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_GESETZT ? '#fff' : '#db2777'; ?>;"><?php echo $stats['gesetzt']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_GESETZT ? '#fce7f3' : '#718096'; ?>; font-size: 11px;">Gesetzt</div>
            </a>
            <!-- Publiziert -->
            <a href="<?php echo esc_url(add_query_arg('status', DGPTM_Artikel_Einreichung::STATUS_PUBLISHED)); ?>" class="stat-filter-card <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? 'active' : ''; ?>" style="text-align: center; padding: 12px; background: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? '#7c3aed' : '#f5f3ff'; ?>; border-radius: 8px; border: 2px solid <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? '#7c3aed' : '#c4b5fd'; ?>; text-decoration: none; display: block; transition: all 0.2s;">
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? '#fff' : '#7c3aed'; ?>;"><?php echo $stats['published']; ?></div>
                <div style="color: <?php echo $filter_status === DGPTM_Artikel_Einreichung::STATUS_PUBLISHED ? '#f5f3ff' : '#718096'; ?>; font-size: 11px;">Publiziert</div>
            </a>
        </div>

        <!-- Filter-Hinweis wenn aktiv -->
        <?php if ($filter_status): ?>
        <div style="margin-bottom: 15px; padding: 10px 15px; background: #fef3c7; border-radius: 6px; border: 1px solid #fcd34d; display: flex; justify-content: space-between; align-items: center;">
            <span style="color: #92400e; font-size: 13px;">
                Filter aktiv: <strong><?php echo esc_html($plugin->get_status_label($filter_status)); ?></strong>
            </span>
            <a href="<?php echo esc_url(remove_query_arg('status')); ?>" style="color: #92400e; font-size: 12px; text-decoration: underline;">Filter zurücksetzen</a>
        </div>
        <?php endif; ?>

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
                        <th>Ausgabe</th>
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
                        $ausgabe = get_field('ausgabe', $article->ID);
                    ?>
                    <tr data-ausgabe="<?php echo esc_attr($ausgabe); ?>">
                        <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                        <td>
                            <div class="article-title"><?php echo esc_html($article->post_title); ?></div>
                        </td>
                        <td><?php echo esc_html(get_field('hauptautorin', $article->ID)); ?></td>
                        <td style="font-size: 12px;"><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[$pub_art] ?? '-'); ?></td>
                        <td style="font-size: 12px; color: #6366f1; font-weight: 500;"><?php echo esc_html($ausgabe ?: '-'); ?></td>
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

    // Ausgabe Filter
    $('#ausgabe-filter').on('change', function() {
        var selectedAusgabe = $(this).val();
        var $rows = $('.dgptm-artikel-table tbody tr');

        if (!selectedAusgabe) {
            // Show all rows
            $rows.show();
        } else {
            // Filter rows by Ausgabe
            $rows.each(function() {
                var rowAusgabe = $(this).data('ausgabe') || '';
                if (rowAusgabe === selectedAusgabe) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Update visible count
        var visibleCount = $rows.filter(':visible').length;
        var totalCount = $rows.length;
        var $filterInfo = $('#filter-info');
        if (selectedAusgabe) {
            if (!$filterInfo.length) {
                $('#ausgabe-filter').after('<span id="filter-info" style="margin-left: 15px; color: #64748b; font-size: 13px;"></span>');
                $filterInfo = $('#filter-info');
            }
            $filterInfo.text('Zeige ' + visibleCount + ' von ' + totalCount + ' Artikeln');
        } else {
            $filterInfo.remove();
        }
    });

    // Export XML (JATS format)
    $(document).on('click', '.export-xml-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var originalText = $btn.text();

        console.log('JATS Export gestartet für Artikel:', articleId);

        if (!articleId) {
            alert('Fehler: Keine Artikel-ID gefunden.');
            return;
        }

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
                console.log('JATS Export Response:', response);
                $btn.prop('disabled', false).text(originalText);

                if (response.success && response.data && response.data.xml) {
                    // Create blob and download
                    var blob = new Blob([response.data.xml], { type: 'application/xml; charset=utf-8' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
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

    // Change status via dropdown
    $(document).on('click', '.change-status-dropdown-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var $select = $('#status-select');
        var newStatus = $select.val();
        var originalText = $btn.text();

        if (!newStatus) {
            alert('Bitte wählen Sie einen Status aus.');
            return;
        }

        // Get the label for confirmation
        var statusLabel = $select.find('option:selected').text();

        if (!confirm('Status wirklich ändern auf "' + statusLabel + '"?')) {
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

    // Change status (legacy button handler - kept for compatibility)
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

    // Save Ausgabe
    $(document).on('click', '.save-ausgabe-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var ausgabe = $('#ausgabe-input').val().trim();
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Wird gespeichert...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_save_ausgabe',
                nonce: config.nonce,
                article_id: articleId,
                ausgabe: ausgabe
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    // Show success feedback
                    $btn.text('Gespeichert!').css('background', '#10b981');
                    setTimeout(function() {
                        $btn.text(originalText).css('background', '#6366f1');
                    }, 2000);
                } else {
                    alert(response.data.message || 'Fehler beim Speichern.');
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

    // Download all files as ZIP
    $(document).on('click', '.download-all-files-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('ZIP wird erstellt...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_download_all_files',
                nonce: config.nonce,
                article_id: articleId
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);

                if (response.success && response.data.download_url) {
                    // Start download
                    var a = document.createElement('a');
                    a.href = response.data.download_url;
                    a.download = response.data.filename;
                    a.style.display = 'none';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    console.log('ZIP Download gestartet:', response.data.filename, response.data.file_count + ' Dateien');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Fehler beim Erstellen der ZIP-Datei.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('Verbindungsfehler.');
            }
        });
    });

    // Delete article (with confirmation)
    $(document).on('click', '.delete-artikel-btn', function() {
        var $btn = $(this);
        var articleId = $btn.data('article-id');
        var articleTitle = $btn.data('article-title');
        var submissionId = $btn.data('submission-id');

        // First confirmation
        if (!confirm('ACHTUNG: Sie sind dabei, den Artikel "' + articleTitle + '" (' + submissionId + ') unwiderruflich zu löschen.\n\nAlle zugehörigen Dateien werden ebenfalls gelöscht.\n\nFortfahren?')) {
            return;
        }

        // Second confirmation with text input
        var confirmText = prompt('Zur Bestätigung geben Sie bitte "DELETE" ein:');
        if (confirmText !== 'DELETE') {
            alert('Löschvorgang abgebrochen. Die Bestätigung war nicht korrekt.');
            return;
        }

        $btn.prop('disabled', true).text('Wird gelöscht...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_delete_artikel',
                nonce: config.nonce,
                article_id: articleId,
                confirm: 'DELETE'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Redirect to overview
                    window.location.href = window.location.pathname;
                } else {
                    $btn.prop('disabled', false).text('🗑️ Artikel endgültig löschen');
                    alert(response.data && response.data.message ? response.data.message : 'Fehler beim Löschen.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('🗑️ Artikel endgültig löschen');
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
.status-indigo {
    background-color: #4f46e5 !important;
    color: #fff !important;
}
.status-pink {
    background-color: #db2777 !important;
    color: #fff !important;
}
.status-cyan {
    background-color: #0891b2 !important;
    color: #fff !important;
}
.status-lightblue {
    background-color: #2563eb !important;
    color: #fff !important;
}
.status-dropdown {
    background-color: #fff;
    cursor: pointer;
}
.status-dropdown:focus {
    border-color: #0369a1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(3, 105, 161, 0.1);
}
/* Stat Filter Cards */
.stat-filter-card {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-filter-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.stat-filter-card.active {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
</style>
