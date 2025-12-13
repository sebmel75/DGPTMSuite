<?php
/*
Plugin Name: Seiten Rollenbasierte Anzeige (Erweitert)
Description: Zeigt oder versteckt Seiten mit Toggle-Steuerung. Toggle AN = nur angemeldete Benutzer. Mit Kriterien = nur Benutzer mit mindestens einem erfüllten Kriterium.
Version: 2.3
Author: Sebastian
*
* Changelog 2.3:
* - IMPROVED: ACF-Berechtigungen werden jetzt automatisch aus der Feldgruppe "Berechtigungen" (group_6792060047841) geladen
* - ADDED: Dynamisches Laden aller True/False Felder aus ACF
* - ADDED: Fallback-Liste wenn ACF-Feldgruppe nicht gefunden wird
* - ADDED: Statusanzeige (Anzahl geladener Felder oder Fallback-Warnung)
* - FIXED: "Webinar" fehlte in der Liste und wird jetzt automatisch erkannt
*/

// Direkten Aufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   ADMIN-Bereich: Meta-Box hinzufügen und speichern
   ========================================================================== */

// Meta-Box im Seiten-Editor hinzufügen
function rbr_add_meta_box() {
    add_meta_box(
        'rbr_meta_box',
        'Erweiterte Zugriffssteuerung',
        'rbr_meta_box_callback',
        'page',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'rbr_add_meta_box' );

// Inhalt der Meta-Box
function rbr_meta_box_callback( $post ) {
    // Sicherheits-Nonce
    wp_nonce_field( 'rbr_save_meta_box', 'rbr_meta_box_nonce' );
    
    // Bestehende Werte laden
    $restrict_access = get_post_meta( $post->ID, 'rbr_restrict_access', true );
    $allowed_roles = get_post_meta( $post->ID, 'rbr_allowed_roles', true );
    if ( ! is_array( $allowed_roles ) ) {
        $allowed_roles = array();
    }
    
    $allowed_acf_fields = get_post_meta( $post->ID, 'rbr_allowed_acf_fields', true );
    if ( ! is_array( $allowed_acf_fields ) ) {
        $allowed_acf_fields = array();
    }
    
    $allowed_users = get_post_meta( $post->ID, 'rbr_allowed_users', true );
    if ( ! is_array( $allowed_users ) ) {
        $allowed_users = array();
    }
    
    $redirect_page = get_post_meta( $post->ID, 'rbr_redirect_page', true );
    
    // Styles für bessere Darstellung
    echo '<style>
        .rbr-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .rbr-section:last-child { border-bottom: none; }
        .rbr-section-title { font-weight: bold; margin-bottom: 10px; display: block; }
        .rbr-checkbox-group label { display: block; margin-bottom: 5px; }
        .rbr-info { background: #f0f0f1; padding: 10px; margin-top: 10px; font-size: 12px; border-left: 3px solid #2271b1; }
        .rbr-info-important { background: #fff3cd; padding: 10px; margin-top: 10px; font-size: 12px; border-left: 3px solid #ffc107; }
        .rbr-user-select { width: 100%; min-height: 100px; }
        .rbr-toggle-section { background: #e8f4f8; padding: 12px; margin-bottom: 15px; border-radius: 4px; }
        .rbr-criteria-section { opacity: 0.5; pointer-events: none; transition: opacity 0.3s; }
        .rbr-criteria-section.active { opacity: 1; pointer-events: auto; }
    </style>';
    
    // JavaScript für Toggle-Funktionalität
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var toggle = document.getElementById("rbr_restrict_access");
            var criteriaSection = document.getElementById("rbr_criteria_section");
            
            function updateCriteriaSection() {
                if (toggle && criteriaSection) {
                    if (toggle.checked) {
                        criteriaSection.classList.add("active");
                    } else {
                        criteriaSection.classList.remove("active");
                    }
                }
            }
            
            if (toggle) {
                toggle.addEventListener("change", updateCriteriaSection);
                updateCriteriaSection();
            }
        });
    </script>';
    
    // ==========================================================================
    // HAUPT-TOGGLE: Seitenzugriff einschränken
    // ==========================================================================
    echo '<div class="rbr-toggle-section">';
    echo '<label style="display: flex; align-items: center; font-weight: bold; font-size: 14px;">';
    echo '<input type="checkbox" id="rbr_restrict_access" name="rbr_restrict_access" value="1" ' . checked( $restrict_access, '1', false ) . ' style="margin-right: 8px;" />';
    echo 'Seitenzugriff einschränken';
    echo '</label>';
    echo '</div>';
    
    echo '<div class="rbr-info-important">
        <strong>⚠️ WICHTIG - Funktionsweise:</strong><br><br>
        <strong>Toggle AUS:</strong><br>
        → Seite ist öffentlich (für alle zugänglich)<br><br>
        <strong>Toggle AN (ohne Kriterien):</strong><br>
        → Seite nur für <u>angemeldete Benutzer</u><br>
        → Alle eingeloggten Benutzer haben Zugriff<br><br>
        <strong>Toggle AN (mit Kriterien):</strong><br>
        → Seite nur für <u>angemeldete Benutzer</u><br>
        → PLUS: Mindestens ein Kriterium muss erfüllt sein<br>
        → Die Kriterien sind ODER-verknüpft (ein Kriterium genügt)
    </div>';
    
    // Wrapper für alle Kriterien (wird ein-/ausgeblendet)
    $criteria_class = $restrict_access == '1' ? 'active' : '';
    echo '<div id="rbr_criteria_section" class="rbr-criteria-section ' . $criteria_class . '">';
    
    echo '<div class="rbr-info" style="margin-bottom: 15px;">
        <strong>Zusätzliche Zugangskriterien (optional):</strong><br>
        Wenn Sie hier Kriterien auswählen, muss der Benutzer zusätzlich zur Anmeldung mindestens EINES dieser Kriterien erfüllen.
    </div>';
    
    // ==========================================================================
    // SEKTION 1: Rollen
    // ==========================================================================
    echo '<div class="rbr-section">';
    echo '<span class="rbr-section-title">1. Zugriff nach Benutzerrollen:</span>';
    
    global $wp_roles;
    $roles = $wp_roles->roles;
    
    echo '<div class="rbr-checkbox-group">';
    foreach ( $roles as $role_key => $role_details ) {
        $checked = in_array( $role_key, $allowed_roles ) ? 'checked="checked"' : '';
        echo '<label><input type="checkbox" name="rbr_allowed_roles[]" value="' . esc_attr( $role_key ) . '" ' . $checked . ' /> ' . esc_html( $role_details['name'] ) . '</label>';
    }
    echo '</div>';
    echo '</div>';
    
    // ==========================================================================
    // SEKTION 2: ACF-Berechtigungen (dynamisch aus ACF-Feldgruppe laden)
    // ==========================================================================
    echo '<div class="rbr-section">';
    echo '<span class="rbr-section-title">2. Zugriff nach ACF-Berechtigungen:</span>';

    // ACF-Berechtigungsfelder dynamisch aus der Feldgruppe "Berechtigungen" laden
    $acf_permission_fields = array();

    if ( function_exists( 'acf_get_field_groups' ) ) {
        // Suche nach der Feldgruppe "Berechtigungen"
        $field_groups = acf_get_field_groups( array(
            'title' => 'Berechtigungen'
        ) );

        // Wenn keine Gruppe mit dem Titel gefunden wurde, versuche mit der key
        if ( empty( $field_groups ) ) {
            $field_groups = acf_get_field_groups();
            foreach ( $field_groups as $group ) {
                if ( $group['key'] === 'group_6792060047841' || $group['title'] === 'Berechtigungen' ) {
                    $field_groups = array( $group );
                    break;
                }
            }
        }

        // Felder aus der Gruppe laden
        if ( ! empty( $field_groups ) ) {
            foreach ( $field_groups as $field_group ) {
                $fields = acf_get_fields( $field_group['key'] );

                if ( $fields ) {
                    foreach ( $fields as $field ) {
                        // Nur True/False Felder berücksichtigen
                        if ( $field['type'] === 'true_false' ) {
                            $acf_permission_fields[ $field['name'] ] = $field['label'];
                        }
                    }
                }
            }
        }
    }

    // Fallback: Wenn keine ACF-Felder gefunden wurden, zeige hardcodierte Liste
    if ( empty( $acf_permission_fields ) ) {
        $acf_permission_fields = array(
            'news_schreiben' => 'News schreiben',
            'news_alle' => 'Alle News bearbeiten',
            'stellenanzeigen_anlegen' => 'Stellenanzeigen anlegen/bearbeiten',
            'testbereich' => 'Testbereiche zeigen',
            'quizz_verwalten' => 'Quizze verwalten',
            'delegate' => 'EBCP-Delegierter',
            'timeline' => 'Timeline',
            'mitgliederversammlung' => 'Online Abstimmen',
            'checkliste' => 'Checkliste',
            'checkliste_erstellen' => 'Checkliste Erstellen',
            'checkliste_template_erstellen' => 'Checkliste Template Erstellen',
            'webinar' => 'Webinare',
        );
        echo '<p style="font-size: 11px; color: #dc3545; margin-top: 0;"><em>⚠️ ACF-Feldgruppe "Berechtigungen" nicht gefunden - verwende Fallback-Liste</em></p>';
    } else {
        // Sortiere alphabetisch nach Label
        asort( $acf_permission_fields );
        echo '<p style="font-size: 11px; color: #28a745; margin-top: 0;"><em>✓ ' . count( $acf_permission_fields ) . ' Berechtigungsfelder aus ACF geladen</em></p>';
    }

    echo '<div class="rbr-checkbox-group">';
    foreach ( $acf_permission_fields as $field_name => $field_label ) {
        $checked = in_array( $field_name, $allowed_acf_fields ) ? 'checked="checked"' : '';
        echo '<label><input type="checkbox" name="rbr_allowed_acf_fields[]" value="' . esc_attr( $field_name ) . '" ' . $checked . ' /> ' . esc_html( $field_label ) . '</label>';
    }
    echo '</div>';
    echo '</div>';
    
    // ==========================================================================
    // SEKTION 3: Einzelne Benutzer
    // ==========================================================================
    echo '<div class="rbr-section">';
    echo '<span class="rbr-section-title">3. Zugriff für einzelne Benutzer:</span>';
    echo '<p style="font-size: 12px; color: #666; margin-top: 0;">Wählen Sie einzelne Benutzer aus, die Zugriff haben sollen:</p>';
    
    // Alle Benutzer laden
    $all_users = get_users( array( 'orderby' => 'display_name' ) );
    
    echo '<select name="rbr_allowed_users[]" multiple="multiple" class="rbr-user-select" size="8">';
    foreach ( $all_users as $user ) {
        $selected = in_array( $user->ID, $allowed_users ) ? 'selected="selected"' : '';
        $user_info = $user->display_name . ' (' . $user->user_email . ')';
        echo '<option value="' . esc_attr( $user->ID ) . '" ' . $selected . '>' . esc_html( $user_info ) . '</option>';
    }
    echo '</select>';
    echo '<p style="font-size: 11px; color: #666; margin-top: 5px;"><em>Tipp: Strg/Cmd gedrückt halten für Mehrfachauswahl</em></p>';
    echo '</div>';
    
    echo '</div>'; // Ende rbr_criteria_section
    
    // ==========================================================================
    // SEKTION 4: Umleitungsseite (immer sichtbar)
    // ==========================================================================
    echo '<div class="rbr-section">';
    echo '<span class="rbr-section-title">Umleitungsseite (optional):</span>';
    echo '<p style="font-size: 12px; color: #666; margin-top: 0;">Wohin sollen Benutzer ohne Berechtigung umgeleitet werden?</p>';
    
    $pages = get_pages();
    echo '<select name="rbr_redirect_page" style="width: 100%;">';
    echo '<option value="0"' . selected( $redirect_page, 0, false ) . '>Standard-Fehlerseite (403)</option>';
    foreach ( $pages as $page ) {
        echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $redirect_page, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

/* ==========================================================================
   Meta-Box Daten speichern
   ========================================================================== */

function rbr_save_meta_box( $post_id ) {
    // Nonce prüfen
    if ( ! isset( $_POST['rbr_meta_box_nonce'] ) ) {
        return;
    }
    
    if ( ! wp_verify_nonce( $_POST['rbr_meta_box_nonce'], 'rbr_save_meta_box' ) ) {
        return;
    }
    
    // Autosave überspringen
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // Berechtigungen prüfen
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    } else {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }
    
    // 0. Haupt-Toggle speichern
    if ( isset( $_POST['rbr_restrict_access'] ) && $_POST['rbr_restrict_access'] == '1' ) {
        update_post_meta( $post_id, 'rbr_restrict_access', '1' );
    } else {
        delete_post_meta( $post_id, 'rbr_restrict_access' );
    }
    
    // 1. Erlaubte Rollen speichern oder löschen
    if ( isset( $_POST['rbr_allowed_roles'] ) && ! empty( $_POST['rbr_allowed_roles'] ) ) {
        $allowed_roles = array_map( 'sanitize_text_field', $_POST['rbr_allowed_roles'] );
        update_post_meta( $post_id, 'rbr_allowed_roles', $allowed_roles );
    } else {
        delete_post_meta( $post_id, 'rbr_allowed_roles' );
    }
    
    // 2. Erlaubte ACF-Felder speichern oder löschen
    if ( isset( $_POST['rbr_allowed_acf_fields'] ) && ! empty( $_POST['rbr_allowed_acf_fields'] ) ) {
        $allowed_acf_fields = array_map( 'sanitize_text_field', $_POST['rbr_allowed_acf_fields'] );
        update_post_meta( $post_id, 'rbr_allowed_acf_fields', $allowed_acf_fields );
    } else {
        delete_post_meta( $post_id, 'rbr_allowed_acf_fields' );
    }
    
    // 3. Erlaubte Benutzer speichern oder löschen
    if ( isset( $_POST['rbr_allowed_users'] ) && ! empty( $_POST['rbr_allowed_users'] ) ) {
        $allowed_users = array_map( 'intval', $_POST['rbr_allowed_users'] );
        update_post_meta( $post_id, 'rbr_allowed_users', $allowed_users );
    } else {
        delete_post_meta( $post_id, 'rbr_allowed_users' );
    }
    
    // 4. Umleitungsseite speichern
    if ( isset( $_POST['rbr_redirect_page'] ) ) {
        $redirect_page = intval( $_POST['rbr_redirect_page'] );
        if ( $redirect_page > 0 ) {
            update_post_meta( $post_id, 'rbr_redirect_page', $redirect_page );
        } else {
            delete_post_meta( $post_id, 'rbr_redirect_page' );
        }
    }
}
add_action( 'save_post', 'rbr_save_meta_box' );

/* ==========================================================================
   FRONTEND: Zugriff basierend auf den Einstellungen prüfen
   ========================================================================== */

function rbr_restrict_page_access() {
    // Im Admin-Bereich nichts tun
    if ( is_admin() ) {
        return;
    }
    
    // Nur auf Seiten prüfen
    if ( is_page() ) {
        global $post;
        if ( ! $post ) {
            return;
        }
        
        // Hauptschalter prüfen
        $restrict_access = get_post_meta( $post->ID, 'rbr_restrict_access', true );
        
        // Wenn Toggle AUS ist, ist die Seite für alle zugänglich
        if ( $restrict_access != '1' ) {
            return;
        }
        
        // ======================================================================
        // AB HIER: Toggle ist AN - Seite ist nur für angemeldete Benutzer
        // ======================================================================
        
        // Erste Prüfung: Ist der Benutzer überhaupt angemeldet?
        if ( ! is_user_logged_in() ) {
            rbr_deny_access( $post->ID, 'Zugriff verweigert. Diese Seite ist nur für angemeldete Benutzer zugänglich.' );
            return;
        }
        
        // Benutzer IST angemeldet - nun prüfen wir ob zusätzliche Kriterien definiert sind
        
        // Alle Berechtigungseinstellungen laden
        $allowed_roles = get_post_meta( $post->ID, 'rbr_allowed_roles', true );
        $allowed_acf_fields = get_post_meta( $post->ID, 'rbr_allowed_acf_fields', true );
        $allowed_users = get_post_meta( $post->ID, 'rbr_allowed_users', true );
        
        // Sicherstellen, dass Arrays vorhanden sind
        if ( ! is_array( $allowed_roles ) ) {
            $allowed_roles = array();
        }
        if ( ! is_array( $allowed_acf_fields ) ) {
            $allowed_acf_fields = array();
        }
        if ( ! is_array( $allowed_users ) ) {
            $allowed_users = array();
        }
        
        // FALL 1: Keine zusätzlichen Kriterien definiert
        // -> ALLE angemeldeten Benutzer haben Zugriff
        if ( empty( $allowed_roles ) && empty( $allowed_acf_fields ) && empty( $allowed_users ) ) {
            // Benutzer ist angemeldet und keine weiteren Kriterien -> Zugriff erlaubt
            return;
        }
        
        // ======================================================================
        // FALL 2: Zusätzliche Kriterien sind definiert
        // -> Benutzer muss mindestens EINES der Kriterien erfüllen (ODER-Verknüpfung)
        // ======================================================================
        
        // Aktuellen Nutzer abrufen
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $user_roles = (array) $user->roles;
        
        $access_granted = false;
        
        // PRÜFUNG 1: Ist der Benutzer in der Liste der erlaubten einzelnen Benutzer?
        if ( ! empty( $allowed_users ) && in_array( $user_id, $allowed_users, true ) ) {
            $access_granted = true;
        }
        
        // PRÜFUNG 2: Hat der Benutzer eine der erlaubten Rollen?
        if ( ! $access_granted && ! empty( $allowed_roles ) ) {
            foreach ( $allowed_roles as $role ) {
                if ( in_array( $role, $user_roles, true ) ) {
                    $access_granted = true;
                    break;
                }
            }
        }
        
        // PRÜFUNG 3: Hat der Benutzer eines der erlaubten ACF-Felder aktiviert?
        if ( ! $access_granted && ! empty( $allowed_acf_fields ) && function_exists( 'get_field' ) ) {
            foreach ( $allowed_acf_fields as $acf_field ) {
                $field_value = get_field( $acf_field, 'user_' . $user_id );
                // Prüfen, ob das Feld auf true gesetzt ist
                if ( $field_value == true || $field_value == 1 || $field_value === '1' ) {
                    $access_granted = true;
                    break;
                }
            }
        }
        
        // Wenn keine Berechtigung erfüllt ist, Zugriff verweigern
        if ( ! $access_granted ) {
            rbr_deny_access( $post->ID, 'Zugriff verweigert. Sie sind angemeldet, erfüllen aber nicht die erforderlichen Zugangskriterien für diese Seite.' );
        }
    }
}
add_action( 'template_redirect', 'rbr_restrict_page_access' );

/**
 * Hilfsfunktion: Zugriff verweigern (entweder umleiten oder Fehler anzeigen)
 */
function rbr_deny_access( $post_id, $error_message ) {
    $redirect_page = get_post_meta( $post_id, 'rbr_redirect_page', true );
    
    if ( $redirect_page ) {
        wp_redirect( get_permalink( $redirect_page ) );
        exit;
    } else {
        wp_die( 
            $error_message,
            'Zugriff verweigert',
            array( 'response' => 403 )
        );
    }
}

/* ==========================================================================
   ADMIN-Hinweis: ACF muss installiert sein
   ========================================================================== */

function rbr_admin_notice_acf() {
    if ( ! function_exists( 'get_field' ) ) {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Seiten Rollenbasierte Anzeige:</strong> Für die volle Funktionalität (ACF-Berechtigungen) muss das Plugin "Advanced Custom Fields" installiert und aktiviert sein.</p>
        </div>';
    }
}
add_action( 'admin_notices', 'rbr_admin_notice_acf' );