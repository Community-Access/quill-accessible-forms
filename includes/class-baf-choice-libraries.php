<?php
/**
 * Choice Libraries
 *
 * Provides predefined choice sets for select, radio, and checkbox fields.
 * Supports cascading relationships between libraries (e.g., Continent → Country → State → City).
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Choice_Libraries class
 */
class BAF_Choice_Libraries {

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
        do_action( 'baf_register_choice_libraries' );
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
            $cache_key = 'baf_library_' . $library_id;
            if ( $parent_value !== null ) {
                $cache_key .= '_' . md5( $parent_value );
            }
            
            $cached = wp_cache_get( $cache_key, 'baf_choices' );
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
            wp_cache_set( $cache_key, $choices, 'baf_choices', $library['cache_ttl'] );
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
            'label'    => __( 'Continents', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_continents' ),
        ) );

        // World Regions
        self::register( 'world_regions', array(
            'label'    => __( 'World Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_world_regions' ),
        ) );

        // Countries (Complete list)
        self::register( 'countries', array(
            'label'    => __( 'Countries', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_countries' ),
        ) );

        // Countries by Continent (cascading)
        self::register( 'countries_by_continent', array(
            'label'    => __( 'Countries by Continent (cascading)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_countries_by_continent' ),
        ) );

        // US States
        self::register( 'us_states', array(
            'label'    => __( 'US States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_us_states' ),
        ) );

        // US States with Territories
        self::register( 'us_states_territories', array(
            'label'    => __( 'US States & Territories', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_us_states_territories' ),
        ) );

        // Canadian Provinces
        self::register( 'ca_provinces', array(
            'label'    => __( 'Canadian Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ca_provinces' ),
        ) );

        // UK Counties/Regions
        self::register( 'uk_counties', array(
            'label'    => __( 'UK Counties/Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_uk_counties' ),
        ) );

        // Australian States
        self::register( 'au_states', array(
            'label'    => __( 'Australian States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_au_states' ),
        ) );

        // German States (Bundesländer)
        self::register( 'de_states', array(
            'label'    => __( 'German States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_de_states' ),
        ) );

        // French Regions
        self::register( 'fr_regions', array(
            'label'    => __( 'French Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_fr_regions' ),
        ) );

        // Italian Regions
        self::register( 'it_regions', array(
            'label'    => __( 'Italian Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_it_regions' ),
        ) );

        // Spanish Autonomous Communities
        self::register( 'es_regions', array(
            'label'    => __( 'Spanish Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_es_regions' ),
        ) );

        // Dutch Provinces
        self::register( 'nl_provinces', array(
            'label'    => __( 'Dutch Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_nl_provinces' ),
        ) );

        // Belgian Provinces
        self::register( 'be_provinces', array(
            'label'    => __( 'Belgian Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_be_provinces' ),
        ) );

        // Swiss Cantons
        self::register( 'ch_cantons', array(
            'label'    => __( 'Swiss Cantons', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ch_cantons' ),
        ) );

        // Austrian States
        self::register( 'at_states', array(
            'label'    => __( 'Austrian States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_at_states' ),
        ) );

        // Mexican States
        self::register( 'mx_states', array(
            'label'    => __( 'Mexican States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_mx_states' ),
        ) );

        // Brazilian States
        self::register( 'br_states', array(
            'label'    => __( 'Brazilian States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_br_states' ),
        ) );

        // Argentine Provinces
        self::register( 'ar_provinces', array(
            'label'    => __( 'Argentine Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ar_provinces' ),
        ) );

        // Indian States
        self::register( 'in_states', array(
            'label'    => __( 'Indian States', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_in_states' ),
        ) );

        // Chinese Provinces
        self::register( 'cn_provinces', array(
            'label'    => __( 'Chinese Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_cn_provinces' ),
        ) );

        // Japanese Prefectures
        self::register( 'jp_prefectures', array(
            'label'    => __( 'Japanese Prefectures', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_jp_prefectures' ),
        ) );

        // South Korean Provinces
        self::register( 'kr_provinces', array(
            'label'    => __( 'South Korean Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_kr_provinces' ),
        ) );

        // New Zealand Regions
        self::register( 'nz_regions', array(
            'label'    => __( 'New Zealand Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_nz_regions' ),
        ) );

        // South African Provinces
        self::register( 'za_provinces', array(
            'label'    => __( 'South African Provinces', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_za_provinces' ),
        ) );

        // Irish Counties
        self::register( 'ie_counties', array(
            'label'    => __( 'Irish Counties', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ie_counties' ),
        ) );

        // Polish Voivodeships
        self::register( 'pl_voivodeships', array(
            'label'    => __( 'Polish Voivodeships', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_pl_voivodeships' ),
        ) );

        // Russian Federal Subjects
        self::register( 'ru_regions', array(
            'label'    => __( 'Russian Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_ru_regions' ),
        ) );

        // States/Provinces (auto-cascade from country)
        self::register( 'states', array(
            'label'    => __( 'States/Provinces (cascading)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_states_for_country' ),
        ) );

        // =====================================================================
        // TIME & DATE LIBRARIES
        // =====================================================================

        // Timezones (grouped by region)
        self::register( 'timezones', array(
            'label'    => __( 'Timezones', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezones' ),
        ) );

        // Timezones by Region (cascading)
        self::register( 'timezones_by_region', array(
            'label'    => __( 'Timezones by Region (cascading)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezones_by_region' ),
        ) );

        // Timezone Regions
        self::register( 'timezone_regions', array(
            'label'    => __( 'Timezone Regions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_timezone_regions' ),
        ) );

        // UTC Offsets
        self::register( 'utc_offsets', array(
            'label'    => __( 'UTC Offsets', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_utc_offsets' ),
        ) );

        // Days of Week
        self::register( 'days', array(
            'label'   => __( 'Days of Week', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'monday', 'label' => __( 'Monday', 'bits-accessible-forms' ) ),
                array( 'value' => 'tuesday', 'label' => __( 'Tuesday', 'bits-accessible-forms' ) ),
                array( 'value' => 'wednesday', 'label' => __( 'Wednesday', 'bits-accessible-forms' ) ),
                array( 'value' => 'thursday', 'label' => __( 'Thursday', 'bits-accessible-forms' ) ),
                array( 'value' => 'friday', 'label' => __( 'Friday', 'bits-accessible-forms' ) ),
                array( 'value' => 'saturday', 'label' => __( 'Saturday', 'bits-accessible-forms' ) ),
                array( 'value' => 'sunday', 'label' => __( 'Sunday', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Days of Week (Short)
        self::register( 'days_short', array(
            'label'   => __( 'Days of Week (Short)', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mon', 'label' => __( 'Mon', 'bits-accessible-forms' ) ),
                array( 'value' => 'tue', 'label' => __( 'Tue', 'bits-accessible-forms' ) ),
                array( 'value' => 'wed', 'label' => __( 'Wed', 'bits-accessible-forms' ) ),
                array( 'value' => 'thu', 'label' => __( 'Thu', 'bits-accessible-forms' ) ),
                array( 'value' => 'fri', 'label' => __( 'Fri', 'bits-accessible-forms' ) ),
                array( 'value' => 'sat', 'label' => __( 'Sat', 'bits-accessible-forms' ) ),
                array( 'value' => 'sun', 'label' => __( 'Sun', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Weekdays Only
        self::register( 'weekdays', array(
            'label'   => __( 'Weekdays', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'monday', 'label' => __( 'Monday', 'bits-accessible-forms' ) ),
                array( 'value' => 'tuesday', 'label' => __( 'Tuesday', 'bits-accessible-forms' ) ),
                array( 'value' => 'wednesday', 'label' => __( 'Wednesday', 'bits-accessible-forms' ) ),
                array( 'value' => 'thursday', 'label' => __( 'Thursday', 'bits-accessible-forms' ) ),
                array( 'value' => 'friday', 'label' => __( 'Friday', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Months
        self::register( 'months', array(
            'label'   => __( 'Months', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => '01', 'label' => __( 'January', 'bits-accessible-forms' ) ),
                array( 'value' => '02', 'label' => __( 'February', 'bits-accessible-forms' ) ),
                array( 'value' => '03', 'label' => __( 'March', 'bits-accessible-forms' ) ),
                array( 'value' => '04', 'label' => __( 'April', 'bits-accessible-forms' ) ),
                array( 'value' => '05', 'label' => __( 'May', 'bits-accessible-forms' ) ),
                array( 'value' => '06', 'label' => __( 'June', 'bits-accessible-forms' ) ),
                array( 'value' => '07', 'label' => __( 'July', 'bits-accessible-forms' ) ),
                array( 'value' => '08', 'label' => __( 'August', 'bits-accessible-forms' ) ),
                array( 'value' => '09', 'label' => __( 'September', 'bits-accessible-forms' ) ),
                array( 'value' => '10', 'label' => __( 'October', 'bits-accessible-forms' ) ),
                array( 'value' => '11', 'label' => __( 'November', 'bits-accessible-forms' ) ),
                array( 'value' => '12', 'label' => __( 'December', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Months (Short)
        self::register( 'months_short', array(
            'label'   => __( 'Months (Short)', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => '01', 'label' => __( 'Jan', 'bits-accessible-forms' ) ),
                array( 'value' => '02', 'label' => __( 'Feb', 'bits-accessible-forms' ) ),
                array( 'value' => '03', 'label' => __( 'Mar', 'bits-accessible-forms' ) ),
                array( 'value' => '04', 'label' => __( 'Apr', 'bits-accessible-forms' ) ),
                array( 'value' => '05', 'label' => __( 'May', 'bits-accessible-forms' ) ),
                array( 'value' => '06', 'label' => __( 'Jun', 'bits-accessible-forms' ) ),
                array( 'value' => '07', 'label' => __( 'Jul', 'bits-accessible-forms' ) ),
                array( 'value' => '08', 'label' => __( 'Aug', 'bits-accessible-forms' ) ),
                array( 'value' => '09', 'label' => __( 'Sep', 'bits-accessible-forms' ) ),
                array( 'value' => '10', 'label' => __( 'Oct', 'bits-accessible-forms' ) ),
                array( 'value' => '11', 'label' => __( 'Nov', 'bits-accessible-forms' ) ),
                array( 'value' => '12', 'label' => __( 'Dec', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Quarters
        self::register( 'quarters', array(
            'label'   => __( 'Quarters', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'Q1', 'label' => __( 'Q1 (Jan-Mar)', 'bits-accessible-forms' ) ),
                array( 'value' => 'Q2', 'label' => __( 'Q2 (Apr-Jun)', 'bits-accessible-forms' ) ),
                array( 'value' => 'Q3', 'label' => __( 'Q3 (Jul-Sep)', 'bits-accessible-forms' ) ),
                array( 'value' => 'Q4', 'label' => __( 'Q4 (Oct-Dec)', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Years (dynamic)
        self::register( 'years', array(
            'label'     => __( 'Years', 'bits-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_years' ),
            'cacheable' => false,
        ) );

        // Years (future)
        self::register( 'years_future', array(
            'label'     => __( 'Years (Future)', 'bits-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_years_future' ),
            'cacheable' => false,
        ) );

        // Birth Years
        self::register( 'birth_years', array(
            'label'     => __( 'Birth Years', 'bits-accessible-forms' ),
            'callback'  => array( __CLASS__, 'get_birth_years' ),
            'cacheable' => false,
        ) );

        // Time Slots (Hourly)
        self::register( 'time_slots_hourly', array(
            'label'    => __( 'Time Slots (Hourly)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_hourly' ),
        ) );

        // Time Slots (30 min)
        self::register( 'time_slots_30min', array(
            'label'    => __( 'Time Slots (30 min)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_30min' ),
        ) );

        // Time Slots (15 min)
        self::register( 'time_slots_15min', array(
            'label'    => __( 'Time Slots (15 min)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_time_slots_15min' ),
        ) );

        // =====================================================================
        // CURRENCY & FINANCE LIBRARIES
        // =====================================================================

        // Currencies (Complete ISO 4217)
        self::register( 'currencies', array(
            'label'    => __( 'Currencies', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_currencies' ),
        ) );

        // Major Currencies
        self::register( 'currencies_major', array(
            'label'    => __( 'Major Currencies', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_currencies_major' ),
        ) );

        // Cryptocurrencies
        self::register( 'cryptocurrencies', array(
            'label'    => __( 'Cryptocurrencies', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_cryptocurrencies' ),
        ) );

        // Income Ranges
        self::register( 'income_ranges', array(
            'label'   => __( 'Income Ranges', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_25k', 'label' => __( 'Under $25,000', 'bits-accessible-forms' ) ),
                array( 'value' => '25k_50k', 'label' => __( '$25,000 - $49,999', 'bits-accessible-forms' ) ),
                array( 'value' => '50k_75k', 'label' => __( '$50,000 - $74,999', 'bits-accessible-forms' ) ),
                array( 'value' => '75k_100k', 'label' => __( '$75,000 - $99,999', 'bits-accessible-forms' ) ),
                array( 'value' => '100k_150k', 'label' => __( '$100,000 - $149,999', 'bits-accessible-forms' ) ),
                array( 'value' => '150k_200k', 'label' => __( '$150,000 - $199,999', 'bits-accessible-forms' ) ),
                array( 'value' => '200k_plus', 'label' => __( '$200,000+', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Budget Ranges
        self::register( 'budget_ranges', array(
            'label'   => __( 'Budget Ranges', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_1k', 'label' => __( 'Under $1,000', 'bits-accessible-forms' ) ),
                array( 'value' => '1k_5k', 'label' => __( '$1,000 - $5,000', 'bits-accessible-forms' ) ),
                array( 'value' => '5k_10k', 'label' => __( '$5,000 - $10,000', 'bits-accessible-forms' ) ),
                array( 'value' => '10k_25k', 'label' => __( '$10,000 - $25,000', 'bits-accessible-forms' ) ),
                array( 'value' => '25k_50k', 'label' => __( '$25,000 - $50,000', 'bits-accessible-forms' ) ),
                array( 'value' => '50k_100k', 'label' => __( '$50,000 - $100,000', 'bits-accessible-forms' ) ),
                array( 'value' => '100k_plus', 'label' => __( '$100,000+', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Payment Methods
        self::register( 'payment_methods', array(
            'label'   => __( 'Payment Methods', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'credit_card', 'label' => __( 'Credit Card', 'bits-accessible-forms' ) ),
                array( 'value' => 'debit_card', 'label' => __( 'Debit Card', 'bits-accessible-forms' ) ),
                array( 'value' => 'paypal', 'label' => __( 'PayPal', 'bits-accessible-forms' ) ),
                array( 'value' => 'bank_transfer', 'label' => __( 'Bank Transfer', 'bits-accessible-forms' ) ),
                array( 'value' => 'apple_pay', 'label' => __( 'Apple Pay', 'bits-accessible-forms' ) ),
                array( 'value' => 'google_pay', 'label' => __( 'Google Pay', 'bits-accessible-forms' ) ),
                array( 'value' => 'crypto', 'label' => __( 'Cryptocurrency', 'bits-accessible-forms' ) ),
                array( 'value' => 'cash', 'label' => __( 'Cash', 'bits-accessible-forms' ) ),
                array( 'value' => 'check', 'label' => __( 'Check', 'bits-accessible-forms' ) ),
                array( 'value' => 'invoice', 'label' => __( 'Invoice', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Credit Card Types
        self::register( 'credit_card_types', array(
            'label'   => __( 'Credit Card Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'visa', 'label' => __( 'Visa', 'bits-accessible-forms' ) ),
                array( 'value' => 'mastercard', 'label' => __( 'Mastercard', 'bits-accessible-forms' ) ),
                array( 'value' => 'amex', 'label' => __( 'American Express', 'bits-accessible-forms' ) ),
                array( 'value' => 'discover', 'label' => __( 'Discover', 'bits-accessible-forms' ) ),
                array( 'value' => 'diners', 'label' => __( 'Diners Club', 'bits-accessible-forms' ) ),
                array( 'value' => 'jcb', 'label' => __( 'JCB', 'bits-accessible-forms' ) ),
                array( 'value' => 'unionpay', 'label' => __( 'UnionPay', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // LANGUAGE LIBRARIES
        // =====================================================================

        // Languages (Complete)
        self::register( 'languages', array(
            'label'    => __( 'Languages', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_languages' ),
        ) );

        // Languages (Major)
        self::register( 'languages_major', array(
            'label'    => __( 'Major Languages', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_languages_major' ),
        ) );

        // Language Proficiency
        self::register( 'language_proficiency', array(
            'label'   => __( 'Language Proficiency', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'native', 'label' => __( 'Native', 'bits-accessible-forms' ) ),
                array( 'value' => 'fluent', 'label' => __( 'Fluent', 'bits-accessible-forms' ) ),
                array( 'value' => 'advanced', 'label' => __( 'Advanced', 'bits-accessible-forms' ) ),
                array( 'value' => 'intermediate', 'label' => __( 'Intermediate', 'bits-accessible-forms' ) ),
                array( 'value' => 'basic', 'label' => __( 'Basic', 'bits-accessible-forms' ) ),
                array( 'value' => 'beginner', 'label' => __( 'Beginner', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // CONTACT & COMMUNICATION LIBRARIES
        // =====================================================================

        // Phone Country Codes
        self::register( 'phone_country_codes', array(
            'label'    => __( 'Phone Country Codes', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_phone_country_codes' ),
        ) );

        // Phone Types
        self::register( 'phone_types', array(
            'label'   => __( 'Phone Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mobile', 'label' => __( 'Mobile', 'bits-accessible-forms' ) ),
                array( 'value' => 'home', 'label' => __( 'Home', 'bits-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'fax', 'label' => __( 'Fax', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Email Types
        self::register( 'email_types', array(
            'label'   => __( 'Email Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'personal', 'label' => __( 'Personal', 'bits-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'school', 'label' => __( 'School', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Address Types
        self::register( 'address_types', array(
            'label'   => __( 'Address Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'home', 'label' => __( 'Home', 'bits-accessible-forms' ) ),
                array( 'value' => 'work', 'label' => __( 'Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'billing', 'label' => __( 'Billing', 'bits-accessible-forms' ) ),
                array( 'value' => 'shipping', 'label' => __( 'Shipping', 'bits-accessible-forms' ) ),
                array( 'value' => 'mailing', 'label' => __( 'Mailing', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Contact Preferences
        self::register( 'contact_preferences', array(
            'label'   => __( 'Contact Preferences', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'email', 'label' => __( 'Email', 'bits-accessible-forms' ) ),
                array( 'value' => 'phone', 'label' => __( 'Phone', 'bits-accessible-forms' ) ),
                array( 'value' => 'sms', 'label' => __( 'SMS/Text', 'bits-accessible-forms' ) ),
                array( 'value' => 'mail', 'label' => __( 'Mail', 'bits-accessible-forms' ) ),
                array( 'value' => 'no_contact', 'label' => __( 'Do not contact', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Social Media Platforms
        self::register( 'social_platforms', array(
            'label'   => __( 'Social Media Platforms', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'facebook', 'label' => __( 'Facebook', 'bits-accessible-forms' ) ),
                array( 'value' => 'twitter', 'label' => __( 'Twitter/X', 'bits-accessible-forms' ) ),
                array( 'value' => 'instagram', 'label' => __( 'Instagram', 'bits-accessible-forms' ) ),
                array( 'value' => 'linkedin', 'label' => __( 'LinkedIn', 'bits-accessible-forms' ) ),
                array( 'value' => 'youtube', 'label' => __( 'YouTube', 'bits-accessible-forms' ) ),
                array( 'value' => 'tiktok', 'label' => __( 'TikTok', 'bits-accessible-forms' ) ),
                array( 'value' => 'pinterest', 'label' => __( 'Pinterest', 'bits-accessible-forms' ) ),
                array( 'value' => 'snapchat', 'label' => __( 'Snapchat', 'bits-accessible-forms' ) ),
                array( 'value' => 'reddit', 'label' => __( 'Reddit', 'bits-accessible-forms' ) ),
                array( 'value' => 'discord', 'label' => __( 'Discord', 'bits-accessible-forms' ) ),
                array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'bits-accessible-forms' ) ),
                array( 'value' => 'telegram', 'label' => __( 'Telegram', 'bits-accessible-forms' ) ),
                array( 'value' => 'wechat', 'label' => __( 'WeChat', 'bits-accessible-forms' ) ),
                array( 'value' => 'github', 'label' => __( 'GitHub', 'bits-accessible-forms' ) ),
                array( 'value' => 'twitch', 'label' => __( 'Twitch', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // DEMOGRAPHIC LIBRARIES
        // =====================================================================

        // Age Ranges
        self::register( 'age_ranges', array(
            'label'   => __( 'Age Ranges', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'under_13', 'label' => __( 'Under 13', 'bits-accessible-forms' ) ),
                array( 'value' => '13_17', 'label' => __( '13-17', 'bits-accessible-forms' ) ),
                array( 'value' => '18_24', 'label' => __( '18-24', 'bits-accessible-forms' ) ),
                array( 'value' => '25_34', 'label' => __( '25-34', 'bits-accessible-forms' ) ),
                array( 'value' => '35_44', 'label' => __( '35-44', 'bits-accessible-forms' ) ),
                array( 'value' => '45_54', 'label' => __( '45-54', 'bits-accessible-forms' ) ),
                array( 'value' => '55_64', 'label' => __( '55-64', 'bits-accessible-forms' ) ),
                array( 'value' => '65_74', 'label' => __( '65-74', 'bits-accessible-forms' ) ),
                array( 'value' => '75_plus', 'label' => __( '75+', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Salutations/Titles
        self::register( 'salutations', array(
            'label'   => __( 'Salutations', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'mr', 'label' => __( 'Mr.', 'bits-accessible-forms' ) ),
                array( 'value' => 'mrs', 'label' => __( 'Mrs.', 'bits-accessible-forms' ) ),
                array( 'value' => 'ms', 'label' => __( 'Ms.', 'bits-accessible-forms' ) ),
                array( 'value' => 'miss', 'label' => __( 'Miss', 'bits-accessible-forms' ) ),
                array( 'value' => 'mx', 'label' => __( 'Mx.', 'bits-accessible-forms' ) ),
                array( 'value' => 'dr', 'label' => __( 'Dr.', 'bits-accessible-forms' ) ),
                array( 'value' => 'prof', 'label' => __( 'Prof.', 'bits-accessible-forms' ) ),
                array( 'value' => 'rev', 'label' => __( 'Rev.', 'bits-accessible-forms' ) ),
                array( 'value' => 'hon', 'label' => __( 'Hon.', 'bits-accessible-forms' ) ),
                array( 'value' => 'sir', 'label' => __( 'Sir', 'bits-accessible-forms' ) ),
                array( 'value' => 'dame', 'label' => __( 'Dame', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Name Suffixes
        self::register( 'name_suffixes', array(
            'label'   => __( 'Name Suffixes', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'jr', 'label' => __( 'Jr.', 'bits-accessible-forms' ) ),
                array( 'value' => 'sr', 'label' => __( 'Sr.', 'bits-accessible-forms' ) ),
                array( 'value' => 'ii', 'label' => __( 'II', 'bits-accessible-forms' ) ),
                array( 'value' => 'iii', 'label' => __( 'III', 'bits-accessible-forms' ) ),
                array( 'value' => 'iv', 'label' => __( 'IV', 'bits-accessible-forms' ) ),
                array( 'value' => 'v', 'label' => __( 'V', 'bits-accessible-forms' ) ),
                array( 'value' => 'phd', 'label' => __( 'Ph.D.', 'bits-accessible-forms' ) ),
                array( 'value' => 'md', 'label' => __( 'M.D.', 'bits-accessible-forms' ) ),
                array( 'value' => 'esq', 'label' => __( 'Esq.', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Gender
        self::register( 'gender', array(
            'label'   => __( 'Gender', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'male', 'label' => __( 'Male', 'bits-accessible-forms' ) ),
                array( 'value' => 'female', 'label' => __( 'Female', 'bits-accessible-forms' ) ),
                array( 'value' => 'non_binary', 'label' => __( 'Non-binary', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Pronouns
        self::register( 'pronouns', array(
            'label'   => __( 'Pronouns', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'he_him', 'label' => __( 'He/Him', 'bits-accessible-forms' ) ),
                array( 'value' => 'she_her', 'label' => __( 'She/Her', 'bits-accessible-forms' ) ),
                array( 'value' => 'they_them', 'label' => __( 'They/Them', 'bits-accessible-forms' ) ),
                array( 'value' => 'he_they', 'label' => __( 'He/They', 'bits-accessible-forms' ) ),
                array( 'value' => 'she_they', 'label' => __( 'She/They', 'bits-accessible-forms' ) ),
                array( 'value' => 'ze_zir', 'label' => __( 'Ze/Zir', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Marital Status
        self::register( 'marital_status', array(
            'label'   => __( 'Marital Status', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'single', 'label' => __( 'Single', 'bits-accessible-forms' ) ),
                array( 'value' => 'married', 'label' => __( 'Married', 'bits-accessible-forms' ) ),
                array( 'value' => 'domestic_partnership', 'label' => __( 'Domestic Partnership', 'bits-accessible-forms' ) ),
                array( 'value' => 'divorced', 'label' => __( 'Divorced', 'bits-accessible-forms' ) ),
                array( 'value' => 'separated', 'label' => __( 'Separated', 'bits-accessible-forms' ) ),
                array( 'value' => 'widowed', 'label' => __( 'Widowed', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Ethnicity
        self::register( 'ethnicity', array(
            'label'   => __( 'Ethnicity', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'american_indian', 'label' => __( 'American Indian or Alaska Native', 'bits-accessible-forms' ) ),
                array( 'value' => 'asian', 'label' => __( 'Asian', 'bits-accessible-forms' ) ),
                array( 'value' => 'black', 'label' => __( 'Black or African American', 'bits-accessible-forms' ) ),
                array( 'value' => 'hispanic', 'label' => __( 'Hispanic or Latino', 'bits-accessible-forms' ) ),
                array( 'value' => 'middle_eastern', 'label' => __( 'Middle Eastern or North African', 'bits-accessible-forms' ) ),
                array( 'value' => 'pacific_islander', 'label' => __( 'Native Hawaiian or Pacific Islander', 'bits-accessible-forms' ) ),
                array( 'value' => 'white', 'label' => __( 'White', 'bits-accessible-forms' ) ),
                array( 'value' => 'two_or_more', 'label' => __( 'Two or More Races', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
                array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Relationship Types
        self::register( 'relationship_types', array(
            'label'   => __( 'Relationship Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'self', 'label' => __( 'Self', 'bits-accessible-forms' ) ),
                array( 'value' => 'spouse', 'label' => __( 'Spouse', 'bits-accessible-forms' ) ),
                array( 'value' => 'partner', 'label' => __( 'Partner', 'bits-accessible-forms' ) ),
                array( 'value' => 'parent', 'label' => __( 'Parent', 'bits-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child', 'bits-accessible-forms' ) ),
                array( 'value' => 'sibling', 'label' => __( 'Sibling', 'bits-accessible-forms' ) ),
                array( 'value' => 'grandparent', 'label' => __( 'Grandparent', 'bits-accessible-forms' ) ),
                array( 'value' => 'grandchild', 'label' => __( 'Grandchild', 'bits-accessible-forms' ) ),
                array( 'value' => 'aunt_uncle', 'label' => __( 'Aunt/Uncle', 'bits-accessible-forms' ) ),
                array( 'value' => 'niece_nephew', 'label' => __( 'Niece/Nephew', 'bits-accessible-forms' ) ),
                array( 'value' => 'cousin', 'label' => __( 'Cousin', 'bits-accessible-forms' ) ),
                array( 'value' => 'friend', 'label' => __( 'Friend', 'bits-accessible-forms' ) ),
                array( 'value' => 'colleague', 'label' => __( 'Colleague', 'bits-accessible-forms' ) ),
                array( 'value' => 'guardian', 'label' => __( 'Guardian', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // EDUCATION LIBRARIES
        // =====================================================================

        // Education Levels
        self::register( 'education', array(
            'label'   => __( 'Education Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'no_formal', 'label' => __( 'No Formal Education', 'bits-accessible-forms' ) ),
                array( 'value' => 'elementary', 'label' => __( 'Elementary School', 'bits-accessible-forms' ) ),
                array( 'value' => 'middle_school', 'label' => __( 'Middle School', 'bits-accessible-forms' ) ),
                array( 'value' => 'some_high_school', 'label' => __( 'Some High School', 'bits-accessible-forms' ) ),
                array( 'value' => 'high_school', 'label' => __( 'High School Diploma/GED', 'bits-accessible-forms' ) ),
                array( 'value' => 'vocational', 'label' => __( 'Vocational Training', 'bits-accessible-forms' ) ),
                array( 'value' => 'some_college', 'label' => __( 'Some College', 'bits-accessible-forms' ) ),
                array( 'value' => 'associate', 'label' => __( 'Associate Degree', 'bits-accessible-forms' ) ),
                array( 'value' => 'bachelor', 'label' => __( 'Bachelor\'s Degree', 'bits-accessible-forms' ) ),
                array( 'value' => 'master', 'label' => __( 'Master\'s Degree', 'bits-accessible-forms' ) ),
                array( 'value' => 'professional', 'label' => __( 'Professional Degree (JD, MD, etc.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'doctorate', 'label' => __( 'Doctorate (Ph.D., Ed.D., etc.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Degree Types
        self::register( 'degree_types', array(
            'label'   => __( 'Degree Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'aa', 'label' => __( 'Associate of Arts (A.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'as', 'label' => __( 'Associate of Science (A.S.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'aas', 'label' => __( 'Associate of Applied Science (A.A.S.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'ba', 'label' => __( 'Bachelor of Arts (B.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'bs', 'label' => __( 'Bachelor of Science (B.S.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'bba', 'label' => __( 'Bachelor of Business Administration (B.B.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'bfa', 'label' => __( 'Bachelor of Fine Arts (B.F.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'ma', 'label' => __( 'Master of Arts (M.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'ms', 'label' => __( 'Master of Science (M.S.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'mba', 'label' => __( 'Master of Business Administration (M.B.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'mfa', 'label' => __( 'Master of Fine Arts (M.F.A.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'med', 'label' => __( 'Master of Education (M.Ed.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'jd', 'label' => __( 'Juris Doctor (J.D.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'md', 'label' => __( 'Doctor of Medicine (M.D.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'do', 'label' => __( 'Doctor of Osteopathic Medicine (D.O.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'phd', 'label' => __( 'Doctor of Philosophy (Ph.D.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'edd', 'label' => __( 'Doctor of Education (Ed.D.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Academic Years
        self::register( 'academic_years', array(
            'label'   => __( 'Academic Years', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'freshman', 'label' => __( 'Freshman (1st Year)', 'bits-accessible-forms' ) ),
                array( 'value' => 'sophomore', 'label' => __( 'Sophomore (2nd Year)', 'bits-accessible-forms' ) ),
                array( 'value' => 'junior', 'label' => __( 'Junior (3rd Year)', 'bits-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior (4th Year)', 'bits-accessible-forms' ) ),
                array( 'value' => 'graduate', 'label' => __( 'Graduate Student', 'bits-accessible-forms' ) ),
                array( 'value' => 'postgraduate', 'label' => __( 'Postgraduate', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Grade Levels
        self::register( 'grade_levels', array(
            'label'   => __( 'Grade Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'pre_k', 'label' => __( 'Pre-Kindergarten', 'bits-accessible-forms' ) ),
                array( 'value' => 'kindergarten', 'label' => __( 'Kindergarten', 'bits-accessible-forms' ) ),
                array( 'value' => '1st', 'label' => __( '1st Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '2nd', 'label' => __( '2nd Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '3rd', 'label' => __( '3rd Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '4th', 'label' => __( '4th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '5th', 'label' => __( '5th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '6th', 'label' => __( '6th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '7th', 'label' => __( '7th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '8th', 'label' => __( '8th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '9th', 'label' => __( '9th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '10th', 'label' => __( '10th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '11th', 'label' => __( '11th Grade', 'bits-accessible-forms' ) ),
                array( 'value' => '12th', 'label' => __( '12th Grade', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Fields of Study
        self::register( 'fields_of_study', array(
            'label'    => __( 'Fields of Study', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_fields_of_study' ),
        ) );

        // =====================================================================
        // EMPLOYMENT LIBRARIES
        // =====================================================================

        // Employment Status
        self::register( 'employment_status', array(
            'label'   => __( 'Employment Status', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'full_time', 'label' => __( 'Full-time Employee', 'bits-accessible-forms' ) ),
                array( 'value' => 'part_time', 'label' => __( 'Part-time Employee', 'bits-accessible-forms' ) ),
                array( 'value' => 'contract', 'label' => __( 'Contractor', 'bits-accessible-forms' ) ),
                array( 'value' => 'freelance', 'label' => __( 'Freelancer', 'bits-accessible-forms' ) ),
                array( 'value' => 'self_employed', 'label' => __( 'Self-employed', 'bits-accessible-forms' ) ),
                array( 'value' => 'business_owner', 'label' => __( 'Business Owner', 'bits-accessible-forms' ) ),
                array( 'value' => 'intern', 'label' => __( 'Intern', 'bits-accessible-forms' ) ),
                array( 'value' => 'temporary', 'label' => __( 'Temporary/Seasonal', 'bits-accessible-forms' ) ),
                array( 'value' => 'unemployed_seeking', 'label' => __( 'Unemployed - Seeking Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'unemployed_not_seeking', 'label' => __( 'Unemployed - Not Seeking Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'bits-accessible-forms' ) ),
                array( 'value' => 'homemaker', 'label' => __( 'Homemaker', 'bits-accessible-forms' ) ),
                array( 'value' => 'retired', 'label' => __( 'Retired', 'bits-accessible-forms' ) ),
                array( 'value' => 'disabled', 'label' => __( 'Unable to Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Job Levels
        self::register( 'job_levels', array(
            'label'   => __( 'Job Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'entry', 'label' => __( 'Entry Level', 'bits-accessible-forms' ) ),
                array( 'value' => 'junior', 'label' => __( 'Junior', 'bits-accessible-forms' ) ),
                array( 'value' => 'mid', 'label' => __( 'Mid-Level', 'bits-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior', 'bits-accessible-forms' ) ),
                array( 'value' => 'lead', 'label' => __( 'Lead/Principal', 'bits-accessible-forms' ) ),
                array( 'value' => 'manager', 'label' => __( 'Manager', 'bits-accessible-forms' ) ),
                array( 'value' => 'senior_manager', 'label' => __( 'Senior Manager', 'bits-accessible-forms' ) ),
                array( 'value' => 'director', 'label' => __( 'Director', 'bits-accessible-forms' ) ),
                array( 'value' => 'vp', 'label' => __( 'Vice President', 'bits-accessible-forms' ) ),
                array( 'value' => 'svp', 'label' => __( 'Senior Vice President', 'bits-accessible-forms' ) ),
                array( 'value' => 'c_level', 'label' => __( 'C-Level (CEO, CTO, CFO, etc.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'owner', 'label' => __( 'Owner/Founder', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Work Schedule Types
        self::register( 'work_schedules', array(
            'label'   => __( 'Work Schedule Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'full_time', 'label' => __( 'Full-time (40+ hrs/week)', 'bits-accessible-forms' ) ),
                array( 'value' => 'part_time', 'label' => __( 'Part-time', 'bits-accessible-forms' ) ),
                array( 'value' => 'flexible', 'label' => __( 'Flexible Hours', 'bits-accessible-forms' ) ),
                array( 'value' => 'shift', 'label' => __( 'Shift Work', 'bits-accessible-forms' ) ),
                array( 'value' => 'rotating', 'label' => __( 'Rotating Shift', 'bits-accessible-forms' ) ),
                array( 'value' => 'on_call', 'label' => __( 'On-Call', 'bits-accessible-forms' ) ),
                array( 'value' => 'compressed', 'label' => __( 'Compressed Work Week', 'bits-accessible-forms' ) ),
                array( 'value' => 'seasonal', 'label' => __( 'Seasonal', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Work Locations
        self::register( 'work_locations', array(
            'label'   => __( 'Work Location Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'onsite', 'label' => __( 'On-site', 'bits-accessible-forms' ) ),
                array( 'value' => 'remote', 'label' => __( 'Remote', 'bits-accessible-forms' ) ),
                array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'bits-accessible-forms' ) ),
                array( 'value' => 'field', 'label' => __( 'Field/Travel', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Industries (expanded)
        self::register( 'industries', array(
            'label'    => __( 'Industries', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_industries' ),
        ) );

        // Job Functions/Departments
        self::register( 'job_functions', array(
            'label'    => __( 'Job Functions', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_job_functions' ),
        ) );

        // Company Sizes
        self::register( 'company_sizes', array(
            'label'   => __( 'Company Sizes', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => __( '1 (Solo)', 'bits-accessible-forms' ) ),
                array( 'value' => '2_10', 'label' => __( '2-10', 'bits-accessible-forms' ) ),
                array( 'value' => '11_50', 'label' => __( '11-50', 'bits-accessible-forms' ) ),
                array( 'value' => '51_200', 'label' => __( '51-200', 'bits-accessible-forms' ) ),
                array( 'value' => '201_500', 'label' => __( '201-500', 'bits-accessible-forms' ) ),
                array( 'value' => '501_1000', 'label' => __( '501-1,000', 'bits-accessible-forms' ) ),
                array( 'value' => '1001_5000', 'label' => __( '1,001-5,000', 'bits-accessible-forms' ) ),
                array( 'value' => '5001_10000', 'label' => __( '5,001-10,000', 'bits-accessible-forms' ) ),
                array( 'value' => '10001_plus', 'label' => __( '10,000+', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Company Types
        self::register( 'company_types', array(
            'label'   => __( 'Company Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'public', 'label' => __( 'Public Company', 'bits-accessible-forms' ) ),
                array( 'value' => 'private', 'label' => __( 'Private Company', 'bits-accessible-forms' ) ),
                array( 'value' => 'startup', 'label' => __( 'Startup', 'bits-accessible-forms' ) ),
                array( 'value' => 'nonprofit', 'label' => __( 'Non-profit', 'bits-accessible-forms' ) ),
                array( 'value' => 'government', 'label' => __( 'Government', 'bits-accessible-forms' ) ),
                array( 'value' => 'education', 'label' => __( 'Educational Institution', 'bits-accessible-forms' ) ),
                array( 'value' => 'sole_proprietor', 'label' => __( 'Sole Proprietorship', 'bits-accessible-forms' ) ),
                array( 'value' => 'partnership', 'label' => __( 'Partnership', 'bits-accessible-forms' ) ),
                array( 'value' => 'llc', 'label' => __( 'LLC', 'bits-accessible-forms' ) ),
                array( 'value' => 'corporation', 'label' => __( 'Corporation', 'bits-accessible-forms' ) ),
                array( 'value' => 'cooperative', 'label' => __( 'Cooperative', 'bits-accessible-forms' ) ),
                array( 'value' => 'franchise', 'label' => __( 'Franchise', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Experience Years
        self::register( 'experience_years', array(
            'label'   => __( 'Years of Experience', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => '0', 'label' => __( 'No Experience', 'bits-accessible-forms' ) ),
                array( 'value' => 'less_1', 'label' => __( 'Less than 1 year', 'bits-accessible-forms' ) ),
                array( 'value' => '1_2', 'label' => __( '1-2 years', 'bits-accessible-forms' ) ),
                array( 'value' => '3_5', 'label' => __( '3-5 years', 'bits-accessible-forms' ) ),
                array( 'value' => '6_10', 'label' => __( '6-10 years', 'bits-accessible-forms' ) ),
                array( 'value' => '11_15', 'label' => __( '11-15 years', 'bits-accessible-forms' ) ),
                array( 'value' => '16_20', 'label' => __( '16-20 years', 'bits-accessible-forms' ) ),
                array( 'value' => '20_plus', 'label' => __( '20+ years', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // PRODUCT & E-COMMERCE LIBRARIES
        // =====================================================================

        // Clothing Sizes
        self::register( 'clothing_sizes', array(
            'label'   => __( 'Clothing Sizes', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'xxs', 'label' => __( 'XXS', 'bits-accessible-forms' ) ),
                array( 'value' => 'xs', 'label' => __( 'XS', 'bits-accessible-forms' ) ),
                array( 'value' => 's', 'label' => __( 'S', 'bits-accessible-forms' ) ),
                array( 'value' => 'm', 'label' => __( 'M', 'bits-accessible-forms' ) ),
                array( 'value' => 'l', 'label' => __( 'L', 'bits-accessible-forms' ) ),
                array( 'value' => 'xl', 'label' => __( 'XL', 'bits-accessible-forms' ) ),
                array( 'value' => 'xxl', 'label' => __( 'XXL', 'bits-accessible-forms' ) ),
                array( 'value' => 'xxxl', 'label' => __( 'XXXL', 'bits-accessible-forms' ) ),
                array( 'value' => '4xl', 'label' => __( '4XL', 'bits-accessible-forms' ) ),
                array( 'value' => '5xl', 'label' => __( '5XL', 'bits-accessible-forms' ) ),
            ),
        ) );

        // T-Shirt Sizes
        self::register( 'tshirt_sizes', array(
            'label'   => __( 'T-Shirt Sizes', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'xs', 'label' => __( 'XS (Extra Small)', 'bits-accessible-forms' ) ),
                array( 'value' => 's', 'label' => __( 'S (Small)', 'bits-accessible-forms' ) ),
                array( 'value' => 'm', 'label' => __( 'M (Medium)', 'bits-accessible-forms' ) ),
                array( 'value' => 'l', 'label' => __( 'L (Large)', 'bits-accessible-forms' ) ),
                array( 'value' => 'xl', 'label' => __( 'XL (Extra Large)', 'bits-accessible-forms' ) ),
                array( 'value' => '2xl', 'label' => __( '2XL', 'bits-accessible-forms' ) ),
                array( 'value' => '3xl', 'label' => __( '3XL', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Shoe Sizes (US)
        self::register( 'shoe_sizes_us', array(
            'label'    => __( 'Shoe Sizes (US)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_shoe_sizes_us' ),
        ) );

        // Shoe Sizes (EU)
        self::register( 'shoe_sizes_eu', array(
            'label'    => __( 'Shoe Sizes (EU)', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_shoe_sizes_eu' ),
        ) );

        // Product Conditions
        self::register( 'product_conditions', array(
            'label'   => __( 'Product Conditions', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'new', 'label' => __( 'New', 'bits-accessible-forms' ) ),
                array( 'value' => 'new_open_box', 'label' => __( 'New (Open Box)', 'bits-accessible-forms' ) ),
                array( 'value' => 'refurbished', 'label' => __( 'Refurbished', 'bits-accessible-forms' ) ),
                array( 'value' => 'like_new', 'label' => __( 'Like New', 'bits-accessible-forms' ) ),
                array( 'value' => 'excellent', 'label' => __( 'Excellent', 'bits-accessible-forms' ) ),
                array( 'value' => 'good', 'label' => __( 'Good', 'bits-accessible-forms' ) ),
                array( 'value' => 'fair', 'label' => __( 'Fair', 'bits-accessible-forms' ) ),
                array( 'value' => 'poor', 'label' => __( 'Poor', 'bits-accessible-forms' ) ),
                array( 'value' => 'for_parts', 'label' => __( 'For Parts/Not Working', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Shipping Methods
        self::register( 'shipping_methods', array(
            'label'   => __( 'Shipping Methods', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'standard', 'label' => __( 'Standard Shipping', 'bits-accessible-forms' ) ),
                array( 'value' => 'express', 'label' => __( 'Express Shipping', 'bits-accessible-forms' ) ),
                array( 'value' => 'overnight', 'label' => __( 'Overnight/Next Day', 'bits-accessible-forms' ) ),
                array( 'value' => 'two_day', 'label' => __( '2-Day Shipping', 'bits-accessible-forms' ) ),
                array( 'value' => 'economy', 'label' => __( 'Economy Shipping', 'bits-accessible-forms' ) ),
                array( 'value' => 'freight', 'label' => __( 'Freight', 'bits-accessible-forms' ) ),
                array( 'value' => 'local_pickup', 'label' => __( 'Local Pickup', 'bits-accessible-forms' ) ),
                array( 'value' => 'international', 'label' => __( 'International', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // HEALTH & MEDICAL LIBRARIES
        // =====================================================================

        // Blood Types
        self::register( 'blood_types', array(
            'label'   => __( 'Blood Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'a_positive', 'label' => __( 'A+', 'bits-accessible-forms' ) ),
                array( 'value' => 'a_negative', 'label' => __( 'A-', 'bits-accessible-forms' ) ),
                array( 'value' => 'b_positive', 'label' => __( 'B+', 'bits-accessible-forms' ) ),
                array( 'value' => 'b_negative', 'label' => __( 'B-', 'bits-accessible-forms' ) ),
                array( 'value' => 'ab_positive', 'label' => __( 'AB+', 'bits-accessible-forms' ) ),
                array( 'value' => 'ab_negative', 'label' => __( 'AB-', 'bits-accessible-forms' ) ),
                array( 'value' => 'o_positive', 'label' => __( 'O+', 'bits-accessible-forms' ) ),
                array( 'value' => 'o_negative', 'label' => __( 'O-', 'bits-accessible-forms' ) ),
                array( 'value' => 'unknown', 'label' => __( 'Unknown', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Dietary Restrictions
        self::register( 'dietary_restrictions', array(
            'label'   => __( 'Dietary Restrictions', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'none', 'label' => __( 'None', 'bits-accessible-forms' ) ),
                array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'bits-accessible-forms' ) ),
                array( 'value' => 'vegan', 'label' => __( 'Vegan', 'bits-accessible-forms' ) ),
                array( 'value' => 'pescatarian', 'label' => __( 'Pescatarian', 'bits-accessible-forms' ) ),
                array( 'value' => 'gluten_free', 'label' => __( 'Gluten-Free', 'bits-accessible-forms' ) ),
                array( 'value' => 'dairy_free', 'label' => __( 'Dairy-Free', 'bits-accessible-forms' ) ),
                array( 'value' => 'nut_free', 'label' => __( 'Nut-Free', 'bits-accessible-forms' ) ),
                array( 'value' => 'kosher', 'label' => __( 'Kosher', 'bits-accessible-forms' ) ),
                array( 'value' => 'halal', 'label' => __( 'Halal', 'bits-accessible-forms' ) ),
                array( 'value' => 'keto', 'label' => __( 'Keto', 'bits-accessible-forms' ) ),
                array( 'value' => 'paleo', 'label' => __( 'Paleo', 'bits-accessible-forms' ) ),
                array( 'value' => 'low_sodium', 'label' => __( 'Low Sodium', 'bits-accessible-forms' ) ),
                array( 'value' => 'diabetic', 'label' => __( 'Diabetic-Friendly', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Common Allergies
        self::register( 'allergies', array(
            'label'   => __( 'Common Allergies', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'none', 'label' => __( 'None', 'bits-accessible-forms' ) ),
                array( 'value' => 'peanuts', 'label' => __( 'Peanuts', 'bits-accessible-forms' ) ),
                array( 'value' => 'tree_nuts', 'label' => __( 'Tree Nuts', 'bits-accessible-forms' ) ),
                array( 'value' => 'milk', 'label' => __( 'Milk/Dairy', 'bits-accessible-forms' ) ),
                array( 'value' => 'eggs', 'label' => __( 'Eggs', 'bits-accessible-forms' ) ),
                array( 'value' => 'wheat', 'label' => __( 'Wheat', 'bits-accessible-forms' ) ),
                array( 'value' => 'soy', 'label' => __( 'Soy', 'bits-accessible-forms' ) ),
                array( 'value' => 'fish', 'label' => __( 'Fish', 'bits-accessible-forms' ) ),
                array( 'value' => 'shellfish', 'label' => __( 'Shellfish', 'bits-accessible-forms' ) ),
                array( 'value' => 'sesame', 'label' => __( 'Sesame', 'bits-accessible-forms' ) ),
                array( 'value' => 'latex', 'label' => __( 'Latex', 'bits-accessible-forms' ) ),
                array( 'value' => 'penicillin', 'label' => __( 'Penicillin', 'bits-accessible-forms' ) ),
                array( 'value' => 'sulfa', 'label' => __( 'Sulfa Drugs', 'bits-accessible-forms' ) ),
                array( 'value' => 'aspirin', 'label' => __( 'Aspirin', 'bits-accessible-forms' ) ),
                array( 'value' => 'insect_stings', 'label' => __( 'Insect Stings', 'bits-accessible-forms' ) ),
                array( 'value' => 'pollen', 'label' => __( 'Pollen', 'bits-accessible-forms' ) ),
                array( 'value' => 'pet_dander', 'label' => __( 'Pet Dander', 'bits-accessible-forms' ) ),
                array( 'value' => 'dust_mites', 'label' => __( 'Dust Mites', 'bits-accessible-forms' ) ),
                array( 'value' => 'mold', 'label' => __( 'Mold', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Medical Specialties
        self::register( 'medical_specialties', array(
            'label'    => __( 'Medical Specialties', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_medical_specialties' ),
        ) );

        // Insurance Types
        self::register( 'insurance_types', array(
            'label'   => __( 'Insurance Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'health', 'label' => __( 'Health Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'dental', 'label' => __( 'Dental Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'vision', 'label' => __( 'Vision Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'life', 'label' => __( 'Life Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'auto', 'label' => __( 'Auto Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'home', 'label' => __( 'Homeowners Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'renters', 'label' => __( 'Renters Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'disability', 'label' => __( 'Disability Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'travel', 'label' => __( 'Travel Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'pet', 'label' => __( 'Pet Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'business', 'label' => __( 'Business Insurance', 'bits-accessible-forms' ) ),
                array( 'value' => 'liability', 'label' => __( 'Liability Insurance', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // TRAVEL & HOSPITALITY LIBRARIES
        // =====================================================================

        // Room Types
        self::register( 'room_types', array(
            'label'   => __( 'Room Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'single', 'label' => __( 'Single Room', 'bits-accessible-forms' ) ),
                array( 'value' => 'double', 'label' => __( 'Double Room', 'bits-accessible-forms' ) ),
                array( 'value' => 'twin', 'label' => __( 'Twin Room', 'bits-accessible-forms' ) ),
                array( 'value' => 'queen', 'label' => __( 'Queen Room', 'bits-accessible-forms' ) ),
                array( 'value' => 'king', 'label' => __( 'King Room', 'bits-accessible-forms' ) ),
                array( 'value' => 'suite', 'label' => __( 'Suite', 'bits-accessible-forms' ) ),
                array( 'value' => 'penthouse', 'label' => __( 'Penthouse', 'bits-accessible-forms' ) ),
                array( 'value' => 'studio', 'label' => __( 'Studio', 'bits-accessible-forms' ) ),
                array( 'value' => 'apartment', 'label' => __( 'Apartment', 'bits-accessible-forms' ) ),
                array( 'value' => 'villa', 'label' => __( 'Villa', 'bits-accessible-forms' ) ),
                array( 'value' => 'cabin', 'label' => __( 'Cabin', 'bits-accessible-forms' ) ),
                array( 'value' => 'dormitory', 'label' => __( 'Dormitory', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Meal Plans
        self::register( 'meal_plans', array(
            'label'   => __( 'Meal Plans', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'room_only', 'label' => __( 'Room Only', 'bits-accessible-forms' ) ),
                array( 'value' => 'breakfast', 'label' => __( 'Bed & Breakfast', 'bits-accessible-forms' ) ),
                array( 'value' => 'half_board', 'label' => __( 'Half Board (Breakfast + Dinner)', 'bits-accessible-forms' ) ),
                array( 'value' => 'full_board', 'label' => __( 'Full Board (All Meals)', 'bits-accessible-forms' ) ),
                array( 'value' => 'all_inclusive', 'label' => __( 'All Inclusive', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Travel Classes
        self::register( 'travel_classes', array(
            'label'   => __( 'Travel Classes', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'economy', 'label' => __( 'Economy', 'bits-accessible-forms' ) ),
                array( 'value' => 'premium_economy', 'label' => __( 'Premium Economy', 'bits-accessible-forms' ) ),
                array( 'value' => 'business', 'label' => __( 'Business Class', 'bits-accessible-forms' ) ),
                array( 'value' => 'first', 'label' => __( 'First Class', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Passenger Types
        self::register( 'passenger_types', array(
            'label'   => __( 'Passenger Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'adult', 'label' => __( 'Adult (12+)', 'bits-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child (2-11)', 'bits-accessible-forms' ) ),
                array( 'value' => 'infant', 'label' => __( 'Infant (under 2)', 'bits-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior (65+)', 'bits-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'bits-accessible-forms' ) ),
                array( 'value' => 'military', 'label' => __( 'Military', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Trip Types
        self::register( 'trip_types', array(
            'label'   => __( 'Trip Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'one_way', 'label' => __( 'One Way', 'bits-accessible-forms' ) ),
                array( 'value' => 'round_trip', 'label' => __( 'Round Trip', 'bits-accessible-forms' ) ),
                array( 'value' => 'multi_city', 'label' => __( 'Multi-City', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // EVENT & BOOKING LIBRARIES
        // =====================================================================

        // Event Types
        self::register( 'event_types', array(
            'label'   => __( 'Event Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'conference', 'label' => __( 'Conference', 'bits-accessible-forms' ) ),
                array( 'value' => 'workshop', 'label' => __( 'Workshop', 'bits-accessible-forms' ) ),
                array( 'value' => 'seminar', 'label' => __( 'Seminar', 'bits-accessible-forms' ) ),
                array( 'value' => 'webinar', 'label' => __( 'Webinar', 'bits-accessible-forms' ) ),
                array( 'value' => 'meeting', 'label' => __( 'Meeting', 'bits-accessible-forms' ) ),
                array( 'value' => 'training', 'label' => __( 'Training', 'bits-accessible-forms' ) ),
                array( 'value' => 'networking', 'label' => __( 'Networking', 'bits-accessible-forms' ) ),
                array( 'value' => 'tradeshow', 'label' => __( 'Trade Show', 'bits-accessible-forms' ) ),
                array( 'value' => 'concert', 'label' => __( 'Concert', 'bits-accessible-forms' ) ),
                array( 'value' => 'festival', 'label' => __( 'Festival', 'bits-accessible-forms' ) ),
                array( 'value' => 'sports', 'label' => __( 'Sports Event', 'bits-accessible-forms' ) ),
                array( 'value' => 'wedding', 'label' => __( 'Wedding', 'bits-accessible-forms' ) ),
                array( 'value' => 'birthday', 'label' => __( 'Birthday Party', 'bits-accessible-forms' ) ),
                array( 'value' => 'anniversary', 'label' => __( 'Anniversary', 'bits-accessible-forms' ) ),
                array( 'value' => 'corporate', 'label' => __( 'Corporate Event', 'bits-accessible-forms' ) ),
                array( 'value' => 'fundraiser', 'label' => __( 'Fundraiser', 'bits-accessible-forms' ) ),
                array( 'value' => 'graduation', 'label' => __( 'Graduation', 'bits-accessible-forms' ) ),
                array( 'value' => 'reunion', 'label' => __( 'Reunion', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Attendance Types
        self::register( 'attendance_types', array(
            'label'   => __( 'Attendance Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'in_person', 'label' => __( 'In Person', 'bits-accessible-forms' ) ),
                array( 'value' => 'virtual', 'label' => __( 'Virtual/Online', 'bits-accessible-forms' ) ),
                array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'bits-accessible-forms' ) ),
            ),
        ) );

        // RSVP Responses
        self::register( 'rsvp_responses', array(
            'label'   => __( 'RSVP Responses', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes, I will attend', 'bits-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No, I cannot attend', 'bits-accessible-forms' ) ),
                array( 'value' => 'maybe', 'label' => __( 'Maybe', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Ticket Types
        self::register( 'ticket_types', array(
            'label'   => __( 'Ticket Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'general', 'label' => __( 'General Admission', 'bits-accessible-forms' ) ),
                array( 'value' => 'vip', 'label' => __( 'VIP', 'bits-accessible-forms' ) ),
                array( 'value' => 'premium', 'label' => __( 'Premium', 'bits-accessible-forms' ) ),
                array( 'value' => 'early_bird', 'label' => __( 'Early Bird', 'bits-accessible-forms' ) ),
                array( 'value' => 'student', 'label' => __( 'Student', 'bits-accessible-forms' ) ),
                array( 'value' => 'senior', 'label' => __( 'Senior', 'bits-accessible-forms' ) ),
                array( 'value' => 'child', 'label' => __( 'Child', 'bits-accessible-forms' ) ),
                array( 'value' => 'family', 'label' => __( 'Family Pack', 'bits-accessible-forms' ) ),
                array( 'value' => 'group', 'label' => __( 'Group', 'bits-accessible-forms' ) ),
                array( 'value' => 'comp', 'label' => __( 'Complimentary', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // RATING & FEEDBACK LIBRARIES
        // =====================================================================

        // Rating Scales (1-5)
        self::register( 'ratings_5', array(
            'label'   => __( 'Ratings (1-5)', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => '1', 'label' => __( '1 - Poor', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( '2 - Fair', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( '3 - Good', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( '4 - Very Good', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( '5 - Excellent', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Rating Scales (1-10)
        self::register( 'ratings_10', array(
            'label'   => __( 'Ratings (1-10)', 'bits-accessible-forms' ),
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
            'label'   => __( 'Satisfaction Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'very_dissatisfied', 'label' => __( 'Very Dissatisfied', 'bits-accessible-forms' ) ),
                array( 'value' => 'dissatisfied', 'label' => __( 'Dissatisfied', 'bits-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => 'satisfied', 'label' => __( 'Satisfied', 'bits-accessible-forms' ) ),
                array( 'value' => 'very_satisfied', 'label' => __( 'Very Satisfied', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Agreement Levels
        self::register( 'agreement', array(
            'label'   => __( 'Agreement Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'strongly_disagree', 'label' => __( 'Strongly Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => 'disagree', 'label' => __( 'Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => 'agree', 'label' => __( 'Agree', 'bits-accessible-forms' ) ),
                array( 'value' => 'strongly_agree', 'label' => __( 'Strongly Agree', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Likelihood Levels
        self::register( 'likelihood', array(
            'label'   => __( 'Likelihood Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'very_unlikely', 'label' => __( 'Very Unlikely', 'bits-accessible-forms' ) ),
                array( 'value' => 'unlikely', 'label' => __( 'Unlikely', 'bits-accessible-forms' ) ),
                array( 'value' => 'neutral', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => 'likely', 'label' => __( 'Likely', 'bits-accessible-forms' ) ),
                array( 'value' => 'very_likely', 'label' => __( 'Very Likely', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Frequency
        self::register( 'frequency', array(
            'label'   => __( 'Frequency', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'never', 'label' => __( 'Never', 'bits-accessible-forms' ) ),
                array( 'value' => 'rarely', 'label' => __( 'Rarely', 'bits-accessible-forms' ) ),
                array( 'value' => 'sometimes', 'label' => __( 'Sometimes', 'bits-accessible-forms' ) ),
                array( 'value' => 'often', 'label' => __( 'Often', 'bits-accessible-forms' ) ),
                array( 'value' => 'always', 'label' => __( 'Always', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Importance
        self::register( 'importance', array(
            'label'   => __( 'Importance Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'not_important', 'label' => __( 'Not Important', 'bits-accessible-forms' ) ),
                array( 'value' => 'slightly_important', 'label' => __( 'Slightly Important', 'bits-accessible-forms' ) ),
                array( 'value' => 'moderately_important', 'label' => __( 'Moderately Important', 'bits-accessible-forms' ) ),
                array( 'value' => 'important', 'label' => __( 'Important', 'bits-accessible-forms' ) ),
                array( 'value' => 'very_important', 'label' => __( 'Very Important', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Priority
        self::register( 'priority', array(
            'label'   => __( 'Priority Levels', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'lowest', 'label' => __( 'Lowest', 'bits-accessible-forms' ) ),
                array( 'value' => 'low', 'label' => __( 'Low', 'bits-accessible-forms' ) ),
                array( 'value' => 'medium', 'label' => __( 'Medium', 'bits-accessible-forms' ) ),
                array( 'value' => 'high', 'label' => __( 'High', 'bits-accessible-forms' ) ),
                array( 'value' => 'highest', 'label' => __( 'Highest', 'bits-accessible-forms' ) ),
                array( 'value' => 'critical', 'label' => __( 'Critical', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // TECHNOLOGY LIBRARIES
        // =====================================================================

        // Operating Systems
        self::register( 'operating_systems', array(
            'label'   => __( 'Operating Systems', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'windows', 'label' => __( 'Windows', 'bits-accessible-forms' ) ),
                array( 'value' => 'macos', 'label' => __( 'macOS', 'bits-accessible-forms' ) ),
                array( 'value' => 'linux', 'label' => __( 'Linux', 'bits-accessible-forms' ) ),
                array( 'value' => 'chrome_os', 'label' => __( 'Chrome OS', 'bits-accessible-forms' ) ),
                array( 'value' => 'ios', 'label' => __( 'iOS', 'bits-accessible-forms' ) ),
                array( 'value' => 'android', 'label' => __( 'Android', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Browsers
        self::register( 'browsers', array(
            'label'   => __( 'Web Browsers', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'chrome', 'label' => __( 'Google Chrome', 'bits-accessible-forms' ) ),
                array( 'value' => 'firefox', 'label' => __( 'Mozilla Firefox', 'bits-accessible-forms' ) ),
                array( 'value' => 'safari', 'label' => __( 'Safari', 'bits-accessible-forms' ) ),
                array( 'value' => 'edge', 'label' => __( 'Microsoft Edge', 'bits-accessible-forms' ) ),
                array( 'value' => 'opera', 'label' => __( 'Opera', 'bits-accessible-forms' ) ),
                array( 'value' => 'brave', 'label' => __( 'Brave', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Devices
        self::register( 'devices', array(
            'label'   => __( 'Device Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'desktop', 'label' => __( 'Desktop Computer', 'bits-accessible-forms' ) ),
                array( 'value' => 'laptop', 'label' => __( 'Laptop', 'bits-accessible-forms' ) ),
                array( 'value' => 'tablet', 'label' => __( 'Tablet', 'bits-accessible-forms' ) ),
                array( 'value' => 'smartphone', 'label' => __( 'Smartphone', 'bits-accessible-forms' ) ),
                array( 'value' => 'smartwatch', 'label' => __( 'Smartwatch', 'bits-accessible-forms' ) ),
                array( 'value' => 'smart_tv', 'label' => __( 'Smart TV', 'bits-accessible-forms' ) ),
                array( 'value' => 'gaming_console', 'label' => __( 'Gaming Console', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // File Types
        self::register( 'file_types', array(
            'label'   => __( 'File Types', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'pdf', 'label' => __( 'PDF', 'bits-accessible-forms' ) ),
                array( 'value' => 'doc', 'label' => __( 'Word Document (.doc/.docx)', 'bits-accessible-forms' ) ),
                array( 'value' => 'xls', 'label' => __( 'Excel Spreadsheet (.xls/.xlsx)', 'bits-accessible-forms' ) ),
                array( 'value' => 'ppt', 'label' => __( 'PowerPoint (.ppt/.pptx)', 'bits-accessible-forms' ) ),
                array( 'value' => 'txt', 'label' => __( 'Text File (.txt)', 'bits-accessible-forms' ) ),
                array( 'value' => 'csv', 'label' => __( 'CSV', 'bits-accessible-forms' ) ),
                array( 'value' => 'jpg', 'label' => __( 'JPEG Image', 'bits-accessible-forms' ) ),
                array( 'value' => 'png', 'label' => __( 'PNG Image', 'bits-accessible-forms' ) ),
                array( 'value' => 'gif', 'label' => __( 'GIF', 'bits-accessible-forms' ) ),
                array( 'value' => 'mp3', 'label' => __( 'MP3 Audio', 'bits-accessible-forms' ) ),
                array( 'value' => 'mp4', 'label' => __( 'MP4 Video', 'bits-accessible-forms' ) ),
                array( 'value' => 'zip', 'label' => __( 'ZIP Archive', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // SIMPLE YES/NO & BOOLEAN LIBRARIES
        // =====================================================================

        // Yes/No
        self::register( 'yes_no', array(
            'label'   => __( 'Yes/No', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'bits-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Yes/No/Maybe
        self::register( 'yes_no_maybe', array(
            'label'   => __( 'Yes/No/Maybe', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'bits-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'bits-accessible-forms' ) ),
                array( 'value' => 'maybe', 'label' => __( 'Maybe', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Yes/No/Not Applicable
        self::register( 'yes_no_na', array(
            'label'   => __( 'Yes/No/N/A', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'yes', 'label' => __( 'Yes', 'bits-accessible-forms' ) ),
                array( 'value' => 'no', 'label' => __( 'No', 'bits-accessible-forms' ) ),
                array( 'value' => 'na', 'label' => __( 'Not Applicable', 'bits-accessible-forms' ) ),
            ),
        ) );

        // True/False
        self::register( 'true_false', array(
            'label'   => __( 'True/False', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'true', 'label' => __( 'True', 'bits-accessible-forms' ) ),
                array( 'value' => 'false', 'label' => __( 'False', 'bits-accessible-forms' ) ),
            ),
        ) );

        // =====================================================================
        // MISCELLANEOUS LIBRARIES
        // =====================================================================

        // How Did You Hear About Us
        self::register( 'referral_sources', array(
            'label'   => __( 'Referral Sources', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'search_engine', 'label' => __( 'Search Engine (Google, Bing, etc.)', 'bits-accessible-forms' ) ),
                array( 'value' => 'social_media', 'label' => __( 'Social Media', 'bits-accessible-forms' ) ),
                array( 'value' => 'friend_family', 'label' => __( 'Friend or Family', 'bits-accessible-forms' ) ),
                array( 'value' => 'colleague', 'label' => __( 'Colleague/Coworker', 'bits-accessible-forms' ) ),
                array( 'value' => 'advertisement', 'label' => __( 'Advertisement', 'bits-accessible-forms' ) ),
                array( 'value' => 'blog_article', 'label' => __( 'Blog/Article', 'bits-accessible-forms' ) ),
                array( 'value' => 'podcast', 'label' => __( 'Podcast', 'bits-accessible-forms' ) ),
                array( 'value' => 'youtube', 'label' => __( 'YouTube', 'bits-accessible-forms' ) ),
                array( 'value' => 'newsletter', 'label' => __( 'Newsletter/Email', 'bits-accessible-forms' ) ),
                array( 'value' => 'event', 'label' => __( 'Event/Conference', 'bits-accessible-forms' ) ),
                array( 'value' => 'review_site', 'label' => __( 'Review Site', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Quantity Selectors
        self::register( 'quantities', array(
            'label'    => __( 'Quantities', 'bits-accessible-forms' ),
            'callback' => array( __CLASS__, 'get_quantities' ),
        ) );

        // Number of People
        self::register( 'num_people', array(
            'label'   => __( 'Number of People', 'bits-accessible-forms' ),
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
                array( 'value' => '10_plus', 'label' => __( '10+', 'bits-accessible-forms' ) ),
            ),
        ) );

        // Colors
        self::register( 'colors', array(
            'label'   => __( 'Colors', 'bits-accessible-forms' ),
            'choices' => array(
                array( 'value' => 'red', 'label' => __( 'Red', 'bits-accessible-forms' ) ),
                array( 'value' => 'orange', 'label' => __( 'Orange', 'bits-accessible-forms' ) ),
                array( 'value' => 'yellow', 'label' => __( 'Yellow', 'bits-accessible-forms' ) ),
                array( 'value' => 'green', 'label' => __( 'Green', 'bits-accessible-forms' ) ),
                array( 'value' => 'blue', 'label' => __( 'Blue', 'bits-accessible-forms' ) ),
                array( 'value' => 'purple', 'label' => __( 'Purple', 'bits-accessible-forms' ) ),
                array( 'value' => 'pink', 'label' => __( 'Pink', 'bits-accessible-forms' ) ),
                array( 'value' => 'brown', 'label' => __( 'Brown', 'bits-accessible-forms' ) ),
                array( 'value' => 'black', 'label' => __( 'Black', 'bits-accessible-forms' ) ),
                array( 'value' => 'white', 'label' => __( 'White', 'bits-accessible-forms' ) ),
                array( 'value' => 'gray', 'label' => __( 'Gray', 'bits-accessible-forms' ) ),
                array( 'value' => 'silver', 'label' => __( 'Silver', 'bits-accessible-forms' ) ),
                array( 'value' => 'gold', 'label' => __( 'Gold', 'bits-accessible-forms' ) ),
                array( 'value' => 'navy', 'label' => __( 'Navy', 'bits-accessible-forms' ) ),
                array( 'value' => 'teal', 'label' => __( 'Teal', 'bits-accessible-forms' ) ),
                array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'AF', 'label' => __( 'Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'AN', 'label' => __( 'Antarctica', 'bits-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'EU', 'label' => __( 'Europe', 'bits-accessible-forms' ) ),
            array( 'value' => 'NA', 'label' => __( 'North America', 'bits-accessible-forms' ) ),
            array( 'value' => 'OC', 'label' => __( 'Oceania', 'bits-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'South America', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'northern_africa', 'label' => __( 'Northern Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'western_africa', 'label' => __( 'Western Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'middle_africa', 'label' => __( 'Middle Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'eastern_africa', 'label' => __( 'Eastern Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'southern_africa', 'label' => __( 'Southern Africa', 'bits-accessible-forms' ) ),
            // Americas
            array( 'value' => 'northern_america', 'label' => __( 'Northern America', 'bits-accessible-forms' ) ),
            array( 'value' => 'central_america', 'label' => __( 'Central America', 'bits-accessible-forms' ) ),
            array( 'value' => 'caribbean', 'label' => __( 'Caribbean', 'bits-accessible-forms' ) ),
            array( 'value' => 'south_america', 'label' => __( 'South America', 'bits-accessible-forms' ) ),
            // Asia
            array( 'value' => 'central_asia', 'label' => __( 'Central Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'eastern_asia', 'label' => __( 'Eastern Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'southern_asia', 'label' => __( 'Southern Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'southeastern_asia', 'label' => __( 'South-Eastern Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'western_asia', 'label' => __( 'Western Asia', 'bits-accessible-forms' ) ),
            // Europe
            array( 'value' => 'eastern_europe', 'label' => __( 'Eastern Europe', 'bits-accessible-forms' ) ),
            array( 'value' => 'northern_europe', 'label' => __( 'Northern Europe', 'bits-accessible-forms' ) ),
            array( 'value' => 'southern_europe', 'label' => __( 'Southern Europe', 'bits-accessible-forms' ) ),
            array( 'value' => 'western_europe', 'label' => __( 'Western Europe', 'bits-accessible-forms' ) ),
            // Oceania
            array( 'value' => 'australia_nz', 'label' => __( 'Australia and New Zealand', 'bits-accessible-forms' ) ),
            array( 'value' => 'melanesia', 'label' => __( 'Melanesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'micronesia', 'label' => __( 'Micronesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'polynesia', 'label' => __( 'Polynesia', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get complete countries list (ISO 3166-1 alpha-2)
     *
     * @return array Countries.
     */
    public static function get_countries() {
        return array(
            array( 'value' => 'AF', 'label' => __( 'Afghanistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'AX', 'label' => __( 'Åland Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'AL', 'label' => __( 'Albania', 'bits-accessible-forms' ) ),
            array( 'value' => 'DZ', 'label' => __( 'Algeria', 'bits-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'American Samoa', 'bits-accessible-forms' ) ),
            array( 'value' => 'AD', 'label' => __( 'Andorra', 'bits-accessible-forms' ) ),
            array( 'value' => 'AO', 'label' => __( 'Angola', 'bits-accessible-forms' ) ),
            array( 'value' => 'AI', 'label' => __( 'Anguilla', 'bits-accessible-forms' ) ),
            array( 'value' => 'AQ', 'label' => __( 'Antarctica', 'bits-accessible-forms' ) ),
            array( 'value' => 'AG', 'label' => __( 'Antigua and Barbuda', 'bits-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Argentina', 'bits-accessible-forms' ) ),
            array( 'value' => 'AM', 'label' => __( 'Armenia', 'bits-accessible-forms' ) ),
            array( 'value' => 'AW', 'label' => __( 'Aruba', 'bits-accessible-forms' ) ),
            array( 'value' => 'AU', 'label' => __( 'Australia', 'bits-accessible-forms' ) ),
            array( 'value' => 'AT', 'label' => __( 'Austria', 'bits-accessible-forms' ) ),
            array( 'value' => 'AZ', 'label' => __( 'Azerbaijan', 'bits-accessible-forms' ) ),
            array( 'value' => 'BS', 'label' => __( 'Bahamas', 'bits-accessible-forms' ) ),
            array( 'value' => 'BH', 'label' => __( 'Bahrain', 'bits-accessible-forms' ) ),
            array( 'value' => 'BD', 'label' => __( 'Bangladesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'BB', 'label' => __( 'Barbados', 'bits-accessible-forms' ) ),
            array( 'value' => 'BY', 'label' => __( 'Belarus', 'bits-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Belgium', 'bits-accessible-forms' ) ),
            array( 'value' => 'BZ', 'label' => __( 'Belize', 'bits-accessible-forms' ) ),
            array( 'value' => 'BJ', 'label' => __( 'Benin', 'bits-accessible-forms' ) ),
            array( 'value' => 'BM', 'label' => __( 'Bermuda', 'bits-accessible-forms' ) ),
            array( 'value' => 'BT', 'label' => __( 'Bhutan', 'bits-accessible-forms' ) ),
            array( 'value' => 'BO', 'label' => __( 'Bolivia', 'bits-accessible-forms' ) ),
            array( 'value' => 'BQ', 'label' => __( 'Bonaire, Sint Eustatius and Saba', 'bits-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bosnia and Herzegovina', 'bits-accessible-forms' ) ),
            array( 'value' => 'BW', 'label' => __( 'Botswana', 'bits-accessible-forms' ) ),
            array( 'value' => 'BV', 'label' => __( 'Bouvet Island', 'bits-accessible-forms' ) ),
            array( 'value' => 'BR', 'label' => __( 'Brazil', 'bits-accessible-forms' ) ),
            array( 'value' => 'IO', 'label' => __( 'British Indian Ocean Territory', 'bits-accessible-forms' ) ),
            array( 'value' => 'BN', 'label' => __( 'Brunei Darussalam', 'bits-accessible-forms' ) ),
            array( 'value' => 'BG', 'label' => __( 'Bulgaria', 'bits-accessible-forms' ) ),
            array( 'value' => 'BF', 'label' => __( 'Burkina Faso', 'bits-accessible-forms' ) ),
            array( 'value' => 'BI', 'label' => __( 'Burundi', 'bits-accessible-forms' ) ),
            array( 'value' => 'CV', 'label' => __( 'Cabo Verde', 'bits-accessible-forms' ) ),
            array( 'value' => 'KH', 'label' => __( 'Cambodia', 'bits-accessible-forms' ) ),
            array( 'value' => 'CM', 'label' => __( 'Cameroon', 'bits-accessible-forms' ) ),
            array( 'value' => 'CA', 'label' => __( 'Canada', 'bits-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Cayman Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'CF', 'label' => __( 'Central African Republic', 'bits-accessible-forms' ) ),
            array( 'value' => 'TD', 'label' => __( 'Chad', 'bits-accessible-forms' ) ),
            array( 'value' => 'CL', 'label' => __( 'Chile', 'bits-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'China', 'bits-accessible-forms' ) ),
            array( 'value' => 'CX', 'label' => __( 'Christmas Island', 'bits-accessible-forms' ) ),
            array( 'value' => 'CC', 'label' => __( 'Cocos (Keeling) Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Colombia', 'bits-accessible-forms' ) ),
            array( 'value' => 'KM', 'label' => __( 'Comoros', 'bits-accessible-forms' ) ),
            array( 'value' => 'CG', 'label' => __( 'Congo', 'bits-accessible-forms' ) ),
            array( 'value' => 'CD', 'label' => __( 'Congo, Democratic Republic', 'bits-accessible-forms' ) ),
            array( 'value' => 'CK', 'label' => __( 'Cook Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'CR', 'label' => __( 'Costa Rica', 'bits-accessible-forms' ) ),
            array( 'value' => 'CI', 'label' => __( 'Côte d\'Ivoire', 'bits-accessible-forms' ) ),
            array( 'value' => 'HR', 'label' => __( 'Croatia', 'bits-accessible-forms' ) ),
            array( 'value' => 'CU', 'label' => __( 'Cuba', 'bits-accessible-forms' ) ),
            array( 'value' => 'CW', 'label' => __( 'Curaçao', 'bits-accessible-forms' ) ),
            array( 'value' => 'CY', 'label' => __( 'Cyprus', 'bits-accessible-forms' ) ),
            array( 'value' => 'CZ', 'label' => __( 'Czechia', 'bits-accessible-forms' ) ),
            array( 'value' => 'DK', 'label' => __( 'Denmark', 'bits-accessible-forms' ) ),
            array( 'value' => 'DJ', 'label' => __( 'Djibouti', 'bits-accessible-forms' ) ),
            array( 'value' => 'DM', 'label' => __( 'Dominica', 'bits-accessible-forms' ) ),
            array( 'value' => 'DO', 'label' => __( 'Dominican Republic', 'bits-accessible-forms' ) ),
            array( 'value' => 'EC', 'label' => __( 'Ecuador', 'bits-accessible-forms' ) ),
            array( 'value' => 'EG', 'label' => __( 'Egypt', 'bits-accessible-forms' ) ),
            array( 'value' => 'SV', 'label' => __( 'El Salvador', 'bits-accessible-forms' ) ),
            array( 'value' => 'GQ', 'label' => __( 'Equatorial Guinea', 'bits-accessible-forms' ) ),
            array( 'value' => 'ER', 'label' => __( 'Eritrea', 'bits-accessible-forms' ) ),
            array( 'value' => 'EE', 'label' => __( 'Estonia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SZ', 'label' => __( 'Eswatini', 'bits-accessible-forms' ) ),
            array( 'value' => 'ET', 'label' => __( 'Ethiopia', 'bits-accessible-forms' ) ),
            array( 'value' => 'FK', 'label' => __( 'Falkland Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'FO', 'label' => __( 'Faroe Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'FJ', 'label' => __( 'Fiji', 'bits-accessible-forms' ) ),
            array( 'value' => 'FI', 'label' => __( 'Finland', 'bits-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'France', 'bits-accessible-forms' ) ),
            array( 'value' => 'GF', 'label' => __( 'French Guiana', 'bits-accessible-forms' ) ),
            array( 'value' => 'PF', 'label' => __( 'French Polynesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'TF', 'label' => __( 'French Southern Territories', 'bits-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Gabon', 'bits-accessible-forms' ) ),
            array( 'value' => 'GM', 'label' => __( 'Gambia', 'bits-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Georgia', 'bits-accessible-forms' ) ),
            array( 'value' => 'DE', 'label' => __( 'Germany', 'bits-accessible-forms' ) ),
            array( 'value' => 'GH', 'label' => __( 'Ghana', 'bits-accessible-forms' ) ),
            array( 'value' => 'GI', 'label' => __( 'Gibraltar', 'bits-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Greece', 'bits-accessible-forms' ) ),
            array( 'value' => 'GL', 'label' => __( 'Greenland', 'bits-accessible-forms' ) ),
            array( 'value' => 'GD', 'label' => __( 'Grenada', 'bits-accessible-forms' ) ),
            array( 'value' => 'GP', 'label' => __( 'Guadeloupe', 'bits-accessible-forms' ) ),
            array( 'value' => 'GU', 'label' => __( 'Guam', 'bits-accessible-forms' ) ),
            array( 'value' => 'GT', 'label' => __( 'Guatemala', 'bits-accessible-forms' ) ),
            array( 'value' => 'GG', 'label' => __( 'Guernsey', 'bits-accessible-forms' ) ),
            array( 'value' => 'GN', 'label' => __( 'Guinea', 'bits-accessible-forms' ) ),
            array( 'value' => 'GW', 'label' => __( 'Guinea-Bissau', 'bits-accessible-forms' ) ),
            array( 'value' => 'GY', 'label' => __( 'Guyana', 'bits-accessible-forms' ) ),
            array( 'value' => 'HT', 'label' => __( 'Haiti', 'bits-accessible-forms' ) ),
            array( 'value' => 'HM', 'label' => __( 'Heard Island and McDonald Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'VA', 'label' => __( 'Holy See', 'bits-accessible-forms' ) ),
            array( 'value' => 'HN', 'label' => __( 'Honduras', 'bits-accessible-forms' ) ),
            array( 'value' => 'HK', 'label' => __( 'Hong Kong', 'bits-accessible-forms' ) ),
            array( 'value' => 'HU', 'label' => __( 'Hungary', 'bits-accessible-forms' ) ),
            array( 'value' => 'IS', 'label' => __( 'Iceland', 'bits-accessible-forms' ) ),
            array( 'value' => 'IN', 'label' => __( 'India', 'bits-accessible-forms' ) ),
            array( 'value' => 'ID', 'label' => __( 'Indonesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'IR', 'label' => __( 'Iran', 'bits-accessible-forms' ) ),
            array( 'value' => 'IQ', 'label' => __( 'Iraq', 'bits-accessible-forms' ) ),
            array( 'value' => 'IE', 'label' => __( 'Ireland', 'bits-accessible-forms' ) ),
            array( 'value' => 'IM', 'label' => __( 'Isle of Man', 'bits-accessible-forms' ) ),
            array( 'value' => 'IL', 'label' => __( 'Israel', 'bits-accessible-forms' ) ),
            array( 'value' => 'IT', 'label' => __( 'Italy', 'bits-accessible-forms' ) ),
            array( 'value' => 'JM', 'label' => __( 'Jamaica', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP', 'label' => __( 'Japan', 'bits-accessible-forms' ) ),
            array( 'value' => 'JE', 'label' => __( 'Jersey', 'bits-accessible-forms' ) ),
            array( 'value' => 'JO', 'label' => __( 'Jordan', 'bits-accessible-forms' ) ),
            array( 'value' => 'KZ', 'label' => __( 'Kazakhstan', 'bits-accessible-forms' ) ),
            array( 'value' => 'KE', 'label' => __( 'Kenya', 'bits-accessible-forms' ) ),
            array( 'value' => 'KI', 'label' => __( 'Kiribati', 'bits-accessible-forms' ) ),
            array( 'value' => 'KP', 'label' => __( 'Korea, North', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR', 'label' => __( 'Korea, South', 'bits-accessible-forms' ) ),
            array( 'value' => 'KW', 'label' => __( 'Kuwait', 'bits-accessible-forms' ) ),
            array( 'value' => 'KG', 'label' => __( 'Kyrgyzstan', 'bits-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Laos', 'bits-accessible-forms' ) ),
            array( 'value' => 'LV', 'label' => __( 'Latvia', 'bits-accessible-forms' ) ),
            array( 'value' => 'LB', 'label' => __( 'Lebanon', 'bits-accessible-forms' ) ),
            array( 'value' => 'LS', 'label' => __( 'Lesotho', 'bits-accessible-forms' ) ),
            array( 'value' => 'LR', 'label' => __( 'Liberia', 'bits-accessible-forms' ) ),
            array( 'value' => 'LY', 'label' => __( 'Libya', 'bits-accessible-forms' ) ),
            array( 'value' => 'LI', 'label' => __( 'Liechtenstein', 'bits-accessible-forms' ) ),
            array( 'value' => 'LT', 'label' => __( 'Lithuania', 'bits-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Luxembourg', 'bits-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Macao', 'bits-accessible-forms' ) ),
            array( 'value' => 'MG', 'label' => __( 'Madagascar', 'bits-accessible-forms' ) ),
            array( 'value' => 'MW', 'label' => __( 'Malawi', 'bits-accessible-forms' ) ),
            array( 'value' => 'MY', 'label' => __( 'Malaysia', 'bits-accessible-forms' ) ),
            array( 'value' => 'MV', 'label' => __( 'Maldives', 'bits-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Mali', 'bits-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Malta', 'bits-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Marshall Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'MQ', 'label' => __( 'Martinique', 'bits-accessible-forms' ) ),
            array( 'value' => 'MR', 'label' => __( 'Mauritania', 'bits-accessible-forms' ) ),
            array( 'value' => 'MU', 'label' => __( 'Mauritius', 'bits-accessible-forms' ) ),
            array( 'value' => 'YT', 'label' => __( 'Mayotte', 'bits-accessible-forms' ) ),
            array( 'value' => 'MX', 'label' => __( 'Mexico', 'bits-accessible-forms' ) ),
            array( 'value' => 'FM', 'label' => __( 'Micronesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Moldova', 'bits-accessible-forms' ) ),
            array( 'value' => 'MC', 'label' => __( 'Monaco', 'bits-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Mongolia', 'bits-accessible-forms' ) ),
            array( 'value' => 'ME', 'label' => __( 'Montenegro', 'bits-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Montserrat', 'bits-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Morocco', 'bits-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Mozambique', 'bits-accessible-forms' ) ),
            array( 'value' => 'MM', 'label' => __( 'Myanmar', 'bits-accessible-forms' ) ),
            array( 'value' => 'NA', 'label' => __( 'Namibia', 'bits-accessible-forms' ) ),
            array( 'value' => 'NR', 'label' => __( 'Nauru', 'bits-accessible-forms' ) ),
            array( 'value' => 'NP', 'label' => __( 'Nepal', 'bits-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Netherlands', 'bits-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'New Caledonia', 'bits-accessible-forms' ) ),
            array( 'value' => 'NZ', 'label' => __( 'New Zealand', 'bits-accessible-forms' ) ),
            array( 'value' => 'NI', 'label' => __( 'Nicaragua', 'bits-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Niger', 'bits-accessible-forms' ) ),
            array( 'value' => 'NG', 'label' => __( 'Nigeria', 'bits-accessible-forms' ) ),
            array( 'value' => 'NU', 'label' => __( 'Niue', 'bits-accessible-forms' ) ),
            array( 'value' => 'NF', 'label' => __( 'Norfolk Island', 'bits-accessible-forms' ) ),
            array( 'value' => 'MK', 'label' => __( 'North Macedonia', 'bits-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Northern Mariana Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'NO', 'label' => __( 'Norway', 'bits-accessible-forms' ) ),
            array( 'value' => 'OM', 'label' => __( 'Oman', 'bits-accessible-forms' ) ),
            array( 'value' => 'PK', 'label' => __( 'Pakistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'PW', 'label' => __( 'Palau', 'bits-accessible-forms' ) ),
            array( 'value' => 'PS', 'label' => __( 'Palestine', 'bits-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Panama', 'bits-accessible-forms' ) ),
            array( 'value' => 'PG', 'label' => __( 'Papua New Guinea', 'bits-accessible-forms' ) ),
            array( 'value' => 'PY', 'label' => __( 'Paraguay', 'bits-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Peru', 'bits-accessible-forms' ) ),
            array( 'value' => 'PH', 'label' => __( 'Philippines', 'bits-accessible-forms' ) ),
            array( 'value' => 'PN', 'label' => __( 'Pitcairn', 'bits-accessible-forms' ) ),
            array( 'value' => 'PL', 'label' => __( 'Poland', 'bits-accessible-forms' ) ),
            array( 'value' => 'PT', 'label' => __( 'Portugal', 'bits-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Puerto Rico', 'bits-accessible-forms' ) ),
            array( 'value' => 'QA', 'label' => __( 'Qatar', 'bits-accessible-forms' ) ),
            array( 'value' => 'RE', 'label' => __( 'Réunion', 'bits-accessible-forms' ) ),
            array( 'value' => 'RO', 'label' => __( 'Romania', 'bits-accessible-forms' ) ),
            array( 'value' => 'RU', 'label' => __( 'Russian Federation', 'bits-accessible-forms' ) ),
            array( 'value' => 'RW', 'label' => __( 'Rwanda', 'bits-accessible-forms' ) ),
            array( 'value' => 'BL', 'label' => __( 'Saint Barthélemy', 'bits-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Saint Helena', 'bits-accessible-forms' ) ),
            array( 'value' => 'KN', 'label' => __( 'Saint Kitts and Nevis', 'bits-accessible-forms' ) ),
            array( 'value' => 'LC', 'label' => __( 'Saint Lucia', 'bits-accessible-forms' ) ),
            array( 'value' => 'MF', 'label' => __( 'Saint Martin (French)', 'bits-accessible-forms' ) ),
            array( 'value' => 'PM', 'label' => __( 'Saint Pierre and Miquelon', 'bits-accessible-forms' ) ),
            array( 'value' => 'VC', 'label' => __( 'Saint Vincent and the Grenadines', 'bits-accessible-forms' ) ),
            array( 'value' => 'WS', 'label' => __( 'Samoa', 'bits-accessible-forms' ) ),
            array( 'value' => 'SM', 'label' => __( 'San Marino', 'bits-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Sao Tome and Principe', 'bits-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'Saudi Arabia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Senegal', 'bits-accessible-forms' ) ),
            array( 'value' => 'RS', 'label' => __( 'Serbia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Seychelles', 'bits-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Sierra Leone', 'bits-accessible-forms' ) ),
            array( 'value' => 'SG', 'label' => __( 'Singapore', 'bits-accessible-forms' ) ),
            array( 'value' => 'SX', 'label' => __( 'Sint Maarten (Dutch)', 'bits-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Slovakia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SI', 'label' => __( 'Slovenia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SB', 'label' => __( 'Solomon Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Somalia', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZA', 'label' => __( 'South Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'GS', 'label' => __( 'South Georgia and the South Sandwich Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'SS', 'label' => __( 'South Sudan', 'bits-accessible-forms' ) ),
            array( 'value' => 'ES', 'label' => __( 'Spain', 'bits-accessible-forms' ) ),
            array( 'value' => 'LK', 'label' => __( 'Sri Lanka', 'bits-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'Sudan', 'bits-accessible-forms' ) ),
            array( 'value' => 'SR', 'label' => __( 'Suriname', 'bits-accessible-forms' ) ),
            array( 'value' => 'SJ', 'label' => __( 'Svalbard and Jan Mayen', 'bits-accessible-forms' ) ),
            array( 'value' => 'SE', 'label' => __( 'Sweden', 'bits-accessible-forms' ) ),
            array( 'value' => 'CH', 'label' => __( 'Switzerland', 'bits-accessible-forms' ) ),
            array( 'value' => 'SY', 'label' => __( 'Syrian Arab Republic', 'bits-accessible-forms' ) ),
            array( 'value' => 'TW', 'label' => __( 'Taiwan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TJ', 'label' => __( 'Tajikistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TZ', 'label' => __( 'Tanzania', 'bits-accessible-forms' ) ),
            array( 'value' => 'TH', 'label' => __( 'Thailand', 'bits-accessible-forms' ) ),
            array( 'value' => 'TL', 'label' => __( 'Timor-Leste', 'bits-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Togo', 'bits-accessible-forms' ) ),
            array( 'value' => 'TK', 'label' => __( 'Tokelau', 'bits-accessible-forms' ) ),
            array( 'value' => 'TO', 'label' => __( 'Tonga', 'bits-accessible-forms' ) ),
            array( 'value' => 'TT', 'label' => __( 'Trinidad and Tobago', 'bits-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tunisia', 'bits-accessible-forms' ) ),
            array( 'value' => 'TR', 'label' => __( 'Turkey', 'bits-accessible-forms' ) ),
            array( 'value' => 'TM', 'label' => __( 'Turkmenistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TC', 'label' => __( 'Turks and Caicos Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'TV', 'label' => __( 'Tuvalu', 'bits-accessible-forms' ) ),
            array( 'value' => 'UG', 'label' => __( 'Uganda', 'bits-accessible-forms' ) ),
            array( 'value' => 'UA', 'label' => __( 'Ukraine', 'bits-accessible-forms' ) ),
            array( 'value' => 'AE', 'label' => __( 'United Arab Emirates', 'bits-accessible-forms' ) ),
            array( 'value' => 'GB', 'label' => __( 'United Kingdom', 'bits-accessible-forms' ) ),
            array( 'value' => 'US', 'label' => __( 'United States', 'bits-accessible-forms' ) ),
            array( 'value' => 'UM', 'label' => __( 'United States Minor Outlying Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'UY', 'label' => __( 'Uruguay', 'bits-accessible-forms' ) ),
            array( 'value' => 'UZ', 'label' => __( 'Uzbekistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'VU', 'label' => __( 'Vanuatu', 'bits-accessible-forms' ) ),
            array( 'value' => 'VE', 'label' => __( 'Venezuela', 'bits-accessible-forms' ) ),
            array( 'value' => 'VN', 'label' => __( 'Vietnam', 'bits-accessible-forms' ) ),
            array( 'value' => 'VG', 'label' => __( 'Virgin Islands (British)', 'bits-accessible-forms' ) ),
            array( 'value' => 'VI', 'label' => __( 'Virgin Islands (U.S.)', 'bits-accessible-forms' ) ),
            array( 'value' => 'WF', 'label' => __( 'Wallis and Futuna', 'bits-accessible-forms' ) ),
            array( 'value' => 'EH', 'label' => __( 'Western Sahara', 'bits-accessible-forms' ) ),
            array( 'value' => 'YE', 'label' => __( 'Yemen', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZM', 'label' => __( 'Zambia', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZW', 'label' => __( 'Zimbabwe', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'AL', 'label' => __( 'Alabama', 'bits-accessible-forms' ) ),
            array( 'value' => 'AK', 'label' => __( 'Alaska', 'bits-accessible-forms' ) ),
            array( 'value' => 'AZ', 'label' => __( 'Arizona', 'bits-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Arkansas', 'bits-accessible-forms' ) ),
            array( 'value' => 'CA', 'label' => __( 'California', 'bits-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Colorado', 'bits-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Connecticut', 'bits-accessible-forms' ) ),
            array( 'value' => 'DE', 'label' => __( 'Delaware', 'bits-accessible-forms' ) ),
            array( 'value' => 'FL', 'label' => __( 'Florida', 'bits-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Georgia', 'bits-accessible-forms' ) ),
            array( 'value' => 'HI', 'label' => __( 'Hawaii', 'bits-accessible-forms' ) ),
            array( 'value' => 'ID', 'label' => __( 'Idaho', 'bits-accessible-forms' ) ),
            array( 'value' => 'IL', 'label' => __( 'Illinois', 'bits-accessible-forms' ) ),
            array( 'value' => 'IN', 'label' => __( 'Indiana', 'bits-accessible-forms' ) ),
            array( 'value' => 'IA', 'label' => __( 'Iowa', 'bits-accessible-forms' ) ),
            array( 'value' => 'KS', 'label' => __( 'Kansas', 'bits-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Kentucky', 'bits-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Louisiana', 'bits-accessible-forms' ) ),
            array( 'value' => 'ME', 'label' => __( 'Maine', 'bits-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Maryland', 'bits-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Massachusetts', 'bits-accessible-forms' ) ),
            array( 'value' => 'MI', 'label' => __( 'Michigan', 'bits-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Minnesota', 'bits-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Mississippi', 'bits-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Missouri', 'bits-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Montana', 'bits-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Nebraska', 'bits-accessible-forms' ) ),
            array( 'value' => 'NV', 'label' => __( 'Nevada', 'bits-accessible-forms' ) ),
            array( 'value' => 'NH', 'label' => __( 'New Hampshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'NJ', 'label' => __( 'New Jersey', 'bits-accessible-forms' ) ),
            array( 'value' => 'NM', 'label' => __( 'New Mexico', 'bits-accessible-forms' ) ),
            array( 'value' => 'NY', 'label' => __( 'New York', 'bits-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'North Carolina', 'bits-accessible-forms' ) ),
            array( 'value' => 'ND', 'label' => __( 'North Dakota', 'bits-accessible-forms' ) ),
            array( 'value' => 'OH', 'label' => __( 'Ohio', 'bits-accessible-forms' ) ),
            array( 'value' => 'OK', 'label' => __( 'Oklahoma', 'bits-accessible-forms' ) ),
            array( 'value' => 'OR', 'label' => __( 'Oregon', 'bits-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Pennsylvania', 'bits-accessible-forms' ) ),
            array( 'value' => 'RI', 'label' => __( 'Rhode Island', 'bits-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'South Carolina', 'bits-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'South Dakota', 'bits-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tennessee', 'bits-accessible-forms' ) ),
            array( 'value' => 'TX', 'label' => __( 'Texas', 'bits-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Utah', 'bits-accessible-forms' ) ),
            array( 'value' => 'VT', 'label' => __( 'Vermont', 'bits-accessible-forms' ) ),
            array( 'value' => 'VA', 'label' => __( 'Virginia', 'bits-accessible-forms' ) ),
            array( 'value' => 'WA', 'label' => __( 'Washington', 'bits-accessible-forms' ) ),
            array( 'value' => 'WV', 'label' => __( 'West Virginia', 'bits-accessible-forms' ) ),
            array( 'value' => 'WI', 'label' => __( 'Wisconsin', 'bits-accessible-forms' ) ),
            array( 'value' => 'WY', 'label' => __( 'Wyoming', 'bits-accessible-forms' ) ),
            array( 'value' => 'DC', 'label' => __( 'District of Columbia', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'AS', 'label' => __( 'American Samoa', 'bits-accessible-forms' ) ),
            array( 'value' => 'GU', 'label' => __( 'Guam', 'bits-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Northern Mariana Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Puerto Rico', 'bits-accessible-forms' ) ),
            array( 'value' => 'VI', 'label' => __( 'U.S. Virgin Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'UM', 'label' => __( 'U.S. Minor Outlying Islands', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'AB', 'label' => __( 'Alberta', 'bits-accessible-forms' ) ),
            array( 'value' => 'BC', 'label' => __( 'British Columbia', 'bits-accessible-forms' ) ),
            array( 'value' => 'MB', 'label' => __( 'Manitoba', 'bits-accessible-forms' ) ),
            array( 'value' => 'NB', 'label' => __( 'New Brunswick', 'bits-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Newfoundland and Labrador', 'bits-accessible-forms' ) ),
            array( 'value' => 'NS', 'label' => __( 'Nova Scotia', 'bits-accessible-forms' ) ),
            array( 'value' => 'NT', 'label' => __( 'Northwest Territories', 'bits-accessible-forms' ) ),
            array( 'value' => 'NU', 'label' => __( 'Nunavut', 'bits-accessible-forms' ) ),
            array( 'value' => 'ON', 'label' => __( 'Ontario', 'bits-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Prince Edward Island', 'bits-accessible-forms' ) ),
            array( 'value' => 'QC', 'label' => __( 'Quebec', 'bits-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Saskatchewan', 'bits-accessible-forms' ) ),
            array( 'value' => 'YT', 'label' => __( 'Yukon', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'BDF', 'label' => __( 'Bedfordshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'BRK', 'label' => __( 'Berkshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'BKM', 'label' => __( 'Buckinghamshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Cambridgeshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHS', 'label' => __( 'Cheshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'CON', 'label' => __( 'Cornwall', 'bits-accessible-forms' ) ),
            array( 'value' => 'CMA', 'label' => __( 'Cumbria', 'bits-accessible-forms' ) ),
            array( 'value' => 'DBY', 'label' => __( 'Derbyshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'DEV', 'label' => __( 'Devon', 'bits-accessible-forms' ) ),
            array( 'value' => 'DOR', 'label' => __( 'Dorset', 'bits-accessible-forms' ) ),
            array( 'value' => 'DUR', 'label' => __( 'Durham', 'bits-accessible-forms' ) ),
            array( 'value' => 'ESS', 'label' => __( 'Essex', 'bits-accessible-forms' ) ),
            array( 'value' => 'GLS', 'label' => __( 'Gloucestershire', 'bits-accessible-forms' ) ),
            array( 'value' => 'LDN', 'label' => __( 'Greater London', 'bits-accessible-forms' ) ),
            array( 'value' => 'GTM', 'label' => __( 'Greater Manchester', 'bits-accessible-forms' ) ),
            array( 'value' => 'HAM', 'label' => __( 'Hampshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'HEF', 'label' => __( 'Herefordshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'HRT', 'label' => __( 'Hertfordshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'KEN', 'label' => __( 'Kent', 'bits-accessible-forms' ) ),
            array( 'value' => 'LAN', 'label' => __( 'Lancashire', 'bits-accessible-forms' ) ),
            array( 'value' => 'LEC', 'label' => __( 'Leicestershire', 'bits-accessible-forms' ) ),
            array( 'value' => 'LIN', 'label' => __( 'Lincolnshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'MSY', 'label' => __( 'Merseyside', 'bits-accessible-forms' ) ),
            array( 'value' => 'NFK', 'label' => __( 'Norfolk', 'bits-accessible-forms' ) ),
            array( 'value' => 'NTH', 'label' => __( 'Northamptonshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'NBL', 'label' => __( 'Northumberland', 'bits-accessible-forms' ) ),
            array( 'value' => 'NTT', 'label' => __( 'Nottinghamshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'OXF', 'label' => __( 'Oxfordshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'SHR', 'label' => __( 'Shropshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'SOM', 'label' => __( 'Somerset', 'bits-accessible-forms' ) ),
            array( 'value' => 'SYK', 'label' => __( 'South Yorkshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'STS', 'label' => __( 'Staffordshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'SFK', 'label' => __( 'Suffolk', 'bits-accessible-forms' ) ),
            array( 'value' => 'SRY', 'label' => __( 'Surrey', 'bits-accessible-forms' ) ),
            array( 'value' => 'TWR', 'label' => __( 'Tyne and Wear', 'bits-accessible-forms' ) ),
            array( 'value' => 'WAR', 'label' => __( 'Warwickshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'WMD', 'label' => __( 'West Midlands', 'bits-accessible-forms' ) ),
            array( 'value' => 'WSX', 'label' => __( 'West Sussex', 'bits-accessible-forms' ) ),
            array( 'value' => 'WYK', 'label' => __( 'West Yorkshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'WIL', 'label' => __( 'Wiltshire', 'bits-accessible-forms' ) ),
            array( 'value' => 'WOR', 'label' => __( 'Worcestershire', 'bits-accessible-forms' ) ),
            // Scotland
            array( 'value' => 'SCT', 'label' => __( 'Scotland', 'bits-accessible-forms' ) ),
            // Wales
            array( 'value' => 'WLS', 'label' => __( 'Wales', 'bits-accessible-forms' ) ),
            // Northern Ireland
            array( 'value' => 'NIR', 'label' => __( 'Northern Ireland', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Australian states and territories
     *
     * @return array Australian states.
     */
    public static function get_au_states() {
        return array(
            array( 'value' => 'ACT', 'label' => __( 'Australian Capital Territory', 'bits-accessible-forms' ) ),
            array( 'value' => 'NSW', 'label' => __( 'New South Wales', 'bits-accessible-forms' ) ),
            array( 'value' => 'NT', 'label' => __( 'Northern Territory', 'bits-accessible-forms' ) ),
            array( 'value' => 'QLD', 'label' => __( 'Queensland', 'bits-accessible-forms' ) ),
            array( 'value' => 'SA', 'label' => __( 'South Australia', 'bits-accessible-forms' ) ),
            array( 'value' => 'TAS', 'label' => __( 'Tasmania', 'bits-accessible-forms' ) ),
            array( 'value' => 'VIC', 'label' => __( 'Victoria', 'bits-accessible-forms' ) ),
            array( 'value' => 'WA', 'label' => __( 'Western Australia', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get German states (Bundesländer)
     *
     * @return array German states.
     */
    public static function get_de_states() {
        return array(
            array( 'value' => 'BW', 'label' => __( 'Baden-Württemberg', 'bits-accessible-forms' ) ),
            array( 'value' => 'BY', 'label' => __( 'Bavaria', 'bits-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Berlin', 'bits-accessible-forms' ) ),
            array( 'value' => 'BB', 'label' => __( 'Brandenburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'HB', 'label' => __( 'Bremen', 'bits-accessible-forms' ) ),
            array( 'value' => 'HH', 'label' => __( 'Hamburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'HE', 'label' => __( 'Hesse', 'bits-accessible-forms' ) ),
            array( 'value' => 'MV', 'label' => __( 'Mecklenburg-Vorpommern', 'bits-accessible-forms' ) ),
            array( 'value' => 'NI', 'label' => __( 'Lower Saxony', 'bits-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'North Rhine-Westphalia', 'bits-accessible-forms' ) ),
            array( 'value' => 'RP', 'label' => __( 'Rhineland-Palatinate', 'bits-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Saarland', 'bits-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Saxony', 'bits-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Saxony-Anhalt', 'bits-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Schleswig-Holstein', 'bits-accessible-forms' ) ),
            array( 'value' => 'TH', 'label' => __( 'Thuringia', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get French regions
     *
     * @return array French regions.
     */
    public static function get_fr_regions() {
        return array(
            array( 'value' => 'ARA', 'label' => __( 'Auvergne-Rhône-Alpes', 'bits-accessible-forms' ) ),
            array( 'value' => 'BFC', 'label' => __( 'Bourgogne-Franche-Comté', 'bits-accessible-forms' ) ),
            array( 'value' => 'BRE', 'label' => __( 'Brittany', 'bits-accessible-forms' ) ),
            array( 'value' => 'CVL', 'label' => __( 'Centre-Val de Loire', 'bits-accessible-forms' ) ),
            array( 'value' => 'COR', 'label' => __( 'Corsica', 'bits-accessible-forms' ) ),
            array( 'value' => 'GES', 'label' => __( 'Grand Est', 'bits-accessible-forms' ) ),
            array( 'value' => 'HDF', 'label' => __( 'Hauts-de-France', 'bits-accessible-forms' ) ),
            array( 'value' => 'IDF', 'label' => __( 'Île-de-France', 'bits-accessible-forms' ) ),
            array( 'value' => 'NOR', 'label' => __( 'Normandy', 'bits-accessible-forms' ) ),
            array( 'value' => 'NAQ', 'label' => __( 'Nouvelle-Aquitaine', 'bits-accessible-forms' ) ),
            array( 'value' => 'OCC', 'label' => __( 'Occitanie', 'bits-accessible-forms' ) ),
            array( 'value' => 'PDL', 'label' => __( 'Pays de la Loire', 'bits-accessible-forms' ) ),
            array( 'value' => 'PAC', 'label' => __( 'Provence-Alpes-Côte d\'Azur', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Italian regions
     *
     * @return array Italian regions.
     */
    public static function get_it_regions() {
        return array(
            array( 'value' => 'ABR', 'label' => __( 'Abruzzo', 'bits-accessible-forms' ) ),
            array( 'value' => 'VDA', 'label' => __( 'Aosta Valley', 'bits-accessible-forms' ) ),
            array( 'value' => 'PUG', 'label' => __( 'Apulia', 'bits-accessible-forms' ) ),
            array( 'value' => 'BAS', 'label' => __( 'Basilicata', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAL', 'label' => __( 'Calabria', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Campania', 'bits-accessible-forms' ) ),
            array( 'value' => 'EMR', 'label' => __( 'Emilia-Romagna', 'bits-accessible-forms' ) ),
            array( 'value' => 'FVG', 'label' => __( 'Friuli Venezia Giulia', 'bits-accessible-forms' ) ),
            array( 'value' => 'LAZ', 'label' => __( 'Lazio', 'bits-accessible-forms' ) ),
            array( 'value' => 'LIG', 'label' => __( 'Liguria', 'bits-accessible-forms' ) ),
            array( 'value' => 'LOM', 'label' => __( 'Lombardy', 'bits-accessible-forms' ) ),
            array( 'value' => 'MAR', 'label' => __( 'Marche', 'bits-accessible-forms' ) ),
            array( 'value' => 'MOL', 'label' => __( 'Molise', 'bits-accessible-forms' ) ),
            array( 'value' => 'PMN', 'label' => __( 'Piedmont', 'bits-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Sardinia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SIC', 'label' => __( 'Sicily', 'bits-accessible-forms' ) ),
            array( 'value' => 'TOS', 'label' => __( 'Tuscany', 'bits-accessible-forms' ) ),
            array( 'value' => 'TAA', 'label' => __( 'Trentino-Alto Adige', 'bits-accessible-forms' ) ),
            array( 'value' => 'UMB', 'label' => __( 'Umbria', 'bits-accessible-forms' ) ),
            array( 'value' => 'VEN', 'label' => __( 'Veneto', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Spanish autonomous communities
     *
     * @return array Spanish regions.
     */
    public static function get_es_regions() {
        return array(
            array( 'value' => 'AN', 'label' => __( 'Andalusia', 'bits-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Aragon', 'bits-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Asturias', 'bits-accessible-forms' ) ),
            array( 'value' => 'IB', 'label' => __( 'Balearic Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'PV', 'label' => __( 'Basque Country', 'bits-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'Canary Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'CB', 'label' => __( 'Cantabria', 'bits-accessible-forms' ) ),
            array( 'value' => 'CL', 'label' => __( 'Castile and León', 'bits-accessible-forms' ) ),
            array( 'value' => 'CM', 'label' => __( 'Castilla-La Mancha', 'bits-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Catalonia', 'bits-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Ceuta', 'bits-accessible-forms' ) ),
            array( 'value' => 'EX', 'label' => __( 'Extremadura', 'bits-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Galicia', 'bits-accessible-forms' ) ),
            array( 'value' => 'RI', 'label' => __( 'La Rioja', 'bits-accessible-forms' ) ),
            array( 'value' => 'MD', 'label' => __( 'Madrid', 'bits-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Melilla', 'bits-accessible-forms' ) ),
            array( 'value' => 'MC', 'label' => __( 'Murcia', 'bits-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'Navarre', 'bits-accessible-forms' ) ),
            array( 'value' => 'VC', 'label' => __( 'Valencia', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Dutch provinces
     *
     * @return array Dutch provinces.
     */
    public static function get_nl_provinces() {
        return array(
            array( 'value' => 'DR', 'label' => __( 'Drenthe', 'bits-accessible-forms' ) ),
            array( 'value' => 'FL', 'label' => __( 'Flevoland', 'bits-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'Friesland', 'bits-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Gelderland', 'bits-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Groningen', 'bits-accessible-forms' ) ),
            array( 'value' => 'LI', 'label' => __( 'Limburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'NB', 'label' => __( 'North Brabant', 'bits-accessible-forms' ) ),
            array( 'value' => 'NH', 'label' => __( 'North Holland', 'bits-accessible-forms' ) ),
            array( 'value' => 'OV', 'label' => __( 'Overijssel', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZH', 'label' => __( 'South Holland', 'bits-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Utrecht', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZE', 'label' => __( 'Zeeland', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Belgian provinces
     *
     * @return array Belgian provinces.
     */
    public static function get_be_provinces() {
        return array(
            array( 'value' => 'ANT', 'label' => __( 'Antwerp', 'bits-accessible-forms' ) ),
            array( 'value' => 'BRU', 'label' => __( 'Brussels', 'bits-accessible-forms' ) ),
            array( 'value' => 'VLG', 'label' => __( 'East Flanders', 'bits-accessible-forms' ) ),
            array( 'value' => 'VBR', 'label' => __( 'Flemish Brabant', 'bits-accessible-forms' ) ),
            array( 'value' => 'HAI', 'label' => __( 'Hainaut', 'bits-accessible-forms' ) ),
            array( 'value' => 'LIE', 'label' => __( 'Liège', 'bits-accessible-forms' ) ),
            array( 'value' => 'LIM', 'label' => __( 'Limburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'LUX', 'label' => __( 'Luxembourg', 'bits-accessible-forms' ) ),
            array( 'value' => 'NAM', 'label' => __( 'Namur', 'bits-accessible-forms' ) ),
            array( 'value' => 'BRW', 'label' => __( 'Walloon Brabant', 'bits-accessible-forms' ) ),
            array( 'value' => 'WVL', 'label' => __( 'West Flanders', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Swiss cantons
     *
     * @return array Swiss cantons.
     */
    public static function get_ch_cantons() {
        return array(
            array( 'value' => 'AG', 'label' => __( 'Aargau', 'bits-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Appenzell Ausserrhoden', 'bits-accessible-forms' ) ),
            array( 'value' => 'AI', 'label' => __( 'Appenzell Innerrhoden', 'bits-accessible-forms' ) ),
            array( 'value' => 'BL', 'label' => __( 'Basel-Landschaft', 'bits-accessible-forms' ) ),
            array( 'value' => 'BS', 'label' => __( 'Basel-Stadt', 'bits-accessible-forms' ) ),
            array( 'value' => 'BE', 'label' => __( 'Bern', 'bits-accessible-forms' ) ),
            array( 'value' => 'FR', 'label' => __( 'Fribourg', 'bits-accessible-forms' ) ),
            array( 'value' => 'GE', 'label' => __( 'Geneva', 'bits-accessible-forms' ) ),
            array( 'value' => 'GL', 'label' => __( 'Glarus', 'bits-accessible-forms' ) ),
            array( 'value' => 'GR', 'label' => __( 'Graubünden', 'bits-accessible-forms' ) ),
            array( 'value' => 'JU', 'label' => __( 'Jura', 'bits-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Lucerne', 'bits-accessible-forms' ) ),
            array( 'value' => 'NE', 'label' => __( 'Neuchâtel', 'bits-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'Nidwalden', 'bits-accessible-forms' ) ),
            array( 'value' => 'OW', 'label' => __( 'Obwalden', 'bits-accessible-forms' ) ),
            array( 'value' => 'SG', 'label' => __( 'St. Gallen', 'bits-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Schaffhausen', 'bits-accessible-forms' ) ),
            array( 'value' => 'SZ', 'label' => __( 'Schwyz', 'bits-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Solothurn', 'bits-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Thurgau', 'bits-accessible-forms' ) ),
            array( 'value' => 'TI', 'label' => __( 'Ticino', 'bits-accessible-forms' ) ),
            array( 'value' => 'UR', 'label' => __( 'Uri', 'bits-accessible-forms' ) ),
            array( 'value' => 'VS', 'label' => __( 'Valais', 'bits-accessible-forms' ) ),
            array( 'value' => 'VD', 'label' => __( 'Vaud', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZG', 'label' => __( 'Zug', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZH', 'label' => __( 'Zürich', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Austrian states
     *
     * @return array Austrian states.
     */
    public static function get_at_states() {
        return array(
            array( 'value' => 'B', 'label' => __( 'Burgenland', 'bits-accessible-forms' ) ),
            array( 'value' => 'K', 'label' => __( 'Carinthia', 'bits-accessible-forms' ) ),
            array( 'value' => 'NO', 'label' => __( 'Lower Austria', 'bits-accessible-forms' ) ),
            array( 'value' => 'OO', 'label' => __( 'Upper Austria', 'bits-accessible-forms' ) ),
            array( 'value' => 'S', 'label' => __( 'Salzburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'ST', 'label' => __( 'Styria', 'bits-accessible-forms' ) ),
            array( 'value' => 'T', 'label' => __( 'Tyrol', 'bits-accessible-forms' ) ),
            array( 'value' => 'V', 'label' => __( 'Vorarlberg', 'bits-accessible-forms' ) ),
            array( 'value' => 'W', 'label' => __( 'Vienna', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Mexican states
     *
     * @return array Mexican states.
     */
    public static function get_mx_states() {
        return array(
            array( 'value' => 'AGU', 'label' => __( 'Aguascalientes', 'bits-accessible-forms' ) ),
            array( 'value' => 'BCN', 'label' => __( 'Baja California', 'bits-accessible-forms' ) ),
            array( 'value' => 'BCS', 'label' => __( 'Baja California Sur', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAM', 'label' => __( 'Campeche', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHP', 'label' => __( 'Chiapas', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHH', 'label' => __( 'Chihuahua', 'bits-accessible-forms' ) ),
            array( 'value' => 'CMX', 'label' => __( 'Mexico City', 'bits-accessible-forms' ) ),
            array( 'value' => 'COA', 'label' => __( 'Coahuila', 'bits-accessible-forms' ) ),
            array( 'value' => 'COL', 'label' => __( 'Colima', 'bits-accessible-forms' ) ),
            array( 'value' => 'DUR', 'label' => __( 'Durango', 'bits-accessible-forms' ) ),
            array( 'value' => 'GUA', 'label' => __( 'Guanajuato', 'bits-accessible-forms' ) ),
            array( 'value' => 'GRO', 'label' => __( 'Guerrero', 'bits-accessible-forms' ) ),
            array( 'value' => 'HID', 'label' => __( 'Hidalgo', 'bits-accessible-forms' ) ),
            array( 'value' => 'JAL', 'label' => __( 'Jalisco', 'bits-accessible-forms' ) ),
            array( 'value' => 'MEX', 'label' => __( 'México', 'bits-accessible-forms' ) ),
            array( 'value' => 'MIC', 'label' => __( 'Michoacán', 'bits-accessible-forms' ) ),
            array( 'value' => 'MOR', 'label' => __( 'Morelos', 'bits-accessible-forms' ) ),
            array( 'value' => 'NAY', 'label' => __( 'Nayarit', 'bits-accessible-forms' ) ),
            array( 'value' => 'NLE', 'label' => __( 'Nuevo León', 'bits-accessible-forms' ) ),
            array( 'value' => 'OAX', 'label' => __( 'Oaxaca', 'bits-accessible-forms' ) ),
            array( 'value' => 'PUE', 'label' => __( 'Puebla', 'bits-accessible-forms' ) ),
            array( 'value' => 'QUE', 'label' => __( 'Querétaro', 'bits-accessible-forms' ) ),
            array( 'value' => 'ROO', 'label' => __( 'Quintana Roo', 'bits-accessible-forms' ) ),
            array( 'value' => 'SLP', 'label' => __( 'San Luis Potosí', 'bits-accessible-forms' ) ),
            array( 'value' => 'SIN', 'label' => __( 'Sinaloa', 'bits-accessible-forms' ) ),
            array( 'value' => 'SON', 'label' => __( 'Sonora', 'bits-accessible-forms' ) ),
            array( 'value' => 'TAB', 'label' => __( 'Tabasco', 'bits-accessible-forms' ) ),
            array( 'value' => 'TAM', 'label' => __( 'Tamaulipas', 'bits-accessible-forms' ) ),
            array( 'value' => 'TLA', 'label' => __( 'Tlaxcala', 'bits-accessible-forms' ) ),
            array( 'value' => 'VER', 'label' => __( 'Veracruz', 'bits-accessible-forms' ) ),
            array( 'value' => 'YUC', 'label' => __( 'Yucatán', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZAC', 'label' => __( 'Zacatecas', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Brazilian states
     *
     * @return array Brazilian states.
     */
    public static function get_br_states() {
        return array(
            array( 'value' => 'AC', 'label' => __( 'Acre', 'bits-accessible-forms' ) ),
            array( 'value' => 'AL', 'label' => __( 'Alagoas', 'bits-accessible-forms' ) ),
            array( 'value' => 'AP', 'label' => __( 'Amapá', 'bits-accessible-forms' ) ),
            array( 'value' => 'AM', 'label' => __( 'Amazonas', 'bits-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bahia', 'bits-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Ceará', 'bits-accessible-forms' ) ),
            array( 'value' => 'DF', 'label' => __( 'Distrito Federal', 'bits-accessible-forms' ) ),
            array( 'value' => 'ES', 'label' => __( 'Espírito Santo', 'bits-accessible-forms' ) ),
            array( 'value' => 'GO', 'label' => __( 'Goiás', 'bits-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Maranhão', 'bits-accessible-forms' ) ),
            array( 'value' => 'MT', 'label' => __( 'Mato Grosso', 'bits-accessible-forms' ) ),
            array( 'value' => 'MS', 'label' => __( 'Mato Grosso do Sul', 'bits-accessible-forms' ) ),
            array( 'value' => 'MG', 'label' => __( 'Minas Gerais', 'bits-accessible-forms' ) ),
            array( 'value' => 'PA', 'label' => __( 'Pará', 'bits-accessible-forms' ) ),
            array( 'value' => 'PB', 'label' => __( 'Paraíba', 'bits-accessible-forms' ) ),
            array( 'value' => 'PR', 'label' => __( 'Paraná', 'bits-accessible-forms' ) ),
            array( 'value' => 'PE', 'label' => __( 'Pernambuco', 'bits-accessible-forms' ) ),
            array( 'value' => 'PI', 'label' => __( 'Piauí', 'bits-accessible-forms' ) ),
            array( 'value' => 'RJ', 'label' => __( 'Rio de Janeiro', 'bits-accessible-forms' ) ),
            array( 'value' => 'RN', 'label' => __( 'Rio Grande do Norte', 'bits-accessible-forms' ) ),
            array( 'value' => 'RS', 'label' => __( 'Rio Grande do Sul', 'bits-accessible-forms' ) ),
            array( 'value' => 'RO', 'label' => __( 'Rondônia', 'bits-accessible-forms' ) ),
            array( 'value' => 'RR', 'label' => __( 'Roraima', 'bits-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Santa Catarina', 'bits-accessible-forms' ) ),
            array( 'value' => 'SP', 'label' => __( 'São Paulo', 'bits-accessible-forms' ) ),
            array( 'value' => 'SE', 'label' => __( 'Sergipe', 'bits-accessible-forms' ) ),
            array( 'value' => 'TO', 'label' => __( 'Tocantins', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Argentine provinces
     *
     * @return array Argentine provinces.
     */
    public static function get_ar_provinces() {
        return array(
            array( 'value' => 'B', 'label' => __( 'Buenos Aires', 'bits-accessible-forms' ) ),
            array( 'value' => 'C', 'label' => __( 'Ciudad Autónoma de Buenos Aires', 'bits-accessible-forms' ) ),
            array( 'value' => 'K', 'label' => __( 'Catamarca', 'bits-accessible-forms' ) ),
            array( 'value' => 'H', 'label' => __( 'Chaco', 'bits-accessible-forms' ) ),
            array( 'value' => 'U', 'label' => __( 'Chubut', 'bits-accessible-forms' ) ),
            array( 'value' => 'X', 'label' => __( 'Córdoba', 'bits-accessible-forms' ) ),
            array( 'value' => 'W', 'label' => __( 'Corrientes', 'bits-accessible-forms' ) ),
            array( 'value' => 'E', 'label' => __( 'Entre Ríos', 'bits-accessible-forms' ) ),
            array( 'value' => 'P', 'label' => __( 'Formosa', 'bits-accessible-forms' ) ),
            array( 'value' => 'Y', 'label' => __( 'Jujuy', 'bits-accessible-forms' ) ),
            array( 'value' => 'L', 'label' => __( 'La Pampa', 'bits-accessible-forms' ) ),
            array( 'value' => 'F', 'label' => __( 'La Rioja', 'bits-accessible-forms' ) ),
            array( 'value' => 'M', 'label' => __( 'Mendoza', 'bits-accessible-forms' ) ),
            array( 'value' => 'N', 'label' => __( 'Misiones', 'bits-accessible-forms' ) ),
            array( 'value' => 'Q', 'label' => __( 'Neuquén', 'bits-accessible-forms' ) ),
            array( 'value' => 'R', 'label' => __( 'Río Negro', 'bits-accessible-forms' ) ),
            array( 'value' => 'A', 'label' => __( 'Salta', 'bits-accessible-forms' ) ),
            array( 'value' => 'J', 'label' => __( 'San Juan', 'bits-accessible-forms' ) ),
            array( 'value' => 'D', 'label' => __( 'San Luis', 'bits-accessible-forms' ) ),
            array( 'value' => 'Z', 'label' => __( 'Santa Cruz', 'bits-accessible-forms' ) ),
            array( 'value' => 'S', 'label' => __( 'Santa Fe', 'bits-accessible-forms' ) ),
            array( 'value' => 'G', 'label' => __( 'Santiago del Estero', 'bits-accessible-forms' ) ),
            array( 'value' => 'V', 'label' => __( 'Tierra del Fuego', 'bits-accessible-forms' ) ),
            array( 'value' => 'T', 'label' => __( 'Tucumán', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Indian states and union territories
     *
     * @return array Indian states.
     */
    public static function get_in_states() {
        return array(
            array( 'value' => 'AN', 'label' => __( 'Andaman and Nicobar Islands', 'bits-accessible-forms' ) ),
            array( 'value' => 'AP', 'label' => __( 'Andhra Pradesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'AR', 'label' => __( 'Arunachal Pradesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'AS', 'label' => __( 'Assam', 'bits-accessible-forms' ) ),
            array( 'value' => 'BR', 'label' => __( 'Bihar', 'bits-accessible-forms' ) ),
            array( 'value' => 'CH', 'label' => __( 'Chandigarh', 'bits-accessible-forms' ) ),
            array( 'value' => 'CT', 'label' => __( 'Chhattisgarh', 'bits-accessible-forms' ) ),
            array( 'value' => 'DN', 'label' => __( 'Dadra and Nagar Haveli and Daman and Diu', 'bits-accessible-forms' ) ),
            array( 'value' => 'DL', 'label' => __( 'Delhi', 'bits-accessible-forms' ) ),
            array( 'value' => 'GA', 'label' => __( 'Goa', 'bits-accessible-forms' ) ),
            array( 'value' => 'GJ', 'label' => __( 'Gujarat', 'bits-accessible-forms' ) ),
            array( 'value' => 'HR', 'label' => __( 'Haryana', 'bits-accessible-forms' ) ),
            array( 'value' => 'HP', 'label' => __( 'Himachal Pradesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'JK', 'label' => __( 'Jammu and Kashmir', 'bits-accessible-forms' ) ),
            array( 'value' => 'JH', 'label' => __( 'Jharkhand', 'bits-accessible-forms' ) ),
            array( 'value' => 'KA', 'label' => __( 'Karnataka', 'bits-accessible-forms' ) ),
            array( 'value' => 'KL', 'label' => __( 'Kerala', 'bits-accessible-forms' ) ),
            array( 'value' => 'LA', 'label' => __( 'Ladakh', 'bits-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Lakshadweep', 'bits-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Madhya Pradesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Maharashtra', 'bits-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Manipur', 'bits-accessible-forms' ) ),
            array( 'value' => 'ML', 'label' => __( 'Meghalaya', 'bits-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Mizoram', 'bits-accessible-forms' ) ),
            array( 'value' => 'NL', 'label' => __( 'Nagaland', 'bits-accessible-forms' ) ),
            array( 'value' => 'OR', 'label' => __( 'Odisha', 'bits-accessible-forms' ) ),
            array( 'value' => 'PY', 'label' => __( 'Puducherry', 'bits-accessible-forms' ) ),
            array( 'value' => 'PB', 'label' => __( 'Punjab', 'bits-accessible-forms' ) ),
            array( 'value' => 'RJ', 'label' => __( 'Rajasthan', 'bits-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Sikkim', 'bits-accessible-forms' ) ),
            array( 'value' => 'TN', 'label' => __( 'Tamil Nadu', 'bits-accessible-forms' ) ),
            array( 'value' => 'TG', 'label' => __( 'Telangana', 'bits-accessible-forms' ) ),
            array( 'value' => 'TR', 'label' => __( 'Tripura', 'bits-accessible-forms' ) ),
            array( 'value' => 'UP', 'label' => __( 'Uttar Pradesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'UT', 'label' => __( 'Uttarakhand', 'bits-accessible-forms' ) ),
            array( 'value' => 'WB', 'label' => __( 'West Bengal', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Chinese provinces and regions
     *
     * @return array Chinese provinces.
     */
    public static function get_cn_provinces() {
        return array(
            array( 'value' => 'AH', 'label' => __( 'Anhui', 'bits-accessible-forms' ) ),
            array( 'value' => 'BJ', 'label' => __( 'Beijing', 'bits-accessible-forms' ) ),
            array( 'value' => 'CQ', 'label' => __( 'Chongqing', 'bits-accessible-forms' ) ),
            array( 'value' => 'FJ', 'label' => __( 'Fujian', 'bits-accessible-forms' ) ),
            array( 'value' => 'GS', 'label' => __( 'Gansu', 'bits-accessible-forms' ) ),
            array( 'value' => 'GD', 'label' => __( 'Guangdong', 'bits-accessible-forms' ) ),
            array( 'value' => 'GX', 'label' => __( 'Guangxi', 'bits-accessible-forms' ) ),
            array( 'value' => 'GZ', 'label' => __( 'Guizhou', 'bits-accessible-forms' ) ),
            array( 'value' => 'HI', 'label' => __( 'Hainan', 'bits-accessible-forms' ) ),
            array( 'value' => 'HE', 'label' => __( 'Hebei', 'bits-accessible-forms' ) ),
            array( 'value' => 'HL', 'label' => __( 'Heilongjiang', 'bits-accessible-forms' ) ),
            array( 'value' => 'HA', 'label' => __( 'Henan', 'bits-accessible-forms' ) ),
            array( 'value' => 'HK', 'label' => __( 'Hong Kong', 'bits-accessible-forms' ) ),
            array( 'value' => 'HB', 'label' => __( 'Hubei', 'bits-accessible-forms' ) ),
            array( 'value' => 'HN', 'label' => __( 'Hunan', 'bits-accessible-forms' ) ),
            array( 'value' => 'NM', 'label' => __( 'Inner Mongolia', 'bits-accessible-forms' ) ),
            array( 'value' => 'JS', 'label' => __( 'Jiangsu', 'bits-accessible-forms' ) ),
            array( 'value' => 'JX', 'label' => __( 'Jiangxi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JL', 'label' => __( 'Jilin', 'bits-accessible-forms' ) ),
            array( 'value' => 'LN', 'label' => __( 'Liaoning', 'bits-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Macau', 'bits-accessible-forms' ) ),
            array( 'value' => 'NX', 'label' => __( 'Ningxia', 'bits-accessible-forms' ) ),
            array( 'value' => 'QH', 'label' => __( 'Qinghai', 'bits-accessible-forms' ) ),
            array( 'value' => 'SN', 'label' => __( 'Shaanxi', 'bits-accessible-forms' ) ),
            array( 'value' => 'SD', 'label' => __( 'Shandong', 'bits-accessible-forms' ) ),
            array( 'value' => 'SH', 'label' => __( 'Shanghai', 'bits-accessible-forms' ) ),
            array( 'value' => 'SX', 'label' => __( 'Shanxi', 'bits-accessible-forms' ) ),
            array( 'value' => 'SC', 'label' => __( 'Sichuan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TW', 'label' => __( 'Taiwan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TJ', 'label' => __( 'Tianjin', 'bits-accessible-forms' ) ),
            array( 'value' => 'XZ', 'label' => __( 'Tibet', 'bits-accessible-forms' ) ),
            array( 'value' => 'XJ', 'label' => __( 'Xinjiang', 'bits-accessible-forms' ) ),
            array( 'value' => 'YN', 'label' => __( 'Yunnan', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZJ', 'label' => __( 'Zhejiang', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Japanese prefectures
     *
     * @return array Japanese prefectures.
     */
    public static function get_jp_prefectures() {
        return array(
            array( 'value' => 'JP-01', 'label' => __( 'Hokkaido', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-02', 'label' => __( 'Aomori', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-03', 'label' => __( 'Iwate', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-04', 'label' => __( 'Miyagi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-05', 'label' => __( 'Akita', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-06', 'label' => __( 'Yamagata', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-07', 'label' => __( 'Fukushima', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-08', 'label' => __( 'Ibaraki', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-09', 'label' => __( 'Tochigi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-10', 'label' => __( 'Gunma', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-11', 'label' => __( 'Saitama', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-12', 'label' => __( 'Chiba', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-13', 'label' => __( 'Tokyo', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-14', 'label' => __( 'Kanagawa', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-15', 'label' => __( 'Niigata', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-16', 'label' => __( 'Toyama', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-17', 'label' => __( 'Ishikawa', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-18', 'label' => __( 'Fukui', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-19', 'label' => __( 'Yamanashi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-20', 'label' => __( 'Nagano', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-21', 'label' => __( 'Gifu', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-22', 'label' => __( 'Shizuoka', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-23', 'label' => __( 'Aichi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-24', 'label' => __( 'Mie', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-25', 'label' => __( 'Shiga', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-26', 'label' => __( 'Kyoto', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-27', 'label' => __( 'Osaka', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-28', 'label' => __( 'Hyogo', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-29', 'label' => __( 'Nara', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-30', 'label' => __( 'Wakayama', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-31', 'label' => __( 'Tottori', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-32', 'label' => __( 'Shimane', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-33', 'label' => __( 'Okayama', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-34', 'label' => __( 'Hiroshima', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-35', 'label' => __( 'Yamaguchi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-36', 'label' => __( 'Tokushima', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-37', 'label' => __( 'Kagawa', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-38', 'label' => __( 'Ehime', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-39', 'label' => __( 'Kochi', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-40', 'label' => __( 'Fukuoka', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-41', 'label' => __( 'Saga', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-42', 'label' => __( 'Nagasaki', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-43', 'label' => __( 'Kumamoto', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-44', 'label' => __( 'Oita', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-45', 'label' => __( 'Miyazaki', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-46', 'label' => __( 'Kagoshima', 'bits-accessible-forms' ) ),
            array( 'value' => 'JP-47', 'label' => __( 'Okinawa', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get South Korean provinces
     *
     * @return array South Korean provinces.
     */
    public static function get_kr_provinces() {
        return array(
            array( 'value' => 'KR-11', 'label' => __( 'Seoul', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-26', 'label' => __( 'Busan', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-27', 'label' => __( 'Daegu', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-28', 'label' => __( 'Incheon', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-29', 'label' => __( 'Gwangju', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-30', 'label' => __( 'Daejeon', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-31', 'label' => __( 'Ulsan', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-50', 'label' => __( 'Sejong', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-41', 'label' => __( 'Gyeonggi', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-42', 'label' => __( 'Gangwon', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-43', 'label' => __( 'North Chungcheong', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-44', 'label' => __( 'South Chungcheong', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-45', 'label' => __( 'North Jeolla', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-46', 'label' => __( 'South Jeolla', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-47', 'label' => __( 'North Gyeongsang', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-48', 'label' => __( 'South Gyeongsang', 'bits-accessible-forms' ) ),
            array( 'value' => 'KR-49', 'label' => __( 'Jeju', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get New Zealand regions
     *
     * @return array New Zealand regions.
     */
    public static function get_nz_regions() {
        return array(
            array( 'value' => 'NTL', 'label' => __( 'Northland', 'bits-accessible-forms' ) ),
            array( 'value' => 'AUK', 'label' => __( 'Auckland', 'bits-accessible-forms' ) ),
            array( 'value' => 'WKO', 'label' => __( 'Waikato', 'bits-accessible-forms' ) ),
            array( 'value' => 'BOP', 'label' => __( 'Bay of Plenty', 'bits-accessible-forms' ) ),
            array( 'value' => 'GIS', 'label' => __( 'Gisborne', 'bits-accessible-forms' ) ),
            array( 'value' => 'HKB', 'label' => __( 'Hawke\'s Bay', 'bits-accessible-forms' ) ),
            array( 'value' => 'TKI', 'label' => __( 'Taranaki', 'bits-accessible-forms' ) ),
            array( 'value' => 'MWT', 'label' => __( 'Manawatū-Whanganui', 'bits-accessible-forms' ) ),
            array( 'value' => 'WGN', 'label' => __( 'Wellington', 'bits-accessible-forms' ) ),
            array( 'value' => 'TAS', 'label' => __( 'Tasman', 'bits-accessible-forms' ) ),
            array( 'value' => 'NSN', 'label' => __( 'Nelson', 'bits-accessible-forms' ) ),
            array( 'value' => 'MBH', 'label' => __( 'Marlborough', 'bits-accessible-forms' ) ),
            array( 'value' => 'WTC', 'label' => __( 'West Coast', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAN', 'label' => __( 'Canterbury', 'bits-accessible-forms' ) ),
            array( 'value' => 'OTA', 'label' => __( 'Otago', 'bits-accessible-forms' ) ),
            array( 'value' => 'STL', 'label' => __( 'Southland', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get South African provinces
     *
     * @return array South African provinces.
     */
    public static function get_za_provinces() {
        return array(
            array( 'value' => 'EC', 'label' => __( 'Eastern Cape', 'bits-accessible-forms' ) ),
            array( 'value' => 'FS', 'label' => __( 'Free State', 'bits-accessible-forms' ) ),
            array( 'value' => 'GP', 'label' => __( 'Gauteng', 'bits-accessible-forms' ) ),
            array( 'value' => 'KZN', 'label' => __( 'KwaZulu-Natal', 'bits-accessible-forms' ) ),
            array( 'value' => 'LP', 'label' => __( 'Limpopo', 'bits-accessible-forms' ) ),
            array( 'value' => 'MP', 'label' => __( 'Mpumalanga', 'bits-accessible-forms' ) ),
            array( 'value' => 'NC', 'label' => __( 'Northern Cape', 'bits-accessible-forms' ) ),
            array( 'value' => 'NW', 'label' => __( 'North West', 'bits-accessible-forms' ) ),
            array( 'value' => 'WC', 'label' => __( 'Western Cape', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Irish counties
     *
     * @return array Irish counties.
     */
    public static function get_ie_counties() {
        return array(
            array( 'value' => 'CW', 'label' => __( 'Carlow', 'bits-accessible-forms' ) ),
            array( 'value' => 'CN', 'label' => __( 'Cavan', 'bits-accessible-forms' ) ),
            array( 'value' => 'CE', 'label' => __( 'Clare', 'bits-accessible-forms' ) ),
            array( 'value' => 'CO', 'label' => __( 'Cork', 'bits-accessible-forms' ) ),
            array( 'value' => 'DL', 'label' => __( 'Donegal', 'bits-accessible-forms' ) ),
            array( 'value' => 'D', 'label' => __( 'Dublin', 'bits-accessible-forms' ) ),
            array( 'value' => 'G', 'label' => __( 'Galway', 'bits-accessible-forms' ) ),
            array( 'value' => 'KY', 'label' => __( 'Kerry', 'bits-accessible-forms' ) ),
            array( 'value' => 'KE', 'label' => __( 'Kildare', 'bits-accessible-forms' ) ),
            array( 'value' => 'KK', 'label' => __( 'Kilkenny', 'bits-accessible-forms' ) ),
            array( 'value' => 'LS', 'label' => __( 'Laois', 'bits-accessible-forms' ) ),
            array( 'value' => 'LM', 'label' => __( 'Leitrim', 'bits-accessible-forms' ) ),
            array( 'value' => 'LK', 'label' => __( 'Limerick', 'bits-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Longford', 'bits-accessible-forms' ) ),
            array( 'value' => 'LH', 'label' => __( 'Louth', 'bits-accessible-forms' ) ),
            array( 'value' => 'MO', 'label' => __( 'Mayo', 'bits-accessible-forms' ) ),
            array( 'value' => 'MH', 'label' => __( 'Meath', 'bits-accessible-forms' ) ),
            array( 'value' => 'MN', 'label' => __( 'Monaghan', 'bits-accessible-forms' ) ),
            array( 'value' => 'OY', 'label' => __( 'Offaly', 'bits-accessible-forms' ) ),
            array( 'value' => 'RN', 'label' => __( 'Roscommon', 'bits-accessible-forms' ) ),
            array( 'value' => 'SO', 'label' => __( 'Sligo', 'bits-accessible-forms' ) ),
            array( 'value' => 'TA', 'label' => __( 'Tipperary', 'bits-accessible-forms' ) ),
            array( 'value' => 'WD', 'label' => __( 'Waterford', 'bits-accessible-forms' ) ),
            array( 'value' => 'WH', 'label' => __( 'Westmeath', 'bits-accessible-forms' ) ),
            array( 'value' => 'WX', 'label' => __( 'Wexford', 'bits-accessible-forms' ) ),
            array( 'value' => 'WW', 'label' => __( 'Wicklow', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Polish voivodeships
     *
     * @return array Polish voivodeships.
     */
    public static function get_pl_voivodeships() {
        return array(
            array( 'value' => 'DS', 'label' => __( 'Lower Silesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'KP', 'label' => __( 'Kuyavia-Pomerania', 'bits-accessible-forms' ) ),
            array( 'value' => 'LU', 'label' => __( 'Lublin', 'bits-accessible-forms' ) ),
            array( 'value' => 'LB', 'label' => __( 'Lubusz', 'bits-accessible-forms' ) ),
            array( 'value' => 'LD', 'label' => __( 'Łódź', 'bits-accessible-forms' ) ),
            array( 'value' => 'MA', 'label' => __( 'Lesser Poland', 'bits-accessible-forms' ) ),
            array( 'value' => 'MZ', 'label' => __( 'Masovia', 'bits-accessible-forms' ) ),
            array( 'value' => 'OP', 'label' => __( 'Opole', 'bits-accessible-forms' ) ),
            array( 'value' => 'PK', 'label' => __( 'Subcarpathia', 'bits-accessible-forms' ) ),
            array( 'value' => 'PD', 'label' => __( 'Podlachia', 'bits-accessible-forms' ) ),
            array( 'value' => 'PM', 'label' => __( 'Pomerania', 'bits-accessible-forms' ) ),
            array( 'value' => 'SL', 'label' => __( 'Silesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'SK', 'label' => __( 'Holy Cross', 'bits-accessible-forms' ) ),
            array( 'value' => 'WN', 'label' => __( 'Warmia-Masuria', 'bits-accessible-forms' ) ),
            array( 'value' => 'WP', 'label' => __( 'Greater Poland', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZP', 'label' => __( 'West Pomerania', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get Russian regions (federal subjects)
     *
     * @return array Russian regions.
     */
    public static function get_ru_regions() {
        return array(
            array( 'value' => 'MOW', 'label' => __( 'Moscow', 'bits-accessible-forms' ) ),
            array( 'value' => 'SPE', 'label' => __( 'Saint Petersburg', 'bits-accessible-forms' ) ),
            array( 'value' => 'MOS', 'label' => __( 'Moscow Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'LEN', 'label' => __( 'Leningrad Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'NIZ', 'label' => __( 'Nizhny Novgorod Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'SVE', 'label' => __( 'Sverdlovsk Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'SAM', 'label' => __( 'Samara Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'ROS', 'label' => __( 'Rostov Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'BA', 'label' => __( 'Bashkortostan', 'bits-accessible-forms' ) ),
            array( 'value' => 'TA', 'label' => __( 'Tatarstan', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHE', 'label' => __( 'Chelyabinsk Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'KDA', 'label' => __( 'Krasnodar Krai', 'bits-accessible-forms' ) ),
            array( 'value' => 'PER', 'label' => __( 'Perm Krai', 'bits-accessible-forms' ) ),
            array( 'value' => 'VOR', 'label' => __( 'Voronezh Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'VGG', 'label' => __( 'Volgograd Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'KYA', 'label' => __( 'Krasnoyarsk Krai', 'bits-accessible-forms' ) ),
            array( 'value' => 'NVS', 'label' => __( 'Novosibirsk Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Saratov Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'TYU', 'label' => __( 'Tyumen Oblast', 'bits-accessible-forms' ) ),
            array( 'value' => 'KEM', 'label' => __( 'Kemerovo Oblast', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'Africa', 'label' => __( 'Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'America', 'label' => __( 'Americas', 'bits-accessible-forms' ) ),
            array( 'value' => 'Antarctica', 'label' => __( 'Antarctica', 'bits-accessible-forms' ) ),
            array( 'value' => 'Arctic', 'label' => __( 'Arctic', 'bits-accessible-forms' ) ),
            array( 'value' => 'Asia', 'label' => __( 'Asia', 'bits-accessible-forms' ) ),
            array( 'value' => 'Atlantic', 'label' => __( 'Atlantic', 'bits-accessible-forms' ) ),
            array( 'value' => 'Australia', 'label' => __( 'Australia', 'bits-accessible-forms' ) ),
            array( 'value' => 'Europe', 'label' => __( 'Europe', 'bits-accessible-forms' ) ),
            array( 'value' => 'Indian', 'label' => __( 'Indian Ocean', 'bits-accessible-forms' ) ),
            array( 'value' => 'Pacific', 'label' => __( 'Pacific', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'USD', 'label' => __( 'US Dollar (USD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'EUR', 'label' => __( 'Euro (EUR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'GBP', 'label' => __( 'British Pound (GBP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'JPY', 'label' => __( 'Japanese Yen (JPY)', 'bits-accessible-forms' ) ),
            array( 'value' => 'AUD', 'label' => __( 'Australian Dollar (AUD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAD', 'label' => __( 'Canadian Dollar (CAD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHF', 'label' => __( 'Swiss Franc (CHF)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CNY', 'label' => __( 'Chinese Yuan (CNY)', 'bits-accessible-forms' ) ),
            array( 'value' => 'HKD', 'label' => __( 'Hong Kong Dollar (HKD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'NZD', 'label' => __( 'New Zealand Dollar (NZD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'SEK', 'label' => __( 'Swedish Krona (SEK)', 'bits-accessible-forms' ) ),
            array( 'value' => 'KRW', 'label' => __( 'South Korean Won (KRW)', 'bits-accessible-forms' ) ),
            array( 'value' => 'SGD', 'label' => __( 'Singapore Dollar (SGD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'NOK', 'label' => __( 'Norwegian Krone (NOK)', 'bits-accessible-forms' ) ),
            array( 'value' => 'MXN', 'label' => __( 'Mexican Peso (MXN)', 'bits-accessible-forms' ) ),
            array( 'value' => 'INR', 'label' => __( 'Indian Rupee (INR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'RUB', 'label' => __( 'Russian Ruble (RUB)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ZAR', 'label' => __( 'South African Rand (ZAR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'TRY', 'label' => __( 'Turkish Lira (TRY)', 'bits-accessible-forms' ) ),
            array( 'value' => 'BRL', 'label' => __( 'Brazilian Real (BRL)', 'bits-accessible-forms' ) ),
            array( 'value' => 'TWD', 'label' => __( 'New Taiwan Dollar (TWD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'DKK', 'label' => __( 'Danish Krone (DKK)', 'bits-accessible-forms' ) ),
            array( 'value' => 'PLN', 'label' => __( 'Polish Zloty (PLN)', 'bits-accessible-forms' ) ),
            array( 'value' => 'THB', 'label' => __( 'Thai Baht (THB)', 'bits-accessible-forms' ) ),
            array( 'value' => 'IDR', 'label' => __( 'Indonesian Rupiah (IDR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'HUF', 'label' => __( 'Hungarian Forint (HUF)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CZK', 'label' => __( 'Czech Koruna (CZK)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ILS', 'label' => __( 'Israeli Shekel (ILS)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CLP', 'label' => __( 'Chilean Peso (CLP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'PHP', 'label' => __( 'Philippine Peso (PHP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'AED', 'label' => __( 'UAE Dirham (AED)', 'bits-accessible-forms' ) ),
            array( 'value' => 'COP', 'label' => __( 'Colombian Peso (COP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'SAR', 'label' => __( 'Saudi Riyal (SAR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'MYR', 'label' => __( 'Malaysian Ringgit (MYR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'RON', 'label' => __( 'Romanian Leu (RON)', 'bits-accessible-forms' ) ),
            array( 'value' => 'PEN', 'label' => __( 'Peruvian Sol (PEN)', 'bits-accessible-forms' ) ),
            array( 'value' => 'VND', 'label' => __( 'Vietnamese Dong (VND)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ARS', 'label' => __( 'Argentine Peso (ARS)', 'bits-accessible-forms' ) ),
            array( 'value' => 'EGP', 'label' => __( 'Egyptian Pound (EGP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'PKR', 'label' => __( 'Pakistani Rupee (PKR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'NGN', 'label' => __( 'Nigerian Naira (NGN)', 'bits-accessible-forms' ) ),
            array( 'value' => 'BDT', 'label' => __( 'Bangladeshi Taka (BDT)', 'bits-accessible-forms' ) ),
            array( 'value' => 'UAH', 'label' => __( 'Ukrainian Hryvnia (UAH)', 'bits-accessible-forms' ) ),
            array( 'value' => 'KZT', 'label' => __( 'Kazakhstani Tenge (KZT)', 'bits-accessible-forms' ) ),
            array( 'value' => 'QAR', 'label' => __( 'Qatari Riyal (QAR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'KWD', 'label' => __( 'Kuwaiti Dinar (KWD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'BHD', 'label' => __( 'Bahraini Dinar (BHD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'OMR', 'label' => __( 'Omani Rial (OMR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'JOD', 'label' => __( 'Jordanian Dinar (JOD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'LKR', 'label' => __( 'Sri Lankan Rupee (LKR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'MAD', 'label' => __( 'Moroccan Dirham (MAD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'KES', 'label' => __( 'Kenyan Shilling (KES)', 'bits-accessible-forms' ) ),
            array( 'value' => 'GHS', 'label' => __( 'Ghanaian Cedi (GHS)', 'bits-accessible-forms' ) ),
            array( 'value' => 'TZS', 'label' => __( 'Tanzanian Shilling (TZS)', 'bits-accessible-forms' ) ),
            array( 'value' => 'UGX', 'label' => __( 'Ugandan Shilling (UGX)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ETB', 'label' => __( 'Ethiopian Birr (ETB)', 'bits-accessible-forms' ) ),
            array( 'value' => 'XAF', 'label' => __( 'Central African CFA (XAF)', 'bits-accessible-forms' ) ),
            array( 'value' => 'XOF', 'label' => __( 'West African CFA (XOF)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get major currencies
     *
     * @return array Major currencies.
     */
    public static function get_currencies_major() {
        return array(
            array( 'value' => 'USD', 'label' => __( 'US Dollar (USD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'EUR', 'label' => __( 'Euro (EUR)', 'bits-accessible-forms' ) ),
            array( 'value' => 'GBP', 'label' => __( 'British Pound (GBP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'JPY', 'label' => __( 'Japanese Yen (JPY)', 'bits-accessible-forms' ) ),
            array( 'value' => 'AUD', 'label' => __( 'Australian Dollar (AUD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CAD', 'label' => __( 'Canadian Dollar (CAD)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CHF', 'label' => __( 'Swiss Franc (CHF)', 'bits-accessible-forms' ) ),
            array( 'value' => 'CNY', 'label' => __( 'Chinese Yuan (CNY)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get cryptocurrencies
     *
     * @return array Cryptocurrencies.
     */
    public static function get_cryptocurrencies() {
        return array(
            array( 'value' => 'BTC', 'label' => __( 'Bitcoin (BTC)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ETH', 'label' => __( 'Ethereum (ETH)', 'bits-accessible-forms' ) ),
            array( 'value' => 'USDT', 'label' => __( 'Tether (USDT)', 'bits-accessible-forms' ) ),
            array( 'value' => 'BNB', 'label' => __( 'Binance Coin (BNB)', 'bits-accessible-forms' ) ),
            array( 'value' => 'USDC', 'label' => __( 'USD Coin (USDC)', 'bits-accessible-forms' ) ),
            array( 'value' => 'XRP', 'label' => __( 'Ripple (XRP)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ADA', 'label' => __( 'Cardano (ADA)', 'bits-accessible-forms' ) ),
            array( 'value' => 'DOGE', 'label' => __( 'Dogecoin (DOGE)', 'bits-accessible-forms' ) ),
            array( 'value' => 'SOL', 'label' => __( 'Solana (SOL)', 'bits-accessible-forms' ) ),
            array( 'value' => 'DOT', 'label' => __( 'Polkadot (DOT)', 'bits-accessible-forms' ) ),
            array( 'value' => 'MATIC', 'label' => __( 'Polygon (MATIC)', 'bits-accessible-forms' ) ),
            array( 'value' => 'LTC', 'label' => __( 'Litecoin (LTC)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get languages (comprehensive)
     *
     * @return array Languages.
     */
    public static function get_languages() {
        return array(
            array( 'value' => 'en', 'label' => __( 'English', 'bits-accessible-forms' ) ),
            array( 'value' => 'zh', 'label' => __( 'Chinese (Mandarin)', 'bits-accessible-forms' ) ),
            array( 'value' => 'hi', 'label' => __( 'Hindi', 'bits-accessible-forms' ) ),
            array( 'value' => 'es', 'label' => __( 'Spanish', 'bits-accessible-forms' ) ),
            array( 'value' => 'fr', 'label' => __( 'French', 'bits-accessible-forms' ) ),
            array( 'value' => 'ar', 'label' => __( 'Arabic', 'bits-accessible-forms' ) ),
            array( 'value' => 'bn', 'label' => __( 'Bengali', 'bits-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Portuguese', 'bits-accessible-forms' ) ),
            array( 'value' => 'ru', 'label' => __( 'Russian', 'bits-accessible-forms' ) ),
            array( 'value' => 'ja', 'label' => __( 'Japanese', 'bits-accessible-forms' ) ),
            array( 'value' => 'de', 'label' => __( 'German', 'bits-accessible-forms' ) ),
            array( 'value' => 'ko', 'label' => __( 'Korean', 'bits-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Italian', 'bits-accessible-forms' ) ),
            array( 'value' => 'tr', 'label' => __( 'Turkish', 'bits-accessible-forms' ) ),
            array( 'value' => 'vi', 'label' => __( 'Vietnamese', 'bits-accessible-forms' ) ),
            array( 'value' => 'pl', 'label' => __( 'Polish', 'bits-accessible-forms' ) ),
            array( 'value' => 'uk', 'label' => __( 'Ukrainian', 'bits-accessible-forms' ) ),
            array( 'value' => 'nl', 'label' => __( 'Dutch', 'bits-accessible-forms' ) ),
            array( 'value' => 'el', 'label' => __( 'Greek', 'bits-accessible-forms' ) ),
            array( 'value' => 'cs', 'label' => __( 'Czech', 'bits-accessible-forms' ) ),
            array( 'value' => 'sv', 'label' => __( 'Swedish', 'bits-accessible-forms' ) ),
            array( 'value' => 'hu', 'label' => __( 'Hungarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'ro', 'label' => __( 'Romanian', 'bits-accessible-forms' ) ),
            array( 'value' => 'th', 'label' => __( 'Thai', 'bits-accessible-forms' ) ),
            array( 'value' => 'id', 'label' => __( 'Indonesian', 'bits-accessible-forms' ) ),
            array( 'value' => 'ms', 'label' => __( 'Malay', 'bits-accessible-forms' ) ),
            array( 'value' => 'da', 'label' => __( 'Danish', 'bits-accessible-forms' ) ),
            array( 'value' => 'fi', 'label' => __( 'Finnish', 'bits-accessible-forms' ) ),
            array( 'value' => 'no', 'label' => __( 'Norwegian', 'bits-accessible-forms' ) ),
            array( 'value' => 'he', 'label' => __( 'Hebrew', 'bits-accessible-forms' ) ),
            array( 'value' => 'fa', 'label' => __( 'Persian (Farsi)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ur', 'label' => __( 'Urdu', 'bits-accessible-forms' ) ),
            array( 'value' => 'ta', 'label' => __( 'Tamil', 'bits-accessible-forms' ) ),
            array( 'value' => 'te', 'label' => __( 'Telugu', 'bits-accessible-forms' ) ),
            array( 'value' => 'mr', 'label' => __( 'Marathi', 'bits-accessible-forms' ) ),
            array( 'value' => 'gu', 'label' => __( 'Gujarati', 'bits-accessible-forms' ) ),
            array( 'value' => 'kn', 'label' => __( 'Kannada', 'bits-accessible-forms' ) ),
            array( 'value' => 'ml', 'label' => __( 'Malayalam', 'bits-accessible-forms' ) ),
            array( 'value' => 'pa', 'label' => __( 'Punjabi', 'bits-accessible-forms' ) ),
            array( 'value' => 'sw', 'label' => __( 'Swahili', 'bits-accessible-forms' ) ),
            array( 'value' => 'tl', 'label' => __( 'Tagalog/Filipino', 'bits-accessible-forms' ) ),
            array( 'value' => 'ca', 'label' => __( 'Catalan', 'bits-accessible-forms' ) ),
            array( 'value' => 'hr', 'label' => __( 'Croatian', 'bits-accessible-forms' ) ),
            array( 'value' => 'sk', 'label' => __( 'Slovak', 'bits-accessible-forms' ) ),
            array( 'value' => 'sl', 'label' => __( 'Slovenian', 'bits-accessible-forms' ) ),
            array( 'value' => 'bg', 'label' => __( 'Bulgarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'sr', 'label' => __( 'Serbian', 'bits-accessible-forms' ) ),
            array( 'value' => 'lt', 'label' => __( 'Lithuanian', 'bits-accessible-forms' ) ),
            array( 'value' => 'lv', 'label' => __( 'Latvian', 'bits-accessible-forms' ) ),
            array( 'value' => 'et', 'label' => __( 'Estonian', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get major languages
     *
     * @return array Major languages.
     */
    public static function get_languages_major() {
        return array(
            array( 'value' => 'en', 'label' => __( 'English', 'bits-accessible-forms' ) ),
            array( 'value' => 'es', 'label' => __( 'Spanish', 'bits-accessible-forms' ) ),
            array( 'value' => 'fr', 'label' => __( 'French', 'bits-accessible-forms' ) ),
            array( 'value' => 'de', 'label' => __( 'German', 'bits-accessible-forms' ) ),
            array( 'value' => 'zh', 'label' => __( 'Chinese', 'bits-accessible-forms' ) ),
            array( 'value' => 'ja', 'label' => __( 'Japanese', 'bits-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Portuguese', 'bits-accessible-forms' ) ),
            array( 'value' => 'ru', 'label' => __( 'Russian', 'bits-accessible-forms' ) ),
            array( 'value' => 'ar', 'label' => __( 'Arabic', 'bits-accessible-forms' ) ),
            array( 'value' => 'hi', 'label' => __( 'Hindi', 'bits-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Italian', 'bits-accessible-forms' ) ),
            array( 'value' => 'ko', 'label' => __( 'Korean', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get phone country codes
     *
     * @return array Phone country codes.
     */
    public static function get_phone_country_codes() {
        return array(
            array( 'value' => '+1', 'label' => __( '+1 (US/Canada)', 'bits-accessible-forms' ) ),
            array( 'value' => '+44', 'label' => __( '+44 (United Kingdom)', 'bits-accessible-forms' ) ),
            array( 'value' => '+61', 'label' => __( '+61 (Australia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+49', 'label' => __( '+49 (Germany)', 'bits-accessible-forms' ) ),
            array( 'value' => '+33', 'label' => __( '+33 (France)', 'bits-accessible-forms' ) ),
            array( 'value' => '+39', 'label' => __( '+39 (Italy)', 'bits-accessible-forms' ) ),
            array( 'value' => '+34', 'label' => __( '+34 (Spain)', 'bits-accessible-forms' ) ),
            array( 'value' => '+31', 'label' => __( '+31 (Netherlands)', 'bits-accessible-forms' ) ),
            array( 'value' => '+32', 'label' => __( '+32 (Belgium)', 'bits-accessible-forms' ) ),
            array( 'value' => '+41', 'label' => __( '+41 (Switzerland)', 'bits-accessible-forms' ) ),
            array( 'value' => '+43', 'label' => __( '+43 (Austria)', 'bits-accessible-forms' ) ),
            array( 'value' => '+46', 'label' => __( '+46 (Sweden)', 'bits-accessible-forms' ) ),
            array( 'value' => '+47', 'label' => __( '+47 (Norway)', 'bits-accessible-forms' ) ),
            array( 'value' => '+45', 'label' => __( '+45 (Denmark)', 'bits-accessible-forms' ) ),
            array( 'value' => '+358', 'label' => __( '+358 (Finland)', 'bits-accessible-forms' ) ),
            array( 'value' => '+353', 'label' => __( '+353 (Ireland)', 'bits-accessible-forms' ) ),
            array( 'value' => '+48', 'label' => __( '+48 (Poland)', 'bits-accessible-forms' ) ),
            array( 'value' => '+351', 'label' => __( '+351 (Portugal)', 'bits-accessible-forms' ) ),
            array( 'value' => '+7', 'label' => __( '+7 (Russia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+81', 'label' => __( '+81 (Japan)', 'bits-accessible-forms' ) ),
            array( 'value' => '+86', 'label' => __( '+86 (China)', 'bits-accessible-forms' ) ),
            array( 'value' => '+82', 'label' => __( '+82 (South Korea)', 'bits-accessible-forms' ) ),
            array( 'value' => '+91', 'label' => __( '+91 (India)', 'bits-accessible-forms' ) ),
            array( 'value' => '+852', 'label' => __( '+852 (Hong Kong)', 'bits-accessible-forms' ) ),
            array( 'value' => '+886', 'label' => __( '+886 (Taiwan)', 'bits-accessible-forms' ) ),
            array( 'value' => '+65', 'label' => __( '+65 (Singapore)', 'bits-accessible-forms' ) ),
            array( 'value' => '+60', 'label' => __( '+60 (Malaysia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+66', 'label' => __( '+66 (Thailand)', 'bits-accessible-forms' ) ),
            array( 'value' => '+62', 'label' => __( '+62 (Indonesia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+63', 'label' => __( '+63 (Philippines)', 'bits-accessible-forms' ) ),
            array( 'value' => '+84', 'label' => __( '+84 (Vietnam)', 'bits-accessible-forms' ) ),
            array( 'value' => '+64', 'label' => __( '+64 (New Zealand)', 'bits-accessible-forms' ) ),
            array( 'value' => '+55', 'label' => __( '+55 (Brazil)', 'bits-accessible-forms' ) ),
            array( 'value' => '+52', 'label' => __( '+52 (Mexico)', 'bits-accessible-forms' ) ),
            array( 'value' => '+54', 'label' => __( '+54 (Argentina)', 'bits-accessible-forms' ) ),
            array( 'value' => '+56', 'label' => __( '+56 (Chile)', 'bits-accessible-forms' ) ),
            array( 'value' => '+57', 'label' => __( '+57 (Colombia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+51', 'label' => __( '+51 (Peru)', 'bits-accessible-forms' ) ),
            array( 'value' => '+27', 'label' => __( '+27 (South Africa)', 'bits-accessible-forms' ) ),
            array( 'value' => '+20', 'label' => __( '+20 (Egypt)', 'bits-accessible-forms' ) ),
            array( 'value' => '+234', 'label' => __( '+234 (Nigeria)', 'bits-accessible-forms' ) ),
            array( 'value' => '+254', 'label' => __( '+254 (Kenya)', 'bits-accessible-forms' ) ),
            array( 'value' => '+971', 'label' => __( '+971 (UAE)', 'bits-accessible-forms' ) ),
            array( 'value' => '+966', 'label' => __( '+966 (Saudi Arabia)', 'bits-accessible-forms' ) ),
            array( 'value' => '+972', 'label' => __( '+972 (Israel)', 'bits-accessible-forms' ) ),
            array( 'value' => '+90', 'label' => __( '+90 (Turkey)', 'bits-accessible-forms' ) ),
            array( 'value' => '+30', 'label' => __( '+30 (Greece)', 'bits-accessible-forms' ) ),
            array( 'value' => '+380', 'label' => __( '+380 (Ukraine)', 'bits-accessible-forms' ) ),
            array( 'value' => '+40', 'label' => __( '+40 (Romania)', 'bits-accessible-forms' ) ),
            array( 'value' => '+36', 'label' => __( '+36 (Hungary)', 'bits-accessible-forms' ) ),
            array( 'value' => '+420', 'label' => __( '+420 (Czech Republic)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get industries (comprehensive)
     *
     * @return array Industries.
     */
    public static function get_industries() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting & Finance', 'bits-accessible-forms' ) ),
            array( 'value' => 'advertising', 'label' => __( 'Advertising & Marketing', 'bits-accessible-forms' ) ),
            array( 'value' => 'aerospace', 'label' => __( 'Aerospace & Defense', 'bits-accessible-forms' ) ),
            array( 'value' => 'agriculture', 'label' => __( 'Agriculture & Farming', 'bits-accessible-forms' ) ),
            array( 'value' => 'architecture', 'label' => __( 'Architecture & Planning', 'bits-accessible-forms' ) ),
            array( 'value' => 'arts', 'label' => __( 'Arts & Entertainment', 'bits-accessible-forms' ) ),
            array( 'value' => 'automotive', 'label' => __( 'Automotive', 'bits-accessible-forms' ) ),
            array( 'value' => 'banking', 'label' => __( 'Banking & Financial Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'biotechnology', 'label' => __( 'Biotechnology', 'bits-accessible-forms' ) ),
            array( 'value' => 'broadcasting', 'label' => __( 'Broadcasting & Media', 'bits-accessible-forms' ) ),
            array( 'value' => 'chemicals', 'label' => __( 'Chemicals', 'bits-accessible-forms' ) ),
            array( 'value' => 'construction', 'label' => __( 'Construction', 'bits-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'bits-accessible-forms' ) ),
            array( 'value' => 'consumer_goods', 'label' => __( 'Consumer Goods', 'bits-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education', 'bits-accessible-forms' ) ),
            array( 'value' => 'electronics', 'label' => __( 'Electronics', 'bits-accessible-forms' ) ),
            array( 'value' => 'energy', 'label' => __( 'Energy & Utilities', 'bits-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'bits-accessible-forms' ) ),
            array( 'value' => 'environmental', 'label' => __( 'Environmental Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'fashion', 'label' => __( 'Fashion & Apparel', 'bits-accessible-forms' ) ),
            array( 'value' => 'food_beverage', 'label' => __( 'Food & Beverage', 'bits-accessible-forms' ) ),
            array( 'value' => 'gaming', 'label' => __( 'Gaming & Casinos', 'bits-accessible-forms' ) ),
            array( 'value' => 'government', 'label' => __( 'Government & Public Sector', 'bits-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'bits-accessible-forms' ) ),
            array( 'value' => 'hospitality', 'label' => __( 'Hospitality & Hotels', 'bits-accessible-forms' ) ),
            array( 'value' => 'human_resources', 'label' => __( 'Human Resources', 'bits-accessible-forms' ) ),
            array( 'value' => 'information_technology', 'label' => __( 'Information Technology', 'bits-accessible-forms' ) ),
            array( 'value' => 'insurance', 'label' => __( 'Insurance', 'bits-accessible-forms' ) ),
            array( 'value' => 'internet', 'label' => __( 'Internet & E-commerce', 'bits-accessible-forms' ) ),
            array( 'value' => 'investment', 'label' => __( 'Investment & Venture Capital', 'bits-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'logistics', 'label' => __( 'Logistics & Supply Chain', 'bits-accessible-forms' ) ),
            array( 'value' => 'machinery', 'label' => __( 'Machinery & Equipment', 'bits-accessible-forms' ) ),
            array( 'value' => 'manufacturing', 'label' => __( 'Manufacturing', 'bits-accessible-forms' ) ),
            array( 'value' => 'maritime', 'label' => __( 'Maritime & Shipping', 'bits-accessible-forms' ) ),
            array( 'value' => 'mining', 'label' => __( 'Mining & Metals', 'bits-accessible-forms' ) ),
            array( 'value' => 'music', 'label' => __( 'Music & Recording', 'bits-accessible-forms' ) ),
            array( 'value' => 'nonprofit', 'label' => __( 'Non-Profit & NGO', 'bits-accessible-forms' ) ),
            array( 'value' => 'oil_gas', 'label' => __( 'Oil & Gas', 'bits-accessible-forms' ) ),
            array( 'value' => 'packaging', 'label' => __( 'Packaging & Containers', 'bits-accessible-forms' ) ),
            array( 'value' => 'pharmaceuticals', 'label' => __( 'Pharmaceuticals', 'bits-accessible-forms' ) ),
            array( 'value' => 'printing', 'label' => __( 'Printing & Publishing', 'bits-accessible-forms' ) ),
            array( 'value' => 'real_estate', 'label' => __( 'Real Estate', 'bits-accessible-forms' ) ),
            array( 'value' => 'recreation', 'label' => __( 'Recreation & Leisure', 'bits-accessible-forms' ) ),
            array( 'value' => 'religious', 'label' => __( 'Religious Organizations', 'bits-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research & Development', 'bits-accessible-forms' ) ),
            array( 'value' => 'restaurants', 'label' => __( 'Restaurants & Food Service', 'bits-accessible-forms' ) ),
            array( 'value' => 'retail', 'label' => __( 'Retail', 'bits-accessible-forms' ) ),
            array( 'value' => 'security', 'label' => __( 'Security & Investigations', 'bits-accessible-forms' ) ),
            array( 'value' => 'semiconductors', 'label' => __( 'Semiconductors', 'bits-accessible-forms' ) ),
            array( 'value' => 'software', 'label' => __( 'Software & SaaS', 'bits-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports & Recreation', 'bits-accessible-forms' ) ),
            array( 'value' => 'staffing', 'label' => __( 'Staffing & Recruiting', 'bits-accessible-forms' ) ),
            array( 'value' => 'telecommunications', 'label' => __( 'Telecommunications', 'bits-accessible-forms' ) ),
            array( 'value' => 'textiles', 'label' => __( 'Textiles', 'bits-accessible-forms' ) ),
            array( 'value' => 'transportation', 'label' => __( 'Transportation', 'bits-accessible-forms' ) ),
            array( 'value' => 'travel', 'label' => __( 'Travel & Tourism', 'bits-accessible-forms' ) ),
            array( 'value' => 'veterinary', 'label' => __( 'Veterinary', 'bits-accessible-forms' ) ),
            array( 'value' => 'warehousing', 'label' => __( 'Warehousing & Storage', 'bits-accessible-forms' ) ),
            array( 'value' => 'wholesale', 'label' => __( 'Wholesale Trade', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get job functions/departments
     *
     * @return array Job functions.
     */
    public static function get_job_functions() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting/Finance', 'bits-accessible-forms' ) ),
            array( 'value' => 'admin', 'label' => __( 'Administrative', 'bits-accessible-forms' ) ),
            array( 'value' => 'art_design', 'label' => __( 'Art/Design', 'bits-accessible-forms' ) ),
            array( 'value' => 'business_dev', 'label' => __( 'Business Development', 'bits-accessible-forms' ) ),
            array( 'value' => 'community', 'label' => __( 'Community/Social Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'bits-accessible-forms' ) ),
            array( 'value' => 'customer_service', 'label' => __( 'Customer Service', 'bits-accessible-forms' ) ),
            array( 'value' => 'data_science', 'label' => __( 'Data Science/Analytics', 'bits-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education/Training', 'bits-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'bits-accessible-forms' ) ),
            array( 'value' => 'executive', 'label' => __( 'Executive/Management', 'bits-accessible-forms' ) ),
            array( 'value' => 'facilities', 'label' => __( 'Facilities/Maintenance', 'bits-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'bits-accessible-forms' ) ),
            array( 'value' => 'hr', 'label' => __( 'Human Resources', 'bits-accessible-forms' ) ),
            array( 'value' => 'it', 'label' => __( 'Information Technology', 'bits-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal', 'bits-accessible-forms' ) ),
            array( 'value' => 'logistics', 'label' => __( 'Logistics/Supply Chain', 'bits-accessible-forms' ) ),
            array( 'value' => 'manufacturing', 'label' => __( 'Manufacturing/Production', 'bits-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'bits-accessible-forms' ) ),
            array( 'value' => 'media', 'label' => __( 'Media/Communications', 'bits-accessible-forms' ) ),
            array( 'value' => 'military', 'label' => __( 'Military/Protective Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'operations', 'label' => __( 'Operations', 'bits-accessible-forms' ) ),
            array( 'value' => 'product', 'label' => __( 'Product Management', 'bits-accessible-forms' ) ),
            array( 'value' => 'project', 'label' => __( 'Project Management', 'bits-accessible-forms' ) ),
            array( 'value' => 'purchasing', 'label' => __( 'Purchasing/Procurement', 'bits-accessible-forms' ) ),
            array( 'value' => 'qa', 'label' => __( 'Quality Assurance', 'bits-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research', 'bits-accessible-forms' ) ),
            array( 'value' => 'sales', 'label' => __( 'Sales', 'bits-accessible-forms' ) ),
            array( 'value' => 'science', 'label' => __( 'Science', 'bits-accessible-forms' ) ),
            array( 'value' => 'skilled_trade', 'label' => __( 'Skilled Trades', 'bits-accessible-forms' ) ),
            array( 'value' => 'strategy', 'label' => __( 'Strategy/Planning', 'bits-accessible-forms' ) ),
            array( 'value' => 'writing', 'label' => __( 'Writing/Editing', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get fields of study
     *
     * @return array Fields of study.
     */
    public static function get_fields_of_study() {
        return array(
            array( 'value' => 'accounting', 'label' => __( 'Accounting', 'bits-accessible-forms' ) ),
            array( 'value' => 'agriculture', 'label' => __( 'Agriculture', 'bits-accessible-forms' ) ),
            array( 'value' => 'anthropology', 'label' => __( 'Anthropology', 'bits-accessible-forms' ) ),
            array( 'value' => 'architecture', 'label' => __( 'Architecture', 'bits-accessible-forms' ) ),
            array( 'value' => 'art', 'label' => __( 'Art/Fine Arts', 'bits-accessible-forms' ) ),
            array( 'value' => 'biology', 'label' => __( 'Biology', 'bits-accessible-forms' ) ),
            array( 'value' => 'business', 'label' => __( 'Business Administration', 'bits-accessible-forms' ) ),
            array( 'value' => 'chemistry', 'label' => __( 'Chemistry', 'bits-accessible-forms' ) ),
            array( 'value' => 'communications', 'label' => __( 'Communications', 'bits-accessible-forms' ) ),
            array( 'value' => 'computer_science', 'label' => __( 'Computer Science', 'bits-accessible-forms' ) ),
            array( 'value' => 'criminal_justice', 'label' => __( 'Criminal Justice', 'bits-accessible-forms' ) ),
            array( 'value' => 'design', 'label' => __( 'Design', 'bits-accessible-forms' ) ),
            array( 'value' => 'economics', 'label' => __( 'Economics', 'bits-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education', 'bits-accessible-forms' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'bits-accessible-forms' ) ),
            array( 'value' => 'english', 'label' => __( 'English/Literature', 'bits-accessible-forms' ) ),
            array( 'value' => 'environmental', 'label' => __( 'Environmental Science', 'bits-accessible-forms' ) ),
            array( 'value' => 'film', 'label' => __( 'Film/Media Studies', 'bits-accessible-forms' ) ),
            array( 'value' => 'finance', 'label' => __( 'Finance', 'bits-accessible-forms' ) ),
            array( 'value' => 'geography', 'label' => __( 'Geography', 'bits-accessible-forms' ) ),
            array( 'value' => 'health', 'label' => __( 'Health Sciences', 'bits-accessible-forms' ) ),
            array( 'value' => 'history', 'label' => __( 'History', 'bits-accessible-forms' ) ),
            array( 'value' => 'hospitality', 'label' => __( 'Hospitality/Tourism', 'bits-accessible-forms' ) ),
            array( 'value' => 'international', 'label' => __( 'International Relations', 'bits-accessible-forms' ) ),
            array( 'value' => 'journalism', 'label' => __( 'Journalism', 'bits-accessible-forms' ) ),
            array( 'value' => 'law', 'label' => __( 'Law/Legal Studies', 'bits-accessible-forms' ) ),
            array( 'value' => 'linguistics', 'label' => __( 'Linguistics', 'bits-accessible-forms' ) ),
            array( 'value' => 'management', 'label' => __( 'Management', 'bits-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'bits-accessible-forms' ) ),
            array( 'value' => 'mathematics', 'label' => __( 'Mathematics', 'bits-accessible-forms' ) ),
            array( 'value' => 'medicine', 'label' => __( 'Medicine', 'bits-accessible-forms' ) ),
            array( 'value' => 'music', 'label' => __( 'Music', 'bits-accessible-forms' ) ),
            array( 'value' => 'nursing', 'label' => __( 'Nursing', 'bits-accessible-forms' ) ),
            array( 'value' => 'philosophy', 'label' => __( 'Philosophy', 'bits-accessible-forms' ) ),
            array( 'value' => 'physics', 'label' => __( 'Physics', 'bits-accessible-forms' ) ),
            array( 'value' => 'political_science', 'label' => __( 'Political Science', 'bits-accessible-forms' ) ),
            array( 'value' => 'psychology', 'label' => __( 'Psychology', 'bits-accessible-forms' ) ),
            array( 'value' => 'public_admin', 'label' => __( 'Public Administration', 'bits-accessible-forms' ) ),
            array( 'value' => 'social_work', 'label' => __( 'Social Work', 'bits-accessible-forms' ) ),
            array( 'value' => 'sociology', 'label' => __( 'Sociology', 'bits-accessible-forms' ) ),
            array( 'value' => 'theater', 'label' => __( 'Theater/Drama', 'bits-accessible-forms' ) ),
            array( 'value' => 'theology', 'label' => __( 'Theology/Religious Studies', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get medical specialties
     *
     * @return array Medical specialties.
     */
    public static function get_medical_specialties() {
        return array(
            array( 'value' => 'allergy', 'label' => __( 'Allergy & Immunology', 'bits-accessible-forms' ) ),
            array( 'value' => 'anesthesiology', 'label' => __( 'Anesthesiology', 'bits-accessible-forms' ) ),
            array( 'value' => 'cardiology', 'label' => __( 'Cardiology', 'bits-accessible-forms' ) ),
            array( 'value' => 'dermatology', 'label' => __( 'Dermatology', 'bits-accessible-forms' ) ),
            array( 'value' => 'emergency', 'label' => __( 'Emergency Medicine', 'bits-accessible-forms' ) ),
            array( 'value' => 'endocrinology', 'label' => __( 'Endocrinology', 'bits-accessible-forms' ) ),
            array( 'value' => 'family', 'label' => __( 'Family Medicine', 'bits-accessible-forms' ) ),
            array( 'value' => 'gastroenterology', 'label' => __( 'Gastroenterology', 'bits-accessible-forms' ) ),
            array( 'value' => 'general', 'label' => __( 'General Practice', 'bits-accessible-forms' ) ),
            array( 'value' => 'geriatrics', 'label' => __( 'Geriatrics', 'bits-accessible-forms' ) ),
            array( 'value' => 'hematology', 'label' => __( 'Hematology', 'bits-accessible-forms' ) ),
            array( 'value' => 'infectious', 'label' => __( 'Infectious Disease', 'bits-accessible-forms' ) ),
            array( 'value' => 'internal', 'label' => __( 'Internal Medicine', 'bits-accessible-forms' ) ),
            array( 'value' => 'nephrology', 'label' => __( 'Nephrology', 'bits-accessible-forms' ) ),
            array( 'value' => 'neurology', 'label' => __( 'Neurology', 'bits-accessible-forms' ) ),
            array( 'value' => 'obstetrics', 'label' => __( 'Obstetrics & Gynecology', 'bits-accessible-forms' ) ),
            array( 'value' => 'oncology', 'label' => __( 'Oncology', 'bits-accessible-forms' ) ),
            array( 'value' => 'ophthalmology', 'label' => __( 'Ophthalmology', 'bits-accessible-forms' ) ),
            array( 'value' => 'orthopedics', 'label' => __( 'Orthopedics', 'bits-accessible-forms' ) ),
            array( 'value' => 'otolaryngology', 'label' => __( 'Otolaryngology (ENT)', 'bits-accessible-forms' ) ),
            array( 'value' => 'pathology', 'label' => __( 'Pathology', 'bits-accessible-forms' ) ),
            array( 'value' => 'pediatrics', 'label' => __( 'Pediatrics', 'bits-accessible-forms' ) ),
            array( 'value' => 'physical', 'label' => __( 'Physical Medicine', 'bits-accessible-forms' ) ),
            array( 'value' => 'plastic', 'label' => __( 'Plastic Surgery', 'bits-accessible-forms' ) ),
            array( 'value' => 'psychiatry', 'label' => __( 'Psychiatry', 'bits-accessible-forms' ) ),
            array( 'value' => 'pulmonology', 'label' => __( 'Pulmonology', 'bits-accessible-forms' ) ),
            array( 'value' => 'radiology', 'label' => __( 'Radiology', 'bits-accessible-forms' ) ),
            array( 'value' => 'rheumatology', 'label' => __( 'Rheumatology', 'bits-accessible-forms' ) ),
            array( 'value' => 'surgery', 'label' => __( 'Surgery', 'bits-accessible-forms' ) ),
            array( 'value' => 'urology', 'label' => __( 'Urology', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get US shoe sizes
     *
     * @return array US shoe sizes.
     */
    public static function get_shoe_sizes_us() {
        return array(
            array( 'value' => '4', 'label' => __( '4 US', 'bits-accessible-forms' ) ),
            array( 'value' => '4.5', 'label' => __( '4.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '5', 'label' => __( '5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '5.5', 'label' => __( '5.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '6', 'label' => __( '6 US', 'bits-accessible-forms' ) ),
            array( 'value' => '6.5', 'label' => __( '6.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '7', 'label' => __( '7 US', 'bits-accessible-forms' ) ),
            array( 'value' => '7.5', 'label' => __( '7.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '8', 'label' => __( '8 US', 'bits-accessible-forms' ) ),
            array( 'value' => '8.5', 'label' => __( '8.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '9', 'label' => __( '9 US', 'bits-accessible-forms' ) ),
            array( 'value' => '9.5', 'label' => __( '9.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '10', 'label' => __( '10 US', 'bits-accessible-forms' ) ),
            array( 'value' => '10.5', 'label' => __( '10.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '11', 'label' => __( '11 US', 'bits-accessible-forms' ) ),
            array( 'value' => '11.5', 'label' => __( '11.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '12', 'label' => __( '12 US', 'bits-accessible-forms' ) ),
            array( 'value' => '12.5', 'label' => __( '12.5 US', 'bits-accessible-forms' ) ),
            array( 'value' => '13', 'label' => __( '13 US', 'bits-accessible-forms' ) ),
            array( 'value' => '14', 'label' => __( '14 US', 'bits-accessible-forms' ) ),
            array( 'value' => '15', 'label' => __( '15 US', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get EU shoe sizes
     *
     * @return array EU shoe sizes.
     */
    public static function get_shoe_sizes_eu() {
        return array(
            array( 'value' => '35', 'label' => __( '35 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '36', 'label' => __( '36 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '37', 'label' => __( '37 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '38', 'label' => __( '38 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '39', 'label' => __( '39 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '40', 'label' => __( '40 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '41', 'label' => __( '41 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '42', 'label' => __( '42 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '43', 'label' => __( '43 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '44', 'label' => __( '44 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '45', 'label' => __( '45 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '46', 'label' => __( '46 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '47', 'label' => __( '47 EU', 'bits-accessible-forms' ) ),
            array( 'value' => '48', 'label' => __( '48 EU', 'bits-accessible-forms' ) ),
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
            array( 'value' => 'christianity', 'label' => __( 'Christianity', 'bits-accessible-forms' ) ),
            array( 'value' => 'islam', 'label' => __( 'Islam', 'bits-accessible-forms' ) ),
            array( 'value' => 'hinduism', 'label' => __( 'Hinduism', 'bits-accessible-forms' ) ),
            array( 'value' => 'buddhism', 'label' => __( 'Buddhism', 'bits-accessible-forms' ) ),
            array( 'value' => 'sikhism', 'label' => __( 'Sikhism', 'bits-accessible-forms' ) ),
            array( 'value' => 'judaism', 'label' => __( 'Judaism', 'bits-accessible-forms' ) ),
            array( 'value' => 'bahai', 'label' => __( 'Bahai', 'bits-accessible-forms' ) ),
            array( 'value' => 'jainism', 'label' => __( 'Jainism', 'bits-accessible-forms' ) ),
            array( 'value' => 'shinto', 'label' => __( 'Shinto', 'bits-accessible-forms' ) ),
            array( 'value' => 'confucianism', 'label' => __( 'Confucianism', 'bits-accessible-forms' ) ),
            array( 'value' => 'taoism', 'label' => __( 'Taoism', 'bits-accessible-forms' ) ),
            array( 'value' => 'zoroastrianism', 'label' => __( 'Zoroastrianism', 'bits-accessible-forms' ) ),
            array( 'value' => 'agnostic', 'label' => __( 'Agnostic', 'bits-accessible-forms' ) ),
            array( 'value' => 'atheist', 'label' => __( 'Atheist', 'bits-accessible-forms' ) ),
            array( 'value' => 'spiritual', 'label' => __( 'Spiritual but not religious', 'bits-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get ethnicity options
     *
     * @return array Ethnicities.
     */
    public static function get_ethnicities() {
        return array(
            array( 'value' => 'asian', 'label' => __( 'Asian', 'bits-accessible-forms' ) ),
            array( 'value' => 'black', 'label' => __( 'Black or African American', 'bits-accessible-forms' ) ),
            array( 'value' => 'hispanic', 'label' => __( 'Hispanic or Latino', 'bits-accessible-forms' ) ),
            array( 'value' => 'middle_eastern', 'label' => __( 'Middle Eastern or North African', 'bits-accessible-forms' ) ),
            array( 'value' => 'native_american', 'label' => __( 'Native American or Alaska Native', 'bits-accessible-forms' ) ),
            array( 'value' => 'pacific_islander', 'label' => __( 'Native Hawaiian or Pacific Islander', 'bits-accessible-forms' ) ),
            array( 'value' => 'white', 'label' => __( 'White or Caucasian', 'bits-accessible-forms' ) ),
            array( 'value' => 'multiracial', 'label' => __( 'Two or more races', 'bits-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get political affiliation options
     *
     * @return array Political affiliations.
     */
    public static function get_political_affiliations() {
        return array(
            array( 'value' => 'conservative', 'label' => __( 'Conservative', 'bits-accessible-forms' ) ),
            array( 'value' => 'liberal', 'label' => __( 'Liberal', 'bits-accessible-forms' ) ),
            array( 'value' => 'moderate', 'label' => __( 'Moderate', 'bits-accessible-forms' ) ),
            array( 'value' => 'progressive', 'label' => __( 'Progressive', 'bits-accessible-forms' ) ),
            array( 'value' => 'libertarian', 'label' => __( 'Libertarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'socialist', 'label' => __( 'Socialist', 'bits-accessible-forms' ) ),
            array( 'value' => 'independent', 'label' => __( 'Independent', 'bits-accessible-forms' ) ),
            array( 'value' => 'apolitical', 'label' => __( 'Apolitical', 'bits-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get US cities (major)
     *
     * @return array US cities.
     */
    public static function get_cities_us() {
        return array(
            array( 'value' => 'new_york', 'label' => __( 'New York, NY', 'bits-accessible-forms' ) ),
            array( 'value' => 'los_angeles', 'label' => __( 'Los Angeles, CA', 'bits-accessible-forms' ) ),
            array( 'value' => 'chicago', 'label' => __( 'Chicago, IL', 'bits-accessible-forms' ) ),
            array( 'value' => 'houston', 'label' => __( 'Houston, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'phoenix', 'label' => __( 'Phoenix, AZ', 'bits-accessible-forms' ) ),
            array( 'value' => 'philadelphia', 'label' => __( 'Philadelphia, PA', 'bits-accessible-forms' ) ),
            array( 'value' => 'san_antonio', 'label' => __( 'San Antonio, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'san_diego', 'label' => __( 'San Diego, CA', 'bits-accessible-forms' ) ),
            array( 'value' => 'dallas', 'label' => __( 'Dallas, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'san_jose', 'label' => __( 'San Jose, CA', 'bits-accessible-forms' ) ),
            array( 'value' => 'austin', 'label' => __( 'Austin, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'jacksonville', 'label' => __( 'Jacksonville, FL', 'bits-accessible-forms' ) ),
            array( 'value' => 'fort_worth', 'label' => __( 'Fort Worth, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'columbus', 'label' => __( 'Columbus, OH', 'bits-accessible-forms' ) ),
            array( 'value' => 'charlotte', 'label' => __( 'Charlotte, NC', 'bits-accessible-forms' ) ),
            array( 'value' => 'san_francisco', 'label' => __( 'San Francisco, CA', 'bits-accessible-forms' ) ),
            array( 'value' => 'indianapolis', 'label' => __( 'Indianapolis, IN', 'bits-accessible-forms' ) ),
            array( 'value' => 'seattle', 'label' => __( 'Seattle, WA', 'bits-accessible-forms' ) ),
            array( 'value' => 'denver', 'label' => __( 'Denver, CO', 'bits-accessible-forms' ) ),
            array( 'value' => 'washington', 'label' => __( 'Washington, DC', 'bits-accessible-forms' ) ),
            array( 'value' => 'boston', 'label' => __( 'Boston, MA', 'bits-accessible-forms' ) ),
            array( 'value' => 'el_paso', 'label' => __( 'El Paso, TX', 'bits-accessible-forms' ) ),
            array( 'value' => 'nashville', 'label' => __( 'Nashville, TN', 'bits-accessible-forms' ) ),
            array( 'value' => 'detroit', 'label' => __( 'Detroit, MI', 'bits-accessible-forms' ) ),
            array( 'value' => 'portland', 'label' => __( 'Portland, OR', 'bits-accessible-forms' ) ),
            array( 'value' => 'memphis', 'label' => __( 'Memphis, TN', 'bits-accessible-forms' ) ),
            array( 'value' => 'oklahoma_city', 'label' => __( 'Oklahoma City, OK', 'bits-accessible-forms' ) ),
            array( 'value' => 'las_vegas', 'label' => __( 'Las Vegas, NV', 'bits-accessible-forms' ) ),
            array( 'value' => 'louisville', 'label' => __( 'Louisville, KY', 'bits-accessible-forms' ) ),
            array( 'value' => 'baltimore', 'label' => __( 'Baltimore, MD', 'bits-accessible-forms' ) ),
            array( 'value' => 'milwaukee', 'label' => __( 'Milwaukee, WI', 'bits-accessible-forms' ) ),
            array( 'value' => 'albuquerque', 'label' => __( 'Albuquerque, NM', 'bits-accessible-forms' ) ),
            array( 'value' => 'tucson', 'label' => __( 'Tucson, AZ', 'bits-accessible-forms' ) ),
            array( 'value' => 'fresno', 'label' => __( 'Fresno, CA', 'bits-accessible-forms' ) ),
            array( 'value' => 'atlanta', 'label' => __( 'Atlanta, GA', 'bits-accessible-forms' ) ),
            array( 'value' => 'miami', 'label' => __( 'Miami, FL', 'bits-accessible-forms' ) ),
            array( 'value' => 'new_orleans', 'label' => __( 'New Orleans, LA', 'bits-accessible-forms' ) ),
            array( 'value' => 'cleveland', 'label' => __( 'Cleveland, OH', 'bits-accessible-forms' ) ),
            array( 'value' => 'pittsburgh', 'label' => __( 'Pittsburgh, PA', 'bits-accessible-forms' ) ),
            array( 'value' => 'minneapolis', 'label' => __( 'Minneapolis, MN', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get world cities (major)
     *
     * @return array World cities.
     */
    public static function get_cities_world() {
        return array(
            array( 'value' => 'tokyo', 'label' => __( 'Tokyo, Japan', 'bits-accessible-forms' ) ),
            array( 'value' => 'delhi', 'label' => __( 'Delhi, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'shanghai', 'label' => __( 'Shanghai, China', 'bits-accessible-forms' ) ),
            array( 'value' => 'sao_paulo', 'label' => __( 'São Paulo, Brazil', 'bits-accessible-forms' ) ),
            array( 'value' => 'mexico_city', 'label' => __( 'Mexico City, Mexico', 'bits-accessible-forms' ) ),
            array( 'value' => 'cairo', 'label' => __( 'Cairo, Egypt', 'bits-accessible-forms' ) ),
            array( 'value' => 'mumbai', 'label' => __( 'Mumbai, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'beijing', 'label' => __( 'Beijing, China', 'bits-accessible-forms' ) ),
            array( 'value' => 'dhaka', 'label' => __( 'Dhaka, Bangladesh', 'bits-accessible-forms' ) ),
            array( 'value' => 'osaka', 'label' => __( 'Osaka, Japan', 'bits-accessible-forms' ) ),
            array( 'value' => 'new_york', 'label' => __( 'New York, USA', 'bits-accessible-forms' ) ),
            array( 'value' => 'karachi', 'label' => __( 'Karachi, Pakistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'buenos_aires', 'label' => __( 'Buenos Aires, Argentina', 'bits-accessible-forms' ) ),
            array( 'value' => 'istanbul', 'label' => __( 'Istanbul, Turkey', 'bits-accessible-forms' ) ),
            array( 'value' => 'kolkata', 'label' => __( 'Kolkata, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'manila', 'label' => __( 'Manila, Philippines', 'bits-accessible-forms' ) ),
            array( 'value' => 'lagos', 'label' => __( 'Lagos, Nigeria', 'bits-accessible-forms' ) ),
            array( 'value' => 'rio_de_janeiro', 'label' => __( 'Rio de Janeiro, Brazil', 'bits-accessible-forms' ) ),
            array( 'value' => 'tianjin', 'label' => __( 'Tianjin, China', 'bits-accessible-forms' ) ),
            array( 'value' => 'kinshasa', 'label' => __( 'Kinshasa, DR Congo', 'bits-accessible-forms' ) ),
            array( 'value' => 'guangzhou', 'label' => __( 'Guangzhou, China', 'bits-accessible-forms' ) ),
            array( 'value' => 'los_angeles', 'label' => __( 'Los Angeles, USA', 'bits-accessible-forms' ) ),
            array( 'value' => 'moscow', 'label' => __( 'Moscow, Russia', 'bits-accessible-forms' ) ),
            array( 'value' => 'shenzhen', 'label' => __( 'Shenzhen, China', 'bits-accessible-forms' ) ),
            array( 'value' => 'lahore', 'label' => __( 'Lahore, Pakistan', 'bits-accessible-forms' ) ),
            array( 'value' => 'bangalore', 'label' => __( 'Bangalore, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'paris', 'label' => __( 'Paris, France', 'bits-accessible-forms' ) ),
            array( 'value' => 'bogota', 'label' => __( 'Bogotá, Colombia', 'bits-accessible-forms' ) ),
            array( 'value' => 'jakarta', 'label' => __( 'Jakarta, Indonesia', 'bits-accessible-forms' ) ),
            array( 'value' => 'chennai', 'label' => __( 'Chennai, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'lima', 'label' => __( 'Lima, Peru', 'bits-accessible-forms' ) ),
            array( 'value' => 'bangkok', 'label' => __( 'Bangkok, Thailand', 'bits-accessible-forms' ) ),
            array( 'value' => 'seoul', 'label' => __( 'Seoul, South Korea', 'bits-accessible-forms' ) ),
            array( 'value' => 'hyderabad', 'label' => __( 'Hyderabad, India', 'bits-accessible-forms' ) ),
            array( 'value' => 'london', 'label' => __( 'London, UK', 'bits-accessible-forms' ) ),
            array( 'value' => 'tehran', 'label' => __( 'Tehran, Iran', 'bits-accessible-forms' ) ),
            array( 'value' => 'hong_kong', 'label' => __( 'Hong Kong', 'bits-accessible-forms' ) ),
            array( 'value' => 'ho_chi_minh', 'label' => __( 'Ho Chi Minh City, Vietnam', 'bits-accessible-forms' ) ),
            array( 'value' => 'singapore', 'label' => __( 'Singapore', 'bits-accessible-forms' ) ),
            array( 'value' => 'sydney', 'label' => __( 'Sydney, Australia', 'bits-accessible-forms' ) ),
            array( 'value' => 'toronto', 'label' => __( 'Toronto, Canada', 'bits-accessible-forms' ) ),
            array( 'value' => 'berlin', 'label' => __( 'Berlin, Germany', 'bits-accessible-forms' ) ),
            array( 'value' => 'madrid', 'label' => __( 'Madrid, Spain', 'bits-accessible-forms' ) ),
            array( 'value' => 'rome', 'label' => __( 'Rome, Italy', 'bits-accessible-forms' ) ),
            array( 'value' => 'amsterdam', 'label' => __( 'Amsterdam, Netherlands', 'bits-accessible-forms' ) ),
            array( 'value' => 'dubai', 'label' => __( 'Dubai, UAE', 'bits-accessible-forms' ) ),
            array( 'value' => 'cape_town', 'label' => __( 'Cape Town, South Africa', 'bits-accessible-forms' ) ),
            array( 'value' => 'melbourne', 'label' => __( 'Melbourne, Australia', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get appointment types
     *
     * @return array Appointment types.
     */
    public static function get_appointment_types() {
        return array(
            array( 'value' => 'consultation', 'label' => __( 'Consultation', 'bits-accessible-forms' ) ),
            array( 'value' => 'follow_up', 'label' => __( 'Follow-up', 'bits-accessible-forms' ) ),
            array( 'value' => 'initial', 'label' => __( 'Initial Visit', 'bits-accessible-forms' ) ),
            array( 'value' => 'checkup', 'label' => __( 'Check-up', 'bits-accessible-forms' ) ),
            array( 'value' => 'emergency', 'label' => __( 'Emergency', 'bits-accessible-forms' ) ),
            array( 'value' => 'procedure', 'label' => __( 'Procedure', 'bits-accessible-forms' ) ),
            array( 'value' => 'screening', 'label' => __( 'Screening', 'bits-accessible-forms' ) ),
            array( 'value' => 'vaccination', 'label' => __( 'Vaccination', 'bits-accessible-forms' ) ),
            array( 'value' => 'lab_work', 'label' => __( 'Lab Work', 'bits-accessible-forms' ) ),
            array( 'value' => 'imaging', 'label' => __( 'Imaging/X-Ray', 'bits-accessible-forms' ) ),
            array( 'value' => 'telehealth', 'label' => __( 'Telehealth/Virtual', 'bits-accessible-forms' ) ),
            array( 'value' => 'therapy', 'label' => __( 'Therapy Session', 'bits-accessible-forms' ) ),
            array( 'value' => 'dental', 'label' => __( 'Dental', 'bits-accessible-forms' ) ),
            array( 'value' => 'eye_exam', 'label' => __( 'Eye Exam', 'bits-accessible-forms' ) ),
            array( 'value' => 'physical', 'label' => __( 'Physical Exam', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get service categories
     *
     * @return array Service categories.
     */
    public static function get_service_categories() {
        return array(
            array( 'value' => 'automotive', 'label' => __( 'Automotive', 'bits-accessible-forms' ) ),
            array( 'value' => 'beauty', 'label' => __( 'Beauty & Spa', 'bits-accessible-forms' ) ),
            array( 'value' => 'cleaning', 'label' => __( 'Cleaning', 'bits-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'bits-accessible-forms' ) ),
            array( 'value' => 'delivery', 'label' => __( 'Delivery', 'bits-accessible-forms' ) ),
            array( 'value' => 'education', 'label' => __( 'Education & Tutoring', 'bits-accessible-forms' ) ),
            array( 'value' => 'electrical', 'label' => __( 'Electrical', 'bits-accessible-forms' ) ),
            array( 'value' => 'events', 'label' => __( 'Events & Entertainment', 'bits-accessible-forms' ) ),
            array( 'value' => 'financial', 'label' => __( 'Financial', 'bits-accessible-forms' ) ),
            array( 'value' => 'fitness', 'label' => __( 'Fitness & Training', 'bits-accessible-forms' ) ),
            array( 'value' => 'handyman', 'label' => __( 'Handyman', 'bits-accessible-forms' ) ),
            array( 'value' => 'healthcare', 'label' => __( 'Healthcare', 'bits-accessible-forms' ) ),
            array( 'value' => 'home_improvement', 'label' => __( 'Home Improvement', 'bits-accessible-forms' ) ),
            array( 'value' => 'landscaping', 'label' => __( 'Landscaping', 'bits-accessible-forms' ) ),
            array( 'value' => 'legal', 'label' => __( 'Legal', 'bits-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing', 'bits-accessible-forms' ) ),
            array( 'value' => 'moving', 'label' => __( 'Moving & Storage', 'bits-accessible-forms' ) ),
            array( 'value' => 'pest_control', 'label' => __( 'Pest Control', 'bits-accessible-forms' ) ),
            array( 'value' => 'pet_services', 'label' => __( 'Pet Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'photography', 'label' => __( 'Photography', 'bits-accessible-forms' ) ),
            array( 'value' => 'plumbing', 'label' => __( 'Plumbing', 'bits-accessible-forms' ) ),
            array( 'value' => 'real_estate', 'label' => __( 'Real Estate', 'bits-accessible-forms' ) ),
            array( 'value' => 'repair', 'label' => __( 'Repair Services', 'bits-accessible-forms' ) ),
            array( 'value' => 'security', 'label' => __( 'Security', 'bits-accessible-forms' ) ),
            array( 'value' => 'technology', 'label' => __( 'Technology & IT', 'bits-accessible-forms' ) ),
            array( 'value' => 'transportation', 'label' => __( 'Transportation', 'bits-accessible-forms' ) ),
            array( 'value' => 'travel', 'label' => __( 'Travel', 'bits-accessible-forms' ) ),
            array( 'value' => 'writing', 'label' => __( 'Writing & Translation', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get project types
     *
     * @return array Project types.
     */
    public static function get_project_types() {
        return array(
            array( 'value' => 'new_build', 'label' => __( 'New Build', 'bits-accessible-forms' ) ),
            array( 'value' => 'renovation', 'label' => __( 'Renovation', 'bits-accessible-forms' ) ),
            array( 'value' => 'repair', 'label' => __( 'Repair', 'bits-accessible-forms' ) ),
            array( 'value' => 'maintenance', 'label' => __( 'Maintenance', 'bits-accessible-forms' ) ),
            array( 'value' => 'consulting', 'label' => __( 'Consulting', 'bits-accessible-forms' ) ),
            array( 'value' => 'research', 'label' => __( 'Research', 'bits-accessible-forms' ) ),
            array( 'value' => 'development', 'label' => __( 'Development', 'bits-accessible-forms' ) ),
            array( 'value' => 'design', 'label' => __( 'Design', 'bits-accessible-forms' ) ),
            array( 'value' => 'implementation', 'label' => __( 'Implementation', 'bits-accessible-forms' ) ),
            array( 'value' => 'migration', 'label' => __( 'Migration', 'bits-accessible-forms' ) ),
            array( 'value' => 'integration', 'label' => __( 'Integration', 'bits-accessible-forms' ) ),
            array( 'value' => 'training', 'label' => __( 'Training', 'bits-accessible-forms' ) ),
            array( 'value' => 'audit', 'label' => __( 'Audit', 'bits-accessible-forms' ) ),
            array( 'value' => 'assessment', 'label' => __( 'Assessment', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get budget ranges
     *
     * @return array Budget ranges.
     */
    public static function get_budget_ranges() {
        return array(
            array( 'value' => 'under_1k', 'label' => __( 'Under $1,000', 'bits-accessible-forms' ) ),
            array( 'value' => '1k_5k', 'label' => __( '$1,000 - $5,000', 'bits-accessible-forms' ) ),
            array( 'value' => '5k_10k', 'label' => __( '$5,000 - $10,000', 'bits-accessible-forms' ) ),
            array( 'value' => '10k_25k', 'label' => __( '$10,000 - $25,000', 'bits-accessible-forms' ) ),
            array( 'value' => '25k_50k', 'label' => __( '$25,000 - $50,000', 'bits-accessible-forms' ) ),
            array( 'value' => '50k_100k', 'label' => __( '$50,000 - $100,000', 'bits-accessible-forms' ) ),
            array( 'value' => '100k_250k', 'label' => __( '$100,000 - $250,000', 'bits-accessible-forms' ) ),
            array( 'value' => '250k_500k', 'label' => __( '$250,000 - $500,000', 'bits-accessible-forms' ) ),
            array( 'value' => '500k_1m', 'label' => __( '$500,000 - $1,000,000', 'bits-accessible-forms' ) ),
            array( 'value' => 'over_1m', 'label' => __( 'Over $1,000,000', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get timeline options
     *
     * @return array Timelines.
     */
    public static function get_timelines() {
        return array(
            array( 'value' => 'asap', 'label' => __( 'As soon as possible', 'bits-accessible-forms' ) ),
            array( 'value' => '1_week', 'label' => __( 'Within 1 week', 'bits-accessible-forms' ) ),
            array( 'value' => '2_weeks', 'label' => __( 'Within 2 weeks', 'bits-accessible-forms' ) ),
            array( 'value' => '1_month', 'label' => __( 'Within 1 month', 'bits-accessible-forms' ) ),
            array( 'value' => '2_3_months', 'label' => __( '2-3 months', 'bits-accessible-forms' ) ),
            array( 'value' => '3_6_months', 'label' => __( '3-6 months', 'bits-accessible-forms' ) ),
            array( 'value' => '6_12_months', 'label' => __( '6-12 months', 'bits-accessible-forms' ) ),
            array( 'value' => 'over_1_year', 'label' => __( 'More than 1 year', 'bits-accessible-forms' ) ),
            array( 'value' => 'flexible', 'label' => __( 'Flexible', 'bits-accessible-forms' ) ),
            array( 'value' => 'not_sure', 'label' => __( 'Not sure yet', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get company size options
     *
     * @return array Company sizes.
     */
    public static function get_company_sizes() {
        return array(
            array( 'value' => '1', 'label' => __( 'Self-employed', 'bits-accessible-forms' ) ),
            array( 'value' => '2_10', 'label' => __( '2-10 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '11_50', 'label' => __( '11-50 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '51_200', 'label' => __( '51-200 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '201_500', 'label' => __( '201-500 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '501_1000', 'label' => __( '501-1,000 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '1001_5000', 'label' => __( '1,001-5,000 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '5001_10000', 'label' => __( '5,001-10,000 employees', 'bits-accessible-forms' ) ),
            array( 'value' => '10001_plus', 'label' => __( '10,001+ employees', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get annual revenue options
     *
     * @return array Annual revenue ranges.
     */
    public static function get_annual_revenue() {
        return array(
            array( 'value' => 'under_100k', 'label' => __( 'Under $100,000', 'bits-accessible-forms' ) ),
            array( 'value' => '100k_500k', 'label' => __( '$100,000 - $500,000', 'bits-accessible-forms' ) ),
            array( 'value' => '500k_1m', 'label' => __( '$500,000 - $1 million', 'bits-accessible-forms' ) ),
            array( 'value' => '1m_5m', 'label' => __( '$1 million - $5 million', 'bits-accessible-forms' ) ),
            array( 'value' => '5m_10m', 'label' => __( '$5 million - $10 million', 'bits-accessible-forms' ) ),
            array( 'value' => '10m_50m', 'label' => __( '$10 million - $50 million', 'bits-accessible-forms' ) ),
            array( 'value' => '50m_100m', 'label' => __( '$50 million - $100 million', 'bits-accessible-forms' ) ),
            array( 'value' => '100m_500m', 'label' => __( '$100 million - $500 million', 'bits-accessible-forms' ) ),
            array( 'value' => '500m_1b', 'label' => __( '$500 million - $1 billion', 'bits-accessible-forms' ) ),
            array( 'value' => 'over_1b', 'label' => __( 'Over $1 billion', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get lead source options
     *
     * @return array Lead sources.
     */
    public static function get_lead_sources() {
        return array(
            array( 'value' => 'google', 'label' => __( 'Google Search', 'bits-accessible-forms' ) ),
            array( 'value' => 'bing', 'label' => __( 'Bing Search', 'bits-accessible-forms' ) ),
            array( 'value' => 'facebook', 'label' => __( 'Facebook', 'bits-accessible-forms' ) ),
            array( 'value' => 'instagram', 'label' => __( 'Instagram', 'bits-accessible-forms' ) ),
            array( 'value' => 'twitter', 'label' => __( 'Twitter/X', 'bits-accessible-forms' ) ),
            array( 'value' => 'linkedin', 'label' => __( 'LinkedIn', 'bits-accessible-forms' ) ),
            array( 'value' => 'youtube', 'label' => __( 'YouTube', 'bits-accessible-forms' ) ),
            array( 'value' => 'tiktok', 'label' => __( 'TikTok', 'bits-accessible-forms' ) ),
            array( 'value' => 'pinterest', 'label' => __( 'Pinterest', 'bits-accessible-forms' ) ),
            array( 'value' => 'reddit', 'label' => __( 'Reddit', 'bits-accessible-forms' ) ),
            array( 'value' => 'referral', 'label' => __( 'Referral/Word of mouth', 'bits-accessible-forms' ) ),
            array( 'value' => 'email', 'label' => __( 'Email marketing', 'bits-accessible-forms' ) ),
            array( 'value' => 'blog', 'label' => __( 'Blog/Article', 'bits-accessible-forms' ) ),
            array( 'value' => 'podcast', 'label' => __( 'Podcast', 'bits-accessible-forms' ) ),
            array( 'value' => 'webinar', 'label' => __( 'Webinar', 'bits-accessible-forms' ) ),
            array( 'value' => 'conference', 'label' => __( 'Conference/Event', 'bits-accessible-forms' ) ),
            array( 'value' => 'print_ad', 'label' => __( 'Print advertisement', 'bits-accessible-forms' ) ),
            array( 'value' => 'tv_radio', 'label' => __( 'TV/Radio', 'bits-accessible-forms' ) ),
            array( 'value' => 'billboard', 'label' => __( 'Billboard/Outdoor', 'bits-accessible-forms' ) ),
            array( 'value' => 'direct_mail', 'label' => __( 'Direct mail', 'bits-accessible-forms' ) ),
            array( 'value' => 'cold_call', 'label' => __( 'Cold call', 'bits-accessible-forms' ) ),
            array( 'value' => 'partner', 'label' => __( 'Partner/Affiliate', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get contact preference options
     *
     * @return array Contact preferences.
     */
    public static function get_contact_preferences() {
        return array(
            array( 'value' => 'email', 'label' => __( 'Email', 'bits-accessible-forms' ) ),
            array( 'value' => 'phone', 'label' => __( 'Phone call', 'bits-accessible-forms' ) ),
            array( 'value' => 'sms', 'label' => __( 'Text message (SMS)', 'bits-accessible-forms' ) ),
            array( 'value' => 'whatsapp', 'label' => __( 'WhatsApp', 'bits-accessible-forms' ) ),
            array( 'value' => 'video', 'label' => __( 'Video call', 'bits-accessible-forms' ) ),
            array( 'value' => 'mail', 'label' => __( 'Postal mail', 'bits-accessible-forms' ) ),
            array( 'value' => 'in_person', 'label' => __( 'In person', 'bits-accessible-forms' ) ),
            array( 'value' => 'no_preference', 'label' => __( 'No preference', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get preferred contact time options
     *
     * @return array Preferred contact times.
     */
    public static function get_contact_times() {
        return array(
            array( 'value' => 'morning', 'label' => __( 'Morning (8am - 12pm)', 'bits-accessible-forms' ) ),
            array( 'value' => 'afternoon', 'label' => __( 'Afternoon (12pm - 5pm)', 'bits-accessible-forms' ) ),
            array( 'value' => 'evening', 'label' => __( 'Evening (5pm - 8pm)', 'bits-accessible-forms' ) ),
            array( 'value' => 'weekday', 'label' => __( 'Weekdays only', 'bits-accessible-forms' ) ),
            array( 'value' => 'weekend', 'label' => __( 'Weekends only', 'bits-accessible-forms' ) ),
            array( 'value' => 'anytime', 'label' => __( 'Anytime', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get relationship status options
     *
     * @return array Relationship statuses.
     */
    public static function get_relationship_statuses() {
        return array(
            array( 'value' => 'single', 'label' => __( 'Single', 'bits-accessible-forms' ) ),
            array( 'value' => 'in_relationship', 'label' => __( 'In a relationship', 'bits-accessible-forms' ) ),
            array( 'value' => 'engaged', 'label' => __( 'Engaged', 'bits-accessible-forms' ) ),
            array( 'value' => 'married', 'label' => __( 'Married', 'bits-accessible-forms' ) ),
            array( 'value' => 'domestic', 'label' => __( 'Domestic partnership', 'bits-accessible-forms' ) ),
            array( 'value' => 'separated', 'label' => __( 'Separated', 'bits-accessible-forms' ) ),
            array( 'value' => 'divorced', 'label' => __( 'Divorced', 'bits-accessible-forms' ) ),
            array( 'value' => 'widowed', 'label' => __( 'Widowed', 'bits-accessible-forms' ) ),
            array( 'value' => 'prefer_not', 'label' => __( 'Prefer not to say', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get pet types
     *
     * @return array Pet types.
     */
    public static function get_pet_types() {
        return array(
            array( 'value' => 'dog', 'label' => __( 'Dog', 'bits-accessible-forms' ) ),
            array( 'value' => 'cat', 'label' => __( 'Cat', 'bits-accessible-forms' ) ),
            array( 'value' => 'bird', 'label' => __( 'Bird', 'bits-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish', 'bits-accessible-forms' ) ),
            array( 'value' => 'rabbit', 'label' => __( 'Rabbit', 'bits-accessible-forms' ) ),
            array( 'value' => 'hamster', 'label' => __( 'Hamster', 'bits-accessible-forms' ) ),
            array( 'value' => 'guinea_pig', 'label' => __( 'Guinea pig', 'bits-accessible-forms' ) ),
            array( 'value' => 'reptile', 'label' => __( 'Reptile', 'bits-accessible-forms' ) ),
            array( 'value' => 'turtle', 'label' => __( 'Turtle/Tortoise', 'bits-accessible-forms' ) ),
            array( 'value' => 'horse', 'label' => __( 'Horse', 'bits-accessible-forms' ) ),
            array( 'value' => 'ferret', 'label' => __( 'Ferret', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
            array( 'value' => 'none', 'label' => __( 'No pets', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get dietary restrictions
     *
     * @return array Dietary restrictions.
     */
    public static function get_dietary_restrictions() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'bits-accessible-forms' ) ),
            array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'vegan', 'label' => __( 'Vegan', 'bits-accessible-forms' ) ),
            array( 'value' => 'pescatarian', 'label' => __( 'Pescatarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'gluten_free', 'label' => __( 'Gluten-free', 'bits-accessible-forms' ) ),
            array( 'value' => 'dairy_free', 'label' => __( 'Dairy-free', 'bits-accessible-forms' ) ),
            array( 'value' => 'nut_free', 'label' => __( 'Nut-free', 'bits-accessible-forms' ) ),
            array( 'value' => 'kosher', 'label' => __( 'Kosher', 'bits-accessible-forms' ) ),
            array( 'value' => 'halal', 'label' => __( 'Halal', 'bits-accessible-forms' ) ),
            array( 'value' => 'keto', 'label' => __( 'Keto', 'bits-accessible-forms' ) ),
            array( 'value' => 'paleo', 'label' => __( 'Paleo', 'bits-accessible-forms' ) ),
            array( 'value' => 'low_sodium', 'label' => __( 'Low sodium', 'bits-accessible-forms' ) ),
            array( 'value' => 'low_sugar', 'label' => __( 'Low sugar/Diabetic', 'bits-accessible-forms' ) ),
            array( 'value' => 'raw', 'label' => __( 'Raw food', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get allergens
     *
     * @return array Allergens.
     */
    public static function get_allergens() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'bits-accessible-forms' ) ),
            array( 'value' => 'peanuts', 'label' => __( 'Peanuts', 'bits-accessible-forms' ) ),
            array( 'value' => 'tree_nuts', 'label' => __( 'Tree nuts', 'bits-accessible-forms' ) ),
            array( 'value' => 'milk', 'label' => __( 'Milk/Dairy', 'bits-accessible-forms' ) ),
            array( 'value' => 'eggs', 'label' => __( 'Eggs', 'bits-accessible-forms' ) ),
            array( 'value' => 'wheat', 'label' => __( 'Wheat', 'bits-accessible-forms' ) ),
            array( 'value' => 'soy', 'label' => __( 'Soy', 'bits-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish', 'bits-accessible-forms' ) ),
            array( 'value' => 'shellfish', 'label' => __( 'Shellfish', 'bits-accessible-forms' ) ),
            array( 'value' => 'sesame', 'label' => __( 'Sesame', 'bits-accessible-forms' ) ),
            array( 'value' => 'gluten', 'label' => __( 'Gluten', 'bits-accessible-forms' ) ),
            array( 'value' => 'sulfites', 'label' => __( 'Sulfites', 'bits-accessible-forms' ) ),
            array( 'value' => 'mustard', 'label' => __( 'Mustard', 'bits-accessible-forms' ) ),
            array( 'value' => 'celery', 'label' => __( 'Celery', 'bits-accessible-forms' ) ),
            array( 'value' => 'lupin', 'label' => __( 'Lupin', 'bits-accessible-forms' ) ),
            array( 'value' => 'mollusks', 'label' => __( 'Mollusks', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get meal preferences
     *
     * @return array Meal preferences.
     */
    public static function get_meal_preferences() {
        return array(
            array( 'value' => 'meat', 'label' => __( 'Meat/Chicken', 'bits-accessible-forms' ) ),
            array( 'value' => 'fish', 'label' => __( 'Fish/Seafood', 'bits-accessible-forms' ) ),
            array( 'value' => 'vegetarian', 'label' => __( 'Vegetarian', 'bits-accessible-forms' ) ),
            array( 'value' => 'vegan', 'label' => __( 'Vegan', 'bits-accessible-forms' ) ),
            array( 'value' => 'kids', 'label' => __( 'Kids meal', 'bits-accessible-forms' ) ),
            array( 'value' => 'no_preference', 'label' => __( 'No preference', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get T-shirt sizes (standard)
     *
     * @return array T-shirt sizes.
     */
    public static function get_tshirt_sizes() {
        return array(
            array( 'value' => 'xs', 'label' => __( 'XS (Extra Small)', 'bits-accessible-forms' ) ),
            array( 'value' => 's', 'label' => __( 'S (Small)', 'bits-accessible-forms' ) ),
            array( 'value' => 'm', 'label' => __( 'M (Medium)', 'bits-accessible-forms' ) ),
            array( 'value' => 'l', 'label' => __( 'L (Large)', 'bits-accessible-forms' ) ),
            array( 'value' => 'xl', 'label' => __( 'XL (Extra Large)', 'bits-accessible-forms' ) ),
            array( 'value' => '2xl', 'label' => __( '2XL', 'bits-accessible-forms' ) ),
            array( 'value' => '3xl', 'label' => __( '3XL', 'bits-accessible-forms' ) ),
            array( 'value' => '4xl', 'label' => __( '4XL', 'bits-accessible-forms' ) ),
            array( 'value' => '5xl', 'label' => __( '5XL', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get accessibility needs
     *
     * @return array Accessibility needs.
     */
    public static function get_accessibility_needs() {
        return array(
            array( 'value' => 'none', 'label' => __( 'None', 'bits-accessible-forms' ) ),
            array( 'value' => 'wheelchair', 'label' => __( 'Wheelchair access', 'bits-accessible-forms' ) ),
            array( 'value' => 'mobility', 'label' => __( 'Mobility assistance', 'bits-accessible-forms' ) ),
            array( 'value' => 'visual', 'label' => __( 'Visual impairment', 'bits-accessible-forms' ) ),
            array( 'value' => 'hearing', 'label' => __( 'Hearing impairment', 'bits-accessible-forms' ) ),
            array( 'value' => 'sign_language', 'label' => __( 'Sign language interpreter', 'bits-accessible-forms' ) ),
            array( 'value' => 'service_animal', 'label' => __( 'Service animal', 'bits-accessible-forms' ) ),
            array( 'value' => 'large_print', 'label' => __( 'Large print materials', 'bits-accessible-forms' ) ),
            array( 'value' => 'braille', 'label' => __( 'Braille materials', 'bits-accessible-forms' ) ),
            array( 'value' => 'captioning', 'label' => __( 'Captioning/Subtitles', 'bits-accessible-forms' ) ),
            array( 'value' => 'audio_description', 'label' => __( 'Audio description', 'bits-accessible-forms' ) ),
            array( 'value' => 'quiet_space', 'label' => __( 'Quiet/Low-stimulation space', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get payment methods
     *
     * @return array Payment methods.
     */
    public static function get_payment_methods() {
        return array(
            array( 'value' => 'credit_card', 'label' => __( 'Credit card', 'bits-accessible-forms' ) ),
            array( 'value' => 'debit_card', 'label' => __( 'Debit card', 'bits-accessible-forms' ) ),
            array( 'value' => 'paypal', 'label' => __( 'PayPal', 'bits-accessible-forms' ) ),
            array( 'value' => 'apple_pay', 'label' => __( 'Apple Pay', 'bits-accessible-forms' ) ),
            array( 'value' => 'google_pay', 'label' => __( 'Google Pay', 'bits-accessible-forms' ) ),
            array( 'value' => 'venmo', 'label' => __( 'Venmo', 'bits-accessible-forms' ) ),
            array( 'value' => 'bank_transfer', 'label' => __( 'Bank transfer', 'bits-accessible-forms' ) ),
            array( 'value' => 'wire_transfer', 'label' => __( 'Wire transfer', 'bits-accessible-forms' ) ),
            array( 'value' => 'check', 'label' => __( 'Check', 'bits-accessible-forms' ) ),
            array( 'value' => 'cash', 'label' => __( 'Cash', 'bits-accessible-forms' ) ),
            array( 'value' => 'crypto', 'label' => __( 'Cryptocurrency', 'bits-accessible-forms' ) ),
            array( 'value' => 'invoice', 'label' => __( 'Invoice/Net terms', 'bits-accessible-forms' ) ),
            array( 'value' => 'financing', 'label' => __( 'Financing', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get shipping methods
     *
     * @return array Shipping methods.
     */
    public static function get_shipping_methods() {
        return array(
            array( 'value' => 'standard', 'label' => __( 'Standard shipping', 'bits-accessible-forms' ) ),
            array( 'value' => 'express', 'label' => __( 'Express shipping', 'bits-accessible-forms' ) ),
            array( 'value' => 'overnight', 'label' => __( 'Overnight shipping', 'bits-accessible-forms' ) ),
            array( 'value' => 'two_day', 'label' => __( '2-day shipping', 'bits-accessible-forms' ) ),
            array( 'value' => 'same_day', 'label' => __( 'Same-day delivery', 'bits-accessible-forms' ) ),
            array( 'value' => 'pickup', 'label' => __( 'In-store pickup', 'bits-accessible-forms' ) ),
            array( 'value' => 'locker', 'label' => __( 'Locker pickup', 'bits-accessible-forms' ) ),
            array( 'value' => 'curbside', 'label' => __( 'Curbside pickup', 'bits-accessible-forms' ) ),
            array( 'value' => 'freight', 'label' => __( 'Freight', 'bits-accessible-forms' ) ),
            array( 'value' => 'international', 'label' => __( 'International', 'bits-accessible-forms' ) ),
            array( 'value' => 'digital', 'label' => __( 'Digital delivery', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get subscription frequencies
     *
     * @return array Subscription frequencies.
     */
    public static function get_subscription_frequencies() {
        return array(
            array( 'value' => 'weekly', 'label' => __( 'Weekly', 'bits-accessible-forms' ) ),
            array( 'value' => 'biweekly', 'label' => __( 'Every 2 weeks', 'bits-accessible-forms' ) ),
            array( 'value' => 'monthly', 'label' => __( 'Monthly', 'bits-accessible-forms' ) ),
            array( 'value' => 'bimonthly', 'label' => __( 'Every 2 months', 'bits-accessible-forms' ) ),
            array( 'value' => 'quarterly', 'label' => __( 'Quarterly', 'bits-accessible-forms' ) ),
            array( 'value' => 'semiannually', 'label' => __( 'Every 6 months', 'bits-accessible-forms' ) ),
            array( 'value' => 'annually', 'label' => __( 'Annually', 'bits-accessible-forms' ) ),
            array( 'value' => 'one_time', 'label' => __( 'One-time (no subscription)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get property types
     *
     * @return array Property types.
     */
    public static function get_property_types() {
        return array(
            array( 'value' => 'house', 'label' => __( 'House', 'bits-accessible-forms' ) ),
            array( 'value' => 'apartment', 'label' => __( 'Apartment', 'bits-accessible-forms' ) ),
            array( 'value' => 'condo', 'label' => __( 'Condo/Condominium', 'bits-accessible-forms' ) ),
            array( 'value' => 'townhouse', 'label' => __( 'Townhouse', 'bits-accessible-forms' ) ),
            array( 'value' => 'duplex', 'label' => __( 'Duplex', 'bits-accessible-forms' ) ),
            array( 'value' => 'multi_family', 'label' => __( 'Multi-family', 'bits-accessible-forms' ) ),
            array( 'value' => 'mobile_home', 'label' => __( 'Mobile/Manufactured home', 'bits-accessible-forms' ) ),
            array( 'value' => 'land', 'label' => __( 'Land/Lot', 'bits-accessible-forms' ) ),
            array( 'value' => 'commercial', 'label' => __( 'Commercial', 'bits-accessible-forms' ) ),
            array( 'value' => 'industrial', 'label' => __( 'Industrial', 'bits-accessible-forms' ) ),
            array( 'value' => 'office', 'label' => __( 'Office', 'bits-accessible-forms' ) ),
            array( 'value' => 'retail', 'label' => __( 'Retail', 'bits-accessible-forms' ) ),
            array( 'value' => 'farm', 'label' => __( 'Farm/Ranch', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get number of bedrooms
     *
     * @return array Number of bedrooms.
     */
    public static function get_bedrooms() {
        return array(
            array( 'value' => 'studio', 'label' => __( 'Studio', 'bits-accessible-forms' ) ),
            array( 'value' => '1', 'label' => __( '1 bedroom', 'bits-accessible-forms' ) ),
            array( 'value' => '2', 'label' => __( '2 bedrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '3', 'label' => __( '3 bedrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '4', 'label' => __( '4 bedrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '5', 'label' => __( '5 bedrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '6_plus', 'label' => __( '6+ bedrooms', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get number of bathrooms
     *
     * @return array Number of bathrooms.
     */
    public static function get_bathrooms() {
        return array(
            array( 'value' => '1', 'label' => __( '1 bathroom', 'bits-accessible-forms' ) ),
            array( 'value' => '1.5', 'label' => __( '1.5 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '2', 'label' => __( '2 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '2.5', 'label' => __( '2.5 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '3', 'label' => __( '3 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '3.5', 'label' => __( '3.5 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '4', 'label' => __( '4 bathrooms', 'bits-accessible-forms' ) ),
            array( 'value' => '4_plus', 'label' => __( '4+ bathrooms', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get vehicle types
     *
     * @return array Vehicle types.
     */
    public static function get_vehicle_types() {
        return array(
            array( 'value' => 'sedan', 'label' => __( 'Sedan', 'bits-accessible-forms' ) ),
            array( 'value' => 'suv', 'label' => __( 'SUV', 'bits-accessible-forms' ) ),
            array( 'value' => 'crossover', 'label' => __( 'Crossover', 'bits-accessible-forms' ) ),
            array( 'value' => 'truck', 'label' => __( 'Truck', 'bits-accessible-forms' ) ),
            array( 'value' => 'van', 'label' => __( 'Van/Minivan', 'bits-accessible-forms' ) ),
            array( 'value' => 'coupe', 'label' => __( 'Coupe', 'bits-accessible-forms' ) ),
            array( 'value' => 'convertible', 'label' => __( 'Convertible', 'bits-accessible-forms' ) ),
            array( 'value' => 'hatchback', 'label' => __( 'Hatchback', 'bits-accessible-forms' ) ),
            array( 'value' => 'wagon', 'label' => __( 'Station wagon', 'bits-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports car', 'bits-accessible-forms' ) ),
            array( 'value' => 'luxury', 'label' => __( 'Luxury', 'bits-accessible-forms' ) ),
            array( 'value' => 'electric', 'label' => __( 'Electric', 'bits-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'bits-accessible-forms' ) ),
            array( 'value' => 'motorcycle', 'label' => __( 'Motorcycle', 'bits-accessible-forms' ) ),
            array( 'value' => 'rv', 'label' => __( 'RV/Camper', 'bits-accessible-forms' ) ),
            array( 'value' => 'boat', 'label' => __( 'Boat', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get fuel types
     *
     * @return array Fuel types.
     */
    public static function get_fuel_types() {
        return array(
            array( 'value' => 'gasoline', 'label' => __( 'Gasoline', 'bits-accessible-forms' ) ),
            array( 'value' => 'diesel', 'label' => __( 'Diesel', 'bits-accessible-forms' ) ),
            array( 'value' => 'electric', 'label' => __( 'Electric', 'bits-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid', 'bits-accessible-forms' ) ),
            array( 'value' => 'plug_in_hybrid', 'label' => __( 'Plug-in hybrid', 'bits-accessible-forms' ) ),
            array( 'value' => 'flex_fuel', 'label' => __( 'Flex fuel', 'bits-accessible-forms' ) ),
            array( 'value' => 'hydrogen', 'label' => __( 'Hydrogen', 'bits-accessible-forms' ) ),
            array( 'value' => 'natural_gas', 'label' => __( 'Natural gas', 'bits-accessible-forms' ) ),
            array( 'value' => 'propane', 'label' => __( 'Propane', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get software platforms
     *
     * @return array Software platforms.
     */
    public static function get_platforms() {
        return array(
            array( 'value' => 'windows', 'label' => __( 'Windows', 'bits-accessible-forms' ) ),
            array( 'value' => 'macos', 'label' => __( 'macOS', 'bits-accessible-forms' ) ),
            array( 'value' => 'linux', 'label' => __( 'Linux', 'bits-accessible-forms' ) ),
            array( 'value' => 'ios', 'label' => __( 'iOS', 'bits-accessible-forms' ) ),
            array( 'value' => 'android', 'label' => __( 'Android', 'bits-accessible-forms' ) ),
            array( 'value' => 'web', 'label' => __( 'Web/Browser', 'bits-accessible-forms' ) ),
            array( 'value' => 'chromeos', 'label' => __( 'ChromeOS', 'bits-accessible-forms' ) ),
            array( 'value' => 'cross_platform', 'label' => __( 'Cross-platform', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get device types
     *
     * @return array Device types.
     */
    public static function get_device_types() {
        return array(
            array( 'value' => 'desktop', 'label' => __( 'Desktop computer', 'bits-accessible-forms' ) ),
            array( 'value' => 'laptop', 'label' => __( 'Laptop', 'bits-accessible-forms' ) ),
            array( 'value' => 'tablet', 'label' => __( 'Tablet', 'bits-accessible-forms' ) ),
            array( 'value' => 'smartphone', 'label' => __( 'Smartphone', 'bits-accessible-forms' ) ),
            array( 'value' => 'smartwatch', 'label' => __( 'Smartwatch', 'bits-accessible-forms' ) ),
            array( 'value' => 'smart_tv', 'label' => __( 'Smart TV', 'bits-accessible-forms' ) ),
            array( 'value' => 'gaming_console', 'label' => __( 'Gaming console', 'bits-accessible-forms' ) ),
            array( 'value' => 'smart_speaker', 'label' => __( 'Smart speaker', 'bits-accessible-forms' ) ),
            array( 'value' => 'ereader', 'label' => __( 'E-reader', 'bits-accessible-forms' ) ),
            array( 'value' => 'vr_headset', 'label' => __( 'VR headset', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get browser types
     *
     * @return array Browser types.
     */
    public static function get_browsers() {
        return array(
            array( 'value' => 'chrome', 'label' => __( 'Google Chrome', 'bits-accessible-forms' ) ),
            array( 'value' => 'firefox', 'label' => __( 'Mozilla Firefox', 'bits-accessible-forms' ) ),
            array( 'value' => 'safari', 'label' => __( 'Safari', 'bits-accessible-forms' ) ),
            array( 'value' => 'edge', 'label' => __( 'Microsoft Edge', 'bits-accessible-forms' ) ),
            array( 'value' => 'opera', 'label' => __( 'Opera', 'bits-accessible-forms' ) ),
            array( 'value' => 'brave', 'label' => __( 'Brave', 'bits-accessible-forms' ) ),
            array( 'value' => 'vivaldi', 'label' => __( 'Vivaldi', 'bits-accessible-forms' ) ),
            array( 'value' => 'samsung', 'label' => __( 'Samsung Internet', 'bits-accessible-forms' ) ),
            array( 'value' => 'ie', 'label' => __( 'Internet Explorer', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get skill levels
     *
     * @return array Skill levels.
     */
    public static function get_skill_levels() {
        return array(
            array( 'value' => 'beginner', 'label' => __( 'Beginner', 'bits-accessible-forms' ) ),
            array( 'value' => 'elementary', 'label' => __( 'Elementary', 'bits-accessible-forms' ) ),
            array( 'value' => 'intermediate', 'label' => __( 'Intermediate', 'bits-accessible-forms' ) ),
            array( 'value' => 'upper_intermediate', 'label' => __( 'Upper Intermediate', 'bits-accessible-forms' ) ),
            array( 'value' => 'advanced', 'label' => __( 'Advanced', 'bits-accessible-forms' ) ),
            array( 'value' => 'expert', 'label' => __( 'Expert', 'bits-accessible-forms' ) ),
            array( 'value' => 'native', 'label' => __( 'Native/Fluent', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get experience levels
     *
     * @return array Experience levels.
     */
    public static function get_experience_levels() {
        return array(
            array( 'value' => 'entry', 'label' => __( 'Entry level', 'bits-accessible-forms' ) ),
            array( 'value' => 'junior', 'label' => __( 'Junior', 'bits-accessible-forms' ) ),
            array( 'value' => 'mid', 'label' => __( 'Mid-level', 'bits-accessible-forms' ) ),
            array( 'value' => 'senior', 'label' => __( 'Senior', 'bits-accessible-forms' ) ),
            array( 'value' => 'lead', 'label' => __( 'Lead', 'bits-accessible-forms' ) ),
            array( 'value' => 'manager', 'label' => __( 'Manager', 'bits-accessible-forms' ) ),
            array( 'value' => 'director', 'label' => __( 'Director', 'bits-accessible-forms' ) ),
            array( 'value' => 'vp', 'label' => __( 'VP', 'bits-accessible-forms' ) ),
            array( 'value' => 'c_level', 'label' => __( 'C-Level', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get years of experience
     *
     * @return array Years of experience.
     */
    public static function get_years_experience() {
        return array(
            array( 'value' => 'none', 'label' => __( 'No experience', 'bits-accessible-forms' ) ),
            array( 'value' => 'less_1', 'label' => __( 'Less than 1 year', 'bits-accessible-forms' ) ),
            array( 'value' => '1_2', 'label' => __( '1-2 years', 'bits-accessible-forms' ) ),
            array( 'value' => '3_5', 'label' => __( '3-5 years', 'bits-accessible-forms' ) ),
            array( 'value' => '6_10', 'label' => __( '6-10 years', 'bits-accessible-forms' ) ),
            array( 'value' => '11_15', 'label' => __( '11-15 years', 'bits-accessible-forms' ) ),
            array( 'value' => '16_20', 'label' => __( '16-20 years', 'bits-accessible-forms' ) ),
            array( 'value' => 'over_20', 'label' => __( 'More than 20 years', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get notification preferences
     *
     * @return array Notification preferences.
     */
    public static function get_notification_preferences() {
        return array(
            array( 'value' => 'all', 'label' => __( 'All notifications', 'bits-accessible-forms' ) ),
            array( 'value' => 'important', 'label' => __( 'Important only', 'bits-accessible-forms' ) ),
            array( 'value' => 'marketing', 'label' => __( 'Marketing and promotions', 'bits-accessible-forms' ) ),
            array( 'value' => 'updates', 'label' => __( 'Product updates', 'bits-accessible-forms' ) ),
            array( 'value' => 'newsletter', 'label' => __( 'Newsletter', 'bits-accessible-forms' ) ),
            array( 'value' => 'account', 'label' => __( 'Account alerts only', 'bits-accessible-forms' ) ),
            array( 'value' => 'none', 'label' => __( 'No notifications', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (length)
     *
     * @return array Length units.
     */
    public static function get_units_length() {
        return array(
            array( 'value' => 'mm', 'label' => __( 'Millimeters (mm)', 'bits-accessible-forms' ) ),
            array( 'value' => 'cm', 'label' => __( 'Centimeters (cm)', 'bits-accessible-forms' ) ),
            array( 'value' => 'm', 'label' => __( 'Meters (m)', 'bits-accessible-forms' ) ),
            array( 'value' => 'km', 'label' => __( 'Kilometers (km)', 'bits-accessible-forms' ) ),
            array( 'value' => 'in', 'label' => __( 'Inches (in)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ft', 'label' => __( 'Feet (ft)', 'bits-accessible-forms' ) ),
            array( 'value' => 'yd', 'label' => __( 'Yards (yd)', 'bits-accessible-forms' ) ),
            array( 'value' => 'mi', 'label' => __( 'Miles (mi)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (weight)
     *
     * @return array Weight units.
     */
    public static function get_units_weight() {
        return array(
            array( 'value' => 'mg', 'label' => __( 'Milligrams (mg)', 'bits-accessible-forms' ) ),
            array( 'value' => 'g', 'label' => __( 'Grams (g)', 'bits-accessible-forms' ) ),
            array( 'value' => 'kg', 'label' => __( 'Kilograms (kg)', 'bits-accessible-forms' ) ),
            array( 'value' => 'oz', 'label' => __( 'Ounces (oz)', 'bits-accessible-forms' ) ),
            array( 'value' => 'lb', 'label' => __( 'Pounds (lb)', 'bits-accessible-forms' ) ),
            array( 'value' => 'st', 'label' => __( 'Stone (st)', 'bits-accessible-forms' ) ),
            array( 'value' => 'ton', 'label' => __( 'Tons', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get units of measurement (volume)
     *
     * @return array Volume units.
     */
    public static function get_units_volume() {
        return array(
            array( 'value' => 'ml', 'label' => __( 'Milliliters (ml)', 'bits-accessible-forms' ) ),
            array( 'value' => 'l', 'label' => __( 'Liters (l)', 'bits-accessible-forms' ) ),
            array( 'value' => 'tsp', 'label' => __( 'Teaspoons (tsp)', 'bits-accessible-forms' ) ),
            array( 'value' => 'tbsp', 'label' => __( 'Tablespoons (tbsp)', 'bits-accessible-forms' ) ),
            array( 'value' => 'fl_oz', 'label' => __( 'Fluid ounces (fl oz)', 'bits-accessible-forms' ) ),
            array( 'value' => 'cup', 'label' => __( 'Cups', 'bits-accessible-forms' ) ),
            array( 'value' => 'pt', 'label' => __( 'Pints (pt)', 'bits-accessible-forms' ) ),
            array( 'value' => 'qt', 'label' => __( 'Quarts (qt)', 'bits-accessible-forms' ) ),
            array( 'value' => 'gal', 'label' => __( 'Gallons (gal)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get temperature units
     *
     * @return array Temperature units.
     */
    public static function get_units_temperature() {
        return array(
            array( 'value' => 'celsius', 'label' => __( 'Celsius (°C)', 'bits-accessible-forms' ) ),
            array( 'value' => 'fahrenheit', 'label' => __( 'Fahrenheit (°F)', 'bits-accessible-forms' ) ),
            array( 'value' => 'kelvin', 'label' => __( 'Kelvin (K)', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get event types
     *
     * @return array Event types.
     */
    public static function get_event_types() {
        return array(
            array( 'value' => 'conference', 'label' => __( 'Conference', 'bits-accessible-forms' ) ),
            array( 'value' => 'seminar', 'label' => __( 'Seminar', 'bits-accessible-forms' ) ),
            array( 'value' => 'workshop', 'label' => __( 'Workshop', 'bits-accessible-forms' ) ),
            array( 'value' => 'webinar', 'label' => __( 'Webinar', 'bits-accessible-forms' ) ),
            array( 'value' => 'meeting', 'label' => __( 'Meeting', 'bits-accessible-forms' ) ),
            array( 'value' => 'networking', 'label' => __( 'Networking event', 'bits-accessible-forms' ) ),
            array( 'value' => 'training', 'label' => __( 'Training', 'bits-accessible-forms' ) ),
            array( 'value' => 'presentation', 'label' => __( 'Presentation', 'bits-accessible-forms' ) ),
            array( 'value' => 'product_launch', 'label' => __( 'Product launch', 'bits-accessible-forms' ) ),
            array( 'value' => 'trade_show', 'label' => __( 'Trade show', 'bits-accessible-forms' ) ),
            array( 'value' => 'exhibition', 'label' => __( 'Exhibition', 'bits-accessible-forms' ) ),
            array( 'value' => 'fundraiser', 'label' => __( 'Fundraiser', 'bits-accessible-forms' ) ),
            array( 'value' => 'gala', 'label' => __( 'Gala', 'bits-accessible-forms' ) ),
            array( 'value' => 'concert', 'label' => __( 'Concert', 'bits-accessible-forms' ) ),
            array( 'value' => 'festival', 'label' => __( 'Festival', 'bits-accessible-forms' ) ),
            array( 'value' => 'party', 'label' => __( 'Party/Celebration', 'bits-accessible-forms' ) ),
            array( 'value' => 'wedding', 'label' => __( 'Wedding', 'bits-accessible-forms' ) ),
            array( 'value' => 'corporate', 'label' => __( 'Corporate event', 'bits-accessible-forms' ) ),
            array( 'value' => 'sports', 'label' => __( 'Sports event', 'bits-accessible-forms' ) ),
            array( 'value' => 'community', 'label' => __( 'Community event', 'bits-accessible-forms' ) ),
            array( 'value' => 'virtual', 'label' => __( 'Virtual event', 'bits-accessible-forms' ) ),
            array( 'value' => 'hybrid', 'label' => __( 'Hybrid event', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get ticket types
     *
     * @return array Ticket types.
     */
    public static function get_ticket_types() {
        return array(
            array( 'value' => 'free', 'label' => __( 'Free', 'bits-accessible-forms' ) ),
            array( 'value' => 'general', 'label' => __( 'General admission', 'bits-accessible-forms' ) ),
            array( 'value' => 'vip', 'label' => __( 'VIP', 'bits-accessible-forms' ) ),
            array( 'value' => 'early_bird', 'label' => __( 'Early bird', 'bits-accessible-forms' ) ),
            array( 'value' => 'student', 'label' => __( 'Student', 'bits-accessible-forms' ) ),
            array( 'value' => 'senior', 'label' => __( 'Senior', 'bits-accessible-forms' ) ),
            array( 'value' => 'military', 'label' => __( 'Military', 'bits-accessible-forms' ) ),
            array( 'value' => 'group', 'label' => __( 'Group', 'bits-accessible-forms' ) ),
            array( 'value' => 'member', 'label' => __( 'Member', 'bits-accessible-forms' ) ),
            array( 'value' => 'press', 'label' => __( 'Press/Media', 'bits-accessible-forms' ) ),
            array( 'value' => 'sponsor', 'label' => __( 'Sponsor', 'bits-accessible-forms' ) ),
            array( 'value' => 'exhibitor', 'label' => __( 'Exhibitor', 'bits-accessible-forms' ) ),
            array( 'value' => 'speaker', 'label' => __( 'Speaker', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get support issue types
     *
     * @return array Support issue types.
     */
    public static function get_support_issue_types() {
        return array(
            array( 'value' => 'bug', 'label' => __( 'Bug/Error', 'bits-accessible-forms' ) ),
            array( 'value' => 'feature', 'label' => __( 'Feature request', 'bits-accessible-forms' ) ),
            array( 'value' => 'question', 'label' => __( 'Question', 'bits-accessible-forms' ) ),
            array( 'value' => 'account', 'label' => __( 'Account issue', 'bits-accessible-forms' ) ),
            array( 'value' => 'billing', 'label' => __( 'Billing/Payment', 'bits-accessible-forms' ) ),
            array( 'value' => 'order', 'label' => __( 'Order issue', 'bits-accessible-forms' ) ),
            array( 'value' => 'shipping', 'label' => __( 'Shipping/Delivery', 'bits-accessible-forms' ) ),
            array( 'value' => 'return', 'label' => __( 'Return/Refund', 'bits-accessible-forms' ) ),
            array( 'value' => 'technical', 'label' => __( 'Technical support', 'bits-accessible-forms' ) ),
            array( 'value' => 'feedback', 'label' => __( 'Feedback', 'bits-accessible-forms' ) ),
            array( 'value' => 'complaint', 'label' => __( 'Complaint', 'bits-accessible-forms' ) ),
            array( 'value' => 'partnership', 'label' => __( 'Partnership inquiry', 'bits-accessible-forms' ) ),
            array( 'value' => 'press', 'label' => __( 'Press/Media inquiry', 'bits-accessible-forms' ) ),
            array( 'value' => 'other', 'label' => __( 'Other', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get priority levels
     *
     * @return array Priority levels.
     */
    public static function get_priority_levels() {
        return array(
            array( 'value' => 'low', 'label' => __( 'Low', 'bits-accessible-forms' ) ),
            array( 'value' => 'medium', 'label' => __( 'Medium', 'bits-accessible-forms' ) ),
            array( 'value' => 'high', 'label' => __( 'High', 'bits-accessible-forms' ) ),
            array( 'value' => 'urgent', 'label' => __( 'Urgent', 'bits-accessible-forms' ) ),
            array( 'value' => 'critical', 'label' => __( 'Critical', 'bits-accessible-forms' ) ),
        );
    }

    /**
     * Get status options
     *
     * @return array Status options.
     */
    public static function get_statuses() {
        return array(
            array( 'value' => 'pending', 'label' => __( 'Pending', 'bits-accessible-forms' ) ),
            array( 'value' => 'open', 'label' => __( 'Open', 'bits-accessible-forms' ) ),
            array( 'value' => 'in_progress', 'label' => __( 'In Progress', 'bits-accessible-forms' ) ),
            array( 'value' => 'on_hold', 'label' => __( 'On Hold', 'bits-accessible-forms' ) ),
            array( 'value' => 'resolved', 'label' => __( 'Resolved', 'bits-accessible-forms' ) ),
            array( 'value' => 'closed', 'label' => __( 'Closed', 'bits-accessible-forms' ) ),
            array( 'value' => 'cancelled', 'label' => __( 'Cancelled', 'bits-accessible-forms' ) ),
        );
    }
}