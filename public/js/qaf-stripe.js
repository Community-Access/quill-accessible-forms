/**
 * QUILL Accessible Forms - Stripe Integration
 *
 * Handles Stripe Elements for accessible payment processing.
 *
 * @package QAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * QAF Stripe Handler
     */
    const QAFStripe = {
        /**
         * Stripe instance
         */
        stripe: null,

        /**
         * Stripe Elements instance
         */
        elements: null,

        /**
         * Card element
         */
        cardElement: null,

        /**
         * Initialize
         */
        init: function() {
            if (typeof Stripe === 'undefined') {
                console.error('QAF: Stripe.js not loaded');
                return;
            }

            if (!qafStripe?.publishableKey) {
                console.error('QAF: Stripe publishable key not configured');
                return;
            }

            // Initialize Stripe
            this.stripe = Stripe(qafStripe.publishableKey);
            this.elements = this.stripe.elements();

            // Initialize all Stripe fields
            const stripeFields = document.querySelectorAll('.qaf-stripe-field');
            stripeFields.forEach(field => this.initField(field));
        },

        /**
         * Initialize a Stripe field
         * @param {HTMLElement} container 
         */
        initField: function(container) {
            const cardContainer = container.querySelector('#qaf-stripe-card-element');
            const errorContainer = container.querySelector('#qaf-stripe-card-errors');
            const tokenInput = container.querySelector('#qaf-stripe-token');

            if (!cardContainer) return;

            // Create card element with accessible styling
            const style = {
                base: {
                    color: getComputedStyle(document.body).getPropertyValue('--qaf-color-text') || '#1a1a1a',
                    fontFamily: getComputedStyle(document.body).getPropertyValue('--qaf-font-family') || '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    fontSize: '16px',
                    fontSmoothing: 'antialiased',
                    '::placeholder': {
                        color: getComputedStyle(document.body).getPropertyValue('--qaf-color-text-muted') || '#666666'
                    }
                },
                invalid: {
                    color: getComputedStyle(document.body).getPropertyValue('--qaf-color-error') || '#cc0000',
                    iconColor: getComputedStyle(document.body).getPropertyValue('--qaf-color-error') || '#cc0000'
                }
            };

            // Create card element
            this.cardElement = this.elements.create('card', {
                style: style,
                hidePostalCode: false
            });

            // Mount the element
            this.cardElement.mount(cardContainer);

            // Handle real-time validation errors
            this.cardElement.on('change', (event) => {
                if (event.error) {
                    errorContainer.textContent = event.error.message;
                    errorContainer.setAttribute('role', 'alert');
                    cardContainer.classList.add('qaf-stripe-error');
                } else {
                    errorContainer.textContent = '';
                    cardContainer.classList.remove('qaf-stripe-error');
                }
            });

            // Handle focus for accessibility
            this.cardElement.on('focus', () => {
                cardContainer.classList.add('qaf-stripe-focused');
            });

            this.cardElement.on('blur', () => {
                cardContainer.classList.remove('qaf-stripe-focused');
            });

            // Intercept form submission
            const form = container.closest('form');
            if (form) {
                this.bindFormSubmission(form, container, tokenInput, errorContainer);
            }
        },

        /**
         * Bind form submission handler
         * @param {HTMLFormElement} form 
         * @param {HTMLElement} container 
         * @param {HTMLInputElement} tokenInput 
         * @param {HTMLElement} errorContainer 
         */
        bindFormSubmission: function(form, container, tokenInput, errorContainer) {
            // Store original submit handler
            const originalSubmit = form.onsubmit;

            form.addEventListener('submit', async (e) => {
                // Skip if payment already processed
                if (tokenInput.value) {
                    return;
                }

                e.preventDefault();

                // Disable submit button
                const submitBtn = form.querySelector('.qaf-submit-button');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                // Show loading state
                form.classList.add('qaf-form-submitting');

                try {
                    // Create payment method
                    const { paymentMethod, error } = await this.stripe.createPaymentMethod({
                        type: 'card',
                        card: this.cardElement,
                        billing_details: this.getBillingDetails(form)
                    });

                    if (error) {
                        // Show error
                        errorContainer.textContent = error.message;
                        errorContainer.setAttribute('role', 'alert');
                        
                        // Announce error
                        if (window.QAFForms) {
                            window.QAFForms.announce(form.id, error.message, 'assertive');
                        }

                        // Re-enable submit
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        form.classList.remove('qaf-form-submitting');
                        return;
                    }

                    // Store payment method ID
                    tokenInput.value = paymentMethod.id;

                    // Continue with form submission
                    if (form.classList.contains('qaf-ajax-form') && window.QAFForms) {
                        window.QAFForms.handleSubmit(form);
                    } else {
                        form.submit();
                    }
                } catch (err) {
                    console.error('QAF Stripe error:', err);
                    errorContainer.textContent = qafStripe.i18n?.cardError || 'Payment processing error.';
                    
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    form.classList.remove('qaf-form-submitting');
                }
            });
        },

        /**
         * Get billing details from form
         * @param {HTMLFormElement} form 
         * @returns {Object}
         */
        getBillingDetails: function(form) {
            const details = {};

            // Try to find name field
            const nameField = form.querySelector('[name="fields[name]"], [name="fields[full_name]"], [name="fields[billing_name]"]');
            if (nameField?.value) {
                details.name = nameField.value;
            }

            // Try to find email field
            const emailField = form.querySelector('[name="fields[email]"], [type="email"]');
            if (emailField?.value) {
                details.email = emailField.value;
            }

            // Try to find phone field
            const phoneField = form.querySelector('[name="fields[phone]"], [type="tel"]');
            if (phoneField?.value) {
                details.phone = phoneField.value;
            }

            // Try to find address fields
            const address = {};
            const addressLineField = form.querySelector('[name="fields[address][street]"], [name="fields[billing_address]"]');
            const cityField = form.querySelector('[name="fields[address][city]"], [name="fields[city]"]');
            const stateField = form.querySelector('[name="fields[address][state]"], [name="fields[state]"]');
            const postalField = form.querySelector('[name="fields[address][zip]"], [name="fields[postal_code]"]');
            const countryField = form.querySelector('[name="fields[address][country]"], [name="fields[country]"]');

            if (addressLineField?.value) address.line1 = addressLineField.value;
            if (cityField?.value) address.city = cityField.value;
            if (stateField?.value) address.state = stateField.value;
            if (postalField?.value) address.postal_code = postalField.value;
            if (countryField?.value) address.country = countryField.value;

            if (Object.keys(address).length > 0) {
                details.address = address;
            }

            return details;
        },

        /**
         * Create PaymentIntent for 3D Secure
         * @param {string} clientSecret 
         * @param {Object} options 
         * @returns {Promise}
         */
        confirmPayment: async function(clientSecret, options = {}) {
            const { error, paymentIntent } = await this.stripe.confirmCardPayment(
                clientSecret,
                {
                    payment_method: {
                        card: this.cardElement,
                        billing_details: options.billingDetails || {}
                    }
                }
            );

            if (error) {
                throw error;
            }

            return paymentIntent;
        },

        /**
         * Destroy and cleanup
         */
        destroy: function() {
            if (this.cardElement) {
                this.cardElement.destroy();
                this.cardElement = null;
            }
            this.elements = null;
            this.stripe = null;
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => QAFStripe.init());
    } else {
        QAFStripe.init();
    }

    // Expose globally
    window.QAFStripe = QAFStripe;

})(jQuery);

/**
 * Default localization (overridden by wp_localize_script)
 */
window.qafStripe = window.qafStripe || {
    publishableKey: '',
    i18n: {
        cardError: 'Your card was declined.',
        processingError: 'An error occurred while processing your payment.'
    }
};
