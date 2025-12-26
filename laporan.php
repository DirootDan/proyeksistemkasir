<?php
// 1. KONEKSI DATABASE
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. SETTING WAKTU
date_default_timezone_set('Asia/Jakarta');
$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');
$tahun_ini = date('Y');

// --- LOGIKA HAPUS DAN RENUMBERING (PENTING!) ---
if (isset($_POST['hapus_pilihan'])) {
    if (!empty($_POST['pilih'])) {
        $ids = $_POST['pilih'];
        $list_id = implode(',', $ids);
        
        // 1. Hapus Data
        $db->exec("DELETE FROM transaksi WHERE id IN ($list_id)");
        
        // 2. LOGIKA RENUMBERING (DEKREMEN OTOMATIS)
        // Ambil semua data sisa, urutkan berdasarkan waktu masuk (id/tanggal)
        $sisa_data = $db->query("SELECT id FROM transaksi ORDER BY tanggal ASC, jam ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $counter = 1;
        // Loop dan update nomor nota satu per satu agar urut kembali dari 1
        foreach($sisa_data as $row) {
            $id_update = $row['id'];
            $db->exec("UPDATE transaksi SET no_nota = $counter WHERE id = $id_update");
            $counter++;
        }

        // Reset sequence jika kosong total
        $cek_sisa = $db->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
        if ($cek_sisa == 0) {
            $db->exec("DELETE FROM sqlite_sequence WHERE name='transaksi'");
        }
        
        echo "<script>alert('Data terhapus & Nomor Nota telah diurutkan ulang!'); window.location=window.location.href;</script>";
    } else {
        echo "<script>alert('Tidak ada data yang dipilih!');</script>";
    }
}

// QUERY STATISTIK
$q_harian = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE tanggal = '$hari_ini'");
$total_harian = $q_harian->fetch()['total'] ?: 0;

$q_bulanan = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE strftime('%Y-%m', tanggal) = '$bulan_ini'");
$total_bulanan = $q_bulanan->fetch()['total'] ?: 0;

$q_tahunan = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE strftime('%Y', tanggal) = '$tahun_ini'");
$total_tahunan = $q_tahunan->fetch()['total'] ?: 0;

// AMBIL SEMUA DATA
$semua_data = $db->query("SELECT * FROM transaksi ORDER BY tanggal DESC, jam DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan & Riwayat</title>
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; background-color: #f4f4f9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .dashboard { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .card h3 { margin: 0; color: #555; font-size: 14px; text-transform: uppercase; }
        .card p { font-size: 24px; font-weight: bold; color: #28a745; margin: 10px 0 0; }
        .btn-back { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        
        .download-panel { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; border-left: 5px solid #28a745; }
        .date-group { display: flex; align-items: center; gap: 10px; }
        .date-group input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-excel { padding: 8px 15px; background-color: #218838; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; border: none; cursor: pointer; }
        
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        #btnHapus { padding: 8px 15px; border-radius: 5px; border: none; color: white; font-weight: bold; cursor: pointer; }
        #btnHapus:disabled { background-color: #cccccc; cursor: not-allowed; }
        
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:hover { background-color: #f1f1f1; }
        input[type=checkbox] { transform: scale(1.3); cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>
    
    <div class="dashboard">
        <div class="card"><h3>Hari Ini</h3> <p>Rp <?= number_format($total_harian) ?></p></div>
        <div class="card"><h3>Bulan Ini</h3> <p>Rp <?= number_format($total_bulanan) ?></p></div>
        <div class="card"><h3>Tahun Ini</h3> <p>Rp <?= number_format($total_tahunan) ?></p></div>
    </div>

    <div class="download-panel">
        <div style="font-weight: bold; color: #28a745;">📥 Download Laporan Excel</div>
        <form action="export_excel.php" method="GET" target="_blank" class="date-group">
            <label>Dari:</label> <input type="date" name="tgl_mulai" required value="<?= date('Y-m-01') ?>">
            <label>Sampai:</label> <input type="date" name="tgl_selesai" required value="<?= date('Y-m-d') ?>">
            <button type="submit" class="btn-excel">Download Per Periode</button>
        </form>
        <a href="export_excel.php" target="_blank" style="color: #666; font-size: 12px; text-decoration: underline;">Download Semua Riwayat</a>
    </div>

    <form method="POST" id="formRiwayat">
        <div class="header-area">
            <h3>📂 Kelola Riwayat Transaksi</h3>
            <button type="submit" name="hapus_pilihan" id="btnHapus" disabled>Hapus</button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="checkAll"></th>
                    <th style="background-color: #0069d9;">No. Nota</th> <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Total (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($semua_data as $row): ?>
                <tr>
                    <td style="text-align: center;">
                        <input type="checkbox" name="pilih[]" value="<?= $row['id'] ?>" class="checkItem">
                    </td>
                    <td style="font-weight: bold; color: #007bff;">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td><?= $row['tanggal'] ?> <small>(<?= $row['jam'] ?>)</small></td>
                    <td><?= $row['nama_pelanggan'] ?></td>
                    <td><small><?= $row['jenis_layanan'] ?></small></td>
                    <td style="font-weight: bold;">Rp <?= number_format($row['total_bayar']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form> 
</div>

<script>
    const checkAll = document.getElementById('checkAll');
    const checkItems = document.querySelectorAll('.checkItem');
    const btnHapus = document.getElementById('btnHapus');

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.checkItem:checked').length;
        if (checkedCount === 0) {
            btnHapus.textContent = "Hapus"; btnHapus.disabled = true; btnHapus.style.backgroundColor = "#cccccc"; 
        } else {
            btnHapus.textContent = "Hapus (" + checkedCount + ")"; btnHapus.disabled = false; btnHapus.style.backgroundColor = "#dc3545"; 
        }
    }

    checkAll.addEventListener('change', function() {
        const isChecked = this.checked;
        checkItems.forEach(item => { item.checked = isChecked; });
        updateButtonState();
    });

    checkItems.forEach(item => {
        item.addEventListener('change', function() { updateButtonState(); });
    });

    btnHapus.addEventListener('click', function(e) {
        if(!confirm('Yakin ingin menghapus? Nomor nota akan diurutkan ulang otomatis!')) e.preventDefault();
    });
</script>

</body>
</html>