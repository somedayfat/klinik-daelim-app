<?php
// File: api_karyawan.php
header('Content-Type: application/json');
include('../../config/koneksi.php');

$results = [];

if (isset($_GET['query'])) {
    $search_query = mysqli_real_escape_string($koneksi, $_GET['query']);
    
    // Cari karyawan berdasarkan nama atau ID Card
    $query = "SELECT id_card, nama, jabatan, departemen FROM karyawan 
              WHERE nama LIKE '%$search_query%' OR id_card LIKE '%$search_query%' 
              ORDER BY nama ASC 
              LIMIT 10"; 
              
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $results[] = [
                'id' => $row['id_card'],
                // Teks yang ditampilkan di dropdown: Nama (ID Card - Jabatan)
                'text' => $row['nama'] . ' (' . $row['id_card'] . ' - ' . $row['jabatan'] . ')'
            ];
        }
    }
} else if (isset($_GET['id_card'])) {
    // Jika hanya mengambil detail satu karyawan berdasarkan ID Card
    $id_card = mysqli_real_escape_string($koneksi, $_GET['id_card']);
    $query = "SELECT id_card, nama, jabatan, departemen FROM karyawan WHERE id_card = '$id_card'";
    $result = mysqli_query($koneksi, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $results[] = [
            'id' => $row['id_card'],
            'nama' => $row['nama'],
            'text' => $row['nama'] . ' (' . $row['id_card'] . ')',
        ];
    }
}

echo json_encode(['results' => $results]);
?>