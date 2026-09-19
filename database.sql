-- Database: `net_ticketing_db`
-- Sistem Network Trouble Ticketing & SLA Tracking (B2B Corporate Services)
-- PT. Visimedia Pratama Persada

CREATE DATABASE IF NOT EXISTS `net_ticketing_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `net_ticketing_db`;

-- --------------------------------------------------------
-- Drop existing tables in correct order
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `ticket_logs`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `priorities`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `clients`;

-- --------------------------------------------------------
-- 1. Tabel Clients (Data Perusahaan Klien B2B & Sirkit Layanan)
-- --------------------------------------------------------
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(150) NOT NULL,
  `company_code` VARCHAR(50) NOT NULL UNIQUE,
  `circuit_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor Sirkit Layanan Jaringan B2B',
  `service_type` VARCHAR(100) NOT NULL COMMENT 'Dedicated Internet, IP VPN MPLS, Metro-E, SD-WAN',
  `bandwidth` VARCHAR(50) NOT NULL COMMENT 'Contoh: 500 Mbps, 1 Gbps',
  `pic_name` VARCHAR(100) NOT NULL COMMENT 'Nama PIC / IT Manager Klien',
  `pic_phone` VARCHAR(30) NOT NULL,
  `pic_email` VARCHAR(100) NOT NULL,
  `site_address` TEXT NOT NULL COMMENT 'Alamat Site/Gedung Kantor Klien',
  `sla_target_pct` DECIMAL(5,2) DEFAULT 99.50 COMMENT 'Target SLA Uptime Kontrak (%)',
  `status` ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Tabel Users (Pengguna Sistem: PIC Klien & Internal Visimedia)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nip` VARCHAR(30) NOT NULL UNIQUE COMMENT 'NIK Karyawan / ID PIC Klien',
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'karyawan', 'helpdesk', 'teknisi', 'manager') NOT NULL DEFAULT 'karyawan' COMMENT 'karyawan = PIC Klien Korporat',
  `client_id` INT NULL COMMENT 'ID Perusahaan jika role = karyawan / PIC Klien',
  `department` VARCHAR(100) NOT NULL COMMENT 'Jabatan / Divisi',
  `phone` VARCHAR(20) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Tabel Settings (Konfigurasi & Profil Website Dinamis)
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT,
  `setting_group` VARCHAR(50) DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Tabel Categories (Kategori Gangguan Jaringan B2B)
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Tabel Priorities (Tingkat Urgensi & Batas Waktu SLA B2B)
-- --------------------------------------------------------
CREATE TABLE `priorities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `sla_hours` INT NOT NULL COMMENT 'Batas waktu penyelesaian dalam hitungan jam',
  `badge_color` VARCHAR(20) DEFAULT 'primary',
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Tabel Tickets (Data Tiket Gangguan Layanan B2B)
-- --------------------------------------------------------
CREATE TABLE `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_code` VARCHAR(30) NOT NULL UNIQUE,
  `client_id` INT NOT NULL COMMENT 'Perusahaan Klien B2B',
  `user_id` INT NOT NULL COMMENT 'PIC Klien Pelapor',
  `category_id` INT NOT NULL,
  `priority_id` INT NOT NULL,
  `technician_id` INT NULL COMMENT 'Network / Field Engineer yang ditugaskan',
  `circuit_id` VARCHAR(50) NOT NULL COMMENT 'Nomor Sirkit yang bermasalah',
  `service_type` VARCHAR(100) NOT NULL COMMENT 'Jenis layanan sirkit',
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(255) NOT NULL COMMENT 'Site / Lokasi Kantor Klien',
  `attachment` VARCHAR(255) NULL COMMENT 'Screenshot log ping / traceroute / foto perangkat',
  `status` ENUM('open', 'assigned', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `assigned_at` DATETIME NULL,
  `started_at` DATETIME NULL COMMENT 'Saat teknisi mulai troubleshooting',
  `resolved_at` DATETIME NULL COMMENT 'Saat teknisi menyelesaikan perbaikan',
  `closed_at` DATETIME NULL,
  `sla_deadline` DATETIME NULL COMMENT 'created_at + sla_hours',
  `sla_status` ENUM('pending', 'within_sla', 'breached', 'exempted') DEFAULT 'pending',
  `sla_exemption_reason` VARCHAR(255) NULL COMMENT 'Alasan Pengecualian SLA: Force Majeure / Backbone FO Cut / Third-Party / Maintenance',
  `is_outage_massal` TINYINT(1) DEFAULT 0 COMMENT 'Flag Gangguan Massal (1=Ya, 0=Tidak)',
  `sla_paused_at` DATETIME NULL COMMENT 'Saat SLA Clock dijeda',
  `sla_paused_total_minutes` INT DEFAULT 0 COMMENT 'Total akumulasi jeda waktu SLA dalam menit',
  `resolution_time_minutes` INT NULL COMMENT 'Durasi pengerjaan dalam menit',
  `technician_notes` TEXT NULL COMMENT 'Berita Acara & Tindakan Perbaikan',
  `root_cause` VARCHAR(255) NULL COMMENT 'Penyebab Gangguan',
  `action_taken` TEXT NULL COMMENT 'Tindakan yang diambil',
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`priority_id`) REFERENCES `priorities`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Tabel Ticket Logs (Riwayat Perjalanan Tiket)
-- --------------------------------------------------------
CREATE TABLE `ticket_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `note` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- DATA SEED AWAL (SIAP PAKAI UNTUK PENGUJIAN, DEMO & SIDANG)
-- ========================================================

-- 1. Data Klien Korporat B2B
INSERT INTO `clients` (`id`, `company_name`, `company_code`, `circuit_id`, `service_type`, `bandwidth`, `pic_name`, `pic_phone`, `pic_email`, `site_address`, `sla_target_pct`, `status`) VALUES
(1, 'PT Sinarmas Land Tbk', 'CLI-SML-001', 'CID-VMI-0101', 'Dedicated Internet Corporate', '500 Mbps', 'Budi Santoso', '081234567890', 'budi.santoso@sinarmasland.com', 'Sinar Mas Land Plaza Tower 2 Lt. 15, Jl. MH Thamrin No. 51, Jakarta Pusat', 99.80, 'active'),
(2, 'PT Bank Central Asia Tbk', 'CLI-BCA-002', 'CID-VMI-0204', 'IP VPN MPLS Inter-Branch', '100 Mbps', 'Siti Rahmawati', '081234567891', 'siti.rahmawati@bca.co.id', 'Menara BCA Grand Indonesia Lt. 28, Jl. MH Thamrin No. 1, Jakarta Pusat', 99.90, 'active'),
(3, 'PT Indofood Sukses Makmur Tbk', 'CLI-IDF-003', 'CID-VMI-0315', 'Metro Ethernet Point-to-Point', '1 Gbps', 'Eko Prasetyo', '081234567896', 'eko.prasetyo@indofood.co.id', 'Indofood Tower Lt. 18, Sudirman Plaza, Jl. Jend. Sudirman Kav. 76-78, Jakarta Selatan', 99.50, 'active'),
(4, 'PT Astra International Tbk', 'CLI-AST-004', 'CID-VMI-0422', 'Managed SD-WAN Corporate', '300 Mbps', 'Rina Marlina', '081234567897', 'rina.marlina@astra.co.id', 'Menara Astra Lt. 35, Jl. Jend. Sudirman Kav. 5-6, Jakarta Pusat', 99.50, 'active');

-- 2. Data Pengguna (Password default: 'password' di-hash dengan password_hash BCRYPT)
-- Password 'password' hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `users` (`id`, `nip`, `name`, `email`, `password`, `role`, `client_id`, `department`, `phone`) VALUES
-- PIC Klien B2B (Role: karyawan)
(1, 'PIC-SML-01', 'Budi Santoso', 'budi@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'karyawan', 1, 'IT Infrastructure & Network Officer', '081234567890'),
(2, 'PIC-BCA-02', 'Siti Rahmawati', 'siti@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'karyawan', 2, 'IT Service Desk & Network Specialist', '081234567891'),
(3, 'PIC-IDF-03', 'Eko Prasetyo', 'eko@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'karyawan', 3, 'Network Engineer Representative', '081234567896'),
(4, 'PIC-AST-04', 'Rina Marlina', 'rina@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'karyawan', 4, 'IT Operations & Infrastructure', '081234567897'),

-- Tim Internal PT. Visimedia Pratama Persada
(5, 'HLP-VMI-01', 'Dimas Prasetyo', 'helpdesk@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'helpdesk', NULL, 'NOC & Customer Care B2B', '081234567892'),
(6, 'TEK-VMI-01', 'Ahmad Fauzi', 'ahmad.teknisi@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', NULL, 'B2B Field Engineering & Operations', '081234567893'),
(7, 'TEK-VMI-02', 'Rian Hidayat', 'rian.teknisi@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', NULL, 'B2B Field Engineering & Operations', '081234567894'),
(8, 'MGR-VMI-01', 'Ir. Hendra Wijaya, M.Kom', 'manager.it@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL, 'Service Delivery & NOC Management', '081234567895'),
(9, 'ADM-VMI-01', 'Administrator Sistem', 'admin@perusahaan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'IT Infrastructure & Web Operations', '081234567899');

-- 3. Data Pengaturan Website / Profil Sistem
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('app_name', 'VMP-NetTicket', 'general', 'Nama Aplikasi / Sistem'),
('company_name', 'PT. Visimedia Pratama Persada', 'general', 'Nama Perusahaan / Penyedia Layanan'),
('company_tagline', 'B2B Network Provider & Managed Service Solutions', 'general', 'Slogan / Tagline Perusahaan'),
('company_address', 'Cyber 2 Tower Lt. 12, Jl. HR Rasuna Said Blok X-5, Jakarta Selatan 12950', 'general', 'Alamat Kantor Pusat'),
('company_phone', '(021) 5299-1234 / Hotline NOC: 0811-9876-543', 'general', 'Nomor Telepon / Hotline NOC 24/7'),
('company_email', 'noc@visimedia.co.id', 'general', 'Email Dukungan NOC / Helpdesk'),
('system_version', 'v1.0 Enterprise', 'system', 'Versi Sistem'),
('footer_text', 'Sistem Informasi Network Trouble Ticketing B2B - PT. Visimedia Pratama Persada', 'general', 'Keterangan Footer'),
('sla_alert_threshold', '80', 'sla', 'Ambang Batas Peringatan SLA (%)'),
('smtp_enabled', '0', 'smtp', 'Aktifkan Notifikasi Email (1=Ya, 0=Tidak)'),
('smtp_host', 'smtp.gmail.com', 'smtp', 'SMTP Server Host'),
('smtp_port', '465', 'smtp', 'SMTP Port (465 SSL / 587 TLS)'),
('smtp_secure', 'ssl', 'smtp', 'Enkripsi SMTP (ssl / tls)'),
('smtp_user', '', 'smtp', 'Alamat Email Gmail Pengirim / Username SMTP'),
('smtp_pass', '', 'smtp', 'Google App Password (16-digit Sandi Aplikasi)'),
('smtp_from_name', 'NOC PT. Visimedia Pratama Persada', 'smtp', 'Nama Pengirim Email Notifikasi'),
('smtp_from_email', '', 'smtp', 'Email Pengirim'),
('outage_broadcast_active', '0', 'outage', 'Status Broadcast Gangguan Massal (1=Aktif, 0=Nonaktif)'),
('outage_broadcast_title', 'Pemberitahuan: Gangguan Massal Kabel Fiber Optic Backbone', 'outage', 'Judul Insiden Gangguan Massal'),
('outage_broadcast_message', 'Terjadi putus kabel Fiber Optic Backbone akibat pekerjaan galian utilitas kota pihak ketiga. Tim Fiber Optic Splicer sedang melakukan perbaikan darurat di lokasi.', 'outage', 'Deskripsi Pengumuman Gangguan Massal'),
('outage_broadcast_area', 'Sudirman, MH Thamrin, Kuningan & Sekitarnya', 'outage', 'Wilayah / Area Site Terdampak'),
('outage_broadcast_eta', '4 Jam (Estimasi Normal: 16:30 WIB)', 'outage', 'Perkiraan Waktu Normalisasi Layanan'),
('outage_broadcast_level', 'danger', 'outage', 'Tingkat Urgensi Alert (danger / warning / info)');

-- 4. Data Kategori Kendala Jaringan B2B
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Link Down / Loss of Signal (LOS)', 'Koneksi sirkit fiber optic terputus, lampu indikator LOS merah / SFP port down total.'),
(2, 'High Latency & Packet Loss Intermittent', 'Tingkat ping RTO / latency melonjak di atas 80ms mengganggu transaksi real-time klien.'),
(3, 'BGP Routing & IP Transit Peering Issue', 'Kendala routing table, prefix BGP tidak ter-advertise, atau gateway timeout.'),
(4, 'Bandwidth Throughput Degradation', 'Kecepatan transfer data turun di bawah komitmen CIR (Committed Information Rate) kontrak.'),
(5, 'Hardware CPE / Managed Router Failure', 'Perangkat router/switch sewa di site klien mati total, error OS, atau overheating.'),
(6, 'IPSec / MPLS VPN Tunnel Drop', 'Koneksi inter-branch VPN antar kantor cabang klien mengalami disconnect.');

-- 5. Data Tingkat Prioritas & Standar SLA B2B
INSERT INTO `priorities` (`id`, `name`, `sla_hours`, `badge_color`, `description`) VALUES
(1, 'Critical (P1)', 2, 'danger', 'Link Utama Down Total / Klien Terisolir (Target Penyelesaian SLA: 2 Jam)'),
(2, 'High (P2)', 4, 'warning', 'Degradasi Mayor / Packet Loss > 20% / Failover Aktif (Target SLA: 4 Jam)'),
(3, 'Medium (P3)', 8, 'info', 'Gangguan Minor pada Sub-Layanan / Routing Parsial (Target SLA: 8 Jam)'),
(4, 'Low (P4)', 24, 'secondary', 'Permintaan Teknis / Penambahan Routing / Pengecekan Rutin (Target SLA: 24 Jam)');

-- 6. Data Dummy Tiket Gangguan B2B
INSERT INTO `tickets` (
  `id`, `ticket_code`, `client_id`, `user_id`, `category_id`, `priority_id`, `technician_id`,
  `circuit_id`, `service_type`, `title`, `description`, `location`, `status`,
  `created_at`, `assigned_at`, `started_at`, `resolved_at`, `closed_at`,
  `sla_deadline`, `sla_status`, `resolution_time_minutes`, `technician_notes`, `root_cause`, `action_taken`
) VALUES
-- Tiket 1: Selesai TEPAT WAKTU (Within SLA)
(
  1, 'TKT-20260901-001', 1, 1, 1, 1, 6,
  'CID-VMI-0101', 'Dedicated Internet Corporate',
  'Link Utama Dedicated Internet 500 Mbps Down Total (LOS Merah)',
  'Sejak pukul 08.15 WIB link sirkit utama mengalami Loss of Signal (LOS). Seluruh operasional kantor Sinar Mas Land Plaza tidak dapat mengakses internet publik.',
  'Sinar Mas Land Plaza Tower 2 Lt. 15, Jl. MH Thamrin No. 51, Jakarta Pusat', 'closed',
  '2026-09-01 08:30:00', '2026-09-01 08:40:00', '2026-09-01 08:55:00', '2026-09-01 10:15:00', '2026-09-01 10:45:00',
  '2026-09-01 10:30:00', 'within_sla', 105,
  'Ditemukan patch cord fiber optic LC-SC di OTB gedung patah saat maintenance sipil. Dilakukan penggantian patch cord Single Mode dan tes redaman optik didapat -18.2 dBm (Normal). Sirkit kembali Up 100%.',
  'Patch cord fiber optic LC-SC bengkok/patah di OTB lantai 15',
  'Penggantian patch cord single mode baru, pembersihan konektor ferrule, dan uji daya optik OTDR'
),

-- Tiket 2: Selesai TERLAMBAT (Breached SLA)
(
  2, 'TKT-20260902-002', 2, 2, 2, 2, 6,
  'CID-VMI-0204', 'IP VPN MPLS Inter-Branch',
  'Intermittent Packet Loss 35% pada Link MPLS Menara BCA ke DC Cikarang',
  'Koneksi sinkronisasi data antar data center BCA mengalami packet loss tinggi dan jitter di atas 120ms.',
  'Menara BCA Grand Indonesia Lt. 28, Jl. MH Thamrin No. 1, Jakarta Pusat', 'resolved',
  '2026-09-02 09:00:00', '2026-09-02 09:20:00', '2026-09-02 10:00:00', '2026-09-02 14:30:00', NULL,
  '2026-09-02 13:00:00', 'breached', 330,
  'Modul SFP 10G di switch aggregation PE mengalami fluktuasi suhu tinggi dan degradasi laser. Dilakukan pergantian modul SFP 10G LR baru dan reload interface. Ping test 10.000 paket loss 0% (Clean).',
  'Degradasi optik pada modul transceiver SFP 10G di POP Visimedia',
  'Pergantian modul transceiver SFP 10G LR dan kalibrasi optical power link MPLS'
),

-- Tiket 3: Sedang Dikerjakan (In Progress)
(
  3, 'TKT-20260905-003', 3, 3, 3, 3, 7,
  'CID-VMI-0315', 'Metro Ethernet Point-to-Point',
  'Flapping BGP Session pada Link Metro-E Indofood Tower ke Pabrik Cibitung',
  'BGP neighbor 10.200.15.1 sering state Active/Idle secara berkala setiap 15 menit.',
  'Indofood Tower Lt. 18, Jl. Jend. Sudirman Kav. 76-78, Jakarta Selatan', 'in_progress',
  '2026-09-05 13:00:00', '2026-09-05 13:15:00', '2026-09-05 13:30:00', NULL, NULL,
  '2026-09-05 21:00:00', 'pending', NULL,
  'Network Engineer sedang melakukan trace route, penyesuaian BGP hold-time timer, dan investigasi interface CRC error di router edge.',
  'Flapping BGP neighbor akibat MTU mismatch dan buffer overload',
  'Sedang dilakukan re-tuning MTU size dan BGP graceful restart'
),

-- Tiket 4: Baru Ditugaskan (Assigned)
(
  4, 'TKT-20260907-004', 1, 1, 4, 3, 6,
  'CID-VMI-0101', 'Dedicated Internet Corporate',
  'Throughput Download Tidak Mencapai Bandwidth Berlangganan 500 Mbps',
  'Hasil speedtest hanya mentok di 120 Mbps pada jam kerja sibuk.',
  'Sinar Mas Land Plaza Tower 2 Lt. 15, Jl. MH Thamrin No. 51, Jakarta Pusat', 'assigned',
  '2026-09-07 09:30:00', '2026-09-07 10:00:00', NULL, NULL, NULL,
  '2026-09-07 17:30:00', 'pending', NULL,
  NULL, NULL, NULL
),

-- Tiket 5: Tiket Baru Belum Ditugaskan (Open)
(
  5, 'TKT-20260907-005', 4, 4, 1, 1, NULL,
  'CID-VMI-0422', 'Managed SD-WAN Corporate',
  'CPE SD-WAN Edge Gateway Menara Astra Padam Total (No Power)',
  'Perangkat SD-WAN Edge di ruang server lantai 35 tidak menyala sama sekali setelah maintenance kelistrikan gedung.',
  'Menara Astra Lt. 35, Jl. Jend. Sudirman Kav. 5-6, Jakarta Pusat', 'open',
  '2026-09-07 14:00:00', NULL, NULL, NULL, NULL,
  '2026-09-07 16:00:00', 'pending', NULL,
  NULL, NULL, NULL
);

-- 7. Data Riwayat Ticket Logs
INSERT INTO `ticket_logs` (`ticket_id`, `user_id`, `action`, `note`, `created_at`) VALUES
(1, 1, 'Tiket Dibuat', 'PIC Klien (PT Sinarmas Land) melaporkan link dedicated internet 500 Mbps mengalami LOS merah.', '2026-09-01 08:30:00'),
(1, 5, 'Tiket Ditugaskan', 'NOC Helpdesk memvalidasi sirkit CID-VMI-0101 dan menugaskan Field Engineer Ahmad Fauzi.', '2026-09-01 08:40:00'),
(1, 6, 'Mulai Pengerjaan', 'Field Engineer tiba di Site Sinar Mas Land Plaza Lt. 15 untuk cek OTB dan kabel patch cord.', '2026-09-01 08:55:00'),
(1, 6, 'Perbaikan Selesai', 'Patch cord fiber optic diganti baru, redaman -18.2 dBm. Tiket diselesaikan tepat waktu (Within SLA).', '2026-09-01 10:15:00'),
(1, 1, 'Tiket Ditutup', 'PIC PT Sinarmas mengonfirmasi link internet 500 Mbps sudah kembali normal & stabil.', '2026-09-01 10:45:00'),

(2, 2, 'Tiket Dibuat', 'PIC Klien (Bank BCA) melaporkan packet loss 35% pada link MPLS CID-VMI-0204.', '2026-09-02 09:00:00'),
(2, 5, 'Tiket Ditugaskan', 'NOC Helpdesk menugaskan Field Engineer Ahmad Fauzi untuk investigasi modul SFP di POP.', '2026-09-02 09:20:00'),
(2, 6, 'Mulai Pengerjaan', 'Teknisi melakukan pengukuran optical power meter dan pengecekan suhu switch PE.', '2026-09-02 10:00:00'),
(2, 6, 'Perbaikan Selesai', 'Modul transceiver SFP 10G LR diganti baru. Pengerjaan melebihi target waktu SLA (Breached).', '2026-09-02 14:30:00'),

(3, 3, 'Tiket Dibuat', 'PIC Klien (PT Indofood) melaporkan flapping BGP session pada link Metro-E CID-VMI-0315.', '2026-09-05 13:00:00'),
(3, 5, 'Tiket Ditugaskan', 'NOC Helpdesk menugaskan Network Engineer Rian Hidayat untuk remote troubleshooting BGP.', '2026-09-05 13:15:00'),
(3, 7, 'Mulai Pengerjaan', 'Network Engineer sedang analisa CRC error dan re-tuning MTU/hold-time di router edge.', '2026-09-05 13:30:00');
