<?php
/**
 * VNR-Neubewertung: Backend-Seite + Shortcode + AJAX
 * Massen-Neubewertung nach VNR via Aerztekammer + KI
 */
if (!defined('ABSPATH')) exit;

// Backend-Seite unter Fortbildungen
add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=fortbildung', 'VNR-Neubewertung', 'VNR-Neubewertung', 'edit_posts', 'fobi-vnr-reeval', 'fobi_vnr_reeval_admin_page');
});

function fobi_vnr_reeval_admin_page() {
    if (!current_user_can('edit_posts')) wp_die('Keine Berechtigung.');
    echo '<div class="wrap"><h1>VNR-basierte Neubewertung</h1>';
    echo do_shortcode('[fobi_vnr_neubewertung]');
    echo '</div>';
}

// Shortcode
add_shortcode('fobi_vnr_neubewertung', 'fobi_vnr_neubewertung_shortcode');

function fobi_vnr_neubewertung_shortcode($atts) {
    if (!current_user_can('edit_posts')) return '<p>Keine Berechtigung.</p>';
    $ajax_url = esc_url(admin_url('admin-ajax.php'));
    $nonce = wp_create_nonce('fobi_vnr_reeval');
    ob_start();
    ?>
    <div class="fobi-vnr-wrap">
        <h3>VNR-basierte Neubewertung</h3>
        <p style="color:#666;font-size:13px;">Waehlen Sie eine Veranstaltung (VNR). Alle zugehoerigen Fortbildungen werden ueber die Aerztekammer verifiziert und per KI neu bewertet.</p>
        <button type="button" id="fobi-vnr-load" style="padding:4px 10px;font-size:12px;background:#0073aa;color:#fff;border:1px solid #0073aa;border-radius:4px;cursor:pointer;">Veranstaltungen mit VNR laden</button>
        <div id="fobi-vnr-list" style="display:none;margin-top:12px;"></div>
        <div id="fobi-vnr-result" style="margin-top:12px;"></div>
    </div>
    <script>
    jQuery(function(jq){
        var ajaxUrl='<?php echo $ajax_url; ?>',nonce='<?php echo $nonce; ?>';
        jq('#fobi-vnr-load').on('click',function(){
            var b=jq(this);b.prop('disabled',true).text('Lade...');
            jq.post(ajaxUrl,{action:'fobi_vnr_list',nonce:nonce},function(r){
                b.prop('disabled',false).text('Veranstaltungen mit VNR laden');
                if(!r.success){alert(r.data||'Fehler');return;}
                var ev=r.data.events;
                if(!ev.length){jq('#fobi-vnr-list').html('<p style="color:#888">Keine Veranstaltungen mit VNR.</p>').show();return;}
                var h='<table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="border-bottom:2px solid #eee"><th style="text-align:left;padding:6px 8px">VNR</th><th style="text-align:left;padding:6px 8px">Veranstaltung</th><th style="text-align:right;padding:6px 8px">Tage</th><th style="text-align:right;padding:6px 8px">Pkt/Tag</th><th></th></tr>';
                for(var i=0;i<ev.length;i++){var e=ev[i];
                    h+='<tr style="border-bottom:1px solid #f0f0f0"><td style="padding:6px 8px;font-family:monospace;font-size:11px">'+e.vnr+'</td><td style="padding:6px 8px">'+e.title+'</td><td style="padding:6px 8px;text-align:right">'+e.count+'</td><td style="padding:6px 8px;text-align:right">'+e.points_per+'</td><td style="padding:6px"><button type="button" class="fobi-vnr-reeval" data-vnr="'+e.vnr+'" style="padding:3px 8px;font-size:11px;background:#0073aa;color:#fff;border:1px solid #0073aa;border-radius:4px;cursor:pointer">Neu bewerten</button></td></tr>';
                }
                h+='</table>';jq('#fobi-vnr-list').html(h).show();
            });
        });
        jq(document).on('click','.fobi-vnr-reeval',function(){
            var b=jq(this),v=b.data('vnr');b.prop('disabled',true).text('Laeuft...');
            jq.post(ajaxUrl,{action:'fobi_vnr_reeval',nonce:nonce,vnr:v},function(r){
                b.prop('disabled',false).text('Neu bewerten');
                if(r.success){var d=r.data;
                    jq('#fobi-vnr-result').prepend('<div style="background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:4px;margin:6px 0;font-size:13px"><strong>VNR '+v+':</strong> '+d.message+(d.baek_title?'<br>AEK: '+d.baek_title:'')+'</div>');
                    b.closest('tr').find('td:nth-child(4)').text(d.points);
                    b.css({background:'#46b450','border-color':'#46b450'}).text('Erledigt');
                }else{
                    jq('#fobi-vnr-result').prepend('<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:10px;border-radius:4px;margin:6px 0;font-size:13px"><strong>VNR '+v+':</strong> '+(r.data&&r.data.message?r.data.message:'Fehler')+'</div>');
                }
            });
        });
    });
    </script>
    <style>.dgptm-dash .fobi-vnr-wrap{max-width:100%}.dgptm-dash .fobi-vnr-wrap h3{font-size:14px;margin:0 0 8px;color:#1d2327}.dgptm-dash .fobi-vnr-wrap table th{font-weight:600;color:#1d2327;font-size:12px;background:none}.dgptm-dash .fobi-vnr-wrap table tr:hover{background:#f8f9fa}</style>
    <?php
    return ob_get_clean();
}

// AJAX: VNR-Liste laden
add_action('wp_ajax_fobi_vnr_list', 'fobi_ajax_vnr_list');

function fobi_ajax_vnr_list() {
    check_ajax_referer('fobi_vnr_reeval', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error('Keine Berechtigung.');
    global $wpdb;

    $results = $wpdb->get_results("
        SELECT pm_vnr.meta_value AS vnr, MIN(p.post_title) AS title, COUNT(*) AS cnt,
               AVG(CAST(COALESCE(pm_pts.meta_value, '0') AS DECIMAL(10,1))) AS points_per_entry
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_vnr ON pm_vnr.post_id = p.ID AND pm_vnr.meta_key = 'vnr'
        LEFT JOIN {$wpdb->postmeta} pm_pts ON pm_pts.post_id = p.ID AND pm_pts.meta_key = 'points'
        WHERE p.post_type = 'fortbildung' AND p.post_status IN ('publish','draft','pending')
          AND pm_vnr.meta_value != '' AND LENGTH(pm_vnr.meta_value) > 5
        GROUP BY pm_vnr.meta_value ORDER BY MAX(p.post_date) DESC LIMIT 100
    ");

    $events = [];
    foreach ($results as $r) {
        $title = html_entity_decode(strip_tags($r->title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s*\(Tag\s+\d+\/\d+\)\s*$/i', '', $title);
        $events[] = ['vnr' => $r->vnr, 'title' => $title, 'count' => (int)$r->cnt, 'points_per' => number_format((float)$r->points_per_entry, 1)];
    }
    wp_send_json_success(['events' => $events]);
}

// AJAX: VNR-Neubewertung durchfuehren
add_action('wp_ajax_fobi_vnr_reeval', 'fobi_ajax_vnr_reeval');

function fobi_ajax_vnr_reeval() {
    check_ajax_referer('fobi_vnr_reeval', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Keine Berechtigung.']);

    $vnr = sanitize_text_field($_POST['vnr'] ?? '');
    if (empty($vnr)) wp_send_json_error(['message' => 'Keine VNR.']);

    $s = fobi_ebcp_get_settings();

    // Exakte VNR-Suche (auch in kommaseparierten Feldern)
    global $wpdb;
    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = 'vnr' AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
        $vnr,
        $vnr . ',%',
        '%,' . $vnr . ',%',
        '%,' . $vnr
    ));
    $posts = !empty($post_ids) ? get_posts([
        'post_type' => 'fortbildung', 'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending'],
        'post__in' => array_map('intval', $post_ids),
    ]) : [];
    if (empty($posts)) wp_send_json_error(['message' => 'Keine Fortbildungen mit VNR ' . $vnr]);

    // BÄK-Daten
    $baek_title = '';
    $baek_data = null;
    if (function_exists('dgptm_eiv_get_baek_token') && function_exists('dgptm_eiv_fetch_veranstaltung')) {
        $jwt = dgptm_eiv_get_baek_token();
        if (!is_wp_error($jwt) && !empty($jwt)) {
            $info = dgptm_eiv_fetch_veranstaltung($jwt, $vnr);
            if (!is_wp_error($info) && is_array($info)) {
                $baek_data = $info;
                $baek_title = $info['titel'] ?? $info['thema'] ?? '';
            }
        }
    }

    // KI: Kategorie bestimmen
    $new_points = 0;
    $category_key = '';
    $category_label = '';
    $ai_reason = '';
    $api_key = $s['claude_api_key'] ?? '';
    $event_title = $baek_title ?: html_entity_decode(strip_tags($posts[0]->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (!empty($api_key)) {
        $categories_desc = fobi_ebcp_get_categories_description();
        $model = $s['claude_model'] ?? 'claude-sonnet-4-6-20250514';

        $intl_list = fobi_ebcp_get_international_list($s);

        $prompt = sprintf(
            "Bewerte diese Fortbildungsveranstaltung fuer EBCP-Punkte. Nutze EXAKT einen Key aus der Kategorieliste.\n\n" .
            "VNR: %s\nTitel: %s\nVeranstalter: %s\nOrt: %s\nDatum: %s\n\n" .
            "EBCP-Kategorien:\n%s\n\n" .
            "INTERNATIONALE MEETINGS: %s\n\n" .
            "WICHTIG fuer category-Werte:\n" .
            "- Verwende AUSSCHLIESSLICH einen Key aus der Kategorieliste oben\n" .
            "- KEIN eigener Key! Nur die exakt aufgelisteten Keys sind erlaubt\n" .
            "- Fuer DGPTM oder DGfK Jahrestagung verwende IMMER: passive_dgptm_jahrestagung\n" .
            "- Workshop = 1 Tag Hands-on-Training, kleiner Rahmen\n" .
            "- Kongress = mehrtaegig, viele Vortraege, grosse Teilnehmerzahl\n" .
            "- Seminar = Lehrveranstaltung, 1-2 Tage, theoretischer Fokus\n\n" .
            "WICHTIG fuer national vs. international:\n" .
            "- INTERNATIONAL wenn: englischer Titel, internationale Organisation (EBCP, AMSECT, EACTS, ECC etc.), Teilnehmer aus mehreren Laendern, EBCP-Punkte vergeben werden\n" .
            "- Workshop in Deutschland mit englischem Titel und EBCP-Zertifikat ist INTERNATIONAL\n" .
            "- Nur deutschsprachige Veranstaltungen von deutschen Fachgesellschaften sind NATIONAL\n\n" .
            "Antworte NUR mit JSON: {\"category\": \"category_key\", \"reason\": \"Begruendung\"}",
            $vnr, $event_title,
            $baek_data['veranstalter'] ?? '',
            $baek_data['veranstaltungsort'] ?? $baek_data['ort'] ?? '',
            $baek_data['beginn'] ?? $baek_data['datum_von'] ?? '',
            $categories_desc, $intl_list
        );

        $resp = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => ['Content-Type' => 'application/json', 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01'],
            'body' => wp_json_encode(['model' => $model, 'max_tokens' => 300, 'messages' => [['role' => 'user', 'content' => $prompt]]]),
            'timeout' => 20,
        ]);

        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $text = $body['content'][0]['text'] ?? '';
            if (preg_match('/\{[\s\S]*\}/s', $text, $m)) {
                $ai = json_decode($m[0], true);
                if (!empty($ai['category'])) {
                    $category_key = $ai['category'];
                    $category_label = fobi_ebcp_get_category_label($category_key, $s);
                    $new_points = fobi_ebcp_calc_points(['category' => $category_key, 'subtype' => '', 'active_role' => 'no', 'ects' => 0], $s);
                    $ai_reason = $ai['reason'] ?? '';
                }
            }
        }
    }

    if ($new_points <= 0) {
        wp_send_json_error(['message' => 'KI konnte keine Kategorie bestimmen. Titel: ' . $event_title]);
    }

    // Mehrtages-Erkennung: BÄK-Daten koennen Start/Ende enthalten
    $baek_start = $baek_data['beginn'] ?? $baek_data['datum_von'] ?? '';
    $baek_end = $baek_data['ende'] ?? $baek_data['datum_bis'] ?? '';
    $num_days_baek = 1;
    if ($baek_start && $baek_end) {
        $s_ts = strtotime($baek_start);
        $e_ts = strtotime($baek_end);
        if ($s_ts && $e_ts && $e_ts > $s_ts) {
            $num_days_baek = (int) round(($e_ts - $s_ts) / 86400) + 1;
        }
    }

    // Fehlende Tage anlegen falls BÄK mehr Tage meldet als Posts vorhanden
    $extra_created = 0;
    if ($num_days_baek > count($posts) && function_exists('update_field')) {
        $existing_dates = [];
        foreach ($posts as $p) {
            $d = function_exists('get_field') ? get_field('date', $p->ID) : '';
            if ($d) $existing_dates[] = $d;
        }

        $author_id = $posts[0]->post_author;
        $user_field = function_exists('get_field') ? get_field('user', $posts[0]->ID) : $author_id;
        $attachment_val = function_exists('get_field') ? get_field('attachements', $posts[0]->ID) : '';
        $base_title = preg_replace('/\s*\(Tag\s+\d+\/\d+\)\s*$/i', '', html_entity_decode(strip_tags($posts[0]->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $group_id = get_post_meta($posts[0]->ID, '_fobi_group_id', true) ?: 'fobi_group_' . wp_generate_password(12, false);

        $start_ts = strtotime($baek_start);
        for ($day = 0; $day < $num_days_baek; $day++) {
            $day_date = date('Y-m-d', strtotime("+{$day} days", $start_ts));
            if (in_array($day_date, $existing_dates)) continue;

            $new_id = wp_insert_post([
                'post_type' => 'fortbildung', 'post_title' => $base_title . ' (Tag ' . ($day + 1) . '/' . $num_days_baek . ')',
                'post_status' => 'publish', 'post_author' => $author_id,
            ]);
            if (is_wp_error($new_id) || !$new_id) continue;

            update_field('user', $user_field, $new_id);
            update_field('date', $day_date, $new_id);
            update_field('location', function_exists('get_field') ? get_field('location', $posts[0]->ID) : '', $new_id);
            update_field('type', $category_label, $new_id);
            update_field('points', $new_points, $new_id);
            update_field('vnr', $vnr, $new_id);
            update_field('token', wp_generate_password(32, false), $new_id);
            update_field('freigegeben', true, $new_id);
            if ($attachment_val) update_field('attachements', $attachment_val, $new_id);

            update_post_meta($new_id, '_fobi_group_id', $group_id);
            update_post_meta($new_id, '_fobi_group_day', $day + 1);
            update_post_meta($new_id, '_fobi_group_total_days', $num_days_baek);
            update_post_meta($new_id, '_ebcp_category_key', $category_key);
            update_post_meta($new_id, '_fobi_baek_verified', true);
            $extra_created++;
            $posts[] = get_post($new_id); // Zur Update-Liste hinzufuegen
        }
    }

    // Alle Posts aktualisieren + freigeben
    $updated = 0;
    foreach ($posts as $p) {
        if (function_exists('update_field')) {
            update_field('points', $new_points, $p->ID);
            update_field('type', $category_label, $p->ID);
            update_field('freigegeben', true, $p->ID);
        }
        update_post_meta($p->ID, '_ebcp_category_key', $category_key);
        update_post_meta($p->ID, '_fobi_baek_verified', true);
        update_post_meta($p->ID, '_fobi_baek_vnr', $vnr);
        if ($baek_data) update_post_meta($p->ID, '_fobi_baek_data', wp_json_encode($baek_data));
        update_post_meta($p->ID, '_ebcp_vnr_reeval_at', current_time('mysql'));
        update_post_meta($p->ID, '_ebcp_vnr_reeval_reason', $ai_reason);
        $updated++;
    }

    $msg = sprintf('%s — %d Eintraege auf %s Pkt/Tag aktualisiert und freigegeben.', $category_label, $updated, number_format($new_points, 1));
    if ($extra_created > 0) $msg .= sprintf(' %d neue Tage angelegt.', $extra_created);

    wp_send_json_success([
        'message' => $msg,
        'baek_title' => $baek_title,
        'category' => $category_key,
        'category_label' => $category_label,
        'points' => number_format($new_points, 1),
        'updated' => $updated,
        'new_total_points' => number_format($new_points * count($posts), 1),
    ]);
}
