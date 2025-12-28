<?php
// --- CONFIG STANDARD ---
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
error_reporting(0); 
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- KONEKSI DATABASE ---
$db_file = __DIR__ . '/salon.db';
try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Gagal koneksi database: " . $e->getMessage());
}

// AUTO MIGRATION (Memastikan kolom tabel lengkap)
try { $db->query("SELECT no_nota FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN no_nota INTEGER DEFAULT 0"); }
try { $db->query("SELECT terapis FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN terapis TEXT DEFAULT '-'"); }

// --- LOGIKA HENTIKAN PROMO ---
if (isset($_POST['stop_promo_id'])) {
    $id_stop = $_POST['stop_promo_id'];
    $kemarin = date('Y-m-d H:i:s', strtotime('-1 day'));
    $db->exec("UPDATE daftar_promo SET berlaku_sampai = '$kemarin' WHERE id = $id_stop");
    header("Location: index.php");
}

$atribut_lock = (isset($_SESSION['role']) && $_SESSION['role'] == 'supervisor') ? "" : "readonly"; 

// --- AMBIL DATA PROMO & LAYANAN ---
$sekarang = date('Y-m-d H:i:s');
$query_promo = $db->query("SELECT * FROM daftar_promo WHERE berlaku_sampai > '$sekarang'");
$semua_promo = $query_promo->fetchAll(PDO::FETCH_ASSOC);

$promo_map = [];
foreach($semua_promo as $p) {
    $targets = explode(',', $p['target_layanan']);
    foreach($targets as $t) { $t = trim($t); if(!empty($t)) $promo_map[$t] = ['nama' => $p['nama_promo'], 'tipe' => $p['jenis_diskon'], 'nilai' => $p['nilai_diskon']]; }
}

$ref_harga_layanan = [];
$opsi_layanan = $db->query("SELECT * FROM master_layanan");
foreach($opsi_layanan as $l) { $ref_harga_layanan[$l['nama_layanan']] = $l['harga_default']; }
$opsi_layanan = $db->query("SELECT * FROM master_layanan"); 
$opsi_metode = $db->query("SELECT * FROM master_metode");
$list_terapis = $db->query("SELECT nama_lengkap FROM users ORDER BY nama_lengkap ASC");

// --- LOGIKA CETAK NOTA ---
if (isset($_GET['cetak'])) {
    $id_transaksi = $_GET['cetak'];
    $q = $db->query("SELECT * FROM transaksi WHERE id = $id_transaksi");
    $data = $q->fetch();
    $toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();

    if ($data) {
        $MAX_CHARS = 32; 
        function buatTengah($teks, $lebar=32) {
            $panjang = strlen($teks); if ($panjang >= $lebar) return substr($teks, 0, $lebar); 
            $kiri = floor(($lebar - $panjang) / 2); return str_repeat(" ", $kiri) . $teks;
        }
        function formatItemNota($nama, $harga, $lebar=32) {
            if (is_numeric($harga)) { $hargaStr = number_format($harga); } else { $hargaStr = $harga; }
            $maxNama = $lebar - strlen($hargaStr) - 1; 
            if (strlen($nama) <= $maxNama) {
                $spasiTengah = $lebar - strlen($nama) - strlen($hargaStr);
                return $nama . str_repeat(" ", $spasiTengah) . $hargaStr . "\n";
            } else { return substr($nama, 0, $maxNama) . " " . $hargaStr . "\n"; }
        }

        $garis = str_repeat("=", $MAX_CHARS) . "\n";
        $garis_tipis = str_repeat("-", $MAX_CHARS) . "\n";
        $struk  = buatTengah(strtoupper($toko['nama_toko']), $MAX_CHARS) . "\n";
        $struk .= buatTengah($toko['alamat_toko'], $MAX_CHARS) . "\n";
        $struk .= $garis;
        $struk .= "NO NOTA : #" . str_pad($data['no_nota'], 4, '0', STR_PAD_LEFT) . "\n";
        $struk .= "Tgl     : " . $data['tanggal'] . " " . $data['jam'] . "\n";
        $struk .= "Plg     : " . substr($data['nama_pelanggan'], 0, 20) . "\n";
        if (!empty($data['terapis']) && $data['terapis'] != '-') { $struk .= "Stylist : " . substr($data['terapis'], 0, 20) . "\n"; }
        $struk .= $garis_tipis;
        $services = explode(',', $data['jenis_layanan']);
        foreach($services as $svc) {
            $svc = trim($svc);
            $harga_satuan = isset($ref_harga_layanan[$svc]) ? $ref_harga_layanan[$svc] : 0;
            $struk .= formatItemNota($svc, $harga_satuan, $MAX_CHARS);
        }
        $struk .= $garis_tipis;
        $struk .= formatItemNota("SUBTOTAL", $data['harga'], $MAX_CHARS);
        if($data['diskon'] > 0) { $struk .= formatItemNota("HEMAT", "- " . number_format($data['diskon']), $MAX_CHARS); }
        $struk .= $garis;
        $struk .= formatItemNota("TOTAL BAYAR", "Rp " . number_format($data['total_bayar']), $MAX_CHARS);
        $struk .= "Metode : " . $data['metode_pembayaran'] . "\n";
        $struk .= $garis;
        $struk .= buatTengah($toko['pesan_footer'], $MAX_CHARS) . "\n";
        $struk .= buatTengah("Kasir: " . strtoupper($_SESSION['nama'] ?? 'USER'), $MAX_CHARS) . "\n";
        $struk .= "\n\n\n"; 
        $nama_file = "nota_print.txt";
        file_put_contents($nama_file, $struk);
        pclose(popen("start notepad.exe $nama_file", "r"));
        header("Location: index.php");
    }
}

// --- LOGIKA SIMPAN TRANSAKSI ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $metode = $_POST['metode'];
    $terapis = $_POST['terapis'];
    $layanan_dipilih = $_POST['layanan'] ?? [];
    if(empty($layanan_dipilih)) { echo "<script>alert('Pilih minimal satu layanan!'); window.location='index.php';</script>"; exit; }

    $nama_layanan_arr = []; $total_harga_asli = 0; $total_diskon_final = 0;
    foreach($layanan_dipilih as $item) {
        $parts = explode('|', $item); $nama_item = $parts[0]; $harga_item = $parts[1];
        $nama_layanan_arr[] = $nama_item; $total_harga_asli += $harga_item;
        if (isset($promo_map[$nama_item])) {
            $rule = $promo_map[$nama_item];
            if ($rule['tipe'] == 'persen') { $total_diskon_final += $harga_item * ($rule['nilai'] / 100); } 
            else { $total_diskon_final += $rule['nilai']; }
        }
    }
    $string_layanan_final = implode(", ", $nama_layanan_arr);
    if ($_SESSION['role'] == 'supervisor') { $total_harga_asli = $_POST['harga_tampil']; }
    $total_bayar = $total_harga_asli - $total_diskon_final;
    if($total_bayar < 0) $total_bayar = 0;
    
    $tanggal = date('Y-m-d'); $jam = date('H:i');
    $max_nota = $db->query("SELECT MAX(no_nota) FROM transaksi")->fetchColumn();
    $next_nota = $max_nota ? ($max_nota + 1) : 1;

    $sql = "INSERT INTO transaksi (no_nota, tanggal, jam, nama_pelanggan, jenis_layanan, harga, diskon, total_bayar, metode_pembayaran, terapis) 
            VALUES ('$next_nota', '$tanggal', '$jam', '$nama', '$string_layanan_final', '$total_harga_asli', '$total_diskon_final', '$total_bayar', '$metode', '$terapis')";
    $db->exec($sql);
    header("Location: index.php");
}
$data_transaksi = $db->query("SELECT * FROM transaksi ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kasir Salon Rengganis</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #ec4899; --primary-dark: #be185d; --bg-body: #f3f4f6; --white: #ffffff; --text-dark: #1f2937; --text-light: #6b7280; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); padding: 30px; margin: 0; color: var(--text-dark); }
        .container { max-width: 1000px; margin: 0 auto; background: var(--white); padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); }
        .user-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .role-badge { background: #fce7f3; color: var(--primary-dark); padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 5px; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 600; border: 1px solid var(--danger); padding: 8px 20px; border-radius: 8px; transition: 0.3s; }
        .btn-logout:hover { background: var(--danger); color: white; }
        .btn-guide { background: var(--info); color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 5px; cursor: pointer; border: none; }
        .top-links { display: flex; gap: 15px; margin-bottom: 30px; }
        .btn-link { flex: 1; text-align: center; text-decoration: none; color: var(--text-dark); font-weight: 600; background: #fff; border: 1px solid #eee; padding: 12px; border-radius: 10px; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-link:hover { background: #f9fafb; border-color: var(--primary); color: var(--primary); }
        .promo-banner { background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-left: 5px solid var(--warning); padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        .promo-item { background: rgba(255,255,255,0.6); padding: 10px 15px; margin-bottom: 8px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-stop { background: var(--danger); color: white; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600; }
        h2 { text-align: center; color: var(--primary); font-weight: 700; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 14px; border: 1px solid #e5e7eb; border-radius: 12px; box-sizing: border-box; font-family: 'Poppins'; font-size: 14px; background: #f9fafb; }
        input:focus, select:focus { outline: none; border-color: var(--primary); background: white; }
        input[readonly] { background-color: #f3f4f6; color: #9ca3af; cursor: not-allowed; }
        
        .selected-services-box { border: 2px dashed #cbd5e1; background: #f8fafc; padding: 15px; border-radius: 12px; min-height: 50px; margin-top: 5px; font-size: 14px; color: var(--text-light); display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 5px; }
        .btn-pilih { background: var(--text-dark); color: white; width: 100%; padding: 14px; margin-top: 10px; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; }
        #inputTotal { background-color: #ecfdf5; color: #065f46; font-size: 32px; font-weight: 700; border: 2px solid #10b981; height: auto; padding: 20px; text-align: right; }
        .btn-simpan { width: 100%; padding: 18px; background: linear-gradient(45deg, #ec4899, #be185d); color: white; border: none; cursor: pointer; font-weight: 700; font-size: 18px; border-radius: 12px; margin-top: 20px; box-shadow: 0 10px 20px rgba(236, 72, 153, 0.3); }
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; margin-top: 10px; }
        th { text-align: left; padding: 15px; color: var(--text-light); font-size: 12px; font-weight: 600; }
        td { background: white; padding: 15px; border-top: 1px solid #f3f4f6; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 999; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700; font-size: 18px; }
        .close-modal { cursor: pointer; font-size: 24px; color: #aaa; }
        .close-modal:hover { color: red; }
        .service-list-container { flex: 1; overflow-y: auto; padding-right: 5px; margin-top: 10px; }
        .service-option { display: flex; align-items: center; padding: 15px; border: 1px solid #f3f4f6; border-radius: 12px; margin-bottom: 10px; cursor: pointer; }
        .service-option:hover { border-color: var(--primary); background: #fdf2f8; }
        .service-option input { width: 20px; height: 20px; margin-right: 15px; accent-color: var(--primary); }
        .tag-promo { background: var(--warning); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; margin-left: 8px; }
        
        .guide-section { margin-bottom: 20px; }
        .guide-title { font-weight: 700; color: var(--info); margin-bottom: 5px; display: block; }
        .guide-text { font-size: 13px; color: var(--text-light); line-height: 1.6; }
        .guide-text ul { padding-left: 20px; margin: 5px 0; }
    </style>

    <script>
        var activePromos = <?php echo json_encode($promo_map); ?>;

        function toggleModal(id, show) { document.getElementById(id).style.display = show ? 'flex' : 'none'; }

        function searchService() {
            var input = document.getElementById("searchLayanan");
            var filter = input.value.toUpperCase();
            var labels = document.getElementsByClassName("service-option");
            for (var i = 0; i < labels.length; i++) {
                var textSpan = labels[i].querySelector(".svc-name");
                labels[i].style.display = textSpan.innerText.toUpperCase().indexOf(filter) > -1 ? "flex" : "none";
            }
        }

        function updateSelection() {
            var checkboxes = document.querySelectorAll('input[name="layanan[]"]:checked');
            var totalHarga = 0, totalDiskon = 0;
            var selectedNames = [];
            checkboxes.forEach(function(box) {
                var parts = box.value.split('|');
                var nama = parts[0], harga = parseInt(parts[1]);
                selectedNames.push(nama);
                totalHarga += harga;
                if (activePromos[nama]) {
                    var rule = activePromos[nama];
                    totalDiskon += (rule.tipe === 'persen') ? harga * (rule.nilai / 100) : rule.nilai;
                }
            });
            var totalBayar = totalHarga - totalDiskon;
            if (totalBayar < 0) totalBayar = 0;
            document.getElementById("inputHarga").value = totalHarga;
            document.getElementById("inputDiskon").value = parseInt(totalDiskon);
            document.getElementById("inputTotal").value = "Rp " + totalBayar.toLocaleString('id-ID');
            var displayBox = document.getElementById("selectedServicesDisplay");
            var btnLabel = document.getElementById("btnPilihLayanan");
            if (selectedNames.length > 0) {
                displayBox.innerHTML = selectedNames.map(name => 
                    `<span style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 20px; margin: 2px; font-size: 12px; display: inline-block;">${name}</span>`
                ).join("");
                displayBox.style.background = "white";
                displayBox.style.borderColor = "#ec4899";
                btnLabel.innerHTML = `✅ Ubah Pilihan (${selectedNames.length})`;
                btnLabel.style.background = "#ec4899";
            } else {
                displayBox.innerHTML = "<span style='color:#9ca3af'>Belum ada layanan yang dipilih...</span>";
                displayBox.style.background = "#f8fafc";
                displayBox.style.borderColor = "#cbd5e1";
                btnLabel.innerHTML = "➕ Pilih Layanan";
                btnLabel.style.background = "#1f2937";
            }
        }

        function manualEditHarga() {
            var hargaManual = parseInt(document.getElementById("inputHarga").value) || 0;
            var diskon = parseInt(document.getElementById("inputDiskon").value) || 0;
            var total = hargaManual - diskon;
            if(total < 0) total = 0;
            document.getElementById("inputTotal").value = "Rp " + total.toLocaleString('id-ID');
        }
    </script>
</head>
<body>

<div class="container">
    <div class="user-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <button class="btn-guide" onclick="toggleModal('modalGuide', true)">📖 Panduan Aplikasi</button>
            <div class="user-info">
                Selamat datang, <b><?= htmlspecialchars(strtoupper($_SESSION['nama'] ?? 'USER')) ?></b>
                <span class="role-badge"><?= ucfirst($_SESSION['role'] ?? 'Staff') ?></span>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>

    <div id="modalGuide" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <span>📖 Panduan & Bantuan</span>
                <span class="close-modal" onclick="toggleModal('modalGuide', false)">&times;</span>
            </div>
            <div style="flex:1; overflow-y:auto;">
                <div class="guide-section">
                    <span class="guide-title">📊 Cara Export Laporan</span>
                    <div class="guide-text">
                        1. Klik tombol <b>"Laporan"</b> di kanan atas.<br>
                        2. Pilih Tanggal Awal dan Akhir.<br>
                        3. Klik tombol hijau <b>"Download Excel"</b>.
                    </div>
                </div>
            </div>
            <button onclick="toggleModal('modalGuide', false)" style="background:#333; color:white; padding:12px; border:none; border-radius:8px; margin-top:10px; cursor:pointer;">Tutup Panduan</button>
        </div>
    </div>

    <div class="top-links">
        <?php if(($_SESSION['role'] ?? '') == 'supervisor'): ?>
            <a href="pengaturan.php" class="btn-link">⚙️ Menu & Promo</a>
            <a href="kelola_user.php" class="btn-link">👥 Kelola User</a>
        <?php endif; ?>
        <a href="laporan.php" class="btn-link">📊 Laporan & Riwayat</a>
    </div>

    <?php if(count($semua_promo) > 0): ?>
    <div class="promo-banner">
        <span class="promo-title">🎉 Promo Sedang Berlangsung</span>
        <?php foreach($semua_promo as $p): ?>
        <div class="promo-item">
            <span>
                <b><?= htmlspecialchars($p['nama_promo']) ?></b> 
                <span style="color:#b45309; font-size:12px;">(<?= htmlspecialchars($p['target_layanan']) ?>)</span>
                <br>
                Diskon: <?= $p['jenis_diskon']=='nominal' ? 'Rp'.number_format($p['nilai_diskon']) : $p['nilai_diskon'].'%' ?>
            </span>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Hentikan promo ini sekarang?');">
                <input type="hidden" name="stop_promo_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn-stop">Stop</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2>🌸 Kasir Salon Rengganis</h2>

    <form method="POST">
        <div class="form-group">
            <label>Nama Pelanggan</label>
            <input type="text" id="inputNama" name="nama" required autocomplete="off" placeholder="Ketik nama pelanggan...">
        </div>

        <div class="form-group">
            <label>Pilih Terapis / Stylist</label>
            <select name="terapis" required>
                <option value="">-- Pilih Stylist --</option>
                <?php foreach($list_terapis as $staff): ?>
                    <option value="<?= $staff['nama_lengkap'] ?>"><?= $staff['nama_lengkap'] ?></option>
                <?php endforeach; ?>
                <option value="-">Tanpa Stylist Khusus</option>
            </select>
        </div>

        <div class="form-group">
            <label>Layanan (Multi-Treatment)</label>
            <div id="selectedServicesDisplay" class="selected-services-box"><span>Belum ada layanan yang dipilih...</span></div>
            <button type="button" id="btnPilihLayanan" class="btn-pilih" onclick="toggleModal('modalService', true)">➕ Pilih Layanan</button>
        </div>

        <div style="display: flex; gap: 20px; margin-top: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Subtotal Harga</label>
                <input type="number" name="harga_tampil" id="inputHarga" <?= $atribut_lock ?> onkeyup="manualEditHarga()">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Total Hemat</label>
                <input type="number" name="diskon_tampil" id="inputDiskon" readonly style="color: var(--danger); font-weight:600;">
            </div>
        </div>

        <div class="form-group" style="margin-top: 10px;">
            <label>TOTAL YANG HARUS DIBAYAR</label>
            <input type="text" id="inputTotal" readonly value="Rp 0">
        </div>

        <div class="form-group">
            <label>Metode Pembayaran</label>
            <select name="metode">
                <?php foreach($opsi_metode as $row): ?>
                    <option value="<?= $row['nama_metode'] ?>"><?= $row['nama_metode'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" name="simpan" class="btn-simpan">PROSES PEMBAYARAN (SIMPAN)</button>
    
        <div id="modalService" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header"><span>Pilih Layanan</span><span class="close-modal" onclick="toggleModal('modalService', false)">&times;</span></div>
                <input type="text" id="searchLayanan" onkeyup="searchService()" placeholder="🔍 Cari layanan...">
                <div id="serviceListContainer" class="service-list-container">
                    <?php foreach($opsi_layanan as $row): ?>
                    <?php $lagi_promo = isset($promo_map[$row['nama_layanan']]); ?>
                    <label class="service-option">
                        <input type="checkbox" name="layanan[]" value="<?= $row['nama_layanan'] ?>|<?= $row['harga_default'] ?>" onchange="updateSelection()">
                        <div style="flex:1;"><span class="svc-name"><?= $row['nama_layanan'] ?></span><span style="font-size:12px; color:#6b7280;">Rp <?= number_format($row['harga_default']) ?></span><?php if($lagi_promo): ?><span class="tag-promo">PROMO</span><?php endif; ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="toggleModal('modalService', false)" style="margin-top: 15px; background:var(--success); color:white; padding:15px; border:none; border-radius:12px; font-weight:600; cursor:pointer;">Selesai</button>
            </div>
        </div>
    </form>

    <h3>📝 Riwayat Transaksi Terakhir</h3>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th width="10%">No Nota</th>
                    <th width="20%">Pelanggan & Stylist</th>
                    <th width="30%">Layanan</th>
                    <th width="15%">Diskon</th>
                    <th width="15%">Total</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data_transaksi as $row): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--primary);">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <?= htmlspecialchars($row['nama_pelanggan']) ?><br>
                        <small style="color:#6b7280; font-style:italic;">Stylist: <?= htmlspecialchars($row['terapis'] ?? '-') ?></small>
                    </td>
                    <td>
                        <small style="color:#6b7280; line-height:1.4; display:block;">
                            <?= htmlspecialchars(str_replace(",", ", ", $row['jenis_layanan'])) ?>
                        </small>
                    </td>
                    <td style="color: var(--danger); font-weight:600;">
                        <?= ($row['diskon'] > 0) ? '-'.number_format($row['diskon']) : '-' ?>
                    </td>
                    <td style="font-weight: 700;">Rp <?= number_format($row['total_bayar']) ?></td>
                    <td>
                        <a href="index.php?cetak=<?= $row['id'] ?>" style="text-decoration: none; font-size:18px;">🖨️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>