<?php
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "LOGIN: " . (isset($_SESSION['login']) ? $_SESSION['login'] : 'TIDAK ADA') . "<br>";
echo "ROLE: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'TIDAK ADA');
?>