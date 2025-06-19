# Pharmasys - Sistem Informasi Apotek dan Dokter

Pharmasys adalah sebuah aplikasi web berbasis PHP yang dirancang untuk memudahkan komunikasi dan kolaborasi antara **dokter** dan **apoteker** dalam proses pelayanan resep, pengelolaan inventaris obat, serta pemrosesan pesanan obat dari pasien. Sistem ini dibangun sebagai solusi digital untuk meningkatkan efisiensi layanan kesehatan, khususnya dalam proses penyediaan obat-obatan yang diresepkan oleh dokter dan dipenuhi oleh apotek.

---

## 🎯 Tujuan Aplikasi

- Memberikan platform terintegrasi antara dokter dan apotek
- Mempercepat proses pencarian dan pemesanan obat
- Mengurangi kesalahan dalam penulisan resep dan pemenuhan obat
- Menyediakan sistem manajemen stok dan pesanan apotek secara digital

---

## 🧩 Fitur Utama

### 1. **Manajemen Pengguna**
- Registrasi akun untuk **dokter** dan **apotek**
- Proses login dan logout yang aman
- Profil pengguna (dokter/apoteker) yang dapat diedit

### 2. **Dashboard Dokter**
- Lihat daftar apotek yang tersedia
- Tulis resep untuk pasien dan kirim ke apotek
- Cari obat dan cek ketersediaannya di berbagai apotek

### 3. **Dashboard Apotek**
- Kelola **inventaris obat** yang tersedia di apotek
- Terima dan proses **pesanan resep** dari dokter
- Lihat daftar dokter yang bekerja sama

### 4. **Pencarian Obat**
- Fitur pencarian cepat berdasarkan nama obat
- Filter berdasarkan apotek tertentu

### 5. **Manajemen Data**
- Manajemen data resep, pesanan, dan ketersediaan stok
- Penyimpanan data pada basis data MySQL dengan struktur relasional

---

## 📁 Struktur Proyek

```plaintext
ProjekWebTeori/
│
├── index.php                  # Halaman login utama
├── register.php               # Pendaftaran umum
├── register_doctor.php        # Pendaftaran akun dokter
├── register_pharmacy.php      # Pendaftaran akun apotek
├── login.php                  # Proses autentikasi login
├── logout.php                 # Logout pengguna
│
├── dashboard.php              # Dashboard utama
├── doctor_dashboard.php       # Dashboard khusus dokter
├── doctor_prescriptions.php   # Buat dan kelola resep
├── doctor_orders.php          # Pantau pesanan dari apotek
├── doctor_pharmacies.php      # Lihat daftar apotek
├── doctor_medication_search.php # Pencarian obat
│
├── pharmacies.php             # Kelola daftar apotek
├── inventory.php              # Kelola inventaris obat
├── orders.php                 # Kelola pesanan masuk
│
├── profile.php                # Halaman pengaturan profil
├── sidebar.php                # Navigasi umum
├── doctor_sidebar.php         # Navigasi khusus dokter
│
├── get_pharmacy_medications.php # Ambil data obat dari apotek
├── install.php                # Setup awal basis data
├── pharmasys.sql              # Struktur database MySQL
│
├── styles.css                 # Desain dan tampilan antarmuka
└── .git/                      # Folder Git (abaikan)
```
---

## 🛠️ Teknologi yang Digunakan

- **Bahasa Pemrograman**: PHP (server-side)
- **Frontend**: HTML, CSS
- **Basis Data**: MySQL (dapat diimpor dari `pharmasys.sql`)
- **Web Server**: Apache (disarankan menggunakan XAMPP, Laragon, atau sejenis)

---

## ⚙️ Cara Instalasi dan Menjalankan Proyek

1. **Kloning atau Ekstrak File**
   Ekstrak isi file `ProjekWebTeori.zip` ke dalam folder `htdocs` (jika menggunakan XAMPP).

2. **Import Basis Data**
   - Buka phpMyAdmin.
   - Buat database baru dengan nama `pharmasys`.
   - Import file `pharmasys.sql` ke dalam database tersebut.

3. **Konfigurasi File Koneksi (jika ada)**
   - Periksa apakah ada file koneksi seperti `install.php`.
   - Pastikan informasi `host`, `user`, `password`, dan `database` sesuai.

4. **Jalankan Aplikasi**
   - Buka browser dan akses melalui `http://localhost/ProjekWebTeori/index.php`.

---

## 🧪 Akun Contoh (Opsional)

Jika tersedia, tambahkan akun dummy yang bisa digunakan untuk testing, misalnya:

**Akun Dokter**  
- Email: `dr.budi@example.com`  
- Password: `password`

- Email: `dr.siti@example.com`  
- Password: `password`

**Akun Apotek**  
- Email: `apotek.sehat@example.com`  
- Password: `password`

- Email: `apotek.kimia@example.com`  
- Password: `password`
  
---

## 📝 Catatan

- Pastikan modul PHP `mysql` sudah aktif di server Anda.
- Jika ada error, cek log error dari server dan pastikan konfigurasi basis data benar.
- Untuk keperluan deployment, disarankan menambahkan sistem validasi dan keamanan tambahan (misalnya hash password, validasi form).

---

## 👨‍💻 Kontributor

Proyek ini dibuat sebagai bagian dari pembelajaran/pengembangan sistem informasi berbasis web oleh:

**Nama, NPM, Kelas:**  
- Amala Ratri Nugraheni 2317051007 Kelas A
- Adila Nurul Hidayah 2317051034 Kelas A
- Sofia' Azahra 2317051075 Kelas A
- Aderiana Yustitia 2317051110 Kelas A

