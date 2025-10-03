<?php
// *** แก้ค่าตามเครื่องคุณ ***
const DB_HOST = 'localhost';
const DB_NAME = 's67160316';
const DB_USER = 's67160316';
const DB_PASS = 'kBjc9BDx';
const DB_CHARSET = 'utf8mb4';

// ตั้งค่า session ให้ปลอดภัยขึ้น
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
  ]);
  session_start();
}
// ใน config_mysqli.php หรือไฟล์เริ่มต้นอื่นๆ
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net;");
// ให้ mysqli โยน exception เวลา error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $mysqli->set_charset(DB_CHARSET);
} catch (Throwable $e) {
  http_response_code(500);
  exit('Database connection failed.');
}
