<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Activator
{
    private const OPTION_DB_VERSION = 'cbt_exam_system_db_version';
    private const OPTION_FRONTEND_PAGE_ID = 'cbt_exam_system_frontend_page_id';
    private const OPTION_SUPERVISOR_FRONTEND_PAGE_ID = 'cbt_exam_system_supervisor_page_id';
    private const OPTION_FRONTEND_PAGE_SYNC_PENDING = 'cbt_exam_system_frontend_page_sync_pending';
    private const DB_VERSION = '1.6.17';

    public static function activate(): void
    {
        self::run_migrations();
        CBT_Runtime::activate();
        if (class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
            CBT_Exam_Availability_Auto_Warm_Service::activate();
        }
        if (class_exists('CBT_Exam_Preflight_Service')) {
            CBT_Exam_Preflight_Service::activate();
        }
        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            CBT_Snapshot_Auto_Heal_Queue_Service::activate();
        }
        if (class_exists('CBT_Login_Snapshot_Freshness_Service')) {
            CBT_Login_Snapshot_Freshness_Service::activate();
        }
        if (class_exists('CBT_Adaptive_Load_Service')) {
            CBT_Adaptive_Load_Service::activate();
        }
        if (class_exists('CBT_Expired_Attempt_Finalize_Service')) {
            CBT_Expired_Attempt_Finalize_Service::activate();
        }
        if (class_exists('CBT_Security_Event_Ingest')) {
            CBT_Security_Event_Ingest::activate();
        }
        if (class_exists('CBT_Student_Cohort_Index_Service')) {
            CBT_Student_Cohort_Index_Service::activate();
        }
        if (class_exists('CBT_Login_Readiness_Warm_Queue_Service')) {
            CBT_Login_Readiness_Warm_Queue_Service::activate();
        }
    }

    public static function maybe_upgrade(): void
    {
        // Keep roles/capabilities in sync even if DB schema version is unchanged.
        self::register_roles();
        if (self::frontend_pages_need_sync()) {
            self::request_frontend_page_sync();
        }

        $installed = (string) get_option(self::OPTION_DB_VERSION, '');
        if ($installed === self::DB_VERSION) {
            return;
        }

        self::run_migrations();
    }

    private static function run_migrations(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_subjects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            code VARCHAR(30) NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_name (name),
            KEY idx_code (code)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_exams (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            exam_token VARCHAR(40) NULL,
            target_kelas TEXT NULL,
            target_agama TEXT NULL,
            target_jenis_kelamin TEXT NULL,
            restrict_to_subject_choice TINYINT(1) NOT NULL DEFAULT 0,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
            kkm_percentage DECIMAL(5,2) NOT NULL DEFAULT 75.00,
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
            randomize_options TINYINT(1) NOT NULL DEFAULT 0,
            show_student_result TINYINT(1) NOT NULL DEFAULT 1,
            enable_calculator TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_status_window (status, starts_at, ends_at),
            KEY idx_created_by (created_by),
            KEY idx_subject_id (subject_id),
            KEY idx_subject_choice_restrict (restrict_to_subject_choice, subject_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_questions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id BIGINT UNSIGNED NOT NULL,
            source_question_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            question_text LONGTEXT NOT NULL,
            question_type VARCHAR(30) NOT NULL,
            points DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            correct_text TEXT NULL,
            explanation LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_exam_id (exam_id),
            KEY idx_exam_id_id (exam_id, id),
            KEY idx_exam_active_id (exam_id, is_active, id),
            KEY idx_source_question_id (source_question_id),
            KEY idx_question_type (question_type)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_options (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            option_key VARCHAR(10) NULL,
            option_text LONGTEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_question_id (question_id),
            KEY idx_question_id_id (question_id, id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_question_multiple_choice (
            question_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (question_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_question_multiple_answer (
            question_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (question_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_question_true_false (
            question_id BIGINT UNSIGNED NOT NULL,
            correct_value TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (question_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_question_short_answer (
            question_id BIGINT UNSIGNED NOT NULL,
            correct_text TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (question_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_question_essay (
            question_id BIGINT UNSIGNED NOT NULL,
            rubric_text LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (question_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
            question_order LONGTEXT NULL,
            option_order LONGTEXT NULL,
            score DECIMAL(7,2) NOT NULL DEFAULT 0,
            max_score DECIMAL(7,2) NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            extra_time_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            deadline_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_exam_student (exam_id, student_id),
            KEY idx_exam_student_status_id (exam_id, student_id, status, id),
            KEY idx_exam_status (exam_id, status),
            KEY idx_student_status (student_id, status),
            KEY idx_student_id_id (student_id, id),
            KEY idx_status_started_id (status, started_at, id),
            KEY idx_status_deadline_id (status, deadline_at, id),
            KEY idx_status (status)
        ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cbt_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            selected_option_ids LONGTEXT NULL,
            answer_text LONGTEXT NULL,
            is_correct TINYINT(1) NULL,
            score_awarded DECIMAL(7,2) NOT NULL DEFAULT 0,
            answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_attempt_question (attempt_id, question_id),
            KEY idx_attempt_id (attempt_id),
            KEY idx_attempt_answered_at (attempt_id, answered_at),
            KEY idx_question_id (question_id)
        ) $charset;";

        $tables[] = CBT_Security_Log::get_create_table_sql($wpdb);
        $tables[] = CBT_Incident_Report::get_create_table_sql($wpdb);
        if (class_exists('CBT_Essay_AI_Grading_Service')) {
            $tables[] = CBT_Essay_AI_Grading_Service::get_create_table_sql($wpdb);
        }
        if (class_exists('CBT_Exam_Audience_Service')) {
            $tables[] = CBT_Exam_Audience_Service::get_create_table_sql($wpdb);
        }
        if (class_exists('CBT_Student_Cohort_Index_Service')) {
            $tables[] = CBT_Student_Cohort_Index_Service::get_create_table_sql($wpdb);
        }

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        // Add foreign keys when supported by current MySQL setup.
        self::maybe_add_foreign_keys($wpdb);
        self::ensure_question_source_linkage_schema($wpdb);
        self::ensure_question_activity_schema($wpdb);
        self::ensure_attempt_extra_time_schema($wpdb);
        self::ensure_exam_kkm_schema($wpdb);
        self::ensure_exam_student_result_visibility_schema($wpdb);
        self::ensure_exam_calculator_schema($wpdb);
        self::ensure_exam_audience_schema($wpdb);
        self::ensure_security_log_ingest_schema($wpdb);
        self::ensure_question_rich_text_storage_schema($wpdb);
        self::migrate_question_type_details($wpdb);
        self::seed_default_subjects($wpdb);
        self::register_roles();
        self::request_frontend_page_sync();
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
        if (class_exists('CBT_Student_Cohort_Index_Service')) {
            CBT_Student_Cohort_Index_Service::activate();
        }
    }

    public static function maybe_sync_frontend_pages(): void
    {
        if (!self::should_sync_frontend_pages_now()) {
            return;
        }

        self::ensure_frontend_pages();
        delete_option(self::OPTION_FRONTEND_PAGE_SYNC_PENDING);
    }

    private static function maybe_add_foreign_keys(wpdb $wpdb): void
    {
        $prefix = $wpdb->prefix;

        $constraints = [
            ['name' => 'fk_cbt_exams_subject', 'sql' => "ALTER TABLE {$prefix}cbt_exams ADD CONSTRAINT fk_cbt_exams_subject FOREIGN KEY (subject_id) REFERENCES {$prefix}cbt_subjects(id) ON DELETE SET NULL"],
            ['name' => 'fk_cbt_questions_exam', 'sql' => "ALTER TABLE {$prefix}cbt_questions ADD CONSTRAINT fk_cbt_questions_exam FOREIGN KEY (exam_id) REFERENCES {$prefix}cbt_exams(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_options_question', 'sql' => "ALTER TABLE {$prefix}cbt_options ADD CONSTRAINT fk_cbt_options_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_qmc_question', 'sql' => "ALTER TABLE {$prefix}cbt_question_multiple_choice ADD CONSTRAINT fk_cbt_qmc_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_qma_question', 'sql' => "ALTER TABLE {$prefix}cbt_question_multiple_answer ADD CONSTRAINT fk_cbt_qma_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_qtf_question', 'sql' => "ALTER TABLE {$prefix}cbt_question_true_false ADD CONSTRAINT fk_cbt_qtf_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_qsa_question', 'sql' => "ALTER TABLE {$prefix}cbt_question_short_answer ADD CONSTRAINT fk_cbt_qsa_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_qes_question', 'sql' => "ALTER TABLE {$prefix}cbt_question_essay ADD CONSTRAINT fk_cbt_qes_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_attempts_exam', 'sql' => "ALTER TABLE {$prefix}cbt_attempts ADD CONSTRAINT fk_cbt_attempts_exam FOREIGN KEY (exam_id) REFERENCES {$prefix}cbt_exams(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_answers_attempt', 'sql' => "ALTER TABLE {$prefix}cbt_answers ADD CONSTRAINT fk_cbt_answers_attempt FOREIGN KEY (attempt_id) REFERENCES {$prefix}cbt_attempts(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_answers_question', 'sql' => "ALTER TABLE {$prefix}cbt_answers ADD CONSTRAINT fk_cbt_answers_question FOREIGN KEY (question_id) REFERENCES {$prefix}cbt_questions(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_incidents_exam', 'sql' => "ALTER TABLE {$prefix}cbt_exam_incidents ADD CONSTRAINT fk_cbt_incidents_exam FOREIGN KEY (exam_id) REFERENCES {$prefix}cbt_exams(id) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_student_subject_choices_user', 'sql' => "ALTER TABLE {$prefix}cbt_student_subject_choices ADD CONSTRAINT fk_cbt_student_subject_choices_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE"],
            ['name' => 'fk_cbt_student_subject_choices_subject', 'sql' => "ALTER TABLE {$prefix}cbt_student_subject_choices ADD CONSTRAINT fk_cbt_student_subject_choices_subject FOREIGN KEY (subject_id) REFERENCES {$prefix}cbt_subjects(id) ON DELETE CASCADE"],
        ];

        $existing_constraints = self::get_existing_foreign_key_constraint_names($wpdb);
        foreach ($constraints as $constraint) {
            $name = strtolower((string) ($constraint['name'] ?? ''));
            if ($name !== '' && isset($existing_constraints[$name])) {
                continue;
            }

            $wpdb->query((string) ($constraint['sql'] ?? ''));
            if ($name !== '') {
                $existing_constraints[$name] = true;
            }
        }
    }

    /**
     * @return array<string,bool>
     */
    private static function get_existing_foreign_key_constraint_names(wpdb $wpdb): array
    {
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT CONSTRAINT_NAME
                   FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND CONSTRAINT_TYPE = %s",
                'FOREIGN KEY'
            )
        );
        $names = [];
        foreach ((array) $rows as $row) {
            $name = strtolower((string) $row);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return $names;
    }

    private static function ensure_security_log_ingest_schema(wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'cbt_security_logs';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('ingest_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN ingest_id CHAR(26) NULL AFTER student_id");
        }

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $has_unique = false;
        if (is_array($indexes)) {
            foreach ($indexes as $index) {
                if ((string) ($index['Key_name'] ?? '') === 'uniq_ingest_id') {
                    $has_unique = true;
                    break;
                }
            }
        }

        if (!$has_unique) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_ingest_id (ingest_id)");
        }
    }

    private static function ensure_question_source_linkage_schema(wpdb $wpdb): void
    {
        $question_table = $wpdb->prefix . 'cbt_questions';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$question_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('source_question_id', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$question_table} ADD COLUMN source_question_id BIGINT UNSIGNED NULL AFTER exam_id"
            );
        }

        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$question_table}", ARRAY_A);
        $index_names = [];
        foreach ((array) $index_rows as $index_row) {
            $index_name = (string) ($index_row['Key_name'] ?? '');
            if ($index_name !== '') {
                $index_names[$index_name] = true;
            }
        }

        if (!isset($index_names['idx_source_question_id'])) {
            $wpdb->query(
                "ALTER TABLE {$question_table} ADD KEY idx_source_question_id (source_question_id)"
            );
        }
    }

    private static function ensure_question_activity_schema(wpdb $wpdb): void
    {
        $question_table = $wpdb->prefix . 'cbt_questions';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$question_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('is_active', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$question_table} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER source_question_id"
            );
        }

        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$question_table}", ARRAY_A);
        $index_names = [];
        foreach ((array) $index_rows as $index_row) {
            $index_name = (string) ($index_row['Key_name'] ?? '');
            if ($index_name !== '') {
                $index_names[$index_name] = true;
            }
        }

        if (!isset($index_names['idx_exam_active_id'])) {
            $wpdb->query(
                "ALTER TABLE {$question_table} ADD KEY idx_exam_active_id (exam_id, is_active, id)"
            );
        }
    }

    private static function ensure_attempt_extra_time_schema(wpdb $wpdb): void
    {
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$attempt_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('extra_time_minutes', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$attempt_table} ADD COLUMN extra_time_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER duration_seconds"
            );
            $columns[] = 'extra_time_minutes';
        }

        if (!in_array('deadline_at', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$attempt_table} ADD COLUMN deadline_at DATETIME NULL AFTER extra_time_minutes"
            );
            $columns[] = 'deadline_at';
        }

        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$attempt_table}", ARRAY_A);
        $index_names = [];
        foreach ((array) $index_rows as $index_row) {
            $index_name = (string) ($index_row['Key_name'] ?? '');
            if ($index_name !== '') {
                $index_names[$index_name] = true;
            }
        }

        if (!isset($index_names['idx_status_started_id'])) {
            $wpdb->query(
                "ALTER TABLE {$attempt_table} ADD KEY idx_status_started_id (status, started_at, id)"
            );
        }

        if (!isset($index_names['idx_status_deadline_id'])) {
            $wpdb->query(
                "ALTER TABLE {$attempt_table} ADD KEY idx_status_deadline_id (status, deadline_at, id)"
            );
        }

        if (in_array('deadline_at', $columns, true)) {
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $wpdb->query(
                "UPDATE {$attempt_table} a
                 INNER JOIN {$exam_table} e ON e.id = a.exam_id
                    SET a.deadline_at = TIMESTAMPADD(
                        MINUTE,
                        GREATEST(1, COALESCE(e.duration_minutes, 0)) + GREATEST(0, COALESCE(a.extra_time_minutes, 0)),
                        a.started_at
                    )
                  WHERE a.deadline_at IS NULL
                    AND a.started_at IS NOT NULL"
            );
        }
    }

    private static function ensure_exam_kkm_schema(wpdb $wpdb): void
    {
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$exam_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('kkm_percentage', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN kkm_percentage DECIMAL(5,2) NOT NULL DEFAULT 75.00 AFTER duration_minutes"
            );
        }
    }

    private static function ensure_exam_student_result_visibility_schema(wpdb $wpdb): void
    {
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$exam_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('show_student_result', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN show_student_result TINYINT(1) NOT NULL DEFAULT 1 AFTER randomize_options"
            );
        }
    }

    private static function ensure_exam_calculator_schema(wpdb $wpdb): void
    {
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$exam_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('enable_calculator', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN enable_calculator TINYINT(1) NOT NULL DEFAULT 1 AFTER show_student_result"
            );
        }
    }

    private static function ensure_exam_audience_schema(wpdb $wpdb): void
    {
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$exam_table}", 0);
        if (!is_array($columns)) {
            $columns = [];
        }

        if (!in_array('target_agama', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN target_agama TEXT NULL AFTER target_kelas"
            );
        }
        if (!in_array('target_jenis_kelamin', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN target_jenis_kelamin TEXT NULL AFTER target_agama"
            );
        }
        if (!in_array('restrict_to_subject_choice', $columns, true)) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD COLUMN restrict_to_subject_choice TINYINT(1) NOT NULL DEFAULT 0 AFTER target_jenis_kelamin"
            );
        }

        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$exam_table}", ARRAY_A);
        $index_names = [];
        foreach ((array) $index_rows as $index_row) {
            $index_name = (string) ($index_row['Key_name'] ?? '');
            if ($index_name !== '') {
                $index_names[$index_name] = true;
            }
        }

        if (!isset($index_names['idx_subject_choice_restrict'])) {
            $wpdb->query(
                "ALTER TABLE {$exam_table} ADD KEY idx_subject_choice_restrict (restrict_to_subject_choice, subject_id)"
            );
        }
    }

    private static function ensure_question_rich_text_storage_schema(wpdb $wpdb): void
    {
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';

        $question_explanation_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$question_table} LIKE 'explanation'",
            ARRAY_A
        );
        $question_explanation_type = strtolower((string) ($question_explanation_column['Type'] ?? ''));
        if ($question_explanation_type !== 'longtext') {
            $wpdb->query(
                "ALTER TABLE {$question_table} MODIFY COLUMN explanation LONGTEXT NULL"
            );
        }

        $option_text_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$option_table} LIKE 'option_text'",
            ARRAY_A
        );
        $option_text_type = strtolower((string) ($option_text_column['Type'] ?? ''));
        if ($option_text_type !== 'longtext') {
            $wpdb->query(
                "ALTER TABLE {$option_table} MODIFY COLUMN option_text LONGTEXT NOT NULL"
            );
        }
    }

    private static function migrate_question_type_details(wpdb $wpdb): void
    {
        $prefix = $wpdb->prefix;
        $question_table = $prefix . 'cbt_questions';
        $option_table = $prefix . 'cbt_options';
        $mc_table = $prefix . 'cbt_question_multiple_choice';
        $ma_table = $prefix . 'cbt_question_multiple_answer';
        $tf_table = $prefix . 'cbt_question_true_false';
        $sa_table = $prefix . 'cbt_question_short_answer';
        $essay_table = $prefix . 'cbt_question_essay';

        $rows = $wpdb->get_results("SELECT id, question_type, correct_text FROM {$question_table}", ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $question_id = (int) ($row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $type = (string) ($row['question_type'] ?? '');
            $legacy_correct_text = trim((string) ($row['correct_text'] ?? ''));
            $now = current_time('mysql');

            if ($type === 'multiple_choice') {
                $wpdb->replace(
                    $mc_table,
                    [
                        'question_id' => $question_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s']
                );
                continue;
            }

            if ($type === 'multiple_answer') {
                $wpdb->replace(
                    $ma_table,
                    [
                        'question_id' => $question_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s']
                );
                continue;
            }

            if ($type === 'true_false') {
                $raw_tf = $legacy_correct_text;
                if ($raw_tf === '') {
                    $raw_tf = (string) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT option_text
                             FROM {$option_table}
                             WHERE question_id = %d AND is_correct = 1
                             ORDER BY id ASC
                             LIMIT 1",
                            $question_id
                        )
                    );
                }

                $wpdb->replace(
                    $tf_table,
                    [
                        'question_id' => $question_id,
                        'correct_value' => self::normalize_true_false_value($raw_tf),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%d', '%s', '%s']
                );
                continue;
            }

            if ($type === 'short_answer') {
                $wpdb->replace(
                    $sa_table,
                    [
                        'question_id' => $question_id,
                        'correct_text' => $legacy_correct_text !== '' ? $legacy_correct_text : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%s']
                );
                continue;
            }

            if ($type === 'essay') {
                $wpdb->replace(
                    $essay_table,
                    [
                        'question_id' => $question_id,
                        'rubric_text' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%s']
                );
            }
        }
    }

    private static function normalize_true_false_value(string $value): int
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
            return 0;
        }
        return 1;
    }

    private static function seed_default_subjects(wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'cbt_subjects';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return;
        }

        $defaults = [
            ['name' => 'Matematika', 'code' => 'MAT'],
            ['name' => 'Bahasa Indonesia', 'code' => 'IND'],
            ['name' => 'Bahasa Inggris', 'code' => 'ENG'],
            ['name' => 'IPA', 'code' => 'IPA'],
            ['name' => 'IPS', 'code' => 'IPS'],
        ];

        foreach ($defaults as $subject) {
            $wpdb->insert(
                $table,
                [
                    'name' => $subject['name'],
                    'code' => $subject['code'],
                    'description' => '',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    private static function ensure_frontend_pages(): void
    {
        self::ensure_frontend_page(
            self::OPTION_FRONTEND_PAGE_ID,
            'cbt-ujian',
            'CBT Ujian',
            '[cbt_exam_frontend]'
        );
        self::ensure_frontend_page(
            self::OPTION_SUPERVISOR_FRONTEND_PAGE_ID,
            'pengawas',
            'CBT Pengawas',
            '[cbt_exam_supervisor_frontend]'
        );
    }

    private static function ensure_frontend_page(
        string $option_key,
        string $page_slug,
        string $page_title,
        string $shortcode
    ): void {
        $by_slug = get_page_by_path($page_slug, OBJECT, 'page');
        if ($by_slug instanceof WP_Post) {
            update_option($option_key, (int) $by_slug->ID);
            if (strpos((string) $by_slug->post_content, $shortcode) === false) {
                wp_update_post([
                    'ID' => (int) $by_slug->ID,
                    'post_content' => trim((string) $by_slug->post_content . "\n\n" . $shortcode),
                ]);
            }
            return;
        }

        $existing_id = (int) get_option($option_key, 0);
        if ($existing_id > 0) {
            $existing_page = get_post($existing_id);
            if (
                $existing_page instanceof WP_Post &&
                $existing_page->post_type === 'page' &&
                $existing_page->post_status !== 'trash'
            ) {
                if (strpos((string) $existing_page->post_content, $shortcode) === false) {
                    wp_update_post([
                        'ID' => (int) $existing_page->ID,
                        'post_content' => trim((string) $existing_page->post_content . "\n\n" . $shortcode),
                    ]);
                }
                return;
            }
        }

        $page_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $page_title,
            'post_name' => $page_slug,
            'post_content' => $shortcode,
        ], true);

        if (!is_wp_error($page_id) && (int) $page_id > 0) {
            update_option($option_key, (int) $page_id);
        }
    }

    private static function should_sync_frontend_pages_now(): bool
    {
        if ((int) get_option(self::OPTION_FRONTEND_PAGE_SYNC_PENDING, 0) > 0) {
            return true;
        }

        return self::frontend_pages_need_sync();
    }

    private static function request_frontend_page_sync(): void
    {
        update_option(self::OPTION_FRONTEND_PAGE_SYNC_PENDING, 1);
    }

    private static function frontend_pages_need_sync(): bool
    {
        return !self::has_valid_frontend_page(
            self::OPTION_FRONTEND_PAGE_ID,
            'cbt-ujian',
            '[cbt_exam_frontend]'
        ) || !self::has_valid_frontend_page(
            self::OPTION_SUPERVISOR_FRONTEND_PAGE_ID,
            'pengawas',
            '[cbt_exam_supervisor_frontend]'
        );
    }

    private static function has_valid_frontend_page(string $option_key, string $page_slug, string $shortcode): bool
    {
        if (function_exists('get_page_by_path')) {
            $by_slug = get_page_by_path($page_slug, OBJECT, 'page');
            if ($by_slug instanceof WP_Post) {
                return (int) get_option($option_key, 0) === (int) $by_slug->ID
                    && $by_slug->post_status !== 'trash'
                    && strpos((string) $by_slug->post_content, $shortcode) !== false;
            }
        }

        $existing_id = (int) get_option($option_key, 0);
        if ($existing_id <= 0 || !function_exists('get_post')) {
            return false;
        }

        $existing_page = get_post($existing_id);
        if (
            !($existing_page instanceof WP_Post) ||
            $existing_page->post_type !== 'page' ||
            $existing_page->post_status === 'trash'
        ) {
            return false;
        }

        return strpos((string) $existing_page->post_content, $shortcode) !== false;
    }

    private static function register_roles(): void
    {
        $admin_caps = [
            'read' => true,
            'cbt_manage_system' => true,
            'cbt_manage_users' => true,
            'cbt_manage_subjects' => true,
            'cbt_manage_exams' => true,
            'cbt_manage_questions' => true,
            'cbt_view_results' => true,
            'cbt_grade_essay' => true,
            'cbt_take_exam' => true,
            'cbt_view_own_result' => true,
        ];

        $guru_caps = [
            'read' => true,
            'cbt_manage_exams' => true,
            'cbt_manage_questions' => true,
            'cbt_view_results' => true,
            'cbt_grade_essay' => true,
            'cbt_take_exam' => false,
            'cbt_manage_users' => false,
            'cbt_manage_subjects' => false,
            'cbt_manage_system' => false,
            'cbt_view_own_result' => false,
        ];

        $siswa_caps = [
            'read' => true,
            'cbt_take_exam' => true,
            'cbt_view_own_result' => true,
            'cbt_manage_exams' => false,
            'cbt_manage_questions' => false,
            'cbt_view_results' => false,
            'cbt_grade_essay' => false,
            'cbt_manage_users' => false,
            'cbt_manage_subjects' => false,
            'cbt_manage_system' => false,
        ];

        // Preferred custom roles.
        add_role('guru_cbt', 'Guru CBT', $guru_caps);
        add_role('siswa_cbt', 'Siswa CBT', $siswa_caps);

        // Backward compatibility with earlier plugin versions.
        add_role('teacher', 'Teacher', $guru_caps);
        add_role('student', 'Student', $siswa_caps);

        // Optional compatibility if school reuses WP default roles.
        $editor = get_role('editor');
        if ($editor instanceof WP_Role) {
            foreach ($guru_caps as $cap => $enabled) {
                if ($enabled) {
                    $editor->add_cap($cap);
                }
            }
        }

        $subscriber = get_role('subscriber');
        if ($subscriber instanceof WP_Role) {
            foreach ($siswa_caps as $cap => $enabled) {
                if ($enabled) {
                    $subscriber->add_cap($cap);
                }
            }
        }

        $admin = get_role('administrator');
        if ($admin instanceof WP_Role) {
            foreach ($admin_caps as $cap => $enabled) {
                if ($enabled) {
                    $admin->add_cap($cap);
                }
            }
        }
    }
}
