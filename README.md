# 📍 GeoPresensi — Sistem Presensi Karyawan GPS & Geofencing

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-00758F?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-CDN-38B2AC?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Leaflet JS](https://img.shields.io/badge/Maps-Leaflet.js-199900?style=flat&logo=leaflet&logoColor=white)](https://leafletjs.com/)
[![Aesthetic](https://img.shields.io/badge/UI-Dark%20Glossy%20Glassmorphism-6366F1?style=flat)](#)

Aplikasi web presensi karyawan modern berbasis **Koordinat GPS (Geolocation)**, **Validasi Radius Kantor (Haversine Formula)**, dan **Verifikasi Foto Selfie Real-Time (WebRTC)** dengan antarmuka futuristik bertema **Dark Glossy (Glassmorphism)** menggunakan **SCSS + Tailwind CDN**.

---

## ✨ Fitur Utama

### 👥 1. Hak Akses Berbasis Peran (Role-Based Access Control)
* **Karyawan (Employee)**:
  * Melakukan presensi masuk dan pulang mandiri dengan verifikasi koordinat GPS dan foto kamera.
  * Melihat riwayat presensi pribadi lengkap dengan filter cepat (*Hari Ini*, *Minggu Ini*, *Bulan Ini*) dan unduh CSV.
  * Memantau status kehadiran rekan kerja hari ini (*Hadir* 🟢 vs *Belum Hadir* 🔴).
  * Mengubah profil pribadi (Nama, Email, No. HP), foto profil (upload file / ambil foto via webcam), dan ganti kata sandi.
  * Proteksi pengalihan otomatis (*Redirect 302*) jika mencoba mengakses menu admin.
* **Administrator**:
  * **Kelola Karyawan**: Tambah pegawai baru, edit data, hapus akun, dan reset kata sandi.
  * **Pengaturan Titik GPS & Radius Kantor**: Geser pin kantor langsung di peta interaktif Leaflet atau gunakan lokasi GPS perangkat, serta slider radius dinamis (20m - 1000m).
  * **Ubah Teks Header Instan**: Edit teks nama instansi/kantor langsung dari modal di `<header>` atau menu pengaturan.
  * **Audit Rekap Laporan Absensi**: Tab filter rekap mingguan, bulanan, statistik per divisi, ekspor CSV / Excel, dan mode cetak resmi (*print-ready*).

### 🛰️ 2. Presensi GPS & Geofencing Presisi
* **Kalkulasi Jarak Real-Time (Haversine)**: Menghitung jarak meter posisi karyawan ke koordinat kantor secara instan.
* **Leaflet Dark Map (100% Free)**: Peta berbasis OpenStreetMap berfilter visual dark high-contrast tanpa batas kuota dan tanpa memerlukan API Key pihak ketiga.
* **Mode Penegakan Radius**: Opsi mode *Ketat* (wajib di dalam radius kantor) atau mode *Fleksibel* (mencatat kehadiran di luar radius).
* **Simulator Koordinat (Demo)**: Tombol pengujian cepat (*Di Kantor*, *Luar Radius*, *GPS Nyata*) untuk kemudahan demonstrasi tanpa harus berpindah tempat secara fisik.

### 📸 3. Bukti Foto Kehadiran (WebRTC Camera)
* Viewfinder kamera dengan HUD corner brackets, laser scanning animasi, dan preview foto instan.
* Optimasi otomatis resolusi gambar di sisi klien (Canvas) sehingga pengunggahan sangat cepat (<200KB) dan hemat kuota.

### 💎 4. Desain Dark Glossy & Responsif Multi-Perangkat
* Desain elegan bercahaya (*ambient glow, translucent glass panels, neon accents*).
* **Live Digital Clock**: Jam digital real-time WIB dan penanggalan berbahasa Indonesia terintegrasi pada Page Header Banner utama.
* **Bottom Navigation Bar Ergonomis**: Bilah menu mengambang di bawah layar ponsel memudahkan navigasi satu tangan di layar sentuh.

---

## 📁 Struktur Direktori

```plaintext
sispresensi/
├── api/
│   ├── presensi.php              # API pemroses absensi GPS, Haversine, & upload bukti selfie
│   └── update_office_name.php    # API update cepat teks nama kantor / header (Khusus Admin)
├── assets/
│   ├── css/style.css             # CSS hasil kompilasi SCSS (Glassmorphism & dark theme)
│   ├── js/
│   │   ├── attendance.js         # Logika Geolocation GPS, peta Leaflet, & kamera WebRTC
│   │   └── settings-map.js       # Peta interaktif penentu koordinat & live slider radius
│   └── uploads/                  # Direktori penyimpanan foto bukti presensi & foto profil
├── config/
│   ├── database.php              # Konfigurasi koneksi PDO, BASE_URL dinamis, & session helper
│   └── migrate.php               # Skrip migrasi tabel database dan data awal
├── database.sql                  # Dump database MySQL lengkap (struktur tabel & data awal)
├── includes/
│   ├── header.php                # Navbar atas, modal ubah teks header, & mobile bottom nav
│   └── footer.php                # Footer dan interval live digital clock
├── scss/
│   ├── _variables.scss           # Palet warna gelap, glow, & font tokens
│   ├── _glass.scss               # Efek backdrop-blur & specular glass highlight
│   ├── _components.scss          # Komponen tombol glowing, badge, HUD viewfinder, leaflet dark
│   └── style.scss                # Master stylesheet SCSS
├── index.php                     # Dashboard utama & workspace presensi live
├── karyawan.php                  # Kelola data master karyawan (Khusus Admin)
├── laporan.php                   # Rekapitulasi laporan kehadiran mingguan & bulanan (Khusus Admin)
├── login.php                     # Halaman login autentikasi karyawan & admin
├── logout.php                    # Penghapusan sesi aman
├── profile.php                   # Edit profil, update foto (upload/webcam), ganti password
├── riwayat.php                   # Riwayat absensi pribadi karyawan
├── settings.php                  # Pengaturan titik koordinat, radius geofence, & jam kerja (Admin)
└── tim.php                       # Monitoring kehadiran rekan kerja hari ini
```

---

## 🚀 Panduan Instalasi

### 1. Kebutuhan Sistem
* Web Server (Apache 2.4+ / Nginx)
* PHP 8.1 atau lebih baru (ekstensi `pdo_mysql`, `gd`, `fileinfo`, `json`)
* MySQL 8.0+ atau MariaDB 10.5+

### 2. Kloning Repositori
```bash
git clone https://github.com/mrhdyt404/sispresensi.git /var/www/html/sispresensi
cd /var/www/html/sispresensi
```

### 3. Konfigurasi Database
1. Buat database baru di MySQL:
   ```sql
   CREATE DATABASE sispresensi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Impor struktur dan data awal:
   ```bash
   mysql -u root -p sispresensi_db < database.sql
   ```
   *Atau jalankan skrip migrasi bawaan:*
   ```bash
   php config/migrate.php
   ```
3. Sesuaikan kredensial database di [`config/database.php`](config/database.php):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sispresensi_db');
   define('DB_USER', 'username_mysql');
   define('DB_PASS', 'password_mysql');
   ```

### 4. Pengaturan Hak Akses Direktori
Pastikan web server memiliki izin menulis pada folder unggahan:
```bash
sudo chown -R www-data:www-data assets/uploads/
sudo chmod -R 775 assets/uploads/
```

### 5. Kompilasi SCSS (Opsional jika ingin mengubah style)
```bash
npm install
npx sass scss/style.scss assets/css/style.css
```

---

## 🔑 Akun Bawaan (Default Demo Accounts)

| Peran (Role) | Email | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@perusahaan.com` | `password123` | Akses penuh: manajemen karyawan, radius GPS, & audit laporan |
| **Karyawan** | `dimas.bagus@perusahaan.com` | `password123` | Presensi mandiri, riwayat pribadi, status rekan kerja, profil |
| **Karyawan** | `siti.nurhaliza@perusahaan.com` | `password123` | Akun staf desain |

---

## 🔒 Konfigurasi Rekomendasi PHP (`php.ini`)
Untuk mendukung pengunggahan foto kamera resolusi tinggi dengan mulus:
```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
```

---

## 📜 Lisensi
Proyek ini dilisensikan di bawah [MIT License](LICENSE).
Dikembangkan untuk kebutuhan manajemen presensi modern dengan akurasi GPS terpercaya.
