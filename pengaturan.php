<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// TABEL PROMO (Kolom target_layanan akan berisi teks koma: "Layanan A,Layanan B")
$db->exec("CREATE TABLE IF NOT EXISTS daftar_promo (
    id INTEGER PRIMARY KEY,
    nama_promo TEXT,
    jenis_diskon TEXT,
    nilai_diskon INTEGER,
    target_layanan TEXT, 
    berlaku_sampai DATETIME
)");

// LOGIKA SIMPAN IDENTITAS
if (isset($_POST['simpan_identitas'])) {
    $stmt = $db->prepare("UPDATE info_toko SET nama_toko=?, alamat_toko=?, pesan_footer=? WHERE id=1");
    $stmt->execute([$_POST['nama_toko'], $_POST['alamat_toko'], $_POST['pesan_footer']]);
    echo "<script>alert('Identitas Toko Berhasil Diupdate!'); window.location='pengaturan.php';</script>";
}

// LOGIKA TAMBAH PROMO (MULTI TARGET)
if (isset($_POST['tambah_promo'])) {
    $nama = $_POST['nama_promo'];
    $jenis = $_POST['jenis_diskon'];
    $nilai = $_POST['nilai_diskon'];
    $sampai = str_replace("T", " ", $_POST['berlaku_sampai']);
    
    // Ambil array checklist, gabungkan jadi satu string dipisah koma
    $target_string = isset($_POST['target_layanan']) ? implode(',', $_POST['target_layanan']) : '';

    if(empty($target_string)) {
        echo "<script>alert('Pilih minimal satu layanan!');</script>";
    } else {
        $stmt = $db->prepare("INSERT INTO daftar_promo (nama_promo, jenis_diskon, nilai_diskon, target_layanan, berlaku_sampai) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $jenis, $nilai, $target_string, $sampai]);
        header("Location: pengaturan.php");
    }
}

// LOGIKA HAPUS PROMO
if (isset($_GET['hapus_promo'])) {
    $db->exec("DELETE FROM daftar_promo WHERE id = {$_GET['hapus_promo']}");
    header("Location: pengaturan.php");
}

// LOGIKA TAMBAH/HAPUS ITEM LAIN
if (isset($_POST['tambah_layanan'])) {
    $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('{$_POST['nama_layanan']}', '{$_POST['harga']}')"); header("Location: pengaturan.php");
}
if (isset($_GET['hapus_layanan'])) {
    $db->exec("DELETE FROM master_layanan WHERE id = {$_GET['hapus_layanan']}"); header("Location: pengaturan.php");
}
if (isset($_POST['tambah_metode'])) {
    $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('{$_POST['nama_metode']}')"); header("Location: pengaturan.php");
}
if (isset($_GET['hapus_metode'])) {
    $db->exec("DELETE FROM master_metode WHERE id = {$_GET['hapus_metode']}"); header("Location: pengaturan.php");
}

// AMBIL DATA
$info_toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();
$list_layanan = $db->query("SELECT * FROM master_layanan");
$list_metode = $db->query("SELECT * FROM master_metode");
$list_promo = $db->query("SELECT * FROM daftar_promo ORDER BY berlaku_sampai ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pengaturan Salon</title>
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; padding: 20px; background: #f4f4f9; }
        .container { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        h3 { border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-top: 0; }
        label { font-size: 14px; font-weight: bold; color: #555; display: block; margin-top: 10px; }
        input, select, button { padding: 10px; margin-top: 5px; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { color: white; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        button:hover { opacity: 0.8; }
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #555; font-weight: bold; padding: 10px 20px; background: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-x { background: #ffcccc; color: #dc3545; width: 25px; height: 25px; display: flex; justify-content: center; align-items: center; border-radius: 50%; text-decoration: none; font-weight: bold; }

        /* GRID LIST */
        .grid-list-area { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; max-height: 200px; overflow-y: auto; padding-right: 5px; }
        .item-card { background: #f8f9fa; border: 1px solid #eee; border-radius: 6px; padding: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
        
        /* PROMO CARD */
        .promo-card { background: #fff3cd; border: 1px solid #ffeeba; border-radius: 6px; padding: 10px; margin-bottom: 10px; position: relative; }
        .promo-title { font-weight: bold; color: #856404; display: block; }
        .promo-detail { font-size: 12px; color: #555; margin-top: 5px; }
        .promo-targets { background: white; padding: 3px 6px; border-radius: 4px; border: 1px solid #eee; font-size: 11px; display: inline-block; margin-top: 5px; color: #333; }
        
        /* MODAL POPUP STYLE */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 20px; border-radius: 10px; width: 500px; max-width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: popup 0.3s ease-out; }
        @keyframes popup { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        .modal-header { font-size: 18px; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; }
        .modal-close { cursor: pointer; color: red; font-size: 20px; }
        .checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 250px; overflow-y: auto; border: 1px solid #f0f0f0; padding: 10px; }
        .check-item { display: flex; align-items: center; background: #fffdf5; padding: 8px; border-radius: 4px; border: 1px solid #eee; cursor: pointer; }
        .check-item:hover { background: #fff8e1; }
        .check-item input { margin-right: 10px; transform: scale(1.2); }
    </style>
</head>
<body>

<a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>

<div class="container">
    
    <div class="box" style="border-top: 5px solid #ff007f;"> 
        <h3>🏠 Identitas Struk</h3>
        <form method="POST">
            <label>Nama Salon:</label> <input type="text" name="nama_toko" value="<?= $info_toko['nama_toko'] ?>" required>
            <label>Alamat:</label> <input type="text" name="alamat_toko" value="<?= $info_toko['alamat_toko'] ?>" required>
            <label>Footer Struk:</label> <input type="text" name="pesan_footer" value="<?= $info_toko['pesan_footer'] ?>" required>
            <button type="submit" name="simpan_identitas" style="background: #ff007f; margin-top: 20px;">💾 UPDATE IDENTITAS</button>
        </form>
    </div>

    <div class="box" style="border-top: 5px solid #ffc107;">
        <h3>🎉 Atur Daftar Promo</h3>
        
        <form method="POST" style="background: #fdfdfd; padding: 10px; border: 1px solid #eee; border-radius: 5px;">
            <label style="margin-top:0;">Nama Promo:</label>
            <input type="text" name="nama_promo" placeholder="Mis: Diskon Spesial" required>
            
            <label>Target Layanan :</label>
            <button type="button" onclick="bukaModal()" style="background: #17a2b8; color: white;">📋 Pilih Layanan (Checklist)</button>
            <div id="summaryDisplay" style="font-size: 11px; color: #666; margin-top: 5px; font-style: italic;">Belum ada layanan dipilih</div>

            <div id="modalLayanan" class="modal-overlay">
                <div class="modal-box">
                    <div class="modal-header">
                        <span>Centang Target Layanan</span>
                        <span class="modal-close" onclick="tutupModal()">&times;</span>
                    </div>
                    <div class="checkbox-grid">
                        <?php 
                        // Query layanan untuk checklist
                        $srv = $db->query("SELECT * FROM master_layanan");
                        foreach($srv as $s): ?>
                            <label class="check-item">
                                <input type="checkbox" name="target_layanan[]" value="<?= $s['nama_layanan'] ?>" onchange="updateSummary()">
                                <?= $s['nama_layanan'] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="tutupModal()" style="background: #28a745; margin-top: 15px;">Selesai Memilih</button>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <div style="flex: 1;">
                    <label>Tipe Diskon:</label>
                    <select name="jenis_diskon">
                        <option value="nominal">Rupiah (Rp)</option>
                        <option value="persen">Persen (%)</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Nilai:</label>
                    <input type="number" name="nilai_diskon" placeholder="Cth: 5000" required>
                </div>
            </div>
            
            <label>Berlaku Sampai:</label>
            <input type="datetime-local" name="berlaku_sampai" required>

            <button type="submit" name="tambah_promo" style="background: #ffc107; color: black; margin-top: 15px;">➕ TAMBAH PROMO</button>
        </form>

        <label>📋 Promo Aktif:</label>
        <div style="max-height: 250px; overflow-y: auto; margin-top: 10px;">
            <?php foreach($list_promo as $p): ?>
            <div class="promo-card">
                <span class="promo-title">
                    <?= $p['nama_promo'] ?> 
                    <span style="background: black; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px;">
                        <?= $p['jenis_diskon'] == 'nominal' ? 'Rp '.number_format($p['nilai_diskon']) : $p['nilai_diskon'].'%' ?> OFF
                    </span>
                </span>
                <div class="promo-detail">
                    <span class="promo-targets">🎯 <?= $p['target_layanan'] ?></span><br>
                    <span style="color: #d63384; font-size: 11px;">Exp: <?= date('d/m/Y H:i', strtotime($p['berlaku_sampai'])) ?></span>
                </div>
                <a href="?hapus_promo=<?= $p['id'] ?>" class="btn-x" style="position: absolute; top: 10px; right: 10px;" onclick="return confirm('Hapus promo ini?')">×</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="box" style="border-top: 5px solid #007bff;">
        <h3>✂️ Daftar Layanan</h3>
        <form method="POST">
            <input type="text" name="nama_layanan" placeholder="Nama Layanan" required>
            <input type="number" name="harga" placeholder="Harga" required>
            <button type="submit" name="tambah_layanan" style="background: #007bff;">TAMBAH</button>
        </form>
        <div class="grid-list-area">
            <?php foreach($list_layanan as $row): ?>
            <div class="item-card">
                <div><b><?= $row['nama_layanan'] ?></b><br><span style="color:#28a745">Rp <?= number_format($row['harga_default']) ?></span></div>
                <a href="?hapus_layanan=<?= $row['id'] ?>" class="btn-x" onclick="return confirm('Hapus?')">×</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="box" style="border-top: 5px solid #28a745;">
        <h3>💳 Metode Pembayaran</h3>
        <form method="POST">
            <input type="text" name="nama_metode" placeholder="Metode" required>
            <button type="submit" name="tambah_metode" style="background: #28a745;">TAMBAH</button>
        </form>
        <div class="grid-list-area">
            <?php foreach($list_metode as $row): ?>
            <div class="item-card">
                <b><?= $row['nama_metode'] ?></b>
                <a href="?hapus_metode=<?= $row['id'] ?>" class="btn-x" onclick="return confirm('Hapus?')">×</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
    function bukaModal() { document.getElementById('modalLayanan').style.display = 'flex'; }
    function tutupModal() { document.getElementById('modalLayanan').style.display = 'none'; }
    function updateSummary() {
        var checkboxes = document.querySelectorAll('input[name="target_layanan[]"]:checked');
        var values = [];
        checkboxes.forEach((checkbox) => { values.push(checkbox.value); });
        var displayDiv = document.getElementById('summaryDisplay');
        if (values.length > 0) {
            displayDiv.innerHTML = "Terpilih: " + values.join(", ");
            displayDiv.style.color = "#333";
        } else {
            displayDiv.innerHTML = "Belum ada layanan dipilih";
            displayDiv.style.color = "#999";
        }
    }
</script>
</body>
</html>