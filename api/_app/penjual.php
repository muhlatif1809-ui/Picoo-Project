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
    $sid = (int)$s['id'];
    $stats = db_sql("SELECT
        COUNT(*) FILTER (WHERE status = 'baru') AS baru,
        COUNT(*) FILTER (WHERE status = 'diproses') AS diproses,
        COALESCE(SUM(total) FILTER (WHERE status = 'selesai' AND created_at::date = CURRENT_DATE), 0) AS pendapatan
        FROM pesanan WHERE stand_id = :s", [':s' => $sid])[0] ?? ['baru'=>0,'diproses'=>0,'pendapatan'=>0];
    $stock = db_sql('SELECT COUNT(*) AS jumlah FROM menu WHERE stand_id = :s AND tersedia = true AND stok > 0', [':s' => $sid])[0]['jumlah'] ?? 0;
    $os = pesanan_muat('p.stand_id = :s', [':s' => $sid], 'ORDER BY p.created_at DESC LIMIT 3');
    ob_start(); ?>
<header class="seller-home-head"><div><small>Dashboard penjual</small><h1><?= e($s['nama']) ?></h1><span>Kios <?= e($s['kios']) ?></span></div>
  <form method="post" action="/penjual/buka"><button class="seller-open <?= $s['buka'] ? 'is-open' : '' ?>"><i></i><?= $s['buka'] ? 'Buka' : 'Tutup' ?></button></form></header>
<div class="bd seller-dashboard" id="live">
  <section class="seller-stats" aria-label="Ringkasan penjualan">
    <a class="seller-stat stat-new" href="/penjual/riwayat?status=baru"><small>Pesanan baru</small><b><?= (int)$stats['baru'] ?></b><span>Perlu diterima <i>→</i></span></a>
    <a class="seller-stat stat-income" href="/penjual/riwayat?status=selesai"><small>Pendapatan hari ini</small><b><?= rp($stats['pendapatan']) ?></b><span>Pesanan selesai</span></a>
    <a class="seller-stat stat-progress" href="/penjual/riwayat?status=diproses"><small>Sedang diproses</small><b><?= (int)$stats['diproses'] ?></b><span>Lihat pesanan <i>→</i></span></a>
    <a class="seller-stat stat-stock" href="/penjual/menu"><small>Menu tersedia</small><b><?= (int)$stock ?></b><span>Kelola stok <i>→</i></span></a>
  </section>
  <section class="seller-recent"><div class="seller-section-title"><div><small>Aktivitas terbaru</small><h2>Pesanan terbaru</h2></div><a href="/penjual/riwayat">Lihat semua <span>→</span></a></div>
    <div class="seller-order-list">
    <?php foreach ($os as $o): ?>
      <article class="box ocard seller-order"><div class="row"><div><small class="mut"><?= waktu($o['created_at']) ?></small><b>#<?= e($o['kode']) ?></b></div><?= tag($o['status']) ?></div>
        <ul class="lines"><?php foreach (array_slice($o['pesanan_item'],0,2) as $i): ?><li><b><?= (int)$i['jumlah'] ?>×</b> <?= e($i['nama']) ?></li><?php endforeach; ?><?php if (count($o['pesanan_item']) > 2): ?><li class="mut">+<?= count($o['pesanan_item'])-2 ?> menu lainnya</li><?php endif; ?></ul>
        <div class="row seller-order-foot"><span><?= e(LAY[$o['layanan']] ?? '') ?></span><b><?= rp($o['total']) ?></b></div></article>
    <?php endforeach; if (!$os) echo empty_box('Belum ada aktivitas pesanan.'); ?>
    </div>
  </section>
</div><?= nav_pen('/penjual');
    layout('Home Penjual', ob_get_clean(), ['live' => 10000]);
}

function r_pen_riwayat(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $filters = ['semua'=>'Semua', 'baru'=>'Baru', 'diproses'=>'Proses', 'siap'=>'Siap', 'selesai'=>'Selesai', 'batal'=>'Batal'];
    $status = $_GET['status'] ?? 'baru';
    if (!isset($filters[$status])) $status = 'baru';
    $where = 'p.stand_id = :s'; $par = [':s' => (int)$s['id']];
    if ($status !== 'semua') { $where .= ' AND p.status = :status'; $par[':status'] = $status; }
    $os = pesanan_muat($where, $par, 'ORDER BY p.created_at DESC LIMIT 60');
    $next = ['baru' => ['diproses', 'Terima & proses'], 'diproses' => ['siap', 'Siap diambil'], 'siap' => ['selesai', 'Selesai (sudah diambil)']];
    ob_start(); ?>
<?= hd('Riwayat Pesanan', e($s['nama']) . ' · Kios ' . e($s['kios'])) ?>
<div class="bd seller-history"><nav class="seller-filters" aria-label="Filter status pesanan"><?php foreach ($filters as $key=>$label): ?><a href="/penjual/riwayat?status=<?= e($key) ?>" class="<?= $status === $key ? 'on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<div id="live" class="seller-order-list">
<?php foreach ($os as $o): ?>
  <div class="box ocard seller-order"><div class="row"><div><small class="mut"><?= waktu($o['created_at']) ?></small><b>#<?= e($o['kode']) ?> · <?= e(LAY[$o['layanan']] ?? '') ?></b></div><?= tag($o['status']) ?></div>
    <ul class="lines"><?php foreach ($o['pesanan_item'] as $i): ?><li><b><?= (int)$i['jumlah'] ?> x</b> <?= e($i['nama']) ?><?= $i['catatan'] ? ' <small class="mut">(' . e($i['catatan']) . ')</small>' : '' ?></li><?php endforeach; ?></ul>
    <div class="row seller-order-foot"><span><?= count($o['pesanan_item']) ?> item</span><b><?= rp($o['total']) ?></b></div>
    <?php if (isset($next[$o['status']])): [$to, $lb] = $next[$o['status']]; ?>
    <div class="btns" style="margin-top:12px">
      <?php if ($o['status'] !== 'siap'): ?><form method="post" action="/penjual/status" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan."><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="batal"><button class="btn sm sec">Batalkan</button></form><?php endif; ?>
      <form method="post" action="/penjual/status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="<?= $to ?>"><button class="btn sm"><?= e($lb) ?></button></form>
    </div><?php endif; ?></div>
<?php endforeach; if (!$os) echo empty_box('Tidak ada pesanan untuk status ini.'); ?>
</div></div><?= nav_pen('/penjual/riwayat');
    layout('Riwayat Pesanan', ob_get_clean(), ['live' => in_array($status, ['semua','baru','diproses','siap'], true) ? 6000 : 0]);
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
    ?>
<header class="seller-menu-head"><div><small>Kelola dagangan</small><h1>Menu</h1><p><?= e($s['nama']) ?></p></div><a href="#tambah-menu" class="btn sm">+ Tambah menu</a></header>
<div class="bd seller-menu-page"><details class="box seller-add-menu" id="tambah-menu"><summary><b>Tambah menu baru</b><span>＋</span></summary>
  <form method="post" action="/penjual/menu/tambah" class="frm" enctype="multipart/form-data">
    <label>Nama menu<input name="nama" placeholder="Contoh: Nasi ayam" required maxlength="60"></label><label>Harga<input name="harga" type="number" min="0" placeholder="Harga (Rp)" required></label><label>Stok<input name="stok" type="number" min="0" placeholder="Jumlah stok" required></label>
    <select name="kategori_id"><?= $opt(0) ?></select>
    <label>Foto (JPG/PNG/WebP, maks 2 MB)<input name="foto" type="file" accept="image/jpeg,image/png,image/webp"></label>
    <button class="btn sm">Simpan menu</button></form></details>
<?php foreach ($menu as $i): ?>
  <article class="box seller-menu-item"><div class="seller-menu-preview"><div class="seller-menu-photo"><?= img($i['foto_url'] ?? null) ?></div><div class="seller-menu-info"><b><?= e($i['nama']) ?></b><strong><?= rp($i['harga']) ?></strong><small><?= (int)$i['stok'] ?> stok · <?= $i['stok'] > 0 && $i['tersedia'] ? 'Tersedia' : 'Habis / nonaktif' ?></small></div></div>
    <details class="seller-edit-menu"><summary>Edit menu <span>⌄</span></summary>
    <form method="post" action="/penjual/menu/simpan" class="frm" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
      <label>Harga<input name="harga" type="number" min="0" value="<?= (int)$i['harga'] ?>" required></label>
      <label>Stok<input name="stok" type="number" min="0" value="<?= (int)$i['stok'] ?>" required></label>
      <label>Kategori<select name="kategori_id"><?= $opt($i['kategori_id'] ?? 0) ?></select></label>
      <label>Ganti foto (kosongkan jika tidak diganti)<input name="foto" type="file" accept="image/jpeg,image/png,image/webp"></label>
      <label class="chk"><input type="checkbox" name="tersedia" value="1" <?= $i['tersedia'] ? 'checked' : '' ?>> Tampilkan di menu</label>
      <div class="btns"><button class="btn sm">Simpan perubahan</button></div></form>
    <form method="post" action="/penjual/menu/hapus" data-confirm="Hapus menu ini?" style="margin-top:8px"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn sm sec">Hapus menu</button></form></details></article>
<?php endforeach; if (!$menu) echo empty_box('Belum ada menu.'); ?>
</div><?= nav_pen('/penjual/menu');
    layout('Kelola Menu', ob_get_clean());
}

function r_pen_menu_tambah(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $k = (int)($_POST['kategori_id'] ?? 0);
    try {
        $r = db_write('POST', 'menu', [
            'stand_id' => (int)$s['id'],
            'nama' => trim($_POST['nama'] ?? ''),
            'harga' => max(0, (int)($_POST['harga'] ?? 0)),
            'stok' => max(0, (int)($_POST['stok'] ?? 0)),
            'kategori_id' => $k ?: null,
            'tersedia' => true,
        ]);
        flash('Menu ditambahkan');
        if (($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($r[0]['id'])) {
            try { foto_simpan('menu', (int)$r[0]['id'], $_FILES['foto']); }
            catch (SbError $x) { flash('Menu ditambahkan, tetapi foto gagal: ' . $x->getMessage()); }
        }
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/penjual/menu');
}

function r_pen_menu_simpan(): void {
    $m = need('penjual'); $s = stand_saya($m);
    $k = (int)($_POST['kategori_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    try {
        $r = db_write('PATCH', 'menu?id=eq.' . $id . '&stand_id=eq.' . (int)$s['id'], [
            'harga' => max(0, (int)($_POST['harga'] ?? 0)),
            'stok' => max(0, (int)($_POST['stok'] ?? 0)),
            'kategori_id' => $k ?: null,
            'tersedia' => ($_POST['tersedia'] ?? '') === '1',
        ]);
        flash('Menu disimpan');
        if ($r && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try { foto_simpan('menu', $id, $_FILES['foto']); }
            catch (SbError $x) { flash('Menu disimpan, tetapi foto gagal: ' . $x->getMessage()); }
        }
    } catch (SbError $x) { flash($x->getMessage()); }
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