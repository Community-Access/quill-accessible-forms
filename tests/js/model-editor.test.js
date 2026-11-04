#!/usr/bin/env node
/**
 * The logic behind the builder's model screens (admin/js/baf-model-editor-core.js):
 * the expression check and its messages, rule sentences, pointer paths as
 * words, moves, translation rows, library users and the quiz "Try it".
 * `node tests/js/model-editor.test.js`.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var assert = require('assert');
var C = require('../../admin/js/baf-model-editor-core.js');

function load(name) {
    return JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'conformance', 'models', 'valid', name), 'utf8'));
}
var logic = load('logic-and-layout.json');
var quiz = load('quiz-and-translations.json');
var tests = [];

function test(name, fn) {
    tests.push([name, fn]);
}

var model = {
    version: 1,
    title: 'Contact',
    pages: [
        { id: 'page_1', title: 'About you', items: [
            { id: 'contact_by', type: 'radio', label: 'Contact me by', choices: [{ value: 'phone', label: 'Phone' }, { value: 'email', label: 'Email' }] },
            { id: 'phone', type: 'phone', label: 'Phone', help: 'With the area code.' },
            { id: 'box', type: 'section', label: 'More', items: [{ id: 'size', type: 'select', label: 'Size', choice_library: 'sizes' }] }
        ] },
        { id: 'thanks', title: 'Thanks', items: [] }
    ],
    choice_libraries: { sizes: [{ value: 's', label: 'Small' }, { value: 'l', label: 'Large', price: 5 }] },
    rules: [{ action: 'require', targets: ['phone'], when: '#contact_by is "phone"' }]
};

test('a rule reads as its sentence', function () {
    assert.strictEqual(C.ruleSentence(model.rules[0], model), 'Require Phone when Contact me by is Phone');
    assert.strictEqual(C.ruleSentence({ action: 'optional', targets: ['phone', 'size'], when: '#contact_by is "email"' }, model), 'Make Phone and Size optional when Contact me by is Email');
    assert.strictEqual(C.ruleSentence({ action: 'skip_to', targets: ['thanks'], when: '#contact_by is "email"' }, model), 'Skip to Thanks when Contact me by is Email');
    assert.strictEqual(C.ruleSentence({ action: 'set', targets: ['phone'], when: '#contact_by is "email"', value: '"none"' }, model), 'Set Phone to none when Contact me by is Email');
});

test('a rule the screen does not know is still a sentence, and a broken condition is its text', function () {
    assert.strictEqual(C.ruleSentence({ action: 'glow', targets: ['phone'], when: '#contact_by is' }, model), 'glow Phone when #contact_by is');
});

test('the sentences of every rule in the shared model', function () {
    var s = logic.rules.map(function (r) { return C.ruleSentence(r, logic); });
    assert.strictEqual(s.length, logic.rules.length);
    s.forEach(function (x) { assert.ok(/ when /.test(x), x); });
});

test('the expression check reads a good condition back', function () {
    var r = C.checkExpression('#contact_by is "phone"', model);
    assert.strictEqual(r.ok, true);
    assert.strictEqual(r.sentence, 'Contact me by is Phone');
});

test('the expression check gives the error with its character position', function () {
    var r = C.checkExpression('#contact_by is', model);
    assert.strictEqual(r.ok, false);
    assert.strictEqual(r.message, 'The expression ends too early. At character 15.');
    assert.strictEqual(r.position, 15);
});

test('positions count characters, not UTF-8 bytes', function () {
    // "é" is two bytes: after the closing quote is byte 4, character 4.
    assert.strictEqual(C.charPosition('"é" is', 4), 4);
    assert.strictEqual(C.charPosition('"😀" x', 6), 4);
    var r = C.checkExpression('"é" @', model);
    assert.strictEqual(r.ok, false);
    assert.ok(/At character 5\.$/.test(r.message), r.message);
});

test('an empty condition, and a field that is not in the form', function () {
    assert.strictEqual(C.checkExpression('', model).ok, false);
    assert.strictEqual(C.checkExpression('  ', model, { optional: true }).ok, true);
    var r = C.checkExpression('#nope is 1', model);
    assert.strictEqual(r.ok, false);
    assert.strictEqual(r.message, 'There is no #nope in this form.');
});

test('the messages can be translated', function () {
    var t = C.translator({ exprAt: '%1$s (carácter %2$d)' });
    assert.strictEqual(C.checkExpression('#a is', model, { t: t }).message.indexOf('(carácter 6)') > 0, true);
});

test('checking a rule names the field each problem belongs to', function () {
    var p = C.checkRule({ action: 'set', targets: [], when: '#x is', value: '' }, model);
    assert.deepStrictEqual(p.map(function (e) { return e.field; }), ['targets', 'when', 'value']);
    assert.deepStrictEqual(C.checkRule(model.rules[0], model), []);
});

test('targets: items and pages, or pages only for skip to', function () {
    var all = C.targetChoices(model, 'show').map(function (c) { return c.id; });
    assert.deepStrictEqual(all, ['contact_by', 'phone', 'box', 'size', 'page_1', 'thanks']);
    assert.deepStrictEqual(C.targetChoices(model, 'skip_to').map(function (c) { return c.name; }), ['About you', 'Thanks']);
});

test('server pointers become words and places', function () {
    assert.deepStrictEqual(C.where('/rules/2/when'), { screen: 'rules', index: 2, field: 'when', text: 'Rule 3, condition' });
    assert.strictEqual(C.where('/rules/0/targets/1').text, 'Rule 1, targets');
    assert.strictEqual(C.where('/scoring/outcomes/0/when').text, 'Outcome 1, condition');
    assert.strictEqual(C.where('/choice_libraries/sizes/1/value').field, 'choice-1-value');
    assert.strictEqual(C.where('/translations/es/fields/q9').screen, 'translations');
    assert.strictEqual(C.where('/pages/0/items/1').screen, null);
    assert.strictEqual(C.where('/pages/0/items/1').text, 'Page 1, item 2');
});

test('moves and the row that takes focus after a delete', function () {
    assert.deepStrictEqual(C.move(['a', 'b', 'c'], 2, 1), ['a', 'c', 'b']);
    assert.deepStrictEqual(C.move(['a', 'b'], 0, -1), ['a', 'b']);
    assert.strictEqual(C.focusAfterDelete(1, 2), 1);
    assert.strictEqual(C.focusAfterDelete(2, 2), 1);
    assert.strictEqual(C.focusAfterDelete(0, 0), -1);
});

test('the fields that use a library', function () {
    assert.deepStrictEqual(C.libraryUsers(model, 'sizes'), ['Size']);
    assert.deepStrictEqual(C.libraryUsers(model, 'other'), []);
});

test('a library is checked: name, labels, values used twice', function () {
    assert.deepStrictEqual(C.checkLibrary('sizes', model.choice_libraries.sizes, 'sizes', model), []);
    var p = C.checkLibrary('sizes', [{ value: 'a', label: 'A' }, { value: 'a', label: '' }], null, model);
    assert.deepStrictEqual(p.map(function (e) { return e.field; }), ['name', 'choice-1-label', 'choice-1-value']);
    assert.strictEqual(C.checkLibrary('9x', [], null, model)[0].field, 'name');
});

test('translation rows: the form\'s text, what is done, and the count', function () {
    var rows = C.translationRows(quiz, 'es');
    var q1 = rows.filter(function (r) { return r.path.join('/') === 'fields/q1/label'; })[0];
    assert.strictEqual(q1.source, 'How many dots are in a braille cell?');
    assert.strictEqual(q1.value, '¿Cuántos puntos tiene una celda braille?');
    assert.strictEqual(q1.what, 'How many dots are in a braille cell?, label');
    var choice = rows.filter(function (r) { return r.path.join('/') === 'fields/q1/choices/6'; })[0];
    assert.strictEqual(choice.value, 'Seis');
    var c = C.translationCount(quiz, 'es');
    assert.ok(c.done > 0 && c.done < c.total, JSON.stringify(c));
    assert.ok(C.translationCount(quiz, 'fr').done === 2);
});

test('setting a translation keeps what it does not touch and drops what empties', function () {
    var tr = quiz.translations.fr;
    var a = C.setTranslation(tr, ['fields', 'q2', 'label'], 'Quels codes ?');
    assert.strictEqual(a.fields.q2.label, 'Quels codes ?');
    assert.strictEqual(a.fields.q1.label, tr.fields.q1.label);
    var b = C.setTranslation(a, ['fields', 'q2', 'label'], '');
    assert.deepStrictEqual(b, tr);
    var c = C.setTranslation({ fields: { q1: { label: 'x' } }, extra: 1 }, ['fields', 'q1', 'label'], '');
    assert.deepStrictEqual(c, { extra: 1 });
    assert.strictEqual(tr.fields.q2, undefined, 'the original is not changed');
});

test('a new language code is checked', function () {
    assert.strictEqual(C.checkLanguage('pt-BR', quiz).length, 0);
    assert.strictEqual(C.checkLanguage('es', quiz)[0].field, 'code');
    assert.strictEqual(C.checkLanguage('en', quiz).length, 1);
    assert.strictEqual(C.checkLanguage('Spanish', quiz).length, 1);
});

test('Try it: a score picks the first outcome that holds', function () {
    assert.strictEqual(C.tryOutcome(quiz, 7).outcome.id, 'top');
    assert.strictEqual(C.tryOutcome(quiz, 5).outcome.id, 'pass');
    assert.strictEqual(C.tryOutcome(quiz, 2).outcome.id, 'retry');
    assert.strictEqual(C.tryOutcome({ scoring: { outcomes: [{ id: 'a', when: 'score > 9', message: '' }] } }, 1), null);
});

test('an outcome is checked', function () {
    var p = C.checkOutcome({ id: 'top', when: 'score >=', message: '' }, 1, quiz);
    assert.deepStrictEqual(p.map(function (e) { return e.field; }), ['id', 'when', 'message']);
    assert.deepStrictEqual(C.checkOutcome(quiz.scoring.outcomes[0], 0, quiz), []);
});

test('slug matches BAF_Form_Model::slug', function () {
    assert.strictEqual(C.slug('Odd: one'), 'odd-one');
    assert.strictEqual(C.slug('  Extra Large!! '), 'extra-large');
});

var failed = 0;
tests.forEach(function (tc) {
    try {
        tc[1]();
        console.log('ok   ' + tc[0]);
    } catch (e) {
        failed++;
        console.log('FAIL ' + tc[0] + ': ' + e.message);
    }
});
console.log(tests.length - failed + ' of ' + tests.length + ' passed');
process.exit(failed ? 1 : 0);
