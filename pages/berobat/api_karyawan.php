<?php
// File: api_karyawan.php (Koreksi Final untuk Select2 & Fungsionalitas)
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
                'text' => $row['nama'] . ' (' . $row['id_card'] . ' - ' . $row['jabatan'] . ')',
                // --- KUNCI TAMBAHAN AGAR JS BISA MENGISI FIELD JABATAN & DEPARTEMEN ---
                'jabatan' => $row['jabatan'], 
                'departemen' => $row['departemen']
            ];
        }
    }
} else if (isset($_GET['id_card'])) {
    // Jika hanya mengambil detail satu karyawan berdasarkan ID Card (mode Edit/load)
    $id_card = mysqli_real_escape_string($koneksi, $_GET['id_card']);
    $query = "SELECT id_card, nama, jabatan, departemen FROM karyawan WHERE id_card = '$id_card'";
    $result = mysqli_query($koneksi, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $results[] = [
            'id' => $row['id_card'],
            'nama' => $row['nama'],
            'text' => $row['nama'] . ' (' . $row['id_card'] . ')',
            // --- KUNCI TAMBAHAN AGAR FORM EDIT BISA MENGAMBIL DATA DETAIL ---
            'id_card' => $row['id_card'], // DITAMBAHKAN: Untuk konsistensi di client side
            'jabatan' => $row['jabatan'], 
            'departemen' => $row['departemen']
        ];
    }
}

echo json_encode(['results' => $results]);
?>