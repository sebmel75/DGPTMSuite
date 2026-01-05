/**
 * DGPTM Projektmanagement - Frontend JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Global state
    var PM = {
        currentProject: null,
        currentTask: null,
        templateTasks: []
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
        $(document).on('click', '.pm-modal-close', function() {
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

    function showConfirm(title, message, callback) {
        $('#pm-confirm-title').text(title);
        $('#pm-confirm-message').text(message);
        $('#pm-confirm-btn').off('click').on('click', function() {
            closeModal('pm-confirm-modal');
            if (typeof callback === 'function') {
                callback();
            }
        });
        openModal('pm-confirm-modal');
    }

    // ========================================
    // Project Management
    // ========================================

    function initProjects() {
        // Create project button
        $('#pm-create-project, #pm-create-project-empty').on('click', function() {
            resetProjectForm();
            $('#pm-project-editor-title').text('Neues Projekt');
            openModal('pm-project-editor-modal');
        });

        // Edit project
        $(document).on('click', '.pm-edit-project', function() {
            var projectId = $(this).data('id');
            loadProjectForEdit(projectId);
        });

        // Delete project
        $(document).on('click', '.pm-delete-project', function() {
            var projectId = $(this).data('id');
            var projectTitle = $(this).closest('.pm-project-card').find('.pm-project-title').text();
            showConfirm('Projekt loeschen', 'Moechten Sie das Projekt "' + projectTitle + '" wirklich loeschen? Alle zugehoerigen Aufgaben werden ebenfalls geloescht.', function() {
                deleteProject(projectId);
            });
        });

        // Project form submit
        $('#pm-project-form').on('submit', function(e) {
            e.preventDefault();
            saveProject();
        });

        // View project tasks
        $(document).on('click', '.pm-view-project, .pm-project-card', function(e) {
            if ($(e.target).closest('.pm-project-actions').length) {
                return; // Don't open if clicking action buttons
            }
            var projectId = $(this).closest('.pm-project-card').data('id') || $(this).data('id');
            openProjectTasks(projectId);
        });

        // Back to projects
        $(document).on('click', '#pm-back-to-projects', function() {
            $('#pm-project-tasks-view').hide();
            $('#pm-projects-view').show();
        });

        // Create from template
        $(document).on('click', '.pm-create-from-template', function() {
            loadTemplatesForSelection();
        });
    }

    function resetProjectForm() {
        $('#pm-project-id').val('');
        $('#pm-project-title').val('');
        $('#pm-project-description').val('');
        $('#pm-project-due').val('');
    }

    function loadProjectForEdit(projectId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_project',
                nonce: pmData.nonce,
                project_id: projectId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var project = response.data;
                    $('#pm-project-id').val(project.id);
                    $('#pm-project-title').val(project.title);
                    $('#pm-project-description').val(project.description);
                    $('#pm-project-due').val(project.due_date);
                    $('#pm-project-editor-title').text('Projekt bearbeiten');
                    openModal('pm-project-editor-modal');
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

    function saveProject() {
        var projectId = $('#pm-project-id').val();
        var title = $('#pm-project-title').val().trim();
        var description = $('#pm-project-description').val().trim();
        var dueDate = $('#pm-project-due').val();

        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        showLoading();
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
                hideLoading();
                if (response.success) {
                    closeModal('pm-project-editor-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
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

    function openProjectTasks(projectId) {
        PM.currentProject = projectId;
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
                if (response.success) {
                    renderProjectTasksView(response.data);
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

    function renderProjectTasksView(data) {
        var $view = $('#pm-project-tasks-view');
        var $content = $view.find('#pm-tasks-content');

        $view.find('#pm-project-tasks-title').text(data.project.title);
        $content.empty();

        if (data.tasks.length === 0) {
            $content.html('<div class="pm-empty-state"><span class="dashicons dashicons-list-view"></span><p>Keine Aufgaben vorhanden.</p></div>');
        } else {
            var $list = $('<div class="pm-task-list"></div>');
            $.each(data.tasks, function(i, task) {
                $list.append(renderTaskRow(task));
            });
            $content.append($list);
        }

        $('#pm-projects-view').hide();
        $view.show();
    }

    function renderTaskRow(task) {
        var priorityLabels = { high: 'Hoch', medium: 'Mittel', low: 'Niedrig' };
        var isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'completed';
        var isCompleted = task.status === 'completed';

        var html = '<div class="pm-task-row pm-priority-' + task.priority + (isOverdue ? ' pm-overdue' : '') + (isCompleted ? ' pm-completed' : '') + '" data-id="' + task.id + '">';
        html += '<div class="pm-task-checkbox"><input type="checkbox" class="pm-task-complete-checkbox" data-task="' + task.id + '"' + (isCompleted ? ' checked disabled' : '') + '></div>';
        html += '<div class="pm-task-info">';
        html += '<span class="pm-task-title">' + escapeHtml(task.title) + '</span>';
        if (task.assignee_name) {
            html += '<span class="pm-task-assignee"><span class="dashicons dashicons-admin-users"></span> ' + escapeHtml(task.assignee_name) + '</span>';
        }
        html += '</div>';
        html += '<div class="pm-task-meta">';
        html += '<span class="pm-priority-badge pm-priority-' + task.priority + '">' + priorityLabels[task.priority] + '</span>';
        if (task.due_date) {
            html += '<span class="pm-due-date' + (isOverdue ? ' pm-overdue' : '') + '">' + formatDate(task.due_date) + '</span>';
        }
        html += '</div>';
        html += '<div class="pm-task-actions">';
        html += '<button type="button" class="pm-btn-icon pm-edit-task" data-id="' + task.id + '" title="Bearbeiten"><span class="dashicons dashicons-edit"></span></button>';
        html += '<button type="button" class="pm-btn-icon pm-delete-task" data-id="' + task.id + '" title="Loeschen"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    // ========================================
    // Task Management
    // ========================================

    function initTasks() {
        // Create task button
        $(document).on('click', '#pm-create-task', function() {
            resetTaskForm();
            loadUsersForSelect();
            $('#pm-task-editor-title').text('Neue Aufgabe');
            openModal('pm-task-editor-modal');
        });

        // Edit task
        $(document).on('click', '.pm-edit-task', function(e) {
            e.stopPropagation();
            var taskId = $(this).data('id');
            loadTaskForEdit(taskId);
        });

        // Delete task
        $(document).on('click', '.pm-delete-task', function(e) {
            e.stopPropagation();
            var taskId = $(this).data('id');
            var taskTitle = $(this).closest('.pm-task-row').find('.pm-task-title').text();
            showConfirm('Aufgabe loeschen', 'Moechten Sie die Aufgabe "' + taskTitle + '" wirklich loeschen?', function() {
                deleteTask(taskId);
            });
        });

        // Complete task via checkbox
        $(document).on('change', '.pm-task-complete-checkbox', function() {
            var taskId = $(this).data('task');
            if ($(this).is(':checked')) {
                completeTask(taskId);
            }
        });

        // Complete task via button
        $(document).on('click', '.pm-complete-task-btn', function() {
            var taskId = $(this).data('task');
            completeTask(taskId);
        });

        // Task form submit
        $('#pm-task-form').on('submit', function(e) {
            e.preventDefault();
            saveTask();
        });

        // View task details
        $(document).on('click', '.pm-view-task', function() {
            var taskId = $(this).data('task');
            loadTaskDetails(taskId);
        });

        // Task row click
        $(document).on('click', '.pm-task-row', function(e) {
            if ($(e.target).closest('.pm-task-checkbox, .pm-task-actions').length) {
                return;
            }
            var taskId = $(this).data('id');
            loadTaskDetails(taskId);
        });

        // Attachment upload
        $('#pm-task-attachment-input').on('change', function() {
            handleAttachmentUpload(this.files);
        });

        // Remove attachment
        $(document).on('click', '.pm-remove-attachment', function() {
            $(this).closest('.pm-attachment-item').remove();
        });

        // Comment form submit
        $(document).on('submit', '#pm-comment-form', function(e) {
            e.preventDefault();
            submitComment();
        });
    }

    function resetTaskForm() {
        $('#pm-task-id').val('');
        $('#pm-task-title').val('');
        $('#pm-task-description').val('');
        $('#pm-task-assignee').val('');
        $('#pm-task-priority').val('medium');
        $('#pm-task-due').val('');
        $('#pm-task-attachments-list').empty();
    }

    function loadUsersForSelect() {
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_users',
                nonce: pmData.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $select = $('#pm-task-assignee');
                    $select.find('option:not(:first)').remove();
                    $.each(response.data, function(i, user) {
                        $select.append('<option value="' + user.id + '">' + escapeHtml(user.name) + '</option>');
                    });
                }
            }
        });
    }

    function loadTaskForEdit(taskId) {
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
                    var task = response.data;
                    loadUsersForSelect();
                    setTimeout(function() {
                        $('#pm-task-id').val(task.id);
                        $('#pm-task-title').val(task.title);
                        $('#pm-task-description').val(task.description);
                        $('#pm-task-assignee').val(task.assignee);
                        $('#pm-task-priority').val(task.priority);
                        $('#pm-task-due').val(task.due_date);

                        // Load existing attachments
                        var $list = $('#pm-task-attachments-list');
                        $list.empty();
                        if (task.attachments && task.attachments.length) {
                            $.each(task.attachments, function(i, att) {
                                $list.append('<div class="pm-attachment-item" data-id="' + att.id + '">' +
                                    '<span class="dashicons dashicons-paperclip"></span>' +
                                    '<span class="pm-attachment-name">' + escapeHtml(att.filename) + '</span>' +
                                    '<button type="button" class="pm-remove-attachment">&times;</button>' +
                                    '<input type="hidden" name="existing_attachments[]" value="' + att.id + '">' +
                                    '</div>');
                            });
                        }

                        $('#pm-task-editor-title').text('Aufgabe bearbeiten');
                        openModal('pm-task-editor-modal');
                    }, 200);
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

    function saveTask() {
        var taskId = $('#pm-task-id').val();
        var title = $('#pm-task-title').val().trim();
        var description = $('#pm-task-description').val().trim();
        var assignee = $('#pm-task-assignee').val();
        var priority = $('#pm-task-priority').val();
        var dueDate = $('#pm-task-due').val();

        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        // Collect attachment IDs
        var attachmentIds = [];
        $('#pm-task-attachments-list .pm-attachment-item').each(function() {
            var id = $(this).data('id');
            if (id) {
                attachmentIds.push(id);
            }
        });

        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: taskId ? 'pm_update_task' : 'pm_create_task',
                nonce: pmData.nonce,
                task_id: taskId,
                project_id: PM.currentProject,
                title: title,
                description: description,
                assignee: assignee,
                priority: priority,
                due_date: dueDate,
                attachments: attachmentIds
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    closeModal('pm-task-editor-modal');
                    openProjectTasks(PM.currentProject);
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function deleteTask(taskId) {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_delete_task',
                nonce: pmData.nonce,
                task_id: taskId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    if (PM.currentProject) {
                        openProjectTasks(PM.currentProject);
                    } else {
                        location.reload();
                    }
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
                    if (PM.currentProject) {
                        openProjectTasks(PM.currentProject);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data.message || 'Fehler');
                    // Uncheck the checkbox
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

    function renderTaskDetailModal(task) {
        PM.currentTask = task.id;
        var priorityLabels = { high: 'Hoch', medium: 'Mittel', low: 'Niedrig' };
        var isCompleted = task.status === 'completed';

        $('#pm-task-detail-title').text(task.title);

        var html = '<div class="pm-task-detail-view">';

        // Meta info
        html += '<div class="pm-task-meta-grid">';
        html += '<div class="pm-meta-item"><strong>Prioritaet:</strong> <span class="pm-priority-badge pm-priority-' + task.priority + '">' + priorityLabels[task.priority] + '</span></div>';
        html += '<div class="pm-meta-item"><strong>Status:</strong> ' + (isCompleted ? '<span class="pm-status-completed">Erledigt</span>' : '<span class="pm-status-pending">Offen</span>') + '</div>';
        if (task.assignee_name) {
            html += '<div class="pm-meta-item"><strong>Zugewiesen:</strong> ' + escapeHtml(task.assignee_name) + '</div>';
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
        if (task.attachments && task.attachments.length) {
            html += '<div class="pm-task-attachments-section"><h4>Anhaenge</h4><ul class="pm-attachment-list">';
            $.each(task.attachments, function(i, att) {
                html += '<li><a href="' + att.url + '" target="_blank"><span class="dashicons dashicons-paperclip"></span> ' + escapeHtml(att.filename) + '</a></li>';
            });
            html += '</ul></div>';
        }

        // Token link (for managers)
        if (task.token_url) {
            html += '<div class="pm-task-token-section"><h4>Direktlink (ohne Login)</h4>';
            html += '<div class="pm-token-link-wrapper">';
            html += '<input type="text" readonly value="' + task.token_url + '" class="pm-token-link-input" id="pm-token-link-input">';
            html += '<button type="button" class="pm-btn pm-btn-small pm-btn-secondary" id="pm-copy-token-link">Kopieren</button>';
            html += '</div></div>';
        }

        // Comments
        html += '<div class="pm-task-comments-section"><h4>Kommentare (' + (task.comments ? task.comments.length : 0) + ')</h4>';
        if (task.comments && task.comments.length) {
            html += '<div class="pm-comments-list">';
            $.each(task.comments, function(i, comment) {
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
            html += '<form id="pm-comment-form" class="pm-comment-form">';
            html += '<textarea id="pm-new-comment" rows="3" placeholder="Kommentar schreiben..." required></textarea>';
            html += '<button type="submit" class="pm-btn pm-btn-secondary">Kommentar senden</button>';
            html += '</form>';
        }
        html += '</div>';

        html += '</div>';

        $('#pm-task-detail-content').html(html);

        // Copy token link handler
        $('#pm-copy-token-link').on('click', function() {
            var $input = $('#pm-token-link-input');
            $input.select();
            document.execCommand('copy');
            $(this).text('Kopiert!');
            setTimeout(function() {
                $('#pm-copy-token-link').text('Kopieren');
            }, 2000);
        });

        openModal('pm-task-detail-modal');
    }

    function submitComment() {
        var comment = $('#pm-new-comment').val().trim();
        if (!comment) {
            alert('Bitte Kommentar eingeben');
            return;
        }

        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_add_comment',
                nonce: pmData.nonce,
                task_id: PM.currentTask,
                comment: comment
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    loadTaskDetails(PM.currentTask);
                } else {
                    alert(response.data.message || 'Fehler');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function handleAttachmentUpload(files) {
        if (!files.length) return;

        var formData = new FormData();
        formData.append('action', 'pm_upload_attachment');
        formData.append('nonce', pmData.nonce);

        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var $list = $('#pm-task-attachments-list');
                    $.each(response.data, function(i, att) {
                        $list.append('<div class="pm-attachment-item" data-id="' + att.id + '">' +
                            '<span class="dashicons dashicons-paperclip"></span>' +
                            '<span class="pm-attachment-name">' + escapeHtml(att.filename) + '</span>' +
                            '<button type="button" class="pm-remove-attachment">&times;</button>' +
                            '</div>');
                    });
                } else {
                    alert(response.data.message || 'Fehler beim Upload');
                }
                // Reset file input
                $('#pm-task-attachment-input').val('');
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
                $('#pm-task-attachment-input').val('');
            }
        });
    }

    // ========================================
    // Template Management
    // ========================================

    function initTemplates() {
        // Create template button
        $('#pm-create-template, #pm-create-template-empty').on('click', function() {
            resetTemplateForm();
            $('#pm-template-editor-title').text('Neue Vorlage');
            openModal('pm-template-editor-modal');
        });

        // Edit template
        $(document).on('click', '.pm-edit-template', function() {
            var templateId = $(this).data('id');
            loadTemplateForEdit(templateId);
        });

        // Delete template
        $(document).on('click', '.pm-delete-template', function() {
            var templateId = $(this).data('id');
            var templateTitle = $(this).closest('.pm-template-card').find('.pm-template-title').text();
            showConfirm('Vorlage loeschen', 'Moechten Sie die Vorlage "' + templateTitle + '" wirklich loeschen?', function() {
                deleteTemplate(templateId);
            });
        });

        // Use template
        $(document).on('click', '.pm-use-template', function() {
            var templateId = $(this).data('id');
            var templateTitle = $(this).closest('.pm-template-card').find('.pm-template-title').text();
            $('#pm-use-template-id').val(templateId);
            $('#pm-new-project-title').val('');
            $('#pm-new-project-due').val('');
            openModal('pm-use-template-modal');
        });

        // Template form submit
        $('#pm-template-form').on('submit', function(e) {
            e.preventDefault();
            saveTemplate();
        });

        // Use template form submit
        $('#pm-use-template-form').on('submit', function(e) {
            e.preventDefault();
            createProjectFromTemplate();
        });

        // Add task to template
        $('#pm-add-template-task').on('click', function() {
            addTemplateTaskRow();
        });

        // Remove task from template
        $(document).on('click', '.pm-remove-template-task', function() {
            $(this).closest('.pm-template-task-row').remove();
            updateTemplateTaskIndices();
        });

        // Initialize sortable for template tasks
        if (typeof $.fn.sortable !== 'undefined') {
            $('#pm-template-tasks-list').sortable({
                handle: '.pm-template-task-drag',
                update: function() {
                    updateTemplateTaskIndices();
                }
            });
        }
    }

    function resetTemplateForm() {
        $('#pm-template-id').val('');
        $('#pm-template-title').val('');
        $('#pm-template-description').val('');
        $('#pm-template-tasks-list').empty();
        PM.templateTasks = [];
    }

    function addTemplateTaskRow(taskData) {
        var $template = $('#pm-template-task-row');
        if (!$template.length) return;

        var $clone = $($template.html());
        var index = $('#pm-template-tasks-list .pm-template-task-row').length;
        $clone.attr('data-index', index);

        if (taskData) {
            $clone.find('.pm-template-task-title').val(taskData.title || '');
            $clone.find('.pm-template-task-priority').val(taskData.priority || 'medium');
            $clone.find('.pm-template-task-days').val(taskData.days_before_due || 0);
            $clone.find('.pm-template-task-description').val(taskData.description || '');
        }

        $('#pm-template-tasks-list').append($clone);
    }

    function updateTemplateTaskIndices() {
        $('#pm-template-tasks-list .pm-template-task-row').each(function(index) {
            $(this).attr('data-index', index);
        });
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
                    var template = response.data;
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
                    days_before_due: parseInt($(this).find('.pm-template-task-days').val()) || 0,
                    description: $(this).find('.pm-template-task-description').val().trim()
                });
            }
        });

        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_save_template',
                nonce: pmData.nonce,
                template_id: templateId,
                title: title,
                description: description,
                tasks: JSON.stringify(tasks)
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    closeModal('pm-template-editor-modal');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
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

    function createProjectFromTemplate() {
        var templateId = $('#pm-use-template-id').val();
        var projectTitle = $('#pm-new-project-title').val().trim();
        var projectDue = $('#pm-new-project-due').val();

        if (!projectTitle) {
            alert('Bitte geben Sie einen Projekttitel ein.');
            return;
        }

        showLoading();
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
                hideLoading();
                if (response.success) {
                    closeModal('pm-use-template-modal');
                    // Redirect to main management if we're on templates page
                    if (window.location.href.indexOf('projekt-templates') > -1 && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data.message || 'Fehler beim Erstellen');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function loadTemplatesForSelection() {
        showLoading();
        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_get_templates',
                nonce: pmData.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success && response.data.length) {
                    renderTemplateSelectionModal(response.data);
                } else {
                    alert('Keine Vorlagen vorhanden. Bitte erstellen Sie zuerst eine Vorlage.');
                }
            },
            error: function() {
                hideLoading();
                alert('Verbindungsfehler');
            }
        });
    }

    function renderTemplateSelectionModal(templates) {
        var html = '<div class="pm-template-selection-list">';
        $.each(templates, function(i, template) {
            html += '<div class="pm-template-selection-item" data-id="' + template.id + '">';
            html += '<strong>' + escapeHtml(template.title) + '</strong>';
            if (template.task_count) {
                html += '<span class="pm-template-task-count">' + template.task_count + ' Aufgabe(n)</span>';
            }
            html += '</div>';
        });
        html += '</div>';

        // Use existing modal or create dynamic one
        if ($('#pm-template-select-modal').length === 0) {
            $('body').append('<div class="pm-modal" id="pm-template-select-modal"><div class="pm-modal-content"><div class="pm-modal-header"><h3>Vorlage auswaehlen</h3><button type="button" class="pm-modal-close">&times;</button></div><div class="pm-modal-body" id="pm-template-select-content"></div></div></div>');
        }

        $('#pm-template-select-content').html(html);

        // Template selection click
        $('.pm-template-selection-item').on('click', function() {
            var templateId = $(this).data('id');
            closeModal('pm-template-select-modal');
            $('#pm-use-template-id').val(templateId);
            $('#pm-new-project-title').val('');
            $('#pm-new-project-due').val('');
            openModal('pm-use-template-modal');
        });

        openModal('pm-template-select-modal');
    }

    // ========================================
    // My Tasks View
    // ========================================

    function initMyTasks() {
        // Toggle completed tasks
        $('#pm-completed-toggle').on('click', function() {
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
        var date = new Date(dateString);
        var day = ('0' + date.getDate()).slice(-2);
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();
        return day + '.' + month + '.' + year;
    }

})(jQuery);
