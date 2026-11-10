<?php
/**
 * Spam: loads the parts and puts the checks together.
 *
 * Design: QUILL's wordpress.md section 13a (the rule that matters most,
 * providers, text questions) and docs/PRD.md here, under Spam.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-qaf-spam-verdict.php';
require_once __DIR__ . '/class-qaf-spam-submission.php';
require_once __DIR__ . '/class-qaf-spam-settings.php';
require_once __DIR__ . '/class-qaf-spam-store.php';
require_once __DIR__ . '/interface-qaf-spam-provider.php';
require_once __DIR__ . '/class-qaf-spam-registry.php';
require_once __DIR__ . '/class-qaf-spam-guard.php';
require_once __DIR__ . '/providers/class-qaf-spam-quiet-checks.php';

/**
 * QAF_Spam class.
 */
class QAF_Spam {

	/** @var QAF_Spam_Guard|null */
	private static $guard = null;

	/**
	 * Use a guard made elsewhere (tests), or null to go back to the default.
	 *
	 * @param QAF_Spam_Guard|null $guard Guard.
	 */
	public static function set_guard( $guard ) {
		self::$guard = $guard;
	}

	/**
	 * The site's guard, with every provider in the order they run.
	 *
	 * @return QAF_Spam_Guard
	 */
	public static function guard() {
		if ( null === self::$guard ) {
			$settings    = new QAF_Spam_Settings();
			$store       = new QAF_Spam_Transient_Store();
			self::$guard = new QAF_Spam_Guard( self::registry( $settings, $store ), $settings );
		}
		return self::$guard;
	}

	/**
	 * The providers, in order: the quiet checks first, then what the site's
	 * own lists and plugins say.
	 *
	 * @param QAF_Spam_Settings $settings Settings.
	 * @param QAF_Spam_Store    $store    Store.
	 * @return QAF_Spam_Registry
	 */
	public static function registry( QAF_Spam_Settings $settings, QAF_Spam_Store $store ) {
		$failures = new QAF_Spam_Failures( $store );
		return new QAF_Spam_Registry( $settings, array(
			new QAF_Spam_Honeypot_Check( $settings ),
			new QAF_Spam_Time_Check( $settings ),
			new QAF_Spam_Links_Check( $settings ),
			new QAF_Spam_Patterns_Check( $settings ),
			new QAF_Spam_Token_Check( $settings ),
			new QAF_Spam_Rate_Check( $settings, new QAF_Rate_Limiter(), $failures ),
			new QAF_Spam_Captcha_Check( $settings ),
		) );
	}
}
