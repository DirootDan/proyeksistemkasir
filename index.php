<?php
$db = new PDO("sqlite:salon.db");

// Ambil data untuk dropdown
$opsi_layanan = $db->query("SELECT * FROM master_layanan");
$opsi_metode = $db->query("SELECT * FROM master_metode");

// --- LOGIKA CETAK NOTA ---
if (isset($_GET['cetak'])) {
    $id_transaksi = $_GET['cetak'];
    $q = $db->query("SELECT * FROM transaksi WHERE id = $id_transaksi");
    $data = $q->fetch();

    if ($data) {
        // Desain Nota dengan Diskon
        $struk  = "      SALON RENGGANIS      \n";
        $struk .= "   Jl. Cantik No. 1 Klaten \n";
        $struk .= "============================\n";
        $struk .= "Tgl : " . $data['tanggal'] . " " . $data['jam'] . "\n";
        $struk .= "Plg : " . $data['nama_pelanggan'] . "\n";
        $struk .= "----------------------------\n";
        $struk .= $data['jenis_layanan'] . "\n";
        $struk .= "Harga       : " . number_format($data['harga']) . "\n";
        if($data['diskon'] > 0) {
            $struk .= "Diskon      : (" . number_format($data['diskon']) . ")\n";
        }
        $struk .= "----------------------------\n";
        $struk .= "TOTAL BAYAR : Rp " . number_format($data['total_bayar']) . "\n";
        $struk .= "Metode      : " . $data['metode_pembayaran'] . "\n";
        $struk .= "============================\n";
        $struk .= "    Terima Kasih Cantik!    \n";

        $nama_file = "nota_print.txt";
        file_put_contents($nama_file, $struk);
        pclose(popen("start notepad.exe $nama_file", "r"));
        header("Location: index.php");
    }
}

// PROSES SIMPAN
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $layanan_raw = explode('|', $_POST['layanan']); 
    $nama_layanan = $layanan_raw[0];
    
    // Ambil angka dari inputan (yang mungkin sudah diedit user)
    $harga = $_POST['harga']; 
    $diskon = $_POST['diskon'];
    
    // Hitung Total Final di Server (Biar aman)
    $total_bayar = $harga - $diskon; 
    
    $metode = $_POST['metode']; 
    $tanggal = date('Y-m-d');
    $jam = date('H:i');

    $sql = "INSERT INTO transaksi (tanggal, jam, nama_pelanggan, jenis_layanan, harga, diskon, total_bayar, metode_pembayaran) 
            VALUES ('$tanggal', '$jam', '$nama', '$nama_layanan', '$harga', '$diskon', '$total_bayar', '$metode')";
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
        /* Font Comic Sans sesuai permintaan */
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; background-color: #f4f4f9; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        
        /* Inputan kita rapikan */
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: 'Comic Sans MS'; }
        
        /* Highlight Total */
        #inputTotal { background-color: #e9ecef; font-weight: bold; color: #28a745; font-size: 18px; pointer-events: none; }
        
        button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; font-size: 16px; border-radius: 5px; margin-top: 10px; font-family: 'Comic Sans MS'; }
        button:hover { background-color: #218838; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #6c757d; color: white; }
        .top-links { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .btn-link { text-decoration: none; color: #007bff; font-weight: bold; }
    </style>

    <script>
        // --- OTAK KALKULATOR OTOMATIS ---
        function hitungOtomatis() {
            // Ambil nilai harga & diskon
            var harga = document.getElementById("inputHarga").value || 0;
            var diskon = document.getElementById("inputDiskon").value || 0;
            
            // Hitung
            var total = parseInt(harga) - parseInt(diskon);
            
            // Tampilkan ke kolom Total (Kalau minus jadi 0)
            if(total < 0) total = 0;
            document.getElementById("inputTotal").value = total;
        }

        function updateHarga() {
            var select = document.getElementById("pilihLayanan");
            var hargaInput = document.getElementById("inputHarga");
            
            // Ambil harga dari dropdown
            var nilai = select.value.split('|');
            if(nilai[1]) {
                hargaInput.value = nilai[1]; 
                hitungOtomatis(); // Langsung hitung ulang
            }
        }
    </script>
</head>
<body>

<div class="container">
    <div class="top-links">
        <a href="pengaturan.php" class="btn-link">⚙️ Atur Menu</a>
        <a href="laporan.php" class="btn-link">📊 Laporan</a>
    </div>

    <h2>🌸 Kasir Salon Rengganis</h2>

    <form method="POST">
        <div class="form-group">
            <label>Nama Pelanggan:</label>
            <input type="text" name="nama" required>
        </div>

        <div class="form-group">
            <label>Jenis Layanan:</label>
            <select name="layanan" id="pilihLayanan" onchange="updateHarga()" required>
                <option value="">-- Pilih Layanan --</option>
                <?php foreach($opsi_layanan as $row): ?>
                    <option value="<?= $row['nama_layanan'] ?>|<?= $row['harga_default'] ?>">
                        <?= $row['nama_layanan'] ?> (Rp <?= number_format($row['harga_default']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>Harga (Bisa Diedit):</label>
                <input type="number" name="harga" id="inputHarga" onkeyup="hitungOtomatis()" required>
            </div>
            
            <div class="form-group" style="flex: 1;">
                <label>Diskon / Potongan (Rp):</label>
                <input type="number" name="diskon" id="inputDiskon" placeholder="0" onkeyup="hitungOtomatis()">
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