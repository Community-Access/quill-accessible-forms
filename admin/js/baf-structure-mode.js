/**
 * BITS Accessible Forms - Structure Mode JavaScript
 * 
 * Industry-first keyboard tree navigation for form organization.
 * Full WCAG 2.2 AA compliance with arrow key navigation, 
 * move operations, and roving tabindex.
 * 
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Structure Mode Controller - Keyboard-First Tree View
     */
    const BAFStructureMode = {
        container: null,
        tree: null,
        announcer: null,
        currentItem: null,
        
        /**
         * Initialize structure mode
         */
        init: function() {
            this.container = document.getElementById('baf-structure-mode');
            if (!this.container) return;
            
            this.tree = this.container.querySelector('[role="tree"]');
            this.announcer = window.BAFAnnouncer ? new window.BAFAnnouncer() : null;
            
            this.bindEvents();
            this.initRovingTabindex();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tree keyboard navigation
            $(this.tree).on('keydown', '[role="treeitem"]', this.handleTreeKeydown.bind(this));
            $(this.tree).on('click', '[role="treeitem"]', this.handleTreeClick.bind(this));
            $(this.tree).on('focus', '[role="treeitem"]', this.handleTreeFocus.bind(this));
            
            // Expand/collapse toggle
            $(this.tree).on('click', '.baf-tree-toggle', this.toggleExpand.bind(this));
            
            // Keyboard shortcuts help
            $(document).on('click', '.baf-keyboard-help-toggle', this.toggleKeyboardHelp.bind(this));
        },
        
        /**
         * Initialize roving tabindex for tree items
         */
        initRovingTabindex: function() {
            const items = this.tree.querySelectorAll('[role="treeitem"]');
            items.forEach((item, index) => {
                item.setAttribute('tabindex', index === 0 ? '0' : '-1');
            });
            
            if (items.length > 0) {
                this.currentItem = items[0];
            }
        },
        
        /**
         * Handle tree keyboard navigation
         */
        handleTreeKeydown: function(e) {
            const $item = $(e.currentTarget);
            const item = e.currentTarget;
            
            let handled = true;
            
            switch (e.key) {
                case 'ArrowDown':
                    this.focusNextItem(item);
                    break;
                    
                case 'ArrowUp':
                    this.focusPreviousItem(item);
                    break;
                    
                case 'ArrowRight':
                    if (this.isExpandable(item)) {
                        if (!this.isExpanded(item)) {
                            this.expand(item);
                        } else {
                            this.focusFirstChild(item);
                        }
                    }
                    break;
                    
                case 'ArrowLeft':
                    if (this.isExpanded(item)) {
                        this.collapse(item);
                    } else {
                        this.focusParent(item);
                    }
                    break;
                    
                case 'Home':
                    this.focusFirst();
                    break;
                    
                case 'End':
                    this.focusLast();
                    break;
                    
                case 'Enter':
                case ' ':
                    if (this.isExpandable(item)) {
                        this.toggleExpand({ currentTarget: item });
                    } else {
                        this.selectItem(item);
                    }
                    break;
                    
                default:
                    // Move operations with Ctrl/Cmd
                    if (e.ctrlKey || e.metaKey) {
                        switch (e.key) {
                            case 'ArrowUp':
                                this.moveItemUp(item);
                                break;
                            case 'ArrowDown':
                                this.moveItemDown(item);
                                break;
                            case 'ArrowLeft':
                                this.outdentItem(item);
                                break;
                            case 'ArrowRight':
                                this.indentItem(item);
                                break;
                            default:
                                handled = false;
                        }
                    } else {
                        handled = false;
                    }
            }
            
            if (handled) {
                e.preventDefault();
                e.stopPropagation();
            }
        },
        
        /**
         * Handle tree item click
         */
        handleTreeClick: function(e) {
            const item = e.currentTarget;
            this.setFocus(item);
            this.selectItem(item);
        },
        
        /**
         * Handle tree item focus
         */
        handleTreeFocus: function(e) {
            this.currentItem = e.currentTarget;
        },
        
        /**
         * Set focus to a tree item with roving tabindex
         */
        setFocus: function(item) {
            // Remove tabindex from previous item
            const items = this.tree.querySelectorAll('[role="treeitem"]');
            items.forEach(i => i.setAttribute('tabindex', '-1'));
            
            // Set tabindex and focus on new item
            item.setAttribute('tabindex', '0');
            item.focus();
            this.currentItem = item;
        },
        
        /**
         * Focus next visible item
         */
        focusNextItem: function(item) {
            const visibleItems = this.getVisibleItems();
            const currentIndex = visibleItems.indexOf(item);
            
            if (currentIndex < visibleItems.length - 1) {
                this.setFocus(visibleItems[currentIndex + 1]);
            }
        },
        
        /**
         * Focus previous visible item
         */
        focusPreviousItem: function(item) {
            const visibleItems = this.getVisibleItems();
            const currentIndex = visibleItems.indexOf(item);
            
            if (currentIndex > 0) {
                this.setFocus(visibleItems[currentIndex - 1]);
            }
        },
        
        /**
         * Focus first item
         */
        focusFirst: function() {
            const visibleItems = this.getVisibleItems();
            if (visibleItems.length > 0) {
                this.setFocus(visibleItems[0]);
            }
        },
        
        /**
         * Focus last item
         */
        focusLast: function() {
            const visibleItems = this.getVisibleItems();
            if (visibleItems.length > 0) {
                this.setFocus(visibleItems[visibleItems.length - 1]);
            }
        },
        
        /**
         * Focus first child of expandable item
         */
        focusFirstChild: function(item) {
            const group = item.querySelector('[role="group"]');
            if (group) {
                const firstChild = group.querySelector('[role="treeitem"]');
                if (firstChild) {
                    this.setFocus(firstChild);
                }
            }
        },
        
        /**
         * Focus parent item
         */
        focusParent: function(item) {
            const $item = $(item);
            const $parentGroup = $item.parent('[role="group"]');
            if ($parentGroup.length) {
                const parentItem = $parentGroup.closest('[role="treeitem"]')[0];
                if (parentItem) {
                    this.setFocus(parentItem);
                }
            }
        },
        
        /**
         * Get all visible tree items (not in collapsed groups)
         */
        getVisibleItems: function() {
            const items = [];
            const walk = (container) => {
                const children = container.querySelectorAll(':scope > [role="treeitem"], :scope > li > [role="treeitem"]');
                children.forEach(item => {
                    items.push(item);
                    if (this.isExpanded(item)) {
                        const group = item.querySelector('[role="group"]');
                        if (group) {
                            walk(group);
                        }
                    }
                });
            };
            walk(this.tree);
            return items;
        },
        
        /**
         * Check if item is expandable
         */
        isExpandable: function(item) {
            return item.hasAttribute('aria-expanded');
        },
        
        /**
         * Check if item is expanded
         */
        isExpanded: function(item) {
            return item.getAttribute('aria-expanded') === 'true';
        },
        
        /**
         * Expand item
         */
        expand: function(item) {
            item.setAttribute('aria-expanded', 'true');
            const group = item.querySelector('[role="group"]');
            if (group) {
                group.hidden = false;
            }
            this.announceExpand(item);
        },
        
        /**
         * Collapse item
         */
        collapse: function(item) {
            item.setAttribute('aria-expanded', 'false');
            const group = item.querySelector('[role="group"]');
            if (group) {
                group.hidden = true;
            }
            this.announceCollapse(item);
        },
        
        /**
         * Toggle expand/collapse
         */
        toggleExpand: function(e) {
            const item = e.currentTarget.closest ? e.currentTarget.closest('[role="treeitem"]') : e.currentTarget;
            if (!item || !this.isExpandable(item)) return;
            
            if (this.isExpanded(item)) {
                this.collapse(item);
            } else {
                this.expand(item);
            }
        },
        
        /**
         * Select item
         */
        selectItem: function(item) {
            // Remove selection from all items
            this.tree.querySelectorAll('[role="treeitem"]').forEach(i => {
                i.setAttribute('aria-selected', 'false');
                i.classList.remove('baf-tree-selected');
            });
            
            // Select current item
            item.setAttribute('aria-selected', 'true');
            item.classList.add('baf-tree-selected');
            
            // Trigger event for field settings loading
            const fieldId = item.dataset.fieldId;
            $(document).trigger('baf:fieldSelected', { fieldId });
        },
        
        /**
         * Move item up in the tree
         */
        moveItemUp: function(item) {
            const $item = $(item).closest('li');
            const $prev = $item.prev('li');
            
            if (!$prev.length) {
                this.announce(bafL10n.alreadyAtTop);
                return;
            }
            
            $item.insertBefore($prev);
            this.setFocus(item);
            this.saveOrder();
            
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announceMove(label, 'up');
        },
        
        /**
         * Move item down in the tree
         */
        moveItemDown: function(item) {
            const $item = $(item).closest('li');
            const $next = $item.next('li');
            
            if (!$next.length) {
                this.announce(bafL10n.alreadyAtBottom);
                return;
            }
            
            $item.insertAfter($next);
            this.setFocus(item);
            this.saveOrder();
            
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announceMove(label, 'down');
        },
        
        /**
         * Indent item (make child of previous sibling)
         */
        indentItem: function(item) {
            const $item = $(item).closest('li');
            const $prev = $item.prev('li');
            
            if (!$prev.length) {
                this.announce(bafL10n.cannotIndent);
                return;
            }
            
            // Find or create group in previous sibling
            const prevItem = $prev.find('> [role="treeitem"]')[0];
            let group = prevItem.querySelector('[role="group"]');
            
            if (!group) {
                group = document.createElement('ul');
                group.setAttribute('role', 'group');
                prevItem.appendChild(group);
                prevItem.setAttribute('aria-expanded', 'true');
            }
            
            $(group).append($item);
            this.setFocus(item);
            this.saveOrder();
            
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announce(`${label} moved into group`);
        },
        
        /**
         * Outdent item (move to parent level)
         */
        outdentItem: function(item) {
            const $item = $(item).closest('li');
            const $group = $item.parent('[role="group"]');
            
            if (!$group.length || $group.parent().is(this.tree)) {
                this.announce(bafL10n.cannotOutdent);
                return;
            }
            
            const $parentLi = $group.closest('li');
            $item.insertAfter($parentLi);
            
            // Clean up empty groups
            if ($group.children().length === 0) {
                const parentItem = $parentLi.find('> [role="treeitem"]')[0];
                parentItem.removeAttribute('aria-expanded');
                $group.remove();
            }
            
            this.setFocus(item);
            this.saveOrder();
            
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announce(`${label} moved out of group`);
        },
        
        /**
         * Save tree order to server
         */
        saveOrder: function() {
            const order = this.getTreeOrder(this.tree);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baf_save_structure_order',
                    form_id: this.getFormId(),
                    order: JSON.stringify(order),
                    nonce: bafL10n.nonce
                }
            });
        },
        
        /**
         * Get nested tree order structure
         */
        getTreeOrder: function(container) {
            const order = [];
            const items = container.querySelectorAll(':scope > li');
            
            items.forEach(li => {
                const item = li.querySelector(':scope > [role="treeitem"]');
                const group = li.querySelector('[role="group"]');
                
                const entry = {
                    id: item.dataset.fieldId,
                    children: group ? this.getTreeOrder(group) : []
                };
                
                order.push(entry);
            });
            
            return order;
        },
        
        /**
         * Get form ID
         */
        getFormId: function() {
            return this.container.dataset.formId;
        },
        
        /**
         * Toggle keyboard shortcuts help panel
         */
        toggleKeyboardHelp: function(e) {
            e.preventDefault();
            const $panel = $(this.container).find('.baf-keyboard-shortcuts');
            const isExpanded = $panel.attr('aria-hidden') === 'false';
            
            $panel.attr('aria-hidden', isExpanded ? 'true' : 'false');
            $panel.slideToggle(200);
            
            $(e.currentTarget).attr('aria-expanded', !isExpanded);
        },
        
        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            if (this.announcer) {
                this.announcer.announce(message, 'polite');
            }
        },
        
        /**
         * Announce expand
         */
        announceExpand: function(item) {
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announce(`${label} expanded`);
        },
        
        /**
         * Announce collapse
         */
        announceCollapse: function(item) {
            const label = item.querySelector('.baf-tree-label').textContent;
            this.announce(`${label} collapsed`);
        },
        
        /**
         * Announce move
         */
        announceMove: function(label, direction) {
            this.announce(`${label} moved ${direction}`);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        BAFStructureMode.init();
    });

    // Export for external use
    window.BAFStructureMode = BAFStructureMode;

})(jQuery);
