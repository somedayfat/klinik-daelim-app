<?php
// File: laporan_berobat.php (VERSI PERBAIKAN)
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
$filter_bulan = $_GET['bulan'] ?? date('Y-m'); 
$filter_departemen = $_GET['departemen'] ?? '';
$data_berobat_detail = [];
$statistik = [];
$analisis_diagnosa = [];
$judul_laporan = "Laporan Kunjungan Medis (Belum Difilter)";
$error_db = null; // Variable untuk menampung error

if (isset($_GET['filter']) || isset($_GET['bulan'])) {
    
    $where_clauses = [];
    $bulan_safe = mysqli_real_escape_string($koneksi, $filter_bulan);
    $where_clauses[] = "DATE_FORMAT(b.tanggal_berobat, '%Y-%m') = '$bulan_safe'";
    
    if (!empty($filter_departemen)) {
        $dept_safe = mysqli_real_escape_string($koneksi, $filter_departemen);
        $where_clauses[] = "k.departemen = '$dept_safe'";
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

    // 1. QUERY UNTUK STATISTIK KUNCI
    $query_statistik = "
        SELECT 
            COUNT(b.id) AS total_kunjungan, 
            COUNT(DISTINCT b.id_card) AS total_pasien_unik
        FROM 
            berobat b
        JOIN 
            karyawan k ON b.id_card = k.id_card
        $where_sql";
    
    $result_statistik = mysqli_query($koneksi, $query_statistik);
    if ($result_statistik) {
        $statistik = mysqli_fetch_assoc($result_statistik);
    } else {
        $error_db = "Gagal mengambil statistik: " . mysqli_error($koneksi);
    }
    
    // 2. QUERY UNTUK ANALISIS DIAGNOSA TERBANYAK
    $query_analisis = "
        SELECT 
            b.diagnosis,
            COUNT(b.id) AS jumlah
        FROM 
            berobat b
        JOIN 
            karyawan k ON b.id_card = k.id_card
        $where_sql
        GROUP BY 
            b.diagnosis
        ORDER BY 
            jumlah DESC LIMIT 5";
            
    $result_analisis = mysqli_query($koneksi, $query_analisis);
    if ($result_analisis) {
        while ($row = mysqli_fetch_assoc($result_analisis)) {
            $analisis_diagnosa[] = $row;
        }
    } else {
        $error_db = "Gagal mengambil analisis diagnosa: " . mysqli_error($koneksi);
    }

    // 3. QUERY UNTUK DETAIL LAPORAN
    $query_detail = "
        SELECT 
            b.tanggal_berobat, 
            k.nama, 
            k.departemen, 
            b.keluhan,             /* Kolom KELUHAN dipertahankan */
            b.diagnosis, 
            b.tekanan_darah, 
            b.suhu_tubuh, 
            b.rujukan, 
            b.petugas 
        FROM 
            berobat b
        JOIN 
            karyawan k ON b.id_card = k.id_card
        $where_sql
        ORDER BY 
            b.tanggal_berobat ASC";

    $result_detail = mysqli_query($koneksi, $query_detail);
    if ($result_detail) {
        while ($row = mysqli_fetch_assoc($result_detail)) {
            $data_berobat_detail[] = $row;
        }
    } else {
        $error_db = "Gagal mengambil detail kunjungan: " . mysqli_error($koneksi);
    }

    $chart_labels = json_encode(array_column($analisis_diagnosa, 'diagnosis'));
    $chart_data = json_encode(array_column($analisis_diagnosa, 'jumlah'));

    $nama_bulan = date('F Y', strtotime($filter_bulan . '-01'));
    $nama_departemen = $filter_departemen ?: 'Semua Departemen';
    $judul_laporan = "Laporan Kunjungan Medis Bulan " . $nama_bulan . " (" . $nama_departemen . ")";
}

// --- LOGIKA EKSPOR EXCEL (CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'export' && isset($_GET['bulan'])) {
    
    $query_export = $query_detail; // Gunakan query detail yang sudah difilter

    $result_export = mysqli_query($koneksi, $query_export);
    
    $filename = "Laporan_Berobat_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, [
        'Tanggal Kunjungan', 
        'Nama Karyawan', 
        'Departemen', 
        'Keluhan Utama', 
        'Diagnosis', 
        'Tekanan Darah', 
        'Suhu Tubuh', 
        'Rujukan', 
        'Petugas'
    ]);
    
    while ($row = mysqli_fetch_assoc($result_export)) {
        fputcsv($output, [
            $row['tanggal_berobat'],
            $row['nama'],
            $row['departemen'],
            $row['keluhan'],
            $row['diagnosis'],
            $row['tekanan_darah'],
            $row['suhu_tubuh'],
            $row['rujukan'],
            $row['petugas']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Kunjungan Medis</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        @media print {
            #sidebar, .navbar, .page-heading p, .filter-form, .btn-print, footer { display: none; }
            .card-body, .card { box-shadow: none !important; border: none !important; }
            #main { margin-left: 0 !important; padding-top: 0 !important; }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Laporan Kunjungan Medis</h3>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">Filter Data Kunjungan</h4></div>
                    <div class="card-body filter-form">
                        <form action="laporan_berobat.php" method="GET" class="row g-3">
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
                                <button type="submit" class="btn btn-primary w-100 me-1"><i class="bi bi-search"></i> Tampilkan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <?php if ($error_db): ?>
                <div class="alert alert-danger">
                    <strong>Error Database:</strong> Terdapat kesalahan saat menjalankan query. Pesan: <?= $error_db ?>
                    <br>Mohon periksa nama kolom (terutama `id_berobat`, `id`, dan `keluhan`) di tabel Anda.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($statistik) && !$error_db): ?>
            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title"><?= $judul_laporan ?></h4>
                        <div>
                            <button class="btn btn-secondary btn-sm me-1 btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                            <a href="laporan_berobat.php?action=export&bulan=<?= $filter_bulan ?>&departemen=<?= $filter_departemen ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        
                        <h5 class="mt-2 mb-3 text-primary"><i class="bi bi-key-fill me-1"></i> Statistik Utama</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="alert alert-info text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_kunjungan'] ?? 0 ?> Kali</h4>
                                    <p class="mb-0">Total Kunjungan Medis</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-success text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_pasien_unik'] ?? 0 ?> Orang</h4>
                                    <p class="mb-0">Total Pasien Unik (Karyawan)</p>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-bar-chart-fill me-1"></i> Top 5 Diagnosa Terbanyak</h5>
                                <div style="height: 350px;">
                                    <canvas id="diagnosaChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-table me-1"></i> Detail Kunjungan (<?= count($data_berobat_detail) ?> Data)</h5>
                        
                        <?php if (empty($data_berobat_detail)): ?>
                            <div class="alert alert-secondary text-center">Tidak ada data kunjungan untuk periode filter ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tgl Kunjungan</th>
                                            <th>Nama Karyawan</th>
                                            <th>Dept</th>
                                            <th>Keluhan</th>
                                            <th>Diagnosis</th>
                                            <th>Rujukan</th>
                                            <th>Petugas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_berobat_detail as $d): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($d['tanggal_berobat'])) ?></td>
                                            <td><?= htmlspecialchars($d['nama']) ?></td>
                                            <td><?= htmlspecialchars($d['departemen']) ?></td>
                                            <td><?= htmlspecialchars($d['keluhan'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($d['diagnosis']) ?></td>
                                            <td><?= htmlspecialchars($d['rujukan'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($d['petugas']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
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

    <?php if (!empty($analisis_diagnosa)): ?>
    <script>
        const chartLabels = <?= $chart_labels ?>;
        const chartData = <?= $chart_data ?>;

        const ctx = document.getElementById('diagnosaChart').getContext('2d');
        const diagnosaChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Kunjungan',
                    data: chartData,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', 
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Kunjungan'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Frekuensi Diagnosa Terbanyak' }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>