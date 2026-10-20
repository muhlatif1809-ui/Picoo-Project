<?php
// =====================================================================
// boot.php : pengaturan dasar, koneksi ke Supabase, dan sesi login.
// Semua halaman memanggil file ini lebih dulu.
// =====================================================================
date_default_timezone_set('Asia/Jakarta');

// Baca file .env (hanya dipakai saat jalan di komputer sendiri).
// Di Vercel, isi lewat Settings > Environment Variables.
$envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v, " \t\"'"));
    }
}

function env(string $k): string { $v = getenv($k); return $v === false ? '' : (string)$v; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n): string { return 'Rp' . number_format((float)$n, 0, ',', '.'); }
function waktu(?string $iso): string { return $iso ? date('d M H:i', strtotime($iso)) : '-'; }
// Pengganti mb_* supaya tidak bergantung pada ekstensi mbstring.
function lc(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); }
function cut(string $s, int $n): string { return preg_match('/^.{0,' . $n . '}/us', $s, $m) ? $m[0] : substr($s, 0, $n); }
function redirect(string $to): void { header('Location: ' . $to, true, 303); exit; }
function is_https(): bool { return !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }

function cookie_set(string $n, string $v, int $ttl = 0): void {
    setcookie($n, $v, ['expires' => $ttl ? time() + $ttl : 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[$n] = $v;
}
function cookie_del(string $n): void { setcookie($n, '', ['expires' => time() - 3600, 'path' => '/']); unset($_COOKIE[$n]); }
function flash(string $m): void { cookie_set('pico_flash', $m, 60); }
function take_flash(): string { $m = $_COOKIE['pico_flash'] ?? ''; if ($m !== '') cookie_del('pico_flash'); return $m; }

// Tolak form yang dikirim dari website lain.
function check_origin(): void {
    $o = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($o === '') return;
    $oh = parse_url($o, PHP_URL_HOST);
    $hosts = [];
    foreach (['HTTP_HOST', 'HTTP_X_FORWARDED_HOST'] as $k) foreach (explode(',', $_SERVER[$k] ?? '') as $h) $hosts[] = explode(':', trim($h))[0];
    $hosts = array_filter($hosts);
    if ($hosts && !in_array($oh, $hosts, true)) { http_response_code(403); exit('Permintaan ditolak.'); }
}

// ---------------------------------------------------------------------
// Klien Supabase (REST + Auth lewat cURL)
// ---------------------------------------------------------------------
class SbError extends Exception {}

if (!function_exists('sb')) {
    /** Satu pintu ke Supabase. $svc=true memakai service_role key (HANYA untuk aksi admin/login di server). */
    function sb(string $method, string $path, $body = null, ?string $token = null, bool $svc = false, array $extra = []) {
        $base = rtrim(env('SUPABASE_URL'), '/');
        $key = $svc ? env('SUPABASE_SERVICE_ROLE_KEY') : env('SUPABASE_ANON_KEY');
        if ($base === '' || $key === '') throw new SbError($svc ? 'SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY belum diisi.' : 'SUPABASE_URL / SUPABASE_ANON_KEY belum diisi.');
        $h = ['apikey: ' . $key, 'Content-Type: application/json', 'Accept: application/json'];
        $bearer = $svc ? $key : ($token ?: $key);
        if (!str_starts_with($bearer, 'sb_')) $h[] = 'Authorization: Bearer ' . $bearer; // key model baru (sb_...) tidak dikirim sebagai Bearer
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
        if (function_exists('curl_init')) {
            $ch = curl_init($base . $path);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => array_merge($h, $extra), CURLOPT_POSTFIELDS => $payload]);
            $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        } else { // cadangan jika cURL tidak tersedia
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", array_merge($h, $extra)), 'content' => $payload ?? '', 'ignore_errors' => true, 'timeout' => 15]]);
            $raw = @file_get_contents($base . $path, false, $ctx); $err = 'gagal terhubung';
            $code = preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $mm) ? (int)$mm[1] : 0;
        }
        if ($raw === false) throw new SbError('Tidak bisa terhubung ke Supabase: ' . $err);
        $data = $raw === '' ? null : json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($data) ? ($data['message'] ?? $data['msg'] ?? $data['error_description'] ?? $data['error'] ?? 'Terjadi kesalahan') : 'Terjadi kesalahan';
            throw new SbError((string)$msg, $code);
        }
        return $data;
    }
}

function tok(): ?string { return $GLOBALS['TOK'] ?? null; }
function db_get(string $qs, bool $svc = false): array { return sb('GET', '/rest/v1/' . $qs, null, tok(), $svc) ?? []; }
function db_write(string $method, string $qs, array $body, bool $svc = false) { return sb($method, '/rest/v1/' . $qs, $body, tok(), $svc, ['Prefer: return=representation']); }
function db_delete(string $qs, bool $svc = false): void { sb('DELETE', '/rest/v1/' . $qs, null, tok(), $svc); }
function db_rpc(string $fn, array $args = [], bool $svc = false) { return sb('POST', '/rest/v1/rpc/' . $fn, $args ?: new stdClass, tok(), $svc); }

function auth_login(string $email, string $pw): array { return sb('POST', '/auth/v1/token?grant_type=password', ['email' => $email, 'password' => $pw]); }
function auth_refresh(string $rt): array { return sb('POST', '/auth/v1/token?grant_type=refresh_token', ['refresh_token' => $rt]); }
function auth_signup(string $email, string $pw, string $nama, string $hp): array {
    return sb('POST', '/auth/v1/signup', ['email' => $email, 'password' => $pw, 'data' => ['nama' => $nama, 'no_hp' => $hp]]) ?? [];
}

// ---------------------------------------------------------------------
// Sesi login: token disimpan di cookie HttpOnly (tidak terbaca JavaScript)
// ---------------------------------------------------------------------
function save_session(array $s): void {
    cookie_set('pico_at', $s['access_token'], 604800);
    cookie_set('pico_rt', $s['refresh_token'] ?? '', 604800);
    $GLOBALS['TOK'] = $s['access_token'];
}
function clear_session(): void { cookie_del('pico_at'); cookie_del('pico_rt'); unset($GLOBALS['TOK']); }
function jwt_claim(string $t, string $k) {
    $p = explode('.', $t);
    if (count($p) < 2) return null;
    $j = json_decode(base64_decode(strtr($p[1], '-_', '+/')), true);
    return is_array($j) ? ($j[$k] ?? null) : null;
}

/** Pengguna yang sedang login (profil dari tabel profiles), atau null. */
function me(): ?array {
    static $m = false;
    if ($m !== false) return $m;
    $at = $_COOKIE['pico_at'] ?? ''; $rt = $_COOKIE['pico_rt'] ?? '';
    if ($at === '') return $m = null;
    if ((int)jwt_claim($at, 'exp') < time() + 30) { // token hampir habis: perbarui
        try { $s = auth_refresh($rt); save_session($s); $at = $s['access_token']; }
        catch (SbError $x) { clear_session(); return $m = null; }
    }
    $GLOBALS['TOK'] = $at;
    try { $r = db_get('profiles?select=id,role,nama,username,no_hp&id=eq.' . rawurlencode((string)jwt_claim($at, 'sub'))); }
    catch (SbError $x) { clear_session(); return $m = null; }
    return $m = ($r[0] ?? null);
}
function home_for(string $role): string { return ['pelanggan' => '/home', 'penjual' => '/penjual', 'admin' => '/admin'][$role] ?? '/'; }
/** Wajib login dengan peran tertentu; kalau tidak, diarahkan ke halaman yang sesuai. */
function need(string $role): array {
    $m = me();
    if (!$m) redirect('/');
    if ($m['role'] !== $role) redirect(home_for($m['role']));
    return $m;
}

// ---------------------------------------------------------------------
// Keranjang: disimpan di cookie (hanya id menu + jumlah + catatan).
// Harga SELALU dihitung ulang oleh database saat pesanan dibuat.
// ---------------------------------------------------------------------
function cart(): array {
    $c = json_decode($_COOKIE['pico_cart'] ?? '', true);
    return is_array($c) && isset($c['i']) && is_array($c['i']) ? $c : ['s' => 0, 'i' => []];
}
function cart_save(array $c): void {
    $c['i'] = array_values($c['i']);
    $c['i'] ? cookie_set('pico_cart', json_encode($c, JSON_UNESCAPED_UNICODE), 86400) : cookie_del('pico_cart');
}
function cart_detail(): array {
    $c = cart(); $items = []; $total = 0; $stand = null;
    if ($c['i']) {
        $ids = implode(',', array_map('intval', array_column($c['i'], 'id')));
        $by = [];
        foreach (db_get('menu?select=id,nama,harga,stok,tersedia,foto_url&stand_id=eq.' . (int)$c['s'] . '&id=in.(' . $ids . ')') as $r) $by[$r['id']] = $r;
        $stand = db_get('stands?select=id,nama,kios,buka&id=eq.' . (int)$c['s'])[0] ?? null;
        foreach ($c['i'] as $x) {
            $m = $by[$x['id']] ?? null;
            if (!$m || !$m['tersedia'] || $m['stok'] < 1) continue;
            $j = min((int)$x['j'], (int)$m['stok']);
            if ($j < 1) continue;
            $items[] = ['id' => $m['id'], 'nama' => $m['nama'], 'harga' => $m['harga'], 'jml' => $j, 'cat' => $x['c'] ?? '', 'foto' => $m['foto_url'], 'stok' => $m['stok']];
            $total += $m['harga'] * $j;
        }
    }
    return compact('stand', 'items', 'total');
}
