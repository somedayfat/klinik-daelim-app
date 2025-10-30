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