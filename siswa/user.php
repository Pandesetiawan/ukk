<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit;
}
?>

<h1>Dashboard User</h1>

<h1>Halo, <b><?= $_SESSION['nama']; ?></b></h1>

<ul>
    <li>Lihat Buku</li>
    <li>Pinjam Buku</li>
    <li><a href="../logout.php">Logout</a></li>
</ul>
