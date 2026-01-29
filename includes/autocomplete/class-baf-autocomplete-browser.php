<?php
/**
 * Browser HTML5 Autocomplete Provider
 *
 * Uses native browser autocomplete tokens.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Autocomplete_Browser class
 */
class BAF_Autocomplete_Browser implements BAF_Autocomplete_Provider {

    /**
     * Available autocomplete tokens grouped by category
     *
     * @var array
     */
    private $tokens = array(
        'personal' => array(
            'name'             => 'Full name',
            'honorific-prefix' => 'Prefix (Mr., Mrs., etc.)',
            'given-name'       => 'First name',
            'additional-name'  => 'Middle name',
            'family-name'      => 'Last name',
            'honorific-suffix' => 'Suffix (Jr., Sr., etc.)',
            'nickname'         => 'Nickname',
            'username'         => 'Username',
            'new-password'     => 'New password',
            'current-password' => 'Current password',
            'organization-title' => 'Job title',
            'organization'     => 'Company/Organization',
            'bday'             => 'Birthday',
            'bday-day'         => 'Birthday day',
            'bday-month'       => 'Birthday month',
            'bday-year'        => 'Birthday year',
            'sex'              => 'Gender',
            'photo'            => 'Photo URL',
            'url'              => 'Website URL',
        ),
        'contact' => array(
            'email'            => 'Email address',
            'tel'              => 'Full phone number',
            'tel-country-code' => 'Country code',
            'tel-national'     => 'National phone number',
            'tel-area-code'    => 'Area code',
            'tel-local'        => 'Local phone number',
            'tel-local-prefix' => 'Phone prefix',
            'tel-local-suffix' => 'Phone suffix',
            'tel-extension'    => 'Phone extension',
            'impp'             => 'IM protocol endpoint',
        ),
        'address' => array(
            'street-address'   => 'Full street address',
            'address-line1'    => 'Address line 1',
            'address-line2'    => 'Address line 2',
            'address-line3'    => 'Address line 3',
            'address-level4'   => 'Level 4 (neighborhood)',
            'address-level3'   => 'Level 3 (district)',
            'address-level2'   => 'Level 2 (city)',
            'address-level1'   => 'Level 1 (state/province)',
            'country'          => 'Country code',
            'country-name'     => 'Country name',
            'postal-code'      => 'Postal/ZIP code',
        ),
        'payment' => array(
            'cc-name'          => 'Cardholder name',
            'cc-given-name'    => 'Cardholder first name',
            'cc-additional-name' => 'Cardholder middle name',
            'cc-family-name'   => 'Cardholder last name',
            'cc-number'        => 'Card number',
            'cc-exp'           => 'Expiration date',
            'cc-exp-month'     => 'Expiration month',
            'cc-exp-year'      => 'Expiration year',
            'cc-csc'           => 'Security code (CVV)',
            'cc-type'          => 'Card type',
            'transaction-currency' => 'Transaction currency',
            'transaction-amount' => 'Transaction amount',
        ),
        'other' => array(
            'language'         => 'Preferred language',
            'off'              => 'Disable autocomplete',
            'on'               => 'Enable autocomplete',
        ),
    );

    /**
     * Get provider ID
     *
     * @return string
     */
    public function get_id() {
        return 'browser';
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Browser Autocomplete', 'bits-accessible-forms' );
    }

    /**
     * Check if available
     *
     * @return bool Always true.
     */
    public function is_available() {
        return true;
    }

    /**
     * Get unavailability reason
     *
     * @return string|null Always null.
     */
    public function get_unavailable_reason() {
        return null;
    }

    /**
     * Get options schema
     *
     * @return array
     */
    public function get_options_schema() {
        return array(
            'token' => array(
                'type'    => 'select',
                'label'   => __( 'Autocomplete Type', 'bits-accessible-forms' ),
                'options' => $this->get_token_options(),
                'default' => '',
            ),
            'section' => array(
                'type'        => 'text',
                'label'       => __( 'Section Name', 'bits-accessible-forms' ),
                'description' => __( 'Optional. Use to group related fields (e.g., "shipping", "billing").', 'bits-accessible-forms' ),
            ),
        );
    }

    /**
     * Get suggestions (not applicable for browser autocomplete)
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array Empty array (browser handles suggestions).
     */
    public function get_suggestions( $query, $options = array() ) {
        // Browser handles autocomplete natively
        return array();
    }

    /**
     * Get JS config
     *
     * @param array $options Options.
     * @return array JS config.
     */
    public function get_js_config( $options = array() ) {
        return array(
            'provider' => 'browser',
            'token'    => $options['token'] ?? '',
            'section'  => $options['section'] ?? '',
        );
    }

    /**
     * Build autocomplete attribute value
     *
     * @param array $options Options.
     * @return string Autocomplete attribute value.
     */
    public function get_autocomplete_value( $options = array() ) {
        $parts = array();

        // Add section if specified
        if ( ! empty( $options['section'] ) ) {
            $parts[] = 'section-' . sanitize_key( $options['section'] );
        }

        // Add token
        if ( ! empty( $options['token'] ) ) {
            $parts[] = $options['token'];
        }

        return implode( ' ', $parts );
    }

    /**
     * Get all available tokens
     *
     * @return array Tokens by category.
     */
    public function get_tokens() {
        return $this->tokens;
    }

    /**
     * Get token options for select
     *
     * @return array Token options.
     */
    public function get_token_options() {
        $options = array(
            '' => __( '-- Select Token --', 'bits-accessible-forms' ),
        );

        foreach ( $this->tokens as $category => $tokens ) {
            $category_label = ucfirst( $category );
            foreach ( $tokens as $token => $label ) {
                $options[ $token ] = sprintf( '%s - %s', $category_label, $label );
            }
        }

        return $options;
    }

    /**
     * Get recommended token for a field type
     *
     * @param string $field_type Field type.
     * @param string $field_id   Field ID.
     * @return string|null Recommended token.
     */
    public function get_recommended_token( $field_type, $field_id = '' ) {
        // Type-based recommendations
        $type_map = array(
            'email'    => 'email',
            'phone'    => 'tel',
            'password' => 'current-password',
            'website'  => 'url',
            'name'     => 'name',
            'address'  => 'street-address',
            'date'     => 'bday',
        );

        if ( isset( $type_map[ $field_type ] ) ) {
            return $type_map[ $field_type ];
        }

        // ID-based recommendations
        $id_patterns = array(
            'first.?name'       => 'given-name',
            'last.?name'        => 'family-name',
            'middle.?name'      => 'additional-name',
            'company|org'       => 'organization',
            'job.?title'        => 'organization-title',
            'city'              => 'address-level2',
            'state|province'    => 'address-level1',
            'zip|postal'        => 'postal-code',
            'country'           => 'country',
            'username'          => 'username',
            'nickname'          => 'nickname',
        );

        $field_id_lower = strtolower( $field_id );
        foreach ( $id_patterns as $pattern => $token ) {
            if ( preg_match( '/' . $pattern . '/i', $field_id_lower ) ) {
                return $token;
            }
        }

        return null;
    }
}
