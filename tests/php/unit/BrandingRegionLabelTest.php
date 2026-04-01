<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class BrandingRegionLabelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-branding-settings.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-report-exam-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exam-cards-service.php';
    }

    public function test_branding_settings_and_setup_context_expose_city_toggle(): void
    {
        update_option(\CBT_Admin_Branding_Settings::option_key(), [
            'school_regency_country_ln' => 'PANGKALPINANG',
            'school_regency_country_ln_is_city' => '1',
            'school_province_abroad_ln_is_foreign' => '1',
        ]);

        $settings = \CBT_Admin_Branding_Settings::get_settings();
        $context = \CBT_Admin_Setup_Service::build_branding_page_context([]);

        self::assertTrue((bool) $settings['school_regency_country_ln_is_city']);
        self::assertTrue((bool) $settings['school_province_abroad_ln_is_foreign']);
        self::assertTrue((bool) $context['school_regency_country_ln_is_city']);
        self::assertTrue((bool) $context['school_province_abroad_ln_is_foreign']);
    }

    public function test_report_header_region_line_uses_kabupaten_label_by_default(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Report_Exam_Service::class, 'build_report_header_region_line');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'Perawas', 'Tanjung Pandan', 'KAB. BELITUNG', 'Prov. Kepulauan Bangka Belitung', false, false);

        self::assertSame(
            'Desa: Perawas, Kecamatan: Tanjung Pandan, Kabupaten: BELITUNG, Provinsi: Kepulauan Bangka Belitung',
            $result
        );
    }

    public function test_report_header_region_line_uses_kota_label_when_toggle_is_enabled(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Report_Exam_Service::class, 'build_report_header_region_line');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'Melintang', 'Pangkalan Baru', 'Kota Pangkalpinang', 'Luar Negeri Singapura', true, true);

        self::assertSame(
            'Desa: Melintang, Kecamatan: Pangkalan Baru, Kota: Pangkalpinang, Luar Negeri: Singapura',
            $result
        );
    }

    public function test_exam_card_region_line_avoids_duplicate_regency_prefixes(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Exam_Cards_Service::class, 'build_card_header_region_line');
        $method->setAccessible(true);

        $kabupatenResult = $method->invoke(null, 'Perawas', 'Tanjung Pandan', 'KAB. BELITUNG', 'Prov. Kep. Babel', false, false);
        $kotaResult = $method->invoke(null, 'Melintang', 'Pangkalan Baru', 'KOTA PANGKALPINANG', 'Luar Negeri Singapura', true, true);

        self::assertSame('Desa Perawas, Kec. Tanjung Pandan, Kab. BELITUNG, Prov. Kep. Babel', $kabupatenResult);
        self::assertSame('Desa Melintang, Kec. Pangkalan Baru, Kota PANGKALPINANG, LN Singapura', $kotaResult);
    }
}
