/**
 * BITS Accessible Forms - Field Validation
 *
 * Comprehensive validation library with accessible error reporting.
 *
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * BAF Validation Library
     */
    const BAFValidation = {
        /**
         * Validation rules registry
         */
        rules: {},

        /**
         * Initialize built-in rules
         */
        init: function() {
            // Register built-in validation rules
            this.registerRule('required', this.validateRequired);
            this.registerRule('email', this.validateEmail);
            this.registerRule('url', this.validateUrl);
            this.registerRule('phone', this.validatePhone);
            this.registerRule('number', this.validateNumber);
            this.registerRule('integer', this.validateInteger);
            this.registerRule('min', this.validateMin);
            this.registerRule('max', this.validateMax);
            this.registerRule('minLength', this.validateMinLength);
            this.registerRule('maxLength', this.validateMaxLength);
            this.registerRule('pattern', this.validatePattern);
            this.registerRule('date', this.validateDate);
            this.registerRule('dateRange', this.validateDateRange);
            this.registerRule('fileSize', this.validateFileSize);
            this.registerRule('fileType', this.validateFileType);
            this.registerRule('creditCard', this.validateCreditCard);
            this.registerRule('alphanumeric', this.validateAlphanumeric);
            this.registerRule('noHtml', this.validateNoHtml);
            this.registerRule('equals', this.validateEquals);
            this.registerRule('custom', this.validateCustom);
        },

        /**
         * Register a validation rule
         * @param {string} name 
         * @param {Function} validator 
         */
        registerRule: function(name, validator) {
            this.rules[name] = validator.bind(this);
        },

        /**
         * Validate a field
         * @param {HTMLElement} field 
         * @param {Object} rules 
         * @returns {Object} { valid: boolean, errors: string[] }
         */
        validate: function(field, rules = {}) {
            const errors = [];
            const value = this.getFieldValue(field);

            // Get rules from field attributes if not provided
            if (Object.keys(rules).length === 0) {
                rules = this.getRulesFromField(field);
            }

            // Run each rule
            for (const [ruleName, ruleConfig] of Object.entries(rules)) {
                if (!this.rules[ruleName]) {
                    console.warn('BAF: Unknown validation rule:', ruleName);
                    continue;
                }

                const result = this.rules[ruleName](value, ruleConfig, field);
                if (result !== true) {
                    errors.push(result);
                }
            }

            return {
                valid: errors.length === 0,
                errors: errors
            };
        },

        /**
         * Get field value
         * @param {HTMLElement} field 
         * @returns {*}
         */
        getFieldValue: function(field) {
            if (!field) return null;

            // Handle different input types
            if (field.type === 'checkbox') {
                // Single checkbox
                if (!field.name.includes('[]')) {
                    return field.checked;
                }
                // Checkbox group
                const form = field.closest('form');
                const checkboxes = form.querySelectorAll(`input[name="${field.name}"]:checked`);
                return Array.from(checkboxes).map(cb => cb.value);
            }

            if (field.type === 'radio') {
                const form = field.closest('form');
                const selected = form.querySelector(`input[name="${field.name}"]:checked`);
                return selected ? selected.value : '';
            }

            if (field.type === 'file') {
                return field.files;
            }

            if (field.multiple) {
                return Array.from(field.selectedOptions || []).map(opt => opt.value);
            }

            return field.value;
        },

        /**
         * Get validation rules from field attributes
         * @param {HTMLElement} field 
         * @returns {Object}
         */
        getRulesFromField: function(field) {
            const rules = {};

            // Required
            if (field.required || field.hasAttribute('data-required')) {
                rules.required = true;
            }

            // Type-based rules
            if (field.type === 'email') {
                rules.email = true;
            } else if (field.type === 'url') {
                rules.url = true;
            } else if (field.type === 'tel') {
                rules.phone = true;
            } else if (field.type === 'number') {
                rules.number = true;
            }

            // Min/Max length
            if (field.minLength > 0) {
                rules.minLength = field.minLength;
            }
            if (field.maxLength > 0) {
                rules.maxLength = field.maxLength;
            }

            // Min/Max value
            if (field.min) {
                rules.min = parseFloat(field.min);
            }
            if (field.max) {
                rules.max = parseFloat(field.max);
            }

            // Pattern
            if (field.pattern) {
                rules.pattern = {
                    pattern: field.pattern,
                    message: field.title || null
                };
            }

            // Data attributes
            if (field.dataset.validate) {
                try {
                    const dataRules = JSON.parse(field.dataset.validate);
                    Object.assign(rules, dataRules);
                } catch (e) {
                    console.error('BAF: Invalid validation data', e);
                }
            }

            return rules;
        },

        /**
         * Required validation
         */
        validateRequired: function(value, config, field) {
            let isEmpty = false;

            if (value === null || value === undefined) {
                isEmpty = true;
            } else if (typeof value === 'string') {
                isEmpty = value.trim() === '';
            } else if (Array.isArray(value)) {
                isEmpty = value.length === 0;
            } else if (typeof value === 'boolean') {
                isEmpty = !value;
            } else if (value instanceof FileList) {
                isEmpty = value.length === 0;
            }

            return isEmpty ? (config.message || bafValidation.i18n.required) : true;
        },

        /**
         * Email validation
         */
        validateEmail: function(value, config) {
            if (!value) return true;

            // RFC 5322 compliant email regex
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            
            return emailRegex.test(value) ? true : (config.message || bafValidation.i18n.invalidEmail);
        },

        /**
         * URL validation
         */
        validateUrl: function(value, config) {
            if (!value) return true;

            try {
                new URL(value);
                return true;
            } catch {
                return config.message || bafValidation.i18n.invalidUrl;
            }
        },

        /**
         * Phone validation
         */
        validatePhone: function(value, config) {
            if (!value) return true;

            // Basic phone regex - allows various formats
            const phoneRegex = /^[\d\s\-\+\(\)\.]{7,20}$/;
            
            return phoneRegex.test(value) ? true : (config.message || bafValidation.i18n.invalidPhone);
        },

        /**
         * Number validation
         */
        validateNumber: function(value, config) {
            if (!value) return true;

            return !isNaN(parseFloat(value)) ? true : (config.message || bafValidation.i18n.invalidNumber);
        },

        /**
         * Integer validation
         */
        validateInteger: function(value, config) {
            if (!value) return true;

            return Number.isInteger(Number(value)) ? true : (config.message || bafValidation.i18n.invalidInteger);
        },

        /**
         * Minimum value validation
         */
        validateMin: function(value, config) {
            if (!value) return true;

            const numValue = parseFloat(value);
            const minValue = typeof config === 'object' ? config.value : config;

            if (isNaN(numValue)) return true;

            return numValue >= minValue ? true : (config.message || bafValidation.i18n.minValue.replace('%d', minValue));
        },

        /**
         * Maximum value validation
         */
        validateMax: function(value, config) {
            if (!value) return true;

            const numValue = parseFloat(value);
            const maxValue = typeof config === 'object' ? config.value : config;

            if (isNaN(numValue)) return true;

            return numValue <= maxValue ? true : (config.message || bafValidation.i18n.maxValue.replace('%d', maxValue));
        },

        /**
         * Minimum length validation
         */
        validateMinLength: function(value, config) {
            if (!value) return true;

            const minLength = typeof config === 'object' ? config.value : config;
            const length = String(value).length;

            return length >= minLength ? true : (config.message || bafValidation.i18n.minLength.replace('%d', minLength));
        },

        /**
         * Maximum length validation
         */
        validateMaxLength: function(value, config) {
            if (!value) return true;

            const maxLength = typeof config === 'object' ? config.value : config;
            const length = String(value).length;

            return length <= maxLength ? true : (config.message || bafValidation.i18n.maxLength.replace('%d', maxLength));
        },

        /**
         * Pattern/Regex validation
         */
        validatePattern: function(value, config) {
            if (!value) return true;

            const pattern = typeof config === 'object' ? config.pattern : config;
            const regex = new RegExp(pattern);

            return regex.test(value) ? true : (config.message || bafValidation.i18n.invalidPattern);
        },

        /**
         * Date validation
         */
        validateDate: function(value, config) {
            if (!value) return true;

            const date = new Date(value);
            
            if (isNaN(date.getTime())) {
                return config.message || bafValidation.i18n.invalidDate;
            }

            // Check min date
            if (config.min) {
                const minDate = config.min === 'today' ? new Date() : new Date(config.min);
                minDate.setHours(0, 0, 0, 0);
                if (date < minDate) {
                    return config.minMessage || bafValidation.i18n.dateMin;
                }
            }

            // Check max date
            if (config.max) {
                const maxDate = config.max === 'today' ? new Date() : new Date(config.max);
                maxDate.setHours(23, 59, 59, 999);
                if (date > maxDate) {
                    return config.maxMessage || bafValidation.i18n.dateMax;
                }
            }

            return true;
        },

        /**
         * Date range validation
         */
        validateDateRange: function(value, config, field) {
            if (!value) return true;

            const form = field.closest('form');
            const otherFieldName = config.compareTo;
            const otherField = form?.querySelector(`[name="${otherFieldName}"]`);

            if (!otherField?.value) return true;

            const thisDate = new Date(value);
            const otherDate = new Date(otherField.value);

            if (config.operator === 'after' && thisDate <= otherDate) {
                return config.message || bafValidation.i18n.dateAfter;
            }

            if (config.operator === 'before' && thisDate >= otherDate) {
                return config.message || bafValidation.i18n.dateBefore;
            }

            return true;
        },

        /**
         * File size validation
         */
        validateFileSize: function(value, config) {
            if (!value || value.length === 0) return true;

            const maxSize = typeof config === 'object' ? config.max : config;
            const maxBytes = this.parseFileSize(maxSize);

            for (const file of value) {
                if (file.size > maxBytes) {
                    return config.message || bafValidation.i18n.fileTooLarge.replace('%s', this.formatFileSize(maxBytes));
                }
            }

            return true;
        },

        /**
         * File type validation
         */
        validateFileType: function(value, config) {
            if (!value || value.length === 0) return true;

            const allowedTypes = typeof config === 'object' ? config.types : config;
            const allowed = Array.isArray(allowedTypes) ? allowedTypes : allowedTypes.split(',').map(t => t.trim());

            for (const file of value) {
                const extension = '.' + file.name.split('.').pop().toLowerCase();
                const mimeType = file.type.toLowerCase();

                const isAllowed = allowed.some(type => {
                    type = type.toLowerCase();
                    // Check extension
                    if (type.startsWith('.')) {
                        return type === extension;
                    }
                    // Check MIME type
                    if (type.includes('/')) {
                        if (type.endsWith('/*')) {
                            return mimeType.startsWith(type.slice(0, -1));
                        }
                        return mimeType === type;
                    }
                    // Check extension without dot
                    return type === extension.substring(1);
                });

                if (!isAllowed) {
                    return config.message || bafValidation.i18n.invalidFileType.replace('%s', allowed.join(', '));
                }
            }

            return true;
        },

        /**
         * Credit card validation (Luhn algorithm)
         */
        validateCreditCard: function(value, config) {
            if (!value) return true;

            // Remove spaces and dashes
            const cardNumber = value.replace(/[\s-]/g, '');

            // Check if all digits
            if (!/^\d+$/.test(cardNumber)) {
                return config.message || bafValidation.i18n.invalidCard;
            }

            // Luhn algorithm
            let sum = 0;
            let isEven = false;

            for (let i = cardNumber.length - 1; i >= 0; i--) {
                let digit = parseInt(cardNumber[i], 10);

                if (isEven) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }

                sum += digit;
                isEven = !isEven;
            }

            return (sum % 10 === 0) ? true : (config.message || bafValidation.i18n.invalidCard);
        },

        /**
         * Alphanumeric validation
         */
        validateAlphanumeric: function(value, config) {
            if (!value) return true;

            const allowSpaces = config?.allowSpaces ?? false;
            const pattern = allowSpaces ? /^[a-zA-Z0-9\s]+$/ : /^[a-zA-Z0-9]+$/;

            return pattern.test(value) ? true : (config.message || bafValidation.i18n.alphanumericOnly);
        },

        /**
         * No HTML validation
         */
        validateNoHtml: function(value, config) {
            if (!value) return true;

            const htmlRegex = /<[^>]*>/;
            
            return !htmlRegex.test(value) ? true : (config.message || bafValidation.i18n.noHtmlAllowed);
        },

        /**
         * Equals validation (compare to another field)
         */
        validateEquals: function(value, config, field) {
            if (!value) return true;

            const form = field.closest('form');
            const otherFieldName = typeof config === 'object' ? config.field : config;
            const otherField = form?.querySelector(`[name="${otherFieldName}"]`);

            if (!otherField) return true;

            return value === otherField.value ? true : (config.message || bafValidation.i18n.fieldsNotMatch);
        },

        /**
         * Custom validation (run callback function)
         */
        validateCustom: function(value, config, field) {
            if (typeof config.validator === 'function') {
                const result = config.validator(value, field);
                if (result === true) return true;
                return result || config.message || bafValidation.i18n.invalidValue;
            }
            return true;
        },

        /**
         * Parse file size string to bytes
         * @param {string|number} size 
         * @returns {number}
         */
        parseFileSize: function(size) {
            if (typeof size === 'number') return size;

            const units = {
                'b': 1,
                'kb': 1024,
                'mb': 1024 * 1024,
                'gb': 1024 * 1024 * 1024
            };

            const match = String(size).toLowerCase().match(/^(\d+(?:\.\d+)?)\s*(b|kb|mb|gb)?$/);
            if (!match) return parseInt(size, 10) || 0;

            const value = parseFloat(match[1]);
            const unit = match[2] || 'b';

            return Math.round(value * (units[unit] || 1));
        },

        /**
         * Format file size for display
         * @param {number} bytes 
         * @returns {string}
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // Initialize
    BAFValidation.init();

    // Expose globally
    window.BAFValidation = BAFValidation;

})(jQuery);

/**
 * Default localization strings
 */
window.bafValidation = window.bafValidation || {
    i18n: {
        required: 'This field is required.',
        invalidEmail: 'Please enter a valid email address.',
        invalidUrl: 'Please enter a valid URL.',
        invalidPhone: 'Please enter a valid phone number.',
        invalidNumber: 'Please enter a valid number.',
        invalidInteger: 'Please enter a whole number.',
        minValue: 'Value must be at least %d.',
        maxValue: 'Value must be no more than %d.',
        minLength: 'Please enter at least %d characters.',
        maxLength: 'Please enter no more than %d characters.',
        invalidPattern: 'Please enter a valid format.',
        invalidDate: 'Please enter a valid date.',
        dateMin: 'Date is too early.',
        dateMax: 'Date is too late.',
        dateAfter: 'Date must be after the other date.',
        dateBefore: 'Date must be before the other date.',
        fileTooLarge: 'File is too large. Maximum size is %s.',
        invalidFileType: 'Invalid file type. Allowed types: %s',
        invalidCard: 'Please enter a valid credit card number.',
        alphanumericOnly: 'Only letters and numbers are allowed.',
        noHtmlAllowed: 'HTML is not allowed.',
        fieldsNotMatch: 'Fields do not match.',
        invalidValue: 'Please enter a valid value.'
    }
};
