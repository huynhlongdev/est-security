<?php

function est_path()
{
    $domain = $_SERVER['SERVER_NAME'];
    $first = explode('.', $domain)[0];
    return $first . '-start';
}

/**
 * Script 
 */
add_action('wp_enqueue_scripts', function () {
    if (is_user_logged_in()) return;
    if (get_option('est_copy_protection') == 0) return;

    $custom_js = <<<JS
document.addEventListener('DOMContentLoaded', function () {

    function isLink(el) {
        while (el && el.tagName) {
            if (el.tagName.toLowerCase() === 'a') return el;
            el = el.parentElement;
        }
        return null;
    }

    function stop(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }

    function addItem(menu, text, action) {
        const item = document.createElement('div');
        item.textContent = text;
        item.style.cssText = 'padding:8px 16px;cursor:pointer;';
        item.addEventListener('mouseenter', () => item.style.background = '#f5f5f5');
        item.addEventListener('mouseleave', () => item.style.background = '');
        item.addEventListener('click', action);
        menu.appendChild(item);
    }

    // =========================
    // Custom context menu (Ẩn Inspect)
    // =========================
    document.addEventListener('contextmenu', function (e) {
        const target = e.target;

        // Cho phép menu gốc trong form
        if (
            target.closest('input') ||
            target.closest('textarea') ||
            target.isContentEditable
        ) {
            return;
        }

        e.preventDefault();

        const oldMenu = document.getElementById('custom-context-menu');

        console.log(oldMenu);
        
        if (oldMenu) oldMenu.remove();

        const menu = document.createElement('div');
        menu.id = 'custom-context-menu';
        menu.style.cssText =
            'position:fixed;' +
            'top:' + e.clientY + 'px;' +
            'left:' + e.clientX + 'px;' +
            'background:#fff;' +
            'border:1px solid #ddd;' +
            'border-radius:6px;' +
            'box-shadow:0 10px 30px rgba(0,0,0,.15);' +
            'font-size:14px;' +
            'z-index:99999;' +
            'padding:6px 0;' +
            'min-width:180px;';

        const link = isLink(target);

        if (link) {
            addItem(menu, 'Mở liên kết trong tab mới', function () {
                window.open(link.href, '_blank');
            });

            addItem(menu, 'Sao chép liên kết', function () {
                navigator.clipboard.writeText(link.href);
            });
        }

        
        addItem(menu, 'Tải lại trang', function () {
            location.reload();
        });

        document.body.appendChild(menu);

        document.addEventListener('click', function () {
            menu.remove();
        }, { once: true });

    }, true);

    // =========================
    // Disable selection
    // =========================
    document.addEventListener('selectstart', function (e) {
        const tag = e.target.tagName?.toLowerCase();
        if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return;
        stop(e);
    }, true);

    // =========================
    // Disable drag / copy / paste
    // =========================
    ['dragstart', 'copy', 'paste'].forEach(evt => {
        document.addEventListener(evt, function (e) {
            stop(e);
        }, true);
    });

    // =========================
    // Keyboard shortcuts
    // =========================
    document.addEventListener('keydown', function (e) {
        const key = e.keyCode || e.which;
        const ctrl = e.ctrlKey || e.metaKey;

        // Allow Ctrl/Cmd + click on links
        if (ctrl && isLink(e.target)) return;

        // F12
        if (key === 123) return stop(e);

        // Ctrl+C / Ctrl+U / Ctrl+S / Ctrl+Shift+I
        if (ctrl && (
            key === 67 || // C
            key === 85 || // U
            key === 83 || // S
            (e.shiftKey && key === 73) // I
        )) {
            return stop(e);
        }
    }, true);

});
JS;

    wp_add_inline_script('jquery-core', $custom_js);

    // Optional CSS
    $style = "
    html, body {
        user-select: none !important;
    }
    input, textarea, [contenteditable] {
        user-select: text !important;
    }
    ";

    wp_add_inline_style('global-styles', $style);
});
