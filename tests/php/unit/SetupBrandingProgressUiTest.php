<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class SetupBrandingProgressUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-branding-settings.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';

        update_option(\CBT_Admin_Branding_Settings::option_key(), [
            'exam_program_name' => 'UAS',
            'school_name' => 'SMK Test',
            'school_motto' => 'Siap Ujian',
            'school_npsn' => '12345',
            'school_address' => 'Jl. Testing',
            'school_village' => 'Perawas',
            'school_district_city_ln' => 'Tanjung Pandan',
            'school_regency_country_ln' => 'Belitung',
            'school_regency_country_ln_is_city' => 0,
            'school_province_abroad_ln' => 'Bangka Belitung',
            'school_province_abroad_ln_is_foreign' => 0,
            'logo_1_attachment_id' => 11,
            'logo_2_attachment_id' => 12,
        ], false);
    }

    public function test_branding_view_renders_informative_progress_bar_hooks(): void
    {
        $html = $this->renderBrandingView();

        self::assertStringContainsString('id="cbt-setup-branding-form"', $html);
        self::assertStringContainsString('data-branding-form', $html);
        self::assertStringContainsString('data-branding-progress', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('data-branding-progress-percent', $html);
        self::assertStringContainsString('data-branding-progress-fill', $html);
        self::assertStringContainsString('data-branding-progress-step', $html);
        self::assertStringContainsString('Menyimpan CBT Branding...', $html);
        self::assertStringContainsString('Menerapkan branding ke frontend CBT dan dokumen terkait.', $html);
        self::assertStringContainsString('startBrandingProgress', $html);
        self::assertStringContainsString('completeBrandingProgress', $html);
        self::assertStringContainsString('bindBrandingFormProgress();', $html);
        self::assertStringContainsString('Logo branding siap disimpan.', $html);
        self::assertStringContainsString('Identitas sekolah dikosongkan.', $html);
        self::assertStringNotContainsString('window.location.reload', $html);
    }

    public function test_branding_view_renders_correctly_with_empty_school_name(): void
    {
        update_option(\CBT_Admin_Branding_Settings::option_key(), [
            'exam_program_name' => '',
            'school_name' => '',
            'school_motto' => '',
            'school_npsn' => '',
            'school_address' => '',
            'school_village' => '',
            'school_district_city_ln' => '',
            'school_regency_country_ln' => '',
            'school_regency_country_ln_is_city' => 0,
            'school_province_abroad_ln' => '',
            'school_province_abroad_ln_is_foreign' => 0,
            'logo_1_attachment_id' => 0,
            'logo_2_attachment_id' => 0,
        ], false);

        $html = $this->renderBrandingView();

        self::assertStringContainsString('id="cbt-setup-branding-form"', $html);
        self::assertStringContainsString('data-branding-form', $html);
        self::assertStringContainsString('Identitas sekolah dikosongkan.', $html);
    }

    public function test_branding_view_renders_progress_bar_with_aria_attributes(): void
    {
        $html = $this->renderBrandingView();

        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('aria-', $html);
    }

    private function renderBrandingView(): string
    {
        $context = \CBT_Admin_Setup_Service::build_branding_page_context([]);
        $cbt_admin_view_mode = 'branding';
        ob_start();
        extract($context, EXTR_SKIP);
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/setup/page.php';

        return (string) ob_get_clean();
    }
}
