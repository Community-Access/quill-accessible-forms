<?php
/**
 * Post Tags Field - WordPress tag selection
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Post_Tags class
 */
class BAF_Field_Post_Tags extends BAF_Field {

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
            'label'         => __( 'Tags', 'bits-accessible-forms' ),
            'description'   => '',
            'taxonomy'      => 'post_tag',
            'display_style' => 'tokens',
            'min_tags'      => 0,
            'max_tags'      => 10,
            'allow_new'     => true,
            'suggestions'   => true,
            'popular_count' => 10,
            'separator'     => ',',
            'placeholder'   => __( 'Add tags...', 'bits-accessible-forms' ),
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'tokens'     => __( 'Token Input', 'bits-accessible-forms' ),
                        'checkboxes' => __( 'Checkboxes', 'bits-accessible-forms' ),
                        'textarea'   => __( 'Comma-separated Text', 'bits-accessible-forms' ),
                        'cloud'      => __( 'Tag Cloud', 'bits-accessible-forms' ),
                    ),
                ),
                'suggestions' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Suggestions', 'bits-accessible-forms' ),
                ),
                'popular_count' => array(
                    'type'      => 'number',
                    'label'     => __( 'Popular Tags to Show', 'bits-accessible-forms' ),
                    'min'       => 0,
                    'max'       => 50,
                    'condition' => array( 'suggestions' => true ),
                ),
            ),
            'validation' => array(
                'min_tags' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Tags', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_tags' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Tags', 'bits-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'advanced' => array(
                'allow_new' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Allow New Tags', 'bits-accessible-forms' ),
                    'description' => __( 'Let users create new tags', 'bits-accessible-forms' ),
                ),
                'separator' => array(
                    'type'    => 'select',
                    'label'   => __( 'Tag Separator', 'bits-accessible-forms' ),
                    'options' => array(
                        ','  => __( 'Comma', 'bits-accessible-forms' ),
                        ';'  => __( 'Semicolon', 'bits-accessible-forms' ),
                        ' '  => __( 'Space', 'bits-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Tags', 'bits-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $display_style = $this->config['display_style'] ?? 'tokens';
        $placeholder = $this->config['placeholder'] ?? '';
        $required = ! empty( $this->config['required'] );
        $value = $this->get_value();
        $max_tags = (int) ( $this->config['max_tags'] ?? 10 );

        $wrapper_class = array( 'baf-field', 'baf-field-post-tags', 'baf-tags-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-max-tags="' . esc_attr( $max_tags ) . '"';
        $html .= ' data-allow-new="' . ( ! empty( $this->config['allow_new'] ) ? 'true' : 'false' ) . '"';
        $html .= '>';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="baf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $name = 'baf_field_' . $this->get_id();
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
                $html .= '<div class="baf-tag-suggestions">';
                $html .= '<span class="baf-suggestions-label">' . esc_html__( 'Popular:', 'bits-accessible-forms' ) . '</span>';
                foreach ( $popular as $tag ) {
                    $html .= '<button type="button" class="baf-suggestion-tag" data-tag="' . esc_attr( $tag->name ) . '">';
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

        $html = '<div class="baf-token-input-wrapper">';
        $html .= '<div class="baf-token-container">';

        // Existing tokens
        foreach ( $values as $v ) {
            if ( empty( $v ) ) continue;
            $html .= '<span class="baf-token">';
            $html .= esc_html( $v );
            $html .= '<button type="button" class="baf-token-remove" aria-label="' . esc_attr( sprintf( __( 'Remove %s', 'bits-accessible-forms' ), $v ) ) . '">&times;</button>';
            $html .= '</span>';
        }

        // Input for new tags
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="baf-token-input"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' autocomplete="off"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '</div>';

        // Hidden input for actual value
        $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $values ) ) . '" data-tags-value>';

        // Autocomplete dropdown
        $html .= '<ul class="baf-tag-autocomplete" role="listbox" hidden></ul>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Render checkboxes
     */
    protected function render_checkboxes( $html_id, $name, $tags, $value, $aria_attrs ) {
        $values = is_array( $value ) ? $value : ( ! empty( $value ) ? explode( ',', $value ) : array() );
        $values = array_map( 'trim', $values );

        $html = '<div class="baf-tag-checkbox-group" role="group" ' . $aria_attrs . '>';

        foreach ( $tags as $tag ) {
            $checked = in_array( $tag->name, $values ) || in_array( $tag->term_id, $values ) ? ' checked' : '';
            $opt_id = $html_id . '-' . $tag->term_id;

            $html .= '<div class="baf-checkbox-option">';
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

        $html = '<div class="baf-tag-cloud" role="group" ' . $aria_attrs . '>';

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

            $html .= '<span class="baf-cloud-tag' . ( $checked ? ' selected' : '' ) . '" style="font-size: ' . esc_attr( $size ) . 'em;">';
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
        $html .= ' class="baf-textarea baf-tags-textarea"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' rows="2"';
        $html .= ' ' . $aria_attrs;
        $html .= '>' . esc_textarea( $val ) . '</textarea>';

        $html .= '<p class="baf-hint">' . esc_html__( 'Separate tags with commas', 'bits-accessible-forms' ) . '</p>';

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
            $this->add_error( __( 'Please add at least one tag.', 'bits-accessible-forms' ) );
            return false;
        }

        if ( $min > 0 && $count < $min ) {
            /* translators: %d: minimum tags */
            $this->add_error( sprintf( __( 'Please add at least %d tags.', 'bits-accessible-forms' ), $min ) );
            return false;
        }

        if ( $count > $max ) {
            /* translators: %d: maximum tags */
            $this->add_error( sprintf( __( 'Please add no more than %d tags.', 'bits-accessible-forms' ), $max ) );
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
