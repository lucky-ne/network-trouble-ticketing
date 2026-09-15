# 🚀 PANDUAN LENGKAP DEPLOY DOCKER & CI/CD DI SERVER UBUNTU LINUX
### Sistem Informasi Network Trouble Ticketing & SLA Tracking B2B
**PT. Visimedia Pratama Persada**

Dokumen ini memuat panduan lengkap dari awal (*from scratch*) mengenai instalasi Docker di Ubuntu Linux, deployment aplikasi web multi-kontainer, konfigurasi domain, integrasi otomatisasi CI/CD GitHub Actions, hingga pemasangan sertifikat SSL HTTPS (Let's Encrypt).

---

## 📋 DAFTAR ISI
1. [Prasyarat Server](#1-prasyarat-server)
2. [Instalasi Docker & Docker Compose di Ubuntu](#2-instalasi-docker--docker-compose-di-ubuntu)
3. [Menyiapkan Source Code di Server](#3-menyiapkan-source-code-di-server)
4. [Menjalankan Aplikasi (1 Perintah Docker Compose)](#4-menjalankan-aplikasi-1-perintah-docker-compose)
5. [Akses Sistem di Browser](#5-akses-sistem-di-browser)
6. [Otomasi Deployment CI/CD (GitHub Actions)](#6-otomasi-deployment-cicd-github-actions)
7. [Konfigurasi Domain & HTTPS / SSL Gratis (Let's Encrypt)](#7-konfigurasi-domain--https--ssl-gratis-lets-encrypt)
8. [Perintah Manajemen, Monitoring & Backup Data](#8-perintah-manajemen-monitoring--backup-data)
9. [Troubleshooting & Solusi Kendala](#9-troubleshooting--solusi-kendala)

---

## 1. Prasyarat Server
- **Sistem Operasi**: Ubuntu 20.04 LTS / 22.04 LTS / 24.04 LTS (x86_64)
- **Spesifikasi Minimum**: 1 vCPU, 1 GB RAM, 10 GB Disk Space
- **IP Publik / Domain**: Contoh: `103.79.155.220` atau domain `vmp-net.zapto.org`
- **Hak Akses**: Pengguna dengan hak `sudo` atau `root`

---

## 2. Instalasi Docker & Docker Compose di Ubuntu

Buka terminal SSH ke server Ubuntu Anda dan jalankan perintah berikut:

```bash
# 1. Update repository paket Ubuntu & install dependensi
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# 2. Tambahkan GPG Key resmi Docker
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# 3. Tambahkan repository Docker
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# 4. Install Docker Engine, CLI, dan Compose Plugin
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# 5. Aktifkan service Docker agar otomatis hidup saat server reboot
sudo systemctl enable docker
sudo systemctl start docker

# 6. Verifikasi versi yang terpasang
docker --version
docker compose version
```

---

## 3. Menyiapkan Source Code di Server

Buat direktori project di server (misalnya di `/var/www/net-ticketing`):

```bash
sudo mkdir -p /var/www/net-ticketing
sudo chown -R $USER:$USER /var/www/net-ticketing
cd /var/www/net-ticketing
```

Clone repository Git dari GitHub:
```bash
git clone https://github.com/lucky-ne/network-trouble-ticketing.git .
```

Pastikan struktur direktori di `/var/www/net-ticketing/` telah memuat file utama:
```text
/var/www/net-ticketing/
├── Dockerfile
├── docker-compose.yml
├── database.sql
├── config/database.php
├── auth/
├── customer/
├── helpdesk/
├── teknisi/
├── manager/
├── admin/
└── ...
```

---

## 4. Menjalankan Aplikasi (1 Perintah Docker Compose)

Masuk ke folder project dan build kontainer:

```bash
cd /var/www/net-ticketing
sudo docker compose up -d --build
```

### ⚡ Apa yang Terjadi Secara Otomatis?
1. **Container `app` (PHP 8.2 Apache)**: Dibangun dengan modul `pdo_mysql`, hak akses folder `uploads/` dikonfigurasi, dan terhubung ke port HTTP `80` (atau port yang disesuaikan).
2. **Container `db` (MariaDB 10.11)**: Database `net_ticketing_db` otomatis dibuat dan skema data awal dari `database.sql` **di-import secara otomatis** saat inisialisasi awal.
3. **Container `phpmyadmin`**: Antarmuka database berbasis web siap digunakan di port `8081`.

---

## 5. Akses Sistem di Browser

| Layanan | URL Akses | Kredensial Login |
| :--- | :--- | :--- |
| **Aplikasi Web Utama** | `http://IP-SERVER/` atau `http://vmp-net.zapto.org` | Demo 1-Klik / Akun masing-masing role |
| **phpMyAdmin (GUI DB)** | `http://IP-SERVER:8081` | Server: `db`, User: `root`, Pass: `root_password` |

### 👥 Kredensial Akun Bawaan:
- **Admin Master**: `admin@perusahaan.com` / `password`
- **Customer (PIC Klien)**: `budi@perusahaan.com` / `password`
- **NOC Helpdesk**: `helpdesk@perusahaan.com` / `password`
- **Field Engineer**: `ahmad.teknisi@perusahaan.com` / `password`
- **Head of NOC / IT Manager**: `manager.it@perusahaan.com` / `password`

---

## 6. Otomasi Deployment CI/CD (GitHub Actions)

Project ini telah dilengkapi pipeline **Continuous Deployment** otomatis pada `.github/workflows/deploy.yml`.

### Alur Kerja CI/CD:
1. Anda mengedit kode di laptop (misal: di `C:\xampp\htdocs\`).
2. Anda melakukan `git commit` dan `git push origin main`.
3. GitHub Actions otomatis terpicu:
   - Melakukan koneksi SSH aman ke server Ubuntu (`103.79.155.220`).
   - Masuk ke direktori `/var/www/net-ticketing`.
   - Menjalankan `git pull origin main`.
   - Menjalankan `docker compose up -d --build`.
   - Seluruh perubahan langsung aktif di server dalam hitungan detik!

### Cara Konfigurasi GitHub Repository Secrets:
Buka repository Anda di GitHub $\rightarrow$ **Settings** $\rightarrow$ **Secrets and variables** $\rightarrow$ **Actions** $\rightarrow$ Tambahkan:
- `SERVER_IP`: IP Publik server Anda (contoh: `103.79.155.220`).
- `SERVER_USER`: Username SSH server (contoh: `root` atau `ubuntu`).
- `SSH_PRIVATE_KEY`: Private Key SSH Anda (isi dari `id_rsa`).
- `SERVER_PORT`: Port SSH (default: `22`).

---

## 7. Konfigurasi Domain & HTTPS / SSL Gratis (Let's Encrypt)

Agar domain `vmp-net.zapto.org` dapat diakses melalui protokol aman **HTTPS** (`https://vmp-net.zapto.org`) dengan indikator gembok hijau resmi, gunakan kombinasi **Nginx Reverse Proxy** dan **Certbot Let's Encrypt**:

### Langkah 1: Ubah Port Web App Docker ke Port Internal (8080)
Edit file `docker-compose.yml` di server:
```yaml
services:
  app:
    ports:
      - "8080:80"   # Aplikasi PHP berjalan di port 8080 host
```
Terapkan perubahan:
```bash
sudo docker compose up -d
```

### Langkah 2: Install Nginx dan Certbot di Ubuntu
```bash
sudo apt-get update
sudo apt-get install -y nginx certbot python3-certbot-nginx
```

### Langkah 3: Konfigurasi Virtual Host Nginx
Buat file konfigurasi Nginx:
```bash
sudo nano /etc/nginx/sites-available/vmp-net.zapto.org
```
Isi dengan konfigurasi berikut:
```nginx
server {
    listen 80;
    server_name vmp-net.zapto.org;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
Aktifkan konfigurasi dan restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/vmp-net.zapto.org /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Langkah 4: Terbitkan Sertifikat SSL HTTPS Gratis
Jalankan Certbot:
```bash
sudo certbot --nginx -d vmp-net.zapto.org
```
- Masukkan alamat email Anda.
- Setujui syarat & ketentuan (*Agree*).
- Certbot akan otomatis mengonfigurasi SSL dan mengarahkan seluruh lalu lintas HTTP ke **HTTPS secara otomatis**.
- Buka browser dan akses: `https://vmp-net.zapto.org` 🔒

---

## 8. Perintah Manajemen, Monitoring & Backup Data

### Melihat Status Kontainer:
```bash
sudo docker compose ps
```

### Melihat Log Real-Time Aplikasi:
```bash
# Log seluruh service
sudo docker compose logs -f

# Log khusus aplikasi web PHP
sudo docker compose logs -f app

# Log database MariaDB
sudo docker compose logs -f db
```

### Menghentikan / Menjalankan Kontainer:
```bash
# Menghentikan kontainer
sudo docker compose down

# Menjalankan kontainer kembali
sudo docker compose up -d

# Me-restart kontainer
sudo docker compose restart
```

### Backup Database MariaDB Secara Langsung:
```bash
sudo docker compose exec db mariadb-dump -u root -proot_password net_ticketing_db > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore Database dari File Backup:
```bash
sudo docker compose exec -T db mariadb -u root -proot_password net_ticketing_db < backup_file.sql
```

---

## 9. Troubleshooting & Solusi Kendala

#### 1. Port 80 / 8080 Terpakai Aplikasi Lain
Periksa proses yang menggunakan port:
```bash
sudo lsof -i :80
sudo lsof -i :8080
```
Hentikan service yang bertabrakan (misal: Apache bawaan OS `sudo systemctl stop apache2`).

#### 2. Reset Database ke Kondisi Awal (Fresh Seed)
Jika Anda ingin menghapus seluruh data pengujian dan mengembalikan isi database ke kondisi awal sesuai `database.sql`:
```bash
sudo docker compose down -v
sudo docker compose up -d --build
```
*(Flag `-v` akan menghapus volume database lama dan menginisialisasi ulang dari `database.sql` secara bersih)*.

---

**Selamat! Sistem Network Trouble Ticketing & SLA Tracking B2B Anda telah berjalan optimal, aman, dan terintegrasi CI/CD secara penuh.** 🚀
