<?php
/**
 * The site's own lists from Settings > Discussion: "Disallowed Comment
 * Keys" (disallowed_keys) and "Comment Moderation" (moderation_keys). One
 * list protects comments and forms.
 *
 * Matching follows WordPress's own (wp_check_comment_disallowed_list and
 * check_comment): one word or phrase per line, matched inside words,
 * without regard to case, against the name, email, web address, content,
 * address and browser.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Blocklist_Check class.
 */
class QAF_Spam_Blocklist_Check extends QAF_Spam_Provider_Base {

	/** @var callable fn( $name ) => option value */
	private $options;

	public function __construct( QAF_Spam_Settings $settings, $options = null ) {
		parent::__construct( $settings );
		$this->options = $options ?: 'get_option';
	}

	public function id() {
		return 'blocklist';
	}

	public function label() {
		return __( 'The site\'s blocklist and moderation words', 'quill-accessible-forms' );
	}

	public function availability() {
		$disallowed = $this->words( 'disallowed_keys' );
		$moderation = $this->words( 'moderation_keys' );
		return array(
			'available' => true,
			/* translators: 1: words in the disallowed list, 2: words in the moderation list. */
			'note'      => sprintf( __( '%1$d disallowed and %2$d moderation words from Settings > Discussion.', 'quill-accessible-forms' ), count( $disallowed ), count( $moderation ) ),
		);
	}

	/**
	 * The words in one of the Discussion lists.
	 *
	 * @param string $option disallowed_keys or moderation_keys.
	 * @return string[]
	 */
	public function words( $option ) {
		$raw = (string) call_user_func( $this->options, $option, '' );
		if ( 'disallowed_keys' === $option && '' === trim( $raw ) ) {
			// Sites last saved before WordPress 5.5 kept it under the old name.
			$raw = (string) call_user_func( $this->options, 'blacklist_keys', '' );
		}
		$words = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $w ) {
			$w = trim( $w );
			if ( '' !== $w ) {
				$words[] = $w;
			}
		}
		return $words;
	}

	/**
	 * The first word that matches, or null.
	 *
	 * @param string[]            $words Words.
	 * @param QAF_Spam_Submission $s     Submission.
	 * @return string|null
	 */
	public static function first_match( $words, QAF_Spam_Submission $s ) {
		$haystacks = array( $s->author_name(), $s->author_email(), $s->author_url(), $s->content(), $s->ip, $s->user_agent );
		foreach ( $words as $word ) {
			$pattern = '#' . preg_quote( $word, '#' ) . '#iu';
			foreach ( $haystacks as $h ) {
				if ( '' !== $h && @preg_match( $pattern, $h ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a word that is not valid UTF-8 matches nothing.
					return $word;
				}
			}
		}
		return null;
	}

	public function check( QAF_Spam_Submission $s ) {
		if ( $this->settings->get( 'use_disallowed' ) ) {
			$word = self::first_match( $this->words( 'disallowed_keys' ), $s );
			if ( null !== $word ) {
				/* translators: %s: the word or phrase that matched. */
				return QAF_Spam_Verdict::spam( $this->id(), sprintf( __( 'Matches the site\'s disallowed words: "%s".', 'quill-accessible-forms' ), $word ) );
			}
		}
		if ( $this->settings->get( 'use_moderation' ) ) {
			$word = self::first_match( $this->words( 'moderation_keys' ), $s );
			if ( null !== $word ) {
				/* translators: %s: the word or phrase that matched. */
				return QAF_Spam_Verdict::unsure( $this->id(), sprintf( __( 'Matches the site\'s moderation words: "%s".', 'quill-accessible-forms' ), $word ) );
			}
		}
		return QAF_Spam_Verdict::pass( $this->id() );
	}
}
