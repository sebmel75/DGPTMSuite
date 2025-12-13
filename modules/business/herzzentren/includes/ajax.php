<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_hzb_load_herzzentrum_edit_form', 'hzb_ajax_load_form' );
add_action( 'wp_ajax_hzb_save_herzzentrum_edit_form', 'hzb_ajax_save_form' );

/**
 * Formular laden (inkl. Auswahl, wenn mehrere bearbeitbare Herzzentren vorhanden)
 */
function hzb_ajax_load_form() {
	check_ajax_referer( 'hzb_editor', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __('Nicht eingeloggt.','dgptm-hzb') ), 403 );
	}

	$user_id = get_current_user_id();
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

	$editable_ids = hzb_get_user_editable_herzzentren( $user_id );

	if ( empty( $editable_ids ) ) {
		wp_send_json_error( array( 'message' => __('Keine Berechtigung für ein Herzzentrum.','dgptm-hzb') ), 403 );
	}

	// Wenn keine bestimmte ID kommt oder diese unzulässig ist → Auswahl.
	if ( $post_id <= 0 || ! in_array( $post_id, $editable_ids, true ) ) {

		// Wenn genau eines bearbeitet werden darf → direktes Formular
		if ( count( $editable_ids ) === 1 ) {
			$post_id = intval( $editable_ids[0] );
		} else {
			// Auswahl-HTML liefern
			$html  = '<div class="hzb-select-step">';
			$html .= '<h3>' . esc_html__('Bitte Herzzentrum wählen','dgptm-hzb') . '</h3>';
			$html .= '<div class="hzb-field"><label for="hzb-select-herzzentrum">'.esc_html__('Herzzentrum','dgptm-hzb').'</label>';
			$html .= '<select id="hzb-select-herzzentrum" class="hzb-select">';
			$posts = get_posts( array(
				'post_type'      => 'herzzentrum',
				'post__in'       => $editable_ids,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'post_status'    => array('publish','draft','pending','private'),
				'fields'         => 'ids',
			) );
			foreach ( $posts as $pid ) {
				$html .= '<option value="'.esc_attr($pid).'">'.esc_html( get_the_title($pid) ?: ('#'.$pid) ).'</option>';
			}
			$html .= '</select></div>';
			$html .= '<button class="button button-primary hzb-choose-hz">'.esc_html__('Weiter','dgptm-hzb').'</button>';
			$html .= '</div>';
			wp_send_json_success( array( 'html' => $html ) );
		}
	}

	// Berechtigung final prüfen
	if ( ! hzb_user_can_edit_herzzentrum( $user_id, $post_id ) ) {
		wp_send_json_error( array( 'message' => __('Keine Berechtigung für dieses Herzzentrum.','dgptm-hzb') ), 403 );
	}

	$html = hzb_render_edit_form( $post_id, $user_id );

	wp_send_json_success( array( 'html' => $html ) );
}

/**
 * Formular speichern
 */
function hzb_ajax_save_form() {
	check_ajax_referer( 'hzb_editor', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __('Nicht eingeloggt.','dgptm-hzb') ), 403 );
	}

	$user_id = get_current_user_id();
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

	if ( $post_id <= 0 ) {
		wp_send_json_error( array( 'message' => __('Ungültige Herzzentrum-ID.','dgptm-hzb') ), 400 );
	}

	if ( ! hzb_user_can_edit_herzzentrum( $user_id, $post_id ) ) {
		wp_send_json_error( array( 'message' => __('Keine Berechtigung.','dgptm-hzb') ), 403 );
	}

	$fields_in = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : array();

	// --- Fix: Checkboxen auch auf "0" setzen, wenn nicht im POST vorhanden ---
	$types = array(); // name => type
	if ( function_exists('acf_get_field_groups') && function_exists('acf_get_fields') ) {
		$groups = acf_get_field_groups( array('post_id'=>$post_id) );
		foreach ( $groups as $g ) {
			$fields = acf_get_fields( $g );
			if ( is_array($fields) ) {
				foreach ( $fields as $f ) {
					if ( ! empty($f['name']) && ! empty($f['type']) ) {
						$types[ $f['name'] ] = $f['type'];
					}
				}
			}
		}
	}

	// WYSIWYG-Overrides für bestimmte ACF-Textareas
	$wysiwyg_overrides = array('anschrift','aus-weiterbildung-ansprechpartner');

	// Checkboxen, die nicht gesendet wurden, explizit auf 0 setzen
	foreach ( $types as $fname => $ftype ) {
		if ( $ftype === 'true_false' && ! array_key_exists($fname, $fields_in) ) {
			$fields_in[$fname] = 0;
		}
	}

	// Geschützte Felder (nur mit "Alle Herzzentren bearbeiten")
	$restricted = array('bundesland','map-marker-latitude','map-marker-longitude','latitudelongitude');
	$can_edit_all = hzb_user_can_edit_all( $user_id );

	foreach ( $fields_in as $name_raw => $val ) {
		// Name säubern, aber Bindestriche erlauben (ACF-Namen enthalten '-')
		$name = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$name_raw );

		// Nur Felder speichern, die in ACF existieren
		if ( ! isset($types[$name]) ) {
			continue;
		}

		// Feldschutz durchsetzen
		if ( in_array( $name, $restricted, true ) && ! $can_edit_all ) {
			continue; // serverseitig blockieren
		}

		$type = isset($types[$name]) ? $types[$name] : 'text';
		// Override: gewisse Textareas als WYSIWYG behandeln
		if ( $type === 'textarea' && in_array( $name, $wysiwyg_overrides, true ) ) {
			$type = 'wysiwyg';
		}

		switch ( $type ) {
			case 'number':
				$clean = ( $val === '' ? null : intval( $val ) );
				break;
			case 'url':
				$clean = ( $val === '' ? '' : esc_url_raw( $val ) );
			 break;
			case 'true_false':
				$clean = ($val==='1' || $val===1 || $val==='true' || $val==='on') ? 1 : 0;
				break;
			case 'textarea':
			case 'text':
			case 'select':
				$clean = is_array($val) ? array_map('sanitize_text_field', $val) : sanitize_text_field($val);
				break;
			case 'wysiwyg':
				$clean = wp_kses_post( $val );
				break;
			case 'image':
				$clean = intval( $val ); // erwartet Attachment-ID
				break;
			default:
				$clean = is_array($val) ? array_map('sanitize_text_field', $val) : sanitize_text_field($val);
				break;
		}

		if ( function_exists('update_field') ) {
			update_field( $name, $clean, $post_id );
		} else {
			update_post_meta( $post_id, $name, $clean );
		}
	}

	wp_send_json_success( array( 'message' => __('Gespeichert.','dgptm-hzb') ) );
}

/**
 * HTML des Formulars rendern
 */
function hzb_render_edit_form( $post_id, $user_id ) {
	$restricted = array('bundesland','map-marker-latitude','map-marker-longitude','latitudelongitude');
	$can_edit_all = hzb_user_can_edit_all( $user_id );
	$wysiwyg_overrides = array('anschrift','aus-weiterbildung-ansprechpartner');

	$fields = array();
	if ( function_exists('acf_get_field_groups') && function_exists('acf_get_fields') ) {
		$groups = acf_get_field_groups( array('post_id'=>$post_id) );
		foreach ( $groups as $g ) {
			$fields_in_group = acf_get_fields( $g );
			if ( is_array( $fields_in_group ) ) {
				foreach ( $fields_in_group as $f ) {
					if ( empty($f['name']) ) continue;

					// Feld ggf. ausblenden
					if ( in_array( $f['name'], $restricted, true ) && ! $can_edit_all ) {
						continue;
					}

					// Override: bestimmte Textareas als WYSIWYG anzeigen
					if ( isset($f['type']) && $f['type'] === 'textarea' && in_array($f['name'], $wysiwyg_overrides, true) ) {
						$f['type'] = 'wysiwyg';
					}

					$fields[] = $f;
				}
			}
		}
	}

	ob_start(); ?>
	<form id="hzb-editor-form">
		<input type="hidden" name="action" value="hzb_save_herzzentrum_edit_form" />
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce('hzb_editor') ); ?>" />
		<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>" />

		<div class="hzb-fields">
			<?php
			if ( empty($fields) ) {
				echo '<p>'.esc_html__('Keine Felder gefunden. Bitte ACF prüfen.','dgptm-hzb').'</p>';
			} else {
				foreach ( $fields as $field ) {
					echo hzb_render_field_html( $field, $post_id );
				}
			}
			?>
		</div>

		<div class="hzb-submit">
			<button type="submit" class="button button-primary"><?php esc_html_e('Speichern','dgptm-hzb'); ?></button>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

function hzb_render_field_html( $field, $post_id ) {
	$name  = isset($field['name'])  ? $field['name']  : '';
	$label = isset($field['label']) ? $field['label'] : $name;
	$type  = isset($field['type'])  ? $field['type']  : 'text';

	// Override: bestimmte Textareas als WYSIWYG anzeigen
	$wysiwyg_overrides = array('anschrift','aus-weiterbildung-ansprechpartner');
	if ( $type === 'textarea' && in_array($name, $wysiwyg_overrides, true) ) {
		$type = 'wysiwyg';
	}

	// Wert lesen
	if ( function_exists('get_field') ) {
		$value = get_field( $name, $post_id );
	} else {
		$value = get_post_meta( $post_id, $name, true );
	}

	// Für Bilder (ACF return_format 'array' oder 'id') auf ID normieren
	if ( $type === 'image' ) {
		if ( is_array($value) && isset($value['ID']) ) {
			$value = intval( $value['ID'] );
		} else {
			$value = intval( $value );
		}
	}

	$id = 'hzb-field-' . esc_attr( $name );

	ob_start();
	?>
	<div class="hzb-field hzb-type-<?php echo esc_attr($type); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<div class="hzb-input">
			<?php
			switch ( $type ) {
				case 'number':
					printf('<input type="number" id="%1$s" name="fields[%2$s]" value="%3$s" />',
						$id, esc_attr($name), esc_attr($value) );
					break;

				case 'url':
					printf('<input type="url" id="%1$s" name="fields[%2$s]" value="%3$s" />',
						$id, esc_attr($name), esc_attr($value) );
					break;

				case 'true_false':
					$checked = ($value) ? 'checked' : '';
					printf('<label class="hzb-checkbox"><input type="checkbox" id="%1$s" name="fields[%2$s]" value="1" %3$s /> %4$s</label>',
						$id, esc_attr($name), $checked, esc_html__('Ja','dgptm-hzb') );
					break;

				case 'textarea':
					printf('<textarea id="%1$s" name="fields[%2$s]" rows="4">%3$s</textarea>',
						$id, esc_attr($name), esc_textarea($value) );
					break;

				case 'wysiwyg':
					// WYSIWYG als Textarea; JS initialisiert wp.editor dynamisch
					printf('<textarea class="hzb-wysiwyg" data-wysiwyg="1" id="%1$s" name="fields[%2$s]" rows="8">%3$s</textarea>',
						$id, esc_attr($name), esc_textarea( is_string($value) ? $value : '' ) );
					break;

				case 'select':
					$choices = array();
					if ( function_exists('acf_get_field') && ! empty($field['key']) ) {
						$def = acf_get_field( $field['key'] );
						if ( $def && ! empty($def['choices']) && is_array($def['choices']) ) {
							$choices = $def['choices'];
						}
					}
					echo '<select id="'.esc_attr($id).'" name="fields['.esc_attr($name).']">';
					echo '<option value="">'.esc_html__('– auswählen –','dgptm-hzb').'</option>';
					if ( $choices ) {
						foreach ( $choices as $k => $lbl ) {
							printf('<option value="%1$s" %2$s>%3$s</option>',
								esc_attr($k),
								selected( $value, $k, false ),
								esc_html( $lbl )
							);
						}
					} else {
						// Fallback ohne Choices
						if ( ! empty($value) ) {
							printf('<option value="%1$s" selected>%1$s</option>', esc_attr($value) );
						}
					}
					echo '</select>';
					break;

				case 'image':
					$thumb = $value ? wp_get_attachment_image( $value, 'thumbnail' ) : '';
					echo '<div class="hzb-image-control" data-target="'.esc_attr($id).'">';
					echo '<div class="hzb-image-preview">'. ( $thumb ?: '<span class="hzb-noimg">'.esc_html__('Kein Bild','dgptm-hzb').'</span>' ) .'</div>';
					printf('<input type="hidden" id="%1$s" name="fields[%2$s]" value="%3$s" />',
						$id, esc_attr($name), esc_attr($value) );
					echo '<div class="hzb-image-actions">';
					echo '<button type="button" class="button hzb-pick-image">'.esc_html__('Bild wählen','dgptm-hzb').'</button> ';
					echo '<button type="button" class="button hzb-remove-image">'.esc_html__('Entfernen','dgptm-hzb').'</button>';
					echo '</div></div>';
					break;

				default: // text
					printf('<input type="text" id="%1$s" name="fields[%2$s]" value="%3$s" />',
						$id, esc_attr($name), esc_attr( is_scalar($value) ? $value : '' ) );
					break;
			}
			?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
