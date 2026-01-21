<?php
/**
 * Template: User's EduGrants
 * Shortcode: [meine_edugrantes]
 */

if (!defined('ABSPATH')) {
    exit;
}

$show_form_link = ($atts['show_form_link'] ?? 'true') === 'true';
$form_page_url = get_option('dgptm_edugrant_form_page', '/veranstaltungen/educational-grant-der-dgptm/educational-grant-abrechnung/');
?>

<div class="edugrant-user-container">
    <h3 class="edugrant-section-title">Meine EduGrants</h3>

    <?php if (empty($grants)): ?>
        <div class="edugrant-no-grants">
            <span class="dashicons dashicons-info"></span>
            <p>Sie haben noch keine EduGrants beantragt.</p>
            <a href="<?php echo esc_url($form_page_url); ?>" class="button">
                Jetzt EduGrant beantragen
            </a>
        </div>
    <?php else: ?>
        <div class="edugrant-grants-list">
            <?php foreach ($grants as $grant):
                $grant_id = $grant['id'] ?? '';
                $grant_number = $grant['Nummer'] ?? $grant['Name'] ?? 'N/A';
                $status = $grant['Status'] ?? 'Unbekannt';
                $status_info = DGPTM_EduGrant_Manager::get_status_info($status);
                $event_name = $grant['Veranstaltung']['name'] ?? $grant['Veranstaltung'] ?? 'Unbekannte Veranstaltung';
                $applied_date = !empty($grant['Beantragt_am']) ? date_i18n('d.m.Y', strtotime($grant['Beantragt_am'])) : '';
                $approved_date = !empty($grant['Genehmigt_am']) ? date_i18n('d.m.Y', strtotime($grant['Genehmigt_am'])) : '';
                $max_funding = $grant['Maximale_Forderung'] ?? '';
                $total_amount = $grant['Summe'] ?? '';
                $documents_folder = $grant['Ordner_mit_Nachweisen'] ?? '';
                $rejection_text = $grant['Text_Ablehnung'] ?? '';
            ?>
                <div class="edugrant-grant-card" data-status="<?php echo esc_attr($status); ?>">
                    <div class="grant-header">
                        <span class="grant-number"><?php echo esc_html($grant_number); ?></span>
                        <span class="grant-status" style="background-color: <?php echo esc_attr($status_info['color']); ?>">
                            <span class="dashicons dashicons-<?php echo esc_attr($status_info['icon']); ?>"></span>
                            <?php echo esc_html($status_info['label']); ?>
                        </span>
                    </div>

                    <div class="grant-details">
                        <div class="grant-event">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html(is_array($event_name) ? ($event_name['name'] ?? 'Veranstaltung') : $event_name); ?>
                        </div>

                        <?php if ($applied_date): ?>
                            <div class="grant-date">
                                <span class="dashicons dashicons-clock"></span>
                                Beantragt am: <?php echo esc_html($applied_date); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($approved_date): ?>
                            <div class="grant-date approved">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Genehmigt am: <?php echo esc_html($approved_date); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($max_funding): ?>
                            <div class="grant-funding">
                                <span class="dashicons dashicons-awards"></span>
                                Max. Förderung: <?php echo esc_html(number_format((float)$max_funding, 0, ',', '.')); ?> &euro;
                            </div>
                        <?php endif; ?>

                        <?php if ($total_amount && $status === 'Überwiesen'): ?>
                            <div class="grant-amount">
                                <span class="dashicons dashicons-money-alt"></span>
                                Überwiesen: <strong><?php echo esc_html(number_format((float)$total_amount, 2, ',', '.')); ?> &euro;</strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($rejection_text): ?>
                            <div class="grant-rejection">
                                <span class="dashicons dashicons-warning"></span>
                                <em><?php echo esc_html($rejection_text); ?></em>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($status === 'Unterlagen angefordert' && $show_form_link): ?>
                        <div class="grant-actions">
                            <div class="grant-action-notice">
                                <span class="dashicons dashicons-upload"></span>
                                <strong>Aktion erforderlich:</strong> Bitte reichen Sie Ihre Unterlagen ein.
                            </div>
                            <a href="<?php echo esc_url(add_query_arg('eduid', $grant_id, $form_page_url)); ?>"
                               class="button edugrant-submit-docs-btn">
                                <span class="dashicons dashicons-media-document"></span>
                                Unterlagen einreichen
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'Nachberechnen' && $show_form_link): ?>
                        <div class="grant-actions">
                            <div class="grant-action-notice warning">
                                <span class="dashicons dashicons-warning"></span>
                                <strong>Nachberechnung erforderlich:</strong> Bitte korrigieren Sie Ihre Angaben.
                            </div>
                            <a href="<?php echo esc_url(add_query_arg('eduid', $grant_id, $form_page_url)); ?>"
                               class="button edugrant-recalc-btn">
                                <span class="dashicons dashicons-edit"></span>
                                Nachberechnung einreichen
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($documents_folder && ($status === 'Abrechnung eingereicht' || $status === 'Überwiesen')): ?>
                        <div class="grant-documents">
                            <a href="<?php echo esc_url($documents_folder); ?>" target="_blank" class="button button-small">
                                <span class="dashicons dashicons-open-folder"></span>
                                Dokumentenordner öffnen
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="edugrant-legend">
            <h4>Statusübersicht:</h4>
            <ul>
                <li><span class="status-dot" style="background: #f0ad4e;"></span> Beantragt - Ihr Antrag wird geprüft</li>
                <li><span class="status-dot" style="background: #5cb85c;"></span> Genehmigt - Antrag wurde genehmigt</li>
                <li><span class="status-dot" style="background: #5bc0de;"></span> Unterlagen angefordert - Bitte Nachweise hochladen</li>
                <li><span class="status-dot" style="background: #337ab7;"></span> Abrechnung eingereicht - In Bearbeitung</li>
                <li><span class="status-dot" style="background: #5cb85c;"></span> Überwiesen - Betrag wurde überwiesen</li>
            </ul>
        </div>
    <?php endif; ?>
</div>
