<?php
// Pastikan tidak ada spasi, baris kosong, atau karakter lain sebelum tag pembuka <?php

// 1. Sertakan file koneksi database
include('../../config/koneksi.php');

// 2. Cek apakah parameter ID ada di URL (GET)
if (isset($_GET['id'])) {
    
    // Ambil ID dari URL dan amankan dari SQL Injection
    $id_card_delete = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Query DELETE
    $sql = "DELETE FROM karyawan WHERE id_card='$id_card_delete'";
    
    // Eksekusi Query
    if (mysqli_query($koneksi, $sql)) {
        // Hapus berhasil, redirect ke halaman karyawan dengan status sukses
        header("Location: karyawan.php?status=hapus_sukses");
        exit();
    } else {
        // Hapus gagal, redirect ke halaman karyawan dengan status gagal
        // Anda mungkin ingin menampilkan error SQL untuk debugging jika diperlukan
        // echo "Gagal menghapus data: " . mysqli_error($koneksi);
        header("Location: karyawan.php?status=hapus_gagal");
        exit();
    }
} else {
    // Jika tidak ada ID, redirect kembali ke halaman karyawan
    header("Location: karyawan.php?status=no_id");
    exit();
}

?>