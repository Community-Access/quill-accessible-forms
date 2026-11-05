/**
 * The builder's model screens: the pieces they share.
 *
 * - The mode switcher's tabs (Visual, Structure, BFL Code, then Rules, Quiz
 *   outcomes, Translations, Choice libraries): arrow keys, Home and End.
 * - The model: read with baf_get_form_model, saved whole with
 *   baf_save_form_model. Each save reads the stored model again and puts
 *   back only the part the screen owns, so nothing another screen (or the
 *   visual builder) saved is lost, and the server's validate() checks it.
 * - A list screen: a table with a caption, one row per object, the first
 *   cell a row header, and Edit, Move up, Move down and Delete buttons whose
 *   names say the row. Moves and deletes are said in the screen's status
 *   region; Delete asks first in an alertdialog; focus goes to a row that
 *   is still there, or to Add.
 * - A modal dialog: focus trapped, Escape closes, focus returns to the
 *   button that opened it; errors are joined to their fields with
 *   aria-describedby and listed in a summary that takes focus.
 * - An expression field: checked as it is typed, with the browser's copy of
 *   the evaluator, and read back as a sentence; "Insert a field" puts #id in
 *   from a list of the form's fields by label.
 *
 * The screens themselves are in baf-model-screens.js.
 *
 * @package BAF
 * @since 1.1.0
 */
(function () {
    'use strict';

    var C = window.BAFModelEditorCore;
    var data = window.bafModelEditor || {};
    var t = C.translator(data.i18n || {});

    // ------------------------------------------------------------------
    // Small helpers
    // ------------------------------------------------------------------

    function $(id) {
        return document.getElementById(id);
    }

    function el(tag, attrs, text) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (attrs[k] === null || attrs[k] === undefined || attrs[k] === false) {
                return;
            }
            if (k === 'className') {
                e.className = attrs[k];
            } else {
                e.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
            }
        });
        if (text !== undefined && text !== null) {
            e.textContent = text;
        }
        return e;
    }

    /** Put a result in a status region; cleared first so the same words are said again. */
    function say(region, text) {
        if (!region) {
            return;
        }
        region.textContent = '';
        window.setTimeout(function () { region.textContent = text; }, 60);
    }

    function post(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', data.nonce);
        Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
        return fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function focusables(container) {
        return Array.prototype.filter.call(
            container.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (e) {
                return !e.disabled && e.getAttribute('tabindex') !== '-1' && e.offsetParent !== null;
            }
        );
    }

    // ------------------------------------------------------------------
    // The mode switcher's tabs
    // ------------------------------------------------------------------

    var tabListeners = [];

    function initTabs() {
        var list = document.querySelector('.baf-mode-switcher[role="tablist"]');
        if (!list) {
            return;
        }
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));

        function select(tab, focus) {
            tabs.forEach(function (other) {
                var on = other === tab;
                other.setAttribute('aria-selected', on ? 'true' : 'false');
                other.setAttribute('tabindex', on ? '0' : '-1');
                other.classList.toggle('active', on);
                var panel = $(other.getAttribute('aria-controls'));
                if (panel) {
                    panel.hidden = !on;
                    panel.classList.toggle('active', on);
                }
            });
            if (focus) {
                tab.focus();
            }
            tabListeners.forEach(function (fn) { fn(tab.getAttribute('aria-controls')); });
        }

        tabs.forEach(function (tab) {
            tab.setAttribute('tabindex', tab.getAttribute('aria-selected') === 'true' ? '0' : '-1');
            tab.addEventListener('click', function () { select(tab, false); });
        });
        list.addEventListener('keydown', function (e) {
            var i = tabs.indexOf(document.activeElement);
            if (i === -1) {
                return;
            }
            var next = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                next = tabs[(i + 1) % tabs.length];
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                next = tabs[(i - 1 + tabs.length) % tabs.length];
            } else if (e.key === 'Home') {
                next = tabs[0];
            } else if (e.key === 'End') {
                next = tabs[tabs.length - 1];
            }
            if (next) {
                e.preventDefault();
                select(next, true);
            }
        });
    }

    // ------------------------------------------------------------------
    // The model
    // ------------------------------------------------------------------

    var state = { model: null, loading: null, busy: false };

    function formId() {
        return Number(data.formId || 0);
    }

    /** The stored model, read again from the server. */
    function fetchModel() {
        return post('baf_get_form_model', { form_id: String(formId()) }).then(function (json) {
            if (!json.success) {
                throw new Error((json.data && json.data.message) || t('loadFailed', 'The form could not be read.'));
            }
            return normalize(json.data.model);
        });
    }

    /** PHP writes an empty object as []; the screens need objects where the model has them. */
    function normalize(model) {
        ['choice_libraries', 'translations'].forEach(function (k) {
            if (Array.isArray(model[k])) {
                model[k] = {};
            }
        });
        Object.keys(model.translations || {}).forEach(function (lang) {
            if (Array.isArray(model.translations[lang])) {
                model.translations[lang] = {};
            }
        });
        return model;
    }

    function load() {
        if (!state.loading) {
            state.loading = fetchModel().then(function (m) {
                state.model = m;
                return m;
            }, function (e) {
                state.loading = null;
                throw e;
            });
        }
        return state.loading;
    }

    /** Leave out what is empty, so the model is the one BFL would give. */
    function tidy(model) {
        if (Array.isArray(model.rules) && !model.rules.length) {
            delete model.rules;
        }
        ['choice_libraries', 'translations'].forEach(function (k) {
            if (model[k] && !Object.keys(model[k]).length) {
                delete model[k];
            }
        });
        if (model.scoring) {
            if (Array.isArray(model.scoring.outcomes) && !model.scoring.outcomes.length) {
                delete model.scoring.outcomes;
            }
            if (!Object.keys(model.scoring).length) {
                delete model.scoring;
            }
        }
        return model;
    }

    /**
     * Save: read the stored model, let apply() change its own part, save it
     * whole. Resolves { ok, model } or { ok: false, message, errors }.
     */
    function persist(apply) {
        if (state.busy) {
            return Promise.resolve({ ok: false, busy: true, message: t('busy', 'Still saving the last change.'), errors: [] });
        }
        state.busy = true;
        return fetchModel().then(function (fresh) {
            var next = C.clone(fresh);
            apply(next);
            tidy(next);
            return post('baf_save_form_model', { form_id: String(formId()), model: JSON.stringify(next) }).then(function (json) {
                state.busy = false;
                if (json.success) {
                    state.model = normalize(next);
                    return { ok: true, model: state.model };
                }
                var d = json.data || {};
                return { ok: false, message: d.message || t('saveFailed', 'The change was not saved.'), errors: Array.isArray(d.errors) ? d.errors : [] };
            });
        }).catch(function (e) {
            state.busy = false;
            return { ok: false, message: (e && e.message) || t('saveFailed', 'The change was not saved.'), errors: [] };
        });
    }

    // ------------------------------------------------------------------
    // Dialogs
    // ------------------------------------------------------------------

    var openDialog = null;

    /**
     * Open a dialog (the wrapper element with the [role=dialog] or
     * [role=alertdialog] inside). opts: { returnTo, first, onClose }.
     */
    function showDialog(wrapper, opts) {
        opts = opts || {};
        var box = wrapper.querySelector('[role="dialog"], [role="alertdialog"]');
        var page = document.querySelector('.baf-wrap');
        var returnTo = opts.returnTo || document.activeElement;
        wrapper.hidden = false;
        if (page) {
            page.setAttribute('inert', '');
        }

        function keydown(e) {
            if (e.key === 'Escape') {
                if (opts.onEscape && opts.onEscape(e) === false) {
                    return;
                }
                e.preventDefault();
                close();
            } else if (e.key === 'Tab') {
                var f = focusables(box);
                if (!f.length) {
                    e.preventDefault();
                    return;
                }
                if (e.shiftKey && document.activeElement === f[0]) {
                    e.preventDefault();
                    f[f.length - 1].focus();
                } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) {
                    e.preventDefault();
                    f[0].focus();
                }
            }
        }

        function close(focusTarget) {
            wrapper.removeEventListener('keydown', keydown);
            wrapper.hidden = true;
            if (page) {
                page.removeAttribute('inert');
            }
            openDialog = null;
            if (opts.onClose) {
                opts.onClose();
            }
            var target = focusTarget || (typeof returnTo === 'function' ? returnTo() : returnTo);
            if (target && document.body.contains(target)) {
                target.focus();
            }
        }

        wrapper.addEventListener('keydown', keydown);
        Array.prototype.forEach.call(wrapper.querySelectorAll('[data-baf-cancel]'), function (b) {
            b.onclick = function () { close(); };
        });
        var first = opts.first || focusables(box)[0];
        if (first) {
            first.focus();
        }
        openDialog = { wrapper: wrapper, close: close };
        return openDialog;
    }

    /** Ask before deleting. Resolves true for Delete, false for Cancel or Escape. */
    function confirmDelete(title, message, returnTo) {
        return new Promise(function (resolve) {
            var wrapper = $('baf-model-confirm');
            $('baf-model-confirm-title').textContent = title;
            $('baf-model-confirm-message').textContent = message;
            var yes = wrapper.querySelector('[data-baf-confirm]');
            var answered = false;
            var d = showDialog(wrapper, {
                returnTo: returnTo,
                first: wrapper.querySelector('[data-baf-cancel]'),
                onClose: function () {
                    if (!answered) {
                        resolve(false);
                    }
                }
            });
            yes.onclick = function () {
                answered = true;
                // Focus is placed by the caller, once the row is gone.
                d.close(document.body);
                resolve(true);
            };
        });
    }

    // ------------------------------------------------------------------
    // Errors: on the field, and in a summary that takes focus
    // ------------------------------------------------------------------

    /**
     * Show problems in a form. fields maps a problem's field name to
     * { control: element to focus, error: element for the message }.
     * problems: [{ field, message, where? }]. The summary lists them as
     * links to their fields and takes focus.
     */
    function showProblems(summary, fields, problems) {
        Object.keys(fields).forEach(function (k) {
            var f = fields[k];
            if (f.error) {
                f.error.textContent = '';
                f.error.hidden = true;
            }
            if (f.control) {
                f.control.removeAttribute('aria-invalid');
            }
        });
        summary.textContent = '';
        if (!problems.length) {
            summary.hidden = true;
            return;
        }
        var h = el('h3', {}, problems.length === 1 ? t('oneProblem', 'There is 1 problem') : t('problems', 'There are %d problems', problems.length));
        var ul = el('ul');
        problems.forEach(function (p) {
            var f = fields[p.field];
            var li = el('li');
            var words = (p.where ? p.where + ': ' : '') + p.message;
            if (f && f.control) {
                // An expression's check line may already say it; it is not said twice.
                var check = f.control.id ? document.getElementById(f.control.id + '-check') : null;
                if (f.error && !(check && check.textContent === p.message)) {
                    f.error.textContent = (f.error.textContent ? f.error.textContent + ' ' : '') + p.message;
                    f.error.hidden = false;
                }
                f.control.setAttribute('aria-invalid', 'true');
                var a = el('a', { href: '#' + (f.control.id || '') }, words);
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    f.control.focus();
                });
                li.appendChild(a);
            } else {
                li.textContent = words;
            }
            ul.appendChild(li);
        });
        summary.appendChild(h);
        summary.appendChild(ul);
        summary.hidden = false;
        summary.focus();
    }

    // ------------------------------------------------------------------
    // Expression fields
    // ------------------------------------------------------------------

    /**
     * Wire an expression text box: its check line (in its aria-describedby)
     * gives the sentence or the error as it is typed; a polite live region
     * says the result once typing pauses, and not again until it changes.
     * opts: { optional, score (offer "score" in the Fields list) }.
     */
    function expressionField(input, opts) {
        opts = opts || {};
        var check = $(input.id + '-check');
        var live = $(input.id + '-live');
        var timer = null;
        var lastSaid = '';

        function words() {
            var r = C.checkExpression(input.value, state.model || {}, { t: t, optional: opts.optional });
            if (r.ok) {
                return r.empty ? t('exprNone', 'No condition.') : t('exprReads', 'Reads: %s.', r.sentence);
            }
            return r.empty ? t('exprWaiting', 'Not filled in yet.') : r.message;
        }

        function update(speak) {
            var w = words();
            if (check) {
                check.textContent = w;
            }
            if (speak && live && w !== lastSaid) {
                lastSaid = w;
                say(live, w);
            }
        }

        input.addEventListener('input', function () {
            update(false);
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { update(true); }, 1200);
        });
        input.addEventListener('blur', function () { window.clearTimeout(timer); });

        var button = $(input.id + '-insert');
        var panel = $(input.id + '-fields');
        if (button && panel) {
            fieldsList(input, button, panel, opts);
        }
        return {
            refresh: function () {
                lastSaid = '';
                update(false);
            }
        };
    }

    /** "Insert a field": a filterable list of the form's fields; choosing one puts #id at the caret. */
    function fieldsList(input, button, panel, opts) {
        var filter = panel.querySelector('input[type="search"]');
        var list = panel.querySelector('ul');
        var count = panel.querySelector('[role="status"]');

        function entries() {
            var out = [];
            if (opts.score) {
                out.push({ text: 'score', name: t('theScore', 'The quiz score') });
            }
            C.fields(state.model || {}).forEach(function (f) {
                out.push({ text: '#' + f.id, name: f.label || '#' + f.id });
            });
            return out;
        }

        function render() {
            var q = (filter.value || '').toLowerCase();
            list.textContent = '';
            var shown = entries().filter(function (e) {
                return !q || e.name.toLowerCase().indexOf(q) !== -1 || e.text.toLowerCase().indexOf(q) !== -1;
            });
            shown.forEach(function (e) {
                var b = el('button', { type: 'button', className: 'button-link' }, t('insertOne', '%1$s (%2$s)', e.name, e.text));
                b.addEventListener('click', function () { insert(e.text); });
                var li = el('li');
                li.appendChild(b);
                list.appendChild(li);
            });
            if (count) {
                say(count, shown.length === 1 ? t('oneField', '1 field.') : t('nFields', '%d fields.', shown.length));
            }
        }

        function setOpen(open) {
            panel.hidden = !open;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                filter.value = '';
                render();
                filter.focus();
            }
        }

        function insert(text) {
            var start = input.selectionStart === null || input.selectionStart === undefined ? input.value.length : input.selectionStart;
            var end = input.selectionEnd === null || input.selectionEnd === undefined ? start : input.selectionEnd;
            var before = input.value.slice(0, start);
            var pad = before && !/\s$/.test(before) ? ' ' : '';
            input.value = before + pad + text + input.value.slice(end);
            setOpen(false);
            input.focus();
            var caret = (before + pad + text).length;
            input.setSelectionRange(caret, caret);
            input.dispatchEvent(new Event('input'));
        }

        button.addEventListener('click', function () { setOpen(panel.hidden); });
        filter.addEventListener('input', render);
        filter.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var firstButton = list.querySelector('button');
                if (firstButton) {
                    firstButton.click();
                }
            }
        });
        panel.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                setOpen(false);
                button.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // A list screen
    // ------------------------------------------------------------------

    /**
     * cfg: {
     *   panel, body (tbody), empty (p), add (button), status, summary: elements,
     *   noun: 'rule' (i18n keys use it), Noun: 'Rule',
     *   list(model) -> array, store(model, list),
     *   cells(obj, i, model) -> [ text, ... ] after the number,
     *   name(obj, i, model) -> text for button names and messages,
     *   edit(obj|null, i|null, done(obj)) -> opens the dialog,
     *   noMove: true for lists whose order does not matter,
     *   extraButtons(obj, i) -> [ { action, text, label, run } ]
     * }
     */
    function listScreen(cfg) {
        var screen = {};

        function rowName(i, obj) {
            return t(cfg.noun + 'Row', cfg.noun + ' %1$d: %2$s', i + 1, cfg.name(obj, i, state.model));
        }

        function button(action, text, label, i) {
            return el('button', { type: 'button', className: 'button button-small', 'data-action': action, 'data-index': String(i), 'aria-label': label }, text);
        }

        screen.render = function (focus) {
            var list = cfg.list(state.model);
            cfg.body.textContent = '';
            list.forEach(function (obj, i) {
                var tr = el('tr', { 'data-index': String(i) });
                tr.appendChild(el('th', { scope: 'row' }, String(i + 1)));
                cfg.cells(obj, i, state.model).forEach(function (c) { tr.appendChild(el('td', {}, c)); });
                var td = el('td', { className: 'baf-row-actions' });
                var name = rowName(i, obj);
                td.appendChild(button('edit', t('edit', 'Edit'), t('editRow', 'Edit %s', name), i));
                if (!cfg.noMove) {
                    var up = button('up', t('moveUp', 'Move up'), t('moveUpRow', 'Move up %s', name), i);
                    var down = button('down', t('moveDown', 'Move down'), t('moveDownRow', 'Move down %s', name), i);
                    if (i === 0) {
                        up.setAttribute('aria-disabled', 'true');
                    }
                    if (i === list.length - 1) {
                        down.setAttribute('aria-disabled', 'true');
                    }
                    td.appendChild(up);
                    td.appendChild(down);
                }
                (cfg.extraButtons ? cfg.extraButtons(obj, i) : []).forEach(function (b) {
                    td.appendChild(button(b.action, b.text, b.label, i));
                });
                td.appendChild(button('delete', t('delete', 'Delete'), t('deleteRow', 'Delete %s', name), i));
                tr.appendChild(td);
                cfg.body.appendChild(tr);
            });
            cfg.empty.hidden = list.length > 0;
            cfg.body.closest('table').hidden = list.length === 0;
            if (focus) {
                screen.focus(focus.index, focus.action);
            }
        };

        /** Focus a row's button, or Add when there is no such row. */
        screen.focus = function (index, action) {
            var b = index === null || index < 0 ? null : cfg.body.querySelector('button[data-index="' + index + '"][data-action="' + (action || 'edit') + '"]');
            (b || cfg.add).focus();
        };

        /** Save a new list; on failure show why at the top of the screen. */
        screen.save = function (list, message, focus) {
            return persist(function (m) { cfg.store(m, list); }).then(function (r) {
                if (r.ok) {
                    showProblems(cfg.summary, {}, []);
                    screen.render(focus);
                    say(cfg.status, message);
                } else if (!r.busy) {
                    screen.fail(r);
                } else {
                    say(cfg.status, r.message);
                }
                return r;
            });
        };

        screen.fail = function (r) {
            var problems = (r.errors || []).map(function (e) {
                var w = C.where(e.path, t);
                return { field: null, where: w.text, message: e.message };
            });
            if (!problems.length) {
                problems = [{ field: null, message: r.message }];
            }
            say(cfg.status, t('notSaved', 'Not saved. The problems are listed at the top of the screen.'));
            showProblems(cfg.summary, {}, problems);
        };

        cfg.body.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-action]');
            if (!b || !state.model) {
                return;
            }
            var i = Number(b.getAttribute('data-index'));
            var list = cfg.list(state.model);
            var obj = list[i];
            var action = b.getAttribute('data-action');
            if (action === 'edit') {
                cfg.edit(C.clone(obj), i, b);
            } else if (action === 'up' || action === 'down') {
                var to = action === 'up' ? i - 1 : i + 1;
                if (to < 0 || to >= list.length) {
                    say(cfg.status, action === 'up' ? t('alreadyFirst', '%s is already first.', t(cfg.noun + 'Cap', cfg.Noun + ' %d', i + 1)) : t('alreadyLast', '%s is already last.', t(cfg.noun + 'Cap', cfg.Noun + ' %d', i + 1)));
                    return;
                }
                screen.save(C.move(list, i, to), t('movedTo', '%1$s is now %2$s.', t(cfg.noun + 'Cap', cfg.Noun + ' %d', i + 1), t(cfg.noun + 'Low', cfg.noun + ' %d', to + 1)), { index: to, action: action });
            } else if (action === 'delete') {
                var name = rowName(i, obj);
                confirmDelete(t('deleteTitle', 'Delete %s?', t(cfg.noun + 'Low', cfg.noun + ' %d', i + 1)), cfg.deleteMessage ? cfg.deleteMessage(obj, i, state.model) : t('deleteMessage', '%s will be deleted from the form.', name.charAt(0).toUpperCase() + name.slice(1)), b).then(function (yes) {
                    if (!yes) {
                        return;
                    }
                    var after = list.slice(0, i).concat(list.slice(i + 1));
                    screen.save(after, t('deleted', '%s deleted.', t(cfg.noun + 'Cap', cfg.Noun + ' %d', i + 1)), { index: C.focusAfterDelete(i, after.length), action: 'edit' }).then(function (r) {
                        if (!r.ok) {
                            screen.focus(i, 'delete');
                        }
                    });
                });
            } else if (cfg.extraButtons) {
                cfg.extraButtons(obj, i).forEach(function (x) {
                    if (x.action === action) {
                        x.run(b);
                    }
                });
            }
        });

        cfg.add.addEventListener('click', function () {
            if (state.model) {
                cfg.edit(null, null, cfg.add);
            }
        });

        return screen;
    }

    // ------------------------------------------------------------------
    // Start
    // ------------------------------------------------------------------

    var screens = {};

    /** Register a screen: { panelId, init(), render() }. */
    function register(name, screen) {
        screens[name] = screen;
    }

    function startScreens(panelId) {
        Object.keys(screens).forEach(function (name) {
            var s = screens[name];
            if (s.panelId !== panelId || s.started) {
                return;
            }
            var panel = $(s.panelId);
            var status = panel.querySelector('.baf-model-status');
            if (!formId()) {
                // Not started: the first question or Save Draft makes the form, and the tab then loads.
                panel.querySelector('.baf-model-needs-save').hidden = false;
                Array.prototype.forEach.call(panel.querySelectorAll('.baf-model-body'), function (b) { b.hidden = true; });
                return;
            }
            s.started = true;
            panel.querySelector('.baf-model-needs-save').hidden = true;
            load().then(function () {
                Array.prototype.forEach.call(panel.querySelectorAll('.baf-model-body'), function (b) { b.hidden = false; });
                s.render();
            }, function (e) {
                s.started = false;
                say(status, e.message);
            });
        });
    }

    window.BAFModelEditor = {
        t: t,
        $: $,
        el: el,
        say: say,
        post: post,
        state: state,
        load: load,
        persist: persist,
        showDialog: showDialog,
        confirmDelete: confirmDelete,
        showProblems: showProblems,
        expressionField: expressionField,
        listScreen: listScreen,
        register: register,
        currentDialog: function () { return openDialog; }
    };

    function init() {
        initTabs();
        tabListeners.push(startScreens);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
