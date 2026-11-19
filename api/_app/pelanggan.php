<?php
// pelanggan.php : halaman awal, login/daftar (semua peran), dan seluruh halaman pelanggan.

function r_root(): void
{
  if ($m = me())
    redirect(home_for($m['role']));
  ob_start(); ?>
  <div class="role-screen">
    <div class="role-hero">
      <div class="role-brand">
        <h1>PICO</h1>
        <p>Pesan makanan Kantin AX tanpa antre</p>
      </div>

      <div class="role-art" aria-hidden="true">
        <div class="role-circle">
          <svg class="role-bowl-svg" viewBox="0 0 220 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Uap makanan hangat -->
            <path class="bowl-steam s1" d="M74 52C71 42 79 34 76 24" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round"/>
            <path class="bowl-steam s2" d="M110 48C107 38 115 30 112 20" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round"/>
            <path class="bowl-steam s3" d="M146 52C143 42 151 34 148 24" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round"/>
            <!-- Isi makanan (mie/nasi hangat + topping) -->
            <path d="M52 108C56 76 82 62 110 62C138 62 164 76 168 108H52Z" fill="#EAA334"/>
            <circle cx="83" cy="91" r="10.5" fill="#17794B"/>
            <circle cx="122" cy="87" r="9.5" fill="#9E2A25"/>
            <circle cx="151" cy="96" r="9.5" fill="#17794B"/>
            <!-- Mangkuk putih -->
            <rect x="30" y="102" width="160" height="14" rx="7" fill="#FFFFFF"/>
            <path d="M36 112H184C181 150 153 174 110 174C67 174 39 150 36 112Z" fill="#FFFFFF"/>
            <path d="M41 132H179C171 159 146 174 110 174C74 174 49 159 41 132Z" fill="#EAECEF"/>
            <rect x="74" y="170" width="72" height="11" rx="5.5" fill="#FFFFFF"/>
          </svg>
        </div>

        <!-- Kartu Kiri: Menu real-time / Stok selalu update (ukuran ringkas) -->
        <div class="role-card-float role-card-left">
          <b class="rcf-title">Menu real-time</b>
          <span class="rcf-sub"><i class="rcf-dot"></i>Stok selalu update</span>
        </div>

        <!-- Kartu Kanan: Notifikasi siap (ukuran ringkas sesuai proporsi mockup) -->
        <div class="role-card-float role-card-right">
          <span class="rcf-check">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </span>
          <b class="rcf-right-title">Notifikasi siap</b>
        </div>
      </div>
    </div>

    <div class="role-sheet">
      <h2>Mau masuk sebagai apa?</h2>
      <p class="role-sheet-sub">Pilih sesuai peranmu di Kantin AX</p>

      <div class="role-actions">
        <a class="role-btn" href="/login?sebagai=pembeli">
          <span class="role-btn-ic">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 7h12l1 13H5L6 7z"/>
              <path d="M9 7V5a3 3 0 0 1 6 0v2"/>
            </svg>
          </span>
          <span class="role-btn-txt">
            <b>Saya Pembeli</b>
            <small>Pesan makanan dari stand kantin</small>
          </span>
          <span class="role-btn-arr"><?= ic('next', 20) ?></span>
        </a>

        <a class="role-btn" href="/login?sebagai=penjual">
          <span class="role-btn-ic">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l1.5-5h15L21 9"/>
              <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>
              <path d="M5 12v8h14v-8"/>
              <path d="M9 14h6v6H9z"/>
            </svg>
          </span>
          <span class="role-btn-txt">
            <b>Saya Penjual</b>
            <small>Kelola pesanan dan menu stand</small>
          </span>
          <span class="role-btn-arr"><?= ic('next', 20) ?></span>
        </a>
      </div>

      <div class="role-footer">Kantin AX · Politeknik Negeri Malang</div>
    </div>
  </div>
  <?php layout('PICO', ob_get_clean());
}

// ---------------------------------------------------------------- login & daftar
function pintu(): string
{
  $as = $_POST['sebagai'] ?? $_GET['sebagai'] ?? '';
  return in_array($as, ['pembeli', 'penjual'], true) ? $as : 'pembeli';
}

function r_login(): void
{
  if ($m = me())
    redirect(home_for($m['role']));
  if (!in_array($_GET['sebagai'] ?? '', ['pembeli', 'penjual'], true))
    redirect('/');
  $as = pintu();
  $err = take_flash();
  ob_start(); ?>
  <div class="auth">
    <div class="logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div>
    <h1>Masuk sebagai <?= $as === 'penjual' ? 'Penjual' : 'Pembeli' ?></h1>
    <p class="mut">Pesan makanan Kantin AX tanpa antre</p>
    <form method="post" action="/login">
      <input type="hidden" name="sebagai" value="<?= e($as) ?>">
      <div class="fld"><input name="u"
          placeholder="<?= $as === 'penjual' ? 'Username atau email' : 'Username/email/no.HP/NIM/NIP' ?>"
          autocomplete="username" required></div>
      <div class="fld"><input name="p" id="pw" type="password" placeholder="Password" autocomplete="current-password"
          required><button type="button" class="eye" id="eye" aria-label="Tampilkan password">👁</button></div>
      <div style="display:flex;justify-content:flex-end;margin:-4px 0 12px">
        <a href="/lupa-password?sebagai=<?= e($as) ?>" class="org"
          style="font-size:.85rem;font-weight:600;text-decoration:none">Lupa password?</a>
      </div>
      <p class="er"><?= e($err) ?></p><button class="btn">Masuk</button>
    </form>
    <?php if ($as === 'pembeli'): ?>
      <p class="mut c">Belum punya akun? <a href="/daftar" class="org">Daftar</a></p>
    <?php else: ?>
      <div class="note">Akun penjual dibuat oleh admin kantin. Hubungi admin jika belum punya akun.</div><?php endif; ?>
    <p class="mut c" style="margin-top:16px"><a href="/" class="org">← Ganti peran</a></p>
  </div>
  <?php layout('Masuk', ob_get_clean());
}

function r_login_post(): void
{
  $as = pintu();
  $back = '/login?sebagai=' . $as;
  $id = trim($_POST['u'] ?? '');
  $pw = (string) ($_POST['p'] ?? '');
  $email = $id;
  if (!str_contains($id, '@')) { // login pakai username / NIM / no HP: cari emailnya dulu
    try {
      $email = db_rpc('login_lookup', ['p_identifier' => $id], true);
    } catch (SbError $x) {
      flash('Gagal memeriksa akun: ' . $x->getMessage());
      redirect($back);
    }
    if (!$email) {
      flash('Akun tidak ditemukan.');
      redirect($back);
    }
  }
  try {
    save_session(auth_login((string) $email, $pw));
  } catch (SbError $x) {
    flash($x->getCode() === 403 ? $x->getMessage() : 'Username atau password salah.');
    redirect($back);
  }
  $m = me();
  if (!$m) {
    flash('Profil akun tidak ditemukan. Hubungi admin.');
    redirect($back);
  }
  redirect(home_for($m['role']));
}

function r_daftar(): void
{
  $err = take_flash();
  ob_start(); ?>
  <div class="auth" style="padding-top:36px">
    <h1>Buat akun</h1>
    <p class="mut">Isi data untuk mulai memesan</p>
    <form method="post" action="/daftar" enctype="multipart/form-data" id="fd">
      <div class="stat">
        <label class="opt"><input type="radio" name="s" value="polinema" required>
          <div><b>Civitas Polinema</b><small>Wajib upload KTM/Kartu Dosen</small></div>
        </label>
        <label class="opt"><input type="radio" name="s" value="umum">
          <div><b>Bukan civitas Polinema</b><small>Pengunjung umum</small></div>
        </label>
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
        <div class="fld"><input name="p" type="password" placeholder="Password (min. 6 karakter)" minlength="6" required>
        </div>
        <div class="fld"><input name="k" type="password" placeholder="Konfirmasi password" required></div>
        <p class="er"><?= e($err) ?></p><button class="btn">Daftar</button>
      </fieldset>
    </form>
    <p class="mut c">Sudah punya akun? <a href="/login?sebagai=pembeli" class="org">Masuk</a></p>
  </div>
  <script>
    (function () {
      var f = document.getElementById('fd');
      if (!f) return;
      var dd = document.getElementById('dd'),
        w = document.getElementById('ktm-w'),
        k = document.getElementById('ktm');

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

function r_daftar_post(): void
{
  $n = trim($_POST['n'] ?? '');
  $u = trim($_POST['u'] ?? '');
  $h = trim($_POST['h'] ?? '');
  $em = trim($_POST['e'] ?? '');
  $p = (string) ($_POST['p'] ?? '');
  $k = (string) ($_POST['k'] ?? '');
  $s = $_POST['s'] ?? '';

  if (!in_array($s, ['polinema', 'umum'], true)) {
    flash('Pilih dulu: mahasiswa Polinema atau bukan.');
    redirect('/daftar');
  }
  $pol = $s === 'polinema';

  if ($n === '' || $u === '' || $em === '' || $p === '') {
    flash('Lengkapi semua data.');
    redirect('/daftar');
  }
  if (!preg_match('/^[a-zA-Z0-9_.\-]{3,30}$/', $u)) {
    flash('Username 3-30 karakter (huruf, angka, titik, atau underscore tanpa spasi).');
    redirect('/daftar');
  }
  if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
    flash('Email tidak valid.');
    redirect('/daftar');
  }
  if (strlen($p) < 6) {
    flash('Password minimal 6 karakter.');
    redirect('/daftar');
  }
  if ($p !== $k) {
    flash('Password dan konfirmasi belum sama.');
    redirect('/daftar');
  }

  $ktm = $_FILES['ktm'] ?? null;
  $mime = '';
  if ($pol) {
    try {
      $mime = ktm_cek($ktm);
    } catch (SbError $x) {
      flash($x->getMessage());
      redirect('/daftar');
    }
  }

  try {
    $sess = auth_signup($em, $p, $n, $h, $pol, $u);
    if ($pol) {
      try {
        ktm_simpan((string) $sess['user_id'], $ktm, $mime);
      } catch (SbError $x) {
        pg_run('DELETE FROM profiles WHERE id = :id', [':id' => $sess['user_id']]);
        throw $x;
      }
    }
  } catch (SbError $x) {
    flash($x->getMessage());
    redirect('/daftar');
  }

  flash('Pendaftaran berhasil! Silakan masuk dengan username dan password Anda.');
  redirect('/login?sebagai=pembeli');
}

function r_keluar(): void
{
  clear_session();
  cookie_del('pico_cart');
  redirect('/');
}

// ---------------------------------------------------------------- home & menu
function r_home(): void
{
  $m = need('pelanggan');
  $cari = trim($_GET['cari'] ?? '');
  $stands = db_sql('SELECT s.id, s.nama, s.kios, s.buka, s.foto_url, (SELECT count(*) FROM menu m WHERE m.stand_id = s.id) AS jml_menu
                      FROM stands s ORDER BY s.kios');
  $items = [];
  if ($cari !== '') {
    // Mencari: tampilkan makanannya langsung (satu menu bisa muncul dari beberapa kedai),
    // lalu stand yang namanya cocok.
    $t = lc($cari);
    $safe = str_replace(['%', '_', '\\'], '', $cari);
    $stands = array_values(array_filter($stands, fn($s) => str_contains(lc($s['nama']), $t)));
    $items = db_sql('SELECT m.id, m.nama, m.harga, m.stok, m.tersedia, m.foto_url,
                            s.id AS stand_id, s.nama AS stand_nama, s.kios, s.buka
                     FROM menu m JOIN stands s ON s.id = m.stand_id
                     WHERE m.nama ILIKE :q
                     ORDER BY (s.buka AND m.tersedia AND m.stok > 0) DESC, m.nama, s.kios
                     LIMIT 40', [':q' => '%' . $safe . '%']);
  }
  ob_start(); ?>
  <header class="hd"><small>Halo, <?= e($m['nama']) ?></small>
    <h1>Mau makan apa hari ini?</h1>
    <form method="get" action="/home" data-live-search><label class="search"><span
          class="search-ic"><?= ic('search', 18) ?></span><input name="cari" type="search" value="<?= e($cari) ?>"
          placeholder="Cari stand atau menu" aria-label="Cari stand atau menu" autocomplete="off"></label></form>
  </header>
  <div class="bd">
    <?php if ($cari !== ''): ?>
      <?php if ($items): ?>
        <div class="sec-head">
          <h3>Menu</h3><small><?= count($items) ?> menu</small>
        </div>
        <?php foreach ($items as $i):
          $ada = $i['buka'] && $i['tersedia'] && (int) $i['stok'] > 0; ?>
          <a class="mres <?= $ada ? '' : 'off' ?>" href="/menu?stand=<?= (int) $i['stand_id'] ?>">
            <div class="thumb"><?= img($i['foto_url']) ?></div>
            <div class="mres-info"><b><?= e($i['nama']) ?></b>
              <span class="mres-stand"><?= ic('store', 13) ?><?= e($i['stand_nama']) ?> · Kios <?= e($i['kios']) ?></span>
              <strong><?= rp($i['harga']) ?></strong>
            </div>
            <span class="badge <?= $ada ? '' : 'off' ?>"><?= !$i['buka'] ? 'Tutup' : ($ada ? 'Tersedia' : 'Habis') ?></span>
          </a>
        <?php endforeach; endif; ?>
      <?php if ($stands): ?>
        <div class="sec-head">
          <h3>Stand</h3><small><?= count($stands) ?> stand</small>
        </div>
        <?php foreach ($stands as $s):
          $buka = $s['buka']; ?>
          <a class="stand <?= $buka ? '' : 'off' ?>" href="/menu?stand=<?= (int) $s['id'] ?>">
            <div class="thumb"><?= img($s['foto_url']) ?></div>
            <div><b><?= e($s['nama']) ?></b><small>Kios
                <?= e($s['kios']) ?></small><small><?= $buka ? (int) $s['jml_menu'] . ' Menu' : 'sedang tutup' ?></small></div>
            <span class="badge <?= $buka ? '' : 'off' ?>"><?= $buka ? 'Buka' : 'Tutup' ?></span>
          </a>
        <?php endforeach; endif; ?>
      <?php if (!$items && !$stands)
        echo empty_state('search', 'Tidak ditemukan', 'Coba kata kunci lain, misalnya nama makanan atau nama stand.'); ?>
    <?php else: ?>
      <div class="sec-head">
        <h3>Stand</h3><small><?= count($stands) ?> stand</small>
      </div>
      <?php foreach ($stands as $s):
        $buka = $s['buka']; ?>
        <a class="stand <?= $buka ? '' : 'off' ?>" href="/menu?stand=<?= (int) $s['id'] ?>">
          <div class="thumb"><?= img($s['foto_url']) ?></div>
          <div><b><?= e($s['nama']) ?></b><small>Kios
              <?= e($s['kios']) ?></small><small><?= $buka ? (int) $s['jml_menu'] . ' Menu' : 'sedang tutup' ?></small></div>
          <span class="badge <?= $buka ? '' : 'off' ?>"><?= $buka ? 'Buka' : 'Tutup' ?></span>
        </a>
      <?php endforeach;
      if (!$stands)
        echo empty_box('Belum ada stand.'); ?>
    <?php endif; ?>
  </div><?= nav_pel('/home') ?>
  <?php layout('Home', ob_get_clean());
}

function r_menu(): void
{
  need('pelanggan');
  $sid = (int) ($_GET['stand'] ?? 0);
  $s = db_get('stands?select=id,nama,kios,buka&id=eq.' . $sid)[0] ?? null;
  if (!$s) {
    flash('Stand tidak ditemukan.');
    redirect('/home');
  }
  $menu = db_get('menu?select=id,nama,harga,stok,tersedia,foto_url&stand_id=eq.' . $sid . '&order=nama');
  $cs = (int) (cart()['s'] ?? 0);
  ob_start();
  ?>
  <header class="hx hx-stand">
    <a class="hx-back" href="/home"><?= ic('back', 18) ?><span>Kembali</span></a>
    <div class="hx-stand-row">
      <?= kios_tile($s['kios']) ?>
      <div class="hx-main">
        <h1><?= e($s['nama']) ?></h1>
        <div class="hx-meta">
          <span
            class="status-pill <?= $s['buka'] ? 'is-open' : 'is-closed' ?>"><i></i><?= $s['buka'] ? 'Buka' : 'Tutup' ?></span>
          <span class="hx-count-txt"><?= count($menu) ?> menu</span>
        </div>
      </div>
    </div>
  </header>
  <div class="bd">
    <?php if (!$s['buka'])
      echo '<div class="note" style="margin:0 0 14px">Stand sedang tutup, belum bisa memesan.</div>'; ?>
    <div class="grid2">
      <?php foreach ($menu as $i):
        $ok = $s['buka'] && $i['tersedia'] && $i['stok'] > 0; ?>
        <div class="pc <?= $i['stok'] > 0 ? '' : 'out' ?>">
          <div class="im"><?= img($i['foto_url']) ?></div><b><?= e($i['nama']) ?></b><span
            class="pr"><?= rp($i['harga']) ?></span>
          <?php if ($i['stok'] <= 0)
            echo '<span class="pill">Stok habis</span>'; ?>
          <?php if ($ok): ?><button type="button" class="plus" data-open="d<?= (int) $i['id'] ?>"
              aria-label="Tambah <?= e($i['nama']) ?>">+</button><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div><?php if (!$menu)
      echo empty_box('Menu belum tersedia.'); ?>
  </div>
  <?php foreach ($menu as $i):
    if (!($s['buka'] && $i['tersedia'] && $i['stok'] > 0))
      continue; ?>
    <dialog id="d<?= (int) $i['id'] ?>">
      <form method="post" action="/keranjang/tambah" class="sheet add" data-max="<?= (int) $i['stok'] ?>"
        data-h="<?= (int) $i['harga'] ?>" data-cs="<?= $cs ?>" data-st="<?= $sid ?>">
        <input type="hidden" name="stand" value="<?= $sid ?>"><input type="hidden" name="menu"
          value="<?= (int) $i['id'] ?>"><input type="hidden" name="jml" value="1"><input type="hidden" name="ganti"
          value="">
        <div class="row" style="gap:14px">
          <div class="thumb" style="width:92px;height:84px"><?= img($i['foto_url']) ?></div>
          <div style="flex:1"><b><?= e($i['nama']) ?></b>
            <div class="org"><?= rp($i['harga']) ?></div><small class="mut">Stok online: <?= (int) $i['stok'] ?></small>
          </div>
        </div>
        <div class="row" style="margin-top:14px"><b>Jumlah</b>
          <div class="qty"><button type="button" class="mn" aria-label="Kurangi">-</button><b class="n">1</b><button
              type="button" class="p pl" aria-label="Tambah">+</button></div>
        </div>
        <textarea name="cat" placeholder="Catatan (opsional)" maxlength="120"></textarea><button class="btn go">Tambahkan ke
          keranjang</button>
      </form>
    </dialog>
  <?php endforeach;
  echo nav_pel('/home');
  layout($s['nama'], ob_get_clean());
}

// ---------------------------------------------------------------- keranjang
function r_cart_add(): void
{
  need('pelanggan');
  $sid = (int) ($_POST['stand'] ?? 0);
  $mid = (int) ($_POST['menu'] ?? 0);
  $jml = max(1, (int) ($_POST['jml'] ?? 1));
  $cat = cut(trim($_POST['cat'] ?? ''), 120);
  $m = db_get('menu?select=id,stok,tersedia&id=eq.' . $mid . '&stand_id=eq.' . $sid)[0] ?? null;
  if (!$m || !$m['tersedia'] || $m['stok'] < 1) {
    flash('Menu tidak tersedia.');
    redirect('/menu?stand=' . $sid);
  }
  $c = cart();
  if ($c['i'] && (int) $c['s'] !== $sid) {
    if (($_POST['ganti'] ?? '') !== '1') {
      flash('Keranjang berisi menu dari stand lain. Kosongkan dulu.');
      redirect('/keranjang');
    }
    $c = ['s' => 0, 'i' => []];
  }
  $c['s'] = $sid;
  $found = false;
  foreach ($c['i'] as &$x)
    if ((int) $x['id'] === $mid) {
      $x['j'] = min((int) $m['stok'], (int) $x['j'] + $jml);
      $found = true;
    }
  unset($x);
  if (!$found)
    $c['i'][] = ['id' => $mid, 'j' => min((int) $m['stok'], $jml), 'c' => $cat];
  cart_save($c);
  flash('Masuk keranjang');
  redirect('/menu?stand=' . $sid);
}

function r_cart(): void
{
  need('pelanggan');
  $d = cart_detail();
  $n = count($d['items']);
  ob_start();
  echo hdx('Keranjang', $n ? ic('store', 15) . '<span>' . e($d['stand']['nama']) . '</span>' : 'Belum ada menu', ['icon' => 'cart', 'count' => $n]); ?>
  <div class="bd">
    <?php foreach ($d['items'] as $x): ?>
      <div class="item">
        <div class="thumb"><?= img($x['foto']) ?></div>
        <div class="t">
          <b><?= e($x['nama']) ?></b><small><?= rp($x['harga']) ?></small><?= $x['cat'] ? '<small><br>' . e($x['cat']) . '</small>' : '' ?>
        </div>
        <form method="post" action="/keranjang/ubah" class="qty"><input type="hidden" name="id"
            value="<?= (int) $x['id'] ?>">
          <button name="d" value="-1" aria-label="Kurangi">-</button><b><?= (int) $x['jml'] ?></b><button name="d" value="1"
            class="p" aria-label="Tambah">+</button>
        </form>
      </div>
    <?php endforeach;
    if (!$n)
      echo empty_state('cart', 'Keranjangmu masih kosong', 'Pilih stand di Home, lalu tambahkan menu yang kamu mau.', '/home', 'Lihat stand'); ?>
  </div>
  <?php if ($n):
    $totalPorsi = array_sum(array_map(fn($it) => (int) $it['jml'], $d['items'])); ?>
    <div class="foot ck-foot" style="bottom:64px">
      <div class="ck-summary">
        <div class="ck-menu-top">
          <b><?= $n ?> Menu</b>
          <span class="ck-porsi">· <?= $totalPorsi ?> porsi dipilih</span>
        </div>
        <div class="ck-total-bottom">
          <span class="ck-total-lbl">Total pembayaran</span>
          <strong class="ck-total-val"><?= rp($d['total']) ?></strong>
        </div>
      </div>
      <a class="btn ck-btn" href="/opsi">Checkout</a>
    </div>
  <?php endif; ?>
  <?= nav_pel('/keranjang');
  layout('Keranjang', ob_get_clean());
}

function r_cart_change(): void
{
  need('pelanggan');
  $id = (int) ($_POST['id'] ?? 0);
  $dlt = (int) ($_POST['d'] ?? 0) > 0 ? 1 : -1;
  $c = cart();
  foreach ($c['i'] as $k => &$x)
    if ((int) $x['id'] === $id) {
      $x['j'] = (int) $x['j'] + $dlt;
      if ($x['j'] < 1)
        unset($c['i'][$k]);
    }
  unset($x);
  cart_save($c);
  redirect('/keranjang');
}

function r_opsi(): void
{
  need('pelanggan');
  $d = cart_detail();
  if (!$d['items'])
    redirect('/keranjang');
  $cur = $_COOKIE['pico_lay'] ?? 'dine_in';
  ob_start();
  echo hd('Konfirmasi Pesanan'); ?>
  <div class="bd">
    <?php foreach ($d['items'] as $x)
      echo '<div class="box"><b>' . (int) $x['jml'] . ' x ' . e($x['nama']) . '</b><div class="mut">' . rp($x['harga'] * $x['jml']) . '</div></div>'; ?>
  </div>
  <form method="post" action="/opsi" class="foot" style="padding-top:16px"><b>Pilih layanan</b>
    <div style="margin-top:10px">
      <label class="opt"><input type="radio" name="l" value="dine_in" <?= $cur === 'dine_in' ? 'checked' : '' ?>>
        <div><b>Dine in</b><small>Makan di kantin</small></div>
      </label>
      <label class="opt"><input type="radio" name="l" value="take_away" <?= $cur === 'take_away' ? 'checked' : '' ?>>
        <div><b>Take Away</b><small>Dibawa pulang</small></div>
      </label>
    </div>
    <div class="btns"><a class="btn sec" href="/keranjang">Kembali</a><button class="btn">Lanjut</button></div>
  </form>
  <?php layout('Konfirmasi', ob_get_clean());
}

function r_opsi_post(): void
{
  need('pelanggan');
  $l = ($_POST['l'] ?? '') === 'take_away' ? 'take_away' : 'dine_in';
  cookie_set('pico_lay', $l, 86400);
  redirect('/bayar');
}

function r_bayar(): void
{
  need('pelanggan');
  $d = cart_detail();
  if (!$d['items'])
    redirect('/keranjang');
  $lay = $_COOKIE['pico_lay'] ?? 'dine_in';
  ob_start();
  echo hd('Pembayaran'); ?>
  <div class="bd">
    <div class="box"><b>Ringkasan</b>
      <div class="sum"><span>Subtotal</span><span><?= rp($d['total']) ?></span></div>
      <div class="sum"><span>Layanan</span><span><?= e(LAY[$lay] ?? $lay) ?></span></div>
      <div class="sum t"><span>Total</span><span><?= rp($d['total']) ?></span></div>
    </div>
    <h3>Metode Pembayaran</h3><label class="opt"><input type="radio" checked>
      <div><b>QRIS</b><small>Semua e-wallet dan m-banking</small></div>
    </label>
    <p class="mut" style="font-size:.9rem">Pembayaran wajib dilakukan sebelum pesanan diproses penjual</p>
  </div>
  <form method="post" action="/bayar" class="foot"><button class="btn">Bayar sekarang</button></form>
  <?php layout('Pembayaran', ob_get_clean());
}

function r_bayar_post(): void
{
  need('pelanggan');
  $c = cart();
  $d = cart_detail();
  if (!$d['items'])
    redirect('/keranjang');
  $lay = ($_COOKIE['pico_lay'] ?? '') === 'take_away' ? 'take_away' : 'dine_in';
  $items = array_map(fn($x) => ['menu_id' => (int) $x['id'], 'jumlah' => (int) $x['jml'], 'catatan' => $x['cat'] !== '' ? $x['cat'] : null], $d['items']);
  try {
    $o = db_rpc('buat_pesanan', ['p_stand' => (int) $c['s'], 'p_layanan' => $lay, 'p_items' => $items]);
  } catch (SbError $x) {
    flash($x->getMessage());
    redirect('/bayar');
  }
  cart_save(['s' => 0, 'i' => []]);
  redirect('/qris?id=' . (int) $o['id']);
}

// ---------------------------------------------------------------- QRIS & status
/** Satu pesanan milik pelanggan ini (lengkap dengan stand dan item), atau null. */
function ambil_pesanan(array $m, int $id): ?array
{
  return pesanan_muat('p.id = :id AND p.pelanggan_id = :u', [':id' => $id, ':u' => $m['id']])[0] ?? null;
}

function r_qris(): void
{
  $m = need('pelanggan');
  $id = (int) ($_GET['id'] ?? 0);
  try {
    db_rpc('bersihkan_kadaluarsa');
  } catch (SbError $x) {
  }
  $o = ambil_pesanan($m, $id);
  if (!$o) {
    flash('Pesanan tidak ditemukan.');
    redirect('/riwayat');
  }
  if ($o['status'] !== 'menunggu_bayar')
    redirect('/status?id=' . $id);
  $exp = strtotime($o['created_at']) + 900;

  $qrStandUrl = (string) ($o['stands']['qr_url'] ?? '');

  $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
  $base = str_contains($scriptPath, '/PICO/') ? '/PICO' : '';

  $qrFallback = '';
  if ($qrStandUrl === '') {
    $seed = $id * 9301 + 49297;
    for ($k = 0; $k < 441; $k++) {
      $x = $k % 21;
      $y = intdiv($k, 21);
      if (($x < 7 && $y < 7) || ($x > 13 && $y < 7) || ($x < 7 && $y > 13))
        $w = !($x % 6 === 0 || $y % 6 === 0 || ($x % 6 > 1 && $x % 6 < 5 && $y % 6 > 1 && $y % 6 < 5));
      else {
        $seed = ($seed * 9301 + 49297) % 233280;
        $w = $seed / 233280 <= .5;
      }
      $qrFallback .= '<i' . ($w ? ' class="w"' : '') . '></i>';
    }
  }

  ob_start(); ?>
  <style>
    #ok:disabled {
      background: #cbd5e1 !important;
      color: #64748b !important;
      border-color: #cbd5e1 !important;
      box-shadow: none !important;
      cursor: not-allowed !important;
      opacity: 1 !important;
    }

    .bukti-upload-card {
      margin: 14px auto 0;
      max-width: 300px;
      background: #fff;
      border: 1.5px dashed #cbd5e1;
      border-radius: 14px;
      padding: 12px 14px;
      text-align: left;
      transition: border-color .2s, background .2s;
    }

    .bukti-upload-card.has-file {
      border-style: solid;
      border-color: #16a34a;
      background: #f0fdf4;
    }

    .bukti-upload-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #1e293b;
      font-size: .84rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 8px;
    }

    .bukti-preview-wrap {
      margin-top: 10px;
      text-align: center;
    }

    .bukti-preview-wrap img {
      max-height: 150px;
      max-width: 100%;
      border-radius: 8px;
      border: 1px solid #cbd5e1;
      display: inline-block;
    }
  </style>
  <header class="hd"><a class="back" href="/home">← Pembayaran QRIS</a></header>
  <div class="bd c" id="qris-box" style="padding-bottom:110px">
    <p class="mut">Pesanan #<?= e($o['kode']) ?> · <?= e($o['stands']['nama'] ?? '') ?>
      (<?= e(LAY[$o['layanan']] ?? '') ?>)</p>
    <h1 style="margin:6px 0"><?= rp($o['total']) ?></h1>

    <!-- Gambar QRIS Stand + Tombol Simpan ke Galeri -->
    <div id="qr-wrap"
      style="margin:12px auto 14px;width:100%;max-width:300px;background:#fff;padding:16px;border-radius:18px;box-shadow:0 4px 16px rgba(0,0,0,.08);transition:all .3s ease">
      <?php if ($qrStandUrl !== ''): ?>
        <img id="img-qr-stand" src="<?= e($qrStandUrl) ?>" alt="QRIS <?= e($o['stands']['nama'] ?? 'Stand') ?>"
          title="Ketuk untuk memperbesar"
          style="width:100%;height:auto;border-radius:10px;display:block;cursor:zoom-in;image-rendering:-webkit-optimize-contrast;image-rendering:crisp-edges">
        <div style="display:flex;gap:8px;justify-content:center;margin-top:14px">
          <a href="<?= e($qrStandUrl) ?>" download="QRIS-<?= e($o['stands']['nama'] ?? 'PICO') ?>-<?= e($o['kode']) ?>.png"
            style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;padding:9px 12px;border-radius:10px;font-size:.82rem;font-weight:700;text-decoration:none">
            ⬇ Simpan QRIS ke Galeri
          </a>
          <button type="button" id="btn-zoom-qr" aria-label="Perbesar QR"
            style="border:1px solid #e2e8f0;background:#f8fafc;color:#334155;padding:9px 12px;border-radius:10px;font-size:.82rem;font-weight:600;cursor:pointer">
            <?= ic('search', 18) ?>
          </button>
        </div>
      <?php else: ?>
        <div class="qr" role="img" aria-label="Kode QR pembayaran"><?= $qrFallback ?></div>
        <small class="mut" style="display:block;margin-top:10px;color:#b42318;font-size:.78rem">
          Penjual belum mengunggah foto QRIS di menu Profil Penjual.
        </small>
      <?php endif; ?>
    </div>

    <?php if ($qrStandUrl !== ''): ?>
      <!-- Dialog Zoom Fullscreen -->
      <dialog id="dlg-zoom-qr"
        style="border:0;border-radius:16px;padding:16px;max-width:92vw;background:#fff;box-shadow:0 20px 40px rgba(0,0,0,.35)">
        <div style="text-align:center">
          <b style="display:block;margin-bottom:8px;font-size:.95rem">QRIS <?= e($o['stands']['nama'] ?? '') ?></b>
          <img src="<?= e($qrStandUrl) ?>" alt="QRIS Zoom"
            style="width:82vw;max-width:420px;height:auto;display:block;margin:0 auto;border-radius:8px">
          <button type="button" id="close-zoom-qr" class="btn sec" style="margin-top:12px">Tutup</button>
        </div>
      </dialog>
    <?php endif; ?>

    <!-- Animasi Sukses -->
    <div id="qr-sukses" hidden
      style="margin:28px auto;padding:24px;max-width:260px;background:#d6f3e1;color:#146c3b;border-radius:18px">
      <div style="font-size:3rem;line-height:1">✅</div>
      <h3 style="margin:10px 0 4px">Pembayaran Berhasil!</h3>
      <small>Bukti pembayaran dan pesanan sedang diteruskan ke penjual...</small>
    </div>

    <span class="timer" id="qr-timer" data-exp="<?= $exp ?>">Bayar dalam --:--</span>

    <!-- Kotak Upload Bukti Pembayaran -->
    <div id="bukti-box" class="bukti-upload-card">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <b style="font-size:.85rem;color:#0f172a">Bukti Pembayaran <span style="color:#dc2626">*</span></b>
        <small id="bukti-status" style="font-size:.75rem;color:#dc2626;font-weight:600">Wajib diunggah</small>
      </div>
      <small class="mut" style="display:block;font-size:.76rem;margin-top:2px">
        Unggah screenshot/foto bukti transfer QRIS (JPG, PNG, WebP, maks. 2 MB) untuk mengaktifkan tombol konfirmasi.
      </small>
      <label for="input-bukti" class="bukti-upload-btn" id="lbl-bukti">
        📷 <span id="txt-pilih-bukti">Pilih Foto Bukti Transfer</span>
      </label>
      <div id="bukti-preview" class="bukti-preview-wrap" hidden>
        <img id="img-bukti-preview" src="" alt="Preview Bukti Transfer">
      </div>
    </div>

    <div id="qr-hint"
      style="margin:14px auto 0;max-width:300px;text-align:left;background:#f8fafc;border:1px solid #e2e8f0;padding:10px 14px;border-radius:12px;font-size:.8rem;color:#475569;line-height:1.5">
      <b>Cara bayar pakai HP sendiri:</b>
      <ol style="margin:4px 0 0;padding-left:18px">
        <li>Klik <b>Simpan QRIS ke Galeri</b> di atas.</li>
        <li>Buka <b>DANA / GoPay / M-Banking</b>, pilih <b>Scan QR</b> lalu ambil gambar dari <b>Galeri</b>.</li>
        <li>Bayar sesuai nominal <b><?= rp($o['total']) ?></b>, lalu <b>screenshot bukti transfer</b>.</li>
        <li>Unggah foto bukti transfer di atas, lalu klik <b>Saya sudah bayar</b>.</li>
      </ol>
    </div>
  </div>

  <form method="post" action="/qris/bayar" enctype="multipart/form-data" class="foot" id="qr-foot">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="file" id="input-bukti" name="bukti" accept="image/jpeg,image/png,image/webp" required hidden>
    <button class="btn" id="ok" disabled>Saya sudah bayar</button>
  </form>

  <script>
    (function () {
      var id = <?= $id ?>;
      var base = <?= json_encode($base) ?>;
      var done = false;

      // Fitur Zoom QRIS
      var imgQr = document.getElementById('img-qr-stand');
      var btnZoom = document.getElementById('btn-zoom-qr');
      var dlgZoom = document.getElementById('dlg-zoom-qr');
      var clsZoom = document.getElementById('close-zoom-qr');
      function openZoom() { if (dlgZoom && dlgZoom.showModal) dlgZoom.showModal(); }
      if (imgQr) imgQr.addEventListener('click', openZoom);
      if (btnZoom) btnZoom.addEventListener('click', openZoom);
      if (clsZoom) clsZoom.addEventListener('click', function () { dlgZoom.close(); });
      if (dlgZoom) dlgZoom.addEventListener('click', function (e) { if (e.target === dlgZoom) dlgZoom.close(); });

      // Fitur Upload Bukti Transfer & Aktivasi Tombol "Saya sudah bayar"
      var inputBukti = document.getElementById('input-bukti');
      var btnOk = document.getElementById('ok');
      var buktiBox = document.getElementById('bukti-box');
      var buktiStatus = document.getElementById('bukti-status');
      var txtPilih = document.getElementById('txt-pilih-bukti');
      var previewWrap = document.getElementById('bukti-preview');
      var previewImg = document.getElementById('img-bukti-preview');

      if (inputBukti) {
        inputBukti.addEventListener('change', function () {
          var file = inputBukti.files && inputBukti.files[0];
          if (!file) {
            btnOk.disabled = true;
            if (buktiBox) buktiBox.classList.remove('has-file');
            if (buktiStatus) { buktiStatus.textContent = 'Wajib diunggah'; buktiStatus.style.color = '#dc2626'; }
            if (txtPilih) txtPilih.textContent = 'Pilih Foto Bukti Transfer';
            if (previewWrap) previewWrap.hidden = true;
            return;
          }

          if (file.size > 2097152) {
            alert('Ukuran foto bukti pembayaran maksimal 2 MB.');
            inputBukti.value = '';
            btnOk.disabled = true;
            return;
          }

          // Aktifkan tombol "Saya sudah bayar"
          btnOk.disabled = false;
          if (buktiBox) buktiBox.classList.add('has-file');
          if (buktiStatus) { buktiStatus.textContent = '✓ Siap dikirim'; buktiStatus.style.color = '#16a34a'; }
          if (txtPilih) txtPilih.textContent = 'Ganti Foto (' + file.name.slice(0, 18) + ')';

          var reader = new FileReader();
          reader.onload = function (ev) {
            if (previewImg && previewWrap) {
              previewImg.src = ev.target.result;
              previewWrap.hidden = false;
            }
          };
          reader.readAsDataURL(file);
        });
      }

      function tampilkanSukses() {
        if (done) return;
        done = true;
        if (dlgZoom && dlgZoom.open) dlgZoom.close();
        var wrap = document.getElementById('qr-wrap');
        var sukses = document.getElementById('qr-sukses');
        var foot = document.getElementById('qr-foot');
        var tmr = document.getElementById('qr-timer');
        var hint = document.getElementById('qr-hint');
        if (wrap) wrap.hidden = true;
        if (buktiBox) buktiBox.hidden = true;
        if (tmr) tmr.hidden = true;
        if (hint) hint.hidden = true;
        if (foot) foot.hidden = true;
        if (sukses) sukses.hidden = false;

        setTimeout(function () {
          window.location.href = base + '/status?id=' + id;
        }, 1100);
      }

      // Kirim form beserta file bukti transfer menggunakan FormData
      var form = document.getElementById('qr-foot');
      if (form) {
        form.addEventListener('submit', function (e) {
          if (!inputBukti || !inputBukti.files || !inputBukti.files[0]) {
            e.preventDefault();
            alert('Silakan unggah foto bukti pembayaran terlebih dahulu.');
            return;
          }
          e.preventDefault();
          btnOk.disabled = true;
          btnOk.textContent = 'Mengunggah bukti...';

          var fd = new FormData(form);
          fetch(base + '/qris/bayar', {
            method: 'POST',
            body: fd
          }).then(function (res) {
            if (res.ok) {
              tampilkanSukses();
            } else {
              form.submit();
            }
          }).catch(function () {
            form.submit();
          });
        });
      }
    })();
  </script>
  <?php layout('QRIS', ob_get_clean());
}

function r_qris_bayar(): void
{
  $m = need('pelanggan');
  $id = (int) ($_POST['id'] ?? 0);
  try {
    bukti_simpan($id, (string) $m['id'], $_FILES['bukti'] ?? []);
    db_rpc('saya_sudah_bayar', ['p_id' => $id]);
  } catch (SbError $x) {
    flash($x->getMessage());
    redirect('/qris?id=' . $id);
  }
  redirect('/status?id=' . $id);
}

function r_status(): void
{
  $m = need('pelanggan');
  $id = (int) ($_GET['id'] ?? 0);
  if (!$id)
    $id = (int) (db_get('pesanan?select=id&pelanggan_id=eq.' . rawurlencode($m['id']) . '&order=created_at.desc&limit=1')[0]['id'] ?? 0);
  $o = $id ? ambil_pesanan($m, $id) : null;
  if (!$o) {
    layout('Status', hd('Status pesanan') . empty_box('Belum ada pesanan.') . nav_pel('/riwayat'));
    return;
  }

  $st = (string) ($o['status'] ?? 'baru');
  $done = $st === 'selesai';
  $batal = $st === 'batal';

  // Pemetaan badge header, estimasi subteks, dan progres bar (1..4)
  $metaMap = [
    'baru' => ['Menunggu konfirmasi', 'Menunggu respon stand', 1, 'is-proc'],
    'diproses' => ['Sedang diproses', 'Perkiraan 8 menit', 2, 'is-proc'],
    'siap' => ['Siap diambil', 'Silakan ambil di stand', 3, 'is-ready'],
    'selesai' => ['Selesai', 'Pesanan telah selesai', 4, 'is-done'],
    'batal' => ['Dibatalkan', 'Stok dikembalikan', 0, 'is-cancel'],
  ];
  [$pillText, $subEst, $progCount, $pillClass] = $metaMap[$st] ?? ['Menunggu bayar', '', 0, 'is-proc'];

  $jamBayar = !empty($o['created_at']) ? date('H.i', strtotime($o['created_at'])) : date('H.i');
  $tglLengkap = !empty($o['created_at']) ? date('d M Y, H:i', strtotime($o['created_at'])) . ' WIB' : '-';
  $refNo = 'PICO-' . (!empty($o['created_at']) ? date('Ymd', strtotime($o['created_at'])) : date('Ymd')) . '-' . str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT);
  $standNama = (string) ($o['stands']['nama'] ?? '-');
  $standKios = (string) ($o['stands']['kios'] ?? '-');
  $standId = (int) ($o['stands']['id'] ?? ($o['stand_id'] ?? 0));
  $layLabel = LAY[$o['layanan']] ?? ($o['layanan'] === 'take_away' ? 'Take Away' : 'Dine In');
  $buktiUrl = trim((string) ($o['bukti_url'] ?? ''));

  // Status tiap langkah timeline: done | active | pending
  $step1 = $batal ? 'pending' : 'done';
  $step2 = $batal ? 'pending' : (in_array($st, ['siap', 'selesai'], true) ? 'done' : (in_array($st, ['baru', 'diproses'], true) ? 'active' : 'pending'));
  $step3 = $batal ? 'pending' : ($st === 'selesai' ? 'done' : ($st === 'siap' ? 'active' : 'pending'));
  $step4 = $st === 'selesai' ? 'done' : 'pending';

  $timelineSteps = [
    [
      'title' => 'Pembayaran diterima',
      'sub' => $jamBayar . ' · QRIS',
      'state' => $step1,
    ],
    [
      'title' => 'Sedang diproses',
      'sub' => $st === 'baru' ? 'Menunggu penjual menyiapkan pesananmu' : 'Penjual sedang menyiapkan pesananmu',
      'state' => $step2,
    ],
    [
      'title' => 'Siap diambil',
      'sub' => $st === 'siap' ? 'Pesananmu sudah matang! Tunjukkan bukti & nomor ke penjual' : 'Kamu akan diberi tahu di sini',
      'state' => $step3,
    ],
    [
      'title' => 'Selesai',
      'sub' => $done ? 'Pesanan telah diambil · Terima kasih!' : '',
      'state' => $step4,
    ],
  ];

  ob_start(); ?>
  <div class="st-page" id="live">
    <header class="st-hero">
      <div class="st-topbar">
        <a href="/riwayat" class="st-back"><?= ic('back', 20) ?><span>Status Pesanan</span></a>
      </div>

      <div class="st-hero-main">
        <div class="st-order-no">
          <small>Nomor pesanan</small>
          <strong><?= e($o['kode']) ?></strong>
        </div>
        <div class="st-status-right">
          <span class="st-pill <?= e($pillClass) ?>"><?= e($pillText) ?></span>
          <?php if ($subEst !== ''): ?>
            <small class="st-est"><?= e($subEst) ?></small>
          <?php endif; ?>
        </div>
      </div>

      <div class="st-progress" role="progressbar" aria-valuenow="<?= (int) $progCount ?>" aria-valuemin="0" aria-valuemax="4">
        <?php for ($sIdx = 1; $sIdx <= 4; $sIdx++): ?>
          <span class="st-seg <?= $sIdx <= $progCount ? 'on' : '' ?> <?= $done ? 'is-complete' : '' ?>"></span>
        <?php endfor; ?>
      </div>
    </header>

    <div class="st-body">
      <!-- Kartu Atas: Bukti Pembayaran (Struk) di kiri + Nomor Pesanan di kanan untuk ditunjukkan ke penjual -->
      <section class="st-card st-pickup-card">
        <div class="st-proof-col">
          <?php if ($buktiUrl !== ''): ?>
            <button type="button" class="st-proof-thumb" data-open-dlg="dlg-bukti-bayar" aria-label="Perbesar bukti pembayaran">
              <img src="<?= e($buktiUrl) ?>" alt="Bukti pembayaran #<?= e($o['kode']) ?>">
              <span class="st-proof-badge">🔍 Bukti Bayar</span>
            </button>
          <?php else: ?>
            <button type="button" class="st-proof-fallback" data-open-dlg="dlg-struk-pesanan" aria-label="Lihat struk pembayaran">
              <span class="st-pf-ic">🧾</span>
              <b>QRIS Lunas</b>
              <small><?= rp($o['total']) ?></small>
              <span class="st-pf-tap">Lihat Struk</span>
            </button>
          <?php endif; ?>
        </div>

        <div class="st-pickup-info">
          <small class="st-pickup-hint">Tunjukkan ke penjual saat mengambil</small>
          <strong class="st-pickup-code"><?= e($o['kode']) ?></strong>
          <b class="st-pickup-stand"><?= e($standNama) ?></b>
          <span class="st-pickup-meta">Kios <?= e($standKios) ?> · <?= e($layLabel) ?></span>
        </div>
      </section>

      <?php if ($batal): ?>
        <div class="st-cancel-banner">
          <?= ic('x', 18) ?>
          <div>
            <b>Pesanan Dibatalkan</b>
            <small>Pesanan ini telah dibatalkan dan stok dikembalikan ke stand.</small>
          </div>
        </div>
      <?php else: ?>
        <!-- Kartu Perjalanan Pesanan -->
        <section class="st-card">
          <h2 class="st-card-title">Perjalanan pesanan</h2>
          <ul class="st-timeline">
            <?php foreach ($timelineSteps as $idx => $step): ?>
              <li class="st-tl-item is-<?= e($step['state']) ?>">
                <span class="st-tl-node">
                  <?php if ($step['state'] === 'done'): ?>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <?php elseif ($step['state'] === 'active'): ?>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><polyline points="12 8 12 12 14.5 13.5"/></svg>
                  <?php endif; ?>
                </span>
                <div class="st-tl-content">
                  <b><?= e($step['title']) ?></b>
                  <?php if ($step['sub'] !== ''): ?>
                    <small><?= e($step['sub']) ?></small>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <!-- Kartu Ringkasan Pesanan -->
      <section class="st-card">
        <h2 class="st-card-title">Ringkasan pesanan</h2>
        <ul class="st-summary-list">
          <?php foreach ($o['pesanan_item'] as $item): ?>
            <li>
              <div class="st-sum-name">
                <span><?= (int) $item['jumlah'] ?> × <?= e($item['nama']) ?></span>
                <?php if (!empty($item['catatan'])): ?>
                  <small class="st-sum-note">Catatan: <?= e($item['catatan']) ?></small>
                <?php endif; ?>
              </div>
              <b class="st-sum-price"><?= rp($item['harga'] * $item['jumlah']) ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="st-summary-total">
          <span>Total dibayar</span>
          <strong><?= rp($o['total']) ?></strong>
        </div>
      </section>

      <!-- Tombol Struk & Pesan Lagi (Muncul ketika pesanan sudah selesai) -->
      <?php if ($done): ?>
        <section class="st-done-actions">
          <button type="button" class="btn st-btn-receipt" data-open-dlg="dlg-struk-pesanan">
            <?= ic('receipt', 19) ?>
            <span>Lihat Struk & Bukti Pembayaran</span>
          </button>
          <?php if ($standId > 0): ?>
            <a href="/menu?stand=<?= $standId ?>" class="btn sec st-btn-reorder">
              Pesan Lagi dari <?= e($standNama) ?>
            </a>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Banner Info 30 Hari -->
      <div class="st-retention-info">
        <span class="st-ret-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
        </span>
        <span>Riwayat pesanan akan dihapus dalam 30 hari.</span>
      </div>
    </div>

    <?php if ($buktiUrl !== ''): ?>
      <!-- Dialog Zoom Bukti Pembayaran -->
      <dialog id="dlg-bukti-bayar" class="rcp-dlg">
        <div class="rcp-sheet">
          <div class="rcp-head">
            <b>Bukti Pembayaran #<?= e($o['kode']) ?></b>
            <small><?= e($standNama) ?> · Kios <?= e($standKios) ?> · <?= rp($o['total']) ?></small>
          </div>
          <div class="rcp-proof-full">
            <img src="<?= e($buktiUrl) ?>" alt="Bukti Pembayaran #<?= e($o['kode']) ?>">
          </div>
          <div class="rcp-actions">
            <a href="<?= e($buktiUrl) ?>" download="Bukti-PICO-<?= e($o['kode']) ?>.jpg" class="btn sec sm">⬇ Unduh Bukti</a>
            <button type="button" class="btn sm" data-close-dlg>Tutup</button>
          </div>
        </div>
      </dialog>
    <?php endif; ?>

    <!-- Dialog Struk Digital Lengkap (Berisi pesanan, harga, stand, waktu, ID referensi & bukti bayar) -->
    <dialog id="dlg-struk-pesanan" class="rcp-dlg">
      <div class="rcp-sheet" id="printable-receipt">
        <div class="rcp-brand">
          <span class="rcp-badge-ok">✓ LUNAS · <?= e(strtoupper($pillText)) ?></span>
          <h3>PICO · Kantin AX</h3>
          <small>Politeknik Negeri Malang</small>
        </div>

        <div class="rcp-code-box">
          <small>Nomor Antrean Pesanan</small>
          <strong>#<?= e($o['kode']) ?></strong>
        </div>

        <dl class="rcp-meta">
          <div><dt>ID Referensi</dt><dd><?= e($refNo) ?></dd></div>
          <div><dt>Waktu Transaksi</dt><dd><?= e($tglLengkap) ?></dd></div>
          <div><dt>Stand Pembelian</dt><dd><?= e($standNama) ?> (Kios <?= e($standKios) ?>)</dd></div>
          <div><dt>Nama Pembeli</dt><dd><?= e($o['pelanggan_nama'] ?? $m['nama']) ?></dd></div>
          <div><dt>Jenis Layanan</dt><dd><?= e($layLabel) ?></dd></div>
          <div><dt>Metode Pembayaran</dt><dd>QRIS (Terverifikasi)</dd></div>
        </dl>

        <div class="rcp-divider"></div>

        <div class="rcp-items-title">Rincian Menu Pesanan</div>
        <ul class="rcp-items">
          <?php foreach ($o['pesanan_item'] as $item): ?>
            <li>
              <div>
                <b><?= (int) $item['jumlah'] ?> × <?= e($item['nama']) ?></b>
                <small>@ <?= rp($item['harga']) ?><?= !empty($item['catatan']) ? ' · "' . e($item['catatan']) . '"' : '' ?></small>
              </div>
              <span><?= rp($item['harga'] * $item['jumlah']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="rcp-divider"></div>

        <div class="rcp-total-row">
          <span>Total Pembayaran</span>
          <strong><?= rp($o['total']) ?></strong>
        </div>

        <div class="rcp-proof-section">
          <div class="rcp-proof-head">
            <b>Lampiran Bukti Pembayaran</b>
            <span>QRIS</span>
          </div>
          <?php if ($buktiUrl !== ''): ?>
            <div class="rcp-proof-img-wrap">
              <img src="<?= e($buktiUrl) ?>" alt="Bukti Transfer #<?= e($o['kode']) ?>">
            </div>
          <?php else: ?>
            <div class="rcp-proof-verified">
              <span>✅ Pembayaran QRIS sebesar <b><?= rp($o['total']) ?></b> telah tercatat lunas di sistem PICO.</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="rcp-actions no-print">
          <button type="button" class="btn sec sm" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
          <button type="button" class="btn sm" data-close-dlg>Tutup Struk</button>
        </div>
      </div>
    </dialog>
  </div><?= nav_pel('/riwayat');
  layout('Status pesanan', ob_get_clean(), ['live' => ($done || $batal) ? 0 : 4000]);
}

function r_riwayat(): void
{
  $m = need('pelanggan');
  try {
    db_rpc('bersihkan_kadaluarsa');
  } catch (SbError $x) {
  }
  $l = pesanan_muat("p.pelanggan_id = :u AND p.created_at >= NOW() - INTERVAL '5 minutes'", [':u' => $m['id']], 'ORDER BY p.created_at DESC LIMIT 30');
  ob_start();
  echo hdx('Riwayat', 'Pesanan 5 menit terakhir', ['icon' => 'receipt']); ?>
  <div class="bd">
    <?php foreach ($l as $o): ?>
      <a class="item order-item"
        href="/<?= $o['status'] === 'menunggu_bayar' ? 'qris' : 'status' ?>?id=<?= (int) $o['id'] ?>" style="display:flex">
        <div class="t"><b>#<?= e($o['kode']) ?> · <?= e($o['stands']['nama'] ?? '') ?></b>
          <small><?= e(implode(', ', array_map(fn($i) => $i['jumlah'] . ' ' . $i['nama'], $o['pesanan_item']))) ?></small>
        </div>
        <div class="order-end"><b><?= rp($o['total']) ?></b><?= tag($o['status']) ?></div>
      </a>
    <?php endforeach;
    if (!$l)
      echo empty_box('Belum ada pesanan.'); ?>
  </div><?= nav_pel('/riwayat');
  layout('Riwayat', ob_get_clean());
}

function profil_inisial(string $nama): string
{
  $parts = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY);
  if (!$parts)
    return 'P';
  if (count($parts) === 1)
    return mb_strtoupper(mb_substr($parts[0], 0, 2));
  return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
}

function r_profil(): void
{
  $m = need('pelanggan');
  $inisial = profil_inisial((string) ($m['nama'] ?? 'Pelanggan'));
  $uname = (string) ($m['username'] ?? '');
  $nim = (string) ($m['nim'] ?? '');
  $hp = (string) ($m['no_hp'] ?? '');
  $email = (string) ($m['email'] ?? '');
  $fotoUrl = (string) ($m['foto_url'] ?? '');

  $lastOrder = pesanan_muat('p.pelanggan_id = :u', [':u' => $m['id']], 'ORDER BY p.created_at DESC LIMIT 1')[0] ?? null;

  $statusMap = [
    'menunggu_bayar' => ['Menunggu Bayar', 'pf-badge-warn'],
    'baru' => ['Menunggu Konfirmasi', 'pf-badge-info'],
    'diproses' => ['Diproses', 'pf-badge-info'],
    'siap' => ['Siap Diambil', 'pf-badge-ok'],
    'selesai' => ['Selesai', 'pf-badge-ok'],
    'batal' => ['Dibatalkan', 'pf-badge-err'],
  ];

  $editItems = [
    ['key' => 'nama', 'label' => 'Nama', 'val' => (string) ($m['nama'] ?? '-'), 'raw' => (string) ($m['nama'] ?? ''), 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.8-3.6 5-5.5 8-5.5s6.2 1.9 8 5.5"/></svg>'],
    ['key' => 'username', 'label' => 'Username', 'val' => $uname !== '' ? $uname : '-', 'raw' => $uname, 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a2.5 2.5 0 0 0 5 0v-1a9 9 0 1 0-5.5 8.3"/></svg>'],
    ['key' => 'nim', 'label' => 'NIM', 'val' => $nim !== '' ? $nim : '-', 'raw' => $nim, 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16c1.2-1.8 2.8-2.5 4.5-2.5S13.8 14.2 15 16"/><line x1="14" y1="9" x2="18" y2="9"/><line x1="15" y1="12" x2="18" y2="12"/></svg>'],
    ['key' => 'no_hp', 'label' => 'No. HP', 'val' => $hp !== '' ? $hp : '-', 'raw' => $hp, 'type' => 'tel', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7A2 2 0 0 1 22 16.9z"/></svg>'],
    ['key' => 'email', 'label' => 'Email', 'val' => $email !== '' ? $email : '-', 'raw' => $email, 'type' => 'email', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>'],
  ];

  ob_start(); ?>
  <div class="pf-page">
    <header class="pf-hero">
      <div class="pf-topbar">
        <a href="/home" class="pf-back-btn" aria-label="Kembali"><?= ic('back', 20) ?></a>
        <span class="pf-top-title">Profil</span>
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
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
            <circle cx="12" cy="13" r="4" />
          </svg>
        </button>
      </div>
      <h1 class="pf-name"><?= e($m['nama']) ?></h1>
      <div class="pf-sub">
        <span>@<?= e($uname !== '' ? $uname : lc(preg_replace('/\s+/', '', (string) $m['nama']))) ?></span>
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
            <button type="button" class="pf-edit-btn" data-pf-open="dlg-edit-<?= e($item['key']) ?>"
              aria-label="Edit <?= e($item['label']) ?>">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
              </svg>
            </button>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pf-sec-title">PESANAN TERAKHIR</div>
      <div class="pf-card">
        <?php if ($lastOrder):
          [$stLabel, $stClass] = $statusMap[$lastOrder['status']] ?? [ucfirst((string) $lastOrder['status']), 'pf-badge-info'];
          $layLabel = LAY[$lastOrder['layanan']] ?? ($lastOrder['layanan'] === 'take_away' ? 'Take Away' : 'Dine In');
          $orderHref = ($lastOrder['status'] === 'menunggu_bayar' ? '/qris' : '/status') . '?id=' . (int) $lastOrder['id'];
          ?>
          <a class="pf-order-card" href="<?= e($orderHref) ?>">
            <div class="pf-order-ic">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1z" />
                <line x1="9" y1="8" x2="15" y2="8" />
                <line x1="9" y1="12" x2="15" y2="12" />
                <line x1="9" y1="16" x2="12" y2="16" />
              </svg>
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
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1z" />
                <line x1="9" y1="8" x2="15" y2="8" />
                <line x1="9" y1="12" x2="15" y2="12" />
              </svg>
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
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="11" width="16" height="10" rx="2" />
              <path d="M8 11V7a4 4 0 0 1 8 0v4" />
              <circle cx="12" cy="16" r="1" />
            </svg>
          </span>
          <span class="pf-nav-label">Ubah kata sandi</span>
          <span class="pf-nav-chev">›</span>
        </button>
        <a href="/keluar" class="pf-nav-row is-logout">
          <span class="pf-nav-ic">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
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
          <input type="<?= e($item['type']) ?>" name="value" value="<?= e($item['raw']) ?>"
            placeholder="Masukkan <?= e(lc($item['label'])) ?>" <?= in_array($item['key'], ['nama', 'username', 'email'], true) ? 'required' : '' ?>>
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
        <label>Pilih Foto (JPG/PNG/WebP, maks. 2 MB)</label>
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
    (function () {
      document.querySelectorAll('[data-pf-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var dlg = document.getElementById(btn.getAttribute('data-pf-open'));
          if (dlg && dlg.showModal) dlg.showModal();
        });
      });
      document.querySelectorAll('[data-pf-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var dlg = btn.closest('dialog');
          if (dlg && dlg.close) dlg.close();
        });
      });
      document.querySelectorAll('dialog.pf-dlg').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          if (e.target === dlg) dlg.close();
        });
      });
    })();
  </script>
  <?= nav_pel('/profil');
  layout('Profil', ob_get_clean());
}

function r_profil_ubah(): void
{
  $m = need('pelanggan');
  $field = trim((string) ($_POST['field'] ?? ''));
  $val = trim((string) ($_POST['value'] ?? ''));

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
      if ($dup)
        throw new SbError('Username sudah digunakan.');
    } elseif ($field === 'email') {
      $val = lc($val);
      if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
        throw new SbError('Format email tidak valid.');
      }
      $dup = pg_run(
        'SELECT 1 FROM profiles WHERE LOWER(email) = :e AND id <> :id',
        [':e' => $val, ':id' => $m['id']]
      )->fetch();
      if ($dup)
        throw new SbError('Email sudah digunakan akun lain.');
    } elseif ($field === 'nim' && $val !== '') {
      $dup = pg_run(
        'SELECT 1 FROM profiles WHERE nim = :n AND id <> :id',
        [':n' => $val, ':id' => $m['id']]
      )->fetch();
      if ($dup)
        throw new SbError('NIM sudah terdaftar pada akun lain.');
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

function r_profil_avatar(): void
{
  $m = need('pelanggan');
  $file = $_FILES['avatar'] ?? null;
  try {
    if (function_exists('ensure_avatar_columns')) {
      ensure_avatar_columns();
    }
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!$file || $err === UPLOAD_ERR_NO_FILE) {
      throw new SbError('Pilih foto terlebih dahulu.');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > 2097152) {
      throw new SbError('Ukuran foto profil maksimal 2 MB.');
    }
    if ($err !== UPLOAD_ERR_OK) {
      throw new SbError('Foto profil gagal diunggah.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
      throw new SbError('Foto harus berformat JPG, PNG, atau WebP.');
    }
    $fh = fopen($file['tmp_name'], 'rb');
    if ($fh === false) {
      throw new SbError('File foto sementara tidak dapat dibaca.');
    }
    $url = '/foto?t=avatar&id=' . rawurlencode((string) $m['id']) . '&v=' . time();
    try {
      $st = pdo()->prepare(
        'UPDATE profiles SET foto_data = :d, foto_mime = :m, foto_url = :u WHERE id = :id'
      );
      $st->bindValue(':d', $fh, PDO::PARAM_LOB);
      $st->bindValue(':m', $mime);
      $st->bindValue(':u', $url);
      $st->bindValue(':id', $m['id']);
      $st->execute();
      if ($st->rowCount() < 1) {
        throw new SbError('Akun profil tidak ditemukan.');
      }
      flash('Foto profil diperbarui.');
    } finally {
      if (is_resource($fh))
        fclose($fh);
    }
  } catch (Throwable $x) {
    error_log('Simpan avatar gagal: ' . $x->getMessage());
    $msg = $x instanceof SbError
      ? $x->getMessage()
      : (env('APP_DEBUG') === '1' ? 'Gagal menyimpan foto profil: ' . $x->getMessage() : 'Gagal menyimpan foto profil.');
    flash($msg);
  }
  redirect('/profil');
}

function r_profil_password(): void
{
  $m = me();
  if (!$m)
    redirect('/');
  $back = ($m['role'] ?? '') === 'penjual' ? '/penjual/profil' : '/profil';
  $lama = (string) ($_POST['pw_lama'] ?? '');
  $baru = (string) ($_POST['pw_baru'] ?? '');
  $konf = (string) ($_POST['pw_konf'] ?? '');

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
function r_lupa(): void
{
  if ($m = me())
    redirect(home_for($m['role']));
  $as = pintu();
  $err = take_flash();
  ob_start(); ?>
  <div class="auth">
    <div class="logo"><img src="/img/pico-logo.png" alt="Logo PICO"></div>
    <h1>Lupa Password</h1>
    <p class="mut">Verifikasi data akun untuk membuat password baru</p>
    <form method="post" action="/lupa-password">
      <input type="hidden" name="sebagai" value="<?= e($as) ?>">
      <div class="fld"><input name="u" placeholder="Username / Email / NIM" required></div>
      <div class="fld"><input name="h" type="tel" placeholder="No. Handphone terdaftar" required></div>
      <div class="fld"><input name="p" id="pw" type="password" placeholder="Password baru (min. 6 karakter)" minlength="6"
          required><button type="button" class="eye" id="eye" aria-label="Tampilkan password">👁</button></div>
      <div class="fld"><input name="k" type="password" placeholder="Konfirmasi password baru" minlength="6" required>
      </div>
      <p class="er"><?= e($err) ?></p><button class="btn">Simpan Password Baru</button>
    </form>
    <p class="mut c" style="margin-top:16px"><a href="/login?sebagai=<?= e($as) ?>" class="org">← Kembali ke Masuk</a></p>
  </div>
  <?php layout('Lupa Password', ob_get_clean());
}

function r_lupa_post(): void
{
  $as = pintu();
  $back = '/lupa-password?sebagai=' . $as;
  $u = trim($_POST['u'] ?? '');
  $h = trim($_POST['h'] ?? '');
  $p = (string) ($_POST['p'] ?? '');
  $k = (string) ($_POST['k'] ?? '');

  if ($u === '' || $h === '' || $p === '') {
    flash('Lengkapi semua data.');
    redirect($back);
  }
  if (strlen($p) < 6) {
    flash('Password baru minimal 6 karakter.');
    redirect($back);
  }
  if ($p !== $k) {
    flash('Password baru dan konfirmasi belum sama.');
    redirect($back);
  }

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
      "UPDATE profiles SET password_hash = :pw WHERE id = :id AND LEFT(password_hash, 1) <> '!'",
      [':pw' => password_hash($p, PASSWORD_DEFAULT), ':id' => $row['id']]
    );
  } catch (SbError $x) {
    flash($x->getMessage());
    redirect($back);
  }

  flash('Password berhasil diubah. Silakan masuk.');
  redirect('/login?sebagai=' . $as);
}