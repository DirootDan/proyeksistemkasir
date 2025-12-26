<?php
session_start();
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 1. SETUP OTOMATIS TABEL USER ---
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    nama_lengkap TEXT,
    role TEXT
)");

// --- 2. BUAT AKUN DEFAULT JIKA KOSONG ---
$cek = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($cek == 0) {
    // Default: admin / admin123
    $pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, nama_lengkap, role) VALUES ('admin', '$pass_hash', 'Owner Salon', 'supervisor')");
}

// --- 3. PROSES LOGIN ---
$error = "";
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $akun = $stmt->fetch();

    if ($akun && password_verify($pass, $akun['password'])) {
        // Login Sukses
        $_SESSION['user_id'] = $akun['id'];
        $_SESSION['nama'] = $akun['nama_lengkap'];
        $_SESSION['role'] = $akun['role'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau Password Salah!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Salon Rengganis</title>
    <style>
        body { font-family: 'Comic Sans MS', sans-serif; background: #ff9a9e; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 300px; text-align: center; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #ff007f; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background: #d6006f; }
        .error { color: red; margin-bottom: 10px; font-size: 14px; }
        .help { margin-top: 15px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 style="color: #ff007f;">🌸 Login Salon</h2>
        
        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">MASUK</button>
        </form>

        <div class="help">
            Lupa Password?<br>
            Hubungi <b>Supervisor/Owner</b> untuk reset.
        </div>
    </div>
</body>
</html>