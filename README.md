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
   excel.php
   config.php
   app.js
   style.css
   logo-kemnaker.png
   logo-rsud-malangbong.png
   soal-radiografer-siku.jpg
   ```

   Aktifkan tampilan file tersembunyi di pengelola file agar `.htaccess` ikut terunggah. **Jangan upload** `database.sql`, dokumen Word, `.git`, `README.md`, atau `REFERENSI_RADIOGRAFER.md` ke folder web; SQL berisi kunci soal. File SQL diimpor dari komputer melalui phpMyAdmin, bukan dijalankan melalui browser.
4. Buka alamat hosting untuk peserta, atau `admin.php` untuk panitia. PHP 8.0+ dengan ekstensi `pdo_mysql`, session, dan MySQL 5.7+/MariaDB 10.2+ diperlukan. Gunakan HTTPS. InfinityFree biasanya hanya mengizinkan koneksi database dari hostingnya sendiri.

`.htaccess` membatasi akses web ke file internal dan SQL untuk Apache dengan `mod_authz_core`/`mod_access_compat`. Sebelum digunakan, pastikan `https://alamat-anda/app.php`, `exam.php`, `excel.php`, dan `config.php` memberikan respons **403 atau 404**, bukan isi file. Jika tidak, jangan gunakan aplikasi sampai hosting dikonfigurasi untuk menolak akses tersebut. Untuk server selain Apache, buat aturan setara; `config.php` adalah file sensitif. Sebagai tambahan, hapus `database.sql` atau dokumen sumber jika terlanjur terunggah. Jangan mengunggah file rahasia atau soal ke repository yang dapat diakses peserta.

Untuk pengembangan lokal, gunakan `php -S 0.0.0.0:8000 router.php`. Router pengembangan hanya melayani file publik yang diperlukan karena server bawaan PHP tidak membaca `.htaccess`.

## Pembaruan database yang sudah dipakai

- **Backup dulu**, hentikan tes yang sedang berjalan, dan pilih database yang benar sebelum impor. `database.sql` tidak menghapus tabel atau hasil peserta. File ini mendukung database baru dan database versi terakhir dengan paket 50 soal keperawatan (tabel `questions.section` dan `submissions.participant_id` sudah ada).
- Impor `database.sql` sekali. Paket administrasi contoh lama dikenali dan diganti; kebidanan, laboratorium, dan rekam medis ditambahkan. Untuk mengaktifkan radiografer pada database yang sudah ada, impor ulang `database.sql` versi terbaru setelah backup; ENUM, soal, dan tabel `question_images` untuk gambar soal akan ditambahkan tanpa menghapus hasil peserta. Impor sebelum mengunggah versi aplikasi terbaru. Soal keperawatan serta soal lain yang telah disunting tidak ditimpa. Hasil lama tetap tersimpan pada tabel `submissions` dan `answers` sebagai salinan. Jangan impor saat peserta sedang mengerjakan.
- Database **lebih lama** yang belum mempunyai kolom `questions.section` atau `submissions.participant_id` tidak dapat diperbarui langsung dengan file ini. Backup dan migrasikan terlebih dahulu ke versi paket 50 soal sebelumnya, atau gunakan database baru. Jangan menghapus data lama tanpa verifikasi.
- SQL dapat diimpor ulang untuk menambahkan soal yang belum ada, tetapi tetap lakukan backup dan jangan mengandalkannya untuk mengganti soal yang sudah diubah. Soal dikelola langsung di tabel `questions`.

## Admin

Buka `admin.php`. Username yang diminta sudah tercantum dalam `config.example.php`. Kata sandi admin **tidak disimpan** dalam Git, sehingga akun harus disiapkan di `config.php` pada server. Di komputer/server yang memiliki PHP, buat hash:

```sh
php -r 'echo password_hash(readline("Kata sandi admin: "), PASSWORD_DEFAULT), PHP_EOL;'
```

Salin hasilnya ke `admin_password_hash` di `config.php`. Jangan menulis kata sandi biasa ke Git. Karena kata sandi sebelumnya dibagikan dalam percakapan, gunakan kata sandi baru yang kuat sebelum aplikasi dibuka. Alternatif konfigurasi menggunakan `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `ADMIN_USERNAME`, dan `ADMIN_PASSWORD_HASH` sebagai environment variable.

Panitia dapat melihat peserta, nilai dan jawaban, memfinalisasi draf peserta yang waktunya habis, atau menghapus pendaftaran yang belum selesai untuk pendaftaran ulang. Akun dikunci 15 menit setelah lima kesalahan login; sesi admin habis setelah 30 menit tidak aktif.

Daftar peserta dapat disaring berdasarkan **posisi**, **status** (selesai, dalam proses, waktu habis), **rentang tanggal pendaftaran**, dan **kata kunci** nama/email/NIK, serta diurutkan menurut waktu daftar, nilai, atau nama. Filter tersimpan di URL sehingga tautan dapat dibagikan atau disimpan.

Tombol **Export Excel** mengunduh satu berkas `.xlsx` berisi dua lembar: `Peserta` (seluruh baris yang sesuai filter, bukan hanya halaman yang tampil) dan `Rekap Posisi` (jumlah peserta, jumlah selesai/dalam proses/waktu habis, rata-rata, nilai tertinggi, dan terendah per posisi). Pada halaman detail peserta tersedia **Export jawaban** untuk mengunduh lembar jawaban peserta tersebut. Berkas dibuat langsung oleh `excel.php` tanpa pustaka tambahan dan tanpa memerlukan ekstensi `zip`, jadi pastikan file itu ikut terunggah. Ekspor peserta dibatasi 10.000 baris pertama; persempit filter bila peserta lebih banyak. Nilai yang diekspor hanya berasal dari hasil tes yang benar-benar dikirim atau difinalisasi panitia.

## Soal dan cara kerja

- Posisi aktif: keperawatan, administrasi, kebidanan, laboratorium/ATLM, rekam medis, dan radiografer/radiologi. Setiap paket terdiri dari 20 TWK dan 30 SKD. IPSRS belum aktif.
- Soal SKD keperawatan berasal dari `SOAL KEPERAWATAN.docx`; 20 TWK keperawatan masih contoh. Administrasi menggunakan 30 soal pertama dari `SOAL ADMINISTRASI.docx`. SKD kebidanan dan laboratorium menggunakan dokumen bidang masing-masing, sedangkan TWK ketiganya berasal dari `SOAL KEBIDANAN 2.docx`. Radiografer menggunakan 20 TWK yang sama dengan laboratorium dan rekam medis serta 30 SKD dari `SOAL UKOM RADIOGRAFER.docx` yang beberapa butirnya diperbaiki. Gambar soal SKD nomor 10 disalin dari dokumen dan harus diunggah sebagai `soal-radiografer-siku.jpg`. Dokumen Word disimpan sebagai sumber, bukan file untuk diupload ke web.
- **Validasi mentor wajib dilakukan sebelum seleksi resmi.** Kunci radiografer **tidak tercantum di dokumen sumber**: kunci disusun sementara menggunakan referensi dan inferensi, bukan kunci resmi. Lihat `REFERENSI_RADIOGRAFER.md` untuk perubahan, referensi per nomor dan butir yang sangat perlu dicek; jangan gunakan untuk seleksi resmi sebelum persetujuan mentor. Kunci paket lain dipertahankan dari sumber dan belum diverifikasi secara klinis/hukum; beberapa butir tampak bermasalah atau usang, termasuk TWK sumber nomor 7, laboratorium nomor 22, serta keperawatan nomor 13, 21, dan 26. Ubah `twk_is_example` menjadi `false` di `config.php` setelah TWK keperawatan diganti dan divalidasi.
- Gambar soal terhubung lewat tabel `question_images` berdasarkan ID soal. Bila panitia mengubah soal bergambar, sesuaikan atau hapus asosiasi gambar di tabel tersebut; template hanya mengizinkan nama gambar bawaan agar tidak memuat URL sembarangan. Impor ulang akan memasang kembali asosiasi bawaan bila teks soal bawaan masih persis sama dan belum ada asosiasi.
- Waktu tes 60 menit dihitung di server sejak biodata dikirim, termasuk saat halaman ditutup atau dimuat ulang. Jawaban disimpan bertahap saat JavaScript aktif dan koneksi tersedia. Setelah waktu habis, perubahan tidak lagi diterima dan soal yang belum dijawab bernilai salah. Jika tab tertutup saat waktu habis, hasil difinalisasi ketika peserta kembali atau admin memilih **Finalisasi hasil**. Perubahan yang belum berhasil tersimpan tidak dihitung.
- NIK hanya dapat digunakan satu kali. Nilai akhir tampil setelah pengiriman dengan rumus jumlah benar / 50 × 100. Pendaftaran yang belum selesai dapat dihapus panitia jika sesi hilang; draf ikut terhapus.
- Mode uji coba: pembatasan salin, klik kanan, dan seleksi teks pada soal **telah dinonaktifkan** di web (`app.js`, `style.css`). Logika soal, timer, dan penyimpanan jawaban tetap berjalan. Untuk mengaktifkan kembali, pulihkan blok event listener di `app.js` dan aturan `user-select:none` di `style.css`.
