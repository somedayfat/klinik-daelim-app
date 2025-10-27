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

if (!$result) {
    die("Query Error: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Riwayat Pemeriksaan Pasien</h3>
                <p class="text-subtitle text-muted">Daftar lengkap riwayat berobat seluruh karyawan.</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <a href="form_pemeriksaan.php" class="btn btn-primary mb-1">
                            <i class="bi bi-plus-circle me-1"></i> Input Pemeriksaan Baru
                        </a>
                        <?php if (isset($_GET['status']) && $_GET['status'] == 'success_add'): ?>
                            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                Data pemeriksaan pasien berhasil disimpan! ✅
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <form action="riwayat_berobat.php" method="GET" class="mb-4 p-3 border rounded bg-light">
                            <h6 class="text-primary"><i class="bi bi-calendar-date"></i> Filter Berdasarkan Tanggal Kunjungan</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="tgl_awal" class="form-label">Tanggal Awal:</label>
                                    <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="tgl_akhir" class="form-label">Tanggal Akhir:</label>
                                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success me-2">Tampilkan Data</button>
                                    <?php if ($tgl_awal != ''): ?>
                                        <a href="riwayat_berobat.php" class="btn btn-warning">Reset Filter</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                        <table class="table table-striped" id="table1">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>ID-Card</th>
                                    <th>Nama Pasien</th>
                                    <th>Tgl/Waktu Berobat</th>
                                    <th>Keluhan</th>
                                    <th>Diagnosis</th>
                                    <th>Tanda Vital (TD/Suhu)</th>
                                    <th>Petugas</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($data = mysqli_fetch_assoc($result)) {
                                ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($data['id_card']); ?></td>
                                            <td><?= htmlspecialchars($data['nama']); ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($data['tanggal_berobat'])); ?></td>
                                            <td><?= htmlspecialchars($data['keluhan']); ?></td>
                                            <td><?= htmlspecialchars($data['diagnosis']); ?></td>
                                            <td><?= htmlspecialchars($data['tekanan_darah']) . ' / ' . htmlspecialchars($data['suhu_tubuh']) . '°C'; ?></td>
                                            <td><?= htmlspecialchars($data['petugas']); ?></td>
                                            <td>
                                                <a href="detail_pemeriksaan.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-info" title="Lihat Detail">Detail</a>
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
            
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>