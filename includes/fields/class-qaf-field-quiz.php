<?php
/**
 * Quiz Field
 *
 * Questions with scoring and grading functionality.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Quiz class
 */
class QAF_Field_Quiz extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'quiz';

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
            'question'           => '',
            'question_type'      => 'multiple_choice',  // 'multiple_choice', 'multiple_answer', 'true_false', 'short_answer'
            'options'            => array(),
            'correct_answers'    => array(),            // Array of correct answer values
            'points'             => 1,
            'partial_credit'     => false,              // For multiple_answer
            'case_sensitive'     => false,              // For short_answer
            'show_feedback'      => true,
            'feedback_correct'   => __( 'Correct!', 'quill-accessible-forms' ),
            'feedback_incorrect' => __( 'Incorrect.', 'quill-accessible-forms' ),
            'feedback_partial'   => __( 'Partially correct.', 'quill-accessible-forms' ),
            'explanation'        => '',                 // Shown after answer
            'hint'               => '',
            'show_hint_button'   => false,
            'shuffle_options'    => false,
            'time_limit'         => 0,                  // 0 = no limit (seconds)
        ) );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'question' => array(
                'question' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Question', 'quill-accessible-forms' ),
                ),
                'question_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Question Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'multiple_choice'  => __( 'Multiple Choice (Single Answer)', 'quill-accessible-forms' ),
                        'multiple_answer'  => __( 'Multiple Answer (Multiple Correct)', 'quill-accessible-forms' ),
                        'true_false'       => __( 'True/False', 'quill-accessible-forms' ),
                        'short_answer'     => __( 'Short Answer', 'quill-accessible-forms' ),
                    ),
                ),
                'options' => array(
                    'type'   => 'repeater_simple',
                    'label'  => __( 'Answer Options', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value'   => __( 'Value', 'quill-accessible-forms' ),
                        'label'   => __( 'Label', 'quill-accessible-forms' ),
                        'correct' => __( 'Correct', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'scoring' => array(
                'points' => array(
                    'type'  => 'number',
                    'label' => __( 'Points', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'partial_credit' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Partial Credit', 'quill-accessible-forms' ),
                    'desc'  => __( 'For multiple answer questions', 'quill-accessible-forms' ),
                ),
                'case_sensitive' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Case Sensitive', 'quill-accessible-forms' ),
                    'desc'  => __( 'For short answer questions', 'quill-accessible-forms' ),
                ),
            ),
            'feedback' => array(
                'show_feedback' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Feedback', 'quill-accessible-forms' ),
                ),
                'feedback_correct' => array(
                    'type'  => 'text',
                    'label' => __( 'Correct Feedback', 'quill-accessible-forms' ),
                ),
                'feedback_incorrect' => array(
                    'type'  => 'text',
                    'label' => __( 'Incorrect Feedback', 'quill-accessible-forms' ),
                ),
                'feedback_partial' => array(
                    'type'  => 'text',
                    'label' => __( 'Partial Credit Feedback', 'quill-accessible-forms' ),
                ),
                'explanation' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Explanation', 'quill-accessible-forms' ),
                    'desc'  => __( 'Shown after answering', 'quill-accessible-forms' ),
                ),
            ),
            'hints' => array(
                'hint' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Hint Text', 'quill-accessible-forms' ),
                ),
                'show_hint_button' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Hint Button', 'quill-accessible-forms' ),
                ),
            ),
            'options_display' => array(
                'shuffle_options' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Shuffle Options', 'quill-accessible-forms' ),
                ),
                'time_limit' => array(
                    'type'  => 'number',
                    'label' => __( 'Time Limit (seconds)', 'quill-accessible-forms' ),
                    'desc'  => __( '0 for no limit', 'quill-accessible-forms' ),
                    'min'   => 0,
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
        $html_id       = $this->get_html_id();
        $name          = 'qaf_field_' . $this->get_id();
        $value         = $this->get_value();
        $question_type = $this->config['question_type'] ?? 'multiple_choice';
        $question      = $this->config['question'] ?? '';
        $options       = $this->config['options'] ?? array();
        $points        = (int) ( $this->config['points'] ?? 1 );
        $time_limit    = (int) ( $this->config['time_limit'] ?? 0 );
        $shuffle       = $this->config['shuffle_options'] ?? false;
        $hint          = $this->config['hint'] ?? '';
        $show_hint_btn = $this->config['show_hint_button'] ?? false;

        // Shuffle options if enabled
        if ( $shuffle && ! empty( $options ) ) {
            $options = $this->shuffle_options( $options );
        }

        $wrapper_classes = array(
            'qaf-quiz-wrapper',
            'qaf-quiz-type-' . $question_type,
        );

        $field = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" ';
        $field .= 'id="' . esc_attr( $html_id ) . '-wrapper" ';
        $field .= 'data-points="' . esc_attr( $points ) . '" ';
        if ( $time_limit > 0 ) {
            $field .= 'data-time-limit="' . esc_attr( $time_limit ) . '" ';
        }
        $field .= '>';

        // Legend with question
        $field .= '<legend class="qaf-quiz-legend">';
        $field .= '<span class="qaf-quiz-question">' . esc_html( $question ) . '</span>';
        $field .= $this->render_required_indicator();
        $field .= '<span class="qaf-quiz-points">(' . sprintf(
            /* translators: %d: number of points */
            _n( '%d point', '%d points', $points, 'quill-accessible-forms' ),
            $points
        ) . ')</span>';
        $field .= '</legend>';

        // Time limit indicator
        if ( $time_limit > 0 ) {
            $field .= '<div class="qaf-quiz-timer" role="timer" aria-live="polite">';
            $field .= '<span class="dashicons dashicons-clock" aria-hidden="true"></span>';
            $field .= '<span class="qaf-timer-display">' . esc_html( $this->format_time( $time_limit ) ) . '</span>';
            $field .= '</div>';
        }

        // Hint button
        if ( $show_hint_btn && $hint ) {
            $field .= '<div class="qaf-quiz-hint-container">';
            $field .= '<button type="button" class="qaf-quiz-hint-btn" aria-expanded="false" aria-controls="' . esc_attr( $html_id ) . '-hint">';
            $field .= '<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>';
            $field .= esc_html__( 'Show Hint', 'quill-accessible-forms' );
            $field .= '</button>';
            $field .= '<div id="' . esc_attr( $html_id ) . '-hint" class="qaf-quiz-hint" hidden>';
            $field .= esc_html( $hint );
            $field .= '</div>';
            $field .= '</div>';
        }

        // Answer input based on type
        switch ( $question_type ) {
            case 'multiple_choice':
                $field .= $this->render_multiple_choice( $html_id, $name, $options, $value );
                break;

            case 'multiple_answer':
                $field .= $this->render_multiple_answer( $html_id, $name, $options, $value );
                break;

            case 'true_false':
                $field .= $this->render_true_false( $html_id, $name, $value );
                break;

            case 'short_answer':
                $field .= $this->render_short_answer( $html_id, $name, $value );
                break;
        }

        // Feedback area (hidden until answered)
        $field .= '<div id="' . esc_attr( $html_id ) . '-feedback" class="qaf-quiz-feedback" aria-live="polite" hidden></div>';

        // Explanation area (hidden until answered)
        if ( ! empty( $this->config['explanation'] ) ) {
            $field .= '<div id="' . esc_attr( $html_id ) . '-explanation" class="qaf-quiz-explanation" hidden>';
            $field .= '<strong>' . esc_html__( 'Explanation:', 'quill-accessible-forms' ) . '</strong> ';
            $field .= esc_html( $this->config['explanation'] );
            $field .= '</div>';
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</fieldset>';

        return $field;
    }

    /**
     * Render multiple choice (single answer)
     *
     * @param string $html_id Field ID.
     * @param string $name    Field name.
     * @param array  $options Answer options.
     * @param mixed  $value   Current value.
     * @return string HTML.
     */
    protected function render_multiple_choice( $html_id, $name, $options, $value ) {
        $html = '<div class="qaf-quiz-options">';

        foreach ( $options as $index => $option ) {
            $opt_value = $option['value'] ?? $option['label'] ?? '';
            $opt_label = $option['label'] ?? $opt_value;
            $opt_id = $html_id . '-option-' . $index;
            $checked = $value === $opt_value ? ' checked' : '';

            $html .= '<div class="qaf-quiz-option">';
            $html .= '<input type="radio" id="' . esc_attr( $opt_id ) . '" name="' . esc_attr( $name ) . '" ';
            $html .= 'value="' . esc_attr( $opt_value ) . '"' . $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $opt_label ) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render multiple answer (multiple correct)
     *
     * @param string $html_id Field ID.
     * @param string $name    Field name.
     * @param array  $options Answer options.
     * @param mixed  $value   Current value.
     * @return string HTML.
     */
    protected function render_multiple_answer( $html_id, $name, $options, $value ) {
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $html = '<div class="qaf-quiz-options qaf-quiz-multiple">';
        $html .= '<p class="qaf-quiz-instruction">' . esc_html__( 'Select all that apply.', 'quill-accessible-forms' ) . '</p>';

        foreach ( $options as $index => $option ) {
            $opt_value = $option['value'] ?? $option['label'] ?? '';
            $opt_label = $option['label'] ?? $opt_value;
            $opt_id = $html_id . '-option-' . $index;
            $checked = in_array( $opt_value, $value, true ) ? ' checked' : '';

            $html .= '<div class="qaf-quiz-option">';
            $html .= '<input type="checkbox" id="' . esc_attr( $opt_id ) . '" name="' . esc_attr( $name ) . '[]" ';
            $html .= 'value="' . esc_attr( $opt_value ) . '"' . $checked . '>';
            $html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $opt_label ) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render true/false
     *
     * @param string $html_id Field ID.
     * @param string $name    Field name.
     * @param mixed  $value   Current value.
     * @return string HTML.
     */
    protected function render_true_false( $html_id, $name, $value ) {
        $html = '<div class="qaf-quiz-options qaf-quiz-true-false">';

        // True option
        $checked_true = $value === 'true' ? ' checked' : '';
        $html .= '<div class="qaf-quiz-option">';
        $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-true" name="' . esc_attr( $name ) . '" ';
        $html .= 'value="true"' . $checked_true . '>';
        $html .= '<label for="' . esc_attr( $html_id ) . '-true">' . esc_html__( 'True', 'quill-accessible-forms' ) . '</label>';
        $html .= '</div>';

        // False option
        $checked_false = $value === 'false' ? ' checked' : '';
        $html .= '<div class="qaf-quiz-option">';
        $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-false" name="' . esc_attr( $name ) . '" ';
        $html .= 'value="false"' . $checked_false . '>';
        $html .= '<label for="' . esc_attr( $html_id ) . '-false">' . esc_html__( 'False', 'quill-accessible-forms' ) . '</label>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Render short answer
     *
     * @param string $html_id Field ID.
     * @param string $name    Field name.
     * @param mixed  $value   Current value.
     * @return string HTML.
     */
    protected function render_short_answer( $html_id, $name, $value ) {
        $html = '<div class="qaf-quiz-short-answer">';
        $html .= '<input type="text" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" ';
        $html .= 'value="' . esc_attr( $value ) . '" class="qaf-quiz-text-input" ';
        $html .= 'placeholder="' . esc_attr__( 'Type your answer...', 'quill-accessible-forms' ) . '">';
        $html .= '</div>';
        return $html;
    }

    /**
     * Shuffle options while preserving keys
     *
     * @param array $options Options to shuffle.
     * @return array Shuffled options.
     */
    protected function shuffle_options( $options ) {
        $shuffled = $options;
        shuffle( $shuffled );
        return $shuffled;
    }

    /**
     * Format time in MM:SS
     *
     * @param int $seconds Total seconds.
     * @return string Formatted time.
     */
    protected function format_time( $seconds ) {
        $minutes = floor( $seconds / 60 );
        $secs = $seconds % 60;
        return sprintf( '%d:%02d', $minutes, $secs );
    }

    /**
     * Grade the answer
     *
     * @param mixed $value Answer value.
     * @return array Grading result with 'correct', 'partial', 'score', 'max_score'.
     */
    public function grade( $value ) {
        $question_type = $this->config['question_type'] ?? 'multiple_choice';
        $correct_answers = $this->get_correct_answers();
        $points = (int) ( $this->config['points'] ?? 1 );
        $partial_credit = $this->config['partial_credit'] ?? false;
        $case_sensitive = $this->config['case_sensitive'] ?? false;

        $result = array(
            'correct'   => false,
            'partial'   => false,
            'score'     => 0,
            'max_score' => $points,
            'feedback'  => '',
        );

        if ( empty( $value ) ) {
            $result['feedback'] = $this->config['feedback_incorrect'] ?? __( 'Incorrect.', 'quill-accessible-forms' );
            return $result;
        }

        switch ( $question_type ) {
            case 'multiple_choice':
            case 'true_false':
                $correct_value = reset( $correct_answers );
                if ( $value === $correct_value ) {
                    $result['correct'] = true;
                    $result['score'] = $points;
                    $result['feedback'] = $this->config['feedback_correct'] ?? __( 'Correct!', 'quill-accessible-forms' );
                } else {
                    $result['feedback'] = $this->config['feedback_incorrect'] ?? __( 'Incorrect.', 'quill-accessible-forms' );
                }
                break;

            case 'multiple_answer':
                if ( ! is_array( $value ) ) {
                    $value = array( $value );
                }
                
                $correct_count = count( array_intersect( $value, $correct_answers ) );
                $wrong_count = count( array_diff( $value, $correct_answers ) );
                $total_correct = count( $correct_answers );

                if ( $correct_count === $total_correct && $wrong_count === 0 ) {
                    $result['correct'] = true;
                    $result['score'] = $points;
                    $result['feedback'] = $this->config['feedback_correct'] ?? __( 'Correct!', 'quill-accessible-forms' );
                } elseif ( $partial_credit && $correct_count > 0 && $wrong_count === 0 ) {
                    $result['partial'] = true;
                    $result['score'] = round( ( $correct_count / $total_correct ) * $points, 2 );
                    $result['feedback'] = $this->config['feedback_partial'] ?? __( 'Partially correct.', 'quill-accessible-forms' );
                } else {
                    $result['feedback'] = $this->config['feedback_incorrect'] ?? __( 'Incorrect.', 'quill-accessible-forms' );
                }
                break;

            case 'short_answer':
                $value_compare = $case_sensitive ? trim( $value ) : strtolower( trim( $value ) );
                $matched = false;
                
                foreach ( $correct_answers as $correct ) {
                    $correct_compare = $case_sensitive ? trim( $correct ) : strtolower( trim( $correct ) );
                    if ( $value_compare === $correct_compare ) {
                        $matched = true;
                        break;
                    }
                }

                if ( $matched ) {
                    $result['correct'] = true;
                    $result['score'] = $points;
                    $result['feedback'] = $this->config['feedback_correct'] ?? __( 'Correct!', 'quill-accessible-forms' );
                } else {
                    $result['feedback'] = $this->config['feedback_incorrect'] ?? __( 'Incorrect.', 'quill-accessible-forms' );
                }
                break;
        }

        return $result;
    }

    /**
     * Get correct answers from options
     *
     * @return array Correct answer values.
     */
    protected function get_correct_answers() {
        // Check for explicit correct_answers config
        if ( ! empty( $this->config['correct_answers'] ) ) {
            return (array) $this->config['correct_answers'];
        }

        // Extract from options
        $correct = array();
        $options = $this->config['options'] ?? array();
        
        foreach ( $options as $option ) {
            if ( ! empty( $option['correct'] ) ) {
                $correct[] = $option['value'] ?? $option['label'] ?? '';
            }
        }

        return $correct;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        $question_type = $this->config['question_type'] ?? 'multiple_choice';

        if ( $question_type === 'multiple_answer' && is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Standard required validation is handled by parent
        // Quiz-specific validation could go here
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( is_array( $value ) ) {
            return implode( ', ', $value );
        }
        
        if ( $value === 'true' ) {
            return __( 'True', 'quill-accessible-forms' );
        }
        if ( $value === 'false' ) {
            return __( 'False', 'quill-accessible-forms' );
        }

        return (string) $value;
    }
}
