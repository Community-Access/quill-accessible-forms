<?php
/**
 * Summary/Review Field
 *
 * Display all form answers before submission.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Summary class
 */
class QAF_Field_Summary extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'summary';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'id'                => '',
            'title'             => __( 'Review Your Submission', 'quill-accessible-forms' ),
            'description'       => __( 'Please review your answers before submitting.', 'quill-accessible-forms' ),
            'layout'            => 'table',         // 'table', 'cards', 'list'
            'show_empty'        => false,           // Show fields with no value
            'show_hidden'       => false,           // Show hidden fields
            'group_by'          => 'section',       // 'section', 'page', 'none'
            'allow_edit'        => true,            // Show edit links
            'edit_mode'         => 'modal',         // 'modal', 'inline', 'jump'
            'fields_include'    => array(),         // Empty = all fields
            'fields_exclude'    => array(),         // Fields to exclude
            'show_field_icons'  => true,
            'collapsible'       => false,
            'default_collapsed' => false,
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
                'title' => array(
                    'type'  => 'text',
                    'label' => __( 'Title', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'table' => __( 'Table', 'quill-accessible-forms' ),
                        'cards' => __( 'Cards', 'quill-accessible-forms' ),
                        'list'  => __( 'List', 'quill-accessible-forms' ),
                    ),
                ),
                'group_by' => array(
                    'type'    => 'select',
                    'label'   => __( 'Group By', 'quill-accessible-forms' ),
                    'options' => array(
                        'section' => __( 'Section', 'quill-accessible-forms' ),
                        'page'    => __( 'Page', 'quill-accessible-forms' ),
                        'none'    => __( 'No Grouping', 'quill-accessible-forms' ),
                    ),
                ),
                'show_empty' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Empty Fields', 'quill-accessible-forms' ),
                ),
                'show_hidden' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Hidden Fields', 'quill-accessible-forms' ),
                ),
                'show_field_icons' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Field Type Icons', 'quill-accessible-forms' ),
                ),
                'collapsible' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Collapsible Sections', 'quill-accessible-forms' ),
                ),
                'default_collapsed' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Start Collapsed', 'quill-accessible-forms' ),
                ),
            ),
            'editing' => array(
                'allow_edit' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Editing', 'quill-accessible-forms' ),
                ),
                'edit_mode' => array(
                    'type'    => 'select',
                    'label'   => __( 'Edit Mode', 'quill-accessible-forms' ),
                    'options' => array(
                        'modal'  => __( 'Edit in Modal', 'quill-accessible-forms' ),
                        'inline' => __( 'Edit Inline', 'quill-accessible-forms' ),
                        'jump'   => __( 'Jump to Field', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'fields' => array(
                'fields_include' => array(
                    'type'  => 'field_select_multi',
                    'label' => __( 'Include Only These Fields', 'quill-accessible-forms' ),
                    'desc'  => __( 'Leave empty to include all fields', 'quill-accessible-forms' ),
                ),
                'fields_exclude' => array(
                    'type'  => 'field_select_multi',
                    'label' => __( 'Exclude Fields', 'quill-accessible-forms' ),
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
        $html_id          = $this->get_html_id();
        $title            = $this->config['title'] ?? __( 'Review Your Submission', 'quill-accessible-forms' );
        $description      = $this->config['description'] ?? '';
        $layout           = $this->config['layout'] ?? 'table';
        $group_by         = $this->config['group_by'] ?? 'section';
        $show_empty       = $this->config['show_empty'] ?? false;
        $show_hidden      = $this->config['show_hidden'] ?? false;
        $allow_edit       = $this->config['allow_edit'] ?? true;
        $edit_mode        = $this->config['edit_mode'] ?? 'modal';
        $show_icons       = $this->config['show_field_icons'] ?? true;
        $collapsible      = $this->config['collapsible'] ?? false;
        $default_collapsed = $this->config['default_collapsed'] ?? false;

        // Get form data and structure
        $form_data = $this->get_form_data();
        $grouped_data = $this->group_form_data( $form_data, $group_by );

        $wrapper_classes = array(
            'qaf-summary-wrapper',
            'qaf-summary-layout-' . $layout,
        );
        if ( $collapsible ) {
            $wrapper_classes[] = 'qaf-summary-collapsible';
        }

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" id="' . esc_attr( $html_id ) . '">';

        // Header
        $field .= '<div class="qaf-summary-header">';
        if ( $title ) {
            $field .= '<h2 class="qaf-summary-title">' . esc_html( $title ) . '</h2>';
        }
        if ( $description ) {
            $field .= '<p class="qaf-summary-description">' . esc_html( $description ) . '</p>';
        }
        $field .= '</div>';

        // Content
        $field .= '<div class="qaf-summary-content" role="region" aria-label="' . esc_attr__( 'Form summary', 'quill-accessible-forms' ) . '">';

        if ( empty( $grouped_data ) ) {
            $field .= '<p class="qaf-summary-empty">' . esc_html__( 'No data to display.', 'quill-accessible-forms' ) . '</p>';
        } else {
            foreach ( $grouped_data as $group_key => $group ) {
                $field .= $this->render_group( $group_key, $group, $layout, $show_icons, $allow_edit, $edit_mode, $show_empty, $collapsible, $default_collapsed );
            }
        }

        $field .= '</div>';

        // Edit modal container (if modal mode)
        if ( $allow_edit && $edit_mode === 'modal' ) {
            $field .= '<div class="qaf-summary-edit-modal" id="' . esc_attr( $html_id ) . '-edit-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $html_id ) . '-modal-title" hidden>';
            $field .= '<div class="qaf-modal-overlay"></div>';
            $field .= '<div class="qaf-modal-content">';
            $field .= '<div class="qaf-modal-header">';
            $field .= '<h3 id="' . esc_attr( $html_id ) . '-modal-title" class="qaf-modal-title">' . esc_html__( 'Edit Field', 'quill-accessible-forms' ) . '</h3>';
            $field .= '<button type="button" class="qaf-modal-close" aria-label="' . esc_attr__( 'Close', 'quill-accessible-forms' ) . '">&times;</button>';
            $field .= '</div>';
            $field .= '<div class="qaf-modal-body"></div>';
            $field .= '<div class="qaf-modal-footer">';
            $field .= '<button type="button" class="qaf-btn qaf-btn-secondary qaf-modal-cancel">' . esc_html__( 'Cancel', 'quill-accessible-forms' ) . '</button>';
            $field .= '<button type="button" class="qaf-btn qaf-btn-primary qaf-modal-save">' . esc_html__( 'Update', 'quill-accessible-forms' ) . '</button>';
            $field .= '</div>';
            $field .= '</div>';
            $field .= '</div>';
        }

        $field .= '</div>';

        return $field;
    }

    /**
     * Render a group of fields
     *
     * @param string $group_key         Group identifier.
     * @param array  $group             Group data.
     * @param string $layout            Layout style.
     * @param bool   $show_icons        Show field icons.
     * @param bool   $allow_edit        Allow editing.
     * @param string $edit_mode         Edit mode.
     * @param bool   $show_empty        Show empty fields.
     * @param bool   $collapsible       Is collapsible.
     * @param bool   $default_collapsed Default collapsed state.
     * @return string HTML.
     */
    protected function render_group( $group_key, $group, $layout, $show_icons, $allow_edit, $edit_mode, $show_empty, $collapsible, $default_collapsed ) {
        $group_title = $group['title'] ?? '';
        $fields = $group['fields'] ?? array();
        $group_id = 'qaf-summary-group-' . sanitize_key( $group_key );

        // Filter empty if needed
        if ( ! $show_empty ) {
            $fields = array_filter( $fields, function( $field ) {
                return ! $this->is_empty_value( $field['value'] ?? null );
            } );
        }

        if ( empty( $fields ) ) {
            return '';
        }

        $html = '<div class="qaf-summary-group" id="' . esc_attr( $group_id ) . '">';

        // Group header
        if ( $group_title && $group_key !== 'ungrouped' ) {
            if ( $collapsible ) {
                $expanded = $default_collapsed ? 'false' : 'true';
                $html .= '<button type="button" class="qaf-summary-group-toggle" ';
                $html .= 'aria-expanded="' . esc_attr( $expanded ) . '" ';
                $html .= 'aria-controls="' . esc_attr( $group_id ) . '-content">';
                $html .= '<h3 class="qaf-summary-group-title">' . esc_html( $group_title ) . '</h3>';
                $html .= '<span class="qaf-toggle-icon" aria-hidden="true"></span>';
                $html .= '</button>';
            } else {
                $html .= '<h3 class="qaf-summary-group-title">' . esc_html( $group_title ) . '</h3>';
            }
        }

        // Group content
        $content_classes = array( 'qaf-summary-group-content' );
        if ( $collapsible && $default_collapsed ) {
            $content_classes[] = 'qaf-collapsed';
        }

        $html .= '<div class="' . esc_attr( implode( ' ', $content_classes ) ) . '" id="' . esc_attr( $group_id ) . '-content">';

        switch ( $layout ) {
            case 'table':
                $html .= $this->render_table_layout( $fields, $show_icons, $allow_edit, $edit_mode );
                break;

            case 'cards':
                $html .= $this->render_cards_layout( $fields, $show_icons, $allow_edit, $edit_mode );
                break;

            case 'list':
                $html .= $this->render_list_layout( $fields, $show_icons, $allow_edit, $edit_mode );
                break;
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render table layout
     *
     * @param array  $fields     Fields to render.
     * @param bool   $show_icons Show icons.
     * @param bool   $allow_edit Allow editing.
     * @param string $edit_mode  Edit mode.
     * @return string HTML.
     */
    protected function render_table_layout( $fields, $show_icons, $allow_edit, $edit_mode ) {
        $html = '<table class="qaf-summary-table">';
        $html .= '<thead class="screen-reader-text">';
        $html .= '<tr>';
        $html .= '<th scope="col">' . esc_html__( 'Field', 'quill-accessible-forms' ) . '</th>';
        $html .= '<th scope="col">' . esc_html__( 'Value', 'quill-accessible-forms' ) . '</th>';
        if ( $allow_edit ) {
            $html .= '<th scope="col">' . esc_html__( 'Actions', 'quill-accessible-forms' ) . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ( $fields as $field ) {
            $html .= '<tr class="qaf-summary-row" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<th scope="row" class="qaf-summary-label">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="qaf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= esc_html( $field['label'] ?? '' );
            $html .= '</th>';

            $html .= '<td class="qaf-summary-value">';
            $html .= $this->format_display_value( $field['value'] ?? '', $field['type'] ?? 'text' );
            $html .= '</td>';

            if ( $allow_edit ) {
                $html .= '<td class="qaf-summary-actions">';
                $html .= $this->render_edit_button( $field, $edit_mode );
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render cards layout
     *
     * @param array  $fields     Fields to render.
     * @param bool   $show_icons Show icons.
     * @param bool   $allow_edit Allow editing.
     * @param string $edit_mode  Edit mode.
     * @return string HTML.
     */
    protected function render_cards_layout( $fields, $show_icons, $allow_edit, $edit_mode ) {
        $html = '<div class="qaf-summary-cards">';

        foreach ( $fields as $field ) {
            $html .= '<div class="qaf-summary-card" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<div class="qaf-card-header">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="qaf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= '<span class="qaf-card-label">' . esc_html( $field['label'] ?? '' ) . '</span>';
            if ( $allow_edit ) {
                $html .= $this->render_edit_button( $field, $edit_mode );
            }
            $html .= '</div>';

            $html .= '<div class="qaf-card-value">';
            $html .= $this->format_display_value( $field['value'] ?? '', $field['type'] ?? 'text' );
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render list layout
     *
     * @param array  $fields     Fields to render.
     * @param bool   $show_icons Show icons.
     * @param bool   $allow_edit Allow editing.
     * @param string $edit_mode  Edit mode.
     * @return string HTML.
     */
    protected function render_list_layout( $fields, $show_icons, $allow_edit, $edit_mode ) {
        $html = '<dl class="qaf-summary-list">';

        foreach ( $fields as $field ) {
            $html .= '<div class="qaf-summary-item" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<dt class="qaf-summary-term">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="qaf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= esc_html( $field['label'] ?? '' );
            if ( $allow_edit ) {
                $html .= $this->render_edit_button( $field, $edit_mode );
            }
            $html .= '</dt>';

            $html .= '<dd class="qaf-summary-definition">';
            $html .= $this->format_display_value( $field['value'] ?? '', $field['type'] ?? 'text' );
            $html .= '</dd>';

            $html .= '</div>';
        }

        $html .= '</dl>';

        return $html;
    }

    /**
     * Render edit button
     *
     * @param array  $field     Field data.
     * @param string $edit_mode Edit mode.
     * @return string HTML.
     */
    protected function render_edit_button( $field, $edit_mode ) {
        $label = sprintf(
            /* translators: %s: field label */
            __( 'Edit %s', 'quill-accessible-forms' ),
            $field['label'] ?? __( 'field', 'quill-accessible-forms' )
        );

        $html = '<button type="button" class="qaf-summary-edit-btn" ';
        $html .= 'data-field-id="' . esc_attr( $field['id'] ?? '' ) . '" ';
        $html .= 'data-edit-mode="' . esc_attr( $edit_mode ) . '" ';
        $html .= 'aria-label="' . esc_attr( $label ) . '">';
        $html .= '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
        $html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
        $html .= '</button>';

        return $html;
    }

    /**
     * Format display value based on field type
     *
     * @param mixed  $value Field value.
     * @param string $type  Field type.
     * @return string Formatted HTML.
     */
    protected function format_display_value( $value, $type ) {
        if ( $this->is_empty_value( $value ) ) {
            return '<span class="qaf-empty-value">' . esc_html__( '(Not provided)', 'quill-accessible-forms' ) . '</span>';
        }

        switch ( $type ) {
            case 'email':
                return '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';

            case 'url':
                return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';

            case 'tel':
            case 'phone':
                return '<a href="tel:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';

            case 'checkbox':
            case 'toggle':
                return $value ? esc_html__( 'Yes', 'quill-accessible-forms' ) : esc_html__( 'No', 'quill-accessible-forms' );

            case 'rich_text':
                return wp_kses_post( $value );

            case 'file':
            case 'fileupload':
                if ( is_array( $value ) ) {
                    $links = array_map( function( $file ) {
                        return '<a href="' . esc_url( $file['url'] ?? '' ) . '" target="_blank">' . esc_html( $file['name'] ?? __( 'File', 'quill-accessible-forms' ) ) . '</a>';
                    }, $value );
                    return implode( ', ', $links );
                }
                return esc_html( $value );

            case 'image_choice':
                if ( is_array( $value ) ) {
                    return esc_html( implode( ', ', $value ) );
                }
                return esc_html( $value );

            case 'rating':
                $stars = intval( $value );
                $html = '<span class="qaf-rating-display" aria-label="' . sprintf( __( '%d stars', 'quill-accessible-forms' ), $stars ) . '">';
                for ( $i = 1; $i <= 5; $i++ ) {
                    $html .= $i <= $stars ? '★' : '☆';
                }
                $html .= '</span>';
                return $html;

            default:
                if ( is_array( $value ) ) {
                    return esc_html( implode( ', ', $value ) );
                }
                return esc_html( $value );
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty_value( $value ) {
        if ( is_null( $value ) ) {
            return true;
        }
        if ( is_string( $value ) && trim( $value ) === '' ) {
            return true;
        }
        if ( is_array( $value ) && empty( $value ) ) {
            return true;
        }
        return false;
    }

    /**
     * Get form data from context
     *
     * @return array Form field data.
     */
    protected function get_form_data() {
        // This would be populated by the form context at render time
        return $this->config['_form_data'] ?? array();
    }

    /**
     * Group form data
     *
     * @param array  $form_data Form data.
     * @param string $group_by  Grouping method.
     * @return array Grouped data.
     */
    protected function group_form_data( $form_data, $group_by ) {
        if ( $group_by === 'none' ) {
            return array(
                'ungrouped' => array(
                    'title'  => '',
                    'fields' => $form_data,
                ),
            );
        }

        $grouped = array();
        foreach ( $form_data as $field ) {
            $group_key = $group_by === 'section' 
                ? ( $field['section'] ?? 'ungrouped' )
                : ( $field['page'] ?? 'ungrouped' );
            
            $group_title = $group_by === 'section'
                ? ( $field['section_title'] ?? '' )
                : ( $field['page_title'] ?? '' );

            if ( ! isset( $grouped[ $group_key ] ) ) {
                $grouped[ $group_key ] = array(
                    'title'  => $group_title,
                    'fields' => array(),
                );
            }

            $grouped[ $group_key ]['fields'][] = $field;
        }

        return $grouped;
    }

    /**
     * Summary field does not collect data
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }

    /**
     * Summary field has no validation
     *
     * @param mixed $value Value.
     */
    protected function validate_value( $value ) {
        // No validation needed for display-only field
    }
}
