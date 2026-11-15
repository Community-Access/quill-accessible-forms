<?php
/**
 * The spam settings write route (POST qaf/v1/spam), the question bank routes
 * (spam/questions) and past weeks (spam/weeks): who may use them, what each
 * accepts and refuses, the conflict check, and a key that is never echoed.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-legacy-rest.php';

class QAF_Spam_Settings_Write_Test extends QAF_Spam_Test_Case {

	/** @var QAF_Spam_Memory_Store */
	private $store;

	protected function setUp(): void {
		parent::setUp();
		$this->store              = new QAF_Spam_Memory_Store();
		$GLOBALS['qaf_test_caps'] = array( 'manage_options' );
	}

	/** The route, reading the saved option as a live site does. */
	private function rest() {
		return new QAF_Spam_REST( null, $this->store );
	}

	private static function post( $body, $route_params = array() ) {
		$r = new WP_REST_Request( 'POST' );
		foreach ( array_merge( $body, $route_params ) as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return $r;
	}

	private static function saved() {
		return QAF_Spam_Settings::sanitize( get_option( QAF_Spam_Settings::OPTION, array() ) );
	}

	public function test_every_route_has_its_permission_check() {
		$this->rest()->register_routes();
		$routes = $GLOBALS['qaf_test_routes'];
		$this->assertSame( array( 'QAF_Spam_REST', 'can_view' ), $routes['qaf/v1/spam'][0]['permission_callback'] );
		$this->assertSame( array( 'QAF_Spam_REST', 'can_edit' ), $routes['qaf/v1/spam'][1]['permission_callback'] );
		$this->assertStringContainsString( 'POST', $routes['qaf/v1/spam'][1]['methods'] );
		$this->assertSame( array( 'QAF_Spam_REST', 'can_view' ), $routes['qaf/v1/spam/weeks']['permission_callback'] );
		foreach ( array( 'qaf/v1/spam/questions', 'qaf/v1/spam/questions/(?P<id>[0-9a-f]{12})' ) as $route ) {
			foreach ( $routes[ $route ] as $endpoint ) {
				$this->assertSame( array( 'QAF_Spam_REST', 'can_edit' ), $endpoint['permission_callback'], $route . ' ' . $endpoint['methods'] );
			}
		}
		$this->assertSame( '__return_true', $routes['qaf/v1/spam/pow']['permission_callback'] );
	}

	public function test_only_people_who_manage_settings_may_change_them() {
		$GLOBALS['qaf_test_caps'] = array();
		$this->assertFalse( QAF_Spam_REST::can_edit() );
		$GLOBALS['qaf_test_caps'] = array( 'qaf_view_entries' );
		$this->assertFalse( QAF_Spam_REST::can_edit(), 'seeing entries is not enough' );
		$this->assertTrue( QAF_Spam_REST::can_view() );
		$GLOBALS['qaf_test_caps'] = array( 'qaf_manage_settings' );
		$this->assertTrue( QAF_Spam_REST::can_edit() );
		$GLOBALS['qaf_test_caps'] = array( 'manage_options' );
		$this->assertTrue( QAF_Spam_REST::can_edit() );
	}

	public function test_a_write_changes_only_what_it_sends_and_says_what_changed() {
		update_option( QAF_Spam_Settings::OPTION, array( 'max_links' => 5, 'oopspam_api_key' => 'kept-key-123' ) );
		$reply = $this->rest()->update( self::post( array(
			'providers'         => array( 'akismet' => true, 'honeypot' => 'false' ),
			'min_seconds'       => '7',
			'challenge_mode'    => 'always',
			'challenge_kinds'   => array( 'maths' => 1 ),
			'send_anyway_after' => 2,
		) ) );
		$this->assertIsArray( $reply );
		$saved = self::saved();
		$this->assertTrue( $saved['providers']['akismet'] );
		$this->assertFalse( $saved['providers']['honeypot'] );
		$this->assertTrue( $saved['providers']['time'], 'a check not sent keeps its value' );
		$this->assertSame( 7, $saved['min_seconds'] );
		$this->assertSame( 5, $saved['max_links'], 'a limit not sent keeps its value' );
		$this->assertSame( 'kept-key-123', $saved['oopspam_api_key'], 'a key not sent is kept' );
		$this->assertSame( 'always', $saved['challenge_mode'] );
		$this->assertTrue( $saved['challenge_kinds']['maths'] );
		$this->assertSame( 2, $saved['send_anyway_after'] );
		$this->assertSame( array( 'providers', 'min_seconds', 'challenge_mode', 'challenge_kinds', 'send_anyway_after' ), $reply['changed'] );
		$this->assertSame( 7, $reply['limits']['min_seconds'], 'the reply is the state as now saved' );
		$this->assertSame( 2, $reply['questions']['send_anyway_after'] );
		$this->assertTrue( $reply['can_edit'] );
	}

	public function test_the_key_is_write_only() {
		$reply = $this->rest()->update( self::post( array( 'oopspam_api_key' => 'new-secret-key-42' ) ) );
		$this->assertSame( 'new-secret-key-42', self::saved()['oopspam_api_key'] );
		$this->assertTrue( $reply['oopspam_key_saved'] );
		$this->assertSame( array( 'oopspam_api_key' ), $reply['changed'] );
		$this->assertStringNotContainsString( 'new-secret-key-42', json_encode( $reply ) );
		$this->assertStringNotContainsString( 'new-secret-key-42', json_encode( $this->rest()->state( new WP_REST_Request( 'GET' ) ) ) );

		$bad = $this->rest()->update( self::post( array( 'oopspam_api_key' => 'has spaces <b>' ) ) );
		$this->assertSame( 'rest_invalid_param', $bad->get_error_code() );
		$this->assertStringNotContainsString( 'has spaces', $bad->get_error_message() . json_encode( $bad->get_error_data() ), 'a refused key is not repeated either' );
		$this->assertSame( 'new-secret-key-42', self::saved()['oopspam_api_key'] );

		$both = $this->rest()->update( self::post( array( 'oopspam_api_key' => 'another-key-99', 'oopspam_api_key_clear' => true ) ) );
		$this->assertSame( 400, $both->get_error_data()['status'] );

		$cleared = $this->rest()->update( self::post( array( 'oopspam_api_key_clear' => true ) ) );
		$this->assertFalse( $cleared['oopspam_key_saved'] );
		$this->assertSame( '', self::saved()['oopspam_api_key'] );
	}

	public function test_a_wrong_value_saves_nothing_and_every_wrong_one_is_named() {
		update_option( QAF_Spam_Settings::OPTION, array( 'max_links' => 5 ) );
		$before = get_option( QAF_Spam_Settings::OPTION );
		$err    = $this->rest()->update( self::post( array(
			'max_links'         => 51,
			'min_seconds'       => 2,
			'oopspam_threshold' => 'high',
			'challenge_mode'    => 'sometimes',
			'providers'         => array( 'nonsense' => true, 'links' => 'perhaps' ),
			'use_moderation'    => 'yes',
			'send_anyway_after' => 0,
			'challenge_kinds'   => array( 'sense' => false ),
		) ) );
		$this->assertSame( 'rest_invalid_param', $err->get_error_code() );
		$this->assertSame( 400, $err->get_error_data()['status'] );
		$this->assertSame(
			array( 'providers.nonsense', 'providers.links', 'use_moderation', 'min_seconds', 'max_links', 'oopspam_threshold', 'send_anyway_after', 'challenge_mode' ),
			array_keys( $err->get_error_data()['params'] )
		);
		$this->assertStringContainsString( 'max_links must be a whole number from 0 to 50.', $err->get_error_message() );
		$this->assertSame( $before, get_option( QAF_Spam_Settings::OPTION ), 'the good value sent beside them was not saved either' );
	}

	public function test_an_unknown_setting_is_refused() {
		$err = $this->rest()->update( self::post( array( 'challenge_bank' => 'x | y', 'max_links' => 2, '_locale' => 'user' ) ) );
		$this->assertSame( 'qaf_spam_unknown_setting', $err->get_error_code() );
		$this->assertSame( array( 'challenge_bank' ), $err->get_error_data()['unknown'], 'the bank has its own routes; WordPress\'s own _ names are ignored' );
		$this->assertFalse( get_option( QAF_Spam_Settings::OPTION ) );
	}

	public function test_a_write_after_somebody_elses_is_refused() {
		$read = $this->rest()->state( new WP_REST_Request( 'GET' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $read['revision'] );
		$ok = $this->rest()->update( self::post( array( 'max_links' => 4, 'if_revision' => $read['revision'] ) ) );
		$this->assertSame( 4, $ok['limits']['max_links'] );
		$this->assertNotSame( $read['revision'], $ok['revision'], 'a change gives a new revision' );

		$late = $this->rest()->update( self::post( array( 'max_links' => 9, 'if_revision' => $read['revision'] ) ) );
		$this->assertSame( 'qaf_spam_conflict', $late->get_error_code() );
		$this->assertSame( 409, $late->get_error_data()['status'] );
		$this->assertSame( $ok['revision'], $late->get_error_data()['revision'] );
		$this->assertSame( 4, self::saved()['max_links'] );
	}

	public function test_the_revision_covers_the_key_without_revealing_it() {
		$a = QAF_Spam_Settings::revision( QAF_Spam_Settings::defaults() );
		$b = QAF_Spam_Settings::revision( array_merge( QAF_Spam_Settings::defaults(), array( 'oopspam_api_key' => 'abcdefgh' ) ) );
		$this->assertNotSame( $a, $b, 'changing only the key is a change' );
		$this->assertSame( $a, QAF_Spam_Settings::revision( QAF_Spam_Settings::defaults() ) );
	}

	public function test_a_form_can_be_given_its_own_mode_and_given_back() {
		$this->rest()->update( self::post( array( 'form_modes' => array( '7' => 'always', 9 => 'never' ) ) ) );
		$this->assertSame( array( 7 => 'always', 9 => 'never' ), self::saved()['form_modes'] );
		$this->rest()->update( self::post( array( 'form_modes' => array( 7 => null ) ) ) );
		$this->assertSame( array( 9 => 'never' ), self::saved()['form_modes'] );
		$err = $this->rest()->update( self::post( array( 'form_modes' => array( 'x' => 'always' ) ) ) );
		$this->assertArrayHasKey( 'form_modes.x', $err->get_error_data()['params'] );
	}

	public function test_send_anyway_follows_the_setting() {
		$settings  = $this->settings( array( 'send_anyway_after' => 1 ) );
		$challenge = new QAF_Text_Challenge( $settings, $this->store, new QAF_Spam_Failures( $this->store ) );
		$s         = $this->submission();
		$this->assertNull( $challenge->issue( $s )['send_anyway'] );
		$challenge->record_failure( $s );
		$this->assertNotNull( $challenge->issue( $s )['send_anyway'], 'offered after one wrong answer' );
		$this->assertSame( 10, QAF_Spam_Settings::sanitize( array( 'send_anyway_after' => 99 ) )['send_anyway_after'] );
		$this->assertSame( 1, QAF_Spam_Settings::sanitize( array( 'send_anyway_after' => 0 ) )['send_anyway_after'], 'never turned off: a person is never locked out' );
		$this->assertSame( 3, QAF_Spam_Settings::defaults()['send_anyway_after'] );
	}

	// The question bank.

	public function test_the_bank_lists_each_question_with_an_id() {
		update_option( QAF_Spam_Settings::OPTION, array( 'challenge_bank' => "What colour is the sky? | blue\nNo answer here\nWhat do cows say? | moo | moo moo\nWhat colour is the sky? | blue" ) );
		$list = $this->rest()->questions( new WP_REST_Request( 'GET' ) );
		$this->assertSame( 200, $list['max'] );
		$this->assertCount( 2, $list['questions'], 'a line with no answer is not a question; a repeat is one question' );
		$this->assertSame( array( 'id', 'question', 'answers' ), array_keys( $list['questions'][0] ) );
		$this->assertSame( array( 'moo', 'moo moo' ), $list['questions'][1]['answers'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $list['questions'][0]['id'] );
	}

	public function test_a_question_is_added_edited_and_removed() {
		$added = $this->rest()->add_question( self::post( array( 'question' => '  Which is colder,   snow or soup? ', 'answers' => array( 'snow', 'Snow', 'snow' ) ) ) );
		$this->assertSame( 201, $added->get_status() );
		$q = $added->get_data();
		$this->assertSame( 'Which is colder, snow or soup?', $q['question'] );
		$this->assertSame( array( 'snow', 'Snow' ), $q['answers'], 'a repeated answer is kept once' );
		$this->assertSame( 'Which is colder, snow or soup? | snow | Snow', self::saved()['challenge_bank'] );

		$again = $this->rest()->add_question( self::post( array( 'question' => 'Which is colder, snow or soup?', 'answers' => array( 'snow', 'Snow' ) ) ) );
		$this->assertSame( 'qaf_spam_question_exists', $again->get_error_code() );

		$edited = $this->rest()->edit_question( self::post( array( 'answers' => array( 'snow' ) ), array( 'id' => $q['id'] ) ) );
		$this->assertSame( $q['id'], $edited['replaces'] );
		$this->assertNotSame( $q['id'], $edited['id'], 'the id follows the words' );
		$this->assertSame( 'Which is colder, snow or soup?', $edited['question'], 'what was not sent is kept' );

		$stale = $this->rest()->edit_question( self::post( array( 'question' => 'x' ), array( 'id' => $q['id'] ) ) );
		$this->assertSame( 'qaf_spam_question_not_found', $stale->get_error_code() );
		$this->assertSame( 404, $stale->get_error_data()['status'] );

		$gone = $this->rest()->delete_question( self::post( array(), array( 'id' => $edited['id'] ) ) );
		$this->assertSame( array( 'deleted' => true, 'id' => $edited['id'], 'remaining' => 0 ), $gone );
		$this->assertSame( '', self::saved()['challenge_bank'] );
		$this->assertSame( 404, $this->rest()->delete_question( self::post( array(), array( 'id' => $edited['id'] ) ) )->get_error_data()['status'] );
	}

	public function test_a_question_that_would_break_the_bank_is_refused() {
		foreach ( array(
			array( array( 'question' => 'A | B', 'answers' => array( 'x' ) ), 'question' ),
			array( array( 'question' => 'Fine?', 'answers' => array() ), 'answers' ),
			array( array( 'question' => 'Fine?', 'answers' => 'yes' ), 'answers' ),
			array( array( 'question' => 'Fine?', 'answers' => array( 'yes', 'a|b' ) ), 'answers.1' ),
			array( array( 'question' => '', 'answers' => array( 'yes' ) ), 'question' ),
			array( array( 'question' => str_repeat( 'q', 301 ), 'answers' => array( 'yes' ) ), 'question' ),
			array( array( 'question' => 'Fine?', 'answers' => array_fill( 0, 11, 'a' ) ), 'answers' ),
		) as $case ) {
			$err = $this->rest()->add_question( self::post( $case[0] ) );
			$this->assertSame( 'rest_invalid_param', $err->get_error_code(), json_encode( $case[0] ) );
			$this->assertArrayHasKey( $case[1], $err->get_error_data()['params'] );
		}
		$this->assertFalse( get_option( QAF_Spam_Settings::OPTION ), 'nothing was saved' );
	}

	public function test_a_full_bank_takes_no_more() {
		$lines = array();
		for ( $i = 0; $i < QAF_Spam_Settings::BANK_MAX; $i++ ) {
			$lines[] = "Question $i? | answer $i";
		}
		update_option( QAF_Spam_Settings::OPTION, array( 'challenge_bank' => implode( "\n", $lines ) ) );
		$err = $this->rest()->add_question( self::post( array( 'question' => 'One more?', 'answers' => array( 'no' ) ) ) );
		$this->assertSame( 'qaf_spam_bank_full', $err->get_error_code() );
	}

	public function test_the_questions_asked_are_the_banks() {
		$this->rest()->add_question( self::post( array( 'question' => 'What do cows say?', 'answers' => array( 'moo' ) ) ) );
		$challenge = new QAF_Text_Challenge( new QAF_Spam_Settings( self::saved() ), $this->store, new QAF_Spam_Failures( $this->store ) );
		$this->assertSame( array( array( 'question' => 'What do cows say?', 'answers' => array( 'moo' ) ) ), $challenge->site_questions() );
	}

	// Past weeks.

	public function test_past_weeks_come_newest_first_with_no_gaps() {
		$now       = time();
		$this_week = gmdate( 'o-\WW', $now );
		$two_ago   = gmdate( 'o-\WW', $now - 14 * DAY_IN_SECONDS );
		$this->store->set( 'stats:' . $this_week, array( 'asked' => 5, 'passed' => 4 ), 100 * DAY_IN_SECONDS );
		$this->store->set( 'stats:' . $two_ago, array( 'asked' => 9, 'failed' => 3 ), 100 * DAY_IN_SECONDS );
		$r = new WP_REST_Request( 'GET' );
		$r->set_param( 'weeks', 3 );
		$weeks = $this->rest()->weeks( $r );
		$this->assertSame( 100, $weeks['kept_days'] );
		$this->assertCount( 3, $weeks['weeks'] );
		$this->assertSame( array( $this_week, 5, 4 ), array( $weeks['weeks'][0]['week'], $weeks['weeks'][0]['asked'], $weeks['weeks'][0]['passed'] ) );
		$this->assertSame( 0, $weeks['weeks'][1]['asked'], 'a quiet week is there with noughts' );
		$this->assertSame( array( $two_ago, 9, 3 ), array( $weeks['weeks'][2]['week'], $weeks['weeks'][2]['asked'], $weeks['weeks'][2]['failed'] ) );
		$r->set_param( 'weeks', 500 );
		$this->assertCount( QAF_Text_Challenge::HISTORY_MAX, $this->rest()->weeks( $r )['weeks'] );
	}

	public function test_a_weeks_numbers_are_kept_long_enough_to_compare() {
		$challenge = new QAF_Text_Challenge( $this->settings(), $this->store, new QAF_Spam_Failures( $this->store ) );
		$challenge->issue( $this->submission() );
		$row = $this->store->rows[ 'stats:' . gmdate( 'o-\WW', time() ) ];
		$this->assertGreaterThanOrEqual( time() + 91 * DAY_IN_SECONDS, $row['expires'], 'thirteen weeks at least' );
	}

	// The old name.

	public function test_baf_v1_reads_spam_but_cannot_change_it() {
		$cb      = function () {};
		$aliases = QAF_Legacy_REST::aliases( array(
			'/qaf/v1/spam'           => array(
				array( 'methods' => array( 'GET' => true ), 'callback' => $cb, 'permission_callback' => array( 'QAF_Spam_REST', 'can_view' ) ),
				array( 'methods' => array( 'POST' => true, 'PUT' => true, 'PATCH' => true ), 'callback' => $cb, 'permission_callback' => array( 'QAF_Spam_REST', 'can_edit' ) ),
			),
			'/qaf/v1/spam/weeks'     => array( array( 'methods' => array( 'GET' => true ), 'callback' => $cb ) ),
			'/qaf/v1/spam/questions/(?P<id>[0-9a-f]{12})' => array(
				array( 'methods' => array( 'POST' => true ), 'callback' => $cb ),
				array( 'methods' => array( 'DELETE' => true ), 'callback' => $cb ),
			),
		) );
		$this->assertSame( array( '/spam', '/spam/weeks' ), array_keys( $aliases ) );
		$this->assertCount( 1, $aliases['/spam'] );
		$this->assertSame( array( 'QAF_Spam_REST', 'can_view' ), $aliases['/spam'][0]['permission_callback'] );
	}
}
