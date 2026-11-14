<?php
// penjual.php : halaman penjual (pesanan masuk, kelola menu, buka/tutup stand).

/** Stand milik penjual yang login. Kalau belum dihubungkan admin, tampilkan pesan. */
function stand_saya(array $m): array
{
  $s = db_sql('SELECT * FROM stands WHERE penjual_id = :u LIMIT 1', [':u' => $m['id']])[0] ?? null;
  if (!$s) {
    layout('Belum ada stand', hd('Halo, ' . e($m['nama'])) . '<div class="bd"><div class="note" style="margin:0 0 14px">Akunmu belum dihubungkan ke stand. Minta admin menghubungkannya.</div><a class="btn sec" href="/keluar">Keluar</a></div>');
    exit;
  }
  return $s;
}

/**
 * Potongan HTML + Modal untuk menampilkan bukti pembayaran dari pembeli di setiap kartu pesanan penjual.
 */
function html_bukti_pembeli(array $o): string
{
  $url = trim((string) ($o['bukti_url'] ?? ''));
  $pembeli = trim((string) ($o['pelanggan_nama'] ?? 'Pembeli'));
  $kode = (string) ($o['kode'] ?? '');

  if ($url === '') {
    return '<div style="margin-top:8px;padding:7px 10px;border-radius:8px;background:#f8fafc;border:1px dashed #cbd5e1;font-size:.78rem;color:#64748b">'
      . '🧾 Belum ada foto bukti pembayaran'
      . '</div>';
  }

  $dlgId = 'dlg-bukti-' . (int) $o['id'];
  return '<div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0">'
    . '<div style="display:flex;align-items:center;gap:8px;min-width:0">'
    . '<img src="' . e($url) . '" alt="Bukti" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #86efac;flex-shrink:0">'
    . '<div style="min-width:0">'
    . '<b style="display:block;font-size:.78rem;color:#14532d">Bukti Pembayaran</b>'
    . '<small style="display:block;font-size:.72rem;color:#15803d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . e($pembeli) . '</small>'
    . '</div>'
    . '</div>'
    . '<button type="button" onclick="var d=document.getElementById(\'' . e($dlgId) . '\');if(d&&d.showModal)d.showModal();" '
    . 'style="border:1px solid #16a34a;background:#fff;color:#15803d;padding:6px 10px;border-radius:8px;font-size:.76rem;font-weight:700;cursor:pointer;flex-shrink:0">'
    . 'Lihat Bukti'
    . '</button>'
    . '</div>'
    . '<dialog id="' . e($dlgId) . '" onclick="if(event.target===this)this.close()" style="border:0;border-radius:16px;padding:16px;max-width:92vw;background:#fff;box-shadow:0 20px 40px rgba(0,0,0,.35)">'
    . '<div style="text-align:center">'
    . '<b style="display:block;font-size:.95rem;margin-bottom:2px">Bukti Pembayaran #' . e($kode) . '</b>'
    . '<small class="mut" style="display:block;margin-bottom:10px">' . e($pembeli) . ' · ' . rp($o['total']) . '</small>'
    . '<img src="' . e($url) . '" alt="Bukti Pembayaran #' . e($kode) . '" style="width:82vw;max-width:380px;max-height:70vh;object-fit:contain;display:block;margin:0 auto;border-radius:10px;border:1px solid #e2e8f0">'
    . '<div style="display:flex;gap:8px;justify-content:center;margin-top:12px">'
    . '<a href="' . e($url) . '" target="_blank" rel="noopener" class="btn sec sm" style="text-decoration:none">Buka Penuh</a>'
    . '<button type="button" onclick="this.closest(\'dialog\').close()" class="btn sm">Tutup</button>'
    . '</div>'
    . '</div>'
    . '</dialog>';
}

function r_pen_home(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $sid = (int) $s['id'];
  $stats = db_sql("SELECT
        COUNT(*) FILTER (WHERE status = 'baru') AS baru,
        COUNT(*) FILTER (WHERE status = 'diproses') AS diproses,
        COALESCE(SUM(total) FILTER (WHERE status = 'selesai' AND created_at::date = CURRENT_DATE), 0) AS pendapatan
        FROM pesanan WHERE stand_id = :s", [':s' => $sid])[0] ?? ['baru' => 0, 'diproses' => 0, 'pendapatan' => 0];
  $stock = db_sql('SELECT COUNT(*) AS jumlah FROM menu WHERE stand_id = :s AND tersedia = true AND stok > 0', [':s' => $sid])[0]['jumlah'] ?? 0;
  $os = pesanan_muat('p.stand_id = :s', [':s' => $sid], 'ORDER BY p.created_at DESC LIMIT 3');

  ob_start(); ?>
  <header class="sx-hero">
    <div class="sx-id">
      <?= kios_tile($s['kios']) ?>
      <div class="sx-name"><span>Dashboard penjual</span>
        <h1><?= e($s['nama']) ?></h1>
      </div>
    </div>
    <form method="post" action="/penjual/buka" class="stand-switch <?= $s['buka'] ? 'is-open' : '' ?>" <?= $s['buka'] ? ' data-confirm="Tutup stand sekarang? Pelanggan tidak bisa memesan sampai kamu membukanya lagi."' : '' ?>>
      <div class="ss-txt">
        <b><?= $s['buka'] ? 'Stand sedang buka' : 'Stand sedang tutup' ?></b><small><?= $s['buka'] ? 'Pelanggan bisa memesan' : 'Pelanggan belum bisa memesan' ?></small>
      </div>
      <?= saklar((bool) $s['buka'], 'Buka atau tutup stand') ?>
    </form>
  </header>
  <div class="bd seller-dashboard" id="live">
    <section class="seller-stats" aria-label="Ringkasan penjualan">
      <a class="seller-stat stat-new" href="/penjual/riwayat?status=baru"><span
          class="st-ic"><?= ic('bell', 18) ?></span><b><?= (int) $stats['baru'] ?></b><small>Pesanan baru</small><span
          class="st-go">Perlu diterima <?= ic('arrow', 14) ?></span></a>
      <a class="seller-stat stat-income" href="/penjual/riwayat?status=selesai"><span
          class="st-ic"><?= ic('wallet', 18) ?></span><b><?= rp($stats['pendapatan']) ?></b><small>Pendapatan hari
          ini</small><span class="st-go">Pesanan selesai</span></a>
      <a class="seller-stat stat-progress" href="/penjual/riwayat?status=diproses"><span
          class="st-ic"><?= ic('flame', 18) ?></span><b><?= (int) $stats['diproses'] ?></b><small>Sedang
          diproses</small><span class="st-go">Lihat pesanan <?= ic('arrow', 14) ?></span></a>
      <a class="seller-stat stat-stock" href="/penjual/menu"><span
          class="st-ic"><?= ic('bowl', 18) ?></span><b><?= (int) $stock ?></b><small>Menu tersedia</small><span
          class="st-go">Kelola stok <?= ic('arrow', 14) ?></span></a>
    </section>
    <section class="seller-recent">
      <div class="seller-section-title">
        <h2><span class="sec-ic"><?= ic('clock', 16) ?></span>Pesanan terbaru</h2><a href="/penjual/riwayat">Lihat semua
          <?= ic('next', 16) ?></a>
      </div>
      <div class="seller-order-list">
        <?php foreach ($os as $o): ?>
          <article class="box ocard seller-order">
            <div class="row">
              <div><small
                  class="mut"><?= waktu($o['created_at']) ?><?= !empty($o['pelanggan_nama']) ? ' · ' . e($o['pelanggan_nama']) : '' ?></small><b>#<?= e($o['kode']) ?></b>
              </div><?= tag($o['status']) ?>
            </div>
            <ul class="lines"><?php foreach (array_slice($o['pesanan_item'], 0, 2) as $i): ?>
                <li><b><?= (int) $i['jumlah'] ?>×</b> <?= e($i['nama']) ?></li>
              <?php endforeach; ?>     <?php if (count($o['pesanan_item']) > 2): ?>
                <li class="mut">+<?= count($o['pesanan_item']) - 2 ?> menu lainnya</li><?php endif; ?>
            </ul>
            <div class="row seller-order-foot"><span><?= e(LAY[$o['layanan']] ?? '') ?></span><b><?= rp($o['total']) ?></b>
            </div>
            <?= html_bukti_pembeli($o) ?>
          </article>
        <?php endforeach;
        if (!$os)
          echo empty_state('receipt', 'Belum ada pesanan', 'Pesanan yang masuk akan muncul di sini.'); ?>
      </div>
    </section>
  </div>
  <?= nav_pen('/penjual'); ?>
  <?php
  $body = ob_get_clean();
  layout('Home Penjual', $body, ['live' => 10000]);
}

function r_pen_riwayat(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $filters = ['semua' => 'Semua', 'baru' => 'Baru', 'diproses' => 'Proses', 'siap' => 'Siap', 'selesai' => 'Selesai', 'batal' => 'Batal'];
  $status = $_GET['status'] ?? 'baru';
  if (!isset($filters[$status]))
    $status = 'baru';
  $where = 'p.stand_id = :s';
  $par = [':s' => (int) $s['id']];
  if ($status !== 'semua') {
    $where .= ' AND p.status = :status';
    $par[':status'] = $status;
  }
  $os = pesanan_muat($where, $par, 'ORDER BY p.created_at DESC LIMIT 60');
  $cnt = [];
  foreach (db_sql('SELECT status, COUNT(*) AS n FROM pesanan WHERE stand_id = :s GROUP BY status', [':s' => (int) $s['id']]) as $r) {
    $cnt[$r['status']] = (int) $r['n'];
  }
  $cnt['semua'] = array_sum($cnt);
  $kosong = ['semua' => 'Belum ada pesanan masuk.', 'baru' => 'Tidak ada pesanan baru.', 'diproses' => 'Tidak ada pesanan yang sedang diproses.', 'siap' => 'Tidak ada pesanan yang siap diambil.', 'selesai' => 'Belum ada pesanan selesai.', 'batal' => 'Tidak ada pesanan dibatalkan.'];
  $next = ['baru' => ['diproses', 'Terima & proses'], 'diproses' => ['siap', 'Siap diambil'], 'siap' => ['selesai', 'Selesai (sudah diambil)']];
  ob_start(); ?>
  <?= hdx('Riwayat pesanan', ic('store', 15) . '<span>' . e($s['nama']) . '</span><i class="dot"></i><span>Kios ' . e($s['kios']) . '</span>', ['icon' => 'receipt']) ?>
  <div class="bd seller-history">
    <nav class="seller-filters" aria-label="Filter status pesanan"><?php foreach ($filters as $key => $label): ?><a
          href="/penjual/riwayat?status=<?= e($key) ?>"
          class="<?= $status === $key ? 'on' : '' ?>"><?= e($label) ?><?php if (!empty($cnt[$key])): ?><em><?= (int) $cnt[$key] ?></em><?php endif; ?></a><?php endforeach; ?>
    </nav>
    <div id="live" class="seller-order-list">
      <?php foreach ($os as $o): ?>
        <div class="box ocard seller-order">
          <div class="row">
            <div><small
                class="mut"><?= waktu($o['created_at']) ?><?= !empty($o['pelanggan_nama']) ? ' · ' . e($o['pelanggan_nama']) : '' ?></small><b>#<?= e($o['kode']) ?>
                · <?= e(LAY[$o['layanan']] ?? '') ?></b></div><?= tag($o['status']) ?>
          </div>
          <ul class="lines"><?php foreach ($o['pesanan_item'] as $i): ?>
              <li><b><?= (int) $i['jumlah'] ?> x</b>
                <?= e($i['nama']) ?>       <?= $i['catatan'] ? ' <small class="mut">(' . e($i['catatan']) . ')</small>' : '' ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="row seller-order-foot"><span><?= count($o['pesanan_item']) ?> item</span><b><?= rp($o['total']) ?></b>
          </div>
          <?= html_bukti_pembeli($o) ?>
          <?php if (isset($next[$o['status']])):
            [$to, $lb] = $next[$o['status']]; ?>
            <div class="btns" style="margin-top:12px">
              <?php if ($o['status'] !== 'siap'): ?>
                <form method="post" action="/penjual/status" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan."><input
                    type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="status"
                    value="batal"><button class="btn sm sec">Batalkan</button></form><?php endif; ?>
              <form method="post" action="/penjual/status"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input
                  type="hidden" name="status" value="<?= $to ?>"><button class="btn sm"><?= e($lb) ?></button></form>
            </div><?php endif; ?>
        </div>
      <?php endforeach;
      if (!$os)
        echo empty_state('receipt', $kosong[$status], 'Pesanan akan muncul di sini otomatis.'); ?>
    </div>
  </div><?= nav_pen('/penjual/riwayat');
  layout('Riwayat Pesanan', ob_get_clean(), ['live' => in_array($status, ['semua', 'baru', 'diproses', 'siap'], true) ? 6000 : 0]);
}

function r_pen_status(): void
{
  need('penjual');
  try {
    db_rpc('ubah_status', ['p_id' => (int) ($_POST['id'] ?? 0), 'p_status' => (string) ($_POST['status'] ?? '')]);
    flash('Status diperbarui');
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual');
}

function r_pen_buka(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  db_write('PATCH', 'stands?id=eq.' . (int) $s['id'], ['buka' => !$s['buka']]);
  $to = ($_POST['redirect'] ?? '') === '/penjual/profil' ? '/penjual/profil' : '/penjual';
  flash($s['buka'] ? 'Stand ditutup' : 'Stand dibuka');
  redirect($to);
}

function r_pen_menu(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $menu = db_get('menu?select=id,nama,harga,stok,tersedia,foto_url,kategori_id&stand_id=eq.' . (int) $s['id'] . '&order=nama');
  $kat = db_get('kategori?select=id,nama&order=nama');
  $opt = fn($sel) => '<option value="">Tanpa kategori</option>' . implode('', array_map(fn($k) => '<option value="' . (int) $k['id'] . '"' . ((int) $sel === (int) $k['id'] ? ' selected' : '') . '>' . e($k['nama']) . '</option>', $kat));
  $jmlAktif = count(array_filter($menu, fn($i) => $i['tersedia'] && $i['stok'] > 0));
  ob_start();
  ?>
  <?= hdx('Menu', ic('store', 15) . '<span>' . e($s['nama']) . '</span><i class="dot"></i><span>' . count($menu) . ' menu, ' . $jmlAktif . ' tersedia</span>', ['extra' => '<a href="#tambah-menu" class="hx-add" data-details="tambah-menu">' . ic('plus', 18) . '<span>Tambah menu</span></a>']) ?>
  <div class="bd seller-menu-page">
    <details class="box seller-add-menu" id="tambah-menu">
      <summary><b>Tambah menu baru</b><span>＋</span></summary>
      <form method="post" action="/penjual/menu/tambah" class="frm" enctype="multipart/form-data">
        <label>Nama menu<input name="nama" placeholder="Contoh: Nasi ayam" required
            maxlength="60"></label><label>Harga<input name="harga" type="number" min="0" placeholder="Harga (Rp)"
            required></label><label>Stok<input name="stok" type="number" min="0" placeholder="Jumlah stok"
            required></label>
        <select name="kategori_id"><?= $opt(0) ?></select>
        <label>Foto (JPG/PNG/WebP, maks 2 MB)<input name="foto" type="file"
            accept="image/jpeg,image/png,image/webp"></label>
        <button class="btn sm">Simpan menu</button>
      </form>
    </details>
    <?php foreach ($menu as $i): ?>
      <article class="box seller-menu-item">
        <div class="seller-menu-preview">
          <div class="seller-menu-photo"><?= img($i['foto_url'] ?? null) ?></div>
          <div class="seller-menu-info">
            <b><?= e($i['nama']) ?></b><strong><?= rp($i['harga']) ?></strong><small><?= (int) $i['stok'] ?> stok</small>
            <span
              class="mi-pill <?= $i['stok'] > 0 && $i['tersedia'] ? 'ok' : 'off' ?>"><?= $i['stok'] > 0 && $i['tersedia'] ? 'Tersedia' : 'Habis / nonaktif' ?></span>
          </div>
        </div>
        <details class="seller-edit-menu">
          <summary>Edit menu <span>⌄</span></summary>
          <form method="post" action="/penjual/menu/simpan" class="frm" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
            <label>Harga<input name="harga" type="number" min="0" value="<?= (int) $i['harga'] ?>" required></label>
            <label>Stok<input name="stok" type="number" min="0" value="<?= (int) $i['stok'] ?>" required></label>
            <label>Kategori<select name="kategori_id"><?= $opt($i['kategori_id'] ?? 0) ?></select></label>
            <label>Ganti foto (kosongkan jika tidak diganti)<input name="foto" type="file"
                accept="image/jpeg,image/png,image/webp"></label>
            <label class="chk"><input type="checkbox" name="tersedia" value="1" <?= $i['tersedia'] ? 'checked' : '' ?>>
              Tampilkan di menu</label>
            <div class="btns"><button class="btn sm">Simpan perubahan</button></div>
          </form>
          <form method="post" action="/penjual/menu/hapus" data-confirm="Hapus menu ini?" style="margin-top:8px"><input
              type="hidden" name="id" value="<?= (int) $i['id'] ?>"><button class="btn sm sec">Hapus menu</button></form>
        </details>
      </article>
    <?php endforeach;
    if (!$menu)
      echo empty_state('bowl', 'Belum ada menu', 'Tambahkan menu pertamamu supaya pelanggan bisa memesan.'); ?>
  </div><?= nav_pen('/penjual/menu');
  layout('Kelola Menu', ob_get_clean());
}

function r_pen_menu_tambah(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $k = (int) ($_POST['kategori_id'] ?? 0);
  try {
    $r = db_write('POST', 'menu', [
      'stand_id' => (int) $s['id'],
      'nama' => trim($_POST['nama'] ?? ''),
      'harga' => max(0, (int) ($_POST['harga'] ?? 0)),
      'stok' => max(0, (int) ($_POST['stok'] ?? 0)),
      'kategori_id' => $k ?: null,
      'tersedia' => true,
    ]);
    flash('Menu ditambahkan');
    if (($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($r[0]['id'])) {
      try {
        foto_simpan('menu', (int) $r[0]['id'], $_FILES['foto']);
      } catch (SbError $x) {
        flash('Menu ditambahkan, tetapi foto gagal: ' . $x->getMessage());
      }
    }
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual/menu');
}

function r_pen_menu_simpan(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $k = (int) ($_POST['kategori_id'] ?? 0);
  $id = (int) ($_POST['id'] ?? 0);
  try {
    $r = db_write('PATCH', 'menu?id=eq.' . $id . '&stand_id=eq.' . (int) $s['id'], [
      'harga' => max(0, (int) ($_POST['harga'] ?? 0)),
      'stok' => max(0, (int) ($_POST['stok'] ?? 0)),
      'kategori_id' => $k ?: null,
      'tersedia' => ($_POST['tersedia'] ?? '') === '1',
    ]);
    flash('Menu disimpan');
    if ($r && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      try {
        foto_simpan('menu', $id, $_FILES['foto']);
      } catch (SbError $x) {
        flash('Menu disimpan, tetapi foto gagal: ' . $x->getMessage());
      }
    }
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual/menu');
}

function r_pen_menu_hapus(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  try {
    db_delete('menu?id=eq.' . (int) ($_POST['id'] ?? 0) . '&stand_id=eq.' . (int) $s['id']);
    flash('Menu dihapus');
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual/menu');
}

function r_pen_profil(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $sid = (int) $s['id'];

  $menuStats = db_sql(
    "SELECT
            COUNT(*) FILTER (WHERE tersedia = true AND stok > 0) AS aktif,
            COUNT(*) FILTER (WHERE tersedia = false OR stok <= 0) AS habis
         FROM menu WHERE stand_id = :s",
    [':s' => $sid]
  )[0] ?? ['aktif' => 0, 'habis' => 0];

  $orderStats = db_sql(
    "SELECT
            COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE) AS hari_ini,
            COALESCE(SUM(total) FILTER (WHERE status = 'selesai' AND created_at::date = CURRENT_DATE), 0) AS omzet_hari_ini,
            COALESCE(SUM(total) FILTER (WHERE status = 'selesai' AND created_at >= CURRENT_DATE - INTERVAL '7 days'), 0) AS omzet_7_hari,
            COALESCE(SUM(total) FILTER (WHERE status = 'selesai'), 0) AS omzet_total,
            COUNT(*) FILTER (WHERE status = 'selesai') AS jml_selesai
         FROM pesanan WHERE stand_id = :s",
    [':s' => $sid]
  )[0] ?? ['hari_ini' => 0, 'omzet_hari_ini' => 0, 'omzet_7_hari' => 0, 'omzet_total' => 0, 'jml_selesai' => 0];

  $notifOn = ($_COOKIE['pico_notif_penjual'] ?? '1') === '1';
  $buka = (bool) $s['buka'];
  $qrUrl = (string) ($s['qr_url'] ?? '');

  $standInfoItems = [
    ['key' => 'stand_nama', 'label' => 'Nama stand', 'val' => (string) $s['nama'], 'raw' => (string) $s['nama'], 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8"/><path d="M9 14h6v6H9z"/></svg>'],
    ['key' => 'stand_kios', 'label' => 'Kios dan lokasi', 'val' => 'Kios ' . $s['kios'] . ' · Kantin AX', 'raw' => (string) $s['kios'], 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-5.4-7-11a7 7 0 1 1 14 0c0 5.6-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>'],
    ['key' => 'nama', 'label' => 'Nama penjual', 'val' => (string) ($m['nama'] ?? '-'), 'raw' => (string) ($m['nama'] ?? ''), 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.8-3.6 5-5.5 8-5.5s6.2 1.9 8 5.5"/></svg>'],
    ['key' => 'username', 'label' => 'Username', 'val' => (string) ($m['username'] ?? '-'), 'raw' => (string) ($m['username'] ?? ''), 'type' => 'text', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a2.5 2.5 0 0 0 5 0v-1a9 9 0 1 0-5.5 8.3"/></svg>'],
    ['key' => 'no_hp', 'label' => 'No. HP', 'val' => (string) (($m['no_hp'] ?? '') ?: '-'), 'raw' => (string) ($m['no_hp'] ?? ''), 'type' => 'tel', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7A2 2 0 0 1 22 16.9z"/></svg>'],
  ];

  ob_start(); ?>
  <div class="sp-page">
    <header class="sp-hero">
      <h1>Profil</h1>
      <div class="sp-stand-head">
        <div class="sp-stand-ic">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M3 9l1.5-5h15L21 9" />
            <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />
            <path d="M5 12v8h14v-8" />
            <path d="M9 14h6v6H9z" />
          </svg>
        </div>
        <div class="sp-stand-title">
          <b><?= e($s['nama']) ?></b>
          <span>Kios <?= e($s['kios']) ?> · Kantin AX</span>
        </div>
      </div>
    </header>

    <div class="sp-body">
      <a class="sp-status-bar" href="/penjual">
        <span class="sp-status-txt"><b>Status toko</b><small>Buka atau tutup lewat Home</small></span>
        <span class="status-pill <?= $buka ? 'is-open' : 'is-closed' ?>"><i></i><?= $buka ? 'Buka' : 'Tutup' ?></span>
        <?= ic('next', 18) ?>
      </a>

      <div class="sp-metrics">
        <div class="sp-metric m-green">
          <small>Menu aktif</small>
          <b><?= (int) $menuStats['aktif'] ?></b>
        </div>
        <div class="sp-metric m-blue">
          <small>Pesanan hari ini</small>
          <b><?= (int) $orderStats['hari_ini'] ?></b>
        </div>
        <div class="sp-metric m-amber">
          <small>Stok habis</small>
          <b><?= (int) $menuStats['habis'] ?></b>
        </div>
      </div>

      <div class="sp-sec-title">INFO STAND DAN AKUN</div>
      <div class="sp-card">
        <?php foreach ($standInfoItems as $item): ?>
          <div class="sp-row">
            <span class="sp-ic"><?= $item['icon'] ?></span>
            <div class="sp-info-main">
              <small><?= e($item['label']) ?></small>
              <b><?= e($item['val']) ?></b>
            </div>
            <button type="button" class="sp-edit-btn" data-sp-open="dlg-sp-<?= e($item['key']) ?>"
              aria-label="Edit <?= e($item['label']) ?>">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
              </svg>
            </button>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="sp-sec-title">PENGATURAN</div>
      <div class="sp-card">
        <button type="button" class="sp-set-row" data-sp-open="dlg-sp-qris">
          <span class="sp-ic">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="3" width="7" height="7" />
              <rect x="14" y="3" width="7" height="7" />
              <rect x="3" y="14" width="7" height="7" />
              <path d="M14 14h3v3h-3zM18 18h3v3h-3zM18 14h3M14 18v3" />
            </svg>
          </span>
          <span class="sp-set-label">Pembayaran QRIS</span>
          <span class="sp-chev">›</span>
        </button>

        <button type="button" class="sp-set-row" data-sp-open="dlg-sp-laporan">
          <span class="sp-ic">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M3 3v18h18" />
              <path d="m7 15 4-4 3 3 5-6" />
            </svg>
          </span>
          <span class="sp-set-label">Laporan pendapatan</span>
          <span class="sp-chev">›</span>
        </button>

        <form method="post" action="/penjual/profil/notifikasi" class="sp-set-row" style="cursor:default">
          <span class="sp-ic">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
          </span>
          <span class="sp-set-label">Notifikasi pesanan masuk</span>
          <button type="submit" class="sp-switch <?= $notifOn ? 'on' : '' ?>"
            aria-label="Toggle notifikasi pesanan masuk"></button>
        </form>

        <button type="button" class="sp-set-row" data-sp-open="dlg-sp-password">
          <span class="sp-ic">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="11" width="16" height="10" rx="2" />
              <path d="M8 11V7a4 4 0 0 1 8 0v4" />
              <circle cx="12" cy="16" r="1" />
            </svg>
          </span>
          <span class="sp-set-label">Ubah kata sandi</span>
          <span class="sp-chev">›</span>
        </button>
      </div>

      <a href="/keluar" class="sp-logout-btn">Keluar akun</a>
    </div>
  </div>

  <?php foreach ($standInfoItems as $item): ?>
    <dialog id="dlg-sp-<?= e($item['key']) ?>" class="sp-dlg">
      <form method="post" action="/penjual/profil/ubah">
        <h3>Ubah <?= e($item['label']) ?></h3>
        <input type="hidden" name="field" value="<?= e($item['key']) ?>">
        <div class="fld">
          <label><?= e($item['label']) ?></label>
          <input type="<?= e($item['type']) ?>" name="value" value="<?= e($item['raw']) ?>" required>
        </div>
        <div class="sp-dlg-btns">
          <button type="button" class="btn sec" data-sp-close>Batal</button>
          <button type="submit" class="btn">Simpan</button>
        </div>
      </form>
    </dialog>
  <?php endforeach; ?>

  <dialog id="dlg-sp-qris" class="sp-dlg">
    <form method="post" action="/penjual/profil/qris" enctype="multipart/form-data">
      <h3>Pembayaran QRIS</h3>
      <?php if ($qrUrl !== ''): ?>
        <div style="text-align:center;margin-bottom:12px">
          <img src="<?= e($qrUrl) ?>" alt="QRIS Stand"
            style="max-width:200px;max-height:200px;border-radius:12px;border:1px solid #e2e8f0">
        </div>
      <?php else: ?>
        <p class="mut" style="font-size:.85rem;margin:0 0 12px">Belum ada gambar QRIS yang diunggah untuk stand ini.</p>
      <?php endif; ?>
      <div class="fld">
        <label>Unggah QRIS Baru (JPG/PNG/WebP, maks. 2 MB)</label>
        <input type="file" name="qris" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <div class="sp-dlg-btns">
        <button type="button" class="btn sec" data-sp-close>Tutup</button>
        <button type="submit" class="btn">Simpan QRIS</button>
      </div>
    </form>
  </dialog>

  <dialog id="dlg-sp-laporan" class="sp-dlg">
    <h3>Laporan Pendapatan</h3>
    <div class="sp-rep-grid">
      <div class="sp-rep-box">
        <small>Hari ini</small>
        <b><?= rp($orderStats['omzet_hari_ini']) ?></b>
      </div>
      <div class="sp-rep-box">
        <small>7 hari terakhir</small>
        <b><?= rp($orderStats['omzet_7_hari']) ?></b>
      </div>
      <div class="sp-rep-box">
        <small>Total pendapatan</small>
        <b><?= rp($orderStats['omzet_total']) ?></b>
      </div>
      <div class="sp-rep-box">
        <small>Pesanan selesai</small>
        <b><?= (int) $orderStats['jml_selesai'] ?> pesanan</b>
      </div>
    </div>
    <div class="sp-dlg-btns">
      <a href="/penjual/riwayat?status=selesai" class="btn" style="text-align:center;text-decoration:none">Lihat Riwayat
        Selesai</a>
      <button type="button" class="btn sec" data-sp-close>Tutup</button>
    </div>
  </dialog>

  <dialog id="dlg-sp-password" class="sp-dlg">
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
      <div class="sp-dlg-btns">
        <button type="button" class="btn sec" data-sp-close>Batal</button>
        <button type="submit" class="btn">Simpan</button>
      </div>
    </form>
  </dialog>

  <script>
    (function () {
      document.querySelectorAll('[data-sp-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var dlg = document.getElementById(btn.getAttribute('data-sp-open'));
          if (dlg && dlg.showModal) dlg.showModal();
        });
      });
      document.querySelectorAll('[data-sp-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var dlg = btn.closest('dialog');
          if (dlg && dlg.close) dlg.close();
        });
      });
      document.querySelectorAll('dialog.sp-dlg').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
          if (e.target === dlg) dlg.close();
        });
      });
    })();
  </script>
  <?= nav_pen('/penjual/profil');
  layout('Profil', ob_get_clean());
}

function r_pen_profil_ubah(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  $field = trim((string) ($_POST['field'] ?? ''));
  $val = trim((string) ($_POST['value'] ?? ''));

  if ($val === '') {
    flash('Data tidak boleh kosong.');
    redirect('/penjual/profil');
  }

  try {
    if (in_array($field, ['stand_nama', 'stand_kios'], true)) {
      $col = $field === 'stand_nama' ? 'nama' : 'kios';
      if ($col === 'kios') {
        $val = preg_replace('/^kios\s+/i', '', $val);
        $val = trim(explode('·', $val)[0]);
      }
      db_write('PATCH', 'stands?id=eq.' . (int) $s['id'], [$col => $val]);
      flash('Info stand berhasil diperbarui.');
    } elseif (in_array($field, ['nama', 'username', 'no_hp'], true)) {
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
      }
      pg_run(
        'UPDATE profiles SET ' . pg_ident($field) . ' = :v WHERE id = :id',
        [':v' => $val, ':id' => $m['id']]
      );
      flash('Info akun berhasil diperbarui.');
    } else {
      throw new SbError('Kolom tidak dikenal.');
    }
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual/profil');
}

function r_pen_profil_qris(): void
{
  $m = need('penjual');
  $s = stand_saya($m);
  try {
    qr_simpan((int) $s['id'], $_FILES['qris'] ?? []);
    flash('QRIS stand berhasil diperbarui.');
  } catch (SbError $x) {
    flash($x->getMessage());
  }
  redirect('/penjual/profil');
}

function r_pen_profil_notifikasi(): void
{
  need('penjual');
  $cur = ($_COOKIE['pico_notif_penjual'] ?? '1') === '1';
  cookie_set('pico_notif_penjual', $cur ? '0' : '1', 86400 * 365);
  flash('Pengaturan notifikasi diperbarui.');
  redirect('/penjual/profil');
}