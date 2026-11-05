<?php
/**
 * Focus Manager
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Focus_Manager class
 * 
 * Manages focus trapping, restoration, and movement for accessibility.
 */
class QAF_Focus_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_focus_trap_container' ) );
        add_action( 'admin_footer', array( $this, 'render_focus_trap_container' ) );
    }

    /**
     * Render focus trap container for modals
     */
    public function render_focus_trap_container() {
        ?>
        <div id="qaf-focus-trap-start" tabindex="0" class="qaf-sr-only" aria-hidden="true"></div>
        <div id="qaf-focus-trap-end" tabindex="0" class="qaf-sr-only" aria-hidden="true"></div>
        <?php
    }

    /**
     * Get focusable elements selector
     *
     * @return string CSS selector for focusable elements.
     */
    public function get_focusable_selector() {
        return 'a[href], area[href], input:not([disabled]):not([type="hidden"]), ' .
               'select:not([disabled]), textarea:not([disabled]), button:not([disabled]), ' .
               'iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable]';
    }

    /**
     * Generate focus management data attributes for a container
     *
     * @param array $options Focus management options.
     * @return array Data attributes.
     */
    public function get_container_data_attrs( $options = array() ) {
        $defaults = array(
            'trap'           => false,
            'auto_focus'     => true,
            'restore_focus'  => true,
            'wrap_around'    => true,
            'roving_tabindex'=> false,
        );

        $options = wp_parse_args( $options, $defaults );

        $attrs = array(
            'data-qaf-focus-container' => 'true',
        );

        if ( $options['trap'] ) {
            $attrs['data-qaf-focus-trap'] = 'true';
        }

        if ( $options['auto_focus'] ) {
            $attrs['data-qaf-auto-focus'] = 'true';
        }

        if ( $options['restore_focus'] ) {
            $attrs['data-qaf-restore-focus'] = 'true';
        }

        if ( $options['wrap_around'] ) {
            $attrs['data-qaf-focus-wrap'] = 'true';
        }

        if ( $options['roving_tabindex'] ) {
            $attrs['data-qaf-roving-tabindex'] = 'true';
        }

        return $attrs;
    }

    /**
     * Generate JavaScript for focus management
     *
     * @return string JavaScript code.
     */
    public function get_js_helpers() {
        return <<<'JS'
window.QAFFocusManager = {
    // Store the element that had focus before trap
    previousFocus: null,
    
    // Focusable elements selector
    focusableSelector: 'a[href], area[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable]',
    
    /**
     * Get all focusable elements within a container
     */
    getFocusableElements: function(container) {
        return Array.from(container.querySelectorAll(this.focusableSelector))
            .filter(function(el) {
                return el.offsetParent !== null && !el.hasAttribute('aria-hidden');
            });
    },
    
    /**
     * Trap focus within a container
     */
    trapFocus: function(container) {
        var self = this;
        var focusable = this.getFocusableElements(container);
        
        if (focusable.length === 0) return;
        
        var firstFocusable = focusable[0];
        var lastFocusable = focusable[focusable.length - 1];
        
        // Store previous focus
        this.previousFocus = document.activeElement;
        
        // Focus first element
        firstFocusable.focus();
        
        // Handle tab key
        container.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') return;
            
            focusable = self.getFocusableElements(container);
            firstFocusable = focusable[0];
            lastFocusable = focusable[focusable.length - 1];
            
            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        });
        
        container.setAttribute('data-qaf-focus-trapped', 'true');
    },
    
    /**
     * Release focus trap and restore previous focus
     */
    releaseTrap: function(container) {
        container.removeAttribute('data-qaf-focus-trapped');
        
        if (this.previousFocus && this.previousFocus.focus) {
            this.previousFocus.focus();
            this.previousFocus = null;
        }
    },
    
    /**
     * Move focus to first invalid field
     */
    focusFirstError: function(form) {
        var errorField = form.querySelector('[aria-invalid="true"], .qaf-field-error input, .qaf-field-error select, .qaf-field-error textarea');
        if (errorField) {
            errorField.focus();
            return true;
        }
        return false;
    },
    
    /**
     * Move focus to specific field by ID
     */
    focusField: function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) {
            field.focus();
            return true;
        }
        return false;
    },
    
    /**
     * Initialize roving tabindex for a group
     */
    initRovingTabindex: function(container, selector) {
        var items = container.querySelectorAll(selector);
        if (items.length === 0) return;
        
        // Set initial tabindex
        items.forEach(function(item, index) {
            item.setAttribute('tabindex', index === 0 ? '0' : '-1');
        });
        
        container.addEventListener('keydown', function(e) {
            var currentIndex = Array.from(items).indexOf(document.activeElement);
            if (currentIndex === -1) return;
            
            var newIndex = currentIndex;
            
            switch (e.key) {
                case 'ArrowDown':
                case 'ArrowRight':
                    e.preventDefault();
                    newIndex = (currentIndex + 1) % items.length;
                    break;
                case 'ArrowUp':
                case 'ArrowLeft':
                    e.preventDefault();
                    newIndex = (currentIndex - 1 + items.length) % items.length;
                    break;
                case 'Home':
                    e.preventDefault();
                    newIndex = 0;
                    break;
                case 'End':
                    e.preventDefault();
                    newIndex = items.length - 1;
                    break;
                default:
                    return;
            }
            
            items[currentIndex].setAttribute('tabindex', '-1');
            items[newIndex].setAttribute('tabindex', '0');
            items[newIndex].focus();
        });
    },
    
    /**
     * Skip to main content
     */
    skipToContent: function(targetId) {
        var target = document.getElementById(targetId);
        if (target) {
            target.setAttribute('tabindex', '-1');
            target.focus();
            // Remove tabindex after blur to maintain normal tab order
            target.addEventListener('blur', function() {
                target.removeAttribute('tabindex');
            }, { once: true });
        }
    }
};
JS;
    }
}
