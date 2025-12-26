<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h1>🛠️ Perbaikan Database Total...</h1>";

try {
    // 1. CEK & BUAT TABEL PROMO (Ini yang bikin error tadi)
    $db->exec("CREATE TABLE IF NOT EXISTS setting_promo (
        id INTEGER PRIMARY KEY,
        nama_promo TEXT,
        nominal_diskon REAL,
        berlaku_sampai DATETIME
    )");
    // Isi default promo kalau kosong
    $cek = $db->query("SELECT count(*) FROM setting_promo")->fetchColumn();
    if ($cek == 0) {
        $db->exec("INSERT INTO setting_promo (id, nama_promo, nominal_diskon, berlaku_sampai) 
                   VALUES (1, 'Tidak Ada Promo', 0, '2020-01-01 00:00:00')");
        echo "✅ Tabel Promo berhasil dibuat.<br>";
    }

    // 2. CEK & BUAT TABEL INFO TOKO (Alamat Salon)
    $db->exec("CREATE TABLE IF NOT EXISTS info_toko (
        id INTEGER PRIMARY KEY,
        nama_toko TEXT,
        alamat_toko TEXT,
        pesan_footer TEXT
    )");
    $cek = $db->query("SELECT count(*) FROM info_toko")->fetchColumn();
    if ($cek == 0) {
        $db->exec("INSERT INTO info_toko (id, nama_toko, alamat_toko, pesan_footer) 
                   VALUES (1, 'SALON RENGGANIS', 'Jl. Cantik No. 1 Klaten', 'Terima Kasih, Cantik!')");
        echo "✅ Tabel Info Toko berhasil dibuat.<br>";
    }

    // 3. CEK & BUAT TABEL USERS (Login)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL
    )");
    $cek = $db->query("SELECT count(*) FROM users")->fetchColumn();
    if ($cek == 0) {
        $pass = password_hash('123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, role) VALUES ('owner', '$pass', 'supervisor')");
        $db->exec("INSERT INTO users (username, password, role) VALUES ('staff', '$pass', 'karyawan')");
        echo "✅ Tabel Users berhasil dibuat.<br>";
    }

    // 4. CEK & BUAT TABEL MENU (Layanan & Metode)
    $db->exec("CREATE TABLE IF NOT EXISTS master_layanan (id INTEGER PRIMARY KEY AUTOINCREMENT, nama_layanan TEXT, harga_default REAL)");
    $db->exec("CREATE TABLE IF NOT EXISTS master_metode (id INTEGER PRIMARY KEY AUTOINCREMENT, nama_metode TEXT)");
    
    // Pastikan ada isinya biar gak error
    $cek_layanan = $db->query("SELECT count(*) FROM master_layanan")->fetchColumn();
    if($cek_layanan == 0) {
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('Potong Rambut', 35000)");
        echo "✅ Data Layanan Awal dibuat.<br>";
    }
    
    $cek_metode = $db->query("SELECT count(*) FROM master_metode")->fetchColumn();
    if($cek_metode == 0) {
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('Tunai')");
        echo "✅ Data Metode Awal dibuat.<br>";
    }

    // 5. UPDATE TABEL TRANSAKSI (Tambah kolom diskon/metode jika belum ada)
    $kolom_baru = [
        "ALTER TABLE transaksi ADD COLUMN diskon REAL DEFAULT 0",
        "ALTER TABLE transaksi ADD COLUMN metode_pembayaran TEXT DEFAULT 'Tunai'"
    ];
    foreach($kolom_baru as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Diam saja kalau kolom sudah ada (Error diabaikan)
        }
    }

    echo "<hr><h3>🎉 SEMUA SIAP! Database sudah Sehat Walafiat.</h3>";
    echo "Silakan buka kembali halaman Login/Kasir.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>