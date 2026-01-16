<?php
// FILE: export_excel.php
// VERSI FINAL: Layout Logis (Harga Normal -> Diskon -> Total)

require_once 'koneksi.php';

// Validasi Login
if (!isset($_COOKIE['user_id'])) { die("Akses Ditolak. Silakan login."); }

// BERSIHKAN BUFFER (Mencegah file korup)
if (ob_get_contents()) ob_end_clean();

// 1. TANGKAP INPUT FILTER
$tipe = $_GET['tipe'] ?? 'bulanan';
$judul_periode = "";
$nama_file_suffix = "";
$conditions = [];

// 2. LOGIKA QUERY
if ($tipe == 'harian') {
    $tgl = $_GET['tanggal']; 
    $conditions[] = "tanggal = '$tgl'";
    $judul_periode = "Harian: " . date('d F Y', strtotime($tgl));
    $nama_file_suffix = "Harian_" . $tgl;

} elseif ($tipe == 'tahunan') {
    $thn = $_GET['tahun'];
    $conditions[] = "strftime('%Y', tanggal) = '$thn'";
    $judul_periode = "Tahunan: " . $thn;
    $nama_file_suffix = "Tahunan_" . $thn;

} else {
    $bln = $_GET['bulan'];
    $thn = $_GET['tahun'];
    $conditions[] = "strftime('%m', tanggal) = '$bln'";
    $conditions[] = "strftime('%Y', tanggal) = '$thn'";
    
    $nama_bulan = date('F', mktime(0,0,0,$bln, 10));
    $judul_periode = "Bulanan: $nama_bulan $thn";
    $nama_file_suffix = "Bulanan_" . $bln . "-" . $thn;
}

// Susun Query
$sql = "SELECT * FROM transaksi";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY tanggal ASC, jam ASC";

$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung Total Omzet (Total Akhir/Net)
$total_omzet = 0;
foreach($data as $d) { $total_omzet += $d['total_bayar']; }

// AMBIL HARGA MASTER (Untuk Referensi Harga Satuan)
$ref_harga = [];
$q_harga = $db->query("SELECT nama_layanan, harga_default FROM master_layanan");
while($h = $q_harga->fetch(PDO::FETCH_ASSOC)){
    $ref_harga[$h['nama_layanan']] = $h['harga_default'];
}

// 3. SET HEADER EXCEL
$filename = "Laporan_Salon_" . $nama_file_suffix . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000000; padding: 6px; vertical-align: top; }
        .header-row { background-color: #ec4899; color: #ffffff; font-weight: bold; text-align: center; vertical-align: middle; }
        .footer-row { background-color: #fce7f3; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        /* Fix untuk break line di Excel */
        br { mso-data-placement:same-cell; }
    </style>
</head>
<body>
    <center>
        <h2>LAPORAN TRANSAKSI SALON</h2>
        <h4><?= $judul_periode ?></h4>
    </center>
    <table>
        <thead>
            <tr class="header-row">
                <th width="30">No</th>
                <th width="80">No Nota</th>
                <th width="100">Waktu</th>
                <th width="120">Pelanggan</th>
                <th width="150">Detail Layanan</th>
                <th width="120">Dikerjakan Oleh</th>
                <th width="100">Harga Normal (Rp)</th>
                <th width="80">Metode</th>
                <th width="60">Diskon (%)</th> 
                <th width="80">Diskon (Rp)</th> 
                <th width="100">Total Bayar</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if(count($data) > 0):
                $no = 1;
                foreach($data as $row):
                    // -- LOGIKA PARSING LAYANAN --
                    $raw_items = explode(',', $row['jenis_layanan']);
                    
                    // 1. Hitung Estimasi Total Master (Harga Standar Katalog)
                    $temp_items = [];
                    $total_estimasi_master = 0;

                    foreach($raw_items as $raw) {
                        $raw = trim($raw);
                        $parts = explode('[', $raw);
                        $full_nama_layanan = trim($parts[0]); 
                        $stylist = (isset($parts[1])) ? str_replace(']', '', $parts[1]) : '-';
                        
                        // Cek qty jika ada format "Nama Layanan (2)"
                        $qty = 1; $real_nama = $full_nama_layanan; 
                        if (strpos($full_nama_layanan, '(') !== false) {
                            $split_qty = explode('(', $full_nama_layanan);
                            $real_nama = trim($split_qty[0]); 
                            $str_qty = isset($split_qty[1]) ? $split_qty[1] : ''; 
                            $qty = (int) filter_var($str_qty, FILTER_SANITIZE_NUMBER_INT);
                        }
                        if($qty < 1) $qty = 1;

                        // Ambil harga dari referensi master
                        $harga_master = isset($ref_harga[$real_nama]) ? $ref_harga[$real_nama] : 0;
                        $subtotal_master = $harga_master * $qty;
                        $total_estimasi_master += $subtotal_master;

                        $temp_items[] = [
                            'nama' => $full_nama_layanan,
                            'stylist' => $stylist,
                            'subtotal_master' => $subtotal_master
                        ];
                    }

                    // 2. Hitung Matematika Diskon & Harga Asli
                    // $row['harga'] = Subtotal Gross (Sebelum Diskon)
                    // $row['total_bayar'] = Grand Total Net (Setelah Diskon)
                    
                    $harga_gross = $row['harga']; 
                    $harga_net = $row['total_bayar'];

                    // Rumus Diskon Rupiah
                    $diskon_rupiah = $harga_gross - $harga_net;
                    
                    // Rumus Diskon Persen
                    $diskon_persen = 0;
                    if($harga_gross > 0 && $diskon_rupiah > 0) {
                        $diskon_persen = ($diskon_rupiah / $harga_gross) * 100;
                    }

                    // Rasio: Digunakan jika user mengubah harga manual (override) tapi bukan via kolom diskon
                    // Agar harga per item tetap proporsional terhadap Subtotal Gross
                    $rasio = ($total_estimasi_master > 0) ? ($harga_gross / $total_estimasi_master) : 0;

                    // 3. Siapkan Tampilan Per Baris
                    $arr_layanan = [];
                    $arr_terapis = [];
                    $arr_nominal = [];

                    foreach($temp_items as $item) {
                        // Harga Tampil = Harga Item Master * Rasio ke Harga Gross Transaksi
                        // Ini menampilkan "Harga Satuan Sebelum Diskon"
                        $harga_tampil = $item['subtotal_master'] * $rasio; 

                        $arr_layanan[] = "• " . $item['nama'];
                        $arr_terapis[] = "• " . $item['stylist'];
                        $arr_nominal[] = "Rp " . number_format($harga_tampil);
                    }
                    
                    $display_layanan = implode('<br>', $arr_layanan);
                    $display_terapis = implode('<br>', $arr_terapis);
                    $display_nominal = implode('<br>', $arr_nominal);
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center" style="mso-number-format:'\@';">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?><br><small><?= $row['jam'] ?></small></td>
                <td><?= $row['nama_pelanggan'] ?></td>
                
                <td style="vertical-align:top;"><?= $display_layanan ?></td>
                <td style="vertical-align:top;"><?= $display_terapis ?></td>
                <td class="text-right" style="vertical-align:top;"><?= $display_nominal ?></td>

                <td class="text-center"><?= $row['metode_pembayaran'] ?></td>
                
                <td class="text-center">
                    <?= $diskon_persen > 0.1 ? round($diskon_persen, 1) . '%' : '-' ?>
                </td>

                <td class="text-right">
                    <?= $diskon_rupiah > 0 ? number_format($diskon_rupiah) : '-' ?>
                </td>

                <td class="text-right text-bold">Rp <?= number_format($row['total_bayar']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="11" class="text-center">Data tidak ditemukan.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td colspan="10" class="text-right">TOTAL OMZET</td>
                <td class="text-right">Rp <?= number_format($total_omzet) ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>