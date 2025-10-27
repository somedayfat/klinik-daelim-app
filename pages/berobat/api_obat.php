<?php
// File: api_obat.php
header('Content-Type: application/json');
include('../../config/koneksi.php'); // Pastikan path ke koneksi.php benar

$results = [];

if (isset($_GET['query'])) {
    $search_query = mysqli_real_escape_string($koneksi, $_GET['query']);
    
    // Cari obat berdasarkan nama_obat atau kode_obat
    $query = "SELECT id, nama_obat, satuan, stok_tersedia FROM obat 
              WHERE (nama_obat LIKE '%$search_query%' OR kode_obat LIKE '%$search_query%') 
              AND stok_tersedia > 0 
              ORDER BY nama_obat ASC 
              LIMIT 10"; 
              
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $results[] = [
                'id' => $row['id'],
                'nama' => $row['nama_obat'],
                'satuan' => $row['satuan'],
                'stok' => (int)$row['stok_tersedia'],
                // Teks yang ditampilkan di dropdown, gabungan nama dan stok
                'text' => $row['nama_obat'] . ' (' . $row['satuan'] . ') - Stok: ' . $row['stok_tersedia']
            ];
        }
    }
} else if (isset($_GET['id'])) {
    // Jika hanya mengambil detail satu obat berdasarkan ID
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $query = "SELECT id, nama_obat, satuan, stok_tersedia FROM obat WHERE id = '$id'";
    $result = mysqli_query($koneksi, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $results[] = [
            'id' => $row['id'],
            'nama' => $row['nama_obat'],
            'satuan' => $row['satuan'],
            'stok' => (int)$row['stok_tersedia'],
            'text' => $row['nama_obat'] . ' (' . $row['satuan'] . ') - Stok: ' . $row['stok_tersedia']
        ];
    }
}

echo json_encode(['results' => $results]);
?>