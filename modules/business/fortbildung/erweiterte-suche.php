<?php
/**
 * Erweiterte Suchfunktion für Fortbildungen
 * - Admin-Filter für alle Spalten
 * - Frontend-Suchformular mit Kombinationsfiltern
 * - AJAX-basierte Suche
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * Admin-Bereich: Filter in der Fortbildungs-Liste
 * ============================================================ */

// Filter-Dropdowns im Admin hinzufügen
add_action( 'restrict_manage_posts', 'fobi_admin_filters' );
function fobi_admin_filters( $post_type ) {
    if ( 'fortbildung' !== $post_type ) return;
    
    // Filter nach Art/Typ
    $current_type = isset($_GET['fobi_type_filter']) ? sanitize_text_field($_GET['fobi_type_filter']) : '';
    $types = array();
    $type_query = new WP_Query(array(
        'post_type' => 'fortbildung',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    if ($type_query->have_posts()) {
        foreach ($type_query->posts as $pid) {
            $type = get_field('type', $pid);
            if ($type && !in_array($type, $types)) {
                $types[] = $type;
            }
        }
    }
    wp_reset_postdata();
    
    echo '<select name="fobi_type_filter">';
    echo '<option value="">Alle Arten</option>';
    foreach ($types as $type) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($type),
            selected($current_type, $type, false),
            esc_html($type)
        );
    }
    echo '</select>';
    
    // Filter nach Benutzer
    $current_user = isset($_GET['fobi_user_filter']) ? intval($_GET['fobi_user_filter']) : 0;
    $users = get_users(array('fields' => array('ID', 'display_name', 'first_name', 'last_name')));
    
    echo '<select name="fobi_user_filter">';
    echo '<option value="">Alle Benutzer</option>';
    foreach ($users as $user) {
        $name = trim($user->first_name . ' ' . $user->last_name);
        if (!$name) $name = $user->display_name;
        printf(
            '<option value="%d"%s>%s</option>',
            $user->ID,
            selected($current_user, $user->ID, false),
            esc_html($name)
        );
    }
    echo '</select>';
    
    // Filter nach Freigabe-Status
    $current_status = isset($_GET['fobi_status_filter']) ? sanitize_text_field($_GET['fobi_status_filter']) : '';
    echo '<select name="fobi_status_filter">';
    echo '<option value="">Alle Status</option>';
    echo '<option value="approved"' . selected($current_status, 'approved', false) . '>Freigegeben</option>';
    echo '<option value="pending"' . selected($current_status, 'pending', false) . '>Nicht freigegeben</option>';
    echo '</select>';
    
    // Datums-Filter (Jahr)
    $current_year = isset($_GET['fobi_year_filter']) ? intval($_GET['fobi_year_filter']) : 0;
    echo '<select name="fobi_year_filter">';
    echo '<option value="">Alle Jahre</option>';
    $start_year = date('Y') - 10;
    $end_year = date('Y') + 1;
    for ($y = $end_year; $y >= $start_year; $y--) {
        printf(
            '<option value="%d"%s>%d</option>',
            $y,
            selected($current_year, $y, false),
            $y
        );
    }
    echo '</select>';
    
    // Ort-Suche (Textfeld)
    $current_location = isset($_GET['fobi_location_search']) ? sanitize_text_field($_GET['fobi_location_search']) : '';
    printf(
        '<input type="text" name="fobi_location_search" placeholder="Ort suchen..." value="%s" style="margin-left:5px;">',
        esc_attr($current_location)
    );
    
    // Punkte-Bereich
    $min_points = isset($_GET['fobi_min_points']) ? floatval($_GET['fobi_min_points']) : '';
    $max_points = isset($_GET['fobi_max_points']) ? floatval($_GET['fobi_max_points']) : '';
    printf(
        '<input type="number" name="fobi_min_points" placeholder="Min. Punkte" value="%s" step="0.1" style="width:100px;margin-left:5px;">',
        $min_points !== '' ? esc_attr($min_points) : ''
    );
    printf(
        '<input type="number" name="fobi_max_points" placeholder="Max. Punkte" value="%s" step="0.1" style="width:100px;margin-left:5px;">',
        $max_points !== '' ? esc_attr($max_points) : ''
    );
}

// Filter-Query anpassen
add_filter( 'parse_query', 'fobi_admin_filter_query' );
function fobi_admin_filter_query( $query ) {
    global $pagenow;
    
    if ( !is_admin() || $pagenow !== 'edit.php' || !isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'fortbildung' ) {
        return;
    }
    
    $meta_query = array('relation' => 'AND');
    
    // Typ-Filter
    if ( isset($_GET['fobi_type_filter']) && $_GET['fobi_type_filter'] !== '' ) {
        $meta_query[] = array(
            'key' => 'type',
            'value' => sanitize_text_field($_GET['fobi_type_filter']),
            'compare' => '='
        );
    }
    
    // Benutzer-Filter
    if ( isset($_GET['fobi_user_filter']) && $_GET['fobi_user_filter'] > 0 ) {
        $meta_query[] = array(
            'key' => 'user',
            'value' => intval($_GET['fobi_user_filter']),
            'compare' => '='
        );
    }
    
    // Status-Filter
    if ( isset($_GET['fobi_status_filter']) && $_GET['fobi_status_filter'] !== '' ) {
        if ($_GET['fobi_status_filter'] === 'approved') {
            $meta_query[] = array(
                'key' => 'freigegeben',
                'value' => '1',
                'compare' => '='
            );
        } elseif ($_GET['fobi_status_filter'] === 'pending') {
            $meta_query[] = array(
                'relation' => 'OR',
                array('key' => 'freigegeben', 'compare' => 'NOT EXISTS'),
                array('key' => 'freigegeben', 'value' => '1', 'compare' => '!=')
            );
        }
    }
    
    // Jahr-Filter
    if ( isset($_GET['fobi_year_filter']) && $_GET['fobi_year_filter'] > 0 ) {
        $year = intval($_GET['fobi_year_filter']);
        $meta_query[] = array(
            'key' => 'date',
            'value' => array($year . '-01-01', $year . '-12-31'),
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        );
    }
    
    // Ort-Filter
    if ( isset($_GET['fobi_location_search']) && $_GET['fobi_location_search'] !== '' ) {
        $meta_query[] = array(
            'key' => 'location',
            'value' => sanitize_text_field($_GET['fobi_location_search']),
            'compare' => 'LIKE'
        );
    }
    
    // Punkte-Filter
    if ( isset($_GET['fobi_min_points']) && $_GET['fobi_min_points'] !== '' ) {
        $meta_query[] = array(
            'key' => 'points',
            'value' => floatval($_GET['fobi_min_points']),
            'compare' => '>=',
            'type' => 'NUMERIC'
        );
    }
    if ( isset($_GET['fobi_max_points']) && $_GET['fobi_max_points'] !== '' ) {
        $meta_query[] = array(
            'key' => 'points',
            'value' => floatval($_GET['fobi_max_points']),
            'compare' => '<=',
            'type' => 'NUMERIC'
        );
    }
    
    if ( count($meta_query) > 1 ) {
        $query->set('meta_query', $meta_query);
    }
}

/* ============================================================
 * Frontend: Erweiterte Suchfunktion als Shortcode
 * ============================================================ */

add_shortcode('fortbildung_suche', 'fobi_frontend_search_shortcode');
function fobi_frontend_search_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Bitte loggen Sie sich ein, um die Suchfunktion zu nutzen.</p>';
    }
    
    $atts = shortcode_atts(array(
        'show_all_users' => 'no', // 'yes' für Admins/Delegierte
    ), $atts);
    
    $current_user_id = get_current_user_id();
    $is_delegate = get_user_meta($current_user_id, 'delegate', true) === '1';
    $can_see_all = current_user_can('manage_options') || $is_delegate || $atts['show_all_users'] === 'yes';
    
    ob_start();
    ?>
    <script>
    (function(){ 
        if(typeof window.ajaxurl==='undefined'){ 
            window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; 
        }
    })();
    </script>
    
    <div class="fobi-search-container">
        <h3>Fortbildungen durchsuchen</h3>
        
        <form id="fobi-search-form" class="fobi-search-form">
            <div class="fobi-search-row">
                <div class="fobi-search-field">
                    <label for="fobi_search_title">Titel:</label>
                    <input type="text" id="fobi_search_title" name="title" placeholder="Suchbegriff im Titel...">
                </div>
                
                <div class="fobi-search-field">
                    <label for="fobi_search_location">Ort:</label>
                    <input type="text" id="fobi_search_location" name="location" placeholder="Ort...">
                </div>
                
                <div class="fobi-search-field">
                    <label for="fobi_search_type">Art:</label>
                    <select id="fobi_search_type" name="type">
                        <option value="">Alle Arten</option>
                        <!-- Wird via AJAX gefüllt -->
                    </select>
                </div>
            </div>
            
            <div class="fobi-search-row">
                <div class="fobi-search-field">
                    <label for="fobi_search_date_from">Datum von:</label>
                    <input type="date" id="fobi_search_date_from" name="date_from">
                </div>
                
                <div class="fobi-search-field">
                    <label for="fobi_search_date_to">Datum bis:</label>
                    <input type="date" id="fobi_search_date_to" name="date_to">
                </div>
                
                <div class="fobi-search-field">
                    <label for="fobi_search_status">Status:</label>
                    <select id="fobi_search_status" name="status">
                        <option value="">Alle</option>
                        <option value="approved">Freigegeben</option>
                        <option value="pending">Nicht freigegeben</option>
                    </select>
                </div>
            </div>
            
            <div class="fobi-search-row">
                <div class="fobi-search-field">
                    <label for="fobi_search_min_points">Min. Punkte:</label>
                    <input type="number" id="fobi_search_min_points" name="min_points" step="0.1" placeholder="0.0">
                </div>
                
                <div class="fobi-search-field">
                    <label for="fobi_search_max_points">Max. Punkte:</label>
                    <input type="number" id="fobi_search_max_points" name="max_points" step="0.1" placeholder="100.0">
                </div>
                
                <?php if ($can_see_all): ?>
                <div class="fobi-search-field">
                    <label for="fobi_search_user">Benutzer:</label>
                    <select id="fobi_search_user" name="user_id">
                        <option value="">Alle Benutzer</option>
                        <!-- Wird via AJAX gefüllt -->
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="fobi-search-actions">
                <button type="submit" class="button button-primary">Suchen</button>
                <button type="button" id="fobi-search-reset" class="button">Zurücksetzen</button>
                <span id="fobi-search-spinner" class="spinner" style="float:none;display:none;"></span>
            </div>
        </form>
        
        <div id="fobi-search-results" class="fobi-search-results">
            <!-- Ergebnisse werden hier eingefügt -->
        </div>
    </div>
    
    <style>
    .fobi-search-container {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin: 20px 0;
    }
    .fobi-search-form {
        margin-bottom: 20px;
    }
    .fobi-search-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .fobi-search-field {
        display: flex;
        flex-direction: column;
    }
    .fobi-search-field label {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    .fobi-search-field input,
    .fobi-search-field select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .fobi-search-actions {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    .fobi-search-actions button {
        margin-right: 10px;
    }
    .fobi-search-results {
        margin-top: 30px;
    }
    .fobi-result-summary {
        background: #f6f7f7;
        padding: 10px 15px;
        border-left: 4px solid #0073aa;
        margin-bottom: 20px;
    }
    .fobi-result-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .fobi-result-table th,
    .fobi-result-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .fobi-result-table thead th {
        background: #f6f7f7;
        font-weight: 600;
    }
    .fobi-result-table tbody tr:hover {
        background: #f9f9f9;
    }
    .fobi-status-approved {
        color: #0a7;
        font-weight: 600;
    }
    .fobi-status-pending {
        color: #d63638;
    }
    .fobi-no-results {
        padding: 40px;
        text-align: center;
        color: #666;
        font-style: italic;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .fobi-search-row {
            grid-template-columns: 1fr;
        }
        .fobi-result-table {
            font-size: 12px;
        }
        .fobi-result-table th,
        .fobi-result-table td {
            padding: 6px;
        }
    }
    </style>
    
    <script>
    (function($) {
        var canSeeAll = <?php echo $can_see_all ? 'true' : 'false'; ?>;
        
        // Initialisierung: Optionen laden
        function initializeFilters() {
            // Typen laden
            $.post(ajaxurl, {
                action: 'fobi_get_filter_options',
                filter_type: 'types'
            }, function(resp) {
                if (resp.success && resp.data) {
                    var $select = $('#fobi_search_type');
                    $.each(resp.data, function(i, type) {
                        $select.append($('<option>', {
                            value: type,
                            text: type
                        }));
                    });
                }
            });
            
            // Benutzer laden (falls berechtigt)
            if (canSeeAll) {
                $.post(ajaxurl, {
                    action: 'fobi_get_filter_options',
                    filter_type: 'users'
                }, function(resp) {
                    if (resp.success && resp.data) {
                        var $select = $('#fobi_search_user');
                        $.each(resp.data, function(id, name) {
                            $select.append($('<option>', {
                                value: id,
                                text: name
                            }));
                        });
                    }
                });
            }
        }
        
        // Suche durchführen
        $('#fobi-search-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            formData += '&action=fobi_frontend_search';
            if (!canSeeAll) {
                formData += '&own_only=1';
            }
            
            $('#fobi-search-spinner').show();
            $('#fobi-search-results').html('');
            
            $.post(ajaxurl, formData, function(resp) {
                $('#fobi-search-spinner').hide();
                if (resp.success) {
                    $('#fobi-search-results').html(resp.data.html);
                } else {
                    $('#fobi-search-results').html('<div class="notice notice-error"><p>Fehler bei der Suche.</p></div>');
                }
            }).fail(function() {
                $('#fobi-search-spinner').hide();
                $('#fobi-search-results').html('<div class="notice notice-error"><p>Verbindungsfehler.</p></div>');
            });
        });
        
        // Zurücksetzen
        $('#fobi-search-reset').on('click', function() {
            $('#fobi-search-form')[0].reset();
            $('#fobi-search-results').html('');
        });
        
        // Bei Laden initialisieren
        $(document).ready(function() {
            initializeFilters();
        });
        
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/* ============================================================
 * AJAX: Filter-Optionen laden
 * ============================================================ */
add_action('wp_ajax_fobi_get_filter_options', 'fobi_get_filter_options_ajax');
function fobi_get_filter_options_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt.'));
    }
    
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';
    
    if ($filter_type === 'types') {
        // Alle verwendeten Typen sammeln
        $types = array();
        $query = new WP_Query(array(
            'post_type' => 'fortbildung',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        if ($query->have_posts()) {
            foreach ($query->posts as $pid) {
                $type = get_field('type', $pid);
                if ($type && !in_array($type, $types)) {
                    $types[] = $type;
                }
            }
        }
        sort($types);
        wp_send_json_success($types);
        
    } elseif ($filter_type === 'users') {
        // Benutzer mit Fortbildungen
        $users = array();
        $query = new WP_Query(array(
            'post_type' => 'fortbildung',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        if ($query->have_posts()) {
            foreach ($query->posts as $pid) {
                $uid = get_post_meta($pid, 'user', true);
                if ($uid && !isset($users[$uid])) {
                    $user = get_userdata($uid);
                    if ($user) {
                        $name = trim($user->first_name . ' ' . $user->last_name);
                        if (!$name) $name = $user->display_name ?: $user->user_login;
                        $users[$uid] = $name;
                    }
                }
            }
        }
        asort($users);
        wp_send_json_success($users);
    }
    
    wp_send_json_error(array('message' => 'Ungültiger Filter-Typ.'));
}

/* ============================================================
 * AJAX: Frontend-Suche durchführen
 * ============================================================ */
add_action('wp_ajax_fobi_frontend_search', 'fobi_frontend_search_ajax');
function fobi_frontend_search_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt.'));
    }
    
    $current_user_id = get_current_user_id();
    $own_only = isset($_POST['own_only']) && $_POST['own_only'] === '1';
    
    // Query aufbauen
    $args = array(
        'post_type' => 'fortbildung',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => 'date',
        'order' => 'DESC'
    );
    
    $meta_query = array('relation' => 'AND');
    
    // Nur eigene Einträge?
    if ($own_only) {
        $meta_query[] = array(
            'key' => 'user',
            'value' => $current_user_id,
            'compare' => '='
        );
    } elseif (isset($_POST['user_id']) && $_POST['user_id'] !== '') {
        $meta_query[] = array(
            'key' => 'user',
            'value' => intval($_POST['user_id']),
            'compare' => '='
        );
    }
    
    // Typ-Filter
    if (isset($_POST['type']) && $_POST['type'] !== '') {
        $meta_query[] = array(
            'key' => 'type',
            'value' => sanitize_text_field($_POST['type']),
            'compare' => '='
        );
    }
    
    // Ort-Filter
    if (isset($_POST['location']) && $_POST['location'] !== '') {
        $meta_query[] = array(
            'key' => 'location',
            'value' => sanitize_text_field($_POST['location']),
            'compare' => 'LIKE'
        );
    }
    
    // Datums-Filter
    if (isset($_POST['date_from']) && $_POST['date_from'] !== '' && isset($_POST['date_to']) && $_POST['date_to'] !== '') {
        $meta_query[] = array(
            'key' => 'date',
            'value' => array(sanitize_text_field($_POST['date_from']), sanitize_text_field($_POST['date_to'])),
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        );
    } elseif (isset($_POST['date_from']) && $_POST['date_from'] !== '') {
        $meta_query[] = array(
            'key' => 'date',
            'value' => sanitize_text_field($_POST['date_from']),
            'compare' => '>=',
            'type' => 'DATE'
        );
    } elseif (isset($_POST['date_to']) && $_POST['date_to'] !== '') {
        $meta_query[] = array(
            'key' => 'date',
            'value' => sanitize_text_field($_POST['date_to']),
            'compare' => '<=',
            'type' => 'DATE'
        );
    }
    
    // Status-Filter
    if (isset($_POST['status']) && $_POST['status'] !== '') {
        if ($_POST['status'] === 'approved') {
            $meta_query[] = array(
                'key' => 'freigegeben',
                'value' => '1',
                'compare' => '='
            );
        } elseif ($_POST['status'] === 'pending') {
            $meta_query[] = array(
                'relation' => 'OR',
                array('key' => 'freigegeben', 'compare' => 'NOT EXISTS'),
                array('key' => 'freigegeben', 'value' => '1', 'compare' => '!=')
            );
        }
    }
    
    // Punkte-Filter
    if (isset($_POST['min_points']) && $_POST['min_points'] !== '') {
        $meta_query[] = array(
            'key' => 'points',
            'value' => floatval($_POST['min_points']),
            'compare' => '>=',
            'type' => 'NUMERIC'
        );
    }
    if (isset($_POST['max_points']) && $_POST['max_points'] !== '') {
        $meta_query[] = array(
            'key' => 'points',
            'value' => floatval($_POST['max_points']),
            'compare' => '<=',
            'type' => 'NUMERIC'
        );
    }
    
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    
    // Titel-Suche (über s-Parameter)
    if (isset($_POST['title']) && $_POST['title'] !== '') {
        $args['s'] = sanitize_text_field($_POST['title']);
    }
    
    $query = new WP_Query($args);
    
    ob_start();
    
    if (!$query->have_posts()) {
        echo '<div class="fobi-no-results">';
        echo '<p>Keine Fortbildungen gefunden, die den Suchkriterien entsprechen.</p>';
        echo '</div>';
    } else {
        $total_points = 0;
        
        // Zusammenfassung
        echo '<div class="fobi-result-summary">';
        echo '<strong>' . $query->found_posts . ' Fortbildung(en) gefunden</strong>';
        echo '</div>';
        
        // Tabelle
        echo '<table class="fobi-result-table">';
        echo '<thead><tr>';
        echo '<th>Datum</th>';
        echo '<th>Titel</th>';
        echo '<th>Ort</th>';
        echo '<th>Punkte</th>';
        echo '<th>Art</th>';
        echo '<th>Status</th>';
        if (!$own_only) {
            echo '<th>Benutzer</th>';
        }
        echo '</tr></thead><tbody>';
        
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            
            $date = get_field('date', $pid);
            $title = get_the_title($pid);
            $location = get_field('location', $pid);
            $points = floatval(get_field('points', $pid));
            $type = get_field('type', $pid);
            $freigegeben = get_field('freigegeben', $pid);
            
            $is_approved = fobi_is_freigegeben($freigegeben);
            if ($is_approved) {
                $total_points += $points;
            }
            
            // Datum formatieren
            $display_date = '';
            if ($date) {
                $formats = array('Y-m-d', 'd.m.Y', 'Ymd');
                foreach ($formats as $fmt) {
                    $dt = DateTime::createFromFormat($fmt, $date);
                    if ($dt instanceof DateTime) {
                        $display_date = date_i18n('d.m.Y', $dt->getTimestamp());
                        break;
                    }
                }
                if (!$display_date) {
                    $display_date = $date;
                }
            }
            
            echo '<tr' . ($is_approved ? '' : ' style="opacity:0.6"') . '>';
            echo '<td>' . esc_html($display_date) . '</td>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>' . esc_html($location) . '</td>';
            echo '<td style="text-align:right">' . esc_html(number_format($points, 1, ',', '.')) . '</td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td class="' . ($is_approved ? 'fobi-status-approved' : 'fobi-status-pending') . '">' . ($is_approved ? 'Freigegeben' : 'Nicht freigegeben') . '</td>';
            
            if (!$own_only) {
                $uid = get_post_meta($pid, 'user', true);
                $user_name = '(Unbekannt)';
                if ($uid) {
                    $user = get_userdata($uid);
                    if ($user) {
                        $user_name = trim($user->first_name . ' ' . $user->last_name);
                        if (!$user_name) $user_name = $user->display_name ?: $user->user_login;
                    }
                }
                echo '<td>' . esc_html($user_name) . '</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Summe
        echo '<div class="fobi-result-summary" style="margin-top:15px">';
        echo '<strong>Gesamtpunkte (nur freigegebene): ' . number_format($total_points, 1, ',', '.') . '</strong>';
        echo '</div>';
        
        wp_reset_postdata();
    }
    
    $html = ob_get_clean();
    
    wp_send_json_success(array(
        'html' => $html,
        'count' => $query->found_posts
    ));
}