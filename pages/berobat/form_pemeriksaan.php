<?php
// File: form_pemeriksaan.php
session_start();
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$error = '';
$data_karyawan = null;
$id_card_cari = '';
$petugas = "Mantri Adit"; // Ganti dengan data session user yang login

// Helper untuk mengisi ulang form setelah POST gagal
function postValue($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default);
}

// --- LOGIKA UTAMA: PENYIMPANAN TRANSAKSIONAL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pemeriksaan'])) {
    
    // 1. Ambil dan Bersihkan Data Pemeriksaan
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    
    $q_check = "SELECT k.nama FROM karyawan k WHERE k.id_card = '$id_card'";
    if (mysqli_num_rows(mysqli_query($koneksi, $q_check)) == 0) {
        $error = "Gagal menyimpan. ID Card tidak valid atau tidak ditemukan.";
    } else {
        // Lanjutkan pengambilan data
        $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
        $diagnosis = mysqli_real_escape_string($koneksi, $_POST['diagnosis']);
        $tekanan_darah = mysqli_real_escape_string($koneksi, $_POST['tekanan_darah']);
        $suhu_tubuh = mysqli_real_escape_string($koneksi, $_POST['suhu_tubuh']);
        $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
        $rujukan = mysqli_real_escape_string($koneksi, $_POST['rujukan']);
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        // $petugas sudah didefinisikan di awal

        // Data Resep Obat
        $obat_ids = $_POST['obat_id'] ?? [];
        $jumlahs = $_POST['jumlah'] ?? [];
        $dosiss = $_POST['dosis'] ?? [];
        $aturan_pakais = $_POST['aturan_pakai'] ?? [];

        // Mulai Transaksi
        mysqli_begin_transaction($koneksi);

        try {
            // A. INSERT KE TABEL BEROBAT
            $query_berobat = "INSERT INTO berobat (
                id_card, tanggal_berobat, keluhan, diagnosis, tekanan_darah, 
                suhu_tubuh, tindakan, rujukan, catatan, petugas
            ) VALUES (
                '$id_card', NOW(), '$keluhan', '$diagnosis', '$tekanan_darah', 
                '$suhu_tubuh', '$tindakan', '$rujukan', '$catatan', '$petugas'
            )";

            if (!mysqli_query($koneksi, $query_berobat)) {
                throw new Exception("Gagal menyimpan data pemeriksaan: " . mysqli_error($koneksi));
            }

            $berobat_id = mysqli_insert_id($koneksi);
            
            // B. PROSES RESEP DAN PENGURANGAN STOK
            if (!empty($obat_ids)) {
                foreach ($obat_ids as $index => $obat_id) {
                    $jumlah = (int)$jumlahs[$index];
                    $dosis = mysqli_real_escape_string($koneksi, $dosiss[$index]);
                    $aturan_pakai = mysqli_real_escape_string($koneksi, $aturan_pakais[$index]);
                    
                    if ($obat_id && $jumlah > 0) {
                        
                        // 1. Cek Stok Saat Ini (Untuk $stok_sebelum)
                        $q_check_stok = "SELECT stok_tersedia, nama_obat FROM obat WHERE id = '$obat_id'";
                        $r_check_stok = mysqli_query($koneksi, $q_check_stok);
                        $data_obat = mysqli_fetch_assoc($r_check_stok);
                        
                        if (!$data_obat) {
                            throw new Exception("Obat dengan ID $obat_id tidak ditemukan.");
                        }

                        $stok_saat_ini = $data_obat['stok_tersedia'];
                        
                        if ($stok_saat_ini < $jumlah) {
                            throw new Exception("Stok obat **" . $data_obat['nama_obat'] . "** tidak cukup! Tersedia: " . $stok_saat_ini . ", Diminta: " . $jumlah);
                        }
                        
                        $stok_sesudah = $stok_saat_ini - $jumlah;

                        // 2. INSERT KE TABEL RESEP_OBAT
                        $query_resep = "INSERT INTO resep_obat (
                            berobat_id, obat_id, jumlah, dosis, aturan_pakai, created_at
                        ) VALUES (
                            '$berobat_id', '$obat_id', '$jumlah', '$dosis', '$aturan_pakai', NOW()
                        )";
                        
                        if (!mysqli_query($koneksi, $query_resep)) {
                            throw new Exception("Gagal menyimpan resep obat: " . mysqli_error($koneksi));
                        }
                        $resep_id = mysqli_insert_id($koneksi); // *** Dapatkan ID Resep ***

                        // 3. UPDATE (PENGURANGAN) STOK OBAT
                        $query_update_stok = "UPDATE obat SET stok_tersedia = '$stok_sesudah', updated_at = NOW() WHERE id = '$obat_id'";
                        
                        if (!mysqli_query($koneksi, $query_update_stok)) {
                            throw new Exception("Gagal mengurangi stok obat: " . mysqli_error($koneksi));
                        }

                        // 4. INSERT KE TABEL TRANSAKSI_OBAT (PENCATATAN KELUAR)
                        $petugas_transaksi = mysqli_real_escape_string($koneksi, $petugas);
                        $keterangan_keluar = "Pengeluaran resep (Berobat ID: $berobat_id) untuk pasien: " . $id_card; 
                        
                        $query_transaksi = "INSERT INTO transaksi_obat (
                            obat_id, jenis_transaksi, jumlah, stok_sebelum, stok_sesudah, 
                            resep_obat_id, tanggal_transaksi, keterangan, petugas
                        ) VALUES (
                            '$obat_id', 'KELUAR', '$jumlah', '$stok_saat_ini', '$stok_sesudah', 
                            '$resep_id', NOW(), '$keterangan_keluar', '$petugas_transaksi'
                        )";
                        
                        if (!mysqli_query($koneksi, $query_transaksi)) {
                            throw new Exception("Gagal menyimpan transaksi obat keluar: " . mysqli_error($koneksi));
                        }
                    }
                }
            }

            // Commit transaksi
            mysqli_commit($koneksi);
            header("Location: riwayat_berobat.php?status=success_add");
            exit();

        } catch (Exception $e) {
            // Rollback jika error
            mysqli_rollback($koneksi);
            $error = $e->getMessage();
        }
    }
}

// --- LOGIKA PENCARIAN KARYAWAN DARI SELECT2 / URL (GET) / RELOAD POST GAGAL ---
// Prioritas: 1. POST (jika simpan gagal), 2. GET (jika baru dipilih via URL)
if (isset($_POST['id_card']) || isset($_GET['id_card_selected'])) {
    // Gunakan ID dari POST (jika gagal simpan) atau dari GET (jika baru dipilih)
    $id_card_cari = mysqli_real_escape_string($koneksi, $_POST['id_card'] ?? $_GET['id_card_selected']);
    
    if (!empty($id_card_cari)) {
        $q_karyawan = "SELECT k.id_card, k.nama, k.jabatan, k.departemen, rm.penyakit_terdahulu, rm.alergi, rm.golongan_darah 
                       FROM karyawan k
                       LEFT JOIN riwayat_medis rm ON k.id_card = rm.id_card
                       WHERE k.id_card = '$id_card_cari'";
        $r_karyawan = mysqli_query($koneksi, $q_karyawan);
        $data_karyawan = mysqli_fetch_assoc($r_karyawan);
        
        if (!$data_karyawan) {
            $error = "Data pasien dengan ID Card **" . htmlspecialchars($id_card_cari) . "** tidak ditemukan.";
        } else {
             $id_card_cari = $data_karyawan['id_card']; // Pastikan $id_card_cari terisi
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Input Pemeriksaan Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <script src="../../assets/static/js/initTheme.js"></script>
</head>

<body>
    <div id="app">
        <div id="Main"></div>
        <div id="app">
        <div id="sidebar">
            <div class="sidebar-wrapper active">
    <div class="sidebar-header position-relative">
        <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
                <a href="../../"><img src="../../assets/images/logo.PNG" alt="Logo" srcset=""></a>
            </div>
            <div class="theme-toggle d-flex gap-2  align-items-center mt-2">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true"
                    role="img" class="iconify iconify--system-uicons" width="20" height="20"
                    preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                    <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path
                            d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4zM4.136 4.136L5.55 5.55m9.9 9.9l1.414 1.414M1.5 10.5h2m14 0h2M4.135 16.863L5.55 15.45m9.899-9.9l1.414-1.415M10.5 19.5v-2m0-14v-2"
                            opacity=".3"></path>
                        <g transform="translate(-210 -1)">
                            <path d="M220.5 2.5v2m6.5.5l-1.5 1.5"></path>
                            <circle cx="220.5" cy="11.5" r="4"></circle>
                            <path d="m214 5l1.5 1.5m5 14v-2m6.5-.5l-1.5-1.5M214 18l1.5-1.5m-4-5h2m14 0h2"></path>
                        </g>
                    </g>
                </svg>
                <div class="form-check form-switch fs-6">
                    <input class="form-check-input  me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
                    <label class="form-check-label"></label>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true"
                    role="img" class="iconify iconify--mdi" width="20" height="20" preserveAspectRatio="xMidYMid meet"
                    viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z">
                    </path>
                </svg>
            </div>
            <div class="sidebar-toggler  x">
                <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
            </div>
        </div>
    </div>
    <div class="sidebar-menu">
        <ul class="menu">
            <li class="sidebar-title">Menu</li>
            
            <li
                class="sidebar-item active ">
                <a href="../../" class='sidebar-link'>
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
                

            </li>
            
            <li
                class="sidebar-item">
                <a href="pages/karyawan/karyawan.php" class='sidebar-link'>
                    <i class="bi bi-stack"></i>
                    <span>Data Karyawan</span>
                </a>
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-collection-fill"></i>
                    <span>Pelayanan Kesehatan</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="pages/berobat/riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                        
                    </li>
                    
                    <!-- <li class="submenu-item  ">
                        <a href="pages/berobat/riwayat_berobat.php" class="submenu-link">Riwayat Medis</a>
                        
                    </li>
                     -->
                    <li class="submenu-item  ">
                        <a href="pages/karyawan/riwayat_kecelakaan.php" class="submenu-link">Kecelakaan Kerja</a>
                        
                    </li>
                </ul>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Manajemen Obat</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="pages/obat/master_obat.php" class="submenu-link">Data Obat</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="pages/obat/laporan_transaksi_obat.php" class="submenu-link">Laporan Transaksi Obat</a>                     
                    </li>              
                </ul>
            </li>
            
            <li
                class="sidebar-item  ">
                <a href="form-layout.html" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-person-circle"></i>
                    <span>Account</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="account-profile.html" class="submenu-link">Profile</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="account-security.html" class="submenu-link">Security</a>
                        
                    </li>
                    
                </ul>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Authentication</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="auth-login.html" class="submenu-link">Login</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="auth-register.html" class="submenu-link">Register</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="auth-forgot-password.html" class="submenu-link">Forgot Password</a>
                        
                    </li>
                    
                </ul>
                

            </li>
            
            
        </ul>
    </div>
</div>
        </div>

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Input Pemeriksaan Baru</h3>
                <p class="text-subtitle text-muted">Formulir untuk mencatat pemeriksaan medis pasien (karyawan).</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Form Pemeriksaan Pasien</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="form_pemeriksaan.php" method="POST">
                            
                            <div class="row mb-4 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-search me-1"></i> Cari & Pilih Pasien</label>
                                    <select class="form-control" id="select_id_card" required>
                                        <?php if ($data_karyawan): ?>
                                        <option value="<?= htmlspecialchars($data_karyawan['id_card']); ?>" selected>
                                            <?= htmlspecialchars($data_karyawan['nama'] . ' (' . $data_karyawan['id_card'] . ' - ' . $data_karyawan['jabatan'] . ')'); ?>
                                        </option>
                                        <?php endif; ?>
                                    </select>
                                    <input type="hidden" name="id_card" id="hidden_id_card" value="<?= htmlspecialchars($data_karyawan['id_card'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nama Pasien</label>
                                    <input type="text" class="form-control" id="nama_pasien_display" 
                                           value="<?= htmlspecialchars($data_karyawan['nama'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            
                            <?php $is_disabled_style = $data_karyawan ? '' : 'pointer-events: none; opacity: 0.6;'; ?>
                            <div class="row" id="form_pemeriksaan_detail" style="<?= $is_disabled_style; ?>">
                                
                                <div class="col-md-6">
                                    <h5 class="text-primary"><i class="bi bi-heart-pulse-fill me-1"></i> Tanda Vital & Keluhan</h5>
                                    
                                    <div class="alert alert-info small p-2">
                                        **Gol. Darah:** <?= htmlspecialchars($data_karyawan['golongan_darah'] ?? 'BELUM ADA DATA'); ?><br>
                                        **Penyakit Terdahulu:** <?= htmlspecialchars($data_karyawan['penyakit_terdahulu'] ?? 'BELUM ADA DATA'); ?><br>
                                        **Alergi:** **<?= htmlspecialchars($data_karyawan['alergi'] ?? 'BELUM ADA DATA'); ?>**
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tekanan Darah (mmHg) *</label>
                                            <input type="text" class="form-control" name="tekanan_darah" placeholder="Cth: 120/80" 
                                                   value="<?= postValue('tekanan_darah'); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Suhu Tubuh (°C) *</label>
                                            <input type="number" step="0.1" class="form-control" name="suhu_tubuh" placeholder="Cth: 36.5" 
                                                   value="<?= postValue('suhu_tubuh'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Keluhan Utama *</label>
                                        <textarea class="form-control" name="keluhan" rows="2" placeholder="Keluhan utama saat ini." 
                                                  required><?= postValue('keluhan'); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Diagnosis Medis *</label>
                                        <textarea class="form-control" name="diagnosis" rows="2" placeholder="Diagnosis (Contoh: Common cold / J00)" 
                                                  required><?= postValue('diagnosis'); ?></textarea>
                                    </div>
                                    
                                </div>

                                <div class="col-md-6">
                                    <h5 class="text-success"><i class="bi bi-prescription2 me-1"></i> Tindakan & Resep Obat</h5>
                                    
                                    <div class="mt-3 p-3 border rounded bg-light">
                                        <label class="form-label fw-bold">Resep Obat</label>
                                        <div id="resep_container">
                                            <?php 
                                            if ($error && isset($_POST['obat_id'])): 
                                                // Counter di-set ulang untuk JS
                                                $index_counter = 0; 
                                                foreach ($_POST['obat_id'] as $index => $obat_id_post):
                                                    $nama_obat_fallback = 'Obat ID: ' . htmlspecialchars($obat_id_post);
                                            ?>
                                                <div class="row resep-row border-bottom pb-2 mb-2" id="row-<?= $index_counter ?>">
                                                    <div class="col-md-12 mb-2">
                                                        <label class="form-label small fw-bold">Obat</label>
                                                        <select class="form-control obat-select" name="obat_id[]" required>
                                                            <option value="<?= htmlspecialchars($obat_id_post); ?>" selected><?= $nama_obat_fallback; ?></option>
                                                        </select>
                                                        <span class="stok-warning"></span>
                                                    </div>
                                                    <div class="col-md-3 mb-2">
                                                        <label class="form-label small">Jumlah</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control form-control-sm jumlah-input" name="jumlah[]" min="1" 
                                                                   value="<?= htmlspecialchars($_POST['jumlah'][$index]); ?>" required>
                                                            <span class="input-group-text satuan-display">Satuan</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 mb-2">
                                                        <label class="form-label small">Dosis</label>
                                                        <input type="text" class="form-control form-control-sm" name="dosis[]" placeholder="Cth: 500 mg" 
                                                               value="<?= htmlspecialchars($_POST['dosis'][$index]); ?>" required>
                                                    </div>
                                                    <div class="col-md-5 mb-2">
                                                        <label class="form-label small">Aturan Pakai</label>
                                                        <input type="text" class="form-control form-control-sm" name="aturan_pakai[]" placeholder="Cth: 3x1 Sesudah Makan" 
                                                               value="<?= htmlspecialchars($_POST['aturan_pakai'][$index]); ?>" required>
                                                    </div>
                                                    <div class="col-md-1 mb-2 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-resep-row">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php 
                                                    $index_counter++;
                                                endforeach;
                                            endif; 
                                            ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-warning mt-2" id="add_resep_row">
                                            <i class="bi bi-plus-circle"></i> Tambah Obat
                                        </button>
                                    </div>
                                    
                                    <div class="mb-3 mt-3">
                                        <label class="form-label">Tindakan Medis Tambahan</label>
                                        <textarea class="form-control" name="tindakan" rows="2" placeholder="Cth: Kompres air hangat, Edukasi gizi."><?= postValue('tindakan'); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rujukan ke</label>
                                        <input type="text" class="form-control" name="rujukan" placeholder="Cth: RSUD Arief" value="<?= postValue('rujukan'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Catatan Tambahan (Khusus)</label>
                                        <textarea class="form-control" name="catatan" rows="2" placeholder="Catatan penting lainnya"><?= postValue('catatan'); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex justify-content-end border-top pt-3 mt-4">
                                <button type="submit" name="simpan_pemeriksaan" class="btn btn-primary me-1 mb-1" id="btn_simpan" <?= $data_karyawan ? '' : 'disabled'; ?>>
                                    <i class="bi bi-save me-1"></i> Simpan Pemeriksaan
                                </button>
                                <a href="riwayat_berobat.php" class="btn btn-secondary me-1 mb-1">Kembali</a>
                                <button type="reset" class="btn btn-light-secondary mb-1">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <footer>
            <div class="footer clearfix mb-0 text-muted">
            <div class="float-start">
                <p>2025 &copy; Daelim</p>
            </div>
                s<div class="float-end">
                <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span>
                    by <a href="https://daelim.id">IT PT. Daelim Indonesia</a></p>
                </div>
             </div>
            </footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>

    <script>
    $(document).ready(function() {
        // Jika terjadi error POST, kita harus menghitung ulang index terakhir
        let row_counter = <?= $error && isset($_POST['obat_id']) ? count($_POST['obat_id']) : 0; ?>;

        // =============================================
        // 1. SELECT2 PENCARIAN KARYAWAN (Menggunakan Redirect GET)
        // =============================================
        $('#select_id_card').select2({
            placeholder: 'Ketik Nama atau ID Card Karyawan...',
            allowClear: true,
            ajax: {
                url: 'api_karyawan.php', 
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { query: params.term };
                },
                processResults: function (data) { return { results: data.results || [] }; },
                cache: true
            },
            minimumInputLength: 2,
            templateSelection: function (data) {
                if (!data.id) return data.text;
                // Tampilkan hanya Nama (ID Card) setelah dipilih
                return data.text.split(' - ')[0] + ' (' + data.id + ')'; 
            }
        });

        // Load data karyawan yang sudah ada (saat page load dari GET/POST)
        const initialIdCard = $('#hidden_id_card').val();
        if (initialIdCard) {
            $.ajax({
                url: 'api_karyawan.php?id_card=' + initialIdCard,
                dataType: 'json',
                success: function(response) {
                    const data = response.results[0];
                    if (data) {
                        $('#nama_pasien_display').val(data.nama);
                        
                        // Set option Select2
                        const newOption = new Option(data.text, data.id, true, true);
                        $('#select_id_card').append(newOption).trigger('change');
                        
                        // Pastikan form diaktifkan jika data ditemukan
                        $('#form_pemeriksaan_detail').css({ 'pointer-events': 'auto', 'opacity': '1' });
                        $('#btn_simpan').prop('disabled', false);
                    }
                }
            });
        }
        
        // Event saat karyawan dipilih: REDIRECT URL
        $('#select_id_card').on('select2:select', function (e) {
            const data = e.params.data;
            
            // Set ID Card ke hidden input sebelum redirect
            $('#hidden_id_card').val(data.id);
            
            // Redirect ke URL baru dengan parameter GET
            window.location.href = 'form_pemeriksaan.php?id_card_selected=' + data.id;
        });


        // =============================================
        // 2. SELECT2 RESEP OBAT
        // =============================================
        function initSelect2Obat(selector, initialData = null) {
            const selectElement = $(selector);
            
            selectElement.select2({
                placeholder: 'Cari Nama/Kode Obat...',
                allowClear: true,
                // Penting: dropdownParent mencegah masalah z-index di modal/form tertentu
                dropdownParent: selectElement.parent().parent(), 
                ajax: {
                    url: 'api_obat.php', 
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { query: params.term };
                    },
                    processResults: function (data) { 
                        return { results: data.results || [] };
                    }, 
                    cache: true
                },
                minimumInputLength: 2,
                templateSelection: function (data) {
                    if (!data.id) return data.text;
                    // Hilangkan Stok: dari tampilan setelah dipilih
                    return data.text.split(' - Stok:')[0]; 
                }
            });

            // Jika ada data awal (saat error POST)
            if (initialData && initialData.id && initialData.text) {
                 const newOption = new Option(initialData.text.split(' - Stok:')[0], initialData.id, true, true);
                 selectElement.append(newOption).trigger('change');
            }

            // Event handler saat obat dipilih: Update info stok
            selectElement.on('select2:select', function (e) {
                const data = e.params.data;
                const row = $(this).closest('.resep-row');
                
                row.find('.jumlah-input').attr('max', data.stok).attr('placeholder', 'Max: ' + data.stok);
                row.find('.satuan-display').text(data.satuan);
                
                // Peringatan stok rendah
                if (data.stok <= 10) {
                     row.find('.stok-warning').html('<span class="text-danger small ms-2">Stok Rendah (' + data.stok + ' ' + data.satuan + ')</span>');
                } else {
                     row.find('.stok-warning').empty();
                }
            });
        }
        

        // FUNGSI TAMBAH BARIS RESEP
        function addResepRow() {
            row_counter++;
            const newRow = `
                <div class="row resep-row border-bottom pb-2 mb-2" id="row-${row_counter}">
                    <div class="col-md-12 mb-2">
                        <label class="form-label small fw-bold">Obat</label>
                        <select class="form-control obat-select" name="obat_id[]" required></select>
                        <span class="stok-warning"></span>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label small">Jumlah</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-sm jumlah-input" name="jumlah[]" min="1" required>
                            <span class="input-group-text satuan-display">Satuan</span>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label small">Dosis</label>
                        <input type="text" class="form-control form-control-sm" name="dosis[]" placeholder="Cth: 500 mg" required>
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="form-label small">Aturan Pakai</label>
                        <input type="text" class="form-control form-control-sm" name="aturan_pakai[]" placeholder="Cth: 3x1 Sesudah Makan" required>
                    </div>
                    <div class="col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm remove-resep-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            $('#resep_container').append(newRow);
            
            // Inisialisasi Select2 pada baris yang baru
            initSelect2Obat(`#row-${row_counter} .obat-select`);
        }

        // Tambah Baris Resep saat tombol ditekan
        $('#add_resep_row').on('click', function() {
            addResepRow();
        });

        // Hapus Baris Resep
        $('#resep_container').on('click', '.remove-resep-row', function() {
            $(this).closest('.resep-row').remove();
        });
        
        // Inisialisasi Select2 untuk baris yang di-reload setelah POST error
        <?php 
        if ($error && isset($_POST['obat_id'])): 
            foreach ($_POST['obat_id'] as $index => $obat_id_post):
        ?>
            (function(index, obatId) {
                // Ambil detail obat yang gagal disimpan
                $.ajax({
                    url: 'api_obat.php?id=' + obatId,
                    dataType: 'json',
                    success: function(response) {
                        const data = response.results[0];
                        if (data) {
                            const initialData = {
                                id: data.id,
                                text: data.text,
                                stok: data.stok,
                                satuan: data.satuan
                            };
                            // Re-init Select2 dengan data yang sudah dipilih
                            // Menggunakan index array PHP karena index counter JS baru dimulai dari akhir loop.
                            initSelect2Obat($(`#row-`+index+` .obat-select`), initialData); 
                            $(`#row-`+index+` .jumlah-input`).attr('max', data.stok).attr('placeholder', 'Max: ' + data.stok);
                            $(`#row-`+index+` .satuan-display`).text(data.satuan);
                        }
                    }
                });
            })(<?= $index ?>, '<?= $obat_id_post ?>');
        <?php 
            endforeach;
        endif; 
        ?>
    });
    </script>
</body>
</html>