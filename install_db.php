<?php
// FILE: install_db.php
require_once 'koneksi.php';

echo "<h2>⚙️ Sedang Membuat Database Baru...</h2>";

try {
    // 1. Tabel Users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        nama_lengkap TEXT DEFAULT 'Staff Salon'
    )");

    // 2. Tabel Transaksi
    $db->exec("CREATE TABLE IF NOT EXISTS transaksi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        no_nota INTEGER DEFAULT 0,
        tanggal DATE NOT NULL,
        jam TIME NOT NULL,
        nama_pelanggan TEXT,
        jenis_layanan TEXT NOT NULL,
        harga REAL NOT NULL,
        diskon REAL DEFAULT 0,
        total_bayar REAL NOT NULL,
        metode_pembayaran TEXT DEFAULT 'Tunai',
        terapis TEXT DEFAULT '-'
    )");

    // 3. Tabel Layanan
    $db->exec("CREATE TABLE IF NOT EXISTS master_layanan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_layanan TEXT NOT NULL,
        harga_default INTEGER
    )");

    // 4. Tabel Terapis
    $db->exec("CREATE TABLE IF NOT EXISTS master_terapis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_terapis TEXT
    )");

    // 5. Tabel Metode Pembayaran
    $db->exec("CREATE TABLE IF NOT EXISTS master_metode (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_metode TEXT
    )");

    // 6. Tabel Promo
    $db->exec("CREATE TABLE IF NOT EXISTS daftar_promo (
        id INTEGER PRIMARY KEY, 
        nama_promo TEXT, 
        jenis_diskon TEXT, 
        nilai_diskon INTEGER, 
        target_layanan TEXT, 
        berlaku_sampai DATETIME
    )");

    // 7. Tabel Info Toko
    $db->exec("CREATE TABLE IF NOT EXISTS info_toko (
        id INTEGER PRIMARY KEY, 
        nama_toko TEXT, 
        alamat_toko TEXT, 
        pesan_footer TEXT
    )");

    // --- ISI DATA DEFAULT (Agar tidak kosong) ---

    // Isi Toko Default
    $cek_toko = $db->query("SELECT COUNT(*) FROM info_toko")->fetchColumn();
    if ($cek_toko == 0) {
        $db->exec("INSERT INTO info_toko (nama_toko, alamat_toko, pesan_footer) VALUES ('Salon Cantik', 'Jl. Mawar No. 1', 'Terima Kasih Kunjungannya')");
        echo "✅ Info Toko dibuat.<br>";
    }

    // Isi User Admin Default
    $cek_user = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($cek_user == 0) {
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $pass, 'Super Admin', 'supervisor']);
        echo "✅ User Admin dibuat.<br>";
    }

    // Isi Layanan Contoh
    $cek_layanan = $db->query("SELECT COUNT(*) FROM master_layanan")->fetchColumn();
    if ($cek_layanan == 0) {
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('Potong Rambut', 35000)");
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('Creambath', 50000)");
        echo "✅ Layanan contoh dibuat.<br>";
    }

    // Isi Metode Bayar Contoh
    $cek_metode = $db->query("SELECT COUNT(*) FROM master_metode")->fetchColumn();
    if ($cek_metode == 0) {
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('Tunai')");
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('Transfer')");
        echo "✅ Metode bayar dibuat.<br>";
    }

    echo "<hr><h1>SUKSES! Database Siap. 🎉</h1>";
    echo "<h3>Username: <b>admin</b></h3>";
    echo "<h3>Password: <b>admin123</b></h3>";
    echo "<br><a href='login.php' style='padding:10px; background:blue; color:white; text-decoration:none; border-radius:5px;'>KLIK DISINI UNTUK LOGIN >></a>";

} catch (PDOException $e) {
    die("Gagal membuat database: " . $e->getMessage());
}
?>