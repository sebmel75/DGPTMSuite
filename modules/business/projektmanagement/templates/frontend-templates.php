<?php
/**
 * Template: Project Templates Management
 * Variables: $user_id, $templates
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="pm-container pm-templates">

    <div class="pm-header">
        <h2>Projekt-Vorlagen</h2>
        <button type="button" class="pm-btn pm-btn-primary" id="pm-create-template">
            <span class="dashicons dashicons-plus-alt"></span> Neue Vorlage
        </button>
    </div>

    <?php if (empty($templates)): ?>
    <div class="pm-empty-state">
        <span class="dashicons dashicons-portfolio"></span>
        <p>Keine Vorlagen vorhanden.</p>
        <p class="pm-hint">Vorlagen ermoeglichen es, Projekte mit vordefinierten Aufgaben schnell zu erstellen.</p>
        <button type="button" class="pm-btn pm-btn-primary" id="pm-create-template-empty">
            Erste Vorlage erstellen
        </button>
    </div>
    <?php else: ?>

    <div class="pm-templates-grid">
        <?php foreach ($templates as $template):
            $template_data = $this->template_manager->get_template_data($template->ID);
            $task_count = $template_data ? $template_data['task_count'] : 0;
        ?>
        <div class="pm-template-card" data-id="<?php echo esc_attr($template->ID); ?>">
            <div class="pm-template-header">
                <h3 class="pm-template-title"><?php echo esc_html($template->post_title); ?></h3>
                <div class="pm-template-actions">
                    <button type="button" class="pm-btn-icon pm-edit-template" data-id="<?php echo esc_attr($template->ID); ?>" title="Bearbeiten">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="pm-btn-icon pm-delete-template" data-id="<?php echo esc_attr($template->ID); ?>" title="Loeschen">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>

            <?php if ($template->post_content): ?>
            <div class="pm-template-description">
                <?php echo wp_trim_words($template->post_content, 15, '...'); ?>
            </div>
            <?php endif; ?>

            <div class="pm-template-meta">
                <span class="pm-meta-item">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php echo $task_count; ?> Aufgabe(n)
                </span>
            </div>

            <?php if ($template_data && !empty($template_data['tasks'])): ?>
            <div class="pm-template-tasks-preview">
                <strong>Aufgaben:</strong>
                <ul>
                    <?php
                    $preview_tasks = array_slice($template_data['tasks'], 0, 3);
                    foreach ($preview_tasks as $task):
                    ?>
                    <li><?php echo esc_html($task['title']); ?></li>
                    <?php endforeach; ?>
                    <?php if ($task_count > 3): ?>
                    <li class="pm-more">... und <?php echo $task_count - 3; ?> weitere</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="pm-template-card-actions">
                <button type="button" class="pm-btn pm-btn-primary pm-btn-small pm-use-template" data-id="<?php echo esc_attr($template->ID); ?>">
                    Projekt erstellen
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<!-- Template Editor Modal -->
<div class="pm-modal" id="pm-template-editor-modal" style="display: none;">
    <div class="pm-modal-content pm-modal-large">
        <div class="pm-modal-header">
            <h3 id="pm-template-editor-title">Neue Vorlage</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <form id="pm-template-form">
                <input type="hidden" id="pm-template-id" name="template_id" value="">

                <div class="pm-form-group">
                    <label for="pm-template-title">Vorlagen-Titel *</label>
                    <input type="text" id="pm-template-title" name="title" required>
                </div>

                <div class="pm-form-group">
                    <label for="pm-template-description">Beschreibung</label>
                    <textarea id="pm-template-description" name="description" rows="2"></textarea>
                </div>

                <div class="pm-template-tasks-section">
                    <div class="pm-template-tasks-header">
                        <h4>Aufgaben-Vorlage</h4>
                        <button type="button" class="pm-btn pm-btn-secondary pm-btn-small" id="pm-add-template-task">
                            <span class="dashicons dashicons-plus"></span> Aufgabe hinzufuegen
                        </button>
                    </div>

                    <div id="pm-template-tasks-list" class="pm-template-tasks-list">
                        <!-- Task templates will be added here -->
                    </div>
                </div>

                <div class="pm-form-actions">
                    <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                    <button type="submit" class="pm-btn pm-btn-primary">Vorlage speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Use Template Modal -->
<div class="pm-modal" id="pm-use-template-modal" style="display: none;">
    <div class="pm-modal-content">
        <div class="pm-modal-header">
            <h3>Projekt aus Vorlage erstellen</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <form id="pm-use-template-form">
                <input type="hidden" id="pm-use-template-id" name="template_id" value="">

                <div class="pm-form-group">
                    <label for="pm-new-project-title">Projekttitel *</label>
                    <input type="text" id="pm-new-project-title" name="project_title" required>
                </div>

                <div class="pm-form-group">
                    <label for="pm-new-project-due">Projekt-Faelligkeit</label>
                    <input type="date" id="pm-new-project-due" name="project_due_date">
                    <small class="pm-hint">Die Aufgaben-Faelligkeiten werden relativ zu diesem Datum berechnet.</small>
                </div>

                <div class="pm-form-actions">
                    <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                    <button type="submit" class="pm-btn pm-btn-primary">Projekt erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template Task Row Template -->
<template id="pm-template-task-row">
    <div class="pm-template-task-row" data-index="">
        <div class="pm-template-task-drag">
            <span class="dashicons dashicons-menu"></span>
        </div>
        <div class="pm-template-task-fields">
            <div class="pm-form-row">
                <div class="pm-form-group pm-form-group-wide">
                    <input type="text" class="pm-template-task-title" placeholder="Aufgaben-Titel *" required>
                </div>
            </div>
            <div class="pm-form-row">
                <div class="pm-form-group">
                    <select class="pm-template-task-priority">
                        <option value="low">Niedrig</option>
                        <option value="medium" selected>Mittel</option>
                        <option value="high">Hoch</option>
                    </select>
                </div>
                <div class="pm-form-group">
                    <input type="number" class="pm-template-task-days" placeholder="Tage vor Faelligkeit" value="0" min="0">
                    <small>Tage vor Projekt-Ende</small>
                </div>
            </div>
            <div class="pm-form-group">
                <textarea class="pm-template-task-description" placeholder="Beschreibung (optional)" rows="2"></textarea>
            </div>
        </div>
        <div class="pm-template-task-actions">
            <button type="button" class="pm-btn-icon pm-remove-template-task" title="Entfernen">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</template>
