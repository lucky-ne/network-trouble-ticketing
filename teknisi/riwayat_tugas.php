<?php
/**
 * Arsip Riwayat Tugas Selesai Field / Network Engineer - PT. Visimedia Pratama Persada
 * Fokus: Riwayat Penanganan Gangguan Sirkit yang Telah Selesai (Status: Resolved & Closed)
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['teknisi']);

$pdo = get_db();
$tek_id = $_SESSION['user']['id'];

// Ambil Statistik Tugas Selesai Field Engineer
$stmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_done,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS total_closed,
    SUM(CASE WHEN sla_status = 'within_sla' THEN 1 ELSE 0 END) AS total_ontime,
    SUM(CASE WHEN sla_status = 'exempted' THEN 1 ELSE 0 END) AS total_exempted,
    SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS total_breached,
    AVG(CASE WHEN resolution_time_minutes > 0 THEN resolution_time_minutes ELSE NULL END) AS avg_mttr_minutes
FROM tickets 
WHERE technician_id = ? AND status IN ('resolved', 'closed')");
$stmt->execute([$tek_id]);
$stats = $stmt->fetch();

$total_done = (int)($stats['total_done'] ?? 0);
$total_ontime = (int)($stats['total_ontime'] ?? 0);
$total_exempted = (int)($stats['total_exempted'] ?? 0);
$total_breached = (int)($stats['total_breached'] ?? 0);

// Hitung Kepatuhan SLA Teknisi (Fair SLA Rule: On-Time + Exempted)
$sla_compliance_pct = ($total_done > 0) ? round((($total_ontime + $total_exempted) / $total_done) * 100, 1) : 100;
$avg_mttr_str = ($stats['avg_mttr_minutes'] > 0) ? format_duration_minutes(round($stats['avg_mttr_minutes'])) : '-';

// Ambil Semua Tiket Selesai Milik Field Engineer
$stmt = $pdo->prepare("SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    u_pelapor.name AS reporter_name,
    u_pelapor.phone AS reporter_phone
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
WHERE t.technician_id = ? AND t.status IN ('resolved', 'closed')
ORDER BY t.resolved_at DESC, t.id DESC");
$stmt->execute([$tek_id]);
$finished_tickets = $stmt->fetchAll();

$page_title = 'Riwayat Tugas Selesai & Berita Acara';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-check-double text-success me-2"></i>Arsip Riwayat Tugas Selesai &amp; Berita Acara
        </h4>
        <p class="text-secondary small mb-0">Daftar seluruh tiket gangguan sirkit jaringan yang telah berhasil Anda perbaiki dan selesaikan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('teknisi/dashboard.php') ?>" class="btn btn-outline-primary btn-sm fw-semibold">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Tugas Aktif
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Performa & Tugas Selesai -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Total Tiket Selesai</div>
            <div class="stat-value text-success"><?= $total_done ?></div>
            <div class="stat-desc"><?= (int)$stats['total_resolved'] ?> Resolved &bull; <?= (int)$stats['total_closed'] ?> Closed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Tepat Waktu (Within SLA)</div>
            <div class="stat-value text-primary"><?= $total_ontime ?></div>
            <div class="stat-desc">Diselesaikan sesuai batas SLA</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-info" style="border-left-color: #7c3aed !important;">
            <div class="stat-label">SLA Exempted (Force Majeure)</div>
            <div class="stat-value" style="color: #7c3aed;"><?= $total_exempted ?></div>
            <div class="stat-desc">Dikecualikan (Backbone Cut/Massal)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b <?= ($sla_compliance_pct >= 90) ? 'border-left-success' : 'border-left-warning' ?>">
            <div class="stat-label">Kepatuhan SLA Teknisi</div>
            <div class="stat-value <?= ($sla_compliance_pct >= 90) ? 'text-success' : 'text-warning' ?>"><?= $sla_compliance_pct ?>%</div>
            <div class="stat-desc">Rata-rata MTTR: <strong><?= $avg_mttr_str ?></strong></div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat Tugas Selesai -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fas fa-archive text-success me-2"></i>Daftar Riwayat Berita Acara Selesai
            </h6>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                Total: <?= count($finished_tickets) ?> Tiket Selesai
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($finished_tickets)): ?>
            <div class="text-center py-5">
                <div class="rounded-circle bg-light d-inline-flex p-3 mb-3 text-muted">
                    <i class="fas fa-inbox fa-2x"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Belum Ada Riwayat Tugas Selesai</h6>
                <p class="text-muted small mb-0">Tiket yang Anda selesaikan melalui formulir Berita Acara akan otomatis diarsipkan di halaman ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-b2b table-hover align-middle datatable w-100 mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">No. Tiket</th>
                            <th style="width: 160px;">Perusahaan Klien</th>
                            <th style="width: 150px;">Sirkit (CID) & Layanan</th>
                            <th>Penyebab & Solusi Teknis</th>
                            <th style="width: 140px;">Waktu Selesai & MTTR</th>
                            <th style="width: 100px;">Status Tiket</th>
                            <th style="width: 140px;">Kepatuhan SLA</th>
                            <th style="width: 110px;" class="text-center">Aksi Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($finished_tickets as $t): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="fw-bold text-primary font-monospace text-nowrap text-decoration-none">
                                        <?= htmlspecialchars($t['ticket_code']) ?>
                                    </a>
                                    <?php if (!empty($t['is_outage_massal'])): ?>
                                        <span class="badge bg-danger ms-1" title="Terkait Gangguan Massal"><i class="fas fa-broadcast-tower"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($t['company_name']) ?>"><?= htmlspecialchars($t['company_name']) ?></div>
                                    <small class="text-secondary text-truncate d-block" style="max-width: 150px;" title="<?= htmlspecialchars($t['location'] ?? '-') ?>">
                                        <i class="fas fa-map-marker-alt text-secondary me-1"></i><?= htmlspecialchars($t['location'] ?? '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                    <div class="small text-secondary mt-1 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($t['service_type']) ?>"><?= htmlspecialchars($t['service_type']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($t['root_cause'])): ?>
                                        <div class="small fw-semibold text-dark text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($t['root_cause']) ?>">
                                            <i class="fas fa-bug text-danger me-1"></i> <?= htmlspecialchars($t['root_cause']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($t['action_taken'])): ?>
                                        <div class="small text-secondary text-truncate mt-1" style="max-width: 240px;" title="<?= htmlspecialchars($t['action_taken']) ?>">
                                            <i class="fas fa-wrench text-success me-1"></i> <?= htmlspecialchars($t['action_taken']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-dark fw-semibold text-nowrap"><?= !empty($t['resolved_at']) ? date('d M Y, H:i', strtotime($t['resolved_at'])) : '-' ?></div>
                                    <small class="text-secondary font-monospace text-nowrap">MTTR: <?= ($t['resolution_time_minutes'] > 0) ? format_duration_minutes($t['resolution_time_minutes']) : '-' ?></small>
                                </td>
                                <td>
                                    <?= get_status_badge($t['status']) ?>
                                </td>
                                <td>
                                    <?= get_sla_badge($t['sla_status'], $t['sla_deadline'], $t['resolved_at'], $t['sla_exemption_reason'] ?? null) ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 justify-content-center">
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Lihat Berita Acara & Riwayat">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= base_url('helpdesk/cetak_tiket.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-sm btn-outline-dark px-2 py-1" title="Cetak Berita Acara (BA) A4">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
