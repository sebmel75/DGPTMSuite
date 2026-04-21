<?php
/**
 * Repository für Webinar-Daten.
 *
 * Einziger Ort, der ACF-Felder liest/schreibt und Stats aggregiert.
 * Löst das N+1-Problem der bisherigen ReflectionMethod-Aufrufe.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Webinar_Repository')) {

    class DGPTM_VW_Webinar_Repository {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {}

        /**
         * Alle publizierten Webinare mit ACF-Feldern.
         *
         * @return array[] Liste mit keys: id, title, description, vimeo_id,
         *                                  ebcp_points, completion_percentage, vnr
         */
        public function get_all(): array {
            $posts = get_posts([
                'post_type'      => 'vimeo_webinar',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            $out = [];
            foreach ($posts as $p) {
                $out[] = $this->map_post($p);
            }
            return $out;
        }

        /**
         * Einzelnes Webinar laden.
         */
        public function get(int $id): ?array {
            $p = get_post($id);
            if (!$p || $p->post_type !== 'vimeo_webinar') return null;
            return $this->map_post($p);
        }

        private function map_post(\WP_Post $p): array {
            return [
                'id'                     => $p->ID,
                'title'                  => $p->post_title,
                'description'            => $p->post_content,
                'vimeo_id'               => (string) get_field('vimeo_id', $p->ID),
                'ebcp_points'            => (float) (get_field('ebcp_points', $p->ID) ?: 1),
                'completion_percentage'  => (int) (get_field('completion_percentage', $p->ID) ?: 90),
                'vnr'                    => (string) get_field('vnr', $p->ID),
                'webinar_date'           => (string) get_field('webinar_date', $p->ID),
            ];
        }

        /**
         * Batch-Stats für viele Webinare mit EINER User-Meta-Abfrage.
         *
         * @param int[] $ids Webinar-IDs
         * @return array{int, array{completed:int, in_progress:int, total_views:int}}
         */
        public function get_stats_batch(array $ids): array {
            global $wpdb;

            $result = [];
            foreach ($ids as $id) {
                $result[(int) $id] = ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
            }
            if (empty($ids)) return $result;

            // Abschlüsse (_vw_completed_{id} = 1)
            $completed_placeholders = [];
            foreach ($ids as $id) {
                $completed_placeholders[] = '_vw_completed_' . intval($id);
            }
            $in_clause = "'" . implode("','", array_map('esc_sql', $completed_placeholders)) . "'";

            $completed_rows = $wpdb->get_results(
                "SELECT meta_key, COUNT(*) AS cnt FROM {$wpdb->usermeta}
                 WHERE meta_key IN ($in_clause) AND meta_value = '1'
                 GROUP BY meta_key",
                ARRAY_A
            );
            foreach ($completed_rows as $row) {
                $id = (int) str_replace('_vw_completed_', '', $row['meta_key']);
                if (isset($result[$id])) $result[$id]['completed'] = (int) $row['cnt'];
            }

            // Fortschritt > 0 und nicht abgeschlossen = In Bearbeitung
            $progress_placeholders = [];
            foreach ($ids as $id) {
                $progress_placeholders[] = '_vw_progress_' . intval($id);
            }
            $in_clause_p = "'" . implode("','", array_map('esc_sql', $progress_placeholders)) . "'";

            $progress_rows = $wpdb->get_results(
                "SELECT meta_key, COUNT(*) AS cnt FROM {$wpdb->usermeta}
                 WHERE meta_key IN ($in_clause_p) AND CAST(meta_value AS DECIMAL(5,2)) > 0
                 GROUP BY meta_key",
                ARRAY_A
            );
            foreach ($progress_rows as $row) {
                $id = (int) str_replace('_vw_progress_', '', $row['meta_key']);
                if (isset($result[$id])) {
                    $views = (int) $row['cnt'];
                    $completed = $result[$id]['completed'];
                    $result[$id]['total_views'] = $views;
                    $result[$id]['in_progress'] = max(0, $views - $completed);
                }
            }

            return $result;
        }

        /**
         * Aggregat: gewichtete Durchschnitts-Abschlussrate über alle Webinare.
         */
        public function get_average_completion_rate(): float {
            $ids = wp_list_pluck($this->get_all(), 'id');
            $stats = $this->get_stats_batch($ids);

            $total_completed = 0;
            $total_views = 0;
            foreach ($stats as $s) {
                $total_completed += $s['completed'];
                $total_views += $s['total_views'];
            }
            if ($total_views === 0) return 0.0;
            return round($total_completed / $total_views * 100, 1);
        }

        /**
         * Anlegen oder aktualisieren.
         *
         * @param array $data  Erwartet: post_id (0=create), title, description, vimeo_id,
         *                     completion_percentage, points, vnr
         * @return int|WP_Error Post-ID oder Fehler
         */
        public function save(array $data) {
            $post_id = intval($data['post_id'] ?? 0);
            $title = sanitize_text_field($data['title'] ?? '');
            $description = wp_kses_post($data['description'] ?? '');
            $vimeo_id = sanitize_text_field($data['vimeo_id'] ?? '');
            $completion = max(1, min(100, intval($data['completion_percentage'] ?? 90)));
            $points = floatval($data['points'] ?? 1);
            $vnr = sanitize_text_field($data['vnr'] ?? '');
            $webinar_date = sanitize_text_field($data['webinar_date'] ?? '');
            // Nur YYYY-MM-DD akzeptieren; sonst leer
            if ($webinar_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $webinar_date)) {
                $webinar_date = '';
            }

            if (empty($title)) return new WP_Error('empty_title', 'Titel fehlt');
            if (empty($vimeo_id)) return new WP_Error('empty_vimeo_id', 'Vimeo-ID fehlt');

            $postarr = [
                'post_type'    => 'vimeo_webinar',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $description,
            ];
            if ($post_id > 0) {
                $postarr['ID'] = $post_id;
                $result = wp_update_post($postarr, true);
            } else {
                $result = wp_insert_post($postarr, true);
            }
            if (is_wp_error($result)) return $result;

            update_field('vimeo_id', $vimeo_id, $result);
            update_field('completion_percentage', $completion, $result);
            update_field('ebcp_points', $points, $result);
            update_field('vnr', $vnr, $result);
            update_field('webinar_date', $webinar_date, $result);

            return (int) $result;
        }

        /**
         * Trash (reversibel). Kein Force-Delete.
         */
        public function trash(int $id): bool {
            return (bool) wp_trash_post($id);
        }
    }
}
