/**
 * Import and Export Forms screen.
 *
 * Import: choose the builder, paste the export (or choose its file), Import.
 * Export: choose a form and a builder, Export, then download or copy it.
 * The outcome is said in each section's status region; the report follows
 * as a heading and lists, grouped by what happened, so it can be read with
 * heading and list navigation.
 *
 * @package BAF
 * @since 1.1.0
 */
(function () {
    'use strict';

    var data = window.bafImportExport || {};
    var i18n = data.i18n || {};

    function t(key, fallback) {
        return i18n[key] || fallback;
    }

    function say(region, text) {
        if (!region) {
            return;
        }
        region.textContent = '';
        window.setTimeout(function () { region.textContent = text; }, 50);
    }

    function post(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', data.nonce);
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
        return fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    var LEVELS = [
        ['dropped', 'notCarried', 'Not carried over'],
        ['changed', 'changed', 'Changed'],
        ['error', 'problems', 'Problems'],
        ['note', 'toCheck', 'To check']
    ];

    /** Counts by level, as a sentence: "2 changed, 1 not carried over." */
    function counts(report) {
        var parts = [];
        LEVELS.forEach(function (l) {
            var n = (report.entries || []).filter(function (e) { return e.level === l[0]; }).length;
            if (n) {
                parts.push(n + ' ' + t(l[1] + 'Count', l[2].toLowerCase()));
            }
        });
        return parts.length ? parts.join(', ') + '.' : t('everything', 'Everything came across.');
    }

    /** The report as a heading, a summary and a list per level. */
    function renderReport(container, heading, summary, report) {
        container.textContent = '';
        var h = document.createElement('h3');
        h.textContent = heading;
        container.appendChild(h);
        var p = document.createElement('p');
        p.textContent = summary + ' ' + counts(report);
        container.appendChild(p);
        LEVELS.forEach(function (l) {
            var entries = (report.entries || []).filter(function (e) { return e.level === l[0]; });
            if (!entries.length) {
                return;
            }
            var h4 = document.createElement('h4');
            h4.textContent = t(l[1], l[2]) + ' (' + entries.length + ')';
            container.appendChild(h4);
            var ul = document.createElement('ul');
            entries.forEach(function (e) {
                var li = document.createElement('li');
                li.textContent = e.where + ': ' + e.message;
                ul.appendChild(li);
            });
            container.appendChild(ul);
        });
    }

    function initImport() {
        var form = document.getElementById('baf-import-form');
        if (!form) {
            return;
        }
        var text = document.getElementById('baf-import-text');
        var file = document.getElementById('baf-import-file');
        var google = document.getElementById('baf-import-google');
        var googleId = document.getElementById('baf-import-google-id');
        var token = document.getElementById('baf-import-google-token');
        var error = document.getElementById('baf-import-error');
        var status = document.getElementById('baf-import-status');
        var result = document.getElementById('baf-import-result');
        var button = document.getElementById('baf-import-button');

        function source() {
            var checked = form.querySelector('input[name="source"]:checked');
            return checked ? checked.value : '';
        }

        function showError(message) {
            error.textContent = message;
            error.hidden = !message;
            text.setAttribute('aria-invalid', message ? 'true' : 'false');
        }

        form.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'source') {
                google.hidden = source() !== 'google-forms';
            }
        });
        google.hidden = source() !== 'google-forms';

        file.addEventListener('change', function () {
            var f = file.files && file.files[0];
            if (!f) {
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                text.value = String(reader.result || '');
                say(status, t('fileRead', 'The file is in the text box. Check it, then choose Import.'));
            };
            reader.onerror = function () {
                say(status, t('fileUnreadable', 'That file could not be read.'));
            };
            reader.readAsText(f);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fields = { source: source() };
            if (fields.source === 'google-forms' && googleId.value.trim() !== '') {
                fields.google_form_id = googleId.value.trim();
                fields.access_token = token.value;
            } else if (text.value.trim() === '') {
                showError(t('needExport', 'Paste the exported form, or choose its file.'));
                text.focus();
                return;
            } else {
                fields.export = text.value;
            }
            showError('');
            button.disabled = true;
            result.hidden = true;
            say(status, t('importing', 'Importing...'));
            post('baf_import_form', fields).then(function (json) {
                button.disabled = false;
                token.value = '';
                var d = json.data || {};
                if (!json.success) {
                    showError(d.message || t('failed', 'The import did not work.'));
                    say(status, d.message || t('failed', 'The import did not work.'));
                    if (d.report) {
                        renderReport(result, t('importReport', 'Import report'), '', d.report);
                        result.hidden = false;
                    }
                    text.focus();
                    return;
                }
                var summary = t('imported', 'Imported %1$d items from %2$s as a draft form.').replace('%1$d', d.report.carried).replace('%2$s', d.report.source);
                renderReport(result, t('importReport', 'Import report'), summary, d.report);
                if (d.edit_url) {
                    var link = document.createElement('a');
                    link.href = d.edit_url;
                    link.textContent = t('openForm', 'Open the new form in the builder');
                    var p = document.createElement('p');
                    p.appendChild(link);
                    result.appendChild(p);
                }
                result.hidden = false;
                say(status, summary + ' ' + counts(d.report) + ' ' + t('reportFollows', 'The report follows the Import button.'));
            }).catch(function () {
                button.disabled = false;
                say(status, t('failed', 'The import did not work.'));
            });
        });
    }

    function initExport() {
        var form = document.getElementById('baf-export-form');
        if (!form) {
            return;
        }
        var status = document.getElementById('baf-export-status');
        var result = document.getElementById('baf-export-result');
        var report = document.getElementById('baf-export-report');
        var text = document.getElementById('baf-export-text');
        var button = document.getElementById('baf-export-button');
        var download = document.getElementById('baf-export-download');
        var copy = document.getElementById('baf-export-copy');
        var filename = 'form.json';

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            button.disabled = true;
            result.hidden = true;
            say(status, t('exporting', 'Exporting...'));
            post('baf_export_form', {
                form_id: document.getElementById('baf-export-form-id').value,
                target: document.getElementById('baf-export-target').value
            }).then(function (json) {
                button.disabled = false;
                var d = json.data || {};
                if (!json.success) {
                    say(status, d.message || t('exportFailed', 'The export did not work.'));
                    return;
                }
                filename = d.filename;
                text.value = d.text;
                var summary = t('exported', 'Exported %1$d items to %2$s.').replace('%1$d', d.report.carried).replace('%2$s', d.report.source);
                renderReport(report, t('exportReport', 'Export report'), summary, d.report);
                result.hidden = false;
                download.textContent = t('download', 'Download %s').replace('%s', filename);
                say(status, summary + ' ' + counts(d.report) + ' ' + t('exportFollows', 'The report and the export follow the Export button.'));
            }).catch(function () {
                button.disabled = false;
                say(status, t('exportFailed', 'The export did not work.'));
            });
        });

        download.addEventListener('click', function () {
            var type = /\.json$/.test(filename) ? 'application/json' : 'text/plain';
            var url = URL.createObjectURL(new Blob([text.value], { type: type + ';charset=utf-8' }));
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            say(status, t('downloaded', 'Downloading %s.').replace('%s', filename));
        });

        copy.addEventListener('click', function () {
            var done = function () { say(status, t('copied', 'The export is copied.')); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text.value).then(done, function () {
                    text.select();
                    say(status, t('copyFailed', 'Copying was refused; the export is selected for you to copy.'));
                });
            } else {
                text.select();
                say(status, t('copyFailed', 'Copying was refused; the export is selected for you to copy.'));
            }
        });
    }

    function init() {
        initImport();
        initExport();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
