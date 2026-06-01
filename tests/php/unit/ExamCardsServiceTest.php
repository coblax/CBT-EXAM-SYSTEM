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
    /** @var mixed */
    private $previousWpdb = null;
    private bool $hadPreviousWpdb = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hadPreviousWpdb = array_key_exists('wpdb', $GLOBALS);
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new ExamCardsServiceFakeWpdb([
            [
                'id' => 91,
                'title' => 'Matematika',
                'starts_at' => '2026-05-20 08:00:00',
                'ends_at' => '',
                'duration_minutes' => 90,
                'target_kelas' => 'XI-A',
            ],
        ]);

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

    protected function tearDown(): void
    {
        if ($this->hadPreviousWpdb) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }

        parent::tearDown();
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

    public function test_attendance_mode_uses_filtered_students_without_generating_passwords(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'attendance',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
        ]);

        self::assertIsArray($context);
        self::assertSame('attendance', (string) ($context['print_mode'] ?? ''));
        self::assertSame(2, (int) ($context['student_total'] ?? 0));
        self::assertCount(2, (array) ($context['students'] ?? []));
        self::assertSame('Matematika', (string) ($context['schedule_items'][0]['title'] ?? ''));
        self::assertSame('', (string) get_user_meta(31, 'cbt_plain_password', true));
        self::assertSame('', (string) get_user_meta(32, 'cbt_plain_password', true));
    }

    public function test_minutes_mode_uses_schedule_defaults_without_generating_passwords(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'minutes',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
        ]);

        self::assertIsArray($context);
        self::assertSame('minutes', (string) ($context['print_mode'] ?? ''));
        $minutes = (array) ($context['minutes_fields'] ?? []);
        self::assertSame('Matematika', (string) ($minutes['subject'] ?? ''));
        self::assertSame('2026-05-20', (string) ($minutes['date'] ?? ''));
        self::assertSame('15:00', (string) ($minutes['start_time'] ?? ''));
        self::assertSame('16:30', (string) ($minutes['end_time'] ?? ''));
        self::assertSame('R1', (string) ($minutes['room'] ?? ''));
        self::assertStringContainsString('20 Mei 2026', (string) ($context['minutes_date_label'] ?? ''));
        self::assertSame('', (string) get_user_meta(31, 'cbt_plain_password', true));
        self::assertSame('', (string) get_user_meta(32, 'cbt_plain_password', true));
    }

    public function test_participant_cards_decrypt_existing_encrypted_passwords(): void
    {
        \CBT_User_Password_Secret::store_user_plain_password(31, 'AndiCardPass');
        \CBT_User_Password_Secret::store_user_plain_password(32, 'BellaCardPass');

        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'participant',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
            'cbt_card_fields_configured' => '1',
            'cbt_card_fields' => ['name', 'password'],
        ]);

        self::assertIsArray($context);
        $students = (array) ($context['students'] ?? []);
        self::assertCount(2, $students);
        self::assertSame('AndiCardPass', (string) ($students[0]['password'] ?? ''));
        self::assertSame('BellaCardPass', (string) ($students[1]['password'] ?? ''));
    }

    public function test_participant_cards_generate_missing_passwords_into_encrypted_meta(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'participant',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
            'cbt_card_fields_configured' => '1',
            'cbt_card_fields' => ['name', 'password'],
        ]);

        self::assertIsArray($context);
        $students = (array) ($context['students'] ?? []);
        self::assertCount(2, $students);

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $printedPassword = (string) ($student['password'] ?? '');
            $stored = (string) get_user_meta($studentId, 'cbt_plain_password', true);

            self::assertNotSame('', $printedPassword);
            self::assertStringStartsWith('cbtenc:v1:', $stored);
            self::assertSame($printedPassword, \CBT_User_Password_Secret::get_user_plain_password($studentId));
        }
    }

    public function test_minutes_mode_accepts_manual_overrides_and_preserves_back_url_state(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'minutes',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_ruang' => 'R1',
            'cbt_minutes_subject' => 'Bahasa Indonesia',
            'cbt_minutes_date' => '2026-05-21',
            'cbt_minutes_start_time' => '10:00',
            'cbt_minutes_end_time' => '11:15',
            'cbt_minutes_room' => 'Lab CBT 1',
            'cbt_minutes_proctor_name' => 'Pak Pengawas 2',
            'cbt_minutes_supervisor_name' => 'Bu Pengawas',
            'cbt_minutes_notes' => "Pelaksanaan lancar.\nTidak ada kendala.",
        ]);

        self::assertIsArray($context);
        $minutes = (array) ($context['minutes_fields'] ?? []);
        self::assertSame('Bahasa Indonesia', (string) ($minutes['subject'] ?? ''));
        self::assertSame('2026-05-21', (string) ($minutes['date'] ?? ''));
        self::assertSame('10:00', (string) ($minutes['start_time'] ?? ''));
        self::assertSame('11:15', (string) ($minutes['end_time'] ?? ''));
        self::assertSame('Lab CBT 1', (string) ($minutes['room'] ?? ''));
        self::assertSame('Pak Pengawas 2', (string) ($minutes['proctor_name'] ?? ''));
        self::assertSame('Bu Pengawas', (string) ($minutes['supervisor_name'] ?? ''));
        self::assertStringContainsString('Pelaksanaan lancar.', (string) ($minutes['notes'] ?? ''));
        self::assertStringContainsString('cbt_card_print_mode=minutes', (string) ($context['back_url'] ?? ''));
        self::assertStringContainsString('cbt_minutes_subject=Bahasa+Indonesia', (string) ($context['back_url'] ?? ''));
        self::assertStringContainsString('cbt_minutes_room=Lab+CBT+1', (string) ($context['back_url'] ?? ''));
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

    public function test_invalid_print_mode_falls_back_to_participant(): void
    {
        $context = \CBT_Admin_Exam_Cards_Service::build_print_context([
            'cbt_card_print_mode' => 'not-a-mode',
            'cbt_card_kelas' => 'XI-A',
            'cbt_card_fields_configured' => '1',
            'cbt_card_fields' => ['name'],
        ]);

        self::assertIsArray($context);
        self::assertSame('participant', (string) ($context['print_mode'] ?? ''));
    }

    public function test_print_mode_options_include_attendance_and_minutes_labels(): void
    {
        $options = \CBT_Admin_Exam_Cards_Service::get_print_mode_options();

        self::assertSame('Daftar Hadir Peserta Ujian', (string) ($options['attendance']['label'] ?? ''));
        self::assertSame('Berita Acara Pelaksanaan', (string) ($options['minutes']['label'] ?? ''));
        self::assertSame('Generate & Print Daftar Hadir', (string) ($options['attendance']['submit_label'] ?? ''));
        self::assertSame('Generate & Print Berita Acara', (string) ($options['minutes']['submit_label'] ?? ''));
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

final class ExamCardsServiceFakeWpdb
{
    public string $prefix = 'wp_';
    public string $usermeta = 'wp_usermeta';

    /** @param array<int,array<string,mixed>> $scheduleRows */
    public function __construct(private array $scheduleRows = [])
    {
    }

    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    public function get_results($prepared, $output = ARRAY_A): array
    {
        return $this->scheduleRows;
    }

    public function get_col($prepared): array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $metaKey = (string) ($args[0] ?? '');

        if ($metaKey === 'kode_kelas') {
            return ['XI-A'];
        }
        if ($metaKey === 'kode_ruang') {
            return ['R1'];
        }

        return [];
    }
}
