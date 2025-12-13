<?php
/**
 * Helpers Test
 *
 * @package DGPTM_Abstimmen\Tests
 */

class HelpersTest extends WP_UnitTestCase {

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
	 * Test manager permission check
	 */
	public function test_is_manager() {
		// Test with admin
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->assertTrue( dgptm_is_manager( $admin_id ), 'Admin should be manager' );

		// Test with manager meta
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'toggle_abstimmungsmanager', '1' );
		$this->assertTrue( dgptm_is_manager( $user_id ), 'User with toggle should be manager' );

		// Test without permission
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->assertFalse( dgptm_is_manager( $subscriber_id ), 'Regular subscriber should not be manager' );
	}

	/**
	 * Test voting code generation
	 */
	public function test_code_generation() {
		$code = DGPTM_Test_Helpers::generate_code();

		$this->assertIsString( $code, 'Code should be string' );
		$this->assertEquals( 6, strlen( $code ), 'Code should be 6 characters' );
		$this->assertTrue( is_numeric( $code ), 'Code should be numeric' );
	}

	/**
	 * Test user voting status
	 */
	public function test_user_voting_status() {
		// Test ON status
		update_user_meta( $this->user_id, 'dgptm_vote_status', 'on' );
		$status = get_user_meta( $this->user_id, 'dgptm_vote_status', true );
		$this->assertEquals( 'on', $status, 'Status should be ON' );

		// Test OFF status
		update_user_meta( $this->user_id, 'dgptm_vote_status', 'off' );
		$status = get_user_meta( $this->user_id, 'dgptm_vote_status', true );
		$this->assertEquals( 'off', $status, 'Status should be OFF' );

		// Test had_on flag
		update_user_meta( $this->user_id, 'dgptm_vote_had_on', '1' );
		$had_on = get_user_meta( $this->user_id, 'dgptm_vote_had_on', true );
		$this->assertEquals( '1', $had_on, 'Had ON flag should be set' );
	}

	/**
	 * Test MV flag
	 */
	public function test_mv_flag() {
		update_user_meta( $this->user_id, 'mitgliederversammlung', 'true' );
		$flag = get_user_meta( $this->user_id, 'mitgliederversammlung', true );
		$this->assertEquals( 'true', $flag, 'MV flag should be true' );
	}
}
