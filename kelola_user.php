<?php
session_start();
// HANYA SUPERVISOR YANG BOLEH AKSES INI
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'supervisor') {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 1. SCRIPT PERBAIKAN DATABASE OTOMATIS ---
try {
    $db->query("SELECT nama_lengkap FROM users LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE users ADD COLUMN nama_lengkap TEXT DEFAULT 'Staff Salon'");
}

// --- 2. LOGIKA UPDATE NAMA (BARU!) ---
if (isset($_POST['update_nama'])) {
    $id_user = $_POST['id_user'];
    $nama_baru = $_POST['nama_lengkap']; // Ambil nama baru dari input

    $stmt = $db->prepare("UPDATE users SET nama_lengkap=? WHERE id=?");
    $stmt->execute([$nama_baru, $id_user]);

    // Jika yang diupdate adalah diri sendiri, update juga sesi nama biar langsung berubah di header
    if ($id_user == $_SESSION['user_id']) {
        $_SESSION['nama'] = $nama_baru;
    }

    echo "<script>alert('Nama berhasil diperbarui!'); window.location='kelola_user.php';</script>";
}

// --- 3. LOGIKA TAMBAH USER ---
if (isset($_POST['tambah_user'])) {
    $user = $_POST['username'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = $_POST['nama_lengkap'];
    $role = $_POST['role'];

    $cek = $db->query("SELECT COUNT(*) FROM users WHERE username='$user'")->fetchColumn();
    if ($cek > 0) {
        echo "<script>alert('Username sudah dipakai! Ganti yang lain.');</script>";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user, $pass, $nama, $role]);
        echo "<script>alert('User baru berhasil ditambahkan!'); window.location='kelola_user.php';</script>";
    }
}

// --- 4. LOGIKA RESET PASSWORD ---
if (isset($_POST['reset_password'])) {
    $id_user = $_POST['id_user'];
    if(!empty($_POST['password_baru'])){
        $pass_baru = password_hash($_POST['password_baru'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$pass_baru, $id_user]);
        echo "<script>alert('Password berhasil di-RESET!'); window.location='kelola_user.php';</script>";
    } else {
        echo "<script>alert('Password baru tidak boleh kosong!');</script>";
    }
}

// --- 5. LOGIKA HAPUS USER ---
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Tidak bisa menghapus akun sendiri!');</script>";
    } else {
        $db->exec("DELETE FROM users WHERE id=$id");
        header("Location: kelola_user.php");
    }
}

$list_users = $db->query("SELECT * FROM users ORDER BY role DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Pengguna</title>
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; background: #fdf2f8; padding: 20px; color: #444; }
        
        .header { display: flex; justify-content: space-between; align-items: center; max-width: 1000px; margin: 0 auto 20px auto; }
        .btn-back { text-decoration: none; font-weight: bold; color: #ff007f; padding: 8px 15px; background: white; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s; }
        .btn-back:hover { background: #ff007f; color: white; }

        .container { max-width: 1000px; margin: 0 auto; display: flex; gap: 30px; align-items: flex-start; }
        .box { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        h3 { margin-top: 0; color: #ff007f; border-bottom: 2px solid #ffe6f0; padding-bottom: 10px; }
        label { font-size: 13px; font-weight: bold; color: #666; display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 12px; margin-top: 5px; box-sizing: border-box; border: 1px solid #eee; border-radius: 8px; background: #f9f9f9; font-family: inherit; }
        input:focus { border-color: #ff007f; outline: none; background: white; }
        
        button { cursor: pointer; color: white; font-weight: bold; border: none; border-radius: 8px; transition: 0.3s; }
        .btn-simpan { width: 100%; padding: 12px; background: linear-gradient(45deg, #ff007f, #ff66b2); margin-top: 20px; box-shadow: 0 4px 10px rgba(255, 0, 127, 0.3); }
        .btn-simpan:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(255, 0, 127, 0.4); }

        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { text-align: left; padding: 10px 15px; color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        
        tr.data-row { background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.03); transition: 0.2s; }
        tr.data-row td { padding: 15px; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr.data-row td:first-child { border-left: 1px solid #f0f0f0; border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        tr.data-row td:last-child { border-right: 1px solid #f0f0f0; border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        tr.data-row:hover { transform: scale(1.01); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-spv { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-staff { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }

        /* Tombol Aksi */
        .form-inline { display: flex; gap: 5px; align-items: center; }
        .input-mini { padding: 8px; font-size: 12px; margin: 0; background: white; border: 1px solid #ddd; }
        .btn-mini { padding: 8px 12px; font-size: 12px; margin: 0; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; } /* Warna Tombol Simpan Nama */
        .btn-del { background: #dc3545; padding: 8px 12px; font-size: 12px; text-decoration: none; color: white; border-radius: 8px; display: inline-block; }
    </style>
</head>
<body>

    <div class="header">
        <h2 style="margin: 0; color: #333;">👥 Manajemen Staff & Supervisor</h2>
        <a href="index.php" class="btn-back">⬅ Kembali ke Dashboard</a>
    </div>

    <div class="container">
        <div class="box" style="flex: 1; position: sticky; top: 20px;">
            <h3>➕ Tambah Akun Baru</h3>
            <form method="POST">
                <label>Username (Login):</label>
                <input type="text" name="username" required placeholder="Contoh: kasir1" autocomplete="off">
                
                <label>Password:</label>
                <input type="text" name="password" required placeholder="Contoh: rahasia123" autocomplete="off">
                
                <label>Nama Lengkap:</label>
                <input type="text" name="nama_lengkap" required placeholder="Contoh: Siti Aminah">
                
                <label>Jabatan:</label>
                <select name="role">
                    <option value="staff">👤 Staff (Kasir Saja)</option>
                    <option value="supervisor">👑 Supervisor (Akses Penuh)</option>
                </select>
                
                <button type="submit" name="tambah_user" class="btn-simpan">💾 SIMPAN PENGGUNA</button>
            </form>
        </div>

        <div class="box" style="flex: 2;">
            <h3>📋 Daftar Pengguna (Bisa Edit Nama)</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%;">Nama Lengkap & Username</th>
                        <th>Jabatan</th>
                        <th>Reset Password / Hapus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list_users as $u): ?>
                    <tr class="data-row">
                        <td>
                            <form method="POST" class="form-inline" style="margin-bottom: 5px;">
                                <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                
                                <input type="text" name="nama_lengkap" class="input-mini" value="<?= $u['nama_lengkap'] ?? '' ?>" style="font-weight: bold; width: 100%;">
                                
                                <button type="submit" name="update_nama" class="btn-mini btn-green" title="Simpan Nama">💾</button>
                            </form>
                            
                            <span style="color: #888; font-size: 12px; margin-left: 5px;">Username: @<?= $u['username'] ?></span>
                        </td>
                        <td>
                            <?php if($u['role']=='supervisor'): ?>
                                <span class="badge badge-spv">👑 SUPERVISOR</span>
                            <?php else: ?>
                                <span class="badge badge-staff">👤 STAFF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                <input type="text" name="password_baru" class="input-mini" placeholder="Pass Baru..." required autocomplete="off" style="width: 80px;">
                                <button type="submit" name="reset_password" class="btn-mini btn-blue">Reset</button>
                                
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?hapus=<?= $u['id'] ?>" class="btn-del" onclick="return confirm('Yakin hapus user ini?')">Hapus</a>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>