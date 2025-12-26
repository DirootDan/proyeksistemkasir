<?php
// Koneksi Database
$db = new PDO("sqlite:salon.db");

// Header agar Browser tahu ini file Excel XML
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Salon_Rengganis.xls"');

// Ambil Data
$data = $db->query("SELECT * FROM transaksi ORDER BY tanggal DESC, jam DESC");
$semua_transaksi = $data->fetchAll(PDO::FETCH_ASSOC);

// Hitung Total
$total_omzet = 0;
foreach($semua_transaksi as $t) {
    $total_omzet += $t['total_bayar'];
}

// --- MULAI TULIS FORMAT XML EXCEL ---
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
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#007bff" ss:Pattern="Solid"/>
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
   
   <Column ss:Width="30"/> <Column ss:Width="70"/> <Column ss:Width="50"/> <Column ss:Width="120"/> <Column ss:Width="100"/> <Column ss:Width="80"/> <Column ss:Width="60"/> <Column ss:Width="80"/> <Column ss:Width="80"/> <Row>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Jam</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Pelanggan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Layanan</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Harga Asli</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Diskon</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Total Bayar</Data></Cell>
    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Metode</Data></Cell>
   </Row>

   <?php $no=1; foreach($semua_transaksi as $row): ?>
   <Row>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['tanggal'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['jam'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['nama_pelanggan'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['jenis_layanan'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['harga'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['diskon'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="Number"><?= $row['total_bayar'] ?></Data></Cell>
    <Cell ss:StyleID="NormalStyle"><Data ss:Type="String"><?= $row['metode_pembayaran'] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <Row>
    <Cell ss:Index="7" ss:StyleID="HeaderStyle"><Data ss:Type="String">TOTAL OMZET</Data></Cell>
    <Cell ss:StyleID="MoneyStyle"><Data ss:Type="Number"><?= $total_omzet ?></Data></Cell>
   </Row>

  </Table>
 </Worksheet>
</Workbook>