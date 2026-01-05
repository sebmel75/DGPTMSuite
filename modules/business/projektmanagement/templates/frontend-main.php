<?php
/**
 * Template: Projektmanagement Main View
 * Variables: $user_id, $is_manager
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get projects based on user role
if ($is_manager) {
    $projects = $this->get_all_projects('active');
} else {
    $projects = $this->get_user_projects($user_id);
}

$all_users = $this->permissions->get_assignable_users();
$templates = $this->template_manager->get_all_templates();
?>

<div class="pm-container">

    <div class="pm-header">
        <h2>Projektmanagement</h2>
        <?php if ($is_manager): ?>
        <div class="pm-header-actions">
            <button type="button" class="pm-btn pm-btn-secondary" id="pm-create-from-template">
                <span class="dashicons dashicons-portfolio"></span> Aus Vorlage
            </button>
            <button type="button" class="pm-btn pm-btn-primary" id="pm-create-project">
                <span class="dashicons dashicons-plus-alt"></span> Neues Projekt
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($projects)): ?>
    <div class="pm-empty-state">
        <span class="dashicons dashicons-clipboard"></span>
        <p>Keine Projekte vorhanden.</p>
        <?php if ($is_manager): ?>
        <button type="button" class="pm-btn pm-btn-primary" id="pm-create-project-empty">
            Erstes Projekt erstellen
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <div class="pm-projects-grid">
        <?php foreach ($projects as $project):
            $tasks = $this->get_project_tasks($project->ID);
            $completed_count = 0;
            $total_count = count($tasks);

            foreach ($tasks as $task) {
                if (get_post_meta($task->ID, '_pm_status', true) === 'completed') {
                    $completed_count++;
                }
            }

            $progress = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;
            $project_status = get_post_meta($project->ID, '_pm_status', true) ?: 'active';
            $project_due = get_post_meta($project->ID, '_pm_due_date', true);
        ?>
        <div class="pm-project-card" data-id="<?php echo esc_attr($project->ID); ?>">
            <div class="pm-project-header">
                <h3 class="pm-project-title"><?php echo esc_html($project->post_title); ?></h3>
                <?php if ($is_manager): ?>
                <div class="pm-project-actions">
                    <button type="button" class="pm-btn-icon pm-add-task" data-project="<?php echo esc_attr($project->ID); ?>" title="Aufgabe hinzufuegen">
                        <span class="dashicons dashicons-plus"></span>
                    </button>
                    <button type="button" class="pm-btn-icon pm-edit-project" data-id="<?php echo esc_attr($project->ID); ?>" title="Bearbeiten">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="pm-btn-icon pm-delete-project" data-id="<?php echo esc_attr($project->ID); ?>" title="Loeschen">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($project_due): ?>
            <div class="pm-project-due">
                <span class="dashicons dashicons-calendar-alt"></span>
                Faellig: <?php echo date_i18n('d.m.Y', strtotime($project_due)); ?>
            </div>
            <?php endif; ?>

            <div class="pm-project-progress">
                <div class="pm-progress-bar">
                    <div class="pm-progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
                <span class="pm-progress-text"><?php echo $completed_count; ?>/<?php echo $total_count; ?> Aufgaben</span>
            </div>

            <div class="pm-task-list">
                <?php if (empty($tasks)): ?>
                <div class="pm-task-empty">Keine Aufgaben</div>
                <?php else: ?>
                    <?php foreach ($tasks as $task):
                        $status = get_post_meta($task->ID, '_pm_status', true) ?: 'pending';
                        $priority = get_post_meta($task->ID, '_pm_priority', true) ?: 'medium';
                        $assignee_id = get_post_meta($task->ID, '_pm_assignee', true);
                        $assignee = get_userdata($assignee_id);
                        $task_due = get_post_meta($task->ID, '_pm_due_date', true);

                        // Check if user can interact with this task
                        $can_complete = $is_manager || (int)$assignee_id === $user_id;
                    ?>
                    <div class="pm-task-row pm-status-<?php echo esc_attr($status); ?> pm-priority-<?php echo esc_attr($priority); ?>"
                         data-id="<?php echo esc_attr($task->ID); ?>">
                        <div class="pm-task-checkbox">
                            <input type="checkbox"
                                   class="pm-task-complete-checkbox"
                                   <?php checked($status, 'completed'); ?>
                                   <?php disabled(!$can_complete); ?>
                                   data-task="<?php echo esc_attr($task->ID); ?>">
                        </div>
                        <div class="pm-task-info" data-task="<?php echo esc_attr($task->ID); ?>">
                            <span class="pm-task-title"><?php echo esc_html($task->post_title); ?></span>
                            <div class="pm-task-meta">
                                <?php if ($assignee): ?>
                                <span class="pm-task-assignee">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php echo esc_html($assignee->display_name); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($task_due): ?>
                                <span class="pm-task-due <?php echo strtotime($task_due) < time() && $status !== 'completed' ? 'overdue' : ''; ?>">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php echo date_i18n('d.m.Y', strtotime($task_due)); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pm-task-priority-badge pm-priority-<?php echo esc_attr($priority); ?>">
                            <?php
                            $priority_labels = ['high' => 'Hoch', 'medium' => 'Mittel', 'low' => 'Niedrig'];
                            echo esc_html($priority_labels[$priority] ?? 'Mittel');
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<!-- Project Modal -->
<div class="pm-modal" id="pm-project-modal" style="display: none;">
    <div class="pm-modal-content">
        <div class="pm-modal-header">
            <h3 id="pm-project-modal-title">Neues Projekt</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <form id="pm-project-form">
                <input type="hidden" id="pm-project-id" name="project_id" value="">

                <div class="pm-form-group">
                    <label for="pm-project-title">Titel *</label>
                    <input type="text" id="pm-project-title" name="title" required>
                </div>

                <div class="pm-form-group">
                    <label for="pm-project-description">Beschreibung</label>
                    <textarea id="pm-project-description" name="description" rows="4"></textarea>
                </div>

                <div class="pm-form-group">
                    <label for="pm-project-due-date">Faelligkeitsdatum</label>
                    <input type="date" id="pm-project-due-date" name="due_date">
                </div>

                <div class="pm-form-actions">
                    <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                    <button type="submit" class="pm-btn pm-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="pm-modal" id="pm-task-modal" style="display: none;">
    <div class="pm-modal-content pm-modal-large">
        <div class="pm-modal-header">
            <h3 id="pm-task-modal-title">Neue Aufgabe</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <form id="pm-task-form">
                <input type="hidden" id="pm-task-id" name="task_id" value="">
                <input type="hidden" id="pm-task-project-id" name="project_id" value="">

                <div class="pm-form-row">
                    <div class="pm-form-group pm-form-group-wide">
                        <label for="pm-task-title">Titel *</label>
                        <input type="text" id="pm-task-title" name="title" required>
                    </div>
                </div>

                <div class="pm-form-group">
                    <label for="pm-task-description">Beschreibung</label>
                    <textarea id="pm-task-description" name="description" rows="3"></textarea>
                </div>

                <div class="pm-form-row">
                    <div class="pm-form-group">
                        <label for="pm-task-assignee">Zuweisen an</label>
                        <select id="pm-task-assignee" name="assignee">
                            <option value="">-- Nicht zugewiesen --</option>
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo esc_attr($u['id']); ?>">
                                <?php echo esc_html($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pm-form-group">
                        <label for="pm-task-priority">Prioritaet</label>
                        <select id="pm-task-priority" name="priority">
                            <option value="low">Niedrig</option>
                            <option value="medium" selected>Mittel</option>
                            <option value="high">Hoch</option>
                        </select>
                    </div>

                    <div class="pm-form-group">
                        <label for="pm-task-due-date">Faellig am</label>
                        <input type="date" id="pm-task-due-date" name="due_date">
                    </div>
                </div>

                <div class="pm-form-actions">
                    <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                    <button type="submit" class="pm-btn pm-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
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

<!-- Template Selection Modal -->
<div class="pm-modal" id="pm-template-modal" style="display: none;">
    <div class="pm-modal-content">
        <div class="pm-modal-header">
            <h3>Projekt aus Vorlage erstellen</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <form id="pm-template-project-form">
                <div class="pm-form-group">
                    <label for="pm-template-select">Vorlage auswaehlen *</label>
                    <select id="pm-template-select" name="template_id" required>
                        <option value="">-- Vorlage waehlen --</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo esc_attr($template->ID); ?>">
                            <?php echo esc_html($template->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pm-form-group">
                    <label for="pm-template-project-title">Projekttitel *</label>
                    <input type="text" id="pm-template-project-title" name="project_title" required>
                </div>

                <div class="pm-form-group">
                    <label for="pm-template-project-due">Projekt-Faelligkeit</label>
                    <input type="date" id="pm-template-project-due" name="project_due_date">
                </div>

                <div class="pm-form-actions">
                    <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                    <button type="submit" class="pm-btn pm-btn-primary">Projekt erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>
