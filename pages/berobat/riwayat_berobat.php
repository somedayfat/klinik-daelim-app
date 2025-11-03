<?php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// --- TANGKAP FILTER DARI FORM ---
$tgl_awal = '';
$tgl_akhir = '';
$where_clause = '';

if (isset($_GET['tgl_awal']) && isset($_GET['tgl_akhir']) && $_GET['tgl_awal'] != '' && $_GET['tgl_akhir'] != '') {
    // Ambil dan bersihkan input filter
    $tgl_awal = mysqli_real_escape_string($koneksi, $_GET['tgl_awal']);
    $tgl_akhir = mysqli_real_escape_string($koneksi, $_GET['tgl_akhir']);

    // Buat kondisi WHERE untuk filter tanggal (mencakup seluruh hari)
    $where_clause = "WHERE DATE(b.tanggal_berobat) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}

// Query utama dengan penambahan filter
$query = "SELECT 
            b.id, 
            b.id_card, 
            k.nama,             
            b.tanggal_berobat, 
            b.keluhan, 
            b.diagnosis, 
            b.tekanan_darah, 
            b.suhu_tubuh, 
            b.petugas           
          FROM berobat b
          JOIN karyawan k ON b.id_card = k.id_card
          $where_clause
          ORDER BY b.tanggal_berobat DESC";
          
$result = mysqli_query($koneksi, $query);

// Helper function untuk format tanggal
function formatTanggal($date_string) {
    if (empty($date_string) || $date_string == '0000-00-00 00:00:00') return '-';
    $timestamp = strtotime($date_string);
    return date('d-m-Y H:i:s', $timestamp);
}

// Cek apakah mode cetak
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pemeriksaan Pasien | Klinik PT. Daelim Indonesia</title>
    
    <?php if ($is_print_view): ?>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .table-responsive { overflow: visible !important; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; }
        .no-print { display: none; }
    </style>
    <?php else: ?>
    <link rel="stylesheet" href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" href="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../../assets/extensions/bootstrap-icons/font/bootstrap-icons.css">
    <?php endif; ?>
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
                                    <a href="../karyawan/form_kecelakaan_kerja.php" class="submenu-link">Kecelakaan Kerja</a>
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
                        <li class="sidebar-item">
                            <a href="logout.php" class='sidebar-link'>
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
    <?php if (!$is_print_view): ?>
    <!-- <div id="app">
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header> -->

            <div class="page-heading">
                <h3>Riwayat Pemeriksaan Pasien</h3>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Riwayat Berobat</li>
                    </ol>
            </div>

    <?php endif; ?>
            
            <section class="section">
                <div class="card">
                    <div class="card-header <?= $is_print_view ? 'no-print' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4>Data Kunjungan Medis</h4>
                            <div class="d-flex">
                                <a href="riwayat_berobat.php?print=true&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" class="btn btn-warning me-2" target="_blank" title="Cetak Laporan">
                                    <i class="bi bi-printer"></i> Cetak
                                </a>
                                <a href="form_pemeriksaan.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Pemeriksaan Baru</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body <?= $is_print_view ? 'no-print' : '' ?>">
                        <form method="GET" action="riwayat_berobat.php" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="tgl_awal" class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?= $tgl_awal ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="tgl_akhir" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?= $tgl_akhir ?>" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-filter"></i> Filter</button>
                                <a href="riwayat_berobat.php" class="btn btn-secondary"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if ($is_print_view): ?>
                            <h4 class="text-center mb-4">LAPORAN RIWAYAT PEMERIKSAAN PASIEN</h4>
                            <p class="text-center mb-4">Periode: **<?= empty($tgl_awal) ? 'Semua Data' : formatTanggal($tgl_awal) . ' s/d ' . formatTanggal($tgl_akhir) ?>**</p>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped" id="<?= $is_print_view ? 'table-print' : 'table1' ?>">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal Berobat</th>
                                        <th>ID Card</th>
                                        <th>Nama Pasien</th>
                                        <th>Keluhan</th>
                                        <th>Diagnosis</th>
                                        <th>Petugas</th>
                                        <th class="<?= $is_print_view ? 'no-print' : '' ?>">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $no = 1;
                                    while ($data = mysqli_fetch_assoc($result)) {
                                ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= formatTanggal($data['tanggal_berobat']); ?></td>
                                            <td><?= htmlspecialchars($data['id_card']); ?></td>
                                            <td><?= htmlspecialchars($data['nama']); ?></td>
                                            <td><?= substr(htmlspecialchars($data['keluhan']), 0, 50) . (strlen($data['keluhan']) > 50 ? '...' : ''); ?></td>
                                            <td><?= substr(htmlspecialchars($data['diagnosis']), 0, 50) . (strlen($data['diagnosis']) > 50 ? '...' : ''); ?></td>
                                            <td><?= htmlspecialchars($data['petugas']); ?></td>
                                            <td class="<?= $is_print_view ? 'no-print' : '' ?>">
                                                <a href="detail_pemeriksaan.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-info" title="Lihat Detail">Detail</a>
                                                <!-- <a href="form_pemeriksaan.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-warning" title="Edit Data"><i class="bi bi-pencil"></i> Edit</a> -->
                                                <a href="delete_pemeriksaan.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-danger" title="Hapus Data" onclick="return confirm('Apakah Anda yakin ingin menghapus riwayat pemeriksaan ini? Proses ini akan mengembalikan stok obat.')"><i class="bi bi-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                <?php
                                    }
                                ?>
                            </tbody>    
                        </table>
                    </div>
                </div>
            </section>

            <?php if (!$is_print_view): ?>
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
    <script src="../../assets/static/js/components/dark.js"></script>
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    
    
    <script src="../../assets/compiled/js/app.js"></script>
    
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    
    <?php endif; ?>
</body>
</html>