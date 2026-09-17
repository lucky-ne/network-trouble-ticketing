    </div> <!-- Close .container -->
</div> <!-- Close .container-fluid main -->

<!-- Footer Formal PT. Visimedia Pratama Persada -->
<footer class="footer mt-auto py-3 bg-white border-top no-print">
    <div class="container text-muted small">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span class="fw-semibold text-dark">&copy; <?= date('Y') ?> <?= htmlspecialchars(get_setting('company_name', 'PT. Visimedia Pratama Persada')) ?> &bull; <?= htmlspecialchars(get_setting('app_name', 'VMP-NetTicket')) ?></span>
                <span class="d-block text-secondary" style="font-size: 0.76rem;">
                    <?= htmlspecialchars(get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions')) ?>
                    <span class="badge bg-light text-secondary border px-1 ms-1"><?= htmlspecialchars(get_setting('system_version', 'v1.0 Enterprise')) ?></span>
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <?php if (isset($_SESSION['user'])): ?>
                <?php 
                $active_role = $_SESSION['user']['role'] ?? ''; 
                // Normalisasi karyawan/customer
                $is_karyawan = ($active_role === 'karyawan' || $active_role === 'customer');
                $is_helpdesk = ($active_role === 'helpdesk');
                $is_teknisi  = ($active_role === 'teknisi');
                $is_manager  = ($active_role === 'manager');
                $is_admin    = ($active_role === 'admin');
                ?>
                <div class="d-inline-flex align-items-center gap-1 p-1 rounded-md border shadow-xs" style="background:#fafafa; border-color:#e4e4e7 !important;">
                    <span class="small me-1 text-secondary fw-semibold" style="font-size:0.72rem;"><i class="fas fa-user-switch text-dark"></i> Switch Demo:</span>
                    
                    <a href="<?= base_url('auth/login.php?quick_login=karyawan') ?>" 
                       class="btn btn-xs py-1 px-2 rounded-md <?= $is_karyawan ? 'fw-bold' : '' ?>" 
                       style="font-size:0.68rem; <?= $is_karyawan ? 'background:#18181b !important; color:#ffffff !important; border:1px solid #18181b !important; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'background:#ffffff; color:#52525b; border:1px solid #e4e4e7;' ?>" 
                       title="Login sebagai PIC Klien B2B">
                        <?= $is_karyawan ? '<i class="fas fa-check-circle me-1" style="font-size:0.65rem; color:#22c55e;"></i>' : '' ?>PIC Klien
                    </a>
                    
                    <a href="<?= base_url('auth/login.php?quick_login=helpdesk') ?>" 
                       class="btn btn-xs py-1 px-2 rounded-md <?= $is_helpdesk ? 'fw-bold' : '' ?>" 
                       style="font-size:0.68rem; <?= $is_helpdesk ? 'background:#18181b !important; color:#ffffff !important; border:1px solid #18181b !important; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'background:#ffffff; color:#52525b; border:1px solid #e4e4e7;' ?>" 
                       title="Login sebagai Helpdesk / NOC">
                        <?= $is_helpdesk ? '<i class="fas fa-check-circle me-1" style="font-size:0.65rem; color:#22c55e;"></i>' : '' ?>NOC Helpdesk
                    </a>
                    
                    <a href="<?= base_url('auth/login.php?quick_login=teknisi') ?>" 
                       class="btn btn-xs py-1 px-2 rounded-md <?= $is_teknisi ? 'fw-bold' : '' ?>" 
                       style="font-size:0.68rem; <?= $is_teknisi ? 'background:#18181b !important; color:#ffffff !important; border:1px solid #18181b !important; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'background:#ffffff; color:#52525b; border:1px solid #e4e4e7;' ?>" 
                       title="Login sebagai Field Engineer">
                        <?= $is_teknisi ? '<i class="fas fa-check-circle me-1" style="font-size:0.65rem; color:#22c55e;"></i>' : '' ?>Teknisi
                    </a>
                    
                    <a href="<?= base_url('auth/login.php?quick_login=manager') ?>" 
                       class="btn btn-xs py-1 px-2 rounded-md <?= $is_manager ? 'fw-bold' : '' ?>" 
                       style="font-size:0.68rem; <?= $is_manager ? 'background:#18181b !important; color:#ffffff !important; border:1px solid #18181b !important; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'background:#ffffff; color:#52525b; border:1px solid #e4e4e7;' ?>" 
                       title="Login sebagai Manager SLA">
                        <?= $is_manager ? '<i class="fas fa-check-circle me-1" style="font-size:0.65rem; color:#22c55e;"></i>' : '' ?>Manager
                    </a>
                    
                    <a href="<?= base_url('auth/login.php?quick_login=admin') ?>" 
                       class="btn btn-xs py-1 px-2 rounded-md <?= $is_admin ? 'fw-bold' : '' ?>" 
                       style="font-size:0.68rem; <?= $is_admin ? 'background:#18181b !important; color:#ffffff !important; border:1px solid #18181b !important; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'background:#ffffff; color:#52525b; border:1px solid #e4e4e7;' ?>" 
                       title="Login sebagai Admin Master">
                        <?= $is_admin ? '<i class="fas fa-check-circle me-1" style="font-size:0.65rem; color:#22c55e;"></i>' : '' ?>Admin
                    </a>
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
