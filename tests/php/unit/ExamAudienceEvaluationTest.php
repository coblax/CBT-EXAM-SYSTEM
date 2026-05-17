<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ExamAudienceEvaluationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_student_allowed_when_kelas_matches(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => 'XII-IPA-1,XII-IPA-2', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => 'XII-IPA-1', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertTrue($result['allowed']);
        self::assertSame('ok', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_student_rejected_when_kelas_mismatch(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => 'XII-IPA-1', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => 'XII-IPS-1', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertFalse($result['allowed']);
        self::assertSame('class_mismatch', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_empty_target_kelas_allows_all(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => '', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => 'ANY-CLASS', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertTrue($result['allowed']);
    }

    #[RunInSeparateProcess]
    public function test_agama_mismatch_rejects_student(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => '', 'target_agama' => 'Islam,Kristen', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => '', 'agama' => 'Hindu', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertFalse($result['allowed']);
        self::assertSame('agama_mismatch', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_agama_alias_normalization(): void
    {
        $this->bootstrapAudienceScaffold();

        self::assertSame('Islam', CBT_Exam_Audience_Service::normalize_agama('muslim'));
        self::assertSame('Kristen', CBT_Exam_Audience_Service::normalize_agama('protestan'));
        self::assertSame('Katolik', CBT_Exam_Audience_Service::normalize_agama('katholik'));
        self::assertSame('Buddha', CBT_Exam_Audience_Service::normalize_agama('budha'));
        self::assertSame('', CBT_Exam_Audience_Service::normalize_agama('unknown'));
    }

    #[RunInSeparateProcess]
    public function test_gender_mismatch_rejects_student(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => '', 'target_agama' => '', 'target_jenis_kelamin' => 'Perempuan', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => '', 'agama' => '', 'jenis_kelamin' => 'Laki-laki', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertFalse($result['allowed']);
        self::assertSame('gender_mismatch', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_gender_normalization(): void
    {
        $this->bootstrapAudienceScaffold();

        self::assertSame('Laki-laki', CBT_Exam_Audience_Service::normalize_gender('L'));
        self::assertSame('Laki-laki', CBT_Exam_Audience_Service::normalize_gender('pria'));
        self::assertSame('Laki-laki', CBT_Exam_Audience_Service::normalize_gender('male'));
        self::assertSame('Perempuan', CBT_Exam_Audience_Service::normalize_gender('P'));
        self::assertSame('Perempuan', CBT_Exam_Audience_Service::normalize_gender('wanita'));
        self::assertSame('', CBT_Exam_Audience_Service::normalize_gender('other'));
    }

    #[RunInSeparateProcess]
    public function test_kelas_code_normalization_uppercase(): void
    {
        $this->bootstrapAudienceScaffold();

        self::assertSame('XII-IPA-1', CBT_Exam_Audience_Service::normalize_kelas_code('xii-ipa-1'));
        self::assertSame('XII-IPA-1', CBT_Exam_Audience_Service::normalize_kelas_code('  XII-IPA-1  '));
    }

    #[RunInSeparateProcess]
    public function test_parse_target_kelas_handles_various_separators(): void
    {
        $this->bootstrapAudienceScaffold();

        $result = CBT_Exam_Audience_Service::parse_target_kelas("XII-IPA-1\nXII-IPA-2;XII-IPS-1|XII-IPS-2,XII-IPA-3");

        self::assertCount(5, $result);
        self::assertContains('XII-IPA-1', $result);
        self::assertContains('XII-IPS-2', $result);
    }

    #[RunInSeparateProcess]
    public function test_invalid_user_id_rejected(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => 'XII-IPA-1', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 0, []);

        self::assertFalse($result['allowed']);
        self::assertSame('invalid_student', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_combined_filters_all_must_pass(): void
    {
        $this->bootstrapAudienceScaffold();

        $exam = ['target_kelas' => 'XII-IPA-1', 'target_agama' => 'Islam', 'target_jenis_kelamin' => 'Laki-laki', 'restrict_to_subject_choice' => 0, 'subject_id' => 0];
        $profile = ['kode_kelas' => 'XII-IPA-1', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-laki', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 1, $profile);

        self::assertTrue($result['allowed']);
    }

    #[RunInSeparateProcess]
    public function test_subject_choice_restriction_allows_selected_subject(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([
            7 => [301, 302],
        ]);

        $exam = ['target_kelas' => '', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 1, 'subject_id' => 302];
        $profile = ['kode_kelas' => '', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 7, $profile);

        self::assertTrue($result['allowed']);
        self::assertSame('ok', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_subject_choice_restriction_rejects_unselected_subject(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([
            7 => [301],
        ]);

        $exam = ['target_kelas' => '', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 1, 'subject_id' => 302];
        $profile = ['kode_kelas' => '', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 7, $profile);

        self::assertFalse($result['allowed']);
        self::assertSame('subject_choice_mismatch', $result['reason']);
        self::assertSame([301], $result['details']['choice_subject_ids'] ?? []);
    }

    #[RunInSeparateProcess]
    public function test_subject_choice_restriction_rejects_missing_exam_subject(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([
            7 => [301],
        ]);

        $exam = ['target_kelas' => '', 'target_agama' => '', 'target_jenis_kelamin' => '', 'restrict_to_subject_choice' => 1, 'subject_id' => 0];
        $profile = ['kode_kelas' => '', 'agama' => '', 'jenis_kelamin' => '', 'nisn' => ''];

        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student($exam, 7, $profile);

        self::assertFalse($result['allowed']);
        self::assertSame('subject_choice_mismatch', $result['reason']);
        self::assertSame(0, (int) ($result['details']['subject_id'] ?? -1));
    }

    #[RunInSeparateProcess]
    public function test_filter_user_ids_by_subject_choice_keeps_only_matching_students(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([
            7 => [301],
            8 => [302],
            9 => [301, 302],
        ]);

        $result = CBT_Exam_Audience_Service::filter_user_ids_by_subject_choice([9, 8, 7], 301);

        self::assertSame([7, 9], $result);
    }

    #[RunInSeparateProcess]
    public function test_get_target_student_ids_for_exam_filters_registered_students_and_subject_choice(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([
            11 => [301],
            12 => [301],
            13 => [302],
        ]);
        $this->registerAudienceUser(11, 'student_match', 'student', 'XII-IPA-1', 'protestan', 'male');
        $this->registerAudienceUser(12, 'student_gender_mismatch', 'student', 'XII-IPA-1', 'Kristen', 'Perempuan');
        $this->registerAudienceUser(13, 'student_subject_mismatch', 'student', 'XII-IPA-1', 'Kristen', 'Laki-laki');
        $this->registerAudienceUser(14, 'teacher_not_student', 'guru', 'XII-IPA-1', 'Kristen', 'Laki-laki');
        $this->registerAudienceUser(15, 'student_class_mismatch', 'student', 'XII-IPS-1', 'Kristen', 'Laki-laki');

        $exam = [
            'target_kelas' => 'XII-IPA-1',
            'target_agama' => 'Kristen',
            'target_jenis_kelamin' => 'Laki-laki',
            'restrict_to_subject_choice' => 1,
            'subject_id' => 301,
        ];

        $result = CBT_Exam_Audience_Service::get_target_student_ids_for_exam($exam);

        self::assertSame([11], $result);
    }

    #[RunInSeparateProcess]
    public function test_get_target_student_ids_for_empty_targets_returns_no_bulk_expansion(): void
    {
        $this->bootstrapAudienceScaffold();
        $this->setupSubjectChoiceWpdb([]);
        $this->registerAudienceUser(31, 'student_empty_a', 'student', 'XII-IPA-1', 'Islam', 'Laki-laki');
        $this->registerAudienceUser(32, 'student_empty_b', 'student', 'XII-IPS-1', 'Katolik', 'Perempuan');
        $this->registerAudienceUser(33, 'teacher_empty_target', 'guru', 'XII-IPA-1', 'Islam', 'Laki-laki');

        $exam = [
            'target_kelas' => '',
            'target_agama' => '',
            'target_jenis_kelamin' => '',
            'restrict_to_subject_choice' => 0,
            'subject_id' => 0,
        ];

        $result = CBT_Exam_Audience_Service::get_target_student_ids_for_exam($exam);

        self::assertSame([], $result);
    }

    private function bootstrapAudienceScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-audience-service.php';
    }

    /**
     * @param array<int,int[]> $choicesByUser
     */
    private function setupSubjectChoiceWpdb(array $choicesByUser): void
    {
        if (!class_exists('wpdb')) {
            eval('class wpdb { public string $prefix = "wp_"; public function prepare($query, ...$args) { return $query; } }');
        }

        $GLOBALS['wpdb'] = new class($choicesByUser) extends wpdb {
            public string $prefix = 'wp_';

            /** @var array<int,int[]> */
            private array $choicesByUser;

            /** @var array<int,mixed> */
            private array $lastArgs = [];

            public function __construct(array $choicesByUser)
            {
                $this->choicesByUser = $choicesByUser;
            }

            public function prepare($query, ...$args): string
            {
                if (count($args) === 1 && is_array($args[0])) {
                    $args = $args[0];
                }
                $this->lastArgs = array_values($args);

                return (string) $query;
            }

            public function get_var($query): string
            {
                $userId = (int) ($this->lastArgs[0] ?? 0);
                $subjectId = (int) ($this->lastArgs[1] ?? 0);

                return in_array($subjectId, $this->choicesByUser[$userId] ?? [], true) ? '1' : '0';
            }

            public function get_col($query): array
            {
                if (str_contains((string) $query, 'WHERE user_id = %d')) {
                    $userId = (int) ($this->lastArgs[0] ?? 0);
                    $choices = $this->choicesByUser[$userId] ?? [];
                    sort($choices, SORT_NUMERIC);

                    return array_map('strval', $choices);
                }

                $subjectId = (int) ($this->lastArgs[0] ?? 0);
                $userIds = array_map('intval', array_slice($this->lastArgs, 1));
                $matched = [];
                foreach ($userIds as $userId) {
                    if (in_array($subjectId, $this->choicesByUser[$userId] ?? [], true)) {
                        $matched[] = (string) $userId;
                    }
                }
                sort($matched, SORT_NUMERIC);

                return $matched;
            }
        };
    }

    private function registerAudienceUser(int $userId, string $login, string $role, string $kelas, string $agama, string $gender): void
    {
        cbt_test_register_user([
            'ID' => $userId,
            'user_login' => $login,
            'user_email' => $login . '@test.com',
            'user_pass' => 'password',
            'display_name' => ucfirst($login),
            'roles' => [$role],
        ]);
        update_user_meta($userId, 'kode_kelas', $kelas);
        update_user_meta($userId, 'agama', $agama);
        update_user_meta($userId, 'jenis_kelamin', $gender);
    }
}
