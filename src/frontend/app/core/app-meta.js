export function createAppMetaManager(deps) {
    var config = deps.config;
    var escapeHtml = deps.escapeHtml;
    var state = deps.state;
    var windowRef = deps.windowRef;

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
                var parsedIsLocal = parsedHost === 'localhost' || parsedHost === '127.0.0.1' || parsedHost === '::1';

                if (parsedIsLocal && parsedHost !== currentHost && parsed.pathname) {
                    return parsed.pathname + parsed.search + parsed.hash;
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
        var branding = {
            tag: 'Portal CBT',
            title: normalized || 'CBT Exam'
        };

        if (normalized === '') {
            return branding;
        }

        var match = normalized.match(/^(SMK(?:\s+N(?:EGERI)?)?|SMKN|SMA(?:\s+N(?:EGERI)?)?|SMAN|SMP(?:\s+N(?:EGERI)?)?|SMPN|SD(?:\s+N(?:EGERI)?)?|SDN|MI|MTSN?|MAN|MA(?:\s+NEGERI)?)(?:\s+(\d+))?\s+(.+)$/i);
        if (!match) {
            return branding;
        }

        branding.tag = normalizeLoginHeroSchoolTag(match[1], match[2]);
        branding.title = String(match[3] || '').trim() || normalized;

        return branding;
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
        if (state.error) {
            return '<div class="cbt-alert cbt-alert-error">' + escapeHtml(state.error) + '</div>';
        }
        if (state.notice) {
            return '<div class="cbt-alert cbt-alert-warning">' + escapeHtml(state.notice) + '</div>';
        }
        var syncAlertMeta = getSyncStatusAlertMeta();
        if (syncAlertMeta) {
            return '<div class="cbt-alert cbt-alert-' + escapeHtml(syncAlertMeta.tone || 'warning') + '">' + escapeHtml(syncAlertMeta.message || '') + '</div>';
        }
        if (state.success) {
            return '<div class="cbt-alert cbt-alert-success">' + escapeHtml(state.success) + '</div>';
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
        isConnectionOffline: isConnectionOffline,
        isBrowserInspectionShortcutBlockingEnabled: isBrowserInspectionShortcutBlockingEnabled,
        isExamCopyPasteBlocked: isExamCopyPasteBlocked,
        isExamFullscreenRequired: isExamFullscreenRequired,
        isHeartbeatLostDetectionEnabled: isHeartbeatLostDetectionEnabled,
        isIdleDetectionEnabled: isIdleDetectionEnabled,
        isSecurityLoggingActiveForAttempt: isSecurityLoggingActiveForAttempt,
        isSecurityLoggingEnabled: isSecurityLoggingEnabled,
        normalizePhotoUrl: normalizePhotoUrl,
        renderAlert: renderAlert,
        safeRichHtml: safeRichHtml
    };
}
