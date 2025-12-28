<?php
// FILE: export_excel.php
require_once 'koneksi.php';

// --- 1. LOGIKA FILTER DATA ---
$sql = "SELECT * FROM transaksi";
$conditions = [];
$judul_periode = "Semua Waktu";

// Filter Bulan & Tahun
if (isset($_GET['bulan']) && isset($_GET['tahun'])) {
    $m = $_GET['bulan']; 
    $y = $_GET['tahun'];
    $conditions[] = "strftime('%m', tanggal) = '$m'";
    $conditions[] = "strftime('%Y', tanggal) = '$y'";
    $judul_periode = "Periode $m/$y";
} 
// Filter Tahunan Saja
elseif (isset($_GET['tahun_saja'])) {
    $y = $_GET['tahun_saja'];
    $conditions[] = "strftime('%Y', tanggal) = '$y'";
    $judul_periode = "Tahun $y";
}
// Filter Range Tanggal Custom
elseif (isset($_GET['tgl_mulai']) && isset($_GET['tgl_selesai'])) {
    $start = $_GET['tgl_mulai'];
    $end = $_GET['tgl_selesai'];
    $conditions[] = "tanggal BETWEEN '$start' AND '$end'";
    $judul_periode = "$start s/d $end";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY tanggal DESC, jam DESC";

// Ambil Data
$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 2. HITUNG STATISTIK (AVERAGE) ---
$total_omzet = 0;
$unik_hari = [];
$unik_bulan = [];
$unik_tahun = [];

foreach ($data as $row) {
    $total_omzet += $row['total_bayar'];
    $unik_hari[$row['tanggal']] = true; // Hitung hari unik
    $unik_bulan[substr($row['tanggal'], 0, 7)] = true; // Hitung bulan unik (YYYY-MM)
    $unik_tahun[substr($row['tanggal'], 0, 4)] = true; // Hitung tahun unik (YYYY)
}

$count_hari = count($unik_hari);
$count_bulan = count($unik_bulan);
$count_tahun = count($unik_tahun);

$avg_harian = ($count_hari > 0) ? $total_omzet / $count_hari : 0;
$avg_bulanan = ($count_bulan > 0) ? $total_omzet / $count_bulan : 0;
$avg_tahunan = ($count_tahun > 0) ? $total_omzet / $count_tahun : 0;

// --- 3. GENERATE EXCEL (XML FORMAT) ---
// Kita pakai format XML Spreadsheet 2003 agar support styling (Bold, Wrap Text) tanpa library tambahan
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Salon_" . date('Ymd_His') . ".xls");

echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 
 <Styles>
  <Style ss:ID="header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#ec4899" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>

  <Style ss:ID="wrap">
   <Alignment ss:Vertical="Top" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  
  <Style ss:ID="title">
   <Font ss:Bold="1" ss:Size="14"/>
  </Style>
  
  <Style ss:ID="currency">
   <NumberFormat ss:Format="#,##0"/>
   <Alignment ss:Vertical="Top"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
 </Styles>

 <Worksheet ss:Name="Laporan Transaksi">
  <Table>
   <Column ss:Width="30"/>  <Column ss:Width="80"/>  <Column ss:Width="100"/> <Column ss:Width="120"/> <Column ss:Width="150"/> <Column ss:Width="100"/> <Column ss:Width="80"/>  <Column ss:Width="80"/>  <Column ss:Width="100"/> <Row>
    <Cell ss:StyleID="title"><Data ss:Type="String">Laporan Keuangan Salon</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Periode:</Data></Cell>
    <Cell><Data ss:Type="String"><?= $judul_periode ?></Data></Cell>
   </Row>
   <Row ss:Index="4">
    <Cell><Data ss:Type="String">Rata-rata Per Hari:</Data></Cell>
    <Cell><Data ss:Type="Number"><?= $avg_harian ?></Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Rata-rata Per Bulan:</Data></Cell>
    <Cell><Data ss:Type="Number"><?= $avg_bulanan ?></Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Rata-rata Per Tahun:</Data></Cell>
    <Cell><Data ss:Type="Number"><?= $avg_tahunan ?></Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Total Omzet:</Data></Cell>
    <Cell><Data ss:Type="Number"><?= $total_omzet ?></Data></Cell>
   </Row>
   <Row></Row> <Row>
    <Cell ss:StyleID="header"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">No Nota</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Waktu</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Pelanggan</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Detail Layanan</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Terapis Handling</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Metode</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Diskon</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Total Bayar</Data></Cell>
   </Row>

   <?php 
   $no = 1;
   foreach ($data as $row): 
       // --- LOGIKA PARSING LAYANAN & TERAPIS ---
       // Data di DB: "Potong [Siti], Creambath [Dwi]"
       $arr_layanan = [];
       $arr_terapis = [];
       
       $items = explode(',', $row['jenis_layanan']);
       foreach($items as $item) {
           $item = trim($item);
           if (strpos($item, '[') !== false) {
               // Pecah string "Layanan [Nama]"
               $parts = explode('[', $item);
               $nama_svc = trim($parts[0]);
               $nama_sty = str_replace(']', '', $parts[1]);
               
               $arr_layanan[] = "• " . $nama_svc;
               $arr_terapis[] = $nama_sty;
           } else {
               // Kasus lama / tanpa kurung
               $arr_layanan[] = "• " . $item;
               $arr_terapis[] = "-";
           }
       }
       
       // Gabungkan dengan Enter (&#10; adalah kode Enter di XML Excel)
       $str_layanan = implode("&#10;", $arr_layanan);
       $str_terapis = implode("&#10;", $arr_terapis);
   ?>
   <Row>
    <Cell ss:StyleID="wrap"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="wrap"><Data ss:Type="String">#<?= str_pad($row['no_nota'], 4, '0', STR_PAD_LEFT) ?></Data></Cell>
    <Cell ss:StyleID="wrap"><Data ss:Type="String"><?= $row['tanggal'] ?> <?= $row['jam'] ?></Data></Cell>
    <Cell ss:StyleID="wrap"><Data ss:Type="String"><?= $row['nama_pelanggan'] ?></Data></Cell>
    
    <Cell ss:StyleID="wrap"><Data ss:Type="String"><?= $str_layanan ?></Data></Cell>
    <Cell ss:StyleID="wrap"><Data ss:Type="String"><?= $str_terapis ?></Data></Cell>
    
    <Cell ss:StyleID="wrap"><Data ss:Type="String"><?= $row['metode_pembayaran'] ?></Data></Cell>
    <Cell ss:StyleID="currency"><Data ss:Type="Number"><?= $row['diskon'] ?></Data></Cell>
    <Cell ss:StyleID="currency"><Data ss:Type="Number"><?= $row['total_bayar'] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

  </Table>
 </Worksheet>
</Workbook>