/**
 * BAF Searchable Select Component
 * 
 * Implements ARIA combobox pattern for accessible searchable/filterable select fields.
 * Fully keyboard accessible with screen reader support.
 * 
 * @package BAF
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * SearchableSelect class - transforms select elements into accessible comboboxes
     */
    class BAFSearchableSelect {
        /**
         * Constructor
         * @param {HTMLSelectElement} selectElement - The original select element
         * @param {Object} options - Configuration options
         */
        constructor(selectElement, options = {}) {
            this.select = selectElement;
            this.options = {
                minChars: options.minChars || 0,
                maxResults: options.maxResults || 100,
                noResultsText: options.noResultsText || 'No results found',
                loadingText: options.loadingText || 'Loading...',
                clearText: options.clearText || 'Clear selection',
                placeholder: options.placeholder || selectElement.getAttribute('placeholder') || 'Search...',
                announceResults: options.announceResults !== false,
                ...options
            };

            this.isOpen = false;
            this.highlightedIndex = -1;
            this.filteredOptions = [];
            this.originalOptions = [];
            this.isMultiple = selectElement.multiple;
            this.selectedValues = new Set();

            this.init();
        }

        /**
         * Initialize the searchable select
         */
        init() {
            // Store original options
            this.originalOptions = Array.from(this.select.options).map(opt => ({
                value: opt.value,
                label: opt.textContent,
                disabled: opt.disabled,
                selected: opt.selected,
                group: opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement.label : null
            }));

            // Initialize selected values
            this.originalOptions.forEach(opt => {
                if (opt.selected && opt.value) {
                    this.selectedValues.add(opt.value);
                }
            });

            // Build the component
            this.buildUI();
            this.bindEvents();
            this.updateDisplay();

            // Hide original select but keep it for form submission
            this.select.style.display = 'none';
            this.select.setAttribute('aria-hidden', 'true');
            this.select.tabIndex = -1;
        }

        /**
         * Build the combobox UI structure
         */
        buildUI() {
            // Create container
            this.container = document.createElement('div');
            this.container.className = 'baf-searchable-select';
            this.container.setAttribute('data-field-id', this.select.id);

            // Create input wrapper
            const inputWrapper = document.createElement('div');
            inputWrapper.className = 'baf-ss-input-wrapper';

            // Create text input
            this.input = document.createElement('input');
            this.input.type = 'text';
            this.input.className = 'baf-ss-input';
            this.input.setAttribute('role', 'combobox');
            this.input.setAttribute('aria-autocomplete', 'list');
            this.input.setAttribute('aria-expanded', 'false');
            this.input.setAttribute('aria-haspopup', 'listbox');
            this.input.setAttribute('autocomplete', 'off');
            this.input.setAttribute('autocapitalize', 'off');
            this.input.setAttribute('spellcheck', 'false');
            this.input.placeholder = this.options.placeholder;

            // Generate unique IDs
            const baseId = this.select.id || 'baf-ss-' + Math.random().toString(36).substr(2, 9);
            this.listboxId = baseId + '-listbox';
            this.input.setAttribute('aria-controls', this.listboxId);

            // Copy required/aria attributes from original select
            if (this.select.required) {
                this.input.required = true;
                this.input.setAttribute('aria-required', 'true');
            }
            if (this.select.getAttribute('aria-describedby')) {
                this.input.setAttribute('aria-describedby', this.select.getAttribute('aria-describedby'));
            }

            // Create clear button
            this.clearBtn = document.createElement('button');
            this.clearBtn.type = 'button';
            this.clearBtn.className = 'baf-ss-clear';
            this.clearBtn.setAttribute('aria-label', this.options.clearText);
            this.clearBtn.innerHTML = '<span aria-hidden="true">&times;</span>';
            this.clearBtn.hidden = true;

            // Create toggle button
            this.toggleBtn = document.createElement('button');
            this.toggleBtn.type = 'button';
            this.toggleBtn.className = 'baf-ss-toggle';
            this.toggleBtn.setAttribute('aria-label', 'Toggle options');
            this.toggleBtn.setAttribute('aria-expanded', 'false');
            this.toggleBtn.setAttribute('tabindex', '-1');
            this.toggleBtn.innerHTML = '<span class="baf-ss-arrow" aria-hidden="true"></span>';

            // Assemble input wrapper
            inputWrapper.appendChild(this.input);
            inputWrapper.appendChild(this.clearBtn);
            inputWrapper.appendChild(this.toggleBtn);

            // Create selected tags container (for multiple select)
            if (this.isMultiple) {
                this.tagsContainer = document.createElement('div');
                this.tagsContainer.className = 'baf-ss-tags';
                this.tagsContainer.setAttribute('aria-live', 'polite');
            }

            // Create listbox
            this.listbox = document.createElement('ul');
            this.listbox.id = this.listboxId;
            this.listbox.className = 'baf-ss-listbox';
            this.listbox.setAttribute('role', 'listbox');
            this.listbox.setAttribute('aria-label', 'Options');
            this.listbox.hidden = true;

            if (this.isMultiple) {
                this.listbox.setAttribute('aria-multiselectable', 'true');
            }

            // Create live region for announcements
            this.liveRegion = document.createElement('div');
            this.liveRegion.className = 'baf-sr-only';
            this.liveRegion.setAttribute('aria-live', 'polite');
            this.liveRegion.setAttribute('aria-atomic', 'true');

            // Assemble container
            if (this.isMultiple) {
                this.container.appendChild(this.tagsContainer);
            }
            this.container.appendChild(inputWrapper);
            this.container.appendChild(this.listbox);
            this.container.appendChild(this.liveRegion);

            // Insert into DOM
            this.select.parentNode.insertBefore(this.container, this.select.nextSibling);
        }

        /**
         * Bind all event listeners
         */
        bindEvents() {
            // Input events
            this.input.addEventListener('input', this.handleInput.bind(this));
            this.input.addEventListener('focus', this.handleFocus.bind(this));
            this.input.addEventListener('blur', this.handleBlur.bind(this));
            this.input.addEventListener('keydown', this.handleKeydown.bind(this));

            // Button events
            this.clearBtn.addEventListener('click', this.handleClear.bind(this));
            this.toggleBtn.addEventListener('click', this.handleToggle.bind(this));

            // Listbox events (delegated)
            this.listbox.addEventListener('click', this.handleOptionClick.bind(this));
            this.listbox.addEventListener('mouseenter', this.handleOptionHover.bind(this), true);

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.close();
                }
            });

            // Sync with original select changes
            this.select.addEventListener('change', () => {
                this.syncFromSelect();
            });
        }

        /**
         * Handle text input
         */
        handleInput() {
            const query = this.input.value.toLowerCase().trim();

            if (query.length >= this.options.minChars || query.length === 0) {
                this.filterOptions(query);
                this.open();
            }

            this.updateClearButton();
        }

        /**
         * Handle input focus
         */
        handleFocus() {
            if (this.options.minChars === 0) {
                this.filterOptions('');
                this.open();
            }
        }

        /**
         * Handle input blur
         */
        handleBlur(e) {
            // Delay to allow click events on listbox
            setTimeout(() => {
                if (!this.container.contains(document.activeElement)) {
                    this.close();
                    
                    // For single select, restore display value if nothing selected
                    if (!this.isMultiple && this.selectedValues.size === 0) {
                        this.input.value = '';
                    }
                }
            }, 150);
        }

        /**
         * Handle keyboard navigation
         */
        handleKeydown(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.filterOptions(this.input.value.toLowerCase().trim());
                        this.open();
                    } else {
                        this.highlightNext();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (this.isOpen) {
                        this.highlightPrev();
                    }
                    break;

                case 'Enter':
                    if (this.isOpen && this.highlightedIndex >= 0) {
                        e.preventDefault();
                        this.selectHighlighted();
                    }
                    break;

                case 'Escape':
                    if (this.isOpen) {
                        e.preventDefault();
                        this.close();
                        this.input.focus();
                    }
                    break;

                case 'Tab':
                    this.close();
                    break;

                case 'Home':
                    if (this.isOpen) {
                        e.preventDefault();
                        this.highlightFirst();
                    }
                    break;

                case 'End':
                    if (this.isOpen) {
                        e.preventDefault();
                        this.highlightLast();
                    }
                    break;

                case 'Backspace':
                    // Remove last tag in multiple mode
                    if (this.isMultiple && this.input.value === '' && this.selectedValues.size > 0) {
                        const lastValue = Array.from(this.selectedValues).pop();
                        this.deselectValue(lastValue);
                    }
                    break;
            }
        }

        /**
         * Handle clear button click
         */
        handleClear(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.clearSelection();
            this.input.focus();
        }

        /**
         * Handle toggle button click
         */
        handleToggle(e) {
            e.preventDefault();
            e.stopPropagation();

            if (this.isOpen) {
                this.close();
            } else {
                this.filterOptions('');
                this.open();
            }
            this.input.focus();
        }

        /**
         * Handle option click
         */
        handleOptionClick(e) {
            const option = e.target.closest('[role="option"]');
            if (option && !option.getAttribute('aria-disabled')) {
                const value = option.dataset.value;
                this.selectValue(value);
            }
        }

        /**
         * Handle option hover
         */
        handleOptionHover(e) {
            const option = e.target.closest('[role="option"]');
            if (option) {
                const index = Array.from(this.listbox.querySelectorAll('[role="option"]')).indexOf(option);
                if (index >= 0) {
                    this.setHighlight(index);
                }
            }
        }

        /**
         * Filter options based on query
         */
        filterOptions(query) {
            this.filteredOptions = this.originalOptions.filter(opt => {
                if (!opt.value) return false; // Skip placeholder
                if (opt.disabled) return false;
                
                return opt.label.toLowerCase().includes(query);
            });

            // Limit results
            if (this.filteredOptions.length > this.options.maxResults) {
                this.filteredOptions = this.filteredOptions.slice(0, this.options.maxResults);
            }

            this.renderOptions();
            this.highlightedIndex = -1;

            // Announce results count
            if (this.options.announceResults) {
                const count = this.filteredOptions.length;
                if (count === 0) {
                    this.announce(this.options.noResultsText);
                } else {
                    this.announce(`${count} option${count !== 1 ? 's' : ''} available`);
                }
            }
        }

        /**
         * Render filtered options in listbox
         */
        renderOptions() {
            this.listbox.innerHTML = '';

            if (this.filteredOptions.length === 0) {
                const noResults = document.createElement('li');
                noResults.className = 'baf-ss-no-results';
                noResults.setAttribute('role', 'presentation');
                noResults.textContent = this.options.noResultsText;
                this.listbox.appendChild(noResults);
                return;
            }

            let currentGroup = null;

            this.filteredOptions.forEach((opt, index) => {
                // Handle optgroups
                if (opt.group && opt.group !== currentGroup) {
                    currentGroup = opt.group;
                    const groupEl = document.createElement('li');
                    groupEl.className = 'baf-ss-optgroup';
                    groupEl.setAttribute('role', 'presentation');
                    groupEl.textContent = opt.group;
                    this.listbox.appendChild(groupEl);
                }

                const li = document.createElement('li');
                li.id = `${this.listboxId}-option-${index}`;
                li.className = 'baf-ss-option';
                li.setAttribute('role', 'option');
                li.dataset.value = opt.value;

                const isSelected = this.selectedValues.has(opt.value);
                li.setAttribute('aria-selected', isSelected.toString());
                
                if (isSelected) {
                    li.classList.add('baf-ss-option--selected');
                }

                // For multiple select, add checkbox indicator
                if (this.isMultiple) {
                    const checkbox = document.createElement('span');
                    checkbox.className = 'baf-ss-checkbox';
                    checkbox.setAttribute('aria-hidden', 'true');
                    checkbox.innerHTML = isSelected ? '☑' : '☐';
                    li.appendChild(checkbox);
                }

                const label = document.createElement('span');
                label.className = 'baf-ss-option-label';
                label.textContent = opt.label;
                li.appendChild(label);

                this.listbox.appendChild(li);
            });
        }

        /**
         * Open the listbox
         */
        open() {
            if (this.isOpen) return;

            this.isOpen = true;
            this.listbox.hidden = false;
            this.input.setAttribute('aria-expanded', 'true');
            this.toggleBtn.setAttribute('aria-expanded', 'true');
            this.container.classList.add('baf-ss--open');
        }

        /**
         * Close the listbox
         */
        close() {
            if (!this.isOpen) return;

            this.isOpen = false;
            this.listbox.hidden = true;
            this.input.setAttribute('aria-expanded', 'false');
            this.toggleBtn.setAttribute('aria-expanded', 'false');
            this.input.removeAttribute('aria-activedescendant');
            this.container.classList.remove('baf-ss--open');
            this.clearHighlight();
        }

        /**
         * Select a value
         */
        selectValue(value) {
            const option = this.originalOptions.find(o => o.value === value);
            if (!option) return;

            if (this.isMultiple) {
                // Toggle selection for multiple
                if (this.selectedValues.has(value)) {
                    this.deselectValue(value);
                } else {
                    this.selectedValues.add(value);
                    this.announce(`${option.label} selected`);
                }
                this.input.value = '';
            } else {
                // Replace selection for single
                this.selectedValues.clear();
                this.selectedValues.add(value);
                this.input.value = option.label;
                this.close();
                this.announce(`${option.label} selected`);
            }

            this.syncToSelect();
            this.updateDisplay();
            this.renderOptions(); // Update selected states
        }

        /**
         * Deselect a value
         */
        deselectValue(value) {
            const option = this.originalOptions.find(o => o.value === value);
            this.selectedValues.delete(value);
            
            if (option) {
                this.announce(`${option.label} removed`);
            }
            
            this.syncToSelect();
            this.updateDisplay();
            this.renderOptions();
        }

        /**
         * Clear all selections
         */
        clearSelection() {
            this.selectedValues.clear();
            this.input.value = '';
            this.syncToSelect();
            this.updateDisplay();
            this.announce('Selection cleared');
        }

        /**
         * Select the currently highlighted option
         */
        selectHighlighted() {
            if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredOptions.length) {
                const option = this.filteredOptions[this.highlightedIndex];
                this.selectValue(option.value);
            }
        }

        /**
         * Highlight next option
         */
        highlightNext() {
            const enabledOptions = this.filteredOptions.filter(o => !o.disabled);
            if (enabledOptions.length === 0) return;

            let nextIndex = this.highlightedIndex + 1;
            if (nextIndex >= this.filteredOptions.length) {
                nextIndex = 0;
            }
            this.setHighlight(nextIndex);
        }

        /**
         * Highlight previous option
         */
        highlightPrev() {
            const enabledOptions = this.filteredOptions.filter(o => !o.disabled);
            if (enabledOptions.length === 0) return;

            let prevIndex = this.highlightedIndex - 1;
            if (prevIndex < 0) {
                prevIndex = this.filteredOptions.length - 1;
            }
            this.setHighlight(prevIndex);
        }

        /**
         * Highlight first option
         */
        highlightFirst() {
            if (this.filteredOptions.length > 0) {
                this.setHighlight(0);
            }
        }

        /**
         * Highlight last option
         */
        highlightLast() {
            if (this.filteredOptions.length > 0) {
                this.setHighlight(this.filteredOptions.length - 1);
            }
        }

        /**
         * Set highlight to specific index
         */
        setHighlight(index) {
            this.clearHighlight();
            
            if (index < 0 || index >= this.filteredOptions.length) return;

            this.highlightedIndex = index;
            const optionEl = this.listbox.querySelector(`#${this.listboxId}-option-${index}`);
            
            if (optionEl) {
                optionEl.classList.add('baf-ss-option--highlighted');
                this.input.setAttribute('aria-activedescendant', optionEl.id);
                
                // Scroll into view
                optionEl.scrollIntoView({ block: 'nearest' });
            }
        }

        /**
         * Clear highlight
         */
        clearHighlight() {
            const highlighted = this.listbox.querySelector('.baf-ss-option--highlighted');
            if (highlighted) {
                highlighted.classList.remove('baf-ss-option--highlighted');
            }
            this.highlightedIndex = -1;
        }

        /**
         * Update display (clear button, tags)
         */
        updateDisplay() {
            this.updateClearButton();
            
            if (this.isMultiple) {
                this.updateTags();
            }
        }

        /**
         * Update clear button visibility
         */
        updateClearButton() {
            const hasValue = this.selectedValues.size > 0 || this.input.value.length > 0;
            this.clearBtn.hidden = !hasValue;
        }

        /**
         * Update selected tags (for multiple select)
         */
        updateTags() {
            if (!this.tagsContainer) return;

            this.tagsContainer.innerHTML = '';

            this.selectedValues.forEach(value => {
                const option = this.originalOptions.find(o => o.value === value);
                if (!option) return;

                const tag = document.createElement('span');
                tag.className = 'baf-ss-tag';

                const tagLabel = document.createElement('span');
                tagLabel.className = 'baf-ss-tag-label';
                tagLabel.textContent = option.label;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'baf-ss-tag-remove';
                removeBtn.setAttribute('aria-label', `Remove ${option.label}`);
                removeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.deselectValue(value);
                    this.input.focus();
                });

                tag.appendChild(tagLabel);
                tag.appendChild(removeBtn);
                this.tagsContainer.appendChild(tag);
            });
        }

        /**
         * Sync selections to original select element
         */
        syncToSelect() {
            Array.from(this.select.options).forEach(opt => {
                opt.selected = this.selectedValues.has(opt.value);
            });

            // Dispatch change event
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        /**
         * Sync from original select element
         */
        syncFromSelect() {
            this.selectedValues.clear();
            
            Array.from(this.select.options).forEach(opt => {
                if (opt.selected && opt.value) {
                    this.selectedValues.add(opt.value);
                }
            });

            this.updateDisplay();
            
            // Update input display for single select
            if (!this.isMultiple && this.selectedValues.size === 1) {
                const value = Array.from(this.selectedValues)[0];
                const option = this.originalOptions.find(o => o.value === value);
                if (option) {
                    this.input.value = option.label;
                }
            }
        }

        /**
         * Announce message to screen readers
         */
        announce(message) {
            this.liveRegion.textContent = message;
            // Clear after announcement
            setTimeout(() => {
                this.liveRegion.textContent = '';
            }, 1000);
        }

        /**
         * Destroy the component and restore original select
         */
        destroy() {
            this.container.remove();
            this.select.style.display = '';
            this.select.removeAttribute('aria-hidden');
            this.select.tabIndex = 0;
        }
    }

    /**
     * Initialize searchable selects on page load
     */
    function initSearchableSelects() {
        const selects = document.querySelectorAll('select.baf-select-searchable');
        
        selects.forEach(select => {
            // Skip if already initialized
            if (select.dataset.bafSearchableInit) return;
            select.dataset.bafSearchableInit = 'true';

            // Get options from data attributes
            const options = {
                minChars: parseInt(select.dataset.minChars) || 0,
                maxResults: parseInt(select.dataset.maxResults) || 100,
                noResultsText: select.dataset.noResultsText,
                placeholder: select.getAttribute('placeholder')
            };

            new BAFSearchableSelect(select, options);
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchableSelects);
    } else {
        initSearchableSelects();
    }

    // Re-initialize on AJAX form loads
    document.addEventListener('baf:form:loaded', initSearchableSelects);

    // Expose for manual initialization
    window.BAFSearchableSelect = BAFSearchableSelect;
    window.bafInitSearchableSelects = initSearchableSelects;

})();
