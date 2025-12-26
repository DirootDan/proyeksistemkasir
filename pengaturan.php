<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$db->exec("CREATE TABLE IF NOT EXISTS setting_promo (
    id INTEGER PRIMARY KEY,
    nama_promo TEXT,
    nominal_diskon INTEGER,
    berlaku_sampai DATETIME
)");

try {
    $db->query("SELECT layanan_promo FROM setting_promo LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE setting_promo ADD COLUMN layanan_promo TEXT DEFAULT ''");
}

$cek_promo = $db->query("SELECT COUNT(*) FROM setting_promo")->fetchColumn();
if ($cek_promo == 0) {
    $db->exec("INSERT INTO setting_promo (id, nama_promo, nominal_diskon, berlaku_sampai, layanan_promo) VALUES (1, 'Promo Grand Opening', 5000, '2025-12-31 23:59', '')");
}

// LOGIKA SIMPAN
if (isset($_POST['simpan_identitas'])) {
    $stmt = $db->prepare("UPDATE info_toko SET nama_toko=?, alamat_toko=?, pesan_footer=? WHERE id=1");
    $stmt->execute([$_POST['nama_toko'], $_POST['alamat_toko'], $_POST['pesan_footer']]);
    echo "<script>alert('Identitas Toko Berhasil Diupdate!'); window.location='pengaturan.php';</script>";
}

if (isset($_POST['simpan_promo'])) {
    $nama_p = $_POST['nama_promo'];
    $diskon_p = $_POST['nominal_diskon'];
    $sampai_p = str_replace("T", " ", $_POST['berlaku_sampai']);
    $layanan_terpilih = isset($_POST['layanan_promo']) ? implode(',', $_POST['layanan_promo']) : '';

    $stmt = $db->prepare("UPDATE setting_promo SET nama_promo=?, nominal_diskon=?, berlaku_sampai=?, layanan_promo=? WHERE id=1");
    $stmt->execute([$nama_p, $diskon_p, $sampai_p, $layanan_terpilih]);
    echo "<script>alert('Setting Promo Berhasil Disimpan!'); window.location='pengaturan.php';</script>";
}

// LOGIKA TAMBAH/HAPUS
if (isset($_POST['tambah_layanan'])) {
    $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('{$_POST['nama_layanan']}', '{$_POST['harga']}')");
    header("Location: pengaturan.php");
}
if (isset($_GET['hapus_layanan'])) {
    $db->exec("DELETE FROM master_layanan WHERE id = {$_GET['hapus_layanan']}");
    header("Location: pengaturan.php");
}
if (isset($_POST['tambah_metode'])) {
    $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('{$_POST['nama_metode']}')");
    header("Location: pengaturan.php");
}
if (isset($_GET['hapus_metode'])) {
    $db->exec("DELETE FROM master_metode WHERE id = {$_GET['hapus_metode']}");
    header("Location: pengaturan.php");
}

// AMBIL DATA
$info_toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();
$info_promo = $db->query("SELECT * FROM setting_promo WHERE id=1")->fetch();
$list_layanan = $db->query("SELECT * FROM master_layanan");
$list_metode = $db->query("SELECT * FROM master_metode");

$array_promo_aktif = explode(',', $info_promo['layanan_promo'] ?? '');
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
        input[type=text], input[type=number], input[type=datetime-local], button { padding: 10px; margin-top: 5px; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { color: white; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        button:hover { opacity: 0.8; }
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #555; font-weight: bold; padding: 10px 20px; background: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        /* --- STYLE GRID ITEMS (AGAR TIDAK MENUMPUK) --- */
        .grid-list-area {
            display: grid;
            grid-template-columns: 1fr 1fr; /* 2 Kolom */
            gap: 10px;
            margin-top: 15px;
            max-height: 200px; /* Batas tinggi agar bisa scroll */
            overflow-y: auto;
            padding-right: 5px;
        }

        .item-card {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        
        .item-info { display: flex; flex-direction: column; }
        .item-name { font-weight: bold; color: #333; }
        .item-price { color: #28a745; font-size: 12px; }

        .btn-x {
            background: #ffcccc; color: #dc3545; 
            width: 25px; height: 25px; 
            display: flex; justify-content: center; align-items: center;
            border-radius: 50%; text-decoration: none; font-weight: bold;
        }
        .btn-x:hover { background: #dc3545; color: white; }

        /* --- STYLE MODAL POPUP --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 999;
            justify-content: center; align-items: center;
        }
        .modal-box {
            background: white; padding: 20px; border-radius: 10px;
            width: 500px; max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: popup 0.3s ease-out;
        }
        @keyframes popup { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        
        .modal-header { font-size: 18px; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; }
        .modal-close { cursor: pointer; color: red; font-size: 20px; }
        
        /* Grid di dalam Modal Promo juga */
        .checkbox-grid { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            max-height: 250px; overflow-y: auto; border: 1px solid #f0f0f0; padding: 10px; 
        }
        .check-item { 
            display: flex; align-items: center; background: #fffdf5; 
            padding: 8px; border-radius: 4px; border: 1px solid #eee; cursor: pointer; 
        }
        .check-item:hover { background: #fff8e1; }
        .check-item input { margin-right: 10px; transform: scale(1.2); }

        .summary-text { font-size: 12px; color: #666; margin-top: 5px; font-style: italic; background: #fffdf5; padding: 8px; border: 1px solid #eee; border-radius: 4px; }
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
        <h3>🎉 Atur Promo Spesial</h3>
        <form method="POST">
            <label>Nama Event:</label> 
            <input type="text" name="nama_promo" value="<?= $info_promo['nama_promo'] ?>" required>
            <label>Potongan (Rp):</label> 
            <input type="number" name="nominal_diskon" value="<?= $info_promo['nominal_diskon'] ?>" required>
            <label>Berlaku Sampai:</label>
            <?php $tgl_value = str_replace(" ", "T", $info_promo['berlaku_sampai']); ?>
            <input type="datetime-local" name="berlaku_sampai" value="<?= $tgl_value ?>" required>

            <label>Pilih Layanan Diskon:</label>
            <button type="button" onclick="bukaModal()" style="background: #17a2b8; color: white;">📋 Klik untuk Memilih (Checklist)</button>
            <div id="summaryDisplay" class="summary-text">
                <?= $info_promo['layanan_promo'] ? 'Terpilih: '.$info_promo['layanan_promo'] : 'Belum ada layanan dipilih' ?>
            </div>

            <div id="modalLayanan" class="modal-overlay">
                <div class="modal-box">
                    <div class="modal-header">
                        <span>Centang Layanan Promo</span>
                        <span class="modal-close" onclick="tutupModal()">&times;</span>
                    </div>
                    <div class="checkbox-grid">
                        <?php 
                        $services = $db->query("SELECT * FROM master_layanan");
                        foreach($services as $s): 
                            $checked = in_array($s['nama_layanan'], $array_promo_aktif) ? 'checked' : '';
                        ?>
                            <label class="check-item">
                                <input type="checkbox" name="layanan_promo[]" value="<?= $s['nama_layanan'] ?>" <?= $checked ?> onchange="updateSummary()">
                                <?= $s['nama_layanan'] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="tutupModal()" style="background: #28a745; margin-top: 15px;">Selesai Memilih</button>
                </div>
            </div>
            <button type="submit" name="simpan_promo" style="background: #ffc107; color: #333; margin-top: 20px;">💾 UPDATE PROMO</button>
        </form>
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
                <div class="item-info">
                    <span class="item-name"><?= $row['nama_layanan'] ?></span>
                    <span class="item-price">Rp <?= number_format($row['harga_default']) ?></span>
                </div>
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
                <div class="item-info">
                    <span class="item-name"><?= $row['nama_metode'] ?></span>
                </div>
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
        var checkboxes = document.querySelectorAll('input[name="layanan_promo[]"]:checked');
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