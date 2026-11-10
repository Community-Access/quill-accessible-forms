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
	 * Every provider, on or off.
	 *
	 * @return QAF_Spam_Provider[]
	 */
	public function providers() {
		return $this->providers;
	}

	/**
	 * @param string $id Provider id.
	 * @return QAF_Spam_Provider|null
	 */
	public function get( $id ) {
		return isset( $this->providers[ $id ] ) ? $this->providers[ $id ] : null;
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
		foreach ( $this->providers as $id => $p ) {
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
		return new QAF_Spam_Result( $verdicts );
	}
}
