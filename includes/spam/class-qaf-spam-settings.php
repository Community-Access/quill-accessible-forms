<?php
/**
 * Spam settings: which checks are on, their limits, the question bank and
 * when a question is asked. One option, `qaf_spam_settings`, edited on
 * QAF Forms > Settings > Spam.
 *
 * Every check that sends what a visitor wrote to another company (Akismet,
 * OOPSpam, CleanTalk) is off until the site owner turns it on (decision 10
 * in QUILL's wordpress.md).
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Settings class.
 */
class QAF_Spam_Settings {

	const OPTION = 'qaf_spam_settings';

	/** The time to fill is never judged below this, however a site sets it. */
	const MIN_SECONDS_FLOOR = 3;

	/** When a question is asked. */
	const ASK_WHEN_UNSURE = 'auto';
	const ASK_ALWAYS      = 'always';
	const ASK_NEVER       = 'never';

	/** @var array|null Values, when given (tests) instead of read from the option. */
	private $values;

	/**
	 * @param array|null $values Values to use instead of the saved option.
	 */
	public function __construct( $values = null ) {
		$this->values = null === $values ? null : self::sanitize( $values );
	}

	/**
	 * Defaults. Providers: on for the quiet checks that never leave the
	 * site, off for anything that sends content away or needs a script.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'providers'          => array(
				'honeypot'   => true,
				'time'       => true,
				'links'      => true,
				'patterns'   => true,
				'blocklist'  => true,
				'rate_limit' => true,
				'token'      => true,
				'captcha'    => true,
				'akismet'    => false,
				'oopspam'    => false,
				'cleantalk'  => false,
				'shield'     => false,
				'altcha'     => false,
				'challenge'  => true,
			),
			'min_seconds'        => self::MIN_SECONDS_FLOOR,
			'max_links'          => 3,
			'use_disallowed'     => true,
			'use_moderation'     => true,
			'oopspam_api_key'    => '',
			'oopspam_threshold'  => 3,
			'altcha_max_number'  => 50000,
			'challenge_mode'     => self::ASK_WHEN_UNSURE,
			'challenge_kinds'    => array(
				'sense'       => true,
				'instruction' => true,
				'choice'      => true,
				'order'       => true,
				'maths'       => false,
			),
			'challenge_bank'     => '',
			'form_modes'         => array(),
		);
	}

	/**
	 * Clean what the settings page (or a test) sent. A check box that was
	 * not sent is off, so unchecking one sticks.
	 *
	 * @param mixed $in Input.
	 * @param bool  $from_form True when it came from the settings page, where a missing box means off.
	 * @return array
	 */
	public static function sanitize( $in, $from_form = false ) {
		$d  = self::defaults();
		$in = is_array( $in ) ? $in : array();
		$out = $d;

		foreach ( $d['providers'] as $id => $default ) {
			if ( isset( $in['providers'][ $id ] ) ) {
				$out['providers'][ $id ] = (bool) $in['providers'][ $id ];
			} elseif ( $from_form ) {
				$out['providers'][ $id ] = false;
			}
		}
		foreach ( $d['challenge_kinds'] as $kind => $default ) {
			if ( isset( $in['challenge_kinds'][ $kind ] ) ) {
				$out['challenge_kinds'][ $kind ] = (bool) $in['challenge_kinds'][ $kind ];
			} elseif ( $from_form ) {
				$out['challenge_kinds'][ $kind ] = false;
			}
		}
		foreach ( array( 'use_disallowed', 'use_moderation' ) as $flag ) {
			if ( isset( $in[ $flag ] ) ) {
				$out[ $flag ] = (bool) $in[ $flag ];
			} elseif ( $from_form ) {
				$out[ $flag ] = false;
			}
		}
		if ( isset( $in['min_seconds'] ) ) {
			$out['min_seconds'] = max( self::MIN_SECONDS_FLOOR, min( 120, (int) $in['min_seconds'] ) );
		}
		if ( isset( $in['max_links'] ) ) {
			$out['max_links'] = max( 0, min( 50, (int) $in['max_links'] ) );
		}
		if ( isset( $in['oopspam_threshold'] ) ) {
			$out['oopspam_threshold'] = max( 1, min( 6, (int) $in['oopspam_threshold'] ) );
		}
		if ( isset( $in['altcha_max_number'] ) ) {
			$out['altcha_max_number'] = max( 1000, min( 1000000, (int) $in['altcha_max_number'] ) );
		}
		if ( isset( $in['oopspam_api_key'] ) ) {
			$out['oopspam_api_key'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $in['oopspam_api_key'] );
		}
		if ( isset( $in['challenge_mode'] ) && in_array( $in['challenge_mode'], self::modes(), true ) ) {
			$out['challenge_mode'] = $in['challenge_mode'];
		}
		if ( isset( $in['challenge_bank'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $in['challenge_bank'] );
			$lines = array_slice( array_filter( array_map( function ( $l ) {
				return trim( sanitize_text_field( $l ) );
			}, $lines ) ), 0, 200 );
			$out['challenge_bank'] = implode( "\n", $lines );
		}
		if ( isset( $in['form_modes'] ) && is_array( $in['form_modes'] ) ) {
			foreach ( $in['form_modes'] as $form_id => $mode ) {
				if ( (int) $form_id > 0 && in_array( $mode, self::modes(), true ) ) {
					$out['form_modes'][ (int) $form_id ] = $mode;
				}
			}
		}
		return $out;
	}

	/**
	 * The site's own key for signing the form's start time and the proof of
	 * work. Made once, kept in the database, never sent to a page.
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( 'qaf_spam_secret', '' );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = bin2hex( random_bytes( 32 ) );
			update_option( 'qaf_spam_secret', $secret, false );
		}
		return $secret;
	}

	/**
	 * The signature the form page carries beside its start time.
	 *
	 * @param int $form_id Form id.
	 * @param int $loaded  Start time.
	 * @return string
	 */
	public static function sign_time( $form_id, $loaded ) {
		return hash_hmac( 'sha256', 'time|' . (int) $form_id . '|' . (int) $loaded, self::secret() );
	}

	/**
	 * The sanitize callback register_setting() uses. From the settings page
	 * (which sends a _from_page marker) a check box that was not sent is off,
	 * and an empty OOPSpam key field keeps the saved key unless "Remove the
	 * saved key" is checked, so the key is never written back into the page.
	 *
	 * @param mixed $in Input.
	 * @return array
	 */
	public static function sanitize_option( $in ) {
		if ( ! is_array( $in ) || empty( $in['_from_page'] ) ) {
			return self::sanitize( $in );
		}
		$saved = self::sanitize( get_option( self::OPTION, array() ) );
		if ( ( ! isset( $in['oopspam_api_key'] ) || '' === trim( (string) $in['oopspam_api_key'] ) ) && empty( $in['oopspam_api_key_clear'] ) ) {
			$in['oopspam_api_key'] = $saved['oopspam_api_key'];
		} elseif ( ! empty( $in['oopspam_api_key_clear'] ) ) {
			$in['oopspam_api_key'] = '';
		}
		return self::sanitize( $in, true );
	}

	/** @return string[] */
	public static function modes() {
		return array( self::ASK_WHEN_UNSURE, self::ASK_ALWAYS, self::ASK_NEVER );
	}

	/**
	 * Every value.
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->values ) {
			return $this->values;
		}
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * One value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether a provider is turned on.
	 *
	 * @param string $id Provider id.
	 * @return bool
	 */
	public function enabled( $id ) {
		$p = $this->get( 'providers' );
		return ! empty( $p[ $id ] );
	}

	/**
	 * When to ask a question on this form: the form's own choice (in its
	 * settings as spam_challenge, or on the Spam settings page), else the
	 * site's.
	 *
	 * @param int   $form_id       Form id.
	 * @param array $form_settings The form's settings.
	 * @return string auto, always or never.
	 */
	public function challenge_mode( $form_id, $form_settings = array() ) {
		if ( ! $this->enabled( 'challenge' ) ) {
			return self::ASK_NEVER;
		}
		if ( isset( $form_settings['spam_challenge'] ) && in_array( $form_settings['spam_challenge'], self::modes(), true ) ) {
			return $form_settings['spam_challenge'];
		}
		$per_form = $this->get( 'form_modes' );
		if ( isset( $per_form[ (int) $form_id ] ) ) {
			return $per_form[ (int) $form_id ];
		}
		return $this->get( 'challenge_mode' );
	}
}
