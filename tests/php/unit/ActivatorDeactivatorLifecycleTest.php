<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ActivatorDeactivatorLifecycleTest extends TestCase
{
    public function test_schema_declares_attempt_deadline_column_and_index(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/sql/cbt_schema.sql');
        $activator = (string) file_get_contents(dirname(__DIR__, 3) . '/includes/class-cbt-activator.php');

        self::assertStringContainsString('deadline_at DATETIME NULL', $schema);
        self::assertStringContainsString('KEY idx_status_deadline_id (status, deadline_at, id)', $schema);
        self::assertStringContainsString('ADD COLUMN deadline_at DATETIME NULL AFTER extra_time_minutes', $activator);
        self::assertStringContainsString('ADD KEY idx_status_deadline_id (status, deadline_at, id)', $activator);
        self::assertStringContainsString('SET a.deadline_at = TIMESTAMPADD(', $activator);
    }

    public function test_foreign_key_migration_skips_existing_constraints(): void
    {
        $activator = (string) file_get_contents(dirname(__DIR__, 3) . '/includes/class-cbt-activator.php');

        self::assertStringContainsString('get_existing_foreign_key_constraint_names', $activator);
        self::assertStringContainsString('information_schema.TABLE_CONSTRAINTS', $activator);
        self::assertStringContainsString('CONSTRAINT_TYPE = %s', $activator);
        self::assertStringContainsString('fk_cbt_exams_subject', $activator);
        self::assertStringContainsString('isset($existing_constraints[$name])', $activator);
        self::assertStringContainsString('continue;', $activator);
    }

    #[RunInSeparateProcess]
    public function test_deactivator_stops_student_cohort_index_worker_without_deleting_data(): void
    {
        $this->defineLifecycleStub('CBT_Runtime');
        $this->defineLifecycleStub('CBT_Exam_Availability_Auto_Warm_Service');
        $this->defineLifecycleStub('CBT_Exam_Preflight_Service');
        $this->defineLifecycleStub('CBT_Snapshot_Auto_Heal_Queue_Service');
        $this->defineLifecycleStub('CBT_Login_Snapshot_Freshness_Service');
        $this->defineLifecycleStub('CBT_Adaptive_Load_Service');
        $this->defineLifecycleStub('CBT_Expired_Attempt_Finalize_Service');
        $this->defineLifecycleStub('CBT_Security_Event_Ingest');
        $this->defineLifecycleStub('CBT_Login_Readiness_Warm_Queue_Service');
        $this->defineLifecycleStub('CBT_Student_Cohort_Index_Service');

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-deactivator.php';

        $GLOBALS['cbt_test_lifecycle_deactivated'] = [];
        CBT_Deactivator::deactivate();

        self::assertContains('CBT_Student_Cohort_Index_Service', $GLOBALS['cbt_test_lifecycle_deactivated']);
        self::assertContains('CBT_Expired_Attempt_Finalize_Service', $GLOBALS['cbt_test_lifecycle_deactivated']);
        self::assertContains('CBT_Login_Readiness_Warm_Queue_Service', $GLOBALS['cbt_test_lifecycle_deactivated']);
    }

    private function defineLifecycleStub(string $class_name): void
    {
        if (class_exists($class_name, false)) {
            return;
        }

        eval(sprintf(
            'class %s { public static function deactivate(): void { $GLOBALS["cbt_test_lifecycle_deactivated"][] = %s::class; } public static function reset_availability_cache(): void {} }',
            $class_name,
            $class_name
        ));
    }
}
