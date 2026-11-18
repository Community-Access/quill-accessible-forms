<?php
/**
 * The site's payment settings: which environment, which keys, and whether
 * each connection has been checked.
 *
 * Every setting is kept per **connection**: one set of credentials for one
 * provider. A connection is named by its reference, the provider alone
 * (`stripe`, `paypal`, `square`) for that provider's default connection, or
 * the provider, a colon and a slug (`stripe:events`) for a named one, as
 * QAF_Payment_Connections lists them. A site from before connections keeps
 * all its settings: they were stored under the provider's name, which is the
 * default connection's reference.
 *
 * Three WordPress options hold them:
 *
 * - `qaf_payments_settings` (loaded on every page): the mode, Stripe's
 *   publishable keys and PayPal's client IDs. None of these is secret; the
 *   page needs them to draw the checkout.
 * - `qaf_payments_secrets` (never autoloaded): secret keys, webhook signing
 *   secrets and webhook IDs. Nothing ever prints them, and no REST route
 *   returns them. A constant in wp-config.php, such as
 *   QAF_STRIPE_TEST_SECRET_KEY, takes the place of the stored value, so a
 *   host can keep secrets out of the database entirely.
 * - `qaf_payments_checks`: what Check Connection found, tied to a hash of the
 *   exact keys it checked. Change a key and the check no longer counts, so
 *   the readiness route cannot report a key nobody has verified.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Settings class.
 */
class QAF_Payment_Settings {

	const OPTION_SETTINGS = 'qaf_payments_settings';
	const OPTION_SECRETS  = 'qaf_payments_secrets';
	const OPTION_CHECKS   = 'qaf_payments_checks';

	const ENVIRONMENTS = array( 'test', 'live' );

	/** Secret names, per provider. */
	const SECRET_KEYS = array(
		'stripe' => array( 'secret_key', 'webhook_secret' ),
		'paypal' => array( 'secret', 'webhook_id' ),
		'square' => array( 'access_token', 'signature_key', 'subscription_id' ),
	);

	/** Public names, per provider. */
	const PUBLIC_KEYS = array(
		'stripe' => array( 'publishable_key' ),
		'paypal' => array( 'client_id' ),
		'square' => array( 'application_id', 'location_id', 'location_currency' ),
	);

	/**
	 * The provider a connection reference belongs to: `stripe:events` is Stripe's.
	 *
	 * @param string $ref Reference.
	 * @return string Provider.
	 */
	public static function provider_of( $ref ) {
		return explode( ':', (string) $ref, 2 )[0];
	}

	/**
	 * The site's environment: test unless someone chose live.
	 *
	 * @return string test or live.
	 */
	public static function mode() {
		$s = get_option( self::OPTION_SETTINGS, array() );
		return ( is_array( $s ) && ( $s['mode'] ?? '' ) === 'live' ) ? 'live' : 'test';
	}

	/**
	 * Whether the site is in test mode.
	 *
	 * @return bool Test mode.
	 */
	public static function is_test() {
		return self::mode() === 'test';
	}

	/**
	 * Set the environment.
	 *
	 * @param string $mode test or live.
	 */
	public static function set_mode( $mode ) {
		$s         = self::all_public();
		$s['mode'] = $mode === 'live' ? 'live' : 'test';
		update_option( self::OPTION_SETTINGS, $s );
	}

	/**
	 * All public settings.
	 *
	 * @return array Settings.
	 */
	private static function all_public() {
		$s = get_option( self::OPTION_SETTINGS, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * A public value (publishable key, client ID).
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @return string Value.
	 */
	public static function get_public( $provider, $env, $name ) {
		$s = self::all_public();
		$v = $s[ $provider ][ $env ][ $name ] ?? '';
		return is_string( $v ) ? $v : '';
	}

	/**
	 * Store a public value.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @param string $value    Value.
	 */
	public static function set_public( $provider, $env, $name, $value ) {
		if ( ! in_array( $name, self::PUBLIC_KEYS[ self::provider_of( $provider ) ] ?? array(), true ) || ! in_array( $env, self::ENVIRONMENTS, true ) ) {
			return;
		}
		$s                               = self::all_public();
		$s[ $provider ][ $env ][ $name ] = (string) $value;
		update_option( self::OPTION_SETTINGS, $s );
	}

	/**
	 * The constant that overrides a secret, such as QAF_STRIPE_TEST_SECRET_KEY.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @return string Constant name.
	 */
	public static function constant_name( $provider, $env, $name ) {
		return strtoupper( 'QAF_' . str_replace( array( ':', '-' ), '_', $provider ) . '_' . $env . '_' . $name );
	}

	/**
	 * Whether a secret comes from wp-config.php rather than the database.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @return bool From a constant.
	 */
	public static function secret_from_constant( $provider, $env, $name ) {
		$c = self::constant_name( $provider, $env, $name );
		return defined( $c ) && is_string( constant( $c ) ) && constant( $c ) !== '';
	}

	/**
	 * A secret, for server-side calls only. Never print or return it.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @return string Secret, or ''.
	 */
	public static function get_secret( $provider, $env, $name ) {
		if ( self::secret_from_constant( $provider, $env, $name ) ) {
			return (string) constant( self::constant_name( $provider, $env, $name ) );
		}
		$s = get_option( self::OPTION_SECRETS, array() );
		$v = is_array( $s ) ? ( $s[ $provider ][ $env ][ $name ] ?? '' ) : '';
		return is_string( $v ) ? $v : '';
	}

	/**
	 * Whether a secret is set, which is all an admin screen may show.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @return bool Set.
	 */
	public static function has_secret( $provider, $env, $name ) {
		return self::get_secret( $provider, $env, $name ) !== '';
	}

	/**
	 * Store or clear a secret. The option is never autoloaded.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $name     Name.
	 * @param string $value    Value; '' clears it.
	 */
	public static function set_secret( $provider, $env, $name, $value ) {
		if ( ! in_array( $name, self::SECRET_KEYS[ self::provider_of( $provider ) ] ?? array(), true ) || ! in_array( $env, self::ENVIRONMENTS, true ) ) {
			return;
		}
		$s = get_option( self::OPTION_SECRETS, array() );
		$s = is_array( $s ) ? $s : array();
		if ( $value === '' ) {
			unset( $s[ $provider ][ $env ][ $name ] );
		} else {
			$s[ $provider ][ $env ][ $name ] = (string) $value;
		}
		update_option( self::OPTION_SECRETS, $s, false );
	}

	/**
	 * A hash of the material a check covered, so the check lapses when a key changes.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $what     credentials or webhook.
	 * @return string Hex digest, or '' when the material is incomplete.
	 */
	public static function fingerprint( $provider, $env, $what ) {
		$ref = $provider;
		$p   = self::provider_of( $ref );
		if ( $p === 'stripe' ) {
			$parts = $what === 'webhook'
				? array( self::get_secret( $ref, $env, 'webhook_secret' ) )
				: array( self::get_public( $ref, $env, 'publishable_key' ), self::get_secret( $ref, $env, 'secret_key' ) );
		} elseif ( $p === 'square' ) {
			$parts = $what === 'webhook'
				? array( self::get_secret( $ref, $env, 'signature_key' ), self::get_public( $ref, $env, 'location_id' ) )
				: array( self::get_public( $ref, $env, 'application_id' ), self::get_public( $ref, $env, 'location_id' ), self::get_secret( $ref, $env, 'access_token' ) );
		} else {
			$parts = $what === 'webhook'
				? array( self::get_secret( $ref, $env, 'webhook_id' ), self::get_public( $ref, $env, 'client_id' ) )
				: array( self::get_public( $ref, $env, 'client_id' ), self::get_secret( $ref, $env, 'secret' ) );
		}
		foreach ( $parts as $p ) {
			if ( $p === '' ) {
				return '';
			}
		}
		return hash( 'sha256', $provider . '|' . $env . '|' . $what . '|' . implode( "\n", $parts ) );
	}

	/**
	 * Record that a check passed or failed for the current keys.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $what     credentials or webhook.
	 * @param bool   $ok       Passed.
	 * @param string $message  What a person reads about it.
	 */
	public static function record_check( $provider, $env, $what, $ok, $message ) {
		$c = get_option( self::OPTION_CHECKS, array() );
		$c = is_array( $c ) ? $c : array();
		$c[ $provider ][ $env ][ $what ] = array(
			'fingerprint' => $ok ? self::fingerprint( $provider, $env, $what ) : '',
			'ok'          => (bool) $ok,
			'message'     => (string) $message,
			'checked_at'  => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( self::OPTION_CHECKS, $c, false );
	}

	/**
	 * The last check, as recorded.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $what     credentials or webhook.
	 * @return array|null Check.
	 */
	public static function last_check( $provider, $env, $what ) {
		$c = get_option( self::OPTION_CHECKS, array() );
		$v = is_array( $c ) ? ( $c[ $provider ][ $env ][ $what ] ?? null ) : null;
		return is_array( $v ) ? $v : null;
	}

	/**
	 * Whether a check passed for exactly the keys stored now.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @param string $what     credentials or webhook.
	 * @return bool Verified.
	 */
	public static function verified( $provider, $env, $what ) {
		$check = self::last_check( $provider, $env, $what );
		$now   = self::fingerprint( $provider, $env, $what );
		return $check && ! empty( $check['ok'] ) && $now !== '' && hash_equals( $now, (string) $check['fingerprint'] );
	}

	/**
	 * Whether the stored keys have the shape this environment needs. Stripe
	 * keys say their environment in their prefix, so a live key saved under
	 * test (or the reverse) is caught before anything is sent.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @return string '' when fine, else the problem.
	 */
	public static function shape_problem( $provider, $env ) {
		$ref = $provider;
		if ( self::provider_of( $ref ) === 'square' ) {
			$app = self::get_public( $ref, $env, 'application_id' );
			if ( $app === '' || self::get_public( $ref, $env, 'location_id' ) === '' || self::get_secret( $ref, $env, 'access_token' ) === '' ) {
				return __( 'The application ID, the location ID and the access token are all needed.', 'quill-accessible-forms' );
			}
			// Square's sandbox application IDs start with sandbox-; production ones do not.
			if ( ( strpos( $app, 'sandbox-' ) === 0 ) !== ( $env === 'test' ) ) {
				return $env === 'test'
					? __( 'The application ID is not a sandbox one. It should start with sandbox-.', 'quill-accessible-forms' )
					: __( 'The application ID is a sandbox one; live payments need the production application ID.', 'quill-accessible-forms' );
			}
			return '';
		}
		if ( self::provider_of( $ref ) === 'stripe' ) {
			$pk = self::get_public( $ref, $env, 'publishable_key' );
			$sk = self::get_secret( $ref, $env, 'secret_key' );
			if ( $pk === '' || $sk === '' ) {
				return __( 'The publishable key and the secret key are both needed.', 'quill-accessible-forms' );
			}
			if ( strpos( $pk, 'pk_' . $env . '_' ) !== 0 ) {
				/* translators: %s: test or live. */
				return sprintf( __( 'The publishable key is not a %1$s key. It should start with pk_%1$s_.', 'quill-accessible-forms' ), $env );
			}
			if ( strpos( $sk, 'sk_' . $env . '_' ) !== 0 && strpos( $sk, 'rk_' . $env . '_' ) !== 0 ) {
				/* translators: %s: test or live. */
				return sprintf( __( 'The secret key is not a %1$s key. It should start with sk_%1$s_ or rk_%1$s_.', 'quill-accessible-forms' ), $env );
			}
			return '';
		}
		if ( self::get_public( $ref, $env, 'client_id' ) === '' || self::get_secret( $ref, $env, 'secret' ) === '' ) {
			return __( 'The client ID and the secret are both needed.', 'quill-accessible-forms' );
		}
		return '';
	}

	/**
	 * Whether a provider's credentials are verified for an environment.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @return bool Configured.
	 */
	public static function configured( $provider, $env ) {
		return self::shape_problem( $provider, $env ) === '' && self::verified( $provider, $env, 'credentials' );
	}

	/**
	 * Whether a provider's webhook is set and verified for an environment.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @return bool Ready.
	 */
	public static function webhook_ready( $provider, $env ) {
		if ( self::provider_of( $provider ) === 'stripe' && strpos( self::get_secret( $provider, $env, 'webhook_secret' ), 'whsec_' ) !== 0 ) {
			return false;
		}
		return self::verified( $provider, $env, 'webhook' );
	}

	/**
	 * The readiness reply of contract version 2: each provider's default
	 * connection under `providers`, as version 1 had it, and every connection
	 * under `connections`. Names and booleans only: no key, no token, no
	 * location ID, no provider reply, ever.
	 *
	 * @return array Reply.
	 */
	public static function readiness() {
		$env       = self::mode();
		$providers = array();
		foreach ( QAF_Payment_Config::PROVIDERS as $p ) {
			$providers[ $p ] = array(
				'configured'    => self::configured( $p, $env ),
				'webhook_ready' => self::webhook_ready( $p, $env ),
			);
		}
		$connections = array();
		foreach ( QAF_Payment_Connections::all() as $c ) {
			$connections[] = array(
				'id'            => $c['id'],
				'name'          => $c['name'],
				'test_mode'     => $env === 'test',
				'configured'    => self::configured( $c['id'], $env ),
				'webhook_ready' => self::webhook_ready( $c['id'], $env ),
			);
		}
		return array(
			'contract_version' => QAF_Payment_Config::CONTRACT_VERSION,
			'test_mode'        => $env === 'test',
			'providers'        => $providers,
			'connections'      => $connections,
		);
	}

	/**
	 * Whether a provider can take a payment right now in an environment.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @return bool Ready.
	 */
	public static function ready( $provider, $env ) {
		return self::configured( $provider, $env ) && self::webhook_ready( $provider, $env );
	}

	/**
	 * The URL a provider sends events to.
	 *
	 * @param string $provider Provider.
	 * @return string URL.
	 */
	public static function webhook_url( $provider ) {
		// A named connection's colon becomes a tilde: the path stays one plain segment.
		return rest_url( 'qaf/v1/payments/webhook/' . str_replace( ':', '~', $provider ) );
	}
}
