/**
 * QAF Save & Continue JavaScript
 *
 * Handles saving form progress and resuming later.
 *
 * @package QAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Save & Continue Manager
     */
    class QAFSaveContinue {
        constructor(form) {
            this.form = form;
            this.$form = $(form);
            this.formId = this.$form.data('form-id');
            this.token = this.getStoredToken() || null;
            this.$saveButton = this.$form.find('.qaf-save-button');
            this.$saveStatus = this.$form.find('.qaf-save-status');
            this.$emailInput = this.$form.find('input[name="qaf_save_email"]');
            
            // Auto-save settings
            this.autoSaveEnabled = this.$form.data('autosave') === true;
            this.autoSaveInterval = this.$form.data('autosave-interval') || 60000; // 1 minute
            this.autoSaveTimer = null;
            this.lastSavedData = null;
            
            this.init();
        }

        /**
         * Initialize event handlers
         */
        init() {
            // Save button click
            this.$saveButton.on('click', (e) => {
                e.preventDefault();
                this.saveProgress();
            });

            // Check for resume token in URL
            this.checkResumeToken();

            // Auto-save functionality
            if (this.autoSaveEnabled) {
                this.startAutoSave();
            }

            // Clear saved data on successful submission
            this.$form.on('qaf:submitted', () => {
                this.clearSavedData();
            });

            // Track form changes for auto-save
            this.$form.on('change input', ':input', () => {
                this.markAsChanged();
            });

            // Warn before leaving with unsaved changes
            if (this.$form.data('warn-unsaved') !== false) {
                this.setupBeforeUnloadWarning();
            }

            // Keyboard shortcut: Ctrl/Cmd + S
            this.$form.on('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    this.saveProgress();
                }
            });
        }

        /**
         * Save form progress
         */
        async saveProgress(silent = false) {
            const formData = this.collectFormData();
            const email = this.$emailInput.val() || '';

            if (!silent) {
                this.setStatus('saving', qafSaveContinue.i18n.saving);
            }

            try {
                const response = await $.ajax({
                    url: qafSaveContinue.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'qaf_save_form_progress',
                        nonce: qafSaveContinue.nonce,
                        form_id: this.formId,
                        form_data: formData,
                        current_page: this.getCurrentPage(),
                        email: email,
                        token: this.token
                    }
                });

                if (response.success) {
                    this.token = response.data.token;
                    this.storeToken(this.token);
                    this.lastSavedData = JSON.stringify(formData);

                    if (!silent) {
                        this.setStatus('success', response.data.message);
                        this.showResumeLink(response.data.resume_link, response.data.expires);
                    }

                    // Trigger event
                    this.$form.trigger('qaf:saved', {
                        token: this.token,
                        resumeLink: response.data.resume_link
                    });
                } else {
                    if (!silent) {
                        this.setStatus('error', response.data.message);
                    }
                }
            } catch (error) {
                console.error('QAF Save Error:', error);
                if (!silent) {
                    this.setStatus('error', qafSaveContinue.i18n.error);
                }
            }
        }

        /**
         * Load saved form data
         */
        async loadSavedData(token) {
            this.setStatus('loading', qafSaveContinue.i18n.loading);

            try {
                const response = await $.ajax({
                    url: qafSaveContinue.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'qaf_load_saved_form',
                        nonce: qafSaveContinue.loadNonce,
                        token: token
                    }
                });

                if (response.success) {
                    this.token = token;
                    this.storeToken(token);
                    this.populateForm(response.data.form_data);
                    
                    // Go to saved page for multi-page forms
                    if (response.data.current_page > 1) {
                        this.goToPage(response.data.current_page);
                    }

                    this.setStatus('success', qafSaveContinue.i18n.restored);
                    this.lastSavedData = JSON.stringify(response.data.form_data);

                    // Trigger event
                    this.$form.trigger('qaf:restored', {
                        data: response.data.form_data,
                        page: response.data.current_page
                    });

                    // Remove token from URL without refresh
                    this.cleanUrl();
                } else {
                    this.setStatus('error', response.data.message);
                }
            } catch (error) {
                console.error('QAF Load Error:', error);
                this.setStatus('error', qafSaveContinue.i18n.loadError);
            }
        }

        /**
         * Delete saved data
         */
        async deleteSavedData() {
            if (!this.token) return;

            try {
                await $.ajax({
                    url: qafSaveContinue.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'qaf_delete_saved_form',
                        nonce: qafSaveContinue.deleteNonce,
                        token: this.token
                    }
                });

                this.clearSavedData();
            } catch (error) {
                console.error('QAF Delete Error:', error);
            }
        }

        /**
         * Collect form data
         */
        collectFormData() {
            const data = {};
            const fields = this.$form.serializeArray();

            fields.forEach(field => {
                // Skip nonce and action fields
                if (field.name.startsWith('_') || field.name === 'action') {
                    return;
                }

                // Handle array fields (checkboxes, multi-select)
                if (field.name.endsWith('[]')) {
                    const baseName = field.name.slice(0, -2);
                    if (!data[baseName]) {
                        data[baseName] = [];
                    }
                    data[baseName].push(field.value);
                } else {
                    data[field.name] = field.value;
                }
            });

            // Handle file inputs (store file names for reference)
            this.$form.find('input[type="file"]').each(function() {
                const $input = $(this);
                const files = this.files;
                if (files.length > 0) {
                    data[$input.attr('name') + '_files'] = Array.from(files).map(f => f.name);
                }
            });

            return data;
        }

        /**
         * Populate form with saved data
         */
        populateForm(data) {
            Object.entries(data).forEach(([name, value]) => {
                // Skip file reference data
                if (name.endsWith('_files')) return;

                const $field = this.$form.find(`[name="${name}"], [name="${name}[]"]`);
                
                if ($field.length === 0) return;

                const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

                switch (fieldType) {
                    case 'checkbox':
                        if (Array.isArray(value)) {
                            $field.each(function() {
                                $(this).prop('checked', value.includes($(this).val()));
                            });
                        } else {
                            $field.prop('checked', !!value);
                        }
                        break;

                    case 'radio':
                        $field.filter(`[value="${value}"]`).prop('checked', true);
                        break;

                    case 'select':
                        if ($field.prop('multiple') && Array.isArray(value)) {
                            $field.val(value);
                        } else {
                            $field.val(value);
                        }
                        break;

                    case 'file':
                        // Can't restore file inputs, but show a message
                        const fileNames = data[name + '_files'];
                        if (fileNames && fileNames.length > 0) {
                            $field.after(
                                `<small class="qaf-previous-files">${qafSaveContinue.i18n.previousFiles}: ${fileNames.join(', ')}</small>`
                            );
                        }
                        break;

                    default:
                        $field.val(value);
                }

                // Trigger change event for conditional logic
                $field.trigger('change');
            });
        }

        /**
         * Get current page number for multi-page forms
         */
        getCurrentPage() {
            const $activePage = this.$form.find('.qaf-page.active, .qaf-page[data-active="true"]');
            if ($activePage.length) {
                return parseInt($activePage.data('page') || $activePage.index() + 1, 10);
            }
            return 1;
        }

        /**
         * Go to specific page in multi-page form
         */
        goToPage(pageNum) {
            // Trigger QAF's built-in page navigation
            this.$form.trigger('qaf:goToPage', { page: pageNum });
        }

        /**
         * Check for resume token in URL
         */
        checkResumeToken() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('qaf_resume');

            if (token) {
                this.loadSavedData(token);
            } else {
                // Check for locally stored token
                const storedToken = this.getStoredToken();
                if (storedToken && this.$form.data('auto-restore') !== false) {
                    this.showRestorePrompt(storedToken);
                }
            }
        }

        /**
         * Show prompt to restore saved data
         */
        showRestorePrompt(token) {
            const $prompt = $(`
                <div class="qaf-restore-prompt" role="dialog" aria-labelledby="qaf-restore-title">
                    <h4 id="qaf-restore-title">${qafSaveContinue.i18n.foundSaved}</h4>
                    <p>${qafSaveContinue.i18n.restorePrompt}</p>
                    <div class="qaf-restore-actions">
                        <button type="button" class="qaf-button qaf-restore-yes">
                            ${qafSaveContinue.i18n.restore}
                        </button>
                        <button type="button" class="qaf-button-secondary qaf-restore-no">
                            ${qafSaveContinue.i18n.startFresh}
                        </button>
                    </div>
                </div>
            `);

            this.$form.prepend($prompt);

            $prompt.find('.qaf-restore-yes').on('click', () => {
                $prompt.remove();
                this.loadSavedData(token);
            });

            $prompt.find('.qaf-restore-no').on('click', () => {
                $prompt.remove();
                this.clearSavedData();
            });

            // Focus the prompt for accessibility
            $prompt.find('.qaf-restore-yes').focus();
        }

        /**
         * Show resume link after saving
         */
        showResumeLink(link, expires) {
            const $linkDisplay = this.$form.find('.qaf-resume-link-display');
            
            if ($linkDisplay.length) {
                $linkDisplay.html(`
                    <div class="qaf-resume-link-info">
                        <label>${qafSaveContinue.i18n.resumeLink}</label>
                        <input type="text" readonly value="${link}" class="qaf-resume-link-input">
                        <button type="button" class="qaf-copy-link" aria-label="${qafSaveContinue.i18n.copyLink}">
                            📋
                        </button>
                        <small>${qafSaveContinue.i18n.expires}: ${expires}</small>
                    </div>
                `);

                // Copy button handler
                $linkDisplay.find('.qaf-copy-link').on('click', function() {
                    const $input = $linkDisplay.find('.qaf-resume-link-input');
                    $input.select();
                    document.execCommand('copy');
                    $(this).text('✓').attr('aria-label', qafSaveContinue.i18n.copied);
                    setTimeout(() => {
                        $(this).text('📋').attr('aria-label', qafSaveContinue.i18n.copyLink);
                    }, 2000);
                });
            }
        }

        /**
         * Set status message
         */
        setStatus(type, message) {
            const icons = {
                saving: '⏳',
                loading: '⏳',
                success: '✅',
                error: '❌',
            };

            this.$saveStatus
                .removeClass('saving loading success error')
                .addClass(type)
                .html(`<span aria-hidden="true">${icons[type] || ''}</span> ${message}`);

            // Announce to screen readers
            if (window.QAFAnnouncer) {
                window.QAFAnnouncer.announce(message, type === 'error' ? 'assertive' : 'polite');
            }

            // Auto-clear success messages
            if (type === 'success') {
                setTimeout(() => {
                    this.$saveStatus.fadeOut(300, function() {
                        $(this).empty().show();
                    });
                }, 5000);
            }
        }

        /**
         * Auto-save functionality
         */
        startAutoSave() {
            this.autoSaveTimer = setInterval(() => {
                // Only save if form has changed
                const currentData = JSON.stringify(this.collectFormData());
                if (currentData !== this.lastSavedData) {
                    this.saveProgress(true); // Silent save
                }
            }, this.autoSaveInterval);

            // Clean up on form unload
            this.$form.on('qaf:submitted', () => {
                this.stopAutoSave();
            });
        }

        /**
         * Stop auto-save timer
         */
        stopAutoSave() {
            if (this.autoSaveTimer) {
                clearInterval(this.autoSaveTimer);
                this.autoSaveTimer = null;
            }
        }

        /**
         * Mark form as having unsaved changes
         */
        markAsChanged() {
            this.$form.data('has-changes', true);
        }

        /**
         * Setup beforeunload warning
         */
        setupBeforeUnloadWarning() {
            $(window).on('beforeunload', (e) => {
                if (this.$form.data('has-changes') && 
                    JSON.stringify(this.collectFormData()) !== this.lastSavedData) {
                    e.preventDefault();
                    e.returnValue = qafSaveContinue.i18n.unsavedWarning;
                    return e.returnValue;
                }
            });
        }

        /**
         * Store token in localStorage
         */
        storeToken(token) {
            try {
                localStorage.setItem(`qaf_resume_${this.formId}`, token);
            } catch (e) {
                // localStorage might be disabled
                console.warn('QAF: Could not store token in localStorage');
            }
        }

        /**
         * Get stored token from localStorage
         */
        getStoredToken() {
            try {
                return localStorage.getItem(`qaf_resume_${this.formId}`);
            } catch (e) {
                return null;
            }
        }

        /**
         * Clear saved data
         */
        clearSavedData() {
            this.token = null;
            try {
                localStorage.removeItem(`qaf_resume_${this.formId}`);
            } catch (e) {
                // Ignore
            }
            this.lastSavedData = null;
            this.$form.data('has-changes', false);
        }

        /**
         * Clean URL (remove resume token)
         */
        cleanUrl() {
            const url = new URL(window.location);
            url.searchParams.delete('qaf_resume');
            window.history.replaceState({}, '', url);
        }
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        $('.qaf-form[data-save-continue="true"]').each(function() {
            new QAFSaveContinue(this);
        });
    });

    // Expose class globally
    window.QAFSaveContinue = QAFSaveContinue;

})(jQuery);
