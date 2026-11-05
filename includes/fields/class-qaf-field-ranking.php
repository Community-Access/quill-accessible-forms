<?php
/**
 * Ranking Field - Accessible drag-drop with keyboard alternatives
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Ranking class
 */
class QAF_Field_Ranking extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'ranking';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'choice';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'choices'       => array(),
            'randomize'     => false,
            'require_all'   => true,
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
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'choices' => array(
                    'type'  => 'choices',
                    'label' => __( 'Items to Rank', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'behavior' => array(
                'randomize' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Randomize Initial Order', 'quill-accessible-forms' ),
                ),
                'require_all' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require All Items Ranked', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML with accessible keyboard controls
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'qaf_field_' . $this->get_id();
        $choices = $this->get_choices();
        $values  = (array) $this->get_value();

        // If we have submitted values, use that order
        if ( ! empty( $values ) ) {
            $ordered_choices = array();
            foreach ( $values as $val ) {
                foreach ( $choices as $choice ) {
                    if ( ( $choice['value'] ?? $choice['label'] ) === $val ) {
                        $ordered_choices[] = $choice;
                        break;
                    }
                }
            }
            // Add any remaining choices not in values
            foreach ( $choices as $choice ) {
                $val = $choice['value'] ?? $choice['label'];
                if ( ! in_array( $val, $values, true ) ) {
                    $ordered_choices[] = $choice;
                }
            }
            $choices = $ordered_choices;
        }

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset/legend for the ranking group
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Legend
        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Instructions for keyboard users
        $html .= '<p id="' . esc_attr( $html_id ) . '-instructions" class="qaf-ranking-instructions">';
        $html .= esc_html__( 'Drag items to reorder, or use the move buttons. Press Enter to activate move mode, then use arrow keys.', 'quill-accessible-forms' );
        $html .= '</p>';

        // Ranking list
        $html .= '<ul class="qaf-ranking-list" role="listbox" aria-describedby="' . esc_attr( $html_id ) . '-instructions" aria-label="' . esc_attr__( 'Rankable items', 'quill-accessible-forms' ) . '">';

        foreach ( $choices as $index => $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'];
            $choice_label = $choice['label'];
            $item_id = $html_id . '-item-' . $index;
            $rank = $index + 1;

            $html .= '<li id="' . esc_attr( $item_id ) . '" ';
            $html .= 'class="qaf-ranking-item" ';
            $html .= 'role="option" ';
            $html .= 'aria-selected="false" ';
            $html .= 'data-value="' . esc_attr( $choice_value ) . '" ';
            $html .= 'draggable="true" ';
            $html .= 'tabindex="' . ( $index === 0 ? '0' : '-1' ) . '">';

            // Rank number
            $html .= '<span class="qaf-ranking-number" aria-hidden="true">' . $rank . '</span>';

            // Drag handle (visual)
            $html .= '<span class="qaf-ranking-handle" aria-hidden="true">';
            $html .= '<span class="qaf-icon-grip"></span>';
            $html .= '</span>';

            // Item label
            $html .= '<span class="qaf-ranking-label">' . esc_html( $choice_label ) . '</span>';

            // Move buttons (keyboard alternative to drag)
            $html .= '<span class="qaf-ranking-buttons">';
            
            $html .= '<button type="button" class="qaf-ranking-move-up" ';
            $html .= 'aria-label="' . esc_attr( sprintf( __( 'Move %s up', 'quill-accessible-forms' ), $choice_label ) ) . '"';
            if ( $index === 0 ) {
                $html .= ' disabled aria-disabled="true"';
            }
            $html .= '>';
            $html .= '<span class="qaf-icon-arrow-up" aria-hidden="true"></span>';
            $html .= '</button>';

            $html .= '<button type="button" class="qaf-ranking-move-down" ';
            $html .= 'aria-label="' . esc_attr( sprintf( __( 'Move %s down', 'quill-accessible-forms' ), $choice_label ) ) . '"';
            if ( $index === count( $choices ) - 1 ) {
                $html .= ' disabled aria-disabled="true"';
            }
            $html .= '>';
            $html .= '<span class="qaf-icon-arrow-down" aria-hidden="true"></span>';
            $html .= '</button>';

            $html .= '</span>';

            // Hidden input for this item's rank
            $html .= '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $choice_value ) . '">';

            $html .= '</li>';
        }

        $html .= '</ul>';

        // Status announcer for screen readers
        $html .= '<div class="qaf-ranking-status" role="status" aria-live="polite" aria-atomic="true"></div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Get choices array
     *
     * @return array Choices.
     */
    protected function get_choices() {
        $choices = $this->config['choices'] ?? array();

        if ( is_string( $choices ) ) {
            $lines = explode( "\n", $choices );
            $choices = array();
            
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( empty( $line ) ) {
                    continue;
                }
                
                if ( strpos( $line, '|' ) !== false ) {
                    list( $value, $label ) = explode( '|', $line, 2 );
                    $choices[] = array(
                        'value' => trim( $value ),
                        'label' => trim( $label ),
                    );
                } else {
                    $choices[] = array(
                        'value' => $line,
                        'label' => $line,
                    );
                }
            }
        }

        if ( ! empty( $this->config['randomize'] ) && empty( $this->get_value() ) ) {
            shuffle( $choices );
        }

        return $choices;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized values.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        return array_map( 'sanitize_text_field', $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $choices = $this->get_choices();
        $values = (array) $value;

        if ( ! empty( $this->config['require_all'] ) ) {
            if ( count( $values ) !== count( $choices ) ) {
                $this->add_error( __( 'Please rank all items.', 'quill-accessible-forms' ) );
                return;
            }
        }

        // Validate all values are valid choices
        $valid_values = array_map( function( $c ) {
            return $c['value'] ?? $c['label'];
        }, $choices );

        foreach ( $values as $val ) {
            if ( ! in_array( $val, $valid_values, true ) ) {
                $this->add_error( __( 'Invalid ranking value.', 'quill-accessible-forms' ) );
                return;
            }
        }

        // Check for duplicates
        if ( count( $values ) !== count( array_unique( $values ) ) ) {
            $this->add_error( __( 'Duplicate rankings are not allowed.', 'quill-accessible-forms' ) );
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty( $value ) {
        return empty( $value ) || ( is_array( $value ) && count( array_filter( $value ) ) === 0 );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) ) {
            return '';
        }

        $choices = $this->get_choices();
        $output = array();

        foreach ( $value as $index => $val ) {
            $label = $val;
            foreach ( $choices as $choice ) {
                if ( ( $choice['value'] ?? $choice['label'] ) === $val ) {
                    $label = $choice['label'];
                    break;
                }
            }
            $output[] = ( $index + 1 ) . '. ' . $label;
        }

        return implode( "\n", $output );
    }
}
