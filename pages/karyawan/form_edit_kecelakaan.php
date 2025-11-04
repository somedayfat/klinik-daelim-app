<?php
// File: form_edit_kecelakaan.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';
$data = null;
$id_kecelakaan = $_GET['id'] ?? null; 
$petugas = "Petugas K3 Budi"; // Ambil dari session jika ada

$upload_dir = 'uploads/kecelakaan/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Helper untuk mengisi ulang form
function postValue($key, $default = '') {
    global $data;
    if (isset($_POST[$key])) {
        return htmlspecialchars($_POST[$key]);
    }
    return htmlspecialchars($data[$key] ?? $default);
}

// Data pilihan statis 
$jenis_kecelakaan_options = ['Terpotong', 'Tertusuk', 'Terjatuh', 'Tersayat', 'Terjepit', 'Lain-lain'];
$status_options = ['Selesai Dirawat', 'Istirahat Mandiri', 'Rujuk Rumah Sakit', 'Rawat Inap Internal'];

// --- LOGIKA UTAMA: AMBIL DATA LAMA ---
if ($id_kecelakaan) {
    $id_kecelakaan_safe = mysqli_real_escape_string($koneksi, $id_kecelakaan);
    $query_data = "
        SELECT 
            kk.*, 
            k.nama, 
            k.jabatan, 
            k.departemen 
        FROM 
            kecelakaan_kerja kk
        JOIN 
            karyawan k ON kk.id_card = k.id_card
        WHERE 
            kk.id = '$id_kecelakaan_safe'";

    $result_data = mysqli_query($koneksi, $query_data);
    $data = mysqli_fetch_assoc($result_data);

    if (!$data) {
        $error = "Data laporan kecelakaan tidak ditemukan.";
    }
} else {
    $error = "ID Laporan tidak valid.";
}

// --- LOGIKA UTAMA: UPDATE DATA KECELAKAAN KERJA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_kecelakaan'])) {
    
    // 1. Ambil dan Bersihkan Data
    $id_kecelakaan_update = mysqli_real_escape_string($koneksi, $_POST['id_kecelakaan']);
    $tanggal_kejadian = mysqli_real_escape_string($koneksi, $_POST['tanggal_kejadian']);
    $lokasi_kejadian = mysqli_real_escape_string($koneksi, $_POST['lokasi_kejadian']);
    $jenis_kecelakaan = mysqli_real_escape_string($koneksi, $_POST['jenis_kecelakaan']);
    $bagian_tubuh = mysqli_real_escape_string($koneksi, $_POST['bagian_tubuh']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
    $lama_istirahat = mysqli_real_escape_string($koneksi, $_POST['lama_istirahat']);
    $status = mysqli_real_escape_string($koneksi, $_POST['status']);
    $tindakan_pencegahan = mysqli_real_escape_string($koneksi, $_POST['tindakan_pencegahan']);
    
    $file_foto_update = $data['file_foto']; // Pertahankan nama file lama

    // 2. Proses Upload Foto Baru (Jika ada)
    if (isset($_FILES['foto_kecelakaan']) && $_FILES['foto_kecelakaan']['error'] == UPLOAD_ERR_OK) {
        // Hapus file lama jika ada
        if (!empty($data['file_foto']) && file_exists($upload_dir . $data['file_foto'])) {
            unlink($upload_dir . $data['file_foto']);
        }

        $file_name = $_FILES['foto_kecelakaan']['name'];
        $file_tmp = $_FILES['foto_kecelakaan']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $id_card_original = $data['id_card']; // Ambil ID Card asli dari data yang dimuat

        if (!in_array($file_ext, $allowed_ext) || $_FILES['foto_kecelakaan']['size'] > 5000000) {
            $error = "Gagal upload. Hanya file JPG/PNG maksimal 5MB yang diizinkan.";
        } else {
            // Buat nama file unik baru
            $new_file_name = $id_card_original . '_' . date('Ymd_His') . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $file_path)) {
                $file_foto_update = $new_file_name; // Update nama file foto
            } else {
                $error = "Gagal memindahkan file upload.";
            }
        }
    }
    
    // 3. Simpan Update ke Database jika tidak ada error
    if (empty($error)) {
        $query_update = "UPDATE kecelakaan_kerja SET
            tanggal_kejadian = '$tanggal_kejadian', 
            lokasi_kejadian = '$lokasi_kejadian', 
            jenis_kecelakaan = '$jenis_kecelakaan', 
            bagian_tubuh = '$bagian_tubuh', 
            deskripsi = '$deskripsi', 
            tindakan = '$tindakan', 
            lama_istirahat = '$lama_istirahat', 
            status = '$status', 
            tindakan_pencegahan = '$tindakan_pencegahan', 
            file_foto = '$file_foto_update', 
            petugas = '$petugas'
        WHERE id = '$id_kecelakaan_update'";

        if (mysqli_query($koneksi, $query_update)) {
            // Refresh data setelah update berhasil
            header("Location: riwayat_kecelakaan.php?status=success_edit");
            exit();
        } else {
            $error = "Gagal mengupdate data: " . mysqli_error($koneksi);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Laporan Kecelakaan Kerja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                    <li class="submenu-item active ">
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

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Edit Laporan Kecelakaan Kerja</h3>
                <p class="text-subtitle text-muted">Formulir untuk memperbarui insiden kecelakaan kerja dan rekomendasi HSE.</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Mengedit Laporan ID: <?= htmlspecialchars($id_kecelakaan) ?></h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($data): ?>
                        <form action="form_edit_kecelakaan.php?id=<?= $id_kecelakaan ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_kecelakaan" value="<?= htmlspecialchars($id_kecelakaan) ?>">
                            
                            <div class="row mb-4 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">ID Card Karyawan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['id_card'] ?? 'N/A'); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Detail Karyawan</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['nama'] ?? 'N/A'); ?> (<?= htmlspecialchars($data['departemen'] ?? 'N/A'); ?>) - <?= htmlspecialchars($data['jabatan'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <h5 class="text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i> Data Kejadian & Medis</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal Kejadian *</label>
                                        <input type="date" class="form-control" name="tanggal_kejadian" 
                                                value="<?= postValue('tanggal_kejadian'); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Lokasi Kejadian *</label>
                                        <input type="text" class="form-control" name="lokasi_kejadian" 
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
                                            <input type="text" class="form-control" name="bagian_tubuh" 
                                                    value="<?= postValue('bagian_tubuh'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi Kejadian (Kronologi) *</label>
                                        <textarea class="form-control" name="deskripsi" rows="3" 
                                                required><?= postValue('deskripsi'); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tindakan Medis yang Diberikan *</label>
                                        <textarea class="form-control" name="tindakan" rows="3" 
                                                required><?= postValue('tindakan'); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Lama Istirahat (Hari) *</label>
                                            <input type="number" class="form-control" name="lama_istirahat" min="0" 
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
                                                required><?= postValue('tindakan_pencegahan'); ?></textarea>
                                    </div>
                                    
                                    <h5 class="text-primary mt-4"><i class="bi bi-image me-1"></i> Dokumentasi Foto</h5>
                                    <?php if (!empty($data['file_foto'])): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Foto Saat Ini:</small><br>
                                            <img src="<?= $upload_dir . htmlspecialchars($data['file_foto']) ?>" class="img-fluid rounded shadow-sm" style="max-height: 150px;">
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-light border">Foto belum diunggah.</div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Ganti Foto Bukti Kecelakaan (Maks 5MB)</label>
                                        <input class="form-control" type="file" name="foto_kecelakaan" accept="image/jpeg, image/png">
                                        <small class="text-muted">Kosongkan jika tidak ingin mengganti.</small>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex justify-content-end border-top pt-3 mt-4">
                                <button type="submit" name="update_kecelakaan" class="btn btn-warning me-1 mb-1">
                                    <i class="bi bi-pencil-square me-1"></i> Update Laporan
                                </button>
                                <a href="riwayat_kecelakaan.php" class="btn btn-secondary me-1 mb-1">Batal</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>