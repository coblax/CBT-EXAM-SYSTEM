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

    private function bootstrapAudienceScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-audience-service.php';
    }
}
