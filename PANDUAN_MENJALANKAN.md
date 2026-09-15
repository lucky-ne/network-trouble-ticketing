# 📖 Panduan Menjalankan Aplikasi Web Trouble Ticketing B2B (XAMPP)

**Judul Proyek KKP:**  
> **"Rancang Bangun Sistem Informasi Network Trouble Ticketing Berbasis Web untuk Meningkatkan Efisiensi Penanganan Gangguan Jaringan pada PT. Visimedia Pratama Persada"**

Panduan ini dibuat agar Anda dapat menjalankan aplikasi sistem ticketing B2B dan SLA tracking di laptop dengan sangat mudah, lengkap dengan skenario demo presentasi sidang KKP.

---

## 🚀 Langkah 1: Akses Proyek di XAMPP

1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL** (pastikan keduanya berwarna hijau).
3. Karena folder proyek berada di `C:\xampp\htdocs\`, Anda cukup membuka browser (Google Chrome / Microsoft Edge) dan ketik alamat:
   ```
   http://localhost/
   ```
   *(atau `http://localhost/auth/login.php`)*

---

## 🗄️ Langkah 2: Import Database ke phpMyAdmin

1. Buka browser dan akses:
   ```
   http://localhost/phpmyadmin
   ```
2. Di phpMyAdmin:
   - Klik database **`net_ticketing_db`** (atau buat baru jika belum ada).
   - Klik tab **Import** di bagian atas.
   - Klik **Choose File** / **Pilih Berkas**, pilih file **`database.sql`** di folder `c:\xampp\htdocs\`.
   - Gulir ke bawah dan klik tombol **Import** (atau **Go**).
   - Seluruh tabel (`clients`, `users`, `tickets`, `ticket_logs`, `categories`, `priorities`, `settings`) beserta data seed B2B akan otomatis terpasang.

---

## 🔑 Langkah 3: Akun Demo Siap Pakai (B2B Multi-Role)

Pada halaman login, Anda dapat menggunakan **Akses Cepat 1-Klik** di sebelah kanan layar, atau login manual:

| Peran (Role) | Email Login | Password | Fungsi dalam Presentasi Sidang |
| :--- | :--- | :--- | :--- |
| **1. PIC Klien B2B** | `budi@perusahaan.com` | `password` | Mewakili **PT Sinarmas Land** untuk lapor gangguan sirkit `CID-VMI-0101` & konfirmasi tutup tiket. |
| **2. NOC Helpdesk** | `helpdesk@perusahaan.com` | `password` | Menerima tiket sirkit, validasi dampak, & menugaskan (*assign*) Field Engineer. |
| **3. Field Engineer** | `ahmad.teknisi@perusahaan.com` | `password` | Menerima tugas, klik *In Progress*, input Berita Acara Troubleshooting (*Root Cause & Action Taken*), lalu selesaikan (*Resolved*). |
| **4. Head of NOC / Manager** | `manager.it@perusahaan.com` | `password` | Memantau kepatuhan SLA per-PT Klien, MTTR, & cetak Laporan Bulanan SLA Resmi (PDF). |
| **5. Administrator Master** | `admin@perusahaan.com` | `password` | Kelola master Klien Korporat B2B, Circuit ID, user accounts, dan pengaturan SMTP/website. |

> 💡 **Tips Praktis:** Di bagian bawah setiap halaman, terdapat menu **"Ganti Role Cepat"** untuk berpindah peran pengguna secara instan saat simulasi di hadapan dosen penguji.

---

## 🎬 Skenario Alur Demo Pengujian untuk Sidang KKP

Gunakan urutan ini saat mendemonstrasikan sistem ke dosen penguji:

1. **Tahap 1: PIC Klien Korporat Melaporkan Gangguan Sirkit**
   - Login sebagai **PIC Klien B2B** (`budi@perusahaan.com` - PT Sinarmas Land).
   - Klik tombol **Buat Laporan Gangguan**.
   - Tunjukkan nomor sirkit otomatis terisi (`CID-VMI-0101 - Dedicated Internet Corporate 500 Mbps`).
   - Pilih Kategori Gangguan: *"Link Down / Loss of Signal (LOS)"* dan Prioritas: *"Critical (P1) - 2 Jam"*.
   - Isi judul: *"Link Fiber Optic Down Total - Indikator LOS Merah"*.
   - Klik **Terbitkan Tiket Gangguan**. Tunjukkan nomor tiket resmi (misal: `TKT-20260914-006`) dengan status `Open`.

2. **Tahap 2: NOC Helpdesk Memvalidasi & Menugaskan Field Engineer**
   - Beralih login ke **NOC Helpdesk** (`helpdesk@perusahaan.com`).
   - Buka menu **Antrian Tiket & Disposisi**.
   - Klik tombol **Disposisi** pada tiket baru tadi, pilih Field Engineer: *"Ahmad Fauzi"*, masukkan instruksi teknis, lalu simpan.
   - Status tiket berubah menjadi `Assigned`.

3. **Tahap 3: Field Engineer Melakukan Troubleshooting & Selesaikan Tiket**
   - Beralih login ke **Teknisi** (`ahmad.teknisi@perusahaan.com`).
   - Pada tiket terkait, klik **Mulai Kerjakan** (status berubah jadi `In Progress`).
   - Isi formulir Berita Acara:
     - **Penyebab (Root Cause):** *"Patch cord fiber optic LC-SC di OTB gedung klien patah saat pemeliharaan sipil"*.
     - **Tindakan (Action Taken):** *"Penggantian patch cord single mode baru dan pembersihan ferrule"*.
     - **Catatan Teknis:** *"Pengukuran optical power meter didapat -18.2 dBm (Normal). Uji ping 10.000 paket loss 0%."*
   - Klik **Selesaikan Tiket & Kunci Waktu SLA**.
   - Tunjukkan sistem otomatis mengunci waktu selesai dan menetapkan status **"Tepat Waktu (Within SLA)"**.

4. **Tahap 4: PIC Klien Mengonfirmasi Normalisasi Sirkit**
   - Beralih login ke **PIC Klien**.
   - Buka tiket terkait (berstatus `Resolved`), lalu klik **Konfirmasi & Tutup Tiket**.
   - Status tiket menjadi `Closed`. Klik tombol **Cetak Berita Acara (PDF)** untuk menunjukkan dokumen resmi bertanda tangan.

5. **Tahap 5: Head of NOC / Manager Memantau SLA & Cetak Laporan**
   - Beralih login ke **Manager** (`manager.it@perusahaan.com`).
   - Tunjukkan tabel **Kepatuhan SLA per Perusahaan Klien Korporat** dan grafik MTTR.
   - Buka menu **Laporan SLA Bulanan**, filter berdasarkan PT Klien, lalu klik **Cetak PDF** untuk menunjukkan laporan resmi ber-kop surat PT. Visimedia Pratama Persada.
