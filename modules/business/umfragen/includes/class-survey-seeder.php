<?php
/**
 * Survey Seeder - ECLS-Zentren seed data
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Survey_Seeder {

    /**
     * Seed the ECLS-Zentren survey with 15 questions
     *
     * @return int|false Survey ID on success, false if already exists or error
     */
    public static function seed_ecls_zentren() {
        global $wpdb;

        $table_surveys   = $wpdb->prefix . 'dgptm_surveys';
        $table_questions = $wpdb->prefix . 'dgptm_survey_questions';

        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_surveys WHERE slug = %s",
            'ecls-zentren'
        ));

        if ($existing) {
            return false;
        }

        $now = current_time('mysql');
        $user_id = get_current_user_id();
        $results_token = wp_generate_password(32, false);

        // Create survey
        $wpdb->insert($table_surveys, [
            'title'           => 'ECLS-Zentren Umfrage',
            'slug'            => 'ecls-zentren',
            'description'     => 'Erhebung zu ECMO/ECLS-Programmen an deutschen herzchirurgischen Zentren. Diese Umfrage dient der Bestandsaufnahme der ECLS-Versorgung in Deutschland.',
            'status'          => 'draft',
            'access_mode'     => 'public',
            'duplicate_check' => 'cookie_ip',
            'results_token'   => $results_token,
            'show_progress'   => 1,
            'allow_save_resume' => 1,
            'created_by'      => $user_id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        $survey_id = $wpdb->insert_id;
        if (!$survey_id) {
            return false;
        }

        // Define questions
        $questions = [
            [
                'sort_order'    => 1,
                'group_label'   => 'Kontaktdaten',
                'question_type' => 'text',
                'question_text' => 'Name der Klinik / des Herzzentrums',
                'description'   => null,
                'choices'       => null,
                'validation_rules' => wp_json_encode(['required' => true]),
                'skip_logic'    => null,
                'is_required'   => 1,
            ],
            [
                'sort_order'    => 2,
                'group_label'   => 'Kontaktdaten',
                'question_type' => 'text',
                'question_text' => 'Ansprechpartner (Name, Funktion)',
                'description'   => null,
                'choices'       => null,
                'validation_rules' => wp_json_encode(['required' => true]),
                'skip_logic'    => null,
                'is_required'   => 1,
            ],
            [
                'sort_order'    => 3,
                'group_label'   => 'Kontaktdaten',
                'question_type' => 'text',
                'question_text' => 'E-Mail-Adresse Ansprechpartner',
                'description'   => null,
                'choices'       => null,
                'validation_rules' => wp_json_encode(['required' => true, 'pattern' => 'email']),
                'skip_logic'    => null,
                'is_required'   => 1,
            ],
            [
                'sort_order'    => 4,
                'group_label'   => 'ECLS-Programm',
                'question_type' => 'radio',
                'question_text' => 'Fuehren Sie ein ECLS/ECMO-Programm durch?',
                'description'   => null,
                'choices'       => wp_json_encode(['Ja', 'Nein']),
                'validation_rules' => wp_json_encode(['required' => true]),
                'skip_logic'    => null, // Will be set after all questions are inserted
                'is_required'   => 1,
            ],
            [
                'sort_order'    => 5,
                'group_label'   => 'ECLS-Programm',
                'question_type' => 'select',
                'question_text' => 'Seit wann besteht das ECLS-Programm?',
                'description'   => null,
                'choices'       => wp_json_encode(['Weniger als 2 Jahre', '2-5 Jahre', '5-10 Jahre', 'Mehr als 10 Jahre']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 6,
                'group_label'   => 'ECLS-Programm',
                'question_type' => 'checkbox',
                'question_text' => 'Welche ECLS-Systeme setzen Sie ein?',
                'description'   => 'Mehrfachauswahl moeglich',
                'choices'       => wp_json_encode(['VA-ECMO', 'VV-ECMO', 'ECCO2R', 'Sonstige']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 7,
                'group_label'   => 'Fallzahlen & Team',
                'question_type' => 'number',
                'question_text' => 'Ungefaehre Fallzahl ECLS pro Jahr',
                'description'   => null,
                'choices'       => null,
                'validation_rules' => wp_json_encode(['min' => 0]),
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 8,
                'group_label'   => 'Fallzahlen & Team',
                'question_type' => 'matrix',
                'question_text' => 'Teamzusammensetzung ECLS',
                'description'   => 'Bitte geben Sie die Beteiligung der einzelnen Berufsgruppen an.',
                'choices'       => wp_json_encode([
                    'rows'    => ['Kardiotechniker/Perfusionist', 'Intensivpflege', 'Herzchirurg', 'Kardiologe', 'Anaesthesist'],
                    'columns' => ['Beteiligt', 'Rufbereitschaft', 'Nicht beteiligt'],
                ]),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 9,
                'group_label'   => 'Organisation',
                'question_type' => 'radio',
                'question_text' => '24/7 ECLS-Bereitschaft vorhanden?',
                'description'   => null,
                'choices'       => wp_json_encode(['Ja', 'Nein', 'In Planung']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 10,
                'group_label'   => 'Organisation',
                'question_type' => 'checkbox',
                'question_text' => 'ECLS-Implantation auch ausserhalb des OP?',
                'description'   => 'Mehrfachauswahl moeglich',
                'choices'       => wp_json_encode(['Herzkatheterlabor', 'Intensivstation', 'Notaufnahme', 'Extern/Transport', 'Nein']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 11,
                'group_label'   => 'Training & Protokolle',
                'question_type' => 'radio',
                'question_text' => 'Standardisiertes ECLS-Training vorhanden?',
                'description'   => null,
                'choices'       => wp_json_encode(['Ja', 'Nein', 'In Planung']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 12,
                'group_label'   => 'Training & Protokolle',
                'question_type' => 'radio',
                'question_text' => 'ECLS-Entwoehnungsprotokoll vorhanden?',
                'description'   => null,
                'choices'       => wp_json_encode(['Ja, standardisiert', 'Ja, individuell', 'Nein']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 13,
                'group_label'   => 'Register & Netzwerk',
                'question_type' => 'checkbox',
                'question_text' => 'Teilnahme an ECLS-Register oder Studie?',
                'description'   => 'Mehrfachauswahl moeglich',
                'choices'       => wp_json_encode(['ELSO Registry', 'DIVI ECMO Register', 'Eigenes Register', 'Keine Teilnahme']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 14,
                'group_label'   => 'Register & Netzwerk',
                'question_type' => 'radio',
                'question_text' => 'Interesse an einem DGPTM ECLS-Netzwerk?',
                'description'   => null,
                'choices'       => wp_json_encode(['Ja', 'Nein', 'Vielleicht']),
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
            [
                'sort_order'    => 15,
                'group_label'   => 'Register & Netzwerk',
                'question_type' => 'textarea',
                'question_text' => 'Anmerkungen / Kommentare',
                'description'   => 'Optionale Anmerkungen oder Kommentare zur Umfrage',
                'choices'       => null,
                'validation_rules' => null,
                'skip_logic'    => null,
                'is_required'   => 0,
            ],
        ];

        // Insert questions
        $question_ids = [];
        foreach ($questions as $q) {
            $wpdb->insert($table_questions, array_merge(
                ['survey_id' => $survey_id],
                $q
            ));
            $question_ids[] = $wpdb->insert_id;
        }

        // Set skip logic for Q4 ("Nein" -> jump to Q15)
        if (isset($question_ids[3]) && isset($question_ids[14])) {
            $skip = wp_json_encode([
                ['if_value' => 'Nein', 'goto_question_id' => $question_ids[14]]
            ]);
            $wpdb->update(
                $table_questions,
                ['skip_logic' => $skip],
                ['id' => $question_ids[3]]
            );
        }

        return $survey_id;
    }
}
