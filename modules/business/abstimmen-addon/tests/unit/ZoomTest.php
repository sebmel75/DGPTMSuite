<?php
/**
 * Zoom Integration Test
 *
 * @package DGPTM_Abstimmen\Tests
 */

class ZoomTest extends WP_UnitTestCase {

	protected $user_id;

	public function setUp(): void {
		parent::setUp();
		$this->user_id = DGPTM_Test_Helpers::create_test_user();
	}

	public function tearDown(): void {
		wp_delete_user( $this->user_id );
		parent::tearDown();
	}

	/**
	 * Test Zoom meeting data structure
	 */
	public function test_zoom_meeting_structure() {
		$meeting = DGPTM_Test_Helpers::mock_zoom_response( 'meeting' );

		$this->assertArrayHasKey( 'id', $meeting, 'Meeting should have ID' );
		$this->assertArrayHasKey( 'topic', $meeting, 'Meeting should have topic' );
		$this->assertArrayHasKey( 'start_time', $meeting, 'Meeting should have start time' );
		$this->assertArrayHasKey( 'duration', $meeting, 'Meeting should have duration' );
		$this->assertEquals( 'waiting', $meeting['status'], 'Default status should be waiting' );
	}

	/**
	 * Test Zoom webinar data structure
	 */
	public function test_zoom_webinar_structure() {
		$webinar = DGPTM_Test_Helpers::mock_zoom_response( 'webinar' );

		$this->assertArrayHasKey( 'id', $webinar, 'Webinar should have ID' );
		$this->assertArrayHasKey( 'topic', $webinar, 'Webinar should have topic' );
		$this->assertEquals( 11, strlen( $webinar['id'] ), 'Zoom ID should be 11 digits' );
	}

	/**
	 * Test registrant response structure
	 */
	public function test_registrant_structure() {
		$registrant = DGPTM_Test_Helpers::mock_zoom_response( 'registrant' );

		$this->assertArrayHasKey( 'id', $registrant, 'Registrant should have ID' );
		$this->assertArrayHasKey( 'email', $registrant, 'Registrant should have email' );
		$this->assertArrayHasKey( 'join_url', $registrant, 'Registrant should have join URL' );
		$this->assertEquals( 'approved', $registrant['status'], 'Default status should be approved' );
	}

	/**
	 * Test user meta storage for Zoom
	 */
	public function test_user_zoom_meta() {
		// Simulate Zoom registration
		$join_url = 'https://zoom.us/w/12345?tk=test123';
		$registrant_id = 'reg_abc123';

		update_user_meta( $this->user_id, 'dgptm_zoom_join_url', $join_url );
		update_user_meta( $this->user_id, 'dgptm_zoom_registrant_id', $registrant_id );

		$stored_url = get_user_meta( $this->user_id, 'dgptm_zoom_join_url', true );
		$stored_id = get_user_meta( $this->user_id, 'dgptm_zoom_registrant_id', true );

		$this->assertEquals( $join_url, $stored_url, 'Join URL should be stored' );
		$this->assertEquals( $registrant_id, $stored_id, 'Registrant ID should be stored' );
	}

	/**
	 * Test MV flag for voting eligibility
	 */
	public function test_mv_flag() {
		// User without MV flag
		$this->assertEmpty( get_user_meta( $this->user_id, 'mitgliederversammlung', true ), 'MV flag should be empty by default' );

		// Set MV flag
		update_user_meta( $this->user_id, 'mitgliederversammlung', 'true' );
		$flag = get_user_meta( $this->user_id, 'mitgliederversammlung', true );

		$this->assertEquals( 'true', $flag, 'MV flag should be set to true' );
	}

	/**
	 * Test Zoom settings storage
	 */
	public function test_zoom_settings() {
		$settings = array(
			'account_id'     => 'test_account',
			'client_id'      => 'test_client_id',
			'client_secret'  => 'test_secret',
			'zoom_id'        => '12345678901',
			'zoom_type'      => 'webinar',
			'webhook_secret' => 'webhook_secret_token',
		);

		update_option( 'dgptm_zoom_settings', $settings, false );

		$stored = get_option( 'dgptm_zoom_settings' );

		$this->assertEquals( $settings['account_id'], $stored['account_id'], 'Account ID should be stored' );
		$this->assertEquals( $settings['zoom_type'], $stored['zoom_type'], 'Zoom type should be stored' );
		$this->assertEquals( 11, strlen( $stored['zoom_id'] ), 'Zoom ID should be 11 characters' );

		delete_option( 'dgptm_zoom_settings' );
	}

	/**
	 * Test attendance option structure
	 */
	public function test_attendance_storage() {
		$meeting_id = '12345678901';
		$email = 'test@example.com';

		$success = DGPTM_Test_Helpers::create_attendance_record(
			$meeting_id,
			$email,
			array(
				'name'   => 'Test User',
				'status' => 'Mitglied',
				'total'  => 3600, // 1 hour in seconds
			)
		);

		$this->assertTrue( $success, 'Attendance record should be created' );

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$this->assertArrayHasKey( $key, $store, 'Meeting key should exist' );
		$this->assertArrayHasKey( 'participants', $store[ $key ], 'Participants should exist' );
		$this->assertArrayHasKey( $pk, $store[ $key ]['participants'], 'User should be in attendance' );

		$participant = $store[ $key ]['participants'][ $pk ];
		$this->assertEquals( 'Test User', $participant['name'], 'Name should match' );
		$this->assertEquals( 3600, $participant['total'], 'Total time should be 1 hour' );

		delete_option( 'dgptm_zoom_attendance' );
	}

	/**
	 * Test manual attendance flag
	 */
	public function test_manual_attendance() {
		$meeting_id = '12345678901';
		$email = 'manual@example.com';

		DGPTM_Test_Helpers::create_attendance_record(
			$meeting_id,
			$email,
			array(
				'name'   => 'Manual User',
				'manual' => 1,
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$participant = $store[ $key ]['participants'][ $pk ];
		$this->assertEquals( 1, $participant['manual'], 'Manual flag should be set' );

		delete_option( 'dgptm_zoom_attendance' );
	}

	/**
	 * Test join/leave timestamps
	 */
	public function test_join_leave_timestamps() {
		$meeting_id = '12345678901';
		$email = 'timestamps@example.com';

		$join_time = time();
		$leave_time = $join_time + 3600; // 1 hour later

		DGPTM_Test_Helpers::create_attendance_record(
			$meeting_id,
			$email,
			array(
				'join_first' => $join_time,
				'leave_last' => $leave_time,
				'total'      => 3600,
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$participant = $store[ $key ]['participants'][ $pk ];

		$this->assertEquals( $join_time, $participant['join_first'], 'Join time should match' );
		$this->assertEquals( $leave_time, $participant['leave_last'], 'Leave time should match' );
		$this->assertEquals( 3600, $participant['total'], 'Total duration should be 1 hour' );

		delete_option( 'dgptm_zoom_attendance' );
	}

	/**
	 * Test multiple participants in same meeting
	 */
	public function test_multiple_participants() {
		$meeting_id = '12345678901';

		DGPTM_Test_Helpers::create_attendance_record( $meeting_id, 'user1@example.com' );
		DGPTM_Test_Helpers::create_attendance_record( $meeting_id, 'user2@example.com' );
		DGPTM_Test_Helpers::create_attendance_record( $meeting_id, 'user3@example.com' );

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;

		$this->assertCount( 3, $store[ $key ]['participants'], 'Should have 3 participants' );

		delete_option( 'dgptm_zoom_attendance' );
	}

	/**
	 * Test participant without email (name-based key)
	 */
	public function test_name_based_participant() {
		$meeting_id = '12345678901';
		$name = 'User Without Email';

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $meeting_id;
		$pk    = 'name:' . md5( strtolower( $name ) );

		if ( ! isset( $store[ $key ]['participants'] ) ) {
			$store[ $key ]['participants'] = array();
		}

		$store[ $key ]['participants'][ $pk ] = array(
			'name'   => $name,
			'status' => 'Teilnehmer',
			'total'  => 1800,
		);

		update_option( 'dgptm_zoom_attendance', $store, false );

		$stored = get_option( 'dgptm_zoom_attendance' );
		$this->assertArrayHasKey( $pk, $stored[ $key ]['participants'], 'Name-based key should exist' );

		delete_option( 'dgptm_zoom_attendance' );
	}

	/**
	 * Test beamer state storage
	 */
	public function test_beamer_state() {
		$state = array(
			'poll_id'     => 123,
			'question_id' => 456,
			'mode'        => 'live',
			'released'    => 1,
		);

		update_option( 'dgptm_beamer_state', $state, false );

		$stored = get_option( 'dgptm_beamer_state' );

		$this->assertEquals( 123, $stored['poll_id'], 'Poll ID should match' );
		$this->assertEquals( 'live', $stored['mode'], 'Mode should be live' );
		$this->assertEquals( 1, $stored['released'], 'Released flag should be set' );

		delete_option( 'dgptm_beamer_state' );
	}
}
