<?php
// File: delete_pemeriksaan.php
session_start();
include('../../config/koneksi.php'); 

if (isset($_GET['id'])) {
    $id_berobat_delete = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Mulai Transaksi untuk memastikan konsistensi data
    mysqli_begin_transaction($koneksi);
    
    try {
        // 1. Rollback Stok Obat yang sebelumnya diresepkan
        // Ambil daftar obat dan jumlahnya dari resep lama
        $q_rollback = "SELECT id_obat, jumlah FROM resep_obat WHERE id_berobat = '$id_berobat_delete'";
        $r_rollback = mysqli_query($koneksi, $q_rollback);
        
        while ($obat_lama = mysqli_fetch_assoc($r_rollback)) {
            $obat_id_lama = $obat_lama['id_obat'];
            $jumlah_lama = $obat_lama['jumlah'];
            
            // Kembalikan stok
            $q_stok_rollback = "UPDATE obat SET stok_tersedia = stok_tersedia + $jumlah_lama WHERE id = '$obat_id_lama'";
            if (!mysqli_query($koneksi, $q_stok_rollback)) {
                throw new Exception('Gagal mengembalikan stok obat: ' . mysqli_error($koneksi));
            }
            // (Disarankan: Tambahkan juga mutasi_obat dengan jenis 'KEMBALI'/'ROLLBACK' untuk audit, tapi di sini diabaikan untuk penyederhanaan)
        }
        
        // 2. Hapus Resep Obat
        $q_delete_resep = "DELETE FROM resep_obat WHERE id_berobat = '$id_berobat_delete'";
        if (!mysqli_query($koneksi, $q_delete_resep)) {
            throw new Exception('Gagal menghapus resep obat: ' . mysqli_error($koneksi));
        }

        // 3. Hapus Data Pemeriksaan Utama
        $q_delete_berobat = "DELETE FROM berobat WHERE id = '$id_berobat_delete'";
        if (!mysqli_query($koneksi, $q_delete_berobat)) {
            throw new Exception('Gagal menghapus data pemeriksaan: ' . mysqli_error($koneksi));
        }

        // Commit Transaksi jika semua query berhasil
        mysqli_commit($koneksi);
        
        header("Location: riwayat_berobat.php?status=hapus_sukses");
        exit();

    } catch (Exception $e) {
        // Rollback Transaksi jika ada error
        mysqli_rollback($koneksi);
        
        // Redirect dengan pesan error
        $error_msg = urlencode("Gagal menghapus data: " . $e->getMessage());
        header("Location: riwayat_berobat.php?status=hapus_gagal&error_db=$error_msg");
        exit();
    }
    
} else {
    // Jika tidak ada ID
    header("Location: riwayat_berobat.php?status=no_id");
    exit();
}
?>