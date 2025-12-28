<?php
require_once 'koneksi.php';
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }

// --- LOGIKA PENYIMPANAN DATA (BACKEND) ---
try {
    // 1. Simpan Identitas Toko
    if (isset($_POST['simpan_toko'])) {
        $stmt = $db->prepare("UPDATE info_toko SET nama_toko=?, alamat_toko=?, pesan_footer=? WHERE id=1");
        $stmt->execute([$_POST['nama'], $_POST['alamat'], $_POST['footer']]);
        echo "<script>alert('✅ Info Toko Berhasil Disimpan!'); window.location='pengaturan.php';</script>"; exit;
    }

    // 2. Tambah Data Sederhana
    if (isset($_POST['add_terapis'])) { 
        $db->prepare("INSERT INTO master_terapis (nama_terapis) VALUES (?)")->execute([$_POST['nama']]);
        header("Location:pengaturan.php?tab=staff"); exit;
    }
    if (isset($_POST['add_metode'])) { 
        $db->prepare("INSERT INTO master_metode (nama_metode) VALUES (?)")->execute([$_POST['nama']]);
        header("Location:pengaturan.php?tab=umum"); exit;
    }
    if (isset($_POST['add_layanan'])) { 
        $db->prepare("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES (?,?)")->execute([$_POST['nama'], $_POST['harga']]); 
        header("Location:pengaturan.php?tab=layanan"); exit;
    }

    // 3. Tambah Promo (Fitur Checkbox)
    if (isset($_POST['add_promo'])) {
        // Gabungkan array checkbox menjadi string koma
        $target = isset($_POST['target']) ? implode(',', $_POST['target']) : '';
        $tgl_db = str_replace('T', ' ', $_POST['tgl']); // Fix format datetime

        $sql = "INSERT INTO daftar_promo (nama_promo, jenis_diskon, nilai_diskon, target_layanan, berlaku_sampai) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$_POST['nama'], $_POST['jenis'], $_POST['nilai'], $target, $tgl_db]);
        
        echo "<script>alert('✅ Promo Berhasil Dibuat!'); window.location='pengaturan.php?tab=promo';</script>"; exit;
    }

    // 4. Hapus Data
    if(isset($_GET['del_terapis'])) { $db->exec("DELETE FROM master_terapis WHERE id=".$_GET['del_terapis']); header("Location:pengaturan.php?tab=staff"); exit; }
    if(isset($_GET['del_metode'])) { $db->exec("DELETE FROM master_metode WHERE id=".$_GET['del_metode']); header("Location:pengaturan.php?tab=umum"); exit; }
    if(isset($_GET['del_layanan'])) { $db->exec("DELETE FROM master_layanan WHERE id=".$_GET['del_layanan']); header("Location:pengaturan.php?tab=layanan"); exit; }
    if(isset($_GET['del_promo'])) { $db->exec("DELETE FROM daftar_promo WHERE id=".$_GET['del_promo']); header("Location:pengaturan.php?tab=promo"); exit; }

} catch (PDOException $e) {
    die("<script>alert('Error Database: ".$e->getMessage()."'); window.history.back();</script>");
}

// AMBIL DATA DARI DATABASE
$toko = $db->query("SELECT * FROM info_toko LIMIT 1")->fetch();
$terapis = $db->query("SELECT * FROM master_terapis ORDER BY nama_terapis")->fetchAll();
$metode = $db->query("SELECT * FROM master_metode")->fetchAll();
$layanan = $db->query("SELECT * FROM master_layanan ORDER BY nama_layanan")->fetchAll();
$promo = $db->query("SELECT * FROM daftar_promo ORDER BY berlaku_sampai")->fetchAll();

// Deteksi Tab Aktif (Default: umum)
$active_tab = $_GET['tab'] ?? 'umum';
?>

<?php include 'header.php'; ?>

<style>
    /* LAYOUT DASAR */
    .app-layout { display: flex; min-height: 100vh; background: #f8fafc; }
    .sidebar { width: 250px; background: white; border-right: 1px solid #e2e8f0; position: fixed; height: 100%; z-index: 10; }
    .brand-area { padding: 20px; text-align: center; border-bottom: 1px solid #f1f5f9; }
    .nav-menu { padding: 20px 15px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; margin-bottom: 5px; color: #64748b; border-radius: 10px; font-weight: 500; transition: 0.3s; text-decoration: none; }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3); }
    .main-content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }

    /* STYLE TAB NAVIGASI */
    .tabs-header { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-btn {
        padding: 10px 20px; border: none; background: transparent; 
        font-weight: 600; color: #64748b; cursor: pointer; border-radius: 8px;
        transition: 0.2s; font-size: 14px;
    }
    .tab-btn:hover { background: #f1f5f9; color: var(--primary); }
    .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(236, 72, 153, 0.2); }

    /* STYLE KONTEN TAB */
    .tab-content { display: none; animation: fadeIn 0.3s; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* KARTU & INPUT */
    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid #f1f5f9; }
    h3 { margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
    
    label { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 5px; display: block; }
    input, select, textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1;
        border-radius: 8px; margin-bottom: 15px; font-size: 13px;
        background: #f8fafc; transition: 0.3s; box-sizing: border-box;
    }
    input:focus, select:focus { outline: none; border-color: var(--primary); background: white; }

    /* STYLE LIST ITEM */
    .list-group { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; max-height: 400px; overflow-y: auto; }
    .list-item { 
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    }
    .list-item:hover { border-color: var(--primary); background: #fdf2f8; }
    .btn-delete { 
        background: #fee2e2; color: #ef4444; width: 30px; height: 30px; 
        display: flex; align-items: center; justify-content: center; 
        border-radius: 6px; text-decoration: none; font-weight: bold;
    }
    .btn-delete:hover { background: #ef4444; color: white; }

    /* STYLE KHUSUS PROMO CHECKBOX */
    .checkbox-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px; max-height: 150px; overflow-y: auto;
        border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; background: #f8fafc; margin-bottom: 15px;
    }
    .chk-item {
        display: flex; align-items: center; gap: 8px; background: white; padding: 8px;
        border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; font-size: 12px;
    }
    .chk-item:hover { border-color: var(--primary); }
    .chk-item input { width: auto; margin: 0; }

    .btn-primary { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    .btn-primary:hover { background: #be185d; }
    .btn-mini { width: auto; padding: 10px 15px; margin-left: 10px; height: 42px; margin-bottom: 15px; }

</style>

<div class="app-layout">
    
    <aside class="sidebar">
        <div class="brand-area">
            <div style="font-size: 40px;">🌸</div>
            <h3>Kasir Salon</h3>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">🏠 Dashboard</a>
            <a href="laporan.php" class="nav-item">📊 Laporan</a>
            <a href="kelola_user.php" class="nav-item">👥 Pengguna</a>
            <a href="pengaturan.php" class="nav-item active">⚙️ Pengaturan</a>
            <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 30px;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0 0 20px 0;">Pengaturan Toko</h2>
        </div>

        <div class="tabs-header">
            <button onclick="switchTab('umum')" id="btn-umum" class="tab-btn <?= $active_tab=='umum'?'active':'' ?>">🏪 Toko & Pembayaran</button>
            <button onclick="switchTab('layanan')" id="btn-layanan" class="tab-btn <?= $active_tab=='layanan'?'active':'' ?>">💇‍♀️ Layanan & Harga</button>
            <button onclick="switchTab('staff')" id="btn-staff" class="tab-btn <?= $active_tab=='staff'?'active':'' ?>">👥 Terapis / Staff</button>
            <button onclick="switchTab('promo')" id="btn-promo" class="tab-btn <?= $active_tab=='promo'?'active':'' ?>">🎉 Promo & Diskon</button>
        </div>

        <div id="tab-umum" class="tab-content <?= $active_tab=='umum'?'active':'' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <h3>🏠 Identitas Struk</h3>
                    <form method="POST">
                        <label>Nama Salon</label>
                        <input type="text" name="nama" value="<?= $toko['nama_toko'] ?>" required>
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" rows="2"><?= $toko['alamat_toko'] ?></textarea>
                        <label>Pesan Footer (Bawah Struk)</label>
                        <input type="text" name="footer" value="<?= $toko['pesan_footer'] ?>">
                        <button type="submit" name="simpan_toko" class="btn-primary">💾 Simpan Info</button>
                    </form>
                </div>

                <div class="card">
                    <h3>💳 Metode Pembayaran</h3>
                    <form method="POST" style="display:flex; align-items:flex-start;">
                        <input type="text" name="nama" placeholder="Contoh: QRIS / OVO / Dana" required>
                        <button type="submit" name="add_metode" class="btn-primary btn-mini">Tambah</button>
                    </form>
                    <div class="list-group">
                        <?php foreach($metode as $m): ?>
                        <div class="list-item">
                            <span><?= $m['nama_metode'] ?></span>
                            <a href="?del_metode=<?= $m['id'] ?>" class="btn-delete" onclick="return confirm('Hapus metode ini?')">🗑️</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-layanan" class="tab-content <?= $active_tab=='layanan'?'active':'' ?>">
            <div class="card">
                <h3>✂️ Daftar Layanan Salon</h3>
                <form method="POST" style="display:flex; gap:10px; align-items:flex-start;">
                    <div style="flex:2"><input type="text" name="nama" placeholder="Nama Layanan (Misal: Creambath)" required></div>
                    <div style="flex:1"><input type="number" name="harga" placeholder="Harga (Rp)" required></div>
                    <button type="submit" name="add_layanan" class="btn-primary btn-mini">➕ Tambah</button>
                </form>

                <div class="list-group">
                    <?php foreach($layanan as $l): ?>
                    <div class="list-item">
                        <div style="display:flex; flex-direction:column;">
                            <span style="font-weight:bold;"><?= $l['nama_layanan'] ?></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:15px;">
                            <span style="color:var(--primary); font-weight:bold;">Rp <?= number_format($l['harga_default']) ?></span>
                            <a href="?del_layanan=<?= $l['id'] ?>" class="btn-delete" onclick="return confirm('Hapus layanan ini?')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="tab-staff" class="tab-content <?= $active_tab=='staff'?'active':'' ?>">
            <div class="card" style="max-width: 600px;">
                <h3>💇‍♀️ Daftar Terapis / Stylist</h3>
                <form method="POST" style="display:flex; align-items:flex-start;">
                    <input type="text" name="nama" placeholder="Nama Panggilan Staff..." required>
                    <button type="submit" name="add_terapis" class="btn-primary btn-mini">Tambah</button>
                </form>
                <div class="list-group">
                    <?php foreach($terapis as $t): ?>
                    <div class="list-item">
                        <span>👤 <?= $t['nama_terapis'] ?></span>
                        <a href="?del_terapis=<?= $t['id'] ?>" class="btn-delete" onclick="return confirm('Hapus terapis ini?')">🗑️</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="tab-promo" class="tab-content <?= $active_tab=='promo'?'active':'' ?>">
            <div style="display: grid; grid-template-columns: 400px 1fr; gap: 20px;">
                
                <div class="card" style="background: #fffbeb; border:1px solid #fcd34d;">
                    <h3 style="color:#b45309">🎉 Buat Promo Baru</h3>
                    <form method="POST">
                        <label>Nama Promo</label>
                        <input type="text" name="nama" placeholder="Contoh: Diskon Kemerdekaan" required>
                        
                        <div style="display:flex; gap:10px;">
                            <div style="width:100px;">
                                <label>Tipe</label>
                                <select name="jenis">
                                    <option value="persen">Persen (%)</option>
                                    <option value="nominal">Rupiah (Rp)</option>
                                </select>
                            </div>
                            <div style="flex:1;">
                                <label>Nilai Potongan</label>
                                <input type="number" name="nilai" placeholder="Contoh: 10 atau 5000" required>
                            </div>
                        </div>

                        <label>Pilih Layanan yang Didiskon (Centang di bawah):</label>
                        <div class="checkbox-grid">
                            <?php 
                            // Trik: Reset pointer array layanan biar bisa dipake lagi
                            $layanan = $db->query("SELECT * FROM master_layanan")->fetchAll(); 
                            foreach($layanan as $l): ?>
                            <label class="chk-item">
                                <input type="checkbox" name="target[]" value="<?= $l['nama_layanan'] ?>">
                                <?= $l['nama_layanan'] ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <label>Berlaku Sampai Tanggal:</label>
                        <input type="datetime-local" name="tgl" required>
                        
                        <button type="submit" name="add_promo" class="btn-primary" style="background:#f59e0b;">🔥 Aktifkan Promo</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Daftar Promo Aktif</h3>
                    <div class="list-group">
                        <?php if(empty($promo)): ?>
                            <div style="text-align:center; padding:20px; color:gray;">Belum ada promo aktif</div>
                        <?php endif; ?>

                        <?php foreach($promo as $p): ?>
                        <div class="list-item" style="display:block;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                <b style="color:#b45309"><?= $p['nama_promo'] ?></b>
                                <a href="?del_promo=<?= $p['id'] ?>" class="btn-delete" onclick="return confirm('Hapus promo ini?')">🗑️</a>
                            </div>
                            <div style="font-size:12px; color:#666;">
                                Potongan: <b><?= $p['jenis_diskon']=='persen' ? $p['nilai_diskon'].'%' : 'Rp '.number_format($p['nilai_diskon']) ?></b>
                            </div>
                            <div style="font-size:11px; background:#eee; padding:5px; margin-top:5px; border-radius:4px;">
                                Target: <?= $p['target_layanan'] ?>
                            </div>
                            <div style="font-size:11px; color:red; margin-top:5px;">
                                Exp: <?= date('d M Y H:i', strtotime($p['berlaku_sampai'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<script>
    // Script Sederhana untuk Pindah Tab
    function switchTab(tabName) {
        // Sembunyikan semua konten
        var contents = document.getElementsByClassName('tab-content');
        for(var i=0; i<contents.length; i++) {
            contents[i].classList.remove('active');
        }
        // Matikan semua tombol aktif
        var btns = document.getElementsByClassName('tab-btn');
        for(var i=0; i<btns.length; i++) {
            btns[i].classList.remove('active');
        }

        // Aktifkan yang dipilih
        document.getElementById('tab-' + tabName).classList.add('active');
        document.getElementById('btn-' + tabName).classList.add('active');
        
        // Update URL biar kalau di-refresh tetap di tab itu
        var url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
</script>

</body>
</html>