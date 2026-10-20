<?php
// admin.php : halaman admin. Membuat akun penjual memakai service_role key (hanya di server).

function adm_layout(string $title, string $body, string $on): void {
    $links = ['/admin' => 'Dashboard', '/admin/stand' => 'Stand & Penjual', '/admin/pengguna' => 'Pengguna', '/admin/pesanan' => 'Pesanan'];
    $nav = '<header class="top"><b class="brand">PICO Admin</b><nav>';
    foreach ($links as $h => $l) $nav .= '<a href="' . $h . '" class="' . ($on === $h ? 'on' : '') . '">' . $l . '</a>';
    $nav .= '<a href="/keluar" class="out">Keluar</a></nav></header>';
    layout($title, $nav . '<main>' . $body . '</main>', ['css' => 'admin', 'wrap' => 'adm']);
}

function r_adm_home(): void {
    need('admin');
    $orders = db_get('pesanan?select=id,total,status,created_at,stand_id,stands(nama)&status=in.(baru,diproses,siap,selesai)&order=created_at.desc&limit=2000');
    $hari = date('Y-m-d'); $today = 0; $incomeToday = 0; $incomeAll = 0; $per = [];
    foreach ($orders as $o) {
        $nama = $o['stands']['nama'] ?? '-';
        $per[$nama] = $per[$nama] ?? ['n' => 0, 'rp' => 0]; $per[$nama]['n']++; $per[$nama]['rp'] += $o['total']; $incomeAll += $o['total'];
        if (date('Y-m-d', strtotime($o['created_at'])) === $hari) { $today++; $incomeToday += $o['total']; }
    }
    arsort($per);
    $users = db_get('profiles?select=role'); $by = array_count_values(array_column($users, 'role'));
    $stands = db_get('stands?select=id,buka');
    ob_start(); ?>
<h1>Dashboard</h1>
<div class="cards">
  <div class="card"><small>Pesanan hari ini</small><b><?= $today ?></b></div>
  <div class="card"><small>Pendapatan hari ini</small><b><?= rp($incomeToday) ?></b></div>
  <div class="card"><small>Total pendapatan</small><b><?= rp($incomeAll) ?></b></div>
  <div class="card"><small>Stand buka / total</small><b><?= count(array_filter($stands, fn($s) => $s['buka'])) ?> / <?= count($stands) ?></b></div>
  <div class="card"><small>Pelanggan</small><b><?= (int)($by['pelanggan'] ?? 0) ?></b></div>
  <div class="card"><small>Penjual</small><b><?= (int)($by['penjual'] ?? 0) ?></b></div>
</div>
<h2>Penjualan per stand</h2>
<div class="tw"><table><tr><th>Stand</th><th>Jumlah pesanan</th><th>Pendapatan</th></tr>
<?php foreach ($per as $n => $v): ?><tr><td><?= e($n) ?></td><td><?= $v['n'] ?></td><td><?= rp($v['rp']) ?></td></tr><?php endforeach; if (!$per) echo '<tr><td colspan="3" class="mut">Belum ada pesanan dibayar.</td></tr>'; ?></table></div>
<?php adm_layout('Dashboard', ob_get_clean(), '/admin');
}

function r_adm_stand(): void {
    need('admin');
    $stands = db_get('stands?select=id,nama,kios,lokasi,buka,penjual_id,profiles(nama,username)&order=kios');
    ob_start(); ?>
<h1>Stand & Penjual</h1>
<details class="panel"><summary><b>+ Tambah stand baru</b></summary>
  <form method="post" action="/admin/stand/tambah" class="frm"><input name="nama" placeholder="Nama stand" required><input name="kios" type="number" min="1" placeholder="No. kios" required><input name="lokasi" placeholder="Lokasi (opsional)"><button class="btn">Simpan stand</button></form></details>
<div class="tw"><table><tr><th>Kios</th><th>Stand</th><th>Status</th><th>Penjual</th><th>Aksi</th></tr>
<?php foreach ($stands as $s): ?><tr><td><?= e($s['kios']) ?></td><td><?= e($s['nama']) ?></td><td><?= $s['buka'] ? 'Buka' : 'Tutup' ?></td>
  <td><?php if ($s['penjual_id']): ?><?= e($s['profiles']['nama'] ?? '') ?><br><small class="mut"><?= e($s['profiles']['username'] ?? '') ?></small><?php else: ?><span class="mut">Belum ada</span><?php endif; ?></td>
  <td><details><summary><?= $s['penjual_id'] ? 'Reset password' : 'Buat akun penjual' ?></summary>
    <?php if ($s['penjual_id']): ?>
    <form method="post" action="/admin/stand/reset" class="frm"><input type="hidden" name="stand" value="<?= (int)$s['id'] ?>"><input name="password" type="password" minlength="6" placeholder="Password baru" required><button class="btn sm">Ganti</button></form>
    <?php else: ?>
    <form method="post" action="/admin/stand/penjual" class="frm"><input type="hidden" name="stand" value="<?= (int)$s['id'] ?>"><input name="nama" placeholder="Nama penjual" required><input name="email" type="email" placeholder="Email login" required><input name="password" type="password" minlength="6" placeholder="Password awal" required><button class="btn sm">Buat akun</button></form>
    <?php endif; ?></details></td></tr>
<?php endforeach; ?></table></div>
<?php adm_layout('Stand', ob_get_clean(), '/admin/stand');
}

function r_adm_stand_tambah(): void {
    need('admin');
    try { db_write('POST', 'stands', ['nama' => trim($_POST['nama'] ?? ''), 'kios' => (int)($_POST['kios'] ?? 0), 'lokasi' => trim($_POST['lokasi'] ?? '') ?: null]); flash('Stand ditambahkan'); }
    catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_stand_penjual(): void {
    need('admin'); $sid = (int)($_POST['stand'] ?? 0);
    try {
        // 1) buat akun login (server memakai service_role key), 2) naikkan role jadi penjual, 3) hubungkan ke stand
        $u = sb('POST', '/auth/v1/admin/users', ['email' => trim($_POST['email'] ?? ''), 'password' => (string)($_POST['password'] ?? ''), 'email_confirm' => true,
            'user_metadata' => ['nama' => trim($_POST['nama'] ?? 'Penjual')]], null, true);
        db_write('PATCH', 'profiles?id=eq.' . rawurlencode($u['id']), ['role' => 'penjual'], true);
        db_write('PATCH', 'stands?id=eq.' . $sid, ['penjual_id' => $u['id']], true);
        flash('Akun penjual dibuat');
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_stand_reset(): void {
    need('admin');
    try {
        $s = db_get('stands?select=penjual_id&id=eq.' . (int)($_POST['stand'] ?? 0))[0] ?? null;
        if (!$s || !$s['penjual_id']) throw new SbError('Stand belum punya penjual.');
        sb('PUT', '/auth/v1/admin/users/' . rawurlencode($s['penjual_id']), ['password' => (string)($_POST['password'] ?? '')], null, true);
        flash('Password diganti');
    } catch (SbError $x) { flash($x->getMessage()); }
    redirect('/admin/stand');
}

function r_adm_pengguna(): void {
    need('admin');
    $u = db_get('profiles?select=id,nama,username,no_hp,role,created_at&order=created_at.desc&limit=500');
    ob_start(); ?>
<h1>Pengguna</h1><p class="mut">Mengubah peran menjadi admin dilakukan lewat SQL Editor Supabase (lihat README).</p>
<div class="tw"><table><tr><th>Nama</th><th>Username</th><th>No. HP</th><th>Peran</th><th>Daftar</th></tr>
<?php foreach ($u as $r): ?><tr><td><?= e($r['nama']) ?></td><td><?= e($r['username']) ?></td><td><?= e($r['no_hp'] ?? '-') ?></td><td><span class="tag r-<?= e($r['role']) ?>"><?= e($r['role']) ?></span></td><td><?= waktu($r['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php adm_layout('Pengguna', ob_get_clean(), '/admin/pengguna');
}

function r_adm_pesanan(): void {
    need('admin');
    $st = $_GET['status'] ?? '';
    $f = isset(STATUS_LABEL[$st]) ? '&status=eq.' . $st : '';
    $os = db_get('pesanan?select=id,kode,status,layanan,total,created_at,stands(nama),pesanan_item(nama,jumlah)' . $f . '&order=created_at.desc&limit=100');
    ob_start(); ?>
<h1>Pesanan</h1>
<div class="chips"><a href="/admin/pesanan" class="<?= $st === '' ? 'on' : '' ?>">Semua</a><?php foreach (STATUS_LABEL as $k => $l): ?><a href="/admin/pesanan?status=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="tw"><table><tr><th>Kode</th><th>Stand</th><th>Menu</th><th>Layanan</th><th>Total</th><th>Status</th><th>Waktu</th></tr>
<?php foreach ($os as $o): ?><tr><td>#<?= e($o['kode']) ?></td><td><?= e($o['stands']['nama'] ?? '') ?></td><td><?= e(implode(', ', array_map(fn($i) => $i['jumlah'] . ' ' . $i['nama'], $o['pesanan_item']))) ?></td>
  <td><?= e(LAY[$o['layanan']] ?? '') ?></td><td><?= rp($o['total']) ?></td><td><?= tag($o['status']) ?></td><td><?= waktu($o['created_at']) ?></td></tr>
<?php endforeach; if (!$os) echo '<tr><td colspan="7" class="mut">Tidak ada pesanan.</td></tr>'; ?></table></div>
<?php adm_layout('Pesanan', ob_get_clean(), '/admin/pesanan');
}
