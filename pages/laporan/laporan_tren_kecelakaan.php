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

// --- TAMPILAN PRINT JIKA DITEKAN (KODE SIDEBAR DIHAPUS DARI SINI) ---
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
                        <li class="sidebar-item ">
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
                class="sidebar-item active has-sub ">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                <ul class="submenu active">
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
                    <li class="submenu-item active ">
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
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

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