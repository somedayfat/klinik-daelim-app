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
    <?php if (!$is_print_view): ?>
    <div id="app">
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

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
                                                <a href="form_pemeriksaan.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-warning" title="Edit Data"><i class="bi bi-pencil"></i> Edit</a>
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