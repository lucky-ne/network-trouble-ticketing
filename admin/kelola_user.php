<?php
/**
 * Manajemen Pengguna Sistem (User CRUD) B2B
 * Kelola Akun PIC Klien Korporat, NOC Helpdesk, Field Engineer, Manager, dan Admin
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Manajemen Pengguna Sistem B2B';

$clients = $pdo->query("SELECT id, company_name, circuit_id FROM clients ORDER BY company_name ASC")->fetchAll();

// PROSES TAMBAH USER BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $nip        = trim($_POST['nip'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $role       = trim($_POST['role'] ?? 'karyawan');
    $client_id  = ($role === 'karyawan' && !empty($_POST['client_id'])) ? (int)$_POST['client_id'] : null;
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    if (empty($nip) || empty($name) || empty($email) || empty($password)) {
        set_flash('danger', 'Harap lengkapi semua kolom wajib (NIP/ID, Nama, Email, Password)!');
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE nip = ? OR email = ?");
        $stmt_check->execute([$nip, $email]);
        if ($stmt_check->fetch()) {
            set_flash('danger', 'NIP/ID atau Email sudah terdaftar pada sistem!');
        } else {
            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
            $stmt_ins = $pdo->prepare("INSERT INTO users (nip, name, email, password, role, client_id, department, phone, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt_ins->execute([$nip, $name, $email, $hashed_pass, $role, $client_id, $department, $phone]);
            set_flash('success', "Pengguna baru '{$name}' (" . strtoupper($role) . ") berhasil ditambahkan!");
        }
    }
    header('Location: ' . base_url('admin/kelola_user.php'));
    exit;
}

// PROSES EDIT DATA USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id    = (int)($_POST['user_id'] ?? 0);
    $nip        = trim($_POST['nip'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $role       = trim($_POST['role'] ?? 'karyawan');
    $client_id  = ($role === 'karyawan' && !empty($_POST['client_id'])) ? (int)$_POST['client_id'] : null;
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    if ($user_id > 0) {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (nip = ? OR email = ?) AND id != ?");
        $stmt_check->execute([$nip, $email, $user_id]);
        if ($stmt_check->fetch()) {
            set_flash('danger', 'NIP/ID atau Email sudah digunakan oleh pengguna lain!');
        } else {
            $stmt_upd = $pdo->prepare("UPDATE users SET nip = ?, name = ?, email = ?, role = ?, client_id = ?, department = ?, phone = ? WHERE id = ?");
            $stmt_upd->execute([$nip, $name, $email, $role, $client_id, $department, $phone, $user_id]);
            set_flash('success', "Data pengguna '{$name}' berhasil diperbarui!");
        }
    }
    header('Location: ' . base_url('admin/kelola_user.php'));
    exit;
}

// PROSES RESET PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $user_id      = (int)($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');

    if ($user_id > 0 && !empty($new_password)) {
        $hashed_pass = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt_pwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_pwd->execute([$hashed_pass, $user_id]);
        set_flash('success', 'Password pengguna berhasil diubah/direset!');
    }
    header('Location: ' . base_url('admin/kelola_user.php'));
    exit;
}

// PROSES HAPUS USER
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($delete_id === (int)$_SESSION['user']['id']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan untuk login!');
    } else {
        try {
            $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$delete_id]);
            set_flash('success', 'Pengguna berhasil dihapus.');
        } catch (PDOException $e) {
            set_flash('danger', 'Gagal menghapus pengguna karena memiliki keterkaitan tiket. Hapus atau pindahkan tiket terlebih dahulu.');
        }
    }
    header('Location: ' . base_url('admin/kelola_user.php'));
    exit;
}

$users = $pdo->query("SELECT u.*, cl.company_name, cl.circuit_id 
                      FROM users u 
                      LEFT JOIN clients cl ON u.client_id = cl.id 
                      ORDER BY u.id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-users-cog text-primary me-2"></i>Manajemen Akun Pengguna Sistem
        </h4>
        <p class="text-secondary small mb-0">Kelola akun PIC Klien Korporat B2B, NOC Dispatcher, Field Engineer, dan Manager.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-1"></i> Tambah Pengguna Baru
    </button>
</div>

<!-- Tabel Daftar User -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Daftar Pengguna Aktif</span>
        <span class="badge bg-light text-dark border">Total: <?= count($users) ?> Akun</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th style="width: 110px;">NIP / ID</th>
                        <th style="width: 170px;">Nama Lengkap</th>
                        <th style="width: 180px;">Email Akun</th>
                        <th style="width: 130px;">Peran (Role)</th>
                        <th>Perusahaan / Divisi</th>
                        <th style="width: 130px;">No. Telepon</th>
                        <th style="width: 110px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-secondary"><?= htmlspecialchars($u['nip']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php
                                $r_badges = [
                                    'admin'    => 'bg-danger',
                                    'karyawan' => 'bg-primary',
                                    'helpdesk' => 'bg-info text-dark',
                                    'teknisi'  => 'bg-warning text-dark',
                                    'manager'  => 'bg-success'
                                ];
                                $r_labels = [
                                    'admin'    => 'Admin Master',
                                    'karyawan' => 'PIC Klien B2B',
                                    'helpdesk' => 'NOC Helpdesk',
                                    'teknisi'  => 'Field Engineer',
                                    'manager'  => 'Manager NOC'
                                ];
                                ?>
                                <span class="badge <?= $r_badges[$u['role']] ?? 'bg-secondary' ?>">
                                    <?= $r_labels[$u['role']] ?? ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'karyawan' && !empty($u['company_name'])): ?>
                                    <div class="fw-semibold text-primary"><?= htmlspecialchars($u['company_name']) ?></div>
                                    <small class="circuit-badge"><?= htmlspecialchars($u['circuit_id']) ?></small>
                                <?php else: ?>
                                    <span class="text-dark small"><?= htmlspecialchars($u['department']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserModal<?= $u['id'] ?>"
                                            title="Edit Pengguna">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning text-dark" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#resetPassModal<?= $u['id'] ?>"
                                            title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($u['id'] !== (int)$_SESSION['user']['id']): ?>
                                        <a href="<?= base_url('admin/kelola_user.php?delete_id=' . $u['id']) ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus akun <?= htmlspecialchars($u['name']) ?>?');"
                                           title="Hapus Akun">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Modal Edit User -->
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="fas fa-user-edit text-primary me-1"></i> Edit Pengguna: <?= htmlspecialchars($u['name']) ?>
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">NIP / ID PIC <span class="text-danger">*</span></label>
                                                        <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($u['nip']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email Akun <span class="text-danger">*</span></label>
                                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Peran (Role) <span class="text-danger">*</span></label>
                                                        <select name="role" class="form-select" required>
                                                            <option value="karyawan" <?= ($u['role'] === 'karyawan') ? 'selected' : '' ?>>PIC Klien Korporat B2B</option>
                                                            <option value="helpdesk" <?= ($u['role'] === 'helpdesk') ? 'selected' : '' ?>>NOC / Helpdesk B2B</option>
                                                            <option value="teknisi" <?= ($u['role'] === 'teknisi') ? 'selected' : '' ?>>Field / Network Engineer</option>
                                                            <option value="manager" <?= ($u['role'] === 'manager') ? 'selected' : '' ?>>Head of NOC / Manager SLA</option>
                                                            <option value="admin" <?= ($u['role'] === 'admin') ? 'selected' : '' ?>>Administrator Master</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Perusahaan Mitra (Khusus PIC Klien)</label>
                                                        <select name="client_id" class="form-select">
                                                            <option value="">-- Bukan Akun Klien (Internal Visimedia) --</option>
                                                            <?php foreach ($clients as $cl): ?>
                                                                <option value="<?= $cl['id'] ?>" <?= ($u['client_id'] == $cl['id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($cl['company_name']) ?> (<?= htmlspecialchars($cl['circuit_id']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Divisi / Jabatan</label>
                                                        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($u['department']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nomor Telepon / WhatsApp</label>
                                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone']) ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Reset Password -->
                                <div class="modal fade" id="resetPassModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="fas fa-key text-warning me-1"></i> Reset Password: <?= htmlspecialchars($u['name']) ?>
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                                    <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold text-dark">
                                                        <i class="fas fa-lock me-1"></i> Update Password
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

<!-- Modal Tambah Pengguna Baru -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog text-start">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header bg-light">
                    <h6 class="modal-title fw-bold text-dark">
                        <i class="fas fa-user-plus text-primary me-1"></i> Tambah Pengguna Baru
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">NIP / ID PIC <span class="text-danger">*</span></label>
                        <input type="text" name="nip" class="form-control" placeholder="Contoh: PIC-TEL-01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Akun <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="user@perusahaan.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Akun <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peran (Role) <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="karyawan">PIC Klien Korporat B2B</option>
                            <option value="helpdesk">NOC / Helpdesk B2B</option>
                            <option value="teknisi">Field / Network Engineer</option>
                            <option value="manager">Head of NOC / Manager SLA</option>
                            <option value="admin">Administrator Master</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Perusahaan Mitra (Khusus PIC Klien)</label>
                        <select name="client_id" class="form-select">
                            <option value="">-- Bukan Akun Klien (Internal Visimedia) --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>">
                                    <?= htmlspecialchars($cl['company_name']) ?> (<?= htmlspecialchars($cl['circuit_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Divisi / Jabatan</label>
                        <input type="text" name="department" class="form-control" placeholder="Contoh: IT Network Manager">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" class="form-control" placeholder="0812xxxxxxxx">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="fas fa-save me-1"></i> Daftarkan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
