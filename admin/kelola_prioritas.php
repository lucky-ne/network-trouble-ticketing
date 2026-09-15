<?php
/**
 * Manajemen Prioritas & Standar SLA
 * Panel Administrator untuk Mengelola Jam Batas Waktu SLA dan Tingkat Urgensi
 */
require_once __DIR__ . '/../config/database.php';

// Proteksi Halaman
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Kelola Prioritas & Standar SLA';

// PROSES TAMBAH PRIORITAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_priority') {
    $name        = trim($_POST['name'] ?? '');
    $sla_hours   = (int)($_POST['sla_hours'] ?? 1);
    $badge_color = trim($_POST['badge_color'] ?? 'primary');
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || $sla_hours <= 0) {
        set_flash('danger', 'Nama prioritas dan jam SLA harus diisi dengan benar!');
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO priorities (name, sla_hours, badge_color, description) VALUES (?, ?, ?, ?)");
        $stmt_ins->execute([$name, $sla_hours, $badge_color, $description]);
        set_flash('success', "Prioritas '{$name}' dengan batas SLA {$sla_hours} Jam berhasil ditambahkan!");
    }
    header('Location: ' . base_url('admin/kelola_prioritas.php'));
    exit;
}

// PROSES EDIT PRIORITAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_priority') {
    $priority_id = (int)($_POST['priority_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $sla_hours   = (int)($_POST['sla_hours'] ?? 1);
    $badge_color = trim($_POST['badge_color'] ?? 'primary');
    $description = trim($_POST['description'] ?? '');

    if ($priority_id > 0 && !empty($name) && $sla_hours > 0) {
        $stmt_upd = $pdo->prepare("UPDATE priorities SET name = ?, sla_hours = ?, badge_color = ?, description = ? WHERE id = ?");
        $stmt_upd->execute([$name, $sla_hours, $badge_color, $description, $priority_id]);
        set_flash('success', "Data prioritas '{$name}' berhasil diperbarui!");
    }
    header('Location: ' . base_url('admin/kelola_prioritas.php'));
    exit;
}

// PROSES HAPUS PRIORITAS
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM priorities WHERE id = ?");
        $stmt_del->execute([$delete_id]);
        set_flash('success', 'Prioritas SLA berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal menghapus prioritas karena sedang digunakan pada data tiket.');
    }
    header('Location: ' . base_url('admin/kelola_prioritas.php'));
    exit;
}

// Ambil data prioritas beserta jumlah tiket
$priorities = $pdo->query("
    SELECT p.*, COUNT(t.id) as ticket_count
    FROM priorities p
    LEFT JOIN tickets t ON p.id = t.priority_id
    GROUP BY p.id
    ORDER BY p.sla_hours ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard.php') ?>">Dashboard Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Master Prioritas & SLA</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0 text-dark"><i class="fas fa-stopwatch text-danger me-2"></i>Master Prioritas & Standar SLA</h3>
                <p class="text-muted small mb-0">Atur batasan waktu penyelesaian tiket (*Service Level Agreement*) dalam satuan jam.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPrioritas">
                <i class="fas fa-plus-circle me-1"></i> Tambah Prioritas Baru
            </button>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tingkat Prioritas</th>
                        <th>Target SLA (Jam)</th>
                        <th>Tampilan Badge</th>
                        <th>Keterangan Dampak Operasional</th>
                        <th class="text-center">Tiket Aktif/Riwayat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($priorities as $p): 
                    ?>
                        <tr>
                            <td class="text-muted small"><?= $no++ ?></td>
                            <td class="fw-bold text-dark">
                                <?= htmlspecialchars($p['name']) ?>
                            </td>
                            <td>
                                <span class="badge bg-dark px-3 py-1 fs-6">
                                    <i class="fas fa-clock me-1 text-warning"></i> <?= $p['sla_hours'] ?> Jam
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= htmlspecialchars($p['badge_color']) ?> px-3 py-1">
                                    <?= htmlspecialchars($p['name']) ?>
                                </span>
                            </td>
                            <td class="text-secondary small"><?= htmlspecialchars($p['description'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <?= $p['ticket_count'] ?> Tiket
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-prio" 
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-hours="<?= $p['sla_hours'] ?>"
                                            data-color="<?= htmlspecialchars($p['badge_color']) ?>"
                                            data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                            title="Edit Prioritas">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <a href="<?= base_url('admin/kelola_prioritas.php?delete_id=' . $p['id']) ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus prioritas <?= htmlspecialchars($p['name']) ?>?');"
                                       title="Hapus Prioritas">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Prioritas -->
<div class="modal fade" id="modalTambahPrioritas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Tambah Prioritas & Standar SLA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_priority">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-secondary">Nama Prioritas <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Urgent / Emergency" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-secondary">Batas SLA (Jam) <span class="text-danger">*</span></label>
                            <input type="number" name="sla_hours" class="form-control" min="1" placeholder="Misal: 2" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Warna Badge Tampilan</label>
                            <select name="badge_color" class="form-select">
                                <option value="danger">Merah (Danger - Kritis)</option>
                                <option value="warning">Kuning (Warning - Tinggi)</option>
                                <option value="info">Biru Muda (Info - Sedang)</option>
                                <option value="primary">Biru Tua (Primary - Standar)</option>
                                <option value="secondary">Abu-abu (Secondary - Ringan)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Keterangan / Deskripsi Dampak</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Penjelasan dampak kendala terhadap operasional..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fas fa-save me-1"></i> Simpan Prioritas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Prioritas -->
<div class="modal fade" id="modalEditPrioritas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Prioritas & SLA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_priority">
                <input type="hidden" name="priority_id" id="edit_prio_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-secondary">Nama Prioritas <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_prio_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-secondary">Batas SLA (Jam) <span class="text-danger">*</span></label>
                            <input type="number" name="sla_hours" id="edit_prio_hours" class="form-control" min="1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Warna Badge Tampilan</label>
                            <select name="badge_color" id="edit_prio_color" class="form-select">
                                <option value="danger">Merah (Danger - Kritis)</option>
                                <option value="warning">Kuning (Warning - Tinggi)</option>
                                <option value="info">Biru Muda (Info - Sedang)</option>
                                <option value="primary">Biru Tua (Primary - Standar)</option>
                                <option value="secondary">Abu-abu (Secondary - Ringan)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Keterangan / Deskripsi Dampak</label>
                            <textarea name="description" id="edit_prio_description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fas fa-check me-1"></i> Update Prioritas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editPrioBtns = document.querySelectorAll('.btn-edit-prio');
    editPrioBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_prio_id').value          = this.dataset.id;
            document.getElementById('edit_prio_name').value        = this.dataset.name;
            document.getElementById('edit_prio_hours').value       = this.dataset.hours;
            document.getElementById('edit_prio_color').value       = this.dataset.color;
            document.getElementById('edit_prio_description').value = this.dataset.description;
            new bootstrap.Modal(document.getElementById('modalEditPrioritas')).show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
