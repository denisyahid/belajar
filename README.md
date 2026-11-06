# Ruang Talenta — Tes Karyawan Baru

Aplikasi tes seleksi berbasis PHP dan MySQL, tanpa framework atau layanan pihak ketiga. Peserta mengisi nama, email, NIK, dan posisi; kemudian mengerjakan soal sesuai posisi. Semua jawaban, biodata, jumlah benar, dan nilai tersimpan dalam MySQL setelah tes dikirim. Nilai tidak ditampilkan kepada peserta.

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql` dan session.
- MySQL 5.7+ atau MariaDB 10.2+ dengan InnoDB dan utf8mb4.
- Web server seperti Apache/Nginx, atau server PHP untuk pengembangan lokal.

## Instalasi

1. Buat database dan tabel dengan menjalankan `database/schema.sql`, kemudian isi soal dari `database/seed.sql`. Misalnya:

   ```sh
   mysql -u root -p < database/schema.sql
   mysql -u root -p tes_karyawan < database/seed.sql
   ```

   Pengguna MySQL membutuhkan izin untuk membuat database pada langkah pertama. Bila database sudah dibuat oleh hosting, pilih database tersebut dan jalankan isi `schema.sql` tanpa baris `CREATE DATABASE` dan `USE`.
2. Salin `config.example.php` ke `config.php`, lalu sesuaikan nama database, host, port, pengguna, dan kata sandi. Alternatifnya, atur environment `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`. Variabel environment didahulukan dari file konfigurasi.
3. Jalankan `php -S 0.0.0.0:8000 -t public` di direktori proyek untuk pengembangan, lalu buka alamat server pada port 8000. Untuk produksi, arahkan document root web server **hanya** ke direktori `public/`, gunakan HTTPS, dan batasi akses ke database. Jangan mengekspos direktori proyek sebagai document root karena berisi soal, kunci, dan konfigurasi.

Impor seed hanya sekali: ada batasan unik untuk posisi dan nomor soal. Jangan mengimpor ulang pada database yang sudah berisi soal tanpa mengelolanya terlebih dahulu.

## Isi soal dan hasil

- **Perawat:** 30 soal dan kunci ditranskripsikan dari `soal karyawan baru.docx`. Soal nomor 21 pada dokumen tidak diberi nomor dan label pilihan ketiga nomor 16 tertulis A; keduanya dinormalisasi mengikuti urutan soal/pilihan.
- **Administrasi:** 10 soal contoh yang dibuat untuk mengisi posisi ini; dokumen sumber tidak menyertakan soal administrasi. Panitia perlu memeriksa atau menggantinya sebelum digunakan.
- **Penting:** kunci keperawatan dipertahankan sebagaimana tercantum dalam dokumen, bukan diverifikasi secara klinis. Beberapa butir dan kunci tampak perlu ditinjau, khususnya nomor 13, 21, dan 26. Minta tenaga kompeten memvalidasi seluruh soal dan kunci sebelum digunakan dalam seleksi. Nilai yang tersimpan mengikuti kunci tersebut.
- Rumus nilai adalah jumlah benar dibagi jumlah soal dikali 100. Untuk perawat, skor ini setara dengan SKOR TAHAP 1 pada dokumen; bobot 30% untuk nilai akhir seleksi **tidak** dihitung karena tahapan lainnya tidak tersedia.
- Tabel `questions` menyimpan bank soal dan kunci; `submissions` menyimpan biodata dan nilai; `answers` menyimpan pilihan dan salinan teks soal/jawaban. Contoh melihat hasil melalui MySQL:

  ```sql
  SELECT id, name, email, nik, position, correct_count, total_questions, score, submitted_at
  FROM submissions ORDER BY submitted_at DESC;
  SELECT * FROM answers WHERE submission_id = 1;
  ```

Soal dapat disesuaikan langsung di tabel `questions` oleh pengelola database. Jangan mengubah daftar soal saat peserta sedang mengerjakan tes. Tidak ada halaman admin/login dalam aplikasi ini; akses hasil dilakukan melalui database oleh petugas berwenang. Lindungi data pribadi dengan pembatasan akses, HTTPS, kebijakan retensi, dan backup yang sesuai. Aplikasi ini belum menyediakan pembatasan percobaan, pemulihan sesi, maupun pengawasan ujian; tambahkan mekanisme tersebut jika diperlukan untuk penggunaan produksi.
