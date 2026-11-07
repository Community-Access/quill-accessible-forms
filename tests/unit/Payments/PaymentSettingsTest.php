<?php
/**
 * Contract, Readiness route: booleans only, never a secret; "configured"
 * means verified for the stated environment, "webhook_ready" means the
 * endpoint and secret are set and verified; "Do not report readiness merely
 * because a text field is nonempty."
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Settings_Test extends QAF_Payment_Test_Case {

	public function test_a_new_site_is_in_test_mode_and_nothing_is_ready() {
		$this->assertSame( array(
			'contract_version' => 1,
			'test_mode'        => true,
			'providers'        => array(
				'stripe' => array( 'configured' => false, 'webhook_ready' => false ),
				'paypal' => array( 'configured' => false, 'webhook_ready' => false ),
			),
		), QAF_Payment_Settings::readiness() );
	}

	public function test_saved_keys_are_not_readiness_until_checked() {
		QAF_Payment_Settings::set_public( 'stripe', 'test', 'publishable_key', self::STRIPE_PK );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'secret_key', self::STRIPE_SK );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'webhook_secret', self::STRIPE_WH );
		$this->assertFalse( QAF_Payment_Settings::readiness()['providers']['stripe']['configured'] );
		QAF_Payment_Settings::record_check( 'stripe', 'test', 'credentials', true, 'ok' );
		$this->assertTrue( QAF_Payment_Settings::readiness()['providers']['stripe']['configured'] );
		$this->assertFalse( QAF_Payment_Settings::readiness()['providers']['stripe']['webhook_ready'] );
	}

	public function test_changing_a_key_lapses_its_check() {
		$this->make_ready( 'stripe' );
		$this->assertTrue( QAF_Payment_Settings::ready( 'stripe', 'test' ) );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'secret_key', 'sk_test_another0000000000000000' );
		$this->assertFalse( QAF_Payment_Settings::configured( 'stripe', 'test' ) );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'webhook_secret', 'whsec_another000000000000000000' );
		$this->assertFalse( QAF_Payment_Settings::webhook_ready( 'stripe', 'test' ) );
	}

	public function test_readiness_is_for_the_sites_own_mode() {
		$this->make_ready( 'stripe', 'test' );
		$this->make_ready( 'paypal', 'test' );
		QAF_Payment_Settings::set_mode( 'live' );
		$r = QAF_Payment_Settings::readiness();
		$this->assertFalse( $r['test_mode'] );
		$this->assertFalse( $r['providers']['stripe']['configured'], 'test keys do not make live ready' );
		$this->assertFalse( $r['providers']['paypal']['webhook_ready'] );
	}

	public function test_a_key_from_the_other_environment_is_caught_by_its_prefix() {
		QAF_Payment_Settings::set_public( 'stripe', 'test', 'publishable_key', 'pk_live_placeholder000000000000' );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'secret_key', self::STRIPE_SK );
		$this->assertStringContainsString( 'pk_test_', QAF_Payment_Settings::shape_problem( 'stripe', 'test' ) );
		QAF_Payment_Settings::set_public( 'stripe', 'test', 'publishable_key', self::STRIPE_PK );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'secret_key', 'sk_live_placeholder000000000000' );
		$this->assertStringContainsString( 'sk_test_', QAF_Payment_Settings::shape_problem( 'stripe', 'test' ) );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'secret_key', 'rk_test_placeholder000000000000' );
		$this->assertSame( '', QAF_Payment_Settings::shape_problem( 'stripe', 'test' ), 'restricted keys are fine' );
	}

	public function test_the_readiness_reply_carries_no_secret() {
		$this->make_ready( 'stripe' );
		$this->make_ready( 'paypal' );
		$json = json_encode( QAF_Payment_Settings::readiness() );
		foreach ( array( self::STRIPE_SK, self::STRIPE_WH, self::STRIPE_PK, self::PAYPAL_SEC, self::PAYPAL_HOOK, self::PAYPAL_ID ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $json );
		}
		$this->assertSame( '{"contract_version":1,"test_mode":true,"providers":{"stripe":{"configured":true,"webhook_ready":true},"paypal":{"configured":true,"webhook_ready":true}}}', $json );
	}

	public function test_secrets_are_kept_apart_and_never_autoloaded() {
		$this->make_ready( 'stripe' );
		$this->assertFalse( $GLOBALS['qaf_test_autoload'][ QAF_Payment_Settings::OPTION_SECRETS ] );
		$public = json_encode( get_option( QAF_Payment_Settings::OPTION_SETTINGS ) );
		$this->assertStringNotContainsString( self::STRIPE_SK, $public );
		$this->assertStringNotContainsString( self::STRIPE_WH, $public );
		$checks = json_encode( get_option( QAF_Payment_Settings::OPTION_CHECKS ) );
		$this->assertStringNotContainsString( self::STRIPE_SK, $checks, 'a check keeps a hash, not the key' );
	}

	public function test_a_blank_secret_clears_it() {
		QAF_Payment_Settings::set_secret( 'paypal', 'test', 'secret', 'x' );
		$this->assertTrue( QAF_Payment_Settings::has_secret( 'paypal', 'test', 'secret' ) );
		QAF_Payment_Settings::set_secret( 'paypal', 'test', 'secret', '' );
		$this->assertFalse( QAF_Payment_Settings::has_secret( 'paypal', 'test', 'secret' ) );
		QAF_Payment_Settings::set_secret( 'paypal', 'test', 'password', 'x' );
		$this->assertFalse( QAF_Payment_Settings::has_secret( 'paypal', 'test', 'password' ), 'only known names are stored' );
	}

	public function test_a_constant_in_wp_config_wins_over_the_database() {
		if ( ! defined( 'QAF_PAYPAL_LIVE_SECRET' ) ) {
			define( 'QAF_PAYPAL_LIVE_SECRET', 'from-wp-config' );
		}
		QAF_Payment_Settings::set_secret( 'paypal', 'live', 'secret', 'from-database' );
		$this->assertSame( 'from-wp-config', QAF_Payment_Settings::get_secret( 'paypal', 'live', 'secret' ) );
		$this->assertTrue( QAF_Payment_Settings::secret_from_constant( 'paypal', 'live', 'secret' ) );
	}
}
