# 🚀 PANDUAN LENGKAP DEPLOY KE DOCKER DI SERVER UBUNTU LINUX
### Sistem Informasi Network Trouble Ticketing & SLA Tracking B2B
**PT. Visimedia Pratama Persada**

---

## 📋 DAFTAR ISI
1. [Prasyarat Server](#1-prasyarat-server)
2. [Instalasi Docker & Docker Compose di Ubuntu](#2-instalasi-docker--docker-compose-di-ubuntu)
3. [Menyiapkan Source Code di Server](#3-menyiapkan-source-code-di-server)
4. [Menjalankan Aplikasi (1 Perintah)](#4-menjalankan-aplikasi-1-perintah)
5. [Akses Sistem di Browser](#5-akses-sistem-di-browser)
6. [Perintah Manajemen & Monitoring](#6-perintah-manajemen--monitoring)
7. [Troubleshooting & Solusi Kendala](#7-troubleshooting--solusi-kendala)

---

## 1. Prasyarat Server
- **Sistem Operasi**: Ubuntu 20.04 LTS / 22.04 LTS / 24.04 LTS
- **RAM**: Minimal 1 GB (Disarankan 2 GB atau lebih)
- **Disk Space**: Minimal 5 GB
- **Akses**: Root / Hak akses `sudo`

---

## 2. Instalasi Docker & Docker Compose di Ubuntu

Jalankan perintah berikut di terminal SSH Ubuntu Anda untuk menginstal Docker resmi terbaru:

```bash
# 1. Update repository paket Ubuntu
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# 2. Tambahkan GPG Key resmi Docker
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# 3. Setup repository Docker
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# 4. Install Docker Engine & Docker Compose Plugin
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# 5. Aktifkan service Docker agar otomatis start saat booting
sudo systemctl enable docker
sudo systemctl start docker

# 6. Verifikasi instalasi berhasil
docker --version
docker compose version
```

---

## 3. Menyiapkan Source Code di Server

Buat direktori project di Ubuntu (misalnya di `/var/www/net-ticketing`):

```bash
sudo mkdir -p /var/www/net-ticketing
cd /var/www/net-ticketing
```

Salin atau upload seluruh folder project ini ke dalam `/var/www/net-ticketing/` menggunakan **Git**, **SCP**, **FileZilla (SFTP)**, atau `rsync`.

Pastikan struktur file di `/var/www/net-ticketing/` memuat:
```text
/var/www/net-ticketing/
├── Dockerfile
├── docker-compose.yml
├── database.sql
├── config/
│   └── database.php
├── auth/
├── customer/
├── helpdesk/
├── teknisi/
├── manager/
├── admin/
└── ...
```

---

## 4. Menjalankan Aplikasi (1 Perintah)

Masuk ke folder project dan jalankan Docker Compose:

```bash
cd /var/www/net-ticketing
sudo docker compose up -d --build
```

> **Catatan Otomatisasi:**
> - Docker akan otomatis membangun image PHP 8.2 Apache dengan modul yang diperlukan.
> - MariaDB akan otomatis membuat database `net_ticketing_db` dan meng-import data awal dari `database.sql` secara otomatis (tanpa perlu import manual!).
> - phpMyAdmin siap digunakan di port `8081`.

---

## 5. Akses Sistem di Browser

Setelah kontainer berjalan (`status: running`), buka browser di laptop/komputer Anda:

| Layanan | URL Akses | Kredensial Login |
| :--- | :--- | :--- |
| **Aplikasi Web Utama** | `http://IP-SERVER-ANDA/` | Demo 1-Klik / Akun masing-masing role |
| **phpMyAdmin (GUI DB)** | `http://IP-SERVER-ANDA:8081` | Server: `db`, User: `root`, Password: `root_password` |

### Akun Demo Bawaan:
- **Admin Master**: `admin@perusahaan.com` / `password`
- **Customer (PIC Klien)**: `budi@perusahaan.com` / `password`
- **NOC Helpdesk**: `helpdesk@perusahaan.com` / `password`
- **Field Engineer**: `fauzi@perusahaan.com` / `password`
- **Manager / Head of NOC**: `manager.it@perusahaan.com` / `password`

---

## 6. Perintah Manajemen & Monitoring

### Melihat Status Kontainer yang Sedang Berjalan:
```bash
sudo docker compose ps
```

### Melihat Log Real-Time Aplikasi:
```bash
# Log seluruh service
sudo docker compose logs -f

# Log khusus aplikasi PHP Apache
sudo docker compose logs -f app

# Log database MariaDB
sudo docker compose logs -f db
```

### Menghentikan Aplikasi:
```bash
sudo docker compose down
```

### Me-restart Aplikasi:
```bash
sudo docker compose restart
```

### Masuk ke Shell Kontainer PHP (Jika Perlu Eksekusi Script Internal):
```bash
sudo docker compose exec app bash
```

### Backup Database Langsung dari Docker:
```bash
sudo docker compose exec db mariadb-dump -u root -proot_password net_ticketing_db > backup_$(date +%Y%m%d).sql
```

---

## 7. Troubleshooting & Solusi Kendala

#### 1. Port 80 Sudah Digunakan Service Lain (misal Apache bawaan Ubuntu / Nginx)
Jika server Ubuntu Anda sudah menjalankan Apache2 atau Nginx lokal di port 80:
- Buka file `docker-compose.yml`:
  ```yaml
  ports:
    - "8080:80"   # Ganti port host dari 80 menjadi 8080
  ```
- Restart kontainer:
  ```bash
  sudo docker compose up -d
  ```
- Akses aplikasi di `http://IP-SERVER-ANDA:8080`.

#### 2. Reset Database ke Kondisi Awal:
Jika ingin me-reset database dan meng-import ulang `database.sql`:
```bash
sudo docker compose down -v
sudo docker compose up -d
```
*(Flag `-v` akan menghapus volume database lama dan menginisialisasi ulang dari `database.sql`)*.

---

**Selamat! Aplikasi Network Trouble Ticketing & SLA Tracking B2B Anda telah berhasil ter-deploy di Docker Ubuntu.** 🚀
