<?php
// File: laporan_karyawan.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// Logika Ekspor Excel (CSV)
if (isset($_GET['action']) && $_GET['action'] == 'export') {
    $filename = "Laporan_Karyawan_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, [
        'ID Card/NIK', 
        'Nama', 
        'Departemen', 
        'Jabatan', 
        'Tanggal Masuk', 
        'Tempat Lahir', 
        'Tanggal Lahir', 
        'Kontak Darurat'
    ]);
    
    // Ambil Data
    $query = "
        SELECT 
            id_card, 
            nama, 
            departemen, 
            jabatan, 
            tgl_masuk, 
            tempat_lahir, 
            tgl_lahir, 
            kontak_darurat 
        FROM 
            karyawan 
        ORDER BY 
            departemen, nama ASC";
    
    $result = mysqli_query($koneksi, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        // Tulis data ke output
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Logika Tampilkan Data di Halaman
$data_karyawan = [];
$query_view = "
    SELECT 
        id_card, 
        nama, 
        departemen, 
        jabatan, 
        tgl_masuk, 
        tempat_lahir, 
        tgl_lahir, 
        kontak_darurat 
    FROM 
        karyawan 
    ORDER BY 
        departemen, nama ASC";

$result_view = mysqli_query($koneksi, $query_view);
while ($row = mysqli_fetch_assoc($result_view)) {
    $data_karyawan[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Data Karyawan</title>
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
                <h3>Laporan Data Master Karyawan</h3>
                <p class="text-subtitle text-muted">Daftar lengkap seluruh karyawan.</p>
            </div>
            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title">Data Karyawan Aktif</h4>
                        <a href="laporan_karyawan.php?action=export" class="btn btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i> Export ke Excel
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="table_karyawan">
                                <thead>
                                    <tr>
                                        <th>ID Card/NIK</th>
                                        <th>Nama</th>
                                        <th>Departemen</th>
                                        <th>Jabatan</th>
                                        <th>Tgl Masuk</th>
                                        <th>Tgl Lahir</th>
                                        <th>Kontak Darurat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_karyawan as $d): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['id_card']) ?></td>
                                        <td><?= htmlspecialchars($d['nama']) ?></td>
                                        <td><?= htmlspecialchars($d['departemen']) ?></td>
                                        <td><?= htmlspecialchars($d['jabatan']) ?></td>
                                        <td><?= date('d-m-Y', strtotime($d['tgl_masuk'])) ?></td>
                                        <td><?= date('d-m-Y', strtotime($d['tgl_lahir'])) ?></td>
                                        <td><?= htmlspecialchars($d['kontak_darurat']) ?></td>
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
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#table_karyawan').DataTable({"language": {"url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"}});
    });
    </script>
</body>
</html>