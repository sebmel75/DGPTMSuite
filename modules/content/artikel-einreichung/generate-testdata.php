<?php
/**
 * Testdaten-Generator für Artikel-Einreichung
 *
 * Aufruf im WordPress-Admin unter:
 * /wp-admin/admin.php?page=dgptm-artikel-settings&generate_testdata=1
 *
 * Oder via WP-CLI:
 * wp eval-file modules/content/artikel-einreichung/generate-testdata.php
 */

if (!defined('ABSPATH')) {
    // Allow CLI execution
    if (php_sapi_name() !== 'cli') {
        exit('Direct access not allowed');
    }

    // Bootstrap WordPress for CLI
    $wp_load = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        exit('WordPress not found');
    }
}

/**
 * Generate test data for Artikel-Einreichung
 */
function dgptm_generate_artikel_testdata($count = 5) {
    if (!class_exists('DGPTM_Artikel_Einreichung')) {
        return ['error' => 'Artikel-Einreichung Modul nicht aktiv'];
    }

    $plugin = DGPTM_Artikel_Einreichung::get_instance();
    $created = [];

    // Sample data
    $titles = [
        'Einfluss der Hypothermie auf die zerebrale Oxygenierung während kardiopulmonalem Bypass',
        'Vergleich verschiedener Oxygenatoren bei Langzeit-ECMO-Unterstützung',
        'Neue Strategien zur Minimierung der Hämolyse bei extrakorporaler Zirkulation',
        'Kardiotechnische Aspekte der minimalinvasiven Herzchirurgie',
        'Point-of-Care-Testing im Herzkatheterlabor: Eine Übersicht',
        'Perfusionsstrategien bei Säuglingen mit komplexen Herzfehlern',
        'Einsatz von Zentrifugalpumpen in der modernen Herzchirurgie',
        'Antikoagulation bei ECMO: Heparin vs. Argatroban',
        'Maschinelle Autotransfusion in der Herzchirurgie',
        'Qualitätsmanagement in der Kardiotechnik',
        'Biokompatible Beschichtungen für extrakorporale Systeme',
        'Metabolisches Monitoring während des kardiopulmonalen Bypasses',
        'Ultrafiltrationsstrategien zur Hämokonzentration',
        'Myokardprotektion: Aktuelle Konzepte und neue Entwicklungen',
        'Simulation in der kardiotechnischen Ausbildung'
    ];

    $abstracts_de = [
        'Hintergrund: Die zerebrale Oxygenierung während des kardiopulmonalen Bypasses ist ein kritischer Parameter. Methoden: In dieser prospektiven Studie wurden 120 Patienten untersucht. Ergebnisse: Die moderate Hypothermie zeigte signifikante Vorteile. Schlussfolgerung: Eine individualisierte Temperatursteuerung wird empfohlen.',
        'Zielsetzung: Vergleich der Leistungsfähigkeit verschiedener Oxygenatorsysteme bei prolongierter ECMO-Therapie. Material und Methoden: Retrospektive Analyse von 85 ECMO-Fällen über 3 Jahre. Resultate: Polymethylpenten-Membranen zeigten überlegene Langzeitstabilität. Fazit: Die Wahl des Oxygenators beeinflusst das Outcome signifikant.',
        'Einleitung: Hämolyse bleibt eine relevante Komplikation der extrakorporalen Zirkulation. Methodik: Evaluation verschiedener Pumpentypen und Kanülendesigns. Ergebnisse: Optimierte Flussprofile reduzierten die freie Hämoglobin-Konzentration um 40%. Diskussion: Technische Innovationen können die Bluttraumatisierung minimieren.',
        'Die minimalinvasive Herzchirurgie erfordert angepasste Perfusionskonzepte. Diese Arbeit beschreibt die technischen Anforderungen und Lösungsansätze für kardiotechnische Teams. Besondere Berücksichtigung finden die periphere Kanülierung und das angepasste Monitoring.',
        'Point-of-Care-Tests ermöglichen schnelle Therapieentscheidungen. Diese Übersichtsarbeit fasst aktuelle Evidenz zur Implementierung im Herzkatheterlabor zusammen und gibt praktische Empfehlungen für den klinischen Alltag.'
    ];

    $abstracts_en = [
        'Background: Cerebral oxygenation during cardiopulmonary bypass is a critical parameter. Methods: This prospective study examined 120 patients. Results: Moderate hypothermia showed significant advantages. Conclusion: Individualized temperature management is recommended.',
        'Objective: Comparison of different oxygenator systems during prolonged ECMO therapy. Materials and Methods: Retrospective analysis of 85 ECMO cases over 3 years. Results: Polymethylpentene membranes demonstrated superior long-term stability. Conclusion: Oxygenator selection significantly affects outcome.',
        'Introduction: Hemolysis remains a relevant complication of extracorporeal circulation. Methodology: Evaluation of different pump types and cannula designs. Results: Optimized flow profiles reduced free hemoglobin concentration by 40%. Discussion: Technical innovations can minimize blood trauma.',
        'Minimally invasive cardiac surgery requires adapted perfusion concepts. This work describes technical requirements and solutions for perfusion teams. Special consideration is given to peripheral cannulation and adapted monitoring.',
        'Point-of-care testing enables rapid therapy decisions. This review summarizes current evidence for implementation in the catheterization laboratory and provides practical recommendations for clinical practice.'
    ];

    $authors = [
        ['name' => 'Dr. med. Thomas Müller', 'email' => 'mueller@test-perfusiologie.de', 'institution' => 'Universitätsklinikum Heidelberg, Klinik für Herzchirurgie'],
        ['name' => 'Dipl.-Ing. Sarah Schmidt', 'email' => 'schmidt@test-perfusiologie.de', 'institution' => 'Herz- und Diabeteszentrum NRW, Bad Oeynhausen'],
        ['name' => 'Prof. Dr. Michael Weber', 'email' => 'weber@test-perfusiologie.de', 'institution' => 'Deutsches Herzzentrum Berlin'],
        ['name' => 'Dr. rer. nat. Anna Fischer', 'email' => 'fischer@test-perfusiologie.de', 'institution' => 'LMU München, Institut für Kardiotechnik'],
        ['name' => 'Dipl.-Kardiotechn. Peter Bauer', 'email' => 'bauer@test-perfusiologie.de', 'institution' => 'Universitätsklinikum Freiburg'],
        ['name' => 'Dr. med. Julia Hoffmann', 'email' => 'hoffmann@test-perfusiologie.de', 'institution' => 'Klinikum Stuttgart'],
        ['name' => 'M.Sc. Markus Klein', 'email' => 'klein@test-perfusiologie.de', 'institution' => 'Universitätsklinikum Essen']
    ];

    $coauthors = [
        "Dr. med. Hans Meier, Universitätsklinikum Hamburg\nDipl.-Ing. Lisa Wagner, Herzzentrum Leipzig",
        "Prof. Dr. Katrin Schulz, Charité Berlin\nDr. med. Stefan Braun, Universitätsklinikum Bonn",
        "Dipl.-Kardiotechn. Maria Richter, Klinikum Augsburg",
        "",
        "Dr. med. Frank Wolf, Universitätsklinikum Göttingen\nDr. med. Sabine Neumann, Herzzentrum Dresden\nM.Sc. Jan Hartmann, Universitätsklinikum Köln"
    ];

    $keywords_de = [
        'Kardiopulmonaler Bypass, Zerebrale Oxygenierung, Hypothermie, Neuroprotektion',
        'ECMO, Oxygenator, Membran, Langzeitunterstützung',
        'Hämolyse, Extrakorporale Zirkulation, Pumpen, Bluttrauma',
        'Minimalinvasive Chirurgie, Perfusion, Kanülierung',
        'Point-of-Care, Gerinnungsdiagnostik, Herzkatheterlabor'
    ];

    $keywords_en = [
        'Cardiopulmonary bypass, Cerebral oxygenation, Hypothermia, Neuroprotection',
        'ECMO, Oxygenator, Membrane, Long-term support',
        'Hemolysis, Extracorporeal circulation, Pumps, Blood trauma',
        'Minimally invasive surgery, Perfusion, Cannulation',
        'Point-of-care, Coagulation diagnostics, Catheterization laboratory'
    ];

    $publikationsarten = array_keys(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN);
    $statuses = [
        DGPTM_Artikel_Einreichung::STATUS_SUBMITTED,
        DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW,
        DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED,
        DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED,
        DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
        DGPTM_Artikel_Einreichung::STATUS_REJECTED
    ];

    for ($i = 0; $i < $count; $i++) {
        $title = $titles[array_rand($titles)];
        $author = $authors[array_rand($authors)];
        $abstract_idx = array_rand($abstracts_de);
        $status = $statuses[array_rand($statuses)];
        $publikationsart = $publikationsarten[array_rand($publikationsarten)];

        // Generate unique submission ID
        $year = date('Y');
        $existing = wp_count_posts(DGPTM_Artikel_Einreichung::POST_TYPE);
        $total = ($existing->publish ?? 0) + ($existing->draft ?? 0) + ($existing->pending ?? 0) + $i + 1;
        $submission_id = sprintf('PERF-%s-%04d', $year, $total);

        // Generate author token
        $author_token = bin2hex(random_bytes(32));

        // Create post
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => '',
            'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
            'post_date' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'))
        ]);

        if (is_wp_error($post_id)) {
            continue;
        }

        // Save ACF fields
        update_field('submission_id', $submission_id, $post_id);
        update_field('author_token', $author_token, $post_id);
        update_field('artikel_status', $status, $post_id);
        update_field('publikationsart', $publikationsart, $post_id);
        update_field('hauptautorin', $author['name'], $post_id);
        update_field('hauptautor_email', $author['email'], $post_id);
        update_field('hauptautor_institution', $author['institution'], $post_id);
        update_field('autoren', $coauthors[array_rand($coauthors)], $post_id);
        update_field('abstract-deutsch', $abstracts_de[$abstract_idx], $post_id);
        update_field('abstract', $abstracts_en[$abstract_idx], $post_id);
        update_field('keywords-deutsch', $keywords_de[$abstract_idx], $post_id);
        update_field('keywords-englisch', $keywords_en[$abstract_idx], $post_id);
        update_field('submitted_at', date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')), $post_id);

        // Add reviewer data for articles in review
        if (in_array($status, [
            DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW,
            DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED,
            DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED,
            DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
            DGPTM_Artikel_Einreichung::STATUS_REJECTED
        ])) {
            // Get available reviewers or create mock data
            $reviewers = get_option(DGPTM_Artikel_Einreichung::OPT_REVIEWERS, []);

            if (!empty($reviewers)) {
                $r1 = $reviewers[array_rand($reviewers)];
                update_field('reviewer_1', $r1, $post_id);
                update_field('reviewer_1_status', 'completed', $post_id);
                update_field('reviewer_1_recommendation', ['accept', 'minor_revision', 'major_revision', 'reject'][array_rand([0,1,2,3])], $post_id);
                update_field('reviewer_1_comment', "Das Manuskript behandelt ein wichtiges Thema. Die Methodik ist solide, jedoch sollten einige Punkte überarbeitet werden:\n\n1. Die Stichprobengröße sollte begründet werden.\n2. Die statistische Analyse könnte präziser dargestellt werden.\n3. Die Diskussion sollte die Limitationen ausführlicher behandeln.\n\nInsgesamt ein guter Beitrag mit Potenzial.", $post_id);

                if (count($reviewers) > 1) {
                    $r2 = $reviewers[array_rand($reviewers)];
                    while ($r2 === $r1 && count($reviewers) > 1) {
                        $r2 = $reviewers[array_rand($reviewers)];
                    }
                    update_field('reviewer_2', $r2, $post_id);
                    update_field('reviewer_2_status', rand(0, 1) ? 'completed' : 'pending', $post_id);
                    if (get_field('reviewer_2_status', $post_id) === 'completed') {
                        update_field('reviewer_2_recommendation', ['accept', 'minor_revision', 'major_revision'][array_rand([0,1,2])], $post_id);
                        update_field('reviewer_2_comment', "Interessante Arbeit mit klinischer Relevanz. Die Autoren sollten folgende Aspekte berücksichtigen:\n\n- Die Einleitung könnte prägnanter formuliert werden.\n- Abbildung 2 ist schwer lesbar, bitte in höherer Auflösung einreichen.\n- Einige Literaturangaben sind veraltet.\n\nNach Überarbeitung zur Publikation geeignet.", $post_id);
                    }
                }
            }

            // Add decision for accepted/rejected articles
            if (in_array($status, [
                DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                DGPTM_Artikel_Einreichung::STATUS_REJECTED,
                DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED
            ])) {
                update_field('decision_at', date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')), $post_id);

                if ($status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED) {
                    update_field('decision_letter', "Sehr geehrte Autorinnen und Autoren,\n\nwir freuen uns, Ihnen mitteilen zu können, dass Ihr Manuskript zur Publikation in Die Perfusiologie angenommen wurde.\n\nDie finalen Produktionsschritte werden in Kürze eingeleitet.\n\nMit freundlichen Grüßen,\nDie Redaktion", $post_id);
                } elseif ($status === DGPTM_Artikel_Einreichung::STATUS_REJECTED) {
                    update_field('decision_letter', "Sehr geehrte Autorinnen und Autoren,\n\nnach sorgfältiger Prüfung durch unsere Gutachter müssen wir Ihnen leider mitteilen, dass Ihr Manuskript nicht für eine Publikation in Die Perfusiologie geeignet ist.\n\nDie wesentlichen Kritikpunkte entnehmen Sie bitte den Gutachten.\n\nMit freundlichen Grüßen,\nDie Redaktion", $post_id);
                } else {
                    update_field('decision_letter', "Sehr geehrte Autorinnen und Autoren,\n\ndie Gutachter haben Ihr Manuskript geprüft und empfehlen eine Überarbeitung.\n\nBitte berücksichtigen Sie die Anmerkungen der Reviewer und reichen Sie eine überarbeitete Version innerhalb von 4 Wochen ein.\n\nMit freundlichen Grüßen,\nDie Redaktion", $post_id);
                }
            }
        }

        // Add some editor notes
        if (rand(0, 1)) {
            update_field('editor_notes', "Artikel wurde am " . date('d.m.Y') . " geprüft.\nPriorität: " . ['Normal', 'Hoch', 'Niedrig'][array_rand([0,1,2])], $post_id);
        }

        $created[] = [
            'id' => $post_id,
            'submission_id' => $submission_id,
            'title' => $title,
            'author' => $author['name'],
            'status' => $status,
            'token' => $author_token
        ];
    }

    return $created;
}

// Execute if called directly or with parameter
if (php_sapi_name() === 'cli' || (isset($_GET['generate_testdata']) && current_user_can('manage_options'))) {
    $count = isset($_GET['count']) ? intval($_GET['count']) : 5;
    $results = dgptm_generate_artikel_testdata($count);

    if (php_sapi_name() === 'cli') {
        echo "\n=== Testdaten generiert ===\n\n";
        foreach ($results as $item) {
            echo sprintf(
                "ID: %s | %s | %s | Status: %s\n",
                $item['submission_id'],
                $item['author'],
                substr($item['title'], 0, 50) . '...',
                $item['status']
            );
            echo "Token-URL: ?autor_token=" . $item['token'] . "\n\n";
        }
        echo "Gesamt: " . count($results) . " Artikel erstellt.\n";
    } else {
        // Redirect back to settings with success message
        set_transient('dgptm_artikel_testdata_created', count($results), 30);
        wp_redirect(admin_url('admin.php?page=dgptm-artikel-settings&testdata_created=' . count($results)));
        exit;
    }
}
