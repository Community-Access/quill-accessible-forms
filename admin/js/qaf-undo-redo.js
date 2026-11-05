/**
 * QAF Undo/Redo System
 *
 * Provides undo/redo functionality for the form builder with version history.
 *
 * @package QAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * History Manager - Manages undo/redo stack
     */
    class HistoryManager {
        /**
         * Initialize history manager
         *
         * @param {Object} options Configuration options
         */
        constructor(options = {}) {
            this.maxStates = options.maxStates || 50;
            this.stack = [];
            this.currentIndex = -1;
            this.listeners = {
                change: [],
                save: []
            };
            this.isPaused = false;
            this.batchOperation = null;

            // Debounce for rapid changes
            this.saveTimeout = null;
            this.saveDelay = options.saveDelay || 500;
        }

        /**
         * Register event listener
         *
         * @param {string} event Event name
         * @param {Function} callback Callback function
         */
        on(event, callback) {
            if (this.listeners[event]) {
                this.listeners[event].push(callback);
            }
        }

        /**
         * Emit event to listeners
         *
         * @param {string} event Event name
         * @param {*} data Event data
         */
        emit(event, data) {
            if (this.listeners[event]) {
                this.listeners[event].forEach(cb => cb(data));
            }
        }

        /**
         * Push a new state to the history
         *
         * @param {Object} state State object
         * @param {string} description Human-readable description
         * @param {boolean} immediate Skip debounce
         */
        push(state, description = '', immediate = false) {
            if (this.isPaused) return;

            // If in batch operation, only keep the latest state
            if (this.batchOperation) {
                this.batchOperation.state = state;
                this.batchOperation.description = description;
                return;
            }

            // Clear debounce timeout
            if (this.saveTimeout) {
                clearTimeout(this.saveTimeout);
            }

            const doSave = () => {
                // Remove any future states if we're in the middle of history
                if (this.currentIndex < this.stack.length - 1) {
                    this.stack = this.stack.slice(0, this.currentIndex + 1);
                }

                // Create state entry
                const entry = {
                    state: this.cloneState(state),
                    description: description,
                    timestamp: Date.now()
                };

                // Add to stack
                this.stack.push(entry);
                this.currentIndex = this.stack.length - 1;

                // Trim old states if over limit
                while (this.stack.length > this.maxStates) {
                    this.stack.shift();
                    this.currentIndex--;
                }

                this.emit('save', entry);
                this.emit('change', this.getStatus());
            };

            if (immediate) {
                doSave();
            } else {
                this.saveTimeout = setTimeout(doSave, this.saveDelay);
            }
        }

        /**
         * Deep clone a state object
         *
         * @param {Object} state State to clone
         * @return {Object} Cloned state
         */
        cloneState(state) {
            return JSON.parse(JSON.stringify(state));
        }

        /**
         * Undo to previous state
         *
         * @return {Object|null} Previous state or null
         */
        undo() {
            if (!this.canUndo()) return null;

            this.currentIndex--;
            const entry = this.stack[this.currentIndex];
            
            this.emit('change', this.getStatus());
            return this.cloneState(entry.state);
        }

        /**
         * Redo to next state
         *
         * @return {Object|null} Next state or null
         */
        redo() {
            if (!this.canRedo()) return null;

            this.currentIndex++;
            const entry = this.stack[this.currentIndex];
            
            this.emit('change', this.getStatus());
            return this.cloneState(entry.state);
        }

        /**
         * Check if undo is available
         *
         * @return {boolean}
         */
        canUndo() {
            return this.currentIndex > 0;
        }

        /**
         * Check if redo is available
         *
         * @return {boolean}
         */
        canRedo() {
            return this.currentIndex < this.stack.length - 1;
        }

        /**
         * Get current status
         *
         * @return {Object} Status object
         */
        getStatus() {
            return {
                canUndo: this.canUndo(),
                canRedo: this.canRedo(),
                currentIndex: this.currentIndex,
                totalStates: this.stack.length,
                currentDescription: this.stack[this.currentIndex]?.description || '',
                undoDescription: this.stack[this.currentIndex - 1]?.description || '',
                redoDescription: this.stack[this.currentIndex + 1]?.description || ''
            };
        }

        /**
         * Get current state
         *
         * @return {Object|null} Current state
         */
        getCurrentState() {
            if (this.currentIndex < 0) return null;
            return this.cloneState(this.stack[this.currentIndex].state);
        }

        /**
         * Get version history
         *
         * @param {number} limit Maximum entries to return
         * @return {Array} History entries
         */
        getHistory(limit = 10) {
            const entries = [];
            const start = Math.max(0, this.currentIndex - limit);
            
            for (let i = start; i <= this.currentIndex; i++) {
                entries.push({
                    index: i,
                    description: this.stack[i].description,
                    timestamp: this.stack[i].timestamp,
                    isCurrent: i === this.currentIndex
                });
            }

            return entries.reverse();
        }

        /**
         * Jump to a specific state
         *
         * @param {number} index State index
         * @return {Object|null} State at index
         */
        jumpTo(index) {
            if (index < 0 || index >= this.stack.length) return null;

            this.currentIndex = index;
            this.emit('change', this.getStatus());
            return this.cloneState(this.stack[index].state);
        }

        /**
         * Pause history recording
         */
        pause() {
            this.isPaused = true;
        }

        /**
         * Resume history recording
         */
        resume() {
            this.isPaused = false;
        }

        /**
         * Start a batch operation (multiple changes as single undo)
         *
         * @param {string} description Batch description
         */
        startBatch(description = '') {
            this.batchOperation = {
                state: null,
                description: description
            };
        }

        /**
         * End batch operation and save
         */
        endBatch() {
            if (this.batchOperation && this.batchOperation.state) {
                const { state, description } = this.batchOperation;
                this.batchOperation = null;
                this.push(state, description, true);
            } else {
                this.batchOperation = null;
            }
        }

        /**
         * Clear all history
         */
        clear() {
            this.stack = [];
            this.currentIndex = -1;
            this.emit('change', this.getStatus());
        }
    }

    /**
     * QAF Undo/Redo UI Controller
     */
    class QAF_UndoRedo {
        /**
         * Initialize
         *
         * @param {Object} options Options
         */
        constructor(options = {}) {
            this.history = new HistoryManager({
                maxStates: options.maxStates || 50,
                saveDelay: options.saveDelay || 300
            });

            this.$container = $(options.container || '.qaf-form-builder');
            this.getState = options.getState || (() => ({}));
            this.setState = options.setState || (() => {});
            this.initialized = false;

            this.init();
        }

        /**
         * Initialize the controller
         */
        init() {
            this.createUI();
            this.bindEvents();
            this.initHistory();
        }

        /**
         * Create undo/redo UI elements
         */
        createUI() {
            // Toolbar buttons
            const $toolbar = this.$container.find('.qaf-builder-toolbar');
            
            if ($toolbar.length && !$toolbar.find('.qaf-undo-redo').length) {
                const $undoRedo = $(`
                    <div class="qaf-undo-redo" role="group" aria-label="${qafUndoRedoL10n.undoRedo || 'Undo/Redo'}">
                        <button type="button" class="qaf-undo-btn qaf-toolbar-btn" disabled
                            aria-label="${qafUndoRedoL10n.undo || 'Undo'}"
                            title="${qafUndoRedoL10n.undo || 'Undo'} (Ctrl+Z)">
                            <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="qaf-redo-btn qaf-toolbar-btn" disabled
                            aria-label="${qafUndoRedoL10n.redo || 'Redo'}"
                            title="${qafUndoRedoL10n.redo || 'Redo'} (Ctrl+Y)">
                            <span class="dashicons dashicons-redo" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="qaf-history-btn qaf-toolbar-btn"
                            aria-label="${qafUndoRedoL10n.history || 'Version History'}"
                            aria-haspopup="true"
                            title="${qafUndoRedoL10n.history || 'Version History'}">
                            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                        </button>
                    </div>
                `);
                
                $toolbar.prepend($undoRedo);
            }

            // History panel
            if (!$('#qaf-history-panel').length) {
                const $panel = $(`
                    <div id="qaf-history-panel" class="qaf-history-panel" hidden>
                        <div class="qaf-history-header">
                            <h3>${qafUndoRedoL10n.versionHistory || 'Version History'}</h3>
                            <button type="button" class="qaf-history-close" aria-label="${qafUndoRedoL10n.close || 'Close'}">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="qaf-history-list" role="list" aria-label="${qafUndoRedoL10n.versions || 'Versions'}">
                        </div>
                    </div>
                `);
                
                this.$container.append($panel);
            }

            this.$undoBtn = this.$container.find('.qaf-undo-btn');
            this.$redoBtn = this.$container.find('.qaf-redo-btn');
            this.$historyBtn = this.$container.find('.qaf-history-btn');
            this.$historyPanel = $('#qaf-history-panel');
            this.$historyList = this.$historyPanel.find('.qaf-history-list');
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Button clicks
            this.$undoBtn.on('click', () => this.undo());
            this.$redoBtn.on('click', () => this.redo());
            this.$historyBtn.on('click', () => this.toggleHistoryPanel());
            this.$historyPanel.find('.qaf-history-close').on('click', () => this.hideHistoryPanel());

            // History list clicks
            this.$historyList.on('click', '.qaf-history-item', (e) => {
                const index = $(e.currentTarget).data('index');
                this.jumpTo(index);
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                // Only when form builder is active/focused
                if (!this.$container.is(':visible')) return;
                
                // Ignore if in input/textarea
                if ($(e.target).is('input, textarea, select, [contenteditable]')) {
                    // Allow Ctrl+Z/Y in text inputs - browser handles that
                    return;
                }

                if (e.ctrlKey || e.metaKey) {
                    if (e.key === 'z' && !e.shiftKey) {
                        e.preventDefault();
                        this.undo();
                    } else if (e.key === 'y' || (e.key === 'z' && e.shiftKey)) {
                        e.preventDefault();
                        this.redo();
                    }
                }
            });

            // Click outside to close history panel
            $(document).on('click', (e) => {
                if (this.$historyPanel.is(':visible') &&
                    !$(e.target).closest('.qaf-history-panel, .qaf-history-btn').length) {
                    this.hideHistoryPanel();
                }
            });

            // History status changes
            this.history.on('change', (status) => this.updateUI(status));
        }

        /**
         * Initialize history with current state
         */
        initHistory() {
            const state = this.getState();
            if (state) {
                this.history.push(state, qafUndoRedoL10n.initialState || 'Initial state', true);
                this.initialized = true;
            }
        }

        /**
         * Record a state change
         *
         * @param {string} description Change description
         */
        recordChange(description = '') {
            if (!this.initialized) {
                this.initHistory();
                return;
            }

            const state = this.getState();
            if (state) {
                this.history.push(state, description);
            }
        }

        /**
         * Start batch operation
         *
         * @param {string} description Description
         */
        startBatch(description) {
            this.history.startBatch(description);
        }

        /**
         * End batch operation
         */
        endBatch() {
            this.history.endBatch();
        }

        /**
         * Perform undo
         */
        undo() {
            const state = this.history.undo();
            if (state) {
                this.applyState(state);
                this.announce(qafUndoRedoL10n.undone || 'Undone');
            }
        }

        /**
         * Perform redo
         */
        redo() {
            const state = this.history.redo();
            if (state) {
                this.applyState(state);
                this.announce(qafUndoRedoL10n.redone || 'Redone');
            }
        }

        /**
         * Jump to specific history state
         *
         * @param {number} index State index
         */
        jumpTo(index) {
            const state = this.history.jumpTo(index);
            if (state) {
                this.applyState(state);
                this.hideHistoryPanel();
                this.announce(qafUndoRedoL10n.restoredTo || 'Restored');
            }
        }

        /**
         * Apply a state
         *
         * @param {Object} state State to apply
         */
        applyState(state) {
            this.history.pause();
            this.setState(state);
            this.history.resume();
        }

        /**
         * Update UI based on history status
         *
         * @param {Object} status History status
         */
        updateUI(status) {
            // Update button states
            this.$undoBtn
                .prop('disabled', !status.canUndo)
                .attr('aria-disabled', !status.canUndo)
                .attr('title', status.canUndo 
                    ? `${qafUndoRedoL10n.undo || 'Undo'}: ${status.undoDescription} (Ctrl+Z)`
                    : `${qafUndoRedoL10n.nothingToUndo || 'Nothing to undo'}`
                );

            this.$redoBtn
                .prop('disabled', !status.canRedo)
                .attr('aria-disabled', !status.canRedo)
                .attr('title', status.canRedo
                    ? `${qafUndoRedoL10n.redo || 'Redo'}: ${status.redoDescription} (Ctrl+Y)`
                    : `${qafUndoRedoL10n.nothingToRedo || 'Nothing to redo'}`
                );

            // Update history panel if open
            if (this.$historyPanel.is(':visible')) {
                this.renderHistoryList();
            }
        }

        /**
         * Toggle history panel
         */
        toggleHistoryPanel() {
            if (this.$historyPanel.is(':visible')) {
                this.hideHistoryPanel();
            } else {
                this.showHistoryPanel();
            }
        }

        /**
         * Show history panel
         */
        showHistoryPanel() {
            this.renderHistoryList();
            this.$historyPanel.removeAttr('hidden').addClass('is-visible');
            this.$historyBtn.attr('aria-expanded', 'true');
        }

        /**
         * Hide history panel
         */
        hideHistoryPanel() {
            this.$historyPanel.attr('hidden', '').removeClass('is-visible');
            this.$historyBtn.attr('aria-expanded', 'false');
        }

        /**
         * Render history list
         */
        renderHistoryList() {
            const history = this.history.getHistory(20);
            let html = '';

            if (history.length === 0) {
                html = `<div class="qaf-history-empty">${qafUndoRedoL10n.noHistory || 'No history yet'}</div>`;
            } else {
                history.forEach(entry => {
                    const time = this.formatTime(entry.timestamp);
                    const current = entry.isCurrent ? ' is-current' : '';
                    
                    html += `
                        <div class="qaf-history-item${current}" 
                             role="listitem"
                             data-index="${entry.index}"
                             tabindex="0"
                             aria-current="${entry.isCurrent ? 'true' : 'false'}">
                            <span class="qaf-history-description">${this.escapeHtml(entry.description)}</span>
                            <span class="qaf-history-time">${time}</span>
                            ${entry.isCurrent ? `<span class="qaf-history-badge">${qafUndoRedoL10n.current || 'Current'}</span>` : ''}
                        </div>
                    `;
                });
            }

            this.$historyList.html(html);
        }

        /**
         * Format timestamp
         *
         * @param {number} timestamp Unix timestamp
         * @return {string} Formatted time
         */
        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = Date.now();
            const diff = (now - timestamp) / 1000; // seconds

            if (diff < 60) {
                return qafUndoRedoL10n.justNow || 'Just now';
            } else if (diff < 3600) {
                const mins = Math.floor(diff / 60);
                return mins === 1 
                    ? qafUndoRedoL10n.oneMinuteAgo || '1 minute ago'
                    : (qafUndoRedoL10n.minutesAgo || '%d minutes ago').replace('%d', mins);
            } else {
                return date.toLocaleTimeString(undefined, { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            }
        }

        /**
         * Escape HTML
         *
         * @param {string} str String to escape
         * @return {string} Escaped string
         */
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Announce message to screen readers
         *
         * @param {string} message Message
         */
        announce(message) {
            let $announcer = $('#qaf-undo-announcer');
            
            if (!$announcer.length) {
                $announcer = $('<div id="qaf-undo-announcer" role="status" aria-live="polite" class="screen-reader-text"></div>');
                $('body').append($announcer);
            }

            $announcer.text(message);
        }

        /**
         * Pause history recording
         */
        pause() {
            this.history.pause();
        }

        /**
         * Resume history recording
         */
        resume() {
            this.history.resume();
        }

        /**
         * Clear history
         */
        clear() {
            this.history.clear();
        }
    }

    // Expose to global scope
    window.QAF_HistoryManager = HistoryManager;
    window.QAF_UndoRedo = QAF_UndoRedo;

})(jQuery);

// Localization object
var qafUndoRedoL10n = qafUndoRedoL10n || {
    undo: 'Undo',
    redo: 'Redo',
    undoRedo: 'Undo/Redo',
    history: 'Version History',
    versionHistory: 'Version History',
    versions: 'Versions',
    close: 'Close',
    current: 'Current',
    noHistory: 'No history yet',
    initialState: 'Initial state',
    undone: 'Undone',
    redone: 'Redone',
    restoredTo: 'Restored',
    nothingToUndo: 'Nothing to undo',
    nothingToRedo: 'Nothing to redo',
    justNow: 'Just now',
    oneMinuteAgo: '1 minute ago',
    minutesAgo: '%d minutes ago'
};
