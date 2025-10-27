<?php
// File: get_riwayat_medis_ajax.php
include('../../config/koneksi.php'); 
header('Content-Type: application/json');

$response = [
    'penyakit_terdahulu' => 'Tidak Diketahui',
    'alergi' => 'Tidak Diketahui',
    'golongan_darah' => 'Tidak Diketahui',
    'status' => 'error',
];

if (isset($_GET['id_card'])) {
    $id_card = mysqli_real_escape_string($koneksi, $_GET['id_card']);
    
    // Query mengambil penyakit_terdahulu, alergi, dan golongan_darah
    $query = "SELECT penyakit_terdahulu, alergi, golongan_darah 
              FROM riwayat_medis 
              WHERE id_card = '$id_card'"; 
              
    $result = mysqli_query($koneksi, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        
        $response = [
            // Jika kolom kosong, tampilkan 'TIDAK ADA'
            'penyakit_terdahulu' => empty($data['penyakit_terdahulu']) ? 'TIDAK ADA' : htmlspecialchars($data['penyakit_terdahulu']),
            'alergi' => empty($data['alergi']) ? 'TIDAK ADA' : htmlspecialchars($data['alergi']),
            'golongan_darah' => empty($data['golongan_darah']) ? 'TIDAK DIKETAHUI' : htmlspecialchars($data['golongan_darah']),
            'status' => 'success',
        ];
    } else {
        // Jika data riwayat medis belum diisi
        $response = [
            'penyakit_terdahulu' => 'BELUM ADA DATA',
            'alergi' => 'BELUM ADA DATA',
            'golongan_darah' => 'BELUM ADA DATA',
            'status' => 'warning',
        ];
    }
}

echo json_encode($response);
exit();
?>