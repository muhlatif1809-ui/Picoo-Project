-- ============================================================
-- PICO - GABUNGAN SEMUA QUERY TAMBAHAN
-- Aman dijalankan berulang di database yang sudah ada (db_pico):
-- memakai IF NOT EXISTS, OR REPLACE, dan ON CONFLICT DO NOTHING.
-- Tidak menghapus data dan tidak menduplikasi data contoh.
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================
-- 1. KOLOM TAMBAHAN
-- ============================================================

ALTER TABLE profiles ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ;
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS login_count INT NOT NULL DEFAULT 0;

-- QR pembayaran per stand
ALTER TABLE stands ADD COLUMN IF NOT EXISTS qr_url TEXT;

-- Foto disimpan langsung di database (menu dan stand),
-- supaya tidak hilang saat aplikasi di-deploy ulang.
ALTER TABLE menu   ADD COLUMN IF NOT EXISTS foto_data BYTEA;
ALTER TABLE menu   ADD COLUMN IF NOT EXISTS foto_mime TEXT;
ALTER TABLE stands ADD COLUMN IF NOT EXISTS foto_data BYTEA;
ALTER TABLE stands ADD COLUMN IF NOT EXISTS foto_mime TEXT;

-- Batasi ukuran foto maksimal 2 MB
ALTER TABLE menu DROP CONSTRAINT IF EXISTS menu_foto_ukuran;
ALTER TABLE menu ADD CONSTRAINT menu_foto_ukuran
  CHECK (foto_data IS NULL OR octet_length(foto_data) <= 2097152);

ALTER TABLE stands DROP CONSTRAINT IF EXISTS stands_foto_ukuran;
ALTER TABLE stands ADD CONSTRAINT stands_foto_ukuran
  CHECK (foto_data IS NULL OR octet_length(foto_data) <= 2097152);

-- ============================================================
-- 2. RIWAYAT LOGIN
-- ============================================================

CREATE TABLE IF NOT EXISTS login_log (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    profile_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    identifier TEXT NOT NULL,
    berhasil BOOLEAN NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_log_profile
    ON login_log(profile_id, created_at DESC);

-- ============================================================
-- 3. FUNGSI DAFTAR & LOGIN
-- ============================================================

CREATE OR REPLACE FUNCTION daftar_pelanggan(
    p_nama TEXT,
    p_password TEXT,
    p_email TEXT DEFAULT NULL,
    p_username TEXT DEFAULT NULL,
    p_no_hp TEXT DEFAULT NULL,
    p_nim TEXT DEFAULT NULL
)
RETURNS JSONB
LANGUAGE plpgsql
AS $$
DECLARE
    v_id UUID;
    v_username TEXT;
BEGIN
    IF COALESCE(TRIM(p_nama), '') = '' THEN
        RAISE EXCEPTION 'Nama wajib diisi';
    END IF;

    IF p_password IS NULL OR LENGTH(p_password) < 6 THEN
        RAISE EXCEPTION 'Password minimal 6 karakter';
    END IF;

    IF COALESCE(TRIM(p_email), '') = ''
       AND COALESCE(TRIM(p_username), '') = '' THEN
        RAISE EXCEPTION 'Isi email atau username';
    END IF;

    INSERT INTO profiles (
        email, username, password_hash, role, nama, no_hp, nim
    )
    VALUES (
        NULLIF(LOWER(TRIM(p_email)), ''),
        NULLIF(LOWER(TRIM(p_username)), ''),
        crypt(p_password, gen_salt('bf')),
        'pelanggan',
        TRIM(p_nama),
        NULLIF(TRIM(p_no_hp), ''),
        NULLIF(TRIM(p_nim), '')
    )
    RETURNING id, username INTO v_id, v_username;

    RETURN jsonb_build_object(
        'id', v_id,
        'username', v_username,
        'role', 'pelanggan'
    );
EXCEPTION
    WHEN unique_violation THEN
        RAISE EXCEPTION 'Email atau username sudah terdaftar';
END;
$$;

CREATE OR REPLACE FUNCTION login_user(
    p_identifier TEXT,
    p_password TEXT,
    p_ip TEXT DEFAULT NULL,
    p_user_agent TEXT DEFAULT NULL
)
RETURNS JSONB
LANGUAGE plpgsql
AS $$
DECLARE
    p profiles%ROWTYPE;
BEGIN
    SELECT * INTO p
    FROM profiles
    WHERE LOWER(username) = LOWER(TRIM(p_identifier))
       OR LOWER(email) = LOWER(TRIM(p_identifier))
       OR no_hp = TRIM(p_identifier)
       OR nim = TRIM(p_identifier)
    LIMIT 1;

    IF NOT FOUND
       OR p.password_hash IS NULL
       OR p.password_hash <> crypt(p_password, p.password_hash) THEN

        INSERT INTO login_log (
            profile_id, identifier, berhasil, ip_address, user_agent
        )
        VALUES (
            CASE WHEN FOUND THEN p.id ELSE NULL END,
            TRIM(p_identifier), FALSE, p_ip, p_user_agent
        );

        RETURN NULL;
    END IF;

    UPDATE profiles
    SET last_login_at = NOW(),
        login_count = login_count + 1
    WHERE id = p.id;

    INSERT INTO login_log (
        profile_id, identifier, berhasil, ip_address, user_agent
    )
    VALUES (
        p.id, TRIM(p_identifier), TRUE, p_ip, p_user_agent
    );

    RETURN jsonb_build_object(
        'id', p.id,
        'username', p.username,
        'nama', p.nama,
        'role', p.role
    );
END;
$$;

-- ============================================================
-- 4. FUNGSI QR STAND & PESANAN BANYAK STAND
-- ============================================================

-- Penjual mengganti QR stand miliknya sendiri
CREATE OR REPLACE FUNCTION ubah_qr_stand(p_stand BIGINT, p_qr_url TEXT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT owns_stand(p_stand) THEN
        RAISE EXCEPTION 'Anda bukan pemilik stand ini';
    END IF;

    UPDATE stands SET qr_url = p_qr_url WHERE id = p_stand;
END;
$$;

-- Keranjang berisi menu dari banyak stand -> satu pesanan per stand,
-- semuanya dalam satu transaksi (gagal satu = batal semua).
CREATE OR REPLACE FUNCTION buat_pesanan_multi(
    p_layanan TEXT,
    p_items JSONB
)
RETURNS JSONB
LANGUAGE plpgsql
AS $$
DECLARE
    r RECORD;
    it JSONB;
    v_hasil JSONB := '[]'::JSONB;
BEGIN
    IF p_items IS NULL
       OR jsonb_typeof(p_items) <> 'array'
       OR jsonb_array_length(p_items) = 0 THEN
        RAISE EXCEPTION 'Keranjang masih kosong';
    END IF;

    FOR it IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        IF NOT (it ? 'menu_id')
           OR jsonb_typeof(it->'menu_id') <> 'number' THEN
            RAISE EXCEPTION 'ID menu tidak valid';
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM menu WHERE id = (it->>'menu_id')::BIGINT
        ) THEN
            RAISE EXCEPTION 'Menu tidak ditemukan';
        END IF;
    END LOOP;

    FOR r IN
        SELECT m.stand_id, jsonb_agg(x.it) AS items
        FROM jsonb_array_elements(p_items) AS x(it)
        JOIN menu m ON m.id = (x.it->>'menu_id')::BIGINT
        GROUP BY m.stand_id
        ORDER BY m.stand_id
    LOOP
        v_hasil := v_hasil || buat_pesanan(r.stand_id, p_layanan, r.items);
    END LOOP;

    RETURN v_hasil;
END;
$$;

-- ============================================================
-- 5. VIEW
-- ============================================================

-- 5a. Katalog menu: menu + stand + kategori (halaman pembeli)
CREATE OR REPLACE VIEW v_menu_stand AS
SELECT m.id AS menu_id, m.nama AS menu, m.harga, m.stok, m.tersedia,
       m.foto_url, s.id AS stand_id, s.nama AS stand, s.kios, s.buka,
       k.nama AS kategori
FROM menu m
JOIN stands s ON s.id = m.stand_id
LEFT JOIN kategori k ON k.id = m.kategori_id;

-- 5b. Antrean pesanan penjual (otomatis hanya stand miliknya)
CREATE OR REPLACE VIEW v_antrean_penjual AS
SELECT p.id, p.kode, p.stand_id, p.layanan, p.status, p.total,
       p.created_at, pr.nama AS nama_pembeli,
       (SELECT jsonb_agg(jsonb_build_object(
            'nama', i.nama, 'jumlah', i.jumlah, 'catatan', i.catatan))
        FROM pesanan_item i WHERE i.pesanan_id = p.id) AS items
FROM pesanan p
JOIN profiles pr ON pr.id = p.pelanggan_id
WHERE owns_stand(p.stand_id);

-- 5c. Riwayat pesanan pembeli (otomatis hanya milik yang login)
CREATE OR REPLACE VIEW v_riwayat_pembeli AS
SELECT p.id, p.kode, s.nama AS stand, p.layanan, p.status,
       p.total, p.created_at,
       (SELECT string_agg(i.jumlah::TEXT || ' ' || i.nama, ', ' ORDER BY i.id)
        FROM pesanan_item i WHERE i.pesanan_id = p.id) AS menu_ringkas
FROM pesanan p
JOIN stands s ON s.id = p.stand_id
WHERE p.pelanggan_id = app_uid();

-- 5d. Rekap penjualan per stand (dashboard admin)
CREATE OR REPLACE VIEW v_rekap_penjualan_stand AS
SELECT s.id AS stand_id, s.nama AS stand, s.kios,
       COUNT(p.id) AS jumlah_pesanan,
       COALESCE(SUM(p.total), 0) AS pendapatan
FROM stands s
LEFT JOIN pesanan p
       ON p.stand_id = s.id
      AND p.status IN ('baru', 'diproses', 'siap', 'selesai')
GROUP BY s.id, s.nama, s.kios;

-- 5e. Daftar pengguna tanpa password_hash (halaman admin)
CREATE OR REPLACE VIEW v_pengguna AS
SELECT id, nama, username, no_hp, role,
       last_login_at, login_count, created_at
FROM profiles;

-- ============================================================
-- 6. AKUN PENJUAL & ADMIN
-- ON CONFLICT DO NOTHING: jika username sudah ada, dilewati
-- (password yang sudah Anda ganti tidak ikut tertimpa).
-- GANTI password sebelum aplikasi dipakai sungguhan.
-- ============================================================

-- Hapus baris placeholder sisa template (jika ada)
DELETE FROM profiles WHERE username = 'USERNAME_PENJUAL';

-- 6a. Penjual: Kedai Bu Ima
INSERT INTO profiles (username, email, password_hash, role, nama)
VALUES (
    'bu_ima',
    'bu_ima@pico.local',
    crypt('passwordnya123', gen_salt('bf')),
    'penjual',
    'Bu Ima'
)
ON CONFLICT (username) DO NOTHING;

-- 6b. Penjual: Naqyka
INSERT INTO profiles (username, email, password_hash, role, nama)
VALUES (
    'naqyka',
    'naqyka@pico.local',
    crypt('passwordnaqyka123', gen_salt('bf')),
    'penjual',
    'Penjual Naqyka'
)
ON CONFLICT (username) DO NOTHING;

-- 6c. Admin
INSERT INTO profiles (username, email, password_hash, role, nama)
VALUES (
    'admin',
    'admin@pico.local',
    crypt('ganti_password_admin', gen_salt('bf')),
    'admin',
    'Admin PICO'
)
ON CONFLICT (username) DO NOTHING;

-- Pastikan akun penjual lama yang belum punya email ikut terisi
UPDATE profiles SET email = 'bu_ima@pico.local'
WHERE username = 'bu_ima' AND email IS NULL;

UPDATE profiles SET email = 'naqyka@pico.local'
WHERE username = 'naqyka' AND email IS NULL;

-- ============================================================
-- 7. HUBUNGKAN PENJUAL KE STAND
-- ============================================================

UPDATE stands
SET penjual_id = (
    SELECT id FROM profiles
    WHERE username = 'bu_ima' AND role = 'penjual'
)
WHERE nama = 'Kedai Bu Ima';

UPDATE stands
SET penjual_id = (
    SELECT id FROM profiles
    WHERE username = 'naqyka' AND role = 'penjual'
)
WHERE nama = 'Naqyka';

UPDATE stands
SET penjual_id = (
    SELECT id FROM profiles
    WHERE username = 'perintis' AND role = 'penjual'
)
WHERE nama = 'Toko Jual Beli Barang Bekas';

-- ============================================================
-- 8. PENGECEKAN (jalankan satu per satu jika editor hanya
--    menampilkan hasil perintah terakhir)
-- ============================================================

-- Akun dan stand-nya
SELECT p.username, p.email, p.role, s.nama AS stand
FROM profiles p
LEFT JOIN stands s ON s.penjual_id = p.id
WHERE p.role IN ('penjual', 'admin')
ORDER BY p.role, p.username;

-- Tes login
SELECT login_user('bu_ima', 'passwordnya123');
SELECT login_user('naqyka', 'passwordnaqyka123');
SELECT login_user('perintis','12345678')
SELECT login_user('admin', 'adminnyaganteng');

-- Cek kolom foto (harus muncul 4 baris)
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_name IN ('menu', 'stands')
  AND column_name IN ('foto_data', 'foto_mime')
ORDER BY table_name, column_name;

-- Tes view
SELECT * FROM v_menu_stand;
SELECT * FROM v_rekap_penjualan_stand;
SELECT * FROM v_pengguna;