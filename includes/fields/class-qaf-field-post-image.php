<?php
/**
 * Post Image Field - WordPress featured image
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Image class
 */
class QAF_Field_Post_Image extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_image';

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
            'label'           => __( 'Featured Image', 'quill-accessible-forms' ),
            'description'     => '',
            'display_style'   => 'dropzone',
            'preview_size'    => 'medium',
            'allowed_types'   => 'jpg,jpeg,png,gif,webp',
            'max_file_size'   => 5,
            'min_width'       => 0,
            'min_height'      => 0,
            'max_width'       => 0,
            'max_height'      => 0,
            'aspect_ratio'    => '',
            'crop_enabled'    => false,
            'crop_aspect'     => '16:9',
            'button_text'     => __( 'Upload Image', 'quill-accessible-forms' ),
            'change_text'     => __( 'Change Image', 'quill-accessible-forms' ),
            'remove_text'     => __( 'Remove', 'quill-accessible-forms' ),
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'dropzone' => __( 'Drop Zone', 'quill-accessible-forms' ),
                        'button'   => __( 'Button Only', 'quill-accessible-forms' ),
                        'inline'   => __( 'Inline Preview', 'quill-accessible-forms' ),
                    ),
                ),
                'preview_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Preview Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'thumbnail' => __( 'Thumbnail', 'quill-accessible-forms' ),
                        'medium'    => __( 'Medium', 'quill-accessible-forms' ),
                        'large'     => __( 'Large', 'quill-accessible-forms' ),
                    ),
                ),
                'button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Upload Button Text', 'quill-accessible-forms' ),
                ),
                'change_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Change Button Text', 'quill-accessible-forms' ),
                ),
            ),
            'file' => array(
                'allowed_types' => array(
                    'type'        => 'text',
                    'label'       => __( 'Allowed File Types', 'quill-accessible-forms' ),
                    'description' => __( 'Comma-separated extensions', 'quill-accessible-forms' ),
                ),
                'max_file_size' => array(
                    'type'        => 'number',
                    'label'       => __( 'Max File Size (MB)', 'quill-accessible-forms' ),
                    'min'         => 0.1,
                    'max'         => 100,
                    'step'        => '0.1',
                ),
            ),
            'dimensions' => array(
                'min_width' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Width (px)', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'min_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Height (px)', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_width' => array(
                    'type'        => 'number',
                    'label'       => __( 'Maximum Width (px)', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'description' => __( '0 for no limit', 'quill-accessible-forms' ),
                ),
                'max_height' => array(
                    'type'        => 'number',
                    'label'       => __( 'Maximum Height (px)', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'description' => __( '0 for no limit', 'quill-accessible-forms' ),
                ),
                'aspect_ratio' => array(
                    'type'    => 'select',
                    'label'   => __( 'Required Aspect Ratio', 'quill-accessible-forms' ),
                    'options' => array(
                        ''     => __( 'Any', 'quill-accessible-forms' ),
                        '1:1'  => __( 'Square (1:1)', 'quill-accessible-forms' ),
                        '4:3'  => __( 'Standard (4:3)', 'quill-accessible-forms' ),
                        '16:9' => __( 'Widescreen (16:9)', 'quill-accessible-forms' ),
                        '3:2'  => __( 'Photo (3:2)', 'quill-accessible-forms' ),
                        '2:3'  => __( 'Portrait (2:3)', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'crop' => array(
                'crop_enabled' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Image Cropping', 'quill-accessible-forms' ),
                ),
                'crop_aspect' => array(
                    'type'      => 'select',
                    'label'     => __( 'Crop Aspect Ratio', 'quill-accessible-forms' ),
                    'options'   => array(
                        'free' => __( 'Free', 'quill-accessible-forms' ),
                        '1:1'  => __( 'Square (1:1)', 'quill-accessible-forms' ),
                        '4:3'  => __( 'Standard (4:3)', 'quill-accessible-forms' ),
                        '16:9' => __( 'Widescreen (16:9)', 'quill-accessible-forms' ),
                        '3:2'  => __( 'Photo (3:2)', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'crop_enabled' => true ),
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
        $label = $this->config['label'] ?? __( 'Featured Image', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $display_style = $this->config['display_style'] ?? 'dropzone';
        $button_text = $this->config['button_text'] ?? __( 'Upload Image', 'quill-accessible-forms' );
        $required = ! empty( $this->config['required'] );
        $value = $this->get_value();

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-image', 'qaf-image-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $allowed_types = $this->config['allowed_types'] ?? 'jpg,jpeg,png,gif,webp';
        $max_size = (float) ( $this->config['max_file_size'] ?? 5 ) * 1024 * 1024;

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-allowed-types="' . esc_attr( $allowed_types ) . '"';
        $html .= ' data-max-size="' . esc_attr( $max_size ) . '"';
        if ( ! empty( $this->config['min_width'] ) ) {
            $html .= ' data-min-width="' . esc_attr( $this->config['min_width'] ) . '"';
        }
        if ( ! empty( $this->config['min_height'] ) ) {
            $html .= ' data-min-height="' . esc_attr( $this->config['min_height'] ) . '"';
        }
        $html .= '>';

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

        // Dropzone
        if ( $display_style === 'dropzone' ) {
            $html .= '<div class="qaf-dropzone" data-dropzone>';
            $html .= '<div class="qaf-dropzone-content">';
            $html .= '<span class="qaf-dropzone-icon" aria-hidden="true">📷</span>';
            $html .= '<span class="qaf-dropzone-text">' . esc_html__( 'Drag and drop an image here', 'quill-accessible-forms' ) . '</span>';
            $html .= '<span class="qaf-dropzone-or">' . esc_html__( 'or', 'quill-accessible-forms' ) . '</span>';
        }

        // File input
        $accept = $this->get_accept_string( $allowed_types );
        $html .= '<input type="file"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' accept="' . esc_attr( $accept ) . '"';
        $html .= ' class="qaf-image-input' . ( $display_style === 'dropzone' ? ' sr-only' : '' ) . '"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        if ( $display_style === 'dropzone' || $display_style === 'button' ) {
            $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-image-button">';
            $html .= esc_html( $button_text );
            $html .= '</label>';
        }

        if ( $display_style === 'dropzone' ) {
            $html .= '</div>'; // .qaf-dropzone-content
            $html .= '</div>'; // .qaf-dropzone
        }

        // Preview area
        $html .= '<div class="qaf-image-preview" data-image-preview' . ( empty( $value ) ? ' hidden' : '' ) . '>';
        if ( ! empty( $value ) && is_numeric( $value ) ) {
            $preview_size = $this->config['preview_size'] ?? 'medium';
            $html .= wp_get_attachment_image( $value, $preview_size, false, array( 'class' => 'qaf-preview-img' ) );
        }
        $html .= '<button type="button" class="qaf-image-remove" aria-label="' . esc_attr( $this->config['remove_text'] ?? __( 'Remove', 'quill-accessible-forms' ) ) . '">';
        $html .= '<span aria-hidden="true">&times;</span>';
        $html .= '</button>';
        $html .= '</div>';

        // Hidden input for attachment ID
        $html .= '<input type="hidden" name="qaf_field_' . esc_attr( $this->get_id() ) . '_id" value="' . esc_attr( $value ) . '" data-image-id>';

        // File info
        $html .= '<p class="qaf-file-hints">';
        /* translators: 1: allowed file types, 2: max file size in MB */
        $html .= sprintf(
            esc_html__( 'Allowed: %1$s. Max size: %2$sMB', 'quill-accessible-forms' ),
            strtoupper( $allowed_types ),
            $this->config['max_file_size'] ?? 5
        );
        $html .= '</p>';

        // Status area
        $html .= '<div class="qaf-image-status" role="status" aria-live="polite"></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get accept string for file input
     *
     * @param string $types Comma-separated extensions.
     * @return string Accept attribute value.
     */
    protected function get_accept_string( $types ) {
        $extensions = array_map( 'trim', explode( ',', $types ) );
        $accept = array();

        foreach ( $extensions as $ext ) {
            $accept[] = '.' . ltrim( $ext, '.' );
        }

        return implode( ',', $accept );
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = $this->get_value();

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please upload an image.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return int Attachment ID.
     */
    public function sanitize( $value ) {
        return absint( $value );
    }

    /**
     * Get post data key
     *
     * @return string Post meta key.
     */
    public function get_post_key() {
        return '_thumbnail_id';
    }
}
