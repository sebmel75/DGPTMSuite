jQuery(document).ready(function($) {
    // Modal öffnen
    $('.dgptm-open-modal-btn').on('click', function() {
        var modalId = $(this).data('modal-id');
        $('#' + modalId).fadeIn();
    });

    // Modal schließen (X klicken)
    $('.dgptm-close-modal').on('click', function() {
        $(this).closest('.dgptm-modal').fadeOut();
    });

    // Klick außerhalb des Modalinhalts schließt ebenfalls
    $('.dgptm-modal').on('click', function(e) {
        if ($(e.target).hasClass('dgptm-modal')) {
            $(this).fadeOut();
        }
    });

    // Upload-Button
    $('#dgptm-upload-btn').on('click', function() {
        var year       = $('#dgptm-year-input').val();
        var fileInput  = $('#dgptm-file-input')[0];
        var target_url = $(this).data('upload-url');
        var responseEl = $('#dgptm-upload-response');
        
        // Kurze Prüfungen
        if (!year) {
            responseEl.html('<span style="color:red;">Bitte das Jahr eingeben!</span>');
            return;
        }
        if (!fileInput.files || !fileInput.files[0]) {
            responseEl.html('<span style="color:red;">Bitte eine Datei auswählen!</span>');
            return;
        }

        // FormData aufbauen
        var formData = new FormData();
        formData.append('action', 'student_certificate_upload');
        formData.append('year', year);
        formData.append('target_url', target_url);
        formData.append('certificate_file', fileInput.files[0]);

        $.ajax({
            url: dgptmUpload.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if (res.success) {
                    // Erfolgsmeldung anzeigen
                    responseEl.html('<span style="color:green;">' + res.data.message + '</span>');
                    
                    // <-- NEU: Seite nach 10 Sekunden neu laden
                    setTimeout(function() {
                        location.reload();
                    }, 10000); 
                    
                } else {
                    // Fehlermeldung anzeigen
                    responseEl.html('<span style="color:red;">' + res.data.message + '</span>');
                }
            },
            error: function() {
                responseEl.html('<span style="color:red;">Fehler beim AJAX-Request!</span>');
            }
        });
    });
});
