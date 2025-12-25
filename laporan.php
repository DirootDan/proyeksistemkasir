<?php
// 1. KONEKSI DATABASE
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. SETTING WAKTU
date_default_timezone_set('Asia/Jakarta');
$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');
$tahun_ini = date('Y');


// Hapus 
if (isset($_POST['hapus_pilihan'])) {
    if (!empty($_POST['pilih'])) {
        
        $ids = $_POST['pilih'];
        
        $list_id = implode(',', $ids);
        
        $db->exec("DELETE FROM transaksi WHERE id IN ($list_id)");
        
        echo "<script>alert('Data terpilih berhasil dihapus!');</script>";
    } else {
        echo "<script>alert('Tidak ada data yang dipilih!');</script>";
    }
}

// fitur hapus semua
if (isset($_POST['hapus_semua'])) {
    // Kosongkan tabel transaksi
    $db->exec("DELETE FROM transaksi");
    // Reset nomor ID biar kembali ke 1 (Opsional, biar rapi)
    $db->exec("DELETE FROM sqlite_sequence WHERE name='transaksi'");
    
    echo "<script>alert('SEMUA RIWAYAT TELAH DIHAPUS BERSIH!');</script>";
}


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
        
        /* Tombol & Navigasi */
        .btn-back { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        .btn-danger { background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-warning { background: #ffc107; color: #333; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        /* Tabel */
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:hover { background-color: #f1f1f1; }
        
        /* Checkbox  */
        input[type=checkbox] { transform: scale(1.3); cursor: pointer; }
        
        .action-bar { display: flex; justify-content: space-between; align-items: center; background: #e9ecef; padding: 10px; border-radius: 5px; }
    </style>
    
    <script>
        
        function toggle(source) {
            checkboxes = document.getElementsByName('pilih[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
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

    <form method="POST">
        
        <h3>📂 Kelola Riwayat Transaksi</h3>
        
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" onclick="toggle(this)">
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
                        <input type="checkbox" name="pilih[]" value="<?= $row['id'] ?>">
                    </td>
                    <td><?= $row['tanggal'] ?> <small>(<?= $row['jam'] ?>)</small></td>
                    <td><?= $row['nama_pelanggan'] ?></td>
                    <td><?= $row['jenis_layanan'] ?></td>
                    <td style="font-weight: bold;">Rp <?= number_format($row['total_bayar']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    </form> </div>

</body>
</html>