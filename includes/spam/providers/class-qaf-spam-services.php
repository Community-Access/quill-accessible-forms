<?php
/**
 * The spam services and security plugins a site may already have
 * (13a.3): Akismet and OOPSpam, which QAF asks; CleanTalk and Shield
 * Security, which QAF detects and does not yet ask.
 *
 * Every one of them is off until the site owner turns it on, and the ones
 * that send what a visitor wrote off the site are listed in the privacy
 * text QAF suggests for the site's privacy policy (decision 10).
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a plugin is active, by its folder and main file, without loading
 * wp-admin/includes/plugin.php on the front end.
 */
class QAF_Spam_Plugins {

	/**
	 * @param string $basename Such as akismet/akismet.php.
	 * @return bool
	 */
	public static function active( $basename ) {
		$active = (array) get_option( 'active_plugins', array() );
		if ( in_array( $basename, $active, true ) ) {
			return true;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network[ $basename ] );
		}
		return false;
	}
}

/**
 * Akismet: when the Akismet plugin is active with a key, an entry goes
 * through Akismet's comment-check as a contact form, and Spam and Not
 * Spam are reported back (submit-spam, submit-ham) so it learns.
 *
 * The request goes through the Akismet plugin's own Akismet::http_post()
 * (as Contact Form 7 does), so it uses the site's key, its server and its
 * user agent; QAF never handles the key.
 */
class QAF_Spam_Akismet_Check extends QAF_Spam_Provider_Base {

	/** @var callable|null fn( $query_string, $path ) => array( headers, body ) */
	private $transport;

	public function __construct( QAF_Spam_Settings $settings, $transport = null ) {
		parent::__construct( $settings );
		$this->transport = $transport;
	}

	public function id() {
		return 'akismet';
	}

	public function label() {
		return 'Akismet';
	}

	public function sends_content() {
		return true;
	}

	public function availability() {
		if ( $this->transport ) {
			return array( 'available' => true, 'note' => '' );
		}
		if ( ! class_exists( 'Akismet' ) || ! is_callable( array( 'Akismet', 'http_post' ) ) ) {
			return array( 'available' => false, 'note' => __( 'The Akismet plugin is not active.', 'quill-accessible-forms' ) );
		}
		$key = is_callable( array( 'Akismet', 'get_api_key' ) ) ? Akismet::get_api_key() : '';
		if ( ! $key ) {
			return array( 'available' => false, 'note' => __( 'Akismet is active but has no key yet (Settings > Akismet).', 'quill-accessible-forms' ) );
		}
		return array( 'available' => true, 'note' => __( 'Akismet is active with a key.', 'quill-accessible-forms' ) );
	}

	/**
	 * What Akismet is sent: the visitor's address, browser, the page, and
	 * what they wrote. Documented in the privacy text.
	 *
	 * @param QAF_Spam_Submission $s Submission.
	 * @return array
	 */
	public function params( QAF_Spam_Submission $s ) {
		return array_filter( array(
			'blog'                 => home_url(),
			'user_ip'              => $s->ip,
			'user_agent'           => $s->user_agent,
			'referrer'             => $s->referrer,
			'permalink'            => $s->permalink,
			'comment_type'         => 'contact-form',
			'comment_author'       => $s->author_name(),
			'comment_author_email' => $s->author_email(),
			'comment_author_url'   => $s->author_url(),
			'comment_content'      => $s->content(),
			'blog_lang'            => function_exists( 'get_locale' ) ? get_locale() : '',
			'blog_charset'         => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'charset' ) : 'UTF-8',
		), function ( $v ) {
			return '' !== (string) $v;
		} );
	}

	private function send( QAF_Spam_Submission $s, $path ) {
		$query = http_build_query( $this->params( $s ) );
		if ( $this->transport ) {
			return call_user_func( $this->transport, $query, $path );
		}
		return Akismet::http_post( $query, $path );
	}

	public function check( QAF_Spam_Submission $s ) {
		$response = $this->send( $s, 'comment-check' );
		if ( ! is_array( $response ) || ! isset( $response[1] ) ) {
			return null; // Akismet did not answer: no opinion.
		}
		$body    = trim( (string) $response[1] );
		$headers = isset( $response[0] ) ? $response[0] : array();
		if ( 'true' === $body ) {
			$tip = '';
			if ( is_array( $headers ) || $headers instanceof ArrayAccess ) {
				$tip = isset( $headers['x-akismet-pro-tip'] ) ? (string) $headers['x-akismet-pro-tip'] : '';
			}
			return QAF_Spam_Verdict::spam(
				$this->id(),
				'discard' === $tip ? __( 'Akismet: blatant spam.', 'quill-accessible-forms' ) : __( 'Akismet says this is spam.', 'quill-accessible-forms' )
			);
		}
		if ( 'false' === $body ) {
			return QAF_Spam_Verdict::pass( $this->id() );
		}
		return null; // "invalid" (a bad key) or anything else: no opinion.
	}

	public function mark_spam( QAF_Spam_Submission $s ) {
		$this->send( $s, 'submit-spam' );
	}

	public function mark_ham( QAF_Spam_Submission $s ) {
		$this->send( $s, 'submit-ham' );
	}
}

/**
 * OOPSpam: a content, sender, address and language check with a score
 * from 0 to 6. Spam and Not Spam are reported back.
 *
 * The key and threshold are QAF's own settings. When the OOPSpam plugin is
 * installed, a site can hand QAF that plugin's key and threshold through
 * the qaf_oopspam_plugin_settings filter; reading the plugin's saved
 * options directly waits until their names are verified with OOPSpam
 * (13a.3: "verify the plugin's integration hooks and report endpoint").
 */
class QAF_Spam_OOPSpam_Check extends QAF_Spam_Provider_Base {

	const API    = 'https://api.oopspam.com/v1/spamdetection';
	const REPORT = 'https://api.oopspam.com/v1/spamdetection/report';

	/** @var callable|null fn( $url, $args ) => a wp_remote_post-shaped reply */
	private $transport;

	public function __construct( QAF_Spam_Settings $settings, $transport = null ) {
		parent::__construct( $settings );
		$this->transport = $transport;
	}

	public function id() {
		return 'oopspam';
	}

	public function label() {
		return 'OOPSpam';
	}

	public function sends_content() {
		return true;
	}

	/**
	 * The key and threshold in use: from the OOPSpam plugin when the site
	 * passes them through the filter, else QAF's.
	 *
	 * @return array key, threshold, source (plugin or qaf).
	 */
	public function config() {
		$from_plugin = apply_filters( 'qaf_oopspam_plugin_settings', array() );
		if ( is_array( $from_plugin ) && ! empty( $from_plugin['key'] ) ) {
			return array(
				'key'       => (string) $from_plugin['key'],
				'threshold' => isset( $from_plugin['threshold'] ) ? max( 1, min( 6, (int) $from_plugin['threshold'] ) ) : (int) $this->settings->get( 'oopspam_threshold' ),
				'source'    => 'plugin',
			);
		}
		return array(
			'key'       => (string) $this->settings->get( 'oopspam_api_key' ),
			'threshold' => (int) $this->settings->get( 'oopspam_threshold' ),
			'source'    => 'qaf',
		);
	}

	public function availability() {
		$c = $this->config();
		if ( '' === $c['key'] ) {
			return array( 'available' => false, 'note' => __( 'No OOPSpam key yet.', 'quill-accessible-forms' ) );
		}
		/* translators: %d: the spam score at which an entry is held. */
		$note = sprintf( __( 'Holding at a score of %d or more, of 6.', 'quill-accessible-forms' ), $c['threshold'] );
		if ( 'plugin' === $c['source'] ) {
			$note .= ' ' . __( 'Using the key from the OOPSpam plugin.', 'quill-accessible-forms' );
		}
		return array( 'available' => true, 'note' => $note );
	}

	/**
	 * What OOPSpam is sent (documented in the privacy text).
	 *
	 * @param QAF_Spam_Submission $s Submission.
	 * @return array
	 */
	public function body( QAF_Spam_Submission $s ) {
		return array_filter( array(
			'senderIP' => $s->ip,
			'email'    => $s->author_email(),
			'content'  => $s->content(),
		), function ( $v ) {
			return '' !== (string) $v;
		} );
	}

	private function post( $url, $body ) {
		$args = array(
			'timeout' => 8,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $this->config()['key'],
			),
			'body'    => wp_json_encode( $body ),
		);
		return $this->transport ? call_user_func( $this->transport, $url, $args ) : wp_remote_post( $url, $args );
	}

	public function check( QAF_Spam_Submission $s ) {
		$c        = $this->config();
		$response = $this->post( self::API, $this->body( $s ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null; // No answer, or out of checks this month: no opinion.
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['Score'] ) || ! is_numeric( $data['Score'] ) ) {
			return null;
		}
		$score = (int) $data['Score'];
		if ( $score >= $c['threshold'] ) {
			return QAF_Spam_Verdict::spam(
				$this->id(),
				/* translators: 1: the score, 2: the site's threshold. */
				sprintf( __( 'OOPSpam score %1$d of 6 (the limit is %2$d).', 'quill-accessible-forms' ), $score, $c['threshold'] ),
				$score,
				6
			);
		}
		return QAF_Spam_Verdict::pass( $this->id(), '', $score, 6 );
	}

	private function report( QAF_Spam_Submission $s, $spam ) {
		$this->post( self::REPORT, array_merge( $this->body( $s ), array( 'shouldBeSpam' => (bool) $spam ) ) );
	}

	public function mark_spam( QAF_Spam_Submission $s ) {
		$this->report( $s, true );
	}

	public function mark_ham( QAF_Spam_Submission $s ) {
		$this->report( $s, false );
	}
}

/**
 * A plugin QAF can see is installed but does not yet ask: CleanTalk and
 * Shield Security. VERIFY INTEGRATION POINTS: their public functions or
 * filters for third-party forms are not confirmed, and QAF does not guess
 * at them. Until they are, this adapter detects the plugin, says so on the
 * settings page and in the Workbench, and holds nothing.
 */
abstract class QAF_Spam_Detected_Plugin extends QAF_Spam_Provider_Base {

	/** @return string The plugin's folder and main file. */
	abstract protected function basename();

	public function availability() {
		if ( ! QAF_Spam_Plugins::active( $this->basename() ) ) {
			/* translators: %s: plugin name. */
			return array( 'available' => false, 'note' => sprintf( __( '%s is not active on this site.', 'quill-accessible-forms' ), $this->label() ) );
		}
		return array(
			'available' => true,
			/* translators: %s: plugin name. */
			'note'      => sprintf( __( '%s is active. QAF does not ask it about entries yet: the ways it lets other forms ask are being confirmed with its developers.', 'quill-accessible-forms' ), $this->label() ),
		);
	}

	public function check( QAF_Spam_Submission $s ) {
		return null; // Holds nothing until verified (see the class comment).
	}
}

/**
 * CleanTalk (Anti-Spam by CleanTalk). Sends content to its cloud once
 * wired, so it is listed as sending content.
 */
class QAF_Spam_CleanTalk_Check extends QAF_Spam_Detected_Plugin {

	public function id() {
		return 'cleantalk';
	}

	public function label() {
		return 'CleanTalk';
	}

	public function sends_content() {
		return true;
	}

	protected function basename() {
		return 'cleantalk-spam-protect/cleantalk.php';
	}
}

/**
 * Shield Security (silentCAPTCHA and its address blocks). Its bot signal
 * judges behaviour, so once wired its answer may only hold (WP-R-027).
 */
class QAF_Spam_Shield_Check extends QAF_Spam_Detected_Plugin {

	public function id() {
		return 'shield';
	}

	public function label() {
		return 'Shield Security';
	}

	protected function basename() {
		return 'wp-simple-firewall/icwp-wpsf.php';
	}
}
