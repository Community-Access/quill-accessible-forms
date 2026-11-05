<?php
/**
 * Table View
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_View_Table class
 * 
 * Renders entries in an accessible table format.
 */
class QAF_View_Table extends QAF_View_Renderer {

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'table';
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'sticky_header'    => true,
            'row_actions'      => true,
            'bulk_actions'     => true,
            'highlight_unread' => true,
            'zebra_striping'   => true,
        ) );
    }

    /**
     * Render view content
     *
     * @return string HTML output.
     */
    protected function render_content() {
        $columns = $this->get_columns();

        if ( empty( $this->entries ) ) {
            return $this->render_empty_state();
        }

        $table_class = 'qaf-entries-table';
        if ( $this->config['sticky_header'] ) {
            $table_class .= ' qaf-sticky-header';
        }
        if ( $this->config['zebra_striping'] ) {
            $table_class .= ' qaf-zebra';
        }

        $output = '<div class="qaf-table-wrapper" role="region" aria-label="' . esc_attr__( 'Entries table', 'quill-accessible-forms' ) . '" tabindex="0">';
        $output .= '<table class="' . esc_attr( $table_class ) . '">';
        $output .= $this->render_table_header( $columns );
        $output .= $this->render_table_body( $columns );
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render table header
     *
     * @param array $columns Column configuration.
     * @return string HTML output.
     */
    private function render_table_header( $columns ) {
        $output = '<thead><tr>';

        // Checkbox column for bulk actions
        if ( $this->config['bulk_actions'] ) {
            $output .= sprintf(
                '<th scope="col" class="qaf-col-checkbox">
                    <input type="checkbox" id="qaf-select-all" aria-label="%s">
                    <label for="qaf-select-all" class="screen-reader-text">%s</label>
                </th>',
                esc_attr__( 'Select all entries', 'quill-accessible-forms' ),
                esc_html__( 'Select all entries', 'quill-accessible-forms' )
            );
        }

        // Data columns
        foreach ( $columns as $id => $column ) {
            $sortable = isset( $column['sortable'] ) && $column['sortable'];
            $label = $column['label'] ?? $id;
            $th_class = 'qaf-col-' . sanitize_html_class( $id );

            if ( $sortable ) {
                $output .= sprintf(
                    '<th scope="col" class="%s qaf-sortable" aria-sort="none">
                        <button type="button" class="qaf-sort-button" data-column="%s">
                            %s
                            <span class="qaf-sort-indicator" aria-hidden="true"></span>
                        </button>
                    </th>',
                    esc_attr( $th_class ),
                    esc_attr( $id ),
                    esc_html( $label )
                );
            } else {
                $output .= sprintf(
                    '<th scope="col" class="%s">%s</th>',
                    esc_attr( $th_class ),
                    esc_html( $label )
                );
            }
        }

        // Actions column
        if ( $this->config['row_actions'] ) {
            $output .= '<th scope="col" class="qaf-col-actions">' . esc_html__( 'Actions', 'quill-accessible-forms' ) . '</th>';
        }

        $output .= '</tr></thead>';

        return $output;
    }

    /**
     * Render table body
     *
     * @param array $columns Column configuration.
     * @return string HTML output.
     */
    private function render_table_body( $columns ) {
        $output = '<tbody>';

        foreach ( $this->entries as $entry ) {
            $output .= $this->render_table_row( $entry, $columns );
        }

        $output .= '</tbody>';

        return $output;
    }

    /**
     * Render single table row
     *
     * @param QAF_Entry $entry   Entry object.
     * @param array     $columns Column configuration.
     * @return string HTML output.
     */
    private function render_table_row( $entry, $columns ) {
        $row_class = 'qaf-entry-row';
        if ( $this->config['highlight_unread'] && ! $entry->is_read ) {
            $row_class .= ' qaf-unread';
        }
        if ( $entry->is_starred ) {
            $row_class .= ' qaf-starred';
        }

        $output = sprintf(
            '<tr class="%s" data-entry-id="%d">',
            esc_attr( $row_class ),
            (int) $entry->id
        );

        // Checkbox
        if ( $this->config['bulk_actions'] ) {
            $output .= sprintf(
                '<td class="qaf-col-checkbox">
                    <input type="checkbox" name="qaf_entries[]" value="%d" id="qaf-entry-%d" aria-label="%s">
                    <label for="qaf-entry-%d" class="screen-reader-text">%s</label>
                </td>',
                (int) $entry->id,
                (int) $entry->id,
                esc_attr( sprintf( __( 'Select entry %d', 'quill-accessible-forms' ), $entry->id ) ),
                (int) $entry->id,
                esc_html( sprintf( __( 'Select entry %d', 'quill-accessible-forms' ), $entry->id ) )
            );
        }

        // Data columns
        foreach ( $columns as $id => $column ) {
            $value = $this->get_column_value( $entry, $id );
            $output .= sprintf(
                '<td class="qaf-col-%s" data-label="%s">%s</td>',
                esc_attr( sanitize_html_class( $id ) ),
                esc_attr( $column['label'] ?? $id ),
                $value
            );
        }

        // Actions
        if ( $this->config['row_actions'] ) {
            $output .= $this->render_row_actions( $entry );
        }

        $output .= '</tr>';

        return $output;
    }

    /**
     * Get column value
     *
     * @param QAF_Entry $entry     Entry object.
     * @param string    $column_id Column ID.
     * @return string Formatted value.
     */
    private function get_column_value( $entry, $column_id ) {
        // Meta fields
        switch ( $column_id ) {
            case 'id':
                return (int) $entry->id;

            case 'created_at':
            case 'date':
                return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) );

            case 'status':
                return sprintf(
                    '<span class="qaf-status qaf-status-%s">%s</span>',
                    esc_attr( $entry->status ),
                    esc_html( ucfirst( $entry->status ) )
                );

            case 'user':
                if ( $entry->user_id ) {
                    $user = get_user_by( 'ID', $entry->user_id );
                    return $user ? esc_html( $user->display_name ) : '';
                }
                return '';

            default:
                return $this->get_display_value( $entry, $column_id );
        }
    }

    /**
     * Render row actions
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_row_actions( $entry ) {
        $output = '<td class="qaf-col-actions">';
        $output .= '<div class="qaf-row-actions">';

        // View
        $output .= sprintf(
            '<button type="button" class="qaf-action-view" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'View entry %d', 'quill-accessible-forms' ), $entry->id ) ),
            esc_html__( 'View', 'quill-accessible-forms' )
        );

        // Edit
        $output .= sprintf(
            '<button type="button" class="qaf-action-edit" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'Edit entry %d', 'quill-accessible-forms' ), $entry->id ) ),
            esc_html__( 'Edit', 'quill-accessible-forms' )
        );

        // Star
        $star_class = $entry->is_starred ? 'dashicons-star-filled' : 'dashicons-star-empty';
        $star_label = $entry->is_starred ? __( 'Unstar', 'quill-accessible-forms' ) : __( 'Star', 'quill-accessible-forms' );
        $output .= sprintf(
            '<button type="button" class="qaf-action-star" data-entry-id="%d" aria-label="%s" aria-pressed="%s">
                <span class="dashicons %s" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( '%s entry %d', 'quill-accessible-forms' ), $star_label, $entry->id ) ),
            $entry->is_starred ? 'true' : 'false',
            esc_attr( $star_class ),
            esc_html( $star_label )
        );

        // Delete
        $output .= sprintf(
            '<button type="button" class="qaf-action-delete" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'Delete entry %d', 'quill-accessible-forms' ), $entry->id ) ),
            esc_html__( 'Delete', 'quill-accessible-forms' )
        );

        $output .= '</div>';
        $output .= '</td>';

        return $output;
    }

    /**
     * Render empty state
     *
     * @return string HTML output.
     */
    private function render_empty_state() {
        return sprintf(
            '<div class="qaf-empty-state" role="status">
                <span class="dashicons dashicons-inbox" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No entries found.', 'quill-accessible-forms' )
        );
    }

    /**
     * Render view header with bulk actions
     *
     * @return string HTML output.
     */
    protected function render_header() {
        $output = '<div class="qaf-view-header">';

        // Bulk actions
        if ( $this->config['bulk_actions'] ) {
            $output .= $this->render_bulk_actions();
        }

        // Search
        if ( $this->config['search'] ) {
            $output .= $this->render_search();
        }

        // Filter dropdowns
        if ( $this->config['filter'] ) {
            $output .= $this->render_filters();
        }

        // Export buttons
        if ( ! empty( $this->config['export'] ) ) {
            $output .= $this->render_export_buttons();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render bulk actions
     *
     * @return string HTML output.
     */
    private function render_bulk_actions() {
        $bulk_id = 'qaf-bulk-action-' . uniqid();
        
        return sprintf(
            '<div class="qaf-bulk-actions">
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="qaf-bulk-select">
                    <option value="">%s</option>
                    <option value="read">%s</option>
                    <option value="unread">%s</option>
                    <option value="star">%s</option>
                    <option value="unstar">%s</option>
                    <option value="trash">%s</option>
                    <option value="delete">%s</option>
                </select>
                <button type="button" class="qaf-bulk-apply button">%s</button>
            </div>',
            esc_attr( $bulk_id ),
            esc_html__( 'Bulk actions', 'quill-accessible-forms' ),
            esc_attr( $bulk_id ),
            esc_html__( 'Bulk Actions', 'quill-accessible-forms' ),
            esc_html__( 'Mark as Read', 'quill-accessible-forms' ),
            esc_html__( 'Mark as Unread', 'quill-accessible-forms' ),
            esc_html__( 'Star', 'quill-accessible-forms' ),
            esc_html__( 'Unstar', 'quill-accessible-forms' ),
            esc_html__( 'Move to Trash', 'quill-accessible-forms' ),
            esc_html__( 'Delete Permanently', 'quill-accessible-forms' ),
            esc_html__( 'Apply', 'quill-accessible-forms' )
        );
    }

    /**
     * Render filters
     *
     * @return string HTML output.
     */
    private function render_filters() {
        $status_id = 'qaf-filter-status-' . uniqid();
        $date_id = 'qaf-filter-date-' . uniqid();
        
        return sprintf(
            '<div class="qaf-filters">
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="qaf-filter-status">
                    <option value="">%s</option>
                    <option value="active">%s</option>
                    <option value="pending">%s</option>
                    <option value="spam">%s</option>
                    <option value="trash">%s</option>
                </select>
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="qaf-filter-date">
                    <option value="">%s</option>
                    <option value="today">%s</option>
                    <option value="week">%s</option>
                    <option value="month">%s</option>
                    <option value="year">%s</option>
                </select>
                <button type="button" class="qaf-filter-apply button">%s</button>
            </div>',
            esc_attr( $status_id ),
            esc_html__( 'Filter by status', 'quill-accessible-forms' ),
            esc_attr( $status_id ),
            esc_html__( 'All Statuses', 'quill-accessible-forms' ),
            esc_html__( 'Active', 'quill-accessible-forms' ),
            esc_html__( 'Pending', 'quill-accessible-forms' ),
            esc_html__( 'Spam', 'quill-accessible-forms' ),
            esc_html__( 'Trash', 'quill-accessible-forms' ),
            esc_attr( $date_id ),
            esc_html__( 'Filter by date', 'quill-accessible-forms' ),
            esc_attr( $date_id ),
            esc_html__( 'All Dates', 'quill-accessible-forms' ),
            esc_html__( 'Today', 'quill-accessible-forms' ),
            esc_html__( 'This Week', 'quill-accessible-forms' ),
            esc_html__( 'This Month', 'quill-accessible-forms' ),
            esc_html__( 'This Year', 'quill-accessible-forms' ),
            esc_html__( 'Filter', 'quill-accessible-forms' )
        );
    }
}
