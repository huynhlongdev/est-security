<?php

function est_path()
{
    $domain = $_SERVER['SERVER_NAME'];
    $first = explode('.', $domain)[0];
    return $first . '-start';
}

add_action('wp_enqueue_scripts', function () {
    if (is_user_logged_in()) return;
    if (get_option('est_copy_protection') == 0) return;
    // Thêm JS inline sau jquery
    $redirect_url = home_url('/');
    $custom_js = "
        jQuery(document).ready(function($) {
            var redirect = function() {
                window.location.href = '" . $redirect_url . "';
            };
            var stop = function(e) {
                e.preventDefault();
                e.stopPropagation();
            };

            // Disable right-click
            document.addEventListener('contextmenu', function(e) {
                stop(e);
                return false;
            }, {
                capture: true
            });

            // Disable text selection via mouse
            document.addEventListener('selectstart', function(e) {
                var tag = e.target && e.target.tagName && e.target.tagName.toLowerCase();
                if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable)
                    return; // allow form fields
                stop(e);
                return false;
            }, {
                capture: true
            });

            // Disable dragstart (images/links)
            document.addEventListener('dragstart', function(e) {
                stop(e);
                return false;
            }, {
                capture: true
            });

            // Intercept copy event — clear clipboard or replace
            document.addEventListener('copy', function(e) {
                var sel = window.getSelection ? window.getSelection().toString() : '';
                if (!sel) {
                    stop(e);
                    return false;
                }
                // Optionally replace clipboard text:
                try {
                    e.clipboardData.setData('text/plain', '');
                    e.preventDefault();
                } catch (err) {
                    stop(e);
                }
                return false;
            }, {
                capture: true
            });

            // Block common keyboard shortcuts: Ctrl/Cmd+C, Ctrl+U, F12, Ctrl+Shift+I, Ctrl+S
            document.addEventListener('keydown', function(e) {
                var key = e.key || e.keyCode;
                var ctrl = e.ctrlKey || e.metaKey;

                // F12
                if (e.keyCode === 123) {
                    stop(e);
                    return false;
                }

                // Ctrl/Cmd+U (view-source), Ctrl/Cmd+S, Ctrl+Shift+I (devtools), Ctrl/Cmd+C
                if (ctrl && (e.keyCode === 85 || e.keyCode === 83 || (e.shiftKey && e.keyCode === 73) || e
                        .keyCode === 67)) {
                    stop(e);
                    return false;
                }

                // Block Ctrl+A (select all) — optional; uncomment if want to block
                // if (ctrl && e.keyCode === 65) { stop(e); return false; }
            }, {
                capture: true
            });

            // Prevent selection via keyboard (Shift+Arrow)
            document.addEventListener('keyup', function(e) {
                // if selection exists, collapse it
                try {
                    var s = window.getSelection();
                    if (s && s.toString().length > 0) {
                        // if selection is inside input/textarea, keep it
                        var node = s.anchorNode;
                        while (node && node.nodeType !== 1) node = node.parentNode;
                        if (node && (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA' || node
                                .isContentEditable)) {
                            return;
                        }
                        s.collapseToStart();
                    }
                } catch (err) {}
            }, {
                capture: false
            });

            // Also prevent copy via context menu fallback on older browsers
            window.addEventListener('mouseup', function() {
                try {
                    var s = window.getSelection();
                    if (s && s.toString().length > 0) {
                        var node = s.anchorNode;
                        while (node && node.nodeType !== 1) node = node.parentNode;
                        if (node && (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA' || node
                                .isContentEditable)) {
                            return;
                        }
                        s.removeAllRanges();
                    }
                } catch (err) {}
            });

            // Helpful: disable drag and drop paste
            document.addEventListener('paste', function(e) {
                stop(e);
                return false;
            }, {
                capture: true
            });
        });
    ";
    wp_add_inline_script('jquery-core', $custom_js);

    $style = "
    html,
    body {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }

    input,
    textarea,
    [contenteditable] {
        -webkit-user-select: text !important;
        -moz-user-select: text !important;
        -ms-user-select: text !important;
        user-select: text !important;
    }
    ";
    wp_add_inline_style('global-styles', $style);
});
