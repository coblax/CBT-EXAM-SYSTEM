export function createAppMetaManager(deps) {
    var config = deps.config;
    var escapeHtml = deps.escapeHtml;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var richZoomGallerySeed = 0;

    function isLoopbackHost(host) {
        var normalized = String(host || '').trim().toLowerCase();
        return normalized === 'localhost' || normalized === '127.0.0.1' || normalized === '::1';
    }

    function isPrivateNetworkHost(host) {
        var normalized = String(host || '').trim().toLowerCase();
        if (!normalized) {
            return false;
        }

        if (isLoopbackHost(normalized)) {
            return true;
        }

        return /^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(normalized)
            || /^192\.168\.\d{1,3}\.\d{1,3}$/.test(normalized)
            || /^172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}$/.test(normalized);
    }

    function isWordPressContentPath(pathname) {
        return /^\/(?:wp-content|wp-includes|wp-admin)\//i.test(String(pathname || ''));
    }

    function normalizeRichTables(html) {
        if (!/<table\b/i.test(html) || !windowRef || !windowRef.document || typeof windowRef.document.createElement !== 'function') {
            return html;
        }

        var template = windowRef.document.createElement('template');
        template.innerHTML = html;

        Array.prototype.forEach.call(template.content.querySelectorAll('table'), function (table) {
            if (!(table instanceof windowRef.HTMLTableElement)) {
                return;
            }

            if (!table.classList.contains('cbt-rich-content-table')) {
                table.classList.add('cbt-rich-content-table');
            }

            var parent = table.parentElement;
            if (parent && parent.classList && parent.classList.contains('cbt-rich-table-wrap')) {
                return;
            }

            var wrap = windowRef.document.createElement('div');
            wrap.className = 'cbt-rich-table-wrap';
            if (table.parentNode) {
                table.parentNode.insertBefore(wrap, table);
            }
            wrap.appendChild(table);
        });

        return template.innerHTML;
    }

    function normalizePhotoUrl(value) {
        var text = String(value || '').trim();
        if (!text) {
            return '';
        }

        if (!/^https?:\/\//i.test(text) && !/^\/\//.test(text) && text.charAt(0) !== '/') {
            return '';
        }

        try {
            var parsed = new URL(text, windowRef.location.origin + '/');
            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                var current = new URL(windowRef.location.origin + '/');
                var parsedHost = String(parsed.hostname || '').toLowerCase();
                var currentHost = String(current.hostname || '').toLowerCase();
                var parsedPath = String(parsed.pathname || '');
                var parsedIsLocal = isLoopbackHost(parsedHost);
                var parsedIsPrivateNetwork = isPrivateNetworkHost(parsedHost);
                var parsedIsWordPressPath = isWordPressContentPath(parsedPath);

                if (parsedHost !== currentHost && parsedPath) {
                    if (parsedIsLocal) {
                        return parsedPath + parsed.search + parsed.hash;
                    }

                    if (parsedIsPrivateNetwork && parsedIsWordPressPath) {
                        return parsedPath + parsed.search + parsed.hash;
                    }

                    if (parsedIsWordPressPath) {
                        return current.origin + parsedPath + parsed.search + parsed.hash;
                    }
                }

                if (current.protocol === 'https:' && parsed.protocol === 'http:' && parsedHost === currentHost) {
                    parsed.protocol = 'https:';
                }

                return parsed.toString();
            }
        } catch (error) {
            return '';
        }

        return '';
    }

    function getConfiguredSchoolName() {
        var value = String(config.schoolName || config.siteName || '').trim();
        return value || 'CBT Exam';
    }

    function getConfiguredSchoolMotto() {
        return String(config.schoolMotto || '').trim();
    }

    function getConfiguredSchoolLogoUrl() {
        return normalizePhotoUrl(config.schoolLogoUrl || '');
    }

    function getConfiguredPluginAuthor() {
        return String(config.pluginAuthor || 'COBLAX').trim();
    }

    function getConfiguredPluginVersion() {
        return String(config.pluginVersion || '').trim();
    }

    function isExamFullscreenRequired() {
        return Number(config.securityForceFullscreen || 0) === 1;
    }

    function isExamCopyPasteBlocked() {
        return Number(config.securityBlockCopyPaste || 0) === 1;
    }

    function isBrowserInspectionShortcutBlockingEnabled() {
        return Number(config.securityBlockBrowserInspectionShortcuts || 0) === 1;
    }

    function isScreenshotKeyDetectionEnabled() {
        return Number(config.securityDetectScreenshotKeys || 0) === 1;
    }

    function isExamWatermarkEnabled() {
        return Number(config.securityShowExamWatermark || 0) === 1;
    }

    function getExamWatermarkOpacity() {
        var opacity = Number(config.securityExamWatermarkOpacity);
        if (!Number.isFinite(opacity)) {
            opacity = 0.07;
        }

        return Math.max(0.03, Math.min(0.12, opacity));
    }

    function isSecurityLoggingEnabled() {
        return Number(config.securityLogEvents || 0) === 1;
    }

    function isIdleDetectionEnabled() {
        return Number(config.securityDetectIdle || 0) === 1;
    }

    function isHeartbeatLostDetectionEnabled() {
        return Number(config.securityDetectHeartbeatLost || 0) === 1;
    }

    function getIdleDetectionThresholdSeconds() {
        var minutes = Number(config.securityIdleThresholdMinutes || 5);
        var seconds = Number(config.securityIdleThresholdSeconds || (minutes * 60));

        if (!Number.isFinite(seconds) || seconds <= 0) {
            seconds = 300;
        }

        return Math.max(60, Math.floor(seconds));
    }

    function isSecurityLoggingActiveForAttempt() {
        return isSecurityLoggingEnabled() && state.stage === 'exam' && (Number(state.attemptId) || 0) > 0;
    }

    function normalizeLoginHeroSchoolTag(prefix, number) {
        var normalizedPrefix = String(prefix || '').replace(/\s+/g, ' ').trim().toUpperCase();
        var compactPrefix = normalizedPrefix;

        if (/^SMK(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMKN') {
            compactPrefix = 'SMKN';
        } else if (/^SMA(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMAN') {
            compactPrefix = 'SMAN';
        } else if (/^SMP(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMPN') {
            compactPrefix = 'SMPN';
        } else if (/^SD(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SDN') {
            compactPrefix = 'SDN';
        } else if (/^MTSN?$/.test(normalizedPrefix)) {
            compactPrefix = normalizedPrefix === 'MTS' ? 'MTS' : 'MTSN';
        } else if (/^MA(?:\s+NEGERI)?$/.test(normalizedPrefix) || normalizedPrefix === 'MAN') {
            compactPrefix = (normalizedPrefix.indexOf('NEGERI') >= 0 || normalizedPrefix === 'MAN') ? 'MAN' : 'MA';
        } else if (normalizedPrefix === 'MI') {
            compactPrefix = 'MI';
        }

        var normalizedNumber = String(number || '').trim();
        return normalizedNumber !== '' ? (compactPrefix + ' ' + normalizedNumber) : compactPrefix;
    }

    function getLoginHeroSchoolBranding(schoolName) {
        var normalized = String(schoolName || '').replace(/\s+/g, ' ').trim();
        return {
            tag: '',
            title: normalized || 'CBT Exam'
        };
    }

    function getCurrentUserName() {
        return state.user && state.user.display_name
            ? String(state.user.display_name)
            : (state.user && state.user.username ? String(state.user.username) : '-');
    }

    function getCurrentUserRole() {
        return state.user && state.user.role ? String(state.user.role) : '-';
    }

    function getCurrentUserPhoto() {
        return normalizePhotoUrl(state.user && state.user.foto ? state.user.foto : '');
    }

    function getUserInitial(name) {
        var text = String(name || '').trim();
        if (!text) {
            return 'U';
        }

        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i);
            if (/[A-Za-z0-9]/.test(ch)) {
                return ch.toUpperCase();
            }
        }

        return 'U';
    }

    function safeRichHtml(value) {
        var html = String(value || '');
        var spacerMarkup = '<div class="cbt-rich-spacer" aria-hidden="true"></div>';
        html = html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
        html = html.replace(/\son\w+="[^"]*"/gi, '');
        html = html.replace(/\son\w+='[^']*'/gi, '');
        html = html.replace(/\r\n?/g, '\n');
        html = html.replace(/<p\b[^>]*>\s*(?:&nbsp;|&#160;|<br\s*\/?>|\s)*<\/p>/gi, spacerMarkup);
        html = html.replace(/(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*){2,}/gi, spacerMarkup);
        html = html.replace(/^(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*)+/i, '');
        html = html.replace(/(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*)+$/i, '');

        var hasExplicitLineBreakMarkup = /<(?:br|p|div|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|figure|figcaption|h[1-6]|hr)\b/i.test(html);
        if (!hasExplicitLineBreakMarkup && html.indexOf('\n') >= 0) {
            html = html.replace(/\n\s*\n+/g, '\n');
            html = html.replace(/\n/g, '<br />');
        }

        html = normalizeRichTables(html);

        return html;
    }

    function buildExamRichZoomMeta(type, context) {
        var normalizedType = String(type || '').toLowerCase() === 'table' ? 'table' : 'image';
        var normalizedContext = String(context || '').toLowerCase() === 'option' ? 'option' : 'question';
        var subjectText = normalizedType === 'table' ? 'tabel' : 'gambar';
        var contextText = normalizedContext === 'option' ? 'opsi' : 'soal';
        var titleText = subjectText.charAt(0).toUpperCase() + subjectText.slice(1) + ' ' + contextText.charAt(0).toUpperCase() + contextText.slice(1);

        return {
            buttonLabel: 'Perbesar ' + subjectText + ' ' + contextText,
            title: titleText
        };
    }

    function canWrapExamRichZoomNode(node) {
        if (!(node instanceof windowRef.Element)) {
            return false;
        }

        if (node.closest('.cbt-rich-zoom-target')) {
            return false;
        }

        if (node.matches('.cbt-tf-matrix-table, .cbt-tf-matrix-wrap')) {
            return false;
        }

        if (node.querySelector('input, textarea, select, button, [data-action]')) {
            return false;
        }

        return true;
    }

    function resolveStandaloneImageZoomNode(image) {
        if (!(image instanceof windowRef.HTMLImageElement)) {
            return null;
        }

        if (image.closest('.cbt-rich-zoom-target, .cbt-rich-table-wrap, .cbt-tf-matrix-wrap, .cbt-tf-matrix-table')) {
            return null;
        }

        var figure = image.closest('figure');
        if (figure instanceof windowRef.HTMLElement) {
            if (figure.querySelectorAll('img').length === 1) {
                return figure;
            }
        }

        var parent = image.parentElement;
        if (!(parent instanceof windowRef.HTMLElement)) {
            return image;
        }

        var parentTag = String(parent.tagName || '').toLowerCase();
        if (parentTag === 'label' || parentTag === 'button' || parentTag === 'a') {
            return null;
        }

        if ((parentTag === 'p' || parentTag === 'div') && parent.childElementCount === 1) {
            var siblingText = Array.prototype.some.call(parent.childNodes, function (child) {
                return child.nodeType === windowRef.Node.TEXT_NODE && String(child.textContent || '').trim() !== '';
            });
            if (!siblingText) {
                return parent;
            }
        }

        if (parentTag === 'span') {
            return null;
        }

        return image;
    }

    function nextExamRichZoomGalleryId() {
        richZoomGallerySeed += 1;
        return 'cbt-rich-zoom-gallery-' + String(richZoomGallerySeed);
    }

    function wrapExamRichZoomNode(targetNode, type, context, options) {
        if (!canWrapExamRichZoomNode(targetNode) || !targetNode.parentNode) {
            return;
        }

        var extra = options && typeof options === 'object' ? options : {};
        var meta = buildExamRichZoomMeta(type, context);
        var wrap = windowRef.document.createElement('div');
        wrap.className = 'cbt-rich-zoom-target cbt-rich-zoom-target--' + String(type || 'image');
        wrap.setAttribute('data-rich-zoom-type', String(type || 'image'));
        wrap.setAttribute('data-rich-zoom-title', meta.title);
        if (extra.galleryId) {
            wrap.setAttribute('data-rich-zoom-gallery-id', String(extra.galleryId));
            wrap.setAttribute('data-rich-zoom-gallery-index', String(Number(extra.galleryIndex) || 0));
            wrap.setAttribute('data-rich-zoom-gallery-count', String(Math.max(0, Number(extra.galleryCount) || 0)));
        }

        var toolbar = windowRef.document.createElement('div');
        toolbar.className = 'cbt-rich-zoom-toolbar';

        var button = windowRef.document.createElement('button');
        button.className = 'cbt-rich-zoom-button';
        button.setAttribute('data-action', 'open-rich-zoom');
        button.setAttribute('type', 'button');
        button.setAttribute('aria-label', meta.buttonLabel);
        button.setAttribute('title', meta.buttonLabel);
        button.innerHTML = '<span class="cbt-rich-zoom-button-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false" aria-hidden="true"><circle cx="8.25" cy="8.25" r="4.75" fill="none" stroke="currentColor" stroke-width="1.8"></circle><path d="M11.7 11.7L16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M8.25 5.8V10.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M5.8 8.25H10.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg></span><span class="cbt-visually-hidden">' + escapeHtml(meta.buttonLabel) + '</span>';

        var source = windowRef.document.createElement('div');
        source.className = 'cbt-rich-zoom-source';

        targetNode.parentNode.insertBefore(wrap, targetNode);
        wrap.appendChild(toolbar);
        toolbar.appendChild(button);
        wrap.appendChild(source);
        source.appendChild(targetNode);
    }

    function renderExamRichHtml(value, options) {
        var html = safeRichHtml(value);
        if (
            html === ''
            || !windowRef
            || !windowRef.document
            || typeof windowRef.document.createElement !== 'function'
            || !/<(?:img|figure|table)\b/i.test(html)
        ) {
            return html;
        }

        var template = windowRef.document.createElement('template');
        template.innerHTML = html;

        var context = options && typeof options === 'object' ? String(options.context || '') : '';
        var seenImageTargets = [];
        var imageTargets = [];

        Array.prototype.forEach.call(template.content.querySelectorAll('.cbt-rich-table-wrap'), function (tableWrap) {
            if (!(tableWrap instanceof windowRef.HTMLElement) || !canWrapExamRichZoomNode(tableWrap)) {
                return;
            }

            wrapExamRichZoomNode(tableWrap, 'table', context);
        });

        Array.prototype.forEach.call(template.content.querySelectorAll('figure'), function (figure) {
            if (
                !(figure instanceof windowRef.HTMLElement)
                || !figure.querySelector('img')
                || !canWrapExamRichZoomNode(figure)
                || figure.querySelectorAll('img').length !== 1
                || seenImageTargets.indexOf(figure) >= 0
            ) {
                return;
            }

            seenImageTargets.push(figure);
            imageTargets.push(figure);
        });

        Array.prototype.forEach.call(template.content.querySelectorAll('img'), function (image) {
            var targetNode = resolveStandaloneImageZoomNode(image);
            if (
                !(targetNode instanceof windowRef.Element)
                || seenImageTargets.indexOf(targetNode) >= 0
                || !canWrapExamRichZoomNode(targetNode)
            ) {
                return;
            }

            seenImageTargets.push(targetNode);
            imageTargets.push(targetNode);
        });

        var galleryId = imageTargets.length > 1 ? nextExamRichZoomGalleryId() : '';
        imageTargets.forEach(function (targetNode, index) {
            wrapExamRichZoomNode(targetNode, 'image', context, galleryId === '' ? null : {
                galleryCount: imageTargets.length,
                galleryId: galleryId,
                galleryIndex: index
            });
        });

        return template.innerHTML;
    }

    function getNavigatorConnectionStatus() {
        return (windowRef && windowRef.navigator && windowRef.navigator.onLine === false) ? 'offline' : 'online';
    }

    function isConnectionOffline() {
        return state.connectionStatus === 'offline' || getNavigatorConnectionStatus() === 'offline';
    }

    function getSyncStatusAlertMeta() {
        if (state.stage !== 'exam' || state.attemptId <= 0) {
            return null;
        }

        var pendingCount = Math.max(0, Number(state.pendingSyncCount) || 0);
        var lastSyncError = String(state.lastSyncError || '').trim();
        var heartbeatLostActive = !!state.heartbeatLostActive;
        var heartbeatLostFailureCount = Math.max(0, Number(state.heartbeatLostFailureCount) || 0);
        var message = '';

        if (state.examLockedForPendingFinish) {
            if (state.isFinishing) {
                message = 'Finalisasi ujian sedang diproses.';
            } else if (state.connectionStatus === 'offline' && pendingCount > 0) {
                message = 'Waktu habis. Jawaban dikunci di perangkat ini dan ' + String(pendingCount) + ' jawaban akan dikirim lagi saat koneksi kembali.';
            } else if (state.connectionStatus === 'offline') {
                message = 'Waktu habis. Jawaban dikunci dan ujian akan difinalkan saat koneksi kembali.';
            } else if (pendingCount > 0) {
                message = 'Waktu habis. Jawaban dikunci dan ' + String(pendingCount) + ' jawaban menunggu sinkronisasi sebelum ujian dikumpulkan.';
            } else {
                message = 'Jawaban sudah sinkron. Ujian akan segera difinalkan.';
            }
        } else if (heartbeatLostActive && getNavigatorConnectionStatus() !== 'offline') {
            message = 'Heartbeat sesi gagal ' + String(Math.max(heartbeatLostFailureCount, 3)) + 'x berturut-turut. Koneksi ke server sedang tidak stabil, tetapi ujian tetap berjalan.';
            if (pendingCount > 0) {
                message += ' ' + String(pendingCount) + ' jawaban masih menunggu sinkronisasi.';
            }
        } else if (state.connectionStatus === 'offline' && pendingCount > 0) {
            message = 'Offline, jawaban tersimpan di perangkat ini. ' + String(pendingCount) + ' jawaban menunggu koneksi.';
        } else if (state.connectionStatus === 'offline') {
            message = 'Koneksi terputus. Anda masih bisa mengerjakan soal yang sudah tersimpan di perangkat ini.';
        } else if (pendingCount > 0) {
            message = 'Sinkronisasi berjalan. ' + String(pendingCount) + ' jawaban menunggu dikirim ke server.';
        }

        if (message === '') {
            return null;
        }

        if (lastSyncError !== '' && pendingCount > 0 && message.indexOf(lastSyncError) < 0) {
            message += ' Terakhir: ' + lastSyncError;
        }

        return {
            tone: 'warning',
            message: message
        };
    }

    function renderAlert() {
        function renderDismissibleAlert(message, tone) {
            return [
                '<div class="cbt-alert cbt-alert-' + escapeHtml(tone || 'warning') + '">',
                '<div class="cbt-alert-copy">' + escapeHtml(message || '') + '</div>',
                '<button class="cbt-alert-dismiss" data-action="dismiss-alert" type="button" aria-label="Tutup informasi" title="Tutup informasi">x</button>',
                '</div>'
            ].join('');
        }

        if (state.error) {
            return renderDismissibleAlert(state.error, 'error');
        }
        if (state.notice) {
            return renderDismissibleAlert(state.notice, 'warning');
        }
        var syncAlertMeta = getSyncStatusAlertMeta();
        if (syncAlertMeta) {
            return '<div class="cbt-alert cbt-alert-' + escapeHtml(syncAlertMeta.tone || 'warning') + '">' + escapeHtml(syncAlertMeta.message || '') + '</div>';
        }
        if (state.success) {
            return renderDismissibleAlert(state.success, 'success');
        }
        return '';
    }

    function clearMessages() {
        state.error = '';
        state.notice = '';
        state.success = '';
    }

    function getExamFooterSyncMeta() {
        var pendingCount = Math.max(0, Number(state.pendingSyncCount) || 0);
        var offline = isConnectionOffline();
        var syncAlertMeta = getSyncStatusAlertMeta();
        var defaultTitle = 'Semua jawaban sudah sinkron dengan server.';
        var title = syncAlertMeta && syncAlertMeta.message
            ? String(syncAlertMeta.message)
            : defaultTitle;

        if (state.examLockedForPendingFinish) {
            if (state.isFinishing) {
                return { label: 'Final', value: 'Proses', note: 'Sedang kirim', tone: 'is-finalizing', title: title };
            }
            if (offline && pendingCount > 0) {
                return { label: 'Final', value: 'Tertahan', note: String(pendingCount) + ' pending', tone: 'is-offline', title: title };
            }
            if (offline) {
                return { label: 'Final', value: 'Offline', note: 'Tunggu koneksi', tone: 'is-offline', title: title };
            }
            if (pendingCount > 0) {
                return { label: 'Final', value: String(pendingCount) + ' pending', note: 'Menunggu final', tone: 'is-finalizing', title: title };
            }
            return { label: 'Final', value: 'Siap', note: 'Finalisasi', tone: 'is-finalizing', title: title };
        }

        if (offline && pendingCount > 0) {
            return { label: 'Sinkron', value: String(pendingCount) + ' pending', note: 'Offline lokal', tone: 'is-offline', title: title };
        }
        if (offline) {
            return { label: 'Sinkron', value: 'Offline', note: 'Lokal aman', tone: 'is-offline', title: title };
        }
        if (pendingCount > 0) {
            return { label: 'Sinkron', value: String(pendingCount) + ' pending', note: 'Menunggu server', tone: 'is-pending', title: title };
        }

        return { label: 'Sinkron', value: 'Online', note: 'Semua aman', tone: 'is-online', title: title };
    }

    function getSelectedExam() {
        var examId = Number(state.selectedExamId) || 0;
        for (var i = 0; i < state.exams.length; i++) {
            var exam = state.exams[i];
            if (Number(exam.id) === examId) {
                return exam;
            }
        }
        return null;
    }

    function findExamById(examId) {
        var targetExamId = Number(examId) || 0;
        if (targetExamId <= 0) {
            return null;
        }
        for (var i = 0; i < state.exams.length; i++) {
            var exam = state.exams[i];
            if (Number(exam && exam.id) === targetExamId) {
                return exam;
            }
        }
        return null;
    }

    return {
        clearMessages: clearMessages,
        findExamById: findExamById,
        getConfiguredPluginAuthor: getConfiguredPluginAuthor,
        getConfiguredPluginVersion: getConfiguredPluginVersion,
        getConfiguredSchoolLogoUrl: getConfiguredSchoolLogoUrl,
        getConfiguredSchoolMotto: getConfiguredSchoolMotto,
        getConfiguredSchoolName: getConfiguredSchoolName,
        getCurrentUserName: getCurrentUserName,
        getCurrentUserPhoto: getCurrentUserPhoto,
        getCurrentUserRole: getCurrentUserRole,
        getExamFooterSyncMeta: getExamFooterSyncMeta,
        getIdleDetectionThresholdSeconds: getIdleDetectionThresholdSeconds,
        getLoginHeroSchoolBranding: getLoginHeroSchoolBranding,
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        getSelectedExam: getSelectedExam,
        getSyncStatusAlertMeta: getSyncStatusAlertMeta,
        getUserInitial: getUserInitial,
        getExamWatermarkOpacity: getExamWatermarkOpacity,
        isConnectionOffline: isConnectionOffline,
        isBrowserInspectionShortcutBlockingEnabled: isBrowserInspectionShortcutBlockingEnabled,
        isExamCopyPasteBlocked: isExamCopyPasteBlocked,
        isExamFullscreenRequired: isExamFullscreenRequired,
        isExamWatermarkEnabled: isExamWatermarkEnabled,
        isHeartbeatLostDetectionEnabled: isHeartbeatLostDetectionEnabled,
        isIdleDetectionEnabled: isIdleDetectionEnabled,
        isSecurityLoggingActiveForAttempt: isSecurityLoggingActiveForAttempt,
        isSecurityLoggingEnabled: isSecurityLoggingEnabled,
        isScreenshotKeyDetectionEnabled: isScreenshotKeyDetectionEnabled,
        normalizePhotoUrl: normalizePhotoUrl,
        renderAlert: renderAlert,
        renderExamRichHtml: renderExamRichHtml,
        safeRichHtml: safeRichHtml
    };
}
