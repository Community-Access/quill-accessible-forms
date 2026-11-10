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

	public function __construct( QAF_Spam_Registry $registry, QAF_Spam_Settings $settings ) {
		$this->registry = $registry;
		$this->settings = $settings;
	}

	/** @return QAF_Spam_Registry */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Decide what happens to a submission.
	 *
	 * @param QAF_Spam_Submission $s       Submission.
	 * @param bool                $can_ask False where nobody can answer a question (an API client).
	 * @return QAF_Spam_Decision
	 */
	public function decide( QAF_Spam_Submission $s, $can_ask = true ) {
		$result = $this->registry->run( $s, array( 'challenge' ) );
		return $this->settle( $result );
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
