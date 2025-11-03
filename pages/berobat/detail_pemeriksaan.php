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
                        <li class="sidebar-item active">
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
                                    <a href="riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="../karyawan/form_kecelakaan_kerja.php" class="submenu-link">Kecelakaan Kerja</a>
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
                class="sidebar-item has-sub ">
                <a href="#" class='sidebar-link'>
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Laporan Klinik</span>
                </a>
                <ul class="submenu ">
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_berobat.php" class="submenu-link">Laporan Berobat</a>                     
                    </li>       
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_obat.php" class="submenu-link">Laporan Obat</a>                     
                    </li>         
                    <li class="submenu-item  ">
                        <a href="pages/laporan/form_laporan_bulanan.php" class="submenu-link">Laporan Kecelakaan Kerja</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_tren_berobat.php" class="submenu-link">Statistik Berobat</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="pages/laporan/laporan_tren_kecelakaan.php" class="submenu-link">Statistik Kecelakaan Kerja</a>
                    </li>
                </ul>
            </li>
                        <li class="sidebar-item">
                            <a href="logout.php" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>


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