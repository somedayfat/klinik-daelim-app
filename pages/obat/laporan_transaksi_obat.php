<?php
// File: laporan_transaksi_obat.php
session_start();
// Pastikan path koneksi sudah benar!
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

// --- PENGATURAN DAN KONVERSI FILTER ---
$default_tanggal_awal = date('Y-m-01');
$default_tanggal_akhir = date('Y-m-d');

// Ambil input dari user (format default HTML date input adalah YYYY-MM-DD)
$tanggal_awal_input = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : $default_tanggal_awal;
$tanggal_akhir_input = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : $default_tanggal_akhir;
$filter_jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($koneksi, $_GET['jenis']) : '';

/**
 * Fungsi untuk mengkonversi string tanggal input (Y-m-d) menjadi format MySQL (Y-m-d) yang aman.
 * Jika input database Anda benar-benar DD/MM/YYYY (VARCHAR), Anda perlu menyesuaikan SQL di bawah.
 * Asumsi: Kolom tanggal_transaksi bertipe DATETIME/TIMESTAMP.
 */
function convert_to_mysql_date($date_input) {
    // Mencoba parsing Y-m-d (format standar HTML input type="date")
    $date_obj = DateTime::createFromFormat('Y-m-d', $date_input);
    if ($date_obj) {
        return $date_obj->format('Y-m-d');
    }
    return date('Y-m-d'); // Fallback jika gagal
}

// Konversi tanggal input ke format YYYY-MM-DD yang siap digunakan di MySQL
$tanggal_awal = convert_to_mysql_date($tanggal_awal_input);
$tanggal_akhir = convert_to_mysql_date($tanggal_akhir_input);

// Jika tombol cetak ditekan
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';

// --- QUERY UTAMA LAPORAN MUTASI STOK (FIXED FILTER LOGIC) ---

// Membuat batasan waktu yang pasti untuk filter:
// Tgl Awal harus dari 00:00:00
$datetime_awal = $tanggal_awal . ' 00:00:00'; 
// Tgl Akhir harus sampai 23:59:59 (untuk mencakup seluruh hari)
$datetime_akhir = $tanggal_akhir . ' 23:59:59'; 

$sql_filter = "
    WHERE t.tanggal_transaksi BETWEEN '$datetime_awal' AND '$datetime_akhir'
";

if (!empty($filter_jenis)) {
    $sql_filter .= " AND t.jenis_transaksi = '$filter_jenis'";
}

$query_laporan = "
    SELECT 
        t.*, 
        o.kode_obat, 
        o.nama_obat, 
        o.satuan 
    FROM 
        transaksi_obat t
    JOIN 
        obat o ON t.obat_id = o.id
    $sql_filter
    ORDER BY 
        t.tanggal_transaksi DESC
";
$result_laporan = mysqli_query($koneksi, $query_laporan);

// Hitung total ringkasan
// GANTI BLOCK PERHITUNGAN TOTAL INI
// Hitung total ringkasan
$total_masuk = 0;
$total_keluar = 0;
$data_laporan = [];

if ($result_laporan) {
    while ($row = mysqli_fetch_assoc($result_laporan)) {
        $data_laporan[] = $row;
        
        // *** PERUBAHAN KRUSIAL: Tambahkan intval() dan trim() ***
        // Trim() menghilangkan spasi tak terlihat, strtoupper() memastikan case-sensitive-ness
        if (strtoupper(trim($row['jenis_transaksi'])) == 'MASUK') { 
            $total_masuk += intval($row['jumlah']); // Memaksa nilai menjadi integer
        } elseif (strtoupper(trim($row['jenis_transaksi'])) == 'KELUAR') {
            $total_keluar += intval($row['jumlah']); // Memaksa nilai menjadi integer
        }
    }
}

// --- TAMPILAN PRINT JIKA DITEKAN (dihilangkan dari sini demi keringkasan) ---
if ($is_print_view) {
// ... (Kode Print View tetap sama seperti sebelumnya, pastikan ada di file Anda) ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cetak Laporan Mutasi Obat</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .header-laporan { text-align: center; margin-bottom: 20px; }
        .header-laporan h3 { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body onload="window.print()">
    <div class="header-laporan">
        <h3>LAPORAN MUTASI STOK OBAT</h3>
        <p>Periode: **<?= date('d/m/Y', strtotime($tanggal_awal)) ?>** s/d **<?= date('d/m/Y', strtotime($tanggal_akhir)) ?>**</p>
        <p>Jenis Transaksi: **<?= empty($filter_jenis) ? 'Semua' : $filter_jenis ?>**</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Tanggal/Waktu</th>
                <th>Kode/Nama Obat</th>
                <th>Jenis</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Stok Awal</th>
                <th class="text-right">Stok Akhir</th>
                <th>Keterangan</th>
                <th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_laporan as $data): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= date('d/m/Y H:i', strtotime($data['tanggal_transaksi'])) ?></td>
                <td><?= htmlspecialchars($data['nama_obat']) ?><br><small class="text-muted"><?= htmlspecialchars($data['kode_obat']) ?></small></td>
                <td><?= $data['jenis_transaksi'] == 'MASUK' ? 'MASUK' : 'KELUAR' ?></td>
                <td class="text-right"><?= number_format($data['jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($data['satuan']) ?></td>
                <td class="text-right"><?= number_format($data['stok_sebelum'], 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($data['stok_sesudah'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($data['petugas']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="4" class="text-right"><strong>TOTAL MUTASI STOK</strong></td>
                <td class="text-right" style="background-color: #e6ffec;">
                    **MASUK: <?= number_format($total_masuk, 0, ',', '.') ?>**
                </td>
                <td colspan="2" class="text-right" style="background-color: #ffe6e6;">
                    **KELUAR: <?= number_format($total_keluar, 0, ',', '.') ?>**
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
    <title>Laporan Mutasi Stok Obat</title>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        /* CSS Kustom untuk Tampilan yang Lebih Elegan */
        .card-elegant {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); 
            border-radius: 12px;
        }
        .header-primary-light {
            background-color: #f0f3ff; 
            color: #3b5a9b; 
            border-bottom: 2px solid #3b5a9b;
        }
        .summary-card-masuk {
            background: linear-gradient(45deg, #d4edda, #e6ffe6); 
            color: #155724;
            border-left: 5px solid #28a745;
        }
        .summary-card-keluar {
            background: linear-gradient(45deg, #f8d7da, #ffe6e6); 
            color: #721c24;
            border-left: 5px solid #dc3545;
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
                <h3>Laporan Mutasi Stok Obat 💊</h3>
                <p class="text-subtitle text-muted">Mencatat dan menganalisis riwayat penambahan dan pengurangan stok obat.</p>
            </div>
            
            <section class="section">
                
                <div class="card card-elegant">
                    <div class="card-header header-primary-light">
                        <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i> Filter Data Mutasi</h5>
                    </div>
                    <div class="card-body">
                        <form action="laporan_transaksi_obat.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Awal</label>
                                <input type="date" class="form-control" name="tgl_awal" value="<?= $tanggal_awal_input ?>" required> 
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Tanggal Akhir</label>
                                <input type="date" class="form-control" name="tgl_akhir" value="<?= $tanggal_akhir_input ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Jenis Transaksi</label>
                                <select class="form-select" name="jenis">
                                    <option value="">-- Semua Jenis --</option>
                                    <option value="MASUK" <?= ($filter_jenis == 'MASUK') ? 'selected' : ''; ?>>Stok Masuk (➕)</option>
                                    <option value="KELUAR" <?= ($filter_jenis == 'KELUAR') ? 'selected' : ''; ?>>Stok Keluar (➖)</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Tampilkan</button>
                                <a href="laporan_transaksi_obat.php?tgl_awal=<?= $tanggal_awal ?>&tgl_akhir=<?= $tanggal_akhir ?>&jenis=<?= $filter_jenis ?>&print=true" target="_blank" class="btn btn-outline-danger"><i class="bi bi-printer"></i> Cetak</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="summary-card summary-card-masuk d-flex align-items-center">
                            <i class="bi bi-box-arrow-in-up-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Total Stok <strong>Masuk</strong> (Unit)</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_masuk, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="summary-card summary-card-keluar d-flex align-items-center">
                            <i class="bi bi-box-arrow-out-down-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <small class="text-muted">Total Stok <strong>Keluar</strong> (Unit)</small>
                                <h3 class="mb-0 fw-bold"><?= number_format($total_keluar, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-elegant">
                    <div class="card-header header-primary-light">
                        <h5 class="card-title mb-0"><i class="bi bi-list-columns-reverse me-2"></i> Detail Transaksi (<?= date('d/m/Y', strtotime($tanggal_awal)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_akhir)) ?>)</h5>
                    </div>
                    <div class="card-body">
                        
                        <table class="table table-striped table-hover" id="table1">
                            <thead class="table-primary"> 
                                <tr>
                                    <th>#</th>
                                    <th>Waktu</th>
                                    <th>Obat (Kode)</th>
                                    <th>Jenis</th>
                                    <th>Jumlah (Unit)</th>
                                    <th>Stok Awal</th>
                                    <th>Stok Akhir</th>
                                    <th>Keterangan</th>
                                    <th>Petugas</th>
                                </tr>
                            </thead>
                             <tbody>
                                <?php $no = 1; foreach ($data_laporan as $data): ?>
                                <?php 
                                    // Logika untuk menentukan warna badge dan class baris
                                    $is_masuk = strtoupper(trim($data['jenis_transaksi'])) == 'MASUK';
                                    
                                    // PENTING: Mengubah warna badge
                                    $jenis_badge = $is_masuk ? 'success' : 'danger'; // success = hijau, danger = merah
                                    
                                    $jenis_icon = $is_masuk ? '➕' : '➖';
                                    $row_class = $is_masuk ? 'tr-masuk' : 'tr-keluar';
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><?= $no++; ?></td>
                                    
                                    <td><?= date('d/m/Y H:i', strtotime($data['tanggal_transaksi'])) ?></td>
                                    
                                    <td>
                                        <strong><?= htmlspecialchars($data['nama_obat']); ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($data['kode_obat']); ?></small>
                                    </td>
                                    
                                    <td><span class="badge bg-<?= $jenis_badge ?>"><?= $jenis_icon ?> <?= htmlspecialchars($data['jenis_transaksi']) ?></span></td>
                                    
                                    <td><strong class="text-<?= $jenis_badge ?>"><?= number_format($data['jumlah'], 0, ',', '.') ?></strong> <?= htmlspecialchars($data['satuan']) ?></td>
                                    <td class="text-muted"><?= number_format($data['stok_sebelum'], 0, ',', '.') ?></td>
                                    <td class="fw-bold"><?= number_format($data['stok_sesudah'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($data['petugas']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>    
                        </table>
                        
                        <?php if (count($data_laporan) == 0): ?>
                            <div class="alert alert-light-warning text-center mt-3">
                                <i class="bi bi-info-circle me-1"></i> Tidak ada data mutasi obat dalam rentang filter yang dipilih.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
    
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script src="../../assets/static/js/pages/simple-datatables.js"></script> 
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>