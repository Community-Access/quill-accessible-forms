/**
 * The builder's model screens, on the shared pieces in baf-model-editor.js:
 * Rules, Quiz outcomes, Translations and Choice libraries.
 *
 * @package BAF
 * @since 1.1.0
 */
(function () {
    'use strict';

    var E = window.BAFModelEditor;
    var C = window.BAFModelEditorCore;
    var t = E.t;
    var $ = E.$;
    var el = E.el;

    /** Open a dialog form: set its title, clear its problems, wire submit once. */
    function dialogForm(id, title, onSubmit) {
        var wrapper = $(id);
        var form = wrapper.querySelector('form');
        $(id + '-title').textContent = title;
        E.showProblems($(id + '-summary'), {}, []);
        form.onsubmit = function (e) {
            e.preventDefault();
            onSubmit();
        };
        return wrapper;
    }

    /** The error lines and controls of a dialog's fields: name => [control id, error id]. */
    function fieldMap(pairs) {
        var out = {};
        Object.keys(pairs).forEach(function (k) {
            out[k] = { control: $(pairs[k][0]), error: $(pairs[k][1]) };
        });
        return out;
    }

    /**
     * Problems the server found, for the object this dialog edits, joined to
     * its fields; the rest are listed with where they are.
     */
    function serverProblems(r, screen, match) {
        var out = (r.errors || []).map(function (e) {
            var w = C.where(e.path, t);
            if (w.screen === screen && match(w)) {
                return { field: w.field ? w.field.split('/')[0] : null, message: e.message };
            }
            return { field: null, where: w.text, message: e.message };
        });
        return out.length ? out : [{ field: null, message: r.message }];
    }

    // ------------------------------------------------------------------
    // Rules
    // ------------------------------------------------------------------

    var rules = { panelId: 'baf-rules-panel' };
    var ruleWhen = null;
    var ruleValue = null;
    var ruleScreen = null;

    function ruleFields() {
        var first = $('baf-rule-targets-list').querySelector('input') || $('baf-rule-targets-filter');
        return {
            action: { control: $('baf-rule-action'), error: $('baf-rule-action-error') },
            targets: { control: first, error: $('baf-rule-targets-error') },
            when: { control: $('baf-rule-when'), error: $('baf-rule-when-error') },
            value: { control: $('baf-rule-value'), error: $('baf-rule-value-error') }
        };
    }

    /** The check boxes of the rule's possible targets, keeping what is checked. */
    function renderTargets(action, checked) {
        var list = $('baf-rule-targets-list');
        var q = ($('baf-rule-targets-filter').value || '').toLowerCase();
        list.textContent = '';
        var shown = 0;
        var known = {};
        C.targetChoices(E.state.model, action).forEach(function (c) {
            known[c.id] = true;
            var name = c.page && action !== 'skip_to' ? t('pageName', '%s (page)', c.name) : c.name;
            var hide = q && name.toLowerCase().indexOf(q) === -1 && c.id.toLowerCase().indexOf(q) === -1 && !checked[c.id];
            var id = 'baf-rule-target-' + c.id;
            var li = el('li', hide ? { hidden: true } : {});
            var box = el('input', { type: action === 'skip_to' ? 'radio' : 'checkbox', name: 'baf-rule-target', id: id, value: c.id });
            box.checked = !!checked[c.id];
            li.appendChild(box);
            li.appendChild(el('label', { 'for': id }, name));
            list.appendChild(li);
            if (!hide) {
                shown++;
            }
        });
        // A target the screen cannot find is kept, and shown so it can be unchecked.
        Object.keys(checked).forEach(function (id) {
            if (!known[id] && checked[id]) {
                var li = el('li');
                var box = el('input', { type: 'checkbox', name: 'baf-rule-target', id: 'baf-rule-target-' + id, value: id });
                box.checked = true;
                li.appendChild(box);
                li.appendChild(el('label', { 'for': 'baf-rule-target-' + id }, t('unknownTarget', '#%s (not in this form)', id)));
                list.appendChild(li);
                shown++;
            }
        });
        return shown;
    }

    function checkedTargets() {
        var out = {};
        Array.prototype.forEach.call($('baf-rule-targets-list').querySelectorAll('input:checked'), function (b) { out[b.value] = true; });
        return out;
    }

    function openRule(rule, index, opener) {
        var isNew = rule === null;
        rule = rule || { action: 'show', targets: [], when: '' };
        var action = $('baf-rule-action');
        // An action this screen does not know is offered as itself, so it is saved back unchanged.
        Array.prototype.forEach.call(action.querySelectorAll('option[data-unknown]'), function (o) { o.remove(); });
        if (C.ACTIONS.indexOf(rule.action) === -1) {
            var o = el('option', { value: rule.action, 'data-unknown': 'true' }, t('unknownAction', '%s (kept as it is)', rule.action));
            action.appendChild(o);
        }
        action.value = rule.action;
        $('baf-rule-targets-filter').value = '';
        var checked = {};
        (rule.targets || []).forEach(function (id) { checked[id] = true; });
        renderTargets(rule.action, checked);
        $('baf-rule-when').value = rule.when || '';
        $('baf-rule-value').value = rule.value || '';
        $('baf-rule-value-row').hidden = rule.action !== 'set';
        ruleWhen.refresh();
        ruleValue.refresh();

        var title = isNew ? t('addRuleTitle', 'Add rule') : t('editRuleTitle', 'Edit rule %d', index + 1);
        var wrapper = dialogForm('baf-rule-dialog', title, function () {
            var next = C.clone(rule);
            next.action = action.value;
            next.targets = Object.keys(checkedTargets());
            next.when = $('baf-rule-when').value.trim();
            if (next.action === 'set') {
                next.value = $('baf-rule-value').value.trim();
            } else {
                delete next.value;
            }
            var problems = C.checkRule(next, E.state.model, t);
            if (next.action === 'skip_to' && next.targets.length > 1) {
                problems.push({ field: 'targets', message: t('onePage', 'Choose one page to skip to.') });
            }
            if (problems.length) {
                E.showProblems($('baf-rule-dialog-summary'), ruleFields(), problems);
                return;
            }
            var list = (E.state.model.rules || []).slice();
            var at = isNew ? list.length : index;
            if (isNew) {
                list.push(next);
            } else {
                list[index] = next;
            }
            E.persist(function (m) { m.rules = list; }).then(function (r) {
                if (!r.ok) {
                    E.showProblems($('baf-rule-dialog-summary'), ruleFields(), serverProblems(r, 'rules', function (w) { return w.index === at; }));
                    return;
                }
                d.close(document.body);
                ruleScreen.render({ index: at, action: 'edit' });
                E.say($('baf-rules-status'), (isNew ? t('ruleAdded', 'Rule %1$d added: %2$s.', at + 1, C.ruleSentence(next, E.state.model, t)) : t('ruleSaved', 'Rule %1$d saved: %2$s.', at + 1, C.ruleSentence(next, E.state.model, t))));
            });
        });
        var d = E.showDialog(wrapper, { returnTo: opener, first: action });
    }

    rules.render = function () {
        ruleScreen.render();
    };

    function initRules() {
        if (!$('baf-rules-panel')) {
            return;
        }
        ruleWhen = E.expressionField($('baf-rule-when'));
        ruleValue = E.expressionField($('baf-rule-value'));
        ruleScreen = E.listScreen({
            body: $('baf-rules-rows'),
            empty: $('baf-rules-empty'),
            add: $('baf-rules-add'),
            status: $('baf-rules-status'),
            summary: $('baf-rules-summary'),
            noun: 'rule',
            Noun: 'Rule',
            list: function (m) { return (m && m.rules) || []; },
            store: function (m, list) { m.rules = list; },
            cells: function (r, i, m) { return [C.ruleSentence(r, m, t), C.targetNames(r, m, t)]; },
            name: function (r, i, m) { return C.ruleSentence(r, m, t); },
            edit: openRule
        });
        $('baf-rule-action').addEventListener('change', function () {
            var a = $('baf-rule-action').value;
            $('baf-rule-value-row').hidden = a !== 'set';
            var checked = checkedTargets();
            if (a === 'skip_to') {
                checked = {};
            }
            renderTargets(a, checked);
        });
        $('baf-rule-targets-filter').addEventListener('input', function () {
            var n = renderTargets($('baf-rule-action').value, checkedTargets());
            E.say($('baf-rule-targets-count'), n === 1 ? t('oneTarget', '1 target shown.') : t('nTargets', '%d targets shown.', n));
        });
        $('baf-rule-targets-filter').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        E.register('rules', rules);
    }

    // ------------------------------------------------------------------
    // Choice libraries
    // ------------------------------------------------------------------

    var libraries = { panelId: 'baf-libraries-panel' };
    var libraryScreen = null;
    var libDraft = null; // { name, original, choices } while the dialog is open.

    function libraryList(m) {
        var libs = (m && m.choice_libraries) || {};
        return Object.keys(libs).map(function (name) { return { name: name, choices: libs[name] || [] }; });
    }

    /** Store the libraries in list order; a renamed library is renamed in the fields that use it. */
    function storeLibraries(m, list, renamed) {
        var out = {};
        list.forEach(function (l) { out[l.name] = l.choices; });
        m.choice_libraries = out;
        if (renamed && renamed.from !== renamed.to) {
            C.fields(m).forEach(function (f) {
                if (f.choice_library === renamed.from) {
                    f.choice_library = renamed.to;
                }
                if (f.cascade && f.cascade.library === renamed.from) {
                    f.cascade.library = renamed.to;
                }
            });
        }
    }

    function choiceName(i, c) {
        return t('choiceRow', 'choice %1$d: %2$s', i + 1, c.label || t('noLabel', 'no label'));
    }

    function numberOrRaw(text) {
        var s = String(text).trim();
        if (s === '') {
            return undefined;
        }
        var n = Number(s);
        return isFinite(n) ? n : s;
    }

    /** The choices grid: one row per choice, every input with a real label. */
    function renderChoices(focus) {
        var body = $('baf-library-choice-rows');
        body.textContent = '';
        libDraft.choices.forEach(function (c, i) {
            var tr = el('tr');
            tr.appendChild(el('th', { scope: 'row' }, String(i + 1)));
            [['label', t('colLabel', 'Label')], ['value', t('colValue', 'Value')], ['price', t('colPrice', 'Price')], ['points', t('colPoints', 'Points')]].forEach(function (col) {
                var id = 'baf-library-choice-' + i + '-' + col[0];
                var td = el('td');
                td.appendChild(el('label', { 'for': id, className: 'baf-sr-only' }, t('choiceField', 'Choice %1$d, %2$s', i + 1, col[1])));
                var input = el('input', {
                    type: 'text',
                    id: id,
                    inputmode: col[0] === 'price' || col[0] === 'points' ? 'decimal' : null,
                    autocomplete: 'off',
                    'aria-describedby': id + '-error'
                });
                input.value = c[col[0]] === undefined ? '' : String(c[col[0]]);
                input.addEventListener('input', function () {
                    if (col[0] === 'price' || col[0] === 'points') {
                        var v = numberOrRaw(input.value);
                        if (v === undefined) {
                            delete c[col[0]];
                        } else {
                            c[col[0]] = v;
                        }
                    } else {
                        c[col[0]] = input.value;
                    }
                });
                if (col[0] === 'label') {
                    input.addEventListener('blur', function () {
                        var value = $('baf-library-choice-' + i + '-value');
                        if (value && value.value === '' && input.value.trim() !== '') {
                            c.value = C.slug(input.value) || input.value.trim();
                            value.value = c.value;
                        }
                    });
                }
                td.appendChild(input);
                td.appendChild(el('span', { id: id + '-error', className: 'baf-field-error', hidden: true }));
                tr.appendChild(td);
            });
            var actions = el('td', { className: 'baf-row-actions' });
            var name = choiceName(i, c);
            [['up', t('moveUp', 'Move up')], ['down', t('moveDown', 'Move down')], ['delete', t('delete', 'Delete')]].forEach(function (b) {
                var label = b[0] === 'up' ? t('moveUpRow', 'Move up %s', name) : (b[0] === 'down' ? t('moveDownRow', 'Move down %s', name) : t('deleteRow', 'Delete %s', name));
                var btn = el('button', { type: 'button', className: 'button button-small', 'data-action': b[0], 'data-index': String(i), 'aria-label': label }, b[1]);
                if ((b[0] === 'up' && i === 0) || (b[0] === 'down' && i === libDraft.choices.length - 1)) {
                    btn.setAttribute('aria-disabled', 'true');
                }
                actions.appendChild(btn);
            });
            tr.appendChild(actions);
            body.appendChild(tr);
        });
        $('baf-library-choices').hidden = libDraft.choices.length === 0;
        if (focus) {
            var target = focus.input ? $('baf-library-choice-' + focus.index + '-' + focus.input) : body.querySelector('button[data-index="' + focus.index + '"][data-action="' + focus.action + '"]');
            (target || $('baf-library-add-choice')).focus();
        }
    }

    function libraryFields() {
        var out = {
            name: { control: $('baf-library-name'), error: $('baf-library-name-error') },
            paste: { control: $('baf-library-paste'), error: $('baf-library-paste-error') }
        };
        libDraft.choices.forEach(function (c, i) {
            ['label', 'value', 'price', 'points'].forEach(function (k) {
                out['choice-' + i + '-' + k] = { control: $('baf-library-choice-' + i + '-' + k), error: $('baf-library-choice-' + i + '-' + k + '-error') };
            });
        });
        return out;
    }

    function openLibrary(lib, index, opener) {
        var isNew = lib === null;
        lib = lib || { name: '', choices: [] };
        libDraft = { name: lib.name, original: isNew ? null : lib.name, choices: C.clone(lib.choices) };
        $('baf-library-name').value = lib.name;
        $('baf-library-paste').value = '';
        renderChoices();
        var title = isNew ? t('addLibraryTitle', 'Add library') : t('editLibraryTitle', 'Edit library %s', lib.name);
        var wrapper = dialogForm('baf-library-dialog', title, function () {
            var name = $('baf-library-name').value.trim();
            var choices = libDraft.choices.map(function (c) {
                var o = C.clone(c);
                o.label = String(o.label || '').trim();
                o.value = String(o.value === undefined ? '' : o.value).trim();
                if (o.value === '' && o.label !== '') {
                    o.value = C.slug(o.label) || o.label;
                }
                return o;
            });
            var problems = C.checkLibrary(name, choices, libDraft.original, E.state.model, t);
            if (problems.length) {
                E.showProblems($('baf-library-dialog-summary'), libraryFields(), problems);
                return;
            }
            var list = libraryList(E.state.model);
            var at = isNew ? list.length : index;
            if (isNew) {
                list.push({ name: name, choices: choices });
            } else {
                list[index] = { name: name, choices: choices };
            }
            var renamed = isNew ? null : { from: libDraft.original, to: name };
            E.persist(function (m) { storeLibraries(m, list, renamed); }).then(function (r) {
                if (!r.ok) {
                    E.showProblems($('baf-library-dialog-summary'), libraryFields(), serverProblems(r, 'libraries', function (w) { return w.key === name; }));
                    return;
                }
                d.close(document.body);
                libraryScreen.render({ index: at, action: 'edit' });
                var users = C.libraryUsers(E.state.model, name).length;
                E.say($('baf-libraries-status'), t('librarySaved', 'Library %1$s saved, with %2$d choices.', name, choices.length) + (renamed && renamed.from !== name && users ? ' ' + t('libraryRenamed', 'The questions that used %1$s now use %2$s.', renamed.from, name) : ''));
            });
        });
        var d = E.showDialog(wrapper, { returnTo: opener, first: $('baf-library-name') });
    }

    function renderBuiltins() {
        var body = $('baf-builtin-rows');
        var libs = (window.bafModelEditor && window.bafModelEditor.libraries) || {};
        body.textContent = '';
        Object.keys(libs).forEach(function (name) {
            var tr = el('tr');
            tr.appendChild(el('th', { scope: 'row' }, libs[name]));
            tr.appendChild(el('td', {}, name));
            var td = el('td', { className: 'baf-row-actions' });
            td.appendChild(el('button', { type: 'button', className: 'button button-small', 'data-name': name, 'aria-label': t('copyLibraryRow', 'Copy %s into this form', libs[name]) }, t('copyLibrary', 'Copy into this form')));
            tr.appendChild(td);
            body.appendChild(tr);
        });
        $('baf-builtin-table').hidden = Object.keys(libs).length === 0;
    }

    function copyBuiltin(name, button) {
        var status = $('baf-libraries-status');
        if (E.state.model.choice_libraries && Object.prototype.hasOwnProperty.call(E.state.model.choice_libraries, name)) {
            E.say(status, t('libraryTaken', 'This form already has a library called %s.', name));
            return;
        }
        E.post('baf_get_choice_library', { name: name }).then(function (json) {
            if (!json.success) {
                E.say(status, (json.data && json.data.message) || t('copyFailed', 'The library could not be copied.'));
                return;
            }
            var list = libraryList(E.state.model);
            list.push({ name: name, choices: json.data.choices });
            libraryScreen.save(list, t('libraryCopied', 'Copied %1$s into this form, with %2$d choices. Questions that use %1$s now use this copy.', name, json.data.choices.length), { index: list.length - 1, action: 'edit' });
        }, function () {
            E.say(status, t('copyFailed', 'The library could not be copied.'));
            button.focus();
        });
    }

    libraries.render = function () {
        libraryScreen.render();
        renderBuiltins();
    };

    function initLibraries() {
        if (!$('baf-libraries-panel')) {
            return;
        }
        libraryScreen = E.listScreen({
            body: $('baf-libraries-rows'),
            empty: $('baf-libraries-empty'),
            add: $('baf-libraries-add'),
            status: $('baf-libraries-status'),
            summary: $('baf-libraries-summary'),
            noun: 'library',
            Noun: 'Library',
            noMove: true,
            list: libraryList,
            store: function (m, list) { storeLibraries(m, list, null); },
            cells: function (l, i, m) {
                var users = C.libraryUsers(m, l.name);
                return [l.name, String(l.choices.length), users.length ? C.listNames(users, t) : t('unused', 'No question yet')];
            },
            name: function (l) { return l.name; },
            deleteMessage: function (l, i, m) {
                var users = C.libraryUsers(m, l.name);
                return users.length
                    ? t('deleteLibraryUsed', 'Library %1$s will be deleted. Questions that use it: %2$s. They will take their choices from a built-in library of that name if there is one, or have none.', l.name, C.listNames(users, t))
                    : t('deleteLibrary', 'Library %s will be deleted from the form.', l.name);
            },
            edit: openLibrary
        });

        $('baf-library-choice-rows').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-action]');
            if (!b) {
                return;
            }
            var i = Number(b.getAttribute('data-index'));
            var action = b.getAttribute('data-action');
            var status = $('baf-library-dialog-status');
            if (action === 'delete') {
                libDraft.choices.splice(i, 1);
                var next = C.focusAfterDelete(i, libDraft.choices.length);
                renderChoices({ index: next, action: 'delete' });
                E.say(status, t('choiceDeleted', 'Choice %d deleted.', i + 1));
                return;
            }
            var to = action === 'up' ? i - 1 : i + 1;
            if (to < 0 || to >= libDraft.choices.length) {
                E.say(status, action === 'up' ? t('alreadyFirst', '%s is already first.', t('choiceCap', 'Choice %d', i + 1)) : t('alreadyLast', '%s is already last.', t('choiceCap', 'Choice %d', i + 1)));
                return;
            }
            libDraft.choices = C.move(libDraft.choices, i, to);
            renderChoices({ index: to, action: action });
            E.say(status, t('movedTo', '%1$s is now %2$s.', t('choiceCap', 'Choice %d', i + 1), t('choiceLow', 'choice %d', to + 1)));
        });

        $('baf-library-add-choice').addEventListener('click', function () {
            libDraft.choices.push({ value: '', label: '' });
            renderChoices({ index: libDraft.choices.length - 1, input: 'label' });
        });

        $('baf-library-paste-add').addEventListener('click', function () {
            var text = $('baf-library-paste').value;
            var status = $('baf-library-dialog-status');
            if (text.trim() === '') {
                E.showProblems($('baf-library-dialog-summary'), libraryFields(), [{ field: 'paste', message: t('pasteEmpty', 'Paste the choices first, one a line.') }]);
                return;
            }
            E.post('baf_read_choice_lines', { lines: text }).then(function (json) {
                var d = json.data || {};
                if (!json.success) {
                    E.say(status, d.message || t('pasteFailed', 'The choices could not be read.'));
                    return;
                }
                if (d.errors && d.errors.length) {
                    E.showProblems($('baf-library-dialog-summary'), libraryFields(), d.errors.map(function (er) {
                        return { field: 'paste', message: t('pasteLine', 'Line %1$d: %2$s', er.line, er.message) };
                    }));
                    return;
                }
                E.showProblems($('baf-library-dialog-summary'), libraryFields(), []);
                var first = libDraft.choices.length;
                libDraft.choices = libDraft.choices.concat(d.choices || []);
                $('baf-library-paste').value = '';
                renderChoices({ index: first, input: 'label' });
                var n = (d.choices || []).length;
                var warn = (d.warnings || []).map(function (w) { return t('pasteLine', 'Line %1$d: %2$s', w.line, w.message); }).join(' ');
                E.say(status, (n === 1 ? t('pastedOne', '1 choice added, as choice %d.', first + 1) : t('pastedMany', '%1$d choices added, from choice %2$d.', n, first + 1)) + (warn ? ' ' + warn : ''));
            }, function () {
                E.say(status, t('pasteFailed', 'The choices could not be read.'));
            });
        });

        $('baf-builtin-rows').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-name]');
            if (b && E.state.model) {
                copyBuiltin(b.getAttribute('data-name'), b);
            }
        });
        E.register('libraries', libraries);
    }

    // ------------------------------------------------------------------
    // Quiz outcomes
    // ------------------------------------------------------------------

    var outcomes = { panelId: 'baf-outcomes-panel' };
    var outcomeScreen = null;
    var outcomeWhen = null;

    function outcomeList(m) {
        return (m && m.scoring && m.scoring.outcomes) || [];
    }

    /** Store the outcomes; a renamed outcome keeps its translations. */
    function storeOutcomes(m, list, renamed) {
        m.scoring = m.scoring || {};
        m.scoring.outcomes = list;
        if (renamed && renamed.from !== renamed.to) {
            Object.keys(m.translations || {}).forEach(function (lang) {
                var o = m.translations[lang].outcomes;
                if (o && Object.prototype.hasOwnProperty.call(o, renamed.from)) {
                    o[renamed.to] = o[renamed.from];
                    delete o[renamed.from];
                }
            });
        }
    }

    function outcomeFields() {
        return fieldMap({
            id: ['baf-outcome-id', 'baf-outcome-id-error'],
            when: ['baf-outcome-when', 'baf-outcome-when-error'],
            message: ['baf-outcome-message', 'baf-outcome-message-error']
        });
    }

    function openOutcome(outcome, index, opener) {
        var isNew = outcome === null;
        outcome = outcome || { id: '', when: '', message: '' };
        $('baf-outcome-id').value = outcome.id;
        $('baf-outcome-when').value = outcome.when;
        $('baf-outcome-message').value = outcome.message;
        outcomeWhen.refresh();
        var title = isNew ? t('addOutcomeTitle', 'Add outcome') : t('editOutcomeTitle', 'Edit outcome %d', index + 1);
        var wrapper = dialogForm('baf-outcome-dialog', title, function () {
            var next = C.clone(outcome);
            next.id = $('baf-outcome-id').value.trim();
            next.when = $('baf-outcome-when').value.trim();
            next.message = $('baf-outcome-message').value;
            var problems = C.checkOutcome(next, isNew ? -1 : index, E.state.model, t);
            if (problems.length) {
                E.showProblems($('baf-outcome-dialog-summary'), outcomeFields(), problems);
                return;
            }
            var list = outcomeList(E.state.model).slice();
            var at = isNew ? list.length : index;
            if (isNew) {
                list.push(next);
            } else {
                list[index] = next;
            }
            var renamed = isNew ? null : { from: outcome.id, to: next.id };
            E.persist(function (m) { storeOutcomes(m, list, renamed); }).then(function (r) {
                if (!r.ok) {
                    E.showProblems($('baf-outcome-dialog-summary'), outcomeFields(), serverProblems(r, 'outcomes', function (w) { return w.index === at; }));
                    return;
                }
                d.close(document.body);
                outcomeScreen.render({ index: at, action: 'edit' });
                E.say($('baf-outcomes-status'), isNew ? t('outcomeAdded', 'Outcome %1$d added: %2$s, when %3$s.', at + 1, next.id, C.sentenceOf(next.when, E.state.model)) : t('outcomeSaved', 'Outcome %1$d saved: %2$s, when %3$s.', at + 1, next.id, C.sentenceOf(next.when, E.state.model)));
            });
        });
        var d = E.showDialog(wrapper, { returnTo: opener, first: $('baf-outcome-id') });
    }

    function renderQuizSettings() {
        var s = (E.state.model && E.state.model.scoring) || {};
        $('baf-quiz-show-score').checked = !!s.show_score;
        $('baf-quiz-pass-mark').value = s.pass_mark === undefined ? '' : String(s.pass_mark);
    }

    outcomes.render = function () {
        renderQuizSettings();
        outcomeScreen.render();
    };

    function initOutcomes() {
        if (!$('baf-outcomes-panel')) {
            return;
        }
        outcomeWhen = E.expressionField($('baf-outcome-when'), { score: true });
        outcomeScreen = E.listScreen({
            body: $('baf-outcomes-rows'),
            empty: $('baf-outcomes-empty'),
            add: $('baf-outcomes-add'),
            status: $('baf-outcomes-status'),
            summary: $('baf-outcomes-summary'),
            noun: 'outcome',
            Noun: 'Outcome',
            list: outcomeList,
            store: function (m, list) { storeOutcomes(m, list, null); },
            cells: function (o, i, m) { return [o.id, C.sentenceOf(o.when, m), o.message]; },
            name: function (o, i, m) { return t('outcomeName', '%1$s, when %2$s', o.id, C.sentenceOf(o.when, m)); },
            edit: openOutcome
        });

        $('baf-quiz-settings').addEventListener('submit', function (e) {
            e.preventDefault();
            var show = $('baf-quiz-show-score').checked;
            var raw = $('baf-quiz-pass-mark').value.trim();
            var mark = raw === '' ? undefined : Number(raw);
            var fields = fieldMap({ pass_mark: ['baf-quiz-pass-mark', 'baf-quiz-pass-mark-error'] });
            if (mark !== undefined && !isFinite(mark)) {
                E.showProblems($('baf-outcomes-summary'), fields, [{ field: 'pass_mark', message: t('passMarkNumber', 'The pass mark must be a number, such as 5.') }]);
                return;
            }
            E.persist(function (m) {
                m.scoring = m.scoring || {};
                if (show) {
                    m.scoring.show_score = true;
                } else {
                    delete m.scoring.show_score;
                }
                if (mark === undefined) {
                    delete m.scoring.pass_mark;
                } else {
                    m.scoring.pass_mark = mark;
                }
            }).then(function (r) {
                if (!r.ok) {
                    if (!r.busy) {
                        outcomeScreen.fail(r);
                    }
                    return;
                }
                E.showProblems($('baf-outcomes-summary'), fields, []);
                var words = show ? t('scoreShown', 'The score is shown.') : t('scoreHidden', 'The score is not shown.');
                words += ' ' + (mark === undefined ? t('noPassMark', 'No pass mark.') : t('passMarkIs', 'The pass mark is %s.', String(mark)));
                E.say($('baf-outcomes-status'), t('quizSaved', 'Quiz settings saved. %s', words));
            });
        });

        $('baf-outcome-try').addEventListener('submit', function (e) {
            e.preventDefault();
            var raw = $('baf-outcome-try-score').value.trim();
            var result = $('baf-outcome-try-result');
            if (raw === '' || !isFinite(Number(raw))) {
                E.say(result, t('tryNumber', 'Type a score, such as 6.'));
                return;
            }
            var found = C.tryOutcome(E.state.model, Number(raw));
            E.say(result, found
                ? t('tryGives', 'A score of %1$s gives outcome %2$d, %3$s: %4$s', raw, found.index + 1, found.outcome.id, found.outcome.message)
                : t('tryNone', 'A score of %s gives no outcome; no condition holds.', raw));
        });
        E.register('outcomes', outcomes);
    }

    // ------------------------------------------------------------------

    function init() {
        initRules();
        initOutcomes();
        initLibraries();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.BAFModelScreens = { dialogForm: dialogForm, fieldMap: fieldMap, serverProblems: serverProblems };
}());
