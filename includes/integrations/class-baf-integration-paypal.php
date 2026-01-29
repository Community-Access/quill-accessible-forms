<?php
/**
 * PayPal Integration
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Integration_PayPal class
 * 
 * Handles PayPal payment integration.
 */
class BAF_Integration_PayPal extends BAF_Integration_Abstract {

    /**
     * Integration ID
     *
     * @var string
     */
    protected $id = 'paypal';

    /**
     * API URLs
     *
     * @var array
     */
    private $api_urls = array(
        'sandbox' => 'https://api-m.sandbox.paypal.com',
        'live'    => 'https://api-m.paypal.com',
    );

    /**
     * Process payment
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     */
    public function process( $entry, $form, $config ) {
        $order_id = $entry->get_meta( 'paypal_order_id' );

        if ( empty( $order_id ) ) {
            // Create order if not already created
            $payment_amount = $this->calculate_amount( $entry, $form, $config );

            if ( $payment_amount <= 0 ) {
                return; // No payment required
            }

            $order = $this->create_order( array(
                'amount'      => $payment_amount,
                'currency'    => $this->get_setting( 'currency', 'USD' ),
                'description' => sprintf(
                    __( 'Form: %s, Entry: %d', 'bits-accessible-forms' ),
                    $form->title,
                    $entry->id
                ),
            ) );

            $entry->set_meta( 'paypal_order_id', $order['id'] );
            $entry->set_meta( 'paypal_order_status', $order['status'] );
            $entry->save_meta();

            return;
        }

        // Capture existing order
        $result = $this->capture_order( $order_id );

        $entry->set_meta( 'paypal_capture_id', $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? '' );
        $entry->set_meta( 'paypal_payment_status', $result['status'] );
        $entry->save_meta();

        /**
         * Fires after PayPal payment is captured
         *
         * @param array     $result Payment result.
         * @param BAF_Entry $entry  Entry object.
         * @param BAF_Form  $form   Form object.
         */
        do_action( 'baf_paypal_payment_captured', $result, $entry, $form );
    }

    /**
     * Get access token
     *
     * @return string Access token.
     * @throws Exception On API error.
     */
    private function get_access_token() {
        $mode = $this->get_setting( 'mode', 'sandbox' );
        $client_id = $this->get_setting( $mode . '_client_id' );
        $secret = $this->get_setting( $mode . '_secret' );

        if ( empty( $client_id ) || empty( $secret ) ) {
            throw new Exception( __( 'PayPal credentials not configured.', 'bits-accessible-forms' ) );
        }

        // Check for cached token
        $cached_token = get_transient( 'baf_paypal_token_' . $mode );
        if ( $cached_token ) {
            return $cached_token;
        }

        $api_url = $this->api_urls[ $mode ];

        $response = wp_remote_post( $api_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error_description'] ?? $body['error'] );
        }

        $token = $body['access_token'];
        $expires_in = $body['expires_in'] ?? 3600;

        // Cache token
        set_transient( 'baf_paypal_token_' . $mode, $token, $expires_in - 60 );

        return $token;
    }

    /**
     * Create order
     *
     * @param array $args Order arguments.
     * @return array Order result.
     * @throws Exception On API error.
     */
    private function create_order( $args ) {
        $mode = $this->get_setting( 'mode', 'sandbox' );
        $api_url = $this->api_urls[ $mode ];
        $token = $this->get_access_token();

        $response = wp_remote_post( $api_url . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'amount' => array(
                            'currency_code' => $args['currency'],
                            'value'         => number_format( $args['amount'], 2, '.', '' ),
                        ),
                        'description' => $args['description'],
                    ),
                ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error_description'] ?? $body['message'] ?? 'Unknown error' );
        }

        return $body;
    }

    /**
     * Capture order
     *
     * @param string $order_id Order ID.
     * @return array Capture result.
     * @throws Exception On API error.
     */
    private function capture_order( $order_id ) {
        $mode = $this->get_setting( 'mode', 'sandbox' );
        $api_url = $this->api_urls[ $mode ];
        $token = $this->get_access_token();

        $response = wp_remote_post( $api_url . '/v2/checkout/orders/' . $order_id . '/capture', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error_description'] ?? $body['message'] ?? 'Unknown error' );
        }

        return $body;
    }

    /**
     * Calculate payment amount
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     * @return float Amount.
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
                        if ( is_array( $field_values[ $field_id ] ) ) {
                            $quantity = count( $field_values[ $field_id ] );
                        }
                        $amount += $price * max( 1, $quantity );
                    }
                }
            }
        }

        return $amount;
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
                    'sandbox' => __( 'Sandbox', 'bits-accessible-forms' ),
                    'live'    => __( 'Live', 'bits-accessible-forms' ),
                ),
                'default' => 'sandbox',
            ),
            'sandbox_client_id' => array(
                'type'  => 'text',
                'label' => __( 'Sandbox Client ID', 'bits-accessible-forms' ),
            ),
            'sandbox_secret' => array(
                'type'  => 'password',
                'label' => __( 'Sandbox Secret', 'bits-accessible-forms' ),
            ),
            'live_client_id' => array(
                'type'  => 'text',
                'label' => __( 'Live Client ID', 'bits-accessible-forms' ),
            ),
            'live_secret' => array(
                'type'  => 'password',
                'label' => __( 'Live Secret', 'bits-accessible-forms' ),
            ),
            'currency' => array(
                'type'    => 'select',
                'label'   => __( 'Currency', 'bits-accessible-forms' ),
                'options' => array(
                    'USD' => 'USD',
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                    'CAD' => 'CAD',
                    'AUD' => 'AUD',
                ),
                'default' => 'USD',
            ),
        );
    }

    /**
     * Test connection
     *
     * @return true|WP_Error True or error.
     */
    public function test_connection() {
        try {
            $this->get_access_token();
            return true;
        } catch ( Exception $e ) {
            return new WP_Error( 'api_error', $e->getMessage() );
        }
    }

    /**
     * Render payment button
     *
     * @param BAF_Form $form   Form object.
     * @param array    $config Integration config.
     * @return string HTML output.
     */
    public function render_payment_button( $form, $config ) {
        $mode = $this->get_setting( 'mode', 'sandbox' );
        $client_id = $this->get_setting( $mode . '_client_id' );

        ob_start();
        ?>
        <div class="baf-paypal-field" 
             data-client-id="<?php echo esc_attr( $client_id ); ?>"
             role="group"
             aria-label="<?php esc_attr_e( 'PayPal payment', 'bits-accessible-forms' ); ?>">
            
            <div id="baf-paypal-buttons" aria-label="<?php esc_attr_e( 'PayPal payment buttons', 'bits-accessible-forms' ); ?>">
                <!-- PayPal buttons will mount here -->
            </div>
            
            <div id="baf-paypal-errors" role="alert" aria-live="polite"></div>
            
            <input type="hidden" name="paypal_order_id" id="baf-paypal-order-id" />
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        $mode = $this->get_setting( 'mode', 'sandbox' );
        $client_id = $this->get_setting( $mode . '_client_id' );
        $currency = $this->get_setting( 'currency', 'USD' );

        wp_enqueue_script(
            'paypal-js',
            'https://www.paypal.com/sdk/js?client-id=' . esc_attr( $client_id ) . '&currency=' . esc_attr( $currency ),
            array(),
            null,
            true
        );

        wp_enqueue_script(
            'baf-paypal',
            BAF_PLUGIN_URL . 'public/js/baf-paypal.js',
            array( 'paypal-js', 'jquery' ),
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-paypal', 'bafPayPal', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'baf_paypal' ),
            'currency' => $currency,
            'i18n' => array(
                'paymentError' => __( 'Payment failed. Please try again.', 'bits-accessible-forms' ),
            ),
        ) );
    }
}
