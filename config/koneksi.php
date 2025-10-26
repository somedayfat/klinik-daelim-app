<?php
// config/koneksi.php

$host = "localhost"; // Biasanya localhost
$user = "root";      // Default user XAMPP
$pass = "";          // Default password XAMPP (kosong)
$db   = "klinik_db"; // Nama database Anda

$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
// echo "Koneksi berhasil"; // Hapus setelah berhasil dites
?>s