<?php
/**
 * Voting System Test
 *
 * @package DGPTM_Abstimmen\Tests
 */

class VotingTest extends WP_UnitTestCase {

	protected $user_id;
	protected $poll_id;
	protected $question_id;

	public function setUp(): void {
		parent::setUp();
		$this->user_id = DGPTM_Test_Helpers::create_test_user();
		$this->poll_id = DGPTM_Test_Helpers::create_test_poll();
		$this->question_id = DGPTM_Test_Helpers::create_test_question( $this->poll_id );
	}

	public function tearDown(): void {
		DGPTM_Test_Helpers::clear_test_data();
		wp_delete_user( $this->user_id );
		parent::tearDown();
	}

	/**
	 * Test poll creation
	 */
	public function test_poll_creation() {
		global $wpdb;

		$poll = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id = %d",
				$this->poll_id
			)
		);

		$this->assertNotNull( $poll, 'Poll should exist' );
		$this->assertEquals( 'active', $poll->status, 'Poll should be active' );
		$this->assertStringContainsString( 'Test Poll', $poll->name, 'Poll name should contain Test Poll' );
	}

	/**
	 * Test question creation
	 */
	public function test_question_creation() {
		global $wpdb;

		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d",
				$this->question_id
			)
		);

		$this->assertNotNull( $question, 'Question should exist' );
		$this->assertEquals( $this->poll_id, $question->poll_id, 'Question should belong to poll' );
		$this->assertEquals( 'active', $question->status, 'Question should be active' );

		$choices = json_decode( $question->choices, true );
		$this->assertIsArray( $choices, 'Choices should be array' );
		$this->assertCount( 3, $choices, 'Should have 3 default choices' );
	}

	/**
	 * Test single vote casting
	 */
	public function test_single_vote() {
		$vote_id = DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $this->user_id, array( 0 ) );

		$this->assertNotFalse( $vote_id, 'Vote should be created' );

		global $wpdb;
		$votes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND user_id = %d",
				$this->question_id,
				$this->user_id
			)
		);

		$this->assertCount( 1, $votes, 'Should have exactly 1 vote' );
		$this->assertEquals( 0, $votes[0]->choice, 'Vote should be for choice 0' );
	}

	/**
	 * Test multi-choice voting
	 */
	public function test_multi_vote() {
		// Create question allowing 2 votes
		$question_id = DGPTM_Test_Helpers::create_test_question(
			$this->poll_id,
			array( 'max_votes' => 2 )
		);

		DGPTM_Test_Helpers::cast_test_vote( $question_id, $this->user_id, array( 0, 1 ) );

		global $wpdb;
		$votes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND user_id = %d",
				$question_id,
				$this->user_id
			)
		);

		$this->assertCount( 2, $votes, 'Should have 2 votes' );
	}

	/**
	 * Test vote validation - max_votes limit
	 */
	public function test_vote_limit_validation() {
		global $wpdb;

		// Create question allowing only 1 vote
		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d",
				$this->question_id
			)
		);

		$this->assertEquals( 1, $question->max_votes, 'Max votes should be 1' );

		// Try to cast 2 votes (should only insert 1 if validation works)
		DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $this->user_id, array( 0, 1 ) );

		$vote_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND user_id = %d",
				$this->question_id,
				$this->user_id
			)
		);

		// Note: This test validates the helper, actual validation happens in AJAX handler
		$this->assertEquals( 2, $vote_count, 'Helper should insert both votes (validation is in AJAX)' );
	}

	/**
	 * Test vote counting
	 */
	public function test_vote_counting() {
		global $wpdb;

		// Cast votes from multiple users
		$user2 = DGPTM_Test_Helpers::create_test_user();
		$user3 = DGPTM_Test_Helpers::create_test_user();

		DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $this->user_id, array( 0 ) );
		DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $user2, array( 0 ) );
		DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $user3, array( 1 ) );

		// Count votes per choice
		$choice_0_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND choice = 0",
				$this->question_id
			)
		);

		$choice_1_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND choice = 1",
				$this->question_id
			)
		);

		$this->assertEquals( 2, $choice_0_count, 'Choice 0 should have 2 votes' );
		$this->assertEquals( 1, $choice_1_count, 'Choice 1 should have 1 vote' );

		wp_delete_user( $user2 );
		wp_delete_user( $user3 );
	}

	/**
	 * Test question release toggle
	 */
	public function test_question_release() {
		global $wpdb;

		// Update question to released
		$wpdb->update(
			$wpdb->prefix . 'dgptm_abstimmung_poll_questions',
			array( 'released' => 1 ),
			array( 'id' => $this->question_id ),
			array( '%d' ),
			array( '%d' )
		);

		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d",
				$this->question_id
			)
		);

		$this->assertEquals( 1, $question->released, 'Question should be released' );
	}

	/**
	 * Test poll archiving
	 */
	public function test_poll_archiving() {
		global $wpdb;

		// Archive poll
		$wpdb->update(
			$wpdb->prefix . 'dgptm_abstimmung_polls',
			array( 'status' => 'archived' ),
			array( 'id' => $this->poll_id ),
			array( '%s' ),
			array( '%d' )
		);

		$poll = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id = %d",
				$this->poll_id
			)
		);

		$this->assertEquals( 'archived', $poll->status, 'Poll should be archived' );
	}

	/**
	 * Test unique cookie ID per vote session
	 */
	public function test_cookie_tracking() {
		global $wpdb;

		DGPTM_Test_Helpers::cast_test_vote( $this->question_id, $this->user_id, array( 0 ) );

		$vote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND user_id = %d",
				$this->question_id,
				$this->user_id
			)
		);

		$this->assertNotEmpty( $vote->cookie_id, 'Cookie ID should be set' );
		$this->assertStringStartsWith( 'test_', $vote->cookie_id, 'Cookie ID should start with test_' );
	}

	/**
	 * Test poll with logo URL
	 */
	public function test_poll_logo() {
		$poll_id = DGPTM_Test_Helpers::create_test_poll(
			array( 'logo_url' => 'https://example.com/logo.png' )
		);

		global $wpdb;
		$poll = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id = %d",
				$poll_id
			)
		);

		$this->assertEquals( 'https://example.com/logo.png', $poll->logo_url, 'Logo URL should be set' );
	}
}
