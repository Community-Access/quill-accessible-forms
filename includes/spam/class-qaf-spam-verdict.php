<?php
/**
 * What one spam check said about one submission, and what all of them said
 * together.
 *
 * The rule these classes exist for (QUILL's wordpress.md 13a.2, WP-R-026 and
 * WP-R-027): a check may say "this is spam" or "I am not sure", and either
 * way the entry is kept with status spam and the reason. No check drops an
 * entry, and a check that judges how someone typed or moved the mouse
 * ("behavioural") may only ever hold. The one refusal is the flood ceiling
 * (many sends from one address in a day), which is said plainly to the
 * person and never silent.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Verdict class.
 */
class QAF_Spam_Verdict {

	/** The check found nothing wrong. */
	const PASS = 'pass';

	/** The check doubts it. A question may settle it (13a.6); otherwise the entry is held. */
	const UNSURE = 'unsure';

	/** The check is sure. The entry is held without asking anything. */
	const SPAM = 'spam';

	/** The flood ceiling only: too many sends from one address today. Said to the person. */
	const REFUSE = 'refuse';

	/** A visible CAPTCHA that was not completed: the person is asked to complete it. */
	const INCOMPLETE = 'incomplete';

	/** @var string Provider id, such as honeypot or akismet. */
	public $provider = '';

	/** @var string One of the constants above. */
	public $verdict = self::PASS;

	/** @var string A sentence for the person reviewing: "6 links (the limit is 3)". */
	public $reason = '';

	/** @var float|null The provider's own score, when it has one. */
	public $score = null;

	/** @var float|null The top of that score's scale (6 for OOPSpam, 1 for reCAPTCHA). */
	public $score_max = null;

	/** @var bool True when the signal is about how the form was used, not what was sent. */
	public $behavioural = false;

	/** @var string For INCOMPLETE and REFUSE: what the person is told. */
	public $message = '';

	/**
	 * Make a verdict.
	 *
	 * @param string     $provider    Provider id.
	 * @param string     $verdict     One of the constants.
	 * @param string     $reason      Reason sentence.
	 * @param float|null $score       Score.
	 * @param float|null $score_max   Scale top.
	 * @param bool       $behavioural Behavioural signal.
	 */
	public function __construct( $provider, $verdict, $reason = '', $score = null, $score_max = null, $behavioural = false ) {
		$this->provider    = (string) $provider;
		$this->verdict     = in_array( $verdict, array( self::PASS, self::UNSURE, self::SPAM, self::REFUSE, self::INCOMPLETE ), true ) ? $verdict : self::UNSURE;
		$this->reason      = (string) $reason;
		$this->score       = null === $score ? null : (float) $score;
		$this->score_max   = null === $score_max ? null : (float) $score_max;
		$this->behavioural = (bool) $behavioural;
		// WP-R-027: a behavioural signal may hold, never reject.
		if ( $this->behavioural && in_array( $this->verdict, array( self::REFUSE, self::INCOMPLETE ), true ) ) {
			$this->verdict = self::UNSURE;
		}
	}

	public static function pass( $provider, $reason = '', $score = null, $score_max = null ) {
		return new self( $provider, self::PASS, $reason, $score, $score_max );
	}

	public static function unsure( $provider, $reason, $score = null, $score_max = null, $behavioural = false ) {
		return new self( $provider, self::UNSURE, $reason, $score, $score_max, $behavioural );
	}

	public static function spam( $provider, $reason, $score = null, $score_max = null, $behavioural = false ) {
		return new self( $provider, self::SPAM, $reason, $score, $score_max, $behavioural );
	}

	public static function refuse( $provider, $reason, $message ) {
		$v          = new self( $provider, self::REFUSE, $reason );
		$v->message = (string) $message;
		return $v;
	}

	public static function incomplete( $provider, $message ) {
		$v          = new self( $provider, self::INCOMPLETE, $message );
		$v->message = (string) $message;
		return $v;
	}

	/**
	 * A verdict from what a third party added through the qaf_spam_check
	 * filter: a verdict object, or an array with provider, verdict, reason
	 * and optionally score, score_max and behavioural.
	 *
	 * @param mixed $thing Verdict or array.
	 * @return QAF_Spam_Verdict|null
	 */
	public static function from( $thing ) {
		if ( $thing instanceof self ) {
			return $thing;
		}
		if ( ! is_array( $thing ) || empty( $thing['provider'] ) || empty( $thing['verdict'] ) ) {
			return null;
		}
		$verdict = (string) $thing['verdict'];
		// A third party cannot refuse an entry or demand a CAPTCHA: the most it can do is hold.
		if ( ! in_array( $verdict, array( self::PASS, self::UNSURE, self::SPAM ), true ) ) {
			$verdict = self::UNSURE;
		}
		return new self(
			sanitize_key( (string) $thing['provider'] ),
			$verdict,
			isset( $thing['reason'] ) ? (string) $thing['reason'] : '',
			isset( $thing['score'] ) ? $thing['score'] : null,
			isset( $thing['score_max'] ) ? $thing['score_max'] : null,
			! empty( $thing['behavioural'] )
		);
	}

	/**
	 * For storage and the REST reply.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'provider'    => $this->provider,
			'verdict'     => $this->verdict,
			'reason'      => $this->reason,
			'score'       => $this->score,
			'score_max'   => $this->score_max,
			'behavioural' => $this->behavioural,
		);
	}
}

/**
 * QAF_Spam_Result class: every verdict for one submission.
 */
class QAF_Spam_Result {

	/** @var QAF_Spam_Verdict[] */
	public $verdicts = array();

	/**
	 * @param QAF_Spam_Verdict[] $verdicts Verdicts.
	 */
	public function __construct( $verdicts = array() ) {
		foreach ( $verdicts as $v ) {
			$v = QAF_Spam_Verdict::from( $v );
			if ( $v ) {
				$this->verdicts[] = $v;
			}
		}
	}

	public function add( QAF_Spam_Verdict $v ) {
		$this->verdicts[] = $v;
		return $this;
	}

	private function first( $kind ) {
		foreach ( $this->verdicts as $v ) {
			if ( $v->verdict === $kind ) {
				return $v;
			}
		}
		return null;
	}

	/** The flood ceiling, if it was reached. */
	public function refusal() {
		return $this->first( QAF_Spam_Verdict::REFUSE );
	}

	/** A visible CAPTCHA the person still has to complete. */
	public function incomplete() {
		return $this->first( QAF_Spam_Verdict::INCOMPLETE );
	}

	public function is_spam() {
		return null !== $this->first( QAF_Spam_Verdict::SPAM );
	}

	public function is_unsure() {
		return ! $this->is_spam() && null !== $this->first( QAF_Spam_Verdict::UNSURE );
	}

	/**
	 * The verdict that explains a hold: the first sure one, else the first doubt.
	 *
	 * @return QAF_Spam_Verdict|null
	 */
	public function lead() {
		return $this->first( QAF_Spam_Verdict::SPAM ) ?: $this->first( QAF_Spam_Verdict::UNSURE );
	}

	/**
	 * Verdicts that are not passes, for the entry's details.
	 *
	 * @return array
	 */
	public function doubts() {
		$out = array();
		foreach ( $this->verdicts as $v ) {
			if ( QAF_Spam_Verdict::PASS !== $v->verdict ) {
				$out[] = $v->to_array();
			}
		}
		return $out;
	}

	/**
	 * Every verdict, for the entry's details.
	 *
	 * @return array
	 */
	public function to_array() {
		return array_map( function ( $v ) {
			return $v->to_array();
		}, $this->verdicts );
	}
}
