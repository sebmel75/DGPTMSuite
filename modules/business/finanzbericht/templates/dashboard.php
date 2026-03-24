<?php if (!defined('ABSPATH')) exit;
$user_id = get_current_user_id();
$fb = DGPTM_Finanzbericht::get_instance();
$access = $fb->get_user_access($user_id);
$role = $fb->get_user_role($user_id);
$role_labels = ['admin' => 'Administrator', 'schatzmeister' => 'Schatzmeister', 'praesident' => 'Praesident', 'geschaeftsstelle' => 'Geschaeftsstelle'];
?>
<div id="dgptm-fb-app" class="dgptm-fb-wrap">
    <div class="dgptm-fb-header">
        <h2>Finanzberichte DGPTM</h2>
        <span class="dgptm-fb-role-badge"><?php echo esc_html($role_labels[$role] ?? $role); ?></span>
    </div>

    <div class="dgptm-fb-nav">
        <?php foreach (DGPTM_Finanzbericht::REPORTS as $key => $label):
            if (!in_array($key, $access)) continue;
        ?>
            <button class="dgptm-fb-tab" data-report="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="dgptm-fb-controls">
        <label>Jahr:
            <select id="dgptm-fb-year"></select>
        </label>
        <button id="dgptm-fb-reload" class="dgptm-fb-btn">Aktualisieren</button>
        <span id="dgptm-fb-source" class="dgptm-fb-badge"></span>
    </div>

    <div id="dgptm-fb-loading" class="dgptm-fb-loading" style="display:none;">
        <span class="spinner is-active"></span> Daten werden geladen...
    </div>

    <div id="dgptm-fb-content" class="dgptm-fb-content">
        <p class="dgptm-fb-placeholder">Bitte Bericht auswaehlen.</p>
    </div>
</div>
