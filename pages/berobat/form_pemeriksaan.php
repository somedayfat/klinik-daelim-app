<?php
// File: pages/berobat/form_pemeriksaan.php
// Versi Lengkap & Diperbaiki: Mirip notepad.php + detail_pemeriksaan.php, Fix semua error, Full Edit Mode

session_start();
include('../../config/koneksi.php'); 

date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';
$data_pemeriksaan = null;
$data_resep = [];
$data_karyawan = null;
$riwayat_medis = null;
$is_edit_mode = false;
$id_berobat_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$petugas = "Aditya Fajrin"; // Ganti dengan $_SESSION['user_nama'] jika ada

// Helper untuk nilai form
function postValue($key, $default = '', $db_data = null) {
    if (isset($_POST[$key])) return htmlspecialchars($_POST[$key]);
    if ($db_data && isset($db_data[$key])) return htmlspecialchars($db_data[$key]);
    return htmlspecialchars($default);
}

// --- MODE EDIT: Prepared Statements ---
if ($id_berobat_edit > 0) {
    $is_edit_mode = true;

    $query_utama = "SELECT 
                        b.*, 
                        k.nama AS nama_karyawan,
                        k.jabatan,
                        k.departemen
                    FROM berobat b
                    JOIN karyawan k ON b.id_card = k.id_card
                    WHERE b.id = ?";
    
    if ($stmt = mysqli_prepare($koneksi, $query_utama)) {
        mysqli_stmt_bind_param($stmt, "i", $id_berobat_edit);
        mysqli_stmt_execute($stmt);
        $result_utama = mysqli_stmt_get_result($stmt);
        
        if ($result_utama && mysqli_num_rows($result_utama) > 0) {
            $data_pemeriksaan = mysqli_fetch_assoc($result_utama);

            $data_karyawan = [
                'id_card' => $data_pemeriksaan['id_card'],
                'nama' => $data_pemeriksaan['nama_karyawan'],
                'jabatan' => $data_pemeriksaan['jabatan'],
                'departemen' => $data_pemeriksaan['departemen']
            ];

            // Resep Obat
            $query_resep = "SELECT rd.obat_id, rd.jumlah, o.nama_obat, o.satuan, o.stok_tersedia
                            FROM resep_obat rd
                            JOIN obat o ON rd.obat_id = o.id
                            WHERE rd.berobat_id = ?";
            if ($stmt_resep = mysqli_prepare($koneksi, $query_resep)) {
                mysqli_stmt_bind_param($stmt_resep, "i", $id_berobat_edit);
                mysqli_stmt_execute($stmt_resep);
                $result_resep = mysqli_stmt_get_result($stmt_resep);
                while ($row = mysqli_fetch_assoc($result_resep)) {
                    $data_resep[] = $row;
                }
            }

            // Riwayat Medis
            $query_rm = "SELECT * FROM riwayat_medis WHERE id_card = ?";
            if ($stmt_rm = mysqli_prepare($koneksi, $query_rm)) {
                mysqli_stmt_bind_param($stmt_rm, "s", $data_pemeriksaan['id_card']);
                mysqli_stmt_execute($stmt_rm);
                $result_rm = mysqli_stmt_get_result($stmt_rm);
                if (mysqli_num_rows($result_rm) > 0) {
                    $riwayat_medis = mysqli_fetch_assoc($result_rm);
                }
            }
        } else {
            $error = "Data pemeriksaan tidak ditemukan.";
            $is_edit_mode = false;
        }
    } else {
        $error = "Query error: " . mysqli_error($koneksi);
    }
}

// --- SIMPAN (INSERT/UPDATE) TRANSAKSI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pemeriksaan'])) {
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    $is_update = !empty($_POST['is_update']);
    $id_berobat_post = $is_update ? (int)$_POST['id_berobat_post'] : 0;

    // Validasi karyawan
    $check_q = "SELECT id_card FROM karyawan WHERE id_card = ?";
    if ($stmt_check = mysqli_prepare($koneksi, $check_q)) {
        mysqli_stmt_bind_param($stmt_check, "s", $id_card);
        mysqli_stmt_execute($stmt_check);
        $check_result = mysqli_stmt_get_result($stmt_check);
        if (mysqli_num_rows($check_result) == 0) {
            $error = "ID Card karyawan tidak valid.";
        }
    }

    if (empty($error)) {
        $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
        $diagnosis = mysqli_real_escape_string($koneksi, $_POST['diagnosis']);
        $tekanan_darah = mysqli_real_escape_string($koneksi, $_POST['tekanan_darah']);
        $suhu_tubuh = mysqli_real_escape_string($koneksi, $_POST['suhu_tubuh']);
        $nadi = mysqli_real_escape_string($koneksi, $_POST['nadi']);
        $pernafasan = mysqli_real_escape_string($koneksi, $_POST['pernafasan']);
        $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
        $rujukan = mysqli_real_escape_string($koneksi, $_POST['rujukan']);
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        $tanggal = date('Y-m-d H:i:s');

        $obat_ids = $_POST['obat_id'] ?? [];
        $jumlahs = $_POST['jumlah'] ?? [];
        $validated = [];
        $stok_updates = [];

        foreach ($obat_ids as $i => $obat_id) {
            if (empty($obat_id) || empty($jumlahs[$i])) continue;
            $obat_id = (int)$obat_id;
            $jumlah = (int)$jumlahs[$i];

            $q_obat = "SELECT stok_tersedia, nama_obat, satuan FROM obat WHERE id = ?";
            if ($stmt_obat = mysqli_prepare($koneksi, $q_obat)) {
                mysqli_stmt_bind_param($stmt_obat, "i", $obat_id);
                mysqli_stmt_execute($stmt_obat);
                $res_obat = mysqli_stmt_get_result($stmt_obat);
                if ($row_obat = mysqli_fetch_assoc($res_obat)) {
                    if ($is_update) {
                        $q_old = "SELECT jumlah FROM resep_obat WHERE berobat_id = ? AND obat_id = ?";
                        if ($stmt_old = mysqli_prepare($koneksi, $q_old)) {
                            mysqli_stmt_bind_param($stmt_old, "ii", $id_berobat_post, $obat_id);
                            mysqli_stmt_execute($stmt_old);
                            $res_old = mysqli_stmt_get_result($stmt_old);
                            $old_row = mysqli_fetch_assoc($res_old);
                            $old_jumlah = $old_row['jumlah'] ?? 0;
                            $available = $row_obat['stok_tersedia'] + $old_jumlah;
                            $delta = $jumlah - $old_jumlah;
                        }
                    } else {
                        $available = $row_obat['stok_tersedia'];
                        $delta = $jumlah;
                    }

                    if ($jumlah > $available) {
                        $error = "Stok {$row_obat['nama_obat']} tidak cukup (max: $available {$row_obat['satuan']}).";
                        break;
                    }

                    $validated[] = ['obat_id' => $obat_id, 'jumlah' => $jumlah];
                    $stok_updates[$obat_id] = -$delta;
                }
            }
        }

        if (empty($error)) {
            mysqli_begin_transaction($koneksi);
            try {
                if ($is_update) {
                    $q_update = "UPDATE berobat SET 
                                 keluhan = ?, diagnosis = ?, tekanan_darah = ?, suhu_tubuh = ?, 
                                 nadi = ?, pernafasan = ?, tindakan = ?, rujukan = ?, 
                                 catatan = ?, petugas = ?, tanggal_berobat = ? 
                                 WHERE id = ?";
                    if ($stmt_up = mysqli_prepare($koneksi, $q_update)) {
                        mysqli_stmt_bind_param($stmt_up, "sssssssssssi", $keluhan, $diagnosis, $tekanan_darah, $suhu_tubuh, 
                                               $nadi, $pernafasan, $tindakan, $rujukan, $catatan, $petugas, $tanggal, $id_berobat_post);
                        mysqli_stmt_execute($stmt_up);
                    }

                    mysqli_query($koneksi, "DELETE FROM resep_obat WHERE berobat_id = $id_berobat_post");
                    $berobat_id = $id_berobat_post;
                } else {
                    $q_insert = "INSERT INTO berobat 
                                 (id_card, tanggal_berobat, keluhan, diagnosis, tekanan_darah, suhu_tubuh, 
                                  nadi, pernafasan, tindakan, rujukan, catatan, petugas, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    if ($stmt_ins = mysqli_prepare($koneksi, $q_insert)) {
                        mysqli_stmt_bind_param($stmt_ins, "ssssssssssss", $id_card, $tanggal, $keluhan, $diagnosis, 
                                               $tekanan_darah, $suhu_tubuh, $nadi, $pernafasan, $tindakan, $rujukan, $catatan, $petugas);
                        mysqli_stmt_execute($stmt_ins);
                    }
                    $berobat_id = mysqli_insert_id($koneksi);
                }

                foreach ($validated as $v) {
                    $q_resep = "INSERT INTO resep_obat (berobat_id, obat_id, jumlah, created_at) 
                                VALUES (?, ?, ?, NOW())";
                    if ($stmt_resep = mysqli_prepare($koneksi, $q_resep)) {
                        mysqli_stmt_bind_param($stmt_resep, "iii", $berobat_id, $v['obat_id'], $v['jumlah']);
                        mysqli_stmt_execute($stmt_resep);
                    }
                }

                foreach ($stok_updates as $oid => $delta) {
                    $q_stok = "UPDATE obat SET stok_tersedia = stok_tersedia + ? WHERE id = ?";
                    if ($stmt_stok = mysqli_prepare($koneksi, $q_stok)) {
                        mysqli_stmt_bind_param($stmt_stok, "ii", $delta, $oid);
                        mysqli_stmt_execute($stmt_stok);
                    }
                }

                mysqli_commit($koneksi);
                $success = $is_update ? "Pemeriksaan berhasil diupdate." : "Pemeriksaan baru berhasil disimpan.";
                // Refresh data untuk mode edit setelah update
                if ($is_update) {
                    header("Location: form_pemeriksaan.php?id=$berobat_id&success=1");
                    exit;
                }
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Gagal simpan: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit_mode ? 'Edit' : 'Tambah'; ?> Pemeriksaan Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <link rel="shortcut icon" href="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2033%2034'%20fill-rule='evenodd'%20stroke-linejoin='round'%20stroke-miterlimit='2'%20xmlns:v='https://vecta.io/nano'%3e%3cpath%20d='M3%2027.472c0%204.409%206.18%205.552%2013.5%205.552%207.281%200%2013.5-1.103%2013.5-5.513s-6.179-5.552-13.5-5.552c-7.281%200-13.5%201.103-13.5%205.513z'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3ccircle%20cx='16.5'%20cy='8.8'%20r='8.8'%20fill='%2341bbdd'/%3e%3c/svg%3e" type="image/x-icon">
    <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
    
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .stok-info { font-size: 0.85em; margin-top: 4px; }
        .row-resep { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        @media print {
            #sidebar, header, footer, .btn, .page-heading .btn { display: none !important; }
            .card { border: none; box-shadow: none; }
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
                        <li class="sidebar-item">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-file-earmark-medical-fill"></i>
                                <span>Laporan Klinik</span>
                            </a>
                        </li>
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Account</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="#" class="submenu-link">Profile</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="#" class="submenu-link">Security</a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-person-badge-fill"></i>
                                <span>Authentication</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="#" class="submenu-link">Login</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="#" class="submenu-link">Register</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="#" class="submenu-link">Forgot Password</a>
                                </li>
                            </ul>
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
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3><?php echo $is_edit_mode ? 'Edit' : 'Form'; ?> Pemeriksaan Pasien</h3>
                            <p class="text-subtitle text-muted">Isi data pemeriksaan dengan lengkap.</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="riwayat_berobat.php">Riwayat</a></li>
                                    <li class="breadcrumb-item active" aria-current="page"><?php echo $is_edit_mode ? 'Edit' : 'Tambah'; ?></li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($success || isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success ?: "Operasi berhasil."; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Data Pemeriksaan' : 'Pemeriksaan Baru'; ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="is_update" value="<?php echo $is_edit_mode ? '1' : '0'; ?>">
                            <input type="hidden" name="id_berobat_post" value="<?php echo $id_berobat_edit; ?>">

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="select_karyawan">Cari Karyawan <span class="text-danger">*</span></label>
                                        <select id="select_karyawan" class="form-select" name="id_card" required>
                                            <option value="">-- Ketik nama / ID Card --</option>
                                            <?php if ($is_edit_mode && $data_karyawan): ?>
                                                <option value="<?php echo $data_karyawan['id_card']; ?>" selected><?php echo $data_karyawan['nama']; ?> (<?php echo $data_karyawan['id_card']; ?> - <?php echo $data_karyawan['jabatan']; ?>)</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="card-title mb-0">Informasi Pasien</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3"><strong>Nama:</strong> <span id="nama_karyawan" class="text-primary"><?php echo $data_karyawan['nama'] ?? '-'; ?></span></div>
                                                <div class="col-md-3"><strong>ID Card:</strong> <span id="id_card_display"><?php echo $data_karyawan['id_card'] ?? '-'; ?></span></div>
                                                <div class="col-md-3"><strong>Jabatan:</strong> <span id="jabatan_karyawan"><?php echo $data_karyawan['jabatan'] ?? '-'; ?></span></div>
                                                <div class="col-md-3"><strong>Departemen:</strong> <span id="departemen_karyawan"><?php echo $data_karyawan['departemen'] ?? '-'; ?></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-danger text-white">
                                            <h5 class="card-title mb-0">Riwayat Medis Kritis</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4"><strong>Golongan Darah:</strong> <span id="golongan_darah" class="text-danger fw-bold"><?php echo $riwayat_medis['golongan_darah'] ?? '-'; ?></span></div>
                                                <div class="col-md-4"><strong>Alergi:</strong> <span id="alergi" class="text-danger fw-bold"><?php echo $riwayat_medis['alergi'] ?? 'TIDAK ADA'; ?></span></div>
                                                <div class="col-md-4"><strong>Penyakit Terdahulu:</strong> <span id="penyakit_terdahulu"><?php echo $riwayat_medis['penyakit_terdahulu'] ?? '-'; ?></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mt-4">Detail Pemeriksaan</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Keluhan Utama</label>
                                        <textarea class="form-control" rows="3" name="keluhan" required><?php echo postValue('keluhan', '', $data_pemeriksaan); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Diagnosis</label>
                                        <textarea class="form-control" rows="3" name="diagnosis"><?php echo postValue('diagnosis', '', $data_pemeriksaan); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Tekanan Darah (mmHg)</label>
                                        <input type="text" class="form-control" name="tekanan_darah" placeholder="120/80" value="<?php echo postValue('tekanan_darah', '', $data_pemeriksaan); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Suhu Tubuh (°C)</label>
                                        <input type="text" class="form-control" name="suhu_tubuh" placeholder="36.5" value="<?php echo postValue('suhu_tubuh', '', $data_pemeriksaan); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Nadi (bpm)</label>
                                        <input type="text" class="form-control" name="nadi" placeholder="80" value="<?php echo postValue('nadi', '', $data_pemeriksaan); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Pernafasan (rpm)</label>
                                        <input type="text" class="form-control" name="pernafasan" placeholder="16" value="<?php echo postValue('pernafasan', '', $data_pemeriksaan); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tindakan / Resep Obat</label>
                                        <textarea class="form-control" rows="3" name="tindakan"><?php echo postValue('tindakan', '', $data_pemeriksaan); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Rujukan</label>
                                        <input type="text" class="form-control" name="rujukan" placeholder="RS / Spesialis" value="<?php echo postValue('rujukan', '', $data_pemeriksaan); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Catatan Tambahan</label>
                                <textarea class="form-control" rows="2" name="catatan"><?php echo postValue('catatan', '', $data_pemeriksaan); ?></textarea>
                            </div>

                            <h5 class="mt-4">Resep Obat</h5>
                            <div id="resep_container" class="mb-3"></div>
                            <button type="button" id="tambah_obat" class="btn btn-outline-primary btn-sm">+ Tambah Obat</button>

                            <div class="mt-4">
                                <button type="submit" name="simpan_pemeriksaan" class="btn btn-success">
                                    <i class="bi bi-save"></i> <?php echo $is_edit_mode ? 'Update' : 'Simpan'; ?> Pemeriksaan
                                </button>
                                <a href="riwayat_berobat.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>2025 &copy; Daelim</p>
                    </div>
                    <div class="float-end">
                        <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span> by <a href="https://daelim.id">IT PT. Daelim Indonesia</a></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="../../assets/static/js/components/dark.js"></script>
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>

    <!-- jQuery & Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let resepCounter = 0;
        const editResepData = <?php echo json_encode($data_resep); ?>;

        function addResepRow(data = null) {
            resepCounter++;
            const rowId = 'row_' + resepCounter;
            const html = `
                <div class="row row-resep mb-3" id="${rowId}">
                    <div class="col-md-5">
                        <label>Obat</label>
                        <select class="form-select obat-select" name="obat_id[]" required></select>
                    </div>
                    <div class="col-md-3">
                        <label>Jumlah</label>
                        <input type="number" class="form-control jumlah-input" name="jumlah[]" min="1" placeholder="Jumlah" ${data ? 'value="' + data.jumlah + '"' : ''} disabled>
                    </div>
                    <div class="col-md-2">
                        <label>Satuan</label>
                        <span class="form-control-plaintext satuan-display">-</span>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button>
                    </div>
                    <div class="col-12"><small class="stok-info text-muted"></small></div>
                </div>`;
            $('#resep_container').append(html);

            const $select = $('#' + rowId + ' .obat-select');
            initObatSelect($select, data);
        }

        function initObatSelect($el, data) {
            $el.select2({
                placeholder: 'Cari nama obat...',
                allowClear: true,
                ajax: {
                    url: 'api_obat.php',
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ query: params.term }),
                    processResults: data => ({ results: data.results || [] })
                },
                minimumInputLength: 2,
                templateResult: item => item.nama_obat ? `${item.nama_obat} (${item.satuan}) - Stok: ${item.stok_tersedia}` : item.text,
                templateSelection: item => item.nama_obat ? `${item.nama_obat} (${item.satuan})` : item.text
            }).on('select2:select', function (e) {
                const d = e.params.data;
                const $row = $(this).closest('.row-resep');
                $row.find('.jumlah-input').prop('disabled', false).attr('max', d.stok_tersedia);
                $row.find('.satuan-display').text(d.satuan);
                $row.find('.stok-info').text(`Stok tersedia: ${d.stok_tersedia} ${d.satuan}`).removeClass('text-danger').addClass('text-success');
            }).on('select2:unselect', function () {
                const $row = $(this).closest('.row-resep');
                $row.find('.jumlah-input').val('').prop('disabled', true);
                $row.find('.satuan-display').text('-');
                $row.find('.stok-info').text('');
            });

            if (data) {
                const opt = new Option(`${data.nama_obat} (${data.satuan}) - Stok: ${data.stok_tersedia}`, data.obat_id, true, true);
                $el.append(opt).trigger('change');
                $el.trigger({ type: 'select2:select', params: { data: { stok_tersedia: data.stok_tersedia, satuan: data.satuan } } });
            }
        }

        $('#tambah_obat').on('click', () => addResepRow());
        $(document).on('click', '.remove-row', function () {
            $(this).closest('.row-resep').remove();
        });

        // Init Karyawan Select2
        $('#select_karyawan').select2({
            placeholder: 'Ketik nama atau ID Card...',
            allowClear: true,
            ajax: {
                url: 'api_karyawan.php',
                dataType: 'json',
                delay: 300,
                data: params => ({ query: params.term }),
                processResults: data => ({ results: data.results || [] })
            },
            minimumInputLength: 2
        }).on('select2:select', function (e) {
            const d = e.params.data;
            $('#nama_karyawan').text(d.nama || d.text.split(' (')[0]);
            $('#id_card_display').text(d.id_card || d.id);
            $('#jabatan_karyawan').text(d.jabatan || '-');
            $('#departemen_karyawan').text(d.departemen || '-');

            // Load Riwayat Medis
            $.get('get_riwayat_medis_ajax.php?id_card=' + (d.id_card || d.id), function (res) {
                $('#golongan_darah').text(res.golongan_darah || '-');
                $('#alergi').text(res.alergi || 'TIDAK ADA');
                $('#penyakit_terdahulu').text(res.penyakit_terdahulu || '-');
            }, 'json').fail(() => {
                $('#golongan_darah, #alergi, #penyakit_terdahulu').text('Gagal load');
            });
        });

        $(document).ready(function () {
            if (editResepData.length > 0) {
                editResepData.forEach(d => addResepRow(d));
            } else {
                addResepRow();
            }

            <?php if ($is_edit_mode && $data_karyawan): ?>
                // Trigger manual untuk edit mode
                $('#select_karyawan').trigger({
                    type: 'select2:select',
                    params: { data: { id: '<?php echo $data_karyawan['id_card']; ?>', text: '<?php echo $data_karyawan['nama']; ?> (<?php echo $data_karyawan['id_card']; ?>)', nama: '<?php echo $data_karyawan['nama']; ?>', jabatan: '<?php echo $data_karyawan['jabatan']; ?>', departemen: '<?php echo $data_karyawan['departemen']; ?>' } }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>