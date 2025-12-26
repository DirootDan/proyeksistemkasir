<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// 1. CEK LOGIN
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$db = new PDO("sqlite:salon.db");

// --- LOGIKA HENTIKAN PROMO (BARU!) ---
if (isset($_POST['stop_promo'])) {
    // Trik: Ubah tanggal berlaku menjadi "Kemarin" agar otomatis expired
    $kemarin = date('Y-m-d H:i:s', strtotime('-1 day'));
    $db->exec("UPDATE setting_promo SET berlaku_sampai = '$kemarin' WHERE id=1");
    echo "<script>window.location='index.php';</script>"; // Refresh halaman
}

// --- LOGIKA KUNCI HARGA ---
$atribut_lock = "readonly"; 
if (isset($_SESSION['role']) && $_SESSION['role'] == 'supervisor') {
    $atribut_lock = ""; 
}

// 2. LOGIKA PROMO OTOMATIS
$promo = $db->query("SELECT * FROM setting_promo WHERE id=1")->fetch();
$sekarang = date('Y-m-d H:i:s');

if ($promo) {
    $promo_aktif = ($sekarang < $promo['berlaku_sampai']);
    $nama_promo = $promo_aktif ? $promo['nama_promo'] : "Tidak Ada Promo";
    $nominal_diskon = $promo_aktif ? $promo['nominal_diskon'] : 0;
    $sisa_waktu = strtotime($promo['berlaku_sampai']) - time();
    $daftar_layanan_promo = explode(',', $promo['layanan_promo'] ?? '');
} else {
    $promo_aktif = false;
    $nama_promo = "";
    $nominal_diskon = 0;
    $sisa_waktu = 0;
    $daftar_layanan_promo = [];
}

$opsi_layanan = $db->query("SELECT * FROM master_layanan");
$opsi_metode = $db->query("SELECT * FROM master_metode");

// --- LOGIKA CETAK NOTA ---
if (isset($_GET['cetak'])) {
    $id_transaksi = $_GET['cetak'];
    $q = $db->query("SELECT * FROM transaksi WHERE id = $id_transaksi");
    $data = $q->fetch();
    $toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();

    if ($data) {
        function buatTengah($teks, $lebar=30) {
            $panjang = strlen($teks);
            if ($panjang >= $lebar) return $teks; 
            $kiri = floor(($lebar - $panjang) / 2);
            return str_repeat(" ", $kiri) . $teks;
        }

        $garis = "==============================\n";
        $struk  = buatTengah(strtoupper($toko['nama_toko'])) . "\n";
        $struk .= buatTengah($toko['alamat_toko']) . "\n";
        $struk .= $garis;
        $struk .= "Tgl : " . $data['tanggal'] . " " . $data['jam'] . "\n";
        $struk .= "Plg : " . substr($data['nama_pelanggan'], 0, 20) . "\n";
        $struk .= "------------------------------\n";
        $struk .= $data['jenis_layanan'] . "\n";
        $struk .= "Harga       : " . number_format($data['harga']) . "\n";
        if($data['diskon'] > 0) {
            $struk .= "Diskon      : (" . number_format($data['diskon']) . ")\n";
        }
        $struk .= "------------------------------\n";
        $struk .= "TOTAL BAYAR : Rp " . number_format($data['total_bayar']) . "\n";
        $struk .= "Metode      : " . $data['metode_pembayaran'] . "\n";
        $struk .= $garis;
        $struk .= buatTengah($toko['pesan_footer']) . "\n";
        $struk .= buatTengah("Kasir: " . strtoupper($_SESSION['nama'] ?? 'USER')) . "\n";
        $struk .= "\n\n";

        $nama_file = "nota_print.txt";
        file_put_contents($nama_file, $struk);
        pclose(popen("start notepad.exe $nama_file", "r"));
        header("Location: index.php");
    }
}

// --- PROSES SIMPAN TRANSAKSI ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $layanan_raw = explode('|', $_POST['layanan']); 
    $nama_layanan = $layanan_raw[0];
    
    // Cek harga (Staff = Database, Supervisor = Input Manual)
    if ($_SESSION['role'] == 'supervisor') {
        $harga_final = $_POST['harga_tampil'];
    } else {
        $harga_final = $layanan_raw[1];
    }
    
    // Validasi ulang diskon di server
    $diskon_final = 0;
    if ($promo_aktif && in_array($nama_layanan, $daftar_layanan_promo)) {
        $diskon_final = $nominal_diskon;
    }

    $total_bayar = $harga_final - $diskon_final;
    if($total_bayar < 0) $total_bayar = 0;
    
    $metode = $_POST['metode']; 
    $tanggal = date('Y-m-d');
    $jam = date('H:i');

    $sql = "INSERT INTO transaksi (tanggal, jam, nama_pelanggan, jenis_layanan, harga, diskon, total_bayar, metode_pembayaran) 
            VALUES ('$tanggal', '$jam', '$nama', '$nama_layanan', '$harga_final', '$diskon_final', '$total_bayar', '$metode')";
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
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        
        input[readonly] { background-color: #e9ecef; color: #555; cursor: not-allowed; border: 1px solid #ccc; }
        input:not([readonly]) { background-color: #fff; border: 1px solid #28a745; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: 'Comic Sans MS'; }
        
        #inputTotal { background-color: #d4edda; font-weight: bold; color: #155724; font-size: 18px; border: 2px solid #c3e6cb; }
        
        button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; font-size: 16px; border-radius: 5px; margin-top: 10px; font-family: 'Comic Sans MS'; }
        button:hover { background-color: #218838; }
        
        /* BANNER PROMO (DENGAN TOMBOL STOP) */
        .promo-banner { 
            background: linear-gradient(45deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
            padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #d63384; font-weight: bold;
            display: <?= $promo_aktif ? 'flex' : 'none' ?>; /* Flex agar tombol ada di kanan */
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ffb3c1;
        }
        .promo-text { text-align: left; }
        
        .btn-stop {
            background: #dc3545; color: white; border: none; padding: 5px 10px; 
            border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-stop:hover { background: #c82333; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #6c757d; color: white; }
        .top-links { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .btn-link { text-decoration: none; color: #007bff; font-weight: bold; }
        
        .user-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    </style>

    <script>
        var diskonNominal = <?= $nominal_diskon ?>;
        var promoAktif = <?= $promo_aktif ? 'true' : 'false' ?>;
        var timeLeft = <?= $sisa_waktu ?>; 
        
        // Bagian promo
        var layananPromo = <?php echo json_encode($daftar_layanan_promo); ?>;
        // fungsi yang digunakan untuk melakukan perhitungan otomatis pada promo 
        function hitungOtomatis() {
            var harga = document.getElementById("inputHarga").value || 0;
            var select = document.getElementById("pilihLayanan");
            var selectedValue = select.value.split('|');
            var namaLayanan = selectedValue[0]; 
            
            var diskon = 0;
            // Cek promo aktif DAN layanan termasuk daftar promo
            if (promoAktif && layananPromo.includes(namaLayanan)) {
                diskon = diskonNominal;
            }
            
            var total = parseInt(harga) - parseInt(diskon);
            if(total < 0) total = 0;
            
            document.getElementById("inputDiskon").value = diskon;
            document.getElementById("inputTotal").value = total;
        }

        function updateHarga() {
            var select = document.getElementById("pilihLayanan");
            var hargaInput = document.getElementById("inputHarga");
            
            var nilai = select.value.split('|');
            if(nilai[1]) {
                hargaInput.value = nilai[1]; 
                hitungOtomatis(); 
            }
        }
        
        function startTimer() {
            if(!promoAktif || timeLeft <= 0) return;
            var timerDisplay = document.getElementById("timerDisplay");
            setInterval(function() {
                if(timeLeft <= 0) {
                    timerDisplay.innerHTML = "WAKTU HABIS!";
                    location.reload(); 
                } else {
                    timeLeft--;
                    var jam = Math.floor(timeLeft / 3600);
                    var menit = Math.floor((timeLeft % 3600) / 60);
                    var detik = timeLeft % 60;
                    timerDisplay.innerHTML = jam + "j " + menit + "m " + detik + "d";
                }
            }, 1000);
        }
    </script>
</head>
<body onload="startTimer()">

<div class="container">
    
    <div class="user-header">
        <div>
            Halo, <b><?= strtoupper($_SESSION['nama'] ?? 'USER') ?></b> 
            (<?= ($_SESSION['role'] ?? '') == 'supervisor' ? '👑 Supervisor' : '👤 Staff' ?>)
        </div>
        <a href="logout.php" style="color: red; text-decoration: none; font-weight: bold;">🚪 Logout</a>
    </div>

    <div class="top-links">
        <?php if(($_SESSION['role'] ?? '') == 'supervisor'): ?>
            <a href="pengaturan.php" class="btn-link">⚙️ Atur Menu & Promo</a>
            <a href="kelola_user.php" class="btn-link" style="color: #d63384;">👥 Kelola User & Reset Pass</a>
        <?php endif; ?>
        
        <a href="laporan.php" class="btn-link">📊 Laporan</a>
    </div>

    <div class="promo-banner">
        <div class="promo-text">
            🎉 <u>PROMO: <?= $nama_promo ?></u><br>
            ✂️ Potongan Rp <?= number_format($nominal_diskon) ?><br>
            ⏳ Sisa Waktu: <span id="timerDisplay">Menghitung...</span>
        </div>

        <form method="POST" onsubmit="return confirm('Yakin ingin MENGHENTIKAN promo ini sekarang?');">
            <button type="submit" name="stop_promo" class="btn-stop">🛑 STOP PROMO</button>
        </form>
    </div>

    <h2>🌸 Kasir Salon Rengganis</h2>

    <form method="POST">
        <div class="form-group">
            <label>Nama Pelanggan:</label>
            <input type="text" name="nama" required autocomplete="off">
        </div>

        <div class="form-group">
            <label>Jenis Layanan:</label>
            <select name="layanan" id="pilihLayanan" onchange="updateHarga()" required>
                <option value="">-- Pilih Layanan --</option>
                <?php foreach($opsi_layanan as $row): ?>
                    <?php $is_promo = in_array($row['nama_layanan'], $daftar_layanan_promo) && $promo_aktif; ?>
                    <option value="<?= $row['nama_layanan'] ?>|<?= $row['harga_default'] ?>" style="<?= $is_promo ? 'color: red; font-weight: bold;' : '' ?>">
                        <?= $row['nama_layanan'] ?> (Rp <?= number_format($row['harga_default']) ?>) <?= $is_promo ? '🏷️ PROMO' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>Harga (Rp):</label>
                <input type="number" name="harga_tampil" id="inputHarga" <?= $atribut_lock ?> onkeyup="hitungOtomatis()" oninput="hitungOtomatis()">
            </div>
            
            <div class="form-group" style="flex: 1;">
                <label>Diskon Otomatis:</label>
                <input type="number" name="diskon_tampil" id="inputDiskon" readonly>
            </div>
        </div>

        <div class="form-group">
            <label>Total Yang Harus Dibayar:</label>
            <input type="text" id="inputTotal" readonly>
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
                <td><?= $row['nama_pelanggan'] ?></td>
                <td><?= $row['jenis_layanan'] ?></td>
                <td style="color: red;"><?= ($row['diskon'] > 0) ? '-'.number_format($row['diskon']) : '-' ?></td>
                <td style="font-weight: bold;">Rp <?= number_format($row['total_bayar']) ?></td>
                <td>
                    <a href="index.php?cetak=<?= $row['id'] ?>" style="text-decoration: none;">🖨️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>