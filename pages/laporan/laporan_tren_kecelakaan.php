<?php
// File: laporan_tren_kecelakaan.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// --- PENGATURAN FILTER TANGGAL ---
$default_tanggal_awal = date('Y-m-01');
$default_tanggal_akhir = date('Y-m-d');

$tanggal_awal_input = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : $default_tanggal_awal;
$tanggal_akhir_input = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : $default_tanggal_akhir;

$tanggal_awal = DateTime::createFromFormat('Y-m-d', $tanggal_awal_input) ? $tanggal_awal_input : $default_tanggal_awal;
$tanggal_akhir = DateTime::createFromFormat('Y-m-d', $tanggal_akhir_input) ? $tanggal_akhir_input : $default_tanggal_akhir;

$datetime_awal = $tanggal_awal . ' 00:00:00'; 
$datetime_akhir = $tanggal_akhir . ' 23:59:59'; 

// Cek apakah mode cetak
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';

// --- QUERY UTAMA: MENGHITUNG TREN AREA RISIKO (FIXED) ---
$query_tren_area = "
    SELECT 
        lokasi_kejadian AS lokasi_kejadian, /* Kolom lokasi dari tabel kecelakaan_kerja */
        COUNT(id) AS total_kasus
    FROM 
        kecelakaan_kerja /* Nama tabel yang benar */
    WHERE 
        tanggal_kejadian BETWEEN '$datetime_awal' AND '$datetime_akhir' /* Kolom tanggal yang benar */
        AND lokasi_kejadian IS NOT NULL AND lokasi_kejadian != ''
    GROUP BY 
        lokasi_kejadian
    ORDER BY 
        total_kasus DESC
    LIMIT 3
";

$result_tren_area = mysqli_query($koneksi, $query_tren_area);

$data_tren_area = [];
if (!$result_tren_area) {
    // Penanganan error jika query gagal
    $data_tren_area = []; 
} else {
    while ($row = mysqli_fetch_assoc($result_tren_area)) {
        $data_tren_area[] = $row;
    }
}

// Hitung total kasus untuk persentase
$grand_total_area = array_sum(array_column($data_tren_area, 'total_kasus'));

// --- LOGIKA EKSPOR EXCEL (CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    $filename = "Tren_Kecelakaan_Area_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Peringkat', 'Area Kejadian', 'Total Kasus', 'Persentase']);
    
    $no = 1;
    foreach ($data_tren_area as $data) {
        $percentage = ($data['total_kasus'] / $grand_total_area) * 100;
        fputcsv($output, [
            $no++,
            $data['lokasi_kejadian'],
            $data['total_kasus'],
            number_format($percentage, 1) . '%'
        ]);
    }
    fputcsv($output, ['', 'TOTAL KASUS', $grand_total_area, '']);
    
    fclose($output);
    exit();
}

// --- TAMPILAN PRINT JIKA DITEKAN ---
if ($is_print_view) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cetak Tren Area Kecelakaan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header-laporan { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body onload="window.print()">
    <div class="header-laporan">
        <h3>LAPORAN TREN AREA RISIKO KECELAKAAN TOP 3</h3>
        <p>Periode: **<?= date('d/m/Y', strtotime($tanggal_awal)) ?>** s/d **<?= date('d/m/Y', strtotime($tanggal_akhir)) ?>**</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Peringkat</th>
                <th>Area Kejadian</th>
                <th>Total Kasus</th>
                <th>Persentase (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_tren_area as $data): 
                $percentage = ($data['total_kasus'] / $grand_total_area) * 100;
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($data['lokasi_kejadian']) ?></td>
                <td><?= number_format($data['total_kasus']) ?></td>
                <td><?= number_format($percentage, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2" style="text-align: right;"><strong>GRAND TOTAL KASUS</strong></td>
                <td><strong><?= number_format($grand_total_area) ?></strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Tren Area Kecelakaan</title>
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .card-analytics { box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); border-radius: 10px; }
        .progress-label { display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: 600; }
    </style>
</head>
<body>
    <div id="app">
        <div id="sidebar"></div> 
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>⚠️ Laporan Tren Area Kecelakaan Kerja</h3>
                <p class="text-subtitle text-muted">Analisis 3 area/departemen dengan frekuensi kecelakaan tertinggi periode **<?= date('d/m/Y', strtotime($tanggal_awal)) ?>** s/d **<?= date('d/m/Y', strtotime($tanggal_akhir)) ?>**.</p>
            </div>
            
            <section class="section">
                
                <div class="card card-analytics">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i> Filter Periode</h5>
                        <a href="../../" class="btn btn-warning btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
                    </div>
                    <div class="card-body">
                        <form action="laporan_tren_kecelakaan.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Awal</label>
                                <input type="date" class="form-control" name="tgl_awal" value="<?= $tanggal_awal_input ?>" required> 
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Akhir</label>
                                <input type="date" class="form-control" name="tgl_akhir" value="<?= $tanggal_akhir_input ?>" required>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Tampilkan</button>
                                
                                <a href="laporan_tren_kecelakaan.php?tgl_awal=<?= $tanggal_awal ?>&tgl_akhir=<?= $tanggal_akhir ?>&action=export_csv" class="btn btn-success me-2">
                                    <i class="bi bi-file-earmark-excel"></i> Export CSV
                                </a>
                                <a href="laporan_tren_kecelakaan.php?tgl_awal=<?= $tanggal_awal ?>&tgl_akhir=<?= $tanggal_akhir ?>&print=true" target="_blank" class="btn btn-outline-danger">
                                    <i class="bi bi-printer"></i> Cetak
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card card-analytics">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-geo-alt-fill me-2"></i> Top 3 Area Risiko Kecelakaan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($data_tren_area)): ?>
                            <div class="alert alert-light-warning text-center">Tidak ada data kecelakaan untuk periode ini.</div>
                        <?php else: 
                            $colors = ['bg-danger', 'bg-warning', 'bg-info'];
                            $i = 0;
                        ?>
                            <?php foreach ($data_tren_area as $data): 
                                $percentage = ($data['total_kasus'] / $grand_total_area) * 100;
                                $color = $colors[$i % count($colors)];
                            ?>
                                <div class="mb-3">
                                    <div class="progress-label">
                                        <span><?= htmlspecialchars($data['lokasi_kejadian']) ?></span>
                                        <span><?= number_format($data['total_kasus']) ?> Kasus (<?= number_format($percentage, 1) ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 15px;">
                                        <div class="progress-bar <?= $color ?>" role="progressbar" style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php $i++; endforeach; ?>
                            <hr>
                            <h5 class="text-end">Total Semua Kecelakaan: <span class="text-danger"><?= number_format($grand_total_area) ?></span></h5>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>