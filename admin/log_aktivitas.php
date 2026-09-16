<?php
/**
 * Log Aktivitas & Audit Trail Sistem
 * Menampilkan riwayat seluruh perubahan status tiket, penugasan, dan aksi sistem
 */
require_once __DIR__ . '/../config/database.php';

// Proteksi Halaman
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Log Aktivitas & Audit Trail';

// Ambil seluruh log dari tabel ticket_logs
$logs = $pdo->query("
    SELECT l.*, u.name as user_name, u.role as user_role, u.department,
           t.ticket_code, t.title as ticket_title
    FROM ticket_logs l
    JOIN users u ON l.user_id = u.id
    JOIN tickets t ON l.ticket_id = t.id
    ORDER BY l.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-history text-danger me-2"></i>Log Aktivitas & Audit Trail Sistem
        </h4>
        <p class="text-secondary small mb-0">Riwayat kronologis seluruh tindakan pengguna, penugasan teknisi, dan perubahan status tiket.</p>
    </div>
    <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 170px;">Waktu & Tanggal</th>
                        <th>Aktor / Pengguna</th>
                        <th>Nomor Tiket</th>
                        <th>Judul Masalah</th>
                        <th>Tindakan (Action)</th>
                        <th>Catatan / Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small text-secondary" style="white-space:nowrap;">
                                <i class="fas fa-clock text-muted me-1"></i> <?= format_date_indo($log['created_at']) ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($log['user_name']) ?></div>
                                <span class="badge bg-secondary-subtle text-dark" style="font-size:0.7rem;">
                                    <?= ucfirst($log['user_role']) ?> (<?= htmlspecialchars($log['department'] ?? '-') ?>)
                                </span>
                            </td>
                            <td class="fw-bold text-primary font-monospace">
                                <?= htmlspecialchars($log['ticket_code']) ?>
                            </td>
                            <td>
                                <div class="text-truncate fw-semibold text-dark" style="max-width: 200px;" title="<?= htmlspecialchars($log['ticket_title']) ?>">
                                    <?= htmlspecialchars($log['ticket_title']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="fas fa-bolt text-warning me-1"></i><?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="small text-secondary"><?= htmlspecialchars($log['note'] ?? '-') ?></td>
                            <td class="text-end">
                                <a href="<?= base_url('customer/detail_tiket.php?id=' . $log['ticket_id']) ?>" class="btn btn-xs btn-outline-primary py-1 px-2" title="Lihat Tiket">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
