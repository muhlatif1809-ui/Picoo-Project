-- =====================================================================
-- PICO : skema database Supabase (v2)
-- Jalankan SEKALI di Supabase > SQL Editor > New query > Run.
-- Perbaikan dari usulan awal: lihat catatan "v2" di bawah.
-- =====================================================================
create table profiles (
  id uuid primary key references auth.users on delete cascade,
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
  menu_id bigint references menu on delete set null,   -- v2: menu boleh dihapus tanpa merusak riwayat
  nama text not null, harga int not null, jumlah int not null check (jumlah > 0), catatan text
);

-- Profil otomatis saat daftar. Role SELALU pelanggan.
-- v2: username dibuat unik otomatis (tidak error jika ada email dengan nama depan sama).
create function handle_new_user() returns trigger language plpgsql security definer set search_path = public as $$
declare v_user text := lower(split_part(new.email,'@',1));
begin
  if exists (select 1 from profiles where username = v_user) then v_user := v_user || substr(new.id::text,1,4); end if;
  insert into profiles (id, nama, no_hp, username)
  values (new.id, coalesce(new.raw_user_meta_data->>'nama', 'Pengguna'), new.raw_user_meta_data->>'no_hp', v_user);
  return new;
end $$;
create trigger on_auth_user_created after insert on auth.users for each row execute function handle_new_user();

create function is_admin() returns boolean language sql stable security definer set search_path = public as
$$ select exists (select 1 from profiles where id = auth.uid() and role = 'admin') $$;
create function owns_stand(sid bigint) returns boolean language sql stable security definer set search_path = public as
$$ select exists (select 1 from stands where id = sid and penjual_id = auth.uid()) $$;

-- Login pakai username / NIM / no HP: cari email-nya dulu.
-- v2: HANYA bisa dipanggil server PHP (service_role), tidak lagi terbuka untuk publik.
create function login_lookup(p_identifier text) returns text language sql security definer set search_path = public as
$$ select u.email::text from profiles p join auth.users u on u.id = p.id
   where p.username = lower(p_identifier) or p.no_hp = p_identifier or p.nim = p_identifier limit 1 $$;
revoke execute on function login_lookup(text) from public, anon, authenticated;
grant execute on function login_lookup(text) to service_role;

-- Pesanan dibuat di server: harga dihitung dari tabel menu dan stok dikurangi atomik.
-- v2: kode pesanan tidak terpotong setelah nomor 999.
create function buat_pesanan(p_stand bigint, p_layanan text, p_items jsonb) returns jsonb language plpgsql security definer set search_path = public as $$
declare v_total int := 0; v_id bigint; v_kode text; v_n bigint; it jsonb; m menu%rowtype;
begin
  if auth.uid() is null then raise exception 'Belum login'; end if;
  if not exists (select 1 from stands where id = p_stand and buka) then raise exception 'Stand sedang tutup'; end if;
  v_n := nextval('pesanan_kode_seq');
  v_kode := 'A-' || case when v_n < 1000 then lpad(v_n::text, 3, '0') else v_n::text end;
  insert into pesanan (kode, pelanggan_id, stand_id, layanan, total) values (v_kode, auth.uid(), p_stand, p_layanan, 0) returning id into v_id;
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
grant execute on function buat_pesanan(bigint, text, jsonb) to authenticated;

-- PROTOTIPE: tombol "Saya sudah bayar". Untuk produksi ganti dengan webhook payment gateway (QRIS dinamis).
-- v2: ditolak jika sudah lewat 15 menit.
create function saya_sudah_bayar(p_id bigint) returns void language plpgsql security definer set search_path = public as $$
begin
  update pesanan set status = 'baru', dibayar_at = now()
   where id = p_id and pelanggan_id = auth.uid() and status = 'menunggu_bayar' and created_at > now() - interval '15 minutes';
  if not found then raise exception 'Pesanan tidak bisa dibayar (sudah lewat 15 menit atau sudah dibayar)'; end if;
end $$;
grant execute on function saya_sudah_bayar(bigint) to authenticated;

-- v2: pesanan yang tidak dibayar dalam 15 menit dibatalkan dan stoknya dikembalikan.
create function bersihkan_kadaluarsa() returns void language plpgsql security definer set search_path = public as $$
declare r record;
begin
  for r in select id from pesanan where status = 'menunggu_bayar' and created_at < now() - interval '15 minutes' for update skip locked loop
    update menu m set stok = m.stok + i.jumlah from pesanan_item i where i.pesanan_id = r.id and i.menu_id = m.id;
    update pesanan set status = 'batal' where id = r.id;
  end loop;
end $$;
grant execute on function bersihkan_kadaluarsa() to authenticated;

-- v2: penjual mengubah status lewat fungsi ini (alur dijaga, total tidak bisa diubah). Batal = stok kembali.
create function ubah_status(p_id bigint, p_status text) returns void language plpgsql security definer set search_path = public as $$
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
grant execute on function ubah_status(bigint, text) to authenticated;

-- Row Level Security: database sendiri yang menolak akses yang bukan haknya.
alter table profiles enable row level security; alter table stands enable row level security; alter table kategori enable row level security;
alter table menu enable row level security; alter table pesanan enable row level security; alter table pesanan_item enable row level security;
create policy "profil sendiri/admin" on profiles for select using (id = auth.uid() or is_admin());
create policy "admin kelola profil" on profiles for all using (is_admin()) with check (is_admin());
create policy "stand terbaca" on stands for select to authenticated using (true);
create policy "penjual ubah standnya" on stands for update using (penjual_id = auth.uid()) with check (penjual_id = auth.uid());
create policy "admin kelola stand" on stands for all using (is_admin()) with check (is_admin());
create policy "kategori terbaca" on kategori for select to authenticated using (true);
create policy "admin kelola kategori" on kategori for all using (is_admin()) with check (is_admin());
create policy "menu terbaca" on menu for select to authenticated using (true);
create policy "penjual kelola menunya" on menu for all using (owns_stand(stand_id)) with check (owns_stand(stand_id));
create policy "admin kelola menu" on menu for all using (is_admin()) with check (is_admin());
create policy "lihat pesanan" on pesanan for select using (pelanggan_id = auth.uid() or owns_stand(stand_id) or is_admin());
-- (v2: tidak ada policy UPDATE pesanan untuk penjual; semua lewat ubah_status)
create policy "lihat item pesanan" on pesanan_item for select using (exists (select 1 from pesanan p where p.id = pesanan_id and (p.pelanggan_id = auth.uid() or owns_stand(p.stand_id) or is_admin())));
