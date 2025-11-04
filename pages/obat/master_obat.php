<?php
// File: master_obat.php
session_start();
// Pastikan path koneksi sudah benar!
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$pesan_status = '';
$tipe_alert = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id_edit = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : 0;

// --- Logika Hapus (DELETE) ---
if ($action == 'delete' && $id_edit > 0) {
    $q_delete = "DELETE FROM obat WHERE id='$id_edit'";
    if (mysqli_query($koneksi, $q_delete)) {
        header("Location: master_obat.php?status=deleted");
        exit();
    } else {
        $pesan_status = "Gagal menghapus data: " . mysqli_error($koneksi) . " ❌";
        $tipe_alert = 'danger';
    }
}

// --- Logika POST (Tambah, Edit, dan TAMBAH STOK BARU) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // =========================================================
    // A. LOGIKA TAMBAH/EDIT DATA MASTER OBAT (CRUD)
    // =========================================================
    if (!isset($_POST['action']) || $_POST['action'] != 'tambah_stok') { 
        $id_post = mysqli_real_escape_string($koneksi, $_POST['id_obat']);
        $kode = mysqli_real_escape_string($koneksi, $_POST['kode_obat']);
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama_obat']);
        $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);
        $satuan = mysqli_real_escape_string($koneksi, $_POST['satuan']);
        $stok_min = mysqli_real_escape_string($koneksi, $_POST['stok_minimum']);
        $stok_tersedia = mysqli_real_escape_string($koneksi, $_POST['stok_tersedia']);
        $harga = mysqli_real_escape_string($koneksi, $_POST['harga_satuan']);
        $kadaluarsa = mysqli_real_escape_string($koneksi, $_POST['tanggal_kadaluarsa']);

        $kadaluarsa_sql = ($kadaluarsa) ? "'$kadaluarsa'" : 'NULL';

        if ($id_post > 0) {
            // UPDATE
            $query = "UPDATE obat SET 
                        kode_obat='$kode', nama_obat='$nama', kategori='$kategori', satuan='$satuan', 
                        stok_minimum='$stok_min', stok_tersedia='$stok_tersedia', harga_satuan='$harga', 
                        tanggal_kadaluarsa=$kadaluarsa_sql, updated_at=NOW() 
                      WHERE id='$id_post'";
            $redirect_status = 'updated';
        } else {
            // INSERT
            $query = "INSERT INTO obat (kode_obat, nama_obat, kategori, satuan, stok_minimum, stok_tersedia, harga_satuan, tanggal_kadaluarsa, updated_at) 
                      VALUES ('$kode', '$nama', '$kategori', '$satuan', '$stok_min', '$stok_tersedia', '$harga', $kadaluarsa_sql, NOW())";
            $redirect_status = 'added';
        }

        if (mysqli_query($koneksi, $query)) {
            header("Location: master_obat.php?status=$redirect_status");
            exit();
        } else {
            $pesan_status = "Gagal menyimpan data: " . mysqli_error($koneksi) . " ❌";
            $tipe_alert = 'danger';
            $data_edit = $_POST;
            $data_edit['id'] = $id_post;
        }
    } 
    
    // =========================================================
    // B. LOGIKA INPUT STOK MASUK DARI MODAL
    // =========================================================
    else if (isset($_POST['action']) && $_POST['action'] == 'tambah_stok') {
        
        // 1. Ambil dan Bersihkan Data
        $obat_id = mysqli_real_escape_string($koneksi, $_POST['obat_id']);
        $jumlah_masuk = (int)$_POST['jumlah_masuk'];
        $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
        $petugas = "Admin Farmasi"; // Ganti dengan data session user yang login

        if ($jumlah_masuk <= 0) {
            $pesan_status = "Gagal. Jumlah stok masuk harus lebih dari 0. ❌";
            $tipe_alert = 'danger';
        } else {
            // Mulai Transaksi
            mysqli_begin_transaction($koneksi);
            
            try {
                // 2. Cek Stok Saat Ini (Untuk $stok_sebelum)
                $q_check_stok = "SELECT stok_tersedia, nama_obat FROM obat WHERE id = '$obat_id'";
                $r_check_stok = mysqli_query($koneksi, $q_check_stok);
                $data_obat = mysqli_fetch_assoc($r_check_stok);
                
                if (!$data_obat) {
                    throw new Exception("Obat tidak ditemukan dalam database.");
                }
                
                $stok_saat_ini = $data_obat['stok_tersedia'];
                $stok_sesudah = $stok_saat_ini + $jumlah_masuk;

                // 3. UPDATE (PENAMBAHAN) STOK OBAT
                $query_update_stok = "UPDATE obat SET 
                    stok_tersedia = '$stok_sesudah', 
                    updated_at = NOW() 
                    WHERE id = '$obat_id'";
                
                if (!mysqli_query($koneksi, $query_update_stok)) {
                    throw new Exception("Gagal menambahkan stok obat: " . mysqli_error($koneksi));
                }

                // 4. INSERT KE TABEL TRANSAKSI_OBAT (PENCATATAN MASUK)
                $query_transaksi = "INSERT INTO transaksi_obat (
                    obat_id, jenis_transaksi, jumlah, stok_sebelum, stok_sesudah, 
                    resep_obat_id, tanggal_transaksi, keterangan, petugas
                ) VALUES (
                    '$obat_id', 'MASUK', '$jumlah_masuk', '$stok_saat_ini', '$stok_sesudah', 
                    NULL, NOW(), '$keterangan', '$petugas'
                )";
                
                if (!mysqli_query($koneksi, $query_transaksi)) {
                    throw new Exception("Gagal menyimpan transaksi stok masuk: " . mysqli_error($koneksi));
                }

                mysqli_commit($koneksi);
                // Redirect untuk menampilkan sukses
                header("Location: master_obat.php?status=success_stok&obat=" . urlencode($data_obat['nama_obat']) . "&jumlah=$jumlah_masuk");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $pesan_status = "Gagal memproses stok: " . $e->getMessage() . " ❌";
                $tipe_alert = 'danger';
            }
        }
    }
}

// --- Logika Tampilkan Form Edit ---
$is_edit = false;
$data_edit = [];
if ($action == 'edit' && $id_edit > 0) {
    $q_edit = "SELECT * FROM obat WHERE id='$id_edit'";
    $r_edit = mysqli_query($koneksi, $q_edit);
    if (mysqli_num_rows($r_edit) > 0) {
        $data_edit = mysqli_fetch_assoc($r_edit);
        $is_edit = true;
    } else {
        $pesan_status = "Data obat tidak ditemukan. ❌";
        $tipe_alert = 'danger';
    }
}

// --- Handle Status dari Redirect ---
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'added') {
        $pesan_status = "Data obat baru berhasil ditambahkan! ✅";
        $tipe_alert = 'success';
    } elseif ($_GET['status'] == 'updated') {
        $pesan_status = "Data obat berhasil diperbarui! ✅";
        $tipe_alert = 'success';
    } elseif ($_GET['status'] == 'deleted') {
        $pesan_status = "Data obat berhasil dihapus! ✅";
        $tipe_alert = 'warning';
    } elseif ($_GET['status'] == 'success_stok') { // LOGIKA BARU
        $obat_nama = htmlspecialchars($_GET['obat'] ?? 'Obat');
        $jumlah = htmlspecialchars($_GET['jumlah'] ?? 0);
        $pesan_status = "Stok obat **$obat_nama** berhasil ditambahkan sebanyak $jumlah! ✅";
        $tipe_alert = 'success';
    }
}

// Query Tampilkan Daftar Obat
$query_list = "SELECT * FROM obat ORDER BY nama_obat ASC";
$result_list = mysqli_query($koneksi, $query_list);

// Fungsi helper
function getValue($data, $key, $default = '') {
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : $default;
}

// Ambil data untuk detail di kolom kiri
$current_data = $is_edit ? $data_edit : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Master Data Obat</title>
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
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
                                    <a href="../berobat/riwayat_berobat.php" class="submenu-link">Pemeriksaan Pasien</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="../karyawan/riwayat_kecelakaan.php" class="submenu-link">Kecelakaan Kerja</a>
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
                        <a href="../laporan/laporan_berobat.php" class="submenu-link">Laporan Berobat</a>                     
                    </li>       
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_obat.php" class="submenu-link">Laporan Obat</a>                     
                    </li>         
                    <li class="submenu-item  ">
                        <a href="../laporan/form_laporan_bulanan.php" class="submenu-link">Laporan Kecelakaan Kerja</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_tren_berobat.php" class="submenu-link">Statistik Berobat</a>
                    </li>
                    <li class="submenu-item  ">
                        <a href="../laporan/laporan_tren_kecelakaan.php" class="submenu-link">Statistik Kecelakaan Kerja</a>
                    </li>
                </ul>
            </li>
                        <li class="sidebar-item">
                            <a href="../../logout.php" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Master Data Obat</h3>
                <p class="text-subtitle text-muted">Manajemen inventaris obat klinik.</p>
            </div>

            <section class="section">
                <?php if ($pesan_status): ?>
                    <div class="alert alert-<?= $tipe_alert ?> alert-dismissible fade show" role="alert">
                        <?= $pesan_status ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><i class="bi bi-pencil-square me-1"></i> Form <?= $is_edit ? 'Edit Data Obat' : 'Tambah Obat Baru' ?></h4>
                                <?php if($is_edit): ?>
                                    <a href="master_obat.php" class="btn btn-sm btn-light-secondary float-end">
                                        <i class="bi bi-plus-circle"></i> Tambah Baru
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <form action="master_obat.php" method="POST">
                                    <input type="hidden" name="id_obat" value="<?= getValue($data_edit, 'id'); ?>">

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Kode Obat</label>
                                            <input type="text" class="form-control" name="kode_obat" value="<?= getValue($data_edit, 'kode_obat'); ?>" required>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Nama Obat</label>
                                            <input type="text" class="form-control" name="nama_obat" value="<?= getValue($data_edit, 'nama_obat'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Kategori</label>
                                            <input type="text" class="form-control" name="kategori" value="<?= getValue($data_edit, 'kategori'); ?>" placeholder="Cth: Analgesik">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Satuan</label>
                                            <select class="form-select" name="satuan" required>
                                                <?php $satuan_val = getValue($data_edit, 'satuan'); ?>
                                                <option value="">-- Pilih Satuan --</option>
                                                <option value="Tablet" <?= ($satuan_val == 'Tablet') ? 'selected' : ''; ?>>Tablet</option>
                                                <option value="Kapsul" <?= ($satuan_val == 'Kapsul') ? 'selected' : ''; ?>>Kapsul</option>
                                                <option value="Botol" <?= ($satuan_val == 'Botol') ? 'selected' : ''; ?>>Botol</option>
                                                <option value="Pcs" <?= ($satuan_val == 'Pcs') ? 'selected' : ''; ?>>Pcs</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tanggal Kadaluarsa</label>
                                            <input type="date" class="form-control" name="tanggal_kadaluarsa" value="<?= getValue($data_edit, 'tanggal_kadaluarsa'); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Stok Tersedia</label>
                                            <input type="number" class="form-control" name="stok_tersedia" value="<?= getValue($data_edit, 'stok_tersedia', 0); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Stok Minimum</label>
                                            <input type="number" class="form-control" name="stok_minimum" value="<?= getValue($data_edit, 'stok_minimum', 0); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Harga Satuan (Rp)</label>
                                            <input type="number" step="1" class="form-control" name="harga_satuan" value="<?= getValue($data_edit, 'harga_satuan', 0); ?>" placeholder="Cth: 1500">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end pt-2 border-top mt-3">
                                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-save me-1"></i> Simpan Data Obat</button>
                                        <?php if($is_edit): ?>
                                        <a href="master_obat.php?action=delete&id=<?= getValue($data_edit, 'id'); ?>" 
                                            onclick="return confirm('Anda yakin ingin menghapus data obat ini secara permanen?')" 
                                            class="btn btn-danger me-2"><i class="bi bi-trash me-1"></i> Hapus</a>
                                        <?php endif; ?>
                                        <a href="master_obat.php" class="btn btn-light-secondary">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><i class="bi bi-box-seam me-1"></i> Informasi Cepat</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($current_data): ?>
                                    <h5 class="text-primary"><?= getValue($current_data, 'nama_obat'); ?></h5>
                                    <p class="text-muted small">Kode: **<?= getValue($current_data, 'kode_obat'); ?>** | Kategori: **<?= getValue($current_data, 'kategori', 'N/A'); ?>**</p>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-archive-fill text-success fs-4 me-2"></i>
                                            <div>
                                                <small class="text-muted">Stok Tersedia</small><br>
                                                <h5 class="mb-0 fw-bold text-success">
                                                    <?= number_format(getValue($current_data, 'stok_tersedia'), 0, ',', '.'); ?> 
                                                    <?= getValue($current_data, 'satuan'); ?>
                                                </h5>
                                            </div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Min:</small>
                                            <h5 class="mb-0 text-danger fw-bold"><?= number_format(getValue($current_data, 'stok_minimum'), 0, ',', '.'); ?></h5>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                        <?php 
                                            $expiry_date_val = getValue($current_data, 'tanggal_kadaluarsa');
                                            $expiry_text_detail = 'Tidak Ada Data';
                                            $expiry_icon_class = 'bi-calendar-check';
                                            $expiry_text_class = 'text-muted';
                                            if ($expiry_date_val) {
                                                $today = time();
                                                $expiry_date = strtotime($expiry_date_val);
                                                $diff = $expiry_date - $today;
                                                $days_left = floor($diff / (60 * 60 * 24));
                                                $expiry_text_detail = date('d F Y', $expiry_date);
                                                
                                                if ($days_left <= 0) {
                                                    $expiry_text_detail = 'KADALUARSA ('.$expiry_text_detail.')';
                                                    $expiry_icon_class = 'bi-x-octagon-fill';
                                                    $expiry_text_class = 'text-danger';
                                                } elseif ($days_left <= 90) {
                                                    $expiry_icon_class = 'bi-exclamation-triangle-fill';
                                                    $expiry_text_class = 'text-warning';
                                                    $expiry_text_detail .= ' (Sisa '.$days_left.' hari)';
                                                } else {
                                                    $expiry_icon_class = 'bi-calendar-check-fill';
                                                    $expiry_text_class = 'text-success';
                                                }
                                            }
                                        ?>
                                        <i class="bi <?= $expiry_icon_class ?> <?= $expiry_text_class ?> fs-4 me-2"></i>
                                        <div>
                                            <small class="text-muted">Tanggal Kadaluarsa</small><br>
                                            <h6 class="mb-0 <?= $expiry_text_class ?> fw-bold"><?= $expiry_text_detail ?></h6>
                                        </div>
                                    </div>
                                    
                                    <p class="mt-3 small text-muted text-end">Terakhir Diperbarui: <?= date('d/m/Y H:i', strtotime(getValue($current_data, 'updated_at'))); ?></p>

                                <?php else: ?>
                                    <div class="alert alert-info">Pilih obat dari daftar atau isi form di samping untuk melihat detailnya di sini.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Daftar Obat Tersedia (Total <?= mysqli_num_rows($result_list); ?> Item)</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped" id="table1">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Kode/Nama Obat</th>
                                    <th>Kategori</th>
                                    <th>Stok (Tersedia/Min)</th>
                                    <th>Harga Satuan</th>
                                    <th>Kadaluarsa</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; mysqli_data_seek($result_list, 0); while ($data = mysqli_fetch_assoc($result_list)): 
                                    $stok_tersedia = $data['stok_tersedia'];
                                    $stok_minimum = $data['stok_minimum'];
                                    
                                    // Logic Stok
                                    $stok_class = ($stok_tersedia <= $stok_minimum) ? 'danger' : (($stok_tersedia <= $stok_minimum * 1.5) ? 'warning' : 'success');
                                    
                                    // Logic Kadaluarsa
                                    $expiry_text_list = 'N/A';
                                    $expiry_class = 'primary';
                                    if ($data['tanggal_kadaluarsa']) {
                                        $today = time();
                                        $expiry_date = strtotime($data['tanggal_kadaluarsa']);
                                        $diff = $expiry_date - $today;
                                        $days_left = floor($diff / (60 * 60 * 24));
                                        $expiry_text_list = date('d/m/Y', $expiry_date);
                                        
                                        if ($days_left <= 0) {
                                            $expiry_text_list = 'EXPIRED';
                                            $expiry_class = 'danger';
                                        } elseif ($days_left <= 90) {
                                            $expiry_class = 'warning';
                                        } else {
                                            $expiry_class = 'success';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($data['nama_obat']); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($data['kode_obat']); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($data['kategori']); ?></td>
                                        <td>
                                            <span class="badge bg-<?= $stok_class ?>">
                                                <?= number_format($stok_tersedia, 0, ',', '.'); ?> / <?= number_format($stok_minimum, 0, ',', '.'); ?>
                                            </span> <?= htmlspecialchars($data['satuan']); ?>
                                        </td>
                                        <td>Rp<?= number_format($data['harga_satuan'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge bg-<?= $expiry_class ?>">
                                                <?= $expiry_text_list; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="master_obat.php?action=edit&id=<?= $data['id']; ?>" class="btn btn-sm btn-warning me-1" title="Edit Data dan Lihat Detail"><i class="bi bi-pencil"></i></a>
                                            
                                            <button type="button" 
                                                class="btn btn-sm btn-success btn-tambah-stok"
                                                data-id="<?= $data['id']; ?>"
                                                data-nama="<?= htmlspecialchars($data['nama_obat']); ?>"
                                                data-stok="<?= $stok_tersedia; ?>"
                                                data-satuan="<?= htmlspecialchars($data['satuan']); ?>"
                                                title="Tambah Stok Masuk"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#stokMasukModal">
                                                <i class="bi bi-box-arrow-in-up"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
    
<div class="modal fade" id="stokMasukModal" tabindex="-1" aria-labelledby="stokMasukModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="master_obat.php" method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="stokMasukModalLabel"><i class="bi bi-box-arrow-in-up me-1"></i> Tambah Stok Obat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="obat_id" id="modal_obat_id">
                    <input type="hidden" name="action" value="tambah_stok">
                    
                    <p class="mb-1">Obat: <strong id="namaObatDisplay">N/A</strong></p>
                    <p>Stok Saat Ini: <strong id="stokSaatIni" class="text-primary">N/A</strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah Stok Masuk *</label>
                        <input type="number" class="form-control" name="jumlah_masuk" min="1" placeholder="Cth: 50" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan *</label>
                        <textarea class="form-control" name="keterangan" rows="2" required placeholder="Cth: Pembelian dari Supplier PT. Farma Jaya, Invoice #123"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" name="simpan_stok_masuk"><i class="bi bi-save me-1"></i> Simpan Stok Masuk</button>
                </div>
            </form>
        </div>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
<script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
<script src="../../assets/static/js/pages/simple-datatables.js"></script> 
<script src="../../assets/compiled/js/app.js"></script>

<script>
    $(document).ready(function() {
        // Event listener saat tombol "Tambah Stok" (di tabel) diklik
        $('.btn-tambah-stok').on('click', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            const stok = $(this).data('stok');
            const satuan = $(this).data('satuan');

            // Isi data ke dalam Modal
            $('#modal_obat_id').val(id);
            $('#namaObatDisplay').text(nama);
            $('#stokSaatIni').text(stok + ' ' + satuan);
            
            // Kosongkan form input stok sebelumnya
            $('#stokMasukModal').find('input[name="jumlah_masuk"]').val('');
            $('#stokMasukModal').find('textarea[name="keterangan"]').val('');
        });
    });
</script>
</body>
</html>