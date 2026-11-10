/**
 * QUILL Accessible Forms - proof of work for the spam checks.
 *
 * When a site turns ALTCHA on, each form asks the site for a challenge and
 * this script searches, in the background, for the number that makes
 * SHA-256(salt + number) equal the challenge. Nothing is shown and nothing
 * is asked of the person; the answer goes into the form's hidden qaf_pow
 * field. If the browser cannot do it (no Web Crypto, scripts off, a page
 * not served over HTTPS), the form is sent without it and the site's other
 * checks decide: a missing answer is a doubt, never a refusal.
 *
 * QAFSpam.ready(form) returns a promise the submit handler waits on, for at
 * most a few seconds. QAFSpam.refresh(form, challenge) starts again with a
 * new challenge (sent back after a wrong answer to a question).
 *
 * @package QAF
 */
(function () {
    'use strict';

    var hex = function (buffer) {
        var bytes = new Uint8Array(buffer);
        var out = '';
        for (var i = 0; i < bytes.length; i++) {
            out += ('0' + bytes[i].toString(16)).slice(-2);
        }
        return out;
    };

    var solve = function (c) {
        if (!window.crypto || !crypto.subtle || !window.TextEncoder) {
            return Promise.resolve('');
        }
        var encoder = new TextEncoder();
        var n = 0;
        var step = function () {
            var batch = [];
            var end = Math.min(c.maxnumber, n + 200);
            for (var i = n; i <= end; i++) {
                batch.push(crypto.subtle.digest('SHA-256', encoder.encode(c.salt + i)));
            }
            var start = n;
            n = end + 1;
            return Promise.all(batch).then(function (hashes) {
                for (var j = 0; j < hashes.length; j++) {
                    if (hex(hashes[j]) === c.challenge) {
                        return btoa(JSON.stringify({
                            algorithm: c.algorithm,
                            challenge: c.challenge,
                            number: start + j,
                            salt: c.salt,
                            signature: c.signature
                        }));
                    }
                }
                return n > c.maxnumber ? '' : step();
            });
        };
        return step();
    };

    var forms = new WeakMap();

    var start = function (form, challenge) {
        var input = form.querySelector('input[name="qaf_pow"]');
        if (!input) {
            return Promise.resolve();
        }
        input.value = '';
        var got = challenge
            ? Promise.resolve(challenge)
            : fetch(form.dataset.qafPow, { credentials: 'same-origin' }).then(function (r) {
                return r.ok ? r.json() : null;
            });
        var done = got.then(function (c) {
            return c && c.challenge ? solve(c) : '';
        }).then(function (payload) {
            input.value = payload || '';
        }).catch(function () {
            input.value = '';
        });
        forms.set(form, done);
        return done;
    };

    window.QAFSpam = {
        ready: function (form, waitMs) {
            var done = forms.get(form);
            if (!done) {
                return Promise.resolve();
            }
            return Promise.race([done, new Promise(function (resolve) {
                setTimeout(resolve, waitMs || 8000);
            })]);
        },
        refresh: function (form, challenge) {
            if (form.dataset.qafPow) {
                start(form, challenge || null);
            }
        }
    };

    var init = function () {
        document.querySelectorAll('form[data-qaf-pow]').forEach(function (form) {
            start(form, null);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
