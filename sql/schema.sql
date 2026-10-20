-- =====================================================================
-- PICO : skema PostgreSQL murni (tanpa Supabase)
-- Impor SEKALI lewat Nivor > Databases > db_pico > Import schema.sql.
-- Butuh PostgreSQL 13+ (gen_random_uuid bawaan).
-- =====================================================================

-- Siapa yang sedang login? PHP mengisi "app.uid" tiap request (lihat pdo() di boot.php).
-- Fungsi ini menggantikan auth.uid() milik Supabase.
create function app_uid() returns uuid language sql stable as
$$ select nullif(current_setting('app.uid', true), '')::uuid $$;

create table profiles (
  id uuid primary key default gen_random_uuid(),
  email text unique,
  password_hash text,
  role text not null default 'pelanggan' check (role in ('pelanggan','penjual','admin')),
  nama text not null, username text unique, no_hp text, nim text,
  created_at timestamptz default now()
);
create table stands (
  id bigint generated always as identity primary key,
  nama text not null, kios int, lokasi text, foto_url text,
  penjual_id uuid references profiles(id) on delete set null, buka boolean not null default true
);
create table kategori (id bigint generated always as identity primary key, nama text unique not null);
create table menu (
  id bigint generated always as identity primary key,
  stand_id bigint not null references stands on delete cascade,
  kategori_id bigint references kategori on delete set null,
  nama text not null, harga int not null check (harga >= 0),
  stok int not null default 0 check (stok >= 0), tersedia boolean not null default true, foto_url text
);
create sequence pesanan_kode_seq;
create table pesanan (
  id bigint generated always as identity primary key,
  kode text unique not null,
  pelanggan_id uuid not null references profiles(id),
  stand_id bigint not null references stands,
  layanan text not null check (layanan in ('dine_in','take_away')),
  status text not null default 'menunggu_bayar' check (status in ('menunggu_bayar','baru','diproses','siap','selesai','batal')),
  total int not null, dibayar_at timestamptz, created_at timestamptz default now()
);
create table pesanan_item (
  id bigint generated always as identity primary key,
  pesanan_id bigint not null references pesanan on delete cascade,
  menu_id bigint references menu on delete set null,
  nama text not null, harga int not null, jumlah int not null check (jumlah > 0), catatan text
);

-- Username dibuat otomatis dari email (unik). Menggantikan trigger auth.users milik Supabase.
create function set_username() returns trigger language plpgsql as $$
declare v_user text;
begin
  if new.username is null and new.email is not null then
    v_user := lower(split_part(new.email, '@', 1));
    if exists (select 1 from profiles where username = v_user) then v_user := v_user || substr(new.id::text, 1, 4); end if;
    new.username := v_user;
  end if;
  return new;
end $$;
create trigger profiles_set_username before insert on profiles for each row execute function set_username();

create function is_admin() returns boolean language sql stable as
$$ select exists (select 1 from profiles where id = app_uid() and role = 'admin') $$;
create function owns_stand(sid bigint) returns boolean language sql stable as
$$ select exists (select 1 from stands where id = sid and penjual_id = app_uid()) $$;

-- Login pakai username / NIM / no HP: cari email-nya dulu.
create function login_lookup(p_identifier text) returns text language sql as
$$ select p.email from profiles p
   where p.username = lower(p_identifier) or p.no_hp = p_identifier or p.nim = p_identifier limit 1 $$;

-- Pesanan dibuat di database: harga dihitung dari tabel menu dan stok dikurangi atomik.
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

-- PROTOTIPE: tombol "Saya sudah bayar". Untuk produksi ganti dengan webhook payment gateway.
create function saya_sudah_bayar(p_id bigint) returns void language plpgsql as $$
begin
  update pesanan set status = 'baru', dibayar_at = now()
   where id = p_id and pelanggan_id = app_uid() and status = 'menunggu_bayar' and created_at > now() - interval '15 minutes';
  if not found then raise exception 'Pesanan tidak bisa dibayar (sudah lewat 15 menit atau sudah dibayar)'; end if;
end $$;

-- Pesanan yang tidak dibayar dalam 15 menit dibatalkan dan stoknya dikembalikan.
create function bersihkan_kadaluarsa() returns void language plpgsql as $$
declare r record;
begin
  for r in select id from pesanan where status = 'menunggu_bayar' and created_at < now() - interval '15 minutes' for update skip locked loop
    update menu m set stok = m.stok + i.jumlah from pesanan_item i where i.pesanan_id = r.id and i.menu_id = m.id;
    update pesanan set status = 'batal' where id = r.id;
  end loop;
end $$;

-- Penjual mengubah status lewat fungsi ini (alur dijaga). Batal = stok kembali.
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

-- CATATAN: Row Level Security (RLS) milik Supabase sengaja tidak dibawa.
-- Hak akses baca/tulis sekarang dijaga oleh kode PHP (filter per pengguna) dan fungsi di atas.

-- Akun admin pertama (hapus tanda -- lalu isi hash dari perintah php di bawah):
--   php -r "echo password_hash('PASSWORD_ADMIN', PASSWORD_DEFAULT);"
-- insert into profiles (email, password_hash, role, nama) values ('admin@pico.local', 'HASH_DI_SINI', 'admin', 'Admin');

-- =====================================================================
-- PICO : skema PostgreSQL murni (tanpa Supabase)
-- Impor SEKALI lewat Nivor > Databases > db_pico > Import schema.sql.
-- Butuh PostgreSQL 13+ (gen_random_uuid bawaan).
-- =====================================================================

-- Siapa yang sedang login? PHP mengisi "app.uid" tiap request (lihat pdo() di boot.php).
-- Fungsi ini menggantikan auth.uid() milik Supabase.
create function app_uid() returns uuid language sql stable as
$$ select nullif(current_setting('app.uid', true), '')::uuid $$;

create table profiles (
  id uuid primary key default gen_random_uuid(),
  email text unique,
  password_hash text,
  role text not null default 'pelanggan' check (role in ('pelanggan','penjual','admin')),
  nama text not null, username text unique, no_hp text, nim text,
  created_at timestamptz default now()
);
create table stands (
  id bigint generated always as identity primary key,
  nama text not null, kios int, lokasi text, foto_url text,
  penjual_id uuid references profiles(id) on delete set null, buka boolean not null default true
);
create table kategori (id bigint generated always as identity primary key, nama text unique not null);
create table menu (
  id bigint generated always as identity primary key,
  stand_id bigint not null references stands on delete cascade,
  kategori_id bigint references kategori on delete set null,
  nama text not null, harga int not null check (harga >= 0),
  stok int not null default 0 check (stok >= 0), tersedia boolean not null default true, foto_url text
);
create sequence pesanan_kode_seq;
create table pesanan (
  id bigint generated always as identity primary key,
  kode text unique not null,
  pelanggan_id uuid not null references profiles(id),
  stand_id bigint not null references stands,
  layanan text not null check (layanan in ('dine_in','take_away')),
  status text not null default 'menunggu_bayar' check (status in ('menunggu_bayar','baru','diproses','siap','selesai','batal')),
  total int not null, dibayar_at timestamptz, created_at timestamptz default now()
);
create table pesanan_item (
  id bigint generated always as identity primary key,
  pesanan_id bigint not null references pesanan on delete cascade,
  menu_id bigint references menu on delete set null,
  nama text not null, harga int not null, jumlah int not null check (jumlah > 0), catatan text
);

-- Username dibuat otomatis dari email (unik). Menggantikan trigger auth.users milik Supabase.
create function set_username() returns trigger language plpgsql as $$
declare v_user text;
begin
  if new.username is null and new.email is not null then
    v_user := lower(split_part(new.email, '@', 1));
    if exists (select 1 from profiles where username = v_user) then v_user := v_user || substr(new.id::text, 1, 4); end if;
    new.username := v_user;
  end if;
  return new;
end $$;
create trigger profiles_set_username before insert on profiles for each row execute function set_username();

create function is_admin() returns boolean language sql stable as
$$ select exists (select 1 from profiles where id = app_uid() and role = 'admin') $$;
create function owns_stand(sid bigint) returns boolean language sql stable as
$$ select exists (select 1 from stands where id = sid and penjual_id = app_uid()) $$;

-- Login pakai username / NIM / no HP: cari email-nya dulu.
create function login_lookup(p_identifier text) returns text language sql as
$$ select p.email from profiles p
   where p.username = lower(p_identifier) or p.no_hp = p_identifier or p.nim = p_identifier limit 1 $$;

-- Pesanan dibuat di database: harga dihitung dari tabel menu dan stok dikurangi atomik.
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

-- PROTOTIPE: tombol "Saya sudah bayar". Untuk produksi ganti dengan webhook payment gateway.
create function saya_sudah_bayar(p_id bigint) returns void language plpgsql as $$
begin
  update pesanan set status = 'baru', dibayar_at = now()
   where id = p_id and pelanggan_id = app_uid() and status = 'menunggu_bayar' and created_at > now() - interval '15 minutes';
  if not found then raise exception 'Pesanan tidak bisa dibayar (sudah lewat 15 menit atau sudah dibayar)'; end if;
end $$;

-- Pesanan yang tidak dibayar dalam 15 menit dibatalkan dan stoknya dikembalikan.
create function bersihkan_kadaluarsa() returns void language plpgsql as $$
declare r record;
begin
  for r in select id from pesanan where status = 'menunggu_bayar' and created_at < now() - interval '15 minutes' for update skip locked loop
    update menu m set stok = m.stok + i.jumlah from pesanan_item i where i.pesanan_id = r.id and i.menu_id = m.id;
    update pesanan set status = 'batal' where id = r.id;
  end loop;
end $$;

-- Penjual mengubah status lewat fungsi ini (alur dijaga). Batal = stok kembali.
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

-- CATATAN: Row Level Security (RLS) milik Supabase sengaja tidak dibawa.
-- Hak akses baca/tulis sekarang dijaga oleh kode PHP (filter per pengguna) dan fungsi di atas.

-- Akun admin pertama (hapus tanda -- lalu isi hash dari perintah php di bawah):
--   php -r "echo password_hash('PASSWORD_ADMIN', PASSWORD_DEFAULT);"
-- insert into profiles (email, password_hash, role, nama) values ('admin@pico.local', 'HASH_DI_SINI', 'admin', 'Admin');


-- =====================================================================
-- Data contoh (dari 02_seed.sql). Jalankan hanya SEKALI.
-- =====================================================================
insert into kategori (nama) values ('Makanan'), ('Minuman'), ('Snack');
insert into stands (nama, kios, buka) values ('Kedai Bu Ima', 1, true), ('Naqyka', 3, true), ('Lovemie', 5, false), ('Bakso Pak Di', 6, true);
insert into menu (stand_id, kategori_id, nama, harga, stok)
select s.id, k.id, v.nama, v.harga, v.stok
from (values
  ('Kedai Bu Ima','Minuman','Es teh',5000,12), ('Kedai Bu Ima','Minuman','Kopi',10000,8), ('Kedai Bu Ima','Snack','Snack',3000,20),
  ('Kedai Bu Ima','Snack','Chiki',3000,0), ('Kedai Bu Ima','Snack','Coklat',15000,6), ('Kedai Bu Ima','Minuman','Susu Jahe',6000,9),
  ('Kedai Bu Ima','Makanan','Soto Ayam',12000,10), ('Kedai Bu Ima','Makanan','Nasi Goreng',12000,6),
  ('Naqyka','Makanan','Nasi Kuning',10000,15), ('Bakso Pak Di','Makanan','Bakso',15000,10)
) as v(stand, kat, nama, harga, stok)
join stands s on s.nama = v.stand join kategori k on k.nama = v.kat;

