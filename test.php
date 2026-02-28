<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session data: ";
print_r($_SESSION);
?>
```

Buka `localhost/perpus/cek_session.php` lalu **setelah login**, buka lagi file itu — apakah `$_SESSION['login']` dan `$_SESSION['role']` ada isinya?

---

Tapi saya curiga masalah utamanya adalah **redirect ke `admin/dashboard_admin.php` gagal** karena halaman itu sendiri yang error/putih.

Coba langsung buka:
```
localhost/perpus/admin/dashboard_admin.php