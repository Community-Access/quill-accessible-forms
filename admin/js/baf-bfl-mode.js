/**
 * The form builder's BFL panel: Format Code and Sync to Visual, on the form
 * model (BFL 1 reader and writer, through admin-ajax).
 *
 * Problems are listed with their line numbers; each one is a button that
 * puts the cursor on that line, so the list can be used from the keyboard.
 * Results are spoken through the panel's status region.
 *
 * @package BAF
 * @since 1.1.0
 */
(function () {
    'use strict';

    function init() {
        var editor = document.getElementById('baf-bfl-editor');
        var status = document.getElementById('baf-bfl-status');
        var errors = document.getElementById('baf-bfl-errors');
        var format = document.getElementById('baf-bfl-format');
        var sync = document.getElementById('baf-bfl-sync');
        if (!editor || !window.bafBuilder) {
            return;
        }
        var i18n = window.bafBuilder.i18n || {};

        function say(text) {
            if (status) {
                status.textContent = '';
                window.setTimeout(function () { status.textContent = text; }, 50);
            }
        }

        function goToLine(line) {
            var lines = editor.value.split('\n');
            var start = 0;
            for (var i = 0; i < line - 1 && i < lines.length; i++) {
                start += lines[i].length + 1;
            }
            var end = start + (lines[line - 1] || '').length;
            editor.focus();
            editor.setSelectionRange(start, end);
        }

        function showProblems(list, warnings) {
            if (!errors) {
                return;
            }
            errors.textContent = '';
            var all = (list || []).map(function (p) { return { p: p, kind: i18n.bflError || 'Error' }; })
                .concat((warnings || []).map(function (p) { return { p: p, kind: i18n.bflWarning || 'Warning' }; }));
            if (!all.length) {
                return;
            }
            var heading = document.createElement('h3');
            heading.textContent = i18n.bflProblems || 'Problems in the BFL';
            errors.appendChild(heading);
            var ul = document.createElement('ul');
            all.forEach(function (entry) {
                var li = document.createElement('li');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'button-link';
                button.textContent = entry.kind + ', ' + (i18n.bflLine || 'line %d').replace('%d', entry.p.line) + ': ' + entry.p.message;
                button.addEventListener('click', function () { goToLine(entry.p.line); });
                li.appendChild(button);
                ul.appendChild(li);
            });
            errors.appendChild(ul);
        }

        function post(action, extra) {
            var body = new FormData();
            body.append('action', action);
            body.append('nonce', window.bafBuilder.nonce);
            Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
            return fetch(window.bafBuilder.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); });
        }

        function failed(json) {
            var data = (json && json.data) || {};
            showProblems(data.errors, data.warnings);
            var count = (data.errors || []).length;
            say(count ? (i18n.bflHasErrors || 'The BFL has %d errors.').replace('%d', count) : (data.message || i18n.saveError || 'Something went wrong.'));
        }

        if (format) {
            format.addEventListener('click', function () {
                post('baf_format_bfl', { bfl_source: editor.value }).then(function (json) {
                    if (!json.success) {
                        failed(json);
                        return;
                    }
                    editor.value = json.data.bfl;
                    showProblems([], json.data.warnings);
                    say(i18n.bflFormatted || 'Formatted.');
                }).catch(function () { say(i18n.saveError || 'Something went wrong.'); });
            });
        }

        if (sync) {
            sync.addEventListener('click', function () {
                post('baf_save_form_model', { bfl: editor.value, form_id: String(window.bafBuilder.formId || 0) }).then(function (json) {
                    if (!json.success) {
                        failed(json);
                        return;
                    }
                    showProblems([], []);
                    say(i18n.bflSynced || 'Saved. Reloading the visual builder.');
                    var id = json.data.form_id;
                    window.setTimeout(function () {
                        var url = new URL(window.location.href);
                        url.searchParams.set('form_id', id);
                        window.location.href = url.toString();
                    }, 800);
                }).catch(function () { say(i18n.saveError || 'Something went wrong.'); });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
