<?php
/**
 * Plugin Name: DGPTM Vorstandsliste
 * Description: Zeigt die historische Vorstandsliste mit allen Amtsperioden. Shortcode: [dgptm_vorstandsliste]
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-vorstandsliste
 */

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten
define('DGPTM_VORSTANDSLISTE_VERSION', '1.0.0');
define('DGPTM_VORSTANDSLISTE_PATH', plugin_dir_path(__FILE__));
define('DGPTM_VORSTANDSLISTE_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Vorstandsliste')) {

    class DGPTM_Vorstandsliste {

        private static $instance = null;

        /**
         * Position-Definitionen
         */
        private $positionen = [
            'praesident' => 'Vorsitzender/Präsident',
            'vizepraesident' => 'Stellvertreter/Vizepräsident',
            'schriftfuehrer' => 'Schriftführer',
            'schatzmeister' => 'Schatzmeister',
            'beisitzer' => 'Beisitzer',
        ];

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // CPTs registrieren
            add_action('init', [$this, 'register_post_types'], 5);

            // ACF Felder registrieren
            add_action('acf/init', [$this, 'register_acf_fields']);

            // Assets registrieren
            add_action('wp_enqueue_scripts', [$this, 'register_assets']);

            // Shortcode registrieren
            add_shortcode('dgptm_vorstandsliste', [$this, 'render_shortcode']);

            // Admin-Spalten für bessere Übersicht
            add_filter('manage_vorstand_periode_posts_columns', [$this, 'add_admin_columns']);
            add_action('manage_vorstand_periode_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

            // Rewrite Rules flushen
            add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);

            // Admin-Menü für CSV-Import
            add_action('admin_menu', [$this, 'add_import_menu']);

            // AJAX Handler für CSV-Import
            add_action('wp_ajax_dgptm_import_vorstandsliste', [$this, 'ajax_import_csv']);

            // AJAX Handler für Vita-Abruf
            add_action('wp_ajax_dgptm_get_vita', [$this, 'ajax_get_vita']);
            add_action('wp_ajax_nopriv_dgptm_get_vita', [$this, 'ajax_get_vita']);
        }

        /**
         * Registriert die Custom Post Types
         */
        public function register_post_types() {
            // CPT: Vorstandsmitglieder (Personen)
            if (!post_type_exists('vorstand_person')) {
                register_post_type('vorstand_person', [
                    'labels' => [
                        'name'               => 'Vorstandsmitglieder',
                        'singular_name'      => 'Vorstandsmitglied',
                        'menu_name'          => 'Vorstandsmitglieder',
                        'add_new'            => 'Hinzufügen',
                        'add_new_item'       => 'Neues Mitglied hinzufügen',
                        'edit_item'          => 'Mitglied bearbeiten',
                        'view_item'          => 'Mitglied ansehen',
                        'all_items'          => 'Alle Mitglieder',
                        'search_items'       => 'Mitglieder suchen',
                        'not_found'          => 'Keine Mitglieder gefunden.',
                    ],
                    'public'             => false,
                    'publicly_queryable' => false,
                    'show_ui'            => true,
                    'show_in_menu'       => 'edit.php?post_type=vorstand_periode',
                    'query_var'          => false,
                    'capability_type'    => 'post',
                    'has_archive'        => false,
                    'hierarchical'       => false,
                    'menu_icon'          => 'dashicons-admin-users',
                    'supports'           => ['title', 'editor', 'thumbnail'],
                    'show_in_rest'       => true,
                ]);
            }

            // CPT: Amtsperioden
            if (!post_type_exists('vorstand_periode')) {
                register_post_type('vorstand_periode', [
                    'labels' => [
                        'name'               => 'Amtsperioden',
                        'singular_name'      => 'Amtsperiode',
                        'menu_name'          => 'Vorstandsliste',
                        'add_new'            => 'Neue Periode',
                        'add_new_item'       => 'Neue Amtsperiode hinzufügen',
                        'edit_item'          => 'Amtsperiode bearbeiten',
                        'view_item'          => 'Amtsperiode ansehen',
                        'all_items'          => 'Alle Perioden',
                        'search_items'       => 'Perioden suchen',
                        'not_found'          => 'Keine Perioden gefunden.',
                    ],
                    'public'             => false,
                    'publicly_queryable' => false,
                    'show_ui'            => true,
                    'show_in_menu'       => true,
                    'query_var'          => false,
                    'capability_type'    => 'post',
                    'has_archive'        => false,
                    'hierarchical'       => false,
                    'menu_position'      => 26,
                    'menu_icon'          => 'dashicons-groups',
                    'supports'           => ['title', 'thumbnail'],
                    'show_in_rest'       => true,
                ]);
            }
        }

        /**
         * Registriert ACF-Felder
         */
        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) {
                return;
            }

            // Felder für Vorstandsmitglieder (Person)
            acf_add_local_field_group([
                'key' => 'group_vorstand_person',
                'title' => 'Mitglied-Details',
                'fields' => [
                    [
                        'key' => 'field_person_titel',
                        'label' => 'Titel',
                        'name' => 'person_titel',
                        'type' => 'text',
                        'instructions' => 'Akademischer Titel (z.B. Dr., Prof.)',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'vorstand_person',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
            ]);

            // Felder für Amtsperioden
            acf_add_local_field_group([
                'key' => 'group_vorstand_periode',
                'title' => 'Amtsperiode-Details',
                'fields' => [
                    [
                        'key' => 'field_periode_start',
                        'label' => 'Beginn',
                        'name' => 'periode_start',
                        'type' => 'date_picker',
                        'display_format' => 'm/Y',
                        'return_format' => 'Y-m-d',
                        'first_day' => 1,
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_periode_ende',
                        'label' => 'Ende',
                        'name' => 'periode_ende',
                        'type' => 'date_picker',
                        'display_format' => 'm/Y',
                        'return_format' => 'Y-m-d',
                        'first_day' => 1,
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_periode_notiz',
                        'label' => 'Notiz zur Periode',
                        'name' => 'periode_notiz',
                        'type' => 'textarea',
                        'rows' => 2,
                        'instructions' => 'Optionale Anmerkungen zu dieser Amtsperiode',
                    ],
                    [
                        'key' => 'field_positionen',
                        'label' => 'Positionen',
                        'name' => 'positionen',
                        'type' => 'repeater',
                        'layout' => 'block',
                        'button_label' => 'Position hinzufügen',
                        'sub_fields' => [
                            [
                                'key' => 'field_position_typ',
                                'label' => 'Position',
                                'name' => 'position_typ',
                                'type' => 'select',
                                'choices' => $this->positionen,
                                'default_value' => 'beisitzer',
                                'wrapper' => ['width' => '25'],
                            ],
                            [
                                'key' => 'field_position_person',
                                'label' => 'Person',
                                'name' => 'position_person',
                                'type' => 'post_object',
                                'post_type' => ['vorstand_person'],
                                'return_format' => 'id',
                                'allow_null' => 0,
                                'wrapper' => ['width' => '35'],
                            ],
                            [
                                'key' => 'field_position_ausgeschieden',
                                'label' => 'Vorzeitig ausgeschieden',
                                'name' => 'position_ausgeschieden',
                                'type' => 'true_false',
                                'ui' => 1,
                                'wrapper' => ['width' => '15'],
                            ],
                            [
                                'key' => 'field_position_ausgeschieden_datum',
                                'label' => 'Ausgeschieden am',
                                'name' => 'position_ausgeschieden_datum',
                                'type' => 'date_picker',
                                'display_format' => 'm/Y',
                                'return_format' => 'Y-m-d',
                                'conditional_logic' => [
                                    [
                                        [
                                            'field' => 'field_position_ausgeschieden',
                                            'operator' => '==',
                                            'value' => '1',
                                        ],
                                    ],
                                ],
                                'wrapper' => ['width' => '25'],
                            ],
                            [
                                'key' => 'field_position_notiz',
                                'label' => 'Notiz',
                                'name' => 'position_notiz',
                                'type' => 'text',
                                'instructions' => 'z.B. "Positionswechsel von Beisitzer"',
                            ],
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'vorstand_periode',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
            ]);
        }

        /**
         * Prüft ob der aktuelle Benutzer bearbeiten darf
         */
        public function kann_bearbeiten() {
            if (!is_user_logged_in()) {
                return false;
            }

            // Administratoren und Editoren
            if (current_user_can('manage_options') || current_user_can('edit_pages')) {
                return true;
            }

            // Benutzer mit timeline-Usermeta
            $user_id = get_current_user_id();
            $timeline_meta = get_user_meta($user_id, 'timeline', true);

            return !empty($timeline_meta);
        }

        /**
         * Registriert Frontend-Assets
         */
        public function register_assets() {
            wp_register_style(
                'dgptm-vorstandsliste',
                DGPTM_VORSTANDSLISTE_URL . 'assets/css/vorstandsliste.css',
                [],
                DGPTM_VORSTANDSLISTE_VERSION
            );

            wp_register_script(
                'dgptm-vorstandsliste',
                DGPTM_VORSTANDSLISTE_URL . 'assets/js/vorstandsliste.js',
                ['jquery'],
                DGPTM_VORSTANDSLISTE_VERSION,
                true
            );
        }

        /**
         * Shortcode [dgptm_vorstandsliste]
         */
        public function render_shortcode($atts) {
            $atts = shortcode_atts([
                'layout' => 'table',      // table oder cards
                'order' => 'DESC',        // ASC oder DESC
                'show_photos' => 'true',
                'show_vita' => 'true',
                'collapsed' => 'false',
            ], $atts);

            wp_enqueue_style('dgptm-vorstandsliste');
            wp_enqueue_script('dgptm-vorstandsliste');

            // Lokalisierung für JavaScript
            wp_localize_script('dgptm-vorstandsliste', 'dgptmVorstandsliste', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_vorstandsliste_nonce'),
            ]);

            // Perioden abrufen
            $perioden = get_posts([
                'post_type' => 'vorstand_periode',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => 'periode_start',
                'orderby' => 'meta_value',
                'order' => strtoupper($atts['order']),
            ]);

            if (empty($perioden)) {
                return '<p class="dgptm-vorstandsliste-empty">Keine Amtsperioden gefunden.</p>';
            }

            $kann_bearbeiten = $this->kann_bearbeiten();
            $show_photos = $atts['show_photos'] === 'true';
            $show_vita = $atts['show_vita'] === 'true';
            $collapsed = $atts['collapsed'] === 'true';

            ob_start();

            if ($atts['layout'] === 'cards') {
                echo $this->render_cards_layout($perioden, $kann_bearbeiten, $show_photos, $show_vita, $collapsed);
            } else {
                echo $this->render_table_layout($perioden, $kann_bearbeiten, $show_photos, $show_vita);
            }

            // Vita Modal Container
            if ($show_vita) {
                ?>
                <div id="dgptm-vita-modal" class="dgptm-vita-modal" style="display:none;">
                    <div class="dgptm-vita-modal-content">
                        <button class="dgptm-vita-modal-close">&times;</button>
                        <div class="dgptm-vita-modal-header">
                            <div class="dgptm-vita-modal-photo"></div>
                            <h3 class="dgptm-vita-modal-name"></h3>
                        </div>
                        <div class="dgptm-vita-modal-body"></div>
                    </div>
                </div>
                <?php
            }

            // Foto Lightbox Container
            if ($show_photos) {
                ?>
                <div id="dgptm-foto-lightbox" class="dgptm-foto-lightbox" style="display:none;">
                    <div class="dgptm-foto-lightbox-content">
                        <button class="dgptm-foto-lightbox-close">&times;</button>
                        <img src="" alt="" class="dgptm-foto-lightbox-img">
                        <p class="dgptm-foto-lightbox-caption"></p>
                    </div>
                </div>
                <?php
            }

            return ob_get_clean();
        }

        /**
         * Rendert Tabellen-Layout
         */
        private function render_table_layout($perioden, $kann_bearbeiten, $show_photos, $show_vita) {
            ob_start();
            ?>
            <div class="dgptm-vorstandsliste dgptm-vorstandsliste-table">
                <table>
                    <thead>
                        <tr>
                            <?php if ($show_photos): ?>
                                <th class="col-foto"></th>
                            <?php endif; ?>
                            <th class="col-periode">Amtsperiode</th>
                            <th class="col-position">Präsident</th>
                            <th class="col-position">Vizepräsident</th>
                            <th class="col-position">Schriftführer</th>
                            <th class="col-position">Schatzmeister</th>
                            <th class="col-beisitzer">Beisitzer</th>
                            <?php if ($kann_bearbeiten): ?>
                                <th class="col-actions"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perioden as $periode): ?>
                            <?php echo $this->render_table_row($periode, $kann_bearbeiten, $show_photos, $show_vita); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Rendert eine Tabellenzeile
         */
        private function render_table_row($periode, $kann_bearbeiten, $show_photos, $show_vita) {
            $periode_id = $periode->ID;
            $start = get_field('periode_start', $periode_id);
            $ende = get_field('periode_ende', $periode_id);
            $positionen = get_field('positionen', $periode_id) ?: [];
            $thumbnail = get_the_post_thumbnail_url($periode_id, 'thumbnail');

            // Formatiere Zeitraum
            $zeitraum = $this->format_zeitraum($start, $ende);

            // Sortiere Positionen nach Typ
            $pos_nach_typ = $this->sortiere_positionen($positionen);

            ob_start();
            ?>
            <tr>
                <?php if ($show_photos): ?>
                    <td class="col-foto">
                        <?php if ($thumbnail): ?>
                            <img src="<?php echo esc_url($thumbnail); ?>"
                                 alt="<?php echo esc_attr($zeitraum); ?>"
                                 class="dgptm-periode-foto"
                                 data-full="<?php echo esc_url(get_the_post_thumbnail_url($periode_id, 'large')); ?>"
                                 data-caption="Vorstand <?php echo esc_attr($zeitraum); ?>">
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td class="col-periode">
                    <strong><?php echo esc_html($zeitraum); ?></strong>
                </td>
                <td class="col-position">
                    <?php echo $this->render_personen_liste($pos_nach_typ['praesident'] ?? [], $show_vita); ?>
                </td>
                <td class="col-position">
                    <?php echo $this->render_personen_liste($pos_nach_typ['vizepraesident'] ?? [], $show_vita); ?>
                </td>
                <td class="col-position">
                    <?php echo $this->render_personen_liste($pos_nach_typ['schriftfuehrer'] ?? [], $show_vita); ?>
                </td>
                <td class="col-position">
                    <?php echo $this->render_personen_liste($pos_nach_typ['schatzmeister'] ?? [], $show_vita); ?>
                </td>
                <td class="col-beisitzer">
                    <?php echo $this->render_personen_liste($pos_nach_typ['beisitzer'] ?? [], $show_vita); ?>
                </td>
                <?php if ($kann_bearbeiten): ?>
                    <td class="col-actions">
                        <a href="<?php echo esc_url(get_edit_post_link($periode_id)); ?>"
                           class="dgptm-edit-link"
                           title="Bearbeiten">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                    </td>
                <?php endif; ?>
            </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * Rendert Card-Layout
         */
        private function render_cards_layout($perioden, $kann_bearbeiten, $show_photos, $show_vita, $collapsed) {
            ob_start();
            ?>
            <div class="dgptm-vorstandsliste dgptm-vorstandsliste-cards">
                <?php foreach ($perioden as $periode): ?>
                    <?php echo $this->render_card($periode, $kann_bearbeiten, $show_photos, $show_vita, $collapsed); ?>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Rendert eine einzelne Karte
         */
        private function render_card($periode, $kann_bearbeiten, $show_photos, $show_vita, $collapsed) {
            $periode_id = $periode->ID;
            $start = get_field('periode_start', $periode_id);
            $ende = get_field('periode_ende', $periode_id);
            $positionen = get_field('positionen', $periode_id) ?: [];
            $notiz = get_field('periode_notiz', $periode_id);
            $thumbnail = get_the_post_thumbnail_url($periode_id, 'medium');

            $zeitraum = $this->format_zeitraum($start, $ende);
            $pos_nach_typ = $this->sortiere_positionen($positionen);

            $collapsed_class = $collapsed ? 'collapsed' : '';

            ob_start();
            ?>
            <div class="dgptm-vorstand-card <?php echo esc_attr($collapsed_class); ?>">
                <div class="dgptm-card-header">
                    <div class="dgptm-card-title">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <strong><?php echo esc_html($zeitraum); ?></strong>
                    </div>
                    <div class="dgptm-card-actions">
                        <?php if ($show_photos && $thumbnail): ?>
                            <button class="dgptm-foto-btn"
                                    data-full="<?php echo esc_url(get_the_post_thumbnail_url($periode_id, 'large')); ?>"
                                    data-caption="Vorstand <?php echo esc_attr($zeitraum); ?>"
                                    title="Foto anzeigen">
                                <span class="dashicons dashicons-camera"></span>
                            </button>
                        <?php endif; ?>
                        <?php if ($kann_bearbeiten): ?>
                            <a href="<?php echo esc_url(get_edit_post_link($periode_id)); ?>"
                               class="dgptm-edit-btn"
                               title="Bearbeiten">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                        <?php endif; ?>
                        <button class="dgptm-toggle-btn" title="Auf-/Zuklappen">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                </div>
                <div class="dgptm-card-body">
                    <?php foreach (['praesident', 'vizepraesident', 'schriftfuehrer', 'schatzmeister', 'beisitzer'] as $pos_typ): ?>
                        <?php if (!empty($pos_nach_typ[$pos_typ])): ?>
                            <div class="dgptm-card-row">
                                <span class="dgptm-position-label"><?php echo esc_html($this->positionen[$pos_typ]); ?>:</span>
                                <span class="dgptm-position-value">
                                    <?php echo $this->render_personen_liste($pos_nach_typ[$pos_typ], $show_vita); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php
                    // Ausgeschiedene Mitglieder
                    $ausgeschiedene = $this->get_ausgeschiedene($positionen);
                    if (!empty($ausgeschiedene)):
                    ?>
                        <div class="dgptm-card-ausgeschieden">
                            <?php foreach ($ausgeschiedene as $aus): ?>
                                <div class="dgptm-ausgeschieden-item">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php echo esc_html($aus['name']); ?> ausgeschieden <?php echo esc_html($aus['datum']); ?>
                                    <?php if ($aus['notiz']): ?>
                                        <em>(<?php echo esc_html($aus['notiz']); ?>)</em>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($notiz): ?>
                        <div class="dgptm-card-notiz">
                            <em><?php echo esc_html($notiz); ?></em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Formatiert den Zeitraum
         */
        private function format_zeitraum($start, $ende) {
            if (!$start) {
                return 'Unbekannt';
            }

            $start_formatted = date('m/y', strtotime($start));
            $ende_formatted = $ende ? date('m/y', strtotime($ende)) : 'heute';

            return $start_formatted . ' - ' . $ende_formatted;
        }

        /**
         * Sortiert Positionen nach Typ
         */
        private function sortiere_positionen($positionen) {
            $sortiert = [];
            foreach ($positionen as $pos) {
                $typ = $pos['position_typ'] ?? 'beisitzer';
                if (!isset($sortiert[$typ])) {
                    $sortiert[$typ] = [];
                }
                $sortiert[$typ][] = $pos;
            }
            return $sortiert;
        }

        /**
         * Rendert eine Liste von Personen
         */
        private function render_personen_liste($positionen, $show_vita) {
            if (empty($positionen)) {
                return '<span class="dgptm-keine-angabe">-</span>';
            }

            $namen = [];
            foreach ($positionen as $pos) {
                $person_id = $pos['position_person'];
                if (!$person_id) continue;

                $person = get_post($person_id);
                if (!$person) continue;

                $name = $person->post_title;
                $titel = get_field('person_titel', $person_id);
                if ($titel) {
                    $name = $titel . ' ' . $name;
                }

                $ausgeschieden = !empty($pos['position_ausgeschieden']);
                $hat_vita = !empty($person->post_content);

                $classes = ['dgptm-person'];
                if ($ausgeschieden) {
                    $classes[] = 'dgptm-ausgeschieden';
                }
                if ($show_vita && $hat_vita) {
                    $classes[] = 'dgptm-has-vita';
                }

                $html = '<span class="' . esc_attr(implode(' ', $classes)) . '"';
                if ($show_vita && $hat_vita) {
                    $html .= ' data-person-id="' . esc_attr($person_id) . '"';
                    $html .= ' title="Vita anzeigen"';
                }
                $html .= '>' . esc_html($name);

                if ($show_vita && $hat_vita) {
                    $html .= ' <span class="dashicons dashicons-info-outline dgptm-vita-icon"></span>';
                }

                $html .= '</span>';

                $namen[] = $html;
            }

            return implode(', ', $namen);
        }

        /**
         * Ermittelt ausgeschiedene Mitglieder
         */
        private function get_ausgeschiedene($positionen) {
            $ausgeschiedene = [];
            foreach ($positionen as $pos) {
                if (empty($pos['position_ausgeschieden'])) continue;

                $person_id = $pos['position_person'];
                if (!$person_id) continue;

                $person = get_post($person_id);
                if (!$person) continue;

                $datum = $pos['position_ausgeschieden_datum'] ?? '';
                $datum_formatted = $datum ? date('m/Y', strtotime($datum)) : '';

                $ausgeschiedene[] = [
                    'name' => $person->post_title,
                    'datum' => $datum_formatted,
                    'notiz' => $pos['position_notiz'] ?? '',
                ];
            }
            return $ausgeschiedene;
        }

        /**
         * Admin-Spalten für Perioden-Liste
         */
        public function add_admin_columns($columns) {
            $new_columns = [];
            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;
                if ($key === 'title') {
                    $new_columns['zeitraum'] = 'Zeitraum';
                    $new_columns['praesident'] = 'Präsident';
                }
            }
            return $new_columns;
        }

        /**
         * Rendert Admin-Spalten
         */
        public function render_admin_columns($column, $post_id) {
            switch ($column) {
                case 'zeitraum':
                    $start = get_field('periode_start', $post_id);
                    $ende = get_field('periode_ende', $post_id);
                    echo esc_html($this->format_zeitraum($start, $ende));
                    break;

                case 'praesident':
                    $positionen = get_field('positionen', $post_id) ?: [];
                    foreach ($positionen as $pos) {
                        if (($pos['position_typ'] ?? '') === 'praesident') {
                            $person_id = $pos['position_person'];
                            if ($person_id) {
                                $person = get_post($person_id);
                                if ($person) {
                                    echo esc_html($person->post_title);
                                }
                            }
                            break;
                        }
                    }
                    break;
            }
        }

        /**
         * AJAX: Vita abrufen
         */
        public function ajax_get_vita() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            $person_id = intval($_POST['person_id'] ?? 0);
            if (!$person_id) {
                wp_send_json_error(['message' => 'Ungültige Person']);
            }

            $person = get_post($person_id);
            if (!$person || $person->post_type !== 'vorstand_person') {
                wp_send_json_error(['message' => 'Person nicht gefunden']);
            }

            $titel = get_field('person_titel', $person_id);
            $name = $titel ? $titel . ' ' . $person->post_title : $person->post_title;
            $foto = get_the_post_thumbnail_url($person_id, 'medium');
            $vita = apply_filters('the_content', $person->post_content);

            wp_send_json_success([
                'name' => $name,
                'foto' => $foto,
                'vita' => $vita,
            ]);
        }

        /**
         * Fügt Import-Menü hinzu
         */
        public function add_import_menu() {
            add_submenu_page(
                'edit.php?post_type=vorstand_periode',
                'CSV-Import',
                'CSV-Import',
                'manage_options',
                'vorstandsliste-import',
                [$this, 'render_import_page']
            );
        }

        /**
         * Rendert Import-Seite
         */
        public function render_import_page() {
            ?>
            <div class="wrap">
                <h1>Vorstandsliste CSV-Import</h1>
                <p>Importieren Sie die historische Vorstandsliste aus einer CSV-Datei.</p>

                <form method="post" enctype="multipart/form-data" id="dgptm-import-form">
                    <?php wp_nonce_field('dgptm_import_vorstandsliste', 'import_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="csv_file">CSV-Datei</label></th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv">
                                <p class="description">
                                    Format: Amtsperiode;Präsident;Vizepräsident;Schriftführer;Schatzmeister;Beisitzer1;Beisitzer2;Beisitzer3
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="skip_header">Kopfzeile überspringen</label></th>
                            <td>
                                <input type="checkbox" name="skip_header" id="skip_header" value="1" checked>
                                <span class="description">Die ersten 2 Zeilen (Titel + Header) überspringen</span>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Import starten</button>
                    </p>
                </form>

                <div id="dgptm-import-results" style="display:none;">
                    <h2>Import-Ergebnis</h2>
                    <div id="dgptm-import-log"></div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                $('#dgptm-import-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    formData.append('action', 'dgptm_import_vorstandsliste');

                    $('#dgptm-import-results').show();
                    $('#dgptm-import-log').html('<p>Import läuft...</p>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                $('#dgptm-import-log').html(response.data.log);
                            } else {
                                $('#dgptm-import-log').html('<p class="error">' + response.data.message + '</p>');
                            }
                        },
                        error: function() {
                            $('#dgptm-import-log').html('<p class="error">Fehler beim Import</p>');
                        }
                    });
                });
            });
            </script>
            <?php
        }

        /**
         * AJAX: CSV-Import
         */
        public function ajax_import_csv() {
            check_ajax_referer('dgptm_import_vorstandsliste', 'import_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            if (empty($_FILES['csv_file']['tmp_name'])) {
                wp_send_json_error(['message' => 'Keine Datei hochgeladen']);
            }

            $file = $_FILES['csv_file']['tmp_name'];
            $skip_header = !empty($_POST['skip_header']);

            // CSV lesen mit korrekter Kodierung
            $content = file_get_contents($file);
            // Von Windows-1252 zu UTF-8 konvertieren
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            $lines = explode("\n", $content);

            $log = '<ul>';
            $personen_cache = []; // Name => Post ID
            $imported_perioden = 0;
            $imported_personen = 0;

            $start_line = $skip_header ? 2 : 0;

            for ($i = $start_line; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;

                // Prüfen ob es eine Datenzeile ist (beginnt mit Datum-Format)
                if (!preg_match('/^\d{2}\/\d{2}/', $line)) {
                    continue;
                }

                $cols = str_getcsv($line, ';');
                if (count($cols) < 5) continue;

                $zeitraum = trim($cols[0]);
                $praesident = trim($cols[1] ?? '');
                $vize = trim($cols[2] ?? '');
                $schriftfuehrer = trim($cols[3] ?? '');
                $schatzmeister = trim($cols[4] ?? '');
                $beisitzer1 = trim($cols[5] ?? '');
                $beisitzer2 = trim($cols[6] ?? '');
                $beisitzer3 = trim($cols[7] ?? '');

                // Zeitraum parsen (Format: "06/71–01/72" oder "01/16-12/18")
                $zeitraum_clean = str_replace(['–', '—'], '-', $zeitraum);
                $teile = explode('-', $zeitraum_clean);

                if (count($teile) >= 2) {
                    $start = $this->parse_datum(trim($teile[0]));
                    $ende = $this->parse_datum(trim($teile[1]));
                } else {
                    continue;
                }

                // Periode erstellen
                $periode_title = $zeitraum;
                $periode_id = wp_insert_post([
                    'post_type' => 'vorstand_periode',
                    'post_title' => $periode_title,
                    'post_status' => 'publish',
                ]);

                if (is_wp_error($periode_id)) {
                    $log .= '<li class="error">Fehler bei Periode ' . esc_html($zeitraum) . '</li>';
                    continue;
                }

                // Datum speichern
                update_field('periode_start', $start, $periode_id);
                update_field('periode_ende', $ende, $periode_id);

                // Positionen hinzufügen
                $positionen = [];

                $position_mapping = [
                    'praesident' => $praesident,
                    'vizepraesident' => $vize,
                    'schriftfuehrer' => $schriftfuehrer,
                    'schatzmeister' => $schatzmeister,
                ];

                foreach ($position_mapping as $typ => $name) {
                    if (empty($name) || $name === '-') continue;

                    $person_id = $this->get_or_create_person($name, $personen_cache, $imported_personen);
                    if ($person_id) {
                        $positionen[] = [
                            'position_typ' => $typ,
                            'position_person' => $person_id,
                            'position_ausgeschieden' => false,
                            'position_notiz' => '',
                        ];
                    }
                }

                // Beisitzer
                foreach ([$beisitzer1, $beisitzer2, $beisitzer3] as $beisitzer) {
                    if (empty($beisitzer) || $beisitzer === '-') continue;

                    $person_id = $this->get_or_create_person($beisitzer, $personen_cache, $imported_personen);
                    if ($person_id) {
                        $positionen[] = [
                            'position_typ' => 'beisitzer',
                            'position_person' => $person_id,
                            'position_ausgeschieden' => false,
                            'position_notiz' => '',
                        ];
                    }
                }

                update_field('positionen', $positionen, $periode_id);
                $imported_perioden++;

                $log .= '<li>Periode ' . esc_html($zeitraum) . ' importiert (' . count($positionen) . ' Positionen)</li>';
            }

            $log .= '</ul>';
            $log .= '<p><strong>Zusammenfassung:</strong> ' . $imported_perioden . ' Perioden, ' . $imported_personen . ' neue Personen importiert.</p>';

            wp_send_json_success(['log' => $log]);
        }

        /**
         * Parst Datum im Format "MM/YY" zu "YYYY-MM-01"
         */
        private function parse_datum($datum) {
            // Format: "06/71" oder "12/2024"
            $teile = explode('/', $datum);
            if (count($teile) !== 2) {
                return '';
            }

            $monat = str_pad($teile[0], 2, '0', STR_PAD_LEFT);
            $jahr = $teile[1];

            // 2-stelliges Jahr konvertieren
            if (strlen($jahr) === 2) {
                $jahr_int = intval($jahr);
                // Annahme: 00-30 = 2000-2030, 31-99 = 1931-1999
                $jahr = ($jahr_int <= 30) ? '20' . $jahr : '19' . $jahr;
            }

            return $jahr . '-' . $monat . '-01';
        }

        /**
         * Holt oder erstellt eine Person
         */
        private function get_or_create_person($name, &$cache, &$counter) {
            $name = trim($name);
            if (empty($name)) return null;

            // Name normalisieren (Punkt am Ende entfernen, etc.)
            $name = rtrim($name, '.');
            $name = trim($name);

            // Cache prüfen
            if (isset($cache[$name])) {
                return $cache[$name];
            }

            // Existierende Person suchen
            $existing = get_posts([
                'post_type' => 'vorstand_person',
                'title' => $name,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);

            if (!empty($existing)) {
                $cache[$name] = $existing[0]->ID;
                return $existing[0]->ID;
            }

            // Neue Person erstellen
            $person_id = wp_insert_post([
                'post_type' => 'vorstand_person',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);

            if (!is_wp_error($person_id)) {
                $cache[$name] = $person_id;
                $counter++;
                return $person_id;
            }

            return null;
        }

        /**
         * Flush Rewrite Rules bei Bedarf
         */
        public function maybe_flush_rewrite_rules() {
            $flush_key = 'dgptm_vorstandsliste_flush_done_100';

            if (get_option($flush_key) !== 'yes') {
                flush_rewrite_rules();
                update_option($flush_key, 'yes');
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_vorstandsliste_initialized'])) {
    $GLOBALS['dgptm_vorstandsliste_initialized'] = true;
    DGPTM_Vorstandsliste::get_instance();
}
