-- Data contoh agar aplikasi langsung ada isinya. Jalankan SETELAH 01_schema.sql. Boleh diedit/dihapus.
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
