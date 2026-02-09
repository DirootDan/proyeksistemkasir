<?php
require 'vendor/autoload.php'; // Load library
require_once 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. Validasi & Filter (Logika Anda tetap sama)
if (!isset($_COOKIE['user_id'])) { die("Akses Ditolak."); }

$tipe = $_GET['tipe'] ?? 'bulanan';
$conditions = [];
// ... (Logika penentuan $conditions, $judul_periode sama seperti kode lama Anda) ...

// 2. Ambil Data
$sql = "SELECT * FROM transaksi";
if (!empty($conditions)) { $sql .= " WHERE " . implode(' AND ', $conditions); }
$sql .= " ORDER BY tanggal ASC, jam ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Transaksi');

// --- STYLING ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EC4899']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// 4. Header Tabel
$sheet->setCellValue('A1', 'LAPORAN TRANSAKSI SALON');
$sheet->mergeCells('A1:K1');
$sheet->setCellValue('A2', $judul_periode);
$sheet->mergeCells('A2:K2');

$headers = ['No', 'No Nota', 'Waktu', 'Pelanggan', 'Detail Layanan', 'Stylist', 'Harga Normal', 'Metode', 'Disc %', 'Disc Rp', 'Total Bayar'];
$sheet->fromArray($headers, NULL, 'A4');
$sheet->getStyle('A4:K4')->applyFromArray($styleHeader);

// 5. Looping Data
$rowNum = 5;
$total_omzet = 0;
foreach ($data as $index => $row) {
    // Logika parsing layanan Anda tetap digunakan di sini
    // (Gunakan variabel $display_layanan, $display_terapis dari kode lama Anda)
    
    $sheet->setCellValue('A' . $rowNum, $index + 1);
    $sheet->setCellValue('B' . $rowNum, "#" . str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT));
    $sheet->setCellValue('C' . $rowNum, $row['tanggal'] . " " . $row['jam']);
    $sheet->setCellValue('D' . $rowNum, $row['nama_pelanggan']);
    
    // Untuk detail yang ada baris barunya, gunakan \n dan set wrapText
    $sheet->setCellValue('E' . $rowNum, str_replace('<br>', "\n", $display_layanan));
    $sheet->setCellValue('F' . $rowNum, str_replace('<br>', "\n", $display_terapis));
    $sheet->getStyle('E'.$rowNum.':F'.$rowNum)->getAlignment()->setWrapText(true);
    
    $sheet->setCellValue('G' . $rowNum, $row['harga']);
    $sheet->setCellValue('H' . $rowNum, $row['metode_pembayaran']);
    $sheet->setCellValue('K' . $rowNum, $row['total_bayar']);
    
    $total_omzet += $row['total_bayar'];
    $rowNum++;
}

// 6. Footer Total
$sheet->setCellValue('A' . $rowNum, 'TOTAL OMZET');
$sheet->mergeCells("A$rowNum:J$rowNum");
$sheet->setCellValue('K' . $rowNum, $total_omzet);
$sheet->getStyle("A$rowNum:K$rowNum")->getFont()->setBold(true);

// 7. Output sebagai .XLSX (Format Asli)
$filename = "Laporan_Salon_" . date('YmdHis') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;