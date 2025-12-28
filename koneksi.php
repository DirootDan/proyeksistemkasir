<?php
// FILE: koneksi.php
date_default_timezone_set('Asia/Jakarta');

// Deteksi path database supaya aman di PHP Desktop
$db_file = __DIR__ . '/salon.db';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- FITUR AUTO-REPAIR (PERBAIKAN OTOMATIS) ---
    // Cek apakah tabel 'transaksi' sudah ada?
    $cek_tabel = $db->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='transaksi'")->fetchColumn();

    // JIKA BELUM ADA, BUAT SEMUA TABEL SEKARANG
    if ($cek_tabel == 0) {
        
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

        // 3. Tabel Lainnya (Layanan, Terapis, Promo, Info Toko)
        $db->exec("CREATE TABLE IF NOT EXISTS master_layanan (id INTEGER PRIMARY KEY AUTOINCREMENT, nama_layanan TEXT NOT NULL, harga_default INTEGER)");
        $db->exec("CREATE TABLE IF NOT EXISTS master_terapis (id INTEGER PRIMARY KEY AUTOINCREMENT, nama_terapis TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS master_metode (id INTEGER PRIMARY KEY AUTOINCREMENT, nama_metode TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS daftar_promo (id INTEGER PRIMARY KEY, nama_promo TEXT, jenis_diskon TEXT, nilai_diskon INTEGER, target_layanan TEXT, berlaku_sampai DATETIME)");
        $db->exec("CREATE TABLE IF NOT EXISTS info_toko (id INTEGER PRIMARY KEY, nama_toko TEXT, alamat_toko TEXT, pesan_footer TEXT)");

        // --- ISI DATA AWAL (DEFAULT) ---
        
        // Buat User ADMIN (Penting biar bisa login!)
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, nama_lengkap, role) VALUES ('admin', '$pass', 'Super Admin', 'supervisor')");

        // Buat Info Toko
        $db->exec("INSERT INTO info_toko (nama_toko, alamat_toko, pesan_footer) VALUES ('Salon Cantik', 'Jl. Mawar No. 1', 'Terima Kasih')");

        // Buat Contoh Layanan
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('Potong Rambut', 35000)");
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('Creambath', 50000)");
        
        // Buat Contoh Metode Bayar
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('Tunai')");
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('QRIS')");
    }

} catch (PDOException $e) {
    die("<div style='text-align:center; margin-top:50px; color:red;'>
            <h2>Gagal Koneksi Database 😔</h2>
            <p>" . $e->getMessage() . "</p>
         </div>");
}
?>