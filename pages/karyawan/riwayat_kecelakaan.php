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
    <title>Riwayat Laporan Kecelakaan Kerja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>

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