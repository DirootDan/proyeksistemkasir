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

// --- 2. LOGIKA UPDATE NAMA ---
if (isset($_POST['update_nama'])) {
    $id_user = $_POST['id_user'];
    $nama_baru = $_POST['nama_lengkap'];

    $stmt = $db->prepare("UPDATE users SET nama_lengkap=? WHERE id=?");
    $stmt->execute([$nama_baru, $id_user]);

    if ($id_user == $_SESSION['user_id']) { $_SESSION['nama'] = $nama_baru; }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ec4899;
            --bg-body: #f3f4f6;
            --white: #ffffff;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --danger: #ef4444;
            --success: #10b981;
            --blue: #3b82f6;
            --warning: #f59e0b;
        }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); padding: 20px; margin: 0; color: var(--text-dark); }
        
        .header-page { max-width: 1100px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { text-decoration: none; color: var(--text-dark); font-weight: 600; background: var(--white); padding: 10px 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.3s; }
        .btn-back:hover { background: var(--text-dark); color: white; }

        .container { 
            max-width: 1100px; margin: 0 auto; 
            display: grid; grid-template-columns: 320px 1fr; /* Kiri Fixed, Kanan Auto */
            gap: 25px; align-items: start;
        }

        /* CARD STYLE */
        .card { 
            background: var(--white); border-radius: 16px; padding: 25px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
        }
        
        h3 { margin: 0 0 20px 0; font-size: 16px; font-weight: 700; color: var(--text-dark); border-bottom: 2px solid #f3f4f6; padding-bottom: 10px; }

        /* FORM STYLE */
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-light); margin-bottom: 5px; margin-top: 10px; }
        input, select { 
            width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; 
            box-sizing: border-box; font-family: 'Poppins'; font-size: 13px; background: #f9fafb; 
        }
        input:focus, select:focus { outline: none; border-color: var(--primary); background: white; }
        
        .btn-save { 
            width: 100%; padding: 12px; background: linear-gradient(45deg, #ec4899, #be185d); 
            color: white; border: none; border-radius: 10px; font-weight: 600; margin-top: 20px; 
            cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(236, 72, 153, 0.2); 
        }
        .btn-save:hover { transform: translateY(-2px); }

        /* TABLE STYLE */
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { text-align: left; padding: 10px 15px; color: var(--text-light); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .data-row td { 
            background: var(--white); padding: 15px; 
            border-top: 1px solid #f3f4f6; border-bottom: 1px solid #f3f4f6; vertical-align: middle; 
        }
        .data-row td:first-child { border-left: 1px solid #f3f4f6; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .data-row td:last-child { border-right: 1px solid #f3f4f6; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        .data-row:hover td { background: #fdf2f8; border-color: #fce7f3; }

        /* EDIT NAME INLINE */
        .name-edit-group { display: flex; align-items: center; gap: 5px; }
        .input-name-edit { 
            border: 1px dashed transparent; background: transparent; font-weight: 600; color: var(--text-dark); padding: 5px; 
        }
        .input-name-edit:focus { border-color: var(--primary); background: white; }
        .btn-mini-save { 
            background: var(--success); color: white; border: none; border-radius: 6px; 
            width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;
        }

        /* BADGES */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-spv { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .badge-staff { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }

        /* ACTION AREA */
        .action-area { display: flex; align-items: center; gap: 8px; }
        .reset-group { display: flex; align-items: center; background: #eff6ff; padding: 4px; border-radius: 8px; border: 1px solid #dbeafe; }
        .input-reset { 
            border: none; background: transparent; font-size: 11px; width: 80px; padding: 5px; 
        }
        .input-reset:focus { outline: none; box-shadow: none; background: transparent; }
        .btn-reset { 
            background: var(--blue); color: white; border: none; border-radius: 6px; 
            padding: 5px 10px; font-size: 10px; font-weight: 600; cursor: pointer; 
        }
        
        .btn-del { 
            background: #fee2e2; color: var(--danger); border: 1px solid #fecaca; 
            width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; 
            text-decoration: none; font-size: 14px; transition: 0.2s; 
        }
        .btn-del:hover { background: var(--danger); color: white; border-color: var(--danger); }

        /* RESPONSIVE */
        @media (max-width: 850px) {
            .container { grid-template-columns: 1fr; }
            .card { position: static; } /* Un-sticky if mobile */
        }
    </style>
</head>
<body>

    <div class="header-page">
        <h2 style="margin: 0; font-size: 24px;">👥 Manajemen Staff</h2>
        <a href="index.php" class="btn-back">⬅ Kembali ke Dashboard</a>
    </div>

    <div class="container">
        
        <div class="card" style="position: sticky; top: 20px;">
            <h3>➕ Tambah Akun Baru</h3>
            <form method="POST">
                <label>Username (Login)</label>
                <input type="text" name="username" required placeholder="Contoh: kasir1" autocomplete="off">
                
                <label>Password Default</label>
                <input type="text" name="password" required placeholder="Contoh: rahasia123" autocomplete="off">
                
                <label>Nama Lengkap Staff</label>
                <input type="text" name="nama_lengkap" required placeholder="Contoh: Siti Aminah">
                
                <label>Jabatan</label>
                <select name="role">
                    <option value="staff">👤 Staff (Kasir Saja)</option>
                    <option value="supervisor">👑 Supervisor (Admin)</option>
                </select>
                
                <button type="submit" name="tambah_user" class="btn-save">Simpan Pengguna</button>
            </form>
        </div>

        <div class="card">
            <h3>📋 Daftar Pengguna (Bisa Edit Nama)</h3>
            <table cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th width="35%">Nama Lengkap & Username</th>
                        <th width="15%">Jabatan</th>
                        <th width="50%">Reset Password / Hapus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list_users as $u): ?>
                    <tr class="data-row">
                        <td>
                            <form method="POST" class="name-edit-group">
                                <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                <input type="text" name="nama_lengkap" class="input-name-edit" value="<?= $u['nama_lengkap'] ?? '' ?>">
                                <button type="submit" name="update_nama" class="btn-mini-save" title="Simpan Perubahan Nama">💾</button>
                            </form>
                            <div style="font-size: 11px; color: var(--text-light); margin-left: 5px; margin-top:2px;">
                                Username: <b>@<?= $u['username'] ?></b>
                            </div>
                        </td>
                        
                        <td>
                            <?php if($u['role']=='supervisor'): ?>
                                <span class="badge badge-spv">👑 SPV</span>
                            <?php else: ?>
                                <span class="badge badge-staff">👤 STAFF</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <div class="action-area">
                                <form method="POST" class="reset-group">
                                    <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                    <input type="text" name="password_baru" class="input-reset" placeholder="Pass Baru..." required autocomplete="off">
                                    <button type="submit" name="reset_password" class="btn-reset">Reset</button>
                                </form>
                                
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?hapus=<?= $u['id'] ?>" class="btn-del" onclick="return confirm('Yakin hapus user ini?')" title="Hapus User">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>