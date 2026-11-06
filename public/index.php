<?php
declare(strict_types=1);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(): void { header('Location: index.php', true, 303); exit; }
function db(): PDO {
    static $db = null;
    if ($db === null) {
        $config = is_file(dirname(__DIR__) . '/config.php') ? require dirname(__DIR__) . '/config.php' : [];
        $host = getenv('DB_HOST') ?: ($config['host'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($config['port'] ?? '3306');
        $name = getenv('DB_NAME') ?: ($config['database'] ?? 'tes_karyawan');
        $user = getenv('DB_USER') ?: ($config['username'] ?? 'root');
        $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? '');
        $db = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}
function questions(string $position): array {
    $stmt = db()->prepare('SELECT * FROM questions WHERE position = ? ORDER BY sort_order');
    $stmt->execute([$position]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$errors = [];
$old = ['name' => '', 'email' => '', 'nik' => '', 'position' => ''];
$databaseError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Sesi tidak valid. Muat ulang halaman dan coba lagi.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'start') {
        foreach ($old as $key => $_) $old[$key] = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
        if ($old['name'] === '' || strlen($old['name']) > 120) $errors[] = 'Isi nama lengkap (maksimal 120 karakter).';
        if (strlen($old['email']) > 254 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Masukkan alamat email yang valid.';
        if (!preg_match('/^[0-9]{16}$/D', $old['nik'])) $errors[] = 'NIK harus terdiri dari 16 digit angka.';
        if (!in_array($old['position'], ['perawat', 'administrasi'], true)) $errors[] = 'Pilih posisi yang dilamar.';
        if (!$errors) {
            try {
                $list = questions($old['position']);
                if (!$list) $errors[] = 'Soal untuk posisi ini belum tersedia. Silakan hubungi panitia.';
                else {
                    session_regenerate_id(true);
                    $_SESSION['candidate'] = $old;
                    $_SESSION['question_ids'] = array_column($list, 'id');
                    $_SESSION['submission_token'] = bin2hex(random_bytes(32));
                    unset($_SESSION['completed']);
                    redirect();
                }
            } catch (Throwable $ex) { $databaseError = true; }
        }
    } elseif ($action === 'submit' && !empty($_SESSION['candidate'])) {
        try {
            $candidate = $_SESSION['candidate'];
            $list = questions($candidate['position']);
            if (!$list || array_column($list, 'id') !== $_SESSION['question_ids']) {
                $errors[] = 'Daftar soal telah berubah. Silakan mulai kembali dan hubungi panitia jika masalah berlanjut.';
            } else {
                $responses = is_array($_POST['answer'] ?? null) ? $_POST['answer'] : [];
                foreach ($list as $q) {
                    if (!isset($responses[$q['id']]) || !is_string($responses[$q['id']]) || !in_array($responses[$q['id']], ['A','B','C','D','E'], true)) {
                        $errors[] = 'Jawab semua soal sebelum mengirimkan tes.'; break;
                    }
                }
                if (!$errors) {
                    $pdo = db(); $pdo->beginTransaction();
                    $correct = 0;
                    foreach ($list as $q) if ($responses[$q['id']] === $q['answer_key']) $correct++;
                    $stmt = $pdo->prepare('INSERT INTO submissions (submission_token, name, email, nik, position, correct_count, total_questions, score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$_SESSION['submission_token'], $candidate['name'], $candidate['email'], $candidate['nik'], $candidate['position'], $correct, count($list), round($correct / count($list) * 100, 2)]);
                    $submissionId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare('INSERT INTO answers (submission_id, question_id, question_text, selected_option, selected_text, is_correct) VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($list as $q) {
                        $answer = $responses[$q['id']];
                        $stmt->execute([$submissionId, $q['id'], $q['question'], $answer, $q['option_' . strtolower($answer)], (int)($answer === $q['answer_key'])]);
                    }
                    $pdo->commit();
                    $_SESSION['completed'] = ['name' => $candidate['name'], 'position' => $candidate['position']];
                    unset($_SESSION['candidate'], $_SESSION['question_ids'], $_SESSION['submission_token']);
                    redirect();
                }
            }
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $databaseError = true;
        }
    } elseif ($action === 'restart') {
        unset($_SESSION['candidate'], $_SESSION['question_ids'], $_SESSION['submission_token'], $_SESSION['completed']);
        redirect();
    } else { redirect(); }
}
$stage = !empty($_SESSION['candidate']) ? 'test' : (!empty($_SESSION['completed']) ? 'done' : 'start');
$list = [];
if ($stage === 'test') {
    try { $list = questions($_SESSION['candidate']['position']); }
    catch (Throwable $ex) { $databaseError = true; }
}
if ($databaseError) $errors[] = 'Koneksi database bermasalah. Periksa konfigurasi dan coba lagi, atau hubungi panitia.';
$positionLabel = ['perawat' => 'Perawat', 'administrasi' => 'Administrasi'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f6f8f7"><title>Tes Karyawan Baru | Ruang Talenta</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body class="stage-<?= e($stage) ?>">
<header class="topbar"><div class="topbar-inner">
    <a class="brand" href="index.php" aria-label="Ruang Talenta, halaman utama"><span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".65"/></svg></span><span>ruang<span class="brand-accent">talenta</span><small>REKRUTMEN & KARIER</small></span></a>
    <div class="top-right"><span class="secure-dot"></span> PORTAL SELEKSI KARYAWAN <span class="top-divider"></span><span class="top-year">2026</span></div>
</div></header>
<main class="page-shell">
<?php if ($stage === 'start'): ?>
<div class="intro-layout">
<section class="intro reveal">
    <div class="eyebrow"><span class="eyebrow-line"></span> SELEKSI KARYAWAN BARU <span class="eyebrow-number">/ 01</span></div>
    <h1>Langkah awal<br>untuk <em>tumbuh</em><br>bersama.</h1>
    <p class="intro-copy">Setiap perjalanan besar dimulai dari langkah pertama. Mari kenali potensi Anda melalui tes seleksi ini.</p>
    <div class="intro-rule"></div>
    <div class="journey-label">PERJALANAN ANDA</div>
    <div class="journey"><div class="journey-item"><span class="journey-icon active">01</span><div><strong>Isi biodata</strong><small>Perkenalkan diri Anda</small></div></div><span class="journey-connector"></span><div class="journey-item"><span class="journey-icon">02</span><div><strong>Kerjakan tes</strong><small>Jawab sesuai kemampuan</small></div></div><span class="journey-connector"></span><div class="journey-item"><span class="journey-icon">03</span><div><strong>Selesai</strong><small>Jawaban tersimpan</small></div></div></div>
    <div class="intro-bottom"><span class="sparkle">✳</span><span>Kesempatan baik dimulai dari sini.</span></div>
</section>
<section class="form-card reveal delay" aria-labelledby="form-title">
    <div class="card-top"><div><div class="section-kicker"><span></span> TAHAP PERTAMA</div><h2 id="form-title">Mari berkenalan.</h2><p>Isi data diri Anda untuk memulai tes seleksi.</p></div><div class="card-number">01<span>/03</span></div></div>
    <?php if ($errors): ?><div class="alert" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" class="biodata-form">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="start">
        <label for="name">Nama lengkap <span>*</span></label><input id="name" name="name" type="text" maxlength="120" placeholder="Masukkan nama lengkap" value="<?= e($old['name']) ?>" autocomplete="name" required>
        <label for="email">Alamat email <span>*</span></label><input id="email" name="email" type="email" maxlength="254" placeholder="contoh@email.com" value="<?= e($old['email']) ?>" autocomplete="email" required>
        <label for="nik">Nomor Induk Kependudukan <span>*</span></label><input id="nik" name="nik" type="text" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" placeholder="Masukkan 16 digit NIK" value="<?= e($old['nik']) ?>" required><div class="field-hint">Pastikan NIK sesuai dengan identitas Anda.</div>
        <label for="position">Posisi yang dilamar <span>*</span></label><div class="select-wrap"><select id="position" name="position" required><option value="" disabled <?= $old['position'] === '' ? 'selected' : '' ?>>Pilih posisi</option><option value="perawat" <?= $old['position'] === 'perawat' ? 'selected' : '' ?>>Perawat</option><option value="administrasi" <?= $old['position'] === 'administrasi' ? 'selected' : '' ?>>Administrasi</option></select></div>
        <button class="primary-button" type="submit">Mulai tes seleksi <span aria-hidden="true">↗</span></button>
        <div class="privacy"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Data Anda digunakan untuk keperluan seleksi.</div>
    </form>
</section>
</div>
<div class="bottom-strip"><div><span class="strip-icon">✦</span><span><strong>Selangkah lebih dekat</strong><small>Menuju perjalanan baru Anda</small></span></div><p>Persiapkan diri Anda dan kerjakan dengan tenang.</p><span class="strip-index">RT — 01</span></div>
<?php elseif ($stage === 'test'): ?>
<section class="test-wrap reveal">
    <div class="test-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> TAHAP KEDUA <span class="eyebrow-number">/ 02</span></div><h1>Waktunya <em>menunjukkan</em><br>potensi Anda.</h1><p>Halo, <?= e($_SESSION['candidate']['name']) ?>. Pilih satu jawaban yang paling tepat untuk setiap pertanyaan.</p></div><div class="position-badge"><span>POSISI DILAMAR</span><strong><?= e($positionLabel[$_SESSION['candidate']['position']]) ?></strong></div></div>
    <?php if ($errors): ?><div class="alert" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php if ($list && array_column($list, 'id') === $_SESSION['question_ids']): ?>
    <form method="post" id="test-form">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="submit">
    <div class="test-layout"><div class="questions-main">
    <div class="test-progress"><div><span>PROGRES TES</span><strong id="progress-label">0 / <?= count($list) ?> terjawab</strong></div><div class="progress-track"><span id="progress-bar"></span></div></div>
    <?php foreach ($list as $i => $q): ?><section class="question-card" data-question data-index="<?= $i ?>">
        <div class="question-meta"><span>PERTANYAAN <b><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></b> <span class="question-total">/ <?= str_pad((string)count($list), 2, '0', STR_PAD_LEFT) ?></span></span><span class="question-type">PILIHAN GANDA</span></div>
        <h2><?= e($q['question']) ?></h2><div class="options">
        <?php foreach (['A','B','C','D','E'] as $letter): $checked = ($_POST['answer'][$q['id']] ?? '') === $letter; ?><label class="option"><input type="radio" name="answer[<?= e($q['id']) ?>]" value="<?= $letter ?>" <?= $checked ? 'checked' : '' ?> required><span class="option-letter"><?= $letter ?></span><span class="option-text"><?= e($q['option_' . strtolower($letter)]) ?></span><span class="option-check">✓</span></label><?php endforeach; ?>
        </div>
    </section><?php endforeach; ?>
    <div class="test-actions"><button type="button" class="secondary-button" id="prev-button">← <span>Sebelumnya</span></button><button type="button" class="primary-button" id="next-button">Selanjutnya <span>→</span></button><button type="submit" class="primary-button" id="submit-button">Kirim jawaban <span>↗</span></button></div>
    </div><aside class="test-aside"><div class="aside-kicker">NAVIGASI SOAL</div><h3>Perjalanan tes</h3><p>Pilih nomor untuk berpindah antar pertanyaan.</p><div class="question-nav" id="question-nav"></div><div class="nav-legend"><span><i></i> Belum dijawab</span><span><i></i> Terjawab</span></div><div class="aside-note"><span>✳</span><p>Periksa kembali jawaban Anda sebelum mengirimkan tes.</p></div></aside></div>
    </form>
    <?php elseif (!$databaseError): ?><div class="alert">Daftar soal telah berubah. Silakan mulai kembali.</div><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="restart"><button class="secondary-button">Mulai kembali</button></form><?php endif; ?>
</section>
<?php else: ?>
<section class="complete-card reveal"><div class="complete-icon">✓</div><div class="section-kicker"><span></span> TES SELESAI</div><h1>Terima kasih,<br><em><?= e($_SESSION['completed']['name']) ?>.</em></h1><p>Jawaban Anda telah berhasil tersimpan. Terima kasih telah meluangkan waktu untuk mengikuti tes seleksi ini.</p><div class="complete-detail"><span>POSISI DILAMAR</span><strong><?= e($positionLabel[$_SESSION['completed']['position']]) ?></strong><span class="complete-status">✓ &nbsp; Terkirim</span></div><p class="complete-note">Informasi mengenai tahapan selanjutnya akan disampaikan oleh tim rekrutmen.</p><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="restart"><button type="submit" class="secondary-button">Kembali ke awal <span>↗</span></button></form></section>
<?php endif; ?>
</main>
<footer><span>© 2026 RUANG TALENTA <span class="footer-dot">•</span> PORTAL SELEKSI KARYAWAN</span><span>MADE FOR YOUR NEXT CHAPTER <span class="footer-star">✳</span></span></footer>
</body></html>
