<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../config.php';

// Proteksi admin
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// ===== AMBIL DATA =====
function hitung($conn, $query) {
    $r = mysqli_query($conn, $query);
    if ($r && mysqli_num_rows($r) > 0) {
        return mysqli_fetch_row($r)[0];
    }
    return 0;
}

$total_buku     = hitung($conn, "SELECT COUNT(*) FROM buku");
$total_anggota  = hitung($conn, "SELECT COUNT(*) FROM users WHERE role='user'");
$total_pinjam   = hitung($conn, "SELECT COUNT(*) FROM peminjaman WHERE status='dipinjam'");
$total_selesai  = hitung($conn, "SELECT COUNT(*) FROM peminjaman WHERE status='selesai'");

$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$today = date('d-m-Y');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Admin</title>
</head>
<body>

<h2>Dashboard Admin</h2>
<p>Halo, <b><?= $admin_name ?></b></p>
<p>Tanggal: <?= $today ?></p>

<hr>

<h3>Statistik Perpustakaan</h3>

<ul>
    <li>Total Buku: <b><?= $total_buku ?></b></li>
    <li>Total Anggota: <b><?= $total_anggota ?></b></li>
    <li>Sedang Dipinjam: <b><?= $total_pinjam ?></b></li>
    <li>Selesai: <b><?= $total_selesai ?></b></li>
</ul>

<hr>

<h3>Menu</h3>

<ul>
    <li><a href="kelolabuku.php">Kelola Buku</a></li>
    <li><a href="kelola_anggota.php">Kelola Anggota</a></li>
    <li><a href="peminjaman.php">Peminjaman</a></li>
    <li><a href="../logout.php">Logout</a></li>
</ul>

</body>
</html>