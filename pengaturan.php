<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 1. SETUP DATABASE ---
// Tabel Promo
$db->exec("CREATE TABLE IF NOT EXISTS daftar_promo (
    id INTEGER PRIMARY KEY,
    nama_promo TEXT,
    jenis_diskon TEXT,
    nilai_diskon INTEGER,
    target_layanan TEXT, 
    berlaku_sampai DATETIME
)");

// Tabel Member Special (Manual VVIP)
$db->exec("CREATE TABLE IF NOT EXISTS member_special (
    id INTEGER PRIMARY KEY,
    nama_pelanggan TEXT UNIQUE
)");

// Update Info Toko (Kolom Syarat VVIP)
try { $db->query("SELECT vvip_visit FROM info_toko LIMIT 1"); } 
catch (Exception $e) {
    $db->exec("ALTER TABLE info_toko ADD COLUMN vvip_visit INTEGER DEFAULT 10");
    $db->exec("ALTER TABLE info_toko ADD COLUMN vvip_spend INTEGER DEFAULT 2000000");
}

// --- LOGIKA SIMPAN ---

// 1. Simpan Identitas & Syarat VVIP
if (isset($_POST['simpan_identitas'])) {
    $stmt = $db->prepare("UPDATE info_toko SET nama_toko=?, alamat_toko=?, pesan_footer=?, vvip_visit=?, vvip_spend=? WHERE id=1");
    $stmt->execute([$_POST['nama_toko'], $_POST['alamat_toko'], $_POST['pesan_footer'], $_POST['vvip_visit'], $_POST['vvip_spend']]);
    echo "<script>alert('Pengaturan Berhasil Diupdate!'); window.location='pengaturan.php';</script>";
}

// 2. Tambah Member Manual (VVIP)
if (isset($_POST['tambah_vvip'])) {
    $nama = trim($_POST['nama_vvip']);
    if(!empty($nama)) {
        // Cek duplikat
        $cek = $db->query("SELECT COUNT(*) FROM member_special WHERE nama_pelanggan='$nama'")->fetchColumn();
        if($cek == 0) {
            $db->exec("INSERT INTO member_special (nama_pelanggan) VALUES ('$nama')");
        }
    }
    header("Location: pengaturan.php");
}

// 3. Hapus Member Manual
if (isset($_GET['hapus_vvip'])) {
    $db->exec("DELETE FROM member_special WHERE id = {$_GET['hapus_vvip']}");
    header("Location: pengaturan.php");
}

// 4. Logika Promo & Layanan (Sama seperti sebelumnya)
if (isset($_POST['tambah_promo'])) {
    $target_string = isset($_POST['target_layanan']) ? implode(',', $_POST['target_layanan']) : '';
    $stmt = $db->prepare("INSERT INTO daftar_promo (nama_promo, jenis_diskon, nilai_diskon, target_layanan, berlaku_sampai) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['nama_promo'], $_POST['jenis_diskon'], $_POST['nilai_diskon'], $target_string, str_replace("T", " ", $_POST['berlaku_sampai'])]);
    header("Location: pengaturan.php");
}
if (isset($_GET['hapus_promo'])) { $db->exec("DELETE FROM daftar_promo WHERE id={$_GET['hapus_promo']}"); header("Location: pengaturan.php"); }
if (isset($_POST['tambah_layanan'])) { $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('{$_POST['nama_layanan']}', '{$_POST['harga']}')"); header("Location: pengaturan.php"); }
if (isset($_GET['hapus_layanan'])) { $db->exec("DELETE FROM master_layanan WHERE id={$_GET['hapus_layanan']}"); header("Location: pengaturan.php"); }
if (isset($_POST['tambah_metode'])) { $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('{$_POST['nama_metode']}')"); header("Location: pengaturan.php"); }
if (isset($_GET['hapus_metode'])) { $db->exec("DELETE FROM master_metode WHERE id={$_GET['hapus_metode']}"); header("Location: pengaturan.php"); }

// AMBIL DATA
$info_toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();
$list_layanan = $db->query("SELECT * FROM master_layanan");
$list_metode = $db->query("SELECT * FROM master_metode");
$list_promo = $db->query("SELECT * FROM daftar_promo ORDER BY berlaku_sampai ASC");
$list_vvip = $db->query("SELECT * FROM member_special ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pengaturan Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #ec4899; --bg-body: #f3f4f6; --white: #ffffff; --text-dark: #1f2937; --text-light: #6b7280; --danger: #ef4444; --success: #10b981; --warning: #f59e0b; --blue: #3b82f6; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); padding: 20px; margin: 0; color: var(--text-dark); }
        
        .header-page { max-width: 1200px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { text-decoration: none; color: var(--text-dark); font-weight: 600; background: var(--white); padding: 10px 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start; }
        .card { background: var(--white); border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 25px; }
        
        .card-header { display: flex; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; }
        .card-icon { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 12px; }
        .icon-pink { background: #fce7f3; color: var(--primary); }
        .icon-gold { background: #fffbeb; color: #d97706; }
        .icon-blue { background: #dbeafe; color: var(--blue); }
        
        h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text-dark); }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-light); margin-bottom: 5px; margin-top: 10px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; box-sizing: border-box; font-family: 'Poppins'; font-size: 13px; background: #f9fafb; }
        button { cursor: pointer; border: none; font-family: 'Poppins'; font-weight: 600; border-radius: 8px; }
        .btn-action { width: 100%; padding: 12px; margin-top: 15px; color: white; font-size: 14px; background: var(--primary); }
        
        /* List Style */
        .item-list { display: flex; flex-direction: column; gap: 8px; max-height: 250px; overflow-y: auto; margin-top: 15px; padding-right: 5px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; background: #f9fafb; padding: 10px; border-radius: 8px; border: 1px solid #f3f4f6; }
        .btn-del { width: 24px; height: 24px; border-radius: 50%; background: #fee2e2; color: var(--danger); display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 25px; border-radius: 15px; width: 450px; }
        .checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 300px; overflow-y: auto; margin-top: 15px; }
        .check-item { display: flex; align-items: center; padding: 8px; background: #f9fafb; border-radius: 6px; }
        .check-item input { margin-right: 8px; }
        
        @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="header-page">
    <h2 style="margin: 0;">⚙️ Pengaturan Salon</h2>
    <a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>
</div>

<div class="container">
    <div class="left-column">
        
        <div class="card">
            <div class="card-header"><div class="card-icon icon-pink">🏠</div><h3>Identitas & Syarat Member</h3></div>
            <form method="POST">
                <label>Nama Salon</label> <input type="text" name="nama_toko" value="<?= $info_toko['nama_toko'] ?>" required>
                <label>Alamat</label> <input type="text" name="alamat_toko" value="<?= $info_toko['alamat_toko'] ?>" required>
                <label>Footer Struk</label> <input type="text" name="pesan_footer" value="<?= $info_toko['pesan_footer'] ?>" required>
                
                <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                <h4 style="margin:0; font-size:13px; color:var(--primary);">Syarat Auto-VVIP (System)</h4>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1"><label>Min. Kunjungan</label><input type="number" name="vvip_visit" value="<?= $info_toko['vvip_visit'] ?>"></div>
                    <div style="flex:1"><label>Min. Belanja</label><input type="number" name="vvip_spend" value="<?= $info_toko['vvip_spend'] ?>"></div>
                </div>
                <button type="submit" name="simpan_identitas" class="btn-action">Simpan Perubahan</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-icon icon-gold">👑</div><h3>Member VVIP (Manual)</h3></div>
            <form method="POST" style="display: flex; gap: 10px;">
                <input type="text" name="nama_vvip" placeholder="Nama Pelanggan Setia..." required style="margin:0;">
                <button type="submit" name="tambah_vvip" style="background:#d97706; color:white; padding:0 15px;">+</button>
            </form>
            <div style="font-size:11px; color:#888; margin-top:5px;">*Orang di daftar ini akan selalu dianggap VVIP.</div>
            <div class="item-list">
                <?php foreach($list_vvip as $v): ?>
                <div class="item-row">
                    <span style="font-weight:600; color:#d97706;">👑 <?= $v['nama_pelanggan'] ?></span>
                    <a href="?hapus_vvip=<?= $v['id'] ?>" class="btn-del" onclick="return confirm('Hapus dari VVIP Manual?')">×</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <div class="right-column">
        <div class="card">
            <div class="card-header"><div class="card-icon icon-blue">🎉</div><h3>Atur Promo</h3></div>
            <form method="POST">
                <label style="margin-top:0">Nama Promo</label><input type="text" name="nama_promo" required>
                <label>Target</label><button type="button" onclick="document.getElementById('modalLayanan').style.display='flex'" style="width:100%; padding:10px; background:#e0e7ff; color:#3730a3; border:1px dashed #818cf8;">📋 Pilih Layanan</button>
                <div id="modalLayanan" class="modal-overlay">
                    <div class="modal-box">
                        <h3 style="margin-bottom:10px;">Pilih Target</h3>
                        <div class="checkbox-grid">
                            <?php 
                            $srv = $db->query("SELECT * FROM master_layanan");
                            foreach($srv as $s): ?>
                                <label class="check-item"><input type="checkbox" name="target_layanan[]" value="<?= $s['nama_layanan'] ?>"><?= $s['nama_layanan'] ?></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="document.getElementById('modalLayanan').style.display='none'" class="btn-action">Selesai</button>
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1"><label>Tipe</label><select name="jenis_diskon"><option value="nominal">Rp</option><option value="persen">%</option></select></div>
                    <div style="flex:1"><label>Nilai</label><input type="number" name="nilai_diskon" required></div>
                </div>
                <label>Berlaku Sampai</label><input type="datetime-local" name="berlaku_sampai" required>
                <button type="submit" name="tambah_promo" class="btn-action" style="background:#f59e0b; color:black;">Tambah Promo</button>
            </form>
            <div class="item-list">
                <?php foreach($list_promo as $p): ?>
                <div class="item-row">
                    <div><b><?= $p['nama_promo'] ?></b><br><span style="font-size:11px; color:#666;">Target: <?= $p['target_layanan'] ?></span></div>
                    <a href="?hapus_promo=<?= $p['id'] ?>" class="btn-del">×</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-icon icon-blue">✂️</div><h3>Daftar Layanan</h3></div>
            <form method="POST" style="display:flex; gap:10px;">
                <input type="text" name="nama_layanan" placeholder="Nama" required>
                <input type="number" name="harga" placeholder="Harga" required>
                <button type="submit" name="tambah_layanan" style="background:var(--primary); color:white; padding:0 15px;">+</button>
            </form>
            <div class="item-list">
                <?php foreach($list_layanan as $l): ?>
                <div class="item-row">
                    <span><?= $l['nama_layanan'] ?> <small style="color:var(--blue)">Rp <?= number_format($l['harga_default']) ?></small></span>
                    <a href="?hapus_layanan=<?= $l['id'] ?>" class="btn-del">×</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>