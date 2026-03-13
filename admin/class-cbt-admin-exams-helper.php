<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exams_Helper
{
    /**
     * @param array<int, array<string, mixed>> $subjects
     * @param array<int, string> $target_kelas
     * @return array<string, string>
     */
    public static function build_question_panel_summary(array $args): array
    {
        $subjects = isset($args['subjects']) && is_array($args['subjects']) ? $args['subjects'] : [];
        $selected_subject_id = isset($args['selected_subject_id']) ? (int) $args['selected_subject_id'] : 0;
        $title = trim((string) ($args['title'] ?? ''));
        $starts_at = (string) ($args['starts_at'] ?? '');
        $ends_at = (string) ($args['ends_at'] ?? '');
        $target_kelas = isset($args['target_kelas']) && is_array($args['target_kelas']) ? $args['target_kelas'] : [];
        $status = (string) ($args['status'] ?? 'draft');
        $duration_minutes = isset($args['duration_minutes']) ? (int) $args['duration_minutes'] : 0;
        $selected_question_count = isset($args['selected_question_count']) ? (int) $args['selected_question_count'] : 0;

        return [
            'subject_label' => self::find_subject_label($subjects, $selected_subject_id),
            'title_text' => $title !== '' ? $title : 'Belum diisi',
            'schedule_text' => self::format_schedule($starts_at, $ends_at),
            'target_kelas_text' => self::format_target_kelas_summary($target_kelas),
            'status_duration_text' => self::format_status_duration_summary($status, $duration_minutes),
            'selected_questions_text' => self::format_selected_questions_summary($selected_question_count),
        ];
    }

    public static function format_schedule(string $starts_at = '', string $ends_at = ''): string
    {
        $starts_at_label = self::format_summary_datetime($starts_at);
        $ends_at_label = self::format_summary_datetime($ends_at);

        if ($starts_at_label === '' && $ends_at_label === '') {
            return 'Belum diatur';
        }

        if ($starts_at_label !== '' && $ends_at_label !== '') {
            return $starts_at_label . ' - ' . $ends_at_label;
        }

        if ($starts_at_label !== '') {
            return 'Mulai: ' . $starts_at_label;
        }

        return 'Selesai: ' . $ends_at_label;
    }

    /**
     * @param array<int, mixed> $kelas_values
     */
    public static function format_target_kelas_summary(array $kelas_values): string
    {
        $normalized_values = self::split_target_kelas_csv($kelas_values);
        if (empty($normalized_values)) {
            return 'Belum dipilih';
        }

        if (count($normalized_values) <= 2) {
            return implode(', ', $normalized_values);
        }

        return sprintf(
            '%s, %s +%d',
            $normalized_values[0],
            $normalized_values[1],
            count($normalized_values) - 2
        );
    }

    public static function format_status_duration_summary(string $status, int $duration_minutes): string
    {
        $status_labels = [
            'draft' => 'Draft',
            'published' => 'Published',
            'closed' => 'Closed',
        ];
        $status_label = $status_labels[$status] ?? ucfirst(trim($status) !== '' ? $status : 'draft');
        $duration_label = $duration_minutes > 0 ? $duration_minutes . ' menit' : 'Durasi belum diisi';

        return $status_label . ' | ' . $duration_label;
    }

    public static function format_selected_questions_summary(int $selected_question_count): string
    {
        return $selected_question_count > 0
            ? sprintf('%d soal dipilih', $selected_question_count)
            : 'Belum ada soal';
    }

    public static function format_exam_list_target_kelas_display(string $raw_target_kelas): string
    {
        $kelas_list = self::split_target_kelas_csv($raw_target_kelas);

        return !empty($kelas_list) ? implode(', ', $kelas_list) : 'Semua kelas';
    }

    /**
     * @param array<int, array<string, mixed>> $subjects
     */
    private static function find_subject_label(array $subjects, int $selected_subject_id): string
    {
        if ($selected_subject_id <= 0) {
            return 'Belum dipilih';
        }

        foreach ($subjects as $subject) {
            $subject_id = isset($subject['id']) ? (int) $subject['id'] : 0;
            if ($subject_id !== $selected_subject_id) {
                continue;
            }

            $subject_name = trim((string) ($subject['name'] ?? ''));
            $subject_code = trim((string) ($subject['code'] ?? ''));
            if ($subject_name === '') {
                break;
            }

            return $subject_code !== ''
                ? $subject_name . ' (' . $subject_code . ')'
                : $subject_name;
        }

        return 'Belum dipilih';
    }

    private static function format_summary_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timezone = wp_timezone();
        $formats = [
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];
        $date = null;

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                $date = $parsed;
                break;
            }
        }

        if (!$date instanceof DateTimeImmutable) {
            try {
                $date = new DateTimeImmutable($value, $timezone);
            } catch (Throwable $throwable) {
                return $value;
            }
        }

        return wp_date('d M Y H:i', $date->getTimestamp(), $timezone);
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private static function split_target_kelas_csv($raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $parts[] = trim((string) $item);
            }
        } else {
            $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', (string) $raw);
            $parts = array_map('trim', explode(',', $raw));
        }

        $items = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $normalized = strtoupper(sanitize_text_field($part));
            if ($normalized === '') {
                continue;
            }

            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }
}
