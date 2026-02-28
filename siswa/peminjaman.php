<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php"); exit;
}
include 'config.php';

/*
 * Struktur tabel peminjaman:
 *   id_peminjaman, id_anggota, tanggal_pinjam, tanggal_jatuh_tempo,
 *   status ENUM('dipinjam','selesai')
 *
 * Kolom tabel buku & anggota dideteksi otomatis.
 */

// ── Auto-deteksi kolom tabel buku ───────────────────────────────────
$cols_buku = [];
$r = mysqli_query($conn, "SHOW COLUMNS FROM buku");
while ($c = mysqli_fetch_assoc($r)) $cols_buku[] = $c['Field'];
$pk_buku   = in_array('id_buku', $cols_buku) ? 'id_buku' : (in_array('id', $cols_buku) ? 'id' : $cols_buku[0]);
$col_judul = in_array('judul', $cols_buku) ? 'judul' : (in_array('judul_buku', $cols_buku) ? 'judul_buku' : $cols_buku[1]);
$col_stok  = in_array('stok', $cols_buku) ? 'stok' : (in_array('stok_buku', $cols_buku) ? 'stok_buku' : (in_array('jumlah_stok', $cols_buku) ? 'jumlah_stok' : $cols_buku[2]));

// ── Auto-deteksi kolom tabel anggota ────────────────────────────────
$cols_ang = [];
$r2 = mysqli_query($conn, "SHOW COLUMNS FROM anggota");
while ($c = mysqli_fetch_assoc($r2)) $cols_ang[] = $c['Field'];
$pk_ang     = in_array('id_anggota', $cols_ang) ? 'id_anggota' : (in_array('id', $cols_ang) ? 'id' : $cols_ang[0]);
$col_nama   = in_array('nama', $cols_ang) ? 'nama' : (in_array('nama_anggota', $cols_ang) ? 'nama_anggota' : $cols_ang[1]);
$col_no_ang = in_array('no_anggota', $cols_ang) ? 'no_anggota' : (in_array('nomor_anggota', $cols_ang) ? 'nomor_anggota' : (in_array('kode_anggota', $cols_ang) ? 'kode_anggota' : $cols_ang[2]));

// ── Kolom peminjaman (SUDAH PASTI dari struktur tabel) ───────────────
// id_peminjaman | id_anggota | tanggal_pinjam | tanggal_jatuh_tempo | status(dipinjam/selesai)
$pk_p          = 'id_peminjaman';
$fk_ang_p      = 'id_anggota';
$fk_buku_p     = in_array('id_buku', mysqli_fetch_all(mysqli_query($conn, "SHOW COLUMNS FROM peminjaman"), MYSQLI_ASSOC)
                   ? array_column(mysqli_fetch_all(mysqli_query($conn, "SHOW COLUMNS FROM peminjaman"), MYSQLI_ASSOC), 'Field')
                   : ['id_buku']) ? 'id_buku' : 'id_buku';
// Deteksi FK buku di peminjaman
$cols_p = array_column(mysqli_fetch_all(mysqli_query($conn, "SHOW COLUMNS FROM peminjaman"), MYSQLI_ASSOC), 'Field');
$fk_buku_p = in_array('id_buku', $cols_p) ? 'id_buku' : (in_array($pk_buku, $cols_p) ? $pk_buku : 'id_buku');

// ── Dropdown data ────────────────────────────────────────────────────
$buku_list    = mysqli_query($conn, "SELECT `$pk_buku`, `$col_judul`, `$col_stok` FROM buku WHERE `$col_stok` > 0 ORDER BY `$col_judul`");
$anggota_list = mysqli_query($conn, "SELECT `$pk_ang`, `$col_nama`, `$col_no_ang` FROM anggota ORDER BY `$col_nama`");

// ── Proses form ──────────────────────────────────────────────────────
$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'tambah') {
        $id_anggota       = (int)$_POST['id_anggota'];
        $id_buku          = (int)$_POST['id_buku'];
        $tanggal_pinjam   = mysqli_real_escape_string($conn, $_POST['tgl_pinjam']);
        $tanggal_jatuh    = mysqli_real_escape_string($conn, $_POST['tgl_kembali']);

        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT `$col_stok` FROM buku WHERE `$pk_buku`=$id_buku"));
        if ($cek && $cek[$col_stok] > 0) {
            mysqli_query($conn, "INSERT INTO peminjaman (`$fk_ang_p`, `$fk_buku_p`, tanggal_pinjam, tanggal_jatuh_tempo, status)
                VALUES ($id_anggota, $id_buku, '$tanggal_pinjam', '$tanggal_jatuh', 'dipinjam')");
            mysqli_query($conn, "UPDATE buku SET `$col_stok` = `$col_stok` - 1 WHERE `$pk_buku` = $id_buku");
            $msg = "✅ Peminjaman berhasil ditambahkan.";
            $msg_type = 'success';
        } else {
            $msg = "❌ Stok buku habis.";
            $msg_type = 'error';
        }
    } elseif ($_POST['action'] === 'kembali') {
        $id_p    = (int)$_POST['id'];
        $id_buku = (int)$_POST['id_buku'];
        mysqli_query($conn, "UPDATE peminjaman SET status='selesai' WHERE id_peminjaman=$id_p");
        mysqli_query($conn, "UPDATE buku SET `$col_stok` = `$col_stok` + 1 WHERE `$pk_buku`=$id_buku");
        $msg = "✅ Buku berhasil dikembalikan.";
        $msg_type = 'success';
    }
}

// ── Query data peminjaman ────────────────────────────────────────────
$keyword       = isset($_GET['q'])      ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE 1=1";
if ($keyword)       $where .= " AND (a.`$col_nama` LIKE '%$keyword%' OR b.`$col_judul` LIKE '%$keyword%')";
if ($filter_status) $where .= " AND p.status='$filter_status'";

$peminjaman = mysqli_query($conn, "
    SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
           a.`$col_nama` AS nama_anggota, a.`$col_no_ang` AS no_anggota,
           b.`$col_judul` AS judul_buku, b.`$pk_buku` AS buku_id
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.`$pk_ang`
    JOIN buku b    ON p.`$fk_buku_p` = b.`$pk_buku`
    $where
    ORDER BY p.id_peminjaman DESC
    LIMIT 50
");

$total_dipinjam  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM peminjaman WHERE status='dipinjam'"))['c'];
$total_kembali   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM peminjaman WHERE status='selesai'"))['c'];
$total_terlambat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM peminjaman WHERE status='dipinjam' AND tanggal_jatuh_tempo < CURDATE()"))['c'];
?>
?>
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peminjaman Buku — LibraryPro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Lora:ital,wght@0,600;0,700;1,500&display=swap" rel="stylesheet">
<style>
:root {
  --bg:         #f0f5ff;
  --white:      #ffffff;
  --s1:         #f7f9ff;
  --blue:       #2563eb;
  --blue-mid:   #3b82f6;
  --blue-light: #60a5fa;
  --blue-pale:  #dbeafe;
  --blue-xpale: #eff6ff;
  --blue-glow:  rgba(37,99,235,0.15);
  --navy:       #1e3a8a;
  --green:      #16a34a;
  --green-pale: #dcfce7;
  --red:        #dc2626;
  --red-pale:   #fee2e2;
  --amber:      #d97706;
  --amber-pale: #fef3c7;
  --text:       #0f172a;
  --muted:      #64748b;
  --dim:        #94a3b8;
  --border:     #e2e8f0;
  --border-b:   rgba(37,99,235,0.2);
  --sw:         265px;
  --r:          16px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 60% 50% at 0% 0%, rgba(37,99,235,0.06) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 100% 100%, rgba(96,165,250,0.07) 0%, transparent 55%);
  pointer-events: none; z-index: 0;
}

/* ═══ SIDEBAR ═══ */
.sidebar {
  width: var(--sw);
  background: var(--navy);
  position: fixed; top:0; left:0; height:100vh;
  overflow-y: auto; scrollbar-width: none;
  display: flex; flex-direction: column;
  z-index: 200;
}
.sidebar::-webkit-scrollbar { display: none; }
.sidebar::before {
  content: '';
  position: absolute; top:0; right:0;
  width: 1px; height: 100%;
  background: linear-gradient(to bottom, transparent, rgba(96,165,250,0.3) 40%, transparent);
}
.sb-top {
  padding: 30px 22px 26px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.sb-logo {
  display: flex; align-items: center; gap: 11px;
  margin-bottom: 28px;
}
.sb-logo-icon {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, var(--blue-mid), var(--blue-light));
  border-radius: 11px;
  display: grid; place-items: center;
  font-size: 1.05rem; flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(59,130,246,0.4);
}
.sb-logo-text {
  font-family: 'Lora', serif;
  font-size: 1.3rem; font-weight: 700;
  color: #fff; letter-spacing: -0.3px;
}
.sb-logo-text span { color: var(--blue-light); font-style: italic; }
.sb-profile {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 16px;
  display: flex; align-items: center; gap: 12px;
}
.sb-avatar {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, var(--blue-mid), var(--blue-light));
  border-radius: 50%;
  display: grid; place-items: center;
  font-size: 1rem; flex-shrink: 0;
  border: 2px solid rgba(96,165,250,0.4);
}
.sb-name { font-size: 0.875rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-badge {
  font-size: 0.67rem; font-weight: 600;
  letter-spacing: 0.8px; text-transform: uppercase;
  color: var(--blue-light);
  display: flex; align-items: center; gap: 5px; margin-top: 3px;
}
.sb-badge::before { content: ''; width: 5px; height: 5px; background: #4ade80; border-radius: 50%; box-shadow: 0 0 6px #4ade80; }
.sb-nav { padding: 22px 18px; flex: 1; }
.sb-label {
  font-size: 0.63rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 1.5px;
  color: rgba(255,255,255,0.25);
  padding: 0 8px; margin: 22px 0 8px; display: block;
}
.sb-label:first-child { margin-top: 0; }
.sb-ul { list-style: none; }
.sb-ul li a {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 11px;
  font-size: 0.855rem; font-weight: 500;
  color: rgba(255,255,255,0.5); text-decoration: none;
  transition: all 0.18s; margin-bottom: 2px;
}
.sb-ul li a:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
.sb-ul li a.active { background: var(--blue-mid); color: #fff; font-weight: 600; box-shadow: 0 4px 14px rgba(59,130,246,0.4); }
.sb-icon { width: 32px; height: 32px; background: rgba(255,255,255,0.07); border-radius: 8px; display: grid; place-items: center; font-size: 0.9rem; flex-shrink: 0; transition: background 0.18s; }
.sb-ul li a.active .sb-icon { background: rgba(255,255,255,0.2); }
.sb-footer { border-top: 1px solid rgba(255,255,255,0.07); padding: 18px; }

/* ═══ MAIN ═══ */
.main {
  margin-left: var(--sw);
  flex: 1; padding: 44px 48px 64px;
  position: relative; z-index: 1;
  animation: rise 0.55s cubic-bezier(0.22,1,0.36,1) both;
}
@keyframes rise { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }

/* Header */
.hd { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 36px; gap: 20px; }
.hd-tag {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 1.5px;
  color: var(--blue); background: var(--blue-pale);
  border: 1px solid var(--border-b); border-radius: 20px;
  padding: 5px 13px; margin-bottom: 14px;
}
.hd-tag::before { content: ''; width: 6px; height: 6px; background: var(--blue); border-radius: 50%; }
.hd h1 { font-family: 'Lora', serif; font-size: 2.5rem; font-weight: 700; color: var(--text); letter-spacing: -1px; line-height: 1.05; }
.hd h1 em { font-style: italic; color: var(--blue); }
.hd-sub { font-size: 0.87rem; color: var(--muted); margin-top: 10px; font-weight: 400; line-height: 1.6; }
.btn-cta {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 22px;
  background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
  color: #fff; border-radius: 11px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.84rem; font-weight: 700;
  text-decoration: none; border: none; cursor: pointer;
  transition: all 0.22s;
  box-shadow: 0 4px 18px rgba(37,99,235,0.35);
  white-space: nowrap;
}
.btn-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.45); }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 36px; }
.sc {
  background: var(--white); border: 1px solid var(--border); border-radius: 20px;
  padding: 24px 22px; position: relative; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
}
.sc:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(37,99,235,0.1); border-color: var(--border-b); }
.sc-stripe { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 20px 20px 0 0; opacity: 0; transition: opacity 0.25s; }
.sc:hover .sc-stripe { opacity: 1; }
.sc-stripe.blue  { background: linear-gradient(90deg, var(--blue), var(--blue-light)); }
.sc-stripe.green { background: linear-gradient(90deg, var(--green), #4ade80); }
.sc-stripe.red   { background: linear-gradient(90deg, var(--red), #f87171); }
.sc-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
.sc-icon { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center; font-size: 1.25rem; transition: all 0.25s; }
.sc-icon.blue  { background: var(--blue-xpale);  border: 1.5px solid var(--blue-pale); }
.sc-icon.green { background: var(--green-pale); border: 1.5px solid #bbf7d0; }
.sc-icon.red   { background: var(--red-pale);   border: 1.5px solid #fecaca; }
.sc-chip { font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
.sc-chip.blue  { background: var(--blue-xpale);  color: var(--blue); border: 1px solid var(--blue-pale); }
.sc-chip.green { background: var(--green-pale); color: var(--green); border: 1px solid #bbf7d0; }
.sc-chip.red   { background: var(--red-pale);   color: var(--red);   border: 1px solid #fecaca; }
.sc-val { font-family: 'Lora', serif; font-size: 2.8rem; font-weight: 700; color: var(--text); line-height: 1; letter-spacing: -2px; margin-bottom: 6px; transition: color 0.2s; }
.sc:hover .sc-val { color: var(--blue); }
.sc-label { font-size: 0.8rem; color: var(--muted); font-weight: 500; }

/* Notification */
.notif {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px; border-radius: 14px;
  font-size: 0.85rem; font-weight: 500;
  margin-bottom: 26px;
  animation: rise 0.3s ease both;
}
.notif.success { background: var(--green-pale); border: 1px solid #bbf7d0; color: var(--green); }
.notif.error   { background: var(--red-pale);   border: 1px solid #fecaca; color: var(--red); }

/* ═══ MODAL / FORM ═══ */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(6px);
  z-index: 500;
  display: none; place-items: center;
}
.modal-overlay.show { display: grid; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.modal {
  background: var(--white);
  border-radius: 24px;
  width: 560px; max-width: calc(100vw - 40px);
  max-height: 90vh; overflow-y: auto;
  padding: 36px;
  box-shadow: 0 24px 80px rgba(15,23,42,0.25);
  animation: slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
  position: relative;
}
@keyframes slideUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

.modal-hd { margin-bottom: 28px; }
.modal-hd h2 { font-family: 'Lora', serif; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px; color: var(--text); }
.modal-hd p { font-size: 0.82rem; color: var(--muted); margin-top: 6px; }

.modal-close {
  position: absolute; top: 20px; right: 20px;
  width: 34px; height: 34px;
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 50%; display: grid; place-items: center;
  font-size: 1rem; cursor: pointer; transition: all 0.18s;
  color: var(--muted);
}
.modal-close:hover { background: var(--red-pale); border-color: #fecaca; color: var(--red); }

/* Form Fields */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.form-group { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
.form-group label {
  font-size: 0.78rem; font-weight: 700;
  color: var(--text); letter-spacing: 0.3px;
  display: flex; align-items: center; gap: 6px;
}
.form-group label span { color: var(--blue); }

.form-group select,
.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem; color: var(--text);
  background: var(--s1);
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
  appearance: none;
}
.form-group select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 38px;
  cursor: pointer;
}
.form-group select:focus,
.form-group input:focus {
  border-color: var(--blue-mid);
  box-shadow: 0 0 0 4px var(--blue-glow);
  background: var(--white);
}

.form-divider { width: 100%; height: 1px; background: var(--border); margin: 20px 0; }

.form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }
.btn-cancel {
  padding: 11px 22px; border-radius: 11px;
  background: var(--bg); border: 1.5px solid var(--border);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.84rem; font-weight: 600; color: var(--muted);
  cursor: pointer; transition: all 0.18s;
}
.btn-cancel:hover { background: var(--border); color: var(--text); }

.btn-submit {
  padding: 11px 28px; border-radius: 11px;
  background: linear-gradient(135deg, var(--blue), var(--blue-mid));
  border: none; color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.84rem; font-weight: 700;
  cursor: pointer; transition: all 0.22s;
  box-shadow: 0 4px 18px rgba(37,99,235,0.35);
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.45); }

/* ═══ FILTER BAR ═══ */
.filter-bar {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px 20px;
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  flex-wrap: wrap;
}
.search-wrap {
  flex: 1; min-width: 200px;
  position: relative;
}
.search-wrap .ic {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  font-size: 0.9rem; pointer-events: none;
}
.search-wrap input {
  width: 100%; padding: 10px 14px 10px 40px;
  border: 1.5px solid var(--border); border-radius: 11px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.855rem; color: var(--text);
  background: var(--s1); outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.search-wrap input:focus { border-color: var(--blue-mid); box-shadow: 0 0 0 4px var(--blue-glow); background: var(--white); }

.filter-select {
  padding: 10px 36px 10px 14px;
  border: 1.5px solid var(--border); border-radius: 11px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.855rem; color: var(--muted);
  background: var(--s1);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 12px center;
  appearance: none; outline: none; cursor: pointer;
  transition: border-color 0.2s;
}
.filter-select:focus { border-color: var(--blue-mid); }

.btn-search {
  padding: 10px 20px; border-radius: 11px;
  background: var(--blue); border: none; color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.84rem; font-weight: 600; cursor: pointer;
  transition: all 0.18s;
}
.btn-search:hover { background: var(--navy); }

/* ═══ TABLE ═══ */
.table-wrap {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.table-hd {
  display: flex; align-items: center;
  justify-content: space-between;
  padding: 22px 26px;
  border-bottom: 1px solid var(--border);
}
.table-title {
  font-family: 'Lora', serif;
  font-size: 1.15rem; font-weight: 700;
  color: var(--text); letter-spacing: -0.3px;
}
.table-count {
  font-size: 0.75rem; font-weight: 600;
  color: var(--muted);
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 20px; padding: 4px 12px;
}

table { width: 100%; border-collapse: collapse; }
thead th {
  background: var(--s1);
  font-size: 0.73rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px;
  color: var(--muted);
  padding: 12px 20px; text-align: left;
  border-bottom: 1px solid var(--border);
}
thead th:first-child { padding-left: 26px; }
thead th:last-child  { padding-right: 26px; text-align: center; }

tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background 0.15s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--blue-xpale); }

tbody td {
  padding: 15px 20px;
  font-size: 0.855rem; color: var(--text);
  vertical-align: middle;
}
tbody td:first-child { padding-left: 26px; }
tbody td:last-child  { padding-right: 26px; text-align: center; }

/* Avatar row */
.row-member {
  display: flex; align-items: center; gap: 12px;
}
.row-avatar {
  width: 36px; height: 36px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--blue-mid), var(--blue-light));
  border-radius: 50%; display: grid; place-items: center;
  font-size: 0.8rem; color: #fff; font-weight: 700;
}
.row-name { font-weight: 600; font-size: 0.875rem; }
.row-id   { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }

/* Book cell */
.book-cell { max-width: 220px; }
.book-title { font-weight: 600; color: var(--text); font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Date cell */
.date-col { font-size: 0.82rem; color: var(--muted); font-weight: 500; }
.date-col strong { color: var(--text); display: block; font-weight: 600; }

/* Status badge */
.badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 0.72rem; font-weight: 700;
  padding: 5px 12px; border-radius: 20px;
  white-space: nowrap;
}
.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
.badge.dipinjam  { background: var(--blue-xpale);  color: var(--blue);  border: 1px solid var(--blue-pale); }
.badge.dipinjam::before  { background: var(--blue); }
.badge.kembali   { background: var(--green-pale); color: var(--green); border: 1px solid #bbf7d0; }
.badge.kembali::before   { background: var(--green); }
.badge.terlambat { background: var(--red-pale);   color: var(--red);   border: 1px solid #fecaca; }
.badge.terlambat::before { background: var(--red); box-shadow: 0 0 4px var(--red); animation: blink 1s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

/* Action buttons */
.act-wrap { display: flex; gap: 8px; justify-content: center; }
.btn-act {
  padding: 7px 16px; border-radius: 9px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.78rem; font-weight: 700;
  border: none; cursor: pointer; transition: all 0.18s;
}
.btn-act.return {
  background: var(--green-pale); color: var(--green);
  border: 1.5px solid #bbf7d0;
}
.btn-act.return:hover { background: var(--green); color: #fff; border-color: var(--green); }
.btn-act.done { background: var(--bg); color: var(--dim); border: 1.5px solid var(--border); cursor: default; }

/* Empty state */
.empty-state {
  padding: 70px 20px; text-align: center;
  color: var(--muted);
}
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 14px; opacity: 0.4; }
.empty-state h3 { font-size: 0.95rem; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.empty-state p { font-size: 0.82rem; }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; padding: 24px 16px 44px; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .form-row { grid-template-columns: 1fr; }
  .filter-bar { flex-direction: column; align-items: stretch; }
  .hd { flex-direction: column; }
  .hd h1 { font-size: 2rem; }
}
@media (max-width: 500px) {
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-logo">
      <div class="sb-logo-icon">📚</div>
      <div class="sb-logo-text">Library<span>Pro</span></div>
    </div>
    <div class="sb-profile">
      <div class="sb-avatar">👤</div>
      <div>
        <div class="sb-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
        <div class="sb-badge">Administrator</div>
      </div>
    </div>
  </div>

  <div class="sb-nav">
    <span class="sb-label">Menu Utama</span>
    <ul class="sb-ul">
      <li><a href="admin.php"><span class="sb-icon">🏠</span>Dashboard</a></li>
      <li><a href="kelolabuku.php"><span class="sb-icon">📚</span>Kelola Buku</a></li>
      <li><a href="kelola_anggota.php"><span class="sb-icon">👥</span>Kelola Anggota</a></li>
      <li><a href="peminjaman.php" class="active"><span class="sb-icon">📖</span>Peminjaman</a></li>
    </ul>
    <span class="sb-label">Lainnya</span>
    <ul class="sb-ul">
      <li><a href="laporan.php"><span class="sb-icon">📊</span>Laporan</a></li>
    </ul>
  </div>

  <div class="sb-footer">
    <ul class="sb-ul">
      <li><a href="logout.php"><span class="sb-icon">🚪</span>Logout</a></li>
    </ul>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <!-- Header -->
  <div class="hd">
    <div>
      <div class="hd-tag">Manajemen Peminjaman</div>
      <h1>Data <em>Peminjaman</em></h1>
      <p class="hd-sub">Monitor dan kelola seluruh transaksi peminjaman buku secara real-time.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:flex-start;padding-top:4px;flex-shrink:0;">
      <span style="font-size:.78rem;font-weight:500;padding:9px 16px;border-radius:10px;background:var(--white);border:1px solid var(--border);color:var(--muted);box-shadow:0 1px 4px rgba(0,0,0,.05)">📅 <?= date('d F Y') ?></span>
      <button class="btn-cta" onclick="openModal()">+ Tambah Peminjaman</button>
    </div>
  </div>

  <!-- Notification -->
  <?php if ($msg): ?>
  <div class="notif <?= $msg_type ?>"><?= $msg ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="sc">
      <div class="sc-stripe blue"></div>
      <div class="sc-top">
        <div class="sc-icon blue">📖</div>
        <span class="sc-chip blue">Aktif</span>
      </div>
      <div class="sc-val"><?= number_format($total_dipinjam) ?></div>
      <div style="width:36px;height:3px;background:linear-gradient(90deg,var(--blue),var(--blue-light));border-radius:2px;margin:10px 0;"></div>
      <div class="sc-label">Sedang Dipinjam</div>
    </div>
    <div class="sc">
      <div class="sc-stripe green"></div>
      <div class="sc-top">
        <div class="sc-icon green">✅</div>
        <span class="sc-chip green">Selesai</span>
      </div>
      <div class="sc-val"><?= number_format($total_kembali) ?></div>
      <div style="width:36px;height:3px;background:linear-gradient(90deg,var(--green),#4ade80);border-radius:2px;margin:10px 0;"></div>
      <div class="sc-label">Sudah Dikembalikan</div>
    </div>
    <div class="sc">
      <div class="sc-stripe red"></div>
      <div class="sc-top">
        <div class="sc-icon red">⚠️</div>
        <span class="sc-chip red">Terlambat</span>
      </div>
      <div class="sc-val"><?= number_format($total_terlambat) ?></div>
      <div style="width:36px;height:3px;background:linear-gradient(90deg,var(--red),#f87171);border-radius:2px;margin:10px 0;"></div>
      <div class="sc-label">Melewati Jatuh Tempo</div>
    </div>
  </div>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar">
    <div class="search-wrap">
      <span class="ic">🔍</span>
      <input type="text" name="q" placeholder="Cari nama anggota atau judul buku…" value="<?= htmlspecialchars($keyword) ?>">
    </div>
    <select name="status" class="filter-select">
      <option value="">Semua Status</option>
      <option value="dipinjam"      <?= $filter_status=='dipinjam'?'selected':'' ?>>Dipinjam</option>
      <option value="selesai"  <?= $filter_status=='selesai'?'selected':'' ?>>Selesai</option>
    </select>
    <button type="submit" class="btn-search">Cari</button>
    <?php if ($keyword || $filter_status): ?>
    <a href="peminjaman.php" style="font-size:.82rem;color:var(--muted);font-weight:500;text-decoration:none;padding:10px 14px;border-radius:11px;background:var(--bg);border:1.5px solid var(--border);">✕ Reset</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="table-wrap">
    <div class="table-hd">
      <span class="table-title">Daftar Peminjaman</span>
      <span class="table-count"><?= mysqli_num_rows($peminjaman) ?> Transaksi</span>
    </div>
    <?php if (mysqli_num_rows($peminjaman) === 0): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>Tidak ada data ditemukan</h3>
        <p>Belum ada peminjaman atau tidak cocok dengan pencarian.</p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Anggota</th>
          <th>Buku</th>
          <th>Tgl Pinjam</th>
          <th>Jatuh Tempo</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = 1; while ($row = mysqli_fetch_assoc($peminjaman)): ?>
        <?php
          $terlambat = ($row['status'] === 'dipinjam' && $row['tanggal_jatuh_tempo'] < date('Y-m-d'));
          $status_class = $row['status'] === 'selesai' ? 'kembali' : ($terlambat ? 'terlambat' : 'dipinjam');
          $status_label = $row['status'] === 'selesai' ? 'Dikembalikan' : ($terlambat ? 'Terlambat' : 'Dipinjam');
          $initials = strtoupper(mb_substr($row['nama_anggota'], 0, 1));
        ?>
        <tr>
          <td style="color:var(--dim);font-size:.78rem;font-weight:600;"><?= $no++ ?></td>
          <td>
            <div class="row-member">
              <div class="row-avatar"><?= $initials ?></div>
              <div>
                <div class="row-name"><?= htmlspecialchars($row['nama_anggota']) ?></div>
                <div class="row-id"><?= htmlspecialchars($row['no_anggota']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="book-cell">
              <div class="book-title" title="<?= htmlspecialchars($row['judul_buku']) ?>">
                📗 <?= htmlspecialchars($row['judul_buku']) ?>
              </div>
            </div>
          </td>
          <td>
            <div class="date-col">
              <strong><?= date('d M Y', strtotime($row['tanggal_pinjam'])) ?></strong>
              <?= date('H:i', strtotime($row['tanggal_pinjam'])) ?>
            </div>
          </td>
          <td>
            <div class="date-col">
              <strong><?= date('d M Y', strtotime($row['tanggal_jatuh_tempo'])) ?></strong>
              <?php if ($terlambat): ?>
              <span style="font-size:.7rem;color:var(--red);font-weight:600;">
                +<?= (int)((strtotime('today') - strtotime($row['tanggal_jatuh_tempo'])) / 86400) ?> hari
              </span>
              <?php endif; ?>
            </div>
          </td>
          <td><span class="badge <?= $status_class ?>"><?= $status_label ?></span></td>
          <td>
            <div class="act-wrap">
              <?php if ($row['status'] !== 'selesai'): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Konfirmasi pengembalian buku ini?')">
                <input type="hidden" name="action"  value="kembali">
                <input type="hidden" name="id"      value="<?= $row[$pk_p] ?>">
                <input type="hidden" name="id_buku" value="<?= $row['buku_id'] ?>">
                <button type="submit" class="btn-act return">✓ Kembalikan</button>
              </form>
              <?php else: ?>
              <span class="btn-act done">✓ Selesai</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>

<!-- ═══ MODAL TAMBAH ═══ -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-close" onclick="closeModal()">✕</div>

    <div class="modal-hd">
      <h2>📖 Tambah Peminjaman</h2>
      <p>Isi data peminjaman buku dengan lengkap dan benar.</p>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="tambah">

      <div class="form-group">
        <label>👤 Anggota <span>*</span></label>
        <select name="id_anggota" required>
          <option value="">— Pilih Anggota —</option>
          <?php
            mysqli_data_seek($anggota_list, 0);
            while ($a = mysqli_fetch_assoc($anggota_list)):
          ?>
          <option value="<?= $a[$pk_ang] ?>"><?= htmlspecialchars($a[$col_nama]) ?> (<?= htmlspecialchars($a[$col_no_ang]) ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>📗 Buku <span>*</span></label>
        <select name="id_buku" required>
          <option value="">— Pilih Buku —</option>
          <?php
            mysqli_data_seek($buku_list, 0);
            while ($b = mysqli_fetch_assoc($buku_list)):
          ?>
          <option value="<?= $b[$pk_buku] ?>"><?= htmlspecialchars($b[$col_judul]) ?> (Stok: <?= $b[$col_stok] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-divider"></div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label>📅 Tanggal Pinjam <span>*</span></label>
          <input type="date" name="tgl_pinjam" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>📅 Jatuh Tempo <span>*</span></label>
          <input type="date" name="tgl_kembali" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        </div>
      </div>

      <div class="form-divider"></div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
        <button type="submit" class="btn-submit">✅ Simpan Peminjaman</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal()  { document.getElementById('modalOverlay').classList.add('show'); }
function closeModal() { document.getElementById('modalOverlay').classList.remove('show'); }

// Shortcut ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Auto set return date 7 days from borrow date
document.querySelector('[name="tgl_pinjam"]')?.addEventListener('change', function() {
  const d = new Date(this.value);
  d.setDate(d.getDate() + 7);
  const iso = d.toISOString().split('T')[0];
  document.querySelector('[name="tgl_kembali"]').value = iso;
});
</script>
</body>
</html>