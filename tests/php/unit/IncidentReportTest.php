<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class IncidentReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-incident-report.php';
    }

    public function test_incident_type_definitions_returns_expected_types(): void
    {
        $definitions = \CBT_Incident_Report::incident_type_definitions();

        $this->assertIsArray($definitions);
        $this->assertArrayHasKey('attendance', $definitions);
        $this->assertArrayHasKey('cheating', $definitions);
        $this->assertArrayHasKey('room_activity', $definitions);
        $this->assertArrayHasKey('technical_issue', $definitions);
        $this->assertArrayHasKey('participant_condition', $definitions);
        $this->assertArrayHasKey('other', $definitions);
        $this->assertSame(6, count($definitions));
    }

    public function test_normalize_incident_type_returns_valid_type(): void
    {
        $this->assertSame('attendance', \CBT_Incident_Report::normalize_incident_type('attendance'));
        $this->assertSame('cheating', \CBT_Incident_Report::normalize_incident_type('cheating'));
        $this->assertSame('technical_issue', \CBT_Incident_Report::normalize_incident_type('technical_issue'));
    }

    public function test_normalize_incident_type_maps_legacy_types(): void
    {
        $this->assertSame('attendance', \CBT_Incident_Report::normalize_incident_type('late'));
        $this->assertSame('attendance', \CBT_Incident_Report::normalize_incident_type('absent'));
        $this->assertSame('room_activity', \CBT_Incident_Report::normalize_incident_type('left_room'));
        $this->assertSame('participant_condition', \CBT_Incident_Report::normalize_incident_type('sick'));
    }

    public function test_normalize_incident_type_returns_empty_for_unknown(): void
    {
        $this->assertSame('', \CBT_Incident_Report::normalize_incident_type('invalid_type'));
        $this->assertSame('', \CBT_Incident_Report::normalize_incident_type(''));
    }

    public function test_incident_type_label_returns_correct_label(): void
    {
        $this->assertSame('Kehadiran', \CBT_Incident_Report::incident_type_label('attendance'));
        $this->assertSame('Pelanggaran / Kecurangan', \CBT_Incident_Report::incident_type_label('cheating'));
        $this->assertSame('Gangguan Teknis (CBT)', \CBT_Incident_Report::incident_type_label('technical_issue'));
    }

    public function test_incident_type_label_returns_lainnya_for_unknown(): void
    {
        $this->assertSame('Lainnya', \CBT_Incident_Report::incident_type_label('nonexistent'));
    }

    public function test_incident_type_label_handles_legacy_types(): void
    {
        $this->assertSame('Kehadiran', \CBT_Incident_Report::incident_type_label('late'));
        $this->assertSame('Kondisi Peserta', \CBT_Incident_Report::incident_type_label('sick'));
    }

    public function test_incident_note_definitions_returns_all_categories(): void
    {
        $definitions = \CBT_Incident_Report::incident_note_definitions();

        $this->assertIsArray($definitions);
        $this->assertArrayHasKey('attendance', $definitions);
        $this->assertArrayHasKey('cheating', $definitions);
        $this->assertArrayHasKey('room_activity', $definitions);
        $this->assertArrayHasKey('technical_issue', $definitions);
        $this->assertArrayHasKey('participant_condition', $definitions);
        $this->assertArrayHasKey('other', $definitions);
    }

    public function test_incident_note_options_for_type_returns_options(): void
    {
        $options = \CBT_Incident_Report::incident_note_options_for_type('attendance');
        $this->assertNotEmpty($options);
        $this->assertContains('Terlambat hadir', $options);
        $this->assertContains('Tidak hadir', $options);
    }

    public function test_incident_note_options_for_type_returns_empty_for_unknown(): void
    {
        $options = \CBT_Incident_Report::incident_note_options_for_type('nonexistent');
        $this->assertEmpty($options);
    }

    public function test_is_valid_incident_note_validates_correctly(): void
    {
        $this->assertTrue(\CBT_Incident_Report::is_valid_incident_note('attendance', 'Terlambat hadir'));
        $this->assertTrue(\CBT_Incident_Report::is_valid_incident_note('cheating', 'Menggunakan HP'));
    }

    public function test_is_valid_incident_note_rejects_invalid_note(): void
    {
        $this->assertFalse(\CBT_Incident_Report::is_valid_incident_note('attendance', 'Not a valid note'));
        $this->assertFalse(\CBT_Incident_Report::is_valid_incident_note('attendance', ''));
    }

    public function test_custom_note_option_value_returns_custom_marker(): void
    {
        $this->assertSame('__custom__', \CBT_Incident_Report::custom_note_option_value());
    }

    public function test_custom_note_option_label_returns_label(): void
    {
        $label = \CBT_Incident_Report::custom_note_option_label();
        $this->assertNotEmpty($label);
        $this->assertStringContainsString('Lainnya', $label);
    }

    public function test_get_note_for_display_returns_note_when_provided(): void
    {
        $this->assertSame('Custom note', \CBT_Incident_Report::get_note_for_display('attendance', 'Custom note'));
    }

    public function test_get_note_for_display_falls_back_to_legacy_label(): void
    {
        $this->assertSame('Terlambat hadir', \CBT_Incident_Report::get_note_for_display('late', ''));
        $this->assertSame('Tidak hadir', \CBT_Incident_Report::get_note_for_display('absent', ''));
        $this->assertSame('Sakit', \CBT_Incident_Report::get_note_for_display('sick', ''));
    }

    public function test_get_note_for_display_returns_empty_for_unknown_legacy(): void
    {
        $this->assertSame('', \CBT_Incident_Report::get_note_for_display('unknown_type', ''));
    }

    public function test_incident_note_options_for_cheating_contains_expected(): void
    {
        $options = \CBT_Incident_Report::incident_note_options_for_type('cheating');
        $this->assertContains('Kecurangan', $options);
        $this->assertContains('Menggunakan HP', $options);
        $this->assertContains('Membuka aplikasi lain', $options);
    }

    public function test_incident_note_options_for_technical_issue(): void
    {
        $options = \CBT_Incident_Report::incident_note_options_for_type('technical_issue');
        $this->assertContains('Gangguan teknis', $options);
        $this->assertContains('Komputer bermasalah', $options);
        $this->assertContains('Internet terputus', $options);
    }
}
