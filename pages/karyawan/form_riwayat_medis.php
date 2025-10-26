<?php
// ... (Bagian PHP Logic Anda, SAMA persis seperti sebelumnya) ...
session_start();
include('../../config/koneksi.php'); 

$pesan_status = '';
$tipe_alert = '';
$id_card_url = isset($_GET['id_card']) ? mysqli_real_escape_string($koneksi, $_GET['id_card']) : '';

// --- FUNGSI UTILITY ---
function getValue($data, $key, $default = '') {
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : $default;
}

// 1. Ambil data Karyawan dan Riwayat Medis yang sudah ada
$karyawan_data = [];
$riwayat_medis_data = [];

if ($id_card_url) {
    // a. Ambil data Karyawan
    $q_karyawan = "SELECT id_card, nama FROM karyawan WHERE id_card='$id_card_url'";
    $r_karyawan = mysqli_query($koneksi, $q_karyawan);
    $karyawan_data = mysqli_fetch_assoc($r_karyawan);
    
    if ($karyawan_data) {
        // b. Ambil data Riwayat Medis (jika sudah ada)
        $q_medis = "SELECT * FROM riwayat_medis WHERE id_card='$id_card_url'";
        $r_medis = mysqli_query($koneksi, $q_medis);
        $riwayat_medis_data = mysqli_fetch_assoc($r_medis);
    } else {
        $pesan_status = "ID Card Karyawan tidak ditemukan. ❌";
        $tipe_alert = 'danger';
    }
}

// 2. Logika POST (Simpan/Update Riwayat Medis)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $karyawan_data) {
    $id_card_post = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    $penyakit = mysqli_real_escape_string($koneksi, $_POST['penyakit_terdahulu']);
    $alergi = mysqli_real_escape_string($koneksi, $_POST['alergi']);
    $gol_darah = mysqli_real_escape_string($koneksi, $_POST['golongan_darah']);
    $kontak_nama = mysqli_real_escape_string($koneksi, $_POST['kontak_darurat_nama']);
    $kontak_telp = mysqli_real_escape_string($koneksi, $_POST['kontak_darurat_telepon']);

    if ($riwayat_medis_data) {
        // UPDATE
        $query = "UPDATE riwayat_medis SET 
                    penyakit_terdahulu='$penyakit', alergi='$alergi', golongan_darah='$gol_darah', 
                    kontak_darurat_nama='$kontak_nama', kontak_darurat_telepon='$kontak_telp', updated_at=NOW() 
                  WHERE id_card='$id_card_post'";
    } else {
        // INSERT
        $query = "INSERT INTO riwayat_medis (id_card, penyakit_terdahulu, alergi, golongan_darah, kontak_darurat_nama, kontak_darurat_telepon, updated_at) 
                  VALUES ('$id_card_post', '$penyakit', '$alergi', '$gol_darah', '$kontak_nama', '$kontak_telp', NOW())";
    }

    if (mysqli_query($koneksi, $query)) {
        header("Location: form_riwayat_medis.php?id_card=" . $id_card_post . "&status=success");
        exit();
    } else {
        $pesan_status = "Gagal menyimpan data: " . mysqli_error($koneksi) . " ❌";
        $tipe_alert = 'danger';
    }
}

// Handle status dari redirect
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $pesan_status = "Data Riwayat Medis berhasil diperbarui! ✅";
    $tipe_alert = 'success';
    $q_medis = "SELECT * FROM riwayat_medis WHERE id_card='$id_card_url'";
    $r_medis = mysqli_query($koneksi, $q_medis);
    $riwayat_medis_data = mysqli_fetch_assoc($r_medis);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klinik PT. Daelim Indonesia</title>
    
    <link rel="shortcut icon" href="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2033%2034'%20fill-rule='evenodd'%20stroke-linejoin='round'%20stroke-miterlimit='2'%20xmlns:v='https://vecta.io/nano'%3e%3cpath%20d='M3%2027.472c0%204.409%206.18%205.552%2013.5%205.552%207.281%200%2013.5-1.103%2013.5-5.513s-6.179-5.552-13.5-5.552c-7.281%200-13.5%201.103-13.5%205.513z'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3ccircle%20cx='16.5'%20cy='8.8'%20r='8.8'%20fill='%2341bbdd'/%3e%3c/svg%3e" type="image/x-icon">
    <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
    
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" href="../../assets/compiled/css/custom.css">
    

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
                <a href="../../index.html"><img src="../../assets/images/logo.PNG" alt="Logo" srcset=""></a>
            </div>
            <div class="theme-toggle d-flex gap-2  align-items-center mt-2">
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
                    <input class="form-check-input  me-0" type="checkbox" id="toggle-dark" style="cursor: pointer">
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
            <div class="sidebar-toggler  x">
                <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
            </div>
        </div>
    </div>
    <div class="sidebar-menu">
        <ul class="menu">
            <li class="sidebar-title">Menu</li>
            
            <li
                class="sidebar-item active ">
                <a href="index.html" class='sidebar-link'>
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
                

            </li>
            
            <li
                class="sidebar-item">
                <a href="karyawan.php" class='sidebar-link'>
                    <i class="bi bi-stack"></i>
                    <span>Data Karyawan</span>
                </a>
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-collection-fill"></i>
                    <span>Pelayanan Kesehatan</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Pemeriksaan Pasien</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Riwayat Medis</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Kecelakaan Kerja</a>
                        
                    </li>
                </ul>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Manajemen Obat</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Data Obat</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Resep Obat</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="layout-default.html" class="submenu-link">Transaksi Obat</a>
                        
                    </li>
                    
                </ul>
                

            </li>
            
            <li
                class="sidebar-item  ">
                <a href="form-layout.html" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-person-circle"></i>
                    <span>Account</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="account-profile.html" class="submenu-link">Profile</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="account-security.html" class="submenu-link">Security</a>
                        
                    </li>
                    
                </ul>
                

            </li>
            
            <li
                class="sidebar-item  has-sub">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Authentication</span>
                </a>
                
                <ul class="submenu ">
                    
                    <li class="submenu-item  ">
                        <a href="auth-login.html" class="submenu-link">Login</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="auth-register.html" class="submenu-link">Register</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="auth-forgot-password.html" class="submenu-link">Forgot Password</a>
                        
                    </li>
                    
                </ul>
                

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
<!-- Tempat Isi Data -->
 <div class="page-heading">
                <h3>Kelola Riwayat Medis Dasar</h3>
                <?php if ($karyawan_data): ?>
                    <p class="text-subtitle text-muted">Profil Medis untuk Pasien: <strong><?= getValue($karyawan_data, 'nama') ?> (<?= $id_card_url ?>)</strong></p>
                <?php endif; ?>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?= $riwayat_medis_data ? 'Edit' : 'Tambah' ?> Riwayat Medis Pasien</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($pesan_status): ?>
                            <div class="alert alert-<?= $tipe_alert ?> alert-dismissible fade show" role="alert">
                                <?= $pesan_status ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($karyawan_data): ?>
                        <form action="form_riwayat_medis.php?id_card=<?= $id_card_url ?>" method="POST" class="form-horizontal">
                            <input type="hidden" name="id_card" value="<?= $id_card_url ?>">
                            
                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <h5 class="mt-2 text-primary">Riwayat Kesehatan Dasar</h5>
                                    <hr class="mt-0">

                                    <div class="mb-3">
                                        <label class="form-label">Penyakit Terdahulu</label>
                                        <textarea class="form-control" name="penyakit_terdahulu" rows="3"
                                               placeholder="cth: Hipertensi, Asma, Diabetes"><?= getValue($riwayat_medis_data, 'penyakit_terdahulu') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Alergi (Makanan/Obat/Lingkungan)</label>
                                        <textarea class="form-control" name="alergi" rows="3"
                                               placeholder="cth: Udang, Paracetamol, Debu"><?= getValue($riwayat_medis_data, 'alergi') ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Golongan Darah</label>
                                        <select class="form-select" name="golongan_darah" required>
                                            <option value="">-- Pilih --</option>
                                            <?php $gd = getValue($riwayat_medis_data, 'golongan_darah'); ?>
                                            <option value="A" <?= ($gd == 'A') ? 'selected' : ''; ?>>A</option>
                                            <option value="B" <?= ($gd == 'B') ? 'selected' : ''; ?>>B</option>
                                            <option value="AB" <?= ($gd == 'AB') ? 'selected' : ''; ?>>AB</option>
                                            <option value="O" <?= ($gd == 'O') ? 'selected' : ''; ?>>O</option>
                                            <option value="Tidak Diketahui" <?= ($gd == 'Tidak Diketahui' || $gd == '') ? 'selected' : ''; ?>>Tidak Diketahui</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 col-12">
                                    <h5 class="mt-2 text-primary">Kontak Darurat (Emergency Contact)</h5>
                                    <hr class="mt-0">

                                    <div class="mb-3">
                                        <label class="form-label">Nama Kontak Darurat</label>
                                        <input type="text" class="form-control" name="kontak_darurat_nama" 
                                               value="<?= getValue($riwayat_medis_data, 'kontak_darurat_nama') ?>" placeholder="cth: Istri/Suami/Ayah">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nomor Telepon Darurat</label>
                                        <input type="text" class="form-control" name="kontak_darurat_telepon" 
                                               value="<?= getValue($riwayat_medis_data, 'kontak_darurat_telepon') ?>" placeholder="cth: 0812xxxxxxxx">
                                    </div>
                                    
                                    <div class="mb-3" style="height: 100px;"></div>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-success me-1 mb-1">Simpan Riwayat Medis</button>
                                <a href="karyawan.php" class="btn btn-light-secondary mb-1">Batal/Kembali</a>
                            </div>
                        </form>
                        <?php else: ?>
                             <div class="alert alert-warning">ID Card tidak valid. Silakan kembali ke halaman Data Karyawan untuk memilih data yang benar.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

<!-- Tempat Isi Data -->
            <footer>
    <div class="footer clearfix mb-0 text-muted">
        <div class="float-start">
            <p>2023 &copy; Mazer</p>
        </div>
        <div class="float-end">
            <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span>
                by <a href="https://saugi.me">IT PT. Daelim Indonesia</a></p>
        </div>
    </div>
</footer>
        </div>
    </div>
    <script src="../../assets/static/js/components/dark.js"></script>
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    
    
    <script src="../../assets/compiled/js/app.js"></script>
    

    
<!-- Need: Apexcharts -->
<script src="../../assets/extensions/apexcharts/apexcharts.min.js"></script>
<script src="../../assets/static/js/pages/dashboard.js"></script>

</body>
