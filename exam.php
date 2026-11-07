<?php
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; }
function participant_state(PDO $pdo, array $candidate, bool $lock = false): ?array {
    $sql = 'SELECT id, TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, DATE_ADD(started_at, INTERVAL 60 MINUTE)) AS remaining FROM participants WHERE id = ? AND nik = ?' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql); $stmt->execute([$candidate['id'], $candidate['nik']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
function saved_drafts(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare('SELECT question_id, selected_option FROM draft_answers WHERE participant_id = ?'); $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
function finish_test(array $candidate, array $list, array $responses, bool $requireExpired = false, ?string $token = null, bool $saveSession = true): ?array {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $state = participant_state($pdo, $candidate, true);
        if (!$state) throw new RuntimeException('Pendaftaran tidak ditemukan.');
        $existing = $pdo->prepare('SELECT id, correct_count, total_questions, score FROM submissions WHERE participant_id = ?');
        $existing->execute([$candidate['id']]); $submission = $existing->fetch(PDO::FETCH_ASSOC);
        if ($submission) {
            $stmt = $pdo->prepare('SELECT question_id, question_text, selected_option, selected_text, is_correct FROM answers WHERE submission_id = ? ORDER BY id');
            $stmt->execute([$submission['id']]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); $review = [];
            $sections = []; foreach ($list as $q) $sections[$q['id']] = $q['section'];
            foreach ($rows as $row) $review[] = ['question' => $row['question_text'], 'section' => $sections[$row['question_id']] ?? '', 'selected_option' => $row['selected_option'], 'selected_text' => $row['selected_text'], 'correct' => (bool)$row['is_correct']];
            $correct = (int)$submission['correct_count']; $total = (int)$submission['total_questions']; $score = (float)$submission['score'];
        } else {
            $expired = (int)$state['remaining'] <= 0;
            if ($requireExpired && !$expired) { $pdo->rollBack(); return null; }
            if ($expired) $responses = saved_drafts($pdo, (int)$candidate['id']);
            $correct = 0; $review = [];
            foreach ($list as $q) {
                $answer = $responses[$q['id']] ?? null;
                if (!is_string($answer) || !in_array($answer, ['A','B','C','D','E'], true) || trim($q['option_' . strtolower($answer)] ?? '') === '') {
                    if (!$expired) throw new InvalidArgumentException('Jawab semua soal sebelum mengirimkan tes.');
                    $answer = null;
                }
                $isCorrect = $answer !== null && $answer === $q['answer_key'];
                $correct += (int)$isCorrect;
                $review[] = ['question' => $q['question'], 'section' => $q['section'], 'selected_option' => $answer, 'selected_text' => $answer === null ? '' : $q['option_' . strtolower($answer)], 'correct' => $isCorrect];
            }
            $total = count($list); $score = round($correct / $total * 100, 2);
            $stmt = $pdo->prepare('INSERT INTO submissions (submission_token, participant_id, name, email, nik, position, correct_count, total_questions, score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$token ?? $_SESSION['submission_token'], $candidate['id'], $candidate['name'], $candidate['email'], $candidate['nik'], $candidate['position'], $correct, $total, $score]);
            $submissionId = $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO answers (submission_id, question_id, question_text, selected_option, selected_text, is_correct) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($list as $i => $q) $stmt->execute([$submissionId, $q['id'], $review[$i]['question'], $review[$i]['selected_option'], $review[$i]['selected_text'], (int)$review[$i]['correct']]);
            $pdo->prepare('DELETE FROM draft_answers WHERE participant_id = ?')->execute([$candidate['id']]);
        }
        $pdo->commit();
        $result = ['name' => $candidate['name'], 'position' => $candidate['position'], 'correct' => $correct, 'total' => $total, 'score' => $score, 'review' => $review];
        if ($saveSession) {
            $_SESSION['completed'] = $result;
            unset($_SESSION['candidate'], $_SESSION['question_ids'], $_SESSION['submission_token']);
        }
        return $result;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}
