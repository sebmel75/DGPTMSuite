<?php
/**
 * Frontend Dashboard Shell Template
 * Variables available: $user_id, $visible_tabs, $active_tab_id, $crm_data
 */
if (!defined('ABSPATH')) exit;
?>
<!-- DGPTM Dashboard v<?php echo DGPTM_DASHBOARD_VERSION; ?> | <?php echo date('Y-m-d H:i:s'); ?> | Tabs: <?php echo count($visible_tabs); ?> -->
<div class="dgptm-dashboard" data-default-tab="<?php echo esc_attr($active_tab_id); ?>">

    <?php // Tab Navigation ?>
    <?php $this->render_tab_navigation($visible_tabs, $active_tab_id); ?>

    <?php // Tab Panels ?>
    <div class="dgptm-tab-panels">
        <?php foreach ($visible_tabs as $tab) :
            $is_active = ($tab['id'] === $active_tab_id);
        ?>
            <div class="dgptm-tab-panel<?php echo $is_active ? ' dgptm-tab-panel--active' : ''; ?>"
                 id="dgptm-panel-<?php echo esc_attr($tab['id']); ?>"
                 role="tabpanel"
                 data-tab-id="<?php echo esc_attr($tab['id']); ?>"
                 data-loaded="<?php echo $is_active ? 'true' : 'false'; ?>"
                 <?php if (!$is_active) echo 'style="display:none;"'; ?>>

                <?php if ($is_active) : ?>
                    <?php echo $this->render_tab_content($tab['id'], $user_id); ?>
                <?php else : ?>
                    <div class="dgptm-tab-loading">
                        <div class="dgptm-spinner"></div>
                        <span>Wird geladen...</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</div>
