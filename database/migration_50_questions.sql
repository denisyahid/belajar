-- UNTUK DATABASE VERSI SEBELUMNYA: backup terlebih dahulu, dan jalankan hanya sekali.
-- Prasyarat: 30 soal perawat lama memiliki sort_order 1 sampai 30.
-- Pastikan tidak ada peserta aktif. Migrasi mempertahankan ID soal dan jawaban lama.
ALTER TABLE questions ADD COLUMN section ENUM('TWK','SKD') NOT NULL DEFAULT 'SKD' AFTER sort_order;
UPDATE questions SET sort_order = sort_order + 20 WHERE position = 'perawat' ORDER BY sort_order DESC;
-- Selanjutnya impor database/seed_twk_example.sql sekali. Soal TWK hanya contoh.
