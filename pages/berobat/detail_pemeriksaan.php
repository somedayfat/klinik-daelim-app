<?php
session_start();
include('../../config/koneksi.php'); 

$id_berobat = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : 0;

if (!$id_berobat) {
    header("Location: riwayat_berobat.php?error=no_id");
    exit();
}

// Query mengambil data berobat spesifik, JOIN dengan karyawan untuk detail pasien
$query = "SELECT 
            b.*, 
            k.nama,             
            k.jabatan,
            k.departemen,
            rm.penyakit_terdahulu, -- Ambil dari riwayat_medis
            rm.alergi,            -- Ambil dari riwayat_medis
            rm.golongan_darah     -- Ambil dari riwayat_medis
          FROM berobat b
          JOIN karyawan k ON b.id_card = k.id_card
          LEFT JOIN riwayat_medis rm ON b.id_card = rm.id_card -- Join dengan riwayat_medis
          WHERE b.id = '$id_berobat'";
          
$result = mysqli_query($koneksi, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    header("Location: riwayat_berobat.php?error=not_found");
    exit();
}

// Fungsi helper untuk tampilan data
function displayValue($value, $default = 'N/A') {
    return !empty($value) ? htmlspecialchars($value) : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <style>
        /* CSS untuk tampilan print */
        @media print {
            #sidebar, header, footer, .btn-print, .btn-kembali {
                display: none !important;
            }
            .page-heading {
                text-align: center;
                border-bottom: 2px solid #333;
                margin-bottom: 20px;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Detail Pemeriksaan Pasien</h3>
                <p class="text-subtitle text-muted">Informasi lengkap kunjungan medis.</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Kunjungan Medis: <?= date('d F Y H:i', strtotime($data['tanggal_berobat'])); ?></h4>
                    </div>
                    <div class="card-body">
                        
                        <div class="d-flex justify-content-between mb-4 pb-2 border-bottom">
                            <a href="riwayat_berobat.php" class="btn btn-secondary btn-kembali">
                                <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                            </a>
                            <button onclick="window.print()" class="btn btn-info btn-print">
                                <i class="bi bi-printer"></i> Cetak Laporan
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-primary mt-2">Data Pasien</h5>
                                <hr class="mt-0">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped">
                                        <tr><td>ID Card</td><td>:</td><td><strong><?= displayValue($data['id_card']); ?></strong></td></tr>
                                        <tr><td>Nama Pasien</td><td>:</td><td><strong><?= displayValue($data['nama']); ?></strong></td></tr>
                                        <tr><td>Jabatan</td><td>:</td><td><?= displayValue($data['jabatan']); ?></td></tr>
                                        <tr><td>Departemen</td><td>:</td><td><?= displayValue($data['departemen']); ?></td></tr>
                                    </table>
                                </div>

                                <h5 class="text-danger mt-4">Riwayat Kritis (Statis)</h5>
                                <hr class="mt-0">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped">
                                        <tr><td>Gol. Darah</td><td>:</td><td><?= displayValue($data['golongan_darah']); ?></td></tr>
                                        <tr><td>Penyakit Terdahulu</td><td>:</td><td><?= displayValue($data['penyakit_terdahulu'], 'Tidak Ada/Belum Diisi'); ?></td></tr>
                                        <tr><td>Alergi</td><td>:</td><td><span class="text-danger fw-bold"><?= displayValue($data['alergi'], 'TIDAK ADA'); ?></span></td></tr>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h5 class="text-success mt-2">Detail Kunjungan</h5>
                                <hr class="mt-0">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped">
                                        <tr><td>Waktu Kunjungan</td><td>:</td><td><?= date('d/m/Y H:i', strtotime($data['tanggal_berobat'])); ?></td></tr>
                                        <tr><td>Petugas Pencatat</td><td>:</td><td><?= displayValue($data['petugas']); ?></td></tr>
                                        <tr><td>Tekanan Darah</td><td>:</td><td><?= displayValue($data['tekanan_darah']); ?></td></tr>
                                        <tr><td>Suhu Tubuh</td><td>:</td><td><?= displayValue($data['suhu_tubuh']) . ' °C'; ?></td></tr>
                                    </table>
                                </div>

                                <h5 class="text-info mt-4">Hasil dan Tindakan</h5>
                                <hr class="mt-0">
                                <p class="fw-bold mb-1">Keluhan Utama:</p>
                                <p class="card-text border p-2 bg-light"><?= displayValue($data['keluhan']); ?></p>
                                
                                <p class="fw-bold mb-1 mt-3">Diagnosis:</p>
                                <p class="card-text border p-2 bg-light"><?= displayValue($data['diagnosis']); ?></p>

                                <p class="fw-bold mb-1 mt-3">Tindakan/Resep Obat:</p>
                                <p class="card-text border p-2 bg-light"><?= displayValue($data['tindakan']); ?></p>

                                <p class="fw-bold mb-1 mt-3">Rujukan:</p>
                                <p class="card-text border p-2 bg-light"><?= displayValue($data['rujukan'], 'Tidak Ada'); ?></p>
                                
                                <p class="fw-bold mb-1 mt-3">Catatan Tambahan:</p>
                                <p class="card-text border p-2 bg-light"><?= displayValue($data['catatan'], 'Tidak Ada'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/compiled/js/app.js"></script>
</body>
</html>