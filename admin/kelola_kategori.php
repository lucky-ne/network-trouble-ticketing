<?php
/**
 * Manajemen Kategori Gangguan Jaringan
 * Panel Administrator untuk Mengelola Jenis Masalah & Tiket Terkait
 */
require_once __DIR__ . '/../config/database.php';

// Proteksi Halaman
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Kelola Kategori Gangguan Jaringan';

// PROSES TAMBAH KATEGORI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_category') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        set_flash('danger', 'Nama kategori tidak boleh kosong!');
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt_ins->execute([$name, $description]);
        set_flash('success', "Kategori '{$name}' berhasil ditambahkan!");
    }
    header('Location: ' . base_url('admin/kelola_kategori.php'));
    exit;
}

// PROSES EDIT KATEGORI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $cat_id      = (int)($_POST['category_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($cat_id > 0 && !empty($name)) {
        $stmt_upd = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt_upd->execute([$name, $description, $cat_id]);
        set_flash('success', "Kategori '{$name}' berhasil diperbarui!");
    }
    header('Location: ' . base_url('admin/kelola_kategori.php'));
    exit;
}

// PROSES HAPUS KATEGORI
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt_del->execute([$delete_id]);
        set_flash('success', 'Kategori gangguan berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal menghapus kategori karena sedang digunakan pada data tiket.');
    }
    header('Location: ' . base_url('admin/kelola_kategori.php'));
    exit;
}

// Ambil data kategori beserta jumlah tiket
$categories = $pdo->query("
    SELECT c.*, COUNT(t.id) as ticket_count
    FROM categories c
    LEFT JOIN tickets t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY c.id ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-tags text-danger me-2"></i>Master Kategori Gangguan Jaringan
        </h4>
        <p class="text-secondary small mb-0">Kelola klasifikasi jenis gangguan jaringan (Wi-Fi, LAN, Port, VPN, Server, dll).</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
        <i class="fas fa-plus-circle me-1"></i> Tambah Kategori Baru
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi & Ruang Lingkup</th>
                        <th class="text-center">Jumlah Tiket Terkait</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($categories as $cat): 
                    ?>
                        <tr>
                            <td class="text-muted small"><?= $no++ ?></td>
                            <td class="fw-bold text-dark">
                                <i class="fas fa-network-wired text-primary me-2"></i><?= htmlspecialchars($cat['name']) ?>
                            </td>
                            <td class="text-secondary small"><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-3 py-1">
                                    <i class="fas fa-ticket-alt text-muted me-1"></i> <?= $cat['ticket_count'] ?> Tiket
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-cat" 
                                            data-id="<?= $cat['id'] ?>"
                                            data-name="<?= htmlspecialchars($cat['name']) ?>"
                                            data-description="<?= htmlspecialchars($cat['description'] ?? '') ?>"
                                            title="Edit Kategori">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <a href="<?= base_url('admin/kelola_kategori.php?delete_id=' . $cat['id']) ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kategori <?= htmlspecialchars($cat['name']) ?>?');"
                                       title="Hapus Kategori">
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

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="modalTambahKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Tambah Kategori Gangguan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_category">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Gangguan DNS / Domain Resolver" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Deskripsi & Ruang Lingkup</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Jelaskan jenis masalah atau panduan teknis kategori ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fas fa-save me-1"></i> Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal fade" id="modalEditKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Kategori Gangguan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_cat_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_cat_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Deskripsi & Ruang Lingkup</label>
                        <textarea name="description" id="edit_cat_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fas fa-check me-1"></i> Update Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editCatBtns = document.querySelectorAll('.btn-edit-cat');
    editCatBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_cat_id').value          = this.dataset.id;
            document.getElementById('edit_cat_name').value        = this.dataset.name;
            document.getElementById('edit_cat_description').value = this.dataset.description;
            new bootstrap.Modal(document.getElementById('modalEditKategori')).show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
