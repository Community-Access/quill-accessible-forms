<?php
/**
 * The submit route's side of the hold rule: what the page is told for each
 * decision, what a held entry keeps, and how a submission is read from a
 * request and from a saved entry.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-entry.php';
require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-rest-api.php';

/**
 * Just enough of a form.
 */
class QAF_Stub_Form {
	public $id       = 7;
	public $settings = array( 'rate_limit_max' => 9 );

	public function get_fields() {
		return array(
			array( 'id' => 'who', 'type' => 'name' ),
			array( 'id' => 'mail', 'type' => 'email' ),
			array( 'id' => 'site', 'type' => 'url' ),
			array( 'id' => 'msg', 'type' => 'textarea' ),
		);
	}
}

class QAF_Spam_Submit_Test extends QAF_Spam_Test_Case {

	private static function call( $method, ...$args ) {
		$m = new ReflectionMethod( 'QAF_REST_API', $method );
		$m->setAccessible( true );
		return $m->invoke( new QAF_REST_API(), ...$args );
	}

	private static function decision( $action, $verdicts = array() ) {
		$d = new QAF_Spam_Decision( $action, new QAF_Spam_Result( $verdicts ) );
		if ( $verdicts ) {
			$d->lead = $d->result->lead();
		}
		return $d;
	}

	public function test_a_held_or_saved_entry_goes_on_to_be_saved() {
		$this->assertNull( self::call( 'spam_reply', self::decision( QAF_Spam_Decision::SAVE ) ) );
		$this->assertNull( self::call( 'spam_reply', self::decision( QAF_Spam_Decision::HOLD, array( QAF_Spam_Verdict::spam( 'honeypot', 'filled' ) ) ) ) );
	}

	public function test_the_flood_ceiling_is_a_plain_429() {
		$d          = self::decision( QAF_Spam_Decision::REFUSE );
		$d->message = 'Please try again tomorrow.';
		$e          = self::call( 'spam_reply', $d );
		$this->assertSame( 'qaf_daily_limit', $e->get_error_code() );
		$this->assertSame( 429, $e->get_error_data()['status'] );
		$this->assertSame( 'Please try again tomorrow.', $e->get_error_message() );
	}

	public function test_a_question_comes_back_without_saving() {
		$d            = self::decision( QAF_Spam_Decision::ASK );
		$d->message   = 'One more question before this is sent.';
		$d->challenge = array( 'id' => str_repeat( 'a', 32 ), 'html' => '<div class="qaf-challenge"></div>' );
		$reply        = self::call( 'spam_reply', $d );
		$this->assertFalse( $reply['success'] );
		$this->assertSame( $d->challenge, $reply['challenge'] );
		$this->assertArrayNotHasKey( 'entry_id', $reply );
	}

	public function test_a_held_entry_keeps_its_reason_and_status() {
		$entry = new QAF_Entry();
		self::call( 'apply_spam_decision', $entry, self::decision( QAF_Spam_Decision::HOLD, array( QAF_Spam_Verdict::unsure( 'links', '6 links (the limit is 3).', 6, 3 ) ) ) );
		$this->assertSame( 'spam', $entry->status );
		$this->assertSame( 'links', $entry->spam_check );
		$this->assertSame( '6 links (the limit is 3).', $entry->spam_reason );
		$this->assertSame( 6.0, $entry->spam_score );
		$this->assertTrue( $entry->get_spam_details()['held_at_submit'] );

		$clean = new QAF_Entry();
		self::call( 'apply_spam_decision', $clean, self::decision( QAF_Spam_Decision::SAVE ) );
		$this->assertSame( 'active', $clean->status );
		$this->assertNull( $clean->spam_reason );
		$this->assertNull( $clean->spam_details );
	}

	public function test_a_submission_is_read_from_the_request() {
		$_SERVER['HTTP_USER_AGENT'] = 'Test browser 2';
		$req                        = new WP_REST_Request( 'POST' );
		$req->set_param( 'fields', array( 'ignored' => 'here' ) );
		$req->set_param( 'qaf_website', '' );
		$req->set_param( 'qaf_visit', 'abc-123<script>' );
		$values = array( 'who' => 'Grace Hopper', 'mail' => 'grace@example.test', 'site' => 'https://example.test', 'msg' => 'Hello' );
		$s      = QAF_Spam_Submission::from_request( new QAF_Stub_Form(), $req, $values, '198.51.100.9' );
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$this->assertSame( 7, $s->form_id );
		$this->assertSame( array( 'rate_limit_max' => 9 ), $s->form_settings );
		$this->assertArrayNotHasKey( 'fields', $s->params, 'the answers are read sanitized, not from the raw request' );
		$this->assertSame( 'Grace Hopper', $s->author_name() );
		$this->assertSame( 'grace@example.test', $s->author_email() );
		$this->assertSame( 'https://example.test', $s->author_url() );
		$this->assertSame( 'abc-123script', $s->visit(), 'the visit id is kept plain' );
		$this->assertSame( 'Test browser 2', $s->user_agent );
		$this->assertSame( "Grace Hopper\ngrace@example.test\nhttps://example.test\nHello", $s->content() );
	}

	public function test_a_binding_changes_with_the_form_the_visit_or_the_browser() {
		$a = $this->submission( array(), array( 'qaf_visit' => 'v1' ) );
		$b = $this->submission( array(), array( 'qaf_visit' => 'v1' ) );
		$this->assertSame( $a->binding(), $b->binding() );
		$b->ip = '192.0.2.1';
		$this->assertSame( $a->binding(), $b->binding(), 'a phone changing address keeps its question' );
		$b->user_agent = 'Other browser';
		$this->assertNotSame( $a->binding(), $b->binding() );
		$c = $this->submission( array(), array( 'qaf_visit' => 'v2' ) );
		$this->assertNotSame( $a->binding(), $c->binding() );
	}

	public function test_a_submission_is_read_back_from_a_saved_entry() {
		$entry             = new QAF_Entry();
		$entry->id         = 5;
		$entry->form_id    = 7;
		$entry->ip_address = '203.0.113.5';
		$entry->user_agent = 'Saved browser';
		$entry->source_url = 'https://example.test/contact/';
		$entry->set_field_values( array( 'mail' => 'x@example.test', 'msg' => 'Hi' ) );
		$s = QAF_Spam_Submission::from_entry( $entry, new QAF_Stub_Form() );
		$this->assertSame( 5, $s->entry_id );
		$this->assertSame( 'x@example.test', $s->author_email() );
		$this->assertSame( 'https://example.test/contact/', $s->permalink );
		$this->assertSame( "x@example.test\nHi", $s->content() );
	}
}
