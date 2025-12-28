<?php
// Include koneksi
require_once 'koneksi.php';

// Cek Cookie (Jika sudah login, langsung lempar ke index)
if (isset($_COOKIE['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Ambil data user
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $akun = $stmt->fetch();

    if ($akun && password_verify($pass, $akun['password'])) {
        // Set Cookie selama 1 Tahun (Cocok untuk PHP Desktop)
        setcookie("user_id", $akun['id'], time() + 31536000, "/");
        setcookie("nama", $akun['nama_lengkap'], time() + 31536000, "/");
        setcookie("role", $akun['role'], time() + 31536000, "/");
        
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 350px;
            text-align: center;
            transition: transform 0.3s;
        }
        .login-card:hover { transform: translateY(-5px); }
        h2 { color: #db2777; margin-bottom: 5px; font-weight: 700; }
        p.subtitle { color: #6b7280; margin-top: 0; font-size: 14px; margin-bottom: 30px; }
        
        input {
            width: 100%; padding: 12px 15px; margin-bottom: 15px;
            border: 1px solid #e5e7eb; border-radius: 10px;
            box-sizing: border-box; font-size: 14px; background: #f9fafb;
        }
        input:focus { outline: none; border-color: #db2777; background: white; }
        
        button {
            width: 100%; padding: 12px;
            background: #db2777; color: white;
            border: none; border-radius: 10px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: 0.3s;
        }
        button:hover { background: #be185d; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.3); }
        .error-msg {
            background: #fee2e2; color: #ef4444;
            padding: 10px; border-radius: 8px;
            font-size: 13px; margin-bottom: 20px; border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div style="font-size: 40px; margin-bottom: 10px;">🌸</div>
        <h2>Salon Rengganis</h2>
        <p class="subtitle">Silakan masuk untuk memulai kasir</p>

        <?php if($error): ?>
            <div class="error-msg">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="text-align: left; font-size: 12px; margin-bottom: 5px; color: #6b7280; font-weight: 600;">Username</div>
            <input type="text" name="username" placeholder="Masukan username..." required autocomplete="off">
            
            <div style="text-align: left; font-size: 12px; margin-bottom: 5px; color: #6b7280; font-weight: 600;">Password</div>
            <input type="password" name="password" placeholder="••••••••" required>
            
            <button type="submit" name="login">MASUK SEKARANG</button>
        </form>

        <p style="margin-top: 20px; font-size: 11px; color: #9ca3af;">
            Aplikasi Kasir v1.0 &bull; PHP Desktop
        </p>
    </div>

</body>
</html>