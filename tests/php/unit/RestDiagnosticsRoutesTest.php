<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestDiagnosticsRoutesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_exam_cache_test_warms_start_snapshot_and_returns_diagnostics(): void
    {
        $this->bootstrapRouteStubs('administrator');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-diagnostics.php';
        $this->defineHarnessClass();

        $response = \DiagnosticsRestRoutesHarness::exam_cache_test(
            new \WP_REST_Request(
                ['exam_id' => 27, 'force_warmup' => 1],
                [],
                [],
                '/cbt/v1/diagnostics/exam-cache-test',
                'GET'
            )
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame([27], $GLOBALS['cbt_test_diagnostics_warmed_exam_ids'] ?? []);
        self::assertEmpty($GLOBALS['cbt_test_diagnostics_wrong_get_snapshot_called'] ?? false);
        self::assertSame(27, $data['exam_id'] ?? 0);
        self::assertSame('connected', $data['redis_status'] ?? '');
        self::assertSame(true, $data['ping_success'] ?? false);
        self::assertSame('ready', $data['snapshot_status'] ?? '');
        self::assertSame(12, $data['item_count'] ?? 0);
        self::assertSame(12, $data['question_count'] ?? 0);
        self::assertSame(4096, $data['payload_bytes'] ?? 0);
        self::assertSame(3600, $data['ttl_seconds'] ?? 0);
        self::assertSame(true, $data['warmup_attempted'] ?? false);
    }

    #[RunInSeparateProcess]
    public function test_permission_requires_admin_role_and_manage_options_capability(): void
    {
        $this->bootstrapRouteStubs('teacher');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-diagnostics.php';
        $this->defineHarnessClass();

        $teacherResponse = \DiagnosticsRestRoutesHarness::permission_diagnostics_admin(
            new \WP_REST_Request([], [], [], '/cbt/v1/diagnostics/exam-cache-test', 'GET')
        );

        self::assertInstanceOf(\WP_Error::class, $teacherResponse);
        self::assertSame('forbidden', $teacherResponse->get_error_code());

        $GLOBALS['cbt_test_diagnostics_role'] = 'administrator';
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $adminWithoutCapabilityResponse = \DiagnosticsRestRoutesHarness::permission_diagnostics_admin(
            new \WP_REST_Request([], [], [], '/cbt/v1/diagnostics/exam-cache-test', 'GET')
        );

        self::assertInstanceOf(\WP_Error::class, $adminWithoutCapabilityResponse);
        self::assertSame('forbidden', $adminWithoutCapabilityResponse->get_error_code());

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        self::assertSame(
            true,
            \DiagnosticsRestRoutesHarness::permission_diagnostics_admin(
                new \WP_REST_Request([], [], [], '/cbt/v1/diagnostics/exam-cache-test', 'GET')
            )
        );
    }

    #[RunInSeparateProcess]
    public function test_exam_cache_test_rejects_invalid_exam_id(): void
    {
        $this->bootstrapRouteStubs('administrator');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-diagnostics.php';
        $this->defineHarnessClass();

        $response = \DiagnosticsRestRoutesHarness::exam_cache_test(
            new \WP_REST_Request(['exam_id' => 0], [], [], '/cbt/v1/diagnostics/exam-cache-test', 'GET')
        );

        self::assertInstanceOf(\WP_Error::class, $response);
        self::assertSame('invalid_exam_id', $response->get_error_code());
    }

    private function bootstrapRouteStubs(string $role): void
    {
        $GLOBALS['cbt_test_diagnostics_role'] = $role;
        $GLOBALS['cbt_test_diagnostics_warmed_exam_ids'] = [];
        $GLOBALS['cbt_test_diagnostics_wrong_get_snapshot_called'] = false;

        if (!class_exists('CBT_Auth', false)) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function verify_request_token(\WP_REST_Request $request)
    {
        return [
            'user_id' => 77,
            'role' => (string) ($GLOBALS['cbt_test_diagnostics_role'] ?? ''),
        ];
    }

    public static function current_user_id(\WP_REST_Request $request): int
    {
        return 77;
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return (string) ($GLOBALS['cbt_test_diagnostics_role'] ?? '');
    }

    public static function is_admin_role(string $role): bool
    {
        return in_array(strtolower($role), ['admin', 'administrator'], true);
    }
}
PHP);
        }

        if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache', false)) {
            eval(<<<'PHP'
class CBT_Exam_Start_Attempt_Snapshot_Cache
{
    public static function get_exam_snapshot(int $exam_id, callable $producer): array
    {
        $GLOBALS['cbt_test_diagnostics_wrong_get_snapshot_called'] = true;
        return $producer($exam_id);
    }

    public static function get_exam_snapshot_diagnostics(int $exam_id): array
    {
        return [
            'exam_id' => $exam_id,
            'redis_available' => true,
            'redis_error' => '',
            'snapshot_status' => 'ready',
            'snapshot_message' => 'Start snapshot Redis siap dipakai untuk kontrak start_attempt.',
            'snapshot_miss_reason' => '',
            'snapshot_miss_reason_label' => '',
            'snapshot_item_count' => 12,
            'snapshot_payload_bytes' => 4096,
            'snapshot_ttl_seconds' => 3600,
            'question_count' => 12,
        ];
    }
}
PHP);
        }
    }

    private function defineHarnessClass(): void
    {
        if (class_exists('DiagnosticsRestRoutesHarness', false)) {
            return;
        }

        eval(<<<'PHP'
class DiagnosticsRestRoutesHarness
{
    use CBT_REST_Diagnostics_Routes;

    public static function warm_exam_start_attempt_snapshot(int $exam_id): void
    {
        $GLOBALS['cbt_test_diagnostics_warmed_exam_ids'][] = $exam_id;
    }
}
PHP);
    }
}
