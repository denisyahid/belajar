<?php
// Salin sebagai config.php dalam folder aplikasi, dan isi rahasia secara privat.
// Pastikan .htaccess terunggah dan melindungi file ini.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; }
return [
    'host' => 'sql104.infinityfree.com',
    'port' => '3306',
    'database' => 'if0_43370700_kemnaker',
    'username' => 'if0_43370700',
    'password' => '', // Isi kata sandi database di server, jangan commit config.php.
    'twk_is_example' => true, // Ubah ke false hanya setelah soal final mentor diimpor dan divalidasi.
    'admin_username' => 'rsudmalangbonggarut@gmail.com',
    'admin_password_hash' => '', // Hasil password_hash(), bukan kata sandi biasa.
];
