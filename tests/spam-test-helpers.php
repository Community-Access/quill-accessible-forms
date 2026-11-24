<?php
/**
 * Shared set-up for the spam tests: the spam classes, WordPress stand-ins,
 * a memory store and a memory rate limiter. No test reaches Akismet,
 * OOPSpam or any other service: every provider that would is given a fake
 * transport that records what it was sent.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-honeypot.php';
require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-rate-limiter.php';
require_once QAF_PLUGIN_DIR . 'includes/spam/class-qaf-spam.php';
require_once __DIR__ . '/mocks/spam-mocks.php';

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

/**
 * Base class for the spam tests.
 */
abstract class QAF_Spam_Test_Case extends QAF_Unit_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['qaf_test_options'] = array();
		$GLOBALS['qaf_test_actions'] = array();
		$GLOBALS['qaf_test_caps']    = array();
		$GLOBALS['qaf_test_routes']  = array();
		QAF_Spam::set_guard( null );
	}

	/** Settings: the defaults with some values changed. */
	protected function settings( $over = array() ) {
		return new QAF_Spam_Settings( array_replace_recursive( QAF_Spam_Settings::defaults(), $over ) );
	}

	/** A submission to form 7 with a name, an email and a message. */
	protected function submission( $fields = array(), $params = array() ) {
		$s              = new QAF_Spam_Submission();
		$s->form_id     = 7;
		$s->fields      = array_merge( array( 'name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'message' => 'Hello, could you tell me when the next meeting is?' ), $fields );
		$s->field_types = array( 'name' => 'name', 'email' => 'email', 'message' => 'textarea' );
		$s->params      = $params;
		$s->ip          = '203.0.113.5';
		$s->user_agent  = 'Test browser';
		$s->now         = time();
		return $s;
	}

	/** Names of the hooks fired so far. */
	protected static function fired( $tag ) {
		return array_values( array_filter( $GLOBALS['qaf_test_actions'], function ( $a ) use ( $tag ) {
			return $a[0] === $tag;
		} ) );
	}
}
