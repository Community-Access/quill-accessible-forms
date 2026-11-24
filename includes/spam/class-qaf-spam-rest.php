<?php
/**
 * Spam routes in qaf/v1.
 *
 * - GET spam/pow (public): a proof-of-work challenge for a form page, when
 *   ALTCHA is turned on. Its cost doubles with each wrong answer from the
 *   same address in the last hour.
 * - GET spam (people who can see entries): which checks are on, each
 *   provider's state, the limits, when questions are asked and this week's
 *   question numbers, for QUILL's Workbench Spam page (13a.5), with a
 *   revision for a writer's conflict check and whether this person may
 *   change them. No key is ever in the reply.
 * - POST spam (people who manage the site's settings): change any of those
 *   settings. Only what is sent changes; a key is write-only.
 * - GET spam/weeks (people who can see entries): this week's question
 *   numbers and the weeks before, newest first.
 * - GET and POST spam/questions, POST and DELETE spam/questions/{id}
 *   (people who manage the site's settings): the site's own question bank.
 *
 * Spam and Not Spam for one entry are entries/{id}/spam and
 * entries/{id}/not-spam, in QAF_REST_API. Every route here that reads is
 * also answered under baf/v1 for one release (QAF_Legacy_REST); the writes
 * are not.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_REST class.
 */
class QAF_Spam_REST {

	const NAMESPACE = 'qaf/v1';

	/** The settings POST spam takes (beside if_revision), with their ranges where they are numbers. */
	const WRITABLE = array(
		'providers',
		'min_seconds',
		'max_links',
		'oopspam_threshold',
		'altcha_max_number',
		'use_disallowed',
		'use_moderation',
		'oopspam_api_key',
		'oopspam_api_key_clear',
		'challenge_mode',
		'challenge_kinds',
		'form_modes',
		'send_anyway_after',
	);

	const RANGES = array(
		'min_seconds'       => array( QAF_Spam_Settings::MIN_SECONDS_FLOOR, 120 ),
		'max_links'         => array( 0, 50 ),
		'oopspam_threshold' => array( 1, 6 ),
		'altcha_max_number' => array( 1000, 1000000 ),
		'send_anyway_after' => array( QAF_Spam_Settings::SEND_ANYWAY_MIN, QAF_Spam_Settings::SEND_ANYWAY_MAX ),
	);

	/** Limits on one question in the bank. */
	const QUESTION_MAX_CHARS = 300;
	const ANSWER_MAX_CHARS   = 100;
	const ANSWERS_MAX        = 10;

	/** @var QAF_Spam_Settings|null */
	private $settings;

	/** @var QAF_Spam_Store|null */
	private $store;

	/**
	 * @param QAF_Spam_Settings|null $settings Settings (tests).
	 * @param QAF_Spam_Store|null    $store    Store (tests).
	 */
	public function __construct( $settings = null, $store = null ) {
		$this->settings = $settings;
		$this->store    = $store;
	}

	private function settings() {
		return $this->settings ?: new QAF_Spam_Settings();
	}

	private function store() {
		return $this->store ?: new QAF_Spam_Transient_Store();
	}

	private function challenge( QAF_Spam_Settings $settings ) {
		$store = $this->store();
		return new QAF_Text_Challenge( $settings, $store, new QAF_Spam_Failures( $store ) );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/spam', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'state' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
			),
			array(
				'methods'             => 'POST, PUT, PATCH',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/spam/weeks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'weeks' ),
			'permission_callback' => array( __CLASS__, 'can_view' ),
			'args'                => array(
				'weeks' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => QAF_Text_Challenge::HISTORY_MAX,
					'default' => 12,
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/spam/questions', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'questions' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_question' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/spam/questions/(?P<id>[0-9a-f]{12})', array(
			array(
				'methods'             => 'POST, PUT, PATCH',
				'callback'            => array( $this, 'edit_question' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_question' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/spam/pow', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'pow' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Who may read the spam state: people who can see entries.
	 *
	 * @return bool
	 */
	public static function can_view() {
		return current_user_can( 'qaf_view_entries' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Who may change the spam settings and read or change the question bank
	 * (whose answers would help a spammer): the people who can change the
	 * site's settings, or QAF's own settings.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( 'manage_options' ) || current_user_can( 'qaf_manage_settings' );
	}

	/**
	 * The spam state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function state( $request ) {
		$settings  = $this->settings();
		$store     = $this->store();
		$all       = $settings->all();
		$challenge = $this->challenge( $settings );
		return array(
			'providers'         => QAF_Spam::registry( $settings, $store )->states(),
			'limits'            => array(
				'min_seconds'       => (int) $all['min_seconds'],
				'max_links'         => (int) $all['max_links'],
				'oopspam_threshold' => (int) $all['oopspam_threshold'],
				'altcha_max_number' => (int) $all['altcha_max_number'],
				'use_disallowed'    => (bool) $all['use_disallowed'],
				'use_moderation'    => (bool) $all['use_moderation'],
			),
			'questions'         => array(
				'mode'              => $all['challenge_mode'],
				'form_modes'        => (object) $all['form_modes'],
				'kinds'             => $all['challenge_kinds'],
				'site_questions'    => count( $challenge->site_questions() ),
				'send_anyway_after' => (int) $all['send_anyway_after'],
				'this_week'         => $challenge->stats(),
			),
			'oopspam_key_saved' => '' !== (string) $all['oopspam_api_key'],
			'revision'          => QAF_Spam_Settings::revision( $all ),
			'can_edit'          => self::can_edit(),
		);
	}

	/**
	 * What a request sent: its JSON body, else its form body, else every
	 * parameter. Names starting with an underscore (_locale, _wpnonce,
	 * _method) are WordPress's own and are left out.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function body( $request ) {
		$body = null;
		if ( method_exists( $request, 'get_json_params' ) ) {
			$body = $request->get_json_params();
		}
		if ( ! is_array( $body ) && method_exists( $request, 'get_body_params' ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) || ! $body ) {
			$body = $request->get_params();
		}
		$out = array();
		foreach ( (array) $body as $k => $v ) {
			if ( '' !== (string) $k && '_' !== substr( (string) $k, 0, 1 ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * A yes or no from JSON or a form: true, false, 1, 0, "1", "0", "true", "false".
	 *
	 * @param mixed $v  Value.
	 * @param bool  $ok Set to false when it is neither.
	 * @return bool
	 */
	private static function flag( $v, &$ok ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( 1 === $v || '1' === $v || 'true' === $v ) {
			return true;
		}
		if ( 0 === $v || '0' === $v || 'false' === $v ) {
			return false;
		}
		$ok = false;
		return false;
	}

	/**
	 * A whole number from JSON or a form.
	 *
	 * @param mixed $v Value.
	 * @return int|null Null when it is not one.
	 */
	private static function whole( $v ) {
		if ( is_int( $v ) ) {
			return $v;
		}
		if ( is_float( $v ) && floor( $v ) === $v ) {
			return (int) $v;
		}
		if ( is_string( $v ) && preg_match( '/^-?\d{1,9}$/', trim( $v ) ) ) {
			return (int) trim( $v );
		}
		return null;
	}

	private static function refuse( $code, $message, $status, $extra = array() ) {
		return new WP_Error( $code, $message, array_merge( array( 'status' => $status ), $extra ) );
	}

	/**
	 * Change the spam settings. Only what is sent changes. Each value is
	 * checked, and nothing is saved when any is wrong: the reply names every
	 * wrong one (400). With if_revision, a write made after somebody else's
	 * is refused (409) and says the revision now saved.
	 *
	 * The OOPSpam key is write-only: oopspam_api_key sets it,
	 * oopspam_api_key_clear removes it, and no reply ever carries it, only
	 * oopspam_key_saved.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error The state as GET spam gives it, with `changed`: the settings this write changed.
	 */
	public function update( $request ) {
		$body    = self::body( $request );
		$unknown = array_values( array_diff( array_keys( $body ), array_merge( self::WRITABLE, array( 'if_revision' ) ) ) );
		if ( $unknown ) {
			return self::refuse( 'qaf_spam_unknown_setting', sprintf( 'These are not spam settings QAF can change: %s.', implode( ', ', $unknown ) ), 400, array( 'unknown' => $unknown ) );
		}
		$current = $this->settings()->all();
		if ( isset( $body['if_revision'] ) && ( ! is_string( $body['if_revision'] ) || ! hash_equals( QAF_Spam_Settings::revision( $current ), $body['if_revision'] ) ) ) {
			return self::refuse( 'qaf_spam_conflict', 'The spam settings were changed since they were read. Read them again, then make the change.', 409, array( 'revision' => QAF_Spam_Settings::revision( $current ) ) );
		}

		$defaults = QAF_Spam_Settings::defaults();
		$next     = $current;
		$errors   = array();

		foreach ( array( 'providers', 'challenge_kinds' ) as $group ) {
			if ( ! array_key_exists( $group, $body ) ) {
				continue;
			}
			if ( ! is_array( $body[ $group ] ) ) {
				$errors[ $group ] = sprintf( '%s must be an object of names and true or false.', $group );
				continue;
			}
			foreach ( $body[ $group ] as $id => $v ) {
				if ( ! array_key_exists( $id, $defaults[ $group ] ) ) {
					$errors[ $group . '.' . $id ] = sprintf( '"%s" is not one of %s: %s.', $id, $group, implode( ', ', array_keys( $defaults[ $group ] ) ) );
					continue;
				}
				$ok = true;
				$on = self::flag( $v, $ok );
				if ( ! $ok ) {
					$errors[ $group . '.' . $id ] = sprintf( '%s.%s must be true or false.', $group, $id );
					continue;
				}
				$next[ $group ][ $id ] = $on;
			}
		}

		foreach ( array( 'use_disallowed', 'use_moderation' ) as $name ) {
			if ( array_key_exists( $name, $body ) ) {
				$ok = true;
				$on = self::flag( $body[ $name ], $ok );
				if ( $ok ) {
					$next[ $name ] = $on;
				} else {
					$errors[ $name ] = sprintf( '%s must be true or false.', $name );
				}
			}
		}

		foreach ( self::RANGES as $name => $range ) {
			if ( ! array_key_exists( $name, $body ) ) {
				continue;
			}
			$n = self::whole( $body[ $name ] );
			if ( null === $n || $n < $range[0] || $n > $range[1] ) {
				$errors[ $name ] = sprintf( '%s must be a whole number from %d to %d.', $name, $range[0], $range[1] );
				continue;
			}
			$next[ $name ] = $n;
		}

		if ( array_key_exists( 'challenge_mode', $body ) ) {
			if ( in_array( $body['challenge_mode'], QAF_Spam_Settings::modes(), true ) ) {
				$next['challenge_mode'] = $body['challenge_mode'];
			} else {
				$errors['challenge_mode'] = sprintf( 'challenge_mode must be one of %s.', implode( ', ', QAF_Spam_Settings::modes() ) );
			}
		}

		if ( array_key_exists( 'form_modes', $body ) ) {
			if ( ! is_array( $body['form_modes'] ) ) {
				$errors['form_modes'] = 'form_modes must be an object of form ids and a mode (or null to use the site\'s).';
			} else {
				foreach ( $body['form_modes'] as $form_id => $mode ) {
					$id = self::whole( $form_id );
					if ( null === $id || $id < 1 ) {
						$errors[ 'form_modes.' . $form_id ] = sprintf( '"%s" is not a form id.', $form_id );
					} elseif ( null === $mode || '' === $mode ) {
						unset( $next['form_modes'][ $id ] );
					} elseif ( in_array( $mode, QAF_Spam_Settings::modes(), true ) ) {
						$next['form_modes'][ $id ] = $mode;
					} else {
						$errors[ 'form_modes.' . $form_id ] = sprintf( 'A form\'s mode must be one of %s, or null.', implode( ', ', QAF_Spam_Settings::modes() ) );
					}
				}
			}
		}

		$clear = false;
		if ( array_key_exists( 'oopspam_api_key_clear', $body ) ) {
			$ok    = true;
			$clear = self::flag( $body['oopspam_api_key_clear'], $ok );
			if ( ! $ok ) {
				$errors['oopspam_api_key_clear'] = 'oopspam_api_key_clear must be true or false.';
			}
		}
		if ( array_key_exists( 'oopspam_api_key', $body ) ) {
			$key = $body['oopspam_api_key'];
			if ( $clear ) {
				$errors['oopspam_api_key'] = 'Send oopspam_api_key or oopspam_api_key_clear, not both.';
			} elseif ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9_\-]{8,200}$/', $key ) ) {
				// The key itself is never repeated, in an error or anywhere else.
				$errors['oopspam_api_key'] = 'The OOPSpam key must be 8 to 200 letters, digits, hyphens or underscores. To remove the saved key, send oopspam_api_key_clear.';
			} else {
				$next['oopspam_api_key'] = $key;
			}
		} elseif ( $clear ) {
			$next['oopspam_api_key'] = '';
		}

		if ( $errors ) {
			return self::refuse( 'rest_invalid_param', 'Nothing was saved: ' . implode( ' ', array_values( $errors ) ), 400, array( 'params' => $errors ) );
		}

		$before = QAF_Spam_Settings::sanitize( $current );
		$saved  = QAF_Spam_Settings::save( $next );
		$this->settings = new QAF_Spam_Settings( $saved );

		$changed = array();
		foreach ( $saved as $name => $value ) {
			if ( ! array_key_exists( $name, $before ) || $before[ $name ] !== $value ) {
				$changed[] = $name;
			}
		}
		$state            = $this->state( $request );
		$state['changed'] = $changed;
		return $state;
	}

	/**
	 * This week's question numbers and the weeks before, newest first.
	 *
	 * @param WP_REST_Request $request Request (weeks: how many, this one included).
	 * @return array weeks, kept_days.
	 */
	public function weeks( $request ) {
		$n = self::whole( $request->get_param( 'weeks' ) );
		return array(
			'weeks'     => $this->challenge( $this->settings() )->history( null === $n ? 12 : $n ),
			'kept_days' => QAF_Text_Challenge::STATS_TTL_DAYS,
		);
	}

	// The question bank.

	private function bank() {
		return QAF_Text_Challenge::bank_items( (string) $this->settings()->get( 'challenge_bank' ) );
	}

	private function save_bank( $items ) {
		$all                   = $this->settings()->all();
		$all['challenge_bank'] = QAF_Text_Challenge::bank_text( $items );
		$this->settings        = new QAF_Spam_Settings( QAF_Spam_Settings::save( $all ) );
		return $this->bank();
	}

	/**
	 * The bank's questions, each with its answers and id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array questions, max, revision.
	 */
	public function questions( $request ) {
		return array(
			'questions' => $this->bank(),
			'max'       => QAF_Spam_Settings::BANK_MAX,
			'revision'  => QAF_Spam_Settings::revision( $this->settings()->all() ),
		);
	}

	private static function chars( $text ) {
		return (int) preg_match_all( '/./us', $text );
	}

	/**
	 * Clean one text of the bank: WordPress's text cleaning, spaces run
	 * together. Null when what came is not text.
	 */
	private static function bank_words( $v ) {
		if ( ! is_string( $v ) ) {
			return null;
		}
		return trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $v ) ) );
	}

	/**
	 * Check a question and its answers.
	 *
	 * @param mixed $question Question.
	 * @param mixed $answers  Answers.
	 * @param array $errors   Filled with what is wrong.
	 * @return array question, answers.
	 */
	private static function checked_question( $question, $answers, &$errors ) {
		$q = self::bank_words( $question );
		if ( null === $q || '' === $q ) {
			$errors['question'] = 'A question needs its words.';
		} elseif ( false !== strpos( $q, '|' ) ) {
			$errors['question'] = 'A question cannot have a bar (|) in it; the bank uses bars between answers.';
		} elseif ( self::chars( $q ) > self::QUESTION_MAX_CHARS ) {
			$errors['question'] = sprintf( 'A question can be at most %d characters.', self::QUESTION_MAX_CHARS );
		}
		$list = array();
		if ( ! is_array( $answers ) || ! $answers ) {
			$errors['answers'] = 'A question needs at least one right answer, as a list.';
		} elseif ( count( $answers ) > self::ANSWERS_MAX ) {
			$errors['answers'] = sprintf( 'A question can have at most %d right answers.', self::ANSWERS_MAX );
		} else {
			foreach ( array_values( $answers ) as $i => $a ) {
				$a = self::bank_words( $a );
				if ( null === $a || '' === $a ) {
					$errors[ 'answers.' . $i ] = sprintf( 'Answer %d is empty.', $i + 1 );
				} elseif ( false !== strpos( $a, '|' ) ) {
					$errors[ 'answers.' . $i ] = sprintf( 'Answer %d has a bar (|) in it.', $i + 1 );
				} elseif ( self::chars( $a ) > self::ANSWER_MAX_CHARS ) {
					$errors[ 'answers.' . $i ] = sprintf( 'Answer %d is longer than %d characters.', $i + 1, self::ANSWER_MAX_CHARS );
				} elseif ( ! in_array( $a, $list, true ) ) {
					$list[] = $a;
				}
			}
		}
		return array( 'question' => (string) $q, 'answers' => $list );
	}

	private static function invalid_question( $errors ) {
		return self::refuse( 'rest_invalid_param', 'The question was not saved: ' . implode( ' ', array_values( $errors ) ), 400, array( 'params' => $errors ) );
	}

	private static function not_in_bank( $id ) {
		return self::refuse( 'qaf_spam_question_not_found', 'That question is not in the bank: it was changed or removed since it was read. Read the questions again.', 404, array( 'id' => $id ) );
	}

	private static function find( $items, $id ) {
		foreach ( $items as $i => $item ) {
			if ( $item['id'] === $id ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Add a question: question, answers (a list).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The question as saved, with its id (201).
	 */
	public function add_question( $request ) {
		$body   = self::body( $request );
		$errors = array();
		$item   = self::checked_question( $body['question'] ?? null, $body['answers'] ?? null, $errors );
		if ( $errors ) {
			return self::invalid_question( $errors );
		}
		$items = $this->bank();
		if ( count( $items ) >= QAF_Spam_Settings::BANK_MAX ) {
			return self::refuse( 'qaf_spam_bank_full', sprintf( 'The bank holds at most %d questions; remove one first.', QAF_Spam_Settings::BANK_MAX ), 400 );
		}
		$item['id'] = QAF_Text_Challenge::bank_id( $item['question'], $item['answers'] );
		if ( null !== self::find( $items, $item['id'] ) ) {
			return self::refuse( 'qaf_spam_question_exists', 'That question, with those answers, is already in the bank.', 409, array( 'id' => $item['id'] ) );
		}
		$items[] = $item;
		$saved   = $this->save_bank( $items );
		$at      = self::find( $saved, $item['id'] );
		return new WP_REST_Response( null === $at ? $item : $saved[ $at ], 201 );
	}

	/**
	 * Change a question: question and/or answers. Its id changes with its
	 * words, so the reply has the new id and `replaces`, the old one.
	 *
	 * @param WP_REST_Request $request Request (id in the route).
	 * @return array|WP_Error
	 */
	public function edit_question( $request ) {
		$id    = (string) $request->get_param( 'id' );
		$items = $this->bank();
		$at    = self::find( $items, $id );
		if ( null === $at ) {
			return self::not_in_bank( $id );
		}
		$body   = self::body( $request );
		unset( $body['id'] );
		$errors = array();
		$item   = self::checked_question(
			array_key_exists( 'question', $body ) ? $body['question'] : $items[ $at ]['question'],
			array_key_exists( 'answers', $body ) ? $body['answers'] : $items[ $at ]['answers'],
			$errors
		);
		if ( $errors ) {
			return self::invalid_question( $errors );
		}
		$item['id'] = QAF_Text_Challenge::bank_id( $item['question'], $item['answers'] );
		$other      = self::find( $items, $item['id'] );
		if ( null !== $other && $other !== $at ) {
			return self::refuse( 'qaf_spam_question_exists', 'Another question in the bank already has those words and answers.', 409, array( 'id' => $item['id'] ) );
		}
		$items[ $at ] = $item;
		$saved        = $this->save_bank( $items );
		$now          = self::find( $saved, $item['id'] );
		$reply        = null === $now ? $item : $saved[ $now ];
		$reply['replaces'] = $id;
		return $reply;
	}

	/**
	 * Remove a question.
	 *
	 * @param WP_REST_Request $request Request (id in the route).
	 * @return array|WP_Error deleted, id, remaining.
	 */
	public function delete_question( $request ) {
		$id    = (string) $request->get_param( 'id' );
		$items = $this->bank();
		$at    = self::find( $items, $id );
		if ( null === $at ) {
			return self::not_in_bank( $id );
		}
		array_splice( $items, $at, 1 );
		$saved = $this->save_bank( $items );
		return array( 'deleted' => true, 'id' => $id, 'remaining' => count( $saved ) );
	}

	/**
	 * A proof-of-work challenge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function pow( $request ) {
		$settings = $this->settings();
		if ( ! $settings->enabled( 'altcha' ) ) {
			return new WP_Error( 'qaf_pow_off', __( 'Proof of work is not turned on for this site.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
		}
		$store = $this->store();
		$check = new QAF_Spam_Altcha_Check( $settings, $store, new QAF_Spam_Failures( $store ) );
		return $check->challenge_for( self::client_ip() );
	}

	/**
	 * The visitor's address, as the submit route reads it.
	 *
	 * @return string
	 */
	public static function client_ip() {
		if ( apply_filters( 'qaf_trust_proxy_headers', false ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}
}
