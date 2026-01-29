<?php
/**
 * Accessibility Manager
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Accessibility_Manager class
 * 
 * Central manager for all WCAG 2.2 AA compliance features.
 */
class BAF_Accessibility_Manager {

    /**
     * ARIA generator instance
     *
     * @var BAF_ARIA_Generator
     */
    private $aria_generator;

    /**
     * Focus manager instance
     *
     * @var BAF_Focus_Manager
     */
    private $focus_manager;

    /**
     * Announcer instance
     *
     * @var BAF_Announcer
     */
    private $announcer;

    /**
     * Constructor
     */
    public function __construct() {
        $this->aria_generator = new BAF_ARIA_Generator();
        $this->focus_manager  = new BAF_Focus_Manager();
        $this->announcer      = new BAF_Announcer();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter( 'baf_form_attributes', array( $this, 'add_form_aria_attributes' ), 10, 2 );
        add_filter( 'baf_field_wrapper_attributes', array( $this, 'add_field_wrapper_attributes' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_accessibility_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_accessibility_scripts' ) );
    }

    /**
     * Get ARIA generator
     *
     * @return BAF_ARIA_Generator
     */
    public function get_aria_generator() {
        return $this->aria_generator;
    }

    /**
     * Get focus manager
     *
     * @return BAF_Focus_Manager
     */
    public function get_focus_manager() {
        return $this->focus_manager;
    }

    /**
     * Get announcer
     *
     * @return BAF_Announcer
     */
    public function get_announcer() {
        return $this->announcer;
    }

    /**
     * Add ARIA attributes to form element
     *
     * @param array   $attrs Form attributes.
     * @param BAF_Form $form  Form object.
     * @return array Modified attributes.
     */
    public function add_form_aria_attributes( $attrs, $form ) {
        $attrs['role'] = 'form';
        $attrs['aria-labelledby'] = 'baf-form-' . $form->id . '-title';

        if ( ! empty( $form->description ) ) {
            $attrs['aria-describedby'] = 'baf-form-' . $form->id . '-desc';
        }

        return $attrs;
    }

    /**
     * Add attributes to field wrapper
     *
     * @param array     $attrs Field wrapper attributes.
     * @param BAF_Field $field Field object.
     * @return array Modified attributes.
     */
    public function add_field_wrapper_attributes( $attrs, $field ) {
        // Add data attribute for JS accessibility features
        $attrs['data-baf-field'] = $field->get_id();

        return $attrs;
    }

    /**
     * Enqueue frontend accessibility scripts
     */
    public function enqueue_accessibility_scripts() {
        wp_enqueue_script(
            'baf-accessibility',
            BAF_PLUGIN_URL . 'public/js/baf-accessibility.js',
            array( 'jquery' ),
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-accessibility', 'bafA11y', array(
            'announcements' => array(
                'formSubmitting'   => __( 'Submitting form, please wait.', 'bits-accessible-forms' ),
                'formSubmitted'    => __( 'Form submitted successfully.', 'bits-accessible-forms' ),
                'formError'        => __( 'There were errors in your submission. Please review and correct them.', 'bits-accessible-forms' ),
                'fieldError'       => __( 'Error:', 'bits-accessible-forms' ),
                'fieldValid'       => __( 'Field is valid.', 'bits-accessible-forms' ),
                'pageChanged'      => __( 'Page %d of %d', 'bits-accessible-forms' ),
                'fieldMoved'       => __( '%s moved to position %d', 'bits-accessible-forms' ),
                'fieldDeleted'     => __( '%s deleted', 'bits-accessible-forms' ),
                'fieldAdded'       => __( '%s added', 'bits-accessible-forms' ),
                'savingProgress'   => __( 'Saving progress...', 'bits-accessible-forms' ),
                'progressSaved'    => __( 'Progress saved.', 'bits-accessible-forms' ),
                'uploading'        => __( 'Uploading file...', 'bits-accessible-forms' ),
                'uploadComplete'   => __( 'File uploaded successfully.', 'bits-accessible-forms' ),
            ),
            'reducedMotion' => '(prefers-reduced-motion: reduce)',
        ) );
    }

    /**
     * Enqueue admin accessibility scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_accessibility_scripts( $hook ) {
        if ( strpos( $hook, 'baf' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'baf-admin-accessibility',
            BAF_PLUGIN_URL . 'admin/js/baf-accessibility.js',
            array( 'jquery' ),
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-admin-accessibility', 'bafAdminA11y', array(
            'announcements' => array(
                'formSaved'        => __( 'Form saved.', 'bits-accessible-forms' ),
                'formPublished'    => __( 'Form published.', 'bits-accessible-forms' ),
                'fieldMoved'       => __( '%s moved to position %d of %d', 'bits-accessible-forms' ),
                'fieldDeleted'     => __( '%s deleted', 'bits-accessible-forms' ),
                'fieldAdded'       => __( '%s added at position %d', 'bits-accessible-forms' ),
                'fieldDuplicated'  => __( '%s duplicated', 'bits-accessible-forms' ),
                'settingsOpened'   => __( 'Field settings panel opened for %s', 'bits-accessible-forms' ),
                'settingsClosed'   => __( 'Field settings panel closed', 'bits-accessible-forms' ),
                'modeSwitch'       => __( 'Switched to %s mode', 'bits-accessible-forms' ),
                'structureExpand'  => __( '%s expanded', 'bits-accessible-forms' ),
                'structureCollapse'=> __( '%s collapsed', 'bits-accessible-forms' ),
            ),
            'shortcuts' => array(
                'save'    => 'Ctrl+S',
                'preview' => 'Ctrl+P',
                'undo'    => 'Ctrl+Z',
                'redo'    => 'Ctrl+Y',
                'help'    => 'F1',
            ),
        ) );
    }

    /**
     * Validate form for accessibility issues
     *
     * @param BAF_Form $form Form to validate.
     * @return array Array of issues found.
     */
    public function validate_accessibility( $form ) {
        $issues = array();

        // Check form has title
        if ( empty( $form->title ) ) {
            $issues[] = array(
                'type'    => 'error',
                'code'    => 'no-title',
                'message' => __( 'Form is missing a title.', 'bits-accessible-forms' ),
            );
        }

        // Check each field
        foreach ( $form->get_fields() as $field_config ) {
            $field_issues = $this->validate_field_accessibility( $field_config );
            $issues = array_merge( $issues, $field_issues );
        }

        return $issues;
    }

    /**
     * Validate field for accessibility issues
     *
     * @param array $field_config Field configuration.
     * @return array Array of issues found.
     */
    private function validate_field_accessibility( $field_config ) {
        $issues = array();
        $field_id = $field_config['id'] ?? 'unknown';

        // Check field has label
        if ( empty( $field_config['label'] ) && empty( $field_config['aria_label'] ) ) {
            $issues[] = array(
                'type'    => 'error',
                'code'    => 'no-label',
                'field'   => $field_id,
                'message' => sprintf(
                    __( 'Field "%s" is missing a label.', 'bits-accessible-forms' ),
                    $field_id
                ),
            );
        }

        // Check placeholder is not used as label
        if ( empty( $field_config['label'] ) && ! empty( $field_config['placeholder'] ) && empty( $field_config['aria_label'] ) ) {
            $issues[] = array(
                'type'    => 'warning',
                'code'    => 'placeholder-as-label',
                'field'   => $field_id,
                'message' => sprintf(
                    __( 'Field "%s" uses placeholder as label. Provide a proper label for better accessibility.', 'bits-accessible-forms' ),
                    $field_id
                ),
            );
        }

        return $issues;
    }

    /**
     * Generate skip links for form
     *
     * @param BAF_Form $form Form object.
     * @return string Skip links HTML.
     */
    public function render_skip_links( $form ) {
        $html = '<div class="baf-skip-links">';
        
        // Skip to form content
        $html .= sprintf(
            '<a href="#baf-form-%d-content" class="baf-skip-link">%s</a>',
            $form->id,
            esc_html__( 'Skip to form content', 'bits-accessible-forms' )
        );

        // If multi-page, skip to navigation
        if ( $form->is_multipage() ) {
            $html .= sprintf(
                '<a href="#baf-form-%d-nav" class="baf-skip-link">%s</a>',
                $form->id,
                esc_html__( 'Skip to form navigation', 'bits-accessible-forms' )
            );
        }

        // Skip to submit button
        $html .= sprintf(
            '<a href="#baf-form-%d-submit" class="baf-skip-link">%s</a>',
            $form->id,
            esc_html__( 'Skip to submit button', 'bits-accessible-forms' )
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * Get keyboard shortcut help content
     *
     * @param string $context Context: 'builder' or 'form'.
     * @return array Keyboard shortcuts.
     */
    public function get_keyboard_shortcuts( $context = 'builder' ) {
        if ( $context === 'builder' ) {
            return array(
                'global' => array(
                    array( 'keys' => 'Ctrl+S', 'action' => __( 'Save form', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+P', 'action' => __( 'Preview form', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+Z', 'action' => __( 'Undo', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+Y', 'action' => __( 'Redo', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'F1', 'action' => __( 'Help', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'F6', 'action' => __( 'Cycle between panels', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Escape', 'action' => __( 'Close modal/cancel', 'bits-accessible-forms' ) ),
                ),
                'structure' => array(
                    array( 'keys' => '↑/↓', 'action' => __( 'Navigate items', 'bits-accessible-forms' ) ),
                    array( 'keys' => '←/→', 'action' => __( 'Collapse/Expand', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Enter', 'action' => __( 'Edit item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Space', 'action' => __( 'Select item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+↑', 'action' => __( 'Move item up', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+↓', 'action' => __( 'Move item down', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+C', 'action' => __( 'Copy item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+V', 'action' => __( 'Paste item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+D', 'action' => __( 'Duplicate item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'Delete', 'action' => __( 'Remove item', 'bits-accessible-forms' ) ),
                    array( 'keys' => 'F2', 'action' => __( 'Rename item', 'bits-accessible-forms' ) ),
                ),
            );
        }

        // Form keyboard shortcuts
        return array(
            'navigation' => array(
                array( 'keys' => 'Tab', 'action' => __( 'Move to next field', 'bits-accessible-forms' ) ),
                array( 'keys' => 'Shift+Tab', 'action' => __( 'Move to previous field', 'bits-accessible-forms' ) ),
                array( 'keys' => 'Enter', 'action' => __( 'Submit form (on button)', 'bits-accessible-forms' ) ),
                array( 'keys' => 'Space', 'action' => __( 'Toggle checkbox/radio', 'bits-accessible-forms' ) ),
                array( 'keys' => '↑/↓', 'action' => __( 'Navigate options in select/radio', 'bits-accessible-forms' ) ),
            ),
            'multipage' => array(
                array( 'keys' => 'Alt+N', 'action' => __( 'Next page', 'bits-accessible-forms' ) ),
                array( 'keys' => 'Alt+P', 'action' => __( 'Previous page', 'bits-accessible-forms' ) ),
            ),
        );
    }
}
