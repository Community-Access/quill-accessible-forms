<?php
/**
 * The quiet checks: honeypot, time to fill, links, code in answers, the
 * form token, the rate limit and the CAPTCHA a form already has.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';

class QAF_Spam_Quiet_Checks_Test extends QAF_Spam_Test_Case {

	public function test_a_filled_honeypot_is_spam_and_says_which_field() {
		$check = new QAF_Spam_Honeypot_Check( $this->settings() );
		$this->assertSame( 'pass', $check->check( $this->submission() )->verdict );
		$v = $check->check( $this->submission( array(), array( 'qaf_website' => 'http://spam.example' ) ) );
		$this->assertSame( 'spam', $v->verdict );
		$this->assertStringContainsString( 'qaf_website', $v->reason );
		$this->assertSame( 'pass', $check->check( $this->submission( array(), array( 'qaf_website' => '   ' ) ) )->verdict );
	}

	public function test_too_fast_is_a_behavioural_doubt_with_the_seconds() {
		$check  = new QAF_Spam_Time_Check( $this->settings() );
		$s      = $this->submission();
		$loaded = $s->now - 1;
		$s->params = array( '_qaf_form_loaded' => $loaded, '_qaf_form_sig' => QAF_Spam_Settings::sign_time( 7, $loaded ) );
		$v = $check->check( $s );
		$this->assertSame( 'unsure', $v->verdict, 'only ever a doubt' );
		$this->assertTrue( $v->behavioural );
		$this->assertSame( 'Filled in 1 second (the shortest the site expects is 3).', $v->reason );

		$s->params = array( '_qaf_form_loaded' => $s->now - 40, '_qaf_form_sig' => QAF_Spam_Settings::sign_time( 7, $s->now - 40 ) );
		$this->assertSame( 'pass', $check->check( $s )->verdict );
	}

	public function test_a_moved_start_time_is_noticed() {
		$check     = new QAF_Spam_Time_Check( $this->settings() );
		$s         = $this->submission();
		$real      = $s->now - 1;
		$s->params = array( '_qaf_form_loaded' => $s->now - 600, '_qaf_form_sig' => QAF_Spam_Settings::sign_time( 7, $real ) );
		$v         = $check->check( $s );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertStringContainsString( 'changed', $v->reason );
	}

	public function test_no_start_time_is_no_opinion() {
		$this->assertNull( ( new QAF_Spam_Time_Check( $this->settings() ) )->check( $this->submission() ) );
	}

	public function test_the_shortest_time_follows_the_setting() {
		$check     = new QAF_Spam_Time_Check( $this->settings( array( 'min_seconds' => 10 ) ) );
		$s         = $this->submission();
		$s->params = array( '_qaf_form_loaded' => $s->now - 6 );
		$this->assertSame( 'Filled in 6 seconds (the shortest the site expects is 10).', $check->check( $s )->reason );
	}

	public function test_links_over_the_limit_are_a_doubt_with_the_count() {
		$check = new QAF_Spam_Links_Check( $this->settings() );
		$three = 'see https://a.example, http://b.example and www.c.example';
		$this->assertSame( 3, QAF_Spam_Links_Check::count_links( $three ) );
		$this->assertSame( 'pass', $check->check( $this->submission( array( 'message' => $three ) ) )->verdict );
		$six = $three . ' [url=http://d.example]d[/url] <a href="https://www.e.example">e</a> and http://f.example';
		$v   = $check->check( $this->submission( array( 'message' => $six ) ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertSame( '6 links (the limit is 3).', $v->reason );
		$this->assertSame( 6.0, $v->score );
		$this->assertSame( 3.0, $v->score_max );
	}

	public function test_links_are_counted_across_every_answer() {
		$check = new QAF_Spam_Links_Check( $this->settings( array( 'max_links' => 1 ) ) );
		$v     = $check->check( $this->submission( array( 'name' => 'http://a.example', 'message' => array( 'http://b.example' ) ) ) );
		$this->assertSame( '2 links (the limit is 1).', $v->reason );
	}

	public function test_code_in_answers_is_a_doubt() {
		$check = new QAF_Spam_Patterns_Check( $this->settings() );
		$this->assertSame( 'pass', $check->check( $this->submission() )->verdict );
		$v = $check->check( $this->submission( array( 'message' => 'hi <script>alert(1)</script>' ) ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertSame( 'The answers contain a script tag.', $v->reason );
		$this->assertSame( 'pass', $check->check( $this->submission( array( 'message' => 'I am online = most evenings' ) ) )->verdict, 'plain words are not code' );
	}

	public function test_a_stale_form_token_is_a_doubt_not_a_refusal() {
		$check = new QAF_Spam_Token_Check( $this->settings() );
		$this->assertNull( $check->check( $this->submission() ), 'no token: an app or an import' );
		$this->assertSame( 'pass', $check->check( $this->submission( array(), array( '_qaf_token' => 'good-qaf_submit_form_7' ) ) )->verdict );
		$v = $check->check( $this->submission( array(), array( '_qaf_token' => 'stale' ) ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertStringContainsString( 'expired', $v->reason );
	}

	private function rate( $limiter, $store = null ) {
		$store = $store ?: new QAF_Spam_Memory_Store();
		return new QAF_Spam_Rate_Check( $this->settings(), $limiter, new QAF_Spam_Failures( $store ) );
	}

	public function test_over_the_rate_limit_is_held_with_the_count() {
		$limiter = new QAF_Memory_Rate_Limiter();
		$check   = $this->rate( $limiter );
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertSame( 'pass', $check->check( $this->submission() )->verdict, 'send ' . $i );
		}
		$v = $check->check( $this->submission() );
		$this->assertSame( 'spam', $v->verdict );
		$this->assertSame( '6 sends from this address in 60 minutes (the limit is 5).', $v->reason );
	}

	public function test_the_daily_ceiling_is_the_one_refusal() {
		$limiter = new QAF_Memory_Rate_Limiter();
		for ( $i = 0; $i < 100; $i++ ) {
			$limiter->events[] = array( 'ip' => '203.0.113.5', 'form_id' => 900 + $i, 'time' => time() - 100 );
		}
		$v = $this->rate( $limiter )->check( $this->submission() );
		$this->assertSame( 'refuse', $v->verdict );
		$this->assertStringContainsString( 'try again tomorrow', $v->message );
	}

	public function test_an_answer_to_a_question_is_not_counted_again() {
		$limiter = new QAF_Memory_Rate_Limiter();
		$check   = $this->rate( $limiter );
		$s       = $this->submission();
		$check->check( $s );
		$s->is_answer = true;
		$check->check( $s );
		$this->assertCount( 1, $limiter->events );
	}

	public function test_wrong_answers_tighten_the_limit() {
		$store    = new QAF_Spam_Memory_Store();
		$failures = new QAF_Spam_Failures( $store );
		$failures->add( '203.0.113.5' );
		$failures->add( '203.0.113.5' );
		$check = $this->rate( new QAF_Memory_Rate_Limiter(), $store );
		$check->check( $this->submission() );
		$check->check( $this->submission() );
		$check->check( $this->submission() );
		$v = $check->check( $this->submission() );
		$this->assertSame( 'spam', $v->verdict );
		$this->assertStringContainsString( '(the limit is 3)', $v->reason );
	}

	public function test_an_allowed_address_is_never_limited() {
		$limiter          = new QAF_Memory_Rate_Limiter();
		$limiter->allowed = array( '203.0.113.5' );
		$this->assertNull( $this->rate( $limiter )->check( $this->submission() ) );
	}

	private function captcha_says( $answer, $enabled = true ) {
		$s                = $this->submission();
		$s->form_settings = array( 'captcha_enabled' => $enabled );
		$s->request       = new WP_REST_Request( 'POST' );
		return ( new QAF_Spam_Captcha_Check( $this->settings(), new QAF_Fake_Captcha( $answer ) ) )->check( $s );
	}

	public function test_a_low_recaptcha_v3_score_only_holds() {
		$v = $this->captcha_says( array( 'provider' => 'recaptcha_v3', 'outcome' => 'low_score', 'score' => 0.2, 'threshold' => 0.5, 'message' => 'x' ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertTrue( $v->behavioural );
		$this->assertSame( 'reCAPTCHA v3 score 0.2 (the threshold is 0.5).', $v->reason );
		$this->assertSame( 0.2, $v->score );
	}

	public function test_a_turnstile_failure_only_holds() {
		$v = $this->captcha_says( array( 'provider' => 'turnstile', 'outcome' => 'failed', 'message' => 'x' ) );
		$this->assertSame( 'unsure', $v->verdict );
		$v = $this->captcha_says( array( 'provider' => 'turnstile', 'outcome' => 'missing', 'message' => 'x' ) );
		$this->assertSame( 'unsure', $v->verdict, 'a managed check that never ran is a doubt, not a refusal' );
	}

	public function test_an_unfinished_visible_puzzle_is_asked_for() {
		$v = $this->captcha_says( array( 'provider' => 'recaptcha_v2', 'outcome' => 'missing', 'message' => 'Please complete the CAPTCHA.' ) );
		$this->assertSame( 'incomplete', $v->verdict );
		$this->assertSame( 'Please complete the CAPTCHA.', $v->message );
	}

	public function test_an_unreachable_captcha_service_is_not_the_visitors_fault() {
		$this->assertNull( $this->captcha_says( array( 'provider' => 'hcaptcha', 'outcome' => 'unreachable', 'message' => 'x' ) ) );
	}

	public function test_no_captcha_on_the_form_means_no_opinion() {
		$this->assertNull( $this->captcha_says( array( 'provider' => 'hcaptcha', 'outcome' => 'failed', 'message' => 'x' ), false ) );
	}

	public function test_the_site_registry_runs_the_quiet_checks_in_order() {
		$ids = array_keys( QAF_Spam::registry( $this->settings(), new QAF_Spam_Memory_Store() )->providers() );
		$this->assertSame( array( 'honeypot', 'time', 'links', 'patterns', 'token', 'rate_limit', 'captcha' ), array_slice( $ids, 0, 7 ) );
	}
}
