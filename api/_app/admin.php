<?php
    // admin.php : halaman admin. Akun penjual dibuat langsung di tabel profiles (password di-hash).

    function adm_layout(string $title, string $body, string $on): void {
        $links = ['/admin' => 'Dashboard', '/admin/stand' => 'Stand & Penjual', '/admin/pengguna' => 'Pengguna', '/admin/pesanan' => 'Pesanan'];
        $nav = '<header class="top"><b class="brand">PICO Admin</b><nav>';
        foreach ($links as $h => $l) $nav .= '<a href="' . $h . '" class="' . ($on === $h ? 'on' : '') . '">' . $l . '</a>';
        $nav .= '<a href="/keluar" class="out">Keluar</a></nav></header>';
        layout($title, $nav . '<main>' . $body . '</main>', ['css' => 'admin', 'wrap' => 'adm']);
    }

    function r_adm_home(): void {
        need('admin');
        $orders = db_sql("SELECT p.id, p.total, p.status, p.created_at, p.stand_id, s.nama AS stand_nama
                        FROM pesanan p LEFT JOIN stands s ON s.id = p.stand_id
                        WHERE p.status IN ('baru','diproses','siap','selesai')
                        ORDER BY p.created_at DESC LIMIT 2000");
        $hari = date('Y-m-d'); $today = 0; $incomeToday = 0; $incomeAll = 0; $per = [];
        foreach ($orders as $o) {
            $nama = $o['stand_nama'] ?? '-';
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
        $stands = db_sql('SELECT s.id, s.nama, s.kios, s.lokasi, s.buka, s.penjual_id, p.nama AS penjual_nama, p.username AS penjual_username
                        FROM stands s LEFT JOIN profiles p ON p.id = s.penjual_id
                        ORDER BY s.kios');
        ob_start(); ?>
    <h1>Stand & Penjual</h1>
    <details class="panel"><summary><b>+ Tambah stand baru</b></summary>
    <form method="post" action="/admin/stand/tambah" class="frm"><input name="nama" placeholder="Nama stand" required><input name="kios" type="number" min="1" placeholder="No. kios" required><input name="lokasi" placeholder="Lokasi (opsional)"><button class="btn">Simpan stand</button></form></details>
    <div class="tw"><table><tr><th>Kios</th><th>Stand</th><th>Status</th><th>Penjual</th><th>Aksi</th></tr>
    <?php foreach ($stands as $s): ?><tr><td><?= e($s['kios']) ?></td><td><?= e($s['nama']) ?></td><td><?= $s['buka'] ? 'Buka' : 'Tutup' ?></td>
    <td><?php if ($s['penjual_id']): ?><?= e($s['penjual_nama'] ?? '') ?><br><small class="mut"><?= e($s['penjual_username'] ?? '') ?></small><?php else: ?><span class="mut">Belum ada</span><?php endif; ?></td>
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
        need('admin');
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

    function r_adm_pengguna(): void {
        need('admin');
        $u = db_get('profiles?select=id,nama,username,no_hp,role,created_at&order=created_at.desc&limit=500');
        ob_start(); ?>
    <h1>Pengguna</h1><p class="mut">Mengubah peran menjadi admin dilakukan lewat SQL (lihat bagian bawah schema.sql).</p>
    <div class="tw"><table><tr><th>Nama Lengkap</th><th>Email</th><th>No. HP</th><th>Peran</th><th>Daftar</th></tr>
    <?php foreach ($u as $r): ?><tr><td><?= e($r['nama']) ?></td><td><?= e($r['username']) ?></td><td><?= e($r['no_hp'] ?? '-') ?></td><td><span class="tag r-<?= e($r['role']) ?>"><?= e($r['role']) ?></span></td><td><?= waktu($r['created_at']) ?></td></tr><?php endforeach; ?></table></div>
    <?php adm_layout('Pengguna', ob_get_clean(), '/admin/pengguna');
    }

    function r_adm_pesanan(): void {
        need('admin');
        $st = $_GET['status'] ?? '';
        $filter = isset(STATUS_LABEL[$st]);
        $os = db_sql(
            "SELECT p.id, p.kode, p.status, p.layanan, p.total, p.created_at, s.nama AS stand_nama,
                    (SELECT string_agg(i.jumlah::text || ' ' || i.nama, ', ' ORDER BY i.id) FROM pesanan_item i WHERE i.pesanan_id = p.id) AS menu_ringkas
            FROM pesanan p LEFT JOIN stands s ON s.id = p.stand_id"
            . ($filter ? ' WHERE p.status = :st' : '')
            . ' ORDER BY p.created_at DESC LIMIT 100',
            $filter ? [':st' => $st] : []
        );
        ob_start(); ?>
    <h1>Pesanan</h1>
    <div class="chips"><a href="/admin/pesanan" class="<?= $st === '' ? 'on' : '' ?>">Semua</a><?php foreach (STATUS_LABEL as $k => $l): ?><a href="/admin/pesanan?status=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <div class="tw"><table><tr><th>Kode</th><th>Stand</th><th>Menu</th><th>Layanan</th><th>Total</th><th>Status</th><th>Waktu</th></tr>
    <?php foreach ($os as $o): ?><tr><td>#<?= e($o['kode']) ?></td><td><?= e($o['stand_nama'] ?? '') ?></td><td><?= e($o['menu_ringkas'] ?? '') ?></td>
    <td><?= e(LAY[$o['layanan']] ?? '') ?></td><td><?= rp($o['total']) ?></td><td><?= tag($o['status']) ?></td><td><?= waktu($o['created_at']) ?></td></tr>
    <?php endforeach; if (!$os) echo '<tr><td colspan="7" class="mut">Tidak ada pesanan.</td></tr>'; ?></table></div>
    <?php adm_layout('Pesanan', ob_get_clean(), '/admin/pesanan');
    }