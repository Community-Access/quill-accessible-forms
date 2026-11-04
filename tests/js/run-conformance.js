#!/usr/bin/env node
/**
 * Runs the conformance suite's expression and behaviour cases against the
 * JavaScript copy of the evaluator (public/js/baf-expression.js) and the
 * model runtime (public/js/baf-form-model.js), so the published form and the
 * server agree. No dependencies: `node tests/js/run-conformance.js`.
 *
 * Exit status 0 when every case passes.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var X = require('../../public/js/baf-expression.js');
var M = require('../../public/js/baf-form-model.js');

var root = path.join(__dirname, '..', 'conformance');
var manifest = JSON.parse(fs.readFileSync(path.join(root, 'manifest.json'), 'utf8'));
var failures = [];
var passed = 0;

function json(rel) {
    return JSON.parse(fs.readFileSync(path.join(root, rel), 'utf8'));
}

/** The README's comparison rules: key order free, numbers equal, booleans are not numbers. */
function same(a, b) {
    if (typeof a === 'number' && typeof b === 'number') {
        return Math.abs(a - b) <= 1e-9;
    }
    if (a === null || b === null || typeof a !== 'object' || typeof b !== 'object') {
        return a === b;
    }
    if (Array.isArray(a) !== Array.isArray(b)) {
        // PHP decodes {} and [] alike; an empty object equals an empty list.
        return Object.keys(a).length === 0 && Object.keys(b).length === 0;
    }
    var ka = Object.keys(a);
    var kb = Object.keys(b);
    if (ka.length !== kb.length) {
        return false;
    }
    return ka.every(function (k) { return Object.prototype.hasOwnProperty.call(b, k) && same(a[k], b[k]); });
}

function check(ok, what, detail) {
    if (ok) {
        passed++;
    } else {
        failures.push(what + (detail ? ': ' + detail : ''));
    }
}

function attempt(what, fn) {
    try {
        fn();
    } catch (e) {
        failures.push(what + ': threw ' + e.message);
    }
}

function runExpressions(file) {
    var cases = json(file);
    cases.parse.forEach(function (c) {
        attempt(file + ' parse ' + c.text, function () {
            var ast = X.parse(c.text);
            var text = X.toText(ast);
            check(text === c.canonical, file + ' canonical ' + c.text, JSON.stringify(text));
            if (c.ast !== undefined) {
                check(same(c.ast, JSON.parse(JSON.stringify(ast))), file + ' ast ' + c.text, JSON.stringify(ast));
            }
            if (c.sentence !== undefined) {
                var s = X.toSentence(ast, cases.labels, cases.choices);
                check(s === c.sentence, file + ' sentence ' + c.text, JSON.stringify(s));
            }
            check(X.canonical(text) === text, file + ' canonical is stable ' + c.text);
        });
    });
    cases.errors.forEach(function (c) {
        attempt(file + ' error ' + c.text, function () {
            var err = null;
            try {
                X.parse(c.text);
            } catch (e) {
                err = e;
            }
            check(err instanceof X.ExpressionError, file + ' rejects ' + JSON.stringify(c.text));
            if (err && c.offset !== undefined) {
                check(err.offset === c.offset, file + ' error offset ' + JSON.stringify(c.text), String(err.offset));
            }
        });
    });
    cases.evaluate.forEach(function (c) {
        attempt(file + ' evaluate ' + c.text, function () {
            var ctx = c.context || cases.context;
            var got = X.evaluate(c.text, ctx);
            check(same(c.result, got), file + ' evaluate ' + c.text, JSON.stringify(got) + ' expected ' + JSON.stringify(c.result));
        });
    });
}

function runBehaviour(file) {
    var spec = json(file);
    spec.cases.forEach(function (c) {
        attempt(file + ' ' + c.name, function () {
            var model = json('models/' + c.model);
            var extra = { today: spec.today };
            if (c.scores !== undefined) {
                check(same(c.scores, M.score(model, c.answers).fields), c.name + ' scores', JSON.stringify(M.score(model, c.answers).fields));
            }
            if (c.total !== undefined) {
                check(same(c.total, M.score(model, c.answers).total), c.name + ' total');
                var o = M.outcome(model, c.answers, extra);
                check((o ? o.id : null) === c.outcome, c.name + ' outcome', o ? o.id : 'none');
            }
            (c.shown || []).forEach(function (id) {
                check(M.isShown(model, id, c.answers, extra) === true, c.name + ' shows ' + id);
            });
            (c.hidden || []).forEach(function (id) {
                check(M.isShown(model, id, c.answers, extra) === false, c.name + ' hides ' + id);
            });
            if (c.pages !== undefined) {
                Object.keys(c.pages).forEach(function (pid) {
                    check(M.isPageShown(model, pid, c.answers, extra) === c.pages[pid], c.name + ' page ' + pid);
                });
            }
            if (c.form !== undefined) {
                var r = M.evaluateForm(model, c.answers, extra);
                Object.keys(c.form).forEach(function (k) {
                    check(same(c.form[k], r[k]), c.name + ' form ' + k, JSON.stringify(r[k]));
                });
            }
        });
    });
}

var ran = 0;
manifest.suites.forEach(function (suite) {
    if (suite.kind === 'expressions') {
        runExpressions(suite.file);
        ran++;
    } else if (suite.kind === 'behaviour') {
        runBehaviour(suite.file);
        ran++;
    }
});

failures.forEach(function (f) { console.log('FAIL ' + f); });
console.log((failures.length ? 'FAILED' : 'OK') + ': ' + passed + ' checks passed, ' + failures.length + ' failed, ' + ran + ' suites (JavaScript).');
process.exit(failures.length ? 1 : 0);
