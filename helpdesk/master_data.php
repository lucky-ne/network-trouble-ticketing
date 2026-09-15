<?php
/**
 * Master Data Kategori & Matriks Prioritas SLA
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['helpdesk']);

$pdo = get_db();

// PROSES: Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        set_flash('success', 'Kategori baru berhasil ditambahkan!');
    }
    header('Location: ' . base_url('helpdesk/master_data.php'));
    exit;
}

// PROSES: Hapus Kategori
if (isset($_GET['del_category'])) {
    $del_id = (int)$_GET['del_category'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$del_id]);
        set_flash('success', 'Kategori berhasil dihapus!');
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus kategori! Pastikan tidak ada tiket yang menggunakan kategori ini.');
    }
    header('Location: ' . base_url('helpdesk/master_data.php'));
    exit;
}

// PROSES: Update SLA Prioritas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_priority'])) {
    $p_id = (int)$_POST['priority_id'];
    $sla_hours = (int)$_POST['sla_hours'];
    $badge_color = trim($_POST['badge_color'] ?? 'primary');
    $desc = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare("UPDATE priorities SET sla_hours = ?, badge_color = ?, description = ? WHERE id = ?");
    $stmt->execute([$sla_hours, $badge_color, $desc, $p_id]);
    set_flash('success', 'Standar target SLA berhasil diperbarui!');
    header('Location: ' . base_url('helpdesk/master_data.php'));
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$priorities = $pdo->query("SELECT * FROM priorities ORDER BY sla_hours ASC")->fetchAll();

$page_title = 'Master Data Kategori & SLA Matrix';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark mb-1">
        <i class="fas fa-cogs text-primary me-2"></i>Master Data Kategori & Matriks SLA
    </h3>
    <p class="text-muted mb-0">Atur parameter kategori gangguan dan standar batas waktu penyelesaian (Service Level Agreement).</p>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Matriks Prioritas SLA -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-clock text-warning me-2"></i>Matriks Target Waktu SLA
                </h5>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Prioritas</th>
                                <th>Target SLA</th>
                                <th>Deskripsi Kebijakan</th>
                                <th class="text-center">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($priorities as $p): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= htmlspecialchars($p['badge_color']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <?= $p['sla_hours'] ?> Jam
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($p['description']) ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-sla" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                                data-hours="<?= $p['sla_hours'] ?>"
                                                data-color="<?= htmlspecialchars($p['badge_color']) ?>"
                                                data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                                title="Edit Standar SLA" data-bs-toggle="modal" data-bs-target="#modalEditSLA">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Daftar Kategori Gangguan -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-tags text-primary me-2"></i>Kategori Gangguan Jaringan
                </h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                    <i class="fas fa-plus me-1"></i> Tambah Kategori
                </button>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th class="text-center">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $idx => $cat): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($cat['description']) ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('helpdesk/master_data.php?del_category=' . $cat['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus kategori ini?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="add_category" value="1">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Kategori Kendala Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Firewall Blocking" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi Kendala</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Jelaskan jenis gangguan yang masuk kategori ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-semibold">Tambah</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit SLA (Single Modal Outside Table) -->
<div class="modal fade" id="modalEditSLA" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content text-start border-0 shadow-lg rounded-4 overflow-hidden">
            <input type="hidden" name="update_priority" value="1">
            <input type="hidden" name="priority_id" id="edit_sla_id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Standar SLA: <span id="edit_sla_title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Target Waktu SLA (Jam) <span class="text-danger">*</span></label>
                    <input type="number" name="sla_hours" id="edit_sla_hours" class="form-control" min="1" required>
                    <small class="text-muted" style="font-size:0.75rem;">Batas waktu maksimal perbaikan sebelum dinyatakan Breached.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Warna Badge</label>
                    <select name="badge_color" id="edit_sla_color" class="form-select">
                        <option value="danger">Merah (Danger)</option>
                        <option value="warning">Kuning (Warning)</option>
                        <option value="info">Biru Muda (Info)</option>
                        <option value="primary">Biru (Primary)</option>
                        <option value="secondary">Abu-abu (Secondary)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Deskripsi</label>
                    <textarea name="description" id="edit_sla_desc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-semibold px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editSlaBtns = document.querySelectorAll('.btn-edit-sla');
    editSlaBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_sla_id').value    = this.dataset.id;
            document.getElementById('edit_sla_title').textContent = this.dataset.name;
            document.getElementById('edit_sla_hours').value = this.dataset.hours;
            document.getElementById('edit_sla_color').value = this.dataset.color;
            document.getElementById('edit_sla_desc').value  = this.dataset.description;
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
