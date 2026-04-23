<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ActivatorDeactivatorLifecycleTest extends TestCase
{
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
