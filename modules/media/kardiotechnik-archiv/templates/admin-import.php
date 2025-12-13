<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Kardiotechnik Archiv - PDFs importieren</h1>

    <div class="card">
        <h2>PDF-Verzeichnis importieren</h2>
        <p>Geben Sie den vollständigen Pfad zum Verzeichnis ein, das die Kardiotechnik-PDFs enthält.</p>

        <form id="kta-import-form">
            <table class="form-table">
                <tr>
                    <th><label for="pdf-directory">PDF-Verzeichnis</label></th>
                    <td>
                        <input type="text" id="pdf-directory" name="pdf_directory" class="large-text"
                               value="<?php echo esc_attr(dirname(__FILE__) . '/..'); ?>" />
                        <p class="description">
                            Beispiel: C:\Users\...\Archiv Kardiotechnik ab 1975
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary" id="kta-import-btn">
                    Import starten
                </button>
            </p>
        </form>

        <div id="kta-import-progress" style="display:none;">
            <h3>Import läuft...</h3>
            <div class="kta-progress-bar">
                <div class="kta-progress-fill"></div>
            </div>
            <p id="kta-import-status"></p>
        </div>

        <div id="kta-import-result" style="display:none;">
            <h3>Import abgeschlossen</h3>
            <p id="kta-import-message"></p>
        </div>
    </div>

    <div class="card">
        <h2>Einzelnen Artikel hinzufügen</h2>
        <form method="post" enctype="multipart/form-data" id="kta-manual-add-form">
            <?php wp_nonce_field('kta_manual_add'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="manual-year">Jahr *</label></th>
                    <td><input type="number" name="year" id="manual-year" min="1975" max="<?php echo date('Y'); ?>" required /></td>
                </tr>
                <tr>
                    <th><label for="manual-issue">Ausgabe *</label></th>
                    <td>
                        <input type="text" name="issue" id="manual-issue" placeholder="z.B. 01, 02, S01" required />
                        <p class="description">Format: 01-04 für reguläre Ausgaben, S01 für Sonderhefte</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="manual-title">Titel *</label></th>
                    <td><input type="text" name="title" id="manual-title" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="manual-author">Autor(en)</label></th>
                    <td><input type="text" name="author" id="manual-author" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="manual-keywords">Schlagwörter</label></th>
                    <td>
                        <input type="text" name="keywords" id="manual-keywords" class="regular-text" />
                        <p class="description">Komma-getrennt</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="manual-abstract">Zusammenfassung</label></th>
                    <td><textarea name="abstract" id="manual-abstract" rows="5" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="manual-page-start">Seiten</label></th>
                    <td>
                        <input type="number" name="page_start" id="manual-page-start" style="width:80px" /> bis
                        <input type="number" name="page_end" id="manual-page-end" style="width:80px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="manual-pdf">PDF-Datei *</label></th>
                    <td><input type="file" name="pdf_file" id="manual-pdf" accept=".pdf" required /></td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="manual_add_article" class="button button-primary">
                    Artikel hinzufügen
                </button>
            </p>
        </form>
    </div>

    <div class="card">
        <h2>Importanleitung</h2>
        <ol>
            <li>Die Verzeichnisse sollten nach dem Schema <code>YYYY-MM Kardiotechnik</code> benannt sein</li>
            <li>Jedes Verzeichnis sollte eine PDF-Datei enthalten</li>
            <li>Bereits importierte PDFs werden beim erneuten Import übersprungen</li>
            <li>Nach dem Import können Sie die Artikeldetails (Titel, Autor, etc.) bearbeiten</li>
        </ol>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#kta-import-form').on('submit', function(e) {
        e.preventDefault();

        var pdfDirectory = $('#pdf-directory').val();

        if (!pdfDirectory) {
            alert('Bitte geben Sie ein Verzeichnis an.');
            return;
        }

        $('#kta-import-btn').prop('disabled', true);
        $('#kta-import-progress').show();
        $('#kta-import-result').hide();
        $('#kta-import-status').text('Import wird vorbereitet...');

        $.post(ajaxurl, {
            action: 'kta_import_pdfs',
            pdf_directory: pdfDirectory,
            nonce: ktaAdmin.nonce
        }, function(response) {
            $('#kta-import-progress').hide();
            $('#kta-import-result').show();
            $('#kta-import-btn').prop('disabled', false);

            if (response.success) {
                $('#kta-import-message').html(
                    '<strong>' + response.data.imported + '</strong> Artikel wurden erfolgreich importiert.'
                );
            } else {
                $('#kta-import-message').html(
                    '<span style="color:red">Fehler: ' + response.data + '</span>'
                );
            }
        });
    });

    // Manuelles Hinzufügen
    $('#kta-manual-add-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'kta_manual_add_article');
        formData.append('nonce', ktaAdmin.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Artikel wurde erfolgreich hinzugefügt!');
                    $('#kta-manual-add-form')[0].reset();
                } else {
                    alert('Fehler: ' + response.data);
                }
            }
        });
    });
});
</script>
