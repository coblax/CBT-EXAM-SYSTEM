import {
    EXAM_TOKEN_ALLOWED_PATTERN,
    EXAM_TOKEN_LENGTH
} from './config';

export function clamp(value, min, max) {
    if (!Number.isFinite(value)) {
        return min;
    }
    return Math.min(max, Math.max(min, value));
}

export function normalizeExamToken(value) {
    var rawValue = String(value || '').toUpperCase();
    var normalized = '';
    for (var i = 0; i < rawValue.length && normalized.length < EXAM_TOKEN_LENGTH; i++) {
        var current = rawValue.charAt(i);
        if (!EXAM_TOKEN_ALLOWED_PATTERN.test(current)) {
            continue;
        }
        normalized += current;
    }
    return normalized;
}

export function parseDateTime(value) {
    var text = String(value || '').trim();
    if (!text) {
        return null;
    }
    var normalized = text.indexOf('T') >= 0 ? text : text.replace(' ', 'T');
    var parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }
    return parsed;
}

export function formatDateTime(value) {
    var date = parseDateTime(value);
    if (!date) {
        return '-';
    }
    try {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    } catch (error) {
        return date.toLocaleString();
    }
}

export function formatDateTimeCompact(value) {
    var date = parseDateTime(value);
    if (!date) {
        return '-';
    }
    try {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    } catch (error) {
        return formatDateTime(value);
    }
}

export function formatScoreValue(value) {
    var number = Number(value);
    if (!Number.isFinite(number)) {
        return '0';
    }

    var safeNumber = Math.abs(number) < 0.0000001 ? 0 : number;

    try {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(safeNumber);
    } catch (error) {
        if (Math.abs(safeNumber - Math.round(safeNumber)) < 0.0000001) {
            return String(Math.round(safeNumber));
        }
        return safeNumber.toFixed(2).replace(/\.?0+$/, '');
    }
}

export function formatQuestionType(type) {
    var map = {
        multiple_choice: 'Multiple Choice',
        multiple_answer: 'Multiple Answer',
        true_false: 'True / False',
        true_false_matrix: 'True / False Matrix',
        ordering: 'Ordering',
        matching: 'Matching',
        cloze_dropdown: 'Cloze Dropdown',
        categorization: 'Categorization',
        table_completion: 'Table Completion',
        short_answer: 'Short Answer',
        essay: 'Essay'
    };
    return map[type] || type || '-';
}

export function navigationQuestionTypeBadgeConfig(questionType) {
    var map = {
        multiple_choice: { code: 'MC', className: 'is-mc' },
        multiple_answer: { code: 'MA', className: 'is-ma' },
        true_false: { code: 'TF', className: 'is-tf' },
        true_false_matrix: { code: 'TFM', className: 'is-tf' },
        ordering: { code: 'ORD', className: 'is-ord' },
        matching: { code: 'MAT', className: 'is-match' },
        cloze_dropdown: { code: 'CLZ', className: 'is-cloze' },
        categorization: { code: 'CAT', className: 'is-cat' },
        table_completion: { code: 'TAB', className: 'is-table' },
        short_answer: { code: 'SA', className: 'is-sa' },
        essay: { code: 'ES', className: 'is-es' }
    };
    var key = String(questionType || '');
    return map[key] || { code: '--', className: 'is-unknown' };
}

export function formatSeconds(totalSeconds) {
    var safe = Math.max(0, Number(totalSeconds) || 0);
    var hours = Math.floor(safe / 3600);
    var minutes = Math.floor((safe % 3600) / 60);
    var seconds = safe % 60;
    return String(hours).padStart(2, '0') + ':' +
        String(minutes).padStart(2, '0') + ':' +
        String(seconds).padStart(2, '0');
}
