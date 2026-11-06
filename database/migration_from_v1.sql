-- Backup terlebih dahulu. Hapus/selesaikan duplikasi NIK lama secara manual
-- sebelum menjalankan migrasi ini. Pilih database aplikasi terlebih dahulu.
CREATE TABLE participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  nik CHAR(16) NOT NULL,
  position ENUM('perawat','administrasi') NOT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_participant_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO participants (name, email, nik, position, started_at)
SELECT name, email, nik, position, submitted_at FROM submissions;
ALTER TABLE submissions ADD COLUMN participant_id BIGINT UNSIGNED NULL;
UPDATE submissions s JOIN participants p ON p.nik = s.nik SET s.participant_id = p.id;
ALTER TABLE submissions MODIFY participant_id BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY unique_submission_participant (participant_id),
  ADD CONSTRAINT fk_submission_participant FOREIGN KEY (participant_id) REFERENCES participants(id);
CREATE TABLE admin_login_attempts (
  ip_hash CHAR(64) PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
