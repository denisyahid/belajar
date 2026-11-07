<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
header('Content-Type: application/json; charset=utf-8');
function respond(int $status, array $data): void { http_response_code($status); echo json_encode($data); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['candidate'])) respond(403, ['error' => 'Sesi tes tidak tersedia.']);
check_csrf();
$id = filter_var($_POST['question_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$answer = $_POST['answer'] ?? null;
if (!$id || !in_array($id, array_map('intval', $_SESSION['question_ids'] ?? []), true) || !is_string($answer) || !in_array($answer, ['A','B','C','D','E'], true)) respond(400, ['error' => 'Jawaban tidak valid.']);
try {
    $pdo = db(); $pdo->beginTransaction(); $candidate = $_SESSION['candidate'];
    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, DATE_ADD(started_at, INTERVAL 60 MINUTE)) AS remaining FROM participants WHERE id = ? AND nik = ? FOR UPDATE');
    $stmt->execute([$candidate['id'], $candidate['nik']]); $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$participant) { $pdo->rollBack(); respond(403, ['error' => 'Pendaftaran tidak ditemukan.']); }
    if ((int)$participant['remaining'] <= 0) { $pdo->rollBack(); respond(409, ['expired' => true]); }
    $stmt = $pdo->prepare('SELECT id FROM submissions WHERE participant_id = ?'); $stmt->execute([$candidate['id']]);
    if ($stmt->fetchColumn()) { $pdo->rollBack(); respond(409, ['error' => 'Tes sudah dikirim.']); }
    $stmt = $pdo->prepare('SELECT option_a, option_b, option_c, option_d, option_e FROM questions WHERE id = ? AND position = ?');
    $stmt->execute([$id, $candidate['position']]); $q = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$q || trim($q['option_' . strtolower($answer)]) === '') { $pdo->rollBack(); respond(400, ['error' => 'Pilihan tidak tersedia.']); }
    $stmt = $pdo->prepare('INSERT INTO draft_answers (participant_id, question_id, selected_option) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option)');
    $stmt->execute([$candidate['id'], $id, $answer]); $pdo->commit();
    respond(200, ['saved' => true, 'remaining' => max(0, (int)$participant['remaining'])]);
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    respond(500, ['error' => 'Jawaban belum tersimpan. Periksa koneksi Anda.']);
}
