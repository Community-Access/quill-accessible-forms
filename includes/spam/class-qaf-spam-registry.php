<?php
/**
 * The spam checks a site runs, in order, and what they said.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Registry class.
 */
class QAF_Spam_Registry {

	/** @var QAF_Spam_Settings */
	private $settings;

	/** @var QAF_Spam_Provider[] By id, in the order they run. */
	private $providers = array();

	/**
	 * @param QAF_Spam_Settings   $settings  Settings.
	 * @param QAF_Spam_Provider[] $providers Providers, in order.
	 */
	public function __construct( QAF_Spam_Settings $settings, $providers = array() ) {
		$this->settings = $settings;
		foreach ( $providers as $p ) {
			$this->add( $p );
		}
	}

	/**
	 * Add a provider (or replace one with the same id).
	 *
	 * @param QAF_Spam_Provider $provider Provider.
	 * @return $this
	 */
	public function add( QAF_Spam_Provider $provider ) {
		$this->providers[ $provider->id() ] = $provider;
		return $this;
	}

	/**
	 * Every provider, on or off, including any a plugin added through the
	 * qaf_spam_providers filter.
	 *
	 * @return QAF_Spam_Provider[]
	 */
	public function providers() {
		/**
		 * Filters the spam providers, in the order they run. A plugin adds an
		 * object that implements QAF_Spam_Provider; it runs whenever its
		 * availability() says it can, and is told about Spam and Not Spam.
		 *
		 * @param QAF_Spam_Provider[] $providers By id.
		 * @param QAF_Spam_Settings   $settings  The spam settings.
		 */
		$list = apply_filters( 'qaf_spam_providers', $this->providers, $this->settings );
		$out  = array();
		foreach ( (array) $list as $p ) {
			if ( $p instanceof QAF_Spam_Provider ) {
				$out[ $p->id() ] = $p;
			}
		}
		return $out;
	}

	/**
	 * @param string $id Provider id.
	 * @return QAF_Spam_Provider|null
	 */
	public function get( $id ) {
		$all = $this->providers();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Whether a provider runs: turned on, and able to run here. A provider
	 * a plugin added through the filter has no setting of its own and runs
	 * whenever it says it is available.
	 *
	 * @param QAF_Spam_Provider $p Provider.
	 * @return bool
	 */
	public function runs( QAF_Spam_Provider $p ) {
		$known = array_key_exists( $p->id(), QAF_Spam_Settings::defaults()['providers'] );
		if ( $known && ! $this->settings->enabled( $p->id() ) ) {
			return false;
		}
		$a = $p->availability();
		return ! empty( $a['available'] );
	}

	/**
	 * Run every provider that runs, except the ones named.
	 *
	 * @param QAF_Spam_Submission $submission Submission.
	 * @param string[]            $skip       Provider ids not to run (the question, which the guard asks itself).
	 * @return QAF_Spam_Result
	 */
	public function run( QAF_Spam_Submission $submission, $skip = array() ) {
		$verdicts = array();
		foreach ( $this->providers() as $id => $p ) {
			if ( in_array( $id, $skip, true ) || ! $this->runs( $p ) ) {
				continue;
			}
			try {
				$v = $p->check( $submission );
			} catch ( \Throwable $e ) {
				// A provider that breaks has no opinion; it never costs the visitor their entry.
				$v = null;
			}
			if ( $v instanceof QAF_Spam_Verdict ) {
				$verdicts[] = $v;
			}
		}
		/**
		 * Filters what the spam checks said about a submission. Add a verdict
		 * to hold an entry: a QAF_Spam_Verdict, or an array with provider,
		 * verdict (pass, unsure or spam), reason, and optionally score,
		 * score_max and behavioural. Nothing added here can refuse an entry:
		 * the most a verdict can do is hold it for a person to review.
		 *
		 * @param array               $verdicts   Verdicts so far.
		 * @param QAF_Spam_Submission $submission The submission.
		 */
		$verdicts = apply_filters( 'qaf_spam_check', $verdicts, $submission );
		return new QAF_Spam_Result( is_array( $verdicts ) ? $verdicts : array() );
	}

	/**
	 * Someone said Spam or Not Spam. Every provider that runs is told (so
	 * Akismet and OOPSpam learn), then qaf_entry_marked fires.
	 *
	 * @param QAF_Spam_Submission $submission The entry, as a submission.
	 * @param string              $as         spam or ham.
	 * @param object|null         $entry      The QAF_Entry, when there is one.
	 * @return string[] Ids of the providers that were told.
	 */
	public function mark( QAF_Spam_Submission $submission, $as, $entry = null ) {
		$as   = 'spam' === $as ? 'spam' : 'ham';
		$told = array();
		foreach ( $this->providers() as $id => $p ) {
			if ( ! $this->runs( $p ) ) {
				continue;
			}
			try {
				if ( 'spam' === $as ) {
					$p->mark_spam( $submission );
				} else {
					$p->mark_ham( $submission );
				}
				$told[] = $id;
			} catch ( \Throwable $e ) {
				continue; // A service that is down does not stop the person's decision.
			}
		}

		/**
		 * Fires when someone marks an entry Spam or Not Spam, so any plugin
		 * can learn from the person's decision.
		 *
		 * @param int                 $entry_id   Entry id.
		 * @param string              $as         spam or ham.
		 * @param QAF_Spam_Submission $submission What was sent.
		 * @param object|null         $entry      The QAF_Entry.
		 */
		do_action( 'qaf_entry_marked', (int) $submission->entry_id, $as, $submission, $entry );
		return $told;
	}

	/**
	 * Each provider's state, for the settings page and the Workbench.
	 *
	 * @return array[] id, label, enabled, available, runs, sends_content, note.
	 */
	public function states() {
		$out = array();
		foreach ( $this->providers() as $id => $p ) {
			$known = array_key_exists( $id, QAF_Spam_Settings::defaults()['providers'] );
			$a     = $p->availability();
			$out[] = array(
				'id'            => $id,
				'label'         => $p->label(),
				'enabled'       => $known ? $this->settings->enabled( $id ) : true,
				'available'     => ! empty( $a['available'] ),
				'runs'          => $this->runs( $p ),
				'sends_content' => $p->sends_content(),
				'note'          => isset( $a['note'] ) ? (string) $a['note'] : '',
			);
		}
		return $out;
	}
}
