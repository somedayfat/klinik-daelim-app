<?php
// File: form_laporan_bulanan.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// --- PENGAMBILAN DATA AWAL UNTUK FILTER ---
$departemen_list = [];
$result_dept = mysqli_query($koneksi, "SELECT DISTINCT departemen FROM karyawan ORDER BY departemen ASC");
while ($row = mysqli_fetch_assoc($result_dept)) {
    $departemen_list[] = $row['departemen'];
}

// --- INISIALISASI FILTER ---
$filter_bulan = $_GET['bulan'] ?? date('Y-m'); // Default ke bulan saat ini
$filter_departemen = $_GET['departemen'] ?? '';
$data_kecelakaan_detail = [];
$statistik = [];
$analisis_jenis = [];

if (isset($_GET['filter']) || isset($_GET['bulan'])) {
    
    // 1. Tentukan Kondisi WHERE
    $where_clauses = [];
    
    // Filter Bulan (Wajib)
    $bulan_safe = mysqli_real_escape_string($koneksi, $filter_bulan);
    $where_clauses[] = "DATE_FORMAT(kk.tanggal_kejadian, '%Y-%m') = '$bulan_safe'";
    
    // Filter Departemen (Opsional)
    if (!empty($filter_departemen)) {
        $dept_safe = mysqli_real_escape_string($koneksi, $filter_departemen);
        $where_clauses[] = "k.departemen = '$dept_safe'";
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

    // 2. QUERY UNTUK STATISTIK KUNCI (Aggregate Data)
    $query_statistik = "
        SELECT 
            COUNT(kk.id) AS total_insiden,
            SUM(kk.lama_istirahat) AS total_hari_hilang,
            IFNULL(SUM(kk.lama_istirahat) / COUNT(kk.id), 0) AS rerata_hari_hilang
        FROM 
            kecelakaan_kerja kk
        JOIN 
            karyawan k ON kk.id_card = k.id_card
        $where_sql";
    
    $result_statistik = mysqli_query($koneksi, $query_statistik);
    $statistik = mysqli_fetch_assoc($result_statistik);
    
    // 3. QUERY UNTUK ANALISIS JENIS KECELAKAAN (Analytic Data)
    $query_analisis = "
        SELECT 
            kk.jenis_kecelakaan,
            COUNT(kk.id) AS jumlah
        FROM 
            kecelakaan_kerja kk
        JOIN 
            karyawan k ON kk.id_card = k.id_card
        $where_sql
        GROUP BY 
            kk.jenis_kecelakaan
        ORDER BY 
            jumlah DESC";
            
    $result_analisis = mysqli_query($koneksi, $query_analisis);
    while ($row = mysqli_fetch_assoc($result_analisis)) {
        $analisis_jenis[] = $row;
    }

    // 4. QUERY UNTUK DETAIL LAPORAN (Detailed Data)
    $query_detail = "
        SELECT 
            kk.*, 
            k.nama, 
            k.departemen 
        FROM 
            kecelakaan_kerja kk
        JOIN 
            karyawan k ON kk.id_card = k.id_card
        $where_sql
        ORDER BY 
            kk.tanggal_kejadian ASC";

    $result_detail = mysqli_query($koneksi, $query_detail);
    while ($row = mysqli_fetch_assoc($result_detail)) {
        $data_kecelakaan_detail[] = $row;
    }

    // Ubah data analisis ke format JSON untuk chart
    $chart_labels = json_encode(array_column($analisis_jenis, 'jenis_kecelakaan'));
    $chart_data = json_encode(array_column($analisis_jenis, 'jumlah'));

    // Format judul laporan
    $nama_bulan = date('F Y', strtotime($filter_bulan . '-01'));
    $nama_departemen = $filter_departemen ?: 'Semua Departemen';
    $judul_laporan = "Laporan Kecelakaan Kerja Bulan " . $nama_bulan . " (" . $nama_departemen . ")";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Bulanan Kecelakaan Kerja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        @media print {
            #sidebar, .navbar, .page-heading p, .card-header a, .filter-form, .btn-print, footer, .modal-backdrop {
                display: none;
            }
            .card-body, .card {
                box-shadow: none !important;
                border: none !important;
            }
            #main {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }
        }
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
                                    <a href="../berobat/riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
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
                        <a href="../laporan/laporan_obat.php" class="submenu-link">Laporan Obat</a>                     
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
    <div id="app">
        <div id="sidebar"></div>

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Rekapitulasi Laporan Kecelakaan Kerja</h3>
                <p class="text-subtitle text-muted">Statistik insiden dan analisis kecelakaan per periode.</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title">Filter Laporan</h4>
                    </div>
                    <div class="card-body filter-form">
                        <form action="form_laporan_bulanan.php" method="GET" class="row g-3">
                            <input type="hidden" name="filter" value="1">
                            
                            <div class="col-md-5">
                                <label class="form-label">Pilih Bulan/Tahun *</label>
                                <input type="month" class="form-control" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>" required>
                            </div>
                            
                            <div class="col-md-5">
                                <label class="form-label">Departemen</label>
                                <select class="form-select" name="departemen">
                                    <option value="">-- Semua Departemen --</option>
                                    <?php foreach ($departemen_list as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept) ?>" <?= $filter_departemen == $dept ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 me-1"><i class="bi bi-bar-chart-fill"></i> Tampilkan Laporan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <?php if (!empty($statistik)): ?>
            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title">Laporan: <?= $judul_laporan ?></h4>
                        <button class="btn btn-secondary btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Laporan</button>
                    </div>
                    
                    <div class="card-body">
                        
                        <h5 class="mt-2 mb-3 text-primary"><i class="bi bi-key-fill me-1"></i> Statistik Utama</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-info text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_insiden'] ?></h4>
                                    <p class="mb-0">Total Insiden Kecelakaan</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_hari_hilang'] ?> Hari</h4>
                                    <p class="mb-0">Total Hari Hilang Kerja (Lost Days)</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success text-center">
                                    <h4 class="fw-bold"><?= number_format($statistik['rerata_hari_hilang'], 2) ?> Hari</h4>
                                    <p class="mb-0">Rerata Hari Hilang per Insiden</p>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-graph-up me-1"></i> Analisis Jenis Kecelakaan</h5>
                                <div style="height: 350px;">
                                    <canvas id="jenisKecelakaanChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-table me-1"></i> Detail Laporan (<?= count($data_kecelakaan_detail) ?> Insiden)</h5>
                        
                        <?php if (empty($data_kecelakaan_detail)): ?>
                            <div class="alert alert-secondary text-center">Tidak ada data kecelakaan untuk periode filter ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tgl Kejadian</th>
                                            <th>Nama Karyawan</th>
                                            <th>Dept</th>
                                            <th>Jenis Kecelakaan</th>
                                            <th>Bagian Terluka</th>
                                            <th>Status</th>
                                            <th>Lama Istirahat (Hari)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($data_kecelakaan_detail as $d): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['tanggal_kejadian'])) ?></td>
                                            <td><?= htmlspecialchars($d['nama']) ?></td>
                                            <td><?= htmlspecialchars($d['departemen']) ?></td>
                                            <td><?= htmlspecialchars($d['jenis_kecelakaan']) ?></td>
                                            <td><?= htmlspecialchars($d['bagian_tubuh']) ?></td>
                                            <td><span class="badge bg-<?= ($d['status'] == 'Rujuk Rumah Sakit') ? 'warning' : 'success' ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                                            <td><?= htmlspecialchars($d['lama_istirahat']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="7" class="text-end fw-bold">TOTAL HARI HILANG</td>
                                            <td class="fw-bold"><?= $statistik['total_hari_hilang'] ?> Hari</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </section>
            <?php endif; ?>
            
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>

    <?php if (!empty($analisis_jenis)): ?>
    <script>
        // Data dari PHP
        const chartLabels = <?= $chart_labels ?>;
        const chartData = <?= $chart_data ?>;

        const ctx = document.getElementById('jenisKecelakaanChart').getContext('2d');
        const jenisKecelakaanChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Insiden',
                    data: chartData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Insiden'
                        },
                        // Pastikan skala Y adalah bilangan bulat
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Distribusi Jenis Kecelakaan'
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>