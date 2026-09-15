    </div> <!-- Close .container -->
</div> <!-- Close .container-fluid main -->

<!-- Footer Formal PT. Visimedia Pratama Persada -->
<footer class="footer mt-auto py-3 bg-white border-top no-print">
    <div class="container text-muted small">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span class="fw-semibold text-dark">&copy; <?= date('Y') ?> <?= htmlspecialchars(get_setting('company_name', 'PT. Visimedia Pratama Persada')) ?></span>
                <span class="d-block text-secondary" style="font-size: 0.76rem;"><?= htmlspecialchars(get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions')) ?></span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <?php if (isset($_SESSION['user'])): ?>
                <div class="d-inline-flex align-items-center gap-1 bg-light p-1 rounded border">
                    <span class="small me-1 text-dark fw-bold" style="font-size:0.75rem;"><i class="fas fa-user-switch text-primary"></i> Demo Role:</span>
                    <a href="<?= base_url('auth/login.php?quick_login=karyawan') ?>" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.7rem;" title="Login sebagai PIC Klien B2B">PIC Klien</a>
                    <a href="<?= base_url('auth/login.php?quick_login=helpdesk') ?>" class="btn btn-xs btn-outline-info text-dark py-0 px-2" style="font-size:0.7rem;" title="Login sebagai Helpdesk / NOC">NOC Helpdesk</a>
                    <a href="<?= base_url('auth/login.php?quick_login=teknisi') ?>" class="btn btn-xs btn-outline-warning text-dark py-0 px-2" style="font-size:0.7rem;" title="Login sebagai Field Engineer">Teknisi</a>
                    <a href="<?= base_url('auth/login.php?quick_login=manager') ?>" class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:0.7rem;" title="Login sebagai Manager SLA">Manager</a>
                    <a href="<?= base_url('auth/login.php?quick_login=admin') ?>" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.7rem;" title="Login sebagai Admin Master">Admin</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(document).ready(function() {
        if ($('.datatable').length) {
            $('.datatable').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        language: {
                            search: "Cari data:",
                            lengthMenu: "Tampilkan _MENU_ baris",
                            info: "Menampilkan _START_ sampai _END_ dari total _TOTAL_ data",
                            infoEmpty: "Tidak ada data yang tersedia",
                            infoFiltered: "(difilter dari _MAX_ total data)",
                            zeroRecords: "Tidak ditemukan data yang sesuai",
                            emptyTable: "Belum ada riwayat tiket gangguan pada filter ini",
                            paginate: {
                                first: "Awal",
                                last: "Akhir",
                                next: "Lanjut",
                                previous: "Kembali"
                            }
                        },
                        pageLength: 10,
                        responsive: true,
                        autoWidth: false
                    });
                }
            });
        }
    });
</script>

</body>
</html>
