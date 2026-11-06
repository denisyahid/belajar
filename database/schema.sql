CREATE DATABASE IF NOT EXISTS tes_karyawan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tes_karyawan;

CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position ENUM('perawat','administrasi') NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  option_a TEXT NOT NULL, option_b TEXT NOT NULL, option_c TEXT NOT NULL, option_d TEXT NOT NULL, option_e TEXT NOT NULL,
  answer_key ENUM('A','B','C','D','E') NOT NULL,
  UNIQUE KEY position_order (position, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_token CHAR(64) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  nik CHAR(16) NOT NULL,
  position ENUM('perawat','administrasi') NOT NULL,
  correct_count SMALLINT UNSIGNED NOT NULL,
  total_questions SMALLINT UNSIGNED NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id BIGINT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NULL,
  question_text TEXT NOT NULL,
  selected_option ENUM('A','B','C','D','E') NOT NULL,
  selected_text TEXT NOT NULL,
  is_correct BOOLEAN NOT NULL,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL,
  UNIQUE KEY submission_question (submission_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
