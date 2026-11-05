/**
 * QAF Field Type Converter
 *
 * Handles field type conversion with preview and validation.
 *
 * @package QAF
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Field Type Converter Controller
     */
    window.QAF_FieldConverter = {
        /**
         * Current field being converted
         */
        currentField: null,

        /**
         * Compatibility matrix from server
         */
        compatibilityMatrix: {},

        /**
         * Form configuration reference
         */
        formConfig: null,

        /**
         * Initialize the converter
         */
        init: function() {
            this.bindEvents();
            this.loadCompatibilityData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Convert button in Structure Mode
            $(document).on('click', '[data-action="convert"]', this.onConvertClick.bind(this));

            // Convert button in Visual Builder
            $(document).on('click', '.qaf-field-action-convert', this.onConvertClick.bind(this));

            // Type selection in modal
            $(document).on('change', '[name="qaf_target_type"]', this.onTypeSelect.bind(this));

            // Type option click (for visual selection)
            $(document).on('click', '.qaf-type-option', this.onTypeOptionClick.bind(this));

            // Confirm conversion
            $(document).on('click', '#qaf-conversion-confirm', this.onConfirmConvert.bind(this));

            // Modal close handlers
            $(document).on('click', '[data-close-modal]', this.closeModal.bind(this));
            $(document).on('keydown', this.onKeyDown.bind(this));
        },

        /**
         * Load compatibility data from server
         */
        loadCompatibilityData: function() {
            if (typeof qafConverterData !== 'undefined') {
                this.compatibilityMatrix = qafConverterData.compatibility || {};
            }
        },

        /**
         * Handle convert button click
         */
        onConvertClick: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(e.currentTarget);
            var $item = $button.closest('[data-field-id]');

            if (!$item.length) {
                $item = $button.closest('.qaf-tree-item, .qaf-field-card');
            }

            var fieldId = $item.data('field-id') || $item.attr('data-field-id');
            
            if (!fieldId) {
                console.error('QAF: No field ID found for conversion');
                return;
            }

            // Get field data
            var field = this.getFieldData(fieldId);
            if (!field) {
                console.error('QAF: Field not found:', fieldId);
                return;
            }

            this.currentField = field;
            this.openModal(field);
        },

        /**
         * Get field data from form config
         */
        getFieldData: function(fieldId) {
            // Try to get from global form config
            if (window.qafFormConfig && window.qafFormConfig.fields) {
                return this.findField(window.qafFormConfig.fields, fieldId);
            }

            // Try Structure Mode data
            if (window.qafStructureData && window.qafStructureData.fields) {
                return this.findField(window.qafStructureData.fields, fieldId);
            }

            // Try Visual Builder data
            if (window.qafBuilderData && window.qafBuilderData.fields) {
                return this.findField(window.qafBuilderData.fields, fieldId);
            }

            return null;
        },

        /**
         * Find field in nested structure
         */
        findField: function(fields, fieldId) {
            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                if (field.id === fieldId) {
                    return field;
                }
                // Check nested fields (columns, sections)
                if (field.fields) {
                    var found = this.findField(field.fields, fieldId);
                    if (found) return found;
                }
            }
            return null;
        },

        /**
         * Open conversion modal
         */
        openModal: function(field) {
            var $modal = $('#qaf-conversion-modal');
            
            if (!$modal.length) {
                console.error('QAF: Conversion modal not found');
                return;
            }

            // Set current type
            var currentType = field.type || 'text';
            $('#qaf-conversion-current-type').text(this.getTypeLabel(currentType));

            // Reset selections
            $modal.find('[name="qaf_target_type"]').prop('checked', false);
            $modal.find('.qaf-type-option').removeClass('selected');
            $modal.find('#qaf-conversion-confirm').prop('disabled', true);

            // Update compatibility indicators
            this.updateCompatibilityIndicators(currentType);

            // Hide preview sections
            $modal.find('#qaf-conversion-preview').attr('hidden', true);
            $modal.find('#qaf-conditional-warnings').attr('hidden', true);
            $modal.find('#qaf-conversion-compatibility').attr('hidden', true);

            // Disable current type
            $modal.find('.qaf-type-option[data-type="' + currentType + '"]')
                .addClass('disabled')
                .find('input').prop('disabled', true);

            // Show modal
            $modal.removeAttr('hidden');
            $modal.find('[name="qaf_target_type"]:not(:disabled)').first().focus();

            // Announce to screen readers
            this.announce('Field type conversion dialog opened. Current type: ' + this.getTypeLabel(currentType));
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            var $modal = $('#qaf-conversion-modal');
            $modal.attr('hidden', true);
            this.currentField = null;

            // Return focus to trigger
            var $trigger = $('[data-action="convert"]:focus, .qaf-field-action-convert:focus');
            if (!$trigger.length) {
                $trigger = $('.qaf-tree-item[data-field-id].focused, .qaf-field-card.selected').find('[data-action="convert"], .qaf-field-action-convert');
            }
            if ($trigger.length) {
                $trigger.first().focus();
            }
        },

        /**
         * Handle keyboard events
         */
        onKeyDown: function(e) {
            var $modal = $('#qaf-conversion-modal');
            if ($modal.attr('hidden') !== undefined) {
                return;
            }

            if (e.key === 'Escape') {
                this.closeModal();
            }
        },

        /**
         * Update compatibility indicators for all type options
         */
        updateCompatibilityIndicators: function(sourceType) {
            var self = this;
            var $modal = $('#qaf-conversion-modal');

            $modal.find('.qaf-type-option').each(function() {
                var $option = $(this);
                var targetType = $option.data('type');
                var compat = self.getCompatibility(sourceType, targetType);

                $option.removeClass('compat-lossless compat-lossy compat-incompatible');
                
                var $indicator = $option.find('.qaf-type-compat');
                $indicator.empty();

                if (targetType === sourceType) {
                    $option.addClass('disabled');
                    $indicator.text('Current');
                    return;
                }

                $option.removeClass('disabled');
                $option.find('input').prop('disabled', false);

                switch (compat) {
                    case 'lossless':
                        $option.addClass('compat-lossless');
                        $indicator.html('<span class="compat-icon" aria-hidden="true">✓</span><span class="sr-only">Fully compatible</span>');
                        break;
                    case 'lossy':
                        $option.addClass('compat-lossy');
                        $indicator.html('<span class="compat-icon" aria-hidden="true">⚠</span><span class="sr-only">Some data may be lost</span>');
                        break;
                    default:
                        $option.addClass('compat-incompatible');
                        $indicator.html('<span class="compat-icon" aria-hidden="true">✗</span><span class="sr-only">May require manual adjustment</span>');
                }
            });
        },

        /**
         * Get compatibility level between two types
         */
        getCompatibility: function(sourceType, targetType) {
            if (sourceType === targetType) {
                return 'lossless';
            }

            if (this.compatibilityMatrix[sourceType] && 
                this.compatibilityMatrix[sourceType][targetType]) {
                return this.compatibilityMatrix[sourceType][targetType];
            }

            // Default fallback rules
            if (targetType === 'text' || targetType === 'textarea') {
                return 'lossy';
            }

            if (sourceType === 'text' || sourceType === 'textarea') {
                return 'lossy';
            }

            return 'incompatible';
        },

        /**
         * Handle type option click
         */
        onTypeOptionClick: function(e) {
            var $option = $(e.currentTarget);
            if ($option.hasClass('disabled')) {
                return;
            }

            $option.find('input').prop('checked', true).trigger('change');
        },

        /**
         * Handle type selection change
         */
        onTypeSelect: function(e) {
            var $input = $(e.target);
            var targetType = $input.val();

            // Update visual selection
            $('.qaf-type-option').removeClass('selected');
            $input.closest('.qaf-type-option').addClass('selected');

            // Enable confirm button
            $('#qaf-conversion-confirm').prop('disabled', false);

            // Load preview
            this.loadConversionPreview(targetType);
        },

        /**
         * Load conversion preview via AJAX
         */
        loadConversionPreview: function(targetType) {
            var self = this;
            var field = this.currentField;

            if (!field) return;

            // Show loading state
            var $preview = $('#qaf-conversion-preview');
            var $compat = $('#qaf-conversion-compatibility');
            
            $preview.attr('hidden', true);
            $compat.attr('hidden', true);

            // Make AJAX request for preview
            $.ajax({
                url: qafConverterData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'qaf_preview_conversion',
                    nonce: qafConverterData.nonce,
                    field: JSON.stringify(field),
                    target_type: targetType,
                    form_id: this.getFormId()
                },
                success: function(response) {
                    if (response.success) {
                        self.displayPreview(response.data);
                    } else {
                        self.showError(response.data.message || 'Preview failed');
                    }
                },
                error: function() {
                    self.showError('Failed to load preview');
                }
            });
        },

        /**
         * Display conversion preview
         */
        displayPreview: function(data) {
            var $preview = $('#qaf-conversion-preview');
            var $compat = $('#qaf-conversion-compatibility');

            // Compatibility info
            if (data.report) {
                var compat = data.report.compatibility || 'incompatible';
                var $status = $compat.find('.qaf-compat-status');
                var $warnings = $compat.find('.qaf-compat-warnings');

                $status.empty().append(this.getCompatibilityBadge(compat));
                $warnings.empty();

                if (data.report.warnings && data.report.warnings.length) {
                    var $list = $('<ul class="qaf-warning-list"></ul>');
                    data.report.warnings.forEach(function(warning) {
                        $list.append('<li>' + this.escapeHtml(warning) + '</li>');
                    }, this);
                    $warnings.append($list);
                }

                $compat.removeAttr('hidden');
            }

            // Property preview
            if (data.report) {
                var $preserved = $('#qaf-preview-preserved-list').empty();
                var $lost = $('#qaf-preview-lost-list').empty();
                var $added = $('#qaf-preview-added-list').empty();

                (data.report.preserved || []).forEach(function(prop) {
                    $preserved.append('<li>' + this.formatPropertyName(prop) + '</li>');
                }, this);

                (data.report.dropped || []).forEach(function(prop) {
                    $lost.append('<li>' + this.formatPropertyName(prop) + '</li>');
                }, this);

                (data.report.transformed || []).forEach(function(item) {
                    var text = this.formatPropertyName(item.property);
                    if (item.note) text += ' - ' + item.note;
                    $added.append('<li>' + text + '</li>');
                }, this);

                // Only show if there's content
                if ($preserved.children().length || $lost.children().length || $added.children().length) {
                    $preview.removeAttr('hidden');
                }
            }

            // Conditional logic warnings
            if (data.conditional_impact && data.conditional_impact.length) {
                this.displayConditionalWarnings(data.conditional_impact);
            } else {
                $('#qaf-conditional-warnings').attr('hidden', true);
            }
        },

        /**
         * Display conditional logic warnings
         */
        displayConditionalWarnings: function(affected) {
            var $section = $('#qaf-conditional-warnings');
            var $list = $section.find('.qaf-conditional-affected').empty();
            var $message = $section.find('.qaf-conditional-message');

            $message.text(affected.length + ' conditional rule(s) may be affected by this conversion.');

            affected.forEach(function(item) {
                var $li = $('<li></li>');
                $li.html(
                    '<strong>' + this.escapeHtml(item.field_label) + '</strong>: ' +
                    this.escapeHtml(item.issue) +
                    (item.suggestion ? ' <em>(' + this.escapeHtml(item.suggestion) + ')</em>' : '')
                );
                if (item.auto_fixable) {
                    $li.append(' <span class="qaf-auto-fixable">Auto-fixable</span>');
                }
                $list.append($li);
            }, this);

            $section.removeAttr('hidden');
        },

        /**
         * Handle confirm conversion
         */
        onConfirmConvert: function(e) {
            e.preventDefault();

            var targetType = $('[name="qaf_target_type"]:checked').val();
            if (!targetType || !this.currentField) {
                return;
            }

            var autoRepair = $('[name="qaf_auto_repair"]').is(':checked');

            this.performConversion(targetType, autoRepair);
        },

        /**
         * Perform the actual conversion
         */
        performConversion: function(targetType, autoRepair) {
            var self = this;
            var field = this.currentField;

            // Show loading
            var $confirm = $('#qaf-conversion-confirm');
            $confirm.prop('disabled', true).text('Converting...');

            $.ajax({
                url: qafConverterData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'qaf_convert_field',
                    nonce: qafConverterData.nonce,
                    field_id: field.id,
                    target_type: targetType,
                    auto_repair: autoRepair ? 1 : 0,
                    form_id: this.getFormId()
                },
                success: function(response) {
                    if (response.success) {
                        self.onConversionSuccess(response.data);
                    } else {
                        self.showError(response.data.message || 'Conversion failed');
                        $confirm.prop('disabled', false).text('Convert Field');
                    }
                },
                error: function() {
                    self.showError('Conversion request failed');
                    $confirm.prop('disabled', false).text('Convert Field');
                }
            });
        },

        /**
         * Handle successful conversion
         */
        onConversionSuccess: function(data) {
            // Close modal
            this.closeModal();

            // Update UI
            this.updateFieldInUI(data.field);

            // Show success message
            this.announce('Field converted to ' + this.getTypeLabel(data.field.type));

            // Trigger refresh events
            $(document).trigger('qaf:field:converted', [data.field, data.report]);

            // Show repair summary if any
            if (data.repairs && data.repairs.length) {
                this.showRepairSummary(data.repairs);
            }
        },

        /**
         * Update field in the UI after conversion
         */
        updateFieldInUI: function(field) {
            var fieldId = field.id;

            // Update Structure Mode
            var $treeItem = $('.qaf-tree-item[data-field-id="' + fieldId + '"]');
            if ($treeItem.length) {
                $treeItem.attr('data-field-type', field.type);
                $treeItem.find('.qaf-tree-field-type').text(field.type);
                $treeItem.find('.qaf-tree-icon').text(this.getTypeIcon(field.type));
            }

            // Update Visual Builder
            var $card = $('.qaf-field-card[data-field-id="' + fieldId + '"]');
            if ($card.length) {
                $card.attr('data-field-type', field.type);
                $card.find('.qaf-field-type-label').text(this.getTypeLabel(field.type));
                $card.find('.qaf-field-type-icon').text(this.getTypeIcon(field.type));
            }

            // Update form config
            this.updateFormConfig(field);
        },

        /**
         * Update form config with converted field
         */
        updateFormConfig: function(field) {
            var configs = [
                window.qafFormConfig,
                window.qafStructureData,
                window.qafBuilderData
            ];

            configs.forEach(function(config) {
                if (config && config.fields) {
                    this.replaceFieldInArray(config.fields, field);
                }
            }, this);
        },

        /**
         * Replace field in nested array
         */
        replaceFieldInArray: function(fields, newField) {
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].id === newField.id) {
                    fields[i] = newField;
                    return true;
                }
                if (fields[i].fields) {
                    if (this.replaceFieldInArray(fields[i].fields, newField)) {
                        return true;
                    }
                }
            }
            return false;
        },

        /**
         * Show repair summary
         */
        showRepairSummary: function(repairs) {
            var message = repairs.length + ' conditional rule(s) were automatically repaired.';
            this.showNotice(message, 'success');
        },

        /**
         * Get form ID
         */
        getFormId: function() {
            return $('[name="form_id"]').val() || 
                   $('[data-form-id]').data('form-id') ||
                   window.qafFormId || 0;
        },

        /**
         * Get type label
         */
        getTypeLabel: function(type) {
            var labels = {
                'text': 'Text',
                'textarea': 'Textarea',
                'email': 'Email',
                'phone': 'Phone',
                'website': 'Website',
                'password': 'Password',
                'hidden': 'Hidden',
                'number': 'Number',
                'range': 'Range Slider',
                'price': 'Price',
                'quantity': 'Quantity',
                'select': 'Dropdown',
                'multiselect': 'Multi-Select',
                'radio': 'Radio Buttons',
                'checkbox': 'Checkboxes',
                'consent': 'Consent',
                'toggle': 'Toggle',
                'date': 'Date',
                'time': 'Time',
                'datetime': 'Date & Time',
                'daterange': 'Date Range',
                'rating': 'Star Rating',
                'likert': 'Likert Scale',
                'nps': 'NPS Score',
                'ranking': 'Ranking',
                'fileupload': 'File Upload'
            };
            return labels[type] || type;
        },

        /**
         * Get type icon
         */
        getTypeIcon: function(type) {
            var icons = {
                'text': '📝',
                'textarea': '📄',
                'email': '✉️',
                'phone': '📞',
                'website': '🌐',
                'password': '🔒',
                'hidden': '👁️',
                'number': '#️⃣',
                'range': '🎚️',
                'price': '💰',
                'quantity': '🔢',
                'select': '🔽',
                'multiselect': '☑️',
                'radio': '🔘',
                'checkbox': '✅',
                'consent': '📋',
                'toggle': '🔀',
                'date': '📅',
                'time': '⏰',
                'datetime': '📆',
                'daterange': '📅',
                'rating': '⭐',
                'likert': '📊',
                'nps': '📈',
                'ranking': '🏆',
                'fileupload': '📎'
            };
            return icons[type] || '📝';
        },

        /**
         * Get compatibility badge HTML
         */
        getCompatibilityBadge: function(compat) {
            var classes = {
                'lossless': 'qaf-badge-success',
                'lossy': 'qaf-badge-warning',
                'incompatible': 'qaf-badge-error'
            };
            var labels = {
                'lossless': 'Fully Compatible',
                'lossy': 'Some Data May Be Lost',
                'incompatible': 'May Require Manual Adjustment'
            };
            return '<span class="qaf-badge ' + classes[compat] + '">' + labels[compat] + '</span>';
        },

        /**
         * Format property name for display
         */
        formatPropertyName: function(prop) {
            return prop.replace(/_/g, ' ').replace(/\b\w/g, function(c) {
                return c.toUpperCase();
            });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="qaf-notice qaf-notice-' + type + '" role="alert">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="qaf-notice-dismiss">&times;</button>' +
                '</div>');

            $('body').append($notice);

            // Auto-dismiss
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);

            // Click dismiss
            $notice.find('.qaf-notice-dismiss').on('click', function() {
                $notice.remove();
            });
        },

        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            var $announcer = $('#qaf-announcer');
            if (!$announcer.length) {
                $announcer = $('<div id="qaf-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>');
                $('body').append($announcer);
            }
            $announcer.text(message);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        QAF_FieldConverter.init();
    });

})(jQuery);
