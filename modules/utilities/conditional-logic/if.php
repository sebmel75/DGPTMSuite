<?php
/**
 * Plugin Name: Conditional Shortcode [ifdgptm]
 * Description: Bedingte Anzeige via [ifdgptm]...[/ifdgptm]. Kann den Output anderer Shortcodes prüfen: test="[irgendein_shortcode ...]" oder expr="[shortcode] is not empty". Unterstützt [else].
 * Version:     1.2.2
 * Author:      ChatGPT for Seb
 * License:     GPL-2.0-or-later
 * Text Domain: conditional-shortcode-ifdgptm
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Conditional_Shortcode_IFDGPTM' ) ) :

class Conditional_Shortcode_IFDGPTM {

  private static $instance = null;

  public static function instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    add_action( 'init', array( $this, 'register_shortcodes' ) );
  }

  public function register_shortcodes() {
    add_shortcode( 'ifdgptm', array( $this, 'handle_if_shortcode' ) );
  }

  /**
   * Shortcode: [ifdgptm ...] ... [else] ... [/ifdgptm]
   * Attrs:
   *   - test  : String. Wenn mit "[" beginnt, wird der enthaltene Shortcode ausgeführt und dessen Ausgabe geprüft.
   *   - is    : not_empty|empty|equals|contains|>|<|>=|<=|!=|=
   *   - value : Vergleichswert (für equals/contains/…)
   *   - expr  : Natürlichsprachlich, z.B. "[foo] is not empty" oder "10 > 3"
   *   - not   : "true" zum Invertieren
   */
  public function handle_if_shortcode( $atts, $content = '' ) {

    $atts = shortcode_atts( array(
      'test'   => '',
      'is'     => '',
      'value'  => '',
      'expr'   => '',
      'not'    => '',
    ), $atts, 'ifdgptm' );

    $blocks = $this->split_if_else( (string) $content );
    $if_content   = $blocks['if'];
    $else_content = $blocks['else'];

    // expr -> test/is/value
    if ( ! empty( $atts['expr'] ) ) {
      $parsed = $this->parse_expr( $atts['expr'] );
      if ( is_array( $parsed ) ) {
        $atts['test']  = $parsed['test'];
        $atts['is']    = $parsed['is'];
        $atts['value'] = $parsed['value'];
      }
    }

    $condition = false;

    // 1) eingebetteten Shortcode testen
    if ( $this->looks_like_shortcode( $atts['test'] ) ) {
      $output   = $this->safe_do_shortcode( $atts['test'] );
      $operator = strtolower( trim( (string) $atts['is'] ) );
      if ( '' === $operator ) { $operator = 'not_empty'; }
      $condition = $this->compare( $operator, $output, (string) $atts['value'] );

    // 2) normaler Vergleich
    } else {
      $operator = strtolower( trim( (string) $atts['is'] ) );
      if ( '' === $operator ) { $operator = ( $atts['value'] !== '' ) ? 'equals' : 'not_empty'; }
      $condition = $this->compare( $operator, (string) $atts['test'], (string) $atts['value'] );
    }

    // invertieren?
    if ( $this->is_true( $atts['not'] ) ) {
      $condition = ! $condition;
    }

    return $condition ? do_shortcode( $if_content ) : do_shortcode( $else_content );
  }

  /** IF/ELSE trennen */
  private function split_if_else( $content ) {
    $parts = preg_split( '/\[else\]/i', (string) $content, 2 );
    return array(
      'if'   => isset( $parts[0] ) ? $parts[0] : '',
      'else' => isset( $parts[1] ) ? $parts[1] : '',
    );
  }

  /** Natürlichsprachliche Ausdrücke parsen */
  private function parse_expr( $expr ) {
    $expr = trim( (string) $expr );

    // "[shortcode]" <op> <value?>
    if ( preg_match( '#^\s*(\[.+\])\s+(is\s+not\s+empty|is\s+empty|equals|contains|>=|<=|>|<|!=|=)\s*(.*)$#i', $expr, $m ) ) {
      $test = trim( $m[1] );
      $op   = strtolower( trim( $m[2] ) );
      $val  = isset( $m[3] ) ? trim( $m[3] ) : '';
      if ( 'is not empty' === $op ) $op = 'not_empty';
      if ( 'is empty'     === $op ) $op = 'empty';
      return array( 'test' => $test, 'is' => $op, 'value' => $val );
    }

    // "left <op> right"
    if ( preg_match( '#^\s*(.+?)\s+(equals|contains|>=|<=|>|<|!=|=)\s*(.+?)\s*$#i', $expr, $m ) ) {
      return array(
        'test'  => trim( $m[1] ),
        'is'    => strtolower( trim( $m[2] ) ),
        'value' => trim( $m[3] ),
      );
    }

    // "[shortcode] is not empty"
    if ( preg_match( '#^\s*(\[.+\])\s+is\s+not\s+empty\s*$#i', $expr, $m ) ) {
      return array( 'test' => trim( $m[1] ), 'is' => 'not_empty', 'value' => '' );
    }

    // Nur Shortcode -> truthy
    if ( $this->looks_like_shortcode( $expr ) ) {
      return array( 'test' => $expr, 'is' => 'not_empty', 'value' => '' );
    }

    return null;
  }

  /** Erkennung: sieht der String wie ein Shortcode aus? */
  private function looks_like_shortcode( $str ) {
    $str = trim( (string) $str );
    return ( strlen( $str ) > 2 && $str[0] === '[' && substr( $str, -1 ) === ']' );
  }

  /**
   * Sicheres Ausführen eines eingebetteten Shortcodes:
   * - Entfernt eingebettete [ifdgptm]-Blöcke (Rekursionsschutz)
   * - Führt do_shortcode aus
   * - Entfernt HTML & trimmt
   */
  private function safe_do_shortcode( $shortcode_text ) {
    $shortcode_text = (string) $shortcode_text;

    // Rekursionsschutz nur für unseren eigenen Tag
    $shortcode_text = preg_replace(
      '/\[ifdgptm[^\]]*\].*?\[\/ifdgptm\]/is',
      '',
      $shortcode_text
    );

    // Shortcode ausführen – Abfang: falls der beinhaltete Shortcode fatalt,
    // ist das Sache des jeweiligen Shortcodes. Wir liefern nur "sauber" zurück.
    $out = do_shortcode( $shortcode_text );

    // HTML entfernen & trimmen
    if ( function_exists( 'wp_strip_all_tags' ) ) {
      $out = wp_strip_all_tags( (string) $out );
    } else {
      $out = strip_tags( (string) $out );
    }

    return trim( (string) $out );
  }

  /** truthy für not= */
  private function is_true( $val ) {
    if ( is_bool( $val ) ) return $val;
    $val = strtolower( trim( (string) $val ) );
    return in_array( $val, array( '1', 'true', 'yes', 'on' ), true );
  }

  /** Vergleichslogik ohne mbstring-Abhängigkeit */
  private function compare( $operator, $left, $right ) {
    $l = (string) $left;
    $r = (string) $right;

    switch ( $operator ) {
      case 'not_empty':
        return strlen( trim( $l ) ) > 0;

      case 'empty':
        return strlen( trim( $l ) ) === 0;

      case 'equals':
      case '=':
        return $l === $r;

      case '!=':
        return $l !== $r;

      case 'contains':
        return ( $r === '' ) ? false : ( stripos( $l, $r ) !== false );

      case '>':
      case 'greater':
      case 'more':
        if ( is_numeric( $l ) && is_numeric( $r ) ) return ( (float) $l >  (float) $r );
        return ( $l > $r );

      case '<':
      case 'less':
        if ( is_numeric( $l ) && is_numeric( $r ) ) return ( (float) $l <  (float) $r );
        return ( $l < $r );

      case '>=':
        if ( is_numeric( $l ) && is_numeric( $r ) ) return ( (float) $l >= (float) $r );
        return ( $l >= $r );

      case '<=':
        if ( is_numeric( $l ) && is_numeric( $r ) ) return ( (float) $l <= (float) $r );
        return ( $l <= $r );

      default:
        // unbekannter Operator -> "truthy"
        return strlen( trim( $l ) ) > 0;
    }
  }
}

endif;

// Bootstrap
Conditional_Shortcode_IFDGPTM::instance();
