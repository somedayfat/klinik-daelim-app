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
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>

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
            
            <footer></footer>
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