<?php
// File: form_pemeriksaan.php (FINAL & STABIL)

session_start();
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';
$data_pemeriksaan_edit = null;
$data_resep_edit = [];       
$data_karyawan = null;    
$riwayat_medis = null;    
$is_edit_mode = false;
$id_berobat_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_card_cari = ''; 
$petugas = "Aditya Fajrin"; 

// Helper untuk mengisi form
function postValue($key, $edit_data = null, $default = '') {
    if (isset($_POST[$key])) {
        return htmlspecialchars($_POST[$key]);
    }
    if ($edit_data && isset($edit_data[$key])) {
        return htmlspecialchars($edit_data[$key]);
    }
    return htmlspecialchars($default);
}

// --- LOGIKA DETEKSI MODE EDIT ---
if ($id_berobat_edit > 0 && !isset($_POST['simpan_pemeriksaan'])) {
    $is_edit_mode = true;
    
    // 1. Ambil data pemeriksaan utama DAN data karyawan
    $q_pemeriksaan = "SELECT 
                        b.*, 
                        k.nama AS nama_karyawan,
                        k.jabatan,
                        k.departemen
                      FROM berobat b
                      JOIN karyawan k ON b.id_card = k.id_card
                      WHERE b.id = '$id_berobat_edit'";
    
    $r_pemeriksaan = mysqli_query($koneksi, $q_pemeriksaan);
    
    if ($r_pemeriksaan && mysqli_num_rows($r_pemeriksaan) > 0) {
        $data_pemeriksaan_edit = mysqli_fetch_assoc($r_pemeriksaan);
        $id_card_cari = $data_pemeriksaan_edit['id_card']; 
        
        // Simpan data karyawan untuk pre-fill tampilan
        $data_karyawan = [
            'id_card' => $data_pemeriksaan_edit['id_card'],
            'nama' => $data_pemeriksaan_edit['nama_karyawan'],
            'jabatan' => $data_pemeriksaan_edit['jabatan'],
            'departemen' => $data_pemeriksaan_edit['departemen']
        ];

        // 2. Ambil data resep obat lama
        $q_resep = "SELECT r.*, o.nama_obat, o.satuan, o.stok_tersedia FROM resep_obat r 
                    JOIN obat o ON r.id_obat = o.id 
                    WHERE r.id_berobat = '$id_berobat_edit'";
        $r_resep = mysqli_query($koneksi, $q_resep);
        if ($r_resep) {
            while ($row_resep = mysqli_fetch_assoc($r_resep)) {
                $data_resep_edit[] = $row_resep;
            }
        }
    } else {
        $error = "Data pemeriksaan (ID: $id_berobat_edit) tidak ditemukan.";
        $is_edit_mode = false; 
    }
}


// --- LOGIKA UTAMA: PENYIMPANAN TRANSAKSIONAL (SAMA DENGAN SEBELUMNYA) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pemeriksaan'])) {
    
    $is_update = isset($_POST['id_berobat_edit']) && (int)$_POST['id_berobat_edit'] > 0;
    $id_berobat_post = (int)$_POST['id_berobat_edit'];
    
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    
    // Validasi Karyawan
    $q_check = "SELECT k.nama FROM karyawan k WHERE k.id_card = '$id_card'";
    $r_check = mysqli_query($koneksi, $q_check);
    if (!$r_check || mysqli_num_rows($r_check) == 0) {
        $error = "Gagal menyimpan. ID Card tidak valid atau tidak ditemukan.";
    } else {
        // Ambil data form
        $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
        $diagnosis = mysqli_real_escape_string($koneksi, $_POST['diagnosis']);
        $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
        $tekanan_darah = mysqli_real_escape_string($koneksi, $_POST['tekanan_darah']);
        $suhu_tubuh = mysqli_real_escape_string($koneksi, $_POST['suhu_tubuh']);
        $rujukan = mysqli_real_escape_string($koneksi, $_POST['rujukan']);
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        $tanggal_berobat = date('Y-m-d H:i:s');
        $petugas_input = mysqli_real_escape_string($koneksi, $petugas);

        $obat_ids = $_POST['obat_id'] ?? [];
        $jumlah_keluar = $_POST['jumlah_keluar'] ?? [];
        
        if (empty($id_card) || empty($keluhan) || empty($diagnosis) || empty($tekanan_darah)) {
            $error = "Data pasien, keluhan, diagnosis, dan tekanan darah wajib diisi.";
        }

        if (empty($error)) {
            mysqli_begin_transaction($koneksi);
            try {
                $id_berobat_affected = 0;
                
                if ($is_update) {
                    $id_berobat_affected = $id_berobat_post;
                    
                    // Rollback & Hapus Resep Lama
                    $q_resep_lama = "SELECT id_obat, jumlah FROM resep_obat WHERE id_berobat = '$id_berobat_affected'";
                    $r_resep_lama = mysqli_query($koneksi, $q_resep_lama);
                    while ($obat_lama = mysqli_fetch_assoc($r_resep_lama)) {
                        mysqli_query($koneksi, "UPDATE obat SET stok_tersedia = stok_tersedia + " . (int)$obat_lama['jumlah'] . " WHERE id = '{$obat_lama['id_obat']}'");
                    }
                    mysqli_query($koneksi, "DELETE FROM resep_obat WHERE id_berobat = '$id_berobat_affected'");
                    mysqli_query($koneksi, "DELETE FROM transaksi_obat WHERE id_berobat = '$id_berobat_affected' AND tipe_transaksi = 'Keluar'");
                    
                    // Update Data Pemeriksaan Utama
                    $q_berobat_update = "UPDATE berobat SET 
                        keluhan = '$keluhan', diagnosis = '$diagnosis', tindakan = '$tindakan', 
                        tekanan_darah = '$tekanan_darah', suhu_tubuh = '$suhu_tubuh', 
                        rujukan = '$rujukan', catatan = '$catatan', petugas = '$petugas_input',
                        tanggal_berobat = '$tanggal_berobat'
                    WHERE id = '$id_berobat_affected'";
                    
                    if (!mysqli_query($koneksi, $q_berobat_update)) {
                        throw new Exception("Gagal memperbarui data pemeriksaan: " . mysqli_error($koneksi));
                    }

                } else {
                    // INSERT BARU
                    $q_berobat_insert = "INSERT INTO berobat (
                        id_card, tanggal_berobat, keluhan, diagnosis, tindakan, 
                        tekanan_darah, suhu_tubuh, rujukan, catatan, petugas
                    ) VALUES (
                        '$id_card', '$tanggal_berobat', '$keluhan', '$diagnosis', '$tindakan', 
                        '$tekanan_darah', '$suhu_tubuh', '$rujukan', '$catatan', '$petugas_input'
                    )";
                    
                    if (!mysqli_query($koneksi, $q_berobat_insert)) {
                        throw new Exception("Gagal menyimpan data pemeriksaan: " . mysqli_error($koneksi));
                    }
                    $id_berobat_affected = mysqli_insert_id($koneksi);
                }

                // LOGIKA RESEP OBAT
                if (!empty($obat_ids)) {
                    foreach ($obat_ids as $index => $obat_id) {
                        $id_obat = mysqli_real_escape_string($koneksi, $obat_id);
                        $jml_keluar = (int)$jumlah_keluar[$index];
                        
                        if ($jml_keluar > 0 && !empty($id_obat)) {
                            
                            $q_check_stok = "SELECT stok_tersedia, nama_obat FROM obat WHERE id = '$id_obat'";
                            $r_check_stok = mysqli_query($koneksi, $q_check_stok);
                            $data_obat = mysqli_fetch_assoc($r_check_stok);

                            if (!$data_obat || $jml_keluar > $data_obat['stok_tersedia']) {
                                throw new Exception("Stok obat **" . htmlspecialchars($data_obat['nama_obat'] ?? 'ID ' . $id_obat) . "** tidak mencukupi.");
                            }
                            
                            $stok_sebelum = $data_obat['stok_tersedia'];
                            $stok_sesudah = $stok_sebelum - $jml_keluar;
                            $keterangan_obat = "Resep pemeriksaan (ID: $id_berobat_affected)";

                            // Update Stok, Catat Transaksi, Catat Resep
                            mysqli_query($koneksi, "UPDATE obat SET stok_tersedia = $stok_sesudah WHERE id = '$id_obat'");
                            mysqli_query($koneksi, "INSERT INTO transaksi_obat (id_obat, tanggal_transaksi, tipe_transaksi, jumlah, stok_sebelum, stok_sesudah, keterangan, id_berobat, petugas) VALUES ('$id_obat', '$tanggal_berobat', 'Keluar', '$jml_keluar', '$stok_sebelum', '$stok_sesudah', '$keterangan_obat', '$id_berobat_affected', '$petugas_input')");
                            mysqli_query($koneksi, "INSERT INTO resep_obat (id_berobat, id_obat, jumlah) VALUES ('$id_berobat_affected', '$id_obat', '$jml_keluar')");
                        }
                    }
                }

                mysqli_commit($koneksi);
                
                if ($is_update) {
                    header("Location: form_pemeriksaan.php?id=$id_berobat_affected&status=update_sukses");
                    exit();
                } else {
                    header("Location: detail_pemeriksaan.php?id=$id_berobat_affected&status=tambah_sukses");
                    exit();
                }

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Terjadi Kesalahan Transaksi: " . $e->getMessage() . " ❌";
                $id_card_cari = $id_card;
                if ($is_update) {
                    $is_edit_mode = true;
                    $id_berobat_edit = $id_berobat_post;
                }
            }
        }
    }
}

// --- Logika untuk mengisi ulang data Karyawan & Riwayat Medis ---
if ($error && isset($_POST['id_card'])) {
    $id_card_cari = mysqli_real_escape_string($koneksi, $_POST['id_card']);
}

// Ambil data karyawan dan riwayat medis jika ID Card sudah diketahui (baik dari edit mode atau post gagal)
if (!empty($id_card_cari)) {
    // Ambil detail karyawan
    $q_karyawan = "SELECT id_card, nama, jabatan, departemen FROM karyawan WHERE id_card = '$id_card_cari'";
    $r_karyawan = mysqli_query($koneksi, $q_karyawan);
    if ($r_karyawan && mysqli_num_rows($r_karyawan) > 0) {
        $karyawan_from_db = mysqli_fetch_assoc($r_karyawan);
        if (!$data_karyawan) {
            $data_karyawan = $karyawan_from_db;
        }
    }

    // Ambil riwayat medis
    $q_riwayat = "SELECT penyakit_terdahulu, alergi, golongan_darah FROM riwayat_medis WHERE id_card = '$id_card_cari'";
    $r_riwayat = mysqli_query($koneksi, $q_riwayat);
    if ($r_riwayat && mysqli_num_rows($r_riwayat) > 0) {
        $riwayat_medis = mysqli_fetch_assoc($r_riwayat);
    } else {
        $riwayat_medis = ['penyakit_terdahulu' => 'TIDAK ADA', 'alergi' => 'TIDAK ADA', 'golongan_darah' => 'TIDAK DIKETAHUI'];
    }
} else {
    $riwayat_medis = ['penyakit_terdahulu' => 'TIDAK ADA', 'alergi' => 'TIDAK ADA', 'golongan_darah' => 'TIDAK DIKETAHUI'];
}

// Tentukan data resep yang akan di-load (Edit > Post Gagal > Kosong)
$resep_to_load = [];
if ($error && isset($_POST['obat_id'])) {
    foreach ($_POST['obat_id'] as $index => $obat_id_post) {
        if (!empty($obat_id_post)) {
            // Kita harus mengambil nama obat dari DB untuk pre-fill display input
            $q_obat = "SELECT nama_obat, satuan, stok_tersedia FROM obat WHERE id = '" . mysqli_real_escape_string($koneksi, $obat_id_post) . "'";
            $r_obat = mysqli_query($koneksi, $q_obat);
            $data_obat = mysqli_fetch_assoc($r_obat);

            $resep_to_load[] = [
                'id_obat' => $obat_id_post, 
                'jumlah' => $_POST['jumlah_keluar'][$index] ?? 0,
                'nama_obat' => $data_obat['nama_obat'] ?? 'Obat Tidak Ditemukan',
                'satuan' => $data_obat['satuan'] ?? 'Unit',
                'stok_tersedia' => $data_obat['stok_tersedia'] ?? 0
            ];
        }
    }
} else if ($is_edit_mode) {
    // Jika mode edit berhasil, $data_resep_edit sudah berisi nama_obat, satuan, stok_tersedia
    $resep_to_load = $data_resep_edit;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit_mode ? 'Edit' : 'Form' ?> Pemeriksaan Medis | Klinik Daelim</title>
    <link rel="shortcut icon" href="../../assets/static/images/logo/favicon.svg" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../../assets/compiled/css/iconly.css">
    <style>
        .form-control[readonly] { 
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        /* Desain Rapi dan Sectioned */
        .section-header {
            border-bottom: 2px solid #435ebe;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        .resep-row {
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            background-color: #fcfcfc;
        }
        .info-box {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #435ebe;
            min-height: 200px;
        }
        .danger-box {
            background-color: #fce4ec;
            border-left: 5px solid #d32f2f;
            min-height: 200px;
        }
        /* Style untuk hasil pencarian yang muncul di bawah input */
        .search-result-box {
            max-height: 200px;
            overflow-y: auto;
            position: absolute; /* Penting agar muncul di atas konten */
            z-index: 10;
            width: 95%; /* Sesuaikan dengan lebar input */
        }
    </style>
</head>

<body>
    <script src="../../assets/static/js/components/dark.js"></script>
    <div id="app">
        <div id="main" class="layout-navbar navbar-fixed">
            <div id="main-content">
                <div class="page-heading">
                    <div class="page-title">
                        <div class="row">
                            <div class="col-12 col-md-6 order-md-1 order-last">
                                <h3><?= $is_edit_mode ? 'Edit Pemeriksaan (ID: '.$id_berobat_edit.')' : 'Pemeriksaan Pasien Baru' ?></h3>
                                <p class="text-subtitle text-muted">Formulir untuk mencatat pemeriksaan dan resep obat.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="card-title text-white">Formulir Pemeriksaan</h4>
                        </div>
                        <div class="card-body">
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $error ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($success || (isset($_GET['status']) && $_GET['status'] == 'update_sukses')): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    Data berhasil disimpan!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form action="form_pemeriksaan.php<?= $is_edit_mode ? '?id='.$id_berobat_edit : '' ?>" method="POST" id="pemeriksaanForm">
                                <input type="hidden" name="id_card" id="input_id_card" value="<?= htmlspecialchars($id_card_cari); ?>">
                                <?php if ($is_edit_mode): ?>
                                    <input type="hidden" name="id_berobat_edit" value="<?= $id_berobat_edit; ?>">
                                <?php endif; ?>

                                <h5 class="text-primary section-header"><i class="bi bi-person-check-fill me-2"></i> 1. Data Pasien & Riwayat</h5>
                                
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label for="input_id_card_display">Cari Pasien (NIK / Nama) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="input_id_card_display" 
                                                placeholder="Ketik NIK atau Nama lalu klik Cari"
                                                value="<?= $data_karyawan['nama'] ?? $data_karyawan['id_card'] ?? '' ?>"
                                                <?= $is_edit_mode ? 'readonly' : '' ?> required>
                                            
                                            <button class="btn btn-primary" type="button" id="btn_search_karyawan" <?= $is_edit_mode ? 'disabled' : '' ?>>
                                                <i class="bi bi-search"></i> Cari
                                            </button>
                                            <button class="btn btn-secondary" type="button" id="btn_clear_karyawan" <?= $is_edit_mode ? 'disabled' : '' ?>>
                                                <i class="bi bi-x-circle"></i> Reset
                                            </button>
                                        </div>
                                        <small class="text-muted" id="search_feedback">
                                            <?php if ($data_karyawan): ?>
                                                Pasien saat ini: **<?= htmlspecialchars($data_karyawan['nama']) ?>** (<?= htmlspecialchars($data_karyawan['id_card']) ?>)
                                            <?php else: ?>
                                                Masukkan NIK atau Nama Karyawan untuk memulai.
                                            <?php endif; ?>
                                        </small>
                                        <div id="search_results" class="list-group mt-1">
                                            </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <h6 class="text-primary"><i class="bi bi-person-vcard me-1"></i> Detail Pasien</h6>
                                            <p class="card-text">
                                                <span class="fw-bold">NIK:</span> <span id="display_nik"><?= htmlspecialchars($data_karyawan['id_card'] ?? '-') ?></span><br>
                                                <span class="fw-bold">Nama:</span> <span id="display_nama"><?= htmlspecialchars($data_karyawan['nama'] ?? '-') ?></span>
                                            </p>
                                            <hr>
                                            <h6 class="text-primary"><i class="bi bi-briefcase-fill me-1"></i> Info Pekerjaan</h6>
                                            <div class="form-group">
                                                <label class="small text-muted">Jabatan</label>
                                                <input type="text" class="form-control form-control-sm" id="karyawan_jabatan" value="<?= htmlspecialchars($data_karyawan['jabatan'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="small text-muted">Departemen</label>
                                                <input type="text" class="form-control form-control-sm" id="karyawan_departemen" value="<?= htmlspecialchars($data_karyawan['departemen'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="info-box danger-box">
                                            <h6 class="text-danger"><i class="bi bi-shield-exclamation-fill me-1"></i> Riwayat Kritis</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small text-muted">Golongan Darah</label>
                                                        <input type="text" class="form-control form-control-sm" id="riwayat_goldar" value="<?= htmlspecialchars($riwayat_medis['golongan_darah'] ?? '') ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small text-muted">Alergi</label>
                                                        <input type="text" class="form-control form-control-sm" id="riwayat_alergi" value="<?= htmlspecialchars($riwayat_medis['alergi'] ?? '') ?>" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="small text-muted">Penyakit Terdahulu</label>
                                                <input type="text" class="form-control form-control-sm" id="riwayat_penyakit" value="<?= htmlspecialchars($riwayat_medis['penyakit_terdahulu'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="text-info section-header"><i class="bi bi-heart-pulse-fill me-2"></i> 2. Anamnesa & Diagnosis</h5>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="tekanan_darah">Tekanan Darah (mmHg) <span class="text-danger">*</span></label>
                                            <input type="text" id="tekanan_darah" class="form-control" name="tekanan_darah" placeholder="Cth: 120/80" value="<?= postValue('tekanan_darah', $data_pemeriksaan_edit); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="suhu_tubuh">Suhu Tubuh (°C) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.1" id="suhu_tubuh" class="form-control" name="suhu_tubuh" placeholder="Cth: 36.5" value="<?= postValue('suhu_tubuh', $data_pemeriksaan_edit); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Petugas Pemeriksa</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($petugas); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="keluhan">Keluhan Utama <span class="text-danger">*</span></label>
                                    <textarea id="keluhan" class="form-control" name="keluhan" rows="3" required><?= postValue('keluhan', $data_pemeriksaan_edit); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="diagnosis">Diagnosis <span class="text-danger">*</span></label>
                                    <textarea id="diagnosis" class="form-control" name="diagnosis" rows="3" required><?= postValue('diagnosis', $data_pemeriksaan_edit); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tindakan">Tindakan Medis / Instruksi <span class="text-danger">*</span></label>
                                            <textarea id="tindakan" class="form-control" name="tindakan" rows="2" required><?= postValue('tindakan', $data_pemeriksaan_edit); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="rujukan">Rujukan (Opsional)</label>
                                            <input type="text" id="rujukan" class="form-control" name="rujukan" placeholder="Cth: Rujuk ke RSUD X" value="<?= postValue('rujukan', $data_pemeriksaan_edit); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="catatan">Catatan Tambahan (Opsional)</label>
                                            <textarea id="catatan" class="form-control" name="catatan" rows="1"><?= postValue('catatan', $data_pemeriksaan_edit); ?></textarea>
                                        </div>
                                    </div>
                                </div>


                                <h5 class="text-success section-header mt-4"><i class="bi bi-capsule-pill me-2"></i> 3. Resep Obat</h5>
                                <p class="text-muted">Masukkan obat yang diresepkan. Stok obat akan dikurangi secara otomatis.</p>
                                
                                <div id="resep_container">
                                    <?php 
                                    $resep_index = 0;
                                    if (!empty($resep_to_load)):
                                        foreach ($resep_to_load as $resep):
                                    ?>
                                    <div class="row gx-2 align-items-center resep-row" id="row-<?= $resep_index ?>">
                                        <div class="col-md-5 col-12 position-relative">
                                            <label class="form-label visually-hidden">Nama Obat</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control obat-display-input" id="obat_display_<?= $resep_index ?>" 
                                                    placeholder="Cari Nama/Kode Obat" 
                                                    value="<?= htmlspecialchars($resep['nama_obat']) . ' (' . htmlspecialchars($resep['satuan']) . ')' ?>" required>
                                                <input type="hidden" class="obat-id-hidden" name="obat_id[]" value="<?= htmlspecialchars($resep['id_obat']); ?>">
                                                
                                                <button class="btn btn-info btn-search-obat" type="button" data-index="<?= $resep_index ?>">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-hapus-obat" type="button" data-index="<?= $resep_index ?>"><i class="bi bi-trash"></i></button>
                                            </div>
                                            <div id="obat_search_results_<?= $resep_index ?>" class="list-group list-group-flush mt-1 search-result-box">
                                                </div>
                                        </div>

                                        <div class="col-md-3 col-6 mt-2 mt-md-0">
                                            <label class="form-label visually-hidden">Jumlah</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control jumlah-input" name="jumlah_keluar[]" min="1" 
                                                    placeholder="Jumlah (Max: <?= $resep['stok_tersedia'] + ($is_edit_mode ? $resep['jumlah'] : 0) ?>)" 
                                                    value="<?= htmlspecialchars($resep['jumlah']); ?>" required>
                                                <span class="input-group-text satuan-display"><?= htmlspecialchars($resep['satuan'] ?? 'Unit') ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6 mt-2 mt-md-0 d-flex align-items-center">
                                            <small class="text-muted stok-info me-2">
                                                Stok: <?= number_format($resep['stok_tersedia']) ?> 
                                                (Max: <?= number_format($resep['stok_tersedia'] + ($is_edit_mode ? $resep['jumlah'] : 0)) ?>)
                                            </small>
                                        </div>
                                    </div>
                                    <?php 
                                        $resep_index++;
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>
                                
                                <button type="button" id="tambah_obat" class="btn btn-sm btn-success mt-3">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Obat
                                </button>
                                
                                <hr class="mt-5">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary me-2" name="simpan_pemeriksaan" id="btnSimpanFinal">
                                        <i class="bi bi-save me-2"></i> <?= $is_edit_mode ? 'Update Pemeriksaan' : 'Simpan Pemeriksaan' ?>
                                    </button>
                                    <a href="riwayat_berobat.php" class="btn btn-light-secondary">Batal</a>
                                </div>
                            </form>
                            
                        </div>
                    </div>
                </section>
            </div>
            
            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>2025 &copy; Daelim</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script>
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>
    <script>
    $(document).ready(function() {
        // Variabel untuk menghitung baris resep. Mulai dari PHP index + 1
        let resepCounter = <?= $resep_index ?>;

        // Data resep lama (khusus untuk perhitungan Max Stok di Edit Mode)
        const resepDataEditMode = <?= json_encode($data_resep_edit) ?>;
        const isEditMode = <?= $is_edit_mode ? 'true' : 'false' ?>;

        // --- FUNGSI RESET SEMUA FIELD PASIEN ---
        function resetPatientFields() {
            $('#input_id_card').val('');
            $('#input_id_card_display').val('');
            $('#display_nik').text('-');
            $('#display_nama').text('-');
            $('#karyawan_jabatan').val('');
            $('#karyawan_departemen').val('');
            $('#riwayat_goldar').val('TIDAK DIKETAHUI');
            $('#riwayat_alergi').val('TIDAK ADA');
            $('#riwayat_penyakit').val('TIDAK ADA');
            $('#search_results').empty();
            $('#search_feedback').html('Masukkan NIK atau Nama Karyawan untuk memulai.');
        }

        // --- FUNGSI MENGISI DETAIL PASIEN (Karyawan + Riwayat) ---
        function fillPatientDetails(id_card, data_karyawan) {
            // 1. Isi form Karyawan
            $('#input_id_card').val(id_card);
            $('#display_nik').text(data_karyawan.id_card || '-');
            $('#display_nama').text(data_karyawan.nama || '-');
            $('#karyawan_jabatan').val(data_karyawan.jabatan || '');
            $('#karyawan_departemen').val(data_karyawan.departemen || '');
            $('#search_feedback').html('Pasien saat ini: **' + data_karyawan.nama + '** (' + data_karyawan.id_card + ')');
            
            // 2. Ambil Riwayat Medis (AJAX)
            $.ajax({
                url: 'get_riwayat_medis_ajax.php?id_card=' + id_card, 
                dataType: 'json',
                success: function(response) {
                    $('#riwayat_penyakit').val(response.penyakit_terdahulu || 'TIDAK ADA');
                    $('#riwayat_alergi').val(response.alergi || 'TIDAK ADA');
                    $('#riwayat_goldar').val(response.golongan_darah || 'TIDAK DIKETAHUI');
                }
            });
        }

        // --- FUNGSI UTAMA PENCARIAN KARYAWAN ---
        function searchKaryawan() {
            const query = $('#input_id_card_display').val().trim();
            $('#search_results').empty();

            if (query.length < 2) {
                $('#search_feedback').text('Masukkan minimal 2 karakter (NIK/Nama) untuk mencari.');
                return;
            }
            
            $('#search_feedback').html('<i class="bi bi-hourglass-split"></i> Mencari...');
            
            $.ajax({
                url: 'api_karyawan.php?query=' + encodeURIComponent(query), 
                dataType: 'json',
                success: function(response) {
                    const results = response.results || [];
                    
                    if (results.length > 0) {
                        $('#search_results').empty(); 
                        $('#search_feedback').text(results.length + ' hasil ditemukan. Pilih salah satu di bawah:');
                        
                        results.forEach(function(item) {
                            const resultItem = $(`
                                <a href="#" class="list-group-item list-group-item-action list-group-item-light" 
                                    data-id="${item.id}" 
                                    data-nama="${item.text.split('(')[0].trim()}"
                                    data-jabatan="${item.jabatan}" 
                                    data-departemen="${item.departemen}">
                                    ${item.text}
                                </a>
                            `);
                            
                            // Event listener untuk memilih hasil
                            resultItem.on('click', function(e) {
                                e.preventDefault();
                                const selectedId = $(this).data('id');
                                const selectedNama = $(this).data('nama');
                                const selectedJabatan = $(this).data('jabatan');
                                const selectedDepartemen = $(this).data('departemen');
                                
                                // Isi Input Display dengan Nama Karyawan yang dipilih
                                $('#input_id_card_display').val(selectedNama + ' (' + selectedId + ')');
                                
                                // Isi field detail dan riwayat
                                fillPatientDetails(selectedId, {
                                    id_card: selectedId,
                                    nama: selectedNama,
                                    jabatan: selectedJabatan,
                                    departemen: selectedDepartemen
                                });
                                
                                $('#search_results').empty(); // Bersihkan hasil
                            });
                            
                            $('#search_results').append(resultItem);
                        });
                        
                    } else {
                        $('#search_results').empty();
                        $('#search_feedback').html('<i class="bi bi-x-circle-fill text-danger"></i> Data karyawan tidak ditemukan.');
                    }
                },
                error: function(xhr, status, error) {
                    $('#search_results').empty();
                    $('#search_feedback').html('<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error koneksi: Gagal memuat data karyawan.');
                }
            });
        }

        // --- FUNGSI UTAMA PENCARIAN OBAT (MIRIP KARYAWAN) ---
        function searchObat(rowElement, query) {
            const index = rowElement.data('index');
            const resultBox = $(`#obat_search_results_${index}`);
            resultBox.empty();

            if (query.length < 2) {
                resultBox.html('<small class="text-muted p-2">Ketik minimal 2 karakter.</small>');
                return;
            }
            
            resultBox.html('<div class="list-group-item list-group-item-info p-2"><i class="bi bi-hourglass-split"></i> Mencari...</div>');
            
            $.ajax({
                url: 'api_obat.php?query=' + encodeURIComponent(query), 
                dataType: 'json',
                success: function(response) {
                    const results = response.results || [];
                    
                    if (results.length > 0) {
                        resultBox.empty();
                        
                        results.forEach(function(item) {
                            const maxStokTersedia = parseInt(item.stok);
                            let maxLimit = maxStokTersedia;

                            // Jika mode edit, tambahkan stok yang diresepkan sebelumnya untuk obat ini
                            if (isEditMode) {
                                const resepLama = resepDataEditMode.find(r => r.id_obat == item.id);
                                if (resepLama) {
                                    maxLimit += parseInt(resepLama.jumlah);
                                }
                            }
                            
                            const resultItem = $(`
                                <a href="#" class="list-group-item list-group-item-action list-group-item-light" 
                                    data-id="${item.id}" 
                                    data-nama="${item.nama}" 
                                    data-satuan="${item.satuan}"
                                    data-stok="${item.stok}"
                                    data-max-limit="${maxLimit}">
                                    ${item.text}
                                    <span class="badge bg-success float-end">Stok: ${item.stok}</span>
                                </a>
                            `);
                            
                            // Event listener untuk memilih hasil
                            resultItem.on('click', function(e) {
                                e.preventDefault();
                                const $this = $(this);
                                const selectedId = $this.data('id');
                                const selectedNama = $this.data('nama');
                                const selectedSatuan = $this.data('satuan');
                                const selectedStok = $this.data('stok');
                                const selectedMaxLimit = $this.data('max-limit');
                                
                                const parentRow = $this.closest('.resep-row');
                                
                                // Isi Input Display dan Hidden ID
                                parentRow.find('.obat-display-input').val(selectedNama + ' (' + selectedSatuan + ')');
                                parentRow.find('.obat-id-hidden').val(selectedId);
                                
                                // Atur Input Jumlah
                                const jumlahInput = parentRow.find('.jumlah-input');
                                jumlahInput.attr('max', selectedMaxLimit).val(1).attr('placeholder', 'Max: ' + selectedMaxLimit).prop('disabled', false);
                                
                                // Update info stok dan satuan
                                parentRow.find('.satuan-display').text(selectedSatuan);
                                parentRow.find('.stok-info').html('Stok: ' + selectedStok + ' (Max: ' + selectedMaxLimit + ')').removeClass('text-danger').addClass('text-success');
                                
                                resultBox.empty(); // Bersihkan hasil
                            });
                            
                            resultBox.append(resultItem);
                        });
                        
                    } else {
                        resultBox.html('<div class="list-group-item list-group-item-warning p-2">Obat tidak ditemukan atau stok kosong.</div>');
                    }
                },
                error: function() {
                    resultBox.html('<div class="list-group-item list-group-item-danger p-2">Error koneksi: Gagal memuat data obat.</div>');
                }
            });
        }


        // --- FUNGSI DINAMIS TAMBAH BARIS OBAT ---
        function addObatRow(initialResepData = {}) {
            const index = resepCounter++;
            
            const isPreFilled = initialResepData.id_obat;
            const currentStock = initialResepData.stok_tersedia || 0;
            const currentAmount = initialResepData.jumlah || 0;
            const currentSatuan = initialResepData.satuan || 'Unit';
            
            let maxLimit = currentStock;
            if (isEditMode && isPreFilled) {
                // Tambahkan jumlah yang sudah diresepkan sebelumnya agar bisa di edit
                maxLimit = currentStock + currentAmount; 
            }

            const displayValue = isPreFilled ? 
                initialResepData.nama_obat + ' (' + currentSatuan + ')' : '';

            const newRow = $(`
                <div class="row gx-2 align-items-center resep-row" id="row-${index}" data-index="${index}">
                    <div class="col-md-5 col-12 position-relative">
                        <label class="form-label visually-hidden">Nama Obat</label>
                        <div class="input-group">
                            <input type="text" class="form-control obat-display-input" id="obat_display_${index}" 
                                placeholder="Cari Nama/Kode Obat" 
                                value="${displayValue}" required>
                            <input type="hidden" class="obat-id-hidden" name="obat_id[]" value="${initialResepData.id_obat || ''}">
                            
                            <button class="btn btn-info btn-search-obat" type="button" data-index="${index}">
                                <i class="bi bi-search"></i>
                            </button>
                            <button class="btn btn-danger btn-hapus-obat" type="button" data-index="${index}"><i class="bi bi-trash"></i></button>
                        </div>
                        <div id="obat_search_results_${index}" class="list-group list-group-flush mt-1 search-result-box">
                        </div>
                    </div>

                    <div class="col-md-3 col-6 mt-2 mt-md-0">
                        <label class="form-label visually-hidden">Jumlah</label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control jumlah-input" name="jumlah_keluar[]" min="1" 
                                placeholder="Jumlah (Max: ${maxLimit})" 
                                value="${currentAmount || ''}" 
                                ${isPreFilled ? '' : 'disabled'} required>
                            <span class="input-group-text satuan-display">${currentSatuan}</span>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 mt-2 mt-md-0 d-flex align-items-center">
                        <small class="text-muted stok-info me-2 ${isPreFilled ? 'text-success' : ''}">
                            ${isPreFilled ? 'Stok: ' + currentStock + ' (Max: ' + maxLimit + ')' : ''}
                        </small>
                    </div>
                </div>
            `);
            
            $('#resep_container').append(newRow);

            // ----------------------------------------------------
            // EVENT LISTENERS UNTUK BARIS OBAT YANG BARU DITAMBAHKAN
            // ----------------------------------------------------

            // 1. Tombol Search Obat
            newRow.find('.btn-search-obat').on('click', function() {
                const query = newRow.find('.obat-display-input').val().trim();
                searchObat(newRow, query);
            });

            // 2. Tombol Hapus Obat
            newRow.find('.btn-hapus-obat').on('click', function() {
                newRow.remove();
            });

            // 3. Pemicu pencarian saat menekan Enter atau input berubah
            newRow.find('.obat-display-input').on('keypress', function(e) {
                if (e.which === 13) { // Key code for Enter
                    e.preventDefault();
                    newRow.find('.btn-search-obat').click();
                }
            });
            
            // 4. Batasan Input Jumlah
            newRow.find('.jumlah-input').on('change keyup', function() {
                const input = $(this);
                const max = parseInt(input.attr('max'));
                const val = parseInt(input.val());
                if (max && val > max) input.val(max);
                else if (val < 1) input.val(1);
            });

            // 5. Clear Input Obat saat diketik ulang
            newRow.find('.obat-display-input').on('input', function() {
                // Hapus ID dan info stok saat mulai mengetik ulang
                newRow.find('.obat-id-hidden').val('');
                newRow.find('.jumlah-input').val('').prop('disabled', true);
                newRow.find('.stok-info').text('');
            });
        }


        // --- EVENT HANDLERS KARYAWAN (Tidak Berubah) ---
        $('#btn_search_karyawan').on('click', searchKaryawan);
        $('#btn_clear_karyawan').on('click', resetPatientFields);
        
        $('#input_id_card_display').on('keypress', function(e) {
            if (e.which === 13) { 
                e.preventDefault();
                searchKaryawan();
            }
        });


        // --- INISIALISASI HALAMAN ---
        
        // 1. Muat data resep (jika mode edit atau POST gagal)
        if (resepDataFromPHP.length > 0) {
            resepDataFromPHP.forEach(data => addObatRow(data));
        } else {
            // 2. Tambah baris resep kosong jika tidak ada data awal
             addObatRow();
        }

    });
    </script>
</body>
</html>