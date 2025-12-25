<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 1. Buat Tabel Master Layanan (Daftar Menu)
    $db->exec("CREATE TABLE IF NOT EXISTS master_layanan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_layanan TEXT NOT NULL,
        harga_default REAL
    )");

    // 2. Buat Tabel Master Metode Pembayaran (Tunai, Transfer, dll)
    $db->exec("CREATE TABLE IF NOT EXISTS master_metode (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_metode TEXT NOT NULL
    )");

    // 3. Tambah Kolom 'metode_pembayaran' di tabel transaksi (kalau belum ada)
    // Kita pakai trik 'silent error' kalau kolom sudah ada agar tidak crash
    try {
        $db->exec("ALTER TABLE transaksi ADD COLUMN metode_pembayaran TEXT DEFAULT 'Tunai'");
    } catch (Exception $e) {
        // Abaikan jika kolom sudah ada
    }

    // 4. Isi Data Awal (Supaya tidak kosong melompong)
    // Cek dulu apakah tabel kosong, kalau kosong baru diisi
    $cek = $db->query("SELECT count(*) FROM master_layanan")->fetchColumn();
    if ($cek == 0) {
        $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES 
            ('Potong Rambut', 35000), 
            ('Creambath', 75000),
            ('Smoothing', 250000)");
            
        $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('Tunai'), ('Transfer BCA'), ('QRIS')");
    }

    echo "<h1>Upgrade Sukses! 🚀</h1> Database Salon Rengganis sudah level up!";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<?php
$db = new PDO("sqlite:salon.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Kita pakai trik 'silent error' lagi agar tidak crash jika kolom sudah ada
    try {
        $db->exec("ALTER TABLE transaksi ADD COLUMN diskon REAL DEFAULT 0");
    } catch (Exception $e) {}

    echo "<h1 style='font-family: Comic Sans MS'>Upgrade Sukses! 🚀</h1>"; 
    echo "Sekarang aplikasimu sudah punya fitur DISKON.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>