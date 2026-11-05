<?php
/**
 * Field base class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field class
 * 
 * Abstract base class for all field types with WCAG 2.2 AA compliance.
 */
abstract class QAF_Field {

    /**
     * Field type identifier
     *
     * @var string
     */
    protected $type = '';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Field configuration
     *
     * @var array
     */
    protected $config = array();

    /**
     * Form ID
     *
     * @var int
     */
    protected $form_id = 0;

    /**
     * Entry value
     *
     * @var mixed
     */
    protected $value = null;

    /**
     * Validation errors
     *
     * @var array
     */
    protected $errors = array();

    /**
     * Constructor
     *
     * @param array $config Field configuration.
     * @param int   $form_id Form ID.
     */
    public function __construct( $config = array(), $form_id = 0 ) {
        $this->config  = wp_parse_args( $config, $this->get_default_config() );
        $this->form_id = $form_id;
    }

    /**
     * Get field type
     *
     * @return string Field type identifier.
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get field category
     *
     * @return string Field category.
     */
    public function get_category() {
        return $this->category;
    }

    /**
     * Get field ID
     *
     * @return string Field ID.
     */
    public function get_id() {
        return isset( $this->config['id'] ) ? $this->config['id'] : '';
    }

    /**
     * Get field HTML ID attribute
     *
     * @return string HTML ID.
     */
    public function get_html_id() {
        return 'qaf-field-' . $this->form_id . '-' . $this->get_id();
    }

    /**
     * Get field label
     *
     * @return string Field label.
     */
    public function get_label() {
        return isset( $this->config['label'] ) ? $this->config['label'] : '';
    }

    /**
     * Get field description
     *
     * @return string Field description.
     */
    public function get_description() {
        return isset( $this->config['description'] ) ? $this->config['description'] : '';
    }

    /**
     * Check if field is required
     *
     * @return bool True if required.
     */
    public function is_required() {
        return ! empty( $this->config['required'] );
    }

    /**
     * Check if field is admin-only
     *
     * @return bool True if admin-only.
     */
    public function is_admin_only() {
        return ! empty( $this->config['admin_only'] );
    }

    /**
     * Check if field is visible
     *
     * @return bool True if visible.
     */
    public function is_visible() {
        // Admin-only fields hidden from frontend
        if ( $this->is_admin_only() && ! is_admin() ) {
            return false;
        }

        // Check conditional logic
        return $this->evaluate_conditional_logic();
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'id'                    => '',
            'label'                 => '',
            'description'           => '',
            'required'              => false,
            'placeholder'           => '',
            'default_value'         => '',
            'css_class'             => '',
            'size'                  => 'medium', // small, medium, large, full
            'label_placement'       => 'inherit', // inherit, above, below, left, hidden
            'description_placement' => 'inherit', // inherit, above, below
            'admin_only'            => false,
            'visibility'            => 'visible', // visible, hidden, admin
            'conditional_logic'     => array(),
            
            // Accessibility
            'aria_label'            => '',
            'aria_describedby'      => '',
            'custom_error_message'  => '',
            
            // Validation
            'validation_rules'      => array(),
            'pattern'               => '', // HTML5 pattern for regex validation
            'unique'                => false,
            'unique_scope'          => 'form', // form|global
            'unique_message'        => '',
            
            // Advanced
            'read_only'             => false,
            'disabled'              => false,
            'allow_prefill'         => true,
            'prefill_parameter'     => '',
            
            // HTML5 attributes
            'autofocus'             => false,
            'spellcheck'            => null, // null = browser default, true/false to force
            'inputmode'             => '', // none, text, decimal, numeric, tel, search, email, url
            'enterkeyhint'          => '', // enter, done, go, next, previous, search, send
        );
    }

    /**
     * Get field-specific settings for the admin UI
     *
     * @return array Settings configuration.
     */
    abstract public function get_field_settings();

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    abstract public function render();

    /**
     * Render field for admin display
     *
     * @return string Rendered HTML for admin.
     */
    public function render_admin() {
        return $this->render();
    }

    /**
     * Render the field wrapper with WCAG compliance
     *
     * @param string $field_input The field input HTML.
     * @return string Complete field HTML.
     */
    protected function render_wrapper( $field_input ) {
        $classes = array(
            'qaf-field',
            'qaf-field-' . $this->type,
            'qaf-field-size-' . $this->config['size'],
        );

        if ( ! empty( $this->config['css_class'] ) ) {
            $classes[] = $this->config['css_class'];
        }

        if ( $this->is_required() ) {
            $classes[] = 'qaf-field-required';
        }

        if ( ! empty( $this->errors ) ) {
            $classes[] = 'qaf-field-error';
        }

        $html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= $this->render_label();

        // Description above field (if configured)
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Field input container
        $html .= '<div class="qaf-field-input">';
        $html .= $field_input;
        $html .= '</div>';

        // Description below field (default)
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container (for AJAX validation)
        $html .= '<div class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render field label
     *
     * @return string Label HTML.
     */
    protected function render_label() {
        $label = $this->get_label();

        if ( empty( $label ) ) {
            return '';
        }

        $placement = $this->get_label_placement();

        if ( $placement === 'hidden' ) {
            // Screen reader only label for accessibility
            return '<label for="' . esc_attr( $this->get_html_id() ) . '" class="qaf-sr-only">' .
                   esc_html( $label ) . $this->render_required_indicator() . '</label>';
        }

        $html = '<label for="' . esc_attr( $this->get_html_id() ) . '" class="qaf-field-label">';
        $html .= esc_html( $label );
        $html .= $this->render_required_indicator();
        $html .= '</label>';

        return $html;
    }

    /**
     * Render required indicator
     *
     * @return string Required indicator HTML.
     */
    protected function render_required_indicator() {
        if ( ! $this->is_required() ) {
            return '';
        }

        // Get settings for required indicator
        $settings = $this->get_required_indicator_settings();

        $style      = $settings['style'] ?? 'asterisk';
        $custom     = $settings['custom_text'] ?? '';
        $icon       = $settings['icon'] ?? 'asterisk';
        $position   = $settings['position'] ?? 'after';
        $visual     = $settings['visual_style'] ?? 'none';
        $color      = $settings['color'] ?? '';
        $sr_text    = $settings['sr_text'] ?? __( 'required', 'quill-accessible-forms' );

        // Build CSS classes
        $classes = array( 'qaf-required-indicator' );
        if ( $visual !== 'none' ) {
            $classes[] = 'qaf-required-' . $visual;
        }

        // Build inline styles
        $inline_style = '';
        if ( ! empty( $color ) ) {
            $inline_style = ' style="color: ' . esc_attr( $color ) . ';"';
        }

        // Get visual indicator
        $indicator = $this->get_indicator_symbol( $style, $custom, $icon );

        // Build HTML
        $html = ' <span class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $inline_style . ' aria-hidden="true">';
        $html .= $indicator;
        $html .= '</span>';
        $html .= '<span class="qaf-sr-only"> ' . esc_html( $sr_text ) . '</span>';

        return $html;
    }

    /**
     * Get required indicator settings
     *
     * @return array Settings for required indicator.
     */
    protected function get_required_indicator_settings() {
        // Check for field-level override
        $field_settings = $this->config['required_indicator'] ?? array();
        
        // Get form-level settings
        $form_settings = apply_filters( 'qaf_required_indicator_settings', array(
            'style'        => 'asterisk',
            'custom_text'  => '',
            'icon'         => 'asterisk',
            'position'     => 'after',
            'visual_style' => 'none',
            'color'        => '',
            'sr_text'      => __( 'required', 'quill-accessible-forms' ),
        ) );

        // Merge: field settings override form settings
        return array_merge( $form_settings, $field_settings );
    }

    /**
     * Get indicator symbol based on style
     *
     * @param string $style  Style type.
     * @param string $custom Custom text.
     * @param string $icon   Icon name.
     * @return string Indicator HTML.
     */
    protected function get_indicator_symbol( $style, $custom, $icon ) {
        switch ( $style ) {
            case 'text':
                return '(' . esc_html__( 'Required', 'quill-accessible-forms' ) . ')';

            case 'both':
                return '* (' . esc_html__( 'Required', 'quill-accessible-forms' ) . ')';

            case 'custom':
                return esc_html( $custom ?: '*' );

            case 'icon':
                return $this->get_icon_html( $icon );

            case 'asterisk':
            default:
                return '*';
        }
    }

    /**
     * Get icon HTML for required indicator
     *
     * @param string $icon Icon name.
     * @return string Icon HTML.
     */
    protected function get_icon_html( $icon ) {
        $icons = array(
            'asterisk'        => '*',
            'asterisk-circle' => '⊛',
            'star'            => '★',
            'dot'             => '●',
            'exclamation'     => '❗',
            'warning'         => '⚠',
        );

        return $icons[ $icon ] ?? $icons['asterisk'];
    }

    /**
     * Render field description
     *
     * @return string Description HTML.
     */
    protected function render_description() {
        $description = $this->get_description();

        if ( empty( $description ) ) {
            return '';
        }

        $desc_id = $this->get_html_id() . '-desc';

        return '<div id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">' .
               wp_kses_post( $description ) . '</div>';
    }

    /**
     * Render validation errors
     *
     * @return string Errors HTML.
     */
    protected function render_errors() {
        if ( empty( $this->errors ) ) {
            return '';
        }

        $error_id = $this->get_html_id() . '-error';

        $html = '<div id="' . esc_attr( $error_id ) . '" class="qaf-field-errors">';

        foreach ( $this->errors as $error ) {
            $html .= '<span class="qaf-error-message">' . esc_html( $error ) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get ARIA attributes for field
     *
     * @return array ARIA attributes.
     */
    protected function get_aria_attributes() {
        $attrs = array();

        // Required
        if ( $this->is_required() ) {
            $attrs['aria-required'] = 'true';
        }

        // Described by (description + error)
        $described_by = array();

        if ( $this->get_description() ) {
            $described_by[] = $this->get_html_id() . '-desc';
        }

        if ( ! empty( $this->errors ) ) {
            $described_by[] = $this->get_html_id() . '-error';
        }

        if ( ! empty( $this->config['aria_describedby'] ) ) {
            $described_by[] = $this->config['aria_describedby'];
        }

        if ( ! empty( $described_by ) ) {
            $attrs['aria-describedby'] = implode( ' ', $described_by );
        }

        // Invalid state
        if ( ! empty( $this->errors ) ) {
            $attrs['aria-invalid'] = 'true';
        }

        // Custom aria-label
        if ( ! empty( $this->config['aria_label'] ) ) {
            $attrs['aria-label'] = $this->config['aria_label'];
        }

        // Read-only state
        if ( ! empty( $this->config['read_only'] ) ) {
            $attrs['aria-readonly'] = 'true';
        }

        // Disabled state
        if ( ! empty( $this->config['disabled'] ) ) {
            $attrs['aria-disabled'] = 'true';
        }

        return $attrs;
    }

    /**
     * Get common HTML5 input attributes
     *
     * Returns attributes like pattern, autofocus, spellcheck, inputmode, enterkeyhint
     * that can be applied to most input fields.
     *
     * @return array HTML5 attributes.
     */
    protected function get_html5_attributes() {
        $attrs = array();

        // Pattern for regex validation
        if ( ! empty( $this->config['pattern'] ) ) {
            $attrs['pattern'] = $this->config['pattern'];
        }

        // Autofocus
        if ( ! empty( $this->config['autofocus'] ) ) {
            $attrs['autofocus'] = true;
        }

        // Spellcheck (only set if explicitly configured)
        if ( $this->config['spellcheck'] !== null ) {
            $attrs['spellcheck'] = $this->config['spellcheck'] ? 'true' : 'false';
        }

        // Input mode for mobile keyboards
        if ( ! empty( $this->config['inputmode'] ) ) {
            $attrs['inputmode'] = $this->config['inputmode'];
        }

        // Enter key hint for mobile keyboards
        if ( ! empty( $this->config['enterkeyhint'] ) ) {
            $attrs['enterkeyhint'] = $this->config['enterkeyhint'];
        }

        return $attrs;
    }

    /**
     * Build HTML attributes string
     *
     * @param array $attrs Attributes array.
     * @return string Attributes string.
     */
    protected function build_attributes( $attrs ) {
        $parts = array();

        foreach ( $attrs as $key => $value ) {
            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $parts[] = esc_attr( $key );
                }
            } elseif ( $value !== '' ) {
                $parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
            }
        }

        return implode( ' ', $parts );
    }

    /**
     * Get label placement
     *
     * @return string Label placement.
     */
    protected function get_label_placement() {
        $placement = $this->config['label_placement'];

        if ( $placement === 'inherit' ) {
            // Get from form settings
            $placement = apply_filters( 'qaf_default_label_placement', 'above' );
        }

        return $placement;
    }

    /**
     * Get description placement
     *
     * @return string Description placement.
     */
    protected function get_description_placement() {
        $placement = $this->config['description_placement'];

        if ( $placement === 'inherit' ) {
            // Get from form settings
            $placement = apply_filters( 'qaf_default_description_placement', 'below' );
        }

        return $placement;
    }

    /**
     * Set field value
     *
     * @param mixed $value Field value.
     */
    public function set_value( $value ) {
        $this->value = $value;
    }

    /**
     * Get field value
     *
     * @return mixed Field value.
     */
    public function get_value() {
        if ( $this->value !== null ) {
            return $this->value;
        }

        // Check for prefill from URL parameter
        if ( ! empty( $this->config['allow_prefill'] ) && ! empty( $this->config['prefill_parameter'] ) ) {
            $param = $this->config['prefill_parameter'];
            if ( isset( $_GET[ $param ] ) ) {
                return sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
            }
        }

        // Smart defaults (progressive enhancement)
        if ( ! empty( $this->config['smart_default'] ) ) {
            $smart = $this->resolve_smart_default( $this->config['smart_default'] );
            if ( $smart !== null && $smart !== '' ) {
                return $smart;
            }
        }

        // Return default value
        return isset( $this->config['default_value'] ) ? $this->config['default_value'] : '';
    }

    /**
     * Resolve smart default token
     *
     * Supported:
     * - geo (via qaf_smart_default_geo filter)
     * - today+N (date relative to today, using WP date format)
     * - user:property (e.g. user:display_name)
     *
     * @param string $token Smart default token.
     * @return mixed|null Resolved value, or null if unsupported/unavailable.
     */
    protected function resolve_smart_default( $token ) {
        $token = trim( (string) $token );

        if ( $token === '' ) {
            return null;
        }

        // user:property
        if ( strpos( $token, 'user:' ) === 0 ) {
            $prop = substr( $token, 5 );
            $user = wp_get_current_user();
            if ( $user && $user->exists() && $prop !== '' && isset( $user->$prop ) ) {
                return $user->$prop;
            }
            return null;
        }

        // today+N
        if ( preg_match( '/^today\+(-?\d+)$/', $token, $m ) ) {
            $days = (int) $m[1];
            $ts   = strtotime( $days . ' days' );
            if ( $ts ) {
                return date_i18n( get_option( 'date_format' ), $ts );
            }
            return null;
        }

        // geo (implementation delegated via filter; no external calls here)
        if ( $token === 'geo' ) {
            return apply_filters( 'qaf_smart_default_geo', null, $this );
        }

        return null;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     * @return bool True if valid, false otherwise.
     */
    public function validate( $value ) {
        $this->errors = array();

        // Skip validation if field not visible due to conditional logic
        if ( ! $this->evaluate_conditional_logic() ) {
            return true;
        }

        // Required validation
        if ( $this->is_required() && $this->is_empty( $value ) ) {
            $this->add_error( $this->get_error_message( 'required' ) );
            return false;
        }

        // Skip other validation if empty and not required
        if ( $this->is_empty( $value ) ) {
            return true;
        }

        // Run field-specific validation
        $this->validate_value( $value );

        // Run custom validation rules
        $this->run_validation_rules( $value );

        return empty( $this->errors );
    }

    /**
     * Field-specific validation
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Override in child classes
    }

    /**
     * Run custom validation rules
     *
     * @param mixed $value Value to validate.
     */
    protected function run_validation_rules( $value ) {
        if ( empty( $this->config['validation_rules'] ) ) {
            return;
        }

        foreach ( $this->config['validation_rules'] as $rule ) {
            $rule_type = $rule['type'] ?? '';
            $rule_value = $rule['value'] ?? '';
            $custom_message = $rule['message'] ?? '';

            switch ( $rule_type ) {
                case 'min_length':
                    if ( strlen( $value ) < (int) $rule_value ) {
                        $this->add_error( $custom_message ?: sprintf(
                            __( 'Please enter at least %d characters.', 'quill-accessible-forms' ),
                            (int) $rule_value
                        ) );
                    }
                    break;

                case 'max_length':
                    if ( strlen( $value ) > (int) $rule_value ) {
                        $this->add_error( $custom_message ?: sprintf(
                            __( 'Please enter no more than %d characters.', 'quill-accessible-forms' ),
                            (int) $rule_value
                        ) );
                    }
                    break;

                case 'pattern':
                    if ( ! preg_match( '/' . $rule_value . '/', $value ) ) {
                        $this->add_error( $custom_message ?: __( 'Please match the requested format.', 'quill-accessible-forms' ) );
                    }
                    break;

                case 'unique':
                    $unique_message = $this->config['unique_message'] ?? '';
                    if ( ! $this->is_unique( $value ) ) {
                        $this->add_error( $custom_message ?: ( $unique_message ?: __( 'This value has already been submitted.', 'quill-accessible-forms' ) ) );
                    }
                    break;
            }

            /**
             * Allow custom validation rules
             *
             * @param QAF_Field $field Field instance.
             * @param mixed $value Field value.
             * @param array $rule Validation rule.
             */
            do_action( 'qaf_validate_rule_' . $rule_type, $this, $value, $rule );
        }
    }

    /**
     * Check if value has been previously submitted
     *
     * @param mixed $value Value to check.
     * @return bool True if unique.
     */
    protected function is_unique( $value ) {
        global $wpdb;

        $meta_table    = $wpdb->prefix . 'qaf_entry_meta';
        $entries_table = $wpdb->prefix . 'qaf_entries';

        $scope = $this->config['unique_scope'] ?? 'form';
        if ( ! in_array( $scope, array( 'form', 'global' ), true ) ) {
            $scope = 'form';
        }

        $where_form = $scope === 'form' ? 'e.form_id = %d AND ' : '';

        $sql = "SELECT COUNT(*) FROM $meta_table m
             JOIN $entries_table e ON m.entry_id = e.id
             WHERE {$where_form}m.field_id = %s AND m.field_value = %s AND e.status != 'trash'";

        $prepare_args = array();
        if ( $scope === 'form' ) {
            $prepare_args[] = $this->form_id;
        }
        $prepare_args[] = $this->get_id();
        $prepare_args[] = $value;

        $count = $wpdb->get_var( $wpdb->prepare( $sql, ...$prepare_args ) );

        return (int) $count === 0;
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty( $value ) {
        if ( is_array( $value ) ) {
            return empty( $value );
        }

        return $value === '' || $value === null;
    }

    /**
     * Add validation error
     *
     * @param string $message Error message.
     */
    protected function add_error( $message ) {
        $this->errors[] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array Validation errors.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get error message for a validation type
     *
     * @param string $type Error type.
     * @return string Error message.
     */
    protected function get_error_message( $type ) {
        // Check for custom error message
        if ( ! empty( $this->config['custom_error_message'] ) ) {
            return $this->config['custom_error_message'];
        }

        // Default messages
        $messages = array(
            'required'      => __( 'This field is required.', 'quill-accessible-forms' ),
            'email'         => __( 'Please enter a valid email address.', 'quill-accessible-forms' ),
            'url'           => __( 'Please enter a valid URL.', 'quill-accessible-forms' ),
            'number'        => __( 'Please enter a valid number.', 'quill-accessible-forms' ),
            'phone'         => __( 'Please enter a valid phone number.', 'quill-accessible-forms' ),
            'date'          => __( 'Please enter a valid date.', 'quill-accessible-forms' ),
            'time'          => __( 'Please enter a valid time.', 'quill-accessible-forms' ),
            'min'           => __( 'Value is below the minimum allowed.', 'quill-accessible-forms' ),
            'max'           => __( 'Value is above the maximum allowed.', 'quill-accessible-forms' ),
            'file_type'     => __( 'This file type is not allowed.', 'quill-accessible-forms' ),
            'file_size'     => __( 'This file is too large.', 'quill-accessible-forms' ),
            'file_count'    => __( 'Too many files selected.', 'quill-accessible-forms' ),
        );

        /**
         * Filter error messages
         *
         * @param array $messages Error messages.
         * @param QAF_Field $field Field instance.
         */
        $messages = apply_filters( 'qaf_field_error_messages', $messages, $this );

        return isset( $messages[ $type ] ) ? $messages[ $type ] : __( 'Please enter a valid value.', 'quill-accessible-forms' );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        // Default sanitization - override in child classes
        if ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        // Default formatting - override in child classes
        if ( is_array( $value ) ) {
            return implode( ', ', $value );
        }

        return esc_html( $value );
    }

    /**
     * Format value for export
     *
     * @param mixed $value Raw value.
     * @return string Export value.
     */
    public function format_export( $value ) {
        // Default formatting - same as display
        return $this->format_display( $value );
    }

    /**
     * Format value for merge tag
     *
     * @param mixed $value Raw value.
     * @param array $options Formatting options.
     * @return string Merge tag value.
     */
    public function format_merge_tag( $value, $options = array() ) {
        return $this->format_display( $value );
    }

    /**
     * Evaluate conditional logic
     *
     * @return bool True if field should be visible.
     */
    protected function evaluate_conditional_logic() {
        $logic = $this->config['conditional_logic'] ?? array();

        if ( empty( $logic['enabled'] ) || empty( $logic['rules'] ) ) {
            return true;
        }

        // Conditional logic evaluation delegated to JavaScript on frontend
        // This is for server-side validation
        return true;
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code for this field.
     */
    public function to_bfl() {
        $bfl = $this->type;

        if ( $this->get_id() ) {
            $bfl .= ' #' . $this->get_id();
        }

        if ( $this->get_label() ) {
            $bfl .= ' "' . addslashes( $this->get_label() ) . '"';
        }

        $modifiers = array();

        if ( $this->is_required() ) {
            $modifiers[] = '@required';
        }

        if ( ! empty( $this->config['placeholder'] ) ) {
            $modifiers[] = '@placeholder "' . addslashes( $this->config['placeholder'] ) . '"';
        }

        if ( ! empty( $this->config['default_value'] ) ) {
            $modifiers[] = '@default "' . addslashes( $this->config['default_value'] ) . '"';
        }

        if ( ! empty( $modifiers ) ) {
            $bfl .= "\n  " . implode( "\n  ", $modifiers );
        }

        return $bfl;
    }

    /**
     * Get field configuration for JavaScript
     *
     * @return array JS configuration.
     */
    public function get_js_config() {
        return array(
            'id'               => $this->get_id(),
            'type'             => $this->type,
            'label'            => $this->get_label(),
            'required'         => $this->is_required(),
            'conditionalLogic' => $this->config['conditional_logic'] ?? array(),
            'validationRules'  => $this->config['validation_rules'] ?? array(),
            'errorMessages'    => array(
                'required' => $this->get_error_message( 'required' ),
            ),
        );
    }
}
