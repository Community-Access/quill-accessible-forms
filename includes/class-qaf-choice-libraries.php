<?php
/**
 * Choice Libraries
 *
 * Provides predefined choice sets for select, radio, and checkbox fields.
 * Supports cascading relationships between libraries (e.g., Continent → Country → State → City).
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Choice_Libraries class
 */
class QAF_Choice_Libraries {

    /**
     * Registered libraries
     *
     * @var array
     */
    private static $libraries = array();

    /**
     * Cascading relationships
     *
     * @var array
     */
    private static $cascades = array();

    /**
     * Initialize built-in libraries
     */
    public static function init() {
        // Register built-in libraries
        self::register_builtin_libraries();
        
        // Register cascading relationships
        self::register_builtin_cascades();

        // Allow plugins to register additional libraries
        do_action( 'qaf_register_choice_libraries' );
    }

    /**
     * Register a choice library
     *
     * @param string   $id       Library identifier.
     * @param array    $config   Library configuration.
     */
    public static function register( $id, $config ) {
        $defaults = array(
            'label'       => $id,
            'choices'     => array(),
            'callback'    => null,
            'cascade_to'  => array(),
            'cacheable'   => true,
            'cache_ttl'   => DAY_IN_SECONDS,
        );

        self::$libraries[ $id ] = wp_parse_args( $config, $defaults );
    }

    /**
     * Register a cascading relationship
     *
     * @param string $parent_library Parent library ID.
     * @param string $child_library  Child library ID.
     * @param array  $mapping        Value-to-choices mapping or callback.
     */
    public static function register_cascade( $parent_library, $child_library, $mapping ) {
        if ( ! isset( self::$cascades[ $parent_library ] ) ) {
            self::$cascades[ $parent_library ] = array();
        }

        self::$cascades[ $parent_library ][ $child_library ] = $mapping;
    }

    /**
     * Get choices from a library
     *
     * @param string $library_id Library identifier.
     * @param string $parent_value Optional parent value for cascading.
     * @return array Choices array.
     */
    public static function get( $library_id, $parent_value = null ) {
        if ( ! isset( self::$libraries[ $library_id ] ) ) {
            return array();
        }

        $library = self::$libraries[ $library_id ];

        // Check cache first
        if ( $library['cacheable'] ) {
            $cache_key = 'qaf_library_' . $library_id;
            if ( $parent_value !== null ) {
                $cache_key .= '_' . md5( $parent_value );
            }
            
            $cached = wp_cache_get( $cache_key, 'qaf_choices' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Get choices
        $choices = array();

        if ( is_callable( $library['callback'] ) ) {
            $choices = call_user_func( $library['callback'], $parent_value );
        } elseif ( ! empty( $library['choices'] ) ) {
            $choices = $library['choices'];
        }

        // Filter by parent value if cascading
        if ( $parent_value !== null && ! is_callable( $library['callback'] ) ) {
            $choices = self::filter_cascade( $library_id, $parent_value, $choices );
        }

        // Cache results
        if ( $library['cacheable'] ) {
            wp_cache_set( $cache_key, $choices, 'qaf_choices', $library['cache_ttl'] );
        }

        return $choices;
    }

    /**
     * Filter choices by cascade parent value
     *
     * @param string $library_id   Library ID.
     * @param string $parent_value Parent field value.
     * @param array  $choices      All choices.
     * @return array Filtered choices.
     */
    private static function filter_cascade( $library_id, $parent_value, $choices ) {
        // Check if there's a cascade mapping for this library
        foreach ( self::$cascades as $parent_lib => $children ) {
            if ( isset( $children[ $library_id ] ) ) {
                $mapping = $children[ $library_id ];

                if ( is_callable( $mapping ) ) {
                    return call_user_func( $mapping, $parent_value );
                }

                if ( is_array( $mapping ) && isset( $mapping[ $parent_value ] ) ) {
                    return $mapping[ $parent_value ];
                }
            }
        }

        return $choices;
    }

    /**
     * Get cascading children for a library
     *
     * @param string $library_id Parent library ID.
     * @return array Child library IDs.
     */
    public static function get_cascade_children( $library_id ) {
        return isset( self::$cascades[ $library_id ] ) 
            ? array_keys( self::$cascades[ $library_id ] ) 
            : array();
    }

    /**
     * Get all registered libraries
     *
     * @return array Libraries with labels.
     */
    public static function get_all() {
        $result = array();
        foreach ( self::$libraries as $id => $config ) {
            $result[ $id ] = $config['label'];
        }
        return $result;
    }

    /**
     * Check if library exists
     *
     * @param string $library_id Library ID.
     * @return bool True if exists.
     */
    public static function exists( $library_id ) {
        return isset( self::$libraries[ $library_id ] );
    }

    /**
     * Register built-in libraries
     */
    private static function register_builtin_libraries() {
        // =====================================================================
        // GEOGRAPHIC LIBRARIES
        // =====================================================================

        // Continents
        self::register( 'continents', array(
            'label'    => __( 'Continents', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_continents' ),
        ) );

        // World Regions
        self::register( 'world_regions', array(
            'label'    => __( 'World Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_world_regions' ),
        ) );

        // Countries (Complete list)
        self::register( 'countries', array(
            'label'    => __( 'Countries', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_countries' ),
        ) );

        // Countries by Continent (cascading)
        self::register( 'countries_by_continent', array(
            'label'    => __( 'Countries by Continent (cascading)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_countries_by_continent' ),
        ) );

        // US States
        self::register( 'us_states', array(
            'label'    => __( 'US States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_us_states' ),
        ) );

        // US States with Territories
        self::register( 'us_states_territories', array(
            'label'    => __( 'US States & Territories', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_us_states_territories' ),
        ) );

        // Canadian Provinces
        self::register( 'ca_provinces', array(
            'label'    => __( 'Canadian Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ca_provinces' ),
        ) );

        // UK Counties/Regions
        self::register( 'uk_counties', array(
            'label'    => __( 'UK Counties/Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_uk_counties' ),
        ) );

        // Australian States
        self::register( 'au_states', array(
            'label'    => __( 'Australian States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_au_states' ),
        ) );

        // German States (Bundesländer)
        self::register( 'de_states', array(
            'label'    => __( 'German States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_de_states' ),
        ) );

        // French Regions
        self::register( 'fr_regions', array(
            'label'    => __( 'French Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_fr_regions' ),
        ) );

        // Italian Regions
        self::register( 'it_regions', array(
            'label'    => __( 'Italian Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_it_regions' ),
        ) );

        // Spanish Autonomous Communities
        self::register( 'es_regions', array(
            'label'    => __( 'Spanish Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_es_regions' ),
        ) );

        // Dutch Provinces
        self::register( 'nl_provinces', array(
            'label'    => __( 'Dutch Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_nl_provinces' ),
        ) );

        // Belgian Provinces
        self::register( 'be_provinces', array(
            'label'    => __( 'Belgian Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_be_provinces' ),
        ) );

        // Swiss Cantons
        self::register( 'ch_cantons', array(
            'label'    => __( 'Swiss Cantons', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ch_cantons' ),
        ) );

        // Austrian States
        self::register( 'at_states', array(
            'label'    => __( 'Austrian States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_at_states' ),
        ) );

        // Mexican States
        self::register( 'mx_states', array(
            'label'    => __( 'Mexican States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_mx_states' ),
        ) );

        // Brazilian States
        self::register( 'br_states', array(
            'label'    => __( 'Brazilian States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_br_states' ),
        ) );

        // Argentine Provinces
        self::register( 'ar_provinces', array(
            'label'    => __( 'Argentine Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ar_provinces' ),
        ) );

        // Indian States
        self::register( 'in_states', array(
            'label'    => __( 'Indian States', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_in_states' ),
        ) );

        // Chinese Provinces
        self::register( 'cn_provinces', array(
            'label'    => __( 'Chinese Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_cn_provinces' ),
        ) );

        // Japanese Prefectures
        self::register( 'jp_prefectures', array(
            'label'    => __( 'Japanese Prefectures', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_jp_prefectures' ),
        ) );

        // South Korean Provinces
        self::register( 'kr_provinces', array(
            'label'    => __( 'South Korean Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_kr_provinces' ),
        ) );

        // New Zealand Regions
        self::register( 'nz_regions', array(
            'label'    => __( 'New Zealand Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_nz_regions' ),
        ) );

        // South African Provinces
        self::register( 'za_provinces', array(
            'label'    => __( 'South African Provinces', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_za_provinces' ),
        ) );

        // Irish Counties
        self::register( 'ie_counties', array(
            'label'    => __( 'Irish Counties', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ie_counties' ),
        ) );

        // Polish Voivodeships
        self::register( 'pl_voivodeships', array(
            'label'    => __( 'Polish Voivodeships', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_pl_voivodeships' ),
        ) );

        // Russian Federal Subjects
        self::register( 'ru_regions', array(
            'label'    => __( 'Russian Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ru_regions' ),
        ) );

        // States/Provinces (auto-cascade from country)
        self::register( 'states', array(
            'label'    => __( 'States/Provinces (cascading)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_states_for_country' ),
        ) );

        // =====================================================================
        // TIME & DATE LIBRARIES
        // =====================================================================

        // Timezones (grouped by region)
        self::register( 'timezones', array(
            'label'    => __( 'Timezones', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezones' ),
        ) );

        // Timezones by Region (cascading)
        self::register( 'timezones_by_region', array(
            'label'    => __( 'Timezones by Region (cascading)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezones_by_region' ),
        ) );

        // Timezone Regions
        self::register( 'timezone_regions', array(
            'label'    => __( 'Timezone Regions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezone_regions' ),
        ) );

        // UTC Offsets
        self::register( 'utc_offsets', array(
            'label'    => __( 'UTC Offsets', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_utc_offsets' ),
        ) );

        // Days of Week
        self::register( 'days', array(
            'label'   => __( 'Days of Week', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'monday', 'label' => __( 'Monday', 'quill-accessible-forms' ) ),
                array( 'value' => 'tuesday', 'label' => __( 'Tuesday', 'quill-accessible-forms' ) ),
                array( 'value' => 'wednesday', 'label' => __( 'Wednesday', 'quill-accessible-forms' ) ),
                array( 'value' => 'thursday', 'label' => __( 'Thursday', 'quill-accessible-forms' ) ),
                array( 'value' => 'friday', 'label' => __( 'Friday', 'quill-accessible-forms' ) ),
                array( 'value' => 'saturday', 'label' => __( 'Saturday', 'quill-accessible-forms' ) ),
                array( 'value' => 'sunday', 'label' => __( 'Sunday', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Days of Week (Short)
        self::register( 'days_short', array(
            'label'   => __( 'Days of Week (Short)', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mon', 'label' => __( 'Mon', 'quill-accessible-forms' ) ),
                array( 'value' => 'tue', 'label' => __( 'Tue', 'quill-accessible-forms' ) ),
                array( 'value' => 'wed', 'label' => __( 'Wed', 'quill-accessible-forms' ) ),
                array( 'value' => 'thu', 'label' => __( 'Thu', 'quill-accessible-forms' ) ),
                array( 'value' => 'fri', 'label' => __( 'Fri', 'quill-accessible-forms' ) ),
                array( 'value' => 'sat', 'label' => __( 'Sat', 'quill-accessible-forms' ) ),
                array( 'value' => 'sun', 'label' => __( 'Sun', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Weekdays Only
        self::register( 'weekdays', array(
            'label'   => __( 'Weekdays', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'monday', 'label' => __( 'Monday', 'quill-accessible-forms' ) ),
                array( 'value' => 'tuesday', 'label' => __( 'Tuesday', 'quill-accessible-forms' ) ),
                array( 'value' => 'wednesday', 'label' => __( 'Wednesday', 'quill-accessible-forms' ) ),
                array( 'value' => 'thursday', 'label' => __( 'Thursday', 'quill-accessible-forms' ) ),
                array( 'value' => 'friday', 'label' => __( 'Friday', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Months
        self::register( 'months', array(
            'label'   => __( 'Months', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '01', 'label' => __( 'January', 'quill-accessible-forms' ) ),
                array( 'value' => '02', 'label' => __( 'February', 'quill-accessible-forms' ) ),
                array( 'value' => '03', 'label' => __( 'March', 'quill-accessible-forms' ) ),
                array( 'value' => '04', 'label' => __( 'April', 'quill-accessible-forms' ) ),
                array( 'value' => '05', 'label' => __( 'May', 'quill-accessible-forms' ) ),
                array( 'value' => '06', 'label' => __( 'June', 'quill-accessible-forms' ) ),
                array( 'value' => '07', 'label' => __( 'July', 'quill-accessible-forms' ) ),
                array( 'value' => '08', 'label' => __( 'August', 'quill-accessible-forms' ) ),
                array( 'value' => '09', 'label' => __( 'September', 'quill-accessible-forms' ) ),
                array( 'value' => '10', 'label' => __( 'October', 'quill-accessible-forms' ) ),
                array( 'value' => '11', 'label' => __( 'November', 'quill-accessible-forms' ) ),
                array( 'value' => '12', 'label' => __( 'December', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Months (Short)
        self::register( 'months_short', array(
            'label'   => __( 'Months (Short)', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '01', 'label' => __( 'Jan', 'quill-accessible-forms' ) ),
                array( 'value' => '02', 'label' => __( 'Feb', 'quill-accessible-forms' ) ),
                array( 'value' => '03', 'label' => __( 'Mar', 'quill-accessible-forms' ) ),
                array( 'value' => '04', 'label' => __( 'Apr', 'quill-accessible-forms' ) ),
                array( 'value' => '05', 'label' => __( 'May', 'quill-accessible-forms' ) ),
                array( 'value' => '06', 'label' => __( 'Jun', 'quill-accessible-forms' ) ),
                array( 'value' => '07', 'label' => __( 'Jul', 'quill-accessible-forms' ) ),
                array( 'value' => '08', 'label' => __( 'Aug', 'quill-accessible-forms' ) ),
                array( 'value' => '09', 'label' => __( 'Sep', 'quill-accessible-forms' ) ),
                array( 'value' => '10', 'label' => __( 'Oct', 'quill-accessible-forms' ) ),
                array( 'value' => '11', 'label' => __( 'Nov', 'quill-accessible-forms' ) ),
                array( 'value' => '12', 'label' => __( 'Dec', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Quarters
        self::register( 'quarters', array(
            'label'   => __( 'Quarters', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'Q1', 'label' => __( 'Q1 (Jan-Mar)', 'quill-accessible-forms' ) ),
                array( 'value' => 'Q2', 'label' => __( 'Q2 (Apr-Jun)', 'quill-accessible-forms' ) ),
                array( 'value' => 'Q3', 'label' => __( 'Q3 (Jul-Sep)', 'quill-accessible-forms' ) ),
                array( 'value' => 'Q4', 'label' => __( 'Q4 (Oct-Dec)', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Years (dynamic)
        self::register( 'years', array(
            'label'     => __( 'Years', 'quill-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_years' ),
            'cacheable' => false,
        ) );

        // Years (future)
        self::register( 'years_future', array(
            'label'     => __( 'Years (Future)', 'quill-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_years_future' ),
            'cacheable' => false,
        ) );

        // Birth Years
        self::register( 'birth_years', array(
            'label'     => __( 'Birth Years', 'quill-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_birth_years' ),
            'cacheable' => false,
        ) );

        // Time Slots (Hourly)
        self::register( 'time_slots_hourly', array(
            'label'    => __( 'Time Slots (Hourly)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_hourly' ),
        ) );

        // Time Slots (30 min)
        self::register( 'time_slots_30min', array(
            'label'    => __( 'Time Slots (30 min)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_30min' ),
        ) );

        // Time Slots (15 min)
        self::register( 'time_slots_15min', array(
            'label'    => __( 'Time Slots (15 min)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_15min' ),
        ) );

        // =====================================================================
        // CURRENCY & FINANCE LIBRARIES
        // =====================================================================

        // Currencies (Complete ISO 4217)
        self::register( 'currencies', array(
            'label'    => __( 'Currencies', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_currencies' ),
        ) );

        // Major Currencies
        self::register( 'currencies_major', array(
            'label'    => __( 'Major Currencies', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_currencies_major' ),
        ) );

        // Cryptocurrencies
        self::register( 'cryptocurrencies', array(
            'label'    => __( 'Cryptocurrencies', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_cryptocurrencies' ),
        ) );

        // Income Ranges
        self::register( 'income_ranges', array(
            'label'   => __( 'Income Ranges', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_25k', 'label' => __( 'Under $25,000', 'quill-accessible-forms' ) ),
                array( 'value' => '25k_50k', 'label' => __( '$25,000 - $49,999', 'quill-accessible-forms' ) ),
                array( 'value' => '50k_75k', 'label' => __( '$50,000 - $74,999', 'quill-accessible-forms' ) ),
                array( 'value' => '75k_100k', 'label' => __( '$75,000 - $99,999', 'quill-accessible-forms' ) ),
                array( 'value' => '100k_150k', 'label' => __( '$100,000 - $149,999', 'quill-accessible-forms' ) ),
                array( 'value' => '150k_200k', 'label' => __( '$150,000 - $199,999', 'quill-accessible-forms' ) ),
                array( 'value' => '200k_plus', 'label' => __( '$200,000+', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Budget Ranges
        self::register( 'budget_ranges', array(
            'label'   => __( 'Budget Ranges', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_1k', 'label' => __( 'Under $1,000', 'quill-accessible-forms' ) ),
                array( 'value' => '1k_5k', 'label' => __( '$1,000 - $5,000', 'quill-accessible-forms' ) ),
                array( 'value' => '5k_10k', 'label' => __( '$5,000 - $10,000', 'quill-accessible-forms' ) ),
                array( 'value' => '10k_25k', 'label' => __( '$10,000 - $25,000', 'quill-accessible-forms' ) ),
                array( 'value' => '25k_50k', 'label' => __( '$25,000 - $50,000', 'quill-accessible-forms' ) ),
                array( 'value' => '50k_100k', 'label' => __( '$50,000 - $100,000', 'quill-accessible-forms' ) ),
                array( 'value' => '100k_plus', 'label' => __( '$100,000+', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Payment Methods
        self::register( 'payment_methods', array(
            'label'   => __( 'Payment Methods', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'credit_card', 'label' => __( 'Credit Card', 'quill-accessible-forms' ) ),
                array( 'value' => 'debit_card', 'label' => __( 'Debit Card', 'quill-accessible-forms' ) ),
                array( 'value' => 'paypal', 'label' => __( 'PayPal', 'quill-accessible-forms' ) ),
                array( 'value' => 'bank_transfer', 'label' => __( 'Bank Transfer', 'quill-accessible-forms' ) ),
                array( 'value' => 'apple_pay', 'label' => __( 'Apple Pay', 'quill-accessible-forms' ) ),
                array( 'value' => 'google_pay', 'label' => __( 'Google Pay', 'quill-accessible-forms' ) ),
                array( 'value' => 'crypto', 'label' => __( 'Cryptocurrency', 'quill-accessible-forms' ) ),
                array( 'value' => 'cash', 'label' => __( 'Cash', 'quill-accessible-forms' ) ),
                array( 'value' => 'check', 'label' => __( 'Check', 'quill-accessible-forms' ) ),
                array( 'value' => 'invoice', 'label' => __( 'Invoice', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Credit Card Types
        self::register( 'credit_card_types', array(
            'label'   => __( 'Credit Card Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'visa', 'label' => __( 'Visa', 'quill-accessible-forms' ) ),
                array( 'value' => 'mastercard', 'label' => __( 'Mastercard', 'quill-accessible-forms' ) ),
                array( 'value' => 'amex', 'label' => __( 'American Express', 'quill-accessible-forms' ) ),
                array( 'value' => 'discover', 'label' => __( 'Discover', 'quill-accessible-forms' ) ),
                array( 'value' => 'diners', 'label' => __( 'Diners Club', 'quill-accessible-forms' ) ),
                array( 'value' => 'jcb', 'label' => __( 'JCB', 'quill-accessible-forms' ) ),
                array( 'value' => 'unionpay', 'label' => __( 'UnionPay', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // LANGUAGE LIBRARIES
        // =====================================================================

        // Languages (Complete)
        self::register( 'languages', array(
            'label'    => __( 'Languages', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_languages' ),
        ) );

        // Languages (Major)
        self::register( 'languages_major', array(
            'label'    => __( 'Major Languages', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_languages_major' ),
        ) );

        // Language Proficiency
        self::register( 'language_proficiency', array(
            'label'   => __( 'Language Proficiency', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'native', 'label' => __( 'Native', 'quill-accessible-forms' ) ),
                array( 'value' => 'fluent', 'label' => __( 'Fluent', 'quill-accessible-forms' ) ),
                array( 'value' => 'advanced', 'label' => __( 'Advanced', 'quill-accessible-forms' ) ),
                array( 'value' => 'intermediate', 'label' => __( 'Intermediate', 'quill-accessible-forms' ) ),
                array( 'value' => 'basic', 'label' => __( 'Basic', 'quill-accessible-forms' ) ),
                array( 'value' => 'beginner', 'label' => __( 'Beginner', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // CONTACT & COMMUNICATION LIBRARIES
        // =====================================================================

        // Phone Country Codes
        self::register( 'phone_country_codes', array(
            'label'    => __( 'Phone Country Codes', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_phone_country_codes' ),
        ) );

        // Phone Types
        self::register( 'phone_types', array(
            'label'   => __( 'Phone Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mobile', 'label' => __( 'Mobile', 'quill-accessible-forms' ) ),
                array( 'value' => 'home', 'label' => __( 'Home', 'quill-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'fax', 'label' => __( 'Fax', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Email Types
        self::register( 'email_types', array(
            'label'   => __( 'Email Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'personal', 'label' => __( 'Personal', 'quill-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'school', 'label' => __( 'School', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Address Types
        self::register( 'address_types', array(
            'label'   => __( 'Address Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'home', 'label' => __( 'Home', 'quill-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'billing', 'label' => __( 'Billing', 'quill-accessible-forms' ) ),
                array( 'value' => 'shipping', 'label' => __( 'Shipping', 'quill-accessible-forms' ) ),
                array( 'value' => 'mailing', 'label' => __( 'Mailing', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Contact Preferences
        self::register( 'contact_preferences', array(
            'label'   => __( 'Contact Preferences', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'email', 'label' => __( 'Email', 'quill-accessible-forms' ) ),
                array( 'value' => 'phone', 'label' => __( 'Phone', 'quill-accessible-forms' ) ),
                array( 'value' => 'sms', 'label' => __( 'SMS/Text', 'quill-accessible-forms' ) ),
                array( 'value' => 'mail', 'label' => __( 'Mail', 'quill-accessible-forms' ) ),
                array( 'value' => 'no_contact', 'label' => __( 'Do not contact', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Social Media Platforms
        self::register( 'social_platforms', array(
            'label'   => __( 'Social Media Platforms', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'facebook', 'label' => __( 'Facebook', 'quill-accessible-forms' ) ),
                array( 'value' => 'twitter', 'label' => __( 'Twitter/X', 'quill-accessible-forms' ) ),
                array( 'value' => 'instagram', 'label' => __( 'Instagram', 'quill-accessible-forms' ) ),
                array( 'value' => 'linkedin', 'label' => __( 'LinkedIn', 'quill-accessible-forms' ) ),
                array( 'value' => 'youtube', 'label' => __( 'YouTube', 'quill-accessible-forms' ) ),
                array( 'value' => 'tiktok', 'label' => __( 'TikTok', 'quill-accessible-forms' ) ),
                array( 'value' => 'pinterest', 'label' => __( 'Pinterest', 'quill-accessible-forms' ) ),
                array( 'value' => 'snapchat', 'label' => __( 'Snapchat', 'quill-accessible-forms' ) ),
                array( 'value' => 'reddit', 'label' => __( 'Reddit', 'quill-accessible-forms' ) ),
                array( 'value' => 'discord', 'label' => __( 'Discord', 'quill-accessible-forms' ) ),
                array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'quill-accessible-forms' ) ),
                array( 'value' => 'telegram', 'label' => __( 'Telegram', 'quill-accessible-forms' ) ),
                array( 'value' => 'wechat', 'label' => __( 'WeChat', 'quill-accessible-forms' ) ),
                array( 'value' => 'github', 'label' => __( 'GitHub', 'quill-accessible-forms' ) ),
                array( 'value' => 'twitch', 'label' => __( 'Twitch', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // DEMOGRAPHIC LIBRARIES
        // =====================================================================

        // Age Ranges
        self::register( 'age_ranges', array(
            'label'   => __( 'Age Ranges', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_13', 'label' => __( 'Under 13', 'quill-accessible-forms' ) ),
                array( 'value' => '13_17', 'label' => __( '13-17', 'quill-accessible-forms' ) ),
                array( 'value' => '18_24', 'label' => __( '18-24', 'quill-accessible-forms' ) ),
                array( 'value' => '25_34', 'label' => __( '25-34', 'quill-accessible-forms' ) ),
                array( 'value' => '35_44', 'label' => __( '35-44', 'quill-accessible-forms' ) ),
                array( 'value' => '45_54', 'label' => __( '45-54', 'quill-accessible-forms' ) ),
                array( 'value' => '55_64', 'label' => __( '55-64', 'quill-accessible-forms' ) ),
                array( 'value' => '65_74', 'label' => __( '65-74', 'quill-accessible-forms' ) ),
                array( 'value' => '75_plus', 'label' => __( '75+', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Salutations/Titles
        self::register( 'salutations', array(
            'label'   => __( 'Salutations', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mr', 'label' => __( 'Mr.', 'quill-accessible-forms' ) ),
                array( 'value' => 'mrs', 'label' => __( 'Mrs.', 'quill-accessible-forms' ) ),
                array( 'value' => 'ms', 'label' => __( 'Ms.', 'quill-accessible-forms' ) ),
                array( 'value' => 'miss', 'label' => __( 'Miss', 'quill-accessible-forms' ) ),
                array( 'value' => 'mx', 'label' => __( 'Mx.', 'quill-accessible-forms' ) ),
                array( 'value' => 'dr', 'label' => __( 'Dr.', 'quill-accessible-forms' ) ),
                array( 'value' => 'prof', 'label' => __( 'Prof.', 'quill-accessible-forms' ) ),
                array( 'value' => 'rev', 'label' => __( 'Rev.', 'quill-accessible-forms' ) ),
                array( 'value' => 'hon', 'label' => __( 'Hon.', 'quill-accessible-forms' ) ),
                array( 'value' => 'sir', 'label' => __( 'Sir', 'quill-accessible-forms' ) ),
                array( 'value' => 'dame', 'label' => __( 'Dame', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Name Suffixes
        self::register( 'name_suffixes', array(
            'label'   => __( 'Name Suffixes', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'jr', 'label' => __( 'Jr.', 'quill-accessible-forms' ) ),
                array( 'value' => 'sr', 'label' => __( 'Sr.', 'quill-accessible-forms' ) ),
                array( 'value' => 'ii', 'label' => __( 'II', 'quill-accessible-forms' ) ),
                array( 'value' => 'iii', 'label' => __( 'III', 'quill-accessible-forms' ) ),
                array( 'value' => 'iv', 'label' => __( 'IV', 'quill-accessible-forms' ) ),
                array( 'value' => 'v', 'label' => __( 'V', 'quill-accessible-forms' ) ),
                array( 'value' => 'phd', 'label' => __( 'Ph.D.', 'quill-accessible-forms' ) ),
                array( 'value' => 'md', 'label' => __( 'M.D.', 'quill-accessible-forms' ) ),
                array( 'value' => 'esq', 'label' => __( 'Esq.', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Gender
        self::register( 'gender', array(
            'label'   => __( 'Gender', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'male', 'label' => __( 'Male', 'quill-accessible-forms' ) ),
                array( 'value' => 'female', 'label' => __( 'Female', 'quill-accessible-forms' ) ),
                array( 'value' => 'non_binary', 'label' => __( 'Non-binary', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Pronouns
        self::register( 'pronouns', array(
            'label'   => __( 'Pronouns', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'he_him', 'label' => __( 'He/Him', 'quill-accessible-forms' ) ),
                array( 'value' => 'she_her', 'label' => __( 'She/Her', 'quill-accessible-forms' ) ),
                array( 'value' => 'they_them', 'label' => __( 'They/Them', 'quill-accessible-forms' ) ),
                array( 'value' => 'he_they', 'label' => __( 'He/They', 'quill-accessible-forms' ) ),
                array( 'value' => 'she_they', 'label' => __( 'She/They', 'quill-accessible-forms' ) ),
                array( 'value' => 'ze_zir', 'label' => __( 'Ze/Zir', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Marital Status
        self::register( 'marital_status', array(
            'label'   => __( 'Marital Status', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'single', 'label' => __( 'Single', 'quill-accessible-forms' ) ),
                array( 'value' => 'married', 'label' => __( 'Married', 'quill-accessible-forms' ) ),
                array( 'value' => 'domestic_partnership', 'label' => __( 'Domestic Partnership', 'quill-accessible-forms' ) ),
                array( 'value' => 'divorced', 'label' => __( 'Divorced', 'quill-accessible-forms' ) ),
                array( 'value' => 'separated', 'label' => __( 'Separated', 'quill-accessible-forms' ) ),
                array( 'value' => 'widowed', 'label' => __( 'Widowed', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Ethnicity
        self::register( 'ethnicity', array(
            'label'   => __( 'Ethnicity', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'american_indian', 'label' => __( 'American Indian or Alaska Native', 'quill-accessible-forms' ) ),
                array( 'value' => 'asian', 'label' => __( 'Asian', 'quill-accessible-forms' ) ),
                array( 'value' => 'black', 'label' => __( 'Black or African American', 'quill-accessible-forms' ) ),
                array( 'value' => 'hispanic', 'label' => __( 'Hispanic or Latino', 'quill-accessible-forms' ) ),
                array( 'value' => 'middle_eastern', 'label' => __( 'Middle Eastern or North African', 'quill-accessible-forms' ) ),
                array( 'value' => 'pacific_islander', 'label' => __( 'Native Hawaiian or Pacific Islander', 'quill-accessible-forms' ) ),
                array( 'value' => 'white', 'label' => __( 'White', 'quill-accessible-forms' ) ),
                array( 'value' => 'two_or_more', 'label' => __( 'Two or More Races', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Relationship Types
        self::register( 'relationship_types', array(
            'label'   => __( 'Relationship Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'self', 'label' => __( 'Self', 'quill-accessible-forms' ) ),
                array( 'value' => 'spouse', 'label' => __( 'Spouse', 'quill-accessible-forms' ) ),
                array( 'value' => 'partner', 'label' => __( 'Partner', 'quill-accessible-forms' ) ),
                array( 'value' => 'parent', 'label' => __( 'Parent', 'quill-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child', 'quill-accessible-forms' ) ),
                array( 'value' => 'sibling', 'label' => __( 'Sibling', 'quill-accessible-forms' ) ),
                array( 'value' => 'grandparent', 'label' => __( 'Grandparent', 'quill-accessible-forms' ) ),
                array( 'value' => 'grandchild', 'label' => __( 'Grandchild', 'quill-accessible-forms' ) ),
                array( 'value' => 'aunt_uncle', 'label' => __( 'Aunt/Uncle', 'quill-accessible-forms' ) ),
                array( 'value' => 'niece_nephew', 'label' => __( 'Niece/Nephew', 'quill-accessible-forms' ) ),
                array( 'value' => 'cousin', 'label' => __( 'Cousin', 'quill-accessible-forms' ) ),
                array( 'value' => 'friend', 'label' => __( 'Friend', 'quill-accessible-forms' ) ),
                array( 'value' => 'colleague', 'label' => __( 'Colleague', 'quill-accessible-forms' ) ),
                array( 'value' => 'guardian', 'label' => __( 'Guardian', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // EDUCATION LIBRARIES
        // =====================================================================

        // Education Levels
        self::register( 'education', array(
            'label'   => __( 'Education Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'no_formal', 'label' => __( 'No Formal Education', 'quill-accessible-forms' ) ),
                array( 'value' => 'elementary', 'label' => __( 'Elementary School', 'quill-accessible-forms' ) ),
                array( 'value' => 'middle_school', 'label' => __( 'Middle School', 'quill-accessible-forms' ) ),
                array( 'value' => 'some_high_school', 'label' => __( 'Some High School', 'quill-accessible-forms' ) ),
                array( 'value' => 'high_school', 'label' => __( 'High School Diploma/GED', 'quill-accessible-forms' ) ),
                array( 'value' => 'vocational', 'label' => __( 'Vocational Training', 'quill-accessible-forms' ) ),
                array( 'value' => 'some_college', 'label' => __( 'Some College', 'quill-accessible-forms' ) ),
                array( 'value' => 'associate', 'label' => __( 'Associate Degree', 'quill-accessible-forms' ) ),
                array( 'value' => 'bachelor', 'label' => __( 'Bachelor\'s Degree', 'quill-accessible-forms' ) ),
                array( 'value' => 'master', 'label' => __( 'Master\'s Degree', 'quill-accessible-forms' ) ),
                array( 'value' => 'professional', 'label' => __( 'Professional Degree (JD, MD, etc.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'doctorate', 'label' => __( 'Doctorate (Ph.D., Ed.D., etc.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Degree Types
        self::register( 'degree_types', array(
            'label'   => __( 'Degree Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'aa', 'label' => __( 'Associate of Arts (A.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'as', 'label' => __( 'Associate of Science (A.S.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'aas', 'label' => __( 'Associate of Applied Science (A.A.S.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'ba', 'label' => __( 'Bachelor of Arts (B.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'bs', 'label' => __( 'Bachelor of Science (B.S.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'bba', 'label' => __( 'Bachelor of Business Administration (B.B.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'bfa', 'label' => __( 'Bachelor of Fine Arts (B.F.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'ma', 'label' => __( 'Master of Arts (M.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'ms', 'label' => __( 'Master of Science (M.S.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'mba', 'label' => __( 'Master of Business Administration (M.B.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'mfa', 'label' => __( 'Master of Fine Arts (M.F.A.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'med', 'label' => __( 'Master of Education (M.Ed.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'jd', 'label' => __( 'Juris Doctor (J.D.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'md', 'label' => __( 'Doctor of Medicine (M.D.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'do', 'label' => __( 'Doctor of Osteopathic Medicine (D.O.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'phd', 'label' => __( 'Doctor of Philosophy (Ph.D.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'edd', 'label' => __( 'Doctor of Education (Ed.D.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Academic Years
        self::register( 'academic_years', array(
            'label'   => __( 'Academic Years', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'freshman', 'label' => __( 'Freshman (1st Year)', 'quill-accessible-forms' ) ),
                array( 'value' => 'sophomore', 'label' => __( 'Sophomore (2nd Year)', 'quill-accessible-forms' ) ),
                array( 'value' => 'junior', 'label' => __( 'Junior (3rd Year)', 'quill-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior (4th Year)', 'quill-accessible-forms' ) ),
                array( 'value' => 'graduate', 'label' => __( 'Graduate Student', 'quill-accessible-forms' ) ),
                array( 'value' => 'postgraduate', 'label' => __( 'Postgraduate', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Grade Levels
        self::register( 'grade_levels', array(
            'label'   => __( 'Grade Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'pre_k', 'label' => __( 'Pre-Kindergarten', 'quill-accessible-forms' ) ),
                array( 'value' => 'kindergarten', 'label' => __( 'Kindergarten', 'quill-accessible-forms' ) ),
                array( 'value' => '1st', 'label' => __( '1st Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '2nd', 'label' => __( '2nd Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '3rd', 'label' => __( '3rd Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '4th', 'label' => __( '4th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '5th', 'label' => __( '5th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '6th', 'label' => __( '6th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '7th', 'label' => __( '7th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '8th', 'label' => __( '8th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '9th', 'label' => __( '9th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '10th', 'label' => __( '10th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '11th', 'label' => __( '11th Grade', 'quill-accessible-forms' ) ),
                array( 'value' => '12th', 'label' => __( '12th Grade', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Fields of Study
        self::register( 'fields_of_study', array(
            'label'    => __( 'Fields of Study', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_fields_of_study' ),
        ) );

        // =====================================================================
        // EMPLOYMENT LIBRARIES
        // =====================================================================

        // Employment Status
        self::register( 'employment_status', array(
            'label'   => __( 'Employment Status', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'full_time', 'label' => __( 'Full-time Employee', 'quill-accessible-forms' ) ),
                array( 'value' => 'part_time', 'label' => __( 'Part-time Employee', 'quill-accessible-forms' ) ),
                array( 'value' => 'contract', 'label' => __( 'Contractor', 'quill-accessible-forms' ) ),
                array( 'value' => 'freelance', 'label' => __( 'Freelancer', 'quill-accessible-forms' ) ),
                array( 'value' => 'self_employed', 'label' => __( 'Self-employed', 'quill-accessible-forms' ) ),
                array( 'value' => 'business_owner', 'label' => __( 'Business Owner', 'quill-accessible-forms' ) ),
                array( 'value' => 'intern', 'label' => __( 'Intern', 'quill-accessible-forms' ) ),
                array( 'value' => 'temporary', 'label' => __( 'Temporary/Seasonal', 'quill-accessible-forms' ) ),
                array( 'value' => 'unemployed_seeking', 'label' => __( 'Unemployed - Seeking Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'unemployed_not_seeking', 'label' => __( 'Unemployed - Not Seeking Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'quill-accessible-forms' ) ),
                array( 'value' => 'homemaker', 'label' => __( 'Homemaker', 'quill-accessible-forms' ) ),
                array( 'value' => 'retired', 'label' => __( 'Retired', 'quill-accessible-forms' ) ),
                array( 'value' => 'disabled', 'label' => __( 'Unable to Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Job Levels
        self::register( 'job_levels', array(
            'label'   => __( 'Job Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'entry', 'label' => __( 'Entry Level', 'quill-accessible-forms' ) ),
                array( 'value' => 'junior', 'label' => __( 'Junior', 'quill-accessible-forms' ) ),
                array( 'value' => 'mid', 'label' => __( 'Mid-Level', 'quill-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior', 'quill-accessible-forms' ) ),
                array( 'value' => 'lead', 'label' => __( 'Lead/Principal', 'quill-accessible-forms' ) ),
                array( 'value' => 'manager', 'label' => __( 'Manager', 'quill-accessible-forms' ) ),
                array( 'value' => 'senior_manager', 'label' => __( 'Senior Manager', 'quill-accessible-forms' ) ),
                array( 'value' => 'director', 'label' => __( 'Director', 'quill-accessible-forms' ) ),
                array( 'value' => 'vp', 'label' => __( 'Vice President', 'quill-accessible-forms' ) ),
                array( 'value' => 'svp', 'label' => __( 'Senior Vice President', 'quill-accessible-forms' ) ),
                array( 'value' => 'c_level', 'label' => __( 'C-Level (CEO, CTO, CFO, etc.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'owner', 'label' => __( 'Owner/Founder', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Work Schedule Types
        self::register( 'work_schedules', array(
            'label'   => __( 'Work Schedule Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'full_time', 'label' => __( 'Full-time (40+ hrs/week)', 'quill-accessible-forms' ) ),
                array( 'value' => 'part_time', 'label' => __( 'Part-time', 'quill-accessible-forms' ) ),
                array( 'value' => 'flexible', 'label' => __( 'Flexible Hours', 'quill-accessible-forms' ) ),
                array( 'value' => 'shift', 'label' => __( 'Shift Work', 'quill-accessible-forms' ) ),
                array( 'value' => 'rotating', 'label' => __( 'Rotating Shift', 'quill-accessible-forms' ) ),
                array( 'value' => 'on_call', 'label' => __( 'On-Call', 'quill-accessible-forms' ) ),
                array( 'value' => 'compressed', 'label' => __( 'Compressed Work Week', 'quill-accessible-forms' ) ),
                array( 'value' => 'seasonal', 'label' => __( 'Seasonal', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Work Locations
        self::register( 'work_locations', array(
            'label'   => __( 'Work Location Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'onsite', 'label' => __( 'On-site', 'quill-accessible-forms' ) ),
                array( 'value' => 'remote', 'label' => __( 'Remote', 'quill-accessible-forms' ) ),
                array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'quill-accessible-forms' ) ),
                array( 'value' => 'field', 'label' => __( 'Field/Travel', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Industries (expanded)
        self::register( 'industries', array(
            'label'    => __( 'Industries', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_industries' ),
        ) );

        // Job Functions/Departments
        self::register( 'job_functions', array(
            'label'    => __( 'Job Functions', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_job_functions' ),
        ) );

        // Company Sizes
        self::register( 'company_sizes', array(
            'label'   => __( 'Company Sizes', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => __( '1 (Solo)', 'quill-accessible-forms' ) ),
                array( 'value' => '2_10', 'label' => __( '2-10', 'quill-accessible-forms' ) ),
                array( 'value' => '11_50', 'label' => __( '11-50', 'quill-accessible-forms' ) ),
                array( 'value' => '51_200', 'label' => __( '51-200', 'quill-accessible-forms' ) ),
                array( 'value' => '201_500', 'label' => __( '201-500', 'quill-accessible-forms' ) ),
                array( 'value' => '501_1000', 'label' => __( '501-1,000', 'quill-accessible-forms' ) ),
                array( 'value' => '1001_5000', 'label' => __( '1,001-5,000', 'quill-accessible-forms' ) ),
                array( 'value' => '5001_10000', 'label' => __( '5,001-10,000', 'quill-accessible-forms' ) ),
                array( 'value' => '10001_plus', 'label' => __( '10,000+', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Company Types
        self::register( 'company_types', array(
            'label'   => __( 'Company Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'public', 'label' => __( 'Public Company', 'quill-accessible-forms' ) ),
                array( 'value' => 'private', 'label' => __( 'Private Company', 'quill-accessible-forms' ) ),
                array( 'value' => 'startup', 'label' => __( 'Startup', 'quill-accessible-forms' ) ),
                array( 'value' => 'nonprofit', 'label' => __( 'Non-profit', 'quill-accessible-forms' ) ),
                array( 'value' => 'government', 'label' => __( 'Government', 'quill-accessible-forms' ) ),
                array( 'value' => 'education', 'label' => __( 'Educational Institution', 'quill-accessible-forms' ) ),
                array( 'value' => 'sole_proprietor', 'label' => __( 'Sole Proprietorship', 'quill-accessible-forms' ) ),
                array( 'value' => 'partnership', 'label' => __( 'Partnership', 'quill-accessible-forms' ) ),
                array( 'value' => 'llc', 'label' => __( 'LLC', 'quill-accessible-forms' ) ),
                array( 'value' => 'corporation', 'label' => __( 'Corporation', 'quill-accessible-forms' ) ),
                array( 'value' => 'cooperative', 'label' => __( 'Cooperative', 'quill-accessible-forms' ) ),
                array( 'value' => 'franchise', 'label' => __( 'Franchise', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Experience Years
        self::register( 'experience_years', array(
            'label'   => __( 'Years of Experience', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '0', 'label' => __( 'No Experience', 'quill-accessible-forms' ) ),
                array( 'value' => 'less_1', 'label' => __( 'Less than 1 year', 'quill-accessible-forms' ) ),
                array( 'value' => '1_2', 'label' => __( '1-2 years', 'quill-accessible-forms' ) ),
                array( 'value' => '3_5', 'label' => __( '3-5 years', 'quill-accessible-forms' ) ),
                array( 'value' => '6_10', 'label' => __( '6-10 years', 'quill-accessible-forms' ) ),
                array( 'value' => '11_15', 'label' => __( '11-15 years', 'quill-accessible-forms' ) ),
                array( 'value' => '16_20', 'label' => __( '16-20 years', 'quill-accessible-forms' ) ),
                array( 'value' => '20_plus', 'label' => __( '20+ years', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // PRODUCT & E-COMMERCE LIBRARIES
        // =====================================================================

        // Clothing Sizes
        self::register( 'clothing_sizes', array(
            'label'   => __( 'Clothing Sizes', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'xxs', 'label' => __( 'XXS', 'quill-accessible-forms' ) ),
                array( 'value' => 'xs', 'label' => __( 'XS', 'quill-accessible-forms' ) ),
                array( 'value' => 's', 'label' => __( 'S', 'quill-accessible-forms' ) ),
                array( 'value' => 'm', 'label' => __( 'M', 'quill-accessible-forms' ) ),
                array( 'value' => 'l', 'label' => __( 'L', 'quill-accessible-forms' ) ),
                array( 'value' => 'xl', 'label' => __( 'XL', 'quill-accessible-forms' ) ),
                array( 'value' => 'xxl', 'label' => __( 'XXL', 'quill-accessible-forms' ) ),
                array( 'value' => 'xxxl', 'label' => __( 'XXXL', 'quill-accessible-forms' ) ),
                array( 'value' => '4xl', 'label' => __( '4XL', 'quill-accessible-forms' ) ),
                array( 'value' => '5xl', 'label' => __( '5XL', 'quill-accessible-forms' ) ),
            ),
        ) );

        // T-Shirt Sizes
        self::register( 'tshirt_sizes', array(
            'label'   => __( 'T-Shirt Sizes', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'xs', 'label' => __( 'XS (Extra Small)', 'quill-accessible-forms' ) ),
                array( 'value' => 's', 'label' => __( 'S (Small)', 'quill-accessible-forms' ) ),
                array( 'value' => 'm', 'label' => __( 'M (Medium)', 'quill-accessible-forms' ) ),
                array( 'value' => 'l', 'label' => __( 'L (Large)', 'quill-accessible-forms' ) ),
                array( 'value' => 'xl', 'label' => __( 'XL (Extra Large)', 'quill-accessible-forms' ) ),
                array( 'value' => '2xl', 'label' => __( '2XL', 'quill-accessible-forms' ) ),
                array( 'value' => '3xl', 'label' => __( '3XL', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Shoe Sizes (US)
        self::register( 'shoe_sizes_us', array(
            'label'    => __( 'Shoe Sizes (US)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_shoe_sizes_us' ),
        ) );

        // Shoe Sizes (EU)
        self::register( 'shoe_sizes_eu', array(
            'label'    => __( 'Shoe Sizes (EU)', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_shoe_sizes_eu' ),
        ) );

        // Product Conditions
        self::register( 'product_conditions', array(
            'label'   => __( 'Product Conditions', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'new', 'label' => __( 'New', 'quill-accessible-forms' ) ),
                array( 'value' => 'new_open_box', 'label' => __( 'New (Open Box)', 'quill-accessible-forms' ) ),
                array( 'value' => 'refurbished', 'label' => __( 'Refurbished', 'quill-accessible-forms' ) ),
                array( 'value' => 'like_new', 'label' => __( 'Like New', 'quill-accessible-forms' ) ),
                array( 'value' => 'excellent', 'label' => __( 'Excellent', 'quill-accessible-forms' ) ),
                array( 'value' => 'good', 'label' => __( 'Good', 'quill-accessible-forms' ) ),
                array( 'value' => 'fair', 'label' => __( 'Fair', 'quill-accessible-forms' ) ),
                array( 'value' => 'poor', 'label' => __( 'Poor', 'quill-accessible-forms' ) ),
                array( 'value' => 'for_parts', 'label' => __( 'For Parts/Not Working', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Shipping Methods
        self::register( 'shipping_methods', array(
            'label'   => __( 'Shipping Methods', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'standard', 'label' => __( 'Standard Shipping', 'quill-accessible-forms' ) ),
                array( 'value' => 'express', 'label' => __( 'Express Shipping', 'quill-accessible-forms' ) ),
                array( 'value' => 'overnight', 'label' => __( 'Overnight/Next Day', 'quill-accessible-forms' ) ),
                array( 'value' => 'two_day', 'label' => __( '2-Day Shipping', 'quill-accessible-forms' ) ),
                array( 'value' => 'economy', 'label' => __( 'Economy Shipping', 'quill-accessible-forms' ) ),
                array( 'value' => 'freight', 'label' => __( 'Freight', 'quill-accessible-forms' ) ),
                array( 'value' => 'local_pickup', 'label' => __( 'Local Pickup', 'quill-accessible-forms' ) ),
                array( 'value' => 'international', 'label' => __( 'International', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // HEALTH & MEDICAL LIBRARIES
        // =====================================================================

        // Blood Types
        self::register( 'blood_types', array(
            'label'   => __( 'Blood Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'a_positive', 'label' => __( 'A+', 'quill-accessible-forms' ) ),
                array( 'value' => 'a_negative', 'label' => __( 'A-', 'quill-accessible-forms' ) ),
                array( 'value' => 'b_positive', 'label' => __( 'B+', 'quill-accessible-forms' ) ),
                array( 'value' => 'b_negative', 'label' => __( 'B-', 'quill-accessible-forms' ) ),
                array( 'value' => 'ab_positive', 'label' => __( 'AB+', 'quill-accessible-forms' ) ),
                array( 'value' => 'ab_negative', 'label' => __( 'AB-', 'quill-accessible-forms' ) ),
                array( 'value' => 'o_positive', 'label' => __( 'O+', 'quill-accessible-forms' ) ),
                array( 'value' => 'o_negative', 'label' => __( 'O-', 'quill-accessible-forms' ) ),
                array( 'value' => 'unknown', 'label' => __( 'Unknown', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Dietary Restrictions
        self::register( 'dietary_restrictions', array(
            'label'   => __( 'Dietary Restrictions', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'none', 'label' => __( 'None', 'quill-accessible-forms' ) ),
                array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'quill-accessible-forms' ) ),
                array( 'value' => 'vegan', 'label' => __( 'Vegan', 'quill-accessible-forms' ) ),
                array( 'value' => 'pescatarian', 'label' => __( 'Pescatarian', 'quill-accessible-forms' ) ),
                array( 'value' => 'gluten_free', 'label' => __( 'Gluten-Free', 'quill-accessible-forms' ) ),
                array( 'value' => 'dairy_free', 'label' => __( 'Dairy-Free', 'quill-accessible-forms' ) ),
                array( 'value' => 'nut_free', 'label' => __( 'Nut-Free', 'quill-accessible-forms' ) ),
                array( 'value' => 'kosher', 'label' => __( 'Kosher', 'quill-accessible-forms' ) ),
                array( 'value' => 'halal', 'label' => __( 'Halal', 'quill-accessible-forms' ) ),
                array( 'value' => 'keto', 'label' => __( 'Keto', 'quill-accessible-forms' ) ),
                array( 'value' => 'paleo', 'label' => __( 'Paleo', 'quill-accessible-forms' ) ),
                array( 'value' => 'low_sodium', 'label' => __( 'Low Sodium', 'quill-accessible-forms' ) ),
                array( 'value' => 'diabetic', 'label' => __( 'Diabetic-Friendly', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Common Allergies
        self::register( 'allergies', array(
            'label'   => __( 'Common Allergies', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'none', 'label' => __( 'None', 'quill-accessible-forms' ) ),
                array( 'value' => 'peanuts', 'label' => __( 'Peanuts', 'quill-accessible-forms' ) ),
                array( 'value' => 'tree_nuts', 'label' => __( 'Tree Nuts', 'quill-accessible-forms' ) ),
                array( 'value' => 'milk', 'label' => __( 'Milk/Dairy', 'quill-accessible-forms' ) ),
                array( 'value' => 'eggs', 'label' => __( 'Eggs', 'quill-accessible-forms' ) ),
                array( 'value' => 'wheat', 'label' => __( 'Wheat', 'quill-accessible-forms' ) ),
                array( 'value' => 'soy', 'label' => __( 'Soy', 'quill-accessible-forms' ) ),
                array( 'value' => 'fish', 'label' => __( 'Fish', 'quill-accessible-forms' ) ),
                array( 'value' => 'shellfish', 'label' => __( 'Shellfish', 'quill-accessible-forms' ) ),
                array( 'value' => 'sesame', 'label' => __( 'Sesame', 'quill-accessible-forms' ) ),
                array( 'value' => 'latex', 'label' => __( 'Latex', 'quill-accessible-forms' ) ),
                array( 'value' => 'penicillin', 'label' => __( 'Penicillin', 'quill-accessible-forms' ) ),
                array( 'value' => 'sulfa', 'label' => __( 'Sulfa Drugs', 'quill-accessible-forms' ) ),
                array( 'value' => 'aspirin', 'label' => __( 'Aspirin', 'quill-accessible-forms' ) ),
                array( 'value' => 'insect_stings', 'label' => __( 'Insect Stings', 'quill-accessible-forms' ) ),
                array( 'value' => 'pollen', 'label' => __( 'Pollen', 'quill-accessible-forms' ) ),
                array( 'value' => 'pet_dander', 'label' => __( 'Pet Dander', 'quill-accessible-forms' ) ),
                array( 'value' => 'dust_mites', 'label' => __( 'Dust Mites', 'quill-accessible-forms' ) ),
                array( 'value' => 'mold', 'label' => __( 'Mold', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Medical Specialties
        self::register( 'medical_specialties', array(
            'label'    => __( 'Medical Specialties', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_medical_specialties' ),
        ) );

        // Insurance Types
        self::register( 'insurance_types', array(
            'label'   => __( 'Insurance Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'health', 'label' => __( 'Health Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'dental', 'label' => __( 'Dental Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'vision', 'label' => __( 'Vision Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'life', 'label' => __( 'Life Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'auto', 'label' => __( 'Auto Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'home', 'label' => __( 'Homeowners Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'renters', 'label' => __( 'Renters Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'disability', 'label' => __( 'Disability Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'travel', 'label' => __( 'Travel Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'pet', 'label' => __( 'Pet Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'business', 'label' => __( 'Business Insurance', 'quill-accessible-forms' ) ),
                array( 'value' => 'liability', 'label' => __( 'Liability Insurance', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // TRAVEL & HOSPITALITY LIBRARIES
        // =====================================================================

        // Room Types
        self::register( 'room_types', array(
            'label'   => __( 'Room Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'single', 'label' => __( 'Single Room', 'quill-accessible-forms' ) ),
                array( 'value' => 'double', 'label' => __( 'Double Room', 'quill-accessible-forms' ) ),
                array( 'value' => 'twin', 'label' => __( 'Twin Room', 'quill-accessible-forms' ) ),
                array( 'value' => 'queen', 'label' => __( 'Queen Room', 'quill-accessible-forms' ) ),
                array( 'value' => 'king', 'label' => __( 'King Room', 'quill-accessible-forms' ) ),
                array( 'value' => 'suite', 'label' => __( 'Suite', 'quill-accessible-forms' ) ),
                array( 'value' => 'penthouse', 'label' => __( 'Penthouse', 'quill-accessible-forms' ) ),
                array( 'value' => 'studio', 'label' => __( 'Studio', 'quill-accessible-forms' ) ),
                array( 'value' => 'apartment', 'label' => __( 'Apartment', 'quill-accessible-forms' ) ),
                array( 'value' => 'villa', 'label' => __( 'Villa', 'quill-accessible-forms' ) ),
                array( 'value' => 'cabin', 'label' => __( 'Cabin', 'quill-accessible-forms' ) ),
                array( 'value' => 'dormitory', 'label' => __( 'Dormitory', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Meal Plans
        self::register( 'meal_plans', array(
            'label'   => __( 'Meal Plans', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'room_only', 'label' => __( 'Room Only', 'quill-accessible-forms' ) ),
                array( 'value' => 'breakfast', 'label' => __( 'Bed & Breakfast', 'quill-accessible-forms' ) ),
                array( 'value' => 'half_board', 'label' => __( 'Half Board (Breakfast + Dinner)', 'quill-accessible-forms' ) ),
                array( 'value' => 'full_board', 'label' => __( 'Full Board (All Meals)', 'quill-accessible-forms' ) ),
                array( 'value' => 'all_inclusive', 'label' => __( 'All Inclusive', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Travel Classes
        self::register( 'travel_classes', array(
            'label'   => __( 'Travel Classes', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'economy', 'label' => __( 'Economy', 'quill-accessible-forms' ) ),
                array( 'value' => 'premium_economy', 'label' => __( 'Premium Economy', 'quill-accessible-forms' ) ),
                array( 'value' => 'business', 'label' => __( 'Business Class', 'quill-accessible-forms' ) ),
                array( 'value' => 'first', 'label' => __( 'First Class', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Passenger Types
        self::register( 'passenger_types', array(
            'label'   => __( 'Passenger Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'adult', 'label' => __( 'Adult (12+)', 'quill-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child (2-11)', 'quill-accessible-forms' ) ),
                array( 'value' => 'infant', 'label' => __( 'Infant (under 2)', 'quill-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior (65+)', 'quill-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'quill-accessible-forms' ) ),
                array( 'value' => 'military', 'label' => __( 'Military', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Trip Types
        self::register( 'trip_types', array(
            'label'   => __( 'Trip Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'one_way', 'label' => __( 'One Way', 'quill-accessible-forms' ) ),
                array( 'value' => 'round_trip', 'label' => __( 'Round Trip', 'quill-accessible-forms' ) ),
                array( 'value' => 'multi_city', 'label' => __( 'Multi-City', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // EVENT & BOOKING LIBRARIES
        // =====================================================================

        // Event Types
        self::register( 'event_types', array(
            'label'   => __( 'Event Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'conference', 'label' => __( 'Conference', 'quill-accessible-forms' ) ),
                array( 'value' => 'workshop', 'label' => __( 'Workshop', 'quill-accessible-forms' ) ),
                array( 'value' => 'seminar', 'label' => __( 'Seminar', 'quill-accessible-forms' ) ),
                array( 'value' => 'webinar', 'label' => __( 'Webinar', 'quill-accessible-forms' ) ),
                array( 'value' => 'meeting', 'label' => __( 'Meeting', 'quill-accessible-forms' ) ),
                array( 'value' => 'training', 'label' => __( 'Training', 'quill-accessible-forms' ) ),
                array( 'value' => 'networking', 'label' => __( 'Networking', 'quill-accessible-forms' ) ),
                array( 'value' => 'tradeshow', 'label' => __( 'Trade Show', 'quill-accessible-forms' ) ),
                array( 'value' => 'concert', 'label' => __( 'Concert', 'quill-accessible-forms' ) ),
                array( 'value' => 'festival', 'label' => __( 'Festival', 'quill-accessible-forms' ) ),
                array( 'value' => 'sports', 'label' => __( 'Sports Event', 'quill-accessible-forms' ) ),
                array( 'value' => 'wedding', 'label' => __( 'Wedding', 'quill-accessible-forms' ) ),
                array( 'value' => 'birthday', 'label' => __( 'Birthday Party', 'quill-accessible-forms' ) ),
                array( 'value' => 'anniversary', 'label' => __( 'Anniversary', 'quill-accessible-forms' ) ),
                array( 'value' => 'corporate', 'label' => __( 'Corporate Event', 'quill-accessible-forms' ) ),
                array( 'value' => 'fundraiser', 'label' => __( 'Fundraiser', 'quill-accessible-forms' ) ),
                array( 'value' => 'graduation', 'label' => __( 'Graduation', 'quill-accessible-forms' ) ),
                array( 'value' => 'reunion', 'label' => __( 'Reunion', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Attendance Types
        self::register( 'attendance_types', array(
            'label'   => __( 'Attendance Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'in_person', 'label' => __( 'In Person', 'quill-accessible-forms' ) ),
                array( 'value' => 'virtual', 'label' => __( 'Virtual/Online', 'quill-accessible-forms' ) ),
                array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'quill-accessible-forms' ) ),
            ),
        ) );

        // RSVP Responses
        self::register( 'rsvp_responses', array(
            'label'   => __( 'RSVP Responses', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes, I will attend', 'quill-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No, I cannot attend', 'quill-accessible-forms' ) ),
                array( 'value' => 'maybe', 'label' => __( 'Maybe', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Ticket Types
        self::register( 'ticket_types', array(
            'label'   => __( 'Ticket Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'general', 'label' => __( 'General Admission', 'quill-accessible-forms' ) ),
                array( 'value' => 'vip', 'label' => __( 'VIP', 'quill-accessible-forms' ) ),
                array( 'value' => 'premium', 'label' => __( 'Premium', 'quill-accessible-forms' ) ),
                array( 'value' => 'early_bird', 'label' => __( 'Early Bird', 'quill-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'quill-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior', 'quill-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child', 'quill-accessible-forms' ) ),
                array( 'value' => 'family', 'label' => __( 'Family Pack', 'quill-accessible-forms' ) ),
                array( 'value' => 'group', 'label' => __( 'Group', 'quill-accessible-forms' ) ),
                array( 'value' => 'comp', 'label' => __( 'Complimentary', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // RATING & FEEDBACK LIBRARIES
        // =====================================================================

        // Rating Scales (1-5)
        self::register( 'ratings_5', array(
            'label'   => __( 'Ratings (1-5)', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => __( '1 - Poor', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( '2 - Fair', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( '3 - Good', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( '4 - Very Good', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( '5 - Excellent', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Rating Scales (1-10)
        self::register( 'ratings_10', array(
            'label'   => __( 'Ratings (1-10)', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => '1' ),
                array( 'value' => '2', 'label' => '2' ),
                array( 'value' => '3', 'label' => '3' ),
                array( 'value' => '4', 'label' => '4' ),
                array( 'value' => '5', 'label' => '5' ),
                array( 'value' => '6', 'label' => '6' ),
                array( 'value' => '7', 'label' => '7' ),
                array( 'value' => '8', 'label' => '8' ),
                array( 'value' => '9', 'label' => '9' ),
                array( 'value' => '10', 'label' => '10' ),
            ),
        ) );

        // Satisfaction Levels
        self::register( 'satisfaction', array(
            'label'   => __( 'Satisfaction Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'very_dissatisfied', 'label' => __( 'Very Dissatisfied', 'quill-accessible-forms' ) ),
                array( 'value' => 'dissatisfied', 'label' => __( 'Dissatisfied', 'quill-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => 'satisfied', 'label' => __( 'Satisfied', 'quill-accessible-forms' ) ),
                array( 'value' => 'very_satisfied', 'label' => __( 'Very Satisfied', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Agreement Levels
        self::register( 'agreement', array(
            'label'   => __( 'Agreement Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'strongly_disagree', 'label' => __( 'Strongly Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => 'disagree', 'label' => __( 'Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => 'agree', 'label' => __( 'Agree', 'quill-accessible-forms' ) ),
                array( 'value' => 'strongly_agree', 'label' => __( 'Strongly Agree', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Likelihood Levels
        self::register( 'likelihood', array(
            'label'   => __( 'Likelihood Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'very_unlikely', 'label' => __( 'Very Unlikely', 'quill-accessible-forms' ) ),
                array( 'value' => 'unlikely', 'label' => __( 'Unlikely', 'quill-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => 'likely', 'label' => __( 'Likely', 'quill-accessible-forms' ) ),
                array( 'value' => 'very_likely', 'label' => __( 'Very Likely', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Frequency
        self::register( 'frequency', array(
            'label'   => __( 'Frequency', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'never', 'label' => __( 'Never', 'quill-accessible-forms' ) ),
                array( 'value' => 'rarely', 'label' => __( 'Rarely', 'quill-accessible-forms' ) ),
                array( 'value' => 'sometimes', 'label' => __( 'Sometimes', 'quill-accessible-forms' ) ),
                array( 'value' => 'often', 'label' => __( 'Often', 'quill-accessible-forms' ) ),
                array( 'value' => 'always', 'label' => __( 'Always', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Importance
        self::register( 'importance', array(
            'label'   => __( 'Importance Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'not_important', 'label' => __( 'Not Important', 'quill-accessible-forms' ) ),
                array( 'value' => 'slightly_important', 'label' => __( 'Slightly Important', 'quill-accessible-forms' ) ),
                array( 'value' => 'moderately_important', 'label' => __( 'Moderately Important', 'quill-accessible-forms' ) ),
                array( 'value' => 'important', 'label' => __( 'Important', 'quill-accessible-forms' ) ),
                array( 'value' => 'very_important', 'label' => __( 'Very Important', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Priority
        self::register( 'priority', array(
            'label'   => __( 'Priority Levels', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'lowest', 'label' => __( 'Lowest', 'quill-accessible-forms' ) ),
                array( 'value' => 'low', 'label' => __( 'Low', 'quill-accessible-forms' ) ),
                array( 'value' => 'medium', 'label' => __( 'Medium', 'quill-accessible-forms' ) ),
                array( 'value' => 'high', 'label' => __( 'High', 'quill-accessible-forms' ) ),
                array( 'value' => 'highest', 'label' => __( 'Highest', 'quill-accessible-forms' ) ),
                array( 'value' => 'critical', 'label' => __( 'Critical', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // TECHNOLOGY LIBRARIES
        // =====================================================================

        // Operating Systems
        self::register( 'operating_systems', array(
            'label'   => __( 'Operating Systems', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'windows', 'label' => __( 'Windows', 'quill-accessible-forms' ) ),
                array( 'value' => 'macos', 'label' => __( 'macOS', 'quill-accessible-forms' ) ),
                array( 'value' => 'linux', 'label' => __( 'Linux', 'quill-accessible-forms' ) ),
                array( 'value' => 'chrome_os', 'label' => __( 'Chrome OS', 'quill-accessible-forms' ) ),
                array( 'value' => 'ios', 'label' => __( 'iOS', 'quill-accessible-forms' ) ),
                array( 'value' => 'android', 'label' => __( 'Android', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Browsers
        self::register( 'browsers', array(
            'label'   => __( 'Web Browsers', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'chrome', 'label' => __( 'Google Chrome', 'quill-accessible-forms' ) ),
                array( 'value' => 'firefox', 'label' => __( 'Mozilla Firefox', 'quill-accessible-forms' ) ),
                array( 'value' => 'safari', 'label' => __( 'Safari', 'quill-accessible-forms' ) ),
                array( 'value' => 'edge', 'label' => __( 'Microsoft Edge', 'quill-accessible-forms' ) ),
                array( 'value' => 'opera', 'label' => __( 'Opera', 'quill-accessible-forms' ) ),
                array( 'value' => 'brave', 'label' => __( 'Brave', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Devices
        self::register( 'devices', array(
            'label'   => __( 'Device Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'desktop', 'label' => __( 'Desktop Computer', 'quill-accessible-forms' ) ),
                array( 'value' => 'laptop', 'label' => __( 'Laptop', 'quill-accessible-forms' ) ),
                array( 'value' => 'tablet', 'label' => __( 'Tablet', 'quill-accessible-forms' ) ),
                array( 'value' => 'smartphone', 'label' => __( 'Smartphone', 'quill-accessible-forms' ) ),
                array( 'value' => 'smartwatch', 'label' => __( 'Smartwatch', 'quill-accessible-forms' ) ),
                array( 'value' => 'smart_tv', 'label' => __( 'Smart TV', 'quill-accessible-forms' ) ),
                array( 'value' => 'gaming_console', 'label' => __( 'Gaming Console', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // File Types
        self::register( 'file_types', array(
            'label'   => __( 'File Types', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'pdf', 'label' => __( 'PDF', 'quill-accessible-forms' ) ),
                array( 'value' => 'doc', 'label' => __( 'Word Document (.doc/.docx)', 'quill-accessible-forms' ) ),
                array( 'value' => 'xls', 'label' => __( 'Excel Spreadsheet (.xls/.xlsx)', 'quill-accessible-forms' ) ),
                array( 'value' => 'ppt', 'label' => __( 'PowerPoint (.ppt/.pptx)', 'quill-accessible-forms' ) ),
                array( 'value' => 'txt', 'label' => __( 'Text File (.txt)', 'quill-accessible-forms' ) ),
                array( 'value' => 'csv', 'label' => __( 'CSV', 'quill-accessible-forms' ) ),
                array( 'value' => 'jpg', 'label' => __( 'JPEG Image', 'quill-accessible-forms' ) ),
                array( 'value' => 'png', 'label' => __( 'PNG Image', 'quill-accessible-forms' ) ),
                array( 'value' => 'gif', 'label' => __( 'GIF', 'quill-accessible-forms' ) ),
                array( 'value' => 'mp3', 'label' => __( 'MP3 Audio', 'quill-accessible-forms' ) ),
                array( 'value' => 'mp4', 'label' => __( 'MP4 Video', 'quill-accessible-forms' ) ),
                array( 'value' => 'zip', 'label' => __( 'ZIP Archive', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // SIMPLE YES/NO & BOOLEAN LIBRARIES
        // =====================================================================

        // Yes/No
        self::register( 'yes_no', array(
            'label'   => __( 'Yes/No', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'quill-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Yes/No/Maybe
        self::register( 'yes_no_maybe', array(
            'label'   => __( 'Yes/No/Maybe', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'quill-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'quill-accessible-forms' ) ),
                array( 'value' => 'maybe', 'label' => __( 'Maybe', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Yes/No/Not Applicable
        self::register( 'yes_no_na', array(
            'label'   => __( 'Yes/No/N/A', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'quill-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'quill-accessible-forms' ) ),
                array( 'value' => 'na', 'label' => __( 'Not Applicable', 'quill-accessible-forms' ) ),
            ),
        ) );

        // True/False
        self::register( 'true_false', array(
            'label'   => __( 'True/False', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'true', 'label' => __( 'True', 'quill-accessible-forms' ) ),
                array( 'value' => 'false', 'label' => __( 'False', 'quill-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // MISCELLANEOUS LIBRARIES
        // =====================================================================

        // How Did You Hear About Us
        self::register( 'referral_sources', array(
            'label'   => __( 'Referral Sources', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'search_engine', 'label' => __( 'Search Engine (Google, Bing, etc.)', 'quill-accessible-forms' ) ),
                array( 'value' => 'social_media', 'label' => __( 'Social Media', 'quill-accessible-forms' ) ),
                array( 'value' => 'friend_family', 'label' => __( 'Friend or Family', 'quill-accessible-forms' ) ),
                array( 'value' => 'colleague', 'label' => __( 'Colleague/Coworker', 'quill-accessible-forms' ) ),
                array( 'value' => 'advertisement', 'label' => __( 'Advertisement', 'quill-accessible-forms' ) ),
                array( 'value' => 'blog_article', 'label' => __( 'Blog/Article', 'quill-accessible-forms' ) ),
                array( 'value' => 'podcast', 'label' => __( 'Podcast', 'quill-accessible-forms' ) ),
                array( 'value' => 'youtube', 'label' => __( 'YouTube', 'quill-accessible-forms' ) ),
                array( 'value' => 'newsletter', 'label' => __( 'Newsletter/Email', 'quill-accessible-forms' ) ),
                array( 'value' => 'event', 'label' => __( 'Event/Conference', 'quill-accessible-forms' ) ),
                array( 'value' => 'review_site', 'label' => __( 'Review Site', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Quantity Selectors
        self::register( 'quantities', array(
            'label'    => __( 'Quantities', 'quill-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_quantities' ),
        ) );

        // Number of People
        self::register( 'num_people', array(
            'label'   => __( 'Number of People', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => '1' ),
                array( 'value' => '2', 'label' => '2' ),
                array( 'value' => '3', 'label' => '3' ),
                array( 'value' => '4', 'label' => '4' ),
                array( 'value' => '5', 'label' => '5' ),
                array( 'value' => '6', 'label' => '6' ),
                array( 'value' => '7', 'label' => '7' ),
                array( 'value' => '8', 'label' => '8' ),
                array( 'value' => '9', 'label' => '9' ),
                array( 'value' => '10', 'label' => '10' ),
                array( 'value' => '10_plus', 'label' => __( '10+', 'quill-accessible-forms' ) ),
            ),
        ) );

        // Colors
        self::register( 'colors', array(
            'label'   => __( 'Colors', 'quill-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'red', 'label' => __( 'Red', 'quill-accessible-forms' ) ),
                array( 'value' => 'orange', 'label' => __( 'Orange', 'quill-accessible-forms' ) ),
                array( 'value' => 'yellow', 'label' => __( 'Yellow', 'quill-accessible-forms' ) ),
                array( 'value' => 'green', 'label' => __( 'Green', 'quill-accessible-forms' ) ),
                array( 'value' => 'blue', 'label' => __( 'Blue', 'quill-accessible-forms' ) ),
                array( 'value' => 'purple', 'label' => __( 'Purple', 'quill-accessible-forms' ) ),
                array( 'value' => 'pink', 'label' => __( 'Pink', 'quill-accessible-forms' ) ),
                array( 'value' => 'brown', 'label' => __( 'Brown', 'quill-accessible-forms' ) ),
                array( 'value' => 'black', 'label' => __( 'Black', 'quill-accessible-forms' ) ),
                array( 'value' => 'white', 'label' => __( 'White', 'quill-accessible-forms' ) ),
                array( 'value' => 'gray', 'label' => __( 'Gray', 'quill-accessible-forms' ) ),
                array( 'value' => 'silver', 'label' => __( 'Silver', 'quill-accessible-forms' ) ),
                array( 'value' => 'gold', 'label' => __( 'Gold', 'quill-accessible-forms' ) ),
                array( 'value' => 'navy', 'label' => __( 'Navy', 'quill-accessible-forms' ) ),
                array( 'value' => 'teal', 'label' => __( 'Teal', 'quill-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            ),
        ) );
    }

    /**
     * Register built-in cascading relationships
     */
    private static function register_builtin_cascades() {
        // Continent → Country cascade
        self::register_cascade( 'continents', 'countries_by_continent', array( __CLASS__, 'get_countries_by_continent' ) );
        
        // Country → State/Province cascade
        self::register_cascade( 'countries', 'states', array( __CLASS__, 'get_states_for_country' ) );
        
        // Timezone Region → Timezones cascade
        self::register_cascade( 'timezone_regions', 'timezones_by_region', array( __CLASS__, 'get_timezones_by_region' ) );
    }

    // =========================================================================
    // DATA PROVIDER METHODS
    // =========================================================================

    /**
     * Get continents
     *
     * @return array Continents.
     */
    public static function get_continents() {
        return array(
            array( 'value' => 'AF', 'label' => __( 'Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'AN', 'label' => __( 'Antarctica', 'quill-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'EU', 'label' => __( 'Europe', 'quill-accessible-forms' ) ),
            array( 'value' => 'NA', 'label' => __( 'North America', 'quill-accessible-forms' ) ),
            array( 'value' => 'OC', 'label' => __( 'Oceania', 'quill-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'South America', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get world regions
     *
     * @return array World regions.
     */
    public static function get_world_regions() {
        return array(
            // Africa
            array( 'value' => 'northern_africa', 'label' => __( 'Northern Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'western_africa', 'label' => __( 'Western Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'middle_africa', 'label' => __( 'Middle Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'eastern_africa', 'label' => __( 'Eastern Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'southern_africa', 'label' => __( 'Southern Africa', 'quill-accessible-forms' ) ),
            // Americas
            array( 'value' => 'northern_america', 'label' => __( 'Northern America', 'quill-accessible-forms' ) ),
            array( 'value' => 'central_america', 'label' => __( 'Central America', 'quill-accessible-forms' ) ),
            array( 'value' => 'caribbean', 'label' => __( 'Caribbean', 'quill-accessible-forms' ) ),
            array( 'value' => 'south_america', 'label' => __( 'South America', 'quill-accessible-forms' ) ),
            // Asia
            array( 'value' => 'central_asia', 'label' => __( 'Central Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'eastern_asia', 'label' => __( 'Eastern Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'southern_asia', 'label' => __( 'Southern Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'southeastern_asia', 'label' => __( 'South-Eastern Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'western_asia', 'label' => __( 'Western Asia', 'quill-accessible-forms' ) ),
            // Europe
            array( 'value' => 'eastern_europe', 'label' => __( 'Eastern Europe', 'quill-accessible-forms' ) ),
            array( 'value' => 'northern_europe', 'label' => __( 'Northern Europe', 'quill-accessible-forms' ) ),
            array( 'value' => 'southern_europe', 'label' => __( 'Southern Europe', 'quill-accessible-forms' ) ),
            array( 'value' => 'western_europe', 'label' => __( 'Western Europe', 'quill-accessible-forms' ) ),
            // Oceania
            array( 'value' => 'australia_nz', 'label' => __( 'Australia and New Zealand', 'quill-accessible-forms' ) ),
            array( 'value' => 'melanesia', 'label' => __( 'Melanesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'micronesia', 'label' => __( 'Micronesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'polynesia', 'label' => __( 'Polynesia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get complete countries list (ISO 3166-1 alpha-2)
     *
     * @return array Countries.
     */
    public static function get_countries() {
        return array(
            array( 'value' => 'AF', 'label' => __( 'Afghanistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'AX', 'label' => __( 'Åland Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'AL', 'label' => __( 'Albania', 'quill-accessible-forms' ) ),
            array( 'value' => 'DZ', 'label' => __( 'Algeria', 'quill-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'American Samoa', 'quill-accessible-forms' ) ),
            array( 'value' => 'AD', 'label' => __( 'Andorra', 'quill-accessible-forms' ) ),
            array( 'value' => 'AO', 'label' => __( 'Angola', 'quill-accessible-forms' ) ),
            array( 'value' => 'AI', 'label' => __( 'Anguilla', 'quill-accessible-forms' ) ),
            array( 'value' => 'AQ', 'label' => __( 'Antarctica', 'quill-accessible-forms' ) ),
            array( 'value' => 'AG', 'label' => __( 'Antigua and Barbuda', 'quill-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Argentina', 'quill-accessible-forms' ) ),
            array( 'value' => 'AM', 'label' => __( 'Armenia', 'quill-accessible-forms' ) ),
            array( 'value' => 'AW', 'label' => __( 'Aruba', 'quill-accessible-forms' ) ),
            array( 'value' => 'AU', 'label' => __( 'Australia', 'quill-accessible-forms' ) ),
            array( 'value' => 'AT', 'label' => __( 'Austria', 'quill-accessible-forms' ) ),
            array( 'value' => 'AZ', 'label' => __( 'Azerbaijan', 'quill-accessible-forms' ) ),
            array( 'value' => 'BS', 'label' => __( 'Bahamas', 'quill-accessible-forms' ) ),
            array( 'value' => 'BH', 'label' => __( 'Bahrain', 'quill-accessible-forms' ) ),
            array( 'value' => 'BD', 'label' => __( 'Bangladesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'BB', 'label' => __( 'Barbados', 'quill-accessible-forms' ) ),
            array( 'value' => 'BY', 'label' => __( 'Belarus', 'quill-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Belgium', 'quill-accessible-forms' ) ),
            array( 'value' => 'BZ', 'label' => __( 'Belize', 'quill-accessible-forms' ) ),
            array( 'value' => 'BJ', 'label' => __( 'Benin', 'quill-accessible-forms' ) ),
            array( 'value' => 'BM', 'label' => __( 'Bermuda', 'quill-accessible-forms' ) ),
            array( 'value' => 'BT', 'label' => __( 'Bhutan', 'quill-accessible-forms' ) ),
            array( 'value' => 'BO', 'label' => __( 'Bolivia', 'quill-accessible-forms' ) ),
            array( 'value' => 'BQ', 'label' => __( 'Bonaire, Sint Eustatius and Saba', 'quill-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bosnia and Herzegovina', 'quill-accessible-forms' ) ),
            array( 'value' => 'BW', 'label' => __( 'Botswana', 'quill-accessible-forms' ) ),
            array( 'value' => 'BV', 'label' => __( 'Bouvet Island', 'quill-accessible-forms' ) ),
            array( 'value' => 'BR', 'label' => __( 'Brazil', 'quill-accessible-forms' ) ),
            array( 'value' => 'IO', 'label' => __( 'British Indian Ocean Territory', 'quill-accessible-forms' ) ),
            array( 'value' => 'BN', 'label' => __( 'Brunei Darussalam', 'quill-accessible-forms' ) ),
            array( 'value' => 'BG', 'label' => __( 'Bulgaria', 'quill-accessible-forms' ) ),
            array( 'value' => 'BF', 'label' => __( 'Burkina Faso', 'quill-accessible-forms' ) ),
            array( 'value' => 'BI', 'label' => __( 'Burundi', 'quill-accessible-forms' ) ),
            array( 'value' => 'CV', 'label' => __( 'Cabo Verde', 'quill-accessible-forms' ) ),
            array( 'value' => 'KH', 'label' => __( 'Cambodia', 'quill-accessible-forms' ) ),
            array( 'value' => 'CM', 'label' => __( 'Cameroon', 'quill-accessible-forms' ) ),
            array( 'value' => 'CA', 'label' => __( 'Canada', 'quill-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Cayman Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'CF', 'label' => __( 'Central African Republic', 'quill-accessible-forms' ) ),
            array( 'value' => 'TD', 'label' => __( 'Chad', 'quill-accessible-forms' ) ),
            array( 'value' => 'CL', 'label' => __( 'Chile', 'quill-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'China', 'quill-accessible-forms' ) ),
            array( 'value' => 'CX', 'label' => __( 'Christmas Island', 'quill-accessible-forms' ) ),
            array( 'value' => 'CC', 'label' => __( 'Cocos (Keeling) Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Colombia', 'quill-accessible-forms' ) ),
            array( 'value' => 'KM', 'label' => __( 'Comoros', 'quill-accessible-forms' ) ),
            array( 'value' => 'CG', 'label' => __( 'Congo', 'quill-accessible-forms' ) ),
            array( 'value' => 'CD', 'label' => __( 'Congo, Democratic Republic', 'quill-accessible-forms' ) ),
            array( 'value' => 'CK', 'label' => __( 'Cook Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'CR', 'label' => __( 'Costa Rica', 'quill-accessible-forms' ) ),
            array( 'value' => 'CI', 'label' => __( 'Côte d\'Ivoire', 'quill-accessible-forms' ) ),
            array( 'value' => 'HR', 'label' => __( 'Croatia', 'quill-accessible-forms' ) ),
            array( 'value' => 'CU', 'label' => __( 'Cuba', 'quill-accessible-forms' ) ),
            array( 'value' => 'CW', 'label' => __( 'Curaçao', 'quill-accessible-forms' ) ),
            array( 'value' => 'CY', 'label' => __( 'Cyprus', 'quill-accessible-forms' ) ),
            array( 'value' => 'CZ', 'label' => __( 'Czechia', 'quill-accessible-forms' ) ),
            array( 'value' => 'DK', 'label' => __( 'Denmark', 'quill-accessible-forms' ) ),
            array( 'value' => 'DJ', 'label' => __( 'Djibouti', 'quill-accessible-forms' ) ),
            array( 'value' => 'DM', 'label' => __( 'Dominica', 'quill-accessible-forms' ) ),
            array( 'value' => 'DO', 'label' => __( 'Dominican Republic', 'quill-accessible-forms' ) ),
            array( 'value' => 'EC', 'label' => __( 'Ecuador', 'quill-accessible-forms' ) ),
            array( 'value' => 'EG', 'label' => __( 'Egypt', 'quill-accessible-forms' ) ),
            array( 'value' => 'SV', 'label' => __( 'El Salvador', 'quill-accessible-forms' ) ),
            array( 'value' => 'GQ', 'label' => __( 'Equatorial Guinea', 'quill-accessible-forms' ) ),
            array( 'value' => 'ER', 'label' => __( 'Eritrea', 'quill-accessible-forms' ) ),
            array( 'value' => 'EE', 'label' => __( 'Estonia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SZ', 'label' => __( 'Eswatini', 'quill-accessible-forms' ) ),
            array( 'value' => 'ET', 'label' => __( 'Ethiopia', 'quill-accessible-forms' ) ),
            array( 'value' => 'FK', 'label' => __( 'Falkland Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'FO', 'label' => __( 'Faroe Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'FJ', 'label' => __( 'Fiji', 'quill-accessible-forms' ) ),
            array( 'value' => 'FI', 'label' => __( 'Finland', 'quill-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'France', 'quill-accessible-forms' ) ),
            array( 'value' => 'GF', 'label' => __( 'French Guiana', 'quill-accessible-forms' ) ),
            array( 'value' => 'PF', 'label' => __( 'French Polynesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'TF', 'label' => __( 'French Southern Territories', 'quill-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Gabon', 'quill-accessible-forms' ) ),
            array( 'value' => 'GM', 'label' => __( 'Gambia', 'quill-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Georgia', 'quill-accessible-forms' ) ),
            array( 'value' => 'DE', 'label' => __( 'Germany', 'quill-accessible-forms' ) ),
            array( 'value' => 'GH', 'label' => __( 'Ghana', 'quill-accessible-forms' ) ),
            array( 'value' => 'GI', 'label' => __( 'Gibraltar', 'quill-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Greece', 'quill-accessible-forms' ) ),
            array( 'value' => 'GL', 'label' => __( 'Greenland', 'quill-accessible-forms' ) ),
            array( 'value' => 'GD', 'label' => __( 'Grenada', 'quill-accessible-forms' ) ),
            array( 'value' => 'GP', 'label' => __( 'Guadeloupe', 'quill-accessible-forms' ) ),
            array( 'value' => 'GU', 'label' => __( 'Guam', 'quill-accessible-forms' ) ),
            array( 'value' => 'GT', 'label' => __( 'Guatemala', 'quill-accessible-forms' ) ),
            array( 'value' => 'GG', 'label' => __( 'Guernsey', 'quill-accessible-forms' ) ),
            array( 'value' => 'GN', 'label' => __( 'Guinea', 'quill-accessible-forms' ) ),
            array( 'value' => 'GW', 'label' => __( 'Guinea-Bissau', 'quill-accessible-forms' ) ),
            array( 'value' => 'GY', 'label' => __( 'Guyana', 'quill-accessible-forms' ) ),
            array( 'value' => 'HT', 'label' => __( 'Haiti', 'quill-accessible-forms' ) ),
            array( 'value' => 'HM', 'label' => __( 'Heard Island and McDonald Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'VA', 'label' => __( 'Holy See', 'quill-accessible-forms' ) ),
            array( 'value' => 'HN', 'label' => __( 'Honduras', 'quill-accessible-forms' ) ),
            array( 'value' => 'HK', 'label' => __( 'Hong Kong', 'quill-accessible-forms' ) ),
            array( 'value' => 'HU', 'label' => __( 'Hungary', 'quill-accessible-forms' ) ),
            array( 'value' => 'IS', 'label' => __( 'Iceland', 'quill-accessible-forms' ) ),
            array( 'value' => 'IN', 'label' => __( 'India', 'quill-accessible-forms' ) ),
            array( 'value' => 'ID', 'label' => __( 'Indonesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'IR', 'label' => __( 'Iran', 'quill-accessible-forms' ) ),
            array( 'value' => 'IQ', 'label' => __( 'Iraq', 'quill-accessible-forms' ) ),
            array( 'value' => 'IE', 'label' => __( 'Ireland', 'quill-accessible-forms' ) ),
            array( 'value' => 'IM', 'label' => __( 'Isle of Man', 'quill-accessible-forms' ) ),
            array( 'value' => 'IL', 'label' => __( 'Israel', 'quill-accessible-forms' ) ),
            array( 'value' => 'IT', 'label' => __( 'Italy', 'quill-accessible-forms' ) ),
            array( 'value' => 'JM', 'label' => __( 'Jamaica', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP', 'label' => __( 'Japan', 'quill-accessible-forms' ) ),
            array( 'value' => 'JE', 'label' => __( 'Jersey', 'quill-accessible-forms' ) ),
            array( 'value' => 'JO', 'label' => __( 'Jordan', 'quill-accessible-forms' ) ),
            array( 'value' => 'KZ', 'label' => __( 'Kazakhstan', 'quill-accessible-forms' ) ),
            array( 'value' => 'KE', 'label' => __( 'Kenya', 'quill-accessible-forms' ) ),
            array( 'value' => 'KI', 'label' => __( 'Kiribati', 'quill-accessible-forms' ) ),
            array( 'value' => 'KP', 'label' => __( 'Korea, North', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR', 'label' => __( 'Korea, South', 'quill-accessible-forms' ) ),
            array( 'value' => 'KW', 'label' => __( 'Kuwait', 'quill-accessible-forms' ) ),
            array( 'value' => 'KG', 'label' => __( 'Kyrgyzstan', 'quill-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Laos', 'quill-accessible-forms' ) ),
            array( 'value' => 'LV', 'label' => __( 'Latvia', 'quill-accessible-forms' ) ),
            array( 'value' => 'LB', 'label' => __( 'Lebanon', 'quill-accessible-forms' ) ),
            array( 'value' => 'LS', 'label' => __( 'Lesotho', 'quill-accessible-forms' ) ),
            array( 'value' => 'LR', 'label' => __( 'Liberia', 'quill-accessible-forms' ) ),
            array( 'value' => 'LY', 'label' => __( 'Libya', 'quill-accessible-forms' ) ),
            array( 'value' => 'LI', 'label' => __( 'Liechtenstein', 'quill-accessible-forms' ) ),
            array( 'value' => 'LT', 'label' => __( 'Lithuania', 'quill-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Luxembourg', 'quill-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Macao', 'quill-accessible-forms' ) ),
            array( 'value' => 'MG', 'label' => __( 'Madagascar', 'quill-accessible-forms' ) ),
            array( 'value' => 'MW', 'label' => __( 'Malawi', 'quill-accessible-forms' ) ),
            array( 'value' => 'MY', 'label' => __( 'Malaysia', 'quill-accessible-forms' ) ),
            array( 'value' => 'MV', 'label' => __( 'Maldives', 'quill-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Mali', 'quill-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Malta', 'quill-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Marshall Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'MQ', 'label' => __( 'Martinique', 'quill-accessible-forms' ) ),
            array( 'value' => 'MR', 'label' => __( 'Mauritania', 'quill-accessible-forms' ) ),
            array( 'value' => 'MU', 'label' => __( 'Mauritius', 'quill-accessible-forms' ) ),
            array( 'value' => 'YT', 'label' => __( 'Mayotte', 'quill-accessible-forms' ) ),
            array( 'value' => 'MX', 'label' => __( 'Mexico', 'quill-accessible-forms' ) ),
            array( 'value' => 'FM', 'label' => __( 'Micronesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Moldova', 'quill-accessible-forms' ) ),
            array( 'value' => 'MC', 'label' => __( 'Monaco', 'quill-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Mongolia', 'quill-accessible-forms' ) ),
            array( 'value' => 'ME', 'label' => __( 'Montenegro', 'quill-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Montserrat', 'quill-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Morocco', 'quill-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Mozambique', 'quill-accessible-forms' ) ),
            array( 'value' => 'MM', 'label' => __( 'Myanmar', 'quill-accessible-forms' ) ),
            array( 'value' => 'NA', 'label' => __( 'Namibia', 'quill-accessible-forms' ) ),
            array( 'value' => 'NR', 'label' => __( 'Nauru', 'quill-accessible-forms' ) ),
            array( 'value' => 'NP', 'label' => __( 'Nepal', 'quill-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Netherlands', 'quill-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'New Caledonia', 'quill-accessible-forms' ) ),
            array( 'value' => 'NZ', 'label' => __( 'New Zealand', 'quill-accessible-forms' ) ),
            array( 'value' => 'NI', 'label' => __( 'Nicaragua', 'quill-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Niger', 'quill-accessible-forms' ) ),
            array( 'value' => 'NG', 'label' => __( 'Nigeria', 'quill-accessible-forms' ) ),
            array( 'value' => 'NU', 'label' => __( 'Niue', 'quill-accessible-forms' ) ),
            array( 'value' => 'NF', 'label' => __( 'Norfolk Island', 'quill-accessible-forms' ) ),
            array( 'value' => 'MK', 'label' => __( 'North Macedonia', 'quill-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Northern Mariana Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'NO', 'label' => __( 'Norway', 'quill-accessible-forms' ) ),
            array( 'value' => 'OM', 'label' => __( 'Oman', 'quill-accessible-forms' ) ),
            array( 'value' => 'PK', 'label' => __( 'Pakistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'PW', 'label' => __( 'Palau', 'quill-accessible-forms' ) ),
            array( 'value' => 'PS', 'label' => __( 'Palestine', 'quill-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Panama', 'quill-accessible-forms' ) ),
            array( 'value' => 'PG', 'label' => __( 'Papua New Guinea', 'quill-accessible-forms' ) ),
            array( 'value' => 'PY', 'label' => __( 'Paraguay', 'quill-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Peru', 'quill-accessible-forms' ) ),
            array( 'value' => 'PH', 'label' => __( 'Philippines', 'quill-accessible-forms' ) ),
            array( 'value' => 'PN', 'label' => __( 'Pitcairn', 'quill-accessible-forms' ) ),
            array( 'value' => 'PL', 'label' => __( 'Poland', 'quill-accessible-forms' ) ),
            array( 'value' => 'PT', 'label' => __( 'Portugal', 'quill-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Puerto Rico', 'quill-accessible-forms' ) ),
            array( 'value' => 'QA', 'label' => __( 'Qatar', 'quill-accessible-forms' ) ),
            array( 'value' => 'RE', 'label' => __( 'Réunion', 'quill-accessible-forms' ) ),
            array( 'value' => 'RO', 'label' => __( 'Romania', 'quill-accessible-forms' ) ),
            array( 'value' => 'RU', 'label' => __( 'Russian Federation', 'quill-accessible-forms' ) ),
            array( 'value' => 'RW', 'label' => __( 'Rwanda', 'quill-accessible-forms' ) ),
            array( 'value' => 'BL', 'label' => __( 'Saint Barthélemy', 'quill-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Saint Helena', 'quill-accessible-forms' ) ),
            array( 'value' => 'KN', 'label' => __( 'Saint Kitts and Nevis', 'quill-accessible-forms' ) ),
            array( 'value' => 'LC', 'label' => __( 'Saint Lucia', 'quill-accessible-forms' ) ),
            array( 'value' => 'MF', 'label' => __( 'Saint Martin (French)', 'quill-accessible-forms' ) ),
            array( 'value' => 'PM', 'label' => __( 'Saint Pierre and Miquelon', 'quill-accessible-forms' ) ),
            array( 'value' => 'VC', 'label' => __( 'Saint Vincent and the Grenadines', 'quill-accessible-forms' ) ),
            array( 'value' => 'WS', 'label' => __( 'Samoa', 'quill-accessible-forms' ) ),
            array( 'value' => 'SM', 'label' => __( 'San Marino', 'quill-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Sao Tome and Principe', 'quill-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'Saudi Arabia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Senegal', 'quill-accessible-forms' ) ),
            array( 'value' => 'RS', 'label' => __( 'Serbia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Seychelles', 'quill-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Sierra Leone', 'quill-accessible-forms' ) ),
            array( 'value' => 'SG', 'label' => __( 'Singapore', 'quill-accessible-forms' ) ),
            array( 'value' => 'SX', 'label' => __( 'Sint Maarten (Dutch)', 'quill-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Slovakia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SI', 'label' => __( 'Slovenia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SB', 'label' => __( 'Solomon Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Somalia', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZA', 'label' => __( 'South Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'GS', 'label' => __( 'South Georgia and the South Sandwich Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'SS', 'label' => __( 'South Sudan', 'quill-accessible-forms' ) ),
            array( 'value' => 'ES', 'label' => __( 'Spain', 'quill-accessible-forms' ) ),
            array( 'value' => 'LK', 'label' => __( 'Sri Lanka', 'quill-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'Sudan', 'quill-accessible-forms' ) ),
            array( 'value' => 'SR', 'label' => __( 'Suriname', 'quill-accessible-forms' ) ),
            array( 'value' => 'SJ', 'label' => __( 'Svalbard and Jan Mayen', 'quill-accessible-forms' ) ),
            array( 'value' => 'SE', 'label' => __( 'Sweden', 'quill-accessible-forms' ) ),
            array( 'value' => 'CH', 'label' => __( 'Switzerland', 'quill-accessible-forms' ) ),
            array( 'value' => 'SY', 'label' => __( 'Syrian Arab Republic', 'quill-accessible-forms' ) ),
            array( 'value' => 'TW', 'label' => __( 'Taiwan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TJ', 'label' => __( 'Tajikistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TZ', 'label' => __( 'Tanzania', 'quill-accessible-forms' ) ),
            array( 'value' => 'TH', 'label' => __( 'Thailand', 'quill-accessible-forms' ) ),
            array( 'value' => 'TL', 'label' => __( 'Timor-Leste', 'quill-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Togo', 'quill-accessible-forms' ) ),
            array( 'value' => 'TK', 'label' => __( 'Tokelau', 'quill-accessible-forms' ) ),
            array( 'value' => 'TO', 'label' => __( 'Tonga', 'quill-accessible-forms' ) ),
            array( 'value' => 'TT', 'label' => __( 'Trinidad and Tobago', 'quill-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tunisia', 'quill-accessible-forms' ) ),
            array( 'value' => 'TR', 'label' => __( 'Turkey', 'quill-accessible-forms' ) ),
            array( 'value' => 'TM', 'label' => __( 'Turkmenistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TC', 'label' => __( 'Turks and Caicos Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'TV', 'label' => __( 'Tuvalu', 'quill-accessible-forms' ) ),
            array( 'value' => 'UG', 'label' => __( 'Uganda', 'quill-accessible-forms' ) ),
            array( 'value' => 'UA', 'label' => __( 'Ukraine', 'quill-accessible-forms' ) ),
            array( 'value' => 'AE', 'label' => __( 'United Arab Emirates', 'quill-accessible-forms' ) ),
            array( 'value' => 'GB', 'label' => __( 'United Kingdom', 'quill-accessible-forms' ) ),
            array( 'value' => 'US', 'label' => __( 'United States', 'quill-accessible-forms' ) ),
            array( 'value' => 'UM', 'label' => __( 'United States Minor Outlying Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'UY', 'label' => __( 'Uruguay', 'quill-accessible-forms' ) ),
            array( 'value' => 'UZ', 'label' => __( 'Uzbekistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'VU', 'label' => __( 'Vanuatu', 'quill-accessible-forms' ) ),
            array( 'value' => 'VE', 'label' => __( 'Venezuela', 'quill-accessible-forms' ) ),
            array( 'value' => 'VN', 'label' => __( 'Vietnam', 'quill-accessible-forms' ) ),
            array( 'value' => 'VG', 'label' => __( 'Virgin Islands (British)', 'quill-accessible-forms' ) ),
            array( 'value' => 'VI', 'label' => __( 'Virgin Islands (U.S.)', 'quill-accessible-forms' ) ),
            array( 'value' => 'WF', 'label' => __( 'Wallis and Futuna', 'quill-accessible-forms' ) ),
            array( 'value' => 'EH', 'label' => __( 'Western Sahara', 'quill-accessible-forms' ) ),
            array( 'value' => 'YE', 'label' => __( 'Yemen', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZM', 'label' => __( 'Zambia', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZW', 'label' => __( 'Zimbabwe', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get countries by continent (cascading)
     *
     * @param string $continent_code Continent code.
     * @return array Countries in the continent.
     */
    public static function get_countries_by_continent( $continent_code = null ) {
        if ( empty( $continent_code ) ) {
            return array();
        }

        $mappings = array(
            'AF' => array( 'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW' ),
            'AN' => array( 'AQ' ),
            'AS' => array( 'AF', 'AM', 'AZ', 'BH', 'BD', 'BT', 'BN', 'KH', 'CN', 'CY', 'GE', 'HK', 'IN', 'ID', 'IR', 'IQ', 'IL', 'JP', 'JO', 'KZ', 'KW', 'KG', 'LA', 'LB', 'MO', 'MY', 'MV', 'MN', 'MM', 'NP', 'KP', 'OM', 'PK', 'PS', 'PH', 'QA', 'SA', 'SG', 'KR', 'LK', 'SY', 'TW', 'TJ', 'TH', 'TL', 'TR', 'TM', 'AE', 'UZ', 'VN', 'YE' ),
            'EU' => array( 'AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'XK', 'LV', 'LI', 'LT', 'LU', 'MT', 'MD', 'MC', 'ME', 'NL', 'MK', 'NO', 'PL', 'PT', 'RO', 'RU', 'SM', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'UA', 'GB', 'VA' ),
            'NA' => array( 'AI', 'AG', 'AW', 'BS', 'BB', 'BZ', 'BM', 'BQ', 'VG', 'CA', 'KY', 'CR', 'CU', 'CW', 'DM', 'DO', 'SV', 'GL', 'GD', 'GP', 'GT', 'HT', 'HN', 'JM', 'MQ', 'MX', 'MS', 'NI', 'PA', 'PR', 'BL', 'KN', 'LC', 'MF', 'PM', 'VC', 'SX', 'TT', 'TC', 'US', 'VI' ),
            'OC' => array( 'AS', 'AU', 'CK', 'FJ', 'PF', 'GU', 'KI', 'MH', 'FM', 'NR', 'NC', 'NZ', 'NU', 'NF', 'MP', 'PW', 'PG', 'PN', 'WS', 'SB', 'TK', 'TO', 'TV', 'VU', 'WF' ),
            'SA' => array( 'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PY', 'PE', 'SR', 'UY', 'VE' ),
        );

        if ( ! isset( $mappings[ strtoupper( $continent_code ) ] ) ) {
            return array();
        }

        $country_codes = $mappings[ strtoupper( $continent_code ) ];
        $all_countries = self::get_countries();
        $filtered = array();

        foreach ( $all_countries as $country ) {
            if ( in_array( $country['value'], $country_codes, true ) ) {
                $filtered[] = $country;
            }
        }

        return $filtered;
    }

    /**
     * Get US states
     *
     * @return array US states.
     */
    public static function get_us_states() {
        return array(
            array( 'value' => 'AL', 'label' => __( 'Alabama', 'quill-accessible-forms' ) ),
            array( 'value' => 'AK', 'label' => __( 'Alaska', 'quill-accessible-forms' ) ),
            array( 'value' => 'AZ', 'label' => __( 'Arizona', 'quill-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Arkansas', 'quill-accessible-forms' ) ),
            array( 'value' => 'CA', 'label' => __( 'California', 'quill-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Colorado', 'quill-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Connecticut', 'quill-accessible-forms' ) ),
            array( 'value' => 'DE', 'label' => __( 'Delaware', 'quill-accessible-forms' ) ),
            array( 'value' => 'FL', 'label' => __( 'Florida', 'quill-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Georgia', 'quill-accessible-forms' ) ),
            array( 'value' => 'HI', 'label' => __( 'Hawaii', 'quill-accessible-forms' ) ),
            array( 'value' => 'ID', 'label' => __( 'Idaho', 'quill-accessible-forms' ) ),
            array( 'value' => 'IL', 'label' => __( 'Illinois', 'quill-accessible-forms' ) ),
            array( 'value' => 'IN', 'label' => __( 'Indiana', 'quill-accessible-forms' ) ),
            array( 'value' => 'IA', 'label' => __( 'Iowa', 'quill-accessible-forms' ) ),
            array( 'value' => 'KS', 'label' => __( 'Kansas', 'quill-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Kentucky', 'quill-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Louisiana', 'quill-accessible-forms' ) ),
            array( 'value' => 'ME', 'label' => __( 'Maine', 'quill-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Maryland', 'quill-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Massachusetts', 'quill-accessible-forms' ) ),
            array( 'value' => 'MI', 'label' => __( 'Michigan', 'quill-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Minnesota', 'quill-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Mississippi', 'quill-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Missouri', 'quill-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Montana', 'quill-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Nebraska', 'quill-accessible-forms' ) ),
            array( 'value' => 'NV', 'label' => __( 'Nevada', 'quill-accessible-forms' ) ),
            array( 'value' => 'NH', 'label' => __( 'New Hampshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'NJ', 'label' => __( 'New Jersey', 'quill-accessible-forms' ) ),
            array( 'value' => 'NM', 'label' => __( 'New Mexico', 'quill-accessible-forms' ) ),
            array( 'value' => 'NY', 'label' => __( 'New York', 'quill-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'North Carolina', 'quill-accessible-forms' ) ),
            array( 'value' => 'ND', 'label' => __( 'North Dakota', 'quill-accessible-forms' ) ),
            array( 'value' => 'OH', 'label' => __( 'Ohio', 'quill-accessible-forms' ) ),
            array( 'value' => 'OK', 'label' => __( 'Oklahoma', 'quill-accessible-forms' ) ),
            array( 'value' => 'OR', 'label' => __( 'Oregon', 'quill-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Pennsylvania', 'quill-accessible-forms' ) ),
            array( 'value' => 'RI', 'label' => __( 'Rhode Island', 'quill-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'South Carolina', 'quill-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'South Dakota', 'quill-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tennessee', 'quill-accessible-forms' ) ),
            array( 'value' => 'TX', 'label' => __( 'Texas', 'quill-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Utah', 'quill-accessible-forms' ) ),
            array( 'value' => 'VT', 'label' => __( 'Vermont', 'quill-accessible-forms' ) ),
            array( 'value' => 'VA', 'label' => __( 'Virginia', 'quill-accessible-forms' ) ),
            array( 'value' => 'WA', 'label' => __( 'Washington', 'quill-accessible-forms' ) ),
            array( 'value' => 'WV', 'label' => __( 'West Virginia', 'quill-accessible-forms' ) ),
            array( 'value' => 'WI', 'label' => __( 'Wisconsin', 'quill-accessible-forms' ) ),
            array( 'value' => 'WY', 'label' => __( 'Wyoming', 'quill-accessible-forms' ) ),
            array( 'value' => 'DC', 'label' => __( 'District of Columbia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get US states with territories
     *
     * @return array US states and territories.
     */
    public static function get_us_states_territories() {
        $states = self::get_us_states();
        $territories = array(
            array( 'value' => 'AS', 'label' => __( 'American Samoa', 'quill-accessible-forms' ) ),
            array( 'value' => 'GU', 'label' => __( 'Guam', 'quill-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Northern Mariana Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Puerto Rico', 'quill-accessible-forms' ) ),
            array( 'value' => 'VI', 'label' => __( 'U.S. Virgin Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'UM', 'label' => __( 'U.S. Minor Outlying Islands', 'quill-accessible-forms' ) ),
        );
        return array_merge( $states, $territories );
    }

    /**
     * Get Canadian provinces and territories
     *
     * @return array Canadian provinces.
     */
    public static function get_ca_provinces() {
        return array(
            array( 'value' => 'AB', 'label' => __( 'Alberta', 'quill-accessible-forms' ) ),
            array( 'value' => 'BC', 'label' => __( 'British Columbia', 'quill-accessible-forms' ) ),
            array( 'value' => 'MB', 'label' => __( 'Manitoba', 'quill-accessible-forms' ) ),
            array( 'value' => 'NB', 'label' => __( 'New Brunswick', 'quill-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Newfoundland and Labrador', 'quill-accessible-forms' ) ),
            array( 'value' => 'NS', 'label' => __( 'Nova Scotia', 'quill-accessible-forms' ) ),
            array( 'value' => 'NT', 'label' => __( 'Northwest Territories', 'quill-accessible-forms' ) ),
            array( 'value' => 'NU', 'label' => __( 'Nunavut', 'quill-accessible-forms' ) ),
            array( 'value' => 'ON', 'label' => __( 'Ontario', 'quill-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Prince Edward Island', 'quill-accessible-forms' ) ),
            array( 'value' => 'QC', 'label' => __( 'Quebec', 'quill-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Saskatchewan', 'quill-accessible-forms' ) ),
            array( 'value' => 'YT', 'label' => __( 'Yukon', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get UK counties/regions
     *
     * @return array UK counties.
     */
    public static function get_uk_counties() {
        return array(
            // England
            array( 'value' => 'BDF', 'label' => __( 'Bedfordshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'BRK', 'label' => __( 'Berkshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'BKM', 'label' => __( 'Buckinghamshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Cambridgeshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHS', 'label' => __( 'Cheshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'CON', 'label' => __( 'Cornwall', 'quill-accessible-forms' ) ),
            array( 'value' => 'CMA', 'label' => __( 'Cumbria', 'quill-accessible-forms' ) ),
            array( 'value' => 'DBY', 'label' => __( 'Derbyshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'DEV', 'label' => __( 'Devon', 'quill-accessible-forms' ) ),
            array( 'value' => 'DOR', 'label' => __( 'Dorset', 'quill-accessible-forms' ) ),
            array( 'value' => 'DUR', 'label' => __( 'Durham', 'quill-accessible-forms' ) ),
            array( 'value' => 'ESS', 'label' => __( 'Essex', 'quill-accessible-forms' ) ),
            array( 'value' => 'GLS', 'label' => __( 'Gloucestershire', 'quill-accessible-forms' ) ),
            array( 'value' => 'LDN', 'label' => __( 'Greater London', 'quill-accessible-forms' ) ),
            array( 'value' => 'GTM', 'label' => __( 'Greater Manchester', 'quill-accessible-forms' ) ),
            array( 'value' => 'HAM', 'label' => __( 'Hampshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'HEF', 'label' => __( 'Herefordshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'HRT', 'label' => __( 'Hertfordshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'KEN', 'label' => __( 'Kent', 'quill-accessible-forms' ) ),
            array( 'value' => 'LAN', 'label' => __( 'Lancashire', 'quill-accessible-forms' ) ),
            array( 'value' => 'LEC', 'label' => __( 'Leicestershire', 'quill-accessible-forms' ) ),
            array( 'value' => 'LIN', 'label' => __( 'Lincolnshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'MSY', 'label' => __( 'Merseyside', 'quill-accessible-forms' ) ),
            array( 'value' => 'NFK', 'label' => __( 'Norfolk', 'quill-accessible-forms' ) ),
            array( 'value' => 'NTH', 'label' => __( 'Northamptonshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'NBL', 'label' => __( 'Northumberland', 'quill-accessible-forms' ) ),
            array( 'value' => 'NTT', 'label' => __( 'Nottinghamshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'OXF', 'label' => __( 'Oxfordshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'SHR', 'label' => __( 'Shropshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'SOM', 'label' => __( 'Somerset', 'quill-accessible-forms' ) ),
            array( 'value' => 'SYK', 'label' => __( 'South Yorkshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'STS', 'label' => __( 'Staffordshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'SFK', 'label' => __( 'Suffolk', 'quill-accessible-forms' ) ),
            array( 'value' => 'SRY', 'label' => __( 'Surrey', 'quill-accessible-forms' ) ),
            array( 'value' => 'TWR', 'label' => __( 'Tyne and Wear', 'quill-accessible-forms' ) ),
            array( 'value' => 'WAR', 'label' => __( 'Warwickshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'WMD', 'label' => __( 'West Midlands', 'quill-accessible-forms' ) ),
            array( 'value' => 'WSX', 'label' => __( 'West Sussex', 'quill-accessible-forms' ) ),
            array( 'value' => 'WYK', 'label' => __( 'West Yorkshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'WIL', 'label' => __( 'Wiltshire', 'quill-accessible-forms' ) ),
            array( 'value' => 'WOR', 'label' => __( 'Worcestershire', 'quill-accessible-forms' ) ),
            // Scotland
            array( 'value' => 'SCT', 'label' => __( 'Scotland', 'quill-accessible-forms' ) ),
            // Wales
            array( 'value' => 'WLS', 'label' => __( 'Wales', 'quill-accessible-forms' ) ),
            // Northern Ireland
            array( 'value' => 'NIR', 'label' => __( 'Northern Ireland', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Australian states and territories
     *
     * @return array Australian states.
     */
    public static function get_au_states() {
        return array(
            array( 'value' => 'ACT', 'label' => __( 'Australian Capital Territory', 'quill-accessible-forms' ) ),
            array( 'value' => 'NSW', 'label' => __( 'New South Wales', 'quill-accessible-forms' ) ),
            array( 'value' => 'NT', 'label' => __( 'Northern Territory', 'quill-accessible-forms' ) ),
            array( 'value' => 'QLD', 'label' => __( 'Queensland', 'quill-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'South Australia', 'quill-accessible-forms' ) ),
            array( 'value' => 'TAS', 'label' => __( 'Tasmania', 'quill-accessible-forms' ) ),
            array( 'value' => 'VIC', 'label' => __( 'Victoria', 'quill-accessible-forms' ) ),
            array( 'value' => 'WA', 'label' => __( 'Western Australia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get German states (Bundesländer)
     *
     * @return array German states.
     */
    public static function get_de_states() {
        return array(
            array( 'value' => 'BW', 'label' => __( 'Baden-Württemberg', 'quill-accessible-forms' ) ),
            array( 'value' => 'BY', 'label' => __( 'Bavaria', 'quill-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Berlin', 'quill-accessible-forms' ) ),
            array( 'value' => 'BB', 'label' => __( 'Brandenburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'HB', 'label' => __( 'Bremen', 'quill-accessible-forms' ) ),
            array( 'value' => 'HH', 'label' => __( 'Hamburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'HE', 'label' => __( 'Hesse', 'quill-accessible-forms' ) ),
            array( 'value' => 'MV', 'label' => __( 'Mecklenburg-Vorpommern', 'quill-accessible-forms' ) ),
            array( 'value' => 'NI', 'label' => __( 'Lower Saxony', 'quill-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'North Rhine-Westphalia', 'quill-accessible-forms' ) ),
            array( 'value' => 'RP', 'label' => __( 'Rhineland-Palatinate', 'quill-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Saarland', 'quill-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Saxony', 'quill-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Saxony-Anhalt', 'quill-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Schleswig-Holstein', 'quill-accessible-forms' ) ),
            array( 'value' => 'TH', 'label' => __( 'Thuringia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get French regions
     *
     * @return array French regions.
     */
    public static function get_fr_regions() {
        return array(
            array( 'value' => 'ARA', 'label' => __( 'Auvergne-Rhône-Alpes', 'quill-accessible-forms' ) ),
            array( 'value' => 'BFC', 'label' => __( 'Bourgogne-Franche-Comté', 'quill-accessible-forms' ) ),
            array( 'value' => 'BRE', 'label' => __( 'Brittany', 'quill-accessible-forms' ) ),
            array( 'value' => 'CVL', 'label' => __( 'Centre-Val de Loire', 'quill-accessible-forms' ) ),
            array( 'value' => 'COR', 'label' => __( 'Corsica', 'quill-accessible-forms' ) ),
            array( 'value' => 'GES', 'label' => __( 'Grand Est', 'quill-accessible-forms' ) ),
            array( 'value' => 'HDF', 'label' => __( 'Hauts-de-France', 'quill-accessible-forms' ) ),
            array( 'value' => 'IDF', 'label' => __( 'Île-de-France', 'quill-accessible-forms' ) ),
            array( 'value' => 'NOR', 'label' => __( 'Normandy', 'quill-accessible-forms' ) ),
            array( 'value' => 'NAQ', 'label' => __( 'Nouvelle-Aquitaine', 'quill-accessible-forms' ) ),
            array( 'value' => 'OCC', 'label' => __( 'Occitanie', 'quill-accessible-forms' ) ),
            array( 'value' => 'PDL', 'label' => __( 'Pays de la Loire', 'quill-accessible-forms' ) ),
            array( 'value' => 'PAC', 'label' => __( 'Provence-Alpes-Côte d\'Azur', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Italian regions
     *
     * @return array Italian regions.
     */
    public static function get_it_regions() {
        return array(
            array( 'value' => 'ABR', 'label' => __( 'Abruzzo', 'quill-accessible-forms' ) ),
            array( 'value' => 'VDA', 'label' => __( 'Aosta Valley', 'quill-accessible-forms' ) ),
            array( 'value' => 'PUG', 'label' => __( 'Apulia', 'quill-accessible-forms' ) ),
            array( 'value' => 'BAS', 'label' => __( 'Basilicata', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAL', 'label' => __( 'Calabria', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Campania', 'quill-accessible-forms' ) ),
            array( 'value' => 'EMR', 'label' => __( 'Emilia-Romagna', 'quill-accessible-forms' ) ),
            array( 'value' => 'FVG', 'label' => __( 'Friuli Venezia Giulia', 'quill-accessible-forms' ) ),
            array( 'value' => 'LAZ', 'label' => __( 'Lazio', 'quill-accessible-forms' ) ),
            array( 'value' => 'LIG', 'label' => __( 'Liguria', 'quill-accessible-forms' ) ),
            array( 'value' => 'LOM', 'label' => __( 'Lombardy', 'quill-accessible-forms' ) ),
            array( 'value' => 'MAR', 'label' => __( 'Marche', 'quill-accessible-forms' ) ),
            array( 'value' => 'MOL', 'label' => __( 'Molise', 'quill-accessible-forms' ) ),
            array( 'value' => 'PMN', 'label' => __( 'Piedmont', 'quill-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Sardinia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SIC', 'label' => __( 'Sicily', 'quill-accessible-forms' ) ),
            array( 'value' => 'TOS', 'label' => __( 'Tuscany', 'quill-accessible-forms' ) ),
            array( 'value' => 'TAA', 'label' => __( 'Trentino-Alto Adige', 'quill-accessible-forms' ) ),
            array( 'value' => 'UMB', 'label' => __( 'Umbria', 'quill-accessible-forms' ) ),
            array( 'value' => 'VEN', 'label' => __( 'Veneto', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Spanish autonomous communities
     *
     * @return array Spanish regions.
     */
    public static function get_es_regions() {
        return array(
            array( 'value' => 'AN', 'label' => __( 'Andalusia', 'quill-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Aragon', 'quill-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Asturias', 'quill-accessible-forms' ) ),
            array( 'value' => 'IB', 'label' => __( 'Balearic Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'PV', 'label' => __( 'Basque Country', 'quill-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'Canary Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'CB', 'label' => __( 'Cantabria', 'quill-accessible-forms' ) ),
            array( 'value' => 'CL', 'label' => __( 'Castile and León', 'quill-accessible-forms' ) ),
            array( 'value' => 'CM', 'label' => __( 'Castilla-La Mancha', 'quill-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Catalonia', 'quill-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Ceuta', 'quill-accessible-forms' ) ),
            array( 'value' => 'EX', 'label' => __( 'Extremadura', 'quill-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Galicia', 'quill-accessible-forms' ) ),
            array( 'value' => 'RI', 'label' => __( 'La Rioja', 'quill-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Madrid', 'quill-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Melilla', 'quill-accessible-forms' ) ),
            array( 'value' => 'MC', 'label' => __( 'Murcia', 'quill-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'Navarre', 'quill-accessible-forms' ) ),
            array( 'value' => 'VC', 'label' => __( 'Valencia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Dutch provinces
     *
     * @return array Dutch provinces.
     */
    public static function get_nl_provinces() {
        return array(
            array( 'value' => 'DR', 'label' => __( 'Drenthe', 'quill-accessible-forms' ) ),
            array( 'value' => 'FL', 'label' => __( 'Flevoland', 'quill-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'Friesland', 'quill-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Gelderland', 'quill-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Groningen', 'quill-accessible-forms' ) ),
            array( 'value' => 'LI', 'label' => __( 'Limburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'NB', 'label' => __( 'North Brabant', 'quill-accessible-forms' ) ),
            array( 'value' => 'NH', 'label' => __( 'North Holland', 'quill-accessible-forms' ) ),
            array( 'value' => 'OV', 'label' => __( 'Overijssel', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZH', 'label' => __( 'South Holland', 'quill-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Utrecht', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZE', 'label' => __( 'Zeeland', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Belgian provinces
     *
     * @return array Belgian provinces.
     */
    public static function get_be_provinces() {
        return array(
            array( 'value' => 'ANT', 'label' => __( 'Antwerp', 'quill-accessible-forms' ) ),
            array( 'value' => 'BRU', 'label' => __( 'Brussels', 'quill-accessible-forms' ) ),
            array( 'value' => 'VLG', 'label' => __( 'East Flanders', 'quill-accessible-forms' ) ),
            array( 'value' => 'VBR', 'label' => __( 'Flemish Brabant', 'quill-accessible-forms' ) ),
            array( 'value' => 'HAI', 'label' => __( 'Hainaut', 'quill-accessible-forms' ) ),
            array( 'value' => 'LIE', 'label' => __( 'Liège', 'quill-accessible-forms' ) ),
            array( 'value' => 'LIM', 'label' => __( 'Limburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'LUX', 'label' => __( 'Luxembourg', 'quill-accessible-forms' ) ),
            array( 'value' => 'NAM', 'label' => __( 'Namur', 'quill-accessible-forms' ) ),
            array( 'value' => 'BRW', 'label' => __( 'Walloon Brabant', 'quill-accessible-forms' ) ),
            array( 'value' => 'WVL', 'label' => __( 'West Flanders', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Swiss cantons
     *
     * @return array Swiss cantons.
     */
    public static function get_ch_cantons() {
        return array(
            array( 'value' => 'AG', 'label' => __( 'Aargau', 'quill-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Appenzell Ausserrhoden', 'quill-accessible-forms' ) ),
            array( 'value' => 'AI', 'label' => __( 'Appenzell Innerrhoden', 'quill-accessible-forms' ) ),
            array( 'value' => 'BL', 'label' => __( 'Basel-Landschaft', 'quill-accessible-forms' ) ),
            array( 'value' => 'BS', 'label' => __( 'Basel-Stadt', 'quill-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Bern', 'quill-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'Fribourg', 'quill-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Geneva', 'quill-accessible-forms' ) ),
            array( 'value' => 'GL', 'label' => __( 'Glarus', 'quill-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Graubünden', 'quill-accessible-forms' ) ),
            array( 'value' => 'JU', 'label' => __( 'Jura', 'quill-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Lucerne', 'quill-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Neuchâtel', 'quill-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'Nidwalden', 'quill-accessible-forms' ) ),
            array( 'value' => 'OW', 'label' => __( 'Obwalden', 'quill-accessible-forms' ) ),
            array( 'value' => 'SG', 'label' => __( 'St. Gallen', 'quill-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Schaffhausen', 'quill-accessible-forms' ) ),
            array( 'value' => 'SZ', 'label' => __( 'Schwyz', 'quill-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Solothurn', 'quill-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Thurgau', 'quill-accessible-forms' ) ),
            array( 'value' => 'TI', 'label' => __( 'Ticino', 'quill-accessible-forms' ) ),
            array( 'value' => 'UR', 'label' => __( 'Uri', 'quill-accessible-forms' ) ),
            array( 'value' => 'VS', 'label' => __( 'Valais', 'quill-accessible-forms' ) ),
            array( 'value' => 'VD', 'label' => __( 'Vaud', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZG', 'label' => __( 'Zug', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZH', 'label' => __( 'Zürich', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Austrian states
     *
     * @return array Austrian states.
     */
    public static function get_at_states() {
        return array(
            array( 'value' => 'B', 'label' => __( 'Burgenland', 'quill-accessible-forms' ) ),
            array( 'value' => 'K', 'label' => __( 'Carinthia', 'quill-accessible-forms' ) ),
            array( 'value' => 'NO', 'label' => __( 'Lower Austria', 'quill-accessible-forms' ) ),
            array( 'value' => 'OO', 'label' => __( 'Upper Austria', 'quill-accessible-forms' ) ),
            array( 'value' => 'S', 'label' => __( 'Salzburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Styria', 'quill-accessible-forms' ) ),
            array( 'value' => 'T', 'label' => __( 'Tyrol', 'quill-accessible-forms' ) ),
            array( 'value' => 'V', 'label' => __( 'Vorarlberg', 'quill-accessible-forms' ) ),
            array( 'value' => 'W', 'label' => __( 'Vienna', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Mexican states
     *
     * @return array Mexican states.
     */
    public static function get_mx_states() {
        return array(
            array( 'value' => 'AGU', 'label' => __( 'Aguascalientes', 'quill-accessible-forms' ) ),
            array( 'value' => 'BCN', 'label' => __( 'Baja California', 'quill-accessible-forms' ) ),
            array( 'value' => 'BCS', 'label' => __( 'Baja California Sur', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Campeche', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHP', 'label' => __( 'Chiapas', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHH', 'label' => __( 'Chihuahua', 'quill-accessible-forms' ) ),
            array( 'value' => 'CMX', 'label' => __( 'Mexico City', 'quill-accessible-forms' ) ),
            array( 'value' => 'COA', 'label' => __( 'Coahuila', 'quill-accessible-forms' ) ),
            array( 'value' => 'COL', 'label' => __( 'Colima', 'quill-accessible-forms' ) ),
            array( 'value' => 'DUR', 'label' => __( 'Durango', 'quill-accessible-forms' ) ),
            array( 'value' => 'GUA', 'label' => __( 'Guanajuato', 'quill-accessible-forms' ) ),
            array( 'value' => 'GRO', 'label' => __( 'Guerrero', 'quill-accessible-forms' ) ),
            array( 'value' => 'HID', 'label' => __( 'Hidalgo', 'quill-accessible-forms' ) ),
            array( 'value' => 'JAL', 'label' => __( 'Jalisco', 'quill-accessible-forms' ) ),
            array( 'value' => 'MEX', 'label' => __( 'México', 'quill-accessible-forms' ) ),
            array( 'value' => 'MIC', 'label' => __( 'Michoacán', 'quill-accessible-forms' ) ),
            array( 'value' => 'MOR', 'label' => __( 'Morelos', 'quill-accessible-forms' ) ),
            array( 'value' => 'NAY', 'label' => __( 'Nayarit', 'quill-accessible-forms' ) ),
            array( 'value' => 'NLE', 'label' => __( 'Nuevo León', 'quill-accessible-forms' ) ),
            array( 'value' => 'OAX', 'label' => __( 'Oaxaca', 'quill-accessible-forms' ) ),
            array( 'value' => 'PUE', 'label' => __( 'Puebla', 'quill-accessible-forms' ) ),
            array( 'value' => 'QUE', 'label' => __( 'Querétaro', 'quill-accessible-forms' ) ),
            array( 'value' => 'ROO', 'label' => __( 'Quintana Roo', 'quill-accessible-forms' ) ),
            array( 'value' => 'SLP', 'label' => __( 'San Luis Potosí', 'quill-accessible-forms' ) ),
            array( 'value' => 'SIN', 'label' => __( 'Sinaloa', 'quill-accessible-forms' ) ),
            array( 'value' => 'SON', 'label' => __( 'Sonora', 'quill-accessible-forms' ) ),
            array( 'value' => 'TAB', 'label' => __( 'Tabasco', 'quill-accessible-forms' ) ),
            array( 'value' => 'TAM', 'label' => __( 'Tamaulipas', 'quill-accessible-forms' ) ),
            array( 'value' => 'TLA', 'label' => __( 'Tlaxcala', 'quill-accessible-forms' ) ),
            array( 'value' => 'VER', 'label' => __( 'Veracruz', 'quill-accessible-forms' ) ),
            array( 'value' => 'YUC', 'label' => __( 'Yucatán', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZAC', 'label' => __( 'Zacatecas', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Brazilian states
     *
     * @return array Brazilian states.
     */
    public static function get_br_states() {
        return array(
            array( 'value' => 'AC', 'label' => __( 'Acre', 'quill-accessible-forms' ) ),
            array( 'value' => 'AL', 'label' => __( 'Alagoas', 'quill-accessible-forms' ) ),
            array( 'value' => 'AP', 'label' => __( 'Amapá', 'quill-accessible-forms' ) ),
            array( 'value' => 'AM', 'label' => __( 'Amazonas', 'quill-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bahia', 'quill-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Ceará', 'quill-accessible-forms' ) ),
            array( 'value' => 'DF', 'label' => __( 'Distrito Federal', 'quill-accessible-forms' ) ),
            array( 'value' => 'ES', 'label' => __( 'Espírito Santo', 'quill-accessible-forms' ) ),
            array( 'value' => 'GO', 'label' => __( 'Goiás', 'quill-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Maranhão', 'quill-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Mato Grosso', 'quill-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Mato Grosso do Sul', 'quill-accessible-forms' ) ),
            array( 'value' => 'MG', 'label' => __( 'Minas Gerais', 'quill-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Pará', 'quill-accessible-forms' ) ),
            array( 'value' => 'PB', 'label' => __( 'Paraíba', 'quill-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Paraná', 'quill-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Pernambuco', 'quill-accessible-forms' ) ),
            array( 'value' => 'PI', 'label' => __( 'Piauí', 'quill-accessible-forms' ) ),
            array( 'value' => 'RJ', 'label' => __( 'Rio de Janeiro', 'quill-accessible-forms' ) ),
            array( 'value' => 'RN', 'label' => __( 'Rio Grande do Norte', 'quill-accessible-forms' ) ),
            array( 'value' => 'RS', 'label' => __( 'Rio Grande do Sul', 'quill-accessible-forms' ) ),
            array( 'value' => 'RO', 'label' => __( 'Rondônia', 'quill-accessible-forms' ) ),
            array( 'value' => 'RR', 'label' => __( 'Roraima', 'quill-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Santa Catarina', 'quill-accessible-forms' ) ),
            array( 'value' => 'SP', 'label' => __( 'São Paulo', 'quill-accessible-forms' ) ),
            array( 'value' => 'SE', 'label' => __( 'Sergipe', 'quill-accessible-forms' ) ),
            array( 'value' => 'TO', 'label' => __( 'Tocantins', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Argentine provinces
     *
     * @return array Argentine provinces.
     */
    public static function get_ar_provinces() {
        return array(
            array( 'value' => 'B', 'label' => __( 'Buenos Aires', 'quill-accessible-forms' ) ),
            array( 'value' => 'C', 'label' => __( 'Ciudad Autónoma de Buenos Aires', 'quill-accessible-forms' ) ),
            array( 'value' => 'K', 'label' => __( 'Catamarca', 'quill-accessible-forms' ) ),
            array( 'value' => 'H', 'label' => __( 'Chaco', 'quill-accessible-forms' ) ),
            array( 'value' => 'U', 'label' => __( 'Chubut', 'quill-accessible-forms' ) ),
            array( 'value' => 'X', 'label' => __( 'Córdoba', 'quill-accessible-forms' ) ),
            array( 'value' => 'W', 'label' => __( 'Corrientes', 'quill-accessible-forms' ) ),
            array( 'value' => 'E', 'label' => __( 'Entre Ríos', 'quill-accessible-forms' ) ),
            array( 'value' => 'P', 'label' => __( 'Formosa', 'quill-accessible-forms' ) ),
            array( 'value' => 'Y', 'label' => __( 'Jujuy', 'quill-accessible-forms' ) ),
            array( 'value' => 'L', 'label' => __( 'La Pampa', 'quill-accessible-forms' ) ),
            array( 'value' => 'F', 'label' => __( 'La Rioja', 'quill-accessible-forms' ) ),
            array( 'value' => 'M', 'label' => __( 'Mendoza', 'quill-accessible-forms' ) ),
            array( 'value' => 'N', 'label' => __( 'Misiones', 'quill-accessible-forms' ) ),
            array( 'value' => 'Q', 'label' => __( 'Neuquén', 'quill-accessible-forms' ) ),
            array( 'value' => 'R', 'label' => __( 'Río Negro', 'quill-accessible-forms' ) ),
            array( 'value' => 'A', 'label' => __( 'Salta', 'quill-accessible-forms' ) ),
            array( 'value' => 'J', 'label' => __( 'San Juan', 'quill-accessible-forms' ) ),
            array( 'value' => 'D', 'label' => __( 'San Luis', 'quill-accessible-forms' ) ),
            array( 'value' => 'Z', 'label' => __( 'Santa Cruz', 'quill-accessible-forms' ) ),
            array( 'value' => 'S', 'label' => __( 'Santa Fe', 'quill-accessible-forms' ) ),
            array( 'value' => 'G', 'label' => __( 'Santiago del Estero', 'quill-accessible-forms' ) ),
            array( 'value' => 'V', 'label' => __( 'Tierra del Fuego', 'quill-accessible-forms' ) ),
            array( 'value' => 'T', 'label' => __( 'Tucumán', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Indian states and union territories
     *
     * @return array Indian states.
     */
    public static function get_in_states() {
        return array(
            array( 'value' => 'AN', 'label' => __( 'Andaman and Nicobar Islands', 'quill-accessible-forms' ) ),
            array( 'value' => 'AP', 'label' => __( 'Andhra Pradesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Arunachal Pradesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Assam', 'quill-accessible-forms' ) ),
            array( 'value' => 'BR', 'label' => __( 'Bihar', 'quill-accessible-forms' ) ),
            array( 'value' => 'CH', 'label' => __( 'Chandigarh', 'quill-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Chhattisgarh', 'quill-accessible-forms' ) ),
            array( 'value' => 'DN', 'label' => __( 'Dadra and Nagar Haveli and Daman and Diu', 'quill-accessible-forms' ) ),
            array( 'value' => 'DL', 'label' => __( 'Delhi', 'quill-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Goa', 'quill-accessible-forms' ) ),
            array( 'value' => 'GJ', 'label' => __( 'Gujarat', 'quill-accessible-forms' ) ),
            array( 'value' => 'HR', 'label' => __( 'Haryana', 'quill-accessible-forms' ) ),
            array( 'value' => 'HP', 'label' => __( 'Himachal Pradesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'JK', 'label' => __( 'Jammu and Kashmir', 'quill-accessible-forms' ) ),
            array( 'value' => 'JH', 'label' => __( 'Jharkhand', 'quill-accessible-forms' ) ),
            array( 'value' => 'KA', 'label' => __( 'Karnataka', 'quill-accessible-forms' ) ),
            array( 'value' => 'KL', 'label' => __( 'Kerala', 'quill-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Ladakh', 'quill-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Lakshadweep', 'quill-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Madhya Pradesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Maharashtra', 'quill-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Manipur', 'quill-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Meghalaya', 'quill-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Mizoram', 'quill-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Nagaland', 'quill-accessible-forms' ) ),
            array( 'value' => 'OR', 'label' => __( 'Odisha', 'quill-accessible-forms' ) ),
            array( 'value' => 'PY', 'label' => __( 'Puducherry', 'quill-accessible-forms' ) ),
            array( 'value' => 'PB', 'label' => __( 'Punjab', 'quill-accessible-forms' ) ),
            array( 'value' => 'RJ', 'label' => __( 'Rajasthan', 'quill-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Sikkim', 'quill-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tamil Nadu', 'quill-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Telangana', 'quill-accessible-forms' ) ),
            array( 'value' => 'TR', 'label' => __( 'Tripura', 'quill-accessible-forms' ) ),
            array( 'value' => 'UP', 'label' => __( 'Uttar Pradesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Uttarakhand', 'quill-accessible-forms' ) ),
            array( 'value' => 'WB', 'label' => __( 'West Bengal', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Chinese provinces and regions
     *
     * @return array Chinese provinces.
     */
    public static function get_cn_provinces() {
        return array(
            array( 'value' => 'AH', 'label' => __( 'Anhui', 'quill-accessible-forms' ) ),
            array( 'value' => 'BJ', 'label' => __( 'Beijing', 'quill-accessible-forms' ) ),
            array( 'value' => 'CQ', 'label' => __( 'Chongqing', 'quill-accessible-forms' ) ),
            array( 'value' => 'FJ', 'label' => __( 'Fujian', 'quill-accessible-forms' ) ),
            array( 'value' => 'GS', 'label' => __( 'Gansu', 'quill-accessible-forms' ) ),
            array( 'value' => 'GD', 'label' => __( 'Guangdong', 'quill-accessible-forms' ) ),
            array( 'value' => 'GX', 'label' => __( 'Guangxi', 'quill-accessible-forms' ) ),
            array( 'value' => 'GZ', 'label' => __( 'Guizhou', 'quill-accessible-forms' ) ),
            array( 'value' => 'HI', 'label' => __( 'Hainan', 'quill-accessible-forms' ) ),
            array( 'value' => 'HE', 'label' => __( 'Hebei', 'quill-accessible-forms' ) ),
            array( 'value' => 'HL', 'label' => __( 'Heilongjiang', 'quill-accessible-forms' ) ),
            array( 'value' => 'HA', 'label' => __( 'Henan', 'quill-accessible-forms' ) ),
            array( 'value' => 'HK', 'label' => __( 'Hong Kong', 'quill-accessible-forms' ) ),
            array( 'value' => 'HB', 'label' => __( 'Hubei', 'quill-accessible-forms' ) ),
            array( 'value' => 'HN', 'label' => __( 'Hunan', 'quill-accessible-forms' ) ),
            array( 'value' => 'NM', 'label' => __( 'Inner Mongolia', 'quill-accessible-forms' ) ),
            array( 'value' => 'JS', 'label' => __( 'Jiangsu', 'quill-accessible-forms' ) ),
            array( 'value' => 'JX', 'label' => __( 'Jiangxi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JL', 'label' => __( 'Jilin', 'quill-accessible-forms' ) ),
            array( 'value' => 'LN', 'label' => __( 'Liaoning', 'quill-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Macau', 'quill-accessible-forms' ) ),
            array( 'value' => 'NX', 'label' => __( 'Ningxia', 'quill-accessible-forms' ) ),
            array( 'value' => 'QH', 'label' => __( 'Qinghai', 'quill-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Shaanxi', 'quill-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'Shandong', 'quill-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Shanghai', 'quill-accessible-forms' ) ),
            array( 'value' => 'SX', 'label' => __( 'Shanxi', 'quill-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Sichuan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TW', 'label' => __( 'Taiwan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TJ', 'label' => __( 'Tianjin', 'quill-accessible-forms' ) ),
            array( 'value' => 'XZ', 'label' => __( 'Tibet', 'quill-accessible-forms' ) ),
            array( 'value' => 'XJ', 'label' => __( 'Xinjiang', 'quill-accessible-forms' ) ),
            array( 'value' => 'YN', 'label' => __( 'Yunnan', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZJ', 'label' => __( 'Zhejiang', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Japanese prefectures
     *
     * @return array Japanese prefectures.
     */
    public static function get_jp_prefectures() {
        return array(
            array( 'value' => 'JP-01', 'label' => __( 'Hokkaido', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-02', 'label' => __( 'Aomori', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-03', 'label' => __( 'Iwate', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-04', 'label' => __( 'Miyagi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-05', 'label' => __( 'Akita', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-06', 'label' => __( 'Yamagata', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-07', 'label' => __( 'Fukushima', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-08', 'label' => __( 'Ibaraki', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-09', 'label' => __( 'Tochigi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-10', 'label' => __( 'Gunma', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-11', 'label' => __( 'Saitama', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-12', 'label' => __( 'Chiba', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-13', 'label' => __( 'Tokyo', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-14', 'label' => __( 'Kanagawa', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-15', 'label' => __( 'Niigata', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-16', 'label' => __( 'Toyama', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-17', 'label' => __( 'Ishikawa', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-18', 'label' => __( 'Fukui', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-19', 'label' => __( 'Yamanashi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-20', 'label' => __( 'Nagano', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-21', 'label' => __( 'Gifu', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-22', 'label' => __( 'Shizuoka', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-23', 'label' => __( 'Aichi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-24', 'label' => __( 'Mie', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-25', 'label' => __( 'Shiga', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-26', 'label' => __( 'Kyoto', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-27', 'label' => __( 'Osaka', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-28', 'label' => __( 'Hyogo', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-29', 'label' => __( 'Nara', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-30', 'label' => __( 'Wakayama', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-31', 'label' => __( 'Tottori', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-32', 'label' => __( 'Shimane', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-33', 'label' => __( 'Okayama', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-34', 'label' => __( 'Hiroshima', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-35', 'label' => __( 'Yamaguchi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-36', 'label' => __( 'Tokushima', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-37', 'label' => __( 'Kagawa', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-38', 'label' => __( 'Ehime', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-39', 'label' => __( 'Kochi', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-40', 'label' => __( 'Fukuoka', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-41', 'label' => __( 'Saga', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-42', 'label' => __( 'Nagasaki', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-43', 'label' => __( 'Kumamoto', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-44', 'label' => __( 'Oita', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-45', 'label' => __( 'Miyazaki', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-46', 'label' => __( 'Kagoshima', 'quill-accessible-forms' ) ),
            array( 'value' => 'JP-47', 'label' => __( 'Okinawa', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get South Korean provinces
     *
     * @return array South Korean provinces.
     */
    public static function get_kr_provinces() {
        return array(
            array( 'value' => 'KR-11', 'label' => __( 'Seoul', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-26', 'label' => __( 'Busan', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-27', 'label' => __( 'Daegu', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-28', 'label' => __( 'Incheon', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-29', 'label' => __( 'Gwangju', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-30', 'label' => __( 'Daejeon', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-31', 'label' => __( 'Ulsan', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-50', 'label' => __( 'Sejong', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-41', 'label' => __( 'Gyeonggi', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-42', 'label' => __( 'Gangwon', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-43', 'label' => __( 'North Chungcheong', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-44', 'label' => __( 'South Chungcheong', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-45', 'label' => __( 'North Jeolla', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-46', 'label' => __( 'South Jeolla', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-47', 'label' => __( 'North Gyeongsang', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-48', 'label' => __( 'South Gyeongsang', 'quill-accessible-forms' ) ),
            array( 'value' => 'KR-49', 'label' => __( 'Jeju', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get New Zealand regions
     *
     * @return array New Zealand regions.
     */
    public static function get_nz_regions() {
        return array(
            array( 'value' => 'NTL', 'label' => __( 'Northland', 'quill-accessible-forms' ) ),
            array( 'value' => 'AUK', 'label' => __( 'Auckland', 'quill-accessible-forms' ) ),
            array( 'value' => 'WKO', 'label' => __( 'Waikato', 'quill-accessible-forms' ) ),
            array( 'value' => 'BOP', 'label' => __( 'Bay of Plenty', 'quill-accessible-forms' ) ),
            array( 'value' => 'GIS', 'label' => __( 'Gisborne', 'quill-accessible-forms' ) ),
            array( 'value' => 'HKB', 'label' => __( 'Hawke\'s Bay', 'quill-accessible-forms' ) ),
            array( 'value' => 'TKI', 'label' => __( 'Taranaki', 'quill-accessible-forms' ) ),
            array( 'value' => 'MWT', 'label' => __( 'Manawatū-Whanganui', 'quill-accessible-forms' ) ),
            array( 'value' => 'WGN', 'label' => __( 'Wellington', 'quill-accessible-forms' ) ),
            array( 'value' => 'TAS', 'label' => __( 'Tasman', 'quill-accessible-forms' ) ),
            array( 'value' => 'NSN', 'label' => __( 'Nelson', 'quill-accessible-forms' ) ),
            array( 'value' => 'MBH', 'label' => __( 'Marlborough', 'quill-accessible-forms' ) ),
            array( 'value' => 'WTC', 'label' => __( 'West Coast', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAN', 'label' => __( 'Canterbury', 'quill-accessible-forms' ) ),
            array( 'value' => 'OTA', 'label' => __( 'Otago', 'quill-accessible-forms' ) ),
            array( 'value' => 'STL', 'label' => __( 'Southland', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get South African provinces
     *
     * @return array South African provinces.
     */
    public static function get_za_provinces() {
        return array(
            array( 'value' => 'EC', 'label' => __( 'Eastern Cape', 'quill-accessible-forms' ) ),
            array( 'value' => 'FS', 'label' => __( 'Free State', 'quill-accessible-forms' ) ),
            array( 'value' => 'GP', 'label' => __( 'Gauteng', 'quill-accessible-forms' ) ),
            array( 'value' => 'KZN', 'label' => __( 'KwaZulu-Natal', 'quill-accessible-forms' ) ),
            array( 'value' => 'LP', 'label' => __( 'Limpopo', 'quill-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Mpumalanga', 'quill-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'Northern Cape', 'quill-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'North West', 'quill-accessible-forms' ) ),
            array( 'value' => 'WC', 'label' => __( 'Western Cape', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Irish counties
     *
     * @return array Irish counties.
     */
    public static function get_ie_counties() {
        return array(
            array( 'value' => 'CW', 'label' => __( 'Carlow', 'quill-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'Cavan', 'quill-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Clare', 'quill-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Cork', 'quill-accessible-forms' ) ),
            array( 'value' => 'DL', 'label' => __( 'Donegal', 'quill-accessible-forms' ) ),
            array( 'value' => 'D', 'label' => __( 'Dublin', 'quill-accessible-forms' ) ),
            array( 'value' => 'G', 'label' => __( 'Galway', 'quill-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Kerry', 'quill-accessible-forms' ) ),
            array( 'value' => 'KE', 'label' => __( 'Kildare', 'quill-accessible-forms' ) ),
            array( 'value' => 'KK', 'label' => __( 'Kilkenny', 'quill-accessible-forms' ) ),
            array( 'value' => 'LS', 'label' => __( 'Laois', 'quill-accessible-forms' ) ),
            array( 'value' => 'LM', 'label' => __( 'Leitrim', 'quill-accessible-forms' ) ),
            array( 'value' => 'LK', 'label' => __( 'Limerick', 'quill-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Longford', 'quill-accessible-forms' ) ),
            array( 'value' => 'LH', 'label' => __( 'Louth', 'quill-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Mayo', 'quill-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Meath', 'quill-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Monaghan', 'quill-accessible-forms' ) ),
            array( 'value' => 'OY', 'label' => __( 'Offaly', 'quill-accessible-forms' ) ),
            array( 'value' => 'RN', 'label' => __( 'Roscommon', 'quill-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Sligo', 'quill-accessible-forms' ) ),
            array( 'value' => 'TA', 'label' => __( 'Tipperary', 'quill-accessible-forms' ) ),
            array( 'value' => 'WD', 'label' => __( 'Waterford', 'quill-accessible-forms' ) ),
            array( 'value' => 'WH', 'label' => __( 'Westmeath', 'quill-accessible-forms' ) ),
            array( 'value' => 'WX', 'label' => __( 'Wexford', 'quill-accessible-forms' ) ),
            array( 'value' => 'WW', 'label' => __( 'Wicklow', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Polish voivodeships
     *
     * @return array Polish voivodeships.
     */
    public static function get_pl_voivodeships() {
        return array(
            array( 'value' => 'DS', 'label' => __( 'Lower Silesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'KP', 'label' => __( 'Kuyavia-Pomerania', 'quill-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Lublin', 'quill-accessible-forms' ) ),
            array( 'value' => 'LB', 'label' => __( 'Lubusz', 'quill-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Łódź', 'quill-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Lesser Poland', 'quill-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Masovia', 'quill-accessible-forms' ) ),
            array( 'value' => 'OP', 'label' => __( 'Opole', 'quill-accessible-forms' ) ),
            array( 'value' => 'PK', 'label' => __( 'Subcarpathia', 'quill-accessible-forms' ) ),
            array( 'value' => 'PD', 'label' => __( 'Podlachia', 'quill-accessible-forms' ) ),
            array( 'value' => 'PM', 'label' => __( 'Pomerania', 'quill-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Silesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Holy Cross', 'quill-accessible-forms' ) ),
            array( 'value' => 'WN', 'label' => __( 'Warmia-Masuria', 'quill-accessible-forms' ) ),
            array( 'value' => 'WP', 'label' => __( 'Greater Poland', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZP', 'label' => __( 'West Pomerania', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get Russian regions (federal subjects)
     *
     * @return array Russian regions.
     */
    public static function get_ru_regions() {
        return array(
            array( 'value' => 'MOW', 'label' => __( 'Moscow', 'quill-accessible-forms' ) ),
            array( 'value' => 'SPE', 'label' => __( 'Saint Petersburg', 'quill-accessible-forms' ) ),
            array( 'value' => 'MOS', 'label' => __( 'Moscow Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'LEN', 'label' => __( 'Leningrad Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'NIZ', 'label' => __( 'Nizhny Novgorod Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'SVE', 'label' => __( 'Sverdlovsk Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'SAM', 'label' => __( 'Samara Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'ROS', 'label' => __( 'Rostov Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bashkortostan', 'quill-accessible-forms' ) ),
            array( 'value' => 'TA', 'label' => __( 'Tatarstan', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHE', 'label' => __( 'Chelyabinsk Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'KDA', 'label' => __( 'Krasnodar Krai', 'quill-accessible-forms' ) ),
            array( 'value' => 'PER', 'label' => __( 'Perm Krai', 'quill-accessible-forms' ) ),
            array( 'value' => 'VOR', 'label' => __( 'Voronezh Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'VGG', 'label' => __( 'Volgograd Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'KYA', 'label' => __( 'Krasnoyarsk Krai', 'quill-accessible-forms' ) ),
            array( 'value' => 'NVS', 'label' => __( 'Novosibirsk Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Saratov Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'TYU', 'label' => __( 'Tyumen Oblast', 'quill-accessible-forms' ) ),
            array( 'value' => 'KEM', 'label' => __( 'Kemerovo Oblast', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get states/provinces for a country (cascading)
     *
     * @param string $country_code Country code.
     * @return array States/provinces.
     */
    public static function get_states_for_country( $country_code = null ) {
        if ( empty( $country_code ) ) {
            return array();
        }

        $code = strtoupper( $country_code );

        $mapping = array(
            'US' => 'get_us_states',
            'CA' => 'get_ca_provinces',
            'GB' => 'get_uk_counties',
            'UK' => 'get_uk_counties',
            'AU' => 'get_au_states',
            'DE' => 'get_de_states',
            'FR' => 'get_fr_regions',
            'IT' => 'get_it_regions',
            'ES' => 'get_es_regions',
            'NL' => 'get_nl_provinces',
            'BE' => 'get_be_provinces',
            'CH' => 'get_ch_cantons',
            'AT' => 'get_at_states',
            'MX' => 'get_mx_states',
            'BR' => 'get_br_states',
            'AR' => 'get_ar_provinces',
            'IN' => 'get_in_states',
            'CN' => 'get_cn_provinces',
            'JP' => 'get_jp_prefectures',
            'KR' => 'get_kr_provinces',
            'NZ' => 'get_nz_regions',
            'ZA' => 'get_za_provinces',
            'IE' => 'get_ie_counties',
            'PL' => 'get_pl_voivodeships',
            'RU' => 'get_ru_regions',
        );

        if ( isset( $mapping[ $code ] ) ) {
            return call_user_func( array( __CLASS__, $mapping[ $code ] ) );
        }

        return array();
    }

    /**
     * Get timezone regions
     *
     * @return array Timezone regions.
     */
    public static function get_timezone_regions() {
        return array(
            array( 'value' => 'Africa', 'label' => __( 'Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'America', 'label' => __( 'Americas', 'quill-accessible-forms' ) ),
            array( 'value' => 'Antarctica', 'label' => __( 'Antarctica', 'quill-accessible-forms' ) ),
            array( 'value' => 'Arctic', 'label' => __( 'Arctic', 'quill-accessible-forms' ) ),
            array( 'value' => 'Asia', 'label' => __( 'Asia', 'quill-accessible-forms' ) ),
            array( 'value' => 'Atlantic', 'label' => __( 'Atlantic', 'quill-accessible-forms' ) ),
            array( 'value' => 'Australia', 'label' => __( 'Australia', 'quill-accessible-forms' ) ),
            array( 'value' => 'Europe', 'label' => __( 'Europe', 'quill-accessible-forms' ) ),
            array( 'value' => 'Indian', 'label' => __( 'Indian Ocean', 'quill-accessible-forms' ) ),
            array( 'value' => 'Pacific', 'label' => __( 'Pacific', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get timezones
     *
     * @return array Timezones.
     */
    public static function get_timezones() {
        $timezones = array();
        $zones = timezone_identifiers_list();

        foreach ( $zones as $zone ) {
            $timezones[] = array(
                'value' => $zone,
                'label' => str_replace( '_', ' ', $zone ),
            );
        }

        return $timezones;
    }

    /**
     * Get timezones by region (cascading)
     *
     * @param string $region Region name.
     * @return array Timezones in region.
     */
    public static function get_timezones_by_region( $region = null ) {
        if ( empty( $region ) ) {
            return array();
        }

        $all_zones = timezone_identifiers_list();
        $filtered = array();

        foreach ( $all_zones as $zone ) {
            if ( strpos( $zone, $region . '/' ) === 0 ) {
                $filtered[] = array(
                    'value' => $zone,
                    'label' => str_replace( array( $region . '/', '_' ), array( '', ' ' ), $zone ),
                );
            }
        }

        return $filtered;
    }

    /**
     * Get UTC offsets
     *
     * @return array UTC offsets.
     */
    public static function get_utc_offsets() {
        $offsets = array();
        for ( $i = -12; $i <= 14; $i++ ) {
            $sign = $i >= 0 ? '+' : '';
            $offsets[] = array(
                'value' => 'UTC' . $sign . $i,
                'label' => 'UTC' . $sign . $i . ':00',
            );
            if ( $i !== 14 && $i !== -12 ) {
                $offsets[] = array(
                    'value' => 'UTC' . $sign . $i . ':30',
                    'label' => 'UTC' . $sign . $i . ':30',
                );
            }
        }
        return $offsets;
    }

    /**
     * Get years (past 100)
     *
     * @param string $parent_value Not used.
     * @return array Years.
     */
    public static function get_years( $parent_value = null ) {
        $current_year = (int) date( 'Y' );
        $years = array();

        for ( $year = $current_year; $year >= $current_year - 100; $year-- ) {
            $years[] = array(
                'value' => (string) $year,
                'label' => (string) $year,
            );
        }

        return $years;
    }

    /**
     * Get future years
     *
     * @param string $parent_value Not used.
     * @return array Years.
     */
    public static function get_years_future( $parent_value = null ) {
        $current_year = (int) date( 'Y' );
        $years = array();

        for ( $year = $current_year; $year <= $current_year + 20; $year++ ) {
            $years[] = array(
                'value' => (string) $year,
                'label' => (string) $year,
            );
        }

        return $years;
    }

    /**
     * Get birth years (for DOB fields)
     *
     * @param string $parent_value Not used.
     * @return array Years.
     */
    public static function get_birth_years( $parent_value = null ) {
        $current_year = (int) date( 'Y' );
        $years = array();

        for ( $year = $current_year - 13; $year >= $current_year - 120; $year-- ) {
            $years[] = array(
                'value' => (string) $year,
                'label' => (string) $year,
            );
        }

        return $years;
    }

    /**
     * Get time slots (hourly)
     *
     * @return array Time slots.
     */
    public static function get_time_slots_hourly() {
        $slots = array();
        for ( $h = 0; $h < 24; $h++ ) {
            $hour = str_pad( $h, 2, '0', STR_PAD_LEFT );
            $label = ( $h < 12 ) ? ( $h === 0 ? '12:00 AM' : $h . ':00 AM' ) : ( $h === 12 ? '12:00 PM' : ( $h - 12 ) . ':00 PM' );
            $slots[] = array(
                'value' => $hour . ':00',
                'label' => $label,
            );
        }
        return $slots;
    }

    /**
     * Get time slots (30 minutes)
     *
     * @return array Time slots.
     */
    public static function get_time_slots_30min() {
        $slots = array();
        for ( $h = 0; $h < 24; $h++ ) {
            foreach ( array( '00', '30' ) as $m ) {
                $hour = str_pad( $h, 2, '0', STR_PAD_LEFT );
                $period = ( $h < 12 ) ? 'AM' : 'PM';
                $display_h = ( $h === 0 || $h === 12 ) ? 12 : ( $h > 12 ? $h - 12 : $h );
                $slots[] = array(
                    'value' => $hour . ':' . $m,
                    'label' => $display_h . ':' . $m . ' ' . $period,
                );
            }
        }
        return $slots;
    }

    /**
     * Get time slots (15 minutes)
     *
     * @return array Time slots.
     */
    public static function get_time_slots_15min() {
        $slots = array();
        for ( $h = 0; $h < 24; $h++ ) {
            foreach ( array( '00', '15', '30', '45' ) as $m ) {
                $hour = str_pad( $h, 2, '0', STR_PAD_LEFT );
                $period = ( $h < 12 ) ? 'AM' : 'PM';
                $display_h = ( $h === 0 || $h === 12 ) ? 12 : ( $h > 12 ? $h - 12 : $h );
                $slots[] = array(
                    'value' => $hour . ':' . $m,
                    'label' => $display_h . ':' . $m . ' ' . $period,
                );
            }
        }
        return $slots;
    }

    /**
     * Get currencies (complete ISO 4217)
     *
     * @return array Currencies.
     */
    public static function get_currencies() {
        return array(
            array( 'value' => 'USD', 'label' => __( 'US Dollar (USD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'EUR', 'label' => __( 'Euro (EUR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'GBP', 'label' => __( 'British Pound (GBP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'JPY', 'label' => __( 'Japanese Yen (JPY)', 'quill-accessible-forms' ) ),
            array( 'value' => 'AUD', 'label' => __( 'Australian Dollar (AUD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAD', 'label' => __( 'Canadian Dollar (CAD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHF', 'label' => __( 'Swiss Franc (CHF)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CNY', 'label' => __( 'Chinese Yuan (CNY)', 'quill-accessible-forms' ) ),
            array( 'value' => 'HKD', 'label' => __( 'Hong Kong Dollar (HKD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'NZD', 'label' => __( 'New Zealand Dollar (NZD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'SEK', 'label' => __( 'Swedish Krona (SEK)', 'quill-accessible-forms' ) ),
            array( 'value' => 'KRW', 'label' => __( 'South Korean Won (KRW)', 'quill-accessible-forms' ) ),
            array( 'value' => 'SGD', 'label' => __( 'Singapore Dollar (SGD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'NOK', 'label' => __( 'Norwegian Krone (NOK)', 'quill-accessible-forms' ) ),
            array( 'value' => 'MXN', 'label' => __( 'Mexican Peso (MXN)', 'quill-accessible-forms' ) ),
            array( 'value' => 'INR', 'label' => __( 'Indian Rupee (INR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'RUB', 'label' => __( 'Russian Ruble (RUB)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ZAR', 'label' => __( 'South African Rand (ZAR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'TRY', 'label' => __( 'Turkish Lira (TRY)', 'quill-accessible-forms' ) ),
            array( 'value' => 'BRL', 'label' => __( 'Brazilian Real (BRL)', 'quill-accessible-forms' ) ),
            array( 'value' => 'TWD', 'label' => __( 'New Taiwan Dollar (TWD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'DKK', 'label' => __( 'Danish Krone (DKK)', 'quill-accessible-forms' ) ),
            array( 'value' => 'PLN', 'label' => __( 'Polish Zloty (PLN)', 'quill-accessible-forms' ) ),
            array( 'value' => 'THB', 'label' => __( 'Thai Baht (THB)', 'quill-accessible-forms' ) ),
            array( 'value' => 'IDR', 'label' => __( 'Indonesian Rupiah (IDR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'HUF', 'label' => __( 'Hungarian Forint (HUF)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CZK', 'label' => __( 'Czech Koruna (CZK)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ILS', 'label' => __( 'Israeli Shekel (ILS)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CLP', 'label' => __( 'Chilean Peso (CLP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'PHP', 'label' => __( 'Philippine Peso (PHP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'AED', 'label' => __( 'UAE Dirham (AED)', 'quill-accessible-forms' ) ),
            array( 'value' => 'COP', 'label' => __( 'Colombian Peso (COP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Saudi Riyal (SAR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'MYR', 'label' => __( 'Malaysian Ringgit (MYR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'RON', 'label' => __( 'Romanian Leu (RON)', 'quill-accessible-forms' ) ),
            array( 'value' => 'PEN', 'label' => __( 'Peruvian Sol (PEN)', 'quill-accessible-forms' ) ),
            array( 'value' => 'VND', 'label' => __( 'Vietnamese Dong (VND)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ARS', 'label' => __( 'Argentine Peso (ARS)', 'quill-accessible-forms' ) ),
            array( 'value' => 'EGP', 'label' => __( 'Egyptian Pound (EGP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'PKR', 'label' => __( 'Pakistani Rupee (PKR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'NGN', 'label' => __( 'Nigerian Naira (NGN)', 'quill-accessible-forms' ) ),
            array( 'value' => 'BDT', 'label' => __( 'Bangladeshi Taka (BDT)', 'quill-accessible-forms' ) ),
            array( 'value' => 'UAH', 'label' => __( 'Ukrainian Hryvnia (UAH)', 'quill-accessible-forms' ) ),
            array( 'value' => 'KZT', 'label' => __( 'Kazakhstani Tenge (KZT)', 'quill-accessible-forms' ) ),
            array( 'value' => 'QAR', 'label' => __( 'Qatari Riyal (QAR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'KWD', 'label' => __( 'Kuwaiti Dinar (KWD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'BHD', 'label' => __( 'Bahraini Dinar (BHD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'OMR', 'label' => __( 'Omani Rial (OMR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'JOD', 'label' => __( 'Jordanian Dinar (JOD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'LKR', 'label' => __( 'Sri Lankan Rupee (LKR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'MAD', 'label' => __( 'Moroccan Dirham (MAD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'KES', 'label' => __( 'Kenyan Shilling (KES)', 'quill-accessible-forms' ) ),
            array( 'value' => 'GHS', 'label' => __( 'Ghanaian Cedi (GHS)', 'quill-accessible-forms' ) ),
            array( 'value' => 'TZS', 'label' => __( 'Tanzanian Shilling (TZS)', 'quill-accessible-forms' ) ),
            array( 'value' => 'UGX', 'label' => __( 'Ugandan Shilling (UGX)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ETB', 'label' => __( 'Ethiopian Birr (ETB)', 'quill-accessible-forms' ) ),
            array( 'value' => 'XAF', 'label' => __( 'Central African CFA (XAF)', 'quill-accessible-forms' ) ),
            array( 'value' => 'XOF', 'label' => __( 'West African CFA (XOF)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get major currencies
     *
     * @return array Major currencies.
     */
    public static function get_currencies_major() {
        return array(
            array( 'value' => 'USD', 'label' => __( 'US Dollar (USD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'EUR', 'label' => __( 'Euro (EUR)', 'quill-accessible-forms' ) ),
            array( 'value' => 'GBP', 'label' => __( 'British Pound (GBP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'JPY', 'label' => __( 'Japanese Yen (JPY)', 'quill-accessible-forms' ) ),
            array( 'value' => 'AUD', 'label' => __( 'Australian Dollar (AUD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CAD', 'label' => __( 'Canadian Dollar (CAD)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CHF', 'label' => __( 'Swiss Franc (CHF)', 'quill-accessible-forms' ) ),
            array( 'value' => 'CNY', 'label' => __( 'Chinese Yuan (CNY)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get cryptocurrencies
     *
     * @return array Cryptocurrencies.
     */
    public static function get_cryptocurrencies() {
        return array(
            array( 'value' => 'BTC', 'label' => __( 'Bitcoin (BTC)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ETH', 'label' => __( 'Ethereum (ETH)', 'quill-accessible-forms' ) ),
            array( 'value' => 'USDT', 'label' => __( 'Tether (USDT)', 'quill-accessible-forms' ) ),
            array( 'value' => 'BNB', 'label' => __( 'Binance Coin (BNB)', 'quill-accessible-forms' ) ),
            array( 'value' => 'USDC', 'label' => __( 'USD Coin (USDC)', 'quill-accessible-forms' ) ),
            array( 'value' => 'XRP', 'label' => __( 'Ripple (XRP)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ADA', 'label' => __( 'Cardano (ADA)', 'quill-accessible-forms' ) ),
            array( 'value' => 'DOGE', 'label' => __( 'Dogecoin (DOGE)', 'quill-accessible-forms' ) ),
            array( 'value' => 'SOL', 'label' => __( 'Solana (SOL)', 'quill-accessible-forms' ) ),
            array( 'value' => 'DOT', 'label' => __( 'Polkadot (DOT)', 'quill-accessible-forms' ) ),
            array( 'value' => 'MATIC', 'label' => __( 'Polygon (MATIC)', 'quill-accessible-forms' ) ),
            array( 'value' => 'LTC', 'label' => __( 'Litecoin (LTC)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get languages (comprehensive)
     *
     * @return array Languages.
     */
    public static function get_languages() {
        return array(
            array( 'value' => 'en', 'label' => __( 'English', 'quill-accessible-forms' ) ),
            array( 'value' => 'zh', 'label' => __( 'Chinese (Mandarin)', 'quill-accessible-forms' ) ),
            array( 'value' => 'hi', 'label' => __( 'Hindi', 'quill-accessible-forms' ) ),
            array( 'value' => 'es', 'label' => __( 'Spanish', 'quill-accessible-forms' ) ),
            array( 'value' => 'fr', 'label' => __( 'French', 'quill-accessible-forms' ) ),
            array( 'value' => 'ar', 'label' => __( 'Arabic', 'quill-accessible-forms' ) ),
            array( 'value' => 'bn', 'label' => __( 'Bengali', 'quill-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Portuguese', 'quill-accessible-forms' ) ),
            array( 'value' => 'ru', 'label' => __( 'Russian', 'quill-accessible-forms' ) ),
            array( 'value' => 'ja', 'label' => __( 'Japanese', 'quill-accessible-forms' ) ),
            array( 'value' => 'de', 'label' => __( 'German', 'quill-accessible-forms' ) ),
            array( 'value' => 'ko', 'label' => __( 'Korean', 'quill-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Italian', 'quill-accessible-forms' ) ),
            array( 'value' => 'tr', 'label' => __( 'Turkish', 'quill-accessible-forms' ) ),
            array( 'value' => 'vi', 'label' => __( 'Vietnamese', 'quill-accessible-forms' ) ),
            array( 'value' => 'pl', 'label' => __( 'Polish', 'quill-accessible-forms' ) ),
            array( 'value' => 'uk', 'label' => __( 'Ukrainian', 'quill-accessible-forms' ) ),
            array( 'value' => 'nl', 'label' => __( 'Dutch', 'quill-accessible-forms' ) ),
            array( 'value' => 'el', 'label' => __( 'Greek', 'quill-accessible-forms' ) ),
            array( 'value' => 'cs', 'label' => __( 'Czech', 'quill-accessible-forms' ) ),
            array( 'value' => 'sv', 'label' => __( 'Swedish', 'quill-accessible-forms' ) ),
            array( 'value' => 'hu', 'label' => __( 'Hungarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'ro', 'label' => __( 'Romanian', 'quill-accessible-forms' ) ),
            array( 'value' => 'th', 'label' => __( 'Thai', 'quill-accessible-forms' ) ),
            array( 'value' => 'id', 'label' => __( 'Indonesian', 'quill-accessible-forms' ) ),
            array( 'value' => 'ms', 'label' => __( 'Malay', 'quill-accessible-forms' ) ),
            array( 'value' => 'da', 'label' => __( 'Danish', 'quill-accessible-forms' ) ),
            array( 'value' => 'fi', 'label' => __( 'Finnish', 'quill-accessible-forms' ) ),
            array( 'value' => 'no', 'label' => __( 'Norwegian', 'quill-accessible-forms' ) ),
            array( 'value' => 'he', 'label' => __( 'Hebrew', 'quill-accessible-forms' ) ),
            array( 'value' => 'fa', 'label' => __( 'Persian (Farsi)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ur', 'label' => __( 'Urdu', 'quill-accessible-forms' ) ),
            array( 'value' => 'ta', 'label' => __( 'Tamil', 'quill-accessible-forms' ) ),
            array( 'value' => 'te', 'label' => __( 'Telugu', 'quill-accessible-forms' ) ),
            array( 'value' => 'mr', 'label' => __( 'Marathi', 'quill-accessible-forms' ) ),
            array( 'value' => 'gu', 'label' => __( 'Gujarati', 'quill-accessible-forms' ) ),
            array( 'value' => 'kn', 'label' => __( 'Kannada', 'quill-accessible-forms' ) ),
            array( 'value' => 'ml', 'label' => __( 'Malayalam', 'quill-accessible-forms' ) ),
            array( 'value' => 'pa', 'label' => __( 'Punjabi', 'quill-accessible-forms' ) ),
            array( 'value' => 'sw', 'label' => __( 'Swahili', 'quill-accessible-forms' ) ),
            array( 'value' => 'tl', 'label' => __( 'Tagalog/Filipino', 'quill-accessible-forms' ) ),
            array( 'value' => 'ca', 'label' => __( 'Catalan', 'quill-accessible-forms' ) ),
            array( 'value' => 'hr', 'label' => __( 'Croatian', 'quill-accessible-forms' ) ),
            array( 'value' => 'sk', 'label' => __( 'Slovak', 'quill-accessible-forms' ) ),
            array( 'value' => 'sl', 'label' => __( 'Slovenian', 'quill-accessible-forms' ) ),
            array( 'value' => 'bg', 'label' => __( 'Bulgarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'sr', 'label' => __( 'Serbian', 'quill-accessible-forms' ) ),
            array( 'value' => 'lt', 'label' => __( 'Lithuanian', 'quill-accessible-forms' ) ),
            array( 'value' => 'lv', 'label' => __( 'Latvian', 'quill-accessible-forms' ) ),
            array( 'value' => 'et', 'label' => __( 'Estonian', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get major languages
     *
     * @return array Major languages.
     */
    public static function get_languages_major() {
        return array(
            array( 'value' => 'en', 'label' => __( 'English', 'quill-accessible-forms' ) ),
            array( 'value' => 'es', 'label' => __( 'Spanish', 'quill-accessible-forms' ) ),
            array( 'value' => 'fr', 'label' => __( 'French', 'quill-accessible-forms' ) ),
            array( 'value' => 'de', 'label' => __( 'German', 'quill-accessible-forms' ) ),
            array( 'value' => 'zh', 'label' => __( 'Chinese', 'quill-accessible-forms' ) ),
            array( 'value' => 'ja', 'label' => __( 'Japanese', 'quill-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Portuguese', 'quill-accessible-forms' ) ),
            array( 'value' => 'ru', 'label' => __( 'Russian', 'quill-accessible-forms' ) ),
            array( 'value' => 'ar', 'label' => __( 'Arabic', 'quill-accessible-forms' ) ),
            array( 'value' => 'hi', 'label' => __( 'Hindi', 'quill-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Italian', 'quill-accessible-forms' ) ),
            array( 'value' => 'ko', 'label' => __( 'Korean', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get phone country codes
     *
     * @return array Phone country codes.
     */
    public static function get_phone_country_codes() {
        return array(
            array( 'value' => '+1', 'label' => __( '+1 (US/Canada)', 'quill-accessible-forms' ) ),
            array( 'value' => '+44', 'label' => __( '+44 (United Kingdom)', 'quill-accessible-forms' ) ),
            array( 'value' => '+61', 'label' => __( '+61 (Australia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+49', 'label' => __( '+49 (Germany)', 'quill-accessible-forms' ) ),
            array( 'value' => '+33', 'label' => __( '+33 (France)', 'quill-accessible-forms' ) ),
            array( 'value' => '+39', 'label' => __( '+39 (Italy)', 'quill-accessible-forms' ) ),
            array( 'value' => '+34', 'label' => __( '+34 (Spain)', 'quill-accessible-forms' ) ),
            array( 'value' => '+31', 'label' => __( '+31 (Netherlands)', 'quill-accessible-forms' ) ),
            array( 'value' => '+32', 'label' => __( '+32 (Belgium)', 'quill-accessible-forms' ) ),
            array( 'value' => '+41', 'label' => __( '+41 (Switzerland)', 'quill-accessible-forms' ) ),
            array( 'value' => '+43', 'label' => __( '+43 (Austria)', 'quill-accessible-forms' ) ),
            array( 'value' => '+46', 'label' => __( '+46 (Sweden)', 'quill-accessible-forms' ) ),
            array( 'value' => '+47', 'label' => __( '+47 (Norway)', 'quill-accessible-forms' ) ),
            array( 'value' => '+45', 'label' => __( '+45 (Denmark)', 'quill-accessible-forms' ) ),
            array( 'value' => '+358', 'label' => __( '+358 (Finland)', 'quill-accessible-forms' ) ),
            array( 'value' => '+353', 'label' => __( '+353 (Ireland)', 'quill-accessible-forms' ) ),
            array( 'value' => '+48', 'label' => __( '+48 (Poland)', 'quill-accessible-forms' ) ),
            array( 'value' => '+351', 'label' => __( '+351 (Portugal)', 'quill-accessible-forms' ) ),
            array( 'value' => '+7', 'label' => __( '+7 (Russia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+81', 'label' => __( '+81 (Japan)', 'quill-accessible-forms' ) ),
            array( 'value' => '+86', 'label' => __( '+86 (China)', 'quill-accessible-forms' ) ),
            array( 'value' => '+82', 'label' => __( '+82 (South Korea)', 'quill-accessible-forms' ) ),
            array( 'value' => '+91', 'label' => __( '+91 (India)', 'quill-accessible-forms' ) ),
            array( 'value' => '+852', 'label' => __( '+852 (Hong Kong)', 'quill-accessible-forms' ) ),
            array( 'value' => '+886', 'label' => __( '+886 (Taiwan)', 'quill-accessible-forms' ) ),
            array( 'value' => '+65', 'label' => __( '+65 (Singapore)', 'quill-accessible-forms' ) ),
            array( 'value' => '+60', 'label' => __( '+60 (Malaysia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+66', 'label' => __( '+66 (Thailand)', 'quill-accessible-forms' ) ),
            array( 'value' => '+62', 'label' => __( '+62 (Indonesia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+63', 'label' => __( '+63 (Philippines)', 'quill-accessible-forms' ) ),
            array( 'value' => '+84', 'label' => __( '+84 (Vietnam)', 'quill-accessible-forms' ) ),
            array( 'value' => '+64', 'label' => __( '+64 (New Zealand)', 'quill-accessible-forms' ) ),
            array( 'value' => '+55', 'label' => __( '+55 (Brazil)', 'quill-accessible-forms' ) ),
            array( 'value' => '+52', 'label' => __( '+52 (Mexico)', 'quill-accessible-forms' ) ),
            array( 'value' => '+54', 'label' => __( '+54 (Argentina)', 'quill-accessible-forms' ) ),
            array( 'value' => '+56', 'label' => __( '+56 (Chile)', 'quill-accessible-forms' ) ),
            array( 'value' => '+57', 'label' => __( '+57 (Colombia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+51', 'label' => __( '+51 (Peru)', 'quill-accessible-forms' ) ),
            array( 'value' => '+27', 'label' => __( '+27 (South Africa)', 'quill-accessible-forms' ) ),
            array( 'value' => '+20', 'label' => __( '+20 (Egypt)', 'quill-accessible-forms' ) ),
            array( 'value' => '+234', 'label' => __( '+234 (Nigeria)', 'quill-accessible-forms' ) ),
            array( 'value' => '+254', 'label' => __( '+254 (Kenya)', 'quill-accessible-forms' ) ),
            array( 'value' => '+971', 'label' => __( '+971 (UAE)', 'quill-accessible-forms' ) ),
            array( 'value' => '+966', 'label' => __( '+966 (Saudi Arabia)', 'quill-accessible-forms' ) ),
            array( 'value' => '+972', 'label' => __( '+972 (Israel)', 'quill-accessible-forms' ) ),
            array( 'value' => '+90', 'label' => __( '+90 (Turkey)', 'quill-accessible-forms' ) ),
            array( 'value' => '+30', 'label' => __( '+30 (Greece)', 'quill-accessible-forms' ) ),
            array( 'value' => '+380', 'label' => __( '+380 (Ukraine)', 'quill-accessible-forms' ) ),
            array( 'value' => '+40', 'label' => __( '+40 (Romania)', 'quill-accessible-forms' ) ),
            array( 'value' => '+36', 'label' => __( '+36 (Hungary)', 'quill-accessible-forms' ) ),
            array( 'value' => '+420', 'label' => __( '+420 (Czech Republic)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get industries (comprehensive)
     *
     * @return array Industries.
     */
    public static function get_industries() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting & Finance', 'quill-accessible-forms' ) ),
            array( 'value' => 'advertising', 'label' => __( 'Advertising & Marketing', 'quill-accessible-forms' ) ),
            array( 'value' => 'aerospace', 'label' => __( 'Aerospace & Defense', 'quill-accessible-forms' ) ),
            array( 'value' => 'agriculture', 'label' => __( 'Agriculture & Farming', 'quill-accessible-forms' ) ),
            array( 'value' => 'architecture', 'label' => __( 'Architecture & Planning', 'quill-accessible-forms' ) ),
            array( 'value' => 'arts', 'label' => __( 'Arts & Entertainment', 'quill-accessible-forms' ) ),
            array( 'value' => 'automotive', 'label' => __( 'Automotive', 'quill-accessible-forms' ) ),
            array( 'value' => 'banking', 'label' => __( 'Banking & Financial Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'biotechnology', 'label' => __( 'Biotechnology', 'quill-accessible-forms' ) ),
            array( 'value' => 'broadcasting', 'label' => __( 'Broadcasting & Media', 'quill-accessible-forms' ) ),
            array( 'value' => 'chemicals', 'label' => __( 'Chemicals', 'quill-accessible-forms' ) ),
            array( 'value' => 'construction', 'label' => __( 'Construction', 'quill-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'quill-accessible-forms' ) ),
            array( 'value' => 'consumer_goods', 'label' => __( 'Consumer Goods', 'quill-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education', 'quill-accessible-forms' ) ),
            array( 'value' => 'electronics', 'label' => __( 'Electronics', 'quill-accessible-forms' ) ),
            array( 'value' => 'energy', 'label' => __( 'Energy & Utilities', 'quill-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'quill-accessible-forms' ) ),
            array( 'value' => 'environmental', 'label' => __( 'Environmental Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'fashion', 'label' => __( 'Fashion & Apparel', 'quill-accessible-forms' ) ),
            array( 'value' => 'food_beverage', 'label' => __( 'Food & Beverage', 'quill-accessible-forms' ) ),
            array( 'value' => 'gaming', 'label' => __( 'Gaming & Casinos', 'quill-accessible-forms' ) ),
            array( 'value' => 'government', 'label' => __( 'Government & Public Sector', 'quill-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'quill-accessible-forms' ) ),
            array( 'value' => 'hospitality', 'label' => __( 'Hospitality & Hotels', 'quill-accessible-forms' ) ),
            array( 'value' => 'human_resources', 'label' => __( 'Human Resources', 'quill-accessible-forms' ) ),
            array( 'value' => 'information_technology', 'label' => __( 'Information Technology', 'quill-accessible-forms' ) ),
            array( 'value' => 'insurance', 'label' => __( 'Insurance', 'quill-accessible-forms' ) ),
            array( 'value' => 'internet', 'label' => __( 'Internet & E-commerce', 'quill-accessible-forms' ) ),
            array( 'value' => 'investment', 'label' => __( 'Investment & Venture Capital', 'quill-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'logistics', 'label' => __( 'Logistics & Supply Chain', 'quill-accessible-forms' ) ),
            array( 'value' => 'machinery', 'label' => __( 'Machinery & Equipment', 'quill-accessible-forms' ) ),
            array( 'value' => 'manufacturing', 'label' => __( 'Manufacturing', 'quill-accessible-forms' ) ),
            array( 'value' => 'maritime', 'label' => __( 'Maritime & Shipping', 'quill-accessible-forms' ) ),
            array( 'value' => 'mining', 'label' => __( 'Mining & Metals', 'quill-accessible-forms' ) ),
            array( 'value' => 'music', 'label' => __( 'Music & Recording', 'quill-accessible-forms' ) ),
            array( 'value' => 'nonprofit', 'label' => __( 'Non-Profit & NGO', 'quill-accessible-forms' ) ),
            array( 'value' => 'oil_gas', 'label' => __( 'Oil & Gas', 'quill-accessible-forms' ) ),
            array( 'value' => 'packaging', 'label' => __( 'Packaging & Containers', 'quill-accessible-forms' ) ),
            array( 'value' => 'pharmaceuticals', 'label' => __( 'Pharmaceuticals', 'quill-accessible-forms' ) ),
            array( 'value' => 'printing', 'label' => __( 'Printing & Publishing', 'quill-accessible-forms' ) ),
            array( 'value' => 'real_estate', 'label' => __( 'Real Estate', 'quill-accessible-forms' ) ),
            array( 'value' => 'recreation', 'label' => __( 'Recreation & Leisure', 'quill-accessible-forms' ) ),
            array( 'value' => 'religious', 'label' => __( 'Religious Organizations', 'quill-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research & Development', 'quill-accessible-forms' ) ),
            array( 'value' => 'restaurants', 'label' => __( 'Restaurants & Food Service', 'quill-accessible-forms' ) ),
            array( 'value' => 'retail', 'label' => __( 'Retail', 'quill-accessible-forms' ) ),
            array( 'value' => 'security', 'label' => __( 'Security & Investigations', 'quill-accessible-forms' ) ),
            array( 'value' => 'semiconductors', 'label' => __( 'Semiconductors', 'quill-accessible-forms' ) ),
            array( 'value' => 'software', 'label' => __( 'Software & SaaS', 'quill-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports & Recreation', 'quill-accessible-forms' ) ),
            array( 'value' => 'staffing', 'label' => __( 'Staffing & Recruiting', 'quill-accessible-forms' ) ),
            array( 'value' => 'telecommunications', 'label' => __( 'Telecommunications', 'quill-accessible-forms' ) ),
            array( 'value' => 'textiles', 'label' => __( 'Textiles', 'quill-accessible-forms' ) ),
            array( 'value' => 'transportation', 'label' => __( 'Transportation', 'quill-accessible-forms' ) ),
            array( 'value' => 'travel', 'label' => __( 'Travel & Tourism', 'quill-accessible-forms' ) ),
            array( 'value' => 'veterinary', 'label' => __( 'Veterinary', 'quill-accessible-forms' ) ),
            array( 'value' => 'warehousing', 'label' => __( 'Warehousing & Storage', 'quill-accessible-forms' ) ),
            array( 'value' => 'wholesale', 'label' => __( 'Wholesale Trade', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get job functions/departments
     *
     * @return array Job functions.
     */
    public static function get_job_functions() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting/Finance', 'quill-accessible-forms' ) ),
            array( 'value' => 'admin', 'label' => __( 'Administrative', 'quill-accessible-forms' ) ),
            array( 'value' => 'art_design', 'label' => __( 'Art/Design', 'quill-accessible-forms' ) ),
            array( 'value' => 'business_dev', 'label' => __( 'Business Development', 'quill-accessible-forms' ) ),
            array( 'value' => 'community', 'label' => __( 'Community/Social Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'quill-accessible-forms' ) ),
            array( 'value' => 'customer_service', 'label' => __( 'Customer Service', 'quill-accessible-forms' ) ),
            array( 'value' => 'data_science', 'label' => __( 'Data Science/Analytics', 'quill-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education/Training', 'quill-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'quill-accessible-forms' ) ),
            array( 'value' => 'executive', 'label' => __( 'Executive/Management', 'quill-accessible-forms' ) ),
            array( 'value' => 'facilities', 'label' => __( 'Facilities/Maintenance', 'quill-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'quill-accessible-forms' ) ),
            array( 'value' => 'hr', 'label' => __( 'Human Resources', 'quill-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Information Technology', 'quill-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal', 'quill-accessible-forms' ) ),
            array( 'value' => 'logistics', 'label' => __( 'Logistics/Supply Chain', 'quill-accessible-forms' ) ),
            array( 'value' => 'manufacturing', 'label' => __( 'Manufacturing/Production', 'quill-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'quill-accessible-forms' ) ),
            array( 'value' => 'media', 'label' => __( 'Media/Communications', 'quill-accessible-forms' ) ),
            array( 'value' => 'military', 'label' => __( 'Military/Protective Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'operations', 'label' => __( 'Operations', 'quill-accessible-forms' ) ),
            array( 'value' => 'product', 'label' => __( 'Product Management', 'quill-accessible-forms' ) ),
            array( 'value' => 'project', 'label' => __( 'Project Management', 'quill-accessible-forms' ) ),
            array( 'value' => 'purchasing', 'label' => __( 'Purchasing/Procurement', 'quill-accessible-forms' ) ),
            array( 'value' => 'qa', 'label' => __( 'Quality Assurance', 'quill-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research', 'quill-accessible-forms' ) ),
            array( 'value' => 'sales', 'label' => __( 'Sales', 'quill-accessible-forms' ) ),
            array( 'value' => 'science', 'label' => __( 'Science', 'quill-accessible-forms' ) ),
            array( 'value' => 'skilled_trade', 'label' => __( 'Skilled Trades', 'quill-accessible-forms' ) ),
            array( 'value' => 'strategy', 'label' => __( 'Strategy/Planning', 'quill-accessible-forms' ) ),
            array( 'value' => 'writing', 'label' => __( 'Writing/Editing', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get fields of study
     *
     * @return array Fields of study.
     */
    public static function get_fields_of_study() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting', 'quill-accessible-forms' ) ),
            array( 'value' => 'agriculture', 'label' => __( 'Agriculture', 'quill-accessible-forms' ) ),
            array( 'value' => 'anthropology', 'label' => __( 'Anthropology', 'quill-accessible-forms' ) ),
            array( 'value' => 'architecture', 'label' => __( 'Architecture', 'quill-accessible-forms' ) ),
            array( 'value' => 'art', 'label' => __( 'Art/Fine Arts', 'quill-accessible-forms' ) ),
            array( 'value' => 'biology', 'label' => __( 'Biology', 'quill-accessible-forms' ) ),
            array( 'value' => 'business', 'label' => __( 'Business Administration', 'quill-accessible-forms' ) ),
            array( 'value' => 'chemistry', 'label' => __( 'Chemistry', 'quill-accessible-forms' ) ),
            array( 'value' => 'communications', 'label' => __( 'Communications', 'quill-accessible-forms' ) ),
            array( 'value' => 'computer_science', 'label' => __( 'Computer Science', 'quill-accessible-forms' ) ),
            array( 'value' => 'criminal_justice', 'label' => __( 'Criminal Justice', 'quill-accessible-forms' ) ),
            array( 'value' => 'design', 'label' => __( 'Design', 'quill-accessible-forms' ) ),
            array( 'value' => 'economics', 'label' => __( 'Economics', 'quill-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education', 'quill-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'quill-accessible-forms' ) ),
            array( 'value' => 'english', 'label' => __( 'English/Literature', 'quill-accessible-forms' ) ),
            array( 'value' => 'environmental', 'label' => __( 'Environmental Science', 'quill-accessible-forms' ) ),
            array( 'value' => 'film', 'label' => __( 'Film/Media Studies', 'quill-accessible-forms' ) ),
            array( 'value' => 'finance', 'label' => __( 'Finance', 'quill-accessible-forms' ) ),
            array( 'value' => 'geography', 'label' => __( 'Geography', 'quill-accessible-forms' ) ),
            array( 'value' => 'health', 'label' => __( 'Health Sciences', 'quill-accessible-forms' ) ),
            array( 'value' => 'history', 'label' => __( 'History', 'quill-accessible-forms' ) ),
            array( 'value' => 'hospitality', 'label' => __( 'Hospitality/Tourism', 'quill-accessible-forms' ) ),
            array( 'value' => 'international', 'label' => __( 'International Relations', 'quill-accessible-forms' ) ),
            array( 'value' => 'journalism', 'label' => __( 'Journalism', 'quill-accessible-forms' ) ),
            array( 'value' => 'law', 'label' => __( 'Law/Legal Studies', 'quill-accessible-forms' ) ),
            array( 'value' => 'linguistics', 'label' => __( 'Linguistics', 'quill-accessible-forms' ) ),
            array( 'value' => 'management', 'label' => __( 'Management', 'quill-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'quill-accessible-forms' ) ),
            array( 'value' => 'mathematics', 'label' => __( 'Mathematics', 'quill-accessible-forms' ) ),
            array( 'value' => 'medicine', 'label' => __( 'Medicine', 'quill-accessible-forms' ) ),
            array( 'value' => 'music', 'label' => __( 'Music', 'quill-accessible-forms' ) ),
            array( 'value' => 'nursing', 'label' => __( 'Nursing', 'quill-accessible-forms' ) ),
            array( 'value' => 'philosophy', 'label' => __( 'Philosophy', 'quill-accessible-forms' ) ),
            array( 'value' => 'physics', 'label' => __( 'Physics', 'quill-accessible-forms' ) ),
            array( 'value' => 'political_science', 'label' => __( 'Political Science', 'quill-accessible-forms' ) ),
            array( 'value' => 'psychology', 'label' => __( 'Psychology', 'quill-accessible-forms' ) ),
            array( 'value' => 'public_admin', 'label' => __( 'Public Administration', 'quill-accessible-forms' ) ),
            array( 'value' => 'social_work', 'label' => __( 'Social Work', 'quill-accessible-forms' ) ),
            array( 'value' => 'sociology', 'label' => __( 'Sociology', 'quill-accessible-forms' ) ),
            array( 'value' => 'theater', 'label' => __( 'Theater/Drama', 'quill-accessible-forms' ) ),
            array( 'value' => 'theology', 'label' => __( 'Theology/Religious Studies', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get medical specialties
     *
     * @return array Medical specialties.
     */
    public static function get_medical_specialties() {
        return array(
            array( 'value' => 'allergy', 'label' => __( 'Allergy & Immunology', 'quill-accessible-forms' ) ),
            array( 'value' => 'anesthesiology', 'label' => __( 'Anesthesiology', 'quill-accessible-forms' ) ),
            array( 'value' => 'cardiology', 'label' => __( 'Cardiology', 'quill-accessible-forms' ) ),
            array( 'value' => 'dermatology', 'label' => __( 'Dermatology', 'quill-accessible-forms' ) ),
            array( 'value' => 'emergency', 'label' => __( 'Emergency Medicine', 'quill-accessible-forms' ) ),
            array( 'value' => 'endocrinology', 'label' => __( 'Endocrinology', 'quill-accessible-forms' ) ),
            array( 'value' => 'family', 'label' => __( 'Family Medicine', 'quill-accessible-forms' ) ),
            array( 'value' => 'gastroenterology', 'label' => __( 'Gastroenterology', 'quill-accessible-forms' ) ),
            array( 'value' => 'general', 'label' => __( 'General Practice', 'quill-accessible-forms' ) ),
            array( 'value' => 'geriatrics', 'label' => __( 'Geriatrics', 'quill-accessible-forms' ) ),
            array( 'value' => 'hematology', 'label' => __( 'Hematology', 'quill-accessible-forms' ) ),
            array( 'value' => 'infectious', 'label' => __( 'Infectious Disease', 'quill-accessible-forms' ) ),
            array( 'value' => 'internal', 'label' => __( 'Internal Medicine', 'quill-accessible-forms' ) ),
            array( 'value' => 'nephrology', 'label' => __( 'Nephrology', 'quill-accessible-forms' ) ),
            array( 'value' => 'neurology', 'label' => __( 'Neurology', 'quill-accessible-forms' ) ),
            array( 'value' => 'obstetrics', 'label' => __( 'Obstetrics & Gynecology', 'quill-accessible-forms' ) ),
            array( 'value' => 'oncology', 'label' => __( 'Oncology', 'quill-accessible-forms' ) ),
            array( 'value' => 'ophthalmology', 'label' => __( 'Ophthalmology', 'quill-accessible-forms' ) ),
            array( 'value' => 'orthopedics', 'label' => __( 'Orthopedics', 'quill-accessible-forms' ) ),
            array( 'value' => 'otolaryngology', 'label' => __( 'Otolaryngology (ENT)', 'quill-accessible-forms' ) ),
            array( 'value' => 'pathology', 'label' => __( 'Pathology', 'quill-accessible-forms' ) ),
            array( 'value' => 'pediatrics', 'label' => __( 'Pediatrics', 'quill-accessible-forms' ) ),
            array( 'value' => 'physical', 'label' => __( 'Physical Medicine', 'quill-accessible-forms' ) ),
            array( 'value' => 'plastic', 'label' => __( 'Plastic Surgery', 'quill-accessible-forms' ) ),
            array( 'value' => 'psychiatry', 'label' => __( 'Psychiatry', 'quill-accessible-forms' ) ),
            array( 'value' => 'pulmonology', 'label' => __( 'Pulmonology', 'quill-accessible-forms' ) ),
            array( 'value' => 'radiology', 'label' => __( 'Radiology', 'quill-accessible-forms' ) ),
            array( 'value' => 'rheumatology', 'label' => __( 'Rheumatology', 'quill-accessible-forms' ) ),
            array( 'value' => 'surgery', 'label' => __( 'Surgery', 'quill-accessible-forms' ) ),
            array( 'value' => 'urology', 'label' => __( 'Urology', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get US shoe sizes
     *
     * @return array US shoe sizes.
     */
    public static function get_shoe_sizes_us() {
        return array(
            array( 'value' => '4', 'label' => __( '4 US', 'quill-accessible-forms' ) ),
            array( 'value' => '4.5', 'label' => __( '4.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '5', 'label' => __( '5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '5.5', 'label' => __( '5.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '6', 'label' => __( '6 US', 'quill-accessible-forms' ) ),
            array( 'value' => '6.5', 'label' => __( '6.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '7', 'label' => __( '7 US', 'quill-accessible-forms' ) ),
            array( 'value' => '7.5', 'label' => __( '7.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '8', 'label' => __( '8 US', 'quill-accessible-forms' ) ),
            array( 'value' => '8.5', 'label' => __( '8.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '9', 'label' => __( '9 US', 'quill-accessible-forms' ) ),
            array( 'value' => '9.5', 'label' => __( '9.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '10', 'label' => __( '10 US', 'quill-accessible-forms' ) ),
            array( 'value' => '10.5', 'label' => __( '10.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '11', 'label' => __( '11 US', 'quill-accessible-forms' ) ),
            array( 'value' => '11.5', 'label' => __( '11.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '12', 'label' => __( '12 US', 'quill-accessible-forms' ) ),
            array( 'value' => '12.5', 'label' => __( '12.5 US', 'quill-accessible-forms' ) ),
            array( 'value' => '13', 'label' => __( '13 US', 'quill-accessible-forms' ) ),
            array( 'value' => '14', 'label' => __( '14 US', 'quill-accessible-forms' ) ),
            array( 'value' => '15', 'label' => __( '15 US', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get EU shoe sizes
     *
     * @return array EU shoe sizes.
     */
    public static function get_shoe_sizes_eu() {
        return array(
            array( 'value' => '35', 'label' => __( '35 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '36', 'label' => __( '36 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '37', 'label' => __( '37 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '38', 'label' => __( '38 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '39', 'label' => __( '39 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '40', 'label' => __( '40 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '41', 'label' => __( '41 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '42', 'label' => __( '42 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '43', 'label' => __( '43 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '44', 'label' => __( '44 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '45', 'label' => __( '45 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '46', 'label' => __( '46 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '47', 'label' => __( '47 EU', 'quill-accessible-forms' ) ),
            array( 'value' => '48', 'label' => __( '48 EU', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get quantities 1-100
     *
     * @return array Quantities.
     */
    public static function get_quantities() {
        $quantities = array();
        for ( $i = 1; $i <= 100; $i++ ) {
            $quantities[] = array( 'value' => (string) $i, 'label' => (string) $i );
        }
        return $quantities;
    }

    /**
     * Get religion/faith options
     *
     * @return array Religions.
     */
    public static function get_religions() {
        return array(
            array( 'value' => 'christianity', 'label' => __( 'Christianity', 'quill-accessible-forms' ) ),
            array( 'value' => 'islam', 'label' => __( 'Islam', 'quill-accessible-forms' ) ),
            array( 'value' => 'hinduism', 'label' => __( 'Hinduism', 'quill-accessible-forms' ) ),
            array( 'value' => 'buddhism', 'label' => __( 'Buddhism', 'quill-accessible-forms' ) ),
            array( 'value' => 'sikhism', 'label' => __( 'Sikhism', 'quill-accessible-forms' ) ),
            array( 'value' => 'judaism', 'label' => __( 'Judaism', 'quill-accessible-forms' ) ),
            array( 'value' => 'bahai', 'label' => __( 'Bahai', 'quill-accessible-forms' ) ),
            array( 'value' => 'jainism', 'label' => __( 'Jainism', 'quill-accessible-forms' ) ),
            array( 'value' => 'shinto', 'label' => __( 'Shinto', 'quill-accessible-forms' ) ),
            array( 'value' => 'confucianism', 'label' => __( 'Confucianism', 'quill-accessible-forms' ) ),
            array( 'value' => 'taoism', 'label' => __( 'Taoism', 'quill-accessible-forms' ) ),
            array( 'value' => 'zoroastrianism', 'label' => __( 'Zoroastrianism', 'quill-accessible-forms' ) ),
            array( 'value' => 'agnostic', 'label' => __( 'Agnostic', 'quill-accessible-forms' ) ),
            array( 'value' => 'atheist', 'label' => __( 'Atheist', 'quill-accessible-forms' ) ),
            array( 'value' => 'spiritual', 'label' => __( 'Spiritual but not religious', 'quill-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get ethnicity options
     *
     * @return array Ethnicities.
     */
    public static function get_ethnicities() {
        return array(
            array( 'value' => 'asian', 'label' => __( 'Asian', 'quill-accessible-forms' ) ),
            array( 'value' => 'black', 'label' => __( 'Black or African American', 'quill-accessible-forms' ) ),
            array( 'value' => 'hispanic', 'label' => __( 'Hispanic or Latino', 'quill-accessible-forms' ) ),
            array( 'value' => 'middle_eastern', 'label' => __( 'Middle Eastern or North African', 'quill-accessible-forms' ) ),
            array( 'value' => 'native_american', 'label' => __( 'Native American or Alaska Native', 'quill-accessible-forms' ) ),
            array( 'value' => 'pacific_islander', 'label' => __( 'Native Hawaiian or Pacific Islander', 'quill-accessible-forms' ) ),
            array( 'value' => 'white', 'label' => __( 'White or Caucasian', 'quill-accessible-forms' ) ),
            array( 'value' => 'multiracial', 'label' => __( 'Two or more races', 'quill-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get political affiliation options
     *
     * @return array Political affiliations.
     */
    public static function get_political_affiliations() {
        return array(
            array( 'value' => 'conservative', 'label' => __( 'Conservative', 'quill-accessible-forms' ) ),
            array( 'value' => 'liberal', 'label' => __( 'Liberal', 'quill-accessible-forms' ) ),
            array( 'value' => 'moderate', 'label' => __( 'Moderate', 'quill-accessible-forms' ) ),
            array( 'value' => 'progressive', 'label' => __( 'Progressive', 'quill-accessible-forms' ) ),
            array( 'value' => 'libertarian', 'label' => __( 'Libertarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'socialist', 'label' => __( 'Socialist', 'quill-accessible-forms' ) ),
            array( 'value' => 'independent', 'label' => __( 'Independent', 'quill-accessible-forms' ) ),
            array( 'value' => 'apolitical', 'label' => __( 'Apolitical', 'quill-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get US cities (major)
     *
     * @return array US cities.
     */
    public static function get_cities_us() {
        return array(
            array( 'value' => 'new_york', 'label' => __( 'New York, NY', 'quill-accessible-forms' ) ),
            array( 'value' => 'los_angeles', 'label' => __( 'Los Angeles, CA', 'quill-accessible-forms' ) ),
            array( 'value' => 'chicago', 'label' => __( 'Chicago, IL', 'quill-accessible-forms' ) ),
            array( 'value' => 'houston', 'label' => __( 'Houston, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'phoenix', 'label' => __( 'Phoenix, AZ', 'quill-accessible-forms' ) ),
            array( 'value' => 'philadelphia', 'label' => __( 'Philadelphia, PA', 'quill-accessible-forms' ) ),
            array( 'value' => 'san_antonio', 'label' => __( 'San Antonio, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'san_diego', 'label' => __( 'San Diego, CA', 'quill-accessible-forms' ) ),
            array( 'value' => 'dallas', 'label' => __( 'Dallas, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'san_jose', 'label' => __( 'San Jose, CA', 'quill-accessible-forms' ) ),
            array( 'value' => 'austin', 'label' => __( 'Austin, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'jacksonville', 'label' => __( 'Jacksonville, FL', 'quill-accessible-forms' ) ),
            array( 'value' => 'fort_worth', 'label' => __( 'Fort Worth, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'columbus', 'label' => __( 'Columbus, OH', 'quill-accessible-forms' ) ),
            array( 'value' => 'charlotte', 'label' => __( 'Charlotte, NC', 'quill-accessible-forms' ) ),
            array( 'value' => 'san_francisco', 'label' => __( 'San Francisco, CA', 'quill-accessible-forms' ) ),
            array( 'value' => 'indianapolis', 'label' => __( 'Indianapolis, IN', 'quill-accessible-forms' ) ),
            array( 'value' => 'seattle', 'label' => __( 'Seattle, WA', 'quill-accessible-forms' ) ),
            array( 'value' => 'denver', 'label' => __( 'Denver, CO', 'quill-accessible-forms' ) ),
            array( 'value' => 'washington', 'label' => __( 'Washington, DC', 'quill-accessible-forms' ) ),
            array( 'value' => 'boston', 'label' => __( 'Boston, MA', 'quill-accessible-forms' ) ),
            array( 'value' => 'el_paso', 'label' => __( 'El Paso, TX', 'quill-accessible-forms' ) ),
            array( 'value' => 'nashville', 'label' => __( 'Nashville, TN', 'quill-accessible-forms' ) ),
            array( 'value' => 'detroit', 'label' => __( 'Detroit, MI', 'quill-accessible-forms' ) ),
            array( 'value' => 'portland', 'label' => __( 'Portland, OR', 'quill-accessible-forms' ) ),
            array( 'value' => 'memphis', 'label' => __( 'Memphis, TN', 'quill-accessible-forms' ) ),
            array( 'value' => 'oklahoma_city', 'label' => __( 'Oklahoma City, OK', 'quill-accessible-forms' ) ),
            array( 'value' => 'las_vegas', 'label' => __( 'Las Vegas, NV', 'quill-accessible-forms' ) ),
            array( 'value' => 'louisville', 'label' => __( 'Louisville, KY', 'quill-accessible-forms' ) ),
            array( 'value' => 'baltimore', 'label' => __( 'Baltimore, MD', 'quill-accessible-forms' ) ),
            array( 'value' => 'milwaukee', 'label' => __( 'Milwaukee, WI', 'quill-accessible-forms' ) ),
            array( 'value' => 'albuquerque', 'label' => __( 'Albuquerque, NM', 'quill-accessible-forms' ) ),
            array( 'value' => 'tucson', 'label' => __( 'Tucson, AZ', 'quill-accessible-forms' ) ),
            array( 'value' => 'fresno', 'label' => __( 'Fresno, CA', 'quill-accessible-forms' ) ),
            array( 'value' => 'atlanta', 'label' => __( 'Atlanta, GA', 'quill-accessible-forms' ) ),
            array( 'value' => 'miami', 'label' => __( 'Miami, FL', 'quill-accessible-forms' ) ),
            array( 'value' => 'new_orleans', 'label' => __( 'New Orleans, LA', 'quill-accessible-forms' ) ),
            array( 'value' => 'cleveland', 'label' => __( 'Cleveland, OH', 'quill-accessible-forms' ) ),
            array( 'value' => 'pittsburgh', 'label' => __( 'Pittsburgh, PA', 'quill-accessible-forms' ) ),
            array( 'value' => 'minneapolis', 'label' => __( 'Minneapolis, MN', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get world cities (major)
     *
     * @return array World cities.
     */
    public static function get_cities_world() {
        return array(
            array( 'value' => 'tokyo', 'label' => __( 'Tokyo, Japan', 'quill-accessible-forms' ) ),
            array( 'value' => 'delhi', 'label' => __( 'Delhi, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'shanghai', 'label' => __( 'Shanghai, China', 'quill-accessible-forms' ) ),
            array( 'value' => 'sao_paulo', 'label' => __( 'São Paulo, Brazil', 'quill-accessible-forms' ) ),
            array( 'value' => 'mexico_city', 'label' => __( 'Mexico City, Mexico', 'quill-accessible-forms' ) ),
            array( 'value' => 'cairo', 'label' => __( 'Cairo, Egypt', 'quill-accessible-forms' ) ),
            array( 'value' => 'mumbai', 'label' => __( 'Mumbai, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'beijing', 'label' => __( 'Beijing, China', 'quill-accessible-forms' ) ),
            array( 'value' => 'dhaka', 'label' => __( 'Dhaka, Bangladesh', 'quill-accessible-forms' ) ),
            array( 'value' => 'osaka', 'label' => __( 'Osaka, Japan', 'quill-accessible-forms' ) ),
            array( 'value' => 'new_york', 'label' => __( 'New York, USA', 'quill-accessible-forms' ) ),
            array( 'value' => 'karachi', 'label' => __( 'Karachi, Pakistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'buenos_aires', 'label' => __( 'Buenos Aires, Argentina', 'quill-accessible-forms' ) ),
            array( 'value' => 'istanbul', 'label' => __( 'Istanbul, Turkey', 'quill-accessible-forms' ) ),
            array( 'value' => 'kolkata', 'label' => __( 'Kolkata, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'manila', 'label' => __( 'Manila, Philippines', 'quill-accessible-forms' ) ),
            array( 'value' => 'lagos', 'label' => __( 'Lagos, Nigeria', 'quill-accessible-forms' ) ),
            array( 'value' => 'rio_de_janeiro', 'label' => __( 'Rio de Janeiro, Brazil', 'quill-accessible-forms' ) ),
            array( 'value' => 'tianjin', 'label' => __( 'Tianjin, China', 'quill-accessible-forms' ) ),
            array( 'value' => 'kinshasa', 'label' => __( 'Kinshasa, DR Congo', 'quill-accessible-forms' ) ),
            array( 'value' => 'guangzhou', 'label' => __( 'Guangzhou, China', 'quill-accessible-forms' ) ),
            array( 'value' => 'los_angeles', 'label' => __( 'Los Angeles, USA', 'quill-accessible-forms' ) ),
            array( 'value' => 'moscow', 'label' => __( 'Moscow, Russia', 'quill-accessible-forms' ) ),
            array( 'value' => 'shenzhen', 'label' => __( 'Shenzhen, China', 'quill-accessible-forms' ) ),
            array( 'value' => 'lahore', 'label' => __( 'Lahore, Pakistan', 'quill-accessible-forms' ) ),
            array( 'value' => 'bangalore', 'label' => __( 'Bangalore, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'paris', 'label' => __( 'Paris, France', 'quill-accessible-forms' ) ),
            array( 'value' => 'bogota', 'label' => __( 'Bogotá, Colombia', 'quill-accessible-forms' ) ),
            array( 'value' => 'jakarta', 'label' => __( 'Jakarta, Indonesia', 'quill-accessible-forms' ) ),
            array( 'value' => 'chennai', 'label' => __( 'Chennai, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'lima', 'label' => __( 'Lima, Peru', 'quill-accessible-forms' ) ),
            array( 'value' => 'bangkok', 'label' => __( 'Bangkok, Thailand', 'quill-accessible-forms' ) ),
            array( 'value' => 'seoul', 'label' => __( 'Seoul, South Korea', 'quill-accessible-forms' ) ),
            array( 'value' => 'hyderabad', 'label' => __( 'Hyderabad, India', 'quill-accessible-forms' ) ),
            array( 'value' => 'london', 'label' => __( 'London, UK', 'quill-accessible-forms' ) ),
            array( 'value' => 'tehran', 'label' => __( 'Tehran, Iran', 'quill-accessible-forms' ) ),
            array( 'value' => 'hong_kong', 'label' => __( 'Hong Kong', 'quill-accessible-forms' ) ),
            array( 'value' => 'ho_chi_minh', 'label' => __( 'Ho Chi Minh City, Vietnam', 'quill-accessible-forms' ) ),
            array( 'value' => 'singapore', 'label' => __( 'Singapore', 'quill-accessible-forms' ) ),
            array( 'value' => 'sydney', 'label' => __( 'Sydney, Australia', 'quill-accessible-forms' ) ),
            array( 'value' => 'toronto', 'label' => __( 'Toronto, Canada', 'quill-accessible-forms' ) ),
            array( 'value' => 'berlin', 'label' => __( 'Berlin, Germany', 'quill-accessible-forms' ) ),
            array( 'value' => 'madrid', 'label' => __( 'Madrid, Spain', 'quill-accessible-forms' ) ),
            array( 'value' => 'rome', 'label' => __( 'Rome, Italy', 'quill-accessible-forms' ) ),
            array( 'value' => 'amsterdam', 'label' => __( 'Amsterdam, Netherlands', 'quill-accessible-forms' ) ),
            array( 'value' => 'dubai', 'label' => __( 'Dubai, UAE', 'quill-accessible-forms' ) ),
            array( 'value' => 'cape_town', 'label' => __( 'Cape Town, South Africa', 'quill-accessible-forms' ) ),
            array( 'value' => 'melbourne', 'label' => __( 'Melbourne, Australia', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get appointment types
     *
     * @return array Appointment types.
     */
    public static function get_appointment_types() {
        return array(
            array( 'value' => 'consultation', 'label' => __( 'Consultation', 'quill-accessible-forms' ) ),
            array( 'value' => 'follow_up', 'label' => __( 'Follow-up', 'quill-accessible-forms' ) ),
            array( 'value' => 'initial', 'label' => __( 'Initial Visit', 'quill-accessible-forms' ) ),
            array( 'value' => 'checkup', 'label' => __( 'Check-up', 'quill-accessible-forms' ) ),
            array( 'value' => 'emergency', 'label' => __( 'Emergency', 'quill-accessible-forms' ) ),
            array( 'value' => 'procedure', 'label' => __( 'Procedure', 'quill-accessible-forms' ) ),
            array( 'value' => 'screening', 'label' => __( 'Screening', 'quill-accessible-forms' ) ),
            array( 'value' => 'vaccination', 'label' => __( 'Vaccination', 'quill-accessible-forms' ) ),
            array( 'value' => 'lab_work', 'label' => __( 'Lab Work', 'quill-accessible-forms' ) ),
            array( 'value' => 'imaging', 'label' => __( 'Imaging/X-Ray', 'quill-accessible-forms' ) ),
            array( 'value' => 'telehealth', 'label' => __( 'Telehealth/Virtual', 'quill-accessible-forms' ) ),
            array( 'value' => 'therapy', 'label' => __( 'Therapy Session', 'quill-accessible-forms' ) ),
            array( 'value' => 'dental', 'label' => __( 'Dental', 'quill-accessible-forms' ) ),
            array( 'value' => 'eye_exam', 'label' => __( 'Eye Exam', 'quill-accessible-forms' ) ),
            array( 'value' => 'physical', 'label' => __( 'Physical Exam', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get service categories
     *
     * @return array Service categories.
     */
    public static function get_service_categories() {
        return array(
            array( 'value' => 'automotive', 'label' => __( 'Automotive', 'quill-accessible-forms' ) ),
            array( 'value' => 'beauty', 'label' => __( 'Beauty & Spa', 'quill-accessible-forms' ) ),
            array( 'value' => 'cleaning', 'label' => __( 'Cleaning', 'quill-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'quill-accessible-forms' ) ),
            array( 'value' => 'delivery', 'label' => __( 'Delivery', 'quill-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education & Tutoring', 'quill-accessible-forms' ) ),
            array( 'value' => 'electrical', 'label' => __( 'Electrical', 'quill-accessible-forms' ) ),
            array( 'value' => 'events', 'label' => __( 'Events & Entertainment', 'quill-accessible-forms' ) ),
            array( 'value' => 'financial', 'label' => __( 'Financial', 'quill-accessible-forms' ) ),
            array( 'value' => 'fitness', 'label' => __( 'Fitness & Training', 'quill-accessible-forms' ) ),
            array( 'value' => 'handyman', 'label' => __( 'Handyman', 'quill-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'quill-accessible-forms' ) ),
            array( 'value' => 'home_improvement', 'label' => __( 'Home Improvement', 'quill-accessible-forms' ) ),
            array( 'value' => 'landscaping', 'label' => __( 'Landscaping', 'quill-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal', 'quill-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'quill-accessible-forms' ) ),
            array( 'value' => 'moving', 'label' => __( 'Moving & Storage', 'quill-accessible-forms' ) ),
            array( 'value' => 'pest_control', 'label' => __( 'Pest Control', 'quill-accessible-forms' ) ),
            array( 'value' => 'pet_services', 'label' => __( 'Pet Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'photography', 'label' => __( 'Photography', 'quill-accessible-forms' ) ),
            array( 'value' => 'plumbing', 'label' => __( 'Plumbing', 'quill-accessible-forms' ) ),
            array( 'value' => 'real_estate', 'label' => __( 'Real Estate', 'quill-accessible-forms' ) ),
            array( 'value' => 'repair', 'label' => __( 'Repair Services', 'quill-accessible-forms' ) ),
            array( 'value' => 'security', 'label' => __( 'Security', 'quill-accessible-forms' ) ),
            array( 'value' => 'technology', 'label' => __( 'Technology & IT', 'quill-accessible-forms' ) ),
            array( 'value' => 'transportation', 'label' => __( 'Transportation', 'quill-accessible-forms' ) ),
            array( 'value' => 'travel', 'label' => __( 'Travel', 'quill-accessible-forms' ) ),
            array( 'value' => 'writing', 'label' => __( 'Writing & Translation', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get project types
     *
     * @return array Project types.
     */
    public static function get_project_types() {
        return array(
            array( 'value' => 'new_build', 'label' => __( 'New Build', 'quill-accessible-forms' ) ),
            array( 'value' => 'renovation', 'label' => __( 'Renovation', 'quill-accessible-forms' ) ),
            array( 'value' => 'repair', 'label' => __( 'Repair', 'quill-accessible-forms' ) ),
            array( 'value' => 'maintenance', 'label' => __( 'Maintenance', 'quill-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'quill-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research', 'quill-accessible-forms' ) ),
            array( 'value' => 'development', 'label' => __( 'Development', 'quill-accessible-forms' ) ),
            array( 'value' => 'design', 'label' => __( 'Design', 'quill-accessible-forms' ) ),
            array( 'value' => 'implementation', 'label' => __( 'Implementation', 'quill-accessible-forms' ) ),
            array( 'value' => 'migration', 'label' => __( 'Migration', 'quill-accessible-forms' ) ),
            array( 'value' => 'integration', 'label' => __( 'Integration', 'quill-accessible-forms' ) ),
            array( 'value' => 'training', 'label' => __( 'Training', 'quill-accessible-forms' ) ),
            array( 'value' => 'audit', 'label' => __( 'Audit', 'quill-accessible-forms' ) ),
            array( 'value' => 'assessment', 'label' => __( 'Assessment', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get budget ranges
     *
     * @return array Budget ranges.
     */
    public static function get_budget_ranges() {
        return array(
            array( 'value' => 'under_1k', 'label' => __( 'Under $1,000', 'quill-accessible-forms' ) ),
            array( 'value' => '1k_5k', 'label' => __( '$1,000 - $5,000', 'quill-accessible-forms' ) ),
            array( 'value' => '5k_10k', 'label' => __( '$5,000 - $10,000', 'quill-accessible-forms' ) ),
            array( 'value' => '10k_25k', 'label' => __( '$10,000 - $25,000', 'quill-accessible-forms' ) ),
            array( 'value' => '25k_50k', 'label' => __( '$25,000 - $50,000', 'quill-accessible-forms' ) ),
            array( 'value' => '50k_100k', 'label' => __( '$50,000 - $100,000', 'quill-accessible-forms' ) ),
            array( 'value' => '100k_250k', 'label' => __( '$100,000 - $250,000', 'quill-accessible-forms' ) ),
            array( 'value' => '250k_500k', 'label' => __( '$250,000 - $500,000', 'quill-accessible-forms' ) ),
            array( 'value' => '500k_1m', 'label' => __( '$500,000 - $1,000,000', 'quill-accessible-forms' ) ),
            array( 'value' => 'over_1m', 'label' => __( 'Over $1,000,000', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get timeline options
     *
     * @return array Timelines.
     */
    public static function get_timelines() {
        return array(
            array( 'value' => 'asap', 'label' => __( 'As soon as possible', 'quill-accessible-forms' ) ),
            array( 'value' => '1_week', 'label' => __( 'Within 1 week', 'quill-accessible-forms' ) ),
            array( 'value' => '2_weeks', 'label' => __( 'Within 2 weeks', 'quill-accessible-forms' ) ),
            array( 'value' => '1_month', 'label' => __( 'Within 1 month', 'quill-accessible-forms' ) ),
            array( 'value' => '2_3_months', 'label' => __( '2-3 months', 'quill-accessible-forms' ) ),
            array( 'value' => '3_6_months', 'label' => __( '3-6 months', 'quill-accessible-forms' ) ),
            array( 'value' => '6_12_months', 'label' => __( '6-12 months', 'quill-accessible-forms' ) ),
            array( 'value' => 'over_1_year', 'label' => __( 'More than 1 year', 'quill-accessible-forms' ) ),
            array( 'value' => 'flexible', 'label' => __( 'Flexible', 'quill-accessible-forms' ) ),
            array( 'value' => 'not_sure', 'label' => __( 'Not sure yet', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get company size options
     *
     * @return array Company sizes.
     */
    public static function get_company_sizes() {
        return array(
            array( 'value' => '1', 'label' => __( 'Self-employed', 'quill-accessible-forms' ) ),
            array( 'value' => '2_10', 'label' => __( '2-10 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '11_50', 'label' => __( '11-50 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '51_200', 'label' => __( '51-200 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '201_500', 'label' => __( '201-500 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '501_1000', 'label' => __( '501-1,000 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '1001_5000', 'label' => __( '1,001-5,000 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '5001_10000', 'label' => __( '5,001-10,000 employees', 'quill-accessible-forms' ) ),
            array( 'value' => '10001_plus', 'label' => __( '10,001+ employees', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get annual revenue options
     *
     * @return array Annual revenue ranges.
     */
    public static function get_annual_revenue() {
        return array(
            array( 'value' => 'under_100k', 'label' => __( 'Under $100,000', 'quill-accessible-forms' ) ),
            array( 'value' => '100k_500k', 'label' => __( '$100,000 - $500,000', 'quill-accessible-forms' ) ),
            array( 'value' => '500k_1m', 'label' => __( '$500,000 - $1 million', 'quill-accessible-forms' ) ),
            array( 'value' => '1m_5m', 'label' => __( '$1 million - $5 million', 'quill-accessible-forms' ) ),
            array( 'value' => '5m_10m', 'label' => __( '$5 million - $10 million', 'quill-accessible-forms' ) ),
            array( 'value' => '10m_50m', 'label' => __( '$10 million - $50 million', 'quill-accessible-forms' ) ),
            array( 'value' => '50m_100m', 'label' => __( '$50 million - $100 million', 'quill-accessible-forms' ) ),
            array( 'value' => '100m_500m', 'label' => __( '$100 million - $500 million', 'quill-accessible-forms' ) ),
            array( 'value' => '500m_1b', 'label' => __( '$500 million - $1 billion', 'quill-accessible-forms' ) ),
            array( 'value' => 'over_1b', 'label' => __( 'Over $1 billion', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get lead source options
     *
     * @return array Lead sources.
     */
    public static function get_lead_sources() {
        return array(
            array( 'value' => 'google', 'label' => __( 'Google Search', 'quill-accessible-forms' ) ),
            array( 'value' => 'bing', 'label' => __( 'Bing Search', 'quill-accessible-forms' ) ),
            array( 'value' => 'facebook', 'label' => __( 'Facebook', 'quill-accessible-forms' ) ),
            array( 'value' => 'instagram', 'label' => __( 'Instagram', 'quill-accessible-forms' ) ),
            array( 'value' => 'twitter', 'label' => __( 'Twitter/X', 'quill-accessible-forms' ) ),
            array( 'value' => 'linkedin', 'label' => __( 'LinkedIn', 'quill-accessible-forms' ) ),
            array( 'value' => 'youtube', 'label' => __( 'YouTube', 'quill-accessible-forms' ) ),
            array( 'value' => 'tiktok', 'label' => __( 'TikTok', 'quill-accessible-forms' ) ),
            array( 'value' => 'pinterest', 'label' => __( 'Pinterest', 'quill-accessible-forms' ) ),
            array( 'value' => 'reddit', 'label' => __( 'Reddit', 'quill-accessible-forms' ) ),
            array( 'value' => 'referral', 'label' => __( 'Referral/Word of mouth', 'quill-accessible-forms' ) ),
            array( 'value' => 'email', 'label' => __( 'Email marketing', 'quill-accessible-forms' ) ),
            array( 'value' => 'blog', 'label' => __( 'Blog/Article', 'quill-accessible-forms' ) ),
            array( 'value' => 'podcast', 'label' => __( 'Podcast', 'quill-accessible-forms' ) ),
            array( 'value' => 'webinar', 'label' => __( 'Webinar', 'quill-accessible-forms' ) ),
            array( 'value' => 'conference', 'label' => __( 'Conference/Event', 'quill-accessible-forms' ) ),
            array( 'value' => 'print_ad', 'label' => __( 'Print advertisement', 'quill-accessible-forms' ) ),
            array( 'value' => 'tv_radio', 'label' => __( 'TV/Radio', 'quill-accessible-forms' ) ),
            array( 'value' => 'billboard', 'label' => __( 'Billboard/Outdoor', 'quill-accessible-forms' ) ),
            array( 'value' => 'direct_mail', 'label' => __( 'Direct mail', 'quill-accessible-forms' ) ),
            array( 'value' => 'cold_call', 'label' => __( 'Cold call', 'quill-accessible-forms' ) ),
            array( 'value' => 'partner', 'label' => __( 'Partner/Affiliate', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get contact preference options
     *
     * @return array Contact preferences.
     */
    public static function get_contact_preferences() {
        return array(
            array( 'value' => 'email', 'label' => __( 'Email', 'quill-accessible-forms' ) ),
            array( 'value' => 'phone', 'label' => __( 'Phone call', 'quill-accessible-forms' ) ),
            array( 'value' => 'sms', 'label' => __( 'Text message (SMS)', 'quill-accessible-forms' ) ),
            array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'quill-accessible-forms' ) ),
            array( 'value' => 'video', 'label' => __( 'Video call', 'quill-accessible-forms' ) ),
            array( 'value' => 'mail', 'label' => __( 'Postal mail', 'quill-accessible-forms' ) ),
            array( 'value' => 'in_person', 'label' => __( 'In person', 'quill-accessible-forms' ) ),
            array( 'value' => 'no_preference', 'label' => __( 'No preference', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get preferred contact time options
     *
     * @return array Preferred contact times.
     */
    public static function get_contact_times() {
        return array(
            array( 'value' => 'morning', 'label' => __( 'Morning (8am - 12pm)', 'quill-accessible-forms' ) ),
            array( 'value' => 'afternoon', 'label' => __( 'Afternoon (12pm - 5pm)', 'quill-accessible-forms' ) ),
            array( 'value' => 'evening', 'label' => __( 'Evening (5pm - 8pm)', 'quill-accessible-forms' ) ),
            array( 'value' => 'weekday', 'label' => __( 'Weekdays only', 'quill-accessible-forms' ) ),
            array( 'value' => 'weekend', 'label' => __( 'Weekends only', 'quill-accessible-forms' ) ),
            array( 'value' => 'anytime', 'label' => __( 'Anytime', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get relationship status options
     *
     * @return array Relationship statuses.
     */
    public static function get_relationship_statuses() {
        return array(
            array( 'value' => 'single', 'label' => __( 'Single', 'quill-accessible-forms' ) ),
            array( 'value' => 'in_relationship', 'label' => __( 'In a relationship', 'quill-accessible-forms' ) ),
            array( 'value' => 'engaged', 'label' => __( 'Engaged', 'quill-accessible-forms' ) ),
            array( 'value' => 'married', 'label' => __( 'Married', 'quill-accessible-forms' ) ),
            array( 'value' => 'domestic', 'label' => __( 'Domestic partnership', 'quill-accessible-forms' ) ),
            array( 'value' => 'separated', 'label' => __( 'Separated', 'quill-accessible-forms' ) ),
            array( 'value' => 'divorced', 'label' => __( 'Divorced', 'quill-accessible-forms' ) ),
            array( 'value' => 'widowed', 'label' => __( 'Widowed', 'quill-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get pet types
     *
     * @return array Pet types.
     */
    public static function get_pet_types() {
        return array(
            array( 'value' => 'dog', 'label' => __( 'Dog', 'quill-accessible-forms' ) ),
            array( 'value' => 'cat', 'label' => __( 'Cat', 'quill-accessible-forms' ) ),
            array( 'value' => 'bird', 'label' => __( 'Bird', 'quill-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish', 'quill-accessible-forms' ) ),
            array( 'value' => 'rabbit', 'label' => __( 'Rabbit', 'quill-accessible-forms' ) ),
            array( 'value' => 'hamster', 'label' => __( 'Hamster', 'quill-accessible-forms' ) ),
            array( 'value' => 'guinea_pig', 'label' => __( 'Guinea pig', 'quill-accessible-forms' ) ),
            array( 'value' => 'reptile', 'label' => __( 'Reptile', 'quill-accessible-forms' ) ),
            array( 'value' => 'turtle', 'label' => __( 'Turtle/Tortoise', 'quill-accessible-forms' ) ),
            array( 'value' => 'horse', 'label' => __( 'Horse', 'quill-accessible-forms' ) ),
            array( 'value' => 'ferret', 'label' => __( 'Ferret', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
            array( 'value' => 'none', 'label' => __( 'No pets', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get dietary restrictions
     *
     * @return array Dietary restrictions.
     */
    public static function get_dietary_restrictions() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'quill-accessible-forms' ) ),
            array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'vegan', 'label' => __( 'Vegan', 'quill-accessible-forms' ) ),
            array( 'value' => 'pescatarian', 'label' => __( 'Pescatarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'gluten_free', 'label' => __( 'Gluten-free', 'quill-accessible-forms' ) ),
            array( 'value' => 'dairy_free', 'label' => __( 'Dairy-free', 'quill-accessible-forms' ) ),
            array( 'value' => 'nut_free', 'label' => __( 'Nut-free', 'quill-accessible-forms' ) ),
            array( 'value' => 'kosher', 'label' => __( 'Kosher', 'quill-accessible-forms' ) ),
            array( 'value' => 'halal', 'label' => __( 'Halal', 'quill-accessible-forms' ) ),
            array( 'value' => 'keto', 'label' => __( 'Keto', 'quill-accessible-forms' ) ),
            array( 'value' => 'paleo', 'label' => __( 'Paleo', 'quill-accessible-forms' ) ),
            array( 'value' => 'low_sodium', 'label' => __( 'Low sodium', 'quill-accessible-forms' ) ),
            array( 'value' => 'low_sugar', 'label' => __( 'Low sugar/Diabetic', 'quill-accessible-forms' ) ),
            array( 'value' => 'raw', 'label' => __( 'Raw food', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get allergens
     *
     * @return array Allergens.
     */
    public static function get_allergens() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'quill-accessible-forms' ) ),
            array( 'value' => 'peanuts', 'label' => __( 'Peanuts', 'quill-accessible-forms' ) ),
            array( 'value' => 'tree_nuts', 'label' => __( 'Tree nuts', 'quill-accessible-forms' ) ),
            array( 'value' => 'milk', 'label' => __( 'Milk/Dairy', 'quill-accessible-forms' ) ),
            array( 'value' => 'eggs', 'label' => __( 'Eggs', 'quill-accessible-forms' ) ),
            array( 'value' => 'wheat', 'label' => __( 'Wheat', 'quill-accessible-forms' ) ),
            array( 'value' => 'soy', 'label' => __( 'Soy', 'quill-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish', 'quill-accessible-forms' ) ),
            array( 'value' => 'shellfish', 'label' => __( 'Shellfish', 'quill-accessible-forms' ) ),
            array( 'value' => 'sesame', 'label' => __( 'Sesame', 'quill-accessible-forms' ) ),
            array( 'value' => 'gluten', 'label' => __( 'Gluten', 'quill-accessible-forms' ) ),
            array( 'value' => 'sulfites', 'label' => __( 'Sulfites', 'quill-accessible-forms' ) ),
            array( 'value' => 'mustard', 'label' => __( 'Mustard', 'quill-accessible-forms' ) ),
            array( 'value' => 'celery', 'label' => __( 'Celery', 'quill-accessible-forms' ) ),
            array( 'value' => 'lupin', 'label' => __( 'Lupin', 'quill-accessible-forms' ) ),
            array( 'value' => 'mollusks', 'label' => __( 'Mollusks', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get meal preferences
     *
     * @return array Meal preferences.
     */
    public static function get_meal_preferences() {
        return array(
            array( 'value' => 'meat', 'label' => __( 'Meat/Chicken', 'quill-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish/Seafood', 'quill-accessible-forms' ) ),
            array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'quill-accessible-forms' ) ),
            array( 'value' => 'vegan', 'label' => __( 'Vegan', 'quill-accessible-forms' ) ),
            array( 'value' => 'kids', 'label' => __( 'Kids meal', 'quill-accessible-forms' ) ),
            array( 'value' => 'no_preference', 'label' => __( 'No preference', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get T-shirt sizes (standard)
     *
     * @return array T-shirt sizes.
     */
    public static function get_tshirt_sizes() {
        return array(
            array( 'value' => 'xs', 'label' => __( 'XS (Extra Small)', 'quill-accessible-forms' ) ),
            array( 'value' => 's', 'label' => __( 'S (Small)', 'quill-accessible-forms' ) ),
            array( 'value' => 'm', 'label' => __( 'M (Medium)', 'quill-accessible-forms' ) ),
            array( 'value' => 'l', 'label' => __( 'L (Large)', 'quill-accessible-forms' ) ),
            array( 'value' => 'xl', 'label' => __( 'XL (Extra Large)', 'quill-accessible-forms' ) ),
            array( 'value' => '2xl', 'label' => __( '2XL', 'quill-accessible-forms' ) ),
            array( 'value' => '3xl', 'label' => __( '3XL', 'quill-accessible-forms' ) ),
            array( 'value' => '4xl', 'label' => __( '4XL', 'quill-accessible-forms' ) ),
            array( 'value' => '5xl', 'label' => __( '5XL', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get accessibility needs
     *
     * @return array Accessibility needs.
     */
    public static function get_accessibility_needs() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'quill-accessible-forms' ) ),
            array( 'value' => 'wheelchair', 'label' => __( 'Wheelchair access', 'quill-accessible-forms' ) ),
            array( 'value' => 'mobility', 'label' => __( 'Mobility assistance', 'quill-accessible-forms' ) ),
            array( 'value' => 'visual', 'label' => __( 'Visual impairment', 'quill-accessible-forms' ) ),
            array( 'value' => 'hearing', 'label' => __( 'Hearing impairment', 'quill-accessible-forms' ) ),
            array( 'value' => 'sign_language', 'label' => __( 'Sign language interpreter', 'quill-accessible-forms' ) ),
            array( 'value' => 'service_animal', 'label' => __( 'Service animal', 'quill-accessible-forms' ) ),
            array( 'value' => 'large_print', 'label' => __( 'Large print materials', 'quill-accessible-forms' ) ),
            array( 'value' => 'braille', 'label' => __( 'Braille materials', 'quill-accessible-forms' ) ),
            array( 'value' => 'captioning', 'label' => __( 'Captioning/Subtitles', 'quill-accessible-forms' ) ),
            array( 'value' => 'audio_description', 'label' => __( 'Audio description', 'quill-accessible-forms' ) ),
            array( 'value' => 'quiet_space', 'label' => __( 'Quiet/Low-stimulation space', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get payment methods
     *
     * @return array Payment methods.
     */
    public static function get_payment_methods() {
        return array(
            array( 'value' => 'credit_card', 'label' => __( 'Credit card', 'quill-accessible-forms' ) ),
            array( 'value' => 'debit_card', 'label' => __( 'Debit card', 'quill-accessible-forms' ) ),
            array( 'value' => 'paypal', 'label' => __( 'PayPal', 'quill-accessible-forms' ) ),
            array( 'value' => 'apple_pay', 'label' => __( 'Apple Pay', 'quill-accessible-forms' ) ),
            array( 'value' => 'google_pay', 'label' => __( 'Google Pay', 'quill-accessible-forms' ) ),
            array( 'value' => 'venmo', 'label' => __( 'Venmo', 'quill-accessible-forms' ) ),
            array( 'value' => 'bank_transfer', 'label' => __( 'Bank transfer', 'quill-accessible-forms' ) ),
            array( 'value' => 'wire_transfer', 'label' => __( 'Wire transfer', 'quill-accessible-forms' ) ),
            array( 'value' => 'check', 'label' => __( 'Check', 'quill-accessible-forms' ) ),
            array( 'value' => 'cash', 'label' => __( 'Cash', 'quill-accessible-forms' ) ),
            array( 'value' => 'crypto', 'label' => __( 'Cryptocurrency', 'quill-accessible-forms' ) ),
            array( 'value' => 'invoice', 'label' => __( 'Invoice/Net terms', 'quill-accessible-forms' ) ),
            array( 'value' => 'financing', 'label' => __( 'Financing', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get shipping methods
     *
     * @return array Shipping methods.
     */
    public static function get_shipping_methods() {
        return array(
            array( 'value' => 'standard', 'label' => __( 'Standard shipping', 'quill-accessible-forms' ) ),
            array( 'value' => 'express', 'label' => __( 'Express shipping', 'quill-accessible-forms' ) ),
            array( 'value' => 'overnight', 'label' => __( 'Overnight shipping', 'quill-accessible-forms' ) ),
            array( 'value' => 'two_day', 'label' => __( '2-day shipping', 'quill-accessible-forms' ) ),
            array( 'value' => 'same_day', 'label' => __( 'Same-day delivery', 'quill-accessible-forms' ) ),
            array( 'value' => 'pickup', 'label' => __( 'In-store pickup', 'quill-accessible-forms' ) ),
            array( 'value' => 'locker', 'label' => __( 'Locker pickup', 'quill-accessible-forms' ) ),
            array( 'value' => 'curbside', 'label' => __( 'Curbside pickup', 'quill-accessible-forms' ) ),
            array( 'value' => 'freight', 'label' => __( 'Freight', 'quill-accessible-forms' ) ),
            array( 'value' => 'international', 'label' => __( 'International', 'quill-accessible-forms' ) ),
            array( 'value' => 'digital', 'label' => __( 'Digital delivery', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get subscription frequencies
     *
     * @return array Subscription frequencies.
     */
    public static function get_subscription_frequencies() {
        return array(
            array( 'value' => 'weekly', 'label' => __( 'Weekly', 'quill-accessible-forms' ) ),
            array( 'value' => 'biweekly', 'label' => __( 'Every 2 weeks', 'quill-accessible-forms' ) ),
            array( 'value' => 'monthly', 'label' => __( 'Monthly', 'quill-accessible-forms' ) ),
            array( 'value' => 'bimonthly', 'label' => __( 'Every 2 months', 'quill-accessible-forms' ) ),
            array( 'value' => 'quarterly', 'label' => __( 'Quarterly', 'quill-accessible-forms' ) ),
            array( 'value' => 'semiannually', 'label' => __( 'Every 6 months', 'quill-accessible-forms' ) ),
            array( 'value' => 'annually', 'label' => __( 'Annually', 'quill-accessible-forms' ) ),
            array( 'value' => 'one_time', 'label' => __( 'One-time (no subscription)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get property types
     *
     * @return array Property types.
     */
    public static function get_property_types() {
        return array(
            array( 'value' => 'house', 'label' => __( 'House', 'quill-accessible-forms' ) ),
            array( 'value' => 'apartment', 'label' => __( 'Apartment', 'quill-accessible-forms' ) ),
            array( 'value' => 'condo', 'label' => __( 'Condo/Condominium', 'quill-accessible-forms' ) ),
            array( 'value' => 'townhouse', 'label' => __( 'Townhouse', 'quill-accessible-forms' ) ),
            array( 'value' => 'duplex', 'label' => __( 'Duplex', 'quill-accessible-forms' ) ),
            array( 'value' => 'multi_family', 'label' => __( 'Multi-family', 'quill-accessible-forms' ) ),
            array( 'value' => 'mobile_home', 'label' => __( 'Mobile/Manufactured home', 'quill-accessible-forms' ) ),
            array( 'value' => 'land', 'label' => __( 'Land/Lot', 'quill-accessible-forms' ) ),
            array( 'value' => 'commercial', 'label' => __( 'Commercial', 'quill-accessible-forms' ) ),
            array( 'value' => 'industrial', 'label' => __( 'Industrial', 'quill-accessible-forms' ) ),
            array( 'value' => 'office', 'label' => __( 'Office', 'quill-accessible-forms' ) ),
            array( 'value' => 'retail', 'label' => __( 'Retail', 'quill-accessible-forms' ) ),
            array( 'value' => 'farm', 'label' => __( 'Farm/Ranch', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get number of bedrooms
     *
     * @return array Number of bedrooms.
     */
    public static function get_bedrooms() {
        return array(
            array( 'value' => 'studio', 'label' => __( 'Studio', 'quill-accessible-forms' ) ),
            array( 'value' => '1', 'label' => __( '1 bedroom', 'quill-accessible-forms' ) ),
            array( 'value' => '2', 'label' => __( '2 bedrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '3', 'label' => __( '3 bedrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '4', 'label' => __( '4 bedrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '5', 'label' => __( '5 bedrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '6_plus', 'label' => __( '6+ bedrooms', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get number of bathrooms
     *
     * @return array Number of bathrooms.
     */
    public static function get_bathrooms() {
        return array(
            array( 'value' => '1', 'label' => __( '1 bathroom', 'quill-accessible-forms' ) ),
            array( 'value' => '1.5', 'label' => __( '1.5 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '2', 'label' => __( '2 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '2.5', 'label' => __( '2.5 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '3', 'label' => __( '3 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '3.5', 'label' => __( '3.5 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '4', 'label' => __( '4 bathrooms', 'quill-accessible-forms' ) ),
            array( 'value' => '4_plus', 'label' => __( '4+ bathrooms', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get vehicle types
     *
     * @return array Vehicle types.
     */
    public static function get_vehicle_types() {
        return array(
            array( 'value' => 'sedan', 'label' => __( 'Sedan', 'quill-accessible-forms' ) ),
            array( 'value' => 'suv', 'label' => __( 'SUV', 'quill-accessible-forms' ) ),
            array( 'value' => 'crossover', 'label' => __( 'Crossover', 'quill-accessible-forms' ) ),
            array( 'value' => 'truck', 'label' => __( 'Truck', 'quill-accessible-forms' ) ),
            array( 'value' => 'van', 'label' => __( 'Van/Minivan', 'quill-accessible-forms' ) ),
            array( 'value' => 'coupe', 'label' => __( 'Coupe', 'quill-accessible-forms' ) ),
            array( 'value' => 'convertible', 'label' => __( 'Convertible', 'quill-accessible-forms' ) ),
            array( 'value' => 'hatchback', 'label' => __( 'Hatchback', 'quill-accessible-forms' ) ),
            array( 'value' => 'wagon', 'label' => __( 'Station wagon', 'quill-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports car', 'quill-accessible-forms' ) ),
            array( 'value' => 'luxury', 'label' => __( 'Luxury', 'quill-accessible-forms' ) ),
            array( 'value' => 'electric', 'label' => __( 'Electric', 'quill-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'quill-accessible-forms' ) ),
            array( 'value' => 'motorcycle', 'label' => __( 'Motorcycle', 'quill-accessible-forms' ) ),
            array( 'value' => 'rv', 'label' => __( 'RV/Camper', 'quill-accessible-forms' ) ),
            array( 'value' => 'boat', 'label' => __( 'Boat', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get fuel types
     *
     * @return array Fuel types.
     */
    public static function get_fuel_types() {
        return array(
            array( 'value' => 'gasoline', 'label' => __( 'Gasoline', 'quill-accessible-forms' ) ),
            array( 'value' => 'diesel', 'label' => __( 'Diesel', 'quill-accessible-forms' ) ),
            array( 'value' => 'electric', 'label' => __( 'Electric', 'quill-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'quill-accessible-forms' ) ),
            array( 'value' => 'plug_in_hybrid', 'label' => __( 'Plug-in hybrid', 'quill-accessible-forms' ) ),
            array( 'value' => 'flex_fuel', 'label' => __( 'Flex fuel', 'quill-accessible-forms' ) ),
            array( 'value' => 'hydrogen', 'label' => __( 'Hydrogen', 'quill-accessible-forms' ) ),
            array( 'value' => 'natural_gas', 'label' => __( 'Natural gas', 'quill-accessible-forms' ) ),
            array( 'value' => 'propane', 'label' => __( 'Propane', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get software platforms
     *
     * @return array Software platforms.
     */
    public static function get_platforms() {
        return array(
            array( 'value' => 'windows', 'label' => __( 'Windows', 'quill-accessible-forms' ) ),
            array( 'value' => 'macos', 'label' => __( 'macOS', 'quill-accessible-forms' ) ),
            array( 'value' => 'linux', 'label' => __( 'Linux', 'quill-accessible-forms' ) ),
            array( 'value' => 'ios', 'label' => __( 'iOS', 'quill-accessible-forms' ) ),
            array( 'value' => 'android', 'label' => __( 'Android', 'quill-accessible-forms' ) ),
            array( 'value' => 'web', 'label' => __( 'Web/Browser', 'quill-accessible-forms' ) ),
            array( 'value' => 'chromeos', 'label' => __( 'ChromeOS', 'quill-accessible-forms' ) ),
            array( 'value' => 'cross_platform', 'label' => __( 'Cross-platform', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get device types
     *
     * @return array Device types.
     */
    public static function get_device_types() {
        return array(
            array( 'value' => 'desktop', 'label' => __( 'Desktop computer', 'quill-accessible-forms' ) ),
            array( 'value' => 'laptop', 'label' => __( 'Laptop', 'quill-accessible-forms' ) ),
            array( 'value' => 'tablet', 'label' => __( 'Tablet', 'quill-accessible-forms' ) ),
            array( 'value' => 'smartphone', 'label' => __( 'Smartphone', 'quill-accessible-forms' ) ),
            array( 'value' => 'smartwatch', 'label' => __( 'Smartwatch', 'quill-accessible-forms' ) ),
            array( 'value' => 'smart_tv', 'label' => __( 'Smart TV', 'quill-accessible-forms' ) ),
            array( 'value' => 'gaming_console', 'label' => __( 'Gaming console', 'quill-accessible-forms' ) ),
            array( 'value' => 'smart_speaker', 'label' => __( 'Smart speaker', 'quill-accessible-forms' ) ),
            array( 'value' => 'ereader', 'label' => __( 'E-reader', 'quill-accessible-forms' ) ),
            array( 'value' => 'vr_headset', 'label' => __( 'VR headset', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get browser types
     *
     * @return array Browser types.
     */
    public static function get_browsers() {
        return array(
            array( 'value' => 'chrome', 'label' => __( 'Google Chrome', 'quill-accessible-forms' ) ),
            array( 'value' => 'firefox', 'label' => __( 'Mozilla Firefox', 'quill-accessible-forms' ) ),
            array( 'value' => 'safari', 'label' => __( 'Safari', 'quill-accessible-forms' ) ),
            array( 'value' => 'edge', 'label' => __( 'Microsoft Edge', 'quill-accessible-forms' ) ),
            array( 'value' => 'opera', 'label' => __( 'Opera', 'quill-accessible-forms' ) ),
            array( 'value' => 'brave', 'label' => __( 'Brave', 'quill-accessible-forms' ) ),
            array( 'value' => 'vivaldi', 'label' => __( 'Vivaldi', 'quill-accessible-forms' ) ),
            array( 'value' => 'samsung', 'label' => __( 'Samsung Internet', 'quill-accessible-forms' ) ),
            array( 'value' => 'ie', 'label' => __( 'Internet Explorer', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get skill levels
     *
     * @return array Skill levels.
     */
    public static function get_skill_levels() {
        return array(
            array( 'value' => 'beginner', 'label' => __( 'Beginner', 'quill-accessible-forms' ) ),
            array( 'value' => 'elementary', 'label' => __( 'Elementary', 'quill-accessible-forms' ) ),
            array( 'value' => 'intermediate', 'label' => __( 'Intermediate', 'quill-accessible-forms' ) ),
            array( 'value' => 'upper_intermediate', 'label' => __( 'Upper Intermediate', 'quill-accessible-forms' ) ),
            array( 'value' => 'advanced', 'label' => __( 'Advanced', 'quill-accessible-forms' ) ),
            array( 'value' => 'expert', 'label' => __( 'Expert', 'quill-accessible-forms' ) ),
            array( 'value' => 'native', 'label' => __( 'Native/Fluent', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get experience levels
     *
     * @return array Experience levels.
     */
    public static function get_experience_levels() {
        return array(
            array( 'value' => 'entry', 'label' => __( 'Entry level', 'quill-accessible-forms' ) ),
            array( 'value' => 'junior', 'label' => __( 'Junior', 'quill-accessible-forms' ) ),
            array( 'value' => 'mid', 'label' => __( 'Mid-level', 'quill-accessible-forms' ) ),
            array( 'value' => 'senior', 'label' => __( 'Senior', 'quill-accessible-forms' ) ),
            array( 'value' => 'lead', 'label' => __( 'Lead', 'quill-accessible-forms' ) ),
            array( 'value' => 'manager', 'label' => __( 'Manager', 'quill-accessible-forms' ) ),
            array( 'value' => 'director', 'label' => __( 'Director', 'quill-accessible-forms' ) ),
            array( 'value' => 'vp', 'label' => __( 'VP', 'quill-accessible-forms' ) ),
            array( 'value' => 'c_level', 'label' => __( 'C-Level', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get years of experience
     *
     * @return array Years of experience.
     */
    public static function get_years_experience() {
        return array(
            array( 'value' => 'none', 'label' => __( 'No experience', 'quill-accessible-forms' ) ),
            array( 'value' => 'less_1', 'label' => __( 'Less than 1 year', 'quill-accessible-forms' ) ),
            array( 'value' => '1_2', 'label' => __( '1-2 years', 'quill-accessible-forms' ) ),
            array( 'value' => '3_5', 'label' => __( '3-5 years', 'quill-accessible-forms' ) ),
            array( 'value' => '6_10', 'label' => __( '6-10 years', 'quill-accessible-forms' ) ),
            array( 'value' => '11_15', 'label' => __( '11-15 years', 'quill-accessible-forms' ) ),
            array( 'value' => '16_20', 'label' => __( '16-20 years', 'quill-accessible-forms' ) ),
            array( 'value' => 'over_20', 'label' => __( 'More than 20 years', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get notification preferences
     *
     * @return array Notification preferences.
     */
    public static function get_notification_preferences() {
        return array(
            array( 'value' => 'all', 'label' => __( 'All notifications', 'quill-accessible-forms' ) ),
            array( 'value' => 'important', 'label' => __( 'Important only', 'quill-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing and promotions', 'quill-accessible-forms' ) ),
            array( 'value' => 'updates', 'label' => __( 'Product updates', 'quill-accessible-forms' ) ),
            array( 'value' => 'newsletter', 'label' => __( 'Newsletter', 'quill-accessible-forms' ) ),
            array( 'value' => 'account', 'label' => __( 'Account alerts only', 'quill-accessible-forms' ) ),
            array( 'value' => 'none', 'label' => __( 'No notifications', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (length)
     *
     * @return array Length units.
     */
    public static function get_units_length() {
        return array(
            array( 'value' => 'mm', 'label' => __( 'Millimeters (mm)', 'quill-accessible-forms' ) ),
            array( 'value' => 'cm', 'label' => __( 'Centimeters (cm)', 'quill-accessible-forms' ) ),
            array( 'value' => 'm', 'label' => __( 'Meters (m)', 'quill-accessible-forms' ) ),
            array( 'value' => 'km', 'label' => __( 'Kilometers (km)', 'quill-accessible-forms' ) ),
            array( 'value' => 'in', 'label' => __( 'Inches (in)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ft', 'label' => __( 'Feet (ft)', 'quill-accessible-forms' ) ),
            array( 'value' => 'yd', 'label' => __( 'Yards (yd)', 'quill-accessible-forms' ) ),
            array( 'value' => 'mi', 'label' => __( 'Miles (mi)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (weight)
     *
     * @return array Weight units.
     */
    public static function get_units_weight() {
        return array(
            array( 'value' => 'mg', 'label' => __( 'Milligrams (mg)', 'quill-accessible-forms' ) ),
            array( 'value' => 'g', 'label' => __( 'Grams (g)', 'quill-accessible-forms' ) ),
            array( 'value' => 'kg', 'label' => __( 'Kilograms (kg)', 'quill-accessible-forms' ) ),
            array( 'value' => 'oz', 'label' => __( 'Ounces (oz)', 'quill-accessible-forms' ) ),
            array( 'value' => 'lb', 'label' => __( 'Pounds (lb)', 'quill-accessible-forms' ) ),
            array( 'value' => 'st', 'label' => __( 'Stone (st)', 'quill-accessible-forms' ) ),
            array( 'value' => 'ton', 'label' => __( 'Tons', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (volume)
     *
     * @return array Volume units.
     */
    public static function get_units_volume() {
        return array(
            array( 'value' => 'ml', 'label' => __( 'Milliliters (ml)', 'quill-accessible-forms' ) ),
            array( 'value' => 'l', 'label' => __( 'Liters (l)', 'quill-accessible-forms' ) ),
            array( 'value' => 'tsp', 'label' => __( 'Teaspoons (tsp)', 'quill-accessible-forms' ) ),
            array( 'value' => 'tbsp', 'label' => __( 'Tablespoons (tbsp)', 'quill-accessible-forms' ) ),
            array( 'value' => 'fl_oz', 'label' => __( 'Fluid ounces (fl oz)', 'quill-accessible-forms' ) ),
            array( 'value' => 'cup', 'label' => __( 'Cups', 'quill-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Pints (pt)', 'quill-accessible-forms' ) ),
            array( 'value' => 'qt', 'label' => __( 'Quarts (qt)', 'quill-accessible-forms' ) ),
            array( 'value' => 'gal', 'label' => __( 'Gallons (gal)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get temperature units
     *
     * @return array Temperature units.
     */
    public static function get_units_temperature() {
        return array(
            array( 'value' => 'celsius', 'label' => __( 'Celsius (°C)', 'quill-accessible-forms' ) ),
            array( 'value' => 'fahrenheit', 'label' => __( 'Fahrenheit (°F)', 'quill-accessible-forms' ) ),
            array( 'value' => 'kelvin', 'label' => __( 'Kelvin (K)', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get event types
     *
     * @return array Event types.
     */
    public static function get_event_types() {
        return array(
            array( 'value' => 'conference', 'label' => __( 'Conference', 'quill-accessible-forms' ) ),
            array( 'value' => 'seminar', 'label' => __( 'Seminar', 'quill-accessible-forms' ) ),
            array( 'value' => 'workshop', 'label' => __( 'Workshop', 'quill-accessible-forms' ) ),
            array( 'value' => 'webinar', 'label' => __( 'Webinar', 'quill-accessible-forms' ) ),
            array( 'value' => 'meeting', 'label' => __( 'Meeting', 'quill-accessible-forms' ) ),
            array( 'value' => 'networking', 'label' => __( 'Networking event', 'quill-accessible-forms' ) ),
            array( 'value' => 'training', 'label' => __( 'Training', 'quill-accessible-forms' ) ),
            array( 'value' => 'presentation', 'label' => __( 'Presentation', 'quill-accessible-forms' ) ),
            array( 'value' => 'product_launch', 'label' => __( 'Product launch', 'quill-accessible-forms' ) ),
            array( 'value' => 'trade_show', 'label' => __( 'Trade show', 'quill-accessible-forms' ) ),
            array( 'value' => 'exhibition', 'label' => __( 'Exhibition', 'quill-accessible-forms' ) ),
            array( 'value' => 'fundraiser', 'label' => __( 'Fundraiser', 'quill-accessible-forms' ) ),
            array( 'value' => 'gala', 'label' => __( 'Gala', 'quill-accessible-forms' ) ),
            array( 'value' => 'concert', 'label' => __( 'Concert', 'quill-accessible-forms' ) ),
            array( 'value' => 'festival', 'label' => __( 'Festival', 'quill-accessible-forms' ) ),
            array( 'value' => 'party', 'label' => __( 'Party/Celebration', 'quill-accessible-forms' ) ),
            array( 'value' => 'wedding', 'label' => __( 'Wedding', 'quill-accessible-forms' ) ),
            array( 'value' => 'corporate', 'label' => __( 'Corporate event', 'quill-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports event', 'quill-accessible-forms' ) ),
            array( 'value' => 'community', 'label' => __( 'Community event', 'quill-accessible-forms' ) ),
            array( 'value' => 'virtual', 'label' => __( 'Virtual event', 'quill-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid event', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get ticket types
     *
     * @return array Ticket types.
     */
    public static function get_ticket_types() {
        return array(
            array( 'value' => 'free', 'label' => __( 'Free', 'quill-accessible-forms' ) ),
            array( 'value' => 'general', 'label' => __( 'General admission', 'quill-accessible-forms' ) ),
            array( 'value' => 'vip', 'label' => __( 'VIP', 'quill-accessible-forms' ) ),
            array( 'value' => 'early_bird', 'label' => __( 'Early bird', 'quill-accessible-forms' ) ),
            array( 'value' => 'student', 'label' => __( 'Student', 'quill-accessible-forms' ) ),
            array( 'value' => 'senior', 'label' => __( 'Senior', 'quill-accessible-forms' ) ),
            array( 'value' => 'military', 'label' => __( 'Military', 'quill-accessible-forms' ) ),
            array( 'value' => 'group', 'label' => __( 'Group', 'quill-accessible-forms' ) ),
            array( 'value' => 'member', 'label' => __( 'Member', 'quill-accessible-forms' ) ),
            array( 'value' => 'press', 'label' => __( 'Press/Media', 'quill-accessible-forms' ) ),
            array( 'value' => 'sponsor', 'label' => __( 'Sponsor', 'quill-accessible-forms' ) ),
            array( 'value' => 'exhibitor', 'label' => __( 'Exhibitor', 'quill-accessible-forms' ) ),
            array( 'value' => 'speaker', 'label' => __( 'Speaker', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get support issue types
     *
     * @return array Support issue types.
     */
    public static function get_support_issue_types() {
        return array(
            array( 'value' => 'bug', 'label' => __( 'Bug/Error', 'quill-accessible-forms' ) ),
            array( 'value' => 'feature', 'label' => __( 'Feature request', 'quill-accessible-forms' ) ),
            array( 'value' => 'question', 'label' => __( 'Question', 'quill-accessible-forms' ) ),
            array( 'value' => 'account', 'label' => __( 'Account issue', 'quill-accessible-forms' ) ),
            array( 'value' => 'billing', 'label' => __( 'Billing/Payment', 'quill-accessible-forms' ) ),
            array( 'value' => 'order', 'label' => __( 'Order issue', 'quill-accessible-forms' ) ),
            array( 'value' => 'shipping', 'label' => __( 'Shipping/Delivery', 'quill-accessible-forms' ) ),
            array( 'value' => 'return', 'label' => __( 'Return/Refund', 'quill-accessible-forms' ) ),
            array( 'value' => 'technical', 'label' => __( 'Technical support', 'quill-accessible-forms' ) ),
            array( 'value' => 'feedback', 'label' => __( 'Feedback', 'quill-accessible-forms' ) ),
            array( 'value' => 'complaint', 'label' => __( 'Complaint', 'quill-accessible-forms' ) ),
            array( 'value' => 'partnership', 'label' => __( 'Partnership inquiry', 'quill-accessible-forms' ) ),
            array( 'value' => 'press', 'label' => __( 'Press/Media inquiry', 'quill-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get priority levels
     *
     * @return array Priority levels.
     */
    public static function get_priority_levels() {
        return array(
            array( 'value' => 'low', 'label' => __( 'Low', 'quill-accessible-forms' ) ),
            array( 'value' => 'medium', 'label' => __( 'Medium', 'quill-accessible-forms' ) ),
            array( 'value' => 'high', 'label' => __( 'High', 'quill-accessible-forms' ) ),
            array( 'value' => 'urgent', 'label' => __( 'Urgent', 'quill-accessible-forms' ) ),
            array( 'value' => 'critical', 'label' => __( 'Critical', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get status options
     *
     * @return array Status options.
     */
    public static function get_statuses() {
        return array(
            array( 'value' => 'pending', 'label' => __( 'Pending', 'quill-accessible-forms' ) ),
            array( 'value' => 'open', 'label' => __( 'Open', 'quill-accessible-forms' ) ),
            array( 'value' => 'in_progress', 'label' => __( 'In Progress', 'quill-accessible-forms' ) ),
            array( 'value' => 'on_hold', 'label' => __( 'On Hold', 'quill-accessible-forms' ) ),
            array( 'value' => 'resolved', 'label' => __( 'Resolved', 'quill-accessible-forms' ) ),
            array( 'value' => 'closed', 'label' => __( 'Closed', 'quill-accessible-forms' ) ),
            array( 'value' => 'cancelled', 'label' => __( 'Cancelled', 'quill-accessible-forms' ) ),
        );
    }
}