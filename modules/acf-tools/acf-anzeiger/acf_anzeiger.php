<?php
/*
Plugin Name:  DGPTM – ACF Display (Hardened)
Description:  Gibt ACF-Felder sicher per Shortcode aus (Text, Bilder, Links, verlinkte Beiträge, Dateien, Galerien). Elementor-freundlich. Unterstützt Titel-Override (title="…"), automatische Typ-Erkennung, Bildgrößen, Lazy-Loading, rel/target-Absicherung, Wrapper, Trenner, Mehrfachwerte. Security-Hardening (Escaping, KSES-Whitelist), saubere Fallbacks.
Version:      1.9.0
Author:       Seb
License:      GPL-2.0-or-later
Text Domain:  dgptm-acf-display
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Kein Direktzugriff.
}

if ( ! class_exists( 'DGPTM_ACF_Display' ) ) :

final class DGPTM_ACF_Display {

	const SHORTCODE = 'dgptm_acf';

	/** Zulässige Werte */
	private const ALLOWED_TYPES   = array( 'auto', 'text', 'image', 'link', 'post_object', 'file', 'gallery' );
	private const ALLOWED_DISPLAY = array( 'anchor', 'title', 'url', 'both', 'download' ); // für file/link/post_object
	private const ALLOWED_TARGETS = array( '_self', '_blank', '_parent', '_top' );

	/**
	 * Konstruktur: Shortcode registrieren
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode_acf_field' ) );
	}

	/**
	 * Shortcode-Handler.
	 *
	 * Beispiele:
	 * [dgptm_acf field="mein_feld"]                               -> auto-detect
	 * [dgptm_acf field="datei"   type="file" display="download"]
	 * [dgptm_acf field="bild"    type="image" size="medium"]
	 * [dgptm_acf field="link"    type="link"  title="Eigener Linktext" target="_blank" rel="noreferrer"]
	 * [dgptm_acf field="bezug"   type="post_object" display="title" sep=" | "]
	 * [dgptm_acf field="galerie" type="gallery" size="thumbnail" class="grid grid-cols-3 gap-2"]
	 *
	 * Zusätzliche Attribute:
	 *  - post_id=""        Post-ID (leer = aktuelle ID)
	 *  - class=""          CSS-Klasse(n)
	 *  - display=""        anchor | title | url | both | download (file/link/post_object)
	 *  - title=""          überschreibt Link-/Titeltext (falls sinnvoll)
	 *  - size=""           Bildgröße (image/gallery), z. B. "thumbnail", "medium", "full" (Default: full)
	 *  - target=""         Linkziel: _self/_blank/_parent/_top (Default: _self)
	 *  - rel=""            rel-Attribut (Default: "noopener noreferrer" bei target=_blank, sonst leer)
	 *  - wrap=""           Wrapper-Tag für Mehrfachwerte (z.B. "div","ul","p") – ohne spitze Klammern
	 *  - item_wrap=""      Item-Wrapper für Mehrfachwerte (z.B. "span","li")
	 *  - sep=", "          Trenner zwischen Mehrfachwerten (wenn kein item_wrap gesetzt ist)
	 *  - before=""         HTML vor der Ausgabe (kses)
	 *  - after=""          HTML nach der Ausgabe (kses)
	 *  - lazy="1|0"        Lazy-Loading für Bilder (Default: 1)
	 *  - link_image="1|0"  Galerie: Bild klickbar zur Vollansicht (Default: 0)
	 *  - empty="hide|dash" Leere Ausgabe verbergen (hide) oder "—" (dash) (Default: hide)
	 *
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_acf_field( $atts ) {

		// Fail-fast, wenn ACF fehlt.
		if ( ! function_exists( 'get_field' ) ) {
			return $this->safe_wrap( __( 'ACF ist nicht installiert oder aktiviert.', 'dgptm-acf-display' ), 'p' );
		}

		$atts = shortcode_atts(
			array(
				'field'      => '',
				'type'       => 'auto',
				'post_id'    => '',
				'class'      => '',
				'display'    => 'anchor',
				'title'      => '',
				'size'       => 'full',
				'target'     => '_self',
				'rel'        => '',
				'wrap'       => '',
				'item_wrap'  => '',
				'sep'        => ', ',
				'before'     => '',
				'after'      => '',
				'lazy'       => '1',
				'link_image' => '0',
				'empty'      => 'hide',
			),
			$atts,
			self::SHORTCODE
		);

		// Sanitize/normalisieren
		$field        = sanitize_key( $atts['field'] );
		$type         = in_array( $atts['type'], self::ALLOWED_TYPES, true ) ? $atts['type'] : 'auto';
		$post_id      = $this->sanitize_post_id( $atts['post_id'] );
		$class        = $this->sanitize_css_classes( $atts['class'] );
		$display      = in_array( $atts['display'], self::ALLOWED_DISPLAY, true ) ? $atts['display'] : 'anchor';
		$override_tit = (string) $atts['title'];
		$size         = sanitize_text_field( $atts['size'] );
		$target       = in_array( $atts['target'], self::ALLOWED_TARGETS, true ) ? $atts['target'] : '_self';
		$rel          = $this->sanitize_rel( $atts['rel'], $target );
		$wrap         = $this->sanitize_tag_name( $atts['wrap'] );
		$item_wrap    = $this->sanitize_tag_name( $atts['item_wrap'] );
		$sep          = wp_kses( (string) $atts['sep'], $this->allowed_html_min() );
		$before       = wp_kses( (string) $atts['before'], $this->allowed_html_min() );
		$after        = wp_kses( (string) $atts['after'],  $this->allowed_html_min() );
		$lazy         = $atts['lazy'] === '0' ? false : true;
		$link_image   = $atts['link_image'] === '1';
		$empty        = ( $atts['empty'] === 'dash' ) ? 'dash' : 'hide';

		if ( empty( $field ) ) {
			return '';
		}

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$value = get_field( $field, $post_id );

		// Wenn leer -> optional "—" oder gar nichts.
		if ( $this->is_empty_value( $value ) ) {
			return ( $empty === 'dash' ) ? '&mdash;' : '';
		}

		// Typ automatisch erkennen, falls gewünscht.
		if ( $type === 'auto' ) {
			$type = $this->detect_type( $value );
		}

		// Ausgabe nach Typ.
		$out = '';

		switch ( $type ) {
			case 'image':
				$out = $this->render_image( $value, $class, $size, $lazy );
				break;

			case 'link':
				$out = $this->render_link_field( $value, $class, $override_tit, $target, $rel );
				break;

			case 'post_object':
				$out = $this->render_post_object( $value, $class, $display, $override_tit, $sep, $wrap, $item_wrap );
				break;

			case 'file':
				$out = $this->render_file( $value, $class, $display, $override_tit, $target, $rel );
			 break;

			case 'gallery':
				$out = $this->render_gallery( $value, $class, $size, $lazy, $link_image );
				break;

			case 'text':
			default:
				$out = $this->render_text( $value, $class );
				break;
		}

		// Final verpacken (before/after)
		if ( $out !== '' ) {
			$out = $before . $out . $after;
		}

		/**
		 * Filter erlaubt letzte Anpassungen.
		 *
		 * @param string $out  HTML-Ausgabe
		 * @param array  $ctx  Kontext/Attribute
		 */
		return apply_filters( 'dgptm_acf_display_output', $out, array(
			'field'       => $field,
			'type'        => $type,
			'post_id'     => $post_id,
			'class'       => $class,
			'display'     => $display,
			'title'       => $override_tit,
			'size'        => $size,
			'target'      => $target,
			'rel'         => $rel,
			'wrap'        => $wrap,
			'item_wrap'   => $item_wrap,
			'sep'         => $sep,
			'before'      => $before,
			'after'       => $after,
			'lazy'        => $lazy,
			'link_image'  => $link_image,
			'empty'       => $empty,
			'raw_value'   => $value,
		) );
	}

	/* =========================
	 *  RENDERER
	 * ========================= */

	private function render_text( $value, string $class ) : string {
		if ( is_array( $value ) ) {
			// Flache Liste rendern
			$items = array();
			foreach ( $value as $v ) {
				if ( is_scalar( $v ) ) {
					$items[] = esc_html( (string) $v );
				}
			}
			$html = implode( ', ', $items );
		} else {
			$html = esc_html( (string) $value );
		}
		if ( $html === '' ) {
			return '';
		}
		return $class ? sprintf( '<span class="%s">%s</span>', esc_attr( $class ), $html ) : $html;
	}

	private function render_image( $value, string $class, string $size, bool $lazy ) : string {
		$loading = $lazy ? 'loading="lazy"' : '';

		// Fall 1: ID
		if ( is_numeric( $value ) ) {
			$img = wp_get_attachment_image( (int) $value, $size, false, array(
				'class'   => $class,
				'loading' => $lazy ? 'lazy' : 'eager',
			) );
			return $img ? $img : '';
		}

		// Fall 2: Array
		if ( is_array( $value ) ) {
			// ACF Bild-Array
			// Bevorzugt wp_get_attachment_image, wenn 'ID' vorhanden (liefert srcset, sizes, alt)
			if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) {
				$img = wp_get_attachment_image( (int) $value['ID'], $size, false, array(
					'class'   => $class,
					'loading' => $lazy ? 'lazy' : 'eager',
				) );
				if ( $img ) {
					return $img;
				}
			}

			$url = isset( $value['url'] ) ? esc_url( $value['url'] ) : '';
			$alt = isset( $value['alt'] ) ? esc_attr( $value['alt'] ) : '';
			if ( ! $url ) {
				return '';
			}
			return sprintf( '<img src="%s" alt="%s" class="%s" %s />', $url, $alt, esc_attr( $class ), $loading );
		}

		// Fall 3: String (URL)
		if ( is_string( $value ) ) {
			$url = esc_url( $value );
			if ( ! $url ) {
				return '';
			}
			return sprintf( '<img src="%s" alt="" class="%s" %s />', $url, esc_attr( $class ), $loading );
		}

		return '';
	}

	private function render_link_field( $value, string $class, string $override_tit, string $target, string $rel ) : string {
		// Erwartet ACF-Link (Array) – toleriert aber auch reine URL (String)
		if ( is_array( $value ) ) {
			$url    = isset( $value['url'] )    ? esc_url( $value['url'] )    : '';
			$title  = isset( $value['title'] )  ? (string) $value['title']    : '';
			$targ   = isset( $value['target'] ) ? (string) $value['target']   : '_self';

			$title = ( $override_tit !== '' ) ? $override_tit : $title;
			$title = esc_html( $title );

			// Target absichern/überschreiben
			$target_final = in_array( $targ, self::ALLOWED_TARGETS, true ) ? $targ : '_self';
			// Falls Shortcode target explizit gesetzt wurde, hat es Priorität
			$target_final = $target ?: $target_final;

			$rel_final = $this->sanitize_rel( $rel, $target_final );

			if ( ! $url ) {
				return '';
			}

			return sprintf(
				'<a class="%s" href="%s" target="%s" rel="%s">%s</a>',
				esc_attr( $class ),
				$url,
				esc_attr( $target_final ),
				esc_attr( $rel_final ),
				$title !== '' ? $title : esc_html( $url )
			);
		}

		// Nur URL als String
		if ( is_string( $value ) ) {
			$url   = esc_url( $value );
			$title = ( $override_tit !== '' ) ? esc_html( $override_tit ) : esc_html( $url );
			if ( ! $url ) {
				return '';
			}
			$rel_final = $this->sanitize_rel( $rel, $target );
			return sprintf(
				'<a class="%s" href="%s" target="%s" rel="%s">%s</a>',
				esc_attr( $class ),
				$url,
				esc_attr( $target ),
				esc_attr( $rel_final ),
				$title
			);
		}

		return '';
	}

	private function render_post_object( $value, string $class, string $display, string $override_tit, string $sep, string $wrap, string $item_wrap ) : string {
		$posts = $this->normalize_posts( $value );

		if ( empty( $posts ) ) {
			return '';
		}

		$items = array();

		foreach ( $posts as $p ) {
			$post_id    = $p->ID;
			$post_title = get_the_title( $post_id );
			$post_link  = get_permalink( $post_id );

			if ( $override_tit !== '' ) {
				$post_title = $override_tit;
			}

			$post_title_esc = esc_html( $post_title );

			switch ( $display ) {
				case 'title':
					$item = sprintf( '<span class="%s">%s</span>', esc_attr( $class ), $post_title_esc );
					break;
				case 'url':
					$item = sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_url( $post_link ) );
					break;
				case 'both':
					$item = sprintf( '<span class="%s">%s (%s)</span>', esc_attr( $class ), $post_title_esc, esc_url( $post_link ) );
					break;
				case 'anchor':
				default:
					$item = sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $post_link ), $post_title_esc );
					break;
			}

			if ( $item_wrap ) {
				$item = $this->wrap_tag( $item_wrap, $item );
			}

			$items[] = $item;
		}

		$html = $item_wrap ? implode( '', $items ) : implode( $sep, $items );
		return $wrap ? $this->wrap_tag( $wrap, $html ) : $html;
	}

	private function render_file( $value, string $class, string $display, string $override_tit, string $target, string $rel ) : string {
		$url   = '';
		$title = '';

		// ID -> URL
		if ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );
			$title = basename( (string) $url );
		}
		// Array (ACF-Dateifeld)
		elseif ( is_array( $value ) ) {
			$url         = isset( $value['url'] )      ? $value['url'] : '';
			$acf_title   = isset( $value['title'] )    ? $value['title'] : '';
			$filename    = isset( $value['filename'] ) ? $value['filename'] : '';
			$title       = $acf_title ?: $filename;
			if ( ! $title && $url ) {
				$title = wp_basename( $url );
			}
		}
		// String (URL)
		elseif ( is_string( $value ) ) {
			$url   = $value;
			$title = wp_basename( $url );
		}

		$url   = esc_url( $url );
		$title = ( $override_tit !== '' ) ? esc_html( $override_tit ) : esc_html( $title );

		if ( ! $url ) {
			return '';
		}

		$rel_final = $this->sanitize_rel( $rel, $target );

		switch ( $display ) {
			case 'title':
				return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), $title );
			case 'url':
				return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), $url );
			case 'both':
				return sprintf( '<span class="%s">%s (%s)</span>', esc_attr( $class ), $title, $url );
			case 'download':
				return sprintf(
					'<a class="%s" href="%s" download="%s" target="%s" rel="%s">%s</a>',
					esc_attr( $class ),
					$url,
					esc_attr( $title ),
					esc_attr( $target ),
					esc_attr( $rel_final ),
					$title
				);
			case 'anchor':
			default:
				return sprintf(
					'<a class="%s" href="%s" target="%s" rel="%s">%s</a>',
					esc_attr( $class ),
					$url,
					esc_attr( $target ),
					esc_attr( $rel_final ),
					$title
				);
		}
	}

	private function render_gallery( $value, string $class, string $size, bool $lazy, bool $link_image ) : string {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		$items = array();
		foreach ( $value as $img ) {
			$item = '';

			// Normalisieren auf ID, wenn möglich
			if ( is_array( $img ) && isset( $img['ID'] ) && is_numeric( $img['ID'] ) ) {
				$item = wp_get_attachment_image( (int) $img['ID'], $size, false, array(
					'loading' => $lazy ? 'lazy' : 'eager',
				) );
				$full_url = wp_get_attachment_image_url( (int) $img['ID'], 'full' );
			} elseif ( is_numeric( $img ) ) {
				$item     = wp_get_attachment_image( (int) $img, $size, false, array(
					'loading' => $lazy ? 'lazy' : 'eager',
				) );
				$full_url = wp_get_attachment_image_url( (int) $img, 'full' );
			} elseif ( is_array( $img ) ) {
				$url = isset( $img['url'] ) ? esc_url( $img['url'] ) : '';
				$alt = isset( $img['alt'] ) ? esc_attr( $img['alt'] ) : '';
				if ( $url ) {
					$item     = sprintf( '<img src="%s" alt="%s" %s />', $url, $alt, $lazy ? 'loading="lazy"' : '' );
					$full_url = $url;
				} else {
					$full_url = '';
				}
			} elseif ( is_string( $img ) ) {
				$url = esc_url( $img );
				if ( $url ) {
					$item     = sprintf( '<img src="%s" alt="" %s />', $url, $lazy ? 'loading="lazy"' : '' );
					$full_url = $url;
				} else {
					$full_url = '';
				}
			} else {
				$full_url = '';
			}

			if ( $item ) {
				if ( $link_image && $full_url ) {
					$items[] = sprintf( '<a href="%s">%s</a>', esc_url( $full_url ), $item );
				} else {
					$items[] = $item;
				}
			}
		}

		if ( empty( $items ) ) {
		 return '';
		}

		$inner = implode( '', $items );
		// Um die gesamte Galerie eine Wrapper-Klasse legen, damit der Benutzer über "class" layouten kann (Grid etc.)
		return sprintf( '<div class="%s">%s</div>', esc_attr( $class ), $inner );
	}

	/* =========================
	 *  HELFER
	 * ========================= */

	private function is_empty_value( $v ) : bool {
		if ( is_array( $v ) ) {
			return count( $v ) === 0;
		}
		return ( $v === null || $v === '' );
	}

	private function detect_type( $value ) : string {
		if ( is_array( $value ) ) {
			// ACF-Link: hat url/title/target
			if ( isset( $value['url'] ) && ( isset( $value['title'] ) || isset( $value['target'] ) ) ) {
				return 'link';
			}
			// ACF-Bild: hat url/alt/mime_type oder ID/sizes
			if ( isset( $value['mime_type'] ) && strpos( $value['mime_type'], 'image/' ) === 0 ) {
				return 'image';
			}
			if ( isset( $value['sizes'] ) || isset( $value['alt'] ) ) {
				return 'image';
			}
			// ACF-Datei: hat mime_type (nicht Bild) oder filename
			if ( isset( $value['mime_type'] ) && strpos( $value['mime_type'], 'image/' ) !== 0 ) {
				return 'file';
			}
			if ( isset( $value['filename'] ) && ! isset( $value['sizes'] ) ) {
				return 'file';
			}
			// Galerie: Liste von Bildern
			if ( isset( $value[0] ) ) {
				return 'gallery';
			}
			// Post Object / Relationship: Array aus WP_Post oder IDs
			if ( isset( $value['ID'] ) || ( isset( $value[0] ) && ( is_object( $value[0] ) || is_numeric( $value[0] ) ) ) ) {
				return 'post_object';
			}
		}

		if ( is_object( $value ) ) {
			// WP_Post?
			if ( isset( $value->ID ) ) {
				return 'post_object';
			}
		}

		// Numerisch könnte Bild/File-ID sein – schwer unterscheidbar. Default "text".
		return 'text';
	}

	/**
	 * @param mixed $value
	 * @return WP_Post[]
	 */
	private function normalize_posts( $value ) : array {
		$posts = array();

		if ( is_object( $value ) && isset( $value->ID ) ) {
			$p = get_post( $value->ID );
			if ( $p instanceof WP_Post ) {
				$posts[] = $p;
			}
		} elseif ( is_array( $value ) ) {
			// Mischung aus Objekten/IDs
			foreach ( $value as $v ) {
				if ( is_object( $v ) && isset( $v->ID ) ) {
					$p = get_post( $v->ID );
				} elseif ( is_numeric( $v ) ) {
					$p = get_post( (int) $v );
				} elseif ( is_array( $v ) && isset( $v['ID'] ) && is_numeric( $v['ID'] ) ) {
					$p = get_post( (int) $v['ID'] );
				} else {
					$p = null;
				}
				if ( $p instanceof WP_Post ) {
					$posts[] = $p;
				}
			}
		}

		return $posts;
	}

	private function sanitize_post_id( $maybe_id ) : int {
		if ( is_numeric( $maybe_id ) ) {
			return (int) $maybe_id;
		}
		return 0;
	}

	private function sanitize_css_classes( $classes ) : string {
		$classes = is_string( $classes ) ? $classes : '';
		$classes = preg_replace( '/[^A-Za-z0-9_\-\s]/', '', $classes );
		$classes = trim( preg_replace( '/\s+/', ' ', $classes ) );
		return $classes;
	}

	private function sanitize_tag_name( $tag ) : string {
		$tag = strtolower( trim( (string) $tag ) );
		if ( $tag === '' ) {
			return '';
		}
		// Nur Buchstaben/Zahlen erlaubt, keine spitzen Klammern
		if ( ! preg_match( '/^[a-z][a-z0-9\-]*$/', $tag ) ) {
			return '';
		}
		return $tag;
	}

	private function sanitize_rel( string $rel, string $target ) : string {
		$rel = trim( preg_replace( '/[^A-Za-z0-9_\-\s:]/', '', (string) $rel ) );
		$bits = array_filter( preg_split( '/\s+/', $rel ) );

		// Bei _blank sicherstellen:
		if ( $target === '_blank' ) {
			if ( ! in_array( 'noopener', $bits, true ) ) {
				$bits[] = 'noopener';
			}
			if ( ! in_array( 'noreferrer', $bits, true ) ) {
				$bits[] = 'noreferrer';
			}
		}

		return implode( ' ', array_unique( $bits ) );
	}

	private function wrap_tag( string $tag, string $html ) : string {
		$tag = $this->sanitize_tag_name( $tag );
		if ( ! $tag ) {
			return $html;
		}
		return sprintf( '<%1$s>%2$s</%1$s>', esc_attr( $tag ), $html );
	}

	private function allowed_html_min() : array {
		// Minimal-Whitelist für before/after/sep
		return array(
			'span' => array( 'class' => true ),
			'br'   => array(),
			'strong' => array(),
			'em'   => array(),
			'small'=> array(),
		);
	}

	private function safe_wrap( string $text, string $tag = 'div' ) : string {
		$tag = $this->sanitize_tag_name( $tag );
		$tag = $tag ?: 'div';
		return sprintf( '<%1$s>%2$s</%1$s>', esc_attr( $tag ), esc_html( $text ) );
	}
}

new DGPTM_ACF_Display();

endif;
