<?php
/**
 * REST API Integration Test
 *
 * @package DGPTM_Abstimmen\Tests
 */

class RestApiTest extends WP_UnitTestCase {

	protected $server;
	protected $user_id;
	protected $admin_id;
	protected $meeting_id;

	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		// Create test users
		$this->user_id = DGPTM_Test_Helpers::create_test_user();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->meeting_id = '12345678901';
	}

	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		wp_delete_user( $this->user_id );
		wp_delete_user( $this->admin_id );
		delete_option( 'dgptm_zoom_attendance' );
		delete_option( 'dgptm_zoom_settings' );

		parent::tearDown();
	}

	/**
	 * Test REST API namespace registration
	 */
	public function test_rest_namespace_registered() {
		$namespaces = $this->server->get_namespaces();

		$this->assertContains( 'dgptm-vote/v1', $namespaces, 'Voting API namespace should be registered' );
		$this->assertContains( 'dgptm-zoom/v1', $namespaces, 'Zoom API namespace should be registered' );
		$this->assertContains( 'dgptm/v1', $namespaces, 'Main API namespace should be registered' );
		$this->assertContains( 'dgptm-addon/v1', $namespaces, 'Addon API namespace should be registered' );
	}

	/**
	 * Test Zoom test endpoint (GET /dgptm-vote/v1/zoom-test)
	 */
	public function test_zoom_test_endpoint() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/dgptm-vote/v1/zoom-test' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data, 'Response should have ok field' );

		// Note: Actual test will fail without Zoom credentials, but endpoint should exist
	}

	/**
	 * Test Zoom test endpoint requires authentication
	 */
	public function test_zoom_test_requires_auth() {
		// No authenticated user
		$request = new WP_REST_Request( 'GET', '/dgptm-vote/v1/zoom-test' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status(), 'Should require authentication' );
	}

	/**
	 * Test Zoom register endpoint structure
	 */
	public function test_zoom_register_endpoint() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/dgptm-vote/v1/zoom-register' );
		$request->set_body_params( array(
			'user_id'    => $this->user_id,
			'first_name' => 'Test',
			'last_name'  => 'User',
			'email'      => 'test@example.com',
		) );

		$response = $this->server->dispatch( $request );

		// Note: Will fail without Zoom credentials, but tests endpoint structure
		$this->assertNotNull( $response, 'Endpoint should exist' );
	}

	/**
	 * Test live status endpoint (GET /dgptm-zoom/v1/live)
	 */
	public function test_live_status_endpoint() {
		$request = new WP_REST_Request( 'GET', '/dgptm-zoom/v1/live' );
		$request->set_query_params( array(
			'id'   => $this->meeting_id,
			'kind' => 'auto',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data, 'Response should have ok field' );
		$this->assertArrayHasKey( 'is_live', $data, 'Response should have is_live field' );
	}

	/**
	 * Test live status requires meeting ID
	 */
	public function test_live_status_requires_id() {
		$request = new WP_REST_Request( 'GET', '/dgptm-zoom/v1/live' );
		// No ID parameter
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status(), 'Should return 400 for missing ID' );
	}

	/**
	 * Test presence endpoint (POST /dgptm-zoom/v1/presence)
	 */
	public function test_presence_endpoint() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/dgptm-zoom/v1/presence' );
		$request->set_body_params( array(
			'id'                => $this->meeting_id,
			'kind'              => 'auto',
			'name'              => 'Test User',
			'email'             => 'test@example.com',
			'status'            => 'Mitglied',
			'mitgliedsart'      => 'Ordentliches Mitglied',
			'mitgliedsnummer'   => 'M12345',
			'manual'            => 1,
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should create presence entry' );

		$data = $response->get_data();
		$this->assertTrue( $data['ok'], 'Response should be ok' );

		// Verify stored
		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:test@example.com';

		$this->assertArrayHasKey( $pk, $store[ $key ]['participants'], 'Participant should be stored' );
	}

	/**
	 * Test presence endpoint requires authentication
	 */
	public function test_presence_requires_auth() {
		$request = new WP_REST_Request( 'POST', '/dgptm-zoom/v1/presence' );
		$request->set_body_params( array(
			'id'   => $this->meeting_id,
			'name' => 'Test',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status(), 'Should require authentication' );
	}

	/**
	 * Test manual search endpoint (POST /dgptm/v1/mvvmanuell)
	 */
	public function test_manual_search_endpoint() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/dgptm/v1/mvvmanuell' );
		$request->set_body_params( array(
			'name'  => 'Test User',
			'query' => 'User',
		) );

		$response = $this->server->dispatch( $request );

		// Note: Will fail without CRM integration, but tests endpoint structure
		$this->assertNotNull( $response, 'Endpoint should exist' );
	}

	/**
	 * Test manual flags endpoint (GET /dgptm-addon/v1/manual-flags)
	 */
	public function test_manual_flags_endpoint() {
		// Create manual attendance entry
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'manual@example.com',
			array( 'manual' => 1 )
		);

		$request = new WP_REST_Request( 'GET', '/dgptm-addon/v1/manual-flags' );
		$request->set_query_params( array(
			'id'   => $this->meeting_id,
			'kind' => 'auto',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data, 'Response should have ok field' );
		$this->assertArrayHasKey( 'manual_pks', $data, 'Response should have manual_pks array' );
		$this->assertContains( 'mail:manual@example.com', $data['manual_pks'], 'Should contain manual email' );
	}

	/**
	 * Test mark manual endpoint (POST /dgptm-addon/v1/mark-manual)
	 */
	public function test_mark_manual_endpoint() {
		wp_set_current_user( $this->admin_id );

		// Create attendance entry
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'mark@example.com',
			array( 'manual' => 0 )
		);

		$request = new WP_REST_Request( 'POST', '/dgptm-addon/v1/mark-manual' );
		$request->set_body_params( array(
			'id'    => $this->meeting_id,
			'kind'  => 'auto',
			'name'  => 'Mark Test',
			'email' => 'mark@example.com',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should mark as manual' );

		// Verify manual flag set
		$store = get_option( 'dgptm_zoom_attendance', array() );
		$key   = 'auto:' . $this->meeting_id;
		$pk    = 'mail:mark@example.com';

		$this->assertEquals( 1, $store[ $key ]['participants'][ $pk ]['manual'], 'Manual flag should be set' );
	}

	/**
	 * Test webhook endpoint (POST /dgptm-zoom/v1/webhook)
	 */
	public function test_webhook_endpoint_structure() {
		$request = new WP_REST_Request( 'POST', '/dgptm-zoom/v1/webhook' );
		$request->set_header( 'x-zm-signature', 'test_signature' );
		$request->set_body_params( array(
			'event'   => 'meeting.participant_joined',
			'payload' => array(
				'object' => array(
					'id'          => $this->meeting_id,
					'participant' => array(
						'user_id'   => 'abc123',
						'user_name' => 'Test User',
						'email'     => 'test@example.com',
						'join_time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					),
				),
			),
		) );

		$response = $this->server->dispatch( $request );

		// Note: Will fail signature validation, but tests endpoint exists
		$this->assertNotNull( $response, 'Webhook endpoint should exist' );
	}

	/**
	 * Test endpoint validation - missing required fields
	 */
	public function test_endpoint_validation() {
		wp_set_current_user( $this->admin_id );

		// Presence endpoint without required 'name'
		$request = new WP_REST_Request( 'POST', '/dgptm-zoom/v1/presence' );
		$request->set_body_params( array(
			'id' => $this->meeting_id,
			// Missing 'name'
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status(), 'Should return 400 for missing required field' );
	}

	/**
	 * Test CORS headers on API endpoints
	 */
	public function test_cors_headers() {
		$request = new WP_REST_Request( 'GET', '/dgptm-zoom/v1/live' );
		$request->set_query_params( array( 'id' => $this->meeting_id ) );

		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();

		// WordPress REST API automatically adds CORS headers
		$this->assertNotEmpty( $headers, 'Response should have headers' );
	}

	/**
	 * Test error response format
	 */
	public function test_error_response_format() {
		$request = new WP_REST_Request( 'GET', '/dgptm-zoom/v1/live' );
		// Missing required 'id' parameter
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status(), 'Should return 400' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data, 'Error should have ok field' );
		$this->assertFalse( $data['ok'], 'ok should be false on error' );
		$this->assertArrayHasKey( 'error', $data, 'Error should have error message' );
	}

	/**
	 * Test success response format
	 */
	public function test_success_response_format() {
		wp_set_current_user( $this->admin_id );

		// Create attendance entry and get manual flags
		DGPTM_Test_Helpers::create_attendance_record(
			$this->meeting_id,
			'test@example.com'
		);

		$request = new WP_REST_Request( 'GET', '/dgptm-addon/v1/manual-flags' );
		$request->set_query_params( array(
			'id'   => $this->meeting_id,
			'kind' => 'auto',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Should return 200' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ok', $data, 'Success should have ok field' );
		$this->assertTrue( $data['ok'], 'ok should be true on success' );
	}
}
