<footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>2025 &copy; Klinik Perusahaan</p>
                    </div>
                    <div class="float-end">
                        <p>Crafted with <span class="text-danger"><i class="bi bi-heart-fill icon-mid"></i></span>
                            by <a href="#">IT PT. Daelim Indonesia</a></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="../assets/static/js/components/dark.js"></script>
    <script src="../assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../assets/compiled/js/app.js"></script>
    
    <!-- DataTables -->
    <script src="../assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        // Init DataTable
        <?php if(isset($useDataTable) && $useDataTable): ?>
        let table1 = document.querySelector('#table1');
        let dataTable = new simpleDatatables.DataTable(table1);
        <?php endif; ?>
    </script>
    
    <!-- Choices.js untuk select2 -->
    <script src="../assets/extensions/choices.js/public/assets/scripts/choices.js"></script>
    
    <!-- Flatpickr untuk datepicker -->
    <script src="../assets/extensions/flatpickr/flatpickr.min.js"></script>
    <script src="../assets/extensions/flatpickr/l10n/id.js"></script>
    
    <!-- Custom Scripts -->
    <?php if(isset($customScript)): ?>
        <script><?= $customScript ?></script>
    <?php endif; ?>
    
    <!-- SweetAlert2 untuk notifikasi -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Alert Success/Error -->
    <?php if(isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= $_SESSION['success'] ?>',
            showConfirmButton: false,
            timer: 2000
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?= $_SESSION['error'] ?>'
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
    
</body>
</html>