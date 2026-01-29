<?php
/**
 * Date Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Date class
 */
class BAF_Field_Date extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'date';

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
            'date_format'      => 'Y-m-d', // ISO format for storage
            'display_format'   => 'F j, Y', // Human readable
            'min_date'         => '',
            'max_date'         => '',
            'disable_past'     => false,
            'disable_future'   => false,
            'disabled_days'    => array(), // 0 = Sunday, 6 = Saturday
            'use_native'       => true,
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Minimum Date', 'bits-accessible-forms' ),
                ),
                'max_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Maximum Date', 'bits-accessible-forms' ),
                ),
                'disable_past' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Past Dates', 'bits-accessible-forms' ),
                ),
                'disable_future' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Future Dates', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'display_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Format', 'bits-accessible-forms' ),
                    'options' => array(
                        'F j, Y'  => date_i18n( 'F j, Y' ),
                        'm/d/Y'   => date_i18n( 'm/d/Y' ),
                        'd/m/Y'   => date_i18n( 'd/m/Y' ),
                        'Y-m-d'   => date_i18n( 'Y-m-d' ),
                    ),
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
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();

        $attrs = array(
            'type'  => 'date',
            'id'    => $html_id,
            'name'  => $name,
            'value' => $value,
            'class' => 'baf-date-input',
        );

        // Min/max dates
        $min_date = $this->get_min_date();
        $max_date = $this->get_max_date();

        if ( $min_date ) {
            $attrs['min'] = $min_date;
        }
        if ( $max_date ) {
            $attrs['max'] = $max_date;
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        // Add date constraints to describedby
        $constraints = array();
        if ( $min_date || $max_date ) {
            $constraint_id = $html_id . '-constraints';
            $existing = $attrs['aria-describedby'] ?? '';
            $attrs['aria-describedby'] = trim( $constraint_id . ' ' . $existing );
        }

        $field = '<input ' . $this->build_attributes( $attrs ) . '>';

        // Date constraints hint
        if ( $min_date || $max_date ) {
            $field .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="baf-date-constraints">';
            
            if ( $min_date && $max_date ) {
                $field .= sprintf(
                    esc_html__( 'Select a date between %1$s and %2$s', 'bits-accessible-forms' ),
                    esc_html( date_i18n( $this->config['display_format'], strtotime( $min_date ) ) ),
                    esc_html( date_i18n( $this->config['display_format'], strtotime( $max_date ) ) )
                );
            } elseif ( $min_date ) {
                $field .= sprintf(
                    esc_html__( 'Select a date on or after %s', 'bits-accessible-forms' ),
                    esc_html( date_i18n( $this->config['display_format'], strtotime( $min_date ) ) )
                );
            } else {
                $field .= sprintf(
                    esc_html__( 'Select a date on or before %s', 'bits-accessible-forms' ),
                    esc_html( date_i18n( $this->config['display_format'], strtotime( $max_date ) ) )
                );
            }
            
            $field .= '</div>';
        }

        return $this->render_wrapper( $field );
    }

    /**
     * Get minimum date
     *
     * @return string Date in Y-m-d format.
     */
    protected function get_min_date() {
        if ( ! empty( $this->config['disable_past'] ) ) {
            return gmdate( 'Y-m-d' );
        }

        if ( ! empty( $this->config['min_date'] ) ) {
            return gmdate( 'Y-m-d', strtotime( $this->config['min_date'] ) );
        }

        return '';
    }

    /**
     * Get maximum date
     *
     * @return string Date in Y-m-d format.
     */
    protected function get_max_date() {
        if ( ! empty( $this->config['disable_future'] ) ) {
            return gmdate( 'Y-m-d' );
        }

        if ( ! empty( $this->config['max_date'] ) ) {
            return gmdate( 'Y-m-d', strtotime( $this->config['max_date'] ) );
        }

        return '';
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Validate date format
        $timestamp = strtotime( $value );
        if ( $timestamp === false ) {
            return '';
        }

        // Store in ISO format
        return gmdate( 'Y-m-d', $timestamp );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $timestamp = strtotime( $value );

        if ( $timestamp === false ) {
            $this->add_error( $this->get_error_message( 'date' ) );
            return;
        }

        $date = gmdate( 'Y-m-d', $timestamp );
        $min_date = $this->get_min_date();
        $max_date = $this->get_max_date();

        if ( $min_date && $date < $min_date ) {
            $this->add_error( sprintf(
                __( 'Please select a date on or after %s.', 'bits-accessible-forms' ),
                date_i18n( $this->config['display_format'], strtotime( $min_date ) )
            ) );
        }

        if ( $max_date && $date > $max_date ) {
            $this->add_error( sprintf(
                __( 'Please select a date on or before %s.', 'bits-accessible-forms' ),
                date_i18n( $this->config['display_format'], strtotime( $max_date ) )
            ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted date.
     */
    public function format_display( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $timestamp = strtotime( $value );
        if ( $timestamp === false ) {
            return esc_html( $value );
        }

        return esc_html( date_i18n( $this->config['display_format'], $timestamp ) );
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code.
     */
    public function to_bfl() {
        $bfl = 'date #' . $this->get_id() . ' "' . addslashes( $this->get_label() ) . '"';

        if ( $this->is_required() ) {
            $bfl .= "\n  @required";
        }

        if ( ! empty( $this->config['min_date'] ) ) {
            $bfl .= "\n  @min \"" . $this->config['min_date'] . '"';
        }

        if ( ! empty( $this->config['max_date'] ) ) {
            $bfl .= "\n  @max \"" . $this->config['max_date'] . '"';
        }

        return $bfl;
    }
}
