<?php
/**
 * Frontend template: Survey form
 *
 * Variables available: $survey, $questions, $resume_data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Group questions by group_label
$groups = [];
$current_group = '';
foreach ($questions as $q) {
    $label = $q->group_label ?: '';
    if ($label !== $current_group) {
        $current_group = $label;
    }
    $groups[$current_group][] = $q;
}
$group_keys = array_keys($groups);
$total_groups = count($group_keys);

// Pre-fill data from resume
$prefill = [];
$response_id = 0;
if ($resume_data) {
    $prefill = $resume_data['answers'];
    $response_id = $resume_data['response_id'];
}
?>
<div class="dgptm-survey-container" data-survey-id="<?php echo esc_attr($survey->id); ?>" data-response-id="<?php echo esc_attr($response_id); ?>">

    <?php if ($survey->status === 'draft') : ?>
        <div class="dgptm-survey-draft-notice">
            <strong>Vorschau:</strong> Diese Umfrage ist noch nicht veroeffentlicht.
        </div>
    <?php endif; ?>

    <div class="dgptm-survey-header">
        <h2 class="dgptm-survey-title"><?php echo esc_html($survey->title); ?></h2>
        <?php if ($survey->description) : ?>
            <p class="dgptm-survey-description"><?php echo esc_html($survey->description); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($survey->show_progress && $total_groups > 1) : ?>
        <div class="dgptm-progress-bar">
            <div class="dgptm-progress-fill" style="width: <?php echo esc_attr(round(100 / $total_groups)); ?>%"></div>
        </div>
        <div class="dgptm-progress-text">
            Abschnitt <span class="dgptm-current-section">1</span> von <?php echo esc_html($total_groups); ?>
        </div>
    <?php endif; ?>

    <form id="dgptm-survey-form-<?php echo esc_attr($survey->id); ?>" class="dgptm-survey-form" enctype="multipart/form-data">
        <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey->id); ?>">
        <input type="hidden" name="response_id" value="<?php echo esc_attr($response_id); ?>">

        <?php
        $group_index = 0;
        foreach ($groups as $group_label => $group_questions) :
            $is_first = ($group_index === 0);
        ?>
            <div class="dgptm-survey-section" data-section="<?php echo esc_attr($group_index); ?>" <?php if (!$is_first) echo 'style="display:none;"'; ?>>

                <?php if ($group_label) : ?>
                    <h3 class="dgptm-section-title"><?php echo esc_html($group_label); ?></h3>
                <?php endif; ?>

                <?php foreach ($group_questions as $q) :
                    $choices = $q->choices ? json_decode($q->choices, true) : [];
                    $validation = $q->validation_rules ? json_decode($q->validation_rules, true) : [];
                    $skip = $q->skip_logic ? json_decode($q->skip_logic, true) : [];
                    $field_name = 'answers[' . $q->id . ']';
                    $prefill_val = isset($prefill[$q->id]) ? $prefill[$q->id] : '';
                    $prefill_decoded = $prefill_val;
                    if ($prefill_val && in_array($q->question_type, ['checkbox', 'matrix'], true)) {
                        $decoded = json_decode($prefill_val, true);
                        if (is_array($decoded)) {
                            $prefill_decoded = $decoded;
                        }
                    }
                ?>
                    <div class="dgptm-question<?php if (!empty($q->parent_question_id)) echo ' dgptm-question-nested'; ?>"
                         data-question-id="<?php echo esc_attr($q->id); ?>"
                         data-question-type="<?php echo esc_attr($q->question_type); ?>"
                         data-required="<?php echo esc_attr($q->is_required); ?>"
                         <?php if ($skip) : ?>data-skip-logic="<?php echo esc_attr(wp_json_encode($skip)); ?>"<?php endif; ?>
                         <?php if (!empty($q->parent_question_id)) : ?>data-parent-id="<?php echo esc_attr($q->parent_question_id); ?>" data-parent-value="<?php echo esc_attr($q->parent_answer_value); ?>"<?php endif; ?>>

                        <label class="dgptm-question-label">
                            <?php echo esc_html($q->question_text); ?>
                            <?php if ($q->is_required) : ?>
                                <span class="dgptm-required">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($q->description) : ?>
                            <p class="dgptm-question-help"><?php echo esc_html($q->description); ?></p>
                        <?php endif; ?>

                        <div class="dgptm-question-input">
                            <?php
                            switch ($q->question_type) :
                                case 'text':
                                    $input_type = 'text';
                                    if (isset($validation['pattern'])) {
                                        if ($validation['pattern'] === 'email') $input_type = 'email';
                                        elseif ($validation['pattern'] === 'url') $input_type = 'url';
                                        elseif ($validation['pattern'] === 'phone') $input_type = 'tel';
                                    }
                                    ?>
                                    <input type="<?php echo esc_attr($input_type); ?>"
                                           name="<?php echo esc_attr($field_name); ?>"
                                           class="dgptm-input dgptm-input-text"
                                           value="<?php echo esc_attr($prefill_val); ?>"
                                           <?php if ($q->is_required) echo 'required'; ?>>
                                    <?php break;

                                case 'textarea':
                                    ?>
                                    <textarea name="<?php echo esc_attr($field_name); ?>"
                                              class="dgptm-input dgptm-input-textarea"
                                              rows="4"
                                              <?php if ($q->is_required) echo 'required'; ?>><?php echo esc_textarea($prefill_val); ?></textarea>
                                    <?php break;

                                case 'number':
                                    ?>
                                    <input type="number"
                                           name="<?php echo esc_attr($field_name); ?>"
                                           class="dgptm-input dgptm-input-number"
                                           value="<?php echo esc_attr($prefill_val); ?>"
                                           <?php if (isset($validation['min'])) echo 'min="' . esc_attr($validation['min']) . '"'; ?>
                                           <?php if (isset($validation['max'])) echo 'max="' . esc_attr($validation['max']) . '"'; ?>
                                           <?php if ($q->is_required) echo 'required'; ?>>
                                    <?php break;

                                case 'radio':
                                    if (is_array($choices)) :
                                        foreach ($choices as $choice) : ?>
                                            <label class="dgptm-radio-label">
                                                <input type="radio"
                                                       name="<?php echo esc_attr($field_name); ?>"
                                                       value="<?php echo esc_attr($choice); ?>"
                                                       <?php checked($prefill_val, $choice); ?>
                                                       <?php if ($q->is_required) echo 'required'; ?>>
                                                <span><?php echo esc_html($choice); ?></span>
                                            </label>
                                        <?php endforeach;
                                    endif;
                                    break;

                                case 'checkbox':
                                    $checked_vals = is_array($prefill_decoded) ? $prefill_decoded : [];
                                    $exclusive_opt = isset($validation['exclusive_option']) ? $validation['exclusive_option'] : '';
                                    if (is_array($choices)) :
                                        foreach ($choices as $choice) : ?>
                                            <label class="dgptm-checkbox-label">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr($field_name); ?>[]"
                                                       value="<?php echo esc_attr($choice); ?>"
                                                       <?php checked(in_array($choice, $checked_vals, true)); ?>>
                                                <span><?php echo esc_html($choice); ?></span>
                                            </label>
                                        <?php endforeach;
                                    endif;
                                    if ($exclusive_opt) : ?>
                                        <label class="dgptm-checkbox-label dgptm-checkbox-exclusive">
                                            <input type="checkbox"
                                                   name="<?php echo esc_attr($field_name); ?>[]"
                                                   value="<?php echo esc_attr($exclusive_opt); ?>"
                                                   <?php checked(in_array($exclusive_opt, $checked_vals, true)); ?>>
                                            <span><?php echo esc_html($exclusive_opt); ?></span>
                                        </label>
                                    <?php endif;
                                    break;

                                case 'select':
                                    ?>
                                    <select name="<?php echo esc_attr($field_name); ?>"
                                            class="dgptm-input dgptm-input-select"
                                            <?php if ($q->is_required) echo 'required'; ?>>
                                        <option value="">-- Bitte waehlen --</option>
                                        <?php if (is_array($choices)) :
                                            foreach ($choices as $choice) : ?>
                                                <option value="<?php echo esc_attr($choice); ?>" <?php selected($prefill_val, $choice); ?>>
                                                    <?php echo esc_html($choice); ?>
                                                </option>
                                            <?php endforeach;
                                        endif; ?>
                                    </select>
                                    <?php break;

                                case 'matrix':
                                    $rows = isset($choices['rows']) ? $choices['rows'] : [];
                                    $cols = isset($choices['columns']) ? $choices['columns'] : [];
                                    $matrix_vals = is_array($prefill_decoded) ? $prefill_decoded : [];
                                    if ($rows && $cols) :
                                    ?>
                                        <div class="dgptm-matrix-wrapper">
                                            <table class="dgptm-matrix-table">
                                                <thead>
                                                    <tr>
                                                        <th></th>
                                                        <?php foreach ($cols as $col) : ?>
                                                            <th><?php echo esc_html($col); ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rows as $ri => $row) :
                                                        $row_key = sanitize_title($row);
                                                    ?>
                                                        <tr>
                                                            <th><?php echo esc_html($row); ?></th>
                                                            <?php foreach ($cols as $col) :
                                                                $checked = isset($matrix_vals[$row_key]) && $matrix_vals[$row_key] === $col;
                                                            ?>
                                                                <td>
                                                                    <input type="radio"
                                                                           name="<?php echo esc_attr($field_name . '[' . $row_key . ']'); ?>"
                                                                           value="<?php echo esc_attr($col); ?>"
                                                                           <?php checked($checked); ?>>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif;
                                    break;

                                case 'file':
                                    ?>
                                    <div class="dgptm-file-upload-area">
                                        <input type="file"
                                               name="<?php echo esc_attr($field_name); ?>"
                                               class="dgptm-file-input"
                                               data-question-id="<?php echo esc_attr($q->id); ?>"
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               <?php if ($q->is_required) echo 'required'; ?>>
                                        <p class="dgptm-file-help">Erlaubte Dateitypen: PDF, JPG, PNG (max. 5 MB)</p>
                                        <div class="dgptm-file-preview"></div>
                                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>_ids" class="dgptm-file-ids" value="">
                                    </div>
                                    <?php break;

                            endswitch;
                            ?>
                        </div>

                        <div class="dgptm-question-error" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php
            $group_index++;
        endforeach;
        ?>

        <div class="dgptm-survey-nav">
            <?php if ($total_groups > 1) : ?>
                <button type="button" class="dgptm-btn dgptm-btn-prev" style="display: none;">Zurueck</button>
                <button type="button" class="dgptm-btn dgptm-btn-next">Weiter</button>
            <?php endif; ?>

            <?php if ($survey->allow_save_resume) : ?>
                <button type="button" class="dgptm-btn dgptm-btn-secondary dgptm-btn-save">Zwischenspeichern</button>
            <?php endif; ?>

            <button type="submit" class="dgptm-btn dgptm-btn-primary dgptm-btn-submit" <?php if ($total_groups > 1) echo 'style="display:none;"'; ?>>
                Absenden
            </button>
        </div>
    </form>

    <div class="dgptm-survey-success" style="display: none;">
        <div class="dgptm-success-icon">&#10003;</div>
        <?php if (!empty($survey->completion_text)) : ?>
            <div class="dgptm-completion-text"><?php echo wp_kses_post($survey->completion_text); ?></div>
        <?php else : ?>
            <h3>Vielen Dank!</h3>
            <p>Ihre Antworten wurden erfolgreich uebermittelt.</p>
        <?php endif; ?>
    </div>
</div>
