# 🌐 Network Trouble Ticketing & SLA Tracking System (B2B Services)
### PT. Visimedia Pratama Persada &bull; Network Operations Center (NOC)

[![PHP Version](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://php.net/)
[![Database](https://img.shields.io/badge/Database-MariaDB%2010.11%20%7C%20MySQL-orange.svg)](https://mariadb.org/)
[![Container](https://img.shields.io/badge/Container-Docker%20%7C%20Docker%20Compose-2496ED.svg)](https://docker.com/)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GitHub%20Actions-2088FF.svg)](https://github.com/features/actions)
[![License](https://img.shields.io/badge/License-Academic%20%2F%20Enterprise-green.svg)]()

Sistem Informasi Manajemen Gangguan Sirkit Jaringan B2B Korporat dan Pemantauan *Service Level Agreement* (SLA) secara *real-time*. Dibangun untuk mengoptimalkan operasional *Network Operations Center* (NOC), Field Engineer, Customer Korporat B2B, dan Manajemen Eksekutif PT. Visimedia Pratama Persada.

---

## 📑 DOKUMENTASI LENGKAP & MATERI KKP

Seluruh materi pendukung akademik, presentasi sidang, dan panduan teknis tersedia lengkap dalam repository ini:
* 📘 [**Materi Lengkap Laporan KKP (Bab 1–5 & Bank Tanya-Jawab Sidang)**](file:///MATERI_LAPORAN_KKP.md)
* 📖 [**Panduan Menjalankan Aplikasi & 5 Skenario Demo Sidang**](file:///PANDUAN_MENJALANKAN.md)
* 🚀 [**Panduan Deploy Docker, CI/CD GitHub Actions & HTTPS SSL**](file:///DEPLOY_DOCKER_UBUNTU.md)

---

## 🚀 Fitur Utama & Multi-Role Architecture

Sistem ini mendukung 5 hak akses peran (*Role-Based Access Control* / RBAC):

1. **PIC Klien Korporat / Customer B2B (`/customer/`)**:
   - Pelaporan gangguan sirkit jaringan 24/7 dengan identifikasi *Circuit ID* otomatis.
   - *Live tracking* tahapan troubleshooting (*Open, Assigned, In Progress, Resolved, Closed*).
   - Konfirmasi penutupan tiket dan pencetakan Berita Acara resmi.
   - Dukungan simulasi multi-perusahaan klien: **PT Sinarmas Land Tbk**, **PT Bank Central Asia Tbk**, dan **PT Indofood Sukses Makmur Tbk**.
2. **NOC Helpdesk & Dispatcher (`/helpdesk/`)**:
   - Antrian tiket masuk (*Live Queue Monitor*) & kalkulasi sisa waktu SLA.
   - Validasi tiket gangguan dan penentuan tingkat prioritas (*P1 Critical, P2 High, P3 Medium, P4 Low*).
   - Disposisi penugasan ke Field / Network Engineer.
   - Tombol aksi cerdas (*Tugaskan, Atur SLA, serta Detail & Cetak untuk tiket Closed*).
3. **Field / Network Engineer (`/teknisi/`)**:
   - Penerimaan lembar kerja tugas penanganan gangguan.
   - Status pengerjaan *In Progress* dengan pencatatan waktu otomatis.
   - Penginputan Berita Acara Troubleshooting (*Root Cause, Action Taken, dan Catatan Hasil Pengujian*).
   - Penyelesaian tiket (*Resolved*) dengan penguncian waktu dan kalkulasi kepatuhan SLA.
4. **Head of NOC / IT Manager (`/manager/`)**:
   - Dashboard eksekutif rasio kepatuhan SLA (*SLA Compliance Rate %*).
   - Analisis rata-rata kecepatan penyelesaian gangguan (*Mean Time to Resolve / MTTR*).
   - Cetak Laporan Rekapitulasi Bulanan SLA (A4 Landscape) dan Laporan Kinerja Teknisi (A4 Portrait).
5. **Admin Master (`/admin/`)**:
   - Manajemen master data perusahaan klien B2B, nomor sirkit (*Circuit ID*), kapasitas bandwidth, dan alamat *site*.
   - Manajemen akun pengguna multi-role.
   - Konfigurasi parameter SLA, kategori gangguan, dan profil perusahaan.

---

## 🖨️ Standarisasi Dokumen Cetak Digital Paperless (A4 Formal)

Sistem telah dilengkapi modul cetak dokumen resmi ber-kop PT. Visimedia Pratama Persada dengan standar **Paperless Corporate Document**:
* **Work Order & Berita Acara Perbaikan** (`helpdesk/cetak_tiket.php` - A4 Portrait)
* **Laporan Kinerja & Produktivitas Teknisi** (`manager/cetak_kinerja_teknisi.php` - A4 Portrait)
* **Laporan Rekapitulasi Kepatuhan SLA Bulanan** (`manager/cetak_laporan.php` - A4 Landscape)
* Dilengkapi **Digital System Validation Panel & System Audit Hash** berbasis timestamp SHA-256 untuk menjamin integritas data tanpa blank tanda tangan fisik manual.

---

## 🛠️ Tech Stack & Arsitektur

- **Backend**: PHP 8.2 Native (Clean Architecture, Secure PDO Prepared Statements, Dual-mode Environment Detection).
- **Database**: MariaDB 10.11 / MySQL 8.0 (Relational InnoDB with Foreign Keys & Cascading).
- **Frontend**: Bootstrap 5.3, FontAwesome 6, DataTables Responsive, Custom Modern CSS.
- **Containerization**: Docker & Docker Compose (Multi-Container: App Apache, MariaDB, phpMyAdmin).
- **DevOps & CI/CD**: GitHub Actions Automated SSH Deployment, Nginx Reverse Proxy, Let's Encrypt SSL.

---

## ⚡ Cara Menjalankan Project

### Opsi 1: Menjalankan di XAMPP Windows (Lokal Laptop)
1. Letakkan folder project di `C:\xampp\htdocs\`
2. Buka XAMPP Control Panel, nyalakan modul **Apache** dan **MySQL**.
3. Buka browser ke `http://localhost/phpmyadmin/`, buat database bernama `net_ticketing_db`, lalu import file `database.sql`.
4. Buka aplikasi di browser: `http://localhost/` *(atau `http://localhost/auth/login.php`)*

---

### Opsi 2: Menjalankan di Docker (Server Ubuntu / VPS)
```bash
# 1. Clone repository
git clone https://github.com/lucky-ne/network-trouble-ticketing.git
cd network-trouble-ticketing

# 2. Jalankan seluruh service dengan 1 perintah
docker compose up -d --build
```
- **Aplikasi Web**: `http://IP-SERVER/` atau `http://vmp-net.zapto.org`
- **phpMyAdmin**: `http://IP-SERVER:8081` *(User: `root`, Pass: `root_password`)*

---

## 👥 Akun Login Demo (1-Klik Masuk)

| Peran (Role) | Email | Password | Klien Korporat / Penugasan |
| :--- | :--- | :--- | :--- |
| **Admin Master** | `admin@perusahaan.com` | `password` | Administrator Sistem Pusat |
| **Customer B2B (1)** | `budi@perusahaan.com` | `password` | **PT Sinarmas Land Tbk** (`CID-VMI-0101`) |
| **Customer B2B (2)** | `siti@perusahaan.com` | `password` | **PT Bank Central Asia Tbk** (`CID-VMI-0204`) |
| **Customer B2B (3)** | `eko@perusahaan.com` | `password` | **PT Indofood Sukses Makmur** (`CID-VMI-0315`) |
| **NOC Helpdesk** | `helpdesk@perusahaan.com` | `password` | NOC Dispatcher & SLA Controller |
| **Field Engineer (1)**| `ahmad.teknisi@perusahaan.com` | `password` | Field / Network Engineer On-Site |
| **Field Engineer (2)**| `rian.teknisi@perusahaan.com` | `password` | Field / Network Engineer On-Site |
| **Head of NOC** | `manager.it@perusahaan.com` | `password` | IT Executive SLA Dashboard |

---

## 📄 Lisensi & Hak Cipta
Dikembangkan untuk implementasi *Enterprise Network Trouble Ticketing & SLA Tracking* &bull; Laporan Kuliah Kerja Praktek (KKP) &bull; **PT. Visimedia Pratama Persada**.
