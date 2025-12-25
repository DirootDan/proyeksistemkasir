<?php
$db = new PDO("sqlite:salon.db");

// PROSES TAMBAH LAYANAN
if (isset($_POST['tambah_layanan'])) {
    $nama = $_POST['nama_layanan'];
    $harga = $_POST['harga'];
    $db->exec("INSERT INTO master_layanan (nama_layanan, harga_default) VALUES ('$nama', '$harga')");
    header("Location: pengaturan.php");
}

// PROSES HAPUS LAYANAN
if (isset($_GET['hapus_layanan'])) {
    $id = $_GET['hapus_layanan'];
    $db->exec("DELETE FROM master_layanan WHERE id = $id");
    header("Location: pengaturan.php");
}

// PROSES TAMBAH METODE
if (isset($_POST['tambah_metode'])) {
    $nama = $_POST['nama_metode'];
    $db->exec("INSERT INTO master_metode (nama_metode) VALUES ('$nama')");
    header("Location: pengaturan.php");
}

// PROSES HAPUS METODE
if (isset($_GET['hapus_metode'])) {
    $id = $_GET['hapus_metode'];
    $db->exec("DELETE FROM master_metode WHERE id = $id");
    header("Location: pengaturan.php");
}

// AMBIL DATA
$list_layanan = $db->query("SELECT * FROM master_layanan");
$list_metode = $db->query("SELECT * FROM master_metode");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pengaturan Salon</title>
    <style>
        body { font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; padding: 20px; background: #f4f4f9; }
        .container { max-width: 900px; margin: 0 auto; display: flex; gap: 20px; }
        .box { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h3 { border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        input, button { padding: 8px; margin-bottom: 10px; width: 100%; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; font-size: 14px; }
        .btn-del { background: #dc3545; color: white; padding: 2px 5px; text-decoration: none; font-size: 12px; border-radius: 3px; }
        .btn-back { display: block; margin-bottom: 20px; text-decoration: none; color: #555; font-weight: bold; }
    </style>
</head>
<body>

<a href="index.php" class="btn-back">⬅ Kembali ke Kasir</a>

<div class="container">
    <div class="box">
        <h3>✂️ Daftar Layanan & Harga</h3>
        <form method="POST">
            <input type="text" name="nama_layanan" placeholder="Nama Layanan (mis: Nail Art)" required>
            <input type="number" name="harga" placeholder="Harga Default" required>
            <button type="submit" name="tambah_layanan">TAMBAH LAYANAN</button>
        </form>
        <table>
            <?php foreach($list_layanan as $row): ?>
            <tr>
                <td><?= $row['nama_layanan'] ?></td>
                <td>Rp <?= number_format($row['harga_default']) ?></td>
                <td><a href="?hapus_layanan=<?= $row['id'] ?>" class="btn-del" onclick="return confirm('Hapus?')">X</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="box">
        <h3>💳 Metode Pembayaran</h3>
        <form method="POST">
            <input type="text" name="nama_metode" placeholder="Metode (mis: OVO)" required>
            <button type="submit" name="tambah_metode">TAMBAH METODE</button>
        </form>
        <table>
            <?php foreach($list_metode as $row): ?>
            <tr>
                <td><?= $row['nama_metode'] ?></td>
                <td><a href="?hapus_metode=<?= $row['id'] ?>" class="btn-del" onclick="return confirm('Hapus?')">X</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

</body>
</html>