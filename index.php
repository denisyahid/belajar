<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/exam.php';
$errors = [];
$old = ['name' => '', 'email' => '', 'nik' => '', 'position' => ''];
$databaseError = false;
$positions = ['perawat' => 'Keperawatan', 'administrasi' => 'Administrasi', 'kebidanan' => 'Kebidanan', 'laboratorium' => 'Laboratorium / ATLM'];
$positionLabel = $positions;
$remaining = 0; $drafts = []; $list = [];

if (!empty($_SESSION['candidate']) && empty($_SESSION['candidate']['id'])) unset($_SESSION['candidate'], $_SESSION['question_ids'], $_SESSION['submission_token']);
if (!empty($_SESSION['completed']) && !isset($_SESSION['completed']['review'])) unset($_SESSION['completed']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf(); $action = $_POST['action'] ?? '';
    if ($action === 'start' && empty($_SESSION['candidate'])) {
        foreach ($old as $key => $_) $old[$key] = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
        if ($old['name'] === '' || strlen($old['name']) > 120) $errors[] = 'Isi nama lengkap (maksimal 120 karakter).';
        if (strlen($old['email']) > 254 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Masukkan alamat email yang valid.';
        if (!preg_match('/^[0-9]{16}$/D', $old['nik'])) $errors[] = 'NIK harus terdiri dari 16 digit angka.';
        if (!isset($positions[$old['position']])) $errors[] = 'Pilih posisi yang tersedia.';
        if (!$errors) {
            try {
                $list = questions($old['position']);
                if (!exam_is_ready($list)) $errors[] = 'Paket soal untuk posisi ini belum lengkap (20 TWK dan 30 SKD). Hubungi panitia.';
                else {
                    $stmt = db()->prepare('INSERT INTO participants (name, email, nik, position) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$old['name'], $old['email'], $old['nik'], $old['position']]);
                    $old['id'] = (int)db()->lastInsertId(); session_regenerate_id(true);
                    $_SESSION['candidate'] = $old; $_SESSION['question_ids'] = array_column($list, 'id');
                    $_SESSION['submission_token'] = bin2hex(random_bytes(32)); unset($_SESSION['completed']); redirect();
                }
            } catch (PDOException $ex) {
                if (($ex->errorInfo[1] ?? 0) === 1062) $errors[] = 'NIK ini sudah terdaftar. Setiap NIK hanya dapat mengikuti tes satu kali. Jika sesi terputus, hubungi panitia.';
                else $databaseError = true;
            } catch (Throwable $ex) { $databaseError = true; }
        }
    } elseif ($action === 'submit' && !empty($_SESSION['candidate'])) {
        try {
            $candidate = $_SESSION['candidate']; $list = questions($candidate['position']);
            if (!exam_is_ready($list) || array_column($list, 'id') !== $_SESSION['question_ids']) $errors[] = 'Daftar soal telah berubah. Silakan hubungi panitia.';
            else { finish_test($candidate, $list, is_array($_POST['answer'] ?? null) ? $_POST['answer'] : []); redirect(); }
        } catch (InvalidArgumentException $ex) { $errors[] = $ex->getMessage(); }
        catch (Throwable $ex) { $databaseError = true; }
    } elseif ($action === 'restart' && empty($_SESSION['candidate'])) { unset($_SESSION['completed']); redirect(); }
    else { redirect(); }
}
if (!empty($_SESSION['candidate']['id'])) {
    try {
        $pdo = db(); $candidate = $_SESSION['candidate']; $state = participant_state($pdo, $candidate);
        if (!$state) { unset($_SESSION['candidate'], $_SESSION['question_ids'], $_SESSION['submission_token']); $errors[] = 'Pendaftaran sebelumnya telah dihapus oleh panitia. Silakan isi biodata kembali.'; }
        else {
            $list = questions($candidate['position']); $remaining = max(0, (int)$state['remaining']);
            $drafts = saved_drafts($pdo, (int)$candidate['id']);
            if ($remaining === 0 && exam_is_ready($list) && array_column($list, 'id') === $_SESSION['question_ids']) { finish_test($candidate, $list, [], true); if (empty($_SESSION['candidate'])) redirect(); }
        }
    } catch (Throwable $ex) { $databaseError = true; }
}
$stage = !empty($_SESSION['candidate']) ? 'test' : (!empty($_SESSION['completed']) ? 'done' : 'start');
if ($databaseError) $errors[] = 'Koneksi database bermasalah. Periksa konfigurasi dan coba lagi, atau hubungi panitia.';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#10355b"><title>Tes Seleksi Magang | RSUD Malangbong &amp; Kemnaker</title>
<link rel="stylesheet" href="style.css">
<script src="app.js" defer></script>
</head>
<body class="stage-<?= e($stage) ?>">
<header class="topbar"><div class="topbar-inner">
    <a class="institution-brand" href="index.php" aria-label="Tes Seleksi Magang RSUD Malangbong dan Kemnaker, halaman utama">
        <span class="institution-logos"><img src="logo-rsud-malangbong.png" alt="Logo RSUD Malangbong"><span class="logo-separator" aria-hidden="true"></span><img src="logo-kemnaker.png" alt="Logo Kemnaker"></span>
        <span class="institution-divider" aria-hidden="true"></span><span class="institution-name"><strong>RSUD Malangbong</strong><small>SELEKSI MAGANG · KEMNAKER</small></span>
    </a>
    <div class="top-right"><span class="secure-dot"></span> PROGRAM MAGANG <span class="top-divider"></span><a class="admin-link" href="admin.php">Admin <span aria-hidden="true">↗</span></a></div>
</div></header>
<main class="page-shell">
<?php if ($stage === 'start'): ?>
<div class="intro-layout">
<section class="intro reveal">
    <div class="eyebrow"><span class="eyebrow-line"></span> PROGRAM MAGANG KEMNAKER <span class="eyebrow-number">/ 01</span></div>
    <h1>Seleksi Magang<br><em>RSUD Malangbong.</em></h1>
    <p class="intro-copy">Selamat datang di tes seleksi magang RSUD Malangbong melalui program Kemnaker. Isi biodata dan tunjukkan potensi terbaik Anda.</p>
    <?php if (config()['twk_is_example'] ?? true): ?><p class="sample-note"><strong>Catatan:</strong> TWK keperawatan masih contoh sementara. Seluruh soal dan kunci perlu divalidasi mentor sebelum seleksi resmi.</p><?php endif; ?>
    <div class="exam-info"><span><strong>50</strong> SOAL</span><i></i><span><strong>20</strong> TWK</span><i></i><span><strong>30</strong> SKD</span><i></i><span><strong>60</strong> MENIT</span></div>
    <div class="intro-rule"></div>
    <div class="journey-label">PERJALANAN ANDA</div>
    <div class="journey"><div class="journey-item"><span class="journey-icon active">01</span><div><strong>Isi biodata</strong><small>Perkenalkan diri Anda</small></div></div><span class="journey-connector"></span><div class="journey-item"><span class="journey-icon">02</span><div><strong>Kerjakan tes</strong><small>Jawab sesuai kemampuan</small></div></div><span class="journey-connector"></span><div class="journey-item"><span class="journey-icon">03</span><div><strong>Selesai</strong><small>Jawaban tersimpan</small></div></div></div>
    <div class="intro-bottom"><span class="sparkle">✳</span><span>Bersama membangun pengalaman dan masa depan.</span></div>
</section>
<section class="form-card reveal delay" aria-labelledby="form-title">
    <div class="card-top"><div><div class="section-kicker"><span></span> TAHAP PERTAMA</div><h2 id="form-title">Mari berkenalan.</h2><p>Isi biodata untuk mengikuti tes seleksi magang.</p></div><div class="card-number">01<span>/03</span></div></div>
    <?php if ($errors): ?><div class="alert" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" class="biodata-form">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="start">
        <label for="name">Nama lengkap <span>*</span></label><input id="name" name="name" type="text" maxlength="120" placeholder="Masukkan nama lengkap" value="<?= e($old['name']) ?>" autocomplete="name" required>
        <label for="email">Alamat email <span>*</span></label><input id="email" name="email" type="email" maxlength="254" placeholder="contoh@email.com" value="<?= e($old['email']) ?>" autocomplete="email" required>
        <label for="nik">Nomor Induk Kependudukan <span>*</span></label><input id="nik" name="nik" type="text" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" placeholder="Masukkan 16 digit NIK" value="<?= e($old['nik']) ?>" required><div class="field-hint">Satu NIK hanya dapat digunakan untuk satu kali pendaftaran.</div>
        <label for="position">Posisi yang dilamar <span>*</span></label><div class="select-wrap"><select id="position" name="position" required><option value="" disabled <?= $old['position'] === '' ? 'selected' : '' ?>>Pilih posisi</option><option value="perawat" <?= $old['position'] === 'perawat' ? 'selected' : '' ?>>Keperawatan</option><option value="kebidanan" <?= $old['position'] === 'kebidanan' ? 'selected' : '' ?>>Kebidanan</option><option value="laboratorium" <?= $old['position'] === 'laboratorium' ? 'selected' : '' ?>>Laboratorium / ATLM</option><option value="administrasi" <?= $old['position'] === 'administrasi' ? 'selected' : '' ?>>Administrasi</option><option value="rekam_medis" disabled>Rekam Medis — belum tersedia</option><option value="radiografer" disabled>Radiografer — belum tersedia</option><option value="ipsrs" disabled>IPSRS — belum tersedia</option></select></div>
        <div class="field-hint availability-note">Tersedia: keperawatan, kebidanan, laboratorium, dan administrasi.</div>
        <button class="primary-button" type="submit">Mulai tes seleksi <span aria-hidden="true">↗</span></button>
        <div class="privacy"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Waktu tes 60 menit dimulai setelah Anda menekan tombol di atas.</div>
    </form>
</section>
</div>
<div class="bottom-strip"><div><span class="strip-icon">✦</span><span><strong>Selangkah lebih dekat</strong><small>Menuju pengalaman di RSUD Malangbong</small></span></div><p>Persiapkan diri dan kerjakan tes dengan tenang.</p><span class="strip-index">MK — 01</span></div>
<?php elseif ($stage === 'test'): ?>
<section class="test-wrap reveal">
    <div class="test-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> TAHAP KEDUA <span class="eyebrow-number">/ 02</span></div><h1>Waktunya <em>menunjukkan</em><br>potensi Anda.</h1><p>Halo, <?= e($_SESSION['candidate']['name']) ?>. Pilih satu jawaban yang paling tepat untuk setiap pertanyaan.</p></div><div class="position-badge"><span>POSISI DILAMAR</span><strong><?= e($positionLabel[$_SESSION['candidate']['position']]) ?></strong></div></div>
    <?php if ($errors): ?><div class="alert" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php if ($list && array_column($list, 'id') === $_SESSION['question_ids']): ?>
    <noscript><div class="alert">Aktifkan JavaScript agar waktu dan penyimpanan jawaban otomatis berfungsi. Waktu tetap berjalan di server.</div></noscript>
    <form method="post" id="test-form" data-remaining="<?= $remaining ?>">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="submit">
    <div class="test-layout"><div class="questions-main">
    <div class="test-progress"><div class="progress-heading"><span>PROGRES TES</span><strong id="progress-label">0 / <?= count($list) ?> terjawab</strong><div class="timer" aria-label="Sisa waktu pengerjaan">◷ <span id="timer"><?= sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60) ?></span></div></div><div class="progress-track"><span id="progress-bar"></span></div><p id="save-status" class="save-status" aria-live="polite">Jawaban disimpan otomatis saat terhubung.</p></div>
    <?php foreach ($list as $i => $q): ?><section class="question-card" data-question data-index="<?= $i ?>">
        <div class="question-meta"><span>PERTANYAAN <b><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></b> <span class="question-total">/ <?= str_pad((string)count($list), 2, '0', STR_PAD_LEFT) ?></span></span><span class="question-type"><?= e($q['section']) ?> <?= $q['section'] === 'TWK' ? $i + 1 : $i - 19 ?> · PILIHAN GANDA</span></div>
        <h2><?= e($q['question']) ?></h2><div class="options">
        <?php foreach (['A','B','C','D','E'] as $letter): if (trim($q['option_' . strtolower($letter)]) === '') continue; $checked = ($_POST['answer'][$q['id']] ?? $drafts[$q['id']] ?? '') === $letter; ?><label class="option"><input type="radio" name="answer[<?= e($q['id']) ?>]" value="<?= $letter ?>" <?= $checked ? 'checked' : '' ?> required><span class="option-letter"><?= $letter ?></span><span class="option-text"><?= e($q['option_' . strtolower($letter)]) ?></span><span class="option-check">✓</span></label><?php endforeach; ?>
        </div>
    </section><?php endforeach; ?>
    <div class="test-actions"><button type="button" class="secondary-button" id="prev-button">← <span>Sebelumnya</span></button><button type="button" class="primary-button" id="next-button">Selanjutnya <span>→</span></button><button type="submit" class="primary-button" id="submit-button">Kirim jawaban <span>↗</span></button></div>
    </div><aside class="test-aside"><div class="aside-kicker">NAVIGASI SOAL</div><h3>Perjalanan tes</h3><p>Pilih nomor untuk berpindah antar pertanyaan.</p><div class="question-nav" id="question-nav"></div><div class="nav-legend"><span><i></i> Belum dijawab</span><span><i></i> Terjawab</span></div><div class="aside-note"><span>✳</span><p>Periksa kembali jawaban Anda sebelum mengirimkan tes.</p></div></aside></div>
    </form>
    <?php elseif (!$databaseError): ?><div class="alert">Daftar soal telah berubah. Silakan hubungi panitia untuk bantuan.</div><?php endif; ?>
</section>
<?php else: ?>
<section class="result-wrap reveal"><div class="result-hero"><div class="result-hero-copy"><div class="section-kicker"><span></span> TES SELESAI</div><h1>Terima kasih,<br><em><?= e($_SESSION['completed']['name']) ?>.</em></h1><p>Jawaban Anda berhasil tersimpan. Berikut hasil tes dan ringkasan jawaban Anda.</p><div class="result-tag">✓ &nbsp; Jawaban terkirim &nbsp; · &nbsp; <?= e($positionLabel[$_SESSION['completed']['position']]) ?></div></div><div class="score-card"><span>NILAI AKHIR</span><strong><?= e(number_format((float)$_SESSION['completed']['score'], fmod((float)$_SESSION['completed']['score'], 1.0) === 0.0 ? 0 : 2, ',', '.')) ?><small class="score-max"> / 100</small></strong><small><?= e($_SESSION['completed']['correct']) ?> dari <?= e($_SESSION['completed']['total']) ?> jawaban benar</small></div></div>
<div class="result-heading"><div><div class="section-kicker"><span></span> RINGKASAN TES</div><h2>Jawaban Anda</h2><p>Hasil dihitung berdasarkan kunci jawaban yang tersedia.</p></div><span><?= e($_SESSION['completed']['total']) ?> SOAL</span></div>
<div class="review-list protected-questions"><?php foreach ($_SESSION['completed']['review'] as $i => $review): ?><article class="review-card"><div class="review-top"><span>SOAL <?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?> · <?= e($review['section'] ?? 'SKD') ?></span><strong class="<?= $review['correct'] ? 'correct' : 'incorrect' ?>"><?= $review['selected_option'] === null ? 'Tidak dijawab' : ($review['correct'] ? '✓ Benar' : '× Belum tepat') ?></strong></div><h3><?= e($review['question']) ?></h3><div class="review-answer"><small>JAWABAN ANDA</small><p><?= $review['selected_option'] === null ? 'Tidak dijawab' : e($review['selected_option']) . '. ' . e($review['selected_text']) ?></p></div></article><?php endforeach; ?></div>
<div class="result-end"><p>Informasi tahapan selanjutnya akan disampaikan oleh panitia seleksi.</p><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="restart"><button type="submit" class="secondary-button">Kembali ke awal <span>↗</span></button></form></div></section>
<?php endif; ?>
</main>
<footer><span>© <?= date('Y') ?> RSUD MALANGBONG <span class="footer-dot">•</span> SELEKSI MAGANG</span><span>PROGRAM MAGANG KEMNAKER <span class="footer-star">✳</span></span></footer>
</body></html>
