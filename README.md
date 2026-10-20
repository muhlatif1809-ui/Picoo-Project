# PICO (PHP) – Polinema Integrated Canteen Order

Aplikasi pemesanan Kantin AX, ditulis dengan **PHP murni** (tanpa Composer) dan terhubung ke **Supabase**.
Ada 3 peran dalam satu aplikasi: **pelanggan**, **penjual**, dan **admin**.

```
Browser  ──►  PHP (Vercel)  ──►  Supabase (database + login)
```

Browser tidak pernah memegang key rahasia. Semua panggilan ke Supabase dilakukan oleh PHP di server.

---

## 1. Isi folder

| Lokasi | Fungsinya |
|---|---|
| `api/index.php` | Pintu masuk + daftar alamat (route). Mulai membaca dari sini. |
| `api/_app/boot.php` | Koneksi Supabase, login/sesi, keranjang |
| `api/_app/pelanggan.php` | Halaman pelanggan + login/daftar |
| `api/_app/penjual.php` | Halaman penjual |
| `api/_app/admin.php` | Halaman admin |
| `api/_app/ui.php` | Kerangka halaman & navigasi |
| `public/css`, `public/js` | Tampilan & JavaScript kecil |
| `sql/01_schema.sql`, `sql/02_seed.sql` | Database (dijalankan di Supabase) |
| `vercel.json` | Pengaturan Vercel |
| `.env.example` | Contoh isi pengaturan rahasia |

---

## 2. Siapkan Supabase (bersama temanmu yang pegang backend)

1. Buka project Supabase → **SQL Editor → New query**.
2. Tempel isi `sql/01_schema.sql` → **Run**. Lalu `sql/02_seed.sql` → **Run** (data contoh stand & menu).
3. **Authentication → Sign In / Providers → Email**: matikan **Confirm email** (supaya pendaftaran langsung bisa dipakai saat uji coba).
4. Ambil 3 nilai ini dari **Project Settings → API** (atau *API Keys*):
   - **Project URL**
   - **anon key** (pada dashboard baru: *publishable key*)
   - **service_role key** (pada dashboard baru: *secret key*) — **RAHASIA**, jangan dibagikan atau di-upload ke GitHub.
5. Buat **admin pertama**: buka aplikasi → **Saya Pembeli** → **Daftar**, buat akunmu, lalu di SQL Editor jalankan (ganti username-nya):
   ```sql
   update profiles set role = 'admin' where username = 'usernamemu';
   ```
6. Setelah itu akun penjual dibuat dari halaman admin (*Stand & Penjual → Buat akun penjual*), tidak perlu SQL lagi.

> Kalau database temanmu sudah punya tabel sendiri yang berbeda, hanya teks query di `api/_app/*.php` yang perlu disesuaikan (nama tabel/kolom).

---

## 3. Coba di komputer (opsional tapi disarankan)

1. Install PHP 8.1+ (Windows: xampp atau php.net; Mac: `brew install php`).
2. Salin `.env.example` menjadi `.env`, isi 3 nilai dari langkah 2.
3. Di terminal VS Code (Terminal → New Terminal):
   ```
   php -S localhost:8000 router.php
   ```
4. Buka http://localhost:8000

---

## 4. Deploy: GitHub → Vercel

1. Buat repository kosong di GitHub, lalu di terminal VS Code:
   ```
   git init
   git add .
   git commit -m "PICO versi PHP"
   git branch -M main
   git remote add origin https://github.com/USERNAME/NAMA-REPO.git
   git push -u origin main
   ```
   (File `.env` otomatis tidak ikut ter-upload karena ada di `.gitignore`.)
2. Buka vercel.com → **Add New → Project** → pilih repository tadi → **Import**.
3. Sebelum menekan Deploy, buka **Environment Variables**, tambahkan:
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
   - `SUPABASE_SERVICE_ROLE_KEY`
4. Klik **Deploy**. Selesai, Vercel memberi alamat `https://nama-proyek.vercel.app`.

Vercel tidak mendukung PHP bawaan, jadi proyek ini memakai runtime komunitas `vercel-php` (diatur di `vercel.json`).

**Jika ada masalah di Vercel**
- Tampilan polos (CSS tidak muncul): di Vercel → Settings → Build & Output → isi **Output Directory** dengan `public`, lalu Redeploy.
- Halaman error tentang runtime/PHP: cek log di tab *Deployments → Functions*. Alternatifnya, deploy lewat **Railway** atau **Render**: sudah disediakan `Dockerfile`, cukup hubungkan repository GitHub dan isi 3 Environment Variables yang sama.
- Setelah mengubah Environment Variables, klik **Redeploy**.

---

## 5. Alur & hak akses singkat

**Halaman awal** menampilkan dua pintu: *Saya Pembeli* (ada tombol Daftar) dan *Saya Penjual* (tanpa Daftar, akunnya dibuat admin). Pintu hanya soal tampilan. Setelah login, pengguna selalu dibawa ke halaman sesuai **role di database**: admin ke `/admin` (boleh login lewat pintu mana saja), penjual ke `/penjual`, pembeli ke `/home`. Tidak ada tombol menuju admin.

- **Pelanggan**: daftar/masuk → pilih stand → tambah menu → checkout → QRIS → pantau status → riwayat.
- **Penjual**: lihat pesanan masuk (refresh otomatis) → Terima → Siap → Selesai / Batalkan; kelola menu (harga, stok, foto); buka/tutup stand.
- **Admin**: dashboard penjualan, tambah stand, buat akun penjual & reset password, daftar pengguna, semua pesanan.
- Harga dan stok **selalu dihitung database**, bukan dari browser. Hak akses dijaga ganda: pengecekan peran di PHP + Row Level Security di Supabase.

## 6. Yang masih prototipe

- **QRIS** masih gambar contoh dan tombol "Saya sudah bayar". Untuk produksi, sambungkan payment gateway (Midtrans/Xendit) dan ubah status lewat webhook.
- **Foto menu** memakai link gambar (belum upload ke Supabase Storage).
- Pesanan yang tidak dibayar 15 menit otomatis dibatalkan dan stoknya dikembalikan (dijalankan saat halaman QRIS/Riwayat dibuka).
- Ubah peran menjadi admin hanya lewat SQL (sengaja, demi keamanan).
