<ul class="menu">
    <li class="sidebar-item active">
        <a href="index.php" class='sidebar-link'>
            <i class="bi bi-grid-fill"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <li class="sidebar-item has-sub">
        <a href="#" class='sidebar-link'>
            <i class="bi bi-person-badge-fill"></i>
            <span>Pemeriksaan Pasien</span>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="kunjungan/form_pemeriksaan.php">Input Pemeriksaan Baru</a>
            </li>
            <li class="submenu-item">
                <a href="kunjungan/riwayat_berobat.php">Riwayat Berobat</a>
            </li>
        </ul>
    </li>

    <li class="sidebar-item has-sub">
        <a href="#" class='sidebar-link'>
            <i class="bi bi-boxes"></i>
            <span>Farmasi / Obat</span>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="farmasi/master_obat.php">Master Obat & Stok</a>
            </li>
            <li class="submenu-item">
                <a href="farmasi/form_stok_masuk.php">Input Stok Masuk</a>
            </li>
        </ul>
    </li>
    
    <li class="sidebar-item has-sub">
        <a href="#" class='sidebar-link'>
            <i class="bi bi-file-earmark-bar-graph-fill"></i>
            <span>Laporan</span>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="laporan/laporan_berobat.php">Laporan Kunjungan</a>
            </li>
            <li class="submenu-item">
                <a href="laporan/laporan_obat.php">Laporan Mutasi Obat</a>
            </li>
            <li class="submenu-item">
                <a href="laporan/laporan_stok_master.php">Laporan Stok & Aset</a>
            </li>
            <li class="submenu-item">
                <a href="laporan/form_laporan_bulanan.php">Laporan Kecelakaan</a>
            </li>
        </ul>
    </li>
    
    <li class="sidebar-item">
        <a href="master/karyawan.php" class='sidebar-link'>
            <i class="bi bi-people-fill"></i>
            <span>Data Karyawan</span>
        </a>
    </li>
    
    <li class="sidebar-item">
        <a href="logout.php" class='sidebar-link text-danger'>
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </li>

</ul>