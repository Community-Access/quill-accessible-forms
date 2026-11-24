<?php
/**
 * The quiet checks: what the form was sent with, never how it was typed
 * (13a.2 rule 1). None of them sends anything off the site, and none of
 * them can drop an entry: they say spam or unsure, and the entry is held.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honeypot: fields a person never sees or reaches. Filled in means a script
 * filled every box it found.
 */
class QAF_Spam_Honeypot_Check extends QAF_Spam_Provider_Base {

	public function id() {
		return 'honeypot';
	}

	public function label() {
		return __( 'Honeypot', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		$names = class_exists( 'QAF_Honeypot' ) ? ( new QAF_Honeypot() )->get_field_names() : array( 'qaf_website', 'qaf_email_confirm', 'qaf_phone_alt', 'qaf_address_2' );
		foreach ( $names as $name ) {
			$value = $s->param( $name );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				/* translators: %s: the hidden field's name. */
				return QAF_Spam_Verdict::spam( $this->id(), sprintf( __( 'The hidden field %s was filled in.', 'quill-accessible-forms' ), $name ) );
			}
		}
		return QAF_Spam_Verdict::pass( $this->id() );
	}
}

/**
 * Time to fill: a form sent within seconds of the page opening. Generous
 * (never judged under three seconds) and only ever unsure, because people
 * who paste, dictate or use autofill are fast too.
 */
class QAF_Spam_Time_Check extends QAF_Spam_Provider_Base {

	public function id() {
		return 'time';
	}

	public function label() {
		return __( 'Time to fill', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		$loaded = $s->param( '_qaf_form_loaded' );
		if ( ! is_numeric( $loaded ) || (int) $loaded <= 0 ) {
			return null; // Sent by something other than the page (an app, an import): nothing to judge.
		}
		$loaded = (int) $loaded;
		$sig    = $s->param( '_qaf_form_sig' );
		if ( is_string( $sig ) && '' !== $sig && ! hash_equals( QAF_Spam_Settings::sign_time( $s->form_id, $loaded ), $sig ) ) {
			return QAF_Spam_Verdict::unsure( $this->id(), __( 'The form\'s start time was changed before it was sent.', 'quill-accessible-forms' ) );
		}
		$min     = max( QAF_Spam_Settings::MIN_SECONDS_FLOOR, (int) $this->settings->get( 'min_seconds' ) );
		$elapsed = $s->now - $loaded;
		if ( $elapsed < 0 ) {
			$elapsed = 0;
		}
		if ( $elapsed < $min ) {
			return QAF_Spam_Verdict::unsure(
				$this->id(),
				/* translators: 1: seconds taken, 2: the shortest time the site expects. */
				sprintf( _n( 'Filled in %1$d second (the shortest the site expects is %2$d).', 'Filled in %1$d seconds (the shortest the site expects is %2$d).', $elapsed, 'quill-accessible-forms' ), $elapsed, $min ),
				$elapsed,
				null,
				true
			);
		}
		return QAF_Spam_Verdict::pass( $this->id() );
	}
}

/**
 * Link count: more links than the site allows.
 */
class QAF_Spam_Links_Check extends QAF_Spam_Provider_Base {

	public function id() {
		return 'links';
	}

	public function label() {
		return __( 'Link count', 'quill-accessible-forms' );
	}

	/**
	 * Links in a text: each web address once, whether it starts with
	 * http, https or www, and whether or not it sits in a forum link code
	 * or a tag.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function count_links( $text ) {
		return (int) preg_match_all( '~(?:https?://|(?<![/\w.])www\.)~i', (string) $text );
	}

	public function check( QAF_Spam_Submission $s ) {
		$max   = (int) $this->settings->get( 'max_links' );
		$count = self::count_links( $s->content() );
		if ( $count > $max ) {
			return QAF_Spam_Verdict::unsure(
				$this->id(),
				/* translators: 1: links found, 2: the site's limit. */
				sprintf( _n( '%1$d link (the limit is %2$d).', '%1$d links (the limit is %2$d).', $count, 'quill-accessible-forms' ), $count, $max ),
				$count,
				$max
			);
		}
		return QAF_Spam_Verdict::pass( $this->id(), '', $count, $max );
	}
}

/**
 * Code and link tricks a person rarely writes into a form: script tags,
 * event handlers, data addresses, forum link codes.
 */
class QAF_Spam_Patterns_Check extends QAF_Spam_Provider_Base {

	public function id() {
		return 'patterns';
	}

	public function label() {
		return __( 'Code in answers', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		$patterns = array(
			'/<script[^>]*>/i'      => __( 'a script tag', 'quill-accessible-forms' ),
			'/javascript:/i'        => __( 'a javascript: address', 'quill-accessible-forms' ),
			'/<[a-z][^>]*\son\w+\s*=/i' => __( 'an event handler in a tag', 'quill-accessible-forms' ),
			'/data:text\/html/i'    => __( 'a data: address', 'quill-accessible-forms' ),
			'/\[(?:url|link)=/i'    => __( 'a forum link code', 'quill-accessible-forms' ),
		);
		$content = $s->content();
		foreach ( $patterns as $re => $what ) {
			if ( preg_match( $re, $content ) ) {
				/* translators: %s: what was found, such as "a script tag". */
				return QAF_Spam_Verdict::unsure( $this->id(), sprintf( __( 'The answers contain %s.', 'quill-accessible-forms' ), $what ) );
			}
		}
		return QAF_Spam_Verdict::pass( $this->id() );
	}
}

/**
 * Rate limit: many sends from one address. Over the form's limit, the entry
 * is held; over the daily ceiling, the person is told, plainly, to try again
 * tomorrow (the one refusal, so a flood cannot fill the database).
 */
class QAF_Spam_Rate_Check extends QAF_Spam_Provider_Base {

	/** @var QAF_Rate_Limiter */
	private $limiter;

	/** @var QAF_Spam_Failures|null */
	private $failures;

	public function __construct( QAF_Spam_Settings $settings, $limiter, $failures = null ) {
		parent::__construct( $settings );
		$this->limiter  = $limiter;
		$this->failures = $failures;
	}

	public function id() {
		return 'rate_limit';
	}

	public function label() {
		return __( 'Rate limit', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		if ( '' === $s->ip || $this->limiter->is_whitelisted( $s->ip ) ) {
			return null;
		}
		$limits  = $this->limiter->limits_for( $s->form_settings );
		$tighten = $this->failures ? $this->failures->count( $s->ip ) : 0;
		// An answer to a question the site asked is the same visit, counted once.
		$a = $this->limiter->assess( $s->form_id, $s->ip, $limits, ! $s->is_answer );

		if ( $a['daily'] >= $limits['global_max'] ) {
			return QAF_Spam_Verdict::refuse(
				$this->id(),
				/* translators: %d: the daily ceiling. */
				sprintf( __( 'More than %d sends from this address today.', 'quill-accessible-forms' ), $limits['global_max'] ),
				__( 'This address has sent a great many forms today. Please try again tomorrow, or contact the site another way.', 'quill-accessible-forms' )
			);
		}
		$allowed = max( 1, $limits['max_submissions'] - $tighten );
		if ( $a['count'] > $allowed ) {
			$minutes = max( 1, (int) round( $limits['window_seconds'] / 60 ) );
			return QAF_Spam_Verdict::spam(
				$this->id(),
				/* translators: 1: sends, 2: minutes, 3: the limit. */
				sprintf( __( '%1$d sends from this address in %2$d minutes (the limit is %3$d).', 'quill-accessible-forms' ), $a['count'], $minutes, $allowed ),
				$a['count'],
				$allowed
			);
		}
		return QAF_Spam_Verdict::pass( $this->id(), '', $a['count'], $allowed );
	}
}

/**
 * The page's form token. A stale one usually means a page left open a long
 * time or served from a cache, so it is a doubt, never a refusal: before
 * this change a stale token lost the person's answers.
 */
class QAF_Spam_Token_Check extends QAF_Spam_Provider_Base {

	/** @var callable|null fn( $token, $action ) => bool */
	private $verify;

	public function __construct( QAF_Spam_Settings $settings, $verify = null ) {
		parent::__construct( $settings );
		$this->verify = $verify;
	}

	public function id() {
		return 'token';
	}

	public function label() {
		return __( 'Form token', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		$token = $s->param( '_qaf_token' );
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$verify = $this->verify ?: 'wp_verify_nonce';
		if ( call_user_func( $verify, $token, 'qaf_submit_form_' . $s->form_id ) ) {
			return QAF_Spam_Verdict::pass( $this->id() );
		}
		return QAF_Spam_Verdict::unsure( $this->id(), __( 'The page\'s form token had expired (the page may have been open a long time, or cached).', 'quill-accessible-forms' ) );
	}
}

/**
 * The CAPTCHA a form already has. A visible puzzle that was not completed
 * is asked for again; reCAPTCHA v3 scores and Turnstile failures judge
 * behaviour, so they only ever hold (WP-R-027).
 */
class QAF_Spam_Captcha_Check extends QAF_Spam_Provider_Base {

	/** @var object Has assess( $request ) returning an array (see QAF_Captcha::assess). */
	private $captcha;

	public function __construct( QAF_Spam_Settings $settings, $captcha = null ) {
		parent::__construct( $settings );
		$this->captcha = $captcha;
	}

	public function id() {
		return 'captcha';
	}

	public function label() {
		return __( 'CAPTCHA', 'quill-accessible-forms' );
	}

	public function check( QAF_Spam_Submission $s ) {
		if ( empty( $s->form_settings['captcha_enabled'] ) || ! $s->request ) {
			return null;
		}
		$captcha = $this->captcha ?: new QAF_Captcha();
		$a       = $captcha->assess( $s->request );
		$p       = isset( $a['provider'] ) ? $a['provider'] : '';
		$names   = array(
			'recaptcha_v2' => 'reCAPTCHA v2',
			'recaptcha_v3' => 'reCAPTCHA v3',
			'hcaptcha'     => 'hCaptcha',
			'turnstile'    => 'Turnstile',
			'math'         => __( 'the maths question', 'quill-accessible-forms' ),
		);
		$name = isset( $names[ $p ] ) ? $names[ $p ] : $p;

		switch ( $a['outcome'] ) {
			case 'pass':
				return QAF_Spam_Verdict::pass( $this->id(), '', isset( $a['score'] ) ? $a['score'] : null, 'recaptcha_v3' === $p ? 1 : null );
			case 'unreachable':
				return null; // The service did not answer: not the visitor's fault.
			case 'missing':
			case 'wrong':
				if ( in_array( $p, array( 'recaptcha_v2', 'hcaptcha', 'math' ), true ) ) {
					return QAF_Spam_Verdict::incomplete( $this->id(), $a['message'] );
				}
				/* translators: %s: the CAPTCHA's name. */
				return QAF_Spam_Verdict::unsure( $this->id(), sprintf( __( 'No %s answer arrived with the form.', 'quill-accessible-forms' ), $name ), null, null, true );
			case 'low_score':
				return QAF_Spam_Verdict::unsure(
					$this->id(),
					/* translators: 1: score, 2: threshold. */
					sprintf( __( 'reCAPTCHA v3 score %1$s (the threshold is %2$s).', 'quill-accessible-forms' ), $a['score'], $a['threshold'] ),
					$a['score'],
					1,
					true
				);
			default:
				/* translators: %s: the CAPTCHA's name. */
				return QAF_Spam_Verdict::unsure( $this->id(), sprintf( __( '%s did not confirm the visitor.', 'quill-accessible-forms' ), $name ), null, null, true );
		}
	}
}
