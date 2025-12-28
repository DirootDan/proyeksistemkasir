<?php
require_once 'koneksi.php';

// Cek ID Transaksi
if (!isset($_GET['id'])) {
    die("ID Transaksi tidak ditemukan.");
}

$id = $_GET['id'];

// Ambil Data Transaksi
$stmt = $db->prepare("SELECT * FROM transaksi WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) { die("Data transaksi tidak ditemukan."); }

// Ambil Data Toko
$toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();

// Parsing Item Layanan (Format: "Potong Rambut [Siti], Creambath [-]")
$raw_items = explode(',', $data['jenis_layanan']);
$items = [];
foreach($raw_items as $raw) {
    $raw = trim($raw);
    $parts = explode('[', $raw);
    
    $nama_svc = trim($parts[0]);
    $stylist = (isset($parts[1])) ? str_replace(']', '', $parts[1]) : '-';
    
    // Ambil harga satuan dari master (Opsional, untuk display)
    // Note: Harga total tersimpan di DB, tapi harga satuan tidak disimpan per item di versi simpel ini.
    // Kita ambil dari master_layanan untuk referensi harga saat ini.
    $q_harga = $db->prepare("SELECT harga_default FROM master_layanan WHERE nama_layanan = ?");
    $q_harga->execute([$nama_svc]);
    $hrg = $q_harga->fetchColumn() ?: 0;

    $items[] = [
        'nama' => $nama_svc,
        'stylist' => $stylist,
        'harga' => $hrg
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Nota #<?= $data['no_nota'] ?></title>
    <style>
        /* CSS KHUSUS STRUK / THERMAL PRINTER */
        body {
            font-family: 'Courier New', Courier, monospace; /* Font ala mesin kasir */
            font-size: 12px;
            color: #000;
            margin: 0; padding: 0;
            width: 58mm; /* Standar Kertas Thermal Kecil */
        }

        .container {
            padding: 10px;
            padding-right: 20px; /* Sedikit jarak biar ga kepotong */
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        .header { margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        .footer { margin-top: 15px; text-align: center; font-size: 10px; }
        
        .meta-info { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 5px; }
        
        .item-row { margin-bottom: 5px; }
        .item-name { display: block; font-weight: bold; }
        .item-detail { display: flex; justify-content: space-between; font-size: 11px; }
        .stylist-name { font-style: italic; font-size: 10px; }

        .total-section { 
            border-top: 1px dashed #000; 
            margin-top: 10px; padding-top: 5px; 
        }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        
        /* Tombol Print (Akan hilang saat diprint) */
        .no-print {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: #eee; padding: 10px; text-align: center;
            border-top: 1px solid #ccc;
        }
        
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header text-center">
            <h3 style="margin:0; text-transform: uppercase;"><?= $toko['nama_toko'] ?></h3>
            <div style="font-size:10px;"><?= $toko['alamat_toko'] ?></div>
        </div>

        <div class="meta-info">
            <span>#<?= str_pad($data['no_nota'], 4, '0', STR_PAD_LEFT) ?></span>
            <span><?= date('d/m/y H:i', strtotime($data['tanggal'].' '.$data['jam'])) ?></span>
        </div>
        <div class="meta-info">
            <span>Pelanggan:</span>
            <span class="bold"><?= substr($data['nama_pelanggan'], 0, 15) ?></span>
        </div>

        <hr style="border:0; border-bottom:1px dashed #000;">

        <?php foreach($items as $i): ?>
        <div class="item-row">
            <span class="item-name"><?= $i['nama'] ?></span>
            <div class="item-detail">
                <span>
                    <?php if($i['stylist'] != '-'): ?>
                        <span class="stylist-name">(Sty: <?= $i['stylist'] ?>)</span>
                    <?php endif; ?>
                </span>
                <span><?= number_format($i['harga']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="total-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span><?= number_format($data['harga']) ?></span>
            </div>
            
            <?php if($data['diskon'] > 0): ?>
            <div class="total-row" style="color: black;">
                <span>Diskon</span>
                <span>-<?= number_format($data['diskon']) ?></span>
            </div>
            <?php endif; ?>

            <div class="total-row bold" style="font-size: 14px; margin-top: 5px;">
                <span>TOTAL</span>
                <span>Rp <?= number_format($data['total_bayar']) ?></span>
            </div>
            
            <br>
            <div class="total-row" style="font-size: 11px;">
                <span>Bayar: <?= $data['metode_pembayaran'] ?></span>
            </div>
        </div>

        <div class="footer">
            <?= $toko['pesan_footer'] ?><br>
            <small>Terima kasih atas kunjungan Anda!</small>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">🖨️ CETAK NOTA</button>
        <button onclick="window.close()" style="padding:10px 20px; cursor:pointer;">Tutup</button>
    </div>

    <script>
        // Otomatis muncul dialog print saat halaman dibuka
        window.onload = function() {
            window.print();
        }
    </script>

</body>
</html>