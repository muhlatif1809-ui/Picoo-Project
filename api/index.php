<?php
// index.php : pintu masuk semua halaman. Mencocokkan alamat URL ke fungsi yang sesuai.
require __DIR__ . '/_app/boot.php';
require __DIR__ . '/_app/ui.php';
require __DIR__ . '/_app/pelanggan.php';
require __DIR__ . '/_app/penjual.php';
require __DIR__ . '/_app/admin.php';

$routes = [
    // umum
    'GET /' => 'r_root', 'GET /login' => 'r_login', 'POST /login' => 'r_login_post',
    'GET /daftar' => 'r_daftar', 'POST /daftar' => 'r_daftar_post', 'GET /keluar' => 'r_keluar',
    // pelanggan
    'GET /home' => 'r_home', 'GET /menu' => 'r_menu',
    'POST /keranjang/tambah' => 'r_cart_add', 'GET /keranjang' => 'r_cart', 'POST /keranjang/ubah' => 'r_cart_change',
    'GET /opsi' => 'r_opsi', 'POST /opsi' => 'r_opsi_post', 'GET /bayar' => 'r_bayar', 'POST /bayar' => 'r_bayar_post',
    'GET /qris' => 'r_qris', 'POST /qris/bayar' => 'r_qris_bayar', 'GET /status' => 'r_status', 'GET /riwayat' => 'r_riwayat', 'GET /profil' => 'r_profil',
    // penjual
    'GET /penjual' => 'r_pen_home', 'GET /penjual/riwayat' => 'r_pen_riwayat', 'POST /penjual/status' => 'r_pen_status', 'POST /penjual/buka' => 'r_pen_buka',
    'GET /penjual/menu' => 'r_pen_menu', 'POST /penjual/menu/tambah' => 'r_pen_menu_tambah', 'POST /penjual/menu/simpan' => 'r_pen_menu_simpan',
    'POST /penjual/menu/hapus' => 'r_pen_menu_hapus', 'GET /penjual/profil' => 'r_pen_profil',
    // admin
    'GET /admin' => 'r_adm_home', 'GET /admin/stand' => 'r_adm_stand', 'POST /admin/stand/tambah' => 'r_adm_stand_tambah',
    'POST /admin/stand/penjual' => 'r_adm_stand_penjual', 'POST /admin/stand/reset' => 'r_adm_stand_reset',
    'GET /admin/pengguna' => 'r_adm_pengguna', 'GET /admin/pesanan' => 'r_adm_pesanan',
];


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = (string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

// Hilangkan awalan /PICO saat dijalankan melalui XAMPP
$basePath = '/PICO';

if ($path === $basePath) {
    $path = '/';
} elseif (str_starts_with($path, $basePath . '/')) {
    $path = substr($path, strlen($basePath));
}

$path = rtrim($path, '/') ?: '/';

if ($method === 'POST') check_origin();


try {
    $h = $routes["$method $path"] ?? null;
    if ($h) $h();
    else { http_response_code(404); layout('Tidak ditemukan', '<div class="empty">Halaman tidak ditemukan.</div><div class="bd"><a class="btn sec" href="/">Ke beranda</a></div>'); }
} catch (SbError $x) {
    if ($x->getCode() === 401) { clear_session(); redirect('/'); }
    http_response_code(500);
    layout('Terjadi masalah', '<div class="empty">Terjadi masalah saat menghubungi database:<br><b>' . e($x->getMessage()) . '</b></div><div class="bd"><a class="btn sec" href="/">Coba lagi</a></div>');
}
