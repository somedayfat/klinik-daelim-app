<?php
// File: api_kecelakaan_detail.php
// PASTIKSAN PATH KONEKSI INI BENAR
include('../../config/koneksi.php'); 

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => null
];

$id = $_GET['id'] ?? null;

if (!empty($id)) {
    $id = mysqli_real_escape_string($koneksi, $id);
    
    $query_detail = "
        SELECT 
            kk.*, 
            k.nama, 
            k.departemen,
            k.jabatan
        FROM 
            kecelakaan_kerja kk
        JOIN 
            karyawan k ON kk.id_card = k.id_card
        WHERE 
            kk.id = '$id'";

    $result_detail = mysqli_query($koneksi, $query_detail);

    if ($result_detail && mysqli_num_rows($result_detail) > 0) {
        $data = mysqli_fetch_assoc($result_detail);
        $response['success'] = true;
        $response['data'] = $data;
    }
}

echo json_encode($response);
exit;
?>