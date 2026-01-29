/**
 * BAF Accessibility Coach
 *
 * Real-time accessibility guidance in the form builder.
 *
 * @package BAF
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Accessibility Coach Controller
     */
    window.BAF_AccessibilityCoach = {
        /**
         * Is coach enabled
         */
        enabled: false,

        /**
         * Current analysis results
         */
        results: null,

        /**
         * Panel element
         */
        $panel: null,

        /**
         * Auto-analyze timeout
         */
        analyzeTimeout: null,

        /**
         * Initialize the coach
         */
        init: function() {
            this.createPanel();
            this.bindEvents();
            this.loadPreferences();
        },

        /**
         * Create the coach panel
         */
        createPanel: function() {
            var html = `
                <div id="baf-accessibility-coach" class="baf-coach-panel" role="complementary" aria-labelledby="baf-coach-title" hidden>
                    <div class="baf-coach-header">
                        <h3 id="baf-coach-title">
                            <span aria-hidden="true">♿</span>
                            ${bafBuilder.i18n.accessibilityCoach || 'Accessibility Coach'}
                        </h3>
                        <div class="baf-coach-actions">
                            <button type="button" class="baf-coach-refresh" aria-label="${bafBuilder.i18n.refreshAnalysis || 'Refresh analysis'}">
                                <span aria-hidden="true">🔄</span>
                            </button>
                            <button type="button" class="baf-coach-close" aria-label="${bafBuilder.i18n.closeCoach || 'Close accessibility coach'}">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="baf-coach-score" aria-live="polite">
                        <div class="baf-score-circle">
                            <span class="baf-score-value">--</span>
                        </div>
                        <div class="baf-score-label">${bafBuilder.i18n.accessibilityScore || 'Accessibility Score'}</div>
                    </div>
                    
                    <div class="baf-coach-summary" aria-live="polite">
                        <span class="baf-summary-errors"><strong>0</strong> ${bafBuilder.i18n.errors || 'Errors'}</span>
                        <span class="baf-summary-warnings"><strong>0</strong> ${bafBuilder.i18n.warnings || 'Warnings'}</span>
                        <span class="baf-summary-info"><strong>0</strong> ${bafBuilder.i18n.suggestions || 'Suggestions'}</span>
                    </div>
                    
                    <div class="baf-coach-body">
                        <div class="baf-coach-loading" hidden>
                            <span class="baf-spinner" aria-hidden="true"></span>
                            <span>${bafBuilder.i18n.analyzing || 'Analyzing...'}</span>
                        </div>
                        
                        <div class="baf-coach-empty" hidden>
                            <span aria-hidden="true">✅</span>
                            <p>${bafBuilder.i18n.noIssuesFound || 'No accessibility issues found. Great job!'}</p>
                        </div>
                        
                        <div class="baf-coach-issues">
                            <!-- Issues will be rendered here -->
                        </div>
                    </div>
                    
                    <div class="baf-coach-footer">
                        <label class="baf-coach-auto-analyze">
                            <input type="checkbox" id="baf-auto-analyze">
                            ${bafBuilder.i18n.autoAnalyze || 'Auto-analyze on changes'}
                        </label>
                    </div>
                </div>
            `;

            this.$panel = $(html);
            $('body').append(this.$panel);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Toggle button in toolbar
            $(document).on('click', '#baf-toggle-coach, .baf-toggle-coach', function(e) {
                e.preventDefault();
                self.toggle();
            });

            // Close button
            this.$panel.on('click', '.baf-coach-close', function() {
                self.hide();
            });

            // Refresh button
            this.$panel.on('click', '.baf-coach-refresh', function() {
                self.analyze();
            });

            // Auto-analyze toggle
            this.$panel.on('change', '#baf-auto-analyze', function() {
                self.setAutoAnalyze($(this).is(':checked'));
            });

            // Issue click - scroll to field
            this.$panel.on('click', '.baf-coach-issue', function() {
                var fieldId = $(this).data('field-id');
                if (fieldId && fieldId !== 'form') {
                    self.scrollToField(fieldId);
                }
            });

            // Listen for form changes if auto-analyze is on
            $(document).on('baf:field:added baf:field:removed baf:field:changed', function() {
                if (self.enabled && $('#baf-auto-analyze').is(':checked')) {
                    self.scheduleAnalyze();
                }
            });

            // Keyboard shortcut (Ctrl+Shift+A)
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                    e.preventDefault();
                    self.toggle();
                }
            });
        },

        /**
         * Load user preferences
         */
        loadPreferences: function() {
            var autoAnalyze = localStorage.getItem('baf_coach_auto_analyze');
            if (autoAnalyze === 'true') {
                this.$panel.find('#baf-auto-analyze').prop('checked', true);
            }
        },

        /**
         * Toggle panel visibility
         */
        toggle: function() {
            if (this.enabled) {
                this.hide();
            } else {
                this.show();
            }
        },

        /**
         * Show the panel
         */
        show: function() {
            this.enabled = true;
            this.$panel.removeAttr('hidden');
            this.analyze();
            this.announce(bafBuilder.i18n.coachOpened || 'Accessibility coach opened');
        },

        /**
         * Hide the panel
         */
        hide: function() {
            this.enabled = false;
            this.$panel.attr('hidden', true);
            this.announce(bafBuilder.i18n.coachClosed || 'Accessibility coach closed');
        },

        /**
         * Set auto-analyze preference
         */
        setAutoAnalyze: function(enabled) {
            localStorage.setItem('baf_coach_auto_analyze', enabled ? 'true' : 'false');
        },

        /**
         * Schedule analysis with debounce
         */
        scheduleAnalyze: function() {
            var self = this;
            clearTimeout(this.analyzeTimeout);
            this.analyzeTimeout = setTimeout(function() {
                self.analyze();
            }, 1000);
        },

        /**
         * Run accessibility analysis
         */
        analyze: function() {
            var self = this;
            var formConfig = this.getFormConfig();

            if (!formConfig) {
                this.showEmpty();
                return;
            }

            this.showLoading();

            $.ajax({
                url: bafBuilder.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'baf_analyze_accessibility',
                    nonce: bafBuilder.nonce,
                    form_config: JSON.stringify(formConfig)
                },
                success: function(response) {
                    if (response.success) {
                        self.results = response.data;
                        self.renderResults(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(bafBuilder.i18n.analyzeError || 'Analysis failed');
                }
            });
        },

        /**
         * Get current form configuration
         */
        getFormConfig: function() {
            // Try different sources
            if (window.bafFormConfig) {
                return window.bafFormConfig;
            }

            if (window.bafBuilderData) {
                return window.bafBuilderData;
            }

            // Try to build from DOM
            return null;
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            this.$panel.find('.baf-coach-loading').removeAttr('hidden');
            this.$panel.find('.baf-coach-empty').attr('hidden', true);
            this.$panel.find('.baf-coach-issues').empty();
        },

        /**
         * Show empty state
         */
        showEmpty: function() {
            this.$panel.find('.baf-coach-loading').attr('hidden', true);
            this.$panel.find('.baf-coach-empty').removeAttr('hidden');
            this.$panel.find('.baf-coach-issues').empty();
            this.updateScore(100);
            this.updateSummary({ errors: 0, warnings: 0, info: 0 });
        },

        /**
         * Show error
         */
        showError: function(message) {
            this.$panel.find('.baf-coach-loading').attr('hidden', true);
            this.$panel.find('.baf-coach-issues').html(
                '<div class="baf-coach-error">' + this.escapeHtml(message) + '</div>'
            );
        },

        /**
         * Render analysis results
         */
        renderResults: function(data) {
            this.$panel.find('.baf-coach-loading').attr('hidden', true);

            // Update score
            this.updateScore(data.score);

            // Update summary
            this.updateSummary(data.summary);

            // Render issues
            if (data.issues.length === 0) {
                this.showEmpty();
            } else {
                this.$panel.find('.baf-coach-empty').attr('hidden', true);
                this.renderIssues(data.issues, data.categories);
            }

            // Announce results
            this.announce(data.summary.total + ' accessibility issues found. Score: ' + data.score);
        },

        /**
         * Update score display
         */
        updateScore: function(score) {
            var $circle = this.$panel.find('.baf-score-circle');
            var $value = this.$panel.find('.baf-score-value');

            $value.text(score);

            // Color based on score
            $circle.removeClass('score-good score-ok score-poor');
            if (score >= 80) {
                $circle.addClass('score-good');
            } else if (score >= 50) {
                $circle.addClass('score-ok');
            } else {
                $circle.addClass('score-poor');
            }
        },

        /**
         * Update summary display
         */
        updateSummary: function(summary) {
            this.$panel.find('.baf-summary-errors strong').text(summary.errors);
            this.$panel.find('.baf-summary-warnings strong').text(summary.warnings);
            this.$panel.find('.baf-summary-info strong').text(summary.info);
        },

        /**
         * Render issues list
         */
        renderIssues: function(issues, categories) {
            var $container = this.$panel.find('.baf-coach-issues');
            $container.empty();

            // Group by category
            if (categories) {
                for (var catId in categories) {
                    var category = categories[catId];
                    var $section = $('<div class="baf-coach-category"></div>');
                    $section.append('<h4>' + this.escapeHtml(category.label) + '</h4>');

                    var $list = $('<ul class="baf-issue-list" role="list"></ul>');
                    category.issues.forEach(function(issue) {
                        $list.append(this.renderIssue(issue));
                    }, this);

                    $section.append($list);
                    $container.append($section);
                }
            } else {
                // Flat list
                var $list = $('<ul class="baf-issue-list" role="list"></ul>');
                issues.forEach(function(issue) {
                    $list.append(this.renderIssue(issue));
                }, this);
                $container.append($list);
            }
        },

        /**
         * Render single issue
         */
        renderIssue: function(issue) {
            var severityIcon = {
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            };

            var $li = $('<li class="baf-coach-issue" role="listitem"></li>');
            $li.addClass('severity-' + issue.severity);
            $li.attr('data-field-id', issue.field_id);
            $li.attr('tabindex', '0');

            var html = `
                <div class="baf-issue-header">
                    <span class="baf-issue-icon" aria-hidden="true">${severityIcon[issue.severity] || ''}</span>
                    <span class="baf-issue-title">${this.escapeHtml(issue.title)}</span>
                    <span class="baf-issue-field">${this.escapeHtml(issue.field_id)}</span>
                </div>
                <p class="baf-issue-message">${this.escapeHtml(issue.message)}</p>
            `;

            if (issue.suggestion) {
                html += `<p class="baf-issue-suggestion"><strong>${bafBuilder.i18n.suggestion || 'Suggestion'}:</strong> ${this.escapeHtml(issue.suggestion)}</p>`;
            }

            $li.html(html);

            // Click/keyboard to focus field
            $li.on('click keypress', function(e) {
                if (e.type === 'keypress' && e.which !== 13) return;
                // Handled by delegated handler
            });

            return $li;
        },

        /**
         * Scroll to and highlight a field
         */
        scrollToField: function(fieldId) {
            // Try Structure Mode tree
            var $treeItem = $('.baf-tree-item[data-field-id="' + fieldId + '"]');
            if ($treeItem.length) {
                $treeItem[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $treeItem.addClass('baf-highlight');
                setTimeout(function() {
                    $treeItem.removeClass('baf-highlight');
                }, 2000);
                $treeItem.focus();
                return;
            }

            // Try Visual Builder card
            var $card = $('.baf-field-card[data-field-id="' + fieldId + '"]');
            if ($card.length) {
                $card[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $card.addClass('baf-highlight');
                setTimeout(function() {
                    $card.removeClass('baf-highlight');
                }, 2000);
                $card.focus();
                return;
            }

            this.announce('Field not found in current view');
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            var $announcer = $('#baf-announcer');
            if (!$announcer.length) {
                $announcer = $('<div id="baf-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>');
                $('body').append($announcer);
            }
            $announcer.text(message);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BAF_AccessibilityCoach.init();
    });

})(jQuery);
