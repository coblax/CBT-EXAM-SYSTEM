-- CBT Exam System schema snapshot.
-- Default table prefix below uses `wp_`. If your WordPress install uses a custom prefix,
-- replace every `wp_` occurrence before running this SQL manually.

CREATE TABLE wp_cbt_subjects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(30) NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_name (name),
  KEY idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_exams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT NULL,
  exam_token VARCHAR(40) NULL,
  target_kelas TEXT NULL,
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
  CONSTRAINT fk_cbt_exams_subject FOREIGN KEY (subject_id) REFERENCES wp_cbt_subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_questions (
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
  KEY idx_question_type (question_type),
  CONSTRAINT fk_cbt_questions_exam FOREIGN KEY (exam_id) REFERENCES wp_cbt_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  option_key VARCHAR(10) NULL,
  option_text LONGTEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_id (question_id),
  KEY idx_question_id_id (question_id, id),
  CONSTRAINT fk_cbt_options_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_multiple_choice (
  question_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qmc_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_multiple_answer (
  question_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qma_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_true_false (
  question_id BIGINT UNSIGNED NOT NULL,
  correct_value TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qtf_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_short_answer (
  question_id BIGINT UNSIGNED NOT NULL,
  correct_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qsa_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_essay (
  question_id BIGINT UNSIGNED NOT NULL,
  rubric_text LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qes_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_ordering (
  question_id BIGINT UNSIGNED NOT NULL,
  scoring_mode VARCHAR(30) NOT NULL DEFAULT 'exact',
  shuffle_items TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qord_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_ordering_items (
  question_id BIGINT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NOT NULL,
  correct_position SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id, option_id),
  UNIQUE KEY uniq_question_position (question_id, correct_position),
  KEY idx_option_id (option_id),
  CONSTRAINT fk_cbt_qordi_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_qordi_option FOREIGN KEY (option_id) REFERENCES wp_cbt_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_matching (
  question_id BIGINT UNSIGNED NOT NULL,
  scoring_mode VARCHAR(30) NOT NULL DEFAULT 'partial',
  shuffle_choices TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qmatch_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_matching_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  item_key VARCHAR(10) NOT NULL,
  item_position SMALLINT UNSIGNED NOT NULL,
  prompt_text LONGTEXT NOT NULL,
  correct_option_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_question_item_key (question_id, item_key),
  UNIQUE KEY uniq_question_item_position (question_id, item_position),
  KEY idx_correct_option_id (correct_option_id),
  CONSTRAINT fk_cbt_qmatchi_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_qmatchi_option FOREIGN KEY (correct_option_id) REFERENCES wp_cbt_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_cloze_dropdown (
  question_id BIGINT UNSIGNED NOT NULL,
  scoring_mode VARCHAR(30) NOT NULL DEFAULT 'partial',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qcloze_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_cloze_dropdown_blanks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  blank_key VARCHAR(10) NOT NULL,
  blank_position SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_question_blank_key (question_id, blank_key),
  UNIQUE KEY uniq_question_blank_position (question_id, blank_position),
  CONSTRAINT fk_cbt_qclozeb_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_cloze_dropdown_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  blank_id BIGINT UNSIGNED NOT NULL,
  option_key VARCHAR(10) NULL,
  option_text LONGTEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  option_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_blank (question_id, blank_id),
  KEY idx_blank_id (blank_id),
  CONSTRAINT fk_cbt_qclozeo_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_qclozeo_blank FOREIGN KEY (blank_id) REFERENCES wp_cbt_question_cloze_dropdown_blanks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_categorization (
  question_id BIGINT UNSIGNED NOT NULL,
  scoring_mode VARCHAR(30) NOT NULL DEFAULT 'partial',
  shuffle_items TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qcat_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_categorization_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  item_key VARCHAR(10) NOT NULL,
  item_position SMALLINT UNSIGNED NOT NULL,
  item_text LONGTEXT NOT NULL,
  correct_option_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_question_item_key (question_id, item_key),
  UNIQUE KEY uniq_question_item_position (question_id, item_position),
  KEY idx_correct_option_id (correct_option_id),
  CONSTRAINT fk_cbt_qcati_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_qcati_option FOREIGN KEY (correct_option_id) REFERENCES wp_cbt_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_table_completion (
  question_id BIGINT UNSIGNED NOT NULL,
  scoring_mode VARCHAR(30) NOT NULL DEFAULT 'partial',
  row_count SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  column_count SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id),
  CONSTRAINT fk_cbt_qtable_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_table_completion_cells (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  cell_key VARCHAR(10) NULL,
  row_position SMALLINT UNSIGNED NOT NULL,
  column_position SMALLINT UNSIGNED NOT NULL,
  cell_type VARCHAR(20) NOT NULL DEFAULT 'static',
  cell_text LONGTEXT NULL,
  correct_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_question_cell_position (question_id, row_position, column_position),
  UNIQUE KEY uniq_question_cell_key (question_id, cell_key),
  KEY idx_question_cell_type (question_id, cell_type),
  CONSTRAINT fk_cbt_qtablec_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_question_table_completion_cell_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  cell_id BIGINT UNSIGNED NOT NULL,
  option_key VARCHAR(10) NULL,
  option_text LONGTEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  option_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_cell (question_id, cell_id),
  KEY idx_cell_id (cell_id),
  CONSTRAINT fk_cbt_qtableo_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_qtableo_cell FOREIGN KEY (cell_id) REFERENCES wp_cbt_question_table_completion_cells(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_attempts (
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
  KEY idx_status (status),
  CONSTRAINT fk_cbt_attempts_exam FOREIGN KEY (exam_id) REFERENCES wp_cbt_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_answers (
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
  KEY idx_question_id (question_id),
  CONSTRAINT fk_cbt_answers_attempt FOREIGN KEY (attempt_id) REFERENCES wp_cbt_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbt_answers_question FOREIGN KEY (question_id) REFERENCES wp_cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_security_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id BIGINT UNSIGNED NOT NULL,
  exam_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  student_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  event_type VARCHAR(50) NOT NULL,
  severity VARCHAR(20) NOT NULL DEFAULT 'info',
  message TEXT NOT NULL,
  context_json LONGTEXT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempt_occurred_at (attempt_id, occurred_at),
  KEY idx_student_occurred_at (student_id, occurred_at),
  KEY idx_event_occurred_at (event_type, occurred_at),
  KEY idx_occurred_at (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE wp_cbt_exam_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  incident_type VARCHAR(50) NOT NULL,
  incident_at DATETIME NOT NULL,
  notes TEXT NULL,
  staff_user_id BIGINT UNSIGNED NOT NULL,
  student_name_snapshot VARCHAR(190) NOT NULL,
  student_kelas_snapshot VARCHAR(120) NULL,
  student_ruang_snapshot VARCHAR(120) NULL,
  staff_name_snapshot VARCHAR(190) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exam_incident_at (exam_id, incident_at),
  KEY idx_student_incident_at (student_id, incident_at),
  KEY idx_incident_type (incident_type),
  KEY idx_incident_at (incident_at),
  CONSTRAINT fk_cbt_incidents_exam FOREIGN KEY (exam_id) REFERENCES wp_cbt_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
