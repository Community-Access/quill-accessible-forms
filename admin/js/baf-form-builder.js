/**
 * BITS Accessible Forms - Form Builder JavaScript
 *
 * Handles field card management, move up/down functionality,
 * drag-drop with keyboard alternatives, and accessibility features.
 *
 * Every card action changes the stored form model on the server and saves
 * it, changing only its own question (or the order): baf_add_field,
 * baf_duplicate_field, baf_delete_field, baf_update_field_order,
 * baf_get_field_settings (field_id: the settings panel's content) and
 * baf_save_field_settings. A refusal (for example deleting a question a
 * rule names) is said, and the page is left as the model is.
 *
 * @package BAF
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // The page's data comes as bafBuilder (wp_localize_script).
    const data = window.bafBuilder || {};
    const ajaxurl = window.ajaxurl || data.ajaxUrl;

    /** A string from bafBuilder.i18n, with %s, %d and %1$s filled in. */
    function L(key, fallback) {
        const args = Array.prototype.slice.call(arguments, 2);
        let i = 0;
        return String((data.i18n && data.i18n[key]) || fallback).replace(/%(?:(\d+)\$)?([sd])/g, function(m, n) {
            const v = n ? args[Number(n) - 1] : args[i++];
            return v === undefined ? m : String(v);
        });
    }

    function reducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    /**
     * Form Builder Controller
     */
    const BAFFormBuilder = {
        canvas: null,
        dropzone: null,
        fieldPalette: null,
        settingsPanel: null,
        announcer: null,
        focusManager: null,

        /**
         * Initialize the form builder
         */
        init: function() {
            this.canvas = document.getElementById('baf-form-canvas');
            this.dropzone = document.getElementById('baf-canvas-dropzone');
            this.fieldPalette = document.getElementById('baf-field-palette');
            this.settingsPanel = document.getElementById('baf-field-settings');

            if (!this.canvas || !this.dropzone) return;

            // Initialize accessibility helpers
            this.announcer = new BAFAnnouncer();
            this.focusManager = new BAFFocusManager();

            this.bindEvents();
            this.initDragDrop();
            this.initKeyboardShortcuts();
        },

        /** POST to admin-ajax with the nonce and the form's ID. */
        post: function(action, fields) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $.extend({ action: action, nonce: data.nonce, form_id: this.getFormId() }, fields)
            });
        },

        /** The message of a refused request. */
        failure: function(response, fallback) {
            return (response && response.data && response.data.message) || fallback;
        },

        /** A card's question label, without the required badge. */
        labelOf: function($card) {
            const $l = $card.find('.baf-field-label').first().clone();
            $l.find('.baf-required-badge').remove();
            return $l.text().replace(/\s+/g, ' ').trim();
        },
        cards: function() {
            return $(this.dropzone).find('.baf-field-card');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Move up/down buttons
            $(document).on('click', '.baf-field-move-up', this.moveFieldUp.bind(this));
            $(document).on('click', '.baf-field-move-down', this.moveFieldDown.bind(this));

            // Field card actions
            $(document).on('click', '.baf-field-edit', this.editField.bind(this));
            $(document).on('click', '.baf-field-duplicate', this.duplicateField.bind(this));
            $(document).on('click', '.baf-field-delete', this.deleteField.bind(this));

            // Field palette - add field (the palette's entries are .baf-palette-field)
            $(document).on('click', '.baf-field-type, .baf-palette-field', this.addField.bind(this));
            $(document).on('keydown', '.baf-field-type, .baf-palette-field', this.handlePaletteKeydown.bind(this));

            // Settings panel: the form is drawn by the server; Enter or Save submits it.
            $(document).on('submit', '#baf-fs-form', this.saveFieldSettings.bind(this));
            $(document).on('click', '.baf-settings-close', this.closeSettings.bind(this));
            $(document).on('click', '.baf-close-settings', (e) => {
                if ($(this.settingsPanel).find('.baf-field-settings-section').length) {
                    this.closeSettings(e);
                }
            });
            $(document).on('keydown', '.baf-field-settings-section', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.closeSettings(e);
                }
            });

            // Modal management
            $(document).on('click', '.baf-modal-close', this.closeModal.bind(this));
            $(document).on('keydown', '.baf-modal', this.handleModalKeydown.bind(this));

            // Update move button states when fields change
            $(document).on('baf:fieldOrderChanged', this.updateMoveButtonStates.bind(this));
        },

        /**
         * Initialize drag and drop with keyboard alternative
         */
        initDragDrop: function() {
            const dropzone = this.dropzone;
            const self = this;

            // Make field cards draggable
            $(dropzone).on('dragstart', '.baf-field-card', function(e) {
                $(this).addClass('baf-dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', $(this).data('field-id'));
            });

            $(dropzone).on('dragend', '.baf-field-card', function() {
                $(this).removeClass('baf-dragging');
            });

            // Handle drop zone
            $(dropzone).on('dragover', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                self.updateDropIndicator(e);
            });

            $(dropzone).on('drop', function(e) {
                e.preventDefault();
                const fieldId = e.originalEvent.dataTransfer.getData('text/plain');
                self.handleDrop(fieldId, e);
            });
        },

        /**
         * Initialize keyboard shortcuts. They act only when the card itself
         * has focus, so Enter on one of its buttons presses that button.
         */
        initKeyboardShortcuts: function() {
            const self = this;

            $(document).on('keydown', function(e) {
                const $activeCard = $(document.activeElement).closest('.baf-field-card');
                if (!$activeCard.length) return;
                const onCard = document.activeElement === $activeCard[0];

                // Ctrl/Cmd + Arrow keys for moving fields, from anywhere in the card
                if (e.ctrlKey || e.metaKey) {
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        self.moveFieldUp({ currentTarget: $activeCard.find('.baf-field-move-up')[0] });
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        self.moveFieldDown({ currentTarget: $activeCard.find('.baf-field-move-down')[0] });
                    }
                    return;
                }

                if (!onCard) return;

                if (e.key === 'Delete') {
                    e.preventDefault();
                    self.deleteField({ currentTarget: $activeCard.find('.baf-field-delete')[0] });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    self.editField({ currentTarget: $activeCard.find('.baf-field-edit')[0] });
                }
            });
        },

        /**
         * Animate a swap, unless motion is reduced; then call done.
         */
        swap: function($card, $other, up, done) {
            if (reducedMotion()) {
                done();
                return;
            }
            const distance = Math.abs($card.offset().top - $other.offset().top);
            $card.css('transform', `translateY(${up ? -distance : distance}px)`);
            $other.css('transform', `translateY(${up ? $card.outerHeight() : -$card.outerHeight()}px)`);
            setTimeout(() => {
                $card.css('transform', '');
                $other.css('transform', '');
                done();
            }, 200);
        },

        /**
         * Move field up in order
         */
        moveFieldUp: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $prev = $card.prev('.baf-field-card');

            if (!$prev.length) {
                this.announcer.announce(L('alreadyAtTop', '%s is already first.', BAFFormBuilder.labelOf($card)), 'polite');
                return;
            }

            this.swap($card, $prev, true, () => {
                $card.insertBefore($prev);
                $card.find('.baf-field-move-up').focus();
                this.afterMove($card);
            });
        },

        /**
         * Move field down in order
         */
        moveFieldDown: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $next = $card.next('.baf-field-card');

            if (!$next.length) {
                this.announcer.announce(L('alreadyAtBottom', '%s is already last.', BAFFormBuilder.labelOf($card)), 'polite');
                return;
            }

            this.swap($card, $next, false, () => {
                $card.insertAfter($next);
                $card.find('.baf-field-move-down').focus();
                this.afterMove($card);
            });
        },

        /**
         * Save the new order, then say where the card is. When the model
         * keeps a question on its own page or section, the cards are put
         * back in the model's order and that is said instead.
         */
        afterMove: function($card) {
            const fieldLabel = BAFFormBuilder.labelOf($card);
            this.updateMoveButtonStates();
            this.updateFieldOrder().then((response) => {
                if (!response || !response.success) {
                    this.restoreOrder(null);
                    this.announcer.announce(this.failure(response, L('moveFailed', 'The new order was not saved.')), 'polite');
                    return;
                }
                const wanted = this.cardIds();
                const order = response.data.order || wanted;
                const same = window.BAFModelEditorCore ? window.BAFModelEditorCore.sameOrder(order, wanted) : order.join('\n') === wanted.join('\n');
                if (!same) {
                    this.restoreOrder(order);
                    $card.find('.baf-field-edit').focus();
                    this.announcer.announce(L('moveStays', '%s stays with its page or section. Move it to another page in Structure mode.', fieldLabel), 'polite');
                    return;
                }
                this.announcer.announceFieldMoved(fieldLabel, this.getFieldPosition($card), wanted.length);
            }, () => {
                this.announcer.announce(L('moveFailed', 'The new order was not saved.'), 'polite');
            });
        },

        cardIds: function() {
            return this.cards().map(function() { return String($(this).data('field-id')); }).get();
        },

        /** Put the cards in the given order (or the last saved one). */
        restoreOrder: function(order) {
            order = order || this.savedOrder || [];
            const $zone = $(this.dropzone);
            order.forEach((id) => {
                const $c = this.cards().filter(function() { return String($(this).data('field-id')) === id; });
                $zone.append($c);
            });
            this.updateMoveButtonStates();
        },

        /**
         * Get field position (1-indexed)
         */
        getFieldPosition: function($card) {
            return this.cards().index($card) + 1;
        },

        /**
         * Move buttons at either end say so rather than being disabled, so
         * focus is never lost when a card reaches the top or the bottom.
         */
        updateMoveButtonStates: function() {
            const $cards = this.cards();
            const totalCards = $cards.length;

            $cards.each(function(index) {
                const $card = $(this);
                const $moveUp = $card.find('.baf-field-move-up');
                const $moveDown = $card.find('.baf-field-move-down');
                const fieldLabel = BAFFormBuilder.labelOf($card);

                $moveUp.prop('disabled', false).attr('aria-disabled', index === 0 ? 'true' : null);
                $moveDown.prop('disabled', false).attr('aria-disabled', index === totalCards - 1 ? 'true' : null);

                $moveUp.attr('aria-label', L('moveUpAt', 'Move %1$s up (currently position %2$d of %3$d)', fieldLabel, index + 1, totalCards));
                $moveDown.attr('aria-label', L('moveDownAt', 'Move %1$s down (currently position %2$d of %3$d)', fieldLabel, index + 1, totalCards));
            });
        },

        /**
         * Add a new field from palette
         */
        addField: function(e) {
            e.preventDefault();
            const $entry = $(e.currentTarget);
            const fieldType = $entry.data('field-type');
            const fieldLabel = ($entry.find('.baf-palette-field-label').text() || $entry.attr('aria-label') || $entry.text()).trim();

            this.post('baf_add_field', { field_type: fieldType, total: this.cards().length + 1 }).then((response) => {
                if (!response.success) {
                    this.announcer.announce(this.failure(response, L('addFailed', 'The question was not added.')), 'polite');
                    return;
                }
                $(this.dropzone).find('.baf-canvas-empty').remove();
                const $newCard = $(response.data.html);
                $(this.dropzone).append($newCard);
                this.savedOrder = this.cardIds();
                this.updateMoveButtonStates();

                // Focus the new field card
                $newCard.find('.baf-field-edit').focus();

                this.announcer.announceFieldAdded(fieldLabel, this.cards().length);
            }, () => {
                this.announcer.announce(L('addFailed', 'The question was not added.'), 'polite');
            });
        },

        /**
         * Handle palette keyboard navigation: arrows, Home and End move;
         * Enter or Space adds.
         */
        handlePaletteKeydown: function(e) {
            const $button = $(e.currentTarget);
            if (e.key === 'Enter' || e.key === ' ') {
                if (!$button.is('button')) {
                    this.addField(e);
                }
                return;
            }
            const $buttons = $(this.fieldPalette).find('.baf-field-type, .baf-palette-field').filter(':visible');
            const currentIndex = $buttons.index($button);
            let newIndex = currentIndex;

            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                e.preventDefault();
                newIndex = (currentIndex + 1) % $buttons.length;
            } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                e.preventDefault();
                newIndex = (currentIndex - 1 + $buttons.length) % $buttons.length;
            } else if (e.key === 'Home') {
                e.preventDefault();
                newIndex = 0;
            } else if (e.key === 'End') {
                e.preventDefault();
                newIndex = $buttons.length - 1;
            }

            if (newIndex !== currentIndex) {
                $buttons.eq(newIndex).focus();
            }
        },

        /**
         * Edit field settings: the server draws them from the question in
         * the stored model.
         */
        editField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = String($card.data('field-id'));

            this.post('baf_get_field_settings', { field_id: fieldId }).then((response) => {
                if (response.success) {
                    this.openSettingsPanel(response.data, $card);
                } else {
                    this.announcer.announce(this.failure(response, L('settingsFailed', 'The settings could not be opened.')), 'polite');
                }
            }, () => {
                this.announcer.announce(L('settingsFailed', 'The settings could not be opened.'), 'polite');
            });
        },

        /**
         * Open settings panel; focus goes to its heading, which says whose
         * settings these are.
         */
        openSettingsPanel: function(response, $card) {
            const $panel = $(this.settingsPanel);
            if (window.BAFFieldScoring) {
                window.BAFFieldScoring.reset();
            }
            this.cards().find('.baf-field-edit').attr('aria-expanded', 'false');
            $panel.find('#baf-settings-body').html(response.html);
            $panel.data('field-id', response.field_id);
            $panel.prop('hidden', false).addClass('baf-panel-open').removeAttr('aria-hidden');
            if ($card) {
                $card.find('.baf-field-edit').attr('aria-expanded', 'true');
            }
            $panel.find('#baf-fs-heading').focus();
        },

        /**
         * Close settings panel
         */
        closeSettings: function(e) {
            if (e && e.preventDefault) e.preventDefault();
            const $panel = $(this.settingsPanel);
            $panel.prop('hidden', true).removeClass('baf-panel-open');
            $panel.find('#baf-settings-body').empty();

            // Return focus to the field card's Edit button
            const fieldId = $panel.data('field-id');
            $panel.removeData('field-id');
            if (fieldId) {
                const $edit = this.cardFor(fieldId).find('.baf-field-edit');
                $edit.attr('aria-expanded', 'false').focus();
            }
        },

        cardFor: function(fieldId) {
            return this.cards().filter(function() { return String($(this).data('field-id')) === String(fieldId); });
        },

        /** Show problems in the settings panel, joined to their fields. */
        settingsProblems: function(problems) {
            const E = window.BAFModelEditor;
            const fields = {
                label: { control: document.getElementById('baf-fs-label'), error: document.getElementById('baf-fs-label-error') }
            };
            if (E) {
                E.showProblems(document.getElementById('baf-fs-summary'), fields, problems);
            } else if (problems.length) {
                this.announcer.announce(problems[0].message, 'assertive');
            }
        },

        /**
         * Save field settings: only this question's label, help,
         * placeholder and required go back into the model.
         */
        saveFieldSettings: function(e) {
            e.preventDefault();
            const $panel = $(this.settingsPanel);
            const fieldId = String($panel.data('field-id'));
            const $form = $panel.find('#baf-fs-form');
            const fields = {
                field_id: fieldId,
                label: $form.find('#baf-fs-label').val(),
                help: $form.find('#baf-fs-help').val(),
                required: $form.find('#baf-fs-required').is(':checked') ? '1' : '',
                has_required: '1'
            };
            if ($form.find('#baf-fs-placeholder').length) {
                fields.placeholder = $form.find('#baf-fs-placeholder').val();
            }
            const C = window.BAFModelEditorCore;
            const local = C ? C.checkFieldSettings(fields, function(key, fallback) { return L(key, fallback); })
                : (String(fields.label).trim() === '' ? [{ field: 'label', message: L('needLabel', 'A question needs a label, so people know what it asks.') }] : []);
            if (local.length) {
                this.settingsProblems(local);
                return;
            }
            fields.total = this.cards().length;

            this.post('baf_save_field_settings', fields).then((response) => {
                if (!response.success) {
                    const d = response.data || {};
                    const problems = (d.problems || []).concat((d.errors || []).map(function(er) {
                        return { field: null, message: er.message };
                    }));
                    this.settingsProblems(problems.length ? problems : [{ field: null, message: this.failure(response, L('settingsNotSaved', 'The settings were not saved.')) }]);
                    return;
                }
                this.settingsProblems([]);
                const $old = this.cardFor(fieldId);
                const $new = $(response.data.html);
                $new.find('.baf-field-edit').attr('aria-expanded', 'true');
                $old.replaceWith($new);
                this.updateMoveButtonStates();
                $panel.find('#baf-fs-heading').text(L('settingsHeading', 'Settings: %s', response.data.label));
                const status = document.getElementById('baf-fs-status');
                if (status) {
                    status.textContent = '';
                    setTimeout(() => { status.textContent = L('settingsSaved', 'Settings saved for %s.', response.data.label); }, 60);
                }
            }, () => {
                this.settingsProblems([{ field: null, message: L('settingsNotSaved', 'The settings were not saved.') }]);
            });
        },

        /**
         * Duplicate field
         */
        duplicateField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = String($card.data('field-id'));
            const fieldLabel = BAFFormBuilder.labelOf($card);

            this.post('baf_duplicate_field', { field_id: fieldId, total: this.cards().length + 1 }).then((response) => {
                if (!response.success) {
                    this.announcer.announce(this.failure(response, L('duplicateFailed', 'The question was not copied.')), 'polite');
                    return;
                }
                const $newCard = $(response.data.html);
                $card.after($newCard);
                this.savedOrder = this.cardIds();
                this.updateMoveButtonStates();

                // Focus the new field card
                $newCard.find('.baf-field-edit').focus();

                this.announcer.announce(L('fieldDuplicated', '%1$s copied, as %2$s, at position %3$d.', fieldLabel, response.data.label, this.getFieldPosition($newCard)), 'polite');
            }, () => {
                this.announcer.announce(L('duplicateFailed', 'The question was not copied.'), 'polite');
            });
        },

        /**
         * Delete field, after asking in an alertdialog.
         */
        deleteField: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const fieldId = String($card.data('field-id'));
            const fieldLabel = BAFFormBuilder.labelOf($card);
            const $button = $card.find('.baf-field-delete');
            const E = window.BAFModelEditor;
            if (!E || !document.getElementById('baf-model-confirm')) {
                return; // The page always has the shared alertdialog; without it nothing is deleted.
            }

            E.confirmDelete(L('deleteTitle', 'Delete question %d?', this.getFieldPosition($card)), L('confirmDelete', '%s will be deleted from the form.', fieldLabel), $button[0]).then((yes) => {
                if (!yes) return;
                // Find next focusable element before removing
                const $next = $card.next('.baf-field-card');
                const $prev = $card.prev('.baf-field-card');
                const $focusTarget = $next.length ? $next : $prev;

                this.post('baf_delete_field', { field_id: fieldId }).then((response) => {
                    if (!response.success) {
                        $button.focus();
                        this.announcer.announce(L('deleteRefused', '%1$s was not deleted. %2$s', fieldLabel, this.failure(response, '')), 'polite');
                        return;
                    }
                    $card.remove();
                    this.savedOrder = this.cardIds();
                    this.updateMoveButtonStates();

                    // Move focus to next/prev field, or to Add Field
                    if ($focusTarget.length) {
                        $focusTarget.find('.baf-field-edit').focus();
                    } else {
                        $('#baf-add-field-btn').focus();
                    }
                    if ($(this.settingsPanel).data('field-id') === fieldId) {
                        $(this.settingsPanel).prop('hidden', true).removeData('field-id').find('#baf-settings-body').empty();
                    }

                    this.announcer.announceFieldDeleted(fieldLabel, this.cards().length);
                }, () => {
                    $button.focus();
                    this.announcer.announce(L('deleteFailed', 'The question was not deleted.'), 'polite');
                });
            });
        },

        /**
         * Save the order of the cards. Resolves with the server's reply.
         */
        updateFieldOrder: function() {
            const fieldOrder = this.cardIds();
            const request = this.post('baf_update_field_order', { field_order: fieldOrder });
            request.then((response) => {
                if (response && response.success) {
                    this.savedOrder = response.data.order || fieldOrder;
                }
            });
            $(document).trigger('baf:fieldOrderChanged');
            return request;
        },

        /**
         * Get current form ID
         */
        getFormId: function() {
            return Number(data.formId || $('#baf-form-id').val() || 0);
        },

        /**
         * Handle drop indicator during drag
         */
        updateDropIndicator: function(e) {
            const $dropzone = $(this.dropzone);
            $dropzone.find('.baf-drop-indicator').remove();

            const $cards = $dropzone.find('.baf-field-card:not(.baf-dragging)');
            let insertAfter = null;

            $cards.each(function() {
                const rect = this.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) {
                    insertAfter = this;
                }
            });

            const $indicator = $('<div class="baf-drop-indicator" aria-hidden="true"></div>');
            if (insertAfter) {
                $(insertAfter).after($indicator);
            } else {
                $dropzone.prepend($indicator);
            }
        },

        /**
         * Handle drop
         */
        handleDrop: function(fieldId, e) {
            const $dropzone = $(this.dropzone);
            $dropzone.find('.baf-drop-indicator').remove();

            const $card = this.cardFor(fieldId);
            const $cards = $dropzone.find('.baf-field-card:not(.baf-dragging)');
            let insertAfter = null;

            $cards.each(function() {
                const rect = this.getBoundingClientRect();
                if (e.clientY > rect.top + rect.height / 2) {
                    insertAfter = this;
                }
            });

            if (insertAfter) {
                $card.insertAfter(insertAfter);
            } else {
                $dropzone.prepend($card);
            }

            this.afterMove($card);
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            const $modal = $(e.currentTarget).closest('.baf-modal');
            $modal.attr('aria-hidden', 'true');
            this.focusManager.releaseTrap();
        },

        /**
         * Handle modal keyboard events
         */
        handleModalKeydown: function(e) {
            if (e.key === 'Escape') {
                this.closeModal(e);
            }
        }
    };

    /**
     * Announcer Class for ARIA live regions
     */
    class BAFAnnouncer {
        constructor() {
            this.politeRegion = document.getElementById('baf-live-polite') || document.getElementById('baf-live-region');
            this.assertiveRegion = document.getElementById('baf-live-assertive') || document.getElementById('baf-live-region-assertive');
            this.queue = [];
            this.isProcessing = false;
        }

        announce(message, type = 'polite') {
            const region = type === 'assertive' ? this.assertiveRegion : this.politeRegion;
            if (!region || !message) return;

            this.queue.push({ message, region });
            this.processQueue();
        }

        processQueue() {
            if (this.isProcessing || this.queue.length === 0) return;

            this.isProcessing = true;
            const { message, region } = this.queue.shift();

            // Clear and set message
            region.textContent = '';
            setTimeout(() => {
                region.textContent = message;
                setTimeout(() => {
                    region.textContent = '';
                    this.isProcessing = false;
                    this.processQueue();
                }, 1000);
            }, 100);
        }

        announceFieldMoved(fieldLabel, position, total) {
            this.announce(L('fieldMoved', '%1$s moved to position %2$d of %3$d.', fieldLabel, position, total), 'polite');
        }

        announceFieldAdded(fieldLabel, total) {
            this.announce(L('fieldAdded', '%1$s added, at position %2$d.', fieldLabel, total), 'polite');
        }

        announceFieldDeleted(fieldLabel, total) {
            this.announce(L('fieldDeleted', '%1$s deleted. %2$d questions left.', fieldLabel, total), 'polite');
        }
    }

    /**
     * Focus Manager Class
     */
    class BAFFocusManager {
        constructor() {
            this.trapContainer = null;
            this.previousFocus = null;
            this.onTrapKeydown = this.handleTrapKeydown.bind(this);
        }

        trapFocus(container) {
            this.trapContainer = container;
            this.previousFocus = document.activeElement;

            const focusableElements = this.getFocusableElements(container);
            if (focusableElements.length === 0) return;

            focusableElements[0].focus();

            container.addEventListener('keydown', this.onTrapKeydown);
        }

        handleTrapKeydown(e) {
            if (e.key !== 'Tab') return;

            const focusableElements = this.getFocusableElements(this.trapContainer);
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }

        releaseTrap() {
            if (this.trapContainer) {
                this.trapContainer.removeEventListener('keydown', this.onTrapKeydown);
                this.trapContainer = null;
            }

            if (this.previousFocus && this.previousFocus.focus) {
                this.previousFocus.focus();
                this.previousFocus = null;
            }
        }

        getFocusableElements(container) {
            const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
            return Array.from(container.querySelectorAll(selector))
                .filter(el => !el.disabled && el.offsetParent !== null);
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        BAFFormBuilder.init();
        if (BAFFormBuilder.dropzone) {
            BAFFormBuilder.savedOrder = BAFFormBuilder.cardIds();
            BAFFormBuilder.updateMoveButtonStates();
        }
    });

    // Export for external use
    window.BAFFormBuilder = BAFFormBuilder;
    window.BAFAnnouncer = BAFAnnouncer;
    window.BAFFocusManager = BAFFocusManager;

})(jQuery);
