<?php

$db = new PDO("sqlite:salon.db");

// Password default baru: admin123
$new_pass = password_hash('admin123', PASSWORD_DEFAULT);

// Reset akun dengan username 'admin'
$db->exec("UPDATE users SET password = '$new_pass' WHERE username = 'admin'");

echo "<h1>RESET BERHASIL</h1>";
echo "Password untuk user 'admin' telah dikembalikan menjadi: <b>admin123</b><br>";
echo "<a href='login.php'>Klik disini untuk Login</a>";
?>