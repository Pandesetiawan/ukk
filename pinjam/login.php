<?php
// 1. AKTIFKAN ERROR REPORTING (Agar kalau ada salah muncul tulisannya, bukan layar putih)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CEK FILE CONFIG (Gunakan ../ karena config.php ada di luar folder pinjam)
if (!file_exists("../config.php")) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red;'>
            <b>ERROR:</b> File <code>config.php</code> tidak ditemukan!<br>
            PHP mencari di: <u>" . realpath("../") . "/config.php</u><br>
            Pastikan file tersebut ada di folder utama <b>perpus</b>.
         </div>");
}

include "../config.php"; 
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan koneksi $conn ada
    if (!$conn) {
        die("Koneksi database ke variabel \$conn gagal. Cek config.php!");
    }

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $query  = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Cek password (mendukung teks biasa untuk awal)
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $_SESSION['id_user']  = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Arahkan ke dashboard (keluar folder pinjam dulu)
            if ($user['role'] === 'admin') {
                header("Location: ../admin/admin.php");
            } else {
                header("Location: ../siswa/dashboard_user.php");
            }
            exit();
        } else { $error = "Password salah!"; }
    } else { $error = "Username tidak ditemukan!"; }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Perpus</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 350px; }
        h2 { text-align: center; color: #1c1e21; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        button { width: 100%; padding: 12px; background: #1877f2; color: white; border: none; border-radius: 6px; font-size: 18px; font-weight: bold; cursor: pointer; }
        button:hover { background: #166fe5; }
        .error-box { background: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .footer { text-align: center; margin-top: 20px; font-size: 14px; color: #606770; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login Perpus</h2>
        <?php if($error) echo "<div class='error-box'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>
        <div class="footer">Belum punya akun? <a href="register.php" style="text-decoration:none; color:#1877f2;">Daftar</a></div>
    </div>
</body>
</html>