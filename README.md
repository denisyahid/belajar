# Tes Seleksi Magang RSUD Malangbong — Kemnaker

Aplikasi PHP dan MySQL untuk seleksi magang di RSUD Malangbong. Semua file aplikasi berada dalam **satu folder**; `database.sql` adalah satu-satunya file SQL untuk struktur dan soal.

## Cara upload ke hosting

1. Buka phpMyAdmin, pilih database yang akan dipakai, lalu impor **`database.sql` sekali**. File tidak membuat database baru. Untuk database yang sudah berisi data, baca bagian pembaruan di bawah dan lakukan backup terlebih dahulu.
2. Salin `config.example.php` menjadi **`config.php`** di folder yang sama. Isi kata sandi database secara privat dan siapkan hash kata sandi admin seperti petunjuk di bawah. File konfigurasi tidak disertakan di Git.
3. Upload file berikut **langsung ke satu folder** hosting, misalnya `htdocs/`, tanpa membuat subfolder:

   ```text
   .htaccess
   index.php
   admin.php
   save_answer.php
   app.php
   exam.php
   config.php
   app.js
   style.css
   logo-kemnaker.png
   logo-rsud-malangbong.png
   ```

   Aktifkan tampilan file tersembunyi di pengelola file agar `.htaccess` ikut terunggah. **Jangan upload** `database.sql`, dokumen Word, `.git`, atau `README.md` ke folder web; SQL berisi kunci soal. File SQL diimpor dari komputer melalui phpMyAdmin, bukan dijalankan melalui browser.
4. Buka alamat hosting untuk peserta, atau `admin.php` untuk panitia. PHP 8.0+ dengan ekstensi `pdo_mysql`, session, dan MySQL 5.7+/MariaDB 10.2+ diperlukan. Gunakan HTTPS. InfinityFree biasanya hanya mengizinkan koneksi database dari hostingnya sendiri.

`.htaccess` membatasi akses web ke file internal dan SQL untuk Apache dengan `mod_authz_core`/`mod_access_compat`. Sebelum digunakan, pastikan `https://alamat-anda/app.php`, `exam.php`, dan `config.php` memberikan respons **403 atau 404**, bukan isi file. Jika tidak, jangan gunakan aplikasi sampai hosting dikonfigurasi untuk menolak akses tersebut. Untuk server selain Apache, buat aturan setara; `config.php` adalah file sensitif. Sebagai tambahan, hapus `database.sql` atau dokumen sumber jika terlanjur terunggah. Jangan mengunggah file rahasia atau soal ke repository yang dapat diakses peserta.

Untuk pengembangan lokal, gunakan `php -S 0.0.0.0:8000 router.php`. Router pengembangan hanya melayani file publik yang diperlukan karena server bawaan PHP tidak membaca `.htaccess`.

## Pembaruan database yang sudah dipakai

- **Backup dulu**, hentikan tes yang sedang berjalan, dan pilih database yang benar sebelum impor. `database.sql` tidak menghapus tabel atau hasil peserta. File ini mendukung database baru dan database versi terakhir dengan paket 50 soal keperawatan (tabel `questions.section` dan `submissions.participant_id` sudah ada).
- Impor `database.sql` sekali. Paket administrasi contoh lama dikenali dan diganti; kebidanan dan laboratorium ditambahkan. Soal keperawatan serta soal lain yang telah disunting tidak ditimpa. Hasil lama tetap tersimpan pada tabel `submissions` dan `answers` sebagai salinan. Jangan impor saat peserta sedang mengerjakan.
- Database **lebih lama** yang belum mempunyai kolom `questions.section` atau `submissions.participant_id` tidak dapat diperbarui langsung dengan file ini. Backup dan migrasikan terlebih dahulu ke versi paket 50 soal sebelumnya, atau gunakan database baru. Jangan menghapus data lama tanpa verifikasi.
- SQL dapat diimpor ulang untuk menambahkan soal yang belum ada, tetapi tetap lakukan backup dan jangan mengandalkannya untuk mengganti soal yang sudah diubah. Soal dikelola langsung di tabel `questions`.

## Admin

Buka `admin.php`. Username yang diminta sudah tercantum dalam `config.example.php`. Kata sandi admin **tidak disimpan** dalam Git, sehingga akun harus disiapkan di `config.php` pada server. Di komputer/server yang memiliki PHP, buat hash:

```sh
php -r 'echo password_hash(readline("Kata sandi admin: "), PASSWORD_DEFAULT), PHP_EOL;'
```

Salin hasilnya ke `admin_password_hash` di `config.php`. Jangan menulis kata sandi biasa ke Git. Karena kata sandi sebelumnya dibagikan dalam percakapan, gunakan kata sandi baru yang kuat sebelum aplikasi dibuka. Alternatif konfigurasi menggunakan `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `ADMIN_USERNAME`, dan `ADMIN_PASSWORD_HASH` sebagai environment variable.

Panitia dapat melihat peserta, nilai dan jawaban, memfinalisasi draf peserta yang waktunya habis, atau menghapus pendaftaran yang belum selesai untuk pendaftaran ulang. Akun dikunci 15 menit setelah lima kesalahan login; sesi admin habis setelah 30 menit tidak aktif.

## Soal dan cara kerja

- Posisi aktif: keperawatan, administrasi, kebidanan, dan laboratorium/ATLM. Setiap paket terdiri dari 20 TWK dan 30 SKD. Rekam medis, radiografer, dan IPSRS belum aktif.
- Soal SKD keperawatan berasal dari `SOAL KEPERAWATAN.docx`; 20 TWK keperawatan masih contoh. Administrasi menggunakan 30 soal pertama dari `SOAL ADMINISTRASI.docx`. SKD kebidanan dan laboratorium menggunakan dokumen bidang masing-masing, sedangkan TWK ketiganya berasal dari `SOAL KEBIDANAN 2.docx`. Dokumen Word disimpan sebagai sumber, bukan file untuk diupload ke web.
- **Validasi mentor wajib dilakukan sebelum seleksi resmi.** Kunci dipertahankan dari sumber dan belum diverifikasi secara klinis/hukum; beberapa butir tampak bermasalah atau usang, termasuk TWK sumber nomor 7, laboratorium nomor 22, serta keperawatan nomor 13, 21, dan 26. Ubah `twk_is_example` menjadi `false` di `config.php` setelah TWK keperawatan diganti dan divalidasi.
- Waktu tes 60 menit dihitung di server sejak biodata dikirim, termasuk saat halaman ditutup atau dimuat ulang. Jawaban disimpan bertahap saat JavaScript aktif dan koneksi tersedia. Setelah waktu habis, perubahan tidak lagi diterima dan soal yang belum dijawab bernilai salah. Jika tab tertutup saat waktu habis, hasil difinalisasi ketika peserta kembali atau admin memilih **Finalisasi hasil**. Perubahan yang belum berhasil tersimpan tidak dihitung.
- NIK hanya dapat digunakan satu kali. Nilai akhir tampil setelah pengiriman dengan rumus jumlah benar / 50 × 100. Pendaftaran yang belum selesai dapat dihapus panitia jika sesi hilang; draf ikut terhapus.
- Penyalinan biasa, klik kanan, dan seleksi teks pada soal dibatasi di desktop dan seluler. Pembatasan ini **tidak mutlak**: screenshot, foto, alat pengembang, dan akses langsung ke sumber tetap dapat menyalin soal. Terapkan kebijakan akses, backup, dan retensi data pribadi yang sesuai.
