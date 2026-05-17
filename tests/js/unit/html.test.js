import { describe, it, expect } from 'vitest';
import { escapeHtml } from '../../../src/frontend/app/core/html';

describe('html escapeHtml', () => {
    it('escapes ampersand', () => {
        expect(escapeHtml('a&b')).toBe('a&amp;b');
    });

    it('escapes less-than', () => {
        expect(escapeHtml('a<b')).toBe('a&lt;b');
    });

    it('escapes greater-than', () => {
        expect(escapeHtml('a>b')).toBe('a&gt;b');
    });

    it('escapes double quotes', () => {
        expect(escapeHtml('a"b')).toBe('a&quot;b');
    });

    it('escapes single quotes', () => {
        expect(escapeHtml("a'b")).toBe('a&#039;b');
    });

    it('handles null', () => {
        expect(escapeHtml(null)).toBe('');
    });

    it('handles undefined', () => {
        expect(escapeHtml(undefined)).toBe('');
    });

    it('handles numbers', () => {
        expect(escapeHtml(42)).toBe('42');
    });

    it('handles empty string', () => {
        expect(escapeHtml('')).toBe('');
    });

    it('handles complex HTML', () => {
        var result = escapeHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script>');
        expect(result).toContain('&lt;script&gt;');
    });

    it('escapes all special characters in one string', () => {
        var result = escapeHtml('&<>"\'');
        expect(result).toBe('&amp;&lt;&gt;&quot;&#039;');
    });
});
