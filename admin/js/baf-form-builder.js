/**
 * BITS Accessible Forms - Form Builder JavaScript
 *
 * Handles field card management, move up/down functionality,
 * drag-drop with keyboard alternatives, and accessibility features.
 *
 * Every card action changes the stored form model on the server and saves
 * it, changing only its own question (or the order): baf_add_field,
 * baf_duplicate_field, baf_delete_field, baf_update_field_order,
 * baf_get_field_settings (field_id: the settings panel's content),
 * baf_save_field_settings, baf_convert_question and baf_save_form_status
 * (Save Draft and Publish). Each answers with the canvas drawn again from
 * the saved model, so the cards (nested in their sections and columns)
 * always show the model as it is. A refusal (deleting a question a rule
 * names, moving one onto another page) is said, and the cards are left as
 * the model is. A form not saved yet is made by its first question or by
 * Save Draft, without a reload.
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

    /** The name of a field type, from the registry the page was given. */
    function typeLabel(type) {
        const cats = data.fieldTypes || {};
        for (const k in cats) {
            const list = (cats[k] && cats[k].fields) || [];
            for (let i = 0; i < list.length; i++) {
                if (list[i].type === type) {
                    return list[i].label;
                }
            }
        }
        return type;
    }

    /** Put words in a status region, cleared first so the same words are said again. */
    function setStatus(el, text) {
        if (!el) return;
        el.textContent = '';
        setTimeout(function() { el.textContent = text; }, 60);
    }

    /** The cards and groups that move among each other. */
    const SIBLINGS = '.baf-field-card, .baf-group-card';

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
        busy: false,

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
            this.savedOrder = this.cardIds();
            this.updateMoveButtonStates();
            this.lastCanvas = this.dropzone.innerHTML;
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

        cards: function() {
            return $(this.dropzone).find('.baf-field-card');
        },

        /** A card's question label, without the required badge. */
        labelOf: function($card) {
            const $l = $card.find('.baf-field-label').first().clone();
            $l.find('.baf-required-badge').remove();
            return $l.text().replace(/\s+/g, ' ').trim();
        },

        cardFor: function(fieldId) {
            return this.cards().filter(function() { return String($(this).data('field-id')) === String(fieldId); });
        },

        /**
         * Take the canvas the server drew from the saved model, and the
         * form's ID when this action made the form; then focus a card's
         * button (or Add Field when that card is gone).
         */
        applyReply: function(reply, focusId, action) {
            if (!reply) return;
            this.adoptForm(reply);
            if (typeof reply.canvas === 'string') {
                $(this.dropzone).html(reply.canvas);
                this.savedOrder = this.cardIds();
                this.updateMoveButtonStates();
                this.lastCanvas = this.dropzone.innerHTML; // The model as saved, to go back to.
            }
            if (focusId !== undefined) {
                const $b = focusId === null ? $() : this.cardFor(focusId).find(action || '.baf-field-edit');
                ($b.length ? $b : $('#baf-add-field-btn')).first().focus();
            }
        },

        /** A form made by this action: the page now edits it, and its address says so. */
        adoptForm: function(reply) {
            if (!reply.form_id || Number(reply.form_id) === this.getFormId()) return;
            data.formId = Number(reply.form_id);
            if (window.bafModelEditor) window.bafModelEditor.formId = data.formId;
            $('#baf-form-id').val(data.formId);
            if (reply.edit_url && window.history && window.history.replaceState) {
                try { window.history.replaceState(null, '', reply.edit_url); } catch (e) { /* another origin in tests */ }
            }
            if (reply.created) {
                this.announcer.announce(L('formCreated', 'The form is made, as a draft. Every change is saved as you make it.'), 'polite');
            }
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
            $(document).on('click', '.baf-field-convert', this.openConvert.bind(this));

            // Field palette - add field (the palette's entries are .baf-palette-field)
            $(document).on('click', '.baf-field-type, .baf-palette-field', this.addFromPalette.bind(this));
            $(document).on('keydown', '.baf-field-type, .baf-palette-field', this.handlePaletteKeydown.bind(this));

            // Add Field (F): the picker dialog
            $(document).on('click', '#baf-add-field-btn', this.openPicker.bind(this));
            $(document).on('click', '.baf-picker-type', this.pickType.bind(this));
            $(document).on('input', '#baf-picker-filter', this.filterPicker.bind(this));
            $(document).on('keydown', '#baf-picker-filter', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#baf-picker-types .baf-picker-type:visible').first().trigger('click');
                }
            });

            // Convert type
            $(document).on('change', '#baf-convert-type', this.previewConvert.bind(this));
            $(document).on('submit', '#baf-convert-dialog form', this.submitConvert.bind(this));

            // Save Draft and Publish
            $(document).on('click', '#baf-save-draft-btn', (e) => this.saveStatus(e, 'draft'));
            $(document).on('click', '#baf-publish-btn', (e) => this.saveStatus(e, 'active'));

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
         * Initialize drag and drop with keyboard alternative. A drop is saved
         * through the same order check as the move buttons.
         */
        initDragDrop: function() {
            const dropzone = this.dropzone;
            const self = this;

            $(dropzone).on('dragstart', '.baf-field-card', function(e) {
                if (e.target !== this) return; // a button inside the card, not the card
                $(this).addClass('baf-dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', String($(this).data('field-id')));
            });

            $(dropzone).on('dragend', '.baf-field-card', function() {
                $(this).removeClass('baf-dragging');
                $(dropzone).find('.baf-drop-indicator').remove();
            });

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
         * Keyboard shortcuts. Control with the up or down arrow moves the
         * card focus is in. Enter and Delete act only when the card itself
         * has focus, so Enter on one of its buttons presses that button. F
         * opens Add Field only while focus is in the canvas and not in a
         * text box, so it is never a page-wide single-key shortcut.
         */
        initKeyboardShortcuts: function() {
            const self = this;

            $(document).on('keydown', function(e) {
                const target = document.activeElement;
                const inText = $(target).is('input, textarea, select, [contenteditable="true"]');
                if ((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey && !inText
                    && self.canvas.contains(target) && !$(target).closest('.baf-model-modal').length) {
                    e.preventDefault();
                    self.openPicker({ currentTarget: target }); // Escape returns focus where F was pressed.
                    return;
                }

                const $activeCard = $(target).closest('.baf-field-card');
                if (!$activeCard.length) return;
                const onCard = target === $activeCard[0];

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
         * Move field up among its neighbours (a section or columns counts
         * as one neighbour, moved past whole).
         */
        moveFieldUp: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $prev = $card.prev(SIBLINGS);

            if (!$prev.length) {
                this.announcer.announce(L('alreadyAtTop', '%s is already first.', this.labelOf($card)), 'polite');
                return;
            }

            this.swap($card, $prev, true, () => {
                $card.insertBefore($prev);
                $card.find('.baf-field-move-up').focus();
                this.afterMove($card, '.baf-field-move-up');
            });
        },

        /**
         * Move field down among its neighbours
         */
        moveFieldDown: function(e) {
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const $next = $card.next(SIBLINGS);

            if (!$next.length) {
                this.announcer.announce(L('alreadyAtBottom', '%s is already last.', this.labelOf($card)), 'polite');
                return;
            }

            this.swap($card, $next, false, () => {
                $card.insertAfter($next);
                $card.find('.baf-field-move-down').focus();
                this.afterMove($card, '.baf-field-move-down');
            });
        },

        /**
         * Save the new order, then say where the card is. When the model
         * keeps a question with its own page, section or column, the canvas
         * comes back in the model's order and that is said instead.
         */
        afterMove: function($card, button) {
            const fieldId = String($card.data('field-id'));
            const fieldLabel = this.labelOf($card);
            const wanted = this.cardIds();
            this.updateMoveButtonStates();
            this.updateFieldOrder().then((response) => {
                if (!response || !response.success) {
                    this.applyReply({ canvas: this.lastCanvas }, fieldId, button);
                    this.announcer.announce(this.failure(response, L('moveFailed', 'The new order was not saved.')), 'polite');
                    return;
                }
                const order = response.data.order || wanted;
                const C = window.BAFModelEditorCore;
                const same = C ? C.sameOrder(order, wanted) : order.join('\n') === wanted.join('\n');
                if (!same) {
                    this.applyReply(response.data, fieldId, '.baf-field-edit');
                    this.announcer.announce(L('moveStays', '%s stays with its page or section. Move it to another page in Structure mode.', fieldLabel), 'polite');
                    return;
                }
                this.lastCanvas = this.dropzone.innerHTML;
                const $now = this.cardFor(fieldId);
                const $sib = $now.parent().children(SIBLINGS);
                this.announcer.announceFieldMoved(fieldLabel, $sib.index($now) + 1, $sib.length);
            }, () => {
                this.announcer.announce(L('moveFailed', 'The new order was not saved.'), 'polite');
            });
        },

        /** The IDs of every card and group, in the canvas's order. */
        cardIds: function() {
            return $(this.dropzone).find('[data-field-id].baf-field-card, [data-group-id]').map(function() {
                return String($(this).attr('data-field-id') || $(this).attr('data-group-id'));
            }).get();
        },

        /**
         * Get field position among its neighbours (1-indexed)
         */
        getFieldPosition: function($card) {
            return $card.parent().children(SIBLINGS).index($card) + 1;
        },

        /**
         * Move buttons at either end of their list say so rather than being
         * disabled, so focus is never lost when a card reaches the top or
         * the bottom.
         */
        updateMoveButtonStates: function() {
            const self = this;
            this.cards().each(function() {
                const $card = $(this);
                const $sib = $card.parent().children(SIBLINGS);
                const index = $sib.index($card);
                const total = $sib.length;
                const $moveUp = $card.find('.baf-field-move-up');
                const $moveDown = $card.find('.baf-field-move-down');
                const fieldLabel = self.labelOf($card);

                $moveUp.prop('disabled', false).attr('aria-disabled', index === 0 ? 'true' : null);
                $moveDown.prop('disabled', false).attr('aria-disabled', index === total - 1 ? 'true' : null);

                $moveUp.attr('aria-label', L('moveUpAt', 'Move %1$s up (currently position %2$d of %3$d)', fieldLabel, index + 1, total));
                $moveDown.attr('aria-label', L('moveDownAt', 'Move %1$s down (currently position %2$d of %3$d)', fieldLabel, index + 1, total));
            });
        },

        /** The palette's entry: add its type. */
        addFromPalette: function(e) {
            e.preventDefault();
            const $entry = $(e.currentTarget);
            const label = ($entry.find('.baf-palette-field-label').text() || $entry.attr('aria-label') || $entry.text()).trim();
            this.addField($entry.data('field-type'), label);
        },

        /**
         * Add a question of a type at the end of the last page. A form not
         * made yet is made by this, as a draft, with the title typed so far.
         */
        addField: function(fieldType, fieldLabel) {
            this.post('baf_add_field', { field_type: fieldType, title: $('#baf-form-title').val() || '' }).then((response) => {
                if (!response.success) {
                    this.announcer.announce(this.failure(response, L('addFailed', 'The question was not added.')), 'polite');
                    $('#baf-add-field-btn').focus();
                    return;
                }
                this.applyReply(response.data, response.data.field_id, '.baf-field-edit');
                const $new = this.cardFor(response.data.field_id);
                this.announcer.announceFieldAdded(fieldLabel || response.data.label, this.getFieldPosition($new));
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
                    this.addFromPalette(e);
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

        // ------------------------------------------------------------------
        // Add Field (F): the picker
        // ------------------------------------------------------------------

        openPicker: function(e) {
            const E = window.BAFModelEditor;
            const wrapper = document.getElementById('baf-picker-dialog');
            if (!E || !wrapper) return;
            $('#baf-picker-filter').val('');
            this.filterPicker();
            this.picker = E.showDialog(wrapper, {
                returnTo: (e && e.currentTarget) || document.getElementById('baf-add-field-btn'),
                first: document.getElementById('baf-picker-filter')
            });
        },

        filterPicker: function() {
            const q = String($('#baf-picker-filter').val() || '').toLowerCase().trim();
            let shown = 0;
            $('#baf-picker-types .baf-picker-category').each(function() {
                let here = 0;
                $(this).find('li').each(function() {
                    const $li = $(this);
                    const $b = $li.find('.baf-picker-type');
                    const C = window.BAFModelEditorCore;
                    const match = C ? C.typeMatches(q, $b.text(), $b.data('field-type'))
                        : (!q || $b.text().toLowerCase().indexOf(q) !== -1 || String($b.data('field-type')).indexOf(q) !== -1);
                    $li.prop('hidden', !match);
                    here += match ? 1 : 0;
                });
                $(this).prop('hidden', here === 0);
                shown += here;
            });
            setStatus(document.getElementById('baf-picker-count'), shown === 1 ? L('oneType', '1 type.') : L('nTypes', '%d types.', shown));
        },

        pickType: function(e) {
            e.preventDefault();
            const $b = $(e.currentTarget);
            if (this.picker) {
                this.picker.close(document.body); // Focus goes to the new card once it is there.
                this.picker = null;
            }
            this.addField($b.data('field-type'), $b.text().trim());
        },

        // ------------------------------------------------------------------
        // Convert type
        // ------------------------------------------------------------------

        openConvert: function(e) {
            e.preventDefault();
            const E = window.BAFModelEditor;
            const wrapper = document.getElementById('baf-convert-dialog');
            if (!E || !wrapper) return;
            const $card = $(e.currentTarget).closest('.baf-field-card');
            const type = String($card.data('field-type') || '');
            this.converting = { fieldId: String($card.data('field-id')), label: this.labelOf($card), type: type };
            $('#baf-convert-dialog-title').text(L('convertTitle', 'Convert %s', this.converting.label));
            $('#baf-convert-current').text(L('convertCurrent', 'It is now: %s.', typeLabel(type)));
            const $select = $('#baf-convert-type').val('');
            $select.find('option').prop('disabled', false);
            $select.find('option[value="' + type + '"]').prop('disabled', true);
            $('#baf-convert-changes').empty();
            $('#baf-convert-preview-heading').prop('hidden', true);
            E.showProblems(document.getElementById('baf-convert-dialog-summary'), this.convertFields(), []);
            this.convertDialog = E.showDialog(wrapper, { returnTo: e.currentTarget, first: $select[0] });
        },

        convertFields: function() {
            return { type: { control: document.getElementById('baf-convert-type'), error: document.getElementById('baf-convert-type-error') } };
        },

        /** Say what the chosen type would change, before anything is changed. */
        previewConvert: function() {
            const type = $('#baf-convert-type').val();
            const $list = $('#baf-convert-changes').empty();
            $('#baf-convert-preview-heading').prop('hidden', !type);
            if (!type || !this.converting) return;
            this.post('baf_convert_question', { field_id: this.converting.fieldId, target_type: type, dry_run: '1' }).then((response) => {
                if ($('#baf-convert-type').val() !== type) return; // chosen again meanwhile
                if (!response.success) {
                    $list.append($('<li>').text(this.failure(response, L('convertFailed', 'It could not be converted.'))));
                    return;
                }
                const d = response.data;
                (d.changes || []).forEach((c) => $list.append($('<li>').text(c)));
                (d.problems || []).forEach((p) => $list.append($('<li>').text(L('convertProblem', 'It could not be saved: %s', p.message))));
                if (!(d.changes || []).length && !(d.problems || []).length) {
                    $list.append($('<li>').text(L('convertNothing', 'Nothing else changes.')));
                }
            });
        },

        submitConvert: function(e) {
            e.preventDefault();
            const E = window.BAFModelEditor;
            const type = $('#baf-convert-type').val();
            if (!type) {
                E.showProblems(document.getElementById('baf-convert-dialog-summary'), this.convertFields(), [{ field: 'type', message: L('convertChoose', 'Choose the type to change it to.') }]);
                return;
            }
            const job = this.converting;
            this.post('baf_convert_question', { field_id: job.fieldId, target_type: type }).then((response) => {
                if (!response.success) {
                    E.showProblems(document.getElementById('baf-convert-dialog-summary'), this.convertFields(), [{ field: 'type', message: this.failure(response, L('convertFailed', 'It could not be converted.')) }]);
                    return;
                }
                this.convertDialog.close(document.body);
                this.applyReply(response.data, job.fieldId, '.baf-field-convert');
                const changes = (response.data.changes || []).join(' ');
                this.announcer.announce(L('converted', '%1$s is now %2$s.', job.label, response.data.type_label) + (changes ? ' ' + changes : ''), 'polite');
            }, () => {
                E.showProblems(document.getElementById('baf-convert-dialog-summary'), this.convertFields(), [{ field: 'type', message: L('convertFailed', 'It could not be converted.') }]);
            });
        },

        // ------------------------------------------------------------------
        // Save Draft and Publish
        // ------------------------------------------------------------------

        /**
         * The questions are saved as they change; these save the title and
         * the status, and make the form if it was not made yet.
         */
        saveStatus: function(e, status) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const statusEl = document.getElementById('baf-save-status');
            if (this.busy) return;
            this.busy = true;
            $button.attr('aria-disabled', 'true');
            this.post('baf_save_form_status', { status: status, title: $('#baf-form-title').val() || '' }).then((response) => {
                this.busy = false;
                $button.removeAttr('aria-disabled');
                if (!response.success) {
                    setStatus(statusEl, this.failure(response, L('statusFailed', 'The form was not saved.')));
                    return;
                }
                this.adoptForm(response.data);
                $('#baf-canvas-title').text(response.data.title);
                setStatus(statusEl, status === 'active' ? L('published', 'Published. The form is live.') : L('draftSaved', 'Saved as a draft.'));
            }, () => {
                this.busy = false;
                $button.removeAttr('aria-disabled');
                setStatus(statusEl, L('statusFailed', 'The form was not saved.'));
            });
        },

        // ------------------------------------------------------------------
        // Settings panel
        // ------------------------------------------------------------------

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
                this.applyReply(response.data);
                this.cardFor(fieldId).find('.baf-field-edit').attr('aria-expanded', 'true');
                $panel.find('#baf-fs-heading').text(L('settingsHeading', 'Settings: %s', response.data.label));
                setStatus(document.getElementById('baf-fs-status'), L('settingsSaved', 'Settings saved for %s.', response.data.label));
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
            const fieldLabel = this.labelOf($card);

            this.post('baf_duplicate_field', { field_id: fieldId }).then((response) => {
                if (!response.success) {
                    this.announcer.announce(this.failure(response, L('duplicateFailed', 'The question was not copied.')), 'polite');
                    return;
                }
                this.applyReply(response.data, response.data.field_id, '.baf-field-edit');
                const $new = this.cardFor(response.data.field_id);
                this.announcer.announce(L('fieldDuplicated', '%1$s copied, as %2$s, at position %3$d.', fieldLabel, response.data.label, this.getFieldPosition($new)), 'polite');
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
            const fieldLabel = this.labelOf($card);
            const $button = $card.find('.baf-field-delete');
            const E = window.BAFModelEditor;
            if (!E || !document.getElementById('baf-model-confirm')) {
                return; // The page always has the shared alertdialog; without it nothing is deleted.
            }

            E.confirmDelete(L('deleteTitle', 'Delete question %d?', this.getFieldPosition($card)), L('confirmDelete', '%s will be deleted from the form.', fieldLabel), $button[0]).then((yes) => {
                if (!yes) return;
                // The card that will take its place, or the one before.
                const $next = $card.next('.baf-field-card');
                const $prev = $card.prev('.baf-field-card');
                const focusId = $next.length ? String($next.data('field-id')) : ($prev.length ? String($prev.data('field-id')) : null);

                this.post('baf_delete_field', { field_id: fieldId }).then((response) => {
                    if (!response.success) {
                        $button.focus();
                        this.announcer.announce(L('deleteRefused', '%1$s was not deleted. %2$s', fieldLabel, this.failure(response, '')), 'polite');
                        return;
                    }
                    if (String($(this.settingsPanel).data('field-id')) === fieldId) {
                        $(this.settingsPanel).prop('hidden', true).removeData('field-id').find('#baf-settings-body').empty();
                    }
                    this.applyReply(response.data, focusId, '.baf-field-edit');
                    this.announcer.announceFieldDeleted(fieldLabel, this.cards().length);
                }, () => {
                    $button.focus();
                    this.announcer.announce(L('deleteFailed', 'The question was not deleted.'), 'polite');
                });
            });
        },

        /**
         * Save the order of the cards and groups. Resolves with the reply.
         */
        updateFieldOrder: function() {
            const request = this.post('baf_update_field_order', { field_order: this.cardIds() });
            $(document).trigger('baf:fieldOrderChanged');
            return request;
        },

        /**
         * Get current form ID
         */
        getFormId: function() {
            return Number(data.formId || $('#baf-form-id').val() || 0);
        },

        /** The card or group under the pointer, and whether to drop after it. */
        dropTarget: function(e, $dragged) {
            let best = null;
            $(this.dropzone).find(SIBLINGS).not('.baf-dragging').each(function() {
                if ($dragged && $.contains($dragged[0], this)) return;
                const rect = this.getBoundingClientRect();
                if (e.clientY >= rect.top && e.clientY <= rect.bottom) {
                    best = { el: this, after: e.clientY > rect.top + rect.height / 2 }; // innermost wins: later in the walk
                }
            });
            return best;
        },

        /**
         * Handle drop indicator during drag
         */
        updateDropIndicator: function(e) {
            const $dropzone = $(this.dropzone);
            $dropzone.find('.baf-drop-indicator').remove();
            const t = this.dropTarget(e, $dropzone.find('.baf-dragging'));
            if (!t) return;
            const $indicator = $('<div class="baf-drop-indicator" aria-hidden="true"></div>');
            if (t.after) {
                $(t.el).after($indicator);
            } else {
                $(t.el).before($indicator);
            }
        },

        /**
         * Handle drop: put the card where it was dropped, then save through
         * the same order check as the move buttons.
         */
        handleDrop: function(fieldId, e) {
            const $dropzone = $(this.dropzone);
            $dropzone.find('.baf-drop-indicator').remove();
            const $card = this.cardFor(fieldId);
            if (!$card.length) return;
            const t = this.dropTarget(e, $card);
            if (!t || t.el === $card[0]) return;
            if (t.after) {
                $card.insertAfter(t.el);
            } else {
                $card.insertBefore(t.el);
            }
            $card.removeClass('baf-dragging');
            $card.find('.baf-field-edit').focus();
            this.afterMove($card, '.baf-field-edit');
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

        announceFieldAdded(fieldLabel, position) {
            this.announce(L('fieldAdded', '%1$s added, at position %2$d.', fieldLabel, position), 'polite');
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
    });

    // Export for external use
    window.BAFFormBuilder = BAFFormBuilder;
    window.BAFAnnouncer = BAFAnnouncer;
    window.BAFFocusManager = BAFFocusManager;

})(jQuery);
