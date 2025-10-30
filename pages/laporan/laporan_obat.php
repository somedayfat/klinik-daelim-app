<?php
// File: laporan_obat.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// --- INISIALISASI FILTER ---
$filter_tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$filter_tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');
$data_mutasi_detail = [];
$statistik = ['total_masuk' => 0, 'total_keluar' => 0, 'total_biaya_keluar' => 0];
$analisis_keluar = [];
$judul_laporan = "Laporan Mutasi Obat (Belum Difilter)";

if (isset($_GET['filter']) || isset($_GET['tgl_awal'])) {
    
    $tgl_awal_safe = mysqli_real_escape_string($koneksi, $filter_tgl_awal) . " 00:00:00";
    $tgl_akhir_safe = mysqli_real_escape_string($koneksi, $filter_tgl_akhir) . " 23:59:59";
    
    $where_sql = "WHERE t.tanggal_transaksi BETWEEN '$tgl_awal_safe' AND '$tgl_akhir_safe'";

    // 1. QUERY UNTUK DETAIL MUTASI & HITUNG BIAYA KELUAR
    $query_detail = "
        SELECT 
            t.tanggal_transaksi, 
            o.kode_obat, 
            o.nama_obat, 
            t.jenis_transaksi, 
            t.jumlah, 
            o.harga_satuan, 
            (CASE WHEN t.jenis_transaksi IN ('Keluar', 'Penyesuaian', 'Kadaluerasa') THEN t.jumlah * o.harga_satuan ELSE 0 END) AS biaya_keluar_estimasi,
            t.stok_sebelum, 
            t.stok_sesudah, 
            t.keterangan, 
            t.petugas
        FROM 
            transaksi_obat t
        JOIN 
            obat o ON t.obat_id = o.id
        $where_sql
        ORDER BY 
            t.tanggal_transaksi ASC";

    $result_detail = mysqli_query($koneksi, $query_detail);
    
    $total_biaya_keluar = 0;
    while ($row = mysqli_fetch_assoc($result_detail)) {
        // Hitung total masuk dan keluar untuk statistik
        if ($row['jenis_transaksi'] == 'Masuk') {
            $statistik['total_masuk'] += $row['jumlah'];
        } elseif (in_array($row['jenis_transaksi'], ['Keluar', 'Penyesuaian', 'Kadaluerasa'])) {
            $statistik['total_keluar'] += abs($row['jumlah']); // Jumlah keluar di DB negatif, kita ambil nilai positifnya
            $total_biaya_keluar += $row['biaya_keluar_estimasi'];
        }
        $data_mutasi_detail[] = $row;
    }
    $statistik['total_biaya_keluar'] = $total_biaya_keluar;
    
    // Reset result pointer untuk Analisis
    mysqli_data_seek($result_detail, 0); 

    // 2. QUERY UNTUK ANALISIS OBAT TERLARIS (Keluar)
    $query_analisis = "
        SELECT 
            o.nama_obat, 
            SUM(ABS(t.jumlah)) AS jumlah_keluar
        FROM 
            transaksi_obat t
        JOIN 
            obat o ON t.obat_id = o.id
        $where_sql
        AND t.jenis_transaksi IN ('Keluar', 'Penyesuaian', 'Kadaluerasa')
        GROUP BY 
            o.nama_obat
        ORDER BY 
            jumlah_keluar DESC LIMIT 5";
            
    $result_analisis = mysqli_query($koneksi, $query_analisis);
    while ($row = mysqli_fetch_assoc($result_analisis)) {
        $analisis_keluar[] = $row;
    }

    $chart_labels = json_encode(array_column($analisis_keluar, 'nama_obat'));
    $chart_data = json_encode(array_column($analisis_keluar, 'jumlah_keluar'));

    $judul_laporan = "Laporan Mutasi Obat (" . date('d/m/Y', strtotime($filter_tgl_awal)) . " s/d " . date('d/m/Y', strtotime($filter_tgl_akhir)) . ")";
}

// --- LOGIKA EKSPOR EXCEL (CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'export' && isset($_GET['tgl_awal'])) {
    // Re-run query detail untuk ekspor
    $result_export = mysqli_query($koneksi, $query_detail);
    
    $filename = "Laporan_Mutasi_Obat_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Tanggal Transaksi', 
        'Kode Obat', 
        'Nama Obat', 
        'Jenis Transaksi', 
        'Jumlah (Unit)', 
        'Harga Satuan',
        'Biaya Keluar (Estimasi)',
        'Stok Sebelum', 
        'Stok Sesudah', 
        'Keterangan', 
        'Petugas'
    ]);
    
    while ($row = mysqli_fetch_assoc($result_export)) {
        fputcsv($output, [
            $row['tanggal_transaksi'],
            $row['kode_obat'],
            $row['nama_obat'],
            $row['jenis_transaksi'],
            abs($row['jumlah']), // Absolutkan jumlah
            $row['harga_satuan'],
            $row['biaya_keluar_estimasi'],
            $row['stok_sebelum'],
            $row['stok_sesudah'],
            $row['keterangan'],
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
    <title>Laporan Mutasi Stok Obat</title>
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
                <h3>Laporan Mutasi Stok Obat</h3>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">Filter Data Mutasi</h4></div>
                    <div class="card-body filter-form">
                        <form action="laporan_obat.php" method="GET" class="row g-3">
                            <input type="hidden" name="filter" value="1">
                            
                            <div class="col-md-5">
                                <label class="form-label">Tanggal Awal *</label>
                                <input type="date" class="form-control" name="tgl_awal" value="<?= htmlspecialchars($filter_tgl_awal) ?>" required>
                            </div>
                            
                            <div class="col-md-5">
                                <label class="form-label">Tanggal Akhir *</label>
                                <input type="date" class="form-control" name="tgl_akhir" value="<?= htmlspecialchars($filter_tgl_akhir) ?>" required>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 me-1"><i class="bi bi-search"></i> Tampilkan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <?php if (isset($_GET['filter'])): ?>
            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title"><?= $judul_laporan ?></h4>
                        <div>
                            <button class="btn btn-secondary btn-sm me-1 btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                            <a href="laporan_obat.php?action=export&tgl_awal=<?= $filter_tgl_awal ?>&tgl_akhir=<?= $filter_tgl_akhir ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        
                        <h5 class="mt-2 mb-3 text-primary"><i class="bi bi-box-seam-fill me-1"></i> Rekap Stok & Biaya</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-success text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_masuk'] ?> Unit</h4>
                                    <p class="mb-0">Total Stok Masuk (Unit)</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-danger text-center">
                                    <h4 class="fw-bold"><?= $statistik['total_keluar'] ?> Unit</h4>
                                    <p class="mb-0">Total Stok Keluar/Kadaluarsa (Unit)</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning text-center">
                                    <h4 class="fw-bold">Rp <?= number_format($statistik['total_biaya_keluar'], 0, ',', '.') ?></h4>
                                    <p class="mb-0">Biaya Keluar (Estimasi)</p>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-graph-up me-1"></i> Top 5 Obat Paling Banyak Keluar</h5>
                                <div style="height: 350px;">
                                    <canvas id="obatKeluarChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <hr>
                        
                        <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-table me-1"></i> Detail Mutasi (<?= count($data_mutasi_detail) ?> Data)</h5>
                        
                        <?php if (empty($data_mutasi_detail)): ?>
                            <div class="alert alert-secondary text-center">Tidak ada data mutasi obat untuk periode filter ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Nama Obat</th>
                                            <th>Jenis Transaksi</th>
                                            <th>Jumlah</th>
                                            <th>Stok Awal</th>
                                            <th>Stok Akhir</th>
                                            <th>Biaya Keluar (Est.)</th>
                                            <th>Keterangan</th>
                                            <th>Petugas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_mutasi_detail as $d): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($d['tanggal_transaksi'])) ?></td>
                                            <td><?= htmlspecialchars($d['nama_obat']) ?></td>
                                            <td><span class="badge bg-<?= ($d['jenis_transaksi'] == 'Masuk') ? 'success' : 'danger' ?>"><?= htmlspecialchars($d['jenis_transaksi']) ?></span></td>
                                            <td><?= abs($d['jumlah']) ?></td>
                                            <td><?= htmlspecialchars($d['stok_sebelum']) ?></td>
                                            <td><?= htmlspecialchars($d['stok_sesudah']) ?></td>
                                            <td>Rp <?= number_format($d['biaya_keluar_estimasi'], 0, ',', '.') ?></td>
                                            <td><?= htmlspecialchars($d['keterangan']) ?></td>
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

    <?php if (!empty($analisis_keluar)): ?>
    <script>
        const chartLabels = <?= $chart_labels ?>;
        const chartData = <?= $chart_data ?>;

        const ctx = document.getElementById('obatKeluarChart').getContext('2d');
        const obatKeluarChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Unit Keluar',
                    data: chartData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    title: { display: true, text: 'Proporsi Stok Keluar Berdasarkan Jenis Obat' }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>