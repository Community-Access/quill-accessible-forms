<?php
/**
 * Text questions (13a.6, WP-R-039): made fresh, the answer kept on the
 * site, one-time and bound to the visit, matched loosely, never locking
 * anyone out, and marked up as a field like any other.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';

class QAF_Spam_Text_Challenge_Test extends QAF_Spam_Test_Case {

	/** @var QAF_Spam_Memory_Store */
	private $store;

	protected function setUp(): void {
		parent::setUp();
		$this->store = new QAF_Spam_Memory_Store();
	}

	private function challenge( $over = array(), $random = null ) {
		$store = $this->store;
		return new QAF_Text_Challenge(
			$this->settings( $over ),
			$store,
			new QAF_Spam_Failures( $store ),
			$random,
			function () use ( $store ) {
				return $store->now();
			}
		);
	}

	/** Random numbers that always pick the lowest. */
	private static function lowest() {
		return function ( $min, $max ) {
			return $min;
		};
	}

	/** Random numbers taken from a list in turn, then the lowest. */
	private static function sequence( $list ) {
		return function ( $min, $max ) use ( &$list ) {
			$n = $list ? array_shift( $list ) : $min;
			return max( $min, min( $max, $n ) );
		};
	}

	private function visit( $visit = 'visit-1', $form_id = 7 ) {
		$s          = $this->submission();
		$s->form_id = $form_id;
		$s->params  = array( 'qaf_visit' => $visit );
		return $s;
	}

	// Making questions.

	public function test_everyday_sense_names_two_things_and_wants_the_right_one() {
		$q = $this->challenge( array(), self::lowest() )->generate( 'sense' );
		$this->assertSame( 'Which is colder, soup or snow?', $q['question'] );
		$this->assertSame( array( 'snow' ), $q['answers'] );
		$this->assertNull( $q['choices'] );
	}

	public function test_an_instruction_about_the_question_itself() {
		$c = $this->challenge( array(), self::sequence( array( 0, 2 ) ) );
		$q = $c->generate( 'instruction' );
		$this->assertSame( 'Type the second word of this sentence.', $q['question'] );
		$this->assertSame( array( 'the' ), $q['answers'] );
		$this->assertTrue( QAF_Text_Challenge::matches( 'The', $q ), 'when the answer is "the", "the" counts' );

		$q = $this->challenge( array(), self::sequence( array( 1 ) ) )->generate( 'instruction' );
		$this->assertSame( 'Type the last word of this sentence.', $q['question'] );
		$this->assertTrue( QAF_Text_Challenge::matches( 'sentence', $q ) );

		$q = $this->challenge( array(), self::sequence( array( 2 ) ) )->generate( 'instruction' );
		$this->assertStringStartsWithText( 'Which word comes right after ', $q['question'] );
		$this->assertCount( 5, $q['options'] );
	}

	private function assertStringStartsWithText( $prefix, $text ) {
		$this->assertSame( 0, strpos( $text, $prefix ), $text );
	}

	public function test_choosing_from_a_short_list_also_offers_radio_buttons() {
		$q = $this->challenge( array(), self::lowest() )->generate( 'choice' );
		$this->assertMatchesRegularExpression( '/^Which of these is an animal: \w+, \w+, \w+\?$/', $q['question'] );
		$this->assertCount( 3, $q['choices'] );
		$this->assertContains( 'horse', $q['choices'] );
		$this->assertSame( array( 'horse' ), $q['answers'] );
	}

	public function test_ordering_is_never_shown_already_in_order() {
		$q = $this->challenge( array(), self::lowest() )->generate( 'order' );
		$this->assertTrue( $q['ordered'] );
		$this->assertSame( 'house town city', $q['answers'][0] );
		$this->assertStringContainsString( 'smallest first', $q['question'] );
		$this->assertStringNotContainsString( 'house, town, city', $q['question'] );
	}

	public function test_maths_is_off_by_default_and_reads_digits_or_words() {
		$this->assertNotContains( 'maths', $this->challenge()->kinds() );
		$c = $this->challenge( array( 'challenge_kinds' => array( 'maths' => true ) ), self::sequence( array( 7, 2 ) ) );
		$this->assertContains( 'maths', $c->kinds() );
		$q = $c->generate( 'maths' );
		$this->assertSame( 'What is seven plus two?', $q['question'] );
		$this->assertTrue( QAF_Text_Challenge::matches( '9', $q ) );
		$this->assertTrue( QAF_Text_Challenge::matches( 'Nine.', $q ) );
		$this->assertFalse( QAF_Text_Challenge::matches( '8', $q ) );
	}

	public function test_the_sites_own_questions_join_the_bank() {
		$c = $this->challenge( array( 'challenge_bank' => "What is the name of our radio station? | Community Radio | CR\nno answers here\n" ) );
		$this->assertContains( 'site', $c->kinds() );
		$this->assertCount( 1, $c->site_questions(), 'a line without an answer is left out' );
		$q = $c->generate( 'site' );
		$this->assertTrue( QAF_Text_Challenge::matches( 'community radio', $q ) );
		$this->assertTrue( QAF_Text_Challenge::matches( 'cr', $q ) );
	}

	public function test_questions_vary() {
		$c    = $this->challenge();
		$seen = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$seen[ $c->generate()['question'] ] = true;
		}
		$this->assertGreaterThan( 20, count( $seen ), 'made fresh each time' );
	}

	// Loose matching.

	public function test_answers_are_matched_loosely() {
		$sense = array( 'answers' => array( 'an elephant' ), 'options' => array( 'an elephant', 'a feather' ), 'ordered' => false );
		foreach ( array( 'an elephant', 'Elephant', '  ELEPHANT!! ', 'the elephant', 'elephnt', 'The elephant is heavier.' ) as $ok ) {
			$this->assertTrue( QAF_Text_Challenge::matches( $ok, $sense ), $ok );
		}
		foreach ( array( '', 'feather', 'elephant or feather', 'giraffe' ) as $no ) {
			$this->assertFalse( QAF_Text_Challenge::matches( $no, $sense ), $no );
		}
	}

	public function test_an_order_is_matched_however_it_is_written() {
		$order = array( 'answers' => array( 'house town city' ), 'options' => array( 'house', 'town', 'city' ), 'ordered' => true );
		foreach ( array( 'house, town, city', 'House town and city', 'house then town then city.', 'hose town city' ) as $ok ) {
			$this->assertTrue( QAF_Text_Challenge::matches( $ok, $order ), $ok );
		}
		foreach ( array( 'city, town, house', 'house city town', 'house town' ) as $no ) {
			$this->assertFalse( QAF_Text_Challenge::matches( $no, $order ), $no );
		}
	}

	public function test_a_short_word_must_be_exact_but_any_of_several_answers_counts() {
		$q = array( 'answers' => array( 'red', 'crimson' ), 'options' => array(), 'ordered' => false );
		$this->assertFalse( QAF_Text_Challenge::matches( 'rod', $q ), 'no slips in a three-letter word' );
		$this->assertTrue( QAF_Text_Challenge::matches( 'Crimsen', $q ) );
	}

	// The lifecycle.

	public function test_the_answer_never_reaches_the_page() {
		$c = $this->challenge( array(), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'Which is colder, soup or snow?', $q['question'] );
		$this->assertSame( array( 'id', 'kind', 'question', 'choices', 'attempt', 'error', 'send_anyway', 'html' ), array_keys( $q ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $q['id'] );
		$this->assertNull( $q['send_anyway'] );
		$stored = $this->store->get( 'ch:' . $q['id'] );
		$this->assertSame( array( 'snow' ), $stored['answers'], 'kept on the site' );
	}

	public function test_a_right_answer_passes_once() {
		$c = $this->challenge( array(), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'pass', $c->verify( $q['id'], 'Snow', $this->visit() ) );
		$this->assertSame( 'unknown', $c->verify( $q['id'], 'Snow', $this->visit() ), 'one-time' );
	}

	public function test_a_question_is_bound_to_its_form_and_visit() {
		$c = $this->challenge( array(), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'unknown', $c->verify( $q['id'], 'snow', $this->visit( 'another-visit' ) ) );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'unknown', $c->verify( $q['id'], 'snow', $this->visit( 'visit-1', 8 ) ) );
		$this->assertSame( 'unknown', $c->verify( 'not-an-id', 'snow', $this->visit() ) );
	}

	public function test_a_question_expires_after_fifteen_minutes() {
		$c = $this->challenge( array(), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->store->offset = QAF_Text_Challenge::TTL + 1;
		$this->assertSame( 'expired', $c->verify( $q['id'], 'snow', $this->visit() ) );
	}

	public function test_wrong_and_empty_answers() {
		$c = $this->challenge( array(), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'fail', $c->verify( $q['id'], 'soup', $this->visit() ) );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'empty', $c->verify( $q['id'], '   ', $this->visit() ) );
	}

	// The guard asks, saves, asks again, and offers Send Anyway.

	private function guard( $verdict, $over = array() ) {
		$s     = $this->settings( $over );
		$store = $this->store;
		$check = new QAF_Scripted_Check( $s, 'time', $verdict );
		return new QAF_Spam_Guard(
			new QAF_Spam_Registry( $s, array( $check ) ),
			$s,
			new QAF_Text_Challenge( $s, $store, new QAF_Spam_Failures( $store ), self::lowest(), function () use ( $store ) {
				return $store->now();
			} )
		);
	}

	private static function doubt() {
		return QAF_Spam_Verdict::unsure( 'time', 'Filled in 1 second (the shortest the site expects is 3).', 1, null, true );
	}

	private function answer( $guard, $id, $answer, $extra = array() ) {
		$s         = $this->visit();
		$s->params = array_merge( $s->params, array( 'qaf_challenge_id' => $id, 'qaf_challenge_answer' => $answer ), $extra );
		return $guard->decide( $s );
	}

	public function test_a_question_is_asked_only_when_the_quiet_checks_are_unsure() {
		$this->assertSame( QAF_Spam_Decision::SAVE, $this->guard( QAF_Spam_Verdict::pass( 'time' ) )->decide( $this->visit() )->action );
		$d = $this->guard( self::doubt() )->decide( $this->visit() );
		$this->assertSame( QAF_Spam_Decision::ASK, $d->action );
		$this->assertFalse( $d->saves(), 'nothing is saved yet, and nothing is lost: the answers stay on the page' );
		$this->assertSame( 'One more question before this is sent.', $d->message );
	}

	public function test_when_a_check_is_sure_no_question_is_asked() {
		$d = $this->guard( QAF_Spam_Verdict::spam( 'time', 'sure' ) )->decide( $this->visit() );
		$this->assertSame( QAF_Spam_Decision::HOLD, $d->action );
		$this->assertNull( $d->challenge );
	}

	public function test_a_right_answer_saves_the_entry() {
		$g = $this->guard( self::doubt() );
		$q = $g->decide( $this->visit() )->challenge;
		$d = $this->answer( $g, $q['id'], 'snow' );
		$this->assertSame( QAF_Spam_Decision::SAVE, $d->action );
		$this->assertSame( 'active', $d->status() );
		$details = json_decode( $d->storage()['spam_details'], true );
		$this->assertSame( array( 'Answered the question.' ), $details['notes'] );
		$this->assertCount( 1, $details['checks'], 'the doubt is still recorded' );
	}

	public function test_a_wrong_answer_asks_again_and_costs_the_address() {
		$g = $this->guard( self::doubt() );
		$q = $g->decide( $this->visit() )->challenge;
		$d = $this->answer( $g, $q['id'], 'soup' );
		$this->assertSame( QAF_Spam_Decision::ASK, $d->action );
		$this->assertStringContainsString( 'did not match', $d->challenge['error'] );
		$this->assertSame( 2, $d->challenge['attempt'] );
		$this->assertNotSame( $q['id'], $d->challenge['id'] );
		$this->assertSame( 1, ( new QAF_Spam_Failures( $this->store ) )->count( '203.0.113.5' ), 'the next proof of work is longer, the rate limit tighter' );
	}

	public function test_an_expired_question_is_replaced_without_counting_against_anyone() {
		$g = $this->guard( self::doubt() );
		$q = $g->decide( $this->visit() )->challenge;
		$this->store->offset = QAF_Text_Challenge::TTL + 10;
		$d = $this->answer( $g, $q['id'], 'snow' );
		$this->assertSame( QAF_Spam_Decision::ASK, $d->action );
		$this->assertStringContainsString( 'new one', $d->challenge['error'] );
		$this->assertSame( 0, ( new QAF_Spam_Failures( $this->store ) )->count( '203.0.113.5' ) );
	}

	public function test_after_three_tries_send_anyway_holds_the_entry() {
		$g = $this->guard( self::doubt() );
		$q = $g->decide( $this->visit() )->challenge;
		for ( $i = 0; $i < 3; $i++ ) {
			$d = $this->answer( $g, $q['id'], 'wrong' );
			$q = $d->challenge;
		}
		$this->assertNotNull( $q['send_anyway'], 'no lockout' );
		$this->assertStringContainsString( 'Send Anyway', $q['html'] );
		$this->assertStringContainsString( 'a person will read it first', $d->message );

		$s         = $this->visit();
		$s->params = array_merge( $s->params, array( 'qaf_send_anyway' => $q['send_anyway'] ) );
		$held      = $g->decide( $s );
		$this->assertSame( QAF_Spam_Decision::HOLD, $held->action );
		$this->assertSame( 'Sent anyway after 3 questions.', $held->storage()['spam_reason'] );
		$this->assertSame( 'challenge', $held->storage()['spam_check'] );

		$again = $g->decide( $s );
		$this->assertSame( QAF_Spam_Decision::ASK, $again->action, 'Send Anyway works once' );
	}

	public function test_always_ask_and_never_ask() {
		$d = $this->guard( QAF_Spam_Verdict::pass( 'time' ), array( 'challenge_mode' => 'always' ) )->decide( $this->visit() );
		$this->assertSame( QAF_Spam_Decision::ASK, $d->action );
		$d = $this->guard( self::doubt(), array( 'challenge_mode' => 'never' ) )->decide( $this->visit() );
		$this->assertSame( QAF_Spam_Decision::HOLD, $d->action, 'never ask: a doubt is held' );
		$d = $this->guard( self::doubt(), array( 'form_modes' => array( 7 => 'never' ) ) )->decide( $this->visit() );
		$this->assertSame( QAF_Spam_Decision::HOLD, $d->action, 'per form' );
		$s                = $this->visit();
		$s->form_settings = array( 'spam_challenge' => 'always' );
		$this->assertSame( QAF_Spam_Decision::ASK, $this->guard( QAF_Spam_Verdict::pass( 'time' ) )->decide( $s )->action, 'in the form\'s own settings' );
	}

	public function test_an_api_caller_is_never_asked() {
		$this->assertSame( QAF_Spam_Decision::HOLD, $this->guard( self::doubt() )->decide( $this->visit(), false )->action );
	}

	public function test_this_weeks_numbers_are_counted() {
		$g = $this->guard( self::doubt() );
		$q = $g->decide( $this->visit() )->challenge;
		$q = $this->answer( $g, $q['id'], 'soup' )->challenge;
		$this->answer( $g, $q['id'], 'snow' );
		$stats = $g->challenge()->stats();
		$this->assertSame( 2, $stats['asked'] );
		$this->assertSame( 1, $stats['failed'] );
		$this->assertSame( 1, $stats['passed'] );
	}

	// The markup.

	public function test_a_question_is_a_field_like_any_other() {
		$c    = $this->challenge( array(), self::lowest() );
		$q    = $c->issue( $this->visit(), 'That answer did not match.' );
		$html = $q['html'];
		$p    = 'qaf-challenge-' . substr( $q['id'], 0, 12 );
		$this->assertStringContainsString( '<label class="qaf-label" for="' . $p . '-answer">Your answer</label>', $html );
		$this->assertStringContainsString( 'aria-describedby="' . $p . '-question ' . $p . '-error"', $html );
		$this->assertStringContainsString( 'id="' . $p . '-error" aria-live="polite"', $html );
		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertStringContainsString( 'name="qaf_challenge_id" value="' . $q['id'] . '"', $html );
		$this->assertStringContainsString( 'there is no time limit', $html );
		$this->assertStringNotContainsString( 'snow"', $html, 'no answer in the markup' );
		$this->assertDoesNotMatchRegularExpression( '/\bbots?\b/i', $html, 'no hidden "if you are a bot" text' );
		$this->assertStringNotContainsString( 'aria-hidden', $html );
		$this->assertStringNotContainsString( 'maxlength', $html );
	}

	public function test_a_short_list_is_radio_buttons_in_a_fieldset() {
		$c    = $this->challenge( array( 'challenge_kinds' => array( 'sense' => false, 'instruction' => false, 'order' => false ) ), self::lowest() );
		$q    = $c->issue( $this->visit() );
		$html = $q['html'];
		$this->assertSame( 'choice', $q['kind'] );
		$this->assertStringContainsString( '<fieldset class="qaf-challenge-field"', $html );
		$this->assertStringContainsString( '<legend class="qaf-challenge-question"', $html );
		$this->assertSame( 3, substr_count( $html, 'type="radio"' ) );
		$this->assertSame( 3, substr_count( $html, '<label for=' ) );
	}

	public function test_the_question_is_escaped() {
		$c = $this->challenge( array( 'challenge_kinds' => array( 'sense' => false, 'instruction' => false, 'choice' => false, 'order' => false ), 'challenge_bank' => 'Type <b>yes</b> & go | yes' ), self::lowest() );
		$q = $c->issue( $this->visit() );
		$this->assertSame( 'site', $q['kind'] );
		$this->assertStringNotContainsString( '<b>', $q['html'] );
	}
}
