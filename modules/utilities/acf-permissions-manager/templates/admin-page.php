<?php
/**
 * Admin Page Template for ACF Permissions Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

$manager = DGPTM_ACF_Permissions_Manager::get_instance();
$permissions = $manager->get_all_permissions();
$all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
?>

<div class="wrap apm-wrap">
    <h1>
        <span class="dashicons dashicons-admin-users"></span>
        ACF Berechtigungen verwalten
    </h1>

    <p class="description">
        Übersicht und Verwaltung aller ACF-Berechtigungen aus der Gruppe "Berechtigungen".
        Änderungen werden automatisch dynamisch erkannt.
    </p>

    <div class="apm-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#overview" class="nav-tab nav-tab-active">Übersicht</a>
            <a href="#permissions" class="nav-tab">Nach Berechtigung</a>
            <a href="#users" class="nav-tab">Nach Benutzer</a>
            <a href="#batch" class="nav-tab">Batch-Operationen</a>
        </nav>
    </div>

    <!-- Overview Tab -->
    <div id="overview" class="apm-tab-content active">
        <div class="apm-card">
            <h2>Statistik</h2>

            <div class="apm-stats">
                <div class="stat-box">
                    <span class="stat-number"><?php echo count($permissions); ?></span>
                    <span class="stat-label">Berechtigungen</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo count($all_users); ?></span>
                    <span class="stat-label">Benutzer</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number" id="total-granted">-</span>
                    <span class="stat-label">Erteilte Berechtigungen</span>
                </div>
            </div>
        </div>

        <div class="apm-card">
            <h2>Alle Berechtigungen</h2>

            <?php if (empty($permissions)): ?>
                <div class="notice notice-warning inline">
                    <p><strong>Keine Berechtigungen gefunden!</strong></p>
                    <p>Stellen Sie sicher, dass:</p>
                    <ul>
                        <li>Advanced Custom Fields Pro aktiviert ist</li>
                        <li>Die ACF-Gruppe "Berechtigungen" existiert (Key: group_6792060047841)</li>
                        <li>Die Gruppe True/False Felder enthält</li>
                    </ul>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="40%">Berechtigung</th>
                            <th width="20%">Feld-Name</th>
                            <th width="20%">Anzahl Benutzer</th>
                            <th width="20%">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $permission):
                            $users_count = count($manager->get_users_with_permission($permission['name']));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($permission['label']); ?></strong></td>
                            <td><code><?php echo esc_html($permission['name']); ?></code></td>
                            <td>
                                <span class="users-count" data-permission="<?php echo esc_attr($permission['name']); ?>">
                                    <?php echo $users_count; ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small view-users"
                                        data-permission="<?php echo esc_attr($permission['name']); ?>"
                                        data-label="<?php echo esc_attr($permission['label']); ?>">
                                    Benutzer anzeigen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Permissions Tab -->
    <div id="permissions" class="apm-tab-content">
        <div class="apm-card">
            <h2>Benutzer nach Berechtigung</h2>

            <div class="apm-filter">
                <label for="permission-select"><strong>Berechtigung auswählen:</strong></label>
                <select id="permission-select" class="regular-text">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($permissions as $permission): ?>
                        <option value="<?php echo esc_attr($permission['name']); ?>">
                            <?php echo esc_html($permission['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="load-permission-users" class="button button-primary" disabled>
                    Laden
                </button>
            </div>

            <div id="permission-users-list" class="apm-users-list" style="display: none;">
                <!-- Users will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Users Tab -->
    <div id="users" class="apm-tab-content">
        <div class="apm-card">
            <h2>Alle Benutzer und ihre Berechtigungen</h2>

            <div class="apm-table-wrapper">
                <table class="wp-list-table widefat fixed striped apm-users-table">
                    <thead>
                        <tr>
                            <th width="200" class="sticky-col">Benutzer</th>
                            <?php foreach ($permissions as $permission): ?>
                                <th class="rotate">
                                    <div>
                                        <span><?php echo esc_html($permission['label']); ?></span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user):
                            $user_id = $user->ID;
                        ?>
                        <tr>
                            <td class="sticky-col">
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <span class="description"><?php echo esc_html($user->user_email); ?></span>
                            </td>
                            <?php foreach ($permissions as $permission):
                                $has_permission = get_field($permission['name'], 'user_' . $user_id);
                            ?>
                            <td class="text-center">
                                <label class="apm-toggle">
                                    <input type="checkbox"
                                           class="permission-toggle"
                                           data-user-id="<?php echo $user_id; ?>"
                                           data-permission="<?php echo esc_attr($permission['name']); ?>"
                                           <?php checked($has_permission); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Batch Tab -->
    <div id="batch" class="apm-tab-content">
        <div class="apm-card">
            <h2>Batch-Zuweisung</h2>

            <div class="apm-batch-section">
                <h3>1. Berechtigung auswählen</h3>
                <select id="batch-permission" class="regular-text">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($permissions as $permission): ?>
                        <option value="<?php echo esc_attr($permission['name']); ?>">
                            <?php echo esc_html($permission['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="apm-batch-section">
                <h3>2. Benutzer auswählen</h3>
                <div class="apm-user-grid">
                    <?php foreach ($all_users as $user): ?>
                        <label class="apm-user-checkbox">
                            <input type="checkbox"
                                   class="batch-user-select"
                                   value="<?php echo $user->ID; ?>">
                            <span><?php echo esc_html($user->display_name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="apm-batch-controls">
                    <button id="select-all-users" class="button">Alle auswählen</button>
                    <button id="deselect-all-users" class="button">Alle abwählen</button>
                    <span class="selected-count">
                        <strong>0</strong> Benutzer ausgewählt
                    </span>
                </div>
            </div>

            <div class="apm-batch-section">
                <h3>3. Aktion ausführen</h3>
                <button id="batch-assign-btn" class="button button-primary button-large" disabled>
                    <span class="dashicons dashicons-yes"></span>
                    Berechtigung erteilen
                </button>
                <button id="batch-revoke-all-btn" class="button button-secondary button-large" disabled>
                    <span class="dashicons dashicons-no"></span>
                    Allen Benutzern entziehen
                </button>
            </div>
        </div>

        <div class="apm-card">
            <h2>CSV-Export</h2>
            <p>Exportieren Sie alle Berechtigungen als CSV-Datei für Excel.</p>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" target="_blank">
                <input type="hidden" name="action" value="apm_export_csv">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('apm_nonce'); ?>">
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    CSV exportieren
                </button>
            </form>
        </div>
    </div>
</div>

<!-- User List Modal -->
<div id="user-list-modal" class="apm-modal" style="display: none;">
    <div class="apm-modal-overlay"></div>
    <div class="apm-modal-content">
        <div class="apm-modal-header">
            <h2 id="modal-title">Benutzer mit Berechtigung</h2>
            <button class="apm-modal-close">&times;</button>
        </div>
        <div class="apm-modal-body" id="modal-users-list">
            <!-- Users will be loaded here -->
        </div>
    </div>
</div>
