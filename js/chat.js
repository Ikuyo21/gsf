var GSF = window.GSF || {};

GSF.chat = (function () {
    var groupId        = null;
    var baseUrl        = '';
    var lastId         = 0;
    var container      = null;
    var timer          = null;
    var currentUserId  = 0;
    var mutedUntil     = 0;
    var isLeader       = false;
    var activeUploadXHR = null;
    var scrollBtn      = null;
    var scrollThreshold = 120;
    var lastKnownHeight = 0;

    function init(gid, base, uid, leader) {
        groupId       = gid;
        baseUrl       = base;
        currentUserId = uid  || 0;
        isLeader      = !!leader;
        container     = document.getElementById('chat_messages');
        scrollBtn     = document.getElementById('chat_scroll_bottom');
        if (!container) return;

        fetch(baseUrl + '/php/chat/fetch.php?group_id=' + groupId + '&after=0')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.messages && data.messages.length) {
                    renderMessages(data.messages, true);
                    scrollBottom();
                }
                initAllAudioPlayers();
            });

        timer = setInterval(poll, 3000);

        var form       = document.getElementById('chat_form');
        var fileInput  = document.getElementById('chat_file_input');
        var attachBtn  = document.getElementById('chat_attach_btn');

        if (attachBtn && fileInput) {
            attachBtn.addEventListener('click', function () { fileInput.click(); });
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files[0]) {
                    var err = validateFile(fileInput.files[0]);
                    if (err) {
                        showFormatError(err);
                        fileInput.value = '';
                        clearFilePreview();
                    } else {
                        showFilePreview(fileInput.files[0]);
                    }
                }
            });
        }

        var sessionBtn = document.getElementById('chat_session_btn');
        if (sessionBtn) {
            sessionBtn.addEventListener('click', openSessionModal);
        }

        var sessionForm = document.getElementById('study_session_form');
        if (sessionForm) {
            sessionForm.addEventListener('submit', function (e) {
                e.preventDefault();
                sendStudySession();
            });
        }

        var sessionModal = document.getElementById('study_session_modal');
        if (sessionModal) {
            sessionModal.addEventListener('click', function (e) {
                if (e.target === sessionModal) closeSessionModal();
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                if (mutedUntil && Date.now() < mutedUntil) {
                    var secs = Math.ceil((mutedUntil - Date.now()) / 1000);
                    showMuteNotice('You are muted for ' + secs + ' more seconds.');
                    return;
                }

                var input   = document.getElementById('chat_input');
                var content = input.value.trim();

                if (fileInput && fileInput.files && fileInput.files[0]) {
                    var file = fileInput.files[0];
                    var ext  = file.name.split('.').pop().toLowerCase();
                    var needsProgress = (ext === 'mp4' || ext === 'mp3');

                    var formData = new FormData();
                    formData.append('group_id',  groupId);
                    formData.append('content',   content);
                    formData.append('attachment', file);

                    input.value = '';
                    fileInput.value = '';
                    clearFilePreview();

                    if (needsProgress) {
                        showUploadProgress(file.name);
                        var xhr = new XMLHttpRequest();
                        activeUploadXHR = xhr;

                        xhr.upload.addEventListener('progress', function (ev) {
                            if (ev.lengthComputable) {
                                var pct = Math.round((ev.loaded / ev.total) * 100);
                                updateUploadProgress(pct);
                            }
                        });

                        xhr.addEventListener('load', function () {
                            activeUploadXHR = null;
                            hideUploadProgress();
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data.format_error) {
                                    showFormatError(data.error);
                                } else if (data.muted) {
                                    mutedUntil = Date.now() + (data.muted_seconds * 1000);
                                    showMuteNotice(data.error);
                                } else {
                                    poll();
                                }
                            } catch (_) { poll(); }
                        });

                        xhr.addEventListener('error', function () {
                            activeUploadXHR = null;
                            hideUploadProgress();
                            showFormatError('Upload failed. Please try again.');
                        });

                        xhr.addEventListener('abort', function () {
                            activeUploadXHR = null;
                            hideUploadProgress();
                        });

                        xhr.open('POST', baseUrl + '/php/chat/send.php');
                        xhr.send(formData);
                    } else {
                        fetch(baseUrl + '/php/chat/send.php', { method: 'POST', body: formData })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data.format_error) {
                                    showFormatError(data.error);
                                } else if (data.muted) {
                                    mutedUntil = Date.now() + (data.muted_seconds * 1000);
                                    showMuteNotice(data.error);
                                } else {
                                    poll();
                                }
                            });
                    }

                } else if (content) {
                    input.value = '';
                    fetch(baseUrl + '/php/chat/send.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ group_id: groupId, content: content })
                    }).then(function (r) { return r.json(); })
                      .then(function (data) {
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

        initScrollTracking();
    }

    var MAX_UPLOAD_SIZE = 20 * 1024 * 1024;

    function validateFile(file) {
        var ext  = file.name.split('.').pop().toLowerCase();
        var mime = file.type || '';
        var isVideo = mime.startsWith('video/') || ext === 'mp4';
        var isAudio = mime.startsWith('audio/') || ext === 'mp3';

        if (isVideo && ext !== 'mp4') return 'Only .mp4 video files are allowed.';
        if (isAudio && ext !== 'mp3') return 'Only .mp3 audio files are allowed.';

        var allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt','zip','rar','pptx','xlsx','csv','jfif','mp4','mp3'];
        if (!allowed.includes(ext)) return 'File type not supported.';

        if (file.size > MAX_UPLOAD_SIZE) {
            if (isVideo) return 'Video file exceeds the 20 MB limit.';
            if (isAudio) return 'Audio file exceeds the 20 MB limit.';
            return 'File exceeds the 20 MB limit.';
        }

        return null;
    }

    function showUploadProgress(fileName) {
        hideUploadProgress();
        var overlay = document.createElement('div');
        overlay.className = 'upload_progress_overlay';
        overlay.id = 'upload_progress_overlay';
        overlay.innerHTML =
            '<div class="upload_progress_popup">' +
                '<div class="upload_progress_header">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>' +
                    '<span>Uploading file</span>' +
                '</div>' +
                '<div class="upload_progress_filename">' + escapeHtml(fileName) + '</div>' +
                '<div class="upload_progress_track"><div class="upload_progress_fill" id="upload_progress_fill"></div></div>' +
                '<div class="upload_progress_pct" id="upload_progress_pct">0%</div>' +
                '<button type="button" class="upload_progress_cancel" id="upload_cancel_btn">Cancel</button>' +
            '</div>';
        document.body.appendChild(overlay);

        var cancelBtn = document.getElementById('upload_cancel_btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (activeUploadXHR) {
                    activeUploadXHR.abort();
                    activeUploadXHR = null;
                }
                hideUploadProgress();
            });
        }
    }

    function updateUploadProgress(pct) {
        var fill = document.getElementById('upload_progress_fill');
        var text = document.getElementById('upload_progress_pct');
        if (fill) fill.style.width = pct + '%';
        if (text) text.textContent = pct + '%';
    }

    function hideUploadProgress() {
        var overlay = document.getElementById('upload_progress_overlay');
        if (overlay) overlay.remove();
    }

    function showFormatError(msg) {
        var existing = document.querySelector('.chat_format_error');
        if (existing) existing.remove();
        var div = document.createElement('div');
        div.className = 'chat_format_error';
        div.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ' + escapeHtml(msg);
        var area = document.querySelector('.chat_input_area');
        if (area) area.parentNode.insertBefore(div, area);
        setTimeout(function () { div.remove(); }, 5000);
    }

    function showFilePreview(file) {
        var area = document.getElementById('chat_file_preview');
        if (!area) return;

        var ext      = file.name.split('.').pop().toLowerCase();
        var isVideo  = (ext === 'mp4');
        var isAudio  = (ext === 'mp3');
        var iconHTML = '';

        if (isVideo) {
            iconHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
        } else if (isAudio) {
            iconHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
        } else {
            iconHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        }

        area.innerHTML =
            iconHTML +
            '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(file.name) + '</span>' +
            '<button type="button" class="chat_file_preview_remove" onclick="GSF.chat.clearFile()">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>';
        area.style.display = 'flex';
    }

    function clearFilePreview() {
        var area = document.getElementById('chat_file_preview');
        if (area) { area.innerHTML = ''; area.style.display = 'none'; }
        var fi = document.getElementById('chat_file_input');
        if (fi) fi.value = '';
    }

    function poll() {
        fetch(baseUrl + '/php/chat/fetch.php?group_id=' + groupId + '&after=' + lastId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.messages && data.messages.length) {
                    var atBottom = isNearBottom();
                    renderMessages(data.messages, false);
                    initAllAudioPlayers();
                    if (atBottom) {
                        scrollBottom();
                    } else {
                        showScrollBtn();
                    }
                }
            });
    }

    function renderMessages(msgs, initial) {
        var welcome = container.querySelector('.chat_welcome');
        if (welcome && msgs.length) welcome.remove();

        var existing   = container.querySelectorAll('.chat_msg');
        var lastMsg    = existing.length ? existing[existing.length - 1] : null;
        var lastUserId = lastMsg ? lastMsg.dataset.userId : null;
        var lastTime   = lastMsg ? lastMsg.dataset.time   : null;

        for (var i = 0; i < msgs.length; i++) {
            var m = msgs[i];
            if (m.id > lastId) lastId = m.id;

            var sameUser = lastUserId === String(m.user_id);
            var timeDiff = lastTime ? (new Date(m.created_at) - new Date(lastTime)) / 1000 : 999;
            var grouped  = sameUser && timeDiff < 300;
            var isMe     = m.is_me || (currentUserId && parseInt(m.user_id) === currentUserId);

            if (m.message_type === 'study_session') grouped = false;

            var div = document.createElement('div');
            div.className = 'chat_msg' +
                (grouped ? ' chat_msg_grouped' : '') +
                (isMe    ? ' is_me'            : '') +
                (m.message_type === 'study_session' ? ' chat_msg_session' : '');
            div.dataset.userId = m.user_id;
            div.dataset.time   = m.created_at;

            var contentHtml = buildMessageContent(m);

            if (m.message_type === 'study_session') {
                div.innerHTML = '<div class="chat_msg_body">' + contentHtml + '</div>';
            } else if (grouped) {
                div.innerHTML = '<div class="chat_msg_body">' + contentHtml + '</div>';
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
            lastTime   = m.created_at;
        }
    }

    function buildMessageContent(m) {
        if (m.message_type === 'study_session') {
            return m.session ? buildStudySessionCard(m.session) : '<div class="chat_msg_bubble"><em>Study session</em></div>';
        }

        var html = '';

        if (m.content) {
            html += '<div class="chat_msg_bubble">' + escapeHtml(m.content) + '</div>';
        }

        if (m.attachment) {
            var ext = (m.attachment_name || m.attachment).split('.').pop().toLowerCase();
            var url = m.attachment_url || (baseUrl + '/uploads/chat_files/' + m.attachment);

            if (ext === 'mp4') {
                html += buildVideoPlayer(url);
            } else if (ext === 'mp3') {
                html += buildAudioPlayer(url, m.attachment_name || '');
            } else {
                var isImage = ['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1;
                if (isImage) {
                    html += '<img src="' + escapeHtml(url) + '" alt="" class="chat_attachment_img">';
                } else {
                    html += '<a href="' + escapeHtml(url) + '" class="chat_attachment" download="' + escapeHtml(m.attachment_name || m.attachment) + '">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                        escapeHtml(m.attachment_name || m.attachment) +
                    '</a>';
                }
            }
        }

        return html;
    }

    function buildVideoPlayer(url) {
        return '<div class="chat_video_wrapper">' +
            '<video class="chat_video" src="' + escapeHtml(url) + '" preload="metadata" playsinline></video>' +
            '<div class="chat_video_overlay">' +
                '<button class="chat_video_play_btn" type="button">' +
                    '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                '</button>' +
            '</div>' +
            '<div class="chat_video_controls">' +
                '<div class="chat_video_progress"><div class="chat_video_progress_fill"></div></div>' +
                '<span class="chat_video_time">0:00</span>' +
            '</div>' +
        '</div>';
    }

    function buildAudioPlayer(url, name) {
        return '<div class="chat_audio_player" data-src="' + escapeHtml(url) + '">' +
            '<button class="cap_play_btn" type="button" aria-label="Play">' +
                '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
            '</button>' +
            '<div class="cap_waveform_bar"><div class="cap_waveform_fill"></div></div>' +
            '<span class="cap_timer">0:00</span>' +
        '</div>';
    }

    function initAllAudioPlayers() {
        var players = document.querySelectorAll('.chat_audio_player:not([data-cap-ready])');
        players.forEach(function (el) {
            el.setAttribute('data-cap-ready', '1');
            initOneAudioPlayer(el);
        });
        var videos = document.querySelectorAll('.chat_video_wrapper:not([data-vid-ready])');
        videos.forEach(function (el) {
            el.setAttribute('data-vid-ready', '1');
            initOneVideoPlayer(el);
        });
    }

    function initOneAudioPlayer(el) {
        var src      = el.dataset.src;
        var audio    = new Audio(src);
        var playBtn  = el.querySelector('.cap_play_btn');
        var timerEl  = el.querySelector('.cap_timer');
        var barFill  = el.querySelector('.cap_waveform_fill');
        var bar      = el.querySelector('.cap_waveform_bar');
        var playing  = false;

        function fmtTime(s) {
            if (isNaN(s) || s === Infinity) return '0:00';
            s = Math.floor(s);
            return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        }

        audio.addEventListener('loadedmetadata', function () {
            timerEl.textContent = fmtTime(audio.duration);
        });

        audio.addEventListener('timeupdate', function () {
            timerEl.textContent = fmtTime(audio.currentTime);
            if (barFill && audio.duration) {
                barFill.style.width = (audio.currentTime / audio.duration * 100) + '%';
            }
        });

        audio.addEventListener('ended', function () {
            playing = false;
            el.classList.remove('cap_playing');
            playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
            timerEl.textContent = fmtTime(audio.duration);
            audio.currentTime = 0;
            if (barFill) barFill.style.width = '0%';
        });

        playBtn.addEventListener('click', function () {
            if (playing) {
                audio.pause();
                playing = false;
                el.classList.remove('cap_playing');
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
            } else {
                document.querySelectorAll('.chat_audio_player.cap_playing').forEach(function (other) {
                    if (other !== el) {
                        var otherAudio = other._audio;
                        if (otherAudio) otherAudio.pause();
                        other.classList.remove('cap_playing');
                        var ob = other.querySelector('.cap_play_btn');
                        if (ob) ob.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
                        other._playing = false;
                    }
                });
                audio.play().catch(function () {});
                playing = true;
                el.classList.add('cap_playing');
                playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="6" y1="4" x2="6" y2="20"/><line x1="18" y1="4" x2="18" y2="20"/></svg>';
            }
        });

        if (bar) {
            bar.addEventListener('click', function (e) {
                var rect = bar.getBoundingClientRect();
                var pct  = (e.clientX - rect.left) / rect.width;
                if (audio.duration) audio.currentTime = pct * audio.duration;
            });
        }

        el._audio   = audio;
        el._playing = false;
    }

    function initOneVideoPlayer(wrapper) {
        var video    = wrapper.querySelector('.chat_video');
        var overlay  = wrapper.querySelector('.chat_video_overlay');
        var playBtn  = wrapper.querySelector('.chat_video_play_btn');
        var progress = wrapper.querySelector('.chat_video_progress');
        var fill     = wrapper.querySelector('.chat_video_progress_fill');
        var timeEl   = wrapper.querySelector('.chat_video_time');

        function fmtTime(s) {
            if (isNaN(s) || s === Infinity) return '0:00';
            s = Math.floor(s);
            return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        }

        function showVideoError(msg) {
            var existing = wrapper.querySelector('.chat_video_error');
            if (existing) return;
            var err = document.createElement('div');
            err.className = 'chat_video_error';
            err.textContent = msg;
            wrapper.appendChild(err);
        }

        video.addEventListener('error', function () {
            var code = video.error ? video.error.code : 0;
            var msg  = 'Video failed to load.';
            if (code === 4) msg = 'Video format not supported or file unreachable.';
            else if (code === 2) msg = 'Network error loading video.';
            showVideoError(msg);
        });

        playBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (video.paused) {
                video.play().catch(function (err) { showVideoError('Could not play video: ' + err.message); });
            } else {
                video.pause();
            }
        });

        video.addEventListener('click', function () {
            if (video.paused) {
                video.play().catch(function (err) { showVideoError('Could not play video: ' + err.message); });
            } else {
                video.pause();
            }
        });

        video.addEventListener('play', function () {
            overlay.classList.add('cap_hidden');
            wrapper.classList.add('expanded');
            playBtn.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="6" y1="4" x2="6" y2="20"/><line x1="18" y1="4" x2="18" y2="20"/></svg>';
            recalcScrollPosition();
        });

        video.addEventListener('pause', function () {
            overlay.classList.remove('cap_hidden');
            wrapper.classList.remove('expanded');
            playBtn.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
            recalcScrollPosition();
        });

        video.addEventListener('ended', function () {
            overlay.classList.remove('cap_hidden');
            wrapper.classList.remove('expanded');
            video.currentTime = 0;
            playBtn.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
        });

        video.addEventListener('timeupdate', function () {
            if (timeEl) timeEl.textContent = fmtTime(video.currentTime);
            if (fill && video.duration) {
                fill.style.width = (video.currentTime / video.duration * 100) + '%';
            }
        });

        if (progress) {
            progress.addEventListener('click', function (e) {
                e.stopPropagation();
                var rect = progress.getBoundingClientRect();
                var pct  = (e.clientX - rect.left) / rect.width;
                if (video.duration) video.currentTime = pct * video.duration;
            });
        }
    }

    function isSessionClosed(session) {
        if (!session || !session.session_date || !session.session_time) return false;
        var dt = new Date(session.session_date + 'T' + session.session_time);
        return Date.now() >= dt.getTime();
    }

    function buildStudySessionCard(session) {
        var dt      = new Date(session.session_date + 'T' + session.session_time);
        var dateStr = dt.toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });
        var timeStr = dt.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
        var joined  = !!session.is_joined;
        var count   = parseInt(session.attendee_count) || 0;
        var creatorName = session.creator_name || '';
        var closed  = isSessionClosed(session);

        var joinBtnHtml = '';
        if (closed) {
            joinBtnHtml = '<button type="button" class="ssc_join_btn" disabled>Session Closed</button>';
        } else if (joined) {
            joinBtnHtml = '<button type="button" class="ssc_join_btn joined" ' +
                'onclick="GSF.chat.toggleSession(' + session.id + ', this)">' +
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Joined</button>';
        } else {
            joinBtnHtml = '<button type="button" class="ssc_join_btn" ' +
                'onclick="GSF.chat.toggleSession(' + session.id + ', this)">Join Session</button>';
        }

        return '<div class="ssc' + (closed ? ' ssc_closed' : '') + '" data-session-id="' + session.id + '" data-session-ts="' + dt.getTime() + '">' +
            '<div class="ssc_head">' +
                '<div class="ssc_head_icon">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                '</div>' +
                '<div>' +
                    '<div class="ssc_label">Study Session</div>' +
                    '<div class="ssc_title">' + escapeHtml(session.title || 'Study Session') + '</div>' +
                    (creatorName ? '<div class="ssc_creator">Scheduled by ' + escapeHtml(creatorName) + '</div>' : '') +
                '</div>' +
            '</div>' +
            '<div class="ssc_body">' +
                '<div class="ssc_row">' +
                    '<svg class="ssc_ri" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                    '<span>' + escapeHtml(dateStr) + ' · ' + escapeHtml(timeStr) + '</span>' +
                '</div>' +
                '<div class="ssc_row">' +
                    '<svg class="ssc_ri" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                    '<span>' + escapeHtml(session.location) + '</span>' +
                '</div>' +
                (session.link
                    ? '<div class="ssc_row"><svg class="ssc_ri" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><a href="' + escapeHtml(session.link) + '" target="_blank" rel="noopener" class="ssc_link">Join via link</a></div>'
                    : '') +
            '</div>' +
            (closed ? '<div class="ssc_closed_label">This session has ended</div>' : '') +
            '<div class="ssc_foot">' +
                '<span class="ssc_att">' +
                    '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>' +
                    '<span class="ssc_att_count">' + count + '</span> joined' +
                '</span>' +
                joinBtnHtml +
            '</div>' +
        '</div>';
    }

    function checkSessionExpiry() {
        var now = Date.now();
        var cards = container.querySelectorAll('.ssc:not(.ssc_closed)');
        cards.forEach(function (card) {
            var ts = parseInt(card.dataset.sessionTs);
            if (ts && now >= ts) {
                card.classList.add('ssc_closed');
                var btn = card.querySelector('.ssc_join_btn');
                if (btn) {
                    btn.disabled = true;
                    btn.className = 'ssc_join_btn';
                    btn.innerHTML = 'Session Closed';
                    btn.onclick = null;
                }
                var foot = card.querySelector('.ssc_foot');
                if (foot && !card.querySelector('.ssc_closed_label')) {
                    var label = document.createElement('div');
                    label.className = 'ssc_closed_label';
                    label.textContent = 'This session has ended';
                    card.insertBefore(label, foot);
                }
            }
        });
    }

    setInterval(function () {
        if (container) checkSessionExpiry();
    }, 10000);

    function openSessionModal() {
        var modal = document.getElementById('study_session_modal');
        if (modal) {
            modal.classList.add('open');
            var dateInput = document.getElementById('ss_date');
            if (dateInput) {
                var today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
            }
        }
    }

    function closeSessionModal() {
        var modal = document.getElementById('study_session_modal');
        if (modal) modal.classList.remove('open');
    }

    function sendStudySession() {
        var title    = (document.getElementById('ss_title')?.value    || '').trim();
        var date     = (document.getElementById('ss_date')?.value     || '').trim();
        var time     = (document.getElementById('ss_time')?.value     || '').trim();
        var location = (document.getElementById('ss_location')?.value || '').trim();
        var link     = (document.getElementById('ss_link')?.value     || '').trim();

        if (!date || !time || !location) return;

        var sendBtn = document.querySelector('#study_session_form .ss_send_btn');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = 'Sending...'; }

        fetch(baseUrl + '/php/api/study_sessions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'create', group_id: groupId, title: title, date: date, time: time, location: location, link: link })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (sendBtn) {
                  sendBtn.disabled = false;
                  sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Invitation';
              }
              if (data.ok) {
                  closeSessionModal();
                  document.getElementById('study_session_form')?.reset();
                  poll();
              }
          })
          .catch(function () {
              if (sendBtn) {
                  sendBtn.disabled = false;
                  sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Invitation';
              }
          });
    }

    function toggleSession(sessionId, btn) {
        var card   = btn.closest('.ssc');
        if (card && card.classList.contains('ssc_closed')) return;

        var joined = btn.classList.contains('joined');
        var action = joined ? 'leave' : 'join';

        btn.disabled = true;

        fetch(baseUrl + '/php/api/study_sessions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: action, session_id: sessionId })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              btn.disabled = false;
              if (data.ok !== undefined || data.attendee_count !== undefined) {
                  var isNowJoined = !!data.is_joined;
                  btn.className = 'ssc_join_btn' + (isNowJoined ? ' joined' : '');
                  btn.innerHTML = isNowJoined
                      ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Joined'
                      : 'Join Session';
                  var countEl = card ? card.querySelector('.ssc_att_count') : null;
                  if (countEl && data.attendee_count !== undefined) {
                      countEl.textContent = data.attendee_count;
                  }
              }
          })
          .catch(function () { btn.disabled = false; });
    }

    function showMuteNotice(msg) {
        var existing = document.querySelector('.chat_mute_notice');
        if (existing) existing.remove();
        var notice = document.createElement('div');
        notice.className = 'chat_mute_notice';
        notice.textContent = msg;
        var area = document.querySelector('.chat_input_area');
        if (area) area.parentNode.insertBefore(notice, area);
        setTimeout(function () { notice.remove(); }, 5000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function isNearBottom() {
        if (!container) return true;
        return container.scrollHeight - container.scrollTop - container.clientHeight < scrollThreshold;
    }

    function scrollBottom() {
        if (!container) return;
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
        hideScrollBtn();
    }

    function showScrollBtn() {
        if (scrollBtn) scrollBtn.classList.add('visible');
    }

    function hideScrollBtn() {
        if (scrollBtn) scrollBtn.classList.remove('visible');
    }

    function recalcScrollPosition() {
        if (!container) return;
        if (isNearBottom()) {
            requestAnimationFrame(function () {
                container.scrollTop = container.scrollHeight;
            });
        }
    }

    function initScrollTracking() {
        if (!container) return;

        container.addEventListener('scroll', function () {
            if (isNearBottom()) {
                hideScrollBtn();
            } else {
                showScrollBtn();
            }
        }, { passive: true });

        if (scrollBtn) {
            scrollBtn.addEventListener('click', function () {
                scrollBottom();
            });
        }

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function () {
                var newHeight = container.scrollHeight;
                if (newHeight !== lastKnownHeight) {
                    if (isNearBottom()) {
                        container.scrollTop = container.scrollHeight;
                    }
                    lastKnownHeight = newHeight;
                }
            });
            ro.observe(container);
        }

        if (window.visualViewport) {
            var chatMain = container.closest('.chat_main');
            window.visualViewport.addEventListener('resize', function () {
                var vpHeight = window.visualViewport.height;
                var offsetTop = window.visualViewport.offsetTop;

                if (chatMain) {
                    chatMain.style.height = vpHeight + 'px';
                    chatMain.style.transform = 'translateY(' + offsetTop + 'px)';
                }

                requestAnimationFrame(function () {
                    if (isNearBottom()) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            });

            window.visualViewport.addEventListener('scroll', function () {
                if (chatMain) {
                    chatMain.style.transform = 'translateY(' + window.visualViewport.offsetTop + 'px)';
                }
            });
        }

        var chatInput = document.getElementById('chat_input');
        if (chatInput) {
            chatInput.addEventListener('focus', function () {
                setTimeout(function () {
                    if (isNearBottom()) {
                        container.scrollTop = container.scrollHeight;
                    }
                }, 350);
            });
        }

        var imgs = container.querySelectorAll('img');
        imgs.forEach(function (img) {
            if (!img.complete) {
                img.addEventListener('load', recalcScrollPosition);
            }
        });

        if (typeof MutationObserver !== 'undefined') {
            var mo = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.addedNodes.length) {
                        m.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) {
                                var newImgs = node.querySelectorAll ? node.querySelectorAll('img') : [];
                                newImgs.forEach(function (img) {
                                    if (!img.complete) {
                                        img.addEventListener('load', recalcScrollPosition);
                                    }
                                });
                            }
                        });
                    }
                });
            });
            mo.observe(container, { childList: true, subtree: true });
        }
    }

    return {
        init:          init,
        clearFile:     clearFilePreview,
        toggleSession: toggleSession
    };
})();
