<?php
// --- 1. LOGIKA PHP UTAMA ---
require_once 'koneksi.php';

// Cek Login
if (!isset($_COOKIE['user_id'])) { header("Location: login.php"); exit; }
$user_nama = $_COOKIE['nama'] ?? 'Staff';
$user_role = $_COOKIE['role'] ?? 'staff';

// Auto Repair DB
try { $db->query("SELECT no_nota FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN no_nota INTEGER DEFAULT 0"); }
try { $db->query("SELECT terapis FROM transaksi LIMIT 1"); } catch (Exception $e) { $db->exec("ALTER TABLE transaksi ADD COLUMN terapis TEXT DEFAULT '-'"); }

// Ambil ID Transaksi Terakhir
$last_trx = $db->query("SELECT id FROM transaksi ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$last_id = $last_trx ? $last_trx['id'] : 0;

// Ambil Data Master
$opsi_layanan = $db->query("SELECT * FROM master_layanan ORDER BY nama_layanan ASC")->fetchAll(PDO::FETCH_ASSOC);
$opsi_metode = $db->query("SELECT * FROM master_metode")->fetchAll(PDO::FETCH_ASSOC);
$list_terapis = $db->query("SELECT * FROM master_terapis ORDER BY nama_terapis ASC")->fetchAll(PDO::FETCH_ASSOC);

// Data Promo
$sekarang = date('Y-m-d H:i:s');
$qp = $db->query("SELECT * FROM daftar_promo WHERE berlaku_sampai > '$sekarang'");
$promo_map = [];
$daftar_promo_aktif = [];

foreach($qp->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $daftar_promo_aktif[] = $p; 
    $targets = explode(',', $p['target_layanan']);
    foreach($targets as $t) { $promo_map[trim($t)] = $p; }
}

// --- PROSES SIMPAN (VERSI BARU: CART SYSTEM) ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $metode = $_POST['metode'];
    
    // UBAHAN UTAMA: Menangkap array 'cart' bukan 'layanan'
    $cart_items = $_POST['cart'] ?? []; 
    $promo_yang_dipilih = $_POST['pilih_promo'] ?? []; 

    if(empty($cart_items)) { 
        echo "<script>alert('⚠️ Keranjang masih kosong! Silakan pilih layanan.'); window.location='index.php';</script>"; exit; 
    }

    $total_asli = 0; 
    $total_diskon = 0;
    $final_services_parts = [];

    // Loop item keranjang (Setiap item adalah baris unik)
    foreach($cart_items as $item) {
        $nama_svc = $item['nama_layanan'];
        $harga_svc= (int)$item['harga'];
        $stylist  = $item['terapis'] ?? '-';
        
        // Qty dianggap 1 per baris agar bisa beda terapis
        $subtotal_item = $harga_svc; 
        $total_asli += $subtotal_item;
        
        // Logika Diskon Promo
        if(isset($promo_map[$nama_svc])) {
            $data_promo = $promo_map[$nama_svc];
            if(in_array($data_promo['id'], $promo_yang_dipilih)) {
                if($data_promo['jenis_diskon'] == 'persen') {
                    $total_diskon += $subtotal_item * ($data_promo['nilai_diskon'] / 100);
                } else {
                    $total_diskon += $data_promo['nilai_diskon'];
                }
            }
        }

        // Format String Nota
        $str = $nama_svc;
        if($stylist && $stylist !== '-') $str .= " [" . $stylist . "]";
        $final_services_parts[] = $str;
    }

    $final_string = implode(', ', $final_services_parts);

    // Hitungan Akhir + Manual Diskon
    $diskon_manual = (int)($_POST['diskon_manual'] ?? 0);
    $total_diskon += $diskon_manual;

    if($user_role == 'supervisor' && !empty($_POST['harga_manual'])) {
        $total_bayar = $_POST['harga_manual'];
    } else {
        $total_bayar = $total_asli - $total_diskon;
    }
    if($total_bayar < 0) $total_bayar = 0;

    // Simpan DB
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
    /* CSS Styling */
    .app-layout { display: flex; min-height: 100vh; background: #f1f5f9; font-family: 'Poppins', sans-serif; color: #334155; }
    .sidebar { width: 260px; background: white; border-right: 1px solid #e2e8f0; position: fixed; height: 100%; z-index: 10; }
    .brand-area { padding: 25px; text-align: center; border-bottom: 1px solid #f1f5f9; }
    .nav-menu { padding: 20px 15px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin-bottom: 5px; color: #64748b; border-radius: 10px; font-weight: 500; text-decoration: none; transition: 0.2s; }
    .nav-item:hover { background: #fdf2f8; color: var(--primary); }
    .nav-item.active { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(236, 72, 153, 0.3); }
    .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
    .pos-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 25px; align-items: start; }
    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #f1f5f9; }
    h3 { color: #1e293b; margin-top: 0; font-weight: 600; }
    
    /* MODAL */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 100; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
    .modal-box { background: white; width: 900px; max-width: 95%; height: 85vh; border-radius: 20px; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: zoomIn 0.2s ease-out; }
    @keyframes zoomIn { from {transform: scale(0.95); opacity: 0;} to {transform: scale(1); opacity: 1;} }
    .modal-header { padding: 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-body { flex: 1; overflow-y: auto; padding: 25px; background: #f8fafc; }
    .modal-footer { padding: 20px; border-top: 1px solid #e2e8f0; text-align: right; background: white; border-radius: 0 0 20px 20px; }
    
    .search-modal { width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; box-sizing: border-box; transition: 0.2s; }
    .search-modal:focus { border-color: var(--primary); outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(236, 72, 153, 0.1); }
    .svc-grid-modal { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
    .svc-item { background: white; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    .svc-item:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    .svc-name { font-weight: 600; font-size: 15px; color: #1e293b; margin-bottom: 4px; }
    .svc-price { color: var(--primary); font-weight: 700; font-size: 14px; }
    
    .sty-select { padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; width: 130px; background: white; }
    
    .preview-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #e2e8f0; background: #fff; margin-bottom: 8px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
    
    .btn-open-modal { width: 100%; padding: 18px; background: white; border: 2px dashed var(--primary); color: var(--primary); border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
    .btn-open-modal:hover { background: #fdf2f8; transform: translateY(-1px); }
    .btn-close-modal { background: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 14px; }
    .action-buttons { display: flex; gap: 12px; margin-top: 25px; }
    .btn-pay { flex: 2; padding: 18px; background: linear-gradient(135deg, #ec4899, #be185d); color: white; border: none; border-radius: 14px; font-weight: 800; font-size: 16px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(236, 72, 153, 0.3); transition: 0.2s; }
    .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(236, 72, 153, 0.4); }
    .btn-print { flex: 1; padding: 18px; background: #3b82f6; color: white; border: none; border-radius: 14px; font-weight: 700; font-size: 16px; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3); }
    .btn-print:hover { background: #2563eb; transform: translateY(-2px); }
    .btn-print.disabled { background: #cbd5e1; cursor: not-allowed; pointer-events: none; box-shadow: none; }
    .form-input { width: 100%; padding: 14px; border: 1px solid #cbd5e1; border-radius: 10px; box-sizing: border-box; margin-bottom: 15px; font-size: 14px; transition: 0.2s; }
    .form-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1); }
    .total-panel { background: #0f172a; color: white; padding: 30px; border-radius: 20px; text-align: center; position: sticky; top: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    .total-display { font-size: 42px; font-weight: 800; margin: 15px 0; letter-spacing: -1px; }
    .promo-panel { background: #fffbeb; border: 1px solid #fcd34d; padding: 18px; border-radius: 14px; margin-bottom: 25px; }
    .promo-header { display: flex; align-items: center; gap: 8px; color: #b45309; font-weight: 700; margin-bottom: 12px; font-size: 14px; letter-spacing: 0.5px; }
    .promo-grid { display: flex; flex-direction: column; gap: 10px; } 
    
    .promo-item-row {
        background: white; border: 1px solid #fde047; border-radius: 10px; padding: 10px 14px;
        display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .promo-chk { width: 20px; height: 20px; accent-color: #d97706; cursor: pointer; }
    
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
    th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; border-bottom: 2px solid #e2e8f0; font-weight: 600; }
    td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
</style>

<div class="app-layout">
    
    <aside class="sidebar">
        <div class="brand-area">
            <div style="font-size: 36px; margin-bottom: 5px;">🌸</div>
            <h3 style="color:var(--primary); margin:0; font-size: 20px;">Kasir Salon</h3>
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
                
                <div>
                    <?php if(!empty($daftar_promo_aktif)): ?>
                    <div class="promo-panel">
                        <div class="promo-header">
                            <span>🎉</span> PILIH PROMO YANG BERLAKU
                        </div>
                        <div class="promo-grid">
                            <?php foreach($daftar_promo_aktif as $p): ?>
                            <label class="promo-item-row">
                                <input type="checkbox" name="pilih_promo[]" 
                                       value="<?= $p['id'] ?>" 
                                       class="promo-chk" 
                                       checked 
                                       onclick="reCalc()">
                                
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-weight:700; color:#d97706;"><?= $p['nama_promo'] ?></span>
                                    <span style="font-size:12px; color:#b45309;">
                                        Diskon: <?= $p['jenis_diskon'] == 'persen' ? $p['nilai_diskon'].'%' : 'Rp '.number_format($p['nilai_diskon']) ?>
                                        (Utk: <?= mb_strimwidth($p['target_layanan'], 0, 20, "...") ?>)
                                    </span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 style="margin:0;">🛒 Layanan Terpilih</h3>
                            <button type="button" class="btn-open-modal" onclick="toggleModal(true)" style="width:auto; padding:8px 15px; font-size:12px;">
                                + Tambah Layanan
                            </button>
                        </div>

                        <div id="cartContainer" style="min-height: 100px; display:flex; flex-direction:column; gap:10px;">
                            <div style="text-align:center; color:#94a3b8; padding:20px;" id="emptyCartMsg">
                                Keranjang masih kosong.<br>Klik tombol tambah di atas.
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <h3>👤 Data Pelanggan</h3>
                        <label style="font-size:12px; font-weight:bold; color:#64748b; margin-bottom:5px; display:block;">Nama Pelanggan</label>
                        <input type="text" name="nama" class="form-input" required placeholder="Masukkan nama tamu..." autocomplete="off">
                        
                        <label style="font-size:12px; font-weight:bold; color:#64748b; margin-bottom:5px; display:block;">Metode Pembayaran</label>
                        <select name="metode" class="form-input">
                            <?php foreach($opsi_metode as $m): ?>
                                <option value="<?= $m['nama_metode'] ?>"><?= $m['nama_metode'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="total-panel">
                        <label style="font-size:12px; font-weight:bold; color:white; margin-bottom:5px; display:block; text-align:left; opacity:0.8;">
                            🎟️ Potongan Manual (Rp)
                        </label>
                        <input type="number" id="diskon_manual" name="diskon_manual" 
                            class="form-input" 
                            placeholder="0" 
                            style="color:#334155; font-weight:bold; text-align:right;"
                            onkeyup="reCalc()" onchange="reCalc()">
                        <small style="text-transform:uppercase; letter-spacing:1px; opacity:0.8;">Total Tagihan</small>
                        <div class="total-display" id="txt_total">Rp 0</div>
                        <div style="font-size:13px; opacity:0.7; border-top:1px solid #334155; padding-top:15px; margin-top:5px;" id="txt_detail">
                            Subtotal: 0 | Diskon: 0
                        </div>

                        <?php if($user_role == 'supervisor'): ?>
                            <input type="number" name="harga_manual" placeholder="Override Harga (Manual)" 
                                   style="width:100%; text-align:center; padding:12px; margin-top:20px; border-radius:10px; border:none; color:black; font-weight:bold;"
                                   onkeyup="manualUpdate(this.value)">
                        <?php endif; ?>

                        <div class="action-buttons">
                            <a href="cetak_nota.php?id=<?= $last_id ?>" target="_blank" 
                               class="btn-print <?= ($last_id == 0) ? 'disabled' : '' ?>">
                                🖨️ Cetak
                            </a>

                            <button type="submit" name="simpan" class="btn-pay">
                                ✅ Proses Bayar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="serviceModal">
                <div class="modal-box">
                    <div class="modal-header">
                        <div>
                            <h2 style="margin:0; color:#1e293b;">Pilih Layanan</h2>
                            <small style="color:#64748b;">Klik layanan untuk menambah ke keranjang</small>
                        </div>
                        <input type="text" id="searchBox" class="search-modal" placeholder="Ketik nama layanan..." onkeyup="filterServices()" style="width: 300px;">
                    </div>
                    
                    <div class="modal-body">
                        <div class="svc-grid-modal" id="modalGrid">
                            <?php foreach($opsi_layanan as $l): ?>
                            <div class="svc-item" onclick="addToCart(
                                    <?= $l['id'] ?>, 
                                    '<?= addslashes($l['nama_layanan']) ?>', 
                                    <?= $l['harga_default'] ?>, 
                                    <?= isset($promo_map[$l['nama_layanan']]) ? $promo_map[$l['nama_layanan']]['id'] : 0 ?>,
                                    '<?= $promo_map[$l['nama_layanan']]['jenis_diskon'] ?? '' ?>',
                                    <?= $promo_map[$l['nama_layanan']]['nilai_diskon'] ?? 0 ?>
                                )">
                                <div style="flex:1">
                                    <div class="svc-name"><?= $l['nama_layanan'] ?></div>
                                    <div class="svc-price">Rp <?= number_format($l['harga_default']) ?></div>
                                </div>
                                <div style="background:var(--primary); color:white; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:bold;">+</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-close-modal" onclick="toggleModal(false)">TUTUP</button>
                    </div>
                </div>
            </div>

        </form>

        <div class="card" style="margin-top:20px;">
            <h3>🕒 Riwayat Transaksi Terakhir</h3>
            <table>
                <thead><tr><th>No Nota</th><th>Waktu</th><th>Pelanggan</th><th>Layanan</th><th>Total</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach($data_transaksi as $r): ?>
                    <tr>
                        <td><b>#<?= str_pad($r['no_nota'], 4, '0', STR_PAD_LEFT) ?></b></td>
                        <td><?= date('d/m H:i', strtotime($r['tanggal'].' '.$r['jam'])) ?></td>
                        <td><?= $r['nama_pelanggan'] ?></td>
                        <td><?= mb_strimwidth($r['jenis_layanan'], 0, 45, "...") ?></td>
                        <td style="color:var(--primary); font-weight:bold;">Rp <?= number_format($r['total_bayar']) ?></td>
                        <td><a href="cetak_nota.php?id=<?= $r['id'] ?>" target="_blank" style="text-decoration:none; font-size:18px;">🖨️</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
    // Data Terapis dari PHP ke JS
    const terapisData = [
        <?php foreach($list_terapis as $t): ?>
        {nama: "<?= $t['nama_terapis'] ?>"},
        <?php endforeach; ?>
    ];

    function toggleModal(show) {
        document.getElementById('serviceModal').style.display = show ? 'flex' : 'none';
    }

    function filterServices() {
        let input = document.getElementById('searchBox').value.toLowerCase();
        document.querySelectorAll('.svc-item').forEach(item => {
            let text = item.innerText.toLowerCase();
            item.style.display = text.includes(input) ? 'flex' : 'none';
        });
    }

    // --- FUNGSI UTAMA: MENAMBAH ITEM KE KERANJANG ---
    function addToCart(id, nama, harga, promoId, diskonTipe, diskonNilai) {
        document.getElementById('emptyCartMsg').style.display = 'none';
        const container = document.getElementById('cartContainer');
        
        // Buat ID unik (Random) agar bisa input layanan sama berkali-kali
        let rowId = Date.now() + Math.random().toString(36).substr(2, 5);

        // Siapkan Dropdown Terapis
        let terapisOptions = `<option value="-">Pilih Terapis</option>`;
        terapisData.forEach(t => {
            terapisOptions += `<option value="${t.nama}">${t.nama}</option>`;
        });

        // Template HTML Baris Baru
        let itemHTML = `
            <div class="preview-item" id="row_${rowId}" style="flex-wrap:wrap; gap:10px;">
                <input type="hidden" name="cart[${rowId}][id_layanan]" value="${id}">
                <input type="hidden" name="cart[${rowId}][nama_layanan]" value="${nama}">
                <input type="hidden" name="cart[${rowId}][harga]" value="${harga}">
                
                <input type="hidden" class="promo-trigger" 
                       data-harga="${harga}" 
                       data-promo-id="${promoId}" 
                       data-diskon-tipe="${diskonTipe}" 
                       data-diskon-nilai="${diskonNilai}">

                <div style="flex: 2; min-width: 150px;">
                    <b>${nama}</b><br>
                    <span style="color:var(--primary); font-size:12px;">Rp ${parseInt(harga).toLocaleString()}</span>
                </div>

                <div style="flex: 1; min-width: 120px;">
                    <select name="cart[${rowId}][terapis]" class="sty-select" style="width:100%;">
                        ${terapisOptions}
                    </select>
                </div>

                <div style="width: 30px; text-align:right;">
                    <button type="button" onclick="removeRow('${rowId}')" style="background:#ef4444; color:white; border:none; border-radius:5px; padding:5px 10px; cursor:pointer;">X</button>
                </div>
            </div>
        `;

        // Masukkan ke layar
        container.insertAdjacentHTML('beforeend', itemHTML);
        toggleModal(false); // Tutup modal otomatis setelah pilih
        reCalc(); // Hitung ulang total
    }

    function removeRow(rowId) {
        document.getElementById('row_' + rowId).remove();
        // Jika kosong, tampilkan pesan kosong lagi
        if(document.getElementById('cartContainer').children.length <= 1) { 
            document.getElementById('emptyCartMsg').style.display = 'block';
        }
        reCalc();
    }

    function reCalc() {
        // 1. Cek Promo apa saja yang dicentang di panel atas
        let selectedPromos = [];
        document.querySelectorAll('.promo-chk:checked').forEach(chk => {
            selectedPromos.push(parseInt(chk.value)); 
        });

        let subtotal = 0;
        let diskonOtomatis = 0;

        // 2. Loop semua item yang ada di keranjang sekarang
        document.querySelectorAll('.promo-trigger').forEach(el => {
            let harga = parseInt(el.getAttribute('data-harga'));
            subtotal += harga;

            // Hitung Diskon jika promo cocok
            let pId = parseInt(el.getAttribute('data-promo-id'));
            let pTipe = el.getAttribute('data-diskon-tipe');
            let pNilai = parseInt(el.getAttribute('data-diskon-nilai'));

            if (pId !== 0 && selectedPromos.includes(pId)) {
                if (pTipe === 'persen') {
                    diskonOtomatis += harga * (pNilai / 100);
                } else {
                    diskonOtomatis += pNilai;
                }
            }
        });

        // 3. Tambahkan Diskon Manual
        let manualInput = document.getElementById('diskon_manual');
        let manualDisc = manualInput ? (parseInt(manualInput.value) || 0) : 0;
        
        let totalDiskon = diskonOtomatis + manualDisc;
        let total = subtotal - totalDiskon;
        if (total < 0) total = 0;

        // 4. Update Teks Total
        document.getElementById('txt_total').innerText = "Rp " + total.toLocaleString();
        document.getElementById('txt_detail').innerText = `Subtotal: ${subtotal.toLocaleString()} | Diskon: ${totalDiskon.toLocaleString()}`;
    }
    
    // Manual override harga (hanya supervisor)
    function manualUpdate(val) {
        if(val) document.getElementById('txt_total').innerText = "Rp " + parseInt(val).toLocaleString() + " (Manual)";
        else reCalc();
    }
</script>

</body>
</html>