<?php
/**
 * Post Custom Field - WordPress post meta
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Custom class
 */
class QAF_Field_Post_Custom extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_custom';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'post';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'label'         => __( 'Custom Field', 'quill-accessible-forms' ),
            'description'   => '',
            'meta_key'      => '',
            'field_type'    => 'text',
            'placeholder'   => '',
            'default_value' => '',
            'options'       => array(),
            'rows'          => 4,
            'min'           => '',
            'max'           => '',
            'step'          => '',
            'prefix'        => '',
            'suffix'        => '',
            'private'       => true,
            'unique'        => false,
            'required'      => false,
            'conditional'   => array(),
            'css_class'     => '',
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
                'meta_key' => array(
                    'type'        => 'text',
                    'label'       => __( 'Meta Key', 'quill-accessible-forms' ),
                    'description' => __( 'The custom field name to store this in', 'quill-accessible-forms' ),
                    'required'    => true,
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'type' => array(
                'field_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Input Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'text'     => __( 'Text', 'quill-accessible-forms' ),
                        'textarea' => __( 'Textarea', 'quill-accessible-forms' ),
                        'number'   => __( 'Number', 'quill-accessible-forms' ),
                        'email'    => __( 'Email', 'quill-accessible-forms' ),
                        'url'      => __( 'URL', 'quill-accessible-forms' ),
                        'date'     => __( 'Date', 'quill-accessible-forms' ),
                        'time'     => __( 'Time', 'quill-accessible-forms' ),
                        'color'    => __( 'Color', 'quill-accessible-forms' ),
                        'select'   => __( 'Select', 'quill-accessible-forms' ),
                        'radio'    => __( 'Radio', 'quill-accessible-forms' ),
                        'checkbox' => __( 'Checkbox', 'quill-accessible-forms' ),
                        'toggle'   => __( 'Toggle', 'quill-accessible-forms' ),
                    ),
                ),
                'options' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Options', 'quill-accessible-forms' ),
                    'description' => __( 'One option per line (value|label format)', 'quill-accessible-forms' ),
                    'condition'   => array( 'field_type' => array( 'select', 'radio', 'checkbox' ) ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'rows' => array(
                    'type'      => 'number',
                    'label'     => __( 'Rows', 'quill-accessible-forms' ),
                    'min'       => 2,
                    'max'       => 20,
                    'condition' => array( 'field_type' => 'textarea' ),
                ),
                'prefix' => array(
                    'type'  => 'text',
                    'label' => __( 'Prefix', 'quill-accessible-forms' ),
                ),
                'suffix' => array(
                    'type'  => 'text',
                    'label' => __( 'Suffix', 'quill-accessible-forms' ),
                ),
            ),
            'number' => array(
                'min' => array(
                    'type'      => 'number',
                    'label'     => __( 'Minimum', 'quill-accessible-forms' ),
                    'condition' => array( 'field_type' => 'number' ),
                ),
                'max' => array(
                    'type'      => 'number',
                    'label'     => __( 'Maximum', 'quill-accessible-forms' ),
                    'condition' => array( 'field_type' => 'number' ),
                ),
                'step' => array(
                    'type'      => 'text',
                    'label'     => __( 'Step', 'quill-accessible-forms' ),
                    'condition' => array( 'field_type' => 'number' ),
                ),
            ),
            'advanced' => array(
                'private' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Private Meta', 'quill-accessible-forms' ),
                    'description' => __( 'Prefix with underscore (hidden from post editor)', 'quill-accessible-forms' ),
                ),
                'unique' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Unique Value', 'quill-accessible-forms' ),
                    'description' => __( 'Value must be unique across all posts', 'quill-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Custom Field', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $field_type = $this->config['field_type'] ?? 'text';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value() ?: ( $this->config['default_value'] ?? '' );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-custom', 'qaf-custom-' . $field_type );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        if ( $field_type !== 'toggle' ) {
            $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
            $html .= esc_html( $label );
            if ( $required ) {
                $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
            }
            $html .= '</label>';
        }

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $name = 'qaf_field_' . $this->get_id();

        // Prefix
        if ( ! empty( $this->config['prefix'] ) && in_array( $field_type, array( 'text', 'number', 'email', 'url' ) ) ) {
            $html .= '<span class="qaf-input-prefix">' . esc_html( $this->config['prefix'] ) . '</span>';
        }

        // Render appropriate input type
        switch ( $field_type ) {
            case 'textarea':
                $rows = (int) ( $this->config['rows'] ?? 4 );
                $html .= '<textarea';
                $html .= ' id="' . esc_attr( $html_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                $html .= ' rows="' . esc_attr( $rows ) . '"';
                $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                $html .= ' class="qaf-textarea"';
                $html .= ' ' . $aria_attrs;
                $html .= '>' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'select':
                $options = $this->parse_options();
                $html .= '<select';
                $html .= ' id="' . esc_attr( $html_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                $html .= ' class="qaf-select"';
                $html .= ' ' . $aria_attrs . '>';
                $html .= '<option value="">' . esc_html( $placeholder ?: __( 'Select...', 'quill-accessible-forms' ) ) . '</option>';
                foreach ( $options as $opt_value => $opt_label ) {
                    $html .= '<option value="' . esc_attr( $opt_value ) . '"' . selected( $value, $opt_value, false ) . '>';
                    $html .= esc_html( $opt_label );
                    $html .= '</option>';
                }
                $html .= '</select>';
                break;

            case 'radio':
                $options = $this->parse_options();
                $html .= '<div class="qaf-radio-group" role="radiogroup" ' . $aria_attrs . '>';
                foreach ( $options as $opt_value => $opt_label ) {
                    $opt_id = $html_id . '-' . sanitize_key( $opt_value );
                    $html .= '<div class="qaf-radio-option">';
                    $html .= '<input type="radio" id="' . esc_attr( $opt_id ) . '"';
                    $html .= ' name="' . esc_attr( $name ) . '"';
                    $html .= ' value="' . esc_attr( $opt_value ) . '"';
                    $html .= checked( $value, $opt_value, false ) . '>';
                    $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $opt_label ) . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;

            case 'checkbox':
                $options = $this->parse_options();
                $values = is_array( $value ) ? $value : array( $value );
                $html .= '<div class="qaf-checkbox-group" role="group" ' . $aria_attrs . '>';
                foreach ( $options as $opt_value => $opt_label ) {
                    $opt_id = $html_id . '-' . sanitize_key( $opt_value );
                    $html .= '<div class="qaf-checkbox-option">';
                    $html .= '<input type="checkbox" id="' . esc_attr( $opt_id ) . '"';
                    $html .= ' name="' . esc_attr( $name ) . '[]"';
                    $html .= ' value="' . esc_attr( $opt_value ) . '"';
                    $html .= ( in_array( $opt_value, $values ) ? ' checked' : '' ) . '>';
                    $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $opt_label ) . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;

            case 'toggle':
                $html .= '<div class="qaf-toggle-wrapper">';
                $html .= '<input type="checkbox" id="' . esc_attr( $html_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                $html .= ' value="1"';
                $html .= ' class="qaf-toggle-input"';
                $html .= ( $value ? ' checked' : '' );
                $html .= ' ' . $aria_attrs . '>';
                $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-toggle-label">';
                $html .= '<span class="qaf-toggle-switch"></span>';
                $html .= esc_html( $label );
                if ( $required ) {
                    $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
                }
                $html .= '</label>';
                $html .= '</div>';
                break;

            case 'number':
                $html .= '<input type="number"';
                $html .= ' id="' . esc_attr( $html_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                $html .= ' value="' . esc_attr( $value ) . '"';
                $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                if ( $this->config['min'] !== '' ) {
                    $html .= ' min="' . esc_attr( $this->config['min'] ) . '"';
                }
                if ( $this->config['max'] !== '' ) {
                    $html .= ' max="' . esc_attr( $this->config['max'] ) . '"';
                }
                if ( $this->config['step'] !== '' ) {
                    $html .= ' step="' . esc_attr( $this->config['step'] ) . '"';
                }
                $html .= ' class="qaf-input"';
                $html .= ' ' . $aria_attrs . '>';
                break;

            default: // text, email, url, date, time, color
                $input_type = in_array( $field_type, array( 'email', 'url', 'date', 'time', 'color' ) ) ? $field_type : 'text';
                $html .= '<input type="' . $input_type . '"';
                $html .= ' id="' . esc_attr( $html_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                $html .= ' value="' . esc_attr( $value ) . '"';
                $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                $html .= ' class="qaf-input"';
                $html .= ' ' . $aria_attrs . '>';
                break;
        }

        // Suffix
        if ( ! empty( $this->config['suffix'] ) && in_array( $field_type, array( 'text', 'number', 'email', 'url' ) ) ) {
            $html .= '<span class="qaf-input-suffix">' . esc_html( $this->config['suffix'] ) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Parse options from config
     *
     * @return array Key-value options.
     */
    protected function parse_options() {
        $options_raw = $this->config['options'] ?? array();

        if ( is_string( $options_raw ) ) {
            $lines = explode( "\n", $options_raw );
            $options = array();
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( empty( $line ) ) continue;
                if ( strpos( $line, '|' ) !== false ) {
                    list( $val, $lbl ) = explode( '|', $line, 2 );
                    $options[ trim( $val ) ] = trim( $lbl );
                } else {
                    $options[ $line ] = $line;
                }
            }
            return $options;
        }

        return $options_raw;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = $this->get_value();
        $field_type = $this->config['field_type'] ?? 'text';

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'This field is required.', 'quill-accessible-forms' ) );
            return false;
        }

        // Type-specific validation
        if ( ! empty( $value ) ) {
            switch ( $field_type ) {
                case 'email':
                    if ( ! is_email( $value ) ) {
                        $this->add_error( __( 'Please enter a valid email address.', 'quill-accessible-forms' ) );
                        return false;
                    }
                    break;

                case 'url':
                    if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                        $this->add_error( __( 'Please enter a valid URL.', 'quill-accessible-forms' ) );
                        return false;
                    }
                    break;

                case 'number':
                    if ( ! is_numeric( $value ) ) {
                        $this->add_error( __( 'Please enter a valid number.', 'quill-accessible-forms' ) );
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        $field_type = $this->config['field_type'] ?? 'text';

        switch ( $field_type ) {
            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'number':
                return is_numeric( $value ) ? floatval( $value ) : '';

            case 'checkbox':
                if ( is_array( $value ) ) {
                    return array_map( 'sanitize_text_field', $value );
                }
                return sanitize_text_field( $value );

            case 'toggle':
                return $value ? 1 : 0;

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Get meta key for storage
     *
     * @return string Meta key.
     */
    public function get_meta_key() {
        $key = $this->config['meta_key'] ?? '';
        if ( ! empty( $this->config['private'] ) && strpos( $key, '_' ) !== 0 ) {
            $key = '_' . $key;
        }
        return $key;
    }

    /**
     * Get post data key
     *
     * @return string Post meta key.
     */
    public function get_post_key() {
        return $this->get_meta_key();
    }
}
