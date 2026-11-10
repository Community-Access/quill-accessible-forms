<?php
/**
 * What happens to one submission: saved, held as spam, or (only for the
 * flood ceiling and an unfinished visible CAPTCHA) handed back to the person.
 *
 * Hold, never drop (WP-R-026): anything a check doubts is saved with status
 * spam and its reason, and the visitor sees the same confirmation as
 * everyone else, so a real person caught by mistake loses nothing.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Decision class.
 */
class QAF_Spam_Decision {

	const SAVE       = 'save';
	const HOLD       = 'hold';
	const ASK        = 'ask';
	const REFUSE     = 'refuse';
	const INCOMPLETE = 'incomplete';

	/** @var string One of the constants. */
	public $action = self::SAVE;

	/** @var QAF_Spam_Result */
	public $result;

	/** @var QAF_Spam_Verdict|null What explains a hold. */
	public $lead = null;

	/** @var string For REFUSE and INCOMPLETE: what the person is told. */
	public $message = '';

	/** @var array|null For ASK: the question, as the page receives it. */
	public $challenge = null;

	/** @var array Extra notes kept with the entry (a question passed, Send Anyway). */
	public $notes = array();

	public function __construct( $action, QAF_Spam_Result $result ) {
		$this->action = $action;
		$this->result = $result;
	}

	/** @return bool True when an entry is to be saved (held or not). */
	public function saves() {
		return in_array( $this->action, array( self::SAVE, self::HOLD ), true );
	}

	/** @return string The entry status: spam when held. */
	public function status() {
		return self::HOLD === $this->action ? 'spam' : 'active';
	}

	/**
	 * What the entry keeps: the check that held it, the reason and the
	 * score, and every doubt in details.
	 *
	 * @return array spam_check, spam_reason, spam_score, spam_details (JSON or null).
	 */
	public function storage() {
		$doubts = $this->result->doubts();
		$lead   = self::HOLD === $this->action ? $this->lead : null;
		if ( ! $lead && ! $doubts && ! $this->notes ) {
			return array( 'spam_check' => null, 'spam_reason' => null, 'spam_score' => null, 'spam_details' => null );
		}
		$details = array(
			'held_at_submit' => self::HOLD === $this->action,
			'score_max'      => $lead ? $lead->score_max : null,
			'checks'         => $doubts,
			'notes'          => $this->notes,
		);
		return array(
			'spam_check'   => $lead ? $lead->provider : null,
			'spam_reason'  => $lead ? substr( $lead->reason, 0, 255 ) : null,
			'spam_score'   => $lead ? $lead->score : null,
			'spam_details' => wp_json_encode( $details ),
		);
	}
}

/**
 * QAF_Spam_Guard class.
 */
class QAF_Spam_Guard {

	/** @var QAF_Spam_Registry */
	protected $registry;

	/** @var QAF_Spam_Settings */
	protected $settings;

	/** @var QAF_Text_Challenge|null */
	protected $challenge;

	public function __construct( QAF_Spam_Registry $registry, QAF_Spam_Settings $settings, $challenge = null ) {
		$this->registry  = $registry;
		$this->settings  = $settings;
		$this->challenge = $challenge;
	}

	/** @return QAF_Spam_Registry */
	public function registry() {
		return $this->registry;
	}

	/** @return QAF_Text_Challenge|null */
	public function challenge() {
		return $this->challenge;
	}

	/**
	 * Decide what happens to a submission.
	 *
	 * Risk first, question second (13a.6): the quiet checks run; when one is
	 * sure, the entry is held without asking anything; when they are only
	 * unsure, a question is asked (if the form asks); a right answer saves
	 * it, a wrong one asks again, and after three tries Send Anyway holds it.
	 *
	 * @param QAF_Spam_Submission $s       Submission.
	 * @param bool                $can_ask False where nobody can answer a question (an API client).
	 * @return QAF_Spam_Decision
	 */
	public function decide( QAF_Spam_Submission $s, $can_ask = true ) {
		$challenge = $can_ask ? $this->challenge : null;

		// Send Anyway, offered after three tries: always held, never lost.
		$anyway = $s->param( 'qaf_send_anyway' );
		if ( $challenge && is_string( $anyway ) && '' !== $anyway ) {
			$tries = $challenge->redeem_send_anyway( $anyway, $s );
			if ( false !== $tries ) {
				$s->is_answer = true;
				$result       = $this->registry->run( $s, array( 'challenge' ) );
				if ( $result->refusal() ) {
					return $this->settle( $result );
				}
				/* translators: %d: how many questions were tried. */
				$lead    = QAF_Spam_Verdict::unsure( 'challenge', sprintf( _n( 'Sent anyway after %d question.', 'Sent anyway after %d questions.', $tries, 'quill-accessible-forms' ), $tries ) );
				$result->add( $lead );
				$d       = new QAF_Spam_Decision( QAF_Spam_Decision::HOLD, $result );
				$d->lead = $lead;
				return $d;
			}
		}

		// An answer to a question this site asked on this visit.
		$answered = null;
		$id       = $s->param( 'qaf_challenge_id' );
		if ( $challenge && is_string( $id ) && '' !== $id ) {
			$answered = $challenge->verify( $id, $s->param( 'qaf_challenge_answer' ), $s );
			if ( 'unknown' !== $answered ) {
				$s->is_answer = true;
			} else {
				$answered = null;
			}
		}

		$result = $this->registry->run( $s, array( 'challenge' ) );
		$d      = $this->settle( $result );
		if ( ! $d->saves() || $result->is_spam() ) {
			return $d; // Refused, a CAPTCHA to finish, or sure: held without a question.
		}

		$mode  = $this->settings->challenge_mode( $s->form_id, $s->form_settings );
		$wants = $challenge && ( QAF_Spam_Settings::ASK_ALWAYS === $mode || ( QAF_Spam_Settings::ASK_WHEN_UNSURE === $mode && $result->is_unsure() ) );
		if ( ! $wants ) {
			return $d; // Unsure and no question: held. Nothing doubtful: saved.
		}

		if ( 'pass' === $answered ) {
			$d          = new QAF_Spam_Decision( QAF_Spam_Decision::SAVE, $result );
			$d->notes[] = __( 'Answered the question.', 'quill-accessible-forms' );
			return $d;
		}

		$errors = array(
			'fail'    => __( 'That answer did not match. Here is another question; take as long as you like.', 'quill-accessible-forms' ),
			'empty'   => __( 'Please answer the question below.', 'quill-accessible-forms' ),
			'expired' => __( 'That question was replaced to keep the form safe. Here is a new one.', 'quill-accessible-forms' ),
		);
		if ( 'fail' === $answered ) {
			$challenge->record_failure( $s );
		}
		$error        = ( $answered && isset( $errors[ $answered ] ) ) ? $errors[ $answered ] : '';
		$d            = new QAF_Spam_Decision( QAF_Spam_Decision::ASK, $result );
		$d->challenge = $challenge->issue( $s, $error );
		$d->message   = $error ?: __( 'One more question before this is sent.', 'quill-accessible-forms' );
		if ( ! empty( $d->challenge['send_anyway'] ) ) {
			$d->message .= ' ' . __( 'You can also send it as it is: a person will read it first.', 'quill-accessible-forms' );
		}
		return $d;
	}

	/**
	 * Turn verdicts into a decision, before any question.
	 *
	 * @param QAF_Spam_Result $result Verdicts.
	 * @return QAF_Spam_Decision
	 */
	protected function settle( QAF_Spam_Result $result ) {
		$refusal = $result->refusal();
		if ( $refusal ) {
			$d          = new QAF_Spam_Decision( QAF_Spam_Decision::REFUSE, $result );
			$d->message = $refusal->message;
			return $d;
		}
		$incomplete = $result->incomplete();
		if ( $incomplete ) {
			$d          = new QAF_Spam_Decision( QAF_Spam_Decision::INCOMPLETE, $result );
			$d->message = $incomplete->message;
			return $d;
		}
		if ( $result->is_spam() || $result->is_unsure() ) {
			$d       = new QAF_Spam_Decision( QAF_Spam_Decision::HOLD, $result );
			$d->lead = $result->lead();
			return $d;
		}
		return new QAF_Spam_Decision( QAF_Spam_Decision::SAVE, $result );
	}
}
