<?php
// ui.php : kerangka halaman (layout), navigasi, dan potongan HTML yang dipakai berulang.

const LAY = [
    'dine_in' => 'Dine in',
    'take_away' => 'Take Away'
];

const STATUS_LABEL = [
    'menunggu_bayar' => 'Menunggu bayar',
    'baru' => 'Pesanan baru',
    'diproses' => 'Diproses',
    'siap' => 'Siap diambil',
    'selesai' => 'Selesai',
    'batal' => 'Dibatalkan'
];

/**
 * Membungkus isi halaman dengan HTML, CSS, dan JS.
 * $o: css, wrap, live (ms untuk auto-refresh).
 */
function layout(string $title, string $body, array $o = []): void
{
    $css = $o['css'] ?? 'app';
    $wrap = $o['wrap'] ?? 'app';
    $fl = take_flash();

    // Menentukan alamat dasar aplikasi saat dijalankan di XAMPP atau hosting.
    $scriptPath = str_replace(
        '\\',
        '/',
        $_SERVER['SCRIPT_NAME'] ?? ''
    );

    $base = str_contains($scriptPath, '/PICO/') ? '/PICO' : '';

    // Pastikan header dikirim sebelum output HTML.
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo '<!DOCTYPE html><html lang="id"><head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · PICO</title>'
        . '<link rel="stylesheet" href="' . $base . '/css/' . e($css) . '.css">'
        . '</head>'
        . '<body' . (!empty($o['live']) ? ' data-live="' . (int) $o['live'] . '"' : '') . '>'
        . '<div class="' . e($wrap) . '">' . $body . '</div>';

    if ($fl !== '') {
        echo '<div class="toast">' . e($fl) . '</div>';
    }

    echo '<script src="' . $base . '/js/app.js"></script>'
        . '</body></html>';
}

/**
 * Membuat ikon SVG.
 */
function ico(string $k): string
{
    static $IC = [
    'home' => 'M3 11l9-8 9 8v10H3zM9 21v-6h6v6',
    'cart' => 'M3 4h2l2.5 11h10l2-8H6.5M9 20h.01M17 20h.01',
    'book' => 'M5 3h14v18H5zM5 17h14',
    'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
    'list' => 'M4 6h16M4 12h16M4 18h16'
    ];

    $path = $IC[$k] ?? $IC['home'];

    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="' . $path . '"/></svg>';
}

/**
 * Ikon garis (SVG) untuk tombol, kartu, dan judul.
 * ic('search', 18) -> ikon kaca pembesar ukuran 18px.
 */
function ic(string $k, int $size = 20): string
{
    static $P = [
        'back' => 'M15 18l-6-6 6-6',
        'next' => 'M9 18l6-6-6-6',
        'arrow' => 'M5 12h14M13 6l6 6-6 6',
        'search' => 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-4.3-4.3',
        'cart' => 'M3 4h2l2.5 11h10l2-8H6.5M9 20h.01M17 20h.01',
        'store' => 'M3 9l1.5-5h15L21 9M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8',
        'receipt' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
        'clock' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
        'plus' => 'M12 5v14M5 12h14',
        'wallet' => 'M3 7h18v12H3zM3 7l2-3h14l2 3M16 13h2',
        'bowl' => 'M3 11h18a9 9 0 01-18 0zM8 7c0-1.5 1-2 1-3.5M13 7c0-1.5 1-2 1-3.5',
        'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
        'flame' => 'M12 2c1 4 5 5 5 10a5 5 0 01-10 0c0-2 1-3 2-4 0 2 1 2 2 2 0-3-1-5 1-8z',
        'bell' => 'M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0',
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'users' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21c0-4 3-6 7-6s7 2 7 6M17 3.5a4 4 0 010 7.5M22 21c0-3-2-5-4-5.8',
        'orders' => 'M9 4h6l1 2h3v15H5V6h3zM9 12h6M9 16h4',
        'logout' => 'M9 4H5v16h4M16 8l4 4-4 4M20 12H9',
        'chev' => 'M6 9l6 6 6-6',
        'check' => 'M5 12l5 5L20 7',
        'edit' => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z',
        'x' => 'M6 6l12 12M18 6L6 18',
    ];
    $path = $P[$k] ?? $P['store'];

    $stroke = $k === 'search' ? '#000' : 'currentColor';

    return '<svg class="ic" viewBox="0 0 24 24" width="' . $size . '" height="' . $size
        . '" fill="none" stroke="' . $stroke . '" stroke-width="1.9" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="'
        . $path . '"/></svg>';
}

/**
 * Warna khas tiap kios (dipakai untuk kotak nomor kios).
 * Nomor kios yang sama selalu dapat warna yang sama.
 */
function kios_warna($kios): string
{
    static $C = ['#f28c28', '#2f9e8f', '#6c63d9', '#d9487a', '#3b82c4', '#7a9a2c'];

    return $C[abs((int) $kios) % count($C)];
}

/**
 * Kotak nomor kios: label kecil "Kios" + angka besar.
 */
function kios_tile($kios, string $extra = ''): string
{
    return '<div class="kios-tile ' . $extra . '" style="--k:' . kios_warna($kios) . '"><small>Kios</small><b>'
        . e($kios) . '</b></div>';
}

/**
 * Header halaman gaya baru (judul + keterangan + ikon/jumlah di kanan).
 * $o: back (alamat tombol kembali), icon (nama ikon), count (angka di pojok ikon).
 * $title dan $sub boleh berisi HTML yang sudah di-escape oleh pemanggil.
 */
function hdx(string $title, string $sub = '', array $o = []): string
{
    $h = '<header class="hx">';

    if (!empty($o['back'])) {
        $h .= '<a class="hx-back" href="' . $o['back'] . '">' . ic('back', 18) . '<span>Kembali</span></a>';
    }

    $h .= '<div class="hx-row"><div class="hx-main"><h1>' . $title . '</h1>'
        . ($sub !== '' ? '<p class="hx-sub">' . $sub . '</p>' : '')
        . '</div>';

    if (!empty($o['icon'])) {
        $h .= '<div class="hx-ic">' . ic($o['icon'], 24);
        if (isset($o['count']) && (int) $o['count'] > 0) {
            $h .= '<span class="hx-count">' . (int) $o['count'] . '</span>';
        }
        $h .= '</div>';
    }

    return $h . '</div>' . ($o['extra'] ?? '') . '</header>';
}

/**
 * Saklar on/off (dipakai untuk buka/tutup stand).
 * Berupa tombol di dalam form: pemanggil yang menyiapkan form-nya.
 */
function saklar(bool $on, string $label): string
{
    return '<button type="submit" class="sw ' . ($on ? 'on' : '') . '" role="switch" aria-checked="'
        . ($on ? 'true' : 'false') . '" aria-label="' . e($label) . '"><i></i></button>';
}

/**
 * Keadaan kosong: ikon, judul, keterangan, dan (opsional) tombol.
 */
function empty_state(string $icon, string $title, string $text, string $href = '', string $btn = ''): string
{
    return '<div class="es"><div class="es-ic">' . ic($icon, 30) . '</div><h2>' . e($title) . '</h2><p>' . e($text) . '</p>'
        . ($href !== '' ? '<a class="btn es-btn" href="' . $href . '">' . e($btn) . '</a>' : '')
        . '</div>';
}

/**
 * Membuat navigasi berdasarkan daftar menu.
 */
function nav(array $items, string $on): string
{
    $h = '<nav class="nav">';

    foreach ($items as [$href, $icon, $label]) {
        $h .= '<a href="' . $href . '" class="' . ($on === $href ? 'on' : '') . '">'
            . ico($icon) . $label . '</a>';
    }

    return $h . '</nav>';
}

/**
 * Navigasi pembeli.
 */
function nav_pel(string $on): string
{
    return nav([
        ['/home', 'home', 'Home'],
        ['/keranjang', 'cart', 'Keranjang'],
        ['/riwayat', 'book', 'Riwayat'],
        ['/profil', 'user', 'Profil']
    ], $on);
}

/**
 * Navigasi penjual.
 */
function nav_pen(string $on): string
{
    return nav([
        ['/penjual', 'home', 'Home'],
        ['/penjual/riwayat', 'book', 'Riwayat'],
        ['/penjual/menu', 'list', 'Menu'],
        ['/penjual/profil', 'user', 'Profil']
    ], $on);
}

/**
 * Header biru.
 * $sub boleh berisi HTML yang sudah di-escape oleh pemanggil.
 */
function hd(string $t, string $sub = '', string $back = ''): string
{
    return '<header class="hd">'
        . ($back ? '<a class="back" href="' . $back . '">' . ic('back', 18) . 'Kembali</a>' : '')
        . '<h1>' . $t . '</h1>'
        . ($sub ? '<p>' . $sub . '</p>' : '')
        . '</header>';
}

/**
 * Menampilkan gambar atau placeholder.
 */
function img(?string $u): string
{
    return $u
        ? '<img src="' . e($u) . '" alt="" loading="lazy">'
        : '<span class="ph">🍽️</span>';
}

/**
 * Menampilkan label status pesanan.
 */
function tag(string $status): string
{
    return '<span class="tag s-' . e($status) . '">'
        . e(STATUS_LABEL[$status] ?? $status)
        . '</span>';
}

/**
 * Menampilkan pesan saat data kosong.
 */
function empty_box(string $t): string
{
    return '<div class="empty">' . e($t) . '</div>';
}
