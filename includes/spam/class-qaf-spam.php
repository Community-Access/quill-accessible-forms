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
require_once __DIR__ . '/providers/class-qaf-spam-blocklist-check.php';
require_once __DIR__ . '/providers/class-qaf-spam-services.php';
require_once __DIR__ . '/providers/class-qaf-altcha.php';
require_once __DIR__ . '/class-qaf-spam-rest.php';

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
			new QAF_Spam_Altcha_Check( $settings, $store, $failures ),
			new QAF_Spam_Blocklist_Check( $settings ),
			new QAF_Spam_Shield_Check( $settings ),
			new QAF_Spam_Akismet_Check( $settings ),
			new QAF_Spam_OOPSpam_Check( $settings ),
			new QAF_Spam_CleanTalk_Check( $settings ),
		) );
	}

	/**
	 * Hook everything in. Called once from the main plugin class.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( new QAF_Spam_REST(), 'register_routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy' ) );
	}

	/**
	 * The privacy text QAF suggests for the site's privacy policy (Settings
	 * > Privacy > Policy Guide): what the spam checks keep, and what each
	 * service that is turned on is sent.
	 *
	 * @return string HTML.
	 */
	public static function privacy_text() {
		$p   = array();
		$p[] = '<p>' . esc_html__( 'When you send a form on this site, the site checks it for spam before saving it. It keeps your address (IP), the name of your browser, the time and the page along with your answers, so that a person can review anything the checks were unsure about. A form the checks doubt is kept for review, never thrown away.', 'quill-accessible-forms' ) . '</p>';
		$p[] = '<p>' . esc_html__( 'These checks run on this site and send nothing elsewhere: a hidden field that people never see, how quickly the form was sent, how many links it contains, the list of blocked words in the site settings, a small calculation your browser does in the background, and how many forms one address sends. A short question in words may be asked when the checks are unsure; its answer is kept on this site for at most 15 minutes.', 'quill-accessible-forms' ) . '</p>';
		$p[] = '<p>' . esc_html__( 'If this site has turned them on, these services are sent your answers, your address, the name of your browser and the page address to check for spam, and are told when a person marks a form as spam or not spam. Each has its own privacy policy:', 'quill-accessible-forms' ) . '</p>';
		$p[] = '<ul>'
			. '<li>' . esc_html__( 'Akismet, run by Automattic.', 'quill-accessible-forms' ) . '</li>'
			. '<li>' . esc_html__( 'OOPSpam (your address, email and answers).', 'quill-accessible-forms' ) . '</li>'
			. '<li>' . esc_html__( 'CleanTalk, once QAF works with it.', 'quill-accessible-forms' ) . '</li>'
			. '</ul>';
		$p[] = '<p>' . esc_html__( 'Sites that use reCAPTCHA, hCaptcha or Cloudflare Turnstile on a form also send those services what their own privacy policies describe.', 'quill-accessible-forms' ) . '</p>';
		return implode( "\n", $p );
	}

	/**
	 * Offer the privacy text to the site's privacy policy guide.
	 */
	public static function privacy_policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'QUILL Accessible Forms', 'quill-accessible-forms' ), wp_kses_post( self::privacy_text() ) );
		}
	}

	/**
	 * What a form page needs for the spam checks: the proof-of-work solver
	 * when ALTCHA is on. Returns the attributes for the form element.
	 *
	 * @param int $form_id Form id.
	 * @return array Form attributes to add.
	 */
	public static function form_attributes( $form_id ) {
		$settings = new QAF_Spam_Settings();
		if ( ! $settings->enabled( 'altcha' ) ) {
			return array();
		}
		return array( 'data-qaf-pow' => rest_url( 'qaf/v1/spam/pow' ) );
	}
}
