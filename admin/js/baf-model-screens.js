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

    function init() {
        initRules();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.BAFModelScreens = { dialogForm: dialogForm, fieldMap: fieldMap, serverProblems: serverProblems };
}());
