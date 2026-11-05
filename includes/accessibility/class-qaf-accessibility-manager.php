<?php
/**
 * Accessibility Manager
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Accessibility_Manager class
 * 
 * Central manager for all WCAG 2.2 AA compliance features.
 */
class QAF_Accessibility_Manager {

    /**
     * ARIA generator instance
     *
     * @var QAF_ARIA_Generator
     */
    private $aria_generator;

    /**
     * Focus manager instance
     *
     * @var QAF_Focus_Manager
     */
    private $focus_manager;

    /**
     * Announcer instance
     *
     * @var QAF_Announcer
     */
    private $announcer;

    /**
     * Constructor
     */
    public function __construct() {
        $this->aria_generator = new QAF_ARIA_Generator();
        $this->focus_manager  = new QAF_Focus_Manager();
        $this->announcer      = new QAF_Announcer();

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter( 'qaf_form_attributes', array( $this, 'add_form_aria_attributes' ), 10, 2 );
        add_filter( 'qaf_field_wrapper_attributes', array( $this, 'add_field_wrapper_attributes' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_accessibility_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_accessibility_scripts' ) );
    }

    /**
     * Get ARIA generator
     *
     * @return QAF_ARIA_Generator
     */
    public function get_aria_generator() {
        return $this->aria_generator;
    }

    /**
     * Get focus manager
     *
     * @return QAF_Focus_Manager
     */
    public function get_focus_manager() {
        return $this->focus_manager;
    }

    /**
     * Get announcer
     *
     * @return QAF_Announcer
     */
    public function get_announcer() {
        return $this->announcer;
    }

    /**
     * Add ARIA attributes to form element
     *
     * @param array   $attrs Form attributes.
     * @param QAF_Form $form  Form object.
     * @return array Modified attributes.
     */
    public function add_form_aria_attributes( $attrs, $form ) {
        $attrs['role'] = 'form';
        $attrs['aria-labelledby'] = 'qaf-form-' . $form->id . '-title';

        if ( ! empty( $form->description ) ) {
            $attrs['aria-describedby'] = 'qaf-form-' . $form->id . '-desc';
        }

        return $attrs;
    }

    /**
     * Add attributes to field wrapper
     *
     * @param array     $attrs Field wrapper attributes.
     * @param QAF_Field $field Field object.
     * @return array Modified attributes.
     */
    public function add_field_wrapper_attributes( $attrs, $field ) {
        // Add data attribute for JS accessibility features
        $attrs['data-qaf-field'] = $field->get_id();

        return $attrs;
    }

    /**
     * Enqueue frontend accessibility scripts
     */
    public function enqueue_accessibility_scripts() {
        wp_enqueue_script(
            'qaf-accessibility',
            QAF_PLUGIN_URL . 'public/js/qaf-accessibility.js',
            array( 'jquery' ),
            QAF_VERSION,
            true
        );

        wp_localize_script( 'qaf-accessibility', 'qafA11y', array(
            'announcements' => array(
                'formSubmitting'   => __( 'Submitting form, please wait.', 'quill-accessible-forms' ),
                'formSubmitted'    => __( 'Form submitted successfully.', 'quill-accessible-forms' ),
                'formError'        => __( 'There were errors in your submission. Please review and correct them.', 'quill-accessible-forms' ),
                'fieldError'       => __( 'Error:', 'quill-accessible-forms' ),
                'fieldValid'       => __( 'Field is valid.', 'quill-accessible-forms' ),
                'pageChanged'      => __( 'Page %d of %d', 'quill-accessible-forms' ),
                'fieldMoved'       => __( '%s moved to position %d', 'quill-accessible-forms' ),
                'fieldDeleted'     => __( '%s deleted', 'quill-accessible-forms' ),
                'fieldAdded'       => __( '%s added', 'quill-accessible-forms' ),
                'savingProgress'   => __( 'Saving progress...', 'quill-accessible-forms' ),
                'progressSaved'    => __( 'Progress saved.', 'quill-accessible-forms' ),
                'uploading'        => __( 'Uploading file...', 'quill-accessible-forms' ),
                'uploadComplete'   => __( 'File uploaded successfully.', 'quill-accessible-forms' ),
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
        if ( strpos( $hook, 'qaf' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'qaf-admin-accessibility',
            QAF_PLUGIN_URL . 'admin/js/qaf-accessibility.js',
            array( 'jquery' ),
            QAF_VERSION,
            true
        );

        wp_localize_script( 'qaf-admin-accessibility', 'qafAdminA11y', array(
            'announcements' => array(
                'formSaved'        => __( 'Form saved.', 'quill-accessible-forms' ),
                'formPublished'    => __( 'Form published.', 'quill-accessible-forms' ),
                'fieldMoved'       => __( '%s moved to position %d of %d', 'quill-accessible-forms' ),
                'fieldDeleted'     => __( '%s deleted', 'quill-accessible-forms' ),
                'fieldAdded'       => __( '%s added at position %d', 'quill-accessible-forms' ),
                'fieldDuplicated'  => __( '%s duplicated', 'quill-accessible-forms' ),
                'settingsOpened'   => __( 'Field settings panel opened for %s', 'quill-accessible-forms' ),
                'settingsClosed'   => __( 'Field settings panel closed', 'quill-accessible-forms' ),
                'modeSwitch'       => __( 'Switched to %s mode', 'quill-accessible-forms' ),
                'structureExpand'  => __( '%s expanded', 'quill-accessible-forms' ),
                'structureCollapse'=> __( '%s collapsed', 'quill-accessible-forms' ),
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
     * @param QAF_Form $form Form to validate.
     * @return array Array of issues found.
     */
    public function validate_accessibility( $form ) {
        $issues = array();

        // Check form has title
        if ( empty( $form->title ) ) {
            $issues[] = array(
                'type'    => 'error',
                'code'    => 'no-title',
                'message' => __( 'Form is missing a title.', 'quill-accessible-forms' ),
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
                    __( 'Field "%s" is missing a label.', 'quill-accessible-forms' ),
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
                    __( 'Field "%s" uses placeholder as label. Provide a proper label for better accessibility.', 'quill-accessible-forms' ),
                    $field_id
                ),
            );
        }

        return $issues;
    }

    /**
     * Generate skip links for form
     *
     * @param QAF_Form $form Form object.
     * @return string Skip links HTML.
     */
    public function render_skip_links( $form ) {
        $html = '<div class="qaf-skip-links">';
        
        // Skip to form content
        $html .= sprintf(
            '<a href="#qaf-form-%d-content" class="qaf-skip-link">%s</a>',
            $form->id,
            esc_html__( 'Skip to form content', 'quill-accessible-forms' )
        );

        // If multi-page, skip to navigation
        if ( $form->is_multipage() ) {
            $html .= sprintf(
                '<a href="#qaf-form-%d-nav" class="qaf-skip-link">%s</a>',
                $form->id,
                esc_html__( 'Skip to form navigation', 'quill-accessible-forms' )
            );
        }

        // Skip to submit button
        $html .= sprintf(
            '<a href="#qaf-form-%d-submit" class="qaf-skip-link">%s</a>',
            $form->id,
            esc_html__( 'Skip to submit button', 'quill-accessible-forms' )
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
                    array( 'keys' => 'Ctrl+S', 'action' => __( 'Save form', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+P', 'action' => __( 'Preview form', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+Z', 'action' => __( 'Undo', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+Y', 'action' => __( 'Redo', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'F1', 'action' => __( 'Help', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'F6', 'action' => __( 'Cycle between panels', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Escape', 'action' => __( 'Close modal/cancel', 'quill-accessible-forms' ) ),
                ),
                'structure' => array(
                    array( 'keys' => '↑/↓', 'action' => __( 'Navigate items', 'quill-accessible-forms' ) ),
                    array( 'keys' => '←/→', 'action' => __( 'Collapse/Expand', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Enter', 'action' => __( 'Edit item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Space', 'action' => __( 'Select item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+↑', 'action' => __( 'Move item up', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+↓', 'action' => __( 'Move item down', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+C', 'action' => __( 'Copy item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+V', 'action' => __( 'Paste item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Ctrl+D', 'action' => __( 'Duplicate item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'Delete', 'action' => __( 'Remove item', 'quill-accessible-forms' ) ),
                    array( 'keys' => 'F2', 'action' => __( 'Rename item', 'quill-accessible-forms' ) ),
                ),
            );
        }

        // Form keyboard shortcuts
        return array(
            'navigation' => array(
                array( 'keys' => 'Tab', 'action' => __( 'Move to next field', 'quill-accessible-forms' ) ),
                array( 'keys' => 'Shift+Tab', 'action' => __( 'Move to previous field', 'quill-accessible-forms' ) ),
                array( 'keys' => 'Enter', 'action' => __( 'Submit form (on button)', 'quill-accessible-forms' ) ),
                array( 'keys' => 'Space', 'action' => __( 'Toggle checkbox/radio', 'quill-accessible-forms' ) ),
                array( 'keys' => '↑/↓', 'action' => __( 'Navigate options in select/radio', 'quill-accessible-forms' ) ),
            ),
            'multipage' => array(
                array( 'keys' => 'Alt+N', 'action' => __( 'Next page', 'quill-accessible-forms' ) ),
                array( 'keys' => 'Alt+P', 'action' => __( 'Previous page', 'quill-accessible-forms' ) ),
            ),
        );
    }
}
