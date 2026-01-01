/**
 * Zeitschrift Kardiotechnik - Enhanced Editor JavaScript
 * WYSIWYG-Editor mit Medienverwaltung
 */

(function($) {
    'use strict';

    var ZKEditorManager = {
        editors: {},
        selectedMedia: [],
        currentEditor: null,
        currentSize: 'medium',

        /**
         * Initialize all editors on page
         */
        init: function() {
            var self = this;

            // Initialize each editor wrapper
            $('.zk-editor-wrapper').each(function() {
                self.initEditor($(this));
            });
        },

        /**
         * Initialize single editor
         */
        initEditor: function($wrapper) {
            var self = this;
            var editorId = $wrapper.data('editor-id');
            var $textarea = $wrapper.find('.zk-editor-textarea');
            var options = $textarea.data('options') || {};

            this.editors[editorId] = {
                $wrapper: $wrapper,
                $textarea: $textarea,
                options: options,
                tinymce: null
            };

            // Initialize TinyMCE
            this.initTinyMCE(editorId, options);

            // Bind events
            this.bindEditorEvents($wrapper, editorId);
        },

        /**
         * Initialize TinyMCE
         */
        initTinyMCE: function(editorId, options) {
            var self = this;

            if (typeof wp === 'undefined' || typeof wp.editor === 'undefined') {
                return;
            }

            var toolbar = this.getToolbar(options.toolbar || 'full');

            setTimeout(function() {
                wp.editor.initialize(editorId, {
                    tinymce: {
                        wpautop: true,
                        plugins: 'lists link paste table charmap hr',
                        toolbar1: toolbar.toolbar1,
                        toolbar2: toolbar.toolbar2,
                        height: options.height || 400,
                        menubar: false,
                        statusbar: true,
                        resize: true,
                        setup: function(editor) {
                            self.editors[editorId].tinymce = editor;

                            // Drag & Drop
                            editor.on('drop', function(e) {
                                self.handleDrop(e, editorId);
                            });

                            editor.on('dragover', function(e) {
                                e.preventDefault();
                            });
                        }
                    },
                    quicktags: {
                        buttons: 'strong,em,link,ul,ol,li,code'
                    },
                    mediaButtons: false
                });
            }, 100);
        },

        /**
         * Get toolbar configuration
         */
        getToolbar: function(type) {
            var toolbars = {
                full: {
                    toolbar1: 'formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote hr | link unlink | table | removeformat',
                    toolbar2: 'subscript superscript | charmap | pastetext | undo redo'
                },
                basic: {
                    toolbar1: 'bold italic | bullist numlist | link unlink | removeformat',
                    toolbar2: ''
                },
                minimal: {
                    toolbar1: 'bold italic link',
                    toolbar2: ''
                }
            };

            return toolbars[type] || toolbars.full;
        },

        /**
         * Bind editor events
         */
        bindEditorEvents: function($wrapper, editorId) {
            var self = this;

            // Add Image button
            $wrapper.on('click', '.zk-btn-add-image', function(e) {
                e.preventDefault();
                self.currentEditor = editorId;
                self.openMediaModal('image');
            });

            // Add File button
            $wrapper.on('click', '.zk-btn-add-file', function(e) {
                e.preventDefault();
                self.currentEditor = editorId;
                self.openMediaModal('file');
            });

            // Media Library button
            $wrapper.on('click', '.zk-btn-media-library', function(e) {
                e.preventDefault();
                self.currentEditor = editorId;
                self.openMediaModal('all');
            });

            // Fullscreen button
            $wrapper.on('click', '.zk-btn-fullscreen', function(e) {
                e.preventDefault();
                $wrapper.toggleClass('zk-fullscreen');
            });

            // Dropzone events
            var $dropzone = $wrapper.find('.zk-editor-dropzone');

            $wrapper.on('dragenter dragover', function(e) {
                e.preventDefault();
                $dropzone.show();
            });

            $dropzone.on('dragleave', function(e) {
                e.preventDefault();
                if (e.originalEvent.relatedTarget === null || !$.contains($dropzone[0], e.originalEvent.relatedTarget)) {
                    $dropzone.hide();
                }
            });

            $dropzone.on('drop', function(e) {
                e.preventDefault();
                $dropzone.hide();
                self.handleDrop(e.originalEvent, editorId);
            });

            // Media Modal events
            var $modal = $wrapper.find('.zk-media-modal');

            $modal.on('click', '.zk-media-modal-close', function() {
                self.closeMediaModal($wrapper);
            });

            $modal.on('click', '.zk-media-tab', function() {
                var tab = $(this).data('tab');
                self.switchMediaTab($modal, tab);
            });

            $modal.on('click', '.zk-media-item', function() {
                self.toggleMediaSelection($(this));
            });

            $modal.on('click', '.zk-btn-insert', function() {
                self.insertSelectedMedia(editorId);
            });

            // Upload handling
            var $uploadArea = $modal.find('.zk-upload-area');
            var $fileInput = $uploadArea.find('.zk-file-input');

            $uploadArea.on('dragover', function() {
                $(this).addClass('dragover');
            }).on('dragleave drop', function() {
                $(this).removeClass('dragover');
            });

            $fileInput.on('change', function() {
                self.handleFileSelect(this.files, editorId);
            });

            // Size selection
            $modal.on('click', '.zk-size-option', function() {
                $modal.find('.zk-size-option').removeClass('selected');
                $(this).addClass('selected');
                self.currentSize = $(this).data('size');
            });

            // Media search
            var searchTimeout;
            $modal.on('input', '.zk-media-search', function() {
                var search = $(this).val();
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.loadMediaLibrary($modal, search);
                }, 300);
            });

            // Media type filter
            $modal.on('change', '.zk-media-type-filter', function() {
                self.loadMediaLibrary($modal);
            });
        },

        /**
         * Open media modal
         */
        openMediaModal: function(type) {
            var editor = this.editors[this.currentEditor];
            if (!editor) return;

            var $modal = editor.$wrapper.find('.zk-media-modal');
            $modal.show();
            this.selectedMedia = [];
            this.updateInsertButton($modal);

            // Switch to upload tab by default
            this.switchMediaTab($modal, 'upload');
        },

        /**
         * Close media modal
         */
        closeMediaModal: function($wrapper) {
            $wrapper.find('.zk-media-modal').hide();
            this.selectedMedia = [];
        },

        /**
         * Switch media tab
         */
        switchMediaTab: function($modal, tab) {
            $modal.find('.zk-media-tab').removeClass('active');
            $modal.find('.zk-media-tab[data-tab="' + tab + '"]').addClass('active');

            $modal.find('.zk-media-tab-content').hide();
            $modal.find('.zk-media-tab-content[data-tab="' + tab + '"]').show();

            if (tab === 'library') {
                this.loadMediaLibrary($modal);
            }
        },

        /**
         * Load media library
         */
        loadMediaLibrary: function($modal, search) {
            var self = this;
            var $grid = $modal.find('.zk-media-grid');
            var $loading = $modal.find('.zk-media-loading');
            var type = $modal.find('.zk-media-type-filter').val();

            $grid.empty();
            $loading.show();

            $.ajax({
                url: zkEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zk_get_media_library',
                    nonce: zkEditor.nonce,
                    type: type,
                    search: search || ''
                },
                success: function(response) {
                    $loading.hide();

                    if (response.success && response.data.items) {
                        self.renderMediaGrid($grid, response.data.items);
                    } else {
                        $grid.html('<p class="zk-no-media">Keine Medien gefunden</p>');
                    }
                },
                error: function() {
                    $loading.hide();
                    $grid.html('<p class="zk-error">Fehler beim Laden</p>');
                }
            });
        },

        /**
         * Render media grid
         */
        renderMediaGrid: function($grid, items) {
            var self = this;

            items.forEach(function(item) {
                var $item = $('<div class="zk-media-item" data-id="' + item.id + '" data-url="' + item.url + '" data-mime="' + item.mime + '">');

                if (item.is_image && item.thumbnail) {
                    $item.html('<img src="' + item.thumbnail + '" alt="' + self.escapeHtml(item.title) + '">');
                } else {
                    var icon = self.getFileIcon(item.mime);
                    $item.addClass('zk-media-item-file');
                    $item.html('<span class="dashicons ' + icon + '"></span><span>' + self.escapeHtml(item.filename) + '</span>');
                }

                $item.append('<div class="zk-media-item-check"><span class="dashicons dashicons-yes"></span></div>');

                // Store full data
                $item.data('media', item);

                $grid.append($item);
            });
        },

        /**
         * Toggle media selection
         */
        toggleMediaSelection: function($item) {
            var id = $item.data('id');
            var index = this.selectedMedia.indexOf(id);

            if (index > -1) {
                this.selectedMedia.splice(index, 1);
                $item.removeClass('selected');
            } else {
                this.selectedMedia.push(id);
                $item.addClass('selected');
            }

            var $modal = $item.closest('.zk-media-modal');
            this.updateInsertButton($modal);
            this.updateSelectionInfo($modal);
        },

        /**
         * Update insert button state
         */
        updateInsertButton: function($modal) {
            var $btn = $modal.find('.zk-btn-insert');
            $btn.prop('disabled', this.selectedMedia.length === 0);
        },

        /**
         * Update selection info
         */
        updateSelectionInfo: function($modal) {
            var $info = $modal.find('.zk-media-selection-info');
            var count = this.selectedMedia.length;

            if (count === 0) {
                $info.text('');
            } else if (count === 1) {
                $info.text('1 Datei ausgewählt');
            } else {
                $info.text(count + ' Dateien ausgewählt');
            }
        },

        /**
         * Insert selected media into editor
         */
        insertSelectedMedia: function(editorId) {
            var self = this;
            var editor = this.editors[editorId];

            if (!editor || this.selectedMedia.length === 0) return;

            var $modal = editor.$wrapper.find('.zk-media-modal');
            var html = '';

            this.selectedMedia.forEach(function(id) {
                var $item = $modal.find('.zk-media-item[data-id="' + id + '"]');
                var media = $item.data('media');

                if (media) {
                    html += self.getMediaHtml(media);
                }
            });

            // Insert into TinyMCE
            if (editor.tinymce) {
                editor.tinymce.execCommand('mceInsertContent', false, html);
            } else {
                // Fallback to textarea
                var $textarea = editor.$textarea;
                var pos = $textarea[0].selectionStart;
                var val = $textarea.val();
                $textarea.val(val.substring(0, pos) + html + val.substring(pos));
            }

            this.closeMediaModal(editor.$wrapper);
        },

        /**
         * Get HTML for media item
         */
        getMediaHtml: function(media) {
            if (media.is_image) {
                var url = media.sizes && media.sizes[this.currentSize] ? media.sizes[this.currentSize] : media.url;
                return '<img src="' + url + '" alt="' + this.escapeHtml(media.title) + '" class="alignnone size-' + this.currentSize + '" />\n';
            } else {
                return '<a href="' + media.url + '" target="_blank">' + this.escapeHtml(media.filename) + '</a>\n';
            }
        },

        /**
         * Handle file select from input
         */
        handleFileSelect: function(files, editorId) {
            var self = this;

            if (!files || files.length === 0) return;

            Array.from(files).forEach(function(file) {
                self.uploadFile(file, editorId);
            });
        },

        /**
         * Handle drop event
         */
        handleDrop: function(e, editorId) {
            var self = this;
            var files = e.dataTransfer ? e.dataTransfer.files : [];

            if (files.length === 0) return;

            Array.from(files).forEach(function(file) {
                self.uploadFile(file, editorId);
            });
        },

        /**
         * Upload file
         */
        uploadFile: function(file, editorId) {
            var self = this;
            var editor = this.editors[editorId];

            if (!editor) return;

            // Check file type
            if (zkEditor.allowedTypes.indexOf(file.type) === -1) {
                alert('Dateityp nicht erlaubt: ' + file.type);
                return;
            }

            // Check file size
            if (file.size > zkEditor.maxFileSize) {
                alert('Datei zu groß. Maximum: ' + Math.round(zkEditor.maxFileSize / 1024 / 1024) + ' MB');
                return;
            }

            var $modal = editor.$wrapper.find('.zk-media-modal');
            var $progress = $modal.find('.zk-upload-progress');
            var $placeholder = $modal.find('.zk-upload-placeholder');

            $placeholder.hide();
            $progress.show();

            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'zk_upload_media');
            formData.append('nonce', zkEditor.nonce);
            formData.append('post_id', editor.options.post_id || 0);

            $.ajax({
                url: zkEditor.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = (e.loaded / e.total) * 100;
                            $progress.find('.zk-progress-fill').css('width', percent + '%');
                            $progress.find('.zk-progress-text').text('Hochladen... ' + Math.round(percent) + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $progress.hide();
                    $placeholder.show();
                    $progress.find('.zk-progress-fill').css('width', '0');

                    if (response.success) {
                        // Insert directly into editor
                        var html = self.getMediaHtml(response.data);

                        if (editor.tinymce) {
                            editor.tinymce.execCommand('mceInsertContent', false, html);
                        }

                        self.closeMediaModal(editor.$wrapper);
                    } else {
                        alert(response.data.message || zkEditor.strings.uploadError);
                    }
                },
                error: function() {
                    $progress.hide();
                    $placeholder.show();
                    alert(zkEditor.strings.uploadError);
                }
            });
        },

        /**
         * Get file icon based on mime type
         */
        getFileIcon: function(mime) {
            if (mime.indexOf('pdf') > -1) {
                return 'dashicons-pdf';
            } else if (mime.indexOf('word') > -1 || mime.indexOf('document') > -1) {
                return 'dashicons-media-document';
            } else if (mime.indexOf('excel') > -1 || mime.indexOf('spreadsheet') > -1) {
                return 'dashicons-media-spreadsheet';
            } else if (mime.indexOf('image') > -1) {
                return 'dashicons-format-image';
            }
            return 'dashicons-media-default';
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Get editor content
         */
        getContent: function(editorId) {
            var editor = this.editors[editorId];
            if (!editor) return '';

            if (editor.tinymce) {
                return editor.tinymce.getContent();
            }
            return editor.$textarea.val();
        },

        /**
         * Set editor content
         */
        setContent: function(editorId, content) {
            var editor = this.editors[editorId];
            if (!editor) return;

            if (editor.tinymce) {
                editor.tinymce.setContent(content || '');
            }
            editor.$textarea.val(content || '');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ZKEditorManager.init();
    });

    // Expose globally for external access
    window.ZKEditorManager = ZKEditorManager;

})(jQuery);
