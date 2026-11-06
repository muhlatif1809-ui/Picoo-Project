<?php
// pelanggan.php : halaman awal, login/daftar (semua peran), dan seluruh halaman pelanggan.

function r_root(): void {
    if ($m = me()) redirect(home_for($m['role']));
    ob_start(); ?>
<div class="auth"><div class="logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div><h1>PICO</h1><p class="mut">Pesan makanan Kantin AX tanpa antre</p>
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
<div class="auth"><div class="logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div><h1>Masuk sebagai <?= $as === 'penjual' ? 'Penjual' : 'Pembeli' ?></h1><p class="mut">Pesan makanan Kantin AX tanpa antre</p>
<form method="post" action="/login">
  <input type="hidden" name="sebagai" value="<?= e($as) ?>">
  <div class="fld"><input name="u" placeholder="<?= $as === 'penjual' ? 'Username atau email' : 'Username/email/no.HP/NIM/NIP' ?>" autocomplete="username" required></div>
  <div class="fld"><input name="p" id="pw" type="password" placeholder="Password" autocomplete="current-password" required><button type="button" class="eye" id="eye" aria-label="Tampilkan password">👁</button></div>
  <div style="display:flex;justify-content:flex-end;margin:-4px 0 12px">
    <a href="/lupa-password?sebagai=<?= e($as) ?>" class="org" style="font-size:.85rem;font-weight:600;text-decoration:none">Lupa password?</a>
  </div>
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
<form method="post" action="/daftar" enctype="multipart/form-data" id="fd">
  <div class="stat">
    <label class="opt"><input type="radio" name="s" value="polinema" required><div><b>Mahasiswa Polinema</b><small>Wajib upload KTM</small></div></label>
    <label class="opt"><input type="radio" name="s" value="umum"><div><b>Bukan mahasiswa Polinema</b><small>Pengunjung umum</small></div></label>
  </div>
  <div class="fld" id="ktm-w" hidden>
    <label for="ktm">Foto KTM (JPG/PNG/WebP, maks. 2 MB)</label>
    <input type="file" id="ktm" name="ktm" accept="image/jpeg,image/png,image/webp">
  </div>
  <fieldset id="dd" disabled>
    <div class="fld"><input name="n" placeholder="Nama Lengkap" required></div>
    <div class="fld"><input name="u" placeholder="Username" autocomplete="username" required></div>
    <div class="fld"><input name="h" type="tel" placeholder="No. handphone" required></div>
    <div class="fld"><input name="e" type="email" placeholder="Email" required></div>
    <div class="fld"><input name="p" type="password" placeholder="Password (min. 6 karakter)" minlength="6" required></div>
    <div class="fld"><input name="k" type="password" placeholder="Konfirmasi password" required></div>
    <p class="er"><?= e($err) ?></p><button class="btn">Daftar</button>
  </fieldset>
</form>
<p class="mut c">Sudah punya akun? <a href="/login?sebagai=pembeli" class="org">Masuk</a></p></div>
<script>
(function () {
  var f = document.getElementById('fd');
  if (!f) return;
  var dd = document.getElementById('dd'),
      w  = document.getElementById('ktm-w'),
      k  = document.getElementById('ktm');

  function sync() {
    var c = f.querySelector('input[name="s"]:checked');
    var pol = !!c && c.value === 'polinema';
    dd.disabled = !c;
    w.hidden = !pol;
    k.required = pol;
    if (!pol) k.value = '';
  }

  f.addEventListener('change', function (e) {
    if (e.target === k && k.files[0] && k.files[0].size > 2097152) {
      alert('Ukuran KTM maksimal 2 MB');
      k.value = '';
    }
    sync();
  });
  sync();
})();
</script>
<?php layout('Daftar', ob_get_clean());
}

function r_daftar_post(): void {
    $n = trim($_POST['n'] ?? '');
    $u = trim($_POST['u'] ?? '');
    $h = trim($_POST['h'] ?? '');
    $em = trim($_POST['e'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    $k = (string)($_POST['k'] ?? '');
    $s = $_POST['s'] ?? '';

    if (!in_array($s, ['polinema', 'umum'], true)) { flash('Pilih dulu: mahasiswa Polinema atau bukan.'); redirect('/daftar'); }
    $pol = $s === 'polinema';

    if ($n === '' || $u === '' || $em === '' || $p === '') { flash('Lengkapi semua data.'); redirect('/daftar'); }
    if (!preg_match('/^[a-zA-Z0-9_.\-]{3,30}$/', $u)) { flash('Username 3-30 karakter (huruf, angka, titik, atau underscore tanpa spasi).'); redirect('/daftar'); }
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) { flash('Email tidak valid.'); redirect('/daftar'); }
    if (strlen($p) < 6) { flash('Password minimal 6 karakter.'); redirect('/daftar'); }
    if ($p !== $k) { flash('Password dan konfirmasi belum sama.'); redirect('/daftar'); }

    $ktm = $_FILES['ktm'] ?? null; $mime = '';
    if ($pol) {
        try { $mime = ktm_cek($ktm); }
        catch (SbError $x) { flash($x->getMessage()); redirect('/daftar'); }
    }

    try {
        $sess = auth_signup($em, $p, $n, $h, $pol, $u);
        if ($pol) {
            try { ktm_simpan((string)$sess['user_id'], $ktm, $mime); }
            catch (SbError $x) {
                // KTM gagal tersimpan: batalkan akun agar tidak ada akun Polinema tanpa KTM
                pg_run('DELETE FROM profiles WHERE id = :id', [':id' => $sess['user_id']]);
                throw $x;
            }
        }
    } catch (SbError $x) { flash($x->getMessage()); redirect('/daftar'); }

    // Arahkan kembali ke halaman Masuk sebagai Pembeli agar user login dengan akun yang baru dibuat
    flash('Pendaftaran berhasil! Silakan masuk dengan username dan password Anda.');
    redirect('/login?sebagai=pembeli');
}

function r_keluar(): void { clear_session(); cookie_del('pico_cart'); redirect('/'); }

// ---------------------------------------------------------------- home & menu
function r_home(): void {
    $m = need('pelanggan');
    $cari = trim($_GET['cari'] ?? '');
    $stands = db_sql('SELECT s.id, s.nama, s.kios, s.buka, s.foto_url, (SELECT count(*) FROM menu m WHERE m.stand_id = s.id) AS jml_menu
                      FROM stands s ORDER BY s.kios');
    if ($cari !== '') {
        $t = lc($cari);
        $safe = str_replace(['*', '(', ')', ',', '%'], '', $cari);
        $hit = array_column(db_get('menu?select=stand_id&nama=ilike.' . rawurlencode('*' . $safe . '*')), 'stand_id');
        $stands = array_values(array_filter($stands, fn($s) => str_contains(lc($s['nama']), $t) || in_array($s['id'], $hit)));
    }
    ob_start(); ?>
<header class="hd"><small>Halo, <?= e($m['nama']) ?></small><h1>Mau makan apa hari ini?</h1>
  <form method="get" action="/home" data-live-search><label class="search"><span>🔍</span><input name="cari" value="<?= e($cari) ?>" placeholder="Cari stand atau menu" aria-label="Cari stand atau menu" autocomplete="off"></label></form></header>
<div class="bd"><h3>Stand</h3>
<?php foreach ($stands as $s): $buka = $s['buka']; ?>
  <a class="stand <?= $buka ? '' : 'off' ?>" href="/menu?stand=<?= (int)$s['id'] ?>"><div class="thumb"><?= img($s['foto_url']) ?></div>
    <div><b><?= e($s['nama']) ?></b><small>Kios <?= e($s['kios']) ?></small><small><?= $buka ? (int)$s['jml_menu'] . ' Menu' : 'sedang tutup' ?></small></div>
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
/** Satu pesanan milik pelanggan ini (lengkap dengan stand dan item), atau null. */
function ambil_pesanan(array $m, int $id): ?array {
    return pesanan_muat('p.id = :id AND p.pelanggan_id = :u', [':id' => $id, ':u' => $m['id']])[0] ?? null;
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
<div class="bd c"><p class="mut">Pesanan #<?= e($o['kode']) ?>   <?= e(LAY[$o['layanan']] ?? '') ?></p><h1 style="margin:6px 0"><?= rp($o['total']) ?></h1>
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
    <div class="mut" style="margin-top:6px"><?= e($o['stands']['nama'] ?? '') ?>   <?= e(LAY[$o['layanan']] ?? '') ?>   <?= rp($o['total']) ?></div></div>
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
    $l = pesanan_muat('p.pelanggan_id = :u', [':u' => $m['id']], 'ORDER BY p.created_at DESC LIMIT 30');
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

function profil_inisial(string $nama): string {
    $parts = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return 'P';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
}

function r_profil(): void {
    $m = need('pelanggan');
    $inisial = profil_inisial((string)($m['nama'] ?? 'Pelanggan'));
    $uname = (string)($m['username'] ?? '');
    $nim = (string)($m['nim'] ?? '');
    $hp = (string)($m['no_hp'] ?? '');
    $email = (string)($m['email'] ?? '');
    $fotoUrl = (string)($m['foto_url'] ?? '');

    $lastOrder = pesanan_muat('p.pelanggan_id = :u', [':u' => $m['id']], 'ORDER BY p.created_at DESC LIMIT 1')[0] ?? null;

    $statusMap = [
        'menunggu_bayar' => ['Menunggu Bayar', 'pf-badge-warn'],
        'baru'           => ['Menunggu Konfirmasi', 'pf-badge-info'],
        'diproses'       => ['Diproses', 'pf-badge-info'],
        'siap'           => ['Siap Diambil', 'pf-badge-ok'],
        'selesai'        => ['Selesai', 'pf-badge-ok'],
        'batal'          => ['Dibatalkan', 'pf-badge-err'],
    ];

    $editItems = [
        ['key' => 'nama',     'label' => 'Nama',     'val' => (string)($m['nama'] ?? '-'), 'raw' => (string)($m['nama'] ?? ''), 'type' => 'text',  'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.8-3.6 5-5.5 8-5.5s6.2 1.9 8 5.5"/></svg>'],
        ['key' => 'username', 'label' => 'Username', 'val' => $uname !== '' ? $uname : '-', 'raw' => $uname, 'type' => 'text',  'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a2.5 2.5 0 0 0 5 0v-1a9 9 0 1 0-5.5 8.3"/></svg>'],
        ['key' => 'nim',      'label' => 'NIM',      'val' => $nim !== '' ? $nim : '-',     'raw' => $nim,   'type' => 'text',  'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16c1.2-1.8 2.8-2.5 4.5-2.5S13.8 14.2 15 16"/><line x1="14" y1="9" x2="18" y2="9"/><line x1="15" y1="12" x2="18" y2="12"/></svg>'],
        ['key' => 'no_hp',    'label' => 'No. HP',   'val' => $hp !== '' ? $hp : '-',       'raw' => $hp,    'type' => 'tel',   'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7A2 2 0 0 1 22 16.9z"/></svg>'],
        ['key' => 'email',    'label' => 'Email',    'val' => $email !== '' ? $email : '-', 'raw' => $email, 'type' => 'email', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>'],
    ];

    ob_start(); ?>
<div class="pf-page">
  <header class="pf-hero">
    <div class="pf-topbar">
      <a href="/home" class="pf-back" aria-label="Kembali">← <span>Profil</span></a>
    </div>
    <div class="pf-avatar-wrap">
      <div class="pf-avatar">
        <?php if ($fotoUrl !== ''): ?>
          <img src="<?= e($fotoUrl) ?>" alt="<?= e($m['nama']) ?>">
        <?php else: ?>
          <?= e($inisial) ?>
        <?php endif; ?>
      </div>
      <button type="button" class="pf-cam-btn" data-pf-open="dlg-avatar" aria-label="Ubah foto profil">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      </button>
    </div>
    <h1 class="pf-name"><?= e($m['nama']) ?></h1>
    <div class="pf-sub">
      <span>@<?= e($uname !== '' ? $uname : lc(preg_replace('/\s+/', '', (string)$m['nama']))) ?></span>
      <span class="pf-role-pill">Pelanggan</span>
    </div>
  </header>

  <div class="pf-body">
    <div class="pf-sec-title">INFO AKUN</div>
    <div class="pf-card">
      <?php foreach ($editItems as $item): ?>
        <div class="pf-info-row">
          <span class="pf-ic"><?= $item['icon'] ?></span>
          <div class="pf-info-main">
            <small><?= e($item['label']) ?></small>
            <b><?= e($item['val']) ?></b>
          </div>
          <button type="button" class="pf-edit-btn" data-pf-open="dlg-edit-<?= e($item['key']) ?>" aria-label="Edit <?= e($item['label']) ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          </button>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pf-sec-title">PESANAN TERAKHIR</div>
    <div class="pf-card">
      <?php if ($lastOrder):
        [$stLabel, $stClass] = $statusMap[$lastOrder['status']] ?? [ucfirst((string)$lastOrder['status']), 'pf-badge-info'];
        $layLabel = LAY[$lastOrder['layanan']] ?? ($lastOrder['layanan'] === 'take_away' ? 'Take Away' : 'Dine In');
        $orderHref = ($lastOrder['status'] === 'menunggu_bayar' ? '/qris' : '/status') . '?id=' . (int)$lastOrder['id'];
      ?>
        <a class="pf-order-card" href="<?= e($orderHref) ?>">
          <div class="pf-order-ic">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
          </div>
          <div class="pf-order-main">
            <b><?= e($lastOrder['kode']) ?> · <?= e($lastOrder['stands']['nama'] ?? '-') ?></b>
            <small><?= e($layLabel) ?> · <?= rp($lastOrder['total']) ?></small>
          </div>
          <span class="pf-badge <?= e($stClass) ?>"><?= e($stLabel) ?></span>
        </a>
      <?php else: ?>
        <div class="pf-order-card">
          <div class="pf-order-ic">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
          </div>
          <div class="pf-order-main">
            <b>Belum ada pesanan</b>
            <small>Pesanan terakhirmu akan tampil di sini</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="pf-sec-title">PENGATURAN</div>
    <div class="pf-card">
      <button type="button" class="pf-nav-row" data-pf-open="dlg-password">
        <span class="pf-nav-ic">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1"/></svg>
        </span>
        <span class="pf-nav-label">Ubah kata sandi</span>
        <span class="pf-nav-chev">›</span>
      </button>
      <a href="/keluar" class="pf-nav-row is-logout">
        <span class="pf-nav-ic">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </span>
        <span class="pf-nav-label">Keluar</span>
        <span class="pf-nav-chev">›</span>
      </a>
    </div>
  </div>
</div>

<?php foreach ($editItems as $item): ?>
<dialog id="dlg-edit-<?= e($item['key']) ?>" class="pf-dlg">
  <form method="post" action="/profil/ubah">
    <h3>Ubah <?= e($item['label']) ?></h3>
    <input type="hidden" name="field" value="<?= e($item['key']) ?>">
    <div class="fld">
      <label><?= e($item['label']) ?></label>
      <input type="<?= e($item['type']) ?>" name="value" value="<?= e($item['raw']) ?>" placeholder="Masukkan <?= e(lc($item['label'])) ?>" <?= in_array($item['key'], ['nama', 'username', 'email'], true) ? 'required' : '' ?>>
    </div>
    <div class="pf-dlg-btns">
      <button type="button" class="btn sec" data-pf-close>Batal</button>
      <button type="submit" class="btn">Simpan</button>
    </div>
  </form>
</dialog>
<?php endforeach; ?>

<dialog id="dlg-avatar" class="pf-dlg">
  <form method="post" action="/profil/avatar" enctype="multipart/form-data">
    <h3>Ubah Foto Profil</h3>
    <div class="fld">
      <label>Pilih Foto (JPG/PNG/WebP, maks. 1 MB)</label>
      <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
    </div>
    <div class="pf-dlg-btns">
      <button type="button" class="btn sec" data-pf-close>Batal</button>
      <button type="submit" class="btn">Unggah</button>
    </div>
  </form>
</dialog>

<dialog id="dlg-password" class="pf-dlg">
  <form method="post" action="/profil/password">
    <h3>Ubah Kata Sandi</h3>
    <div class="fld">
      <label>Kata sandi lama</label>
      <input type="password" name="pw_lama" placeholder="Masukkan kata sandi lama" required>
    </div>
    <div class="fld">
      <label>Kata sandi baru</label>
      <input type="password" name="pw_baru" minlength="6" placeholder="Minimal 6 karakter" required>
    </div>
    <div class="fld">
      <label>Konfirmasi kata sandi baru</label>
      <input type="password" name="pw_konf" minlength="6" placeholder="Ulangi kata sandi baru" required>
    </div>
    <div class="pf-dlg-btns">
      <button type="button" class="btn sec" data-pf-close>Batal</button>
      <button type="submit" class="btn">Simpan</button>
    </div>
  </form>
</dialog>

<script>
(function(){
  document.querySelectorAll('[data-pf-open]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var dlg = document.getElementById(btn.getAttribute('data-pf-open'));
      if (dlg && dlg.showModal) dlg.showModal();
    });
  });
  document.querySelectorAll('[data-pf-close]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var dlg = btn.closest('dialog');
      if (dlg && dlg.close) dlg.close();
    });
  });
  document.querySelectorAll('dialog.pf-dlg').forEach(function(dlg){
    dlg.addEventListener('click', function(e){
      if (e.target === dlg) dlg.close();
    });
  });
})();
</script>
<?= nav_pel('/profil');
    layout('Profil', ob_get_clean());
}

function r_profil_ubah(): void {
    $m = need('pelanggan');
    $field = trim((string)($_POST['field'] ?? ''));
    $val = trim((string)($_POST['value'] ?? ''));

    $allowed = ['nama', 'username', 'nim', 'no_hp', 'email'];
    if (!in_array($field, $allowed, true)) {
        flash('Kolom tidak valid.');
        redirect('/profil');
    }
    if ($val === '' && in_array($field, ['nama', 'username', 'email'], true)) {
        flash('Data tidak boleh kosong.');
        redirect('/profil');
    }

    try {
        if ($field === 'username') {
            if (!preg_match('/^[a-zA-Z0-9_.\-]{3,30}$/', $val)) {
                throw new SbError('Username 3-30 karakter (huruf, angka, titik, underscore).');
            }
            $dup = pg_run(
                'SELECT 1 FROM profiles WHERE LOWER(username) = LOWER(:u) AND id <> :id',
                [':u' => $val, ':id' => $m['id']]
            )->fetch();
            if ($dup) throw new SbError('Username sudah digunakan.');
        } elseif ($field === 'email') {
            $val = lc($val);
            if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                throw new SbError('Format email tidak valid.');
            }
            $dup = pg_run(
                'SELECT 1 FROM profiles WHERE LOWER(email) = :e AND id <> :id',
                [':e' => $val, ':id' => $m['id']]
            )->fetch();
            if ($dup) throw new SbError('Email sudah digunakan akun lain.');
        } elseif ($field === 'nim' && $val !== '') {
            $dup = pg_run(
                'SELECT 1 FROM profiles WHERE nim = :n AND id <> :id',
                [':n' => $val, ':id' => $m['id']]
            )->fetch();
            if ($dup) throw new SbError('NIM sudah terdaftar pada akun lain.');
        }

        pg_run(
            'UPDATE profiles SET ' . pg_ident($field) . ' = :v WHERE id = :id',
            [':v' => ($val === '' ? null : $val), ':id' => $m['id']]
        );
        flash('Profil berhasil diperbarui.');
    } catch (SbError $x) {
        flash($x->getMessage());
    }
    redirect('/profil');
}

function r_profil_avatar(): void {
    $m = need('pelanggan');
    $file = $_FILES['avatar'] ?? null;
    try {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new SbError('Pilih foto terlebih dahulu.');
        }
        if (($file['size'] ?? 0) > 1048576) {
            throw new SbError('Ukuran foto profil maksimal 1 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new SbError('Foto harus berformat JPG, PNG, atau WebP.');
        }
        $fh = fopen($file['tmp_name'], 'rb');
        $url = '/foto?t=avatar&id=' . rawurlencode((string)$m['id']) . '&v=' . time();
        try {
            $st = pdo()->prepare(
                'UPDATE profiles SET foto_data = :d, foto_mime = :m, foto_url = :u WHERE id = :id'
            );
            $st->bindValue(':d', $fh, PDO::PARAM_LOB);
            $st->bindValue(':m', $mime);
            $st->bindValue(':u', $url);
            $st->bindValue(':id', $m['id']);
            $st->execute();
            flash('Foto profil diperbarui.');
        } finally {
            if (is_resource($fh)) fclose($fh);
        }
    } catch (Throwable $x) {
        flash($x instanceof SbError ? $x->getMessage() : 'Gagal menyimpan foto profil.');
    }
    redirect('/profil');
}

function r_profil_password(): void {
    $m = me();
    if (!$m) redirect('/');
    $back = ($m['role'] ?? '') === 'penjual' ? '/penjual/profil' : '/profil';
    $lama = (string)($_POST['pw_lama'] ?? '');
    $baru = (string)($_POST['pw_baru'] ?? '');
    $konf = (string)($_POST['pw_konf'] ?? '');

    if ($lama === '' || $baru === '' || $konf === '') {
        flash('Semua kolom kata sandi wajib diisi.');
        redirect($back);
    }
    if (strlen($baru) < 6) {
        flash('Kata sandi baru minimal 6 karakter.');
        redirect($back);
    }
    if ($baru !== $konf) {
        flash('Konfirmasi kata sandi baru tidak cocok.');
        redirect($back);
    }

    try {
        $row = pg_run(
            "SELECT password_hash,
                    (password_hash = crypt(:pw, password_hash)) AS crypt_ok
             FROM profiles WHERE id = :id LIMIT 1",
            [':pw' => $lama, ':id' => $m['id']]
        )->fetch();

        $cryptOk = $row && in_array($row['crypt_ok'] ?? false, [true, 1, '1', 't', 'true'], true);
        $phpOk = $row && !empty($row['password_hash']) && password_verify($lama, $row['password_hash']);

        if (!$row || (!$phpOk && !$cryptOk)) {
            throw new SbError('Kata sandi lama salah.');
        }

        pg_run(
            'UPDATE profiles SET password_hash = :h WHERE id = :id',
            [':h' => password_hash($baru, PASSWORD_DEFAULT), ':id' => $m['id']]
        );
        flash('Kata sandi berhasil diubah.');
    } catch (SbError $x) {
        flash($x->getMessage());
    }
    redirect($back);
}

// ---------------------------------------------------------------- lupa password
function r_lupa(): void {
    if ($m = me()) redirect(home_for($m['role']));
    $as = pintu(); $err = take_flash();
    ob_start(); ?>
<div class="auth"><div class="logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div><h1>Lupa Password</h1><p class="mut">Verifikasi data akun untuk membuat password baru</p>
<form method="post" action="/lupa-password">
  <input type="hidden" name="sebagai" value="<?= e($as) ?>">
  <div class="fld"><input name="u" placeholder="Username / Email / NIM" required></div>
  <div class="fld"><input name="h" type="tel" placeholder="No. Handphone terdaftar" required></div>
  <div class="fld"><input name="p" id="pw" type="password" placeholder="Password baru (min. 6 karakter)" minlength="6" required><button type="button" class="eye" id="eye" aria-label="Tampilkan password">👁</button></div>
  <div class="fld"><input name="k" type="password" placeholder="Konfirmasi password baru" minlength="6" required></div>
  <p class="er"><?= e($err) ?></p><button class="btn">Simpan Password Baru</button>
</form>
<p class="mut c" style="margin-top:16px"><a href="/login?sebagai=<?= e($as) ?>" class="org">← Kembali ke Masuk</a></p></div>
<?php layout('Lupa Password', ob_get_clean());
}

function r_lupa_post(): void {
    $as = pintu();
    $back = '/lupa-password?sebagai=' . $as;
    $u = trim($_POST['u'] ?? '');
    $h = trim($_POST['h'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    $k = (string)($_POST['k'] ?? '');

    if ($u === '' || $h === '' || $p === '') { flash('Lengkapi semua data.'); redirect($back); }
    if (strlen($p) < 6) { flash('Password baru minimal 6 karakter.'); redirect($back); }
    if ($p !== $k) { flash('Password baru dan konfirmasi belum sama.'); redirect($back); }

    try {
        $row = pg_run(
            "SELECT id FROM profiles
             WHERE (LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:u) OR nim = :u)
               AND no_hp = :h
             LIMIT 1",
            [':u' => $u, ':h' => $h]
        )->fetch();

        if (!$row) {
            flash('Data akun atau No. HP tidak cocok.');
            redirect($back);
        }

        pg_run(
            'UPDATE profiles SET password_hash = :pw WHERE id = :id',
            [':pw' => password_hash($p, PASSWORD_DEFAULT), ':id' => $row['id']]
        );
    } catch (SbError $x) {
        flash($x->getMessage());
        redirect($back);
    }

    flash('Password berhasil diubah. Silakan masuk.');
    redirect('/login?sebagai=' . $as);
}