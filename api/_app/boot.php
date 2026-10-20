
<?php
// =====================================================================
// boot.php : pengaturan dasar, koneksi PostgreSQL (PDO), dan sesi login.
// =====================================================================

date_default_timezone_set('Asia/Jakarta');

// Membaca file .env lokal.
$envFile = dirname(__DIR__, 2) . '/.env';

if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v, " \t\"'"));
    }
}

// Membaca konfigurasi hosting jika tersedia.
$cfgFile = __DIR__ . '/config.server.php';

if (is_file($cfgFile)) {
    $cfg = require $cfgFile;

    if (is_array($cfg)) {
        foreach ($cfg as $k => $v) {
            if ((string) getenv((string) $k) === '') {
                putenv($k . '=' . $v);
            }
        }
    }
}

function env(string $k): string
{
    $v = getenv($k);
    return $v === false ? '' : (string) $v;
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rp($n): string
{
    return 'Rp' . number_format((float) $n, 0, ',', '.');
}

function waktu(?string $iso): string
{
    return $iso ? date('d M H:i', strtotime($iso)) : '-';
}

function lc(string $s): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($s)
        : strtolower($s);
}

function cut(string $s, int $n): string
{
    return preg_match('/^.{0,' . $n . '}/us', $s, $m)
        ? $m[0]
        : substr($s, 0, $n);
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 303);
    exit;
}

function is_https(): bool
{
    return !empty($_SERVER['HTTPS']) &&
        strtolower((string) $_SERVER['HTTPS']) !== 'off'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function cookie_set(string $n, string $v, int $ttl = 0): void
{
    setcookie($n, $v, [
        'expires' => $ttl ? time() + $ttl : 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[$n] = $v;
}

function cookie_del(string $n): void
{
    setcookie($n, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[$n]);
}

function flash(string $m): void
{
    cookie_set('pico_flash', $m, 60);
}

function take_flash(): string
{
    $m = $_COOKIE['pico_flash'] ?? '';

    if ($m !== '') {
        cookie_del('pico_flash');
    }

    return $m;
}

// Perlindungan dasar terhadap pengiriman form dari domain lain.
function check_origin(): void
{
    $o = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($o === '') {
        return;
    }

    $oh = parse_url($o, PHP_URL_HOST);
    $hosts = [];

    foreach (['HTTP_HOST', 'HTTP_X_FORWARDED_HOST'] as $k) {
        foreach (explode(',', $_SERVER[$k] ?? '') as $h) {
            $hosts[] = explode(':', trim($h))[0];
        }
    }

    $hosts = array_filter($hosts);

    if ($oh && $hosts && !in_array($oh, $hosts, true)) {
        http_response_code(403);
        exit('Permintaan ditolak.');
    }
}

// =====================================================================
// SESI LOGIN
// =====================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('pico_sid');

    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// =====================================================================
// KONEKSI POSTGRESQL DENGAN PDO
// =====================================================================

class SbError extends Exception
{
}

function pdo(): PDO
{
    static $p = null;

    if ($p instanceof PDO) {
        return $p;
    }

    if (
        env('DB_HOST') === '' ||
        env('DB_DATABASE') === '' ||
        env('DB_USERNAME') === ''
    ) {
        throw new SbError(
            'DB_HOST, DB_DATABASE, dan DB_USERNAME belum diisi.'
        );
    }

    try {
        $dsn = 'pgsql:host=' . env('DB_HOST')
            . ';port=' . (env('DB_PORT') ?: '5432')
            . ';dbname=' . env('DB_DATABASE');

        $p = new PDO(
            $dsn,
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $p->prepare(
            "SELECT set_config('app.uid', :u, false)"
        )->execute([
            ':u' => (string) ($_SESSION['uid'] ?? ''),
        ]);
    } catch (PDOException $x) {
        error_log('Koneksi database gagal: ' . $x->getMessage());
        throw new SbError('Tidak bisa terhubung ke database.');
    }

    return $p;
}

function pg_ident(string $s): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s)) {
        throw new SbError('Nama kolom atau tabel tidak valid.');
    }

    return '"' . $s . '"';
}

function pg_run(string $sql, array $par = []): PDOStatement
{
    try {
        $st = pdo()->prepare($sql);

        foreach ($par as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }

            $type = is_bool($v)
                ? PDO::PARAM_BOOL
                : (is_null($v)
                    ? PDO::PARAM_NULL
                    : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));

            $st->bindValue($k, $v, $type);
        }

        $st->execute();

        return $st;
    } catch (PDOException $x) {
        $info = $x->errorInfo ?? [];

        if (($info[0] ?? '') === 'P0001') {
            $message = trim(
                (string) preg_replace(
                    '/^ERROR:\s*/',
                    '',
                    (string) ($info[2] ?? '')
                )
            );

            throw new SbError($message, 400);
        }

        error_log('DB error: ' . $x->getMessage());

        if (env('APP_DEBUG') === '1') {
            throw new SbError('DB: ' . $x->getMessage(), 500);
        }

        throw new SbError('Terjadi kesalahan pada database.', 500);
    }
}

// =====================================================================
// QUERY GAYA REST LAMA, DIJALANKAN KE POSTGRESQL
// =====================================================================

function pg_parse(string $qs): array
{
    $q = strpos($qs, '?');
    $table = $q === false ? $qs : substr($qs, 0, $q);
    $rest = $q === false ? '' : substr($qs, $q + 1);

    $r = [
        'table' => $table,
        'select' => '*',
        'order' => '',
        'limit' => null,
        'offset' => null,
        'where' => '',
        'par' => [],
    ];

    $ops = [
        'eq' => '=',
        'neq' => '<>',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'like' => 'LIKE',
        'ilike' => 'ILIKE',
    ];

    $w = [];
    $n = 0;

    foreach (array_filter(explode('&', $rest), 'strlen') as $pair) {
        $eq = strpos($pair, '=');

        if ($eq === false) {
            continue;
        }

        $k = rawurldecode(substr($pair, 0, $eq));
        $v = rawurldecode(substr($pair, $eq + 1));

        if ($k === 'select') {
            if (str_contains($v, '(') || str_contains($v, ':')) {
                throw new SbError('Select bersarang belum didukung.');
            }

            $r['select'] = $v === '*'
                ? '*'
                : implode(
                    ', ',
                    array_map(
                        'pg_ident',
                        array_map('trim', explode(',', $v))
                    )
                );
        } elseif ($k === 'order') {
            $o = [];

            foreach (explode(',', $v) as $part) {
                $bits = explode('.', trim($part));
                $direction = strtolower($bits[1] ?? 'asc');

                if (!in_array($direction, ['asc', 'desc'], true)) {
                    throw new SbError('Arah pengurutan tidak valid.');
                }

                $o[] = pg_ident($bits[0]) .
                    ($direction === 'desc' ? ' DESC' : ' ASC');
            }

            $r['order'] = ' ORDER BY ' . implode(', ', $o);
        } elseif ($k === 'limit') {
            $r['limit'] = max(0, (int) $v);
        } elseif ($k === 'offset') {
            $r['offset'] = max(0, (int) $v);
        } elseif ($k === 'or' || $k === 'and') {
            throw new SbError('Filter ' . $k . ' belum didukung.');
        } else {
            $dot = strpos($v, '.');

            if ($dot === false) {
                throw new SbError('Filter tidak valid: ' . $k);
            }

            $op = substr($v, 0, $dot);
            $val = substr($v, $dot + 1);
            $col = pg_ident($k);

            if ($op === 'in') {
                $ph = [];

                foreach (explode(',', trim($val, '()')) as $item) {
                    $key = ':w' . (++$n);
                    $r['par'][$key] = trim($item, " \"'");
                    $ph[] = $key;
                }

                $w[] = $ph
                    ? $col . ' IN (' . implode(',', $ph) . ')'
                    : 'FALSE';
            } elseif ($op === 'is') {
                $map = [
                    'null' => 'NULL',
                    'true' => 'TRUE',
                    'false' => 'FALSE',
                ];

                if (!isset($map[$val])) {
                    throw new SbError('Filter is tidak valid.');
                }

                $w[] = $col . ' IS ' . $map[$val];
            } elseif (isset($ops[$op])) {
                $key = ':w' . (++$n);
                $r['par'][$key] = in_array($op, ['like', 'ilike'], true)
                    ? str_replace('*', '%', $val)
                    : $val;

                $w[] = $col . ' ' . $ops[$op] . ' ' . $key;
            } else {
                throw new SbError('Operator tidak dikenal: ' . $op);
            }
        }
    }

    $r['where'] = $w
        ? ' WHERE ' . implode(' AND ', $w)
        : '';

    return $r;
}

function db_get(string $qs, bool $svc = false): array
{
    $p = pg_parse($qs);

    $sql = 'SELECT ' . $p['select']
        . ' FROM ' . pg_ident($p['table'])
        . $p['where']
        . $p['order'];

    if ($p['limit'] !== null) {
        $sql .= ' LIMIT ' . (int) $p['limit'];
    }

    if ($p['offset'] !== null) {
        $sql .= ' OFFSET ' . (int) $p['offset'];
    }

    return pg_run($sql, $p['par'])->fetchAll();
}

function db_write(
    string $method,
    string $qs,
    array $body,
    bool $svc = false
) {
    $p = pg_parse($qs);
    $t = pg_ident($p['table']);

    if ($method === 'POST') {
        $rows = (
            $body &&
            array_keys($body) === range(0, count($body) - 1)
        ) ? $body : [$body];

        if (!$rows) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $cols = array_keys($row);
            $par = [];
            $ph = [];

            foreach ($cols as $i => $c) {
                $ph[] = ':v' . $i;
                $par[':v' . $i] = $row[$c] ?? null;
            }

            $sql = 'INSERT INTO ' . $t
                . ' (' . implode(', ', array_map('pg_ident', $cols)) . ')'
                . ' VALUES (' . implode(', ', $ph) . ') RETURNING *';

            foreach (pg_run($sql, $par)->fetchAll() as $x) {
                $out[] = $x;
            }
        }

        return $out;
    }

    if ($method === 'PATCH') {
        if ($p['where'] === '') {
            throw new SbError('UPDATE tanpa filter ditolak.');
        }

        if (!$body) {
            throw new SbError('Data UPDATE kosong.');
        }

        $set = [];
        $par = $p['par'];

        foreach (array_keys($body) as $i => $c) {
            $set[] = pg_ident($c) . ' = :s' . $i;
            $par[':s' . $i] = $body[$c];
        }

        return pg_run(
            'UPDATE ' . $t . ' SET ' . implode(', ', $set)
            . $p['where'] . ' RETURNING *',
            $par
        )->fetchAll();
    }

    throw new SbError('Metode tidak didukung: ' . $method);
}

function db_delete(string $qs, bool $svc = false): void
{
    $p = pg_parse($qs);

    if ($p['where'] === '') {
        throw new SbError('DELETE tanpa filter ditolak.');
    }

    pg_run(
        'DELETE FROM ' . pg_ident($p['table']) . $p['where'],
        $p['par']
    );
}

function db_sql(string $sql, array $par = []): array
{
    return pg_run($sql, $par)->fetchAll();
}

function pesanan_muat(
    string $where,
    array $par = [],
    string $tail = ''
): array {
    $rows = db_sql(
        'SELECT p.id, p.kode, p.status, p.layanan, p.total, p.created_at,
                s.nama AS stand_nama, s.kios AS stand_kios
         FROM pesanan p
         LEFT JOIN stands s ON s.id = p.stand_id
         WHERE ' . $where . ' ' . $tail,
        $par
    );

    if (!$rows) {
        return [];
    }

    $ids = implode(',', array_map('intval', array_column($rows, 'id')));
    $by = [];

    foreach (
        db_sql(
            'SELECT pesanan_id, nama, jumlah, harga, catatan
             FROM pesanan_item
             WHERE pesanan_id IN (' . $ids . ')
             ORDER BY id'
        ) as $i
    ) {
        $by[$i['pesanan_id']][] = [
            'nama' => $i['nama'],
            'jumlah' => $i['jumlah'],
            'harga' => $i['harga'],
            'catatan' => $i['catatan'],
        ];
    }

    foreach ($rows as &$r) {
        $r['stands'] = [
            'nama' => $r['stand_nama'],
            'kios' => $r['stand_kios'],
        ];

        $r['pesanan_item'] = $by[$r['id']] ?? [];
    }

    unset($r);

    return $rows;
}

function db_rpc(string $fn, array $args = [], bool $svc = false)
{
    $ph = [];
    $par = [];
    $i = 0;

    foreach ($args as $k => $v) {
        $ph[] = pg_ident($k) . ' := :a' . $i;
        $par[':a' . $i] = $v;
        $i++;
    }

    $rows = pg_run(
        'SELECT * FROM ' . pg_ident($fn)
        . '(' . implode(', ', $ph) . ')',
        $par
    )->fetchAll();

    if (
        count($rows) === 1 &&
        count($rows[0]) === 1 &&
        array_key_exists($fn, $rows[0])
    ) {
        $v = $rows[0][$fn];

        if (
            is_string($v) &&
            $v !== '' &&
            in_array($v[0], ['{', '['], true)
        ) {
            $j = json_decode($v, true);

            if ($j !== null) {
                return $j;
            }
        }

        return $v;
    }

    return $rows;
}

// =====================================================================
// DAFTAR DAN LOGIN
// =====================================================================

function auth_signup(
    string $email,
    string $pw,
    string $nama,
    string $hp
): array {
    $email = lc(trim($email));

    if (
        pg_run(
            'SELECT 1 FROM profiles WHERE LOWER(email) = :e',
            [':e' => $email]
        )->fetch()
    ) {
        throw new SbError('Email sudah terdaftar.', 422);
    }

    $r = pg_run(
        'INSERT INTO profiles
            (email, password_hash, nama, no_hp, role)
         VALUES (:e, :h, :n, :p, :r)
         RETURNING id',
        [
            ':e' => $email,
            ':h' => password_hash($pw, PASSWORD_DEFAULT),
            ':n' => $nama,
            ':p' => $hp,
            ':r' => 'pelanggan',
        ]
    )->fetch();

    return ['user_id' => $r['id']];
}

/**
 * Login menggunakan username, email, nomor HP, atau NIM.
 * Mendukung hash dari PHP password_hash() dan PostgreSQL crypt().
 */
function auth_login(string $identifier, string $pw): array
{
    $identifier = trim($identifier);

    if ($identifier === '' || $pw === '') {
        throw new SbError('Username dan password wajib diisi.', 400);
    }

    $row = pg_run(
        "SELECT id, password_hash,
                (password_hash = crypt(:pw, password_hash)) AS crypt_ok
         FROM profiles
         WHERE LOWER(username) = LOWER(:username)
            OR LOWER(email) = LOWER(:email)
            OR no_hp = :hp
            OR nim = :nim
         ORDER BY CASE
             WHEN LOWER(username) = LOWER(:prioritas) THEN 0
             WHEN LOWER(email) = LOWER(:email_prioritas) THEN 1
             ELSE 2
         END
         LIMIT 1",
        [
            ':pw' => $pw,
            ':username' => $identifier,
            ':email' => $identifier,
            ':hp' => $identifier,
            ':nim' => $identifier,
            ':prioritas' => $identifier,
            ':email_prioritas' => $identifier,
        ]
    )->fetch();

    $cryptOk = $row && in_array(
        $row['crypt_ok'] ?? false,
        [true, 1, '1', 't', 'true'],
        true
    );

    $phpOk = $row &&
        !empty($row['password_hash']) &&
        password_verify($pw, $row['password_hash']);

    if (!$row || (!$phpOk && !$cryptOk)) {
        throw new SbError('Username atau password salah.', 400);
    }

    return ['user_id' => $row['id']];
}

function save_session(array $s): void
{
    session_regenerate_id(true);

    $_SESSION['uid'] = $s['user_id'];

    pg_run(
        "SELECT set_config('app.uid', :u, false)",
        [':u' => (string) $s['user_id']]
    );
}

function clear_session(): void
{
    unset($_SESSION['uid']);
    session_regenerate_id(true);

    pg_run("SELECT set_config('app.uid', '', false)");
}

function me(): ?array
{
    static $m = false;

    if ($m !== false) {
        return $m;
    }

    $id = (string) ($_SESSION['uid'] ?? '');

    if ($id === '') {
        return $m = null;
    }

    try {
        $r = db_get(
            'profiles?select=id,role,nama,username,no_hp&id=eq.'
            . rawurlencode($id)
        );
    } catch (SbError $x) {
        unset($_SESSION['uid']);
        return $m = null;
    }

    return $m = ($r[0] ?? null);
}

function home_for(string $role): string
{
    return [
        'pelanggan' => '/home',
        'penjual' => '/penjual',
        'admin' => '/admin',
    ][$role] ?? '/';
}

function need(string $role): array
{
    $m = me();

    if (!$m) {
        redirect('/');
    }

    if ($m['role'] !== $role) {
        redirect(home_for($m['role']));
    }

    return $m;
}

// =====================================================================
// KERANJANG
// =====================================================================

function cart(): array
{
    $c = json_decode($_COOKIE['pico_cart'] ?? '', true);

    return is_array($c) && isset($c['i']) && is_array($c['i'])
        ? $c
        : ['s' => 0, 'i' => []];
}

function cart_save(array $c): void
{
    $c['i'] = array_values($c['i']);

    if ($c['i']) {
        cookie_set(
            'pico_cart',
            json_encode($c, JSON_UNESCAPED_UNICODE),
            86400
        );
    } else {
        cookie_del('pico_cart');
    }
}

function cart_detail(): array
{
    $c = cart();
    $items = [];
    $total = 0;
    $stand = null;

    if ($c['i']) {
        $ids = implode(
            ',',
            array_map('intval', array_column($c['i'], 'id'))
        );

        $by = [];

        foreach (
            db_get(
                'menu?select=id,nama,harga,stok,tersedia,foto_url'
                . '&stand_id=eq.' . (int) $c['s']
                . '&id=in.(' . $ids . ')'
            ) as $r
        ) {
            $by[$r['id']] = $r;
        }

        $standRows = db_get(
            'stands?select=id,nama,kios,buka&id=eq.' . (int) $c['s']
        );

        $stand = $standRows[0] ?? null;

        foreach ($c['i'] as $x) {
            $m = $by[$x['id']] ?? null;

            if (!$m || !$m['tersedia'] || $m['stok'] < 1) {
                continue;
            }

            $j = min((int) $x['j'], (int) $m['stok']);

            if ($j < 1) {
                continue;
            }

            $items[] = [
                'id' => $m['id'],
                'nama' => $m['nama'],
                'harga' => $m['harga'],
                'jml' => $j,
                'cat' => $x['c'] ?? '',
                'foto' => $m['foto_url'],
                'stok' => $m['stok'],
            ];

            $total += $m['harga'] * $j;
        }
    }

    return compact('stand', 'items', 'total');
}
