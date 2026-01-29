<?php
/**
 * Static Dataset Autocomplete Provider
 *
 * Provides autocomplete from built-in or custom static datasets.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Autocomplete_Static class
 */
class BAF_Autocomplete_Static implements BAF_Autocomplete_Provider {

    /**
     * Built-in datasets
     *
     * @var array
     */
    private $datasets = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_datasets();
    }

    /**
     * Get provider ID
     *
     * @return string
     */
    public function get_id() {
        return 'static';
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Static Dataset', 'bits-accessible-forms' );
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
            'dataset' => array(
                'type'        => 'select',
                'label'       => __( 'Dataset', 'bits-accessible-forms' ),
                'options'     => $this->get_dataset_options(),
                'default'     => '',
                'description' => __( 'Choose a built-in dataset or provide custom options.', 'bits-accessible-forms' ),
            ),
            'custom_data' => array(
                'type'        => 'textarea',
                'label'       => __( 'Custom Data', 'bits-accessible-forms' ),
                'description' => __( 'One option per line, or value|label format.', 'bits-accessible-forms' ),
                'condition'   => array( 'dataset' => 'custom' ),
            ),
            'min_chars' => array(
                'type'    => 'number',
                'label'   => __( 'Minimum Characters', 'bits-accessible-forms' ),
                'default' => 1,
                'min'     => 0,
                'max'     => 10,
            ),
            'max_results' => array(
                'type'    => 'number',
                'label'   => __( 'Maximum Results', 'bits-accessible-forms' ),
                'default' => 10,
                'min'     => 1,
                'max'     => 100,
            ),
            'searchable' => array(
                'type'    => 'checkbox',
                'label'   => __( 'Enable Type-to-Search', 'bits-accessible-forms' ),
                'default' => true,
            ),
        );
    }

    /**
     * Get suggestions
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array Suggestions.
     */
    public function get_suggestions( $query, $options = array() ) {
        $dataset = $options['dataset'] ?? '';
        $max_results = $options['max_results'] ?? 10;
        $min_chars = $options['min_chars'] ?? 1;

        if ( strlen( $query ) < $min_chars ) {
            return array();
        }

        // Get data
        if ( $dataset === 'custom' && ! empty( $options['custom_data'] ) ) {
            $data = $this->parse_custom_data( $options['custom_data'] );
        } elseif ( isset( $this->datasets[ $dataset ] ) ) {
            $data = $this->datasets[ $dataset ];
        } else {
            return array();
        }

        // Filter by query
        $query_lower = strtolower( $query );
        $results = array();

        foreach ( $data as $item ) {
            $label = is_array( $item ) ? ( $item['label'] ?? $item['value'] ) : $item;
            $value = is_array( $item ) ? ( $item['value'] ?? $label ) : $item;

            if ( stripos( $label, $query ) !== false || stripos( $value, $query ) !== false ) {
                $results[] = array(
                    'value' => $value,
                    'label' => $label,
                );

                if ( count( $results ) >= $max_results ) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Get JS config
     *
     * @param array $options Options.
     * @return array JS config.
     */
    public function get_js_config( $options = array() ) {
        $dataset = $options['dataset'] ?? '';

        // For client-side filtering, send the full dataset
        $data = array();
        if ( $dataset === 'custom' && ! empty( $options['custom_data'] ) ) {
            $data = $this->parse_custom_data( $options['custom_data'] );
        } elseif ( isset( $this->datasets[ $dataset ] ) ) {
            $data = $this->datasets[ $dataset ];
        }

        return array(
            'provider'   => 'static',
            'data'       => $data,
            'minChars'   => $options['min_chars'] ?? 1,
            'maxResults' => $options['max_results'] ?? 10,
            'searchable' => $options['searchable'] ?? true,
        );
    }

    /**
     * Get available datasets
     *
     * @return array Dataset IDs and labels.
     */
    public function get_dataset_options() {
        return array(
            ''                  => __( '-- Select Dataset --', 'bits-accessible-forms' ),
            'countries'         => __( 'Countries', 'bits-accessible-forms' ),
            'us_states'         => __( 'US States', 'bits-accessible-forms' ),
            'ca_provinces'      => __( 'Canadian Provinces', 'bits-accessible-forms' ),
            'timezones'         => __( 'Timezones', 'bits-accessible-forms' ),
            'languages'         => __( 'Languages', 'bits-accessible-forms' ),
            'currencies'        => __( 'Currencies', 'bits-accessible-forms' ),
            'custom'            => __( 'Custom (enter below)', 'bits-accessible-forms' ),
        );
    }

    /**
     * Get a specific dataset
     *
     * @param string $id Dataset ID.
     * @return array Dataset data.
     */
    public function get_dataset( $id ) {
        return $this->datasets[ $id ] ?? array();
    }

    /**
     * Load built-in datasets
     */
    private function load_datasets() {
        $this->datasets = array(
            'countries' => $this->get_countries(),
            'us_states' => $this->get_us_states(),
            'ca_provinces' => $this->get_ca_provinces(),
            'timezones' => $this->get_timezones(),
            'languages' => $this->get_languages(),
            'currencies' => $this->get_currencies(),
        );

        /**
         * Filter static datasets
         *
         * @param array $datasets Datasets.
         */
        $this->datasets = apply_filters( 'baf_autocomplete_static_datasets', $this->datasets );
    }

    /**
     * Parse custom data string
     *
     * @param string $data Custom data.
     * @return array Parsed data.
     */
    private function parse_custom_data( $data ) {
        $lines = explode( "\n", $data );
        $result = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }

            if ( strpos( $line, '|' ) !== false ) {
                list( $value, $label ) = array_map( 'trim', explode( '|', $line, 2 ) );
                $result[] = array(
                    'value' => $value,
                    'label' => $label,
                );
            } else {
                $result[] = array(
                    'value' => $line,
                    'label' => $line,
                );
            }
        }

        return $result;
    }

    /**
     * Get countries dataset
     *
     * @return array Countries.
     */
    private function get_countries() {
        return array(
            array( 'value' => 'AF', 'label' => 'Afghanistan' ),
            array( 'value' => 'AL', 'label' => 'Albania' ),
            array( 'value' => 'DZ', 'label' => 'Algeria' ),
            array( 'value' => 'AD', 'label' => 'Andorra' ),
            array( 'value' => 'AO', 'label' => 'Angola' ),
            array( 'value' => 'AG', 'label' => 'Antigua and Barbuda' ),
            array( 'value' => 'AR', 'label' => 'Argentina' ),
            array( 'value' => 'AM', 'label' => 'Armenia' ),
            array( 'value' => 'AU', 'label' => 'Australia' ),
            array( 'value' => 'AT', 'label' => 'Austria' ),
            array( 'value' => 'AZ', 'label' => 'Azerbaijan' ),
            array( 'value' => 'BS', 'label' => 'Bahamas' ),
            array( 'value' => 'BH', 'label' => 'Bahrain' ),
            array( 'value' => 'BD', 'label' => 'Bangladesh' ),
            array( 'value' => 'BB', 'label' => 'Barbados' ),
            array( 'value' => 'BY', 'label' => 'Belarus' ),
            array( 'value' => 'BE', 'label' => 'Belgium' ),
            array( 'value' => 'BZ', 'label' => 'Belize' ),
            array( 'value' => 'BJ', 'label' => 'Benin' ),
            array( 'value' => 'BT', 'label' => 'Bhutan' ),
            array( 'value' => 'BO', 'label' => 'Bolivia' ),
            array( 'value' => 'BA', 'label' => 'Bosnia and Herzegovina' ),
            array( 'value' => 'BW', 'label' => 'Botswana' ),
            array( 'value' => 'BR', 'label' => 'Brazil' ),
            array( 'value' => 'BN', 'label' => 'Brunei' ),
            array( 'value' => 'BG', 'label' => 'Bulgaria' ),
            array( 'value' => 'BF', 'label' => 'Burkina Faso' ),
            array( 'value' => 'BI', 'label' => 'Burundi' ),
            array( 'value' => 'KH', 'label' => 'Cambodia' ),
            array( 'value' => 'CM', 'label' => 'Cameroon' ),
            array( 'value' => 'CA', 'label' => 'Canada' ),
            array( 'value' => 'CV', 'label' => 'Cape Verde' ),
            array( 'value' => 'CF', 'label' => 'Central African Republic' ),
            array( 'value' => 'TD', 'label' => 'Chad' ),
            array( 'value' => 'CL', 'label' => 'Chile' ),
            array( 'value' => 'CN', 'label' => 'China' ),
            array( 'value' => 'CO', 'label' => 'Colombia' ),
            array( 'value' => 'KM', 'label' => 'Comoros' ),
            array( 'value' => 'CG', 'label' => 'Congo' ),
            array( 'value' => 'CR', 'label' => 'Costa Rica' ),
            array( 'value' => 'HR', 'label' => 'Croatia' ),
            array( 'value' => 'CU', 'label' => 'Cuba' ),
            array( 'value' => 'CY', 'label' => 'Cyprus' ),
            array( 'value' => 'CZ', 'label' => 'Czech Republic' ),
            array( 'value' => 'DK', 'label' => 'Denmark' ),
            array( 'value' => 'DJ', 'label' => 'Djibouti' ),
            array( 'value' => 'DM', 'label' => 'Dominica' ),
            array( 'value' => 'DO', 'label' => 'Dominican Republic' ),
            array( 'value' => 'EC', 'label' => 'Ecuador' ),
            array( 'value' => 'EG', 'label' => 'Egypt' ),
            array( 'value' => 'SV', 'label' => 'El Salvador' ),
            array( 'value' => 'GQ', 'label' => 'Equatorial Guinea' ),
            array( 'value' => 'ER', 'label' => 'Eritrea' ),
            array( 'value' => 'EE', 'label' => 'Estonia' ),
            array( 'value' => 'ET', 'label' => 'Ethiopia' ),
            array( 'value' => 'FJ', 'label' => 'Fiji' ),
            array( 'value' => 'FI', 'label' => 'Finland' ),
            array( 'value' => 'FR', 'label' => 'France' ),
            array( 'value' => 'GA', 'label' => 'Gabon' ),
            array( 'value' => 'GM', 'label' => 'Gambia' ),
            array( 'value' => 'GE', 'label' => 'Georgia' ),
            array( 'value' => 'DE', 'label' => 'Germany' ),
            array( 'value' => 'GH', 'label' => 'Ghana' ),
            array( 'value' => 'GR', 'label' => 'Greece' ),
            array( 'value' => 'GD', 'label' => 'Grenada' ),
            array( 'value' => 'GT', 'label' => 'Guatemala' ),
            array( 'value' => 'GN', 'label' => 'Guinea' ),
            array( 'value' => 'GW', 'label' => 'Guinea-Bissau' ),
            array( 'value' => 'GY', 'label' => 'Guyana' ),
            array( 'value' => 'HT', 'label' => 'Haiti' ),
            array( 'value' => 'HN', 'label' => 'Honduras' ),
            array( 'value' => 'HU', 'label' => 'Hungary' ),
            array( 'value' => 'IS', 'label' => 'Iceland' ),
            array( 'value' => 'IN', 'label' => 'India' ),
            array( 'value' => 'ID', 'label' => 'Indonesia' ),
            array( 'value' => 'IR', 'label' => 'Iran' ),
            array( 'value' => 'IQ', 'label' => 'Iraq' ),
            array( 'value' => 'IE', 'label' => 'Ireland' ),
            array( 'value' => 'IL', 'label' => 'Israel' ),
            array( 'value' => 'IT', 'label' => 'Italy' ),
            array( 'value' => 'JM', 'label' => 'Jamaica' ),
            array( 'value' => 'JP', 'label' => 'Japan' ),
            array( 'value' => 'JO', 'label' => 'Jordan' ),
            array( 'value' => 'KZ', 'label' => 'Kazakhstan' ),
            array( 'value' => 'KE', 'label' => 'Kenya' ),
            array( 'value' => 'KI', 'label' => 'Kiribati' ),
            array( 'value' => 'KP', 'label' => 'North Korea' ),
            array( 'value' => 'KR', 'label' => 'South Korea' ),
            array( 'value' => 'KW', 'label' => 'Kuwait' ),
            array( 'value' => 'KG', 'label' => 'Kyrgyzstan' ),
            array( 'value' => 'LA', 'label' => 'Laos' ),
            array( 'value' => 'LV', 'label' => 'Latvia' ),
            array( 'value' => 'LB', 'label' => 'Lebanon' ),
            array( 'value' => 'LS', 'label' => 'Lesotho' ),
            array( 'value' => 'LR', 'label' => 'Liberia' ),
            array( 'value' => 'LY', 'label' => 'Libya' ),
            array( 'value' => 'LI', 'label' => 'Liechtenstein' ),
            array( 'value' => 'LT', 'label' => 'Lithuania' ),
            array( 'value' => 'LU', 'label' => 'Luxembourg' ),
            array( 'value' => 'MK', 'label' => 'North Macedonia' ),
            array( 'value' => 'MG', 'label' => 'Madagascar' ),
            array( 'value' => 'MW', 'label' => 'Malawi' ),
            array( 'value' => 'MY', 'label' => 'Malaysia' ),
            array( 'value' => 'MV', 'label' => 'Maldives' ),
            array( 'value' => 'ML', 'label' => 'Mali' ),
            array( 'value' => 'MT', 'label' => 'Malta' ),
            array( 'value' => 'MH', 'label' => 'Marshall Islands' ),
            array( 'value' => 'MR', 'label' => 'Mauritania' ),
            array( 'value' => 'MU', 'label' => 'Mauritius' ),
            array( 'value' => 'MX', 'label' => 'Mexico' ),
            array( 'value' => 'FM', 'label' => 'Micronesia' ),
            array( 'value' => 'MD', 'label' => 'Moldova' ),
            array( 'value' => 'MC', 'label' => 'Monaco' ),
            array( 'value' => 'MN', 'label' => 'Mongolia' ),
            array( 'value' => 'ME', 'label' => 'Montenegro' ),
            array( 'value' => 'MA', 'label' => 'Morocco' ),
            array( 'value' => 'MZ', 'label' => 'Mozambique' ),
            array( 'value' => 'MM', 'label' => 'Myanmar' ),
            array( 'value' => 'NA', 'label' => 'Namibia' ),
            array( 'value' => 'NR', 'label' => 'Nauru' ),
            array( 'value' => 'NP', 'label' => 'Nepal' ),
            array( 'value' => 'NL', 'label' => 'Netherlands' ),
            array( 'value' => 'NZ', 'label' => 'New Zealand' ),
            array( 'value' => 'NI', 'label' => 'Nicaragua' ),
            array( 'value' => 'NE', 'label' => 'Niger' ),
            array( 'value' => 'NG', 'label' => 'Nigeria' ),
            array( 'value' => 'NO', 'label' => 'Norway' ),
            array( 'value' => 'OM', 'label' => 'Oman' ),
            array( 'value' => 'PK', 'label' => 'Pakistan' ),
            array( 'value' => 'PW', 'label' => 'Palau' ),
            array( 'value' => 'PS', 'label' => 'Palestine' ),
            array( 'value' => 'PA', 'label' => 'Panama' ),
            array( 'value' => 'PG', 'label' => 'Papua New Guinea' ),
            array( 'value' => 'PY', 'label' => 'Paraguay' ),
            array( 'value' => 'PE', 'label' => 'Peru' ),
            array( 'value' => 'PH', 'label' => 'Philippines' ),
            array( 'value' => 'PL', 'label' => 'Poland' ),
            array( 'value' => 'PT', 'label' => 'Portugal' ),
            array( 'value' => 'QA', 'label' => 'Qatar' ),
            array( 'value' => 'RO', 'label' => 'Romania' ),
            array( 'value' => 'RU', 'label' => 'Russia' ),
            array( 'value' => 'RW', 'label' => 'Rwanda' ),
            array( 'value' => 'KN', 'label' => 'Saint Kitts and Nevis' ),
            array( 'value' => 'LC', 'label' => 'Saint Lucia' ),
            array( 'value' => 'VC', 'label' => 'Saint Vincent and the Grenadines' ),
            array( 'value' => 'WS', 'label' => 'Samoa' ),
            array( 'value' => 'SM', 'label' => 'San Marino' ),
            array( 'value' => 'ST', 'label' => 'São Tomé and Príncipe' ),
            array( 'value' => 'SA', 'label' => 'Saudi Arabia' ),
            array( 'value' => 'SN', 'label' => 'Senegal' ),
            array( 'value' => 'RS', 'label' => 'Serbia' ),
            array( 'value' => 'SC', 'label' => 'Seychelles' ),
            array( 'value' => 'SL', 'label' => 'Sierra Leone' ),
            array( 'value' => 'SG', 'label' => 'Singapore' ),
            array( 'value' => 'SK', 'label' => 'Slovakia' ),
            array( 'value' => 'SI', 'label' => 'Slovenia' ),
            array( 'value' => 'SB', 'label' => 'Solomon Islands' ),
            array( 'value' => 'SO', 'label' => 'Somalia' ),
            array( 'value' => 'ZA', 'label' => 'South Africa' ),
            array( 'value' => 'SS', 'label' => 'South Sudan' ),
            array( 'value' => 'ES', 'label' => 'Spain' ),
            array( 'value' => 'LK', 'label' => 'Sri Lanka' ),
            array( 'value' => 'SD', 'label' => 'Sudan' ),
            array( 'value' => 'SR', 'label' => 'Suriname' ),
            array( 'value' => 'SZ', 'label' => 'Eswatini' ),
            array( 'value' => 'SE', 'label' => 'Sweden' ),
            array( 'value' => 'CH', 'label' => 'Switzerland' ),
            array( 'value' => 'SY', 'label' => 'Syria' ),
            array( 'value' => 'TW', 'label' => 'Taiwan' ),
            array( 'value' => 'TJ', 'label' => 'Tajikistan' ),
            array( 'value' => 'TZ', 'label' => 'Tanzania' ),
            array( 'value' => 'TH', 'label' => 'Thailand' ),
            array( 'value' => 'TL', 'label' => 'Timor-Leste' ),
            array( 'value' => 'TG', 'label' => 'Togo' ),
            array( 'value' => 'TO', 'label' => 'Tonga' ),
            array( 'value' => 'TT', 'label' => 'Trinidad and Tobago' ),
            array( 'value' => 'TN', 'label' => 'Tunisia' ),
            array( 'value' => 'TR', 'label' => 'Turkey' ),
            array( 'value' => 'TM', 'label' => 'Turkmenistan' ),
            array( 'value' => 'TV', 'label' => 'Tuvalu' ),
            array( 'value' => 'UG', 'label' => 'Uganda' ),
            array( 'value' => 'UA', 'label' => 'Ukraine' ),
            array( 'value' => 'AE', 'label' => 'United Arab Emirates' ),
            array( 'value' => 'GB', 'label' => 'United Kingdom' ),
            array( 'value' => 'US', 'label' => 'United States' ),
            array( 'value' => 'UY', 'label' => 'Uruguay' ),
            array( 'value' => 'UZ', 'label' => 'Uzbekistan' ),
            array( 'value' => 'VU', 'label' => 'Vanuatu' ),
            array( 'value' => 'VA', 'label' => 'Vatican City' ),
            array( 'value' => 'VE', 'label' => 'Venezuela' ),
            array( 'value' => 'VN', 'label' => 'Vietnam' ),
            array( 'value' => 'YE', 'label' => 'Yemen' ),
            array( 'value' => 'ZM', 'label' => 'Zambia' ),
            array( 'value' => 'ZW', 'label' => 'Zimbabwe' ),
        );
    }

    /**
     * Get US states
     *
     * @return array States.
     */
    private function get_us_states() {
        return array(
            array( 'value' => 'AL', 'label' => 'Alabama' ),
            array( 'value' => 'AK', 'label' => 'Alaska' ),
            array( 'value' => 'AZ', 'label' => 'Arizona' ),
            array( 'value' => 'AR', 'label' => 'Arkansas' ),
            array( 'value' => 'CA', 'label' => 'California' ),
            array( 'value' => 'CO', 'label' => 'Colorado' ),
            array( 'value' => 'CT', 'label' => 'Connecticut' ),
            array( 'value' => 'DE', 'label' => 'Delaware' ),
            array( 'value' => 'FL', 'label' => 'Florida' ),
            array( 'value' => 'GA', 'label' => 'Georgia' ),
            array( 'value' => 'HI', 'label' => 'Hawaii' ),
            array( 'value' => 'ID', 'label' => 'Idaho' ),
            array( 'value' => 'IL', 'label' => 'Illinois' ),
            array( 'value' => 'IN', 'label' => 'Indiana' ),
            array( 'value' => 'IA', 'label' => 'Iowa' ),
            array( 'value' => 'KS', 'label' => 'Kansas' ),
            array( 'value' => 'KY', 'label' => 'Kentucky' ),
            array( 'value' => 'LA', 'label' => 'Louisiana' ),
            array( 'value' => 'ME', 'label' => 'Maine' ),
            array( 'value' => 'MD', 'label' => 'Maryland' ),
            array( 'value' => 'MA', 'label' => 'Massachusetts' ),
            array( 'value' => 'MI', 'label' => 'Michigan' ),
            array( 'value' => 'MN', 'label' => 'Minnesota' ),
            array( 'value' => 'MS', 'label' => 'Mississippi' ),
            array( 'value' => 'MO', 'label' => 'Missouri' ),
            array( 'value' => 'MT', 'label' => 'Montana' ),
            array( 'value' => 'NE', 'label' => 'Nebraska' ),
            array( 'value' => 'NV', 'label' => 'Nevada' ),
            array( 'value' => 'NH', 'label' => 'New Hampshire' ),
            array( 'value' => 'NJ', 'label' => 'New Jersey' ),
            array( 'value' => 'NM', 'label' => 'New Mexico' ),
            array( 'value' => 'NY', 'label' => 'New York' ),
            array( 'value' => 'NC', 'label' => 'North Carolina' ),
            array( 'value' => 'ND', 'label' => 'North Dakota' ),
            array( 'value' => 'OH', 'label' => 'Ohio' ),
            array( 'value' => 'OK', 'label' => 'Oklahoma' ),
            array( 'value' => 'OR', 'label' => 'Oregon' ),
            array( 'value' => 'PA', 'label' => 'Pennsylvania' ),
            array( 'value' => 'RI', 'label' => 'Rhode Island' ),
            array( 'value' => 'SC', 'label' => 'South Carolina' ),
            array( 'value' => 'SD', 'label' => 'South Dakota' ),
            array( 'value' => 'TN', 'label' => 'Tennessee' ),
            array( 'value' => 'TX', 'label' => 'Texas' ),
            array( 'value' => 'UT', 'label' => 'Utah' ),
            array( 'value' => 'VT', 'label' => 'Vermont' ),
            array( 'value' => 'VA', 'label' => 'Virginia' ),
            array( 'value' => 'WA', 'label' => 'Washington' ),
            array( 'value' => 'WV', 'label' => 'West Virginia' ),
            array( 'value' => 'WI', 'label' => 'Wisconsin' ),
            array( 'value' => 'WY', 'label' => 'Wyoming' ),
            array( 'value' => 'DC', 'label' => 'District of Columbia' ),
            array( 'value' => 'PR', 'label' => 'Puerto Rico' ),
            array( 'value' => 'VI', 'label' => 'U.S. Virgin Islands' ),
            array( 'value' => 'GU', 'label' => 'Guam' ),
        );
    }

    /**
     * Get Canadian provinces
     *
     * @return array Provinces.
     */
    private function get_ca_provinces() {
        return array(
            array( 'value' => 'AB', 'label' => 'Alberta' ),
            array( 'value' => 'BC', 'label' => 'British Columbia' ),
            array( 'value' => 'MB', 'label' => 'Manitoba' ),
            array( 'value' => 'NB', 'label' => 'New Brunswick' ),
            array( 'value' => 'NL', 'label' => 'Newfoundland and Labrador' ),
            array( 'value' => 'NS', 'label' => 'Nova Scotia' ),
            array( 'value' => 'NT', 'label' => 'Northwest Territories' ),
            array( 'value' => 'NU', 'label' => 'Nunavut' ),
            array( 'value' => 'ON', 'label' => 'Ontario' ),
            array( 'value' => 'PE', 'label' => 'Prince Edward Island' ),
            array( 'value' => 'QC', 'label' => 'Quebec' ),
            array( 'value' => 'SK', 'label' => 'Saskatchewan' ),
            array( 'value' => 'YT', 'label' => 'Yukon' ),
        );
    }

    /**
     * Get timezones
     *
     * @return array Timezones.
     */
    private function get_timezones() {
        $timezones = array();
        $tz_ids = timezone_identifiers_list();

        foreach ( $tz_ids as $tz ) {
            $timezones[] = array(
                'value' => $tz,
                'label' => str_replace( '_', ' ', $tz ),
            );
        }

        return $timezones;
    }

    /**
     * Get languages
     *
     * @return array Languages.
     */
    private function get_languages() {
        return array(
            array( 'value' => 'en', 'label' => 'English' ),
            array( 'value' => 'es', 'label' => 'Spanish' ),
            array( 'value' => 'fr', 'label' => 'French' ),
            array( 'value' => 'de', 'label' => 'German' ),
            array( 'value' => 'it', 'label' => 'Italian' ),
            array( 'value' => 'pt', 'label' => 'Portuguese' ),
            array( 'value' => 'ru', 'label' => 'Russian' ),
            array( 'value' => 'zh', 'label' => 'Chinese' ),
            array( 'value' => 'ja', 'label' => 'Japanese' ),
            array( 'value' => 'ko', 'label' => 'Korean' ),
            array( 'value' => 'ar', 'label' => 'Arabic' ),
            array( 'value' => 'hi', 'label' => 'Hindi' ),
            array( 'value' => 'bn', 'label' => 'Bengali' ),
            array( 'value' => 'pa', 'label' => 'Punjabi' ),
            array( 'value' => 'te', 'label' => 'Telugu' ),
            array( 'value' => 'mr', 'label' => 'Marathi' ),
            array( 'value' => 'ta', 'label' => 'Tamil' ),
            array( 'value' => 'ur', 'label' => 'Urdu' ),
            array( 'value' => 'vi', 'label' => 'Vietnamese' ),
            array( 'value' => 'th', 'label' => 'Thai' ),
            array( 'value' => 'nl', 'label' => 'Dutch' ),
            array( 'value' => 'pl', 'label' => 'Polish' ),
            array( 'value' => 'uk', 'label' => 'Ukrainian' ),
            array( 'value' => 'tr', 'label' => 'Turkish' ),
            array( 'value' => 'el', 'label' => 'Greek' ),
            array( 'value' => 'he', 'label' => 'Hebrew' ),
            array( 'value' => 'sv', 'label' => 'Swedish' ),
            array( 'value' => 'no', 'label' => 'Norwegian' ),
            array( 'value' => 'da', 'label' => 'Danish' ),
            array( 'value' => 'fi', 'label' => 'Finnish' ),
        );
    }

    /**
     * Get currencies
     *
     * @return array Currencies.
     */
    private function get_currencies() {
        return array(
            array( 'value' => 'USD', 'label' => 'US Dollar (USD)' ),
            array( 'value' => 'EUR', 'label' => 'Euro (EUR)' ),
            array( 'value' => 'GBP', 'label' => 'British Pound (GBP)' ),
            array( 'value' => 'JPY', 'label' => 'Japanese Yen (JPY)' ),
            array( 'value' => 'CNY', 'label' => 'Chinese Yuan (CNY)' ),
            array( 'value' => 'AUD', 'label' => 'Australian Dollar (AUD)' ),
            array( 'value' => 'CAD', 'label' => 'Canadian Dollar (CAD)' ),
            array( 'value' => 'CHF', 'label' => 'Swiss Franc (CHF)' ),
            array( 'value' => 'INR', 'label' => 'Indian Rupee (INR)' ),
            array( 'value' => 'MXN', 'label' => 'Mexican Peso (MXN)' ),
            array( 'value' => 'BRL', 'label' => 'Brazilian Real (BRL)' ),
            array( 'value' => 'KRW', 'label' => 'South Korean Won (KRW)' ),
            array( 'value' => 'SGD', 'label' => 'Singapore Dollar (SGD)' ),
            array( 'value' => 'HKD', 'label' => 'Hong Kong Dollar (HKD)' ),
            array( 'value' => 'NOK', 'label' => 'Norwegian Krone (NOK)' ),
            array( 'value' => 'SEK', 'label' => 'Swedish Krona (SEK)' ),
            array( 'value' => 'DKK', 'label' => 'Danish Krone (DKK)' ),
            array( 'value' => 'NZD', 'label' => 'New Zealand Dollar (NZD)' ),
            array( 'value' => 'ZAR', 'label' => 'South African Rand (ZAR)' ),
            array( 'value' => 'RUB', 'label' => 'Russian Ruble (RUB)' ),
        );
    }
}
