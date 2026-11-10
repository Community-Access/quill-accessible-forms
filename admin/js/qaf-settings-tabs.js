/**
 * QUILL Accessible Forms - the settings page's tabs.
 *
 * A tab list as the ARIA Authoring Practices describe it: Tab moves into
 * the list and on to the open panel; Left and Right Arrow move between tabs
 * and open them; Home and End go to the first and last. Only the open tab
 * is in the Tab order. Before this script the tab buttons did nothing, so
 * every panel but General was out of reach.
 *
 * @package QAF
 */
(function () {
    'use strict';

    var init = function () {
        var list = document.querySelector('.qaf-settings-tabs [role="tablist"]');
        if (!list) {
            return;
        }
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));

        var open = function (tab, focus) {
            tabs.forEach(function (t) {
                var selected = t === tab;
                t.setAttribute('aria-selected', selected ? 'true' : 'false');
                t.tabIndex = selected ? 0 : -1;
                t.classList.toggle('active', selected);
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) {
                    panel.hidden = !selected;
                    panel.classList.toggle('active', selected);
                }
            });
            if (focus) {
                tab.focus();
            }
        };

        tabs.forEach(function (tab, i) {
            tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
            tab.addEventListener('click', function () {
                open(tab, false);
            });
            tab.addEventListener('keydown', function (e) {
                var next = null;
                if (e.key === 'ArrowRight') {
                    next = tabs[(i + 1) % tabs.length];
                } else if (e.key === 'ArrowLeft') {
                    next = tabs[(i - 1 + tabs.length) % tabs.length];
                } else if (e.key === 'Home') {
                    next = tabs[0];
                } else if (e.key === 'End') {
                    next = tabs[tabs.length - 1];
                }
                if (next) {
                    e.preventDefault();
                    open(next, true);
                }
            });
        });

        // Open the tab named in the address (#spam), so a link can go straight there.
        var wanted = window.location.hash.replace('#', '');
        if (wanted) {
            var match = document.getElementById('qaf-tab-btn-' + wanted);
            if (match) {
                open(match, false);
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
