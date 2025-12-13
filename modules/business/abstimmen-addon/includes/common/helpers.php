<?php
if (!defined('ABSPATH')) exit;

// Manager-Recht über Usermeta
if (!function_exists('dgptm_is_manager')) {
    function dgptm_is_manager($user_id = null) {
        if ($user_id === null) $user_id = get_current_user_id();
        if (!$user_id) return current_user_can('manage_options');
        if (current_user_can('manage_options')) return true;
        $flag = get_user_meta($user_id, 'toggle_abstimmungsmanager', true);
        return (bool) $flag;
    }
}

// Usermeta UI + speichern
if (!function_exists('dgptm_user_field_abstimmungsmanager')) {
    function dgptm_user_field_abstimmungsmanager($user){
        if(!current_user_can('manage_options')) return;
        $val = get_user_meta($user->ID,'toggle_abstimmungsmanager',true);
        ?>
        <h2>Abstimmungsmanager</h2>
        <table class="form-table" role="presentation">
          <tr>
            <th><label for="toggle_abstimmungsmanager">Ist Abstimmungsmanager?</label></th>
            <td>
              <label style="display:inline-flex;align-items:center;gap:10px;">
                <span style="display:inline-block;width:46px;height:24px;position:relative;">
                  <input type="checkbox" name="toggle_abstimmungsmanager" id="toggle_abstimmungsmanager" value="1" <?php checked($val, '1'); ?> style="display:none;">
                  <span style="position:absolute;inset:0;border-radius:24px;background:#ccc;"></span>
                  <span style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .2s;" id="dgptm_knob"></span>
                </span>
                <span>Aktiviert Zugriff auf [manage_poll] und [beamer_view]</span>
              </label>
              <script>
              (function(){
                var cb=document.getElementById('toggle_abstimmungsmanager');
                if(!cb) return;
                var knob=document.getElementById('dgptm_knob');
                var wrap=cb.previousElementSibling;
                function paint(){
                  wrap.firstElementChild.style.background = cb.checked ? '#4CAF50' : '#ccc';
                  knob.style.transform = cb.checked ? 'translateX(22px)' : 'translateX(0)';
                }
                wrap.addEventListener('click',function(e){ cb.checked=!cb.checked; paint(); });
                paint();
              })();
              </script>
              <p class="description">Schaltet Manager-Rechte unabhängig von der Rolle frei.</p>
            </td>
          </tr>
        </table>
        <?php
    }
}

if (!function_exists('dgptm_save_user_field_abstimmungsmanager')) {
    function dgptm_save_user_field_abstimmungsmanager($user_id){
        if(!current_user_can('manage_options')) return;
        $val = isset($_POST['toggle_abstimmungsmanager']) ? '1' : '0';
        update_user_meta($user_id, 'toggle_abstimmungsmanager', $val);
    }
}

// Shortcode 1/0 je nach Toggle
if (!function_exists('dgptm_shortcode_manager_toggle')) {
    function dgptm_shortcode_manager_toggle(){
        $user_id = get_current_user_id();
        if(!$user_id) return '0';
        $v = get_user_meta($user_id,'toggle_abstimmungsmanager',true);
        return $v==='1' ? '1' : '0';
    }
}

// helpers
if (!function_exists('dgptm_is_image_ext')) {
    function dgptm_is_image_ext($url){
        $ext = strtolower(pathinfo(parse_url($url,PHP_URL_PATH)??'',PATHINFO_EXTENSION));
        return in_array($ext,array('jpg','jpeg','png'),true);
    }
}

// Öffentliche Ziel-URL (?dgptm_member=1)
if (!function_exists('dgptm_add_query_var')) {
    function dgptm_add_query_var($vars){ $vars[]='dgptm_member'; $vars[]='poll_id'; $vars[]='token'; return $vars; }
}

if (!function_exists('dgptm_template_redirect')) {
    function dgptm_template_redirect(){
        if(get_query_var('dgptm_member')){
            status_header(200);
            nocache_headers();
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Abstimmung</title>';
            wp_head();
            echo '</head><body>';
            echo do_shortcode('[member_vote]');
            wp_footer();
            echo '</body></html>';
            exit;
        }
    }
}
