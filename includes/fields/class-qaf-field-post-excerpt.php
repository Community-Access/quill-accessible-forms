<?php
/**
 * Post Excerpt Field - WordPress post excerpt
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Excerpt class
 */
class QAF_Field_Post_Excerpt extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_excerpt';

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
            'label'           => __( 'Excerpt', 'quill-accessible-forms' ),
            'description'     => __( 'A brief summary of your content', 'quill-accessible-forms' ),
            'placeholder'     => __( 'Enter a brief excerpt...', 'quill-accessible-forms' ),
            'default_value'   => '',
            'rows'            => 3,
            'min_length'      => 0,
            'max_length'      => 300,
            'show_counter'    => true,
            'auto_generate'   => false,
            'generate_length' => 55,
            'required'        => false,
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
            'appearance' => array(
                'rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Rows', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 10,
                ),
                'show_counter' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Character Counter', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'auto' => array(
                'auto_generate' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Auto-generate from Content', 'quill-accessible-forms' ),
                    'description' => __( 'Generate excerpt from content if left empty', 'quill-accessible-forms' ),
                ),
                'generate_length' => array(
                    'type'      => 'number',
                    'label'     => __( 'Generated Length (words)', 'quill-accessible-forms' ),
                    'min'       => 10,
                    'max'       => 200,
                    'condition' => array( 'auto_generate' => true ),
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
        $label = $this->config['label'] ?? __( 'Excerpt', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value() ?: ( $this->config['default_value'] ?? '' );
        $rows = (int) ( $this->config['rows'] ?? 3 );
        $max_length = (int) ( $this->config['max_length'] ?? 300 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-excerpt' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

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

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $html .= '<textarea';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' rows="' . esc_attr( $rows ) . '"';
        $html .= ' maxlength="' . esc_attr( $max_length ) . '"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' class="qaf-textarea qaf-post-excerpt-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>' . esc_textarea( $value ) . '</textarea>';

        // Character counter
        if ( ! empty( $this->config['show_counter'] ) ) {
            $html .= '<div class="qaf-char-counter" aria-live="polite">';
            $html .= '<span class="qaf-char-current">' . strlen( $value ) . '</span>';
            $html .= ' / <span class="qaf-char-max">' . esc_html( $max_length ) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = trim( $this->get_value() );
        $min_length = (int) ( $this->config['min_length'] ?? 0 );
        $max_length = (int) ( $this->config['max_length'] ?? 300 );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter an excerpt.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            $length = mb_strlen( $value );

            if ( $min_length > 0 && $length < $min_length ) {
                /* translators: %d: minimum characters */
                $this->add_error( sprintf( __( 'Excerpt must be at least %d characters.', 'quill-accessible-forms' ), $min_length ) );
                return false;
            }

            if ( $length > $max_length ) {
                /* translators: %d: maximum characters */
                $this->add_error( sprintf( __( 'Excerpt cannot exceed %d characters.', 'quill-accessible-forms' ), $max_length ) );
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized excerpt.
     */
    public function sanitize( $value ) {
        return sanitize_textarea_field( trim( $value ) );
    }

    /**
     * Get post data key
     *
     * @return string Post array key.
     */
    public function get_post_key() {
        return 'post_excerpt';
    }
}
