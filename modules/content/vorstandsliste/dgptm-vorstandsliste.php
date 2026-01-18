<?php
/**
 * Plugin Name: DGPTM Vorstandsliste
 * Description: Zeigt die historische Vorstandsliste mit allen Amtsperioden. Shortcode: [dgptm_vorstandsliste]
 * Version: 2.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-vorstandsliste
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_VORSTANDSLISTE_VERSION', '2.1.0');
define('DGPTM_VORSTANDSLISTE_PATH', plugin_dir_path(__FILE__));
define('DGPTM_VORSTANDSLISTE_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Vorstandsliste')) {

    class DGPTM_Vorstandsliste {

        private static $instance = null;

        private $positionen = [
            'praesident' => 'Präsident',
            'vizepraesident' => 'Vizepräsident',
            'schatzmeister' => 'Schatzmeister',
            'schriftfuehrer' => 'Schriftführer',
            'beisitzer' => 'Beisitzer',
        ];

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('init', [$this, 'register_post_types'], 5);
            add_action('acf/init', [$this, 'register_acf_fields']);
            add_action('wp_enqueue_scripts', [$this, 'register_assets']);
            add_shortcode('dgptm_vorstandsliste', [$this, 'render_shortcode']);
            add_shortcode('dgptm_aktueller_vorstand', [$this, 'render_aktueller_vorstand']);

            // Admin
            add_filter('manage_vorstand_periode_posts_columns', [$this, 'add_admin_columns']);
            add_action('manage_vorstand_periode_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
            add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
            add_action('admin_menu', [$this, 'add_import_menu']);

            // AJAX Handlers
            add_action('wp_ajax_dgptm_import_vorstandsliste', [$this, 'ajax_import_csv']);
            add_action('wp_ajax_dgptm_get_vita', [$this, 'ajax_get_vita']);
            add_action('wp_ajax_nopriv_dgptm_get_vita', [$this, 'ajax_get_vita']);
            add_action('wp_ajax_dgptm_get_periode', [$this, 'ajax_get_periode']);
            add_action('wp_ajax_dgptm_save_periode', [$this, 'ajax_save_periode']);
            add_action('wp_ajax_dgptm_get_person', [$this, 'ajax_get_person']);
            add_action('wp_ajax_dgptm_save_person', [$this, 'ajax_save_person']);
            add_action('wp_ajax_dgptm_search_persons', [$this, 'ajax_search_persons']);
            add_action('wp_ajax_dgptm_create_person', [$this, 'ajax_create_person']);
            add_action('wp_ajax_dgptm_delete_periode', [$this, 'ajax_delete_periode']);
        }

        public function register_post_types() {
            if (!post_type_exists('vorstand_person')) {
                register_post_type('vorstand_person', [
                    'labels' => [
                        'name' => 'Vorstandsmitglieder',
                        'singular_name' => 'Vorstandsmitglied',
                        'menu_name' => 'Mitglieder',
                    ],
                    'public' => false,
                    'publicly_queryable' => false,
                    'show_ui' => true,
                    'show_in_menu' => 'edit.php?post_type=vorstand_periode',
                    'capability_type' => 'post',
                    'supports' => ['title', 'editor', 'thumbnail'],
                    'show_in_rest' => true,
                ]);
            }

            if (!post_type_exists('vorstand_periode')) {
                register_post_type('vorstand_periode', [
                    'labels' => [
                        'name' => 'Amtsperioden',
                        'singular_name' => 'Amtsperiode',
                        'menu_name' => 'Vorstandsliste',
                    ],
                    'public' => false,
                    'publicly_queryable' => false,
                    'show_ui' => true,
                    'show_in_menu' => true,
                    'menu_position' => 26,
                    'menu_icon' => 'dashicons-groups',
                    'capability_type' => 'post',
                    'supports' => ['title', 'thumbnail'],
                    'show_in_rest' => true,
                ]);
            }
        }

        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) {
                return;
            }

            acf_add_local_field_group([
                'key' => 'group_vorstand_person',
                'title' => 'Mitglied-Details',
                'fields' => [
                    [
                        'key' => 'field_person_titel',
                        'label' => 'Titel',
                        'name' => 'person_titel',
                        'type' => 'text',
                        'instructions' => 'z.B. Prof. Dr., Dr. med., M.Sc.',
                        'wrapper' => ['width' => '30'],
                    ],
                    [
                        'key' => 'field_person_klinik',
                        'label' => 'Klinik / Arbeitsstätte',
                        'name' => 'person_klinik',
                        'type' => 'text',
                        'instructions' => 'Klinik oder Institution',
                        'wrapper' => ['width' => '70'],
                    ],
                ],
                'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'vorstand_person']]],
            ]);

            acf_add_local_field_group([
                'key' => 'group_vorstand_periode',
                'title' => 'Amtsperiode-Details',
                'fields' => [
                    ['key' => 'field_periode_start', 'label' => 'Beginn', 'name' => 'periode_start', 'type' => 'date_picker', 'display_format' => 'm/Y', 'return_format' => 'Y-m-d', 'wrapper' => ['width' => '50']],
                    ['key' => 'field_periode_ende', 'label' => 'Ende', 'name' => 'periode_ende', 'type' => 'date_picker', 'display_format' => 'm/Y', 'return_format' => 'Y-m-d', 'wrapper' => ['width' => '50']],
                    ['key' => 'field_periode_notiz', 'label' => 'Notiz', 'name' => 'periode_notiz', 'type' => 'textarea', 'rows' => 2],
                    [
                        'key' => 'field_positionen',
                        'label' => 'Positionen',
                        'name' => 'positionen',
                        'type' => 'repeater',
                        'layout' => 'block',
                        'button_label' => 'Position hinzufügen',
                        'sub_fields' => [
                            ['key' => 'field_position_typ', 'label' => 'Position', 'name' => 'position_typ', 'type' => 'select', 'choices' => $this->positionen, 'wrapper' => ['width' => '25']],
                            ['key' => 'field_position_person', 'label' => 'Person', 'name' => 'position_person', 'type' => 'post_object', 'post_type' => ['vorstand_person'], 'return_format' => 'id', 'wrapper' => ['width' => '35']],
                            ['key' => 'field_position_ausgeschieden', 'label' => 'Ausgeschieden', 'name' => 'position_ausgeschieden', 'type' => 'true_false', 'ui' => 1, 'wrapper' => ['width' => '15']],
                            ['key' => 'field_position_ausgeschieden_datum', 'label' => 'Datum', 'name' => 'position_ausgeschieden_datum', 'type' => 'date_picker', 'display_format' => 'm/Y', 'return_format' => 'Y-m-d', 'conditional_logic' => [[['field' => 'field_position_ausgeschieden', 'operator' => '==', 'value' => '1']]], 'wrapper' => ['width' => '25']],
                            ['key' => 'field_position_notiz', 'label' => 'Notiz', 'name' => 'position_notiz', 'type' => 'text'],
                        ],
                    ],
                ],
                'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'vorstand_periode']]],
            ]);
        }

        public function kann_bearbeiten() {
            if (!is_user_logged_in()) return false;
            if (current_user_can('manage_options') || current_user_can('edit_pages')) return true;
            $user_id = get_current_user_id();
            return !empty(get_user_meta($user_id, 'timeline', true));
        }

        public function register_assets() {
            wp_register_style('dgptm-vorstandsliste', DGPTM_VORSTANDSLISTE_URL . 'assets/css/vorstandsliste.css', [], DGPTM_VORSTANDSLISTE_VERSION);
            wp_register_script('dgptm-vorstandsliste', DGPTM_VORSTANDSLISTE_URL . 'assets/js/vorstandsliste.js', ['jquery'], DGPTM_VORSTANDSLISTE_VERSION, true);
        }

        public function render_shortcode($atts) {
            $atts = shortcode_atts([
                'order' => 'DESC',
                'expanded' => 'first', // first, all, none
            ], $atts);

            wp_enqueue_style('dgptm-vorstandsliste');
            wp_enqueue_script('dgptm-vorstandsliste');

            $kann_bearbeiten = $this->kann_bearbeiten();

            wp_localize_script('dgptm-vorstandsliste', 'dgptmVorstandsliste', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_vorstandsliste_nonce'),
                'canEdit' => $kann_bearbeiten,
                'positionen' => $this->positionen,
            ]);

            $perioden = get_posts([
                'post_type' => 'vorstand_periode',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => 'periode_start',
                'orderby' => 'meta_value',
                'order' => strtoupper($atts['order']),
            ]);

            ob_start();
            ?>
            <div class="dgptm-vorstandsliste" data-expanded="<?php echo esc_attr($atts['expanded']); ?>">

                <?php if ($kann_bearbeiten): ?>
                <div class="dgptm-vl-toolbar">
                    <button type="button" class="dgptm-vl-btn dgptm-vl-btn-primary" id="dgptm-add-periode">
                        <span class="dgptm-vl-icon">+</span> Neue Amtsperiode
                    </button>
                </div>
                <?php endif; ?>

                <div class="dgptm-vl-accordion">
                    <?php
                    $first = true;
                    foreach ($perioden as $periode):
                        $is_expanded = ($atts['expanded'] === 'all') || ($atts['expanded'] === 'first' && $first);
                        echo $this->render_accordion_item($periode, $kann_bearbeiten, $is_expanded);
                        $first = false;
                    endforeach;
                    ?>
                </div>

                <?php if (empty($perioden)): ?>
                    <p class="dgptm-vl-empty">Keine Amtsperioden vorhanden.</p>
                <?php endif; ?>

                <?php echo $this->render_modals($kann_bearbeiten); ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Shortcode: [dgptm_aktueller_vorstand]
         * Zeigt den aktuellen Vorstand in einer Karten-Darstellung mit Bild und ausklappbarer Vita
         */
        public function render_aktueller_vorstand($atts) {
            $atts = shortcode_atts([
                'columns' => '3', // Anzahl Spalten (2, 3 oder 4)
                'show_klinik' => 'yes',
                'show_vita' => 'yes',
            ], $atts);

            wp_enqueue_style('dgptm-vorstandsliste');
            wp_enqueue_script('dgptm-vorstandsliste');

            wp_localize_script('dgptm-vorstandsliste', 'dgptmVorstandsliste', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_vorstandsliste_nonce'),
                'canEdit' => false,
                'positionen' => $this->positionen,
            ]);

            // Aktuellste Periode holen (ohne Ende-Datum oder größtes Start-Datum)
            $aktuelle_periode = get_posts([
                'post_type' => 'vorstand_periode',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_key' => 'periode_start',
                'orderby' => 'meta_value',
                'order' => 'DESC',
            ]);

            if (empty($aktuelle_periode)) {
                return '<p class="dgptm-vl-empty">Kein aktueller Vorstand vorhanden.</p>';
            }

            $periode_id = $aktuelle_periode[0]->ID;
            $positionen = get_field('positionen', $periode_id) ?: [];

            // Positionen sortieren nach Reihenfolge
            $sortiert = [];
            $reihenfolge = ['praesident', 'vizepraesident', 'schatzmeister', 'schriftfuehrer', 'beisitzer'];

            foreach ($reihenfolge as $typ) {
                foreach ($positionen as $pos) {
                    if (($pos['position_typ'] ?? '') === $typ && empty($pos['position_ausgeschieden'])) {
                        $sortiert[] = $pos;
                    }
                }
            }

            ob_start();
            ?>
            <div class="dgptm-av-container dgptm-av-cols-<?php echo esc_attr($atts['columns']); ?>">
                <div class="dgptm-av-grid">
                    <?php foreach ($sortiert as $pos): ?>
                        <?php echo $this->render_vorstand_card($pos, $atts); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Vita Modal (wiederverwendet) -->
                <div id="dgptm-vl-vita-modal" class="dgptm-vl-modal" style="display:none;">
                    <div class="dgptm-vl-modal-overlay"></div>
                    <div class="dgptm-vl-modal-container dgptm-vl-modal-sm">
                        <div class="dgptm-vl-modal-header">
                            <h3 class="dgptm-vl-modal-title">Vita</h3>
                            <button type="button" class="dgptm-vl-modal-close">&times;</button>
                        </div>
                        <div class="dgptm-vl-modal-body">
                            <div class="dgptm-vl-vita-header">
                                <div class="dgptm-vl-vita-photo"></div>
                                <div class="dgptm-vl-vita-name"></div>
                            </div>
                            <div class="dgptm-vl-vita-content"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Rendert eine einzelne Vorstandskarte
         */
        private function render_vorstand_card($pos, $atts) {
            $person_id = $pos['position_person'] ?? 0;
            if (!$person_id) return '';

            $person = get_post($person_id);
            if (!$person) return '';

            $name = $person->post_title;
            $titel = get_field('person_titel', $person_id);
            $klinik = get_field('person_klinik', $person_id);
            $position_label = $this->positionen[$pos['position_typ']] ?? $pos['position_typ'];
            $foto = get_the_post_thumbnail_url($person_id, 'medium');
            $hat_vita = !empty($person->post_content);
            $show_vita = $atts['show_vita'] === 'yes' && $hat_vita;

            $vollstaendiger_name = $titel ? $titel . ' ' . $name : $name;

            ob_start();
            ?>
            <div class="dgptm-av-card<?php echo $show_vita ? ' has-vita' : ''; ?>"
                 <?php echo $show_vita ? 'data-person-id="' . esc_attr($person_id) . '"' : ''; ?>>
                <div class="dgptm-av-card-image">
                    <?php if ($foto): ?>
                        <img src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr($vollstaendiger_name); ?>">
                    <?php else: ?>
                        <div class="dgptm-av-card-placeholder">
                            <svg viewBox="0 0 24 24" width="48" height="48">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="dgptm-av-card-content">
                    <div class="dgptm-av-card-position"><?php echo esc_html($position_label); ?></div>
                    <h3 class="dgptm-av-card-name"><?php echo esc_html($vollstaendiger_name); ?></h3>
                    <?php if ($atts['show_klinik'] === 'yes' && $klinik): ?>
                        <p class="dgptm-av-card-klinik"><?php echo esc_html($klinik); ?></p>
                    <?php endif; ?>
                    <?php if ($show_vita): ?>
                        <button type="button" class="dgptm-av-card-vita-btn dgptm-vl-person has-vita" data-person-id="<?php echo esc_attr($person_id); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4m0-4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Vita anzeigen
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_accordion_item($periode, $kann_bearbeiten, $is_expanded = false) {
            $id = $periode->ID;
            $start = get_field('periode_start', $id);
            $ende = get_field('periode_ende', $id);
            $positionen = get_field('positionen', $id) ?: [];
            $notiz = get_field('periode_notiz', $id);
            $thumbnail = get_the_post_thumbnail_url($id, 'medium');

            $zeitraum = $this->format_zeitraum($start, $ende);
            $praesident = $this->get_position_name($positionen, 'praesident');
            $expanded_class = $is_expanded ? 'expanded' : '';

            ob_start();
            ?>
            <div class="dgptm-vl-item <?php echo $expanded_class; ?>" data-periode-id="<?php echo $id; ?>">
                <div class="dgptm-vl-header">
                    <div class="dgptm-vl-header-main">
                        <span class="dgptm-vl-toggle">
                            <svg class="dgptm-vl-chevron" viewBox="0 0 24 24" width="20" height="20">
                                <path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </span>
                        <span class="dgptm-vl-zeitraum"><?php echo esc_html($zeitraum); ?></span>
                        <span class="dgptm-vl-praesident"><?php echo esc_html($praesident); ?></span>
                    </div>
                    <div class="dgptm-vl-header-actions">
                        <?php if ($thumbnail): ?>
                            <button type="button" class="dgptm-vl-btn-icon dgptm-vl-foto-btn"
                                    data-foto="<?php echo esc_url(get_the_post_thumbnail_url($id, 'large')); ?>"
                                    data-caption="Vorstand <?php echo esc_attr($zeitraum); ?>"
                                    title="Foto anzeigen">
                                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($kann_bearbeiten): ?>
                            <button type="button" class="dgptm-vl-btn-icon dgptm-vl-edit-periode" title="Bearbeiten">
                                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dgptm-vl-content">
                    <?php echo $this->render_positionen_liste($positionen, $kann_bearbeiten); ?>

                    <?php if ($notiz): ?>
                        <div class="dgptm-vl-notiz">
                            <em><?php echo esc_html($notiz); ?></em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_positionen_liste($positionen, $kann_bearbeiten) {
            $sortiert = [];
            foreach ($positionen as $pos) {
                $typ = $pos['position_typ'] ?? 'beisitzer';
                if (!isset($sortiert[$typ])) $sortiert[$typ] = [];
                $sortiert[$typ][] = $pos;
            }

            ob_start();
            ?>
            <div class="dgptm-vl-positionen">
                <?php foreach (['praesident', 'vizepraesident', 'schatzmeister', 'schriftfuehrer', 'beisitzer'] as $typ): ?>
                    <?php if (!empty($sortiert[$typ])): ?>
                        <div class="dgptm-vl-position-row">
                            <span class="dgptm-vl-position-label"><?php echo esc_html($this->positionen[$typ]); ?></span>
                            <span class="dgptm-vl-position-persons">
                                <?php echo $this->render_personen($sortiert[$typ], $kann_bearbeiten); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_personen($positionen, $kann_bearbeiten) {
            $html = [];
            foreach ($positionen as $pos) {
                $person_id = $pos['position_person'];
                if (!$person_id) continue;

                $person = get_post($person_id);
                if (!$person) continue;

                $name = $person->post_title;
                $titel = get_field('person_titel', $person_id);
                if ($titel) $name = $titel . ' ' . $name;

                $ausgeschieden = !empty($pos['position_ausgeschieden']);
                $hat_vita = !empty($person->post_content);

                $classes = ['dgptm-vl-person'];
                if ($ausgeschieden) $classes[] = 'ausgeschieden';
                if ($hat_vita) $classes[] = 'has-vita';

                $item = '<span class="' . implode(' ', $classes) . '" data-person-id="' . $person_id . '">';
                $item .= esc_html($name);

                if ($ausgeschieden) {
                    $datum = $pos['position_ausgeschieden_datum'] ?? '';
                    if ($datum) {
                        $item .= ' <small>(bis ' . date('m/Y', strtotime($datum)) . ')</small>';
                    }
                }

                if ($hat_vita) {
                    $item .= ' <svg class="dgptm-vl-info-icon" viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4m0-4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                }

                $item .= '</span>';
                $html[] = $item;
            }
            return implode(', ', $html);
        }

        private function get_position_name($positionen, $typ) {
            foreach ($positionen as $pos) {
                if (($pos['position_typ'] ?? '') === $typ) {
                    $person_id = $pos['position_person'];
                    if ($person_id) {
                        $person = get_post($person_id);
                        if ($person) {
                            $name = $person->post_title;
                            $titel = get_field('person_titel', $person_id);
                            return $titel ? $titel . ' ' . $name : $name;
                        }
                    }
                }
            }
            return '';
        }

        private function format_zeitraum($start, $ende) {
            if (!$start) return 'Unbekannt';
            $s = date('m/Y', strtotime($start));
            $e = $ende ? date('m/Y', strtotime($ende)) : 'heute';
            return $s . ' – ' . $e;
        }

        private function render_modals($kann_bearbeiten) {
            ob_start();
            ?>
            <!-- Vita Modal -->
            <div id="dgptm-vl-vita-modal" class="dgptm-vl-modal" style="display:none;">
                <div class="dgptm-vl-modal-overlay"></div>
                <div class="dgptm-vl-modal-container dgptm-vl-modal-sm">
                    <div class="dgptm-vl-modal-header">
                        <h3 class="dgptm-vl-modal-title">Vita</h3>
                        <button type="button" class="dgptm-vl-modal-close">&times;</button>
                    </div>
                    <div class="dgptm-vl-modal-body">
                        <div class="dgptm-vl-vita-header">
                            <div class="dgptm-vl-vita-photo"></div>
                            <div class="dgptm-vl-vita-name"></div>
                        </div>
                        <div class="dgptm-vl-vita-content"></div>
                    </div>
                </div>
            </div>

            <!-- Foto Lightbox -->
            <div id="dgptm-vl-foto-modal" class="dgptm-vl-modal dgptm-vl-lightbox" style="display:none;">
                <div class="dgptm-vl-modal-overlay"></div>
                <div class="dgptm-vl-lightbox-content">
                    <button type="button" class="dgptm-vl-modal-close">&times;</button>
                    <img src="" alt="" class="dgptm-vl-lightbox-img">
                    <p class="dgptm-vl-lightbox-caption"></p>
                </div>
            </div>

            <?php if ($kann_bearbeiten): ?>
            <!-- Periode Edit Modal -->
            <div id="dgptm-vl-periode-modal" class="dgptm-vl-modal" style="display:none;">
                <div class="dgptm-vl-modal-overlay"></div>
                <div class="dgptm-vl-modal-container dgptm-vl-modal-lg">
                    <div class="dgptm-vl-modal-header">
                        <h3 class="dgptm-vl-modal-title">Amtsperiode bearbeiten</h3>
                        <button type="button" class="dgptm-vl-modal-close">&times;</button>
                    </div>
                    <div class="dgptm-vl-modal-body">
                        <form id="dgptm-vl-periode-form">
                            <input type="hidden" name="periode_id" id="periode_id">

                            <div class="dgptm-vl-form-row">
                                <div class="dgptm-vl-form-group">
                                    <label>Beginn (MM/YYYY)</label>
                                    <input type="text" name="periode_start" id="periode_start" placeholder="01/2020" pattern="\d{2}/\d{4}">
                                </div>
                                <div class="dgptm-vl-form-group">
                                    <label>Ende (MM/YYYY)</label>
                                    <input type="text" name="periode_ende" id="periode_ende" placeholder="12/2023" pattern="\d{2}/\d{4}">
                                </div>
                            </div>

                            <div class="dgptm-vl-form-group">
                                <label>Notiz</label>
                                <textarea name="periode_notiz" id="periode_notiz" rows="2"></textarea>
                            </div>

                            <div class="dgptm-vl-form-group">
                                <label>Positionen</label>
                                <div id="dgptm-vl-positionen-list"></div>
                                <button type="button" class="dgptm-vl-btn dgptm-vl-btn-secondary" id="dgptm-add-position">
                                    + Position hinzufügen
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="dgptm-vl-modal-footer">
                        <button type="button" class="dgptm-vl-btn dgptm-vl-btn-danger" id="dgptm-delete-periode" style="margin-right:auto;">Löschen</button>
                        <button type="button" class="dgptm-vl-btn dgptm-vl-btn-secondary dgptm-vl-modal-cancel">Abbrechen</button>
                        <button type="button" class="dgptm-vl-btn dgptm-vl-btn-primary" id="dgptm-save-periode">Speichern</button>
                    </div>
                </div>
            </div>

            <!-- Person Edit Modal -->
            <div id="dgptm-vl-person-modal" class="dgptm-vl-modal" style="display:none;">
                <div class="dgptm-vl-modal-overlay"></div>
                <div class="dgptm-vl-modal-container dgptm-vl-modal-sm">
                    <div class="dgptm-vl-modal-header">
                        <h3 class="dgptm-vl-modal-title">Person bearbeiten</h3>
                        <button type="button" class="dgptm-vl-modal-close">&times;</button>
                    </div>
                    <div class="dgptm-vl-modal-body">
                        <form id="dgptm-vl-person-form">
                            <input type="hidden" name="person_id" id="person_edit_id">

                            <div class="dgptm-vl-form-group">
                                <label>Titel (Dr., Prof., etc.)</label>
                                <input type="text" name="person_titel" id="person_titel">
                            </div>

                            <div class="dgptm-vl-form-group">
                                <label>Name *</label>
                                <input type="text" name="person_name" id="person_name" required>
                            </div>

                            <div class="dgptm-vl-form-group">
                                <label>Vita</label>
                                <textarea name="person_vita" id="person_vita" rows="6"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="dgptm-vl-modal-footer">
                        <button type="button" class="dgptm-vl-btn dgptm-vl-btn-secondary dgptm-vl-modal-cancel">Abbrechen</button>
                        <button type="button" class="dgptm-vl-btn dgptm-vl-btn-primary" id="dgptm-save-person">Speichern</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php
            return ob_get_clean();
        }

        // ==================== AJAX HANDLERS ====================

        public function ajax_get_vita() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            $person_id = intval($_POST['person_id'] ?? 0);
            $person = get_post($person_id);

            if (!$person || $person->post_type !== 'vorstand_person') {
                wp_send_json_error(['message' => 'Person nicht gefunden']);
            }

            $titel = get_field('person_titel', $person_id);
            $name = $titel ? $titel . ' ' . $person->post_title : $person->post_title;

            wp_send_json_success([
                'name' => $name,
                'foto' => get_the_post_thumbnail_url($person_id, 'medium'),
                'vita' => apply_filters('the_content', $person->post_content),
            ]);
        }

        public function ajax_get_periode() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $periode_id = intval($_POST['periode_id'] ?? 0);

            if ($periode_id) {
                $periode = get_post($periode_id);
                if (!$periode || $periode->post_type !== 'vorstand_periode') {
                    wp_send_json_error(['message' => 'Periode nicht gefunden']);
                }

                $start = get_field('periode_start', $periode_id);
                $ende = get_field('periode_ende', $periode_id);
                $positionen_raw = get_field('positionen', $periode_id) ?: [];

                $positionen = [];
                foreach ($positionen_raw as $pos) {
                    $person_id = $pos['position_person'];
                    $person_name = '';
                    if ($person_id) {
                        $person = get_post($person_id);
                        if ($person) {
                            $titel = get_field('person_titel', $person_id);
                            $person_name = $titel ? $titel . ' ' . $person->post_title : $person->post_title;
                        }
                    }
                    $positionen[] = [
                        'typ' => $pos['position_typ'],
                        'person_id' => $person_id,
                        'person_name' => $person_name,
                        'ausgeschieden' => !empty($pos['position_ausgeschieden']),
                        'ausgeschieden_datum' => $pos['position_ausgeschieden_datum'] ?? '',
                        'notiz' => $pos['position_notiz'] ?? '',
                    ];
                }

                wp_send_json_success([
                    'id' => $periode_id,
                    'start' => $start ? date('m/Y', strtotime($start)) : '',
                    'ende' => $ende ? date('m/Y', strtotime($ende)) : '',
                    'notiz' => get_field('periode_notiz', $periode_id),
                    'positionen' => $positionen,
                ]);
            } else {
                // Neue Periode
                wp_send_json_success([
                    'id' => 0,
                    'start' => '',
                    'ende' => '',
                    'notiz' => '',
                    'positionen' => [],
                ]);
            }
        }

        public function ajax_save_periode() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $periode_id = intval($_POST['periode_id'] ?? 0);
            $start = sanitize_text_field($_POST['start'] ?? '');
            $ende = sanitize_text_field($_POST['ende'] ?? '');
            $notiz = sanitize_textarea_field($_POST['notiz'] ?? '');
            $positionen = json_decode(stripslashes($_POST['positionen'] ?? '[]'), true);

            // Datum parsen
            $start_date = $this->parse_datum_input($start);
            $ende_date = $this->parse_datum_input($ende);

            $zeitraum = ($start ? $start : '?') . ' – ' . ($ende ? $ende : 'heute');

            if ($periode_id) {
                wp_update_post(['ID' => $periode_id, 'post_title' => $zeitraum]);
            } else {
                $periode_id = wp_insert_post([
                    'post_type' => 'vorstand_periode',
                    'post_title' => $zeitraum,
                    'post_status' => 'publish',
                ]);
            }

            if (is_wp_error($periode_id)) {
                wp_send_json_error(['message' => 'Fehler beim Speichern']);
            }

            update_field('periode_start', $start_date, $periode_id);
            update_field('periode_ende', $ende_date, $periode_id);
            update_field('periode_notiz', $notiz, $periode_id);

            // Positionen speichern
            $acf_positionen = [];
            foreach ($positionen as $pos) {
                $acf_positionen[] = [
                    'position_typ' => sanitize_text_field($pos['typ'] ?? 'beisitzer'),
                    'position_person' => intval($pos['person_id'] ?? 0),
                    'position_ausgeschieden' => !empty($pos['ausgeschieden']),
                    'position_ausgeschieden_datum' => $this->parse_datum_input($pos['ausgeschieden_datum'] ?? ''),
                    'position_notiz' => sanitize_text_field($pos['notiz'] ?? ''),
                ];
            }
            update_field('positionen', $acf_positionen, $periode_id);

            // HTML für aktualisiertes Item zurückgeben
            $periode = get_post($periode_id);
            $html = $this->render_accordion_item($periode, true, true);

            wp_send_json_success([
                'periode_id' => $periode_id,
                'html' => $html,
            ]);
        }

        public function ajax_delete_periode() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $periode_id = intval($_POST['periode_id'] ?? 0);
            if (!$periode_id) {
                wp_send_json_error(['message' => 'Ungültige ID']);
            }

            wp_delete_post($periode_id, true);
            wp_send_json_success();
        }

        public function ajax_get_person() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $person_id = intval($_POST['person_id'] ?? 0);
            $person = get_post($person_id);

            if (!$person || $person->post_type !== 'vorstand_person') {
                wp_send_json_error(['message' => 'Person nicht gefunden']);
            }

            wp_send_json_success([
                'id' => $person_id,
                'name' => $person->post_title,
                'titel' => get_field('person_titel', $person_id),
                'vita' => $person->post_content,
            ]);
        }

        public function ajax_save_person() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $person_id = intval($_POST['person_id'] ?? 0);
            $name = sanitize_text_field($_POST['name'] ?? '');
            $titel = sanitize_text_field($_POST['titel'] ?? '');
            $vita = wp_kses_post($_POST['vita'] ?? '');

            if (empty($name)) {
                wp_send_json_error(['message' => 'Name ist erforderlich']);
            }

            if ($person_id) {
                wp_update_post([
                    'ID' => $person_id,
                    'post_title' => $name,
                    'post_content' => $vita,
                ]);
            } else {
                $person_id = wp_insert_post([
                    'post_type' => 'vorstand_person',
                    'post_title' => $name,
                    'post_content' => $vita,
                    'post_status' => 'publish',
                ]);
            }

            update_field('person_titel', $titel, $person_id);

            wp_send_json_success([
                'person_id' => $person_id,
                'name' => $titel ? $titel . ' ' . $name : $name,
            ]);
        }

        public function ajax_search_persons() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $search = sanitize_text_field($_POST['search'] ?? '');

            $persons = get_posts([
                'post_type' => 'vorstand_person',
                'posts_per_page' => 20,
                's' => $search,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC',
            ]);

            $results = [];
            foreach ($persons as $p) {
                $titel = get_field('person_titel', $p->ID);
                $results[] = [
                    'id' => $p->ID,
                    'name' => $titel ? $titel . ' ' . $p->post_title : $p->post_title,
                ];
            }

            wp_send_json_success($results);
        }

        public function ajax_create_person() {
            check_ajax_referer('dgptm_vorstandsliste_nonce', 'nonce');

            if (!$this->kann_bearbeiten()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $name = sanitize_text_field($_POST['name'] ?? '');
            if (empty($name)) {
                wp_send_json_error(['message' => 'Name ist erforderlich']);
            }

            $person_id = wp_insert_post([
                'post_type' => 'vorstand_person',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);

            wp_send_json_success([
                'id' => $person_id,
                'name' => $name,
            ]);
        }

        private function parse_datum_input($datum) {
            if (empty($datum)) return '';

            // Format: MM/YYYY
            if (preg_match('/^(\d{2})\/(\d{4})$/', $datum, $m)) {
                return $m[2] . '-' . $m[1] . '-01';
            }
            // Format: MM/YY
            if (preg_match('/^(\d{2})\/(\d{2})$/', $datum, $m)) {
                $year = intval($m[2]);
                $year = ($year <= 30) ? '20' . $m[2] : '19' . $m[2];
                return $year . '-' . $m[1] . '-01';
            }
            return '';
        }

        // ==================== ADMIN ====================

        public function add_admin_columns($columns) {
            $new = [];
            foreach ($columns as $k => $v) {
                $new[$k] = $v;
                if ($k === 'title') {
                    $new['zeitraum'] = 'Zeitraum';
                    $new['praesident'] = 'Präsident';
                }
            }
            return $new;
        }

        public function render_admin_columns($column, $post_id) {
            if ($column === 'zeitraum') {
                echo esc_html($this->format_zeitraum(
                    get_field('periode_start', $post_id),
                    get_field('periode_ende', $post_id)
                ));
            } elseif ($column === 'praesident') {
                $positionen = get_field('positionen', $post_id) ?: [];
                echo esc_html($this->get_position_name($positionen, 'praesident'));
            }
        }

        public function add_import_menu() {
            add_submenu_page('edit.php?post_type=vorstand_periode', 'CSV-Import', 'CSV-Import', 'manage_options', 'vorstandsliste-import', [$this, 'render_import_page']);
        }

        public function render_import_page() {
            ?>
            <div class="wrap">
                <h1>Vorstandsliste CSV-Import</h1>
                <form method="post" enctype="multipart/form-data" id="dgptm-import-form">
                    <?php wp_nonce_field('dgptm_import_vorstandsliste', 'import_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="csv_file">CSV-Datei</label></th>
                            <td><input type="file" name="csv_file" id="csv_file" accept=".csv"></td>
                        </tr>
                        <tr>
                            <th><label for="skip_header">Kopfzeile überspringen</label></th>
                            <td><input type="checkbox" name="skip_header" id="skip_header" value="1" checked></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary">Import</button></p>
                </form>
                <div id="dgptm-import-results" style="display:none;"><div id="dgptm-import-log"></div></div>
            </div>
            <script>
            jQuery('#dgptm-import-form').on('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fd.append('action', 'dgptm_import_vorstandsliste');
                jQuery('#dgptm-import-results').show();
                jQuery('#dgptm-import-log').html('<p>Import läuft...</p>');
                jQuery.ajax({url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false,
                    success: function(r) { jQuery('#dgptm-import-log').html(r.success ? r.data.log : r.data.message); }
                });
            });
            </script>
            <?php
        }

        public function ajax_import_csv() {
            check_ajax_referer('dgptm_import_vorstandsliste', 'import_nonce');
            if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Keine Berechtigung']);
            if (empty($_FILES['csv_file']['tmp_name'])) wp_send_json_error(['message' => 'Keine Datei']);

            $content = mb_convert_encoding(file_get_contents($_FILES['csv_file']['tmp_name']), 'UTF-8', 'Windows-1252');
            $lines = explode("\n", $content);
            $log = '<ul>';
            $cache = [];
            $cnt_p = $cnt_m = 0;

            for ($i = (!empty($_POST['skip_header']) ? 2 : 0); $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line) || !preg_match('/^\d{2}\/\d{2}/', $line)) continue;

                $c = str_getcsv($line, ';');
                if (count($c) < 5) continue;

                $zr = str_replace(['–','—'], '-', trim($c[0]));
                $t = explode('-', $zr);
                if (count($t) < 2) continue;

                $periode_id = wp_insert_post(['post_type' => 'vorstand_periode', 'post_title' => trim($c[0]), 'post_status' => 'publish']);
                if (is_wp_error($periode_id)) continue;

                update_field('periode_start', $this->parse_datum_input(trim($t[0])), $periode_id);
                update_field('periode_ende', $this->parse_datum_input(trim($t[1])), $periode_id);

                $pos = [];
                $map = ['praesident' => 1, 'vizepraesident' => 2, 'schriftfuehrer' => 3, 'schatzmeister' => 4];
                foreach ($map as $typ => $idx) {
                    $n = trim($c[$idx] ?? '');
                    if ($n && $n !== '-') {
                        $pid = $this->get_or_create_person($n, $cache, $cnt_m);
                        if ($pid) $pos[] = ['position_typ' => $typ, 'position_person' => $pid, 'position_ausgeschieden' => false, 'position_notiz' => ''];
                    }
                }
                for ($b = 5; $b <= 7; $b++) {
                    $n = trim($c[$b] ?? '');
                    if ($n && $n !== '-') {
                        $pid = $this->get_or_create_person($n, $cache, $cnt_m);
                        if ($pid) $pos[] = ['position_typ' => 'beisitzer', 'position_person' => $pid, 'position_ausgeschieden' => false, 'position_notiz' => ''];
                    }
                }
                update_field('positionen', $pos, $periode_id);
                $cnt_p++;
                $log .= '<li>' . esc_html(trim($c[0])) . '</li>';
            }

            $log .= '</ul><p><strong>' . $cnt_p . ' Perioden, ' . $cnt_m . ' Personen importiert.</strong></p>';
            wp_send_json_success(['log' => $log]);
        }

        private function get_or_create_person($name, &$cache, &$counter) {
            $name = rtrim(trim($name), '.');
            if (isset($cache[$name])) return $cache[$name];

            $ex = get_posts(['post_type' => 'vorstand_person', 'title' => $name, 'posts_per_page' => 1, 'post_status' => 'publish']);
            if (!empty($ex)) { $cache[$name] = $ex[0]->ID; return $ex[0]->ID; }

            $id = wp_insert_post(['post_type' => 'vorstand_person', 'post_title' => $name, 'post_status' => 'publish']);
            if (!is_wp_error($id)) { $cache[$name] = $id; $counter++; return $id; }
            return null;
        }

        public function maybe_flush_rewrite_rules() {
            if (get_option('dgptm_vorstandsliste_flush_200') !== 'yes') {
                flush_rewrite_rules();
                update_option('dgptm_vorstandsliste_flush_200', 'yes');
            }
        }
    }
}

if (!isset($GLOBALS['dgptm_vorstandsliste_initialized'])) {
    $GLOBALS['dgptm_vorstandsliste_initialized'] = true;
    DGPTM_Vorstandsliste::get_instance();
}
