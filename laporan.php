<?php
require_once 'koneksi.php';
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }

// LOGIKA HAPUS DATA
if (isset($_POST['hapus_pilihan']) && !empty($_POST['pilih'])) {
    if($_COOKIE['role'] != 'supervisor') { echo "<script>alert('Hanya SPV yang boleh hapus data!');</script>"; }
    else {
        $ids = implode(',', $_POST['pilih']);
        $db->exec("DELETE FROM transaksi WHERE id IN ($ids)");
        // Re-numbering Nota (Opsional, agar urut lagi)
        $sisa = $db->query("SELECT id FROM transaksi ORDER BY tanggal ASC, id ASC")->fetchAll();
        $no = 1;
        foreach($sisa as $row) {
            $db->exec("UPDATE transaksi SET no_nota = $no WHERE id = ".$row['id']);
            $no++;
        }
        header("Location: laporan.php"); exit;
    }
}

// QUERY STATISTIK
$hari_ini = date('Y-m-d'); $bulan_ini = date('Y-m'); $tahun_ini = date('Y');

$omzet_hari  = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE tanggal = '$hari_ini'")->fetchColumn() ?: 0;
$omzet_bulan = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE strftime('%Y-%m', tanggal) = '$bulan_ini'")->fetchColumn() ?: 0;
$omzet_tahun = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE strftime('%Y', tanggal) = '$tahun_ini'")->fetchColumn() ?: 0;

// AMBIL SEMUA DATA
$data_laporan = $db->query("SELECT * FROM transaksi ORDER BY no_nota DESC")->fetchAll();
?>

<?php include 'header.php'; ?>
<style>
    /* CSS Sidebar (Sama) */
    .app-layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; }
    .nav-menu { padding: 0 15px; margin-top: 20px; }
    .nav-item { display: block; padding: 12px 15px; margin-bottom: 5px; color: var(--text-dark); border-radius: 8px; font-weight: 500; transition: 0.2s; }
    .nav-item:hover { background: #fce7f3; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; }
    .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }

    /* Dashboard Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .stat-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .stat-label { font-size: 12px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 1px; }
    .stat-value { font-size: 24px; font-weight: bold; color: var(--text-dark); margin-top: 5px; }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 15px; background: #f9fafb; border-bottom: 2px solid #eee; font-size: 12px; }
    td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 13px; }
</style>

<div class="app-layout">
    <aside class="sidebar">
        <div style="padding: 25px; text-align: center; border-bottom: 1px solid #f3f4f6;">
            <h3 style="margin:0; color: var(--primary);">Kasir Salon</h3>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">🏠 Dashboard</a>
            <a href="laporan.php" class="nav-item active">📊 Laporan</a>
            <a href="kelola_user.php" class="nav-item">👥 Pengguna</a>
            <a href="pengaturan.php" class="nav-item">⚙️ Pengaturan</a>
            <a href="logout.php" class="nav-item" style="color:red; margin-top:20px;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Omzet Hari Ini</div>
                <div class="stat-value" style="color: var(--primary);">Rp <?= number_format($omzet_hari) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Omzet Bulan Ini</div>
                <div class="stat-value" style="color: #059669;">Rp <?= number_format($omzet_bulan) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Omzet Tahun Ini</div>
                <div class="stat-value" style="color: #7c3aed;">Rp <?= number_format($omzet_tahun) ?></div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Riwayat Transaksi</h3>
                
                <form action="export_excel.php" method="GET" target="_blank" style="display:flex; gap:10px;">
                    <select name="bulan" style="width:120px; padding:8px; margin:0;">
                        <?php for($i=1;$i<=12;$i++): ?>
                            <option value="<?= sprintf('%02d',$i) ?>" <?= date('m')==$i?'selected':'' ?>><?= date('F', mktime(0,0,0,$i,10)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" name="tahun" value="<?= date('Y') ?>" style="width:80px; padding:8px; margin:0;">
                    <button type="submit" class="btn" style="background:#10b981; color:white; padding:8px 15px;">📥 Excel</button>
                </form>
            </div>

            <form method="POST" onsubmit="return confirm('Yakin hapus data terpilih?');">
                <div style="max-height: 500px; overflow-y: auto;">
                    <table cellspacing="0">
                        <thead style="position: sticky; top: 0;">
                            <tr>
                                <th width="30">✔</th>
                                <th>No Nota</th>
                                <th>Waktu</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data_laporan as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="pilih[]" value="<?= $row['id'] ?>"></td>
                                <td><b>#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></b></td>
                                <td><?= $row['tanggal'] ?> <small><?= $row['jam'] ?></small></td>
                                <td><?= $row['nama_pelanggan'] ?></td>
                                <td style="font-size:12px; color:#555;"><?= mb_strimwidth($row['jenis_layanan'], 0, 40, "...") ?></td>
                                <td style="font-weight:bold;">Rp <?= number_format($row['total_bayar']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="hapus_pilihan" class="btn" style="margin-top:15px; background:#fee2e2; color:red;">🗑️ Hapus Terpilih</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>