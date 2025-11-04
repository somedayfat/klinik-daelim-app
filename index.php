<?php
// File: index.php (atau file lain yang perlu dilindungi)
session_start();
include('config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// Cek autentikasi
if (!isset($_SESSION['user_login']) || $_SESSION['level'] !== 'klinik') {
    header("Location: login.php");
    exit();
}

// Lanjutkan kode index.php di sini
// ...

// Tentukan rentang waktu untuk data dashboard (Default: Bulan Ini)
$tahun_bulan = date('Y-m');
$tgl_hari_ini = date('Y-m-d');
$tgl_awal_bulan = date('Y-m-01');
$tgl_akhir_bulan = date('Y-m-t');

// =========================================================
// --- FUNGSI-FUNGSI PENGAMBILAN DATA UNTUK DASHBOARD ---
// =========================================================

// 1. Fungsi Statistik Utama (Cards)
function get_stats($koneksi, $tgl_hari_ini, $tahun_bulan) {
    $stats = [
        'total_kunjungan_hari_ini' => 0,
        'total_kunjungan_bulan_ini' => 0,
        'total_karyawan' => 0,
        'total_low_stock' => 0,
        'total_kecelakaan_bulan_ini' => 0
    ];

    // Total Kunjungan Hari Ini
    $q1 = "SELECT COUNT(id) AS total FROM berobat WHERE DATE(tanggal_berobat) = '$tgl_hari_ini'";
    $r1 = mysqli_query($koneksi, $q1);
    $stats['total_kunjungan_hari_ini'] = $r1 ? mysqli_fetch_assoc($r1)['total'] : 0;

    // Total Kunjungan Bulan Ini
    $q2 = "SELECT COUNT(id) AS total FROM berobat WHERE DATE_FORMAT(tanggal_berobat, '%Y-%m') = '$tahun_bulan'";
    $r2 = mysqli_query($koneksi, $q2);
    $stats['total_kunjungan_bulan_ini'] = $r2 ? mysqli_fetch_assoc($r2)['total'] : 0;

    // Total Karyawan Aktif
    $q3 = "SELECT COUNT(id_card) AS total FROM karyawan"; // Asumsi semua di tabel karyawan aktif
    $r3 = mysqli_query($koneksi, $q3);
    $stats['total_karyawan'] = $r3 ? mysqli_fetch_assoc($r3)['total'] : 0;

    // Total Obat Stok Minimum
    $q4 = "SELECT COUNT(id) AS total FROM obat WHERE stok_tersedia <= stok_minimum";
    $r4 = mysqli_query($koneksi, $q4);
    $stats['total_low_stock'] = $r4 ? mysqli_fetch_assoc($r4)['total'] : 0;

    // Total Kecelakaan Bulan Ini (NEW STAT)
    $q5 = "SELECT COUNT(id) AS total FROM kecelakaan_kerja WHERE DATE_FORMAT(tanggal_kejadian, '%Y-%m') = '$tahun_bulan'";
    $r5 = mysqli_query($koneksi, $q5);
    $stats['total_kecelakaan_bulan_ini'] = $r5 ? mysqli_fetch_assoc($r5)['total'] : 0;
    
    return $stats;
}

// 2. Fungsi Data Kunjungan Harian (Line Chart)
function get_daily_visits_data($koneksi, $tgl_awal_bulan, $tgl_akhir_bulan) {
    $data_kunjungan = [];
    $labels = [];
    
    // Inisialisasi array untuk semua hari dalam bulan ini
    $start = new DateTime($tgl_awal_bulan);
    $end = new DateTime($tgl_akhir_bulan);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

    $all_days = [];
    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        $labels[] = $date->format('d');
        $all_days[$day] = 0;
    }

    // Query untuk mengambil total kunjungan per hari
    $query = "SELECT 
                DATE(tanggal_berobat) AS tanggal, 
                COUNT(id) AS total 
              FROM berobat 
              WHERE DATE(tanggal_berobat) BETWEEN '$tgl_awal_bulan' AND '$tgl_akhir_bulan'
              GROUP BY tanggal 
              ORDER BY tanggal ASC";
              
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $all_days[$row['tanggal']] = (int)$row['total'];
        }
    }

    // Ambil data dalam urutan tanggal
    foreach ($all_days as $total) {
        $data_kunjungan[] = $total;
    }

    return [
        'labels' => json_encode($labels),
        'data' => json_encode($data_kunjungan),
        'has_data' => array_sum($data_kunjungan) > 0
    ];
}

// 3. Fungsi Alert Stok Minimum (Table)
function get_stock_alerts($koneksi) {
    $alerts = [];
    $query = "SELECT kode_obat, nama_obat, stok_tersedia, satuan, stok_minimum 
              FROM obat 
              WHERE stok_tersedia <= stok_minimum 
              ORDER BY nama_obat ASC 
              LIMIT 5";
    $result = mysqli_query($koneksi, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $alerts[] = $row;
        }
    }
    return $alerts;
}

// 4. Fungsi Pengunjung Paling Sering (Table)
function get_frequent_visitors($koneksi) {
    $visitors = [];
    $query = "SELECT 
                b.id_card, 
                k.nama, 
                k.departemen,
                COUNT(b.id) AS total_kunjungan 
              FROM berobat b
              JOIN karyawan k ON b.id_card = k.id_card
              GROUP BY b.id_card, k.nama, k.departemen
              ORDER BY total_kunjungan DESC 
              LIMIT 5";
    $result = mysqli_query($koneksi, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $visitors[] = $row;
        }
    }
    return $visitors;
}

// 5. Fungsi Tren Diagnosis Terbanyak (Bar Chart)
function get_diagnosis_trend($koneksi, $tgl_awal, $tgl_akhir) {
    $data_tren = [];
    $labels = [];
    $data = [];
    
    // Ambil 5 diagnosis terbanyak dalam rentang waktu
    $query = "SELECT 
                diagnosis,
                COUNT(id) AS total_kasus
              FROM berobat
              WHERE DATE(tanggal_berobat) BETWEEN '$tgl_awal' AND '$tgl_akhir'
              GROUP BY diagnosis
              ORDER BY total_kasus DESC
              LIMIT 5";
              
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data_tren[] = $row;
            $labels[] = htmlspecialchars($row['diagnosis']);
            $data[] = (int)$row['total_kasus'];
        }
    }
    
    return [
        'data_tren' => $data_tren,
        'labels' => json_encode($labels),
        'data' => json_encode($data),
        'has_data' => count($data_tren) > 0
    ];
}


// 6. Fungsi Pengambilan Data Tren Kecelakaan Area/Departemen (NEW)
function get_accident_risk_trend($koneksi, $tgl_awal, $tgl_akhir) {
    $data_tren = [];
    $labels = [];
    $data = [];
    
    // Ambil 5 Area Kecelakaan Terbanyak (Diasumsikan kolom yang dianalisis adalah 'lokasi_kejadian')
    // Jika Anda ingin menganalisis berdasarkan 'Departemen' karyawan, ganti: kk.lokasi_kejadian -> k.departemen
    $query = "SELECT 
                kk.lokasi_kejadian,
                COUNT(kk.id) AS total_kasus
              FROM kecelakaan_kerja kk
              WHERE DATE(kk.tanggal_kejadian) BETWEEN '$tgl_awal' AND '$tgl_akhir'
              GROUP BY kk.lokasi_kejadian
              ORDER BY total_kasus DESC
              LIMIT 5";
              
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data_tren[] = $row;
            $labels[] = htmlspecialchars($row['lokasi_kejadian']);
            $data[] = (int)$row['total_kasus'];
        }
    }
    
    return [
        'data_tren' => $data_tren,
        'labels' => json_encode($labels),
        'data' => json_encode($data),
        'has_data' => count($data_tren) > 0
    ];
}


// =========================================================
// --- EKSEKUSI FUNGSI DAN PENGUMPULAN DATA ---
// =========================================================

// Jalankan semua fungsi
$stats = get_stats($koneksi, $tgl_hari_ini, $tahun_bulan);
$chart_data_visits = get_daily_visits_data($koneksi, $tgl_awal_bulan, $tgl_akhir_bulan);
$stock_alerts = get_stock_alerts($koneksi);
$frequent_visitors = get_frequent_visitors($koneksi);
// Ambil data tren untuk bulan ini
$diagnosis_trend = get_diagnosis_trend($koneksi, $tgl_awal_bulan, $tgl_akhir_bulan);
$accident_trend_data = get_accident_risk_trend($koneksi, $tgl_awal_bulan, $tgl_akhir_bulan);
// Hitung total kasus untuk persentase Diagnosis
$total_kasus_diagnosis = array_sum(array_column($diagnosis_trend['data_tren'], 'total_kasus'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Klinik PT. Daelim Indonesia</title>
    
    <link rel="shortcut icon" href="assets/static/images/logo/favicon.svg" type="image/x-icon">
    <link rel="stylesheet" href="assets/compiled/css/app.css">
    <link rel="stylesheet" href="assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="assets/compiled/css/iconly.css">
    </head>

<body>
    <script src="assets/static/js/initTheme.js"></script>
    <div id="app">
         <div id="sidebar"></div>
        <!-- batas copy -->
            <div class="sidebar-wrapper active">
    <div class="sidebar-header position-relative">
        <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
                <a href=""><img src="assets/images/logo.PNG" alt="Logo" srcset=""></a>
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
                class="sidebar-item has-sub ">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                <ul class="submenu ">
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_berobat.php" class="submenu-link">Laporan Berobat</a>                     
                    </li>       
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_obat.php" class="submenu-link">Laporan Obat</a>                     
                    </li>         
                    <li class="submenu-item  ">
                        <a href="pages/laporan/form_laporan_bulanan.php" class="submenu-link">Laporan Kecelakaan Kerja</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_tren_berobat.php" class="submenu-link">Statistik Berobat</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_tren_kecelakaan.php" class="submenu-link">Statistik Kecelakaan Kerja</a>
                    </li>
                </ul>
            </li>
            
            <li
                class="sidebar-item">
                <a href="logout.php" class='sidebar-link'>
                    <i class="bi bi-person-circle"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
            
            <div class="page-heading">
                <h3>Dashboard Klinik</h3>
            </div>
        <div class="card">
        <section class="row">
            <!-- <div class="col-12 col-lg-9"> -->
            <div id="carouselExampleSlidesOnly" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <div class="carousel-item active">
                  <img src="assets/static/images/carousel-1.png" class="d-block w-100" alt="...">
                </div>
                <div class="carousel-item">
                  <img src="assets/static/images/carousel-2.png" class="d-block w-100" alt="...">
                </div>
                <div class="carousel-item">
                  <img src="assets/static/images/carousel-3.png" class="d-block w-100" alt="...">
                </div>
              </div>
            </div>
        </section>
          </div>
            <div class="page-content">
                <section class="row">
                    <div class="col-12 col-lg-9">
                        <div class="row">
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-4 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-4 d-flex justify-content-start ">
                                                <div class="stats-icon purple mb-2">
                                                    <i class="iconly-boldProfile"></i>
                                                </div>
                                            </div>
                                       <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-8">
                                                <h6 class="text-muted font-semibold">Kunjungan Hari Ini</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($stats['total_kunjungan_hari_ini'], 0, ',', '.') ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-4 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-4 d-flex justify-content-start ">
                                                <div class="stats-icon blue mb-2">
                                                    <i class="iconly-boldShield-Done"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-8">
                                                <h6 class="text-muted font-semibold">Kunjungan Bulan Ini</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($stats['total_kunjungan_bulan_ini'], 0, ',', '.') ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-4 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-4 d-flex justify-content-start ">
                                                <div class="stats-icon red mb-2">
                                                    <i class="iconly-boldDanger"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-8">
                                                <h6 class="text-muted font-semibold">Kecelakaan Bln Ini</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($stats['total_kecelakaan_bulan_ini'], 0, ',', '.') ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-4 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-4 d-flex justify-content-start ">
                                                <div class="stats-icon green mb-2">
                                                    <i class="iconly-boldBag"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-8">
                                                <h6 class="text-muted font-semibold">Stok Minimum</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($stats['total_low_stock'], 0, ',', '.') ?> Item</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Tren Kunjungan Harian (Bulan Ini: <?= date('F Y') ?>)</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-daily-visits"></div>
                                        <?php if (!$chart_data_visits['has_data']): ?>
                                            <div class="alert alert-light-warning text-center mt-3 mb-0">
                                                Tidak ada data kunjungan untuk bulan ini.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>5 Diagnosis Terbanyak (Bulan Ini)</h4>
                                        <a href="pages/laporan/laporan_tren_berobat.php" class="btn btn-sm btn-outline-primary float-end">Lihat Laporan Lengkap</a>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-diagnosis-trend"></div>
                                        <?php if ($diagnosis_trend['has_data']): ?>
                                            <div class="mt-3">
                                                <h6 class="text-end">Total Kasus: <span class="text-primary"><?= number_format($total_kasus_diagnosis) ?></span></h6>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-light-warning text-center mt-3 mb-0">
                                                Tidak ada data diagnosis untuk bulan ini.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>5 Area Risiko Kecelakaan Tertinggi (Bulan Ini)</h4>
                                        <a href="pages/laporan/laporan_tren_kecelakaan.php" class="btn btn-sm btn-outline-danger float-end">Lihat Laporan Lengkap</a>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-accident-risk-trend"></div>
                                        <?php if ($accident_trend_data['has_data']): ?>
                                            <div class="mt-3">
                                                <h6 class="text-end">Total Kecelakaan: <span class="text-danger"><?= number_format(array_sum(array_column($accident_trend_data['data_tren'], 'total_kasus'))) ?></span></h6>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-light-warning text-center mt-3 mb-0">
                                                Tidak ada data kecelakaan kerja untuk bulan ini.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div class="col-12 col-lg-3">
                        
                        <div class="card">
                            <div class="card-header">
                                <h4>Alert Stok Minimum</h4>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($stock_alerts)): ?>
                                        <?php foreach ($stock_alerts as $alert): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0 text-danger"><?= htmlspecialchars($alert['nama_obat']) ?></h6>
                                                    <small class="text-muted">Min: <?= number_format($alert['stok_minimum']) ?></small>
                                                </div>
                                                <span class="badge bg-light-danger text-danger badge-pill">
                                                    Sisa: <?= number_format($alert['stok_tersedia']) ?> <?= htmlspecialchars($alert['satuan']) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center text-success">
                                            <i class="bi bi-check-circle me-1"></i> Semua stok aman!
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <a href="pages/obat/master_obat.php" class="btn btn-sm btn-block btn-outline-primary font-bold mt-3">Kelola Stok</a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>5 Pengunjung Paling Sering</h4>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($frequent_visitors)): ?>
                                        <?php foreach ($frequent_visitors as $visitor): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($visitor['nama']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($visitor['departemen']) ?></small>
                                                </div>
                                                <span class="badge bg-light-primary badge-pill">
                                                    <?= number_format($visitor['total_kunjungan']) ?>x
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center text-muted">
                                            Belum ada riwayat kunjungan.
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <a href="pages/berobat/riwayat_berobat.php" class="btn btn-sm btn-block btn-outline-info font-bold mt-3">Lihat Riwayat Lengkap</a>
                            </div>
                        </div>

                    </div>
                </section>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>2025 &copy; Daelim</p>
                    </div>
                    <div class="float-end">
                        <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span>
                            by <a href="https://daelim.id">IT PT. Daelim Indonesia</a></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <script src="assets/static/js/components/dark.js"></script>
    <script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/compiled/js/app.js"></script>

    <script src="assets/extensions/apexcharts/apexcharts.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", () => {

        // =========================================================
        // 1. CHART KUNJUNGAN HARIAN (Line Chart)
        // =========================================================
        <?php if ($chart_data_visits['has_data']): ?>
        const chartLabelsVisits = <?= $chart_data_visits['labels'] ?>;
        const chartDataVisits = <?= $chart_data_visits['data'] ?>;

        const optionsDailyVisits = {
            series: [{
                name: "Total Kunjungan",
                data: chartDataVisits,
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: { show: false },
            },
            stroke: { curve: 'smooth' },
            xaxis: {
                categories: chartLabelsVisits,
                title: { text: 'Tanggal' }
            },
            yaxis: {
                title: { text: 'Jumlah Kunjungan' },
                min: 0,
                tickAmount: 5 // Batasi jumlah tick Y-axis
            },
            tooltip: {
                x: { format: 'dd' },
                y: { formatter: (val) => val.toFixed(0) + " Kunjungan" }
            }
        };

        new ApexCharts(document.querySelector("#chart-daily-visits"), optionsDailyVisits).render();
        <?php endif; ?>

        // =========================================================
        // 2. CHART TREN DIAGNOSIS (Bar Chart)
        // =========================================================
        <?php if ($diagnosis_trend['has_data']): ?>
        const chartLabelsDiagnosis = <?= $diagnosis_trend['labels'] ?>;
        const chartDataDiagnosis = <?= $diagnosis_trend['data'] ?>;

        const optionsDiagnosis = {
            series: [{
                name: "Jumlah Kasus",
                data: chartDataDiagnosis
            }],
            chart: {
                type: 'bar',
                height: 350
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: chartLabelsDiagnosis,
                title: { text: 'Jumlah Kasus' }
            }
        };

        new ApexCharts(document.querySelector("#chart-diagnosis-trend"), optionsDiagnosis).render();
        <?php endif; ?>


        // =========================================================
        // 3. CHART TREN RISIKO KECELAKAAN (Bar Chart - NEW)
        // =========================================================
        <?php if ($accident_trend_data['has_data']): ?>
        const chartLabelsAccident = <?= $accident_trend_data['labels'] ?>;
        const chartDataAccident = <?= $accident_trend_data['data'] ?>;

        const optionsAccident = {
            series: [{
                name: "Total Kecelakaan",
                data: chartDataAccident
            }],
            chart: {
                type: 'bar',
                height: 350
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                    colors: {
                        ranges: [{
                            from: 0,
                            to: 1000,
                            color: '#dc3545' // Warna Merah untuk Risiko
                        }]
                    }
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: chartLabelsAccident,
                title: { text: 'Jumlah Kecelakaan' }
            },
            // Tambahkan warna merah spesifik
            colors: ['#dc3545']
        };

        new ApexCharts(document.querySelector("#chart-accident-risk-trend"), optionsAccident).render();
        <?php endif; ?>

    });
    </script>
</body>

</html>