<?php
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; }
ini_set('session.gc_maxlifetime', '7200');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, private');
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url = 'index.php'): void { header('Location: ' . $url, true, 303); exit; }
function config(): array {
    static $config;
    if ($config === null) $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    return $config;
}
function db(): PDO {
    static $db = null;
    if ($db === null) {
        $config = config();
        $host = getenv('DB_HOST') ?: ($config['host'] ?? 'sql104.infinityfree.com');
        $port = getenv('DB_PORT') ?: ($config['port'] ?? '3306');
        $name = getenv('DB_NAME') ?: ($config['database'] ?? 'if0_43370700_kemnaker');
        $user = getenv('DB_USER') ?: ($config['username'] ?? 'if0_43370700');
        $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? '');
        $db = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}
function questions(string $position): array {
    $stmt = db()->prepare('SELECT q.*, qi.image_name FROM questions q LEFT JOIN question_images qi ON qi.question_id = q.id WHERE q.position = ? ORDER BY q.sort_order');
    $stmt->execute([$position]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function check_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) { http_response_code(403); exit('Sesi tidak valid. Muat ulang halaman dan coba lagi.'); }
}

// Paket soal hanya dibuka jika struktur 20 TWK + 30 SKD lengkap.
function exam_is_ready(array $questions): bool {
    if (count($questions) !== 50) return false;
    foreach ($questions as $i => $question) {
        if (($question['section'] ?? '') !== ($i < 20 ? 'TWK' : 'SKD')) return false;
    }
    return true;
}
