<?php
// File: laporan_transaksi_obat.php (Laporan Mutasi Stok)
session_start();
// Pastikan path koneksi sudah benar!
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

// --- PENGATURAN DAN KONVERSI FILTER ---
$default_tanggal_awal = date('Y-m-01');
$default_tanggal_akhir = date('Y-m-d');

// Ambil input dari user (format default HTML date input adalah YYYY-MM-DD)
$tanggal_awal_input = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : $default_tanggal_awal;
$tanggal_akhir_input = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : $default_tanggal_akhir;
$filter_jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($koneksi, $_GET['jenis']) : '';

/**
 * Fungsi untuk mengkonversi string tanggal input (Y-m-d) menjadi format MySQL (Y-m-d) yang aman.
 */
function convert_to_mysql_date($date_input) {
    // Mencoba parsing Y-m-d (format standar HTML input type="date")
    $date_obj = DateTime::createFromFormat('Y-m-d', $date_input);
    if ($date_obj) {
        return $date_obj->format('Y-m-d');
    }
    return date('Y-m-d'); // Fallback jika gagal
}

// Konversi tanggal input ke format YYYY-MM-DD yang siap digunakan di MySQL
$tanggal_awal = convert_to_mysql_date($tanggal_awal_input);
$tanggal_akhir = convert_to_mysql_date($tanggal_akhir_input);

// Jika tombol cetak ditekan
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';

// --- QUERY UTAMA LAPORAN MUTASI STOK ---

// Membuat batasan waktu yang pasti untuk filter:
// Tgl Awal harus dari 00:00:00
$datetime_awal = $tanggal_awal . ' 00:00:00'; 
// Tgl Akhir harus sampai 23:59:59 (untuk mencakup seluruh hari)
$datetime_akhir = $tanggal_akhir . ' 23:59:59'; 

$sql_filter = "
    WHERE t.tanggal_transaksi BETWEEN '$datetime_awal' AND '$datetime_akhir'
";

if (!empty($filter_jenis)) {
    $sql_filter .= " AND t.jenis_transaksi = '$filter_jenis'";
}

$query_laporan = "
    SELECT 
        t.*, 
        o.kode_obat, 
        o.nama_obat, 
        o.satuan 
    FROM 
        transaksi_obat t
    JOIN 
        obat o ON t.obat_id = o.id
    $sql_filter
    ORDER BY 
        t.tanggal_transaksi DESC
";
$result_laporan = mysqli_query($koneksi, $query_laporan);

// Hitung total ringkasan dan isi data laporan
$total_masuk = 0;
$total_keluar = 0;
$data_laporan = [];

if ($result_laporan) {
    while ($row = mysqli_fetch_assoc($result_laporan)) {
        $data_laporan[] = $row;
        
        // Memastikan perhitungan akurat (mengatasi spasi/case-sensitive)
        if (strtoupper(trim($row['jenis_transaksi'])) == 'MASUK') { 
            $total_masuk += intval($row['jumlah']); 
        } elseif (strtoupper(trim($row['jenis_transaksi'])) == 'KELUAR') {
            $total_keluar += intval($row['jumlah']);
        }
    }
}

// --- LOGIKA EKSPOR EXCEL (CSV) ---
// Gunakan query yang sama dengan di atas
if (isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    
    // Jalankan ulang query untuk memastikan data terbaru (atau gunakan $data_laporan jika filter sudah dijalankan)
    $result_export = mysqli_query($koneksi, $query_laporan); 

    $filename = "Mutasi_Obat_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, [
        'Waktu Transaksi', 
        'Kode Obat', 
        'Nama Obat', 
        'Jenis Transaksi', 
        'Jumlah (Unit)', 
        'Stok Awal', 
        'Stok Akhir', 
        'Keterangan', 
        'Petugas'
    ]);
    
    // Data Baris
    if ($result_export) {
        while ($row = mysqli_fetch_assoc($result_export)) {
            fputcsv($output, [
                date('d/m/Y H:i:s', strtotime($row['tanggal_transaksi'])),
                $row['kode_obat'],
                $row['nama_obat'],
                $row['jenis_transaksi'],
                $row['jumlah'],
                $row['stok_sebelum'],
                $row['stok_sesudah'],
                $row['keterangan'] ?? '-',
                $row['petugas']
            ]);
        }
    }
    
    fclose($output);
    exit();
}

// --- TAMPILAN PRINT JIKA DITEKAN ---
if ($is_print_view) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cetak Laporan Mutasi Obat</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header-laporan { text-align: center; margin-bottom: 20px; }
        .header-laporan h3 { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body onload="window.print()">
    <div class="header-laporan">
        <h3>LAPORAN MUTASI STOK OBAT</h3>
        <p>Periode: **<?= date('d/m/Y', strtotime($tanggal_awal)) ?>** s/d **<?= date('d/m/Y', strtotime($tanggal_akhir)) ?>**</p>
        <p>Jenis Transaksi: **<?= empty($filter_jenis) ? 'Semua' : $filter_jenis ?>**</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Tanggal/Waktu</th>
                <th>Kode/Nama Obat</th>
                <th>Jenis</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Stok Awal</th>
                <th class="text-right">Stok Akhir</th>
                <th>Keterangan</th>
                <th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_laporan as $data): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= date('d/m/Y H:i', strtotime($data['tanggal_transaksi'])) ?></td>
                <td><?= htmlspecialchars($data['nama_obat']) ?><br><small class="text-muted"><?= htmlspecialchars($data['kode_obat']) ?></small></td>
                <td><?= $data['jenis_transaksi'] == 'MASUK' ? 'MASUK' : 'KELUAR' ?></td>
                <td class="text-right"><?= number_format($data['jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($data['satuan']) ?></td>
                <td class="text-right"><?= number_format($data['stok_sebelum'], 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($data['stok_sesudah'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($data['petugas']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="4" class="text-right"><strong>TOTAL MUTASI STOK</strong></td>
                <td class="text-right" style="background-color: #e6ffec;">
                    <strong>MASUK: <?= number_format($total_masuk, 0, ',', '.') ?></strong>
                </td>
                <td colspan="2" class="text-right" style="background-color: #ffe6e6;">
                    <strong>KELUAR: <?= number_format($total_keluar, 0, ',', '.') ?></strong>
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
    <title>Laporan Mutasi Stok Obat</title>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        /* CSS Kustom untuk Tampilan yang Lebih Elegan */
        .card-elegant {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); 
            border-radius: 12px;
        }
        .header-primary-light {
            background-color: #f0f3ff; 
            color: #3b5a9b; 
            border-bottom: 2px solid #3b5a9b;
        }
        .summary-card-masuk {
            background: linear-gradient(45deg, #d4edda, #e6ffe6); 
            color: #155724;
            border-left: 5px solid #28a745;
        }
        .summary-card-keluar {
            background: linear-gradient(45deg, #f8d7da, #ffe6e6); 
            color: #721c24;
            border-left: 5px solid #dc3545;
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
        
        <div id="sidebar">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="../../"><img src="../../assets/images/logo.PNG" alt="Logo" srcset=""></a>
                        </div>
                        <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
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
                                <input class="form-check-input me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
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
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-collection-fill"></i>
                                <span>Pelayanan Kesehatan</span>
                            </a>
                            
                            <ul class="submenu ">
                                <li class="submenu-item">
                                    <a href="../berobat/riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="../karyawan/riwayat_kecelakaan.php" class="submenu-link">Kecelakaan Kerja</a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="sidebar-item active has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-grid-1x2-fill"></i>
                                <span>Manajemen Obat</span>
                            </a>
                            
                            <ul class="submenu ">
                                <li class="submenu-item">
                                    <a href="master_obat.php" class="submenu-link">Data Obat</a>
                                </li>
                                
                                <li class="submenu-item">
                                    <a href="laporan_stok_master.php" class="submenu-link">Laporan Stok Obat</a> 
                                </li>
                                
                                <li class="submenu-item active">
                                    <a href="laporan_transaksi_obat.php" class="submenu-link">Laporan Mutasi Obat</a>
                                </li>              
                            </ul>
                        </li>
                        
                        <li class="sidebar-item">
                            <a href="form-layout.html" class='sidebar-link'>
                                <i class="bi bi-file-earmark-medical-fill"></i>
                                <span>Laporan Klinik</span>
                            </a>
                        </li>
                        
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Account</span>
                            </a>
                            
                            <ul class="submenu ">
                                <li class="submenu-item">
                                    <a href="account-profile.html" class="submenu-link">Profile</a>
                                </li>
                                
                                <li class="submenu-item">
                                    <a href="account-security.html" class="submenu-link">Security</a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-person-badge-fill"></i>
                                <span>Authentication</span>
                            </a>
                            
                            <ul class="submenu ">
                                <li class="submenu-item">
                                    <a href="auth-login.html" class="submenu-link">Login</a>
                                </li>
                                
                                <li class="submenu-item">
                                    <a href="auth-register.html" class="submenu-link">Register</a>
                                </li>
                                
                                <li class="submenu-item">
                                    <a href="auth-forgot-password.html" class="submenu-link">Forgot Password</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Laporan Mutasi Stok Obat 💊</h3>
                <p class="text-subtitle text-muted">Mencatat dan menganalisis riwayat penambahan dan pengurangan stok obat.</p>
            </div>
            
            <section class="section">
                
                <div class="card card-elegant">
                    <div class="card-header header-primary-light">
                        <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i> Filter Data Mutasi</h5>
                    </div>
                    <div class="card-body">
                        <form action="laporan_transaksi_obat.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Awal</label>
                                <input type="date" class="form-control" name="tgl_awal" value="<?= $tanggal_awal_input ?>" required> 
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Akhir</label>
                                <input type="date" class="form-control" name="tgl_akhir" value="<?= $tanggal_akhir_input ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Jenis Transaksi</label>
                                <select class="form-select" name="jenis">
                                    <option value="">-- Semua Jenis --</option>
                                    <option value="MASUK" <?= ($filter_jenis == 'MASUK') ? 'selected' : ''; ?>>Stok Masuk (➕)</option>
                                    <option value="KELUAR" <?= ($filter_jenis == 'KELUAR') ? 'selected' : ''; ?>>Stok Keluar (➖)</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Tampilkan</button>
                                
                                <a href="laporan_transaksi_obat.php?tgl_awal=<?= $tanggal_awal ?>&tgl_akhir=<?= $tanggal_akhir ?>&jenis=<?= $filter_jenis ?>&action=export_csv" class="btn btn-success me-2">
                                    <i class="bi bi-file-earmark-excel"></i> Export CSV
                                </a>
                                
                                <a href="laporan_transaksi_obat.php?tgl_awal=<?= $tanggal_awal ?>&tgl_akhir=<?= $tanggal_akhir ?>&jenis=<?= $filter_jenis ?>&print=true" target="_blank" class="btn btn-outline-danger">
                                    <i class="bi bi-printer"></i> Cetak
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="summary-card summary-card-masuk d-flex align-items-center">
                            <i class="bi bi-box-arrow-in-up-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Total Stok <strong>Masuk</strong> (Unit)</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_masuk, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="summary-card summary-card-keluar d-flex align-items-center">
                            <i class="bi bi-box-arrow-out-down-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Total Stok <strong>Keluar</strong> (Unit)</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_keluar, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-elegant">
                    <div class="card-header header-primary-light">
                        <h5 class="card-title mb-0"><i class="bi bi-list-columns-reverse me-2"></i> Detail Transaksi (<?= date('d/m/Y', strtotime($tanggal_awal)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_akhir)) ?>)</h5>
                    </div>
                    <div class="card-body">
                        
                        <table class="table table-striped table-hover" id="table1">
                            <thead class="table-primary"> 
                                <tr>
                                    <th>#</th>
                                    <th>Waktu</th>
                                    <th>Obat (Kode)</th>
                                    <th>Jenis</th>
                                    <th>Jumlah (Unit)</th>
                                    <th>Stok Awal</th>
                                    <th>Stok Akhir</th>
                                    <th>Keterangan</th>
                                    <th>Petugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_laporan as $data): ?>
                                <?php 
                                    // Logika untuk menentukan warna badge dan class baris
                                    $is_masuk = strtoupper(trim($data['jenis_transaksi'])) == 'MASUK';
                                    $jenis_badge = $is_masuk ? 'success' : 'danger'; // success = hijau, danger = merah
                                    $jenis_icon = $is_masuk ? '➕' : '➖';
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    
                                    <td><?= date('d/m/Y H:i', strtotime($data['tanggal_transaksi'])) ?></td>
                                    
                                    <td>
                                        <strong><?= htmlspecialchars($data['nama_obat']); ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($data['kode_obat']); ?></small>
                                    </td>
                                    
                                    <td><span class="badge bg-<?= $jenis_badge ?>"><?= $jenis_icon ?> <?= htmlspecialchars($data['jenis_transaksi']) ?></span></td>
                                    
                                    <td><strong class="text-<?= $jenis_badge ?>"><?= number_format($data['jumlah'], 0, ',', '.') ?></strong> <?= htmlspecialchars($data['satuan']) ?></td>
                                    <td class="text-muted"><?= number_format($data['stok_sebelum'], 0, ',', '.') ?></td>
                                    <td class="fw-bold"><?= number_format($data['stok_sesudah'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($data['petugas']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>    
                        </table>
                        
                        <?php if (count($data_laporan) == 0): ?>
                            <div class="alert alert-light-warning text-center mt-3">
                                <i class="bi bi-info-circle me-1"></i> Tidak ada data mutasi obat dalam rentang filter yang dipilih.
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