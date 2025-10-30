<?php
// File: form_kecelakaan_kerja.php (VERSI LENGKAP DENGAN UPLOAD & IMPROVEMENT)
session_start();
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$error = '';
$data_karyawan = null;
$id_card_cari = '';
$petugas = "Petugas K3 Budi"; 

// Folder tempat menyimpan file foto (PASTIKAN FOLDER INI ADA DAN DAPAT DITULIS)
$upload_dir = 'uploads/kecelakaan/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Helper untuk mengisi ulang form setelah POST gagal
function postValue($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default);
}

// Data pilihan statis 
$jenis_kecelakaan_options = ['Terpotong', 'Tertusuk', 'Terjatuh', 'Terkilir', 'Lain-lain'];
$status_options = ['Selesai Dirawat', 'Istirahat Mandiri', 'Rujuk Rumah Sakit', 'Rawat Inap Internal'];

// --- LOGIKA UTAMA: PENYIMPANAN DATA KECELAKAAN KERJA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_kecelakaan'])) {
    
    // 1. Ambil dan Bersihkan Data
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    
    $q_check = "SELECT k.nama FROM karyawan k WHERE k.id_card = '$id_card'";
    if (mysqli_num_rows(mysqli_query($koneksi, $q_check)) == 0) {
        $error = "Gagal menyimpan. ID Card tidak valid atau tidak ditemukan.";
    } else {
        // Lanjutkan pengambilan data
        $tanggal_kejadian = mysqli_real_escape_string($koneksi, $_POST['tanggal_kejadian']);
        $lokasi_kejadian = mysqli_real_escape_string($koneksi, $_POST['lokasi_kejadian']);
        $jenis_kecelakaan = mysqli_real_escape_string($koneksi, $_POST['jenis_kecelakaan']);
        $bagian_tubuh = mysqli_real_escape_string($koneksi, $_POST['bagian_tubuh']);
        $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
        $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
        $lama_istirahat = mysqli_real_escape_string($koneksi, $_POST['lama_istirahat']);
        $status = mysqli_real_escape_string($koneksi, $_POST['status']);
        
        // --- DATA BARU ---
        $tindakan_pencegahan = mysqli_real_escape_string($koneksi, $_POST['tindakan_pencegahan']);
        $file_foto = ''; // Inisialisasi nama file foto

        // 2. Proses Upload Foto
        if (isset($_FILES['foto_kecelakaan']) && $_FILES['foto_kecelakaan']['error'] == UPLOAD_ERR_OK) {
            $file_name = $_FILES['foto_kecelakaan']['name'];
            $file_tmp = $_FILES['foto_kecelakaan']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png'];

            if (!in_array($file_ext, $allowed_ext)) {
                $error = "Gagal upload. Hanya file JPG, JPEG, dan PNG yang diizinkan.";
            } elseif ($_FILES['foto_kecelakaan']['size'] > 5000000) { // Batas 5MB
                $error = "Gagal upload. Ukuran file maksimal 5MB.";
            } else {
                // Buat nama file unik: IDCard_Tanggal_Timestamp.ext
                $new_file_name = $id_card . '_' . date('Ymd_His') . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $file_path)) {
                    $file_foto = $new_file_name; // Simpan hanya nama file ke database
                } else {
                    $error = "Gagal memindahkan file upload ke folder tujuan.";
                }
            }
        }
        
        // 3. Simpan ke Database jika tidak ada error
        if (empty($error)) {
            $query_kecelakaan = "INSERT INTO kecelakaan_kerja (
                id_card, tanggal_kejadian, lokasi_kejadian, jenis_kecelakaan, 
                bagian_tubuh, deskripsi, tindakan, lama_istirahat, status, 
                tindakan_pencegahan, file_foto, petugas, created_at
            ) VALUES (
                '$id_card', '$tanggal_kejadian', '$lokasi_kejadian', '$jenis_kecelakaan', 
                '$bagian_tubuh', '$deskripsi', '$tindakan', '$lama_istirahat', '$status', 
                '$tindakan_pencegahan', '$file_foto', '$petugas', NOW()
            )";

            if (mysqli_query($koneksi, $query_kecelakaan)) {
                header("Location: riwayat_kecelakaan.php?status=success_add");
                exit();
            } else {
                // Jika gagal simpan DB, hapus file yang sudah terupload (jika ada)
                if (!empty($file_foto)) {
                    unlink($upload_dir . $file_foto);
                }
                $error = "Gagal menyimpan data kecelakaan: " . mysqli_error($koneksi);
            }
        }
    }
}

// --- LOGIKA PENCARIAN KARYAWAN (SAMA SEPERTI SEBELUMNYA) ---
if (isset($_POST['id_card']) || isset($_GET['id_card_selected'])) {
    $id_card_cari = mysqli_real_escape_string($koneksi, $_POST['id_card'] ?? $_GET['id_card_selected']);
    if (!empty($id_card_cari)) {
        $q_karyawan = "SELECT id_card, nama, jabatan, departemen FROM karyawan WHERE id_card = '$id_card_cari'";
        $r_karyawan = mysqli_query($koneksi, $q_karyawan);
        $data_karyawan = mysqli_fetch_assoc($r_karyawan);
        if ($data_karyawan) {
             $id_card_cari = $data_karyawan['id_card'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Input Laporan Kecelakaan Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <link rel="shortcut icon" href="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2033%2034'%20fill-rule='evenodd'%20stroke-linejoin='round'%20stroke-miterlimit='2'%20xmlns:v='https://vecta.io/nano'%3e%3cpath%20d='M3%2027.472c0%204.409%206.18%205.552%2013.5%205.552%207.281%200%2013.5-1.103%2013.5-5.513s-6.179-5.552-13.5-5.552c-7.281%200-13.5%201.103-13.5%205.513z'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3ccircle%20cx='16.5'%20cy='8.8'%20r='8.8'%20fill='%2341bbdd'/%3e%3c/svg%3e" type="image/x-icon">
    <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
    
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
</head>

<body>
    <script src="../../assets/static/js/initTheme.js"></script>
    <div id="app">
        <div id="sidebar"></div>
        <div class="sidebar-wrapper active">
    <div class="sidebar-header position-relative">
        <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
                <a href="../../"><img src="../../assets/images/logo.PNG" alt="Logo" srcset=""></a>
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
                <a href="../../" class='sidebar-link'>
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
                

            </li>
            
            <li
                class="sidebar-item">
                <a href="../karyawan/karyawan.php" class='sidebar-link'>
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
                        <a href="../berobat/riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                        
                    </li>
                    
                    <!-- <li class="submenu-item  ">
                        <a href="pages/berobat/riwayat_berobat.php" class="submenu-link">Riwayat Medis</a>
                        
                    </li>
                     -->
                    <li class="submenu-item  ">
                        <a href="../karyawan/riwayat_kecelakaan.php" class="submenu-link">Kecelakaan Kerja</a>
                        
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
                        <a href="../obat/master_obat.php" class="submenu-link">Data Obat</a>
                        
                    </li>
                    
                    <li class="submenu-item  ">
                        <a href="../obat/laporan_transaksi_obat.php" class="submenu-link">Laporan Transaksi Obat</a>                     
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
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Input Laporan Kecelakaan Kerja</h3>
                <p class="text-subtitle text-muted">Formulir untuk mencatat insiden kecelakaan kerja dan rekomendasi perbaikan.</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Form Laporan Kecelakaan & HSE</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="form_kecelakaan_kerja.php" method="POST" enctype="multipart/form-data">
                            
                            <div class="row mb-4 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Cari & Pilih Karyawan</label>
                                    <select class="form-control" id="select_id_card" required>
                                        <?php if ($data_karyawan): ?>
                                        <option value="<?= htmlspecialchars($data_karyawan['id_card']); ?>" selected>
                                            <?= htmlspecialchars($data_karyawan['nama'] . ' (' . $data_karyawan['id_card'] . ' - ' . $data_karyawan['jabatan'] . ')'); ?>
                                        </option>
                                        <?php endif; ?>
                                    </select>
                                    <input type="hidden" name="id_card" id="hidden_id_card" value="<?= htmlspecialchars($data_karyawan['id_card'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Detail Karyawan</label>
                                    <input type="text" class="form-control" id="nama_pasien_display" 
                                            value="<?= htmlspecialchars($data_karyawan['nama'] ?? 'N/A'); ?> (<?= htmlspecialchars($data_karyawan['departemen'] ?? 'N/A'); ?>)" readonly>
                                </div>
                            </div>
                            
                            <?php $is_disabled_style = $data_karyawan ? '' : 'pointer-events: none; opacity: 0.6;'; ?>
                            <div class="row" id="form_kecelakaan_detail" style="<?= $is_disabled_style; ?>">
                                
                                <div class="col-md-6 border-end">
                                    <h5 class="text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i> Data Kejadian & Medis</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal Kejadian *</label>
                                        <input type="date" class="form-control" name="tanggal_kejadian" 
                                                value="<?= postValue('tanggal_kejadian', date('Y-m-d')); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Lokasi Kejadian *</label>
                                        <input type="text" class="form-control" name="lokasi_kejadian" placeholder="Cth: Area Produksi Blok A"
                                                value="<?= postValue('lokasi_kejadian'); ?>" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Jenis Kecelakaan *</label>
                                            <select class="form-select" name="jenis_kecelakaan" required>
                                                <option value="">Pilih Jenis</option>
                                                <?php foreach ($jenis_kecelakaan_options as $option): 
                                                    $selected = (postValue('jenis_kecelakaan') == $option) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $option ?>" <?= $selected ?>><?= $option ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Bagian Tubuh Terluka *</label>
                                            <input type="text" class="form-control" name="bagian_tubuh" placeholder="Cth: Jari telunjuk kiri"
                                                    value="<?= postValue('bagian_tubuh'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi Kejadian (Kronologi) *</label>
                                        <textarea class="form-control" name="deskripsi" rows="3" placeholder="Jelaskan kronologi singkat kejadian." 
                                                required><?= postValue('deskripsi'); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tindakan Medis yang Diberikan *</label>
                                        <textarea class="form-control" name="tindakan" rows="3" placeholder="Pertolongan pertama/medis yang telah diberikan." 
                                                required><?= postValue('tindakan'); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Lama Istirahat (Hari) *</label>
                                            <input type="number" class="form-control" name="lama_istirahat" min="0" placeholder="0 jika tidak ada" 
                                                    value="<?= postValue('lama_istirahat', 0); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Status Penanganan *</label>
                                            <select class="form-select" name="status" required>
                                                <option value="">Pilih Status</option>
                                                <?php foreach ($status_options as $option): 
                                                    $selected = (postValue('status') == $option) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $option ?>" <?= $selected ?>><?= $option ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="text-primary"><i class="bi bi-shield-check me-1"></i> Tindakan Pencegahan (HSE)</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rekomendasi Tindakan Pencegahan *</label>
                                        <textarea class="form-control" name="tindakan_pencegahan" rows="4" 
                                                placeholder="Cth: Memperbaiki pencahayaan area, Memberikan training APD ulang, Mengganti mesin yang usang." 
                                                required><?= postValue('tindakan_pencegahan'); ?></textarea>
                                        <small class="text-muted">Diisi oleh Petugas Klinik/HSE untuk mencegah terulangnya kejadian serupa.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i class="bi bi-camera me-1"></i> Foto Bukti Kecelakaan (Maks 5MB)</label>
                                        <input class="form-control" type="file" name="foto_kecelakaan" accept="image/jpeg, image/png">
                                        <small class="text-muted">Opsional, untuk dokumentasi visual.</small>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex justify-content-end border-top pt-3 mt-4">
                                <button type="submit" name="simpan_kecelakaan" class="btn btn-primary me-1 mb-1" id="btn_simpan" <?= $data_karyawan ? '' : 'disabled'; ?>>
                                    <i class="bi bi-file-earmark-check me-1"></i> Simpan Laporan Kecelakaan & HSE
                                </button>
                                <a href="riwayat_kecelakaan.php" class="btn btn-secondary me-1 mb-1">Kembali</a>
                                <button type="reset" class="btn btn-light-secondary mb-1">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
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
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>

    <script>
    $(document).ready(function() {
        // =============================================
        // SELECT2 PENCARIAN KARYAWAN (SAMA SEPERTI SEBELUMNYA)
        // Pastikan api_karyawan.php sudah berfungsi dengan benar.
        // =============================================
        $('#select_id_card').select2({
            placeholder: 'Ketik Nama atau ID Card Karyawan...',
            allowClear: true,
            ajax: {
                url: 'api_karyawan.php', 
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { query: params.term };
                },
                processResults: function (data) { 
                    return { results: data.results || [] }; 
                },
                cache: true
            },
            minimumInputLength: 2,
            templateSelection: function (data) {
                if (!data.id) return data.text;
                return data.text.split(' - ')[0] + ' (' + data.id + ')'; 
            }
        });

        // Event saat karyawan dipilih: REDIRECT URL
        $('#select_id_card').on('select2:select', function (e) {
            const data = e.params.data;
            $('#hidden_id_card').val(data.id);
            window.location.href = 'form_kecelakaan_kerja.php?id_card_selected=' + data.id;
        });

        // Event saat karyawan di-clear: Matikan form
        $('#select_id_card').on('select2:unselect', function (e) {
            $('#hidden_id_card').val('');
            $('#nama_pasien_display').val('N/A');
            $('#form_kecelakaan_detail').css({ 'pointer-events': 'none', 'opacity': '0.6' });
            $('#btn_simpan').prop('disabled', true);
            window.location.href = 'form_kecelakaan_kerja.php';
        });

    });
    </script>
</body>
</html>