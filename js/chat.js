var GSF = window.GSF || {};

GSF.chat = (function() {
    var groupId = null;
    var baseUrl = '';
    var lastId = 0;
    var container = null;
    var timer = null;
    var currentUserId = 0;
    var mutedUntil = 0;

    function init(gid, base, uid) {
        groupId = gid;
        baseUrl = base;
        currentUserId = uid || 0;
        container = document.getElementById('chat_messages');
        if (!container) return;

        fetch(baseUrl + '/php/chat/fetch.php?group_id=' + groupId + '&after=0')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.messages && data.messages.length) {
                    renderMessages(data.messages, true);
                    scrollBottom();
                }
            });

        timer = setInterval(poll, 3000);

        var form = document.getElementById('chat_form');
        if (form) {
            var fileInput = document.getElementById('chat_file_input');
            var attachBtn = document.getElementById('chat_attach_btn');
            var previewArea = document.getElementById('chat_file_preview');

            if (attachBtn && fileInput) {
                attachBtn.addEventListener('click', function() {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function() {
                    if (fileInput.files && fileInput.files[0]) {
                        showFilePreview(fileInput.files[0]);
                    }
                });
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (mutedUntil && Date.now() < mutedUntil) {
                    var secs = Math.ceil((mutedUntil - Date.now()) / 1000);
                    showMuteNotice('You are muted for ' + secs + ' more seconds.');
                    return;
                }

                var input = document.getElementById('chat_input');
                var content = input.value.trim();

                if (fileInput && fileInput.files && fileInput.files[0]) {
                    var formData = new FormData();
                    formData.append('group_id', groupId);
                    formData.append('content', content);
                    formData.append('attachment', fileInput.files[0]);

                    input.value = '';
                    fileInput.value = '';
                    clearFilePreview();

                    fetch(baseUrl + '/php/chat/send.php', {
                        method: 'POST',
                        body: formData
                    }).then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.muted) {
                            mutedUntil = Date.now() + (data.muted_seconds * 1000);
                            showMuteNotice(data.error);
                        } else {
                            poll();
                        }
                    });
                } else if (content) {
                    input.value = '';

                    fetch(baseUrl + '/php/chat/send.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ group_id: groupId, content: content })
                    }).then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.muted) {
                            mutedUntil = Date.now() + (data.muted_seconds * 1000);
                            showMuteNotice(data.error);
                        } else {
                            poll();
                        }
                    });
                }
            });
        }
    }

    function showMuteNotice(msg) {
        var existing = document.querySelector('.chat_mute_notice');
        if (existing) existing.remove();
        var notice = document.createElement('div');
        notice.className = 'chat_mute_notice';
        notice.textContent = msg;
        var inputArea = document.querySelector('.chat_input_area');
        if (inputArea) {
            inputArea.parentNode.insertBefore(notice, inputArea);
        }
        setTimeout(function() { notice.remove(); }, 5000);
    }

    function showFilePreview(file) {
        var area = document.getElementById('chat_file_preview');
        if (!area) return;
        area.innerHTML =
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(file.name) + '</span>' +
            '<button type="button" class="chat_file_preview_remove" onclick="GSF.chat.clearFile()">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>';
        area.style.display = 'flex';
    }

    function clearFilePreview() {
        var area = document.getElementById('chat_file_preview');
        if (area) {
            area.innerHTML = '';
            area.style.display = 'none';
        }
        var fileInput = document.getElementById('chat_file_input');
        if (fileInput) fileInput.value = '';
    }

    function poll() {
        fetch(baseUrl + '/php/chat/fetch.php?group_id=' + groupId + '&after=' + lastId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.messages && data.messages.length) {
                    var atBottom = isAtBottom();
                    renderMessages(data.messages, false);
                    if (atBottom) scrollBottom();
                }
            });
    }

    function renderMessages(msgs, initial) {
        var welcome = container.querySelector('.chat_welcome');
        if (welcome && msgs.length) welcome.remove();

        var existing = container.querySelectorAll('.chat_msg');
        var lastMsg = existing.length ? existing[existing.length - 1] : null;
        var lastUserId = lastMsg ? lastMsg.dataset.userId : null;
        var lastTime = lastMsg ? lastMsg.dataset.time : null;

        for (var i = 0; i < msgs.length; i++) {
            var m = msgs[i];
            if (m.id > lastId) lastId = m.id;

            var sameUser = lastUserId === String(m.user_id);
            var timeDiff = lastTime ? (new Date(m.created_at) - new Date(lastTime)) / 1000 : 999;
            var grouped = sameUser && timeDiff < 300;
            var isMe = m.is_me || (currentUserId && parseInt(m.user_id) === currentUserId);

            var div = document.createElement('div');
            div.className = 'chat_msg' + (grouped ? ' chat_msg_grouped' : '') + (isMe ? ' is_me' : '');
            div.dataset.userId = m.user_id;
            div.dataset.time = m.created_at;

            var contentHtml = '';
            if (m.content) {
                contentHtml += '<div class="chat_msg_bubble">' + escapeHtml(m.content) + '</div>';
            }
            if (m.attachment) {
                var ext = (m.attachment_name || m.attachment).split('.').pop().toLowerCase();
                var isImage = ['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1;
                if (isImage) {
                    contentHtml += '<img src="' + escapeHtml(m.attachment_url || (baseUrl + '/uploads/chat_files/' + m.attachment)) + '" alt="" class="chat_attachment_img">';
                } else {
                    contentHtml += '<a href="' + escapeHtml(m.attachment_url || (baseUrl + '/uploads/chat_files/' + m.attachment)) + '" class="chat_attachment" download="' + escapeHtml(m.attachment_name || m.attachment) + '">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                        escapeHtml(m.attachment_name || m.attachment) +
                    '</a>';
                }
            }

            if (grouped) {
                div.innerHTML =
                    '<div class="chat_msg_body">' +
                        contentHtml +
                    '</div>';
            } else {
                div.innerHTML =
                    '<img src="' + escapeHtml(m.avatar_url) + '" alt="" class="chat_msg_avatar">' +
                    '<div class="chat_msg_body">' +
                        '<div class="chat_msg_header">' +
                            '<span class="chat_msg_name">' + escapeHtml(m.full_name) + '</span>' +
                        '</div>' +
                        contentHtml +
                    '</div>';
            }

            container.appendChild(div);

            lastUserId = String(m.user_id);
            lastTime = m.created_at;
        }
    }

    function formatTime(dt) {
        var d = new Date(dt);
        var now = new Date();
        var h = d.getHours();
        var min = String(d.getMinutes()).padStart(2, '0');
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;

        if (d.toDateString() === now.toDateString()) {
            return h + ':' + min + ' ' + ampm;
        }
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + h + ':' + min + ' ' + ampm;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function isAtBottom() {
        return container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    }

    function scrollBottom() {
        container.scrollTop = container.scrollHeight;
    }

    return { init: init, clearFile: clearFilePreview };
})();