var GSF = window.GSF || {};

GSF.modal = {
    open: function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    },
    close: function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.remove('open');
            document.body.style.overflow = '';
        }
    }
};

GSF.confirm = function(message, onConfirm) {
    var overlay = document.createElement('div');
    overlay.className = 'modal_overlay open';
    overlay.innerHTML =
        '<div class="modal_content" style="max-width:380px;">' +
            '<div class="modal_header">' +
                '<h2 class="modal_title">Confirm</h2>' +
                '<button type="button" class="modal_close" data-action="cancel">' +
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>' +
            '</div>' +
            '<div class="modal_body"><p style="font-size:14px;line-height:1.6;">' + message + '</p></div>' +
            '<div class="modal_footer">' +
                '<button class="btn btn_ghost" data-action="cancel">Cancel</button>' +
                '<button class="btn btn_danger" data-action="confirm">Confirm</button>' +
            '</div>' +
        '</div>';

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    overlay.addEventListener('click', function(e) {
        var action = e.target.dataset.action || e.target.closest('[data-action]')?.dataset.action;
        if (action === 'cancel' || e.target === overlay) {
            overlay.remove();
            document.body.style.overflow = '';
        } else if (action === 'confirm') {
            overlay.remove();
            document.body.style.overflow = '';
            if (onConfirm) onConfirm();
        }
    });
};

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.modal_overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    document.querySelectorAll('.file_upload').forEach(function(wrap) {
        var input = wrap.querySelector('input[type="file"]');
        var preview = wrap.querySelector('.file_upload_preview');
        var label = wrap.querySelector('.file_upload_label');

        if (input) {
            wrap.addEventListener('click', function(e) {
                if (e.target !== input) input.click();
            });

            input.addEventListener('change', function() {
                if (input.files && input.files[0]) {
                    var file = input.files[0];
                    if (label) label.textContent = file.name;
                    if (preview && file.type.startsWith('image/')) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        }
    });

    document.querySelectorAll('.font_picker .font_option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            opt.closest('.font_picker').querySelectorAll('.font_option').forEach(function(o) {
                o.classList.remove('active');
            });
            opt.classList.add('active');
        });
    });

    document.querySelectorAll('.card_style_picker .card_style_option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            opt.closest('.card_style_picker').querySelectorAll('.card_style_option').forEach(function(o) {
                o.classList.remove('active');
            });
            opt.classList.add('active');
        });
    });

    document.querySelectorAll('.color_sync_group').forEach(function(group) {
        var colorInput = group.querySelector('input[type="color"]');
        var hexInput = group.querySelector('.color_hex_input');
        if (colorInput && hexInput) {
            colorInput.addEventListener('input', function() {
                hexInput.value = colorInput.value;
            });
            hexInput.addEventListener('input', function() {
                var val = hexInput.value;
                if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                    colorInput.value = val;
                }
            });
        }
    });

    document.addEventListener('click', function(e) {
        document.querySelectorAll('.chat_dots_menu.open').forEach(function(menu) {
            if (!menu.parentElement.contains(e.target)) {
                menu.classList.remove('open');
            }
        });
    });
});
