<?php
/**
 * Appointment/Scheduling Field
 *
 * Time slot picker with availability management.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Appointment class
 */
class BAF_Field_Appointment extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'appointment';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'duration'           => 30,              // Minutes per slot
            'buffer_time'        => 0,               // Minutes between slots
            'min_notice'         => 24,              // Hours advance booking required
            'max_advance'        => 30,              // Days in advance booking allowed
            'timezone'           => 'site',          // 'site', 'user', specific timezone
            'availability'       => array(),         // Weekly schedule
            'blocked_dates'      => array(),         // Specific dates to block
            'max_per_slot'       => 1,               // Appointments per slot (for group)
            'show_timezone'      => true,
            'date_format'        => 'F j, Y',
            'time_format'        => 'g:i a',
            'week_starts'        => 0,               // 0 = Sunday, 1 = Monday
            'calendar_months'    => 2,               // Months to show
            'show_remaining'     => false,           // Show remaining spots
            'confirmation_email' => true,
        ) );
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'scheduling' => array(
                'duration' => array(
                    'type'    => 'select',
                    'label'   => __( 'Appointment Duration', 'bits-accessible-forms' ),
                    'options' => array(
                        15  => __( '15 minutes', 'bits-accessible-forms' ),
                        30  => __( '30 minutes', 'bits-accessible-forms' ),
                        45  => __( '45 minutes', 'bits-accessible-forms' ),
                        60  => __( '1 hour', 'bits-accessible-forms' ),
                        90  => __( '1.5 hours', 'bits-accessible-forms' ),
                        120 => __( '2 hours', 'bits-accessible-forms' ),
                    ),
                ),
                'buffer_time' => array(
                    'type'  => 'number',
                    'label' => __( 'Buffer Time (minutes)', 'bits-accessible-forms' ),
                    'desc'  => __( 'Time between appointments', 'bits-accessible-forms' ),
                    'min'   => 0,
                    'max'   => 60,
                ),
                'min_notice' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Notice (hours)', 'bits-accessible-forms' ),
                    'desc'  => __( 'How far in advance booking required', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_advance' => array(
                    'type'  => 'number',
                    'label' => __( 'Max Advance Booking (days)', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 365,
                ),
                'max_per_slot' => array(
                    'type'  => 'number',
                    'label' => __( 'Max Per Slot', 'bits-accessible-forms' ),
                    'desc'  => __( 'For group appointments', 'bits-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'availability' => array(
                'availability' => array(
                    'type'  => 'availability_schedule',
                    'label' => __( 'Weekly Availability', 'bits-accessible-forms' ),
                ),
                'blocked_dates' => array(
                    'type'  => 'date_list',
                    'label' => __( 'Blocked Dates', 'bits-accessible-forms' ),
                    'desc'  => __( 'Dates when no appointments available', 'bits-accessible-forms' ),
                ),
            ),
            'display' => array(
                'timezone' => array(
                    'type'    => 'select',
                    'label'   => __( 'Timezone', 'bits-accessible-forms' ),
                    'options' => $this->get_timezone_options(),
                ),
                'show_timezone' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Timezone', 'bits-accessible-forms' ),
                ),
                'date_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Date Format', 'bits-accessible-forms' ),
                    'options' => array(
                        'F j, Y'  => date_i18n( 'F j, Y' ),
                        'j F Y'   => date_i18n( 'j F Y' ),
                        'Y-m-d'   => date_i18n( 'Y-m-d' ),
                        'm/d/Y'   => date_i18n( 'm/d/Y' ),
                        'd/m/Y'   => date_i18n( 'd/m/Y' ),
                    ),
                ),
                'time_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Time Format', 'bits-accessible-forms' ),
                    'options' => array(
                        'g:i a' => date_i18n( 'g:i a' ),
                        'g:i A' => date_i18n( 'g:i A' ),
                        'H:i'   => date_i18n( 'H:i' ),
                    ),
                ),
                'week_starts' => array(
                    'type'    => 'select',
                    'label'   => __( 'Week Starts On', 'bits-accessible-forms' ),
                    'options' => array(
                        0 => __( 'Sunday', 'bits-accessible-forms' ),
                        1 => __( 'Monday', 'bits-accessible-forms' ),
                    ),
                ),
                'calendar_months' => array(
                    'type'    => 'select',
                    'label'   => __( 'Months to Display', 'bits-accessible-forms' ),
                    'options' => array(
                        1 => __( '1 month', 'bits-accessible-forms' ),
                        2 => __( '2 months', 'bits-accessible-forms' ),
                        3 => __( '3 months', 'bits-accessible-forms' ),
                    ),
                ),
                'show_remaining' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Remaining Spots', 'bits-accessible-forms' ),
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
        $html_id      = $this->get_html_id();
        $name         = 'baf_field_' . $this->get_id();
        $value        = $this->get_value();
        $duration     = (int) ( $this->config['duration'] ?? 30 );
        $show_tz      = $this->config['show_timezone'] ?? true;
        $date_format  = $this->config['date_format'] ?? 'F j, Y';
        $time_format  = $this->config['time_format'] ?? 'g:i a';
        $week_starts  = (int) ( $this->config['week_starts'] ?? 0 );
        $months       = (int) ( $this->config['calendar_months'] ?? 2 );

        $timezone = $this->get_timezone();

        // Parse current value
        $selected_date = '';
        $selected_time = '';
        if ( ! empty( $value ) ) {
            $parts = explode( ' ', $value, 2 );
            $selected_date = $parts[0] ?? '';
            $selected_time = $parts[1] ?? '';
        }

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-appointment-wrapper';

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-duration="' . esc_attr( $duration ) . '"';
        $field .= ' data-week-starts="' . esc_attr( $week_starts ) . '">';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Timezone indicator
        if ( $show_tz ) {
            $field .= '<div class="baf-appointment-timezone">';
            $field .= '<span class="dashicons dashicons-clock" aria-hidden="true"></span>';
            $field .= sprintf(
                /* translators: %s: timezone */
                __( 'Times shown in %s', 'bits-accessible-forms' ),
                esc_html( $timezone )
            );
            $field .= '</div>';
        }

        // Calendar + time slots container
        $field .= '<div class="baf-appointment-picker">';

        // Calendar section
        $field .= '<div class="baf-appointment-calendar" role="application" aria-label="' . esc_attr__( 'Date picker', 'bits-accessible-forms' ) . '">';
        $field .= $this->render_calendar( $html_id, $selected_date, $week_starts, $months );
        $field .= '</div>';

        // Time slots section
        $field .= '<div class="baf-appointment-times" aria-live="polite">';
        $field .= '<div class="baf-times-header">';
        $field .= '<h4 class="baf-times-title">' . esc_html__( 'Available Times', 'bits-accessible-forms' ) . '</h4>';
        $field .= '</div>';
        $field .= '<div class="baf-times-list" id="' . esc_attr( $html_id ) . '-times" role="listbox" aria-label="' . esc_attr__( 'Available appointment times', 'bits-accessible-forms' ) . '">';
        if ( $selected_date ) {
            $field .= $this->render_time_slots( $html_id, $name, $selected_date, $selected_time );
        } else {
            $field .= '<p class="baf-times-empty">' . esc_html__( 'Select a date to see available times.', 'bits-accessible-forms' ) . '</p>';
        }
        $field .= '</div>';
        $field .= '</div>';

        $field .= '</div>';

        // Selected value display
        $field .= '<div class="baf-appointment-selected" id="' . esc_attr( $html_id ) . '-selected">';
        if ( $selected_date && $selected_time ) {
            $datetime = strtotime( $selected_date . ' ' . $selected_time );
            $field .= '<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>';
            $field .= '<strong>' . esc_html__( 'Selected:', 'bits-accessible-forms' ) . '</strong> ';
            $field .= esc_html( date_i18n( $date_format, $datetime ) ) . ' ' . esc_html__( 'at', 'bits-accessible-forms' ) . ' ';
            $field .= esc_html( date_i18n( $time_format, $datetime ) );
        }
        $field .= '</div>';

        // Hidden input for form submission
        $field .= '<input type="hidden" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" ';
        $field .= 'value="' . esc_attr( $value ) . '" class="baf-appointment-value">';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</div>';

        return $field;
    }

    /**
     * Render calendar grid
     *
     * @param string $html_id       Field ID.
     * @param string $selected_date Selected date.
     * @param int    $week_starts   Day week starts on.
     * @param int    $months        Number of months to show.
     * @return string HTML.
     */
    protected function render_calendar( $html_id, $selected_date, $week_starts, $months ) {
        $html = '';
        $current_month = new DateTime();
        
        for ( $m = 0; $m < $months; $m++ ) {
            $month_date = clone $current_month;
            $month_date->modify( "+{$m} months" );
            
            $html .= $this->render_month( $html_id, $month_date, $selected_date, $week_starts, $m === 0 );
        }

        return $html;
    }

    /**
     * Render a single month
     *
     * @param string   $html_id       Field ID.
     * @param DateTime $month_date    Month to render.
     * @param string   $selected_date Selected date.
     * @param int      $week_starts   Day week starts on.
     * @param bool     $show_nav      Show navigation.
     * @return string HTML.
     */
    protected function render_month( $html_id, $month_date, $selected_date, $week_starts, $show_nav ) {
        $month_start = clone $month_date;
        $month_start->modify( 'first day of this month' );
        
        $month_end = clone $month_date;
        $month_end->modify( 'last day of this month' );

        $today = new DateTime();
        $today->setTime( 0, 0, 0 );

        $min_date = $this->get_min_date();
        $max_date = $this->get_max_date();

        $html = '<div class="baf-calendar-month" data-month="' . esc_attr( $month_date->format( 'Y-m' ) ) . '">';

        // Month header
        $html .= '<div class="baf-calendar-header">';
        if ( $show_nav ) {
            $html .= '<button type="button" class="baf-calendar-nav baf-calendar-prev" aria-label="' . esc_attr__( 'Previous month', 'bits-accessible-forms' ) . '">';
            $html .= '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>';
            $html .= '</button>';
        }
        $html .= '<h4 class="baf-calendar-title">' . esc_html( $month_date->format( 'F Y' ) ) . '</h4>';
        if ( $show_nav ) {
            $html .= '<button type="button" class="baf-calendar-nav baf-calendar-next" aria-label="' . esc_attr__( 'Next month', 'bits-accessible-forms' ) . '">';
            $html .= '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
            $html .= '</button>';
        }
        $html .= '</div>';

        // Day headers
        $days = $this->get_day_headers( $week_starts );
        $html .= '<div class="baf-calendar-days-header">';
        foreach ( $days as $day ) {
            $html .= '<span class="baf-calendar-day-name" aria-hidden="true">' . esc_html( $day ) . '</span>';
        }
        $html .= '</div>';

        // Days grid
        $html .= '<div class="baf-calendar-days" role="grid">';

        // Calculate start offset
        $first_dow = (int) $month_start->format( 'w' );
        $offset = $first_dow - $week_starts;
        if ( $offset < 0 ) {
            $offset += 7;
        }

        // Empty cells before first day
        for ( $i = 0; $i < $offset; $i++ ) {
            $html .= '<span class="baf-calendar-day baf-calendar-empty"></span>';
        }

        // Days of month
        $day = clone $month_start;
        while ( $day <= $month_end ) {
            $date_str = $day->format( 'Y-m-d' );
            $is_today = $day == $today;
            $is_selected = $date_str === $selected_date;
            $is_past = $day < $min_date;
            $is_future = $day > $max_date;
            $is_blocked = $this->is_date_blocked( $day );
            $has_slots = $this->date_has_availability( $day );
            $is_disabled = $is_past || $is_future || $is_blocked || ! $has_slots;

            $day_classes = array( 'baf-calendar-day' );
            if ( $is_today ) {
                $day_classes[] = 'baf-today';
            }
            if ( $is_selected ) {
                $day_classes[] = 'baf-selected';
            }
            if ( $is_disabled ) {
                $day_classes[] = 'baf-disabled';
            }

            if ( $is_disabled ) {
                $html .= '<span class="' . esc_attr( implode( ' ', $day_classes ) ) . '" aria-disabled="true">';
                $html .= esc_html( $day->format( 'j' ) );
                $html .= '</span>';
            } else {
                $html .= '<button type="button" class="' . esc_attr( implode( ' ', $day_classes ) ) . '" ';
                $html .= 'data-date="' . esc_attr( $date_str ) . '" ';
                if ( $is_selected ) {
                    $html .= 'aria-pressed="true" ';
                }
                $html .= 'aria-label="' . esc_attr( date_i18n( 'F j, Y', $day->getTimestamp() ) ) . '">';
                $html .= esc_html( $day->format( 'j' ) );
                $html .= '</button>';
            }

            $day->modify( '+1 day' );
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render time slots for a date
     *
     * @param string $html_id       Field ID.
     * @param string $name          Field name.
     * @param string $date          Date (Y-m-d).
     * @param string $selected_time Selected time.
     * @return string HTML.
     */
    protected function render_time_slots( $html_id, $name, $date, $selected_time ) {
        $slots = $this->get_available_slots( $date );

        if ( empty( $slots ) ) {
            return '<p class="baf-times-empty">' . esc_html__( 'No available times for this date.', 'bits-accessible-forms' ) . '</p>';
        }

        $time_format = $this->config['time_format'] ?? 'g:i a';
        $show_remaining = $this->config['show_remaining'] ?? false;
        $max_per_slot = (int) ( $this->config['max_per_slot'] ?? 1 );

        $html = '';
        foreach ( $slots as $slot ) {
            $time_str = $slot['time'];
            $remaining = $slot['remaining'] ?? $max_per_slot;
            $is_selected = $time_str === $selected_time;
            
            $slot_classes = array( 'baf-time-slot' );
            if ( $is_selected ) {
                $slot_classes[] = 'baf-selected';
            }

            $time_display = date_i18n( $time_format, strtotime( $date . ' ' . $time_str ) );

            $html .= '<button type="button" class="' . esc_attr( implode( ' ', $slot_classes ) ) . '" ';
            $html .= 'role="option" ';
            $html .= 'data-time="' . esc_attr( $time_str ) . '" ';
            if ( $is_selected ) {
                $html .= 'aria-selected="true" ';
            }
            $html .= '>';
            $html .= esc_html( $time_display );
            
            if ( $show_remaining && $max_per_slot > 1 ) {
                $html .= ' <span class="baf-slot-remaining">(' . sprintf(
                    /* translators: %d: remaining spots */
                    _n( '%d spot', '%d spots', $remaining, 'bits-accessible-forms' ),
                    $remaining
                ) . ')</span>';
            }
            
            $html .= '</button>';
        }

        return $html;
    }

    /**
     * Get day header names
     *
     * @param int $week_starts Day week starts on.
     * @return array Day names.
     */
    protected function get_day_headers( $week_starts ) {
        $days = array(
            __( 'Su', 'bits-accessible-forms' ),
            __( 'Mo', 'bits-accessible-forms' ),
            __( 'Tu', 'bits-accessible-forms' ),
            __( 'We', 'bits-accessible-forms' ),
            __( 'Th', 'bits-accessible-forms' ),
            __( 'Fr', 'bits-accessible-forms' ),
            __( 'Sa', 'bits-accessible-forms' ),
        );

        if ( $week_starts === 1 ) {
            $sunday = array_shift( $days );
            $days[] = $sunday;
        }

        return $days;
    }

    /**
     * Get timezone
     *
     * @return string Timezone identifier.
     */
    protected function get_timezone() {
        $tz = $this->config['timezone'] ?? 'site';

        if ( $tz === 'site' ) {
            return wp_timezone_string();
        }

        return $tz;
    }

    /**
     * Get timezone options
     *
     * @return array Timezone options.
     */
    protected function get_timezone_options() {
        $options = array(
            'site' => __( 'Site Timezone', 'bits-accessible-forms' ),
            'user' => __( 'User\'s Timezone', 'bits-accessible-forms' ),
        );

        $timezones = timezone_identifiers_list();
        foreach ( $timezones as $tz ) {
            $options[ $tz ] = $tz;
        }

        return $options;
    }

    /**
     * Get minimum bookable date
     *
     * @return DateTime Minimum date.
     */
    protected function get_min_date() {
        $min_notice = (int) ( $this->config['min_notice'] ?? 24 );
        $min = new DateTime();
        $min->modify( "+{$min_notice} hours" );
        $min->setTime( 0, 0, 0 );
        return $min;
    }

    /**
     * Get maximum bookable date
     *
     * @return DateTime Maximum date.
     */
    protected function get_max_date() {
        $max_advance = (int) ( $this->config['max_advance'] ?? 30 );
        $max = new DateTime();
        $max->modify( "+{$max_advance} days" );
        $max->setTime( 23, 59, 59 );
        return $max;
    }

    /**
     * Check if date is blocked
     *
     * @param DateTime $date Date to check.
     * @return bool True if blocked.
     */
    protected function is_date_blocked( $date ) {
        $blocked = $this->config['blocked_dates'] ?? array();
        $date_str = $date->format( 'Y-m-d' );
        return in_array( $date_str, $blocked, true );
    }

    /**
     * Check if date has any availability
     *
     * @param DateTime $date Date to check.
     * @return bool True if has availability.
     */
    protected function date_has_availability( $date ) {
        $availability = $this->config['availability'] ?? array();
        $dow = (int) $date->format( 'w' ); // 0 = Sunday

        if ( empty( $availability ) ) {
            // Default: weekdays 9-17
            return $dow >= 1 && $dow <= 5;
        }

        return isset( $availability[ $dow ] ) && ! empty( $availability[ $dow ] );
    }

    /**
     * Get available time slots for a date
     *
     * @param string $date Date (Y-m-d).
     * @return array Available slots.
     */
    protected function get_available_slots( $date ) {
        $availability = $this->config['availability'] ?? array();
        $duration = (int) ( $this->config['duration'] ?? 30 );
        $buffer = (int) ( $this->config['buffer_time'] ?? 0 );
        $max_per_slot = (int) ( $this->config['max_per_slot'] ?? 1 );

        $date_obj = new DateTime( $date );
        $dow = (int) $date_obj->format( 'w' );

        // Get schedule for this day
        $schedule = $availability[ $dow ] ?? array(
            array( 'start' => '09:00', 'end' => '17:00' ),
        );

        $slots = array();
        foreach ( $schedule as $period ) {
            $start = strtotime( $date . ' ' . $period['start'] );
            $end = strtotime( $date . ' ' . $period['end'] );

            while ( $start + ( $duration * 60 ) <= $end ) {
                $time_str = date( 'H:i', $start );
                
                // Check against existing bookings (would query database in real implementation)
                $booked = $this->get_bookings_for_slot( $date, $time_str );
                $remaining = $max_per_slot - $booked;

                if ( $remaining > 0 ) {
                    $slots[] = array(
                        'time'      => $time_str,
                        'remaining' => $remaining,
                    );
                }

                $start += ( $duration + $buffer ) * 60;
            }
        }

        return $slots;
    }

    /**
     * Get number of bookings for a slot
     *
     * @param string $date Date.
     * @param string $time Time.
     * @return int Number of bookings.
     */
    protected function get_bookings_for_slot( $date, $time ) {
        // This would query actual booking data in a real implementation
        // For now, return 0 (no bookings)
        return apply_filters( 'baf_appointment_bookings_count', 0, $date, $time, $this->get_id() );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized datetime string.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Validate datetime format (Y-m-d H:i)
        $value = sanitize_text_field( $value );
        
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value ) ) {
            return $value;
        }

        return '';
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( empty( $value ) ) {
            return;
        }

        // Parse datetime
        $datetime = strtotime( $value );
        if ( $datetime === false ) {
            $this->add_error( __( 'Invalid date/time format.', 'bits-accessible-forms' ) );
            return;
        }

        // Check min date
        $min_date = $this->get_min_date();
        if ( $datetime < $min_date->getTimestamp() ) {
            $this->add_error( sprintf(
                /* translators: %d: hours */
                __( 'Appointments must be booked at least %d hours in advance.', 'bits-accessible-forms' ),
                $this->config['min_notice'] ?? 24
            ) );
            return;
        }

        // Check max date
        $max_date = $this->get_max_date();
        if ( $datetime > $max_date->getTimestamp() ) {
            $this->add_error( sprintf(
                /* translators: %d: days */
                __( 'Appointments cannot be booked more than %d days in advance.', 'bits-accessible-forms' ),
                $this->config['max_advance'] ?? 30
            ) );
            return;
        }

        // Check if slot is still available
        $parts = explode( ' ', $value );
        $date = $parts[0];
        $time = $parts[1];
        
        $max_per_slot = (int) ( $this->config['max_per_slot'] ?? 1 );
        $booked = $this->get_bookings_for_slot( $date, $time );
        
        if ( $booked >= $max_per_slot ) {
            $this->add_error( __( 'This time slot is no longer available. Please select another time.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $datetime = strtotime( $value );
        if ( $datetime === false ) {
            return $value;
        }

        $date_format = $this->config['date_format'] ?? 'F j, Y';
        $time_format = $this->config['time_format'] ?? 'g:i a';

        return date_i18n( $date_format, $datetime ) . ' ' . __( 'at', 'bits-accessible-forms' ) . ' ' . date_i18n( $time_format, $datetime );
    }
}
