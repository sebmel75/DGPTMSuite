<?php
/**
 * Attendance Tracking Integration Test
 *
 * @package DGPTM_Abstimmen\Tests
 */

class AttendanceTest extends WP_UnitTestCase {

	protected $meeting_id;
	protected $user_id;

	public function setUp(): void {
		parent::setUp();
		$this->meeting_id = '12345678901';
		$this->user_id = DGPTM_Test_Helpers::create_test_user();
	}

	public function tearDown(): void {
		delete_option( 'dgptm_zoom_attendance' );
		wp_delete_user( $this->user_id );
		parent::tearDown();
	}

	/**
	 * Test participant join event
	 */
	public function test_participant_join() {
		$email = 'join@example.com';
		$join_time = time();

		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			$email,
			array(
				'name'       => 'Join Test',
				'join_first' => $join_time,
				'leave_last' => 0,
				'total'      => 0,
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$this->assertArrayHasKey( $key, $store, 'Meeting should exist' );
		$participant = $store[ $key ]['participants'][ $pk ];

		$this->assertEquals( 'Join Test', $participant['name'], 'Name should match' );
		$this->assertEquals( $join_time, $participant['join_first'], 'Join time should be set' );
		$this->assertEquals( 0, $participant['leave_last'], 'Leave time should be 0' );
		$this->assertEquals( 0, $participant['total'], 'Total should be 0 initially' );
	}

	/**
	 * Test participant leave event
	 */
	public function test_participant_leave() {
		$email = 'leave@example.com';
		$join_time = time();
		$leave_time = $join_time + 1800; // 30 minutes later

		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			$email,
			array(
				'name'       => 'Leave Test',
				'join_first' => $join_time,
				'leave_last' => $leave_time,
				'total'      => 1800,
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$participant = $store[ $key ]['participants'][ $pk ];

		$this->assertEquals( $leave_time, $participant['leave_last'], 'Leave time should be set' );
		$this->assertEquals( 1800, $participant['total'], 'Total should be 30 minutes' );
	}

	/**
	 * Test participant rejoin (multiple join/leave cycles)
	 */
	public function test_participant_rejoin() {
		$email = 'rejoin@example.com';
		$join_time_1 = time();
		$leave_time_1 = $join_time_1 + 600; // 10 min session

		// First session
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			$email,
			array(
				'join_first' => $join_time_1,
				'leave_last' => $leave_time_1,
				'total'      => 600,
			)
		);

		// Rejoin after 5 minutes
		$join_time_2 = $leave_time_1 + 300;
		$leave_time_2 = $join_time_2 + 900; // 15 min session

		// Update with second session (join_first stays the same, total accumulates)
		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$store[ $key ]['participants'][ $pk ]['leave_last'] = $leave_time_2;
		$store[ $key ]['participants'][ $pk ]['total'] = 600 + 900; // Accumulate

		update_option( 'dgptm_zoom_attendance', $store, false );

		$updated = get_option( 'dgptm_zoom_attendance' );
		$participant = $updated[ $key ]['participants'][ $pk ];

		$this->assertEquals( $join_time_1, $participant['join_first'], 'First join time should remain' );
		$this->assertEquals( $leave_time_2, $participant['leave_last'], 'Last leave time should update' );
		$this->assertEquals( 1500, $participant['total'], 'Total should be 25 minutes (600 + 900)' );
	}

	/**
	 * Test manual presence entry
	 */
	public function test_manual_presence() {
		$email = 'manual@example.com';

		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			$email,
			array(
				'name'              => 'Manual Entry',
				'status'            => 'Mitglied',
				'mitgliedsart'      => 'Ordentliches Mitglied',
				'mitgliedsnummer'   => 'M12345',
				'manual'            => 1,
				'join_first'        => time(),
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:' . strtolower( $email );

		$participant = $store[ $key ]['participants'][ $pk ];

		$this->assertEquals( 1, $participant['manual'], 'Manual flag should be set' );
		$this->assertEquals( 'Ordentliches Mitglied', $participant['mitgliedsart'], 'Mitgliedsart should be set' );
		$this->assertEquals( 'M12345', $participant['mitgliedsnummer'], 'Mitgliedsnummer should be set' );
	}

	/**
	 * Test attendance list export data structure
	 */
	public function test_attendance_export_structure() {
		// Create 3 participants
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'user1@example.com',
			array(
				'name'              => 'User One',
				'status'            => 'Mitglied',
				'mitgliedsnummer'   => 'M001',
				'total'             => 3600,
			)
		);

		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'user2@example.com',
			array(
				'name'              => 'User Two',
				'status'            => 'Mitglied',
				'mitgliedsnummer'   => 'M002',
				'total'             => 1800,
				'manual'            => 1,
			)
		);

		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'user3@example.com',
			array(
				'name'   => 'User Three',
				'status' => 'Gast',
				'total'  => 7200,
			)
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;

		$this->assertCount( 3, $store[ $key ]['participants'], 'Should have 3 participants' );

		// Verify structure for export
		foreach ( $store[ $key ]['participants'] as $pk => $participant ) {
			$this->assertArrayHasKey( 'name', $participant, 'Each participant should have name' );
			$this->assertArrayHasKey( 'status', $participant, 'Each participant should have status' );
			$this->assertArrayHasKey( 'total', $participant, 'Each participant should have total time' );
		}
	}

	/**
	 * Test attendance with different member types
	 */
	public function test_member_types() {
		$member_types = array(
			'Ordentliches Mitglied',
			'Fördermitglied',
			'Ehrenmitglied',
			'Gast',
		);

		foreach ( $member_types as $index => $type ) {
			DGPTM_Test_Helpers::create_attendance_record(
				$this->meeting_id,
				"user{$index}@example.com",
				array(
					'name'         => "User {$index}",
					'status'       => $type,
					'mitgliedsart' => $type,
				)
			);
		}

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;

		$this->assertCount( 4, $store[ $key ]['participants'], 'Should have 4 different member types' );

		// Verify each type is stored correctly
		$stored_types = array();
		foreach ( $store[ $key ]['participants'] as $participant ) {
			$stored_types[] = $participant['status'];
		}

		$this->assertEquals( $member_types, $stored_types, 'All member types should be stored correctly' );
	}

	/**
	 * Test attendance duration calculation
	 */
	public function test_duration_calculation() {
		$email = 'duration@example.com';
		$durations = array(
			600,    // 10 min
			1800,   // 30 min
			3600,   // 1 hour
			7200,   // 2 hours
		);

		foreach ( $durations as $duration ) {
			$join = time();
			$leave = $join + $duration;

			DGPTM_Test_Helpers::create_attendance_record(
				$this->meeting_id . '_' . $duration,
				$email,
				array(
					'join_first' => $join,
					'leave_last' => $leave,
					'total'      => $duration,
				)
			);
		}

		$store = get_option( 'dgptm_zoom_attendance', array() );

		// Verify each duration
		foreach ( $durations as $duration ) {
			$key = 'auto:' . $this->meeting_id . '_' . $duration;
			$pk  = 'mail:' . strtolower( $email );

			$this->assertEquals(
				$duration,
				$store[ $key ]['participants'][ $pk ]['total'],
				"Duration {$duration} should be stored correctly"
			);
		}
	}

	/**
	 * Test attendance with special characters in name/email
	 */
	public function test_special_characters() {
		$test_cases = array(
			array(
				'name'  => 'Müller, Max',
				'email' => 'max.müller@example.com',
			),
			array(
				'name'  => "O'Brien, Sarah",
				'email' => 'sarah.obrien@example.com',
			),
			array(
				'name'  => 'José García',
				'email' => 'jose.garcia@example.com',
			),
		);

		foreach ( $test_cases as $index => $case ) {
			DGPTM_Test_Helpers::create_attendance_record(
				$this->meeting_id,
				$case['email'],
				array(
					'name' => $case['name'],
				)
			);
		}

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;

		$this->assertCount( 3, $store[ $key ]['participants'], 'Should handle special characters' );

		// Verify names are stored correctly
		foreach ( $test_cases as $case ) {
			$pk = 'mail:' . strtolower( $case['email'] );
			$this->assertEquals(
				$case['name'],
				$store[ $key ]['participants'][ $pk ]['name'],
				"Name with special characters should be stored: {$case['name']}"
			);
		}
	}

	/**
	 * Test clearing attendance data
	 */
	public function test_clear_attendance() {
		// Create test data
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'test@example.com'
		);

		$store = get_option( 'dgptm_zoom_attendance', array() );
		$this->assertNotEmpty( $store, 'Attendance data should exist' );

		// Clear data
		delete_option( 'dgptm_zoom_attendance' );

		$cleared = get_option( 'dgptm_zoom_attendance', array() );
		$this->assertEmpty( $cleared, 'Attendance data should be cleared' );
	}
}
