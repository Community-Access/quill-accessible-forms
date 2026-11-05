/**
 * The Scoring section of a question's settings in the Visual mode.
 *
 * A scored question's card has a Scoring button. It asks the server for
 * the section (qaf_get_field_scoring, drawn from the question in the stored
 * model), shows it in the field settings panel and moves focus to its
 * heading. Save puts the section into the model with
 * QAFModelEditorCore.applyScoring -- only this question's scoring and its
 * own choices' points -- and saves the model whole through the shared
 * persist(), which reads the stored model again first. Problems are joined
 * to their fields and listed in a summary that takes focus; the save is
 * said in the section's status region. Close or Escape hides the panel and
 * returns focus to the Scoring button.
 *
 * @package QAF
 * @since 1.1.0
 */
(function () {
    'use strict';

    var E = window.QAFModelEditor;
    var C = window.QAFModelEditorCore;
    var t = E.t;
    var $ = E.$;
    var current = null; // { button, fieldId, label }

    function panel() {
        return $('qaf-field-settings');
    }

    function close() {
        var p = panel();
        if (!current) {
            return;
        }
        p.hidden = true;
        p.classList.remove('qaf-panel-open');
        $('qaf-settings-body').textContent = '';
        current.button.setAttribute('aria-expanded', 'false');
        var back = current.button;
        current = null;
        if (document.body.contains(back)) {
            back.focus();
        }
    }

    /** What the section's controls say, as applyScoring's input. */
    function readSection(section) {
        var input = {};
        var v = function (id) { var e = $(id); return e ? e.value : undefined; };
        input.points = v('qaf-scoring-points');
        if (section.getAttribute('data-kind') === 'text') {
            input.correct = String(v('qaf-scoring-accepted') || '').split(/\r?\n/);
            input.case_sensitive = $('qaf-scoring-case-sensitive').checked;
        } else if ($('qaf-scoring-correct')) {
            input.correct = Array.prototype.filter.call(section.querySelectorAll('input[name="qaf-scoring-correct"]'), function (b) { return b.checked; }).map(function (b) { return b.value; });
        }
        if ($('qaf-scoring-partial')) {
            input.partial = $('qaf-scoring-partial').checked;
        }
        Array.prototype.forEach.call(section.querySelectorAll('textarea[data-key]'), function (ta) {
            input[ta.getAttribute('data-key')] = ta.value;
        });
        var points = section.querySelectorAll('#qaf-scoring-choice-points input[data-index]');
        if (points.length) {
            input.choicePoints = {};
            Array.prototype.forEach.call(points, function (p) { input.choicePoints[p.getAttribute('data-index')] = p.value; });
        }
        return input;
    }

    function fields(section) {
        var out = { points: { control: $('qaf-scoring-points'), error: $('qaf-scoring-points-error') } };
        if ($('qaf-scoring-accepted')) {
            out.correct = { control: $('qaf-scoring-accepted'), error: $('qaf-scoring-accepted-error') };
        } else if ($('qaf-scoring-correct')) {
            out.correct = { control: section.querySelector('input[name="qaf-scoring-correct"]') || $('qaf-scoring-points'), error: $('qaf-scoring-correct-error') };
        }
        Array.prototype.forEach.call(section.querySelectorAll('#qaf-scoring-choice-points input[data-index]'), function (p) {
            out['choice-' + p.getAttribute('data-index') + '-points'] = { control: p, error: $(p.id + '-error') };
        });
        return out;
    }

    /** The save in words: "2 points; correct answer: Six." */
    function savedWords(model, fieldId) {
        var f = C.findItem(model, fieldId) || {};
        var s = f.scoring || {};
        var parts = [s.points === undefined ? t('scoringNoPoints', 'no points') : (s.points === 1 ? t('scoringOnePoint', '1 point') : t('scoringNPoints', '%s points', String(s.points)))];
        var correct = s.correct || [];
        var labels = (C.choiceLabels(model)[fieldId]) || {};
        var names = correct.map(function (v) { return labels[v] !== undefined ? labels[v] : v; });
        if (!names.length) {
            parts.push(t('scoringNoCorrect', 'no correct answer'));
        } else if (names.length === 1) {
            parts.push(t('scoringCorrectOne', 'correct answer: %s', names[0]));
        } else {
            parts.push(t('scoringCorrectMany', 'correct answers: %s', C.listNames(names, t)));
        }
        var priced = (f.choices || []).filter(function (c) { return c.points !== undefined; });
        if (priced.length) {
            parts.push(t('scoringChoicePointsSaid', 'choice points: %s', C.listNames(priced.map(function (c) { return c.label + ' ' + c.points; }), t)));
        }
        return parts.join('; ');
    }

    function wire(section) {
        var form = $('qaf-scoring-form');
        var status = $('qaf-scoring-status');
        var summary = $('qaf-scoring-summary');
        var fieldId = section.getAttribute('data-field-id');

        section.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });
        section.querySelector('[data-qaf-scoring-close]').addEventListener('click', close);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = readSection(section);
            var check = C.applyScoring(E.state.model, fieldId, input, t);
            if (check.problems.length) {
                E.showProblems(summary, fields(section), check.problems);
                return;
            }
            E.persist(function (m) {
                var r = C.applyScoring(m, fieldId, input, t);
                if (!r.model) {
                    throw new Error(r.problems[0].message);
                }
                Object.keys(m).forEach(function (k) { delete m[k]; });
                Object.keys(r.model).forEach(function (k) { m[k] = r.model[k]; });
            }).then(function (r) {
                if (!r.ok) {
                    var problems = (r.errors || []).map(function (er) {
                        var f = C.scoringField(E.state.model, fieldId, er.path);
                        return f ? { field: f, message: er.message } : { field: null, where: C.where(er.path, t).text, message: er.message };
                    });
                    E.showProblems(summary, fields(section), problems.length ? problems : [{ field: null, message: r.message }]);
                    return;
                }
                E.showProblems(summary, fields(section), []);
                E.say(status, t('scoringSaved', 'Scoring saved: %s.', savedWords(r.model, fieldId)));
            });
        });
    }

    function open(button) {
        var fieldId = button.getAttribute('data-field-id');
        if (current && current.fieldId === fieldId) {
            var h = $('qaf-scoring-heading');
            if (h) {
                h.focus();
            }
            return;
        }
        if (current) {
            current.button.setAttribute('aria-expanded', 'false');
        }
        var live = $('qaf-live-region');
        Promise.all([
            E.load(),
            E.post('qaf_get_field_scoring', { form_id: String((window.qafModelEditor || {}).formId || 0), field_id: fieldId })
        ]).then(function (res) {
            var json = res[1];
            if (!json.success) {
                E.say(live, (json.data && json.data.message) || t('scoringLoadFailed', 'The scoring could not be opened.'));
                return;
            }
            var body = $('qaf-settings-body');
            // The panel held a question's settings, perhaps: they are closed now.
            Array.prototype.forEach.call(document.querySelectorAll('.qaf-field-edit[aria-expanded="true"]'), function (b) { b.setAttribute('aria-expanded', 'false'); });
            if (window.jQuery) {
                window.jQuery(panel()).removeData('field-id');
            }
            body.innerHTML = json.data.html;
            var p = panel();
            p.hidden = false;
            p.classList.add('qaf-panel-open');
            p.removeAttribute('aria-hidden');
            button.setAttribute('aria-expanded', 'true');
            current = { button: button, fieldId: fieldId };
            var section = body.querySelector('.qaf-scoring-section');
            wire(section);
            $('qaf-scoring-heading').focus();
        }, function (e) {
            E.say(live, (e && e.message) || t('scoringLoadFailed', 'The scoring could not be opened.'));
        });
    }

    /** The settings panel is being given to something else: forget the open section quietly. */
    window.QAFFieldScoring = {
        reset: function () {
            if (current) {
                current.button.setAttribute('aria-expanded', 'false');
                current = null;
            }
        }
    };

    function init() {
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.qaf-scoring-btn') : null;
            if (b) {
                e.preventDefault();
                open(b);
                return;
            }
            if (current && e.target.closest && e.target.closest('.qaf-close-settings')) {
                close();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
