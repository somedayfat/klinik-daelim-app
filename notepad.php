<?php
session_start();
include('../../config/koneksi.php');

// --- LOGIKA SIMPAN DATA (INSERT ke tabel berobat) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil dan bersihkan data dari form
    $id_card = mysqli_real_escape_string($koneksi, $_POST['id_card']);
    $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
    $diagnosis = mysqli_real_escape_string($koneksi, $_POST['diagnosis']);
    $tekanan_darah = mysqli_real_escape_string($koneksi, $_POST['tekanan_darah']);
    $suhu_tubuh = mysqli_real_escape_string($koneksi, $_POST['suhu_tubuh']);
    $tindakan = mysqli_real_escape_string($koneksi, $_POST['tindakan']);
    $rujukan = mysqli_real_escape_string($koneksi, $_POST['rujukan']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    
    // Petugas (Ganti dengan data sesi user login yang sebenarnya)
    $petugas = isset($_POST['petugas']) ? mysqli_real_escape_string($koneksi, $_POST['petugas']) : 'Petugas Default (Sesi)'; 
    $tanggal_berobat = date("Y-m-d H:i:s"); 

    // Query INSERT data
    $query = "INSERT INTO berobat (id_card, tanggal_berobat, keluhan, diagnosis, tekanan_darah, suhu_tubuh, tindakan, rujukan, catatan, petugas, created_at) 
              VALUES ('$id_card', '$tanggal_berobat', '$keluhan', '$diagnosis', '$tekanan_darah', '$suhu_tubuh', '$tindakan', '$rujukan', '$catatan', '$petugas', NOW())";

    if (mysqli_query($koneksi, $query)) {
        // REDIRECT OTOMATIS setelah sukses simpan
        header("Location: riwayat_berobat.php?status=success_add"); 
        exit();
    } else {
        // Jika gagal, tampilkan pesan error di halaman ini
        $pesan_status = "Gagal menyimpan data pemeriksaan: " . mysqli_error($koneksi) . " ❌";
        $tipe_alert = 'danger';
    }
}

// Query untuk mengambil semua data karyawan (untuk modal pencarian)
$karyawan_query = "SELECT id_card, nama, jabatan, departemen FROM karyawan ORDER BY nama ASC";
$karyawan_result = mysqli_query($koneksi, $karyawan_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app.css">
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="../../assets/extensions/simple-datatables/style.css"> 
    <link rel="stylesheet" crossorigin href="../../assets/compiled/css/table-datatable.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <div id="app">
        <div id="sidebar"></div>

        <div id="main">
            <header class="mb-3"></header>

            <div class="page-heading">
                <h3>Input Pemeriksaan Baru</h3>
                <p class="text-subtitle text-muted">Formulir untuk mencatat pemeriksaan medis pasien (karyawan).</p>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Form Pemeriksaan Pasien</h4>
                    </div>
                    <div class="card-body">

                        <?php if (isset($pesan_status) && $pesan_status): ?>
                            <div class="alert alert-<?= $tipe_alert ?> alert-dismissible fade show" role="alert">
                                <?= $pesan_status ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="form_pemeriksaan.php" method="POST" class="form-horizontal">
                            <div class="row">

                                <div class="col-md-6 col-12">
                                    <h5 class="mt-2 text-primary">Data Pasien & Tanda Vital</h5>
                                    <hr class="mt-0">
                                    
                                    <div class="mb-3">
                                        <label for="id_card_display" class="form-label">ID Card Pasien *</label>
                                        <div class="input-group">
                                            <input type="hidden" id="id_card" name="id_card" required>
                                            <input type="text" class="form-control" id="id_card_display" placeholder="Pilih ID Card" readonly required>
                                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCariKaryawan">
                                                <i class="bi bi-search"></i> Cari Karyawan
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="nama_lengkap_display" class="form-label">Nama Pasien</label>
                                        <input type="text" class="form-control" id="nama_lengkap_display" placeholder="Nama lengkap karyawan" readonly>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="tekanan_darah" class="form-label">Tekanan Darah (Sistolik/Diastolik)</label>
                                            <input type="text" class="form-control" id="tekanan_darah" name="tekanan_darah" placeholder="cth: 120/80">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="suhu_tubuh" class="form-label">Suhu Tubuh (°C)</label>
                                            <input type="number" step="0.1" class="form-control" id="suhu_tubuh" name="suhu_tubuh" placeholder="cth: 36.5">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="keluhan" class="form-label">Keluhan *</label>
                                        <textarea class="form-control" id="keluhan" name="keluhan" rows="2" required placeholder="cth: Demam, Sakit kepala, Batuk"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="diagnosis" class="form-label">Diagnosis</label>
                                        <input type="text" class="form-control" id="diagnosis" name="diagnosis" placeholder="cth: ISPA, Gastritis, Common Cold">
                                    </div>

                                </div>

                                <div class="col-md-6 col-12">
                                    <h5 class="mt-2 text-primary">Informasi Kritis & Tindakan</h5>
                                    <hr class="mt-0">
                                    
                                    <div class="mb-3">
                                        <div class="alert alert-warning" id="alergi-box" role="alert">
                                            <h6 class="alert-heading mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Riwayat Medis Statis</h6>
                                            <p class="mb-0">
                                                Penyakit Terdahulu: <strong id="data_penyakit">Pilih pasien untuk memuat data.</strong><br>
                                                Alergi: <strong id="data_alergi">Pilih pasien untuk memuat data.</strong><br>
                                                Golongan Darah: <strong id="data_golongan_darah">Pilih pasien untuk memuat data.</strong>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tindakan" class="form-label">Tindakan / Pemberian Obat *</label>
                                        <textarea class="form-control" id="tindakan" name="tindakan" rows="3" required placeholder="cth: Paracetamol 500mg, Istirahat 1 hari"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="rujukan" class="form-label">Rujukan</label>
                                        <input type="text" class="form-control" id="rujukan" name="rujukan" placeholder="cth: RS Sentosa (Jika dirujuk)">
                                    </div>

                                    <div class="mb-3">
                                        <label for="catatan" class="form-label">Catatan Tambahan</label>
                                        <textarea class="form-control" id="catatan" name="catatan" rows="1" placeholder="Informasi pendukung lain"></textarea>
                                    </div>

                                    <?php $nama_petugas_saat_ini = 'Dokter/Suster'; ?>
                                    <input type="hidden" name="petugas" value="<?= htmlspecialchars($nama_petugas_saat_ini) ?>">
                                    
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-end border-top pt-3 mt-3"> 
                                <button type="submit" class="btn btn-primary me-1 mb-1">Simpan Pemeriksaan</button> 
                                <a href="riwayat_berobat.php" class="btn btn-secondary me-1 mb-1">Kembali ke Daftar</a> 
                                <button type="reset" class="btn btn-light-secondary mb-1">Reset Form</button> 
                            </div>
                            <!-- <div class="col-12 d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-primary me-1 mb-1">Simpan Pemeriksaan</button>
                                <button type="reset" class="btn btn-light-secondary mb-1">Batal/Reset</button>
                            </div> -->
                        </form>

                    </div>
                </div>
            </section>
            
            <div class="modal fade" id="modalCariKaryawan" tabindex="-1" aria-labelledby="modalCariKaryawanLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCariKaryawanLabel">Pilih Data Karyawan (Pasien)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-striped" id="tabelPilihKaryawan">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID-Card</th>
                                        <th>Nama</th>
                                        <th>Jabatan</th>
                                        <th>Departemen</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    mysqli_data_seek($karyawan_result, 0); 
                                    while($data = mysqli_fetch_assoc($karyawan_result)): ?>
                                        <tr>
                                            <td><?= $data['id_card'] ?></td>
                                            <td><?= $data['nama'] ?></td>
                                            <td><?= $data['jabatan'] ?></td>
                                            <td><?= $data['departemen'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success btn-pilih-karyawan" 
                                                    data-id="<?= $data['id_card'] ?>" 
                                                    data-nama="<?= htmlspecialchars($data['nama']) ?>">Pilih</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <footer></footer>
        </div>
    </div>
    
    <script src="../../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../../assets/compiled/js/app.js"></script>
    
    <script src="../../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        // Inisialisasi Simple-Datatables untuk tabel di modal
        let tableKaryawan = new simpleDatatables.DataTable(document.getElementById('tabelPilihKaryawan'));
        
        // Fungsi untuk mengambil data riwayat medis via AJAX
        function fetchRiwayatMedis(idCard) {
            const penyakitEl = document.getElementById('data_penyakit'); 
            const alergiEl = document.getElementById('data_alergi');
            const golDarahEl = document.getElementById('data_golongan_darah');
            const alergiBox = document.getElementById('alergi-box');
            
            // Set status loading
            penyakitEl.innerText = 'Memuat...'; 
            alergiEl.innerText = 'Memuat...';
            golDarahEl.innerText = 'Memuat...';
            alergiBox.classList.remove('alert-danger', 'alert-warning', 'alert-success');
            alergiBox.classList.add('alert-warning');

            fetch('get_riwayat_medis_ajax.php?id_card=' + idCard)
                .then(response => response.json())
                .then(data => {
                    // Isi data dari response
                    penyakitEl.innerText = data.penyakit_terdahulu; 
                    alergiEl.innerText = data.alergi;
                    golDarahEl.innerText = data.golongan_darah;
                    
                    // Logic untuk ganti warna box berdasarkan Alergi/Penyakit Terdahulu
                    
                    const hasAlergi = data.alergi.toLowerCase() !== 'tidak ada' && data.alergi.toLowerCase() !== 'belum ada data';
                    const hasPenyakit = data.penyakit_terdahulu.toLowerCase() !== 'tidak ada' && data.penyakit_terdahulu.toLowerCase() !== 'belum ada data';
                    
                    if (data.status === 'success' && (hasAlergi || hasPenyakit)) {
                        // Alergi atau Penyakit DITEMUKAN: KRITIS
                        alergiBox.classList.remove('alert-warning', 'alert-success');
                        alergiBox.classList.add('alert-danger');
                    } else if (data.status === 'success') {
                         // Riwayat diisi, tapi tidak ada alergi/penyakit: AMAN
                        alergiBox.classList.remove('alert-danger', 'alert-warning');
                        alergiBox.classList.add('alert-success');
                    } else {
                        // BELUM ADA DATA: PERINGATAN (Warning)
                        alergiBox.classList.remove('alert-danger', 'alert-success');
                        alergiBox.classList.add('alert-warning'); 
                    }
                })
                .catch(error => {
                    console.error('Error fetching riwayat medis:', error);
                    penyakitEl.innerText = 'Gagal memuat';
                    alergiEl.innerText = 'Gagal memuat';
                    golDarahEl.innerText = 'Gagal memuat';
                });
        }

        // Event listener saat tombol 'Pilih' di modal diklik
        document.getElementById('tabelPilihKaryawan').addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-pilih-karyawan')) {
                const idCard = e.target.getAttribute('data-id');
                const nama = e.target.getAttribute('data-nama');
                
                // 1. Isi field di form utama
                document.getElementById('id_card').value = idCard; // Input Hidden (untuk POST)
                document.getElementById('id_card_display').value = idCard; 
                document.getElementById('nama_lengkap_display').value = nama;
                
                // 2. Panggil fungsi untuk mengambil data riwayat medis (AJAX)
                fetchRiwayatMedis(idCard);

                // 3. Tutup modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalCariKaryawan'));
                modal.hide();
            }
        });
    </script>
</body>
</html>