<?php
// 1. Koneksi ke Database (Nama DB sesuai file .sql Anda: perpus)
$conn = mysqli_connect("localhost", "root", "", "perpus");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// 2. Query SQL yang disesuaikan dengan file perpus(2).sql
// Kita mengambil data dari peminjaman, nama dari users, dan judul dari buku
$sql = "SELECT 
            p.id_peminjaman, 
            u.nama AS nama_peminjam, 
            b.judul AS judul_buku, 
            p.tanggal_pinjam, 
            p.tanggal_jatuh_tempo, 
            p.status 
        FROM peminjaman p
        JOIN users u ON p.id_anggota = u.id_user
        JOIN buku b ON p.id_buku = b.id_buku
        ORDER BY p.tanggal_pinjam DESC";

$result = mysqli_query($conn, $sql);
$hari_ini = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Admin - Perpustakaan</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .box { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #3498db; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ddd; font-size: 14px; }
        tr:hover { background-color: #f1f1f1; }
        
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-dipinjam { background-color: #f39c12; color: white; }
        .badge-selesai { background-color: #27ae60; color: white; }
        .terlambat { color: #e74c3c; font-weight: bold; }
        .aman { color: #7f8c8d; }
    </style>
</head>
<body>

<div class="box">
    <h2>Laporan Monitoring Peminjaman Buku</h2>
    <p>Status Data Per: <strong><?php echo date('d-m-Y'); ?></strong></p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama User</th>
                <th>Judul Buku</th>
                <th>Tgl Pinjam</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <?php 
                        // Cek keterlambatan (Hanya jika status masih 'dipinjam')
                        $jatuh_tempo = $row['tanggal_jatuh_tempo'];
                        $is_telat = ($hari_ini > $jatuh_tempo && $row['status'] == 'dipinjam');
                    ?>
                    <tr>
                        <td>#<?php echo $row['id_peminjaman']; ?></td>
                        <td><strong><?php echo $row['nama_peminjam']; ?></strong></td>
                        <td><?php echo $row['judul_buku']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($jatuh_tempo)); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $row['status']; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($is_telat): ?>
                                <span class="terlambat">⚠️ Lewat Jadwal!</span>
                            <?php elseif($row['status'] == 'selesai'): ?>
                                <span style="color: green;">✓ Dikembalikan</span>
                            <?php else: ?>
                                <span class="aman">Masa Pinjam</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada data peminjaman ditemukan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>