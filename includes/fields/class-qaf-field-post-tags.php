<?php
/**
 * Post Tags Field - WordPress tag selection
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Tags class
 */
class QAF_Field_Post_Tags extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_tags';

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
            'label'         => __( 'Tags', 'quill-accessible-forms' ),
            'description'   => '',
            'taxonomy'      => 'post_tag',
            'display_style' => 'tokens',
            'min_tags'      => 0,
            'max_tags'      => 10,
            'allow_new'     => true,
            'suggestions'   => true,
            'popular_count' => 10,
            'separator'     => ',',
            'placeholder'   => __( 'Add tags...', 'quill-accessible-forms' ),
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
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
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
                        'tokens'     => __( 'Token Input', 'quill-accessible-forms' ),
                        'checkboxes' => __( 'Checkboxes', 'quill-accessible-forms' ),
                        'textarea'   => __( 'Comma-separated Text', 'quill-accessible-forms' ),
                        'cloud'      => __( 'Tag Cloud', 'quill-accessible-forms' ),
                    ),
                ),
                'suggestions' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Suggestions', 'quill-accessible-forms' ),
                ),
                'popular_count' => array(
                    'type'      => 'number',
                    'label'     => __( 'Popular Tags to Show', 'quill-accessible-forms' ),
                    'min'       => 0,
                    'max'       => 50,
                    'condition' => array( 'suggestions' => true ),
                ),
            ),
            'validation' => array(
                'min_tags' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Tags', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_tags' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Tags', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'advanced' => array(
                'allow_new' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Allow New Tags', 'quill-accessible-forms' ),
                    'description' => __( 'Let users create new tags', 'quill-accessible-forms' ),
                ),
                'separator' => array(
                    'type'    => 'select',
                    'label'   => __( 'Tag Separator', 'quill-accessible-forms' ),
                    'options' => array(
                        ','  => __( 'Comma', 'quill-accessible-forms' ),
                        ';'  => __( 'Semicolon', 'quill-accessible-forms' ),
                        ' '  => __( 'Space', 'quill-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Tags', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $display_style = $this->config['display_style'] ?? 'tokens';
        $placeholder = $this->config['placeholder'] ?? '';
        $required = ! empty( $this->config['required'] );
        $value = $this->get_value();
        $max_tags = (int) ( $this->config['max_tags'] ?? 10 );

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-tags', 'qaf-tags-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-max-tags="' . esc_attr( $max_tags ) . '"';
        $html .= ' data-allow-new="' . ( ! empty( $this->config['allow_new'] ) ? 'true' : 'false' ) . '"';
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

        $name = 'qaf_field_' . $this->get_id();
        $tags = $this->get_existing_tags();

        switch ( $display_style ) {
            case 'checkboxes':
                $html .= $this->render_checkboxes( $html_id, $name, $tags, $value, $aria_attrs );
                break;

            case 'cloud':
                $html .= $this->render_cloud( $html_id, $name, $tags, $value, $aria_attrs );
                break;

            case 'textarea':
                $html .= $this->render_textarea( $html_id, $name, $value, $placeholder, $aria_attrs );
                break;

            default: // tokens
                $html .= $this->render_tokens( $html_id, $name, $tags, $value, $placeholder, $aria_attrs );
                break;
        }

        // Suggestions
        if ( ! empty( $this->config['suggestions'] ) ) {
            $popular = $this->get_popular_tags();
            if ( ! empty( $popular ) ) {
                $html .= '<div class="qaf-tag-suggestions">';
                $html .= '<span class="qaf-suggestions-label">' . esc_html__( 'Popular:', 'quill-accessible-forms' ) . '</span>';
                foreach ( $popular as $tag ) {
                    $html .= '<button type="button" class="qaf-suggestion-tag" data-tag="' . esc_attr( $tag->name ) . '">';
                    $html .= esc_html( $tag->name );
                    $html .= '</button>';
                }
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render token-style input
     */
    protected function render_tokens( $html_id, $name, $tags, $value, $placeholder, $aria_attrs ) {
        $values = is_array( $value ) ? $value : ( ! empty( $value ) ? explode( ',', $value ) : array() );
        $values = array_map( 'trim', $values );

        $html = '<div class="qaf-token-input-wrapper">';
        $html .= '<div class="qaf-token-container">';

        // Existing tokens
        foreach ( $values as $v ) {
            if ( empty( $v ) ) continue;
            $html .= '<span class="qaf-token">';
            $html .= esc_html( $v );
            $html .= '<button type="button" class="qaf-token-remove" aria-label="' . esc_attr( sprintf( __( 'Remove %s', 'quill-accessible-forms' ), $v ) ) . '">&times;</button>';
            $html .= '</span>';
        }

        // Input for new tags
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="qaf-token-input"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' autocomplete="off"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '</div>';

        // Hidden input for actual value
        $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $values ) ) . '" data-tags-value>';

        // Autocomplete dropdown
        $html .= '<ul class="qaf-tag-autocomplete" role="listbox" hidden></ul>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Render checkboxes
     */
    protected function render_checkboxes( $html_id, $name, $tags, $value, $aria_attrs ) {
        $values = is_array( $value ) ? $value : ( ! empty( $value ) ? explode( ',', $value ) : array() );
        $values = array_map( 'trim', $values );

        $html = '<div class="qaf-tag-checkbox-group" role="group" ' . $aria_attrs . '>';

        foreach ( $tags as $tag ) {
            $checked = in_array( $tag->name, $values ) || in_array( $tag->term_id, $values ) ? ' checked' : '';
            $opt_id = $html_id . '-' . $tag->term_id;

            $html .= '<div class="qaf-checkbox-option">';
            $html .= '<input type="checkbox"';
            $html .= ' id="' . esc_attr( $opt_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '[]"';
            $html .= ' value="' . esc_attr( $tag->name ) . '"';
            $html .= $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $tag->name ) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render tag cloud
     */
    protected function render_cloud( $html_id, $name, $tags, $value, $aria_attrs ) {
        $values = is_array( $value ) ? $value : ( ! empty( $value ) ? explode( ',', $value ) : array() );
        $values = array_map( 'trim', $values );

        $html = '<div class="qaf-tag-cloud" role="group" ' . $aria_attrs . '>';

        // Get count range for sizing
        $counts = wp_list_pluck( $tags, 'count' );
        $min_count = min( $counts ) ?: 1;
        $max_count = max( $counts ) ?: 1;

        foreach ( $tags as $tag ) {
            $checked = in_array( $tag->name, $values ) || in_array( $tag->term_id, $values );
            $opt_id = $html_id . '-' . $tag->term_id;

            // Calculate font size
            $size = $max_count > $min_count 
                ? 0.8 + ( ( $tag->count - $min_count ) / ( $max_count - $min_count ) ) * 0.8
                : 1;

            $html .= '<span class="qaf-cloud-tag' . ( $checked ? ' selected' : '' ) . '" style="font-size: ' . esc_attr( $size ) . 'em;">';
            $html .= '<input type="checkbox"';
            $html .= ' id="' . esc_attr( $opt_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '[]"';
            $html .= ' value="' . esc_attr( $tag->name ) . '"';
            $html .= ' class="sr-only"';
            $html .= ( $checked ? ' checked' : '' ) . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $tag->name ) . '</label>';
            $html .= '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render simple textarea
     */
    protected function render_textarea( $html_id, $name, $value, $placeholder, $aria_attrs ) {
        $separator = $this->config['separator'] ?? ',';
        $val = is_array( $value ) ? implode( $separator . ' ', $value ) : $value;

        $html = '<textarea';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' class="qaf-textarea qaf-tags-textarea"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' rows="2"';
        $html .= ' ' . $aria_attrs;
        $html .= '>' . esc_textarea( $val ) . '</textarea>';

        $html .= '<p class="qaf-hint">' . esc_html__( 'Separate tags with commas', 'quill-accessible-forms' ) . '</p>';

        return $html;
    }

    /**
     * Get existing tags
     */
    protected function get_existing_tags() {
        $taxonomy = $this->config['taxonomy'] ?? 'post_tag';
        return get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
        ) );
    }

    /**
     * Get popular tags
     */
    protected function get_popular_tags() {
        $taxonomy = $this->config['taxonomy'] ?? 'post_tag';
        $count = (int) ( $this->config['popular_count'] ?? 10 );

        return get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => $count,
        ) );
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = $this->get_value();
        $tags = is_array( $value ) ? $value : ( ! empty( $value ) ? explode( ',', $value ) : array() );
        $tags = array_filter( array_map( 'trim', $tags ) );
        $count = count( $tags );

        $min = (int) ( $this->config['min_tags'] ?? 0 );
        $max = (int) ( $this->config['max_tags'] ?? 10 );

        if ( ! empty( $this->config['required'] ) && $count === 0 ) {
            $this->add_error( __( 'Please add at least one tag.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( $min > 0 && $count < $min ) {
            /* translators: %d: minimum tags */
            $this->add_error( sprintf( __( 'Please add at least %d tags.', 'quill-accessible-forms' ), $min ) );
            return false;
        }

        if ( $count > $max ) {
            /* translators: %d: maximum tags */
            $this->add_error( sprintf( __( 'Please add no more than %d tags.', 'quill-accessible-forms' ), $max ) );
            return false;
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized tags.
     */
    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', array_filter( array_map( 'trim', $value ) ) );
        }

        $separator = $this->config['separator'] ?? ',';
        $tags = explode( $separator, $value );
        return array_map( 'sanitize_text_field', array_filter( array_map( 'trim', $tags ) ) );
    }

    /**
     * Get post data key
     *
     * @return string Post array key.
     */
    public function get_post_key() {
        return 'tags_input';
    }
}
