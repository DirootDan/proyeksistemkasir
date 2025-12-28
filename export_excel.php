<?php
// Koneksi Database
$db = new PDO("sqlite:salon.db");

// --- 1. LOGIKA FILTER (BULANAN, TAHUNAN, RANGE) ---
$periode_teks = "Semua Riwayat";
$sql = "SELECT * FROM transaksi";

// A. Filter Bulanan
if (isset($_GET['bulan']) && isset($_GET['tahun'])) {
    $bln = $_GET['bulan'];
    $thn = $_GET['tahun'];
    $mulai = "$thn-$bln-01";
    $selesai = date("Y-m-t", strtotime($mulai)); 
    $sql .= " WHERE tanggal BETWEEN '$mulai' AND '$selesai'";
    
    $nama_bulan = [
        '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
        '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
        '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
    ];
    $periode_teks = "Laporan Bulan: " . $nama_bulan[$bln] . " " . $thn;
}
// B. Filter Tahunan
else if (isset($_GET['tahun_saja'])) {
    $thn = $_GET['tahun_saja'];
    $mulai = "$thn-01-01";
    $selesai = "$thn-12-31";
    $sql .= " WHERE tanggal BETWEEN '$mulai' AND '$selesai'";
    $periode_teks = "Laporan Tahunan: $thn";
}
// C. Filter Kustom
else if (isset($_GET['tgl_mulai']) && isset($_GET['tgl_selesai'])) {
    $mulai = $_GET['tgl_mulai'];
    $selesai = $_GET['tgl_selesai'];
    $sql .= " WHERE tanggal BETWEEN '$mulai' AND '$selesai'";
    $periode_teks = "Periode: $mulai s/d $selesai";
}

$sql .= " ORDER BY tanggal DESC, jam DESC";
$data = $db->query($sql);
$semua_transaksi = $data->fetchAll(PDO::FETCH_ASSOC);

// --- 2. HITUNG TOTAL & RINCIAN ---
$total_omzet = 0;
$rincian_metode = []; 
$rincian_bulanan = [];

foreach($semua_transaksi as $t) {
    $total_omzet += $t['total_bayar'];
    $metode = $t['metode_pembayaran'] ?: 'Lainnya';
    
    // Per Metode
    if (!isset($rincian_metode[$metode])) { $rincian_metode[$metode] = 0; }
    $rincian_metode[$metode] += $t['total_bayar'];

    // Per Bulan (Untuk Rata-rata)
    $key_bulan = date('Y-m', strtotime($t['tanggal']));
    if (!isset($rincian_bulanan[$key_bulan])) { $rincian_bulanan[$key_bulan] = 0; }
    $rincian_bulanan[$key_bulan] += $t['total_bayar'];
}

ksort($rincian_bulanan);

// --- 3. GENERATE EXCEL XML ---
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Salon.xls"');

echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">

 <Styles>
  <Style ss:ID="HeaderStyle">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#007bff" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>

  <Style ss:ID="GreenHeader">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#28a745" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  
  <Style ss:ID="OrangeHeader">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#fd7e14" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>

  <Style ss:ID="NormalStyle">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>

  <Style ss:ID="MoneyStyle">
   <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
   <Font ss:Bold="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
 </Styles>

 <Worksheet ss:Name="Laporan Transaksi">
  <Table x:FullColumns="1" x:FullRows="1">
   
   <Column ss:Width="30"/> <Column ss:Width="70"/> <Column ss:Width="50"/> <Column ss:Width="120"/> <Column ss:Width="100"/> <Column ss:Width="150"/> <Column ss:Width="80"/> <Column ss:Width="60"/> <Column ss:Width="80"/> <Column ss:Width="80"/> <Row>
    <Cell ss:MergeAcross="9" ss:StyleID="NormalStyle">
        <Data ss:Type="String"><?= $periode_teks ?></Data>
    </Cell>
   </Row>

   <Row>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Jam</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Pelanggan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Terapis</Data></Cell> <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Layanan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Harga Asli</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Diskon</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Total Bayar</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Metode</Data></Cell>
   </Row>

   <?php $no=1; foreach($semua_transaksi as $row): ?>
   <Row>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['no_nota'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['tanggal'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['jam'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['nama_pelanggan'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['terapis'] ?? '-' ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['jenis_layanan'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['harga'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['diskon'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['total_bayar'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['metode_pembayaran'] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <Row>
    <Cell ss:Index="8" ss:StyleID="HeaderStyle"><Data ss:Type="String">GRAND TOTAL</Data></Cell>
    <Cell ss:StyleID="MoneyStyle"><Data ss:Type="Number"><?= $total_omzet ?></Data></Cell>
   </Row>

   <Row></Row>
   <Row></Row>

   <Row>
    <Cell ss:MergeAcross="1" ss:StyleID="GreenHeader"><Data ss:Type="String">RINCIAN METODE</Data></Cell>
   </Row>
   <?php foreach($rincian_metode as $nama_metode => $jumlah): ?>
   <Row>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $nama_metode ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $jumlah ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <Row></Row>
   <Row></Row>

   <Row>
    <Cell ss:MergeAcross="2" ss:StyleID="OrangeHeader"><Data ss:Type="String">ANALISA PENDAPATAN BULANAN</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Bulan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Pendapatan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Rata-rata Kumulatif</Data></Cell>
   </Row>

   <?php 
   $total_kumulatif = 0;
   $jumlah_bulan = 0;
   
   $bulan_names = [
       '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
       '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
       '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
   ];

   foreach($rincian_bulanan as $bln_key => $omzet_bulan_ini): 
       $pecah = explode('-', $bln_key);
       $nama_bln_tampil = $bulan_names[$pecah[1]] . " " . $pecah[0];

       $total_kumulatif += $omzet_bulan_ini;
       $jumlah_bulan++;
       $rata_rata_saat_ini = $total_kumulatif / $jumlah_bulan;
   ?>
   <Row>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $nama_bln_tampil ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $omzet_bulan_ini ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $rata_rata_saat_ini ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <Row>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">TOTAL RATA-RATA AKHIR</Data></Cell>
    <Cell ss:StyleID="MoneyStyle"><Data ss:Type="String">-</Data></Cell>
    <Cell ss:StyleID="MoneyStyle"><Data ss:Type="Number"><?= ($jumlah_bulan > 0) ? ($total_kumulatif / $jumlah_bulan) : 0 ?></Data></Cell>
   </Row>

  </Table>
 </Worksheet>
</Workbook>