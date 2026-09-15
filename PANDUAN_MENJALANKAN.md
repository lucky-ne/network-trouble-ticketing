# 📖 PANDUAN LENGKAP MENJALANKAN SISTEM TROUBLE TICKETING B2B
### PT. Visimedia Pratama Persada &bull; Network Operations Center (NOC)

**Judul Proyek KKP:**  
> **"Rancang Bangun Sistem Informasi Network Trouble Ticketing Berbasis Web untuk Meningkatkan Efisiensi Penanganan Gangguan Jaringan pada PT. Visimedia Pratama Persada"**

Panduan ini disusun untuk mempermudah pengujian aplikasi di laptop lokal (XAMPP), kontainer server (Docker Ubuntu), maupun mengakses server live produksi yang siap didemonstrasikan di hadapan dosen penguji.

---

## 🌐 3 OPSI CARA MENGAKSES / MENJALANKAN SISTEM

### Opsi A: Akses Langsung Server Live Produksi (Online)
Jika server Ubuntu dan domain telah aktif, Anda dapat langsung membuka browser:
* **URL Sistem Utama**: [`http://vmp-net.zapto.org`](http://vmp-net.zapto.org) *(atau `http://103.79.155.220`)*
* **URL phpMyAdmin Database**: `http://103.79.155.220:8081` *(User: `root` / Pass: `root_password`)*

---

### Opsi B: Menjalankan di XAMPP Windows (Lokal Laptop)
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL** (pastikan keduanya berwarna hijau).
3. Buka browser dan akses phpMyAdmin:
   ```
   http://localhost/phpmyadmin/
   ```
4. Buat database baru bernama **`net_ticketing_db`**, lalu klik tab **Import** dan pilih file **`database.sql`** yang ada di folder `C:\xampp\htdocs\`.
5. Buka aplikasi melalui alamat:
   ```
   http://localhost/
   ```
   *(atau `http://localhost/auth/login.php`)*

---

### Opsi C: Menjalankan di Docker (Server Ubuntu / VPS)
1. Masuk ke folder project di server:
   ```bash
   cd /var/www/net-ticketing
   ```
2. Jalankan seluruh container dengan 1 perintah:
   ```bash
   sudo docker compose up -d --build
   ```
3. Docker akan otomatis mem-build aplikasi PHP 8.2 Apache dan meng-import `database.sql` ke MariaDB tanpa perlu import manual!

---

## 🔑 DAFTAR AKUN DEMO RESMI (MULTI-ROLE B2B)

Sistem telah dilengkapi fitur **"Akses Cepat 1-Klik"** di halaman login. Anda juga dapat login manual menggunakan kredensial berikut:

| Peran (Role) | Email Login | Password | Perusahaan / Divisi | Circuit ID / Fungsi Demo |
| :--- | :--- | :--- | :--- | :--- |
| **1. Admin Master** | `admin@perusahaan.com` | `password` | PT. Visimedia Pratama Persada | Akses penuh master data klien, user, SLA, & kategori. |
| **2. PIC Klien B2B (1)** | `budi@perusahaan.com` | `password` | **PT Sinarmas Land Tbk** | Lapor gangguan sirkit `CID-VMI-0101` (Dedicated Internet 500M). |
| **3. PIC Klien B2B (2)** | `siti@perusahaan.com` | `password` | **PT Bank Central Asia Tbk** | Lapor gangguan sirkit `CID-VMI-0204` (IP VPN MPLS 100M). |
| **4. PIC Klien B2B (3)** | `eko@perusahaan.com` | `password` | **PT Indofood Sukses Makmur** | Lapor gangguan sirkit `CID-VMI-0315` (Metro-E 1 Gbps). |
| **5. NOC Helpdesk** | `helpdesk@perusahaan.com` | `password` | NOC & Customer Care VMP | Validasi tiket, atur SLA, & disposisi ke Field Engineer. |
| **6. Field Engineer** | `ahmad.teknisi@perusahaan.com` | `password` | B2B Field Engineering VMP | Mulai pengerjaan, input Berita Acara & selesaikan perbaikan. |
| **7. Head of NOC (Mgr)**| `manager.it@perusahaan.com` | `password` | Executive NOC Management | Pantau SLA Compliance %, MTTR, & cetak Laporan Resmi. |

> 💡 **Fitur Floating Role Switcher:** Di pojok kanan bawah setiap halaman internal, tersedia menu bar cepat untuk berpindah antar-role secara instan saat simulasi di hadapan dosen penguji.

---

## 🎬 SKENARIO SIMULASI DEMO LENGKAP UNTUK SIDANG KKP

Ikuti 5 tahapan berurutan ini saat mendemonstrasikan sistem di hadapan dosen penguji:

### 📍 Tahap 1: PIC Klien Korporat Melaporkan Gangguan Sirkit
1. Buka halaman login, klik tombol demo **"PIC Klien B2B (Sinarmas Land)"** (`budi@perusahaan.com`).
2. Di dashboard Klien, perhatikan informasi sirkit aktif: `CID-VMI-0101 - Dedicated Internet Corporate 500 Mbps`.
3. Klik tombol **"Buat Laporan Gangguan"** (`/customer/create.php`).
4. Isi formulir:
   - **Kategori Gangguan**: *Link Down / Loss of Signal (LOS)*
   - **Prioritas Dampak**: *Critical (P1) - 2 Jam*
   - **Judul Gangguan**: *Link FO Down Total - Indikator LOS ONT Merah di Gedung Sinar Mas Land Plaza Lt. 15*
   - **Deskripsi**: *Seluruh koneksi internet kantor pusat tidak dapat diakses sejak pukul 08:30 WIB. Log ping router gateway RTO 100%.*
5. Klik **"Terbitkan Tiket Gangguan"**.
6. Sistem akan menerbitkan nomor tiket resmi (contoh: `TKT-20260916-001`) dengan status `Open` dan menghitung batas waktu penyelesaian SLA otomatis.

---

### 📍 Tahap 2: NOC Helpdesk Memvalidasi & Menugaskan Field Engineer
1. Beralih login sebagai **NOC Helpdesk** (`helpdesk@perusahaan.com`).
2. Masuk ke menu **"Antrian Tiket & Disposisi"** (`/helpdesk/index.php`).
3. Pada tiket `TKT-20260916-001` yang berstatus `Open`, tunjukkan tombol aksi **"Tugaskan"** dan **"Atur SLA"**.
4. Klik tombol **"Tugaskan"** (`/helpdesk/assign.php`):
   - Pilih Teknisi: **Ahmad Fauzi (Field / Network Engineer 1)**
   - Catatan Disposisi: *Segera koordinasikan dengan security gedung klien untuk pengecekan OTB lantai 15 dan ukur optical power meter.*
5. Klik **"Simpan & Tugaskan Teknisi"**. Status tiket kini berubah menjadi `Assigned`.

---

### 📍 Tahap 3: Field Engineer Mengerjakan & Menginput Berita Acara
1. Beralih login sebagai **Field Engineer** (`ahmad.teknisi@perusahaan.com`).
2. Di dashboard teknisi, buka tiket tugas baru tersebut.
3. Klik tombol **"Mulai Kerjakan"** (`/teknisi/process.php`). Status tiket berubah menjadi `In Progress` dan waktu mulai (`started_at`) tercatat di log audit.
4. Lakukan penginputan Berita Acara Troubleshooting:
   - **Penyebab Gangguan (Root Cause)**: *Patch cord fiber optic LC-SC di dalam Optical Termination Box (OTB) patah akibat tarikan kabel saat pemeliharaan sipil gedung.*
   - **Tindakan Perbaikan (Action Taken)**: *Penggantian patch cord single mode 3 meter baru, pembersihan konektor ferrule menggunakan optical cleaner cassette, dan reposisi kabel ke dalam cable tray.*
   - **Catatan Hasil Pengujian**: *Pengukuran optical power meter didapatkan Rx -18.4 dBm (Normal). Uji ping gateway 10.000 paket loss 0%, throughput speedtest mencapai 502 Mbps simetris.*
5. Klik **"Selesaikan Tiket & Kunci Waktu SLA"**.
6. Sistem otomatis mengunci timestamp `resolved_at`, menghitung durasi pengerjaan, dan menetapkan status kepatuhan **"Tepat Waktu (Within SLA)"**.

---

### 📍 Tahap 4: PIC Klien Konfirmasi Normalisasi & Cetak Berita Acara
1. Beralih login kembali sebagai **PIC Klien** (`budi@perusahaan.com`).
2. Buka menu **"Status Tiket"** dan pilih tiket terkait yang berstatus `Resolved`.
3. Tunjukkan kepada penguji bahwa hasil perbaikan dan tindakan teknisi sudah tampil lengkap.
4. Klik tombol **"Konfirmasi & Tutup Tiket"**. Status tiket resmi berubah menjadi `Closed`.
5. Klik tombol **"Cetak Berita Acara (PDF)"** (`/helpdesk/cetak_tiket.php`).
6. Tunjukkan dokumen **Berita Acara Penanganan Gangguan & Work Order** ber-kop surat resmi PT. Visimedia Pratama Persada format A4 Portrait lengkap dengan **Digital System Validation Hash** tanpa blank tanda tangan manual fisik.

---

### 📍 Tahap 5: Head of NOC / IT Manager Memantau SLA & Cetak Rekap Bulanan
1. Beralih login sebagai **Head of NOC / IT Manager** (`manager.it@perusahaan.com`).
2. Di dashboard eksekutif (`/manager/index.php`), tunjukkan:
   - Persentase **SLA Compliance Rate %** tim operasional.
   - Rata-rata durasi penanganan (**MTTR**).
   - Tabel performa kepatuhan SLA per mitra klien korporat.
3. Buka menu **"Laporan Bulanan SLA"** (`/manager/laporan.php`), filter berdasarkan bulan berjalan dan pilih klien *PT Sinarmas Land Tbk*.
4. Klik **"Cetak Laporan PDF"** (`/manager/cetak_laporan.php`). Tunjukkan hasil cetak rekapitulasi SLA format A4 Landscape siap audit manajemen.
5. Buka menu **"Kinerja Teknisi"** (`/manager/kinerja_teknisi.php`) dan tunjukkan cetak matriks produktivitas teknisi Ahmad Fauzi format A4 Portrait.

---

## 🛠️ TIPS PENTING SAAT PRESENTASI SIDANG KKP

1. **Gunakan Fitur Print to PDF Browser**:
   - Saat menekan tombol Cetak Berita Acara atau Cetak Laporan, browser akan membuka dialog print.
   - Pilih *Destination*: **Save as PDF** / **Microsoft Print to PDF**.
   - Pastikan opsi *Background graphics* dicentang agar kop surat dan warna badge tampil sempurna.
2. **Koneksi Internet untuk Server Online**:
   - Jika mendemokan secara online via domain `http://vmp-net.zapto.org`, pastikan laptop Anda terhubung ke internet (Hotspot HP stabil).
   - Selalu siapkan XAMPP lokal di laptop sebagai *backup offline* jika koneksi internet di ruang sidang terkendala.
