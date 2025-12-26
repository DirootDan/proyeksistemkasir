<?php
// 1. KONEKSI DATABASE
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. SETTING WAKTU
date_default_timezone_set('Asia/Jakarta');
$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');
$tahun_ini = date('Y');

// --- LOGIKA HAPUS (Backend) ---
if (isset($_POST['hapus_pilihan'])) {
    if (!empty($_POST['pilih'])) {
        $ids = $_POST['pilih'];
        $list_id = implode(',', $ids);
        $db->exec("DELETE FROM transaksi WHERE id IN ($list_id)");
        
        // Reset sequence jika kosong (opsional)
        $cek_sisa = $db->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
        if ($cek_sisa == 0) {
            $db->exec("DELETE FROM sqlite_sequence WHERE name='transaksi'");
        }
        echo "<script>alert('Data terpilih berhasil dihapus!'); window.location=window.location.href;</script>";
    } else {
        echo "<script>alert('Tidak ada data yang dipilih!');</script>";
    }
}

// --- QUERY DATA STATISTIK ---
$q_harian = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE tanggal = '$hari_ini'");
$total_harian = $q_harian->fetch()['total'] ?: 0;

$q_bulanan = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE strftime('%Y-%m', tanggal) = '$bulan_ini'");
$total_bulanan = $q_bulanan->fetch()['total'] ?: 0;

$q_tahunan = $db->query("SELECT SUM(total_bayar) as total FROM transaksi WHERE strftime('%Y', tanggal) = '$tahun_ini'");
$total_tahunan = $q_tahunan->fetch()['total'] ?: 0;

// Ambil Semua Data
$semua_data = $db->query("SELECT * FROM transaksi ORDER BY tanggal DESC, jam DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan & Riwayat</title>
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; background-color: #f4f4f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Dashboard Kartu */
        .dashboard { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .card h3 { margin: 0; color: #555; font-size: 14px; text-transform: uppercase; }
        .card p { font-size: 24px; font-weight: bold; color: #28a745; margin: 10px 0 0; }
        
        /* Navigasi */
        .btn-back { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        
        /* Area Header Tabel (Flexbox untuk tombol Kanan/Kiri) */
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .header-area h3 { margin: 0; }
        .button-group { display: flex; gap: 10px; }

        /* Styling Tombol Hapus & Excel */
        #btnHapus {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        #btnHapus:disabled { background-color: #cccccc; cursor: not-allowed; }
        
        .btn-excel {
            padding: 10px 20px;
            background-color: #218838; /* Warna Hijau Excel */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            border: none;
        }
        .btn-excel:hover { background-color: #1e7e34; }
        
        /* Tabel */
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:hover { background-color: #f1f1f1; }
        
        /* Checkbox  */
        input[type=checkbox] { transform: scale(1.3); cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>
    
    <div class="dashboard">
        <div class="card">
            <h3>Hari Ini</h3>
            <p>Rp <?= number_format($total_harian) ?></p>
        </div>
        <div class="card">
            <h3>Bulan Ini</h3>
            <p>Rp <?= number_format($total_bulanan) ?></p>
        </div>
        <div class="card">
            <h3>Tahun Ini</h3>
            <p>Rp <?= number_format($total_tahunan) ?></p>
        </div>
    </div>

    <form method="POST" id="formRiwayat">
        
        <div class="header-area">
            <h3>📂 Kelola Riwayat Transaksi</h3>
            
            <div class="button-group">
                <a href="export_excel.php" target="_blank" class="btn-excel">
                    📥 Download Excel
                </a>

                <button type="submit" name="hapus_pilihan" id="btnHapus" disabled>
                    Hapus
                </button>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="checkAll">
                    </th>
                    <th>Tanggal</th>
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
                    <td><?= $row['tanggal'] ?> <small>(<?= $row['jam'] ?>)</small></td>
                    <td><?= $row['nama_pelanggan'] ?></td>
                    <td><?= $row['jenis_layanan'] ?></td>
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
        const totalItems = checkItems.length;

        if (checkedCount === 0) {
            btnHapus.textContent = "Hapus";
            btnHapus.disabled = true;
            btnHapus.style.backgroundColor = "#cccccc"; 
        } 
        else if (checkedCount === totalItems) {
            btnHapus.textContent = "Hapus Semua Data";
            btnHapus.disabled = false;
            btnHapus.style.backgroundColor = "#dc3545"; 
        } 
        else {
            btnHapus.textContent = "Hapus (" + checkedCount + ") Item";
            btnHapus.disabled = false;
            btnHapus.style.backgroundColor = "#ffc107"; 
            btnHapus.style.color = "black";
        }
    }

    checkAll.addEventListener('change', function() {
        const isChecked = this.checked;
        checkItems.forEach(item => { item.checked = isChecked; });
        updateButtonState();
    });

    checkItems.forEach(item => {
        item.addEventListener('change', function() {
            if (!this.checked) checkAll.checked = false;
            if (document.querySelectorAll('.checkItem:checked').length === checkItems.length) checkAll.checked = true;
            updateButtonState();
        });
    });

    btnHapus.addEventListener('click', function(e) {
        if(!confirm('Apakah Anda yakin ingin menghapus data ini?')) e.preventDefault();
    });
</script>

</body>
</html>