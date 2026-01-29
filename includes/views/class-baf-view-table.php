<?php
/**
 * Table View
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_View_Table class
 * 
 * Renders entries in an accessible table format.
 */
class BAF_View_Table extends BAF_View_Renderer {

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

        $table_class = 'baf-entries-table';
        if ( $this->config['sticky_header'] ) {
            $table_class .= ' baf-sticky-header';
        }
        if ( $this->config['zebra_striping'] ) {
            $table_class .= ' baf-zebra';
        }

        $output = '<div class="baf-table-wrapper" role="region" aria-label="' . esc_attr__( 'Entries table', 'bits-accessible-forms' ) . '" tabindex="0">';
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
                '<th scope="col" class="baf-col-checkbox">
                    <input type="checkbox" id="baf-select-all" aria-label="%s">
                    <label for="baf-select-all" class="screen-reader-text">%s</label>
                </th>',
                esc_attr__( 'Select all entries', 'bits-accessible-forms' ),
                esc_html__( 'Select all entries', 'bits-accessible-forms' )
            );
        }

        // Data columns
        foreach ( $columns as $id => $column ) {
            $sortable = isset( $column['sortable'] ) && $column['sortable'];
            $label = $column['label'] ?? $id;
            $th_class = 'baf-col-' . sanitize_html_class( $id );

            if ( $sortable ) {
                $output .= sprintf(
                    '<th scope="col" class="%s baf-sortable" aria-sort="none">
                        <button type="button" class="baf-sort-button" data-column="%s">
                            %s
                            <span class="baf-sort-indicator" aria-hidden="true"></span>
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
            $output .= '<th scope="col" class="baf-col-actions">' . esc_html__( 'Actions', 'bits-accessible-forms' ) . '</th>';
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
     * @param BAF_Entry $entry   Entry object.
     * @param array     $columns Column configuration.
     * @return string HTML output.
     */
    private function render_table_row( $entry, $columns ) {
        $row_class = 'baf-entry-row';
        if ( $this->config['highlight_unread'] && ! $entry->is_read ) {
            $row_class .= ' baf-unread';
        }
        if ( $entry->is_starred ) {
            $row_class .= ' baf-starred';
        }

        $output = sprintf(
            '<tr class="%s" data-entry-id="%d">',
            esc_attr( $row_class ),
            (int) $entry->id
        );

        // Checkbox
        if ( $this->config['bulk_actions'] ) {
            $output .= sprintf(
                '<td class="baf-col-checkbox">
                    <input type="checkbox" name="baf_entries[]" value="%d" id="baf-entry-%d" aria-label="%s">
                    <label for="baf-entry-%d" class="screen-reader-text">%s</label>
                </td>',
                (int) $entry->id,
                (int) $entry->id,
                esc_attr( sprintf( __( 'Select entry %d', 'bits-accessible-forms' ), $entry->id ) ),
                (int) $entry->id,
                esc_html( sprintf( __( 'Select entry %d', 'bits-accessible-forms' ), $entry->id ) )
            );
        }

        // Data columns
        foreach ( $columns as $id => $column ) {
            $value = $this->get_column_value( $entry, $id );
            $output .= sprintf(
                '<td class="baf-col-%s" data-label="%s">%s</td>',
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
     * @param BAF_Entry $entry     Entry object.
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
                    '<span class="baf-status baf-status-%s">%s</span>',
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
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_row_actions( $entry ) {
        $output = '<td class="baf-col-actions">';
        $output .= '<div class="baf-row-actions">';

        // View
        $output .= sprintf(
            '<button type="button" class="baf-action-view" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'View entry %d', 'bits-accessible-forms' ), $entry->id ) ),
            esc_html__( 'View', 'bits-accessible-forms' )
        );

        // Edit
        $output .= sprintf(
            '<button type="button" class="baf-action-edit" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'Edit entry %d', 'bits-accessible-forms' ), $entry->id ) ),
            esc_html__( 'Edit', 'bits-accessible-forms' )
        );

        // Star
        $star_class = $entry->is_starred ? 'dashicons-star-filled' : 'dashicons-star-empty';
        $star_label = $entry->is_starred ? __( 'Unstar', 'bits-accessible-forms' ) : __( 'Star', 'bits-accessible-forms' );
        $output .= sprintf(
            '<button type="button" class="baf-action-star" data-entry-id="%d" aria-label="%s" aria-pressed="%s">
                <span class="dashicons %s" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( '%s entry %d', 'bits-accessible-forms' ), $star_label, $entry->id ) ),
            $entry->is_starred ? 'true' : 'false',
            esc_attr( $star_class ),
            esc_html( $star_label )
        );

        // Delete
        $output .= sprintf(
            '<button type="button" class="baf-action-delete" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                <span class="screen-reader-text">%s</span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'Delete entry %d', 'bits-accessible-forms' ), $entry->id ) ),
            esc_html__( 'Delete', 'bits-accessible-forms' )
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
            '<div class="baf-empty-state" role="status">
                <span class="dashicons dashicons-inbox" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No entries found.', 'bits-accessible-forms' )
        );
    }

    /**
     * Render view header with bulk actions
     *
     * @return string HTML output.
     */
    protected function render_header() {
        $output = '<div class="baf-view-header">';

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
        $bulk_id = 'baf-bulk-action-' . uniqid();
        
        return sprintf(
            '<div class="baf-bulk-actions">
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="baf-bulk-select">
                    <option value="">%s</option>
                    <option value="read">%s</option>
                    <option value="unread">%s</option>
                    <option value="star">%s</option>
                    <option value="unstar">%s</option>
                    <option value="trash">%s</option>
                    <option value="delete">%s</option>
                </select>
                <button type="button" class="baf-bulk-apply button">%s</button>
            </div>',
            esc_attr( $bulk_id ),
            esc_html__( 'Bulk actions', 'bits-accessible-forms' ),
            esc_attr( $bulk_id ),
            esc_html__( 'Bulk Actions', 'bits-accessible-forms' ),
            esc_html__( 'Mark as Read', 'bits-accessible-forms' ),
            esc_html__( 'Mark as Unread', 'bits-accessible-forms' ),
            esc_html__( 'Star', 'bits-accessible-forms' ),
            esc_html__( 'Unstar', 'bits-accessible-forms' ),
            esc_html__( 'Move to Trash', 'bits-accessible-forms' ),
            esc_html__( 'Delete Permanently', 'bits-accessible-forms' ),
            esc_html__( 'Apply', 'bits-accessible-forms' )
        );
    }

    /**
     * Render filters
     *
     * @return string HTML output.
     */
    private function render_filters() {
        $status_id = 'baf-filter-status-' . uniqid();
        $date_id = 'baf-filter-date-' . uniqid();
        
        return sprintf(
            '<div class="baf-filters">
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="baf-filter-status">
                    <option value="">%s</option>
                    <option value="active">%s</option>
                    <option value="pending">%s</option>
                    <option value="spam">%s</option>
                    <option value="trash">%s</option>
                </select>
                <label for="%s" class="screen-reader-text">%s</label>
                <select id="%s" class="baf-filter-date">
                    <option value="">%s</option>
                    <option value="today">%s</option>
                    <option value="week">%s</option>
                    <option value="month">%s</option>
                    <option value="year">%s</option>
                </select>
                <button type="button" class="baf-filter-apply button">%s</button>
            </div>',
            esc_attr( $status_id ),
            esc_html__( 'Filter by status', 'bits-accessible-forms' ),
            esc_attr( $status_id ),
            esc_html__( 'All Statuses', 'bits-accessible-forms' ),
            esc_html__( 'Active', 'bits-accessible-forms' ),
            esc_html__( 'Pending', 'bits-accessible-forms' ),
            esc_html__( 'Spam', 'bits-accessible-forms' ),
            esc_html__( 'Trash', 'bits-accessible-forms' ),
            esc_attr( $date_id ),
            esc_html__( 'Filter by date', 'bits-accessible-forms' ),
            esc_attr( $date_id ),
            esc_html__( 'All Dates', 'bits-accessible-forms' ),
            esc_html__( 'Today', 'bits-accessible-forms' ),
            esc_html__( 'This Week', 'bits-accessible-forms' ),
            esc_html__( 'This Month', 'bits-accessible-forms' ),
            esc_html__( 'This Year', 'bits-accessible-forms' ),
            esc_html__( 'Filter', 'bits-accessible-forms' )
        );
    }
}
