<?php
// File: form_pemeriksaan.php
session_start();
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$error = '';
$data_karyawan = null;
$id_card_cari = '';
$petugas = "Aditya Fajrin"; // Ganti dengan session nanti

// Helper post value aman
function postValue($key, $default = '') {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default;
}

// --- SIMPAN TRANSAKSI (TIDAK DIRUBAH) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pemeriksaan'])) {
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    
    $q_check = "SELECT nama FROM karyawan WHERE id_card = '$id_card'";
    if (mysqli_num_rows(mysqli_query($koneksi, $q_check)) == 0) {
        $error = "ID Card tidak ditemukan.";
    } else {
        $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
        $diagnosis = mysqli_real_escape_string($koneksi, $_POST['diagnosis']);
        $tekanan_darah = mysqli_real_escape_string($koneksi, $_POST['tekanan_darah']);
        $suhu_tubuh = mysqli_real_escape_string($koneksi, $_POST['suhu_tubuh']);
        $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan'] ?? '');
        $rujukan = mysqli_real_escape_string($koneksi, $_POST['rujukan'] ?? '');
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');

        $obat_ids = $_POST['obat_id'] ?? [];
        $jumlahs = $_POST['jumlah'] ?? [];
        $dosiss = $_POST['dosis'] ?? [];
        $aturan_pakais = $_POST['aturan_pakai'] ?? [];

        mysqli_begin_transaction($koneksi);

        try {
            $query_berobat = "INSERT INTO berobat (
                id_card, tanggal_berobat, keluhan, diagnosis, tekanan_darah, 
                suhu_tubuh, tindakan, rujukan, catatan, petugas
            ) VALUES (
                '$id_card', NOW(), '$keluhan', '$diagnosis', '$tekanan_darah', 
                '$suhu_tubuh', '$tindakan', '$rujukan', '$catatan', '$petugas'
            )";
            if (!mysqli_query($koneksi, $query_berobat)) throw new Exception(mysqli_error($koneksi));
            $berobat_id = mysqli_insert_id($koneksi);

            if (!empty($obat_ids)) {
                foreach ($obat_ids as $index => $obat_id) {
                    $jumlah = (int)($jumlahs[$index] ?? 0);
                    $dosis = mysqli_real_escape_string($koneksi, $dosiss[$index] ?? '');
                    $aturan_pakai = mysqli_real_escape_string($koneksi, $aturan_pakais[$index] ?? '');
                    
                    if ($obat_id && $jumlah > 0) {
                        $q_stok = "SELECT stok_tersedia, nama_obat FROM obat WHERE id = '$obat_id'";
                        $r_stok = mysqli_query($koneksi, $q_stok);
                        $obat = mysqli_fetch_assoc($r_stok);
                        if (!$obat) throw new Exception("Obat ID $obat_id tidak ada.");
                        if ($obat['stok_tersedia'] < $jumlah) throw new Exception("Stok {$obat['nama_obat']} kurang (ada {$obat['stok_tersedia']}, minta $jumlah).");

                        $stok_sesudah = $obat['stok_tersedia'] - $jumlah;

                        $q_resep = "INSERT INTO resep_obat (berobat_id, obat_id, jumlah, dosis, aturan_pakai, created_at) 
                                    VALUES ('$berobat_id', '$obat_id', '$jumlah', '$dosis', '$aturan_pakai', NOW())";
                        if (!mysqli_query($koneksi, $q_resep)) throw new Exception(mysqli_error($koneksi));
                        $resep_id = mysqli_insert_id($koneksi);

                        $q_update = "UPDATE obat SET stok_tersedia = '$stok_sesudah', updated_at = NOW() WHERE id = '$obat_id'";
                        if (!mysqli_query($koneksi, $q_update)) throw new Exception(mysqli_error($koneksi));

                        $keterangan = "Keluar resep Berobat ID: $berobat_id";
                        $q_trans = "INSERT INTO transaksi_obat (
                            obat_id, jenis_transaksi, jumlah, stok_sebelum, stok_sesudah, 
                            resep_obat_id, tanggal_transaksi, keterangan, petugas
                        ) VALUES (
                            '$obat_id', 'KELUAR', '$jumlah', '{$obat['stok_tersedia']}', '$stok_sesudah', 
                            '$resep_id', NOW(), '$keterangan', '$petugas'
                        )";
                        if (!mysqli_query($koneksi, $q_trans)) throw new Exception(mysqli_error($koneksi));
                    }
                }
            }

            mysqli_commit($koneksi);
            header("Location: riwayat_berobat.php?status=success_add");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error = $e->getMessage();
        }
    }
}

// --- LOAD KARYAWAN ---
if (isset($_POST['id_card'])) {
    $id_card_cari = mysqli_real_escape_string($koneksi, $_POST['id_card']);
} elseif (isset($_GET['id_card_selected'])) {
    $id_card_cari = mysqli_real_escape_string($koneksi, $_GET['id_card_selected']);
}

if ($id_card_cari) {
    $q = "SELECT k.id_card, k.nama, k.jabatan, k.departemen, 
                 rm.golongan_darah, rm.penyakit_terdahulu, rm.alergi 
          FROM karyawan k 
          LEFT JOIN riwayat_medis rm ON k.id_card = rm.id_card 
          WHERE k.id_card = '$id_card_cari'";
    $r = mysqli_query($koneksi, $q);
    $data_karyawan = mysqli_fetch_assoc($r);
    if (!$data_karyawan) $error = "Pasien tidak ditemukan.";
}

// --- RIWAYAT MEDIS ---
$riwayat_medis = [];
if ($data_karyawan) {
    $q_riwayat = "SELECT tanggal_berobat, keluhan, diagnosis, tekanan_darah, suhu_tubuh 
                  FROM berobat 
                  WHERE id_card = '{$data_karyawan['id_card']}' 
                  ORDER BY tanggal_berobat DESC 
                  LIMIT 5";
    $r_riwayat = mysqli_query($koneksi, $q_riwayat);
    while ($row = mysqli_fetch_assoc($r_riwayat)) {
        $riwayat_medis[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Pemeriksaan Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" href="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../../assets/extensions/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        :root { --primary: #4361ee; --danger: #f14668; --info: #4cc9f0; --warning: #f5b74f; }
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); background: #fff; margin-bottom: 2rem; }
        .card-header { background: var(--primary); color: #fff; border-radius: 1rem 1rem 0 0 !important; padding: 1.5rem; }
        /* PERBAIKAN SELECT2 & FORM CONTROL */
        .form-control, 
        .select2-selection, 
        .select2-container--default .select2-selection--single { 
            border-radius: 0.75rem; 
            padding: 0.65rem 1rem; 
            border: 1.5px solid #ddd; 
            height: calc(2.4rem + 10px); /* Fix tinggi Select2 */
            line-height: 1.8; 
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(67,97,238,0.25); }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
             /* DITINGGIKAN/DIPRESISIKAN */
             line-height: 1.2rem; /* Nilai diperkecil agar teks terangkat */
             padding-top: 0.2rem; /* Padding atas agar lebih presisi center */
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(2.4rem + 2px); /* Sesuaikan tinggi arrow */
        }
        /* END PERBAIKAN SELECT2 & FORM CONTROL */

        .btn { border-radius: 0.75rem; padding: 0.75rem 1.5rem; font-weight: 600; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: #3f37c9; }
        .resep-row { background: #f8f9fa; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem; border: 1px solid #eee; }
        .patient-info { background: #e3f2fd; border-radius: 0.75rem; padding: 1rem; }
        .badge-info { background: #bbdefb; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-size: 0.9rem; }
        #add_resep_row { background: rgba(245,183,79,0.2); border: 2px dashed var(--warning); color: var(--warning); }
        #add_resep_row:hover { background: var(--warning); color: #fff; }
        .stok-warning { color: var(--danger); font-weight: bold; font-size: 0.85rem; }
        .table-riwayat { font-size: 0.9rem; }
        .table-riwayat th { background: #e9ecef; font-weight: 600; text-align: center; }
        .table-riwayat td { vertical-align: middle; }
        .info-medis { background: #e3f2fd; border-radius: 0.75rem; padding: 1rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }
        hr { border-top: 2px dashed #dee2e6; margin: 2rem 0; }
    </style>
</head>
<body>
    
<script src="../../assets/static/js/initTheme.js"></script>
    <div id="app">
        <div id="sidebar">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="../../"><img src="../../assets/images/logo.PNG" alt="Logo" srcset=""></a>
                        </div>
                        <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--system-uicons" width="20" height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                                <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4zM4.136 4.136L5.55 5.55m9.9 9.9l1.414 1.414M1.5 10.5h2m14 0h2M4.135 16.863L5.55 15.45m9.899-9.9l1.414-1.415M10.5 19.5v-2m0-14v-2" opacity=".3"></path>
                                    <g transform="translate(-210 -1)">
                                        <path d="M220.5 2.5v2m6.5.5l-1.5 1.5"></path>
                                        <circle cx="220.5" cy="11.5" r="4"></circle>
                                        <path d="m214 5l1.5 1.5m5 14v-2m6.5-.5l-1.5-1.5M214 18l1.5-1.5m-4-5h2m14 0h2"></path>
                                    </g>
                                </g>
                            </svg>
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
                                <label class="form-check-label"></label>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--mdi" width="20" height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24">
                                <path fill="currentColor" d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z"></path>
                            </svg>
                        </div>
                        <div class="sidebar-toggler x">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                        </div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-title">Menu</li>
                        <li class="sidebar-item active">
                            <a href="../../" class='sidebar-link'>
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../karyawan/karyawan.php" class='sidebar-link'>
                                <i class="bi bi-stack"></i>
                                <span>Data Karyawan</span>
                            </a>
                        </li>
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-collection-fill"></i>
                                <span>Pelayanan Kesehatan</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="../karyawan/riwayat_kecelakaan.php" class="submenu-link">Kecelakaan Kerja</a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-grid-1x2-fill"></i>
                                <span>Manajemen Obat</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="../obat/master_obat.php" class="submenu-link">Data Obat</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="../obat/laporan_transaksi_obat.php" class="submenu-link">Laporan Transaksi Obat</a>
                                </li>
                            </ul>
                        </li>
                        <li
                class="sidebar-item has-sub ">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                <ul class="submenu ">
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_berobat.php" class="submenu-link">Laporan Berobat</a>                     
                    </li>       
                    <li class="submenu-item  ">
                        <a href="..laporan/laporan_obat.php" class="submenu-link">Laporan Obat</a>                     
                    </li>         
                    <li class="submenu-item  ">
                        <a href="../laporan/form_laporan_bulanan.php" class="submenu-link">Laporan Kecelakaan Kerja</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_tren_berobat.php" class="submenu-link">Statistik Berobat</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_tren_kecelakaan.php" class="submenu-link">Statistik Kecelakaan Kerja</a>
                    </li>
                </ul>
            </li>
                        <li class="sidebar-item">
                            <a href="../../logout.php" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main" class="layout-navbar">
        <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
            <div class="page-heading p-4">
                <h3>Input Pemeriksaan Baru</h3>
                <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Riwayat Berobat</li>
                    </ol>
            </div>
            <section class="section px-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="ti ti-file-medical"></i> Form Pemeriksaan Pasien</h4>
                    </div>
                    <div class="card-body pt-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="row align-items-start g-4 mb-4">
                                <div class="col-md-5">
                                    <label class="form-label"><i class="ti ti-search"></i> Cari Pasien</label>
                                    <select class="form-control" id="select_id_card" required></select>
                                    <input type="hidden" name="id_card" id="hidden_id_card" value="<?= htmlspecialchars($data_karyawan['id_card'] ?? '') ?>">
                                </div>
                                <div class="col-md-7">
                                    <div class="patient-info">
                                        <div class="row g-3">
                                            <div class="col-sm-6"><strong>Nama:</strong> <span id="nama_display"><?= htmlspecialchars($data_karyawan['nama'] ?? 'N/A') ?></span></div>
                                            <div class="col-sm-6"><strong>ID Card:</strong> <?= htmlspecialchars($data_karyawan['id_card'] ?? '-') ?></div>
                                            <div class="col-sm-6"><strong>Jabatan:</strong> <span class="badge-info"><?= htmlspecialchars($data_karyawan['jabatan'] ?? 'N/A') ?></span></div>
                                            <div class="col-sm-6"><strong>Dept:</strong> <span class="badge-info"><?= htmlspecialchars($data_karyawan['departemen'] ?? 'N/A') ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($data_karyawan): ?>
                            <hr>

                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <h5 class="section-title"><i class="ti ti-user-check"></i> Data Medis Dasar</h5>
                                    <div class="info-medis">
                                        <div class="row g-3 text-center">
                                            <div class="col-4">
                                                <strong>Gol. Darah</strong><br>
                                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($data_karyawan['golongan_darah'] ?? 'N/A') ?></span>
                                            </div>
                                            <div class="col-4">
                                                <strong>Penyakit Lama</strong><br>
                                                <span class="badge bg-info fs-6"><?= htmlspecialchars($data_karyawan['penyakit_terdahulu'] ?? 'N/A') ?></span>
                                            </div>
                                            <div class="col-4">
                                                <strong>Alergi</strong><br>
                                                <span class="badge bg-danger fs-6"><?= htmlspecialchars($data_karyawan['alergi'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="section-title"><i class="ti ti-history"></i> Riwayat Pemeriksaan (5 Terakhir)</h5>
                                    <?php if (!empty($riwayat_medis)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-riwayat table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Keluhan</th>
                                                    <th>Diagnosis</th>
                                                    <th>Vital</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($riwayat_medis as $r): ?>
                                                <tr>
                                                    <td class="text-center"><?= date('d/m/Y', strtotime($r['tanggal_berobat'])) ?></td>
                                                    <td><?= htmlspecialchars($r['keluhan']) ?></td>
                                                    <td><?= htmlspecialchars($r['diagnosis']) ?></td>
                                                    <td class="small">TD: <?= htmlspecialchars($r['tekanan_darah']) ?><br>Suhu: <?= htmlspecialchars($r['suhu_tubuh']) ?>°C</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted small text-center">Belum ada riwayat pemeriksaan.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <hr>

                            <div <?= $data_karyawan ? '' : 'style="opacity:0.6; pointer-events:none;"' ?>>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <h5 class="section-title"><i class="ti ti-heart-pulse"></i> Tanda Vital & Keluhan</h5>
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label>Tekanan Darah *</label>
                                                <input type="text" class="form-control" name="tekanan_darah" placeholder="120/80" value="<?= postValue('tekanan_darah') ?>" required>
                                            </div>
                                            <div class="col-6">
                                                <label>Suhu Tubuh (°C) *</label>
                                                <input type="number" step="0.1" class="form-control" name="suhu_tubuh" placeholder="36.5" value="<?= postValue('suhu_tubuh') ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label>Keluhan *</label>
                                                <textarea class="form-control" name="keluhan" rows="4" required><?= postValue('keluhan') ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label>Diagnosis *</label>
                                                <textarea class="form-control" name="diagnosis" rows="4" required><?= postValue('diagnosis') ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <h5 class="section-title"><i class=""></i> Resep Obat</h5>
                                        <div id="resep_container">
                                            <?php if ($error && isset($_POST['obat_id'])): 
                                                foreach ($_POST['obat_id'] as $i => $oid): ?>
                                                <div class="resep-row row g-3 align-items-end">
                                                    <div class="col-6">
                                                        <select class="form-control obat-select" name="obat_id[]" required>
                                                            <option value="<?= htmlspecialchars($oid) ?>" selected>Loading...</option>
                                                        </select>
                                                        <small class="stok-warning d-block mt-1"></small>
                                                    </div>
                                                    <div class="col-2">
                                                        <input type="number" class="form-control" name="jumlah[]" min="1" value="<?= htmlspecialchars($_POST['jumlah'][$i] ?? '') ?>" required>
                                                    </div>
                                                    <div class="col-2">
                                                        <input type="text" class="form-control" name="dosis[]" placeholder="Dosis" value="<?= htmlspecialchars($_POST['dosis'][$i] ?? '') ?>">
                                                    </div>
                                                    <div class="col-1">
                                                        <input type="text" class="form-control" name="aturan_pakai[]" placeholder="Aturan" value="<?= htmlspecialchars($_POST['aturan_pakai'][$i] ?? '') ?>">
                                                    </div>
                                                    <div class="col-1">
                                                        <button type="button" class="btn btn-danger btn-sm remove-row"><i class="ti ti-trash"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm mt-2" id="add_resep_row"><i class="ti ti-plus"></i> Tambah Obat</button>

                                        <h5 class="section-title mt-4"><i class="ti ti-first-aid-kit"></i> Tindakan Lanjutan</h5>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label>Tindakan</label>
                                                <textarea class="form-control" name="tindakan" rows="3"><?= postValue('tindakan') ?></textarea>
                                            </div>
                                            <div class="col-6">
                                                <label>Rujukan</label>
                                                <input type="text" class="form-control" name="rujukan" value="<?= postValue('rujukan') ?>">
                                            </div>
                                            <div class="col-6">
                                                <label>Catatan</label>
                                                <textarea class="form-control" name="catatan" rows="3"><?= postValue('catatan') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="mt-5">

                                <div class="text-end">
                                    <button type="submit" name="simpan_pemeriksaan" class="btn btn-primary px-5" <?= $data_karyawan ? '' : 'disabled' ?>><i class="ti ti-save"></i> Simpan Pemeriksaan</button>
                                    <a href="riwayat_berobat.php" class="btn btn-secondary px-4">Kembali</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="../../assets/extensions/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(function() {
        let rowCnt = <?= ($error && isset($_POST['obat_id'])) ? count($_POST['obat_id']) : 0 ?>;

        // Select2 Pasien
        $('#select_id_card').select2({
            placeholder: 'Cari nama/ID...',
            ajax: {
                url: 'api_karyawan.php',
                dataType: 'json',
                delay: 250,
                data: params => ({ query: params.term }),
                processResults: data => ({ results: data.results || [] })
            },
            minimumInputLength: 2
        }).on('select2:select', function(e) {
            const d = e.params.data;
            $('#hidden_id_card').val(d.id);
            location.href = '?id_card_selected=' + d.id;
        });

        // Load pasien (Koreksi Undefined)
        if ($('#hidden_id_card').val()) {
            $.get('api_karyawan.php?id_card=' + $('#hidden_id_card').val(), function(res) {
                if (res.results && res.results[0]) {
                    const d = res.results[0];
                    // Menggunakan d.text yang sudah diformat dari API
                    const select2_text = d.text; 
                    
                    $('#nama_display').text(d.nama);
                    
                    // Membuat option baru dengan text yang benar (menghilangkan undefined)
                    const opt = new Option(select2_text, d.id, true, true);
                    $('#select_id_card').append(opt).trigger('change');
                }
            }, 'json');
        }

        // Select2 Obat
        function initObat(sel, init = null) {
            $(sel).select2({
                placeholder: 'Cari obat...',
                ajax: {
                    url: 'api_obat.php',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ query: params.term }),
                    processResults: data => ({ results: data.results || [] })
                },
                minimumInputLength: 2
            }).on('select2:select', function(e) {
                const d = e.params.data;
                const row = $(this).closest('.resep-row');
                row.find('input[name^="jumlah"]').attr('max', d.stok);
                row.find('.stok-warning').html(d.stok <= 10 ? '<span class="text-danger">Stok rendah: ' + d.stok + ' ' + d.satuan + '</span>' : '');
            });
            if (init) {
                const opt = new Option(init.text.split(' - Stok:')[0], init.id, true, true);
                $(sel).append(opt).trigger('change');
            }
        }

        // Tambah row
        $('#add_resep_row').click(function() {
            rowCnt++;
            const row = `
                <div class="resep-row row g-3 align-items-end">
                    <div class="col-6">
                        <select class="form-control obat-select" name="obat_id[]" required></select>
                        <small class="stok-warning d-block mt-1"></small>
                    </div>
                    <div class="col-2">
                        <input type="number" class="form-control" name="jumlah[]" min="1" required>
                    </div>
                    <div class="col-2">
                        <input type="text" class="form-control" name="dosis[]" placeholder="Dosis" required>
                    </div>
                    <div class="col-1">
                        <input type="text" class="form-control" name="aturan_pakai[]" placeholder="Aturan" required>
                    </div>
                    <div class="col-1">
                        <button type="button" class="btn btn-danger btn-sm remove-row"><i class="ti ti-trash"></i></button>
                    </div>
                </div>`;
            $('#resep_container').append(row);
            initObat('#resep_container .obat-select:last');
        });

        // Hapus row
        $(document).on('click', '.remove-row', function() { $(this).closest('.resep-row').remove(); });

        // Re-init obat POST error (Koreksi tampilan obat)
        <?php if ($error && isset($_POST['obat_id'])): 
            foreach ($_POST['obat_id'] as $i => $oid): ?>
            (function(i, id) {
                $.get('api_obat.php?id=' + id, function(res) {
                    if (res.results && res.results[0]) {
                        const d = res.results[0];
                        // Inisialisasi Select2 dengan data lengkap
                        initObat('.obat-select:eq(' + i + ')', {id: d.id, text: d.text});
                        
                        // Perbarui stok warning
                        $('.stok-warning:eq(' + i + ')').html(d.stok <= 10 ? '<span class="text-danger">Stok rendah: ' + d.stok + ' ' + d.satuan + '</span>' : '');
                        $('.obat-select:eq(' + i + ')').closest('.resep-row').find('input[name^="jumlah"]').attr('max', d.stok);
                    }
                }, 'json');
            })(<?= $i ?>, '<?= htmlspecialchars($oid) ?>');
        <?php endforeach; endif; ?>
    });
    </script>
    <script src="../../assets/static/js/components/dark.js"></script>
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    
    
    <script src="../../assets/compiled/js/app.js"></script>
    
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    
</body>
</html>