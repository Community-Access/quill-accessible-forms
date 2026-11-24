<?php
/**
 * A spam check (WP-R-028). QAF's own quiet checks, the site's blocklists,
 * the spam services a site already uses, proof of work and the text
 * question are all providers of this one shape, and a plugin can add its
 * own through the `qaf_spam_providers` filter, or add verdicts without a
 * provider through `qaf_spam_check`.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Provider interface.
 */
interface QAF_Spam_Provider {

	/** @return string Short id, such as akismet. */
	public function id();

	/** @return string The provider's name, for the settings page and the Workbench. */
	public function label();

	/** @return bool True when it sends what a visitor wrote off the site. */
	public function sends_content();

	/**
	 * Whether it can run here (its plugin is active, it has a key), and a
	 * sentence about its state for the settings page.
	 *
	 * @return array available (bool), note (string).
	 */
	public function availability();

	/**
	 * Check one submission.
	 *
	 * @param QAF_Spam_Submission $submission Submission.
	 * @return QAF_Spam_Verdict|null Null when it has no opinion.
	 */
	public function check( QAF_Spam_Submission $submission );

	/**
	 * Someone confirmed this entry is spam.
	 *
	 * @param QAF_Spam_Submission $submission The entry, as a submission.
	 */
	public function mark_spam( QAF_Spam_Submission $submission );

	/**
	 * Someone said this entry is not spam.
	 *
	 * @param QAF_Spam_Submission $submission The entry, as a submission.
	 */
	public function mark_ham( QAF_Spam_Submission $submission );
}

/**
 * QAF_Spam_Provider_Base class: the parts most providers share.
 */
abstract class QAF_Spam_Provider_Base implements QAF_Spam_Provider {

	/** @var QAF_Spam_Settings */
	protected $settings;

	public function __construct( QAF_Spam_Settings $settings ) {
		$this->settings = $settings;
	}

	public function sends_content() {
		return false;
	}

	public function availability() {
		return array( 'available' => true, 'note' => '' );
	}

	public function mark_spam( QAF_Spam_Submission $submission ) {
	}

	public function mark_ham( QAF_Spam_Submission $submission ) {
	}
}
