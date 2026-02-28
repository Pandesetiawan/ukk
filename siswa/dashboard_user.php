<?php
include "database.php";
session_start();

// Cek login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php"); exit;
}

$id_user  = $_SESSION['id_user'];
$username = $_SESSION['username'];

// =============================================
// AKSI PINJAM BUKU
// =============================================
if (isset($_POST['pinjam'])) {
    $id_buku = (int)$_POST['id_buku'];

    // Cek apakah user sudah pinjam buku yang sama dan belum dikembalikan
    $cek = mysqli_prepare($conn, "SELECT id FROM peminjaman WHERE id_user = ? AND id_buku = ? AND status = 'dipinjam'");
    mysqli_stmt_bind_param($cek, "ii", $id_user, $id_buku);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        $pesan = ["type" => "error", "text" => "Kamu sudah meminjam buku ini!"];
    } else {
        // Cek stok buku
        $stok_q = mysqli_prepare($conn, "SELECT stok FROM buku WHERE id_buku = ?");
        mysqli_stmt_bind_param($stok_q, "i", $id_buku);
        mysqli_stmt_execute($stok_q);
        $stok_r = mysqli_stmt_get_result($stok_q);
        $stok_d = mysqli_fetch_assoc($stok_r);

        if ($stok_d && $stok_d['stok'] > 0) {
            // Tambah peminjaman
            $tgl_pinjam  = date('Y-m-d');
            $tgl_kembali = date('Y-m-d', strtotime('+7 days'));

            $ins = mysqli_prepare($conn, "INSERT INTO peminjaman (id_user, id_buku, tgl_pinjam, tgl_kembali, status) VALUES (?, ?, ?, ?, 'dipinjam')");
            mysqli_stmt_bind_param($ins, "iiss", $id_user, $id_buku, $tgl_pinjam, $tgl_kembali);
            mysqli_stmt_execute($ins);

            // Kurangi stok
            mysqli_query($conn, "UPDATE buku SET stok = stok - 1 WHERE id_buku = $id_buku");

            $pesan = ["type" => "success", "text" => "Berhasil meminjam buku! Batas kembali: $tgl_kembali"];
        } else {
            $pesan = ["type" => "error", "text" => "Stok buku habis!"];
        }
    }
}

// =============================================
// AKSI KEMBALIKAN BUKU
// =============================================
if (isset($_POST['kembali'])) {
    $id_pinjam = (int)$_POST['id_pinjam'];
    $id_buku   = (int)$_POST['id_buku'];

    $upd = mysqli_prepare($conn, "UPDATE peminjaman SET status = 'dikembalikan', tgl_aktual_kembali = ? WHERE id = ? AND id_user = ?");
    $tgl_hari_ini = date('Y-m-d');
    mysqli_stmt_bind_param($upd, "sii", $tgl_hari_ini, $id_pinjam, $id_user);
    mysqli_stmt_execute($upd);

    // Tambah stok kembali
    mysqli_query($conn, "UPDATE buku SET stok = stok + 1 WHERE id_buku = $id_buku");

    $pesan = ["type" => "success", "text" => "Buku berhasil dikembalikan!"];
}

// =============================================
// AMBIL DATA
// =============================================

// Daftar semua buku
$buku_list = mysqli_query($conn, "SELECT * FROM buku ORDER BY judul ASC");

// Buku yang sedang dipinjam user ini
$pinjam_aktif = mysqli_query($conn, "
    SELECT p.id, p.tgl_pinjam, p.tgl_kembali, b.judul, b.penulis, b.id_buku,
           DATEDIFF(p.tgl_kembali, CURDATE()) as sisa_hari
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user = $id_user AND p.status = 'dipinjam'
    ORDER BY p.tgl_kembali ASC
");

// Riwayat peminjaman
$riwayat = mysqli_query($conn, "
    SELECT p.tgl_pinjam, p.tgl_kembali, p.tgl_aktual_kembali, p.status, b.judul, b.penulis
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user = $id_user AND p.status = 'dikembalikan'
    ORDER BY p.tgl_aktual_kembali DESC
    LIMIT 10
");

// Hitung statistik
$total_pinjam  = mysqli_num_rows($pinjam_aktif);
$total_riwayat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE id_user = $id_user AND status = 'dikembalikan'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LibraryPro</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f5ff;
            color: #333;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: #1e3a8a;
            color: white;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .navbar .brand { font-size: 1.2rem; font-weight: bold; }
        .navbar .user-info { display: flex; align-items: center; gap: 14px; font-size: 0.88rem; }
        .navbar .user-info span { opacity: 0.85; }
        .btn-logout {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.82rem;
            text-decoration: none;
        }
        .btn-logout:hover { background: #dc2626; }

        /* ── TAB MENU ── */
        .tabs {
            display: flex;
            gap: 6px;
            padding: 20px 28px 0;
            border-bottom: 2px solid #dde4f5;
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            font-weight: 700;
        }
        .tab-btn:hover { color: #2563eb; }

        /* ── KONTEN UTAMA ── */
        .container { padding: 24px 28px; max-width: 1100px; margin: 0 auto; }

        /* ── KARTU STATISTIK ── */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card .icon { font-size: 2rem; margin-bottom: 8px; }
        .stat-card .number { font-size: 1.8rem; font-weight: bold; color: #1e3a8a; }
        .stat-card .label { font-size: 0.8rem; color: #888; margin-top: 4px; }

        /* ── PESAN NOTIFIKASI ── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── SECTION ── */
        .section { display: none; }
        .section.active { display: block; }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e7ff;
        }

        /* ── GRID BUKU ── */
        .buku-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        .buku-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .buku-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
        .buku-card .cover {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 8px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
        .buku-card .judul { font-weight: 700; font-size: 0.9rem; color: #1e3a8a; line-height: 1.3; }
        .buku-card .penulis { font-size: 0.78rem; color: #666; }
        .buku-card .stok {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            width: fit-content;
        }
        .stok.ada    { background: #d1fae5; color: #065f46; }
        .stok.habis  { background: #fee2e2; color: #991b1b; }

        /* ── TOMBOL ── */
        .btn-pinjam {
            width: 100%;
            padding: 8px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            margin-top: auto;
        }
        .btn-pinjam:hover   { background: #1d4ed8; }
        .btn-pinjam:disabled { background: #cbd5e1; cursor: not-allowed; }

        /* ── TABEL ── */
        .tabel-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        th {
            background: #1e3a8a;
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.85rem;
        }
        td {
            padding: 11px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8faff; }

        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge.dipinjam     { background: #fef3c7; color: #92400e; }
        .badge.dikembalikan { background: #d1fae5; color: #065f46; }
        .badge.telat        { background: #fee2e2; color: #991b1b; }

        .btn-kembali {
            padding: 5px 12px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .btn-kembali:hover { background: #059669; }

        .kosong {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 0.9rem;
        }
        .kosong .icon { font-size: 3rem; display: block; margin-bottom: 8px; }

        /* Search */
        .search-box {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }
        .search-box:focus { outline: none; border-color: #2563eb; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="brand">📚 LibraryPro</div>
    <div class="user-info">
        <span>👤 <?= htmlspecialchars($username) ?></span>
        <a href="logout.php" class="btn-logout">Keluar</a>
    </div>
</div>

<!-- TAB MENU -->
<div class="tabs">
    <button class="tab-btn active" onclick="bukaTab('beranda')">🏠 Beranda</button>
    <button class="tab-btn"       onclick="bukaTab('daftar-buku')">📖 Daftar Buku</button>
    <button class="tab-btn"       onclick="bukaTab('pinjaman-saya')">📋 Pinjaman Saya</button>
    <button class="tab-btn"       onclick="bukaTab('riwayat')">🕓 Riwayat</button>
</div>

<div class="container">

    <?php if (!empty($pesan)): ?>
        <div class="alert <?= $pesan['type'] ?>">
            <?= $pesan['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($pesan['text']) ?>
        </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════
         TAB 1: BERANDA
    ══════════════════════════════════ -->
    <div id="beranda" class="section active">
        <div class="stats">
            <div class="stat-card">
                <div class="icon">📖</div>
                <div class="number"><?= $total_pinjam ?></div>
                <div class="label">Sedang Dipinjam</div>
            </div>
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="number"><?= $total_riwayat['total'] ?></div>
                <div class="label">Total Dikembalikan</div>
            </div>
            <div class="stat-card">
                <div class="icon">📚</div>
                <div class="number"><?= mysqli_num_rows($buku_list) ?></div>
                <div class="label">Total Koleksi Buku</div>
            </div>
        </div>

        <p class="section-title">Selamat datang, <?= htmlspecialchars($username) ?>! 👋</p>
        <p style="color:#666; font-size:0.9rem;">Gunakan menu di atas untuk meminjam buku atau melihat riwayat pinjaman kamu.</p>

        <?php if ($total_pinjam > 0): ?>
        <br>
        <p class="section-title">📋 Pinjaman Aktif Kamu</p>
        <div class="tabel-wrap">
        <table>
            <thead><tr><th>Judul</th><th>Penulis</th><th>Batas Kembali</th><th>Sisa Hari</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php
            mysqli_data_seek($pinjam_aktif, 0);
            while ($p = mysqli_fetch_assoc($pinjam_aktif)):
                $sisa = $p['sisa_hari'];
                $warna = $sisa < 0 ? 'telat' : ($sisa <= 2 ? 'dipinjam' : 'dikembalikan');
                $label = $sisa < 0 ? "Telat $sisa hari" : ($sisa == 0 ? "Hari ini!" : "$sisa hari lagi");
            ?>
            <tr>
                <td><b><?= htmlspecialchars($p['judul']) ?></b></td>
                <td><?= htmlspecialchars($p['penulis']) ?></td>
                <td><?= $p['tgl_kembali'] ?></td>
                <td><span class="badge <?= $warna ?>"><?= $label ?></span></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id_pinjam" value="<?= $p['id'] ?>">
                        <input type="hidden" name="id_buku"   value="<?= $p['id_buku'] ?>">
                        <button type="submit" name="kembali" class="btn-kembali"
                                onclick="return confirm('Kembalikan buku ini?')">
                            Kembalikan
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════
         TAB 2: DAFTAR BUKU
    ══════════════════════════════════ -->
    <div id="daftar-buku" class="section">
        <p class="section-title">📖 Koleksi Buku</p>
        <input type="text" class="search-box" id="searchBuku" placeholder="🔍 Cari judul atau penulis..." onkeyup="cariБuku()">
        <div class="buku-grid" id="bukuGrid">
            <?php
            mysqli_data_seek($buku_list, 0);
            while ($b = mysqli_fetch_assoc($buku_list)):
                $ada = $b['stok'] > 0;
            ?>
            <div class="buku-card" data-judul="<?= strtolower($b['judul']) ?>" data-penulis="<?= strtolower($b['penulis']) ?>">
                <div class="cover">📗</div>
                <div class="judul"><?= htmlspecialchars($b['judul']) ?></div>
                <div class="penulis">✍️ <?= htmlspecialchars($b['penulis']) ?></div>
                <span class="stok <?= $ada ? 'ada' : 'habis' ?>">
                    <?= $ada ? "✅ Stok: {$b['stok']}" : "❌ Stok Habis" ?>
                </span>
                <form method="POST">
                    <input type="hidden" name="id_buku" value="<?= $b['id_buku'] ?>">
                    <button type="submit" name="pinjam" class="btn-pinjam"
                            <?= !$ada ? 'disabled' : '' ?>
                            <?= $ada ? "onclick=\"return confirm('Pinjam buku ini?')\"" : '' ?>>
                        <?= $ada ? '📥 Pinjam Buku' : 'Tidak Tersedia' ?>
                    </button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════
         TAB 3: PINJAMAN SAYA
    ══════════════════════════════════ -->
    <div id="pinjaman-saya" class="section">
        <p class="section-title">📋 Buku yang Sedang Dipinjam</p>
        <?php
        mysqli_data_seek($pinjam_aktif, 0);
        $ada_pinjaman = mysqli_num_rows($pinjam_aktif) > 0;
        ?>
        <?php if ($ada_pinjaman): ?>
        <div class="tabel-wrap">
        <table>
            <thead><tr><th>Judul</th><th>Penulis</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php while ($p = mysqli_fetch_assoc($pinjam_aktif)):
                $sisa = $p['sisa_hari'];
                $warna = $sisa < 0 ? 'telat' : 'dipinjam';
                $label = $sisa < 0 ? "⚠️ Telat " . abs($sisa) . " hari" : ($sisa == 0 ? "⚡ Hari ini!" : "🕓 $sisa hari lagi");
            ?>
            <tr>
                <td><b><?= htmlspecialchars($p['judul']) ?></b></td>
                <td><?= htmlspecialchars($p['penulis']) ?></td>
                <td><?= $p['tgl_pinjam'] ?></td>
                <td><?= $p['tgl_kembali'] ?></td>
                <td><span class="badge <?= $warna ?>"><?= $label ?></span></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="id_pinjam" value="<?= $p['id'] ?>">
                        <input type="hidden" name="id_buku"   value="<?= $p['id_buku'] ?>">
                        <button type="submit" name="kembali" class="btn-kembali"
                                onclick="return confirm('Kembalikan buku ini sekarang?')">
                            ↩️ Kembalikan
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <div class="kosong"><span class="icon">📭</span>Kamu belum meminjam buku apapun.</div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════
         TAB 4: RIWAYAT
    ══════════════════════════════════ -->
    <div id="riwayat" class="section">
        <p class="section-title">🕓 Riwayat Peminjaman</p>
        <?php $ada_riwayat = mysqli_num_rows($riwayat) > 0; ?>
        <?php if ($ada_riwayat): ?>
        <div class="tabel-wrap">
        <table>
            <thead><tr><th>Judul</th><th>Penulis</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Dikembalikan</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($r = mysqli_fetch_assoc($riwayat)):
                $telat = strtotime($r['tgl_aktual_kembali']) > strtotime($r['tgl_kembali']);
            ?>
            <tr>
                <td><b><?= htmlspecialchars($r['judul']) ?></b></td>
                <td><?= htmlspecialchars($r['penulis']) ?></td>
                <td><?= $r['tgl_pinjam'] ?></td>
                <td><?= $r['tgl_kembali'] ?></td>
                <td><?= $r['tgl_aktual_kembali'] ?></td>
                <td>
                    <span class="badge <?= $telat ? 'telat' : 'dikembalikan' ?>">
                        <?= $telat ? '⚠️ Telat' : '✅ Tepat Waktu' ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <div class="kosong"><span class="icon">📜</span>Belum ada riwayat peminjaman.</div>
        <?php endif; ?>
    </div>

</div><!-- end container -->

<script>
    function bukaTab(nama) {
        // Sembunyikan semua section
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

        // Tampilkan yang dipilih
        document.getElementById(nama).classList.add('active');
        event.target.classList.add('active');
    }

    function cariБuku() {
        var keyword = document.getElementById('searchBuku').value.toLowerCase();
        document.querySelectorAll('.buku-card').forEach(function(card) {
            var judul   = card.getAttribute('data-judul');
            var penulis = card.getAttribute('data-penulis');
            card.style.display = (judul.includes(keyword) || penulis.includes(keyword)) ? 'flex' : 'none';
        });
    }
</script>
</body>
</html>