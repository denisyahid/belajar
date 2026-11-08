<?php
// Router untuk server pengembangan bawaan PHP. Hosting Apache memakai .htaccess.
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$allowed = ['/', '/index.php', '/admin.php', '/save_answer.php', '/app.js', '/style.css', '/logo-kemnaker.png', '/logo-rsud-malangbong.png', '/soal-radiografer-siku.jpg'];
if (!in_array($path, $allowed, true)) { http_response_code(404); exit('Tidak ditemukan.'); }
return false;
