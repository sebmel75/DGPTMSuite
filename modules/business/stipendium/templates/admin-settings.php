<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>Stipendium — Einstellungen</h1>

    <div id="dgptm-stipendium-settings-app">
        <form id="dgptm-stipendium-settings-form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

            <h2>Stipendientypen & Bewerbungszeitraeume</h2>
            <table class="widefat" id="stipendientypen-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bezeichnung</th>
                        <th>Aktuelle Runde</th>
                        <th>Bewerbung Start</th>
                        <th>Bewerbung Ende</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings['stipendientypen'] as $i => $typ) : ?>
                    <tr data-index="<?php echo $i; ?>">
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][id]" value="<?php echo esc_attr($typ['id']); ?>" class="regular-text" readonly></td>
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][bezeichnung]" value="<?php echo esc_attr($typ['bezeichnung']); ?>" class="regular-text"></td>
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][runde]" value="<?php echo esc_attr($typ['runde']); ?>" class="regular-text" placeholder="z.B. Ausschreibung 2026"></td>
                        <td><input type="date" name="stipendientypen[<?php echo $i; ?>][start]" value="<?php echo esc_attr($typ['start']); ?>"></td>
                        <td><input type="date" name="stipendientypen[<?php echo $i; ?>][ende]" value="<?php echo esc_attr($typ['ende']); ?>"></td>
                        <td><button type="button" class="button remove-typ" title="Entfernen">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-stipendientyp">+ Stipendientyp hinzufuegen</button></p>

            <hr>
            <h2>Verfahrenseinstellungen</h2>
            <table class="form-table">
                <tr>
                    <th>Freigabe-Modus</th>
                    <td>
                        <select name="freigabe_modus">
                            <option value="vorsitz" <?php selected($settings['freigabe_modus'], 'vorsitz'); ?>>Freigabe durch Vorsitzenden</option>
                            <option value="direkt" <?php selected($settings['freigabe_modus'], 'direkt'); ?>>Direktzugriff (alle sehen sofort)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Gleichstand-Regel</th>
                    <td>
                        <select name="gleichstand_regel">
                            <option value="rubrik_a" <?php selected($settings['gleichstand_regel'], 'rubrik_a'); ?>>Rubrik A entscheidet</option>
                            <option value="mehrheit" <?php selected($settings['gleichstand_regel'], 'mehrheit'); ?>>Mehrheitsentscheid</option>
                            <option value="manuell" <?php selected($settings['gleichstand_regel'], 'manuell'); ?>>Manuell (Vorsitzender entscheidet)</option>
                        </select>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>DSGVO / Loeschfristen</h2>
            <table class="form-table">
                <tr>
                    <th>Loeschfrist (nicht vergeben)</th>
                    <td><input type="number" name="loeschfrist_monate_nicht_vergeben" value="<?php echo esc_attr($settings['loeschfrist_monate_nicht_vergeben']); ?>" min="1" max="60"> Monate nach Rundenende</td>
                </tr>
                <tr>
                    <th>Loeschfrist (vergeben, abgeschlossen)</th>
                    <td><input type="number" name="loeschfrist_jahre_vergeben" value="<?php echo esc_attr($settings['loeschfrist_jahre_vergeben']); ?>" min="1" max="30"> Jahre nach Stipendiums-Abschluss</td>
                </tr>
                <tr>
                    <th>Automatische Loeschung</th>
                    <td><label><input type="checkbox" name="auto_loeschung" value="1" <?php checked($settings['auto_loeschung']); ?>> Daten nach Fristablauf automatisch loeschen (sonst nur Erinnerung)</label></td>
                </tr>
            </table>

            <hr>
            <h2>Integrationen</h2>
            <table class="form-table">
                <tr>
                    <th>WorkDrive Team-Folder ID</th>
                    <td><input type="text" name="workdrive_team_folder_id" value="<?php echo esc_attr($settings['workdrive_team_folder_id']); ?>" class="regular-text" placeholder="z.B. abc123def456"></td>
                </tr>
                <tr>
                    <th>E-Mail Vorsitzende/r</th>
                    <td><input type="email" name="benachrichtigung_vorsitz_email" value="<?php echo esc_attr($settings['benachrichtigung_vorsitz_email']); ?>" class="regular-text"></td>
                </tr>
            </table>

            <hr>
            <h2>E-Mail Eingangsbestaetigung</h2>
            <table class="form-table">
                <tr>
                    <th>Text</th>
                    <td>
                        <textarea name="bestaetigungsmail_text" rows="8" class="large-text"><?php echo esc_textarea($settings['bestaetigungsmail_text']); ?></textarea>
                        <p class="description">Platzhalter: {name}, {stipendientyp}, {runde}, {datum}</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Einstellungen speichern</button>
                <span id="dgptm-stipendium-save-status"></span>
            </p>
        </form>
    </div>

    <script>
    jQuery(function($) {
        // Speichern
        $('#dgptm-stipendium-settings-form').on('submit', function(e) {
            e.preventDefault();
            var formData = {};
            $(this).find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (!name || name === 'nonce') return;
                if ($(this).attr('type') === 'checkbox') {
                    formData[name] = $(this).is(':checked');
                } else {
                    formData[name] = $(this).val();
                }
            });

            // Stipendientypen als Array aufbauen
            var typen = [];
            $('#stipendientypen-table tbody tr').each(function() {
                var idx = $(this).data('index');
                typen.push({
                    id:          $(this).find('[name$="[id]"]').val(),
                    bezeichnung: $(this).find('[name$="[bezeichnung]"]').val(),
                    runde:       $(this).find('[name$="[runde]"]').val(),
                    start:       $(this).find('[name$="[start]"]').val(),
                    ende:        $(this).find('[name$="[ende]"]').val()
                });
            });
            formData.stipendientypen = typen;

            $('#dgptm-stipendium-save-status').text('Wird gespeichert...');
            $.post(ajaxurl, {
                action: 'dgptm_stipendium_save_settings',
                nonce: $('[name="nonce"]').val(),
                settings: JSON.stringify(formData)
            }, function(res) {
                $('#dgptm-stipendium-save-status').text(res.success ? 'Gespeichert!' : 'Fehler: ' + res.data);
                setTimeout(function() { $('#dgptm-stipendium-save-status').text(''); }, 3000);
            });
        });

        // Typ entfernen
        $(document).on('click', '.remove-typ', function() {
            if (confirm('Stipendientyp wirklich entfernen?')) {
                $(this).closest('tr').remove();
            }
        });

        // Typ hinzufuegen
        $('#add-stipendientyp').on('click', function() {
            var idx = $('#stipendientypen-table tbody tr').length;
            var row = '<tr data-index="' + idx + '">' +
                '<td><input type="text" name="stipendientypen[' + idx + '][id]" class="regular-text" placeholder="eindeutige_id"></td>' +
                '<td><input type="text" name="stipendientypen[' + idx + '][bezeichnung]" class="regular-text"></td>' +
                '<td><input type="text" name="stipendientypen[' + idx + '][runde]" class="regular-text" placeholder="z.B. Ausschreibung 2027"></td>' +
                '<td><input type="date" name="stipendientypen[' + idx + '][start]"></td>' +
                '<td><input type="date" name="stipendientypen[' + idx + '][ende]"></td>' +
                '<td><button type="button" class="button remove-typ">&times;</button></td>' +
                '</tr>';
            $('#stipendientypen-table tbody').append(row);
        });
    });
    </script>
</div>
