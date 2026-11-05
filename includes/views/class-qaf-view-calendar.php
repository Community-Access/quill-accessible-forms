<?php
/**
 * Calendar View
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_View_Calendar class
 * 
 * Renders entries in an accessible calendar format.
 */
class QAF_View_Calendar extends QAF_View_Renderer {

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'calendar';
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'date_field'       => '',
            'end_date_field'   => '',
            'title_field'      => '',
            'default_view'     => 'month',
            'views'            => array( 'month', 'week', 'agenda' ),
            'week_starts_on'   => get_option( 'start_of_week', 0 ),
            'agenda_list'      => true,
        ) );
    }

    /**
     * Render view content
     *
     * @return string HTML output.
     */
    protected function render_content() {
        if ( empty( $this->entries ) ) {
            return $this->render_empty_state();
        }

        $output = '<div class="qaf-calendar-container">';

        // Navigation
        $output .= $this->render_calendar_nav();

        // View switcher
        if ( count( $this->config['views'] ) > 1 ) {
            $output .= $this->render_view_switcher();
        }

        // Calendar grid
        $output .= $this->render_calendar_grid();

        // Agenda list (accessibility fallback)
        if ( $this->config['agenda_list'] ) {
            $output .= $this->render_agenda_list();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render calendar navigation
     *
     * @return string HTML output.
     */
    private function render_calendar_nav() {
        $current_month = date_i18n( 'F Y' );

        return sprintf(
            '<div class="qaf-calendar-nav">
                <button type="button" class="qaf-nav-prev" aria-label="%s">
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </button>
                <h3 class="qaf-calendar-title" aria-live="polite">%s</h3>
                <button type="button" class="qaf-nav-next" aria-label="%s">
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </button>
                <button type="button" class="qaf-nav-today">%s</button>
            </div>',
            esc_attr__( 'Previous month', 'quill-accessible-forms' ),
            esc_html( $current_month ),
            esc_attr__( 'Next month', 'quill-accessible-forms' ),
            esc_html__( 'Today', 'quill-accessible-forms' )
        );
    }

    /**
     * Render view switcher
     *
     * @return string HTML output.
     */
    private function render_view_switcher() {
        $output = '<div class="qaf-calendar-views" role="tablist" aria-label="' . esc_attr__( 'Calendar views', 'quill-accessible-forms' ) . '">';

        $view_labels = array(
            'month'  => __( 'Month', 'quill-accessible-forms' ),
            'week'   => __( 'Week', 'quill-accessible-forms' ),
            'day'    => __( 'Day', 'quill-accessible-forms' ),
            'agenda' => __( 'Agenda', 'quill-accessible-forms' ),
        );

        foreach ( $this->config['views'] as $view ) {
            $is_selected = $view === $this->config['default_view'];
            $output .= sprintf(
                '<button type="button" 
                         role="tab" 
                         class="qaf-view-tab%s" 
                         data-view="%s"
                         aria-selected="%s"
                         tabindex="%s">%s</button>',
                $is_selected ? ' qaf-active' : '',
                esc_attr( $view ),
                $is_selected ? 'true' : 'false',
                $is_selected ? '0' : '-1',
                esc_html( $view_labels[ $view ] ?? ucfirst( $view ) )
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render calendar grid
     *
     * @return string HTML output.
     */
    private function render_calendar_grid() {
        $events = $this->get_events();
        $events_json = wp_json_encode( $events );

        // Day names
        $day_names = array(
            __( 'Sunday', 'quill-accessible-forms' ),
            __( 'Monday', 'quill-accessible-forms' ),
            __( 'Tuesday', 'quill-accessible-forms' ),
            __( 'Wednesday', 'quill-accessible-forms' ),
            __( 'Thursday', 'quill-accessible-forms' ),
            __( 'Friday', 'quill-accessible-forms' ),
            __( 'Saturday', 'quill-accessible-forms' ),
        );

        // Reorder based on week start
        $start = (int) $this->config['week_starts_on'];
        $ordered_days = array_merge( array_slice( $day_names, $start ), array_slice( $day_names, 0, $start ) );

        $output = '<div class="qaf-calendar-grid" data-events="' . esc_attr( $events_json ) . '" role="grid" aria-label="' . esc_attr__( 'Calendar', 'quill-accessible-forms' ) . '">';

        // Header row
        $output .= '<div class="qaf-calendar-header" role="row">';
        foreach ( $ordered_days as $day ) {
            $abbrev = mb_substr( $day, 0, 3 );
            $output .= sprintf(
                '<div class="qaf-calendar-day-header" role="columnheader" aria-label="%s">
                    <abbr title="%s">%s</abbr>
                </div>',
                esc_attr( $day ),
                esc_attr( $day ),
                esc_html( $abbrev )
            );
        }
        $output .= '</div>';

        // Calendar body (populated by JS)
        $output .= '<div class="qaf-calendar-body" role="rowgroup"></div>';

        $output .= '</div>';

        // Screen reader description
        $output .= sprintf(
            '<div class="screen-reader-text" aria-live="polite" id="qaf-calendar-description">%s</div>',
            esc_html__( 'Use arrow keys to navigate between dates. Press Enter to see events for the selected date.', 'quill-accessible-forms' )
        );

        return $output;
    }

    /**
     * Get events from entries
     *
     * @return array Events data.
     */
    private function get_events() {
        $events = array();

        foreach ( $this->entries as $entry ) {
            $date = null;

            // Get date field value
            if ( $this->config['date_field'] ) {
                $date_value = $entry->get_field_value( $this->config['date_field'] );
                if ( $date_value ) {
                    $date = strtotime( $date_value );
                }
            }

            // Fallback to created_at
            if ( ! $date ) {
                $date = strtotime( $entry->created_at );
            }

            if ( ! $date ) {
                continue;
            }

            // Get title
            $title = '';
            if ( $this->config['title_field'] ) {
                $title = $this->get_display_value( $entry, $this->config['title_field'] );
            }
            if ( empty( $title ) ) {
                $title = sprintf( __( 'Entry #%d', 'quill-accessible-forms' ), $entry->id );
            }

            // Get end date (for multi-day events)
            $end_date = null;
            if ( $this->config['end_date_field'] ) {
                $end_value = $entry->get_field_value( $this->config['end_date_field'] );
                if ( $end_value ) {
                    $end_date = date( 'Y-m-d', strtotime( $end_value ) );
                }
            }

            $events[] = array(
                'id'       => $entry->id,
                'title'    => $title,
                'date'     => date( 'Y-m-d', $date ),
                'end_date' => $end_date,
                'time'     => date( 'H:i', $date ),
            );
        }

        return $events;
    }

    /**
     * Render agenda list
     *
     * @return string HTML output.
     */
    private function render_agenda_list() {
        $events = $this->get_events();

        // Sort by date
        usort( $events, function( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        // Group by date
        $grouped = array();
        foreach ( $events as $event ) {
            $grouped[ $event['date'] ][] = $event;
        }

        $output = '<div id="qaf-calendar-agenda" class="qaf-calendar-agenda">';
        $output .= sprintf(
            '<h3 class="qaf-agenda-heading">%s</h3>',
            esc_html__( 'Upcoming Events', 'quill-accessible-forms' )
        );

        $output .= '<div class="qaf-agenda-list" role="list">';

        $count = 0;
        foreach ( $grouped as $date => $day_events ) {
            if ( $count >= 20 ) break; // Limit agenda items

            $output .= sprintf(
                '<div class="qaf-agenda-date" role="listitem">
                    <time datetime="%s" class="qaf-agenda-date-header">%s</time>
                    <ul class="qaf-agenda-events">',
                esc_attr( $date ),
                esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) )
            );

            foreach ( $day_events as $event ) {
                $output .= sprintf(
                    '<li class="qaf-agenda-event">
                        <a href="#" class="qaf-entry-link" data-entry-id="%d">
                            <span class="qaf-event-time">%s</span>
                            <span class="qaf-event-title">%s</span>
                        </a>
                    </li>',
                    (int) $event['id'],
                    esc_html( $event['time'] !== '00:00' ? date_i18n( get_option( 'time_format' ), strtotime( $event['date'] . ' ' . $event['time'] ) ) : __( 'All day', 'quill-accessible-forms' ) ),
                    esc_html( $event['title'] )
                );
                $count++;
            }

            $output .= '</ul></div>';
        }

        $output .= '</div>';
        $output .= '</div>';

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
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No events found.', 'quill-accessible-forms' )
        );
    }
}
