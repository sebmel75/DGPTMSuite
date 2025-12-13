<?php
/**
 * Test Helper Functions
 *
 * @package DGPTM_Abstimmen\Tests
 */

class DGPTM_Test_Helpers {

	/**
	 * Create test user with voting permissions
	 *
	 * @param array $args User arguments
	 * @return int User ID
	 */
	public static function create_test_user( $args = array() ) {
		$defaults = array(
			'user_login' => 'testuser_' . wp_generate_password( 8, false ),
			'user_email' => 'test_' . wp_generate_password( 8, false ) . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		);

		$user_data = wp_parse_args( $args, $defaults );
		$user_id   = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		// Add voting meta
		update_user_meta( $user_id, 'dgptm_vote_status', 'on' );
		update_user_meta( $user_id, 'dgptm_vote_code', self::generate_code() );
		update_user_meta( $user_id, 'mitgliederversammlung', 'true' );

		return $user_id;
	}

	/**
	 * Create test poll
	 *
	 * @param array $args Poll arguments
	 * @return int Poll ID
	 */
	public static function create_test_poll( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'             => 'Test Poll ' . time(),
			'status'           => 'active',
			'requires_signup'  => 0,
			'logo_url'         => '',
			'created'          => current_time( 'mysql' ),
		);

		$poll_data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'dgptm_abstimmung_polls',
			$poll_data,
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Create test question
	 *
	 * @param int   $poll_id Poll ID
	 * @param array $args Question arguments
	 * @return int Question ID
	 */
	public static function create_test_question( $poll_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'poll_id'    => $poll_id,
			'question'   => 'Test Question?',
			'choices'    => wp_json_encode( array( 'Ja', 'Nein', 'Enthaltung' ) ),
			'max_votes'  => 1,
			'status'     => 'active',
			'chart_type' => 'bar',
			'released'   => 0,
			'created'    => current_time( 'mysql' ),
		);

		$question_data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'dgptm_abstimmung_poll_questions',
			$question_data,
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Cast test vote
	 *
	 * @param int $question_id Question ID
	 * @param int $user_id User ID
	 * @param array $choices Selected choices
	 * @return int|false Vote ID or false
	 */
	public static function cast_test_vote( $question_id, $user_id, $choices = array( 0 ) ) {
		global $wpdb;

		$cookie_id = 'test_' . wp_generate_password( 16, false );

		foreach ( $choices as $choice_index ) {
			$wpdb->insert(
				$wpdb->prefix . 'dgptm_abstimmung_votes',
				array(
					'question_id' => $question_id,
					'choice'      => $choice_index,
					'user_id'     => $user_id,
					'cookie_id'   => $cookie_id,
					'voted_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Generate test code (6-stellig)
	 *
	 * @return string
	 */
	public static function generate_code() {
		return sprintf( '%06d', wp_rand( 0, 999999 ) );
	}

	/**
	 * Mock Zoom API Response
	 *
	 * @param string $endpoint Endpoint name
	 * @param array $data Response data
	 * @return array
	 */
	public static function mock_zoom_response( $endpoint, $data = array() ) {
		$defaults = array(
			'id'         => '12345678901',
			'topic'      => 'Test Meeting',
			'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 day' ) ),
			'duration'   => 60,
			'status'     => 'waiting',
		);

		if ( $endpoint === 'meeting' || $endpoint === 'webinar' ) {
			return wp_parse_args( $data, $defaults );
		}

		if ( $endpoint === 'registrant' ) {
			return array(
				'id'         => 'reg_' . wp_generate_password( 16, false ),
				'email'      => 'test@example.com',
				'first_name' => 'Test',
				'last_name'  => 'User',
				'status'     => 'approved',
				'join_url'   => 'https://zoom.us/w/test?tk=xyz',
			);
		}

		return array();
	}

	/**
	 * Create test attendance record
	 *
	 * @param string $meeting_id Meeting ID
	 * @param string $email Email
	 * @param array $args Additional args
	 * @return bool
	 */
	public static function create_attendance_record( $meeting_id, $email, $args = array() ) {
		$defaults = array(
			'name'           => 'Test User',
			'status'         => 'Mitglied',
			'mitgliedsart'   => 'Ordentliches Mitglied',
			'mitgliedsnummer' => 'M12345',
			'join_first'     => time(),
			'leave_last'     => 0,
			'total'          => 0,
			'manual'         => 0,
		);

		$data = wp_parse_args( $args, $defaults );

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		if ( ! isset( $store[ $key ]['participants'] ) ) {
			$store[ $key ]['participants'] = array();
		}

		$store[ $key ]['participants'][ $pk ] = $data;

		return update_option( 'dgptm_zoom_attendance', $store, false );
	}

	/**
	 * Clear all test data
	 */
	public static function clear_test_data() {
		global $wpdb;

		// Clear database tables
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}dgptm_abstimmung_polls" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}dgptm_abstimmung_poll_questions" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}dgptm_abstimmung_votes" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}dgptm_abstimmung_participants" );

		// Clear options
		delete_option( 'dgptm_zoom_attendance' );
		delete_option( 'dgptm_beamer_state' );
		delete_option( 'dgptm_vote_zoom_log' );
	}
}
