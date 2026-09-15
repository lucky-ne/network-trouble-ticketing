# 📑 Naskah Lengkap & Materi Laporan KKP (Kuliah Kerja Praktek)

**Judul Penelitian / Laporan KKP:**  
> **"Rancang Bangun Sistem Informasi Network Trouble Ticketing Berbasis Web untuk Meningkatkan Efisiensi Penanganan Gangguan Jaringan pada PT. Visimedia Pratama Persada"**

Dokumen ini disusun sebagai panduan menyeluruh penyusunan naskah laporan KKP (Bab 1 sampai Bab 4), pemodelan UML, metodologi Waterfall, basis data relasional B2B, serta bank tanya-jawab komprehensif untuk menghadapi sidang penguji.

---

## BAB 1: PENDAHULUAN

### 1.1 Latar Belakang
**PT. Visimedia Pratama Persada** merupakan perusahaan yang bergerak di bidang penyedia infrastruktur jaringan telekomunikasi dan *Managed Network Services* berbasis *Business-to-Business* (B2B). Layanan yang disediakan mencakup *Dedicated Internet Corporate*, *Metro Ethernet*, *IP VPN MPLS Inter-Branch*, dan *Managed SD-WAN Corporate* kepada berbagai perusahaan mitra korporat (*corporate clients*) di Indonesia.

Ketersediaan (*availability*) dan stabilitas sirkit jaringan berkecepatan tinggi merupakan komitmen vital yang tertuang dalam perjanjian tingkat layanan (*Service Level Agreement* / SLA) dengan target *uptime* rata-rata $\ge 99.5\%$. Namun, pada operasional harian di divisi *Network Operations Center* (NOC) PT. Visimedia Pratama Persada, proses penanganan keluhan gangguan sirkit dari klien korporat masih menghadapi kendala krusial:
1. **Saluran Pelaporan Masih Bersifat Konvensional & Terpencar**: Klien korporat sering melaporkan gangguan sirkit melalui pesan instan WhatsApp personal, panggilan telepon, atau email tidak terstruktur. Hal ini mengakibatkan komplain terlewat (*missed communication*), tidak memiliki nomor tiket (*tracking number*) resmi, dan menyulitkan identifikasi nomor sirkit (*Circuit ID*).
2. **Ketiadaan Transparansi Status Penanganan Real-Time**: PIC Klien tidak dapat memantau secara langsung tahapan troubleshooting teknisi PT. Visimedia Pratama Persada (apakah tiket sudah divalidasi NOC, Field Engineer sudah ditugaskan, atau proses perbaikan sedang berjalan di lokasi *site*).
3. **Belum Adanya Sistem Pengukuran Batas Waktu SLA Otomatis**: Pengukuran durasi perbaikan (*Mean Time to Resolve* / MTTR) dan status kepatuhan SLA (*On-Time vs Breached*) masih dihitung manual menggunakan spreadsheet, sehingga rentan kesalahan kalkulasi dan memicu sengketa evaluasi kontrak bulanan.
4. **Ketiadaan Notifikasi Email Terintegrasi**: Sebelumnya tidak tersedia pengingat otomatis ke email PIC Klien dan Field Engineer saat terjadi perubahan status atau penerbitan Berita Acara Perbaikan.

Oleh karena itu, dibangun **Sistem Informasi Network Trouble Ticketing Berbasis Web** pada PT. Visimedia Pratama Persada yang mengintegrasikan pencatatan *Circuit ID*, pemantauan batas waktu SLA otomatis (*real-time countdown*), dan notifikasi transaksional email guna meningkatkan efisiensi dan transparansi penanganan gangguan jaringan B2B.

---

### 1.2 Rumusan Masalah
1. Bagaimana merancang dan membangun sistem informasi *Network Trouble Ticketing* berbasis web untuk mengelola dan mendokumentasikan keluhan gangguan sirkit jaringan klien korporat secara terpusat pada **PT. Visimedia Pratama Persada**?
2. Bagaimana menerapkan sistem pemantauan batas toleransi *Service Level Agreement* (SLA) otomatis berbasis prioritas dampak (*Critical, High, Medium, Low*) untuk mengukur kepatuhan waktu penanganan teknisi (*On-Time vs Breached*)?
3. Bagaimana mengintegrasikan sistem notifikasi email otomatis (SMTP Gmail & PHPMailer) ke PIC Klien, NOC Dispatcher, dan Field Engineer pada setiap tahapan penanganan tiket?
4. Bagaimana menyajikan dashboard monitoring dan rekapitulasi laporan bulanan performa SLA B2B bagi pimpinan/manager NOC?

---

### 1.3 Batasan Masalah
1. Sistem berfokus pada manajemen trouble ticketing layanan jaringan B2B PT. Visimedia Pratama Persada (*Dedicated Internet, IP VPN MPLS, Metro Ethernet, SD-WAN*).
2. Sistem memiliki 5 hak akses pengguna: **PIC Klien Korporat (Pelapor B2B)**, **NOC Helpdesk (Dispatcher)**, **Field / Network Engineer (Teknisi Lapangan)**, **Head of NOC / Manager (Pimpinan)**, dan **Administrator Master**.
3. Notifikasi email dikirimkan secara otomatis menggunakan pustaka PHPMailer melalui protokol SMTP Gmail terenkripsi (SSL/TLS).
4. Pengujian fungsionalitas sistem dilakukan menggunakan metode *Black Box Testing*.

---

### 1.4 Tujuan Penelitian
1. Membangun sistem informasi *Network Trouble Ticketing* berbasis web yang responsif, terintegrasi, dan mudah digunakan pada **PT. Visimedia Pratama Persada**.
2. Mempercepat proses eskalasi dan distribusi tugas perbaikan dari NOC Helpdesk ke Field Engineer berdasarkan Nomor Sirkit (*Circuit ID*).
3. Meningkatkan transparansi proses penanganan melalui pelacakan status (*Open, Assigned, In Progress, Resolved, Closed*).
4. Menyediakan parameter evaluasi kinerja berbasis SLA (*Compliance Rate %*) dan laporan bulanan resmi (*Work Order & Monthly SLA Report*) untuk kebutuhan audit manajemen dan evaluasi kontrak klien B2B.

---

### 1.5 Manfaat Penelitian
* **Bagi PT. Visimedia Pratama Persada**: Meningkatkan kepuasan klien korporat, menjaga reputasi kualitas layanan (*Service Quality*), meminimalisir pinalti SLA, dan mendokumentasikan Berita Acara Troubleshooting secara rapi.
* **Bagi Tim NOC & Field Engineer**: Mempermudah alur kerja penugasan tugas teknis, pencatatan *root cause*, dan pemantauan sisa waktu SLA.
* **Bagi Perusahaan Klien (Mitra B2B)**: Memperoleh kemudahan melapor kendala sirkit, memantau estimasi teknisi tiba di site, dan menerima Berita Acara resmi.
* **Bagi Penulis**: Menerapkan konsep rekayasa perangkat lunak, arsitektur database relasional, dan integrasi sistem web ke dalam studi kasus nyata industri telekomunikasi.

---

## BAB 2: METODOLOGI PENGEMBANGAN SISTEM (WATERFALL)

Sistem dikembangkan menggunakan model proses perangkat lunak **Waterfall (Air Terjun)** menurut Roger S. Pressman / Ian Sommerville:

```
[ 1. Requirement Analysis (Analisis Kebutuhan B2B) ]
                     │
                     ▼
[ 2. System Design (Perancangan UML & Database ERD) ]
                     │
                     ▼
[ 3. Implementation (Pengkodean PHP, MySQL, Bootstrap 5) ]
                     │
                     ▼
[ 4. Integration & Testing (Pengujian Black Box Testing) ]
                     │
                     ▼
[ 5. Operation & Maintenance (Pemeliharaan & Manajemen Master) ]
```

1. **Requirement Analysis (Analisis Kebutuhan)**: Mengidentifikasi alur kerja pelaporan gangguan sirkit jaringan di PT. Visimedia Pratama Persada, mengumpulkan data jenis layanan B2B, dan menetapkan matriks target SLA per tingkat urgensi.
2. **System Design (Perancangan)**: Membuat diagram UML (*Use Case, Activity, Sequence, Class Diagram*) dan merancang skema Entity Relationship Diagram (ERD) dengan tabel relasi `clients`, `users`, `tickets`, `ticket_logs`, `categories`, `priorities`, dan `settings`.
3. **Implementation (Pengkodean)**: Mengembangkan aplikasi web berbasis PHP Native berstruktur modular, database MySQL, styling Bootstrap 5 bergaya Enterprise Human-Crafted, DataTables, dan PHPMailer.
4. **Integration & Testing (Pengujian)**: Menguji fungsionalitas sistem menggunakan metode *Black Box Testing* untuk memverifikasi alur disposisi tiket, kalkulasi SLA otomatis, dan pengiriman email.
5. **Operation & Maintenance (Pemeliharaan)**: Menyediakan panel konfigurasi dinamis (profil perusahaan, pengaturan SMTP, manajemen akun, master klien, kategori, dan prioritas SLA).

---

## BAB 3: ANALISIS DAN PERANCANGAN SISTEM (UML)

### 3.1 Identifikasi Aktor & Hak Akses
1. **PIC Klien Korporat**: Menerbitkan tiket gangguan sirkit (*Circuit ID*), memantau timeline perbaikan teknisi, menerima bukti email, dan mengonfirmasi penutupan tiket (*Close Confirmation*).
2. **NOC Helpdesk (Dispatcher)**: Menerima notifikasi tiket masuk dari klien B2B, memvalidasi kendala sirkit, menentukan tingkat prioritas SLA, dan mendisposisikan ke *Field Engineer*.
3. **Field / Network Engineer**: Menerima email tugas, mengubah status menjadi *In Progress*, menginput Berita Acara Troubleshooting (*Root Cause, Action Taken, Technical Notes*), dan menyelesaikan tiket (*Resolved*).
4. **Head of NOC / Manager**: Memantau ringkasan statistik, persentase kepatuhan SLA per-klien (*Compliance Rate %*), rata-rata MTTR, dan mencetak Rekap Laporan Bulanan Resmi.
5. **Administrator Master**: Mengelola master data klien B2B, Circuit ID, akun pengguna, kategori gangguan, parameter SLA, dan konfigurasi SMTP.

---

### 3.2 Matriks Standar Service Level Agreement (SLA) B2B

| Tingkat Urgensi | Target Batas SLA | Contoh Kasus Gangguan Sirkit B2B |
| :--- | :--- | :--- |
| **Critical (P1)** | **2 Jam** | Link Utama Dedicated Internet Down Total / Loss of Signal (LOS) / Router CPE Mati. |
| **High (P2)** | **4 Jam** | Intermittent Packet Loss > 20% pada Link MPLS / Flapping BGP Session / Failover Aktif. |
| **Medium (P3)** | **8 Jam** | Throughput Download/Upload di bawah bandwidth kontrak / Sub-layanan VPN tertentu error. |
| **Low (P4)** | **24 Jam** | Permintaan teknis penambahan routing IP / Pengecekan berkala port converter. |

---

### 3.3 Logika & Rumus Perhitungan SLA

1. **Target Waktu Selesai (SLA Deadline)**:
   $$\text{SLA Deadline} = \text{created\_at} + \text{SLA Hours}$$

2. **Durasi Penanganan Riil (Resolution Time / MTTR)**:
   $$\text{Resolution Time (Menit)} = \text{resolved\_at} - \text{created\_at}$$

3. **Status Kepatuhan SLA**:
   * **Within SLA (Tepat Waktu)**: Jika $\text{resolved\_at} \le \text{SLA Deadline}$
   * **Breached SLA (Terlambat)**: Jika $\text{resolved\_at} > \text{SLA Deadline}$

4. **Tingkat Kepatuhan SLA Tim (SLA Compliance Rate %)**:
   $$\text{Compliance Rate} = \left( \frac{\sum \text{Tiket Selesai Tepat Waktu}}{\sum \text{Total Tiket Selesai}} \right) \times 100\%$$

---

## 🎯 Kumpulan Tanya Jawab (Q&A) untuk Sidang KKP

Gunakan panduan jawaban ini saat ditanya dosen penguji:

* **T: Mengapa judul penelitian Anda berfokus pada gangguan jaringan di PT. Visimedia Pratama Persada?**
  > **J:** Karena PT. Visimedia Pratama Persada merupakan penyedia layanan jaringan B2B (Managed Services & Dedicated Connection) untuk klien korporat. Sebelum adanya sistem ini, pelaporan komplain sirkit masih melalui WhatsApp dan telepon manual tanpa *ticket tracking* resmi, sehingga sering terjadi keterlambatan respon dan ketidakjelasan perhitungan SLA kontrak.

* **T: Apa perbedaan sistem ticketing ini dengan sistem helpdesk internal biasa?**
  > **J:** Sistem ini dirancang untuk model **B2B (Business-to-Business)**. Objek pelaporannya adalah nomor sirkit layanan (*Circuit ID*) milik perusahaan klien korporat (misal: Dedicated Internet 500 Mbps atau IP-VPN MPLS). Sistem ini mengukur SLA kepatuhan kontrak bisnis dan menyediakan Berita Acara Troubleshooting resmi serta laporan evaluasi performa per-klien korporat.

* **T: Mengapa memilih metode Waterfall dalam pengembangan sistem ini?**
  > **J:** Karena spesifikasi kebutuhan sistem trouble ticketing B2B, matriks waktu SLA, dan alur penugasan teknisi sudah terdefinisi secara jelas sejak awal analisis, sehingga tahapan Waterfall yang berurutan dan terstruktur (Analisis $\rightarrow$ Desain UML $\rightarrow$ Koding $\rightarrow$ Testing) sangat efektif dan terukur.

* **T: Bagaimana sistem membuktikan notifikasi email bekerja otomatis?**
  > **J:** Sistem mengintegrasikan pustaka PHPMailer dengan protokol SMTP Gmail. Setiap kali terjadi pemicu (*trigger*) transaksi—seperti Klien submit tiket, Helpdesk menugaskan teknisi, atau teknisi menyelesaikan perbaikan—sistem langsung mengeksekusi pengiriman email HTML formal yang memuat nomor tiket, Circuit ID, batas SLA, dan tombol verifikasi ke kotak masuk Gmail penerima.

* **T: Bagaimana sistem menentukan apakah penanganan teknisi berstatus Within SLA atau Breached?**
  > **J:** Sistem mengunci *timestamp* saat tiket dibuat (`created_at`) dan menambahkan batas jam prioritas untuk menghasilkan `sla_deadline`. Saat teknisi menyelesaikan perbaikan, sistem mencatat `resolved_at` dan membandingkan secara otomatis: jika waktu selesai $\le$ batas deadline, maka tiket diklasifikasikan sebagai *Within SLA (On-Time)*; jika melebihi, otomatis menjadi *Breached*.
