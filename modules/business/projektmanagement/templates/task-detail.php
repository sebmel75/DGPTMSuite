<?php
/**
 * Template: Token-based Task Detail View
 * Variables: $task (from $GLOBALS['pm_token_task']), $token (from $GLOBALS['pm_token'])
 */
if (!defined('ABSPATH')) {
    exit;
}

$priority = get_post_meta($task->ID, '_pm_priority', true) ?: 'medium';
$due_date = get_post_meta($task->ID, '_pm_due_date', true);
$status = get_post_meta($task->ID, '_pm_status', true) ?: 'pending';
$project_id = get_post_meta($task->ID, '_pm_project_id', true);
$project = get_post($project_id);
$is_completed = $status === 'completed';
$is_overdue = $due_date && strtotime($due_date) < strtotime('today') && !$is_completed;

// Get comments
$comments = get_comments([
    'post_id' => $task->ID,
    'status'  => 'approve',
    'order'   => 'ASC',
]);

// Get attachments
$attachment_ids = get_post_meta($task->ID, '_pm_attachments', true) ?: [];

// Priority labels
$priority_labels = ['high' => 'Hoch', 'medium' => 'Mittel', 'low' => 'Niedrig'];
?>

<div class="pm-container pm-token-view">

    <div class="pm-task-detail-card pm-priority-<?php echo esc_attr($priority); ?> <?php echo $is_completed ? 'pm-completed' : ''; ?>">

        <?php if ($is_completed): ?>
        <div class="pm-completed-banner">
            <span class="dashicons dashicons-yes-alt"></span>
            Diese Aufgabe wurde bereits abgeschlossen.
        </div>
        <?php endif; ?>

        <div class="pm-task-detail-header">
            <h1><?php echo esc_html($task->post_title); ?></h1>
            <span class="pm-priority-badge pm-priority-<?php echo esc_attr($priority); ?>">
                <?php echo esc_html($priority_labels[$priority] ?? 'Mittel'); ?>
            </span>
        </div>

        <div class="pm-task-detail-meta">
            <?php if ($project): ?>
            <div class="pm-meta-row">
                <span class="pm-meta-label">Projekt:</span>
                <span class="pm-meta-value"><?php echo esc_html($project->post_title); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($due_date): ?>
            <div class="pm-meta-row <?php echo $is_overdue ? 'pm-overdue' : ''; ?>">
                <span class="pm-meta-label">Faellig:</span>
                <span class="pm-meta-value">
                    <?php echo date_i18n('d.m.Y', strtotime($due_date)); ?>
                    <?php if ($is_overdue): ?>
                    <strong class="pm-overdue-text">(ueberfaellig!)</strong>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="pm-meta-row">
                <span class="pm-meta-label">Status:</span>
                <span class="pm-meta-value">
                    <?php if ($is_completed): ?>
                    <span class="pm-status-badge pm-status-completed">Erledigt</span>
                    <?php else: ?>
                    <span class="pm-status-badge pm-status-pending">Offen</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($task->post_content): ?>
        <div class="pm-task-detail-description">
            <h3>Beschreibung</h3>
            <div class="pm-description-content">
                <?php echo nl2br(esc_html($task->post_content)); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($attachment_ids)): ?>
        <div class="pm-task-detail-attachments">
            <h3>Anhaenge</h3>
            <ul class="pm-attachment-list">
                <?php foreach ($attachment_ids as $att_id):
                    $url = wp_get_attachment_url($att_id);
                    $filename = basename(get_attached_file($att_id));
                    if (!$url) continue;
                ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" class="pm-attachment-link">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php echo esc_html($filename); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Comments Section -->
        <div class="pm-task-detail-comments">
            <h3>Kommentare (<?php echo count($comments); ?>)</h3>

            <?php if (!empty($comments)): ?>
            <div class="pm-comments-list">
                <?php foreach ($comments as $comment): ?>
                <div class="pm-comment">
                    <div class="pm-comment-header">
                        <span class="pm-comment-author"><?php echo esc_html($comment->comment_author); ?></span>
                        <span class="pm-comment-date"><?php echo date_i18n('d.m.Y H:i', strtotime($comment->comment_date)); ?></span>
                    </div>
                    <div class="pm-comment-content">
                        <?php echo nl2br(esc_html($comment->comment_content)); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="pm-no-comments">Noch keine Kommentare.</p>
            <?php endif; ?>

            <?php if (!$is_completed): ?>
            <div class="pm-comment-form-wrapper">
                <h4>Kommentar hinzufuegen</h4>
                <form id="pm-token-comment-form" class="pm-comment-form" data-token="<?php echo esc_attr($token); ?>">
                    <textarea id="pm-token-comment" name="comment" rows="3" placeholder="Ihr Kommentar..." required></textarea>
                    <button type="submit" class="pm-btn pm-btn-secondary">
                        <span class="dashicons dashicons-edit"></span> Kommentar senden
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <?php if (!$is_completed): ?>
        <div class="pm-task-detail-actions">
            <button type="button" id="pm-token-complete" class="pm-btn pm-btn-primary pm-btn-large" data-token="<?php echo esc_attr($token); ?>">
                <span class="dashicons dashicons-yes"></span> Aufgabe als erledigt markieren
            </button>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
jQuery(document).ready(function($) {
    // Complete task via token
    $('#pm-token-complete').on('click', function() {
        var token = $(this).data('token');
        var $btn = $(this);

        if (!confirm('Aufgabe wirklich als erledigt markieren?')) {
            return;
        }

        $btn.prop('disabled', true).text('Wird gespeichert...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'pm_token_complete',
                token: token
            },
            success: function(response) {
                if (response.success) {
                    alert('Aufgabe erfolgreich abgeschlossen!');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aufgabe als erledigt markieren');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aufgabe als erledigt markieren');
            }
        });
    });

    // Comment via token
    $('#pm-token-comment-form').on('submit', function(e) {
        e.preventDefault();

        var token = $(this).data('token');
        var comment = $('#pm-token-comment').val().trim();
        var $btn = $(this).find('button[type="submit"]');

        if (!comment) {
            alert('Bitte Kommentar eingeben');
            return;
        }

        $btn.prop('disabled', true).text('Wird gesendet...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'pm_token_comment',
                token: token,
                comment: comment
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-edit"></span> Kommentar senden');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-edit"></span> Kommentar senden');
            }
        });
    });
});
</script>
