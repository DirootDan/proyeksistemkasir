<?php
require_once 'koneksi.php';

// Validasi ID Transaksi
if (!isset($_GET['id'])) die("ID Transaksi tidak ditemukan.");
$id = $_GET['id'];

// Ambil Data Transaksi
$stmt = $db->prepare("SELECT * FROM transaksi WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die("Data transaksi tidak ditemukan.");

// Ambil Info Toko
$toko = $db->query("SELECT * FROM info_toko WHERE id=1")->fetch();

// --- LOGIC ITEM ---
$raw_items = explode(',', $data['jenis_layanan']);
$items = [];
foreach($raw_items as $raw) {
    $raw = trim($raw);
    $parts = explode('[', $raw);
    $full_nama_layanan = trim($parts[0]); 
    $stylist = (isset($parts[1])) ? str_replace(']', '', $parts[1]) : '-';
    $qty = 1; $real_nama = $full_nama_layanan; 
    if (strpos($full_nama_layanan, '(') !== false) {
        $split_qty = explode('(', $full_nama_layanan);
        $real_nama = trim($split_qty[0]); 
        $str_qty = isset($split_qty[1]) ? $split_qty[1] : ''; 
        $qty = (int) filter_var($str_qty, FILTER_SANITIZE_NUMBER_INT);
    }
    if($qty < 1) $qty = 1;
    $q_harga = $db->prepare("SELECT harga_default FROM master_layanan WHERE nama_layanan = ?");
    $q_harga->execute([$real_nama]);
    $hrg_satuan = $q_harga->fetchColumn() ?: 0;
    $subtotal_item = $hrg_satuan * $qty;
    $items[] = ['nama' => $real_nama, 'qty' => $qty, 'stylist' => $stylist, 'harga_satuan' => $hrg_satuan, 'subtotal' => $subtotal_item];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Nota #<?= $data['no_nota'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #fff; 
            font-family: 'VT323', monospace; 
            font-size: 18px; color: #000; font-weight: bold;
            margin: 0; padding: 10px; width: 76mm; 
        }

        /* HEADER FLEXBOX */
        .header-layout {
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            margin-bottom: 15px; 
            border-bottom: 2px dashed #000; 
            padding-bottom: 10px;
        }

        .header-text { text-align: left; }
        .header-text h3 { font-size: 24px; margin: 0; line-height: 0.9; text-transform: uppercase; font-weight: 900; }
        
        .meta-info, .item-detail, .total-row { display: flex; justify-content: space-between; }
        .no-print { position: fixed; bottom: 0; left: 0; width: 100%; background: #eee; padding: 15px; text-align: center; border-top: 1px solid #ccc; font-family: sans-serif; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>

    <div class="header-layout">
        
        <img src="assets/images/logo_rengganis.png?v=<?= time() ?>" 
             alt="LOGO" 
             style="width: 60px; height: 60px; object-fit: contain;">

        <div class="header-text">
            <h3><?= $toko['nama_toko'] ?></h3>
            <div style="font-size:14px; margin-top:5px; line-height:1.1;"><?= $toko['alamat_toko'] ?></div>
            </div>

    </div>

    <div class="meta-info">
        <span>#<?= str_pad($data['no_nota'], 4, '0', STR_PAD_LEFT) ?></span>
        <span><?= date('d/m/y H:i', strtotime($data['tanggal'].' '.$data['jam'])) ?></span>
    </div>
    <div class="meta-info">
        <span>Pelanggan:</span>
        <span><?= substr($data['nama_pelanggan'], 0, 15) ?></span>
    </div>

    <hr style="border:0; border-bottom:1px dashed #000;">

    <?php foreach($items as $i): ?>
    <div style="margin-bottom: 10px; border-bottom: 1px dotted #000; padding-bottom: 5px;">
        <span style="display:block; font-size:18px;"><?= $i['nama'] ?></span>
        <div class="item-detail">
            <div>
                <?php if($i['qty'] > 1): ?><span><?= $i['qty'] ?> x <?= number_format($i['harga_satuan']) ?></span><?php endif; ?>
                <?php if($i['stylist'] != '-'): ?><span style="font-style:italic; font-size:14px;">Sty: <?= $i['stylist'] ?></span><?php endif; ?>
            </div>
            <span><?= number_format($i['subtotal']) ?></span>
        </div>
    </div>
    <?php endforeach; ?>

    <div style="border-top: 2px dashed #000; margin-top: 15px; padding-top: 10px;">
        <div class="total-row"><span>Subtotal</span><span><?= number_format($data['harga']) ?></span></div>
        <?php if($data['diskon'] > 0): ?>
        <div class="total-row"><span>Diskon</span><span>-<?= number_format($data['diskon']) ?></span></div>
        <?php endif; ?>
        <div class="total-row" style="font-size: 24px; margin-top: 5px; font-weight:900;">
            <span>TOTAL</span><span>Rp <?= number_format($data['total_bayar']) ?></span>
        </div>
        <div class="total-row" style="font-size: 14px;"><span>Bayar: <?= $data['metode_pembayaran'] ?></span></div>
    </div>

    <div style="text-align:center; margin-top:20px;">
        <?= $toko['pesan_footer'] ?><br>
        <small>Terima kasih atas kunjungan Anda!</small>
    </div>

    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">🖨️ CETAK NOTA</button>
        <button onclick="window.close()" style="padding:10px 20px; cursor:pointer;">Tutup</button>
    </div>
    
    <script>
        window.onload = function() { window.print(); }
    </script>

</body>
</html>