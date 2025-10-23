<?php
// Pastikan tidak ada karakter atau spasi sebelum tag PHP
// =========================================================

// 1. Sertakan file koneksi database
include('config/koneksi.php');

// 2. Cek apakah form telah disubmit (dengan nama button="simpan")
if (isset($_POST['simpan'])) {
    
    // 3. Amankan dan ambil data dari form
    // Gunakan mysqli_real_escape_string untuk mencegah SQL Injection
    
    // Data Wajib/Kunci
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $jabatan = mysqli_real_escape_string($koneksi, $_POST['jabatan']);
    $departemen= mysqli_real_escape_string($koneksi, $_POST['departemen']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $tgl_masuk = mysqli_real_escape_string($koneksi, $_POST['tgl_masuk']); 
    $status= mysqli_real_escape_string($koneksi, $_POST['status']);

    // Data Opsional (tetap diamankan)
    $tgl_lahir= mysqli_real_escape_string($koneksi, $_POST['tgl_lahir']); 
    $telepon  = mysqli_real_escape_string($koneksi, $_POST['telepon']);

    // 4. Siapkan Query INSERT
    $sql = "INSERT INTO karyawan (
        id_card, 
        nama, 
        jabatan, 
        departemen, 
        tanggal_lahir, 
        jenis_kelamin, 
        telepon, 
        tanggal_masuk, 
        status
    ) 
    VALUES (
        '$id_card', 
        '$nama', 
        '$jabatan', 
        '$departemen', 
        '$tgl_lahir', 
        '$jenis_kelamin', 
        '$telepon', 
        '$tgl_masuk', 
        '$status'
    )";

    // 5. Eksekusi Query dan Lakukan Redirect
    if (mysqli_query($koneksi, $sql)) {
        // Query berhasil, arahkan kembali ke halaman data karyawan
        header("Location: karyawan.php?status=tambah_sukses");
        exit();
    } else {
        // Query gagal, arahkan kembali ke form_add_karyawan dengan pesan error
        // Atau tampilkan error untuk debugging
        // echo "Gagal menyimpan data: " . mysqli_error($koneksi);
        header("Location: form_add_karyawan.php?status=tambah_gagal");
        exit();
    }
    
} else {
    // Jika diakses tanpa submit form, arahkan ke halaman data karyawan
    header("Location: karyawan.php");
    exit();
}

// =========================================================
?>