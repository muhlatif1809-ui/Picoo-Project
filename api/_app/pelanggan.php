<?php
// pelanggan.php : halaman awal, login/daftar (semua peran), dan seluruh halaman pelanggan.

function r_root(): void {
    if ($m = me()) redirect(home_for($m['role']));
    ob_start(); ?>
<div class="auth"><div class="logo">P</div><h1>PICO</h1><p class="mut">Pesan makanan Kantin AX tanpa antre</p>
<div class="pilih">
  <a href="/login?sebagai=pembeli"><span class="ic">🍽️</span><span><b>Saya Pembeli</b><small>Pesan makanan dari stand kantin</small></span></a>
  <a href="/login?sebagai=penjual"><span class="ic">🏪</span><span><b>Saya Penjual</b><small>Kelola pesanan dan menu stand</small></span></a>
</div></div>
<?php layout('PICO', ob_get_clean());
}

// ---------------------------------------------------------------- login & daftar
// Dua "pintu" (pembeli / penjual) hanya beda tampilan. Admin boleh lewat pintu mana saja.
// Setelah login, pengguna SELALU dibawa ke halaman sesuai role di database, bukan sesuai pintu yang diklik.
function pintu(): string { $as = $_POST['sebagai'] ?? $_GET['sebagai'] ?? ''; return in_array($as, ['pembeli', 'penjual'], true) ? $as : 'pembeli'; }

function r_login(): void {
    if ($m = me()) redirect(home_for($m['role']));
    if (!in_array($_GET['sebagai'] ?? '', ['pembeli', 'penjual'], true)) redirect('/');
    $as = pintu(); $err = take_flash();
    ob_start(); ?>
<div class="auth"><div class="logo">P</div><h1>Masuk sebagai <?= $as === 'penjual' ? 'Penjual' : 'Pembeli' ?></h1><p class="mut">Pesan makanan Kantin AX tanpa antre</p>
<form method="post" action="/login">
  <input type="hidden" name="sebagai" value="<?= e($as) ?>">
  <div class="fld"><input name="u" placeholder="<?= $as === 'penjual' ? 'Username atau email' : 'Username/email/no.HP/NIM/NIP' ?>" autocomplete="username" required></div>
  <div class="fld"><input name="p" id="pw" type="password" placeholder="Password" autocomplete="current-password" required><button type="button" class="eye" id="eye" aria-label="Tampilkan password">👁</button></div>
  <p class="er"><?= e($err) ?></p><button class="btn">Masuk</button>
</form>
<?php if ($as === 'pembeli'): ?><p class="mut c">Belum punya akun? <a href="/daftar" class="org">Daftar</a></p>
<?php else: ?><div class="note">Akun penjual dibuat oleh admin kantin. Hubungi admin jika belum punya akun.</div><?php endif; ?>
<p class="mut c" style="margin-top:16px"><a href="/" class="org">← Ganti peran</a></p></div>
<?php layout('Masuk', ob_get_clean());
}

function r_login_post(): void {
    $as = pintu(); $back = '/login?sebagai=' . $as;
    $id = trim($_POST['u'] ?? ''); $pw = (string)($_POST['p'] ?? '');
    $email = $id;
    if (!str_contains($id, '@')) { // login pakai username / NIM / no HP: cari emailnya dulu
        try { $email = db_rpc('login_lookup', ['p_identifier' => $id], true); }
        catch (SbError $x) { flash('Gagal memeriksa akun: ' . $x->getMessage()); redirect($back); }
        if (!$email) { flash('Akun tidak ditemukan.'); redirect($back); }
    }
    try { save_session(auth_login((string)$email, $pw)); }
    catch (SbError $x) { flash('Username atau password salah.'); redirect($back); }
    $m = me();
    if (!$m) { flash('Profil akun tidak ditemukan. Hubungi admin.'); redirect($back); }
    redirect(home_for($m['role']));
}

function r_daftar(): void {
    $err = take_flash();
    ob_start(); ?>
<div class="auth" style="padding-top:36px"><h1>Buat akun</h1><p class="mut">Isi data untuk mulai memesan</p>
<form method="post" action="/daftar">
  <div class="fld"><input name="n" placeholder="Nama Lengkap" required></div>
  <div class="fld"><input name="h" type="tel" placeholder="No. handphone" required></div>
  <div class="fld"><input name="e" type="email" placeholder="Email" required></div>
  <div class="fld"><input name="p" type="password" placeholder="Password (min. 6 karakter)" minlength="6" required></div>
  <div class="fld"><input name="k" type="password" placeholder="Konfirmasi password" required></div>
  <p class="er"><?= e($err) ?></p><button class="btn">Daftar</button>
</form>
<p class="mut c">Sudah punya akun? <a href="/login?sebagai=pembeli" class="org">Masuk</a></p></div>
<?php layout('Daftar', ob_get_clean());
}

function r_daftar_post(): void {
    $n = trim($_POST['n'] ?? ''); $h = trim($_POST['h'] ?? ''); $em = trim($_POST['e'] ?? '');
    $p = (string)($_POST['p'] ?? ''); $k = (string)($_POST['k'] ?? '');
    if ($n === '' || $em === '' || $p === '') { flash('Lengkapi semua data.'); redirect('/daftar'); }
    if ($p !== $k) { flash('Password dan konfirmasi belum sama.'); redirect('/daftar'); }
    try { $r = auth_signup($em, $p, $n, $h); }
    catch (SbError $x) { flash($x->getMessage()); redirect('/daftar'); }
    if (!empty($r['access_token'])) { save_session($r); redirect('/home'); }
    flash('Akun dibuat. Cek email untuk verifikasi, lalu masuk.'); redirect('/login?sebagai=pembeli');
}

function r_keluar(): void { clear_session(); cookie_del('pico_cart'); redirect('/'); }

// ---------------------------------------------------------------- home & menu
function r_home(): void {
    $m = need('pelanggan');
    $cari = trim($_GET['cari'] ?? '');
    $stands = db_get('stands?select=id,nama,kios,buka,foto_url,menu(count)&order=kios');
    if ($cari !== '') {
        $t = lc($cari);
        $safe = str_replace(['*', '(', ')', ',', '%'], '', $cari);
        $hit = array_column(db_get('menu?select=stand_id&nama=ilike.' . rawurlencode('*' . $safe . '*')), 'stand_id');
        $stands = array_values(array_filter($stands, fn($s) => str_contains(lc($s['nama']), $t) || in_array($s['id'], $hit)));
    }
    ob_start(); ?>
<header class="hd"><small>Halo, <?= e($m['nama']) ?></small><h1>Mau makan apa hari ini?</h1>
  <form method="get" action="/home"><label class="search"><span>🔍</span><input name="cari" value="<?= e($cari) ?>" placeholder="Cari stand atau menu"></label></form></header>
<div class="bd"><h3>Stand</h3>
<?php foreach ($stands as $s): $buka = $s['buka']; ?>
  <a class="stand <?= $buka ? '' : 'off' ?>" href="/menu?stand=<?= (int)$s['id'] ?>"><div class="thumb"><?= img($s['foto_url']) ?></div>
    <div><b><?= e($s['nama']) ?></b><small>Kios <?= e($s['kios']) ?></small><small><?= $buka ? (int)($s['menu'][0]['count'] ?? 0) . ' Menu' : 'sedang tutup' ?></small></div>
    <span class="badge <?= $buka ? '' : 'off' ?>"><?= $buka ? 'Buka' : 'Tutup' ?></span></a>
<?php endforeach; if (!$stands) echo empty_box('Stand tidak ditemukan.'); ?>
</div><?= nav_pel('/home') ?>
<?php layout('Home', ob_get_clean());
}

function r_menu(): void {
    need('pelanggan');
    $sid = (int)($_GET['stand'] ?? 0);
    $s = db_get('stands?select=id,nama,kios,buka&id=eq.' . $sid)[0] ?? null;
    if (!$s) { flash('Stand tidak ditemukan.'); redirect('/home'); }
    $menu = db_get('menu?select=id,nama,harga,stok,tersedia,foto_url&stand_id=eq.' . $sid . '&order=nama');
    $cs = (int)(cart()['s'] ?? 0);
    ob_start();
    echo hd(e($s['nama']), ($s['buka'] ? 'Buka' : 'Tutup') . '<br>Kios ' . e($s['kios']), '/home'); ?>
<div class="bd"><?php if (!$s['buka']) echo '<div class="note" style="margin:0 0 14px">Stand sedang tutup, belum bisa memesan.</div>'; ?>
<div class="grid2">
<?php foreach ($menu as $i): $ok = $s['buka'] && $i['tersedia'] && $i['stok'] > 0; ?>
  <div class="pc <?= $i['stok'] > 0 ? '' : 'out' ?>"><div class="im"><?= img($i['foto_url']) ?></div><b><?= e($i['nama']) ?></b><span class="pr"><?= rp($i['harga']) ?></span>
    <?php if ($i['stok'] <= 0) echo '<span class="pill">Stok habis</span>'; ?>
    <?php if ($ok): ?><button type="button" class="plus" data-open="d<?= (int)$i['id'] ?>" aria-label="Tambah <?= e($i['nama']) ?>">+</button><?php endif; ?></div>
<?php endforeach; ?>
</div><?php if (!$menu) echo empty_box('Menu belum tersedia.'); ?></div>
<?php foreach ($menu as $i): if (!($s['buka'] && $i['tersedia'] && $i['stok'] > 0)) continue; ?>
<dialog id="d<?= (int)$i['id'] ?>"><form method="post" action="/keranjang/tambah" class="sheet add" data-max="<?= (int)$i['stok'] ?>" data-h="<?= (int)$i['harga'] ?>" data-cs="<?= $cs ?>" data-st="<?= $sid ?>">
  <input type="hidden" name="stand" value="<?= $sid ?>"><input type="hidden" name="menu" value="<?= (int)$i['id'] ?>"><input type="hidden" name="jml" value="1"><input type="hidden" name="ganti" value="">
  <div class="row" style="gap:14px"><div class="thumb" style="width:92px;height:84px"><?= img($i['foto_url']) ?></div>
    <div style="flex:1"><b><?= e($i['nama']) ?></b><div class="org"><?= rp($i['harga']) ?></div><small class="mut">Stok online: <?= (int)$i['stok'] ?></small></div></div>
  <div class="row" style="margin-top:14px"><b>Jumlah</b><div class="qty"><button type="button" class="mn" aria-label="Kurangi">-</button><b class="n">1</b><button type="button" class="p pl" aria-label="Tambah">+</button></div></div>
  <textarea name="cat" placeholder="Catatan (opsional)" maxlength="120"></textarea><button class="btn go">Tambahkan ke keranjang</button>
</form></dialog>
<?php endforeach; echo nav_pel('/home');
    layout($s['nama'], ob_get_clean());
}

// ---------------------------------------------------------------- keranjang
function r_cart_add(): void {
    need('pelanggan');
    $sid = (int)($_POST['stand'] ?? 0); $mid = (int)($_POST['menu'] ?? 0);
    $jml = max(1, (int)($_POST['jml'] ?? 1)); $cat = cut(trim($_POST['cat'] ?? ''), 120);
    $m = db_get('menu?select=id,stok,tersedia&id=eq.' . $mid . '&stand_id=eq.' . $sid)[0] ?? null;
    if (!$m || !$m['tersedia'] || $m['stok'] < 1) { flash('Menu tidak tersedia.'); redirect('/menu?stand=' . $sid); }
    $c = cart();
    if ($c['i'] && (int)$c['s'] !== $sid) {
        if (($_POST['ganti'] ?? '') !== '1') { flash('Keranjang berisi menu dari stand lain. Kosongkan dulu.'); redirect('/keranjang'); }
        $c = ['s' => 0, 'i' => []];
    }
    $c['s'] = $sid; $found = false;
    foreach ($c['i'] as &$x) if ((int)$x['id'] === $mid) { $x['j'] = min((int)$m['stok'], (int)$x['j'] + $jml); $found = true; }
    unset($x);
    if (!$found) $c['i'][] = ['id' => $mid, 'j' => min((int)$m['stok'], $jml), 'c' => $cat];
    cart_save($c); flash('Masuk keranjang'); redirect('/menu?stand=' . $sid);
}

function r_cart(): void {
    need('pelanggan');
    $d = cart_detail(); $n = count($d['items']);
    ob_start();
    echo hd('Keranjang', $n ? $n . ' menu dari ' . e($d['stand']['nama']) : 'Belum ada menu'); ?>
<div class="bd">
<?php foreach ($d['items'] as $x): ?>
  <div class="item"><div class="thumb"><?= img($x['foto']) ?></div><div class="t"><b><?= e($x['nama']) ?></b><small><?= rp($x['harga']) ?></small><?= $x['cat'] ? '<small><br>' . e($x['cat']) . '</small>' : '' ?></div>
    <form method="post" action="/keranjang/ubah" class="qty"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
      <button name="d" value="-1" aria-label="Kurangi">-</button><b><?= (int)$x['jml'] ?></b><button name="d" value="1" class="p" aria-label="Tambah">+</button></form></div>
<?php endforeach; if (!$n) echo empty_box('Keranjang masih kosong. Pilih stand di Home dulu.'); ?>
</div>
<div class="foot" style="bottom:64px"><div class="row" style="margin-bottom:10px"><span class="mut">Total</span><b><?= rp($d['total']) ?></b></div>
  <?= $n ? '<a class="btn" href="/opsi">Checkout</a>' : '<button class="btn" disabled>Checkout</button>' ?></div>
<?= nav_pel('/keranjang');
    layout('Keranjang', ob_get_clean());
}

function r_cart_change(): void {
    need('pelanggan');
    $id = (int)($_POST['id'] ?? 0); $dlt = (int)($_POST['d'] ?? 0) > 0 ? 1 : -1;
    $c = cart();
    foreach ($c['i'] as $k => &$x) if ((int)$x['id'] === $id) { $x['j'] = (int)$x['j'] + $dlt; if ($x['j'] < 1) unset($c['i'][$k]); }
    unset($x);
    cart_save($c); redirect('/keranjang');
}

function r_opsi(): void {
    need('pelanggan');
    $d = cart_detail(); if (!$d['items']) redirect('/keranjang');
    $cur = $_COOKIE['pico_lay'] ?? 'dine_in';
    ob_start();
    echo hd('Konfirmasi Pesanan'); ?>
<div class="bd"><?php foreach ($d['items'] as $x) echo '<div class="box"><b>' . (int)$x['jml'] . ' x ' . e($x['nama']) . '</b><div class="mut">' . rp($x['harga'] * $x['jml']) . '</div></div>'; ?></div>
<form method="post" action="/opsi" class="foot" style="padding-top:16px"><b>Pilih layanan</b><div style="margin-top:10px">
  <label class="opt"><input type="radio" name="l" value="dine_in" <?= $cur === 'dine_in' ? 'checked' : '' ?>><div><b>Dine in</b><small>Makan di kantin</small></div></label>
  <label class="opt"><input type="radio" name="l" value="take_away" <?= $cur === 'take_away' ? 'checked' : '' ?>><div><b>Take Away</b><small>Dibawa pulang</small></div></label></div>
  <div class="btns"><a class="btn sec" href="/keranjang">Kembali</a><button class="btn">Lanjut</button></div></form>
<?php layout('Konfirmasi', ob_get_clean());
}

function r_opsi_post(): void {
    need('pelanggan');
    $l = ($_POST['l'] ?? '') === 'take_away' ? 'take_away' : 'dine_in';
    cookie_set('pico_lay', $l, 86400); redirect('/bayar');
}

function r_bayar(): void {
    need('pelanggan');
    $d = cart_detail(); if (!$d['items']) redirect('/keranjang');
    $lay = $_COOKIE['pico_lay'] ?? 'dine_in';
    ob_start();
    echo hd('Pembayaran'); ?>
<div class="bd"><div class="box"><b>Ringkasan</b><div class="sum"><span>Subtotal</span><span><?= rp($d['total']) ?></span></div><div class="sum"><span>Layanan</span><span><?= e(LAY[$lay] ?? $lay) ?></span></div><div class="sum t"><span>Total</span><span><?= rp($d['total']) ?></span></div></div>
<h3>Metode Pembayaran</h3><label class="opt"><input type="radio" checked><div><b>QRIS</b><small>Semua e-wallet dan m-banking</small></div></label>
<p class="mut" style="font-size:.9rem">Pembayaran wajib dilakukan sebelum pesanan diproses penjual</p></div>
<form method="post" action="/bayar" class="foot"><button class="btn">Bayar sekarang</button></form>
<?php layout('Pembayaran', ob_get_clean());
}

function r_bayar_post(): void {
    need('pelanggan');
    $c = cart(); $d = cart_detail(); if (!$d['items']) redirect('/keranjang');
    $lay = ($_COOKIE['pico_lay'] ?? '') === 'take_away' ? 'take_away' : 'dine_in';
    $items = array_map(fn($x) => ['menu_id' => (int)$x['id'], 'jumlah' => (int)$x['jml'], 'catatan' => $x['cat'] !== '' ? $x['cat'] : null], $d['items']);
    try { $o = db_rpc('buat_pesanan', ['p_stand' => (int)$c['s'], 'p_layanan' => $lay, 'p_items' => $items]); }
    catch (SbError $x) { flash($x->getMessage()); redirect('/bayar'); }
    cart_save(['s' => 0, 'i' => []]);
    redirect('/qris?id=' . (int)$o['id']);
}

// ---------------------------------------------------------------- QRIS & status
const PESANAN_SEL = 'pesanan?select=id,kode,status,layanan,total,created_at,stands(nama,kios),pesanan_item(nama,jumlah,harga,catatan)';

function ambil_pesanan(array $m, int $id): ?array {
    return db_get(PESANAN_SEL . '&id=eq.' . $id . '&pelanggan_id=eq.' . rawurlencode($m['id']))[0] ?? null;
}

function r_qris(): void {
    $m = need('pelanggan'); $id = (int)($_GET['id'] ?? 0);
    try { db_rpc('bersihkan_kadaluarsa'); } catch (SbError $x) {}
    $o = ambil_pesanan($m, $id);
    if (!$o) { flash('Pesanan tidak ditemukan.'); redirect('/riwayat'); }
    if ($o['status'] !== 'menunggu_bayar') redirect('/status?id=' . $id);
    $exp = strtotime($o['created_at']) + 900;
    $seed = $id * 9301 + 49297; $qr = '';
    for ($k = 0; $k < 441; $k++) {
        $x = $k % 21; $y = intdiv($k, 21);
        if (($x < 7 && $y < 7) || ($x > 13 && $y < 7) || ($x < 7 && $y > 13)) $w = !($x % 6 === 0 || $y % 6 === 0 || ($x % 6 > 1 && $x % 6 < 5 && $y % 6 > 1 && $y % 6 < 5));
        else { $seed = ($seed * 9301 + 49297) % 233280; $w = $seed / 233280 <= .5; }
        $qr .= '<i' . ($w ? ' class="w"' : '') . '></i>';
    }
    ob_start(); ?>
<header class="hd"><a class="back" href="/home">← Pembayaran QRIS</a></header>
<div class="bd c"><p class="mut">Pesanan #<?= e($o['kode']) ?> &nbsp; <?= e(LAY[$o['layanan']] ?? '') ?></p><h1 style="margin:6px 0"><?= rp($o['total']) ?></h1>
<div class="qr" role="img" aria-label="Kode QR pembayaran (contoh)"><?= $qr ?></div>
<span class="timer" data-exp="<?= $exp ?>">Bayar dalam --:--</span>
<p class="mut" style="margin-top:14px">Scan dengan e-wallet atau m-banking</p><p class="mut" style="font-size:.78rem">QR contoh. QRIS asli menyusul dari payment gateway.</p></div>
<form method="post" action="/qris/bayar" class="foot"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn" id="ok">Saya sudah bayar</button></form>
<?php layout('QRIS', ob_get_clean());
}

function r_qris_bayar(): void {
    need('pelanggan'); $id = (int)($_POST['id'] ?? 0);
    try { db_rpc('saya_sudah_bayar', ['p_id' => $id]); }
    catch (SbError $x) { flash($x->getMessage()); redirect('/riwayat'); }
    redirect('/status?id=' . $id);
}

function r_status(): void {
    $m = need('pelanggan'); $id = (int)($_GET['id'] ?? 0);
    if (!$id) $id = (int)(db_get('pesanan?select=id&pelanggan_id=eq.' . rawurlencode($m['id']) . '&order=created_at.desc&limit=1')[0]['id'] ?? 0);
    $o = $id ? ambil_pesanan($m, $id) : null;
    if (!$o) { layout('Status', hd('Status pesanan') . empty_box('Belum ada pesanan.') . nav_pel('/riwayat')); return; }
    $map = ['baru' => ['Menunggu penjual menerima pesanan', 1], 'diproses' => ['Pesananmu sedang diproses', 1], 'siap' => ['Pesananmu siap diambil', 2], 'selesai' => ['Pesanan selesai', 4],
            'batal' => ['Pesanan dibatalkan', -1]];
    [$head, $at] = $map[$o['status']] ?? ['Menunggu pembayaran', 0];
    $done = $o['status'] === 'selesai';
    $S = [['Pembayaran diterima', ''], ['Sedang diproses', $o['status'] === 'diproses' ? 'Perkiraan 8 menit' : ''], ['Siap diambil', ''], ['Selesai', '']];
    $sub = e($head) . ($done || $at < 0 ? '' : '<br><small style="margin-top:4px">Tunjukkan nomor pesanan ke penjual saat mengambil makanan</small>');
    ob_start();
    echo hd('Pesanan #' . e($o['kode']), $sub); ?>
<div class="bd" id="live">
  <div class="box"><b><?= e(implode(', ', array_map(fn($i) => $i['jumlah'] . ' x ' . $i['nama'], $o['pesanan_item']))) ?></b>
    <div class="mut" style="margin-top:6px"><?= e($o['stands']['nama'] ?? '') ?> &nbsp; <?= e(LAY[$o['layanan']] ?? '') ?> &nbsp; <?= rp($o['total']) ?></div></div>
  <?php if ($at >= 0): ?>
  <ul class="steps" style="margin:18px 6px"><?php foreach ($S as $k => [$t, $sb]): ?>
    <li class="<?= ($k === 0 || $k < $at || $done) ? 'd' : ($k === $at ? 'a' : '') ?>"><div><b><?= e($t) ?></b><?= $sb ? '<small>' . e($sb) . '</small>' : '' ?></div></li>
  <?php endforeach; ?></ul>
  <?php else: ?><div class="note" style="background:#fde1e1;color:#b42318">Pesanan dibatalkan. Stok sudah dikembalikan.</div><?php endif; ?>
  <div class="note">Riwayat pesanan akan dihapus dalam 30 hari</div>
</div><?= nav_pel('/riwayat');
    layout('Status pesanan', ob_get_clean(), ['live' => ($done || $at < 0) ? 0 : 4000]);
}

function r_riwayat(): void {
    $m = need('pelanggan');
    try { db_rpc('bersihkan_kadaluarsa'); } catch (SbError $x) {}
    $l = db_get(PESANAN_SEL . '&pelanggan_id=eq.' . rawurlencode($m['id']) . '&order=created_at.desc&limit=30');
    ob_start();
    echo hd('Riwayat', 'Pesanan 30 hari terakhir'); ?>
<div class="bd">
<?php foreach ($l as $o): ?>
  <a class="item" href="/<?= $o['status'] === 'menunggu_bayar' ? 'qris' : 'status' ?>?id=<?= (int)$o['id'] ?>" style="display:flex"><div class="t"><b>#<?= e($o['kode']) ?> · <?= e($o['stands']['nama'] ?? '') ?></b>
    <small><?= e(implode(', ', array_map(fn($i) => $i['jumlah'] . ' ' . $i['nama'], $o['pesanan_item']))) ?></small></div>
    <div style="text-align:right"><b><?= rp($o['total']) ?></b><br><?= tag($o['status']) ?></div></a>
<?php endforeach; if (!$l) echo empty_box('Belum ada pesanan.'); ?>
</div><?= nav_pel('/riwayat');
    layout('Riwayat', ob_get_clean());
}

function r_profil(): void {
    $m = need('pelanggan');
    layout('Profil', hd('Profil', e($m['nama'])) . '<div class="bd"><div class="box"><div class="sum"><span class="mut">Nama</span><b>' . e($m['nama']) . '</b></div><div class="sum"><span class="mut">Username</span><b>' . e($m['username']) . '</b></div><div class="sum"><span class="mut">No. HP</span><b>' . e($m['no_hp'] ?? '-') . '</b></div></div><a class="btn sec" href="/keluar">Keluar</a></div>' . nav_pel('/profil'));
}
