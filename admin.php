<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/exam.php';
$error = ''; $notice = '';
$adminUser = getenv('ADMIN_USERNAME') ?: (config()['admin_username'] ?? 'rsudmalangbonggarut@gmail.com');
$adminHash = getenv('ADMIN_PASSWORD_HASH') ?: (config()['admin_password_hash'] ?? '');
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
$participants = []; $detail = null; $answers = []; $stats = ['total' => 0, 'completed' => 0];
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = 1;
if ($loggedIn) {
    $_SESSION['admin_last'] = time();
    try {
        $pdo = db();
        $stats = $pdo->query('SELECT COUNT(*) AS total, COUNT(s.id) AS completed FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id')->fetch(PDO::FETCH_ASSOC);
        $where = ''; $params = [];
        if ($search !== '') { $where = ' WHERE p.name LIKE ? OR p.nik LIKE ? OR p.email LIKE ?'; $like = '%' . $search . '%'; $params = [$like, $like, $like]; }
        $count = $pdo->prepare('SELECT COUNT(*) FROM participants p' . $where); $count->execute($params);
        $totalPages = max(1, (int)ceil((int)$count->fetchColumn() / 25)); $page = min($page, $totalPages); $offset = ($page - 1) * 25;
        $stmt = $pdo->prepare('SELECT p.*, s.id AS submission_id, s.score, s.correct_count, s.total_questions, s.submitted_at, (CURRENT_TIMESTAMP >= DATE_ADD(p.started_at, INTERVAL 60 MINUTE)) AS time_expired FROM participants p LEFT JOIN submissions s ON s.participant_id = p.id' . $where . ' ORDER BY p.started_at DESC, p.id DESC LIMIT 25 OFFSET ' . $offset);
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
$labels = ['perawat' => 'Keperawatan', 'administrasi' => 'Administrasi', 'kebidanan' => 'Kebidanan', 'laboratorium' => 'Laboratorium / ATLM'];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10355b"><title>Admin | Seleksi Magang RSUD Malangbong</title><link rel="stylesheet" href="style.css"></head>
<body class="admin-page"><header class="topbar"><div class="topbar-inner"><a class="institution-brand" href="index.php" aria-label="Tes Seleksi Magang RSUD Malangbong dan Kemnaker, halaman utama"><span class="institution-logos"><img src="logo-rsud-malangbong.png" alt="Logo RSUD Malangbong"><span class="logo-separator" aria-hidden="true"></span><img src="logo-kemnaker.png" alt="Logo Kemnaker"></span><span class="institution-divider" aria-hidden="true"></span><span class="institution-name"><strong>RSUD Malangbong</strong><small>SELEKSI MAGANG · KEMNAKER</small></span></a><div class="top-right"><?php if ($loggedIn): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="header-button">Keluar ↗</button></form><?php else: ?><a href="index.php" class="admin-link">Kembali ke tes ↗</a><?php endif; ?></div></div></header>
<main class="page-shell">
<?php if (!$loggedIn): ?>
<section class="admin-login form-card reveal"><div class="section-kicker"><span></span> AKSES PANITIA</div><h1>Selamat datang<br><em>kembali.</em></h1><p>Masuk untuk melihat peserta dan hasil tes seleksi magang RSUD Malangbong.</p><?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?><form method="post" class="biodata-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login"><label for="username">Nama pengguna</label><input id="username" name="username" autocomplete="username" required><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="current-password" required><button class="primary-button">Masuk ke dashboard <span>↗</span></button></form></section>
<?php else: ?>
<section class="admin-wrap reveal"><div class="admin-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> PANEL PANITIA</div><h1>Dashboard <em>seleksi.</em></h1><p>Pantau peserta dan hasil tes dalam satu tempat.</p></div><span class="admin-badge">● &nbsp; AREA ADMIN</span></div>
<?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($detail): ?><a href="admin.php" class="back-link">← Kembali ke daftar peserta</a><div class="admin-detail"><div class="admin-detail-head"><div><span class="aside-kicker">DETAIL PESERTA</span><h2><?= e($detail['name']) ?></h2><p><?= e($detail['email']) ?> · <?= e($detail['nik']) ?> · <?= e($labels[$detail['position']]) ?></p></div><?php if ($detail['submission_id']): ?><div class="detail-score"><strong><?= e(number_format((float)$detail['score'], 2, ',', '.')) ?></strong><span><?= e($detail['correct_count']) ?> / <?= e($detail['total_questions']) ?> benar</span></div><?php else: ?><span class="status status-pending"><?= $detail['time_expired'] ? 'Waktu habis · belum dikirim' : 'Sedang mengerjakan' ?></span><?php endif; ?></div>
<?php if ($answers): ?><div class="review-list"><?php foreach ($answers as $i => $answer): ?><article class="review-card"><div class="review-top"><span>SOAL <?= str_pad((string)($i+1), 2, '0', STR_PAD_LEFT) ?></span><strong class="<?= $answer['is_correct'] ? 'correct' : 'incorrect' ?>"><?= $answer['selected_option'] === null ? 'Tidak dijawab' : ($answer['is_correct'] ? '✓ Benar' : '× Belum tepat') ?></strong></div><h3><?= e($answer['question_text']) ?></h3><div class="review-answer"><small>JAWABAN PESERTA</small><p><?= $answer['selected_option'] === null ? 'Tidak dijawab' : e($answer['selected_option']) . '. ' . e($answer['selected_text']) ?></p></div></article><?php endforeach; ?></div><?php else: ?><p class="empty-state">Peserta belum mengirimkan jawaban.</p><?php endif; ?></div>
<?php else: ?>
<div class="stats-grid"><div class="stat-card"><span>SELURUH PESERTA</span><strong><?= e($stats['total']) ?></strong><small>Peserta terdaftar</small></div><div class="stat-card"><span>TES SELESAI</span><strong><?= e($stats['completed']) ?></strong><small>Jawaban terkirim</small></div><div class="stat-card"><span>DALAM PROSES</span><strong><?= e((int)$stats['total'] - (int)$stats['completed']) ?></strong><small>Belum mengirim jawaban</small></div></div>
<div class="participants-card"><div class="participants-head"><div><div class="section-kicker"><span></span> DATA SELEKSI</div><h2>Daftar peserta</h2><p>Peserta yang telah memulai tes akan tampil di sini.</p></div><form class="search-form" method="get"><label class="sr-only" for="search">Cari peserta</label><input id="search" name="q" placeholder="Cari nama, email, atau NIK" value="<?= e($search) ?>"><button type="submit">Cari</button></form></div>
<div class="table-scroll"><table><thead><tr><th>PESERTA</th><th>NIK</th><th>POSISI</th><th>STATUS</th><th>NILAI</th><th>WAKTU DAFTAR</th><th>AKSI</th></tr></thead><tbody><?php foreach ($participants as $p): ?><tr><td><strong><?= e($p['name']) ?></strong><small><?= e($p['email']) ?></small></td><td><?= e($p['nik']) ?></td><td><?= e($labels[$p['position']]) ?></td><td><span class="status <?= $p['submission_id'] ? 'status-done' : 'status-pending' ?>"><?= $p['submission_id'] ? 'Selesai' : ($p['time_expired'] ? 'Waktu habis' : 'Dalam proses') ?></span></td><td><?= $p['submission_id'] ? e(number_format((float)$p['score'], 2, ',', '.')) : '—' ?></td><td><?= e($p['started_at']) ?></td><td><a class="table-link" href="admin.php?id=<?= e($p['id']) ?>">Detail ↗</a><?php if (!$p['submission_id'] && $p['time_expired']): ?><form method="post" onsubmit="return confirm('Finalisasi jawaban yang tersimpan? Soal yang belum dijawab akan dihitung salah.')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="finalize"><input type="hidden" name="participant_id" value="<?= e($p['id']) ?>"><button class="table-link finalize-button">Finalisasi hasil</button></form><?php endif; ?><?php if (!$p['submission_id']): ?><form method="post" onsubmit="return confirm('Hapus pendaftaran yang belum selesai agar peserta dapat mendaftar ulang?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="participant_id" value="<?= e($p['id']) ?>"><button class="reset-button">Hapus pendaftaran</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php if (!$participants): ?><p class="empty-state">Belum ada peserta yang ditemukan.</p><?php endif; ?></div>
<div class="pagination"><span>Halaman <?= $page ?> dari <?= $totalPages ?></span><div><?php if ($page > 1): ?><a href="?<?= e(http_build_query(['q' => $search, 'page' => $page-1])) ?>">← Sebelumnya</a><?php endif; ?> <?php if ($page < $totalPages): ?><a href="?<?= e(http_build_query(['q' => $search, 'page' => $page+1])) ?>">Selanjutnya →</a><?php endif; ?></div></div></div>
<?php endif; ?></section><?php endif; ?></main><footer><span>© <?= date('Y') ?> RSUD MALANGBONG <span class="footer-dot">•</span> SELEKSI MAGANG</span><span>PROGRAM MAGANG KEMNAKER <span class="footer-star">✳</span></span></footer></body></html>
