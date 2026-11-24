<?php
/**
 * The providers of WP-R-028: the site's blocklists, Akismet, OOPSpam,
 * CleanTalk and Shield (detected only), ALTCHA proof of work, and the
 * registry's Spam and Not Spam reporting.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';

class QAF_Spam_Providers_Test extends QAF_Spam_Test_Case {

	// The site's Discussion lists.

	private function blocklist( $disallowed = '', $moderation = '', $over = array() ) {
		$options = array( 'disallowed_keys' => $disallowed, 'moderation_keys' => $moderation );
		return new QAF_Spam_Blocklist_Check( $this->settings( $over ), function ( $name, $default = '' ) use ( $options ) {
			return isset( $options[ $name ] ) ? $options[ $name ] : $default;
		} );
	}

	public function test_a_disallowed_word_is_spam_and_names_the_word() {
		$v = $this->blocklist( "viagra\n  casino  \n" )->check( $this->submission( array( 'message' => 'Best CASINO bonus' ) ) );
		$this->assertSame( 'spam', $v->verdict );
		$this->assertSame( 'Matches the site\'s disallowed words: "casino".', $v->reason );
	}

	public function test_a_moderation_word_is_a_doubt() {
		$v = $this->blocklist( 'casino', "loan\n" )->check( $this->submission( array( 'message' => 'About my loan application' ) ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertStringContainsString( '"loan"', $v->reason );
	}

	public function test_the_lists_match_the_sender_and_address_too() {
		$this->assertSame( 'spam', $this->blocklist( 'example.test' )->check( $this->submission() )->verdict, 'the email matches' );
		$this->assertSame( 'spam', $this->blocklist( '203.0.113.' )->check( $this->submission() )->verdict, 'the address matches' );
		$this->assertSame( 'pass', $this->blocklist( "\n\n" )->check( $this->submission() )->verdict, 'blank lines match nothing' );
	}

	public function test_each_list_can_be_left_out() {
		$check = $this->blocklist( 'meeting', 'meeting', array( 'use_disallowed' => false, 'use_moderation' => false ) );
		$this->assertSame( 'pass', $check->check( $this->submission() )->verdict );
	}

	public function test_a_site_saved_before_wordpress_5_5_keeps_its_list() {
		$check = new QAF_Spam_Blocklist_Check( $this->settings(), function ( $name, $default = '' ) {
			return 'blacklist_keys' === $name ? 'meeting' : '';
		} );
		$this->assertSame( 'spam', $check->check( $this->submission() )->verdict );
	}

	// Akismet.

	private function akismet( &$calls, $body, $headers = array() ) {
		$calls = array();
		return new QAF_Spam_Akismet_Check( $this->settings( array( 'providers' => array( 'akismet' => true ) ) ), function ( $query, $path ) use ( &$calls, $body, $headers ) {
			parse_str( $query, $params );
			$calls[] = array( 'path' => $path, 'params' => $params );
			return array( $headers, $body );
		} );
	}

	public function test_akismet_is_asked_as_a_contact_form() {
		$check = $this->akismet( $calls, 'false' );
		$this->assertSame( 'pass', $check->check( $this->submission() )->verdict );
		$this->assertSame( 'comment-check', $calls[0]['path'] );
		$p = $calls[0]['params'];
		$this->assertSame( 'contact-form', $p['comment_type'] );
		$this->assertSame( 'Ada Lovelace', $p['comment_author'] );
		$this->assertSame( 'ada@example.test', $p['comment_author_email'] );
		$this->assertSame( '203.0.113.5', $p['user_ip'] );
		$this->assertStringContainsString( 'next meeting', $p['comment_content'] );
	}

	public function test_akismet_spam_and_blatant_spam() {
		$this->assertSame( 'Akismet says this is spam.', $this->akismet( $calls, 'true' )->check( $this->submission() )->reason );
		$v = $this->akismet( $calls, 'true', array( 'x-akismet-pro-tip' => 'discard' ) )->check( $this->submission() );
		$this->assertSame( 'spam', $v->verdict, 'even blatant spam is held, never discarded' );
		$this->assertSame( 'Akismet: blatant spam.', $v->reason );
	}

	public function test_an_akismet_error_is_no_opinion() {
		$this->assertNull( $this->akismet( $calls, 'invalid' )->check( $this->submission() ) );
		$this->assertNull( $this->akismet( $calls, null )->check( $this->submission() ) );
	}

	public function test_spam_and_not_spam_are_reported_to_akismet() {
		$check = $this->akismet( $calls, '' );
		$check->mark_spam( $this->submission() );
		$check->mark_ham( $this->submission() );
		$this->assertSame( array( 'submit-spam', 'submit-ham' ), array_column( $calls, 'path' ) );
		$this->assertSame( 'contact-form', $calls[1]['params']['comment_type'] );
	}

	public function test_without_the_akismet_plugin_it_is_unavailable() {
		$a = ( new QAF_Spam_Akismet_Check( $this->settings() ) )->availability();
		$this->assertFalse( $a['available'] );
		$this->assertTrue( ( new QAF_Spam_Akismet_Check( $this->settings() ) )->sends_content() );
	}

	// OOPSpam.

	private function oopspam( &$calls, $status, $body, $over = array() ) {
		$calls = array();
		$s     = $this->settings( array_replace_recursive( array( 'providers' => array( 'oopspam' => true ), 'oopspam_api_key' => 'test-key-123' ), $over ) );
		return new QAF_Spam_OOPSpam_Check( $s, function ( $url, $args ) use ( &$calls, $status, $body ) {
			$calls[] = array( 'url' => $url, 'args' => $args, 'body' => json_decode( $args['body'], true ) );
			return array( 'response' => array( 'code' => $status ), 'body' => is_string( $body ) ? $body : json_encode( $body ) );
		} );
	}

	public function test_an_oopspam_score_at_the_threshold_holds() {
		$v = $this->oopspam( $calls, 200, array( 'Score' => 5, 'Details' => array() ) )->check( $this->submission() );
		$this->assertSame( 'spam', $v->verdict );
		$this->assertSame( 'OOPSpam score 5 of 6 (the limit is 3).', $v->reason );
		$this->assertSame( 5.0, $v->score );
		$this->assertSame( 6.0, $v->score_max );
		$this->assertSame( QAF_Spam_OOPSpam_Check::API, $calls[0]['url'] );
		$this->assertSame( 'test-key-123', $calls[0]['args']['headers']['X-Api-Key'] );
		$this->assertSame( array( 'senderIP', 'email', 'content' ), array_keys( $calls[0]['body'] ) );
	}

	public function test_an_oopspam_score_under_the_threshold_passes_with_its_score() {
		$v = $this->oopspam( $calls, 200, array( 'Score' => 2 ) )->check( $this->submission() );
		$this->assertSame( 'pass', $v->verdict );
		$this->assertSame( 2.0, $v->score );
		$v = $this->oopspam( $calls, 200, array( 'Score' => 2 ), array( 'oopspam_threshold' => 2 ) )->check( $this->submission() );
		$this->assertSame( 'spam', $v->verdict, 'the threshold is the site\'s' );
	}

	public function test_an_oopspam_error_is_no_opinion() {
		$this->assertNull( $this->oopspam( $calls, 429, array( 'message' => 'limit' ) )->check( $this->submission() ) );
		$this->assertNull( $this->oopspam( $calls, 200, 'not json' )->check( $this->submission() ) );
	}

	public function test_spam_and_not_spam_are_reported_to_oopspam() {
		$check = $this->oopspam( $calls, 200, '{}' );
		$check->mark_spam( $this->submission() );
		$check->mark_ham( $this->submission() );
		$this->assertSame( QAF_Spam_OOPSpam_Check::REPORT, $calls[0]['url'] );
		$this->assertTrue( $calls[0]['body']['shouldBeSpam'] );
		$this->assertFalse( $calls[1]['body']['shouldBeSpam'] );
	}

	public function test_oopspam_without_a_key_is_unavailable() {
		$this->assertFalse( ( new QAF_Spam_OOPSpam_Check( $this->settings() ) )->availability()['available'] );
	}

	// CleanTalk and Shield: detected, never asked until verified.

	public function test_cleantalk_and_shield_are_detected_and_hold_nothing() {
		$s = $this->settings( array( 'providers' => array( 'cleantalk' => true, 'shield' => true ) ) );
		foreach ( array( new QAF_Spam_CleanTalk_Check( $s ), new QAF_Spam_Shield_Check( $s ) ) as $check ) {
			$this->assertFalse( $check->availability()['available'], $check->id() . ' is not installed' );
		}
		update_option( 'active_plugins', array( 'cleantalk-spam-protect/cleantalk.php', 'wp-simple-firewall/icwp-wpsf.php' ) );
		foreach ( array( new QAF_Spam_CleanTalk_Check( $s ), new QAF_Spam_Shield_Check( $s ) ) as $check ) {
			$a = $check->availability();
			$this->assertTrue( $a['available'] );
			$this->assertStringContainsString( 'being confirmed', $a['note'] );
			$this->assertNull( $check->check( $this->submission( array( 'message' => 'casino casino' ) ) ) );
		}
		$this->assertTrue( ( new QAF_Spam_CleanTalk_Check( $s ) )->sends_content() );
		$this->assertFalse( ( new QAF_Spam_Shield_Check( $s ) )->sends_content() );
	}

	// ALTCHA proof of work.

	public function test_a_solved_challenge_verifies() {
		$a = new QAF_Altcha( 'test-key' );
		$c = $a->create( 1000, null, 412 );
		$this->assertSame( 'SHA-256', $c['algorithm'] );
		$this->assertSame( 1000, $c['maxnumber'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{24}\?expires=\d+&$/', $c['salt'] );
		$this->assertSame( hash( 'sha256', $c['salt'] . '412' ), $c['challenge'] );
		$this->assertTrue( $a->verify( self::solution( $c, 412 ) )['ok'] );
	}

	public function test_a_wrong_forged_or_late_solution_fails() {
		$a = new QAF_Altcha( 'test-key' );
		$c = $a->create( 1000, null, 412 );
		$this->assertSame( 'wrong', $a->verify( self::solution( $c, 411 ) )['reason'] );
		$this->assertSame( 'forged', ( new QAF_Altcha( 'another-key' ) )->verify( self::solution( $c, 412 ) )['reason'] );
		$this->assertSame( 'expired', $a->verify( self::solution( $c, 412 ), time() + QAF_Altcha::TTL + 5 )['reason'] );
		$this->assertSame( 'malformed', $a->verify( 'not base64 json' )['reason'] );
	}

	public function test_digits_cannot_be_moved_into_the_salt_to_stretch_the_expiry() {
		$a       = new QAF_Altcha( 'test-key' );
		$c       = $a->create( 100, null, 52 );
		$spliced = $c;
		// Same characters, split differently: salt ends "...&5", number 2.
		$spliced['salt'] = $c['salt'] . '5';
		$this->assertSame( 'malformed', $a->verify( self::solution( $spliced, 2 ) )['reason'] );
	}

	public function test_the_provider_needs_a_fresh_solution_each_time() {
		$store = new QAF_Spam_Memory_Store();
		$a     = new QAF_Altcha( 'test-key' );
		$check = new QAF_Spam_Altcha_Check( $this->settings( array( 'providers' => array( 'altcha' => true ) ) ), $store, new QAF_Spam_Failures( $store ), $a );
		$this->assertSame( 'unsure', $check->check( $this->submission() )->verdict, 'no solution: a doubt, not a refusal' );
		$c       = $a->create( 50, null, 7 );
		$payload = self::solution( $c, 7 );
		$this->assertSame( 'pass', $check->check( $this->submission( array(), array( 'qaf_pow' => $payload ) ) )->verdict );
		$v = $check->check( $this->submission( array(), array( 'qaf_pow' => $payload ) ) );
		$this->assertSame( 'unsure', $v->verdict );
		$this->assertStringContainsString( 'already been used', $v->reason );
	}

	public function test_each_wrong_answer_doubles_the_work() {
		$store    = new QAF_Spam_Memory_Store();
		$failures = new QAF_Spam_Failures( $store );
		$check    = new QAF_Spam_Altcha_Check( $this->settings( array( 'altcha_max_number' => 1000 ) ), $store, $failures, new QAF_Altcha( 'k' ) );
		$this->assertSame( 1000, $check->challenge_for( '203.0.113.5' )['maxnumber'] );
		$failures->add( '203.0.113.5' );
		$failures->add( '203.0.113.5' );
		$this->assertSame( 4000, $check->challenge_for( '203.0.113.5' )['maxnumber'] );
		for ( $i = 0; $i < 10; $i++ ) {
			$failures->add( '203.0.113.5' );
		}
		$this->assertSame( 16000, $check->challenge_for( '203.0.113.5' )['maxnumber'], 'at most sixteen times' );
		$this->assertSame( 1000, $check->challenge_for( '198.51.100.9' )['maxnumber'], 'another address is unaffected' );
	}

	public function test_the_pow_route_is_public_and_off_until_turned_on() {
		( new QAF_Spam_REST() )->register_routes();
		$this->assertSame( '__return_true', $GLOBALS['qaf_test_routes']['qaf/v1/spam/pow']['permission_callback'] );
		$off = ( new QAF_Spam_REST( $this->settings(), new QAF_Spam_Memory_Store() ) )->pow( new WP_REST_Request( 'GET' ) );
		$this->assertSame( 'qaf_pow_off', $off->get_error_code() );
		$GLOBALS['qaf_test_options']['qaf_spam_secret'] = str_repeat( 'a', 64 );
		$on = ( new QAF_Spam_REST( $this->settings( array( 'providers' => array( 'altcha' => true ) ) ), new QAF_Spam_Memory_Store() ) )->pow( new WP_REST_Request( 'GET' ) );
		$this->assertSame( array( 'algorithm', 'challenge', 'maxnumber', 'salt', 'signature' ), array_keys( $on ) );
	}

	private static function solution( $c, $number ) {
		return base64_encode( json_encode( array(
			'algorithm' => $c['algorithm'],
			'challenge' => $c['challenge'],
			'number'    => $number,
			'salt'      => $c['salt'],
			'signature' => $c['signature'],
		) ) );
	}

	// The registry: filters, marks and states.

	public function test_a_verdict_added_as_an_array_is_read() {
		$r = new QAF_Spam_Result( array( array( 'provider' => 'other', 'verdict' => 'spam', 'reason' => 'Other plugin says so.', 'score' => 9 ) ) );
		$this->assertTrue( $r->is_spam() );
		$this->assertSame( 'Other plugin says so.', $r->lead()->reason );
	}

	public function test_spam_and_not_spam_tell_each_running_provider_and_fire_the_action() {
		$s       = $this->settings( array( 'providers' => array( 'akismet' => true ) ) );
		$calls   = array();
		$akismet = new QAF_Spam_Akismet_Check( $s, function ( $q, $path ) use ( &$calls ) {
			$calls[] = $path;
			return array( array(), '' );
		} );
		$off      = new QAF_Spam_OOPSpam_Check( $s, function () {
			throw new RuntimeException( 'not turned on, so never called' );
		} );
		$registry = new QAF_Spam_Registry( $s, array( new QAF_Spam_Honeypot_Check( $s ), $akismet, $off ) );
		$sub           = $this->submission();
		$sub->entry_id = 42;
		$told          = $registry->mark( $sub, 'ham' );
		$this->assertSame( array( 'honeypot', 'akismet' ), $told );
		$this->assertSame( array( 'submit-ham' ), $calls );
		$fired = self::fired( 'qaf_entry_marked' );
		$this->assertCount( 1, $fired );
		$this->assertSame( 42, $fired[0][1][0] );
		$this->assertSame( 'ham', $fired[0][1][1] );
	}

	public function test_states_say_what_each_provider_is_doing() {
		$states = QAF_Spam::registry( $this->settings(), new QAF_Spam_Memory_Store() )->states();
		$by_id  = array_column( $states, null, 'id' );
		foreach ( array( 'honeypot', 'time', 'links', 'patterns', 'token', 'rate_limit', 'captcha', 'altcha', 'blocklist', 'shield', 'akismet', 'oopspam', 'cleantalk' ) as $id ) {
			$this->assertArrayHasKey( $id, $by_id );
		}
		$this->assertTrue( $by_id['honeypot']['runs'] );
		$this->assertFalse( $by_id['akismet']['enabled'] );
		$this->assertTrue( $by_id['akismet']['sends_content'] );
		$this->assertFalse( $by_id['altcha']['enabled'] );
	}

	public function test_the_privacy_text_names_every_service_that_is_sent_content() {
		$text = QAF_Spam::privacy_text();
		foreach ( array( 'Akismet', 'OOPSpam', 'CleanTalk', 'kept for review, never thrown away' ) as $part ) {
			$this->assertStringContainsString( $part, $text );
		}
	}
}
