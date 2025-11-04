<?php
// File: laporan_stok_master.php (Laporan Stok dan Nilai Aset)
session_start();
// Pastikan path koneksi sudah benar!
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// Cek apakah mode cetak
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';

// --- QUERY UTAMA LAPORAN STOK MASTER ---
// Mengambil semua data obat dan menghitung nilai aset
$query_laporan = "
    SELECT 
        kode_obat,
        nama_obat,
        satuan,
        stok_tersedia,
        stok_minimum,
        harga_satuan,
        tanggal_kadaluarsa,
        (stok_tersedia * harga_satuan) AS nilai_aset
    FROM 
        obat
    ORDER BY 
        nama_obat ASC
";
$result_laporan = mysqli_query($koneksi, $query_laporan);

// Inisialisasi data laporan dan total nilai aset
$data_laporan = [];
$grand_total_aset = 0;
$total_item_low_stock = 0;

if ($result_laporan) {
    while ($row = mysqli_fetch_assoc($result_laporan)) {
        $data_laporan[] = $row;
        $grand_total_aset += floatval($row['nilai_aset']);
        
        // Cek Stok Minimum
        if (intval($row['stok_tersedia']) <= intval($row['stok_minimum'])) {
            $total_item_low_stock++;
        }
    }
} else {
    // Penanganan error jika query gagal
    // die("Query Gagal: " . mysqli_error($koneksi));
}

// --- LOGIKA EKSPOR EXCEL (CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    $filename = "Stok_Master_Obat_Aset_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, [
        'Kode Obat', 
        'Nama Obat', 
        'Satuan', 
        'Harga Satuan (Rp)', 
        'Stok Tersedia', 
        'Stok Minimum', 
        'Tanggal Kadaluarsa', 
        'Status Stok',
        'Nilai Aset (Rp)'
    ]);
    
    // Data Baris
    foreach ($data_laporan as $data) {
        // Logika Status Stok
        $stok_status = (intval($data['stok_tersedia']) <= intval($data['stok_minimum'])) ? 'Rendah/Habis' : 'Aman';
        $kadaluarsa_status = (strtotime($data['tanggal_kadaluarsa']) < strtotime('+3 months') && strtotime($data['tanggal_kadaluarsa']) >= time()) ? 'Mendekati' : 
                             (strtotime($data['tanggal_kadaluarsa']) < time() ? 'Kadaluarsa' : 'Aman');
        
        fputcsv($output, [
            $data['kode_obat'],
            $data['nama_obat'],
            $data['satuan'],
            number_format($data['harga_satuan'], 0, ',', '.'),
            $data['stok_tersedia'],
            $data['stok_minimum'],
            $data['tanggal_kadaluarsa'],
            $stok_status . ' | KAD: ' . $kadaluarsa_status,
            number_format($data['nilai_aset'], 0, ',', '.')
        ]);
    }
    
    fputcsv($output, ['---', '---', '---', '---', '---', '---', '---', 'GRAND TOTAL ASET (Rp)', number_format($grand_total_aset, 0, ',', '.')]);

    fclose($output);
    exit();
}

// --- TAMPILAN PRINT JIKA DITEKAN ---
if ($is_print_view) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cetak Laporan Stok Obat Master</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header-laporan { text-align: center; margin-bottom: 20px; }
        .header-laporan h3 { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
    </style>
</head>
<body onload="window.print()">
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
    <div class="header-laporan">
        <h3>LAPORAN STOK OBAT MASTER & NILAI ASET INVENTARIS</h3>
        <p>Tanggal Laporan: **<?= date('d/m/Y H:i:s') ?>**</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Kode/Nama Obat</th>
                <th class="text-right">Stok</th>
                <th>Satuan</th>
                <th class="text-right">Hrg/Unit (Rp)</th>
                <th class="text-right">Nilai Aset (Rp)</th>
                <th>Kadaluarsa</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_laporan as $data): 
                $is_low_stock = intval($data['stok_tersedia']) <= intval($data['stok_minimum']);
                $kadaluarsa_time = strtotime($data['tanggal_kadaluarsa']);
                $is_expired = $kadaluarsa_time < time();
                $is_near_expiry = !$is_expired && $kadaluarsa_time < strtotime('+3 months');
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($data['nama_obat']) ?><br><small class="text-muted"><?= htmlspecialchars($data['kode_obat']) ?></small></td>
                <td class="text-right" style="<?= $is_low_stock ? 'background-color: #ffcccc;' : '' ?>"><?= number_format($data['stok_tersedia'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($data['satuan']) ?></td>
                <td class="text-right"><?= number_format($data['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right"><strong><?= number_format($data['nilai_aset'], 0, ',', '.') ?></strong></td>
                <td style="<?= $is_expired ? 'background-color: #f8d7da;' : ($is_near_expiry ? 'background-color: #fff3cd;' : '') ?>">
                    <?= date('d/m/Y', $kadaluarsa_time) ?>
                </td>
                <td>
                    <?= $is_expired ? 'KADALUARSA' : ($is_near_expiry ? 'MENDK. KAD' : 'Aman') ?> |
                    <?= $is_low_stock ? 'STOK RENDAH' : 'STOK AMAN' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="5" class="text-right"><strong>GRAND TOTAL NILAI ASET INVENTARIS</strong></td>
                <td class="text-right" style="background-color: #d4edda;">
                    <strong>Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></strong>
                </td>
                <td colspan="2"></td>
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
    <title>Laporan Stok Obat Master</title>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        .card-elegant { box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); border-radius: 12px; }
        .summary-card-total {
            background: linear-gradient(45deg, #d3eafc, #e6f7ff); 
            color: #0d6efd;
            border-left: 5px solid #0d6efd;
        }
        .summary-card-low {
            background: linear-gradient(45deg, #fce5d3, #fff2e6); 
            color: #ffc107;
            border-left: 5px solid #ffc107;
        }
        .summary-card {
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: 100%; 
        }
    </style>

</head>
<body>
    <div id="app">
        <div id="sidebar"></div>
        
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>📊 Laporan Stok Obat Master & Nilai Aset</h3>
                <p class="text-subtitle text-muted">Melihat daftar stok obat dan menghitung total nilai aset inventaris farmasi.</p>
            </div>
            
            <section class="section">

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="summary-card summary-card-total d-flex align-items-center">
                            <i class="bi bi-currency-dollar fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">GRAND TOTAL NILAI ASET INVENTARIS</small>
                                <h3 class="mb-0 fw-bold">Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card summary-card-low d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Jumlah Item Stok Rendah</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_item_low_stock, 0, ',', '.') ?> Item</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-elegant">
                    <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-box-seam me-2"></i> Daftar Stok Obat Saat Ini (<?= date('d/m/Y') ?>)</h5>
                        <div class="btn-group">
                            <a href="laporan_stok_master.php?action=export_csv" class="btn btn-success btn-sm me-2">
                                <i class="bi bi-file-earmark-excel"></i> Export CSV
                            </a>
                            <a href="laporan_stok_master.php?print=true" target="_blank" class="btn btn-danger btn-sm">
                                <i class="bi bi-printer"></i> Cetak
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        <table class="table table-striped table-hover" id="table1">
                            <thead class="table-primary"> 
                                <tr>
                                    <th>#</th>
                                    <th>Kode/Nama Obat</th>
                                    <th>Stok (Unit)</th>
                                    <th>Min. Stok</th>
                                    <th>Hrg/Unit (Rp)</th>
                                    <th>Nilai Aset (Rp)</th>
                                    <th>Tgl. Kadaluarsa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_laporan as $data): ?>
                                <?php 
                                    $is_low_stock = intval($data['stok_tersedia']) <= intval($data['stok_minimum']);
                                    $kadaluarsa_time = strtotime($data['tanggal_kadaluarsa']);
                                    $is_expired = $kadaluarsa_time < time();
                                    $is_near_expiry = !$is_expired && $kadaluarsa_time < strtotime('+3 months');
                                    
                                    // Tentukan class badge
                                    $stok_badge = $is_low_stock ? 'danger' : 'success';
                                    $expiry_badge = $is_expired ? 'dark' : ($is_near_expiry ? 'warning' : 'success');
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    
                                    <td>
                                        <strong><?= htmlspecialchars($data['nama_obat']); ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($data['kode_obat']); ?></small>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-<?= $stok_badge ?>"><?= number_format($data['stok_tersedia'], 0, ',', '.') ?></span> 
                                        <?= htmlspecialchars($data['satuan']) ?>
                                    </td>
                                    
                                    <td class="text-muted"><?= number_format($data['stok_minimum'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-end text-success"><?= number_format($data['harga_satuan'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-end fw-bold">Rp<?= number_format($data['nilai_aset'], 0, ',', '.') ?></td>
                                    
                                    <td>
                                        <span class="badge bg-<?= $expiry_badge ?>"><?= date('d/m/Y', $kadaluarsa_time) ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-secondary"><?= $is_expired ? 'KADALUARSA' : 'Aman' ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>    
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end bg-light">GRAND TOTAL NILAI ASET INVENTARIS</th>
                                    <th class="text-end bg-success text-white">Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></th>
                                    <th colspan="2" class="bg-light"></th>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <?php if (count($data_laporan) == 0): ?>
                            <div class="alert alert-light-warning text-center mt-3">
                                <i class="bi bi-info-circle me-1"></i> Tidak ada data obat ditemukan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
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
    </footer>
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>