<?php
// File: riwayat_kecelakaan.php
session_start();
// PASTIKSAN PATH KONEKSI INI BENAR
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

// --- PENGAMBILAN DATA UNTUK FILTER ---
$departemen_list = [];
$result_dept = mysqli_query($koneksi, "SELECT DISTINCT departemen FROM karyawan ORDER BY departemen ASC");
while ($row = mysqli_fetch_assoc($result_dept)) {
    $departemen_list[] = $row['departemen'];
}

// --- LOGIKA FILTER DAN QUERY UTAMA ---
$filter_departemen = $_GET['departemen'] ?? '';
$filter_bulan = $_GET['bulan'] ?? ''; // Format: YYYY-MM

$where_clauses = [];

// 1. Filter Departemen
if (!empty($filter_departemen)) {
    $where_clauses[] = "k.departemen = '" . mysqli_real_escape_string($koneksi, $filter_departemen) . "'";
}

// 2. Filter Bulan
if (!empty($filter_bulan)) {
    // Memastikan format YYYY-MM
    if (preg_match('/^\d{4}-\d{2}$/', $filter_bulan)) {
        $where_clauses[] = "DATE_FORMAT(kk.tanggal_kejadian, '%Y-%m') = '" . mysqli_real_escape_string($koneksi, $filter_bulan) . "'";
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$data_kecelakaan = [];
$query_riwayat = "
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
        kk.created_at DESC";

$result_riwayat = mysqli_query($koneksi, $query_riwayat);

if ($result_riwayat) {
    while ($row = mysqli_fetch_assoc($result_riwayat)) {
        $data_kecelakaan[] = $row;
    }
} else {
    $error_db = "Gagal mengambil data: " . mysqli_error($koneksi);
}

// PASTIKSAN PATH FOLDER UPLOAD INI BENAR
$base_url_foto = 'uploads/kecelakaan/'; 


// --- LOGIKA UTAMA: DELETE DATA KECELAKAAN KERJA ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_delete = mysqli_real_escape_string($koneksi, $_GET['id']);

    $q_file = "SELECT file_foto FROM kecelakaan_kerja WHERE id = '$id_delete'";
    $r_file = mysqli_query($koneksi, $q_file);
    $data_file = mysqli_fetch_assoc($r_file);
    $file_to_delete = $data_file['file_foto'] ?? null;
    
    $query_delete = "DELETE FROM kecelakaan_kerja WHERE id = '$id_delete'";
    
    if (mysqli_query($koneksi, $query_delete)) {
        if (!empty($file_to_delete) && file_exists($base_url_foto . $file_to_delete)) {
            unlink($base_url_foto . $file_to_delete);
        }
        header("Location: riwayat_kecelakaan.php?status=success_delete");
        exit();
    } else {
        header("Location: riwayat_kecelakaan.php?status=error_delete");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Laporan Kecelakaan Kerja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <link rel="shortcut icon" href="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2033%2034'%20fill-rule='evenodd'%20stroke-linejoin='round'%20stroke-miterlimit='2'%20xmlns:v='https://vecta.io/nano'%3e%3cpath%20d='M3%2027.472c0%204.409%206.18%205.552%2013.5%205.552%207.281%200%2013.5-1.103%2013.5-5.513s-6.179-5.552-13.5-5.552c-7.281%200-13.5%201.103-13.5%205.513z'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3ccircle%20cx='16.5'%20cy='8.8'%20r='8.8'%20fill='%2341bbdd'/%3e%3c/svg%3e" type="image/x-icon">
    <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
    
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
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
        <!-- Base -->

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Riwayat Laporan Kecelakaan Kerja</h3>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Data Kecelakaan Kerja</h4>
                        <a href="form_kecelakaan_kerja.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Buat Laporan Baru
                        </a>
                    </div>
                    <div class="card-body">
                        
                        <?php 
                        // LOGIKA NOTIFIKASI
                        if (isset($_GET['status'])): 
                            $status = $_GET['status'];
                            $msg = null;
                            if ($status == 'success_add') $msg = ['success', 'Berhasil!', 'Laporan telah sukses dicatat.'];
                            else if ($status == 'success_edit') $msg = ['success', 'Berhasil!', 'Laporan telah sukses diperbarui.'];
                            else if ($status == 'success_delete') $msg = ['success', 'Berhasil!', 'Laporan telah sukses dihapus.'];
                            else if ($status == 'error_delete') $msg = ['danger', 'Gagal!', 'Terjadi kesalahan saat menghapus laporan.'];
                            
                            if ($msg):
                        ?>
                        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show" role="alert">
                            <strong><i class="bi bi-check-circle-fill me-1"></i> <?= $msg[1] ?></strong> <?= $msg[2] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; endif; ?>

                        <?php if (isset($error_db)): ?>
                            <div class="alert alert-danger"><?= $error_db ?></div>
                        <?php endif; ?>

                        <form action="riwayat_kecelakaan.php" method="GET" class="mb-4 p-3 border rounded bg-light">
                            <h6 class="mb-3"><i class="bi bi-funnel-fill me-1"></i> Filter Data</h6>
                            <div class="row g-3">
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
                                <div class="col-md-5">
                                    <label class="form-label">Bulan Kejadian</label>
                                    <input type="month" class="form-control" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-info w-100 me-1"><i class="bi bi-search"></i> Cari</button>
                                    <a href="riwayat_kecelakaan.php" class="btn btn-secondary w-100"><i class="bi bi-x-lg"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-striped" id="table_kecelakaan">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Jenis Kecelakaan</th>
                                        <th>Status Penanganan</th>
                                        <th>Lama Istirahat (Hari)</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_kecelakaan as $data): ?>
                                    <tr>
                                        <td><?= date('d-m-Y', strtotime($data['tanggal_kejadian'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama']) ?> (<?= htmlspecialchars($data['id_card']) ?>)</td>
                                        <td><?= htmlspecialchars($data['departemen']) ?></td>
                                        
                                        <td><?= htmlspecialchars($data['jenis_kecelakaan'] ?? 'N/A') ?></td> 
                                        <td>
                                            <span class="badge bg-<?= ($data['status'] == 'Rujuk Rumah Sakit') ? 'warning' : 'success' ?>">
                                                <?= htmlspecialchars($data['status'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        
                                        <td><?= htmlspecialchars($data['lama_istirahat']) ?></td>
                                        <td class="text-center d-flex justify-content-center">
                                            <button type="button" class="btn btn-sm btn-info view-detail me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal"
                                                    data-id="<?= htmlspecialchars($data['id']) ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="form_edit_kecelakaan.php?id=<?= htmlspecialchars($data['id']) ?>" class="btn btn-sm btn-warning me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?= htmlspecialchars($data['id']) ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            
            <footer></footer>
        </div>
    </div>
    
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailModalLabel"><i class="bi bi-file-earmark-medical me-1"></i> Detail Laporan Kecelakaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modal-content-placeholder">
                        <div class="text-center p-5"><i class="bi bi-arrow-clockwise spinner-border"></i> Memuat data...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin menghapus laporan kecelakaan ini? Tindakan ini tidak dapat dibatalkan.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a id="btn-confirm-delete" class="btn btn-danger">Hapus Permanen</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        // Datatables hanya diaktifkan jika tidak ada filter (agar filter dari PHP bisa bekerja)
        if (!'<?= $filter_departemen ?>' && !'<?= $filter_bulan ?>') {
            $('#table_kecelakaan').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
                }
            });
        }
        
        // Logika Hapus Konfirmasi
        $('#table_kecelakaan').on('click', '.btn-delete', function() {
            const id_delete = $(this).data('id');
            const deleteUrl = 'riwayat_kecelakaan.php?action=delete&id=' + id_delete;
            
            $('#btn-confirm-delete').attr('href', deleteUrl);
            $('#deleteModal').modal('show');
        });

        // =============================================
        // LOGIKA MODAL VIEW DETAIL (Menggunakan AJAX)
        // =============================================
        $('.view-detail').on('click', function() {
            const id = $(this).data('id');
            const modalBody = $('#modal-content-placeholder');
            const baseUrlFoto = '<?= $base_url_foto ?>';
            
            modalBody.html('<div class="text-center p-5"><i class="bi bi-arrow-clockwise spinner-border"></i> Memuat data...</div>');

            // Memanggil API yang sudah dibuat di balasan sebelumnya
            $.ajax({
                url: 'api_kecelakaan_detail.php', 
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const d = response.data;
                        const fotoPath = d.file_foto ? baseUrlFoto + d.file_foto : 'N/A';
                        const fotoDisplay = d.file_foto 
                            ? `<img src="${fotoPath}" class="img-fluid rounded shadow-sm" style="max-height: 200px; object-fit: cover;" alt="Foto Kecelakaan">`
                            : `<span class="text-muted"><i class="bi bi-image-fill"></i> Foto tidak diunggah.</span>`;

                        const htmlContent = `
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <h6 class="fw-bold text-danger">Data Karyawan & Insiden</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr><th style="width: 40%;">ID Card/NIK</th><td>: ${d.id_card}</td></tr>
                                        <tr><th>Nama Karyawan</th><td>: ${d.nama}</td></tr>
                                        <tr><th>Departemen</th><td>: ${d.departemen}</td></tr>
                                        <tr><th>Tanggal Kejadian</th><td>: ${new Date(d.tanggal_kejadian).toLocaleDateString('id-ID')}</td></tr>
                                        <tr><th>Lokasi</th><td>: ${d.lokasi_kejadian}</td></tr>
                                        <tr><th>Jenis Kecelakaan</th><td>: ${d.jenis_kecelakaan} (${d.bagian_tubuh})</td></tr>
                                        <tr><th>Lama Istirahat</th><td>: ${d.lama_istirahat} Hari</td></tr>
                                    </table>
                                    
                                    <h6 class="fw-bold text-danger mt-3">Kronologi & Tindakan Medis</h6>
                                    <p><strong>Kronologi:</strong><br>${d.deskripsi}</p>
                                    <p><strong>Tindakan Medis:</strong><br>${d.tindakan}</p>
                                    <p><strong>Status:</strong> <span class="badge bg-${(d.status == 'Rujuk Rumah Sakit') ? 'warning' : 'success'}">${d.status}</span></p>

                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-primary">Rekomendasi Pencegahan (HSE)</h6>
                                    <p class="alert alert-light border border-primary p-2">
                                        ${d.tindakan_pencegahan}
                                    </p>
                                    
                                    <h6 class="fw-bold text-primary mt-3">Dokumentasi Foto</h6>
                                    <div class="text-center border p-2 rounded">
                                        ${fotoDisplay}
                                    </div>
                                    <p class="mt-3"><small class="text-muted">Dicatat oleh: ${d.petugas} pada ${new Date(d.created_at).toLocaleString('id-ID')}</small></p>
                                </div>
                            </div>
                        `;
                        modalBody.html(htmlContent);

                    } else {
                        modalBody.html('<div class="alert alert-warning">Data detail tidak ditemukan.</div>');
                    }
                },
                error: function() {
                    modalBody.html('<div class="alert alert-danger">Gagal memuat data dari server.</div>');
                }
            });
        });

    });
    </script>
</body>
</html>