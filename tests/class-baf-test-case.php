<?php
/**
 * Base test case for BAF integration tests
 *
 * @package BAF
 */

/**
 * BAF_Test_Case class
 *
 * Base class for integration tests that require WordPress.
 */
class BAF_Test_Case extends WP_UnitTestCase {

    /**
     * Test form IDs to clean up
     *
     * @var array
     */
    protected $test_form_ids = array();

    /**
     * Test entry IDs to clean up
     *
     * @var array
     */
    protected $test_entry_ids = array();

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Reset any cached data
        if ( function_exists( 'baf_cache' ) ) {
            baf_cache()->flush();
        }
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up test entries
        foreach ( $this->test_entry_ids as $entry_id ) {
            $this->delete_test_entry( $entry_id );
        }

        // Clean up test forms
        foreach ( $this->test_form_ids as $form_id ) {
            $this->delete_test_form( $form_id );
        }

        $this->test_form_ids = array();
        $this->test_entry_ids = array();

        parent::tearDown();
    }

    /**
     * Create a test form
     *
     * @param array $data Form data.
     * @return BAF_Form|int Form object or ID.
     */
    protected function create_test_form( $data = array() ) {
        $form_data = baf_get_test_form_data( $data );

        $form = new BAF_Form();
        $form->title = $form_data['title'];
        $form->slug = $form_data['slug'];
        $form->status = $form_data['status'];
        $form->settings = $form_data['settings'];
        $form->fields = $form_data['fields'];
        $form->save();

        $this->test_form_ids[] = $form->id;

        return $form;
    }

    /**
     * Create a test entry
     *
     * @param int   $form_id Form ID.
     * @param array $data    Entry data.
     * @return BAF_Entry Entry object.
     */
    protected function create_test_entry( $form_id, $data = array() ) {
        $entry_data = baf_get_test_entry_data( $form_id, $data );

        $entry = new BAF_Entry();
        $entry->form_id = $entry_data['form_id'];
        $entry->status = $entry_data['status'];
        $entry->ip_address = $entry_data['ip_address'];
        $entry->user_agent = $entry_data['user_agent'];
        $entry->source_url = $entry_data['source_url'];
        $entry->set_field_values( $entry_data['field_values'] );
        $entry->save();

        $this->test_entry_ids[] = $entry->id;

        return $entry;
    }

    /**
     * Delete test form
     *
     * @param int $form_id Form ID.
     */
    protected function delete_test_form( $form_id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'baf_forms', array( 'id' => $form_id ) );
    }

    /**
     * Delete test entry
     *
     * @param int $entry_id Entry ID.
     */
    protected function delete_test_entry( $entry_id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'baf_entries', array( 'id' => $entry_id ) );
        $wpdb->delete( $wpdb->prefix . 'baf_entry_meta', array( 'entry_id' => $entry_id ) );
    }

    /**
     * Assert form exists in database
     *
     * @param int $form_id Form ID.
     */
    protected function assertFormExists( $form_id ) {
        $form = BAF_Form::find( $form_id );
        $this->assertNotNull( $form, "Form {$form_id} should exist" );
    }

    /**
     * Assert entry exists in database
     *
     * @param int $entry_id Entry ID.
     */
    protected function assertEntryExists( $entry_id ) {
        $entry = BAF_Entry::find( $entry_id );
        $this->assertNotNull( $entry, "Entry {$entry_id} should exist" );
    }

    /**
     * Assert validation error for field
     *
     * @param array  $errors   Validation errors.
     * @param string $field_id Field ID.
     * @param string $message  Optional expected message.
     */
    protected function assertHasValidationError( $errors, $field_id, $message = '' ) {
        $this->assertArrayHasKey( $field_id, $errors, "Field {$field_id} should have validation error" );

        if ( $message ) {
            $this->assertStringContainsString( $message, $errors[ $field_id ] );
        }
    }

    /**
     * Assert no validation errors
     *
     * @param array $errors Validation errors.
     */
    protected function assertNoValidationErrors( $errors ) {
        $this->assertEmpty( $errors, 'Should have no validation errors: ' . print_r( $errors, true ) );
    }

    /**
     * Create admin user and set as current user
     *
     * @return int User ID.
     */
    protected function set_admin_user() {
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );
        return $user_id;
    }

    /**
     * Create subscriber user and set as current user
     *
     * @return int User ID.
     */
    protected function set_subscriber_user() {
        $user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );
        return $user_id;
    }

    /**
     * Set anonymous user (logged out)
     */
    protected function set_anonymous_user() {
        wp_set_current_user( 0 );
    }

    /**
     * Make REST request
     *
     * @param string $route  REST route.
     * @param string $method HTTP method.
     * @param array  $params Request params.
     * @return WP_REST_Response Response object.
     */
    protected function rest_request( $route, $method = 'GET', $params = array() ) {
        $request = new WP_REST_Request( $method, '/baf/v1' . $route );

        if ( $method === 'GET' ) {
            $request->set_query_params( $params );
        } else {
            $request->set_body_params( $params );
        }

        return rest_do_request( $request );
    }
}
