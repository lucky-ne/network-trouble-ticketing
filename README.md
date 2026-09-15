# 🌐 Network Trouble Ticketing & SLA Tracking System (B2B Services)
### PT. Visimedia Pratama Persada &bull; Enterprise IT Architecture

Sistem Informasi Manajemen Gangguan Sirkit Jaringan B2B Korporat dan Pemantauan Service Level Agreement (SLA) secara real-time. Dibangun untuk mendukung operasional Network Operation Center (NOC), Field Engineer, Customer Korporat, dan Manajemen Eksekutif.

---

## 🚀 Fitur Utama & Multi-Role Architecture

Sistem ini mendukung 5 hak akses peran (*Role-Based Access Control*):

1. **Admin Master (`/admin`)**:
   - Master data perusahaan klien B2B, ID Sirkit (CID), kapasitas bandwidth, dan lokasi site.
   - Manajemen akun pengguna multi-role.
   - Konfigurasi parameter prioritas SLA dan kategori gangguan.
2. **Customer / PIC Klien Korporat (`/customer`)**:
   - Pelaporan gangguan sirkit jaringan 24/7.
   - Live tracking timeline penanganan gangguan (mulai dari open, assigned, in progress, hingga closed).
   - Konfirmasi penutupan tiket dan feedback kualitas layanan.
3. **NOC Helpdesk & Dispatcher (`/helpdesk`)**:
   - Queue monitor & live SLA deadline tracking.
   - Validasi tiket gangguan sirkit dan penentuan tingkat prioritas (P1 Critical, P2 High, P3, P4).
   - Disposisi penugasan ke Field / Network Engineer.
4. **Field / Network Engineer (`/teknisi`)**:
   - Penerimaan lembar kerja tugas penanganan gangguan.
   - Penginputan berita acara troubleshooting (Root Cause, Action Taken, dan Catatan Teknis).
   - Update status pengerjaan menuju status *Resolved*.
5. **Head of NOC / IT Manager (`/manager`)**:
   - Executive dashboard rasio kepatuhan SLA (% Uptime & On-Time Performance).
   - Analisis rata-rata durasi penyelesaian gangguan (MTTR).
   - Cetak laporan bulanan SLA resmi per klien dan rekap kinerja teknisi (Siap Cetak Format A4).

---

## 🛠️ Tech Stack
- **Backend**: Native PHP 8.2 (Secure PDO, Prepared Statements, Clean Architecture)
- **Database**: MariaDB 10.11 / MySQL 8.0
- **Frontend**: Bootstrap 5.3, FontAwesome 6, DataTables Responsive, Vanilla CSS
- **Containerization**: Docker & Docker Compose

---

## ⚡ Cara Menjalankan Project

### Opsi 1: Menjalankan di Docker (Ubuntu Server / VPS)
```bash
# 1. Clone repository
git clone https://github.com/USERNAME/REPO_NAME.git
cd REPO_NAME

# 2. Jalankan dengan 1 perintah Docker Compose
docker compose up -d --build
```
- **Aplikasi Web**: Buka `http://IP-SERVER/`
- **phpMyAdmin**: Buka `http://IP-SERVER:8081` *(User: `root`, Pass: `root_password`)*

---

### Opsi 2: Menjalankan di XAMPP Lokal (Windows)
1. Pindahkan folder project ke `C:\xampp\htdocs\`
2. Buka XAMPP Control Panel, nyalakan **Apache** dan **MySQL**.
3. Buka browser ke `http://localhost/phpmyadmin/`, buat database bernama `net_ticketing_db`, lalu import file `database.sql`.
4. Buka aplikasi di browser: `http://localhost/auth/login.php`

---

## 👥 Akun Login Demo (1-Klik Masuk)

| Peran | Email | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Admin Master** | `admin@perusahaan.com` | `password` | Akses penuh konfigurasi sistem |
| **Customer (B2B)** | `budi@perusahaan.com` | `password` | PIC PT Sinarmas Land Tbk (CID-VMI-0101) |
| **Customer (B2B)** | `siti@perusahaan.com` | `password` | PIC PT Bank Central Asia Tbk (CID-VMI-0205) |
| **Customer (B2B)** | `eko@perusahaan.com` | `password` | PIC PT Indofood Sukses Makmur (CID-VMI-0315) |
| **NOC Helpdesk** | `helpdesk@perusahaan.com` | `password` | Dispatcher & SLA Controller |
| **Field Engineer**| `fauzi@perusahaan.com` | `password` | Network / Field Engineer On-Site |
| **Head of NOC** | `manager.it@perusahaan.com` | `password` | Executive SLA Dashboard |

---

## 📄 Lisensi & Hak Cipta
Dikembangkan untuk implementasi Enterprise Network Management & Dokumentasi KKP B2B Managed Services &bull; PT. Visimedia Pratama Persada.
