<?php
session_start();
include '../../config/koneksi.php';

$title = 'Data Karyawan';
$useDataTable = true;
include '../../includes/header.php';

// Query untuk ambil data karyawan
$query = "SELECT * FROM karyawan ORDER BY nama ASC";
$result = mysqli_query($conn, $query);
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Data Karyawan</h3>
                <p class="text-subtitle text-muted">Kelola data karyawan perusahaan</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Data Karyawan</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Daftar Karyawan</h5>
                    <a href="tambah.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Tambah Karyawan
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-striped" id="table1">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Card</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Departemen</th>
                            <th>Jenis Kelamin</th>
                            <th>Telepon</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($result)): 
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= $row['id_card'] ?></strong></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['jabatan'] ?></td>
                            <td><span class="badge bg-light-primary"><?= $row['departemen'] ?></span></td>
                            <td><?= $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                            <td><?= $row['telepon'] ?></td>
                            <td>
                                <?php if($row['status'] == 'Aktif'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="riwayat_medis.php?id_card=<?= $row['id_card'] ?>" 
                                       class="btn btn-sm btn-info" title="Riwayat Medis">
                                        <i class="bi bi-heart-pulse"></i>
                                    </a>
                                    <a href="edit.php?id_card=<?= $row['id_card'] ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="hapus.php?id_card=<?= $row['id_card'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus data ini?')"
                                       title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>