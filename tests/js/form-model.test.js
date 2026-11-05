#!/usr/bin/env node
/**
 * What the published form applies from its model, beyond the shared suite:
 * requirements, enabling, "set", calculations, skipping pages and smart
 * defaults (QAFFormModel.evaluateForm). `node tests/js/form-model.test.js`.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var assert = require('assert');
var M = require('../../public/js/qaf-form-model.js');
var X = require('../../public/js/qaf-expression.js');

var model = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'conformance', 'models', 'valid', 'logic-and-layout.json'), 'utf8'));
var quiz = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'conformance', 'models', 'valid', 'quiz-and-translations.json'), 'utf8'));
var extra = { today: '2026-11-04', user: { first_name: 'Jeff' }, query: {}, geo: null };
var tests = [];

function test(name, fn) {
    tests.push([name, fn]);
}

test('a charity: the fee is set, the page about family is skipped', function () {
    var r = M.evaluateForm(model, { type: 'charity', company: 'Helping Hands', contact_by: 'post', members: '2', fee: '20' }, extra);
    assert.strictEqual(r.set.fee, 10);
    assert.strictEqual(r.skipTo, 'pay');
    assert.strictEqual(r.pages.family, false);
    assert.strictEqual(r.pages.pay, true);
    assert.strictEqual(r.shown.postal, true);
    assert.strictEqual(r.shown.staff, false);
});

test('a person: no skip, the family page is shown', function () {
    var r = M.evaluateForm(model, { type: 'person', children: ['x'], contact_by: 'email' }, extra);
    assert.strictEqual(r.skipTo, null);
    assert.strictEqual(r.pages.family, true);
    assert.strictEqual(r.shown.start, true);
});

test('require and optional', function () {
    var r = M.evaluateForm(model, { contact_by: 'phone', first: 'Mary Ann' }, extra);
    assert.strictEqual(r.required.phone, true);
    assert.strictEqual(r.required.last, false);
    r = M.evaluateForm(model, { contact_by: 'email', first: 'Mary' }, extra);
    assert.strictEqual(r.required.phone, false);
    assert.strictEqual(Object.prototype.hasOwnProperty.call(r.required, 'last'), false, 'optional that does not hold changes nothing');
});

test('enable and disable', function () {
    var r = M.evaluateForm(model, { members: '0' }, extra);
    assert.strictEqual(r.disabled.total, true);
    assert.strictEqual(r.disabled.fee, true);
    r = M.evaluateForm(model, { members: '3' }, extra);
    assert.strictEqual(r.disabled.total, false);
    assert.strictEqual(r.disabled.fee, false);
});

test('calculations', function () {
    var r = M.evaluateForm(model, { members: '12', fee: '20', discount: '24' }, extra);
    assert.strictEqual(r.calculated.discount, 24);
    assert.strictEqual(r.calculated.total, 216);
    r = M.evaluateForm(model, { members: '2', fee: '20', discount: '0' }, extra);
    assert.strictEqual(r.calculated.discount, 0);
    assert.strictEqual(r.calculated.total, 40);
});

test('the outcome follows the score', function () {
    var r = M.evaluateForm(quiz, { q1: '6', q2: ['nemeth', 'ueb'], q3: 'braille', feel: 'high' }, extra);
    assert.strictEqual(r.outcome.id, 'top');
    assert.strictEqual(r.context.score, 7);
});

test('smart defaults', function () {
    var first = M.items(model).filter(function (e) { return e.item.id === 'first'; })[0].item;
    var start = M.items(model).filter(function (e) { return e.item.id === 'start'; })[0].item;
    assert.strictEqual(M.smartDefault(first, extra), 'Jeff');
    assert.strictEqual(M.smartDefault(start, extra), '2026-11-05');
    assert.strictEqual(M.smartDefault({ id: 'x' }, extra), null);
});

test('an expression the page cannot read never hides anything', function () {
    var broken = { version: 1, pages: [{ id: 'p', items: [{ id: 'a', type: 'text', when: '#a is' }] }] };
    assert.strictEqual(M.isShown(broken, 'a', {}, extra), true);
});

test('values print as PHP prints them', function () {
    assert.strictEqual(X.str(2.5), '2.5');
    assert.strictEqual(X.str(3), '3');
    assert.strictEqual(X.str(['a', 'b']), 'a, b');
    assert.strictEqual(X.str(true), 'true');
});

var failed = 0;
tests.forEach(function (t) {
    try {
        t[1]();
    } catch (e) {
        failed++;
        console.log('FAIL ' + t[0] + ': ' + e.message);
    }
});
console.log((failed ? 'FAILED' : 'OK') + ': ' + (tests.length - failed) + ' of ' + tests.length + ' form model tests passed.');
process.exit(failed ? 1 : 0);
