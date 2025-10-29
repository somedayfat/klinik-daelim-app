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
$jenis_kecelakaan_options = ['Terpotong', 'Tertusuk', 'Terjatuh', 'Terkilir', 'Lain-lain'];
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