<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-branding-settings.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-users-service.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exam-cards-service.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

final class ExamCardsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        cbt_test_register_user([
            'ID' => 31,
            'user_login' => 'andi',
            'user_email' => 'andi@example.test',
            'display_name' => 'Andi Pratama',
            'roles' => ['siswa_cbt'],
        ]);
        update_user_meta(31, 'kode_kelas', 'XI-A');
        update_user_meta(31, 'kode_ruang', 'R1');
        update_user_meta(31, 'nisn', '31001');

        cbt_test_register_user([
            'ID' => 32,
            'user_login' => 'bella',
            'user_email' => 'bella@example.test',
            'display_name' => 'Bella Putri',
            'roles' => ['siswa_cbt'],
        ]);
        update_user_meta(32, 'kode_kelas', 'XI-A');
        update_user_meta(32, 'kode_ruang', 'R1');
        update_user_meta(32, 'nisn', '32001');
    }

    public function test_build_print_context_supports_desk_number_mode_without_generating_passwords(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'desk_number',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
            'cbt_card_seat_start' => '5',
            'cbt_card_seat_padding' => '3',
        ]);

        self::assertIsArray($context);
        self::assertSame('desk_number', (string) ($context['print_mode'] ?? ''));
        self::assertSame(5, (int) ($context['seat_start_number'] ?? 0));
        self::assertSame(3, (int) ($context['seat_padding'] ?? 0));
        self::assertCount(2, (array) ($context['seat_cards'] ?? []));
        self::assertSame('005', (string) ($context['seat_cards'][0]['seat_number_display'] ?? ''));
        self::assertSame('006', (string) ($context['seat_cards'][1]['seat_number_display'] ?? ''));
        self::assertSame('', (string) get_user_meta(31, 'cbt_plain_password', true));
        self::assertSame('', (string) get_user_meta(32, 'cbt_plain_password', true));
    }

    public function test_participant_mode_still_requires_display_fields_when_explicitly_empty(): void
    {
        $result = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'participant',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_fields_configured' => '1',
            'cbt_card_fields' => [],
        ]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('exam_cards_display_fields_empty', $result->get_error_code());
    }

    public function test_build_desk_number_cards_keeps_full_number_when_padding_is_smaller_than_value_length(): void
    {
        $reflection = new ReflectionClass(\CBT_Admin_Exam_Cards_Service::class);
        $method = $reflection->getMethod('build_desk_number_cards');
        $method->setAccessible(true);

        $cards = $method->invoke(null, [[
            'id' => 77,
            'name' => 'Siswa Uji',
            'kelas' => 'XI-A',
            'ruang' => 'R1',
        ]], 998, 2);

        self::assertSame(998, (int) ($cards[0]['seat_number_raw'] ?? 0));
        self::assertSame('998', (string) ($cards[0]['seat_number_display'] ?? ''));
    }
}
