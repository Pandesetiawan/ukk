<?php
session_start();
include '../config.php';

// Cek login admin
if (!isset($_SESSION['login']) || ($_SESSION['role'] ?? '') != 'admin') {
    die("Akses ditolak. Silakan login sebagai admin.");
}

// Tambah buku
if (isset($_POST['tambah'])) {
    $judul     = mysqli_real_escape_string($conn, $_POST['judul']);
    $pengarang = mysqli_real_escape_string($conn, $_POST['pengarang']);
    $penerbit  = mysqli_real_escape_string($conn, $_POST['penerbit']);
    $tahun     = (int)$_POST['tahun'];
    $stok      = (int)$_POST['stok'];

    mysqli_query($conn, "INSERT INTO buku (judul,pengarang,penerbit,tahun,stok)
                         VALUES ('$judul','$pengarang','$penerbit','$tahun','$stok')");
    header("Location: kelolabuku.php");
    exit;
}

// Hapus buku
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM buku WHERE id_buku=$id");
    header("Location: kelolabuku.php");
    exit;
}

$result = mysqli_query($conn, "SELECT * FROM buku ORDER BY id_buku DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Buku</title>
    <style>
        body { font-family: Arial; padding:40px; background:#f4f6f9; }
        h2 { margin-bottom:20px; }
        table { border-collapse: collapse; width:100%; background:white; }
        th, td { padding:10px; border:1px solid #ddd; text-align:left; }
        th { background:#2563eb; color:white; }
        form { margin-bottom:20px; background:white; padding:20px; }
        input { width:100%; padding:8px; margin-bottom:10px; }
        button { padding:8px 14px; background:#2563eb; color:white; border:none; cursor:pointer; }
        a.hapus { color:red; text-decoration:none; }
    </style>
</head>
<body>

<h2>Kelola Buku</h2>

<form method="POST">
    <input type="text" name="judul" placeholder="Judul Buku" required>
    <input type="text" name="pengarang" placeholder="Pengarang">
    <input type="text" name="penerbit" placeholder="Penerbit">
    <input type="number" name="tahun" placeholder="Tahun">
    <input type="number" name="stok" placeholder="Stok" required>
    <button type="submit" name="tambah">Tambah Buku</button>
</form>

<table>
    <tr>
        <th>No</th>
        <th>Judul</th>
        <th>Pengarang</th>
        <th>Penerbit</th>
        <th>Tahun</th>
        <th>Stok</th>
        <th>Aksi</th>
    </tr>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['judul']) ?></td>
            <td><?= htmlspecialchars($row['pengarang']) ?></td>
            <td><?= htmlspecialchars($row['penerbit']) ?></td>
            <td><?= $row['tahun'] ?></td>
            <td><?= $row['stok'] ?></td>
            <td>
                <a class="hapus" href="?hapus=<?= $row['id_buku'] ?>"
                   onclick="return confirm('Yakin hapus buku ini?')">
                   Hapus
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" style="text-align:center;">Belum ada data buku</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>