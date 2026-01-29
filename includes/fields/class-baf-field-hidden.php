<?php
/**
 * Hidden Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Hidden class
 */
class BAF_Field_Hidden extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'hidden';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'default_value'    => '',
            'dynamic_source'   => '',
            'parameter_name'   => '',
            'allow_override'   => false,
        ) );
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
                    'label' => __( 'Admin Label', 'bits-accessible-forms' ),
                    'desc'  => __( 'For admin reference only.', 'bits-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'bits-accessible-forms' ),
                ),
            ),
            'dynamic' => array(
                'dynamic_source' => array(
                    'type'    => 'select',
                    'label'   => __( 'Populate From', 'bits-accessible-forms' ),
                    'options' => array(
                        ''           => __( 'None (Static Value)', 'bits-accessible-forms' ),
                        'url_param'  => __( 'URL Parameter', 'bits-accessible-forms' ),
                        'user_meta'  => __( 'User Meta', 'bits-accessible-forms' ),
                        'post_meta'  => __( 'Post Meta', 'bits-accessible-forms' ),
                        'cookie'     => __( 'Cookie', 'bits-accessible-forms' ),
                        'session'    => __( 'Session', 'bits-accessible-forms' ),
                    ),
                ),
                'parameter_name' => array(
                    'type'  => 'text',
                    'label' => __( 'Parameter/Key Name', 'bits-accessible-forms' ),
                ),
                'allow_override' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow URL Override', 'bits-accessible-forms' ),
                    'desc'  => __( 'Allow the value to be changed via URL parameters.', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML (hidden, no visible output)
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_dynamic_value();

        $html = '<input type="hidden"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= '>';

        return $html;
    }

    /**
     * Get dynamic value based on source
     *
     * @return string Dynamic value.
     */
    protected function get_dynamic_value() {
        $value  = $this->get_value();
        $source = $this->config['dynamic_source'] ?? '';
        $param  = $this->config['parameter_name'] ?? '';

        if ( empty( $source ) || empty( $param ) ) {
            return $value ?: ( $this->config['default_value'] ?? '' );
        }

        $dynamic_value = '';

        switch ( $source ) {
            case 'url_param':
                if ( isset( $_GET[ $param ] ) ) {
                    $dynamic_value = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                }
                break;

            case 'user_meta':
                if ( is_user_logged_in() ) {
                    $dynamic_value = get_user_meta( get_current_user_id(), $param, true );
                }
                break;

            case 'post_meta':
                global $post;
                if ( $post ) {
                    $dynamic_value = get_post_meta( $post->ID, $param, true );
                }
                break;

            case 'cookie':
                if ( isset( $_COOKIE[ $param ] ) ) {
                    $dynamic_value = sanitize_text_field( wp_unslash( $_COOKIE[ $param ] ) );
                }
                break;

            case 'session':
                if ( isset( $_SESSION[ $param ] ) ) {
                    $dynamic_value = sanitize_text_field( $_SESSION[ $param ] );
                }
                break;
        }

        return $dynamic_value ?: ( $this->config['default_value'] ?? '' );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        return sanitize_text_field( $value );
    }

    /**
     * Hidden fields typically don't validate user input
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Hidden fields are usually system-controlled
    }
}
