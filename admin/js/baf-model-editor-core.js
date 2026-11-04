/**
 * The logic behind the builder's model screens (rules, quiz outcomes,
 * translations, choice libraries), kept apart from the page so Node can
 * test it: the expression check and its read-back, each rule as a sentence,
 * JSON pointer paths as words, list moves, translation rows and counts,
 * which fields use a library, and the quiz "Try it".
 *
 * Works in the browser (window.BAFModelEditorCore, after baf-expression.js)
 * and in Node (require). No DOM, no dependencies beyond the evaluator.
 *
 * @package BAF
 * @since 1.1.0
 */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(require('../../public/js/baf-expression.js'));
    } else {
        root.BAFModelEditorCore = factory(root.BAFExpression);
    }
}(typeof self !== 'undefined' ? self : this, function (X) {
    'use strict';

    var LAYOUT = { section: true, columns: true };

    /** The default translator: English, with %s, %d and %1$s style slots. */
    function format(text, args) {
        var i = 0;
        return String(text).replace(/%(?:(\d+)\$)?([sd])/g, function (m, n) {
            var v = n ? args[Number(n) - 1] : args[i++];
            return v === undefined ? m : String(v);
        });
    }

    function translator(t) {
        return function (key, fallback) {
            var args = Array.prototype.slice.call(arguments, 2);
            var text = (t && t[key]) || fallback;
            return format(text, args);
        };
    }

    function clone(v) {
        return v === undefined ? undefined : JSON.parse(JSON.stringify(v));
    }

    // ------------------------------------------------------------------
    // The form's items
    // ------------------------------------------------------------------

    /** Every item, depth first, with its page: [{ item, page }]. */
    function items(model) {
        var out = [];
        function walk(list, page) {
            (list || []).forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }
                out.push({ item: item, page: page });
                if (item.type === 'columns') {
                    (item.columns || []).forEach(function (col) { walk(col.items, page); });
                } else if (Array.isArray(item.items)) {
                    walk(item.items, page);
                }
            });
        }
        ((model && model.pages) || []).forEach(function (p) { walk(p.items, p.id); });
        return out;
    }

    /** The fields (not sections or columns), in order. */
    function fields(model) {
        return items(model).filter(function (e) { return e.item.id && !LAYOUT[e.item.type]; }).map(function (e) { return e.item; });
    }

    /** Item ID => label, as BAF_Form_Model::labels(). */
    function labels(model) {
        var out = {};
        items(model).forEach(function (e) {
            if (e.item.id) {
                out[e.item.id] = e.item.label || '';
            }
        });
        return out;
    }

    /** Field ID => (value => label), as BAF_Form_Model::choice_labels(). */
    function choiceLabels(model) {
        var out = {};
        fields(model).forEach(function (f) {
            var list = f.choices || (f.choice_library && model.choice_libraries ? model.choice_libraries[f.choice_library] : null) || [];
            list.forEach(function (c) {
                out[f.id] = out[f.id] || {};
                out[f.id][c.value] = c.label;
            });
        });
        return out;
    }

    /** What a person calls an item: its label, else its ID. */
    function itemName(model, id) {
        var l = labels(model)[id];
        return l ? l : '#' + id;
    }

    function pageName(model, id) {
        var pages = (model && model.pages) || [];
        for (var i = 0; i < pages.length; i++) {
            if (pages[i].id === id) {
                return pages[i].title ? pages[i].title : '#' + id;
            }
        }
        return '#' + id;
    }

    /**
     * What a rule's targets can be: every item by label (a show rule can
     * name a section), and for skip to, the pages only.
     */
    function targetChoices(model, action) {
        if (action === 'skip_to') {
            return ((model && model.pages) || []).map(function (p) { return { id: p.id, name: p.title || '#' + p.id }; });
        }
        var out = items(model).filter(function (e) { return e.item.id; }).map(function (e) {
            return { id: e.item.id, name: e.item.label || '#' + e.item.id };
        });
        ((model && model.pages) || []).forEach(function (p) {
            out.push({ id: p.id, name: p.title || '#' + p.id, page: true });
        });
        return out;
    }

    /** "A", "A and B", "A, B and C". */
    function listNames(names, t) {
        t = t || translator();
        if (names.length <= 1) {
            return names.join('');
        }
        return t('listAnd', '%1$s and %2$s', names.slice(0, -1).join(', '), names[names.length - 1]);
    }

    // ------------------------------------------------------------------
    // Expressions: the live check
    // ------------------------------------------------------------------

    /** A UTF-8 byte offset as a 1-based character position. */
    function charPosition(text, byteOffset) {
        var bytes = 0;
        var chars = 0;
        var s = String(text);
        for (var i = 0; i < s.length; i++) {
            if (bytes >= byteOffset) {
                break;
            }
            var c = s.charCodeAt(i);
            if (c >= 0xD800 && c <= 0xDBFF && i + 1 < s.length) {
                bytes += 4;
                i++;
            } else {
                bytes += c < 0x80 ? 1 : (c < 0x800 ? 2 : 3);
            }
            chars++;
        }
        return chars + 1;
    }

    /**
     * Check an expression the way the server will, and read it back.
     *
     * @param {string} text Expression text.
     * @param {object} model The form model (for labels and IDs).
     * @param {object} [opts] { t, optional: empty is fine, score: score allowed }.
     * @return {{ok:boolean, empty?:boolean, sentence?:string, canonical?:string, message?:string, position?:number}}
     */
    function checkExpression(text, model, opts) {
        opts = opts || {};
        var t = opts.t || translator();
        var s = String(text === undefined || text === null ? '' : text);
        if (s.trim() === '') {
            return opts.optional ? { ok: true, empty: true, sentence: '' } : { ok: false, empty: true, message: t('exprEmpty', 'Type a condition, such as #type is "business".') };
        }
        var node;
        try {
            node = X.parse(s);
        } catch (e) {
            if (!(e instanceof X.ExpressionError)) {
                throw e;
            }
            var pos = charPosition(s, e.offset || 0);
            return { ok: false, message: t('exprAt', '%1$s At character %2$d.', e.message, pos), position: pos };
        }
        var known = labels(model);
        var missing = X.refs(node).filter(function (id) { return !Object.prototype.hasOwnProperty.call(known, id); });
        if (missing.length) {
            return { ok: false, message: t('exprUnknownRef', 'There is no #%s in this form.', missing[0]) };
        }
        return { ok: true, sentence: X.toSentence(node, known, choiceLabels(model)), canonical: X.toText(node) };
    }

    /** The sentence for an expression, or the text itself when it does not parse. */
    function sentenceOf(text, model) {
        try {
            return X.toSentence(X.parse(text), labels(model), choiceLabels(model));
        } catch (e) {
            return String(text);
        }
    }

    // ------------------------------------------------------------------
    // Rules
    // ------------------------------------------------------------------

    var ACTIONS = ['show', 'hide', 'require', 'optional', 'enable', 'disable', 'set', 'skip_to'];

    /** A rule's targets by name. */
    function targetNames(rule, model, t) {
        var ids = Array.isArray(rule.targets) ? rule.targets : [];
        return listNames(ids.map(function (id) {
            return rule.action === 'skip_to' ? pageName(model, id) : (labels(model)[id] !== undefined ? itemName(model, id) : pageName(model, id));
        }), t);
    }

    /** "Require Phone when Contact me by is Phone". */
    function ruleSentence(rule, model, t) {
        t = t || translator();
        var who = targetNames(rule, model, t);
        var when = sentenceOf(rule.when, model);
        switch (rule.action) {
            case 'show': return t('ruleShow', 'Show %1$s when %2$s', who, when);
            case 'hide': return t('ruleHide', 'Hide %1$s when %2$s', who, when);
            case 'require': return t('ruleRequire', 'Require %1$s when %2$s', who, when);
            case 'optional': return t('ruleOptional', 'Make %1$s optional when %2$s', who, when);
            case 'enable': return t('ruleEnable', 'Enable %1$s when %2$s', who, when);
            case 'disable': return t('ruleDisable', 'Disable %1$s when %2$s', who, when);
            case 'set': return t('ruleSet', 'Set %1$s to %2$s when %3$s', who, sentenceOf(rule.value, model), when);
            case 'skip_to': return t('ruleSkip', 'Skip to %1$s when %2$s', who, when);
            default: return t('ruleOther', '%1$s %2$s when %3$s', String(rule.action), who, when);
        }
    }

    /** Problems with a rule before it is saved: [{ field, message }]. */
    function checkRule(rule, model, t) {
        t = t || translator();
        var out = [];
        if (!rule.action) {
            out.push({ field: 'action', message: t('needAction', 'Choose what the rule does.') });
        }
        if (!rule.targets || !rule.targets.length) {
            out.push({ field: 'targets', message: rule.action === 'skip_to' ? t('needPage', 'Choose the page to skip to.') : t('needTargets', 'Choose at least one question for the rule.') });
        }
        var w = checkExpression(rule.when, model, { t: t });
        if (!w.ok) {
            out.push({ field: 'when', message: w.message });
        }
        if (rule.action === 'set') {
            var v = checkExpression(rule.value, model, { t: t });
            if (!v.ok) {
                out.push({ field: 'value', message: v.empty ? t('needValue', 'Type the value to set, such as #price * 2.') : v.message });
            }
        }
        return out;
    }

    // ------------------------------------------------------------------
    // Quiz outcomes
    // ------------------------------------------------------------------

    /** The outcome a given score picks: the first whose condition holds. */
    function tryOutcome(model, score) {
        var list = (model && model.scoring && model.scoring.outcomes) || [];
        var ctx = { values: {}, scores: {}, score: Number(score) };
        for (var i = 0; i < list.length; i++) {
            var holds;
            try {
                holds = X.truthy(X.evaluate(list[i].when, ctx));
            } catch (e) {
                holds = true; // As the published form: an unreadable condition never hides.
            }
            if (holds) {
                return { index: i, outcome: list[i] };
            }
        }
        return null;
    }

    var ID_PATTERN = /^[A-Za-z_][A-Za-z0-9_]*$/;

    function checkOutcome(outcome, index, model, t) {
        t = t || translator();
        var out = [];
        if (!ID_PATTERN.test(outcome.id || '')) {
            out.push({ field: 'id', message: t('badId', 'An ID uses letters, digits and underscores, and does not start with a digit.') });
        } else {
            ((model.scoring && model.scoring.outcomes) || []).forEach(function (o, i) {
                if (i !== index && o.id === outcome.id) {
                    out.push({ field: 'id', message: t('idTaken', 'Another outcome is already called %s.', outcome.id) });
                }
            });
        }
        var w = checkExpression(outcome.when, model, { t: t });
        if (!w.ok) {
            out.push({ field: 'when', message: w.message });
        }
        if (String(outcome.message || '').trim() === '') {
            out.push({ field: 'message', message: t('needMessage', 'Type the message the person sees.') });
        }
        return out;
    }

    // ------------------------------------------------------------------
    // Choice libraries
    // ------------------------------------------------------------------

    /** The fields that take their choices from a library, by name. */
    function libraryUsers(model, name) {
        return fields(model).filter(function (f) {
            return f.choice_library === name || (f.cascade && f.cascade.library === name);
        }).map(function (f) { return f.label || '#' + f.id; });
    }

    function checkLibrary(name, choices, originalName, model, t) {
        t = t || translator();
        var out = [];
        if (!ID_PATTERN.test(name || '')) {
            out.push({ field: 'name', message: t('badLibraryName', 'A library name uses letters, digits and underscores, and does not start with a digit.') });
        } else if (name !== originalName && model.choice_libraries && Object.prototype.hasOwnProperty.call(model.choice_libraries, name)) {
            out.push({ field: 'name', message: t('libraryTaken', 'This form already has a library called %s.', name) });
        }
        var seen = {};
        (choices || []).forEach(function (c, i) {
            if (String(c.label || '').trim() === '') {
                out.push({ field: 'choice-' + i + '-label', message: t('needChoiceLabel', 'Choice %d needs a label.', i + 1) });
            }
            if (String(c.value || '') === '') {
                out.push({ field: 'choice-' + i + '-value', message: t('needChoiceValue', 'Choice %d needs a value.', i + 1) });
            } else if (seen[c.value]) {
                out.push({ field: 'choice-' + i + '-value', message: t('choiceValueTwice', 'Choice %1$d has the value %2$s, which choice %3$d has too.', i + 1, c.value, seen[c.value]) });
            } else {
                seen[c.value] = i + 1;
            }
            ['price', 'points'].forEach(function (k) {
                if (c[k] !== undefined && (typeof c[k] !== 'number' || !isFinite(c[k]))) {
                    out.push({ field: 'choice-' + i + '-' + k, message: t('needNumber', 'Choice %1$d: the %2$s must be a number.', i + 1, k) });
                }
            });
        });
        return out;
    }

    /** A value from a label, as BAF_Form_Model::slug (lower case, hyphens). */
    function slug(label) {
        var s = String(label).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return s;
    }

    // ------------------------------------------------------------------
    // Translations
    // ------------------------------------------------------------------

    var MESSAGES = ['required', 'invalid_email', 'submit', 'next', 'previous'];

    /**
     * Every text a translation can hold, with the form's own text and the
     * translation's, in reading order.
     *
     * @return {Array<{path:string[], what:string, source:string, value:string}>}
     */
    function translationRows(model, lang, t) {
        t = t || translator();
        var tr = (model.translations && model.translations[lang]) || {};
        var rows = [];
        function get(path) {
            var v = tr;
            for (var i = 0; i < path.length; i++) {
                if (!v || typeof v !== 'object' || !Object.prototype.hasOwnProperty.call(v, path[i])) {
                    return '';
                }
                v = v[path[i]];
            }
            return typeof v === 'string' ? v : '';
        }
        function row(path, what, source) {
            rows.push({ path: path, what: what, source: source === undefined || source === null ? '' : String(source), value: get(path) });
        }
        row(['title'], t('trFormTitle', 'Form title'), model.title);
        if (model.description) {
            row(['description'], t('trFormDescription', 'Form description'), model.description);
        }
        (model.pages || []).forEach(function (p, pi) {
            var name = p.title || t('trPageN', 'Page %d', pi + 1);
            if (p.title) {
                row(['pages', p.id, 'title'], t('trPageTitle', 'Page %s, title', name), p.title);
            }
            if (p.description) {
                row(['pages', p.id, 'description'], t('trPageDescription', 'Page %s, description', name), p.description);
            }
        });
        items(model).forEach(function (e) {
            var f = e.item;
            if (!f.id || f.type === 'columns') {
                return;
            }
            var name = f.label || '#' + f.id;
            if (f.label) {
                row(['fields', f.id, 'label'], t('trLabel', '%s, label', name), f.label);
            }
            if (f.help) {
                row(['fields', f.id, 'help'], t('trHelp', '%s, help', name), f.help);
            }
            if (f.placeholder) {
                row(['fields', f.id, 'placeholder'], t('trPlaceholder', '%s, placeholder', name), f.placeholder);
            }
            (f.choices || []).forEach(function (c) {
                row(['fields', f.id, 'choices', c.value], t('trChoice', '%1$s, choice %2$s', name, c.label), c.label);
            });
        });
        (model.confirmations || []).forEach(function (c) {
            if (c.message) {
                row(['confirmations', c.id, 'message'], t('trConfirmation', 'Confirmation %s, message', c.id), c.message);
            }
        });
        ((model.scoring && model.scoring.outcomes) || []).forEach(function (o) {
            row(['outcomes', o.id, 'message'], t('trOutcome', 'Quiz outcome %s, message', o.id), o.message);
        });
        var msgs = MESSAGES.slice();
        Object.keys(tr.messages || {}).forEach(function (k) {
            if (msgs.indexOf(k) === -1) {
                msgs.push(k);
            }
        });
        msgs.forEach(function (k) {
            row(['messages', k], t('trMessage', 'Built-in message %s', k), '');
        });
        return rows;
    }

    /** How much of a language is translated. */
    function translationCount(model, lang) {
        var rows = translationRows(model, lang);
        return { done: rows.filter(function (r) { return r.value !== ''; }).length, total: rows.length };
    }

    /**
     * Set one text in a translation (a copy is returned). An empty text is
     * removed, and so is any object it leaves empty, so the translation
     * holds only what was translated; keys this screen does not know stay.
     */
    function setTranslation(tr, path, value) {
        var out = clone(tr || {});
        var chain = [out];
        var o = out;
        for (var i = 0; i < path.length - 1; i++) {
            if (!o[path[i]] || typeof o[path[i]] !== 'object' || Array.isArray(o[path[i]])) {
                o[path[i]] = {};
            }
            o = o[path[i]];
            chain.push(o);
        }
        var last = path[path.length - 1];
        if (value === '') {
            delete o[last];
            for (var j = path.length - 2; j >= 0; j--) {
                if (Object.keys(chain[j + 1]).length === 0) {
                    delete chain[j][path[j]];
                } else {
                    break;
                }
            }
        } else {
            o[last] = value;
        }
        return out;
    }

    var LANGUAGE = /^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/;

    function checkLanguage(code, model, t) {
        t = t || translator();
        if (!LANGUAGE.test(code || '')) {
            return [{ field: 'code', message: t('badLanguage', 'Type a language code such as es, fr or pt-BR.') }];
        }
        if (model.translations && Object.prototype.hasOwnProperty.call(model.translations, code)) {
            return [{ field: 'code', message: t('languageTaken', 'This form already has a translation for %s.', code) }];
        }
        if (code === (model.language || '')) {
            return [{ field: 'code', message: t('languageOwn', '%s is the form\'s own language.', code) }];
        }
        return [];
    }

    // ------------------------------------------------------------------
    // A question's scoring (the Visual mode's Scoring section)
    // ------------------------------------------------------------------

    /** The JSON pointer of an item, as BAF_Form_Model::items() gives it, or null. */
    function itemPointer(model, id) {
        var found = null;
        function walk(list, path) {
            (list || []).forEach(function (item, i) {
                if (found || !item || typeof item !== 'object') {
                    return;
                }
                var p = path + '/' + i;
                if (item.id === id) {
                    found = p;
                    return;
                }
                if (item.type === 'columns') {
                    (item.columns || []).forEach(function (col, ci) { walk(col.items, p + '/columns/' + ci + '/items'); });
                } else if (Array.isArray(item.items)) {
                    walk(item.items, p + '/items');
                }
            });
        }
        ((model && model.pages) || []).forEach(function (pg, pi) { walk(pg.items, '/pages/' + pi + '/items'); });
        return found;
    }

    function findItem(model, id) {
        var all = items(model);
        for (var i = 0; i < all.length; i++) {
            if (all[i].item.id === id) {
                return all[i].item;
            }
        }
        return null;
    }

    function parseNumber(text) {
        var s = String(text === undefined || text === null ? '' : text).trim();
        if (s === '') {
            return { empty: true };
        }
        var n = Number(s);
        return isFinite(n) && /^-?(\d+\.?\d*|\.\d+)$/.test(s) ? { value: n } : { bad: true };
    }

    /**
     * Put one question's Scoring section into a model (a copy is returned):
     * its scoring, and its own choices' points; nothing else is touched.
     *
     * input: { points, correct: [values], partial, case_sensitive,
     *          feedback_correct, feedback_incorrect, feedback_partial,
     *          explanation, choicePoints: { index: text } } -- a key left
     *          out is a control the section did not show, kept as it was.
     *
     * @return {{model:object|null, problems:Array<{field, message}>}}
     */
    function applyScoring(model, fieldId, input, t) {
        t = t || translator();
        var out = clone(model);
        var field = findItem(out, fieldId);
        var problems = [];
        if (!field) {
            return { model: null, problems: [{ field: null, message: t('scoringNoField', 'This question is no longer in the form; reload the builder.') }] };
        }
        var s = field.scoring && typeof field.scoring === 'object' && !Array.isArray(field.scoring) ? field.scoring : {};
        if (input.points !== undefined) {
            var p = parseNumber(input.points);
            if (p.bad) {
                problems.push({ field: 'points', message: t('scoringPoints', 'The points must be a number, such as 2.') });
            } else if (p.empty) {
                delete s.points;
            } else {
                s.points = p.value;
            }
        }
        if (input.correct !== undefined) {
            var list = input.correct.map(function (v) { return String(v).trim(); }).filter(function (v) { return v !== ''; });
            var seen = {};
            list = list.filter(function (v) { return seen[v] ? false : (seen[v] = true); });
            if (list.length) {
                s.correct = list;
            } else {
                delete s.correct;
            }
        }
        ['partial', 'case_sensitive'].forEach(function (k) {
            if (input[k] === undefined) {
                return;
            }
            if (input[k]) {
                s[k] = true;
            } else if (Object.prototype.hasOwnProperty.call(s, k)) {
                s[k] = false; // An explicit "no" stays explicit; one never written is not added.
            }
        });
        ['feedback_correct', 'feedback_incorrect', 'feedback_partial', 'explanation'].forEach(function (k) {
            if (input[k] === undefined) {
                return;
            }
            if (String(input[k]).trim() === '') {
                delete s[k];
            } else {
                s[k] = String(input[k]);
            }
        });
        if (Object.keys(s).length || field.type === 'quiz') {
            field.scoring = s; // A quiz question must keep its scoring, even empty.
        } else {
            delete field.scoring;
        }
        Object.keys(input.choicePoints || {}).forEach(function (i) {
            var c = field.choices && field.choices[Number(i)];
            if (!c) {
                return;
            }
            var n = parseNumber(input.choicePoints[i]);
            if (n.bad) {
                problems.push({ field: 'choice-' + i + '-points', message: t('scoringChoicePoints', 'The points for %s must be a number, such as 1.', c.label) });
            } else if (n.empty) {
                delete c.points;
            } else {
                c.points = n.value;
            }
        });
        return { model: problems.length ? null : out, problems: problems };
    }

    /**
     * A problem the server found, joined to the Scoring section's field when
     * it is about this question: "/pages/0/items/2/scoring/points" is "points".
     */
    function scoringField(model, fieldId, pointer) {
        var base = itemPointer(model, fieldId);
        var p = String(pointer || '');
        if (!base || p.indexOf(base + '/') !== 0) {
            return null;
        }
        var rest = p.slice(base.length + 1).split('/');
        if (rest[0] === 'scoring') {
            return rest[1] === 'correct' ? 'correct' : (rest[1] || 'points');
        }
        if (rest[0] === 'choices' && rest[2] === 'points') {
            return 'choice-' + rest[1] + '-points';
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Lists and paths
    // ------------------------------------------------------------------

    /** A copy of a list with one entry moved. */
    function move(list, from, to) {
        var out = list.slice();
        if (to < 0 || to >= out.length || from === to) {
            return out;
        }
        var e = out.splice(from, 1)[0];
        out.splice(to, 0, e);
        return out;
    }

    /** After a delete, which row takes focus: the one now there, else the one before, else none (-1). */
    function focusAfterDelete(index, lengthAfter) {
        if (lengthAfter === 0) {
            return -1;
        }
        return index < lengthAfter ? index : lengthAfter - 1;
    }

    var PARTS = {
        when: ['pWhen', 'condition'],
        value: ['pValue', 'value'],
        targets: ['pTargets', 'targets'],
        action: ['pAction', 'action'],
        message: ['pMessage', 'message'],
        id: ['pId', 'ID']
    };

    /**
     * A JSON pointer from the server, as words and as a place on a screen.
     * "/rules/2/when" is { screen: 'rules', index: 2, field: 'when', text: 'Rule 3, condition' }.
     */
    function where(pointer, t) {
        t = t || translator();
        var p = String(pointer || '').split('/').slice(1).map(function (s) { return s.replace(/~1/g, '/').replace(/~0/g, '~'); });
        function part(k) {
            return PARTS[k] ? t(PARTS[k][0], PARTS[k][1]) : k;
        }
        if (p[0] === 'rules' && p.length >= 2) {
            return { screen: 'rules', index: Number(p[1]), field: p[2] || null, text: p[2] ? t('whereRulePart', 'Rule %1$d, %2$s', Number(p[1]) + 1, part(p[2])) : t('whereRule', 'Rule %d', Number(p[1]) + 1) };
        }
        if (p[0] === 'scoring' && p[1] === 'outcomes' && p.length >= 3) {
            return { screen: 'outcomes', index: Number(p[2]), field: p[3] || null, text: p[3] ? t('whereOutcomePart', 'Outcome %1$d, %2$s', Number(p[2]) + 1, part(p[3])) : t('whereOutcome', 'Outcome %d', Number(p[2]) + 1) };
        }
        if (p[0] === 'scoring') {
            return { screen: 'outcomes', index: null, field: p[1] || null, text: t('whereScoring', 'Quiz settings') };
        }
        if (p[0] === 'translations' && p.length >= 2) {
            return { screen: 'translations', key: p[1], field: p.slice(2).join('/') || null, text: t('whereTranslation', 'Translation %s', p[1]) };
        }
        if (p[0] === 'choice_libraries' && p.length >= 2) {
            return { screen: 'libraries', key: p[1], field: p[2] !== undefined ? 'choice-' + p[2] + (p[3] ? '-' + p[3] : '') : null, text: p[2] !== undefined ? t('whereLibraryChoice', 'Library %1$s, choice %2$d', p[1], Number(p[2]) + 1) : t('whereLibrary', 'Library %s', p[1]) };
        }
        if (p[0] === 'pages' && p.length >= 2) {
            if (p[2] === 'items' && p.length >= 4) {
                return { screen: null, text: t('wherePageItem', 'Page %1$d, item %2$d', Number(p[1]) + 1, Number(p[3]) + 1) };
            }
            return { screen: null, text: t('wherePage', 'Page %d', Number(p[1]) + 1) };
        }
        return { screen: null, text: pointer ? String(pointer) : t('whereForm', 'The form') };
    }

    return {
        ACTIONS: ACTIONS,
        MESSAGES: MESSAGES,
        format: format,
        translator: translator,
        clone: clone,
        items: items,
        fields: fields,
        labels: labels,
        choiceLabels: choiceLabels,
        itemName: itemName,
        pageName: pageName,
        targetChoices: targetChoices,
        listNames: listNames,
        charPosition: charPosition,
        checkExpression: checkExpression,
        sentenceOf: sentenceOf,
        targetNames: targetNames,
        ruleSentence: ruleSentence,
        checkRule: checkRule,
        tryOutcome: tryOutcome,
        checkOutcome: checkOutcome,
        libraryUsers: libraryUsers,
        checkLibrary: checkLibrary,
        slug: slug,
        translationRows: translationRows,
        translationCount: translationCount,
        setTranslation: setTranslation,
        checkLanguage: checkLanguage,
        move: move,
        focusAfterDelete: focusAfterDelete,
        itemPointer: itemPointer,
        findItem: findItem,
        applyScoring: applyScoring,
        scoringField: scoringField,
        where: where
    };
}));
