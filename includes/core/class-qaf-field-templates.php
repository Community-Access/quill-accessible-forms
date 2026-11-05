<?php
/**
 * Field Templates
 *
 * Pre-built field configurations for common form patterns.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Templates class
 */
class QAF_Field_Templates {

    /**
     * Singleton instance
     *
     * @var QAF_Field_Templates
     */
    private static $instance = null;

    /**
     * Registered templates
     *
     * @var array
     */
    private $templates = array();

    /**
     * Get singleton instance
     *
     * @return QAF_Field_Templates
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->register_default_templates();
        
        /**
         * Action to register custom field templates
         *
         * @param QAF_Field_Templates $templates Templates instance
         */
        do_action( 'qaf_register_field_templates', $this );
    }

    /**
     * Register default templates
     */
    private function register_default_templates() {
        // Address templates
        $this->register( 'address_us', array(
            'name'        => __( 'US Address', 'quill-accessible-forms' ),
            'category'    => 'address',
            'description' => __( 'Complete US mailing address with street, city, state, and ZIP', 'quill-accessible-forms' ),
            'icon'        => 'location',
            'fields'      => array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Street Address', 'quill-accessible-forms' ),
                    'placeholder' => __( '123 Main Street', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'address-line1',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Address Line 2', 'quill-accessible-forms' ),
                    'placeholder' => __( 'Apt, Suite, Unit (optional)', 'quill-accessible-forms' ),
                    'autocomplete' => 'address-line2',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'City', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'address-level2',
                ),
                array(
                    'type'    => 'select',
                    'label'   => __( 'State', 'quill-accessible-forms' ),
                    'required' => true,
                    'autocomplete' => 'address-level1',
                    'options' => $this->get_us_states(),
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'ZIP Code', 'quill-accessible-forms' ),
                    'required'    => true,
                    'pattern'     => '\\d{5}(-\\d{4})?',
                    'inputmode'   => 'numeric',
                    'autocomplete' => 'postal-code',
                    'help_text'   => __( '5-digit ZIP or ZIP+4', 'quill-accessible-forms' ),
                ),
            ),
            'bfl' => "# US Address\nstreet_address: text @required @autocomplete(address-line1) \"Street Address\"\naddress_line2: text @autocomplete(address-line2) \"Address Line 2\"\ncity: text @required @autocomplete(address-level2) \"City\"\nstate: select @required @autocomplete(address-level1) \"State\"\n  {US States}\nzip_code: text @required @pattern(\\d{5}(-\\d{4})?) @inputmode(numeric) @autocomplete(postal-code) @help(5-digit ZIP or ZIP+4) \"ZIP Code\"",
        ) );

        $this->register( 'address_international', array(
            'name'        => __( 'International Address', 'quill-accessible-forms' ),
            'category'    => 'address',
            'description' => __( 'Address format supporting international addresses', 'quill-accessible-forms' ),
            'icon'        => 'admin-site',
            'fields'      => array(
                array(
                    'type'    => 'select',
                    'label'   => __( 'Country', 'quill-accessible-forms' ),
                    'required' => true,
                    'autocomplete' => 'country',
                    'options' => $this->get_countries(),
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Address Line 1', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'address-line1',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Address Line 2', 'quill-accessible-forms' ),
                    'autocomplete' => 'address-line2',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'City / Town', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'address-level2',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'State / Province / Region', 'quill-accessible-forms' ),
                    'autocomplete' => 'address-level1',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Postal Code', 'quill-accessible-forms' ),
                    'autocomplete' => 'postal-code',
                ),
            ),
            'bfl' => "# International Address\ncountry: select @required @autocomplete(country) \"Country\"\n  {Countries}\naddress_line1: text @required @autocomplete(address-line1) \"Address Line 1\"\naddress_line2: text @autocomplete(address-line2) \"Address Line 2\"\ncity: text @required @autocomplete(address-level2) \"City / Town\"\nstate_region: text @autocomplete(address-level1) \"State / Province / Region\"\npostal_code: text @autocomplete(postal-code) \"Postal Code\"",
        ) );

        // Contact templates
        $this->register( 'contact_basic', array(
            'name'        => __( 'Basic Contact', 'quill-accessible-forms' ),
            'category'    => 'contact',
            'description' => __( 'Name, email, and phone', 'quill-accessible-forms' ),
            'icon'        => 'id-alt',
            'fields'      => array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Full Name', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'name',
                ),
                array(
                    'type'        => 'email',
                    'label'       => __( 'Email Address', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'email',
                ),
                array(
                    'type'        => 'tel',
                    'label'       => __( 'Phone Number', 'quill-accessible-forms' ),
                    'autocomplete' => 'tel',
                ),
            ),
            'bfl' => "# Contact Information\nfull_name: text @required @autocomplete(name) \"Full Name\"\nemail: email @required @autocomplete(email) \"Email Address\"\nphone: tel @autocomplete(tel) \"Phone Number\"",
        ) );

        $this->register( 'contact_full', array(
            'name'        => __( 'Full Contact', 'quill-accessible-forms' ),
            'category'    => 'contact',
            'description' => __( 'Complete contact info with name parts, email, phone types', 'quill-accessible-forms' ),
            'icon'        => 'businessman',
            'fields'      => array(
                array(
                    'type'    => 'select',
                    'label'   => __( 'Title', 'quill-accessible-forms' ),
                    'autocomplete' => 'honorific-prefix',
                    'options' => array(
                        ''     => __( '(Select)', 'quill-accessible-forms' ),
                        'Mr.'  => __( 'Mr.', 'quill-accessible-forms' ),
                        'Mrs.' => __( 'Mrs.', 'quill-accessible-forms' ),
                        'Ms.'  => __( 'Ms.', 'quill-accessible-forms' ),
                        'Dr.'  => __( 'Dr.', 'quill-accessible-forms' ),
                    ),
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'First Name', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'given-name',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Last Name', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'family-name',
                ),
                array(
                    'type'        => 'email',
                    'label'       => __( 'Email Address', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'email',
                ),
                array(
                    'type'        => 'tel',
                    'label'       => __( 'Primary Phone', 'quill-accessible-forms' ),
                    'autocomplete' => 'tel',
                ),
                array(
                    'type'        => 'tel',
                    'label'       => __( 'Mobile Phone', 'quill-accessible-forms' ),
                    'autocomplete' => 'tel-national',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Company / Organization', 'quill-accessible-forms' ),
                    'autocomplete' => 'organization',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Job Title', 'quill-accessible-forms' ),
                    'autocomplete' => 'organization-title',
                ),
            ),
            'bfl' => "# Full Contact\ntitle: select @autocomplete(honorific-prefix) \"Title\"\n  - (Select)\n  - Mr.\n  - Mrs.\n  - Ms.\n  - Dr.\nfirst_name: text @required @autocomplete(given-name) \"First Name\"\nlast_name: text @required @autocomplete(family-name) \"Last Name\"\nemail: email @required @autocomplete(email) \"Email Address\"\nprimary_phone: tel @autocomplete(tel) \"Primary Phone\"\nmobile_phone: tel @autocomplete(tel-national) \"Mobile Phone\"\ncompany: text @autocomplete(organization) \"Company / Organization\"\njob_title: text @autocomplete(organization-title) \"Job Title\"",
        ) );

        // Consent templates
        $this->register( 'consent_gdpr', array(
            'name'        => __( 'GDPR Consent', 'quill-accessible-forms' ),
            'category'    => 'consent',
            'description' => __( 'GDPR-compliant consent checkboxes', 'quill-accessible-forms' ),
            'icon'        => 'shield',
            'fields'      => array(
                array(
                    'type'     => 'checkbox',
                    'label'    => __( 'Privacy Policy Agreement', 'quill-accessible-forms' ),
                    'required' => true,
                    'options'  => array(
                        'agreed' => __( 'I have read and agree to the Privacy Policy', 'quill-accessible-forms' ),
                    ),
                ),
                array(
                    'type'    => 'checkbox',
                    'label'   => __( 'Marketing Communications', 'quill-accessible-forms' ),
                    'options' => array(
                        'email' => __( 'I agree to receive marketing emails', 'quill-accessible-forms' ),
                        'sms'   => __( 'I agree to receive SMS notifications', 'quill-accessible-forms' ),
                    ),
                    'help_text' => __( 'You can unsubscribe at any time', 'quill-accessible-forms' ),
                ),
                array(
                    'type'     => 'checkbox',
                    'label'    => __( 'Terms of Service', 'quill-accessible-forms' ),
                    'required' => true,
                    'options'  => array(
                        'agreed' => __( 'I agree to the Terms of Service', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'bfl' => "# GDPR Consent\nprivacy_agreement: checkbox @required \"Privacy Policy Agreement\"\n  - I have read and agree to the Privacy Policy\nmarketing: checkbox @help(You can unsubscribe at any time) \"Marketing Communications\"\n  - I agree to receive marketing emails\n  - I agree to receive SMS notifications\nterms_agreement: checkbox @required \"Terms of Service\"\n  - I agree to the Terms of Service",
        ) );

        $this->register( 'consent_simple', array(
            'name'        => __( 'Simple Consent', 'quill-accessible-forms' ),
            'category'    => 'consent',
            'description' => __( 'Basic terms acceptance', 'quill-accessible-forms' ),
            'icon'        => 'yes-alt',
            'fields'      => array(
                array(
                    'type'     => 'checkbox',
                    'label'    => __( 'Agreement', 'quill-accessible-forms' ),
                    'required' => true,
                    'options'  => array(
                        'agreed' => __( 'I agree to the terms and conditions', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'bfl' => "# Agreement\nagreement: checkbox @required \"Agreement\"\n  - I agree to the terms and conditions",
        ) );

        // Payment templates
        $this->register( 'payment_card', array(
            'name'        => __( 'Credit Card', 'quill-accessible-forms' ),
            'category'    => 'payment',
            'description' => __( 'Credit card details (for display/collection, not processing)', 'quill-accessible-forms' ),
            'icon'        => 'money',
            'fields'      => array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Cardholder Name', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'cc-name',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Card Number', 'quill-accessible-forms' ),
                    'required'    => true,
                    'inputmode'   => 'numeric',
                    'autocomplete' => 'cc-number',
                    'pattern'     => '[0-9\\s]{13,19}',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Expiration Date', 'quill-accessible-forms' ),
                    'required'    => true,
                    'placeholder' => 'MM/YY',
                    'autocomplete' => 'cc-exp',
                    'pattern'     => '(0[1-9]|1[0-2])\\/[0-9]{2}',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Security Code (CVV)', 'quill-accessible-forms' ),
                    'required'    => true,
                    'inputmode'   => 'numeric',
                    'autocomplete' => 'cc-csc',
                    'pattern'     => '[0-9]{3,4}',
                    'help_text'   => __( '3 or 4 digit code on back of card', 'quill-accessible-forms' ),
                ),
            ),
            'bfl' => "# Credit Card\ncardholder_name: text @required @autocomplete(cc-name) \"Cardholder Name\"\ncard_number: text @required @inputmode(numeric) @autocomplete(cc-number) @pattern([0-9\\s]{13,19}) \"Card Number\"\nexpiration: text @required @placeholder(MM/YY) @autocomplete(cc-exp) @pattern((0[1-9]|1[0-2])\\/[0-9]{2}) \"Expiration Date\"\ncvv: text @required @inputmode(numeric) @autocomplete(cc-csc) @pattern([0-9]{3,4}) @help(3 or 4 digit code on back of card) \"Security Code (CVV)\"",
        ) );

        $this->register( 'payment_billing', array(
            'name'        => __( 'Billing Information', 'quill-accessible-forms' ),
            'category'    => 'payment',
            'description' => __( 'Billing name and address', 'quill-accessible-forms' ),
            'icon'        => 'clipboard',
            'fields'      => array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Full Name', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'billing name',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Billing Address', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'billing address-line1',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'City', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'billing address-level2',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'State/Province', 'quill-accessible-forms' ),
                    'autocomplete' => 'billing address-level1',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Postal Code', 'quill-accessible-forms' ),
                    'required'    => true,
                    'autocomplete' => 'billing postal-code',
                ),
                array(
                    'type'    => 'select',
                    'label'   => __( 'Country', 'quill-accessible-forms' ),
                    'required' => true,
                    'autocomplete' => 'billing country',
                    'options' => $this->get_countries(),
                ),
            ),
            'bfl' => "# Billing Information\nbilling_name: text @required @autocomplete(billing name) \"Full Name\"\nbilling_address: text @required @autocomplete(billing address-line1) \"Billing Address\"\nbilling_city: text @required @autocomplete(billing address-level2) \"City\"\nbilling_state: text @autocomplete(billing address-level1) \"State/Province\"\nbilling_postal: text @required @autocomplete(billing postal-code) \"Postal Code\"\nbilling_country: select @required @autocomplete(billing country) \"Country\"\n  {Countries}",
        ) );

        // Date/Time templates
        $this->register( 'datetime_appointment', array(
            'name'        => __( 'Appointment Scheduler', 'quill-accessible-forms' ),
            'category'    => 'datetime',
            'description' => __( 'Date and time selection for appointments', 'quill-accessible-forms' ),
            'icon'        => 'calendar-alt',
            'fields'      => array(
                array(
                    'type'     => 'date',
                    'label'    => __( 'Preferred Date', 'quill-accessible-forms' ),
                    'required' => true,
                ),
                array(
                    'type'     => 'time',
                    'label'    => __( 'Preferred Time', 'quill-accessible-forms' ),
                    'required' => true,
                ),
                array(
                    'type'    => 'date',
                    'label'   => __( 'Alternative Date', 'quill-accessible-forms' ),
                ),
                array(
                    'type'    => 'time',
                    'label'   => __( 'Alternative Time', 'quill-accessible-forms' ),
                ),
            ),
            'bfl' => "# Appointment\npreferred_date: date @required \"Preferred Date\"\npreferred_time: time @required \"Preferred Time\"\nalternative_date: date \"Alternative Date\"\nalternative_time: time \"Alternative Time\"",
        ) );

        $this->register( 'datetime_daterange', array(
            'name'        => __( 'Date Range', 'quill-accessible-forms' ),
            'category'    => 'datetime',
            'description' => __( 'Start and end date selection', 'quill-accessible-forms' ),
            'icon'        => 'calendar',
            'fields'      => array(
                array(
                    'type'     => 'date',
                    'label'    => __( 'Start Date', 'quill-accessible-forms' ),
                    'required' => true,
                ),
                array(
                    'type'     => 'date',
                    'label'    => __( 'End Date', 'quill-accessible-forms' ),
                    'required' => true,
                ),
            ),
            'bfl' => "# Date Range\nstart_date: date @required \"Start Date\"\nend_date: date @required \"End Date\"",
        ) );

        // Feedback templates
        $this->register( 'feedback_rating', array(
            'name'        => __( 'Rating & Feedback', 'quill-accessible-forms' ),
            'category'    => 'feedback',
            'description' => __( 'Star rating with optional comment', 'quill-accessible-forms' ),
            'icon'        => 'star-filled',
            'fields'      => array(
                array(
                    'type'     => 'rating',
                    'label'    => __( 'How would you rate your experience?', 'quill-accessible-forms' ),
                    'required' => true,
                    'max'      => 5,
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Additional Comments', 'quill-accessible-forms' ),
                    'placeholder' => __( 'Tell us more about your experience...', 'quill-accessible-forms' ),
                    'rows'        => 4,
                ),
            ),
            'bfl' => "# Feedback\nrating: rating @required @max(5) \"How would you rate your experience?\"\ncomments: textarea @placeholder(Tell us more about your experience...) @rows(4) \"Additional Comments\"",
        ) );

        $this->register( 'feedback_nps', array(
            'name'        => __( 'NPS Survey', 'quill-accessible-forms' ),
            'category'    => 'feedback',
            'description' => __( 'Net Promoter Score style survey', 'quill-accessible-forms' ),
            'icon'        => 'chart-bar',
            'fields'      => array(
                array(
                    'type'     => 'slider',
                    'label'    => __( 'How likely are you to recommend us?', 'quill-accessible-forms' ),
                    'required' => true,
                    'min'      => 0,
                    'max'      => 10,
                    'step'     => 1,
                    'help_text' => __( '0 = Not at all likely, 10 = Extremely likely', 'quill-accessible-forms' ),
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'What is the primary reason for your score?', 'quill-accessible-forms' ),
                    'rows'        => 3,
                ),
            ),
            'bfl' => "# NPS Survey\nnps_score: slider @required @min(0) @max(10) @step(1) @help(0 = Not at all likely, 10 = Extremely likely) \"How likely are you to recommend us?\"\nnps_reason: textarea @rows(3) \"What is the primary reason for your score?\"",
        ) );

        // Demographics templates
        $this->register( 'demographics_basic', array(
            'name'        => __( 'Basic Demographics', 'quill-accessible-forms' ),
            'category'    => 'demographics',
            'description' => __( 'Age, gender, and location', 'quill-accessible-forms' ),
            'icon'        => 'groups',
            'fields'      => array(
                array(
                    'type'    => 'select',
                    'label'   => __( 'Age Range', 'quill-accessible-forms' ),
                    'options' => array(
                        ''      => __( '(Select)', 'quill-accessible-forms' ),
                        '18-24' => __( '18-24', 'quill-accessible-forms' ),
                        '25-34' => __( '25-34', 'quill-accessible-forms' ),
                        '35-44' => __( '35-44', 'quill-accessible-forms' ),
                        '45-54' => __( '45-54', 'quill-accessible-forms' ),
                        '55-64' => __( '55-64', 'quill-accessible-forms' ),
                        '65+'   => __( '65+', 'quill-accessible-forms' ),
                    ),
                ),
                array(
                    'type'    => 'radio',
                    'label'   => __( 'Gender', 'quill-accessible-forms' ),
                    'options' => array(
                        'female'        => __( 'Female', 'quill-accessible-forms' ),
                        'male'          => __( 'Male', 'quill-accessible-forms' ),
                        'non-binary'    => __( 'Non-binary', 'quill-accessible-forms' ),
                        'prefer-not'    => __( 'Prefer not to say', 'quill-accessible-forms' ),
                    ),
                ),
                array(
                    'type'    => 'select',
                    'label'   => __( 'Country', 'quill-accessible-forms' ),
                    'options' => $this->get_countries(),
                ),
            ),
            'bfl' => "# Demographics\nage_range: select \"Age Range\"\n  - (Select)\n  - 18-24\n  - 25-34\n  - 35-44\n  - 45-54\n  - 55-64\n  - 65+\ngender: radio \"Gender\"\n  - Female\n  - Male\n  - Non-binary\n  - Prefer not to say\ncountry: select \"Country\"\n  {Countries}",
        ) );
    }

    /**
     * Register a template
     *
     * @param string $id Template ID.
     * @param array  $template Template configuration.
     */
    public function register( $id, $template ) {
        $this->templates[ $id ] = wp_parse_args( $template, array(
            'name'        => '',
            'category'    => 'general',
            'description' => '',
            'icon'        => 'forms',
            'fields'      => array(),
            'bfl'         => '',
        ) );
    }

    /**
     * Get a template by ID
     *
     * @param string $id Template ID.
     * @return array|null Template or null.
     */
    public function get( $id ) {
        return $this->templates[ $id ] ?? null;
    }

    /**
     * Get all templates
     *
     * @return array All templates.
     */
    public function get_all() {
        return $this->templates;
    }

    /**
     * Get templates by category
     *
     * @param string $category Category slug.
     * @return array Templates in category.
     */
    public function get_by_category( $category ) {
        return array_filter( $this->templates, function( $template ) use ( $category ) {
            return $template['category'] === $category;
        } );
    }

    /**
     * Get all categories
     *
     * @return array Categories with labels.
     */
    public function get_categories() {
        return array(
            'address'      => __( 'Address', 'quill-accessible-forms' ),
            'contact'      => __( 'Contact', 'quill-accessible-forms' ),
            'consent'      => __( 'Consent & Legal', 'quill-accessible-forms' ),
            'payment'      => __( 'Payment', 'quill-accessible-forms' ),
            'datetime'     => __( 'Date & Time', 'quill-accessible-forms' ),
            'feedback'     => __( 'Feedback & Surveys', 'quill-accessible-forms' ),
            'demographics' => __( 'Demographics', 'quill-accessible-forms' ),
            'general'      => __( 'General', 'quill-accessible-forms' ),
        );
    }

    /**
     * Get templates grouped by category
     *
     * @return array Grouped templates.
     */
    public function get_grouped() {
        $grouped = array();
        $categories = $this->get_categories();

        foreach ( $categories as $cat_id => $cat_label ) {
            $templates = $this->get_by_category( $cat_id );
            if ( ! empty( $templates ) ) {
                $grouped[ $cat_id ] = array(
                    'label'     => $cat_label,
                    'templates' => $templates,
                );
            }
        }

        return $grouped;
    }

    /**
     * Get US states list
     *
     * @return array States.
     */
    private function get_us_states() {
        return array(
            ''   => __( '(Select State)', 'quill-accessible-forms' ),
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        );
    }

    /**
     * Get countries list
     *
     * @return array Countries.
     */
    private function get_countries() {
        return array(
            ''   => __( '(Select Country)', 'quill-accessible-forms' ),
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'ZA' => 'South Africa',
            // Add more as needed
        );
    }
}
