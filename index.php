<?php
// --- 1. LOGIKA PHP ---
require_once 'koneksi.php';

// Cek Login
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }
$user_nama = $_COOKIE['nama'] ?? 'Staff';
$user_role = $_COOKIE['role'] ?? 'staff';

// Auto Repair DB
try { $db->query("SELECT no_nota FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN no_nota INTEGER DEFAULT 0"); }
try { $db->query("SELECT terapis FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN terapis TEXT DEFAULT '-'"); }

// Ambil Data
$opsi_layanan = $db->query("SELECT * FROM master_layanan ORDER BY nama_layanan ASC")->fetchAll(PDO::FETCH_ASSOC);
$opsi_metode = $db->query("SELECT * FROM master_metode")->fetchAll(PDO::FETCH_ASSOC);
$list_terapis = $db->query("SELECT * FROM master_terapis ORDER BY nama_terapis ASC")->fetchAll(PDO::FETCH_ASSOC);

// Data Promo
$sekarang = date('Y-m-d H:i:s');
$qp = $db->query("SELECT * FROM daftar_promo WHERE berlaku_sampai > '$sekarang'");
$promo_map = [];
foreach($qp->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $targets = explode(',', $p['target_layanan']);
    foreach($targets as $t) { $promo_map[trim($t)] = $p; }
}

// Proses Simpan
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $metode = $_POST['metode'];
    $layanan_pilih = $_POST['layanan'] ?? [];

    if(empty($layanan_pilih)) { 
        echo "<script>alert('⚠️ Pilih minimal satu layanan!'); window.location='index.php';</script>"; exit; 
    }

    $total_asli = 0; 
    $total_diskon = 0;
    $final_services_parts = [];

    foreach($layanan_pilih as $val) {
        $parts = explode('|', $val); // Nama|Harga|ID
        $nama_svc = $parts[0];
        $harga_svc = (int)$parts[1];
        $id_svc = $parts[2];
        
        // Ambil Data Inputan
        $input_sty = "terapis_" . $id_svc;
        $input_qty = "qty_" . $id_svc; 
        
        $stylist = $_POST[$input_sty] ?? '-';
        $qty = (int)($_POST[$input_qty] ?? 1); 
        if($qty < 1) $qty = 1;

        // Hitung Total (Harga x Jumlah)
        $subtotal_item = $harga_svc * $qty;
        $total_asli += $subtotal_item;
        
        // Hitung Diskon
        if(isset($promo_map[$nama_svc])) {
            $r = $promo_map[$nama_svc];
            if($r['jenis_diskon'] == 'persen') {
                $total_diskon += $subtotal_item * ($r['nilai_diskon'] / 100);
            } else {
                $total_diskon += $r['nilai_diskon'] * $qty;
            }
        }

        // Format String Database
        $str = $nama_svc;
        if($qty > 1) {
            $str .= " ({$qty}x)"; 
        }
        if($stylist && $stylist !== '-') {
            $str .= " [" . $stylist . "]";
        }
        $final_services_parts[] = $str;
    }

    $final_string = implode(', ', $final_services_parts);

    // Override Harga (Supervisor)
    if($user_role == 'supervisor' && !empty($_POST['harga_manual'])) {
        $total_bayar = $_POST['harga_manual'];
    } else {
        $total_bayar = $total_asli - $total_diskon;
    }
    if($total_bayar < 0) $total_bayar = 0;

    // Simpan
    $max_nota = $db->query("SELECT MAX(no_nota) FROM transaksi")->fetchColumn();
    $next = $max_nota ? $max_nota + 1 : 1;

    $stmt = $db->prepare("INSERT INTO transaksi (no_nota, tanggal, jam, nama_pelanggan, jenis_layanan, harga, diskon, total_bayar, metode_pembayaran, terapis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$next, date('Y-m-d'), date('H:i'), $nama, $final_string, $total_asli, $total_diskon, $total_bayar, $metode, 'Multi']);
    
    header("Location: index.php?sukses=1"); exit;
}

$data_transaksi = $db->query("SELECT * FROM transaksi ORDER BY id DESC LIMIT 5");
?>

<?php include 'header.php'; ?>

<style>
    /* CSS TAMPILAN RAPI */
    .app-layout { display: flex; min-height: 100vh; background: #f8fafc; font-family: 'Poppins', sans-serif; }
    
    .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; height: 100%; z-index: 99; }
    .brand-area { padding: 25px; text-align: center; border-bottom: 1px solid #f1f5f9; }
    .nav-menu { padding: 20px 15px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin-bottom: 5px; color: #64748b; border-radius: 10px; font-weight: 500; text-decoration: none; transition: 0.2s; }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(236, 72, 153, 0.3); }

    .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }

    .pos-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start; }
    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    /* SEARCH BOX */
    .search-box {
        position: relative; margin-bottom: 15px;
    }
    .search-input {
        width: 100%; padding: 12px 12px 12px 40px; border: 1px solid #cbd5e1; 
        border-radius: 8px; font-size: 14px; background: #f1f5f9; box-sizing: border-box;
    }
    .search-input:focus { outline: none; border-color: var(--primary); background: white; }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 16px; opacity: 0.5; }

    /* SERVICE ROW DENGAN QUANTITY */
    .service-list { display: flex; flex-direction: column; gap: 10px; max-height: 500px; overflow-y: auto; padding-right: 5px; }
    .service-row { 
        display: flex; align-items: center; justify-content: space-between; 
        padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; transition: 0.2s; background: white; 
    }
    .service-row:hover { border-color: var(--primary); background: #fdf4ff; }
    
    .chk-group { display: flex; align-items: center; gap: 12px; flex: 2; cursor: pointer; }
    .chk-input { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
    .svc-info { display: flex; flex-direction: column; }
    .svc-name { font-weight: 600; color: #334155; font-size: 13px; }
    .svc-price { color: var(--primary); font-weight: bold; font-size: 12px; }
    .promo-tag { background: #f59e0b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 9px; margin-left: 5px; }

    /* Input Quantity */
    .qty-input { 
        width: 50px; padding: 5px; text-align: center; border: 1px solid #cbd5e1; 
        border-radius: 6px; margin: 0 10px; font-weight: bold; color: #334155;
    }
    .qty-input:disabled { background: #f1f5f9; color: #94a3b8; }

    /* Dropdown Terapis */
    .stylist-select { 
        width: 130px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; 
        font-size: 11px; background: #f8fafc; color: #475569;
    }

    /* Panel Total */
    .total-panel { background: #1e293b; color: white; padding: 25px; border-radius: 16px; text-align: center; position: sticky; top: 20px; }
    .total-display { font-size: 32px; font-weight: 800; margin: 10px 0; }
    .total-detail { font-size: 11px; opacity: 0.7; border-top: 1px solid #374151; padding-top: 10px; margin-top: 10px; }
    
    .btn-pay { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; margin-top: 15px; }
    .btn-pay:hover { background: #be185d; }
    
    input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; margin-bottom: 15px; }

    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { text-align: left; padding: 12px; background: #f8fafc; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
</style>

<div class="app-layout">
    
    <aside class="sidebar">
        <div class="brand-area">
            <div style="font-size: 32px;">🌸</div>
            <h3 style="color:var(--primary); margin:5px 0;">Kasir Salon</h3>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item active"><span>💻</span> Dashboard</a>
            <a href="laporan.php" class="nav-item"><span>📊</span> Laporan</a>
            <a href="kelola_user.php" class="nav-item"><span>👥</span> Pengguna</a>
            <a href="pengaturan.php" class="nav-item"><span>⚙️</span> Pengaturan</a>
            <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 30px;"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        
        <form method="POST">
            <div class="pos-grid">
                
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0; padding:0; border:none;">💇‍♀️ Pilih Layanan</h3>
                        <small style="color:#64748b;">(Tersedia: <?= count($opsi_layanan) ?> layanan)</small>
                    </div>

                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="inputSearch" class="search-input" placeholder="Ketik nama layanan untuk mencari..." onkeyup="cariLayanan()">
                    </div>

                    <div class="service-list" id="listLayanan">
                        <?php foreach($opsi_layanan as $l): ?>
                        <div class="service-row">
                            
                            <label class="chk-group">
                                <input type="checkbox" class="chk-input trigger-hitung" 
                                       name="layanan[]" 
                                       id="chk_<?= $l['id'] ?>"
                                       value="<?= $l['nama_layanan'] ?>|<?= $l['harga_default'] ?>|<?= $l['id'] ?>"
                                       data-id="<?= $l['id'] ?>"
                                       data-harga="<?= $l['harga_default'] ?>"
                                       data-promo="<?= isset($promo_map[$l['nama_layanan']]) ? 1 : 0 ?>"
                                       data-diskon-tipe="<?= $promo_map[$l['nama_layanan']]['jenis_diskon'] ?? '' ?>"
                                       data-diskon-nilai="<?= $promo_map[$l['nama_layanan']]['nilai_diskon'] ?? 0 ?>"
                                       onclick="toggleItem(<?= $l['id'] ?>)">
                                
                                <div class="svc-info">
                                    <span class="svc-name">
                                        <?= $l['nama_layanan'] ?>
                                        <?php if(isset($promo_map[$l['nama_layanan']])): ?>
                                            <span class="promo-tag">PROMO</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="svc-price">Rp <?= number_format($l['harga_default']) ?></span>
                                </div>
                            </label>

                            <div style="display:flex; align-items:center; gap:5px;">
                                <small style="color:#94a3b8; font-size:10px;">Jml:</small>
                                <input type="number" name="qty_<?= $l['id'] ?>" id="qty_<?= $l['id'] ?>" 
                                       value="1" min="1" class="qty-input" disabled
                                       onchange="hitung()" onkeyup="hitung()">
                            </div>

                            <select name="terapis_<?= $l['id'] ?>" id="sty_<?= $l['id'] ?>" class="stylist-select" disabled>
                                <option value="-">-- Terapis --</option>
                                <?php 
                                $list_terapis = $db->query("SELECT * FROM master_terapis ORDER BY nama_terapis ASC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach($list_terapis as $t): ?>
                                    <option value="<?= $t['nama_terapis'] ?>"><?= $t['nama_terapis'] ?></option>
                                <?php endforeach; ?>
                            </select>

                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <h3>👤 Pelanggan</h3>
                        <label>Nama Pelanggan</label>
                        <input type="text" name="nama" required placeholder="Nama tamu..." autocomplete="off">
                        
                        <label>Metode Bayar</label>
                        <select name="metode">
                            <?php foreach($opsi_metode as $m): ?>
                                <option value="<?= $m['nama_metode'] ?>"><?= $m['nama_metode'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="total-panel">
                        <small>Total Tagihan</small>
                        <div class="total-display" id="txt_total">Rp 0</div>
                        
                        <?php if($user_role == 'supervisor'): ?>
                            <input type="number" name="harga_manual" placeholder="Override Harga (Manual)" 
                                   style="width:100%; text-align:center; padding:8px; margin-bottom:10px; font-size:12px;"
                                   onkeyup="manualUpdate(this.value)">
                        <?php endif; ?>

                        <button type="submit" name="simpan" class="btn-pay">✅ PROSES BAYAR</button>
                        
                        <div class="total-detail" id="txt_detail">Subtotal: 0 | Diskon: 0</div>
                    </div>
                </div>

            </div>
        </form>

        <div class="card">
            <h3>🕒 Riwayat Terakhir</h3>
            <table>
                <thead><tr><th>No Nota</th><th>Pelanggan</th><th>Layanan</th><th>Total</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach($data_transaksi as $r): ?>
                    <tr>
                        <td><b>#<?= str_pad($r['no_nota'], 4, '0', STR_PAD_LEFT) ?></b></td>
                        <td><?= $r['nama_pelanggan'] ?></td>
                        <td><?= mb_strimwidth($r['jenis_layanan'], 0, 40, "...") ?></td>
                        <td style="color:var(--primary); font-weight:bold;">Rp <?= number_format($r['total_bayar']) ?></td>
                        <td><a href="cetak_nota.php?id=<?= $r['id'] ?>" target="_blank" style="color:#0284c7; text-decoration:none;">🖨️</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
    // 1. FITUR SEARCH (PENCARIAN)
    function cariLayanan() {
        // Ambil teks dari kotak pencarian
        let input = document.getElementById('inputSearch').value.toLowerCase();
        // Ambil semua baris layanan
        let rows = document.querySelectorAll('.service-row');

        rows.forEach(row => {
            // Cari elemen nama layanan di dalam baris
            let nameElement = row.querySelector('.svc-name');
            let nameText = nameElement.innerText.toLowerCase();

            // Jika cocok tampilkan, jika tidak sembunyikan
            if (nameText.includes(input)) {
                row.style.display = "flex";
            } else {
                row.style.display = "none";
            }
        });
    }

    // 2. LOGIKA PERHITUNGAN
    const checkboxes = document.querySelectorAll('.trigger-hitung');

    function hitung() {
        let subtotal = 0;
        let diskon = 0;

        checkboxes.forEach(chk => {
            if(chk.checked) {
                // Ambil ID dan Quantity
                let id = chk.getAttribute('data-id');
                let qtyInput = document.getElementById('qty_' + id);
                let qty = parseInt(qtyInput.value) || 1; 
                if(qty < 1) qty = 1;

                let hargaSatuan = parseInt(chk.getAttribute('data-harga'));
                
                let totalItem = hargaSatuan * qty;
                subtotal += totalItem;

                // Hitung Diskon
                if(chk.getAttribute('data-promo') == '1') {
                    let tipe = chk.getAttribute('data-diskon-tipe');
                    let nilai = parseInt(chk.getAttribute('data-diskon-nilai'));
                    
                    if(tipe === 'persen') {
                        diskon += totalItem * (nilai / 100);
                    } else {
                        diskon += nilai * qty;
                    }
                }
            }
        });

        let total = subtotal - diskon;
        if(total < 0) total = 0;

        document.getElementById('txt_total').innerText = "Rp " + total.toLocaleString();
        document.getElementById('txt_detail').innerText = "Subtotal: " + subtotal.toLocaleString() + " | Diskon: " + diskon.toLocaleString();
    }

    function toggleItem(id) {
        let chk = document.getElementById('chk_' + id);
        let sel = document.getElementById('sty_' + id);
        let qty = document.getElementById('qty_' + id);
        
        if(chk.checked) {
            sel.disabled = false;
            qty.disabled = false; 
            chk.closest('.service-row').style.backgroundColor = '#fdf2f8';
            chk.closest('.service-row').style.borderColor = '#ec4899';
        } else {
            sel.disabled = true;
            sel.value = '-';
            qty.disabled = true;
            qty.value = 1; 
            chk.closest('.service-row').style.backgroundColor = 'white';
            chk.closest('.service-row').style.borderColor = '#e2e8f0';
        }
        hitung(); 
    }

    function manualUpdate(val) {
        if(val) {
            document.getElementById('txt_total').innerText = "Rp " + parseInt(val).toLocaleString() + " (Manual)";
        } else {
            hitung();
        }
    }
</script>

</body>
</html>