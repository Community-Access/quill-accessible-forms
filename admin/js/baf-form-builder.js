/**
 * BITS Accessible Forms - Form Builder JavaScript
 * 
 * Handles field card management, move up/down functionality,
 * drag-drop with keyboard alternatives, and accessibility features.
 * 
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Form Builder Controller
     */
    const BAFFormBuilder = {
        canvas: null,
        fieldPalette: null,
        settingsPanel: null,
        announcer: null,
        focusManager: null,
        
        /**
         * Initialize the form builder
         */
        init: function() {
            this.canvas = document.getElementById('baf-builder-canvas');
            this.fieldPalette = document.getElementById('baf-field-palette');
            this.settingsPanel = document.getElementById('baf-field-settings');
            
            if (!this.canvas) return;
            
            // Initialize accessibility helpers
            this.announcer = new BAFAnnouncer();
            this.focusManager = new BAFFocusManager();
            
            this.bindEvents();
            this.initDragDrop();
            this.initKeyboardShortcuts();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Move up/down buttons
            $(document).on('click', '.baf-field-move-up', this.moveFieldUp.bind(this));
            $(document).on('click', '.baf-field-move-down', this.moveFieldDown.bind(this));
            
            // Field card actions
            $(document).on('click', '.baf-field-edit', this.editField.bind(this));
            $(document).on('click', '.baf-field-duplicate', this.duplicateField.bind(this));
            $(document).on('click', '.baf-field-delete', this.deleteField.bind(this));
            
            // Field palette - add field
            $(document).on('click', '.baf-field-type', this.addField.bind(this));
            $(document).on('keydown', '.baf-field-type', this.handlePaletteKeydown.bind(this));
            
            // Settings panel
            $(document).on('click', '.baf-settings-save', this.saveFieldSettings.bind(this));
            $(document).on('click', '.baf-settings-close', this.closeSettings.bind(this));
            
            // Modal management
            $(document).on('click', '.baf-modal-close', this.closeModal.bind(this));
            $(document).on('keydown', '.baf-modal', this.handleModalKeydown.bind(this));
            
            // Update move button states when fields change
            $(document).on('baf:fieldOrderChanged', this.updateMoveButtonStates.bind(this));
        },
        
        /**
         * Initialize drag and drop with keyboard alternative
         */
        initDragDrop: function() {
            const canvas = this.canvas;
            const self = this;
            
            // Make field cards draggable
            $(canvas).on('dragstart', '.baf-field-card', function(e) {
                $(this).addClass('baf-dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', $(this).data('field-id'));
            });
            
            $(canvas).on('dragend', '.baf-field-card', function(e) {
                $(this).removeClass('baf-dragging');
                self.updateFieldOrder();
            });
            
            // Handle drop zone
            $(canvas).on('dragover', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                self.updateDropIndicator(e);
            });
            
            $(canvas).on('drop', function(e) {
                e.preventDefault();
                const fieldId = e.originalEvent.dataTransfer.getData('text/plain');
                self.handleDrop(fieldId, e);
            });
        },
        
        /**
         * Initialize keyboard shortcuts
         */
        initKeyboardShortcuts: function() {
            const self = this;
            
            $(document).on('keydown', function(e) {
                // Only handle when in canvas or field card
                const $activeCard = $(document.activeElement).closest('.baf-field-card');
                if (!$activeCard.length) return;
                
                // Ctrl/Cmd + Arrow keys for moving fields
                if (e.ctrlKey || e.metaKey) {
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        self.moveFieldUp({ currentTarget: $activeCard.find('.baf-field-move-up')[0] });
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        self.moveFieldDown({ currentTarget: $activeCard.find('.baf-field-move-down')[0] });
                    }
                }
                
                // Delete key for deleting field
                if (e.key === 'Delete' && !$(document.activeElement).is('input, textarea, select')) {
                    e.preventDefault();
                    self.deleteField({ currentTarget: $activeCard.find('.baf-field-delete')[0] });
                }
                
                // Enter key for editing field
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.editField({ currentTarget: $activeCard.find('.baf-field-edit')[0] });
                }
            });
        },
        
        /**
         * Move field up in order
         */
        moveFieldUp: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $prev = $card.prev('.baf-field-card');
            
            if (!$prev.length) {
                this.announcer.announce(bafL10n.alreadyAtTop, 'polite');
                return;
            }
            
            // Animate the swap
            const cardOffset = $card.offset();
            const prevOffset = $prev.offset();
            const distance = cardOffset.top - prevOffset.top;
            
            $card.css('transform', `translateY(-${distance}px)`);
            $prev.css('transform', `translateY(${$card.outerHeight()}px)`);
            
            setTimeout(() => {
                $card.css('transform', '');
                $prev.css('transform', '');
                $card.insertBefore($prev);
                
                // Maintain focus on the moved field
                $card.find('.baf-field-move-up').focus();
                
                this.updateFieldOrder();
                this.updateMoveButtonStates();
                
                const fieldLabel = $card.find('.baf-field-label').text();
                this.announcer.announceFieldMoved(fieldLabel, this.getFieldPosition($card));
            }, 200);
        },
        
        /**
         * Move field down in order
         */
        moveFieldDown: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $next = $card.next('.baf-field-card');
            
            if (!$next.length) {
                this.announcer.announce(bafL10n.alreadyAtBottom, 'polite');
                return;
            }
            
            // Animate the swap
            const cardOffset = $card.offset();
            const nextOffset = $next.offset();
            const distance = nextOffset.top - cardOffset.top;
            
            $card.css('transform', `translateY(${distance}px)`);
            $next.css('transform', `translateY(-${$card.outerHeight()}px)`);
            
            setTimeout(() => {
                $card.css('transform', '');
                $next.css('transform', '');
                $card.insertAfter($next);
                
                // Maintain focus on the moved field
                $card.find('.baf-field-move-down').focus();
                
                this.updateFieldOrder();
                this.updateMoveButtonStates();
                
                const fieldLabel = $card.find('.baf-field-label').text();
                this.announcer.announceFieldMoved(fieldLabel, this.getFieldPosition($card));
            }, 200);
        },
        
        /**
         * Get field position (1-indexed)
         */
        getFieldPosition: function($card) {
            return this.canvas.querySelectorAll('.baf-field-card').length > 0
                ? Array.from(this.canvas.querySelectorAll('.baf-field-card')).indexOf($card[0]) + 1
                : 1;
        },
        
        /**
         * Update move button disabled states
         */
        updateMoveButtonStates: function() {
            const $cards = $(this.canvas).find('.baf-field-card');
            const totalCards = $cards.length;
            
            $cards.each(function(index) {
                const $card = $(this);
                const $moveUp = $card.find('.baf-field-move-up');
                const $moveDown = $card.find('.baf-field-move-down');
                const fieldLabel = $card.find('.baf-field-label').text();
                
                // Update disabled state
                $moveUp.prop('disabled', index === 0);
                $moveDown.prop('disabled', index === totalCards - 1);
                
                // Update ARIA labels with position
                $moveUp.attr('aria-label', `Move ${fieldLabel} up (currently position ${index + 1} of ${totalCards})`);
                $moveDown.attr('aria-label', `Move ${fieldLabel} down (currently position ${index + 1} of ${totalCards})`);
            });
        },
        
        /**
         * Add a new field from palette
         */
        addField: function(e) {
            e.preventDefault();
            const fieldType = $(e.currentTarget).data('field-type');
            const fieldLabel = $(e.currentTarget).text();
            
            // Create field via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_add_field',
                    form_id: this.getFormId(),
                    field_type: fieldType,
                    nonce: bafL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $newCard = $(response.data.html);
                        $(this.canvas).find('.baf-dropzone').append($newCard);
                        
                        this.updateFieldOrder();
                        this.updateMoveButtonStates();
                        
                        // Focus the new field card
                        $newCard.find('.baf-field-edit').focus();
                        
                        this.announcer.announceFieldAdded(fieldLabel);
                    }
                }
            });
        },
        
        /**
         * Handle palette keyboard navigation
         */
        handlePaletteKeydown: function(e) {
            const $button = $(e.currentTarget);
            const $buttons = $(this.fieldPalette).find('.baf-field-type');
            const currentIndex = $buttons.index($button);
            let newIndex = currentIndex;
            
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                e.preventDefault();
                newIndex = (currentIndex + 1) % $buttons.length;
            } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                e.preventDefault();
                newIndex = (currentIndex - 1 + $buttons.length) % $buttons.length;
            } else if (e.key === 'Home') {
                e.preventDefault();
                newIndex = 0;
            } else if (e.key === 'End') {
                e.preventDefault();
                newIndex = $buttons.length - 1;
            }
            
            if (newIndex !== currentIndex) {
                $buttons.eq(newIndex).focus();
            }
        },
        
        /**
         * Edit field settings
         */
        editField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = $card.data('field-id');
            
            // Load field settings via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_get_field_settings',
                    field_id: fieldId,
                    nonce: bafL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.openSettingsPanel(response.data);
                    }
                }
            });
        },
        
        /**
         * Open settings panel
         */
        openSettingsPanel: function(data) {
            const $panel = $(this.settingsPanel);
            $panel.html(data.html);
            $panel.addClass('baf-panel-open');
            $panel.attr('aria-hidden', 'false');
            
            // Focus first input
            setTimeout(() => {
                $panel.find('input, select, textarea').first().focus();
            }, 100);
            
            this.announcer.announce(bafL10n.settingsPanelOpened, 'polite');
        },
        
        /**
         * Close settings panel
         */
        closeSettings: function() {
            const $panel = $(this.settingsPanel);
            $panel.removeClass('baf-panel-open');
            $panel.attr('aria-hidden', 'true');
            
            // Return focus to the field card
            const fieldId = $panel.data('field-id');
            if (fieldId) {
                $(`[data-field-id="${fieldId}"] .baf-field-edit`).focus();
            }
        },
        
        /**
         * Save field settings
         */
        saveFieldSettings: function(e) {
            e.preventDefault();
            const $form = $(this.settingsPanel).find('form');
            const formData = new FormData($form[0]);
            formData.append('action', 'baf_save_field_settings');
            formData.append('nonce', bafL10n.nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // Update field card UI
                        const $card = $(`[data-field-id="${response.data.field_id}"]`);
                        $card.find('.baf-field-label').text(response.data.label);
                        
                        this.closeSettings();
                        this.announcer.announce(bafL10n.settingsSaved, 'polite');
                    }
                }
            });
        },
        
        /**
         * Duplicate field
         */
        duplicateField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = $card.data('field-id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_duplicate_field',
                    field_id: fieldId,
                    nonce: bafL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $newCard = $(response.data.html);
                        $card.after($newCard);
                        
                        this.updateFieldOrder();
                        this.updateMoveButtonStates();
                        
                        // Focus the new field card
                        $newCard.find('.baf-field-edit').focus();
                        
                        const fieldLabel = $card.find('.baf-field-label').text();
                        this.announcer.announce(`${fieldLabel} duplicated`, 'polite');
                    }
                }
            });
        },
        
        /**
         * Delete field
         */
        deleteField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = $card.data('field-id');
            const fieldLabel = $card.find('.baf-field-label').text();
            
            // Confirm deletion
            if (!confirm(bafL10n.confirmDelete.replace('%s', fieldLabel))) {
                return;
            }
            
            // Find next focusable element before removing
            const $next = $card.next('.baf-field-card');
            const $prev = $card.prev('.baf-field-card');
            const $focusTarget = $next.length ? $next : $prev;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_delete_field',
                    field_id: fieldId,
                    nonce: bafL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $card.slideUp(200, () => {
                            $card.remove();
                            this.updateFieldOrder();
                            this.updateMoveButtonStates();
                            
                            // Move focus to next/prev field or canvas
                            if ($focusTarget.length) {
                                $focusTarget.find('.baf-field-edit').focus();
                            } else {
                                $(this.canvas).find('.baf-dropzone').focus();
                            }
                            
                            this.announcer.announceFieldDeleted(fieldLabel);
                        });
                    }
                }
            });
        },
        
        /**
         * Update field order after reordering
         */
        updateFieldOrder: function() {
            const fieldOrder = [];
            $(this.canvas).find('.baf-field-card').each(function(index) {
                fieldOrder.push($(this).data('field-id'));
            });
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_update_field_order',
                    form_id: this.getFormId(),
                    field_order: fieldOrder,
                    nonce: bafL10n.nonce
                }
            });
            
            $(document).trigger('baf:fieldOrderChanged');
        },
        
        /**
         * Get current form ID
         */
        getFormId: function() {
            return $(this.canvas).data('form-id');
        },
        
        /**
         * Handle drop indicator during drag
         */
        updateDropIndicator: function(e) {
            const $dropzone = $(this.canvas).find('.baf-dropzone');
            $dropzone.find('.baf-drop-indicator').remove();
            
            const $cards = $dropzone.find('.baf-field-card:not(.baf-dragging)');
            let insertAfter = null;
            
            $cards.each(function() {
                const rect = this.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) {
                    insertAfter = this;
                }
            });
            
            const $indicator = $('<div class="baf-drop-indicator" aria-hidden="true"></div>');
            if (insertAfter) {
                $(insertAfter).after($indicator);
            } else {
                $dropzone.prepend($indicator);
            }
        },
        
        /**
         * Handle drop
         */
        handleDrop: function(fieldId, e) {
            const $dropzone = $(this.canvas).find('.baf-dropzone');
            $dropzone.find('.baf-drop-indicator').remove();
            
            const $card = $(`[data-field-id="${fieldId}"]`);
            const $cards = $dropzone.find('.baf-field-card:not(.baf-dragging)');
            let insertAfter = null;
            
            $cards.each(function() {
                const rect = this.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) {
                    insertAfter = this;
                }
            });
            
            if (insertAfter) {
                $card.insertAfter(insertAfter);
            } else {
                $dropzone.prepend($card);
            }
            
            this.updateFieldOrder();
            this.updateMoveButtonStates();
            
            const fieldLabel = $card.find('.baf-field-label').text();
            this.announcer.announceFieldMoved(fieldLabel, this.getFieldPosition($card));
        },
        
        /**
         * Close modal
         */
        closeModal: function(e) {
            const $modal = $(e.currentTarget).closest('.baf-modal');
            $modal.attr('aria-hidden', 'true');
            this.focusManager.releaseTrap();
        },
        
        /**
         * Handle modal keyboard events
         */
        handleModalKeydown: function(e) {
            if (e.key === 'Escape') {
                this.closeModal(e);
            }
        }
    };

    /**
     * Announcer Class for ARIA live regions
     */
    class BAFAnnouncer {
        constructor() {
            this.politeRegion = document.getElementById('baf-live-polite');
            this.assertiveRegion = document.getElementById('baf-live-assertive');
            this.queue = [];
            this.isProcessing = false;
        }
        
        announce(message, type = 'polite') {
            const region = type === 'assertive' ? this.assertiveRegion : this.politeRegion;
            if (!region) return;
            
            this.queue.push({ message, region });
            this.processQueue();
        }
        
        processQueue() {
            if (this.isProcessing || this.queue.length === 0) return;
            
            this.isProcessing = true;
            const { message, region } = this.queue.shift();
            
            // Clear and set message
            region.textContent = '';
            setTimeout(() => {
                region.textContent = message;
                setTimeout(() => {
                    region.textContent = '';
                    this.isProcessing = false;
                    this.processQueue();
                }, 1000);
            }, 100);
        }
        
        announceFieldMoved(fieldLabel, position) {
            this.announce(`${fieldLabel} moved to position ${position}`, 'polite');
        }
        
        announceFieldAdded(fieldLabel) {
            this.announce(`${fieldLabel} field added`, 'polite');
        }
        
        announceFieldDeleted(fieldLabel) {
            this.announce(`${fieldLabel} field deleted`, 'polite');
        }
    }

    /**
     * Focus Manager Class
     */
    class BAFFocusManager {
        constructor() {
            this.trapContainer = null;
            this.previousFocus = null;
        }
        
        trapFocus(container) {
            this.trapContainer = container;
            this.previousFocus = document.activeElement;
            
            const focusableElements = this.getFocusableElements(container);
            if (focusableElements.length === 0) return;
            
            focusableElements[0].focus();
            
            container.addEventListener('keydown', this.handleTrapKeydown.bind(this));
        }
        
        handleTrapKeydown(e) {
            if (e.key !== 'Tab') return;
            
            const focusableElements = this.getFocusableElements(this.trapContainer);
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
        
        releaseTrap() {
            if (this.trapContainer) {
                this.trapContainer.removeEventListener('keydown', this.handleTrapKeydown.bind(this));
                this.trapContainer = null;
            }
            
            if (this.previousFocus && this.previousFocus.focus) {
                this.previousFocus.focus();
                this.previousFocus = null;
            }
        }
        
        getFocusableElements(container) {
            const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
            return Array.from(container.querySelectorAll(selector))
                .filter(el => !el.disabled && el.offsetParent !== null);
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        BAFFormBuilder.init();
    });

    // Export for external use
    window.BAFFormBuilder = BAFFormBuilder;
    window.BAFAnnouncer = BAFAnnouncer;
    window.BAFFocusManager = BAFFocusManager;

})(jQuery);
