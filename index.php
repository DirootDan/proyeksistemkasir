<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$db = new PDO("sqlite:salon.db");

// --- 1. CEK KOLOM NO_NOTA ---
try {
    $db->query("SELECT no_nota FROM transaksi LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE transaksi ADD COLUMN no_nota INTEGER DEFAULT 0");
}

// --- LOGIKA STOP PROMO ---
if (isset($_POST['stop_promo_id'])) {
    $id_stop = $_POST['stop_promo_id'];
    $kemarin = date('Y-m-d H:i:s', strtotime('-1 day'));
    $db->exec("UPDATE daftar_promo SET berlaku_sampai = '$kemarin' WHERE id = $id_stop");
    header("Location: index.php");
}

$atribut_lock = (isset($_SESSION['role']) && $_SESSION['role'] == 'supervisor') ? "" : "readonly"; 

// --- PEMETAAN PROMO & HARGA ---
$sekarang = date('Y-m-d H:i:s');
$query_promo = $db->query("SELECT * FROM daftar_promo WHERE berlaku_sampai > '$sekarang'");
$semua_promo = $query_promo->fetchAll(PDO::FETCH_ASSOC);

$promo_map = [];
foreach($semua_promo as $p) {
    $targets = explode(',', $p['target_layanan']);
    foreach($targets as $t) {
        $t = trim($t);
        if(!empty($t)) $promo_map[$t] = ['nama' => $p['nama_promo'], 'tipe' => $p['jenis_diskon'], 'nilai' => $p['nilai_diskon']];
    }
}

// Referensi Harga
$ref_harga_layanan = [];
$opsi_layanan = $db->query("SELECT * FROM master_layanan");
foreach($opsi_layanan as $l) {
    $ref_harga_layanan[$l['nama_layanan']] = $l['harga_default'];
}
$opsi_layanan = $db->query("SELECT * FROM master_layanan"); 
$opsi_metode = $db->query("SELECT * FROM master_metode");

// --- LOGIKA CETAK NOTA (CLEAN STYLE - TANPA TITIK) ---
if (isset($_GET['cetak'])) {
    $id_transaksi = $_GET['cetak'];
    $q = $db->query("SELECT * FROM transaksi WHERE id = $id_transaksi");
    $data = $q->fetch();
    $toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();

    if ($data) {
        $MAX_CHARS = 32; // Lebar Kertas 58mm

        function buatTengah($teks, $lebar=32) {
            $panjang = strlen($teks);
            if ($panjang >= $lebar) return substr($teks, 0, $lebar); 
            $kiri = floor(($lebar - $panjang) / 2);
            return str_repeat(" ", $kiri) . $teks;
        }

        // FUNGSI FORMAT BARU (CLEAN SPACE)
        function formatItemNota($nama, $harga, $lebar=32) {
            // 1. Format Harga (Cek Angka/String)
            if (is_numeric($harga)) {
                $hargaStr = number_format($harga);
            } else {
                $hargaStr = $harga;
            }

            // 2. Hitung sisa ruang untuk Nama
            // Kita butuh minimal 1 spasi pemisah
            $maxNama = $lebar - strlen($hargaStr) - 1; 
            
            if (strlen($nama) <= $maxNama) {
                // Jika nama muat, hitung jumlah spasi tengah
                $spasiTengah = $lebar - strlen($nama) - strlen($hargaStr);
                // Ganti "." dengan " " (Spasi Kosong)
                return $nama . str_repeat(" ", $spasiTengah) . $hargaStr . "\n";
            } else {
                // Jika kepanjangan, potong nama
                return substr($nama, 0, $maxNama) . " " . $hargaStr . "\n";
            }
        }

        $garis = str_repeat("=", $MAX_CHARS) . "\n";
        $garis_tipis = str_repeat("-", $MAX_CHARS) . "\n";

        $struk  = buatTengah(strtoupper($toko['nama_toko']), $MAX_CHARS) . "\n";
        $struk .= buatTengah($toko['alamat_toko'], $MAX_CHARS) . "\n";
        $struk .= $garis;
        
        $struk .= "NO NOTA : #" . str_pad($data['no_nota'], 4, '0', STR_PAD_LEFT) . "\n";
        $struk .= "Tgl     : " . $data['tanggal'] . " " . $data['jam'] . "\n";
        $struk .= "Plg     : " . substr($data['nama_pelanggan'], 0, 20) . "\n";
        $struk .= $garis_tipis;
        
        $services = explode(',', $data['jenis_layanan']);
        foreach($services as $svc) {
            $svc = trim($svc);
            $harga_satuan = isset($ref_harga_layanan[$svc]) ? $ref_harga_layanan[$svc] : 0;
            $struk .= formatItemNota($svc, $harga_satuan, $MAX_CHARS);
        }

        $struk .= $garis_tipis;
        
        $struk .= formatItemNota("SUBTOTAL", $data['harga'], $MAX_CHARS);
        
        if($data['diskon'] > 0) {
            // Tambahkan tanda minus biar jelas itu potongan
            $struk .= formatItemNota("HEMAT", "- " . number_format($data['diskon']), $MAX_CHARS);
        }
        
        $struk .= $garis;
        // Total Bayar
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

// --- PROSES SIMPAN TRANSAKSI ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $metode = $_POST['metode'];
    $layanan_dipilih = $_POST['layanan'] ?? [];
    
    if(empty($layanan_dipilih)) {
        echo "<script>alert('Pilih minimal satu layanan!'); window.location='index.php';</script>";
        exit;
    }

    $nama_layanan_arr = [];
    $total_harga_asli = 0;
    $total_diskon_final = 0;

    foreach($layanan_dipilih as $item) {
        $parts = explode('|', $item);
        $nama_item = $parts[0];
        $harga_item = $parts[1];
        $nama_layanan_arr[] = $nama_item;
        $total_harga_asli += $harga_item;

        if (isset($promo_map[$nama_item])) {
            $rule = $promo_map[$nama_item];
            if ($rule['tipe'] == 'persen') {
                $total_diskon_final += $harga_item * ($rule['nilai'] / 100);
            } else {
                $total_diskon_final += $rule['nilai'];
            }
        }
    }

    $string_layanan_final = implode(", ", $nama_layanan_arr);
    if ($_SESSION['role'] == 'supervisor') {
        $total_harga_asli = $_POST['harga_tampil']; 
    }
    $total_bayar = $total_harga_asli - $total_diskon_final;
    if($total_bayar < 0) $total_bayar = 0;
    
    $tanggal = date('Y-m-d');
    $jam = date('H:i');

    $max_nota = $db->query("SELECT MAX(no_nota) FROM transaksi")->fetchColumn();
    $next_nota = $max_nota ? ($max_nota + 1) : 1;

    $sql = "INSERT INTO transaksi (no_nota, tanggal, jam, nama_pelanggan, jenis_layanan, harga, diskon, total_bayar, metode_pembayaran) 
            VALUES ('$next_nota', '$tanggal', '$jam', '$nama', '$string_layanan_final', '$total_harga_asli', '$total_diskon_final', '$total_bayar', '$metode')";
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
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; background-color: #f4f4f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[readonly] { background-color: #e9ecef; color: #555; cursor: not-allowed; border: 1px solid #ccc; }
        input:not([readonly]) { background-color: #fff; border: 1px solid #28a745; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: 'Comic Sans MS'; }
        #inputTotal { background-color: #d4edda; font-weight: bold; color: #155724; font-size: 24px; border: 2px solid #c3e6cb; height: 50px; }
        button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; font-size: 16px; border-radius: 5px; margin-top: 10px; font-family: 'Comic Sans MS'; }
        button:hover { background-color: #218838; }
        
        .promo-banner { background: linear-gradient(45deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #d63384; font-weight: bold; border: 1px solid #ffb3c1; }
        .promo-item { background: rgba(255,255,255,0.5); padding: 5px 10px; margin-bottom: 5px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .btn-stop { background: #dc3545; color: white; border: none; padding: 2px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; }

        .selected-services-box { border: 1px solid #007bff; background: #f0f8ff; padding: 10px; border-radius: 5px; min-height: 40px; margin-top: 5px; font-size: 14px; color: #333; }
        .btn-pilih { background: #007bff; width: auto; padding: 10px 20px; margin-top: 5px; display: inline-block; font-size: 14px; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 20px; border-radius: 10px; width: 500px; max-width: 95%; max-height: 80vh; box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; flex-direction: column; }
        .modal-header { font-size: 18px; font-weight: bold; margin-bottom: 10px; display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .close-modal { cursor: pointer; color: red; font-size: 24px; }
        #searchLayanan { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .service-list-container { flex: 1; overflow-y: auto; border: 1px solid #f0f0f0; padding: 5px; }
        .service-option { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #f9f9f9; cursor: pointer; transition: 0.2s; }
        .service-option:hover { background: #f1f1f1; }
        .service-option input { width: 20px; height: 20px; margin-right: 15px; cursor: pointer; }
        .tag-promo { background: #ffc107; color: black; font-size: 10px; padding: 2px 5px; border-radius: 4px; font-weight: bold; margin-left: 5px; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #6c757d; color: white; }
        .top-links { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .btn-link { text-decoration: none; color: #007bff; font-weight: bold; }
        .user-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    </style>

    <script>
        var activePromos = <?php echo json_encode($promo_map); ?>;

        function toggleModal(show) { document.getElementById('modalService').style.display = show ? 'flex' : 'none'; }

        function searchService() {
            var input = document.getElementById("searchLayanan");
            var filter = input.value.toUpperCase();
            var container = document.getElementById("serviceListContainer");
            var labels = container.getElementsByClassName("service-option");
            for (var i = 0; i < labels.length; i++) {
                var textSpan = labels[i].querySelector(".svc-name");
                if ((textSpan.textContent || textSpan.innerText).toUpperCase().indexOf(filter) > -1) {
                    labels[i].style.display = "";
                } else {
                    labels[i].style.display = "none";
                }
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
                displayBox.innerHTML = "<b>Terpilih:</b> " + selectedNames.join(", ");
                displayBox.style.background = "#e3f2fd";
                btnLabel.innerHTML = "📝 Ubah Pilihan (" + selectedNames.length + ")";
            } else {
                displayBox.innerHTML = "<span style='color:#999'>Belum ada layanan yang dipilih...</span>";
                displayBox.style.background = "#f9f9f9";
                btnLabel.innerHTML = "➕ Pilih Layanan";
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
        <div>Halo, <b><?= strtoupper($_SESSION['nama'] ?? 'USER') ?></b> (<?= ($_SESSION['role'] ?? '') == 'supervisor' ? '👑 Supervisor' : '👤 Staff' ?>)</div>
        <a href="logout.php" style="color: red; text-decoration: none; font-weight: bold;">🚪 Logout</a>
    </div>

    <div class="top-links">
        <?php if(($_SESSION['role'] ?? '') == 'supervisor'): ?>
            <a href="pengaturan.php" class="btn-link">⚙️ Atur Menu & Promo</a>
            <a href="kelola_user.php" class="btn-link" style="color: #d63384;">👥 Kelola User & Reset Pass</a>
        <?php endif; ?>
        <a href="laporan.php" class="btn-link">📊 Laporan</a>
    </div>

    <?php if(count($semua_promo) > 0): ?>
    <div class="promo-banner">
        <div style="text-align: center; margin-bottom: 5px;">🎉 PROMO AKTIF 🎉</div>
        <?php foreach($semua_promo as $p): ?>
        <div class="promo-item">
            <span><b><?= $p['nama_promo'] ?></b>: Diskon <?= $p['jenis_diskon']=='nominal' ? 'Rp'.number_format($p['nilai_diskon']) : $p['nilai_diskon'].'%' ?> (<?= $p['target_layanan'] ?>)</span>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Stop promo ini?');">
                <input type="hidden" name="stop_promo_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn-stop">STOP</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2>🌸 Kasir Salon Rengganis</h2>

    <form method="POST">
        <div class="form-group">
            <label>Nama Pelanggan:</label>
            <input type="text" name="nama" required autocomplete="off" placeholder="Masukkan nama...">
        </div>

        <div class="form-group">
            <label>Pilih Layanan (Multi-Treatment):</label>
            <div id="selectedServicesDisplay" class="selected-services-box">
                <span style='color:#999'>Belum ada layanan yang dipilih...</span>
            </div>
            <button type="button" id="btnPilihLayanan" class="btn-pilih" onclick="toggleModal(true)">➕ Pilih Layanan</button>

            <div id="modalService" class="modal-overlay">
                <div class="modal-box">
                    <div class="modal-header">
                        <span>📋 Daftar Layanan</span>
                        <span class="close-modal" onclick="toggleModal(false)">&times;</span>
                    </div>
                    <input type="text" id="searchLayanan" onkeyup="searchService()" placeholder="🔍 Cari layanan...">
                    <div id="serviceListContainer" class="service-list-container">
                        <?php foreach($opsi_layanan as $row): ?>
                        <?php $lagi_promo = isset($promo_map[$row['nama_layanan']]); ?>
                        <label class="service-option">
                            <input type="checkbox" name="layanan[]" value="<?= $row['nama_layanan'] ?>|<?= $row['harga_default'] ?>" onchange="updateSelection()">
                            <div style="flex:1;">
                                <span class="svc-name"><?= $row['nama_layanan'] ?></span><br>
                                <span style="font-size:12px; color:#666;">Rp <?= number_format($row['harga_default']) ?></span>
                                <?php if($lagi_promo): ?><span class="tag-promo">🏷️ PROMO</span><?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="toggleModal(false)" style="margin-top: 10px; background:#28a745;">Selesai Memilih</button>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>Subtotal Harga (Rp):</label>
                <input type="number" name="harga_tampil" id="inputHarga" <?= $atribut_lock ?> onkeyup="manualEditHarga()">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Total Hemat / Diskon:</label>
                <input type="number" name="diskon_tampil" id="inputDiskon" readonly>
            </div>
        </div>

        <div class="form-group">
            <label>TOTAL YANG HARUS DIBAYAR:</label>
            <input type="text" id="inputTotal" readonly value="Rp 0">
        </div>

        <div class="form-group">
            <label>Metode Pembayaran:</label>
            <select name="metode">
                <?php foreach($opsi_metode as $row): ?>
                    <option value="<?= $row['nama_metode'] ?>"><?= $row['nama_metode'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" name="simpan">SIMPAN TRANSAKSI</button>
    </form>

    <h3>📝 Transaksi Terakhir</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Pelanggan</th>
                <th>Layanan</th>
                <th>Diskon</th>
                <th>Total Bayar</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data_transaksi as $row): ?>
            <tr>
                <td style="font-weight: bold; color: #007bff;">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></td>
                <td><?= $row['nama_pelanggan'] ?></td>
                <td><small><?= $row['jenis_layanan'] ?></small></td>
                <td style="color: red;"><?= ($row['diskon'] > 0) ? '-'.number_format($row['diskon']) : '-' ?></td>
                <td style="font-weight: bold;">Rp <?= number_format($row['total_bayar']) ?></td>
                <td><a href="index.php?cetak=<?= $row['id'] ?>" style="text-decoration: none;">🖨️</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>