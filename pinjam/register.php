<?php
require_once '../koneksi.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';
    $nama     = trim($_POST['nama'] ?? '');

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'Semua field wajib diisi.';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek username sudah ada
        $check = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username sudah digunakan, pilih username lain.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $role   = 'user'; // default role

            $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama, $username, $hashed, $role);

            if ($stmt->execute()) {
                $success = 'Akun berhasil dibuat! <a href="login.php">Masuk sekarang →</a>';
            } else {
                $error = 'Gagal membuat akun: ' . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — Perpustakaan</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink:   #0f0e0c;
    --cream: #f5f0e8;
    --sand:  #e8dfc8;
    --gold:  #c9a84c;
    --rust:  #b85c38;
    --green: #3a7d5c;
    --muted: #7a7060;
  }

  body {
    min-height: 100vh;
    background: var(--cream);
    display: flex;
    font-family: 'DM Sans', sans-serif;
    overflow: hidden;
  }

  .panel-left {
    width: 45%;
    background: #1c2b24;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 3rem;
    overflow: hidden;
  }

  .panel-left::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      radial-gradient(ellipse 60% 70% at 20% 30%, rgba(58,125,92,.25) 0%, transparent 60%),
      radial-gradient(ellipse 40% 50% at 80% 75%, rgba(201,168,76,.12) 0%, transparent 55%);
  }

  .panel-left .big-num {
    font-family: 'Playfair Display', serif;
    font-size: 18vw;
    font-weight: 900;
    color: rgba(255,255,255,.04);
    position: absolute;
    top: -2rem;
    left: -1rem;
    line-height: 1;
    user-select: none;
  }

  .panel-left .lines {
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(0deg, transparent, transparent 59px, rgba(255,255,255,.04) 60px);
  }

  .panel-left .tagline {
    position: relative;
    z-index: 1;
  }

  .panel-left .tagline h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.6rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 1rem;
  }

  .panel-left .tagline p {
    color: rgba(255,255,255,.5);
    font-size: .95rem;
    line-height: 1.7;
    max-width: 28ch;
  }

  .panel-left .green-bar {
    width: 3rem; height: 3px;
    background: #6ec89a;
    margin-bottom: 1.2rem;
  }

  .panel-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    overflow-y: auto;
  }

  .card {
    width: 100%;
    max-width: 420px;
    padding: 1rem 0;
    animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .card .logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 2.5rem;
    display: flex;
    align-items: center;
    gap: .6rem;
  }

  .card .logo span.dot {
    width: 8px; height: 8px;
    background: #6ec89a;
    border-radius: 50%;
  }

  .card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: var(--ink);
    margin-bottom: .4rem;
  }

  .card .sub {
    color: var(--muted);
    font-size: .9rem;
    margin-bottom: 2rem;
  }

  .error-box {
    background: #fde8e8;
    border-left: 3px solid var(--rust);
    padding: .75rem 1rem;
    border-radius: 4px;
    color: var(--rust);
    font-size: .88rem;
    margin-bottom: 1.4rem;
  }

  .success-box {
    background: #e8f5ef;
    border-left: 3px solid var(--green);
    padding: .75rem 1rem;
    border-radius: 4px;
    color: var(--green);
    font-size: .88rem;
    margin-bottom: 1.4rem;
  }

  .success-box a { color: var(--green); font-weight: 600; }

  .field { margin-bottom: 1.2rem; }

  .field label {
    display: block;
    font-size: .78rem;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: .45rem;
  }

  .field input {
    width: 100%;
    padding: .8rem 1rem;
    border: 1.5px solid var(--sand);
    border-radius: 8px;
    background: #fff;
    font-family: 'DM Sans', sans-serif;
    font-size: .95rem;
    color: var(--ink);
    transition: border-color .2s;
    outline: none;
  }

  .field input:focus {
    border-color: #6ec89a;
    box-shadow: 0 0 0 3px rgba(110,200,154,.12);
  }

  .hint {
    font-size: .78rem;
    color: var(--muted);
    margin-top: .35rem;
  }

  .btn {
    width: 100%;
    padding: .9rem;
    background: #1c2b24;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: .95rem;
    font-weight: 500;
    cursor: pointer;
    letter-spacing: .03em;
    transition: background .2s, transform .15s;
    margin-top: .4rem;
  }

  .btn:hover { background: #2d4237; transform: translateY(-1px); }
  .btn:active { transform: translateY(0); }

  .footer-link {
    text-align: center;
    margin-top: 1.5rem;
    font-size: .88rem;
    color: var(--muted);
  }

  .footer-link a {
    color: #3a7d5c;
    text-decoration: none;
    font-weight: 500;
  }

  .footer-link a:hover { text-decoration: underline; }

  @media (max-width: 680px) {
    .panel-left { display: none; }
    .panel-right { padding: 1.5rem; }
  }
</style>
</head>
<body>

<div class="panel-left">
  <div class="big-num">D</div>
  <div class="lines"></div>
  <div class="tagline">
    <div class="green-bar"></div>
    <h1>Bergabung Bersama Kami</h1>
    <p>Buat akun baru dan mulai nikmati layanan perpustakaan digital kami.</p>
  </div>
</div>

<div class="panel-right">
  <div class="card">
    <div class="logo">
      <span class="dot"></span> Perpus
    </div>

    <h2>Buat Akun</h2>
    <p class="sub">Isi data di bawah untuk mendaftar</p>

    <?php if ($error): ?>
      <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success-box"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="">
      <div class="field">
        <label for="nama">Nama Lengkap <span style="color:var(--muted);font-weight:300">(opsional)</span></label>
        <input type="text" id="nama" name="nama" placeholder="Nama Anda"
               value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="username">Username <span style="color:var(--rust)">*</span></label>
        <input type="text" id="username" name="username" placeholder="Minimal 3 karakter"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        <p class="hint">Hanya huruf, angka, dan underscore. Minimal 3 karakter.</p>
      </div>

      <div class="field">
        <label for="password">Password <span style="color:var(--rust)">*</span></label>
        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
      </div>

      <div class="field">
        <label for="confirm">Konfirmasi Password <span style="color:var(--rust)">*</span></label>
        <input type="password" id="confirm" name="confirm" placeholder="Ulangi password" required>
      </div>

      <button type="submit" class="btn">Daftar Sekarang →</button>
    </form>
    <?php endif; ?>

    <p class="footer-link">
      Sudah punya akun? <a href="login.php">Masuk di sini</a>
    </p>
  </div>
</div>

</body>
</html>