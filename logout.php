<?php
// FILE: logout.php

// 1. Hapus Cookie (Mundurkan waktu kedaluwarsa)
setcookie("user_id", "", time() - 3600, "/");
setcookie("nama", "", time() - 3600, "/");
setcookie("role", "", time() - 3600, "/");

// 2. Hapus Session (Jaga-jaga jika ada)
session_start();
session_destroy();

// 3. Kembali ke Login
header("Location: login.php");
exit;
?>