<?php
// File: form_stok_masuk.php
session_start();
include('../../config/koneksi.php'); 
date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_stok_masuk'])) {
    
    // 1. Ambil dan Bersihkan Data
    $obat_id = mysqli_real_escape_string($koneksi, $_POST['obat_id']);
    $jumlah_masuk = (int)$_POST['jumlah_masuk'];
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $petugas = "Admin Farmasi"; // Ganti dengan data session user yang login

    if ($jumlah_masuk <= 0) {
        $error = "Jumlah stok masuk harus lebih dari 0.";
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
                stok_tersedia = stok_tersedia + '$jumlah_masuk', 
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

            // Commit transaksi
            mysqli_commit($koneksi);
            $success = "Stok obat **" . $data_obat['nama_obat'] . "** berhasil ditambahkan sebanyak $jumlah_masuk. Stok kini: $stok_sesudah.";

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Input Stok Obat Masuk</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
</head>
<body>
    <div id="app">
        <div id="sidebar"></div> 

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Input Stok Obat Masuk</h3>
                <p class="text-subtitle text-muted">Formulir untuk mencatat penambahan stok obat (pembelian).</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Form Stok Obat Masuk</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $error ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $success ?></div>
                        <?php endif; ?>

                        <form action="form_stok_masuk.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Pilih Obat *</label>
                                    <select class="form-control" name="obat_id" id="select_obat" required>
                                        <option value="">Cari Nama/Kode Obat...</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Jumlah Stok Masuk *</label>
                                    <input type="number" class="form-control" name="jumlah_masuk" min="1" placeholder="Cth: 50" required>
                                    <small class="form-text text-muted">Stok saat ini: <span id="current_stok">N/A</span></small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Keterangan *</label>
                                <textarea class="form-control" name="keterangan" rows="2" required placeholder="Cth: Pembelian dari Supplier ABC, Invoice #123"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end border-top pt-3 mt-4">
                                <button type="submit" name="simpan_stok_masuk" class="btn btn-primary me-1 mb-1">
                                    <i class="bi bi-save me-1"></i> Simpan Stok Masuk
                                </button>
                                <button type="reset" class="btn btn-light-secondary mb-1">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/jquery/jquery.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>

    <script>
    $(document).ready(function() {
        // Menggunakan API Obat yang sudah ada, tapi tanpa filter stok > 0
        $('#select_obat').select2({
            placeholder: 'Cari Nama/Kode Obat...',
            allowClear: true,
            ajax: {
                url: 'api_obat.php', 
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { query: params.term, include_all: true }; // Tambahkan flag jika diperlukan, tapi kita pakai query saja
                },
                processResults: function (data) { 
                    return { 
                        // Perlu sedikit modifikasi di api_obat.php jika ingin menampilkan stok, tapi untuk saat ini kita gunakan text saja
                        results: data.results || [] 
                    };
                }, 
                cache: true
            },
            minimumInputLength: 2,
            templateSelection: function (data) {
                if (!data.id) return data.text;
                // Hapus bagian Stok: di tampilan Select2 jika ada
                return data.text.split(' - Stok:')[0]; 
            }
        });

        // Event handler saat obat dipilih: Update info stok
        $('#select_obat').on('select2:select', function (e) {
            const data = e.params.data;
            $('#current_stok').text(data.stok + ' ' + data.satuan);
        });
        
        // --- CATATAN PENTING ---
        // Agar Select2 di form_stok_masuk.php bisa menampilkan STOK, 
        // pastikan file api_obat.php Anda sudah mengembalikan data.stok dan data.satuan.
        // (Kode api_obat.php yang saya berikan sebelumnya sudah menyertakan ini).
    });
    </script>
</body>
</html>