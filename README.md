# PICO – Polinema Integrated Canteen Order

Aplikasi pemesanan makanan Kantin AX Politeknik Negeri Malang. Ditulis dengan **PHP murni** (tanpa framework dan tanpa Composer), memakai **PostgreSQL** langsung lewat **PDO**, dan tampilan **HTML + CSS + JavaScript biasa**.

Ada 3 peran dalam satu aplikasi: **pelanggan (pembeli)**, **penjual**, dan **admin**.

```
Browser  ──►  Aplikasi PHP (api/index.php)  ──►  PostgreSQL (PDO)
```

> **Penting:** aplikasi ini **tidak lagi memakai Supabase**. Versi awal memang memakai Supabase, tetapi sekarang PHP terhubung langsung ke database PostgreSQL di server Nivor. Nama fungsi `db_get`, `db_write`, dan `db_rpc` di `boot.php` adalah peninggalan versi awal; isinya sudah menjalankan SQL biasa lewat PDO.

---

## 1. Fitur per peran

| Peran | Fitur |
|---|---|
| **Pelanggan** | Daftar (mahasiswa Polinema mengunggah foto KTM, pengunjung umum tanpa KTM), login, lupa password, lihat stand dan menu, keranjang, pilih Dine in / Take Away, bayar QRIS (dengan hitung mundur 15 menit dan unggah bukti bayar), pantau status pesanan, riwayat, profil (ubah data, foto, password) |
| **Penjual** | Beranda ringkasan stand, daftar dan detail pesanan, ubah status pesanan (baru, diproses, siap, selesai, batal), kelola menu (tambah, ubah, hapus, foto, stok, tersedia/tidak), riwayat penjualan, buka/tutup stand, profil (ubah data, foto, unggah QRIS stand, notifikasi) |
| **Admin** | Login admin terpisah, dashboard penjualan, kelola pengguna (tambah, aktif/nonaktif, verifikasi KTM), kelola stand (tambah, buka/tutup, buat akun penjual, reset password), kategori, daftar menu, semua pesanan, laporan dengan ekspor |

---

## 2. Struktur folder

| Lokasi | Isi | Dikerjakan oleh |
|---|---|---|
| `index.php` | Pintu masuk root, meneruskan ke `api/index.php` | – |
| `api/index.php` | Daftar rute (alamat URL ke nama fungsi) | Bersama |
| `api/_app/boot.php` | Koneksi PostgreSQL (PDO), sesi login, keranjang, fungsi bantu, upload foto/KTM, rute otomatis `/foto`, `/ktm`, `/admin/ktm`, `/lupa-password` | **Back-end** |
| `api/_app/pelanggan.php` | Halaman pelanggan, login, dan daftar | Tampilan: front-end · Pemroses data: back-end |
| `api/_app/penjual.php` | Halaman penjual | Tampilan: front-end · Pemroses data: back-end |
| `api/_app/admin.php` | Halaman admin | Tampilan: front-end · Pemroses data: back-end |
| `api/_app/ui.php` | Kerangka halaman, ikon, menu navigasi, label status | **Front-end** |
| `api/_app/config.server.php` | Pengaturan koneksi database di server (berisi rahasia) | **Back-end** |
| `public/css/app.css`, `public/css/admin.css` | Gaya tampilan pembeli/penjual dan admin | **Front-end** |
| `public/js/app.js` | Interaksi kecil di browser | **Front-end** |
| `public/img/` | Gambar (logo PICO) | **Front-end** |
| `sql/schema.sql` | Tambahan struktur database (lihat bagian 5) | **Back-end** |
| `.htaccess` | Aturan Apache: blokir folder internal, arahkan URL ke `api/index.php` | Back-end |
| `router.php` | Penyaji untuk `php -S` dan Docker (file statis dari `public/`, sisanya ke `api/index.php`) | Back-end |
| `Dockerfile` | Gambar Docker (PHP 8.3 + `pdo_pgsql`, dijalankan lewat `router.php`) | Back-end |
| `vercel.json` | Peninggalan versi awal (Vercel). Tidak dipakai untuk deploy sekarang | – |
| `opencode.json` | Pengaturan alat bantu AI pribadi, **bukan bagian aplikasi** | – |

Aturan kerja: front-end hanya mengubah tampilan (HTML, CSS, JS, dan bagian tampilan di file halaman). Fungsi pemroses data (`r_*_post`, fungsi yang menyimpan/menghapus data) dan `boot.php` adalah tanggung jawab back-end.

---

## 3. Cara menjalankan di komputer sendiri

1. Pasang **PHP 8.3** dengan ekstensi **`pdo_pgsql`** dan **`fileinfo`** (dibutuhkan untuk koneksi database dan pengecekan jenis file unggahan).
2. Siapkan database PostgreSQL (lihat bagian 5). Host `nivor-postgres` kemungkinan hanya bisa dijangkau dari server Nivor sendiri, jadi untuk komputer lokal gunakan PostgreSQL lokal.
3. Buat file `.env` di folder utama (isinya lihat bagian 4).
4. Jalankan di terminal:
   ```
   php -S localhost:8000 router.php
   ```
5. Buka http://localhost:8000

---

## 4. Pengaturan koneksi database

Semua nilai dibaca oleh `boot.php` dari variabel lingkungan (environment):

| Variabel | Arti |
|---|---|
| `DB_CONNECTION` | Jenis database, isi `pgsql` |
| `DB_HOST` | Alamat server database |
| `DB_PORT` | Port (biasanya `5432`) |
| `DB_DATABASE` | Nama database |
| `DB_USERNAME` | Nama pengguna database |
| `DB_PASSWORD` | Password database |
| `APP_DEBUG` | `1` = tampilkan pesan error detail; `0` = pesan umum. **Isi `0` di server sungguhan** |

Contoh `.env` (ganti dengan nilai milik tim, jangan di-commit):

```
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=nama_database
DB_USERNAME=nama_pengguna
DB_PASSWORD=password_anda
APP_DEBUG=1
```

Urutan pembacaan di `boot.php`: file `.env` dibaca lebih dulu, lalu `api/_app/config.server.php` **hanya mengisi variabel yang masih kosong**.

**Keamanan:** `.env` dan `config.server.php` berisi rahasia. Repository harus **private**, dan `.env` sebaiknya ditambahkan ke `.gitignore` (saat ini belum tercantum). Kalau password pernah ter-upload ke GitHub, ganti di panel Nivor.

---

## 5. Database

### Tabel yang dipakai aplikasi
`profiles` (semua akun), `stands`, `kategori`, `menu`, `pesanan`, `pesanan_item`, dan `login_log`. Beberapa kolom tambahan (misalnya `bukti_*` pada `pesanan` dan `foto_*` pada `profiles`/`stands`) dibuat otomatis oleh `boot.php` saat pertama dipakai.

### Fungsi database yang dipanggil PHP
`buat_pesanan`, `saya_sudah_bayar`, `bersihkan_kadaluarsa`, `ubah_status`, dan `login_lookup`. Fungsi ini menjalankan aturan penting di sisi database: membuat pesanan, mengurangi stok, membatalkan pesanan yang tidak dibayar dalam 15 menit, dan menjaga urutan status pesanan.

### Isi `sql/schema.sql`
Berkas ini hanya **tambahan** di atas tabel yang sudah ada: kolom baru, tabel `login_log`, fungsi `daftar_pelanggan`, `login_user`, `ubah_qr_stand`, `buat_pesanan_multi`, lima view laporan, dan akun contoh (penjual dan admin).

### Yang belum ada di repository (perlu dilengkapi back-end)
- Perintah pembuatan tabel dasar (`profiles`, `stands`, `kategori`, `menu`, `pesanan`, `pesanan_item`) **tidak ada** di repo.
- Definisi lima fungsi di atas (`buat_pesanan`, `saya_sudah_bayar`, `bersihkan_kadaluarsa`, `ubah_status`, `login_lookup`) juga **tidak ada** di repo.

Akibatnya database baru tidak bisa dibangun hanya dari isi repo; strukturnya hanya ada di database yang sedang berjalan. Saran: back-end mengekspor strukturnya (`pg_dump --schema-only`) dan menyimpannya di `sql/`.

### Akun contoh
`schema.sql` bagian 6 membuat akun contoh (penjual `bu_ima`, `naqyka`, `perintis`, dan `admin`). **Ganti password semua akun itu sebelum dipakai sungguhan**, dan jangan menyimpan password asli di berkas SQL.

---

## 6. Deploy

- **Server Nivor** (`pico-app.nivor.id`): bisa memakai `Dockerfile` (menjalankan `php -S 0.0.0.0:$PORT router.php`) atau Apache dengan `.htaccess`. Sesuaikan dengan pengaturan di panel Nivor.
- **Docker lokal:**
  ```
  docker build -t pico .
  docker run -p 8080:8080 --env-file .env pico
  ```
- Folder `sql/` dan `api/_app/` diblokir dari akses browser oleh `.htaccess`.

---

## 7. Cara kerja kode singkat

1. Browser meminta alamat, misalnya `/home`.
2. `api/index.php` mencocokkan "METODE alamat" (misalnya `GET /home`) dengan nama fungsi di daftar rute, lalu menjalankannya (`r_home`).
3. Fungsi itu mengecek login dan peran (`need('pelanggan')`), mengambil data dari database, menyusun HTML, lalu memanggil `layout()` di `ui.php`.
4. Beberapa alamat ditangani langsung oleh `boot.php` tanpa lewat daftar rute: `/foto` (menyajikan foto dari database), `/ktm`, `/admin/ktm`, dan `/lupa-password`.

### Daftar rute

**Umum dan pelanggan**

| Alamat | Fungsi |
|---|---|
| `GET /` | Halaman awal (pilih Pembeli / Penjual) |
| `GET, POST /login` | Login |
| `GET, POST /daftar` | Daftar akun |
| `GET /keluar` | Keluar |
| `GET /home`, `GET /menu` | Daftar stand, daftar menu |
| `POST /keranjang/tambah`, `GET /keranjang`, `POST /keranjang/ubah` | Keranjang |
| `GET, POST /opsi` | Pilih Dine in / Take Away |
| `GET, POST /bayar` | Pembayaran dan pembuatan pesanan |
| `GET /qris`, `POST /qris/bayar` | Pembayaran QRIS |
| `GET /status`, `GET /riwayat` | Status dan riwayat pesanan |
| `GET /profil`, `POST /profil/ubah`, `POST /profil/avatar`, `POST /profil/password` | Profil |

**Penjual**

| Alamat | Fungsi |
|---|---|
| `GET /penjual` | Beranda |
| `GET /penjual/pesanan`, `GET /penjual/pesanan/detail`, `GET /penjual/riwayat` | Pesanan dan riwayat |
| `POST /penjual/status`, `POST /penjual/buka` | Ubah status pesanan, buka/tutup stand |
| `GET /penjual/menu`, `GET /penjual/menu/edit` | Kelola menu |
| `POST /penjual/menu/tambah`, `/simpan`, `/hapus`, `/toggle` | Aksi menu |
| `GET /penjual/profil`, `POST /penjual/profil/ubah`, `/avatar`, `/qris`, `/notifikasi` | Profil penjual |

**Admin**

| Alamat | Fungsi |
|---|---|
| `GET, POST /admin/masuk` | Login admin |
| `GET /admin` | Dashboard |
| `GET /admin/pengguna`, `GET /admin/pengguna/tambah`, `POST /admin/pengguna/simpan`, `POST /admin/pengguna/status` | Pengguna |
| `GET /admin/stand`, `POST /admin/stand/buka`, `/tambah`, `/penjual`, `/reset` | Stand dan akun penjual |
| `GET /admin/kategori`, `POST /admin/kategori/tambah`, `/hapus` | Kategori |
| `GET /admin/menu`, `GET /admin/pesanan` | Daftar menu dan pesanan |
| `GET /admin/laporan`, `GET /admin/laporan/ekspor` | Laporan dan ekspor |

---

## 8. Keamanan yang sudah ada di kode

- Password disimpan dalam bentuk hash (`password_hash`, diperiksa dengan `password_verify`).
- Cookie sesi bernama `pico_sid`: `HttpOnly`, `SameSite=Lax`, dan `Secure` saat memakai HTTPS.
- Semua teks yang ditampilkan di halaman dilewatkan fungsi `e()` (mencegah penyisipan skrip).
- Form POST dari situs lain ditolak oleh `check_origin()`.
- Unggahan foto/KTM dibatasi 2 MB dan jenis file diperiksa dengan `finfo`.
- Akun yang dinonaktifkan admin tidak bisa login (hash password diberi awalan `!`).
- Harga dan stok selalu dihitung ulang di server saat pesanan dibuat, bukan dari data keranjang di browser.

---

## 9. Pemecahan masalah

| Gejala | Kemungkinan penyebab dan solusi |
|---|---|
| Halaman error soal koneksi database | Variabel `DB_*` belum benar atau database tidak bisa dijangkau. Periksa `.env` / `config.server.php`; ekstensi `pdo_pgsql` harus aktif |
| Tampilan polos tanpa warna | File CSS tidak terlayani. Pastikan `public/` ikut ter-deploy dan `.htaccess` aktif (atau jalankan lewat `router.php`) |
| Foto tidak tampil | Foto disajikan dari database lewat `/foto`. Periksa kolom `foto_data` dan `foto_mime`, dan pastikan file di bawah 2 MB |
| Login selalu gagal | Akun mungkin nonaktif (hash diawali `!`) atau password belum diganti dari akun contoh. Cek tabel `profiles` |
| Pesan error terlalu teknis di server | `APP_DEBUG` masih `1`. Ubah ke `0` |

---

## 10. Catatan penutup

- `.gitignore` saat ini mengabaikan `.vercel`, `node_modules`, `vendor`, `.DS_Store`, dan `PICO HEBAT MANTAP.txt`. Tambahkan `.env`.
- README ini disusun dari pembacaan kode terbaru. Jika rute atau struktur database berubah, perbarui bagian 5 dan 7.
