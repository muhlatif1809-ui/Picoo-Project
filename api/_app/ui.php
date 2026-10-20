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
       . '<body' . (!empty($o['live']) ? ' data-live="' . (int)$o['live'] . '"' : '') . '>'
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
        ['/penjual', 'home', 'Pesanan'],
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
        . ($back ? '<a class="back" href="' . $back . '">← Kembali</a>' : '')
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
