<?php
/**
 * Named payment connections: more than one account per provider.
 *
 * A connection is one set of credentials for one provider, with a name a
 * person reads ("Stripe: Donations"). Every provider always has its default
 * connection, whose reference is the provider alone (`stripe`, `paypal`,
 * `square`); a site adds named ones, referenced as the provider, a colon and
 * a short name (`stripe:donations`, `square:downtown`). A form's payments
 * block names connections by reference (payment contract version 2), and
 * QAF_Payment_Settings keeps each connection's keys and checks under it.
 *
 * Only names live in `qaf_payments_connections`. A named connection that a
 * form uses cannot be removed: removing it would leave that form with
 * nowhere to send a payment, so remove() refuses and names the forms.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Connections class.
 */
class QAF_Payment_Connections {

	const OPTION = 'qaf_payments_connections';

	/** A reference: a provider, optionally a colon and a short name. */
	const REFERENCE = '/^(stripe|paypal|square)(?::([a-z0-9][a-z0-9-]{0,39}))?$/';

	/**
	 * The forms to look through, for tests: a callable returning
	 * array( array( 'id' => int, 'title' => string, 'model' => array ), ... ).
	 *
	 * @var callable|null
	 */
	public static $forms_source = null;

	/**
	 * Whether a text is a connection reference.
	 *
	 * @param mixed $ref Reference.
	 * @return bool Valid.
	 */
	public static function valid( $ref ) {
		return is_string( $ref ) && preg_match( self::REFERENCE, $ref ) === 1;
	}

	/**
	 * The named connections as stored: reference => array( 'name' => ... ).
	 *
	 * @return array Named connections.
	 */
	private static function named() {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Every connection, defaults first in provider order, then the named ones.
	 *
	 * @return array List of array( 'id', 'name', 'provider', 'default' ).
	 */
	public static function all() {
		$out = array();
		foreach ( QAF_Payment_Config::PROVIDERS as $p ) {
			$out[] = array(
				'id'       => $p,
				'name'     => QAF_Payment_Config::provider_name( $p ),
				'provider' => $p,
				'default'  => true,
			);
		}
		foreach ( self::named() as $ref => $row ) {
			if ( self::valid( $ref ) && strpos( $ref, ':' ) !== false ) {
				$out[] = array(
					'id'       => $ref,
					'name'     => is_string( $row['name'] ?? null ) && $row['name'] !== '' ? $row['name'] : $ref,
					'provider' => QAF_Payment_Settings::provider_of( $ref ),
					'default'  => false,
				);
			}
		}
		return $out;
	}

	/**
	 * Whether a connection exists on this site.
	 *
	 * @param string $ref Reference.
	 * @return bool Exists.
	 */
	public static function exists( $ref ) {
		if ( ! self::valid( $ref ) ) {
			return false;
		}
		return strpos( $ref, ':' ) === false || isset( self::named()[ $ref ] );
	}

	/**
	 * A connection's name, as the payer and the site owner read it.
	 *
	 * @param string $ref Reference.
	 * @return string Name.
	 */
	public static function name( $ref ) {
		foreach ( self::all() as $c ) {
			if ( $c['id'] === $ref ) {
				return $c['name'];
			}
		}
		return (string) $ref;
	}

	/**
	 * Add a named connection.
	 *
	 * @param string $provider stripe, paypal or square.
	 * @param string $short    Short name: lower-case letters, digits and hyphens.
	 * @param string $name     What people read, such as "Stripe: Donations".
	 * @return string|WP_Error The new reference.
	 */
	public static function add( $provider, $short, $name ) {
		$short = strtolower( trim( (string) $short ) );
		$ref   = $provider . ':' . $short;
		if ( ! in_array( $provider, QAF_Payment_Config::PROVIDERS, true ) || ! self::valid( $ref ) ) {
			return new WP_Error( 'qaf_connection_name', __( 'A short name is lower-case letters, digits and hyphens, at most 40, starting with a letter or digit.', 'quill-accessible-forms' ) );
		}
		$all = self::named();
		if ( isset( $all[ $ref ] ) ) {
			/* translators: %s: connection reference. */
			return new WP_Error( 'qaf_connection_exists', sprintf( __( 'There is already a connection called %s.', 'quill-accessible-forms' ), $ref ) );
		}
		$name        = trim( (string) $name );
		$all[ $ref ] = array( 'name' => $name !== '' ? $name : QAF_Payment_Config::provider_name( $provider ) . ': ' . $short );
		update_option( self::OPTION, $all );
		return $ref;
	}

	/**
	 * Rename a named connection. The reference stays, so forms keep working.
	 *
	 * @param string $ref  Reference.
	 * @param string $name New name.
	 * @return true|WP_Error Done.
	 */
	public static function rename( $ref, $name ) {
		$all  = self::named();
		$name = trim( (string) $name );
		if ( ! isset( $all[ $ref ] ) || $name === '' ) {
			return new WP_Error( 'qaf_connection_missing', __( 'Only a named connection can be renamed, and it needs a name.', 'quill-accessible-forms' ) );
		}
		$all[ $ref ]['name'] = $name;
		update_option( self::OPTION, $all );
		return true;
	}

	/**
	 * Remove a named connection and its keys, unless a form uses it.
	 *
	 * @param string $ref Reference.
	 * @return true|WP_Error Done, or why not (naming the forms that use it).
	 */
	public static function remove( $ref ) {
		$all = self::named();
		if ( ! isset( $all[ $ref ] ) ) {
			return new WP_Error( 'qaf_connection_missing', __( 'A provider\'s main connection cannot be removed; clear its keys instead.', 'quill-accessible-forms' ) );
		}
		$using = self::forms_using( $ref );
		if ( $using ) {
			$titles = array();
			foreach ( $using as $f ) {
				$titles[] = $f['title'] !== '' ? $f['title'] : '#' . $f['id'];
			}
			return new WP_Error(
				'qaf_connection_in_use',
				/* translators: 1: connection name, 2: list of form titles. */
				sprintf( __( '%1$s is used by these forms, so it was not removed: %2$s. Change those forms first.', 'quill-accessible-forms' ), self::name( $ref ), implode( ', ', $titles ) )
			);
		}
		unset( $all[ $ref ] );
		update_option( self::OPTION, $all );
		foreach ( QAF_Payment_Settings::ENVIRONMENTS as $env ) {
			foreach ( QAF_Payment_Settings::SECRET_KEYS[ QAF_Payment_Settings::provider_of( $ref ) ] as $key ) {
				QAF_Payment_Settings::set_secret( $ref, $env, $key, '' );
			}
		}
		return true;
	}

	/**
	 * The forms whose payments can go to a connection: a change to it affects these.
	 *
	 * @param string $ref Reference.
	 * @return array List of array( 'id', 'title' ).
	 */
	public static function forms_using( $ref ) {
		$out = array();
		foreach ( self::forms() as $f ) {
			if ( in_array( $ref, QAF_Payment_Config::references( $f['model'] ), true ) ) {
				$out[] = array( 'id' => (int) $f['id'], 'title' => (string) $f['title'] );
			}
		}
		return $out;
	}

	/**
	 * Every form that takes payment, with its model.
	 *
	 * @return array Forms.
	 */
	private static function forms() {
		if ( self::$forms_source ) {
			return call_user_func( self::$forms_source );
		}
		$out = array();
		foreach ( QAF_Form::get_all() as $form ) {
			if ( ( $form->status ?? '' ) === 'trash' ) {
				continue;
			}
			$model = is_array( $form->config['form_model'] ?? null ) ? $form->config['form_model'] : null;
			if ( $model && QAF_Payment_Config::applies( $model ) ) {
				$out[] = array( 'id' => $form->id, 'title' => (string) $form->title, 'model' => $model );
			}
		}
		return $out;
	}
}
