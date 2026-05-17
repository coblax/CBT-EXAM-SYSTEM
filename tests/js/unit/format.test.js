import { describe, it, expect } from 'vitest';
import {
    clamp,
    normalizeExamToken,
    parseDateTime,
    formatDateTime,
    formatScoreValue,
    formatQuestionType,
    navigationQuestionTypeBadgeConfig,
    formatSeconds
} from '../../../src/frontend/app/core/format';

describe('format utilities', () => {
    describe('clamp', () => {
        it('clamps value within range', () => {
            expect(clamp(5, 0, 10)).toBe(5);
            expect(clamp(-5, 0, 10)).toBe(0);
            expect(clamp(15, 0, 10)).toBe(10);
        });

        it('returns min for non-finite values', () => {
            expect(clamp(NaN, 0, 10)).toBe(0);
            expect(clamp(Infinity, 0, 10)).toBe(0);
        });
    });

    describe('normalizeExamToken', () => {
        it('uppercases and strips invalid chars', () => {
            expect(normalizeExamToken('abc123')).toBe('ABC123');
        });

        it('returns empty for empty input', () => {
            expect(normalizeExamToken('')).toBe('');
            expect(normalizeExamToken(null)).toBe('');
        });
    });

    describe('parseDateTime', () => {
        it('parses valid datetime string', () => {
            var date = parseDateTime('2026-01-15 10:30:00');
            expect(date).toBeInstanceOf(Date);
            expect(date.getFullYear()).toBe(2026);
        });

        it('parses ISO format', () => {
            var date = parseDateTime('2026-01-15T10:30:00');
            expect(date).toBeInstanceOf(Date);
        });

        it('returns null for empty string', () => {
            expect(parseDateTime('')).toBeNull();
            expect(parseDateTime(null)).toBeNull();
        });

        it('returns null for invalid date', () => {
            expect(parseDateTime('not-a-date')).toBeNull();
        });
    });

    describe('formatDateTime', () => {
        it('returns dash for invalid input', () => {
            expect(formatDateTime('')).toBe('-');
            expect(formatDateTime(null)).toBe('-');
        });

        it('formats valid date', () => {
            var result = formatDateTime('2026-01-15 10:30:00');
            expect(result).not.toBe('-');
            expect(typeof result).toBe('string');
        });
    });

    describe('formatScoreValue', () => {
        it('formats integers without decimals', () => {
            expect(formatScoreValue(85)).not.toBe('');
        });

        it('returns 0 for non-finite', () => {
            expect(formatScoreValue(NaN)).toBe('0');
            expect(formatScoreValue(Infinity)).toBe('0');
        });

        it('handles near-zero values', () => {
            expect(formatScoreValue(0.00000001)).toBe('0');
        });
    });

    describe('formatQuestionType', () => {
        it('maps known types', () => {
            expect(formatQuestionType('multiple_choice')).toBe('Multiple Choice');
            expect(formatQuestionType('essay')).toBe('Essay');
            expect(formatQuestionType('ordering')).toBe('Ordering');
        });

        it('returns type as-is for unknown', () => {
            expect(formatQuestionType('custom')).toBe('custom');
        });

        it('returns dash for empty', () => {
            expect(formatQuestionType('')).toBe('-');
            expect(formatQuestionType(null)).toBe('-');
        });
    });

    describe('navigationQuestionTypeBadgeConfig', () => {
        it('returns badge for known types', () => {
            var badge = navigationQuestionTypeBadgeConfig('multiple_choice');
            expect(badge.code).toBe('MC');
            expect(badge.className).toBe('is-mc');
        });

        it('returns unknown badge for unrecognized type', () => {
            var badge = navigationQuestionTypeBadgeConfig('custom_type');
            expect(badge.code).toBe('--');
            expect(badge.className).toBe('is-unknown');
        });
    });

    describe('formatSeconds', () => {
        it('formats 0 as 00:00:00', () => {
            expect(formatSeconds(0)).toBe('00:00:00');
        });

        it('formats hours, minutes, seconds', () => {
            expect(formatSeconds(3661)).toBe('01:01:01');
        });

        it('handles negative as 0', () => {
            expect(formatSeconds(-10)).toBe('00:00:00');
        });

        it('handles NaN as 0', () => {
            expect(formatSeconds(NaN)).toBe('00:00:00');
        });
    });
});
