document.addEventListener('DOMContentLoaded', function() {
    // Neue Kategorie hinzufügen
    var addCategoryBtn = document.querySelector('#add-category');
    if ( addCategoryBtn ) {
        addCategoryBtn.addEventListener('click', function() {
            var categoryName = document.querySelector('#new-category').value;
            if ( categoryName ) {
                fetch( quizManager.ajaxUrl + "?action=add_quiz_category&nonce=" + quizManager.nonce + "&name=" + encodeURIComponent( categoryName ) )
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Fehler beim Hinzufügen der Kategorie: ' + data.message);
                        }
                    });
            }
        });
    }

    // Quiz Status umschalten
    document.querySelectorAll('.quiz-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var quizId = this.getAttribute('data-id');
            var status = this.checked ? 'publish' : 'draft';
            fetch(quizManager.ajaxUrl + "?action=toggle_quiz_status&nonce=" + quizManager.nonce + "&id=" + quizId + "&status=" + status)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Fehler beim Aktualisieren des Status: ' + data.message);
                    }
                });
        });
    });

    // Quiz Titel aktualisieren (direkt im Tabellenfeld)
    document.querySelectorAll('.quiz-title').forEach(function(input) {
        input.addEventListener('change', function() {
            var quizId = this.getAttribute('data-id');
            var title = this.value;
            fetch(quizManager.ajaxUrl + "?action=update_quiz_title&nonce=" + quizManager.nonce + "&id=" + quizId + "&title=" + encodeURIComponent(title))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Fehler beim Aktualisieren des Titels: ' + data.message);
                    }
                });
        });
    });

    // Quiz löschen
    document.querySelectorAll('.delete-quiz').forEach(function(button) {
        button.addEventListener('click', function() {
            var quizId = this.getAttribute('data-id');
            if (confirm('Quiz wirklich löschen?')) {
                fetch(quizManager.ajaxUrl + "?action=trash_quiz&nonce=" + quizManager.nonce + "&id=" + quizId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Fehler beim Löschen des Quiz: ' + data.message);
                        }
                    });
            }
        });
    });

    // Quiz Kategorie aktualisieren (Dropdown in der Tabelle)
    document.querySelectorAll('.quiz-category').forEach(function(select) {
        select.addEventListener('change', function() {
            var quizId = this.getAttribute('data-id');
            var categoryId = this.value;
            fetch(quizManager.ajaxUrl + "?action=update_quiz_category&nonce=" + quizManager.nonce + "&id=" + quizId + "&category_id=" + categoryId)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Fehler beim Aktualisieren der Kategorie: ' + data.message);
                    }
                });
        });
    });

    // Bearbeiten-Button: Lädt Quiz-Daten und zeigt das Inline-Bearbeitungsformular (unten) an
    document.querySelectorAll('.edit-quiz').forEach(function(button) {
        button.addEventListener('click', function() {
            var quizId = this.getAttribute('data-id');
            console.log('Bearbeiten-Button geklickt, Quiz-ID: ' + quizId);
            fetch(quizManager.ajaxUrl + "?action=get_quiz_details&nonce=" + quizManager.nonce + "&id=" + quizId)
                .then(response => response.json())
                .then(data => {
                    console.log('Antwort von get_quiz_details:', data);
                    if (data.success) {
                        var quiz = data.data;
                        document.getElementById('quiz-edit-id').value = quiz.id;
                        document.getElementById('quiz-edit-title').value = quiz.title;
                        document.getElementById('quiz-edit-description').value = quiz.description;
                        document.getElementById('quiz-edit-category').value = quiz.quiz_category_id;
                        document.getElementById('quiz-edit-ordering').value = quiz.ordering;
                        document.getElementById('quiz-edit-url').value = quiz.quiz_url;
                        document.getElementById('quiz-edit-published').checked = (parseInt(quiz.published) === 1);
                        // Inline-Formular einblenden
                        document.getElementById('quiz-edit-form-container').style.display = 'block';
                        loadQuizQuestions(quiz.id);
                    } else {
                        alert('Fehler beim Abrufen der Quiz-Daten: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Abrufen der Quiz-Daten:', error);
                });
        });
    });
    
    // Lädt alle Fragen eines Quiz und zeigt sie in einer Tabelle an
    function loadQuizQuestions(quizId) {
        fetch(quizManager.ajaxUrl + "?action=get_quiz_questions&nonce=" + quizManager.nonce + "&id=" + quizId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var questions = data.data;
                    var container = document.getElementById('quiz-edit-questions-container');
                    if (questions.length === 0) {
                        container.innerHTML = "<p>Keine Fragen zu diesem Quiz gefunden.</p>";
                        return;
                    }
                    // Tabelle erzeugen
                    var tableHtml = '<table class="questions-table"><thead><tr><th>Frage</th><th>Aktion</th></tr></thead><tbody>';
                    questions.forEach(function(q) {
                        tableHtml += renderQuestionCompact(q);
                    });
                    tableHtml += '</tbody></table>';
                    container.innerHTML = tableHtml;
                } else {
                    alert('Fehler beim Laden der Fragen: ' + data.message);
                }
            });
    }
    
    // Generiert HTML für einen Frage-Block in Tabellenform:
    // Die erste Zeile zeigt eine kompakte Textzeile (Frage-Zusammenfassung) und einen "Bearbeiten"-Button.
    // Darunter folgt eine versteckte Detailzeile, die beim Klicken angezeigt wird.
    function renderQuestionCompact(question) {
        var summary = escapeHtml(question.question_title || question.question || 'Keine Frage');
        var detailsHtml = `
          <tr class="question-details-row" id="question-details-${question.id}" style="display: none;">
              <td colspan="2">
                  <label>Fragetitel:</label>
                  <input type="text" class="question-title" value="${escapeHtml(question.question_title || '')}">
                  <label>Frage:</label>
                  <textarea class="question-text">${escapeHtml(question.question || '')}</textarea>
                  <label>Hinweis:</label>
                  <textarea class="question-hint">${escapeHtml(question.question_hint || '')}</textarea>
                  <label>Erklärung:</label>
                  <textarea class="explanation">${escapeHtml(question.explanation || '')}</textarea>
                  <label>Veröffentlicht:</label>
                  <input type="checkbox" class="question-published" ${parseInt(question.published) === 1 ? "checked" : ""}>
                  <button type="button" class="toggle-answers" data-question-id="${question.id}">Antworten bearbeiten</button>
                  <div class="answers-container" id="answers-for-question-${question.id}" style="display: none;"></div>
              </td>
          </tr>
        `;
        return `
          <tr class="question-row" data-id="${question.id}">
              <td class="question-summary-cell">${summary}</td>
              <td class="question-action-cell">
                  <button type="button" class="edit-question-details" data-question-id="${question.id}">Bearbeiten</button>
              </td>
          </tr>
          ${detailsHtml}
        `;
    }
    
    // Beim Klick auf den "edit-question-details"-Button wird die Detailzeile umgeschaltet
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('edit-question-details')) {
            var questionId = e.target.getAttribute('data-question-id');
            var detailsRow = document.getElementById('question-details-' + questionId);
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = 'table-row';
            } else {
                detailsRow.style.display = 'none';
            }
        }
    });
    
    // Beim Klick auf "toggle-answers" wird der Antworten-Bereich getoggelt und ggf. geladen
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('toggle-answers')) {
            var questionId = e.target.getAttribute('data-question-id');
            var container = document.getElementById('answers-for-question-' + questionId);
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                if (container.innerHTML.trim() === "" || container.innerHTML.indexOf("Lade Antworten") !== -1) {
                    loadQuestionAnswers(questionId);
                }
            } else {
                container.style.display = 'none';
            }
        }
    });
    
    // Lädt die Antworten zu einer Frage und fügt sie in den entsprechenden Container ein
    function loadQuestionAnswers(questionId) {
        fetch(quizManager.ajaxUrl + "?action=get_question_answers&nonce=" + quizManager.nonce + "&question_id=" + questionId)
          .then(response => response.json())
          .then(data => {
              var container = document.getElementById('answers-for-question-' + questionId);
              if (data.success) {
                  var answers = data.data;
                  if (answers.length === 0) {
                      container.innerHTML = "<p>Keine Antworten gefunden.</p>";
                  } else {
                      var answersHtml = "";
                      answers.forEach(function(answer) {
                          answersHtml += renderAnswerForm(answer);
                      });
                      container.innerHTML = answersHtml;
                  }
                  container.insertAdjacentHTML('beforeend', `<button type="button" class="add-answer" data-question-id="${questionId}">Antwort hinzufügen</button>`);
              } else {
                  container.innerHTML = "<p>Fehler beim Laden der Antworten: " + data.message + "</p>";
              }
          });
    }
    
    // Generiert HTML für einen Antwort-Block (in einer Zeile)
    function renderAnswerForm(answer) {
        var checked = (parseInt(answer.correct) === 1) ? "checked" : "";
        return `
           <div class="quiz-answer" data-id="${answer.id}">
             <div class="answer-row">
               <label>Antwort:</label>
               <input type="text" class="answer-text" placeholder="Antwort" value="${escapeHtml(answer.answer || '')}">
               <label class="answer-toggle-label">Richtig:</label>
               <label class="switch answer-switch">
                  <input type="checkbox" class="answer-correct" ${checked}>
                  <span class="slider round"></span>
               </label>
               <button type="button" class="remove-answer">Entfernen</button>
             </div>
           </div>
        `;
    }
    
    // Hilfsfunktion zum Escapen von HTML-Zeichen
    function escapeHtml(text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Event Delegation: Antwort hinzufügen oder entfernen
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('add-answer')) {
             var questionId = e.target.getAttribute('data-question-id');
             var container = document.getElementById('answers-for-question-' + questionId);
             var newAnswerHtml = renderAnswerForm({ id: '', answer: '', correct: 0 });
             e.target.insertAdjacentHTML('beforebegin', newAnswerHtml);
        }
        if (e.target && e.target.classList.contains('remove-answer')) {
             e.target.closest('.quiz-answer').remove();
        }
    });
    
    // Formular zum Speichern von Quiz, Fragen und Antworten
    var quizEditForm = document.getElementById('quiz-edit-form');
    if (quizEditForm) {
        quizEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var quizId = document.getElementById('quiz-edit-id').value;
            var title = document.getElementById('quiz-edit-title').value;
            var description = document.getElementById('quiz-edit-description').value;
            var quiz_category_id = document.getElementById('quiz-edit-category').value;
            var ordering = document.getElementById('quiz-edit-ordering').value;
            var quiz_url = document.getElementById('quiz-edit-url').value;
            var published = document.getElementById('quiz-edit-published').checked ? 1 : 0;
        
        // Quiz-Daten aktualisieren
        var urlQuiz = quizManager.ajaxUrl + "?action=update_quiz_details&nonce=" + quizManager.nonce +
                  "&quiz_id=" + encodeURIComponent(quizId) +
                  "&title=" + encodeURIComponent(title) +
                  "&description=" + encodeURIComponent(description) +
                  "&quiz_category_id=" + encodeURIComponent(quiz_category_id) +
                  "&ordering=" + encodeURIComponent(ordering) +
                  "&quiz_url=" + encodeURIComponent(quiz_url) +
                  "&published=" + published;
        
        fetch(urlQuiz)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Für jede Frage auch die Antworten aktualisieren
                    var answerPromises = [];
                    document.querySelectorAll('.quiz-question').forEach(function(qElem) {
                        var questionId = qElem.getAttribute('data-id');
                        var answersArray = [];
                        qElem.querySelectorAll('.quiz-answer').forEach(function(aElem) {
                             var answerObj = {
                                 id: aElem.getAttribute('data-id'),
                                 answer: aElem.querySelector('.answer-text').value,
                                 correct: aElem.querySelector('.answer-correct').checked ? 1 : 0
                             };
                             answersArray.push(answerObj);
                        });
                        var formData = new FormData();
                        formData.append('action', 'update_question_answers');
                        formData.append('nonce', quizManager.nonce);
                        formData.append('question_id', questionId);
                        formData.append('answers_data', JSON.stringify(answersArray));
                        
                        answerPromises.push(
                           fetch(quizManager.ajaxUrl, {
                               method: 'POST',
                               body: formData
                           }).then(response => response.json())
                        );
                    });
                    
                    Promise.all(answerPromises).then(function(results) {
                        var allSuccessful = results.every(function(r) { return r.success; });
                        if (allSuccessful) {
                             alert('Quiz und alle Antworten erfolgreich aktualisiert');
                             location.reload();
                        } else {
                             alert('Fehler beim Aktualisieren einiger Antworten');
                        }
                    });
                } else {
                    alert('Fehler beim Aktualisieren des Quiz: ' + data.message);
                }
            });
        });
    }

    // Abbrechen-Button: Blendet das Bearbeitungsformular wieder aus
    var quizEditCancel = document.getElementById('quiz-edit-cancel');
    if (quizEditCancel) {
        quizEditCancel.addEventListener('click', function() {
            document.getElementById('quiz-edit-form-container').style.display = 'none';
        });
    }
});
