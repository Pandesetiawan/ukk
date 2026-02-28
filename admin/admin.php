<?php
session_start();
include '../config.php';

// PROTEKSI ADMIN
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// TAMBAH DATA
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = "anggota";

    mysqli_query($conn, "INSERT INTO users (nama, email, password, role) 
                         VALUES ('$nama','$email','$password','$role')");
}

// HAPUS DATA
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM users WHERE id=$id");
}

// AMBIL DATA
$data = mysqli_query($conn, "SELECT * FROM users WHERE role='anggota'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Anggota</title>
</head>
<body>

<h2>Kelola Anggota</h2>

<!-- FORM TAMBAH -->
<form method="POST">
    <input type="text" name="nama" placeholder="Nama" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit" name="tambah">Tambah</button>
</form>

<br><hr><br>

<!-- TABEL DATA -->
<table border="1" cellpadding="10">
    <tr>
        <th>No</th>
        <th>Nama</th>
        <th>Email</th>
        <th>Aksi</th>
    </tr>

    <?php
    $no = 1;
    while ($row = mysqli_fetch_assoc($data)) {
    ?>
    <tr>
        <td><?= $no++; ?></td>
        <td><?= $row['nama']; ?></td>
        <td><?= $row['email']; ?></td>
        <td>
            <a href="?hapus=<?= $row['id']; ?>" 
               onclick="return confirm('Yakin hapus?')">
               Hapus
            </a>
        </td>
    </tr>
    <?php } ?>
</table>

</body>
</html>