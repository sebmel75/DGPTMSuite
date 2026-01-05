/**
 * DGPTM Projektmanagement - Frontend JavaScript
 * Version: 1.0.1
 */

(function($) {
    'use strict';

    // Global state
    var PM = {
        currentProject: null,
        currentTask: null
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initModals();
        initProjects();
        initTasks();
        initTemplates();
        initMyTasks();
    });

    // ========================================
    // Modal Handling
    // ========================================

    function initModals() {
        // Close modal on X click
        $(document).on('click', '.pm-modal-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.pm-modal').fadeOut(200);
        });

        // Close modal on backdrop click
        $(document).on('click', '.pm-modal', function(e) {
            if ($(e.target).hasClass('pm-modal')) {
                $(this).fadeOut(200);
            }
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.pm-modal:visible').fadeOut(200);
            }
        });
    }

    function openModal(modalId) {
        $('#' + modalId).fadeIn(200);
    }

    function closeModal(modalId) {
        $('#' + modalId).fadeOut(200);
    }

    function showLoading() {
        $('#pm-loading').show();
    }

    function hideLoading() {
        $('#pm-loading').hide();
    }

    // ========================================
    // Project Management
    // ========================================

    function initProjects() {
        // Create project button
        $(document).on('click', '#pm-create-project, #pm-create-project-empty', function(e) {
            e.preventDefault();
            resetProjectForm();
            $('#pm-project-modal-title').text('Neues Projekt');
            openModal('pm-project-modal');
        });

        // Edit project
        $(document).on('click', '.pm-edit-project', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var projectId = $(this).data('id');
            loadProjectForEdit(projectId);
        });

        // Delete project
        $(document).on('click', '.pm-delete-project', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var projectId = $(this).data('id');
            var projectTitle = $(this).closest('.pm-project-card').find('.pm-project-title').text();

            if (confirm('Moechten Sie das Projekt "' + projectTitle + '" wirklich loeschen? Alle zugehoerigen Aufgaben werden ebenfalls geloescht.')) {
                deleteProject(projectId);
            }
        });

        // Project form submit
        $(document).on('submit', '#pm-project-form', function(e) {
            e.preventDefault();
            saveProject();
        });

        // Create from template button
        $(document).on('click', '#pm-create-from-template', function(e) {
            e.preventDefault();
            openModal('pm-template-modal');
        });

        // Template project form submit
        $(document).on('submit', '#pm-template-project-form', function(e) {
            e.preventDefault();
            createProjectFromTemplate();
        });
    }

    function resetProjectForm() {
        $('#pm-project-id').val('');
        $('#pm-project-title').val('');
        $('#pm-project-description').val('');
        $('#pm-project-due-date').val('');
    }

    function loadProjectForEdit(projectId) {
        // Get project data from the card
        var $card = $('.pm-project-card[data-id="' + projectId + '"]');
        var title = $card.find('.pm-project-title').text();

        // We need to fetch full project data via AJAX
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_project_tasks',
                nonce: pmData.nonce,
                project_id: projectId
            },
            success: function(response) {
                hideLoading();
                // Set basic data we have
                $('#pm-project-id').val(projectId);
                $('#pm-project-title').val(title);
                $('#pm-project-modal-title').text('Projekt bearbeiten');
                openModal('pm-project-modal');
            },
            error: function() {
                hideLoading();
                // Fallback: just open modal with title
                $('#pm-project-id').val(projectId);
                $('#pm-project-title').val(title);
                $('#pm-project-modal-title').text('Projekt bearbeiten');
                openModal('pm-project-modal');
            }
        });
    }

    function saveProject() {
        var projectId = $('#pm-project-id').val();
        var title = $('#pm-project-title').val().trim();
        var description = $('#pm-project-description').val().trim();
        var dueDate = $('#pm-project-due-date').val();

        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        var $btn = $('#pm-project-form button[type="submit"]');
        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: projectId ? 'pm_update_project' : 'pm_create_project',
                nonce: pmData.nonce,
                project_id: projectId,
                title: title,
                description: description,
                due_date: dueDate
            },
            success: function(response) {
                if (response.success) {
                    closeModal('pm-project-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                    $btn.prop('disabled', false).text('Speichern');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Speichern');
            }
        });
    }

    function deleteProject(projectId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_delete_project',
                nonce: pmData.nonce,
                project_id: projectId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Loeschen');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function createProjectFromTemplate() {
        var templateId = $('#pm-template-select').val();
        var projectTitle = $('#pm-template-project-title').val().trim();
        var projectDue = $('#pm-template-project-due').val();

        if (!templateId) {
            alert('Bitte waehlen Sie eine Vorlage aus.');
            return;
        }

        if (!projectTitle) {
            alert('Bitte geben Sie einen Projekttitel ein.');
            return;
        }

        var $btn = $('#pm-template-project-form button[type="submit"]');
        $btn.prop('disabled', true).text('Erstellen...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_create_from_template',
                nonce: pmData.nonce,
                template_id: templateId,
                project_title: projectTitle,
                project_due_date: projectDue
            },
            success: function(response) {
                if (response.success) {
                    closeModal('pm-template-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Erstellen');
                    $btn.prop('disabled', false).text('Projekt erstellen');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Projekt erstellen');
            }
        });
    }

    // ========================================
    // Task Management
    // ========================================

    function initTasks() {
        // Add task button (per project)
        $(document).on('click', '.pm-add-task', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var projectId = $(this).data('project');
            resetTaskForm();
            $('#pm-task-project-id').val(projectId);
            $('#pm-task-modal-title').text('Neue Aufgabe');
            openModal('pm-task-modal');
        });

        // Click on task row to view details
        $(document).on('click', '.pm-task-row .pm-task-info', function(e) {
            e.preventDefault();
            var taskId = $(this).data('task');
            loadTaskDetails(taskId);
        });

        // Complete task via checkbox
        $(document).on('change', '.pm-task-complete-checkbox', function() {
            var taskId = $(this).data('task');
            var isChecked = $(this).is(':checked');

            if (isChecked) {
                if (confirm('Aufgabe als erledigt markieren?')) {
                    completeTask(taskId);
                } else {
                    $(this).prop('checked', false);
                }
            }
        });

        // Task form submit
        $(document).on('submit', '#pm-task-form', function(e) {
            e.preventDefault();
            saveTask();
        });
    }

    function resetTaskForm() {
        $('#pm-task-id').val('');
        $('#pm-task-project-id').val('');
        $('#pm-task-title').val('');
        $('#pm-task-description').val('');
        $('#pm-task-assignee').val('');
        $('#pm-task-priority').val('medium');
        $('#pm-task-due-date').val('');
    }

    function saveTask() {
        var taskId = $('#pm-task-id').val();
        var projectId = $('#pm-task-project-id').val();
        var title = $('#pm-task-title').val().trim();
        var description = $('#pm-task-description').val().trim();
        var assignee = $('#pm-task-assignee').val();
        var priority = $('#pm-task-priority').val();
        var dueDate = $('#pm-task-due-date').val();

        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        if (!projectId && !taskId) {
            alert('Projekt-ID fehlt.');
            return;
        }

        var $btn = $('#pm-task-form button[type="submit"]');
        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: taskId ? 'pm_update_task' : 'pm_create_task',
                nonce: pmData.nonce,
                task_id: taskId,
                project_id: projectId,
                title: title,
                description: description,
                assignee: assignee,
                priority: priority,
                due_date: dueDate
            },
            success: function(response) {
                if (response.success) {
                    closeModal('pm-task-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                    $btn.prop('disabled', false).text('Speichern');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Speichern');
            }
        });
    }

    function completeTask(taskId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_complete_task',
                nonce: pmData.nonce,
                task_id: taskId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                    $('.pm-task-complete-checkbox[data-task="' + taskId + '"]').prop('checked', false);
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
                $('.pm-task-complete-checkbox[data-task="' + taskId + '"]').prop('checked', false);
            }
        });
    }

    function loadTaskDetails(taskId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_task_details',
                nonce: pmData.nonce,
                task_id: taskId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    renderTaskDetailModal(response.data);
                } else {
                    alert(response.data.message || 'Fehler beim Laden');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function renderTaskDetailModal(data) {
        var task = data.task;
        var comments = data.comments || [];
        var attachments = data.attachments || [];

        PM.currentTask = task.id;
        var priorityLabels = { high: 'Hoch', medium: 'Mittel', low: 'Niedrig' };
        var isCompleted = task.status === 'completed';

        $('#pm-task-detail-title').text(task.title);

        var html = '<div class="pm-task-detail-view">';

        // Meta info
        html += '<div class="pm-task-meta-grid">';
        html += '<div class="pm-meta-item"><strong>Projekt:</strong> ' + escapeHtml(task.project) + '</div>';
        html += '<div class="pm-meta-item"><strong>Prioritaet:</strong> <span class="pm-priority-badge pm-priority-' + task.priority + '">' + priorityLabels[task.priority] + '</span></div>';
        html += '<div class="pm-meta-item"><strong>Status:</strong> ' + (isCompleted ? '<span class="pm-status-completed">Erledigt</span>' : '<span class="pm-status-pending">Offen</span>') + '</div>';
        if (task.assignee) {
            html += '<div class="pm-meta-item"><strong>Zugewiesen an:</strong> ' + escapeHtml(task.assignee) + '</div>';
        }
        if (task.due_date) {
            html += '<div class="pm-meta-item"><strong>Faellig:</strong> ' + formatDate(task.due_date) + '</div>';
        }
        html += '</div>';

        // Description
        if (task.description) {
            html += '<div class="pm-task-description-section"><h4>Beschreibung</h4><p>' + escapeHtml(task.description).replace(/\n/g, '<br>') + '</p></div>';
        }

        // Attachments
        if (attachments.length > 0) {
            html += '<div class="pm-task-attachments-section"><h4>Anhaenge (' + attachments.length + ')</h4><ul class="pm-attachment-list">';
            $.each(attachments, function(i, att) {
                html += '<li><a href="' + att.url + '" target="_blank"><span class="dashicons dashicons-paperclip"></span> ' + escapeHtml(att.filename) + '</a></li>';
            });
            html += '</ul></div>';
        }

        // Comments
        html += '<div class="pm-task-comments-section"><h4>Kommentare (' + comments.length + ')</h4>';
        if (comments.length > 0) {
            html += '<div class="pm-comments-list">';
            $.each(comments, function(i, comment) {
                html += '<div class="pm-comment">';
                html += '<div class="pm-comment-header"><span class="pm-comment-author">' + escapeHtml(comment.author) + '</span>';
                html += '<span class="pm-comment-date">' + comment.date + '</span></div>';
                html += '<div class="pm-comment-content">' + escapeHtml(comment.content).replace(/\n/g, '<br>') + '</div>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<p class="pm-no-comments">Noch keine Kommentare.</p>';
        }

        // Comment form
        if (!isCompleted) {
            html += '<form id="pm-comment-form" class="pm-comment-form" data-task="' + task.id + '">';
            html += '<textarea id="pm-new-comment" rows="3" placeholder="Kommentar schreiben..." required></textarea>';
            html += '<button type="submit" class="pm-btn pm-btn-secondary">Kommentar senden</button>';
            html += '</form>';
        }
        html += '</div>';

        // Action buttons
        if (!isCompleted) {
            html += '<div class="pm-task-detail-actions">';
            html += '<button type="button" class="pm-btn pm-btn-primary pm-complete-task-detail" data-task="' + task.id + '">Als erledigt markieren</button>';
            html += '</div>';
        }

        html += '</div>';

        $('#pm-task-detail-content').html(html);
        openModal('pm-task-detail-modal');

        // Bind comment form
        $('#pm-comment-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            submitComment(task.id);
        });

        // Bind complete button
        $('.pm-complete-task-detail').off('click').on('click', function() {
            var taskId = $(this).data('task');
            if (confirm('Aufgabe als erledigt markieren?')) {
                completeTask(taskId);
            }
        });
    }

    function submitComment(taskId) {
        var comment = $('#pm-new-comment').val().trim();
        if (!comment) {
            alert('Bitte Kommentar eingeben');
            return;
        }

        var $btn = $('#pm-comment-form button[type="submit"]');
        $btn.prop('disabled', true).text('Senden...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_add_comment',
                nonce: pmData.nonce,
                task_id: taskId,
                comment: comment
            },
            success: function(response) {
                if (response.success) {
                    loadTaskDetails(taskId);
                } else {
                    alert(response.data.message || 'Fehler');
                    $btn.prop('disabled', false).text('Kommentar senden');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Kommentar senden');
            }
        });
    }

    // ========================================
    // Template Management (for [dgptm-projekt-templates])
    // ========================================

    function initTemplates() {
        // Create template button
        $(document).on('click', '#pm-create-template, #pm-create-template-empty', function(e) {
            e.preventDefault();
            resetTemplateForm();
            $('#pm-template-editor-title').text('Neue Vorlage');
            openModal('pm-template-editor-modal');
        });

        // Edit template
        $(document).on('click', '.pm-edit-template', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var templateId = $(this).data('id');
            loadTemplateForEdit(templateId);
        });

        // Delete template
        $(document).on('click', '.pm-delete-template', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var templateId = $(this).data('id');
            var templateTitle = $(this).closest('.pm-template-card').find('.pm-template-title').text();

            if (confirm('Moechten Sie die Vorlage "' + templateTitle + '" wirklich loeschen?')) {
                deleteTemplate(templateId);
            }
        });

        // Use template
        $(document).on('click', '.pm-use-template', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var templateId = $(this).data('id');
            $('#pm-use-template-id').val(templateId);
            $('#pm-new-project-title').val('');
            $('#pm-new-project-due').val('');
            openModal('pm-use-template-modal');
        });

        // Template form submit
        $(document).on('submit', '#pm-template-form', function(e) {
            e.preventDefault();
            saveTemplate();
        });

        // Use template form submit
        $(document).on('submit', '#pm-use-template-form', function(e) {
            e.preventDefault();
            useTemplate();
        });

        // Add task to template
        $(document).on('click', '#pm-add-template-task', function(e) {
            e.preventDefault();
            addTemplateTaskRow();
        });

        // Remove task from template
        $(document).on('click', '.pm-remove-template-task', function(e) {
            e.preventDefault();
            $(this).closest('.pm-template-task-row').remove();
        });
    }

    function resetTemplateForm() {
        $('#pm-template-id').val('');
        $('#pm-template-title').val('');
        $('#pm-template-description').val('');
        $('#pm-template-tasks-list').empty();
    }

    function addTemplateTaskRow(taskData) {
        var $template = $('#pm-template-task-row');
        if (!$template.length) return;

        var $clone = $($template.html());

        if (taskData) {
            $clone.find('.pm-template-task-title').val(taskData.title || '');
            $clone.find('.pm-template-task-priority').val(taskData.priority || 'medium');
            $clone.find('.pm-template-task-days').val(taskData.relative_due_days || 0);
            $clone.find('.pm-template-task-description').val(taskData.description || '');
        }

        $('#pm-template-tasks-list').append($clone);
    }

    function loadTemplateForEdit(templateId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_template',
                nonce: pmData.nonce,
                template_id: templateId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var template = response.data.template;
                    $('#pm-template-id').val(template.id);
                    $('#pm-template-title').val(template.title);
                    $('#pm-template-description').val(template.description);

                    // Load tasks
                    $('#pm-template-tasks-list').empty();
                    if (template.tasks && template.tasks.length) {
                        $.each(template.tasks, function(i, task) {
                            addTemplateTaskRow(task);
                        });
                    }

                    $('#pm-template-editor-title').text('Vorlage bearbeiten');
                    openModal('pm-template-editor-modal');
                } else {
                    alert(response.data.message || 'Fehler beim Laden');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function saveTemplate() {
        var templateId = $('#pm-template-id').val();
        var title = $('#pm-template-title').val().trim();
        var description = $('#pm-template-description').val().trim();

        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        // Collect tasks
        var tasks = [];
        $('#pm-template-tasks-list .pm-template-task-row').each(function() {
            var taskTitle = $(this).find('.pm-template-task-title').val().trim();
            if (taskTitle) {
                tasks.push({
                    title: taskTitle,
                    priority: $(this).find('.pm-template-task-priority').val(),
                    relative_due_days: parseInt($(this).find('.pm-template-task-days').val()) || 0,
                    description: $(this).find('.pm-template-task-description').val().trim()
                });
            }
        });

        var $btn = $('#pm-template-form button[type="submit"]');
        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_save_template',
                nonce: pmData.nonce,
                template_id: templateId,
                title: title,
                description: description,
                tasks: tasks
            },
            success: function(response) {
                if (response.success) {
                    closeModal('pm-template-editor-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                    $btn.prop('disabled', false).text('Vorlage speichern');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Vorlage speichern');
            }
        });
    }

    function deleteTemplate(templateId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_delete_template',
                nonce: pmData.nonce,
                template_id: templateId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Loeschen');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function useTemplate() {
        var templateId = $('#pm-use-template-id').val();
        var projectTitle = $('#pm-new-project-title').val().trim();
        var projectDue = $('#pm-new-project-due').val();

        if (!projectTitle) {
            alert('Bitte geben Sie einen Projekttitel ein.');
            return;
        }

        var $btn = $('#pm-use-template-form button[type="submit"]');
        $btn.prop('disabled', true).text('Erstellen...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_create_from_template',
                nonce: pmData.nonce,
                template_id: templateId,
                project_title: projectTitle,
                project_due_date: projectDue
            },
            success: function(response) {
                if (response.success) {
                    closeModal('pm-use-template-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Erstellen');
                    $btn.prop('disabled', false).text('Projekt erstellen');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Projekt erstellen');
            }
        });
    }

    // ========================================
    // My Tasks View (for [dgptm-meine-aufgaben])
    // ========================================

    function initMyTasks() {
        // Toggle completed tasks
        $(document).on('click', '#pm-completed-toggle', function() {
            var $icon = $(this).find('.pm-toggle-icon');
            var $list = $('.pm-completed-tasks');

            if ($list.is(':visible')) {
                $list.slideUp(200);
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $(this).addClass('pm-collapsed');
            } else {
                $list.slideDown(200);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $(this).removeClass('pm-collapsed');
            }
        });

        // View task button
        $(document).on('click', '.pm-view-task', function(e) {
            e.preventDefault();
            var taskId = $(this).data('task');
            loadTaskDetails(taskId);
        });

        // Complete task button
        $(document).on('click', '.pm-complete-task-btn', function(e) {
            e.preventDefault();
            var taskId = $(this).data('task');
            if (confirm('Aufgabe als erledigt markieren?')) {
                completeTask(taskId);
            }
        });

        // Task card click (not checkbox or buttons)
        $(document).on('click', '.pm-task-card', function(e) {
            if ($(e.target).closest('.pm-task-card-checkbox, .pm-task-card-actions').length) {
                return;
            }
            var taskId = $(this).data('id');
            if (taskId) {
                loadTaskDetails(taskId);
            }
        });
    }

    // ========================================
    // Utility Functions
    // ========================================

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        var parts = dateString.split('-');
        if (parts.length === 3) {
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }
        return dateString;
    }

})(jQuery);
