-- =====================================================================
-- PICO : skema PostgreSQL lengkap (versi gabungan)
-- Dasar: schema.sql milik Anda (satu salinan, tanpa duplikat)
-- + semua query yang sudah dijalankan (akun penjual, admin)
-- + QR per stand, pesanan banyak stand, dan view.
--
-- UNTUK DATABASE KOSONG. Jalankan SEKALI.
-- Untuk database yang sudah berisi data, pakai pico_semua_query.sql.
-- Butuh PostgreSQL 13+.
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ---------------------------------------------------------------------
-- 1. IDENTITAS PENGGUNA
-- PHP mengisi "app.uid" tiap request (lihat pdo() di boot.php).
-- ---------------------------------------------------------------------
create function app_uid() returns uuid language sql stable as
$$ select nullif(current_setting('app.uid', true), '')::uuid $$;

-- ---------------------------------------------------------------------
-- 2. TABEL
-- ---------------------------------------------------------------------
create table profiles (
  id uuid primary key default gen_random_uuid(),
  email text unique,
  password_hash text,
  role text not null default 'pelanggan' check (role in ('pelanggan','penjual','admin')),
  nama text not null, username text unique, no_hp text, nim text,
  last_login_at timestamptz,
  login_count int not null default 0,
  created_at timestamptz default now()
);

create table stands (
  id bigint generated always as identity primary key,
  nama text not null, kios int, lokasi text, foto_url text,
  qr_url text,   -- QR pembayaran milik stand
  penjual_id uuid references profiles(id) on delete set null,
  buka boolean not null default true
);

create table kategori (
  id bigint generated always as identity primary key,
  nama text unique not null
);

create table menu (
  id bigint generated always as identity primary key,
  stand_id bigint not null references stands on delete cascade,
  kategori_id bigint references kategori on delete set null,
  nama text not null, harga int not null check (harga >= 0),
  stok int not null default 0 check (stok >= 0),
  tersedia boolean not null default true, foto_url text
);

create sequence pesanan_kode_seq;

create table pesanan (
  id bigint generated always as identity primary key,
  kode text unique not null,
  pelanggan_id uuid not null references profiles(id),
  stand_id bigint not null references stands,
  layanan text not null check (layanan in ('dine_in','take_away')),
  status text not null default 'menunggu_bayar'
    check (status in ('menunggu_bayar','baru','diproses','siap','selesai','batal')),
  total int not null, dibayar_at timestamptz, created_at timestamptz default now()
);

create table pesanan_item (
  id bigint generated always as identity primary key,
  pesanan_id bigint not null references pesanan on delete cascade,
  menu_id bigint references menu on delete set null,
  nama text not null, harga int not null,
  jumlah int not null check (jumlah > 0), catatan text
);

-- Riwayat login (berhasil maupun gagal)
create table login_log (
  id bigint generated always as identity primary key,
  profile_id uuid references profiles(id) on delete cascade,
  identifier text not null,
  berhasil boolean not null,
  ip_address text,
  user_agent text,
  created_at timestamptz not null default now()
);
create index idx_login_log_profile on login_log(profile_id, created_at desc);

-- ---------------------------------------------------------------------
-- 3. TRIGGER USERNAME
-- ---------------------------------------------------------------------
create function set_username() returns trigger language plpgsql as $$
declare v_user text;
begin
  if new.username is null and new.email is not null then
    v_user := lower(split_part(new.email, '@', 1));
    if exists (select 1 from profiles where username = v_user) then
      v_user := v_user || substr(new.id::text, 1, 4);
    end if;
    new.username := v_user;
  end if;
  return new;
end $$;

create trigger profiles_set_username
  before insert on profiles for each row execute function set_username();

-- ---------------------------------------------------------------------
-- 4. HAK AKSES & LOGIN
-- ---------------------------------------------------------------------
create function is_admin() returns boolean language sql stable as
$$ select exists (select 1 from profiles where id = app_uid() and role = 'admin') $$;

create function owns_stand(sid bigint) returns boolean language sql stable as
$$ select exists (select 1 from stands where id = sid and penjual_id = app_uid()) $$;

-- Login pakai username / NIM / no HP: cari email-nya dulu.
-- (Akun harus punya email agar ditemukan.)
create function login_lookup(p_identifier text) returns text language sql as
$$ select p.email from profiles p
   where p.username = lower(p_identifier) or p.no_hp = p_identifier or p.nim = p_identifier limit 1 $$;

-- Login penuh di database: cocokkan password, catat ke login_log.
-- Mengembalikan profil (jsonb) jika berhasil, NULL jika gagal.
create function login_user(p_identifier text, p_password text, p_ip text default null, p_user_agent text default null)
returns jsonb language plpgsql as $$
declare p profiles%rowtype;
begin
  select * into p from profiles
   where lower(username) = lower(trim(p_identifier))
      or lower(email) = lower(trim(p_identifier))
      or no_hp = trim(p_identifier)
      or nim = trim(p_identifier)
   limit 1;

  if not found or p.password_hash is null or p.password_hash <> crypt(p_password, p.password_hash) then
    insert into login_log (profile_id, identifier, berhasil, ip_address, user_agent)
    values (case when found then p.id else null end, trim(p_identifier), false, p_ip, p_user_agent);
    return null;
  end if;

  update profiles set last_login_at = now(), login_count = login_count + 1 where id = p.id;
  insert into login_log (profile_id, identifier, berhasil, ip_address, user_agent)
  values (p.id, trim(p_identifier), true, p_ip, p_user_agent);

  return jsonb_build_object('id', p.id, 'username', p.username, 'nama', p.nama, 'role', p.role);
end $$;

-- ---------------------------------------------------------------------
-- 5. PESANAN
-- ---------------------------------------------------------------------

-- Pesanan satu stand: harga dari tabel menu, stok dikurangi atomik.
create function buat_pesanan(p_stand bigint, p_layanan text, p_items jsonb) returns jsonb language plpgsql as $$
declare v_total int := 0; v_id bigint; v_kode text; v_n bigint; it jsonb; m menu%rowtype;
begin
  if app_uid() is null then raise exception 'Belum login'; end if;
  if not exists (select 1 from stands where id = p_stand and buka) then raise exception 'Stand sedang tutup'; end if;
  v_n := nextval('pesanan_kode_seq');
  v_kode := 'A-' || case when v_n < 1000 then lpad(v_n::text, 3, '0') else v_n::text end;
  insert into pesanan (kode, pelanggan_id, stand_id, layanan, total) values (v_kode, app_uid(), p_stand, p_layanan, 0) returning id into v_id;
  for it in select * from jsonb_array_elements(p_items) loop
    update menu set stok = stok - (it->>'jumlah')::int
      where id = (it->>'menu_id')::bigint and stand_id = p_stand and tersedia and stok >= (it->>'jumlah')::int returning * into m;
    if not found then raise exception 'Menu habis atau tidak tersedia'; end if;
    insert into pesanan_item (pesanan_id, menu_id, nama, harga, jumlah, catatan) values (v_id, m.id, m.nama, m.harga, (it->>'jumlah')::int, it->>'catatan');
    v_total := v_total + m.harga * (it->>'jumlah')::int;
  end loop;
  update pesanan set total = v_total where id = v_id;
  return jsonb_build_object('id', v_id, 'kode', v_kode, 'total', v_total);
end $$;

-- Keranjang berisi menu dari banyak stand -> satu pesanan per stand,
-- semuanya satu transaksi (gagal satu = batal semua).
create function buat_pesanan_multi(p_layanan text, p_items jsonb) returns jsonb language plpgsql as $$
declare r record; it jsonb; v_hasil jsonb := '[]'::jsonb;
begin
  if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
    raise exception 'Keranjang masih kosong';
  end if;
  for it in select * from jsonb_array_elements(p_items) loop
    if not (it ? 'menu_id') or jsonb_typeof(it->'menu_id') <> 'number' then
      raise exception 'ID menu tidak valid';
    end if;
    if not exists (select 1 from menu where id = (it->>'menu_id')::bigint) then
      raise exception 'Menu tidak ditemukan';
    end if;
  end loop;
  for r in
    select m.stand_id, jsonb_agg(x.it) as items
    from jsonb_array_elements(p_items) as x(it)
    join menu m on m.id = (x.it->>'menu_id')::bigint
    group by m.stand_id order by m.stand_id
  loop
    v_hasil := v_hasil || buat_pesanan(r.stand_id, p_layanan, r.items);
  end loop;
  return v_hasil;
end $$;

-- PROTOTIPE: tombol "Saya sudah bayar". Untuk produksi ganti dengan
-- webhook payment gateway atau konfirmasi penjual.
create function saya_sudah_bayar(p_id bigint) returns void language plpgsql as $$
begin
  update pesanan set status = 'baru', dibayar_at = now()
   where id = p_id and pelanggan_id = app_uid() and status = 'menunggu_bayar' and created_at > now() - interval '15 minutes';
  if not found then raise exception 'Pesanan tidak bisa dibayar (sudah lewat 15 menit atau sudah dibayar)'; end if;
end $$;

-- Pesanan tidak dibayar 15 menit -> batal, stok kembali.
create function bersihkan_kadaluarsa() returns void language plpgsql as $$
declare r record;
begin
  for r in select id from pesanan where status = 'menunggu_bayar' and created_at < now() - interval '15 minutes' for update skip locked loop
    update menu m set stok = m.stok + i.jumlah from pesanan_item i where i.pesanan_id = r.id and i.menu_id = m.id;
    update pesanan set status = 'batal' where id = r.id;
  end loop;
end $$;

-- Penjual mengubah status (alur dijaga). Batal = stok kembali.
create function ubah_status(p_id bigint, p_status text) returns void language plpgsql as $$
declare p pesanan%rowtype; ok boolean;
begin
  select * into p from pesanan where id = p_id for update;
  if not found or not owns_stand(p.stand_id) then raise exception 'Pesanan tidak ditemukan'; end if;
  ok := (p.status = 'baru' and p_status in ('diproses','batal')) or (p.status = 'diproses' and p_status in ('siap','batal')) or (p.status = 'siap' and p_status = 'selesai');
  if not ok then raise exception 'Perubahan status tidak valid'; end if;
  if p_status = 'batal' then
    update menu m set stok = m.stok + i.jumlah from pesanan_item i where i.pesanan_id = p.id and i.menu_id = m.id;
  end if;
  update pesanan set status = p_status where id = p.id;
end $$;

-- Penjual mengganti QR stand miliknya
create function ubah_qr_stand(p_stand bigint, p_qr_url text) returns void language plpgsql as $$
begin
  if not owns_stand(p_stand) then raise exception 'Anda bukan pemilik stand ini'; end if;
  update stands set qr_url = p_qr_url where id = p_stand;
end $$;

-- ---------------------------------------------------------------------
-- 6. VIEW
-- ---------------------------------------------------------------------

-- Katalog menu: menu + stand + kategori (halaman pembeli)
create view v_menu_stand as
select m.id as menu_id, m.nama as menu, m.harga, m.stok, m.tersedia,
       m.foto_url, s.id as stand_id, s.nama as stand, s.kios, s.buka,
       k.nama as kategori
from menu m
join stands s on s.id = m.stand_id
left join kategori k on k.id = m.kategori_id;

-- Antrean pesanan penjual (otomatis hanya stand miliknya)
create view v_antrean_penjual as
select p.id, p.kode, p.stand_id, p.layanan, p.status, p.total,
       p.created_at, pr.nama as nama_pembeli,
       (select jsonb_agg(jsonb_build_object('nama', i.nama, 'jumlah', i.jumlah, 'catatan', i.catatan))
        from pesanan_item i where i.pesanan_id = p.id) as items
from pesanan p
join profiles pr on pr.id = p.pelanggan_id
where owns_stand(p.stand_id);

-- Riwayat pesanan pembeli (otomatis hanya milik yang login)
create view v_riwayat_pembeli as
select p.id, p.kode, s.nama as stand, p.layanan, p.status,
       p.total, p.created_at,
       (select string_agg(i.jumlah::text || ' ' || i.nama, ', ' order by i.id)
        from pesanan_item i where i.pesanan_id = p.id) as menu_ringkas
from pesanan p
join stands s on s.id = p.stand_id
where p.pelanggan_id = app_uid();

-- Rekap penjualan per stand (dashboard admin)
create view v_rekap_penjualan_stand as
select s.id as stand_id, s.nama as stand, s.kios,
       count(p.id) as jumlah_pesanan,
       coalesce(sum(p.total), 0) as pendapatan
from stands s
left join pesanan p on p.stand_id = s.id and p.status in ('baru','diproses','siap','selesai')
group by s.id, s.nama, s.kios;

-- Daftar pengguna tanpa password_hash (halaman admin)
create view v_pengguna as
select id, nama, username, no_hp, role, last_login_at, login_count, created_at
from profiles;

-- ---------------------------------------------------------------------
-- 7. DATA CONTOH (jalankan hanya sekali)
-- ---------------------------------------------------------------------
insert into kategori (nama) values ('Makanan'), ('Minuman'), ('Snack');

insert into stands (nama, kios, buka) values
  ('Kedai Bu Ima', 1, true), ('Naqyka', 3, true),
  ('Lovemie', 5, false), ('Bakso Pak Di', 6, true);

insert into menu (stand_id, kategori_id, nama, harga, stok)
select s.id, k.id, v.nama, v.harga, v.stok
from (values
  ('Kedai Bu Ima','Minuman','Es teh',5000,12), ('Kedai Bu Ima','Minuman','Kopi',10000,8), ('Kedai Bu Ima','Snack','Snack',3000,20),
  ('Kedai Bu Ima','Snack','Chiki',3000,0), ('Kedai Bu Ima','Snack','Coklat',15000,6), ('Kedai Bu Ima','Minuman','Susu Jahe',6000,9),
  ('Kedai Bu Ima','Makanan','Soto Ayam',12000,10), ('Kedai Bu Ima','Makanan','Nasi Goreng',12000,6),
  ('Naqyka','Makanan','Nasi Kuning',10000,15), ('Bakso Pak Di','Makanan','Bakso',15000,10)
) as v(stand, kat, nama, harga, stok)
join stands s on s.nama = v.stand join kategori k on k.nama = v.kat;

-- ---------------------------------------------------------------------
-- 8. AKUN (sama seperti yang sudah Anda jalankan)
-- GANTI password sebelum aplikasi dipakai sungguhan.
-- Email diisi agar login_lookup bisa menemukan akun.
-- ---------------------------------------------------------------------
insert into profiles (username, email, password_hash, role, nama) values
  ('bu_ima', 'bu_ima@pico.local', crypt('passwordnya123', gen_salt('bf')), 'penjual', 'Bu Ima'),
  ('naqyka', 'naqyka@pico.local', crypt('passwordnaqyka123', gen_salt('bf')), 'penjual', 'Penjual Naqyka'),
  ('admin',  'admin@pico.local',  crypt('adminnyaganteng', gen_salt('bf')), 'admin', 'Admin PICO');

-- Hubungkan penjual ke stand
update stands set penjual_id = (select id from profiles where username = 'bu_ima' and role = 'penjual')
 where nama = 'Kedai Bu Ima';
update stands set penjual_id = (select id from profiles where username = 'naqyka' and role = 'penjual')
 where nama = 'Naqyka';

-- ---------------------------------------------------------------------
-- 9. PENGECEKAN
-- ---------------------------------------------------------------------
-- select p.username, p.role, s.nama as stand from profiles p left join stands s on s.penjual_id = p.id;
-- select * from v_menu_stand;
-- select * from v_rekap_penjualan_stand;
-- select * from v_pengguna;