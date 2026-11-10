<?php
/**
 * REST and admin: entries carry their reason and score, Spam and Not Spam
 * tell the providers and fire qaf_entry_marked, the Spam state for the
 * Workbench, and the Settings > Spam section.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'spam-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-entry.php';
require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-rest-api.php';

/**
 * An entry that saves in memory.
 */
class QAF_Memory_Entry extends QAF_Entry {
	public $saves = 0;

	public function save() {
		$this->saves++;
		return $this->id;
	}

	public function get_form() {
		return null;
	}
}

class QAF_Spam_Rest_And_Admin_Test extends QAF_Spam_Test_Case {

	/** @var array Paths Akismet was sent to. */
	private $akismet = array();

	protected function setUp(): void {
		parent::setUp();
		QAF_Payment_Service::set_instance( new QAF_Payment_Service( new QAF_Payment_Memory_Store(), array( 'none' => null ) ) );
		$s             = $this->settings( array( 'providers' => array( 'akismet' => true ) ) );
		$this->akismet = array();
		$calls         = &$this->akismet;
		$registry      = new QAF_Spam_Registry( $s, array(
			new QAF_Spam_Honeypot_Check( $s ),
			new QAF_Spam_Akismet_Check( $s, function ( $q, $path ) use ( &$calls ) {
				$calls[] = $path;
				return array( array(), '' );
			} ),
		) );
		QAF_Spam::set_guard( new QAF_Spam_Guard( $registry, $s ) );
	}

	protected function tearDown(): void {
		QAF_Spam::set_guard( null );
		QAF_Payment_Service::set_instance( null );
		parent::tearDown();
	}

	private function held_entry() {
		$e              = new QAF_Memory_Entry();
		$e->id          = 31;
		$e->form_id     = 7;
		$e->status      = 'spam';
		$e->ip_address  = '203.0.113.5';
		$e->spam_check  = 'links';
		$e->spam_reason = '6 links (the limit is 3).';
		$e->spam_score  = 6.0;
		$e->set_field_values( array( 'message' => 'see http://a.example' ) );
		$e->set_spam_details( array( 'held_at_submit' => true, 'score_max' => 3, 'checks' => array( array( 'provider' => 'links', 'verdict' => 'unsure' ) ), 'notes' => array() ) );
		return $e;
	}

	public function test_the_spam_routes_need_the_entry_editing_capability() {
		( new QAF_REST_API() )->register_routes();
		$routes = $GLOBALS['qaf_test_routes'];
		foreach ( array( 'spam', 'not-spam' ) as $r ) {
			$route = $routes[ 'qaf/v1/entries/(?P<id>[\d]+)/' . $r ];
			$this->assertSame( 'POST', $route['methods'] );
			$this->assertSame( 'update_entry_permissions_check', $route['permission_callback'][1] );
		}
		$this->assertContains( 'spam', $routes['qaf/v1/entries'][0]['args']['status']['enum'], 'the list can show held entries' );
	}

	public function test_an_entry_says_why_it_was_held() {
		$view = QAF_Spam::entry_view( $this->held_entry() );
		$this->assertSame( 'links', $view['check'] );
		$this->assertSame( '6 links (the limit is 3).', $view['reason'] );
		$this->assertSame( 6.0, $view['score'] );
		$this->assertSame( 3.0, $view['score_max'] );
		$this->assertTrue( $view['held_at_submit'] );
		$this->assertNull( QAF_Spam::entry_view( new QAF_Memory_Entry() ), 'nothing doubted: no spam object' );
	}

	public function test_not_spam_restores_tells_the_providers_and_releases_once() {
		$entry = $this->held_entry();
		$reply = ( new QAF_REST_API() )->mark_entry( new WP_REST_Request( 'POST' ), 'ham', $entry );
		$this->assertSame( 'active', $reply['status'] );
		$this->assertSame( array( 'honeypot', 'akismet' ), $reply['told'] );
		$this->assertTrue( $reply['released'] );
		$this->assertSame( array( 'submit-ham' ), $this->akismet );
		$this->assertSame( '6 links (the limit is 3).', $reply['spam']['reason'], 'the history stays' );
		$this->assertSame( 'ham', $reply['spam']['marks'][0]['as'] );
		$this->assertCount( 1, self::fired( 'qaf_entry_marked' ) );
		$this->assertCount( 1, self::fired( 'qaf_form_submitted' ), 'notifications that waited go out' );

		QAF_Spam::mark_entry( $entry, 'spam' );
		QAF_Spam::mark_entry( $entry, 'ham' );
		$this->assertCount( 1, self::fired( 'qaf_form_submitted' ), 'released once, however often it is marked' );
		$this->assertCount( 3, self::fired( 'qaf_entry_marked' ) );
	}

	public function test_spam_holds_an_entry_and_says_a_person_did_it() {
		$entry         = new QAF_Memory_Entry();
		$entry->id     = 32;
		$entry->status = 'active';
		$reply         = ( new QAF_REST_API() )->mark_entry( new WP_REST_Request( 'POST' ), 'spam', $entry );
		$this->assertSame( 'spam', $reply['status'] );
		$this->assertSame( 'person', $reply['spam']['check'] );
		$this->assertSame( array( 'submit-spam' ), $this->akismet );
		$this->assertFalse( $reply['released'] );
		$marked = self::fired( 'qaf_entry_marked' );
		$this->assertSame( array( 32, 'spam' ), array( $marked[0][1][0], $marked[0][1][1] ) );
	}

	public function test_an_entry_in_the_trash_is_restored_first() {
		$entry         = new QAF_Memory_Entry();
		$entry->id     = 33;
		$entry->status = 'trash';
		$this->assertSame( 'rest_entry_in_trash', ( new QAF_REST_API() )->mark_entry( new WP_REST_Request( 'POST' ), 'ham', $entry )->get_error_code() );
	}

	public function test_the_spam_state_for_the_workbench_carries_no_key() {
		$s     = $this->settings( array( 'oopspam_api_key' => 'secret-key-999' ) );
		$state = ( new QAF_Spam_REST( $s, new QAF_Spam_Memory_Store() ) )->state( new WP_REST_Request( 'GET' ) );
		$this->assertSame( array( 'providers', 'limits', 'questions', 'oopspam_key_saved' ), array_keys( $state ) );
		$this->assertTrue( $state['oopspam_key_saved'] );
		$this->assertStringNotContainsString( 'secret-key-999', json_encode( $state ) );
		$this->assertSame( 3, $state['limits']['max_links'] );
		$this->assertSame( 'auto', $state['questions']['mode'] );
		$this->assertSame( 0, $state['questions']['this_week']['asked'] );
		$this->assertFalse( QAF_Spam_REST::can_view() );
		$GLOBALS['qaf_test_caps'] = array( 'qaf_view_entries' );
		$this->assertTrue( QAF_Spam_REST::can_view() );
	}

	public function test_the_settings_page_keeps_a_saved_key_unless_told_to_remove_it() {
		update_option( QAF_Spam_Settings::OPTION, array( 'oopspam_api_key' => 'kept-key' ) );
		$kept = QAF_Spam_Settings::sanitize_option( array( '_from_page' => '1', 'oopspam_api_key' => '' ) );
		$this->assertSame( 'kept-key', $kept['oopspam_api_key'] );
		$this->assertFalse( $kept['providers']['honeypot'], 'from the page, an unchecked box is off' );
		$new = QAF_Spam_Settings::sanitize_option( array( '_from_page' => '1', 'oopspam_api_key' => 'new-key' ) );
		$this->assertSame( 'new-key', $new['oopspam_api_key'] );
		$gone = QAF_Spam_Settings::sanitize_option( array( '_from_page' => '1', 'oopspam_api_key_clear' => '1' ) );
		$this->assertSame( '', $gone['oopspam_api_key'] );
		$this->assertArrayNotHasKey( '_from_page', $gone );
	}

	private function render_settings( $values = array() ) {
		$qaf_spam_values = QAF_Spam_Settings::sanitize( array_replace_recursive( QAF_Spam_Settings::defaults(), $values ) );
		$qaf_spam_states = QAF_Spam::registry( new QAF_Spam_Settings( $qaf_spam_values ), new QAF_Spam_Memory_Store() )->states();
		$qaf_spam_forms  = array( 7 => 'Contact us', 9 => 'Volunteer sign-up' );
		$qaf_spam_week   = array( 'asked' => 40, 'passed' => 31, 'failed' => 7, 'sent_anyway' => 2 );
		ob_start();
		include QAF_PLUGIN_DIR . 'admin/partials/settings-spam.php';
		return ob_get_clean();
	}

	public function test_every_spam_setting_has_a_label() {
		$html = $this->render_settings();
		preg_match_all( '/<(input|select|textarea)\b[^>]*>/', $html, $controls );
		$labelled = 0;
		foreach ( $controls[0] as $tag ) {
			if ( strpos( $tag, 'type="hidden"' ) !== false ) {
				continue;
			}
			$this->assertMatchesRegularExpression( '/\bid="([^"]+)"/', $tag, $tag );
			preg_match( '/\bid="([^"]+)"/', $tag, $m );
			$this->assertStringContainsString( '<label for="' . $m[1] . '"', $html, 'a label for ' . $m[1] );
			$labelled++;
		}
		$this->assertGreaterThan( 25, $labelled );
		$this->assertSame( substr_count( $html, '<fieldset' ), substr_count( $html, '<legend>' ), 'every group has a legend' );
		$this->assertStringContainsString( 'This week: asked 40 times, 31 answered, 7 wrong answers, 2 sent anyway.', $html );
		$this->assertStringContainsString( 'Volunteer sign-up', $html );
	}

	public function test_the_settings_page_never_shows_the_saved_key() {
		$html = $this->render_settings( array( 'oopspam_api_key' => 'secret-key-999' ) );
		$this->assertStringNotContainsString( 'secret-key-999', $html );
		$this->assertStringContainsString( 'A key is saved.', $html );
		$this->assertStringContainsString( 'Remove the saved OOPSpam key', $html );
	}

	public function test_the_settings_page_shows_each_providers_state() {
		$html = $this->render_settings();
		$this->assertStringContainsString( 'Now: The Akismet plugin is not active.', $html );
		$this->assertStringContainsString( 'Now: No OOPSpam key yet.', $html );
		$this->assertStringContainsString( 'name="qaf_spam_settings[_from_page]"', $html );
	}
}
