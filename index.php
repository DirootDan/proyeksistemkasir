<?php
// --- 1. LOGIKA PHP (VERSI STABIL / CHECKBOX) ---
require_once 'koneksi.php';

// Cek Login
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }
$user_nama = $_COOKIE['nama'] ?? 'Staff';
$user_role = $_COOKIE['role'] ?? 'staff';

// Auto Repair Database
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

// Proses Simpan Transaksi
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
        
        // Ambil Terapis dari dropdown
        $input_sty = "terapis_" . $id_svc;
        $stylist = $_POST[$input_sty] ?? '-';

        $total_asli += $harga_svc;
        
        // Hitung Diskon
        if(isset($promo_map[$nama_svc])) {
            $r = $promo_map[$nama_svc];
            $total_diskon += ($r['jenis_diskon'] == 'persen') ? $harga_svc * ($r['nilai_diskon'] / 100) : $r['nilai_diskon'];
        }

        $str = $nama_svc;
        if($stylist && $stylist !== '-') $str .= " [" . $stylist . "]";
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
    /* --- PERBAIKAN TAMPILAN (CSS) --- */
    
    /* 1. Layout Utama */
    .app-layout { display: flex; min-height: 100vh; background: #f8fafc; font-family: 'Poppins', sans-serif; }
    
    /* 2. Sidebar (Menu Kiri) */
    .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; height: 100%; z-index: 99; }
    .brand-area { padding: 25px; text-align: center; border-bottom: 1px solid #f1f5f9; }
    .nav-menu { padding: 20px 15px; }
    .nav-item { 
        display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin-bottom: 5px; 
        color: #64748b; border-radius: 10px; font-weight: 500; text-decoration: none; transition: 0.2s; 
    }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(236, 72, 153, 0.3); }

    /* 3. Area Konten Utama */
    .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }

    /* 4. Grid Formulir (Kiri & Kanan) */
    .pos-grid {
        display: grid;
        grid-template-columns: 2fr 1fr; /* Kolom kiri 2x lebih lebar */
        gap: 25px;
        align-items: start;
    }

    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
    h3 { margin-top: 0; color: #1e293b; font-size: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }

    /* 5. LIST LAYANAN (DIPERBAIKI DISINI) */
    .service-list { display: flex; flex-direction: column; gap: 12px; }
    
    .service-row { 
        display: flex; 
        align-items: center; /* Vertikal rata tengah */
        justify-content: space-between; /* Kiri kanan mentok */
        padding: 15px; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        transition: 0.2s; 
        background: white;
    }
    .service-row:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(236, 72, 153, 0.1); }
    
    /* Bagian Kiri (Checkbox + Teks) */
    .chk-label-group {
        display: flex; 
        align-items: center; 
        gap: 15px; 
        cursor: pointer;
        flex: 1; /* Ambil sisa ruang */
    }
    
    .chk-input { 
        width: 20px; 
        height: 20px; 
        accent-color: var(--primary); 
        cursor: pointer; 
    }
    
    .svc-text { display: flex; flex-direction: column; }
    .svc-name { font-weight: 600; color: #334155; font-size: 14px; }
    .svc-price { color: var(--primary); font-weight: bold; font-size: 13px; margin-top: 2px; }
    .promo-tag { background: #f59e0b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px; }

    /* Bagian Kanan (Dropdown) */
    .stylist-select { 
        padding: 8px 12px; 
        border: 1px solid #cbd5e1; 
        border-radius: 8px; 
        font-size: 12px; 
        background: #f8fafc; 
        outline: none; 
        width: 160px; /* Lebar tetap biar rapi */
        cursor: pointer;
        color: #475569;
    }
    .stylist-select:focus { border-color: var(--primary); background: white; }

    /* 6. Panel Kanan (Customer & Total) */
    label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; color: #64748b; }
    .form-input { 
        width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; 
        box-sizing: border-box; font-family: inherit; font-size: 14px; background: #f8fafc;
    }
    .form-input:focus { outline: none; border-color: var(--primary); background: white; }

    .total-panel { 
        background: #1f2937; color: white; padding: 30px 25px; border-radius: 16px; text-align: center;
        position: sticky; top: 20px; 
    }
    .total-title { font-size: 13px; opacity: 0.8; margin-bottom: 5px; }
    .total-display { font-size: 38px; font-weight: 800; margin: 5px 0 20px 0; letter-spacing: -1px; }
    .total-detail { font-size: 12px; opacity: 0.6; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); }
    
    .btn-pay { 
        width: 100%; padding: 15px; background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); 
        color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 16px; 
        cursor: pointer; box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4); transition: 0.2s;
    }
    .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(236, 72, 153, 0.6); }

    /* 7. Tabel Riwayat */
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
    td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .btn-print { background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 11px; }
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
                    <h3>💇‍♀️ Pilih Layanan & Terapis</h3>
                    
                    <div class="service-list">
                        <?php foreach($opsi_layanan as $l): ?>
                        <div class="service-row">
                            
                            <label class="chk-label-group">
                                <input type="checkbox" class="chk-input trigger-hitung" 
                                       name="layanan[]" 
                                       value="<?= $l['nama_layanan'] ?>|<?= $l['harga_default'] ?>|<?= $l['id'] ?>"
                                       data-harga="<?= $l['harga_default'] ?>"
                                       data-promo="<?= isset($promo_map[$l['nama_layanan']]) ? 1 : 0 ?>"
                                       data-diskon-tipe="<?= $promo_map[$l['nama_layanan']]['jenis_diskon'] ?? '' ?>"
                                       data-diskon-nilai="<?= $promo_map[$l['nama_layanan']]['nilai_diskon'] ?? 0 ?>"
                                       onclick="toggleSelect(<?= $l['id'] ?>)">
                                
                                <div class="svc-text">
                                    <span class="svc-name">
                                        <?= $l['nama_layanan'] ?>
                                        <?php if(isset($promo_map[$l['nama_layanan']])): ?>
                                            <span class="promo-tag">PROMO</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="svc-price">Rp <?= number_format($l['harga_default']) ?></span>
                                </div>
                            </label>

                            <select name="terapis_<?= $l['id'] ?>" id="sty_<?= $l['id'] ?>" class="stylist-select" disabled>
                                <option value="-">-- Pilih Terapis --</option>
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
                        <h3>👤 Data Pelanggan</h3>
                        <div style="margin-bottom:15px;">
                            <label>Nama Pelanggan</label>
                            <input type="text" name="nama" class="form-input" required placeholder="Nama tamu..." autocomplete="off">
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label>Metode Pembayaran</label>
                            <select name="metode" class="form-input">
                                <?php foreach($opsi_metode as $m): ?>
                                    <option value="<?= $m['nama_metode'] ?>"><?= $m['nama_metode'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="total-panel">
                        <div class="total-title">Total Yang Harus Dibayar</div>
                        <div class="total-display" id="txt_total">Rp 0</div>
                        
                        <?php if($user_role == 'supervisor'): ?>
                            <input type="number" name="harga_manual" placeholder="Override Harga (Manual)" 
                                   style="width:100%; padding:10px; margin-bottom:15px; border-radius:8px; border:none; text-align:center; color:black;"
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
            <table cellspacing="0">
                <thead>
                    <tr>
                        <th>No Nota</th>
                        <th>Pelanggan</th>
                        <th>Layanan</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($data_transaksi as $r): ?>
                    <tr>
                        <td><span style="background:#f1f5f9; padding:5px 8px; border-radius:6px; font-weight:bold;">#<?= str_pad($r['no_nota'], 4, '0', STR_PAD_LEFT) ?></span></td>
                        <td>
                            <b><?= $r['nama_pelanggan'] ?></b><br>
                            <small style="color:#94a3b8"><?= $r['jam'] ?></small>
                        </td>
                        <td style="font-size:12px; color:#64748b;"><?= mb_strimwidth($r['jenis_layanan'], 0, 45, "...") ?></td>
                        <td style="color:var(--primary); font-weight:bold;">Rp <?= number_format($r['total_bayar']) ?></td>
                        <td><a href="cetak_nota.php?id=<?= $r['id'] ?>" target="_blank" class="btn-print">🖨️ Cetak</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
    // LOGIKA PERHITUNGAN (TETAP SAMA YANG STABIL)
    const checkboxes = document.querySelectorAll('.trigger-hitung');

    checkboxes.forEach(chk => {
        chk.addEventListener('change', hitung);
    });

    function hitung() {
        let subtotal = 0;
        let diskon = 0;

        checkboxes.forEach(chk => {
            if(chk.checked) {
                let harga = parseInt(chk.getAttribute('data-harga'));
                subtotal += harga;

                if(chk.getAttribute('data-promo') == '1') {
                    let tipe = chk.getAttribute('data-diskon-tipe');
                    let nilai = parseInt(chk.getAttribute('data-diskon-nilai'));
                    
                    if(tipe === 'persen') {
                        diskon += harga * (nilai / 100);
                    } else {
                        diskon += nilai;
                    }
                }
            }
        });

        let total = subtotal - diskon;
        if(total < 0) total = 0;

        document.getElementById('txt_total').innerText = "Rp " + total.toLocaleString();
        document.getElementById('txt_detail').innerText = "Subtotal: " + subtotal.toLocaleString() + " | Diskon: " + diskon.toLocaleString();
    }

    function toggleSelect(id) {
        let chk = document.querySelector(`input[value*='|${id}']`);
        let sel = document.getElementById('sty_' + id);
        
        if(chk.checked) {
            sel.disabled = false;
            // Visual feedback baris aktif
            chk.closest('.service-row').style.borderColor = '#ec4899';
            chk.closest('.service-row').style.backgroundColor = '#fdf2f8';
        } else {
            sel.disabled = true;
            sel.value = '-';
            // Visual feedback reset
            chk.closest('.service-row').style.borderColor = '#e2e8f0';
            chk.closest('.service-row').style.backgroundColor = 'white';
        }
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