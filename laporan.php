<?php
// FILE: laporan.php (LAYOUT FIX SESUAI HEADER.PHP)
require_once 'koneksi.php';
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('Asia/Jakarta');
$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');
$tahun_ini = date('Y');

// --- LOGIKA HAPUS DATA ---
if (isset($_POST['hapus_pilihan']) && !empty($_POST['pilih'])) {
    $ids = implode(',', $_POST['pilih']);
    $db->exec("DELETE FROM transaksi WHERE id IN ($ids)");
    
    // Repair Numbering
    $sisa = $db->query("SELECT id FROM transaksi ORDER BY tanggal ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $no = 1;
    foreach($sisa as $row) {
        $db->exec("UPDATE transaksi SET no_nota = $no WHERE id = ".$row['id']);
        $no++;
    }
    echo "<script>alert('✅ Data berhasil dihapus!'); window.location='laporan.php';</script>";
    exit;
}

// QUERY STATISTIK
$omzet_hari  = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE tanggal = '$hari_ini'")->fetchColumn() ?: 0;
$omzet_bulan = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE strftime('%Y-%m', tanggal) = '$bulan_ini'")->fetchColumn() ?: 0;
$omzet_tahun = $db->query("SELECT SUM(total_bayar) FROM transaksi WHERE strftime('%Y', tanggal) = '$tahun_ini'")->fetchColumn() ?: 0;

$data_laporan = $db->query("SELECT * FROM transaksi ORDER BY no_nota DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<style>
    /
    
    /* Sidebar Layout Override */
    .sidebar { 
        width: var(--sidebar-width); 
        background: var(--white); 
        border-right: 1px solid #e2e8f0; 
        position: fixed; 
        height: 100%; 
        z-index: 10; 
        left: 0; top: 0;
    }
    
    /* Konten Utama Mengikuti Sidebar */
    .main-content { 
        flex: 1; 
        margin-left: var(--sidebar-width); /* KUNCI: Pakai variabel agar pas */
        padding: 30px; 
        width: calc(100% - var(--sidebar-width)); 
    }

    .nav-menu { padding: 20px 15px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; margin-bottom: 5px; color: var(--text-gray); border-radius: 10px; font-weight: 500; transition: 0.2s; text-decoration: none; }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: var(--white); box-shadow: 0 4px 10px rgba(236, 72, 153, 0.3); }

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .stat-box { background: var(--white); padding: 20px; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    .stat-label { font-size: 12px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 1px; }
    .stat-value { font-size: 24px; font-weight: 800; margin-top: 5px; }
    
    /* Table & Components */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { text-align: left; padding: 15px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: var(--text-gray); }
    td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-dark); }
    
    .btn-print { 
        background: #e0f2fe; color: #0284c7; padding: 5px 10px; 
        border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: bold; 
    }
    .pagination { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
</style>

<aside class="sidebar">
    <div style="padding: 25px; text-align: center; border-bottom: 1px solid #f1f5f9;">
        <div style="font-size: 32px;">🌸</div>
        <h3 style="margin:5px 0; color: var(--primary);">Kasir Salon</h3>
    </div>
    <nav class="nav-menu">
        <a href="index.php" class="nav-item"><span>🏠</span> Dashboard</a>
        <a href="laporan.php" class="nav-item active"><span>📊</span> Laporan</a>
        <a href="kelola_user.php" class="nav-item"><span>👥</span> Pengguna</a>
        <a href="pengaturan.php" class="nav-item"><span>⚙️</span> Pengaturan</a>
        <a href="logout.php" class="nav-item" style="color:#ef4444; margin-top:20px;"><span>🚪</span> Logout</a>
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
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <h3 style="margin:0;">Riwayat Transaksi</h3>
            
            <div style="display:flex; gap:15px; align-items:center;">
                <input type="text" id="searchInput" placeholder="Cari nota, nama..." style="width:200px; margin:0;">

                <form action="export_excel.php" method="GET" target="_blank" style="display:flex; gap:10px; align-items: center; background: #f1f5f9; padding: 5px 10px; border-radius: 10px;">
                    
                    <select name="tipe" id="tipeFilter" onchange="ubahFilter()" style="margin:0; width:auto; cursor:pointer;">
                        <option value="harian">📅 Harian</option>
                        <option value="bulanan" selected>📆 Bulanan</option>
                        <option value="tahunan">📈 Tahunan</option>
                    </select>

                    <input type="date" name="tanggal" id="inputHarian" value="<?= date('Y-m-d') ?>" style="display:none; margin:0; width:auto;">

                    <div id="inputBulanan" style="display:flex; gap:5px;">
                        <select name="bulan" style="margin:0; width:auto;">
                            <?php for($i=1;$i<=12;$i++): ?>
                                <option value="<?= sprintf('%02d',$i) ?>" <?= date('m')==$i?'selected':'' ?>><?= date('F', mktime(0,0,0,$i,10)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <input type="number" name="tahun" id="inputTahun" value="<?= date('Y') ?>" style="margin:0; width:70px; text-align:center;">
                    
                    <button type="submit" class="btn" style="background:#10b981; color:white; padding: 8px 15px; font-size:12px;">📥 Excel</button>
                </form>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('Yakin hapus data terpilih?');">
            <div style="min-height: 300px;">
                <table cellspacing="0">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="checkAll"></th>
                            <th>No Nota</th>
                            <th>Waktu</th>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:space-between; margin-top:15px; border-top:1px solid #f1f5f9; padding-top:15px;">
                <button type="submit" name="hapus_pilihan" class="btn" style="background:#fee2e2; color:#ef4444;">🗑️ Hapus Terpilih</button>
                
                <div class="pagination">
                    <button type="button" class="btn" id="btnPrev" onclick="changePage(-1)" style="border:1px solid #ccc;">Prev</button>
                    <span id="pageInfo" style="padding:10px;">Page 1</span>
                    <button type="button" class="btn" id="btnNext" onclick="changePage(1)" style="border:1px solid #ccc;">Next</button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
    // JS: Filter UI Toggle
    function ubahFilter() {
        let tipe = document.getElementById('tipeFilter').value;
        let dHarian = document.getElementById('inputHarian');
        let dBulanan = document.getElementById('inputBulanan');
        let dTahun = document.getElementById('inputTahun');

        dHarian.style.display = 'none';
        dBulanan.style.display = 'none';
        dTahun.style.display = 'none';

        if(tipe === 'harian') dHarian.style.display = 'block';
        else if(tipe === 'bulanan') { dBulanan.style.display = 'flex'; dTahun.style.display = 'block'; }
        else if(tipe === 'tahunan') dTahun.style.display = 'block';
    }
    window.onload = function() { ubahFilter(); renderTable(); };

    // JS: Table Logic
    const allData = <?= json_encode($data_laporan) ?>;
    let filteredData = allData;
    let currentPage = 1;
    const rowsPerPage = 10;

    function renderTable() {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        const start = (currentPage - 1) * rowsPerPage;
        const pageData = filteredData.slice(start, start + rowsPerPage);

        if(pageData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">Tidak ada data.</td></tr>';
            return;
        }

        pageData.forEach(row => {
            let total = new Intl.NumberFormat('id-ID').format(row.total_bayar);
            let nota = String(row.no_nota).padStart(4, '0');
            let layanan = row.jenis_layanan.length > 40 ? row.jenis_layanan.substring(0,40)+'...' : row.jenis_layanan;
            
            let tr = `<tr>
                <td><input type="checkbox" name="pilih[]" value="${row.id}" class="chk-item"></td>
                <td><b>#${nota}</b></td>
                <td>${row.tanggal}<br><small>${row.jam}</small></td>
                <td>${row.nama_pelanggan}</td>
                <td style="font-size:12px; color:#666;">${layanan}</td>
                <td style="color:var(--primary); font-weight:bold;">Rp ${total}</td>
                <td><a href="cetak_nota.php?id=${row.id}" target="_blank" class="btn-print">🖨️ Cetak</a></td>
            </tr>`;
            tbody.innerHTML += tr;
        });
        
        document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${Math.ceil(filteredData.length/rowsPerPage)}`;
        resetCheckboxes();
    }

    function changePage(dir) {
        let totalPages = Math.ceil(filteredData.length/rowsPerPage);
        if((currentPage + dir) >= 1 && (currentPage + dir) <= totalPages) {
            currentPage += dir;
            renderTable();
        }
    }
    
    // Search
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        let key = e.target.value.toLowerCase();
        filteredData = allData.filter(item => 
            item.nama_pelanggan.toLowerCase().includes(key) || 
            String(item.no_nota).includes(key)
        );
        currentPage = 1;
        renderTable();
    });

    // Checkbox All
    const checkAll = document.getElementById('checkAll');
    function resetCheckboxes() {
        checkAll.checked = false;
        document.querySelectorAll('.chk-item').forEach(chk => {
            chk.addEventListener('change', () => { if(!chk.checked) checkAll.checked = false; });
        });
    }
    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.chk-item').forEach(chk => chk.checked = this.checked);
    });
</script>
</body>
</html>