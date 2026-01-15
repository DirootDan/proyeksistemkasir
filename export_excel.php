<?php

require_once 'koneksi.php';

// Validasi Login
if (!isset($_COOKIE['user_id'])) { die("Akses Ditolak. Silakan login."); }

// BERSIHKAN BUFFER (Penting untuk mencegah error header)
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

// Eksekusi
$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung Total
$total_omzet = 0;
foreach($data as $d) { $total_omzet += $d['total_bayar']; }

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
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000000; padding: 8px; vertical-align: top; }
        .header-row { background-color: #ec4899; color: #ffffff; font-weight: bold; text-align: center; }
        .footer-row { background-color: #fce7f3; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        /* Style biar baris baru di dalam sel Excel rapi */
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
                <th>No</th>
                <th>No Nota</th>
                <th>Waktu</th>
                <th>Pelanggan</th>
                <th>Detail Layanan</th>
                <th>Dikerjakan Oleh (Terapis)</th> <th>Metode</th>
                <th>Diskon</th>
                <th>Total Bayar</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if(count($data) > 0):
                $no = 1;
                foreach($data as $row):
                    // LOGIKA PARSING TEXT DATABASE
                    $items = explode(',', $row['jenis_layanan']);
                    
                    $arr_layanan = [];
                    $arr_terapis = [];

                    foreach($items as $item) {
                        $item = trim($item);
                        // Cek apakah ada tanda kurung siku [Nama]
                        if (strpos($item, '[') !== false) {
                            $parts = explode('[', $item);
                            $nama_svc = trim($parts[0]);
                            $nama_sty = str_replace(']', '', $parts[1]);
                            
                            $arr_layanan[] = "• " . $nama_svc;
                            $arr_terapis[] = "• " . $nama_sty;
                        } else {
                            // Jika data lama tidak ada nama terapisnya
                            $arr_layanan[] = "• " . $item;
                            $arr_terapis[] = "-";
                        }
                    }
                    
                    // Gabungkan array jadi string dengan baris baru (Enter)
                    $display_layanan = implode('<br>', $arr_layanan);
                    $display_terapis = implode('<br>', $arr_terapis);
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center" style="mso-number-format:'\@';">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></td>
                <td class="text-center"><?= $row['tanggal'] ?><br><small><?= $row['jam'] ?></small></td>
                <td><?= $row['nama_pelanggan'] ?></td>
                
                <td><?= $display_layanan ?></td>
                
                <td><?= $display_terapis ?></td>

                <td class="text-center"><?= $row['metode_pembayaran'] ?></td>
                <td class="text-right"><?= $row['diskon'] > 0 ? number_format($row['diskon']) : '-' ?></td>
                <td class="text-right text-bold">Rp <?= number_format($row['total_bayar']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center">Data tidak ditemukan.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td colspan="8" class="text-right">TOTAL OMZET</td>
                <td class="text-right">Rp <?= number_format($total_omzet) ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>