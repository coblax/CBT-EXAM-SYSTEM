<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AdminTokensServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-auth.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-tokens-service.php';
    }

    public function test_can_manage_exams_returns_bool(): void
    {
        $this->assertIsBool(\CBT_Admin_Tokens_Service::can_manage_exams());
    }

    public function test_is_admin_scope_returns_bool(): void
    {
        $this->assertIsBool(\CBT_Admin_Tokens_Service::is_admin_scope());
    }

    public function test_build_page_context_returns_expected_keys(): void
    {
        $ctx = \CBT_Admin_Tokens_Service::build_page_context([]);
        $this->assertArrayHasKey('notice', $ctx);
        $this->assertArrayHasKey('error', $ctx);
        $this->assertArrayHasKey('global_token_display', $ctx);
        $this->assertArrayHasKey('global_token_value', $ctx);
        $this->assertArrayHasKey('global_token_refresh_minutes', $ctx);
        $this->assertArrayHasKey('global_token_remaining_label', $ctx);
        $this->assertArrayHasKey('global_token_next_refresh_label', $ctx);
        $this->assertArrayHasKey('global_token_frontend_auto_apply', $ctx);
        $this->assertArrayHasKey('frontend_auto_status_label', $ctx);
        $this->assertArrayHasKey('frontend_auto_status_description', $ctx);
    }

    public function test_build_page_context_notice_from_query(): void
    {
        $ctx = \CBT_Admin_Tokens_Service::build_page_context(['cbt_msg' => 'Token updated']);
        $this->assertSame('Token updated', $ctx['notice']);
    }

    public function test_build_page_context_error_from_query(): void
    {
        $ctx = \CBT_Admin_Tokens_Service::build_page_context(['cbt_err' => 'Some error']);
        $this->assertSame('Some error', $ctx['error']);
    }

    public function test_build_page_context_empty_notice_error_by_default(): void
    {
        $ctx = \CBT_Admin_Tokens_Service::build_page_context([]);
        $this->assertSame('', $ctx['notice']);
        $this->assertSame('', $ctx['error']);
    }

    public function test_global_token_display_shows_dashes_when_empty(): void
    {
        $ctx = \CBT_Admin_Tokens_Service::build_page_context([]);
        // When token is empty, display should be '------'
        if ($ctx['global_token_value'] === '') {
            $this->assertSame('------', $ctx['global_token_display']);
        } else {
            $this->assertSame(strtoupper($ctx['global_token_value']), $ctx['global_token_display']);
        }
    }
}
