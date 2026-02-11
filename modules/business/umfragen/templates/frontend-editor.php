<?php
/**
 * Frontend template: Survey editor (list + edit views)
 */

if (!defined('ABSPATH')) {
    exit;
}

$editor = DGPTM_Survey_Frontend_Editor::get_instance();
$action = isset($_GET['survey_action']) ? sanitize_text_field($_GET['survey_action']) : '';
$survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;

$current_url = remove_query_arg(['survey_action', 'survey_id']);

$question_types = [
    'text'     => 'Text (einzeilig)',
    'textarea' => 'Text (mehrzeilig)',
    'number'   => 'Zahl',
    'radio'    => 'Einfachauswahl (Radio)',
    'checkbox' => 'Mehrfachauswahl (Checkbox)',
    'select'   => 'Dropdown (Select)',
    'matrix'   => 'Matrix',
    'file'     => 'Datei-Upload',
];

$status_labels = [
    'draft'  => 'Entwurf',
    'active' => 'Aktiv',
    'closed' => 'Geschlossen',
];
?>
<div class="dgptm-fe-editor">

<?php if ($action === 'edit') :
    // Edit view
    $survey = $survey_id ? $editor->get_survey($survey_id) : null;
    $questions = $survey_id ? $editor->get_questions($survey_id) : [];
?>
    <div class="dgptm-fe-topbar">
        <a href="<?php echo esc_url($current_url); ?>" class="dgptm-fe-btn dgptm-fe-btn-secondary">&larr; Zurueck zur Liste</a>
        <h2><?php echo $survey ? esc_html('Umfrage bearbeiten: ' . $survey->title) : 'Neue Umfrage'; ?></h2>
    </div>

    <!-- Survey Settings Form -->
    <div class="dgptm-fe-card">
        <h3>Umfrage-Einstellungen</h3>
        <form id="dgptm-fe-survey-form" class="dgptm-fe-form">
            <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey_id); ?>">

            <div class="dgptm-fe-field">
                <label>Titel *</label>
                <input type="text" name="title" required value="<?php echo esc_attr($survey ? $survey->title : ''); ?>">
            </div>

            <div class="dgptm-fe-field">
                <label>Slug</label>
                <input type="text" name="slug" value="<?php echo esc_attr($survey ? $survey->slug : ''); ?>" placeholder="Wird automatisch generiert">
            </div>

            <div class="dgptm-fe-field">
                <label>Beschreibung</label>
                <textarea name="description" rows="3"><?php echo esc_textarea($survey ? $survey->description : ''); ?></textarea>
            </div>

            <div class="dgptm-fe-row">
                <div class="dgptm-fe-field">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach ($status_labels as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($survey ? $survey->status : 'draft', $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dgptm-fe-field">
                    <label>Zugang</label>
                    <select name="access_mode">
                        <option value="public" <?php selected($survey ? $survey->access_mode : 'public', 'public'); ?>>Oeffentlich</option>
                        <option value="token" <?php selected($survey ? $survey->access_mode : '', 'token'); ?>>Nur mit Token</option>
                        <option value="logged_in" <?php selected($survey ? $survey->access_mode : '', 'logged_in'); ?>>Nur eingeloggt</option>
                    </select>
                </div>

                <div class="dgptm-fe-field">
                    <label>Duplikatschutz</label>
                    <select name="duplicate_check">
                        <option value="cookie_ip" <?php selected($survey ? $survey->duplicate_check : 'cookie_ip', 'cookie_ip'); ?>>Cookie + IP</option>
                        <option value="cookie" <?php selected($survey ? $survey->duplicate_check : '', 'cookie'); ?>>Nur Cookie</option>
                        <option value="ip" <?php selected($survey ? $survey->duplicate_check : '', 'ip'); ?>>Nur IP</option>
                        <option value="none" <?php selected($survey ? $survey->duplicate_check : '', 'none'); ?>>Kein Schutz</option>
                    </select>
                </div>
            </div>

            <div class="dgptm-fe-row">
                <label class="dgptm-fe-checkbox">
                    <input type="checkbox" name="show_progress" value="1" <?php checked($survey ? $survey->show_progress : 1); ?>>
                    Fortschrittsbalken anzeigen
                </label>
                <label class="dgptm-fe-checkbox">
                    <input type="checkbox" name="allow_save_resume" value="1" <?php checked($survey ? $survey->allow_save_resume : 0); ?>>
                    Zwischenspeichern erlauben
                </label>
            </div>

            <?php if ($survey && !empty($survey->survey_token)) :
                $survey_url = 'https://perfusiologie.de/umfragen?survey=' . $survey->survey_token;
            ?>
            <div class="dgptm-fe-field">
                <label>Umfrage-Link</label>
                <div class="dgptm-fe-link-row">
                    <code class="dgptm-fe-link"><?php echo esc_url($survey_url); ?></code>
                    <a href="<?php echo esc_url($survey_url); ?>" class="dgptm-fe-btn dgptm-fe-btn-small" target="_blank">Oeffnen</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($survey && $survey->results_token) : ?>
            <div class="dgptm-fe-field">
                <label>Ergebnis-Link</label>
                <div class="dgptm-fe-link-row">
                    <code class="dgptm-fe-link"><?php echo esc_url(home_url('/umfrage-ergebnisse/' . $survey->results_token)); ?></code>
                    <a href="<?php echo esc_url(home_url('/umfrage-ergebnisse/' . $survey->results_token)); ?>" class="dgptm-fe-btn dgptm-fe-btn-small" target="_blank">Oeffnen</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="dgptm-fe-actions">
                <button type="submit" class="dgptm-fe-btn dgptm-fe-btn-primary">Umfrage speichern</button>
            </div>
        </form>
    </div>

    <?php if ($survey_id) : ?>
    <!-- Question Builder -->
    <div class="dgptm-fe-card">
        <h3>Fragen <span class="dgptm-fe-question-count">(<?php echo count($questions); ?>)</span></h3>

        <div id="dgptm-fe-questions-list" class="dgptm-fe-questions-sortable">
            <?php foreach ($questions as $index => $q) :
                $choices = $q->choices ? json_decode($q->choices, true) : [];
                $validation = $q->validation_rules ? json_decode($q->validation_rules, true) : [];
                $skip = $q->skip_logic ? json_decode($q->skip_logic, true) : [];
            ?>
                <div class="dgptm-fe-question-item" data-question-id="<?php echo esc_attr($q->id); ?>">
                    <div class="dgptm-fe-question-header">
                        <span class="dgptm-fe-drag-handle">&#9776;</span>
                        <span class="dgptm-fe-question-number"><?php echo $index + 1; ?>.</span>
                        <span class="dgptm-fe-question-preview"><?php echo esc_html(wp_trim_words($q->question_text, 10)); ?></span>
                        <span class="dgptm-fe-type-badge"><?php echo esc_html($question_types[$q->question_type] ?? $q->question_type); ?></span>
                        <?php if ($q->is_required) : ?><span class="dgptm-fe-required-badge">Pflicht</span><?php endif; ?>
                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-toggle-q">&#9660;</button>
                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-q">&times;</button>
                    </div>
                    <div class="dgptm-fe-question-body" style="display: none;">
                        <div class="dgptm-fe-field">
                            <label>Fragetyp</label>
                            <select class="dgptm-fe-q-type">
                                <?php foreach ($question_types as $type => $label) : ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($q->question_type, $type); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dgptm-fe-field">
                            <label>Fragetext *</label>
                            <textarea class="dgptm-fe-q-text" rows="2"><?php echo esc_textarea($q->question_text); ?></textarea>
                        </div>
                        <div class="dgptm-fe-field">
                            <label>Hilfetext</label>
                            <input type="text" class="dgptm-fe-q-description" value="<?php echo esc_attr($q->description); ?>">
                        </div>
                        <div class="dgptm-fe-field">
                            <label>Abschnitts-Label</label>
                            <input type="text" class="dgptm-fe-q-group" value="<?php echo esc_attr($q->group_label); ?>">
                        </div>

                        <!-- Choices (radio/checkbox/select) -->
                        <div class="dgptm-fe-choices-section" <?php if (!in_array($q->question_type, ['radio', 'checkbox', 'select'])) echo 'style="display:none;"'; ?>>
                            <label>Antwortoptionen</label>
                            <div class="dgptm-fe-choices-list">
                                <?php
                                $flat_choices = is_array($choices) && !isset($choices['rows']) ? $choices : [];
                                foreach ($flat_choices as $choice) : ?>
                                    <div class="dgptm-fe-choice-item">
                                        <input type="text" class="dgptm-fe-choice-input" value="<?php echo esc_attr($choice); ?>">
                                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-choice">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-choice">+ Option</button>
                        </div>

                        <!-- Matrix -->
                        <div class="dgptm-fe-matrix-section" <?php if ($q->question_type !== 'matrix') echo 'style="display:none;"'; ?>>
                            <div class="dgptm-fe-field">
                                <label>Matrix-Zeilen</label>
                                <div class="dgptm-fe-matrix-rows-list">
                                    <?php
                                    $matrix_rows = isset($choices['rows']) ? $choices['rows'] : [];
                                    foreach ($matrix_rows as $row) : ?>
                                        <div class="dgptm-fe-choice-item">
                                            <input type="text" class="dgptm-fe-matrix-row-input" value="<?php echo esc_attr($row); ?>">
                                            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-matrix-row">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-matrix-row">+ Zeile</button>
                            </div>
                            <div class="dgptm-fe-field">
                                <label>Matrix-Spalten</label>
                                <div class="dgptm-fe-matrix-cols-list">
                                    <?php
                                    $matrix_cols = isset($choices['columns']) ? $choices['columns'] : [];
                                    foreach ($matrix_cols as $col) : ?>
                                        <div class="dgptm-fe-choice-item">
                                            <input type="text" class="dgptm-fe-matrix-col-input" value="<?php echo esc_attr($col); ?>">
                                            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-matrix-col">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-matrix-col">+ Spalte</button>
                            </div>
                        </div>

                        <!-- Number validation -->
                        <div class="dgptm-fe-number-section" <?php if ($q->question_type !== 'number') echo 'style="display:none;"'; ?>>
                            <div class="dgptm-fe-row">
                                <div class="dgptm-fe-field" style="max-width:120px;">
                                    <label>Min</label>
                                    <input type="number" class="dgptm-fe-q-min" value="<?php echo esc_attr(isset($validation['min']) ? $validation['min'] : ''); ?>">
                                </div>
                                <div class="dgptm-fe-field" style="max-width:120px;">
                                    <label>Max</label>
                                    <input type="number" class="dgptm-fe-q-max" value="<?php echo esc_attr(isset($validation['max']) ? $validation['max'] : ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Text pattern validation -->
                        <div class="dgptm-fe-text-section" <?php if ($q->question_type !== 'text') echo 'style="display:none;"'; ?>>
                            <div class="dgptm-fe-field">
                                <label>Pattern</label>
                                <select class="dgptm-fe-q-pattern">
                                    <option value="">Keins</option>
                                    <option value="email" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'email'); ?>>E-Mail</option>
                                    <option value="url" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'url'); ?>>URL</option>
                                    <option value="phone" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'phone'); ?>>Telefon</option>
                                </select>
                            </div>
                        </div>

                        <!-- Nesting -->
                        <div class="dgptm-fe-field">
                            <label>Elternfrage</label>
                            <div class="dgptm-fe-row">
                                <select class="dgptm-fe-q-parent">
                                    <option value="">-- Keine --</option>
                                    <?php foreach ($questions as $tq) :
                                        if ($tq->id === $q->id) continue;
                                    ?>
                                        <option value="<?php echo esc_attr($tq->id); ?>" <?php selected($q->parent_question_id, $tq->id); ?>>
                                            <?php echo esc_html($tq->sort_order . '. ' . wp_trim_words($tq->question_text, 5)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="dgptm-fe-q-parent-value" value="<?php echo esc_attr($q->parent_answer_value); ?>" placeholder="Sichtbar wenn Antwort =" style="max-width: 150px;">
                            </div>
                        </div>

                        <!-- Skip logic -->
                        <div class="dgptm-fe-field">
                            <label>Skip-Logic</label>
                            <div class="dgptm-fe-skip-rules">
                                <?php foreach ($skip as $rule) : ?>
                                    <div class="dgptm-fe-skip-rule">
                                        Wenn = <input type="text" class="dgptm-fe-skip-value" value="<?php echo esc_attr($rule['if_value'] ?? ''); ?>" style="width:100px;">
                                        &rarr; Frage
                                        <select class="dgptm-fe-skip-goto">
                                            <option value="">--</option>
                                            <?php foreach ($questions as $tq) : ?>
                                                <option value="<?php echo esc_attr($tq->id); ?>" <?php selected(isset($rule['goto_question_id']) ? $rule['goto_question_id'] : 0, $tq->id); ?>>
                                                    <?php echo esc_html($tq->sort_order . '. ' . wp_trim_words($tq->question_text, 5)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-skip">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-skip">+ Skip-Regel</button>
                        </div>

                        <label class="dgptm-fe-checkbox">
                            <input type="checkbox" class="dgptm-fe-q-required" <?php checked($q->is_required); ?>>
                            Pflichtfeld
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="dgptm-fe-add-bar">
            <select id="dgptm-fe-new-type">
                <?php foreach ($question_types as $type => $label) : ?>
                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-secondary" id="dgptm-fe-add-question">+ Frage hinzufuegen</button>
        </div>

        <div class="dgptm-fe-actions" style="margin-top: 16px;">
            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary" id="dgptm-fe-save-questions">Alle Fragen speichern</button>
        </div>
    </div>

    <!-- Question Template for JS -->
    <script type="text/html" id="tmpl-dgptm-fe-question">
        <div class="dgptm-fe-question-item" data-question-id="0">
            <div class="dgptm-fe-question-header">
                <span class="dgptm-fe-drag-handle">&#9776;</span>
                <span class="dgptm-fe-question-number">{{number}}.</span>
                <span class="dgptm-fe-question-preview">Neue Frage</span>
                <span class="dgptm-fe-type-badge">{{typeLabel}}</span>
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-toggle-q">&#9660;</button>
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-q">&times;</button>
            </div>
            <div class="dgptm-fe-question-body">
                <div class="dgptm-fe-field">
                    <label>Fragetyp</label>
                    <select class="dgptm-fe-q-type">
                        <?php foreach ($question_types as $type => $label) : ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dgptm-fe-field">
                    <label>Fragetext *</label>
                    <textarea class="dgptm-fe-q-text" rows="2"></textarea>
                </div>
                <div class="dgptm-fe-field">
                    <label>Hilfetext</label>
                    <input type="text" class="dgptm-fe-q-description" value="">
                </div>
                <div class="dgptm-fe-field">
                    <label>Abschnitts-Label</label>
                    <input type="text" class="dgptm-fe-q-group" value="">
                </div>
                <div class="dgptm-fe-choices-section" style="display:none;">
                    <label>Antwortoptionen</label>
                    <div class="dgptm-fe-choices-list"></div>
                    <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-choice">+ Option</button>
                </div>
                <div class="dgptm-fe-matrix-section" style="display:none;">
                    <div class="dgptm-fe-field">
                        <label>Matrix-Zeilen</label>
                        <div class="dgptm-fe-matrix-rows-list"></div>
                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-matrix-row">+ Zeile</button>
                    </div>
                    <div class="dgptm-fe-field">
                        <label>Matrix-Spalten</label>
                        <div class="dgptm-fe-matrix-cols-list"></div>
                        <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-matrix-col">+ Spalte</button>
                    </div>
                </div>
                <div class="dgptm-fe-number-section" style="display:none;">
                    <div class="dgptm-fe-row">
                        <div class="dgptm-fe-field" style="max-width:120px;">
                            <label>Min</label>
                            <input type="number" class="dgptm-fe-q-min" value="">
                        </div>
                        <div class="dgptm-fe-field" style="max-width:120px;">
                            <label>Max</label>
                            <input type="number" class="dgptm-fe-q-max" value="">
                        </div>
                    </div>
                </div>
                <div class="dgptm-fe-text-section" style="display:none;">
                    <div class="dgptm-fe-field">
                        <label>Pattern</label>
                        <select class="dgptm-fe-q-pattern">
                            <option value="">Keins</option>
                            <option value="email">E-Mail</option>
                            <option value="url">URL</option>
                            <option value="phone">Telefon</option>
                        </select>
                    </div>
                </div>
                <div class="dgptm-fe-field">
                    <label>Elternfrage</label>
                    <div class="dgptm-fe-row">
                        <select class="dgptm-fe-q-parent"><option value="">-- Keine --</option></select>
                        <input type="text" class="dgptm-fe-q-parent-value" value="" placeholder="Sichtbar wenn Antwort =" style="max-width: 150px;">
                    </div>
                </div>
                <div class="dgptm-fe-field">
                    <label>Skip-Logic</label>
                    <div class="dgptm-fe-skip-rules"></div>
                    <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-add-skip">+ Skip-Regel</button>
                </div>
                <label class="dgptm-fe-checkbox">
                    <input type="checkbox" class="dgptm-fe-q-required">
                    Pflichtfeld
                </label>
            </div>
        </div>
    </script>
    <?php endif; ?>

<?php else : ?>
    <!-- List view -->
    <div class="dgptm-fe-topbar">
        <h2>Umfragen verwalten</h2>
        <a href="<?php echo esc_url(add_query_arg('survey_action', 'edit', $current_url)); ?>" class="dgptm-fe-btn dgptm-fe-btn-primary">+ Neue Umfrage</a>
    </div>

    <?php
    $surveys = $editor->get_user_surveys();
    if (empty($surveys)) :
    ?>
        <div class="dgptm-fe-empty">
            <p>Keine Umfragen vorhanden.</p>
        </div>
    <?php else : ?>
        <div class="dgptm-fe-survey-grid">
            <?php foreach ($surveys as $s) :
                $edit_link = add_query_arg(['survey_action' => 'edit', 'survey_id' => $s->id], $current_url);
                $results_link = !empty($s->results_token) ? home_url('/umfrage-ergebnisse/' . $s->results_token) : '';
                $survey_link = !empty($s->survey_token) ? 'https://perfusiologie.de/umfragen?survey=' . $s->survey_token : '';
                $status_class = 'dgptm-fe-status-' . $s->status;
            ?>
                <div class="dgptm-fe-survey-card">
                    <div class="dgptm-fe-card-header">
                        <h3><a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($s->title); ?></a></h3>
                        <span class="dgptm-fe-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_labels[$s->status] ?? $s->status); ?></span>
                    </div>
                    <?php if ($s->description) : ?>
                        <p class="dgptm-fe-card-desc"><?php echo esc_html(wp_trim_words($s->description, 20)); ?></p>
                    <?php endif; ?>
                    <div class="dgptm-fe-card-meta">
                        <span><?php echo esc_html($s->response_count); ?> Antworten</span>
                        <span><?php echo esc_html(wp_date('d.m.Y', strtotime($s->created_at))); ?></span>
                    </div>
                    <div class="dgptm-fe-card-actions">
                        <a href="<?php echo esc_url($edit_link); ?>" class="dgptm-fe-btn dgptm-fe-btn-small">Bearbeiten</a>
                        <?php if ($survey_link) : ?>
                            <a href="<?php echo esc_url($survey_link); ?>" class="dgptm-fe-btn dgptm-fe-btn-small" target="_blank">zur Umfrage</a>
                        <?php endif; ?>
                        <?php if ($results_link) : ?>
                            <a href="<?php echo esc_url($results_link); ?>" class="dgptm-fe-btn dgptm-fe-btn-small" target="_blank">Ergebnis</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>
