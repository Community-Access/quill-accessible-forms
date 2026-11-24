<?php
/**
 * One form submission, as the spam checks see it.
 *
 * Built from a REST request when someone presses Submit, and again from a
 * saved entry when someone presses Spam or Not Spam, so a provider that
 * learns (Akismet, OOPSpam) is told about the same content it was asked
 * about.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Submission class.
 */
class QAF_Spam_Submission {

	/** @var int Form id. */
	public $form_id = 0;

	/** @var array The form's own settings (per-form limits and the question mode). */
	public $form_settings = array();

	/** @var array Field id => sanitized value. */
	public $fields = array();

	/** @var array Field id => field type, so the author's name and email can be found. */
	public $field_types = array();

	/** @var array Every other parameter the page sent (honeypot names, tokens, answers). */
	public $params = array();

	/** @var object|null The request itself, for checks that read it (CAPTCHA). */
	public $request = null;

	/** @var string Visitor's address. */
	public $ip = '';

	/** @var string Visitor's browser. */
	public $user_agent = '';

	/** @var string Page the form was on. */
	public $permalink = '';

	/** @var string Referring page. */
	public $referrer = '';

	/** @var int Logged-in user, or 0. */
	public $user_id = 0;

	/** @var string Language of the form, when it has one. */
	public $lang = '';

	/** @var int The time of the submission (Unix seconds). */
	public $now = 0;

	/**
	 * @var bool True when this send answers a question the site really asked
	 * (13a.6), so the rate limit counts the visit once, not once per answer.
	 */
	public $is_answer = false;

	/** @var int Saved entry id, when built from an entry. */
	public $entry_id = 0;

	/**
	 * From a submit request.
	 *
	 * @param object $form    QAF_Form (id, settings, get_fields()).
	 * @param object $request WP_REST_Request.
	 * @param array  $values  Sanitized field values.
	 * @param string $ip      Visitor address.
	 * @return QAF_Spam_Submission
	 */
	public static function from_request( $form, $request, $values, $ip ) {
		$s                = new self();
		$s->form_id       = (int) $form->id;
		$s->form_settings = is_array( $form->settings ) ? $form->settings : array();
		$s->fields        = is_array( $values ) ? $values : array();
		foreach ( (array) $form->get_fields() as $field ) {
			if ( isset( $field['id'] ) ) {
				$s->field_types[ $field['id'] ] = isset( $field['type'] ) ? (string) $field['type'] : 'text';
			}
		}
		$params = method_exists( $request, 'get_params' ) ? (array) $request->get_params() : array();
		unset( $params['fields'] );
		$s->params     = $params;
		$s->request    = $request;
		$s->ip         = (string) $ip;
		$s->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		$s->referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$s->permalink  = $s->referrer;
		$s->user_id    = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$s->lang       = sanitize_key( (string) $s->param( 'qaf_lang' ) );
		$s->now        = time();
		return $s;
	}

	/**
	 * From a saved entry, for Spam and Not Spam.
	 *
	 * @param object $entry QAF_Entry.
	 * @param object $form  QAF_Form, or null.
	 * @return QAF_Spam_Submission
	 */
	public static function from_entry( $entry, $form = null ) {
		$s             = new self();
		$s->entry_id   = (int) $entry->id;
		$s->form_id    = (int) $entry->form_id;
		$s->fields     = (array) $entry->get_field_values();
		$s->ip         = (string) $entry->ip_address;
		$s->user_agent = (string) $entry->user_agent;
		$s->permalink  = (string) $entry->source_url;
		$s->referrer   = (string) $entry->referrer_url;
		$s->user_id    = (int) $entry->user_id;
		$s->now        = $entry->created_at ? (int) strtotime( $entry->created_at ) : time();
		if ( $form ) {
			$s->form_settings = is_array( $form->settings ) ? $form->settings : array();
			foreach ( (array) $form->get_fields() as $field ) {
				if ( isset( $field['id'] ) ) {
					$s->field_types[ $field['id'] ] = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				}
			}
		}
		return $s;
	}

	/**
	 * A parameter the page sent, or null.
	 *
	 * @param string $name Name.
	 * @return mixed
	 */
	public function param( $name ) {
		return array_key_exists( $name, $this->params ) ? $this->params[ $name ] : null;
	}

	/**
	 * The visit id the page made when it loaded, kept short and plain.
	 *
	 * @return string
	 */
	public function visit() {
		$v = $this->param( 'qaf_visit' );
		return is_string( $v ) ? substr( preg_replace( '/[^A-Za-z0-9\-]/', '', $v ), 0, 64 ) : '';
	}

	/**
	 * What ties a question to this visit: the form, the page's visit id and
	 * the browser. Not the address, which changes on a phone between masts.
	 *
	 * @return string
	 */
	public function binding() {
		return hash( 'sha256', $this->form_id . '|' . $this->visit() . '|' . $this->user_agent );
	}

	/**
	 * Every string the visitor wrote, one per line.
	 *
	 * @return string
	 */
	public function content() {
		$parts = array();
		array_walk_recursive( $this->fields, function ( $v ) use ( &$parts ) {
			if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				$parts[] = (string) $v;
			}
		} );
		return implode( "\n", $parts );
	}

	/**
	 * The first value of a field of one of these types.
	 *
	 * @param string[] $types Field types.
	 * @return string
	 */
	private function first_of( $types ) {
		foreach ( $this->field_types as $id => $type ) {
			if ( in_array( $type, $types, true ) && isset( $this->fields[ $id ] ) ) {
				$v = $this->fields[ $id ];
				if ( is_array( $v ) ) {
					$v = implode( ' ', array_filter( array_map( 'strval', array_filter( $v, 'is_scalar' ) ) ) );
				}
				if ( '' !== trim( (string) $v ) ) {
					return trim( (string) $v );
				}
			}
		}
		return '';
	}

	public function author_name() {
		return $this->first_of( array( 'name', 'full_name' ) );
	}

	public function author_email() {
		return $this->first_of( array( 'email' ) );
	}

	public function author_url() {
		return $this->first_of( array( 'url', 'website' ) );
	}
}
