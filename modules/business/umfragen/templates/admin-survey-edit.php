<?php
/**
 * Admin template: Survey editor with question builder
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = DGPTM_Survey_Admin::get_instance();
$survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;
$survey = $survey_id ? $admin->get_survey($survey_id) : null;
$questions = $survey_id ? $admin->get_questions($survey_id) : [];

$list_url = admin_url('admin.php?page=dgptm-umfragen');

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
?>
<div class="wrap dgptm-umfragen-wrap">
    <h1>
        <a href="<?php echo esc_url($list_url); ?>">&larr; Zurueck</a> |
        <?php echo $survey ? 'Umfrage bearbeiten' : 'Neue Umfrage'; ?>
    </h1>

    <!-- Survey Settings -->
    <div class="dgptm-survey-settings card">
        <h2>Umfrage-Einstellungen</h2>
        <form id="dgptm-survey-form">
            <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey_id); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="survey-title">Titel *</label></th>
                    <td><input type="text" id="survey-title" name="title" class="regular-text" required
                               value="<?php echo esc_attr($survey ? $survey->title : ''); ?>"></td>
                </tr>
                <tr>
                    <th><label for="survey-slug">Slug</label></th>
                    <td>
                        <input type="text" id="survey-slug" name="slug" class="regular-text"
                               value="<?php echo esc_attr($survey ? $survey->slug : ''); ?>">
                        <p class="description">Wird automatisch aus dem Titel generiert, wenn leer.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="survey-description">Beschreibung</label></th>
                    <td><textarea id="survey-description" name="description" class="large-text" rows="3"><?php echo esc_textarea($survey ? $survey->description : ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="survey-status">Status</label></th>
                    <td>
                        <select id="survey-status" name="status">
                            <option value="draft" <?php selected($survey ? $survey->status : 'draft', 'draft'); ?>>Entwurf</option>
                            <option value="active" <?php selected($survey ? $survey->status : '', 'active'); ?>>Aktiv</option>
                            <option value="closed" <?php selected($survey ? $survey->status : '', 'closed'); ?>>Geschlossen</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="survey-access">Zugang</label></th>
                    <td>
                        <select id="survey-access" name="access_mode">
                            <option value="public" <?php selected($survey ? $survey->access_mode : 'public', 'public'); ?>>Oeffentlich</option>
                            <option value="token" <?php selected($survey ? $survey->access_mode : '', 'token'); ?>>Nur mit Token</option>
                            <option value="logged_in" <?php selected($survey ? $survey->access_mode : '', 'logged_in'); ?>>Nur eingeloggt</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="survey-duplicate">Duplikatschutz</label></th>
                    <td>
                        <select id="survey-duplicate" name="duplicate_check">
                            <option value="cookie_ip" <?php selected($survey ? $survey->duplicate_check : 'cookie_ip', 'cookie_ip'); ?>>Cookie + IP</option>
                            <option value="cookie" <?php selected($survey ? $survey->duplicate_check : '', 'cookie'); ?>>Nur Cookie</option>
                            <option value="ip" <?php selected($survey ? $survey->duplicate_check : '', 'ip'); ?>>Nur IP</option>
                            <option value="none" <?php selected($survey ? $survey->duplicate_check : '', 'none'); ?>>Kein Schutz</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Optionen</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_progress" value="1" <?php checked($survey ? $survey->show_progress : 1); ?>>
                            Fortschrittsbalken anzeigen
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="allow_save_resume" value="1" <?php checked($survey ? $survey->allow_save_resume : 0); ?>>
                            Zwischenspeichern erlauben
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="survey-completion-text">Abschlusstext</label></th>
                    <td>
                        <textarea id="survey-completion-text" name="completion_text" class="large-text" rows="3" placeholder="Vielen Dank fuer Ihre Teilnahme! (HTML erlaubt)"><?php echo esc_textarea($survey ? $survey->completion_text : ''); ?></textarea>
                        <p class="description">Individueller Text nach dem Absenden. Leer = Standardtext. HTML ist erlaubt.</p>
                    </td>
                </tr>
                <tr>
                    <th>Geteilt mit</th>
                    <td>
                        <input type="hidden" name="shared_with" id="dgptm-shared-with" value="<?php echo esc_attr($survey ? $survey->shared_with : ''); ?>">
                        <div id="dgptm-shared-users-list" class="dgptm-shared-users-list">
                            <?php
                            if ($survey && !empty($survey->shared_with)) {
                                $shared_ids = array_map('absint', array_filter(explode(',', $survey->shared_with)));
                                foreach ($shared_ids as $uid) {
                                    $u = get_userdata($uid);
                                    if ($u) {
                                        echo '<span class="dgptm-shared-user" data-user-id="' . esc_attr($uid) . '">';
                                        echo esc_html($u->display_name) . ' <small>(' . esc_html($u->user_email) . ')</small>';
                                        echo ' <button type="button" class="dgptm-remove-shared-user" title="Entfernen">&times;</button>';
                                        echo '</span> ';
                                    }
                                }
                            }
                            ?>
                        </div>
                        <div class="dgptm-share-search-wrap" style="margin-top: 6px;">
                            <input type="text" id="dgptm-share-search" class="regular-text" placeholder="Name oder E-Mail eingeben..." autocomplete="off">
                            <div id="dgptm-share-results" class="dgptm-share-results" style="display:none;"></div>
                        </div>
                        <p class="description">Berechtigte Benutzer, die diese Umfrage mitbearbeiten duerfen.</p>
                    </td>
                </tr>
                <?php if ($survey && $survey->results_token) : ?>
                <tr>
                    <th>Ergebnis-Link</th>
                    <td>
                        <code id="results-link"><?php echo esc_url(home_url('/umfrage-ergebnisse/' . $survey->results_token)); ?></code>
                        <a href="<?php echo esc_url(home_url('/umfrage-ergebnisse/' . $survey->results_token)); ?>" class="button button-small" target="_blank">Oeffnen</a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($survey && !empty($survey->survey_token)) : ?>
                <tr>
                    <th>Umfrage-Link</th>
                    <td>
                        <code id="survey-link"><?php echo esc_url('https://perfusiologie.de/umfragen?survey=' . $survey->survey_token); ?></code>
                        <a href="<?php echo esc_url('https://perfusiologie.de/umfragen?survey=' . $survey->survey_token); ?>" class="button button-small" target="_blank">Oeffnen</a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Umfrage speichern</button>
            </p>
        </form>
    </div>

    <?php if ($survey_id) : ?>
    <!-- Question Builder -->
    <div class="dgptm-question-builder card">
        <h2>Fragen <span class="dgptm-question-count">(<?php echo count($questions); ?>)</span></h2>

        <div id="dgptm-questions-list" class="dgptm-questions-sortable">
            <?php foreach ($questions as $index => $q) :
                $choices = $q->choices ? json_decode($q->choices, true) : [];
                $validation = $q->validation_rules ? json_decode($q->validation_rules, true) : [];
                $skip = $q->skip_logic ? json_decode($q->skip_logic, true) : [];
            ?>
                <div class="dgptm-question-item" data-question-id="<?php echo esc_attr($q->id); ?>">
                    <div class="dgptm-question-header">
                        <span class="dgptm-drag-handle dashicons dashicons-move"></span>
                        <span class="dgptm-question-number"><?php echo $index + 1; ?>.</span>
                        <span class="dgptm-question-title-preview"><?php echo esc_html(wp_trim_words($q->question_text, 10)); ?></span>
                        <span class="dgptm-question-type-badge"><?php echo esc_html($question_types[$q->question_type] ?? $q->question_type); ?></span>
                        <?php if ($q->is_required) : ?>
                            <span class="dgptm-required-badge">Pflicht</span>
                        <?php endif; ?>
                        <button type="button" class="dgptm-toggle-question button button-small">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <button type="button" class="dgptm-remove-question button button-small">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="dgptm-question-body" style="display: none;">
                        <table class="form-table">
                            <tr>
                                <th><label>Fragetyp</label></th>
                                <td>
                                    <select class="dgptm-q-type">
                                        <?php foreach ($question_types as $type => $label) : ?>
                                            <option value="<?php echo esc_attr($type); ?>" <?php selected($q->question_type, $type); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Fragetext *</label></th>
                                <td><textarea class="dgptm-q-text large-text" rows="2"><?php echo esc_textarea($q->question_text); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>Hilfetext</label></th>
                                <td><input type="text" class="dgptm-q-description regular-text" value="<?php echo esc_attr($q->description); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>Abschnitts-Label</label></th>
                                <td><input type="text" class="dgptm-q-group regular-text" value="<?php echo esc_attr($q->group_label); ?>"></td>
                            </tr>
                            <tr class="dgptm-choices-row" <?php if (!in_array($q->question_type, ['radio', 'checkbox', 'select'])) echo 'style="display:none;"'; ?>>
                                <th><label>Antwortoptionen</label></th>
                                <td>
                                    <div class="dgptm-choices-list">
                                        <?php
                                        $flat_choices = is_array($choices) && !isset($choices['rows']) ? $choices : [];
                                        foreach ($flat_choices as $choice) : ?>
                                            <div class="dgptm-choice-item">
                                                <input type="text" class="dgptm-choice-input" value="<?php echo esc_attr($choice); ?>">
                                                <button type="button" class="button button-small dgptm-remove-choice">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-small dgptm-add-choice">+ Option</button>
                                </td>
                            </tr>
                            <tr class="dgptm-exclusive-row" <?php if ($q->question_type !== 'checkbox') echo 'style="display:none;"'; ?>>
                                <th><label>Ausschluss-Option</label></th>
                                <td>
                                    <input type="text" class="dgptm-q-exclusive regular-text" value="<?php echo esc_attr(isset($validation['exclusive_option']) ? $validation['exclusive_option'] : ''); ?>" placeholder="z.B. Nein / Keine Antwort">
                                    <p class="description">Wird als letzte Option angezeigt und schliesst alle anderen aus.</p>
                                </td>
                            </tr>
                            <tr class="dgptm-matrix-row" <?php if ($q->question_type !== 'matrix') echo 'style="display:none;"'; ?>>
                                <th><label>Matrix-Zeilen</label></th>
                                <td>
                                    <div class="dgptm-matrix-rows-list">
                                        <?php
                                        $matrix_rows = isset($choices['rows']) ? $choices['rows'] : [];
                                        foreach ($matrix_rows as $row) : ?>
                                            <div class="dgptm-matrix-item">
                                                <input type="text" class="dgptm-matrix-row-input" value="<?php echo esc_attr($row); ?>">
                                                <button type="button" class="button button-small dgptm-remove-matrix-row">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-small dgptm-add-matrix-row">+ Zeile</button>
                                </td>
                            </tr>
                            <tr class="dgptm-matrix-row" <?php if ($q->question_type !== 'matrix') echo 'style="display:none;"'; ?>>
                                <th><label>Matrix-Spalten</label></th>
                                <td>
                                    <div class="dgptm-matrix-cols-list">
                                        <?php
                                        $matrix_cols = isset($choices['columns']) ? $choices['columns'] : [];
                                        foreach ($matrix_cols as $col) : ?>
                                            <div class="dgptm-matrix-item">
                                                <input type="text" class="dgptm-matrix-col-input" value="<?php echo esc_attr($col); ?>">
                                                <button type="button" class="button button-small dgptm-remove-matrix-col">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-small dgptm-add-matrix-col">+ Spalte</button>
                                </td>
                            </tr>
                            <tr class="dgptm-number-row" <?php if ($q->question_type !== 'number') echo 'style="display:none;"'; ?>>
                                <th><label>Validierung</label></th>
                                <td>
                                    <label>Min: <input type="number" class="dgptm-q-min small-text" value="<?php echo esc_attr(isset($validation['min']) ? $validation['min'] : ''); ?>"></label>
                                    <label>Max: <input type="number" class="dgptm-q-max small-text" value="<?php echo esc_attr(isset($validation['max']) ? $validation['max'] : ''); ?>"></label>
                                </td>
                            </tr>
                            <tr class="dgptm-text-validation-row" <?php if ($q->question_type !== 'text') echo 'style="display:none;"'; ?>>
                                <th><label>Pattern</label></th>
                                <td>
                                    <select class="dgptm-q-pattern">
                                        <option value="">Keins</option>
                                        <option value="email" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'email'); ?>>E-Mail</option>
                                        <option value="url" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'url'); ?>>URL</option>
                                        <option value="phone" <?php selected(isset($validation['pattern']) ? $validation['pattern'] : '', 'phone'); ?>>Telefon</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Elternfrage</label></th>
                                <td>
                                    <select class="dgptm-q-parent">
                                        <option value="">-- Keine --</option>
                                        <?php foreach ($questions as $tq) :
                                            if ($tq->id === $q->id) continue;
                                        ?>
                                            <option value="<?php echo esc_attr($tq->id); ?>" <?php selected($q->parent_question_id, $tq->id); ?>>
                                                <?php echo esc_html($tq->sort_order . '. ' . wp_trim_words($tq->question_text, 5)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label style="margin-left: 10px;">Sichtbar wenn Antwort = <input type="text" class="dgptm-q-parent-value" value="<?php echo esc_attr($q->parent_answer_value); ?>" placeholder="z.B. Ja" style="width: 120px;"></label>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Skip-Logic</label></th>
                                <td>
                                    <div class="dgptm-skip-logic-rules">
                                        <?php foreach ($skip as $rule) : ?>
                                            <div class="dgptm-skip-rule">
                                                Wenn Antwort = <input type="text" class="dgptm-skip-value" value="<?php echo esc_attr($rule['if_value'] ?? ''); ?>">
                                                &rarr; Springe zu Frage
                                                <select class="dgptm-skip-goto">
                                                    <option value="">-- Waehlen --</option>
                                                    <?php foreach ($questions as $tq) : ?>
                                                        <option value="<?php echo esc_attr($tq->id); ?>" <?php selected(isset($rule['goto_question_id']) ? $rule['goto_question_id'] : 0, $tq->id); ?>>
                                                            <?php echo esc_html($tq->sort_order . '. ' . wp_trim_words($tq->question_text, 5)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="button button-small dgptm-remove-skip">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-small dgptm-add-skip">+ Skip-Regel</button>
                                </td>
                            </tr>
                            <tr>
                                <th>Optionen</th>
                                <td>
                                    <label>
                                        <input type="checkbox" class="dgptm-q-required" <?php checked($q->is_required); ?>>
                                        Pflichtfeld
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="dgptm-add-question-bar">
            <select id="dgptm-new-question-type">
                <?php foreach ($question_types as $type => $label) : ?>
                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="dgptm-add-question">+ Frage hinzufuegen</button>
        </div>

        <p class="submit">
            <button type="button" class="button button-primary" id="dgptm-save-questions">Alle Fragen speichern</button>
        </p>
    </div>

    <!-- Question Template (hidden, used by JS) -->
    <script type="text/html" id="tmpl-dgptm-question">
        <div class="dgptm-question-item" data-question-id="0">
            <div class="dgptm-question-header">
                <span class="dgptm-drag-handle dashicons dashicons-move"></span>
                <span class="dgptm-question-number">{{number}}.</span>
                <span class="dgptm-question-title-preview">Neue Frage</span>
                <span class="dgptm-question-type-badge">{{typeLabel}}</span>
                <button type="button" class="dgptm-toggle-question button button-small">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <button type="button" class="dgptm-remove-question button button-small">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <div class="dgptm-question-body">
                <table class="form-table">
                    <tr>
                        <th><label>Fragetyp</label></th>
                        <td>
                            <select class="dgptm-q-type">
                                <?php foreach ($question_types as $type => $label) : ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Fragetext *</label></th>
                        <td><textarea class="dgptm-q-text large-text" rows="2"></textarea></td>
                    </tr>
                    <tr>
                        <th><label>Hilfetext</label></th>
                        <td><input type="text" class="dgptm-q-description regular-text" value=""></td>
                    </tr>
                    <tr>
                        <th><label>Abschnitts-Label</label></th>
                        <td><input type="text" class="dgptm-q-group regular-text" value=""></td>
                    </tr>
                    <tr class="dgptm-choices-row" style="display:none;">
                        <th><label>Antwortoptionen</label></th>
                        <td>
                            <div class="dgptm-choices-list"></div>
                            <button type="button" class="button button-small dgptm-add-choice">+ Option</button>
                        </td>
                    </tr>
                    <tr class="dgptm-exclusive-row" style="display:none;">
                        <th><label>Ausschluss-Option</label></th>
                        <td>
                            <input type="text" class="dgptm-q-exclusive regular-text" value="" placeholder="z.B. Nein / Keine Antwort">
                            <p class="description">Wird als letzte Option angezeigt und schliesst alle anderen aus.</p>
                        </td>
                    </tr>
                    <tr class="dgptm-matrix-row" style="display:none;">
                        <th><label>Matrix-Zeilen</label></th>
                        <td>
                            <div class="dgptm-matrix-rows-list"></div>
                            <button type="button" class="button button-small dgptm-add-matrix-row">+ Zeile</button>
                        </td>
                    </tr>
                    <tr class="dgptm-matrix-row" style="display:none;">
                        <th><label>Matrix-Spalten</label></th>
                        <td>
                            <div class="dgptm-matrix-cols-list"></div>
                            <button type="button" class="button button-small dgptm-add-matrix-col">+ Spalte</button>
                        </td>
                    </tr>
                    <tr class="dgptm-number-row" style="display:none;">
                        <th><label>Validierung</label></th>
                        <td>
                            <label>Min: <input type="number" class="dgptm-q-min small-text" value=""></label>
                            <label>Max: <input type="number" class="dgptm-q-max small-text" value=""></label>
                        </td>
                    </tr>
                    <tr class="dgptm-text-validation-row" style="display:none;">
                        <th><label>Pattern</label></th>
                        <td>
                            <select class="dgptm-q-pattern">
                                <option value="">Keins</option>
                                <option value="email">E-Mail</option>
                                <option value="url">URL</option>
                                <option value="phone">Telefon</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Elternfrage</label></th>
                        <td>
                            <select class="dgptm-q-parent">
                                <option value="">-- Keine --</option>
                            </select>
                            <label style="margin-left: 10px;">Sichtbar wenn Antwort = <input type="text" class="dgptm-q-parent-value" value="" placeholder="z.B. Ja" style="width: 120px;"></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Skip-Logic</label></th>
                        <td>
                            <div class="dgptm-skip-logic-rules"></div>
                            <button type="button" class="button button-small dgptm-add-skip">+ Skip-Regel</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Optionen</th>
                        <td>
                            <label>
                                <input type="checkbox" class="dgptm-q-required">
                                Pflichtfeld
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </script>
    <?php endif; ?>
</div>
