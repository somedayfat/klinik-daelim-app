<?php
// FILE: cari_obat_ajax.php

// ----------------------------------------------------------------------
// *** PENTING: SESUAIKAN PATH KONEKSI INI! ***
// Jika Anda tidak yakin, coba salah satu:
// include('../../../config/koneksi.php'); // Jika form Anda 3 tingkat di bawah koneksi
include('../../config/koneksi.php'); // Coba ini dulu, 2 tingkat di bawah koneksi
// ----------------------------------------------------------------------

header('Content-Type: application/json');

$response = ['results' => []];
// Ambil kata kunci pencarian dari Select2
$search = isset($_GET['q']) ? mysqli_real_escape_string($koneksi, $_GET['q']) : '';

// Query untuk mencari Obat dengan stok > 0
$query = "
    SELECT 
        id, 
        nama_obat, 
        kode_obat,
        satuan,
        stok
    FROM 
        obat 
    WHERE 
        (nama_obat LIKE '%$search%' OR kode_obat LIKE '%$search%') 
        AND stok > 0 
    LIMIT 10
";

$result = mysqli_query($koneksi, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Format teks yang akan ditampilkan di dropdown Select2
        $text = htmlspecialchars($row['nama_obat']) . " (" . htmlspecialchars($row['kode_obat']) . ") - Stok: " . number_format($row['stok'], 0, ',', '.') . " " . htmlspecialchars($row['satuan']);
        
        $response['results'][] = [
            'id' => $row['id'], // ID Obat yang akan disimpan
            'text' => $text,
            'stok' => $row['stok'] // Data tambahan untuk JS
        ];
    }
} else {
    // Memberikan pesan error yang jelas jika query gagal
    $response['error'] = 'Query failed: ' . mysqli_error($koneksi);
}

echo json_encode($response);
// Pastikan tidak ada karakter atau spasi di luar tag <?php ?>