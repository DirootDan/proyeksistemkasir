<?php
// 1. KONEKSI DATABASE
$db = new PDO("sqlite:salon.db");

// 2. MANTRA AJAIB (HEADER)
// Ini yang bikin file ini didownload sebagai Excel (.xls), bukan dibuka sebagai web biasa.
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Salon_Rengganis.xls");

// 3. AMBIL DATA DARI DATABASE
// Kita ambil semua riwayat transaksi
$data = $db->query("SELECT * FROM transaksi ORDER BY tanggal DESC, jam DESC");
?>

<center>
    <h3>LAPORAN KEUANGAN SALON RENGGANIS</h3>
</center>

<table border="1">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>No</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Nama Pelanggan</th>
            <th>Layanan</th>
            <th>Harga Awal (Sebelum Diskon)</th>
            <th>Potongan / Diskon</th>
            <th>TOTAL AKHIR (Setelah Diskon)</th>
            <th>Metode Bayar</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach($data as $row): 
        ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= $row['tanggal'] ?></td>
            <td><?= $row['jam'] ?></td>
            <td><?= $row['nama_pelanggan'] ?></td>
            <td><?= $row['jenis_layanan'] ?></td>
            
            <td><?= $row['harga'] ?></td> 
            
            <td><?= $row['diskon'] ?></td>
            
            <td><?= $row['total_bayar'] ?></td>
            
            <td><?= $row['metode_pembayaran'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>