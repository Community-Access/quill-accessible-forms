/**
 * The QAF form model's runtime, in JavaScript.
 *
 * A port of the parts of includes/model/class-qaf-form-model.php that a
 * published form needs: walking the items, scoring, the evaluation context,
 * the quiz outcome, whether an item is shown, and what rules apply. It
 * behaves exactly like the PHP; tests/conformance/models/behaviour.json is
 * the contract, run by tests/js/run-conformance.js.
 *
 * Works in the browser (window.QAFFormModel, after qaf-expression.js) and in
 * Node (module.exports).
 *
 * @package QAF
 * @since 1.1.0
 */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(require('./qaf-expression.js'));
    } else {
        root.QAFFormModel = factory(root.QAFExpression);
    }
}(typeof self !== 'undefined' ? self : this, function (X) {
    'use strict';

    var LAYOUT = ['section', 'columns'];

    function isField(item) {
        return !!item && typeof item.type === 'string' && LAYOUT.indexOf(item.type) === -1;
    }

    function phpStrval(v) {
        if (v === null || v === undefined || v === false) {
            return '';
        }
        if (v === true) {
            return '1';
        }
        return X.str(v);
    }

    function trim(s) {
        return s.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
    }

    function lower(s) {
        return s.replace(/[A-Z]+/g, function (m) { return m.toLowerCase(); });
    }

    /** Every item, depth first, with its page id. */
    function items(model) {
        var out = [];
        (model.pages || []).forEach(function (page) {
            walk(page.items || [], page.id || '', out);
        });
        return out;
    }

    function walk(list, page, out) {
        list.forEach(function (item) {
            out.push({ item: item, page: page });
            if (item.type === 'columns') {
                (item.columns || []).forEach(function (col) {
                    walk(col.items || [], page, out);
                });
            } else if (Array.isArray(item.items)) {
                walk(item.items, page, out);
            }
        });
    }

    /** Fields only, in order, as [id, field] pairs and a lookup. */
    function fields(model) {
        var out = {};
        var order = [];
        items(model).forEach(function (entry) {
            if (isField(entry.item)) {
                if (!Object.prototype.hasOwnProperty.call(out, entry.item.id)) {
                    order.push(entry.item.id);
                }
                out[entry.item.id] = entry.item;
            }
        });
        return { byId: out, order: order };
    }

    /** Points each scored field earns, and the total (QAF_Form_Model::score). */
    function score(model, values) {
        values = values || {};
        var f = fields(model);
        var scores = {};
        var total = 0;
        f.order.forEach(function (id) {
            var field = f.byId[id];
            var answer = Object.prototype.hasOwnProperty.call(values, id) ? values[id] : null;
            var chosen = Array.isArray(answer) ? answer.map(phpStrval) : (X.isEmpty(answer) ? [] : [phpStrval(answer)]);
            var points = 0;
            var has = false;
            var scoring = field.scoring || null;
            if (scoring && Array.isArray(scoring.correct)) {
                has = true;
                var worth = scoring.points === undefined || scoring.points === null ? 1 : scoring.points;
                var fold = !scoring.case_sensitive;
                var norm = function (s) {
                    s = trim(s);
                    return fold ? lower(s) : s;
                };
                var c = scoring.correct.map(phpStrval).map(norm).sort();
                var a = chosen.map(norm).sort();
                var same = a.length === c.length && a.every(function (v, i) { return v === c[i]; });
                if (same && a.length) {
                    points += worth;
                } else if (scoring.partial && a.length && a.every(function (v) { return c.indexOf(v) !== -1; })) {
                    points += worth * a.length / c.length;
                }
            }
            (field.choices || []).forEach(function (choice) {
                if (choice.points !== undefined && choice.points !== null) {
                    has = true;
                    if (chosen.indexOf(phpStrval(choice.value)) !== -1) {
                        points += choice.points;
                    }
                }
            });
            if (has) {
                scores[id] = points;
                total += points;
            }
        });
        return { fields: scores, total: total };
    }

    /** The evaluation context for a set of answers. */
    function context(model, values, extra) {
        var s = score(model, values);
        var ctx = {};
        Object.keys(extra || {}).forEach(function (k) {
            ctx[k] = extra[k];
        });
        ctx.values = values || {};
        ctx.scores = s.fields;
        ctx.score = s.total;
        return ctx;
    }

    function holds(text, ctx) {
        try {
            return X.truthy(X.evaluate(text, ctx));
        } catch (e) {
            // An expression the page cannot read never hides the form.
            return true;
        }
    }

    /** The first outcome whose condition holds, or null. */
    function outcome(model, values, extra) {
        var ctx = context(model, values, extra);
        var list = (model.scoring && model.scoring.outcomes) || [];
        for (var i = 0; i < list.length; i++) {
            if (holds(list[i].when, ctx)) {
                return list[i];
            }
        }
        return null;
    }

    function chain(list, id) {
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (item.id === id) {
                return [item];
            }
            var children = [];
            if (item.type === 'columns') {
                (item.columns || []).forEach(function (col) {
                    children = children.concat(col.items || []);
                });
            } else {
                children = item.items || [];
            }
            var sub = chain(children, id);
            if (sub !== null) {
                sub.unshift(item);
                return sub;
            }
        }
        return null;
    }

    /** Whether an item is shown, given a context from context(). */
    function isShownIn(model, id, ctx) {
        var pages = model.pages || [];
        for (var p = 0; p < pages.length; p++) {
            var page = pages[p];
            var found = chain(page.items || [], id);
            if (found === null) {
                continue;
            }
            if (page.when !== undefined && !holds(page.when, ctx)) {
                return false;
            }
            for (var i = 0; i < found.length; i++) {
                if (found[i].when !== undefined && !holds(found[i].when, ctx)) {
                    return false;
                }
            }
            var shown = true;
            var names = found.map(function (it) { return it.id; }).concat([page.id]);
            var rules = model.rules || [];
            for (var r = 0; r < rules.length; r++) {
                var rule = rules[r];
                var hit = (rule.targets || []).some(function (t) { return names.indexOf(t) !== -1; });
                if (!hit) {
                    continue;
                }
                var h = holds(rule.when, ctx);
                if (rule.action === 'hide' && h) {
                    return false;
                }
                if (rule.action === 'show' && !h) {
                    shown = false;
                }
            }
            return shown;
        }
        return false;
    }

    /** Whether an item is shown for these answers (QAF_Form_Model::is_shown). */
    function isShown(model, id, values, extra) {
        return isShownIn(model, id, context(model, values, extra));
    }

    /** Whether a page is shown: its own condition and show/hide rules naming it. */
    function isPageShownIn(model, pageId, ctx) {
        var pages = model.pages || [];
        for (var p = 0; p < pages.length; p++) {
            var page = pages[p];
            if (page.id !== pageId) {
                continue;
            }
            if (page.when !== undefined && !holds(page.when, ctx)) {
                return false;
            }
            var shown = true;
            (model.rules || []).forEach(function (rule) {
                if ((rule.targets || []).indexOf(pageId) === -1) {
                    return;
                }
                var h = holds(rule.when, ctx);
                if (rule.action === 'hide' && h) {
                    shown = false;
                }
                if (rule.action === 'show' && !h) {
                    shown = false;
                }
            });
            return shown;
        }
        return false;
    }

    /**
     * Everything the page must apply for these answers, in one pass:
     * shown (item ID => bool), pages (page ID => bool), required and
     * disabled (ID => bool, only for items a rule names), set (ID => value)
     * and calculated (ID => value), skipTo (page ID or null), outcome.
     */
    function evaluateForm(model, values, extra) {
        var ctx = context(model, values, extra);
        var result = { shown: {}, pages: {}, required: {}, disabled: {}, set: {}, calculated: {}, skipTo: null, outcome: null, context: ctx };
        items(model).forEach(function (entry) {
            var item = entry.item;
            if (item.id !== undefined) {
                result.shown[item.id] = isShownIn(model, item.id, ctx);
            }
            if (item.calculation !== undefined) {
                try {
                    result.calculated[item.id] = X.evaluate(item.calculation, ctx);
                } catch (e) {
                    result.calculated[item.id] = null;
                }
            }
        });
        (model.pages || []).forEach(function (page) {
            result.pages[page.id] = isPageShownIn(model, page.id, ctx);
        });
        (model.rules || []).forEach(function (rule) {
            var h = holds(rule.when, ctx);
            (rule.targets || []).forEach(function (t) {
                switch (rule.action) {
                    case 'require':
                        result.required[t] = result.required[t] === true || h;
                        break;
                    case 'optional':
                        if (h) {
                            result.required[t] = false;
                        }
                        break;
                    case 'disable':
                        result.disabled[t] = result.disabled[t] === true || h;
                        break;
                    case 'enable':
                        if (!Object.prototype.hasOwnProperty.call(result.disabled, t)) {
                            result.disabled[t] = !h;
                        } else if (h) {
                            result.disabled[t] = false;
                        }
                        break;
                    case 'set':
                        if (h && rule.value !== undefined) {
                            try {
                                result.set[t] = X.evaluate(rule.value, ctx);
                            } catch (e) {
                                // Leave the field as it is.
                            }
                        }
                        break;
                    case 'skip_to':
                        if (h && result.skipTo === null) {
                            result.skipTo = t;
                        }
                        break;
                }
            });
        });
        result.outcome = outcome(model, values, extra);
        return result;
    }

    /** The value a smart default gives, or null. */
    function smartDefault(item, extra) {
        if (item.smart_default === undefined) {
            return null;
        }
        try {
            return X.evaluate(item.smart_default, extra || {});
        } catch (e) {
            return null;
        }
    }

    return {
        items: items,
        fields: fields,
        score: score,
        context: context,
        outcome: outcome,
        isShown: isShown,
        isPageShown: function (model, pageId, values, extra) {
            return isPageShownIn(model, pageId, context(model, values, extra));
        },
        evaluateForm: evaluateForm,
        smartDefault: smartDefault
    };
}));
