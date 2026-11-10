<?php
/**
 * The rule that matters most (QUILL's wordpress.md 13a.2, WP-R-026 and
 * WP-R-027): a doubt holds the entry with its reason, never drops it, and
 * a check that judges behaviour may only hold.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';

/**
 * A provider that says whatever the test tells it to.
 */
class QAF_Scripted_Check extends QAF_Spam_Provider_Base {
	public $name;
	public $verdict;
	public $calls = 0;

	public function __construct( QAF_Spam_Settings $settings, $name, $verdict ) {
		parent::__construct( $settings );
		$this->name    = $name;
		$this->verdict = $verdict;
	}

	public function id() {
		return $this->name;
	}

	public function label() {
		return $this->name;
	}

	public function check( QAF_Spam_Submission $s ) {
		$this->calls++;
		if ( $this->verdict instanceof Throwable ) {
			throw $this->verdict;
		}
		return $this->verdict;
	}
}

class QAF_Spam_Hold_Rule_Test extends QAF_Spam_Test_Case {

	private function guard( $providers ) {
		$settings = $this->settings();
		return new QAF_Spam_Guard( new QAF_Spam_Registry( $settings, $providers ), $settings );
	}

	public function test_nothing_doubtful_is_saved_as_active() {
		$s = $this->settings();
		$d = $this->guard( array( new QAF_Scripted_Check( $s, 'one', QAF_Spam_Verdict::pass( 'one' ) ) ) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::SAVE, $d->action );
		$this->assertSame( 'active', $d->status() );
		$this->assertNull( $d->storage()['spam_reason'] );
	}

	public function test_a_sure_check_holds_with_its_reason_and_score() {
		$s = $this->settings();
		$d = $this->guard( array(
			new QAF_Scripted_Check( $s, 'one', QAF_Spam_Verdict::pass( 'one' ) ),
			new QAF_Scripted_Check( $s, 'service', QAF_Spam_Verdict::spam( 'service', 'Service score 5 of 6 (the limit is 3).', 5, 6 ) ),
		) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::HOLD, $d->action );
		$this->assertTrue( $d->saves(), 'held entries are saved, never dropped' );
		$this->assertSame( 'spam', $d->status() );
		$stored = $d->storage();
		$this->assertSame( 'service', $stored['spam_check'] );
		$this->assertSame( 'Service score 5 of 6 (the limit is 3).', $stored['spam_reason'] );
		$this->assertSame( 5.0, $stored['spam_score'] );
		$details = json_decode( $stored['spam_details'], true );
		$this->assertTrue( $details['held_at_submit'] );
		$this->assertSame( 6.0, (float) $details['score_max'] );
	}

	public function test_a_doubt_holds_when_no_question_can_be_asked() {
		$s = $this->settings();
		$d = $this->guard( array( new QAF_Scripted_Check( $s, 'time', QAF_Spam_Verdict::unsure( 'time', 'Filled in 1 second.', 1, null, true ) ) ) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::HOLD, $d->action );
		$this->assertSame( 'Filled in 1 second.', $d->storage()['spam_reason'] );
	}

	public function test_a_sure_verdict_explains_the_hold_ahead_of_a_doubt() {
		$s = $this->settings();
		$d = $this->guard( array(
			new QAF_Scripted_Check( $s, 'time', QAF_Spam_Verdict::unsure( 'time', 'fast', null, null, true ) ),
			new QAF_Scripted_Check( $s, 'honeypot', QAF_Spam_Verdict::spam( 'honeypot', 'filled' ) ),
		) )->decide( $this->submission() );
		$this->assertSame( 'honeypot', $d->storage()['spam_check'] );
		$this->assertCount( 2, json_decode( $d->storage()['spam_details'], true )['checks'], 'every doubt is kept' );
	}

	public function test_a_behavioural_signal_can_never_refuse_or_demand() {
		$v = new QAF_Spam_Verdict( 'recaptcha', QAF_Spam_Verdict::REFUSE, 'low score', 0.1, 1, true );
		$this->assertSame( QAF_Spam_Verdict::UNSURE, $v->verdict );
		$v = new QAF_Spam_Verdict( 'turnstile', QAF_Spam_Verdict::INCOMPLETE, 'failed', null, null, true );
		$this->assertSame( QAF_Spam_Verdict::UNSURE, $v->verdict );
	}

	public function test_a_third_party_verdict_can_hold_but_never_refuse() {
		$v = QAF_Spam_Verdict::from( array( 'provider' => 'Other Plugin', 'verdict' => 'refuse', 'reason' => 'blocked' ) );
		$this->assertSame( QAF_Spam_Verdict::UNSURE, $v->verdict );
		$this->assertSame( 'otherplugin', $v->provider );
		$this->assertNull( QAF_Spam_Verdict::from( array( 'verdict' => 'spam' ) ), 'a verdict needs a provider' );
		$this->assertNull( QAF_Spam_Verdict::from( 'spam' ) );
	}

	public function test_only_the_flood_ceiling_refuses_and_it_says_why() {
		$s = $this->settings();
		$d = $this->guard( array(
			new QAF_Scripted_Check( $s, 'rate_limit', QAF_Spam_Verdict::refuse( 'rate_limit', 'More than 100 sends today.', 'Please try again tomorrow.' ) ),
		) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::REFUSE, $d->action );
		$this->assertFalse( $d->saves() );
		$this->assertSame( 'Please try again tomorrow.', $d->message );
	}

	public function test_an_unfinished_visible_captcha_is_asked_for_again() {
		$s = $this->settings();
		$d = $this->guard( array( new QAF_Scripted_Check( $s, 'captcha', QAF_Spam_Verdict::incomplete( 'captcha', 'Please complete the CAPTCHA.' ) ) ) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::INCOMPLETE, $d->action );
		$this->assertSame( 'Please complete the CAPTCHA.', $d->message );
	}

	public function test_a_broken_provider_costs_the_visitor_nothing() {
		$s = $this->settings();
		$d = $this->guard( array( new QAF_Scripted_Check( $s, 'broken', new RuntimeException( 'service down' ) ) ) )->decide( $this->submission() );
		$this->assertSame( QAF_Spam_Decision::SAVE, $d->action );
	}

	public function test_a_provider_that_is_turned_off_does_not_run() {
		$s     = $this->settings( array( 'providers' => array( 'honeypot' => false ) ) );
		$check = new QAF_Scripted_Check( $s, 'honeypot', QAF_Spam_Verdict::spam( 'honeypot', 'filled' ) );
		$d     = ( new QAF_Spam_Guard( new QAF_Spam_Registry( $s, array( $check ) ), $s ) )->decide( $this->submission() );
		$this->assertSame( 0, $check->calls );
		$this->assertSame( QAF_Spam_Decision::SAVE, $d->action );
	}

	public function test_the_time_to_fill_is_never_judged_under_three_seconds() {
		$this->assertSame( 3, ( new QAF_Spam_Settings( array( 'min_seconds' => 1 ) ) )->get( 'min_seconds' ) );
		$this->assertSame( 3, ( new QAF_Spam_Settings( array( 'min_seconds' => -5 ) ) )->get( 'min_seconds' ) );
		$this->assertSame( 8, ( new QAF_Spam_Settings( array( 'min_seconds' => 8 ) ) )->get( 'min_seconds' ) );
	}

	public function test_defaults_keep_content_sending_providers_off() {
		$p = QAF_Spam_Settings::defaults()['providers'];
		foreach ( array( 'akismet', 'oopspam', 'cleantalk', 'shield' ) as $id ) {
			$this->assertFalse( $p[ $id ], $id . ' is off until the owner turns it on' );
		}
		foreach ( array( 'honeypot', 'time', 'links', 'rate_limit' ) as $id ) {
			$this->assertTrue( $p[ $id ], $id );
		}
		$this->assertSame( 3, QAF_Spam_Settings::defaults()['max_links'] );
	}

	public function test_an_unchecked_box_on_the_settings_page_sticks() {
		$from_page = QAF_Spam_Settings::sanitize( array( 'providers' => array( 'time' => '1' ) ), true );
		$this->assertTrue( $from_page['providers']['time'] );
		$this->assertFalse( $from_page['providers']['honeypot'], 'a box that was not sent is off' );
		$from_code = QAF_Spam_Settings::sanitize( array( 'providers' => array( 'time' => '1' ) ) );
		$this->assertTrue( $from_code['providers']['honeypot'], 'outside the page, a missing value keeps its default' );
	}

	public function test_the_schema_upgrade_adds_the_reason_columns_and_the_send_log() {
		$src = file_get_contents( QAF_PLUGIN_DIR . 'includes/class-qaf-activator.php' );
		foreach ( array( 'spam_check varchar(50)', 'spam_reason varchar(255)', 'spam_score decimal', 'spam_details longtext', '$table_rate_events' ) as $part ) {
			$this->assertStringContainsString( $part, $src );
		}
		require_once QAF_PLUGIN_DIR . 'includes/class-qaf-activator.php';
		$this->assertGreaterThanOrEqual( 2, QAF_Activator::SCHEMA_VERSION );
		$this->assertFalse( QAF_Activator::schema_ready() );
		update_option( 'qaf_schema_version', QAF_Activator::SCHEMA_VERSION );
		$this->assertTrue( QAF_Activator::schema_ready() );
		QAF_Activator::maybe_upgrade(); // Nothing due: returns without touching the database.
	}
}
