<?php
/**
 * Export: CSV und PDF fuer Anwesenheit und Abstimmungsergebnisse.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Export {

	public function __construct() {
		add_action( 'wp_ajax_dgptm_mv_export_attendance_csv', [ $this, 'export_attendance_csv' ] );
		add_action( 'wp_ajax_dgptm_mv_export_attendance_pdf', [ $this, 'export_attendance_pdf' ] );
		add_action( 'wp_ajax_dgptm_mv_export_results_csv', [ $this, 'export_results_csv' ] );
	}

	/**
	 * Anwesenheitsliste als CSV.
	 */
	public function export_attendance_csv() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$assembly_id = (int) ( $_GET['assembly_id'] ?? 0 );
		if ( ! $assembly_id ) {
			$assembly = DGPTM_MV_Assembly::get_active_assembly();
			$assembly_id = $assembly ? $assembly->id : 0;
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('attendance') .
			" WHERE assembly_id = %d ORDER BY member_name ASC",
			$assembly_id
		) );

		$assembly = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('assemblies') . " WHERE id = %d",
			$assembly_id
		) );

		$filename = 'Anwesenheit_' . sanitize_file_name( $assembly->name ?? 'MV' ) . '_' . date( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM fuer Excel
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv( $out, [
			'Name', 'E-Mail', 'Mitgliedsnummer', 'Mitgliedsstatus',
			'Art', 'Stimmberechtigt', 'Check-in', 'Check-out', 'Quelle'
		] );

		foreach ( $rows as $r ) {
			fputcsv( $out, [
				$r->member_name,
				$r->member_email,
				$r->member_no,
				$r->member_status,
				$r->attendance_type === 'presence' ? 'Praesenz' : 'Online',
				$r->is_eligible_voter ? 'Ja' : 'Nein',
				$r->checked_in_at,
				$r->checked_out_at ?: '',
				$r->source,
			] );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Anwesenheitsliste als PDF.
	 */
	public function export_attendance_pdf() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$fpdf_path = defined( 'DGPTM_SUITE_PATH' )
			? DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php'
			: dirname( dirname( dirname( __DIR__ ) ) ) . '/../../libraries/fpdf/fpdf.php';

		if ( ! file_exists( $fpdf_path ) ) {
			wp_die( 'FPDF-Bibliothek nicht gefunden.' );
		}
		require_once $fpdf_path;

		$assembly_id = (int) ( $_GET['assembly_id'] ?? 0 );
		if ( ! $assembly_id ) {
			$assembly = DGPTM_MV_Assembly::get_active_assembly();
			$assembly_id = $assembly ? $assembly->id : 0;
		}

		global $wpdb;
		$assembly = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('assemblies') . " WHERE id = %d",
			$assembly_id
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('attendance') .
			" WHERE assembly_id = %d ORDER BY member_name ASC",
			$assembly_id
		) );

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly_id );

		$pdf = new \FPDF( 'L', 'mm', 'A4' );
		$pdf->AddPage();
		$pdf->SetFont( 'Arial', 'B', 14 );
		$pdf->Cell( 0, 10, $this->utf8( 'Anwesenheitsliste - ' . ( $assembly->name ?? 'MV' ) ), 0, 1, 'C' );

		$pdf->SetFont( 'Arial', '', 10 );
		$pdf->Cell( 0, 6, $this->utf8(
			'Gesamt: ' . $stats['total'] . '  |  Stimmberechtigt: ' . $stats['eligible'] .
			'  |  Praesenz: ' . $stats['presence'] . '  |  Online: ' . $stats['online']
		), 0, 1, 'C' );
		$pdf->Ln( 3 );

		// Tabelle
		$pdf->SetFont( 'Arial', 'B', 8 );
		$widths = [ 55, 55, 25, 35, 25, 30, 35, 20 ];
		$headers = [ 'Name', 'E-Mail', 'Mitgl.-Nr.', 'Status', 'Art', 'Stimmber.', 'Check-in', 'Quelle' ];
		$pdf->SetFillColor( 230, 230, 230 );

		foreach ( $headers as $i => $h ) {
			$pdf->Cell( $widths[$i], 7, $this->utf8( $h ), 1, 0, 'C', true );
		}
		$pdf->Ln();

		$pdf->SetFont( 'Arial', '', 7 );
		foreach ( $rows as $r ) {
			$cells = [
				$r->member_name,
				$r->member_email,
				$r->member_no,
				$r->member_status,
				$r->attendance_type === 'presence' ? 'Praesenz' : 'Online',
				$r->is_eligible_voter ? 'Ja' : 'Nein',
				$r->checked_in_at ? wp_date( 'd.m.Y H:i', strtotime( $r->checked_in_at ) ) : '',
				$r->source,
			];
			foreach ( $cells as $i => $val ) {
				$pdf->Cell( $widths[$i], 5, $this->utf8( $val ), 1 );
			}
			$pdf->Ln();
		}

		$filename = 'Anwesenheit_' . sanitize_file_name( $assembly->name ?? 'MV' ) . '.pdf';

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$pdf->Output( 'D', $filename );
		exit;
	}

	/**
	 * Abstimmungsergebnisse als CSV.
	 */
	public function export_results_csv() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$assembly_id = (int) ( $_GET['assembly_id'] ?? 0 );
		if ( ! $assembly_id ) {
			$assembly = DGPTM_MV_Assembly::get_active_assembly();
			$assembly_id = $assembly ? $assembly->id : 0;
		}

		global $wpdb;
		$polls = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') .
			" WHERE assembly_id = %d ORDER BY created_at ASC",
			$assembly_id
		) );

		$filename = 'Abstimmungsergebnisse_' . date( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv( $out, [ 'Frage', 'Typ', 'Erforderliche Mehrheit', 'Antwort', 'Stimmen', 'Gesamt abgegeben', 'Gueltige Stimmen', 'Mehrheit erreicht' ] );

		foreach ( $polls as $poll ) {
			$choices = json_decode( $poll->choices, true ) ?: [];
			$vote_counts = array_fill( 0, count( $choices ), 0 );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT choice_index, COUNT(*) AS cnt FROM " . DGPTM_MV_Database::table('votes') .
				" WHERE poll_id = %d GROUP BY choice_index",
				$poll->id
			) );
			foreach ( $rows as $r ) {
				if ( isset( $vote_counts[ $r->choice_index ] ) ) {
					$vote_counts[ $r->choice_index ] = (int) $r->cnt;
				}
			}

			$total = array_sum( $vote_counts );
			$total_receipts = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
				$poll->id
			) );

			foreach ( $choices as $idx => $label ) {
				fputcsv( $out, [
					$poll->question,
					$poll->poll_type,
					$poll->required_majority,
					$label,
					$vote_counts[$idx],
					$total_receipts,
					$total,
					$poll->results_released ? 'Ja' : 'Ausstehend',
				] );
			}
		}

		fclose( $out );
		exit;
	}

	private function utf8( $str ) {
		return iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str );
	}
}
