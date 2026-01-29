<?php
/**
 * Summary/Review Field
 *
 * Display all form answers before submission.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Summary class
 */
class BAF_Field_Summary extends BAF_Field {

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
            'title'             => __( 'Review Your Submission', 'bits-accessible-forms' ),
            'description'       => __( 'Please review your answers before submitting.', 'bits-accessible-forms' ),
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
                    'label' => __( 'Title', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
            ),
            'display' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        'table' => __( 'Table', 'bits-accessible-forms' ),
                        'cards' => __( 'Cards', 'bits-accessible-forms' ),
                        'list'  => __( 'List', 'bits-accessible-forms' ),
                    ),
                ),
                'group_by' => array(
                    'type'    => 'select',
                    'label'   => __( 'Group By', 'bits-accessible-forms' ),
                    'options' => array(
                        'section' => __( 'Section', 'bits-accessible-forms' ),
                        'page'    => __( 'Page', 'bits-accessible-forms' ),
                        'none'    => __( 'No Grouping', 'bits-accessible-forms' ),
                    ),
                ),
                'show_empty' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Empty Fields', 'bits-accessible-forms' ),
                ),
                'show_hidden' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Hidden Fields', 'bits-accessible-forms' ),
                ),
                'show_field_icons' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Field Type Icons', 'bits-accessible-forms' ),
                ),
                'collapsible' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Collapsible Sections', 'bits-accessible-forms' ),
                ),
                'default_collapsed' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Start Collapsed', 'bits-accessible-forms' ),
                ),
            ),
            'editing' => array(
                'allow_edit' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Editing', 'bits-accessible-forms' ),
                ),
                'edit_mode' => array(
                    'type'    => 'select',
                    'label'   => __( 'Edit Mode', 'bits-accessible-forms' ),
                    'options' => array(
                        'modal'  => __( 'Edit in Modal', 'bits-accessible-forms' ),
                        'inline' => __( 'Edit Inline', 'bits-accessible-forms' ),
                        'jump'   => __( 'Jump to Field', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'fields' => array(
                'fields_include' => array(
                    'type'  => 'field_select_multi',
                    'label' => __( 'Include Only These Fields', 'bits-accessible-forms' ),
                    'desc'  => __( 'Leave empty to include all fields', 'bits-accessible-forms' ),
                ),
                'fields_exclude' => array(
                    'type'  => 'field_select_multi',
                    'label' => __( 'Exclude Fields', 'bits-accessible-forms' ),
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
        $title            = $this->config['title'] ?? __( 'Review Your Submission', 'bits-accessible-forms' );
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
            'baf-summary-wrapper',
            'baf-summary-layout-' . $layout,
        );
        if ( $collapsible ) {
            $wrapper_classes[] = 'baf-summary-collapsible';
        }

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" id="' . esc_attr( $html_id ) . '">';

        // Header
        $field .= '<div class="baf-summary-header">';
        if ( $title ) {
            $field .= '<h2 class="baf-summary-title">' . esc_html( $title ) . '</h2>';
        }
        if ( $description ) {
            $field .= '<p class="baf-summary-description">' . esc_html( $description ) . '</p>';
        }
        $field .= '</div>';

        // Content
        $field .= '<div class="baf-summary-content" role="region" aria-label="' . esc_attr__( 'Form summary', 'bits-accessible-forms' ) . '">';

        if ( empty( $grouped_data ) ) {
            $field .= '<p class="baf-summary-empty">' . esc_html__( 'No data to display.', 'bits-accessible-forms' ) . '</p>';
        } else {
            foreach ( $grouped_data as $group_key => $group ) {
                $field .= $this->render_group( $group_key, $group, $layout, $show_icons, $allow_edit, $edit_mode, $show_empty, $collapsible, $default_collapsed );
            }
        }

        $field .= '</div>';

        // Edit modal container (if modal mode)
        if ( $allow_edit && $edit_mode === 'modal' ) {
            $field .= '<div class="baf-summary-edit-modal" id="' . esc_attr( $html_id ) . '-edit-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $html_id ) . '-modal-title" hidden>';
            $field .= '<div class="baf-modal-overlay"></div>';
            $field .= '<div class="baf-modal-content">';
            $field .= '<div class="baf-modal-header">';
            $field .= '<h3 id="' . esc_attr( $html_id ) . '-modal-title" class="baf-modal-title">' . esc_html__( 'Edit Field', 'bits-accessible-forms' ) . '</h3>';
            $field .= '<button type="button" class="baf-modal-close" aria-label="' . esc_attr__( 'Close', 'bits-accessible-forms' ) . '">&times;</button>';
            $field .= '</div>';
            $field .= '<div class="baf-modal-body"></div>';
            $field .= '<div class="baf-modal-footer">';
            $field .= '<button type="button" class="baf-btn baf-btn-secondary baf-modal-cancel">' . esc_html__( 'Cancel', 'bits-accessible-forms' ) . '</button>';
            $field .= '<button type="button" class="baf-btn baf-btn-primary baf-modal-save">' . esc_html__( 'Update', 'bits-accessible-forms' ) . '</button>';
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
        $group_id = 'baf-summary-group-' . sanitize_key( $group_key );

        // Filter empty if needed
        if ( ! $show_empty ) {
            $fields = array_filter( $fields, function( $field ) {
                return ! $this->is_empty_value( $field['value'] ?? null );
            } );
        }

        if ( empty( $fields ) ) {
            return '';
        }

        $html = '<div class="baf-summary-group" id="' . esc_attr( $group_id ) . '">';

        // Group header
        if ( $group_title && $group_key !== 'ungrouped' ) {
            if ( $collapsible ) {
                $expanded = $default_collapsed ? 'false' : 'true';
                $html .= '<button type="button" class="baf-summary-group-toggle" ';
                $html .= 'aria-expanded="' . esc_attr( $expanded ) . '" ';
                $html .= 'aria-controls="' . esc_attr( $group_id ) . '-content">';
                $html .= '<h3 class="baf-summary-group-title">' . esc_html( $group_title ) . '</h3>';
                $html .= '<span class="baf-toggle-icon" aria-hidden="true"></span>';
                $html .= '</button>';
            } else {
                $html .= '<h3 class="baf-summary-group-title">' . esc_html( $group_title ) . '</h3>';
            }
        }

        // Group content
        $content_classes = array( 'baf-summary-group-content' );
        if ( $collapsible && $default_collapsed ) {
            $content_classes[] = 'baf-collapsed';
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
        $html = '<table class="baf-summary-table">';
        $html .= '<thead class="screen-reader-text">';
        $html .= '<tr>';
        $html .= '<th scope="col">' . esc_html__( 'Field', 'bits-accessible-forms' ) . '</th>';
        $html .= '<th scope="col">' . esc_html__( 'Value', 'bits-accessible-forms' ) . '</th>';
        if ( $allow_edit ) {
            $html .= '<th scope="col">' . esc_html__( 'Actions', 'bits-accessible-forms' ) . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ( $fields as $field ) {
            $html .= '<tr class="baf-summary-row" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<th scope="row" class="baf-summary-label">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="baf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= esc_html( $field['label'] ?? '' );
            $html .= '</th>';

            $html .= '<td class="baf-summary-value">';
            $html .= $this->format_display_value( $field['value'] ?? '', $field['type'] ?? 'text' );
            $html .= '</td>';

            if ( $allow_edit ) {
                $html .= '<td class="baf-summary-actions">';
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
        $html = '<div class="baf-summary-cards">';

        foreach ( $fields as $field ) {
            $html .= '<div class="baf-summary-card" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<div class="baf-card-header">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="baf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= '<span class="baf-card-label">' . esc_html( $field['label'] ?? '' ) . '</span>';
            if ( $allow_edit ) {
                $html .= $this->render_edit_button( $field, $edit_mode );
            }
            $html .= '</div>';

            $html .= '<div class="baf-card-value">';
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
        $html = '<dl class="baf-summary-list">';

        foreach ( $fields as $field ) {
            $html .= '<div class="baf-summary-item" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
            
            $html .= '<dt class="baf-summary-term">';
            if ( $show_icons && ! empty( $field['icon'] ) ) {
                $html .= '<span class="baf-field-icon dashicons dashicons-' . esc_attr( $field['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= esc_html( $field['label'] ?? '' );
            if ( $allow_edit ) {
                $html .= $this->render_edit_button( $field, $edit_mode );
            }
            $html .= '</dt>';

            $html .= '<dd class="baf-summary-definition">';
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
            __( 'Edit %s', 'bits-accessible-forms' ),
            $field['label'] ?? __( 'field', 'bits-accessible-forms' )
        );

        $html = '<button type="button" class="baf-summary-edit-btn" ';
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
            return '<span class="baf-empty-value">' . esc_html__( '(Not provided)', 'bits-accessible-forms' ) . '</span>';
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
                return $value ? esc_html__( 'Yes', 'bits-accessible-forms' ) : esc_html__( 'No', 'bits-accessible-forms' );

            case 'rich_text':
                return wp_kses_post( $value );

            case 'file':
            case 'fileupload':
                if ( is_array( $value ) ) {
                    $links = array_map( function( $file ) {
                        return '<a href="' . esc_url( $file['url'] ?? '' ) . '" target="_blank">' . esc_html( $file['name'] ?? __( 'File', 'bits-accessible-forms' ) ) . '</a>';
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
                $html = '<span class="baf-rating-display" aria-label="' . sprintf( __( '%d stars', 'bits-accessible-forms' ), $stars ) . '">';
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
