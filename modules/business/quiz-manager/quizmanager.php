<?php
/**
 * Plugin Name: Quiz Manager – Komplett (Version 3.0.0)
 * Description: Verwalten, veröffentlichen, bearbeiten und löschen von Quizzes sowie deren Fragen, Antworten und Kategorien in einem geschützten Mitgliederbereich.
 *              Zusätzlich: Öffentliche Anzeige der Quiz-Kategorien in einem responsiven Accordion (zweispaltig, zentrierte Kategorienamen) mit modaler Quizanzeige,
 *              sowie Anzeige der bestandenen Quizze (0,5 Punkte pro Quiz, maximal 6 Punkte pro Jahr). Über einen Button ("Bescheinigung ausdrucken")
 *              wird der im Benutzerprofil gespeicherte Wert "zoho_id" an einen Zoho‑CRM‑Webhook gesendet.
 *              Modernes Design mit CSS-Variablen, responsive Grid-Layout und verbesserte Benutzerfreundlichkeit.
 * Version: 3.0.0
 * Author: Sebastian Melzer
 * Text Domain: quiz-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direkter Aufruf nicht erlaubt.
}

/* =======================================================================
   Grundlegende Berechtigungsprüfung
======================================================================== */
function quiz_manager_user_can_manage() {
    $user_id = get_current_user_id();
    return get_user_meta( $user_id, 'quizz_verwalten', true ) === '1';
}

/* =======================================================================
   Management-Ansicht Shortcode: [quiz_manager]
   ======================================================================= */
function quiz_manager_display() {
    if ( ! quiz_manager_user_can_manage() ) {
        return '<p>Sie haben keine Berechtigung, Quizzes zu verwalten.</p>';
    }
    
    global $wpdb;
    $quiz_table     = $wpdb->prefix . 'aysquiz_quizes';
    $category_table = $wpdb->prefix . 'aysquiz_quizcategories';
    
    // Alle Quizzes und Kategorien abrufen
    $quizzes = $wpdb->get_results( "SELECT * FROM $quiz_table" );
    $categories = $wpdb->get_results( "SELECT * FROM $category_table" );
    
    if ( ! $quizzes ) {
        return '<p>Keine Quizzes gefunden.</p>';
    }
    
    ob_start();
    ?>
    <div class="quiz-manager-container">
        <h3>Quiz Manager</h3>
        <!-- Neue Kategorie anlegen -->
        <div class="quiz-category-section">
            <label for="new-category">Neue Kategorie anlegen</label>
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <div style="flex: 1; max-width: 400px;">
                    <input type="text" id="new-category" placeholder="z.B. Kardiologie, Pädiatrie, etc.">
                </div>
                <button id="add-category">Kategorie hinzufügen</button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="quiz-manager-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>Kategorie (Anzeige &amp; Schnellzuordnung)</th>
                    <th>Veröffentlichungsdatum</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $quizzes as $quiz ) : 
                    // Falls create_date gesetzt ist, nur den Datumsteil (YYYY-MM-DD) extrahieren:
                    $create_date = ( !empty($quiz->create_date) ) ? date('Y-m-d', strtotime($quiz->create_date)) : '';
                ?>
                <tr class="quiz-row" data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">
                    <td><?php echo esc_html( $quiz->id ); ?></td>
                    <td><span class="quiz-title-text"><?php echo esc_html( $quiz->title ); ?></span></td>
                    <td>
                        <?php
                        $cat_title = '';
                        if ( ! empty( $categories ) ) {
                            foreach ( $categories as $cat ) {
                                if ( $cat->id == $quiz->quiz_category_id ) {
                                    $cat_title = $cat->title;
                                    break;
                                }
                            }
                        }
                        echo esc_html( $cat_title );
                        ?>
                        <br>
                        <!-- Dropdown zur Schnellzuordnung -->
                        <select class="quiz-category" data-id="<?php echo esc_attr( $quiz->id ); ?>">
                            <?php if ( ! empty( $categories ) ) : ?>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $quiz->quiz_category_id, $category->id ); ?>>
                                        <?php echo esc_html( $category->title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">Keine Kategorien verfügbar</option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td>
                        <!-- Veröffentlichungsdatum (create_date) per HTML5-Datumsauswahl -->
                        <input type="date" class="quiz-create-date" data-id="<?php echo esc_attr( $quiz->id ); ?>" value="<?php echo esc_attr( $create_date ); ?>">
                    </td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" class="quiz-toggle" data-id="<?php echo esc_attr( $quiz->id ); ?>" <?php checked( $quiz->published, 1 ); ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                    <td>
                        <button class="edit-quiz" data-id="<?php echo esc_attr( $quiz->id ); ?>">Bearbeiten</button>
                        <button class="delete-quiz" data-id="<?php echo esc_attr( $quiz->id ); ?>">Löschen</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Verstecktes Template für das Inline-Bearbeitungsformular inkl. Fragen und Antworten -->
    <div id="quiz-edit-template" style="display:none;">
        <form id="quiz-edit-form">
            <input type="hidden" name="quiz_id" id="quiz-edit-id">
            
            <label for="quiz-edit-title">Titel:</label>
            <input type="text" name="title" id="quiz-edit-title">
            
            <label for="quiz-edit-description">Beschreibung:</label>
            <textarea name="description" id="quiz-edit-description"></textarea>
            
            <label for="quiz-edit-category">Kategorie:</label>
            <select name="quiz_category_id" id="quiz-edit-category">
                <?php if ( ! empty( $categories ) ) : ?>
                    <?php foreach ( $categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category->id ); ?>">
                            <?php echo esc_html( $category->title ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value="">Keine Kategorien verfügbar</option>
                <?php endif; ?>
            </select>
            
            <label for="quiz-edit-ordering">Reihenfolge:</label>
            <input type="number" name="ordering" id="quiz-edit-ordering">
            
            <label for="quiz-edit-url">Quiz URL:</label>
            <input type="text" name="quiz_url" id="quiz-edit-url">
            
            <label for="quiz-edit-create-date">Veröffentlichungsdatum:</label>
            <!-- Feldname und -ID auf "create_date" gesetzt -->
            <input type="date" name="create_date" id="quiz-edit-create-date">
            
            <label for="quiz-edit-published">Veröffentlicht:</label>
            <input type="checkbox" name="published" id="quiz-edit-published">
            
            <h4>Fragen bearbeiten</h4>
            <!-- Container für Fragen inkl. Antworten -->
            <div id="quiz-edit-questions-container"></div>
            
            <button type="submit">Speichern</button>
            <button type="button" id="quiz-edit-cancel">Abbrechen</button>
        </form>
    </div>
    
    <!-- Inline JavaScript: Verwaltung (Kategorie hinzufügen, Schnellzuordnung, Inline-Edit inkl. Fragen & Antworten) -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Neue Kategorie per AJAX hinzufügen
        $('#add-category').click(function(e) {
            e.preventDefault();
            var catTitle = $('#new-category').val().trim();
            if ( catTitle === '' ) {
                alert('Bitte geben Sie einen Kategorienamen ein.');
                return;
            }
            $.ajax({
                url: quizManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'quiz_manager_add_category',
                    nonce: quizManager.nonce,
                    category_title: catTitle
                },
                success: function(response) {
                    if ( response.success ) {
                        alert('Kategorie "' + response.data.title + '" wurde hinzugefügt.');
                        $('#quiz-edit-category, .quiz-category').each(function() {
                            $(this).append($('<option>', {value: response.data.id, text: response.data.title}));
                        });
                        $('#new-category').val('');
                    } else {
                        alert('Fehler: ' + (response.data.message || 'undefined'));
                    }
                }
            });
        });
        
        // Schnellzuordnung: Beim Ändern der Kategorie im Dropdown
        $('.quiz-category').on('change', function(){
            var quizId = $(this).data('id');
            var newCat = $(this).val();
            $.ajax({
               url: quizManager.ajaxUrl,
               method: 'POST',
               data: {
                   action: 'update_quiz_details',
                   nonce: quizManager.nonce,
                   quiz_id: quizId,
                   title: $('tr.quiz-row[data-quiz-id="'+quizId+'"] .quiz-title-text').text(),
                   description: '',
                   quiz_category_id: newCat,
                   ordering: 0,
                   quiz_url: '',
                   published: 1
               },
               success: function(resp2){
                   if(resp2.success){
                       alert('Kategorie erfolgreich aktualisiert.');
                       var selectText = $('tr.quiz-row[data-quiz-id="'+quizId+'"] .quiz-category option:selected').text();
                       $('tr.quiz-row[data-quiz-id="'+quizId+'"] td:nth-child(3)').html(selectText + '<br/>' + $('tr.quiz-row[data-quiz-id="'+quizId+'"] .quiz-category').prop('outerHTML'));
                   } else {
                       alert('Fehler: ' + (resp2.data.message || 'undefined'));
                   }
               }
            });
        });
        
        // Veröffentlichungsdatum-Änderung: Beim Ändern des Datums in der Übersicht
        $('.quiz-create-date').on('change', function(){
            var quizId = $(this).data('id');
            var newDate = $(this).val(); // Format: YYYY-MM-DD
            $.ajax({
               url: quizManager.ajaxUrl,
               method: 'POST',
               data: {
                   action: 'update_quiz_create_date',
                   nonce: quizManager.nonce,
                   quiz_id: quizId,
                   create_date: newDate
               },
               success: function(resp) {
                   if(resp.success){
                       alert('Veröffentlichungsdatum erfolgreich aktualisiert.');
                   } else {
                       alert('Fehler: ' + (resp.data.message || 'undefined'));
                   }
               }
            });
        });
        
        // Klick auf "Bearbeiten" – Inline-Bearbeitungsformular einblenden
        $('.edit-quiz').click(function(e) {
            e.preventDefault();
            var quizId = $(this).data('id');
            var currentRow = $(this).closest('tr');
            $('#quiz-edit-row').remove();
            $.ajax({
                url: quizManager.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'get_quiz_details',
                    nonce: quizManager.nonce,
                    id: quizId
                },
                success: function(response) {
                    if ( response.success ) {
                        var data = response.data;
                        var formHtml = $('#quiz-edit-template').html();
                        // Da die Tabelle jetzt 6 Spalten hat, passt colspan entsprechend
                        var editRow = $('<tr id="quiz-edit-row"><td colspan="6">' + formHtml + '</td></tr>');
                        currentRow.after(editRow);
                        var form = editRow.find('#quiz-edit-form');
                        form.find('#quiz-edit-id').val(data.id);
                        form.find('#quiz-edit-title').val(data.title);
                        form.find('#quiz-edit-description').val(data.description);
                        form.find('#quiz-edit-category').val(data.quiz_category_id);
                        form.find('#quiz-edit-ordering').val(data.ordering);
                        form.find('#quiz-edit-url').val(data.quiz_url);
                        // Falls create_date vorhanden ist, extrahiere den Datumsteil (YYYY-MM-DD)
                        var createDate = (data.create_date) ? data.create_date.substr(0,10) : '';
                        form.find('#quiz-edit-create-date').val(createDate);
                        form.find('#quiz-edit-published').prop('checked', data.published == 1);
                        
                        // Lade zugehörige Fragen und Antworten
                        $.ajax({
                            url: quizManager.ajaxUrl,
                            method: 'GET',
                            data: {
                                action: 'get_quiz_questions',
                                nonce: quizManager.nonce,
                                id: quizId
                            },
                            success: function(resp) {
                                if(resp.success) {
                                    var questions = resp.data;
                                    var html = '';
                                    if(questions && questions.length > 0){
                                        html += '<table class="quiz-questions-table" style="width:100%; border-collapse:collapse;" border="1"><thead><tr><th>ID</th><th>Frage</th><th>Fragetitel</th><th>Antworten</th></tr></thead><tbody>';
                                        $.each(questions, function(i, q){
                                            html += '<tr data-question-id="'+q.id+'">';
                                            html += '<td>'+ q.id +'</td>';
                                            html += '<td><input type="text" name="question['+q.id+'][question]" value="'+ q.question +'" style="width:90%;"></td>';
                                            html += '<td><input type="text" name="question['+q.id+'][question_title]" value="'+ q.question_title +'" style="width:90%;"></td>';
                                            html += '<td><button type="button" class="toggle-answers" data-question-id="'+q.id+'">Antworten bearbeiten</button></td>';
                                            html += '</tr>';
                                            // Versteckte Zeile für Antworten
                                            html += '<tr class="answers-row" data-question-id="'+q.id+'" style="display:none;"><td colspan="4">';
                                            html += '<div class="answers-container" data-question-id="'+q.id+'"></div>';
                                            html += '</td></tr>';
                                        });
                                        html += '</tbody></table>';
                                    } else {
                                        html = '<p>Keine Fragen gefunden.</p>';
                                    }
                                    form.find('#quiz-edit-questions-container').html(html);
                                }
                            }
                        });
                        
                    } else {
                        alert('Fehler: ' + (response.data.message || 'undefined'));
                    }
                }
            });
        });
        
        // Toggle Antworten für eine Frage
        $(document).on('click', '.toggle-answers', function(){
            var qid = $(this).data('question-id');
            var answersRow = $('tr.answers-row[data-question-id="'+ qid +'"]');
            if(answersRow.is(':visible')){
                answersRow.slideUp();
            } else {
                var container = answersRow.find('.answers-container');
                if(container.html().trim() === ''){
                    $.ajax({
                        url: quizManager.ajaxUrl,
                        method: 'GET',
                        data: {
                            action: 'get_question_answers',
                            nonce: quizManager.nonce,
                            question_id: qid
                        },
                        success: function(resp) {
                            if(resp.success){
                                var answers = resp.data;
                                var ahtml = '';
                                if(answers && answers.length > 0){
                                    ahtml += '<table class="answers-table" style="width:100%; border-collapse:collapse;" border="1"><thead><tr><th>ID</th><th>Antwort</th><th>Korrekt</th></tr></thead><tbody>';
                                    $.each(answers, function(i, a){
                                        ahtml += '<tr class="answer-row">';
                                        ahtml += '<td><input type="hidden" class="answer-id" value="'+ a.id +'">'+ a.id +'</td>';
                                        ahtml += '<td><input type="text" class="answer-text" name="answer['+ a.id +'][answer]" value="'+ a.answer +'" style="width:90%;"></td>';
                                        ahtml += '<td><input type="checkbox" class="answer-correct" name="answer['+ a.id +'][correct]" '+ (a.correct==1?'checked':'') +'></td>';
                                        ahtml += '</tr>';
                                    });
                                    ahtml += '</tbody></table>';
                                } else {
                                    ahtml = '<p>Keine Antworten gefunden.</p>';
                                }
                                ahtml += '<button type="button" class="add-new-answer" data-question-id="'+ qid +'">Neue Antwort hinzufügen</button>';
                                container.html(ahtml);
                            }
                        }
                    });
                }
                answersRow.slideDown();
            }
        });
        
        // Neue Antwort hinzufügen in Antworten-Container
        $(document).on('click', '.add-new-answer', function(){
            var qid = $(this).data('question-id');
            var container = $('.answers-container[data-question-id="'+ qid +'"]');
            var newRow = '<tr class="answer-row">';
            newRow += '<td><input type="hidden" class="answer-id" value="0">Neu</td>';
            newRow += '<td><input type="text" class="answer-text" name="answer[new_'+ Date.now() +'][answer]" value="" style="width:90%;"></td>';
            newRow += '<td><input type="checkbox" class="answer-correct" name="answer[new_'+ Date.now() +'][correct]"></td>';
            newRow += '</tr>';
            if(container.find('table.answers-table').length > 0){
                container.find('table.answers-table tbody').append(newRow);
            } else {
                var table = '<table class="answers-table" style="width:100%; border-collapse:collapse;" border="1"><thead><tr><th>ID</th><th>Antwort</th><th>Korrekt</th></tr></thead><tbody>' + newRow + '</tbody></table>';
                container.prepend(table);
            }
        });
        
        // Abbrechen des Inline-Edit-Formulars
        $(document).on('click', '#quiz-edit-cancel', function(e) {
            e.preventDefault();
            $('#quiz-edit-row').fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Absenden des Inline-Edit-Formulars: Quiz-Daten, dann Fragen und Antworten aktualisieren
        $(document).on('submit', '#quiz-edit-form', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var quizId = $('#quiz-edit-id').val();
            // Update Quiz-Daten
            $.ajax({
                url: quizManager.ajaxUrl,
                method: 'POST',
                data: formData + '&action=update_quiz_details&nonce=' + quizManager.nonce,
                success: function(response) {
                    if ( response.success ) {
                        // Update Quiz-Fragen
                        $.ajax({
                            url: quizManager.ajaxUrl,
                            method: 'POST',
                            data: formData + '&action=update_quiz_questions&nonce=' + quizManager.nonce,
                            success: function(resp2) {
                                if(resp2.success){
                                    // Für jede Frage: Antworten updaten
                                    $('.answers-container').each(function(){
                                        var container = $(this);
                                        var qid = container.data('question-id');
                                        var answerArray = [];
                                        container.find('tr.answer-row').each(function(){
                                            var ansId = $(this).find('.answer-id').val();
                                            var ansText = $(this).find('.answer-text').val();
                                            var correct = $(this).find('.answer-correct').is(':checked') ? 1 : 0;
                                            answerArray.push({id: ansId, answer: ansText, correct: correct});
                                        });
                                        if(answerArray.length > 0){
                                            $.ajax({
                                                url: quizManager.ajaxUrl,
                                                method: 'POST',
                                                data: {
                                                    action: 'update_question_answers',
                                                    nonce: quizManager.nonce,
                                                    question_id: qid,
                                                    answers_data: JSON.stringify(answerArray)
                                                },
                                                success: function(resp3){
                                                    // Optional: Feedback pro Frage
                                                }
                                            });
                                        }
                                    });
                                    alert('Quiz, Fragen und Antworten erfolgreich aktualisiert.');
                                    var updatedTitle = $('#quiz-edit-title').val();
                                    var updatedCatText = $('#quiz-edit-category option:selected').text();
                                    var row = $('tr.quiz-row[data-quiz-id="'+quizId+'"]');
                                    row.find('.quiz-title-text').text(updatedTitle);
                                    row.find('td:nth-child(3)').html(
                                        updatedCatText + '<br/>' + row.find('.quiz-category').prop('outerHTML')
                                    );
                                    // Aktualisiere auch das Veröffentlichungsdatum in der Übersicht
                                    var updatedCreateDate = $('#quiz-edit-create-date').val();
                                    row.find('td:nth-child(4)').html(
                                        '<input type="date" class="quiz-create-date" data-id="'+quizId+'" value="'+updatedCreateDate+'">'
                                    );
                                    $('#quiz-edit-row').fadeOut(function() {
                                        $(this).remove();
                                    });
                                } else {
                                    alert('Fragen-Update Fehler: ' + (resp2.data.message || 'undefined'));
                                }
                            }
                        });
                    } else {
                        alert('Fehler: ' + (response.data.message || 'undefined'));
                    }
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'quiz_manager', 'quiz_manager_display' );


/* =======================================================================
   AJAX-Handler zum Aktualisieren des Veröffentlichungsdatums (create_date)
   ======================================================================= */
function quiz_manager_update_quiz_create_date() {
    // Sicherheitsprüfung mittels Nonce – stelle sicher, dass der Nonce-Name hier mit Deinem JavaScript übereinstimmt
    check_ajax_referer('quiz_manager_nonce', 'nonce');

    // Prüfe, ob der Nutzer berechtigt ist
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error(array('message' => 'Keine Berechtigung.'));
    }

    // Quiz-ID und create_date aus der AJAX-Anfrage holen
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    $create_date = isset($_POST['create_date']) ? sanitize_text_field($_POST['create_date']) : '';

    if ( !$quiz_id || empty($create_date) ) {
        wp_send_json_error(array('message' => 'Ungültige Daten.'));
    }

    // Das vom Datumselement übermittelte Format ist YYYY-MM-DD.
    // Für das Datenbankfeld vom Typ datetime ergänzen wir "00:00:00"
    $datetime = $create_date . ' 00:00:00';

    global $wpdb;
    $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
    $result = $wpdb->update(
        $quiz_table,
        array('create_date' => $datetime), // Daten, die aktualisiert werden sollen
        array('id' => $quiz_id),           // WHERE-Klausel
        array('%s'),                       // Format für create_date (String)
        array('%d')                        // Format für quiz_id (Integer)
    );

    if ( $result !== false ) {
        wp_send_json_success(array('message' => 'Datum erfolgreich aktualisiert.'));
    } else {
        wp_send_json_error(array('message' => 'Datenbank-Update fehlgeschlagen.'));
    }
}
add_action('wp_ajax_update_quiz_create_date', 'quiz_manager_update_quiz_create_date');

/* =======================================================================
   Öffentlicher Shortcode: [dgptm-quiz-categories]
======================================================================== */
function dgptm_quiz_categories_display() {
    global $wpdb;
    
    // Abfrage des neusten veröffentlichten Quiz
    $newest_quiz = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}aysquiz_quizes WHERE published = 1 ORDER BY create_date DESC LIMIT 1" );
    
    ob_start();
    ?>
    <!-- Teaser-Bereich für das neuste Quiz -->
    <div class="newest-quiz-teaser">
        <h2 style="margin-top: 0; color: var(--e-global-color-accent, #bd1722);">Neuestes Quiz</h2>
        <?php
        if ( $newest_quiz ) {
            // Hier wird das neuste Quiz per Shortcode eingebunden.
            echo do_shortcode( "[ays_quiz id='" . esc_attr( $newest_quiz->id ) . "']" );
        } else {
            echo "<p>Kein Quiz gefunden.</p>";
        }
        ?>
    </div>
    
    <!-- Abfrage der veröffentlichten Kategorien -->
    <?php 
    $categories = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}aysquiz_quizcategories WHERE published = 1 ORDER BY title ASC" );
    
    if ( ! $categories ) {
        echo '<p>Keine Quizkategorien gefunden.</p>';
        return ob_get_clean();
    }
    ?>
    
    <div class="ays-quiz-categories-container">
        <?php 
        $hasAtLeastOne = false;
        foreach ( $categories as $category ) :
            // Hole alle veröffentlichten Quizze dieser Kategorie, sortiert nach create_date DESC
            $quizzes = $wpdb->get_results( 
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}aysquiz_quizes WHERE quiz_category_id = %d AND published = 1 ORDER BY create_date DESC",
                    $category->id
                )
            );
            if ( empty( $quizzes ) ) {
                continue;
            }
            $hasAtLeastOne = true;
        ?>
            <div class="ays-quiz-category">
                <h2 class="ays-category-header" data-category="<?php echo esc_attr( $category->id ); ?>">
                    <?php echo esc_html( $category->title ); ?>
                </h2>
                <div class="ays-category-quizzes">
                    <?php foreach ( $quizzes as $quiz ) : ?>
                        <div class="ays-quiz-item" data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">
                            <?php 
                            // Ausgabe des Quiz-Titels gefolgt von einem Aufzählungspunkt und dem Veröffentlichungsmonat sowie Jahr
                            echo esc_html( $quiz->title ); 
                            echo ' &bull; ' . esc_html( date_i18n( 'F Y', strtotime( $quiz->create_date ) ) );
                            ?>
                        </div>
                        <!-- Modal -->
                        <div id="ays-modal-<?php echo esc_attr( $quiz->id ); ?>" class="ays-modal">
                            <div class="ays-modal-content">
                                <span class="ays-modal-close">&times;</span>
                                <?php echo do_shortcode( "[ays_quiz id='" . esc_attr( $quiz->id ) . "']" ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach;
        if ( ! $hasAtLeastOne ) {
            echo '<p>Keine Quizkategorien gefunden.</p>';
        }
        ?>
    </div>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.ays-category-header').on('click', function() {
                $(this).next('.ays-category-quizzes').slideToggle();
            });
            $('.ays-quiz-item').on('click', function() {
                var quizId = $(this).data('quiz-id');
                $('#ays-modal-' + quizId).fadeIn();
            });
            $('.ays-modal-close').on('click', function() {
                $(this).closest('.ays-modal').fadeOut();
            });
            $('.ays-modal').on('click', function(e) {
                if ($(e.target).hasClass('ays-modal')) {
                    $(this).fadeOut();
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'dgptm-quiz-categories', 'dgptm_quiz_categories_display' );

/* =======================================================================
   Shortcode: [passed_quizzes]
   Zeigt für den aktuell angemeldeten Benutzer die Liste der bestandenen Quizze (aktuelles Jahr) an,
   vergibt 0,5 Punkte pro Quiz (max. 6 Punkte) und bietet einen Button "Bescheinigung ausdrucken",
   der den im Benutzerprofil gespeicherten Wert "zoho_id" an einen Zoho‑CRM‑Webhook sendet.
======================================================================== */
function remove_duplicate_quizzes($results) {
    global $wpdb;
    $unique = array();
    $seen = array();

    foreach ($results as $row) {
        $year = date('Y', strtotime($row->end_date));

        $title = strtolower($row->quiz_title);
        $title = preg_replace('/teil\s+ii\b/i', 'teil 2', $title);
        $title = preg_replace('/teil\s+i\b/i', 'teil 1', $title);
        $title = preg_replace('/teil\s+iv\b/i', 'teil 4', $title);
        $title = preg_replace('/teil\s+iii\b/i', 'teil 3', $title);
        $title = preg_replace('/[^a-z0-9]+/i', '', $title);

        $key = $title . '|' . $year;

        if (!isset($seen[$key])) {
            $unique[] = $row;
            $seen[$key] = $row;
        } else {
            $wpdb->delete(
                $wpdb->prefix . 'aysquiz_reports',
                array('id' => $row->id),
                array('%d')
            );
        }
    }
    return $unique;
}

// Admin-Funktion zur globalen Bereinigung aller doppelten Quizberichte
function quiz_manager_admin_cleanup_duplicates() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Keine Berechtigung.</p>';
    }
    global $wpdb;

    $results = $wpdb->get_results(
        "SELECT r.*, q.title as quiz_title, r.end_date
         FROM {$wpdb->prefix}aysquiz_reports r
         JOIN {$wpdb->prefix}aysquiz_quizes q ON r.quiz_id = q.id
         ORDER BY r.user_id, q.title, r.end_date"
    );

    $deleted = 0;
    remove_duplicate_quizzes($results);

    return '<div class="updated"><p>Doppelte Einträge wurden bereinigt.</p></div>';
}

// Admin-Menüpunkt einfügen
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Quiz-Dubletten bereinigen',
        'Quiz-Dubletten',
        'manage_options',
        'quiz-dubletten-bereinigen',
        function() {
            echo '<div class="wrap"><h1>Quiz-Dubletten bereinigen</h1>';
            echo quiz_manager_admin_cleanup_duplicates();
            echo '</div>';
        }
    );
});

function passed_quizzes_display() {
    if ( ! is_user_logged_in() ) {
        return '<p>Bitte melden Sie sich an, um Ihre bestandenen Quizze anzuzeigen.</p>';
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $current_year = date('Y');

    $reports_table = $wpdb->prefix . 'aysquiz_reports';
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, q.title as quiz_title, q.create_date as quiz_create_date
             FROM $reports_table r 
             JOIN {$wpdb->prefix}aysquiz_quizes q ON r.quiz_id = q.id 
             WHERE r.user_id = %d AND YEAR(r.end_date) = %d 
             ORDER BY r.end_date DESC",
            $user_id, $current_year
        )
    );

    if ( empty( $results ) ) {
        return '<p>Sie haben in diesem Jahr noch keine Quiz bestanden.</p>';
    }

    $results = remove_duplicate_quizzes($results);

    $points = min( count($results) * 0.5, 6 );

    ob_start();
    ?>
    <div class="passed-quizzes-container">
         <h3>Bestandene Quizze (<?php echo esc_html( $current_year ); ?>)</h3>
         <table class="passed-quizzes-table">
            <thead>
              <tr>
                <th>Quiz Titel</th>
                <th>Datum</th>
                <th>Punkte</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $results as $row ) : ?>
              <tr>
                <td><?php echo esc_html( $row->quiz_title ); ?></td>
                <td><?php echo esc_html( date("d.m.Y", strtotime($row->end_date)) ); ?></td>
                <td>0,5</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
         </table>
         <p><strong>Insgesamt: <?php echo esc_html( $points ); ?> EBCP Punkte</strong></p>
         <button id="send-zoho-btn">Bescheinigung ausdrucken</button>
    </div>
    <script type="text/javascript">
       jQuery(document).ready(function($) {
         $('#send-zoho-btn').click(function(e) {
            e.preventDefault();
            $.ajax({
               url: quizManager.ajaxUrl,
               method: 'POST',
               data: {
                  action: 'send_zoho_data',
                  nonce: quizManager.nonce
               },
               success: function(response) {
                  if ( response.success ) {
                     alert(response.data.message);
                  } else {
                     alert('Fehler: ' + (response.data.message || 'undefined'));
                  }
               }
            });
         });
       });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('passed_quizzes', 'passed_quizzes_display');
/* =======================================================================
   AJAX Handler: Sende Zoho-Daten (für "Bescheinigung ausdrucken")
======================================================================== */
function quiz_manager_send_zoho_data() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
         wp_send_json_error( array( 'message' => 'Nicht angemeldet.' ) );
    }
    $user_id = get_current_user_id();
    $zoho_id = get_user_meta( $user_id, 'zoho_id', true );
    if ( empty( $zoho_id ) ) {
         wp_send_json_error( array( 'message' => 'Keine Zoho-ID gefunden.' ) );
    }
    $data = array( 'zoho_id' => $zoho_id );
    // Bitte hier die URL Deines Zoho CRM Webhooks eintragen:
    $webhook_url = 'https://your-zoho-crm-webhook-url.com';
    $response = wp_remote_post( $webhook_url, array(
       'body'    => json_encode( $data ),
       'headers' => array( 'Content-Type' => 'application/json' ),
       'timeout' => 15
    ));
    if ( is_wp_error( $response ) ) {
         wp_send_json_error( array( 'message' => 'Fehler beim Senden an Zoho.' ) );
    }
    wp_send_json_success( array( 'message' => 'Daten erfolgreich an Zoho gesendet.' ) );
}
add_action( 'wp_ajax_send_zoho_data', 'quiz_manager_send_zoho_data' );

/* =======================================================================
   Weitere AJAX Handler (Quiz-Daten, Fragen, Antworten, Kategorien)
======================================================================== */
function quiz_manager_get_quiz_details() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $quiz_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
    if ( ! $quiz_id ) {
        wp_send_json_error( array( 'message' => 'Ungültige Quiz-ID' ) );
    }
    global $wpdb;
    $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
    $quiz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $quiz_table WHERE id = %d", $quiz_id ) );
    if ( ! $quiz ) {
        wp_send_json_error( array( 'message' => 'Quiz nicht gefunden' ) );
    }
    $data = array(
        'id'               => $quiz->id,
        'title'            => $quiz->title,
        'description'      => $quiz->description,
        'quiz_category_id' => $quiz->quiz_category_id,
        'ordering'         => $quiz->ordering,
        'quiz_url'         => $quiz->quiz_url,
        'published'        => $quiz->published,
        'question_ids'     => $quiz->question_ids,
    );
    wp_send_json_success( $data );
}
add_action( 'wp_ajax_get_quiz_details', 'quiz_manager_get_quiz_details' );

function quiz_manager_update_quiz_details() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $quiz_id = isset( $_REQUEST['quiz_id'] ) ? intval( $_REQUEST['quiz_id'] ) : 0;
    if ( ! $quiz_id ) {
        wp_send_json_error( array( 'message' => 'Ungültige Quiz-ID' ) );
    }
    $title            = isset( $_REQUEST['title'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) : '';
    $description      = isset( $_REQUEST['description'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['description'] ) ) : '';
    $quiz_category_id = isset( $_REQUEST['quiz_category_id'] ) ? intval( $_REQUEST['quiz_category_id'] ) : 0;
    $ordering         = isset( $_REQUEST['ordering'] ) ? intval( $_REQUEST['ordering'] ) : 0;
    $quiz_url         = isset( $_REQUEST['quiz_url'] ) ? esc_url_raw( wp_unslash( $_REQUEST['quiz_url'] ) ) : '';
    $published        = isset( $_REQUEST['published'] ) ? intval( $_REQUEST['published'] ) : 0;
    if ( empty( $title ) ) {
        wp_send_json_error( array( 'message' => 'Titel ist erforderlich' ) );
    }
    global $wpdb;
    $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
    $result = $wpdb->update( $quiz_table, array(
        'title'             => $title,
        'description'       => $description,
        'quiz_category_id'  => $quiz_category_id,
        'ordering'          => $ordering,
        'quiz_url'          => $quiz_url,
        'published'         => $published,
    ), array( 'id' => $quiz_id ), array( '%s', '%s', '%d', '%d', '%s', '%d' ), array( '%d' ) );
    if ( false === $result ) {
        wp_send_json_error( array( 'message' => 'Fehler beim Aktualisieren des Quiz' ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_update_quiz_details', 'quiz_manager_update_quiz_details' );

function quiz_manager_get_quiz_questions() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $quiz_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
    if ( ! $quiz_id ) {
        wp_send_json_error( array( 'message' => 'Ungültige Quiz-ID' ) );
    }
    global $wpdb;
    $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
    $quiz = $wpdb->get_row( $wpdb->prepare( "SELECT question_ids FROM $quiz_table WHERE id = %d", $quiz_id ) );
    if ( ! $quiz ) {
        wp_send_json_error( array( 'message' => 'Quiz nicht gefunden' ) );
    }
    $question_ids = array_filter( array_map( 'trim', explode( ',', $quiz->question_ids ) ) );
    if ( empty( $question_ids ) ) {
        wp_send_json_success( array() );
    }
    $placeholders = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
    $query = "SELECT * FROM " . $wpdb->prefix . "aysquiz_questions WHERE id IN ($placeholders)";
    $query = $wpdb->prepare( $query, $question_ids );
    $questions = $wpdb->get_results( $query );
    wp_send_json_success( $questions );
}
add_action( 'wp_ajax_get_quiz_questions', 'quiz_manager_get_quiz_questions' );

function quiz_manager_update_quiz_questions() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    if ( ! isset( $_REQUEST['question'] ) || ! is_array( $_REQUEST['question'] ) ) {
        wp_send_json_success();
    }
    global $wpdb;
    $table = $wpdb->prefix . 'aysquiz_questions';
    $errors = array();
    foreach ( $_REQUEST['question'] as $qid => $qdata ) {
        $qid = intval( $qid );
        $question = isset( $qdata['question'] ) ? sanitize_text_field( $qdata['question'] ) : '';
        $question_title = isset( $qdata['question_title'] ) ? sanitize_text_field( $qdata['question_title'] ) : '';
        $result = $wpdb->update( $table, array(
            'question' => $question,
            'question_title' => $question_title
        ), array( 'id' => $qid ), array( '%s', '%s' ), array( '%d' ) );
        if ( false === $result ) {
            $errors[] = "Frage ID $qid konnte nicht aktualisiert werden.";
        }
    }
    if ( ! empty( $errors ) ) {
         wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_update_quiz_questions', 'quiz_manager_update_quiz_questions' );

function quiz_manager_get_question_answers() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $question_id = isset( $_REQUEST['question_id'] ) ? intval( $_REQUEST['question_id'] ) : 0;
    if ( ! $question_id ) {
        wp_send_json_error( array( 'message' => 'Ungültige Frage-ID' ) );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'aysquiz_answers';
    $answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE question_id = %d ORDER BY id", $question_id ) );
    wp_send_json_success( $answers );
}
add_action( 'wp_ajax_get_question_answers', 'quiz_manager_get_question_answers' );

function quiz_manager_update_question_answers() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
         wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
    if ( ! $question_id ) {
         wp_send_json_error( array( 'message' => 'Ungültige Frage-ID' ) );
    }
    $answers_json = isset( $_POST['answers_data'] ) ? wp_unslash( $_POST['answers_data'] ) : '';
    if ( empty( $answers_json ) ) {
         wp_send_json_error( array( 'message' => 'Keine Antwort-Daten übermittelt' ) );
    }
    $answers = json_decode( $answers_json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
         wp_send_json_error( array( 'message' => 'Ungültiges JSON-Format für Antworten' ) );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'aysquiz_answers';
    $errors = array();
    foreach ( $answers as $answer ) {
         $data = array();
         $format = array();
         $data['answer'] = sanitize_text_field( $answer['answer'] );
         $format[] = '%s';
         $data['correct'] = ( isset( $answer['correct'] ) && intval( $answer['correct'] ) ) ? 1 : 0;
         $format[] = '%d';
         if ( isset( $answer['id'] ) && intval( $answer['id'] ) > 0 ) {
             $answer_id = intval( $answer['id'] );
             $result = $wpdb->update( $table, $data, array( 'id' => $answer_id ), $format, array( '%d' ) );
             if ( false === $result ) {
                  $errors[] = "Antwort ID $answer_id konnte nicht aktualisiert werden.";
             }
         } else {
             $data['question_id'] = $question_id;
             $format[] = '%d';
             $result = $wpdb->insert( $table, $data, $format );
             if ( false === $result ) {
                  $errors[] = "Neue Antwort für Frage $question_id konnte nicht eingefügt werden.";
             }
         }
    }
    if ( ! empty( $errors ) ) {
         wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_update_question_answers', 'quiz_manager_update_question_answers' );

function quiz_manager_toggle_status() {
    check_ajax_referer( 'quiz_manager_nonce', 'nonce' );
    if ( ! quiz_manager_user_can_manage() ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
    }
    $quiz_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
    $status  = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
    if ( ! $quiz_id || ( 'publish' !== $status && 'draft' !== $status ) ) {
        wp_send_json_error( array( 'message' => 'Ungültige Parameter' ) );
    }
    global $wpdb;
    $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
    $published  = ( 'publish' === $status ) ? 1 : 0;
    $result     = $wpdb->update( $quiz_table, array( 'published' => $published ), array( 'id' => $quiz_id ), array( '%d' ), array( '%d' ) );
    if ( false === $result ) {
        wp_send_json_error( array( 'message' => 'Fehler beim Aktualisieren des Status' ) );
    }
    wp_send_json_success();
}
add_action( 'wp_ajax_toggle_quiz_status', 'quiz_manager_toggle_status' );

/* =======================================================================
   Skripte und Styles einbinden
======================================================================== */
function quiz_manager_enqueue_scripts() {
    // WICHTIG: Nicht im Elementor-Editor laden - verursacht JavaScript-Konflikte!
    if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
        return;
    }

    // Nicht im Elementor-Preview laden
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
        return;
    }

    wp_enqueue_script( 'quiz-manager-script', plugin_dir_url( __FILE__ ) . 'js/quiz-manager.js', array( 'jquery' ), '2.4.5', true );
    wp_localize_script( 'quiz-manager-script', 'quizManager', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'quiz_manager_nonce' ),
    ));
    wp_enqueue_style( 'quiz-manager-style', plugin_dir_url( __FILE__ ) . 'css/quiz-manager.css' );
}
add_action( 'wp_enqueue_scripts', 'quiz_manager_enqueue_scripts' );
?>
