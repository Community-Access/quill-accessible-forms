/**
 * QAF Specialty Fields
 *
 * JavaScript handlers for currency, OTP, slider, and typed signature fields.
 *
 * @package QAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Currency Field Handler
     */
    const CurrencyField = {
        /**
         * Initialize currency fields
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('input', '.qaf-currency-display', this.handleInput.bind(this));
            $(document).on('blur', '.qaf-currency-display', this.handleBlur.bind(this));
            $(document).on('focus', '.qaf-currency-display', this.handleFocus.bind(this));
        },

        /**
         * Handle input on currency field
         *
         * @param {Event} e Input event
         */
        handleInput(e) {
            const $display = $(e.target);
            const $hidden = $display.siblings('.qaf-currency-value');
            const config = this.getConfig($display);

            // Get raw numeric value
            let value = this.parseValue($display.val(), config);

            // Update hidden field
            $hidden.val(value);

            // Don't reformat on input - let user type freely
        },

        /**
         * Handle blur - format the value
         *
         * @param {Event} e Blur event
         */
        handleBlur(e) {
            const $display = $(e.target);
            const config = this.getConfig($display);
            let value = this.parseValue($display.val(), config);

            if (value === '' || isNaN(parseFloat(value))) {
                $display.val('');
                return;
            }

            // Format for display
            $display.val(this.formatValue(parseFloat(value), config));
        },

        /**
         * Handle focus - select all text
         *
         * @param {Event} e Focus event
         */
        handleFocus(e) {
            // Select all on focus for easy replacement
            setTimeout(() => e.target.select(), 0);
        },

        /**
         * Get field configuration from data attributes
         *
         * @param {jQuery} $field Field element
         * @return {Object} Configuration
         */
        getConfig($field) {
            return {
                symbol: $field.data('currency-symbol') || '$',
                symbolPosition: $field.data('symbol-position') || 'before',
                decimals: parseInt($field.data('decimals')) || 2,
                thousandsSeparator: $field.data('thousands-separator') || ',',
                decimalSeparator: $field.data('decimal-separator') || '.'
            };
        },

        /**
         * Parse a display value to raw number
         *
         * @param {string} value Display value
         * @param {Object} config Configuration
         * @return {string} Raw numeric value
         */
        parseValue(value, config) {
            if (!value) return '';

            // Remove currency symbol
            value = value.replace(new RegExp('[' + this.escapeRegex(config.symbol) + '\\s]', 'g'), '');

            // Remove thousands separators
            value = value.split(config.thousandsSeparator).join('');

            // Normalize decimal separator to period
            if (config.decimalSeparator !== '.') {
                value = value.replace(config.decimalSeparator, '.');
            }

            // Remove any remaining non-numeric characters except minus and decimal
            value = value.replace(/[^\d.-]/g, '');

            return value;
        },

        /**
         * Format a raw value for display
         *
         * @param {number} value Raw numeric value
         * @param {Object} config Configuration
         * @return {string} Formatted display value
         */
        formatValue(value, config) {
            if (isNaN(value)) return '';

            // Format number
            let parts = value.toFixed(config.decimals).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, config.thousandsSeparator);
            let formatted = parts.join(config.decimalSeparator);

            // Add symbol
            if (config.symbolPosition === 'before') {
                formatted = config.symbol + formatted;
            } else {
                formatted = formatted + ' ' + config.symbol;
            }

            return formatted;
        },

        /**
         * Escape special regex characters
         *
         * @param {string} str String to escape
         * @return {string} Escaped string
         */
        escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
    };

    /**
     * OTP Field Handler
     */
    const OTPField = {
        /**
         * Initialize OTP fields
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('input', '.qaf-otp-digit', this.handleInput.bind(this));
            $(document).on('keydown', '.qaf-otp-digit', this.handleKeydown.bind(this));
            $(document).on('paste', '.qaf-otp-digit', this.handlePaste.bind(this));
            $(document).on('focus', '.qaf-otp-digit', this.handleFocus.bind(this));
        },

        /**
         * Handle input in OTP digit
         *
         * @param {Event} e Input event
         */
        handleInput(e) {
            const $input = $(e.target);
            const $wrapper = $input.closest('.qaf-otp-wrapper');
            const $digits = $wrapper.find('.qaf-otp-digit');
            const $hidden = $wrapper.find('.qaf-otp-value');
            const mode = $wrapper.data('mode') || 'numeric';

            // Validate input based on mode
            let value = $input.val();
            value = this.sanitizeInput(value, mode);
            $input.val(value);

            // Move to next input if value entered
            if (value.length >= 1) {
                const index = $digits.index($input);
                if (index < $digits.length - 1) {
                    $digits.eq(index + 1).focus();
                }
            }

            // Update combined value
            this.updateCombinedValue($wrapper);

            // Auto-submit check
            if ($wrapper.data('auto-submit')) {
                const combined = this.getCombinedValue($digits);
                const expectedLength = $digits.length;
                if (combined.length === expectedLength) {
                    this.triggerComplete($wrapper, combined);
                }
            }
        },

        /**
         * Handle keydown for navigation
         *
         * @param {Event} e Keydown event
         */
        handleKeydown(e) {
            const $input = $(e.target);
            const $wrapper = $input.closest('.qaf-otp-wrapper');
            const $digits = $wrapper.find('.qaf-otp-digit');
            const index = $digits.index($input);

            switch (e.key) {
                case 'Backspace':
                    if ($input.val() === '' && index > 0) {
                        e.preventDefault();
                        $digits.eq(index - 1).val('').focus();
                    }
                    // Update after backspace
                    setTimeout(() => this.updateCombinedValue($wrapper), 0);
                    break;

                case 'Delete':
                    $input.val('');
                    this.updateCombinedValue($wrapper);
                    break;

                case 'ArrowLeft':
                    if (index > 0) {
                        e.preventDefault();
                        $digits.eq(index - 1).focus();
                    }
                    break;

                case 'ArrowRight':
                    if (index < $digits.length - 1) {
                        e.preventDefault();
                        $digits.eq(index + 1).focus();
                    }
                    break;
            }
        },

        /**
         * Handle paste event
         *
         * @param {Event} e Paste event
         */
        handlePaste(e) {
            e.preventDefault();

            const $wrapper = $(e.target).closest('.qaf-otp-wrapper');
            const $digits = $wrapper.find('.qaf-otp-digit');
            const mode = $wrapper.data('mode') || 'numeric';

            let pasteData = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
            pasteData = this.sanitizeInput(pasteData, mode);

            // Distribute across inputs
            $digits.each((i, input) => {
                if (pasteData[i]) {
                    $(input).val(pasteData[i]);
                }
            });

            // Focus last filled or first empty
            const lastFilled = Math.min(pasteData.length, $digits.length) - 1;
            if (lastFilled >= 0 && lastFilled < $digits.length - 1) {
                $digits.eq(lastFilled + 1).focus();
            } else {
                $digits.eq(lastFilled).focus();
            }

            this.updateCombinedValue($wrapper);
        },

        /**
         * Handle focus
         *
         * @param {Event} e Focus event
         */
        handleFocus(e) {
            // Select value on focus
            e.target.select();
        },

        /**
         * Sanitize input based on mode
         *
         * @param {string} value Input value
         * @param {string} mode numeric|alpha|alphanumeric
         * @return {string} Sanitized value
         */
        sanitizeInput(value, mode) {
            switch (mode) {
                case 'numeric':
                    return value.replace(/[^0-9]/g, '');
                case 'alpha':
                    return value.replace(/[^a-zA-Z]/g, '').toUpperCase();
                case 'alphanumeric':
                    return value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                default:
                    return value;
            }
        },

        /**
         * Get combined value from all digits
         *
         * @param {jQuery} $digits Digit inputs
         * @return {string} Combined value
         */
        getCombinedValue($digits) {
            let combined = '';
            $digits.each((i, input) => {
                combined += $(input).val();
            });
            return combined;
        },

        /**
         * Update the hidden combined value field
         *
         * @param {jQuery} $wrapper OTP wrapper element
         */
        updateCombinedValue($wrapper) {
            const $digits = $wrapper.find('.qaf-otp-digit');
            const $hidden = $wrapper.find('.qaf-otp-value');
            $hidden.val(this.getCombinedValue($digits));
        },

        /**
         * Trigger completion callback
         *
         * @param {jQuery} $wrapper OTP wrapper element
         * @param {string} value Complete OTP value
         */
        triggerComplete($wrapper, value) {
            $wrapper.trigger('qaf:otp:complete', [value]);

            // Announce to screen readers
            const $status = $wrapper.find('.qaf-otp-status');
            $status.text(qafSpecialtyFieldsL10n.otpComplete || 'Code entered. Submitting...');
        }
    };

    /**
     * Slider Field Handler
     */
    const SliderField = {
        /**
         * Initialize slider fields
         */
        init() {
            this.bindEvents();
            this.initializeExisting();
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('input', '.qaf-slider-input', this.handleSliderInput.bind(this));
            $(document).on('input', '.qaf-slider-number-input', this.handleNumberInput.bind(this));
        },

        /**
         * Initialize existing sliders
         */
        initializeExisting() {
            $('.qaf-slider-input').each((i, slider) => {
                this.updateDisplay($(slider));
            });
        },

        /**
         * Handle slider input
         *
         * @param {Event} e Input event
         */
        handleSliderInput(e) {
            const $slider = $(e.target);
            this.updateDisplay($slider);
            this.syncNumberInput($slider);
        },

        /**
         * Handle number input (paired with slider)
         *
         * @param {Event} e Input event
         */
        handleNumberInput(e) {
            const $number = $(e.target);
            const sliderId = $number.data('linked-slider');
            const $slider = $('#' + sliderId);

            if ($slider.length) {
                let value = parseFloat($number.val());
                const min = parseFloat($slider.attr('min'));
                const max = parseFloat($slider.attr('max'));

                // Clamp value
                if (!isNaN(value)) {
                    value = Math.max(min, Math.min(max, value));
                    $slider.val(value);
                    this.updateDisplay($slider);
                }
            }
        },

        /**
         * Update the value display
         *
         * @param {jQuery} $slider Slider input
         */
        updateDisplay($slider) {
            const value = $slider.val();
            const prefix = $slider.data('prefix') || '';
            const suffix = $slider.data('suffix') || '';
            const $output = $('#' + $slider.attr('id') + '-output');

            // Update output element
            if ($output.length) {
                $output.text(prefix + this.formatNumber(value, $slider) + suffix);
            }

            // Update aria-valuetext
            $slider.attr('aria-valuetext', prefix + this.formatNumber(value, $slider) + suffix);
            $slider.attr('aria-valuenow', value);

            // Update track fill (CSS custom property)
            const min = parseFloat($slider.attr('min')) || 0;
            const max = parseFloat($slider.attr('max')) || 100;
            const percent = ((parseFloat(value) - min) / (max - min)) * 100;
            $slider.css('--value-percent', percent + '%');
        },

        /**
         * Sync paired number input
         *
         * @param {jQuery} $slider Slider input
         */
        syncNumberInput($slider) {
            const $number = $('[data-linked-slider="' + $slider.attr('id') + '"]');
            if ($number.length) {
                $number.val($slider.val());
            }
        },

        /**
         * Format number based on step
         *
         * @param {number} value Value to format
         * @param {jQuery} $slider Slider element
         * @return {string} Formatted number
         */
        formatNumber(value, $slider) {
            const step = parseFloat($slider.attr('step')) || 1;
            let decimals = 0;

            if (step < 1) {
                const stepStr = step.toString();
                const decimalPart = stepStr.split('.')[1];
                if (decimalPart) {
                    decimals = decimalPart.length;
                }
            }

            return parseFloat(value).toFixed(decimals);
        }
    };

    /**
     * Typed Signature Field Handler
     */
    const TypedSignatureField = {
        /**
         * Initialize typed signature fields
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('input', '.qaf-signature-input', this.handleInput.bind(this));
            $(document).on('change', '.qaf-signature-confirm-checkbox', this.handleConfirmation.bind(this));
        },

        /**
         * Handle input on signature field
         *
         * @param {Event} e Input event
         */
        handleInput(e) {
            const $input = $(e.target);
            const $wrapper = $input.closest('.qaf-typed-signature');
            const $preview = $wrapper.find('.qaf-signature-text');
            const value = $input.val().trim();

            // Update preview
            if ($preview.length) {
                if (value) {
                    $preview.text(value).removeClass('qaf-signature-empty');
                } else {
                    $preview.text($preview.data('empty') || '').addClass('qaf-signature-empty');
                }
            }
        },

        /**
         * Handle confirmation checkbox
         *
         * @param {Event} e Change event
         */
        handleConfirmation(e) {
            const $checkbox = $(e.target);
            const $wrapper = $checkbox.closest('.qaf-typed-signature');
            const isChecked = $checkbox.is(':checked');

            $wrapper.toggleClass('qaf-signature-confirmed', isChecked);

            // Announce to screen readers
            if (isChecked) {
                this.announce(qafSpecialtyFieldsL10n.signatureConfirmed || 'Signature confirmed');
            }
        },

        /**
         * Announce message to screen readers
         *
         * @param {string} message Message to announce
         */
        announce(message) {
            const $announcer = $('#qaf-sr-announcer');
            if ($announcer.length) {
                $announcer.text(message);
            } else {
                $('body').append(
                    '<div id="qaf-sr-announcer" role="status" aria-live="polite" class="screen-reader-text">' +
                    message + '</div>'
                );
            }
        }
    };

    /**
     * Searchable Select Enhancement
     */
    const SearchableSelect = {
        /**
         * Initialize searchable selects
         */
        init() {
            this.enhanceSelects();
            this.observeDOM();
        },

        /**
         * Enhance select elements with searchable attribute
         */
        enhanceSelects() {
            $('select.qaf-select-searchable:not(.qaf-combobox-initialized)').each((i, select) => {
                this.createCombobox($(select));
            });
        },

        /**
         * Observe DOM for dynamically added selects
         */
        observeDOM() {
            if (typeof MutationObserver === 'undefined') return;

            const observer = new MutationObserver((mutations) => {
                let shouldEnhance = false;
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1) {
                                if ($(node).is('select.qaf-select-searchable') ||
                                    $(node).find('select.qaf-select-searchable').length) {
                                    shouldEnhance = true;
                                }
                            }
                        });
                    }
                });
                if (shouldEnhance) {
                    this.enhanceSelects();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Create combobox from select element
         *
         * @param {jQuery} $select Select element
         */
        createCombobox($select) {
            // Check if qaf-searchable-select.js is loaded
            if (typeof QAF_SearchableSelect !== 'undefined') {
                new QAF_SearchableSelect($select[0]);
                $select.addClass('qaf-combobox-initialized');
            }
        }
    };

    /**
     * Initialize all specialty field handlers
     */
    function init() {
        CurrencyField.init();
        OTPField.init();
        SliderField.init();
        TypedSignatureField.init();
        SearchableSelect.init();
    }

    // Initialize on DOM ready
    $(document).ready(init);

    // Expose for external use
    window.QAF_SpecialtyFields = {
        CurrencyField,
        OTPField,
        SliderField,
        TypedSignatureField,
        SearchableSelect
    };

})(jQuery);

// Localization object (populated by wp_localize_script)
var qafSpecialtyFieldsL10n = qafSpecialtyFieldsL10n || {
    otpComplete: 'Code entered',
    signatureConfirmed: 'Signature confirmed'
};
