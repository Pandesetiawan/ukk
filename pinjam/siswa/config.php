<?php
// C:\laragon\www\perpus\config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'perpus');

// Menggunakan gaya Object-Oriented agar cocok dengan register.php kamu
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Koneksi gagal ke database " . DB_NAME . ": " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>