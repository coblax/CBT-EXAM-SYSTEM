<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-ui-helper.php';

final class AdminNoticeRedirectEncodingTest extends TestCase
{
    public function test_raw_notice_with_hash_is_encoded_instead_of_becoming_a_fragment(): void
    {
        $location = 'http://example.test/wp-admin/admin.php?page=cbt-question-bank&cbt_msg=Soal #12 disimpan ke Bank Soal.&cbt_question_tab=list';

        $encoded = (string) CBT_Admin_UI_Helper::encode_notice_query_args($location);

        self::assertStringNotContainsString('#', $encoded);
        parse_str((string) parse_url($encoded, PHP_URL_QUERY), $query);
        self::assertSame('Soal #12 disimpan ke Bank Soal.', $query['cbt_msg'] ?? null);
        self::assertSame('list', $query['cbt_question_tab'] ?? null);
    }

    public function test_notice_already_sanitized_by_wp_safe_redirect_is_not_double_encoded(): void
    {
        // wp_safe_redirect() menjalankan wp_sanitize_redirect() (spasi -> %20) sebelum filter wp_redirect.
        $location = 'http://example.test/wp-admin/admin.php?page=cbt-question-bank&cbt_msg=Soal%20#1900%20dihapus.&cbt_question_paged=1';

        $encoded = (string) CBT_Admin_UI_Helper::encode_notice_query_args($location);

        parse_str((string) parse_url($encoded, PHP_URL_QUERY), $query);
        self::assertSame('Soal #1900 dihapus.', $query['cbt_msg'] ?? null);
        self::assertSame('1', $query['cbt_question_paged'] ?? null);
    }

    public function test_raw_notice_with_ampersand_and_percent_keeps_full_message(): void
    {
        $location = 'http://example.test/wp-admin/admin.php?page=cbt-exams&cbt_err=Exam "PAS IPA & IPS" 50% gagal dihapus.';

        $encoded = (string) CBT_Admin_UI_Helper::encode_notice_query_args($location);

        parse_str((string) parse_url($encoded, PHP_URL_QUERY), $query);
        self::assertSame('Exam "PAS IPA & IPS" 50% gagal dihapus.', $query['cbt_err'] ?? null);
    }

    public function test_already_encoded_notice_and_trailing_fragment_are_preserved(): void
    {
        $location = 'http://example.test/wp-admin/admin.php?page=cbt-security&cbt_msg=' . rawurlencode('Pengaturan security berhasil disimpan.') . '#security';

        self::assertSame($location, CBT_Admin_UI_Helper::encode_notice_query_args($location));
    }

    public function test_urls_without_notice_are_untouched(): void
    {
        $location = 'http://example.test/wp-admin/admin.php?page=cbt-exams#cbt-results-attempt-row-5';

        self::assertSame($location, CBT_Admin_UI_Helper::encode_notice_query_args($location));
    }
}
