<?php
/**
 * Template: My Tasks View
 * Variables: $user_id, $tasks, $is_manager
 */
if (!defined('ABSPATH')) {
    exit;
}

// Group tasks by status
$pending_tasks = [];
$completed_tasks = [];

foreach ($tasks as $task) {
    $status = get_post_meta($task->ID, '_pm_status', true) ?: 'pending';
    if ($status === 'completed') {
        $completed_tasks[] = $task;
    } else {
        $pending_tasks[] = $task;
    }
}
?>

<div class="pm-container pm-my-tasks">

    <div class="pm-header">
        <h2>Meine Aufgaben</h2>
        <div class="pm-task-stats">
            <span class="pm-stat pm-stat-pending">
                <span class="pm-stat-number"><?php echo count($pending_tasks); ?></span>
                <span class="pm-stat-label">Offen</span>
            </span>
            <span class="pm-stat pm-stat-completed">
                <span class="pm-stat-number"><?php echo count($completed_tasks); ?></span>
                <span class="pm-stat-label">Erledigt</span>
            </span>
        </div>
    </div>

    <?php if (empty($tasks)): ?>
    <div class="pm-empty-state">
        <span class="dashicons dashicons-yes-alt"></span>
        <p>Keine Aufgaben vorhanden.</p>
    </div>
    <?php else: ?>

    <!-- Open Tasks -->
    <?php if (!empty($pending_tasks)): ?>
    <div class="pm-section">
        <h3 class="pm-section-title">
            <span class="dashicons dashicons-clock"></span>
            Offene Aufgaben (<?php echo count($pending_tasks); ?>)
        </h3>

        <div class="pm-task-cards">
            <?php foreach ($pending_tasks as $task):
                $priority = get_post_meta($task->ID, '_pm_priority', true) ?: 'medium';
                $due_date = get_post_meta($task->ID, '_pm_due_date', true);
                $project_id = get_post_meta($task->ID, '_pm_project_id', true);
                $project = get_post($project_id);
                $is_overdue = $due_date && strtotime($due_date) < strtotime('today');

                // Get comments count
                $comments_count = get_comments_number($task->ID);

                // Get attachments count
                $attachments = get_post_meta($task->ID, '_pm_attachments', true) ?: [];
                $attachments_count = count($attachments);
            ?>
            <div class="pm-task-card pm-priority-<?php echo esc_attr($priority); ?> <?php echo $is_overdue ? 'pm-overdue' : ''; ?>"
                 data-id="<?php echo esc_attr($task->ID); ?>">

                <div class="pm-task-card-header">
                    <div class="pm-task-card-checkbox">
                        <input type="checkbox" class="pm-task-complete-checkbox" data-task="<?php echo esc_attr($task->ID); ?>">
                    </div>
                    <h4 class="pm-task-card-title"><?php echo esc_html($task->post_title); ?></h4>
                    <span class="pm-priority-badge pm-priority-<?php echo esc_attr($priority); ?>">
                        <?php
                        $priority_labels = ['high' => 'Hoch', 'medium' => 'Mittel', 'low' => 'Niedrig'];
                        echo esc_html($priority_labels[$priority] ?? 'Mittel');
                        ?>
                    </span>
                </div>

                <?php if ($task->post_content): ?>
                <div class="pm-task-card-description">
                    <?php echo wp_trim_words($task->post_content, 20, '...'); ?>
                </div>
                <?php endif; ?>

                <div class="pm-task-card-meta">
                    <?php if ($project): ?>
                    <span class="pm-meta-item">
                        <span class="dashicons dashicons-portfolio"></span>
                        <?php echo esc_html($project->post_title); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($due_date): ?>
                    <span class="pm-meta-item <?php echo $is_overdue ? 'pm-overdue' : ''; ?>">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo date_i18n('d.m.Y', strtotime($due_date)); ?>
                        <?php if ($is_overdue): ?>
                        <strong>(ueberfaellig!)</strong>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($comments_count > 0): ?>
                    <span class="pm-meta-item">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <?php echo $comments_count; ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($attachments_count > 0): ?>
                    <span class="pm-meta-item">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php echo $attachments_count; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="pm-task-card-actions">
                    <button type="button" class="pm-btn pm-btn-small pm-btn-secondary pm-view-task" data-task="<?php echo esc_attr($task->ID); ?>">
                        Details
                    </button>
                    <button type="button" class="pm-btn pm-btn-small pm-btn-primary pm-complete-task-btn" data-task="<?php echo esc_attr($task->ID); ?>">
                        Erledigen
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Completed Tasks -->
    <?php if (!empty($completed_tasks)): ?>
    <div class="pm-section pm-section-completed">
        <h3 class="pm-section-title pm-collapsed" id="pm-completed-toggle">
            <span class="dashicons dashicons-yes"></span>
            Erledigte Aufgaben (<?php echo count($completed_tasks); ?>)
            <span class="dashicons dashicons-arrow-down-alt2 pm-toggle-icon"></span>
        </h3>

        <div class="pm-task-cards pm-completed-tasks" style="display: none;">
            <?php foreach ($completed_tasks as $task):
                $project_id = get_post_meta($task->ID, '_pm_project_id', true);
                $project = get_post($project_id);
                $completed_date = get_post_meta($task->ID, '_pm_completed_date', true);
            ?>
            <div class="pm-task-card pm-task-completed" data-id="<?php echo esc_attr($task->ID); ?>">
                <div class="pm-task-card-header">
                    <div class="pm-task-card-checkbox">
                        <input type="checkbox" checked disabled>
                    </div>
                    <h4 class="pm-task-card-title"><?php echo esc_html($task->post_title); ?></h4>
                </div>

                <div class="pm-task-card-meta">
                    <?php if ($project): ?>
                    <span class="pm-meta-item">
                        <span class="dashicons dashicons-portfolio"></span>
                        <?php echo esc_html($project->post_title); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($completed_date): ?>
                    <span class="pm-meta-item">
                        <span class="dashicons dashicons-yes"></span>
                        Erledigt am <?php echo date_i18n('d.m.Y', strtotime($completed_date)); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<!-- Task Detail Modal -->
<div class="pm-modal" id="pm-task-detail-modal" style="display: none;">
    <div class="pm-modal-content pm-modal-large">
        <div class="pm-modal-header">
            <h3 id="pm-task-detail-title">Aufgabe</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <div id="pm-task-detail-content">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
