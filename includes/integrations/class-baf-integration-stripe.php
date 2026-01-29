<?php
/**
 * Stripe Integration
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Integration_Stripe class
 * 
 * Handles Stripe payment integration.
 */
class BAF_Integration_Stripe extends BAF_Integration_Abstract {

    /**
     * Integration ID
     *
     * @var string
     */
    protected $id = 'stripe';

    /**
     * Stripe API version
     *
     * @var string
     */
    const API_VERSION = '2023-10-16';

    /**
     * API base URL
     *
     * @var string
     */
    const API_URL = 'https://api.stripe.com/v1';

    /**
     * Process payment
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     */
    public function process( $entry, $form, $config ) {
        $payment_amount = $this->calculate_amount( $entry, $form, $config );

        if ( $payment_amount <= 0 ) {
            return; // No payment required
        }

        $payment_token = $entry->get_meta( 'stripe_token' );

        if ( empty( $payment_token ) ) {
            throw new Exception( __( 'Payment token not found.', 'bits-accessible-forms' ) );
        }

        // Create payment intent or charge
        $result = $this->create_payment( array(
            'amount'      => $payment_amount,
            'currency'    => $this->get_setting( 'currency', 'usd' ),
            'source'      => $payment_token,
            'description' => sprintf(
                __( 'Form: %s, Entry: %d', 'bits-accessible-forms' ),
                $form->title,
                $entry->id
            ),
            'metadata'    => array(
                'form_id'  => $form->id,
                'entry_id' => $entry->id,
            ),
        ) );

        // Store payment info
        $entry->set_meta( 'stripe_payment_id', $result['id'] );
        $entry->set_meta( 'stripe_payment_status', $result['status'] );
        $entry->set_meta( 'stripe_payment_amount', $payment_amount );
        $entry->save_meta();

        /**
         * Fires after Stripe payment is processed
         *
         * @param array     $result Payment result.
         * @param BAF_Entry $entry  Entry object.
         * @param BAF_Form  $form   Form object.
         */
        do_action( 'baf_stripe_payment_processed', $result, $entry, $form );
    }

    /**
     * Create payment
     *
     * @param array $args Payment arguments.
     * @return array Payment result.
     * @throws Exception On API error.
     */
    private function create_payment( $args ) {
        $mode = $this->get_setting( 'mode', 'test' );
        $secret_key = $this->get_setting( $mode . '_secret_key' );

        if ( empty( $secret_key ) ) {
            throw new Exception( __( 'Stripe API key not configured.', 'bits-accessible-forms' ) );
        }

        // Use PaymentIntents API for SCA compliance
        $response = wp_remote_post( self::API_URL . '/payment_intents', array(
            'headers' => array(
                'Authorization'  => 'Bearer ' . $secret_key,
                'Stripe-Version' => self::API_VERSION,
                'Content-Type'   => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount'               => $args['amount'],
                'currency'             => $args['currency'],
                'payment_method'       => $args['source'],
                'description'          => $args['description'],
                'confirmation_method'  => 'automatic',
                'confirm'              => 'true',
                'metadata'             => $args['metadata'],
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error']['message'] );
        }

        return $body;
    }

    /**
     * Calculate payment amount
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     * @return int Amount in cents.
     */
    private function calculate_amount( $entry, $form, $config ) {
        $amount = 0;

        // Fixed amount
        if ( ! empty( $config['amount_type'] ) && $config['amount_type'] === 'fixed' ) {
            $amount = floatval( $config['fixed_amount'] ?? 0 );
        }

        // Field-based amount
        if ( ! empty( $config['amount_type'] ) && $config['amount_type'] === 'field' ) {
            $field_id = $config['amount_field'] ?? '';
            $field_values = $entry->get_field_values();

            if ( isset( $field_values[ $field_id ] ) ) {
                $amount = floatval( $field_values[ $field_id ] );
            }
        }

        // Product-based amount
        if ( ! empty( $config['amount_type'] ) && $config['amount_type'] === 'product' ) {
            $products = $config['products'] ?? array();
            $field_values = $entry->get_field_values();

            foreach ( $products as $product ) {
                $field_id = $product['field_id'] ?? '';
                $price = floatval( $product['price'] ?? 0 );

                if ( isset( $field_values[ $field_id ] ) ) {
                    $quantity = intval( $field_values[ $field_id ] );
                    if ( $quantity > 0 || is_array( $field_values[ $field_id ] ) ) {
                        // For multi-select, count selections
                        if ( is_array( $field_values[ $field_id ] ) ) {
                            $quantity = count( $field_values[ $field_id ] );
                        }
                        $amount += $price * max( 1, $quantity );
                    }
                }
            }
        }

        // Convert to cents
        return intval( $amount * 100 );
    }

    /**
     * Get settings fields
     *
     * @return array Settings fields.
     */
    public function get_settings_fields() {
        return array(
            'mode' => array(
                'type'    => 'select',
                'label'   => __( 'Mode', 'bits-accessible-forms' ),
                'options' => array(
                    'test' => __( 'Test', 'bits-accessible-forms' ),
                    'live' => __( 'Live', 'bits-accessible-forms' ),
                ),
                'default' => 'test',
            ),
            'test_publishable_key' => array(
                'type'  => 'text',
                'label' => __( 'Test Publishable Key', 'bits-accessible-forms' ),
            ),
            'test_secret_key' => array(
                'type'  => 'password',
                'label' => __( 'Test Secret Key', 'bits-accessible-forms' ),
            ),
            'live_publishable_key' => array(
                'type'  => 'text',
                'label' => __( 'Live Publishable Key', 'bits-accessible-forms' ),
            ),
            'live_secret_key' => array(
                'type'  => 'password',
                'label' => __( 'Live Secret Key', 'bits-accessible-forms' ),
            ),
            'currency' => array(
                'type'    => 'select',
                'label'   => __( 'Currency', 'bits-accessible-forms' ),
                'options' => array(
                    'usd' => 'USD',
                    'eur' => 'EUR',
                    'gbp' => 'GBP',
                    'cad' => 'CAD',
                    'aud' => 'AUD',
                ),
                'default' => 'usd',
            ),
        );
    }

    /**
     * Test connection
     *
     * @return true|WP_Error True or error.
     */
    public function test_connection() {
        $mode = $this->get_setting( 'mode', 'test' );
        $secret_key = $this->get_setting( $mode . '_secret_key' );

        if ( empty( $secret_key ) ) {
            return new WP_Error( 'missing_key', __( 'API key not configured.', 'bits-accessible-forms' ) );
        }

        $response = wp_remote_get( self::API_URL . '/balance', array(
            'headers' => array(
                'Authorization'  => 'Bearer ' . $secret_key,
                'Stripe-Version' => self::API_VERSION,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error( 'api_error', $body['error']['message'] );
        }

        return true;
    }

    /**
     * Render payment field
     *
     * @param BAF_Form $form   Form object.
     * @param array    $config Integration config.
     * @return string HTML output.
     */
    public function render_payment_field( $form, $config ) {
        $mode = $this->get_setting( 'mode', 'test' );
        $publishable_key = $this->get_setting( $mode . '_publishable_key' );

        ob_start();
        ?>
        <div class="baf-stripe-field" 
             data-publishable-key="<?php echo esc_attr( $publishable_key ); ?>"
             role="group"
             aria-label="<?php esc_attr_e( 'Payment information', 'bits-accessible-forms' ); ?>">
            
            <div id="baf-stripe-card-element" aria-label="<?php esc_attr_e( 'Credit or debit card', 'bits-accessible-forms' ); ?>">
                <!-- Stripe Elements will mount here -->
            </div>
            
            <div id="baf-stripe-card-errors" role="alert" aria-live="polite"></div>
            
            <input type="hidden" name="stripe_token" id="baf-stripe-token" />
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        $mode = $this->get_setting( 'mode', 'test' );
        $publishable_key = $this->get_setting( $mode . '_publishable_key' );

        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        wp_enqueue_script(
            'baf-stripe',
            BAF_PLUGIN_URL . 'public/js/baf-stripe.js',
            array( 'stripe-js', 'jquery' ),
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-stripe', 'bafStripe', array(
            'publishableKey' => $publishable_key,
            'i18n' => array(
                'cardError' => __( 'Your card was declined.', 'bits-accessible-forms' ),
            ),
        ) );
    }
}
