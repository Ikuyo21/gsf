document.addEventListener('DOMContentLoaded', function() {
    var flash = document.getElementById('flash_msg');
    if (flash) {
        setTimeout(function() {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(function() { flash.remove(); }, 300);
        }, 4000);
    }

    document.querySelectorAll('form').forEach(function(form) {
        if (form.id === 'chat_form') return;
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                setTimeout(function() { btn.disabled = false; }, 3000);
            }
        });
    });

    var sidebar = document.getElementById('main_sidebar');
    var collapseBtn = document.getElementById('sidebar_collapse_btn');
    if (sidebar && collapseBtn) {
        var saved = localStorage.getItem('gsf_sidebar_collapsed');
        if (saved === '1') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar_is_collapsed');
        }
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar_is_collapsed');
            localStorage.setItem('gsf_sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }

    document.querySelectorAll('.sidebar_link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var rect = link.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            link.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 600);
        });
    });

    if (window.BASE_URL) {
        setInterval(function() {
            fetch(window.BASE_URL + '/php/api/heartbeat.php', { method: 'POST' }).catch(function() {});
        }, 60000);
        fetch(window.BASE_URL + '/php/api/heartbeat.php', { method: 'POST' }).catch(function() {});
    }

    document.querySelectorAll('.btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var rect = btn.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.cssText = 'position:absolute;border-radius:50%;background:rgba(255,255,255,.3);transform:scale(0);animation:ripple_anim 400ms ease-out forwards;pointer-events:none;';
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 500);
        });
    });
});
