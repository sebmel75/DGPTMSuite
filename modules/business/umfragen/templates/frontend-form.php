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
                    <div class="dgptm-group-card">
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
                    // Nested questions start hidden until parent answer matches
                    $is_nested = !empty($q->parent_question_id);
                    $initially_hidden = $is_nested && empty($prefill_val);
                ?>
                    <div class="dgptm-question<?php if ($is_nested) echo ' dgptm-question-nested'; ?><?php if ($initially_hidden) echo ' dgptm-question-hidden-nested'; ?>"
                         data-question-id="<?php echo esc_attr($q->id); ?>"
                         data-question-type="<?php echo esc_attr($q->question_type); ?>"
                         data-required="<?php echo esc_attr($q->is_required); ?>"
                         <?php if ($skip) : ?>data-skip-logic="<?php echo esc_attr(wp_json_encode($skip)); ?>"<?php endif; ?>
                         <?php if ($is_nested) : ?>data-parent-id="<?php echo esc_attr($q->parent_question_id); ?>" data-parent-value="<?php echo esc_attr($q->parent_answer_value); ?>"<?php endif; ?>>

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
                                    $sub_qs = isset($validation['sub_questions']) ? $validation['sub_questions'] : [];
                                    if (!empty($sub_qs)) :
                                        $prefill_subs = $prefill_val ? json_decode($prefill_val, true) : [];
                                        if (!is_array($prefill_subs)) $prefill_subs = [];
                                        foreach ($sub_qs as $sq_label) : ?>
                                            <div class="dgptm-subquestion">
                                                <label class="dgptm-subquestion-label"><?php echo esc_html($sq_label); ?></label>
                                                <input type="<?php echo esc_attr($input_type); ?>"
                                                       name="<?php echo esc_attr($field_name); ?>[<?php echo esc_attr($sq_label); ?>]"
                                                       class="dgptm-input dgptm-input-text dgptm-subq-field"
                                                       value="<?php echo esc_attr($prefill_subs[$sq_label] ?? ''); ?>">
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <input type="<?php echo esc_attr($input_type); ?>"
                                               name="<?php echo esc_attr($field_name); ?>"
                                               class="dgptm-input dgptm-input-text"
                                               value="<?php echo esc_attr($prefill_val); ?>"
                                               <?php if ($q->is_required) echo 'required'; ?>>
                                    <?php endif;
                                    break;

                                case 'textarea':
                                    $sub_qs = isset($validation['sub_questions']) ? $validation['sub_questions'] : [];
                                    if (!empty($sub_qs)) :
                                        $prefill_subs = $prefill_val ? json_decode($prefill_val, true) : [];
                                        if (!is_array($prefill_subs)) $prefill_subs = [];
                                        foreach ($sub_qs as $sq_label) : ?>
                                            <div class="dgptm-subquestion">
                                                <label class="dgptm-subquestion-label"><?php echo esc_html($sq_label); ?></label>
                                                <textarea name="<?php echo esc_attr($field_name); ?>[<?php echo esc_attr($sq_label); ?>]"
                                                          class="dgptm-input dgptm-input-textarea dgptm-subq-field"
                                                          rows="3"><?php echo esc_textarea($prefill_subs[$sq_label] ?? ''); ?></textarea>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <textarea name="<?php echo esc_attr($field_name); ?>"
                                                  class="dgptm-input dgptm-input-textarea"
                                                  rows="4"
                                                  <?php if ($q->is_required) echo 'required'; ?>><?php echo esc_textarea($prefill_val); ?></textarea>
                                    <?php endif;
                                    break;

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
                                    $cwt = isset($validation['choices_with_text']) ? $validation['choices_with_text'] : [];
                                    $pf_base = $prefill_val;
                                    $pf_text = '';
                                    if ($prefill_val && strpos($prefill_val, '|||') !== false) {
                                        list($pf_base, $pf_text) = explode('|||', $prefill_val, 2);
                                    }
                                    if (is_array($choices)) :
                                        foreach ($choices as $choice) :
                                            $has_text = in_array($choice, $cwt, true);
                                            $is_checked = ($pf_base === $choice);
                                            $ct_val = ($is_checked && $has_text) ? $pf_text : '';
                                            ?>
                                            <label class="dgptm-radio-label<?php if ($has_text) echo ' dgptm-has-text'; ?>">
                                                <input type="radio"
                                                       name="<?php echo esc_attr($field_name); ?>"
                                                       value="<?php echo esc_attr($choice); ?>"
                                                       <?php checked($is_checked); ?>
                                                       <?php if ($q->is_required) echo 'required'; ?>>
                                                <span><?php echo esc_html($choice); ?></span>
                                                <?php if ($has_text) : ?>
                                                    <input type="text" class="dgptm-choice-text-input dgptm-input-text"
                                                           value="<?php echo esc_attr($ct_val); ?>"
                                                           placeholder="Bitte angeben..."
                                                           style="<?php if (!$is_checked) echo 'display:none;'; ?>">
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach;
                                    endif;
                                    if (!empty($validation['allow_other'])) :
                                        $other_checked = ($pf_base === '__other__');
                                        $other_text = $other_checked ? $pf_text : '';
                                        ?>
                                        <label class="dgptm-radio-label dgptm-other-label">
                                            <input type="radio"
                                                   name="<?php echo esc_attr($field_name); ?>"
                                                   value="__other__"
                                                   <?php checked($other_checked); ?>
                                                   <?php if ($q->is_required) echo 'required'; ?>>
                                            <span>Sonstiges:</span>
                                            <input type="text" class="dgptm-other-text-input dgptm-input-text"
                                                   value="<?php echo esc_attr($other_text); ?>"
                                                   placeholder="Bitte angeben..."
                                                   style="<?php if (!$other_checked) echo 'display:none;'; ?>">
                                        </label>
                                    <?php endif;
                                    break;

                                case 'checkbox':
                                    $cwt = isset($validation['choices_with_text']) ? $validation['choices_with_text'] : [];
                                    $checked_vals = is_array($prefill_decoded) ? $prefill_decoded : [];
                                    // Parse prefill: extract base values and texts
                                    $checked_bases = [];
                                    $checked_texts = [];
                                    foreach ($checked_vals as $cv) {
                                        if (strpos($cv, '|||') !== false) {
                                            list($base, $text) = explode('|||', $cv, 2);
                                            $checked_bases[] = $base;
                                            $checked_texts[$base] = $text;
                                        } else {
                                            $checked_bases[] = $cv;
                                        }
                                    }
                                    $exclusive_opt = isset($validation['exclusive_option']) ? $validation['exclusive_option'] : '';
                                    if (is_array($choices)) :
                                        foreach ($choices as $choice) :
                                            $has_text = in_array($choice, $cwt, true);
                                            $is_checked = in_array($choice, $checked_bases, true);
                                            $ct_val = ($is_checked && $has_text && isset($checked_texts[$choice])) ? $checked_texts[$choice] : '';
                                            ?>
                                            <label class="dgptm-checkbox-label<?php if ($has_text) echo ' dgptm-has-text'; ?>">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr($field_name); ?>[]"
                                                       value="<?php echo esc_attr($choice); ?>"
                                                       <?php checked($is_checked); ?>>
                                                <span><?php echo esc_html($choice); ?></span>
                                                <?php if ($has_text) : ?>
                                                    <input type="text" class="dgptm-choice-text-input dgptm-input-text"
                                                           value="<?php echo esc_attr($ct_val); ?>"
                                                           placeholder="Bitte angeben..."
                                                           style="<?php if (!$is_checked) echo 'display:none;'; ?>">
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach;
                                    endif;
                                    if ($exclusive_opt) : ?>
                                        <label class="dgptm-checkbox-label dgptm-checkbox-exclusive">
                                            <input type="checkbox"
                                                   name="<?php echo esc_attr($field_name); ?>[]"
                                                   value="<?php echo esc_attr($exclusive_opt); ?>"
                                                   <?php checked(in_array($exclusive_opt, $checked_bases, true)); ?>>
                                            <span><?php echo esc_html($exclusive_opt); ?></span>
                                        </label>
                                    <?php endif;
                                    if (!empty($validation['allow_other'])) :
                                        $other_checked = in_array('__other__', $checked_bases, true);
                                        $other_text = isset($checked_texts['__other__']) ? $checked_texts['__other__'] : '';
                                        ?>
                                        <label class="dgptm-checkbox-label dgptm-other-label">
                                            <input type="checkbox"
                                                   name="<?php echo esc_attr($field_name); ?>[]"
                                                   value="__other__"
                                                   <?php checked($other_checked); ?>>
                                            <span>Sonstiges:</span>
                                            <input type="text" class="dgptm-other-text-input dgptm-input-text"
                                                   value="<?php echo esc_attr($other_text); ?>"
                                                   placeholder="Bitte angeben..."
                                                   style="<?php if (!$other_checked) echo 'display:none;'; ?>">
                                        </label>
                                    <?php endif;
                                    break;

                                case 'select':
                                    $pf_base = $prefill_val;
                                    $pf_text = '';
                                    if ($prefill_val && strpos($prefill_val, '|||') !== false) {
                                        list($pf_base, $pf_text) = explode('|||', $prefill_val, 2);
                                    }
                                    ?>
                                    <select name="<?php echo esc_attr($field_name); ?>"
                                            class="dgptm-input dgptm-input-select"
                                            <?php if ($q->is_required) echo 'required'; ?>>
                                        <option value="">-- Bitte waehlen --</option>
                                        <?php if (is_array($choices)) :
                                            foreach ($choices as $choice) : ?>
                                                <option value="<?php echo esc_attr($choice); ?>" <?php selected($pf_base, $choice); ?>>
                                                    <?php echo esc_html($choice); ?>
                                                </option>
                                            <?php endforeach;
                                        endif;
                                        if (!empty($validation['allow_other'])) : ?>
                                            <option value="__other__" <?php selected($pf_base, '__other__'); ?>>Sonstiges...</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (!empty($validation['allow_other'])) : ?>
                                        <input type="text" class="dgptm-other-text-input dgptm-input dgptm-input-text"
                                               value="<?php echo esc_attr($pf_text); ?>"
                                               placeholder="Bitte angeben..."
                                               style="margin-top: 8px; <?php if ($pf_base !== '__other__') echo 'display:none;'; ?>">
                                    <?php endif;
                                    break;

                                case 'matrix':
                                    $rows = isset($choices['rows']) ? $choices['rows'] : [];
                                    $cols = isset($choices['columns']) ? $choices['columns'] : [];
                                    $matrix_input = isset($choices['matrix_input_type']) ? $choices['matrix_input_type'] : 'radio';
                                    $matrix_vals = is_array($prefill_decoded) ? $prefill_decoded : [];
                                    if ($rows && $cols) :
                                    ?>
                                        <div class="dgptm-matrix-wrapper" data-matrix-type="<?php echo esc_attr($matrix_input); ?>">
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
                                                            <?php if ($matrix_input === 'number') :
                                                                foreach ($cols as $col) :
                                                                    $col_key = sanitize_title($col);
                                                                    $pf_num = isset($matrix_vals[$row_key][$col_key]) ? $matrix_vals[$row_key][$col_key] : '';
                                                            ?>
                                                                <td>
                                                                    <input type="number"
                                                                           name="<?php echo esc_attr($field_name . '[' . $row_key . '][' . $col_key . ']'); ?>"
                                                                           class="dgptm-matrix-number-input"
                                                                           value="<?php echo esc_attr($pf_num); ?>">
                                                                </td>
                                                            <?php endforeach;
                                                            else :
                                                                foreach ($cols as $col) :
                                                                    $checked = isset($matrix_vals[$row_key]) && $matrix_vals[$row_key] === $col;
                                                            ?>
                                                                <td>
                                                                    <input type="radio"
                                                                           name="<?php echo esc_attr($field_name . '[' . $row_key . ']'); ?>"
                                                                           value="<?php echo esc_attr($col); ?>"
                                                                           <?php checked($checked); ?>>
                                                                </td>
                                                            <?php endforeach;
                                                            endif; ?>
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

                            // Free text field (for any question type if enabled)
                            if (!empty($validation['allow_free_text'])) :
                                $free_key = $field_name . '_free';
                                $free_prefill = isset($resume_data['answers'][$q->id . '_free']) ? $resume_data['answers'][$q->id . '_free'] : '';
                            ?>
                                <div class="dgptm-free-text" style="margin-top: 10px;">
                                    <label class="dgptm-free-text-label" style="font-size:13px;color:var(--dgptm-gray-500, #6b7280);display:block;margin-bottom:4px;">Weitere Anmerkungen (optional):</label>
                                    <textarea name="answers[<?php echo esc_attr($q->id); ?>_free]"
                                              class="dgptm-input dgptm-input-textarea dgptm-free-text-input"
                                              rows="2"
                                              placeholder="Freitext..."><?php echo esc_textarea($free_prefill); ?></textarea>
                                </div>
                            <?php endif;
                            ?>
                        </div>

                        <div class="dgptm-question-error" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>

                <?php if ($group_label) : ?>
                    </div><!-- .dgptm-group-card -->
                <?php endif; ?>

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
