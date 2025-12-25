<?php
// Nama file database kita. Nanti akan muncul file 'salon.db' di folder yang sama.
$db_file = 'salon.db';

try {
    // 1. Membuat koneksi sekaligus file database jika belum ada
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Perintah SQL untuk membuat Tabel Transaksi (Inti aplikasi kita)
    // Kita buat simpel: Ada tanggal, layanan, dan uangnya.
    $sql_transaksi = "CREATE TABLE IF NOT EXISTS transaksi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal DATE NOT NULL,
        jam TIME NOT NULL,
        nama_pelanggan TEXT,
        jenis_layanan TEXT NOT NULL,
        harga REAL NOT NULL,
        diskon REAL DEFAULT 0,
        total_bayar REAL NOT NULL,
        catatan TEXT
    )";

    // 3. Eksekusi perintah
    $db->exec($sql_transaksi);

    echo "<h1>Berhasil! 🎉</h1>";
    echo "Database <b>salon.db</b> dan tabel <b>transaksi</b> sudah siap digunakan.<br>";
    

} catch (PDOException $e) {
    echo "Waduh, ada error: " . $e->getMessage();
}
?>