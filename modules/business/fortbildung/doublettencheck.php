<?php
/**
 * Add-on: Manuelle Doubletten-Prüfung mit intelligenter Gruppierung
 * Version: 2.2 (Erweitert)
 * 
 * Features:
 * - Flexible Suchoptionen (Ort und/oder Datum ignorieren)
 * - Echte Gruppierung (alle ähnlichen Einträge in EINER Gruppe)
 * - Anzeige aller Veranstaltungsnamen der Gruppe
 * - "Online" darf mehrfach vorkommen
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * Admin-Menü
 * ============================================================ */
add_action('admin_menu', 'fobi_add_manual_dedupe_menu_v2', 16);
function fobi_add_manual_dedupe_menu_v2() {
    add_submenu_page(
        'edit.php?post_type=fortbildung',
        'Doubletten manuell prüfen',
        'Doubletten manuell prüfen',
        'manage_options',
        'fobi-manual-dedupe-v2',
        'fobi_manual_dedupe_page_render_v2'
    );
}

/* ============================================================
 * Haupt-Seite
 * ============================================================ */
function fobi_manual_dedupe_page_render_v2() {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.');
    }
    
    $nonce = wp_create_nonce('fobi_manual_dedupe_v2');
    ?>
    <div class="wrap">
        <h1>Doubletten manuell prüfen und bereinigen</h1>
        
        <div class="notice notice-info">
            <p><strong>Intelligente Gruppierung:</strong> Alle ähnlichen Einträge werden in einer Gruppe zusammengefasst. Sie können flexibel nach Ort und/oder Datum filtern.</p>
        </div>
        
        <div class="fobi-dedupe-controls" style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin:20px 0;border-radius:4px;">
            <h2>Suchoptionen</h2>
            <table class="form-table">
                <tr>
                    <th><label for="fobi-dedupe-user-v2">Benutzer filtern (optional)</label></th>
                    <td>
                        <select id="fobi-dedupe-user-v2" style="min-width:300px;">
                            <option value="">Alle Benutzer</option>
                            <?php
                            global $wpdb;
                            $users_with_fobi = $wpdb->get_col(
                                "SELECT DISTINCT pm.meta_value 
                                FROM {$wpdb->postmeta} pm 
                                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
                                WHERE pm.meta_key = 'user' 
                                AND p.post_type = 'fortbildung' 
                                AND p.post_status = 'publish'
                                AND pm.meta_value != ''
                                ORDER BY pm.meta_value"
                            );
                            
                            foreach ($users_with_fobi as $uid) {
                                $user = get_userdata($uid);
                                if ($user) {
                                    $name = trim(($user->first_name ?: '') . ' ' . ($user->last_name ?: ''));
                                    if ($name === '') $name = $user->display_name ?: $user->user_login;
                                    echo '<option value="' . esc_attr($uid) . '">' . esc_html($name) . ' (ID: ' . $uid . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="fobi-dedupe-similarity-v2">Ähnlichkeitsschwelle</label></th>
                    <td>
                        <select id="fobi-dedupe-similarity-v2">
                            <option value="exact">Exakte Übereinstimmung (100%)</option>
                            <option value="high" selected>Hohe Ähnlichkeit (≥85%)</option>
                            <option value="medium">Mittlere Ähnlichkeit (≥70%)</option>
                        </select>
                        <p class="description">Legt fest, wie ähnlich Titel sein müssen, um gruppiert zu werden.</p>
                    </td>
                </tr>
                <tr>
                    <th>Gruppierungsregeln</th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" id="fobi-ignore-location-v2" value="1">
                                <strong>Ort ignorieren</strong> — Gruppiert auch Einträge mit unterschiedlichen Orten
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" id="fobi-ignore-date-v2" value="1">
                                <strong>Datum ignorieren</strong> — Gruppiert auch Einträge mit unterschiedlichen Daten
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" id="fobi-exclude-online-v2" value="1" checked>
                                <strong>"Online"-Veranstaltungen ausschließen</strong> — Online-Einträge werden nie als Doubletten behandelt
                            </label>
                        </fieldset>
                        <p class="description"><strong>Standard:</strong> Nur ähnlicher Titel + gleicher Ort = Doublette (Online ausgeschlossen)</p>
                    </td>
                </tr>
            </table>
            
            <p>
                <button id="fobi-dedupe-scan-v2" class="button button-primary button-large">
                    <span class="dashicons dashicons-search" style="margin-top:3px;"></span> Doubletten-Gruppen suchen
                </button>
                <span id="fobi-dedupe-spinner-v2" class="spinner" style="float:none;display:none;margin-left:10px;"></span>
            </p>
        </div>

        <div id="fobi-dedupe-results-v2" style="margin-top:20px;"></div>
        
        <div id="fobi-dedupe-actions-v2" style="display:none;margin-top:20px;background:#f9f9f9;border:1px solid #ccd0d4;padding:15px;border-radius:4px;">
            <h3>Ausgewählte Aktionen</h3>
            <p>
                <button id="fobi-dedupe-delete-selected-v2" class="button button-primary button-large">
                    <span class="dashicons dashicons-trash" style="margin-top:3px;"></span> Ausgewählte Doubletten löschen
                </button>
                <button id="fobi-dedupe-select-all-keep-approved-v2" class="button">
                    <span class="dashicons dashicons-yes"></span> Freigegebene behalten
                </button>
                <button id="fobi-dedupe-select-all-keep-older-v2" class="button">
                    <span class="dashicons dashicons-calendar"></span> Älteste behalten
                </button>
                <button id="fobi-dedupe-select-all-keep-higher-points-v2" class="button">
                    <span class="dashicons dashicons-star-filled"></span> Höchste Punkte behalten
                </button>
                <span id="fobi-dedupe-action-msg-v2" style="margin-left:15px;font-weight:bold;"></span>
            </p>
        </div>
    </div>

    <style>
    .fobi-dedupe-group-v2 {
        background: #fff;
        border: 2px solid #ccd0d4;
        border-radius: 6px;
        padding: 0;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .fobi-dedupe-group-v2.group-skipped {
        opacity: 0.5;
        border-color: #999;
    }
    
    .fobi-dedupe-group-header-v2 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 4px 4px 0 0;
        font-weight: 600;
        font-size: 15px;
    }
    
    .fobi-group-title-wrapper-v2 {
        margin-bottom: 10px;
    }
    
    .fobi-group-titles-v2 {
        background: rgba(255,255,255,0.15);
        padding: 10px;
        border-radius: 4px;
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .fobi-title-item-v2 {
        padding: 4px 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .fobi-title-item-v2:last-child {
        border-bottom: none;
    }
    
    .fobi-group-meta-v2 {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    
    .fobi-dedupe-group-body-v2 {
        padding: 20px;
    }
    
    .fobi-dedupe-group-actions-v2 {
        background: #f6f7f7;
        padding: 12px 20px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .fobi-dedupe-entries-v2 {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .fobi-dedupe-item-v2 {
        border: 2px solid #dcdcde;
        border-radius: 4px;
        padding: 15px;
        background: #fafafa;
        position: relative;
        transition: all 0.2s ease;
    }
    
    .fobi-dedupe-item-v2.selected {
        border-color: #00a32a;
        background: #f0f9f0;
        box-shadow: 0 0 0 1px #00a32a;
    }
    
    .fobi-dedupe-item-v2.will-delete {
        border-color: #d63638;
        background: #fcf0f1;
        opacity: 0.8;
    }
    
    .fobi-dedupe-radio-wrapper-v2 {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 2px solid #ddd;
    }
    
    .fobi-dedupe-radio-label-v2 {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
    }
    
    .fobi-dedupe-radio-v2 {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .fobi-field-row-v2 {
        display: grid;
        grid-template-columns: 90px 1fr;
        gap: 8px;
        margin-bottom: 6px;
        font-size: 13px;
        line-height: 1.5;
    }
    
    .fobi-field-label-v2 {
        font-weight: 600;
        color: #444;
    }
    
    .fobi-field-value-v2 {
        color: #666;
        word-break: break-word;
    }
    
    .fobi-entry-title-v2 {
        font-size: 14px;
        font-weight: 600;
        color: #2c3338;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #ddd;
    }
    
    .fobi-badge-v2 {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .fobi-badge-approved-v2 {
        background: #d4edda;
        color: #155724;
    }
    
    .fobi-badge-pending-v2 {
        background: #fff3cd;
        color: #856404;
    }
    
    .fobi-similarity-badge-v2 {
        background: rgba(255,255,255,0.3);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .fobi-delete-indicator-v2 {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #d63638;
        color: white;
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        z-index: 10;
    }
    
    .fobi-keep-indicator-v2 {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #00a32a;
        color: white;
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        z-index: 10;
    }
    
    .fobi-no-duplicates-v2 {
        background: #d4edda;
        border: 2px solid #c3e6cb;
        color: #155724;
        padding: 30px;
        border-radius: 6px;
        text-align: center;
        font-size: 16px;
    }
    
    .fobi-stats-v2 {
        background: #f0f6fc;
        border: 2px solid #c8d7e1;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 15px;
    }
    
    .fobi-stats-v2 strong {
        color: #2271b1;
    }
    
    .fobi-skip-group-btn-v2 {
        background: #f6f7f7;
        border: 1px solid #ccd0d4;
        color: #2c3338;
    }
    
    .fobi-skip-group-btn-v2:hover {
        background: #e5e5e5;
        border-color: #999;
    }
    
    @media (max-width: 782px) {
        .fobi-dedupe-entries-v2 {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    jQuery(function($) {
        var nonce = <?php echo json_encode($nonce); ?>;
        var currentDuplicates = [];
        var skippedGroups = new Set();
        
        $('#fobi-dedupe-scan-v2').on('click', function(e) {
            e.preventDefault();
            
            var userId = $('#fobi-dedupe-user-v2').val();
            var similarity = $('#fobi-dedupe-similarity-v2').val();
            var ignoreLocation = $('#fobi-ignore-location-v2').is(':checked') ? '1' : '0';
            var ignoreDate = $('#fobi-ignore-date-v2').is(':checked') ? '1' : '0';
            var excludeOnline = $('#fobi-exclude-online-v2').is(':checked') ? '1' : '0';
            
            $('#fobi-dedupe-results-v2').html('');
            $('#fobi-dedupe-actions-v2').hide();
            $('#fobi-dedupe-spinner-v2').show();
            $(this).prop('disabled', true);
            skippedGroups.clear();
            
            $.post(ajaxurl, {
                action: 'fobi_find_manual_duplicates_v2',
                _wpnonce: nonce,
                user_id: userId,
                similarity: similarity,
                ignore_location: ignoreLocation,
                ignore_date: ignoreDate,
                exclude_online: excludeOnline
            }, function(resp) {
                $('#fobi-dedupe-spinner-v2').hide();
                $('#fobi-dedupe-scan-v2').prop('disabled', false);
                
                if (resp && resp.success) {
                    currentDuplicates = resp.data.duplicates || [];
                    renderDuplicates(resp.data);
                } else {
                    $('#fobi-dedupe-results-v2').html(
                        '<div class="notice notice-error"><p>Fehler: ' + 
                        (resp && resp.data && resp.data.message ? resp.data.message : 'Unbekannter Fehler') + 
                        '</p></div>'
                    );
                }
            }).fail(function(xhr) {
                $('#fobi-dedupe-spinner-v2').hide();
                $('#fobi-dedupe-scan-v2').prop('disabled', false);
                $('#fobi-dedupe-results-v2').html(
                    '<div class="notice notice-error"><p>HTTP-Fehler: ' + xhr.status + '</p></div>'
                );
            });
        });
        
        function renderDuplicates(data) {
            var duplicates = data.duplicates || [];
            var html = '';
            
            if (duplicates.length === 0) {
                html = '<div class="fobi-no-duplicates-v2">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:48px;width:48px;height:48px;"></span><br><br>' +
                    '<strong style="font-size:20px;">Keine Doubletten-Gruppen gefunden!</strong><br><br>' +
                    'Alle Fortbildungseinträge sind eindeutig.' +
                    '</div>';
                $('#fobi-dedupe-results-v2').html(html);
                return;
            }
            
            html += '<div class="fobi-stats-v2">' +
                '🔍 <strong>' + duplicates.length + '</strong> Doubletten-Gruppe(n) mit insgesamt ' +
                '<strong>' + data.total_entries + '</strong> betroffenen Einträgen.<br>' +
                '💡 <strong>Tipp:</strong> Wählen Sie pro Gruppe den zu behaltenden Eintrag oder überspringen Sie die Gruppe.' +
                '</div>';
            
            duplicates.forEach(function(group, groupIndex) {
                html += '<div class="fobi-dedupe-group-v2" data-group-index="' + groupIndex + '" id="group-v2-' + groupIndex + '">';
                
                // Header mit allen Titeln
                html += '<div class="fobi-dedupe-group-header-v2">' +
                    '<div class="fobi-group-title-wrapper-v2">' +
                    '<div><span class="dashicons dashicons-flag"></span> <strong>Gruppe #' + (groupIndex + 1) + '</strong></div>';
                
                // Alle unterschiedlichen Titel anzeigen
                if (group.titles && group.titles.length > 0) {
                    html += '<div class="fobi-group-titles-v2">' +
                        '<strong>Veranstaltungsbezeichnungen in dieser Gruppe:</strong>';
                    group.titles.forEach(function(title) {
                        html += '<div class="fobi-title-item-v2">📌 ' + escapeHtml(title) + '</div>';
                    });
                    html += '</div>';
                }
                
                html += '</div>'; // title-wrapper
                
                html += '<div class="fobi-group-meta-v2">' +
                    '<span class="fobi-similarity-badge-v2">⌀ ' + group.similarity + '% Ähnlichkeit</span>' +
                    '<span class="fobi-similarity-badge-v2">' + group.entries.length + ' Einträge</span>';
                
                if (group.locations && group.locations.length > 0) {
                    html += '<span class="fobi-similarity-badge-v2">📍 Orte: ' + group.locations.join(', ') + '</span>';
                }
                
                html += '</div></div>'; // meta + header
                
                // Body mit allen Einträgen
                html += '<div class="fobi-dedupe-group-body-v2"><div class="fobi-dedupe-entries-v2">';
                
                group.entries.forEach(function(entry, entryIndex) {
                    html += renderEntry(entry, groupIndex, entryIndex);
                });
                
                html += '</div></div>';
                
                // Aktionen
                html += '<div class="fobi-dedupe-group-actions-v2">' +
                    '<div><strong>Zu löschen:</strong> <span class="group-delete-count-v2" data-group="' + groupIndex + '">0</span> von ' + group.entries.length + '</div>' +
                    '<div><button class="button fobi-skip-group-btn-v2" data-group="' + groupIndex + '">' +
                    '<span class="dashicons dashicons-dismiss"></span> Gruppe überspringen</button></div>' +
                    '</div></div>';
            });
            
            $('#fobi-dedupe-results-v2').html(html);
            $('#fobi-dedupe-actions-v2').show();
            
            // Standard: Ersten Eintrag behalten
            $('.fobi-dedupe-group-v2').each(function() {
                var $group = $(this);
                var $firstRadio = $group.find('.fobi-dedupe-radio-v2').first();
                $firstRadio.prop('checked', true).trigger('change');
            });
        }
        
        function renderEntry(entry, groupIndex, entryIndex) {
            var html = '<div class="fobi-dedupe-item-v2" data-group="' + groupIndex + '" data-entry="' + entryIndex + '" data-post-id="' + entry.id + '">';
            
            html += '<div class="fobi-dedupe-radio-wrapper-v2">' +
                '<label class="fobi-dedupe-radio-label-v2">' +
                '<input type="radio" name="keep_group_v2_' + groupIndex + '" value="' + entryIndex + '" ' +
                'class="fobi-dedupe-radio-v2" data-group="' + groupIndex + '" data-entry="' + entryIndex + '"> ' +
                '<span>✓ Behalten</span></label></div>';
            
            // Titel des Eintrags prominent
            html += '<div class="fobi-entry-title-v2">' + escapeHtml(entry.title) + '</div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">ID:</div>' +
                '<div class="fobi-field-value-v2"><strong>#' + entry.id + '</strong></div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Datum:</div>' +
                '<div class="fobi-field-value-v2">' + escapeHtml(entry.date) + '</div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Ort:</div>' +
                '<div class="fobi-field-value-v2"><strong>' + escapeHtml(entry.location) + '</strong></div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Punkte:</div>' +
                '<div class="fobi-field-value-v2"><strong style="font-size:16px;color:#2271b1;">' + entry.points + '</strong></div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Art:</div>' +
                '<div class="fobi-field-value-v2">' + escapeHtml(entry.type) + '</div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Status:</div>' +
                '<div class="fobi-field-value-v2">' +
                (entry.approved ? 
                    '<span class="fobi-badge-v2 fobi-badge-approved-v2">✓ Freigegeben</span>' : 
                    '<span class="fobi-badge-v2 fobi-badge-pending-v2">⏳ Ausstehend</span>') +
                '</div></div>';
            
            html += '<div class="fobi-field-row-v2"><div class="fobi-field-label-v2">Erstellt:</div>' +
                '<div class="fobi-field-value-v2" style="font-size:11px;color:#999;">' + 
                escapeHtml(entry.created) + '</div></div>';
            
            html += '</div>';
            return html;
        }
        
        $(document).on('change', '.fobi-dedupe-radio-v2', function() {
            var $radio = $(this);
            var groupIndex = $radio.data('group');
            
            if (skippedGroups.has(groupIndex)) return;
            
            $('.fobi-dedupe-item-v2[data-group="' + groupIndex + '"]')
                .removeClass('selected will-delete')
                .find('.fobi-delete-indicator-v2, .fobi-keep-indicator-v2').remove();
            
            var deleteCount = 0;
            var $allRadios = $('input[name="keep_group_v2_' + groupIndex + '"]');
            
            $allRadios.each(function() {
                var $r = $(this);
                var eIdx = $r.data('entry');
                var $item = $('.fobi-dedupe-item-v2[data-group="' + groupIndex + '"][data-entry="' + eIdx + '"]');
                
                if ($r.is(':checked')) {
                    $item.addClass('selected').prepend('<div class="fobi-keep-indicator-v2">✓ BEHALTEN</div>');
                } else {
                    $item.addClass('will-delete').prepend('<div class="fobi-delete-indicator-v2">✗ LÖSCHEN</div>');
                    deleteCount++;
                }
            });
            
            $('.group-delete-count-v2[data-group="' + groupIndex + '"]').text(deleteCount);
        });
        
        $(document).on('click', '.fobi-skip-group-btn-v2', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var groupIndex = $btn.data('group');
            var $group = $('#group-v2-' + groupIndex);
            
            if (skippedGroups.has(groupIndex)) {
                skippedGroups.delete(groupIndex);
                $group.removeClass('group-skipped');
                $btn.html('<span class="dashicons dashicons-dismiss"></span> Gruppe überspringen');
                var $firstRadio = $group.find('.fobi-dedupe-radio-v2').first();
                $firstRadio.prop('checked', true).trigger('change');
            } else {
                skippedGroups.add(groupIndex);
                $group.addClass('group-skipped');
                $btn.html('<span class="dashicons dashicons-update"></span> Gruppe wieder aktivieren');
                $group.find('.fobi-dedupe-item-v2').removeClass('selected will-delete')
                    .find('.fobi-delete-indicator-v2, .fobi-keep-indicator-v2').remove();
                $group.find('.fobi-dedupe-radio-v2').prop('checked', false);
                $('.group-delete-count-v2[data-group="' + groupIndex + '"]').text('0');
            }
        });
        
        $('#fobi-dedupe-select-all-keep-approved-v2').on('click', function(e) {
            e.preventDefault();
            
            $('.fobi-dedupe-group-v2').each(function() {
                var $group = $(this);
                var groupIndex = $group.data('group-index');
                if (skippedGroups.has(groupIndex)) return;
                
                var $approvedRadio = null;
                $('input[name="keep_group_v2_' + groupIndex + '"]').each(function() {
                    var $radio = $(this);
                    var entryIdx = $radio.data('entry');
                    var entry = currentDuplicates[groupIndex].entries[entryIdx];
                    
                    if (entry.approved && !$approvedRadio) {
                        $approvedRadio = $radio;
                        return false;
                    }
                });
                
                if ($approvedRadio) {
                    $approvedRadio.prop('checked', true).trigger('change');
                }
            });
            
            showMessage('✓ Freigegebene ausgewählt', 'success');
        });
        
        $('#fobi-dedupe-select-all-keep-older-v2').on('click', function(e) {
            e.preventDefault();
            
            $('.fobi-dedupe-group-v2').each(function() {
                var $group = $(this);
                var groupIndex = $group.data('group-index');
                if (skippedGroups.has(groupIndex)) return;
                
                var oldestIndex = 0;
                var oldestDate = null;
                
                currentDuplicates[groupIndex].entries.forEach(function(entry, idx) {
                    var entryDate = new Date(entry.created);
                    if (!oldestDate || entryDate < oldestDate) {
                        oldestDate = entryDate;
                        oldestIndex = idx;
                    }
                });
                
                var $oldestRadio = $('.fobi-dedupe-radio-v2[data-group="' + groupIndex + '"][data-entry="' + oldestIndex + '"]');
                $oldestRadio.prop('checked', true).trigger('change');
            });
            
            showMessage('✓ Älteste ausgewählt', 'success');
        });
        
        $('#fobi-dedupe-select-all-keep-higher-points-v2').on('click', function(e) {
            e.preventDefault();
            
            $('.fobi-dedupe-group-v2').each(function() {
                var $group = $(this);
                var groupIndex = $group.data('group-index');
                if (skippedGroups.has(groupIndex)) return;
                
                var highestIndex = 0;
                var highestPoints = 0;
                
                currentDuplicates[groupIndex].entries.forEach(function(entry, idx) {
                    var points = parseFloat(entry.points.replace(',', '.'));
                    if (points > highestPoints) {
                        highestPoints = points;
                        highestIndex = idx;
                    }
                });
                
                var $highestRadio = $('.fobi-dedupe-radio-v2[data-group="' + groupIndex + '"][data-entry="' + highestIndex + '"]');
                $highestRadio.prop('checked', true).trigger('change');
            });
            
            showMessage('✓ Höchste Punkte ausgewählt', 'success');
        });
        
        $('#fobi-dedupe-delete-selected-v2').on('click', function(e) {
            e.preventDefault();
            
            var toDelete = [];
            
            $('.fobi-dedupe-group-v2').each(function() {
                var $group = $(this);
                var groupIndex = $group.data('group-index');
                if (skippedGroups.has(groupIndex)) return;
                
                $('.fobi-dedupe-item-v2[data-group="' + groupIndex + '"].will-delete').each(function() {
                    toDelete.push(parseInt($(this).data('post-id')));
                });
            });
            
            if (toDelete.length === 0) {
                alert('Keine Einträge zum Löschen ausgewählt.');
                return;
            }
            
            var processedGroups = $('.fobi-dedupe-group-v2').length - skippedGroups.size;
            
            if (!confirm('Möchten Sie ' + toDelete.length + ' Einträge aus ' + processedGroups + ' Gruppe(n) wirklich löschen?\n\n' + skippedGroups.size + ' Gruppe(n) bleiben unverändert.')) {
                return;
            }
            
            showMessage('🗑️ Lösche ' + toDelete.length + ' Einträge...', 'info');
            $(this).prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'fobi_delete_selected_duplicates_v2',
                _wpnonce: nonce,
                post_ids: toDelete
            }, function(resp) {
                if (resp && resp.success) {
                    showMessage('✓ ' + resp.data.deleted + ' Einträge gelöscht!', 'success');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showMessage('✗ Fehler: ' + (resp.data && resp.data.message ? resp.data.message : 'Unbekannt'), 'error');
                    $('#fobi-dedupe-delete-selected-v2').prop('disabled', false);
                }
            }).fail(function() {
                showMessage('✗ HTTP-Fehler', 'error');
                $('#fobi-dedupe-delete-selected-v2').prop('disabled', false);
            });
        });
        
        function showMessage(text, type) {
            var colors = {
                success: '#00a32a',
                error: '#d63638',
                info: '#2271b1'
            };
            $('#fobi-dedupe-action-msg-v2').text(text).css('color', colors[type] || '#000');
            if (type === 'success' || type === 'error') {
                setTimeout(function() { $('#fobi-dedupe-action-msg-v2').text(''); }, 3000);
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    });
    </script>
    <?php
}

/* ============================================================
 * AJAX: Intelligente Doubletten-Suche mit Gruppierung
 * ============================================================ */
add_action('wp_ajax_fobi_find_manual_duplicates_v2', 'fobi_find_manual_duplicates_v2_ajax');
function fobi_find_manual_duplicates_v2_ajax() {
    check_ajax_referer('fobi_manual_dedupe_v2');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Keine Berechtigung.'));
    }
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $similarity = isset($_POST['similarity']) ? sanitize_text_field($_POST['similarity']) : 'high';
    $ignore_location = isset($_POST['ignore_location']) && $_POST['ignore_location'] === '1';
    $ignore_date = isset($_POST['ignore_date']) && $_POST['ignore_date'] === '1';
    $exclude_online = isset($_POST['exclude_online']) && $_POST['exclude_online'] === '1';
    
    $threshold = 85;
    if ($similarity === 'exact') {
        $threshold = 100;
    } elseif ($similarity === 'medium') {
        $threshold = 70;
    }
    
    $args = array(
        'post_type' => 'fortbildung',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    if ($user_id > 0) {
        $args['meta_query'] = array(
            array(
                'key' => 'user',
                'value' => $user_id,
                'compare' => '='
            )
        );
    }
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        wp_send_json_error(array('message' => 'Keine Fortbildungen gefunden.'));
    }
    
    // Alle Einträge sammeln
    $all_entries = array();
    
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $uid = intval(get_post_meta($post_id, 'user', true));
        
        if (!$uid) continue;
        
        $location = trim((string) get_field('location', $post_id));
        $location_normalized = strtolower($location);
        $is_online = in_array($location_normalized, array('online', 'webinar', 'virtual', 'digital', 'e-learning'));
        
        // Online ausschließen wenn gewünscht
        if ($exclude_online && $is_online) continue;
        
        $entry = array(
            'id' => $post_id,
            'user_id' => $uid,
            'title' => get_the_title($post_id),
            'date' => fobi_format_date(get_field('date', $post_id)),
            'date_raw' => get_field('date', $post_id),
            'location' => $location,
            'location_normalized' => $location_normalized,
            'is_online' => $is_online,
            'points' => number_format((float) get_field('points', $post_id), 1, ',', '.'),
            'points_raw' => (float) get_field('points', $post_id),
            'type' => (string) get_field('type', $post_id),
            'approved' => fobi_is_freigegeben(get_field('freigegeben', $post_id)),
            'created' => get_post_field('post_date', $post_id)
        );
        
        $all_entries[] = $entry;
    }
    
    wp_reset_postdata();
    
    // Gruppierung: Alle ähnlichen Einträge zusammenfassen
    $groups = array();
    $processed = array();
    
    for ($i = 0; $i < count($all_entries); $i++) {
        if (isset($processed[$i])) continue;
        
        $group = array($all_entries[$i]);
        $processed[$i] = true;
        
        for ($j = $i + 1; $j < count($all_entries); $j++) {
            if (isset($processed[$j])) continue;
            
            // Prüfe ob gleicher Benutzer
            if ($all_entries[$i]['user_id'] !== $all_entries[$j]['user_id']) continue;
            
            // Prüfe Ort (wenn nicht ignoriert)
            if (!$ignore_location) {
                if ($all_entries[$i]['location_normalized'] !== $all_entries[$j]['location_normalized']) {
                    continue;
                }
            }
            
            // Prüfe Datum (wenn nicht ignoriert)
            if (!$ignore_date) {
                if ($all_entries[$i]['date_raw'] !== $all_entries[$j]['date_raw']) {
                    continue;
                }
            }
            
            // Prüfe Titel-Ähnlichkeit
            $sim = fobi_calculate_similarity_v2($all_entries[$i]['title'], $all_entries[$j]['title']);
            
            if ($sim >= $threshold) {
                $group[] = $all_entries[$j];
                $processed[$j] = true;
            }
        }
        
        // Nur Gruppen mit mindestens 2 Einträgen
        if (count($group) >= 2) {
            $groups[] = $group;
        }
    }
    
    // Gruppen aufbereiten
    $duplicates = array();
    $total_entries = 0;
    
    foreach ($groups as $group) {
        // Durchschnittliche Ähnlichkeit berechnen
        $total_sim = 0;
        $count_sim = 0;
        for ($a = 0; $a < count($group); $a++) {
            for ($b = $a + 1; $b < count($group); $b++) {
                $total_sim += fobi_calculate_similarity_v2($group[$a]['title'], $group[$b]['title']);
                $count_sim++;
            }
        }
        $avg_sim = $count_sim > 0 ? round($total_sim / $count_sim) : 100;
        
        // Alle unterschiedlichen Titel sammeln
        $titles = array();
        $locations = array();
        foreach ($group as $entry) {
            if (!in_array($entry['title'], $titles)) {
                $titles[] = $entry['title'];
            }
            if (!in_array($entry['location'], $locations)) {
                $locations[] = $entry['location'];
            }
        }
        
        $duplicates[] = array(
            'titles' => $titles,
            'locations' => $locations,
            'similarity' => $avg_sim,
            'entries' => $group
        );
        
        $total_entries += count($group);
    }
    
    // Sortiere nach erster Titel
    usort($duplicates, function($a, $b) {
        return strcmp($a['titles'][0], $b['titles'][0]);
    });
    
    wp_send_json_success(array(
        'duplicates' => $duplicates,
        'total_entries' => $total_entries,
        'groups' => count($duplicates)
    ));
}

/* ============================================================
 * AJAX: Löschen
 * ============================================================ */
add_action('wp_ajax_fobi_delete_selected_duplicates_v2', 'fobi_delete_selected_duplicates_v2_ajax');
function fobi_delete_selected_duplicates_v2_ajax() {
    check_ajax_referer('fobi_manual_dedupe_v2');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Keine Berechtigung.'));
    }
    
    $post_ids = isset($_POST['post_ids']) ? (array) $_POST['post_ids'] : array();
    $post_ids = array_map('intval', $post_ids);
    $post_ids = array_filter($post_ids);
    
    if (empty($post_ids)) {
        wp_send_json_error(array('message' => 'Keine IDs übergeben.'));
    }
    
    $deleted = 0;
    $errors = array();
    
    foreach ($post_ids as $post_id) {
        if (get_post_type($post_id) !== 'fortbildung') {
            $errors[] = "ID $post_id ist keine Fortbildung.";
            continue;
        }
        
        $result = wp_delete_post($post_id, true);
        
        if ($result) {
            $deleted++;
        } else {
            $errors[] = "Fehler beim Löschen von ID $post_id.";
        }
    }
    
    wp_send_json_success(array(
        'deleted' => $deleted,
        'errors' => $errors,
        'message' => "$deleted Einträge gelöscht."
    ));
}

/* ============================================================
 * Hilfsfunktion: Ähnlichkeit
 * ============================================================ */
if (!function_exists('fobi_calculate_similarity_v2')) {
    function fobi_calculate_similarity_v2($str1, $str2) {
        $str1 = strtolower(trim(preg_replace('/\s+/', ' ', $str1)));
        $str2 = strtolower(trim(preg_replace('/\s+/', ' ', $str2)));
        
        if ($str1 === $str2) {
            return 100;
        }
        
        if (strlen($str1) <= 255 && strlen($str2) <= 255) {
            $distance = levenshtein($str1, $str2);
            $maxLen = max(strlen($str1), strlen($str2));
            
            if ($maxLen > 0) {
                $similarity = (1 - ($distance / $maxLen)) * 100;
                return max(0, $similarity);
            }
        }
        
        similar_text($str1, $str2, $percent);
        return $percent;
    }
}