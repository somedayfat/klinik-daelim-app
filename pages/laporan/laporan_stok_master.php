<?php
// File: laporan_stok_master.php (Laporan Stok dan Nilai Aset)
session_start();
// Pastikan path koneksi sudah benar!
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

// Cek apakah mode cetak
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';

// --- QUERY UTAMA LAPORAN STOK MASTER ---
// Mengambil semua data obat dan menghitung nilai aset
$query_laporan = "
    SELECT 
        kode_obat,
        nama_obat,
        satuan,
        stok_tersedia,
        stok_minimum,
        harga_satuan,
        tanggal_kadaluarsa,
        (stok_tersedia * harga_satuan) AS nilai_aset
    FROM 
        obat
    ORDER BY 
        nama_obat ASC
";
$result_laporan = mysqli_query($koneksi, $query_laporan);

// Inisialisasi data laporan dan total nilai aset
$data_laporan = [];
$grand_total_aset = 0;
$total_item_low_stock = 0;

if ($result_laporan) {
    while ($row = mysqli_fetch_assoc($result_laporan)) {
        $data_laporan[] = $row;
        $grand_total_aset += floatval($row['nilai_aset']);
        
        // Cek Stok Minimum
        if (intval($row['stok_tersedia']) <= intval($row['stok_minimum'])) {
            $total_item_low_stock++;
        }
    }
} else {
    // Penanganan error jika query gagal
    // die("Query Gagal: " . mysqli_error($koneksi));
}

// --- LOGIKA EKSPOR EXCEL (CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    $filename = "Stok_Master_Obat_Aset_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, [
        'Kode Obat', 
        'Nama Obat', 
        'Satuan', 
        'Harga Satuan (Rp)', 
        'Stok Tersedia', 
        'Stok Minimum', 
        'Tanggal Kadaluarsa', 
        'Status Stok',
        'Nilai Aset (Rp)'
    ]);
    
    // Data Baris
    foreach ($data_laporan as $data) {
        // Logika Status Stok
        $stok_status = (intval($data['stok_tersedia']) <= intval($data['stok_minimum'])) ? 'Rendah/Habis' : 'Aman';
        $kadaluarsa_status = (strtotime($data['tanggal_kadaluarsa']) < strtotime('+3 months') && strtotime($data['tanggal_kadaluarsa']) >= time()) ? 'Mendekati' : 
                             (strtotime($data['tanggal_kadaluarsa']) < time() ? 'Kadaluarsa' : 'Aman');
        
        fputcsv($output, [
            $data['kode_obat'],
            $data['nama_obat'],
            $data['satuan'],
            number_format($data['harga_satuan'], 0, ',', '.'),
            $data['stok_tersedia'],
            $data['stok_minimum'],
            $data['tanggal_kadaluarsa'],
            $stok_status . ' | KAD: ' . $kadaluarsa_status,
            number_format($data['nilai_aset'], 0, ',', '.')
        ]);
    }
    
    fputcsv($output, ['---', '---', '---', '---', '---', '---', '---', 'GRAND TOTAL ASET (Rp)', number_format($grand_total_aset, 0, ',', '.')]);

    fclose($output);
    exit();
}

// --- TAMPILAN PRINT JIKA DITEKAN ---
if ($is_print_view) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cetak Laporan Stok Obat Master</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header-laporan { text-align: center; margin-bottom: 20px; }
        .header-laporan h3 { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
    </style>
</head>
<body onload="window.print()">
    <div class="header-laporan">
        <h3>LAPORAN STOK OBAT MASTER & NILAI ASET INVENTARIS</h3>
        <p>Tanggal Laporan: **<?= date('d/m/Y H:i:s') ?>**</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Kode/Nama Obat</th>
                <th class="text-right">Stok</th>
                <th>Satuan</th>
                <th class="text-right">Hrg/Unit (Rp)</th>
                <th class="text-right">Nilai Aset (Rp)</th>
                <th>Kadaluarsa</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_laporan as $data): 
                $is_low_stock = intval($data['stok_tersedia']) <= intval($data['stok_minimum']);
                $kadaluarsa_time = strtotime($data['tanggal_kadaluarsa']);
                $is_expired = $kadaluarsa_time < time();
                $is_near_expiry = !$is_expired && $kadaluarsa_time < strtotime('+3 months');
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($data['nama_obat']) ?><br><small class="text-muted"><?= htmlspecialchars($data['kode_obat']) ?></small></td>
                <td class="text-right" style="<?= $is_low_stock ? 'background-color: #ffcccc;' : '' ?>"><?= number_format($data['stok_tersedia'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($data['satuan']) ?></td>
                <td class="text-right"><?= number_format($data['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right"><strong><?= number_format($data['nilai_aset'], 0, ',', '.') ?></strong></td>
                <td style="<?= $is_expired ? 'background-color: #f8d7da;' : ($is_near_expiry ? 'background-color: #fff3cd;' : '') ?>">
                    <?= date('d/m/Y', $kadaluarsa_time) ?>
                </td>
                <td>
                    <?= $is_expired ? 'KADALUARSA' : ($is_near_expiry ? 'MENDK. KAD' : 'Aman') ?> |
                    <?= $is_low_stock ? 'STOK RENDAH' : 'STOK AMAN' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="5" class="text-right"><strong>GRAND TOTAL NILAI ASET INVENTARIS</strong></td>
                <td class="text-right" style="background-color: #d4edda;">
                    <strong>Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></strong>
                </td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Laporan Stok Obat Master</title>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        .card-elegant { box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); border-radius: 12px; }
        .summary-card-total {
            background: linear-gradient(45deg, #d3eafc, #e6f7ff); 
            color: #0d6efd;
            border-left: 5px solid #0d6efd;
        }
        .summary-card-low {
            background: linear-gradient(45deg, #fce5d3, #fff2e6); 
            color: #ffc107;
            border-left: 5px solid #ffc107;
        }
        .summary-card {
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: 100%; 
        }
    </style>

</head>
<body>
    <div id="app">
        <div id="sidebar"></div>
        
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>📊 Laporan Stok Obat Master & Nilai Aset</h3>
                <p class="text-subtitle text-muted">Melihat daftar stok obat dan menghitung total nilai aset inventaris farmasi.</p>
            </div>
            
            <section class="section">

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="summary-card summary-card-total d-flex align-items-center">
                            <i class="bi bi-currency-dollar fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">GRAND TOTAL NILAI ASET INVENTARIS</small>
                                <h3 class="mb-0 fw-bold">Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card summary-card-low d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Jumlah Item Stok Rendah</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_item_low_stock, 0, ',', '.') ?> Item</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-elegant">
                    <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-box-seam me-2"></i> Daftar Stok Obat Saat Ini (<?= date('d/m/Y') ?>)</h5>
                        <div class="btn-group">
                            <a href="laporan_stok_master.php?action=export_csv" class="btn btn-success btn-sm me-2">
                                <i class="bi bi-file-earmark-excel"></i> Export CSV
                            </a>
                            <a href="laporan_stok_master.php?print=true" target="_blank" class="btn btn-danger btn-sm">
                                <i class="bi bi-printer"></i> Cetak
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        <table class="table table-striped table-hover" id="table1">
                            <thead class="table-primary"> 
                                <tr>
                                    <th>#</th>
                                    <th>Kode/Nama Obat</th>
                                    <th>Stok (Unit)</th>
                                    <th>Min. Stok</th>
                                    <th>Hrg/Unit (Rp)</th>
                                    <th>Nilai Aset (Rp)</th>
                                    <th>Tgl. Kadaluarsa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_laporan as $data): ?>
                                <?php 
                                    $is_low_stock = intval($data['stok_tersedia']) <= intval($data['stok_minimum']);
                                    $kadaluarsa_time = strtotime($data['tanggal_kadaluarsa']);
                                    $is_expired = $kadaluarsa_time < time();
                                    $is_near_expiry = !$is_expired && $kadaluarsa_time < strtotime('+3 months');
                                    
                                    // Tentukan class badge
                                    $stok_badge = $is_low_stock ? 'danger' : 'success';
                                    $expiry_badge = $is_expired ? 'dark' : ($is_near_expiry ? 'warning' : 'success');
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    
                                    <td>
                                        <strong><?= htmlspecialchars($data['nama_obat']); ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($data['kode_obat']); ?></small>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-<?= $stok_badge ?>"><?= number_format($data['stok_tersedia'], 0, ',', '.') ?></span> 
                                        <?= htmlspecialchars($data['satuan']) ?>
                                    </td>
                                    
                                    <td class="text-muted"><?= number_format($data['stok_minimum'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-end text-success"><?= number_format($data['harga_satuan'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-end fw-bold">Rp<?= number_format($data['nilai_aset'], 0, ',', '.') ?></td>
                                    
                                    <td>
                                        <span class="badge bg-<?= $expiry_badge ?>"><?= date('d/m/Y', $kadaluarsa_time) ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-secondary"><?= $is_expired ? 'KADALUARSA' : 'Aman' ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>    
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end bg-light">GRAND TOTAL NILAI ASET INVENTARIS</th>
                                    <th class="text-end bg-success text-white">Rp<?= number_format($grand_total_aset, 0, ',', '.') ?></th>
                                    <th colspan="2" class="bg-light"></th>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <?php if (count($data_laporan) == 0): ?>
                            <div class="alert alert-light-warning text-center mt-3">
                                <i class="bi bi-info-circle me-1"></i> Tidak ada data obat ditemukan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <footer>
        <div class="footer clearfix mb-0 text-muted">
            <div class="float-start">
            <p>2025 &copy; Daelim</p>
        </div>
        <div class="float-end">
            <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span>
                by <a href="https://daelim.id">IT PT. Daelim Indonesia</a></p>
        </div>
    </footer>
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>