<?php
/**
 * Progress Indicator Field
 *
 * Visual progress indicator for multi-page forms.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Progress class
 */
class QAF_Field_Progress extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'progress';

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
            'id'                  => '',
            'style'               => 'steps',         // 'steps', 'bar', 'percentage', 'dots'
            'show_labels'         => true,
            'show_numbers'        => true,
            'show_percentage'     => true,
            'clickable'           => false,           // Allow clicking to navigate
            'connector_style'     => 'solid',         // 'solid', 'dashed', 'none'
            'color_completed'     => '#2271b1',
            'color_current'       => '#135e96',
            'color_pending'       => '#dcdcde',
            'animation'           => true,
            'position'            => 'top',           // 'top', 'bottom', 'fixed_top'
            'compact_mobile'      => true,
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'display' => array(
                'style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Progress Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'steps'      => __( 'Step Indicators', 'quill-accessible-forms' ),
                        'bar'        => __( 'Progress Bar', 'quill-accessible-forms' ),
                        'percentage' => __( 'Percentage Circle', 'quill-accessible-forms' ),
                        'dots'       => __( 'Dot Navigation', 'quill-accessible-forms' ),
                    ),
                ),
                'position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Position', 'quill-accessible-forms' ),
                    'options' => array(
                        'top'       => __( 'Top of Form', 'quill-accessible-forms' ),
                        'bottom'    => __( 'Bottom of Form', 'quill-accessible-forms' ),
                        'fixed_top' => __( 'Fixed at Top', 'quill-accessible-forms' ),
                    ),
                ),
                'show_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Step Labels', 'quill-accessible-forms' ),
                ),
                'show_numbers' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Step Numbers', 'quill-accessible-forms' ),
                ),
                'show_percentage' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Percentage', 'quill-accessible-forms' ),
                ),
            ),
            'interaction' => array(
                'clickable' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Click Navigation', 'quill-accessible-forms' ),
                    'desc'  => __( 'Let users click completed steps to navigate back', 'quill-accessible-forms' ),
                ),
                'animation' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Animate Progress', 'quill-accessible-forms' ),
                ),
                'compact_mobile' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Compact on Mobile', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'connector_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Connector Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'solid'  => __( 'Solid Line', 'quill-accessible-forms' ),
                        'dashed' => __( 'Dashed Line', 'quill-accessible-forms' ),
                        'none'   => __( 'No Connector', 'quill-accessible-forms' ),
                    ),
                ),
                'color_completed' => array(
                    'type'  => 'color',
                    'label' => __( 'Completed Color', 'quill-accessible-forms' ),
                ),
                'color_current' => array(
                    'type'  => 'color',
                    'label' => __( 'Current Step Color', 'quill-accessible-forms' ),
                ),
                'color_pending' => array(
                    'type'  => 'color',
                    'label' => __( 'Pending Color', 'quill-accessible-forms' ),
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
        $html_id         = $this->get_html_id();
        $style           = $this->config['style'] ?? 'steps';
        $position        = $this->config['position'] ?? 'top';
        $show_labels     = $this->config['show_labels'] ?? true;
        $show_numbers    = $this->config['show_numbers'] ?? true;
        $show_percentage = $this->config['show_percentage'] ?? true;
        $clickable       = $this->config['clickable'] ?? false;
        $connector       = $this->config['connector_style'] ?? 'solid';
        $animate         = $this->config['animation'] ?? true;
        $compact         = $this->config['compact_mobile'] ?? true;

        $color_completed = $this->config['color_completed'] ?? '#2271b1';
        $color_current   = $this->config['color_current'] ?? '#135e96';
        $color_pending   = $this->config['color_pending'] ?? '#dcdcde';

        // Get page info from form context
        $pages = $this->get_form_pages();
        $current_page = $this->get_current_page();
        $total_pages = count( $pages );
        $percentage = $total_pages > 0 ? round( ( $current_page / $total_pages ) * 100 ) : 0;

        $wrapper_classes = array(
            'qaf-progress-wrapper',
            'qaf-progress-style-' . $style,
            'qaf-progress-position-' . $position,
            'qaf-connector-' . $connector,
        );
        if ( $animate ) {
            $wrapper_classes[] = 'qaf-progress-animated';
        }
        if ( $compact ) {
            $wrapper_classes[] = 'qaf-progress-compact-mobile';
        }

        $css_vars = sprintf(
            '--qaf-progress-completed: %s; --qaf-progress-current: %s; --qaf-progress-pending: %s;',
            esc_attr( $color_completed ),
            esc_attr( $color_current ),
            esc_attr( $color_pending )
        );

        $field = '<nav class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" ';
        $field .= 'id="' . esc_attr( $html_id ) . '" ';
        $field .= 'aria-label="' . esc_attr__( 'Form progress', 'quill-accessible-forms' ) . '" ';
        $field .= 'style="' . esc_attr( $css_vars ) . '" ';
        $field .= 'data-current="' . esc_attr( $current_page ) . '" ';
        $field .= 'data-total="' . esc_attr( $total_pages ) . '">';

        switch ( $style ) {
            case 'steps':
                $field .= $this->render_steps( $pages, $current_page, $show_labels, $show_numbers, $clickable );
                break;

            case 'bar':
                $field .= $this->render_bar( $percentage, $show_percentage, $current_page, $total_pages );
                break;

            case 'percentage':
                $field .= $this->render_percentage_circle( $percentage, $current_page, $total_pages );
                break;

            case 'dots':
                $field .= $this->render_dots( $pages, $current_page, $clickable );
                break;
        }

        // Screen reader live region for progress updates
        $field .= '<div class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true">';
        $field .= sprintf(
            /* translators: 1: current step, 2: total steps, 3: percentage */
            __( 'Step %1$d of %2$d (%3$d%% complete)', 'quill-accessible-forms' ),
            $current_page,
            $total_pages,
            $percentage
        );
        $field .= '</div>';

        $field .= '</nav>';

        return $field;
    }

    /**
     * Render step indicators
     *
     * @param array $pages        Page definitions.
     * @param int   $current_page Current page number.
     * @param bool  $show_labels  Whether to show labels.
     * @param bool  $show_numbers Whether to show numbers.
     * @param bool  $clickable    Whether steps are clickable.
     * @return string HTML.
     */
    protected function render_steps( $pages, $current_page, $show_labels, $show_numbers, $clickable ) {
        $html = '<ol class="qaf-progress-steps">';

        foreach ( $pages as $index => $page ) {
            $page_num = $index + 1;
            $is_completed = $page_num < $current_page;
            $is_current = $page_num === $current_page;
            $is_accessible = $is_completed && $clickable;

            $step_classes = array( 'qaf-step' );
            if ( $is_completed ) {
                $step_classes[] = 'qaf-step-completed';
            }
            if ( $is_current ) {
                $step_classes[] = 'qaf-step-current';
            }

            $html .= '<li class="' . esc_attr( implode( ' ', $step_classes ) ) . '">';

            // Step indicator
            if ( $is_accessible ) {
                $html .= '<button type="button" class="qaf-step-indicator qaf-step-clickable" ';
                $html .= 'data-page="' . esc_attr( $page_num ) . '" ';
                $html .= 'aria-label="' . esc_attr( sprintf(
                    /* translators: 1: page title, 2: step number */
                    __( 'Go to %1$s (step %2$d)', 'quill-accessible-forms' ),
                    $page['title'] ?? __( 'Step', 'quill-accessible-forms' ),
                    $page_num
                ) ) . '">';
            } else {
                $html .= '<span class="qaf-step-indicator">';
            }

            // Step number or checkmark
            if ( $is_completed ) {
                $html .= '<span class="qaf-step-check" aria-hidden="true">✓</span>';
            } elseif ( $show_numbers ) {
                $html .= '<span class="qaf-step-number">' . esc_html( $page_num ) . '</span>';
            }

            if ( $is_accessible ) {
                $html .= '</button>';
            } else {
                $html .= '</span>';
            }

            // Step label
            if ( $show_labels && ! empty( $page['title'] ) ) {
                $html .= '<span class="qaf-step-label"';
                if ( $is_current ) {
                    $html .= ' aria-current="step"';
                }
                $html .= '>' . esc_html( $page['title'] ) . '</span>';
            }

            $html .= '</li>';
        }

        $html .= '</ol>';
        return $html;
    }

    /**
     * Render progress bar
     *
     * @param int  $percentage      Progress percentage.
     * @param bool $show_percentage Whether to show percentage text.
     * @param int  $current_page    Current page number.
     * @param int  $total_pages     Total pages.
     * @return string HTML.
     */
    protected function render_bar( $percentage, $show_percentage, $current_page, $total_pages ) {
        $html = '<div class="qaf-progress-bar-container">';

        if ( $show_percentage ) {
            $html .= '<div class="qaf-progress-bar-header">';
            $html .= '<span class="qaf-progress-bar-steps">';
            $html .= sprintf(
                /* translators: 1: current step, 2: total steps */
                __( 'Step %1$d of %2$d', 'quill-accessible-forms' ),
                $current_page,
                $total_pages
            );
            $html .= '</span>';
            $html .= '<span class="qaf-progress-bar-percent">' . esc_html( $percentage ) . '%</span>';
            $html .= '</div>';
        }

        $html .= '<div class="qaf-progress-bar-track" role="progressbar" ';
        $html .= 'aria-valuenow="' . esc_attr( $percentage ) . '" ';
        $html .= 'aria-valuemin="0" ';
        $html .= 'aria-valuemax="100">';
        $html .= '<div class="qaf-progress-bar-fill" style="width: ' . esc_attr( $percentage ) . '%;"></div>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Render percentage circle
     *
     * @param int $percentage   Progress percentage.
     * @param int $current_page Current page.
     * @param int $total_pages  Total pages.
     * @return string HTML.
     */
    protected function render_percentage_circle( $percentage, $current_page, $total_pages ) {
        // SVG circle parameters
        $radius = 45;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference - ( $percentage / 100 ) * $circumference;

        $html = '<div class="qaf-progress-circle-container">';
        $html .= '<svg class="qaf-progress-circle" viewBox="0 0 100 100" role="progressbar" ';
        $html .= 'aria-valuenow="' . esc_attr( $percentage ) . '" aria-valuemin="0" aria-valuemax="100">';

        // Background circle
        $html .= '<circle class="qaf-progress-circle-bg" cx="50" cy="50" r="' . esc_attr( $radius ) . '" />';

        // Progress circle
        $html .= '<circle class="qaf-progress-circle-fill" cx="50" cy="50" r="' . esc_attr( $radius ) . '" ';
        $html .= 'stroke-dasharray="' . esc_attr( $circumference ) . '" ';
        $html .= 'stroke-dashoffset="' . esc_attr( $offset ) . '" />';

        $html .= '</svg>';

        // Center text
        $html .= '<div class="qaf-progress-circle-text">';
        $html .= '<span class="qaf-progress-circle-percent">' . esc_html( $percentage ) . '%</span>';
        $html .= '<span class="qaf-progress-circle-steps">';
        $html .= sprintf(
            /* translators: 1: current step, 2: total steps */
            __( '%1$d / %2$d', 'quill-accessible-forms' ),
            $current_page,
            $total_pages
        );
        $html .= '</span>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Render dot navigation
     *
     * @param array $pages        Page definitions.
     * @param int   $current_page Current page.
     * @param bool  $clickable    Whether dots are clickable.
     * @return string HTML.
     */
    protected function render_dots( $pages, $current_page, $clickable ) {
        $html = '<div class="qaf-progress-dots">';

        foreach ( $pages as $index => $page ) {
            $page_num = $index + 1;
            $is_completed = $page_num < $current_page;
            $is_current = $page_num === $current_page;
            $is_accessible = $is_completed && $clickable;

            $dot_classes = array( 'qaf-dot' );
            if ( $is_completed ) {
                $dot_classes[] = 'qaf-dot-completed';
            }
            if ( $is_current ) {
                $dot_classes[] = 'qaf-dot-current';
            }

            $label = sprintf(
                /* translators: 1: step number, 2: page title */
                __( 'Step %1$d: %2$s', 'quill-accessible-forms' ),
                $page_num,
                $page['title'] ?? __( 'Page', 'quill-accessible-forms' )
            );

            if ( $is_accessible ) {
                $html .= '<button type="button" class="' . esc_attr( implode( ' ', $dot_classes ) ) . '" ';
                $html .= 'data-page="' . esc_attr( $page_num ) . '" ';
                $html .= 'aria-label="' . esc_attr( __( 'Go to', 'quill-accessible-forms' ) . ' ' . $label ) . '">';
                $html .= '</button>';
            } else {
                $html .= '<span class="' . esc_attr( implode( ' ', $dot_classes ) ) . '" ';
                if ( $is_current ) {
                    $html .= 'aria-current="step" ';
                }
                $html .= 'aria-label="' . esc_attr( $label ) . '">';
                $html .= '</span>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get form pages from context
     *
     * @return array Page definitions.
     */
    protected function get_form_pages() {
        // This would typically be provided by the form context
        // For now return a placeholder that can be populated at render time
        return $this->config['_pages'] ?? array(
            array( 'title' => __( 'Page 1', 'quill-accessible-forms' ) ),
            array( 'title' => __( 'Page 2', 'quill-accessible-forms' ) ),
            array( 'title' => __( 'Page 3', 'quill-accessible-forms' ) ),
        );
    }

    /**
     * Get current page number
     *
     * @return int Current page (1-indexed).
     */
    protected function get_current_page() {
        return $this->config['_current_page'] ?? 1;
    }

    /**
     * Progress field does not collect data
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }

    /**
     * Progress field has no validation
     *
     * @param mixed $value Value.
     */
    protected function validate_value( $value ) {
        // No validation needed for display-only field
    }
}
