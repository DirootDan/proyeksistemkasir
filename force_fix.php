<?php
// FILE: force_fix.php (VERSI TOTAL / SAPU JAGAT)
// Script ini memperbaiki Data Master Menu DAN Data Transaksi sekaligus.
require_once 'koneksi.php';

try {
    $db->beginTransaction(); // Mulai proses perbaikan massal

    // 1. PERBAIKI MASTER LAYANAN (DAFTAR MENU)
    // Logika: Jika harga menu < 1000 (misal 50), kali 1000 (jadi 50.000)
    $db->exec("UPDATE master_layanan SET harga_default = harga_default * 1000 WHERE harga_default > 0 AND harga_default < 1000");

    // 2. PERBAIKI TRANSAKSI (RIWAYAT)
    // Perbaiki Total Bayar
    $db->exec("UPDATE transaksi SET total_bayar = total_bayar * 1000 WHERE total_bayar > 0 AND total_bayar < 1000");
    
    // Perbaiki Harga Dasar (Subtotal)
    $db->exec("UPDATE transaksi SET harga = harga * 1000 WHERE harga > 0 AND harga < 1000");
    
    // Perbaiki Nilai Diskon
    $db->exec("UPDATE transaksi SET diskon = diskon * 1000 WHERE diskon > 0 AND diskon < 1000");

    // 3. PERBAIKI DAFTAR PROMO
    // Hanya untuk diskon tipe 'nominal' (Rupiah)
    $db->exec("UPDATE daftar_promo SET nilai_diskon = nilai_diskon * 1000 WHERE jenis_diskon = 'nominal' AND nilai_diskon > 0 AND nilai_diskon < 1000");

    $db->commit(); // Simpan semua perubahan

    // Pesan Sukses (Bahasa Profesional)
    echo "<script>
            alert('✅ SUKSES! Seluruh data (Menu & Transaksi) telah diperbaiki dan disinkronkan.');
            window.location='pengaturan.php';
          </script>";

} catch (Exception $e) {
    $db->rollBack(); // Batalkan jika ada error
    echo "<h3>Gagal Memperbaiki Data:</h3>";
    echo $e->getMessage();
}
?>