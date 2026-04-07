<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Attempt_Runtime_Snapshot_Service
{
    /**
     * @return array{
     *   ok:bool,
     *   attempt_id:int,
     *   exam_id:int,
     *   message:string,
     *   session_snapshot:array<string,mixed>,
     *   contract_snapshot:array<string,mixed>
     * }
     */
    public static function rebuild_attempt_snapshots(int $attempt_id, int $expected_exam_id = 0): array
    {
        $attempt_id = absint($attempt_id);
        $expected_exam_id = absint($expected_exam_id);

        if ($attempt_id <= 0) {
            return [
                'ok' => false,
                'attempt_id' => 0,
                'exam_id' => 0,
                'message' => 'Attempt wajib dipilih untuk refresh runtime snapshot.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'rebuild_attempt_runtime_snapshots')) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $expected_exam_id,
                'message' => 'Helper runtime snapshot belum tersedia di environment ini.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        $result = CBT_REST::rebuild_attempt_runtime_snapshots($attempt_id, $expected_exam_id);
        if (!is_array($result)) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $expected_exam_id,
                'message' => 'Helper runtime snapshot mengembalikan payload yang tidak valid.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        $result['ok'] = !empty($result['ok']);
        $result['attempt_id'] = absint($result['attempt_id'] ?? $attempt_id);
        $result['exam_id'] = absint($result['exam_id'] ?? $expected_exam_id);
        $result['message'] = trim((string) ($result['message'] ?? ''));
        $result['session_snapshot'] = is_array($result['session_snapshot'] ?? null) ? $result['session_snapshot'] : [];
        $result['contract_snapshot'] = is_array($result['contract_snapshot'] ?? null) ? $result['contract_snapshot'] : [];

        if ($result['message'] === '') {
            $result['message'] = $result['ok']
                ? 'Runtime snapshot berhasil diperbarui.'
                : 'Runtime snapshot tidak dapat diperbarui.';
        }

        return $result;
    }
}
