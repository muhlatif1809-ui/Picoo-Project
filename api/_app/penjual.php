<?php
// penjual.php : halaman penjual (pesanan masuk, kelola menu, buka/tutup stand).

/** Stand milik penjual yang login. Kalau belum dihubungkan admin, tampilkan pesan. */
function stand_saya(array $m): array {
    $s = db_get('stands?select=id,nama,kios,buka&penjual_id=eq.' . rawurlencode($m['id']) . '&limit=1')[0] ?? null;
    if (!$s) {
        layout('Belum ada stand', hd('Halo, ' . e($m['nama'])) . '<div class="bd"><div class="note" style="margin:0 0 14px">Akunmu belum dihubungkan ke stand. Minta admin menghubungkannya.</div><a class="btn sec" href="/keluar">Keluar</a></div>');
        exit;
    }
    return $s;
}

function r_pen_home(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $tab = ($_GET['tab'] ?? '') === 'riwayat' ? 'riwayat' : 'aktif';
    $filter = $tab === 'aktif' ? 'status=in.(baru,diproses,siap)&order=created_at.asc' : 'status=in.(selesai,batal)&order=created_at.desc&limit=30';
    $os = db_get('pesanan?select=id,kode,status,layanan,total,created_at,pesanan_item(nama,jumlah,catatan)&stand_id=eq.' . (int)$s['id'] . '&' . $filter);
    $next = ['baru' => ['diproses', 'Terima & proses'], 'diproses' => ['siap', 'Siap diambil'], 'siap' => ['selesai', 'Selesai (sudah diambil)']];
    ob_start(); ?>
<header class="hd"><small>Stand Kios <?= e($s['kios']) ?></small><h1><?= e($s['nama']) ?></h1>
  <form method="post" action="/penjual/buka" style="margin-top:10px"><button class="btn sm <?= $s['buka'] ? 'ok' : 'sec' ?>"><?= $s['buka'] ? 'Stand BUKA · ketuk untuk menutup' : 'Stand TUTUP · ketuk untuk membuka' ?></button></form></header>
<div class="bd"><div class="tabs"><a href="/penjual" class="<?= $tab === 'aktif' ? 'on' : '' ?>">Pesanan aktif</a><a href="/penjual?tab=riwayat" class="<?= $tab === 'riwayat' ? 'on' : '' ?>">Selesai / batal</a></div>
<div id="live">
<?php foreach ($os as $o): ?>
  <div class="box ocard"><div class="row"><b>#<?= e($o['kode']) ?> · <?= e(LAY[$o['layanan']] ?? '') ?></b><?= tag($o['status']) ?></div>
    <ul class="lines"><?php foreach ($o['pesanan_item'] as $i): ?><li><b><?= (int)$i['jumlah'] ?> x</b> <?= e($i['nama']) ?><?= $i['catatan'] ? ' <small class="mut">(' . e($i['catatan']) . ')</small>' : '' ?></li><?php endforeach; ?></ul>
    <div class="row"><span class="mut"><?= waktu($o['created_at']) ?></span><b><?= rp($o['total']) ?></b></div>
    <?php if (isset($next[$o['status']])): [$to, $lb] = $next[$o['status']]; ?>
    <div class="btns" style="margin-top:12px">
      <?php if ($o['status'] !== 'siap'): ?><form method="post" action="/penjual/status" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan."><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="batal"><button class="btn sm sec">Batalkan</button></form><?php endif; ?>
      <form method="post" action="/penjual/status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="<?= $to ?>"><button class="btn sm"><?= e($lb) ?></button></form>
    </div><?php endif; ?></div>
<?php endforeach; if (!$os) echo empty_box($tab === 'aktif' ? 'Belum ada pesanan aktif.' : 'Belum ada riwayat.'); ?>
</div></div><?= nav_pen('/penjual');
    layout('Pesanan', ob_get_clean(), ['live' => $tab === 'aktif' ? 6000 : 0]);
}

function r_pen_status(): void {
    need('penjual');
    try { db_rpc('ubah_status', ['p_id' => (int)($_POST['id'] ?? 0), 'p_status' => (string)($_POST['status'] ?? '')]); flash('Status diperbarui'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/penjual');
}

function r_pen_buka(): void {
    $m = need('penjual'); $s = stand_saya($m);
    db_write('PATCH', 'stands?id=eq.' . (int)$s['id'], ['buka' => !$s['buka']]);
    flash($s['buka'] ? 'Stand ditutup' : 'Stand dibuka'); redirect('/penjual');
}

function r_pen_menu(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $menu = db_get('menu?select=id,nama,harga,stok,tersedia,foto_url,kategori_id&stand_id=eq.' . (int)$s['id'] . '&order=nama');
    $kat = db_get('kategori?select=id,nama&order=nama');
    $opt = fn($sel) => '<option value="">Tanpa kategori</option>' . implode('', array_map(fn($k) => '<option value="' . (int)$k['id'] . '"' . ((int)$sel === (int)$k['id'] ? ' selected' : '') . '>' . e($k['nama']) . '</option>', $kat));
    ob_start();
    echo hd('Kelola Menu', e($s['nama'])); ?>
<div class="bd"><details class="box"><summary><b>+ Tambah menu baru</b></summary>
  <form method="post" action="/penjual/menu/tambah" class="frm">
    <input name="nama" placeholder="Nama menu" required maxlength="60"><input name="harga" type="number" min="0" placeholder="Harga (Rp)" required><input name="stok" type="number" min="0" placeholder="Stok" required>
    <select name="kategori_id"><?= $opt(0) ?></select><input name="foto_url" type="url" placeholder="Link foto (opsional)"><button class="btn sm">Simpan menu</button></form></details>
<?php foreach ($menu as $i): ?>
  <div class="box"><div class="row"><b><?= e($i['nama']) ?></b><?= $i['stok'] > 0 && $i['tersedia'] ? '<span class="tag s-selesai">Tersedia</span>' : '<span class="tag s-batal">Habis / nonaktif</span>' ?></div>
    <form method="post" action="/penjual/menu/simpan" class="frm">
      <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
      <label>Harga<input name="harga" type="number" min="0" value="<?= (int)$i['harga'] ?>" required></label>
      <label>Stok<input name="stok" type="number" min="0" value="<?= (int)$i['stok'] ?>" required></label>
      <label>Kategori<select name="kategori_id"><?= $opt($i['kategori_id'] ?? 0) ?></select></label>
      <label>Link foto<input name="foto_url" type="url" value="<?= e($i['foto_url']) ?>"></label>
      <label class="chk"><input type="checkbox" name="tersedia" value="1" <?= $i['tersedia'] ? 'checked' : '' ?>> Tampilkan di menu</label>
      <div class="btns"><button class="btn sm">Simpan</button></div></form>
    <form method="post" action="/penjual/menu/hapus" data-confirm="Hapus menu ini?" style="margin-top:8px"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn sm sec">Hapus menu</button></form></div>
<?php endforeach; if (!$menu) echo empty_box('Belum ada menu.'); ?>
</div><?= nav_pen('/penjual/menu');
    layout('Kelola Menu', ob_get_clean());
}

function r_pen_menu_tambah(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $k = (int)($_POST['kategori_id'] ?? 0);
    try { db_write('POST', 'menu', ['stand_id' => (int)$s['id'], 'nama' => trim($_POST['nama'] ?? ''), 'harga' => max(0, (int)($_POST['harga'] ?? 0)), 'stok' => max(0, (int)($_POST['stok'] ?? 0)),
        'kategori_id' => $k ?: null, 'foto_url' => trim($_POST['foto_url'] ?? '') ?: null, 'tersedia' => true]); flash('Menu ditambahkan'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/penjual/menu');
}

function r_pen_menu_simpan(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $k = (int)($_POST['kategori_id'] ?? 0);
    try { db_write('PATCH', 'menu?id=eq.' . (int)($_POST['id'] ?? 0) . '&stand_id=eq.' . (int)$s['id'], ['harga' => max(0, (int)($_POST['harga'] ?? 0)), 'stok' => max(0, (int)($_POST['stok'] ?? 0)),
        'kategori_id' => $k ?: null, 'foto_url' => trim($_POST['foto_url'] ?? '') ?: null, 'tersedia' => ($_POST['tersedia'] ?? '') === '1']); flash('Menu disimpan'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/penjual/menu');
}

function r_pen_menu_hapus(): void {
    $m = need('penjual'); $s = stand_saya($m);
    try { db_delete('menu?id=eq.' . (int)($_POST['id'] ?? 0) . '&stand_id=eq.' . (int)$s['id']); flash('Menu dihapus'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/penjual/menu');
}

function r_pen_profil(): void {
    $m = need('penjual'); $s = stand_saya($m);
    layout('Profil', hd('Profil', e($m['nama'])) . '<div class="bd"><div class="box"><div class="sum"><span class="mut">Nama</span><b>' . e($m['nama']) . '</b></div><div class="sum"><span class="mut">Username</span><b>' . e($m['username']) . '</b></div><div class="sum"><span class="mut">Stand</span><b>' . e($s['nama']) . ' (Kios ' . e($s['kios']) . ')</b></div></div><a class="btn sec" href="/keluar">Keluar</a></div>' . nav_pen('/penjual/profil'));
}
