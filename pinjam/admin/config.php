<?php
// koneksi.php - Konfigurasi Database

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Ganti dengan username MySQL Anda
define('DB_PASS', '');            // Ganti dengan password MySQL Anda
define('DB_NAME', 'perpus');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>