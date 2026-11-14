<?php
// admin.php : halaman admin (dashboard, pengguna, stand, kategori, menu, pesanan, laporan).
// Akun penjual dibuat langsung di tabel profiles (password di-hash).

const ADM_PAID = "('baru','diproses','siap','selesai')";

/** Wajib login sebagai admin; kalau belum, arahkan ke halaman masuk admin. */
function adm_need(): array
{
    if (!me()) {
        redirect('/admin/masuk');
    }

    return need('admin');
}

function adm_layout(string $title, string $body, string $on): void
{
    $nav = [
        ['dashboard', '/admin', 'Dashboard', 'grid', []],
        ['pengguna', '/admin/pengguna?role=pelanggan', 'Pengguna', 'users', [
            ['pengguna:pelanggan', '/admin/pengguna?role=pelanggan', 'Pelanggan'],
            ['pengguna:penjual', '/admin/pengguna?role=penjual', 'Penjual'],
            ['pengguna:admin', '/admin/pengguna?role=admin', 'Admin'],
        ]],
        ['stand', '/admin/stand', 'Stand', 'store', []],
        ['kategori', '/admin/kategori', 'Kategori', 'tag', []],
        ['menu', '/admin/menu', 'Menu', 'fork', []],
        ['pesanan', '/admin/pesanan', 'Pesanan', 'orders', []],
        ['laporan', '/admin/laporan', 'Laporan', 'receipt', []],
    ];
    $grup = explode(':', $on)[0];
    $m = me();
    $ini = $m ? profil_inisial((string) $m['nama']) : 'AD';

    $h = '<header class="adm-mobile-top"><button type="button" class="adm-burger" data-nav-toggle aria-label="Buka menu" aria-expanded="false">' . ic('menu', 22) . '</button>'
        . '<a class="adm-mobile-title" href="/admin">PICO</a><span class="adm-avatar" title="' . e((string) ($m['nama'] ?? '')) . '">' . e($ini) . '</span></header>'
        . '<aside class="adm-side"><a class="adm-brand" href="/admin"><span class="adm-logo"><img src="/img/pico-logo.png" alt="PICO"></span>'
        . '<span class="adm-brand-txt"><b>PICO</b><small>Panel admin</small></span></a><nav>';

    foreach ($nav as [$key, $href, $label, $icon, $sub]) {
        $aktif = ($grup === $key && !$sub) || ($sub && $on === $key);
        $h .= '<a href="' . $href . '" class="' . ($aktif ? 'on' : ($grup === $key ? 'grp' : '')) . '">' . ic($icon, 18) . '<span>' . $label . '</span></a>';
        foreach ($sub as [$sk, $sh, $sl]) {
            $h .= '<a href="' . $sh . '" class="sub ' . ($on === $sk ? 'on' : '') . '"><span>' . $sl . '</span></a>';
        }
    }

    $h .= '</nav><div class="adm-side-foot"><a href="/keluar" class="adm-logout">' . ic('logout', 18) . '<span>Keluar</span></a></div></aside>'
        . '<button type="button" class="adm-scrim" data-nav-toggle aria-label="Tutup menu" tabindex="-1"></button>';

    layout($title, $h . '<main class="adm-main">' . $body . '</main>', ['css' => 'admin', 'wrap' => 'adm']);
}

/** Judul halaman + keterangan + (opsional) tombol aksi di kanan. */
function adm_head(string $h1, string $sub = '', string $aksi = ''): string
{
    return '<div class="adm-page-head"><div><h1>' . $h1 . '</h1>' . ($sub !== '' ? '<p>' . $sub . '</p>' : '') . '</div>'
        . ($aksi !== '' ? '<div class="adm-head-act">' . $aksi . '</div>' : '') . '</div>';
}

/** Grafik batang sederhana. $rows: [['l' => label, 'v' => angka, 't' => tooltip], ...] */
function adm_bars(array $rows): string
{
    $maks = max(1, max(array_column($rows, 'v') ?: [0]));
    $h = '<div class="bars">';
    foreach ($rows as $r) {
        $pct = $r['v'] > 0 ? max(6, (int) round($r['v'] / $maks * 100)) : 0;
        $h .= '<div class="bar-col" title="' . e($r['t'] ?? '') . '"><div class="bar-track"><div class="bar'
            . (($r['v'] === $maks && $maks > 0) ? ' peak' : '') . ($pct ? '' : ' nol') . '" style="height:' . $pct . '%"></div></div><span>' . e($r['l']) . '</span></div>';
    }

    return $h . '</div>';
}

/** Dialog konfirmasi bergaya Figma (ikon, judul, keterangan, tombol Batal / Ya). */
function adm_dialog_konfirmasi(string $id, string $judul, string $teks, string $aksi, array $hidden, string $ya): string
{
    $f = '';
    foreach ($hidden as $k => $v) {
        $f .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }

    return '<dialog id="' . e($id) . '" class="adm-dlg adm-dlg-ask"><div class="ask-ic">' . ic('trash', 22) . '</div><h3>' . e($judul) . '</h3><p>' . e($teks) . '</p>'
        . '<form method="post" action="' . e($aksi) . '">' . $f . '<div class="ask-btns"><button type="button" class="btn sec" onclick="this.closest(\'dialog\').close()">Batal</button>'
        . '<button class="btn danger">' . e($ya) . '</button></div></form></dialog>';
}

/** Cegah rumus Excel/Sheets pada sel CSV yang berasal dari input pengguna. */
function csv_aman($v): string
{
    $v = (string) $v;

    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

// ------------------------------------------------------------------ LOGIN ADMIN
function r_adm_masuk(): void
{
    if ($m = me()) {
        redirect(home_for($m['role']));
    }
    $err = take_flash();
    ob_start(); ?>
<div class="adm-auth-card">
  <div class="adm-auth-logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div>
  <h1>Masuk sebagai admin</h1>
  <p>Kelola operasional kantin PICO</p>
  <form method="post" action="/admin/masuk">
    <label>Email atau username<input name="u" autocomplete="username" required></label>
    <label>Password<input name="p" type="password" autocomplete="current-password" required></label>
    <p class="adm-auth-err"><?= e($err) ?></p>
    <button class="btn">Masuk</button>
  </form>
</div>
<?php layout('Masuk Admin', ob_get_clean(), ['css' => 'admin', 'wrap' => 'adm-auth']);
}

function r_adm_masuk_post(): void
{
    $id = trim($_POST['u'] ?? '');
    $pw = (string) ($_POST['p'] ?? '');
    $email = $id;
    try {
        if ($id !== '' && !str_contains($id, '@')) {
            $email = db_rpc('login_lookup', ['p_identifier' => $id], true);
        }
        if (!$email) {
            throw new SbError('Akun tidak ditemukan.', 400);
        }
        save_session(auth_login((string) $email, $pw));
    } catch (SbError $x) {
        flash($x->getCode() === 403 ? $x->getMessage() : 'Username atau password salah.');
        redirect('/admin/masuk');
    }
    $m = me();
    if (!$m || $m['role'] !== 'admin') {
        clear_session();
        flash('Akun ini bukan akun admin.');
        redirect('/admin/masuk');
    }
    redirect('/admin');
}

// ------------------------------------------------------------------ DASHBOARD
function r_adm_home(): void
{
    adm_need();
    $k = db_sql("SELECT
            COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE) AS hari,
            COALESCE(SUM(total), 0) AS omzet,
            COALESCE(SUM(total) FILTER (WHERE created_at::date = CURRENT_DATE), 0) AS omzet_hari
        FROM pesanan WHERE status IN " . ADM_PAID)[0] ?? ['hari' => 0, 'omzet' => 0, 'omzet_hari' => 0];
    $s = db_sql('SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE buka) AS buka FROM stands')[0] ?? ['total' => 0, 'buka' => 0];
    $by = [];
    foreach (db_sql('SELECT role, COUNT(*) AS n FROM profiles GROUP BY role') as $r) {
        $by[$r['role']] = (int) $r['n'];
    }
    $hr = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    $grafik = [];
    foreach (db_sql("SELECT g.d::date::text AS k, EXTRACT(ISODOW FROM g.d)::int AS dow,
            (SELECT COUNT(*) FROM pesanan p WHERE p.created_at::date = g.d::date AND p.status IN " . ADM_PAID . ") AS n
        FROM generate_series(CURRENT_DATE - 6, CURRENT_DATE, interval '1 day') AS g(d) ORDER BY g.d") as $r) {
        $grafik[] = ['l' => $hr[(int) $r['dow'] - 1], 'v' => (int) $r['n'], 't' => $r['k'] . ': ' . (int) $r['n'] . ' pesanan'];
    }
    $baru = db_sql("SELECT p.id, p.kode, p.total, p.status, u.nama AS pelanggan
        FROM pesanan p LEFT JOIN profiles u ON u.id = p.pelanggan_id
        WHERE p.status IN " . ADM_PAID . " ORDER BY p.created_at DESC LIMIT 5");
    $per = db_sql("SELECT s.nama, COUNT(*) AS n, COALESCE(SUM(p.total), 0) AS rp
        FROM pesanan p JOIN stands s ON s.id = p.stand_id
        WHERE p.status IN " . ADM_PAID . " GROUP BY s.nama ORDER BY rp DESC");
    $omzet = (int) $k['omzet'];
    $pctBuka = (int) $s['total'] ? (int) round($s['buka'] / $s['total'] * 100) : 0;
    ob_start(); ?>
<?= adm_head('Dashboard', 'Ringkasan penjualan dan aktivitas Kantin AX.') ?>
<section class="kpis" aria-label="Ringkasan">
  <article class="kpi"><span class="kpi-ic"><?= ic('orders', 20) ?></span><small>Pesanan hari ini</small><b><?= (int) $k['hari'] ?></b></article>
  <article class="kpi kpi-main"><span class="kpi-ic"><?= ic('wallet', 22) ?></span><small>Transaksi</small><b><?= rp($omzet) ?></b><span class="kpi-note"><?= rp($k['omzet_hari']) ?> hari ini</span></article>
  <article class="kpi"><span class="kpi-ic"><?= ic('store', 20) ?></span><small>Stand aktif</small><b><?= (int) $s['buka'] ?> <em>dari <?= (int) $s['total'] ?></em></b><div class="kpi-bar"><i style="width:<?= $pctBuka ?>%"></i></div></article>
  <article class="kpi"><span class="kpi-ic"><?= ic('users', 20) ?></span><small>Pengguna</small><b><?= array_sum($by) ?></b><span class="kpi-note2"><?= (int) ($by['pelanggan'] ?? 0) ?> pelanggan · <?= (int) ($by['penjual'] ?? 0) ?> penjual</span></article>
</section>
<section class="dash-grid">
  <div class="panel"><div class="panel-head"><h2>Pesanan per hari</h2><small>7 hari terakhir</small></div><?= adm_bars($grafik) ?></div>
  <div class="panel"><div class="panel-head"><h2>Pesanan terbaru</h2><a href="/admin/pesanan">Lihat semua</a></div>
    <div class="tw tw-flat"><table class="mcards"><tr><th>ID</th><th>Pelanggan</th><th class="num">Total</th><th>Status</th></tr>
    <?php foreach ($baru as $o): ?><tr><td data-l="ID"><b>#<?= e($o['kode']) ?></b></td><td data-l="Pelanggan"><?= e($o['pelanggan'] ?? '-') ?></td><td class="num" data-l="Total"><?= rp($o['total']) ?></td><td data-l="Status"><?= tag($o['status']) ?></td></tr><?php endforeach;
    if (!$baru) echo '<tr><td colspan="4" class="mut">Belum ada pesanan.</td></tr>'; ?></table></div></div>
</section>
<div class="adm-sec-head"><h2>Penjualan per stand</h2><small><?= count($per) ?> stand punya pesanan</small></div>
<div class="tw"><table class="tbl-stand mcards"><tr><th>Stand</th><th class="num">Pesanan</th><th class="num">Pendapatan</th><th class="share-col">Kontribusi</th></tr>
<?php foreach ($per as $v): $pct = $omzet ? (int) round($v['rp'] / $omzet * 100) : 0; ?><tr><td data-l="Stand"><b><?= e($v['nama']) ?></b></td><td class="num" data-l="Pesanan"><?= (int) $v['n'] ?></td><td class="num" data-l="Pendapatan"><?= rp($v['rp']) ?></td><td class="share-col" data-l="Kontribusi"><div class="share"><div class="share-bar"><i style="width:<?= $pct ?>%"></i></div><span><?= $pct ?>%</span></div></td></tr><?php endforeach;
if (!$per) echo '<tr><td colspan="4" class="mut">Belum ada pesanan dibayar.</td></tr>'; ?></table></div>
<?php adm_layout('Dashboard', ob_get_clean(), 'dashboard');
}

// ------------------------------------------------------------------ PENGGUNA
function r_adm_pengguna(): void
{
    $me = adm_need();
    ensure_profil_aktif();
    $role = $_GET['role'] ?? 'pelanggan';
    if (!in_array($role, ['pelanggan', 'penjual', 'admin'], true)) $role = 'pelanggan';
    $bisaNonaktif = profil_punya_aktif();
    $ak = $bisaNonaktif ? ', p.aktif' : '';
    $f = $_GET['ktm'] ?? '';
    $u = db_sql("SELECT p.id, p.nama, p.username, p.email, p.no_hp, p.nim, p.role, p.created_at, p.ktm_status, s.nama AS stand_nama $ak
                 FROM profiles p LEFT JOIN stands s ON s.penjual_id = p.id
                 WHERE p.role = :r ORDER BY p.created_at DESC LIMIT 500", [':r' => $role]);
    $menunggu = count(array_filter($u, fn($r) => ($r['ktm_status'] ?? '') === 'menunggu'));
    if ($role === 'pelanggan' && $f === 'menunggu') {
        $u = array_values(array_filter($u, fn($r) => ($r['ktm_status'] ?? '') === 'menunggu'));
    }
    $judul = ['pelanggan' => 'Pelanggan', 'penjual' => 'Penjual', 'admin' => 'Admin'][$role];
    $kolom = ['pelanggan' => 5, 'penjual' => 4, 'admin' => 3][$role];
    $warna = ['menunggu' => 'background:#fff3cd;color:#7a5b00', 'disetujui' => 'background:#d6f3e1;color:#146c3b', 'ditolak' => 'background:#fde1e1;color:#b42318'];
    $lbl = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
    $dlg = '';
    ob_start(); ?>
<?= adm_head('<span class="crumb">Pengguna</span> / ' . $judul, 'Kelola akun ' . strtolower($judul) . ' PICO.', '<a class="btn add-btn" href="/admin/pengguna/tambah">' . ic('plus', 18) . '<span>Tambah pengguna</span></a>') ?>
<?php if (!$bisaNonaktif): ?><div class="adm-note">Fitur nonaktifkan akun belum aktif di database. Jalankan baris <code>ALTER TABLE profiles ADD COLUMN IF NOT EXISTS aktif BOOLEAN NOT NULL DEFAULT TRUE;</code> (ada di sql/schema.sql), lalu muat ulang halaman ini.</div><?php endif; ?>
<div class="adm-user-tabs"><?php foreach (['pelanggan' => 'Pelanggan', 'penjual' => 'Penjual', 'admin' => 'Admin'] as $k => $l): ?><a href="/admin/pengguna?role=<?= $k ?>" class="<?= $role === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="adm-toolbar"><label class="adm-search"><?= ic('search', 18) ?><input type="search" placeholder="Cari <?= e(strtolower($judul)) ?>" data-table-search="users-table"></label><span class="count-txt"><b><?= count($u) ?></b> akun</span>
  <?php if ($role === 'pelanggan'): ?><a class="ktm-link" href="/admin/pengguna?role=pelanggan<?= $f === 'menunggu' ? '' : '&amp;ktm=menunggu' ?>"><?= $f === 'menunggu' ? 'Tampilkan semua' : 'KTM menunggu verifikasi (' . $menunggu . ')' ?></a><?php endif; ?></div>
<div class="tw"><table id="users-table" class="mcards">
  <tr><th>Nama</th>
    <?php if ($role === 'pelanggan'): ?><th>Email</th><th>NIM</th><th>KTM</th>
    <?php elseif ($role === 'penjual'): ?><th>No. HP</th><th>Stand</th>
    <?php else: ?><th>Email</th><?php endif; ?>
    <th class="act-col">Aksi</th></tr>
<?php foreach ($u as $r):
    $ks = $r['ktm_status'] ?? '';
    $nonaktif = $bisaNonaktif && profil_nonaktif($r);
    $diri = (string) $r['id'] === (string) $me['id'];
    $vid = 'dlg-v-' . preg_replace('/[^a-zA-Z0-9]/', '', (string) $r['id']);
    $nid = 'dlg-n-' . preg_replace('/[^a-zA-Z0-9]/', '', (string) $r['id']);
    ob_start(); ?>
<dialog id="<?= $vid ?>" class="adm-dlg"><h3><?= e($r['nama']) ?></h3>
  <dl class="adm-dl">
    <div><dt>Peran</dt><dd><?= e($r['role']) ?></dd></div>
    <div><dt>Username</dt><dd><?= e($r['username'] ?: '-') ?></dd></div>
    <div><dt>Email</dt><dd><?= e($r['email'] ?: '-') ?></dd></div>
    <div><dt>No. HP</dt><dd><?= e($r['no_hp'] ?: '-') ?></dd></div>
    <?php if ($role === 'pelanggan'): ?><div><dt>NIM</dt><dd><?= e($r['nim'] ?: '-') ?></dd></div><?php endif; ?>
    <?php if ($role === 'penjual'): ?><div><dt>Stand</dt><dd><?= e($r['stand_nama'] ?: 'Belum terhubung') ?></dd></div><?php endif; ?>
    <div><dt>Terdaftar</dt><dd><?= waktu($r['created_at']) ?></dd></div>
    <?php if ($bisaNonaktif): ?><div><dt>Status akun</dt><dd><?= $nonaktif ? 'Nonaktif' : 'Aktif' ?></dd></div><?php endif; ?>
  </dl>
  <div class="ask-btns"><button type="button" class="btn" onclick="this.closest('dialog').close()">Tutup</button></div></dialog>
<?php if ($bisaNonaktif && !$diri) echo adm_dialog_konfirmasi($nid, $nonaktif ? 'Aktifkan akun?' : 'Nonaktifkan akun?',
        $nonaktif ? $r['nama'] . ' akan bisa masuk lagi ke PICO.' : $r['nama'] . ' tidak akan bisa masuk sampai akunnya diaktifkan kembali. Datanya tetap tersimpan.',
        '/admin/pengguna/status', ['id' => $r['id'], 'aksi' => $nonaktif ? 'aktif' : 'nonaktif', 'role' => $role], $nonaktif ? 'Ya, aktifkan' : 'Ya, nonaktifkan');
    $dlg .= ob_get_clean(); ?>
  <tr class="<?= $nonaktif ? 'is-off' : '' ?>">
    <td data-l="Nama"><div class="who"><span class="av"><?= e(profil_inisial((string) $r['nama'])) ?></span><div><b><?= e($r['nama']) ?></b><?php if ($nonaktif): ?> <span class="pill-off">Nonaktif</span><?php endif; ?><?php if ($role !== 'admin' && $r['username']): ?><small class="mut">@<?= e($r['username']) ?></small><?php endif; ?></div></div></td>
    <?php if ($role === 'pelanggan'): ?>
      <td data-l="Email" class="mut"><?= e($r['email'] ?: '-') ?></td>
      <td data-l="NIM"><?= e($r['nim'] ?: '-') ?></td>
      <td data-l="KTM"><?php if ($ks === ''): ?><span class="mut">-</span><?php else: ?>
        <span class="ktm-chip" style="<?= $warna[$ks] ?? '' ?>"><?= e($lbl[$ks] ?? $ks) ?></span>
        <a class="ktm-view" href="/ktm?id=<?= e($r['id']) ?>" target="_blank" rel="noopener">Lihat KTM</a>
        <form method="post" action="/admin/ktm" class="ktm-act"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
          <?php if ($ks !== 'disetujui'): ?><button name="aksi" value="setujui" class="ok">Setujui</button><?php endif; ?>
          <?php if ($ks !== 'ditolak'): ?><button name="aksi" value="tolak" class="no">Tolak</button><?php endif; ?></form>
      <?php endif; ?></td>
    <?php elseif ($role === 'penjual'): ?>
      <td data-l="No. HP"><?= e($r['no_hp'] ?: '-') ?></td>
      <td data-l="Stand"><?= $r['stand_nama'] ? e($r['stand_nama']) : '<span class="mut">Belum terhubung</span>' ?></td>
    <?php else: ?>
      <td data-l="Email" class="mut"><?= e($r['email'] ?: '-') ?></td>
    <?php endif; ?>
    <td data-l="Aksi" class="act-col"><div class="ic-btns">
      <button type="button" class="ic-btn" data-open="<?= $vid ?>" aria-label="Lihat <?= e($r['nama']) ?>"><?= ic('eye', 17) ?><span>Lihat</span></button>
      <?php if ($bisaNonaktif && !$diri): ?><button type="button" class="ic-btn <?= $nonaktif ? 'ok' : 'danger' ?>" data-open="<?= $nid ?>" aria-label="<?= $nonaktif ? 'Aktifkan' : 'Nonaktifkan' ?> <?= e($r['nama']) ?>"><?= ic($nonaktif ? 'check' : 'trash', 17) ?><span><?= $nonaktif ? 'Aktifkan' : 'Nonaktifkan' ?></span></button><?php endif; ?>
    </div></td>
  </tr>
<?php endforeach; if (!$u) echo '<tr><td colspan="' . $kolom . '" class="mut">Belum ada ' . e(strtolower($judul)) . '.</td></tr>'; ?></table></div>
<?= $dlg ?>
<?php adm_layout('Pengguna ' . $judul, ob_get_clean(), 'pengguna:' . $role);
}

function r_adm_pengguna_tambah(): void
{
    adm_need();
    $stands = db_sql('SELECT id, nama, kios FROM stands WHERE penjual_id IS NULL ORDER BY kios');
    $err = take_flash();
    ob_start(); ?>
<?= adm_head('<span class="crumb">Pengguna</span> / Tambah pengguna', 'Buat akun baru untuk pelanggan, penjual, atau admin.') ?>
<form method="post" action="/admin/pengguna/simpan" class="adm-form">
  <?php if ($err !== ''): ?><div class="adm-note"><?= e($err) ?></div><?php endif; ?>
  <div class="af-grid">
    <label>Nama lengkap<input name="nama" placeholder="Nama lengkap" required maxlength="80"></label>
    <label>Email<input name="email" type="email" placeholder="nama@email.com" required></label>
    <label>No. HP<input name="no_hp" inputmode="tel" placeholder="08xxxxxxxxxx"></label>
    <label>NIM<input name="nim" placeholder="Khusus pelanggan (opsional)"></label>
    <label>Peran<select name="role" required><option value="">Pilih peran</option><option value="pelanggan">Pelanggan</option><option value="penjual">Penjual</option><option value="admin">Admin</option></select></label>
    <label>Password<input name="password" type="password" minlength="6" placeholder="Minimal 6 karakter" required autocomplete="new-password"></label>
    <label class="af-wide">Stand (khusus penjual)<select name="stand"><option value="">Hubungkan nanti</option><?php foreach ($stands as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nama']) ?> · Kios <?= e($s['kios']) ?></option><?php endforeach; ?></select></label>
  </div>
  <div class="af-btns"><button class="btn">Simpan pengguna</button><a class="btn sec" href="/admin/pengguna?role=pelanggan">Batal</a></div>
</form>
<?php adm_layout('Tambah Pengguna', ob_get_clean(), 'pengguna');
}

function r_adm_pengguna_simpan(): void
{
    adm_need();
    $role = (string) ($_POST['role'] ?? '');
    try {
        $nama = trim($_POST['nama'] ?? '');
        $email = lc(trim($_POST['email'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $hp = trim($_POST['no_hp'] ?? '');
        $nim = trim($_POST['nim'] ?? '');
        $sid = (int) ($_POST['stand'] ?? 0);
        if ($nama === '') throw new SbError('Nama wajib diisi.');
        if (!in_array($role, ['pelanggan', 'penjual', 'admin'], true)) throw new SbError('Pilih peran pengguna.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new SbError('Email tidak valid.');
        if (strlen($pw) < 6) throw new SbError('Password minimal 6 karakter.');
        if (db_get('profiles?select=id&email=eq.' . rawurlencode($email))) throw new SbError('Email sudah terdaftar.');
        if ($nim !== '' && db_get('profiles?select=id&nim=eq.' . rawurlencode($nim))) throw new SbError('NIM sudah terdaftar.');

        pdo()->beginTransaction();
        try {
            $u = db_write('POST', 'profiles', [
                'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'nama' => $nama, 'no_hp' => $hp ?: null, 'nim' => $nim ?: null, 'role' => $role,
            ]);
            if ($role === 'penjual' && $sid) {
                $ok = db_write('PATCH', 'stands?id=eq.' . $sid . '&penjual_id=is.null', ['penjual_id' => $u[0]['id']]);
                if (!$ok) throw new SbError('Stand tidak ditemukan atau sudah punya penjual.');
            }
            pdo()->commit();
        } catch (Throwable $t) {
            if (pdo()->inTransaction()) pdo()->rollBack();
            throw $t;
        }
        flash('Pengguna ditambahkan');
        redirect('/admin/pengguna?role=' . $role);
    } catch (SbError $x) {
        flash($x->getMessage());
        redirect('/admin/pengguna/tambah');
    }
}

function r_adm_pengguna_status(): void
{
    $me = adm_need();
    ensure_profil_aktif();
    $role = in_array($_POST['role'] ?? '', ['pelanggan', 'penjual', 'admin'], true) ? $_POST['role'] : 'pelanggan';
    try {
        if (!profil_punya_aktif()) throw new SbError('Kolom "aktif" belum ada di database. Jalankan sql/schema.sql dulu.');
        $id = (string) ($_POST['id'] ?? '');
        if ($id === (string) $me['id']) throw new SbError('Akun yang sedang kamu pakai tidak bisa dinonaktifkan.');
        $aktif = ($_POST['aksi'] ?? '') === 'aktif';
        $r = db_write('PATCH', 'profiles?id=eq.' . rawurlencode($id), ['aktif' => $aktif]);
        if (!$r) throw new SbError('Pengguna tidak ditemukan.');
        flash($aktif ? 'Akun diaktifkan' : 'Akun dinonaktifkan');
    } catch (SbError $x) {
        flash($x->getMessage());
    }
    redirect('/admin/pengguna?role=' . $role);
}

// ------------------------------------------------------------------ STAND
function r_adm_stand(): void
{
    adm_need();
    $stands = db_sql('SELECT s.id, s.nama, s.kios, s.lokasi, s.buka, s.penjual_id, p.nama AS penjual_nama, p.username AS penjual_username
                    FROM stands s LEFT JOIN profiles p ON p.id = s.penjual_id
                    ORDER BY s.kios');
    $jmlBuka = count(array_filter($stands, fn($x) => $x['buka']));
    $jmlPenjual = count(array_filter($stands, fn($x) => $x['penjual_id']));
    ob_start(); ?>
<?= adm_head('Stand &amp; Penjual', 'Atur stand kantin, akun penjual, dan status buka tutupnya.') ?>
<div class="adm-chips"><span class="chip"><b><?= count($stands) ?></b> stand</span><span class="chip ok"><b><?= $jmlBuka ?></b> buka</span><span class="chip"><b><?= $jmlPenjual ?></b> sudah ada penjual</span></div>
<div class="adm-toolbar"><label class="adm-search"><?= ic('search', 18) ?><input type="search" placeholder="Cari stand" data-table-search="stand-table"></label></div>
<details class="adm-add">
  <summary><?= ic('plus', 18) ?><span>Tambah stand baru</span></summary>
  <form method="post" action="/admin/stand/tambah" class="frm"><input name="nama" placeholder="Nama stand" required><input name="kios" type="number" min="1" placeholder="No. kios" required><input name="lokasi" placeholder="Lokasi (opsional)"><button class="btn">Simpan stand</button></form>
</details>
<div class="tw"><table id="stand-table" class="tbl-stand mcards"><tr><th>Kios</th><th>Stand</th><th>Lokasi</th><th>Status</th><th>Penjual</th><th>Aksi</th></tr>
<?php foreach ($stands as $s): ?><tr><td data-l="Kios"><?= kios_tile($s['kios'], 'xs') ?></td><td data-l="Stand"><b><?= e($s['nama']) ?></b></td><td data-l="Lokasi" class="mut"><?= e($s['lokasi'] ?: '-') ?></td>
<td data-l="Status"><form method="post" action="/admin/stand/buka" class="sw-cell"<?= $s['buka'] ? ' data-confirm="Tutup stand ' . e($s['nama']) . '? Pelanggan tidak bisa memesan sampai dibuka lagi."' : '' ?>><input type="hidden" name="stand" value="<?= (int) $s['id'] ?>"><?= saklar((bool) $s['buka'], 'Buka atau tutup ' . $s['nama']) ?><span class="sw-txt <?= $s['buka'] ? 'on' : '' ?>"><?= $s['buka'] ? 'Aktif' : 'Nonaktif' ?></span></form></td>
<td data-l="Penjual"><?php if ($s['penjual_id']): ?><div class="who"><span class="av"><?= e(profil_inisial((string) ($s['penjual_nama'] ?? ''))) ?></span><div><b><?= e($s['penjual_nama'] ?? '') ?></b><small class="mut">@<?= e($s['penjual_username'] ?? '') ?></small></div></div><?php else: ?><span class="mut">Belum ada</span><?php endif; ?></td>
<td data-l="Aksi"><details class="act"><summary><?= $s['penjual_id'] ? 'Reset password' : 'Buat akun penjual' ?></summary>
    <?php if ($s['penjual_id']): ?>
    <form method="post" action="/admin/stand/reset" class="frm"><input type="hidden" name="stand" value="<?= (int) $s['id'] ?>"><input name="password" type="password" minlength="6" placeholder="Password baru" required><button class="btn sm">Ganti</button></form>
    <?php else: ?>
    <form method="post" action="/admin/stand/penjual" class="frm"><input type="hidden" name="stand" value="<?= (int) $s['id'] ?>"><input name="nama" placeholder="Nama penjual" required><input name="email" type="email" placeholder="Email login" required><input name="password" type="password" minlength="6" placeholder="Password awal" required><button class="btn sm">Buat akun</button></form>
    <?php endif; ?></details></td></tr>
<?php endforeach; if (!$stands) echo '<tr><td colspan="6" class="mut">Belum ada stand.</td></tr>'; ?></table></div>
<?php adm_layout('Stand', ob_get_clean(), 'stand');
}

// ------------------------------------------------------------------ AKSI STAND
function r_adm_stand_buka(): void {
    adm_need();
    try {
        $id = (int)($_POST['stand'] ?? 0);
        $s = db_get('stands?select=id,nama,buka&id=eq.' . $id)[0] ?? null;
        if (!$s) throw new SbError('Stand tidak ditemukan.');
        db_write('PATCH', 'stands?id=eq.' . $id, ['buka' => !$s['buka']]);
        flash($s['buka'] ? 'Stand ' . $s['nama'] . ' ditutup' : 'Stand ' . $s['nama'] . ' dibuka');
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_stand_tambah(): void {
    adm_need();
    try { db_write('POST', 'stands', ['nama' => trim($_POST['nama'] ?? ''), 'kios' => (int)($_POST['kios'] ?? 0), 'lokasi' => trim($_POST['lokasi'] ?? '') ?: null]); flash('Stand ditambahkan'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_stand_penjual(): void {
    adm_need(); $sid = (int)($_POST['stand'] ?? 0);
    try {
        $email = lc(trim($_POST['email'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        $nama = trim($_POST['nama'] ?? '') ?: 'Penjual';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new SbError('Email tidak valid.');
        if (strlen($pw) < 6) throw new SbError('Password minimal 6 karakter.');
        if (db_get('profiles?select=id&email=eq.' . rawurlencode($email))) throw new SbError('Email sudah terdaftar.');

        // Satu paket: buat akun penjual + hubungkan ke stand. Gagal di tengah = dibatalkan semua.
        pdo()->beginTransaction();
        try {
            $u = db_write('POST', 'profiles', ['email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'nama' => $nama, 'role' => 'penjual']);
            $ok = db_write('PATCH', 'stands?id=eq.' . $sid . '&penjual_id=is.null', ['penjual_id' => $u[0]['id']]);
            if (!$ok) throw new SbError('Stand tidak ditemukan atau sudah punya penjual.');
            pdo()->commit();
        } catch (Throwable $t) {
            if (pdo()->inTransaction()) pdo()->rollBack();
            throw $t;
        }
        flash('Akun penjual dibuat');
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_stand_reset(): void {
    adm_need();
    try {
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 6) throw new SbError('Password minimal 6 karakter.');
        $s = db_get('stands?select=penjual_id&id=eq.' . (int)($_POST['stand'] ?? 0))[0] ?? null;
        if (!$s || !$s['penjual_id']) throw new SbError('Stand belum punya penjual.');
        db_write('PATCH', 'profiles?id=eq.' . rawurlencode($s['penjual_id']), ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
        flash('Password diganti');
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}


// ------------------------------------------------------------------ KATEGORI
function r_adm_kategori(): void
{
    adm_need();
    $kat = db_sql('SELECT k.id, k.nama, (SELECT COUNT(*) FROM menu m WHERE m.kategori_id = k.id) AS jml FROM kategori k ORDER BY k.nama');
    ob_start(); ?>
<?= adm_head('Kategori', 'Pengelompokan menu yang dipakai semua stand.') ?>
<div class="adm-toolbar"><label class="adm-search"><?= ic('search', 18) ?><input type="search" placeholder="Cari kategori" data-table-search="kat-table"></label><span class="count-txt"><b><?= count($kat) ?></b> kategori</span></div>
<details class="adm-add">
  <summary><?= ic('plus', 18) ?><span>Tambah kategori</span></summary>
  <form method="post" action="/admin/kategori/tambah" class="frm"><input name="nama" placeholder="Nama kategori" required maxlength="40"><button class="btn">Simpan kategori</button></form>
</details>
<div class="tw"><table id="kat-table" class="mcards"><tr><th>Kategori</th><th class="num">Jumlah menu</th><th class="act-col">Aksi</th></tr>
<?php foreach ($kat as $k): ?><tr><td data-l="Kategori"><b><?= e($k['nama']) ?></b></td><td class="num" data-l="Jumlah menu"><?= (int) $k['jml'] ?></td>
<td class="act-col" data-l="Aksi"><?php if ((int) $k['jml'] === 0): ?>
  <form method="post" action="/admin/kategori/hapus" data-confirm="Hapus kategori <?= e($k['nama']) ?>?"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="ic-btn danger" aria-label="Hapus <?= e($k['nama']) ?>"><?= ic('trash', 17) ?><span>Hapus</span></button></form>
<?php else: ?><span class="mut small">Dipakai menu</span><?php endif; ?></td></tr>
<?php endforeach; if (!$kat) echo '<tr><td colspan="3" class="mut">Belum ada kategori.</td></tr>'; ?></table></div>
<?php adm_layout('Kategori', ob_get_clean(), 'kategori');
}

function r_adm_kategori_tambah(): void
{
    adm_need();
    try {
        $n = trim($_POST['nama'] ?? '');
        if ($n === '') throw new SbError('Nama kategori wajib diisi.');
        if (db_sql('SELECT 1 FROM kategori WHERE LOWER(nama) = LOWER(:n)', [':n' => $n])) throw new SbError('Kategori itu sudah ada.');
        db_write('POST', 'kategori', ['nama' => $n]);
        flash('Kategori ditambahkan');
    } catch (SbError $x) {
        flash($x->getMessage());
    }
    redirect('/admin/kategori');
}

function r_adm_kategori_hapus(): void
{
    adm_need();
    try {
        $id = (int) ($_POST['id'] ?? 0);
        $n = (int) (db_sql('SELECT COUNT(*) AS n FROM menu WHERE kategori_id = :k', [':k' => $id])[0]['n'] ?? 0);
        if ($n > 0) throw new SbError('Kategori masih dipakai ' . $n . ' menu.');
        db_delete('kategori?id=eq.' . $id);
        flash('Kategori dihapus');
    } catch (SbError $x) {
        flash($x->getMessage());
    }
    redirect('/admin/kategori');
}

// ------------------------------------------------------------------ MENU (semua stand)
function r_adm_menu(): void
{
    adm_need();
    $menu = db_sql('SELECT m.id, m.nama, m.harga, m.stok, m.tersedia, s.nama AS stand, s.kios, k.nama AS kategori
                    FROM menu m JOIN stands s ON s.id = m.stand_id LEFT JOIN kategori k ON k.id = m.kategori_id
                    ORDER BY s.kios, m.nama LIMIT 500');
    $dlg = '';
    ob_start(); ?>
<?= adm_head('Menu', 'Semua menu dari seluruh stand kantin.') ?>
<div class="adm-toolbar"><label class="adm-search"><?= ic('search', 18) ?><input type="search" placeholder="Cari menu atau stand" data-table-search="menu-table"></label><span class="count-txt"><b><?= count($menu) ?></b> menu</span></div>
<div class="tw"><table id="menu-table" class="mcards"><tr><th>Menu</th><th>Stand</th><th>Kategori</th><th class="num">Harga</th><th>Status</th><th class="act-col">Aksi</th></tr>
<?php foreach ($menu as $m):
    $stok = (int) $m['stok'];
    if (!$m['tersedia']) [$sk, $sl] = ['off', 'Disembunyikan'];
    elseif ($stok <= 0) [$sk, $sl] = ['bad', 'Habis'];
    elseif ($stok <= 5) [$sk, $sl] = ['warn', 'Stok ' . $stok];
    else [$sk, $sl] = ['ok', 'Tersedia'];
    $vid = 'dlg-m-' . (int) $m['id'];
    ob_start(); ?>
<dialog id="<?= $vid ?>" class="adm-dlg"><h3><?= e($m['nama']) ?></h3>
  <dl class="adm-dl"><div><dt>Stand</dt><dd><?= e($m['stand']) ?> · Kios <?= e($m['kios']) ?></dd></div><div><dt>Kategori</dt><dd><?= e($m['kategori'] ?: '-') ?></dd></div><div><dt>Harga</dt><dd><?= rp($m['harga']) ?></dd></div><div><dt>Stok</dt><dd><?= $stok ?></dd></div><div><dt>Status</dt><dd><?= e($sl) ?></dd></div></dl>
  <div class="ask-btns"><button type="button" class="btn" onclick="this.closest('dialog').close()">Tutup</button></div></dialog>
<?php $dlg .= ob_get_clean(); ?>
  <tr><td data-l="Menu"><b><?= e($m['nama']) ?></b></td><td data-l="Stand" class="mut"><?= e($m['stand']) ?></td><td data-l="Kategori" class="mut"><?= e($m['kategori'] ?: '-') ?></td><td class="num" data-l="Harga"><?= rp($m['harga']) ?></td>
  <td data-l="Status"><span class="st-pill st-<?= $sk ?>"><?= e($sl) ?></span></td>
  <td class="act-col" data-l="Aksi"><button type="button" class="ic-btn" data-open="<?= $vid ?>" aria-label="Lihat <?= e($m['nama']) ?>"><?= ic('eye', 17) ?><span>Lihat</span></button></td></tr>
<?php endforeach; if (!$menu) echo '<tr><td colspan="6" class="mut">Belum ada menu.</td></tr>'; ?></table></div>
<?= $dlg ?>
<?php adm_layout('Menu', ob_get_clean(), 'menu');
}

// ------------------------------------------------------------------ PESANAN
function r_adm_pesanan(): void
{
    adm_need();
    if (function_exists('ensure_bukti_columns')) {
        ensure_bukti_columns();
    }
    $st = $_GET['status'] ?? '';
    $filter = isset(STATUS_LABEL[$st]);
    $os = db_sql(
        "SELECT p.id, p.kode, p.status, p.layanan, p.total, p.created_at, p.bukti_url,
                s.nama AS stand_nama, u.nama AS pelanggan_nama,
                (SELECT string_agg(i.jumlah::text || ' ' || i.nama, ', ' ORDER BY i.id) FROM pesanan_item i WHERE i.pesanan_id = p.id) AS menu_ringkas
        FROM pesanan p
        LEFT JOIN stands s ON s.id = p.stand_id
        LEFT JOIN profiles u ON u.id = p.pelanggan_id"
        . ($filter ? ' WHERE p.status = :st' : '')
        . ' ORDER BY p.created_at DESC LIMIT 100',
        $filter ? [':st' => $st] : []
    );
    ob_start(); ?>
<?= adm_head('Pesanan', 'Semua pesanan dari seluruh stand (100 terbaru).') ?>
<div class="chips"><a href="/admin/pesanan" class="<?= $st === '' ? 'on' : '' ?>">Semua</a><?php foreach (STATUS_LABEL as $k => $l): ?><a href="/admin/pesanan?status=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="adm-toolbar"><label class="adm-search"><?= ic('search', 18) ?><input type="search" placeholder="Cari ID atau pelanggan" data-table-search="ord-table"></label><span class="count-txt"><b><?= count($os) ?></b> pesanan</span></div>
<div class="tw">
  <table id="ord-table" class="mcards">
    <tr><th>ID</th><th>Pelanggan</th><th>Stand</th><th>Menu</th><th>Layanan</th><th class="num">Total</th><th>Bukti</th><th>Status</th><th>Waktu</th></tr>
    <?php foreach ($os as $o):
      $buktiUrl = trim((string) ($o['bukti_url'] ?? ''));
      $dlgId = 'dlg-adm-bukti-' . (int) $o['id'];
    ?>
    <tr>
      <td data-l="ID"><b>#<?= e($o['kode']) ?></b></td>
      <td data-l="Pelanggan"><?= e($o['pelanggan_nama'] ?? '-') ?></td>
      <td data-l="Stand"><?= e($o['stand_nama'] ?? '') ?></td>
      <td data-l="Menu" class="mut"><?= e($o['menu_ringkas'] ?? '') ?></td>
      <td data-l="Layanan"><?= e(LAY[$o['layanan']] ?? '') ?></td>
      <td class="num" data-l="Total"><?= rp($o['total']) ?></td>
      <td data-l="Bukti">
        <?php if ($buktiUrl !== ''): ?>
          <button type="button" class="ic-btn ok" data-open="<?= e($dlgId) ?>"><img src="<?= e($buktiUrl) ?>" alt="" class="bukti-th"><span>Lihat</span></button>
          <dialog id="<?= e($dlgId) ?>" class="adm-dlg adm-dlg-bukti">
            <h3>Bukti Pembayaran #<?= e($o['kode']) ?></h3>
            <p class="mut"><?= e($o['pelanggan_nama'] ?? 'Pembeli') ?> · <?= e($o['stand_nama'] ?? '') ?> · <?= rp($o['total']) ?></p>
            <img src="<?= e($buktiUrl) ?>" alt="Bukti Pembayaran #<?= e($o['kode']) ?>" class="bukti-big">
            <div class="ask-btns"><a href="<?= e($buktiUrl) ?>" target="_blank" rel="noopener" class="btn sec">Buka penuh</a><button type="button" class="btn" onclick="this.closest('dialog').close()">Tutup</button></div>
          </dialog>
        <?php else: ?>
          <span class="mut small">Belum ada</span>
        <?php endif; ?>
      </td>
      <td data-l="Status"><?= tag($o['status']) ?></td>
      <td data-l="Waktu" class="mut"><?= waktu($o['created_at']) ?></td>
    </tr>
    <?php endforeach; if (!$os) echo '<tr><td colspan="9" class="mut">Tidak ada pesanan.</td></tr>'; ?>
  </table>
</div>
<?php adm_layout('Pesanan', ob_get_clean(), 'pesanan');
}

// ------------------------------------------------------------------ LAPORAN
/** Membaca filter laporan dari URL: rentang tanggal (maks 92 hari) dan stand. */
function adm_laporan_filter(): array
{
    $today = db_sql('SELECT CURRENT_DATE::text AS t')[0]['t'];
    $ok = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
    $b = $ok($_GET['sampai'] ?? null) ? $_GET['sampai'] : $today;
    $a = $ok($_GET['dari'] ?? null) ? $_GET['dari'] : date('Y-m-d', strtotime($b . ' -6 day'));
    if ($a > $b) [$a, $b] = [$b, $a];
    if (strtotime($b) - strtotime($a) > 91 * 86400) $a = date('Y-m-d', strtotime($b . ' -91 day'));

    return ['dari' => $a, 'sampai' => $b, 'stand' => max(0, (int) ($_GET['stand'] ?? 0))];
}

function r_adm_laporan(): void
{
    adm_need();
    $fl = adm_laporan_filter();
    $par = [':a' => $fl['dari'], ':b' => $fl['sampai']];
    $cond = "p.status = 'selesai' AND p.created_at::date BETWEEN CAST(:a AS date) AND CAST(:b AS date)";
    if ($fl['stand']) {
        $cond .= ' AND p.stand_id = :s';
        $par[':s'] = $fl['stand'];
    }
    $stands = db_sql('SELECT id, nama FROM stands ORDER BY kios');
    $hari = [];
    for ($d = strtotime($fl['dari']); $d <= strtotime($fl['sampai']); $d += 86400) {
        $hari[date('Y-m-d', $d)] = ['n' => 0, 'rp' => 0, 'ts' => $d];
    }
    foreach (db_sql("SELECT p.created_at::date::text AS k, COUNT(*) AS n, COALESCE(SUM(p.total), 0) AS rp FROM pesanan p WHERE $cond GROUP BY 1", $par) as $r) {
        if (isset($hari[$r['k']])) {
            $hari[$r['k']]['n'] = (int) $r['n'];
            $hari[$r['k']]['rp'] = (int) $r['rp'];
        }
    }
    $totN = array_sum(array_column($hari, 'n'));
    $totRp = array_sum(array_column($hari, 'rp'));
    $rata = $totN ? (int) round($totRp / $totN) : 0;
    $nama = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $banyak = count($hari);
    $grafik = [];
    $i = 0;
    foreach ($hari as $k => $v) {
        $lbl = $banyak <= 7 ? $nama[(int) date('w', $v['ts'])] : (($i % max(1, (int) ceil($banyak / 10)) === 0) ? date('j', $v['ts']) : '');
        $grafik[] = ['l' => $lbl, 'v' => $v['rp'], 't' => $k . ': ' . rp($v['rp']) . ' · ' . $v['n'] . ' pesanan'];
        $i++;
    }
    $per = db_sql("SELECT s.nama, COUNT(*) AS n, COALESCE(SUM(p.total), 0) AS rp
        FROM pesanan p JOIN stands s ON s.id = p.stand_id WHERE $cond GROUP BY s.nama ORDER BY rp DESC", $par);
    $qs = http_build_query(['dari' => $fl['dari'], 'sampai' => $fl['sampai'], 'stand' => $fl['stand']]);
    ob_start(); ?>
<?= adm_head('Laporan', 'Pendapatan dari pesanan yang sudah selesai.', '<a class="btn add-btn" href="/admin/laporan/ekspor?' . e($qs) . '">' . ic('download', 18) . '<span>Ekspor laporan</span></a>') ?>
<form method="get" action="/admin/laporan" class="lap-filter">
  <label><span>Dari</span><input type="date" name="dari" value="<?= e($fl['dari']) ?>"></label>
  <label><span>Sampai</span><input type="date" name="sampai" value="<?= e($fl['sampai']) ?>"></label>
  <label><span>Stand</span><select name="stand"><option value="0">Semua stand</option><?php foreach ($stands as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $fl['stand'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['nama']) ?></option><?php endforeach; ?></select></label>
  <button class="btn sm">Terapkan</button>
</form>
<section class="kpis kpis-3">
  <article class="kpi"><span class="kpi-ic"><?= ic('wallet', 20) ?></span><small>Total transaksi</small><b><?= rp($totRp) ?></b></article>
  <article class="kpi"><span class="kpi-ic"><?= ic('orders', 20) ?></span><small>Jumlah pesanan</small><b><?= $totN ?></b></article>
  <article class="kpi"><span class="kpi-ic"><?= ic('receipt', 20) ?></span><small>Rata-rata pesanan</small><b><?= rp($rata) ?></b></article>
</section>
<div class="panel"><div class="panel-head"><h2>Transaksi per hari</h2><small><?= $banyak ?> hari</small></div><?= adm_bars($grafik) ?></div>
<div class="adm-sec-head"><h2>Per stand</h2><small><?= count($per) ?> stand</small></div>
<div class="tw"><table class="tbl-stand mcards"><tr><th>Stand</th><th class="num">Pesanan</th><th class="num">Transaksi</th></tr>
<?php foreach ($per as $v): ?><tr><td data-l="Stand"><b><?= e($v['nama']) ?></b></td><td class="num" data-l="Pesanan"><?= (int) $v['n'] ?></td><td class="num" data-l="Transaksi"><?= rp($v['rp']) ?></td></tr><?php endforeach;
if (!$per) echo '<tr><td colspan="3" class="mut">Belum ada transaksi pada rentang ini.</td></tr>'; ?></table></div>
<?php adm_layout('Laporan', ob_get_clean(), 'laporan');
}

function r_adm_laporan_ekspor(): void
{
    adm_need();
    $fl = adm_laporan_filter();
    $par = [':a' => $fl['dari'], ':b' => $fl['sampai']];
    $cond = "p.status = 'selesai' AND p.created_at::date BETWEEN CAST(:a AS date) AND CAST(:b AS date)";
    if ($fl['stand']) {
        $cond .= ' AND p.stand_id = :s';
        $par[':s'] = $fl['stand'];
    }
    $rows = db_sql("SELECT p.kode, p.created_at, s.nama AS stand, u.nama AS pelanggan, p.layanan, p.total
        FROM pesanan p LEFT JOIN stands s ON s.id = p.stand_id LEFT JOIN profiles u ON u.id = p.pelanggan_id
        WHERE $cond ORDER BY p.created_at LIMIT 5000", $par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-pico-' . $fl['dari'] . '_' . $fl['sampai'] . '.csv"');
    header('Cache-Control: no-store');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // supaya Excel membaca UTF-8 dengan benar
    fputcsv($o, ['Kode', 'Waktu', 'Stand', 'Pelanggan', 'Layanan', 'Total'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($o, [csv_aman($r['kode']), $r['created_at'], csv_aman($r['stand']), csv_aman($r['pelanggan']), LAY[$r['layanan']] ?? $r['layanan'], (int) $r['total']], ',', '"', '\\');
    }
    fclose($o);
    exit;
}
