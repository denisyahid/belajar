# Tes Seleksi Magang RSUD Malangbong — Program Kemnaker

Aplikasi tes PHP dan MySQL yang ringan, dengan tampilan responsif, biodata, soal per posisi, hasil peserta, dan dashboard panitia. Aplikasi ini ditujukan untuk seleksi magang di RSUD Malangbong melalui program Kemnaker. Warna biru terinspirasi dari [identitas Kemnaker](https://lapormenaker.kemnaker.go.id/uploads/file/Kemnaker%20No.%2020%20Tahun%202015.pdf). Logo RSUD bersumber dari [situs RSUD Malangbong](https://rsud-malangbong.garutkab.go.id/profil), dan logo Kemnaker dari [layanan informasi Kemnaker](https://layananinformasi.kemnaker.go.id/). Pastikan penggunaan identitas dan publikasi aplikasi sesuai izin dan ketentuan kedua instansi.

## Kebutuhan

- PHP 8.0+ dengan `pdo_mysql` dan session; MySQL 5.7+ atau MariaDB 10.2+ dengan InnoDB dan utf8mb4.
- Hosting dengan HTTPS. InfinityFree umumnya hanya mengizinkan koneksi database dari hostingnya sendiri; koneksi dari komputer lokal bisa tidak tersedia. Ikuti detail koneksi di panel hosting.

## Instalasi database baru

1. Pilih database `if0_43370700_kemnaker` di phpMyAdmin hosting. Impor `database/schema.sql`, lalu `database/seed.sql` dan `database/seed_twk_example.sql` (masing-masing sekali saja). Skema tidak membuat database, sehingga dapat digunakan dengan database hosting yang sudah tersedia. Untuk CLI, contoh: `mysql -u USER -p NAMA_DATABASE < database/schema.sql` lalu `mysql -u USER -p NAMA_DATABASE < database/seed.sql` dan `mysql -u USER -p NAMA_DATABASE < database/seed_twk_example.sql`.
2. Salin `config.example.php` menjadi `config.php` di direktori proyek (di luar `public/`). Host, port, nama database, dan username sudah mengikuti data hosting yang diberikan. Isi `password` dengan kata sandi database secara privat di server. `config.php` diabaikan Git dan tidak boleh dibagikan. Variabel lingkungan `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD` mengesampingkan file konfigurasi.
3. Konfigurasi akun admin seperti di bawah. Arahkan document root server **hanya ke `public/`**; direktori induk berisi konfigurasi, soal dan kunci yang tidak boleh dapat diakses publik. Di lokal untuk pengembangan: `php -S 0.0.0.0:8000 -t public`. Pada shared hosting dengan document root tetap seperti `htdocs/`, salin **isi** `public/` ke `htdocs/` dan letakkan `app.php` serta `config.php` pada direktori induk `htdocs/` yang dapat dibaca PHP tetapi tidak dilayani web. Jangan menyalin `config.php`, `app.php`, `database/`, atau dokumen soal ke direktori yang dilayani web.

## Migrasi dari versi sebelumnya

Backup database. Periksa NIK yang ganda sebelum migrasi:

```sql
SELECT nik, COUNT(*) FROM submissions GROUP BY nik HAVING COUNT(*) > 1;
```

Selesaikan duplikasi secara manual sesuai kebijakan panitia, termasuk jawaban yang terkait; migrasi tidak menghapus data otomatis. Pilih database lama, lalu jalankan `database/migration_from_v1.sql` **sekali**. Migrasi ini membuat pendaftaran untuk hasil yang sudah ada dan menambahkan batas unik NIK. Jangan impor `schema.sql` atau `seed.sql` lagi pada database lama. Lakukan migrasi saat tidak ada peserta yang sedang mengerjakan; sesi tes versi lama tidak dapat dilanjutkan setelah pembaruan.

## Pembaruan paket 50 soal pada database yang sudah ada

Jika sebelumnya sudah memakai versi dengan tabel `participants`, backup database, pastikan 30 soal perawat bernomor urut 1–30 dan tidak ada tes aktif, lalu jalankan `database/migration_50_questions.sql` dan impor `database/seed_twk_example.sql` masing-masing **sekali**. Migrasi mempertahankan ID soal serta jawaban/hasil sebelumnya. File ini terpisah dari `migration_from_v1.sql`; jika database masih versi pertama, lakukan migrasi v1 terlebih dahulu, baru migrasi paket 50 soal. Jangan menjalankan migrasi ini pada database baru yang memakai `schema.sql` terbaru. Sesi tes yang sedang berlangsung tidak dapat dilanjutkan setelah daftar soal berubah.

Untuk mengisi soal final dari mentor, ubah 20 baris dengan `position='perawat'`, `section='TWK'` dan `sort_order` 1–20, serta 30 baris `section='SKD'` dan `sort_order` 21–50 di tabel `questions`. Periksa teks, pilihan A–E, dan `answer_key`. Setelah soal TWK final diimpor dan divalidasi, atur `twk_is_example` menjadi `false` di `config.php` untuk menyembunyikan catatan contoh. Aplikasi hanya membuka keperawatan jika komposisi paket tersebut lengkap dan urut. Jangan mengubah soal ketika tes sedang berlangsung. Untuk membuka posisi lain nanti diperlukan paket soal, perluasan ENUM posisi di tiga tabel, dan perubahan validasi aplikasi.

## Admin

Buka `admin.php`. Admin dapat melihat daftar peserta yang sudah memulai, status, nilai, dan detail jawaban; cari berdasarkan nama, email, atau NIK. Data ditampilkan 25 peserta per halaman. Registrasi yang **belum selesai** bisa dihapus oleh admin supaya peserta dapat mendaftar ulang jika sesi terputus. Hasil yang sudah dikirim tidak dapat dihapus dengan tombol ini.

Nama pengguna admin yang diminta sudah dicantumkan pada `config.example.php`. Kata sandi admin tidak dimasukkan ke Git; atur hash di file privat `config.php` pada server. Jika file konfigurasi lokal tidak ikut saat deploy, akun harus disiapkan kembali di server. Di komputer/server yang memiliki PHP, buat hash menggunakan:

```sh
php -r 'echo password_hash(readline("Kata sandi admin: "), PASSWORD_DEFAULT), PHP_EOL;'
```

Simpan hasilnya sebagai `admin_password_hash` pada `config.php`, dan atur `admin_username`. Alternatif: variabel lingkungan `ADMIN_USERNAME` dan `ADMIN_PASSWORD_HASH`. Karena kata sandi pernah dibagikan dalam percakapan, ganti dengan kata sandi baru yang kuat sebelum aplikasi dibuka untuk umum; jangan menyimpan kata sandi biasa dalam file atau Git. Sesi admin kedaluwarsa setelah 30 menit tidak aktif. Lima percobaan login gagal mengunci alamat IP selama 15 menit; konfigurasi proxy dapat menyebabkan beberapa pengguna berbagi alamat IP.

## Alur dan catatan

- NIK 16 digit dicatat dan dikunci unik saat peserta mulai tes, termasuk saat peserta belum mengirim. Jika sesi hilang, peserta harus menghubungi panitia untuk menghapus pendaftaran yang belum selesai; jawaban yang belum dikirim tidak tersimpan. Pembatasan NIK bukan verifikasi identitas.
- Setelah dikirim, peserta melihat nilai dan daftar jawaban beserta status benar/salah selama sesi browser aktif. Nilai ditampilkan segera setelah pengiriman pada skala 0–100, dihitung dari jumlah benar dibagi jumlah soal dikali 100; misalnya 35 dari 50 benar menghasilkan 70. Nilai dan jawaban juga tersimpan dalam database. Tidak ada klaim kelulusan otomatis.
- **Keperawatan:** 20 soal TWK contoh sementara (bukan buatan mentor) dan 30 soal SKD dan kunci ditranskripsikan dari `soal karyawan baru.docx`. Mentor harus mengganti dan memvalidasi soal TWK sebelum seleksi resmi. Nomor 21 pada dokumen tidak diberi nomor dan label pilihan ketiga nomor 16 tertulis A; keduanya dinormalisasi mengikuti urutan.
- **Posisi lain:** Kebidanan, Rekam Medis, Radiografer, Administrasi, IPSRS, dan ATLM ditampilkan tetapi dinonaktifkan. Soal administrasi lama hanya contoh dan tidak dapat dipilih. PIC dan soal final untuk setiap bidang belum diberikan.
- **Penting:** kunci keperawatan dipertahankan sebagaimana tertulis, belum diverifikasi secara klinis. Beberapa butir dan kunci tampak perlu ditinjau, khususnya nomor 13, 21, dan 26. Minta tenaga kompeten memvalidasi seluruh soal dan kunci sebelum seleksi; hasil mengikuti kunci tersebut. Bobot 30% untuk nilai akhir tidak dihitung karena tahapan lainnya tidak tersedia.
- Soal dikelola langsung dalam tabel `questions`; jangan mengubahnya saat peserta mengerjakan tes. Aplikasi menampilkan status benar/salah kepada peserta setelah tes, sehingga bank soal tidak lagi rahasia setelah dikirim.
- Gunakan HTTPS, batasi akses database dan admin, lakukan backup, dan tetapkan kebijakan retensi data pribadi. Aplikasi belum menyediakan pemulihan sesi, verifikasi identitas, atau pengawasan ujian.
