<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/exam.php';
require __DIR__ . '/excel.php';
date_default_timezone_set('Asia/Jakarta'); // Hanya memengaruhi jam ekspor dan tahun di footer.

/** Daftar posisi yang tersedia. */
function admin_positions(): array {
    return ['perawat' => 'Keperawatan', 'administrasi' => 'Administrasi', 'kebidanan' => 'Kebidanan', 'laboratorium' => 'Laboratorium / ATLM', 'rekam_medis' => 'Rekam Medis', 'radiografer' => 'Radiografer / Radiologi'];
}

/** Urutan pilihan pengurutan tabel. */
function admin_sorts(): array {
    return ['baru' => 'Terbaru', 'lama' => 'Terlama', 'nilai_desc' => 'Nilai tertinggi', 'nilai_asc' => 'Nilai terendah', 'nama' => 'Nama A–Z'];
}

/** Label status peserta. */
function admin_statuses(): array {
    return ['selesai' => 'Selesai', 'proses' => 'Dalam proses', 'habis' => 'Waktu habis'];
}

/** Baca filter dari URL dan buang nilai yang tidak dikenal. */
function admin_filters(): array {
    $filters = ['q' => '', 'posisi' => '', 'status' => '', 'dari' => '', 'sampai' => '', 'sort' => 'baru'];
    if (is_string($_GET['q'] ?? null)) {
        $keyword = trim($_GET['q']);
        $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120, 'UTF-8') : substr($keyword, 0, 120);
        if (preg_match('//u', $keyword)) $filters['q'] = $keyword;
    }
    $posisi = is_string($_GET['posisi'] ?? null) ? $_GET['posisi'] : '';
    if (array_key_exists($posisi, admin_positions())) $filters['posisi'] = $posisi;
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    if (array_key_exists($status, admin_statuses())) $filters['status'] = $status;
    foreach (['dari', 'sampai'] as $key) {
        $value = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        $date = $value !== '' ? DateTime::createFromFormat('!Y-m-d', $value) : false;
        if ($date && $date->format('Y-m-d') === $value) $filters[$key] = $value;
    }
    $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
    if (array_key_exists($sort, admin_sorts())) $filters['sort'] = $sort;
    return $filters;
}

/** Ubah filter menjadi klausa WHERE dan parameter query. */
function admin_where(array $filters): array {
    $clauses = [];
    $params = [];
    if ($filters['q'] !== '') {
        $like = '%' . $filters['q'] . '%';
        $clauses[] = '(p.name LIKE ? OR p.email LIKE ? OR p.nik LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if ($filters['posisi'] !== '') { $clauses[] = 'p.position = ?'; $params[] = $filters['posisi']; }
    $expired = 'CURRENT_TIMESTAMP >= DATE_ADD(p.started_at, INTERVAL 60 MINUTE)';
    if ($filters['status'] === 'selesai') {
        $clauses[] = 's.id IS NOT NULL';
    } elseif ($filters['status'] === 'proses') {
        $clauses[] = 's.id IS NULL AND NOT (' . $expired . ')';
    } elseif ($filters['status'] === 'habis') {
        $clauses[] = 's.id IS NULL AND (' . $expired . ')';
    }
    if ($filters['dari'] !== '') { $clauses[] = 'p.started_at >= ?'; $params[] = $filters['dari'] . ' 00:00:00'; }
    if ($filters['sampai'] !== '') { $clauses[] = 'p.started_at <= ?'; $params[] = $filters['sampai'] . ' 23:59:59'; }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/** Klausa ORDER BY yang diizinkan. */
function admin_order(array $filters): string {
    switch ($filters['sort']) {
        case 'lama': return 'p.started_at ASC, p.id ASC';
        case 'nilai_desc': return 's.score IS NULL, s.score DESC, p.started_at DESC';
        case 'nilai_asc': return 's.score IS NULL, s.score ASC, p.started_at DESC';
        case 'nama': return 'p.name ASC, p.id ASC';
        default: return 'p.started_at DESC, p.id DESC';
    }
}

/** Query string untuk tautan halaman berikutnya / unduhan. */
function admin_query(array $filters, array $extra = []): string {
    $query = $filters;
    foreach ($query as $key => $value) if ($value === '') unset($query[$key]);
    if (($query['sort'] ?? '') === 'baru') unset($query['sort']);
    return http_build_query(array_merge($query, $extra));
}

/** Ringkasan filter aktif untuk ditampilkan di halaman dan di berkas Excel. */
function admin_filter_summary(array $filters): array {
    $labels = admin_positions();
    $statuses = admin_statuses();
    $sorts = admin_sorts();
    $parts = [];
    if ($filters['q'] !== '') $parts[] = 'Pencarian "' . $filters['q'] . '"';
    if ($filters['posisi'] !== '') $parts[] = 'Posisi ' . $labels[$filters['posisi']];
    if ($filters['status'] !== '') $parts[] = 'Status ' . $statuses[$filters['status']];
    if ($filters['dari'] !== '' && $filters['sampai'] !== '') $parts[] = 'Daftar ' . admin_date($filters['dari']) . ' s.d. ' . admin_date($filters['sampai']);
    elseif ($filters['dari'] !== '') $parts[] = 'Daftar sejak ' . admin_date($filters['dari']);
    elseif ($filters['sampai'] !== '') $parts[] = 'Daftar sampai ' . admin_date($filters['sampai']);
    if (($filters['sort'] ?? 'baru') !== 'baru') $parts[] = 'Urutan ' . $sorts[$filters['sort']];
    return $parts;
}

function admin_date(?string $value): string {
    if (!$value) return '—';
    $timestamp = strtotime($value);
    return $timestamp === false ? (string)$value : date('d-m-Y', $timestamp);
}

function admin_datetime(?string $value): string {
    if (!$value) return '—';
    $timestamp = strtotime($value);
    return $timestamp === false ? (string)$value : date('d-m-Y H:i', $timestamp);
}

/** Label status satu baris data peserta. */
function admin_status_label(array $row): string {
    if (!empty($row['submission_id'])) return 'Selesai';
    return !empty($row['time_expired']) ? 'Waktu habis' : 'Dalam proses';
}

/** Unduh daftar peserta sesuai filter sebagai berkas Excel. */
function admin_export_peserta(PDO $pdo, array $filters): void {
    [$where, $params] = admin_where($filters);
    $stmt = $pdo->prepare('SELECT p.id, p.name, p.email, p.nik, p.position, p.started_at, s.id AS submission_id, s.correct_count, s.total_questions, s.score, s.submitted_at, (CURRENT_TIMESTAMP >= DATE_ADD(p.started_at, INTERVAL 60 MINUTE)) AS time_expired FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id' . $where . ' ORDER BY ' . admin_order($filters) . ' LIMIT 10000');
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = admin_positions();

    $header = ['NO', 'NAMA', 'EMAIL', 'NIK', 'POSISI', 'STATUS', 'NILAI', 'JAWABAN BENAR', 'TOTAL SOAL', 'WAKTU DAFTAR', 'WAKTU KIRIM'];
    $table = [array_map(static fn(string $cell): array => xlsx_text($cell, 1), $header)];
    foreach ($rows as $index => $row) {
        $table[] = [
            xlsx_number($index + 1),
            xlsx_text($row['name']),
            xlsx_text($row['email']),
            xlsx_text($row['nik']),
            xlsx_text($labels[$row['position']] ?? $row['position']),
            xlsx_text(admin_status_label($row)),
            $row['submission_id'] ? xlsx_number($row['score'], 2) : xlsx_text('—'),
            $row['submission_id'] ? xlsx_number($row['correct_count']) : xlsx_text('—'),
            $row['submission_id'] ? xlsx_number($row['total_questions']) : xlsx_text('—'),
            xlsx_text(admin_datetime($row['started_at'])),
            xlsx_text(admin_datetime($row['submitted_at'] ?? null)),
        ];
    }

    $rekap = [];
    foreach ($labels as $key => $label) $rekap[$key] = ['label' => $label, 'total' => 0, 'selesai' => 0, 'proses' => 0, 'habis' => 0, 'nilai' => [], 'jumlah_nilai' => 0.0];
    foreach ($rows as $row) {
        $key = $row['position'];
        if (!isset($rekap[$key])) continue;
        $rekap[$key]['total']++;
        if (!empty($row['submission_id'])) {
            $rekap[$key]['selesai']++;
            $rekap[$key]['nilai'][] = (float)$row['score'];
            $rekap[$key]['jumlah_nilai'] += (float)$row['score'];
        } elseif (!empty($row['time_expired'])) {
            $rekap[$key]['habis']++;
        } else {
            $rekap[$key]['proses']++;
        }
    }

    $summary = 'Diekspor ' . date('d-m-Y H:i') . ' WIB · ' . count($rows) . ' peserta' . (count($rows) >= 10000 ? ' (dibatasi 10.000 baris pertama)' : '');
    $active = implode(' · ', admin_filter_summary($filters));
    $rekapSheet = [
        [xlsx_text('REKAP SELEKSI MAGANG RSUD MALANGBONG', 3)],
        [xlsx_text($summary, 4)],
        [xlsx_text($active !== '' ? 'Filter: ' . $active : 'Filter: seluruh peserta', 4)],
        [],
        array_map(static fn(string $cell): array => xlsx_text($cell, 1), ['POSISI', 'PESERTA', 'SELESAI', 'DALAM PROSES', 'WAKTU HABIS', 'RATA-RATA NILAI', 'NILAI TERTINGGI', 'NILAI TERENDAH']),
    ];
    $totals = [0, 0, 0, 0, 0.0, 0];
    foreach ($rekap as $item) {
        $count = count($item['nilai']);
        $average = $count ? $item['jumlah_nilai'] / $count : null;
        $rekapSheet[] = [
            xlsx_text($item['label']),
            xlsx_number($item['total']),
            xlsx_number($item['selesai']),
            xlsx_number($item['proses']),
            xlsx_number($item['habis']),
            $average === null ? xlsx_text('—') : xlsx_number($average, 2),
            $count ? xlsx_number(max($item['nilai']), 2) : xlsx_text('—'),
            $count ? xlsx_number(min($item['nilai']), 2) : xlsx_text('—'),
        ];
        $totals[0] += $item['total'];
        $totals[1] += $item['selesai'];
        $totals[2] += $item['proses'];
        $totals[3] += $item['habis'];
        $totals[4] += $item['jumlah_nilai'];
        $totals[5] += count($item['nilai']);
    }
    $rekapSheet[] = [];
    $rekapSheet[] = [
        xlsx_text('TOTAL', 5),
        xlsx_number($totals[0]),
        xlsx_number($totals[1]),
        xlsx_number($totals[2]),
        xlsx_number($totals[3]),
        $totals[5] ? xlsx_number($totals[4] / $totals[5], 2) : xlsx_text('—'),
        '',
        '',
    ];
    $rekapSheet[] = [];
    $rekapSheet[] = [xlsx_text('Nilai akhir = jumlah jawaban benar / 50 × 100. Tanda — berarti belum ada peserta yang mengirim jawaban.', 4)];

    xlsx_download('rekap-peserta-' . date('Ymd-Hi') . '.xlsx', [
        ['name' => 'Peserta', 'widths' => [6, 30, 34, 20, 24, 15, 10, 15, 12, 18, 18], 'rows' => $table, 'freeze' => 1, 'autofilter' => true],
        ['name' => 'Rekap Posisi', 'widths' => [26, 12, 12, 16, 15, 17, 18, 18], 'rows' => $rekapSheet],
    ]);
}

/** Unduh jawaban satu peserta sebagai berkas Excel. */
function admin_export_jawaban(PDO $pdo, int $id): void {
    $stmt = $pdo->prepare('SELECT p.*, s.id AS submission_id, s.score, s.correct_count, s.total_questions, s.submitted_at FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id WHERE p.id = ?');
    $stmt->execute([$id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$participant) { http_response_code(404); exit('Peserta tidak ditemukan.'); }
    $labels = admin_positions();
    $answers = [];
    if ($participant['submission_id']) {
        $stmt = $pdo->prepare('SELECT question_text, selected_option, selected_text, is_correct FROM answers WHERE submission_id = ? ORDER BY id');
        $stmt->execute([$participant['submission_id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sheet = [
        [xlsx_text('LEMBAR JAWABAN PESERTA', 3)],
        [xlsx_text('Nama: ' . $participant['name'], 5)],
        [xlsx_text('Email: ' . $participant['email'] . ' · NIK: ' . $participant['nik'], 4)],
        [xlsx_text('Posisi: ' . ($labels[$participant['position']] ?? $participant['position']), 4)],
        [xlsx_text('Waktu daftar: ' . admin_datetime($participant['started_at']) . ' · Waktu kirim: ' . admin_datetime($participant['submitted_at'] ?? null), 4)],
        [xlsx_text('Nilai: ' . ($participant['submission_id'] ? number_format((float)$participant['score'], 2, ',', '.') . ' (' . $participant['correct_count'] . '/' . $participant['total_questions'] . ' benar)' : 'belum mengirim jawaban'), 4)],
        [],
    ];
    if ($answers) {
        $sheet[] = array_map(static fn(string $cell): array => xlsx_text($cell, 1), ['NO', 'SOAL', 'JAWABAN PESERTA', 'STATUS']);
        foreach ($answers as $index => $answer) {
            $status = $answer['selected_option'] === null ? 'Tidak dijawab' : (!empty($answer['is_correct']) ? 'Benar' : 'Belum tepat');
            $selected = $answer['selected_option'] === null ? '—' : $answer['selected_option'] . '. ' . $answer['selected_text'];
            $sheet[] = [xlsx_number($index + 1), xlsx_text($answer['question_text']), xlsx_text($selected), xlsx_text($status)];
        }
    } else {
        $sheet[] = [xlsx_text('Peserta belum mengirimkan jawaban.', 4)];
    }

    $safeName = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $participant['name']), '-');
    if ($safeName === '') $safeName = 'peserta';
    xlsx_download('jawaban-' . $safeName . '-' . date('Ymd-Hi') . '.xlsx', [
        ['name' => 'Jawaban', 'widths' => [6, 80, 46, 16], 'rows' => $sheet, 'freeze' => 8],
    ]);
}

$error = ''; $notice = '';
$adminUser = getenv('ADMIN_USERNAME') ?: (config()['admin_username'] ?? 'rsudmalangbonggarut@gmail.com');
$adminHash = getenv('ADMIN_PASSWORD_HASH') ?: (config()['admin_password_hash'] ?? '');
$labels = admin_positions();
if (!empty($_SESSION['admin_last']) && time() - $_SESSION['admin_last'] > 1800) unset($_SESSION['admin'], $_SESSION['admin_last']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!$adminHash || !password_get_info($adminHash)['algo']) {
            $error = 'Akun admin belum dikonfigurasi. Hubungi pengelola aplikasi.';
        } else {
            try {
                $pdo = db(); $pdo->beginTransaction();
                $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $pdo->prepare('INSERT IGNORE INTO admin_login_attempts (ip_hash) VALUES (?)')->execute([$ip]);
                $stmt = $pdo->prepare('SELECT attempts, (locked_until > UTC_TIMESTAMP()) AS locked FROM admin_login_attempts WHERE ip_hash = ? FOR UPDATE');
                $stmt->execute([$ip]); $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($attempt['locked']) {
                    $pdo->commit(); $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
                } elseif (hash_equals((string)$adminUser, $username) && password_verify($password, $adminHash)) {
                    $pdo->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ?')->execute([$ip]);
                    $pdo->commit(); session_regenerate_id(true); $_SESSION['admin'] = true; $_SESSION['admin_last'] = time(); redirect('admin.php');
                } else {
                    $attempts = (int)$attempt['attempts'];
                    if ($attempts >= 5) $attempts = 0; // Lock expired: start a new window.
                    $attempts++;
                    $stmt = $pdo->prepare('UPDATE admin_login_attempts SET attempts = ?, locked_until = IF(? >= 5, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), NULL) WHERE ip_hash = ?');
                    $stmt->execute([$attempts, $attempts, $ip]); $pdo->commit();
                    $error = 'Nama pengguna atau kata sandi salah.';
                }
            } catch (Throwable $ex) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $error = 'Koneksi database bermasalah. Periksa konfigurasi dan migrasi database.';
            }
        }
    } elseif ($action === 'logout' && !empty($_SESSION['admin'])) {
        unset($_SESSION['admin'], $_SESSION['admin_last']); session_regenerate_id(true); redirect('admin.php');
    } elseif ($action === 'finalize' && !empty($_SESSION['admin'])) {
        try {
            $id = filter_var($_POST['participant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $stmt = db()->prepare('SELECT id, name, email, nik, position FROM participants WHERE id = ?');
            $stmt->execute([$id ?: 0]); $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$candidate) $error = 'Pendaftaran tidak ditemukan.';
            else {
                $list = questions($candidate['position']);
                if (!exam_is_ready($list)) $error = 'Paket soal belum lengkap. Hasil tidak dapat difinalisasi.';
                else {
                    $result = finish_test($candidate, $list, [], true, bin2hex(random_bytes(32)), false);
                    $notice = $result ? 'Jawaban yang tersimpan telah difinalisasi.' : 'Waktu tes peserta belum habis.';
                }
            }
        } catch (Throwable $ex) { $error = 'Hasil tidak dapat difinalisasi. Periksa database dan coba lagi.'; }
    } elseif ($action === 'reset' && !empty($_SESSION['admin'])) {
        // Only a registration without a submitted test can be removed.
        try {
            $id = filter_var($_POST['participant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id) {
                $stmt = db()->prepare('DELETE p FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id WHERE p.id = ? AND s.id IS NULL');
                $stmt->execute([$id]);
                $notice = $stmt->rowCount() ? 'Pendaftaran yang belum selesai telah dihapus. Peserta dapat mendaftar kembali.' : 'Pendaftaran tidak ditemukan atau tes sudah dikirim.';
            }
        } catch (Throwable $ex) { $error = 'Pendaftaran tidak dapat dihapus. Silakan coba lagi.'; }
    }
}
$loggedIn = !empty($_SESSION['admin']);
$filters = admin_filters();

// Ekspor Excel memakai filter yang sama dengan tabel, sehingga hasil unduhan sesuai tampilan.
if ($loggedIn && isset($_GET['export'])) {
    try {
        $pdo = db();
        $type = is_string($_GET['export']) ? $_GET['export'] : 'peserta';
        if ($type === 'jawaban') {
            $exportId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$exportId) $error = 'Peserta yang akan diekspor tidak ditemukan.';
            else admin_export_jawaban($pdo, (int)$exportId);
        } else {
            admin_export_peserta($pdo, $filters);
        }
    } catch (Throwable $ex) {
        $error = 'Berkas Excel tidak dapat dibuat. Periksa koneksi database lalu coba lagi.';
    }
}

$participants = []; $detail = null; $answers = []; $stats = ['total' => 0, 'completed' => 0, 'pending' => 0];
$totalFiltered = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = 1;
if ($loggedIn) {
    $_SESSION['admin_last'] = time();
    try {
        $pdo = db();
        $stats = $pdo->query('SELECT COUNT(*) AS total, COUNT(s.id) AS completed, COUNT(*) - COUNT(s.id) AS pending FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id')->fetch(PDO::FETCH_ASSOC);
        [$where, $params] = admin_where($filters);
        $count = $pdo->prepare('SELECT COUNT(*) FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id' . $where);
        $count->execute($params);
        $totalFiltered = (int)$count->fetchColumn();
        $totalPages = max(1, (int)ceil($totalFiltered / 25)); $page = min($page, $totalPages); $offset = ($page - 1) * 25;
        $stmt = $pdo->prepare('SELECT p.*, s.id AS submission_id, s.score, s.correct_count, s.total_questions, s.submitted_at, (CURRENT_TIMESTAMP >= DATE_ADD(p.started_at, INTERVAL 60 MINUTE)) AS time_expired FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id' . $where . ' ORDER BY ' . admin_order($filters) . ' LIMIT 25 OFFSET ' . $offset);
        $stmt->execute($params); $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $detailId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($detailId) {
            $stmt = $pdo->prepare('SELECT p.*, s.id AS submission_id, s.score, s.correct_count, s.total_questions, s.submitted_at, (CURRENT_TIMESTAMP >= DATE_ADD(p.started_at, INTERVAL 60 MINUTE)) AS time_expired FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id WHERE p.id = ?');
            $stmt->execute([$detailId]); $detail = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($detail && $detail['submission_id']) {
                $stmt = $pdo->prepare('SELECT question_text, selected_option, selected_text, is_correct FROM answers WHERE submission_id = ? ORDER BY id');
                $stmt->execute([$detail['submission_id']]); $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $ex) { $error = 'Data tidak dapat dimuat. Periksa konfigurasi dan migrasi database.'; }
}
$filterSummary = admin_filter_summary($filters);
$filterActive = $filterSummary !== [];
$listUrl = 'admin.php' . ($filterActive ? '?' . admin_query($filters) : '');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10355b"><title>Admin | Seleksi Magang RSUD Malangbong</title><link rel="stylesheet" href="style.css"></head>
<body class="admin-page"><header class="topbar"><div class="topbar-inner"><a class="institution-brand" href="index.php" aria-label="Tes Seleksi Magang RSUD Malangbong dan Kemnaker, halaman utama"><span class="institution-logos"><img src="logo-rsud-malangbong.png" alt="Logo RSUD Malangbong"><span class="logo-separator" aria-hidden="true"></span><img src="logo-kemnaker.png" alt="Logo Kemnaker"></span><span class="institution-divider" aria-hidden="true"></span><span class="institution-name"><strong>RSUD Malangbong</strong><small>SELEKSI MAGANG · KEMNAKER</small></span></a><div class="top-right"><?php if ($loggedIn): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="header-button">Keluar ↗</button></form><?php else: ?><a href="index.php" class="admin-link">Kembali ke tes ↗</a><?php endif; ?></div></div></header>
<main class="page-shell">
<?php if (!$loggedIn): ?>
<section class="admin-login form-card reveal"><div class="section-kicker"><span></span> AKSES PANITIA</div><h1>Selamat datang<br><em>kembali.</em></h1><p>Masuk untuk melihat peserta dan hasil tes seleksi magang RSUD Malangbong.</p><?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?><form method="post" class="biodata-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login"><label for="username">Nama pengguna</label><input id="username" name="username" autocomplete="username" required><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="current-password" required><button class="primary-button">Masuk ke dashboard <span>↗</span></button></form></section>
<?php else: ?>
<section class="admin-wrap reveal"><div class="admin-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> PANEL PANITIA</div><h1>Dashboard <em>seleksi.</em></h1><p>Pantau peserta dan hasil tes dalam satu tempat.</p></div><span class="admin-badge">● &nbsp; AREA ADMIN</span></div>
<?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($detail): ?><a href="<?= e($listUrl) ?>" class="back-link">← Kembali ke daftar peserta</a><div class="admin-detail"><div class="admin-detail-head"><div><span class="aside-kicker">DETAIL PESERTA</span><h2><?= e($detail['name']) ?></h2><p><?= e($detail['email']) ?> · <?= e($detail['nik']) ?> · <?= e($labels[$detail['position']] ?? $detail['position']) ?></p></div><div class="detail-tools"><?php if ($detail['submission_id']): ?><div class="detail-score"><strong><?= e(number_format((float)$detail['score'], 2, ',', '.')) ?></strong><span><?= e($detail['correct_count']) ?> / <?= e($detail['total_questions']) ?> benar</span></div><?php else: ?><span class="status status-pending"><?= $detail['time_expired'] ? 'Waktu habis · belum dikirim' : 'Sedang mengerjakan' ?></span><?php endif; ?><a class="export-button" href="admin.php?<?= e(admin_query($filters, ['export' => 'jawaban', 'id' => $detail['id']])) ?>">⤓ Export jawaban</a></div></div>
<?php if ($answers): ?><div class="review-list"><?php foreach ($answers as $i => $answer): ?><article class="review-card"><div class="review-top"><span>SOAL <?= str_pad((string)($i+1), 2, '0', STR_PAD_LEFT) ?></span><strong class="<?= $answer['is_correct'] ? 'correct' : 'incorrect' ?>"><?= $answer['selected_option'] === null ? 'Tidak dijawab' : ($answer['is_correct'] ? '✓ Benar' : '× Belum tepat') ?></strong></div><h3><?= e($answer['question_text']) ?></h3><div class="review-answer"><small>JAWABAN PESERTA</small><p><?= $answer['selected_option'] === null ? 'Tidak dijawab' : e($answer['selected_option']) . '. ' . e($answer['selected_text']) ?></p></div></article><?php endforeach; ?></div><?php else: ?><p class="empty-state">Peserta belum mengirimkan jawaban.</p><?php endif; ?></div>
<?php else: ?>
<div class="stats-grid"><div class="stat-card"><span>SELURUH PESERTA</span><strong><?= e($stats['total']) ?></strong><small>Peserta terdaftar</small></div><div class="stat-card"><span>TES SELESAI</span><strong><?= e($stats['completed']) ?></strong><small>Jawaban terkirim</small></div><div class="stat-card"><span>DALAM PROSES</span><strong><?= e($stats['pending']) ?></strong><small>Belum mengirim jawaban</small></div><div class="stat-card stat-card-filter"><span>SESUAI FILTER</span><strong><?= e($totalFiltered) ?></strong><small><?= $filters['posisi'] !== '' ? e($labels[$filters['posisi']]) : 'Seluruh posisi' ?></small></div></div>
<div class="participants-card"><div class="participants-head"><div><div class="section-kicker"><span></span> DATA SELEKSI</div><h2>Daftar peserta</h2><p>Peserta yang telah memulai tes akan tampil di sini.</p></div><div class="participants-tools"><span class="filter-count"><?= e(count($participants)) ?> dari <?= e($totalFiltered) ?> peserta</span><a class="export-button" href="admin.php?<?= e(admin_query($filters, ['export' => 'peserta'])) ?>" title="Unduh daftar peserta sesuai filter sebagai berkas Excel">⤓ Export Excel</a></div></div>
<form class="filter-form" method="get" action="admin.php"><div class="filter-field filter-search"><label for="search">Cari peserta</label><input id="search" name="q" type="search" placeholder="Nama, email, atau NIK" value="<?= e($filters['q']) ?>"></div><div class="filter-field"><label for="filter-posisi">Posisi</label><select id="filter-posisi" name="posisi"><option value="">Semua posisi</option><?php foreach ($labels as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['posisi'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="filter-field"><label for="filter-status">Status</label><select id="filter-status" name="status"><option value="">Semua status</option><?php foreach (admin_statuses() as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="filter-field"><label for="filter-dari">Daftar dari</label><input id="filter-dari" name="dari" type="date" value="<?= e($filters['dari']) ?>"></div><div class="filter-field"><label for="filter-sampai">Daftar sampai</label><input id="filter-sampai" name="sampai" type="date" value="<?= e($filters['sampai']) ?>"></div><div class="filter-field"><label for="filter-sort">Urutkan</label><select id="filter-sort" name="sort"><?php foreach (admin_sorts() as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="filter-actions"><button class="filter-submit" type="submit">Terapkan filter</button><?php if ($filterActive): ?><a class="filter-reset" href="admin.php">Reset</a><?php endif; ?></div></form>
<p class="filter-summary"><?php if ($filterActive): ?>Filter aktif: <?= e(implode(' · ', $filterSummary)) ?> · <?= e($totalFiltered) ?> peserta cocok.<?php else: ?>Menampilkan seluruh peserta tanpa filter.<?php endif; ?></p>
<div class="table-scroll"><table><thead><tr><th>PESERTA</th><th>NIK</th><th>POSISI</th><th>STATUS</th><th>NILAI</th><th>WAKTU DAFTAR</th><th>AKSI</th></tr></thead><tbody><?php foreach ($participants as $p): ?><tr><td><strong><?= e($p['name']) ?></strong><small><?= e($p['email']) ?></small></td><td><?= e($p['nik']) ?></td><td><span class="position-tag"><?= e($labels[$p['position']] ?? $p['position']) ?></span></td><td><span class="status <?= $p['submission_id'] ? 'status-done' : 'status-pending' ?>"><?= e(admin_status_label($p)) ?></span></td><td><?= $p['submission_id'] ? e(number_format((float)$p['score'], 2, ',', '.')) : '—' ?></td><td><?= e(admin_datetime($p['started_at'])) ?></td><td><a class="table-link" href="admin.php?<?= e(admin_query($filters, ['id' => $p['id']])) ?>">Detail ↗</a><?php if (!$p['submission_id'] && $p['time_expired']): ?><form method="post" onsubmit="return confirm('Finalisasi jawaban yang tersimpan? Soal yang belum dijawab akan dihitung salah.')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="finalize"><input type="hidden" name="participant_id" value="<?= e($p['id']) ?>"><button class="table-link finalize-button">Finalisasi hasil</button></form><?php endif; ?><?php if (!$p['submission_id']): ?><form method="post" onsubmit="return confirm('Hapus pendaftaran yang belum selesai agar peserta dapat mendaftar ulang?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="participant_id" value="<?= e($p['id']) ?>"><button class="reset-button">Hapus pendaftaran</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php if (!$participants): ?><p class="empty-state"><?= $filterActive ? 'Tidak ada peserta yang cocok dengan filter. Coba ubah atau reset filter.' : 'Belum ada peserta yang ditemukan.' ?></p><?php endif; ?></div>
<div class="pagination"><span>Halaman <?= $page ?> dari <?= $totalPages ?> · <?= e($totalFiltered) ?> peserta</span><div><?php if ($page > 1): ?><a href="?<?= e(admin_query($filters, ['page' => $page-1])) ?>">← Sebelumnya</a><?php endif; ?> <?php if ($page < $totalPages): ?><a href="?<?= e(admin_query($filters, ['page' => $page+1])) ?>">Selanjutnya →</a><?php endif; ?></div></div></div>
<?php endif; ?></section><?php endif; ?></main><footer><span>© <?= date('Y') ?> RSUD MALANGBONG <span class="footer-dot">•</span> SELEKSI MAGANG</span><span>PROGRAM MAGANG KEMNAKER <span class="footer-star">✳</span></span></footer></body></html>
