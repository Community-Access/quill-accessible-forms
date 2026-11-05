/**
 * QAF Accessibility Coach
 *
 * Real-time accessibility guidance in the form builder.
 *
 * @package QAF
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Accessibility Coach Controller
     */
    window.QAF_AccessibilityCoach = {
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
                <div id="qaf-accessibility-coach" class="qaf-coach-panel" role="complementary" aria-labelledby="qaf-coach-title" hidden>
                    <div class="qaf-coach-header">
                        <h3 id="qaf-coach-title">
                            <span aria-hidden="true">♿</span>
                            ${qafBuilder.i18n.accessibilityCoach || 'Accessibility Coach'}
                        </h3>
                        <div class="qaf-coach-actions">
                            <button type="button" class="qaf-coach-refresh" aria-label="${qafBuilder.i18n.refreshAnalysis || 'Refresh analysis'}">
                                <span aria-hidden="true">🔄</span>
                            </button>
                            <button type="button" class="qaf-coach-close" aria-label="${qafBuilder.i18n.closeCoach || 'Close accessibility coach'}">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="qaf-coach-score" aria-live="polite">
                        <div class="qaf-score-circle">
                            <span class="qaf-score-value">--</span>
                        </div>
                        <div class="qaf-score-label">${qafBuilder.i18n.accessibilityScore || 'Accessibility Score'}</div>
                    </div>
                    
                    <div class="qaf-coach-summary" aria-live="polite">
                        <span class="qaf-summary-errors"><strong>0</strong> ${qafBuilder.i18n.errors || 'Errors'}</span>
                        <span class="qaf-summary-warnings"><strong>0</strong> ${qafBuilder.i18n.warnings || 'Warnings'}</span>
                        <span class="qaf-summary-info"><strong>0</strong> ${qafBuilder.i18n.suggestions || 'Suggestions'}</span>
                    </div>
                    
                    <div class="qaf-coach-body">
                        <div class="qaf-coach-loading" hidden>
                            <span class="qaf-spinner" aria-hidden="true"></span>
                            <span>${qafBuilder.i18n.analyzing || 'Analyzing...'}</span>
                        </div>
                        
                        <div class="qaf-coach-empty" hidden>
                            <span aria-hidden="true">✅</span>
                            <p>${qafBuilder.i18n.noIssuesFound || 'No accessibility issues found. Great job!'}</p>
                        </div>
                        
                        <div class="qaf-coach-issues">
                            <!-- Issues will be rendered here -->
                        </div>
                    </div>
                    
                    <div class="qaf-coach-footer">
                        <label class="qaf-coach-auto-analyze">
                            <input type="checkbox" id="qaf-auto-analyze">
                            ${qafBuilder.i18n.autoAnalyze || 'Auto-analyze on changes'}
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
            $(document).on('click', '#qaf-toggle-coach, .qaf-toggle-coach', function(e) {
                e.preventDefault();
                self.toggle();
            });

            // Close button
            this.$panel.on('click', '.qaf-coach-close', function() {
                self.hide();
            });

            // Refresh button
            this.$panel.on('click', '.qaf-coach-refresh', function() {
                self.analyze();
            });

            // Auto-analyze toggle
            this.$panel.on('change', '#qaf-auto-analyze', function() {
                self.setAutoAnalyze($(this).is(':checked'));
            });

            // Issue click - scroll to field
            this.$panel.on('click', '.qaf-coach-issue', function() {
                var fieldId = $(this).data('field-id');
                if (fieldId && fieldId !== 'form') {
                    self.scrollToField(fieldId);
                }
            });

            // Listen for form changes if auto-analyze is on
            $(document).on('qaf:field:added qaf:field:removed qaf:field:changed', function() {
                if (self.enabled && $('#qaf-auto-analyze').is(':checked')) {
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
            var autoAnalyze = localStorage.getItem('qaf_coach_auto_analyze');
            if (autoAnalyze === 'true') {
                this.$panel.find('#qaf-auto-analyze').prop('checked', true);
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
            this.announce(qafBuilder.i18n.coachOpened || 'Accessibility coach opened');
        },

        /**
         * Hide the panel
         */
        hide: function() {
            this.enabled = false;
            this.$panel.attr('hidden', true);
            this.announce(qafBuilder.i18n.coachClosed || 'Accessibility coach closed');
        },

        /**
         * Set auto-analyze preference
         */
        setAutoAnalyze: function(enabled) {
            localStorage.setItem('qaf_coach_auto_analyze', enabled ? 'true' : 'false');
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
                url: qafBuilder.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'qaf_analyze_accessibility',
                    nonce: qafBuilder.nonce,
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
                    self.showError(qafBuilder.i18n.analyzeError || 'Analysis failed');
                }
            });
        },

        /**
         * Get current form configuration
         */
        getFormConfig: function() {
            // Try different sources
            if (window.qafFormConfig) {
                return window.qafFormConfig;
            }

            if (window.qafBuilderData) {
                return window.qafBuilderData;
            }

            // Try to build from DOM
            return null;
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            this.$panel.find('.qaf-coach-loading').removeAttr('hidden');
            this.$panel.find('.qaf-coach-empty').attr('hidden', true);
            this.$panel.find('.qaf-coach-issues').empty();
        },

        /**
         * Show empty state
         */
        showEmpty: function() {
            this.$panel.find('.qaf-coach-loading').attr('hidden', true);
            this.$panel.find('.qaf-coach-empty').removeAttr('hidden');
            this.$panel.find('.qaf-coach-issues').empty();
            this.updateScore(100);
            this.updateSummary({ errors: 0, warnings: 0, info: 0 });
        },

        /**
         * Show error
         */
        showError: function(message) {
            this.$panel.find('.qaf-coach-loading').attr('hidden', true);
            this.$panel.find('.qaf-coach-issues').html(
                '<div class="qaf-coach-error">' + this.escapeHtml(message) + '</div>'
            );
        },

        /**
         * Render analysis results
         */
        renderResults: function(data) {
            this.$panel.find('.qaf-coach-loading').attr('hidden', true);

            // Update score
            this.updateScore(data.score);

            // Update summary
            this.updateSummary(data.summary);

            // Render issues
            if (data.issues.length === 0) {
                this.showEmpty();
            } else {
                this.$panel.find('.qaf-coach-empty').attr('hidden', true);
                this.renderIssues(data.issues, data.categories);
            }

            // Announce results
            this.announce(data.summary.total + ' accessibility issues found. Score: ' + data.score);
        },

        /**
         * Update score display
         */
        updateScore: function(score) {
            var $circle = this.$panel.find('.qaf-score-circle');
            var $value = this.$panel.find('.qaf-score-value');

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
            this.$panel.find('.qaf-summary-errors strong').text(summary.errors);
            this.$panel.find('.qaf-summary-warnings strong').text(summary.warnings);
            this.$panel.find('.qaf-summary-info strong').text(summary.info);
        },

        /**
         * Render issues list
         */
        renderIssues: function(issues, categories) {
            var $container = this.$panel.find('.qaf-coach-issues');
            $container.empty();

            // Group by category
            if (categories) {
                for (var catId in categories) {
                    var category = categories[catId];
                    var $section = $('<div class="qaf-coach-category"></div>');
                    $section.append('<h4>' + this.escapeHtml(category.label) + '</h4>');

                    var $list = $('<ul class="qaf-issue-list" role="list"></ul>');
                    category.issues.forEach(function(issue) {
                        $list.append(this.renderIssue(issue));
                    }, this);

                    $section.append($list);
                    $container.append($section);
                }
            } else {
                // Flat list
                var $list = $('<ul class="qaf-issue-list" role="list"></ul>');
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

            var $li = $('<li class="qaf-coach-issue" role="listitem"></li>');
            $li.addClass('severity-' + issue.severity);
            $li.attr('data-field-id', issue.field_id);
            $li.attr('tabindex', '0');

            var html = `
                <div class="qaf-issue-header">
                    <span class="qaf-issue-icon" aria-hidden="true">${severityIcon[issue.severity] || ''}</span>
                    <span class="qaf-issue-title">${this.escapeHtml(issue.title)}</span>
                    <span class="qaf-issue-field">${this.escapeHtml(issue.field_id)}</span>
                </div>
                <p class="qaf-issue-message">${this.escapeHtml(issue.message)}</p>
            `;

            if (issue.suggestion) {
                html += `<p class="qaf-issue-suggestion"><strong>${qafBuilder.i18n.suggestion || 'Suggestion'}:</strong> ${this.escapeHtml(issue.suggestion)}</p>`;
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
            var $treeItem = $('.qaf-tree-item[data-field-id="' + fieldId + '"]');
            if ($treeItem.length) {
                $treeItem[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $treeItem.addClass('qaf-highlight');
                setTimeout(function() {
                    $treeItem.removeClass('qaf-highlight');
                }, 2000);
                $treeItem.focus();
                return;
            }

            // Try Visual Builder card
            var $card = $('.qaf-field-card[data-field-id="' + fieldId + '"]');
            if ($card.length) {
                $card[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $card.addClass('qaf-highlight');
                setTimeout(function() {
                    $card.removeClass('qaf-highlight');
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
        QAF_AccessibilityCoach.init();
    });

})(jQuery);
