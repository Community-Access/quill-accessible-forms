/**
 * BITS Accessible Forms - Public JavaScript
 *
 * Handles form submission, validation, multi-page navigation, and accessibility features.
 *
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * BAF Forms Handler
     */
    const BAFForms = {
        /**
         * Initialize
         */
        init: function() {
            this.forms = document.querySelectorAll('.baf-form');
            
            this.forms.forEach(form => {
                this.initForm(form);
            });

            // Initialize conditional logic
            this.initConditionalLogic();
        },

        /**
         * Initialize a single form
         * @param {HTMLFormElement} form 
         */
        initForm: function(form) {
            const formId = form.dataset.formId;
            const instanceId = form.id;

            // Setup announcer
            this.setupAnnouncer(instanceId);

            // Bind events
            this.bindFormEvents(form);
            this.bindValidation(form);
            this.bindMultiPage(form);
            this.bindFileUploads(form);
            this.bindRatingFields(form);
            this.initCollapsibleSections(form);
            this.initCascadingDropdowns(form);

            // Initialize input masks
            this.initInputMasks(form);

            // Initialize Google Places (optional)
            this.initGooglePlaces(form);

            // Initialize character counters
            this.initCharacterCounters(form);

            // Mark form as loaded (for honeypot timing)
            const loadedField = form.querySelector('input[name="_baf_form_loaded"]');
            if (loadedField) {
                loadedField.value = Math.floor(Date.now() / 1000);
            }
        },

        /**
         * Setup announcer for screen reader notifications
         * @param {string} instanceId 
         */
        setupAnnouncer: function(instanceId) {
            const announcer = document.getElementById(instanceId + '-announcer');
            if (announcer) {
                this.announcers = this.announcers || {};
                this.announcers[instanceId] = announcer;
            }
        },

        /**
         * Announce message to screen readers
         * @param {string} instanceId 
         * @param {string} message 
         * @param {string} priority - 'polite' or 'assertive'
         */
        announce: function(instanceId, message, priority = 'polite') {
            const announcer = this.announcers?.[instanceId];
            if (announcer) {
                announcer.setAttribute('aria-live', priority);
                announcer.textContent = '';
                // Force reflow for announcement
                void announcer.offsetWidth;
                announcer.textContent = message;
            }
        },

        /**
         * Bind form submission events
         * @param {HTMLFormElement} form 
         */
        bindFormEvents: function(form) {
            if (form.classList.contains('baf-ajax-form')) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleSubmit(form);
                });
            }
        },

        /**
         * Handle form submission
         * @param {HTMLFormElement} form 
         */
        handleSubmit: async function(form) {
            const instanceId = form.id;
            const formId = form.dataset.formId;

            // Validate all fields
            if (!this.validateForm(form)) {
                this.announce(instanceId, bafPublic.i18n.validationErrors, 'assertive');
                this.focusFirstError(form);
                return;
            }

            // Show loading state
            form.classList.add('baf-form-submitting');
            const submitBtn = form.querySelector('.baf-submit-button');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            this.announce(instanceId, bafPublic.i18n.submitting, 'polite');

            try {
                const formData = new FormData(form);
                
                // Convert FormData to object for JSON
                const data = {
                    fields: {}
                };

                formData.forEach((value, key) => {
                    // Canonical: fields[field_id]
                    if (key.startsWith('fields[')) {
                        const fieldId = key.match(/fields\[([^\]]+)\]/)?.[1];
                        if (fieldId) {
                            if (data.fields[fieldId]) {
                                if (!Array.isArray(data.fields[fieldId])) {
                                    data.fields[fieldId] = [data.fields[fieldId]];
                                }
                                data.fields[fieldId].push(value);
                            } else {
                                data.fields[fieldId] = value;
                            }
                        }
                        return;
                    }

                    // Field classes currently render: baf_fields[field_id]
                    if (key.startsWith('baf_fields[')) {
                        const fieldId = key.match(/baf_fields\[([^\]]+)\]/)?.[1];
                        if (fieldId) {
                            if (data.fields[fieldId]) {
                                if (!Array.isArray(data.fields[fieldId])) {
                                    data.fields[fieldId] = [data.fields[fieldId]];
                                }
                                data.fields[fieldId].push(value);
                            } else {
                                data.fields[fieldId] = value;
                            }
                        }
                        return;
                    }

                    // Composite field classes render: baf_field_<id>[sub]
                    if (key.startsWith('baf_field_')) {
                        const match = key.match(/^baf_field_([^\[]+)(?:\[(.+)\])?$/);
                        const fieldId = match?.[1];
                        const subKey = match?.[2];
                        if (fieldId) {
                            if (subKey) {
                                if (!data.fields[fieldId] || typeof data.fields[fieldId] !== 'object' || Array.isArray(data.fields[fieldId])) {
                                    data.fields[fieldId] = {};
                                }
                                data.fields[fieldId][subKey] = value;
                            } else {
                                data.fields[fieldId] = value;
                            }
                        }
                        return;
                    }

                    if (!key.startsWith('_')) {
                        data[key] = value;
                    }
                });

                // Get nonce
                const nonce = form.querySelector('input[name="_baf_token"]')?.value;

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({
                        ...data,
                        form_id: formId
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    this.handleSuccess(form, result);
                } else {
                    this.handleError(form, result);
                }
            } catch (error) {
                console.error('BAF submission error:', error);
                this.handleError(form, {
                    message: bafPublic.i18n.networkError
                });
            } finally {
                form.classList.remove('baf-form-submitting');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        },

        /**
         * Handle successful submission
         * @param {HTMLFormElement} form 
         * @param {Object} result 
         */
        handleSuccess: function(form, result) {
            const instanceId = form.id;
            const wrapper = form.closest('.baf-form-wrapper');
            const messagesDiv = wrapper?.querySelector('.baf-messages');

            // Clear form
            form.reset();

            // Show success message
            const message = result.message || bafPublic.i18n.successMessage;
            
            if (messagesDiv) {
                messagesDiv.innerHTML = `
                    <div class="baf-message baf-message-success" role="status">
                        ${this.escapeHtml(message)}
                    </div>
                `;
                messagesDiv.focus();
            }

            this.announce(instanceId, message, 'assertive');

            // Redirect if specified
            if (result.redirect) {
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 500);
            }

            // Trigger custom event
            form.dispatchEvent(new CustomEvent('baf:submitted', {
                detail: result
            }));
        },

        /**
         * Handle submission error
         * @param {HTMLFormElement} form 
         * @param {Object} result 
         */
        handleError: function(form, result) {
            const instanceId = form.id;
            const wrapper = form.closest('.baf-form-wrapper');
            const messagesDiv = wrapper?.querySelector('.baf-messages');

            const message = result.message || bafPublic.i18n.errorMessage;

            if (messagesDiv) {
                messagesDiv.innerHTML = `
                    <div class="baf-message baf-message-error" role="alert">
                        ${this.escapeHtml(message)}
                    </div>
                `;
                messagesDiv.focus();
            }

            this.announce(instanceId, message, 'assertive');

            // Handle field-specific errors
            if (result.errors) {
                Object.entries(result.errors).forEach(([fieldId, error]) => {
                    this.showFieldError(form, fieldId, error);
                });
                this.focusFirstError(form);
            }
        },

        /**
         * Bind validation events
         * @param {HTMLFormElement} form 
         */
        bindValidation: function(form) {
            const fields = form.querySelectorAll('input, select, textarea');

            const debounce = (fn, wait) => {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...args), wait);
                };
            };

            fields.forEach(field => {
                const wrapper = field.closest('.baf-field');
                const validateOn = wrapper?.dataset?.validateOn || 'blur';
                const debounceMs = parseInt(wrapper?.dataset?.debounce || '0', 10) || 0;

                const runValidate = () => this.validateField(field);
                const debouncedValidate = debounceMs > 0 ? debounce(runValidate, debounceMs) : runValidate;

                if (validateOn === 'input') {
                    field.addEventListener('input', () => debouncedValidate());
                } else if (validateOn === 'change') {
                    field.addEventListener('change', () => debouncedValidate());
                } else {
                    field.addEventListener('blur', () => debouncedValidate());
                }

                // Clear error on input
                field.addEventListener('input', () => {
                    this.clearFieldError(field);
                });
            });
        },

        /**
         * Validate entire form
         * @param {HTMLFormElement} form 
         * @returns {boolean}
         */
        validateForm: function(form) {
            let isValid = true;
            const fields = form.querySelectorAll('input, select, textarea');

            fields.forEach(field => {
                // Skip hidden fields and honeypot
                if (field.type === 'hidden' || field.closest('.baf-hp')) {
                    return;
                }

                // Skip fields in hidden pages
                const page = field.closest('.baf-page');
                if (page && page.classList.contains('baf-page-hidden')) {
                    return;
                }

                if (!this.validateField(field)) {
                    isValid = false;
                }
            });

            return isValid;
        },

        /**
         * Validate a single field
         * @param {HTMLElement} field 
         * @returns {boolean}
         */
        validateField: function(field) {
            const wrapper = field.closest('.baf-field');
            if (!wrapper) return true;

            const fieldId = wrapper.dataset.fieldId;
            const form = field.closest('form');
            let error = '';

            // Required validation
            if (field.required || wrapper.classList.contains('baf-field-required')) {
                if (field.type === 'checkbox') {
                    // For checkbox groups, check if at least one is checked
                    const group = wrapper.querySelectorAll('input[type="checkbox"]');
                    const checked = Array.from(group).some(cb => cb.checked);
                    if (!checked) {
                        error = bafPublic.i18n.required;
                    }
                } else if (field.type === 'radio') {
                    // For radio groups, check if one is selected
                    const group = wrapper.querySelectorAll('input[type="radio"]');
                    const selected = Array.from(group).some(rb => rb.checked);
                    if (!selected) {
                        error = bafPublic.i18n.required;
                    }
                } else if (!field.value.trim()) {
                    error = bafPublic.i18n.required;
                }
            }

            // Email validation
            if (!error && field.type === 'email' && field.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    error = bafPublic.i18n.invalidEmail;
                }
            }

            // URL validation
            if (!error && field.type === 'url' && field.value) {
                try {
                    new URL(field.value);
                } catch {
                    error = bafPublic.i18n.invalidUrl;
                }
            }

            // Min/Max length
            if (!error && field.minLength > 0 && field.value.length < field.minLength) {
                error = bafPublic.i18n.minLength.replace('%d', field.minLength);
            }

            if (!error && field.maxLength > 0 && field.value.length > field.maxLength) {
                error = bafPublic.i18n.maxLength.replace('%d', field.maxLength);
            }

            // Min/Max for numbers
            if (!error && field.type === 'number' && field.value) {
                const val = parseFloat(field.value);
                if (field.min && val < parseFloat(field.min)) {
                    error = bafPublic.i18n.minValue.replace('%d', field.min);
                }
                if (field.max && val > parseFloat(field.max)) {
                    error = bafPublic.i18n.maxValue.replace('%d', field.max);
                }
            }

            // Pattern validation
            if (!error && field.pattern && field.value) {
                const regex = new RegExp(field.pattern);
                if (!regex.test(field.value)) {
                    error = field.title || bafPublic.i18n.invalidFormat;
                }
            }

            // Show or clear error
            if (error) {
                this.showFieldError(form, fieldId, error);
                return false;
            } else {
                this.clearFieldError(field);
                return true;
            }
        },

        /**
         * Show field error
         * @param {HTMLFormElement} form 
         * @param {string} fieldId 
         * @param {string} error 
         */
        showFieldError: function(form, fieldId, error) {
            const wrapper = form.querySelector(`.baf-field[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            wrapper.classList.add('baf-field-has-error');

            const errorDiv = wrapper.querySelector('.baf-field-error') || wrapper.querySelector('.baf-field-errors');
            if (errorDiv) {
                errorDiv.textContent = error;
            }

            // Set aria-invalid
            const field = wrapper.querySelector('input, select, textarea');
            if (field) {
                field.setAttribute('aria-invalid', 'true');
                if (errorDiv?.id) {
                    field.setAttribute('aria-describedby', (field.getAttribute('aria-describedby') || '') + ' ' + errorDiv.id);
                }
            }
        },

        /**
         * Clear field error
         * @param {HTMLElement} field 
         */
        clearFieldError: function(field) {
            const wrapper = field.closest('.baf-field');
            if (!wrapper) return;

            wrapper.classList.remove('baf-field-has-error');

            const errorDiv = wrapper.querySelector('.baf-field-error') || wrapper.querySelector('.baf-field-errors');
            if (errorDiv) {
                errorDiv.textContent = '';
            }

            field.removeAttribute('aria-invalid');
        },

        /**
         * Focus first error field
         * @param {HTMLFormElement} form 
         */
        focusFirstError: function(form) {
            const firstError = form.querySelector('.baf-field-has-error input, .baf-field-has-error select, .baf-field-has-error textarea');
            if (firstError) {
                firstError.focus();
            }
        },

        /**
         * Bind multi-page navigation
         * @param {HTMLFormElement} form 
         */
        bindMultiPage: function(form) {
            const pages = form.querySelectorAll('.baf-page');
            if (pages.length <= 1) return;

            const instanceId = form.id;
            let currentPage = 1;
            const totalPages = pages.length;

            // Previous button
            form.querySelectorAll('[data-action="prev-page"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (currentPage > 1) {
                        this.goToPage(form, currentPage - 1);
                        currentPage--;
                    }
                });
            });

            // Next button
            form.querySelectorAll('[data-action="next-page"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    // Validate current page
                    if (this.validatePage(form, currentPage)) {
                        if (currentPage < totalPages) {
                            this.goToPage(form, currentPage + 1);
                            currentPage++;
                        }
                    } else {
                        this.announce(instanceId, bafPublic.i18n.validationErrors, 'assertive');
                        this.focusFirstError(form);
                    }
                });
            });
        },

        /**
         * Validate a specific page
         * @param {HTMLFormElement} form 
         * @param {number} pageNum 
         * @returns {boolean}
         */
        validatePage: function(form, pageNum) {
            const page = form.querySelector(`.baf-page[data-page="${pageNum}"]`);
            if (!page) return true;

            let isValid = true;
            const fields = page.querySelectorAll('input, select, textarea');

            fields.forEach(field => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });

            return isValid;
        },

        /**
         * Navigate to a specific page
         * @param {HTMLFormElement} form 
         * @param {number} pageNum 
         */
        goToPage: function(form, pageNum) {
            const instanceId = form.id;
            const pages = form.querySelectorAll('.baf-page');
            const totalPages = pages.length;

            // Hide all pages
            pages.forEach(page => {
                page.classList.add('baf-page-hidden');
                page.setAttribute('aria-hidden', 'true');
            });

            // Show target page
            const targetPage = form.querySelector(`.baf-page[data-page="${pageNum}"]`);
            if (targetPage) {
                targetPage.classList.remove('baf-page-hidden');
                targetPage.removeAttribute('aria-hidden');
                
                // Focus first field or page title
                const firstInput = targetPage.querySelector('input, select, textarea');
                const pageTitle = targetPage.querySelector('.baf-page-title');
                
                if (pageTitle) {
                    pageTitle.setAttribute('tabindex', '-1');
                    pageTitle.focus();
                } else if (firstInput) {
                    firstInput.focus();
                }
            }

            // Update progress indicator
            this.updateProgress(form, pageNum, totalPages);

            // Show/hide submit button
            const submitWrapper = form.querySelector('.baf-submit-wrapper');
            if (submitWrapper) {
                const showOnPage = parseInt(submitWrapper.dataset.showOnPage || totalPages);
                submitWrapper.classList.toggle('baf-submit-hidden', pageNum < showOnPage);
            }

            // Announce page change
            this.announce(instanceId, 
                bafPublic.i18n.pageChanged.replace('%1$d', pageNum).replace('%2$d', totalPages), 
                'polite'
            );
        },

        /**
         * Update progress indicator
         * @param {HTMLFormElement} form 
         * @param {number} currentPage 
         * @param {number} totalPages 
         */
        updateProgress: function(form, currentPage, totalPages) {
            const progress = form.closest('.baf-form-wrapper')?.querySelector('.baf-progress');
            if (!progress) return;

            // Update progress bar
            const progressBar = progress.querySelector('.baf-progress-fill');
            if (progressBar) {
                progressBar.style.width = ((currentPage / totalPages) * 100) + '%';
            }

            // Update aria values
            progress.setAttribute('aria-valuenow', currentPage);

            // Update step indicators
            const steps = progress.querySelectorAll('.baf-progress-step');
            steps.forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('baf-step-current', 'baf-step-completed');
                
                if (stepNum === currentPage) {
                    step.classList.add('baf-step-current');
                } else if (stepNum < currentPage) {
                    step.classList.add('baf-step-completed');
                }
            });

            // Update screen reader status
            const srStatus = progress.querySelector('.baf-progress-status');
            if (srStatus) {
                srStatus.textContent = bafPublic.i18n.pageStatus
                    .replace('%1$d', currentPage)
                    .replace('%2$d', totalPages);
            }
        },

        /**
         * Bind file upload functionality
         * @param {HTMLFormElement} form 
         */
        bindFileUploads: function(form) {
            const fileInputs = form.querySelectorAll('.baf-file-input');

            fileInputs.forEach(input => {
                const wrapper = input.closest('.baf-file-upload');
                const button = wrapper?.querySelector('.baf-file-button');
                const list = wrapper?.querySelector('.baf-file-list');

                if (button) {
                    button.addEventListener('click', () => input.click());
                    button.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            input.click();
                        }
                    });
                }

                input.addEventListener('change', () => {
                    if (list) {
                        this.updateFileList(input, list);
                    }
                });
            });
        },

        /**
         * Update file list display
         * @param {HTMLInputElement} input 
         * @param {HTMLElement} list 
         */
        updateFileList: function(input, list) {
            list.innerHTML = '';

            Array.from(input.files || []).forEach((file, index) => {
                const item = document.createElement('li');
                item.className = 'baf-file-item';
                item.innerHTML = `
                    <span class="baf-file-name">${this.escapeHtml(file.name)}</span>
                    <span class="baf-file-size">(${this.formatFileSize(file.size)})</span>
                    <button type="button" class="baf-file-remove" data-index="${index}" 
                            aria-label="${bafPublic.i18n.removeFile.replace('%s', file.name)}">
                        &times;
                    </button>
                `;
                list.appendChild(item);
            });

            // Bind remove buttons
            list.querySelectorAll('.baf-file-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    // Note: Can't modify FileList, would need to re-implement with DataTransfer
                    const wrapper = list.closest('.baf-file-upload');
                    if (wrapper) {
                        const fileInput = wrapper.querySelector('.baf-file-input');
                        if (fileInput) {
                            fileInput.value = '';
                            list.innerHTML = '';
                        }
                    }
                });
            });
        },

        /**
         * Format file size
         * @param {number} bytes 
         * @returns {string}
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Bind rating fields
         * @param {HTMLFormElement} form 
         */
        bindRatingFields: function(form) {
            const ratingFields = form.querySelectorAll('.baf-rating');

            ratingFields.forEach(rating => {
                const stars = rating.querySelectorAll('.baf-rating-star');
                const input = rating.querySelector('input[type="hidden"]');

                stars.forEach((star, index) => {
                    // Click handler
                    star.addEventListener('click', () => {
                        this.setRating(rating, index + 1);
                    });

                    // Keyboard handler
                    star.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            this.setRating(rating, index + 1);
                        } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            const next = stars[index + 1];
                            if (next) next.focus();
                        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            const prev = stars[index - 1];
                            if (prev) prev.focus();
                        }
                    });

                    // Hover effects
                    star.addEventListener('mouseenter', () => {
                        stars.forEach((s, i) => {
                            s.classList.toggle('baf-rating-hover', i <= index);
                        });
                    });

                    star.addEventListener('mouseleave', () => {
                        stars.forEach(s => s.classList.remove('baf-rating-hover'));
                    });
                });
            });
        },

        /**
         * Set rating value
         * @param {HTMLElement} rating 
         * @param {number} value 
         */
        setRating: function(rating, value) {
            const stars = rating.querySelectorAll('.baf-rating-star');
            const input = rating.querySelector('input[type="hidden"]');

            stars.forEach((star, index) => {
                star.classList.toggle('baf-rating-selected', index < value);
                star.setAttribute('aria-pressed', index < value ? 'true' : 'false');
            });

            if (input) {
                input.value = value;
            }

            // Announce
            const form = rating.closest('form');
            if (form) {
                this.announce(form.id, 
                    bafPublic.i18n.ratingSet.replace('%d', value), 
                    'polite'
                );
            }
        },

        /**
         * Initialize collapsible sections
         * @param {HTMLFormElement} form 
         */
        initGooglePlaces: function(form) {
            const inputs = form.querySelectorAll('[data-google-places="true"]');
            if (!inputs.length) return;

            const apiKey = bafPublic?.google?.mapsApiKey;
            if (!apiKey) return;

            // If google is already present, init immediately
            if (typeof window.google !== 'undefined' && window.google?.maps?.places?.Autocomplete) {
                inputs.forEach((input) => this.attachGooglePlacesAutocomplete(input, form));
                return;
            }

            // Load Google Places library once
            if (!window.__bafGooglePlacesLoading) {
                window.__bafGooglePlacesLoading = true;
                const cb = '__bafGooglePlacesInit';

                window[cb] = () => {
                    inputs.forEach((input) => this.attachGooglePlacesAutocomplete(input, form));
                };

                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places&callback=${encodeURIComponent(cb)}`;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            } else if (typeof window.__bafGooglePlacesInit === 'function') {
                // Another form triggered the load; will init via callback
            }
        },

        attachGooglePlacesAutocomplete: function(input, form) {
            if (input.__bafPlacesAttached) return;
            if (typeof window.google === 'undefined' || !window.google?.maps?.places?.Autocomplete) return;

            let types = [];
            try {
                types = JSON.parse(input.dataset.googlePlacesTypes || '[]');
            } catch (e) {
                types = [];
            }

            const ac = new window.google.maps.places.Autocomplete(input, {
                types: Array.isArray(types) ? types : [],
                fields: ['address_components']
            });

            let countries = [];
            try {
                countries = JSON.parse(input.dataset.googlePlacesCountries || '[]');
            } catch (e) {
                countries = [];
            }

            if (Array.isArray(countries) && countries.length) {
                ac.setComponentRestrictions({ country: countries });
            }

            ac.addListener('place_changed', () => {
                const place = ac.getPlace();
                this.fillAddressFromPlace(input, place);
                this.applyConditionalLogic(form);
            });

            input.__bafPlacesAttached = true;
        },

        fillAddressFromPlace: function(line1Input, place) {
            if (!place?.address_components) return;

            const fieldId = line1Input.dataset.addressFieldId;
            if (!fieldId) return;

            const rootName = `baf_field_${fieldId}`;
            const form = line1Input.closest('form');
            if (!form) return;

            const components = {};
            place.address_components.forEach((c) => {
                (c.types || []).forEach((t) => { components[t] = c.long_name; });
            });

            const streetNumber = components.street_number || '';
            const route = components.route || '';
            const city = components.locality || components.postal_town || '';
            const state = components.administrative_area_level_1 || '';
            const postal = components.postal_code || '';
            const countryCode = components.country || '';

            const setIfPresent = (suffix, value) => {
                const el = form.querySelector(`[name="${rootName}[${suffix}]"]`);
                if (el && value) el.value = value;
            };

            // line1: combine street number + route
            const line1 = `${streetNumber} ${route}`.trim();
            if (line1) line1Input.value = line1;

            setIfPresent('city', city);
            setIfPresent('state', state);
            setIfPresent('zip', postal);

            // Country select expects code in this plugin; Places gives long_name unless requested.
            // We avoid forcing it unless it matches an existing option.
            const countrySelect = form.querySelector(`[name="${rootName}[country]"]`);
            if (countrySelect && countryCode) {
                const opt = [...countrySelect.options].find(o => o.value === countryCode);
                if (opt) countrySelect.value = countryCode;
            }
        },

        initCollapsibleSections: function(form) {
            const toggles = form.querySelectorAll('.baf-section-toggle');

            toggles.forEach(toggle => {
                // Click handler (Space and Enter work natively on buttons)
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleSection(toggle, form);
                });
            });
        },

        /**
         * Toggle section expand/collapse
         * @param {HTMLButtonElement} toggle 
         * @param {HTMLFormElement} form 
         */
        toggleSection: function(toggle, form) {
            const panelId = toggle.getAttribute('aria-controls');
            const panel = document.getElementById(panelId);
            
            if (!panel) return;

            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            const iconStyle = this.getIconStyle(toggle);

            // Toggle state
            toggle.setAttribute('aria-expanded', !isExpanded);

            if (isExpanded) {
                // Collapse
                panel.setAttribute('hidden', '');
                this.updateIcon(toggle, iconStyle, false);
                this.announce(form, bafPublic.i18n?.sectionCollapsed || 'Section collapsed', 'polite');
            } else {
                // Expand
                panel.removeAttribute('hidden');
                this.updateIcon(toggle, iconStyle, true);
                this.announce(form, bafPublic.i18n?.sectionExpanded || 'Section expanded', 'polite');
            }
        },

        /**
         * Get icon style from toggle button
         * @param {HTMLButtonElement} toggle 
         * @returns {string} Icon style
         */
        getIconStyle: function(toggle) {
            const icon = toggle.querySelector('.baf-section-icon');
            if (!icon) return 'chevron';

            if (icon.classList.contains('baf-icon-plus_minus')) return 'plus_minus';
            if (icon.classList.contains('baf-icon-caret')) return 'caret';
            if (icon.classList.contains('baf-icon-arrow')) return 'arrow';
            return 'chevron';
        },

        /**
         * Update collapse icon
         * @param {HTMLButtonElement} toggle 
         * @param {string} style 
         * @param {boolean} expanded 
         */
        updateIcon: function(toggle, style, expanded) {
            const icon = toggle.querySelector('.baf-section-icon');
            if (!icon) return;

            const icons = {
                'chevron': { expanded: '▾', collapsed: '▸' },
                'plus_minus': { expanded: '−', collapsed: '+' },
                'caret': { expanded: '▼', collapsed: '▶' },
                'arrow': { expanded: '↓', collapsed: '→' }
            };

            const state = expanded ? 'expanded' : 'collapsed';
            icon.textContent = icons[style]?.[state] || icons['chevron'][state];
        },

        /**
         * Announce message to screen reader
         * @param {HTMLFormElement} form 
         * @param {string} message 
         * @param {string} priority 
         */
        announce: function(form, message, priority = 'polite') {
            const instanceId = form.id;
            const announcer = this.announcers?.[instanceId];
            
            if (announcer) {
                announcer.setAttribute('aria-live', priority);
                announcer.textContent = message;
                
                // Clear after announcement
                setTimeout(() => {
                    announcer.textContent = '';
                }, 1000);
            }
        },

        /**
         * Initialize cascading dropdowns
         * @param {HTMLFormElement} form 
         */
        initCascadingDropdowns: function(form) {
            const cascadeSelects = form.querySelectorAll('.baf-select-cascade');

            cascadeSelects.forEach(select => {
                const parentFieldId = select.dataset.cascadeFrom;
                if (!parentFieldId) return;

                // Find parent field
                const parentField = form.querySelector(`[name="baf_field_${parentFieldId}"], [name="fields[${parentFieldId}]"]`);
                if (!parentField) return;

                // Store original options for static mapping
                const mapping = select.dataset.cascadeMapping ? JSON.parse(select.dataset.cascadeMapping) : null;
                const populate = select.dataset.populate ? JSON.parse(select.dataset.populate) : null;

                // Initial state
                this.updateCascadeOptions(select, parentField.value, mapping, populate, form);

                // Listen for parent changes
                parentField.addEventListener('change', () => {
                    this.updateCascadeOptions(select, parentField.value, mapping, populate, form);
                });
            });
        },

        /**
         * Update cascading dropdown options
         * @param {HTMLSelectElement} select 
         * @param {string} parentValue 
         * @param {Object} mapping 
         * @param {Object} populate 
         * @param {HTMLFormElement} form 
         */
        updateCascadeOptions: function(select, parentValue, mapping, populate, form) {
            const emptyMessage = select.dataset.emptyMessage || 'Please select an option above first';
            const loadingMessage = select.dataset.loadingMessage || 'Loading options...';
            const loadingEl = select.parentElement?.querySelector('.baf-cascade-loading');

            // Clear current options
            select.innerHTML = '';

            // No parent value - show empty message
            if (!parentValue) {
                select.innerHTML = `<option value="">${this.escapeHtml(emptyMessage)}</option>`;
                select.disabled = true;
                select.setAttribute('aria-busy', 'false');
                this.announce(form, emptyMessage, 'polite');
                return;
            }

            // Static mapping
            if (mapping && mapping[parentValue]) {
                this.populateCascadeOptions(select, mapping[parentValue], form);
                return;
            }

            // Dynamic populate via AJAX
            if (populate) {
                select.innerHTML = `<option value="">${this.escapeHtml(loadingMessage)}</option>`;
                select.disabled = true;
                select.setAttribute('aria-busy', 'true');
                
                if (loadingEl) {
                    loadingEl.removeAttribute('hidden');
                }

                this.fetchCascadeOptions(select, parentValue, populate, form, loadingEl);
                return;
            }

            // No mapping or populate config
            select.innerHTML = `<option value="">${this.escapeHtml(emptyMessage)}</option>`;
            select.disabled = true;
        },

        /**
         * Populate cascade options from static data
         * @param {HTMLSelectElement} select 
         * @param {Array|Object} options 
         * @param {HTMLFormElement} form 
         */
        populateCascadeOptions: function(select, options, form) {
            const placeholder = select.dataset.placeholder || 'Select an option...';
            
            // Add placeholder
            select.innerHTML = `<option value="">${this.escapeHtml(placeholder)}</option>`;

            // Add options
            if (Array.isArray(options)) {
                options.forEach(opt => {
                    if (typeof opt === 'object') {
                        const option = document.createElement('option');
                        option.value = opt.value ?? opt.label;
                        option.textContent = opt.label;
                        select.appendChild(option);
                    } else {
                        const option = document.createElement('option');
                        option.value = opt;
                        option.textContent = opt;
                        select.appendChild(option);
                    }
                });
            } else if (typeof options === 'object') {
                // Object format: { "value": "label" }
                Object.entries(options).forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    select.appendChild(option);
                });
            }

            select.disabled = false;
            select.setAttribute('aria-busy', 'false');
            
            const count = select.options.length - 1; // Exclude placeholder
            this.announce(form, `${count} options available`, 'polite');
        },

        /**
         * Fetch cascade options via AJAX
         * @param {HTMLSelectElement} select 
         * @param {string} parentValue 
         * @param {Object} populate 
         * @param {HTMLFormElement} form 
         * @param {HTMLElement} loadingEl 
         */
        fetchCascadeOptions: async function(select, parentValue, populate, form, loadingEl) {
            const emptyMessage = select.dataset.emptyMessage || 'No options available';

            try {
                // Build filters with parent value
                const filters = [...(populate.filters || [])];
                
                // Replace {parent} placeholder in filter values
                filters.forEach(filter => {
                    if (typeof filter.value === 'string' && filter.value.includes('{')) {
                        filter.value = parentValue;
                    }
                });

                const response = await fetch(bafPublic.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'baf_populate_field',
                        nonce: bafPublic.nonce,
                        source: populate.source || '',
                        filters: JSON.stringify(filters),
                        order: JSON.stringify(populate.order || {}),
                        limit: populate.limit || 0,
                        config: JSON.stringify(populate.config || {}),
                        value_field: populate.value || 'value',
                        label_field: populate.label || 'label',
                    }),
                });

                const data = await response.json();

                if (loadingEl) {
                    loadingEl.setAttribute('hidden', '');
                }

                if (data.success && data.data.choices?.length) {
                    this.populateCascadeOptions(select, data.data.choices, form);
                } else {
                    select.innerHTML = `<option value="">${this.escapeHtml(emptyMessage)}</option>`;
                    select.disabled = true;
                    select.setAttribute('aria-busy', 'false');
                    this.announce(form, emptyMessage, 'polite');
                }
            } catch (error) {
                console.error('BAF: Cascade fetch error', error);
                
                if (loadingEl) {
                    loadingEl.setAttribute('hidden', '');
                }

                select.innerHTML = `<option value="">Error loading options</option>`;
                select.disabled = true;
                select.setAttribute('aria-busy', 'false');
            }
        },

        /**
         * Initialize character counters
         * @param {HTMLFormElement} form 
         */
        initCharacterCounters: function(form) {
            const counters = form.querySelectorAll('[data-counter]');

            counters.forEach(field => {
                const maxLength = field.maxLength || parseInt(field.dataset.maxLength) || 0;
                if (maxLength <= 0) return;

                // Create counter element
                const counter = document.createElement('div');
                counter.className = 'baf-char-counter';
                counter.setAttribute('aria-live', 'polite');
                field.parentNode.insertBefore(counter, field.nextSibling);

                // Update on input
                const updateCounter = () => {
                    const remaining = maxLength - field.value.length;
                    counter.textContent = bafPublic.i18n.charsRemaining.replace('%d', remaining);
                    counter.classList.toggle('baf-counter-warning', remaining < 20);
                };

                field.addEventListener('input', updateCounter);
                updateCounter();
            });
        },

        /**
         * Initialize input masks (progressive enhancement)
         * Supports simple placeholder masks like (999) 999-9999, 99/99/9999, and a basic currency format.
         * @param {HTMLFormElement} form
         */
        initInputMasks: function(form) {
            const inputs = form.querySelectorAll('input[data-inputmask], textarea[data-inputmask]');

            inputs.forEach((input) => {
                const mask = input.dataset.inputmask;
                if (!mask) return;

                // Special-case: international phone (+digits up to 15)
                if (mask === '+9{1,15}') {
                    const applyIntl = () => {
                        const digits = (input.value || '').replace(/\D/g, '').slice(0, 15);
                        input.value = digits ? `+${digits}` : '';
                    };
                    input.addEventListener('input', applyIntl);
                    input.addEventListener('blur', applyIntl);
                    applyIntl();
                    return;
                }

                // Special-case: currency
                if (mask === 'currency') {
                    const normalize = () => {
                        // Keep digits and decimal separator.
                        const raw = (input.value || '').replace(/[^0-9.\-]/g, '');
                        input.value = raw;
                    };

                    const format = () => {
                        const raw = (input.value || '').replace(/[^0-9.\-]/g, '');
                        if (!raw) return;
                        const num = Number(raw);
                        if (!Number.isFinite(num)) return;
                        input.value = num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };

                    input.addEventListener('input', normalize);
                    input.addEventListener('blur', format);
                    normalize();
                    return;
                }

                // Generic placeholder masks: 9 digit, A/a letter, * alphanumeric.
                const defs = {
                    '9': /\d/,
                    'A': /[a-zA-Z]/,
                    'a': /[a-zA-Z]/,
                    '*': /[a-zA-Z0-9]/
                };

                const hasPlaceholder = Array.from(mask).some(ch => defs[ch]);
                if (!hasPlaceholder) return;

                const applyMask = () => {
                    const raw = (input.value || '').replace(/[^a-zA-Z0-9]/g, '');
                    let rawIndex = 0;
                    let out = '';

                    const hasMoreRaw = (fromIndex) => raw.slice(fromIndex).length > 0;
                    const hasFuturePlaceholder = (fromMaskIndex) => {
                        for (let j = fromMaskIndex; j < mask.length; j++) {
                            if (defs[mask[j]]) return true;
                        }
                        return false;
                    };

                    for (let i = 0; i < mask.length; i++) {
                        const m = mask[i];

                        if (defs[m]) {
                            let matched = false;
                            while (rawIndex < raw.length) {
                                const c = raw[rawIndex];
                                rawIndex++;
                                if (defs[m].test(c)) {
                                    out += c;
                                    matched = true;
                                    break;
                                }
                            }
                            if (!matched) {
                                break;
                            }
                        } else {
                            // Only include literals when user has started typing and there is more to come.
                            if ((out.length > 0 || hasMoreRaw(rawIndex)) && hasFuturePlaceholder(i + 1)) {
                                out += m;
                            }
                        }
                    }

                    input.value = out;
                };

                input.addEventListener('input', applyMask);
                applyMask();
            });
        },

        /**
         * Initialize conditional logic
         */
        initConditionalLogic: function() {
            const conditionalFields = document.querySelectorAll('[data-conditional]');

            conditionalFields.forEach(wrapper => {
                try {
                    const rules = JSON.parse(wrapper.dataset.conditional);
                    this.applyConditionalLogic(wrapper, rules);

                    // Re-evaluate on field changes
                    const form = wrapper.closest('form');
                    if (form) {
                        form.addEventListener('change', () => {
                            this.applyConditionalLogic(wrapper, rules);
                        });
                    }
                } catch (e) {
                    console.error('BAF: Invalid conditional logic', e);
                }
            });
        },

        /**
         * Apply conditional logic rules
         * @param {HTMLElement} wrapper 
         * @param {Object} rules 
         */
        applyConditionalLogic: function(wrapper, rules) {
            const form = wrapper.closest('form');
            if (!form) return;

            const action = rules.action || 'show';
            const logic = rules.logic || 'all'; // 'all' or 'any'
            const conditions = rules.conditions || [];

            const getControllingValue = (fieldId) => {
                const selector = `[name="fields[${fieldId}]"],[name="baf_fields[${fieldId}]"],[name^="baf_field_${fieldId}"]`;
                const nodes = Array.from(form.querySelectorAll(selector));
                if (!nodes.length) return null;

                // Checkbox/radio groups
                if (nodes[0].type === 'checkbox') {
                    return nodes.filter(n => n.checked).map(n => n.value);
                }
                if (nodes[0].type === 'radio') {
                    return nodes.find(n => n.checked)?.value || '';
                }

                // Composite object (e.g., baf_field_id[sub])
                if (nodes.length > 1 && nodes.some(n => n.name.includes('['))) {
                    const obj = {};
                    nodes.forEach(n => {
                        const m = n.name.match(/\[([^\]]+)\]$/);
                        if (m) obj[m[1]] = n.value;
                    });
                    return obj;
                }

                return nodes[0].value;
            };

            const isEmptyValue = (v) => {
                if (v === null || v === undefined) return true;
                if (Array.isArray(v)) return v.length === 0;
                if (typeof v === 'object') return Object.values(v).every(x => !x);
                return String(v).length === 0;
            };

            const matchWildcard = (value, pattern) => {
                const escaped = String(pattern).replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
                const re = new RegExp(`^${escaped}$`, 'i');
                return re.test(String(value));
            };

            let shouldApply = logic === 'all';

            conditions.forEach(condition => {
                const fieldValue = getControllingValue(condition.field);
                let conditionMet = false;

                switch (condition.operator) {
                    case 'equals':
                        conditionMet = Array.isArray(fieldValue) ? fieldValue.includes(String(condition.value)) : String(fieldValue) == String(condition.value);
                        break;
                    case 'not_equals':
                        conditionMet = Array.isArray(fieldValue) ? !fieldValue.includes(String(condition.value)) : String(fieldValue) != String(condition.value);
                        break;
                    case 'contains':
                        conditionMet = Array.isArray(fieldValue) ? fieldValue.includes(String(condition.value)) : String(fieldValue).includes(String(condition.value));
                        break;
                    case 'not_contains':
                        conditionMet = Array.isArray(fieldValue) ? !fieldValue.includes(String(condition.value)) : !String(fieldValue).includes(String(condition.value));
                        break;
                    case 'starts_with':
                        conditionMet = String(fieldValue).startsWith(String(condition.value));
                        break;
                    case 'ends_with':
                        conditionMet = String(fieldValue).endsWith(String(condition.value));
                        break;
                    case 'greater_than':
                        conditionMet = parseFloat(fieldValue) > parseFloat(condition.value);
                        break;
                    case 'less_than':
                        conditionMet = parseFloat(fieldValue) < parseFloat(condition.value);
                        break;
                    case 'before':
                        conditionMet = new Date(fieldValue) < new Date(condition.value);
                        break;
                    case 'after':
                        conditionMet = new Date(fieldValue) > new Date(condition.value);
                        break;
                    case 'any_of':
                        conditionMet = Array.isArray(fieldValue) && Array.isArray(condition.value) ? condition.value.some(v => fieldValue.includes(String(v))) : false;
                        break;
                    case 'all_of':
                        conditionMet = Array.isArray(fieldValue) && Array.isArray(condition.value) ? condition.value.every(v => fieldValue.includes(String(v))) : false;
                        break;
                    case 'matches':
                        conditionMet = matchWildcard(fieldValue, condition.value);
                        break;
                    case 'is_empty':
                        conditionMet = isEmptyValue(fieldValue);
                        break;
                    case 'is_not_empty':
                        conditionMet = !isEmptyValue(fieldValue);
                        break;
                }

                if (logic === 'all') {
                    shouldApply = shouldApply && conditionMet;
                } else {
                    shouldApply = shouldApply || conditionMet;
                }
            });

            // Apply action
            if (action === 'hide') {
                shouldApply = !shouldApply;
            }

            if (action === 'disable') {
                const inputs = wrapper.querySelectorAll('input, select, textarea');
                inputs.forEach(input => { input.disabled = shouldApply; });
                return;
            }

            if (action === 'enable') {
                const inputs = wrapper.querySelectorAll('input, select, textarea');
                inputs.forEach(input => { input.disabled = !shouldApply; });
                return;
            }

            if (action === 'require') {
                const inputs = wrapper.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (shouldApply) {
                        input.setAttribute('required', 'required');
                    } else {
                        input.removeAttribute('required');
                    }
                });
                return;
            }

            if (action === 'set_value') {
                if (!shouldApply) return;
                const inputs = wrapper.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') return;
                    input.value = rules.value ?? '';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
                return;
            }

            // Default: show/hide
            wrapper.style.display = shouldApply ? '' : 'none';
            wrapper.setAttribute('aria-hidden', shouldApply ? 'false' : 'true');

            // Disable hidden fields for validation
            const inputs = wrapper.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = !shouldApply;
            });
        },

        /**
         * Escape HTML
         * @param {string} str 
         * @returns {string}
         */
        escapeHtml: function(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => BAFForms.init());
    } else {
        BAFForms.init();
    }

    // Expose globally
    window.BAFForms = BAFForms;

})(jQuery);

/**
 * Default localization strings (overridden by wp_localize_script)
 */
window.bafPublic = window.bafPublic || {
    ajaxUrl: '',
    restUrl: '',
    nonce: '',
    i18n: {
        required: 'This field is required.',
        invalidEmail: 'Please enter a valid email address.',
        invalidUrl: 'Please enter a valid URL.',
        minLength: 'Please enter at least %d characters.',
        maxLength: 'Please enter no more than %d characters.',
        minValue: 'Value must be at least %d.',
        maxValue: 'Value must be no more than %d.',
        invalidFormat: 'Please enter a valid format.',
        validationErrors: 'Please correct the errors before submitting.',
        submitting: 'Submitting form...',
        successMessage: 'Form submitted successfully.',
        errorMessage: 'An error occurred. Please try again.',
        networkError: 'Network error. Please check your connection.',
        pageChanged: 'Now on page %1$d of %2$d.',
        pageStatus: 'Step %1$d of %2$d',
        removeFile: 'Remove file %s',
        charsRemaining: '%d characters remaining',
        ratingSet: 'Rating set to %d'
    }
};
