<?php
/**
 * DGPTM Stipendium — Seeder fuer Freigaben und Kommentare.
 *
 * Schreibt einmalig (versioniert) die in der Diskussion ausgetauschten
 * Freigaben und Kommentare in die wp_options-Eintraege der Freigabe-Komponente.
 * Erkennt User per user_login mit Fallback auf display_name.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Freigabe_Seeder {

    const SEED_VERSION_KEY = 'dgptm_stipendium_freigabe_seed_version';
    const SEED_VERSION     = 'r1-2026-04-25';

    /**
     * Beim Modul-Init aufgerufen. Idempotent: laeuft nur einmal pro Version.
     */
    public static function maybe_seed() {
        if (get_option(self::SEED_VERSION_KEY) === self::SEED_VERSION) {
            return;
        }

        self::seed_approvals();
        self::seed_comments();

        update_option(self::SEED_VERSION_KEY, self::SEED_VERSION, false);
    }

    /**
     * Erneutes Seeding erzwingen (z.B. nach Anpassungen).
     */
    public static function force_reseed() {
        delete_option(self::SEED_VERSION_KEY);
        self::maybe_seed();
    }

    private static function resolve_user($login_or_name) {
        $u = get_user_by('login', $login_or_name);
        if (!$u) {
            $u = get_user_by('email', $login_or_name);
        }
        if (!$u) {
            // Fallback per display_name
            $found = get_users(['search' => $login_or_name, 'number' => 1]);
            if (!empty($found)) $u = $found[0];
        }
        if ($u) {
            return ['id' => (int) $u->ID, 'name' => $u->display_name ?: $login_or_name];
        }
        return ['id' => 0, 'name' => $login_or_name];
    }

    /* ──────────────────────────────────────────
     * Freigaben (3)
     * ────────────────────────────────────────── */

    private static function seed_approvals() {
        $existing = get_option(\DGPTM_Stipendium_Freigabe::OPT_APPROVALS, []);
        if (!is_array($existing)) $existing = [];
        $existing_uids = array_map(function($a){ return (int)($a['user_id'] ?? 0); }, $existing);

        $entries = [
            ['login' => 'chkluess', 'timestamp' => '2026-04-12 13:12:24'],
            ['login' => 'frmuench', 'timestamp' => '2026-04-13 09:12:03'],
            ['login' => 'simayer',  'timestamp' => '2026-04-15 22:57:04'],
        ];

        foreach ($entries as $e) {
            $u = self::resolve_user($e['login']);
            if ($u['id'] && in_array($u['id'], $existing_uids, true)) {
                continue;
            }
            $existing[] = [
                'user_id'   => $u['id'],
                'user_name' => $u['name'],
                'timestamp' => $e['timestamp'],
            ];
            $existing_uids[] = $u['id'];
        }

        update_option(\DGPTM_Stipendium_Freigabe::OPT_APPROVALS, $existing, false);
    }

    /* ──────────────────────────────────────────
     * Kommentare (32, in Original-Reihenfolge)
     * ────────────────────────────────────────── */

    private static function seed_comments() {
        $existing = get_option(\DGPTM_Stipendium_Freigabe::OPT_COMMENTS, []);
        if (!is_array($existing)) $existing = [];

        // Marker pro Kommentar (section + user + timestamp): nicht doppelt einpflegen
        $marker = function($c) {
            return ($c['section'] ?? '') . '|' . ($c['user_id'] ?? '') . '|' . ($c['timestamp'] ?? '');
        };
        $existing_markers = array_map($marker, $existing);

        $defs = self::get_comment_definitions();

        foreach ($defs as $def) {
            $u = self::resolve_user($def['login']);
            $entry = [
                'id'        => wp_generate_uuid4(),
                'section'   => $def['section'],
                'user_id'   => $u['id'],
                'user_name' => $u['name'],
                'text'      => $def['text'],
                'timestamp' => $def['timestamp'],
            ];
            if (in_array($marker($entry), $existing_markers, true)) {
                continue;
            }
            $existing[] = $entry;
            $existing_markers[] = $marker($entry);
        }

        // Nach timestamp sortieren
        usort($existing, function($a, $b) {
            return strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? '');
        });

        update_option(\DGPTM_Stipendium_Freigabe::OPT_COMMENTS, $existing, false);
    }

    /**
     * Alle 32 Kommentare aus der Diskussion (Stand 2026-04-25).
     */
    private static function get_comment_definitions() {
        return [
            // 1. Was aendert sich?
            [
                'section'   => 'section-aenderungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:27:11',
                'text'      => "Wir haben aktuell noch nichts abgestimmt. Dafür gibt es unsere Sitzungen und hier werden wir nicht überstürzen, da ja schon Bewerbungen eingegangen sind. Die Digital nach zu fordern sehe ich als schwierig und auch von Madeleine auf Vollständigkeit überprüft wird und auch nachfragen gestellt werden, bevor sie an den Stipentiumsrat weitergeleitet werden. Oder sehe ich hier was falsch. Auch hier haben wir aktuell noch nicht final nur Vorgaben für Promotion und muss jetzt auch gemacht werden für Güttler. Realistisch gesehen wird das sehr knapp werden bis 30.4. das muss erst auch alles stehen und da hat Dirk die Hoheit. Das möchte ich auch nicht antasten. Auch wichtig wegen der Unabhängigkeit. Prinzipiell super, auch hier war besprochen das wir das bis Weihnachten haben wollten. Jetzt würden wir wieder unter druck geraden heuer das digitale Toll durch zu drücken.",
            ],
            [
                'section'   => 'section-aenderungen',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 18:22:24',
                'text'      => "In Leipzig wurde besprochen, das wir uns um eine digitale Umsetzung kümmern. Wenn der Flow gerade gut ist, passt doch alles. Heißt nicht, das wir es direkt implementieren jetzt. Ich freue mich her, das die Arbeit so ansteckend ist und wir hier Fortschritte machen.",
            ],
            [
                'section'   => 'section-aenderungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 08:52:39',
                'text'      => "Damit kann ich sehr gut leben",
            ],

            // 2. Wer ist beteiligt?
            [
                'section'   => 'section-rollen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:34:02',
                'text'      => "Hier wie schon geschrieben ist nee Tolle Idee und für 2027 sehr Sinnvoll und sollten wir auch dran bleiben und freischalten sobald alles geklärt ist. Kann ja sein das sich Leute im Juli für 2027. Hier sollte bei Eingang Bewerbung erst die GS eine Meldung bekommen. Dann findet Vorprüfung statt und am 1.Mail 2027 wird das für den Gutachterrat bzw. in Abstimmung erst für den Vorsitzenden freigeschalten und er schaltet dann für die Gutachter frei. Kann ja sein das Befangenheit gibt und das kann der Vorsitzende vorab klären und trotzdem noch die Möglichkeit geben das Gutachter das machen können. Zweites Problem nicht alle Gutachter sind auch Mitglieder. Wie ist das mit der Freischaltung für den speziellen Bereich.  Das alle Gutachter die Gutachten der anderen Einsehen können halte ich für nicht gut. Muss noch mit Dirk besprochen werden was er meint. Hier soll max. Unabhängigkeit gewährleistet werden.",
            ],
            [
                'section'   => 'section-rollen',
                'login'     => 'dbuchwald',
                'timestamp' => '2026-04-12 12:10:05',
                'text'      => "Aus meiner Sicht dürfen die Gutachter während des Begutachtungsprozesses NICHT die Gutachten der anderen sehen. Nach Abschluss und Entscheidung könnte man dann allen alle Antworten zeigen. So kenne ich es von der Artikelbegutachtung bei einer Zeitschrift.",
            ],
            [
                'section'   => 'section-rollen',
                'login'     => 'Sebastian Melzer',
                'timestamp' => '2026-04-12 13:45:38',
                'text'      => "Die beiden Probleme sind handelbar. Ich baue das dann auf Token Basis auf. D.h. jeder bekommt einen individuellen Link welcher auch unabhängig vom Login funktioniert. Ich frage mich allerdings warum externe Nichtmitglieder in diesen Prozess eingreifen sollten.",
            ],
            [
                'section'   => 'section-rollen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 08:55:08',
                'text'      => "Nicht alle Hochschullehrer die es Beurteilen sind soweit ich weiß Mitglied. Daher ist das so. Ich denke da werden wir Mitte des Jahres nochmal drauf schauen und eine Lösung finden das Gutachter bei uns auch gelistet sind. Wir sind im Aufbau und da muss halt noch so gearbeitet werden.",
            ],

            // 3. Ablauf
            [
                'section'   => 'section-ablauf',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 08:57:30',
                'text'      => "Die Unterlagen liegen im \"Internen Mitgliederbereich\" und werden dort dem Vorsitzenden, Dirk und den weiteren Mitgliedern zur Verfügung gestellt.",
            ],
            [
                'section'   => 'section-ablauf',
                'login'     => 'Sebastian Melzer',
                'timestamp' => '2026-04-12 08:59:43',
                'text'      => "Genau, die Unterlagen sind sicher im CRM gespeichert und werden dann da abgerufen und angezeigt. Das verhindert, dass die Lebensläuft ect. frei im Netz stehen.",
            ],
            [
                'section'   => 'section-ablauf',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:38:52',
                'text'      => "Wie vorher gesagt sind hier ein paar Fristen vor zu geben. Das sollte kein Problem sein. Hier müssen überall Beschreibungstext angepasst werden, das klar ist was genau zu tun ist. Auch halte ich es nicht für notwendig das alle Vorstandmitglieder die Unterlagen direkt einsehen können. dafür haben wir ein Gremium. Auf einzelnachfrage bei der VS zur Bestätigung der Zusage kann hier nachgeschaut werden.  Die Phasen find ich vom Prinzip gut.",
            ],

            // 4. Bewertungsbogen
            [
                'section'   => 'section-bewertungsbogen',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 08:58:13',
                'text'      => "Der Leitfaden wird komplett abgebildet. Prüfer müssen lediglich Ihre Noten und ggf. Kommentare eingeben.",
            ],
            [
                'section'   => 'section-bewertungsbogen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:40:22',
                'text'      => "Sieht gut aus und kann mir sogar vorstellen das wir diesen Teil heuer schon so nehmen könnten. Allerdings aktuell ist das wie auch schon geschrieben nur für das Promotionsstipendium gemacht. das Güttler sieht anders aus.",
            ],
            [
                'section'   => 'section-bewertungsbogen',
                'login'     => 'Sebastian Melzer',
                'timestamp' => '2026-04-12 11:58:37',
                'text'      => "Hi, bitte die genauen Unterschiede hier einmal aufzeigen. Bisher behandle ich beide Stipendien gleich.",
            ],
            [
                'section'   => 'section-bewertungsbogen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 08:57:29',
                'text'      => "Ja, Güttler hat ein wenig andere Voraussetzungen und Schwerpunkte. Promotion sollte schon mehr dahinter stecken. Wird ja erarbeitet.",
            ],

            // 5. Dokumente
            [
                'section'   => 'section-dokumente',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 08:58:30',
                'text'      => "Exposé/Exzerpt?",
            ],
            [
                'section'   => 'section-dokumente',
                'login'     => 'Sebastian Melzer',
                'timestamp' => '2026-04-12 11:20:34',
                'text'      => "ORCID-ID abfragen und Historie automatisch abrufen",
            ],
            [
                'section'   => 'section-dokumente',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:43:07',
                'text'      => "Das ist eine gute Idee das für 2027 mit zu fordern. Jetzt ist es ja die Projektbeschreibung. Aber das macht wahnsinnig sinn und da sollten wir eine Musterexpose entwerfen und als Beispiel angeben. Dann bekommen wir da in Zukunft eine Struktur rein. Jasper könntest du ein Musterexpose mal entwerfen?",
            ],
            [
                'section'   => 'section-dokumente',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 18:53:16',
                'text'      => "Kann ich grundsätzlich machen, würde aber gerne meine Sichtweise zur Diskussion stellen:\nDa jede Hochschule aktuell eine eigenes \"Template\" verwendet (wäre perspektivisch sicher klasse, wenn wir es mit Münster, Berlin und der HFU gleich hätten), würde ich die Bewerbenden ungern ein weiteres Mal für das Stipendium formatieren lassen. Vielmehr würde ich ein einseitiges Dokument zur Verfügung stellen, welche Punkte und auch welche Reihenfolge eingehalten werden soll und kurze Erklärungen dazu machen:\nTitel der Arbeit / Arbeitstitel\nEinleitung & Problemstellung (inkl. expliziter Forschungsfrage)\nZielsetzung der Arbeit\nWissenschaftlicher Hintergrund & Forschungsstand\nMethodik\nRelevanz für die Perfusiologie\nZeitplan\nLiteraturverzeichnis (zählt nicht zur Seitenzahl)\n\nDie Gliederung zeigt damit sogar einen direkten Bezug zu den Bewetungskriterien A-D.\nEin Drift dazu hätte ich fertig.\nSofern die Idee Zustimmung findet, würde das Dokument zum Download auf der Stipendien-Seite liegen und beim Upload-Formular als Pflichtlektüre verlinkt werden.",
            ],
            [
                'section'   => 'section-dokumente',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 18:56:06',
                'text'      => "Gedanklich bin ich allerdings eher beim Güttler Stipendium. Welche Erweiterung eine Diss mit sich bringt, kann ich nicht beantworten. Darauf bin ich auch gespannt, was sich im Beurteilungsleitfaden gegenüber dem Güttler ändern muss.",
            ],
            [
                'section'   => 'section-dokumente',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 09:00:37',
                'text'      => "Expose Vorlage ist sehr sinnvoll und sollten wir im Sep. dann final haben und den Akademischen Beirat vorstellen. Die werden das dann sicher übernehmen. Beim Dissertation muss ja sowieso eine Projektbeschreibung gemacht werden. Ist im Prinzip auch ein Expose. nur halt umfangreicher denke ich.",
            ],

            // 6. Datenschutz
            [
                'section'   => 'section-datenschutz',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:44:08',
                'text'      => "Das ist stark. Sehr gut",
            ],

            // 7. Einstellungen
            [
                'section'   => 'section-einstellungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:47:12',
                'text'      => "Hier sollte die GS in Rücksprache mit den Präsidenten und Vorsitzenden, Gutachter freigeben können. Hier wird es ja immer wieder Änderungen geben. Wir sollten vielleicht in der Geschäftsordnung aufnehmen das das Gremium alle drei Jahre überprüft werden sollte. Jasper was meinst du?",
            ],
            [
                'section'   => 'section-einstellungen',
                'login'     => 'jaheller',
                'timestamp' => '2026-04-12 18:46:22',
                'text'      => "Finde ich sehr gut. Habe es sogar in die Agenda für die nächste Vorstandssitzung mit aufgenommen. Vor allem fehlt etwas die Balance aus Erfahrung und neuen Gesichtern, die natürlich mind. für das Güttler einen M.Sc. Abschluss haben. Hier dachte ich z.B. an Simon.",
            ],
            [
                'section'   => 'section-einstellungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 09:04:22',
                'text'      => "Lass uns das auch für 2027 diskutieren und die Gutachter für 2026 einfach erst mal so lassen. Oder Dirk siehst du das anders? Wie ist die Erfahrung mit den aktuellen Gutachter. Wir sollten hier in die breite gehen und Leuten sowenig wie möglich Doppelfunktionen machen lassen. Dann kommen wir auch mehr Akzeptanz. Obwohl ich Simon für absolut geeignet halt.",
            ],

            // 8. Benachrichtigungen
            [
                'section'   => 'section-benachrichtigungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:48:35',
                'text'      => "Hier ist die GS in Form von Madeleine vorgeschaltet. Das ist wichtig das der Vorstitzende nicht vorzeitig belästigt sit",
            ],
            [
                'section'   => 'section-benachrichtigungen',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:50:31',
                'text'      => "dann das Madeleine einen Kopf drückt wo das eine Bestätigung raus geht das die Unterlagen vollständig sind. Eine Automaische Mail an GS ist ja klar und dann das an den Bewerber geht das die Sachen angekommen sind und geprüft werden. Finde ich sehr gut.",
            ],

            // 9. Naechste Schritte
            [
                'section'   => 'section-naechste-schritte',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-12 11:51:58',
                'text'      => "Ja den Plan so aufbauen und das wir gerne schon ab Sommer das für 2027 final haben, das fände ich super. Am liebsten das wir das am 2.6. final besprechen und dann die letzten Schritte terminieren. Danke",
            ],
            [
                'section'   => 'section-naechste-schritte',
                'login'     => 'dbuchwald',
                'timestamp' => '2026-04-12 12:16:38',
                'text'      => "Auch wenn es digital in diesem Jahr noch nicht praktiziert wird, können wir die Bewertung Papier/pdf-basiert nach dem neuen Schema bereits durchführen. Für das Güttler-Stipendium sind nur minimale Änderungen in den Fragestellungen und in den Bewertungsgewichtungen erforderlich. Bin bei der Bearbeitung.",
            ],
            [
                'section'   => 'section-naechste-schritte',
                'login'     => 'Sebastian Melzer',
                'timestamp' => '2026-04-12 15:49:34',
                'text'      => "Passt der Zeitplan so? Kommentarfunktion bleibt hier offen und wird an alle Beteiligten kommuniziert.",
            ],
            [
                'section'   => 'section-naechste-schritte',
                'login'     => 'frmuench',
                'timestamp' => '2026-04-13 09:07:10',
                'text'      => "ich finde den Zeitplan sehr gut und das mit der Test Phase mit den Gutachern so wenn ich es richtig verstehe nach dem Kongress auch. Klar wenn wir das bis 30.4. in PDF Form hin bekommen wäre das großartig.",
            ],
        ];
    }
}
