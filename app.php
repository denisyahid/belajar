<?php
declare(strict_types=1);
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
    $stmt = db()->prepare('SELECT * FROM questions WHERE position = ? ORDER BY sort_order');
    $stmt->execute([$position]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function check_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) { http_response_code(403); exit('Sesi tidak valid. Muat ulang halaman dan coba lagi.'); }
}
