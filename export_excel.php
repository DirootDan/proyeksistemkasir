<?php
require_once 'koneksi.php';

// 1. Validasi & Filter
if (!isset($_COOKIE['user_id'])) { die("Akses Ditolak."); }

$tipe = $_GET['tipe'] ?? 'bulanan';
$judul_periode = "Periode: " . ucfirst($tipe); // Contoh sederhana

// 2. Ambil Data
$sql = "SELECT * FROM transaksi ORDER BY tanggal ASC, jam ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


$filename = "Laporan_Salon_" . date('YmdHis') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 4. Buka output stream (php://output)
$output = fopen('php://output', 'w');

// 5. Tulis Header Laporan (Opsional)
fputcsv($output, ['LAPORAN TRANSAKSI SALON']);
fputcsv($output, [$judul_periode]);
fputcsv($output, []); // Baris kosong untuk jarak

// 6. Tulis Header Kolom Tabel
$headers = ['No', 'No Nota', 'Waktu', 'Pelanggan', 'Detail Layanan', 'Stylist', 'Harga Normal', 'Metode', 'Total Bayar'];
fputcsv($output, $headers);

// 7. Looping Data
$total_omzet = 0;
foreach ($data as $index => $row) {
    // Bersihkan tag HTML jika ada (seperti logika kamu sebelumnya)
    $layanan_clean = strip_tags(str_replace(['<br>', '<br/>', '<br />'], " | ", $row['detail_layanan'] ?? ''));
    
    // Siapkan baris data
    $line = [
        $index + 1,
        "#" . str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT),
        $row['tanggal'] . " " . $row['jam'],
        $row['nama_pelanggan'],
        $layanan_clean,
        $row['stylist'] ?? '-',
        $row['harga'],
        $row['metode_pembayaran'],
        $row['total_bayar']
    ];
    
    // Tulis baris ke CSV
    fputcsv($output, $line);
    
    $total_omzet += $row['total_bayar'];
}

// 8. Tambahkan Footer Total
fputcsv($output, []); // Baris kosong
fputcsv($output, ['', '', '', '', '', '', '', 'TOTAL OMZET', $total_omzet]);
// ini sudah dibuat 
fclose($output); 
exit;