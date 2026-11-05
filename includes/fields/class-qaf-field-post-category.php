<?php
/**
 * Post Category Field - WordPress category selection
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Category class
 */
class QAF_Field_Post_Category extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_category';

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
            'label'             => __( 'Category', 'quill-accessible-forms' ),
            'description'       => '',
            'taxonomy'          => 'category',
            'display_style'     => 'dropdown',
            'multiple'          => false,
            'hierarchical'      => true,
            'show_count'        => false,
            'hide_empty'        => false,
            'include'           => array(),
            'exclude'           => array(),
            'default_category'  => 0,
            'allow_new'         => false,
            'placeholder'       => __( 'Select a category', 'quill-accessible-forms' ),
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'taxonomy' => array(
                'taxonomy' => array(
                    'type'    => 'select',
                    'label'   => __( 'Taxonomy', 'quill-accessible-forms' ),
                    'options' => 'taxonomies', // Dynamic options
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'dropdown'  => __( 'Dropdown', 'quill-accessible-forms' ),
                        'radio'     => __( 'Radio Buttons', 'quill-accessible-forms' ),
                        'checkbox'  => __( 'Checkboxes', 'quill-accessible-forms' ),
                        'tree'      => __( 'Hierarchical Tree', 'quill-accessible-forms' ),
                        'searchable' => __( 'Searchable Dropdown', 'quill-accessible-forms' ),
                    ),
                ),
                'multiple' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Allow Multiple Selection', 'quill-accessible-forms' ),
                    'condition' => array( 'display_style' => array( 'dropdown', 'checkbox', 'tree', 'searchable' ) ),
                ),
                'hierarchical' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show as Hierarchy', 'quill-accessible-forms' ),
                ),
                'show_count' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Post Count', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
            ),
            'filtering' => array(
                'hide_empty' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Hide Empty Categories', 'quill-accessible-forms' ),
                ),
                'include' => array(
                    'type'        => 'text',
                    'label'       => __( 'Include Only (IDs)', 'quill-accessible-forms' ),
                    'description' => __( 'Comma-separated category IDs', 'quill-accessible-forms' ),
                ),
                'exclude' => array(
                    'type'        => 'text',
                    'label'       => __( 'Exclude (IDs)', 'quill-accessible-forms' ),
                    'description' => __( 'Comma-separated category IDs to exclude', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'allow_new' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Allow New Categories', 'quill-accessible-forms' ),
                    'description' => __( 'Let users create new categories', 'quill-accessible-forms' ),
                ),
                'default_category' => array(
                    'type'    => 'select',
                    'label'   => __( 'Default Category', 'quill-accessible-forms' ),
                    'options' => 'categories',
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
        $label = $this->config['label'] ?? __( 'Category', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $taxonomy = $this->config['taxonomy'] ?? 'category';
        $display_style = $this->config['display_style'] ?? 'dropdown';
        $multiple = ! empty( $this->config['multiple'] );
        $required = ! empty( $this->config['required'] );
        $value = $this->get_value();

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-category', 'qaf-category-' . $display_style );
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

        // Get categories
        $categories = $this->get_categories( $taxonomy );
        $name = 'qaf_field_' . $this->get_id() . ( $multiple ? '[]' : '' );

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        switch ( $display_style ) {
            case 'radio':
                $html .= $this->render_radio( $html_id, $name, $categories, $value, $aria_attrs );
                break;

            case 'checkbox':
                $html .= $this->render_checkboxes( $html_id, $name, $categories, $value, $aria_attrs );
                break;

            case 'tree':
                $html .= $this->render_tree( $html_id, $name, $categories, $value, $multiple, $aria_attrs );
                break;

            default: // dropdown
                $html .= $this->render_dropdown( $html_id, $name, $categories, $value, $multiple, $aria_attrs );
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get categories for the taxonomy
     *
     * @param string $taxonomy Taxonomy name.
     * @return array Categories.
     */
    protected function get_categories( $taxonomy ) {
        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => ! empty( $this->config['hide_empty'] ),
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if ( ! empty( $this->config['include'] ) ) {
            $args['include'] = array_map( 'intval', explode( ',', $this->config['include'] ) );
        }

        if ( ! empty( $this->config['exclude'] ) ) {
            $args['exclude'] = array_map( 'intval', explode( ',', $this->config['exclude'] ) );
        }

        return get_terms( $args );
    }

    /**
     * Render dropdown
     */
    protected function render_dropdown( $html_id, $name, $categories, $value, $multiple, $aria_attrs ) {
        $placeholder = $this->config['placeholder'] ?? __( 'Select a category', 'quill-accessible-forms' );
        $show_count = ! empty( $this->config['show_count'] );

        $html = '<select';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' class="qaf-select qaf-category-select"';
        if ( $multiple ) {
            $html .= ' multiple';
        }
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        if ( ! $multiple ) {
            $html .= '<option value="">' . esc_html( $placeholder ) . '</option>';
        }

        foreach ( $categories as $cat ) {
            $selected = is_array( $value ) ? in_array( $cat->term_id, $value ) : ( $value == $cat->term_id );
            $label = $cat->name;
            if ( $show_count ) {
                $label .= ' (' . $cat->count . ')';
            }
            $html .= '<option value="' . esc_attr( $cat->term_id ) . '"' . selected( $selected, true, false ) . '>';
            $html .= esc_html( $label );
            $html .= '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Render radio buttons
     */
    protected function render_radio( $html_id, $name, $categories, $value, $aria_attrs ) {
        $show_count = ! empty( $this->config['show_count'] );

        $html = '<div class="qaf-category-radio-group" role="radiogroup" ' . $aria_attrs . '>';

        foreach ( $categories as $cat ) {
            $checked = ( $value == $cat->term_id ) ? ' checked' : '';
            $opt_id = $html_id . '-' . $cat->term_id;
            $label = $cat->name;
            if ( $show_count ) {
                $label .= ' (' . $cat->count . ')';
            }

            $html .= '<div class="qaf-radio-option">';
            $html .= '<input type="radio"';
            $html .= ' id="' . esc_attr( $opt_id ) . '"';
            $html .= ' name="' . esc_attr( str_replace( '[]', '', $name ) ) . '"';
            $html .= ' value="' . esc_attr( $cat->term_id ) . '"';
            $html .= $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $label ) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render checkboxes
     */
    protected function render_checkboxes( $html_id, $name, $categories, $value, $aria_attrs ) {
        $show_count = ! empty( $this->config['show_count'] );
        $values = is_array( $value ) ? $value : array( $value );

        $html = '<div class="qaf-category-checkbox-group" role="group" ' . $aria_attrs . '>';

        foreach ( $categories as $cat ) {
            $checked = in_array( $cat->term_id, $values ) ? ' checked' : '';
            $opt_id = $html_id . '-' . $cat->term_id;
            $label = $cat->name;
            if ( $show_count ) {
                $label .= ' (' . $cat->count . ')';
            }

            $html .= '<div class="qaf-checkbox-option">';
            $html .= '<input type="checkbox"';
            $html .= ' id="' . esc_attr( $opt_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $cat->term_id ) . '"';
            $html .= $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $label ) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render hierarchical tree
     */
    protected function render_tree( $html_id, $name, $categories, $value, $multiple, $aria_attrs ) {
        $html = '<ul class="qaf-category-tree" role="tree" ' . $aria_attrs . '>';
        $html .= $this->render_tree_items( $html_id, $name, $categories, $value, $multiple, 0 );
        $html .= '</ul>';
        return $html;
    }

    /**
     * Render tree items recursively
     */
    protected function render_tree_items( $html_id, $name, $categories, $value, $multiple, $parent ) {
        $html = '';
        $values = is_array( $value ) ? $value : array( $value );

        foreach ( $categories as $cat ) {
            if ( $cat->parent != $parent ) {
                continue;
            }

            $checked = in_array( $cat->term_id, $values ) ? ' checked' : '';
            $opt_id = $html_id . '-' . $cat->term_id;
            $input_type = $multiple ? 'checkbox' : 'radio';

            $html .= '<li class="qaf-tree-item" role="treeitem">';
            $html .= '<input type="' . $input_type . '"';
            $html .= ' id="' . esc_attr( $opt_id ) . '"';
            $html .= ' name="' . esc_attr( $multiple ? $name : str_replace( '[]', '', $name ) ) . '"';
            $html .= ' value="' . esc_attr( $cat->term_id ) . '"';
            $html .= $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $cat->name ) . '</label>';

            // Child items
            $children = $this->render_tree_items( $html_id, $name, $categories, $value, $multiple, $cat->term_id );
            if ( ! empty( $children ) ) {
                $html .= '<ul class="qaf-tree-children" role="group">' . $children . '</ul>';
            }

            $html .= '</li>';
        }

        return $html;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = $this->get_value();

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please select a category.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return array|int Sanitized category ID(s).
     */
    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'absint', $value );
        }
        return absint( $value );
    }

    /**
     * Get post data key
     *
     * @return string Post array key.
     */
    public function get_post_key() {
        return 'post_category';
    }
}
