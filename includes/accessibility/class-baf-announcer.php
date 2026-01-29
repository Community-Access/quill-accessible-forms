<?php
/**
 * Announcer for Screen Readers
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Announcer class
 * 
 * Handles ARIA live region announcements for screen readers.
 */
class BAF_Announcer {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_live_regions' ) );
        add_action( 'admin_footer', array( $this, 'render_live_regions' ) );
    }

    /**
     * Render live region containers
     */
    public function render_live_regions() {
        ?>
        <!-- BAF Announcer Live Regions -->
        <div id="baf-announcer" class="baf-sr-only" aria-live="polite" aria-atomic="true"></div>
        <div id="baf-announcer-assertive" class="baf-sr-only" aria-live="assertive" aria-atomic="true"></div>
        <div id="baf-announcer-status" class="baf-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
        <?php
    }

    /**
     * Get JavaScript announcer helper
     *
     * @return string JavaScript code.
     */
    public function get_js_helper() {
        return <<<'JS'
window.BAFAnnouncer = {
    // Announcement queue
    queue: [],
    processing: false,
    delay: 150, // ms between announcements
    
    /**
     * Announce a message to screen readers
     * @param {string} message - Message to announce
     * @param {string} priority - 'polite' or 'assertive'
     */
    announce: function(message, priority) {
        priority = priority || 'polite';
        
        this.queue.push({
            message: message,
            priority: priority
        });
        
        if (!this.processing) {
            this.processQueue();
        }
    },
    
    /**
     * Process announcement queue
     */
    processQueue: function() {
        var self = this;
        
        if (this.queue.length === 0) {
            this.processing = false;
            return;
        }
        
        this.processing = true;
        var item = this.queue.shift();
        
        var region = item.priority === 'assertive' 
            ? document.getElementById('baf-announcer-assertive')
            : document.getElementById('baf-announcer');
        
        if (region) {
            // Clear and then set content to trigger announcement
            region.textContent = '';
            
            // Use setTimeout to ensure the clear is processed
            setTimeout(function() {
                region.textContent = item.message;
                
                // Process next item after delay
                setTimeout(function() {
                    self.processQueue();
                }, self.delay);
            }, 50);
        } else {
            this.processQueue();
        }
    },
    
    /**
     * Announce polite message
     * @param {string} message - Message to announce
     */
    polite: function(message) {
        this.announce(message, 'polite');
    },
    
    /**
     * Announce assertive message (interrupts)
     * @param {string} message - Message to announce
     */
    assertive: function(message) {
        this.announce(message, 'assertive');
    },
    
    /**
     * Announce status update
     * @param {string} message - Status message
     */
    status: function(message) {
        var region = document.getElementById('baf-announcer-status');
        if (region) {
            region.textContent = message;
        }
    },
    
    /**
     * Clear all regions
     */
    clear: function() {
        this.queue = [];
        ['baf-announcer', 'baf-announcer-assertive', 'baf-announcer-status'].forEach(function(id) {
            var region = document.getElementById(id);
            if (region) {
                region.textContent = '';
            }
        });
    },
    
    /**
     * Announce form submission status
     * @param {string} status - 'submitting', 'success', 'error'
     * @param {string} customMessage - Optional custom message
     */
    announceFormStatus: function(status, customMessage) {
        var messages = window.bafA11y ? window.bafA11y.announcements : {};
        var message = customMessage;
        
        switch (status) {
            case 'submitting':
                message = message || messages.formSubmitting || 'Submitting form, please wait.';
                this.polite(message);
                break;
            case 'success':
                message = message || messages.formSubmitted || 'Form submitted successfully.';
                this.assertive(message);
                break;
            case 'error':
                message = message || messages.formError || 'There were errors in your submission.';
                this.assertive(message);
                break;
        }
    },
    
    /**
     * Announce field validation result
     * @param {string} fieldLabel - Label of the field
     * @param {string} error - Error message or null if valid
     */
    announceFieldValidation: function(fieldLabel, error) {
        if (error) {
            this.polite(fieldLabel + ': ' + error);
        }
    },
    
    /**
     * Announce page change in multi-page form
     * @param {number} current - Current page number
     * @param {number} total - Total pages
     */
    announcePageChange: function(current, total) {
        var messages = window.bafA11y ? window.bafA11y.announcements : {};
        var template = messages.pageChanged || 'Page %d of %d';
        var message = template.replace('%d', current).replace('%d', total);
        this.polite(message);
    },
    
    /**
     * Announce field movement (for reordering)
     * @param {string} fieldLabel - Label of the moved field
     * @param {number} newPosition - New position (1-indexed)
     * @param {number} total - Total number of fields
     */
    announceFieldMove: function(fieldLabel, newPosition, total) {
        var messages = window.bafAdminA11y ? window.bafAdminA11y.announcements : {};
        var template = messages.fieldMoved || '%s moved to position %d of %d';
        var message = template.replace('%s', fieldLabel).replace('%d', newPosition).replace('%d', total);
        this.polite(message);
    },
    
    /**
     * Announce field deletion
     * @param {string} fieldLabel - Label of the deleted field
     */
    announceFieldDelete: function(fieldLabel) {
        var messages = window.bafAdminA11y ? window.bafAdminA11y.announcements : {};
        var template = messages.fieldDeleted || '%s deleted';
        var message = template.replace('%s', fieldLabel);
        this.polite(message);
    },
    
    /**
     * Announce field addition
     * @param {string} fieldLabel - Label of the added field
     * @param {number} position - Position where added
     */
    announceFieldAdd: function(fieldLabel, position) {
        var messages = window.bafAdminA11y ? window.bafAdminA11y.announcements : {};
        var template = messages.fieldAdded || '%s added at position %d';
        var message = template.replace('%s', fieldLabel).replace('%d', position);
        this.polite(message);
    }
};
JS;
    }
}
