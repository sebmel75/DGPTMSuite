/**
 * DGPTM Vimeo Webinare - Admin Script
 * Version: 1.3.0
 */

jQuery(document).ready(function($) {
    // Media Uploader für Bilder
    var mediaUploader;

    $(document).on('click', '.vw-upload-image', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');

        mediaUploader = wp.media({
            title: 'Bild auswählen',
            button: { text: 'Bild verwenden' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.id);
            $('#' + targetId + '_preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
        });

        mediaUploader.open();
    });

    $(document).on('click', '.vw-remove-image', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        $('#' + targetId + '_preview').html('');
    });
});
