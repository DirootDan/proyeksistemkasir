<?php
// FILE: kelola_user.php
require_once 'koneksi.php'; // Koneksi sudah mencakup session/cookie handling dasar

// 1. CEK AKSES (Hanya Supervisor)
if (!isset($_COOKIE['role']) || $_COOKIE['role'] != 'supervisor') {
    echo "<script>alert('⛔ Akses Ditolak! Halaman ini hanya untuk Supervisor.'); window.location='index.php';</script>";
    exit;
}

// 2. LOGIKA UPDATE NAMA
if (isset($_POST['update_nama'])) {
    $id_user = $_POST['id_user'];
    $nama_baru = $_POST['nama_lengkap'];

    $stmt = $db->prepare("UPDATE users SET nama_lengkap=? WHERE id=?");
    $stmt->execute([$nama_baru, $id_user]);

    // Jika update diri sendiri, perbarui cookie juga agar nama di pojok kanan atas berubah
    if ($id_user == $_COOKIE['user_id']) { 
        setcookie('nama', $nama_baru, time() + 31536000, '/'); 
    }

    echo "<script>alert('✅ Nama berhasil diperbarui!'); window.location='kelola_user.php';</script>";
    exit;
}

// 3. LOGIKA TAMBAH USER
if (isset($_POST['tambah_user'])) {
    $user = $_POST['username'];
    // Validasi sederhana: Username gak boleh spasi
    if (strpos($user, ' ') !== false) {
        echo "<script>alert('⚠️ Username tidak boleh pakai spasi!'); window.location='kelola_user.php';</script>";
        exit;
    }

    $cek = $db->query("SELECT COUNT(*) FROM users WHERE username='$user'")->fetchColumn();
    if ($cek > 0) {
        echo "<script>alert('⚠️ Username sudah dipakai! Gunakan yang lain.'); window.location='kelola_user.php';</script>";
    } else {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama = $_POST['nama_lengkap'];
        $role = $_POST['role'];

        $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user, $pass, $nama, $role]);
        echo "<script>alert('✅ User baru berhasil ditambahkan!'); window.location='kelola_user.php';</script>";
        exit;
    }
}

// 4. LOGIKA RESET PASSWORD
if (isset($_POST['reset_password'])) {
    $id_user = $_POST['id_user'];
    $pass_baru_raw = $_POST['password_baru'];

    if(!empty($pass_baru_raw)){
        $pass_baru_hash = password_hash($pass_baru_raw, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$pass_baru_hash, $id_user]);
        echo "<script>alert('✅ Password berhasil di-RESET!'); window.location='kelola_user.php';</script>";
        exit;
    } else {
        echo "<script>alert('⚠️ Password baru tidak boleh kosong!'); window.location='kelola_user.php';</script>";
    }
}

// 5. LOGIKA HAPUS USER
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    // Cegah hapus diri sendiri
    if ($id == $_COOKIE['user_id']) {
        echo "<script>alert('⚠️ Anda tidak bisa menghapus akun sendiri!'); window.location='kelola_user.php';</script>";
    } else {
        $db->exec("DELETE FROM users WHERE id=$id");
        echo "<script>alert('🗑️ User berhasil dihapus!'); window.location='kelola_user.php';</script>";
        exit;
    }
}

// AMBIL DATA USER
$list_users = $db->query("SELECT * FROM users ORDER BY role DESC, username ASC")->fetchAll();
?>

<?php include 'header.php'; ?>

<style>
    /* LAYOUT UTAMA (Sama persis dengan Index & Laporan) */
    .app-layout { display: flex; min-height: 100vh; background: #f8fafc; }
    
    /* SIDEBAR */
    .sidebar { width: 250px; background: white; border-right: 1px solid #e2e8f0; position: fixed; height: 100%; z-index: 10; }
    .brand-area { padding: 20px; text-align: center; border-bottom: 1px solid #f1f5f9; }
    .nav-menu { padding: 20px 15px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; margin-bottom: 5px; color: #64748b; border-radius: 10px; font-weight: 500; transition: 0.3s; text-decoration: none; }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3); }

    /* CONTENT AREA */
    .main-content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }

    /* GRID USER MANAGER (Kiri: Form, Kanan: Tabel) */
    .user-grid {
        display: grid;
        grid-template-columns: 300px 1fr; /* Kolom Kiri Fixed 300px, Kanan sisa layar */
        gap: 25px;
        align-items: start;
    }

    /* Form Card */
    .form-card {
        background: white; border-radius: 16px; padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        position: sticky; top: 20px; /* Biar tetap terlihat saat scroll */
    }

    /* Table Card */
    .table-card {
        background: white; border-radius: 16px; padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        overflow-x: auto;
    }

    /* Style Input Form */
    label { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 5px; display: block; }
    .input-field {
        width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0;
        border-radius: 8px; margin-bottom: 15px; font-size: 13px;
        background: #f8fafc; transition: 0.3s;
    }
    .input-field:focus { outline: none; border-color: var(--primary); background: white; }

    /* Tombol */
    .btn-submit {
        width: 100%; padding: 12px; background: var(--primary); color: white;
        border: none; border-radius: 10px; font-weight: 600; cursor: pointer;
        transition: 0.2s;
    }
    .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }

    /* Style Tabel */
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
    td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    
    /* Badges */
    .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .badge-spv { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .badge-staff { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

    /* Action Groups */
    .reset-box { display: flex; gap: 5px; background: #eff6ff; padding: 5px; border-radius: 8px; border: 1px solid #dbeafe; width: fit-content; }
    .reset-input { border: none; background: transparent; font-size: 11px; width: 100px; padding: 5px; }
    .reset-input:focus { outline: none; }
    .btn-reset { background: #3b82f6; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; cursor: pointer; }
    
    .btn-delete { 
        width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
        background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 8px;
        text-decoration: none; transition: 0.2s; 
    }
    .btn-delete:hover { background: #ef4444; color: white; }

    /* Edit Nama Inline */
    .edit-name-group { display: flex; align-items: center; gap: 5px; }
    .edit-name-input { border: 1px dashed transparent; background: transparent; font-weight: 600; padding: 4px; color: #334155; font-size: 14px; width: 150px; }
    .edit-name-input:hover { border-color: #cbd5e1; }
    .edit-name-input:focus { border-color: var(--primary); background: white; outline: none; }
    .btn-save-mini { background: none; border: none; cursor: pointer; font-size: 16px; opacity: 0.5; transition: 0.2s; }
    .edit-name-input:focus + .btn-save-mini, .btn-save-mini:hover { opacity: 1; transform: scale(1.1); }

    @media (max-width: 900px) { .user-grid { grid-template-columns: 1fr; } .form-card { position: static; } }
</style>

<div class="app-layout">
    
    <aside class="sidebar">
        <div class="brand-area">
            <div style="font-size: 40px;">🌸</div>
            <h3>Kasir Salon</h3>
            <div style="font-size: 12px; color: #94a3b8;">Admin Panel</div>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item"><span>🏠</span> Dashboard</a>
            <a href="laporan.php" class="nav-item"><span>📊</span> Laporan</a>
            <a href="kelola_user.php" class="nav-item active"><span>👥</span> Pengguna</a>
            <a href="pengaturan.php" class="nav-item"><span>⚙️</span> Pengaturan</a>
            <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 30px; background: #fef2f2;"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        
        <h2 style="margin-top: 0; color: var(--text-dark); margin-bottom: 25px;">Manajemen Staff & Akses</h2>

        <div class="user-grid">
            
            <div class="form-card">
                <h3 style="margin-top:0; border-bottom:1px solid #f1f5f9; padding-bottom:15px; color:var(--primary);">➕ Tambah Staff Baru</h3>
                
                <form method="POST">
                    <label>Username (Login)</label>
                    <input type="text" name="username" class="input-field" required placeholder="Cth: kasir1" autocomplete="off">
                    
                    <label>Password Awal</label>
                    <input type="text" name="password" class="input-field" required placeholder="Cth: rahasia123" autocomplete="off">
                    
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="input-field" required placeholder="Cth: Siti Aminah">
                    
                    <label>Jabatan / Role</label>
                    <select name="role" class="input-field">
                        <option value="staff">👤 Staff (Kasir Biasa)</option>
                        <option value="supervisor">👑 Supervisor (Admin Full)</option>
                    </select>
                    
                    <button type="submit" name="tambah_user" class="btn-submit">Simpan User Baru</button>
                </form>

                <div style="margin-top: 20px; padding: 10px; background: #f0f9ff; border-radius: 8px; font-size: 11px; color: #0369a1;">
                    ℹ️ <b>Tips:</b><br>
                    Username tidak boleh menggunakan spasi. Gunakan huruf kecil agar mudah diingat.
                </div>
            </div>

            <div class="table-card">
                <h3 style="margin-top:0; margin-bottom:20px;">📋 Daftar Pengguna Aktif</h3>
                
                <table cellspacing="0">
                    <thead>
                        <tr>
                            <th width="30%">Nama & Username</th>
                            <th width="15%">Role</th>
                            <th width="55%">Reset Password / Hapus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($list_users as $u): ?>
                        <tr>
                            <td>
                                <form method="POST" class="edit-name-group">
                                    <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                    <input type="text" name="nama_lengkap" class="edit-name-input" value="<?= $u['nama_lengkap'] ?>" title="Klik untuk edit nama">
                                    <button type="submit" name="update_nama" class="btn-save-mini" title="Simpan Nama">💾</button>
                                </form>
                                <div style="font-size: 11px; color: #94a3b8; margin-left: 5px;">@<?= $u['username'] ?></div>
                            </td>
                            
                            <td>
                                <?php if($u['role'] == 'supervisor'): ?>
                                    <span class="badge badge-spv">SUPERVISOR</span>
                                <?php else: ?>
                                    <span class="badge badge-staff">STAFF</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div style="display:flex; align-items:center; gap: 10px;">
                                    <form method="POST" class="reset-box">
                                        <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                                        <input type="text" name="password_baru" class="reset-input" placeholder="Password Baru..." required autocomplete="off">
                                        <button type="submit" name="reset_password" class="btn-reset">RESET</button>
                                    </form>
                                    
                                    <?php if($u['id'] != $_COOKIE['user_id']): ?>
                                        <a href="?hapus=<?= $u['id'] ?>" class="btn-delete" onclick="return confirm('⚠️ Yakin ingin menghapus user: <?= $u['nama_lengkap'] ?>?')" title="Hapus User">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>
</div>