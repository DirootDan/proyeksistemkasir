<?php
// 1. KONEKSI DATABASE
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. SETTING WAKTU
date_default_timezone_set('Asia/Jakarta');
$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');
$tahun_ini = date('Y');

// --- LOGIKA HAPUS ---
if (isset($_POST['hapus_pilihan'])) {
    if (!empty($_POST['pilih'])) {
        $ids = $_POST['pilih'];
        $list_id = implode(',', $ids);
        $db->exec("DELETE FROM transaksi WHERE id IN ($list_id)");
        
        $sisa_data = $db->query("SELECT id FROM transaksi ORDER BY tanggal ASC, jam ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $counter = 1;
        foreach($sisa_data as $row) {
            $id_update = $row['id'];
            $db->exec("UPDATE transaksi SET no_nota = $counter WHERE id = $id_update");
            $counter++;
        }
        $cek_sisa = $db->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
        if ($cek_sisa == 0) { $db->exec("DELETE FROM sqlite_sequence WHERE name='transaksi'"); }
        
        echo "<script>alert('Data berhasil dihapus & dirapikan!'); window.location=window.location.href;</script>";
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
$semua_data = $db->query("SELECT * FROM transaksi ORDER BY no_nota DESC");

// ARRAY BULAN
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #ec4899; --bg-body: #f3f4f6; --white: #ffffff; --text-dark: #1f2937; --text-light: #6b7280; --success: #10b981; --danger: #ef4444; --blue: #3b82f6; --warning: #f59e0b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); padding: 20px; margin: 0; color: var(--text-dark); }
        .header-page { max-width: 1100px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { text-decoration: none; color: var(--text-dark); font-weight: 600; background: var(--white); padding: 10px 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.3s; }
        .btn-back:hover { background: var(--text-dark); color: white; }
        .container { max-width: 1100px; margin: 0 auto; }

        /* DASHBOARD */
        .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: var(--white); padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 15px; }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .icon-green { background: #d1fae5; color: #059669; } .icon-blue { background: #dbeafe; color: #2563eb; } .icon-purple { background: #ede9fe; color: #7c3aed; }
        .stat-info h4 { margin: 0; font-size: 12px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-info p { margin: 2px 0 0 0; font-size: 20px; font-weight: 700; color: var(--text-dark); }

        /* DOWNLOAD SECTION (3 KOLOM) */
        .download-area { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Otomatis menyesuaikan */
            gap: 20px; margin-bottom: 25px; 
        }
        .dl-card { background: var(--white); padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f3f4f6; display: flex; flex-direction: column; justify-content: space-between; }
        .dl-title { font-weight: 700; font-size: 14px; margin-bottom: 15px; display: block; color: var(--text-dark); border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .filter-form { display: flex; flex-direction: column; gap: 10px; }
        .row-group { display: flex; gap: 10px; }
        
        select, input[type=date] { width: 100%; border: 1px solid #d1d5db; padding: 10px; border-radius: 8px; font-family: inherit; font-size: 13px; color: var(--text-dark); background: #f9fafb; cursor: pointer; }
        select:focus, input:focus { outline: none; border-color: var(--primary); background: white; }

        .btn-excel { background: #10b981; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 5px; text-decoration: none; width: 100%; margin-top: 10px; transition: 0.2s; }
        .btn-excel:hover { background: #059669; }
        .btn-purple { background: #8b5cf6; } .btn-purple:hover { background: #7c3aed; }
        .btn-blue { background: #3b82f6; } .btn-blue:hover { background: #2563eb; }

        /* TABLE & DELETE */
        .table-action-bar { display: flex; justify-content: flex-end; margin-bottom: 10px; }
        .btn-delete { background: #fee2e2; color: var(--danger); border: 1px solid #fecaca; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.2s; }
        .btn-delete:hover:enabled { background: var(--danger); color: white; }
        .btn-delete:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

        .table-wrapper { background: var(--white); border-radius: 16px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { text-align: left; padding: 15px; color: var(--text-light); font-size: 12px; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #f3f4f6; }
        td { padding: 15px; border-bottom: 1px solid #f9fafb; font-size: 13px; color: var(--text-dark); vertical-align: middle; }
        .nota-badge { background: #eff6ff; color: var(--blue); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; }
        .price-text { font-weight: 600; font-size: 14px; }
        .date-text { color: var(--text-light); font-size: 12px; }
        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary); }

        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } .download-area { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="header-page">
        <h2 style="margin: 0; font-size: 24px;">📊 Laporan & Riwayat</h2>
        <a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>
    </div>

    <div class="container">
        
        <div class="dashboard-grid">
            <div class="stat-card"><div class="icon-box icon-green">📅</div><div class="stat-info"><h4>Omzet Hari Ini</h4><p>Rp <?= number_format($total_harian) ?></p></div></div>
            <div class="stat-card"><div class="icon-box icon-blue">🗓️</div><div class="stat-info"><h4>Omzet Bulan Ini</h4><p>Rp <?= number_format($total_bulanan) ?></p></div></div>
            <div class="stat-card"><div class="icon-box icon-purple">📈</div><div class="stat-info"><h4>Omzet Tahun Ini</h4><p>Rp <?= number_format($total_tahunan) ?></p></div></div>
        </div>

        <div class="download-area">
            
            <div class="dl-card">
                <span class="dl-title">📂 Laporan Bulanan</span>
                <form action="export_excel.php" method="GET" target="_blank" class="filter-form">
                    <div class="row-group">
                        <select name="bulan" required>
                            <?php $curMonth = date('m'); foreach($bulan_indo as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k == $curMonth ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="tahun" required>
                            <?php $curYear = date('Y'); for($y = $curYear - 2; $y <= $curYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-excel btn-green">📥 Download Bulan Ini</button>
                </form>
            </div>

            <div class="dl-card">
                <span class="dl-title">📅 Laporan Tahunan</span>
                <form action="export_excel.php" method="GET" target="_blank" class="filter-form">
                    <select name="tahun_saja" required>
                        <?php $curYear = date('Y'); for($y = $curYear - 2; $y <= $curYear + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn-excel btn-purple">📥 Download Satu Tahun</button>
                </form>
            </div>

            <div class="dl-card">
                <span class="dl-title">📆 Harian / Kustom</span>
                <form action="export_excel.php" method="GET" target="_blank" class="filter-form">
                    <div class="row-group">
                        <input type="date" name="tgl_mulai" required value="<?= date('Y-m-d') ?>">
                        <span style="align-self:center">-</span>
                        <input type="date" name="tgl_selesai" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <button type="submit" class="btn-excel btn-blue">📥 Download Custom</button>
                </form>
            </div>

        </div>

        <div class="table-action-bar">
            <button type="button" id="btnHapusTrigger" class="btn-delete" disabled onclick="submitDelete()">Hapus Data Terpilih</button>
        </div>

        <div class="table-wrapper">
            <form method="POST" id="mainDeleteForm">
                <table cellspacing="0">
                    <thead>
                        <tr><th width="5%"><input type="checkbox" id="checkAll"></th><th width="10%">No. Nota</th><th width="20%">Waktu</th><th width="20%">Pelanggan</th><th width="25%">Layanan</th><th width="20%">Total Bayar</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($semua_data as $row): ?>
                        <tr>
                            <td><input type="checkbox" name="pilih[]" value="<?= $row['id'] ?>" class="checkItem"></td>
                            <td><span class="nota-badge">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></span></td>
                            <td><div style="font-weight:600;"><?= $row['tanggal'] ?></div><div class="date-text"><?= $row['jam'] ?></div></td>
                            <td style="font-weight:500;"><?= $row['nama_pelanggan'] ?></td>
                            <td style="color:var(--text-light); font-size:12px;"><?= str_replace(",", ", ", $row['jenis_layanan']) ?></td>
                            <td class="price-text">Rp <?= number_format($row['total_bayar']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" name="hapus_pilihan" id="realDeleteBtn" style="display:none;"></button>
            </form>
        </div>

    </div>

    <script>
        const checkAll = document.getElementById('checkAll');
        const checkItems = document.querySelectorAll('.checkItem');
        const btnHapusTrigger = document.getElementById('btnHapusTrigger');

        function updateButtonState() {
            const checkedCount = document.querySelectorAll('.checkItem:checked').length;
            if (checkedCount > 0) {
                btnHapusTrigger.disabled = false;
                btnHapusTrigger.textContent = `Hapus (${checkedCount}) Data`;
            } else {
                btnHapusTrigger.disabled = true;
                btnHapusTrigger.textContent = "Hapus Data Terpilih";
            }
        }

        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            checkItems.forEach(item => { item.checked = isChecked; });
            updateButtonState();
        });

        checkItems.forEach(item => {
            item.addEventListener('change', function() { if (!this.checked) checkAll.checked = false; updateButtonState(); });
        });

        function submitDelete() {
            if(confirm('⚠️ PERINGATAN:\nData yang dihapus tidak bisa dikembalikan.\nNomor Nota akan diurutkan ulang secara otomatis.\n\nLanjutkan menghapus?')) {
                document.getElementById('realDeleteBtn').click();
            }
        }
    </script>

</body>
</html>