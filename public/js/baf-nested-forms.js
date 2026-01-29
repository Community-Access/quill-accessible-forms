/**
 * Nested Forms JavaScript
 *
 * Handles child form modals, entry management, and validation
 * for nested form fields.
 *
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Nested Forms Manager
     */
    class BAFNestedForms {
        constructor() {
            this.instances = new Map();
            this.init();
        }

        /**
         * Initialize all nested form fields on page.
         */
        init() {
            $('.baf-nested-form').each((_, element) => {
                const instance = new NestedFormField(element);
                this.instances.set(element, instance);
            });

            // Handle dynamic additions
            $(document).on('baf:field:added', (e, field) => {
                if ($(field).hasClass('baf-nested-form')) {
                    const instance = new NestedFormField(field);
                    this.instances.set(field, instance);
                }
            });
        }

        /**
         * Get instance for element.
         */
        getInstance(element) {
            return this.instances.get(element);
        }
    }

    /**
     * Individual Nested Form Field
     */
    class NestedFormField {
        constructor(element) {
            this.$el = $(element);
            this.fieldId = this.$el.data('field-id');
            this.childFormId = this.$el.data('child-form');
            this.parentFormId = this.$el.data('parent-form');
            this.minEntries = parseInt(this.$el.data('min-entries'), 10) || 0;
            this.maxEntries = parseInt(this.$el.data('max-entries'), 10) || 0;
            this.displayStyle = this.$el.data('display-style') || 'table';

            this.$dataInput = this.$el.find('.baf-nested-entries-data');
            this.$entriesContainer = this.$el.find('.baf-nested-body');
            this.$validation = this.$el.find('.baf-nested-validation');
            this.$addBtn = this.$el.find('.baf-nested-add-btn');

            this.entries = [];
            this.editingIndex = -1;
            this.$modal = null;

            this.loadExistingEntries();
            this.bindEvents();
        }

        /**
         * Load existing entries from hidden input.
         */
        loadExistingEntries() {
            try {
                const data = this.$dataInput.val();
                this.entries = data ? JSON.parse(data) : [];
            } catch (e) {
                this.entries = [];
            }

            this.renderEntries();
        }

        /**
         * Bind event handlers.
         */
        bindEvents() {
            // Add button
            this.$addBtn.on('click', (e) => {
                e.preventDefault();
                this.openModal();
            });

            // Delegate for entry actions
            this.$entriesContainer.on('click', '.baf-nested-edit', (e) => {
                e.preventDefault();
                const index = this.getEntryIndex(e.target);
                this.editEntry(index);
            });

            this.$entriesContainer.on('click', '.baf-nested-remove', (e) => {
                e.preventDefault();
                const index = this.getEntryIndex(e.target);
                this.removeEntry(index);
            });

            // Form validation hook
            this.$el.closest('form').on('baf:validate', (e, errors) => {
                this.validate(errors);
            });
        }

        /**
         * Get entry index from target element.
         */
        getEntryIndex(target) {
            const $row = $(target).closest('[data-index]');
            return parseInt($row.data('index'), 10);
        }

        /**
         * Render all entries.
         */
        renderEntries() {
            this.$entriesContainer.empty();
            const $template = this.$el.find('.baf-nested-row-template').html();

            this.entries.forEach((entry, index) => {
                const $row = $($.parseHTML($template));
                $row.attr('data-index', index);

                // Fill in display fields
                $row.find('[data-field]').each((_, cell) => {
                    const fieldId = $(cell).data('field');
                    let value = entry[fieldId] || '';
                    
                    if (Array.isArray(value)) {
                        value = value.join(', ');
                    }
                    
                    $(cell).text(value);
                });

                this.$entriesContainer.append($row);
            });

            this.updateDataInput();
            this.updateAddButton();
        }

        /**
         * Update hidden data input.
         */
        updateDataInput() {
            this.$dataInput.val(JSON.stringify(this.entries));
            this.$dataInput.trigger('change');
        }

        /**
         * Update add button state.
         */
        updateAddButton() {
            if (this.maxEntries > 0 && this.entries.length >= this.maxEntries) {
                this.$addBtn.prop('disabled', true).addClass('baf-disabled');
            } else {
                this.$addBtn.prop('disabled', false).removeClass('baf-disabled');
            }
        }

        /**
         * Open modal for adding/editing entry.
         */
        openModal(entryData = null) {
            // Create modal from template
            const $template = this.$el.find('.baf-nested-modal-template').html();
            this.$modal = $($.parseHTML($template));

            // Add to body
            $('body').append(this.$modal);

            // Load child form
            this.loadChildForm(entryData);

            // Bind modal events
            this.$modal.find('.baf-nested-modal-close, .baf-nested-cancel, .baf-nested-modal-overlay').on('click', () => {
                this.closeModal();
            });

            this.$modal.find('.baf-nested-save').on('click', () => {
                this.saveEntry();
            });

            // Trap focus
            this.trapFocus();

            // Escape to close
            $(document).on('keydown.bafNestedModal', (e) => {
                if (e.key === 'Escape') {
                    this.closeModal();
                }
            });

            // Prevent body scroll
            $('body').addClass('baf-modal-open');

            // Announce for screen readers
            this.announce(bafNestedForms.i18n.loading);
        }

        /**
         * Close modal.
         */
        closeModal() {
            if (this.$modal) {
                this.$modal.remove();
                this.$modal = null;
            }

            this.editingIndex = -1;
            
            $(document).off('keydown.bafNestedModal');
            $('body').removeClass('baf-modal-open');

            // Return focus
            this.$addBtn.focus();
        }

        /**
         * Load child form via AJAX.
         */
        loadChildForm(entryData = null) {
            const $container = this.$modal.find('.baf-nested-form-container');
            
            $container.html('<div class="baf-loading">' + bafNestedForms.i18n.loading + '</div>');

            $.ajax({
                url: bafNestedForms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'baf_nested_get_form',
                    nonce: bafNestedForms.nonce,
                    child_form_id: this.childFormId,
                    entry_data: entryData ? JSON.stringify(entryData) : ''
                },
                success: (response) => {
                    if (response.success) {
                        $container.html(response.data.html);
                        this.initChildFormFields($container);
                        
                        // Populate with existing data if editing
                        if (entryData) {
                            this.populateForm($container, entryData);
                        }

                        // Focus first field
                        $container.find(':input:visible:first').focus();
                    } else {
                        $container.html('<p class="baf-error">' + (response.data?.message || bafNestedForms.i18n.error) + '</p>');
                    }
                },
                error: () => {
                    $container.html('<p class="baf-error">' + bafNestedForms.i18n.error + '</p>');
                }
            });
        }

        /**
         * Initialize child form fields (conditionals, etc.).
         */
        initChildFormFields($container) {
            // Trigger init event for other handlers
            $(document).trigger('baf:nested:form:loaded', [$container, this]);
        }

        /**
         * Populate form with entry data.
         */
        populateForm($container, entryData) {
            Object.entries(entryData).forEach(([fieldId, value]) => {
                const $field = $container.find(`[name="${fieldId}"]`);
                
                if ($field.length === 0) return;

                if ($field.is(':checkbox') || $field.is(':radio')) {
                    const values = Array.isArray(value) ? value : [value];
                    $field.each((_, el) => {
                        $(el).prop('checked', values.includes($(el).val()));
                    });
                } else if ($field.is('select[multiple]')) {
                    $field.val(Array.isArray(value) ? value : [value]);
                } else {
                    $field.val(value);
                }

                $field.trigger('change');
            });
        }

        /**
         * Save entry from modal.
         */
        saveEntry() {
            const $container = this.$modal.find('.baf-nested-form-container');
            const entryData = this.collectFormData($container);

            // Validate via AJAX
            $.ajax({
                url: bafNestedForms.ajaxUrl,
                type: 'POST',
                data: {
                    action: this.editingIndex >= 0 ? 'baf_nested_edit_entry' : 'baf_nested_add_entry',
                    nonce: bafNestedForms.nonce,
                    parent_form_id: this.parentFormId,
                    child_form_id: this.childFormId,
                    field_id: this.fieldId,
                    index: this.editingIndex,
                    entry_data: JSON.stringify(entryData)
                },
                beforeSend: () => {
                    this.$modal.find('.baf-nested-save').prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        if (this.editingIndex >= 0) {
                            this.entries[this.editingIndex] = response.data.entry;
                        } else {
                            this.entries.push(response.data.entry);
                        }

                        this.renderEntries();
                        this.closeModal();
                        this.clearValidation();
                    } else {
                        this.showModalErrors(response.data?.errors || {});
                    }
                },
                error: () => {
                    this.showModalError(bafNestedForms.i18n.error);
                },
                complete: () => {
                    if (this.$modal) {
                        this.$modal.find('.baf-nested-save').prop('disabled', false);
                    }
                }
            });
        }

        /**
         * Collect form data from modal.
         */
        collectFormData($container) {
            const data = {};

            $container.find(':input').each((_, input) => {
                const $input = $(input);
                const name = $input.attr('name');
                
                if (!name) return;

                if ($input.is(':checkbox')) {
                    if ($input.prop('checked')) {
                        if (!data[name]) data[name] = [];
                        data[name].push($input.val());
                    }
                } else if ($input.is(':radio')) {
                    if ($input.prop('checked')) {
                        data[name] = $input.val();
                    }
                } else if ($input.is('select[multiple]')) {
                    data[name] = $input.val() || [];
                } else {
                    data[name] = $input.val();
                }
            });

            return data;
        }

        /**
         * Edit an existing entry.
         */
        editEntry(index) {
            if (index < 0 || index >= this.entries.length) return;

            this.editingIndex = index;
            this.openModal(this.entries[index]);

            // Update modal title
            if (this.$modal) {
                this.$modal.find('.baf-nested-modal-header h3').text(
                    this.$el.find('.baf-nested-edit').first().text() || 'Edit Entry'
                );
            }
        }

        /**
         * Remove an entry.
         */
        removeEntry(index) {
            if (index < 0 || index >= this.entries.length) return;

            if (confirm(bafNestedForms.i18n.confirmRemove)) {
                this.entries.splice(index, 1);
                this.renderEntries();
                this.announce('Entry removed');
            }
        }

        /**
         * Show validation errors in modal.
         */
        showModalErrors(errors) {
            const $container = this.$modal.find('.baf-nested-form-container');

            // Clear existing errors
            $container.find('.baf-field-error').remove();
            $container.find('.baf-error').removeClass('baf-error');

            // Show new errors
            Object.entries(errors).forEach(([fieldId, message]) => {
                const $field = $container.find(`[name="${fieldId}"]`);
                const $wrapper = $field.closest('.baf-field-wrapper');
                
                $wrapper.addClass('baf-error');
                $wrapper.append(`<div class="baf-field-error" role="alert">${message}</div>`);
            });

            // Focus first error
            $container.find('.baf-error :input:first').focus();
        }

        /**
         * Show general modal error.
         */
        showModalError(message) {
            const $container = this.$modal.find('.baf-nested-form-container');
            $container.prepend(`<div class="baf-error-message" role="alert">${message}</div>`);
        }

        /**
         * Validate entries before form submission.
         */
        validate(errors) {
            if (this.minEntries > 0 && this.entries.length < this.minEntries) {
                const message = bafNestedForms.i18n.minEntries.replace('%d', this.minEntries);
                errors[this.fieldId] = message;
                this.$validation.html(`<div class="baf-error-message" role="alert">${message}</div>`);
            } else if (this.maxEntries > 0 && this.entries.length > this.maxEntries) {
                const message = bafNestedForms.i18n.maxEntries.replace('%d', this.maxEntries);
                errors[this.fieldId] = message;
                this.$validation.html(`<div class="baf-error-message" role="alert">${message}</div>`);
            } else {
                this.clearValidation();
            }
        }

        /**
         * Clear validation messages.
         */
        clearValidation() {
            this.$validation.empty();
        }

        /**
         * Trap focus within modal.
         */
        trapFocus() {
            const $modal = this.$modal;
            const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            $modal.on('keydown', (e) => {
                if (e.key !== 'Tab') return;

                const $focusable = $modal.find(focusableSelector);
                const $first = $focusable.first();
                const $last = $focusable.last();

                if (e.shiftKey && document.activeElement === $first[0]) {
                    e.preventDefault();
                    $last.focus();
                } else if (!e.shiftKey && document.activeElement === $last[0]) {
                    e.preventDefault();
                    $first.focus();
                }
            });
        }

        /**
         * Announce message for screen readers.
         */
        announce(message) {
            const $announcer = $('<div>', {
                'aria-live': 'polite',
                'aria-atomic': 'true',
                'class': 'baf-sr-only',
                'text': message
            });

            $('body').append($announcer);
            
            setTimeout(() => $announcer.remove(), 1000);
        }

        /**
         * Get current entries.
         */
        getEntries() {
            return [...this.entries];
        }

        /**
         * Set entries programmatically.
         */
        setEntries(entries) {
            this.entries = entries;
            this.renderEntries();
        }

        /**
         * Calculate aggregate from entries.
         */
        calculate(operation, fieldId = null) {
            if (operation === 'count') {
                return this.entries.length;
            }

            if (!fieldId) return 0;

            const values = this.entries
                .map(entry => parseFloat(entry[fieldId]) || 0)
                .filter(v => !isNaN(v));

            if (values.length === 0) return 0;

            switch (operation) {
                case 'sum':
                    return values.reduce((a, b) => a + b, 0);
                case 'avg':
                    return values.reduce((a, b) => a + b, 0) / values.length;
                case 'min':
                    return Math.min(...values);
                case 'max':
                    return Math.max(...values);
                default:
                    return 0;
            }
        }
    }

    // Initialize
    $(document).ready(() => {
        window.bafNestedFormsManager = new BAFNestedForms();
    });

    // Export for external use
    window.BAFNestedForms = BAFNestedForms;
    window.NestedFormField = NestedFormField;

})(jQuery);
