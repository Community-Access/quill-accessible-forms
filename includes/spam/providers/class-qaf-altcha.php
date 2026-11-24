<?php
/**
 * Proof of work, self-hosted, following ALTCHA's published challenge
 * format (altcha.org, "Server integration"): the browser is given a salt, a
 * SHA-256 challenge and an HMAC signature, and searches for the number that
 * makes SHA-256(salt + number) equal the challenge. Nothing leaves the
 * site, nothing is tracked, and there is no puzzle to see.
 *
 *   challenge = hex( SHA-256( salt . number ) )
 *   signature = hex( HMAC-SHA-256( challenge, site key ) )
 *   payload   = base64( JSON { algorithm, challenge, number, salt, signature } )
 *
 * The salt carries its own expiry as "?expires=<unix time>&". The closing
 * "&" matters: without it, digits of the number could be moved into the
 * salt to push the expiry out, so verify() accepts only that exact shape.
 *
 * QAF ships its own small solver (public/js/qaf-spam.js) rather than the
 * ALTCHA widget, so no file is downloaded or vendored. The format is
 * ALTCHA's, so the widget should work against create() too; that pairing
 * has not been tried in a browser.
 *
 * A missing or wrong solution is only a doubt (a browser with scripts off
 * cannot solve it), so the quiet checks or a question settle it.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Altcha class: the algorithm.
 */
class QAF_Altcha {

	const ALGORITHM = 'SHA-256';
	const TTL       = 1200;

	/** @var string */
	private $key;

	/**
	 * @param string $key HMAC key.
	 */
	public function __construct( $key ) {
		$this->key = (string) $key;
	}

	/**
	 * A new challenge.
	 *
	 * @param int      $max_number The search goes from 0 to this: the cost.
	 * @param int|null $now        The time (tests).
	 * @param int|null $number     The secret number (tests).
	 * @return array algorithm, challenge, maxnumber, salt, signature.
	 */
	public function create( $max_number, $now = null, $number = null ) {
		$max_number = max( 1, (int) $max_number );
		$now        = null === $now ? time() : (int) $now;
		$number     = null === $number ? random_int( 0, $max_number ) : (int) $number;
		$salt       = bin2hex( random_bytes( 12 ) ) . '?expires=' . ( $now + self::TTL ) . '&';
		$challenge  = hash( 'sha256', $salt . $number );
		return array(
			'algorithm' => self::ALGORITHM,
			'challenge' => $challenge,
			'maxnumber' => $max_number,
			'salt'      => $salt,
			'signature' => hash_hmac( 'sha256', $challenge, $this->key ),
		);
	}

	/**
	 * Check a solution.
	 *
	 * @param string   $payload Base64 JSON from the page.
	 * @param int|null $now     The time (tests).
	 * @return array ok (bool), reason (string: ok, malformed, expired, wrong, forged), challenge, expires.
	 */
	public function verify( $payload, $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$fail = function ( $why ) {
			return array( 'ok' => false, 'reason' => $why, 'challenge' => '', 'expires' => 0 );
		};
		if ( ! is_string( $payload ) || '' === $payload || strlen( $payload ) > 2000 ) {
			return $fail( 'malformed' );
		}
		$data = json_decode( (string) base64_decode( $payload, true ), true );
		if ( ! is_array( $data ) ) {
			return $fail( 'malformed' );
		}
		foreach ( array( 'algorithm', 'challenge', 'number', 'salt', 'signature' ) as $k ) {
			if ( ! isset( $data[ $k ] ) || ! is_scalar( $data[ $k ] ) ) {
				return $fail( 'malformed' );
			}
		}
		if ( self::ALGORITHM !== $data['algorithm'] || ! preg_match( '/^\d{1,9}$/', (string) $data['number'] ) ) {
			return $fail( 'malformed' );
		}
		if ( ! preg_match( '/^[0-9a-f]{24}\?expires=(\d{1,12})&$/', (string) $data['salt'], $m ) ) {
			return $fail( 'malformed' );
		}
		$expires = (int) $m[1];
		if ( $expires < $now ) {
			return $fail( 'expired' );
		}
		$challenge = (string) $data['challenge'];
		if ( ! hash_equals( hash_hmac( 'sha256', $challenge, $this->key ), (string) $data['signature'] ) ) {
			return $fail( 'forged' );
		}
		if ( ! hash_equals( hash( 'sha256', $data['salt'] . (int) $data['number'] ), $challenge ) ) {
			return $fail( 'wrong' );
		}
		return array( 'ok' => true, 'reason' => 'ok', 'challenge' => $challenge, 'expires' => $expires );
	}
}

/**
 * QAF_Spam_Altcha_Check class: the provider.
 */
class QAF_Spam_Altcha_Check extends QAF_Spam_Provider_Base {

	/** @var QAF_Spam_Store */
	private $store;

	/** @var QAF_Spam_Failures */
	private $failures;

	/** @var QAF_Altcha */
	private $altcha;

	public function __construct( QAF_Spam_Settings $settings, QAF_Spam_Store $store, QAF_Spam_Failures $failures, $altcha = null ) {
		parent::__construct( $settings );
		$this->store    = $store;
		$this->failures = $failures;
		$this->altcha   = $altcha ?: new QAF_Altcha( QAF_Spam_Settings::secret() );
	}

	public function id() {
		return 'altcha';
	}

	public function label() {
		return __( 'Proof of work (ALTCHA)', 'quill-accessible-forms' );
	}

	/**
	 * A challenge for this address: each wrong answer from it in the last
	 * hour doubles the work, up to sixteen times.
	 *
	 * @param string $ip Address.
	 * @return array
	 */
	public function challenge_for( $ip ) {
		$base  = (int) $this->settings->get( 'altcha_max_number' );
		$fails = min( 4, $this->failures->count( $ip ) );
		return $this->altcha->create( $base * ( 1 << $fails ) );
	}

	public function check( QAF_Spam_Submission $s ) {
		$payload = $s->param( 'qaf_pow' );
		if ( ! is_string( $payload ) || '' === $payload ) {
			return QAF_Spam_Verdict::unsure( $this->id(), __( 'No proof of work arrived (the page\'s script may not have run).', 'quill-accessible-forms' ) );
		}
		$r = $this->altcha->verify( $payload );
		if ( ! $r['ok'] ) {
			$why = array(
				'expired' => __( 'The proof of work had expired.', 'quill-accessible-forms' ),
				'forged'  => __( 'The proof of work was not one this site issued.', 'quill-accessible-forms' ),
				'wrong'   => __( 'The proof of work was wrong.', 'quill-accessible-forms' ),
			);
			return QAF_Spam_Verdict::unsure( $this->id(), isset( $why[ $r['reason'] ] ) ? $why[ $r['reason'] ] : __( 'The proof of work could not be read.', 'quill-accessible-forms' ) );
		}
		$key = 'pow:' . $r['challenge'];
		if ( $this->store->get( $key ) ) {
			return QAF_Spam_Verdict::unsure( $this->id(), __( 'The proof of work had already been used.', 'quill-accessible-forms' ) );
		}
		$this->store->set( $key, 1, max( 1, $r['expires'] - time() ) );
		return QAF_Spam_Verdict::pass( $this->id() );
	}
}
