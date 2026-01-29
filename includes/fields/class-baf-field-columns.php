<?php
/**
 * Columns Layout Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Columns class
 */
class BAF_Field_Columns extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'columns';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Whether field stores data
     *
     * @var bool
     */
    protected $has_value = false;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'layout'          => '1/2-1/2',
            'gap'             => 'medium',
            'stack_on_mobile' => true,
            'reverse_mobile'  => false,
            'columns'         => array( array(), array() ),
            'conditional'     => array(),
            'css_class'       => '',
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
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Column Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        '1/2-1/2'       => __( '50% - 50%', 'bits-accessible-forms' ),
                        '1/3-2/3'       => __( '33% - 67%', 'bits-accessible-forms' ),
                        '2/3-1/3'       => __( '67% - 33%', 'bits-accessible-forms' ),
                        '1/3-1/3-1/3'   => __( '33% - 33% - 33%', 'bits-accessible-forms' ),
                        '1/4-3/4'       => __( '25% - 75%', 'bits-accessible-forms' ),
                        '3/4-1/4'       => __( '75% - 25%', 'bits-accessible-forms' ),
                        '1/4-1/2-1/4'   => __( '25% - 50% - 25%', 'bits-accessible-forms' ),
                        '1/4-1/4-1/4-1/4' => __( '25% - 25% - 25% - 25%', 'bits-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'  => 'column_builder',
                    'label' => __( 'Column Contents', 'bits-accessible-forms' ),
                ),
            ),
            'spacing' => array(
                'gap' => array(
                    'type'    => 'select',
                    'label'   => __( 'Column Gap', 'bits-accessible-forms' ),
                    'options' => array(
                        'none'   => __( 'None', 'bits-accessible-forms' ),
                        'small'  => __( 'Small', 'bits-accessible-forms' ),
                        'medium' => __( 'Medium', 'bits-accessible-forms' ),
                        'large'  => __( 'Large', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'responsive' => array(
                'stack_on_mobile' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Stack on Mobile', 'bits-accessible-forms' ),
                    'desc'  => __( 'Display columns vertically on small screens.', 'bits-accessible-forms' ),
                ),
                'reverse_mobile' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Reverse Order on Mobile', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the columns layout
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $layout  = $this->config['layout'] ?? '1/2-1/2';
        $gap     = $this->config['gap'] ?? 'medium';
        $columns = $this->config['columns'] ?? array();

        $wrapper_class = array( 
            'baf-columns',
            'baf-columns-' . $this->layout_to_class( $layout ),
            'baf-gap-' . $gap,
        );
        
        if ( ! empty( $this->config['stack_on_mobile'] ) ) {
            $wrapper_class[] = 'baf-columns-stack';
        }
        
        if ( ! empty( $this->config['reverse_mobile'] ) ) {
            $wrapper_class[] = 'baf-columns-reverse-mobile';
        }
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $widths = $this->get_column_widths( $layout );
        
        // Use role="group" for semantic structure
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' role="group"';
        $html .= ' aria-label="' . esc_attr__( 'Form fields group', 'bits-accessible-forms' ) . '">';

        // Render each column
        foreach ( $widths as $index => $width ) {
            $column_fields = $columns[ $index ] ?? array();
            $column_class = array(
                'baf-column',
                'baf-column-' . str_replace( '/', '-', $width ),
            );

            $html .= '<div class="' . esc_attr( implode( ' ', $column_class ) ) . '">';
            
            // Render fields within column
            foreach ( $column_fields as $field_config ) {
                $html .= $this->render_column_field( $field_config );
            }
            
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Convert layout to CSS class
     *
     * @param string $layout Layout string.
     * @return string CSS-safe class.
     */
    protected function layout_to_class( $layout ) {
        return str_replace( array( '/', '-' ), array( '', '-' ), $layout );
    }

    /**
     * Get column widths from layout
     *
     * @param string $layout Layout string.
     * @return array Column widths.
     */
    protected function get_column_widths( $layout ) {
        $layouts = array(
            '1/2-1/2'         => array( '1/2', '1/2' ),
            '1/3-2/3'         => array( '1/3', '2/3' ),
            '2/3-1/3'         => array( '2/3', '1/3' ),
            '1/3-1/3-1/3'     => array( '1/3', '1/3', '1/3' ),
            '1/4-3/4'         => array( '1/4', '3/4' ),
            '3/4-1/4'         => array( '3/4', '1/4' ),
            '1/4-1/2-1/4'     => array( '1/4', '1/2', '1/4' ),
            '1/4-1/4-1/4-1/4' => array( '1/4', '1/4', '1/4', '1/4' ),
        );

        return $layouts[ $layout ] ?? array( '1/2', '1/2' );
    }

    /**
     * Render a field within a column
     *
     * @param array $field_config Field configuration.
     * @return string Rendered field HTML.
     */
    protected function render_column_field( $field_config ) {
        if ( empty( $field_config ) || empty( $field_config['type'] ) ) {
            return '';
        }

        $registry = BAF_Field_Registry::get_instance();
        $field_class = $registry->get_field_class( $field_config['type'] );

        if ( ! $field_class || ! class_exists( $field_class ) ) {
            return '';
        }

        $field = new $field_class( $field_config );
        return $field->render();
    }

    /**
     * Columns layout doesn't need validation itself
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Columns layout doesn't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}
