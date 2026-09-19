<?php
/**
 * Master Data Klien Korporat B2B & Sirkit Layanan
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['admin']);

$pdo = get_db();

// PROSES 1: Tambah Klien B2B Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_client'])) {
    $company_name   = trim($_POST['company_name'] ?? '');
    $company_code   = trim($_POST['company_code'] ?? '');
    $circuit_id     = trim($_POST['circuit_id'] ?? '');
    $service_type   = trim($_POST['service_type'] ?? '');
    $bandwidth      = trim($_POST['bandwidth'] ?? '');
    $pic_name       = trim($_POST['pic_name'] ?? '');
    $pic_phone      = trim($_POST['pic_phone'] ?? '');
    $pic_email      = trim($_POST['pic_email'] ?? '');
    $site_address   = trim($_POST['site_address'] ?? '');
    $sla_target_pct = (float)($_POST['sla_target_pct'] ?? 99.50);

    if (empty($company_name) || empty($company_code) || empty($circuit_id) || empty($service_type) || empty($pic_name)) {
        set_flash('danger', 'Harap lengkapi semua kolom wajib (*)!');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (
                company_name, company_code, circuit_id, service_type, bandwidth, 
                pic_name, pic_phone, pic_email, site_address, sla_target_pct, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW()
            )");
            $stmt->execute([
                $company_name, $company_code, $circuit_id, $service_type, $bandwidth,
                $pic_name, $pic_phone, $pic_email, $site_address, $sla_target_pct
            ]);
            set_flash('success', "Klien B2B {$company_name} (Sirkit: {$circuit_id}) berhasil didaftarkan!");
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mendaftarkan klien: ' . $e->getMessage());
        }
    }
    header('Location: ' . base_url('admin/kelola_klien.php'));
    exit;
}

// PROSES 2: Update Klien B2B
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_client'])) {
    $id             = (int)$_POST['id'];
    $company_name   = trim($_POST['company_name'] ?? '');
    $company_code   = trim($_POST['company_code'] ?? '');
    $circuit_id     = trim($_POST['circuit_id'] ?? '');
    $service_type   = trim($_POST['service_type'] ?? '');
    $bandwidth      = trim($_POST['bandwidth'] ?? '');
    $pic_name       = trim($_POST['pic_name'] ?? '');
    $pic_phone      = trim($_POST['pic_phone'] ?? '');
    $pic_email      = trim($_POST['pic_email'] ?? '');
    $site_address   = trim($_POST['site_address'] ?? '');
    $sla_target_pct = (float)($_POST['sla_target_pct'] ?? 99.50);
    $status         = $_POST['status'] ?? 'active';

    try {
        $stmt = $pdo->prepare("UPDATE clients SET 
            company_name = ?, company_code = ?, circuit_id = ?, service_type = ?, bandwidth = ?,
            pic_name = ?, pic_phone = ?, pic_email = ?, site_address = ?, sla_target_pct = ?, status = ?
        WHERE id = ?");
        $stmt->execute([
            $company_name, $company_code, $circuit_id, $service_type, $bandwidth,
            $pic_name, $pic_phone, $pic_email, $site_address, $sla_target_pct, $status, $id
        ]);
        set_flash('success', "Data Klien B2B {$company_name} berhasil diperbarui!");
    } catch (Exception $e) {
        set_flash('danger', 'Gagal memperbarui klien: ' . $e->getMessage());
    }
    header('Location: ' . base_url('admin/kelola_klien.php'));
    exit;
}

// PROSES 3: Hapus Klien
if (isset($_GET['del'])) {
    $del_id = (int)$_GET['del'];
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$del_id]);
        set_flash('success', 'Data Klien B2B berhasil dihapus!');
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus! Pastikan tidak ada tiket yang terkait dengan klien ini.');
    }
    header('Location: ' . base_url('admin/kelola_klien.php'));
    exit;
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();

$page_title = 'Master Klien B2B & Sirkit Layanan';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-building text-warning me-2"></i>Master Klien Korporat B2B & Sirkit Jaringan
        </h4>
        <p class="text-secondary small mb-0">Kelola daftar perusahaan mitra, nomor sirkit (Circuit ID), jenis layanan kontrak, dan PIC resmi.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addClientModal">
        <i class="fas fa-plus-circle me-1"></i> Tambah Klien B2B Baru
    </button>
</div>

<!-- Tabel Daftar Klien B2B -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Daftar Perusahaan Mitra & Sirkit Aktif</span>
        <span class="badge bg-light text-dark border">Total: <?= count($clients) ?> Klien</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">No</th>
                        <th>Perusahaan Klien</th>
                        <th style="width: 140px;">Sirkit (CID)</th>
                        <th>Jenis Layanan B2B</th>
                        <th style="width: 110px;">Bandwidth</th>
                        <th>PIC & Kontak</th>
                        <th style="width: 95px;" class="text-center">Target SLA</th>
                        <th style="width: 90px;" class="text-center">Status</th>
                        <th style="width: 90px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($clients as $cl): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($cl['company_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($cl['company_code']) ?></small>
                            </td>
                            <td>
                                <span class="circuit-badge"><?= htmlspecialchars($cl['circuit_id']) ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= htmlspecialchars($cl['service_type']) ?></div>
                                <small class="text-muted d-block text-truncate" style="max-width: 180px;"><?= htmlspecialchars($cl['site_address']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($cl['bandwidth']) ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?= htmlspecialchars($cl['pic_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($cl['pic_phone']) ?> | <?= htmlspecialchars($cl['pic_email']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark"><?= $cl['sla_target_pct'] ?>%</span>
                            </td>
                            <td>
                                <?php if ($cl['status'] === 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= ucfirst($cl['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?= $cl['id'] ?>"
                                            title="Edit Data Klien">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?= base_url('admin/kelola_klien.php?del=' . $cl['id']) ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data klien <?= htmlspecialchars($cl['company_name']) ?>?');"
                                       title="Hapus Klien">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>

                                <!-- Modal Edit Klien -->
                                <div class="modal fade" id="editModal<?= $cl['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg text-start">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="fas fa-edit text-primary me-1"></i> Edit Data Klien: <?= htmlspecialchars($cl['company_name']) ?>
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Nama Perusahaan Klien <span class="text-danger">*</span></label>
                                                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($cl['company_name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Kode Klien (Account ID) <span class="text-danger">*</span></label>
                                                            <input type="text" name="company_code" class="form-control" value="<?= htmlspecialchars($cl['company_code']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Nomor Sirkit (Circuit ID) <span class="text-danger">*</span></label>
                                                            <input type="text" name="circuit_id" class="form-control" value="<?= htmlspecialchars($cl['circuit_id']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Jenis Layanan B2B <span class="text-danger">*</span></label>
                                                            <input type="text" name="service_type" class="form-control" value="<?= htmlspecialchars($cl['service_type']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Kapasitas Bandwidth <span class="text-danger">*</span></label>
                                                            <input type="text" name="bandwidth" class="form-control" value="<?= htmlspecialchars($cl['bandwidth']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Target SLA Uptime (%)</label>
                                                            <input type="number" step="0.01" name="sla_target_pct" class="form-control" value="<?= $cl['sla_target_pct'] ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Nama PIC Klien <span class="text-danger">*</span></label>
                                                            <input type="text" name="pic_name" class="form-control" value="<?= htmlspecialchars($cl['pic_name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">No. Telepon PIC</label>
                                                            <input type="text" name="pic_phone" class="form-control" value="<?= htmlspecialchars($cl['pic_phone']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Email PIC</label>
                                                            <input type="email" name="pic_email" class="form-control" value="<?= htmlspecialchars($cl['pic_email']) ?>" required>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <label class="form-label">Site / Lokasi Gedung Kantor Klien</label>
                                                            <textarea name="site_address" class="form-control" rows="2" required><?= htmlspecialchars($cl['site_address']) ?></textarea>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Status Kontrak</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= ($cl['status'] === 'active') ? 'selected' : '' ?>>Aktif</option>
                                                                <option value="suspended" <?= ($cl['status'] === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                                                                <option value="inactive" <?= ($cl['status'] === 'inactive') ? 'selected' : '' ?>>Non-Aktif</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="action_edit_client" class="btn btn-primary btn-sm fw-semibold">
                                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Klien Baru -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-light">
                    <h6 class="modal-title fw-bold text-dark">
                        <i class="fas fa-plus-circle text-primary me-1"></i> Registrasi Klien Korporat B2B Baru
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Perusahaan Klien <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" placeholder="Contoh: PT Telkom Akses Mitra" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kode Klien (Account Code) <span class="text-danger">*</span></label>
                            <input type="text" name="company_code" class="form-control" placeholder="Contoh: CLI-TAM-005" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Sirkit (Circuit ID) <span class="text-danger">*</span></label>
                            <input type="text" name="circuit_id" class="form-control" placeholder="Contoh: CID-VMI-0550" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Layanan B2B <span class="text-danger">*</span></label>
                            <select name="service_type" class="form-select" required>
                                <option value="Dedicated Internet Corporate">Dedicated Internet Corporate</option>
                                <option value="IP VPN MPLS Inter-Branch">IP VPN MPLS Inter-Branch</option>
                                <option value="Metro Ethernet Point-to-Point">Metro Ethernet Point-to-Point</option>
                                <option value="Managed SD-WAN Corporate">Managed SD-WAN Corporate</option>
                                <option value="Dark Fiber Leased Line">Dark Fiber Leased Line</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kapasitas Bandwidth <span class="text-danger">*</span></label>
                            <input type="text" name="bandwidth" class="form-control" placeholder="Contoh: 500 Mbps / 1 Gbps" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target SLA Uptime (%)</label>
                            <input type="number" step="0.01" name="sla_target_pct" class="form-control" value="99.50" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama PIC Klien <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control" placeholder="Nama IT PIC" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. Telepon PIC</label>
                            <input type="text" name="pic_phone" class="form-control" placeholder="0812xxxxxxxx" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email PIC</label>
                            <input type="email" name="pic_email" class="form-control" placeholder="pic@perusahaan.com" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Site / Lokasi Gedung Kantor Klien</label>
                            <textarea name="site_address" class="form-control" rows="2" placeholder="Nama gedung, lantai, jalan, kota..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="action_add_client" class="btn btn-primary btn-sm fw-semibold">
                        <i class="fas fa-save me-1"></i> Simpan Data Klien B2B
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
