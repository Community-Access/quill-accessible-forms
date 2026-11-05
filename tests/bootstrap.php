<?php
/**
 * PHPUnit bootstrap file
 *
 * @package QAF
 */

// Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
}

// Test constants
define( 'QAF_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'QAF_TESTS_DIR', __DIR__ . '/' );
define( 'QAF_TESTING', true );

// Find WordPress test suite
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Give access to tests_add_filter() function
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    require_once $_tests_dir . '/includes/functions.php';

    /**
     * Manually load the plugin being tested.
     */
    function _manually_load_plugin() {
        require QAF_PLUGIN_DIR . 'quill-accessible-forms.php';
    }

    tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

    // Start up the WP testing environment
    require $_tests_dir . '/includes/bootstrap.php';
} else {
    // WP test environment not available, use mock functions
    require_once __DIR__ . '/mocks/wp-functions.php';
}

// Load test base classes
// Only load WP_UnitTestCase-based tests when WP test suite is available.
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    require_once __DIR__ . '/class-qaf-test-case.php';
}
require_once __DIR__ . '/class-qaf-unit-test-case.php';

/**
 * Create test form data
 *
 * @param array $overrides Override default values.
 * @return array Form data.
 */
function qaf_get_test_form_data( $overrides = array() ) {
    return array_merge( array(
        'title'    => 'Test Form',
        'slug'     => 'test-form',
        'status'   => 'active',
        'settings' => array(
            'submit_button_text' => 'Submit',
            'success_message'    => 'Thank you!',
        ),
        'fields'   => array(
            array(
                'id'       => 'name',
                'type'     => 'text',
                'label'    => 'Name',
                'required' => true,
            ),
            array(
                'id'       => 'email',
                'type'     => 'email',
                'label'    => 'Email',
                'required' => true,
            ),
        ),
    ), $overrides );
}

/**
 * Create test entry data
 *
 * @param int   $form_id   Form ID.
 * @param array $overrides Override default values.
 * @return array Entry data.
 */
function qaf_get_test_entry_data( $form_id, $overrides = array() ) {
    return array_merge( array(
        'form_id'      => $form_id,
        'status'       => 'new',
        'ip_address'   => '127.0.0.1',
        'user_agent'   => 'PHPUnit Test',
        'source_url'   => 'https://example.com/test',
        'field_values' => array(
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ),
    ), $overrides );
}

/**
 * Create test BFL source
 *
 * @return string BFL source code.
 */
function qaf_get_test_bfl() {
    return <<<'BFL'
form "Contact Form" {
    page "Contact Info" {
        text name {
            label "Your Name"
            required
            placeholder "Enter your name"
        }
        email email {
            label "Email Address"
            required
        }
        textarea message {
            label "Message"
            required
            rows 5
        }
    }
    submit {
        label "Send Message"
    }
}
BFL;
}
