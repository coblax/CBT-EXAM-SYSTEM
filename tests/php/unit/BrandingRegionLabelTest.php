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
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-users-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-report-exam-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
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

    public function test_exam_card_photo_uses_profile_style_gender_defaults(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Exam_Cards_Service::class, 'resolve_student_photo');
        $method->setAccessible(true);

        $maleResult = $method->invoke(null, 'siswa_cbt', '', 'Laki-laki');
        $femaleResult = $method->invoke(null, 'siswa_cbt', CBT_EXAM_SYSTEM_URL . 'public/images/default-student-avatar.png', 'Perempuan');

        self::assertStringEndsWith('/public/Default%20Pria.png', $maleResult);
        self::assertStringEndsWith('/public/Default%20Wanita.png', $femaleResult);
    }

    public function test_exam_card_photo_reuses_profile_url_normalization(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Exam_Cards_Service::class, 'resolve_student_photo');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'siswa_cbt', 'http://127.0.0.1/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg', 'Laki-laki');

        self::assertStringContainsString('/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg', $result);
        self::assertStringNotContainsString('127.0.0.1', $result);
    }

    public function test_report_exam_photo_uses_profile_style_normalization_and_existing_default(): void
    {
        $defaultResult = \CBT_Admin_Report_Exam_Service::resolve_student_default_photo('siswa_cbt', '');
        $normalizedResult = \CBT_Admin_Report_Exam_Service::resolve_student_default_photo(
            'siswa_cbt',
            'http://127.0.0.1/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg'
        );

        self::assertStringEndsWith('/public/Default%20Pria.png', $defaultResult);
        self::assertStringContainsString('/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg', $normalizedResult);
        self::assertStringNotContainsString('127.0.0.1', $normalizedResult);
    }
}
