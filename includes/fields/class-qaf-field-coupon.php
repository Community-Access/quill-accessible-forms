<?php
/**
 * Coupon Field - Discount code entry
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Coupon class
 */
class QAF_Field_Coupon extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'coupon';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'pricing';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'label'             => __( 'Discount Code', 'quill-accessible-forms' ),
            'description'       => '',
            'placeholder'       => __( 'Enter coupon code', 'quill-accessible-forms' ),
            'button_text'       => __( 'Apply', 'quill-accessible-forms' ),
            'success_message'   => __( 'Coupon applied successfully!', 'quill-accessible-forms' ),
            'error_message'     => __( 'Invalid coupon code.', 'quill-accessible-forms' ),
            'expired_message'   => __( 'This coupon has expired.', 'quill-accessible-forms' ),
            'used_message'      => __( 'Coupon usage limit reached.', 'quill-accessible-forms' ),
            'display_style'     => 'inline',
            'show_savings'      => true,
            'savings_format'    => 'amount',
            'coupons'           => array(),
            'validate_ajax'     => false,
            'case_sensitive'    => false,
            'auto_apply'        => '',
            'required'          => false,
            'conditional'       => array(),
            'css_class'         => '',
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'label' => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'coupons' => array(
                'coupons' => array(
                    'type'   => 'repeater',
                    'label'  => __( 'Coupon Codes', 'quill-accessible-forms' ),
                    'fields' => array(
                        'code' => array(
                            'type'  => 'text',
                            'label' => __( 'Code', 'quill-accessible-forms' ),
                        ),
                        'discount_type' => array(
                            'type'    => 'select',
                            'label'   => __( 'Discount Type', 'quill-accessible-forms' ),
                            'options' => array(
                                'percentage' => __( 'Percentage (%)', 'quill-accessible-forms' ),
                                'fixed'      => __( 'Fixed Amount', 'quill-accessible-forms' ),
                                'free_ship'  => __( 'Free Shipping', 'quill-accessible-forms' ),
                            ),
                        ),
                        'discount_value' => array(
                            'type'  => 'number',
                            'label' => __( 'Discount Value', 'quill-accessible-forms' ),
                            'min'   => 0,
                            'step'  => '0.01',
                        ),
                        'min_purchase' => array(
                            'type'  => 'number',
                            'label' => __( 'Minimum Purchase', 'quill-accessible-forms' ),
                            'min'   => 0,
                            'step'  => '0.01',
                        ),
                        'max_discount' => array(
                            'type'        => 'number',
                            'label'       => __( 'Maximum Discount', 'quill-accessible-forms' ),
                            'description' => __( 'For percentage coupons, cap the discount', 'quill-accessible-forms' ),
                            'min'         => 0,
                            'step'        => '0.01',
                        ),
                        'usage_limit' => array(
                            'type'  => 'number',
                            'label' => __( 'Usage Limit', 'quill-accessible-forms' ),
                            'min'   => 0,
                        ),
                        'expiry_date' => array(
                            'type'  => 'date',
                            'label' => __( 'Expiry Date', 'quill-accessible-forms' ),
                        ),
                        'enabled' => array(
                            'type'    => 'toggle',
                            'label'   => __( 'Enabled', 'quill-accessible-forms' ),
                            'default' => true,
                        ),
                    ),
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'inline'   => __( 'Inline (input + button)', 'quill-accessible-forms' ),
                        'stacked'  => __( 'Stacked', 'quill-accessible-forms' ),
                        'collapsible' => __( 'Collapsible ("Have a coupon?")', 'quill-accessible-forms' ),
                        'link'     => __( 'Link to reveal input', 'quill-accessible-forms' ),
                    ),
                ),
                'button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Apply Button Text', 'quill-accessible-forms' ),
                ),
                'show_savings' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Savings Amount', 'quill-accessible-forms' ),
                ),
                'savings_format' => array(
                    'type'      => 'select',
                    'label'     => __( 'Savings Display Format', 'quill-accessible-forms' ),
                    'options'   => array(
                        'amount'     => __( 'Discount amount (e.g., -$10)', 'quill-accessible-forms' ),
                        'percentage' => __( 'Percentage (e.g., 10% off)', 'quill-accessible-forms' ),
                        'both'       => __( 'Both', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'show_savings' => true ),
                ),
            ),
            'messages' => array(
                'success_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Success Message', 'quill-accessible-forms' ),
                ),
                'error_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Invalid Code Message', 'quill-accessible-forms' ),
                ),
                'expired_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Expired Message', 'quill-accessible-forms' ),
                ),
                'used_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Usage Limit Message', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'case_sensitive' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Case Sensitive Codes', 'quill-accessible-forms' ),
                ),
                'validate_ajax' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Validate via AJAX', 'quill-accessible-forms' ),
                ),
                'auto_apply' => array(
                    'type'        => 'text',
                    'label'       => __( 'Auto-Apply Code', 'quill-accessible-forms' ),
                    'description' => __( 'Automatically apply this coupon code', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $label = $this->config['label'] ?? __( 'Discount Code', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $display_style = $this->config['display_style'] ?? 'inline';
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-coupon', 'qaf-coupon-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $coupons = $this->config['coupons'] ?? array();
        $coupons_json = wp_json_encode( $this->sanitize_coupons_for_frontend( $coupons ) );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-coupons="' . esc_attr( $coupons_json ) . '"';
        $html .= ' data-case-sensitive="' . ( ! empty( $this->config['case_sensitive'] ) ? 'true' : 'false' ) . '"';
        $html .= '>';

        if ( $display_style === 'collapsible' || $display_style === 'link' ) {
            $html .= '<button type="button" class="qaf-coupon-toggle" aria-expanded="false" aria-controls="' . esc_attr( $html_id . '-wrapper' ) . '">';
            $html .= esc_html__( 'Have a coupon code?', 'quill-accessible-forms' );
            $html .= '</button>';
            $html .= '<div id="' . esc_attr( $html_id . '-wrapper' ) . '" class="qaf-coupon-wrapper" hidden>';
        }

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        // Input wrapper
        $html .= '<div class="qaf-coupon-input-wrapper">';

        // Input
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' class="qaf-coupon-input"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' autocomplete="off"';
        if ( $required ) {
            $html .= ' aria-required="true"';
        }
        if ( $desc_id ) {
            $html .= ' aria-describedby="' . esc_attr( $desc_id ) . ' ' . esc_attr( $html_id . '-status' ) . '"';
        } else {
            $html .= ' aria-describedby="' . esc_attr( $html_id . '-status' ) . '"';
        }
        $html .= '>';

        // Apply button
        $html .= '<button type="button" class="qaf-coupon-apply">';
        $html .= esc_html( $this->config['button_text'] ?? __( 'Apply', 'quill-accessible-forms' ) );
        $html .= '</button>';

        $html .= '</div>';

        // Status message area
        $html .= '<div id="' . esc_attr( $html_id . '-status' ) . '" class="qaf-coupon-status" role="status" aria-live="polite"></div>';

        // Savings display
        if ( ! empty( $this->config['show_savings'] ) ) {
            $html .= '<div class="qaf-coupon-savings" data-coupon-savings style="display: none;">';
            $html .= '<span class="qaf-savings-label">' . esc_html__( 'You save:', 'quill-accessible-forms' ) . '</span>';
            $html .= '<span class="qaf-savings-value" data-savings-value></span>';
            $html .= '</div>';
        }

        // Applied coupon display
        $html .= '<div class="qaf-coupon-applied" data-coupon-applied style="display: none;">';
        $html .= '<span class="qaf-coupon-code-display" data-applied-code></span>';
        $html .= '<button type="button" class="qaf-coupon-remove" aria-label="' . esc_attr__( 'Remove coupon', 'quill-accessible-forms' ) . '">';
        $html .= '<span aria-hidden="true">&times;</span>';
        $html .= '</button>';
        $html .= '</div>';

        // Hidden field for discount value
        $html .= '<input type="hidden" name="qaf_field_' . esc_attr( $this->get_id() ) . '_discount" value="0" data-coupon-discount-hidden>';

        if ( $display_style === 'collapsible' || $display_style === 'link' ) {
            $html .= '</div>'; // Close wrapper
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Sanitize coupons for frontend (remove sensitive data)
     *
     * @param array $coupons Coupon configurations.
     * @return array Safe coupon data.
     */
    protected function sanitize_coupons_for_frontend( $coupons ) {
        $safe = array();
        foreach ( $coupons as $coupon ) {
            if ( empty( $coupon['enabled'] ) ) {
                continue;
            }
            // Only send hash, type, and constraints - not the actual code
            $safe[] = array(
                'hash'          => wp_hash( strtolower( $coupon['code'] ?? '' ) ),
                'discount_type' => $coupon['discount_type'] ?? 'percentage',
                'discount_value' => (float) ( $coupon['discount_value'] ?? 0 ),
                'min_purchase'  => (float) ( $coupon['min_purchase'] ?? 0 ),
                'max_discount'  => (float) ( $coupon['max_discount'] ?? 0 ),
            );
        }
        return $safe;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = trim( $this->get_value() );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter a coupon code.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            $valid_coupon = $this->validate_coupon( $value );
            if ( is_wp_error( $valid_coupon ) ) {
                $this->add_error( $valid_coupon->get_error_message() );
                return false;
            }
        }

        return true;
    }

    /**
     * Validate coupon code
     *
     * @param string $code Coupon code.
     * @return true|WP_Error True if valid, WP_Error otherwise.
     */
    protected function validate_coupon( $code ) {
        $coupons = $this->config['coupons'] ?? array();
        $case_sensitive = ! empty( $this->config['case_sensitive'] );
        $check_code = $case_sensitive ? $code : strtolower( $code );

        foreach ( $coupons as $coupon ) {
            if ( empty( $coupon['enabled'] ) ) {
                continue;
            }

            $coupon_code = $case_sensitive ? $coupon['code'] : strtolower( $coupon['code'] );
            
            if ( $check_code === $coupon_code ) {
                // Check expiry
                if ( ! empty( $coupon['expiry_date'] ) ) {
                    $expiry = strtotime( $coupon['expiry_date'] );
                    if ( $expiry && $expiry < time() ) {
                        return new WP_Error(
                            'coupon_expired',
                            $this->config['expired_message'] ?? __( 'This coupon has expired.', 'quill-accessible-forms' )
                        );
                    }
                }

                return true;
            }
        }

        return new WP_Error(
            'invalid_coupon',
            $this->config['error_message'] ?? __( 'Invalid coupon code.', 'quill-accessible-forms' )
        );
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized coupon code.
     */
    public function sanitize( $value ) {
        return sanitize_text_field( trim( $value ) );
    }
}
